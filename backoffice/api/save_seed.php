<?php
require __DIR__ . '/_safe_loader.php';
require_once __DIR__ . '/../lib/packages.php';
require_role(array('admin','superadmin','editor'));
header('Content-Type: application/json; charset=utf-8');
try {
    $raw=file_get_contents('php://input');
    $data=json_decode($raw,true);
    $seed=isset($data['seed']) ? $data['seed'] : $data;
    $res = sarah_save_seed_file(SARAH_SEED_FILE, $seed, 'save_seed_legacy');
    echo json_encode(array('ok'=>true,'backup'=>$res['backup'],'icon_paths_fixed'=>$res['icon_paths_fixed']), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch(Throwable $e) {
    http_response_code(400);
    echo json_encode(array('ok'=>false,'error'=>$e->getMessage()), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}
