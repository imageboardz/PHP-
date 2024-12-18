<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Error logging configuration
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/error.log');

// Create directories if not exist
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}
if (!file_exists($threads_dir)) {
    mkdir($threads_dir, 0755, true);
}

// Ensure CSRF token exists
get_global_csrf_token();

$db = new SQLite3($db_file);

// Create table if not exists
$db->exec("CREATE TABLE IF NOT EXISTS posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    parent_id INTEGER DEFAULT 0,
    name TEXT,
    subject TEXT,
    comment TEXT,
    image TEXT,
    datetime TEXT
)");

$db->exec("PRAGMA journal_mode = WAL;");
$db->exec("PRAGMA foreign_keys = ON;");

// Vacuum and analyze for performance
$db->exec("VACUUM;");
$db->exec("ANALYZE;");

// Generate initial index page(s)
generate_all_index_pages($db);

echo "Installation completed successfully. You can now visit the board: <a href=\"index.html\">index.html</a>";

// Attempt to delete install.php after installation for security
@unlink(__FILE__);
