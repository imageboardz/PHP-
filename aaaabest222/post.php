<?php
declare(strict_types=1);

// Configuration
$board_name = "a"; // Board name
$upload_dir = __DIR__ . '/uploads/';
$threads_dir = __DIR__ . '/threads/';
$data_dir = __DIR__ . '/data/';
$posts_per_page = 20;
$allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4'];
$csrf_file = __DIR__ . '/csrf_secret.txt';
$admin_password = 'am4';
$secure_salt = "PUT_SOME_LONG_RANDOM_STRING_HERE";
$posts_file = $data_dir . 'posts.json';

// Ensure directories exist
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}
if (!file_exists($threads_dir)) {
    mkdir($threads_dir, 0755, true);
}
if (!file_exists($data_dir)) {
    mkdir($data_dir, 0755, true);
}

// Ensure posts.json exists
if (!file_exists($posts_file)) {
    file_put_contents($posts_file, json_encode([], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
}

// Functions
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

function load_posts(): array {
    global $posts_file;
    $json = file_get_contents($posts_file);
    if ($json === false) return [];
    $posts = json_decode($json, true);
    if (!is_array($posts)) return [];
    return $posts;
}

function save_posts(array $posts): void {
    global $posts_file;
    file_put_contents($posts_file, json_encode($posts, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function get_next_post_id(array $posts): int {
    $max_id = 0;
    foreach ($posts as $p) {
        if ((int)$p['id'] > $max_id) {
            $max_id = (int)$p['id'];
        }
    }
    return $max_id + 1;
}

function get_threads(array $posts): array {
    $threads = array_filter($posts, function($p) {
        return $p['parent_id'] === 0 && $p['deleted'] === false;
    });
    usort($threads, function($a,$b){
        return strtotime($b['datetime']) - strtotime($a['datetime']);
    });
    return $threads;
}

function get_replies(array $posts, int $thread_id): array {
    $replies = array_filter($posts, function($p) use ($thread_id) {
        return $p['parent_id'] === $thread_id && $p['deleted'] === false;
    });
    usort($replies, function($a,$b){
        return (int)$a['id'] - (int)$b['id'];
    });
    return $replies;
}

function generate_all_index_pages(): void {
    global $posts_per_page;
    $posts = load_posts();
    $threads = get_threads($posts);
    $total_threads = count($threads);
    $total_pages = $total_threads > 0 ? (int)ceil($total_threads / $posts_per_page) : 1;

    for ($p = 1; $p <= $total_pages; $p++) {
        generate_static_index($p, $threads);
    }
}

function generate_static_index(int $page, array $threads): void {
    global $posts_per_page;

    $total_threads = count($threads);
    $total_pages = $total_threads > 0 ? (int)ceil($total_threads / $posts_per_page) : 1;
    if ($page > $total_pages) {
        $page = $total_pages;
    }

    $offset = ($page - 1) * $posts_per_page;
    $threads_page = array_slice($threads, $offset, $posts_per_page);

    ob_start();
    render_board_index_with_array($threads_page, $page, $total_pages);
    $html = ob_get_clean();

    $filename = $page === 1 ? 'index.html' : 'index_' . $page . '.html';
    file_put_contents(__DIR__ . '/' . $filename, $html, LOCK_EX);
}

function generate_static_thread(int $thread_id): void {
    global $threads_dir;
    $posts = load_posts();
    $op = null;
    foreach($posts as $p) {
        if ($p['id'] === $thread_id && $p['parent_id'] === 0 && $p['deleted'] === false) {
            $op = $p;
            break;
        }
    }

    if (!$op) {
        // Thread not found or deleted
        $thread_file = $threads_dir . 'thread_' . $thread_id . '.html';
        if (file_exists($thread_file)) {
            unlink($thread_file);
        }
        return;
    }

    $replies = get_replies($posts, $thread_id);

    ob_start();
    render_thread_page($op, $replies);
    $html = ob_get_clean();
    file_put_contents($threads_dir . 'thread_' . $thread_id . '.html', $html, LOCK_EX);
}

function render_header(string $title, string $page_type = 'index'): void {
    $board_name_js = htmlspecialchars($GLOBALS['board_name'], ENT_QUOTES|ENT_SUBSTITUTE|ENT_HTML5);
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?php echo htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5); ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=yes">

<link rel="stylesheet" href="/f/css/style.css" title="default" type="text/css" media="screen">
<link rel="stylesheet" href="/f/css/1.css" title="style1" type="text/css" media="screen">
<link rel="stylesheet" href="/f/css/2.css" title="style2" type="text/css" media="screen">
<link rel="stylesheet" href="/f/css/3.css" title="style3" type="text/css" media="screen">
<link rel="stylesheet" href="/f/css/4.css" title="style4" type="text/css" media="screen">
<link rel="stylesheet" href="/f/css/5.css" title="style5" type="text/css" media="screen">
<link rel="stylesheet" href="/f/css/6.css" title="style6" type="text/css" media="screen">
<link rel="stylesheet" href="/f/css/7.css" title="style7" type="text/css" media="screen">
<link rel="stylesheet" href="/f/css/font-awesome/css/font-awesome.min.css">

<script type="text/javascript">
    const active_page = "<?php echo $page_type; ?>";
    const board_name = "<?php echo $board_name_js; ?>";

    function setActiveStyleSheet(title) {
        const links = document.getElementsByTagName("link");
        for (let i = 0; i < links.length; i++) {
            const a = links[i];
            if(a.getAttribute("rel") && a.getAttribute("rel").indexOf("stylesheet") !== -1 && a.getAttribute("title")) {
                a.disabled = (a.getAttribute("title") !== title);
            }
        }
        localStorage.setItem('selectedStyle', title);
    }

    window.addEventListener('load', () => {
        const savedStyle = localStorage.getItem('selectedStyle');
        if (savedStyle) {
            setActiveStyleSheet(savedStyle);
        } else {
            setActiveStyleSheet("default");
        }
    });
</script>

<script type="text/javascript" src="/f/js/jquery.min.js"></script>
<script type="text/javascript" src="/f/js/main.js"></script>
<script type="text/javascript" src="/f/js/inline-expanding.js"></script>
<script type="text/javascript" src="/f/js/hide-form.js"></script>
</head>
<body class="visitor is-not-moderator active-index" data-stylesheet="default">
<header><h1>/<?php echo $board_name_js; ?>/ - Random</h1><div class="subtitle"></div></header>
    <?php
}

function render_footer(): void {
    ?>
<footer>
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

function render_board_index_with_array(array $posts, int $page = 1, int $total_pages = 1): void {
    $csrf_token = htmlspecialchars(get_global_csrf_token(), ENT_QUOTES|ENT_SUBSTITUTE|ENT_HTML5);
    render_header('/' . $GLOBALS['board_name'] . '/ - Random', 'index');
    ?>
    <form name="post" enctype="multipart/form-data" action="post.php?action=new_thread" method="post">
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
    $all_posts = load_posts();
    foreach ($posts as $op) {
        render_single_thread($all_posts, $op);
    }

    // Pagination
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

function render_single_thread(array $all_posts, array $post): void {
    global $board_name;
    $id = (int)$post['id'];
    $name = get_tripcode($post['name']);
    $subject = htmlspecialchars($post['subject'], ENT_QUOTES|ENT_SUBSTITUTE|ENT_HTML5);
    $comment = nl2br(htmlspecialchars($post['comment'], ENT_QUOTES|ENT_SUBSTITUTE|ENT_HTML5));

    $reply_count = 0;
    foreach ($all_posts as $p) {
        if ($p['parent_id'] === $id && !$p['deleted']) {
            $reply_count++;
        }
    }

    $image_html = render_image_html($post['image']);
    $reply_link_text = $reply_count > 0 ? "Reply[".$reply_count."]" : "Reply";
    $thread_url = 'threads/thread_' . $id . '.html';

    // Latest reply
    $replies = get_replies($all_posts, $id);
    $latest_reply_html = '';
    if (!empty($replies)) {
        $lr = end($replies);
        $latest_reply_name = get_tripcode($lr['name']);
        $latest_reply_text = nl2br(htmlspecialchars($lr['comment'], ENT_QUOTES|ENT_SUBSTITUTE|ENT_HTML5));
        $lr_img_html = render_image_html($lr['image']);

        $latest_reply_html = '
        <div class="post reply" id="latest_reply">
            <p class="intro"><span class="name">'.$latest_reply_name.'</span></p>
            '.$lr_img_html.'
            <div class="body bold-center">Latest reply</div>
            <div class="body">'.$latest_reply_text.'</div>
        </div>';
    }

    echo '<div class="thread" id="thread_'.$id.'" data-board="'.htmlspecialchars($board_name, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5).'">';
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
    global $board_name;
    $csrf_token = htmlspecialchars(get_global_csrf_token(), ENT_QUOTES|ENT_SUBSTITUTE|ENT_HTML5);
    $thread_id = (int)$op['id'];
    render_header('/' . $board_name . '/ - Random', 'thread');
    ?>
    <div class="banner">Posting mode: Reply <a class="unimportant" href="../index.html">[Return]</a> <a class="unimportant" href="#bottom">[Go to bottom]</a></div>

    <!-- Changed action to ../post.php to go up one directory from /threads/ to /f/ -->
    <form name="post" action="../post.php?action=reply&thread_id=<?php echo $thread_id; ?>" method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <input type="hidden" name="thread" value="<?php echo $thread_id; ?>">
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

    <!-- Also change delete form action to go up one directory -->
    <form name="postcontrols" action="../post.php?action=delete&thread_id=<?php echo $thread_id; ?>" method="post">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="board" value="<?php echo htmlspecialchars($board_name, ENT_QUOTES|ENT_SUBSTITUTE|ENT_HTML5); ?>" />

    <div class="thread" id="thread_<?php echo $thread_id; ?>">
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

    // Using absolute path for images
    $img_path = "/f/uploads/".htmlspecialchars($image, ENT_QUOTES|ENT_SUBSTITUTE|ENT_HTML5);

    if (in_array($image_ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
        return '
        <div class="files">
            <div class="file">
                <p class="fileinfo">File: <a href="'.$img_path.'">'.htmlspecialchars(basename($image), ENT_QUOTES|ENT_SUBSTITUTE|ENT_HTML5).'</a></p>
                <a href="'.$img_path.'" target="_blank"><img class="post-image" src="'.$img_path.'" alt="" /></a>
            </div>
        </div>';
    } elseif ($image_ext === 'mp4') {
        return '
        <div class="files">
            <div class="file">
                <p class="fileinfo">File: <a href="'.$img_path.'">'.htmlspecialchars(basename($image), ENT_QUOTES|ENT_SUBSTITUTE|ENT_HTML5).'</a></p>
                <video class="post-video" controls>
                    <source src="'.$img_path.'" type="video/mp4">
                    Your browser does not support the video tag.
                </video>
            </div>
        </div>';
    }
    return '';
}

// Ensure CSRF token exists
get_global_csrf_token();

// Process actions
$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $posts = load_posts();
    if ($action === 'new_thread' && isset($_POST['post'])) {
        // Create new thread
        $name = sanitize_input($_POST['name'] ?? '');
        $subject = sanitize_input($_POST['subject'] ?? '');
        $comment = sanitize_input($_POST['body'] ?? '');
        if ($name === '' || $subject === '' || $comment === '') {
            die("All fields (Name, Subject, Comment) are required.");
        }
        $datetime = gmdate('Y-m-d\TH:i:s\Z');
        global $allowed_exts, $upload_dir;
        $image_path = '';
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK && $_FILES['file']['size'] > 0) {
            $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed_exts, true)) {
                $filename = time() . '_' . random_int(1000,9999) . '.' . $ext;
                $target = $upload_dir . $filename;
                if (move_uploaded_file($_FILES['file']['tmp_name'], $target)) {
                    $image_path = $filename;
                }
            }
        }
        $id = get_next_post_id($posts);
        $posts[] = [
            'id' => $id,
            'parent_id' => 0,
            'name' => $name,
            'subject' => $subject,
            'comment' => $comment,
            'image' => $image_path,
            'datetime' => $datetime,
            'deleted' => false
        ];
        save_posts($posts);
        generate_all_index_pages();
        generate_static_thread($id);
        header("Location: index.html");
        exit;
    } elseif ($action === 'reply' && isset($_POST['post'])) {
        // New reply
        $thread_id = (int)($_GET['thread_id'] ?? 0);
        if ($thread_id <= 0) {
            die("Invalid thread ID.");
        }
        // Find OP
        $op = null;
        foreach ($posts as &$p) {
            if ($p['id'] === $thread_id && $p['parent_id'] === 0 && $p['deleted'] === false) {
                $op = &$p;
                break;
            }
        }
        if (!$op) {
            die("Thread not found or deleted.");
        }

        $name = sanitize_input($_POST['name'] ?? '');
        $comment = sanitize_input($_POST['body'] ?? '');
        if ($name === '' || $comment === '') {
            die("Name and Comment fields are required.");
        }

        $datetime = gmdate('Y-m-d\TH:i:s\Z');
        // No image upload for replies here, but could be added similarly to threads if needed
        $id = get_next_post_id($posts);
        $posts[] = [
            'id' => $id,
            'parent_id' => $thread_id,
            'name' => $name,
            'subject' => '',
            'comment' => $comment,
            'image' => '',
            'datetime' => $datetime,
            'deleted' => false
        ];

        // Bump thread time
        $op['datetime'] = $datetime;

        save_posts($posts);
        generate_all_index_pages();
        generate_static_thread($thread_id);
        header("Location: threads/thread_{$thread_id}.html");
        exit;
    } elseif ($action === 'delete' && isset($_POST['delete_selected'])) {
        // Admin deletion
        $thread_id = (int)($_GET['thread_id'] ?? 0);
        if ($thread_id <= 0) {
            die("Invalid thread ID.");
        }
        $op = null;
        foreach ($posts as &$p) {
            if ($p['id'] === $thread_id && $p['parent_id'] === 0 && $p['deleted'] === false) {
                $op = &$p;
                break;
            }
        }

        if (!$op) {
            header("Location: index.html");
            exit;
        }

        $admin_pw = $_POST['admin_pw'] ?? '';
        global $admin_password;
        if ($admin_pw === $admin_password) {
            $checked_posts = [];
            foreach ($_POST as $key => $val) {
                if (str_starts_with((string)$key, 'delete_') && $val === 'on') {
                    $post_id = (int)str_replace('delete_', '', $key);
                    if ($post_id > 0) {
                        $checked_posts[] = $post_id;
                    }
                }
            }

            if (!empty($checked_posts)) {
                if (in_array($thread_id, $checked_posts, true)) {
                    // Delete entire thread
                    foreach ($posts as &$post) {
                        if ($post['id'] === $thread_id || $post['parent_id'] === $thread_id) {
                            $post['deleted'] = true;
                        }
                    }
                    save_posts($posts);
                    generate_all_index_pages();
                    generate_static_thread($thread_id);
                    header("Location: ../index.html");
                    exit;
                } else {
                    // Delete selected replies
                    foreach ($posts as &$post) {
                        if (in_array($post['id'], $checked_posts, true)) {
                            $post['deleted'] = true;
                        }
                    }
                    save_posts($posts);
                    generate_all_index_pages();
                    generate_static_thread($thread_id);
                    header("Location: threads/thread_{$thread_id}.html");
                    exit;
                }
            }

            header("Location: threads/thread_{$thread_id}.html");
            exit;
        } else {
            // Wrong password
            header("Location: threads/thread_{$thread_id}.html");
            exit;
        }
    }
}

// If GET request:
$thread_id = (int)($_GET['thread_id'] ?? 0);
if ($thread_id > 0) {
    // Show thread page
    if (file_exists(__DIR__ . "/threads/thread_{$thread_id}.html")) {
        header("Location: threads/thread_{$thread_id}.html");
        exit;
    }

    // If thread page not found, generate if thread exists:
    $posts = load_posts();
    $op = null;
    foreach ($posts as $p) {
        if ($p['id'] === $thread_id && $p['parent_id'] === 0 && $p['deleted'] === false) {
            $op = $p;
            break;
        }
    }

    if (!$op) {
        // Thread not found
        if (!file_exists(__DIR__ . '/index.html')) {
            generate_all_index_pages();
        }
        header("Location: index.html");
        exit;
    }

    $replies = get_replies($posts, $thread_id);
    ob_start();
    render_thread_page($op, $replies);
    $html = ob_get_clean();
    file_put_contents(__DIR__ . '/threads/thread_' . $thread_id . '.html', $html, LOCK_EX);
    header("Location: threads/thread_{$thread_id}.html");
    exit;
}

// Show index
$page = (int)($_GET['page'] ?? 1);
if ($page < 1) $page = 1;

if (!file_exists(__DIR__ . '/index.html')) {
    generate_all_index_pages();
}

if ($page === 1) {
    header("Location: index.html");
    exit;
} else {
    header("Location: index_{$page}.html");
    exit;
}

