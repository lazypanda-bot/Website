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

// Create / Update product
if ($method === 'POST' && $action === 'save') {
    $id = isset($_POST['product_id']) && ctype_digit($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $name = trim($_POST['product_name'] ?? '');
    $service = trim($_POST['service_type'] ?? '');
    $price = isset($_POST['price']) ? (float)$_POST['price'] : 0.0;
    $details = trim($_POST['product_details'] ?? '');
    $images = trim($_POST['images'] ?? '');
    if ($name === '') fail('Product name required');
    if ($price < 0) fail('Price invalid');
    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE products SET product_name=?, service_type=?, price=?, product_details=?, images=? WHERE product_id=?");
        if(!$stmt) fail('Prepare failed: ' . $conn->error,500);
        $stmt->bind_param('ssdssi', $name,$service,$price,$details,$images,$id);
        if(!$stmt->execute()) fail('Update failed: ' . $stmt->error,500);
        echo json_encode(['status'=>'ok','action'=>'updated','id'=>$id]);
        $stmt->close();
    } else {
        $stmt = $conn->prepare("INSERT INTO products (product_name, service_type, price, product_details, images) VALUES (?,?,?,?,?)");
        if(!$stmt) fail('Prepare failed: ' . $conn->error,500);
        $stmt->bind_param('ssdss', $name,$service,$price,$details,$images);
        if(!$stmt->execute()) fail('Insert failed: ' . $stmt->error,500);
        echo json_encode(['status'=>'ok','action'=>'inserted','id'=>$stmt->insert_id]);
        $stmt->close();
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