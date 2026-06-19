<?php
require __DIR__ . '/_safe_loader.php';
require_once __DIR__ . '/../lib/packages.php';
header('Content-Type: application/json; charset=utf-8');
$pack = isset($_GET['pack']) ? $_GET['pack'] : '__default';
$file = sarah_pack_file($pack);
if (!file_exists($file) && $pack === '__default') {
    file_put_contents($file, json_encode(sarah_empty_seed(), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
}
$seed = sarah_load_seed_file($file);
echo json_encode($seed, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
