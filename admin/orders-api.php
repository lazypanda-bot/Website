<?php
session_start();
header('Content-Type: application/json');
// admin scripts live in /admin/, database.php is one level up in project root
require_once __DIR__ . '/../database.php';
// if(!isset($_SESSION['is_admin'])) { http_response_code(403); echo json_encode(['status'=>'error','message'=>'Forbidden']); exit; }

function fail($m,$c=400){ http_response_code($c); echo json_encode(['status'=>'error','message'=>$m]); exit; }

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// List orders with joined product & customer
if ($action === 'list') {
    $hasCompletedAt = false;
    if($chk = $conn->query("SHOW COLUMNS FROM orders LIKE 'CompletedAt'")) { if($chk->num_rows>0) $hasCompletedAt=true; $chk->close(); }
    $completedFrag = $hasCompletedAt ? ', o.CompletedAt' : '';
    // AmountPaid: sum of payments for order (Paid or Partial) for display. If payments table large, consider separate endpoint / LIMIT.
    $sql = "SELECT o.order_id, o.product_id, o.customer_id, o.size, o.quantity, o.OrderStatus, o.DeliveryStatus, o.TotalAmount, o.isPartialPayment, o.created_at".$completedFrag.
        ", c.".ACCOUNT_NAME_COL." AS customer_name, c.".ACCOUNT_PHONE_COL." AS phone, c.".ACCOUNT_ADDRESS_COL." AS address, p.product_name, p.price,
         do.designoption_id, do.designfilepath, cu.color AS design_color, cu.note AS design_note,
         (SELECT COALESCE(SUM(py.payment_amount),0) FROM payments py WHERE py.order_id = o.order_id AND py.payment_status IN ('Paid','Partial')) AS AmountPaid
         FROM orders o
         LEFT JOIN ".ACCOUNT_TABLE." c ON c.".ACCOUNT_ID_COL." = o.customer_id
         LEFT JOIN products p ON p.product_id = o.product_id
         LEFT JOIN designoption do ON do.designoption_id = o.designoption_id
         LEFT JOIN customization cu ON cu.customization_id = do.customization_id
         ORDER BY o.created_at DESC";
    $rows = [];
    $res = $conn->query($sql);
    if(!$res){ fail('Query failed: '.$conn->error,500); }
    while($r=$res->fetch_assoc()) { $rows[]=$r; }
    $res->free();
    echo json_encode(['status'=>'ok','orders'=>$rows]);
    exit;
}

// Update order status only
if ($action === 'update_status') {
    $id = (int)($_POST['order_id'] ?? 0);
    $status = trim($_POST['OrderStatus'] ?? '');
    if ($id<=0) fail('Invalid id');
    if ($status==='') fail('Status required');
    // Prevent direct setting to Completed from the admin order dropdown; only delivery confirmation or pickup should complete
    if (strcasecmp($status,'Completed')===0) fail('Completed can only be set via delivery confirmation');
    $stmt = $conn->prepare("UPDATE orders SET OrderStatus=? WHERE order_id=?");
    if(!$stmt) fail('Prepare failed: '.$conn->error,500);
    $stmt->bind_param('si',$status,$id);
    if(!$stmt->execute()) fail('Update failed: '.$stmt->error,500);
    $stmt->close();
    echo json_encode(['status'=>'ok','action'=>'order_status_updated']);
    exit;
}

// Delivery status update (still allowed, independent from OrderStatus). Prevent setting Delivered if OrderStatus already Completed? We allow; customer confirmation will finalize.
if ($action === 'update_delivery_status') {
    $id = (int)($_POST['order_id'] ?? 0);
    $status = trim($_POST['DeliveryStatus'] ?? '');
    if ($id<=0) fail('Invalid id');
    if ($status==='') fail('Delivery status required');
    // Accept new delivery statuses: Pending, Shipped, Delivered, Completed, Picked up, Failed
    $allowed = ['Pending','Shipped','Delivered','Completed','Picked up','Failed'];
    if(!in_array($status,$allowed,true)) fail('Invalid delivery status');
    // If picked up, set DeliveryStatus='Picked up' and also mark OrderStatus='Completed'
    if (strcasecmp($status,'Picked up')===0) {
        $stmt = $conn->prepare("UPDATE orders SET DeliveryStatus=?, OrderStatus='Completed' WHERE order_id=?");
        if(!$stmt) fail('Prepare failed: '.$conn->error,500);
        $stmt->bind_param('si',$status,$id);
    } else {
        $stmt = $conn->prepare("UPDATE orders SET DeliveryStatus=? WHERE order_id=?");
        if(!$stmt) fail('Prepare failed: '.$conn->error,500);
        $stmt->bind_param('si',$status,$id);
    }
    if(!$stmt->execute()) fail('Update failed: '.$stmt->error,500);
    $stmt->close();
    echo json_encode(['status'=>'ok','action'=>'delivery_status_updated']);
    exit;
}

fail('Unsupported action');
?>