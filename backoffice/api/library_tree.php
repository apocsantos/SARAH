<?php
require __DIR__ . '/../lib/common.php';

$root = sarah_storage_root();
$iconsRoot = $root . DIRECTORY_SEPARATOR . 'icons';

$requested = $_GET['dir'] ?? '';
$requested = sarah_normalize_relative_path($requested);

if ($requested !== '' && !str_starts_with($requested, 'icons')) {
    $requested = 'icons/' . $requested;
}

$currentRel = $requested === '' ? 'icons' : $requested;
$currentAbs = sarah_safe_storage_path($currentRel);

if (!is_dir($iconsRoot)) {
    mkdir($iconsRoot, 0775, true);
}

$realIcons = realpath($iconsRoot);
$realCurrent = realpath($currentAbs);

if ($realCurrent === false || $realIcons === false || !str_starts_with($realCurrent, $realIcons)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Diretoria inválida'], JSON_UNESCAPED_UNICODE);
    exit;
}

$dirs = [];
$files = [];

foreach (scandir($realCurrent) ?: [] as $entry) {
    if ($entry === '.' || $entry === '..') continue;
    $abs = $realCurrent . DIRECTORY_SEPARATOR . $entry;
    $rel = str_replace('\\', '/', substr($abs, strlen($root) + 1));

    if (is_dir($abs)) {
        $dirs[] = ['name' => $entry, 'path' => $rel];
    } elseif (is_file($abs) && strtolower(pathinfo($entry, PATHINFO_EXTENSION)) === 'svg') {
        $content = file_get_contents($abs);
        $files[] = [
            'name' => $entry,
            'path' => $rel,
            'size' => filesize($abs),
            'content' => $content !== false ? $content : '',
        ];
    }
}

usort($dirs, fn($a,$b) => strcasecmp($a['name'], $b['name']));
usort($files, fn($a,$b) => strcasecmp($a['name'], $b['name']));

$parent = null;
if ($currentRel !== 'icons') {
    $parts = explode('/', $currentRel);
    array_pop($parts);
    $parent = implode('/', $parts);
    if ($parent === '') $parent = 'icons';
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok' => true,
    'current' => $currentRel,
    'parent' => $parent,
    'dirs' => $dirs,
    'files' => $files,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
