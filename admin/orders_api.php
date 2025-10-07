<?php
session_start();
header('Content-Type: application/json');
require_once '../database.php';
// if(!isset($_SESSION['is_admin'])) { http_response_code(403); echo json_encode(['status'=>'error','message'=>'Forbidden']); exit; }

function fail($m,$c=400){ http_response_code($c); echo json_encode(['status'=>'error','message'=>$m]); exit; }

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// List orders with joined product & customer
if ($action === 'list') {
    $sql = "SELECT o.order_id, o.product_id, o.customer_id, o.size, o.quantity, o.OrderStatus, o.TotalAmount, o.isPartialPayment, o.created_at, 
                   c.".ACCOUNT_NAME_COL." AS customer_name, c.".ACCOUNT_PHONE_COL." AS phone, c.".ACCOUNT_ADDRESS_COL." AS address,
                   p.product_name, p.price
            FROM orders o
            LEFT JOIN ".ACCOUNT_TABLE." c ON c.".ACCOUNT_ID_COL." = o.customer_id
            LEFT JOIN products p ON p.product_id = o.product_id
            ORDER BY o.created_at DESC";
    $rows = [];
    if($res = $conn->query($sql)) {
        while($r=$res->fetch_assoc()) { $rows[]=$r; }
    }
    echo json_encode(['status'=>'ok','orders'=>$rows]);
    exit;
}

// Update status
if ($action === 'update_status') {
    $id = (int)($_POST['order_id'] ?? 0);
    $status = trim($_POST['OrderStatus'] ?? '');
    if ($id<=0) fail('Invalid id');
    if ($status==='') fail('Status required');
    if ($status === 'Delivered') {
        // Map to DeliveryStatus column instead of OrderStatus
        $stmt = $conn->prepare("UPDATE orders SET DeliveryStatus='Delivered' WHERE order_id=?");
        if(!$stmt) fail('Prepare failed: '.$conn->error,500);
        $stmt->bind_param('i',$id);
    } else {
        $stmt = $conn->prepare("UPDATE orders SET OrderStatus=? WHERE order_id=?");
        if(!$stmt) fail('Prepare failed: '.$conn->error,500);
        $stmt->bind_param('si',$status,$id);
    }
    if(!$stmt->execute()) fail('Update failed: '.$stmt->error,500);
    $stmt->close();
    echo json_encode(['status'=>'ok','action'=>'updated']);
    exit;
}

fail('Unsupported action');
?>