<?php
// Simple products CRUD API for admin panel
session_start();
header('Content-Type: application/json');
require_once '../database.php';

// (Optional) admin auth check placeholder
// if(!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) { http_response_code(403); echo json_encode(['error'=>'Forbidden']); exit; }

function fail($msg,$code=400){ http_response_code($code); echo json_encode(['status'=>'error','message'=>$msg]); exit; }

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// Utility: fetch products list
if ($method === 'GET' && $action === 'list') {
    $data = [];
    $q = $conn->query("SELECT product_id, product_name, price, service_type, product_details, images, created_at FROM products ORDER BY created_at DESC");
    if ($q instanceof mysqli_result) {
        while($r=$q->fetch_assoc()) { $data[] = $r; }
    }
    echo json_encode(['status'=>'ok','products'=>$data]);
    exit;
}

// Create / Update product (supports current images + uploaded files for admin UI)
if ($method === 'POST' && $action === 'save') {
    $id = isset($_POST['product_id']) && ctype_digit($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $name = trim($_POST['product_name'] ?? '');
    $service = trim($_POST['service_type'] ?? '');
    $price = isset($_POST['price']) ? (float)$_POST['price'] : 0.0;
    $details = trim($_POST['product_details'] ?? '');
    // images_current is JSON list of kept images (paths)
    $images_current = [];
    if (!empty($_POST['images_current'])) {
        $decoded = json_decode($_POST['images_current'], true);
        if (is_array($decoded)) $images_current = $decoded;
    }
    // images_removed[] contains paths (relative) admin wants to remove
    $images_removed = [];
    if (!empty($_POST['images_removed']) && is_array($_POST['images_removed'])) {
        $images_removed = $_POST['images_removed'];
    }

    if ($name === '') fail('Product name required');
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
        $stmt = $conn->prepare("UPDATE products SET product_name=?, service_type=?, price=?, product_details=?, images=? WHERE product_id=?");
        if(!$stmt) fail('Prepare failed: ' . $conn->error,500);
        $stmt->bind_param('ssdssi', $name,$service,$price,$details,$images_json,$id);
        if(!$stmt->execute()) fail('Update failed: ' . $stmt->error,500);
        $stmt->close();
        echo json_encode(['status'=>'ok','action'=>'updated','id'=>$id,'images'=>$final_images,'upload_errors'=>$upload_errors]);
    } else {
        // New product: if files uploaded, save to tmp first, then insert product to get id, then move tmp files to final folder
        $processFilesToFolder('tmp');
        // Insert product with empty images placeholder for now
        $initial_images_json = json_encode($images_current);
        $stmt = $conn->prepare("INSERT INTO products (product_name, service_type, price, product_details, images) VALUES (?,?,?,?,?)");
        if(!$stmt) fail('Prepare failed: ' . $conn->error,500);
        $stmt->bind_param('ssdss', $name,$service,$price,$details,$initial_images_json);
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
        // Update product with final image list
        $stmt2 = $conn->prepare("UPDATE products SET images=? WHERE product_id=?");
        if($stmt2) {
            $stmt2->bind_param('si', $images_json, $newId);
            $stmt2->execute();
            $stmt2->close();
        }
        echo json_encode(['status'=>'ok','action'=>'inserted','id'=>$newId,'images'=>$final_images,'upload_errors'=>$upload_errors]);
    }
    exit;
}

// Delete product
if ($method === 'POST' && $action === 'delete') {
    $id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    if ($id <= 0) fail('Invalid id');
    $stmt = $conn->prepare("DELETE FROM products WHERE product_id=?");
    if(!$stmt) fail('Prepare failed: ' . $conn->error,500);
    $stmt->bind_param('i',$id);
    if(!$stmt->execute()) fail('Delete failed: ' . $stmt->error,500);
    echo json_encode(['status'=>'ok','action'=>'deleted','id'=>$id]);
    $stmt->close();
    exit;
}

// Add service (distinct service_type value entry) – we just ensure uniqueness reference
if ($method === 'POST' && $action === 'add_service') {
    $serviceName = trim($_POST['service_name'] ?? '');
    if ($serviceName === '') fail('Service name required');
    // Optionally we could persist services in a separate table; for now we just echo success and rely on existing products to show datalist values.
    echo json_encode(['status'=>'ok','service'=>$serviceName]);
    exit;
}

fail('Unsupported action',400);
?>