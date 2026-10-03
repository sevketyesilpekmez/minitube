<?php
/*
  Şevket Yeşilpekmez
  MiniTube Project - login.php

  Authenticates user using password_verify().
*/

require_once "db.php";

$username = $_POST["username"] ?? "";
$password = $_POST["password"] ?? "";

$stmt = $conn->prepare("SELECT user_id, full_name, password FROM USERS WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    if (password_verify($password, $row["password"])) {
        header("Location: feed.php?user_id=" . $row["user_id"]);
        exit();
    }
}

$conn->close();

header("Location: login.html?error=1");
exit();
?>