<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// redirect if not logged in
if (!isset($_SESSION['user_id'])) {
  header('Location: home.php');
  exit();
}

// connect to database
$conn = new mysqli("localhost", "root", "", "printshop");
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// fetch user data
$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT username, email FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($username, $email);
$stmt->fetch();
$stmt->close();
$conn->close();
?>

<!-- <!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Profile</title>
  <link rel="stylesheet" href="profile.css">
</head> -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Printing Website</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <link href="navbar-footer.css" rel="stylesheet" />
    <link href="style.css" rel="stylesheet" />
    <link href="login.css" rel="stylesheet" />
    <link rel="stylesheet" href="about.css">
    <link rel="stylesheet" href="message.css">
    <link rel="stylesheet" href="services.css">
    <link rel="stylesheet" href="profile.css">
</head>
<?php
    $isAuthenticated = isset($_SESSION['user_id']);
?>
<script>
    window.isAuthenticated = <?= $isAuthenticated ? 'true' : 'false' ?>;
</script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css" />
</head>
<body>
    <section id="header">
        <div class="left-nav">
            <a href="home.php"><img src="img/Icons/printing_logo-removebg-preview.png" class="logo" alt=""></a>
            <ul class="desktop-nav">
                <li><a href="home.php" class="nav-link">Home</a></li>
                <!-- <li><a href="services.html" class="nav-link">Services</a></li> -->
                <li><a href="products.php" class="nav-link">Products</a></li>
                <li><a href="about.php" class="nav-link">About</a></li>
                <li><a href="contact.php" class="nav-link">Contact</a></li>
            </ul>
        </div>
        <div class="right-nav">
            <form class="search-bar">
                <input type="search" placeholder="Search" name="searchbar" class="search-input hidden">
                <button type="button" class="search-btn"><i class="fa-solid fa-magnifying-glass"></i></button>
            </form>
            <ul style="display: flex; align-items: center; gap: 10px; list-style: none; margin: 0; padding: 0;">
                <li><a href="#" id="cart-icon" class="cart-icon"><i class="fa-solid fa-cart-shopping"></i></a></li>
                <li><a href="#" class="auth-link" id="profile-icon"><i class="fa-solid fa-user"></i></a></li>
            </ul>
            <div id="navbar">
                <button id="close-menu" aria-label="Close Menu">x</button>
                <div class="menu-user">
                    <a href="#" class="auth-link" id="mobile-profile-icon"><i class="fa-solid fa-user"></i></a>
                </div>
                <ul class="mobile-nav">
                    <li><a href="home.php" class="nav-link">Home</a></li>
                    <!-- <li><a href="services.html" class="nav-link">Services</a></li> -->
                    <li><a href="products.php" class="nav-link">Products</a></li>
                    <li><a href="about.php" class="nav-link">About</a></li>
                    <li><a href="contact.php" class="nav-link">Contact</a></li>
                </ul>
            </div>
            <button id="menu-toggle" aria-label="Toggle Menu"><i class="fas fa-outdent"></i></button>
        </div>
    </section>

    <div class="settings-container">
        <section class="account-flex">
            <div class="profile-image">
                <img src="assets/default-avatar.png" alt="Profile Image" />
            </div>
            <form class="account-form">
                <h2>Account Details</h2>
                <div class="form-group">
                    <label for="username">Full Name</label>
                    <input type="text" id="username" name="username" value="<?= htmlspecialchars($username) ?>" readonly />
                </div>
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" readonly />
                </div>
                <div class="button-group">
                    <button type="button" class="btn edit-btn">Edit</button>
                    <button type="button" class="btn delete-btn">Delete</button>
                </div>
            </form>
            <form action="logout.php" method="post" class="logout-bar">
                <button type="submit" class="btn logout-btn">Logout</button>
            </form>
        </section>
    </div>
</body>
<script src="login.js?v=<?= time() ?>"></script>
<script src="cart.js"></script>
</html>