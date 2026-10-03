<?php
/*
  Şevket Yeşilpekmez
  CSE348 MiniTube Project
  MiniTube profile page
  Shows user info, subscriptions, liked videos, comments and watch history.
*/

require_once "db.php";

$user_id = intval($_GET["user_id"] ?? 0);

$userStmt = $conn->prepare("SELECT * FROM USERS WHERE user_id = ?");
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();
if (!$user) die("Invalid user_id. Please login again.");

$ownChannelStmt = $conn->prepare("SELECT channel_id, name FROM CHANNELS WHERE owner_id = ?");
$ownChannelStmt->bind_param("i", $user_id);
$ownChannelStmt->execute();
$ownChannel = $ownChannelStmt->get_result()->fetch_assoc();

$subsStmt = $conn->prepare(" 
    SELECT c.channel_id, c.name, c.channel_image, c.category, s.subscribed_at
    FROM SUBSCRIPTIONS s
    JOIN CHANNELS c ON s.channel_id = c.channel_id
    WHERE s.subscriber_id = ?
    ORDER BY s.subscribed_at DESC
    LIMIT 8
");
$subsStmt->bind_param("i", $user_id);
$subsStmt->execute();
$subscriptions = $subsStmt->get_result();

$likedStmt = $conn->prepare(" 
    SELECT v.video_id, v.title, c.name AS channel_name, vl.liked_at
    FROM VIDEO_LIKES vl
    JOIN VIDEOS v ON vl.video_id = v.video_id
    JOIN CHANNELS c ON v.channel_id = c.channel_id
    WHERE vl.user_id = ?
    ORDER BY vl.liked_at DESC
    LIMIT 8
");
$likedStmt->bind_param("i", $user_id);
$likedStmt->execute();
$likedVideos = $likedStmt->get_result();

$commentsStmt = $conn->prepare(" 
    SELECT cm.comment_id, cm.body, cm.posted_at, v.video_id, v.title
    FROM COMMENTS cm
    JOIN VIDEOS v ON cm.video_id = v.video_id
    WHERE cm.user_id = ?
    ORDER BY cm.posted_at DESC
    LIMIT 8
");
$commentsStmt->bind_param("i", $user_id);
$commentsStmt->execute();
$comments = $commentsStmt->get_result();

$historyStmt = $conn->prepare(" 
    SELECT wh.watched_at, v.video_id, v.title, c.name AS channel_name
    FROM WATCH_HISTORY wh
    JOIN VIDEOS v ON wh.video_id = v.video_id
    JOIN CHANNELS c ON v.channel_id = c.channel_id
    WHERE wh.user_id = ?
    ORDER BY wh.watched_at DESC
    LIMIT 8
");
$historyStmt->bind_param("i", $user_id);
$historyStmt->execute();
$history = $historyStmt->get_result();

function fallbackAvatar($seed) {
    return "https://ui-avatars.com/api/?name=" . urlencode($seed) . "&background=333333&color=ffffff";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($user["full_name"]); ?> Profile</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="header">
        <div class="logo"><span class="logo-mark">▶</span><span>MiniTube</span></div>
        <div class="header-actions">
            <a href="feed.php?user_id=<?php echo $user_id; ?>">Homepage</a>
            <a href="liked.php?user_id=<?php echo $user_id; ?>">Liked Videos</a>
            <a href="sql.php?user_id=<?php echo $user_id; ?>">SQL Page</a>
        </div>
    </div>

    <div class="container">
        <div class="card video-hero">
            <img class="channel-img" src="<?php echo htmlspecialchars($user["user_image"]); ?>" onerror="this.onerror=null;this.src='<?php echo fallbackAvatar($user["full_name"]); ?>';" alt="Profile">
            <div>
                <h1><?php echo htmlspecialchars($user["full_name"]); ?></h1>
                <p class="meta">Username: <?php echo htmlspecialchars($user["username"]); ?></p>
                <p class="meta">Country: <?php echo htmlspecialchars($user["country"]); ?> • Joined: <?php echo htmlspecialchars($user["joined_on"]); ?></p>
                <p><?php echo htmlspecialchars($user["bio"]); ?></p>
                <?php if ($ownChannel): ?>
                    <a class="btn" href="channel.php?user_id=<?php echo $user_id; ?>&channel_id=<?php echo $ownChannel["channel_id"]; ?>">Open my channel</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="profile-grid">
            <div class="card">
                <h2>Subscribed Channels</h2>
                <?php if ($subscriptions->num_rows == 0): ?><div class="empty-state">No subscriptions yet.</div><?php endif; ?>
                <?php while ($sub = $subscriptions->fetch_assoc()): ?>
                    <div class="side-channel">
                        <img class="small-img" src="<?php echo htmlspecialchars($sub["channel_image"]); ?>" onerror="this.onerror=null;this.src='<?php echo fallbackAvatar($sub["name"]); ?>';" alt="Channel">
                        <div><a href="channel.php?user_id=<?php echo $user_id; ?>&channel_id=<?php echo $sub["channel_id"]; ?>"><?php echo htmlspecialchars($sub["name"]); ?></a><p class="meta"><?php echo htmlspecialchars($sub["category"]); ?> • <?php echo htmlspecialchars($sub["subscribed_at"]); ?></p></div>
                    </div>
                <?php endwhile; ?>
            </div>

            <div class="card">
                <h2>Liked Videos</h2>
                <?php if ($likedVideos->num_rows == 0): ?><div class="empty-state">No liked videos yet.</div><?php endif; ?>
                <?php while ($video = $likedVideos->fetch_assoc()): ?>
                    <p><a href="watch.php?user_id=<?php echo $user_id; ?>&video_id=<?php echo $video["video_id"]; ?>"><?php echo htmlspecialchars($video["title"]); ?></a></p>
                    <p class="meta"><?php echo htmlspecialchars($video["channel_name"]); ?> • liked at <?php echo htmlspecialchars($video["liked_at"]); ?></p>
                <?php endwhile; ?>
            </div>

            <div class="card">
                <h2>Recent Watch History</h2>
                <?php if ($history->num_rows == 0): ?><div class="empty-state">No watch history yet.</div><?php endif; ?>
                <?php while ($item = $history->fetch_assoc()): ?>
                    <p><a href="watch.php?user_id=<?php echo $user_id; ?>&video_id=<?php echo $item["video_id"]; ?>"><?php echo htmlspecialchars($item["title"]); ?></a></p>
                    <p class="meta"><?php echo htmlspecialchars($item["channel_name"]); ?> • watched at <?php echo htmlspecialchars($item["watched_at"]); ?></p>
                <?php endwhile; ?>
            </div>

            <div class="card">
                <h2>Recent Comments</h2>
                <?php if ($comments->num_rows == 0): ?><div class="empty-state">No comments yet.</div><?php endif; ?>
                <?php while ($comment = $comments->fetch_assoc()): ?>
                    <p><?php echo htmlspecialchars($comment["body"]); ?></p>
                    <p class="meta">On <a href="watch.php?user_id=<?php echo $user_id; ?>&video_id=<?php echo $comment["video_id"]; ?>"><?php echo htmlspecialchars($comment["title"]); ?></a> • <?php echo htmlspecialchars($comment["posted_at"]); ?></p>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>
