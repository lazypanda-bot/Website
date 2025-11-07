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
    // Adapt to products schema differences (name/price column existence, PK variations)
    $prodCols = []; $productsTableExists = false;
    if ($pc = $conn->query('SHOW COLUMNS FROM products')) { $productsTableExists = true; while($pr=$pc->fetch_assoc()){ $prodCols[strtolower($pr['Field'])]=$pr['Field']; } $pc->free(); }
    $pPk = null; foreach(['product_id','id','prod_id','products_id'] as $c){ if(isset($prodCols[$c])) { $pPk = $prodCols[$c]; break; } }
    $pNameCol = null; foreach(['product_name','name','title'] as $c){ if(isset($prodCols[$c])) { $pNameCol = $prodCols[$c]; break; } }
    $pPriceCol = null; foreach(['price','unit_price','amount','cost'] as $c){ if(isset($prodCols[$c])) { $pPriceCol = $prodCols[$c]; break; } }
    $nameExpr = $pNameCol ? ('p.'.$pNameCol.' AS product_name') : "'' AS product_name";
    $priceExpr = $pPriceCol ? ('p.'.$pPriceCol.' AS price') : '0 AS price';
    $joinProducts = ($productsTableExists && $pPk) ? (' LEFT JOIN products p ON p.'.$pPk.' = o.product_id ') : ' ';

    // AmountPaid: sum of payments for order (Paid or Partial) for display. If payments table large, consider separate endpoint / LIMIT.
    // Select using snake_case DB columns but alias to legacy camelCase keys for client-side compatibility
    $sql = "SELECT o.order_id, o.product_id, o.customer_id, o.size, o.quantity, o.order_status AS OrderStatus, o.delivery_status AS DeliveryStatus, o.total_amount AS TotalAmount, o.partial_payment AS isPartialPayment, o.created_at".$completedFrag.
        ", c.".ACCOUNT_NAME_COL." AS customer_name, c.".ACCOUNT_PHONE_COL." AS phone, c.".ACCOUNT_ADDRESS_COL." AS address, $nameExpr, $priceExpr,
         do.designoption_id, do.designfilepath, cu.color AS design_color, cu.note AS design_note,
         (SELECT COALESCE(SUM(py.payment_amount),0) FROM payments py WHERE py.order_id = o.order_id AND py.payment_status IN ('Paid','Partial')) AS AmountPaid
         FROM orders o
         LEFT JOIN ".ACCOUNT_TABLE." c ON c.".ACCOUNT_ID_COL." = o.customer_id" . $joinProducts . "
         LEFT JOIN designoption do ON do.designoption_id = o.designoption_id
         LEFT JOIN customization cu ON cu.customization_id = do.customization_id
         ORDER BY o.created_at DESC";
    $rows = [];
    try {
        $res = $conn->query($sql);
    } catch (Throwable $e) {
        fail('Query failed: '.$e->getMessage(),500);
    }
    if(!$res){ fail('Query failed: '.$conn->error,500); }
    while($r=$res->fetch_assoc()) { $rows[]=$r; }
    $res->free();
    echo json_encode(['status'=>'ok','orders'=>$rows]);
    exit;
}

// Update order status only
if ($action === 'update_status') {
    $id = (int)($_POST['order_id'] ?? 0);
    // Accept legacy key 'OrderStatus' from client but store in snake_case column
    $status = trim($_POST['OrderStatus'] ?? '');
    if ($id<=0) fail('Invalid id');
    if ($status==='') fail('Status required');
    // Prevent direct setting to Completed from the admin order dropdown; only delivery confirmation or pickup should complete
    if (strcasecmp($status,'Completed')===0) fail('Completed can only be set via delivery confirmation');
    $stmt = $conn->prepare("UPDATE orders SET order_status=? WHERE order_id=?");
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
    // Accept legacy key 'DeliveryStatus' from client but store in snake_case column
    $status = trim($_POST['DeliveryStatus'] ?? '');
    if ($id<=0) fail('Invalid id');
    if ($status==='') fail('Delivery status required');
    // Accept new delivery statuses: Pending, Shipped, Delivered, Completed, Picked up, Failed
    $allowed = ['Pending','Shipped','Delivered','Completed','Picked up','Failed'];
    if(!in_array($status,$allowed,true)) fail('Invalid delivery status');
    // If picked up, set DeliveryStatus='Picked up' and also mark OrderStatus='Completed'
    if (strcasecmp($status,'Picked up')===0) {
        $stmt = $conn->prepare("UPDATE orders SET delivery_status=?, order_status='Completed' WHERE order_id=?");
        if(!$stmt) fail('Prepare failed: '.$conn->error,500);
        $stmt->bind_param('si',$status,$id);
    } else {
    $stmt = $conn->prepare("UPDATE orders SET delivery_status=? WHERE order_id=?");
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