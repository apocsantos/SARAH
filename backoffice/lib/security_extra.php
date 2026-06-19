<?php
function security_headers(): void {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
}
function ensure_post_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_check($_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('Token CSRF inválido.');
    }
}
function rate_limit_simple(string $bucket, int $maxHits, int $windowSeconds): void {
    $ip = client_ip();
    $dir = storage_root() . DIRECTORY_SEPARATOR . '_ratelimit';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $safeBucket = preg_replace('/[^a-zA-Z0-9._-]/', '_', $bucket);
    $safeIp = preg_replace('/[^a-zA-Z0-9._:-]/', '_', $ip);
    $file = $dir . DIRECTORY_SEPARATOR . $safeBucket . '_' . $safeIp . '.json';

    $now = time();
    $data = ['hits' => []];
    if (file_exists($file)) {
        $decoded = json_decode((string)file_get_contents($file), true);
        if (is_array($decoded) && isset($decoded['hits']) && is_array($decoded['hits'])) $data = $decoded;
    }
    $data['hits'] = array_values(array_filter($data['hits'], fn($t) => is_int($t) && $t > ($now - $windowSeconds)));
    if (count($data['hits']) >= $maxHits) {
        http_response_code(429);
        exit('Too many requests');
    }
    $data['hits'][] = $now;
    file_put_contents($file, json_encode($data));
}
function seed_payload_or_fail(array $seed): array {
    if (!seed_is_valid_structure($seed)) {
        http_response_code(400);
        exit('Estrutura de seed.json inválida.');
    }
    return $seed;
}
function sanitize_svg_content(string $svg): ?string {
    if (stripos($svg, '<svg') === false) return null;
    $dangerPatterns = [
        '/<script\b[^>]*>.*?<\/script>/is',
        '/\son\w+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/is',
        '/javascript\s*:/is',
        '/<iframe\b[^>]*>.*?<\/iframe>/is',
        '/<object\b[^>]*>.*?<\/object>/is',
        '/<embed\b[^>]*>.*?<\/embed>/is',
        '/<foreignObject\b[^>]*>.*?<\/foreignObject>/is',
    ];
    foreach ($dangerPatterns as $pattern) $svg = preg_replace($pattern, '', $svg);
    if (stripos($svg, '<svg') === false) return null;
    return trim($svg);
}
function require_svg_or_fail(string $svg): string {
    $sanitized = sanitize_svg_content($svg);
    if ($sanitized === null || $sanitized === '') {
        http_response_code(400);
        exit('SVG inválido ou perigoso.');
    }
    return $sanitized;
}
function require_https_or_local(): void {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') == '443');
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $isLocal = str_contains($host, 'localhost') || str_contains($host, '127.0.0.1');
    if (!$https && !$isLocal) header('Warning: 199 - HTTPS recommended');
}
function password_policy_min_length(): int {
    return (int)(CONFIG['password_min_length'] ?? 12);
}
function password_is_strong_enough(string $password): bool {
    if (strlen($password) < password_policy_min_length()) return false;
    return (bool)(preg_match('/[A-Z]/', $password) && preg_match('/[a-z]/', $password) && preg_match('/\d/', $password));
}
function session_idle_timeout(): int {
    return (int)(CONFIG['session_idle_timeout'] ?? 1800);
}
function enforce_session_idle_timeout(): void {
    $now = time();
    if (!empty($_SESSION['authenticated'])) {
        $last = (int)($_SESSION['last_activity_at'] ?? $now);
        if (($now - $last) > session_idle_timeout()) {
            $_SESSION = [];
            session_destroy();
            session_start();
            header('Location: index.php?expired=1');
            exit;
        }
        $_SESSION['last_activity_at'] = $now;
    }
}
function log_login_attempt_db(string $username, bool $success, string $stage): void {
    $stmt = db()->prepare("INSERT INTO login_attempts_v2 (username, ip, success, stage, created_at) VALUES (?,?,?,?,NOW())");
    $stmt->execute([$username, client_ip(), $success ? 1 : 0, $stage]);
}
function is_ip_locked_db(): bool {
    $minutes = 15; $max = 12;
    $stmt = db()->prepare("SELECT COUNT(*) FROM login_attempts_v2 WHERE ip = ? AND success = 0 AND created_at >= (NOW() - INTERVAL ? MINUTE)");
    $stmt->execute([client_ip(), $minutes]);
    return ((int)$stmt->fetchColumn()) >= $max;
}
function is_user_locked_db(string $username): bool {
    $minutes = 15; $max = 6;
    $stmt = db()->prepare("SELECT COUNT(*) FROM login_attempts_v2 WHERE username = ? AND success = 0 AND created_at >= (NOW() - INTERVAL ? MINUTE)");
    $stmt->execute([$username, $minutes]);
    return ((int)$stmt->fetchColumn()) >= $max;
}
function create_password_reset_token(int $userId): array {
    $selector = bin2hex(random_bytes(12));
    $token = bin2hex(random_bytes(32));
    $tokenHash = password_hash($token, PASSWORD_DEFAULT);
    $stmt = db()->prepare("INSERT INTO password_reset_tokens (user_id, selector, token_hash, expires_at, created_at) VALUES (?,?,?,?,NOW())");
    $stmt->execute([$userId, $selector, $tokenHash, date('Y-m-d H:i:s', time() + 3600)]);
    return ['selector' => $selector, 'token' => $token];
}
function find_valid_reset_token(string $selector): ?array {
    $stmt = db()->prepare("SELECT * FROM password_reset_tokens WHERE selector = ? AND used_at IS NULL AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
    $stmt->execute([$selector]);
    $row = $stmt->fetch();
    return $row ?: null;
}
function mark_reset_token_used(int $id): void {
    $stmt = db()->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ?");
    $stmt->execute([$id]);
}
function send_password_reset_email(string $toEmailOrUsername, string $selector, string $token): bool {
    $base = rtrim((string)(CONFIG['mail_reset_base_url'] ?? ''), '/');
    if ($base === '') return false;
    $from = (string)(CONFIG['mail_from'] ?? '');
    $link = $base . '/reset_password.php?selector=' . urlencode($selector) . '&token=' . urlencode($token);
    $subject = 'SARAH - Reset de password';
    $body = "Foi pedido um reset de password.\n\nUsa este link nas próximas 1 hora:\n$link\n\nSe não foste tu, ignora este email.";
    $headers = '';
    if ($from !== '') $headers .= "From: " . $from . "\r\n";
    return @mail($toEmailOrUsername, $subject, $body, $headers);
}
