<?php
require __DIR__ . '/../lib/bootstrap.php';
require_role(['superadmin','editor']);
rate_limit_simple('api_publish', 60, 300);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>false,'error'=>'Método não permitido'], JSON_UNESCAPED_UNICODE);
    exit;
}
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') === false) {
    http_response_code(415);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>false,'error'=>'Content-Type inválido'], JSON_UNESCAPED_UNICODE);
    exit;
}
$raw = file_get_contents('php://input');
$data = json_decode((string)$raw, true);
if (!is_array($data) || !isset($data['seed']) || !is_array($data['seed'])) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>false,'error'=>'Payload inválido'], JSON_UNESCAPED_UNICODE);
    exit;
}
$seed = seed_payload_or_fail($data['seed']);
file_put_contents(seed_path(), json_encode($seed, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
$assets = $data['assets'] ?? [];
if (is_array($assets)) {
    foreach ($assets as $asset) {
        $path = normalize_relative_path((string)($asset['path'] ?? ''));
        $content = (string)($asset['content'] ?? '');
        if ($path === '' || !str_ends_with(strtolower($path), '.svg')) continue;
        $content = require_svg_or_fail($content);
        $target = safe_storage_path($path);
        $dir = dirname($target);
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        file_put_contents($target, $content);
    }
}
$u=current_user(); audit_log('api_publish_package','seed + assets', (int)$u['id'], (string)$u['username']);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
