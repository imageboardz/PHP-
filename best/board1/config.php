<?php

declare(strict_types=1);

// config.php - Board-specific configuration

// Board name inferred from directory name
$board_name = basename(__DIR__); // e.g., "board1"

// Filesystem paths
$db_file = __DIR__ . '/database.sqlite';
$upload_dir = __DIR__ . '/uploads/';
$threads_dir = __DIR__ . '/threads/';

// Pagination
$posts_per_page = 20; // Can be changed any time

// Allowed file extensions
$allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4'];

// CSRF token storage
$csrf_file = __DIR__ . '/csrf_secret.txt';
