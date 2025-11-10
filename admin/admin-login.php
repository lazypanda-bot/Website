<?php
// admin-login.phph — merged HTML + PHP handling
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
                $stored = (string)$row['admin_password'];
                $ok = false;
                // Primary: bcrypt/argon verify
                if ($stored !== '' && password_get_info($stored)['algo'] !== 0) {
                    $ok = password_verify($password, $stored);
                    // Detect possible truncated bcrypt (should be 60 chars)
                    if (!$ok && substr($stored, 0, 4) === '$2y$' && strlen($stored) < 60) {
                        $GLOBALS['__LOGIN_DIAG'] = [
                            'reason' => 'truncated_hash',
                            'stored_len' => strlen($stored),
                            'stored_prefix' => substr($stored,0,10)
                        ];
                    }
                }
                // Legacy fallbacks: MD5 or plaintext, then auto-upgrade to bcrypt
                if (!$ok) {
                    $isHex32 = (bool)preg_match('/^[a-f0-9]{32}$/i', $stored);
                    if ($isHex32 && hash_equals(strtolower($stored), md5($password))) {
                        $ok = true;
                    } elseif ($stored !== '' && hash_equals($stored, $password)) {
                        $ok = true;
                    }
                    if ($ok) {
                        // Upgrade to bcrypt for future logins
                        $newHash = password_hash($password, PASSWORD_DEFAULT);
                        if ($newHash) {
                            $up = $conn->prepare('UPDATE admin SET admin_password = ? WHERE admin_id = ?');
                            if ($up) { $aid = (int)$row['admin_id']; $up->bind_param('si', $newHash, $aid); $up->execute(); $up->close(); }
                        }
                    }
                }
                if ($ok) {
                    $_SESSION['admin_id'] = (int)$row['admin_id'];
                    $_SESSION['admin_name'] = $row['name'];
                    $_SESSION['is_admin'] = true;
                    header('Location: admin.html');
                    exit;
                } else if (!isset($GLOBALS['__LOGIN_DIAG'])) {
                    // Generic mismatch diagnostic (only when login_debug is used)
                    $GLOBALS['__LOGIN_DIAG'] = [
                        'reason' => 'password_mismatch_or_email_not_found',
                        'stored_len' => strlen($stored),
                        'stored_prefix' => substr($stored,0,10),
                        'algo' => password_get_info($stored)
                    ];
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
                <h1 id="formTitle">Sign In</h1>
                <form id="signinForm" action="" method="POST" autocomplete="on">
                    <label for="signin-email">Email</label>
                    <input id="signin-email" name="identifier" type="email" required placeholder="you@example.com">
                    <label for="signin-password">Password</label>
                    <input id="signin-password" name="password" type="password" required placeholder="password">
                    <button type="submit" class="signin-btn"><span class="btn-label">Sign in</span></button>
                </form>            
                <form id="registerForm" action="" method="POST" autocomplete="on" style="display:none">
                    <input type="hidden" name="action" value="register" />
                    <label for="reg-name">Name</label>
                    <input id="reg-name" name="reg_name" type="text" required placeholder="Admin Name">
                    <label for="reg-email">Email</label>
                    <input id="reg-email" name="reg_email" type="email" required placeholder="admin@example.com">
                    <label for="reg-password">Password</label>
                    <input id="reg-password" name="reg_password" type="password" required placeholder="Create password">
                    <button type="submit" class="signin-btn"><span class="btn-label">Create Admin</span></button>
                </form>
                <div class="auth-toggle" style="margin-top:10px;font-weight:600">
                    <a href="#" id="showRegister">Create an admin account</a>
                    <a href="#" id="showSignin" style="display:none">Back to Sign In</a>
                </div>
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

                // Toggle between signin and register
                const signForm = document.getElementById('signinForm');
                const regForm = document.getElementById('registerForm');
                const showReg = document.getElementById('showRegister');
                const showSign = document.getElementById('showSignin');
                const title = document.getElementById('formTitle');
                if (showReg && showSign && signForm && regForm && title){
                    showReg.addEventListener('click', (e)=>{ e.preventDefault(); signForm.style.display='none'; regForm.style.display='flex'; showReg.style.display='none'; showSign.style.display='inline'; title.textContent='Register'; });
                    showSign.addEventListener('click', (e)=>{ e.preventDefault(); regForm.style.display='none'; signForm.style.display='flex'; showSign.style.display='none'; showReg.style.display='inline'; title.textContent='Sign In'; });
                }
            t.textContent = flash;
            container.appendChild(t);
            // auto-hide using CSS class
            setTimeout(()=>{ t.classList.add('toast-hide'); setTimeout(()=>t.remove(),300); }, 4000);
        }
        <?php if(isset($_GET['login_debug']) && isset($GLOBALS['__LOGIN_DIAG'])): ?>
        console.group('admin-login debug');
        console.log(<?= json_encode($GLOBALS['__LOGIN_DIAG']) ?>);
        console.log('Tip:', (<?= json_encode(isset($GLOBALS['__LOGIN_DIAG']['reason']) ? $GLOBALS['__LOGIN_DIAG']['reason'] : '') ?> === 'truncated_hash') ? 'Increase admin.admin_password column to VARCHAR(255) and reset the password.' : 'If password was inserted as plaintext or MD5, the system will auto-upgrade after a successful login.');
        console.groupEnd();
        <?php endif; ?>
    })();
    </script>
</body>
</html>
