<?php

// install.php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Error logging configuration
ini_set('display_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/error.log');

/**
 * Recursively remove all files and subdirectories inside a given directory, but keep the directory itself.
 *
 * @param string $dir The directory to clear.
 * @return void
 */
function clear_directory(string $dir): void {
    if (!is_dir($dir)) {
        return;
    }

    $files = scandir($dir);
    if ($files === false) {
        return;
    }

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        if (is_dir($path)) {
            clear_directory($path);
            rmdir($path);
        } else {
            unlink($path);
        }
    }
}

// Create directories if they don't exist and clear them if they do
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
} else {
    clear_directory($upload_dir);
}

if (!file_exists($threads_dir)) {
    mkdir($threads_dir, 0755, true);
} else {
    clear_directory($threads_dir);
}

// Ensure CSRF token exists
get_global_csrf_token();

$db = init_db();
$table = get_table_name();
$schema = $board_name;

// Drop the existing schema if it exists (complete reset)
try {
    $db->exec("DROP SCHEMA IF EXISTS \"{$schema}\" CASCADE");
} catch (PDOException $e) {
    echo "Error dropping existing schema: " . $e->getMessage() . "<br>";
}

// Now create the schema and table again
$db->exec("CREATE SCHEMA IF NOT EXISTS \"{$schema}\"");
$db->exec("CREATE TABLE IF NOT EXISTS $table (
    id SERIAL PRIMARY KEY,
    parent_id INTEGER DEFAULT 0,
    name TEXT,
    subject TEXT,
    comment TEXT,
    image TEXT,
    datetime TIMESTAMPTZ,
    deleted BOOLEAN DEFAULT false
)");

// Add the index on (parent_id, datetime)
$db->exec("CREATE INDEX posts_parent_datetime_idx ON $table (parent_id, datetime)");

generate_all_index_pages($db);

echo "Installation completed successfully for board '$board_name'. You can now visit the board: <a href=\"index.html\">index.html</a>";

// Optionally delete install.php after install for security
@unlink(__FILE__);
