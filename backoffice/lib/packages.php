<?php
require_once __DIR__ . '/bootstrap.php';

function sarah_pack_dir() {
    sarah_ensure_dirs();
    $dir = SARAH_STORAGE_DIR . '/packs';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    return $dir;
}

function sarah_slug($s) {
    $s = strtolower(trim((string)$s));
    if ($s === '') $s = 'pacote';
    if (function_exists('transliterator_transliterate')) {
        $t = transliterator_transliterate('Any-Latin; Latin-ASCII', $s);
        if ($t !== false) $s = $t;
    } else {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if ($t !== false) $s = $t;
    }
    $s = preg_replace('/[^a-z0-9]+/', '_', $s);
    $s = trim($s, '_');
    return $s !== '' ? $s : 'pacote';
}

function sarah_current_pack_slug() {
    $u = current_user();
    return sarah_slug($u && isset($u['username']) ? $u['username'] : 'utilizador');
}

function sarah_pack_file($pack) {
    $pack = (string)$pack;
    if ($pack === '' || $pack === '__user') $pack = sarah_current_pack_slug();
    if ($pack === '__default' || $pack === 'seed') return SARAH_SEED_FILE;
    $slug = sarah_slug($pack);
    return sarah_pack_dir() . '/' . $slug . '.json';
}

function sarah_pack_public_path($pack) {
    if ($pack === '__default' || $pack === 'seed') return 'storage/seed.json';
    return 'storage/packs/' . sarah_slug($pack) . '.json';
}

function sarah_empty_seed() {
    return array(
        'versao' => 1,
        'idioma' => 'pt-PT',
        'voz' => array('lang'=>'pt-PT','rate'=>1,'pitch'=>1),
        'categorias' => array(),
        'itens' => array()
    );
}

function sarah_load_seed_file($file) {
    if (!file_exists($file)) return sarah_empty_seed();
    $raw = file_get_contents($file);
    $seed = json_decode($raw, true);
    if (!is_array($seed)) return sarah_empty_seed();
    foreach (sarah_empty_seed() as $k=>$v) {
        if (!array_key_exists($k, $seed)) $seed[$k] = $v;
    }
    if (!is_array($seed['categorias'])) $seed['categorias'] = array();
    if (!is_array($seed['itens'])) $seed['itens'] = array();
    return $seed;
}

function sarah_normalize_icon_paths(&$value, &$count=0) {
    if (is_array($value)) {
        foreach ($value as &$v) sarah_normalize_icon_paths($v, $count);
        return;
    }
    if (is_string($value) && stripos($value, '.svg') !== false) {
        $old = $value;
        $value = str_replace('%20', '_', $value);
        $value = preg_replace('/\s+/', '_', $value);
        if ($old !== $value) $count++;
    }
}

function sarah_validate_seed($seed) {
    if (!is_array($seed)) throw new Exception('JSON inválido.');
    foreach (array('versao','idioma','voz','categorias','itens') as $k) {
        if (!array_key_exists($k, $seed)) throw new Exception('Campo em falta: '.$k);
    }
    if (!is_array($seed['categorias']) || !is_array($seed['itens'])) {
        throw new Exception('Categorias/itens inválidos.');
    }
}

function sarah_save_seed_file($file, $seed, $auditAction='save_pack') {
    sarah_validate_seed($seed);
    $fixed = 0;
    sarah_normalize_icon_paths($seed, $fixed);
    $dir = dirname($file);
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $backup = '';
    if (file_exists($file)) {
        $backup = $file . '.bak_' . date('Ymd_His');
        @copy($file, $backup);
    }
    file_put_contents($file, json_encode($seed, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    audit_log($auditAction, basename($file).'; itens='.count($seed['itens']).'; categorias='.count($seed['categorias']).'; icon_paths_fixed='.$fixed);
    return array('backup'=>$backup, 'icon_paths_fixed'=>$fixed);
}

function sarah_list_packs() {
    $packs = array();
    if (file_exists(SARAH_SEED_FILE)) {
        $seed = sarah_load_seed_file(SARAH_SEED_FILE);
        $packs[] = array(
            'slug'=>'__default',
            'name'=>'Pacote principal',
            'file'=>'seed.json',
            'path'=>'storage/seed.json',
            'items'=>count($seed['itens']),
            'categories'=>count($seed['categorias']),
            'updated_at'=>date('c', filemtime(SARAH_SEED_FILE)),
            'is_default'=>true
        );
    }
    $dir = sarah_pack_dir();
    foreach (glob($dir . '/*.json') ?: array() as $file) {
        $slug = basename($file, '.json');
        $seed = sarah_load_seed_file($file);
        $packs[] = array(
            'slug'=>$slug,
            'name'=>str_replace('_',' ', $slug),
            'file'=>basename($file),
            'path'=>'storage/packs/'.basename($file),
            'items'=>count($seed['itens']),
            'categories'=>count($seed['categorias']),
            'updated_at'=>date('c', filemtime($file)),
            'is_default'=>false
        );
    }
    return $packs;
}
?>
