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
  <link href="style.css" rel="stylesheet" />
  <link href="about.css" rel="stylesheet" />
  <link href="login.css" rel="stylesheet" />
  <link rel="stylesheet" href="message.css">
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
<body class="about-page">
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
                    <!-- <li><a href="services.php" class="nav-link">Services</a></li> -->
                    <li><a href="products.php" class="nav-link">Products</a></li>
                    <li><a href="about.php" class="nav-link">About</a></li>
                    <li><a href="contact.php" class="nav-link">Contact</a></li>
                </ul>
            </div>
            <button id="menu-toggle" aria-label="Toggle Menu"><i class="fas fa-outdent"></i></button>
        </div>
    </section>

    <section class="about-section1">
        <div class="section1-about">
            <div class="textbox-section1">
                <h1>I Love PrintShoppe</h1>
                <p>Your local destination for custom printing in Ozamiz City, Misamis Occidental. We make it easy to turn ideas into printed merchandise.</p>
            </div>
            <div class="section1-image">
                <img src="img/logo.png" alt="Assorted Tote Bag Mockups for Print Shoppe" loading="lazy">
            </div>
        </div>
    </section>

    <section class="about-section2">
        <div class="section2-about">
            <div class="section2-image">
                <img src="img/2nd.jpg" alt="Exterior view of I Love PrintShoppe storefront" loading="lazy">
            </div>
            <div class="textbox-section2">
                <h2>Crafting quality through every print</h2>
                <p>At <span class="redtxt3"><strong>I Love PrintShoppe</strong></span>, we believe every idea deserves to be transformed, celebrated. Whether it's a T-shirt, mug, tote, or a gift, we help you turn everyday moments into lasting impression with our helpful tools, clear pricing, and locally rooted service in <strong>Ozamiz City</strong>.</p>
            </div>
        </div>
    </section>

    <section class="about-section3">
        <div class="section3-about">
            <div class="textbox-section3">
                <h2>Your vision made our process simple</h2>
                <p>From upload-ready designs to time-tested brand customization, we make printing fast, personal, and effortless. With no minimums, free delivery, and digital proofs, we make sure every piece exceeds your control. Every piece we produce comes your story, crafted with clarity and care.</p>
            </div>
            <div class="section3-image">
                <img src="img/3rd.jpeg" alt="Custom T-shirt design with character" loading="lazy">
                <img src="img/4th.jpg" alt="Printing press machinery in operation" loading="lazy">
                <img src="img/5th.jpg" alt="Fabric being prepared for printing" loading="lazy">
                <img src="img/6th.jpg" alt="Fabric being prepared for printing" loading="lazy">
            </div>
        </div>
    </section>

    <section class="gallery-section">
        <h3>Every Print Has a Purpose. Every Idea Deserves a Voice.</h3>
        <div class="scrolling-carousel carousel-top">
            <div class="carousel-track">
                <img src="img/snorlax.png" alt="Custom printed t-shirts and hoodies" loading="lazy">
                <img src="img/snorlax.png" alt="Collection of custom printed mugs and shirts" loading="lazy">
                <img src="img/snorlax.png" alt="Close-up of printed t-shirt designs" loading="lazy">
                <img src="img/snorlax.png" alt="Various custom printed tote bags and accessories" loading="lazy">
                <img src="img/snorlax.png" alt="Custom printed caps and headwear" loading="lazy">
                <img src="img/snorlax.png" alt="More custom printed apparel" loading="lazy">
                <img src="img/snorlax.png" alt="Custom printed t-shirts and hoodies (duplicate)" loading="lazy">
                <img src="img/snorlax.png" alt="Collection of custom printed mugs and shirts (duplicate)" loading="lazy">
                <img src="img/snorlax.png" alt="Close-up of printed t-shirt designs (duplicate)" loading="lazy">
                <img src="img/snorlax.png" alt="Various custom printed tote bags and accessories (duplicate)" loading="lazy">
                <img src="img/snorlax.png" alt="Custom printed caps and headwear (duplicate)" loading="lazy">
                <img src="img/snorlax.png" alt="More custom printed apparel (duplicate)" loading="lazy">
            </div>
        </div>
        <div class="scrolling-carousel carousel-bottom">
            <div class="carousel-track">
                <img src="img/snorlax.png" alt="Custom printed pet apparel" loading="lazy">
                <img src="img/snorlax.png" alt="Happy birthday custom mug" loading="lazy">
                <img src="img/snorlax.png" alt="Custom designed canvas tote bag" loading="lazy">
                <img src="img/snorlax.png" alt="Custom phone cases and accessories" loading="lazy">
                <img src="img/snorlax.png" alt="Personalized clothing item with initial" loading="lazy">
                <img src="img/snorlax.png" alt="Custom printed face masks or fabric" loading="lazy">
                <img src="img/snorlax.png" alt="Custom printed pet apparel (duplicate)" loading="lazy">
                <img src="img/snorlax.png" alt="Happy birthday custom mug (duplicate)" loading="lazy">
                <img src="img/snorlax.png" alt="Custom designed canvas tote bag (duplicate)" loading="lazy">
                <img src="img/snorlax.png" alt="Custom phone cases and accessories (duplicate)" loading="lazy">
                <img src="img/snorlax.png" alt="Personalized clothing item with initial (duplicate)" loading="lazy">
                <img src="img/snorlax.png" alt="Custom printed face masks or fabric (duplicate)" loading="lazy">
            </div>
        </div>
    </section>

    <?php include 'footer.php'; ?>
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
            <input type="text" placeholder="Type here..." />
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
    <script src="about.js"></script>
    <script src="login.js"></script>
    <script src="message.js"></script>
</body>
</html>