<?php
session_start();
header('Content-Type: application/json');
require_once '../database.php';
function fail($m,$c=400){ http_response_code($c); echo json_encode(['status'=>'error','message'=>$m]); exit; }
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($action === 'list') {
    $sql = "SELECT o.order_id, o.customer_id, o.TotalAmount, o.isPartialPayment, o.OrderStatus, o.created_at,
                   c.".ACCOUNT_NAME_COL." AS customer_name, c.".ACCOUNT_PHONE_COL." AS phone, c.".ACCOUNT_ADDRESS_COL." AS address,
                   p.product_name, o.quantity, p.price
            FROM orders o
            LEFT JOIN ".ACCOUNT_TABLE." c ON c.".ACCOUNT_ID_COL." = o.customer_id
            LEFT JOIN products p ON p.product_id = o.product_id
            ORDER BY o.created_at DESC";
    $rows=[]; if($res=$conn->query($sql)){ while($r=$res->fetch_assoc()){ $rows[]=$r; } }
    echo json_encode(['status'=>'ok','payments'=>$rows]);
    exit;
}

fail('Unsupported action');
?>