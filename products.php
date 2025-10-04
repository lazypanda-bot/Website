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
  <link href="login.css" rel="stylesheet" />
  <link rel="stylesheet" href="message.css">
  <link rel="stylesheet" href="products.css">
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
            <li><a href="#" class="auth-link" id="profile-icon"><i class="fa-solid fa-user"></i></a></li>
            <div id="navbar">
                <button id="close-menu" aria-label="Close Menu">x</button>
                <div class="menu-user">
                    <li><a href="#" class="auth-link" id="profile-icon"><i class="fa-solid fa-user"></i></a></li>
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

    <div class="admin-wrapper">
        <nav class="sidebar">
            <div class="back-container">
                <button onclick="history.back()" class="back-btn">← Back</button>
            </div>
            <ul class="nav-links">
                <li><a href="#" data-target="tarpaulin">Tarpaulin</a></li>
                <li><a href="#" data-target="apparel">Apparel Printing</a></li>
                <li><a href="#" data-target="personalized">Personalized Printing</a></li>
                <li><a href="#" data-target="stickers">Stickers</a></li>
                <li><a href="#" data-target="signages">Signages</a></li>
                <li><a href="#" data-target="tailoring">Tailoring Services</a></li>
            </ul>
        </nav>
        <div class="main-panel">
            <section class="content-box" id="tarpaulin">
                <h3>Tarpaulin</h3>
                <div class="service-card">
                    <a href="product-details.php?name=Tarpaulin%20Printing&img=img/snorlax.png&price=100">
                        <img src="img/snorlax.png" alt="Tarpaulin Printing" class="service-img">
                    </a>
                    <h4>Tarpaulin</h4>
                </div>
            </section>
            <section class="content-box" id="apparel">
                <h3>Apparel Printing</h3>
                <div class="service-grid">
                    <div class="service-card">
                        <a href="product-details.php?name=T-Shirt%20Printing&img=img/snorlax.png&price=150">
                            <img src="img/snorlax.png" alt="T-Shirt Printing" class="service-img">
                        </a>
                        <h4>T-Shirt Printing</h4>
                    </div>
                    <div class="service-card">
                        <a href="product-details.php?name=Polo%20Shirts&img=img/snorlax.png&price=180">
                            <img src="img/snorlax.png" alt="Polo Shirts" class="service-img">
                        </a>
                        <h4>Polo Shirts</h4>
                    </div>
                    <div class="service-card">
                        <a href="product-details.php?name=Jerseys&img=img/snorlax.png&price=200">
                            <img src="img/snorlax.png" alt="Jerseys" class="service-img">
                        </a>
                        <h4>Jerseys</h4>
                    </div>
                    <div class="service-card">
                        <a href="product-details.php?name=Hoodies&img=img/snorlax.png&price=250">
                            <img src="img/snorlax.png" alt="Hoodies" class="service-img">
                        </a>
                        <h4>Hoodies</h4>
                    </div>
                    <div class="service-card">
                        <a href="product-details.php?name=Caps&img=img/snorlax.png&price=100">
                            <img src="img/snorlax.png" alt="Caps" class="service-img">
                        </a>
                        <h4>Caps</h4>
                    </div>
                </div>
            </section>
            <section class="content-box" id="personalized">
                <h3>Personalized Printing</h3>
                <div class="service-grid">
                    <div class="service-card">
                        <a href="product-details.php?name=Mug%20Printing&img=img/snorlax.png&price=120">
                            <img src="img/snorlax.png" alt="Mugs" class="service-img">
                        </a>
                        <h4>Mugs</h4>
                    </div>
                    <div class="service-card">
                        <a href="product-details.php?name=Plate%20Printing&img=img/snorlax.png&price=110">
                            <img src="img/snorlax.png" alt="Plate" class="service-img">
                        </a>
                        <h4>Plate</h4>
                    </div>
                    <div class="service-card">
                        <a href="product-details.php?name=Tumbler%20Printing&img=img/snorlax.png&price=130">
                            <img src="img/snorlax.png" alt="Tumbler" class="service-img">
                        </a>
                        <h4>Tumbler</h4>
                    </div>
                    <div class="service-card">
                        <a href="product-details.php?name=Keychain%20Printing&img=img/snorlax.png&price=50">
                            <img src="img/snorlax.png" alt="Keychain" class="service-img">
                        </a>
                        <h4>Keychain</h4>
                    </div>
                    <div class="service-card">
                        <a href="product-details.php?name=Ref%20Magnet%20Printing&img=img/snorlax.png&price=60">
                            <img src="img/snorlax.png" alt="Ref Magnet" class="service-img">
                        </a>
                        <h4>Ref Magnet</h4>
                    </div>
                    <div class="service-card">
                        <a href="product-details.php?name=Mouse%20Pad%20Printing&img=img/snorlax.png&price=70">
                            <img src="img/snorlax.png" alt="Mouse Pad" class="service-img">
                        </a>
                        <h4>Mouse Pad</h4>
                    </div>
                    <div class="service-card">
                        <a href="product-details.php?name=Plaque%20Printing&img=img/snorlax.png&price=200">
                            <img src="img/snorlax.png" alt="Plaque / Medal" class="service-img">
                        </a>
                        <h4>Plaque / Medal</h4>
                    </div>
                    <div class="service-card">
                        <a href="product-details.php?name=ID%20Lanyard%20Printing&img=img/snorlax.png&price=40">
                            <img src="img/snorlax.png" alt="ID / Lanyard" class="service-img">
                        </a>
                        <h4>ID / Lanyard</h4>
                    </div>
                </div>
            </section>
            <section class="content-box" id="stickers">
                <h3>Stickers</h3>
                <div class="service-grid">
                    <div class="service-card">
                        <a href="product-details.php?name=Vinyl%20White%20Sticker&img=img/snorlax.png&price=20">
                            <img src="img/snorlax.png" alt="Vinyl White" class="service-img">
                        </a>
                        <h4>Vinyl White</h4>
                    </div>
                    <div class="service-card">
                        <a href="product-details.php?name=Clear%20Sticker&img=img/snorlax.png&price=20">
                            <img src="img/snorlax.png" alt="Clear" class="service-img">
                        </a>
                        <h4>Clear</h4>
                    </div>
                    <div class="service-card">
                        <a href="product-details.php?name=Perforated%20Sticker&img=img/snorlax.png&price=25">
                            <img src="img/snorlax.png" alt="Perforated" class="service-img">
                        </a>
                        <h4>Perforated</h4>
                    </div>
                    <div class="service-card">
                        <a href="product-details.php?name=Frosted%20Sticker&img=img/snorlax.png&price=25">
                            <img src="img/snorlax.png" alt="Frosted" class="service-img">
                        </a>
                        <h4>Frosted</h4>
                    </div>
                    <div class="service-card">
                        <a href="product-details.php?name=3M%20Reflective%20Sticker&img=img/snorlax.png&price=30">
                            <img src="img/snorlax.png" alt="3M Reflective" class="service-img">
                        </a>
                        <h4>3M Reflective</h4>
                    </div>
                </div>
            </section>
            <section class="content-box" id="signages">
                <h3>Signages</h3>
                <div class="service-grid">
                    <div class="service-card">
                        <a href="product-details.php?name=Printable%20Panaflex&img=img/snorlax.png&price=200">
                            <img src="img/snorlax.png" alt="Printable Panaflex" class="service-img">
                        </a>
                        <h4>Printable Panaflex</h4>
                    </div>
                    <div class="service-card">
                        <a href="product-details.php?name=Panaflex%20with%20Sticker&img=img/snorlax.png&price=220">
                            <img src="img/snorlax.png" alt="Panaflex with Sticker" class="service-img">
                        </a>
                        <h4>Panaflex with Sticker</h4>
                    </div>
                    <div class="service-card">
                        <a href="product-details.php?name=Acrylic%20Build-Up&img=img/snorlax.png&price=300">
                            <img src="img/snorlax.png" alt="Acrylic Build-Up" class="service-img">
                        </a>
                        <h4>Acrylic Build-Up</h4>
                    </div>
                    <div class="service-card">
                        <a href="product-details.php?name=Stainless%20Signage&img=img/snorlax.png&price=350">
                            <img src="img/snorlax.png" alt="Stainless" class="service-img">
                        </a>
                        <h4>Stainless</h4>
                    </div>
                </div>
            </section>
            <section class="content-box" id="tailoring">
                <h3>Tailoring Services</h3>
                <div class="service-grid">            
                    <div class="service-card">
                        <a href="product-details.php?name=Repair%20Services&img=img/snorlax.png&price=80">
                            <img src="img/snorlax.png" alt="Repair Services" class="service-img">
                        </a>
                        <h4>Repair Services</h4>
                    </div>
                    <div class="service-card">
                        <a href="product-details.php?name=Fit%20Adjustment&img=img/snorlax.png&price=60">
                            <img src="img/snorlax.png" alt="Fit Adjustment" class="service-img">
                        </a>
                        <h4>Fit Adjustment</h4>
                    </div>
                    <div class="service-card">
                        <a href="product-details.php?name=Everyday%20and%20School%20Clothes&img=img/snorlax.png&price=100">
                            <img src="img/snorlax.png" alt="Everyday & School Clothes" class="service-img">
                        </a>
                        <h4>Everyday & School Clothes</h4>
                    </div>
                </div>
        </div>
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
    </footer>
    <div id="login-container"></div>

    <div class="chat-box" id="chatBox">
        <div class="chat-header">
            <div class="chat-logo">
                <img src="img/logo.png" alt="Chat Logo" />
            </div>
            <div class="chat-menu">dots</div>
        </div>
        <div class="chat-thread" id="chatThread">
        <!-- messages will be inserted -->
        </div>
        <div class="chat-input">
                                        <a href="product-details.php?name=Tote%20Bag%20Printing&img=img/tote.png&price=100">
            <input type="text" placeholder="Type here..." />
            <button class="icon-btn">button</button>
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
    <script src="products.js"></script>
</body>
</html>