<?php
/*
  Şevket Yeşilpekmez
  CSE348 MiniTube Project
  MiniTube liked videos page
*/

require_once "db.php";

$user_id = intval($_GET["user_id"] ?? 0);

$userStmt = $conn->prepare("SELECT full_name FROM USERS WHERE user_id = ?");
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();
if (!$user) die("Invalid user_id. Please login again.");

$stmt = $conn->prepare(" 
    SELECT v.video_id, v.title, v.url, v.duration_seconds, v.view_count, v.like_count, vl.liked_at,
           c.channel_id, c.name AS channel_name, c.channel_image,
           COUNT(cm.comment_id) AS comment_count
    FROM VIDEO_LIKES vl
    JOIN VIDEOS v ON vl.video_id = v.video_id
    JOIN CHANNELS c ON v.channel_id = c.channel_id
    LEFT JOIN COMMENTS cm ON cm.video_id = v.video_id
    WHERE vl.user_id = ?
    GROUP BY v.video_id, v.title, v.url, v.duration_seconds, v.view_count, v.like_count,
             vl.liked_at, c.channel_id, c.name, c.channel_image
    ORDER BY vl.liked_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$videos = $stmt->get_result();

function formatDuration($seconds) {
    $m = floor($seconds / 60);
    $s = $seconds % 60;
    return $m . ":" . str_pad($s, 2, "0", STR_PAD_LEFT);
}
function youtubeThumbnail($url) {
    if (preg_match('/(?:v=|youtu\.be\/)([A-Za-z0-9_-]{6,})/', $url, $match)) {
        return "https://img.youtube.com/vi/" . $match[1] . "/hqdefault.jpg";
    }
    return "https://picsum.photos/seed/liked" . abs(crc32($url)) . "/480/270";
}
function fallbackAvatar($seed) {
    return "https://ui-avatars.com/api/?name=" . urlencode($seed) . "&background=333333&color=ffffff";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Liked Videos</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="header">
        <div class="logo"><span class="logo-mark">▶</span><span>MiniTube</span></div>
        <div class="header-actions">
            <a href="feed.php?user_id=<?php echo $user_id; ?>">Homepage</a>
            <a href="profile.php?user_id=<?php echo $user_id; ?>">Profile</a>
            <a href="sql.php?user_id=<?php echo $user_id; ?>">SQL Page</a>
        </div>
    </div>

    <div class="container">
        <div class="card">
            <h1>Liked Videos</h1>
            <p class="meta">Videos liked by <?php echo htmlspecialchars($user["full_name"]); ?></p>
            <?php if ($videos->num_rows == 0): ?>
                <div class="empty-state">You have not liked any videos yet.</div>
            <?php endif; ?>

            <?php while ($video = $videos->fetch_assoc()): ?>
                <div class="video-card">
                    <a class="thumb-wrap" href="watch.php?user_id=<?php echo $user_id; ?>&video_id=<?php echo $video["video_id"]; ?>">
                        <img class="video-thumb" src="<?php echo htmlspecialchars(youtubeThumbnail($video["url"])); ?>" alt="Video thumbnail">
                        <span class="duration-badge"><?php echo formatDuration(intval($video["duration_seconds"])); ?></span>
                    </a>
                    <div>
                        <h3 class="video-title"><a href="watch.php?user_id=<?php echo $user_id; ?>&video_id=<?php echo $video["video_id"]; ?>"><?php echo htmlspecialchars($video["title"]); ?></a></h3>
                        <div class="channel-line">
                            <img class="small-img" src="<?php echo htmlspecialchars($video["channel_image"]); ?>" onerror="this.onerror=null;this.src='<?php echo fallbackAvatar($video["channel_name"]); ?>';" alt="Channel">
                            <a href="channel.php?user_id=<?php echo $user_id; ?>&channel_id=<?php echo $video["channel_id"]; ?>"><?php echo htmlspecialchars($video["channel_name"]); ?></a>
                        </div>
                        <p class="meta"><?php echo intval($video["view_count"]); ?> views • <?php echo intval($video["like_count"]); ?> likes • <?php echo intval($video["comment_count"]); ?> comments</p>
                        <p class="meta">Liked at <?php echo htmlspecialchars($video["liked_at"]); ?></p>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>
