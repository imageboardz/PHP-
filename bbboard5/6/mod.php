<?php
// define the password
$password = 'a777';

// create the database if it doesn't exist
$db = new SQLite3('forum.db');
$db->exec('CREATE TABLE IF NOT EXISTS messages (id INTEGER PRIMARY KEY AUTOINCREMENT, content TEXT)');

// check if the user has submitted a post ID and the correct password to delete
if (isset($_POST['id']) && isset($_POST['password']) && $_POST['password'] == $password) {
    $id = $_POST['id'];
    $stmt = $db->prepare('DELETE FROM messages WHERE id = :id');
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $stmt->execute();
    echo 'Post ' . $id . ' has been deleted.';
}

// display a form to input the post ID and the password to delete
echo '
    <html>
        <head>
            <link rel="stylesheet" type="text/css" href="style.css">
        </head>
        <body>
            <form method="post">
                <input type="text" name="id" placeholder="Post ID" required>
                <br>
                <input type="password" name="password" placeholder="Password" required>
                <br>
                <input type="submit" value="Delete">
            </form>
        </body>
    </html>
';
?>

