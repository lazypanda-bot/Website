<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include 'database.php';
$id = $_POST['id'];
$sql = "DELETE FROM cart WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
if ($stmt->execute()) {
    echo "<script>alert('Item deleted!'); window.location.href='cart.php';</script>";
} else {
    echo "Error: " . $stmt->error;
}
$stmt->close();
$conn->close();
?>
