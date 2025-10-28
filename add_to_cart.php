<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include 'database.php';
header('Content-Type: application/json'); // send JSON always
// Adaptive cart table/columns detection
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
session_start();
require_once __DIR__ . '/includes/auth.php';
$user_id = require_valid_user_json();

function json_error($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit;
}

$user_id    = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$size       = isset($_POST['size']) ? trim($_POST['size']) : '';
$color      = isset($_POST['color']) ? trim($_POST['color']) : '';
$quantity   = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;

if ($user_id <= 0) {
    json_error('You must be logged in to add items to the cart.', 401);
}
if ($product_id <= 0) {
    json_error('Invalid or missing product id.');
}
if ($quantity <= 0) {
    $quantity = 1; // normalize
}
// Provide safe defaults
if ($size === '')  { $size = 'Default'; }
if ($color === '') { $color = 'Standard'; }

// Verify product exists (prevents foreign key failure)
$prodCheck = $conn->prepare('SELECT 1 FROM products WHERE product_id = ? LIMIT 1');
if ($prodCheck) {
    $prodCheck->bind_param('i', $product_id);
    $prodCheck->execute();
    $prodCheck->store_result();
    if ($prodCheck->num_rows === 0) {
        $prodCheck->close();
        json_error('Product does not exist (maybe removed).');
    }
    $prodCheck->close();
} else {
    json_error('Server error preparing product validation.');
}

// Check if item exists (same user, product, size, color) then update quantity else insert
$checkSql = "SELECT " . CART_PK_COL . ", " . CART_QTY_COL . " FROM " . CART_TABLE . " WHERE " . CART_USER_FK_COL . "=? AND " . CART_PRODUCT_FK_COL . "=? AND " . CART_SIZE_COL . "=? AND " . CART_COLOR_COL . "=? LIMIT 1";
$check = $conn->prepare($checkSql);
$check->bind_param("iiss", $user_id, $product_id, $size, $color);
$check->execute();
$existingId = null; $existingQty = 0;
$check->bind_result($existingId, $existingQty);
$check->fetch();
$check->close();

try {
    if ($existingId) {
        // Previous behavior: cumulative addition ($existingQty + $quantity) caused user confusion (appearing as 'adding two').
        // New behavior: treat Add to Cart as setting the desired quantity (replace mode).
        $newQty = max(1, $quantity);
        $upd = $conn->prepare("UPDATE " . CART_TABLE . " SET " . CART_QTY_COL . "=? WHERE " . CART_PK_COL . "=?");
        if (!$upd) json_error('Failed to prepare update: ' . $conn->error, 500);
        $upd->bind_param("ii", $newQty, $existingId);
        if ($upd->execute()) {
            echo json_encode(['status'=>'ok','action'=>'replaced','quantity'=>$newQty]);
        } else {
            json_error('DB update error: ' . $upd->error, 500);
        }
        $upd->close();
    } else {
        $sql = "INSERT INTO " . CART_TABLE . " (" . CART_USER_FK_COL . ", " . CART_PRODUCT_FK_COL . ", " . CART_SIZE_COL . ", " . CART_COLOR_COL . ", " . CART_QTY_COL . ") VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) json_error('Failed to prepare insert: ' . $conn->error, 500);
        $stmt->bind_param("iissi", $user_id, $product_id, $size, $color, $quantity);
        if ($stmt->execute()) {
            echo json_encode(['status'=>'ok','action'=>'inserted','id'=>$stmt->insert_id,'quantity'=>$quantity]);
        } else {
            json_error('DB insert error: ' . $stmt->error, 500);
        }
        $stmt->close();
    }
} catch (Throwable $e) {
    json_error('Unexpected server error: ' . $e->getMessage(), 500);
}
$conn->close();
?>
