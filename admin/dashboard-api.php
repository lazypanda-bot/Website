<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../database.php';

function fail($m,$c=400){ http_response_code($c); echo json_encode(['status'=>'error','message'=>$m]); exit; }
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// Dynamic column discovery helpers (orders/products)
function discover_order_columns($conn){
    $cols=[]; if($res=$conn->query('SHOW COLUMNS FROM orders')){ while($r=$res->fetch_assoc()){ $cols[strtolower($r['Field'])]=$r['Field']; } $res->free(); }
    return [
        'id'      => $cols['order_id']   ?? ($cols['id'] ?? 'order_id'),
        'cust'    => $cols['customer_id']?? ($cols['user_id'] ?? ($cols['account_id'] ?? 'customer_id')),
        'status'  => $cols['order_status']?? ($cols['orderstatus'] ?? ($cols['status'] ?? 'order_status')),
        'delivery'=> $cols['delivery_status']?? ($cols['deliverystatus'] ?? 'delivery_status'),
        'total'   => $cols['total_amount']?? ($cols['totalamount'] ?? ($cols['amount'] ?? 'total_amount')),
        'created' => $cols['created_at']  ?? ($cols['date_created'] ?? ($cols['created'] ?? 'created_at')),
        'partial' => $cols['partial_payment'] ?? ($cols['ispartialpayment'] ?? ($cols['partial'] ?? 'partial_payment'))
    ];
}

function discover_payment_columns($conn){
    $exists=false; if($t=$conn->query("SHOW TABLES LIKE 'payments'")){ $exists = $t->num_rows>0; $t->free(); }
    if(!$exists) return null;
    $cols=[]; if($res=$conn->query('SHOW COLUMNS FROM payments')){ while($r=$res->fetch_assoc()){ $cols[strtolower($r['Field'])]=$r['Field']; } $res->free(); }
    return [
        'order'  => $cols['order_id'] ?? ($cols['orders_id'] ?? 'order_id'),
        'amount' => $cols['payment_amount'] ?? ($cols['amount'] ?? ($cols['paid_amount'] ?? 'payment_amount')),
        'status' => $cols['payment_status'] ?? ($cols['status'] ?? 'payment_status'),
        'method' => $cols['payment_method'] ?? ($cols['method'] ?? 'payment_method'),
        'date'   => $cols['payment_date'] ?? ($cols['date'] ?? ($cols['created_at'] ?? 'payment_date'))
    ];
}

if($action==='counts'){
    $o = discover_order_columns($conn);
    $today = date('Y-m-d');
    // Today's orders count
    $sqlToday = "SELECT COUNT(*) c FROM orders WHERE DATE(".$o['created'].")=?";
    $stmt = $conn->prepare($sqlToday); $cToday=0; if($stmt){ $stmt->bind_param('s',$today); $stmt->execute(); $stmt->bind_result($cToday); $stmt->fetch(); $stmt->close(); }
    // Status buckets
    $statusCounts = ['Pending'=>0,'Completed'=>0,'Cancelled'=>0,'Picked up'=>0];
    if($res=$conn->query("SELECT " . $o['status'] . " AS st, COUNT(*) c FROM orders GROUP BY " . $o['status'])){
        while($r=$res->fetch_assoc()){ $statusCounts[$r['st']] = (int)$r['c']; }
        $res->free();
    }
    $pending = $statusCounts['Pending'] ?? 0; // treat Delivered as pending? Could extend later.
    $completed = ($statusCounts['Completed'] ?? 0) + ($statusCounts['Picked up'] ?? 0);
    $cancelled = $statusCounts['Cancelled'] ?? 0;
    echo json_encode(['status'=>'ok','counts'=>[
        'today'=>$cToday,
        'pending'=>$pending,
        'completed'=>$completed,
        'cancelled'=>$cancelled
    ]]);
    exit;
}

if($action==='recent_orders'){
    $o = discover_order_columns($conn);
    // Join account table for customer name and attempt to show design via order_items first product designoption?
    $sql = "SELECT o.".$o['id']." AS order_id, o.".$o['status']." AS order_status, o.".$o['delivery']." AS delivery_status, o.".$o['total']." AS total_amount, o.".$o['created']." AS created_at, c.".ACCOUNT_NAME_COL." AS customer_name
        FROM orders o LEFT JOIN ".ACCOUNT_TABLE." c ON c.".ACCOUNT_ID_COL."=o.".$o['cust']." ORDER BY o.".$o['created']." DESC LIMIT 3";
    $rows=[]; if($res=$conn->query($sql)){ while($r=$res->fetch_assoc()){ $rows[]=$r; } $res->free(); }
    echo json_encode(['status'=>'ok','orders'=>$rows]); exit;
}

if($action==='outstanding_payments'){
    $o = discover_order_columns($conn); $p=discover_payment_columns($conn);
    if(!$p){ echo json_encode(['status'=>'ok','payments'=>[]]); exit; }
    // Sum paid per order
    $sql = "SELECT o.".$o['id']." AS order_id, c.".ACCOUNT_NAME_COL." AS customer_name, o.".$o['total']." AS total_amount, o.".$o['created']." AS created_at,
            COALESCE((SELECT SUM(py.".$p['amount'].") FROM payments py WHERE py.".$p['order']."=o.".$o['id']." AND UPPER(py.".$p['status'].") IN ('PAID','PARTIAL')),0) AS paid_amount
            FROM orders o LEFT JOIN ".ACCOUNT_TABLE." c ON c.".ACCOUNT_ID_COL."=o.".$o['cust']." ORDER BY o.".$o['created']." DESC";
    $rows=[]; if($res=$conn->query($sql)){ while($r=$res->fetch_assoc()){ $rows[]=$r; } $res->free(); }
    $out=[]; foreach($rows as $r){ $total=(float)($r['total_amount']??0); $paid=(float)($r['paid_amount']??0); if($paid < $total && $total>0){ $r['balance'] = $total - $paid; $out[]=$r; } if(count($out)>=8) break; }
    echo json_encode(['status'=>'ok','payments'=>$out]); exit;
}

fail('Unsupported action');
?>