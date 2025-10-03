<?php
session_start();
$_SESSION['redirect_after_auth'] = $_SERVER['REQUEST_URI'];
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>

<?php
$loginMessage = "";
$registerMessage = "";

// Handle login
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['form_type']) && $_POST['form_type'] === 'login') {
  $conn = new mysqli("localhost", "root", "", "printshop");
  $email = trim($_POST['identifier']);
  $password = $_POST['password'];

  $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE email = ?");
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
      $redirect = $_POST['redirect_to'] ?? 'home.php';
        header("Location: $redirect");
        exit();
    } else {
      $loginMessage = "Incorrect password.";
    }
  } else {
    $loginMessage = "User not found.";
  }
  $stmt->close();
  $conn->close();
}

// Handle registration
echo "Register logic triggered.";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['form_type']) && $_POST['form_type'] === 'register') {
  $conn = new mysqli("localhost", "root", "", "printshop");
  $username = trim($_POST['username']);
  $email = trim($_POST['email']);
  $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

  $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
  $stmt->bind_param("s", $email);
  $stmt->execute();
  $stmt->store_result();

  if ($stmt->num_rows > 0) {
    $registerMessage = "Email already registered.";
  } else {
    $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $username, $email, $password);
    if ($stmt->execute()) {
      $registerMessage = "Registration successful!";
    } else {
      $registerMessage = "Error: " . $stmt->error;
    }
  }
  $stmt->close();
  $conn->close();
}
?>


<div class="modal" id="auth-modal">
  <div class="auth-box" id="auth-box">
    <button id="modal-close" class="close-btn" aria-label="Close">&times;</button>

    <div class="forms-container">
      <div class="signin-signup">

        <!-- Sign In Form -->
        <form action="login.php" method="POST" class="sign-in-form">
          <input type="hidden" name="redirect_to" value="<?= $_SESSION['redirect_after_auth'] ?? 'home.php' ?>" />

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
            <label>
              <input type="checkbox" id="toggle-login-password" />Show Password
            </label>
          </div>

          <input type="submit" value="Login" class="auth-btn solid" />

          <p class="social-text">Or sign in with:</p>
          <div class="social-media" id="login-social">
            <a href="#" class="social-icon" id="login-use-phone"><i class="fas fa-phone"></i></a>
            <a href="#" class="social-icon" id="login-use-facebook"><i class="fab fa-facebook-f"></i></a>
          </div>
        </form>

        <!-- Sign Up Form -->
        <form action="login.php" method="POST" class="sign-up-form">
          <input type="hidden" name="redirect_to" value="<?= $_SESSION['redirect_after_auth'] ?? 'home.php' ?>" />

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
            <label>
              <input type="checkbox" id="toggle-register-password" />Show Password
            </label>
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
