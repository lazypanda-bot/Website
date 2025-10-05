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

if (!$user_id || !$product_id) {
    echo "Error: User not logged in or product not specified.";
    exit;
}

$sql = "INSERT INTO orders (user_id, isPartialPayment, TotalAmount, OrderStatus, DeliveryAddress, DeliveryStatus) VALUES (?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iissss", $user_id, $isPartialPayment, $TotalAmount, $OrderStatus, $DeliveryAddress, $DeliveryStatus);

if ($stmt->execute()) {
    echo "<script>alert('Order placed successfully!'); window.location.href='product-details.php';</script>";
} else {
    echo "Error: " . $stmt->error;
}
$stmt->close();
$conn->close();
?>
