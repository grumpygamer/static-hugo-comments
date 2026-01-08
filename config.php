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

// Size of preview text sent to discord
define('PREVIEW_LENGTH', 80)

?>
