<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit();
}
?>
<h2>Welcome, <?= htmlspecialchars($_SESSION['username']) ?>!</h2>
<p>Email: <?= htmlspecialchars($_SESSION['email']) ?></p>
<a href="logout.php">Logout</a>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Account Settings</title>
  <link rel="stylesheet" href="profile.css" />
  <link href="navbar-footer.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
</head>
<body>
  <section id="header">
        <div class="left-nav">
            <a href="home.php"><img src="img/Icons/printing_logo-removebg-preview.png" class="logo" alt=""></a>
            <ul class="desktop-nav">
                <li><a href="home.php" class="nav-link">Home</a></li>
                <!-- <li><a href="services.html" class="nav-link">Services</a></li> -->
                <li><a href="products.html" class="nav-link">Products</a></li>
                <li><a href="about.html" class="nav-link">About</a></li>
                <li><a href="contact.html" class="nav-link">Contact</a></li>
            </ul>
        </div>
        <div class="right-nav">
            <form class="search-bar">
                <input type="search" placeholder="Search" name="searchbar" class="search-input hidden">
                <button type="button" class="search-btn"><i class="fa-solid fa-magnifying-glass"></i></button>
            </form>
            <a href="cart.html" class="cart-icon"><i class="fa-solid fa-cart-shopping"></i></a>
            <li><a href="#" class="auth-link" id="open-login"><i class="fa-solid fa-user"></i></a></li>
            <div id="navbar">
                <button id="close-menu" aria-label="Close Menu">x</button>
                <div class="menu-user">
                    <a href="login.php" class="auth-link"><i class="fa-solid fa-user"></i></a>
                </div>      
                <ul class="mobile-nav">
                    <li><a href="home.php" class="nav-link">Home</a></li>
                    <!-- <li><a href="services.html" class="nav-link">Services</a></li> -->
                    <li><a href="products.html" class="nav-link">Products</a></li>
                    <li><a href="about.html" class="nav-link">About</a></li>
                    <li><a href="contact.html" class="nav-link">Contact</a></li>
                </ul>
            </div>
            <button id="menu-toggle" aria-label="Toggle Menu"><i class="fas fa-outdent"></i></button>
        </div>
    </section>

    <div class="settings-container">
      <!-- Account Section -->
      <section class="account-section">
        <div class="account-flex">
          <!-- Profile Image -->
          <div class="profile-image">
            <img src="img/snorlax.png" alt="Profile Image" />
          </div>
          <!-- Form Fields -->
          <div class="account-form">
            <h2>Account Details</h2>
            <div class="form-group">
            <label for="first-name">Name:</label>
            <input type="text" id="first-name" value="<?php echo htmlspecialchars($user['username']); ?>" />
          </div>
          <div class="form-group">
            <label for="email">Email Address:</label>
            <input type="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" />
          </div>
          <div class="form-group">
            <label for="password">Password:</label>
            <input type="password" id="password" placeholder="••••••••" />
          </div>
          <div class="form-group">
            <label for="phone">Phone Number:</label>
            <input type="tel" id="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" />
          </div>
          <div class="button-group">
            <button class="btn edit-btn">Edit Account</button>
          </div>
        </div>
      <!-- Security Section -->
      <section class="security-section">
        <h2>Security</h2>
        <div class="form-group">
          <label for="current-password">Current Password:</label>
          <input type="password" id="current-password" placeholder="Enter current password" />
        </div>
        <div class="form-group">
          <label for="new-password">New Password:</label>
          <input type="password" id="new-password" placeholder="Enter new password" />
        </div>
        <div class="form-group">
          <label for="confirm-password">Confirm Password:</label>
          <input type="password" id="confirm-password" placeholder="Re-enter new password" />
        </div>
        <div class="button-group">
          <button class="btn delete-btn">Delete Account</button>
        </div>
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
                    <li><a href="about.html">About</a></li>
                    <li><a href="contact.html">Contact</a></li>
                </ul>
            </div>
            <div class="footer-column">
                <h4>Services</h4>
                <ul>
                    <li>Tarpaulin Printing</li>
                    <li>Apparel Printing</li>
                    <li>Personalized Items</li>
                    <li>Stickers</li>
                    <li>Signage</li>
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

    <script src="app.js"></script>
</body>
</html>