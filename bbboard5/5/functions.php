<?php
function validate_input($input) {
    return !empty($input) && strlen($input) <= MAX_MESSAGE_LENGTH && preg_match('/^[a-zA-Z0-9\s.,?!-]*$/', $input);
}

function prepare_database() {
    $db = new SQLite3(DB_PATH);
    $db->exec('CREATE TABLE IF NOT EXISTS messages (id INTEGER PRIMARY KEY AUTOINCREMENT, content TEXT)');
    return $db;
}

function set_secure_session_cookie() {
    $params = session_get_cookie_params();
    session_set_cookie_params(
        $params['lifetime'],
        $params['path'],
        $params['domain'],
        true, // secure flag
        true // httpOnly flag
    );
    session_start();
}
?>

