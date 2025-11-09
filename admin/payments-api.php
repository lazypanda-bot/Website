<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../database.php';

function fail($m,$c=400){ http_response_code($c); echo json_encode(['status'=>'error','message'=>$m]); exit; }

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($action === 'list') {
    // Discover orders schema (ids, totals, status may vary in casing/names)
    $ordCols = []; if($oc=$conn->query('SHOW COLUMNS FROM orders')){ while($r=$oc->fetch_assoc()){ $ordCols[strtolower($r['Field'])]=$r['Field']; } $oc->free(); }
    $oIdCol     = $ordCols['order_id']   ?? ($ordCols['id'] ?? ($ordCols['orders_id'] ?? 'order_id'));
    $oCustFkCol = $ordCols['customer_id']?? ($ordCols['user_id'] ?? ($ordCols['account_id'] ?? 'customer_id'));
    $oProdCol   = $ordCols['product_id'] ?? ($ordCols['prod_id'] ?? ($ordCols['products_id'] ?? 'product_id'));
    $oQtyCol    = $ordCols['quantity']   ?? ($ordCols['qty'] ?? 'quantity');
    $oSizeCol   = $ordCols['size']       ?? ($ordCols['sizes'] ?? 'size');
    $oTotalCol  = $ordCols['total_amount'] ?? ($ordCols['totalamount'] ?? ($ordCols['amount'] ?? ($ordCols['total'] ?? 'total_amount')));
    $oPartialCol= $ordCols['partial_payment'] ?? ($ordCols['ispartialpayment'] ?? ($ordCols['partial'] ?? 'partial_payment'));
    $oStatusCol = $ordCols['order_status'] ?? ($ordCols['orderstatus'] ?? ($ordCols['status'] ?? 'order_status'));
    $oCreatedCol= $ordCols['created_at'] ?? ($ordCols['createdat'] ?? ($ordCols['date_created'] ?? 'created_at'));

    // Discover products schema (name/price/pk can vary)
    $prodCols = []; $productsTableExists = false;
    if ($pc = $conn->query('SHOW COLUMNS FROM products')) { $productsTableExists = true; while($pr=$pc->fetch_assoc()){ $prodCols[strtolower($pr['Field'])]=$pr['Field']; } $pc->free(); }
    $pPk = null; foreach(['product_id','id','prod_id','products_id'] as $c){ if(isset($prodCols[$c])) { $pPk = $prodCols[$c]; break; } }
    $pNameCol = null; foreach(['product_name','name','title'] as $c){ if(isset($prodCols[$c])) { $pNameCol = $prodCols[$c]; break; } }
    $pPriceCol = null; foreach(['price','unit_price','amount','cost'] as $c){ if(isset($prodCols[$c])) { $pPriceCol = $prodCols[$c]; break; } }
    $nameExpr = $pNameCol ? ('p.'.$pNameCol.' AS product_name') : "'' AS product_name";
    $priceExpr = $pPriceCol ? ('p.'.$pPriceCol.' AS price') : '0 AS price';
    $joinProducts = ($productsTableExists && $pPk) ? (' LEFT JOIN products p ON p.'.$pPk.' = o.'.$oProdCol.' ') : ' ';

    // Payments table existence and latest-payment join
    $hasPayments = false; if ($t = $conn->query("SHOW TABLES LIKE 'payments'")) { $hasPayments = ($t->num_rows>0); $t->free(); }
    $latestJoin = '';
    if ($hasPayments) {
        // Discover payments column names
        $payCols=[]; if($pc2=$conn->query('SHOW COLUMNS FROM payments')){ while($r=$pc2->fetch_assoc()){ $payCols[strtolower($r['Field'])]=$r['Field']; } $pc2->free(); }
        $payOrderFk = $payCols['order_id'] ?? ($payCols['orders_id'] ?? 'order_id');
        $payDateCol = $payCols['payment_date'] ?? ($payCols['date'] ?? ($payCols['created_at'] ?? 'payment_date'));
        $payAmtCol  = $payCols['payment_amount'] ?? ($payCols['amount'] ?? ($payCols['paid_amount'] ?? 'payment_amount'));
        $payStatCol = $payCols['payment_status'] ?? ($payCols['status'] ?? 'payment_status');
        $payMethCol = $payCols['payment_method'] ?? ($payCols['method'] ?? 'payment_method');

        // Latest payment join using discovered columns
    $latestJoin = " LEFT JOIN (SELECT t1.* FROM payments t1 INNER JOIN (SELECT ".$payOrderFk." AS order_id, MAX(".$payDateCol.") AS max_date FROM payments GROUP BY ".$payOrderFk.") t2 ON t1.".$payOrderFk."=t2.order_id AND t1.".$payDateCol."=t2.max_date) py ON py.".$payOrderFk." = o.".$oIdCol." ";

    // Shared subquery for total paid (Paid or Partial)
    $paidSumSubquery = "(SELECT COALESCE(SUM(py2.".$payAmtCol."),0) FROM payments py2 WHERE py2.".$payOrderFk."=o.".$oIdCol." AND UPPER(py2.".$payStatCol.") IN ('PAID','PARTIAL'))";
    // Amount paid expression using discovered names and multiple casing values for status
    $amountPaidExpr = $paidSumSubquery . " AS AmountPaid";
        // Expose column names for later select list
        $GLOBALS['__PAY_METHOD_COL'] = $payMethCol; $GLOBALS['__PAY_STATUS_COL']=$payStatCol; $GLOBALS['__PAY_AMOUNT_COL']=$payAmtCol; $GLOBALS['__PAY_DATE_COL']=$payDateCol; $GLOBALS['__PAY_ORDERFK_COL']=$payOrderFk;
    } else {
        $paidSumSubquery = '0';
        $amountPaidExpr = '0 AS AmountPaid';
    }

    // Build dynamic total (fallback to order_items sum when stored total is 0) and balance expression
    $dynamicTotalExpr = "CASE WHEN o.".$oTotalCol." IS NULL OR o.".$oTotalCol."=0 THEN (SELECT COALESCE(SUM(oi.line_price),0) FROM order_items oi WHERE oi.order_id = o.".$oIdCol.") ELSE o.".$oTotalCol." END AS TotalAmount";
    $balanceExpr = "CASE WHEN (CASE WHEN o.".$oTotalCol." IS NULL OR o.".$oTotalCol."=0 THEN (SELECT COALESCE(SUM(oi3.line_price),0) FROM order_items oi3 WHERE oi3.order_id = o.".$oIdCol.") ELSE o.".$oTotalCol." END) - " . $paidSumSubquery . " < 0 THEN 0 ELSE (CASE WHEN o.".$oTotalCol." IS NULL OR o.".$oTotalCol."=0 THEN (SELECT COALESCE(SUM(oi4.line_price),0) FROM order_items oi4 WHERE oi4.order_id = o.".$oIdCol.") ELSE o.".$oTotalCol." END) - " . $paidSumSubquery . " END AS Balance";

    // Build final query; orders table is canonical with snake_case per schema; alias to camelCase keys expected by client
    // Try to include receipt/proof column when it exists; discover again here (safe)
        $receiptColDetect = '';
        if ($hasPayments) {
                $payCols2=[]; if($pc3=$conn->query('SHOW COLUMNS FROM payments')){ while($r=$pc3->fetch_assoc()){ $payCols2[strtolower($r['Field'])]=$r['Field']; } $pc3->free(); }
        $receiptColName = $payCols2['receipt_url'] ?? ($payCols2['receipt'] ?? ($payCols2['proof_image'] ?? ($payCols2['payment_proof'] ?? ($payCols2['img_proof'] ?? null))));
                if($receiptColName){ $receiptColDetect = ", COALESCE(py.$receiptColName,'') AS receipt_url "; }
        }
        $selectPaymentFields = $hasPayments
                ? (
                        "COALESCE(py.".$GLOBALS['__PAY_METHOD_COL'].",'') AS payment_method, " .
                        "COALESCE(py.".$GLOBALS['__PAY_STATUS_COL'].",'') AS payment_status, " .
                        "COALESCE(py.".$GLOBALS['__PAY_AMOUNT_COL'].",0) AS payment_amount, " .
                        "py.".$GLOBALS['__PAY_DATE_COL']." AS payment_date" . $receiptColDetect . ","
                    )
                : "'' AS payment_method, '' AS payment_status, 0 AS payment_amount, NULL AS payment_date, '' AS receipt_url,";

    // Derive delivery method heuristically: if delivery_status contains 'Picked' or address is empty -> Pick up, else Standard Delivery
    $deliveryMethodExpr = "CASE WHEN (LOWER(o.delivery_status) LIKE '%picked%' OR COALESCE(NULLIF(TRIM(c.".ACCOUNT_ADDRESS_COL."),''),'')='') THEN 'Pick up' ELSE 'Standard Delivery' END AS delivery_method";
    $paymentTypeExpr = "CASE WHEN o.".$oPartialCol."=1 THEN 'Partial' ELSE 'Full' END AS payment_type";

    $sql = "SELECT o.".$oIdCol." AS order_id, o.".$oCustFkCol." AS customer_id, $dynamicTotalExpr, $balanceExpr, o.".$oPartialCol." AS isPartialPayment, o.".$oStatusCol." AS OrderStatus, o.".$oCreatedCol." AS created_at,
                   c.".ACCOUNT_NAME_COL." AS customer_name, c.".ACCOUNT_PHONE_COL." AS phone, c.".ACCOUNT_ADDRESS_COL." AS address,
                   $nameExpr, $priceExpr, $selectPaymentFields $amountPaidExpr
                   , $deliveryMethodExpr, $paymentTypeExpr
            FROM orders o
            LEFT JOIN ".ACCOUNT_TABLE." c ON c.".ACCOUNT_ID_COL." = o.".$oCustFkCol."" .
            $joinProducts . $latestJoin .
            " ORDER BY o.".$oCreatedCol." DESC";

    $rows=[]; 
    try { $res=$conn->query($sql); } catch(Throwable $e){ fail('Query failed: '.$e->getMessage(),500); }
    if($res){ while($r=$res->fetch_assoc()){ $rows[]=$r; } $res->free(); }
    // Fallback: if no receipt_url from DB and uploads/payments/order_* image exists, attach first one
    $baseDir = realpath(__DIR__ . '/../uploads/payments');
    // Compute site base path like '/Website' so URLs work from admin pages
    $siteBase = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if($baseDir){
        foreach($rows as &$row){
            if(!isset($row['receipt_url']) || $row['receipt_url']===''){
                $oid = isset($row['order_id']) ? (int)$row['order_id'] : 0;
                if($oid>0){
                    // Scan for first file matching order_{id}_*.{ext}
                    $patterns = ["order_{$oid}_*.jpg","order_{$oid}_*.jpeg","order_{$oid}_*.png","order_{$oid}_*.webp","order_{$oid}_*.gif"];
                    foreach($patterns as $pat){
                        $matches = glob($baseDir . DIRECTORY_SEPARATOR . $pat);
                        if($matches && count($matches)>0){
                            $fileName = basename($matches[0]);
                            $row['receipt_url'] = ($siteBase ? $siteBase.'/' : '/') . 'uploads/payments/' . $fileName;
                            break;
                        }
                    }
                }
            } else {
                // Normalize to absolute site path if stored as relative (e.g., 'uploads/payments/...')
                $url = (string)$row['receipt_url'];
                if($url !== '' && strpos($url,'http://')!==0 && strpos($url,'https://')!==0 && strpos($url,'/')!==0){
                    $row['receipt_url'] = ($siteBase ? $siteBase.'/' : '/') . ltrim($url,'/');
                }
            }
        }
        unset($row);
    }
    echo json_encode(['status'=>'ok','payments'=>$rows]);
    exit;
}

// Allow updating payment status for Cash or GCash payments (admin action)
if ($action === 'update_payment_status') {
    $orderId = (int)($_POST['order_id'] ?? 0);
    $newStatus = trim($_POST['PaymentStatus'] ?? '');
    if ($orderId<=0) fail('Invalid order id');
    if ($newStatus==='') fail('Status required');
    // Verify latest payment method is cash
    $payCols=[]; if($pc=$conn->query('SHOW COLUMNS FROM payments')){ while($r=$pc->fetch_assoc()){ $payCols[strtolower($r['Field'])]=$r['Field']; } $pc->free(); }
    $payOrderFk = $payCols['order_id'] ?? 'order_id';
    $payDateCol = $payCols['payment_date'] ?? ($payCols['date'] ?? ($payCols['created_at'] ?? 'payment_date'));
    $payStatCol = $payCols['payment_status'] ?? 'payment_status';
    $payMethCol = $payCols['payment_method'] ?? 'payment_method';
    $payAmtCol  = $payCols['payment_amount'] ?? ($payCols['amount'] ?? ($payCols['paid_amount'] ?? 'payment_amount'));
    $payCustCol = $payCols['customer_id'] ?? ($payCols['user_id'] ?? ($payCols['account_id'] ?? null));
    // Get latest payment row for this order
    $sql = "SELECT $payMethCol AS method FROM payments WHERE $payOrderFk=? ORDER BY $payDateCol DESC LIMIT 1";
    $stmt = $conn->prepare($sql); if(!$stmt) fail('Prepare failed: '.$conn->error,500);
    $stmt->bind_param('i',$orderId); if(!$stmt->execute()) fail('Query failed: '.$stmt->error,500);
    $res = $stmt->get_result(); $row = $res? $res->fetch_assoc():null; $stmt->close();
    $method = strtolower($row['method'] ?? '');
    if($method !== 'cash' && $method !== 'gcash') fail('Only Cash or GCash payments can be updated manually');
    $allowed = ['Partial','Paid']; if(!in_array($newStatus,$allowed,true)) fail('Invalid payment status');
    // Discover order totals
    $ordCols=[]; if($oc=$conn->query('SHOW COLUMNS FROM orders')){ while($r=$oc->fetch_assoc()){ $ordCols[strtolower($r['Field'])]=$r['Field']; } $oc->free(); }
    $oTotalCol  = $ordCols['total_amount'] ?? ($ordCols['totalamount'] ?? ($ordCols['amount'] ?? ($ordCols['total'] ?? 'total_amount')));
    $oIdCol     = $ordCols['order_id'] ?? ($ordCols['id'] ?? 'order_id');
    $oCustFkCol = $ordCols['customer_id'] ?? ($ordCols['user_id'] ?? ($ordCols['account_id'] ?? 'customer_id'));
    $sqlTotal = "SELECT CASE WHEN o.$oTotalCol IS NULL OR o.$oTotalCol=0 THEN (SELECT COALESCE(SUM(oi.line_price),0) FROM order_items oi WHERE oi.order_id=o.$oIdCol) ELSE o.$oTotalCol END AS total FROM orders o WHERE o.$oIdCol=?";
    $stmtT = $conn->prepare($sqlTotal); if(!$stmtT) fail('Prepare failed: '.$conn->error,500);
    $stmtT->bind_param('i',$orderId); if(!$stmtT->execute()) fail('Query failed: '.$stmtT->error,500);
    $resT = $stmtT->get_result(); $rowT = $resT? $resT->fetch_assoc():['total'=>0]; $stmtT->close();
    $total = (float)($rowT['total'] ?? 0);
    // Get order customer id for FK inserts
    $stmtC = $conn->prepare("SELECT $oCustFkCol AS cid FROM orders WHERE $oIdCol=?");
    if(!$stmtC) fail('Prepare failed: '.$conn->error,500);
    $stmtC->bind_param('i',$orderId); if(!$stmtC->execute()) fail('Query failed: '.$stmtC->error,500);
    $resC = $stmtC->get_result(); $rowC = $resC? $resC->fetch_assoc():['cid'=>null]; $stmtC->close();
    $customerId = isset($rowC['cid']) ? (int)$rowC['cid'] : null;

    // Sum already paid (Paid or Partial rows)
    $sqlPaid = "SELECT COALESCE(SUM(p.$payAmtCol),0) AS paid FROM payments p WHERE p.$payOrderFk=? AND UPPER(p.$payStatCol) IN ('PAID','PARTIAL')";
    $stmtP = $conn->prepare($sqlPaid); if(!$stmtP) fail('Prepare failed: '.$conn->error,500);
    $stmtP->bind_param('i',$orderId); if(!$stmtP->execute()) fail('Query failed: '.$stmtP->error,500);
    $resP = $stmtP->get_result(); $rowP = $resP? $resP->fetch_assoc():['paid'=>0]; $stmtP->close();
    $paidSoFar = (float)($rowP['paid'] ?? 0);

    if($newStatus === 'Paid'){
        $outstanding = max($total - $paidSoFar, 0);
        if($outstanding > 0){
            if($payCustCol){
                $ins = $conn->prepare("INSERT INTO payments ($payOrderFk,$payCustCol,$payAmtCol,$payStatCol,$payMethCol,$payDateCol) VALUES (?,?,?,?,?,NOW())");
                if(!$ins) fail('Prepare failed: '.$conn->error,500);
                $methodCap = ($method==='gcash'?'GCash':'Cash'); $status='Paid';
                $ins->bind_param('iidss',$orderId,$customerId,$outstanding,$status,$methodCap);
            } else {
                $ins = $conn->prepare("INSERT INTO payments ($payOrderFk,$payAmtCol,$payStatCol,$payMethCol,$payDateCol) VALUES (?,?,?,?,NOW())");
                if(!$ins) fail('Prepare failed: '.$conn->error,500);
                $methodCap = ($method==='gcash'?'GCash':'Cash'); $status='Paid';
                $ins->bind_param('idss',$orderId,$outstanding,$status,$methodCap);
            }
            if(!$ins->execute()) fail('Insert failed: '.$ins->error,500);
            $ins->close();
            $paidSoFar += $outstanding;
        } else {
            // No outstanding: still add a zero-amount Paid marker if no previous Paid row
            if($paidSoFar >= $total && $total>0){
                // do nothing extra
            }
        }
    } else { // Partial
        // If no partial/paid yet, insert a zero-amount partial placeholder
        if($paidSoFar === 0){
            if($payCustCol){
                $ins = $conn->prepare("INSERT INTO payments ($payOrderFk,$payCustCol,$payAmtCol,$payStatCol,$payMethCol,$payDateCol) VALUES (?,?,?,?,?,NOW())");
                if(!$ins) fail('Prepare failed: '.$conn->error,500);
                $zero=0.0; $methodCap = ($method==='gcash'?'GCash':'Cash'); $status='Partial';
                $ins->bind_param('iidss',$orderId,$customerId,$zero,$status,$methodCap);
            } else {
                $ins = $conn->prepare("INSERT INTO payments ($payOrderFk,$payAmtCol,$payStatCol,$payMethCol,$payDateCol) VALUES (?,?,?,?,NOW())");
                if(!$ins) fail('Prepare failed: '.$conn->error,500);
                $zero=0.0; $methodCap = ($method==='gcash'?'GCash':'Cash'); $status='Partial';
                $ins->bind_param('idss',$orderId,$zero,$status,$methodCap);
            }
            if(!$ins->execute()) fail('Insert failed: '.$ins->error,500);
            $ins->close();
        }
    }
    $balance = max($total - $paidSoFar,0);
    echo json_encode(['status'=>'ok','AmountPaid'=>$paidSoFar,'Balance'=>$balance,'PaymentStatus'=>$newStatus]);
    exit;
}

fail('Unsupported action');
?>