<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/database.php';

$isAuthenticated = isset($_SESSION['user_id']);
$username = $email = $address = $phone = $avatarPath = '';
$userOrders = [];
if ($isAuthenticated) {
    // Handle profile update or delete BEFORE any output
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $userId = $_SESSION['user_id'];
        // Avatar upload handling (independent quick form)
        if (isset($_POST['upload_avatar']) && isset($_FILES['avatar_file'])) {
            $file = $_FILES['avatar_file'];
            if ($file['error'] === UPLOAD_ERR_OK) {
                $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
                $mime = mime_content_type($file['tmp_name']);
                if (isset($allowed[$mime])) {
                    $ext = $allowed[$mime];
                    $destDir = __DIR__ . '/uploads/avatars';
                    if (!is_dir($destDir)) { @mkdir($destDir, 0775, true); }
                    $newName = 'avatar_' . $userId . '_' . time() . '.' . $ext;
                    $destPath = $destDir . '/' . $newName;
                    if (move_uploaded_file($file['tmp_name'], $destPath)) {
                        $relPath = 'uploads/avatars/' . $newName;
                        if (!$conn->connect_error) {
                            $stmt = $conn->prepare("UPDATE " . ACCOUNT_TABLE . " SET " . ACCOUNT_AVATAR_COL . " = ? WHERE " . ACCOUNT_ID_COL . " = ?");
                            $stmt->bind_param('si', $relPath, $userId);
                            $stmt->execute();
                            $stmt->close();
                        }
                        header('Location: profile.php?avatar=1');
                        exit();
                    }
                }
            }
        }
        // If delete is set, delete the user
        if (isset($_POST['delete_account'])) {
            if (!$conn->connect_error) {
                $stmt = $conn->prepare("DELETE FROM " . ACCOUNT_TABLE . " WHERE " . ACCOUNT_ID_COL . " = ?");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $stmt->close();
                // Log out and redirect to home
                session_destroy();
                header("Location: home.php?deleted=1");
                exit();
            }
        } else {
            $address = trim($_POST['address'] ?? '');
            $phoneRaw = trim($_POST['phone'] ?? '');
            $digits = preg_replace('/\D+/', '', $phoneRaw);
            if (strlen($digits) > 15) { $digits = substr($digits, 0, 15); }
            $MIN_PHONE_DIGITS = 11; // configurable minimum

            if (!$conn->connect_error) {
                // Fetch current stored values
                $stmt = $conn->prepare("SELECT " . ACCOUNT_ADDRESS_COL . ", " . ACCOUNT_PHONE_COL . " FROM " . ACCOUNT_TABLE . " WHERE " . ACCOUNT_ID_COL . " = ?");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $stmt->bind_result($currentAddress, $currentPhone);
                $stmt->fetch();
                $stmt->close();

                if ($address === '') $address = $currentAddress;
                $invalidPhone = false;
                if ($digits === '') {
                    $finalPhone = $currentPhone; // unchanged
                } elseif (strlen($digits) < $MIN_PHONE_DIGITS) {
                    $invalidPhone = true;
                    $finalPhone = $currentPhone; // keep existing
                } else {
                    $finalPhone = $digits;
                }

                $stmt = $conn->prepare("UPDATE " . ACCOUNT_TABLE . " SET " . ACCOUNT_ADDRESS_COL . " = ?, " . ACCOUNT_PHONE_COL . " = ? WHERE " . ACCOUNT_ID_COL . " = ?");
                $stmt->bind_param("ssi", $address, $finalPhone, $userId);
                $stmt->execute();
                $stmt->close();
                if ($invalidPhone) {
                    header("Location: profile.php?invalid_phone=1");
                } else {
                    header("Location: profile.php?updated=1");
                }
                exit();
            }
        }
    }
    // Use existing $conn from database.php
    // fetch user data
    $userId = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT " . ACCOUNT_NAME_COL . ", " . ACCOUNT_EMAIL_COL . ", " . ACCOUNT_ADDRESS_COL . ", " . ACCOUNT_PHONE_COL . ", " . ACCOUNT_AVATAR_COL . " FROM " . ACCOUNT_TABLE . " WHERE " . ACCOUNT_ID_COL . " = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($username, $email, $address, $phone, $avatarPath);
    $stmt->fetch();
    $stmt->close();

    // Fetch user orders (multiple orders support)
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
        $pkCol = 'order_id';
        foreach (['order_id','id','orders_id'] as $c) { if (isset($orderCols[$c])) { $pkCol = $orderCols[$c]; break; } }
        define('ORDERS_PK_COL', $pkCol);
        $createdCol = 'created_at';
        foreach (['created_at','order_date','date_created','created'] as $c) { if (isset($orderCols[$c])) { $createdCol = $orderCols[$c]; break; } }
        define('ORDERS_CREATED_COL', $createdCol);
    }
    $orderItemsMap = [];
    if ($isAuthenticated && !$conn->connect_error) {
        $sqlOrders = "SELECT o." . ORDERS_PK_COL . " AS order_id, o.TotalAmount, o.OrderStatus, o.DeliveryStatus, o." . ORDERS_CREATED_COL . " AS created_col, o.product_id, o.size, o.quantity, p.product_name FROM " . ORDERS_TABLE . " o LEFT JOIN products p ON p.product_id = o.product_id WHERE o." . ORDERS_ACCOUNT_FK_COL . " = ? ORDER BY created_col DESC";
        if ($stmtO = $conn->prepare($sqlOrders)) {
            $stmtO->bind_param('i', $userId);
            if ($stmtO->execute()) {
                $resO = $stmtO->get_result();
                $orderIds = [];
                while ($row = $resO->fetch_assoc()) { $userOrders[] = $row; $orderIds[] = (int)$row['order_id']; }
                $resO->free();
                if (count($orderIds) > 0) {
                    $in = implode(',', array_map('intval', $orderIds));
                    $sqlItems = "SELECT oi.order_id, oi.product_id, oi.size, oi.quantity, oi.line_price, p.product_name FROM order_items oi LEFT JOIN products p ON p.product_id = oi.product_id WHERE oi.order_id IN ($in) ORDER BY oi.order_id DESC, oi.order_item_id ASC";
                    if ($resI = $conn->query($sqlItems)) { while ($ri = $resI->fetch_assoc()) { $orderItemsMap[$ri['order_id']][] = $ri; } $resI->free(); }
                }
            }
            $stmtO->close();
        }
        foreach ($userOrders as $uo) {
            $oid=(int)$uo['order_id'];
            if (empty($orderItemsMap[$oid]) && !empty($uo['product_id'])) {
                $orderItemsMap[$oid] = [[
                    'order_id'=>$oid,
                    'product_id'=>$uo['product_id'],
                    'product_name'=>$uo['product_name'] ?? 'Product',
                    'size'=>$uo['size'] ?? 'Default',
                    'quantity'=>$uo['quantity'] ?? 1,
                    'line_price'=>$uo['TotalAmount']
                ]];
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>My Profile</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <link href="navbar-footer.css" rel="stylesheet" />
    <link href="style.css" rel="stylesheet" />
    <link href="login.css" rel="stylesheet" />
    <link rel="stylesheet" href="about.css">
    <link rel="stylesheet" href="message.css">
    <link rel="stylesheet" href="services.css">
    <link rel="stylesheet" href="profile.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css" />
</head>
<body class="profile-page">
    <section id="header">
        <div class="left-nav">
            <a href="home.php"><img src="img/Icons/printing_logo-removebg-preview.png" class="logo" alt=""></a>
            <ul class="desktop-nav">
                <li><a href="home.php" class="nav-link">Home</a></li>
                <li><a href="products.php" class="nav-link">Products</a></li>
                <li><a href="about.php" class="nav-link">About</a></li>
                <li><a href="contact.php" class="nav-link">Contact</a></li>
            </ul>
        </div>
        <div class="right-nav">
            <form class="search-bar">
                <input type="search" placeholder="Search" name="searchbar" class="search-input hidden">
                <button type="button" class="search-btn"><i class="fa-solid fa-magnifying-glass"></i></button>
            </form>
            <ul style="display: flex; align-items: center; gap: 10px; list-style: none; margin: 0; padding: 0;">
                <li><a href="#" id="cart-icon" class="cart-icon"><i class="fa-solid fa-cart-shopping"></i></a></li>
                <?php include_once 'nav_avatar.php'; ?>
                <li><a href="profile.php" id="profile-icon" class="auth-link"><?= $NAV_AVATAR_HTML ?></a></li>
            </ul>
            <div id="navbar">
                <button id="close-menu" aria-label="Close Menu">x</button>
                <div class="menu-user">
                    <a href="profile.php" class="auth-link" id="mobile-profile-icon"><?= $NAV_AVATAR_HTML ?></a>
                </div>
                <ul class="mobile-nav">
                    <li><a href="home.php" class="nav-link">Home</a></li>
                    <li><a href="products.php" class="nav-link">Products</a></li>
                    <li><a href="about.php" class="nav-link">About</a></li>
                    <li><a href="contact.php" class="nav-link">Contact</a></li>
                </ul>
            </div>
            <button id="menu-toggle" aria-label="Toggle Menu"><i class="fas fa-outdent"></i></button>
        </div>
    </section>
    <div class="settings-container">
        <section class="account-flex">
            <div class="profile-side" style="position:relative;">
                <div class="profile-image-wrapper">
                    <div class="profile-avatar-ring">
                        <?php $avatarSafe = ($avatarPath && file_exists(__DIR__ . '/' . $avatarPath)) ? htmlspecialchars($avatarPath) : 'img/snorlax.png'; ?>
                        <img src="<?= $avatarSafe ?>" alt="Profile Image" class="profile-avatar" id="profileAvatarImg" />
                        <button type="button" class="avatar-edit-btn" id="avatarEditBtn" aria-label="Change avatar"><i class="fa fa-camera"></i></button>
                        <form id="avatarUploadForm" action="profile.php" method="post" enctype="multipart/form-data" style="display:none;">
                            <input type="hidden" name="upload_avatar" value="1" />
                            <input type="file" name="avatar_file" id="avatarFileInput" accept="image/*" />
                        </form>
                    </div>
                </div>
                <div class="profile-identity">
                    <h1><?= htmlspecialchars($username ?: 'User') ?></h1>
                    <div class="email-handle"><?= htmlspecialchars($email ?: '') ?></div>
                </div>
                <div class="profile-image-actions" id="profileActions">
                    <form action="logout.php" method="post" class="logout-bar no-padding" title="Logout">
                        <button type="submit" class="btn logout-btn" id="logoutBtn" aria-label="Logout"><i class="fa fa-right-from-bracket"></i></button>
                    </form>
                    <form action="profile.php" method="post" class="delete-bar no-padding" onsubmit="return confirm('Are you sure you want to delete your account? This action cannot be undone.');" title="Delete Account">
                        <input type="hidden" name="delete_account" value="1">
                        <button type="submit" class="btn delete-btn" id="deleteAccountBtn" aria-label="Delete Account"><i class="fa fa-trash"></i></button>
                    </form>
                    <form class="save-bar no-padding" method="post" action="profile.php" id="profileFormSave" title="Save Changes">
                        <button type="submit" form="profileForm" class="btn edit-btn" id="saveProfileBtn" aria-label="Save" disabled><i class="fa fa-save"></i></button>
                    </form>
                </div>
            </div>
            <form class="account-form" method="post" action="profile.php" id="profileForm">
                <h2>Account Details</h2>
                <div class="form-group">
                    <label for="username">Full Name</label>
                    <input type="text" id="username" name="username" value="<?= htmlspecialchars($username) ?>" readonly />
                </div>
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" readonly />
                </div>
                <div class="form-group input-edit-group">
                    <label for="address">Address</label>
                    <div class="input-edit-wrapper">
                        <button type="button" id="edit-address-btn" class="input-edit-btn" tabindex="-1">
                            <i class="fa fa-pencil-alt" aria-hidden="true"></i>
                        </button>
                        <input type="text" id="address" name="address" value="<?= htmlspecialchars($address ?? '') ?>" placeholder="Enter your address" required readonly />
                    </div>
                </div>
                <div class="form-group input-edit-group">
                    <label for="phone">Phone Number</label>
                    <div class="input-edit-wrapper">
                        <button type="button" id="edit-phone-btn" class="input-edit-btn" tabindex="-1">
                            <i class="fa fa-pencil-alt" aria-hidden="true"></i>
                        </button>
                        <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($phone ?? '') ?>" placeholder="Enter your phone number" required readonly inputmode="numeric" />
                    </div>
                </div>
            </form>
            <!-- Orders Panel -->
            <?php if ($isAuthenticated): ?>
            <section class="orders-panel" id="ordersPanel">
                <div class="orders-panel-header">
                    <h2>My Orders</h2>
                    <button type="button" class="orders-toggle" id="ordersToggleBtn" aria-expanded="true" aria-controls="ordersTableWrap">Hide</button>
                </div>
                <div class="orders-filter-bar">
                    <button type="button" class="orders-filter-btn active" data-filter="all">All</button>
                    <button type="button" class="orders-filter-btn" data-filter="pending">Pending</button>
                    <button type="button" class="orders-filter-btn" data-filter="shipped">Shipped</button>
                    <button type="button" class="orders-filter-btn" data-filter="delivered">Delivered</button>
                    <button type="button" class="orders-filter-btn" data-filter="cancelled">Cancelled</button>
                </div>
                <div class="orders-table-wrap" id="ordersTableWrap">
                <?php if (empty($userOrders)): ?>
                    <div class="orders-empty">You have no orders yet.</div>
                <?php else: ?>
                    <div class="orders-cards-grid">
                        <?php foreach ($userOrders as $o): 
                            $oid=(int)$o['order_id']; 
                            $items=$orderItemsMap[$oid] ?? []; 
                            $deliveryNorm = strtolower(preg_replace('/\s+/', '-', $o['DeliveryStatus'])); 
                            // Normalize order status to limited set
                            $rawStatus = trim($o['OrderStatus']);
                            $norm = strtolower($rawStatus);
                            $displayStatus = 'Processing';
                            if (in_array($norm,['processing','pending','in-progress','inprocess'])) { $displayStatus='Processing'; }
                            elseif (in_array($norm,['shipped','dispatched','in-transit','out-for-delivery','ready'])) { $displayStatus='Shipped'; }
                            elseif ($norm==='delivered') { $displayStatus='Delivered'; }
                            elseif (in_array($norm,['cancelled','canceled'])) { $displayStatus='Cancelled'; }
                            elseif ($norm==='completed') { $displayStatus='Completed'; }
                            $displayStatusClass = strtolower(str_replace(' ','-',$displayStatus));
                        ?>
                        <div class="order-card" data-order-id="<?= htmlspecialchars($o['order_id']) ?>" data-delivery-status="<?= htmlspecialchars($deliveryNorm) ?>">
                            <div class="order-card-header">
                                <div class="order-card-logo"><img src="img/iloveprintshoppe.jpg" alt="logo"></div>
                                <div class="order-card-meta">
                                    <div class="oc-line"><span class="oc-label">Order #</span><strong><?= htmlspecialchars($o['order_id']) ?></strong></div>
                                    <div class="oc-line"><span class="oc-label">Date</span><span><?= htmlspecialchars($o['created_col']) ?></span></div>
                                    <div class="oc-line"><span class="oc-label">Total</span><span>₱<?= htmlspecialchars(number_format((float)$o['TotalAmount'],2)) ?></span></div>
                                    <div class="oc-line"><span class="oc-label">Status</span><span class="badge status-<?= htmlspecialchars($displayStatusClass) ?>"><?= htmlspecialchars($displayStatus) ?></span></div>
                                    <?php 
                                        $deliveredFlag = (strcasecmp($o['DeliveryStatus'],'Delivered')===0);
                                        $needConfirm = $deliveredFlag && strcasecmp($displayStatus,'Completed')!==0;
                                    ?>
                                    <?php if(!$deliveredFlag): ?>
                                        <div class="oc-line"><span class="oc-label">Delivery</span><span class="badge delivery-<?= strtolower(preg_replace('/\s+/','-', $o['DeliveryStatus'])) ?>"><?= htmlspecialchars($o['DeliveryStatus']) ?></span></div>
                                    <?php elseif($needConfirm): ?>
                                        <div class="oc-line"><span class="oc-label">Delivery</span><button type="button" class="confirm-delivery-btn inline">Confirm Delivery</button></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="order-card-separator"></div>
                            <div class="order-card-items">
                                <?php if (empty($items)): ?>
                                    <div class="no-items">No line items.</div>
                                <?php else: ?>
                                    <?php foreach ($items as $li): $lineTotal=(float)($li['line_price'] ?? 0) * (int)($li['quantity'] ?? 1); ?>
                                        <div class="order-item-row">
                                            <div class="oi-name"><?= htmlspecialchars($li['product_name'] ?? ('#'.$li['product_id'])) ?></div>
                                            <div class="oi-size">Size: <?= htmlspecialchars($li['size'] ?? '—') ?></div>
                                            <div class="oi-qty">Qty: <?= htmlspecialchars($li['quantity']) ?></div>
                                            <div class="oi-line">₱<?= htmlspecialchars(number_format((float)$li['line_price'],2)) ?></div>
                                            <div class="oi-total">₱<?= htmlspecialchars(number_format($lineTotal,2)) ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <?php if(!$deliveredFlag || (!$needConfirm && $deliveredFlag)): ?>
                                <div class="order-card-footer"><span class="oc-dash">—</span></div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                </div>
            </section>
            <?php endif; ?>
            <?php if (isset($_GET['updated']) && $isAuthenticated): ?>
                <div id="profile-toast" class="profile-toast-success bottom-right">Profile updated successfully!</div>
            <?php endif; ?>
            <?php if (isset($_GET['order']) && $isAuthenticated): 
                $orderedName = isset($_GET['name']) ? htmlspecialchars(urldecode($_GET['name'])) : null;
                $orderedQty = isset($_GET['qty']) ? (int)$_GET['qty'] : null;
                $multiCount = isset($_GET['count']) ? (int)$_GET['count'] : null;
            ?>
                <div id="order-toast" class="profile-toast-success bottom-right" style="background:linear-gradient(135deg,#215c21,#357d35);">
                    <?php if ($multiCount): ?>
                        Placed <?= $multiCount ?> items successfully!
                    <?php else: ?>
                        Order placed<?= $orderedName ? ' for <strong>' . $orderedName . '</strong>' : '' ?><?= $orderedQty ? ' (x' . $orderedQty . ')' : '' ?> successfully!
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['complete_profile']) && $isAuthenticated): ?>
                <div id="complete-profile-toast" class="profile-toast-error bottom-right" style="background:linear-gradient(135deg,#6d1e1e,#8e2b2b);">Please complete your address & phone number to place an order.</div>
            <?php endif; ?>
        </section>
    </div>
    <?php include 'footer.php'; ?>
    <div id="login-container"></div>
    <script>
        window.isAuthenticated = <?= $isAuthenticated ? 'true' : 'false' ?>;
    </script>
    <script src="login.js?v=<?= time() ?>"></script>
    <script src="cart.js"></script>
    <style>
    /* Orders panel: transparent background with only a subtle top border */
    .orders-panel { margin-top:40px; background:transparent; backdrop-filter:none; -webkit-backdrop-filter:none; border-radius:0; padding:30px 0 90px; box-shadow:none; width:100%; grid-column:1 / -1; border-top:2px solid #e7d2d2; box-sizing:border-box; --orders-side-gutter:48px; overflow:visible; min-height:clamp(520px,60vh,900px); }
    @media (max-width:820px){ .orders-panel { min-height:clamp(480px,55vh,780px); } }
    @media (max-width:560px){ .orders-panel { min-height:unset; } }
    /* Side gutters live on the grid so cards never look flush with panel edges */
    .orders-panel .orders-cards-grid { padding:32px var(--orders-side-gutter) 110px; margin:0; }
    /* Add a spacer after the grid to guarantee shadow clearance */
    .orders-panel::after { content:""; display:block; height:8px; }
    @media (max-width:1100px){ .orders-panel { --orders-side-gutter:36px; } }
    @media (max-width:820px){ .orders-panel { --orders-side-gutter:28px; } }
    @media (max-width:560px){ .orders-panel { --orders-side-gutter:18px; padding:26px 0 30px; } }
        .orders-panel-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:14px; }
        .orders-panel-header h2 { font-size:1.25rem; margin:0; font-weight:600; letter-spacing:.5px; }
        .orders-toggle { background:#752525; color:#fff; border:none; padding:6px 14px; border-radius:30px; cursor:pointer; font-size:.8rem; font-weight:600; letter-spacing:.5px; transition:background .3s ease, transform .3s ease; }
        .orders-toggle:hover { background:#c90606; transform:translateY(-2px); }
        .orders-table-wrap { transition:max-height .5s ease, opacity .45s ease; overflow:hidden; }
        .orders-table-scroll { max-height:320px; overflow:auto; border-radius:12px; border:1px solid rgba(0,0,0,.08); background:rgba(255,255,255,0.55); }
        .orders-table { width:100%; border-collapse:collapse; font-size:.82rem; }
        .orders-table th, .orders-table td { padding:10px 12px; text-align:left; white-space:nowrap; }
        .orders-table thead th { position:sticky; top:0; background:#752525; color:#fff; font-weight:600; font-size:.7rem; letter-spacing:.7px; text-transform:uppercase; }
        .orders-table tbody tr:nth-child(even) { background:rgba(0,0,0,0.04); }
    .orders-table tbody tr:hover { background:rgba(201,6,6,0.08); }
    .orders-empty { padding:10px 4px; font-size:.85rem; color:#444; }
    .orders-cards-grid { display:grid; grid-template-columns: repeat(auto-fill,minmax(300px,1fr)); gap:34px 34px; box-sizing:border-box; }
    .order-card { background:linear-gradient(145deg,rgba(255,255,255,.78),rgba(255,255,255,.55)); border:1px solid rgba(255,255,255,.7); border-radius:26px; box-shadow:0 10px 24px -10px rgba(117,37,37,.28),0 4px 10px -4px rgba(0,0,0,.12); padding:20px 22px 18px; display:flex; flex-direction:column; position:relative; overflow:visible; }
    .order-card-header { display:flex; flex-direction:column; align-items:center; text-align:center; }
    .order-card-logo img { width:70px; height:70px; object-fit:cover; border-radius:16px; box-shadow:0 6px 14px -6px rgba(117,37,37,.35); margin-bottom:10px; }
    .order-card-meta { display:grid; grid-template-columns: repeat(2,auto); gap:6px 16px; font-size:.65rem; letter-spacing:.5px; }
    .order-card-meta .oc-line { display:flex; gap:6px; align-items:center; }
    .oc-label { text-transform:uppercase; font-weight:600; opacity:.7; font-size:.58rem; letter-spacing:.9px; }
    .order-card-separator { margin:14px 0 12px; border-bottom:2px solid #e7d2d2; }
    .order-card-items { display:flex; flex-direction:column; gap:10px; }
    /* Order item row: flex-wrap so content auto-fits within narrow cards */
    .order-item-row { display:flex; flex-wrap:wrap; gap:8px 18px; font-size:.62rem; align-items:center; background:rgba(255,255,255,.6); border:1px solid #eadbdb; padding:10px 14px; border-radius:14px; width:100%; box-sizing:border-box; }
    .order-item-row .oi-name { flex:1 1 140px; font-weight:600; color:#752525; line-height:1.25; min-width:140px; }
    .order-item-row .oi-size, .order-item-row .oi-qty, .order-item-row .oi-line { flex:0 0 auto; }
    .order-item-row .oi-line, .order-item-row .oi-total { text-align:right; }
    .order-item-row .oi-total { flex:0 0 auto; font-weight:600; margin-left:auto; }
    /* On very narrow screens stack meta under name nicely */
    @media (max-width:520px){
        .order-item-row { gap:6px 14px; }
        .order-item-row .oi-name { flex:1 1 100%; min-width:100%; }
        .order-item-row .oi-total { width:100%; text-align:right; margin-left:0; }
    }
    .order-item-row .oi-name { font-weight:600; color:#752525; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .order-item-row .oi-total { font-weight:600; }
    .order-card-footer { margin-top:16px; display:flex; justify-content:flex-end; }
    .order-card-footer .confirm-delivery-btn { background:#215c21; color:#fff; border:none; padding:6px 12px; border-radius:30px; font-size:.6rem; letter-spacing:.6px; cursor:pointer; font-weight:600; box-shadow:0 6px 16px -6px rgba(33,92,33,.5); }
    .order-card-footer .confirm-delivery-btn:hover { background:#2d7b2d; }
    .confirm-delivery-btn.inline { padding:4px 10px; font-size:.55rem; box-shadow:none; margin-left:4px; }
    .confirm-delivery-btn.inline:hover { background:#2d7b2d; }
    .oc-dash { font-size:.65rem; opacity:.5; }
    @media (max-width:640px){ .order-item-row { grid-template-columns: minmax(110px,1fr) repeat(4,auto); } }
    .badge { display:inline-block; padding:4px 10px; border-radius:30px; font-size:.65rem; font-weight:600; letter-spacing:.5px; text-transform:uppercase; background:#bbb; color:#fff; }
    .order-details-row td { background:rgba(255,255,255,0.75); }
    .order-summary-row.open + .order-details-row { animation: detailsIn .35s ease; }
    @keyframes detailsIn { from { opacity:0; transform:translateY(-4px);} to { opacity:1; transform:translateY(0);} }
    .order-expand-btn { transition: transform .25s ease; }
    .order-summary-row.open .order-expand-btn { transform:rotate(90deg); }
    .status-processing { background:#5c6bc0; }
    .status-shipped { background:#0277bd; }
    .status-delivered { background:#00897b; }
    .status-cancelled { background:#b71c1c; }
    .status-completed { background:#2e7d32; }
        .delivery-pending { background:#757575; }
        .delivery-dispatched { background:#1976d2; }
        .delivery-delivered { background:#2e7d32; }
        .delivery-failed { background:#b71c1c; }
    /* Filter bar for delivery statuses */
    .orders-filter-bar { display:flex; flex-wrap:wrap; gap:10px; margin:0 0 18px; }
    .orders-filter-btn { background:#f4eaea; border:1px solid #e2caca; color:#752525; padding:6px 14px; border-radius:30px; font-size:.58rem; font-weight:600; letter-spacing:.6px; cursor:pointer; transition:all .25s ease; }
    .orders-filter-btn:hover { background:#752525; color:#fff; }
    .orders-filter-btn.active { background:#752525; color:#fff; box-shadow:0 6px 16px -6px rgba(117,37,37,.4); }
    /* Force exactly 3 cards per row on large screens */
    .orders-cards-grid { grid-template-columns: repeat(3, 1fr); }
    @media (max-width:1100px){ .orders-cards-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width:640px){ .orders-cards-grid { grid-template-columns: 1fr; } }
    .visually-hidden { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0 0 0 0); border:0; }
    /* Toast styles */
    .profile-toast-success, .profile-toast-error { position:fixed; right:25px; bottom:25px; padding:14px 20px; border-radius:14px; color:#fff; font-weight:600; font-size:.85rem; letter-spacing:.4px; box-shadow:0 6px 18px -6px rgba(0,0,0,.4); opacity:0; transform:translateY(10px); animation:toastIn .55s cubic-bezier(.16,.8,.3,1) forwards; z-index:9999; }
    .profile-toast-error { background:linear-gradient(135deg,#7e2222,#a22d2d); }
    @keyframes toastIn { to { opacity:1; transform:translateY(0); } }
    @keyframes toastOut { to { opacity:0; transform:translateY(10px); } }
        @media (max-width:780px) { .orders-table th, .orders-table td { padding:8px 10px; } .orders-panel { padding:18px 18px 22px; } }
        @media (max-width:540px) { .orders-table { font-size:.7rem; } .orders-panel-header h2 { font-size:1.05rem; } }
    </style>
    <script src="profile.js?v=<?= time() ?>"></script>
    <script>
        (function(){
            const btn = document.getElementById('ordersToggleBtn');
            const wrap = document.getElementById('ordersTableWrap');
            if(btn && wrap){
                let open = true;
                wrap.style.maxHeight = wrap.scrollHeight + 'px';
                btn.addEventListener('click', ()=>{
                    open = !open;
                    btn.setAttribute('aria-expanded', open?'true':'false');
                    btn.textContent = open? 'Hide' : 'Show';
                    if(open){ wrap.style.opacity='1'; wrap.style.maxHeight = wrap.scrollHeight + 'px'; }
                    else { wrap.style.opacity='0'; wrap.style.maxHeight='0px'; }
                });
                window.addEventListener('resize', ()=>{ if(open) { wrap.style.maxHeight = wrap.scrollHeight + 'px'; } });
            }
            // Toast auto-hide
            ['profile-toast','order-toast','complete-profile-toast'].forEach(id=>{
                const el=document.getElementById(id); if(!el) return; setTimeout(()=>{ el.style.animation='toastOut .5s ease forwards'; },3500);
            });
            if (window.location.hash==='#ordersPanel') {
                const panel=document.getElementById('ordersPanel'); if(panel) panel.scrollIntoView({behavior:'smooth'});
            }
        })();
    </script>
</body>
</html>