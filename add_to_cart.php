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

if (!$user_id || !$product_id) {
    echo "Error: User not logged in or product not specified.";
    exit;
}

$sql = "INSERT INTO cart (user_id, product_id, size, color, quantity) VALUES (?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iissi", $user_id, $product_id, $size, $color, $quantity);

if ($stmt->execute()) {
    echo "<script>alert('Added to cart!'); window.location.href='cart.php';</script>";
} else {
    echo "<h3 style='color:red'>Error adding to cart: ".$stmt->error."<br>";
    echo "user_id: $user_id<br>product_id: $product_id<br>size: $size<br>color: $color<br>quantity: $quantity<br></h3>";
    echo "<a href='product-details.php'>Back to product</a>";
}
$stmt->close();
$conn->close();
?>
