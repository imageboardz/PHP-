<?php
$file = 'index.html';
$message = $_POST['message'];

// Append the message to the file
$result = file_put_contents($file, "<p>" . htmlspecialchars($message) . "</p>\n", FILE_APPEND);

if ($result === false) {
    // Handle the error
    error_log('Failed to write message to file: ' . $file);
    // Display an error message to the user or redirect to an error page
    die('Failed to write message to file. Please try again later.');
}

header('Location: index.html');
exit;
?>