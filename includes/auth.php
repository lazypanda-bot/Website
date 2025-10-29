<?php
// Centralized session -> account validation helpers
// Usage:
//  - require_valid_user_json() -> returns int user id or responds with JSON auth and exits
//  - require_valid_user_redirect() -> redirects to login when missing
//  - session_user_id_or_zero() -> returns user id if present and valid, otherwise 0 (and clears stale session)

if (session_status() === PHP_SESSION_NONE) { @session_start(); }

require_once __DIR__ . '/../database.php';

function session_user_id_or_zero() {
    global $conn;
    if (!isset($_SESSION['user_id'])) return 0;
    $uid = (int)$_SESSION['user_id'];
    if ($uid <= 0) return 0;
    $chk = $conn->prepare('SELECT 1 FROM ' . ACCOUNT_TABLE . ' WHERE ' . ACCOUNT_ID_COL . ' = ? LIMIT 1');
    if (!$chk) {
        // If we cannot prepare, conservatively treat as unauthenticated
        session_unset(); session_destroy();
        return 0;
    }
    $chk->bind_param('i', $uid);
    $chk->execute();
    $chk->store_result();
    $exists = $chk->num_rows > 0;
    $chk->close();
    if (!$exists) { session_unset(); session_destroy(); return 0; }
    return $uid;
}

function require_valid_user_json() {
    if (session_status() === PHP_SESSION_NONE) { @session_start(); }
    $uid = session_user_id_or_zero();
    if ($uid <= 0) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'auth', 'message' => 'Not logged in']);
        exit;
    }
    return $uid;
}

function require_valid_user_redirect() {
    if (session_status() === PHP_SESSION_NONE) { @session_start(); }
    $uid = session_user_id_or_zero();
    if ($uid <= 0) {
        header('Location: login.php?login_error=1');
        exit;
    }
    return $uid;
}

?>
