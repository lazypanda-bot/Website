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
$sql = "SELECT o." . ORDERS_PK_COL . " AS order_id, o.TotalAmount, o.OrderStatus, o.DeliveryStatus, o." . (defined('ORDERS_CREATED_COL') ? ORDERS_CREATED_COL : 'created_at') . " AS created_col, o.product_id, o.size, o.quantity FROM " . ORDERS_TABLE . " o WHERE o." . ORDERS_ACCOUNT_FK_COL . " = ? ORDER BY created_col DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$orders = [];
$orderIds = [];
while($row = $result->fetch_assoc()) { $orders[] = $row; $orderIds[] = (int)$row['order_id']; }
$stmt->close();

// Fetch line items from order_items (if table exists) for all orders in one query
$itemsMap = [];
if (!empty($orderIds)) {
    $idList = implode(',', array_map('intval',$orderIds));
    // Check if order_items table exists
    if ($res = $conn->query("SHOW TABLES LIKE 'order_items'")) {
        $tableExists = $res->num_rows > 0;
        $res->close();
        if ($tableExists) {
            $oiSql = "SELECT oi.order_id, oi.product_id, oi.size, oi.quantity, oi.line_price, p.product_name
                      FROM order_items oi
                      LEFT JOIN products p ON p.product_id = oi.product_id
                      WHERE oi.order_id IN ($idList)
                      ORDER BY oi.order_id DESC, oi.order_item_id ASC";
            if ($res2 = $conn->query($oiSql)) {
                while($li = $res2->fetch_assoc()) {
                    $oid = (int)$li['order_id'];
                    $itemsMap[$oid][] = $li;
                }
                $res2->close();
            }
        }
    }
}
// Fallback: if no order_items rows for an order but legacy columns present, synthesize a single line item
foreach($orders as $or){ $oid=(int)$or['order_id']; if(empty($itemsMap[$oid]) && !empty($or['product_id'])) { $itemsMap[$oid] = [[ 'order_id'=>$oid, 'product_id'=>$or['product_id'], 'product_name'=>'Product #'.$or['product_id'], 'size'=>$or['size'] ?? 'Default', 'quantity'=>$or['quantity'] ?? 1, 'line_price'=>$or['TotalAmount'] ]]; }}
?>
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <title>Your Orders</title>
    <link rel="stylesheet" href="details.css">
</head>
<body>
    <div class="orders-page-container">
    <h2>Your Orders</h2>
    <div class="orders-table-wrapper">
    <table class="orders-table-class" border="1" cellpadding="8" style="border-collapse:collapse; width:100%;">
        <thead>
            <tr>
                <th style="width:70px;">Order ID</th>
                <th style="width:120px;">Total (₱)</th>
                <th style="width:120px;">Status</th>
                <th style="width:120px;">Delivery</th>
                <th style="width:160px;">Date</th>
                <th>Items</th>
            </tr>
        </thead>
        <tbody>
        <?php if(empty($orders)): ?>
            <tr><td colspan="6" style="text-align:center;padding:30px 0;">No orders yet.</td></tr>
        <?php else: foreach($orders as $row): $oid=(int)$row['order_id']; $lines = $itemsMap[$oid] ?? []; ?>
            <tr class="order-row">
                <td><?php echo htmlspecialchars($row['order_id']); ?></td>
                <td><?php echo htmlspecialchars(number_format((float)$row['TotalAmount'],2)); ?></td>
                <td><?php echo htmlspecialchars($row['OrderStatus']); ?></td>
                <td><?php echo htmlspecialchars($row['DeliveryStatus']); ?></td>
                <td><?php echo htmlspecialchars($row['created_col']); ?></td>
                <td>
                    <?php if(empty($lines)): ?>
                        <div style="font-size:.7rem;color:#555;">No line items.</div>
                    <?php else: ?>
                        <ul class="order-lines" style="list-style:none;margin:0;padding:0;display:grid;gap:6px;">
                            <?php foreach($lines as $li): ?>
                                <li style="background:rgba(255,255,255,.7);border:1px solid #eadbdb;padding:8px 10px;border-radius:10px;display:grid;grid-template-columns: minmax(160px,240px) repeat(4,auto);gap:12px;align-items:center;font-size:.7rem;">
                                    <span style="font-weight:600;color:#752525;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                        <?php echo htmlspecialchars($li['product_name'] ?? ('#'.$li['product_id'])); ?>
                                    </span>
                                    <span style="font-weight:500;">Size: <?php echo htmlspecialchars($li['size'] ?? '—'); ?></span>
                                    <span>Qty: <?php echo htmlspecialchars($li['quantity']); ?></span>
                                    <span>Line: ₱<?php echo htmlspecialchars(number_format((float)($li['line_price'] ?? 0),2)); ?></span>
                                    <span style="font-weight:600;">₱<?php echo htmlspecialchars(number_format(((float)($li['line_price'] ?? 0))*(int)($li['quantity'] ?? 1),2)); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    </div>
    <p style="margin-top:24px;"><a href="products.php">Continue Shopping</a></p>
    </div>
    <style>
        body { font-family:'Poppins',sans-serif; background:linear-gradient(140deg,#fcfcfc 0%, #fafafa 45%, #f6f6f6 100%); margin:0; padding:40px 0 60px; }
        h2 { margin:0 0 24px; font-size:2rem; font-weight:600; color:#752525; letter-spacing:.6px; }
        .orders-page-container { max-width:1400px; width:95%; margin:0 auto; background:rgba(255,255,255,.75); backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px); padding:46px 54px 54px; border-radius:34px; box-shadow:0 25px 55px -18px rgba(117,37,37,.25),0 8px 22px -6px rgba(0,0,0,.12); position:relative; overflow:hidden; }
        .orders-page-container:before, .orders-page-container:after { content:""; position:absolute; border-radius:50%; filter:blur(28px); opacity:.35; pointer-events:none; }
        .orders-page-container:before { width:200px; height:200px; top:-80px; right:-80px; background:radial-gradient(circle at center,rgba(201,6,6,.25),transparent 70%); }
        .orders-page-container:after { width:240px; height:240px; bottom:-110px; left:-110px; background:radial-gradient(circle at center,rgba(154,65,65,.25),transparent 70%); }
        .orders-table-wrapper { overflow:auto; border:1px solid rgba(255,255,255,.7); border-radius:22px; background:linear-gradient(145deg,rgba(255,255,255,.85),rgba(255,255,255,.6)); box-shadow:0 10px 28px -10px rgba(117,37,37,.28),0 4px 12px -4px rgba(0,0,0,.12); }
        table.orders-table-class { border:none; font-size:.85rem; min-width:800px; }
        table.orders-table-class th { background:#752525; color:#fff; font-weight:600; font-size:.7rem; letter-spacing:.7px; text-transform:uppercase; padding:14px 16px; position:sticky; top:0; }
        table.orders-table-class td { padding:12px 16px; background:rgba(255,255,255,.75); border-bottom:1px solid #e9dada; }
        table.orders-table-class tr:nth-child(even) td { background:rgba(0,0,0,0.03); }
        table.orders-table-class tr:hover td { background:rgba(201,6,6,0.08); }
        a { color:#7b1e1e; font-weight:600; text-decoration:none; position:relative; }
        a:after { content:""; position:absolute; left:0; bottom:-2px; height:2px; width:0; background:#c90606; transition:width .35s ease; }
        a:hover:after { width:100%; }
        @media (max-width:1100px){ .orders-page-container { padding:40px 40px 48px; } }
        @media (max-width:840px){ .orders-page-container { padding:34px 30px 44px; } table.orders-table-class { font-size:.78rem; } }
    @media (max-width:640px){ .orders-page-container { padding:30px 22px 40px; border-radius:28px; } h2 { font-size:1.6rem; } table.orders-table-class { min-width:600px; } .order-lines li { grid-template-columns: minmax(140px,190px) repeat(4,auto); } }
    </style>
</body>
</html>
<?php
// Close connection gracefully (statement already closed earlier)
if (isset($conn) && $conn instanceof mysqli) { $conn->close(); }
?>
