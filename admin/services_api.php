<?php
session_start();
header('Content-Type: application/json');
// Capture any accidental output so we can return clean JSON
if(!ini_get('output_buffering')) @ob_start(); else @ob_start();

// Prevent PHP warnings from being sent to the client and breaking JSON
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
@ini_set('error_log', __DIR__ . '/../logs/services_api_error.log');

function flush_json($arr){
    // clear any previous output (warnings, HTML, etc.) so client gets valid JSON
    while(ob_get_level()) @ob_end_clean();
    echo json_encode($arr);
    exit;
}
require_once '../database.php';

// if(!isset($_SESSION['is_admin'])) { http_response_code(403); echo json_encode(['status'=>'error','message'=>'Forbidden']); exit; }

function fail($m,$c=400){ http_response_code($c); // ensure clean buffer
    while(ob_get_level()) @ob_end_clean();
    echo json_encode(['status'=>'error','message'=>$m]); exit; }

// Ensure services table exists
$createSql = "CREATE TABLE IF NOT EXISTS services (
  service_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL UNIQUE,
  description TEXT NULL,
    image VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($createSql);

// Ensure image column exists (safe on older MySQL by checking information_schema)
$colRes = $conn->query("SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='services' AND COLUMN_NAME='image'");
if($colRes instanceof mysqli_result){ $colRow = $colRes->fetch_assoc(); if((int)$colRow['cnt'] === 0){ @mysqli_query($conn, "ALTER TABLE services ADD COLUMN image VARCHAR(255) NULL"); } }

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($action === 'list') {
    $services = [];
    $res = $conn->query("SELECT service_id, name, description, image, created_at FROM services ORDER BY name ASC");
    if ($res instanceof mysqli_result) {
        while($r=$res->fetch_assoc()) { $services[]=$r; }
    }
    // Auto-seed from existing products if empty
    if (count($services) === 0) {
        if ($prodRes = $conn->query("SELECT DISTINCT service_type FROM products WHERE service_type IS NOT NULL AND service_type <> ''")) {
            $insertStmt = $conn->prepare("INSERT IGNORE INTO services (name) VALUES (?)");
            if ($insertStmt) {
                while($pr = $prodRes->fetch_assoc()) {
                    $n = trim($pr['service_type']);
                    if ($n !== '') { $insertStmt->bind_param('s',$n); $insertStmt->execute(); }
                }
                $insertStmt->close();
            }
            $prodRes->close();
        }
        // Re-query after seeding
        $services = [];
        if ($res2 = $conn->query("SELECT service_id, name, description, image, created_at FROM services ORDER BY name ASC")) {
            while($r2=$res2->fetch_assoc()) { $services[]=$r2; }
        }
    }
    flush_json(['status'=>'ok','services'=>$services]);
}

if ($action === 'add') {
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    if ($name === '') fail('Service name required');
    // Handle optional uploaded image
    $imagePath = null;
    if (!empty($_FILES['service_image']) && isset($_FILES['service_image']['tmp_name']) && is_uploaded_file($_FILES['service_image']['tmp_name'])) {
        $up = $_FILES['service_image'];
        if ($up['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($up['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','webp'];
            if (!in_array($ext, $allowed)) { /* ignore image if invalid type */ $ext = null; }
            if ($ext) {
                $dir = __DIR__ . '/../uploads/services';
                if (!is_dir($dir)) @mkdir($dir, 0755, true);
                $basename = preg_replace('/[^a-z0-9\-_.]/i','', pathinfo($up['name'], PATHINFO_FILENAME));
                // random_bytes may not be available in some environments; fallback to uniqid
                try {
                    $rand = function_exists('random_bytes') ? bin2hex(random_bytes(4)) : substr(uniqid(),-8);
                } catch(Throwable $e) {
                    $rand = substr(uniqid(),-8);
                }
                $filename = $basename . '-' . time() . '-' . $rand . '.' . $ext;
                $dest = $dir . '/' . $filename;
                if (move_uploaded_file($up['tmp_name'], $dest)) {
                    $imagePath = 'uploads/services/' . $filename;
                }
            }
        }
    }

    // Insert with or without image
    if ($imagePath !== null) {
        $stmt = $conn->prepare("INSERT INTO services (name, description, image) VALUES (?,?,?)");
        if(!$stmt) fail('Prepare failed: '.$conn->error,500);
        $stmt->bind_param('sss',$name,$desc,$imagePath);
    } else {
        $stmt = $conn->prepare("INSERT INTO services (name, description) VALUES (?,?)");
        if(!$stmt) fail('Prepare failed: '.$conn->error,500);
        $stmt->bind_param('ss',$name,$desc);
    }
    if(!$stmt->execute()) {
        if ($conn->errno == 1062) fail('Service already exists');
        fail('Insert failed: '.$stmt->error,500);
    }
    flush_json(['status'=>'ok','action'=>'inserted','id'=>$stmt->insert_id]);
    $stmt->close();
    exit;
}

if ($action === 'edit') {
    $id = (int)($_POST['service_id'] ?? 0);
    if ($id <= 0) fail('Invalid id');
    $name = trim($_POST['name'] ?? '');
    if ($name === '') fail('Service name required');

    // Load existing image (if any)
    $oldImg = null;
    $g = $conn->prepare("SELECT image FROM services WHERE service_id=? LIMIT 1");
    if ($g) { $g->bind_param('i',$id); if($g->execute()){ $res = $g->get_result(); $r = $res ? $res->fetch_assoc() : null; if($r) $oldImg = $r['image']; } $g->close(); }

    // Handle optional new uploaded image (same logic as add)
    $imagePath = null;
    if (!empty($_FILES['service_image']) && isset($_FILES['service_image']['tmp_name']) && is_uploaded_file($_FILES['service_image']['tmp_name'])) {
        $up = $_FILES['service_image'];
        if ($up['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($up['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','webp'];
            if (!in_array($ext, $allowed)) { $ext = null; }
            if ($ext) {
                $dir = __DIR__ . '/../uploads/services';
                if (!is_dir($dir)) @mkdir($dir, 0755, true);
                $basename = preg_replace('/[^a-z0-9\-_.]/i','', pathinfo($up['name'], PATHINFO_FILENAME));
                try { $rand = function_exists('random_bytes') ? bin2hex(random_bytes(4)) : substr(uniqid(),-8); } catch(Throwable $e) { $rand = substr(uniqid(),-8); }
                $filename = $basename . '-' . time() . '-' . $rand . '.' . $ext;
                $dest = $dir . '/' . $filename;
                if (move_uploaded_file($up['tmp_name'], $dest)) {
                    $imagePath = 'uploads/services/' . $filename;
                }
            }
        }
    }

    // If new image uploaded and old image exists in uploads/services, move old to trash
    if ($imagePath !== null && $oldImg) {
        $old = $oldImg;
        if (strpos($old, 'uploads/services/') !== false) {
            $oldPath = __DIR__ . '/../' . $old;
            if (is_file($oldPath)) {
                $trashDir = __DIR__ . '/../uploads/trash'; if (!is_dir($trashDir)) @mkdir($trashDir,0755,true);
                $tname = basename($old);
                @rename($oldPath, $trashDir . '/' . time() . '_' . $tname);
            }
        }
    }

    // Update name and image (if provided)
    if ($imagePath !== null) {
        $stmt = $conn->prepare("UPDATE services SET name=?, image=? WHERE service_id=?");
        if(!$stmt) fail('Prepare failed: '.$conn->error,500);
        $stmt->bind_param('ssi',$name,$imagePath,$id);
    } else {
        $stmt = $conn->prepare("UPDATE services SET name=? WHERE service_id=?");
        if(!$stmt) fail('Prepare failed: '.$conn->error,500);
        $stmt->bind_param('si',$name,$id);
    }
    if(!$stmt->execute()) fail('Update failed: '.$stmt->error,500);
    flush_json(['status'=>'ok','action'=>'updated','id'=>$id]);
    $stmt->close();
    exit;
}

if ($action === 'delete') {
    $id = (int)($_POST['service_id'] ?? 0);
    if ($id <= 0) fail('Invalid id');
    // Load service name for dependency check
    $getName = $conn->prepare("SELECT name FROM services WHERE service_id=? LIMIT 1");
    if (!$getName) fail('Prepare failed: '.$conn->error,500);
    $getName->bind_param('i', $id);
    if (!$getName->execute()) { $getName->close(); fail('Query failed: '.$getName->error,500); }
    $res = $getName->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $getName->close();
    if (!$row) fail('Service not found',404);
    $serviceName = trim($row['name']);

    // Count products that reference this service by name
    $countStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM products WHERE TRIM(service_type) = ?");
    if (!$countStmt) fail('Prepare failed: '.$conn->error,500);
    $countStmt->bind_param('s', $serviceName);
    if (!$countStmt->execute()) { $countStmt->close(); fail('Query failed: '.$countStmt->error,500); }
    $countRes = $countStmt->get_result();
    $countRow = $countRes ? $countRes->fetch_assoc() : null;
    $countStmt->close();
    $refCount = (int)($countRow['cnt'] ?? 0);
    if ($refCount > 0) {
        fail("Cannot delete — {$refCount} products still reference this service.");
    }

    // Safe to delete
    $stmt = $conn->prepare("DELETE FROM services WHERE service_id=?");
    if(!$stmt) fail('Prepare failed: '.$conn->error,500);
    $stmt->bind_param('i',$id);
    if(!$stmt->execute()) fail('Delete failed: '.$stmt->error,500);
    flush_json(['status'=>'ok','action'=>'deleted']);
    $stmt->close();
    exit;
}

fail('Unsupported action');
?>