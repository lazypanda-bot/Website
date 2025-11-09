<?php
// Returns JSON list of cart items for the logged in user (DB-backed cart)
// Production-safe: emit only JSON; log errors to file instead of mixing into output
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');
// Error logging
if (!is_dir(__DIR__ . '/logs')) { @mkdir(__DIR__ . '/logs', 0777, true); }
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/cart_items_error.log');
require_once 'database.php';
session_start();
require_once 'includes/auth.php';
$userId = session_user_id_or_zero();
if ($userId === 0) { echo json_encode(['items'=>[]]); exit; }

// Adaptive cart table mapping (reuse logic if already defined)
if (!defined('CART_TABLE')) {
    define('CART_TABLE', 'cart');
    $cartCols = [];
    if ($res = $conn->query('SHOW COLUMNS FROM ' . CART_TABLE)) {
        while ($r = $res->fetch_assoc()) { $cartCols[strtolower($r['Field'])] = $r['Field']; }
        $res->free();
    }
    $pk = 'id'; foreach(['id','cart_id'] as $c){ if(isset($cartCols[$c])) { $pk = $cartCols[$c]; break; } }
    define('CART_PK_COL', $pk);
    $userFk = 'user_id'; foreach(['customer_id','user_id','account_id'] as $c){ if(isset($cartCols[$c])) { $userFk=$cartCols[$c]; break; } }
    define('CART_USER_FK_COL', $userFk);
    $prodFk = 'product_id'; foreach(['product_id','prod_id','item_id'] as $c){ if(isset($cartCols[$c])) { $prodFk=$cartCols[$c]; break; } }
    define('CART_PRODUCT_FK_COL', $prodFk);
    $qtyCol = 'quantity'; foreach(['quantity','qty','amount'] as $c){ if(isset($cartCols[$c])) { $qtyCol=$cartCols[$c]; break; } }
    define('CART_QTY_COL', $qtyCol);
    $sizeCol = 'size'; foreach(['size','sizes'] as $c){ if(isset($cartCols[$c])) { $sizeCol=$cartCols[$c]; break; } }
    define('CART_SIZE_COL', $sizeCol);
    $colorCol = 'color'; foreach(['color','colour','variant'] as $c){ if(isset($cartCols[$c])) { $colorCol=$cartCols[$c]; break; } }
    define('CART_COLOR_COL', $colorCol);
}

// Attempt to pull product name & price columns adaptively; tolerate missing columns
$productNameCol = null;
$productPriceCol = null;
$productCols = [];
$productsTableExists = false;
if ($res = $conn->query('SHOW COLUMNS FROM products')) {
    $productsTableExists = true;
    while ($r = $res->fetch_assoc()) { $productCols[strtolower($r['Field'])] = $r['Field']; }
    $res->free();
}
foreach(['name','product_name','title'] as $c){ if(isset($productCols[$c])) { $productNameCol=$productCols[$c]; break; } }
foreach(['price','unit_price','amount','cost'] as $c){ if(isset($productCols[$c])) { $productPriceCol=$productCols[$c]; break; } }

// Detect product PK for join (only if products table exists)
$productPk = null;
if ($productsTableExists) {
    foreach(['product_id','id','prod_id','products_id'] as $c){ if(isset($productCols[$c])) { $productPk = $productCols[$c]; break; } }
}

$designColSelect = '';
// If cart table has a designoption_id column, include it and try to pull design metadata
$cartTableCols = [];
if ($resCols = $conn->query('SHOW COLUMNS FROM ' . CART_TABLE)) { while ($r = $resCols->fetch_assoc()) { $cartTableCols[strtolower($r['Field'])] = $r['Field']; } $resCols->free(); }
$designColName = null;
if (isset($cartTableCols['designoption_id'])) $designColName = $cartTableCols['designoption_id'];
elseif (isset($cartTableCols['design_option_id'])) $designColName = $cartTableCols['design_option_id'];
elseif (isset($cartTableCols['design_id'])) $designColName = $cartTableCols['design_id'];

// Build SELECT and JOINs
$joinSql = '';
$designSelect = '';
if ($designColName) {
    $designSelect = ', c.' . $designColName . ' AS designoption_id';
    // Join to designoption and customization only if those tables exist
    $hasDesign = false; $hasCust = false;
    if ($t = $conn->query("SHOW TABLES LIKE 'designoption'")) { $hasDesign = ($t->num_rows>0); $t->free(); }
    if ($t = $conn->query("SHOW TABLES LIKE 'customization'")) { $hasCust = ($t->num_rows>0); $t->free(); }
    if ($hasDesign) {
        $joinSql .= ' LEFT JOIN designoption d ON d.designoption_id = c.' . $designColName . ' ';
        if ($hasCust) {
            // Check customization table columns and only select columns that exist
            $custCols = [];
            if ($cRes = $conn->query('SHOW COLUMNS FROM customization')) {
                while ($cr = $cRes->fetch_assoc()) { $custCols[strtolower($cr['Field'])] = $cr['Field']; }
                $cRes->free();
            }
            $hasColorCol = isset($custCols['color']);
            $hasNoteCol  = isset($custCols['note']);
            $joinSql .= ' LEFT JOIN customization cu ON cu.customization_id = d.customization_id ';
            if ($hasColorCol) $designSelect .= ', cu.color AS design_color';
            if ($hasNoteCol)  $designSelect .= ', cu.note AS design_meta';
        }
        $designSelect .= ', d.request_design AS design_request, d.designfilepath AS designfilepath';
    }
}

// Even if there's no designoption_id on the cart table, try to surface any direct
// design/preview path stored on the cart row (common in flexible schemas). Map it to
// a unified alias 'designfilepath' so the client can render a preview.
$directDesignExpr = '';
foreach (['designfilepath','design_file','designpath','design','preview_path','preview','thumb','thumbnail','image','img'] as $cand) {
    if (isset($cartTableCols[$cand])) {
        $directDesignExpr = ', c.' . $cartTableCols[$cand] . ' AS designfilepath';
        break;
    }
}

// Build SELECT expressions with safe fallbacks
// Only reference p.* when we actually join products with a valid PK
$joinPart = ($productsTableExists && $productPk) ? (' LEFT JOIN products p ON p.'.$productPk.' = c.'.CART_PRODUCT_FK_COL.' ') : ' ';
$nameExpr = ($productsTableExists && $productPk && $productNameCol) ? ('p.'.$productNameCol) : "''";
$basePriceExpr = ($productsTableExists && $productPk && $productPriceCol) ? ('p.'.$productPriceCol) : '0';

// Optional: derive price from products_sub using size/color when available
$calcPriceExpr = $basePriceExpr;
$subJoins = '';
if ($resTbl = $conn->query("SHOW TABLES LIKE 'products_sub'")) {
    if ($resTbl->num_rows > 0) {
        $psCols = [];
        if ($psC = $conn->query('SHOW COLUMNS FROM products_sub')) { while($r=$psC->fetch_assoc()){ $psCols[strtolower($r['Field'])]=$r['Field']; } $psC->free(); }
        $psPrice = $psCols['price'] ?? ($psCols['amount'] ?? ($psCols['unit_price'] ?? ($psCols['cost'] ?? 'price')));
        $psKind  = $psCols['kind'] ?? ($psCols['type'] ?? ($psCols['name'] ?? null));
        $psValue = $psCols['value'] ?? ($psCols['values'] ?? ($psCols['val'] ?? ($psCols['option_value'] ?? ($psCols['option'] ?? null))));
        $psSizes = $psCols['sizes'] ?? ($psCols['size'] ?? null);
        $psAttrs = $psCols['attributes'] ?? ($psCols['attribute'] ?? ($psCols['color'] ?? ($psCols['colour'] ?? null)));
        $psProdId = $psCols['product_id'] ?? ($psCols['products_id'] ?? ($psCols['prod_id'] ?? 'product_id'));
        // Normalize comparison by forcing both operands to the same collation to avoid
        // "Illegal mix of collations" when schema columns differ.
        $cmpCollation = 'utf8mb4_general_ci';

        // size-based price (wide column or kind/value fallback)
        if ($psSizes) {
            $subJoins .= ' LEFT JOIN products_sub ps_size ON ps_size.'.$psProdId.
                " = c.".CART_PRODUCT_FK_COL.
                " AND ps_size.$psSizes COLLATE $cmpCollation = c.".CART_SIZE_COL." COLLATE $cmpCollation ";
        } elseif ($psKind && $psValue) {
            $subJoins .= ' LEFT JOIN products_sub ps_size ON ps_size.'.$psProdId.
                " = c.".CART_PRODUCT_FK_COL.
                " AND ps_size.$psKind = 'size' AND ps_size.$psValue COLLATE $cmpCollation = c.".CART_SIZE_COL." COLLATE $cmpCollation ";
        }
        // attribute/color-based price (wide column or kind/value fallback)
        if ($psAttrs) {
            $subJoins .= ' LEFT JOIN products_sub ps_attr ON ps_attr.'.$psProdId.
                " = c.".CART_PRODUCT_FK_COL.
                " AND ps_attr.$psAttrs COLLATE $cmpCollation = c.".CART_COLOR_COL." COLLATE $cmpCollation ";
        } elseif ($psKind && $psValue) {
            $subJoins .= ' LEFT JOIN products_sub ps_attr ON ps_attr.'.$psProdId.
                " = c.".CART_PRODUCT_FK_COL.
                " AND ps_attr.$psKind IN ('attribute','attributes','color','colour') AND ps_attr.$psValue COLLATE $cmpCollation = c.".CART_COLOR_COL." COLLATE $cmpCollation ";
        }
        // combo (sizes + attributes) price when both wide columns exist
        if ($psSizes && $psAttrs) {
            $subJoins .= ' LEFT JOIN products_sub ps_combo ON ps_combo.'.$psProdId.
                " = c.".CART_PRODUCT_FK_COL.
                " AND ps_combo.$psSizes COLLATE $cmpCollation = c.".CART_SIZE_COL." COLLATE $cmpCollation".
                " AND ps_combo.$psAttrs COLLATE $cmpCollation = c.".CART_COLOR_COL." COLLATE $cmpCollation ";
        }

        // Final price preference: combo > size > attr > any price for same product (fallback) > base product price
        // Treat 0.00 as "no price" by using NULLIF so it won't override a valid non-zero price later in COALESCE
        $anyJoin = ' LEFT JOIN (SELECT '.$psProdId.' AS pid, MAX(CASE WHEN '.$psPrice.' > 0 THEN '.$psPrice.' END) AS any_price FROM products_sub GROUP BY '.$psProdId.') ps_any ON ps_any.pid = c.'.CART_PRODUCT_FK_COL.' ';
        $subJoins .= $anyJoin;

        $psSizeExpr  = 'NULLIF(ps_size.'.$psPrice.',0)';
        $psAttrExpr  = 'NULLIF(ps_attr.'.$psPrice.',0)';
        $psComboExpr = 'NULLIF(ps_combo.'.$psPrice.',0)';
        $psAnyExpr   = 'NULLIF(ps_any.any_price,0)';

        if ($psSizes && $psAttrs) {
            $calcPriceExpr = "COALESCE($psComboExpr, $psSizeExpr, $psAttrExpr, $psAnyExpr, $basePriceExpr)";
        } else {
            $calcPriceExpr = "COALESCE($psSizeExpr, $psAttrExpr, $psAnyExpr, $basePriceExpr)";
        }
    }
    $resTbl->free();
}

$sql = "SELECT c.".CART_PK_COL." AS id, c.".CART_PRODUCT_FK_COL." AS product_id, c.".CART_SIZE_COL." AS size, c.".CART_COLOR_COL." AS color, c.".CART_QTY_COL." AS quantity" . $designSelect . $directDesignExpr . ", $nameExpr AS name, $calcPriceExpr AS price
    FROM ".CART_TABLE." c" . $joinPart . $subJoins . $joinSql . " WHERE c.".CART_USER_FK_COL."=? ORDER BY c.".CART_PK_COL." DESC LIMIT 200";

$stmt = $conn->prepare($sql);
if(!$stmt){
    // Log the original failure then fall back to a minimal select that avoids
    // joins and complex expressions which may fail on varied schemas/collations.
    error_log('cart-items prepare failed: '.$conn->error.' SQL='.$sql);
    // Build a safe fallback SQL that returns core cart columns plus any direct design column
    $fallbackSql = "SELECT c.".CART_PK_COL." AS id, c.".CART_PRODUCT_FK_COL." AS product_id, c.".CART_SIZE_COL." AS size, c.".CART_COLOR_COL." AS color, c.".CART_QTY_COL." AS quantity" . (isset(
        $directDesignExpr) ? $directDesignExpr : '') . " FROM ".CART_TABLE." c WHERE c.".CART_USER_FK_COL."=? ORDER BY c.".CART_PK_COL." DESC LIMIT 200";
    $stmt = $conn->prepare($fallbackSql);
    if (!$stmt) { error_log('cart-items fallback prepare also failed: '.$conn->error.' SQL='.$fallbackSql); echo json_encode(['items'=>[]]); exit; }
}
$stmt->bind_param('i', $userId);
if(!$stmt->execute()){ error_log('cart-items exec failed: '.$stmt->error); $stmt->close(); echo json_encode(['items'=>[]]); exit; }
$res = $stmt->get_result();
$items = [];
while ($row = $res->fetch_assoc()) {
    // Ensure the response always contains the design-related keys so the client
    // can rely on their presence (null when no design is attached).
    $designDefaults = [
        'designoption_id' => null,
        'design_color' => null,
        'design_meta' => null,
        'design_request' => null,
        'designfilepath' => null
    ];
    foreach ($designDefaults as $k => $v) {
        if (!array_key_exists($k, $row)) $row[$k] = $v;
    }
    // If designfilepath is a bare numeric id (legacy rows stored id in a path column),
    // attempt to resolve it to an actual path.
    if (!empty($row['designfilepath']) && ctype_digit((string)$row['designfilepath'])) {
        $did = (int)$row['designfilepath'];
        if ($did > 0) {
            try {
                if ($chk = $conn->query("SHOW TABLES LIKE 'designoption'")) {
                    if ($chk->num_rows > 0) {
                        $chk->close();
                        $stmtFix = $conn->prepare("SELECT designfilepath FROM designoption WHERE designoption_id=? LIMIT 1");
                        if ($stmtFix) {
                            $stmtFix->bind_param('i',$did);
                            if ($stmtFix->execute()) {
                                $rf = $stmtFix->get_result();
                                if ($frow = $rf->fetch_assoc()) {
                                    if (!empty($frow['designfilepath'])) {
                                        $row['designfilepath'] = $frow['designfilepath'];
                                    }
                                }
                            }
                            $stmtFix->close();
                        }
                    } else { $chk->close(); }
                }
            } catch(Throwable $e){ /* ignore resolution errors */ }
        }
    }
    $items[] = $row;
}
$stmt->close();
// If items include designoption_id but missing designfilepath (fallback case),
// perform a small, safe lookup to resolve designoption_id -> designfilepath.
$needLookup = [];
foreach ($items as $it) {
    if (!empty($it['designoption_id']) && empty($it['designfilepath'])) {
        $needLookup[] = (int)$it['designoption_id'];
    }
}
if (count($needLookup) > 0) {
    $needLookup = array_values(array_unique($needLookup));
    // Build placeholders
    $placeholders = implode(',', array_fill(0, count($needLookup), '?'));
    $sql2 = "SELECT designoption_id, designfilepath FROM designoption WHERE designoption_id IN ($placeholders)";
    $stmt2 = $conn->prepare($sql2);
    if ($stmt2) {
        // bind params dynamically as integers
        $types = str_repeat('i', count($needLookup));
        $stmt2->bind_param($types, ...$needLookup);
        if ($stmt2->execute()) {
            $res2 = $stmt2->get_result();
            $map = [];
            while ($r2 = $res2->fetch_assoc()) {
                $map[(int)$r2['designoption_id']] = $r2['designfilepath'];
            }
            // apply to items
            foreach ($items as &$it) {
                if (!empty($it['designoption_id']) && empty($it['designfilepath'])) {
                    $did = (int)$it['designoption_id'];
                    if (isset($map[$did])) $it['designfilepath'] = $map[$did];
                }
            }
            unset($it);
        }
        $stmt2->close();
    }
}
echo json_encode(['items'=>$items]);
?>
