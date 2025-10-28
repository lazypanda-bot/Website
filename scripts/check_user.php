<?php
require_once __DIR__ . '/../database.php';
$email = 'user@example.com';
if (!($conn instanceof mysqli)) { echo "No mysqli connection\n"; exit(2); }
if (!defined('ACCOUNT_TABLE') || !defined('ACCOUNT_EMAIL_COL')) { echo "Schema constants missing\n"; exit(3); }
$sql = "SELECT * FROM " . ACCOUNT_TABLE . " WHERE " . ACCOUNT_EMAIL_COL . " = ? LIMIT 1";
if (!$stmt = $conn->prepare($sql)) { echo "Prepare failed: (" . $conn->errno . ") " . $conn->error . "\n"; exit(4); }
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    echo "Found row:\n";
    foreach ($row as $k=>$v) {
        echo "$k => $v\n";
    }
} else {
    echo "No row for $email\n";
}
$stmt->close();

?>