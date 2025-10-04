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
  <link rel="stylesheet" href="services.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css" />

<?php
    session_start();
    $isAuthenticated = isset($_SESSION['user_id']);
    ?>
<script>
    window.isAuthenticated = <?= $isAuthenticated ? 'true' : 'false' ?>;
</script>

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
            <a href="cart.php" class="cart-icon"><i class="fa-solid fa-cart-shopping"></i></a>
            <li><a href="#" class="auth-link" id="profile-icon"><i class="fa-solid fa-user"></i></a></li>
            <div id="navbar">
                <button id="close-menu" aria-label="Close Menu">x</button>
                <div class="menu-user">
                    <div class="menu-user">
                        <a href="#" class="auth-link" id="mobile-profile-icon"><i class="fa-solid fa-user"></i></a>
                    </div>
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

    <!-- <section id="reels" class="section-reels">
        <div class="reels-wrapper">
            <div class="swiper reels-carousel">
            <div class="swiper-wrapper">
                <div class="swiper-slide reel">
                    <div class="spinner"></div>
                    <video muted loop playsinline preload="auto">
                        <source src="videos/sewing.mp4" type="video/mp4" />
                    </video>
                </div>
                <div class="swiper-slide reel">
                    <video muted loop playsinline preload="auto">
                        <source src="videos/solo.mp4" type="video/mp4" />
                    </video>
                </div>
                <div class="swiper-slide reel">
                    <video muted loop playsinline preload="auto">
                        <source src="videos/mixed1.mp4" type="video/mp4" />
                    </video>
                </div>
                <div class="swiper-slide reel">
                    <video muted loop playsinline preload="auto">
                        <source src="videos/lanyard.mp4" type="video/mp4" />
                    </video>
                </div>
                <div class="swiper-slide reel">
                    <video muted loop playsinline preload="auto">
                        <source src="videos/plaque.mp4" type="video/mp4" />
                    </video>
                </div>
            </div>
        </div>
        </div>
    </section> -->

    <section id="why" class="section2">
        <div class="swiper why-carousel">
            <div class="swiper-wrapper">
                <!-- Display -->
                <div class="swiper-slide" id="display">
                    <h1>Bring your ideas to life</h1>
                    <p><span class="redtxt">ILovePrintshoppe</span> turns your ideas into reality</p>
                    <p>We're your one-stop shop for your printing needs</p>
                    <button>Order Now</button>
                </div>
                <!--Why Choose Us + Inner Carousel-->
                <div class="swiper-slide choose">
                    <h1> Why choose <span class="redtxt1">ILovePrintshoppe</span>?</h1>
                    <!-- Inner Carousel of Reasons -->
                    <section id="reasons" class="section3">
                        <div class="swiper reason-carousel">
                            <div class="swiper-wrapper">
                                <div class="swiper-slide reason intro-slide">
                                    <h3>We combine cutting edge technology with exceptional service</h3>
                                    <h3>to deliver outstanding printing solutions for all your needs</h3>
                                </div>
                                <div class="swiper-slide reason">
                                    <img src="img/graphic-design.png" alt="">
                                    <!-- <div> Icons made by <a href="https://www.freepik.com" title="Freepik"> Freepik </a> from <a href="https://www.flaticon.com/" title="Flaticon">www.flaticon.com'</a></div> -->
                                    <h3>Custom Design</h3>
                                    <p>Professional design service tailored to your wants</p>
                                </div>
                                <div class="swiper-slide reason">
                                    <img src="img/snorlax.png" alt="">
                                    <h3>Fast Turnaround</h3>
                                    <p>Quick delivery without compromising quality</p>
                                </div>
                                <div class="swiper-slide reason">
                                    <img src="img/snorlax.png" alt="">
                                    <h3>Eco-Friendly</h3>
                                    <p>Sustainable materials and printing practices</p>
                                </div>
                            </div>
                            <!-- <div class="swiper-pagination reason-pagination"></div> -->
                        </div>
                    </section>
                </div>
            </div>
            <div class="swiper-button-prev"></div>
            <div class="swiper-button-next"></div>
        </div>    
    </section>
    <section id="feature" class="section4">
        <div class="fe-prod-row">
            <div class="fe-text">
                <h1>Services</h1>
                <p>Try out our services</p>
            </div>
            <!-- <div class="glow-wrap">
                <a href="products.html"><button id="view">View All Products</button></a>
            </div> -->
        </div>
    </section>
    <section id="featured" class="section5">
        <a href="products.html#tarpaulin" class="serv-link">
            <div class="serv">
                <img src="img/snorlax.png" alt="">
                <div class="title-price">
                    <h3>Tarpaulin</h3>
                </div>
            </div>
        </a>
        <a href="products.html#apparel" class="serv-link">
            <div class="serv">
                <img src="img/snorlax.png" alt="">
                <div class="title-price">
                    <h3>Apparel Printing</h3>
                </div>
            </div>
        </a>
        <a href="products.html#personalized" class="serv-link">
            <div class="serv">
                <img src="img/snorlax.png" alt="">
                <div class="title-price">
                    <h3>Personalized Printing</h3>
                </div>
            </div>
        </a>
        <a href="products.html#stickers" class="serv-link">
            <div class="serv">
                <img src="img/snorlax.png" alt="">
                <div class="title-price">
                    <h3>Stickers</h3>
                </div>
            </div>
        </a>
        <a href="products.html#signages" class="serv-link">
            <div class="serv">
                <img src="img/snorlax.png" alt="">
                <div class="title-price">
                    <h3>Signages</h3>
                </div>
            </div>
        </a>
        <a href="products.html#tailoring" class="serv-link">
            <div class="serv">
                <img src="img/snorlax.png" alt="">
                <div class="title-price">
                    <h3>Tailoring Services</h3>
                </div>
            </div>
        </a>
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
                    <li><a href="about.html">About</a></li>
                    <li><a href="contact.html">Contact</a></li>
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
            <div class="chat-menu">•••</div>
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

    <script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>
    <script src="app.js"></script>
    <script src="login.js"></script>
    <script src="message.js"></script>
    <script src="services.js"></script>
</body>
</html>