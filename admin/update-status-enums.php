<?php
// One-time helper: add missing enum values to orders table for OrderStatus and DeliveryStatus.
// Usage: visit this script once (admin area) or run via CLI. Make a DB backup first.

require_once __DIR__ . '/../database.php';
header('Content-Type: text/plain');

echo "Checking orders table enums...\n";

function col_has_enum($conn, $table, $column, $value){
    $res = $conn->query("SHOW COLUMNS FROM `".$conn->real_escape_string($table)."` LIKE '".$conn->real_escape_string($column)."'");
    if(!$res) return false;
    $row = $res->fetch_assoc(); $res->free();
    if(!$row) return false;
    $type = $row['Type']; // e.g., enum('a','b')
    return stripos($type, "'".$conn->real_escape_string($value)."'") !== false;
}

$changed = false;
// Desired values
$order_extra = [ 'Ready for Pickup', 'Ready to Ship' ];
$delivery_extra = ['Shipped', 'Picked up'];

// Fetch current column types
// Determine actual column names (support both camelCase and snake_case).
$colMap = [];
$colsRes = $conn->query("SHOW COLUMNS FROM `orders`");
if ($colsRes) {
    while ($c = $colsRes->fetch_assoc()) { $colMap[strtolower($c['Field'])] = $c['Field']; }
    $colsRes->free();
}

$orderCol = $colMap['order_status'] ?? ($colMap['orderstatus'] ?? null);
$deliveryCol = $colMap['delivery_status'] ?? ($colMap['deliverystatus'] ?? null);

if (!$orderCol) { echo "Could not read OrderStatus column (order_status or OrderStatus).\n"; exit; }
if (!$deliveryCol) { echo "Could not read DeliveryStatus column (delivery_status or DeliveryStatus).\n"; exit; }

$res = $conn->query("SHOW COLUMNS FROM `orders` LIKE '" . $conn->real_escape_string($orderCol) . "'");
if($res && $row = $res->fetch_assoc()){
    $type = $row['Type'];
    $existing = $type;
    foreach($order_extra as $v){
        if(stripos($existing, "'".$conn->real_escape_string($v)."'") === false){
            echo "{$orderCol} missing: $v\n";
            $changed = true;
        }
    }
    $res->free();
}

$res = $conn->query("SHOW COLUMNS FROM `orders` LIKE '" . $conn->real_escape_string($deliveryCol) . "'");
if($res && $row = $res->fetch_assoc()){
    $type = $row['Type'];
    $existing = $type;
    foreach($delivery_extra as $v){
        if(stripos($existing, "'".$conn->real_escape_string($v)."'") === false){
            echo "{$deliveryCol} missing: $v\n";
            $changed = true;
        }
    }
    $res->free();
}

if(!$changed){ echo "Enums already contain the desired values. No action needed.\n"; exit; }

echo "Attempting to ALTER TABLE to include missing enum values.\n";

// Build new enum definitions by merging existing values and extras
function parse_enum_values($type){
    // type like: enum('Pending','Processing')
    $inside = preg_replace('/^enum\((.*)\)$/i', '$1', $type);
    // split by ',', but values are quoted
    $parts = preg_split("/,(?=(?:[^']*'[^']*')*[^']*$)/", $inside);
    $vals = array_map(function($p){ return trim($p); }, $parts);
    return $vals; // quoted
}

// Get current types again (use detected column names)
$res = $conn->query("SHOW COLUMNS FROM `orders` LIKE '" . $conn->real_escape_string($orderCol) . "'"); $row = $res->fetch_assoc(); $orderType = $row['Type']; $res->free();
$res = $conn->query("SHOW COLUMNS FROM `orders` LIKE '" . $conn->real_escape_string($deliveryCol) . "'"); $row = $res->fetch_assoc(); $deliveryType = $row['Type']; $res->free();

$orderVals = parse_enum_values($orderType);
$deliveryVals = parse_enum_values($deliveryType);

// add extras if missing
foreach($order_extra as $v){ $q = "'".$conn->real_escape_string($v)."'"; if(!in_array($q,$orderVals)) $orderVals[] = $q; }
foreach($delivery_extra as $v){ $q = "'".$conn->real_escape_string($v)."'"; if(!in_array($q,$deliveryVals)) $deliveryVals[] = $q; }

$newOrderEnum = 'ENUM(' . implode(',', $orderVals) . ') NOT NULL DEFAULT ' . (strpos(implode(',', $orderVals), "'Pending'")!==false ? "'Pending'" : $orderVals[0]);
$newDeliveryEnum = 'ENUM(' . implode(',', $deliveryVals) . ') NOT NULL DEFAULT ' . (strpos(implode(',', $deliveryVals), "'Pending'")!==false ? "'Pending'" : $deliveryVals[0]);

$alterSql = "ALTER TABLE `orders` MODIFY COLUMN `" . $conn->real_escape_string($orderCol) . "` " . $newOrderEnum . ", MODIFY COLUMN `" . $conn->real_escape_string($deliveryCol) . "` " . $newDeliveryEnum . ";";

if($conn->query($alterSql) === TRUE){
    echo "Alter successful.\n";
} else {
    echo "Alter failed: " . $conn->error . "\n";
}

?>
