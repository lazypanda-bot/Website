<?php
session_start();
ini_set('display_errors',1);error_reporting(E_ALL);
$isDebug = isset($_GET['debug']);
if(!$isDebug) header('Content-Type: application/json');
require_once __DIR__ . '/database.php';
// Centralized auth helper (clears stale sessions and responds with JSON on failure)
require_once __DIR__ . '/includes/auth.php';

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
        if ($stmt = $conn->prepare('SELECT price, product_name FROM products WHERE product_id=? LIMIT 1')) {
            $stmt->bind_param('i',$it['product_id']);
            if($stmt->execute()) {
                $stmt->bind_result($p,$nm);
                if($stmt->fetch()) {
                    $it['price']=$p; $it['name']=$nm; $total += ($p * $it['quantity']);
                    if ($primaryName==='') $primaryName = $nm;
                }
            }
            $stmt->close();
        }
    }
    if ($total <= 0){ qlog('Zero total multi'); respond(['status'=>'error','message'=>'No valid items to order']); }
} else {
    $price = 0.00; $nm=''; $prodExists=false;
    if($stmt = $conn->prepare('SELECT price, product_name FROM products WHERE product_id=? LIMIT 1')) {
        $stmt->bind_param('i',$product_id);
        if($stmt->execute()) {
            $stmt->bind_result($price,$nm);
            if($stmt->fetch()) { $prodExists = true; $primaryName = $nm; }
        }
        $stmt->close();
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
$createdAt = date('Y-m-d H:i:s');
// Debounce duplicate: prevent new pending order with same product(s) in last 30s
$productIdsToCheck = [];
if ($isMulti) { foreach($multiItems as $it) { $productIdsToCheck[] = (int)$it['product_id']; } }
else { $productIdsToCheck[] = $product_id; }
$placeholders = implode(',', array_fill(0, count($productIdsToCheck), '?'));
if ($placeholders) {
    $types = str_repeat('i', count($productIdsToCheck)+1);
    $query = 'SELECT COUNT(*) FROM ' . ORDERS_TABLE . ' WHERE ' . ORDERS_ACCOUNT_FK_COL . '=? AND OrderStatus="Pending" AND created_at >= (NOW() - INTERVAL 30 SECOND) AND product_id IN (' . $placeholders . ')';
    if ($stmt = $conn->prepare($query)) {
        $params = array_merge([$userId], $productIdsToCheck);
        $stmt->bind_param($types, ...$params);
        if ($stmt->execute()) { $stmt->bind_result($dupCount); $stmt->fetch(); if (($dupCount??0) > 0) { qlog('Duplicate blocked'); $stmt->close(); respond(['status'=>'duplicate','message'=>'Recent pending order already placed. Please wait a moment.']); } }
        $stmt->close();
    }
}

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

// Mandatory: FK, TotalAmount, OrderStatus, created_at if exist else fallback to current timestamp later
$fkColReal = ORDERS_ACCOUNT_FK_COL;
$createdColReal = isset($orderCols['created_at']) ? $orderCols['created_at'] : null;
// Helper to add param
$add = function($col, $type, &$var) use (&$cols,&$placeholders,&$types,&$values) {
    $cols[] = $col; $placeholders[]='?'; $types.=$type; $values[]=&$var; };

// Always include FK
$add($fkColReal,'i',$userId);
if(isset($orderCols['product_id'])) $add($orderCols['product_id'],'i',$legacyProdId);
if(isset($orderCols['size'])) $add($orderCols['size'],'s',$legacySize);
if(isset($orderCols['quantity'])) $add($orderCols['quantity'],'i',$legacyQty);
if(isset($orderCols['ispartialpayment'])) $add($orderCols['ispartialpayment'],'i',$isPartial);
if(isset($orderCols['totalamount'])) $add($orderCols['totalamount'],'s',$totalFormatted);
// include partial_amount if orders table has a column for it
if ($partialAmount !== null) {
    // common column names to check
    // prefer canonical 'partial', but accept other modern synonyms if present in DB
    foreach(['partial','amount_paid','downpayment','deposit'] as $c) {
        if (isset($orderCols[$c])) { $add($orderCols[$c],'s',$partialAmount); break; }
    }
}
if(isset($orderCols['orderstatus'])) $add($orderCols['orderstatus'],'s',$orderStatus);
if(isset($orderCols['deliveryaddress'])) $add($orderCols['deliveryaddress'],'s',$userAddress);
if(isset($orderCols['deliverystatus'])) $add($orderCols['deliverystatus'],'s',$deliveryStatus);
if(isset($orderCols['phone_number'])) $add($orderCols['phone_number'],'s',$userPhone);
if($createdColReal) $add($createdColReal,'s',$createdAt);

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
} else {
    // Optional: create matching single line (keeps future compatibility). Include designoption_id if exists in order_items
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
respond([
    'status'=>'ok',
    'order_id'=>$orderId,
    'redirect'=>$redirect,
    'multi'=>$isMulti,
    'items_count'=>$isMulti?count($multiItems):1,
    'partial'=>$isPartial
]);
?>