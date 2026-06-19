<?php
require __DIR__ . '/../api/_safe_loader.php';
require_role(array('admin','superadmin'));
function slug($text){$text=preg_replace('/\.svg$/i','',$text);$t=@iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$text);if($t!==false)$text=$t;$text=strtolower($text);$text=preg_replace('/[^a-z0-9]+/','_',$text);return trim($text,'_')?:'icone_'.substr(sha1($text),0,8);}
$storage=sarah_bo_storage_root();$icons=sarah_bo_icons_root();$groups=array();$total=0;
$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($icons,FilesystemIterator::SKIP_DOTS));
foreach($it as $f){if(strtolower($f->getExtension())!=='svg')continue;$total++;$rel=sarah_bo_rel_path($f->getPathname(),$storage);$key=dirname($rel).'/'.slug(basename($rel));$groups[$key][]=array('rel'=>$rel,'size'=>$f->getSize());}
app_header('Auditoria biblioteca');
?>
<h1>Auditoria da biblioteca</h1><p><a href="../dashboard.php">← Dashboard</a></p>
<div class="card"><p>Total SVG: <b><?=h($total)?></b></p><p>Grupos: <b><?=h(count($groups))?></b></p><p><a class="btn warn" href="library_auto_cleanup.php">Abrir limpeza automática</a></p></div>
<div class="card"><h2>Duplicados prováveis</h2>
<?php foreach($groups as $k=>$arr): if(count($arr)<2)continue; ?><h3><?=h($k)?> (<?=count($arr)?> ficheiros)</h3><table><tr><th>Path</th><th>Tamanho</th></tr><?php foreach($arr as $r): ?><tr><td><?=h($r['rel'])?></td><td><?=h($r['size'])?></td></tr><?php endforeach; ?></table><?php endforeach; ?>
</div><?php app_footer(); ?>
