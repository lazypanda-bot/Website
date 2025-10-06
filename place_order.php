<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include 'database.php';
// Adaptive detection for orders table column names (user/customer foreign key & order id) only once per request.
if (!defined('ORDERS_TABLE')) {
    define('ORDERS_TABLE', 'orders');
    $orderCols = [];
    if ($res = $conn->query('SHOW COLUMNS FROM ' . ORDERS_TABLE)) {
        while ($r = $res->fetch_assoc()) { $orderCols[strtolower($r['Field'])] = $r['Field']; }
        $res->free();
    }
    // Determine FK to account table (prefer customer_id, user_id, account_id)
    $fkCol = 'user_id';
    foreach (['customer_id','user_id','account_id','cust_id'] as $c) {
        if (isset($orderCols[$c])) { $fkCol = $orderCols[$c]; break; }
    }
    define('ORDERS_ACCOUNT_FK_COL', $fkCol);
    // Determine primary key
    $pkCol = 'id';
    foreach (['order_id','id','orders_id'] as $c) { if (isset($orderCols[$c])) { $pkCol = $orderCols[$c]; break; } }
    define('ORDERS_PK_COL', $pkCol);
}
session_start();


$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$product_id = isset($_POST['product_id']) ? $_POST['product_id'] : null;
$size = $_POST['size'];
$color = $_POST['color'];
$quantity = $_POST['quantity'];
$isPartialPayment = $_POST['isPartialPayment'];
$TotalAmount = $_POST['TotalAmount'];
$OrderStatus = $_POST['OrderStatus'];
$DeliveryAddress = $_POST['DeliveryAddress'];
$DeliveryStatus = $_POST['DeliveryStatus'];
$created_at = date('Y-m-d H:i:s');
// Fetch phone_number from users table
$phone_number = '';
if ($user_id) {
    $user_stmt = $conn->prepare("SELECT " . ACCOUNT_PHONE_COL . " FROM " . ACCOUNT_TABLE . " WHERE " . ACCOUNT_ID_COL . " = ? LIMIT 1");
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user_stmt->bind_result($phone_number);
    $user_stmt->fetch();
    $user_stmt->close();
}

if (!$user_id || !$product_id) {
    echo "Error: User not logged in or product not specified.";
    exit;
}


$sql = "INSERT INTO " . ORDERS_TABLE . " (" . ORDERS_ACCOUNT_FK_COL . ", isPartialPayment, TotalAmount, OrderStatus, DeliveryAddress, DeliveryStatus, created_at, phone_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iissssss", $user_id, $isPartialPayment, $TotalAmount, $OrderStatus, $DeliveryAddress, $DeliveryStatus, $created_at, $phone_number);

if ($stmt->execute()) {
    echo "<script>alert('Order placed successfully!'); window.location.href='product-details.php';</script>";
} else {
    echo "Error: " . $stmt->error;
}
$stmt->close();
$conn->close();
?>
