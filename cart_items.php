<?php
// Returns JSON list of cart items for the logged in user (DB-backed cart)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
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

// Attempt to pull product name & price columns adaptively
$productNameCol = 'name';
$productPriceCol = 'price';
$productCols = [];
if ($res = $conn->query('SHOW COLUMNS FROM products')) {
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

// Detect product PK for join
$productPk = 'id';
foreach(['product_id','id','prod_id','products_id'] as $c){ if(isset($productCols[$c])) { $productPk = $productCols[$c]; break; } }

$sql = "SELECT c.".CART_PK_COL." AS id, c.".CART_PRODUCT_FK_COL." AS product_id, c.".CART_SIZE_COL." AS size, c.".CART_COLOR_COL." AS color, c.".CART_QTY_COL." AS quantity, p.".$productNameCol." AS name, p.".$productPriceCol." AS price
        FROM ".CART_TABLE." c
        LEFT JOIN products p ON p.".$productPk." = c.".CART_PRODUCT_FK_COL." WHERE c.".CART_USER_FK_COL."=?";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $userId);
$stmt->execute();
$res = $stmt->get_result();
$items = [];
while ($row = $res->fetch_assoc()) { $items[] = $row; }
$stmt->close();
echo json_encode(['items'=>$items]);
?>