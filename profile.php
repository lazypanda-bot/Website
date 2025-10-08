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
                        <?php $avatarSafe = ($avatarPath && file_exists(__DIR__ . '/' . $avatarPath)) ? htmlspecialchars($avatarPath) : 'img/logo.png'; ?>
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
            <section class="orders-panel<?php if (!empty($userOrders)) echo ' has-orders'; ?>" id="ordersPanel">
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
                            $deliveryNorm = strtolower(preg_replace('/\s+/', '-', $o['DeliveryStatus'] ?? '')); 
                            // Normalize order status to limited set
                            $rawStatus = trim($o['OrderStatus']);
                            $norm = strtolower($rawStatus);
                            $displayStatus = 'Processing';
                            if (in_array($norm,['processing','pending','in-progress','inprocess'])) { $displayStatus='Processing'; }
                            elseif ($norm==='ready') { $displayStatus='Ready'; }
                            elseif (in_array($norm,['shipped','dispatched','in-transit','out-for-delivery'])) { $displayStatus='Shipped'; }
                            elseif ($norm==='delivered') { $displayStatus='Delivered'; }
                            elseif (in_array($norm,['cancelled','canceled'])) { $displayStatus='Cancelled'; }
                            elseif ($norm==='completed') { $displayStatus='Completed'; }
                            $displayStatusClass = strtolower(str_replace(' ','-',$displayStatus));
                        ?>
                        <div class="order-card" data-order-id="<?= htmlspecialchars($o['order_id']) ?>" data-order-status="<?= htmlspecialchars($displayStatusClass) ?>">
                            <div class="order-card-header">
                                <div class="order-card-logo"><img src="img/logo.png" alt="Logo"></div>
                                <div class="order-card-meta">
                                    <div class="oc-line"><span class="oc-label">Order #</span><strong><?= htmlspecialchars($o['order_id']) ?></strong></div>
                                    <div class="oc-line"><span class="oc-label">Date</span><span><?= htmlspecialchars($o['created_col']) ?></span></div>
                                    <div class="oc-line"><span class="oc-label">Total</span><span>₱<?= htmlspecialchars(number_format((float)$o['TotalAmount'],2)) ?></span></div>
                                    <div class="oc-line"><span class="oc-label">Status</span><span class="badge status-<?= htmlspecialchars($displayStatusClass) ?>"><?= htmlspecialchars($displayStatus) ?></span></div>
                                    <?php 
                                        $rawDelivery = isset($o['DeliveryStatus']) ? $o['DeliveryStatus'] : $o['OrderStatus'];
                                        $deliveredFlag = (strcasecmp($rawDelivery,'Delivered')===0) || (strcasecmp($o['OrderStatus'],'Delivered')===0);
                                        $needConfirm = $deliveredFlag && strcasecmp($displayStatus,'Completed')!==0;
                                    ?>
                                    <?php if(strcasecmp($displayStatus,'Cancelled')===0): ?>
                                        <!-- Delivery suppressed for cancelled orders -->
                                    <?php elseif(!$deliveredFlag && strcasecmp($displayStatus,'Completed')!==0 && !empty($o['DeliveryStatus'])): ?>
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
            <?php if (!empty($_SESSION['flash_profile_order_success']) && $isAuthenticated): 
                $flash = $_SESSION['flash_profile_order_success']; unset($_SESSION['flash_profile_order_success']);
                $multiCount = isset($flash['count']) ? (int)$flash['count'] : null;
                $orderedName = isset($flash['name']) ? htmlspecialchars($flash['name']) : null;
                $orderedQty = isset($flash['qty']) ? (int)$flash['qty'] : null;
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

    <script src="profile.js?v=<?= time() ?>"></script>
    <script>
        (function(){
            const btn = document.getElementById('ordersToggleBtn');
            const wrap = document.getElementById('ordersTableWrap');
            const ordersPanel = document.getElementById('ordersPanel');
            const settingsContainer = document.querySelector('.settings-container');
            const COMPACT_THRESHOLD = 260; // lowered threshold to trigger compact mode sooner

            function applyCompactIfNeeded(){
                if(!ordersPanel || !settingsContainer) return;
                const innerHeight = wrap ? wrap.scrollHeight : ordersPanel.scrollHeight;
                if(innerHeight < COMPACT_THRESHOLD) settingsContainer.classList.add('compact-order-space');
                else settingsContainer.classList.remove('compact-order-space');
            }

            function setAutoHeightImmediate(){
                if(!wrap) return;
                // Remove transition interference during measurement
                wrap.style.transition = 'none';
                wrap.style.maxHeight = 'none'; // allow natural shrink
                // Force reflow then restore transition
                void wrap.offsetHeight;
                wrap.style.transition = '';
            }

            function animateOpen(){
                if(!wrap) return;
                wrap.style.opacity = '1';
                // Start from current collapsed height
                wrap.style.maxHeight = wrap.scrollHeight + 'px';
                wrap.addEventListener('transitionend', function handler(e){
                    if(e.propertyName === 'max-height'){
                        wrap.removeEventListener('transitionend', handler);
                        setAutoHeightImmediate(); // allow natural layout, prevents stale space
                        applyCompactIfNeeded();
                    }
                });
            }

            if(btn && wrap){
                let open = true;
                // Initial natural sizing (no stale stored max-height)
                setAutoHeightImmediate();
                applyCompactIfNeeded();

                btn.addEventListener('click', () => {
                    open = !open;
                    btn.setAttribute('aria-expanded', open? 'true':'false');
                    btn.textContent = open? 'Hide':'Show';
                    if(open){
                        wrap.style.maxHeight = '0px'; // collapse instantly then animate
                        void wrap.offsetHeight; // reflow
                        if (ordersPanel) ordersPanel.classList.remove('orders-collapsed');
                        animateOpen();
                    } else {
                        wrap.style.opacity='0';
                        wrap.style.maxHeight='0px';
                        if (ordersPanel) ordersPanel.classList.add('orders-collapsed');
                        applyCompactIfNeeded();
                    }
                });

                // Observe dynamic content changes (e.g., future filtering)
                if(window.ResizeObserver){
                    const ro = new ResizeObserver(()=>{ if(open){ setAutoHeightImmediate(); applyCompactIfNeeded(); } });
                    ro.observe(wrap);
                } else {
                    window.addEventListener('resize', ()=>{ if(open){ setAutoHeightImmediate(); applyCompactIfNeeded(); } });
                }
                // Fallback delayed re-measures (after fonts/image load)
                setTimeout(()=>{ if(open){ setAutoHeightImmediate(); applyCompactIfNeeded(); } }, 120);
                setTimeout(()=>{ if(open){ setAutoHeightImmediate(); applyCompactIfNeeded(); } }, 450);
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