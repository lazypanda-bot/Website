<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include 'database.php';
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
    $user_stmt = $conn->prepare("SELECT phone_number FROM users WHERE id = ? LIMIT 1");
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


$sql = "INSERT INTO orders (user_id, isPartialPayment, TotalAmount, OrderStatus, DeliveryAddress, DeliveryStatus, created_at, phone_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
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
