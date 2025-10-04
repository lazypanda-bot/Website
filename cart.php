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
    $isAuthenticated = isset($_SESSION['user_id']);
    ?>
<script>
    window.isAuthenticated = <?= $isAuthenticated ? 'true' : 'false' ?>;
</script>
<script src="login.js?v=<?= time() ?>"></script>

</head>
<body>
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
            <li><a href="#" class="auth-link" id="profile-icon"><i class="fa-solid fa-user"></i></a></li>
            <div id="navbar">
                <button id="close-menu" aria-label="Close Menu">x</button>
                <div class="menu-user">
                    <li><a href="#" class="auth-link" id="profile-icon"><i class="fa-solid fa-user"></i></a></li>
                </div>      
                <ul class="mobile-nav">
                    <li><a href="home.php" class="nav-link">Home</a></li>
                    <!-- <li><a href="services.php" class="nav-link">Services</a></li> -->
                    <li><a href="products.php" class="nav-link">Products</a></li>
                    <li><a href="about.php" class="nav-link">About</a></li>
                    <li><a href="contact.php" class="nav-link">Contact</a></li>
                </ul>
            </div>
            <button id="menu-toggle" aria-label="Toggle Menu"><i class="fas fa-outdent"></i></button>
        </div>
    </section>

    <div class="back-container">
        <button onclick="history.back()" class="back-btn">← Back</button>
    </div>
    <div class="cart-container">
        <h2>Your Cart</h2>
        <div class="cart-items" id="cart-items">
            <p class="empty-cart-msg">Your cart is currently empty.</p>
        </div>
        <div class="cart-summary" id="cart-summary">
            <h3>Total: ₱0.00</h3>
            <button class="checkout-btn" disabled onclick="document.getElementById('checkout-form').style.display='block'">Proceed to Checkout</button>
        </div>
        <form id="checkout-form" style="display:none; margin-top:2em;" onsubmit="return false;">
            <h3>Checkout</h3>
            <div class="form-group">
                <label>Delivery Method:</label><br>
                <input type="radio" name="delivery_method" value="pickup" required> Pick up
                <input type="radio" name="delivery_method" value="standard"> Standard Delivery
            </div>
            <div class="form-group">
                <label>Payment Method:</label><br>
                <input type="radio" name="payment_method" value="cash" required> Cash
                <input type="radio" name="payment_method" value="gcash"> GCash
            </div>
            <div class="form-group">
                <label for="delivery_address">Delivery Address:</label><br>
                <input type="text" id="delivery_address" name="delivery_address" required style="width:100%;max-width:400px;">
            </div>
            <div class="form-group">
                <h4>Order Summary</h4>
                <div id="order-summary"></div>
            </div>
            <button type="submit" onclick="alert('Order placed! (Demo only)'); localStorage.removeItem('cart'); window.location.reload();">Place Order</button>
        </form>
    </div>

    <footer id="footer">
        <div class="footer-container">
            <div class="footer-column">
                <h4>Customer Service</h4>
                <p>Available 7am to 12pm</p>
                <p>+63 917 123 4567</p>
                <p>Zamoras St., Ozamis City, Misamis Occidental</p>
            </div>
            <div class="footer-column">
                <h4>Information</h4>
                <ul>
                    <li><a href="about.php">About</a></li>
                    <li><a href="contact.php">Contact</a></li>
                </ul>
            </div>
            <div class="footer-column">
                <h4>Services</h4>
                <ul>
                    <li>Tarpaulin Printing</li>
                    <li>Apparel Printing</li>
                    <li>Personalized Items</li>
                    <li>Stickers</li>
                    <li>Signages</li>
                    <li>Tailoring</li>
                </ul>
            </div>
            <div class="footer-column">
                <h4>Follow Us</h4>
                <div class="social-icons">
                    <a href="#"><i class="fab fa-facebook"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
        </div>
    </footer>
    <div id="login-container"></div>

    <div class="chat-box" id="chatBox">
        <div class="chat-header">
            <div class="chat-logo">
                <img src="img/logo.png" alt="Chat Logo" />
            </div>
            <div class="chat-menu">put +</div>
        </div>
        <div class="chat-thread" id="chatThread">
        <!-- Real-time messages will be injected here -->
        </div>
        <div class="chat-input">
            <button class="icon-btn">✏️</button>
            <input type="text" placeholder="Type here" />
            <button class="icon-btn">📤</button>
        </div>
    </div>
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
        // Populate order summary in checkout form
        document.addEventListener('DOMContentLoaded', function() {
            const orderSummary = document.getElementById('order-summary');
            if (orderSummary) {
                const items = JSON.parse(localStorage.getItem('cart') || '[]');
                if (items.length > 0) {
                    let html = '<ul style="padding-left:1em;">';
                    let total = 0;
                    items.forEach(product => {
                        const price = parseFloat(product.price || '0');
                        const subtotal = price * product.quantity;
                        total += subtotal;
                        html += `<li>${product.name} (${product.size}) x ${product.quantity} - ₱${subtotal.toFixed(2)}</li>`;
                    });
                    html += `</ul><strong>Total: ₱${total.toFixed(2)}</strong>`;
                    orderSummary.innerHTML = html;
                } else {
                    orderSummary.innerHTML = '<em>No items in cart.</em>';
                }
            }
        });
        </script>
</body>
</html>