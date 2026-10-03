<?php
/*
  Şevket Yeşilpekmez
  MiniTube Project - db.php

  Common database connection file.
*/

$servername = "localhost";
$username   = "root";
$password   = ""; // XAMPP kullanıyorsan genelde "" yap
$dbname     = "sevket_yesilpekmez";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
?>
