<?php
require __DIR__ . '/_safe_loader.php';
require_once __DIR__ . '/../lib/packages.php';
header('Content-Type: application/json; charset=utf-8');
try {
    $pack = isset($_GET['pack']) ? $_GET['pack'] : '__default';
    $file = sarah_pack_file($pack);
    $seed = sarah_load_seed_file($file);
    echo json_encode(array('ok'=>true,'pack'=>$pack,'path'=>sarah_pack_public_path($pack),'seed'=>$seed), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(array('ok'=>false,'error'=>$e->getMessage()), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}
