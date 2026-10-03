<?php
/*
  Şevket Yeşilpekmez
  MiniTube Project - sql.php

  Allows arbitrary SQL queries.
*/

require_once "db.php";

$user_id = intval($_GET["user_id"] ?? $_POST["user_id"] ?? 0);
$query = "";
$message = "";
$resultRows = null;
$columns = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $query = trim($_POST["query"] ?? "");

    if ($query !== "") {
        $result = $conn->query($query);

        if ($result === false) {
            $message = "<p class='error'>SQL Error: " . htmlspecialchars($conn->error) . "</p>";
        } else {
            $queryType = strtoupper(strtok(ltrim($query), " "));

            if ($queryType === "SELECT" || $queryType === "SHOW" || $queryType === "DESCRIBE") {
                $resultRows = [];
                $fieldInfo = $result->fetch_fields();

                foreach ($fieldInfo as $field) {
                    $columns[] = $field->name;
                }

                $count = 0;
                while (($row = $result->fetch_assoc()) && $count < 10) {
                    $resultRows[] = $row;
                    $count++;
                }
            } else {
                $message = "<p class='success'>Affected rows: " . $conn->affected_rows . "</p>";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SQL Page</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="header">
        <div><b>MiniTube SQL Page</b></div>
        <div>
            <a href="feed.php?user_id=<?php echo $user_id; ?>">Homepage</a>
        </div>
    </div>

    <div class="container">
        <div class="card">
            <h1>Run SQL Query</h1>
            <form method="post">
                <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                <textarea name="query" rows="6" placeholder="Write SQL query here..."><?php echo htmlspecialchars($query); ?></textarea>
                <button type="submit">Execute</button>
            </form>
        </div>

        <?php if ($query !== ""): ?>
            <div class="card">
                <h2>Executed Query</h2>
                <div class="query-box"><?php echo htmlspecialchars($query); ?></div>
            </div>
        <?php endif; ?>

        <?php if ($message !== ""): ?>
            <div class="card">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($resultRows !== null): ?>
            <div class="card">
                <h2>Result</h2>
                <?php if (count($resultRows) == 0): ?>
                    <p>No rows returned.</p>
                <?php else: ?>
                    <table>
                        <tr>
                            <?php foreach ($columns as $col): ?>
                                <th><?php echo htmlspecialchars($col); ?></th>
                            <?php endforeach; ?>
                        </tr>
                        <?php foreach ($resultRows as $row): ?>
                            <tr>
                                <?php foreach ($columns as $col): ?>
                                    <td><?php echo htmlspecialchars((string)$row[$col]); ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
<?php $conn->close(); ?>
