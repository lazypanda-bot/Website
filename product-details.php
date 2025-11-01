<?php
session_start();
// Ensure DB connection is available
require_once 'database.php';
// Use centralized auth helper to clear stale sessions and validate user
require_once __DIR__ . '/includes/auth.php';
$isAuthenticated = session_user_id_or_zero() > 0;
// Compute base path (handles when app is served from a subdirectory, e.g. /Website)
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
if ($basePath === '/') $basePath = '';

// Adaptive detection of product columns (id, name, price, images) once
$productIdCol = 'product_id';
$productNameCol = null;
$productPriceCol = null;
$productImagesCol = null;

$productsTableExists = false;
if ($conn && !$conn->connect_error) {
    if ($colsRes = $conn->query("SHOW COLUMNS FROM products")) {
        $productsTableExists = true;
        $available = [];
        while ($cRow = $colsRes->fetch_assoc()) { $available[strtolower($cRow['Field'])] = $cRow['Field']; }
        $colsRes->free();
        foreach(['product_id','id','prod_id','products_id'] as $c){ if(isset($available[$c])) { $productIdCol = $available[$c]; break; } }
        foreach(['product_name','name','title','producttitle','product'] as $c){ if(isset($available[$c])) { $productNameCol = $available[$c]; break; } }
        foreach(['price','product_price','amount','cost'] as $c){ if(isset($available[$c])) { $productPriceCol = $available[$c]; break; } }
        foreach(['images','image','img','picture'] as $c){ if(isset($available[$c])) { $productImagesCol = $available[$c]; break; } }
    }
}

$productId = null; // will resolve below
$productRow = null;
$debugMessages = [];
// Ensure rawImages is always defined to avoid undefined variable warnings
$rawImages = '';

// Prefer explicit id param (?id=) for reliability; fallback to name when only name is provided
if (isset($_GET['id']) && ctype_digit($_GET['id'])) {
    $productId = (int)$_GET['id'];
    $debugMessages[] = "Got id param=" . $productId;
    if ($productsTableExists) {
        if ($stmt = $conn->prepare("SELECT * FROM products WHERE $productIdCol = ? LIMIT 1")) {
            $stmt->bind_param('i', $productId);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                $productRow = $res->fetch_assoc();
            }
            $stmt->close();
        }
    }
} else {
    // Fallback: attempt lookup by name if provided
    $nameParam = isset($_GET['name']) ? trim($_GET['name']) : '';
    if ($nameParam !== '' && $productsTableExists && $productNameCol) {
        if ($stmt = $conn->prepare("SELECT * FROM products WHERE $productNameCol = ? LIMIT 1")) {
            $stmt->bind_param('s', $nameParam);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                if ($productRow = $res->fetch_assoc()) {
                    $productId = (int)$productRow[$productIdCol];
                }
            }
            $stmt->close();
        }
        $debugMessages[] = $productRow ? 'Resolved by name param' : 'Name param lookup failed';
    }
}

// If product still null, do NOT default to 1 silently; mark not found
$productNotFound = ($productId === null || !$productRow);

// Derive display variables – trust DB row over query params
if (!$productNotFound) {
    $productName = $productRow[$productNameCol] ?? ($_GET['name'] ?? 'Product');
    $productPrice = $productPriceCol && isset($productRow[$productPriceCol]) ? $productRow[$productPriceCol] : ($_GET['price'] ?? '0');
    $rawImages = ($productImagesCol && isset($productRow[$productImagesCol])) ? $productRow[$productImagesCol] : '';
    // Use helper to pick the first image
    $productImg = pd_first_image($rawImages);
    // Normalize $productImg to include $basePath if needed (handles subdirectory installs)
    if ($productImg) {
        $t = trim($productImg);
        if (str_starts_with($t, 'uploads/')) {
            $productImg = $basePath . '/' . $t;
        } elseif (preg_match('#^(https?:)?//#i', $t)) {
            $productImg = $t; // absolute or protocol-relative
        } elseif (str_starts_with($t, '/')) {
            $productImg = $basePath . $t;
        } else {
            $productImg = $basePath . '/' . $t;
        }
    }
    // Ensure a fallback logo if productImg ended up empty
    if (!$productImg) $productImg = $basePath . '/img/logo.png';
} else {
    $productName = 'Product Not Found';
    $productPrice = '0.00';
    $productImg = $basePath . '/img/logo.png';
}

// Optional debug view (?debug_products=1)
if (isset($_GET['debug_products'])) {
    header('Content-Type: text/plain');
    echo "productsTableExists=" . ($productsTableExists ? 'yes' : 'no') . "\n";
    echo "productIdCol=$productIdCol\n";
    echo "productNameCol=" . ($productNameCol ?? 'n/a') . "\n";
    echo "productPriceCol=" . ($productPriceCol ?? 'n/a') . "\n";
    echo "productImagesCol=" . ($productImagesCol ?? 'n/a') . "\n";
    echo "Resolved productId=" . ($productId ?? 'null') . "\n";
    echo "NotFound=" . ($productNotFound ? '1' : '0') . "\n";
    foreach($debugMessages as $m){ echo "DBG: $m\n"; }
    if ($productRow) { echo "Row JSON=" . json_encode($productRow, JSON_PRETTY_PRINT) . "\n"; }
    exit;
}

// Fetch related products (same service_type) if available
$relatedProducts = [];
if (!$productNotFound && isset($productRow['service_type']) && $productRow['service_type'] !== '') {
    if ($stmtRel = $conn->prepare("SELECT product_id, product_name, price, images FROM products WHERE service_type = ? AND product_id <> ? ORDER BY created_at DESC LIMIT 8")) {
        $svc = $productRow['service_type'];
        $pid = $productId;
        $stmtRel->bind_param('si', $svc, $pid);
        if ($stmtRel->execute()) {
            $resRel = $stmtRel->get_result();
            while ($r = $resRel->fetch_assoc()) { $relatedProducts[] = $r; }
        }
        $stmtRel->close();
    }
}

function pd_first_image($imagesField) {
    if (!$imagesField) return 'img/logo.png';
    $trim = trim($imagesField);
    if ($trim === '') return 'img/logo.png';
    $candidate = null;
    if (str_starts_with($trim, '[')) {
        $decoded = json_decode($trim, true);
        if (is_array($decoded) && count($decoded) > 0) $candidate = $decoded[0];
    } elseif (strpos($trim, ',') !== false) {
        $parts = array_map('trim', explode(',', $trim));
        if ($parts[0] !== '') $candidate = $parts[0];
    } else {
        $candidate = $trim;
    }
    if (!$candidate) return 'img/logo.png';
    $candidate = trim($candidate);
    // If candidate is an uploads path without leading slash, make it root-relative so the browser resolves correctly
    if (str_starts_with($candidate, 'uploads/')) return '/' . $candidate;
    // If it's already root-relative or an absolute URL, return as-is
    return $candidate;
}
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
  <link rel="stylesheet" href="login.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css" />
</head>
<script>
  window.isAuthenticated = <?= $isAuthenticated ? 'true' : 'false' ?>;
</script>
<body>
<?php if(!empty($_SESSION['flash_order_success'])): unset($_SESSION['flash_order_success']); ?>
    <div class="flash-message success flash-order-success">
        <span>Order placed successfully.</span>
        <button type="button" aria-label="Dismiss" class="flash-dismiss-btn">✕</button>
    </div>
    <script> // auto dismiss after 4s
        setTimeout(()=>{ const fm=document.querySelector('.flash-message.success'); if(fm) fm.remove(); },4000);
    </script>
<?php endif; ?>
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
            <?php include_once 'nav-avatar.php'; ?>
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

    <section class="product-listing">
        <div class="product-main">
            <div class="back-container">
                <button class="back-btn" id="backBtn">← Back</button>
            </div>
            <div class="image-column">
                <img src="<?php echo htmlspecialchars($productImg); ?>" alt="<?php echo htmlspecialchars($productName); ?>" class="product-image" id="mainImage" />
                <div class="thumbnail-row">
<?php
    // Helper: parse images field into normalized array (root-relative for uploads/)
    function pd_images_array($imagesField) {
        $out = [];
        if (!$imagesField) return $out;
        $trim = trim($imagesField);
        if ($trim === '') return $out;
        if (str_starts_with($trim, '[')) {
            $decoded = json_decode($trim, true);
            if (is_array($decoded)) $out = $decoded;
        } elseif (strpos($trim, ',') !== false) {
            $parts = array_map('trim', explode(',', $trim));
            $out = array_filter($parts, function($v){ return $v !== ''; });
        } else {
            $out = [$trim];
        }
        // Normalize each path: trim only; final URL normalization happens below (so we can prefix base path)
        $out = array_values(array_map(function($c){ return trim($c); }, $out));
        // Remove known fallback/logo references which are not real uploaded images
        $fallbacks = ['img/logo.png','/img/logo.png','../img/logo.png','logo.png'];
        $out = array_values(array_filter($out, function($v) use ($fallbacks){ return $v !== '' && !in_array($v, $fallbacks); }));
        // Remove duplicates while preserving order
        $out = array_values(array_unique($out));
        return $out;
    }

    $imagesList = pd_images_array($rawImages);
    // Normalize thumbnail URLs (prefix basePath when needed), remove duplicates,
    // and exclude any image that is identical to the chosen main image so the
    // thumbnail row only contains additional images added by the admin.
    // Robust normalization: convert to predictable relative/absolute forms and compare by filename
    $normalize = function($u) use ($basePath) {
        $trim = trim((string)$u);
        if ($trim === '') return '';
        $trim = str_replace('\\', '/', $trim);
        // strip protocol+host if present
        $trim = preg_replace('#^https?://[^/]+/#i', '', $trim);
        // remove leading ../ or ./ or leading slash for canonicalization
        $trim = preg_replace('#^(\.{1,2}/)+#', '', $trim);
        $trim = ltrim($trim, '/');
        // if it's an uploads path, ensure basePath prefix
        if (str_starts_with($trim, 'uploads/')) return $basePath . '/' . $trim;
        // if it's already absolute (starts with http or //) return as-is
        if (preg_match('#^(https?:)?//#i', $u)) return $u;
        // for img/ paths or others, prefix basePath so browser resolves consistently
        return $basePath . '/' . $trim;
    };
    $normalized = array_values(array_filter(array_map($normalize, $imagesList), function($v){ return $v !== '' && $v !== null; }));
    // Deduplicate by filename (basename) to avoid rendering the same file twice under different path forms
    $seen = [];
    $unique = [];
    foreach ($normalized as $n) {
        $bn = strtolower(basename(parse_url($n, PHP_URL_PATH) ?: $n));
        if ($bn === '') continue;
        if (isset($seen[$bn])) continue;
        $seen[$bn] = true;
        $unique[] = $n;
    }
    // normalize the main image basename for comparison
    $mainBasename = '';
    if (!empty($productImg)) {
        $mp = trim((string)$productImg);
        $mp = str_replace('\\','/',$mp);
        $mp = preg_replace('#^https?://[^/]+/#i', '', $mp);
        $mp = preg_replace('#^(\.{1,2}/)+#', '', $mp);
        $mp = ltrim($mp, '/');
        $mainBasename = strtolower(basename($mp));
    }
    // Exclude any thumbnail whose basename matches the main image's basename
    $thumbs = [];
    foreach ($unique as $u) {
        $bn = strtolower(basename(parse_url($u, PHP_URL_PATH) ?: $u));
        if ($mainBasename !== '' && $bn === $mainBasename) continue;
        $thumbs[] = $u;
    }
    $hasThumbnails = count($thumbs) > 0;
    if ($hasThumbnails) {
        foreach ($thumbs as $idx => $imgSrc) {
            $safeSrc = htmlspecialchars($imgSrc);
            echo "<div class=\"thumbnail-wrapper\">";
            echo "<img src=\"$safeSrc\" alt=\"Thumbnail {$idx}\" class=\"thumbnail\" data-action=\"change-image\" />";
            echo "</div>";
        }
    }
    // Temporary debug: show image src and server file existence when ?debug_images=1 is present
    if (isset($_GET['debug_images'])) {
        echo '<div class="image-debug" style="margin-top:18px;padding:12px;background:#fff6f6;border:1px solid #f2dede;border-radius:8px;color:#5a1d1d;">';
        echo '<strong>Image debug</strong><ul style="margin:8px 0;padding-left:18px;">';
        // show rawImages and whether thumbnails exist
        echo '<li>rawImages: ' . htmlspecialchars($rawImages) . '</li>';
        echo '<li>hasThumbnails: ' . ($hasThumbnails ? 'yes' : 'no') . '</li>';
        foreach ($imagesList as $ii => $isrc) {
            $url = $isrc;
            // Resolve a filesystem path for checking existence
            $rel = ltrim($url, '/');
            $fs = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/' . $rel;
            $exists = file_exists($fs) ? 'exists' : 'missing';
            echo '<li style="margin-bottom:6px;">src: ' . htmlspecialchars($url) . ' — file: ' . htmlspecialchars($fs) . ' — <strong>' . $exists . '</strong></li>';
        }
        echo '</ul></div>';
    }
?>
                </div>
            </div>
        <div class="product-text">
            <h2><?php echo htmlspecialchars($productName); ?></h2>
            <div class="tab-header">
                <button class="tab-btn active" data-tab="description">Description</button>
                <button class="tab-btn" data-tab="order">Start Your Order</button>
            </div>
        <div class="tab-content" id="description">
            <div class="product-details">
                <p class="product-description"><?php echo nl2br(htmlspecialchars($productRow['product_details'] ?? '')); ?></p>
            </div>
            <div class="review-container">
                <h4 class="review-title">Reviews</h4>
                <div class="no-reviews">No reviews yet</div>
            </div>
            <?php if (!$productNotFound && count($relatedProducts) > 0): ?>
            <div class="related-products">
                <h4>More in this Service</h4>
                <div class="related-grid">
                <?php foreach($relatedProducts as $rp): 
                    $rImg = htmlspecialchars(pd_first_image($rp['images'] ?? ''));
                    $rName = htmlspecialchars($rp['product_name']);
                    $rPrice = htmlspecialchars(number_format($rp['price'],2));
                    $rId = (int)$rp['product_id'];
                ?>
                  <a class="related-card" href="product-details.php?id=<?=$rId?>" title="<?=$rName?>">
                    <div class="rel-img-wrap"><img src="<?=$rImg?>" alt="<?=$rName?>"></div>
                    <div class="rel-info">
                        <span class="rel-name"><?=$rName?></span>
                        <span class="rel-price">₱<?=$rPrice?></span>
                    </div>
                  </a>
                <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    <div class="tab-content" id="order" hidden>
            <section class="product-detail-section">
                <div class="order-step product-detail">
                    <h3>1. Product Detail</h3>
                    <p class="step-description"><?php echo nl2br(htmlspecialchars($productRow['product_details'] ?? 'Start your order in just a few clicks — whether you\'re uploading your own artwork or consulting with our team, we\'ll make sure every order feels personal.')); ?></p>
                    <p class="step-note">Follow the steps below to place your order.</p>
                    <div class="details-container">
                        <form class="product-options-row">
                            <div class="form-group grid-col-1">
                                <label for="product-name">Product Name</label>
                                <div class="product-static-box auto-width-box">
                                    <?php echo htmlspecialchars($productName); ?>
                                </div>
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
                    <button type="button" class="design-btn" id="uploadDesignBtn">
                        Upload Your Design
                    </button>
                    <?php 
                        // preserve previous href but prefer server-side sim with product_id
                        $simHref = 'sim.php';
                        if (!empty($productId)) $simHref .= '?product_id=' . intval($productId);
                    ?>
                    <a href="<?= htmlspecialchars($simHref) ?>" class="design-btn">Customize Design</a>
                    <button type="button" class="design-btn" id="requestDesignBtn">
                        Request Design
                    </button>
                </div>
                <p class="design-note">
                    <strong>Note:</strong> A digital proof of your design will be sent to your registered account. Please review and approve it to proceed with printing.
                </p>
                <input type="hidden" name="design-option" id="design-option" value="" />
                <?php
                    // If page was opened with a saved designoption id, render a small preview area
                    $queriedDesignOption = isset($_GET['designoption_id']) ? (int)$_GET['designoption_id'] : null;
                    if ($queriedDesignOption && isset($conn) && !$conn->connect_error) {
                        $dsql = 'SELECT do.designoption_id, do.designfilepath, do.request_design, cu.color, cu.note FROM designoption do LEFT JOIN customization cu ON cu.customization_id = do.customization_id WHERE do.designoption_id = ? LIMIT 1';
                        if ($dstmt = $conn->prepare($dsql)) {
                            $dstmt->bind_param('i', $queriedDesignOption);
                            if ($dstmt->execute()) {
                                $dres = $dstmt->get_result();
                                if ($drow = $dres->fetch_assoc()) {
                                    $thumb = $drow['designfilepath'] ? htmlspecialchars($drow['designfilepath']) : null;
                                    $dc_raw = $drow['color'] ?? '';
                                    $dc = $dc_raw ? htmlspecialchars($dc_raw) : '';
                                    $rawNote = $drow['note'] ?? $drow['request_design'] ?? '';
                                    // If the note looks like internal 3D metadata (JSON with camera/rotation), don't print raw JSON — show a friendly label instead
                                    $noteDisplay = '';
                                    $is3Dmeta = false;
                                    if ($rawNote !== '') {
                                        $trim = trim($rawNote);
                                        if ((str_starts_with($trim, '{') || str_starts_with($trim, '['))) {
                                            $dec = json_decode($trim, true);
                                            if (is_array($dec) && (isset($dec['camera']) || isset($dec['rotation']))) {
                                                $is3Dmeta = true;
                                            }
                                        }
                                        $noteDisplay = $is3Dmeta ? 'Custom 3D design saved' : htmlspecialchars($rawNote);
                                    } else {
                                        $noteDisplay = 'No description';
                                    }

                                    echo "<div class=\"saved-design-preview\">";
                                    if ($thumb) {
                                        $src = $thumb;
                                        echo "<div class=\"saved-design-thumb\"><img src=\"$src\" alt=\"Saved design\"/></div>";
                                    } else {
                                        $sw = $dc ? $dc : '#efeef0';
                                        echo "<div class=\"saved-design-thumb swatch\" style=\"background:$sw\"></div>";
                                    }
                                    echo "<div class=\"saved-design-meta\"><strong>Saved design</strong><div class=\"sd-note\">" . $noteDisplay . "</div></div></div>";
                                }
                                $dres->free();
                            }
                            $dstmt->close();
                        }
                    }
                ?>
            </section>
            <div class="action-buttons">
                <?php if ($productNotFound): ?>
                    <div class="product-warning" style="color:#b30000; font-weight:600; padding:10px 0;">
                        This product could not be found. It may have been removed or the link is invalid.
                    </div>
                <?php endif; ?>
                <form action="place-order.php" method="POST" id="orderForm" class="order-form">
                    <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($productId ?? ''); ?>" />
                    <input type="hidden" name="size" id="form_size" value="12oz" />
                    <input type="hidden" name="color" id="form_color" value="" />
                    <input type="hidden" name="quantity" id="form_quantity" value="1" />
                    
                    <input type="hidden" name="TotalAmount" id="form_totalAmount" value="<?php echo htmlspecialchars($productPrice); ?>" />
                    <input type="hidden" name="OrderStatus" value="Pending" />
                    <input type="hidden" name="DeliveryAddress" id="form_DeliveryAddress" value="" />
                    <input type="hidden" name="DeliveryStatus" value="Pending" />
                    <button type="button" class="buy-btn" id="buyNowBtn" data-price="<?php echo htmlspecialchars($productPrice); ?>" <?php echo $productNotFound ? 'disabled style="opacity:.5;cursor:not-allowed;"' : ''; ?>>Buy Now</button>
                </form>
                <form action="add-to-cart.php" method="POST" id="cartForm" class="cart-form" onsubmit="return false;">
                    <form action="add-to-cart.php" method="POST" id="cartForm" class="cart-form">
                    <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($productId ?? ''); ?>" />
                    <input type="hidden" name="size" id="cart_size" value="12oz" />
                    <input type="hidden" name="color" id="cart_color" value="" />
                    <input type="hidden" name="quantity" id="cart_quantity" value="1" />
                    <button type="button" class="addcart-btn" <?php echo $productNotFound ? 'disabled style="opacity:.5;cursor:not-allowed;"' : ''; ?>>Add to Cart</button>
                </form>
                                <script>
                                // Inline fallback: attach only if the external buy-now handler never registered.
                                (function(){
                                    try{
                                        var buy = document.querySelector('.buy-btn');
                                        if (!buy) return;
                                        // Give external scripts a short window to register their handler.
                                        setTimeout(function(){
                                            if (window.__buyNowHandled) return; // external script active
                                            buy.addEventListener('click', function(e){
                                                e.preventDefault();
                                                try{
                                                    var nameEl = document.getElementById('product-name');
                                                    var name = (nameEl && nameEl.value) || (document.querySelector('.product-text h2') && document.querySelector('.product-text h2').textContent) || (document.querySelector('h2') && document.querySelector('h2').textContent) || 'Item';
                                                    var size = document.getElementById('size')?.value || 'Default';
                                                    var qty = parseInt(document.getElementById('quantity')?.value||'1',10) || 1;
                                                    var priceText = document.querySelector('.price-box')?.textContent || '0';
                                                    var match = priceText.match(/([\d,.]+)/);
                                                    var price = match ? parseFloat(match[1].replace(/,/g,'')) : 0;
                                                    var prodId = document.querySelector('input[name="product_id"]')?.value || '';
                                                    var product = { id: prodId, name: name.trim(), size: size, quantity: qty, price: price, total: price*qty };
                                                    // if not authenticated, open login modal instead of redirecting immediately
                                                    if (!window.isAuthenticated) {
                                                        if (typeof window.openLoginModal === 'function') {
                                                            window.openLoginModal('cart.php#checkout');
                                                            return;
                                                        }
                                                    }
                                                    var cart = JSON.parse(localStorage.getItem('cart')||'[]');
                                                    cart.push(product);
                                                    localStorage.setItem('cart', JSON.stringify(cart));
                                                    window.location.href = 'cart.php#checkout';
                                                }catch(err){ console.error('Buy fallback error', err); window.location.href = 'cart.php#checkout'; }
                                            });
                                        }, 120);
                                    }catch(e){ console.error(e); }
                                })();
                                </script>
            </div>
    </div>
    </section>

    <?php include 'footer.php'; ?>
    <!-- Quick Order Confirmation Modal (portal overlay) -->
    <div id="quickOrderModal" class="custom-modal quick-order-overlay" hidden aria-hidden="true" role="dialog" aria-modal="true">
        <div class="modal-content quick-order-content" role="document">
            <div class="modal-header">
                <span class="modal-title">Confirm Order</span>
                <button type="button" class="modal-close-btn" id="closeQuickOrderModalBtn" aria-label="Close">&times;</button>
            </div>
            <div class="modal-body">
                <div class="order-summary-block" id="quickOrderSummary"></div>
                <div class="profile-warning" id="quickOrderProfileWarn">You must complete your address & phone in profile before ordering.</div>
                <div class="modal-actions">
                    <button type="button" class="design-btn alt-btn" id="quickOrderCancelBtn">Cancel</button>
                    <button type="button" class="design-btn primary-btn" id="quickOrderConfirmBtn">Place Order</button>
                </div>
            </div>
        </div>
    </div>
    <div id="login-container"></div>
    <?php include 'login.php'; ?>

            <!-- Request Design Modal -->
            <div id="designModal" class="custom-modal" hidden>
                <div class="modal-content">
                    <div class="modal-header">
                        <span class="modal-title">Request a Design</span>
                        <button type="button" class="modal-close-btn" id="closeDesignModalBtn">&times;</button>
                    </div>
                    <div class="modal-body">
                        <form id="requestDesignForm">
                            <label for="requestDetails">Describe your design:</label>
                            <textarea id="requestDetails" rows="4" class="modal-textarea"></textarea>
                            <div class="modal-actions">
                                <button type="submit" class="design-btn">Submit Request</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Upload Design Modal -->
            <div id="uploadModal" class="custom-modal" hidden>
                <div class="modal-content">
                    <div class="modal-header">
                        <span class="modal-title">Upload Your Design</span>
                        <button type="button" class="modal-close-btn" id="closeUploadModalBtn">&times;</button>
                    </div>
                    <div class="modal-body">
                        <form id="uploadDesignForm">
                            <div class="file-label-wrapper">
                                <label for="uploadFile" class="file-label">
                                    <span class="file-label-text">Choose file</span>
                                    <input type="file" id="uploadFile" accept="image/*,application/pdf" class="modal-file-input" required />
                                </label>
                                <span id="fileNameDisplay" class="file-name-display"></span>
                            </div>
                            <div class="modal-actions">
                                <button type="submit" class="design-btn">Upload</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
    <script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>
    <script src="app.js"></script>
    <script src="about.js"></script>
    <script src="login.js"></script>
    <script src="message.js"></script>
    <script src="details.js"></script>
    <script src="forproductbtns.js"></script>
    <script src="three.min.js"></script>
    <script src="GLTFLoader.js"></script>
    <script src="OrbitControls.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@simonwep/pickr"></script>
    <script src="sim.js"></script>

</body>
</html>
