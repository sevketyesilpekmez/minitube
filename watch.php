<?php
/*
  Şevket Yeşilpekmez
  CSE348 MiniTube Project
  MiniTube watch page
  Handles view count, simple like action, video metadata, and threaded comments.
*/

require_once "db.php";

$user_id = intval($_GET["user_id"] ?? 0);
$video_id = intval($_GET["video_id"] ?? 0);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "comment";

    if ($action === "like") {
        $likeStmt = $conn->prepare("INSERT IGNORE INTO VIDEO_LIKES (user_id, video_id, liked_at) VALUES (?, ?, NOW())");
        $likeStmt->bind_param("ii", $user_id, $video_id);
        $likeStmt->execute();

        if ($likeStmt->affected_rows > 0) {
            $countLikeStmt = $conn->prepare("UPDATE VIDEOS SET like_count = like_count + 1 WHERE video_id = ?");
            $countLikeStmt->bind_param("i", $video_id);
            $countLikeStmt->execute();
        }

        header("Location: watch.php?user_id=$user_id&video_id=$video_id&no_view=1");
        exit();
    }

    if ($action === "delete_comment") {
        $comment_id = intval($_POST["comment_id"] ?? 0);
        $deleteStmt = $conn->prepare("DELETE FROM COMMENTS WHERE comment_id = ? AND user_id = ?");
        $deleteStmt->bind_param("ii", $comment_id, $user_id);
        $deleteStmt->execute();

        header("Location: watch.php?user_id=$user_id&video_id=$video_id&no_view=1");
        exit();
    }

    if ($action === "comment") {
        $body = trim($_POST["body"] ?? "");

        if ($body !== "") {
            $stmt = $conn->prepare("
                INSERT INTO COMMENTS (video_id, user_id, parent_comment_id, body, posted_at)
                VALUES (?, ?, NULL, ?, NOW())
            ");
            $stmt->bind_param("iis", $video_id, $user_id, $body);
            $stmt->execute();
        }

        header("Location: watch.php?user_id=$user_id&video_id=$video_id&no_view=1");
        exit();
    }
}

/*
  Normal video page loads increment view_count.
  POST redirects include no_view=1 so comments/likes do not count as new views.
*/
if (!isset($_GET["no_view"])) {
    $incStmt = $conn->prepare("UPDATE VIDEOS SET view_count = view_count + 1 WHERE video_id = ?");
    $incStmt->bind_param("i", $video_id);
    $incStmt->execute();

    $historyStmt = $conn->prepare("
        INSERT INTO WATCH_HISTORY (user_id, video_id, watched_at)
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE watched_at = NOW()
    ");
    $historyStmt->bind_param("ii", $user_id, $video_id);
    $historyStmt->execute();
}

$stmt = $conn->prepare("
    SELECT 
        v.*,
        c.channel_id,
        c.name AS channel_name,
        c.channel_image,
        u.country AS uploader_country,
        CASE
            WHEN v.view_count >= 1000 THEN 'Popular'
            WHEN v.view_count >= 100 THEN 'Trending'
            ELSE 'New'
        END AS popularity_badge
    FROM VIDEOS v
    JOIN CHANNELS c ON v.channel_id = c.channel_id
    JOIN USERS u ON c.owner_id = u.user_id
    WHERE v.video_id = ?
");
$stmt->bind_param("i", $video_id);
$stmt->execute();
$video = $stmt->get_result()->fetch_assoc();

if (!$video) {
    die("Video not found.");
}

$countStmt = $conn->prepare("SELECT COUNT(*) AS total_comments FROM COMMENTS WHERE video_id = ?");
$countStmt->bind_param("i", $video_id);
$countStmt->execute();
$commentCount = intval($countStmt->get_result()->fetch_assoc()["total_comments"] ?? 0);

$commentsStmt = $conn->prepare("
    SELECT
        top.comment_id AS top_comment_id,
        top.body AS top_body,
        top.posted_at AS top_posted_at,
        top.user_id AS top_user_id,
        topUser.full_name AS top_user_name,
        reply.comment_id AS reply_comment_id,
        reply.body AS reply_body,
        reply.posted_at AS reply_posted_at,
        reply.user_id AS reply_user_id,
        replyUser.full_name AS reply_user_name
    FROM COMMENTS top
    JOIN USERS topUser ON top.user_id = topUser.user_id
    LEFT JOIN COMMENTS reply ON reply.parent_comment_id = top.comment_id
    LEFT JOIN USERS replyUser ON reply.user_id = replyUser.user_id
    WHERE top.video_id = ? AND top.parent_comment_id IS NULL
    ORDER BY top.posted_at DESC, reply.posted_at ASC
");
$commentsStmt->bind_param("i", $video_id);
$commentsStmt->execute();
$commentRows = $commentsStmt->get_result();

$threads = [];
while ($row = $commentRows->fetch_assoc()) {
    $topId = $row["top_comment_id"];

    if (!isset($threads[$topId])) {
        $threads[$topId] = [
            "id" => $topId,
            "body" => $row["top_body"],
            "posted_at" => $row["top_posted_at"],
            "user_id" => intval($row["top_user_id"]),
            "user" => $row["top_user_name"],
            "replies" => []
        ];
    }

    if ($row["reply_comment_id"] !== null) {
        $threads[$topId]["replies"][] = [
            "id" => intval($row["reply_comment_id"]),
            "body" => $row["reply_body"],
            "posted_at" => $row["reply_posted_at"],
            "user_id" => intval($row["reply_user_id"]),
            "user" => $row["reply_user_name"]
        ];
    }
}

function formatDuration($seconds) {
    $m = floor($seconds / 60);
    $s = $seconds % 60;
    return $m . ":" . str_pad($s, 2, "0", STR_PAD_LEFT);
}

function youtubeThumbnail($url) {
    if (preg_match('/(?:v=|youtu\.be\/)([A-Za-z0-9_-]{6,})/', $url, $match)) {
        return "https://img.youtube.com/vi/" . $match[1] . "/hqdefault.jpg";
    }
    return "https://picsum.photos/seed/watch" . abs(crc32($url)) . "/640/360";
}

function badgeClass($label) {
    if ($label === "Trending") return "badge-trending";
    if ($label === "Popular") return "badge-popular";
    return "badge-new";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($video["title"]); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="header">
        <div class="logo"><span class="logo-mark">▶</span><span>MiniTube</span></div>
        <div class="header-actions">
            <a href="feed.php?user_id=<?php echo $user_id; ?>">Homepage</a>
            <a href="channel.php?user_id=<?php echo $user_id; ?>&channel_id=<?php echo $video["channel_id"]; ?>">Channel</a>
            <a href="liked.php?user_id=<?php echo $user_id; ?>">Liked Videos</a>
            <a href="profile.php?user_id=<?php echo $user_id; ?>">Profile</a>
            <a href="sql.php?user_id=<?php echo $user_id; ?>">SQL Page</a>
        </div>
    </div>

    <div class="container watch-layout">
        <div class="card video-hero">
            <div class="thumb-wrap">
                <img class="video-thumb" src="<?php echo htmlspecialchars(youtubeThumbnail($video["url"])); ?>" alt="Video thumbnail">
                <span class="duration-badge"><?php echo formatDuration(intval($video["duration_seconds"])); ?></span>
            </div>

            <div>
                <h1><?php echo htmlspecialchars($video["title"]); ?></h1>
                <p>
                    Channel:
                    <a href="channel.php?user_id=<?php echo $user_id; ?>&channel_id=<?php echo $video["channel_id"]; ?>">
                        <?php echo htmlspecialchars($video["channel_name"]); ?>
                    </a>
                </p>
                <p class="meta">
                    Uploader country: <?php echo htmlspecialchars($video["uploader_country"]); ?> •
                    Uploaded: <?php echo htmlspecialchars($video["uploaded_at"]); ?>
                </p>

                <div class="stat-line">
                    <span>Views: <b><?php echo intval($video["view_count"]); ?></b></span>
                    <span>Likes: <b><?php echo intval($video["like_count"]); ?></b></span>
                    <span>Comments: <b><?php echo $commentCount; ?></b></span>
                    <span class="badge <?php echo badgeClass($video["popularity_badge"]); ?>"><?php echo htmlspecialchars($video["popularity_badge"]); ?></span>
                </div>

                <p><?php echo htmlspecialchars($video["description"]); ?></p>

                <form method="post" style="display:inline;">
                    <input type="hidden" name="action" value="like">
                    <button class="btn-like" type="submit">Like</button>
                </form>
                <a class="btn" target="_blank" href="<?php echo htmlspecialchars($video["url"]); ?>">Open Video URL</a>
            </div>
        </div>

        <div class="card">
            <h2>Comments (<?php echo $commentCount; ?>)</h2>

            <?php if (count($threads) == 0): ?>
                <div class="empty-state">No comments yet. Be the first one to comment.</div>
            <?php endif; ?>

            <?php foreach ($threads as $thread): ?>
                <div class="comment-block">
                    <p><b><?php echo htmlspecialchars($thread["user"]); ?></b></p>
                    <p><?php echo htmlspecialchars($thread["body"]); ?></p>
                    <p class="meta"><?php echo htmlspecialchars($thread["posted_at"]); ?></p>
                    <?php if ($thread["user_id"] === $user_id): ?>
                        <form method="post" class="inline-delete">
                            <input type="hidden" name="action" value="delete_comment">
                            <input type="hidden" name="comment_id" value="<?php echo intval($thread["id"]); ?>">
                            <button class="btn-small-danger" type="submit">Delete</button>
                        </form>
                    <?php endif; ?>

                    <?php foreach ($thread["replies"] as $reply): ?>
                        <div class="reply">
                            <p><b><?php echo htmlspecialchars($reply["user"]); ?></b></p>
                            <p><?php echo htmlspecialchars($reply["body"]); ?></p>
                            <p class="meta"><?php echo htmlspecialchars($reply["posted_at"]); ?></p>
                            <?php if ($reply["user_id"] === $user_id): ?>
                                <form method="post" class="inline-delete">
                                    <input type="hidden" name="action" value="delete_comment">
                                    <input type="hidden" name="comment_id" value="<?php echo intval($reply["id"]); ?>">
                                    <button class="btn-small-danger" type="submit">Delete</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card">
            <h2>Add Comment</h2>
            <form method="post">
                <input type="hidden" name="action" value="comment">
                <textarea name="body" rows="4" placeholder="Write a new top-level comment..." required></textarea>
                <button type="submit">Post Comment</button>
            </form>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>
