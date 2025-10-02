<?php
require 'database.php'; 

// Get form data
$username = $_POST['username'];
$email = $_POST['email'];
$password = password_hash($_POST['password'], PASSWORD_DEFAULT);

// Prepare SQL
$stmt = $conn->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
$stmt->bind_param("ss", $email, $password);

// Execute and redirect
if ($stmt->execute()) {
  echo "✅ Registration successful!";
  // header("Location: index.html"); // or login page
} else {
  echo "❌ Registration failed: " . $conn->error;
}
?>
