<?php
// Enable error reporting for all types of errors
error_reporting(E_ALL);

// Do not display errors to the user
ini_set('display_errors', '0');

// Enable error logging
ini_set('log_errors', '1');

// Set the path to the error log file
ini_set('error_log', __DIR__ . '/error.log');

// Start session for future enhancements
session_start();

// Configuration
define('ADELIA_BOARD_DESC', 'Adelia Imageboard');
define('ADELIA_THREADS_PER_PAGE', 10);
define('ADELIA_REPLIES_PREVIEW', 3);
define('ADELIA_MAX_LINES', 15);
define('ADELIA_TIMEZONE', 'UTC');
define('ADELIA_MAX_FILE_SIZE', 2048 * 1024); // 2 MB
define('ADELIA_UPLOAD_DIR', __DIR__ . '/uploads/');
define('ADELIA_THUMB_DIR', __DIR__ . '/thumbs/');
define('ADELIA_ALLOWED_MIME', ['image/jpeg', 'image/png', 'image/gif']);
define('ADELIA_THUMB_WIDTH', 250);
define('ADELIA_THUMB_HEIGHT', 250);

// Ensure upload directories exist
foreach ([ADELIA_UPLOAD_DIR, ADELIA_THUMB_DIR] as $dir) {
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            error_log("Failed to create directory: $dir");
            die("An unexpected error occurred. Please try again later.");
        }
    }
}

// Set timezone
date_default_timezone_set(ADELIA_TIMEZONE);

// Initialize SQLite3 Database
$db = new SQLite3(__DIR__ . '/adelia.db');
if (!$db) {
    error_log('Failed to connect to the database.');
    die('An unexpected error occurred. Please try again later.');
}

// Create posts table if not exists
$db->exec("CREATE TABLE IF NOT EXISTS posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    parent INTEGER NOT NULL,
    timestamp INTEGER NOT NULL,
    bumped INTEGER NOT NULL,
    ip TEXT NOT NULL,
    subject TEXT,
    message TEXT NOT NULL,
    file TEXT,
    thumb TEXT
)");

// Utility Functions
function sanitize(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function generateUniqueFilename(string $extension): string {
    return uniqid('', true) . '.' . $extension;
}

function createThumbnail(string $sourcePath, string $thumbPath): bool {
    list($width, $height, $type) = getimagesize($sourcePath);
    switch ($type) {
        case IMAGETYPE_JPEG:
            $srcImg = imagecreatefromjpeg($sourcePath);
            break;
        case IMAGETYPE_PNG:
            $srcImg = imagecreatefrompng($sourcePath);
            break;
        case IMAGETYPE_GIF:
            $srcImg = imagecreatefromgif($sourcePath);
            break;
        default:
            return false;
    }

    if (!$srcImg) {
        error_log('Failed to create image from source: ' . $sourcePath);
        return false;
    }

    // Calculate thumbnail size while maintaining aspect ratio
    $ratio = min(ADELIA_THUMB_WIDTH / $width, ADELIA_THUMB_HEIGHT / $height);
    $thumbWidth = (int)($width * $ratio);
    $thumbHeight = (int)($height * $ratio);

    $thumbImg = imagecreatetruecolor($thumbWidth, $thumbHeight);
    
    // Preserve transparency for PNG and GIF
    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
        imagecolortransparent($thumbImg, imagecolorallocatealpha($thumbImg, 0, 0, 0, 127));
        imagealphablending($thumbImg, false);
        imagesavealpha($thumbImg, true);
    }

    if (!imagecopyresampled($thumbImg, $srcImg, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height)) {
        error_log('Failed to resample image: ' . $sourcePath);
        imagedestroy($srcImg);
        imagedestroy($thumbImg);
        return false;
    }

    // Save thumbnail
    $saved = false;
    switch ($type) {
        case IMAGETYPE_JPEG:
            $saved = imagejpeg($thumbImg, $thumbPath, 85);
            break;
        case IMAGETYPE_PNG:
            $saved = imagepng($thumbImg, $thumbPath);
            break;
        case IMAGETYPE_GIF:
            $saved = imagegif($thumbImg, $thumbPath);
            break;
    }

    imagedestroy($srcImg);
    imagedestroy($thumbImg);

    if (!$saved) {
        error_log('Failed to save thumbnail to: ' . $thumbPath);
    }

    return $saved;
}

function getThreads(int $page, int $perPage, SQLite3 $db): array {
    $offset = ($page - 1) * $perPage;
    $stmt = $db->prepare("SELECT * FROM posts WHERE parent = 0 ORDER BY bumped DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $perPage, SQLITE3_INTEGER);
    $stmt->bindValue(':offset', $offset, SQLITE3_INTEGER);
    $result = $stmt->execute();

    $threads = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $row['reply_count'] = countReplies($row['id'], $db);
        $threads[] = $row;
    }
    return $threads;
}

function getTotalThreads(SQLite3 $db): int {
    $result = $db->querySingle("SELECT COUNT(*) as count FROM posts WHERE parent = 0", true);
    return (int)$result['count'];
}

function getReplies(int $threadId, SQLite3 $db): array {
    $stmt = $db->prepare("SELECT * FROM posts WHERE parent = :parent ORDER BY timestamp ASC");
    $stmt->bindValue(':parent', $threadId, SQLITE3_INTEGER);
    $result = $stmt->execute();

    $replies = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $replies[] = $row;
    }
    return $replies;
}

function insertPost(SQLite3 $db, array $data): int {
    $stmt = $db->prepare("INSERT INTO posts (parent, timestamp, bumped, ip, subject, message, file, thumb) VALUES (:parent, :timestamp, :bumped, :ip, :subject, :message, :file, :thumb)");
    $stmt->bindValue(':parent', $data['parent'], SQLITE3_INTEGER);
    $stmt->bindValue(':timestamp', time(), SQLITE3_INTEGER);
    $stmt->bindValue(':bumped', time(), SQLITE3_INTEGER);
    $stmt->bindValue(':ip', $_SERVER['REMOTE_ADDR'], SQLITE3_TEXT);
    $stmt->bindValue(':subject', $data['subject'], SQLITE3_TEXT);
    $stmt->bindValue(':message', $data['message'], SQLITE3_TEXT);
    $stmt->bindValue(':file', $data['file'], SQLITE3_TEXT);
    $stmt->bindValue(':thumb', $data['thumb'], SQLITE3_TEXT);
    $stmt->execute();

    return (int)$db->lastInsertRowID();
}

function countReplies(int $threadId, SQLite3 $db): int {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM posts WHERE parent = :parent");
    $stmt->bindValue(':parent', $threadId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    return (int)$row['count'];
}

function escapeOutput(string $text): string {
    return nl2br($text);
}

function displayPagination(int $currentPage, int $threadsPerPage, SQLite3 $db): string {
    $totalPages = (int)ceil(getTotalThreads($db) / $threadsPerPage);
    if ($totalPages <= 1) {
        return '';
    }

    $pagination = '<div class="pagination">';
    if ($currentPage > 1) {
        $pagination .= '<a href="?page=' . ($currentPage - 1) . '">&laquo; Previous</a>';
    } else {
        $pagination .= '<span class="disabled">&laquo; Previous</span>';
    }

    for ($i = 1; $i <= $totalPages; $i++) {
        if ($i === $currentPage) {
            $pagination .= '<span class="current">' . $i . '</span>';
        } else {
            $pagination .= '<a href="?page=' . $i . '">' . $i . '</a>';
        }
    }

    if ($currentPage < $totalPages) {
        $pagination .= '<a href="?page=' . ($currentPage + 1) . '">Next &raquo;</a>';
    } else {
        $pagination .= '<span class="disabled">Next &raquo;</span>';
    }

    $pagination .= '</div>';
    return $pagination;
}

// Handle POST Requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $parent = isset($_POST['parent']) ? (int)$_POST['parent'] : 0;
    $subject = isset($_POST['subject']) ? sanitize($_POST['subject']) : '';
    $message = isset($_POST['message']) ? sanitize($_POST['message']) : '';

    // Validate message
    if (empty($message)) {
        die('Message cannot be empty.');
    }

    // Handle file upload
    $file = null;
    $thumb = null;
    if (isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            die('File upload error.');
        }

        if ($_FILES['file']['size'] > ADELIA_MAX_FILE_SIZE) {
            die('File exceeds maximum allowed size of 2 MB.');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES['file']['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, ADELIA_ALLOWED_MIME)) {
            die('Unsupported file type.');
        }

        $extension = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            default => '',
        };

        if ($extension === '') {
            die('Unsupported file extension.');
        }

        $uniqueFilename = generateUniqueFilename($extension);
        $destination = ADELIA_UPLOAD_DIR . $uniqueFilename;

        if (!move_uploaded_file($_FILES['file']['tmp_name'], $destination)) {
            die('Failed to move uploaded file.');
        }

        // Create thumbnail
        $thumbFilename = 'thumb_' . $uniqueFilename;
        $thumbPath = ADELIA_THUMB_DIR . $thumbFilename;
        if (!createThumbnail($destination, $thumbPath)) {
            unlink($destination);
            die('Failed to create thumbnail.');
        }

        $file = $uniqueFilename;
        $thumb = $thumbFilename;
    }

    // Insert post into database
    $postData = [
        'parent' => $parent,
        'subject' => $subject,
        'message' => $message,
        'file' => $file,
        'thumb' => $thumb
    ];

    $postId = insertPost($db, $postData);

    // Update bumped time if it's a reply and bump the original post
    if ($parent !== 0) {
        $stmt = $db->prepare("UPDATE posts SET bumped = :bumped WHERE id = :id");
        $stmt->bindValue(':bumped', time(), SQLITE3_INTEGER);
        $stmt->bindValue(':id', $parent, SQLITE3_INTEGER);
        $stmt->execute();
    }

    // Redirect to the appropriate page
    if ($parent === 0) {
        header('Location: ?');
    } else {
        header('Location: ?thread=' . $parent . '#post' . $postId);
    }
    exit;
}

// Determine current page
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

// Fetch threads for current page
$threads = getThreads($currentPage, ADELIA_THREADS_PER_PAGE, $db);

// HTML and CSS
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars(ADELIA_BOARD_DESC) ?></title>
    <link rel="stylesheet" href="adelia.css">
</head>
<body>
    <?php
    // Display Thread View if requested
    if (isset($_GET['thread'])) {
        $threadId = (int)$_GET['thread'];
        $mainThread = null;

        // Fetch the main thread post
        $stmt = $db->prepare("SELECT * FROM posts WHERE id = :id AND parent = 0 LIMIT 1");
        $stmt->bindValue(':id', $threadId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $mainThread = $result->fetchArray(SQLITE3_ASSOC);

        if ($mainThread) {
            // Reply Mode Label and Link Back
            echo '<div class="replymode">';
            echo '<strong>Reply Mode</strong> | <a href="' . htmlspecialchars($_SERVER['PHP_SELF']) . '">Back to Main Board</a>';
            echo '</div>';

            // Reply Form at the Top
            echo '<div class="postarea">';
            echo '<form class="postform" action="" method="post" enctype="multipart/form-data">';
            echo '<input type="hidden" name="parent" value="' . $threadId . '">';
            echo '<label for="file">Image (optional)</label>';
            echo '<input type="file" id="file" name="file" accept="image/jpeg,image/png,image/gif">';

            echo '<label for="subject">Subject (optional)</label>';
            echo '<input type="text" id="subject" name="subject" maxlength="75">';

            echo '<label for="message">Message</label>';
            echo '<textarea id="message" name="message" rows="4" maxlength="8000" required></textarea>';

            echo '<input type="submit" value="Reply">';
            echo '</form>';
            echo '</div>';

            echo '<hr>';

            // Display the Main Thread Post
            echo '<div class="thread">';
            echo '<div class="row1">';
            if (!empty($mainThread['file'])) {
                echo '<div class="thread-image"><img class="expandable-image" data-thumb="thumbs/' . htmlspecialchars($mainThread['thumb']) . '" data-full="uploads/' . htmlspecialchars($mainThread['file']) . '" src="thumbs/' . htmlspecialchars($mainThread['thumb']) . '" alt="Image"></div>';
            }
            echo '<a href="#" class="reply-link">Reply (' . countReplies($mainThread['id'], $db) . ')</a><br>';
            echo '<span class="subject">' . (!empty($mainThread['subject']) ? sanitize($mainThread['subject']) : 'No Subject') . '</span><br><br>';
            echo '<span class="message">' . escapeOutput($mainThread['message']) . '</span><br>';
            echo '</div></div><hr>';

            // Display Replies
            $replies = getReplies($threadId, $db);
            foreach ($replies as $reply) {
                echo '<div class="reply" id="post' . $reply['id'] . '">';
                if (!empty($reply['file'])) {
                    echo '<div class="reply-image"><img class="expandable-image" data-thumb="thumbs/' . htmlspecialchars($reply['thumb']) . '" data-full="uploads/' . htmlspecialchars($reply['file']) . '" src="thumbs/' . htmlspecialchars($reply['thumb']) . '" alt="Image"></div>';
                }
                echo '<a href="#" class="reply-link" style="float: right;">Reply (' . countReplies($reply['id'], $db) . ')</a><br>';
                echo '<span class="subject">' . (!empty($reply['subject']) ? sanitize($reply['subject']) : 'No Subject') . '</span><br>';
                echo '<span class="message">' . escapeOutput($reply['message']) . '</span><br>';
                echo '</div><hr>';
            }
        } else {
            echo '<p>Thread not found.</p>';
        }
    } else {
        // Display Main Board
        echo '<div class="adminbar">';
        echo '<!-- Future admin links can be added here -->';
        echo '</div>';
        echo '<div class="logo">';
        echo htmlspecialchars(ADELIA_BOARD_DESC);
        echo '</div>';
        echo '<hr>';

        echo '<div class="postarea">';
        echo '<form class="postform" action="" method="post" enctype="multipart/form-data">';
        echo '<input type="hidden" name="parent" value="0">';
        echo '<label for="file">Image (optional)</label>';
        echo '<input type="file" id="file" name="file" accept="image/jpeg,image/png,image/gif">';

        echo '<label for="subject">Subject (optional)</label>';
        echo '<input type="text" id="subject" name="subject" maxlength="75">';

        echo '<label for="message">Message</label>';
        echo '<textarea id="message" name="message" rows="4" maxlength="8000" required></textarea>';

        echo '<input type="submit" value="Post">';
        echo '</form>';
        echo '</div>';

        echo '<hr>';

        // Display Threads
        foreach ($threads as $thread) {
            echo '<div class="thread">';
            echo '<div class="row1">';
            if (!empty($thread['file'])) {
                echo '<div class="thread-image"><img class="expandable-image" data-thumb="thumbs/' . htmlspecialchars($thread['thumb']) . '" data-full="uploads/' . htmlspecialchars($thread['file']) . '" src="thumbs/' . htmlspecialchars($thread['thumb']) . '" alt="Image"></div>';
            }
            echo '<a href="?thread=' . $thread['id'] . '" class="reply-link" style="float: right;">Reply (' . $thread['reply_count'] . ')</a><br>';
            echo '<span class="subject">' . (!empty($thread['subject']) ? sanitize($thread['subject']) : 'No Subject') . '</span><br><br>';
            echo '<span class="message">' . escapeOutput($thread['message']) . '</span><br>';
            echo '</div><hr>';
        }

        // Display Pagination
        echo displayPagination($currentPage, ADELIA_THREADS_PER_PAGE, $db);
    }
    ?>

    <hr>
    <div class="footer">
        - <a href="https://example.com" target="_blank">Adelia</a> -
    </div>

    <!-- Expandable Image JS -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.expandable-image').forEach(image => {
                image.addEventListener('click', function() {
                    if (this.src.includes('thumbs/')) {
                        this.style.width = 'auto';
                        this.style.maxHeight = '90vh';
                        this.src = this.dataset.full;
                        this.classList.add('expanded');
                    } else {
                        this.style.width = '';
                        this.style.maxHeight = '';
                        this.src = this.dataset.thumb;
                        this.classList.remove('expanded');
                    }
                });
            });
        });
    </script>
</body>
</html>
