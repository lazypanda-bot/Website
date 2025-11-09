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

// Load product sub-options (Type/Size/Attribute) from products_sub to mirror admin
$typesOptions = [];
$sizesOptions = [];
$attrOptions = [];
$priceByType = [];
$priceBySize = [];
$priceByAttr = [];
$priceByCombo = [];
if (!$productNotFound && isset($conn) && !$conn->connect_error) {
    // Detect products_sub table and columns
    $subsExists = false; $subCols = [];
    if ($cols = $conn->query("SHOW COLUMNS FROM products_sub")) {
        $subsExists = true;
        while ($c = $cols->fetch_assoc()) { $subCols[strtolower($c['Field'])] = $c['Field']; }
        $cols->free();
    }
    if ($subsExists) {
        $cProductId = $subCols['product_id'] ?? 'product_id';
        $cKind      = $subCols['kind'] ?? 'kind';
        $cValue     = $subCols['value'] ?? 'value';
        $cPrice     = $subCols['price'] ?? 'price';
        $cTypes     = $subCols['types'] ?? null;
        $cSizes     = $subCols['sizes'] ?? null;
        $cAttrs     = $subCols['attributes'] ?? ($subCols['attribute'] ?? null);

        $sql = "SELECT $cKind AS kind, $cValue AS value, IFNULL($cPrice,0) AS price";
        if ($cTypes) $sql .= ", $cTypes AS types"; else $sql .= ", '' AS types";
        if ($cSizes) $sql .= ", $cSizes AS sizes"; else $sql .= ", '' AS sizes";
        if ($cAttrs) $sql .= ", $cAttrs AS attributes"; else $sql .= ", '' AS attributes";
        $sql .= " FROM products_sub WHERE $cProductId = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param('i', $productId);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $k = strtolower(trim((string)$row['kind']));
                    $v = trim((string)$row['value']);
                    $p = is_null($row['price']) ? 0 : (float)$row['price'];
                    $t = trim((string)$row['types']);
                    $s = trim((string)$row['sizes']);
                    $a = trim((string)$row['attributes']);

                    // Collect option lists by kind
                    if ($k === 'type' && $v !== '') { $typesOptions[] = $v; if ($p > 0) $priceByType[$v] = $p; }
                    if ($k === 'size' && $v !== '') { $sizesOptions[] = $v; if ($p > 0) $priceBySize[$v] = $p; }
                    if (($k === 'attribute' || $k === 'attributes') && $v !== '') { $attrOptions[] = $v; if ($p > 0) $priceByAttr[$v] = $p; }

                    // If wide columns are present, record combo pricing (partial or full)
                    if ($t !== '' || $s !== '' || $a !== '') {
                        $ckey = strtolower($t) . '|' . strtolower($s) . '|' . strtolower($a);
                        if ($p > 0) $priceByCombo[$ckey] = $p;
                        // also merge into option pools
                        if ($t !== '') $typesOptions[] = $t;
                        if ($s !== '') $sizesOptions[] = $s;
                        if ($a !== '') $attrOptions[] = $a;
                    }
                }
                $res->free();
            }
            $stmt->close();
        }

        // Dedupe while preserving order
        $typesOptions = array_values(array_unique($typesOptions));
        $sizesOptions = array_values(array_unique($sizesOptions));
        $attrOptions  = array_values(array_unique($attrOptions));
    }
}

// Fetch related products (same service_type) if available
$relatedProducts = [];
// Detect service_type/service_id and build a resilient related-products query
if (!$productNotFound && $productsTableExists) {
    // Extend detected product columns with service columns and created_at
    $serviceTypeCol = null; $serviceIdCol = null; $createdAtCol = null;
    if ($colsRes2 = $conn->query("SHOW COLUMNS FROM products")) {
        $available2 = [];
        while ($cRow2 = $colsRes2->fetch_assoc()) { $available2[strtolower($cRow2['Field'])] = $cRow2['Field']; }
        $colsRes2->free();
        foreach(['service_type','service','category','type'] as $c){ if(isset($available2[$c])) { $serviceTypeCol = $available2[$c]; break; } }
        foreach(['service_id','serviceid','sid'] as $c){ if(isset($available2[$c])) { $serviceIdCol = $available2[$c]; break; } }
        foreach(['created_at','createdat','date_created','createdon'] as $c){ if(isset($available2[$c])) { $createdAtCol = $available2[$c]; break; } }
    }

    $whereCol = null; $whereType = null; $whereVal = null;
    if ($serviceTypeCol && isset($productRow[$serviceTypeCol]) && $productRow[$serviceTypeCol] !== '') {
        $whereCol = $serviceTypeCol; $whereType = 's'; $whereVal = (string)$productRow[$serviceTypeCol];
    } elseif ($serviceIdCol && isset($productRow[$serviceIdCol])) {
        $whereCol = $serviceIdCol; $whereType = 'i'; $whereVal = (int)$productRow[$serviceIdCol];
    }

    if ($whereCol) {
        // Build SELECT with aliases so downstream rendering can use consistent keys
        $selId = $productIdCol . ' AS product_id';
        $selName = ($productNameCol ? ($productNameCol . ' AS product_name') : ("'' AS product_name"));
        $selPrice = ($productPriceCol ? ($productPriceCol . ' AS price') : ('0 AS price'));
        $selImages = ($productImagesCol ? ($productImagesCol . ' AS images') : ("'' AS images"));
        $order = $createdAtCol ? ($createdAtCol . ' DESC') : ($productIdCol . ' DESC');
        $sqlRel = "SELECT $selId, $selName, $selPrice, $selImages FROM products WHERE $whereCol = ? AND $productIdCol <> ? ORDER BY $order LIMIT 8";
        if ($stmtRel = $conn->prepare($sqlRel)) {
            if ($whereType === 's') { $stmtRel->bind_param('si', $whereVal, $productId); }
            else { $stmtRel->bind_param('ii', $whereVal, $productId); }
            if ($stmtRel->execute()) {
                $resRel = $stmtRel->get_result();
                while ($r = $resRel->fetch_assoc()) { $relatedProducts[] = $r; }
            }
            $stmtRel->close();
        }
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
<?php
    // When a design has just been saved, prefer landing on the Start Your Order tab
    $forceOrderTab = isset($_GET['designoption_id']) && ctype_digit((string)$_GET['designoption_id']) && ((int)$_GET['designoption_id']) > 0;
?>
    <script>
        // Dynamic options from admin (products_sub)
        window.__pd_priceByCombo = <?php echo json_encode($priceByCombo, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
        window.__pd_priceByType  = <?php echo json_encode($priceByType, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
        window.__pd_priceBySize  = <?php echo json_encode($priceBySize, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
        window.__pd_priceByAttr  = <?php echo json_encode($priceByAttr, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
        window.__pd_defaults = {
            type: <?php echo json_encode($typesOptions[0] ?? ''); ?>,
            size: <?php echo json_encode($sizesOptions[0] ?? ''); ?>,
            attribute: <?php echo json_encode($attrOptions[0] ?? ''); ?>,
            basePrice: <?php echo json_encode((float)($productPrice ?? 0)); ?>
        };
    </script>
    <script>
        // Price calculation and form sync for Type/Size/Attribute
        document.addEventListener('DOMContentLoaded', function(){
            try{
                var typeSel = document.getElementById('typeSelect');
                var sizeSel = document.getElementById('sizeSelect');
                var attrSel = document.getElementById('attrSelect');
                var hTypeF = document.getElementById('form_type');
                var hSizeF = document.getElementById('form_size');
                var hAttrF = document.getElementById('form_attribute');
                var hTypeC = document.getElementById('cart_type');
                var hSizeC = document.getElementById('cart_size');
                var hAttrC = document.getElementById('cart_attribute');
                var hColorF = document.getElementById('form_color');
                var hColorC = document.getElementById('cart_color');
                var priceBox = document.querySelector('.price-box');
                var priceAmountEl = priceBox ? priceBox.querySelector('.amount') : null;
                var buyBtn = document.querySelector('.buy-btn');

                function val(el){ return (el && el.value) ? el.value.trim() : ''; }

                function computePrice(t, s, a){
                    var pBase = (window.__pd_defaults && window.__pd_defaults.basePrice) ? parseFloat(window.__pd_defaults.basePrice)||0 : 0;
                    t = (t||'').toLowerCase(); s = (s||'').toLowerCase(); a = (a||'').toLowerCase();
                    var combo = window.__pd_priceByCombo || {};
                    var key = t+'|'+s+'|'+a;
                    if (combo[key] != null) return parseFloat(combo[key])||0;
                    // Try partial keys in priority order
                    var partials = [ t+'|'+s+'|', t+'||'+a, '|'+s+'|'+a, t+'||', '|'+s+'|', '||'+a ];
                    for (var i=0;i<partials.length;i++){ var k=partials[i]; if (combo[k]!=null) return parseFloat(combo[k])||0; }
                    // Fallback to per-kind pricing
                    if (t && window.__pd_priceByType && window.__pd_priceByType[t]!=null) return parseFloat(window.__pd_priceByType[t])||0;
                    if (s && window.__pd_priceBySize && window.__pd_priceBySize[s]!=null) return parseFloat(window.__pd_priceBySize[s])||0;
                    if (a && window.__pd_priceByAttr && window.__pd_priceByAttr[a]!=null) return parseFloat(window.__pd_priceByAttr[a])||0;
                    return pBase;
                }

                function syncHidden(){
                    var t = val(typeSel), s = val(sizeSel), a = val(attrSel);
                    if (hTypeF) hTypeF.value = t; if (hTypeC) hTypeC.value = t;
                    if (hSizeF) hSizeF.value = s; if (hSizeC) hSizeC.value = s;
                    if (hAttrF) hAttrF.value = a; if (hAttrC) hAttrC.value = a;
                    // Map attribute -> color when relevant (keeps downstream compatibility)
                    if (hColorF) hColorF.value = a; if (hColorC) hColorC.value = a;
                }

                function renderPrice(){
                    var t = val(typeSel), s = val(sizeSel), a = val(attrSel);
                    var p = computePrice(t,s,a);
                    if (priceBox) { priceBox.setAttribute('data-price', p.toFixed(2)); }
                    if (priceAmountEl) { priceAmountEl.textContent = p.toFixed(2); }
                    if (buyBtn) { buyBtn.setAttribute('data-price', p.toFixed(2)); }
                    var totalHidden = document.getElementById('form_totalAmount');
                    if (totalHidden) totalHidden.value = p.toFixed(2);
                }

                // Persistent selection storage (survives navigation to sim.php and back)
                var pidInput = document.querySelector('input[name="product_id"]');
                var pid = pidInput && pidInput.value ? pidInput.value : null;
                var storageKey = pid ? 'pd_opts_' + pid : null;
                var keepKey = pid ? 'pd_keep_' + pid : null;

                function selectOptionValue(sel, value){
                    if(!sel || !value) return;
                    for(var i=0;i<sel.options.length;i++){
                        if(sel.options[i].value === value){ sel.selectedIndex = i; break; }
                    }
                }

                function restoreSelections(){
                    if(!storageKey) return;
                    try{
                        var saved = JSON.parse(localStorage.getItem(storageKey)||'{}');
                        if(saved.type) selectOptionValue(typeSel, saved.type);
                        if(saved.size) selectOptionValue(sizeSel, saved.size);
                        if(saved.attribute) selectOptionValue(attrSel, saved.attribute);
                        // variant restored in variant wiring script; here we only handle core dropdowns
                    }catch(e){ /* ignore */ }
                }

                function persistSelections(){
                    if(!storageKey) return;
                    try{
                        var data = JSON.parse(localStorage.getItem(storageKey)||'{}');
                        data.type = val(typeSel);
                        data.size = val(sizeSel);
                        data.attribute = val(attrSel);
                        localStorage.setItem(storageKey, JSON.stringify(data));
                    }catch(e){ /* ignore */ }
                }

                function initDefaults(){
                    // If nothing restored/selected, auto-select the first option for convenience.
                    restoreSelections();
                    [typeSel,sizeSel,attrSel].forEach(function(sel){ if (sel && sel.selectedIndex < 0 && sel.options.length > 0) sel.selectedIndex = 0; });
                    syncHidden();
                    renderPrice();
                }

                if (typeSel) typeSel.addEventListener('change', function(){ syncHidden(); renderPrice(); persistSelections(); });
                if (sizeSel) sizeSel.addEventListener('change', function(){ syncHidden(); renderPrice(); persistSelections(); });
                if (attrSel) attrSel.addEventListener('change', function(){ syncHidden(); renderPrice(); persistSelections(); });

                // Single-use persistence: keep selections only when navigating via design actions.
                // If page is being left normally, clear saved selections.
                window.__pd_persistNextNavigation = false;
                window.addEventListener('beforeunload', function(){
                    try{
                        if (!window.__pd_persistNextNavigation && storageKey) {
                            localStorage.removeItem(storageKey);
                        }
                    }catch(e){}
                });
                // If we were instructed to keep across the previous navigation (from sim/upload/request),
                // clear the keep flag now so future navigations reset unless explicitly set again.
                try{ if (keepKey && sessionStorage.getItem(keepKey)==='1') { sessionStorage.removeItem(keepKey); } }catch(e){}
                initDefaults();
            }catch(e){ console.error('Option wiring failed', e); }
        });
        </script>
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
        echo '<div class="image-debug">';
        echo '<strong>Image debug</strong><ul>';
        // show rawImages and whether thumbnails exist
        echo '<li class="image-debug-item">rawImages: ' . htmlspecialchars($rawImages) . '</li>';
        echo '<li class="image-debug-item">hasThumbnails: ' . ($hasThumbnails ? 'yes' : 'no') . '</li>';
        foreach ($imagesList as $ii => $isrc) {
            $url = $isrc;
            // Resolve a filesystem path for checking existence
            $rel = ltrim($url, '/');
            $fs = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/' . $rel;
            $exists = file_exists($fs) ? 'exists' : 'missing';
            echo '<li class="image-debug-item">src: ' . htmlspecialchars($url) . ' — file: ' . htmlspecialchars($fs) . ' — <strong>' . $exists . '</strong></li>';
        }
        echo '</ul></div>';
    }
?>
                </div>
            </div>
        <div class="product-text">
            <h2><?php echo htmlspecialchars($productName); ?></h2>
            <div class="tab-header">
                <button class="tab-btn <?= $forceOrderTab ? '' : 'active' ?>" data-tab="description">Description</button>
                <button class="tab-btn <?= $forceOrderTab ? 'active' : '' ?>" data-tab="order">Start Your Order</button>
            </div>
        <div class="tab-content" id="description"<?= $forceOrderTab ? ' hidden' : '' ?>>
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
    <div class="tab-content" id="order"<?= $forceOrderTab ? '' : ' hidden' ?>>
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
                            <?php
                                $hasTypes = count($typesOptions) > 0;
                                $hasSizes = count($sizesOptions) > 0;
                                $hasAttrs = count($attrOptions) > 0;
                                $defType = $hasTypes ? $typesOptions[0] : '';
                                $defSize = $hasSizes ? $sizesOptions[0] : '';
                                $defAttr = $hasAttrs ? $attrOptions[0] : '';
                            ?>
                            <?php if ($hasTypes): ?>
                            <div class="form-group grid-col-2">
                                <label for="typeSelect">Type</label>
                                <select id="typeSelect" name="type" style="width:100%;padding:8px;border-radius:8px;border:1px solid #ccc;">
                                    <?php foreach($typesOptions as $opt): $safe=htmlspecialchars($opt); ?>
                                        <option value="<?=$safe?>" <?= ($opt===$defType?'selected':'')?>><?=$safe?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>

                            <?php if ($hasSizes): ?>
                            <div class="form-group grid-col-2">
                                <label for="sizeSelect">Size</label>
                                <select id="sizeSelect" name="size" style="width:100%;padding:8px;border-radius:8px;border:1px solid #ccc;">
                                    <?php foreach($sizesOptions as $opt): $safe=htmlspecialchars($opt); ?>
                                        <option value="<?=$safe?>" <?= ($opt===$defSize?'selected':'')?>><?=$safe?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>

                            <?php if ($hasAttrs): ?>
                            <div class="form-group grid-col-2">
                                <label for="attrSelect">Attribute</label>
                                <select id="attrSelect" name="attribute" style="width:100%;padding:8px;border-radius:8px;border:1px solid #ccc;">
                                    <?php foreach($attrOptions as $opt): $safe=htmlspecialchars($opt); ?>
                                        <option value="<?=$safe?>" <?= ($opt===$defAttr?'selected':'')?>><?=$safe?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
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
                                <?php
                                    $variants = [];
                                        if (!empty($productRow['variants'])) {
                                            $raw = $productRow['variants'];
                                            $dec = json_decode($raw, true);
                                            if (is_array($dec)) $variants = $dec;
                                            else if (is_string($raw) && trim($raw) !== '') {
                                                // tolerate comma-separated stored strings
                                                $parts = array_map('trim', explode(',', $raw));
                                                $parts = array_values(array_filter($parts, function($x){ return $x !== ''; }));
                                                if (count($parts)>0) $variants = $parts;
                                            }
                                        }
                                ?>
                                <?php if (!empty($variants)): ?>
                                    <div class="product-static-box price-box">
                                        <label for="variantSelect" style="display:block;font-weight:600;margin-bottom:6px;color:#752525;">Choose option</label>
                                        <select id="variantSelect" name="variant" style="width:100%;padding:8px;border-radius:8px;border:1px solid #ccc;">
                                            <option value="" selected disabled>Select option</option>
                                            <?php foreach($variants as $vi => $vv):
                                                // Support both simple string variants and structured entries with nested colors
                                                if (is_array($vv)) {
                                                    $vname = htmlspecialchars($vv['name'] ?? 'Option');
                                                    // If this variant contains a 'colors' array, emit one option per color
                                                    if (!empty($vv['colors']) && is_array($vv['colors'])){
                                                        foreach($vv['colors'] as $ci => $cinfo){
                                                            $colorLabel = htmlspecialchars($cinfo['color'] ?? ($cinfo['name'] ?? ''));
                                                            $vprice_raw = isset($cinfo['price']) ? floatval($cinfo['price']) : null;
                                                            $vprice_str = $vprice_raw !== null ? number_format($vprice_raw, 2) : '';
                                                            $dataPrice = $vprice_raw !== null ? ' data-price="' . htmlspecialchars($vprice_str) . '"' : '';
                                                            $dataColor = ' data-color="' . $colorLabel . '"';
                                                            $dataVid = ' data-vid="' . intval($vi) . '" data-cid="' . intval($ci) . '"';
                                                            $label = $vname . ($colorLabel ? (' — ' . $colorLabel) : '');
                                                            echo "<option value=\"". htmlspecialchars($label) ."\"". $dataPrice . $dataColor . $dataVid .">" . htmlspecialchars($label) . ($vprice_str !== '' ? ' — ₱' . $vprice_str : '') . "</option>";
                                                        }
                                                    } else {
                                                        // fallback: single price on the variant object
                                                        $vprice_raw = isset($vv['price']) ? floatval($vv['price']) : null;
                                                        $vprice_str = $vprice_raw !== null ? number_format($vprice_raw, 2) : '';
                                                        $dataPrice = $vprice_raw !== null ? ' data-price="' . htmlspecialchars($vprice_str) . '"' : '';
                                                        $dataVid = ' data-vid="' . intval($vi) . '"';
                                                        echo "<option value=\"". $vname ."\"". $dataPrice . $dataVid .">" . $vname . ($vprice_str !== '' ? ' — ₱' . $vprice_str : '') . "</option>";
                                                    }
                                                } else {
                                                    $vname = htmlspecialchars((string)$vv);
                                                    echo "<option value=\"". $vname ."\">" . $vname . "</option>";
                                                }
                                            endforeach; ?>
                                        </select>
                                    </div>
                                <?php else: ?>
                                    <div class="product-static-box price-box" data-price="<?php echo htmlspecialchars(is_numeric($productPrice)?number_format((float)$productPrice,2,'.',''):$productPrice); ?>">
                                        <span class="peso-sign">₱</span>
                                        <span class="amount"><?php echo htmlspecialchars(is_numeric($productPrice)?number_format((float)$productPrice,2):$productPrice); ?></span>
                                    </div>
                                <?php endif; ?>
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
                    <a href="<?= htmlspecialchars($simHref) ?>" class="design-btn" id="customizeDesignLink">Customize Design</a>
                    <button type="button" class="design-btn" id="requestDesignBtn">
                        Request Design
                    </button>
                </div>
                <input type="hidden" name="design-option" id="design-option" value="" />
                <?php
                    // If page was opened with a saved designoption id, render a small preview area
                    $queriedDesignOption = isset($_GET['designoption_id']) ? (int)$_GET['designoption_id'] : null;
                    // Show saved design preview only if the designoption is still referenced
                    // in the user's cart (ensures deleting the cart item removes preview on product page).
                    if ($queriedDesignOption && isset($conn) && !$conn->connect_error) {
                        $stillInCart = false;
                        try {
                            // Detect cart table & design column
                            $ctCols = [];
                            if ($ctRes = $conn->query('SHOW COLUMNS FROM cart')) { while($cr=$ctRes->fetch_assoc()){ $ctCols[strtolower($cr['Field'])]=$cr['Field']; } $ctRes->free(); }
                            $designCartCol = null; foreach(['designoption_id','design_option_id','design_id'] as $c){ if(isset($ctCols[$c])) { $designCartCol=$ctCols[$c]; break; } }
                            $cartUserCol = isset($ctCols['user_id']) ? $ctCols['user_id'] : (isset($ctCols['customer_id']) ? $ctCols['customer_id'] : 'user_id');
                            $cartProdCol = isset($ctCols['product_id']) ? $ctCols['product_id'] : (isset($ctCols['prod_id'])?$ctCols['prod_id']:'product_id');
                            if ($designCartCol) {
                                $chkSql = "SELECT 1 FROM cart WHERE $designCartCol = ? AND $cartProdCol = ? AND $cartUserCol = ? LIMIT 1";
                                if ($chk = $conn->prepare($chkSql)) {
                                    $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
                                    $chk->bind_param('iii', $queriedDesignOption, $productId, $uid);
                                    if ($chk->execute()) { $chk->store_result(); $stillInCart = $chk->num_rows > 0; }
                                    $chk->close();
                                }
                            }
                        } catch(Exception $e) { /* ignore */ }
                        if (!$stillInCart) { $queriedDesignOption = null; }
                        // Detect optional columns on customization to avoid Unknown column errors (e.g., 'note')
                        $cCols = [];
                        if ($cRes = $conn->query('SHOW COLUMNS FROM customization')) {
                            while ($cr = $cRes->fetch_assoc()) { $cCols[strtolower($cr['Field'])] = $cr['Field']; }
                            $cRes->free();
                        }
                        $selColor = isset($cCols['color']) ? ('cu.' . $cCols['color'] . ' AS color') : ("'' AS color");
                        $selNote  = isset($cCols['note'])  ? ('cu.' . $cCols['note']  . ' AS note')  : ("'' AS note");
                        $dsql = 'SELECT do.designoption_id, do.designfilepath, do.request_design, ' . $selColor . ', ' . $selNote . ' FROM designoption do LEFT JOIN customization cu ON cu.customization_id = do.customization_id WHERE do.designoption_id = ? LIMIT 1';
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
                                        // Prefer to check the file exists on disk so we can render reliably
                                        $absPath = __DIR__ . '/' . $src;
                                        if (file_exists($absPath)) {
                                            $safeSrc = htmlspecialchars($src);
                                            echo "<div class=\"saved-design-thumb\"><a href=\"$safeSrc\" target=\"_blank\" rel=\"noopener noreferrer\"><img src=\"$safeSrc\" alt=\"Saved design\"/></a></div>";
                                        } else {
                                            // File missing on disk — show a placeholder and the stored path for debugging
                                            $debugPath = htmlspecialchars($src);
                                            echo "<div class=\"saved-design-thumb\"><div class=\"saved-design-missing\">Image not found</div></div>";
                                            echo "<div class=\"saved-design-debugpath\">Stored path: <code>$debugPath</code></div>";
                                        }
                                    } else {
                                        $sw = $dc ? $dc : '#efeef0';
                                        // background color is dynamic so we set it inline here (color value comes from DB)
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
                    <div class="product-warning">
                        This product could not be found. It may have been removed or the link is invalid.
                    </div>
                <?php endif; ?>
                <form action="place-order.php" method="POST" id="orderForm" class="order-form">
                    <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($productId ?? ''); ?>" />
                    <input type="hidden" name="type" id="form_type" value="" />
                    <input type="hidden" name="size" id="form_size" value="" />
                    <input type="hidden" name="attribute" id="form_attribute" value="" />
                    <input type="hidden" name="color" id="form_color" value="" />
                    <input type="hidden" name="variant_index" id="form_variant_index" value="" />
                    <input type="hidden" name="quantity" id="form_quantity" value="1" />
                    
                    <input type="hidden" name="TotalAmount" id="form_totalAmount" value="<?php echo htmlspecialchars($productPrice); ?>" />
                    <input type="hidden" name="OrderStatus" value="Pending" />
                    <input type="hidden" name="DeliveryAddress" id="form_DeliveryAddress" value="" />
                    <input type="hidden" name="DeliveryStatus" value="Pending" />
                    <button type="button" class="buy-btn" id="buyNowBtn" data-price="<?php echo htmlspecialchars($productPrice); ?>" <?php echo $productNotFound ? 'disabled' : ''; ?>>Buy Now</button>
                </form>
                <form action="add-to-cart.php" method="POST" id="cartForm" class="cart-form" onsubmit="return false;">
                    <form action="add-to-cart.php" method="POST" id="cartForm" class="cart-form">
                    <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($productId ?? ''); ?>" />
                    <input type="hidden" name="type" id="cart_type" value="" />
                    <input type="hidden" name="size" id="cart_size" value="" />
                    <input type="hidden" name="attribute" id="cart_attribute" value="" />
                    <input type="hidden" name="color" id="cart_color" value="" />
                    <input type="hidden" name="variant_index" id="cart_variant_index" value="" />
                    <input type="hidden" name="quantity" id="cart_quantity" value="1" />
                    <button type="button" class="addcart-btn" <?php echo $productNotFound ? 'disabled' : ''; ?>>Add to Cart</button>
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
                                                    var size = (document.getElementById('sizeSelect') && document.getElementById('sizeSelect').value) || (document.getElementById('size') && document.getElementById('size').value) || 'Default';
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

            <!-- Upload Design Modal (drag/drop + browse) -->
            <div id="uploadModal" class="custom-modal" hidden>
                <div class="modal-content">
                    <div class="modal-header">
                        <span class="modal-title">Upload Your Design</span>
                        <button type="button" class="modal-close-btn" id="closeUploadModalBtn">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div id="dropZone" class="upload-drop" aria-label="File drop zone">
                            <svg width="56" height="56" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="upload-svg">
                                <path d="M12 3v10" stroke="#a02b2b" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M8 7l4-4 4 4" stroke="#a02b2b" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                <rect x="3" y="13" width="18" height="8" rx="2" stroke="#a02b2b" stroke-width="1.2"/>
                            </svg>
                            <h3>Select Files to Upload</h3>
                            <p class="upload-sub">or Drag and Drop, Copy and Paste Files</p>
                            <label class="browse-btn" for="uploadFile">Browse Files</label>
                            <input type="file" id="uploadFile" accept="image/*,application/pdf" class="modal-file-input" multiple />
                        </div>
                        <div id="uploadList" class="upload-list" aria-live="polite"></div>
                        <div class="modal-actions">
                            <button type="button" class="design-btn" id="cancelUploadBtn">Cancel</button>
                            <button type="button" class="design-btn" id="confirmUploadBtn" disabled>Select</button>
                        </div>
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
    
    <script>
        // Before leaving for Customize Design, persist current selections explicitly
        document.addEventListener('DOMContentLoaded', function(){
            try{
                var link = document.getElementById('customizeDesignLink');
                var uploadBtn = document.getElementById('uploadDesignBtn');
                var requestBtn = document.getElementById('requestDesignBtn');
                if(!link) return;
                function persistForDesignAction(){
                    var pidInput = document.querySelector('input[name="product_id"]');
                    var pid = pidInput && pidInput.value ? pidInput.value : null;
                    if(!pid) return;
                    var key = 'pd_opts_' + pid;
                    var keepKey = 'pd_keep_' + pid;
                    function val(id){ var el=document.getElementById(id); return el && el.value ? el.value : ''; }
                    var data = {
                        type: val('typeSelect'),
                        size: val('sizeSelect'),
                        attribute: val('attrSelect'),
                        variant: (function(){ var v=document.getElementById('variantSelect'); return v && v.value ? v.value : ''; })()
                    };
                    try{ localStorage.setItem(key, JSON.stringify(data)); }catch(e){}
                    try{ sessionStorage.setItem(keepKey,'1'); }catch(e){}
                    window.__pd_persistNextNavigation = true; // prevent beforeunload cleanup
                }
                link.addEventListener('click', persistForDesignAction);
                if(uploadBtn) uploadBtn.addEventListener('click', persistForDesignAction);
                if(requestBtn) requestBtn.addEventListener('click', persistForDesignAction);
                });
            }catch(e){ console.warn('persist-on-customize failed', e); }
        });
        // Wire variant selection to update hidden total and visible price display
        document.addEventListener('DOMContentLoaded', function(){
            try{
                var variantSelect = document.getElementById('variantSelect');
                var formTotal = document.getElementById('form_totalAmount');
                var priceBox = document.querySelector('.price-box');
                var buyBtn = document.querySelector('.buy-btn');
                function updatePriceFromSelect(){
                    if(!variantSelect || !formTotal) return;
                    // If the option provides a data-price attribute (structured variant), use that.
                    var opt = variantSelect.selectedOptions && variantSelect.selectedOptions[0];
                    var val = 0;
                    if (opt && opt.dataset && opt.dataset.price) {
                        val = parseFloat(opt.dataset.price.replace(/,/g,'')) || 0;
                    } else {
                        // no structured price available — keep original product price
                        val = parseFloat(formTotal.value || '0') || 0;
                    }
                    formTotal.value = val.toFixed(2);
                    // update selected variant and color hidden inputs when available
                    try{
                        var fVar = document.getElementById('form_variant_index');
                        var cVar = document.getElementById('cart_variant_index');
                        var fColor = document.getElementById('form_color');
                        var cColor = document.getElementById('cart_color');
                        if(opt && opt.dataset){
                            if(fVar) fVar.value = opt.dataset.vid || '';
                            if(cVar) cVar.value = opt.dataset.vid || '';
                            if(opt.dataset.color){ if(fColor) fColor.value = opt.dataset.color; if(cColor) cColor.value = opt.dataset.color; }
                        }
                    }catch(e){/* ignore */}
                    // render a small price display inside the price-box so existing parsers can pick it up
                    if(priceBox){
                        var disp = priceBox.querySelector('.variant-price-display');
                        if(!disp){
                            disp = document.createElement('div');
                            disp.className = 'variant-price-display';
                            disp.style.marginTop = '8px';
                            disp.style.fontWeight = '700';
                            disp.innerHTML = '<span class="peso-sign">₱</span><span class="amount"></span>';
                            priceBox.appendChild(disp);
                        }
                        disp.querySelector('.amount').textContent = val.toFixed(2);
                        // also set data-price for alternative access
                        priceBox.setAttribute('data-price', val.toFixed(2));
                    }
                    if(buyBtn) buyBtn.setAttribute('data-price', val.toFixed(2));
                }
                if(variantSelect){
                    updatePriceFromSelect();
                    variantSelect.addEventListener('change', function(){ updatePriceFromSelect(); });
                    // Restore previously chosen variant (after returning from customization)
                    try{
                        var pidInput2 = document.querySelector('input[name="product_id"]');
                        var pid2 = pidInput2 && pidInput2.value ? pidInput2.value : null;
                        var key2 = pid2 ? 'pd_opts_' + pid2 : null;
                        if(key2){
                            var saved2 = JSON.parse(localStorage.getItem(key2)||'{}');
                            if(saved2.variant){
                                for(var i=0;i<variantSelect.options.length;i++){
                                    if(variantSelect.options[i].value === saved2.variant){ variantSelect.selectedIndex = i; break; }
                                }
                                updatePriceFromSelect();
                            }
                        }
                    }catch(e){ /* ignore */ }
                    // Persist variant on change
                    variantSelect.addEventListener('change', function(){
                        try{
                            var pidInput3 = document.querySelector('input[name="product_id"]');
                            var pid3 = pidInput3 && pidInput3.value ? pidInput3.value : null;
                            if(!pid3) return;
                            var key3 = 'pd_opts_' + pid3;
                            var data3 = JSON.parse(localStorage.getItem(key3)||'{}');
                            data3.variant = variantSelect.value;
                            localStorage.setItem(key3, JSON.stringify(data3));
                        }catch(e){ /* ignore */ }
                    });
                }
            }catch(e){ console.error('Variant wiring failed', e); }
        });
    </script>
    <script>
        (function(){
            const uploadBtn = document.getElementById('uploadDesignBtn');
            const uploadModal = document.getElementById('uploadModal');
            const dropZone = document.getElementById('dropZone');
            const fileInput = document.getElementById('uploadFile');
            const uploadList = document.getElementById('uploadList');
            const closeBtn = document.getElementById('closeUploadModalBtn');
            const cancelBtn = document.getElementById('cancelUploadBtn');
            const confirmBtn = document.getElementById('confirmUploadBtn');
            let selectedFiles = [];

            function humanFileSize(size){ if (size === 0) return '0 B'; const i = Math.floor(Math.log(size)/Math.log(1024)); const sizes=['B','KB','MB','GB']; return (size/Math.pow(1024,i)).toFixed(i?1:0)+' '+sizes[i]; }

            function openModal(){ if(!uploadModal) return; uploadModal.hidden = false; document.body.style.overflow='hidden'; selectedFiles=[]; fileInput.value=''; renderList(); confirmBtn.disabled=true; }
            function closeModal(){ if(!uploadModal) return; uploadModal.hidden = true; document.body.style.overflow=''; }

            // keep track of object URLs to revoke when items removed
            const _objectURLs = new Map();

            function renderList(){
                uploadList.innerHTML = '';
                if (!selectedFiles.length) {
                    uploadList.innerHTML = '<div class="upload-empty">No files selected</div>';
                    confirmBtn.disabled = true;
                    return;
                }

                selectedFiles.forEach((f, idx) => {
                    const item = document.createElement('div'); item.className = 'item';
                    const left = document.createElement('div'); left.className = 'left';

                    // thumbnail (images) or placeholder
                    const thumb = document.createElement('div');
                    if (f.type && f.type.indexOf('image/') === 0) {
                        const img = document.createElement('img');
                        img.className = 'thumb';
                        let url = _objectURLs.get(f) || URL.createObjectURL(f);
                        if (!_objectURLs.has(f)) _objectURLs.set(f, url);
                        img.src = url;
                        thumb.appendChild(img);
                    } else {
                        // simple file icon box
                        const box = document.createElement('div');
                        box.className = 'thumb file-box';
                        box.innerHTML = '<i class="fa-solid fa-file file-icon"></i>';
                        thumb.appendChild(box);
                    }

                    const meta = document.createElement('div'); meta.className = 'meta';
                    const name = document.createElement('div'); name.className = 'name'; name.textContent = f.name;
                    const sizeEl = document.createElement('div'); sizeEl.className = 'size'; sizeEl.textContent = humanFileSize(f.size);
                    meta.appendChild(name); meta.appendChild(sizeEl);

                    left.appendChild(thumb); left.appendChild(meta);
                    item.appendChild(left);

                    const actions = document.createElement('div'); actions.className = 'actions';
                    const removeBtn = document.createElement('button'); removeBtn.type = 'button'; removeBtn.className = 'remove-btn'; removeBtn.textContent = 'Remove';
                    removeBtn.addEventListener('click', () => {
                        // revoke object URL if any
                        const url = _objectURLs.get(f);
                        try { if (url) URL.revokeObjectURL(url); } catch(e){}
                        _objectURLs.delete(f);
                        selectedFiles.splice(idx, 1);
                        renderList();
                    });
                    actions.appendChild(removeBtn);
                    item.appendChild(actions);

                    uploadList.appendChild(item);
                });
                confirmBtn.disabled = false;
            }

            function handleFiles(files){
                // merge incoming FileList/array with existing selection
                const arr = Array.from(files || []);
                // simple concat - keep order (you can dedupe by name+size if desired)
                selectedFiles = selectedFiles.concat(arr);
                renderList();
            }

            function escapeHtml(s){ return s.replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }

            if (uploadBtn) uploadBtn.addEventListener('click', function(e){ e.preventDefault(); openModal(); });
            if (closeBtn) closeBtn.addEventListener('click', closeModal);
            if (cancelBtn) cancelBtn.addEventListener('click', closeModal);

            ['dragenter','dragover','dragleave','drop'].forEach(evt=>{ dropZone.addEventListener(evt, e=>{ e.preventDefault(); e.stopPropagation(); }); });
            dropZone.addEventListener('dragover', ()=> dropZone.classList.add('drag-over'));
            dropZone.addEventListener('dragleave', ()=> dropZone.classList.remove('drag-over'));
            dropZone.addEventListener('drop', (e)=>{ dropZone.classList.remove('drag-over'); handleFiles(e.dataTransfer.files); });

            fileInput.addEventListener('change', (e)=>{ handleFiles(e.target.files); });

            confirmBtn.addEventListener('click', ()=>{
                if (!selectedFiles.length) return;
                // Render a preview in the Design Option area (mimic saved-design-preview)
                try {
                    const designSection = document.querySelector('.design-option-section');
                    if (designSection) {
                        // remove any previous client-side preview (keep server-side saved preview intact)
                        const existing = designSection.querySelector('.selected-design-preview');
                        if (existing) existing.remove();

                        const previewWrap = document.createElement('div');
                        previewWrap.className = 'selected-design-preview saved-design-preview';

                        // Thumbnail container
                        const thumbContainer = document.createElement('div');
                        thumbContainer.className = 'saved-design-thumb-list';

                        selectedFiles.forEach((f, i) => {
                            const box = document.createElement('div');
                            box.className = 'sd-thumb-item';

                            if (f.type && f.type.indexOf('image/') === 0) {
                                const img = document.createElement('img');
                                img.className = 'sd-thumb-img';
                                let url = _objectURLs.get(f) || URL.createObjectURL(f);
                                if (!_objectURLs.has(f)) _objectURLs.set(f, url);
                                img.src = url;
                                box.appendChild(img);
                            } else {
                                const icon = document.createElement('div');
                                icon.innerHTML = '<i class="fa-solid fa-file file-icon file-icon-lg"></i>';
                                box.appendChild(icon);
                            }
                            thumbContainer.appendChild(box);
                        });

                        // Meta / actions
                        const meta = document.createElement('div');
                        meta.className = 'saved-design-meta preview-meta';
                        // show only the heading — hide the preview note to keep the UI compact
                        meta.innerHTML = '<strong>Selected design</strong>';

                        // Remove (clear selection) button
                        const removeBtn = document.createElement('button');
                        removeBtn.type = 'button';
                        // smaller variant for inline preview
                        removeBtn.className = 'design-btn small';
                        removeBtn.textContent = 'Remove Design';
                        removeBtn.addEventListener('click', function(){
                            // revoke any object URLs created
                            selectedFiles.forEach(f => { const u = _objectURLs.get(f); try{ if(u) URL.revokeObjectURL(u); }catch(e){} _objectURLs.delete(f); });
                            selectedFiles = [];
                            // remove preview element
                            previewWrap.remove();
                        });

                        const topRow = document.createElement('div');
                        topRow.className = 'preview-toprow';
                        topRow.appendChild(thumbContainer);
                        topRow.appendChild(meta);
                        topRow.appendChild(removeBtn);

                        previewWrap.appendChild(topRow);

                        // insert preview after the design buttons area
                        const insertAfter = designSection.querySelector('.design-buttons') || designSection;
                        insertAfter.parentNode.insertBefore(previewWrap, insertAfter.nextSibling);
                    }
                } catch (err) {
                    console.error('Failed to render selected preview', err);
                }

                // keep the files in memory for further integration (upload or editor); close modal
                console.log('Selected design files:', selectedFiles);
                closeModal();
            });

            document.addEventListener('keydown', (e)=>{ if (e.key==='Escape') closeModal(); });

        })();
    </script>
</body>
</html>
