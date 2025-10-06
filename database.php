<?php
// Centralized database connection & adaptive schema mapping for account table.
// Target (ideal) columns: customer_id, name, address, email, facebook_account, cust_password, profile_pic, registration__date, phone_number
// Falls back automatically if some legacy columns differ (e.g., username instead of name, id instead of customer_id, password instead of cust_password, etc.).

if (!isset($conn) || !($conn instanceof mysqli)) {
    $conn = new mysqli('localhost', 'root', '', 'website');
    if ($conn->connect_error) {
        die('Database connection failed: ' . $conn->connect_error);
    }
    $conn->set_charset('utf8mb4');
}

// Detect actual table name (customers vs customer) once
if (!defined('ACCOUNT_TABLE')) {
    // Prefer singular 'customer' table if it exists; otherwise fallback to plural 'customers'.
    $tableName = 'customer';
    $chkSingular = $conn->query("SHOW TABLES LIKE 'customer'");
    if (!$chkSingular || $chkSingular->num_rows === 0) {
        $chkPlural = $conn->query("SHOW TABLES LIKE 'customers'");
        if ($chkPlural && $chkPlural->num_rows > 0) {
            $tableName = 'customers';
        }
    }
    define('ACCOUNT_TABLE', $tableName);
}

// Fetch column names present in the chosen table (if it exists) for adaptive mapping.
$availableCols = [];
$colResult = $conn->query('SHOW COLUMNS FROM ' . ACCOUNT_TABLE);
if ($colResult instanceof mysqli_result) {
    while ($row = $colResult->fetch_assoc()) {
        $availableCols[strtolower($row['Field'])] = $row['Field']; // preserve original case
    }
    $colResult->free();
}

// Helper to pick the first existing column from a preference list.
function pickCol(array $candidates, array $available, $default) {
    foreach ($candidates as $c) {
        $key = strtolower($c);
        if (isset($available[$key])) return $available[$key];
    }
    return $default; // fallback even if missing (will surface an SQL error prompting inspection)
}

// Determine mappings with graceful fallbacks.
$idCol       = pickCol(['customer_id','id','cust_id','user_id'], $availableCols, 'customer_id');
$nameCol     = pickCol(['name','username','full_name','fullname'], $availableCols, 'name');
$emailCol    = pickCol(['email','email_address','user_email'], $availableCols, 'email');
$passCol     = pickCol(['cust_password','password','pass','user_password'], $availableCols, 'cust_password');
$addrCol     = pickCol(['address','addr','address_line','home_address'], $availableCols, 'address');
$phoneCol    = pickCol(['phone_number','phone','contact_number','mobile','mobile_number'], $availableCols, 'phone_number');
$createdCol  = pickCol(['registration__date','registration_date','created_at','created','date_created'], $availableCols, 'registration__date');
$avatarCol   = pickCol(['profile_pic','profilepicture','avatar','profile_image','avatar_path'], $availableCols, 'profile_pic');

if (!defined('ACCOUNT_ID_COL')) define('ACCOUNT_ID_COL', $idCol);
if (!defined('ACCOUNT_NAME_COL')) define('ACCOUNT_NAME_COL', $nameCol);
if (!defined('ACCOUNT_EMAIL_COL')) define('ACCOUNT_EMAIL_COL', $emailCol);
if (!defined('ACCOUNT_PASS_COL')) define('ACCOUNT_PASS_COL', $passCol);
if (!defined('ACCOUNT_ADDRESS_COL')) define('ACCOUNT_ADDRESS_COL', $addrCol);
if (!defined('ACCOUNT_PHONE_COL')) define('ACCOUNT_PHONE_COL', $phoneCol);
if (!defined('ACCOUNT_CREATED_COL')) define('ACCOUNT_CREATED_COL', $createdCol);
if (!defined('ACCOUNT_AVATAR_COL')) define('ACCOUNT_AVATAR_COL', $avatarCol);

// Optional debug: append ?debug_schema=1 to any page including this file to view resolved mappings (admin/dev use only)
if (!headers_sent() && isset($_GET['debug_schema'])) {
    header('Content-Type: text/plain');
    echo "ACCOUNT_TABLE=" . ACCOUNT_TABLE . "\n";
    echo "ACCOUNT_ID_COL=" . ACCOUNT_ID_COL . "\n";
    echo "ACCOUNT_NAME_COL=" . ACCOUNT_NAME_COL . "\n";
    echo "ACCOUNT_EMAIL_COL=" . ACCOUNT_EMAIL_COL . "\n";
    echo "ACCOUNT_PASS_COL=" . ACCOUNT_PASS_COL . "\n";
    echo "ACCOUNT_ADDRESS_COL=" . ACCOUNT_ADDRESS_COL . "\n";
    echo "ACCOUNT_PHONE_COL=" . ACCOUNT_PHONE_COL . "\n";
    echo "ACCOUNT_CREATED_COL=" . ACCOUNT_CREATED_COL . "\n";
    echo "ACCOUNT_AVATAR_COL=" . ACCOUNT_AVATAR_COL . "\n";
    exit; // stop normal page rendering
}
?>
