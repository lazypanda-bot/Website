<?php
$conn = new mysqli("localhost", "root", "", "printshop");
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}
?>
