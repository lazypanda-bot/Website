<?php
// Returns counts of orders by status (Pending, Delivered, Completed, Cancelled)
session_start();
// Buffer output and silence display_errors so we never leak HTML into JSON
if(!ini_get('output_buffering')) @ob_start(); else @ob_start();
@ini_set('display_errors','0');
@ini_set('log_errors','1');
@ini_set('error_log', __DIR__ . '/../logs/orders_stats_api_error.log');
header('Content-Type: application/json');

// Ensure database bootstrap is found relative to admin/
$dbFile = __DIR__ . '/../database.php';
if (!file_exists($dbFile)) {
    while(ob_get_level()) @ob_end_clean();
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>'Server database bootstrap missing']);
    exit;
}
require_once $dbFile;

// if(!isset($_SESSION['is_admin'])) { http_response_code(403); while(ob_get_level()) @ob_end_clean(); echo json_encode(['status'=>'error','message'=>'Forbidden']); exit; }

$counts = ['Pending'=>0,'Delivered'=>0,'Completed'=>0,'Cancelled'=>0];

try {
    // OrderStatus-based counts
    if($res = $conn->query("SELECT order_status AS OrderStatus, COUNT(*) c FROM orders GROUP BY order_status")) {
        while($r=$res->fetch_assoc()) {
            $st = $r['OrderStatus'];
            if(isset($counts[$st])) $counts[$st] = (int)$r['c'];
        }
        $res->free();
    }
    // Delivered = delivery status delivered but not yet confirmed (not completed)
    if($res2 = $conn->query("SELECT COUNT(*) c FROM orders WHERE delivery_status='Delivered' AND order_status<>'Completed'")) {
        if($row=$res2->fetch_assoc()) { $counts['Delivered'] = (int)$row['c']; }
        $res2->free();
    }
    while(ob_get_level()) @ob_end_clean();
    echo json_encode(['status'=>'ok','counts'=>$counts]);
} catch (Throwable $e) {
    error_log('orders-stats-api: '.$e->getMessage());
    while(ob_get_level()) @ob_end_clean();
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>'Failed to fetch stats']);
}
?>
