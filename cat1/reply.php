<?php
declare(strict_types=1);

// reply.php - Displays a single thread and its replies, allows replying without images

// Error logging configuration
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/error.log');

// Configuration
$db_file = __DIR__ . '/database.sqlite';
$db = new SQLite3($db_file);
// Ensure WAL mode
$db->exec("PRAGMA journal_mode = WAL;");

$thread_id = filter_input(INPUT_GET, 'thread_id', FILTER_VALIDATE_INT, [
    'options' => ['default' => 0, 'min_range' => 1]
]);

if ($thread_id <= 0) {
    die("Invalid thread ID.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $comment = trim($_POST['body'] ?? '');

    if ($name === '' || $comment === '') {
        die("Name and Comment fields are required.");
    }

    $datetime = gmdate('Y-m-d\TH:i:s\Z');

    $stmt = $db->prepare("INSERT INTO posts (parent_id, name, subject, comment, image, datetime) VALUES (?, ?, '', ?, '', ?)");
    $stmt->bindValue(1, $thread_id, SQLITE3_INTEGER);
    $stmt->bindValue(2, $name, SQLITE3_TEXT);
    $stmt->bindValue(3, $comment, SQLITE3_TEXT);
    $stmt->bindValue(4, $datetime, SQLITE3_TEXT);
    $stmt->execute();

    $bump_stmt = $db->prepare("UPDATE posts SET datetime = ? WHERE id = ? AND parent_id = 0");
    $bump_stmt->bindValue(1, $datetime, SQLITE3_TEXT);
    $bump_stmt->bindValue(2, $thread_id, SQLITE3_INTEGER);
    $bump_stmt->execute();

    header("Location: " . $_SERVER['PHP_SELF'] . "?thread_id={$thread_id}");
    exit;
}

$op_stmt = $db->prepare("SELECT * FROM posts WHERE id = ? AND parent_id = 0");
$op_stmt->bindValue(1, $thread_id, SQLITE3_INTEGER);
$op = $op_stmt->execute()->fetchArray(SQLITE3_ASSOC);

if (!$op) {
    die("Thread not found.");
}

$replies_res = $db->prepare("SELECT * FROM posts WHERE parent_id = ? ORDER BY id ASC");
$replies_res->bindValue(1, $thread_id, SQLITE3_INTEGER);
$replies = $replies_res->execute();

$reply_posts = [];
while ($reply_row = $replies->fetchArray(SQLITE3_ASSOC)) {
    $reply_posts[] = $reply_row;
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>/b/ - Random</title>
<meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=yes">
    <!-- Stylesheets -->
    <link rel="stylesheet" title="default" href="css/style.css" type="text/css" media="screen">
    <link rel="stylesheet" title="style1" href="css/1.css" type="text/css" media="screen" disabled="disabled">
    <link rel="stylesheet" title="style2" href="css/2.css" type="text/css" media="screen" disabled="disabled">
    <link rel="stylesheet" title="style3" href="css/3.css" type="text/css" media="screen" disabled="disabled">
    <link rel="stylesheet" title="style4" href="css/4.css" type="text/css" media="screen" disabled="disabled">
    <link rel="stylesheet" title="style5" href="css/5.css" type="text/css" media="screen" disabled="disabled">
    <link rel="stylesheet" title="style6" href="css/6.css" type="text/css" media="screen" disabled="disabled">
    <link rel="stylesheet" title="style7" href="css/7.css" type="text/css" media="screen" disabled="disabled">
    <link rel="stylesheet" href="css/font-awesome/css/font-awesome.min.css">
<link rel="stylesheet" href="css/font-awesome/css/font-awesome.min.css">


<script type="text/javascript">
function setActiveStyleSheet(title) {
    var links = document.getElementsByTagName("link");
    for (var i = 0; i < links.length; i++) {
        var a = links[i];
        if(a.getAttribute("rel") && a.getAttribute("rel").indexOf("stylesheet") != -1 && a.getAttribute("title")) {
            a.disabled = true;
            if(a.getAttribute("title") === title) a.disabled = false;
        }
    }
    localStorage.setItem('selectedStyle', title);
}

window.addEventListener('load', function() {
    var savedStyle = localStorage.getItem('selectedStyle');
    if(savedStyle) {
        setActiveStyleSheet(savedStyle);
    }
});

var active_page = "thread",
    board_name = "b",
    thread_id = "<?php echo $thread_id; ?>";
var configRoot="/";
var inMod = false;
var modRoot="/"+(inMod ? "mod.php?/" : "");
</script>

<script type="text/javascript" src="js/main.js"></script>
<script type="text/javascript" src="js/jquery.min.js"></script>
<script type="text/javascript" src="js/inline-expanding.js"></script>

</head>
<body class="8chan vichan is-not-moderator active-thread" data-stylesheet="default">
<a name="top"></a>

<div id="style-selector" style="position:absolute; top:10px; left:10px; background:#eee; padding:5px; border:1px solid #ccc;">
    <label for="style_select">Style:</label>
    <select id="style_select" onchange="setActiveStyleSheet(this.value)">
        <option value="default">default</option>
        <option value="style1">style1</option>
        <option value="style2">style2</option>
        <option value="style3">style3</option>
        <option value="style4">style4</option>
        <option value="style5">style5</option>
        <option value="style6">style6</option>
        <option value="style7">style7</option>
    </select>
</div>

<header><h1>/b/ - Random</h1><div class="subtitle"></div></header>
<div class="banner">Posting mode: Reply <a class="unimportant" href="index.php">[Return]</a> <a class="unimportant" href="#bottom">[Go to bottom]</a></div>

<form name="post" onsubmit="return true;" action="?thread_id=<?php echo $thread_id; ?>" method="post">
    <input type="hidden" name="thread" value="<?php echo $thread_id; ?>">
    <input type="hidden" name="board" value="b">
    <table>
        <tr>
            <th>Name</th>
            <td><input type="text" name="name" size="25" maxlength="35" autocomplete="off" required></td>
        </tr>
        <tr>
            <th>Comment</th>
            <td>
                <textarea name="body" id="body" rows="5" cols="35" required></textarea>
                <input accesskey="s" style="margin-left:2px;" type="submit" name="post" value="New Reply" />
            </td>
        </tr>
    </table>
    <input type="hidden" name="hash" value="dummyhash">
</form>
<hr />

<form name="postcontrols" action="" method="post">
<input type="hidden" name="board" value="b" />

<div class="thread" id="thread_<?php echo $thread_id; ?>" data-board="b">
    <?php
    $op_name = htmlspecialchars($op['name'], ENT_QUOTES, 'UTF-8');
    $op_subject = htmlspecialchars($op['subject'], ENT_QUOTES, 'UTF-8');
    $op_comment = nl2br(htmlspecialchars($op['comment'], ENT_QUOTES, 'UTF-8'));
    $op_img_html = '';
    if ($op['image']) {
        $img_path = 'uploads/' . $op['image'];
        $op_img_html = '
        <div class="files">
            <div class="file">
                <p class="fileinfo">File: <a href="'.htmlspecialchars($img_path, ENT_QUOTES, 'UTF-8').'">'.htmlspecialchars(basename($op['image']), ENT_QUOTES, 'UTF-8').'</a></p>
                <a href="'.htmlspecialchars($img_path, ENT_QUOTES, 'UTF-8').'" target="_blank"><img class="post-image" src="'.htmlspecialchars($img_path, ENT_QUOTES, 'UTF-8').'" style="width:255px;height:auto" alt="" /></a>
            </div>
        </div>';
    }

    echo '<a id="'. $thread_id .'" class="post_anchor"></a>';
    echo $op_img_html;
    echo '<div class="post op" id="op_'.$thread_id.'"><p class="intro">
    <input type="checkbox" class="delete" name="delete_'.$thread_id.'" id="delete_'.$thread_id.'" />
    <label for="delete_'.$thread_id.'">';
    if (!empty($op_subject)) {
        echo '<span class="subject">'.$op_subject.'</span> ';
    }
    echo '<span class="name">'.$op_name.'</span></label>&nbsp;';
    // Removed "No." and the post number link for OP
    echo '</p>
    <div class="body">'.$op_comment.'</div></div>';

    $reply_num = 0;
    foreach ($reply_posts as $r) {
        $reply_num++;
        $r_id = (int)$r['id'];
        $r_name = htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8');
        $r_comment = nl2br(htmlspecialchars($r['comment'], ENT_QUOTES, 'UTF-8'));

        echo '<div class="post reply" id="reply_'.$r_id.'">
        <p class="intro">
        <a id="'.$r_id.'" class="post_anchor"></a>
        <input type="checkbox" class="delete" name="delete_'.$r_id.'" id="delete_'.$r_id.'" />
        <label for="delete_'.$r_id.'"><span class="name">'.$r_name.'</span></label>&nbsp;';
        // Removed "No." and the post number link for replies as well
        echo '</p>
        <div class="files"></div>
        <div class="body" style="text-align:center;font-weight:bold;">Reply '.$reply_num.'</div>
        <div class="body">'.$r_comment.'</div></div><br/>';
    }
    ?>
    <br class="clear"/>
    <hr/>
</div>

<div id="thread-interactions">
    <span id="thread-links">
        <a id="thread-return" href="index.php">[Return]</a>
        <a id="thread-top" href="#top">[Go to top]</a>
    </span>
    <span id="thread-quick-reply"><a id="link-quick-reply" href="#">[Post a Reply]</a></span>
    <div id="post-moderation-fields">
        <div id="report-fields">
            <label for="reason">Reason</label> <input id="reason" type="text" name="reason" size="20" maxlength="30" /><input type="submit" name="report" value="Report" />
        </div>
    </div>
</div>
<div class="clearfix"></div>
</form>
<a name="bottom"></a>
<footer>
    <p class="unimportant" style="margin-top:20px;text-align:center;">
        All trademarks, copyrights, comments,
        and images on this page are owned by and are the responsibility of their respective parties.
    </p>
</footer>
<script type="text/javascript">ready();</script>
</body>
</html>
