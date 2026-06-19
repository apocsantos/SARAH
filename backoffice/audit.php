<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_role(array('admin','superadmin'));
$rows=db()->query("SELECT * FROM audit_log ORDER BY id DESC LIMIT 300")->fetchAll(PDO::FETCH_ASSOC);
app_header('SARAH Audit');
?>
<h1>Audit log</h1><p><a href="dashboard.php">← Dashboard</a></p>
<div class="card"><table><tr><th>Data</th><th>User</th><th>Ação</th><th>Detalhes</th></tr><?php foreach($rows as $r): ?><tr><td><?=h($r['created_at'])?></td><td><?=h($r['username'])?></td><td><?=h($r['action'])?></td><td><?=h($r['details'])?></td></tr><?php endforeach; ?></table></div>
<?php app_footer(); ?>
