<?php
// create the database if it doesn't exist
$db = new SQLite3('forum.db');
$db->exec('CREATE TABLE IF NOT EXISTS messages (id INTEGER PRIMARY KEY AUTOINCREMENT, content TEXT, timestamp DATETIME DEFAULT CURRENT_TIMESTAMP)');

// handle form submission
if (isset($_POST['message'])) {
    // sanitize and validate the message input
    $message = trim($_POST['message']);
    if (!empty($message)) {
        $message = htmlspecialchars($message); // sanitize HTML tags
        $stmt = $db->prepare('INSERT INTO messages (content) VALUES (:content)');
        $stmt->bindValue(':content', $message, SQLITE3_TEXT);
        $stmt->execute();
    }
}

// pagination
$page_size = 10; // number of messages to display per page
$page_num = isset($_GET['page']) ? intval($_GET['page']) : 1; // current page number
$offset = ($page_num - 1) * $page_size;
$total_messages = $db->querySingle('SELECT COUNT(*) FROM messages');
$total_pages = ceil($total_messages / $page_size);

// retrieve messages for the current page
$stmt = $db->prepare('SELECT * FROM messages ORDER BY timestamp DESC LIMIT :limit OFFSET :offset');
$stmt->bindValue(':limit', $page_size, SQLITE3_INTEGER);
$stmt->bindValue(':offset', $offset, SQLITE3_INTEGER);
$result = $stmt->execute();

// display the form
echo '
    <form method="post">
        <textarea name="message" placeholder="message" required></textarea>
        <br>
        <input type="submit" value="Post">
    </form>
';

// display the messages for the current page
while ($row = $result->fetchArray()) {
    $timestamp = htmlspecialchars($row['timestamp']); // sanitize HTML tags
    $content = htmlspecialchars($row['content']); // sanitize HTML tags
    echo '
        <div>
            <strong>' . $timestamp . '</strong>
            <p>' . $content . '</p>
        </div>
    ';
}

// display pagination links
if ($total_pages > 1) {
    echo '<p>Page ' . $page_num . ' of ' . $total_pages . '</p>';
    for ($i = 1; $i <= $total_pages; $i++) {
        if ($i == $page_num) {
            echo $i . ' ';
        } else {
            echo '<a href="?page=' . $i . '">' . $i . '</a> ';
        }
    }
}
?>

