<?php
// check_user_web.php (disabled)
header('Content-Type: application/json');
echo json_encode(['error' => 'disabled', 'message' => 'This debug endpoint has been disabled for security reasons.']);
exit;

?>