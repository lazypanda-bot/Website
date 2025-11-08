<?php
// Simple products CRUD API for admin panel
session_start();
// Ensure we buffer output and silence display_errors so accidental PHP warnings
// or HTML don't break the JSON responses consumed by the admin UI.
if(!ini_get('output_buffering')) @ob_start(); else @ob_start();
@ini_set('display_errors','0');
@ini_set('log_errors','1');
@ini_set('error_log', __DIR__ . '/../logs/products_api_error.log');
header('Content-Type: application/json');
require_once '../database.php';

// Ensure auxiliary table products_sub exists for per-product Types/Sizes/Attributes (with optional price)
$hasProductsSub = false;
try {
    $res3 = $conn->query("SHOW TABLES LIKE 'products_sub'");
    if ($res3 && $res3 instanceof mysqli_result && $res3->num_rows > 0) {
        $hasProductsSub = true;
        // Validate required columns; add any missing to be compatible with older schemas
        try {
            $colsRes = $conn->query("SHOW COLUMNS FROM products_sub");
            $have = [];
            if ($colsRes && $colsRes instanceof mysqli_result) {
                while($c=$colsRes->fetch_assoc()) { $have[strtolower($c['Field'])] = true; }
            }
            if (!isset($have['product_id'])) { $conn->query("ALTER TABLE products_sub ADD COLUMN product_id INT NOT NULL DEFAULT 0"); }
            if (!isset($have['kind'])) { $conn->query("ALTER TABLE products_sub ADD COLUMN kind VARCHAR(20) NOT NULL DEFAULT ''"); }
            if (!isset($have['value'])) { $conn->query("ALTER TABLE products_sub ADD COLUMN value VARCHAR(255) NOT NULL DEFAULT ''"); }
            if (!isset($have['price'])) { $conn->query("ALTER TABLE products_sub ADD COLUMN price DECIMAL(10,2) NULL"); }
            // Add helpful indexes if missing
            $conn->query("CREATE UNIQUE INDEX IF NOT EXISTS uniq_prod_kind_val ON products_sub (product_id, kind, value)");
            $conn->query("CREATE INDEX IF NOT EXISTS idx_prod ON products_sub (product_id)");
            $conn->query("CREATE INDEX IF NOT EXISTS idx_kind ON products_sub (kind)");
            // Detect optional wide columns (types/sizes/attributes) for compatibility with existing DBs
            $GLOBALS['PS_HAS_TYPES'] = isset($have['types']);
            $GLOBALS['PS_HAS_SIZES'] = isset($have['sizes']);
            $GLOBALS['PS_HAS_ATTRS'] = isset($have['attributes']);
        } catch (Exception $e) { /* ignore */ }
    } else {
        // try create
        $sqlCreate = "CREATE TABLE IF NOT EXISTS products_sub (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            kind VARCHAR(20) NOT NULL,
            value VARCHAR(255) NOT NULL,
            price DECIMAL(10,2) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_prod_kind_val (product_id, kind, value),
            INDEX idx_prod (product_id),
            INDEX idx_kind (kind)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        if ($conn->query($sqlCreate) === TRUE) { 
            $hasProductsSub = true; 
            $GLOBALS['PS_HAS_TYPES'] = false;
            $GLOBALS['PS_HAS_SIZES'] = false;
            $GLOBALS['PS_HAS_ATTRS'] = false;
        }
    }
} catch (Exception $e) { /* ignore; optional table */ }

// Detect whether products table has a 'variants' column so we can persist it safely
$hasVariants = false;
try {
    $res = $conn->query("SHOW COLUMNS FROM products LIKE 'variants'");
    if ($res && $res instanceof mysqli_result && $res->num_rows > 0) $hasVariants = true;
} catch (Exception $e) { /* ignore */ }
// Detect whether products table has a 'wherepricedepends' column so we can persist price-dependency data
$hasWherePrice = false;
try {
    $res2 = $conn->query("SHOW COLUMNS FROM products LIKE 'wherepricedepends'");
    if ($res2 && $res2 instanceof mysqli_result && $res2->num_rows > 0) $hasWherePrice = true;
} catch (Exception $e) { /* ignore */ }

// Inspect available product columns so queries adapt to different schemas
$productCols = [];
try{
    $colRes = $conn->query("SHOW COLUMNS FROM products");
    if($colRes instanceof mysqli_result){ while($cr=$colRes->fetch_assoc()){ $productCols[strtolower($cr['Field'])] = $cr['Field']; } $colRes->free(); }
}catch(Exception $e){ /* ignore */ }

// Helper flags for common columns (derive adaptable column names)
$hasProductId = isset($productCols['product_id']);
$hasIdCol = isset($productCols['id']);
$pkCol = $hasProductId ? $productCols['product_id'] : ($hasIdCol ? $productCols['id'] : null);
$hasProductName = isset($productCols['product_name']);
$hasNameAlt = isset($productCols['name']);
$nameCol = $hasProductName ? $productCols['product_name'] : ($hasNameAlt ? $productCols['name'] : null);
$hasPriceCol = isset($productCols['price']);
$priceCol = $hasPriceCol ? $productCols['price'] : null;
$hasServiceType = isset($productCols['service_type']);
$hasServiceId = isset($productCols['service_id']);
$serviceCol = $hasServiceType ? $productCols['service_type'] : ($hasServiceId ? $productCols['service_id'] : null);
$hasProductDetails = isset($productCols['product_details']);
$hasDescription = isset($productCols['description']);
$detailsCol = $hasProductDetails ? $productCols['product_details'] : ($hasDescription ? $productCols['description'] : null);
$hasImages = isset($productCols['images']);
$imagesCol = $hasImages ? $productCols['images'] : null;
$hasCreatedAt = isset($productCols['created_at']);

function flush_json($arr, $code = 200){
    http_response_code($code);
    // clear any buffered output (warnings, HTML) so client gets clean JSON
    while(ob_get_level()) @ob_end_clean();
    echo json_encode($arr);
    exit;
}

// (Optional) admin auth check placeholder
// if(!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) { http_response_code(403); flush_json(['status'=>'error','message'=>'Forbidden'],403); }

function fail($msg,$code=400){
    // ensure clean buffer
    while(ob_get_level()) @ob_end_clean();
    http_response_code($code);
    echo json_encode(['status'=>'error','message'=>$msg]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// Utility: fetch products list
if ($method === 'GET' && $action === 'list') {
    $data = [];
    // Build a compatible SELECT list based on available columns
    if (!$pkCol) { flush_json(['status'=>'error','message'=>'products table missing primary key (product_id or id)'],500); }
    $parts = [];
    $parts[] = $pkCol === 'product_id' ? 'product_id' : ($pkCol . ' as product_id');
    $parts[] = $nameCol ? ($nameCol === 'product_name' ? 'product_name' : ($nameCol . ' as product_name')) : "'' as product_name";
    $parts[] = $hasPriceCol ? 'price' : "0 as price";
    // prefer service_type text; if absent fall back to service_id (numeric)
    if ($hasServiceType) $parts[] = 'service_type';
    elseif ($hasServiceId) $parts[] = 'service_id as service_type';
    else $parts[] = "'' as service_type";
    $parts[] = $detailsCol ? ($detailsCol === 'product_details' ? 'product_details' : ($detailsCol . ' as product_details')) : "'' as product_details";
    $parts[] = $imagesCol ? ($imagesCol === 'images' ? 'images' : ($imagesCol . ' as images')) : "'' as images";
    if ($hasVariants) $parts[] = 'variants';
    if ($hasWherePrice) $parts[] = 'wherepricedepends';
    // Order by created_at if available, otherwise by product_id desc
    $orderBy = $hasCreatedAt ? 'created_at DESC' : ($pkCol . ' DESC');
    $selectFields = implode(', ', $parts);
    $q = $conn->query("SELECT " . $selectFields . " FROM products ORDER BY $orderBy");
    if ($q instanceof mysqli_result) {
        while($r=$q->fetch_assoc()) { $data[] = $r; }
    }
    flush_json(['status'=>'ok','products'=>$data]);
}

// List all sub-options grouped by product
if ($method === 'GET' && $action === 'sub_list_all') {
    if(!$hasProductsSub) flush_json(['status'=>'ok','byProduct'=>new stdClass()]);
    $q = $conn->query("SELECT product_id, kind, value, COALESCE(price,0) as price FROM products_sub ORDER BY product_id, kind, value");
    $map = [];
    if ($q instanceof mysqli_result) {
        while($r=$q->fetch_assoc()){
            $pid = (string)$r['product_id'];
            if(!isset($map[$pid])) $map[$pid] = ['types'=>[], 'sizes'=>[], 'attributes'=>[]];
            $k = strtolower($r['kind']);
            if($k==='type') $map[$pid]['types'][] = $r['value'];
            elseif($k==='size') $map[$pid]['sizes'][] = $r['value'];
            else $map[$pid]['attributes'][] = ['name'=>$r['value'], 'price'=>(float)$r['price']];
        }
    }
    flush_json(['status'=>'ok','byProduct'=>$map]);
}

// Create / Update product (supports current images + uploaded files for admin UI)
if ($method === 'POST' && $action === 'save') {
    $id = isset($_POST['product_id']) && ctype_digit($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    if(!$id && isset($_POST['id']) && ctype_digit($_POST['id'])) $id = (int)$_POST['id'];
    $name = trim($_POST['product_name'] ?? ($_POST['name'] ?? ''));
    $service = trim($_POST['service_type'] ?? ($_POST['service_id'] ?? ''));
    $price = isset($_POST['price']) ? (float)$_POST['price'] : 0.0;
    $details = trim($_POST['product_details'] ?? ($_POST['description'] ?? ''));
    // images_current is JSON list of kept images (paths)
    $images_current = [];
    if (!empty($_POST['images_current'])) {
        $decoded = json_decode($_POST['images_current'], true);
        if (is_array($decoded)) $images_current = $decoded;
    }
    // Variants: accept JSON array, or comma-separated string. We'll normalize to JSON array string when saving.
    $variants_raw = '';
    if ($hasVariants && isset($_POST['variants'])) {
        $v = trim($_POST['variants']);
        if ($v !== '') {
            // Try decoding any JSON first (supports arrays or associative objects)
            $decoded = json_decode($v, true);
            if ($decoded === null) {
                // Not JSON — treat as comma-separated string and convert to simple array
                $parts = array_map('trim', explode(',', $v));
                $parts = array_values(array_filter($parts, function($x){ return $x !== ''; }));
                $variants_raw = json_encode(array_values($parts));
            } else {
                // Valid JSON (can be numeric array or associative object). Preserve structure.
                $variants_raw = json_encode($decoded);
            }
            if (strlen($variants_raw) > 200000) fail('Variants payload too large',413);
        } else {
            $variants_raw = json_encode([]);
        }
    }
    // wherepricedepends: optional JSON structure describing which attributes affect price (colors, box, pockets, others)
    $wherepriced_raw = '';
    if ($hasWherePrice && isset($_POST['wherepricedepends'])) {
        $w = trim($_POST['wherepricedepends']);
        if ($w !== '') {
            $decoded = json_decode($w, true);
            if ($decoded === null) {
                // if not valid JSON, save empty array instead of raw string
                $wherepriced_raw = json_encode([]);
            } else {
                $wherepriced_raw = json_encode($decoded);
            }
        } else {
            $wherepriced_raw = json_encode([]);
        }
        if (strlen($wherepriced_raw) > 200000) fail('wherepricedepends payload too large',413);
    }
    // Sanitize images_current: drop any data: URIs or extremely long values which may indicate
    // an in-browser data URL accidentally included (these can blow up DB packet size).
    if (!empty($images_current) && is_array($images_current)) {
        $san = [];
        foreach ($images_current as $imv) {
            if (!is_string($imv)) continue;
            $s = trim($imv);
            if ($s === '') continue;
            // drop data URIs
            if (stripos($s, 'data:') === 0) {
                error_log('products-api: dropped data URI from images_current');
                continue;
            }
            // drop excessively long entries
            if (strlen($s) > 4096) { error_log('products-api: dropped overly long image entry'); continue; }
            $san[] = $s;
        }
        $images_current = $san;
    }
    // images_removed[] contains paths (relative) admin wants to remove
    $images_removed = [];
    if (!empty($_POST['images_removed']) && is_array($_POST['images_removed'])) {
        $images_removed = $_POST['images_removed'];
    }

    if ($name === '' && $nameCol) fail('Product name required');
    if ($price < 0) fail('Price invalid');

    // Handle uploaded files (images_files[]). We'll collect errors and moved paths.
    $upload_errors = [];
    $uploaded_paths = [];
    $targetDirBase = __DIR__ . '/../uploads/products';
    if (!is_dir($targetDirBase)) mkdir($targetDirBase, 0755, true);

    // Helper to process files into a given folder name (relativeName is e.g. '1' or 'tmp')
    $processFilesToFolder = function($folderName) use (&$upload_errors, &$uploaded_paths, $targetDirBase) {
        if (empty($_FILES['images_files'])) return;
        $files = $_FILES['images_files'];
        $folder = $targetDirBase . '/' . $folderName;
        if (!is_dir($folder)) mkdir($folder, 0755, true);
        for ($i=0;$i<count($files['name']);$i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) { $upload_errors[] = "upload_error_{$i}:" . $files['error'][$i]; continue; }
            $tmp = $files['tmp_name'][$i];
            $orig = basename($files['name'][$i]);
            $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) { $upload_errors[] = "invalid_type_{$i}:{$orig}"; continue; }
            $uniq = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            $dest = $folder . '/' . $uniq;
            if (@move_uploaded_file($tmp, $dest)) {
                $rel = 'uploads/products/' . $folderName . '/' . $uniq;
                $uploaded_paths[] = $rel;
            } else {
                $upload_errors[] = "move_failed_{$i}:{$orig}";
            }
        }
    };

    if ($id > 0) {
        // Existing product: move files directly into its folder
        $processFilesToFolder((string)$id);
        // Remove any marked images from images_current and attempt to move them to trash
        $targetTrash = __DIR__ . '/../uploads/trash';
        if (!is_dir($targetTrash)) mkdir($targetTrash, 0755, true);
        $sanitized_current = [];
        foreach ($images_current as $im) {
            // Only remove if explicitly requested; otherwise keep
            if (in_array($im, $images_removed, true)) {
                // move file to trash if it's local (uploads/products/...)
                if (preg_match('#^uploads/products/#', $im)) {
                    $full = __DIR__ . '/../' . $im;
                    if (file_exists($full)) {
                        $dst = $targetTrash . '/' . basename($im);
                        // ensure unique filename in trash
                        $dst = $targetTrash . '/' . time() . '_' . bin2hex(random_bytes(4)) . '_' . basename($im);
                        @rename($full, $dst);
                    }
                }
                // do not add to sanitized_current
                continue;
            }
            $sanitized_current[] = $im;
        }
        $final_images = array_values(array_merge($sanitized_current, $uploaded_paths));
        $images_json = json_encode($final_images);
        // Guard against extremely large payload being bound into SQL (prevents MySQL max_allowed_packet)
        if (strlen($images_json) > 200000) {
            fail('Images payload too large. Remove inline/data images and try again.', 413);
        }
        // Choose prepared statement based on which optional columns exist
        // Build dynamic UPDATE based on available columns
        if(!$pkCol) fail('products table missing primary key (product_id or id)',500);
        $setParts = [];
        $types = '';
        $vals = [];
        if($nameCol){ $setParts[] = "$nameCol=?"; $types.='s'; $vals[] = $name; }
        if($serviceCol){ $setParts[] = "$serviceCol=?"; $types .= ($serviceCol==='service_id' ? 'i' : 's'); $vals[] = ($serviceCol==='service_id' ? (int)$service : $service); }
        if($priceCol){ $setParts[] = "$priceCol=?"; $types.='d'; $vals[] = $price; }
        if($detailsCol){ $setParts[] = "$detailsCol=?"; $types.='s'; $vals[] = $details; }
        if($imagesCol){ $setParts[] = "$imagesCol=?"; $types.='s'; $vals[] = $images_json; }
        if($hasVariants){ $setParts[] = "variants=?"; $types.='s'; $vals[] = $variants_raw; }
        if($hasWherePrice){ $setParts[] = "wherepricedepends=?"; $types.='s'; $vals[] = $wherepriced_raw; }
        if(empty($setParts)) fail('No updatable columns available in products table',500);
        $sql = "UPDATE products SET ".implode(', ',$setParts)." WHERE $pkCol=?";
        $types.='i'; $vals[] = $id;
        $stmt = $conn->prepare($sql);
        if(!$stmt) fail('Prepare failed: '.$conn->error,500);
        $stmt->bind_param($types, ...$vals);
        if(!$stmt->execute()) fail('Update failed: ' . $stmt->error,500);
        $stmt->close();
        // Optionally persist sub-items if provided
        if ($hasProductsSub) {
            save_sub_items($conn, $id, $_POST);
        }
        flush_json(['status'=>'ok','action'=>'updated','id'=>$id,'images'=>$final_images,'upload_errors'=>$upload_errors]);
    } else {
        // New product: if files uploaded, save to tmp first, then insert product to get id, then move tmp files to final folder
        $processFilesToFolder('tmp');
        // Insert product with empty images placeholder for now
        $initial_images_json = json_encode($images_current);
        // Build dynamic INSERT based on available columns
        $cols = [];
        $place = [];
        $types = '';
        $vals = [];
        if($nameCol){ $cols[] = $nameCol; $place[]='?'; $types.='s'; $vals[]=$name; }
        if($serviceCol){ $cols[] = $serviceCol; $place[]='?'; $types.= ($serviceCol==='service_id' ? 'i' : 's'); $vals[] = ($serviceCol==='service_id' ? (int)$service : $service); }
        if($priceCol){ $cols[] = $priceCol; $place[]='?'; $types.='d'; $vals[]=$price; }
        if($detailsCol){ $cols[] = $detailsCol; $place[]='?'; $types.='s'; $vals[]=$details; }
        if($imagesCol){ $cols[] = $imagesCol; $place[]='?'; $types.='s'; $vals[]=$initial_images_json; }
        if($hasVariants){ $cols[]='variants'; $place[]='?'; $types.='s'; $vals[]=$variants_raw; }
        if($hasWherePrice){ $cols[]='wherepricedepends'; $place[]='?'; $types.='s'; $vals[]=$wherepriced_raw; }
        if(empty($cols)) fail('No insertable columns available in products table',500);
        $sql = 'INSERT INTO products (' . implode(',', $cols) . ') VALUES (' . implode(',', $place) . ')';
        $stmt = $conn->prepare($sql);
        if(!$stmt) fail('Prepare failed: ' . $conn->error,500);
        $stmt->bind_param($types, ...$vals);
        if(!$stmt->execute()) fail('Insert failed: ' . $stmt->error,500);
        $newId = $stmt->insert_id;
        $stmt->close();
        // If we have uploaded tmp files, move them to newId folder
        $moved_paths = [];
        if (count($uploaded_paths) > 0) {
            $tmpFolder = $targetDirBase . '/tmp';
            $finalFolder = $targetDirBase . '/' . $newId;
            if (!is_dir($finalFolder)) mkdir($finalFolder, 0755, true);
            foreach ($uploaded_paths as $rel) {
                $filename = basename($rel);
                $srcPath = $tmpFolder . '/' . $filename;
                $dstPath = $finalFolder . '/' . $filename;
                if (@rename($srcPath, $dstPath)) {
                    $moved_paths[] = 'uploads/products/' . $newId . '/' . $filename;
                } else {
                    $upload_errors[] = 'move_tmp_failed:' . $filename;
                }
            }
        }
        // For new product, removed list shouldn't normally apply, but sanitize anyway
        $sanitized_current = [];
        foreach ($images_current as $im) {
            if (in_array($im, $images_removed, true)) {
                // don't add
                continue;
            }
            $sanitized_current[] = $im;
        }
        $final_images = array_values(array_merge($sanitized_current, $moved_paths));
        $images_json = json_encode($final_images);
        if (strlen($images_json) > 200000) {
            fail('Images payload too large. Remove inline/data images and try again.', 413);
        }
        // Update product with final image list
        $stmt2 = $conn->prepare("UPDATE products SET ".$imagesCol."=? WHERE $pkCol=?");
        if($stmt2 && $imagesCol) {
            $stmt2->bind_param('si', $images_json, $newId);
            $stmt2->execute();
            $stmt2->close();
        }
        // Optionally persist sub-items for new product
        if ($hasProductsSub) {
            save_sub_items($conn, $newId, $_POST);
        }
        flush_json(['status'=>'ok','action'=>'inserted','id'=>$newId,'images'=>$final_images,'upload_errors'=>$upload_errors]);
    }
    exit;
}

// Quick add sub-items only (types, sizes, attributes) without touching product fields
if ($method === 'POST' && $action === 'quick_add_sub') {
    $pid = isset($_POST['product_id']) && ctype_digit($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    if ($pid <= 0) fail('Invalid product id');
    if (!$hasProductsSub) fail('products_sub not available on this DB', 500);
    save_sub_items($conn, $pid, $_POST);
    flush_json(['status'=>'ok','action'=>'saved_sub']);
}

// Delete a single sub item (type/size/attribute) for a product
if ($method === 'POST' && $action === 'delete_sub') {
    if (!$hasProductsSub) fail('products_sub not available on this DB', 500);
    $pid = isset($_POST['product_id']) && ctype_digit($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $kind = strtolower(trim($_POST['kind'] ?? ''));
    $value = trim($_POST['value'] ?? '');
    if ($pid<=0 || !$kind || $value==='') fail('Invalid parameters');
    $stmt = $conn->prepare("DELETE FROM products_sub WHERE product_id=? AND kind=? AND value=? LIMIT 1");
    if(!$stmt) fail('Prepare failed: '.$conn->error,500);
    $stmt->bind_param('iss', $pid, $kind, $value);
    if(!$stmt->execute()) fail('Delete failed: '.$stmt->error,500);
    $stmt->close();
    flush_json(['status'=>'ok','action'=>'deleted']);
}

// Delete product
if ($method === 'POST' && $action === 'delete') {
    $id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
    if ($id <= 0) fail('Invalid id');
    if(!$pkCol) fail('products table missing primary key',500);
    $stmt = $conn->prepare("DELETE FROM products WHERE $pkCol=?");
    if(!$stmt) fail('Prepare failed: ' . $conn->error,500);
    $stmt->bind_param('i',$id);
    if(!$stmt->execute()) fail('Delete failed: ' . $stmt->error,500);
    flush_json(['status'=>'ok','action'=>'deleted','id'=>$id]);
    $stmt->close();
    exit;
}

// Add service (distinct service_type value entry) – we just ensure uniqueness reference
if ($method === 'POST' && $action === 'add_service') {
    $serviceName = trim($_POST['service_name'] ?? '');
    if ($serviceName === '') fail('Service name required');
    // Optionally we could persist services in a separate table; for now we just echo success and rely on existing products to show datalist values.
    flush_json(['status'=>'ok','service'=>$serviceName]);
    exit;
}

// Migration: backfill products_sub from existing products (variants / wherepricedepends)
if ($method === 'GET' && $action === 'migrate_sub') {
    if(!$hasProductsSub) fail('products_sub not available on this DB', 500);
    $inserted = ['types'=>0,'sizes'=>0,'attributes'=>0];
    // Track seen values case-insensitively per (kind, product_id) to avoid double inserts within this run
    $seen = ['type'=>[], 'size'=>[], 'attribute'=>[]];
    $selectFields = 'product_id';
    if ($hasVariants) $selectFields .= ', variants';
    if ($hasWherePrice) $selectFields .= ', wherepricedepends';
    $q = $conn->query("SELECT $selectFields FROM products");
    if ($q instanceof mysqli_result) {
        $stmt = $conn->prepare("INSERT INTO products_sub (product_id, kind, value, price) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE price=VALUES(price)");
        $hasTypesCol = !empty($GLOBALS['PS_HAS_TYPES']);
        $hasSizesCol = !empty($GLOBALS['PS_HAS_SIZES']);
        $hasAttrsCol = !empty($GLOBALS['PS_HAS_ATTRS']);
        $updType = $hasTypesCol ? $conn->prepare("UPDATE products_sub SET types=? WHERE product_id=? AND kind='type' AND value=?") : null;
        $updSize = $hasSizesCol ? $conn->prepare("UPDATE products_sub SET sizes=? WHERE product_id=? AND kind='size' AND value=?") : null;
        $updAttr = $hasAttrsCol ? $conn->prepare("UPDATE products_sub SET attributes=? WHERE product_id=? AND kind='attribute' AND value=?") : null;
        if(!$stmt) fail('Prepare failed: '.$conn->error,500);
        while($r=$q->fetch_assoc()){
            $pid = (int)$r['product_id'];
            // Parse variants for types / sizes if present
            if ($hasVariants && !empty($r['variants'])) {
                $vRaw = $r['variants'];
                $decoded = json_decode($vRaw, true);
                $items = [];
                if ($decoded !== null) {
                    $items = $decoded;
                } else {
                    // Fallback: comma-separated list -> treat as types
                    $items = array_filter(array_map('trim', explode(',', $vRaw)));
                }
                if (is_array($items)) {
                    if (!empty($items) && isset($items[0]) && is_array($items[0])) {
                        // Array of objects each possibly containing type/size
                        foreach($items as $it){
                            if(!is_array($it)) continue;
                            $tp = isset($it['type']) ? trim((string)$it['type']) : '';
                            $sz = isset($it['size']) ? trim((string)$it['size']) : '';
                            if($tp!==''){ $lc = strtolower($tp); if(!isset($seen['type'][$pid])) $seen['type'][$pid]=[]; if(!isset($seen['type'][$pid][$lc])){ $kind='type'; $price=0.0; $stmt->bind_param('issd',$pid,$kind,$tp,$price); $stmt->execute(); if($updType){ $updType->bind_param('sis',$tp,$pid,$tp); $updType->execute(); } $seen['type'][$pid][$lc]=true; $inserted['types']++; } }
                            if($sz!==''){ $lc = strtolower($sz); if(!isset($seen['size'][$pid])) $seen['size'][$pid]=[]; if(!isset($seen['size'][$pid][$lc])){ $kind='size'; $price=0.0; $stmt->bind_param('issd',$pid,$kind,$sz,$price); $stmt->execute(); if($updSize){ $updSize->bind_param('sis',$sz,$pid,$sz); $updSize->execute(); } $seen['size'][$pid][$lc]=true; $inserted['sizes']++; } }
                        }
                    } else {
                        // Array of scalars -> treat as types
                        foreach($items as $sv){ if(!is_scalar($sv)) continue; $val = trim((string)$sv); if($val==='') continue; $lc = strtolower($val); if(!isset($seen['type'][$pid])) $seen['type'][$pid]=[]; if(!isset($seen['type'][$pid][$lc])){ $kind='type'; $price=0.0; $stmt->bind_param('issd',$pid,$kind,$val,$price); $stmt->execute(); if($updType){ $updType->bind_param('sis',$val,$pid,$val); $updType->execute(); } $seen['type'][$pid][$lc]=true; $inserted['types']++; } }
                    }
                }
            }
            // Parse wherepricedepends for attributes (others[] list with name/price)
            if ($hasWherePrice && !empty($r['wherepricedepends'])) {
                $wRaw = $r['wherepricedepends'];
                $w = json_decode($wRaw, true);
                if (is_array($w)) {
                    $others = [];
                    // Patterns: either top-level has 'others', or nested arrays have 'others'
                    if (isset($w['others']) && is_array($w['others'])) {
                        $others = $w['others'];
                    } else {
                        foreach($w as $entry){ if(is_array($entry) && isset($entry['others']) && is_array($entry['others'])){ foreach($entry['others'] as $o){ $others[]=$o; } } }
                    }
                    foreach($others as $o){
                        if(!is_array($o)) continue;
                        $nm = isset($o['name']) ? trim((string)$o['name']) : '';
                        if($nm==='') continue;
                        $pr = isset($o['price']) ? (float)$o['price'] : 0.0;
                        $lc = strtolower($nm);
                        if(!isset($seen['attribute'][$pid])) $seen['attribute'][$pid]=[];
                        if(!isset($seen['attribute'][$pid][$lc])){
                            $kind='attribute';
                            $stmt->bind_param('issd',$pid,$kind,$nm,$pr);
                            $stmt->execute();
                            if($updAttr){ $updAttr->bind_param('sis',$nm,$pid,$nm); $updAttr->execute(); }
                            $seen['attribute'][$pid][$lc]=true;
                            $inserted['attributes']++;
                        }
                    }
                }
            }
        }
        $stmt->close();
        if($updType) $updType->close();
        if($updSize) $updSize->close();
        if($updAttr) $updAttr->close();
    }
    flush_json(['status'=>'ok','migrated'=>$inserted]);
}

fail('Unsupported action',400);

// Helpers
function save_sub_items($conn, $pid, $post){
    // Expect JSON arrays in sub_types, sub_sizes, sub_attrs (name, price)
    $types = [];$sizes=[];$attrs=[];
    if(isset($post['sub_types'])){ $t = json_decode($post['sub_types'], true); if(is_array($t)) $types = $t; }
    if(isset($post['sub_sizes'])){ $t = json_decode($post['sub_sizes'], true); if(is_array($t)) $sizes = $t; }
    if(isset($post['sub_attrs'])){ $t = json_decode($post['sub_attrs'], true); if(is_array($t)) $attrs = $t; }
    if(empty($types) && empty($sizes) && empty($attrs)) return;
    // Prepare statements for base upsert
    $stmt = $conn->prepare("INSERT INTO products_sub (product_id, kind, value, price) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE price=VALUES(price)");
    if(!$stmt) return;
    // Optional updates for wide columns if they exist
    $hasTypesCol = !empty($GLOBALS['PS_HAS_TYPES']);
    $hasSizesCol = !empty($GLOBALS['PS_HAS_SIZES']);
    $hasAttrsCol = !empty($GLOBALS['PS_HAS_ATTRS']);
    $updType = $hasTypesCol ? $conn->prepare("UPDATE products_sub SET types=? WHERE product_id=? AND kind='type' AND value=?") : null;
    $updSize = $hasSizesCol ? $conn->prepare("UPDATE products_sub SET sizes=? WHERE product_id=? AND kind='size' AND value=?") : null;
    $updAttr = $hasAttrsCol ? $conn->prepare("UPDATE products_sub SET attributes=? WHERE product_id=? AND kind='attribute' AND value=?") : null;
    // types
    foreach($types as $tv){
        $val = trim((string)$tv);
        if($val==='') continue;
        $kind='type'; $price = 0.0;
        $stmt->bind_param('issd', $pid, $kind, $val, $price);
        $stmt->execute();
        if($updType){ $updType->bind_param('sis', $val, $pid, $val); $updType->execute(); }
    }
    // sizes
    foreach($sizes as $sv){
        $val = trim((string)$sv);
        if($val==='') continue;
        $kind='size'; $price = 0.0;
        $stmt->bind_param('issd', $pid, $kind, $val, $price);
        $stmt->execute();
        if($updSize){ $updSize->bind_param('sis', $val, $pid, $val); $updSize->execute(); }
    }
    // attributes with price
    foreach($attrs as $av){
        if(!is_array($av)) continue;
        $val = trim((string)($av['name'] ?? ''));
        if($val==='') continue;
        $pr = isset($av['price']) ? (float)$av['price'] : 0.0;
        $kind='attribute';
        $stmt->bind_param('issd', $pid, $kind, $val, $pr);
        $stmt->execute();
        if($updAttr){ $updAttr->bind_param('sis', $val, $pid, $val); $updAttr->execute(); }
    }
    $stmt->close();
    if($updType) $updType->close();
    if($updSize) $updSize->close();
    if($updAttr) $updAttr->close();
}
?>
