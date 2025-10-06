<?php
session_start();
header('Content-Type: application/json');
require_once '../database.php';

// if(!isset($_SESSION['is_admin'])) { http_response_code(403); echo json_encode(['status'=>'error','message'=>'Forbidden']); exit; }

function fail($m,$c=400){ http_response_code($c); echo json_encode(['status'=>'error','message'=>$m]); exit; }

// Ensure services table exists
$createSql = "CREATE TABLE IF NOT EXISTS services (
  service_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL UNIQUE,
  description TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($createSql);

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($action === 'list') {
    $services = [];
    $res = $conn->query("SELECT service_id, name, description, created_at FROM services ORDER BY name ASC");
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
        if ($res2 = $conn->query("SELECT service_id, name, description, created_at FROM services ORDER BY name ASC")) {
            while($r2=$res2->fetch_assoc()) { $services[]=$r2; }
        }
    }
    echo json_encode(['status'=>'ok','services'=>$services]);
    exit;
}

if ($action === 'add') {
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    if ($name === '') fail('Service name required');
    $stmt = $conn->prepare("INSERT INTO services (name, description) VALUES (?,?)");
    if(!$stmt) fail('Prepare failed: '.$conn->error,500);
    $stmt->bind_param('ss',$name,$desc);
    if(!$stmt->execute()) {
        if ($conn->errno == 1062) fail('Service already exists');
        fail('Insert failed: '.$stmt->error,500);
    }
    echo json_encode(['status'=>'ok','action'=>'inserted','id'=>$stmt->insert_id]);
    $stmt->close();
    exit;
}

if ($action === 'delete') {
    $id = (int)($_POST['service_id'] ?? 0);
    if ($id <= 0) fail('Invalid id');
    $stmt = $conn->prepare("DELETE FROM services WHERE service_id=?");
    if(!$stmt) fail('Prepare failed: '.$conn->error,500);
    $stmt->bind_param('i',$id);
    if(!$stmt->execute()) fail('Delete failed: '.$stmt->error,500);
    echo json_encode(['status'=>'ok','action'=>'deleted']);
    $stmt->close();
    exit;
}

fail('Unsupported action');
?>