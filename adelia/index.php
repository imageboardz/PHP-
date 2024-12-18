<?php
declare(strict_types=1);

session_start();

// Setup error logging
error_reporting(E_ALL);
ini_set('display_errors', '1');
touch(__DIR__ . '/error.txt'); // Ensure error file exists
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/error.txt');

define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_URL', 'uploads/');
define('MAX_FILE_SIZE', 20 * 1024 * 1024); // 20 MB
define('ALLOWED_MIME_TYPES', [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
    'video/mp4'
]);

$SECURE_SALT = 'mysecretsaltchangeit';

// Ensure upload directory exists
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

// Database
$db = new SQLite3(__DIR__ . '/board.db', SQLITE3_OPEN_CREATE | SQLITE3_OPEN_READWRITE);
$db->exec("CREATE TABLE IF NOT EXISTS posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    parent INTEGER NOT NULL DEFAULT 0,
    name TEXT NOT NULL,
    title TEXT NOT NULL,
    message TEXT NOT NULL,
    image TEXT DEFAULT NULL,
    created_at INTEGER NOT NULL,
    updated_at INTEGER NOT NULL DEFAULT 0
)");

function sanitize(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function generateCsrfToken(): string {
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    return $token;
}

function verifyCsrfToken(?string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function generateTripcode(string $name, string $secure_salt): string {
    if (strpos($name, '##') !== false) {
        [$displayName, $tripPart] = explode('##', $name, 2);
        $displayName = trim($displayName);
        $tripPart = trim($tripPart);

        if ($tripPart !== '') {
            $tripcode = substr(crypt($tripPart, $secure_salt), -10);
            return $displayName . ' !!' . $tripcode;
        } else {
            return $displayName;
        }
    } elseif (strpos($name, '#') !== false) {
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

function saveFile(array $file): ?string {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, ALLOWED_MIME_TYPES, true)) {
        return null;
    }

    if (str_starts_with($mime, 'image/')) {
        $imageInfo = getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            return null;
        }
    }

    $extension = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
        'video/mp4'  => 'mp4',
        default       => null
    };

    if (!$extension) {
        return null;
    }

    $filename = bin2hex(random_bytes(8)) . '.' . $extension;
    $filepath = UPLOAD_DIR . $filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return $filename;
    }

    return null;
}

// Handle new thread POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? null;
    if (!verifyCsrfToken($csrf)) {
        die("Invalid CSRF token.");
    }

    $parent = (int)($_POST['parent'] ?? 0);
    if ($parent !== 0) {
        die("Invalid request: no replies allowed here. Use reply.php.");
    }

    $name = sanitize($_POST['name'] ?? '');
    $title = sanitize($_POST['title'] ?? '');
    $message = sanitize($_POST['message'] ?? '');

    if (strlen($name) < 1 || strlen($title) < 1 || strlen($message) < 3) {
        die("Name, title, and message are required. Message must be at least 3 characters.");
    }

    $name = generateTripcode($name, $SECURE_SALT);

    $image = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $image = saveFile($_FILES['image']);
    }

    $time = time();
    $stmt = $db->prepare("INSERT INTO posts (parent, name, title, message, image, created_at, updated_at) VALUES (:parent, :name, :title, :message, :image, :created_at, :updated_at)");
    $stmt->bindValue(':parent', 0, SQLITE3_INTEGER);
    $stmt->bindValue(':name', $name, SQLITE3_TEXT);
    $stmt->bindValue(':title', $title, SQLITE3_TEXT);
    $stmt->bindValue(':message', $message, SQLITE3_TEXT);
    $stmt->bindValue(':image', $image, SQLITE3_TEXT);
    $stmt->bindValue(':created_at', $time, SQLITE3_INTEGER);
    $stmt->bindValue(':updated_at', $time, SQLITE3_INTEGER);
    $stmt->execute();

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Generate CSRF token on GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_SESSION['csrf_token'])) {
        generateCsrfToken();
    }
}

$csrfToken = $_SESSION['csrf_token'] ?? '';

// Fetch all threads ordered by updated_at DESC
$threads = $db->query("SELECT * FROM posts WHERE parent = 0 ORDER BY updated_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Imageboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h1>Simple Imageboard</h1>

    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="parent" value="0">
        <input type="text" name="name" placeholder="Name (tripcodes allowed)" required>
        <input type="text" name="title" placeholder="Title" required>
        <textarea name="message" rows="4" placeholder="Message" required></textarea>
        <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp,video/mp4">
        <button type="submit">Post Thread</button>
    </form>

    <hr>

    <?php
    $found_threads = false;
    while ($thread = $threads->fetchArray(SQLITE3_ASSOC)):
        $found_threads = true;
        $thread_id = (int)$thread['id'];
        ?>
        <div class="thread">
            <div class="post-info">
                <a href="reply.php?thread=<?= $thread_id ?>" style="font-weight: bold;">[Reply]</a>
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

            <?php
            // Fetch last 4 replies
            $replies_result = $db->query("SELECT * FROM posts WHERE parent = $thread_id ORDER BY created_at DESC LIMIT 4");
            $replies = [];
            while ($r = $replies_result->fetchArray(SQLITE3_ASSOC)) {
                array_unshift($replies, $r); // reverse order to show oldest first
            }

            foreach ($replies as $reply):
            ?>
                <div class="reply-snippet">
                    <div class="post-info">
                        <strong><?= sanitize($reply['name']) ?></strong> | Reply #<?= (int)$reply['id'] ?>
                    </div>
                    <p><?= nl2br(sanitize($reply['message'])) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endwhile; ?>

    <?php if (!$found_threads): ?>
        <div class="no-threads">No threads found.</div>
    <?php endif; ?>

</body>
</html>
