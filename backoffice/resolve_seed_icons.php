<?php

header('Content-Type: text/html; charset=utf-8');

$storage = __DIR__ . '/storage';
$iconsRoot = $storage . '/icons';
$seedFile = $storage . '/seed.json';

function norm_txt($s) {
    $s = strtolower($s);
    $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    if ($t !== false) $s = $t;
    $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
    return trim(preg_replace('/\s+/', ' ', $s));
}

function rel_path($full, $root) {
    return str_replace('\\','/', ltrim(substr($full, strlen($root)), '/\\'));
}

$icons = [];

$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($iconsRoot, FilesystemIterator::SKIP_DOTS)
);

foreach ($it as $f) {
    if (strtolower($f->getExtension()) !== 'svg') continue;

    $rel = rel_path($f->getPathname(), $storage);

    $icons[] = [
        'rel' => $rel,
        'norm' => norm_txt($f->getFilename() . ' ' . $rel)
    ];
}

$seed = json_decode(file_get_contents($seedFile), true);

if (!isset($seed['itens'])) {
    die('Seed inválido');
}

$fixed = 0;

foreach ($seed['itens'] as &$item) {

    $icon = $item['icone'] ?? '';
    $texto = $item['texto'] ?? '';

    $full = $storage . '/' . $icon;

    if ($icon && file_exists($full)) {
        continue;
    }

    $best = null;
    $bestScore = 0;

    $search = norm_txt($texto . ' ' . basename($icon));

    foreach ($icons as $ic) {

        similar_text($search, $ic['norm'], $pct);

        if ($pct > $bestScore) {
            $bestScore = $pct;
            $best = $ic['rel'];
        }
    }

    if ($best && $bestScore > 20) {
        $item['icone'] = $best;
        $fixed++;
    }
}

copy($seedFile, $seedFile . '.bak_' . date('Ymd_His'));

file_put_contents(
    $seedFile,
    json_encode(
        $seed,
        JSON_PRETTY_PRINT |
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    )
);

echo "<h1>Concluído</h1>";
echo "<p>Ícones corrigidos: $fixed</p>";