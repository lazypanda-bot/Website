<?php
session_start();
$isAuthenticated = isset($_SESSION['user_id']);

// Get product info from query string
$productName = $_GET['name'] ?? 'Product';
$productImg = $_GET['img'] ?? 'img/snorlax.png';
$productPrice = $_GET['price'] ?? '150';

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Printing Website - <?php echo htmlspecialchars($productName); ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="navbar-footer.css" rel="stylesheet" />
  <link rel="stylesheet" href="details.css">
  <link href="login.css" rel="stylesheet" />
  <link rel="stylesheet" href="message.css">
  <link rel="stylesheet" href="sim.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css" />
</head>
<script>
  window.isAuthenticated = <?= $isAuthenticated ? 'true' : 'false' ?>;
</script>
<body>
    <section id="header">
        <div class="left-nav">
            <a href="home.php"><img src="img/Icons/printing logo.webp" class="logo" alt=""></a>
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
            <li><a href="#" id="cart-icon" class="cart-icon"><i class="fa-solid fa-cart-shopping"></i></a></li>
            <li><a href="#" class="auth-link" id="profile-icon"><i class="fa-solid fa-user"></i></a></li>
            <div id="navbar">
                <button id="close-menu" aria-label="Close Menu">x</button>
                <div class="menu-user">
                    <li><a href="#" class="auth-link" id="profile-icon"><i class="fa-solid fa-user"></i></a></li>
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

    <section class="product-listing">
        <div class="product-main">
            <div class="back-container">
                <button onclick="history.back()" class="back-btn">← Back</button>
            </div>
            <div class="image-column">
                <img src="<?php echo htmlspecialchars($productImg); ?>" alt="<?php echo htmlspecialchars($productName); ?>" class="product-image" id="mainImage" />
                <div class="thumbnail-row">
                    <div class="thumbnail-wrapper">
                        <img src="<?php echo htmlspecialchars($productImg); ?>" alt="Mug 1" class="thumbnail" onclick="changeImage(this)" />
                        <button class="delete-thumbnail-btn" type="button" onclick="deleteThumbnail(this)" title="Delete thumbnail">-</button>
                    </div>
                    <div class="thumbnail-wrapper">
                        <img src="<?php echo htmlspecialchars($productImg); ?>" alt="Mug 2" class="thumbnail" onclick="changeImage(this)" />
                        <button class="delete-thumbnail-btn" type="button" onclick="deleteThumbnail(this)" title="Delete thumbnail">-</button>
                    </div>
                    <div class="thumbnail-wrapper">
                        <img src="<?php echo htmlspecialchars($productImg); ?>" alt="Mug 3" class="thumbnail" onclick="changeImage(this)" />
                        <button class="delete-thumbnail-btn" type="button" onclick="deleteThumbnail(this)" title="Delete thumbnail">-</button>
                    </div>
                    <button type="button" id="add-thumbnail-btn" onclick="addThumbnail()">
                        <span>+</span>
                    </button>
                </div>
            </div>
        <div class="product-text">
            <h2><?php echo htmlspecialchars($productName); ?></h2>
            <div class="tab-header">
                <button class="tab-btn active" onclick="showTab('description', this)">Description</button>
                <button class="tab-btn" onclick="showTab('order', this)">Start Your Order</button>
            </div>
        <div class="tab-content" id="description">
            <p class="product-description">
                <!-- You can make this dynamic as well -->
                High quality custom printing for your needs.
            </p>
            <ul class="product-features">
                <li>Premium materials</li>
                <li>Customizable design</li>
            </ul>
            <h4>Product Details</h4>
            <ul class="product-details"></ul>
        </div>
        <section class="tab-content" id="order" style="display: none;">
            <section class="product-detail-section">
                <div class="order-step product-detail">
                    <h3>1. Product Detail</h3>
                    <p class="step-description">
                        Start your order in just a few clicks — whether you're uploading your own artwork or consulting with our team, we'll make sure every order feels personal.
                    </p>
                    <p class="step-note">Follow the steps below to place your order.</p>
                    <div class="details-container">
                        <form class="product-options-row">
                            <div class="form-group" style="grid-column: 1;">
                                <label for="product-name">Product Name</label>
                                <div class="product-static-box auto-width-box">
                                    <?php echo htmlspecialchars($productName); ?>
                                </div>
                            </div>
                            <div class="form-group" style="grid-column: 2;">
                                <label for="size">Size</label>
                                <div class="custom-dropdown" id="sizeDropdown">
                                    <button type="button" class="dropdown-toggle" onclick="toggleSizeDropdown()" style="width:170px;">12oz</button>
                                    <ul class="dropdown-menu">
                                        <li onclick="selectSize(this)">12oz</li>
                                        <li onclick="selectSize(this)">15oz</li>
                                    </ul>
                                    <input type="hidden" name="size" id="size" value="12oz" />
                                </div>
                            </div>
                            <div class="form-group" style="grid-column: 1;">
                                <label for="quantity">Quantity</label>
                                <div class="quantity-control">
                                    <button type="button" onclick="adjustQuantity(-1)">−</button>
                                    <input type="number" id="quantity" name="quantity" value="0" min="0" />
                                    <button type="button" onclick="adjustQuantity(1)">+</button>
                                </div>
                            </div>
                            <div class="form-group price-group" style="grid-column: 2;">
                                <label for="product-price">Price</label>
                                <div class="product-static-box price-box">
                                    <span class="peso-sign">₱</span>
                                    <?php echo htmlspecialchars($productPrice); ?>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </section>
            <section class="design-option-section">
                <h3>2. Design Option</h3>
                <p class="step-description">
                    Choose how you'd like to personalize your order. You can upload your own artwork or use our customization tools to create something unique.
                </p>
                <div class="design-buttons">
                    <button type="button" class="design-btn" onclick="selectDesign('upload')">
                        Upload Your Design
                    </button>
                    <button type="button" class="design-btn" id="customizeBtn" onclick="selectDesign('customize')">
                        Customize Design
                    </button>
                    <button type="button" class="design-btn" onclick="selectDesign('request')">
                        Request Design
                    </button>
                </div>
                <p class="design-note">
                    <strong>Note:</strong> A digital proof of your design will be sent to your registered account. Please review and approve it to proceed with printing.
                </p>
                <input type="hidden" name="design-option" id="design-option" value="" />
            </section>
            <div class="action-buttons">
                <button class="buy-btn">Buy Now</button>
                <button class="addcart-btn">Add to Cart</button>
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
    <div id="login-container"></div>
    <!-- Login Modal Inline Start -->
    <?php include 'login.php'; ?>
    <!-- Login Modal Inline End -->
    <div id="cart-notification" style="display:none;position:fixed;top:30px;right:30px;z-index:10000;background:#3a0d0d;color:#fff;padding:18px 32px;border-radius:8px;box-shadow:0 2px 12px rgba(0,0,0,0.15);font-size:1.1rem;transition:opacity 0.3s;opacity:0;">
        <i class="fa-solid fa-cart-plus" style="margin-right:10px;"></i>Added to cart!
    </div>
    <!-- ...existing modals and scripts... -->
    <script src="app.js"></script>
    <script src="about.js"></script>
    <script src="login.js"></script>
    <script src="message.js"></script>
    <script src="details.js"></script>
    <script src="three.min.js"></script>
    <script src="GLTFLoader.js"></script>
    <script src="OrbitControls.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@simonwep/pickr"></script>
    <script src="sim.js"></script>
    <script src="forproductbtns.js"></script>
</body>
</html>
