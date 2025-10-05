<?php
include 'database.php';
session_start();
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
if (!$user_id) {
    echo "<h2>Please log in to view your orders.</h2>";
    exit;
}
$sql = "SELECT orders.id, orders.TotalAmount, orders.OrderStatus, orders.DeliveryStatus, orders.created_at FROM orders WHERE orders.user_id = ? ORDER BY orders.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <title>Your Orders</title>
    <link rel="stylesheet" href="details.css">
</head>
<body>
    <h2>Your Orders</h2>
    <table border="1" cellpadding="8" style="border-collapse:collapse;">
        <tr>
            <th>Order ID</th>
            <th>Total Amount</th>
            <th>Status</th>
            <th>Delivery</th>
            <th>Date</th>
        </tr>
        <?php while($row = $result->fetch_assoc()): ?>
        <tr>
            <td><?php echo htmlspecialchars($row['id']); ?></td>
            <td><?php echo htmlspecialchars($row['TotalAmount']); ?></td>
            <td><?php echo htmlspecialchars($row['OrderStatus']); ?></td>
            <td><?php echo htmlspecialchars($row['DeliveryStatus']); ?></td>
            <td><?php echo htmlspecialchars($row['created_at']); ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
    <p><a href="products.php">Continue Shopping</a></p>
</body>
</html>
<?php
$stmt->close();
$conn->close();
?>
