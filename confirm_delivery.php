<?php
// User confirms receipt -> set OrderStatus=Completed when the order is in a 'Delivered' state and belongs to the user.
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/database.php';

if(!isset($_SESSION['user_id'])) { echo json_encode(['status'=>'auth']); exit; }
$userId = (int)$_SESSION['user_id'];
$orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;

if($orderId<=0){ echo json_encode(['status'=>'error','message'=>'Invalid order id']); exit; }
// Ensure order belongs to user and delivery is delivered
$stmt = $conn->prepare('SELECT OrderStatus, IFNULL(DeliveryStatus, OrderStatus) AS EffectiveDelivery FROM orders WHERE order_id=? AND customer_id=? LIMIT 1');

if(!$stmt){ echo json_encode(['status'=>'error','message'=>'Prepare failed']); exit; }
$stmt->bind_param('ii',$orderId,$userId);
$stmt->execute();
$stmt->bind_result($oStatus,$dStatus);

if(!$stmt->fetch()){ $stmt->close(); echo json_encode(['status'=>'error','message'=>'Not found']); exit; }
$stmt->close();

// Accept either DeliveryStatus='Delivered' OR (no delivery column meaning OrderStatus already "Delivered")
if(strtolower($dStatus) !== 'delivered' && strtolower($oStatus) !== 'delivered'){
	echo json_encode(['status'=>'error','message'=>'Order not marked delivered yet']);
	exit;
}
if(strtolower($oStatus)==='completed'){ echo json_encode(['status'=>'ok','message'=>'Already completed']); exit; }
// Set CompletedAt if column exists
$hasCompletedAt = false;

if($chk = $conn->query("SHOW COLUMNS FROM orders LIKE 'CompletedAt'")) { if($chk->num_rows>0) $hasCompletedAt=true; $chk->close(); }
$sqlUp = $hasCompletedAt ? "UPDATE orders SET OrderStatus='Completed', DeliveryStatus='Completed', CompletedAt=NOW() WHERE order_id=?" : "UPDATE orders SET OrderStatus='Completed', DeliveryStatus='Completed' WHERE order_id=?";
$up = $conn->prepare($sqlUp);

if(!$up){ echo json_encode(['status'=>'error','message'=>'Prepare failed']); exit; }
$up->bind_param('i',$orderId);

if(!$up->execute()){ echo json_encode(['status'=>'error','message'=>'Update failed']); $up->close(); exit; }
$up->close();
// Fetch updated values to return to client
$stmt2 = $conn->prepare('SELECT OrderStatus, DeliveryStatus FROM orders WHERE order_id=? LIMIT 1');
if($stmt2){
	$stmt2->bind_param('i',$orderId);
	$stmt2->execute();
	$stmt2->bind_result($newOrderStatus,$newDeliveryStatus);
	$stmt2->fetch();
	$stmt2->close();
	echo json_encode(['status'=>'ok','message'=>'Order confirmed','order'=>['OrderStatus'=>$newOrderStatus,'DeliveryStatus'=>$newDeliveryStatus]]);
} else {
	echo json_encode(['status'=>'ok','message'=>'Order confirmed']);
}
?>
