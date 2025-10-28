<?php
session_start();
header('Content-Type: application/json');
require_once '../database.php';
function fail($m,$c=400){ http_response_code($c); echo json_encode(['status'=>'error','message'=>$m]); exit; }
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($action === 'list') {
    // Join latest payment for each order (by payment_date desc)
    $sql = "SELECT o.order_id, o.customer_id, o.TotalAmount, o.isPartialPayment, o.OrderStatus, o.created_at,
                   c.".ACCOUNT_NAME_COL." AS customer_name, c.".ACCOUNT_PHONE_COL." AS phone, c.".ACCOUNT_ADDRESS_COL." AS address,
                   p.product_name, o.quantity, p.price,
                   py.payment_method, py.payment_status, py.payment_amount, py.payment_date
            FROM orders o
            LEFT JOIN ".ACCOUNT_TABLE." c ON c.".ACCOUNT_ID_COL." = o.customer_id
            LEFT JOIN products p ON p.product_id = o.product_id
            LEFT JOIN (
                SELECT t1.* FROM payments t1
                INNER JOIN (
                    SELECT order_id, MAX(payment_date) AS max_date FROM payments GROUP BY order_id
                ) t2 ON t1.order_id = t2.order_id AND t1.payment_date = t2.max_date
            ) py ON py.order_id = o.order_id
            ORDER BY o.created_at DESC";
    $rows=[]; if($res=$conn->query($sql)){ while($r=$res->fetch_assoc()){ $rows[]=$r; } }
    echo json_encode(['status'=>'ok','payments'=>$rows]);
    exit;
}

fail('Unsupported action');
?>