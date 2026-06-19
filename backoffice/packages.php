<?php
require_once __DIR__ . '/lib/packages.php';
require_role(array('admin','superadmin','editor'));
sarah_ensure_dirs();
$msg = '';
$err = '';

function package_post_redirect($msg='') {
    $q = $msg ? ('?msg=' . urlencode($msg)) : '';
    header('Location: packages.php' . $q);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        if ($action === 'create') {
            $name = trim($_POST['name'] ?? '');
            if ($name === '') throw new Exception('Indica o nome do pacote.');
            $slug = sarah_slug($name);
            $file = sarah_pack_file($slug);
            if (file_exists($file)) throw new Exception('Já existe um pacote com esse nome.');
            $mode = $_POST['mode'] ?? 'empty';
            $seed = $mode === 'copy_default' ? sarah_load_seed_file(SARAH_SEED_FILE) : sarah_empty_seed();
            sarah_save_seed_file($file, $seed, 'create_pack');
            package_post_redirect('Pacote criado: ' . $slug);
        }
        if ($action === 'import') {
            $name = trim($_POST['name'] ?? '');
            if ($name === '') $name = 'pacote_importado_' . date('Ymd_His');
            if (!isset($_FILES['json']) || $_FILES['json']['error'] !== UPLOAD_ERR_OK) throw new Exception('Ficheiro JSON não recebido.');
            $raw = file_get_contents($_FILES['json']['tmp_name']);
            $seed = json_decode($raw, true);
            sarah_validate_seed($seed);
            $slug = sarah_slug($name);
            sarah_save_seed_file(sarah_pack_file($slug), $seed, 'import_pack');
            package_post_redirect('Pacote importado: ' . $slug);
        }
        if ($action === 'set_default') {
            require_role(array('admin','superadmin'));
            $pack = $_POST['pack'] ?? '';
            if ($pack === '' || $pack === '__default') throw new Exception('Escolhe um pacote de utilizador.');
            $seed = sarah_load_seed_file(sarah_pack_file($pack));
            sarah_save_seed_file(SARAH_SEED_FILE, $seed, 'set_default_pack_ui');
            package_post_redirect('Pacote definido como principal.');
        }
        if ($action === 'delete') {
            require_role(array('admin','superadmin'));
            $pack = $_POST['pack'] ?? '';
            if ($pack === '' || $pack === '__default') throw new Exception('Não é permitido apagar o pacote principal.');
            $file = sarah_pack_file($pack);
            if (!file_exists($file)) throw new Exception('Pacote não encontrado.');
            rename($file, $file . '.deleted_' . date('Ymd_His'));
            audit_log('delete_pack_ui', $pack);
            package_post_redirect('Pacote removido.');
        }
    }
} catch (Throwable $e) {
    $err = $e->getMessage();
}
if (isset($_GET['msg'])) $msg = $_GET['msg'];
$packs = sarah_list_packs();
$packDir = sarah_pack_dir();
$packDirWritable = is_writable($packDir);
$storageWritable = is_writable(SARAH_STORAGE_DIR);
$userSlug = sarah_current_pack_slug();
app_header('SARAH Pacotes');
?>
<p><a href="dashboard.php">← Dashboard</a> · <b>Pacotes</b></p>
<h1>Gestão de pacotes SARAH-ACC</h1>
<p class="muted">Aqui geres o pacote principal da PWA e os pacotes publicados por cada utilizador. O <code>seed.json</code> fica reservado como pacote principal/default.</p>
<?php if ($msg): ?><div class="card goodText"><b><?=h($msg)?></b></div><?php endif; ?>
<?php if ($err): ?><div class="card errText"><b>Erro:</b> <?=h($err)?></div><?php endif; ?>
<div class="card"><b>Estado de gravação:</b> storage <?= $storageWritable ? '<span class="goodText">OK</span>' : '<span class="errText">sem escrita</span>' ?> · packs <?= $packDirWritable ? '<span class="goodText">OK</span>' : '<span class="errText">sem escrita</span>' ?><br><span class="muted">Pasta: <code><?=h($packDir)?></code></span></div>

<div class="grid">
  <div class="card">
    <h2>Criar pacote</h2>
    <form method="post">
      <input type="hidden" name="action" value="create">
      <p class="muted">Será criado em <code>storage/packs/nome_do_pacote.json</code>.</p>
      <label>Nome do pacote</label>
      <input name="name" value="<?=h($userSlug)?>" placeholder="antonio_santos, turma_3a, terapia_fala...">
      <label>Base inicial</label>
      <select name="mode">
        <option value="copy_default">Copiar pacote principal atual</option>
        <option value="empty">Criar vazio</option>
      </select>
      <button type="submit" class="primary">Criar pacote</button>
    </form>
  </div>
  <div class="card">
    <h2>Importar pacote</h2>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="action" value="import">
      <label>Nome para guardar</label>
      <input name="name" placeholder="nome_do_utilizador">
      <label>Ficheiro JSON</label>
      <input type="file" name="json" accept="application/json,.json">
      <button type="submit" class="primary">Importar JSON</button>
    </form>
  </div>
  <div class="card">
    <h2>URLs importantes</h2>
    <p>Pacote principal da PWA:</p>
    <code>https://sarah.aeaveromar.pt/backoffice/storage/seed.json</code>
    <p class="muted">Pacotes de utilizador ficam em <code>/backoffice/storage/packs/nomedoutilizador.json</code>.</p>
  </div>
</div>

<div class="card">
<h2>Pacotes disponíveis</h2>
<table>
<tr><th>Pacote</th><th>Tipo</th><th>Categorias</th><th>Itens</th><th>Atualizado</th><th>URL/ficheiro</th><th>Ações</th></tr>
<?php foreach ($packs as $p): ?>
<tr>
  <td><b><?=h($p['name'])?></b><br><span class="muted"><?=h($p['slug'])?></span></td>
  <td><?= $p['is_default'] ? '<span class="goodText">Principal/default</span>' : 'Utilizador' ?></td>
  <td><?=h($p['categories'])?></td>
  <td><?=h($p['items'])?></td>
  <td><?=h(substr($p['updated_at'],0,19))?></td>
  <td><code><?=h($p['path'])?></code></td>
  <td>
    <a class="btn primary" href="editor_v3.php?pack=<?=urlencode($p['slug'])?>&v=<?=time()?>">Editar</a>
    <a class="btn" href="<?=h($p['path'])?>" download>Download</a>
    <?php if (!$p['is_default']): ?>
      <form method="post" style="display:inline" onsubmit="return confirm('Definir este pacote como principal/default?')">
        <input type="hidden" name="action" value="set_default"><input type="hidden" name="pack" value="<?=h($p['slug'])?>"><button class="good">Tornar principal</button>
      </form>
      <form method="post" style="display:inline" onsubmit="return confirm('Remover este pacote?')">
        <input type="hidden" name="action" value="delete"><input type="hidden" name="pack" value="<?=h($p['slug'])?>"><button class="danger">Remover</button>
      </form>
    <?php endif; ?>
  </td>
</tr>
<?php endforeach; ?>
</table>
</div>

<div class="card">
<h2>Fluxo recomendado</h2>
<ol>
<li>Criar ou importar um pacote com o nome do utilizador.</li>
<li>Editar no Editor V3.</li>
<li>Guardar/publicar o pacote. Ele será gravado como <code>storage/packs/nomedoutilizador.json</code>.</li>
<li>Quando estiver validado, usar <b>Tornar principal</b> para copiar para <code>storage/seed.json</code>.</li>
</ol>
</div>
<?php app_footer(); ?>
