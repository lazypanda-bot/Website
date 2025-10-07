<?php
// Update quantity of a cart line (DB-backed cart only)
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/database.php';
if(!isset($_SESSION['user_id'])) { echo json_encode(['status'=>'auth']); exit; }
$userId = (int)$_SESSION['user_id'];
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$qty = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
if($id<=0 || $qty<=0) { echo json_encode(['status'=>'error','message'=>'Invalid id or quantity']); exit; }
// Adaptive cart table mapping (reuse from cart_items if not defined)
if (!defined('CART_TABLE')) {
    define('CART_TABLE','cart');
    $cartCols = [];
    if ($res = $conn->query('SHOW COLUMNS FROM '.CART_TABLE)) { while($r=$res->fetch_assoc()){ $cartCols[strtolower($r['Field'])]=$r['Field']; } $res->free(); }
    $pk='cart_id'; foreach(['id','cart_id'] as $c){ if(isset($cartCols[$c])) { $pk=$cartCols[$c]; break; } }
    define('CART_PK_COL',$pk);
    $userFk='user_id'; foreach(['customer_id','user_id','account_id'] as $c){ if(isset($cartCols[$c])) { $userFk=$cartCols[$c]; break; } }
    define('CART_USER_FK_COL',$userFk);
    $qtyCol='quantity'; foreach(['quantity','qty','amount'] as $c){ if(isset($cartCols[$c])) { $qtyCol=$cartCols[$c]; break; } }
    define('CART_QTY_COL',$qtyCol);
}
$sql = 'UPDATE '.CART_TABLE.' SET '.CART_QTY_COL.'=? WHERE '.CART_PK_COL.'=? AND '.CART_USER_FK_COL.'=?';
if(!$stmt = $conn->prepare($sql)) { echo json_encode(['status'=>'error','message'=>'Prepare failed']); exit; }
$stmt->bind_param('iii',$qty,$id,$userId);
if(!$stmt->execute()) { echo json_encode(['status'=>'error','message'=>'Update failed']); $stmt->close(); exit; }
$stmt->close();
echo json_encode(['status'=>'ok']);
?>
