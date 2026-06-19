<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_login();

function sarah_bo_storage_root() {
    if (defined('SARAH_STORAGE_DIR')) return realpath(SARAH_STORAGE_DIR) ?: SARAH_STORAGE_DIR;
    return realpath(__DIR__ . '/../storage') ?: (__DIR__ . '/../storage');
}

function sarah_bo_icons_root() {
    if (defined('SARAH_ICONS_DIR')) return realpath(SARAH_ICONS_DIR) ?: SARAH_ICONS_DIR;
    return realpath(__DIR__ . '/../storage/icons') ?: (__DIR__ . '/../storage/icons');
}

function sarah_bo_rel_path($full, $storageRoot) {
    $fullReal = realpath($full);
    $rootReal = realpath($storageRoot);
    if ($fullReal && $rootReal && strpos($fullReal, $rootReal) === 0) {
        $rel = ltrim(substr($fullReal, strlen($rootReal)), DIRECTORY_SEPARATOR);
    } else {
        $rel = basename($full);
    }
    return str_replace(DIRECTORY_SEPARATOR, '/', $rel);
}

function sarah_bo_norm($s) {
    $s = strtolower((string)$s);
    if (function_exists('transliterator_transliterate')) {
        $t = transliterator_transliterate('Any-Latin; Latin-ASCII', $s);
        if ($t !== false) $s = $t;
    } else {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if ($t !== false) $s = $t;
    }
    $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
    return trim($s);
}

function sarah_bo_scan_svg_limited($iconsRoot, $storageRoot, $q='', $limit=80, $offset=0, $includeContent=true) {
    $items = array();
    $scanned = 0;
    $matched = 0;
    $returned = 0;

    if (!is_dir($iconsRoot)) {
        return array($items, $scanned, $matched, $returned);
    }

    $qNorm = sarah_bo_norm($q);
    $stack = array($iconsRoot);

    while (!empty($stack)) {
        $dir = array_pop($stack);
        $entries = @scandir($dir);
        if ($entries === false) continue;

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;

            $full = $dir . DIRECTORY_SEPARATOR . $entry;

            if (is_dir($full)) {
                $stack[] = $full;
                continue;
            }

            if (!is_file($full)) continue;
            if (strtolower(pathinfo($entry, PATHINFO_EXTENSION)) !== 'svg') continue;

            $scanned++;

            $rel = sarah_bo_rel_path($full, $storageRoot);
            $name = pathinfo(basename($rel), PATHINFO_FILENAME);
            $hay = sarah_bo_norm($rel . ' ' . $name);

            if ($qNorm !== '' && strpos($hay, $qNorm) === false) continue;

            $matched++;

            if ($matched <= $offset) continue;
            if ($returned >= $limit) continue;

            $content = '';
            if ($includeContent) {
                $content = @file_get_contents($full);
                if ($content === false || strlen($content) > 220000) {
                    $content = '';
                }
            }

            $items[] = array(
                'path' => $rel,
                'fileName' => basename($rel),
                'name' => $name,
                'category' => basename(dirname($rel)),
                'size' => @filesize($full) ?: 0,
                'content' => $content
            );

            $returned++;
        }
    }

    return array($items, $scanned, $matched, $returned);
}
?>