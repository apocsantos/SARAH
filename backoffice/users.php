<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_role(array('superadmin'));
$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(($_POST['action']??'')==='create'){
        $secret=random_base32();
        create_user(trim($_POST['username']), (string)$_POST['password'], $_POST['role'] ?? 'editor', $secret);
        audit_log('create_user', $_POST['username']);
        $msg='Utilizador criado. Secret TOTP: '.$secret;
    }
}
$users=db()->query("SELECT id,username,role,active,created_at,totp_secret FROM users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
app_header('SARAH Utilizadores');
?>
<h1>Utilizadores</h1><p><a href="dashboard.php">← Dashboard</a></p>
<?php if($msg): ?><div class="card goodText"><?=h($msg)?></div><?php endif; ?>
<div class="card"><h2>Novo utilizador</h2><form method="post"><input type="hidden" name="action" value="create"><label>Username</label><input name="username"><label>Password</label><input name="password" type="password"><label>Perfil</label><select name="role"><option>editor</option><option>admin</option><option>superadmin</option></select><button class="primary">Criar</button></form></div>
<div class="card"><h2>Lista</h2><table><tr><th>ID</th><th>User</th><th>Role</th><th>Ativo</th><th>TOTP Secret</th></tr><?php foreach($users as $u): ?><tr><td><?=h($u['id'])?></td><td><?=h($u['username'])?></td><td><?=h($u['role'])?></td><td><?=h($u['active'])?></td><td><code><?=h($u['totp_secret'])?></code></td></tr><?php endforeach; ?></table></div>
<?php app_footer(); ?>
