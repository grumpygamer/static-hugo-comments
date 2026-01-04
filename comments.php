<?php

// If you want to use sqlite3 from the command line, permissions need
// be set so the sqlite3 doesn't block all writes.

// # 1. Add your user to the www-data group (if not already there)
// sudo usermod -aG www-data {USER}

// # 2. Change ownership of the folder and file to the shared group
// sudo chown -R www-data:www-data {DB_DIR}

// # 3. Apply the 'Sticky Group' bit to the directory
// # This ensures new files (like -wal and -shm) inherit the 'www-data' group
// sudo chmod g+s {DB_DIR}

// # 4. Set directory permissions so group members can write/execute
// sudo chmod 775 {DB_DIR}

// DB_DIR must be read/write for the web server
define('DB_DIR', "path/to/dir");

// Comment out this line to use flat files
define('DB_FILE', DB_DIR."/db.sqlite");

// File to disable all comments in an emergency (spam attack)
define('KILL_FILE', DB_DIR."/kill");

// Use ADMIN_SECRET for name to post as owner and getting check mark
define('ADMIN_SECRET', "my_secret_username");
// Replaces with these values
define('ADMIN_NAME', "My Name");
define('ADMIN_EMAIL', "my@email.com");

// Notify discord if comment is spam
define('SPAM_NOTIFY', true);

// Save spam in db
define('SPAM_SAVE', true);

// Comment out for no capcha
// Uses https://www.hcaptcha.com/
define('CAPCHA_SECRET', "xxxxx");
define('CAPCHA_DATAKEY', "xxxxx");

// Discord webhook, comment out to disable Discord posting
define('DISCORD_WEBHOOK', "https://discord.com/api/webhooks/xxxxx");

$host = $_SERVER['HTTP_HOST'];
$post = trim($_SERVER['REQUEST_URI'], "/");

// ----------------------------------------------------------------------------
function init_db() {
    $db = new SQLite3(DB_FILE);
    $db->exec("CREATE TABLE IF NOT EXISTS comments (
               id INTEGER PRIMARY KEY AUTOINCREMENT,
               name TEXT,
               email TEXT,
               text TEXT,
               url TEXT,
               date TEXT,
               ip TEXT,
               spam BOOLEAN)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_comments_url ON comments(url,spam)");
    $db->exec("PRAGMA journal_mode=WAL;");
    $db->exec("PRAGMA synchronous=NORMAL;");
    return $db;
}

// ----------------------------------------------------------------------------
function relDate($date) {
    if (is_string($date)) {
        $date = strtotime($date);
    }
    $date = intval($date);
    $rel_date = time() - $date;
    if ($rel_date / 60 / 60 / 24 >= 7) {
        return date("M d, Y", $date);
    }
    if ($rel_date / 60 / 60 / 24 >= 1) {
        return strval(intval($rel_date / 60 / 60 / 24)) . "d ago";
    }
    if ($rel_date / 60 / 60 > 1) {
        return strval(intval($rel_date / 60 / 60)) . "h ago";
    }
    if ($rel_date / 60 > 1) {
        return strval(intval($rel_date / 60)) . "m ago";
    }
    return strval(intval($rel_date)) . "s ago";
}

// ----------------------------------------------------------------------------
function simpleFormat($text) {
    // Strip any html tags
    $text = strip_tags($text);

    // Add any additonal formating of comments here

    // Replace cr/lf with <br> for formatting
    $text = str_replace("\r\n", "<br>", $text);
    $text = str_replace("\n", "<br>", $text);

    return $text;
}

// ----------------------------------------------------------------------------
function isSpam($text) {
    // Don't allow @ used in an email addresses
    if (preg_match('/(\S)\@(\S)/', $text)) return true;

    // Traps posting bbcode
    if (preg_match('/\[.*?\]/', $text)) return true;

    // Put any words here that should be flaged as spam
    if (stripos($text, "penis") !== false) return true;
    if (stripos($text, "fuck") !== false) return true;
    if (stripos($text, "cbd") !== false) return true;
    if (stripos($text, "xevil") !== false) return true;
    if (stripos($text, "bitch") !== false) return true;
    if (stripos($text, "https:") !== false) return true;
    if (stripos($text, "http:") !== false) return true;
    if (stripos($text, "<a ") !== false) return true;
    if (stripos($text, "href=") !== false) return true;
    if (stripos($text, ".com") !== false) return true;

    return false;
}

// ----------------------------------------------------------------------------
function notifyComment($comment) {
    if (defined("DISCORD_WEBHOOK")) {
        if ($comment['spam'] == true && !SPAM_NOTIFY) return

        $title = ""; // For reasons I don't understand, this is needed.
        $title = $comment['spam'] === true ? "New comment spam" : "New comment";

        $content = "{$title} in {$comment['url']} from {$comment['name']}.";
        $content .= "\n".mb_strimwidth($comment['text'], 0, 80, "...");

        $data = [
            "content" => $content,
            "username" => "Comment Robot"
        ];

        $json_data = json_encode($data);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, DISCORD_WEBHOOK);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        curl_close($ch);
    }
}

if (defined("DB_FILE")) {
    $db = init_db();
}

// ----------------------------------------------------------------------------
// Handle Kill Switch
if (defined("KILL_FILE") && is_file(KILL_FILE)) {
    die; // Comments are globally disabled
}

// ----------------------------------------------------------------------------
// Handle New Comment Submission
if (isset($_POST['comment_button'])) {

    $date = time();
    $comment = [
        'name' => $_REQUEST['comment_name'],
        'email' => $_REQUEST['comment_email']??"",
        'text' => $_REQUEST['comment_text'],
        'url' => $_REQUEST['comment_url'],
        'ip' => $_SERVER['REMOTE_ADDR'],
        // The following are only used for flat files
        'filename' => $date, // filename is just the timestamp
        'id' => $date,       // id is also the timestamp
        'date' => $date,
    ];

    $date = $comment['date'];
    $name = $comment['name'];
    $email = $comment['email'];
    $text = $comment['text'];
    $url = $comment['url'];
    $ip = $comment['ip'];
    $spam = false;

    // Stop invalid post slugs, probably from bots.
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $url)) {
        header("Location: https://$host");
        die;
    }

    // Didn't leave a comment
    if (trim($text) === "") {
        header("Location: https://$host/$url");
        die();
    }

    if (defined("CAPCHA_SECRET")) {
        $data = [
            'secret' => CAPCHA_SECRET,
            'response' => $_POST['h-captcha-response'],
            'remoteip' => $_SERVER['REMOTE_ADDR']
        ];
        $verify = curl_init();
        curl_setopt($verify, CURLOPT_URL, "https://hcaptcha.com/siteverify");
        curl_setopt($verify, CURLOPT_POST, true);
        curl_setopt($verify, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($verify, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($verify);
        $responseData = json_decode($response);
        if (!$responseData->success) {
            header("Location: https://$host/$url");
            die;
        }
    }

    // Has spam words?
    if (isSpam($text)) {
        if (!SPAM_SAVE)  {
            header("Location: https://$host/$url");
            die;
        }
        $spam = true;
    }

    if (defined("DB_FILE")) {
        $stmt = $db->prepare("INSERT INTO comments
                              (name, email, text, url, date, ip, spam)
                              VALUES
                              (:name, :email, :text, :url, datetime('now'), :ip, :spam)");
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $stmt->bindValue(':text', $text, SQLITE3_TEXT);
        $stmt->bindValue(':url', $url, SQLITE3_TEXT);
        $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
        $stmt->bindValue(':spam', $spam, SQLITE3_INTEGER);
        $stmt->execute();
        $last_id = $db->lastInsertRowID();
        $comment['id'] = $last_id;
        $comment['spam'] = $spam;
        syslog(LOG_WARNING, $comment['spam'] ? "Adding spam id: $last_id'"
                                             : "Adding comment id: $last_id");
        notifyComment($comment);
        header("Location: https://$host/$url#$last_id");
        die();
    } else {
        // Save the comment as a flat file?
        // Create the directory for the comment
        $dir = DB_DIR."/$url";
        if (!is_dir($dir)) {
            if (mkdir($dir) === false) {
                syslog(LOG_WARNING, "Error making directory '$dir'");
                header("Location: https://$host");
                die();
            }
        }

        $comment['spam'] = $spam;
        $filename = "{$comment['filename']}.json";
        $contents = json_encode($comment, JSON_PRETTY_PRINT);
        if ($contents === false) {
            syslog(LOG_WARNING, "Bad json_encode encoding");
            header("Location: https://$host");
            die();
        }
        if (file_put_contents("$dir/$filename", $contents) === false) {
            syslog(LOG_WARNING, "Error writing file '$dir/$filename'");
            header("Location: https://$host");
            die();
        }
        syslog(LOG_WARNING, $comment['spam'] ? "Adding spam '$dir/$filename'"
                                             : "Adding comment '$dir/$filename'");
        notifyComment($comment);
        header("Location: https://$host/$url#{$comment['id']}");
        die();
    }
} else {
    // Stop calls to comments.php directly as it is probably from bots
    if ($post === "comments.php") {
        header("Location: https://$host");
        die;
    }
}

// ----------------------------------------------------------------------------
// Fetch comments for display

$comments = [];

if (defined("DB_FILE")) {
    $stmt = $db->prepare("SELECT *
                          FROM comments
                          WHERE url = :url AND spam = 0
                          ORDER BY date ASC");
    $stmt->bindValue(':url', $post, SQLITE3_TEXT);
    $result = $stmt->execute();

    while ($comment_data = $result->fetchArray(SQLITE3_ASSOC)) {
        $name = trim($comment_data['name'] ?? "");
        $email = trim($comment_data['email'] ?? "");

        if ($name == "") $name = "Anonymous";

        if ($name == ADMIN_SECRET || $email == ADMIN_SECRET) {
            $email = ADMIN_EMAIL;
            $name = ADMIN_NAME . " <span class='check'>✔</span>";
        } else {
            // Keep commenters from trying post as admin's name/email
            if ($name == ADMIN_NAME || $email == ADMIN_EMAIL) {
                $name = "Anonymous";
                $email = "";
            }
        }

        $comments[] = [
            'name' => $name,
            'date' => relDate($comment_data['date'] ?? 0),
            'raw_date' => intval($comment_data['date'] ?? 0),
            'text' => simpleFormat($comment_data['text']),
            'id' => $comment_data['id'] ?? 'none',
        ];
    }
} else {
    $dir = DB_DIR."/$post";
    if (is_dir($dir)) {
        // Comes back sorted by file name, which is unixtime
        $comment_files = scandir($dir);
        foreach ($comment_files as $filename) {
            $path = "$dir/$filename";
            if (is_file($path)) {
                $comment_data = json_decode(file_get_contents($path), true);

                // Skip comment if blank or spam or deleted
                if (empty($comment_data['text']) || !empty($comment_data['spam'])) {
                    continue;
                }

                $name = trim($comment_data['name'] ?? "");
                $email = trim($comment_data['email'] ?? "");

                if ($name == "") $name = "Anonymous";

                if ($name == ADMIN_SECRET || $email == ADMIN_SECRET) {
                    $email = ADMIN_EMAIL;
                    $name = ADMIN_NAME . " <span class='check'>✔</span>";
                } else {
                    // Keep commenters from trying post as admin
                    if ($name == ADMIN_NAME || $email == ADMIN_EMAIL) {
                        $name = "Anonymous";
                        $email = "";
                    }
                }

                $comments[] = [
                    'name' => $name,
                    'date' => relDate($comment_data['date'] ?? 0),
                    'raw_date' => intval($comment_data['date'] ?? 0),
                    'text' => simpleFormat($comment_data['text']),
                    'id' => $comment_data['id'] ?? 'none',
                ];
            }
        }
    }
}

?>

<div class='comments'>
    <hr>
    <h3>Comments:</h3>

    <?php if (empty($comments)): ?>
        <div>None yet</div>
    <?php else: ?>
        <?php foreach ($comments as $comment): ?>
            <div class='comment'>
                <div class='comment-info' id='<?php echo htmlspecialchars($comment['id']); ?>'>
                    <span class='name'><?php echo $comment['name']; ?></span>
                    <span class='date' nortreblig='<?php echo htmlspecialchars($comment['raw_date']); ?>'><?php echo htmlspecialchars($comment['date']); ?></span>
                    <div class='text'><?php echo $comment['text']; ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div>
        <h3>Add comment:</h3>
        <form action="https://<?php echo htmlspecialchars($host); ?>/comments.php" method='post' accept-charset='UTF-8'>
            <div>
                <label class="sr-only2" for="comment_name">Name</label>
                <input type="text" class="input-control" name="comment_name" id="comment_name" placeholder="Elaine Marley" required>
            </div>
            <div class='form-group'>
                <label class="sr-only2" for="comment_text">Brief Message</label>
                <textarea class='input-control' name='comment_text' id='comment_text' rows='5' placeholder="Be nice!  Plain text only.  No urls or links." required></textarea>
            </div>
            <input type="hidden" name="comment_url" value="<?php echo htmlspecialchars($post); ?>">

            <?php if (defined("CAPCHA_SECRET")): ?>
                <div class='h-captcha'
                     data-sitekey="<?php echo CAPCHA_DATAKEY; ?>"
                     data-theme='dark'>
                </div>
                <script src='https://js.hcaptcha.com/1/api.js' async defer></script>
            <?php endif; ?>

            <button type='submit' name='comment_button' class='button'>Comment</button>
        </form>
    </div>
</div>
