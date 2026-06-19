<?php
require __DIR__ . '/_safe_loader.php';
header('Content-Type: application/json; charset=utf-8');
echo json_encode(array(
 'ok'=>true,
 'php_version'=>PHP_VERSION,
 'memory_limit'=>ini_get('memory_limit'),
 'max_execution_time'=>ini_get('max_execution_time'),
 'document_root'=>$_SERVER['DOCUMENT_ROOT'] ?? '',
 'script_filename'=>$_SERVER['SCRIPT_FILENAME'] ?? ''
), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
