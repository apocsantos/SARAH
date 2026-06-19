<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_login();
sarah_ensure_dirs();
app_header('SARAH Dashboard');
?>
<h1>SARAH - Dashboard V3</h1>
<p class="muted">Utilizador: <?=h($_SESSION['user']['username'])?> · Perfil: <?=h($_SESSION['user']['role'])?></p>
<div class="grid">
  <div class="card"><h2>1. Pacotes</h2><p>Gerir pacotes por utilizador, pacote principal/default, importação e publicação.</p><a class="btn primary" href="packages.php">Gerir pacotes</a></div>
  <div class="card"><h2>2. Editor visual</h2><p>Editar categorias, frases e escolher pictogramas da biblioteca.</p><a class="btn primary" href="editor_v3.php?pack=__default&v=3">Abrir editor V3</a><a class="btn" href="editor.php?v=999">Editor antigo</a></div>
  <div class="card"><h2>3. Biblioteca</h2><p>Navegar, pesquisar e carregar pictogramas ARASAAC no servidor.</p><a class="btn" href="library_browser.php">Navegar biblioteca</a><a class="btn" href="upload.php">Upload</a></div>
  <div class="card"><h2>4. Ferramentas</h2><p>Corrigir caminhos, auditar e limpar a biblioteca SVG.</p><a class="btn" href="resolve_seed_icons.php">Resolver ícones</a><a class="btn" href="fix_seed_underscores.php">Corrigir underscores</a><a class="btn" href="tools/library_cleanup.php">Auditoria</a><a class="btn warn" href="tools/library_auto_cleanup.php">Limpeza automática</a></div>
  <div class="card"><h2>5. Administração</h2><p>Utilizadores, 2FA e logs de auditoria.</p><a class="btn" href="users.php">Utilizadores</a><a class="btn" href="audit.php">Audit log</a></div>
  <div class="card"><h2>Sessão</h2><p>Terminar sessão de forma segura.</p><a class="btn danger" href="logout.php">Sair</a></div>
</div>
<?php app_footer(); ?>
