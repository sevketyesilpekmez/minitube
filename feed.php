<?php
/*
  Şevket Yeşilpekmez
  CSE348 MiniTube Project
  MiniTube feed page
  Features: search, sorting, category filter, pagination, recommendations.
*/

require_once "db.php";

$user_id = intval($_GET["user_id"] ?? 0);
$q = trim($_GET["q"] ?? "");
$sort = $_GET["sort"] ?? "newest";
$category = trim($_GET["category"] ?? "");
$page = max(1, intval($_GET["page"] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$allowedSorts = ["newest", "views", "likes"];
if (!in_array($sort, $allowedSorts)) $sort = "newest";

$orderBy = "v.uploaded_at DESC";
if ($sort === "views") $orderBy = "v.view_count DESC, v.uploaded_at DESC";
if ($sort === "likes") $orderBy = "v.like_count DESC, v.uploaded_at DESC";

$userStmt = $conn->prepare("SELECT * FROM USERS WHERE user_id = ?");
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();
if (!$user) die("Invalid user_id. Please login again.");

$ownChannelStmt = $conn->prepare("SELECT channel_id, name FROM CHANNELS WHERE owner_id = ?");
$ownChannelStmt->bind_param("i", $user_id);
$ownChannelStmt->execute();
$ownChannel = $ownChannelStmt->get_result()->fetch_assoc();

$categories = $conn->query("SELECT DISTINCT category FROM CHANNELS WHERE category IS NOT NULL AND category <> '' ORDER BY category ASC");

function formatDuration($seconds) {
    $m = floor($seconds / 60);
    $s = $seconds % 60;
    return $m . ":" . str_pad($s, 2, "0", STR_PAD_LEFT);
}

function youtubeThumbnail($url) {
    if (preg_match('/(?:v=|youtu\.be\/)([A-Za-z0-9_-]{6,})/', $url, $match)) {
        return "https://img.youtube.com/vi/" . $match[1] . "/hqdefault.jpg";
    }
    return "https://picsum.photos/seed/minitube" . abs(crc32($url)) . "/480/270";
}

function fallbackAvatar($seed) {
    return "https://ui-avatars.com/api/?name=" . urlencode($seed) . "&background=333333&color=ffffff";
}

function badgeInfo($views, $uploadedAt) {
    if ($views >= 1000) return ["Popular", "badge-popular"];
    if ($views >= 100) return ["Trending", "badge-trending"];
    return ["New", "badge-new"];
}

$where = [];
$types = "";
$params = [];

if ($q !== "") {
    $where[] = "v.title LIKE ?";
    $types .= "s";
    $params[] = "%" . $q . "%";
}

if ($category !== "") {
    $where[] = "c.category = ?";
    $types .= "s";
    $params[] = $category;
}

$where[] = "s.subscriber_id = ?";
$types .= "i";
$params[] = $user_id;
$whereSql = count($where) > 0 ? implode(" AND ", $where) : "1=1";

$fromSql = "
FROM SUBSCRIPTIONS s
JOIN CHANNELS c ON s.channel_id = c.channel_id
JOIN USERS u ON c.owner_id = u.user_id
JOIN VIDEOS v ON c.channel_id = v.channel_id
LEFT JOIN COMMENTS cm ON cm.video_id = v.video_id
";

$countSql = "SELECT COUNT(DISTINCT v.video_id) AS total " . $fromSql . " WHERE " . $whereSql;
$countStmt = $conn->prepare($countSql);
if ($types !== "") $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalRows = intval($countStmt->get_result()->fetch_assoc()["total"] ?? 0);
$totalPages = max(1, ceil($totalRows / $perPage));

$sql = "
    SELECT 
        v.video_id, v.title, v.url, v.duration_seconds, v.uploaded_at, v.view_count, v.like_count,
        c.channel_id, c.name AS channel_name, c.channel_image, c.category,
        u.country AS uploader_country,
        DATEDIFF(CURDATE(), DATE(v.uploaded_at)) AS days_ago,
        COUNT(cm.comment_id) AS comment_count
    $fromSql
    WHERE $whereSql
    GROUP BY v.video_id, v.title, v.url, v.duration_seconds, v.uploaded_at, v.view_count,
             v.like_count, c.channel_id, c.name, c.channel_image, c.category, u.country
    ORDER BY $orderBy
    LIMIT ? OFFSET ?
";
$videosStmt = $conn->prepare($sql);
$types2 = $types . "ii";
$params2 = array_merge($params, [$perPage, $offset]);
$videosStmt->bind_param($types2, ...$params2);
$videosStmt->execute();
$videos = $videosStmt->get_result();

$recommended = null;
if ($videos->num_rows == 0) {
    $recommendedStmt = $conn->prepare(" 
        SELECT v.video_id, v.title, v.url, v.duration_seconds, v.uploaded_at, v.view_count, v.like_count,
               c.channel_id, c.name AS channel_name, c.channel_image, c.category,
               u.country AS uploader_country,
               DATEDIFF(CURDATE(), DATE(v.uploaded_at)) AS days_ago,
               COUNT(cm.comment_id) AS comment_count
        FROM VIDEOS v
        JOIN CHANNELS c ON v.channel_id = c.channel_id
        JOIN USERS u ON c.owner_id = u.user_id
        LEFT JOIN COMMENTS cm ON cm.video_id = v.video_id
        GROUP BY v.video_id, v.title, v.url, v.duration_seconds, v.uploaded_at, v.view_count,
                 v.like_count, c.channel_id, c.name, c.channel_image, c.category, u.country
        ORDER BY $orderBy
        LIMIT 10
    ");
    $recommendedStmt->execute();
    $recommended = $recommendedStmt->get_result();
}

$topChannels = $conn->query(" 
    SELECT c.channel_id, c.name, c.channel_image, COUNT(s.subscription_id) AS subscriber_count
    FROM CHANNELS c
    LEFT JOIN SUBSCRIPTIONS s ON c.channel_id = s.channel_id
    GROUP BY c.channel_id, c.name, c.channel_image
    ORDER BY subscriber_count DESC, c.channel_id ASC
    LIMIT 5
");


$trendingVideos = $conn->query(" 
    SELECT v.video_id, v.title, v.url, v.view_count, c.name AS channel_name
    FROM VIDEOS v
    JOIN CHANNELS c ON v.channel_id = c.channel_id
    ORDER BY v.view_count DESC, v.like_count DESC
    LIMIT 5
");

$continueWatchingStmt = $conn->prepare(" 
    SELECT v.video_id, v.title, v.url, wh.watched_at, c.name AS channel_name
    FROM WATCH_HISTORY wh
    JOIN VIDEOS v ON wh.video_id = v.video_id
    JOIN CHANNELS c ON v.channel_id = c.channel_id
    WHERE wh.user_id = ?
    ORDER BY wh.watched_at DESC
    LIMIT 5
");
$continueWatchingStmt->bind_param("i", $user_id);
$continueWatchingStmt->execute();
$continueWatching = $continueWatchingStmt->get_result();

function pageUrl($pageNo, $user_id, $q, $sort, $category) {
    return "feed.php?" . http_build_query(["user_id" => $user_id, "q" => $q, "sort" => $sort, "category" => $category, "page" => $pageNo]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Hello, <?php echo htmlspecialchars($user["full_name"]); ?>!</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="header">
        <div class="logo"><span class="logo-mark">▶</span><span>MiniTube</span></div>
        <form class="search-form" method="get" action="feed.php">
            <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
            <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
            <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
            <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search videos by title...">
            <button type="submit">Search</button>
        </form>
        <div class="header-actions">
            <span>Hello, <?php echo htmlspecialchars($user["full_name"]); ?>!</span>
            <a href="profile.php?user_id=<?php echo $user_id; ?>">Profile</a>
            <a href="liked.php?user_id=<?php echo $user_id; ?>">Liked Videos</a>
            <a href="sql.php?user_id=<?php echo $user_id; ?>">SQL Page</a>
            <a href="login.html">Logout</a>
        </div>
    </div>

    <div class="container grid">
        <main>
            <div class="card">
                <h2><?php echo $q !== "" ? "Search results from subscribed channels" : "Latest Videos From Subscribed Channels"; ?></h2>
                <?php if ($q !== ""): ?><p class="meta">Showing results for: <b><?php echo htmlspecialchars($q); ?></b></p><?php endif; ?>

                <form class="sort-form" method="get" action="feed.php">
                    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                    <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search videos...">
                    <select name="category">
                        <option value="">All categories</option>
                        <?php while ($cat = $categories->fetch_assoc()): ?>
                            <option value="<?php echo htmlspecialchars($cat["category"]); ?>" <?php echo $category === $cat["category"] ? "selected" : ""; ?>><?php echo htmlspecialchars($cat["category"]); ?></option>
                        <?php endwhile; ?>
                    </select>
                    <select name="sort">
                        <option value="newest" <?php echo $sort === "newest" ? "selected" : ""; ?>>Newest first</option>
                        <option value="views" <?php echo $sort === "views" ? "selected" : ""; ?>>Most viewed</option>
                        <option value="likes" <?php echo $sort === "likes" ? "selected" : ""; ?>>Most liked</option>
                    </select>
                    <button type="submit">Apply</button>
                </form>

                <?php if ($videos->num_rows == 0 && $recommended === null): ?>
                    <div class="empty-state">No videos matched this filter.</div>
                <?php endif; ?>

                <?php while ($video = $videos->fetch_assoc()): ?>
                    <?php [$badgeLabel, $badgeClass] = badgeInfo(intval($video["view_count"]), $video["uploaded_at"]); ?>
                    <div class="video-card">
                        <a class="thumb-wrap" href="watch.php?user_id=<?php echo $user_id; ?>&video_id=<?php echo $video["video_id"]; ?>">
                            <img class="video-thumb" src="<?php echo htmlspecialchars(youtubeThumbnail($video["url"])); ?>" alt="Video thumbnail">
                            <span class="duration-badge"><?php echo formatDuration(intval($video["duration_seconds"])); ?></span>
                        </a>
                        <div>
                            <h3 class="video-title"><a href="watch.php?user_id=<?php echo $user_id; ?>&video_id=<?php echo $video["video_id"]; ?>"><?php echo htmlspecialchars($video["title"]); ?></a><span class="badge <?php echo $badgeClass; ?>"><?php echo $badgeLabel; ?></span></h3>
                            <div class="channel-line">
                                <img class="small-img" src="<?php echo htmlspecialchars($video["channel_image"]); ?>" onerror="this.onerror=null;this.src='<?php echo fallbackAvatar($video["channel_name"]); ?>';" alt="Channel">
                                <a href="channel.php?user_id=<?php echo $user_id; ?>&channel_id=<?php echo $video["channel_id"]; ?>"><?php echo htmlspecialchars($video["channel_name"]); ?></a>
                            </div>
                            <p class="meta"><?php echo intval($video["view_count"]); ?> views • <?php echo intval($video["like_count"]); ?> likes • <?php echo intval($video["comment_count"]); ?> comments • <?php echo htmlspecialchars($video["category"]); ?></p>
                            <p class="meta">Uploader country: <?php echo htmlspecialchars($video["uploader_country"]); ?> • Uploaded <?php echo intval($video["days_ago"]); ?> days ago</p>
                        </div>
                    </div>
                <?php endwhile; ?>

                <?php if ($totalRows > 0): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?><a class="btn" href="<?php echo pageUrl($page - 1, $user_id, $q, $sort, $category); ?>">Previous</a><?php endif; ?>
                        <span>Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                        <?php if ($page < $totalPages): ?><a class="btn" href="<?php echo pageUrl($page + 1, $user_id, $q, $sort, $category); ?>">Next</a><?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($recommended !== null): ?>
                    <div class="empty-state"><?php echo $q === "" ? "You are not subscribed to a matching channel yet. Here are popular videos to start with." : "No exact search result found. Here are popular videos instead."; ?></div>
                    <?php while ($video = $recommended->fetch_assoc()): ?>
                        <?php [$badgeLabel, $badgeClass] = badgeInfo(intval($video["view_count"]), $video["uploaded_at"]); ?>
                        <div class="video-card">
                            <a class="thumb-wrap" href="watch.php?user_id=<?php echo $user_id; ?>&video_id=<?php echo $video["video_id"]; ?>">
                                <img class="video-thumb" src="<?php echo htmlspecialchars(youtubeThumbnail($video["url"])); ?>" alt="Video thumbnail">
                                <span class="duration-badge"><?php echo formatDuration(intval($video["duration_seconds"])); ?></span>
                            </a>
                            <div>
                                <h3 class="video-title"><a href="watch.php?user_id=<?php echo $user_id; ?>&video_id=<?php echo $video["video_id"]; ?>"><?php echo htmlspecialchars($video["title"]); ?></a><span class="badge <?php echo $badgeClass; ?>"><?php echo $badgeLabel; ?></span></h3>
                                <div class="channel-line"><img class="small-img" src="<?php echo htmlspecialchars($video["channel_image"]); ?>" onerror="this.onerror=null;this.src='<?php echo fallbackAvatar($video["channel_name"]); ?>';" alt="Channel"><a href="channel.php?user_id=<?php echo $user_id; ?>&channel_id=<?php echo $video["channel_id"]; ?>"><?php echo htmlspecialchars($video["channel_name"]); ?></a></div>
                                <p class="meta"><?php echo intval($video["view_count"]); ?> views • <?php echo intval($video["like_count"]); ?> likes • <?php echo intval($video["comment_count"]); ?> comments • <?php echo htmlspecialchars($video["category"]); ?></p>
                                <p class="meta">Uploader country: <?php echo htmlspecialchars($video["uploader_country"]); ?> • Uploaded <?php echo intval($video["days_ago"]); ?> days ago</p>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
        </main>

        <aside>
            <div class="card">
                <h2>Top 5 Channels</h2>
                <?php while ($channel = $topChannels->fetch_assoc()): ?>
                    <div class="side-channel">
                        <img class="small-img" src="<?php echo htmlspecialchars($channel["channel_image"]); ?>" onerror="this.onerror=null;this.src='<?php echo fallbackAvatar($channel["name"]); ?>';" alt="Channel">
                        <div><a href="channel.php?user_id=<?php echo $user_id; ?>&channel_id=<?php echo $channel["channel_id"]; ?>"><?php echo htmlspecialchars($channel["name"]); ?></a><p class="meta"><?php echo intval($channel["subscriber_count"]); ?> subscribers</p></div>
                    </div>
                <?php endwhile; ?>
            </div>

            <div class="card">
                <h2>Trending Videos</h2>
                <?php while ($tv = $trendingVideos->fetch_assoc()): ?>
                    <div class="mini-video">
                        <a href="watch.php?user_id=<?php echo $user_id; ?>&video_id=<?php echo $tv["video_id"]; ?>">
                            <img src="<?php echo htmlspecialchars(youtubeThumbnail($tv["url"])); ?>" alt="Trending thumbnail">
                        </a>
                        <div>
                            <a href="watch.php?user_id=<?php echo $user_id; ?>&video_id=<?php echo $tv["video_id"]; ?>"><?php echo htmlspecialchars($tv["title"]); ?></a>
                            <p class="meta"><?php echo intval($tv["view_count"]); ?> views • <?php echo htmlspecialchars($tv["channel_name"]); ?></p>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>

            <div class="card">
                <h2>Continue Watching</h2>
                <?php if ($continueWatching->num_rows == 0): ?>
                    <div class="empty-state">Videos you open will appear here.</div>
                <?php endif; ?>
                <?php while ($cw = $continueWatching->fetch_assoc()): ?>
                    <div class="mini-video">
                        <a href="watch.php?user_id=<?php echo $user_id; ?>&video_id=<?php echo $cw["video_id"]; ?>">
                            <img src="<?php echo htmlspecialchars(youtubeThumbnail($cw["url"])); ?>" alt="Continue watching thumbnail">
                        </a>
                        <div>
                            <a href="watch.php?user_id=<?php echo $user_id; ?>&video_id=<?php echo $cw["video_id"]; ?>"><?php echo htmlspecialchars($cw["title"]); ?></a>
                            <p class="meta"><?php echo htmlspecialchars($cw["channel_name"]); ?></p>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>

            <div class="card">
                <h2>User Profile</h2>
                <div class="channel-line"><img class="small-img" src="<?php echo htmlspecialchars($user["user_image"]); ?>" onerror="this.onerror=null;this.src='<?php echo fallbackAvatar($user["full_name"]); ?>';" alt="User"><b><?php echo htmlspecialchars($user["full_name"]); ?></b></div>
                <p class="meta">Country: <?php echo htmlspecialchars($user["country"]); ?></p>
                <p class="meta">Joined on: <?php echo htmlspecialchars($user["joined_on"]); ?></p>
                <p><?php echo htmlspecialchars($user["bio"]); ?></p>
                <a class="profile-link" href="profile.php?user_id=<?php echo $user_id; ?>">Open full profile</a>
                <?php if ($ownChannel): ?><br><a class="profile-link" href="channel.php?user_id=<?php echo $user_id; ?>&channel_id=<?php echo $ownChannel["channel_id"]; ?>">Open my channel: <?php echo htmlspecialchars($ownChannel["name"]); ?></a><?php endif; ?>
            </div>
        </aside>
    </div>
</body>
</html>
<?php $conn->close(); ?>
