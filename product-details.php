<?php
session_start();
$isAuthenticated = isset($_SESSION['user_id']);

// Include DB for product lookup
require_once 'database.php';

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
    // Basic first image extraction if images stored as JSON or comma-separated; fallback to provided img param
    $productImg = $_GET['img'] ?? 'img/snorlax.png';
    if ($rawImages) {
        if (str_starts_with(trim($rawImages), '[')) { // JSON array
            $decoded = json_decode($rawImages, true);
            if (is_array($decoded) && count($decoded) > 0) { $productImg = $decoded[0]; }
        } elseif (strpos($rawImages, ',') !== false) {
            $parts = array_map('trim', explode(',', $rawImages));
            if ($parts[0] !== '') $productImg = $parts[0];
        } elseif (trim($rawImages) !== '') {
            $productImg = trim($rawImages);
        }
    }
} else {
    $productName = 'Product Not Found';
    $productPrice = '0.00';
    $productImg = 'img/snorlax.png';
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
    if (!$imagesField) return 'img/snorlax.png';
    $trim = trim($imagesField);
    if ($trim === '') return 'img/snorlax.png';
    if (str_starts_with($trim, '[')) {
        $decoded = json_decode($trim, true);
        if (is_array($decoded) && count($decoded) > 0) return $decoded[0];
    }
    if (strpos($trim, ',') !== false) {
        $parts = array_map('trim', explode(',', $trim));
        if ($parts[0] !== '') return $parts[0];
    }
    return $trim;
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
    <div class="flash-message success" style="position:fixed;top:70px;right:20px;background:#136b1d;color:#fff;padding:12px 18px;border-radius:8px;font-size:.9rem;font-weight:600;box-shadow:0 4px 12px rgba(0,0,0,.18);z-index:9999;display:flex;align-items:center;gap:10px;">
        <span>Order placed successfully.</span>
        <button type="button" aria-label="Dismiss" onclick="this.parentElement.remove();" style="background:rgba(255,255,255,.15);color:#fff;border:none;border-radius:4px;padding:4px 8px;cursor:pointer;font-size:.75rem;">✕</button>
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
                <button class="back-btn" id="backBtn">← Back</button>
            </div>
            <div class="image-column">
                <img src="<?php echo htmlspecialchars($productImg); ?>" alt="<?php echo htmlspecialchars($productName); ?>" class="product-image" id="mainImage" />
                <div class="thumbnail-row">
                    <div class="thumbnail-wrapper">
                        <img src="<?php echo htmlspecialchars($productImg); ?>" alt="Mug 1" class="thumbnail" />
                        <button class="delete-thumbnail-btn" type="button" title="Delete thumbnail">-</button>
                    </div>
                    <div class="thumbnail-wrapper">
                        <img src="<?php echo htmlspecialchars($productImg); ?>" alt="Mug 2" class="thumbnail" />
                        <button class="delete-thumbnail-btn" type="button" title="Delete thumbnail">-</button>
                    </div>
                    <div class="thumbnail-wrapper">
                        <img src="<?php echo htmlspecialchars($productImg); ?>" alt="Mug 3" class="thumbnail" />
                        <button class="delete-thumbnail-btn" type="button" title="Delete thumbnail">-</button>
                    </div>
                    <button type="button" id="add-thumbnail-btn">
                        <span>+</span>
                    </button>
                </div>
            </div>
        <div class="product-text">
            <h2><?php echo htmlspecialchars($productName); ?></h2>
            <div class="tab-header">
                <button class="tab-btn active" data-tab="description">Description</button>
                <button class="tab-btn" data-tab="order">Start Your Order</button>
            </div>
        <div class="tab-content" id="description">
            <p class="product-description">
                High quality custom printing for your needs.
            </p>
            <ul class="product-features">
                <li>Premium materials</li>
                <li>Customizable design</li>
            </ul>
            <h4>Product Details</h4>
            <ul class="product-details"></ul>
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
                    <p class="step-description">
                        Start your order in just a few clicks — whether you're uploading your own artwork or consulting with our team, we'll make sure every order feels personal.
                    </p>
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
                                    <button type="button" onclick="adjustQuantity(-1)">−</button>
                                    <input type="number" id="quantity" name="quantity" value="1" min="1" />
                                    <button type="button" onclick="adjustQuantity(1)">+</button>
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
                    <a href="sim.html" class="design-btn">Customize Design</a>
                    <button type="button" class="design-btn" id="requestDesignBtn">
                        Request Design
                    </button>
                </div>
                <p class="design-note">
                    <strong>Note:</strong> A digital proof of your design will be sent to your registered account. Please review and approve it to proceed with printing.
                </p>
                <input type="hidden" name="design-option" id="design-option" value="" />
            </section>
            <div class="action-buttons">
                <?php if ($productNotFound): ?>
                    <div class="product-warning" style="color:#b30000; font-weight:600; padding:10px 0;">
                        This product could not be found. It may have been removed or the link is invalid.
                    </div>
                <?php endif; ?>
                <form action="place_order.php" method="POST" id="orderForm" class="order-form">
                    <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($productId ?? ''); ?>" />
                    <input type="hidden" name="size" id="form_size" value="12oz" />
                    <input type="hidden" name="color" id="form_color" value="" />
                    <input type="hidden" name="quantity" id="form_quantity" value="1" />
                    <input type="hidden" name="isPartialPayment" value="0" />
                    <input type="hidden" name="TotalAmount" id="form_totalAmount" value="<?php echo htmlspecialchars($productPrice); ?>" />
                    <input type="hidden" name="OrderStatus" value="Pending" />
                    <input type="hidden" name="DeliveryAddress" id="form_DeliveryAddress" value="" />
                    <input type="hidden" name="DeliveryStatus" value="Pending" />
                    <button type="button" class="buy-btn" id="buyNowBtn" data-price="<?php echo htmlspecialchars($productPrice); ?>" <?php echo $productNotFound ? 'disabled style="opacity:.5;cursor:not-allowed;"' : ''; ?>>Buy Now</button>
                </form>
                <form action="add_to_cart.php" method="POST" id="cartForm" class="cart-form" onsubmit="return false;">
                    <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($productId ?? ''); ?>" />
                    <input type="hidden" name="size" id="cart_size" value="12oz" />
                    <input type="hidden" name="color" id="cart_color" value="" />
                    <input type="hidden" name="quantity" id="cart_quantity" value="1" />
                    <button type="button" class="addcart-btn" <?php echo $productNotFound ? 'disabled style="opacity:.5;cursor:not-allowed;"' : ''; ?>>Add to Cart</button>
                </form>
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
