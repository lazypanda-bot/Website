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

$sql = "SELECT c.".CART_PK_COL." AS id, c.".CART_PRODUCT_FK_COL." AS product_id, c.".CART_SIZE_COL." AS size, c.".CART_COLOR_COL." AS color, c.".CART_QTY_COL." AS quantity, p.".$productNameCol." AS name, p.".$productPriceCol." AS price
        FROM ".CART_TABLE." c
        LEFT JOIN products p ON p.".$productCols[strtolower($productCols[$productNameCol] ?? $productNameCol)] ?? $productNameCol." = p.".$productCols[strtolower($productNameCol)] ?? $productNameCol." 
        WHERE c.".CART_USER_FK_COL."=?";
// The above join simplifies to ON p.<product_id candidate> not implemented (no products PK mapping). We just join on product_id if exists.
// Rebuild with correct join using product FK detection.

// Detect product PK for join (only if products table exists)
$productPk = null;
if ($productsTableExists) {
    foreach(['product_id','id','prod_id','products_id'] as $c){ if(isset($productCols[$c])) { $productPk = $productCols[$c]; break; } }
}

$designColSelect = '';
// If cart table has a designoption_id (or similar) column, include it and try to pull design metadata
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
    // Join to designoption and customization if those tables/columns are present
    // We'll attempt the common column names
    $joinSql = ' LEFT JOIN designoption d ON d.designoption_id = c.' . $designColName . ' LEFT JOIN customization cu ON cu.customization_id = d.customization_id ';
    $designSelect .= ', cu.color AS design_color, cu.note AS design_meta, d.request_design AS design_request, d.designfilepath AS designfilepath';
}

// Build SELECT expressions with safe fallbacks
$nameExpr = $productNameCol ? ('p.'.$productNameCol) : "''";
$priceExpr = $productPriceCol ? ('p.'.$productPriceCol) : '0';
$joinPart = ($productsTableExists && $productPk) ? (' LEFT JOIN products p ON p.'.$productPk.' = c.'.CART_PRODUCT_FK_COL.' ') : ' ';

$sql = "SELECT c.".CART_PK_COL." AS id, c.".CART_PRODUCT_FK_COL." AS product_id, c.".CART_SIZE_COL." AS size, c.".CART_COLOR_COL." AS color, c.".CART_QTY_COL." AS quantity" . $designSelect . ", $nameExpr AS name, $priceExpr AS price
    FROM ".CART_TABLE." c" . $joinPart . $joinSql . " WHERE c.".CART_USER_FK_COL."=? ORDER BY c.".CART_PK_COL." DESC LIMIT 200";

$stmt = $conn->prepare($sql);
if(!$stmt){ echo json_encode(['items'=>[]]); exit; }
$stmt->bind_param('i', $userId);
if(!$stmt->execute()){ $stmt->close(); echo json_encode(['items'=>[]]); exit; }
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
    $items[] = $row;
}
$stmt->close();
echo json_encode(['items'=>$items]);
?>
