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
$sql = "SELECT o." . ORDERS_PK_COL . " AS order_id, o.total_amount AS TotalAmount, o.order_status AS OrderStatus, o.delivery_status AS DeliveryStatus, o." . (defined('ORDERS_CREATED_COL') ? ORDERS_CREATED_COL : 'created_at') . " AS created_col, o.product_id, o.size, o.quantity FROM " . ORDERS_TABLE . " o WHERE o." . ORDERS_ACCOUNT_FK_COL . " = ? ORDER BY created_col DESC";
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
            // Determine product name column dynamically
            $pCols=[]; if($pc=$conn->query('SHOW COLUMNS FROM products')){ while($r=$pc->fetch_assoc()){ $pCols[strtolower($r['Field'])]=$r['Field']; } $pc->free(); }
            $pName = $pCols['product_name'] ?? ($pCols['name'] ?? ($pCols['title'] ?? 'product_name'));
            $pPk   = $pCols['product_id'] ?? ($pCols['id'] ?? ($pCols['prod_id'] ?? 'product_id'));
            $oiSql = "SELECT oi.order_id, oi.product_id, oi.size, oi.quantity, oi.line_price, p.".$pName." AS product_name
                      FROM order_items oi
                      LEFT JOIN products p ON p.".$pPk." = oi.product_id
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
foreach($orders as $or){ $oid=(int)$or['order_id']; if(empty($itemsMap[$oid]) && !empty($or['product_id'])) {
    // Attempt to fetch product name dynamically for the fallback
    $pCols=[]; if($pc=$conn->query('SHOW COLUMNS FROM products')){ while($r=$pc->fetch_assoc()){ $pCols[strtolower($r['Field'])]=$r['Field']; } $pc->free(); }
    $pName = $pCols['product_name'] ?? ($pCols['name'] ?? ($pCols['title'] ?? 'product_name'));
    $pPk   = $pCols['product_id'] ?? ($pCols['id'] ?? ($pCols['prod_id'] ?? 'product_id'));
    $pnameVal = 'Product #'.$or['product_id'];
    if($stmt2=$conn->prepare('SELECT '.$pName.' FROM products WHERE '.$pPk.'=? LIMIT 1')){ $pid=(int)$or['product_id']; $stmt2->bind_param('i',$pid); if($stmt2->execute()){ $stmt2->bind_result($pn); if($stmt2->fetch()){ $pnameVal = $pn; } } $stmt2->close(); }
    $itemsMap[$oid] = [[ 'order_id'=>$oid, 'product_id'=>$or['product_id'], 'product_name'=>$pnameVal, 'size'=>$or['size'] ?? 'Default', 'quantity'=>$or['quantity'] ?? 1, 'line_price'=>$or['TotalAmount'] ]];
}}

// Enrich items with type/attribute and derive line price from products_sub when missing or zero
function enrich_with_subdata($conn, $itemsMap){
    // Inspect products_sub columns
    $psCols=[]; $hasPs=false; if($t=$conn->query("SHOW TABLES LIKE 'products_sub'")){ $hasPs = ($t->num_rows>0); $t->free(); }
    if(!$hasPs) return $itemsMap;
    if($pc=$conn->query('SHOW COLUMNS FROM products_sub')){ while($r=$pc->fetch_assoc()){ $psCols[strtolower($r['Field'])]=$r['Field']; } $pc->free(); }
    $psPrice = $psCols['price'] ?? ($psCols['amount'] ?? 'price');
    $psProd  = $psCols['product_id'] ?? ($psCols['products_id'] ?? ($psCols['prod_id'] ?? 'product_id'));
    $psSizes = $psCols['sizes'] ?? ($psCols['size'] ?? null);
    $psAttrs = $psCols['attributes'] ?? ($psCols['attribute'] ?? ($psCols['color'] ?? ($psCols['colour'] ?? null)));
    $psKind  = $psCols['kind'] ?? ($psCols['type'] ?? ($psCols['name'] ?? null));
    $psValue = $psCols['value'] ?? ($psCols['values'] ?? ($psCols['val'] ?? ($psCols['option_value'] ?? ($psCols['option'] ?? null))));

    foreach($itemsMap as $oid => &$lines){
        foreach($lines as &$li){
            $pid = (int)$li['product_id']; $size = trim((string)($li['size'] ?? ''));
            $price = (float)($li['line_price'] ?? 0);
            $typeVal = null; $attrVal = null; $attrPrice = null; $sizePrice = null; $comboPrice = null; $anyPrice = null;
            // derive type/attribute values via kind/value rows if present
            if($psKind && $psValue){
                if($st = $conn->prepare("SELECT $psValue FROM products_sub WHERE $psProd=? AND $psKind='type' LIMIT 1")){
                    $st->bind_param('i',$pid); if($st->execute()){ $st->bind_result($tv); if($st->fetch()){ $typeVal = $tv; } } $st->close();
                }
            }
            // attribute price or value
            if($psAttrs){
                if($st = $conn->prepare("SELECT $psPrice FROM products_sub WHERE $psProd=? AND $psAttrs IS NOT NULL LIMIT 1")){
                    $st->bind_param('i',$pid); if($st->execute()){ $st->bind_result($ap); if($st->fetch()){ $attrPrice = (float)$ap; } } $st->close();
                }
            } elseif($psKind && $psValue){
                if($st=$conn->prepare("SELECT $psPrice, $psValue FROM products_sub WHERE $psProd=? AND $psKind IN ('attribute','attributes','color','colour') LIMIT 1")){
                    $st->bind_param('i',$pid); if($st->execute()){ $st->bind_result($ap,$av); if($st->fetch()){ $attrPrice=(float)$ap; $attrVal=$av; } } $st->close();
                }
            }
            // size-related price
            if($size !== ''){
                if($psSizes){
                    if($st=$conn->prepare("SELECT $psPrice FROM products_sub WHERE $psProd=? AND $psSizes=? LIMIT 1")){
                        $st->bind_param('is',$pid,$size); if($st->execute()){ $st->bind_result($sp); if($st->fetch()){ $sizePrice=(float)$sp; } } $st->close();
                    }
                } elseif($psKind && $psValue){
                    if($st=$conn->prepare("SELECT $psPrice FROM products_sub WHERE $psProd=? AND $psKind='size' AND $psValue=? LIMIT 1")){
                        $st->bind_param('is',$pid,$size); if($st->execute()){ $st->bind_result($sp); if($st->fetch()){ $sizePrice=(float)$sp; } } $st->close();
                    }
                }
            }
            // combo price if both wide columns exist
            if($psSizes && $psAttrs && $size !== ''){
                if($st=$conn->prepare("SELECT $psPrice, $psAttrs FROM products_sub WHERE $psProd=? AND $psSizes=? AND $psAttrs IS NOT NULL LIMIT 1")){
                    $st->bind_param('is',$pid,$size); if($st->execute()){ $st->bind_result($cp,$av); if($st->fetch()){ $comboPrice=(float)$cp; if(!$attrVal) $attrVal=$av; } } $st->close();
                }
            }
            // any price
            if($st=$conn->prepare("SELECT MAX(CASE WHEN $psPrice>0 THEN $psPrice END) FROM products_sub WHERE $psProd=?")){
                $st->bind_param('i',$pid); if($st->execute()){ $st->bind_result($ap); if($st->fetch()){ $anyPrice=(float)$ap; } } $st->close();
            }
            // choose effective line price if missing/zero
            if($price<=0){
                $eff = null; foreach([$comboPrice,$sizePrice,$attrPrice,$anyPrice] as $cand){ if($cand && $cand>0){ $eff=$cand; break; } }
                if($eff!==null){ $li['line_price'] = $eff; }
            }
            // Attach type/attribute metadata to line
            if($typeVal) $li['type'] = $typeVal;
            if($attrVal) $li['attribute'] = $attrVal;
        }
    }
    return $itemsMap;
}

$itemsMap = enrich_with_subdata($conn, $itemsMap);

// Recompute order totals when stored TotalAmount is missing/zero
foreach($orders as &$or){
    if((float)($or['TotalAmount'] ?? 0) <= 0){
        $sum = 0; $oid=(int)$or['order_id']; $lines = $itemsMap[$oid] ?? [];
        foreach($lines as $li){ $sum += ((float)($li['line_price'] ?? 0)) * ((int)($li['quantity'] ?? 1)); }
        $or['TotalAmount'] = $sum;
    }
}
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
    <table class="orders-table-class orders-table" border="1" cellpadding="8">
        <thead>
            <tr>
                <th class="order-id-th">Order ID</th>
                <th class="order-total-th">Total (₱)</th>
                <th class="order-status-th">Status</th>
                <th class="order-delivery-th">Delivery</th>
                <th class="order-date-th">Date</th>
                <th>Items</th>
            </tr>
        </thead>
        <tbody>
        <?php if(empty($orders)): ?>
            <tr><td colspan="6" class="orders-empty">No orders yet.</td></tr>
        <?php else: foreach($orders as $row): $oid=(int)$row['order_id']; $lines = $itemsMap[$oid] ?? []; ?>
            <tr class="order-row">
                <td><?php echo htmlspecialchars($row['order_id']); ?></td>
                <td><?php echo htmlspecialchars(number_format((float)$row['TotalAmount'],2)); ?></td>
                <td><?php echo htmlspecialchars($row['OrderStatus']); ?></td>
                <td><?php echo htmlspecialchars($row['DeliveryStatus']); ?></td>
                <td><?php echo htmlspecialchars($row['created_col']); ?></td>
                <td>
                    <?php if(empty($lines)): ?>
                        <div class="no-line-items">No line items.</div>
                    <?php else: ?>
                        <ul class="order-lines">
                            <?php foreach($lines as $li): ?>
                        <li class="order-line-item">
                                    <span class="order-line-name">
                                        <?php echo htmlspecialchars($li['product_name'] ?? ('#'.$li['product_id'])); ?>
                                    </span>
                                    <span class="order-line-size">Size: <?php echo htmlspecialchars($li['size'] ?? '—'); ?></span>
                                    <?php if(isset($li['type'])): ?><span class="order-line-type">Type: <?php echo htmlspecialchars($li['type']); ?></span><?php endif; ?>
                                    <?php if(isset($li['attribute'])): ?><span class="order-line-attr">Attribute: <?php echo htmlspecialchars($li['attribute']); ?></span><?php endif; ?>
                                    <span class="order-line-qty">Qty: <?php echo htmlspecialchars($li['quantity']); ?></span>
                                    <span class="order-line-line">Price: ₱<?php echo htmlspecialchars(number_format((float)($li['line_price'] ?? 0),2)); ?></span>
                                    <span class="order-line-total">₱<?php echo htmlspecialchars(number_format(((float)($li['line_price'] ?? 0))*(int)($li['quantity'] ?? 1),2)); ?></span>
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
    <p class="continue-shopping"><a href="products.php">Continue Shopping</a></p>
    </div>

</body>
</html>
<?php
// Close connection gracefully (statement already closed earlier)
if (isset($conn) && $conn instanceof mysqli) { $conn->close(); }
?>
