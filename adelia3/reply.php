<?php
declare(strict_types=1);

session_start();

// Setup error logging
error_reporting(E_ALL);
ini_set('display_errors', '1');
touch(__DIR__ . '/error.txt'); // Ensure error file exists
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/error.txt');

// Define constants here as well
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_URL', 'uploads/');
define('MAX_FILE_SIZE', 20 * 1024 * 1024);
define('ALLOWED_MIME_TYPES', [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
    'video/mp4'
]);

$SECURE_SALT = 'mysecretsaltchangeit';

// Database with exceptions enabled
try {
    $db = new SQLite3(__DIR__ . '/board.db', SQLITE3_OPEN_READWRITE);
    $db->enableExceptions(true);
} catch (Throwable $e) {
    die("Database error: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

function sanitize(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function generateTripcode(string $name, string $secure_salt): string {
    if (str_contains($name, '##')) {
        [$displayName, $tripPart] = explode('##', $name, 2);
        $displayName = trim($displayName);
        $tripPart = trim($tripPart);

        if ($tripPart !== '') {
            $tripcode = substr(crypt($tripPart, $secure_salt), -10);
            return $displayName . ' !!' . $tripcode;
        } else {
            return $displayName;
        }
    } elseif (str_contains($name, '#')) {
        [$displayName, $tripPart] = explode('#', $name, 2);
        $displayName = trim($displayName);
        $tripPart = trim($tripPart);

        if ($tripPart !== '') {
            $salt = substr($tripPart . 'H.', 1, 2);
            $salt = preg_replace('/[^\.-z]/', '.', $salt);
            $tripcode = substr(crypt($tripPart, $salt), -10);
            return $displayName . ' !' . $tripcode;
        } else {
            return $displayName;
        }
    } else {
        return trim($name);
    }
}

function generateCsrfToken(): string {
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    return $token;
}

function verifyCsrfToken(?string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Get thread ID
$thread_id = isset($_GET['thread']) ? (int)$_GET['thread'] : 0;
if ($thread_id < 1) {
    die("Invalid thread ID.");
}

// Check thread exists
try {
    $thread = $db->querySingle("SELECT * FROM posts WHERE id = $thread_id AND parent = 0", true);
} catch (Throwable $e) {
    die("Database error checking thread: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

if (!$thread) {
    die("Thread not found.");
}

// Handle POST (reply)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? null;
    if (!verifyCsrfToken($csrf)) {
        die("Invalid CSRF token.");
    }

    $name = sanitize($_POST['name'] ?? '');
    $message = sanitize($_POST['message'] ?? '');

    if (strlen($name) < 1 || strlen($message) < 3) {
        die("Name and message are required, and message must be at least 3 characters.");
    }

    $name = generateTripcode($name, $SECURE_SALT);
    $parent = $thread_id;
    $title = '';
    $image = null; 
    $time = time();

    try {
        $stmt = $db->prepare("INSERT INTO posts (parent, name, title, message, image, created_at, updated_at) VALUES (:parent, :name, :title, :message, :image, :created_at, :updated_at)");
        $stmt->bindValue(':parent', $parent, SQLITE3_INTEGER);
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $stmt->bindValue(':title', $title, SQLITE3_TEXT);
        $stmt->bindValue(':message', $message, SQLITE3_TEXT);
        $stmt->bindValue(':image', $image, SQLITE3_TEXT);
        $stmt->bindValue(':created_at', $time, SQLITE3_INTEGER);
        $stmt->bindValue(':updated_at', $time, SQLITE3_INTEGER);
        $stmt->execute();

        // Update thread updated_at
        $update_stmt = $db->prepare("UPDATE posts SET updated_at = :updated_at WHERE id = :id");
        $update_stmt->bindValue(':updated_at', $time, SQLITE3_INTEGER);
        $update_stmt->bindValue(':id', $thread_id, SQLITE3_INTEGER);
        $update_stmt->execute();
    } catch (Throwable $e) {
        die("Database error while inserting reply: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
    }

    header('Location: reply.php?thread=' . $thread_id);
    exit;
}

// Generate CSRF token on GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_SESSION['csrf_token'])) {
        generateCsrfToken();
    }
}
$csrfToken = $_SESSION['csrf_token'] ?? '';

// Fetch all replies, order by creation time to number them sequentially
try {
    $replies = $db->query("SELECT * FROM posts WHERE parent = $thread_id ORDER BY created_at ASC");
} catch (Throwable $e) {
    die("Database error fetching replies: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reply Mode</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h1>Reply Mode</h1>
    <p class="back-link"><a href="index.php">Back to Board</a></p>

    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <input type="text" name="name" placeholder="Name (tripcodes allowed)" required>
        <textarea name="message" rows="4" placeholder="Message" required></textarea>
        <button type="submit">Post Reply</button>
    </form>

    <hr>

    <!-- Show original post -->
    <div class="thread">
        <div class="post-info">
            <strong><?= sanitize($thread['name']) ?></strong> | <?= sanitize($thread['title']) ?>
        </div>
        <?php if ($thread['image']):
            $ext = pathinfo($thread['image'], PATHINFO_EXTENSION);
            if ($ext === 'mp4'): ?>
                <video controls>
                    <source src="<?= htmlspecialchars(UPLOAD_URL . $thread['image'], ENT_QUOTES, 'UTF-8') ?>" type="video/mp4">
                    Your browser does not support the video tag.
                </video>
            <?php else: ?>
                <img src="<?= htmlspecialchars(UPLOAD_URL . $thread['image'], ENT_QUOTES, 'UTF-8') ?>" alt="Image">
            <?php endif; ?>
        <?php endif; ?>
        <p><?= nl2br(sanitize($thread['message'])) ?></p>
    </div>

    <!-- Show replies below, increment a counter to display reply number -->
    <?php $replyCount = 0; ?>
    <?php while ($reply = $replies->fetchArray(SQLITE3_ASSOC)): ?>
        <?php $replyCount++; ?>
        <div class="reply-post">
            <div class="post-info">
                <strong><?= sanitize($reply['name']) ?></strong> | Reply #<?= $replyCount ?>
            </div>
            <p><?= nl2br(sanitize($reply['message'])) ?></p>
        </div>
    <?php endwhile; ?>
</body>
</html>
