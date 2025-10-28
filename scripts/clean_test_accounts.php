<?php
// Remove obvious test accounts created during debugging.
// Run from CLI with: php scripts/clean_test_accounts.php
// It will delete the customer with email 'user@example.com' and name 'Demo User', and any admin accounts with example.com emails.

require_once __DIR__ . '/../database.php';

if (!($conn instanceof mysqli)) {
    fwrite(STDERR, "No mysqli connection available.\n");
    exit(2);
}

$deleted = [];
// Clean customer/test account
$emailToRemove = 'user@example.com';
$nameToRemove = 'Demo User';

// Defensive: ensure ACCOUNT_TABLE and ACCOUNT_EMAIL_COL exist
if (defined('ACCOUNT_TABLE') && defined('ACCOUNT_EMAIL_COL') && defined('ACCOUNT_NAME_COL')) {
    $sql = "SELECT " . ACCOUNT_ID_COL . " AS id, " . ACCOUNT_EMAIL_COL . " AS email, " . ACCOUNT_NAME_COL . " AS name FROM " . ACCOUNT_TABLE . " WHERE " . ACCOUNT_EMAIL_COL . " = ? OR " . ACCOUNT_NAME_COL . " = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('ss', $emailToRemove, $nameToRemove);
        $stmt->execute();
        $res = $stmt->get_result();
        $found = [];
        while ($r = $res->fetch_assoc()) {
            $found[] = $r;
        }
        $stmt->close();
        if (count($found) > 0) {
            foreach ($found as $row) {
                $delSql = "DELETE FROM " . ACCOUNT_TABLE . " WHERE " . ACCOUNT_ID_COL . " = ? LIMIT 1";
                if ($d = $conn->prepare($delSql)) {
                    $d->bind_param('i', $row['id']);
                    if ($d->execute()) {
                        $deleted[] = ['table' => ACCOUNT_TABLE, 'id' => $row['id'], 'email' => $row['email'], 'name' => $row['name']];
                    }
                    $d->close();
                }
            }
        }
    }
}

// Clean admin accounts created for testing (likely use example.com emails)
$adminDeleted = [];
if ($res = $conn->query("SHOW TABLES LIKE 'admin'")) {
    if ($res->num_rows > 0) {
        $res->free();
        $q = "SELECT admin_id, email, name FROM admin WHERE email LIKE '%@example.com'";
        if ($r = $conn->query($q)) {
            while ($a = $r->fetch_assoc()) {
                $aid = (int)$a['admin_id'];
                $admDel = $conn->prepare('DELETE FROM admin WHERE admin_id = ? LIMIT 1');
                if ($admDel) {
                    $admDel->bind_param('i', $aid);
                    if ($admDel->execute()) {
                        $adminDeleted[] = $a;
                    }
                    $admDel->close();
                }
            }
            $r->free();
        }
    } else {
        if ($res) $res->free();
    }
}

// Output results
if (empty($deleted) && empty($adminDeleted)) {
    echo "No matching test accounts found/removed.\n";
} else {
    if (!empty($deleted)) {
        echo "Deleted customer accounts:\n";
        foreach ($deleted as $d) {
            echo " - {$d['table']} id={$d['id']} email={$d['email']} name={$d['name']}\n";
        }
    }
    if (!empty($adminDeleted)) {
        echo "Deleted admin accounts:\n";
        foreach ($adminDeleted as $a) {
            echo " - admin id={$a['admin_id']} email={$a['email']} name={$a['name']}\n";
        }
    }
}

?>