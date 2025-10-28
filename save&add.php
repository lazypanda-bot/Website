<?php
// save&add.php (renamed copy of save_and_add_design.php)
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/admin/save-design.php';

// Accept only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') sd_json_err('Invalid method',405);

$color = $_POST['color'] ?? null;
$size = $_POST['size'] ?? null;
$name = $_POST['name'] ?? 'Custom Design';
$meta = $_POST['meta'] ?? null; // stringified JSON
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;

// Use helper to do the inserts
$res = save_design_to_db($conn, $color, $size, $meta, $name);
$customization_id = $res['customization_id'];
$designoption_id = $res['designoption_id'];

// Optionally add to cart if logged in and product_id supplied
$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$cart_inserted = false;
$cart_insert_id = null;
require_once __DIR__ . '/includes/auth.php';
if ($user_id > 0 && $product_id > 0) {
    // Defensive: ensure account still exists before attempting to add to cart
    $user_id = session_user_id_or_zero();
    if ($user_id === 0) { /* skip cart insert */ }
    // Use adaptive cart table column names detection similar to add-to-cart.php
    $cartCols = [];
    if ($res2 = $conn->query('SHOW COLUMNS FROM cart')) {
        while ($r = $res2->fetch_assoc()) { $cartCols[strtolower($r['Field'])] = $r['Field']; }
        $res2->free();
    }
    $userFk = $cartCols['customer_id'] ?? $cartCols['user_id'] ?? 'customer_id';
    $prodFk = $cartCols['product_id'] ?? 'product_id';
    $qtyCol = $cartCols['quantity'] ?? 'quantity';
    $sizeCol = $cartCols['size'] ?? 'size';
    $colorCol = $cartCols['color'] ?? 'color';

    // Detect if cart table has a column to store designoption_id (nullable int)
    $designCol = $cartCols['designoption_id'] ?? $cartCols['design_option_id'] ?? $cartCols['design_id'] ?? null;
    $qty = 1; $s = $size ?: 'Default'; $c = $color ?: 'Standard';
    if ($designCol) {
        // include designoption column in insert
        $insSql = "INSERT INTO cart ({$userFk}, {$prodFk}, {$sizeCol}, {$colorCol}, {$qtyCol}, {$designCol}) VALUES (?,?,?,?,?,?)";
        $ins = $conn->prepare($insSql);
        if ($ins) {
            $did = $designoption_id ? (int)$designoption_id : 0;
            $ins->bind_param('iissii', $user_id, $product_id, $s, $c, $qty, $did);
            if ($ins->execute()) { $cart_inserted = true; $cart_insert_id = $ins->insert_id; }
            $ins->close();
        }
    } else {
        $insSql = "INSERT INTO cart ({$userFk}, {$prodFk}, {$sizeCol}, {$colorCol}, {$qtyCol}) VALUES (?,?,?,?,?)";
        $ins = $conn->prepare($insSql);
        if ($ins) {
            $ins->bind_param('iissi', $user_id, $product_id, $s, $c, $qty);
            if ($ins->execute()) { $cart_inserted = true; $cart_insert_id = $ins->insert_id; }
            $ins->close();
        }
    }
}

echo json_encode(['status'=>'ok','customization_id'=>$customization_id,'designoption_id'=>$designoption_id,'cart_inserted'=>$cart_inserted,'cart_id'=>$cart_insert_id]);
$conn->close();
exit;
