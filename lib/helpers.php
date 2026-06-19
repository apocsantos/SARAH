<?php
function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function csrf_token(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function csrf_check(string $token): bool { return !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token); }
function storage_root(): string { return rtrim(CONFIG['storage_root'], '/\\'); }
function seed_path(): string { return storage_root() . DIRECTORY_SEPARATOR . (CONFIG['seed_filename'] ?? 'seed.json'); }
function normalize_relative_path(string $path): string {
  $path = str_replace('\\', '/', trim($path));
  $path = preg_replace('#/+#', '/', $path);
  $parts = [];
  foreach (explode('/', $path) as $part) {
    if ($part === '' || $part === '.' || $part === '..') continue;
    $parts[] = preg_replace('/[^a-zA-Z0-9._-]/', '_', $part);
  }
  return implode('/', $parts);
}
function safe_storage_path(string $relative): string { return storage_root() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, normalize_relative_path($relative)); }
function captcha_regenerate_if_missing(): void { if (!isset($_SESSION['captcha_a'], $_SESSION['captcha_b'])) { $_SESSION['captcha_a']=random_int(1,9); $_SESSION['captcha_b']=random_int(1,9);} }
function captcha_check(string $answer): bool {
  if (empty(CONFIG['local_captcha_enabled'])) return true;
  $ok = ((int)$answer === ((int)($_SESSION['captcha_a'] ?? -1) + (int)($_SESSION['captcha_b'] ?? -1)));
  $_SESSION['captcha_a']=random_int(1,9); $_SESSION['captcha_b']=random_int(1,9); return $ok;
}
function current_user(): ?array {
  if (empty($_SESSION['user_id'])) return null;
  $s = db()->prepare('SELECT id, username, role, is_active, totp_secret FROM users WHERE id = ?');
  $s->execute([(int)$_SESSION['user_id']]);
  $u = $s->fetch();
  return $u ?: null;
}
function is_authenticated(): bool { $u = current_user(); return !!($u && !empty($u['is_active']) && !empty($_SESSION['authenticated'])); }
function ensure_auth(): void { if (!is_authenticated()) { header('Location: index.php'); exit; } }
function require_role(array $roles): void { ensure_auth(); $u=current_user(); if(!$u || !in_array($u['role'],$roles,true)){ http_response_code(403); exit('Acesso negado.'); } }
function logout_now(): void {
  $_SESSION = [];
  if (ini_get('session.use_cookies')) { $p = session_get_cookie_params(); setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']); }
  session_destroy();
}
function audit_log(string $action, string $details='', ?int $userId=null, ?string $username=null): void {
  $s = db()->prepare('INSERT INTO audit_log (user_id, username, ip, action, details, created_at) VALUES (?,?,?,?,?,NOW())');
  $ip = $_SERVER['REMOTE_ADDR'] ?? '';
  $s->execute([$userId, $username, $ip, $action, $details]);
}
function seed_json_array(): array { $p=seed_path(); if(!file_exists($p)) return []; $j=json_decode((string)file_get_contents($p), true); return is_array($j)?$j:[]; }
function seed_is_valid_structure(array $j): bool { return isset($j['versao'],$j['idioma'],$j['voz'],$j['categorias'],$j['itens']) && is_array($j['voz']) && is_array($j['categorias']) && is_array($j['itens']); }
function seed_missing_icons(): array {
  $j = seed_json_array(); $out = [];
  foreach (($j['itens'] ?? []) as $item) {
    $icon = normalize_relative_path((string)($item['icone'] ?? ''));
    if ($icon === '' || !str_ends_with(strtolower($icon), '.svg')) continue;
    if (!file_exists(safe_storage_path($icon))) $out[] = ['id'=>(string)($item['id']??''),'texto'=>(string)($item['texto']??''),'icone'=>$icon];
  }
  return $out;
}
function list_storage_files(string $root): array {
  $out=[]; if(!is_dir($root)) return $out;
  $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
  foreach ($it as $f) if ($f->isFile()) $out[]=['path'=>str_replace('\\','/',substr($f->getPathname(), strlen(rtrim($root,'/\\'))+1)),'size'=>$f->getSize(),'mtime'=>$f->getMTime()];
  usort($out, fn($a,$b)=>strcmp($a['path'],$b['path'])); return $out;
}
