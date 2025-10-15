<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Printing Website</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="navbar-footer.css" rel="stylesheet" />
  <link rel="stylesheet" href="details.css">
  <link href="login.css" rel="stylesheet" />
  <link rel="stylesheet" href="message.css">
  <link rel="stylesheet" href="sim.css">
  <link rel="stylesheet" href="login.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css" />
</head>

<?php
session_start();
$isAuthenticated = isset($_SESSION['user_id']);
?>
<script>
    window.isAuthenticated = <?= $isAuthenticated ? 'true' : 'false' ?>;
</script>
<script src="login.js"></script>

<body>
    <section id="header">
        <div class="left-nav">
            <a href="home.php"><img src="img/Icons/printing logo.webp" class="logo" alt=""></a>
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
            <div id="navbar">
                <button id="close-menu" aria-label="Close Menu">x</button>
                <div class="menu-user">
                    <li><a href="profile.php" class="auth-link" id="profile-icon"><?= $NAV_AVATAR_HTML ?></a></li>
                </div>      
                <ul class="mobile-nav">
                    <li><a href="home.php" class="nav-link">Home</a></li>
                    <!-- <li><a href="services.html" class="nav-link">Services</a></li> -->
                    <li><a href="products.php" class="nav-link">Products</a></li>
                    <li><a href="about.php" class="nav-link">About</a></li>
                    <li><a href="contact.php" class="nav-link">Contact</a></li>
                </ul>
                        </div>
            <button id="menu-toggle" aria-label="Toggle Menu"><i class="fas fa-outdent"></i></button>
        </div>
    </section>

    <section class="product-listing">
        <div class="product-main">
            <div class="back-container">
                <button type="button" class="back-btn" id="backBtn">← Back</button>
            </div>
            <div class="image-column">
                <img src="img/logo.png" alt="Custom Mug" class="product-image" id="mainImage" />
                <div class="thumbnail-row">
                    <div class="thumbnail-wrapper">
                        <img src="img/logo.png" alt="Mug 1" class="thumbnail" data-action="change-image" />
                        <button class="delete-thumbnail-btn" type="button" title="Delete thumbnail">-</button>
                    </div>
                    <div class="thumbnail-wrapper">
                        <img src="img/logo.png" alt="Mug 2" class="thumbnail" data-action="change-image" />
                        <button class="delete-thumbnail-btn" type="button" title="Delete thumbnail">-</button>
                    </div>
                    <div class="thumbnail-wrapper">
                        <img src="img/logo.png" alt="Mug 3" class="thumbnail" data-action="change-image" />
                        <button class="delete-thumbnail-btn" type="button" title="Delete thumbnail">-</button>
                    </div>
                    <button type="button" id="add-thumbnail-btn"><span>+</span></button>
                </div>
            </div>
        <div class="product-text">
            <h2>Custom Mugs</h2>
            <div class="tab-header">
                <button class="tab-btn active" data-tab="description">Description</button>
                <button class="tab-btn" data-tab="order">Start Your Order</button>
            </div>
            <div class="tab-content" id="description">
                <div class="product-details"></div>
            </div>
            <section class="tab-content" id="order" hidden>
            <section class="product-detail-section">
                <div class="order-step product-detail">
                    <h3>1. Product Detail</h3>
                    <p class="step-description">
                        Start your order in just a few clicks — whether you're uploading your own artwork or consulting with our team, we'll make sure every sip feels personal. Let's get your perfect mug in motion.
                    </p>
                    <p class="step-note">Follow the steps below to place your order.</p>
                    <div class="details-container">
                        <form class="product-options-row">
                            <div class="form-group grid-col-1">
                                <label for="product-name">Product Name</label>
                                <div class="product-static-box auto-width-box">One Piece Mug</div>
                            </div>
                            <div class="form-group grid-col-2">
                                <label for="size">Size</label>
                                <div class="custom-dropdown" id="sizeDropdown">
                                    <button type="button" class="dropdown-toggle" id="sizeDropdownToggle">12oz</button>
                                    <ul class="dropdown-menu">
                                        <li class="size-option">12oz</li>
                                        <li class="size-option">15oz</li>
                                    </ul>
                                    <input type="hidden" name="size" id="size" value="12oz" />
                                </div>
                            </div>
                            <div class="form-group grid-col-1">
                                <label for="quantity">Quantity</label>
                                <div class="quantity-control">
                                    <button type="button" id="qtyMinus">−</button>
                                    <input type="number" id="quantity" name="quantity" value="1" min="1" />
                                    <button type="button" id="qtyPlus">+</button>
                                </div>
                            </div>
                            <div class="form-group price-group grid-col-2">
                                <label for="product-price">Price</label>
                                <div class="product-static-box price-box">
                                    <span class="peso-sign">₱</span>
                                    150
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </section>
            <section class="design-option-section">
                <h3>2. Design Option</h3>
                <p class="step-description">
                    Choose how you'd like to personalize your mug. You can upload your own artwork or use our customization tools to create something unique.
                </p>
                <div class="design-buttons">
                    <button type="button" class="design-btn" id="uploadDesignBtn">Upload Your Design</button>
                    <a href="sim.html" class="design-btn">Customize Design</a>
                    <button type="button" class="design-btn" id="requestDesignBtn">Request Design</button>
                </div>
                <p class="design-note">
                    <strong>Note:</strong> A digital proof of your design will be sent to your registered account. Please review and approve it to proceed with printing.
                </p>
                <input type="hidden" name="design-option" id="design-option" value="" />
            </section>
            <div class="action-buttons">
                <form action="#" method="POST" id="orderForm" class="order-form">
                    <input type="hidden" name="product_id" value="1" />
                    <input type="hidden" name="size" id="form_size" value="12oz" />
                    <input type="hidden" name="color" id="form_color" value="" />
                    <input type="hidden" name="quantity" id="form_quantity" value="1" />
                    <input type="hidden" name="isPartialPayment" value="0" />
                    <input type="hidden" name="TotalAmount" id="form_totalAmount" value="150" />
                    <input type="hidden" name="OrderStatus" value="Pending" />
                    <input type="hidden" name="DeliveryAddress" id="form_DeliveryAddress" value="" />
                    <input type="hidden" name="DeliveryStatus" value="Pending" />
                    <button type="button" class="buy-btn" id="buyNowBtn">Buy Now</button>
                </form>
                <form action="#" method="POST" id="cartForm" class="cart-form" onsubmit="return false;">
                    <input type="hidden" name="product_id" value="1" />
                    <input type="hidden" name="size" id="cart_size" value="12oz" />
                    <input type="hidden" name="color" id="cart_color" value="" />
                    <input type="hidden" name="quantity" id="cart_quantity" value="1" />
                    <button type="button" class="addcart-btn">Add to Cart</button>
                </form>
            </div>
        </section>
    </section>

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
        <div class="modal" id="auth-modal">
            <div class="auth-box" id="auth-box">
                <button id="modal-close" class="close-btn" aria-label="Close">&times;</button>

                <div class="forms-container">
                    <div class="signin-signup">

                        <form action="login.php" method="POST" class="sign-in-form">
                            <input type="hidden" name="form_type" value="login" />
                            <input type="hidden" name="redirect_after_auth" id="login-redirect-after-auth" />

                            <h2 class="title">Sign In</h2>

                            <div class="input-field" id="login-identifier-field">
                                <i class="fas fa-user" id="login-identifier-icon"></i>
                                <input type="text" name="identifier" id="login-identifier-input" placeholder="Email" required />
                            </div>

                            <div class="input-field">
                                <i class="fas fa-lock"></i>
                                <input type="password" name="password" id="login-password" placeholder="Password" required />
                            </div>

                            <div class="checkbox-field">
                                <label><input type="checkbox" id="toggle-login-password" />Show Password</label>
                            </div>

                            <input type="submit" value="Login" class="auth-btn solid" />

                            <p class="social-text">Or sign in with:</p>
                            <div class="social-media" id="login-social">
                                <a href="#" class="social-icon" id="login-use-phone"><i class="fas fa-phone"></i></a>
                                <a href="#" class="social-icon" id="login-use-facebook"><i class="fab fa-facebook-f"></i></a>
                            </div>
                        </form>

                        <form action="login.php" method="POST" class="sign-up-form">
                            <input type="hidden" name="form_type" value="register" />
                            <input type="hidden" name="redirect_after_auth" id="register-redirect-after-auth" />

                            <h2 class="title">Sign Up</h2>

                            <div class="input-field">
                                <i class="fas fa-user"></i>
                                <input type="text" name="username" placeholder="Full Name" required />
                            </div>

                            <div class="input-field" id="reg-identifier-field">
                                <i class="fas fa-envelope" id="reg-identifier-icon"></i>
                                <input type="email" name="email" id="reg-identifier-input" placeholder="Email" required />
                            </div>

                            <div class="input-field">
                                <i class="fas fa-lock"></i>
                                <input type="password" name="password" id="register-password" placeholder="Password" required />
                            </div>

                            <div class="checkbox-field">
                                <label><input type="checkbox" id="toggle-register-password" />Show Password</label>
                            </div>

                            <input type="submit" class="auth-btn" value="Register" />

                            <p class="social-text">Or sign up with:</p>
                            <div class="social-media" id="reg-social">
                                <a href="#" class="social-icon" id="reg-use-phone"><i class="fas fa-phone"></i></a>
                                <a href="#" class="social-icon" id="reg-use-facebook"><i class="fab fa-facebook-f"></i></a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="panels-container">
                    <div class="panel left-panel">
                        <div class="content">
                            <h3>New here?</h3>
                            <p>Register with your personal details to use all of site features</p>
                            <button class="auth-btn transparent" id="sign-up-btn">Sign Up</button>
                        </div>
                    </div>
                    <div class="panel right-panel">
                        <div class="content">
                            <h3>One of us?</h3>
                            <p>Sign in to access your account and enjoy our services</p>
                            <button class="auth-btn transparent" id="sign-in-btn">Sign In</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

            <div id="cart-notification" class="cart-notification">
                <i class="fa-solid fa-cart-plus"></i>Added to cart!
            </div>

    <div id="designModal" class="modal-overlay">
        <div class="modal-box">
            <h2>Describe your design</h2>
            <textarea placeholder="Enter your design idea" class="design-input"></textarea>
            <div class="modal-actions">
                <button class="cancel-btn" id="closeModalBtn">Cancel</button>
                <button class="request-btn" id="submitDesignBtn">Request</button>
            </div>
        </div>
    </div>
    <!-- Upload Modal -->
    <div id="uploadModal" class="modal-overlay">
        <div class="modal-box">
            <h2>Upload Your Design</h2>
            <div class="drop-zone" id="dropZone">
                <p>Drag & Drop files here</p>
                <span>or</span>
                <label class="browse-btn">
                    Browse Files
                    <input type="file" id="designFile" accept="image/*,.pdf" hidden />
                </label>
            </div>
            <div class="modal-actions">
                <button class="cancel-btn" id="closeUploadModalBtn">Cancel</button>
                <button class="request-btn" id="submitUploadBtn">Upload</button>
            </div>
        </div>
    </div>

    <div id="login-container"></div>

    <script src="app.js"></script>
    <script src="about.js"></script>
    <script src="login.js"></script>
    <script src="message.js"></script>
    <script src="details.js"></script>
    <script src="three.min.js"></script>
    <script src="GLTFLoader.js"></script>
    <script src="OrbitControls.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@simonwep/pickr"></script>
    <script src="forproductbtns.js"></script>

</body>
</html>