<?php
/*
  Şevket Yeşilpekmez
  MiniTube Project - install.php

  Creates database and tables, then imports seed.sql.
*/

$servername = "127.0.0.1";
$username   = "root";
$password   = ""; 
$dbname     = "sevket_yesilpekmez";

$conn = new mysqli($servername, $username, $password);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

if (!$conn->query("DROP DATABASE IF EXISTS `$dbname`")) {
    die("Database drop error: " . $conn->error);
}

if (!$conn->query("CREATE DATABASE `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
    die("Database create error: " . $conn->error);
}

if (!$conn->select_db($dbname)) {
    die("Database select error: " . $conn->error);
}

$queries = [];

$queries[] = "
CREATE TABLE USERS (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    user_image VARCHAR(500),
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    country VARCHAR(60),
    joined_on DATE NOT NULL,
    bio TEXT
) ENGINE=InnoDB;
";

$queries[] = "
CREATE TABLE CHANNELS (
    channel_id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL UNIQUE,
    channel_image VARCHAR(500),
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_on DATE NOT NULL,
    category VARCHAR(60),
    CONSTRAINT fk_channels_owner
        FOREIGN KEY (owner_id) REFERENCES USERS(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB;
";

$queries[] = "
CREATE TABLE VIDEOS (
    video_id INT AUTO_INCREMENT PRIMARY KEY,
    channel_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    url VARCHAR(500) NOT NULL,
    duration_seconds INT NOT NULL,
    uploaded_at DATETIME NOT NULL,
    view_count INT NOT NULL DEFAULT 0,
    like_count INT NOT NULL DEFAULT 0,
    CONSTRAINT fk_videos_channel
        FOREIGN KEY (channel_id) REFERENCES CHANNELS(channel_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB;
";

$queries[] = "
CREATE TABLE SUBSCRIPTIONS (
    subscription_id INT AUTO_INCREMENT PRIMARY KEY,
    subscriber_id INT NOT NULL,
    channel_id INT NOT NULL,
    subscribed_at DATETIME NOT NULL,
    UNIQUE (subscriber_id, channel_id),
    CONSTRAINT fk_subscriptions_user
        FOREIGN KEY (subscriber_id) REFERENCES USERS(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_subscriptions_channel
        FOREIGN KEY (channel_id) REFERENCES CHANNELS(channel_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB;
";

$queries[] = "
CREATE TABLE COMMENTS (
    comment_id INT AUTO_INCREMENT PRIMARY KEY,
    video_id INT NOT NULL,
    user_id INT NOT NULL,
    parent_comment_id INT NULL,
    body TEXT NOT NULL,
    posted_at DATETIME NOT NULL,
    CONSTRAINT fk_comments_video
        FOREIGN KEY (video_id) REFERENCES VIDEOS(video_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_comments_user
        FOREIGN KEY (user_id) REFERENCES USERS(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_comments_parent
        FOREIGN KEY (parent_comment_id) REFERENCES COMMENTS(comment_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB;
";


$queries[] = "
CREATE TABLE VIDEO_LIKES (
    like_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    video_id INT NOT NULL,
    liked_at DATETIME NOT NULL,
    UNIQUE (user_id, video_id),
    CONSTRAINT fk_video_likes_user
        FOREIGN KEY (user_id) REFERENCES USERS(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_video_likes_video
        FOREIGN KEY (video_id) REFERENCES VIDEOS(video_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB;
";

$queries[] = "
CREATE TABLE WATCH_HISTORY (
    history_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    video_id INT NOT NULL,
    watched_at DATETIME NOT NULL,
    UNIQUE (user_id, video_id),
    CONSTRAINT fk_watch_history_user
        FOREIGN KEY (user_id) REFERENCES USERS(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_watch_history_video
        FOREIGN KEY (video_id) REFERENCES VIDEOS(video_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB;
";

foreach ($queries as $query) {
    if (!$conn->query($query)) {
        die("Table creation error: " . $conn->error);
    }
}

$seedFile = __DIR__ . "/seed.sql";

if (!file_exists($seedFile)) {
    die("seed.sql not found. Please run generate_data.php first.");
}

$seedSql = file_get_contents($seedFile);

if (!$conn->multi_query($seedSql)) {
    die("seed.sql import error: " . $conn->error);
}

do {
    if ($result = $conn->store_result()) {
        $result->free();
    }
} while ($conn->more_results() && $conn->next_result());

if ($conn->errno) {
    die("seed.sql import error: " . $conn->error);
}

$conn->close();

header("Location: login.html");
exit();
?>
