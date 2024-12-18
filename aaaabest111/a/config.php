<?php
declare(strict_types=1);

// config.php - Board-specific configuration

$board_name = "a"; // Manually set the board name here

// Database credentials
$db_host = 'localhost';
$db_port = '5432';
$db_name = 'CHANGEEEEMEEEEEE';
$db_user = ''CHANGEEEEMEEEEEE';';
$db_pass = ''CHANGEEEEMEEEEEE';';

$upload_dir = __DIR__ . '/uploads/';
$threads_dir = __DIR__ . '/threads/';

$posts_per_page = 20;
$allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4'];

$csrf_file = __DIR__ . '/csrf_secret.txt';
$admin_password = 'CHANGEEEEMEEEEEE';

// Add a unique, random salt for secure tripcodes
$secure_salt = "PUT_SOME_LONG_RANDOM_STRING_HERE";
