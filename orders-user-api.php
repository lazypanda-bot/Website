<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/database.php';
if(!isset($_SESSION['user_id'])){ echo json_encode(['status'=>'auth']); exit; }
$userId = (int)$_SESSION['user_id'];
$orders = [];
// Select snake_case columns and alias to legacy camelCase keys expected by client
$stmt = $conn->prepare("SELECT order_id, order_status AS OrderStatus, delivery_status AS DeliveryStatus FROM orders WHERE customer_id = ? ORDER BY created_at DESC");
if($stmt){
    $stmt->bind_param('i',$userId);
    $stmt->execute();
    $res = $stmt->get_result();
    while($r = $res->fetch_assoc()) { $orders[] = $r; }
    $res->free();
    $stmt->close();
}
echo json_encode(['status'=>'ok','orders'=>$orders]);
?>
