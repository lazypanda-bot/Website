<?php
include 'database.php';
// Adaptive detection for orders table columns (only if not already defined)
if (!defined('ORDERS_TABLE')) {
    define('ORDERS_TABLE', 'orders');
    $orderCols = [];
    if ($res = $conn->query('SHOW COLUMNS FROM ' . ORDERS_TABLE)) {
        while ($r = $res->fetch_assoc()) { $orderCols[strtolower($r['Field'])] = $r['Field']; }
        $res->free();
    }
    $fkCol = 'user_id';
    foreach (['customer_id','user_id','account_id','cust_id'] as $c) { if (isset($orderCols[$c])) { $fkCol = $orderCols[$c]; break; } }
    define('ORDERS_ACCOUNT_FK_COL', $fkCol);
    $pkCol = 'id';
    foreach (['order_id','id','orders_id'] as $c) { if (isset($orderCols[$c])) { $pkCol = $orderCols[$c]; break; } }
    define('ORDERS_PK_COL', $pkCol);
    $createdCol = 'created_at';
    foreach (['created_at','order_date','date_created','created'] as $c) { if (isset($orderCols[$c])) { $createdCol = $orderCols[$c]; break; } }
    define('ORDERS_CREATED_COL', $createdCol);
}
session_start();
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
if (!$user_id) {
    echo "<h2>Please log in to view your orders.</h2>";
    exit;
}
$sql = "SELECT o." . ORDERS_PK_COL . " AS order_id, o.TotalAmount, o.OrderStatus, o.DeliveryStatus, o." . (defined('ORDERS_CREATED_COL') ? ORDERS_CREATED_COL : 'created_at') . " AS created_col FROM " . ORDERS_TABLE . " o WHERE o." . ORDERS_ACCOUNT_FK_COL . " = ? ORDER BY created_col DESC";
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
            <td><?php echo htmlspecialchars($row['order_id']); ?></td>
            <td><?php echo htmlspecialchars($row['TotalAmount']); ?></td>
            <td><?php echo htmlspecialchars($row['OrderStatus']); ?></td>
            <td><?php echo htmlspecialchars($row['DeliveryStatus']); ?></td>
            <td><?php echo htmlspecialchars($row['created_col']); ?></td>
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
