<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_role(array('admin','superadmin','editor'));
$msg='';
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_FILES['files'])){
    $cat=trim($_POST['category'] ?? '');
    $target=SARAH_ICONS_DIR . ($cat!=='' ? '/' . preg_replace('/[^a-zA-Z0-9._-]+/','_', $cat) : '');
    if(!is_dir($target)) @mkdir($target,0775,true);
    $n=0;
    foreach($_FILES['files']['tmp_name'] as $i=>$tmp){
        if(!is_uploaded_file($tmp)) continue;
        $name=basename($_FILES['files']['name'][$i]);
        if(strtolower(pathinfo($name,PATHINFO_EXTENSION))!=='svg') continue;
        $safe=preg_replace('/[^a-zA-Z0-9._ -]+/','_', $name);
        move_uploaded_file($tmp, $target.'/'.$safe);
        $n++;
    }
    audit_log('upload_icons', 'count='.$n);
    $msg='Upload concluído: '.$n.' ficheiro(s).';
}
app_header('SARAH Upload');
?>
<h1>Upload de pictogramas SVG</h1><p><a href="dashboard.php">← Dashboard</a></p>
<div class="card">
<?php if($msg): ?><p class="goodText"><?=h($msg)?></p><?php endif; ?>
<form method="post" enctype="multipart/form-data">
<label>Categoria/pasta opcional</label><input name="category" placeholder="comida">
<label>Ficheiros SVG</label><input type="file" name="files[]" multiple accept=".svg,image/svg+xml">
<button class="primary">Enviar</button>
</form>
</div>
<?php app_footer(); ?>
