<?php
header('Content-Type: text/plain; charset=utf-8');

$seedFile = __DIR__ . '/storage/packs/sarah_arasaac_dia_a_dia_pt/seed.json';

if (!file_exists($seedFile)) {
    die("seed.json não encontrado\n");
}

$seed = json_decode(file_get_contents($seedFile), true);

if (!$seed || !isset($seed['itens'])) {
    die("seed.json inválido ou sem itens\n");
}

$backup = $seedFile . '.bak_' . date('Ymd_His');
copy($seedFile, $backup);

$fixed = 0;

foreach ($seed['itens'] as &$item) {
    if (!empty($item['icone'])) {
        $old = $item['icone'];

        $item['icone'] = preg_replace('/\s+/', '_', $item['icone']);

        if ($old !== $item['icone']) {
            echo "$old -> {$item['icone']}\n";
            $fixed++;
        }
    }
}

file_put_contents(
    $seedFile,
    json_encode($seed, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
);

echo "\nConcluído.\n";
echo "Corrigidos: $fixed\n";
echo "Backup: $backup\n";