<?php
session_start();
// Suppress direct HTML error output for production JSON endpoints
// Use internal logging instead; allow verbose output only when ?debug=1
$isDebug = isset($_GET['debug']);
if ($isDebug) {
    ini_set('display_errors',1); error_reporting(E_ALL);
    header('Content-Type: text/plain; charset=utf-8');
} else {
    ini_set('display_errors',0); ini_set('display_startup_errors',0); error_reporting(E_ALL);
    header('Content-Type: application/json');
}
require_once __DIR__ . '/database.php';
// Centralized auth helper (clears stale sessions and responds with JSON on failure)
require_once __DIR__ . '/includes/auth.php';

// Convert PHP errors/exceptions into JSON so the client doesn't see empty bodies
set_exception_handler(function($ex){
    try { qlog('EXCEPTION: '.$ex->getMessage().' @ '.$ex->getFile().':'.$ex->getLine()); } catch(Throwable $e){}
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['status'=>'error','message'=>'Server error','detail'=>$ex->getMessage()]);
    exit;
});
set_error_handler(function($errno,$errstr,$errfile,$errline){
    try { qlog('ERROR '.$errno.': '.$errstr.' @ '.$errfile.':'.$errline); } catch(Throwable $e){}
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['status'=>'error','message'=>'Server error']);
    exit;
});

function qlog($msg){
    $dir = __DIR__ . '/logs';
    if(!is_dir($dir)) @mkdir($dir,0775,true);
    @file_put_contents($dir.'/quick_order.log','['.date('Y-m-d H:i:s').'] '.$msg."\n",FILE_APPEND);
}

function respond($arr, $code=200){
    http_response_code($code);
    if(isset($_GET['debug'])){
        header('Content-Type: text/plain; charset=utf-8');
        echo "QUICK_ORDER DEBUG RESPONSE\n";
        echo json_encode($arr, JSON_PRETTY_PRINT);
    } else {
        echo json_encode($arr);
    }
    exit;
}
// Validate session and referenced account row using centralized helper.
// This will respond with a 401 JSON payload and exit if invalid.
$userId = require_valid_user_json();

// Support either single product fields or multi-items (JSON)
$rawItemsJson = $_POST['items'] ?? '';
$multiItems = [];
$isMulti = false;
// Capture payment method & optional GCash fields
$paymentMethod = strtolower(trim($_POST['payment_method'] ?? ''));
if(!in_array($paymentMethod,['cash','gcash','card','bank','other'])) $paymentMethod = 'cash';
// GCash receipt (file) and amount if provided
$gcashPaidRaw = isset($_POST['gcash_paid']) ? trim((string)$_POST['gcash_paid']) : '';
$gcashPaid = ($gcashPaidRaw !== '' && is_numeric($gcashPaidRaw)) ? number_format((float)$gcashPaidRaw,2,'.','') : null;

$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$size = trim($_POST['size'] ?? '');
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
$product_name_hint = trim($_POST['product_name'] ?? '');

if ($rawItemsJson) {
    $decoded = json_decode($rawItemsJson, true);
    if (is_array($decoded) && count($decoded) > 0) {
        foreach ($decoded as $it) {
                $pid = (int)($it['product_id'] ?? 0);
                $qty = (int)($it['quantity'] ?? 0);
                $sz  = trim($it['size'] ?? 'Default');
                $designoption = isset($it['designoption_id']) ? (int)$it['designoption_id'] : null;
                if ($pid > 0 && $qty > 0) {
                    $multiItems[] = ['product_id'=>$pid,'quantity'=>$qty,'size'=>$sz,'designoption_id'=>$designoption];
                }
            }
        if (count($multiItems) > 0) { $isMulti = true; }
    }
}

if (!$isMulti) {
    if($product_id<=0){ qlog('Invalid product_id='.$product_id); respond(['status'=>'error','message'=>'Invalid product']); }
    if($quantity<=0) $quantity = 1;
    if($size==='') $size = 'Default';
}

if($conn->connect_error){ qlog('DB connection failed: '.$conn->connect_error); respond(['status'=>'error','message'=>'DB connection failed']); }

// Fetch price(s) & name(s)
$total = 0.00;
$primaryName = '';
if ($isMulti) {
    foreach ($multiItems as &$it) {
        $p = 0; $nm='';
        // Adaptive products table column discovery
        $prodCols = [];
        if ($res = $conn->query('SHOW COLUMNS FROM products')) { while($r=$res->fetch_assoc()){ $prodCols[strtolower($r['Field'])]=$r['Field']; } $res->free(); }
        $prodPk = null; foreach(['product_id','id','prod_id','products_id'] as $c){ if(isset($prodCols[$c])){ $prodPk=$prodCols[$c]; break; } }
        $prodPriceCol = null; foreach(['price','unit_price','amount','cost'] as $c){ if(isset($prodCols[$c])){ $prodPriceCol=$prodCols[$c]; break; } }
        $prodNameCol = null; foreach(['product_name','name','title'] as $c){ if(isset($prodCols[$c])){ $prodNameCol=$prodCols[$c]; break; } }

        // Fallback to products_sub price when base price column missing or 0
        if ($prodPk && $prodNameCol) {
            $sqlP = 'SELECT ' . ($prodPriceCol ? ('p.'.$prodPriceCol) : '0') . ' AS base_price, p.' . $prodNameCol . ' AS prod_name FROM products p WHERE p.' . $prodPk . '=? LIMIT 1';
            if ($stmt = $conn->prepare($sqlP)) {
                $stmt->bind_param('i',$it['product_id']);
                if($stmt->execute()) {
                    $stmt->bind_result($basePrice,$prodNameVal); $stmt->fetch(); $stmt->close();
                    // Derive sub price (size/attribute) or fallback any non-zero price
                    $subPrice = null; $anyPrice = null;
                    if ($subChk = $conn->prepare("SELECT MAX(CASE WHEN price>0 THEN price END) FROM products_sub WHERE product_id=?")) {
                        $subChk->bind_param('i',$it['product_id']);
                        if($subChk->execute()){ $subChk->bind_result($anyPrice); $subChk->fetch(); }
                        $subChk->close();
                    }
                    $effectivePrice = ($basePrice && $basePrice>0) ? $basePrice : ($anyPrice ?? 0);
                    $p = $effectivePrice; $nm = $prodNameVal;
                } else { $stmt->close(); }
            }
        }
        $it['price']=$p; $it['name']=$nm; $total += ($p * $it['quantity']); if ($primaryName==='') $primaryName = $nm;
    }
    if ($total <= 0){ qlog('Zero total multi'); respond(['status'=>'error','message'=>'No valid items to order']); }
} else {
    $price = 0.00; $nm=''; $prodExists=false;
    $prodCols = [];
    if ($res = $conn->query('SHOW COLUMNS FROM products')) { while($r=$res->fetch_assoc()){ $prodCols[strtolower($r['Field'])]=$r['Field']; } $res->free(); }
    $prodPk = null; foreach(['product_id','id','prod_id','products_id'] as $c){ if(isset($prodCols[$c])){ $prodPk=$prodCols[$c]; break; } }
    $prodPriceCol = null; foreach(['price','unit_price','amount','cost'] as $c){ if(isset($prodCols[$c])){ $prodPriceCol=$prodCols[$c]; break; } }
    $prodNameCol = null; foreach(['product_name','name','title'] as $c){ if(isset($prodCols[$c])){ $prodNameCol=$prodCols[$c]; break; } }
    if($prodPk && $prodNameCol){
        $sqlP = 'SELECT ' . ($prodPriceCol ? ('p.'.$prodPriceCol) : '0') . ' AS base_price, p.' . $prodNameCol . ' AS prod_name FROM products p WHERE p.' . $prodPk . '=? LIMIT 1';
        if($stmt = $conn->prepare($sqlP)){
            $stmt->bind_param('i',$product_id);
            if($stmt->execute()){
                $stmt->bind_result($basePrice,$prodNameVal); if($stmt->fetch()){ $prodExists=true; $nm=$prodNameVal; }
                $stmt->close();
                $anyPrice = null;
                if($subStmt = $conn->prepare('SELECT MAX(CASE WHEN price>0 THEN price END) FROM products_sub WHERE product_id=?')){
                    $subStmt->bind_param('i',$product_id); if($subStmt->execute()){ $subStmt->bind_result($anyPrice); $subStmt->fetch(); } $subStmt->close();
                }
                $price = ($basePrice && $basePrice>0) ? $basePrice : ($anyPrice ?? 0);
                $primaryName = $nm;
            } else { $stmt->close(); }
        }
    }
    if(!$prodExists){ qlog('Product not found id='.$product_id); respond(['status'=>'error','message'=>'Product not found']); }
    $total = $price * $quantity;
    if ($product_name_hint && $primaryName==='') $primaryName = $product_name_hint;
}
$totalFormatted = number_format($total,2,'.','');

// Fetch user address & phone (baseline from profile)
$userAddress = $userPhone = '';
if($stmt = $conn->prepare('SELECT ' . ACCOUNT_ADDRESS_COL . ', ' . ACCOUNT_PHONE_COL . ' FROM ' . ACCOUNT_TABLE . ' WHERE ' . ACCOUNT_ID_COL . '=? LIMIT 1')) {
    $stmt->bind_param('i',$userId);
    if($stmt->execute()) {
        $stmt->bind_result($userAddress,$userPhone);
        $stmt->fetch();
    }
    $stmt->close();
}
// Allow override via checkout POST fields (cart checkout scenario)
$postedAddress = trim($_POST['delivery_address'] ?? '');
$postedPhone   = preg_replace('/\D+/','', $_POST['delivery_phone'] ?? '');
if($postedAddress !== '') $userAddress = $postedAddress;
if($postedPhone   !== '') $userPhone   = $postedPhone;

if(!$userAddress || !$userPhone) {
    qlog('Missing profile data address or phone (after override check)');
    respond(['status'=>'need_profile','message'=>'Please complete your address and phone number before ordering.','redirect'=>'profile.php?complete_profile=1']);
}

// Adaptive orders table detection (if not already defined)
if (!defined('ORDERS_TABLE')) {
    define('ORDERS_TABLE','orders');
}
// Collect columns (case-insensitive map)
$orderCols = [];
if ($res = $conn->query('SHOW COLUMNS FROM ' . ORDERS_TABLE)) { while($r=$res->fetch_assoc()){ $orderCols[strtolower($r['Field'])] = $r['Field']; } $res->free(); }
$fkCol = 'user_id'; foreach(['customer_id','user_id','account_id','cust_id'] as $c){ if(isset($orderCols[$c])) { $fkCol = $orderCols[$c]; break; } }
if(!defined('ORDERS_ACCOUNT_FK_COL')) define('ORDERS_ACCOUNT_FK_COL',$fkCol);
$pkCol = 'order_id'; foreach(['order_id','id','orders_id'] as $c){ if(isset($orderCols[$c])) { $pkCol=$orderCols[$c]; break; } }
if(!defined('ORDERS_PK_COL')) define('ORDERS_PK_COL',$pkCol);
$createdColDetected = isset($orderCols['created_at']) ? $orderCols['created_at'] : (isset($orderCols['created']) ? $orderCols['created'] : null);
$createdAt = date('Y-m-d H:i:s');
// Debounce duplicate: prevent new pending order with same product(s) in last 30s
$productIdsToCheck = [];
if ($isMulti) { foreach($multiItems as $it) { $productIdsToCheck[] = (int)$it['product_id']; } }
else { $productIdsToCheck[] = $product_id; }
$placeholders = implode(',', array_fill(0, count($productIdsToCheck), '?'));
if ($placeholders) {
    // Use detected orders column name for order status (support snake_case or legacy camelCase)
    $orderStatusColName = isset($orderCols['orderstatus']) ? $orderCols['orderstatus'] : (isset($orderCols['order_status']) ? $orderCols['order_status'] : 'order_status');
    // Prefer orders.product_id if it exists; otherwise fall back to order_items join
    $ordersProductCol = isset($orderCols['product_id']) ? $orderCols['product_id'] : null;
    if ($ordersProductCol) {
        $types = str_repeat('i', count($productIdsToCheck)+1);
        $createdClause = $createdColDetected ? (' AND ' . $createdColDetected . ' >= (NOW() - INTERVAL 30 SECOND)') : '';
        $query = 'SELECT COUNT(*) FROM ' . ORDERS_TABLE . ' WHERE ' . ORDERS_ACCOUNT_FK_COL . '=? AND ' . $orderStatusColName . "='Pending'" . $createdClause . ' AND ' . $ordersProductCol . ' IN (' . $placeholders . ')';
        if ($stmt = $conn->prepare($query)) {
            $params = array_merge([$userId], $productIdsToCheck);
            $stmt->bind_param($types, ...$params);
            if ($stmt->execute()) { $stmt->bind_result($dupCount); $stmt->fetch(); if (($dupCount??0) > 0) { qlog('Duplicate blocked'); $stmt->close(); respond(['status'=>'duplicate','message'=>'Recent pending order already placed. Please wait a moment.']); } }
            $stmt->close();
        }
    } else {
        // Check if order_items table exists; if so, use it for duplicate detection
        $hasOrderItems=false; if($chkOi=$conn->query("SHOW TABLES LIKE 'order_items'")){ $hasOrderItems = $chkOi->num_rows>0; $chkOi->free(); }
        if ($hasOrderItems) {
            $types = 'i' . str_repeat('i', count($productIdsToCheck));
            $createdClause = $createdColDetected ? (' AND o.' . $createdColDetected . ' >= (NOW() - INTERVAL 30 SECOND)') : '';
            $query = 'SELECT COUNT(DISTINCT o.' . ORDERS_PK_COL . ') FROM ' . ORDERS_TABLE . ' o JOIN order_items oi ON oi.order_id = o.' . ORDERS_PK_COL . ' WHERE o.' . ORDERS_ACCOUNT_FK_COL . '=? AND o.' . $orderStatusColName . "='Pending'" . $createdClause . ' AND oi.product_id IN (' . $placeholders . ')';
            if ($stmt = $conn->prepare($query)) {
                $params = array_merge([$userId], $productIdsToCheck);
                $stmt->bind_param($types, ...$params);
                if ($stmt->execute()) { $stmt->bind_result($dupCount); $stmt->fetch(); if (($dupCount??0) > 0) { qlog('Duplicate blocked (order_items)'); $stmt->close(); respond(['status'=>'duplicate','message'=>'Recent pending order already placed. Please wait a moment.']); } }
                $stmt->close();
            }
        } // else: skip duplicate detection to avoid SQL errors on unknown schema
    }
}

// Secondary duplicate guard: order signature per user for 30 seconds to prevent rapid double submits
// Normalize size (trim, lowercase) and collapse identical items to aggregate qty to reduce false negatives.
$sigMap = [];
if ($isMulti) {
    foreach($multiItems as $it){
        $pid = (int)$it['product_id'];
        $sz  = strtolower(trim($it['size'] ?? 'Default'));
        $key = $pid . ':' . $sz;
        $sigMap[$key] = ($sigMap[$key] ?? 0) + (int)$it['quantity'];
    }
} else {
    $pid = (int)$product_id; $sz = strtolower(trim($size ?: 'Default'));
    $sigMap[$pid . ':' . $sz] = (int)$quantity ?: 1;
}
$sigParts = [];
foreach($sigMap as $k=>$qty){ $sigParts[] = $k . ':' . $qty; }
sort($sigParts);
$orderSignature = sha1(implode('|',$sigParts));
$sigDir = __DIR__ . '/logs'; if(!is_dir($sigDir)) @mkdir($sigDir,0775,true);
$sigPath = $sigDir . '/order_sig_' . $userId . '.json';
$prev = @file_get_contents($sigPath); $prevData = $prev ? json_decode($prev,true) : null;
if (is_array($prevData) && isset($prevData['sig'],$prevData['time'])) {
    if ($prevData['sig'] === $orderSignature && (time() - (int)$prevData['time']) < 30) {
        qlog('Duplicate blocked (signature)');
        respond(['status'=>'duplicate','message'=>'Order already submitted. Please wait a moment.']);
    }
}
@file_put_contents($sigPath, json_encode(['sig'=>$orderSignature,'time'=>time()]));

// Insert order adaptively based on existing columns
$cols = []; $placeholders = []; $types = ''; $values = [];
$isPartial = isset($_POST['isPartialPayment']) ? (int)$_POST['isPartialPayment'] : 0; // 1 partial, 0 full
// partial amount (optional)
$partialAmount = null;
// Accept both legacy 'partial_amount' and new shorter 'partial' POST names
$paRaw = null;
if (isset($_POST['partial'])) $paRaw = $_POST['partial'];
if ($paRaw !== null) {
    $pa = preg_replace('/[^0-9\.]/','', (string)$paRaw);
    if ($pa !== '') {
        $partialAmount = number_format((float)$pa, 2, '.', '');
    }
}
$orderStatus='Pending'; $deliveryStatus='Pending';
$legacyProdId = $isMulti ? $multiItems[0]['product_id'] : $product_id;
$legacySize   = $isMulti ? $multiItems[0]['size']       : $size;
$legacyQty    = $isMulti ? $multiItems[0]['quantity']   : $quantity;
// If orders table has a single designoption_id column, store the first item's design (best effort)
$legacyDesign = null;
if ($isMulti) {
    foreach ($multiItems as $it) { if (!empty($it['designoption_id'])) { $legacyDesign = (int)$it['designoption_id']; break; } }
}

// Mandatory: FK, TotalAmount, OrderStatus, created_at if exist else fallback to current timestamp later
$fkColReal = ORDERS_ACCOUNT_FK_COL;
$createdColReal = isset($orderCols['created_at']) ? $orderCols['created_at'] : null;
// Helper to add param
// Safe add helper that allows passing expressions or scalars without reference errors.
$add = function($col, $type, $var, $byRef=false) use (&$cols,&$placeholders,&$types,&$values) {
    $cols[] = $col; $placeholders[]='?'; $types.=$type; 
    if($byRef) { $values[] = &$var; } else { $values[] = $var; }
};

// Always include FK
// Add base columns
$add($fkColReal,'i',$userId);
if(isset($orderCols['product_id'])) $add($orderCols['product_id'],'i',$legacyProdId);
if(isset($orderCols['size'])) $add($orderCols['size'],'s',$legacySize);
if(isset($orderCols['quantity'])) $add($orderCols['quantity'],'i',$legacyQty);
if(isset($orderCols['designoption_id']) && $legacyDesign) $add($orderCols['designoption_id'],'i',$legacyDesign);
if(isset($orderCols['ispartialpayment'])) $add($orderCols['ispartialpayment'],'i',$isPartial); else if(isset($orderCols['partial_payment'])) $add($orderCols['partial_payment'],'i',$isPartial);
if(isset($orderCols['totalamount'])) $add($orderCols['totalamount'],'s',$totalFormatted); else if(isset($orderCols['total_amount'])) $add($orderCols['total_amount'],'s',$totalFormatted);
// include partial_amount if orders table has a column for it
if ($partialAmount !== null) {
    // common column names to check
    // prefer canonical 'partial', but accept other modern synonyms if present in DB
    foreach(['partial','amount_paid','downpayment','deposit'] as $c) {
    if (isset($orderCols[$c])) { $add($orderCols[$c],'s',$partialAmount); break; }
    }
}
if(isset($orderCols['orderstatus'])) $add($orderCols['orderstatus'],'s',$orderStatus); else if(isset($orderCols['order_status'])) $add($orderCols['order_status'],'s',$orderStatus);
if(isset($orderCols['deliveryaddress'])) $add($orderCols['deliveryaddress'],'s',$userAddress); else if(isset($orderCols['delivery_address'])) $add($orderCols['delivery_address'],'s',$userAddress);
if(isset($orderCols['deliverystatus'])) $add($orderCols['deliverystatus'],'s',$deliveryStatus); else if(isset($orderCols['delivery_status'])) $add($orderCols['delivery_status'],'s',$deliveryStatus);
if(isset($orderCols['phone_number'])) $add($orderCols['phone_number'],'s',$userPhone); else if(isset($orderCols['phone'])) $add($orderCols['phone'],'s',$userPhone);
if($createdColDetected) $add($createdColDetected,'s',$createdAt);

if(empty($cols)) { qlog('No writable columns in orders table'); respond(['status'=>'error','message'=>'No writable columns found in orders table']); }
$sql = 'INSERT INTO ' . ORDERS_TABLE . ' (' . implode(',', $cols) . ') VALUES (' . implode(',', $placeholders) . ')';
if(!$stmt = $conn->prepare($sql)) { qlog('Prepare failed '.$conn->error.' SQL='.$sql); respond(['status'=>'error','message'=>'Prepare failed: '.$conn->error,'sql'=>$sql]); }
// Bind params dynamically
$stmt->bind_param($types, ...$values);
if(!$stmt->execute()) { $m=$stmt->error; qlog('Insert failed '.$m.' SQL='.$sql); $stmt->close(); respond(['status'=>'error','message'=>'Insert failed: '.$m,'sql'=>$sql]); }
$orderId = $stmt->insert_id; $stmt->close();

// Insert order_items for multi or (optional) single for future consistency
    if ($isMulti) {
    // Insert order_items; include designoption_id if the column exists
    // First, ensure order_items table exists; if not, skip gracefully
    $hasOrderItems=false; if($chkOi=$conn->query("SHOW TABLES LIKE 'order_items'")){ $hasOrderItems = $chkOi->num_rows>0; $chkOi->free(); }
    if ($hasOrderItems) {
        $orderItemsCols = [];
        if ($resCols = $conn->query('SHOW COLUMNS FROM order_items')) { while($r=$resCols->fetch_assoc()){ $orderItemsCols[strtolower($r['Field'])]=$r['Field']; } $resCols->free(); }
        $hasDesignInOrderItems = isset($orderItemsCols['designoption_id']) || isset($orderItemsCols['design_option_id']) || isset($orderItemsCols['design_id']);
        if ($hasDesignInOrderItems) {
            $colName = isset($orderItemsCols['designoption_id']) ? $orderItemsCols['designoption_id'] : (isset($orderItemsCols['design_option_id']) ? $orderItemsCols['design_option_id'] : $orderItemsCols['design_id']);
            $insSql = "INSERT INTO order_items (order_id, product_id, size, quantity, line_price, {$colName}) VALUES (?,?,?,?,?,?)";
            if ($ins = $conn->prepare($insSql)) {
                foreach ($multiItems as $it) {
                    $linePrice = number_format($it['price'] * $it['quantity'], 2, '.', '');
                    $did = isset($it['designoption_id']) && $it['designoption_id'] ? (int)$it['designoption_id'] : null;
                    $ins->bind_param('iisisi', $orderId, $it['product_id'], $it['size'], $it['quantity'], $linePrice, $did);
                    $ins->execute();
                }
                $ins->close();
            }
        } else {
            if ($ins = $conn->prepare('INSERT INTO order_items (order_id, product_id, size, quantity, line_price) VALUES (?,?,?,?,?)')) {
                foreach ($multiItems as $it) {
                    $linePrice = number_format($it['price'] * $it['quantity'], 2, '.', '');
                    $ins->bind_param('iisis', $orderId, $it['product_id'], $it['size'], $it['quantity'], $linePrice);
                    $ins->execute();
                }
                $ins->close();
            }
        }
    }
} else {
    // Optional: create matching single line (keeps future compatibility). Include designoption_id if exists in order_items
    $hasOrderItems=false; if($chkOi=$conn->query("SHOW TABLES LIKE 'order_items'")){ $hasOrderItems = $chkOi->num_rows>0; $chkOi->free(); }
    if ($hasOrderItems) {
        $orderItemsCols = [];
        if ($resCols = $conn->query('SHOW COLUMNS FROM order_items')) { while($r=$resCols->fetch_assoc()){ $orderItemsCols[strtolower($r['Field'])]=$r['Field']; } $resCols->free(); }
        $hasDesignInOrderItems = isset($orderItemsCols['designoption_id']) || isset($orderItemsCols['design_option_id']) || isset($orderItemsCols['design_id']);
        if ($hasDesignInOrderItems) {
            $colName = isset($orderItemsCols['designoption_id']) ? $orderItemsCols['designoption_id'] : (isset($orderItemsCols['design_option_id']) ? $orderItemsCols['design_option_id'] : $orderItemsCols['design_id']);
            if ($ins = $conn->prepare("INSERT INTO order_items (order_id, product_id, size, quantity, line_price, {$colName}) VALUES (?,?,?,?,?,?)")) {
                $linePrice = number_format(($price ?? 0) * $quantity, 2, '.', '');
                $did = isset($multiItems[0]['designoption_id']) ? (int)$multiItems[0]['designoption_id'] : null;
                $ins->bind_param('iisisi',$orderId,$product_id,$size,$quantity,$linePrice,$did);
                $ins->execute();
                $ins->close();
            }
        } else {
            if ($ins = $conn->prepare('INSERT INTO order_items (order_id, product_id, size, quantity, line_price) VALUES (?,?,?,?,?)')) {
                $linePrice = number_format(($price ?? 0) * $quantity, 2, '.', '');
                $ins->bind_param('iisis',$orderId,$product_id,$size,$quantity,$linePrice);
                $ins->execute();
                $ins->close();
            }
        }
    }
}

// Remove from cart for each product id
if(!defined('CART_TABLE')) {
    define('CART_TABLE','cart');
    $cartCols=[]; if($res=$conn->query('SHOW COLUMNS FROM '.CART_TABLE)){while($r=$res->fetch_assoc()){$cartCols[strtolower($r['Field'])]=$r['Field'];} $res->free();}
    $userFk='user_id'; foreach(['customer_id','user_id','account_id'] as $c){ if(isset($cartCols[$c])) { $userFk=$cartCols[$c]; break; } }
    define('CART_USER_FK_COL',$userFk);
    $prodFk='product_id'; foreach(['product_id','prod_id','item_id'] as $c){ if(isset($cartCols[$c])) { $prodFk=$cartCols[$c]; break; } }
    define('CART_PRODUCT_FK_COL',$prodFk);
}
if ($isMulti) {
    $del = $conn->prepare('DELETE FROM '.CART_TABLE.' WHERE '.CART_USER_FK_COL.'=? AND '.CART_PRODUCT_FK_COL.'=?');
    if($del){ foreach($productIdsToCheck as $pid){ $del->bind_param('ii',$userId,$pid); $del->execute(); } $del->close(); }
} else {
    $del = $conn->prepare('DELETE FROM '.CART_TABLE.' WHERE '.CART_USER_FK_COL.'=? AND '.CART_PRODUCT_FK_COL.'=?');
    if($del){ $del->bind_param('ii',$userId,$product_id); $del->execute(); $del->close(); }
}

// Build redirect with query params
if ($isMulti) {
    $_SESSION['flash_profile_order_success'] = [ 'count' => count($multiItems) ];
    $redirect = 'profile.php#ordersPanel';
} else {
    $_SESSION['flash_profile_order_success'] = [ 'name' => $primaryName, 'qty' => $legacyQty ];
    $redirect = 'profile.php#ordersPanel';
}
qlog('SUCCESS order_id='.$orderId.' redirect='.$redirect.' items=' . ($isMulti?count($multiItems):1) . ' partial='.$isPartial);
// Insert initial payment row if method is cash (full) or gcash partial/paid amount provided
$createdPaymentId = null; $receiptRelPath = null;
// Discover payments table columns if it exists
$hasPaymentsTable=false; if($chk=$conn->query("SHOW TABLES LIKE 'payments'")){ $hasPaymentsTable = $chk->num_rows>0; $chk->free(); }
if($hasPaymentsTable){
    try {
        $payCols=[]; if($pr=$conn->query('SHOW COLUMNS FROM payments')){ while($r=$pr->fetch_assoc()){ $payCols[strtolower($r['Field'])]=$r['Field']; } $pr->free(); }
        $payOrderCol = $payCols['order_id'] ?? 'order_id';
        $payCustCol  = $payCols['customer_id'] ?? ($payCols['user_id'] ?? 'customer_id');
        $payAmtCol   = $payCols['payment_amount'] ?? ($payCols['amount'] ?? 'payment_amount');
        $payMethodCol= $payCols['payment_method'] ?? ($payCols['method'] ?? 'payment_method');
        $payStatusCol= $payCols['payment_status'] ?? ($payCols['status'] ?? 'payment_status');
        $payDateCol  = $payCols['payment_date'] ?? ($payCols['date'] ?? 'payment_date');
    // Optional receipt column discovery (support multiple common names including img_proof)
    $receiptCol  = $payCols['receipt_url'] ?? ($payCols['receipt'] ?? ($payCols['proof_image'] ?? ($payCols['payment_proof'] ?? ($payCols['img_proof'] ?? null))));
        $insertPayment = false;
        $initialStatus = 'Pending';
        $initialAmount = 0.00;
        if($paymentMethod==='cash'){
            // For cash, we record a pending payment with amount 0 (will be updated when collected) OR full amount immediately if full payment
            if($isPartial){
                $initialStatus = 'Partial';
                $initialAmount = ($partialAmount !== null) ? (float)$partialAmount : 0.00;
            } else {
                // Full cash payment will be recorded when actually paid; start as Pending
                $initialStatus = 'Pending';
                $initialAmount = 0.00;
            }
            $insertPayment = true;
        } elseif($paymentMethod==='gcash') {
            // If user provided a gcash paid amount treat as Partial (if not full) or Paid if equal to total
            if($gcashPaid !== null){
                $paidVal = (float)$gcashPaid;
                $initialAmount = $paidVal;
                if(abs($paidVal - (float)$totalFormatted) < 0.01){
                    $initialStatus='Paid';
                } else {
                    $initialStatus='Partial';
                }
                $insertPayment = true;
            }
        }
        // Handle receipt upload when gcash and file posted
        if($paymentMethod==='gcash' && isset($_FILES['gcash_receipt']) && $_FILES['gcash_receipt']['error']===UPLOAD_ERR_OK){
            $tmp = $_FILES['gcash_receipt']['tmp_name'];
            $orig = $_FILES['gcash_receipt']['name'];
            $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            if(!in_array($ext,['jpg','jpeg','png','webp','gif'])) $ext='jpg';
            $payDir = __DIR__ . '/uploads/payments';
            if(!is_dir($payDir)) @mkdir($payDir,0775,true);
            $destFile = 'order_'.$orderId.'_'.uniqid().'_receipt.'.$ext;
            $destPath = $payDir . '/' . $destFile;
            if(@move_uploaded_file($tmp,$destPath)){
                $receiptRelPath = 'uploads/payments/'.$destFile;
            }
        }
        if($insertPayment){
            $cols=[]; $ph=[]; $types=''; $vals=[];
            $addP = function($col,$type,&$var) use (&$cols,&$ph,&$types,&$vals){ $cols[]=$col; $ph[]='?'; $types.=$type; $vals[]=&$var; };
            $addP($payCustCol,'i',$userId);
            $addP($payOrderCol,'i',$orderId);
            $amtStr = number_format($initialAmount,2,'.',''); $addP($payAmtCol,'s',$amtStr);
            $methodStr = ($paymentMethod==='gcash' ? 'GCash' : 'Cash');
            $addP($payMethodCol,'s',$methodStr);
            $addP($payStatusCol,'s',$initialStatus);
            if($receiptCol && $receiptRelPath){
                // Store relative path (uploads/payments/...) for DB; API will normalize to absolute
                $addP($receiptCol,'s',$receiptRelPath);
            }
            $sqlPay = 'INSERT INTO payments (' . implode(',', $cols) . ') VALUES (' . implode(',', $ph) . ')';
            if($pst = $conn->prepare($sqlPay)){
                $pst->bind_param($types, ...$vals);
                if($pst->execute()){ $createdPaymentId = $pst->insert_id; }
                $pst->close();
            }
        } elseif ($paymentMethod==='gcash' && $receiptRelPath) {
            // If a GCash receipt was uploaded but no amount provided, persist a zero-amount Partial row to attach the receipt
            try {
                $cols=[]; $ph=[]; $types=''; $vals=[];
                $addP = function($col,$type,&$var) use (&$cols,&$ph,&$types,&$vals){ $cols[]=$col; $ph[]='?'; $types.=$type; $vals[]=&$var; };
                $addP($payCustCol,'i',$userId);
                $addP($payOrderCol,'i',$orderId);
                $zeroStr = number_format(0,2,'.',''); $addP($payAmtCol,'s',$zeroStr);
                $methodStr = 'GCash'; $addP($payMethodCol,'s',$methodStr);
                $statusStr = 'Partial'; $addP($payStatusCol,'s',$statusStr);
                if($receiptCol){ $addP($receiptCol,'s',$receiptRelPath); }
                $sqlPay = 'INSERT INTO payments (' . implode(',', $cols) . ') VALUES (' . implode(',', $ph) . ')';
                if($pst = $conn->prepare($sqlPay)){
                    $pst->bind_param($types, ...$vals);
                    if($pst->execute()){ $createdPaymentId = $pst->insert_id; }
                    $pst->close();
                }
            } catch (Throwable $e) { qlog('payments placeholder error: '.$e->getMessage()); }
        }
    } catch (Throwable $e) {
        // Do not fail the whole order if payments insert fails; log and continue
        qlog('payments error: '.$e->getMessage());
    }
}
respond([
    'status'=>'ok',
    'order_id'=>$orderId,
    'redirect'=>$redirect,
    'multi'=>$isMulti,
    'items_count'=>$isMulti?count($multiItems):1,
    'partial'=>$isPartial,
    'payment_method'=>$paymentMethod,
    'payment_created_id'=>$createdPaymentId,
    'receipt_url'=>$receiptRelPath
]);
?>