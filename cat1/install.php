<?php
declare(strict_types=1);

// install.php - Prepares the environment, database, and directories.

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/error.log');

// Configuration
$db_file = __DIR__ . '/database.sqlite';
$upload_dir = __DIR__ . '/uploads/';

// Create directories if not exist
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Set proper permissions (adjust as needed)
chmod($upload_dir, 0777);

// Initialize DB
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

// Enable WAL mode for performance
$db->exec("PRAGMA journal_mode = WAL;");

// Vacuum and analyze to optimize
$db->exec("VACUUM;");
$db->exec("ANALYZE;");

echo "Installation completed successfully.\n";
