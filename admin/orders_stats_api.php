<?php
// Returns counts of orders by status (Pending, Completed, Cancelled)
session_start();
header('Content-Type: application/json');
require_once '../database.php';
// if(!isset($_SESSION['is_admin'])) { http_response_code(403); echo json_encode(['status'=>'error','message'=>'Forbidden']); exit; }
$counts = ['Pending'=>0,'Delivered'=>0,'Completed'=>0,'Cancelled'=>0];
// OrderStatus-based counts
if($res = $conn->query("SELECT OrderStatus, COUNT(*) c FROM orders GROUP BY OrderStatus")) {
  while($r=$res->fetch_assoc()) {
    $st = $r['OrderStatus'];
    if(isset($counts[$st])) $counts[$st] = (int)$r['c'];
  }
  $res->free();
}
// Delivered = delivery status delivered but not yet confirmed (not completed)
if($res2 = $conn->query("SELECT COUNT(*) c FROM orders WHERE DeliveryStatus='Delivered' AND OrderStatus<>'Completed'")) {
  if($row=$res2->fetch_assoc()) { $counts['Delivered'] = (int)$row['c']; }
  $res2->free();
}
echo json_encode(['status'=>'ok','counts'=>$counts]);
?>
