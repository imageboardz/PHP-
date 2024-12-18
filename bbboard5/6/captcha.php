<?php
session_start();

// check if form was submitted and captcha was answered correctly
if (isset($_POST['captcha']) && isset($_SESSION['captcha']) && $_POST['captcha'] == $_SESSION['captcha']) {
    // captcha was answered correctly, do something here
    echo "Captcha was answered correctly!";
} else if (isset($_POST['captcha'])) {
    // captcha was answered incorrectly, display error message
    echo "Incorrect captcha, please try again.";
}

// generate new captcha
$num1 = rand(1, 10);
$num2 = rand(1, 10);
$answer = $num1 + $num2;
$captcha = $num1 . " + " . $num2 . " = ";

// store captcha in session for validation
$_SESSION['captcha'] = $answer;

// display captcha form
echo '
    <form method="post">
        <label for="captcha">' . $captcha . '</label>
        <input type="number" name="captcha" required>
        <br>
        <input type="submit" value="Submit">
    </form>
';
?>

