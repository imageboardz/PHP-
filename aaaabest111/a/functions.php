<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function init_db(): PDO {
    global $db_host, $db_port, $db_name, $db_user, $db_pass;
    $dsn = "pgsql:host=$db_host;port=$db_port;dbname=$db_name;";
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    return $pdo;
}

function get_table_name(): string {
    global $board_name;
    return "\"{$board_name}\".\"posts\"";
}

function get_global_csrf_token(): string {
    global $csrf_file;
    if (!file_exists($csrf_file)) {
        $token = bin2hex(random_bytes(32));
        file_put_contents($csrf_file, $token, LOCK_EX);
        return $token;
    }
    $token = trim((string)file_get_contents($csrf_file));
    if ($token === '') {
        $token = bin2hex(random_bytes(32));
        file_put_contents($csrf_file, $token, LOCK_EX);
    }
    return $token;
}

function verify_csrf_token(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        $global_token = get_global_csrf_token();
        if (!hash_equals($global_token, $token)) {
            http_response_code(403);
            die("Invalid CSRF token.");
        }
    }
}

function sanitize_input(string $input): string {
    return trim(strip_tags($input));
}

// Function to generate secure tripcodes
function get_tripcode(string $name): string {
    global $secure_salt;

    if (strpos($name, '##') !== false) {
        list($display_name, $secret) = explode('##', $name, 2);
        $display_name = trim($display_name);
        $secret = trim($secret);

        $hash = sha1($secure_salt . $secret, true);
        $trip_raw = substr(base64_encode($hash), 0, 10);
        $tripcode = '!'.$trip_raw;

        return ($display_name !== '' ? htmlspecialchars($display_name, ENT_QUOTES|ENT_SUBSTITUTE|ENT_HTML5) . ' ' : '') . '<span class="tripcode">'.$tripcode.'</span>';
    } else {
        return htmlspecialchars($name, ENT_QUOTES|ENT_SUBSTITUTE|ENT_HTML5);
    }
}

function generate_all_index_pages(PDO $db): void {
    $table = get_table_name();
    global $posts_per_page;
    $count_stmt = $db->query("SELECT COUNT(*) as total FROM $table WHERE parent_id=0 AND deleted=false");
    $total_threads = (int)$count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = $total_threads > 0 ? (int)ceil($total_threads / $posts_per_page) : 1;

    for ($p = 1; $p <= $total_pages; $p++) {
        generate_static_index($db, $p);
    }
}

function generate_static_index(PDO $db, int $page = 1): void {
    $table = get_table_name();
    global $posts_per_page;

    $count_stmt = $db->query("SELECT COUNT(*) as total FROM $table WHERE parent_id=0 AND deleted=false");
    $total_threads = (int)$count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = $total_threads > 0 ? (int)ceil($total_threads / $posts_per_page) : 1;

    if ($page > $total_pages) {
        $page = $total_pages;
    }

    $offset = ($page - 1) * $posts_per_page;
    $stmt = $db->prepare("SELECT * FROM $table WHERE parent_id=0 AND deleted=false ORDER BY datetime DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $posts_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    ob_start();
    if (count($posts) > 0) {
        render_board_index_with_array($posts, $page, $total_pages);
    } else {
        render_board_index(null);
    }
    $html = ob_get_clean();

    $filename = $page === 1 ? 'index.html' : 'index_' . $page . '.html';
    file_put_contents(__DIR__ . '/' . $filename, $html, LOCK_EX);
}

function generate_static_thread(PDO $db, int $thread_id): void {
    $table = get_table_name();
    global $threads_dir;

    $op_stmt = $db->prepare("SELECT * FROM $table WHERE id = :id AND parent_id = 0 AND deleted=false");
    $op_stmt->bindValue(':id', $thread_id, PDO::PARAM_INT);
    $op_stmt->execute();
    $op = $op_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$op) {
        // Thread deleted or not found
        $thread_file = $threads_dir . 'thread_' . $thread_id . '.html';
        if (file_exists($thread_file)) {
            unlink($thread_file);
        }
        return;
    }

    $replies_stmt = $db->prepare("SELECT * FROM $table WHERE parent_id = :pid AND deleted=false ORDER BY id ASC");
    $replies_stmt->bindValue(':pid', $thread_id, PDO::PARAM_INT);
    $replies_stmt->execute();
    $replies = $replies_stmt->fetchAll(PDO::FETCH_ASSOC);

    ob_start();
    render_thread_page($op, $replies);
    $html = ob_get_clean();
    file_put_contents($threads_dir . 'thread_' . $thread_id . '.html', $html, LOCK_EX);
}

function render_header(string $title, string $page_type = 'index'): void {
    $board_name_js = htmlspecialchars($GLOBALS['board_name'], ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5);
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?php echo htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5); ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=yes">
<link rel="stylesheet" title="default" href="/css/style.css" type="text/css" media="screen">
<link rel="stylesheet" title="style1" href="/css/1.css" type="text/css" media="screen" disabled="disabled">
<link rel="stylesheet" title="style2" href="/css/2.css" type="text/css" media="screen" disabled="disabled">
<link rel="stylesheet" title="style3" href="/css/3.css" type="text/css" media="screen" disabled="disabled">
<link rel="stylesheet" title="style4" href="/css/4.css" type="text/css" media="screen" disabled="disabled">
<link rel="stylesheet" title="style5" href="/css/5.css" type="text/css" media="screen" disabled="disabled">
<link rel="stylesheet" title="style6" href="/css/6.css" type="text/css" media="screen" disabled="disabled">
<link rel="stylesheet" title="style7" href="/css/7.css" type="text/css" media="screen" disabled="disabled">
<link rel="stylesheet" href="/css/font-awesome/css/font-awesome.min.css">

<script type="text/javascript">
    const active_page = "<?php echo $page_type; ?>";
    const board_name = "<?php echo $board_name_js; ?>";

    function setActiveStyleSheet(title) {
        const links = document.getElementsByTagName("link");
        for (let i = 0; i < links.length; i++) {
            const a = links[i];
            if(a.getAttribute("rel") && a.getAttribute("rel").indexOf("stylesheet") !== -1 && a.getAttribute("title")) {
                a.disabled = true;
                if(a.getAttribute("title") === title) a.disabled = false;
            }
        }
        localStorage.setItem('selectedStyle', title);
    }

    window.addEventListener('load', () => {
        const savedStyle = localStorage.getItem('selectedStyle');
        if(savedStyle) {
            setActiveStyleSheet(savedStyle);
        }
    });
</script>

<script type="text/javascript" src="/js/jquery.min.js"></script>
<script type="text/javascript" src="/js/main.js"></script>
<script type="text/javascript" src="/js/inline-expanding.js"></script>
<script type="text/javascript" src="/js/hide-form.js"></script>
</head>
<body class="visitor is-not-moderator active-index" data-stylesheet="default">
<header><h1>/<?php echo $board_name_js; ?>/ - Random</h1><div class="subtitle"></div></header>
    <?php
}

function render_footer(): void {
    ?>
<footer>
    <!-- Style selector -->
    <div id="style-selector">
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

    <p class="unimportant">
        All trademarks, copyrights,
        comments, and images on this page are owned by and are
        the responsibility of their respective parties.
    </p>

    <div style="text-align:center; margin-top:10px;">
        <a href="https://example.com/">COM</a> | 
        <a href="https://example.net/">NET</a> |
        <a href="https://example.org/">ORG</a>
    </div>
</footer>

<div id="home-button">
    <a href="../">Home</a>
</div>

<script type="text/javascript">ready();</script>
</body>
</html>
    <?php
}

function render_board_index($db, $results = null): void {
    $csrf_token = htmlspecialchars(get_global_csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5);
    render_header('/' . $GLOBALS['board_name'] . '/ - Random', 'index');
    ?>
    <form name="post" enctype="multipart/form-data" action="chess.php" method="post">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <table>
            <tr><th>Name</th><td><input type="text" name="name" size="25" maxlength="35" autocomplete="off" required></td></tr>
            <tr><th>Subject</th><td><input type="text" name="subject" size="25" maxlength="100" autocomplete="off" required>
                <input type="submit" name="post" value="New Topic" style="margin-left:2px;"></td></tr>
            <tr><th>Comment</th><td><textarea name="body" id="body" rows="5" cols="35" required></textarea></td></tr>
            <tr id="upload"><th>File</th><td><input type="file" name="file" accept=".jpg,.jpeg,.png,.gif,.webp,.mp4"></td></tr>
        </table>
    </form>
    <hr />
    <?php
    if ($results instanceof PDOStatement) {
        $dbx = init_db();
        while ($post = $results->fetch(PDO::FETCH_ASSOC)) {
            render_single_thread($dbx, $post);
        }
    }
    render_footer();
}

function render_board_index_with_array(array $posts, int $page = 1, int $total_pages = 1): void {
    $csrf_token = htmlspecialchars(get_global_csrf_token(), ENT_QUOTES|ENT_SUBSTITUTE|ENT_HTML5);
    render_header('/' . $GLOBALS['board_name'] . '/ - Random', 'index');
    ?>
    <form name="post" enctype="multipart/form-data" action="chess.php" method="post">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <table>
            <tr><th>Name</th><td><input type="text" name="name" size="25" maxlength="35" required></td></tr>
            <tr><th>Subject</th><td><input type="text" name="subject" size="25" maxlength="100" required>
                <input type="submit" name="post" value="New Topic" style="margin-left:2px;"></td></tr>
            <tr><th>Comment</th><td><textarea name="body" id="body" rows="5" cols="35" required></textarea></td></tr>
            <tr id="upload"><th>File</th><td><input type="file" name="file" accept=".jpg,.jpeg,.png,.gif,.webp,.mp4"></td></tr>
        </table>
    </form>
    <hr />
    <?php
    $db = init_db();
    foreach ($posts as $post) {
        render_single_thread($db, $post);
    }

    // Pagination links
    echo '<div class="pagination">';
    if ($page > 1) {
        $prev_page = $page - 1;
        $prev_link = $prev_page === 1 ? 'index.html' : 'index_' . $prev_page . '.html';
        echo '<a href="'.$prev_link.'">Previous</a> ';
    }

    for ($i = 1; $i <= $total_pages; $i++) {
        $page_link = $i === 1 ? 'index.html' : 'index_' . $i . '.html';
        if ($i === $page) {
            echo '<strong>'.$i.'</strong> ';
        } else {
            echo '<a href="'.$page_link.'">'.$i.'</a> ';
        }
    }

    if ($page < $total_pages) {
        $next_page = $page + 1;
        $next_link = 'index_' . $next_page . '.html';
        echo ' <a href="'.$next_link.'">Next</a>';
    }
    echo '</div>';

    render_footer();
}

function render_single_thread(PDO $db, array $post): void {
    $table = get_table_name();
    $id = (int)$post['id'];
    $name = get_tripcode($post['name']);
    $subject = htmlspecialchars($post['subject'], ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5);
    $comment = nl2br(htmlspecialchars($post['comment'], ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5));

    $count_stmt = $db->prepare("SELECT COUNT(*) as cnt FROM $table WHERE parent_id = :pid AND deleted=false");
    $count_stmt->bindValue(':pid', $id, PDO::PARAM_INT);
    $count_stmt->execute();
    $reply_count = (int)$count_stmt->fetch(PDO::FETCH_ASSOC)['cnt'];

    $image_html = render_image_html($post['image']);
    $reply_link_text = $reply_count > 0 ? "Reply[".$reply_count."]" : "Reply";
    $thread_url = 'threads/thread_' . $id . '.html';

    $latest_reply_html = '';
    if ($reply_count > 0) {
        $latest_reply_stmt = $db->prepare("SELECT name, comment, image FROM $table WHERE parent_id = :pid AND deleted=false ORDER BY id DESC LIMIT 1");
        $latest_reply_stmt->bindValue(':pid', $id, PDO::PARAM_INT);
        $latest_reply_stmt->execute();
        $lr = $latest_reply_stmt->fetch(PDO::FETCH_ASSOC);
        if ($lr) {
            $latest_reply_name = get_tripcode($lr['name']);
            $latest_reply_text = nl2br(htmlspecialchars($lr['comment'], ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5));
            $lr_img_html = render_image_html($lr['image']);

            $latest_reply_html = '
            <div class="post reply" id="latest_reply">
                <p class="intro"><span class="name">'.$latest_reply_name.'</span></p>
                '.$lr_img_html.'
                <div class="body bold-center">Latest reply</div>
                <div class="body">'.$latest_reply_text.'</div>
            </div>';
        }
    }

    echo '<div class="thread" id="thread_'.$id.'" data-board="'.htmlspecialchars($GLOBALS['board_name'], ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5).'">';
    echo $image_html;
    echo '<div class="post op" id="op_'.$id.'">
        <p class="intro">';
    if (!empty($subject)) {
        echo '<span class="subject">'.$subject.'</span> ';
    }
    echo '<span class="name">'.$name.'</span>
            &nbsp;<a href="'.$thread_url.'">'.$reply_link_text.'</a>
        </p>
        <div class="body">'.$comment.'</div>'
        . $latest_reply_html .
    '</div>
    <br class="clear"/>
    <hr/>
    </div>';
}

function render_thread_page(array $op, array $replies): void {
    global $board_name, $admin_password;
    $csrf_token = htmlspecialchars(get_global_csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5);
    $thread_id = (int)$op['id'];
    render_header('/' . $board_name . '/ - Random', 'thread');
    ?>
    <div class="banner">Posting mode: Reply <a class="unimportant" href="../index.html">[Return]</a> <a class="unimportant" href="#bottom">[Go to bottom]</a></div>

    <form name="post" action="../reply.php?thread_id=<?php echo $thread_id; ?>" method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <input type="hidden" name="thread" value="<?php echo $thread_id; ?>">
        <input type="hidden" name="board" value="<?php echo htmlspecialchars($board_name, ENT_QUOTES|ENT_SUBSTITUTE|ENT_HTML5); ?>">
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
    </form>
    <hr />

    <form name="postcontrols" action="../reply.php?thread_id=<?php echo $thread_id; ?>" method="post">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="board" value="<?php echo htmlspecialchars($board_name, ENT_QUOTES|ENT_SUBSTITUTE|ENT_HTML5); ?>" />

    <div class="thread" id="thread_<?php echo $thread_id; ?>" data-board="<?php echo htmlspecialchars($board_name, ENT_QUOTES|ENT_SUBSTITUTE|ENT_HTML5); ?>">
        <?php
        $op_img_html = render_image_html($op['image']);
        $op_name = get_tripcode($op['name']);
        $op_subject = htmlspecialchars($op['subject'], ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5);
        $op_comment = nl2br(htmlspecialchars($op['comment'], ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5));

        echo '<a id="'. $thread_id .'" class="post_anchor"></a>';
        echo $op_img_html;
        echo '<div class="post op" id="op_'.$thread_id.'"><p class="intro">
        <input type="checkbox" class="delete" name="delete_'.$thread_id.'" id="delete_'.$thread_id.'" />
        <label for="delete_'.$thread_id.'">';
        if (!empty($op_subject)) {
            echo '<span class="subject">'.$op_subject.'</span> ';
        }
        echo '<span class="name">'.$op_name.'</span></label>&nbsp;</p>
        <div class="body">'.$op_comment.'</div></div>';

        $reply_num = 0;
        foreach ($replies as $r) {
            $reply_num++;
            $r_id = (int)$r['id'];
            $r_name = get_tripcode($r['name']);
            $r_comment = nl2br(htmlspecialchars($r['comment'], ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5));
            $r_img_html = render_image_html($r['image']);

            echo '<div class="post reply" id="reply_'.$r_id.'">
            <p class="intro">
            <a id="'.$r_id.'" class="post_anchor"></a>
            <input type="checkbox" class="delete" name="delete_'.$r_id.'" id="delete_'.$r_id.'" />
            <label for="delete_'.$r_id.'"><span class="name">'.$r_name.'</span></label>&nbsp;
            </p>
            '.$r_img_html.'
            <div class="body bold-center">Reply '.$reply_num.'</div>
            <div class="body">'.$r_comment.'</div></div><br/>';
        }
        ?>
        <br class="clear"/>
        <hr/>
    </div>

    <div class="admin-controls">
        <label for="admin_pw">Admin Password:</label>
        <input type="text" name="admin_pw" id="admin_pw" size="20" required>
        <input type="submit" name="delete_selected" value="Delete">
    </div>

    <div class="clearfix"></div>
    </form>
    <a name="bottom"></a>
    <?php
    render_footer();
}

function render_image_html(?string $image): string {
    if (!$image) {
        return '';
    }
    global $allowed_exts, $board_name;
    $image_ext = strtolower(pathinfo($image, PATHINFO_EXTENSION));
    if (!in_array($image_ext, $allowed_exts, true)) {
        return '';
    }

    $full_path = __DIR__ . '/uploads/' . $image;
    if (!file_exists($full_path)) {
        return '';
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($full_path);

    $allowed_mimes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'mp4' => 'video/mp4'
    ];
    if (!isset($allowed_mimes[$image_ext]) || $allowed_mimes[$image_ext] !== $mime) {
        return '';
    }

    $img_path = '/'. htmlspecialchars($board_name, ENT_QUOTES|ENT_SUBSTITUTE|ENT_HTML5) . '/uploads/' . htmlspecialchars($image, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5);

    if (in_array($image_ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
        return '
        <div class="files">
            <div class="file">
                <p class="fileinfo">File: <a href="'.$img_path.'">'.htmlspecialchars(basename($image), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5).'</a></p>
                <a href="'.$img_path.'" target="_blank"><img class="post-image" src="'.$img_path.'" alt="" /></a>
            </div>
        </div>';
    } elseif ($image_ext === 'mp4') {
        return '
        <div class="files">
            <div class="file">
                <p class="fileinfo">File: <a href="'.$img_path.'">'.htmlspecialchars(basename($image), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5).'</a></p>
                <video class="post-video" controls>
                    <source src="'.$img_path.'" type="video/mp4">
                    Your browser does not support the video tag.
                </video>
            </div>
        </div>';
    }
    return '';
}
