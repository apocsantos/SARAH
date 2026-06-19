<?php
require_once __DIR__ . '/lib/bootstrap.php';
app_header('Recuperação');
?>
<h1>Recuperação de password</h1>
<div class="card"><p>Por segurança, a recuperação é feita por superadmin em <code>users.php</code> ou alterando diretamente via setup/SQLite. Não há envio de email automático neste pacote.</p><p><a href="index.php">Voltar</a></p></div>
<?php app_footer(); ?>
