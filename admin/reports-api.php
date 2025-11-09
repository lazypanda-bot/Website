<?php
session_start();
header('Content-Type: application/json');
require_once '../database.php';
function fail($m,$c=400){ http_response_code($c); echo json_encode(['status'=>'error','message'=>$m]); exit; }
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($action === 'weekly') {
    // Weekly aggregation. We compute:
    // - total_orders
    // - completed_orders (Completed or Picked up)
    // - pending_orders (Pending/Processing/Ready to .../Delivered not completed)
    // - cancelled_orders
    // - pending_payments (#orders where AmountPaid < Total)
    // - profit (sum of AmountPaid)

    // payments aggregate by order
    $paidMap = [];
    if($t=$conn->query("SHOW TABLES LIKE 'payments'")){
        if($t->num_rows>0){
            $t->free();
            $res = $conn->query("SELECT order_id, COALESCE(SUM(CASE WHEN UPPER(payment_status) IN ('PAID','PARTIAL') THEN payment_amount ELSE 0 END),0) AS paid FROM payments GROUP BY order_id");
            if($res){ while($r=$res->fetch_assoc()){ $paidMap[(int)$r['order_id']] = (float)$r['paid']; } $res->free(); }
        } else { $t->free(); }
    }

    // Pull minimal order info to compute dynamic totals and statuses
    $rows=[]; $data=[];
    $q = "SELECT order_id, order_status, delivery_status, total_amount, created_at FROM orders";
    if($res=$conn->query($q)){
        while($o=$res->fetch_assoc()){
            $oid=(int)$o['order_id'];
            $created=$o['created_at'];
            $year = (int)date('o', strtotime($created));
            $week = (int)date('W', strtotime($created));
            $key = sprintf('%04d-%02d',$year,$week);
            if(!isset($data[$key])){
                // Compute friendly week range label (Mon–Sun)
                $monday = new DateTime();
                $monday->setISODate($year, $week); // Monday of ISO week
                $sunday = clone $monday; $sunday->modify('+6 days');
                $dateLabel = $monday->format('M j') . '–' . $sunday->format('j, Y');
                $data[$key] = [
                    'date_label' => $dateLabel,
                    'total_orders'=>0,
                    'completed_orders'=>0,
                    'pending_orders'=>0,
                    'cancelled_orders'=>0,
                    'pending_payments'=>0,
                    'profit'=>0.0
                ];
            }
            $status = trim((string)($o['order_status'] ?? ''));
            $statusU = strtoupper($status);
            $delivery = trim((string)($o['delivery_status'] ?? ''));
            $deliveryU = strtoupper($delivery);
            $data[$key]['total_orders']++;

            $isCompleted = ($statusU==='COMPLETED' || $statusU==='PICKED UP' || $statusU==='PICKED-UP' || $statusU==='PICKEDUP');
            $isCancelled = ($statusU==='CANCELLED' || $statusU==='CANCELED');
            // Consider Delivered as pending until completion
            $isPending = (!$isCompleted && !$isCancelled);
            if($isCompleted) $data[$key]['completed_orders']++;
            elseif($isCancelled) $data[$key]['cancelled_orders']++;
            elseif($isPending) $data[$key]['pending_orders']++;

            // Dynamic total fallback
            $total = (float)($o['total_amount'] ?? 0);
            if($total<=0){
                $totRes = $conn->query('SELECT COALESCE(SUM(line_price),0) t FROM order_items WHERE order_id='.$oid);
                if($totRes){ $r=$totRes->fetch_assoc(); $total=(float)($r['t']??0); $totRes->free(); }
            }
            $paid = $paidMap[$oid] ?? 0.0;
            if($paid < $total) $data[$key]['pending_payments']++;
            $data[$key]['profit'] += $paid;
        }
        $res->free();
    }
    // Turn into rows sorted descending by year-week, limit 12
    krsort($data);
    $out=[]; $i=0; foreach($data as $k=>$v){ $v['yw']=$k; $out[]=$v; if(++$i>=12) break; }
    echo json_encode(['status'=>'ok','weekly'=>$out]);
    exit;
}

fail('Unsupported action');
?>
