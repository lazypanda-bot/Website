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

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Profile</title>
  <link rel="stylesheet" href="profile.css">
</head>
<body>
    <div class="logout-bar">
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>

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
        </section>
    </div>
</body>
</html>