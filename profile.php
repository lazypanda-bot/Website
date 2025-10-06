<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/database.php';

$isAuthenticated = isset($_SESSION['user_id']);
$username = $email = $address = $phone = $avatarPath = '';
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
<body>
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
                <li><a href="profile.php" id="profile-icon" class="auth-link"><i class="fa-solid fa-user"></i></a></li>
            </ul>
            <div id="navbar">
                <button id="close-menu" aria-label="Close Menu">x</button>
                <div class="menu-user">
                    <a href="#" class="auth-link" id="mobile-profile-icon"><i class="fa-solid fa-user"></i></a>
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
            <?php if (isset($_GET['updated']) && $isAuthenticated): ?>
                <div id="profile-toast" class="profile-toast-success bottom-right">Profile updated successfully!</div>
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
</body>
</html>