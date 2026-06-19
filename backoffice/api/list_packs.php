<?php
require __DIR__ . '/_safe_loader.php';
require_once __DIR__ . '/../lib/packages.php';
header('Content-Type: application/json; charset=utf-8');
try {
    echo json_encode(array('ok'=>true,'packs'=>sarah_list_packs()), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(array('ok'=>false,'error'=>$e->getMessage()), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}
