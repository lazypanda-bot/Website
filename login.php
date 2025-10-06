<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
error_reporting(E_ALL);
ini_set('display_errors', 1);

// handle redirect origin
$_SESSION['redirect_after_auth'] = $_POST['redirect_after_auth'] ?? $_SESSION['redirect_after_auth'] ?? $_SERVER['REQUEST_URI'];

$loginMessage = "";
$registerMessage = "";

// Central DB connection
require_once __DIR__ . '/database.php';

// determine form type
$formType = $_POST['form_type'] ?? null;

// handle Login
if ($_SERVER["REQUEST_METHOD"] === "POST" && $formType === 'login') {
  $email = trim($_POST['identifier'] ?? '');
  $password = $_POST['password'] ?? '';
  $redirect = $_POST['redirect_after_auth'] ?? 'profile.php';

  if ($email && $password) {
  $stmt = $conn->prepare(
    "SELECT " . ACCOUNT_ID_COL . ", " . ACCOUNT_NAME_COL . ", " . ACCOUNT_PASS_COL . " FROM " . ACCOUNT_TABLE . " WHERE " . ACCOUNT_EMAIL_COL . " = ?"
  );
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
      $stmt->bind_result($id, $username, $hashedPassword);
      $stmt->fetch();
      if (password_verify($password, $hashedPassword)) {
        $_SESSION['user_id'] = $id;
        $_SESSION['username'] = $username;
        $_SESSION['email'] = $email;
        // Set short-lived welcome toast cookie (URL encoded message)
  $welcomeMsg = 'Welcome back, ' . ($username ?: 'user') . '!';
  // Store plain text (avoid URL encoding artifacts like %20 in UI)
  $safeMsg = str_replace(["\r","\n",";"], ' ', $welcomeMsg);
  setcookie('welcome_toast', $safeMsg, time()+60, '/');
        header("Location: " . $redirect);
        exit();
      } else {
        // Redirect back with error
        header("Location: $redirect?login_error=1");
        exit();
      }
    } else {
      // Redirect back with error
      header("Location: $redirect?login_error=1");
      exit();
    }
    $stmt->close();
  } else {
    // Redirect back with error
    header("Location: $redirect?login_error=1");
    exit();
  }
}

// handle Registration
if ($_SERVER["REQUEST_METHOD"] === "POST" && $formType === 'register') {
  $username = trim($_POST['username'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $passwordRaw = $_POST['password'] ?? '';
  $password = password_hash($passwordRaw, PASSWORD_DEFAULT);

  if ($username && $email && $passwordRaw) {
  $stmt = $conn->prepare(
    "SELECT " . ACCOUNT_ID_COL . " FROM " . ACCOUNT_TABLE . " WHERE " . ACCOUNT_EMAIL_COL . " = ?"
  );
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
      $registerMessage = "Email already registered.";
    } else {
  $stmt = $conn->prepare(
    "INSERT INTO " . ACCOUNT_TABLE . " (" . ACCOUNT_NAME_COL . ", " . ACCOUNT_EMAIL_COL . ", " . ACCOUNT_PASS_COL . ") VALUES (?, ?, ?)"
  );
      $stmt->bind_param("sss", $username, $email, $password);
      if ($stmt->execute()) {
        $_SESSION['user_id'] = $stmt->insert_id;
        $_SESSION['username'] = $username;
        $_SESSION['email'] = $email;
        // Set welcome toast for new registration
  $welcomeMsg = 'Account created — welcome, ' . ($username ?: 'user') . '!';
  $safeMsg = str_replace(["\r","\n",";"], ' ', $welcomeMsg);
  setcookie('welcome_toast', $safeMsg, time()+60, '/');
        header("Location: " . $_SESSION['redirect_after_auth']);
        exit();
      } else {
        $registerMessage = "Error: " . $stmt->error;
      }
    }
    $stmt->close();
  } else {
    $registerMessage = "Please fill in all fields.";
  }
}

// Optional: leave connection open for included components; PHP will close automatically.
?>

<div class="modal" id="auth-modal">
  <?php if(!empty($registerMessage)): ?>
    <div style="color:#b30000;font-weight:600;text-align:center;margin-bottom:10px;"><?= htmlspecialchars($registerMessage) ?></div>
  <?php endif; ?>
  <div class="auth-box" id="auth-box">
    <button id="modal-close" class="close-btn" aria-label="Close">&times;</button>

    <div class="forms-container">
      <div class="signin-signup">

        <form action="login.php" method="POST" class="sign-in-form">
          <input type="hidden" name="form_type" value="login" />
          <input type="hidden" name="redirect_after_auth" id="login-redirect-after-auth" />

          <h2 class="title">Sign In</h2>

          <div class="input-field" id="login-identifier-field">
            <i class="fas fa-user" id="login-identifier-icon"></i>
            <input type="text" name="identifier" id="login-identifier-input" placeholder="Email" required />
          </div>

          <div class="input-field">
            <i class="fas fa-lock"></i>
            <input type="password" name="password" id="login-password" placeholder="Password" required />
          </div>

          <div class="checkbox-field">
            <label><input type="checkbox" id="toggle-login-password" />Show Password</label>
          </div>

          <input type="submit" value="Login" class="auth-btn solid" />

          <p class="social-text">Or sign in with:</p>
          <div class="social-media" id="login-social">
            <a href="#" class="social-icon" id="login-use-phone"><i class="fas fa-phone"></i></a>
            <a href="#" class="social-icon" id="login-use-facebook"><i class="fab fa-facebook-f"></i></a>
          </div>
        </form>

        <form action="login.php" method="POST" class="sign-up-form">
          <input type="hidden" name="form_type" value="register" />
          <input type="hidden" name="redirect_after_auth" id="register-redirect-after-auth" />

          <h2 class="title">Sign Up</h2>

          <div class="input-field">
            <i class="fas fa-user"></i>
            <input type="text" name="username" placeholder="Full Name" required />
          </div>

          <div class="input-field" id="reg-identifier-field">
            <i class="fas fa-envelope" id="reg-identifier-icon"></i>
            <input type="email" name="email" id="reg-identifier-input" placeholder="Email" required />
          </div>

          <div class="input-field">
            <i class="fas fa-lock"></i>
            <input type="password" name="password" id="register-password" placeholder="Password" required />
          </div>

          <div class="checkbox-field">
            <label><input type="checkbox" id="toggle-register-password" />Show Password</label>
          </div>

          <input type="submit" class="auth-btn" value="Register" />

          <p class="social-text">Or sign up with:</p>
          <div class="social-media" id="reg-social">
            <a href="#" class="social-icon" id="reg-use-phone"><i class="fas fa-phone"></i></a>
            <a href="#" class="social-icon" id="reg-use-facebook"><i class="fab fa-facebook-f"></i></a>
          </div>
        </form>
      </div>
    </div>

    <div class="panels-container">
      <div class="panel left-panel">
        <div class="content">
          <h3>New here?</h3>
          <p>Register with your personal details to use all of site features</p>
          <button class="auth-btn transparent" id="sign-up-btn">Sign Up</button>
        </div>
      </div>
      <div class="panel right-panel">
        <div class="content">
          <h3>One of us?</h3>
          <p>Sign in to access your account and enjoy our services</p>
          <button class="auth-btn transparent" id="sign-in-btn">Sign In</button>
        </div>
      </div>
    </div>
  </div>
</div>