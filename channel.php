<?php
/*
  Şevket Yeşilpekmez
  CSE348 MiniTube Project
  MiniTube channel page
  Shows channel details, subscription action, channel search and sorting.
*/

require_once "db.php";

$user_id = intval($_GET["user_id"] ?? 0);
$channel_id = intval($_GET["channel_id"] ?? 0);
$q = trim($_GET["q"] ?? "");
$sort = $_GET["sort"] ?? "newest";
$allowedSorts = ["newest", "views", "likes"];
if (!in_array($sort, $allowedSorts)) {
    $sort = "newest";
}

$changed = false;
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    if ($action === "subscribe") {
        $stmt = $conn->prepare("INSERT IGNORE INTO SUBSCRIPTIONS (subscriber_id, channel_id, subscribed_at) VALUES (?, ?, NOW())");
        $stmt->bind_param("ii", $user_id, $channel_id);
        $stmt->execute();
        $changed = true;
    }

    if ($action === "unsubscribe") {
        $stmt = $conn->prepare("DELETE FROM SUBSCRIPTIONS WHERE subscriber_id = ? AND channel_id = ?");
        $stmt->bind_param("ii", $user_id, $channel_id);
        $stmt->execute();
        $changed = true;
    }

    $qs = http_build_query(["user_id" => $user_id, "channel_id" => $channel_id, "q" => $q, "sort" => $sort, "changed" => $changed ? 1 : 0]);
    header("Location: channel.php?$qs");
    exit();
}

$stmt = $conn->prepare("
    SELECT 
        c.*,
        u.full_name AS owner_name,
        u.country AS owner_country,
        COUNT(s.subscription_id) AS subscriber_count
    FROM CHANNELS c
    JOIN USERS u ON c.owner_id = u.user_id
    LEFT JOIN SUBSCRIPTIONS s ON c.channel_id = s.channel_id
    WHERE c.channel_id = ?
    GROUP BY c.channel_id
");
$stmt->bind_param("i", $channel_id);
$stmt->execute();
$channel = $stmt->get_result()->fetch_assoc();

if (!$channel) {
    die("Channel not found.");
}

$subStmt = $conn->prepare("SELECT subscription_id FROM SUBSCRIPTIONS WHERE subscriber_id = ? AND channel_id = ?");
$subStmt->bind_param("ii", $user_id, $channel_id);
$subStmt->execute();
$isSubscribed = $subStmt->get_result()->num_rows > 0;

$orderBy = "v.uploaded_at DESC";
if ($sort === "views") {
    $orderBy = "v.view_count DESC, v.uploaded_at DESC";
} elseif ($sort === "likes") {
    $orderBy = "v.like_count DESC, v.uploaded_at DESC";
}

$like = "%" . $q . "%";
if ($q !== "") {
    $videosStmt = $conn->prepare("
        SELECT v.video_id, v.title, v.url, v.duration_seconds, v.uploaded_at, v.view_count, v.like_count,
               COUNT(cm.comment_id) AS comment_count
        FROM VIDEOS v
        LEFT JOIN COMMENTS cm ON cm.video_id = v.video_id
        WHERE v.channel_id = ? AND v.title LIKE ?
        GROUP BY v.video_id, v.title, v.url, v.duration_seconds, v.uploaded_at, v.view_count, v.like_count
        ORDER BY $orderBy
    ");
    $videosStmt->bind_param("is", $channel_id, $like);
} else {
    $videosStmt = $conn->prepare("
        SELECT v.video_id, v.title, v.url, v.duration_seconds, v.uploaded_at, v.view_count, v.like_count,
               COUNT(cm.comment_id) AS comment_count
        FROM VIDEOS v
        LEFT JOIN COMMENTS cm ON cm.video_id = v.video_id
        WHERE v.channel_id = ?
        GROUP BY v.video_id, v.title, v.url, v.duration_seconds, v.uploaded_at, v.view_count, v.like_count
        ORDER BY $orderBy
    ");
    $videosStmt->bind_param("i", $channel_id);
}
$videosStmt->execute();
$videos = $videosStmt->get_result();

function formatDuration($seconds) {
    $m = floor($seconds / 60);
    $s = $seconds % 60;
    return $m . ":" . str_pad($s, 2, "0", STR_PAD_LEFT);
}

function youtubeThumbnail($url) {
    if (preg_match('/(?:v=|youtu\.be\/)([A-Za-z0-9_-]{6,})/', $url, $match)) {
        return "https://img.youtube.com/vi/" . $match[1] . "/hqdefault.jpg";
    }
    return "https://picsum.photos/seed/channel" . abs(crc32($url)) . "/480/270";
}

function fallbackAvatar($seed) {
    return "https://ui-avatars.com/api/?name=" . urlencode($seed) . "&background=333333&color=ffffff";
}

function badgeInfo($views, $uploadedAt) {
    $days = (time() - strtotime($uploadedAt)) / 86400;
    if ($days <= 30) return ["New", "badge-new"];
    if ($views >= 4000) return ["Trending", "badge-trending"];
    if ($views >= 1000) return ["Popular", "badge-popular"];
    return ["New", "badge-new"];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($channel["name"]); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="header">
        <div class="logo"><span class="logo-mark">▶</span><span>MiniTube</span></div>
        <form class="search-form" method="get" action="channel.php">
            <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
            <input type="hidden" name="channel_id" value="<?php echo $channel_id; ?>">
            <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
            <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search this channel...">
            <button type="submit">Search</button>
        </form>
        <div class="header-actions">
            <a href="feed.php?user_id=<?php echo $user_id; ?>">Homepage</a>
            <a href="liked.php?user_id=<?php echo $user_id; ?>">Liked Videos</a>
            <a href="profile.php?user_id=<?php echo $user_id; ?>">Profile</a>
            <a href="sql.php?user_id=<?php echo $user_id; ?>">SQL Page</a>
        </div>
    </div>

    <div class="container">
        <div class="card video-hero">
            <img class="channel-img" src="<?php echo htmlspecialchars($channel["channel_image"]); ?>" onerror="this.onerror=null;this.src='<?php echo fallbackAvatar($channel["name"]); ?>';" alt="Channel">

            <div>
                <h1><?php echo htmlspecialchars($channel["name"]); ?></h1>
                <p class="meta">Category: <?php echo htmlspecialchars($channel["category"]); ?></p>
                <p>Owner: <?php echo htmlspecialchars($channel["owner_name"]); ?> (<?php echo htmlspecialchars($channel["owner_country"]); ?>)</p>
                <p>Created on: <?php echo htmlspecialchars($channel["created_on"]); ?></p>
                <p class="subscriber-pill <?php echo isset($_GET["changed"]) ? "changed" : ""; ?>">Subscribers: <b><?php echo intval($channel["subscriber_count"]); ?></b></p>
                <p>
                    <?php 
                        $desc = trim($channel["description"] ?? "");
                        echo $desc === "" ? "(no description)" : htmlspecialchars($desc);
                    ?>
                </p>

                <form method="post">
                    <input type="hidden" name="q" value="<?php echo htmlspecialchars($q); ?>">
                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
                    <?php if ($isSubscribed): ?>
                        <input type="hidden" name="action" value="unsubscribe">
                        <button class="btn-danger" type="submit">Unsubscribe</button>
                    <?php else: ?>
                        <input type="hidden" name="action" value="subscribe">
                        <button class="btn-green" type="submit">Subscribe</button>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="card">
            <h2>Videos</h2>
            <form class="sort-form" method="get" action="channel.php">
                <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                <input type="hidden" name="channel_id" value="<?php echo $channel_id; ?>">
                <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search channel videos...">
                <select name="sort">
                    <option value="newest" <?php echo $sort === "newest" ? "selected" : ""; ?>>Newest first</option>
                    <option value="views" <?php echo $sort === "views" ? "selected" : ""; ?>>Most viewed</option>
                    <option value="likes" <?php echo $sort === "likes" ? "selected" : ""; ?>>Most liked</option>
                </select>
                <button type="submit">Apply</button>
            </form>

            <?php if ($videos->num_rows == 0): ?>
                <div class="empty-state">No videos found in this channel.</div>
            <?php endif; ?>

            <?php while ($video = $videos->fetch_assoc()): ?>
                <?php [$badgeLabel, $badgeClass] = badgeInfo(intval($video["view_count"]), $video["uploaded_at"]); ?>
                <div class="video-card">
                    <a class="thumb-wrap" href="watch.php?user_id=<?php echo $user_id; ?>&video_id=<?php echo $video["video_id"]; ?>">
                        <img class="video-thumb" src="<?php echo htmlspecialchars(youtubeThumbnail($video["url"])); ?>" alt="Video thumbnail">
                        <span class="duration-badge"><?php echo formatDuration(intval($video["duration_seconds"])); ?></span>
                    </a>
                    <div>
                        <h3 class="video-title">
                            <a href="watch.php?user_id=<?php echo $user_id; ?>&video_id=<?php echo $video["video_id"]; ?>">
                                <?php echo htmlspecialchars($video["title"]); ?>
                            </a>
                            <span class="badge <?php echo $badgeClass; ?>"><?php echo $badgeLabel; ?></span>
                        </h3>
                        <p class="meta">
                            Uploaded: <?php echo htmlspecialchars($video["uploaded_at"]); ?> •
                            <?php echo intval($video["view_count"]); ?> views •
                            <?php echo intval($video["like_count"]); ?> likes •
                            <?php echo intval($video["comment_count"]); ?> comments
                        </p>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>
