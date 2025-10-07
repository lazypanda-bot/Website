<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Printing Website</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
  <link href="navbar-footer.css" rel="stylesheet" />
  <link href="style.css" rel="stylesheet" />
  <link href="login.css" rel="stylesheet" />
  <link rel="stylesheet" href="about.css">
  <link rel="stylesheet" href="message.css">
  <link rel="stylesheet" href="cart.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css" />




<?php
session_start();
require_once __DIR__ . '/database.php';
$isAuthenticated = isset($_SESSION['user_id']);
$userAddress = '';
$userPhone = '';
$userName = '';
if ($isAuthenticated && !$conn->connect_error) {
    $userId = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT " . ACCOUNT_ADDRESS_COL . ", " . ACCOUNT_PHONE_COL . ", " . ACCOUNT_NAME_COL . " FROM " . ACCOUNT_TABLE . " WHERE " . ACCOUNT_ID_COL . " = ?"); // adaptive mapping from database.php already applied
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($userAddress, $userPhone, $userName);
    $stmt->fetch();
    $stmt->close();
}
?>
<script>
    window.isAuthenticated = <?= $isAuthenticated ? 'true' : 'false' ?>;
    window.userAddress = <?= json_encode($userAddress) ?>;
    window.userPhone = <?= json_encode($userPhone) ?>;
    window.userName = <?= json_encode($userName) ?>;
</script>
<script src="login.js?v=<?= time() ?>"></script>

</head>
<body class="cart-page">
    <section id="header">
        <div class="left-nav">
            <a href="home.php"><img src="img/Icons/printing_logo-removebg-preview.png" class="logo" alt=""></a>
            <ul class="desktop-nav">
                <li><a href="home.php" class="nav-link">Home</a></li>
                <!-- <li><a href="services.html" class="nav-link">Services</a></li> -->
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
            <li><a href="#" id="cart-icon" class="cart-icon"><i class="fa-solid fa-cart-shopping"></i></a></li>
            <?php include_once 'nav_avatar.php'; ?>
            <li><a href="profile.php" class="auth-link" id="profile-icon"><?= $NAV_AVATAR_HTML ?></a></li>
        </div>
    </section>

    <div class="back-container">
        <button onclick="history.back()" class="back-btn">← Back</button>
    </div>
    <div class="cart-container">
        <div class="cart-progress-bar" id="cart-progress"></div>
        <h2>Your Cart</h2>
        <div class="cart-items" id="cart-items" data-source="db">
            <p class="empty-cart-msg">Loading cart...</p>
        </div>
        <div class="cart-summary" id="cart-summary">
            <h3>Total: ₱0.00</h3>
            <button class="checkout-btn" disabled onclick="document.getElementById('checkout-form').style.display='block'">Proceed to Checkout</button>
        </div>
    <form id="checkout-form" class="checkout-form" style="display:none;" onsubmit="return false;">
        <h3>Checkout</h3>
        <div id="profile-missing-info" class="profile-missing-info">
            <strong>Missing address or phone number.</strong><br>
            Please <button type="button" id="update-profile-btn">update your profile</button> to proceed with checkout.
        </div>
        <div class="checkout-columns">
            <div class="checkout-left-col">
                <div class="form-group">
                    <span>Payment Type:</span><br>
                    <input type="radio" name="isPartialPayment" id="payment_partial" value="1" required> <label for="payment_partial">Partial</label>
                    <input type="radio" name="isPartialPayment" id="payment_full" value="0"> <label for="payment_full">Full</label>
                </div>
                <div class="form-group">
                    <span>Delivery Method:</span><br>
                    <input type="radio" name="delivery_method" id="delivery_pickup" value="pickup" required> <label for="delivery_pickup">Pick up</label>
                    <input type="radio" name="delivery_method" id="delivery_standard" value="standard"> <label for="delivery_standard">Standard Delivery</label>
                </div>
                <div class="form-group">
                    <span>Payment Method:</span><br>
                    <input type="radio" name="payment_method" id="payment_cash" value="cash" required> <label for="payment_cash">Cash</label>
                    <input type="radio" name="payment_method" id="payment_gcash" value="gcash"> <label for="payment_gcash">GCash</label>
                </div>
                <div class="form-group">
                    <label for="delivery_address">Delivery Address:</label><br>
                    <input type="text" id="delivery_address" name="delivery_address" required>
                </div>
                <div class="form-group">
                    <label for="delivery_phone">Phone Number:</label><br>
                    <input type="text" id="delivery_phone" name="delivery_phone" required>
                </div>
            </div>
            <div class="checkout-right-col">
                <div class="form-group order-summary-group">
                    <h4 style="margin-top:0;">Order Summary</h4>
                    <div id="order-summary"></div>
                    <div id="shipping-fee" style="margin-top:8px;font-weight:600;"></div>
                </div>
                <button type="submit" class="place-order-btn" style="margin-top:auto;">Place Order</button>
            </div>
        </div>
    </form>
    </div>

    <?php include 'footer.php'; ?>
    <div class="floating-chat">
        <div class="chat-tooltip">Need help? Chat with us!</div>
        <div class="chat-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
            </svg>
        </div>
    </div>
    <div id="login-container"></div>

    <script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>
    <script src="app.js"></script>
    <script src="login.js"></script>
    <script src="message.js"></script>
        <script src="cart.js"></script>
        <script>
            // trigger subtle progress bar animation once DOM ready
            window.addEventListener('load', ()=> document.body.classList.add('loaded'));
        </script>
</body>
</html>