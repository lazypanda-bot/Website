<?php
// Migration script: add cart.designoption_id (nullable) + FK to designoption if missing.
// Run via browser or CLI: http://localhost/Website/scripts/add_cart_design_column.php
// Safe to re-run: checks and only applies when column absent.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../database.php';

header('Content-Type: text/plain');

if (!$conn || $conn->connect_error) {
    http_response_code(500);
    echo 'DB connection failed: ' . ($conn ? $conn->connect_error : 'no $conn');
    exit;
}

function hasColumn(mysqli $conn, string $table, string $column): bool {
    $sql = "SHOW COLUMNS FROM `{$table}` LIKE ?";
    if (!$stmt = $conn->prepare($sql)) return false;
    $stmt->bind_param('s', $column);
    $ok = $stmt->execute();
    if (!$ok) { $stmt->close(); return false; }
    $res = $stmt->get_result();
    $exists = ($res && $res->num_rows > 0);
    if ($res) $res->free();
    $stmt->close();
    return $exists;
}

function hasTable(mysqli $conn, string $table): bool {
    $res = $conn->query("SHOW TABLES LIKE '".$conn->real_escape_string($table)."'");
    if (!$res) return false;
    $exists = ($res->num_rows > 0);
    $res->free();
    return $exists;
}

$table = 'cart';
$col   = 'designoption_id';

if (!hasTable($conn, $table)) {
    echo "Table `{$table}` not found. Nothing to do.\n";
    exit;
}

if (hasColumn($conn, $table, $col)) {
    echo "Column `{$table}`.`{$col}` already exists. No changes applied.\n";
    exit;
}

$steps = [
    // add the nullable column
    "ALTER TABLE `cart` ADD COLUMN `designoption_id` INT UNSIGNED NULL AFTER `quantity`",
    // index for joins
    "ALTER TABLE `cart` ADD INDEX `idx_cart_design`(`designoption_id`)",
];

// add FK only if designoption table exists
if (hasTable($conn, 'designoption')) {
    $steps[] = "ALTER TABLE `cart` ADD CONSTRAINT `fk_cart_designoption` FOREIGN KEY (`designoption_id`) REFERENCES `designoption`(`designoption_id`) ON DELETE SET NULL ON UPDATE CASCADE";
}

foreach ($steps as $sql) {
    echo "> $sql\n";
    if (!$conn->query($sql)) {
        // tolerate duplicate FK/idx on re-run
        $err = $conn->error;
        if (stripos($err, 'duplicate') !== false || stripos($err, 'exists') !== false) {
            echo "  - skipped (already applied)\n";
            continue;
        }
        http_response_code(500);
        echo "  ! failed: $err\n";
        exit;
    }
    echo "  + ok\n";
}

echo "\nMigration completed.\n";
