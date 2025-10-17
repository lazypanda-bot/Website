<?php
// Start session and load DB for dynamic products
session_start();
require_once 'database.php';

// --- Search Handling ---
$searchTerm = isset($_GET['q']) ? trim($_GET['q']) : '';
$isSearching = ($searchTerm !== '');

// Fetch products grouped by service_type (filtered if searching)
$productsByCategory = [];
$queryError = null;
$servicesList = [];
$totalMatches = 0;
if ($conn && !$conn->connect_error) {
    if ($isSearching) {
        // Prepared statement for search to avoid injection & allow partial matching
        $sqlProducts = "SELECT product_id, product_name, price, service_type, images, created_at
                        FROM products
                        WHERE product_name LIKE ? OR service_type LIKE ?
                        ORDER BY created_at DESC";
        if ($stmt = $conn->prepare($sqlProducts)) {
            $like = '%' . $searchTerm . '%';
            $stmt->bind_param('ss', $like, $like);
            if ($stmt->execute()) {
                if ($res = $stmt->get_result()) {
                    while ($row = $res->fetch_assoc()) {
                        $cat = trim($row['service_type'] ?? '');
                        if ($cat === '') $cat = 'Uncategorized';
                        if (!isset($productsByCategory[$cat])) { $productsByCategory[$cat] = []; }
                        $productsByCategory[$cat][] = $row;
                        $totalMatches++;
                    }
                    $res->free();
                }
            } else {
                $queryError = $stmt->error;
            }
            $stmt->close();
        } else {
            $queryError = $conn->error;
        }
        // Also search services table names so users can discover services without products yet
        if ($srv = $conn->query("SHOW TABLES LIKE 'services'")) {
            if ($srv->num_rows > 0) {
                if ($stmtS = $conn->prepare("SELECT name FROM services WHERE name LIKE ? ORDER BY name ASC")) {
                    $like = '%' . $searchTerm . '%';
                    $stmtS->bind_param('s', $like);
                    if ($stmtS->execute()) {
                        if ($resS = $stmtS->get_result()) {
                            while ($srow = $resS->fetch_assoc()) {
                                $sname = trim($srow['name']);
                                if ($sname !== '') {
                                    if (!isset($productsByCategory[$sname])) {
                                        $productsByCategory[$sname] = []; // empty bucket (no products yet)
                                    }
                                    $servicesList[] = $sname;
                                }
                            }
                            $resS->free();
                        }
                    }
                    $stmtS->close();
                }
            }
            $srv->free();
        }
    } else {
        // Non-search: load all products & ensure service categories appear
        $sqlProducts = "SELECT product_id, product_name, price, service_type, images, created_at FROM products ORDER BY created_at DESC";
        $q = $conn->query($sqlProducts);
        if ($q instanceof mysqli_result) {
            while ($row = $q->fetch_assoc()) {
                $cat = trim($row['service_type'] ?? '');
                if ($cat === '') $cat = 'Uncategorized';
                if (!isset($productsByCategory[$cat])) { $productsByCategory[$cat] = []; }
                $productsByCategory[$cat][] = $row;
                $totalMatches++;
            }
            $q->free();
        } else {
            $queryError = $conn->error; // capture for debug output
        }

        // Fetch services table (if exists) so categories show even without products
        if ($srv = $conn->query("SHOW TABLES LIKE 'services'")) {
            if ($srv->num_rows > 0) {
                if ($rs = $conn->query("SELECT name FROM services ORDER BY name ASC")) {
                    while ($srow = $rs->fetch_assoc()) {
                        $sname = trim($srow['name']);
                        if ($sname !== '' && !isset($productsByCategory[$sname])) {
                            $productsByCategory[$sname] = []; // empty bucket (no products yet)
                        }
                        if ($sname !== '') { $servicesList[] = $sname; }
                    }
                    $rs->free();
                }
            }
            $srv->free();
        }
    }
} else {
    $queryError = 'DB connection failed.';
}
$isAuthenticated = isset($_SESSION['user_id']);
// Helper to derive first image path
function firstImage($imagesField) {
    if (!$imagesField) return 'img/logo.png';
    $trim = trim($imagesField);
    if ($trim === '') return 'img/logo.png';
    if (str_starts_with($trim, '[')) {
        $decoded = json_decode($trim, true);
        if (is_array($decoded) && count($decoded)>0) return $decoded[0];
    }
    if (strpos($trim, ',') !== false) {
        $parts = array_map('trim', explode(',', $trim));
        if ($parts[0] !== '') return $parts[0];
    }
    return $trim; // single path
}
?>
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
                        <form class="search-bar" id="productSearchForm">
                            <input type="search" placeholder="Search" name="searchbar" class="search-input hidden" value="<?= htmlspecialchars($searchTerm) ?>">
                            <button type="button" class="search-btn" aria-label="Search"><i class="fa-solid fa-magnifying-glass"></i></button>
                        </form>
            <li><a href="#" id="cart-icon" class="cart-icon"><i class="fa-solid fa-cart-shopping"></i></a></li>
            <?php include_once 'nav-avatar.php'; ?>
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

    <div class="admin-wrapper">
        <nav class="sidebar">
            <div class="back-container">
                <button type="button" class="back-btn" id="backBtn">← Back</button>
            </div>
            <ul class="nav-links" id="dynamicCategoryNav">
                <?php
                // Preferred known categories ordering; any others appended.
                $preferredOrder = ['Tarpaulin','Apparel Printing','Personalized Printing','Stickers','Signages','Tailoring Services','Uncategorized'];
                $allCategories = array_keys($productsByCategory);
                $ordered = [];
                foreach ($preferredOrder as $p) { if (in_array($p, $allCategories)) $ordered[] = $p; }
                foreach ($allCategories as $c) { if (!in_array($c, $ordered)) $ordered[] = $c; }
                if (empty($ordered)) {
                    echo '<li><span class="no-categories-msg">No categories</span></li>';
                } else {
                    foreach ($ordered as $cat) {
                        $anchor = strtolower(preg_replace('/\s+/', '-', $cat));
                        echo '<li><a href="#' . htmlspecialchars($anchor) . '" data-target="' . htmlspecialchars($anchor) . '">' . htmlspecialchars($cat) . '</a></li>';
                    }
                }
                ?>
            </ul>
            <?php if (isset($_GET['debug_products'])): ?>
                <div class="debug-products-box">
                    <strong>Debug Products</strong><br>
                    Categories: <?= count($productsByCategory) ?><br>
                    Rows: <?= array_sum(array_map('count',$productsByCategory)) ?><br>
                    <?= $queryError ? ('SQL Error: '.htmlspecialchars($queryError)) : 'OK' ?>
                </div>
            <?php endif; ?>
        </nav>
        <div class="main-panel">
            <?php
            // Prepare ordering of categories (existing logic reused)
            $preferredOrder = ['Tarpaulin','Apparel Printing','Personalized Printing','Stickers','Signages','Tailoring Services','Uncategorized'];
            $allCategories = array_keys($productsByCategory);
            $ordered = [];
            foreach ($preferredOrder as $p) { if (in_array($p, $allCategories)) $ordered[] = $p; }
            foreach ($allCategories as $c) { if (!in_array($c, $ordered)) $ordered[] = $c; }

            $searchTermEsc = htmlspecialchars($searchTerm);

            if ($isSearching) {
                echo '<div class="search-results-bar">';
                if ($totalMatches > 0 || !empty($productsByCategory)) {
                    $countProducts = array_sum(array_map('count', $productsByCategory));
                    echo '<span>Search results for <strong>"' . $searchTermEsc . '"</strong>: ' . $countProducts . ' product' . ($countProducts===1?'':'s') . '</span>';
                } else {
                    echo '<span>Search results for <strong>"' . $searchTermEsc . '"</strong>:</span>';
                }
                if ($queryError) {
                    echo '<span class="query-error-msg">(Query error: ' . htmlspecialchars($queryError) . ')</span>';
                }
                echo '<a href="products.php" class="clear-search-link" aria-label="Clear search">'
                    . '<i class="fa-solid fa-circle-xmark" aria-hidden="true"></i>'
                    . '</a>';
                echo '</div>';
            }

            if (empty($productsByCategory)) {
                echo '<div class="no-products-msg">' . ($isSearching ? 'No results found.' : 'No products found. Please add products in the database.') . '</div>';
            } else {
                foreach ($ordered as $cat) {
                    $sectionId = strtolower(preg_replace('/\s+/', '-', $cat));
                    echo '<section class="content-box" id="' . htmlspecialchars($sectionId) . '">';
                    echo '<h3>' . htmlspecialchars($cat) . '</h3>';
                    if (empty($productsByCategory[$cat])) {
                        echo '<div class="no-service-products-msg">No products yet for this service.</div>';
                    } else {
                        echo '<div class="service-grid">';
                        foreach ($productsByCategory[$cat] as $p) {
                            $img = htmlspecialchars(firstImage($p['images'] ?? ''));
                            $nameEsc = htmlspecialchars($p['product_name']);
                            // No highlighting while searching per request
                            $priceEsc = htmlspecialchars($p['price']);
                            $id = (int)$p['product_id'];
                            echo '<div class="service-card">';
                            echo '<a href="product-details.php?id=' . $id . '">';
                            // If an image fails to load (missing file or bad path), use the site logo as fallback
                            echo '<img src="' . $img . '" alt="' . strip_tags($nameEsc) . '" class="service-img" onerror="this.onerror=null;this.src=\'img/logo.png\';">';
                            echo '</a>';
                            echo '<h4>' . $nameEsc . '</h4>';
                            echo '<div class="service-price">₱' . $priceEsc . '</div>';
                            echo '</div>';
                        }
                        echo '</div>';
                    }
                    echo '</section>';
                }
            }
            ?>
        </div>
    </div>

    <?php include 'footer.php'; ?>
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