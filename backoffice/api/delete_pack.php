<?php
require __DIR__ . '/_safe_loader.php';
require_once __DIR__ . '/../lib/packages.php';
require_role(array('admin','superadmin'));
header('Content-Type: application/json; charset=utf-8');
try {
    $pack = isset($_POST['pack']) ? $_POST['pack'] : (isset($_GET['pack']) ? $_GET['pack'] : '');
    if ($pack === '' || $pack === '__default') throw new Exception('Não é permitido apagar o pacote principal.');
    $file = sarah_pack_file($pack);
    if (!file_exists($file)) throw new Exception('Pacote não encontrado.');
    $backup = $file . '.deleted_' . date('Ymd_His');
    rename($file, $backup);
    audit_log('delete_pack', basename($file).' -> '.basename($backup));
    echo json_encode(array('ok'=>true,'backup'=>basename($backup)), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(array('ok'=>false,'error'=>$e->getMessage()), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}
