<?php
require __DIR__ . '/_safe_loader.php';
require_once __DIR__ . '/../lib/packages.php';
require_role(array('admin','superadmin','editor'));
header('Content-Type: application/json; charset=utf-8');
try {
    $pack = isset($_GET['pack']) ? $_GET['pack'] : '__user';
    if ($pack === '') $pack = '__user';
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    $seed = isset($data['seed']) ? $data['seed'] : $data;
    $file = sarah_pack_file($pack);
    $res = sarah_save_seed_file($file, $seed, $pack === '__default' ? 'save_default_seed' : 'save_user_pack');
    echo json_encode(array('ok'=>true,'pack'=>$pack,'file'=>basename($file),'path'=>sarah_pack_public_path($pack),'backup'=>$res['backup'],'icon_paths_fixed'=>$res['icon_paths_fixed']), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(array('ok'=>false,'error'=>$e->getMessage()), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}
