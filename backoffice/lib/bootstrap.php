<?php
if (!file_exists(__DIR__ . '/../config.php')) {
    if (basename($_SERVER['SCRIPT_NAME']) !== 'setup.php') {
        header('Location: setup.php');
        exit;
    }
} else {
    require_once __DIR__ . '/../config.php';
}

if (!defined('SARAH_SESSION_NAME')) define('SARAH_SESSION_NAME', 'SARAH_BACKOFFICE');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name(SARAH_SESSION_NAME);
    session_start();
}

function sarah_ensure_dirs() {
    $dirs = array(
        SARAH_STORAGE_DIR,
        SARAH_ICONS_DIR,
        SARAH_STORAGE_DIR . '/reports',
        SARAH_STORAGE_DIR . '/exports',
        SARAH_STORAGE_DIR . '/packs',
        SARAH_STORAGE_DIR . '/_duplicates_backup'
    );
    foreach ($dirs as $d) {
        if (!is_dir($d)) @mkdir($d, 0775, true);
    }
}

function db_path() {
    return SARAH_STORAGE_DIR . '/backoffice.sqlite';
}

function db() {
    sarah_ensure_dirs();
    static $pdo = null;
    if ($pdo) return $pdo;
    $pdo = new PDO('sqlite:' . db_path());
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'editor',
        totp_secret TEXT,
        active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS audit_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT,
        action TEXT,
        details TEXT,
        created_at TEXT NOT NULL
    )");
    return $pdo;
}

function audit_log($action, $details='') {
    try {
        $u = isset($_SESSION['user']['username']) ? $_SESSION['user']['username'] : null;
        $stmt = db()->prepare("INSERT INTO audit_log(username, action, details, created_at) VALUES(?,?,?,?)");
        $stmt->execute(array($u, $action, $details, date('c')));
    } catch (Throwable $e) {}
}

function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

function current_user() {
    return isset($_SESSION['user']) ? $_SESSION['user'] : null;
}

function require_login() {
    if (!current_user()) redirect('index.php?expired=1');
}

function require_role($roles) {
    require_login();
    if (!in_array($_SESSION['user']['role'], $roles, true)) {
        http_response_code(403);
        echo "Acesso negado.";
        exit;
    }
}

function ensure_auth() {
    require_login();
}

function user_by_username($username) {
    $stmt = db()->prepare("SELECT * FROM users WHERE username=? AND active=1");
    $stmt->execute(array($username));
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function create_user($username, $password, $role='superadmin', $totp_secret=null) {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = db()->prepare("INSERT INTO users(username,password_hash,role,totp_secret,active,created_at) VALUES(?,?,?,?,1,?)");
    $stmt->execute(array($username, $hash, $role, $totp_secret, date('c')));
}

function random_base32($length=32) {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $out = '';
    for ($i=0; $i<$length; $i++) $out .= $alphabet[random_int(0, strlen($alphabet)-1)];
    return $out;
}

function base32_decode_sarah($b32) {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $b32));
    $bits = '';
    for ($i=0; $i<strlen($b32); $i++) {
        $v = strpos($alphabet, $b32[$i]);
        if ($v === false) continue;
        $bits .= str_pad(decbin($v), 5, '0', STR_PAD_LEFT);
    }
    $bytes = '';
    for ($i=0; $i+8<=strlen($bits); $i+=8) {
        $bytes .= chr(bindec(substr($bits, $i, 8)));
    }
    return $bytes;
}

function hotp($secret, $counter, $digits=6) {
    $key = base32_decode_sarah($secret);
    $binCounter = pack('N*', 0) . pack('N*', $counter);
    $hash = hash_hmac('sha1', $binCounter, $key, true);
    $offset = ord(substr($hash, -1)) & 0x0F;
    $truncated = unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF;
    return str_pad((string)($truncated % pow(10, $digits)), $digits, '0', STR_PAD_LEFT);
}

function verify_totp($secret, $code, $window=1) {
    $code = preg_replace('/\s+/', '', (string)$code);
    if (!preg_match('/^\d{6}$/', $code)) return false;
    $time = floor(time() / 30);
    for ($i=-$window; $i<=$window; $i++) {
        if (hash_equals(hotp($secret, $time + $i), $code)) return true;
    }
    return false;
}

function captcha_question() {
    if (!isset($_SESSION['captcha_a'])) {
        $_SESSION['captcha_a'] = random_int(1, 9);
        $_SESSION['captcha_b'] = random_int(1, 9);
    }
    return $_SESSION['captcha_a'] . ' + ' . $_SESSION['captcha_b'];
}

function captcha_check($answer) {
    if (!defined('SARAH_ENABLE_CAPTCHA') || !SARAH_ENABLE_CAPTCHA) return true;
    $ok = isset($_SESSION['captcha_a'], $_SESSION['captcha_b']) && ((int)$answer === ($_SESSION['captcha_a'] + $_SESSION['captcha_b']));
    unset($_SESSION['captcha_a'], $_SESSION['captcha_b']);
    return $ok;
}

function app_header($title='SARAH Backoffice') {
    echo '<!doctype html><html lang="pt"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.h($title).'</title><link rel="stylesheet" href="assets/style.css"></head><body><div class="wrap">';
}

function app_footer() {
    echo '</div></body></html>';
}
