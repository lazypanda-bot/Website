<?php
// Centralized avatar resolution for nav bar with initials fallback.
// Provides: $navAvatar (path or default), $NAV_AVATAR_HTML (final markup), $navAvatarInitials
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
if (!isset($navAvatar) || !isset($NAV_AVATAR_HTML)) {
    $navAvatar = 'img/logo.png';
    $navAvatarInitials = '';
    $hasImage = false;
    $usernameSession = isset($_SESSION['username']) ? trim($_SESSION['username']) : '';

    if (isset($_SESSION['user_id'])) {
        require_once __DIR__ . '/database.php';
        if (isset($conn) && !$conn->connect_error) {
            $uid = (int)$_SESSION['user_id'];
            $sql = 'SELECT ' . ACCOUNT_AVATAR_COL . ', ' . ACCOUNT_NAME_COL . ' FROM ' . ACCOUNT_TABLE . ' WHERE ' . ACCOUNT_ID_COL . ' = ?';
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param('i', $uid);
                if ($stmt->execute()) {
                    $stmt->bind_result($avPath, $dbName);
                    if ($stmt->fetch()) {
                        if ($dbName && !$usernameSession) { $usernameSession = $dbName; }
                        if ($avPath && file_exists(__DIR__ . '/' . $avPath)) {
                            $navAvatar = htmlspecialchars($avPath, ENT_QUOTES, 'UTF-8');
                            $hasImage = true;
                        }
                    }
                }
                $stmt->close();
            }
        }
    }

    // Derive initials if no image found
    if (!$hasImage) {
        $base = $usernameSession ?: 'User';
        $parts = preg_split('/\s+/', trim($base));
        $first = strtoupper(substr($parts[0] ?? 'U', 0, 1));
        $last = '';
        if (count($parts) > 1) {
            $last = strtoupper(substr(end($parts), 0, 1));
        }
        $navAvatarInitials = $first . $last;
        if ($navAvatarInitials === '') $navAvatarInitials = 'U';
    }

    // Determine display name
    $displayNameRaw = $usernameSession ?: 'Profile';
    $displayName = htmlspecialchars($displayNameRaw, ENT_QUOTES, 'UTF-8');

    if ($hasImage) {
        $NAV_AVATAR_HTML = '<img src="' . $navAvatar . '" alt="' . $displayName . ' avatar" class="nav-avatar" loading="lazy" />';
    } else {
        $NAV_AVATAR_HTML = '<div class="nav-avatar initials" aria-label="' . $displayName . ' initials" role="img">' . htmlspecialchars($navAvatarInitials, ENT_QUOTES, 'UTF-8') . '</div>';
    }

    // Append username label (always) for nav display
    $NAV_AVATAR_HTML .= '<span class="nav-username">' . $displayName . '</span>';
}
?>