<?php
header('Content-Type: application/json; charset=utf-8');

$name = $_GET['name'] ?? '';
$name = strtolower($name);
$name = preg_replace('/[^a-z0-9_\-]/', '', $name);

if ($name === '') {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'Nome inválido']);
    exit;
}

$file = __DIR__ . '/../storage/packs/' . $name . '.json';

if (!file_exists($file)) {
    http_response_code(404);
    echo json_encode(['ok'=>false,'error'=>'Pack não encontrado']);
    exit;
}

readfile($file);