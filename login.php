<?php
echo "✅ Connected to database.";

require 'database.php';
session_start();

$email = $_POST['identifier']; // matches your input name
$password = $_POST['password'];

$stmt = $conn->prepare("SELECT password FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
  $stmt->bind_result($hashedPassword);
  $stmt->fetch();

  if (password_verify($password, $hashedPassword)) {
    $_SESSION['email'] = $email;
    header("Location: home.html");
  } else {
    echo "❌ Incorrect password.";
  }
} else {
  echo "❌ Email not found.";
}
?>
