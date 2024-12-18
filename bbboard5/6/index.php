<?php
session_start();

// create the database if it doesn't exist
$db = new SQLite3('forum.db');
$db->exec('CREATE TABLE IF NOT EXISTS messages (id INTEGER PRIMARY KEY AUTOINCREMENT, content TEXT)');

// handle form submission
if (isset($_POST['message']) && isset($_POST['captcha']) && isset($_SESSION['captcha']) && $_POST['captcha'] == $_SESSION['captcha']) {
    // sanitize and validate the message input
    $message = trim($_POST['message']);
    if (!empty($message) && strlen($message) <= 1000) { // limit message length to 1000 characters
        $message = htmlspecialchars($message); // sanitize HTML tags
        $stmt = $db->prepare('INSERT INTO messages (content) VALUES (:content)');
        $stmt->bindValue(':content', $message, SQLITE3_TEXT);
        $stmt->execute();
    } else {
        echo '<p style="color: red;">Message must not be empty and must not exceed 1000 characters.</p>';
    }
} else if (isset($_POST['message'])) {
    // captcha was answered incorrectly, display error message
    echo '<p style="color: red;">Incorrect captcha, please try again.</p>';
}

// generate new captcha
$num1 = rand(1, 10);
$num2 = rand(1, 10);
$answer = $num1 + $num2;
$captcha = $num1 . " + " . $num2 . " = ";

// store captcha in session for validation
$_SESSION['captcha'] = $answer;

// pagination
$page_size = 10; // number of messages to display per page
$page_num = isset($_GET['page']) ? intval($_GET['page']) : 1; // current page number
$offset = ($page_num - 1) * $page_size;
$total_messages = $db->querySingle('SELECT COUNT(*) FROM messages');
$total_pages = ceil($total_messages / $page_size);

// retrieve messages for the current page
$stmt = $db->prepare('SELECT * FROM messages ORDER BY id DESC LIMIT :limit OFFSET :offset');
$stmt->bindValue(':limit', $page_size, SQLITE3_INTEGER);
$stmt->bindValue(':offset', $offset, SQLITE3_INTEGER);
$result = $stmt->execute();

// display the form and messages
echo '
    <html>
        <head>
            <link rel="stylesheet" type="text/css" href="style.css">
        </head>
        <body>
            <form method="post">
                <textarea name="message" placeholder="message" required></textarea>
                <br>
                <label for="captcha" style="background-color: black; color: lime;">' . $captcha . '</label>
               
                <input type="text" name="captcha" pattern="\d{1,2}" size="2" maxlength="2" required>


                <br>
                <input type="submit" value="Post">
            </form>
';

while ($row = $result->fetchArray()) {
    $id = $row['id'];
    $content = htmlspecialchars($row['content']); // sanitize HTML tags
    echo '
            <div class="message">
                <strong>' . $id . '</strong>
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

echo '
        </body>
    </html>
';
?>

