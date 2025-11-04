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
                <li><a href="products.php" class="nav-link active">Products</a></li>
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
                    <li><a href="products.php" class="nav-link active">Products</a></li>
                    <li><a href="about.php" class="nav-link">About</a></li>
                    <li><a href="contact.php" class="nav-link">Contact</a></li>
                </ul>
            </div>
            <button id="menu-toggle" aria-label="Toggle Menu"><i class="fas fa-outdent"></i></button>
        </div>
    </section>

    <main class="sim-viewer-container">
        <div class="back-container">
            <button onclick="history.back()" class="back-btn">← Back</button>
        </div>
        <!-- Heading and save button row -->
        <div class="sim-header-row">
            <h2 class="sim-title">Customize</h2>
            <div>
                <button id="saveDesignBtn" class="save-design-btn save-float">Save Design</button>
            </div>
        </div>

        <!-- Two-column layout: left controls, right editor + 3D viewer -->
        <div class="sim-twocol">
            <!-- LEFT: controls (upload, color swatches, color picker) -->
                <aside class="sim-left">
                <div class="control-heading">Shirt Color</div>
                <div id="colorPickerContainer"></div>
                    <div class="mt-12">
                        <div class="control-label bold">Text to Apply</div>
                        <div class="sim-control-block vstack">
                            <input id="fontText" type="text" placeholder="Enter text to apply" />
                            <div class="hstack">
                                <label class="control-label">Color</label>
                                <input id="fontColor" type="color" value="#000000" />
                                <label class="control-label ml-8">Size</label>
                                <input id="fontSize" type="number" min="8" max="400" value="72" />
                            </div>
                            <div class="mt-4">
                                <div class="muted-note">Text updates in real-time on the 3D shirt as you type.</div>
                            </div>
                        </div>
                    </div>
                <div class="control-label subtle">Font family</div>
                <div class="select-wrap">
                    <select id="fontFamily">
                        <option value="Poppins">Poppins</option>
                        <option value="Arial, Helvetica, sans-serif">Arial</option>
                        <option value="Helvetica, Arial, sans-serif">Helvetica</option>
                        <option value="Georgia, serif">Georgia</option>
                        <option value="Times New Roman, Times, serif">Times New Roman</option>
                        <option value="Impact, Charcoal, sans-serif">Impact</option>
                        <option value="Courier New, monospace">Courier New</option>
                    </select>
                </div>
            </aside>

            <!-- RIGHT: editor canvas + 3D viewer (toggle) -->
            <section class="sim-right">
                <div class="section-header">
                    <div class="section-title">Design your shirt</div>
                </div>

                <div id="editor-area" class="editor-area">
                    <div id="shirt3d-wrapper">
                        <div id="viewerCanvas"></div>
                        <div class="rotate-hint" id="rotateHint">Drag left / right to rotate</div>
                    </div>
                    <div class="editor-note"></div>
                </div>
            </section>
        </div>
    </main>

    <?php
        // Use the central footer so services list and links remain consistent across pages
        include __DIR__ . '/footer.php';
    ?>
    <div id="login-container"></div>


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
