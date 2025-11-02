<?php
session_start();
$isAuthenticated = isset($_SESSION['user_id']);
// include nav avatar helper so $NAV_AVATAR_HTML is available like other pages
require_once __DIR__ . '/nav-avatar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Shirt Customizer</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@simonwep/pickr/dist/themes/classic.min.css" />
  <link rel="stylesheet" href="navbar-footer.css" />
  <link rel="stylesheet" href="sim.css" />
  <link rel="stylesheet" href="details.css" />
    <link rel="stylesheet" href="login.css" />
</head>
<script>window.isAuthenticated = <?= $isAuthenticated ? 'true' : 'false' ?>;</script>
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
            <li><a href="profile.php" class="auth-link" id="profile-icon"><?= $NAV_AVATAR_HTML ?></a></li>
            <div id="navbar">
                <button id="close-menu" aria-label="Close Menu">x</button>
                <div class="menu-user">
                    <li><a href="profile.php" class="auth-link" id="profile-icon"><?= $NAV_AVATAR_HTML ?></a></li>
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

    <main class="sim-viewer-container" style="padding:18px;">
        <div class="back-container">
            <button onclick="history.back()" class="back-btn">← Back</button>
        </div>
        <!-- Heading and save button row -->
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
            <h2 class="sim-title" style="margin:0;">Customize</h2>
            <div>
                <button id="saveDesignBtn" class="save-design-btn" style="position:absolute;top:24px;right:24px;z-index:2000;">Save Design</button>
            </div>
        </div>

        <!-- Two-column layout: left controls, right editor + 3D viewer -->
        <div class="sim-twocol" style="display:flex;gap:18px;align-items:flex-start;">
            <!-- LEFT: controls (upload, color swatches, color picker) -->
                <aside class="sim-left" style="width:460px;background:#fbf8ff;border-radius:12px;padding:18px;box-shadow:0 2px 6px rgba(0,0,0,0.04);">
                <!-- Upload area removed — 3D-only editor uses model assets and decals -->
                <div style="font-weight:700;margin-top:6px;margin-bottom:8px;">Shirt Color</div>
                <div id="colorPickerContainer" style="margin-bottom:6px;"></div>
                <!-- Inline text input removed (2D-only) -->
                <div style="margin-top:12px;">
                    <div style="font-size:13px;font-weight:600;margin-bottom:6px;">3D Apply Mode</div>
                    <div style="display:flex;gap:8px;align-items:center;margin-bottom:6px;">
                        <label style="display:flex;align-items:center;gap:6px;"><input type="radio" name="applyMode" value="full" id="applyModeFull" checked /> Full</label>
                        <label style="display:flex;align-items:center;gap:6px;"><input type="radio" name="applyMode" value="logo" id="applyModeLogo" /> Logo</label>
                    </div>
                    <div id="logoControls" style="display:none;align-items:center;gap:8px;">
                        <div style="font-size:12px;color:#666;margin-bottom:4px;">Logo scale</div>
                        <input id="logoScale" type="range" min="0.05" max="0.6" step="0.01" value="0.15" style="width:100%;" />
                    </div>
                    <div style="margin-top:8px;display:flex;gap:8px;">
                        <button id="applyTo3DBtn" class="editor2d-btn" style="flex:1;background:#2b7aeb;color:#fff;border-radius:6px;padding:8px 10px;border:none;">Apply to 3D</button>
                    </div>
                </div>
                <div style="margin-top:10px;font-size:13px;color:#666;">Material</div>
                <div style="background:#fff;border-radius:8px;padding:10px;border:1px solid #eee;margin-top:6px;">Cotton base</div>
            </aside>

            <!-- RIGHT: editor canvas + 3D viewer (toggle) -->
            <section class="sim-right" style="flex:1;display:flex;flex-direction:column;gap:12px;">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
                    <div style="font-weight:700;font-size:16px;">Design your shirt</div>
                </div>

                <div id="editor-area" style="background:#fff;border-radius:10px;padding:14px;border:1px solid #f0f0f0;min-height:520px;display:flex;flex-direction:column;">
                    <div id="shirt3d-wrapper" style="flex:1;position:relative;background:#ffffff;border-radius:6px;padding:8px;display:flex;align-items:center;justify-content:center;">
                        <div id="viewerCanvas" style="display:block;position:absolute;inset:0;border-radius:6px;overflow:hidden;background:#fff;"></div>
                        <div class="rotate-hint" id="rotateHint" style="display:block;">Drag left / right to rotate</div>
                    </div>
                    <div style="margin-top:10px;color:#666;font-size:13px;text-align:center;"></div>
                </div>
            </section>
        </div>
    </main>

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

    <!-- 3D-only viewer: no 2D export fields -->

    <script src="https://cdn.jsdelivr.net/npm/three@0.145.0/build/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.145.0/examples/js/controls/OrbitControls.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.145.0/examples/js/loaders/GLTFLoader.js"></script>
    <!-- DecalGeometry from three examples for logo decal support -->
    <script src="https://cdn.jsdelivr.net/npm/three@0.145.0/examples/js/geometries/DecalGeometry.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.145.0/examples/js/loaders/FontLoader.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.145.0/examples/js/geometries/TextGeometry.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@simonwep/pickr"></script>
    <script src="login.js"></script>
    <script src="sim.js"></script>
</body>
</html>
