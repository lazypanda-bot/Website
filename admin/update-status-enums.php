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
$res = $conn->query("SHOW COLUMNS FROM `orders` LIKE 'OrderStatus'");
if($res && $row = $res->fetch_assoc()){
    $type = $row['Type'];
    $existing = $type;
    foreach($order_extra as $v){
        if(stripos($existing, "'".$conn->real_escape_string($v)."'") === false){
            echo "OrderStatus missing: $v\n";
            $changed = true;
        }
    }
    $res->free();
} else { echo "Could not read OrderStatus column.\n"; exit; }

$res = $conn->query("SHOW COLUMNS FROM `orders` LIKE 'DeliveryStatus'");
if($res && $row = $res->fetch_assoc()){
    $type = $row['Type'];
    $existing = $type;
    foreach($delivery_extra as $v){
        if(stripos($existing, "'".$conn->real_escape_string($v)."'") === false){
            echo "DeliveryStatus missing: $v\n";
            $changed = true;
        }
    }
    $res->free();
} else { echo "Could not read DeliveryStatus column.\n"; exit; }

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

// Get current types again
$res = $conn->query("SHOW COLUMNS FROM `orders` LIKE 'OrderStatus'"); $row = $res->fetch_assoc(); $orderType = $row['Type']; $res->free();
$res = $conn->query("SHOW COLUMNS FROM `orders` LIKE 'DeliveryStatus'"); $row = $res->fetch_assoc(); $deliveryType = $row['Type']; $res->free();

$orderVals = parse_enum_values($orderType);
$deliveryVals = parse_enum_values($deliveryType);

// add extras if missing
foreach($order_extra as $v){ $q = "'".$conn->real_escape_string($v)."'"; if(!in_array($q,$orderVals)) $orderVals[] = $q; }
foreach($delivery_extra as $v){ $q = "'".$conn->real_escape_string($v)."'"; if(!in_array($q,$deliveryVals)) $deliveryVals[] = $q; }

$newOrderEnum = 'ENUM(' . implode(',', $orderVals) . ') NOT NULL DEFAULT ' . (strpos(implode(',', $orderVals), "'Pending'")!==false ? "'Pending'" : $orderVals[0]);
$newDeliveryEnum = 'ENUM(' . implode(',', $deliveryVals) . ') NOT NULL DEFAULT ' . (strpos(implode(',', $deliveryVals), "'Pending'")!==false ? "'Pending'" : $deliveryVals[0]);

$alterSql = "ALTER TABLE `orders` MODIFY COLUMN `OrderStatus` " . $newOrderEnum . ", MODIFY COLUMN `DeliveryStatus` " . $newDeliveryEnum . ";";

if($conn->query($alterSql) === TRUE){
    echo "Alter successful.\n";
} else {
    echo "Alter failed: " . $conn->error . "\n";
}

?>
