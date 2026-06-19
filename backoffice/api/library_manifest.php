<?php
require __DIR__ . '/_safe_loader.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $storageRoot = sarah_bo_storage_root();
    $iconsRoot = sarah_bo_icons_root();

    $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    if ($offset < 0) $offset = 0;

    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 80;
    if ($limit < 1) $limit = 80;
    if ($limit > 120) $limit = 120;

    $includeContent = true;
    if (isset($_GET['content']) && $_GET['content'] === '0') {
        $includeContent = false;
    }

    list($items, $scanned, $matched, $returned) = sarah_bo_scan_svg_limited(
        $iconsRoot,
        $storageRoot,
        $q,
        $limit,
        $offset,
        $includeContent
    );

    echo json_encode(array(
        'ok' => true,
        'items' => $items,
        'returned' => $returned,
        'matched_seen' => $matched,
        'scanned' => $scanned,
        'limit' => $limit,
        'offset' => $offset,
        'query' => $q,
        'storage_root' => $storageRoot,
        'icons_root' => $iconsRoot,
        'icons_root_exists' => is_dir($iconsRoot)
    ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(array(
        'ok' => false,
        'error' => $e->getMessage(),
        'php_version' => PHP_VERSION
    ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
?>