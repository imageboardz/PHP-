<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/error.log');

verify_csrf_token();

$schema = $board_name;
$db = init_db();
$table = get_table_name();

// Sequence name for the SERIAL primary key
$sequence_name = "\"{$board_name}\".posts_id_seq";

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post'])) {
    $name = sanitize_input($_POST['name'] ?? '');
    $subject = sanitize_input($_POST['subject'] ?? '');
    $comment = sanitize_input($_POST['body'] ?? '');

    if ($name === '' || $subject === '' || $comment === '') {
        die("All fields (Name, Subject, Comment) are required.");
    }

    $datetime = gmdate('Y-m-d\TH:i:s\Z');

    $image_path = '';
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK && $_FILES['file']['size'] > 0) {
        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed_exts, true)) {
            $filename = time() . '_' . random_int(1000,9999) . '.' . $ext;
            $target = $upload_dir . $filename;
            if (move_uploaded_file($_FILES['file']['tmp_name'], $target)) {
                $image_path = $filename;
            }
        }
    }

    $stmt = $db->prepare("INSERT INTO $table (parent_id, name, subject, comment, image, datetime, deleted) VALUES (0, :name, :subject, :comment, :image, :datetime, false)");
    $stmt->bindValue(':name', $name, PDO::PARAM_STR);
    $stmt->bindValue(':subject', $subject, PDO::PARAM_STR);
    $stmt->bindValue(':comment', $comment, PDO::PARAM_STR);
    $stmt->bindValue(':image', $image_path, PDO::PARAM_STR);
    $stmt->bindValue(':datetime', $datetime, PDO::PARAM_STR);
    $stmt->execute();

    $thread_id = (int)$db->lastInsertId($sequence_name);

    generate_all_index_pages($db);
    generate_static_thread($db, $thread_id);

    header("Location: index.html");
    exit;
}

// If no index.html exists yet, create it
if (!file_exists(__DIR__ . '/index.html')) {
    generate_all_index_pages($db);
}

if ($page === 1) {
    header("Location: index.html");
} else {
    header("Location: index_{$page}.html");
}
exit;
