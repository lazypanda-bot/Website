<?php
session_start();
header('Content-Type: application/json');
require_once '../database.php';
function fail($m,$c=400){ http_response_code($c); echo json_encode(['status'=>'error','message'=>$m]); exit; }
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($action === 'weekly') {
    // Aggregate by YEARWEEK(created_at)
    // Use snake_case DB columns and compute legacy metrics
    $sql = "SELECT YEARWEEK(created_at,1) AS yw, 
             COUNT(*) AS total_orders,
             SUM(CASE WHEN order_status='Paid' THEN 1 ELSE 0 END) AS paid,
             SUM(CASE WHEN partial_payment=1 THEN 1 ELSE 0 END) AS partial,
             SUM(CASE WHEN order_status='Pending' THEN 1 ELSE 0 END) AS pending,
             SUM(total_amount) AS revenue
         FROM orders GROUP BY YEARWEEK(created_at,1) ORDER BY yw DESC LIMIT 12";
    $rows=[]; if($res=$conn->query($sql)){ while($r=$res->fetch_assoc()) { $rows[]=$r; } }
    echo json_encode(['status'=>'ok','weekly'=>$rows]);
    exit;
}

fail('Unsupported action');
?>
