<?php

// Initialize the database file
$dbFile = "database.json";
if (!file_exists($dbFile)) {
    file_put_contents($dbFile, "[]");
}

// Check if the form has been submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get the post data from the form submission
    $postName = htmlspecialchars($_POST["name"], ENT_QUOTES);
    $postText = htmlspecialchars($_POST["post"], ENT_QUOTES);

    // Validate the input
    $nameLength = strlen($postName);
    $textLength = strlen($postText);
    if ($nameLength > 14 || $textLength > 20000) {
        $errorMessage = "Name must be 14 characters or less and message must be 14000 characters or less.";
    } else {
        // Load the existing posts from the database
        $posts = json_decode(file_get_contents($dbFile), true);

        // Add the new post to the beginning of the array
        $post = array(
            "name" => $postName,
            "text" => $postText,
            "date" => date("Y/m/d g:i:s")
        );
        array_unshift($posts, $post);

        // Update the database file with the new array of posts
        file_put_contents($dbFile, json_encode($posts));

        // Redirect the user back to the homepage
        header("Location: index.php");
        exit();
    }
}

// Load the existing posts from the database
$posts = json_decode(file_get_contents($dbFile), true);

// Set the number of posts to display
$defaultNumPosts = 10;
$numPosts = isset($_GET["num_posts"]) ? $_GET["num_posts"] : $defaultNumPosts;
$numPosts = min(max(intval($numPosts), 5), 500);

// Slice the posts array to get the desired number of posts
$displayedPosts = array_slice($posts, 0, $numPosts);

?>

<!-- HTML code for the homepage -->
<html>
<head>
    <link rel="stylesheet" href="style.css">
</head>
<body>

   <div id="showPosts">
        <span>Show:</span>
       
   <?php
        $displayOptions = array(5, 15, 25, 50, 100, 200, 500);
  foreach ($displayOptions as $option) {
    if ($option == $numPosts) {
        echo '<a href="?num_posts=' . $option . '" class="active">' . $option . '</a> ';
    } else {
        echo '<a href="?num_posts=' . $option . '">' . $option . '</a> ';
    }
}

        ?>
    </div>














    <div id="postBox">
        <?php if (isset($errorMessage)) { ?>
            <div class="error">
                <?php echo htmlspecialchars($errorMessage, ENT_QUOTES); ?> <a href="index.php">Return to main page</a>
            </div>
        <?php } ?>
        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES); ?>" method="post">
            <table>
                <tr>
                    <td>Name:</td>
                    <td><input type="text" name="name" value="Anonymous" required maxlength="14"></td>
                </tr>
                <tr>
                    <td>Post:</td>
                    <td><textarea name="post" required maxlength="14000"></textarea></td>
                </tr>
            </table>
            <input type="submit" value="Post">
        </form>
    </div>

    <?php foreach ($displayedPosts as $post) { ?>
        <!-- HTML code for a single post -->
        <div class="reply">
            <span class="postName"><?php echo htmlspecialchars($post["name"], ENT_QUOTES); ?></span>
            <span class="postDate"><?php echo htmlspecialchars($post["date"], ENT_QUOTES); ?></span>
            <p class="postText"><?php echo htmlspecialchars($post["text"], ENT_QUOTES); ?></p>
        </div>
    <?php } ?>

 
<br>
<br>
<br>
   
</body>
</html>
