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

// If a PNG blob was uploaded as 'design_png', save it to uploads/designs and update the designoption record
if (isset($_FILES['design_png']) && $_FILES['design_png']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = __DIR__ . '/uploads/designs';
    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
    $tmp = $_FILES['design_png']['tmp_name'];
    $ext = 'png';
    $fname = 'design_' . intval($designoption_id) . '_' . time() . '.' . $ext;
    $destPath = $uploadDir . '/' . $fname;
    if (@move_uploaded_file($tmp, $destPath)) {
        // store web-accessible path 
        $webPath = 'uploads/designs/' . $fname;

        // Create a visible thumbnail (240x240) with a neutral background so mostly-transparent
        // or white images are visible in the product preview.
        $thumbName = 'thumb_' . $fname;
        $thumbPath = $uploadDir . '/' . $thumbName;
        $thumbWeb = 'uploads/designs/' . $thumbName;
        // Try GD-based thumbnail creation if available
        if (function_exists('imagecreatefrompng')) {
            try {
                $srcImg = @imagecreatefrompng($destPath);
                if ($srcImg !== false) {
                    // Create destination image
                    $tw = 240; $th = 240;
                    $dst = imagecreatetruecolor($tw, $th);
                    // Fill with light gray background
                    $bg = imagecolorallocate($dst, 238, 238, 238);
                    imagefill($dst, 0, 0, $bg);
                    // Preserve alpha for source
                    imagealphablending($srcImg, true);
                    imagesavealpha($srcImg, true);

                    // Compute scaled size to fit inside thumb while preserving aspect
                    $sw = imagesx($srcImg);
                    $sh = imagesy($srcImg);
                    if ($sw > 0 && $sh > 0) {
                        $scale = min($tw / $sw, $th / $sh);
                        $nw = (int)($sw * $scale);
                        $nh = (int)($sh * $scale);
                        $dstX = (int)(($tw - $nw) / 2);
                        $dstY = (int)(($th - $nh) / 2);
                        // Copy resampled onto the gray background
                        imagecopyresampled($dst, $srcImg, $dstX, $dstY, 0, 0, $nw, $nh, $sw, $sh);
                        // Save as PNG
                        imagepng($dst, $thumbPath, 6);
                        imagedestroy($dst);
                    }
                    imagedestroy($srcImg);
                }
            } catch (Throwable $e) { /* ignore thumbnail failure */ }
        }

        // If thumbnail creation failed, just copy the original to thumb path as fallback
        if (!file_exists($thumbPath)) {
            @copy($destPath, $thumbPath);
        }

        // Update DB to point to the thumbnail for previewing
        $upd = $conn->prepare('UPDATE designoption SET designfilepath = ? WHERE designoption_id = ?');
        if ($upd) {
            $upd->bind_param('si', $thumbWeb, $designoption_id);
            $upd->execute();
            $upd->close();
        }
    }
}

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
    // Detect any direct design filepath column too
    $designPathCol = $cartCols['designfilepath'] ?? ($cartCols['design_file'] ?? ($cartCols['designpath'] ?? ($cartCols['design'] ?? null)));
    $hasPath = $designPathCol && isset($thumbWeb) && $thumbWeb;
    if ($designCol && $hasPath) {
        $insSql = "INSERT INTO cart ({$userFk}, {$prodFk}, {$sizeCol}, {$colorCol}, {$qtyCol}, {$designCol}, {$designPathCol}) VALUES (?,?,?,?,?,?,?)";
        $ins = $conn->prepare($insSql);
        if ($ins) {
            $did = $designoption_id ? (int)$designoption_id : 0; $path = $thumbWeb;
            $ins->bind_param('iississ', $user_id, $product_id, $s, $c, $qty, $did, $path);
            if ($ins->execute()) { $cart_inserted = true; $cart_insert_id = $ins->insert_id; }
            $ins->close();
        }
    } elseif ($designCol) {
        // include designoption column in insert
        $insSql = "INSERT INTO cart ({$userFk}, {$prodFk}, {$sizeCol}, {$colorCol}, {$qtyCol}, {$designCol}) VALUES (?,?,?,?,?,?)";
        $ins = $conn->prepare($insSql);
        if ($ins) {
            $did = $designoption_id ? (int)$designoption_id : 0;
            $ins->bind_param('iissii', $user_id, $product_id, $s, $c, $qty, $did);
            if ($ins->execute()) { $cart_inserted = true; $cart_insert_id = $ins->insert_id; }
            $ins->close();
        }
    } elseif ($hasPath) {
        $insSql = "INSERT INTO cart ({$userFk}, {$prodFk}, {$sizeCol}, {$colorCol}, {$qtyCol}, {$designPathCol}) VALUES (?,?,?,?,?,?)";
        $ins = $conn->prepare($insSql);
        if ($ins) {
            $path = $thumbWeb;
            $ins->bind_param('iissis', $user_id, $product_id, $s, $c, $qty, $path);
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

// Attempt to retroactively attach the created designoption to an existing cart row
// when a cart row exists for this user and product but earlier logic didn't set it.
// This helps cases where the client saved the design after already adding the
// product to cart (so the cart row exists but has no designoption_id yet).
if ($user_id > 0 && $designoption_id && $product_id > 0) {
    $cartCols2 = [];
    if ($res3 = $conn->query('SHOW COLUMNS FROM cart')) {
        while ($r = $res3->fetch_assoc()) { $cartCols2[strtolower($r['Field'])] = $r['Field']; }
        $res3->free();
    }
    $userFk2 = $cartCols2['customer_id'] ?? $cartCols2['user_id'] ?? $cartCols2['account_id'] ?? null;
    $prodFk2 = $cartCols2['product_id'] ?? $cartCols2['prod_id'] ?? $cartCols2['item_id'] ?? null;
    $designColCandidates = ['designoption_id','design_option_id','design_id','designfilepath','design_file','designpath','design'];
    $foundDesignCol = null;
    foreach ($designColCandidates as $c) { if (isset($cartCols2[strtolower($c)])) { $foundDesignCol = $cartCols2[strtolower($c)]; break; } }
    if ($foundDesignCol && $userFk2 && $prodFk2) {
        $updSql = "UPDATE cart SET {$foundDesignCol} = ? WHERE {$userFk2} = ? AND {$prodFk2} = ? AND ({$foundDesignCol} IS NULL OR {$foundDesignCol} = '' OR {$foundDesignCol} = 0) LIMIT 1";
        $upd = $conn->prepare($updSql);
        if ($upd) { $upd->bind_param('iii', $designoption_id, $user_id, $product_id); $upd->execute(); $upd->close(); }
    }
    // Also attempt to set a path column if exists and empty
    $pathCol = $cartCols2['designfilepath'] ?? ($cartCols2['design_file'] ?? ($cartCols2['designpath'] ?? ($cartCols2['design'] ?? null)));
    if ($pathCol && isset($thumbWeb) && $thumbWeb && $userFk2 && $prodFk2) {
        $upd2 = $conn->prepare("UPDATE cart SET {$pathCol} = ? WHERE {$userFk2} = ? AND {$prodFk2} = ? AND ({$pathCol} IS NULL OR {$pathCol} = '') LIMIT 1");
        if ($upd2) { $u = $thumbWeb; $upd2->bind_param('sii', $u, $user_id, $product_id); $upd2->execute(); $upd2->close(); }
    }
}

// Include the thumbnail web path in the response when available so clients can
// immediately preview the saved design without an extra DB roundtrip.
$resp = [
    'status' => 'ok',
    'customization_id' => $customization_id,
    'designoption_id' => $designoption_id,
    'cart_inserted' => $cart_inserted,
    'cart_id' => $cart_insert_id,
    'designfilepath' => isset($thumbWeb) ? $thumbWeb : null
];
echo json_encode($resp);
$conn->close();
exit;
