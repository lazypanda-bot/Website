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
    require_once 'database.php';
    $isAuthenticated = isset($_SESSION['user_id']);
    include_once 'nav_avatar.php';

    // Fetch services with a representative product image if available
    $servicesHome = [];
    if ($conn && !$conn->connect_error) {
        // Ensure services table exists before querying (non-fatal if missing)
        if ($chk = $conn->query("SHOW TABLES LIKE 'services'")) {
            if ($chk->num_rows > 0) {
                $sql = "SELECT s.name, s.image AS service_image, (SELECT images FROM products p WHERE p.service_type = s.name ORDER BY p.created_at DESC LIMIT 1) AS sample_images FROM services s ORDER BY s.name ASC";
                if ($res = $conn->query($sql)) {
                    while ($row = $res->fetch_assoc()) { $servicesHome[] = $row; }
                    $res->free();
                }
            }
            $chk->free();
        }
    }

    function firstImageHome($imagesField) {
    if (!$imagesField) return 'img/logo.png';
    $trim = trim($imagesField);
    if ($trim === '') return 'img/logo.png';
        if (str_starts_with($trim, '[')) {
            $decoded = json_decode($trim, true);
            if (is_array($decoded) && count($decoded) > 0 && $decoded[0] !== '') return $decoded[0];
        }
        if (strpos($trim, ',') !== false) {
            $parts = array_map('trim', explode(',', $trim));
            if (!empty($parts[0])) return $parts[0];
        }
        return $trim;
    }
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
                <li><a href="home.php" class="nav-link active">Home</a></li>
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
            <li><a href="profile.php" class="auth-link" id="profile-icon"><?= $NAV_AVATAR_HTML ?></a></li>
            <div id="navbar">
                <button id="close-menu" aria-label="Close Menu">x</button>
                <div class="menu-user">
                    <div class="menu-user">
                        <a href="profile.php" class="auth-link" id="mobile-profile-icon"><?= $NAV_AVATAR_HTML ?></a>
                    </div>
                </div>      
                <ul class="mobile-nav">
                    <li><a href="home.php" class="nav-link active">Home</a></li>
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
                    <button id="order-now-btn">Order Now</button>
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
                                    <img src="img/logo.png" alt="">
                                    <h3>Fast Turnaround</h3>
                                    <p>Quick delivery without compromising quality</p>
                                </div>
                                <div class="swiper-slide reason">
                                    <img src="img/logo.png" alt="">
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
        <?php if (empty($servicesHome)): ?>
            <div style="padding:10px 15px;font-size:.9rem;color:#555;font-style:italic;">No services added yet.</div>
        <?php else: ?>
            <?php foreach ($servicesHome as $srv): 
                $name = $srv['name'];
                $slug = strtolower(preg_replace('/\s+/', '-', $name));
                // Prefer the explicit service image (admin-uploaded). If it's not present or file missing, fall back to product sample images.
                $svcImg = isset($srv['service_image']) ? trim($srv['service_image']) : '';
                $img = '';
                if ($svcImg !== '') {
                    // If stored path is relative, check file exists on server
                    $serverPath = __DIR__ . '/' . $svcImg;
                    if (is_file($serverPath)) {
                        $img = htmlspecialchars($svcImg);
                    } else {
                        // if file not present, fall back
                        $img = htmlspecialchars(firstImageHome($srv['sample_images'] ?? ''));
                    }
                } else {
                    $img = htmlspecialchars(firstImageHome($srv['sample_images'] ?? ''));
                }
            ?>
            <a href="products.php#<?= htmlspecialchars($slug) ?>" class="serv-link">
                <div class="serv">
                    <img src="<?= $img ?>" alt="<?= htmlspecialchars($name) ?>" loading="lazy" onerror="this.onerror=null;this.src='img/logo.png'">
                    <div class="serv-body">
                        <h3><?= htmlspecialchars($name) ?></h3>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        <?php endif; ?>
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
                    <?php if (!empty($servicesHome)):
                        foreach ($servicesHome as $srv):
                            $name = $srv['name'];
                            $slug = strtolower(preg_replace('/\s+/', '-', $name)); ?>
                            <li><a href="products.php#<?= htmlspecialchars($slug) ?>"><?= htmlspecialchars($name) ?></a></li>
                        <?php endforeach; else: ?>
                            <li style="font-style:italic;color:#666;">No services yet</li>
                    <?php endif; ?>
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