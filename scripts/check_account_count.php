<?php
require_once __DIR__ . '/../database.php';
header('Content-Type: text/plain');
echo "ACCOUNT_TABLE=" . (defined('ACCOUNT_TABLE')?ACCOUNT_TABLE:'(undef)') . "\n";
echo "ACCOUNT_ID_COL=" . (defined('ACCOUNT_ID_COL')?ACCOUNT_ID_COL:'(undef)') . "\n";
echo "ACCOUNT_EMAIL_COL=" . (defined('ACCOUNT_EMAIL_COL')?ACCOUNT_EMAIL_COL:'(undef)') . "\n";
echo "ACCOUNT_PASS_COL=" . (defined('ACCOUNT_PASS_COL')?ACCOUNT_PASS_COL:'(undef)') . "\n";
if (!defined('ACCOUNT_TABLE')) { echo "No account table defined\n"; exit(0); }
$res = $conn->query('SELECT COUNT(*) AS c FROM ' . ACCOUNT_TABLE);
if ($res) {
    $r = $res->fetch_assoc();
    echo "ROW_COUNT=" . ($r['c'] ?? '0') . "\n";
} else {
    echo "QUERY_ERROR=" . $conn->error . "\n";
}
?>