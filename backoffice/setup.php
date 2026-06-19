<?php
require_once __DIR__ . '/lib/bootstrap.php';
sarah_ensure_dirs();
$error = '';
$created = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? 'admin');
    $password = (string)($_POST['password'] ?? '');
    $role = 'superadmin';
    if ($username === '' || strlen($password) < 8) {
        $error = 'Indica utilizador e password com pelo menos 8 caracteres.';
    } else {
        $count = db()->query("SELECT COUNT(*) FROM users")->fetchColumn();
        if ($count > 0) {
            $error = 'Já existem utilizadores. Usa users.php autenticado.';
        } else {
            $secret = random_base32();
            create_user($username, $password, $role, $secret);
            file_put_contents(__DIR__ . '/installed.lock', date('c'));
            $created = array('username'=>$username,'secret'=>$secret);
        }
    }
}
app_header('SARAH Setup');
?>
<h1>SARAH - Setup inicial</h1>
<div class="card">
<?php if ($created): ?>
<p class="goodText"><b>Superadmin criado.</b></p>
<p>Utilizador: <code><?=h($created['username'])?></code></p>
<p>Secret TOTP para Google Authenticator / Aegis / Microsoft Authenticator:</p>
<p><code><?=h($created['secret'])?></code></p>
<p>Guarda este código. Depois entra em <a href="index.php">index.php</a>.</p>
<?php else: ?>
<?php if ($error): ?><p class="errText"><?=h($error)?></p><?php endif; ?>
<form method="post">
<label>Utilizador</label><input name="username" value="admin">
<label>Password</label><input name="password" type="password">
<button class="primary">Criar superadmin</button>
</form>
<?php endif; ?>
</div>
<?php app_footer(); ?>
