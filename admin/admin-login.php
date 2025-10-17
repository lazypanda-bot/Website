<?php
// admin-login.phph — merged HTML + PHP handling as requested
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../database.php';

$error = '';
$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Distinguish between signin and temporary registration
    $action = $_POST['action'] ?? 'signin';
    if ($action === 'signin') {
        $email = trim($_POST['identifier'] ?? '');
        $password = $_POST['password'] ?? '';
        if ($email && $password) {
            $stmt = $conn->prepare('SELECT admin_id, name, admin_password FROM admin WHERE email = ? LIMIT 1');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                if (password_verify($password, $row['admin_password'])) {
                    $_SESSION['admin_id'] = (int)$row['admin_id'];
                    $_SESSION['admin_name'] = $row['name'];
                    $_SESSION['is_admin'] = true;
                    header('Location: admin.html');
                    exit;
                }
            }
            $stmt->close();
        }
        $error = 'Invalid credentials';
        $flash = $error; // will be shown as toast
    } else if ($action === 'register') {
        // Temporary registration: minimal checks, create account if email not used
        $reg_email = trim($_POST['reg_email'] ?? '');
        $reg_pass = $_POST['reg_password'] ?? '';
        $reg_name = trim($_POST['reg_name'] ?? 'Admin');
        if ($reg_email && $reg_pass) {
            $check = $conn->prepare('SELECT admin_id FROM admin WHERE email = ? LIMIT 1');
            $check->bind_param('s', $reg_email);
            $check->execute();
            $r = $check->get_result();
            if ($r && $r->fetch_assoc()) {
                $flash = 'Email already registered';
            } else {
                $hash = password_hash($reg_pass, PASSWORD_DEFAULT);
                $ins = $conn->prepare('INSERT INTO admin (name, admin_password, email, created_at) VALUES (?, ?, ?, NOW())');
                $ins->bind_param('sss', $reg_name, $hash, $reg_email);
                if ($ins->execute()) {
                    $flash = 'Temporary admin created. Please delete this registration after use.';
                } else {
                    $flash = 'Failed to create admin: ' . ($ins->error ?: 'unknown');
                }
            }
            $check->close();
        } else {
            $flash = 'Provide email and password to register.';
        }
    }
}
?>
<!Doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Admin login</title>
    <link rel="stylesheet" href="admin-login.css">
    <style> .error { color:#b33; font-weight:600; margin-bottom:10px; } </style>
</head>
<body>
    <div class="wrap">
        <div class="panel">
            <div class="left"><img src="img/logo.png" alt="Logo"></div>
            <div class="right">
                <h1>Sign In</h1>
                <form id="signinForm" action="" method="POST" autocomplete="on">
                    <label for="signin-email">Email</label>
                    <input id="signin-email" name="identifier" type="email" required placeholder="you@example.com">
                    <label for="signin-password">Password</label>
                    <input id="signin-password" name="password" type="password" required placeholder="password">
                    <button type="submit" class="signin-btn"><span class="btn-label">Sign in</span></button>
                </form>            
            </div>
        </div>
    </div>
    <!-- toast container -->
    <div id="toast-container" aria-live="polite" aria-atomic="true"></div>
    <script>
    (function(){
        const flash = <?= json_encode($flash) ?>;
        if(flash){
            const container = document.getElementById('toast-container');
            const t = document.createElement('div');
            t.className = 'toast-msg';
            t.textContent = flash;
            container.appendChild(t);
            // auto-hide using CSS class
            setTimeout(()=>{ t.classList.add('toast-hide'); setTimeout(()=>t.remove(),300); }, 4000);
        }
    })();
    </script>
</body>
</html>
