<?php
require __DIR__ . '/_safe_loader.php';
require_once __DIR__ . '/../lib/packages.php';
require_role(array('admin','superadmin'));
header('Content-Type: application/json; charset=utf-8');
try {
    $pack = isset($_POST['pack']) ? $_POST['pack'] : (isset($_GET['pack']) ? $_GET['pack'] : '');
    if ($pack === '' || $pack === '__default') throw new Exception('Indica um pacote válido.');
    $src = sarah_pack_file($pack);
    if (!file_exists($src)) throw new Exception('Pacote não encontrado.');
    $seed = sarah_load_seed_file($src);
    $res = sarah_save_seed_file(SARAH_SEED_FILE, $seed, 'set_default_pack');
    echo json_encode(array('ok'=>true,'default'=>'storage/seed.json','source'=>sarah_pack_public_path($pack),'backup'=>$res['backup']), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(array('ok'=>false,'error'=>$e->getMessage()), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}
