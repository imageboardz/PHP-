<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/error.log');

$db = init_db();
$table = get_table_name();

$thread_id = filter_input(INPUT_GET, 'thread_id', FILTER_VALIDATE_INT, [
    'options' => ['default' => 0, 'min_range' => 1]
]);

if ($thread_id <= 0) {
    die("Invalid thread ID.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    if (isset($_POST['delete_selected'])) {
        // Admin deletion
        $admin_pw = $_POST['admin_pw'] ?? '';
        global $admin_password;
        if ($admin_pw === $admin_password) {
            $checked_posts = [];
            foreach ($_POST as $key => $val) {
                if (str_starts_with((string)$key, 'delete_') && $val === 'on') {
                    $post_id = (int)str_replace('delete_', '', $key);
                    if ($post_id > 0) {
                        $checked_posts[] = $post_id;
                    }
                }
            }

            if (!empty($checked_posts)) {
                if (in_array($thread_id, $checked_posts, true)) {
                    // Delete entire thread
                    $del_stmt = $db->prepare("UPDATE $table SET deleted=true WHERE id=:tid OR parent_id=:tid");
                    $del_stmt->bindValue(':tid', $thread_id, PDO::PARAM_INT);
                    $del_stmt->execute();

                    generate_all_index_pages($db);
                    generate_static_thread($db, $thread_id);
                    header("Location: ../index.html");
                    exit;
                } else {
                    // Delete selected replies
                    $in_list = implode(',', array_map('intval', $checked_posts));
                    $db->exec("UPDATE $table SET deleted=true WHERE id IN ($in_list)");
                    generate_all_index_pages($db);
                    generate_static_thread($db, $thread_id);
                    header("Location: threads/thread_{$thread_id}.html");
                    exit;
                }
            }

            header("Location: threads/thread_{$thread_id}.html");
            exit;
        } else {
            // Wrong password
            header("Location: threads/thread_{$thread_id}.html");
            exit;
        }
    } elseif (isset($_POST['post'])) {
        // New reply
        $name = sanitize_input($_POST['name'] ?? '');
        $comment = sanitize_input($_POST['body'] ?? '');

        if ($name === '' || $comment === '') {
            die("Name and Comment fields are required.");
        }

        $datetime = gmdate('Y-m-d\TH:i:s\Z');

        $stmt = $db->prepare("INSERT INTO $table (parent_id, name, subject, comment, image, datetime, deleted) VALUES (:tid, :name, '', :comment, '', :datetime, false)");
        $stmt->bindValue(':tid', $thread_id, PDO::PARAM_INT);
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->bindValue(':comment', $comment, PDO::PARAM_STR);
        $stmt->bindValue(':datetime', $datetime, PDO::PARAM_STR);
        $stmt->execute();

        // Bump thread
        $bump_stmt = $db->prepare("UPDATE $table SET datetime = :datetime WHERE id = :tid AND parent_id = 0 AND deleted=false");
        $bump_stmt->bindValue(':datetime', $datetime, PDO::PARAM_STR);
        $bump_stmt->bindValue(':tid', $thread_id, PDO::PARAM_INT);
        $bump_stmt->execute();

        generate_all_index_pages($db);
        generate_static_thread($db, $thread_id);

        header("Location: threads/thread_{$thread_id}.html");
        exit;
    }
}

// If thread page exists
if (file_exists(__DIR__ . "/threads/thread_{$thread_id}.html")) {
    header("Location: threads/thread_{$thread_id}.html");
    exit;
}

// If not found or deleted, go to index
$op_stmt = $db->prepare("SELECT * FROM $table WHERE id = :tid AND parent_id = 0 AND deleted=false");
$op_stmt->bindValue(':tid', $thread_id, PDO::PARAM_INT);
$op_stmt->execute();
$op = $op_stmt->fetch(PDO::FETCH_ASSOC);

if (!$op) {
    header("Location: index.html");
    exit;
}

$replies_stmt = $db->prepare("SELECT * FROM $table WHERE parent_id = :tid AND deleted=false ORDER BY id ASC");
$replies_stmt->bindValue(':tid', $thread_id, PDO::PARAM_INT);
$replies_stmt->execute();
$replies = $replies_stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
render_thread_page($op, $replies);
$html = ob_get_clean();

file_put_contents(__DIR__ . '/threads/thread_' . $thread_id . '.html', $html, LOCK_EX);
header("Location: threads/thread_{$thread_id}.html");
exit;
