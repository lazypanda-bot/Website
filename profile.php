<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

$isAuthenticated = isset($_SESSION['user_id']);
$username = $email = $address = $phone = '';
if ($isAuthenticated) {
    // Handle profile update BEFORE any output
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $address = trim($_POST['address'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $userId = $_SESSION['user_id'];
        if ($address && $phone) {
            $conn = new mysqli("localhost", "root", "", "printshop");
            if (!$conn->connect_error) {
                $stmt = $conn->prepare("UPDATE users SET address = ?, phone_number = ? WHERE id = ?");
                $stmt->bind_param("ssi", $address, $phone, $userId);
                $stmt->execute();
                $stmt->close();
                $conn->close();
                header("Location: profile.php?updated=1");
                exit();
            }
        }
    }
    // connect to database
    $conn = new mysqli("localhost", "root", "", "printshop");
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    // fetch user data
    $userId = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT username, email, address, phone_number FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($username, $email, $address, $phone);
    $stmt->fetch();
    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>My Profile</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <link href="navbar-footer.css" rel="stylesheet" />
    <link href="style.css" rel="stylesheet" />
    <link href="login.css" rel="stylesheet" />
    <link rel="stylesheet" href="about.css">
    <link rel="stylesheet" href="message.css">
    <link rel="stylesheet" href="services.css">
    <link rel="stylesheet" href="profile.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css" />
    <!-- Inline CSS moved to profile.css -->
</head>
<body>
    <section id="header">
        <div class="left-nav">
            <a href="home.php"><img src="img/Icons/printing_logo-removebg-preview.png" class="logo" alt=""></a>
            <ul class="desktop-nav">
                <li><a href="home.php" class="nav-link">Home</a></li>
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
                <li><a href="profile.php" id="profile-icon" class="auth-link"><i class="fa-solid fa-user"></i></a></li>
            </ul>
            <div id="navbar">
                <button id="close-menu" aria-label="Close Menu">x</button>
                <div class="menu-user">
                    <a href="#" class="auth-link" id="mobile-profile-icon"><i class="fa-solid fa-user"></i></a>
                </div>
                <ul class="mobile-nav">
                    <li><a href="home.php" class="nav-link">Home</a></li>
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
                <img src="img/snorlax.png" alt="Profile Image" class="profile-avatar" />
            </div>
            <form class="account-form" method="post" action="profile.php" id="profileForm">
                <h2>Account Details</h2>
                <div class="form-group">
                    <label for="username">Full Name</label>
                    <input type="text" id="username" name="username" value="<?= htmlspecialchars($username) ?>" readonly />
                </div>
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" readonly />
                </div>
                <div class="form-group input-edit-group">
                    <label for="address">Address</label>
                    <div class="input-edit-wrapper">
                        <button type="button" id="edit-address-btn" class="input-edit-btn" tabindex="-1">
                            <i class="fa fa-pencil-alt" aria-hidden="true"></i>
                        </button>
                        <input type="text" id="address" name="address" value="<?= htmlspecialchars($address ?? '') ?>" placeholder="Enter your address" required readonly />
                    </div>
                </div>
                <div class="form-group input-edit-group">
                    <label for="phone">Phone Number</label>
                    <div class="input-edit-wrapper">
                        <button type="button" id="edit-phone-btn" class="input-edit-btn" tabindex="-1">
                            <i class="fa fa-pencil-alt" aria-hidden="true"></i>
                        </button>
                        <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($phone ?? '') ?>" placeholder="Enter your phone number" required readonly />
                    </div>
                </div>
                <div class="profile-action-row">
                    <div class="button-group" id="save-btn-group">
                        <button type="submit" form="profileForm" class="btn edit-btn">Save</button>
                    </div>
                    <form action="logout.php" method="post" class="logout-bar no-padding">
                        <button type="submit" class="btn logout-btn">Logout</button>
                    </form>
                    <form action="#" method="post" class="delete-bar no-padding" onsubmit="return confirm('Are you sure you want to delete your account? This action cannot be undone.');">
                        <button type="submit" class="btn delete-btn">Delete</button>
                    </form>
                </div>
            </form>
            <?php if (isset($_GET['updated']) && $isAuthenticated): ?>
                <div id="profile-toast" class="profile-toast-success">Profile updated successfully!</div>
            <?php endif; ?>
        </section>
    </div>
    <footer id="footer">
        <div class="footer-container">
            <div class="footer-column">
                <h4>Customer Service</h4>
                <p>Available 7am to 12pm</p>
                <p>+63 917 123 4567</p>
                <p>Zamoras St., Ozamis City, Misamis Occidental</p>
            </div>
            <div class="footer-column">
                <h4>Information</h4>
                <ul>
                    <li><a href="about.php">About</a></li>
                    <li><a href="contact.php">Contact</a></li>
                </ul>
            </div>
            <div class="footer-column">
                <h4>Services</h4>
                <ul>
                    <li>Tarpaulin Printing</li>
                    <li>Apparel Printing</li>
                    <li>Personalized Items</li>
                    <li>Stickers</li>
                    <li>Signages</li>
                    <li>Tailoring</li>
                </ul>
            </div>
            <div class="footer-column">
                <h4>Follow Us</h4>
                <div class="social-icons">
                    <a href="#"><i class="fab fa-facebook"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
        </div>
    </footer>
    <div id="login-container"></div>
    <script>
    window.isAuthenticated = <?= $isAuthenticated ? 'true' : 'false' ?>;
    </script>
    <script src="login.js?v=<?= time() ?>"></script>
    <script src="cart.js"></script>
    <script src="profile.js"></script>
</body>
</html>