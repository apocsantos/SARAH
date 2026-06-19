<?php
require __DIR__ . '/../api/_safe_loader.php';
require_role(array('admin','superadmin'));
function ac_slug($text){$text=preg_replace('/\.svg$/i','',$text);$t=@iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$text);if($t!==false)$text=$t;$text=strtolower($text);$text=preg_replace('/[^a-z0-9]+/','_',$text);return trim($text,'_')?:'icone_'.substr(sha1($text),0,8);}
function spath($root,$rel){$parts=array();foreach(explode('/',str_replace('\\','/',$rel)) as $p){if($p===''||$p==='.'||$p==='..')continue;$parts[]=$p;}return $root.DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR,$parts);}
$mode=$_GET['mode']??'';$result=null;
$storage=sarah_bo_storage_root();$icons=sarah_bo_icons_root();
$records=array();$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($icons,FilesystemIterator::SKIP_DOTS));
foreach($it as $f){if(strtolower($f->getExtension())!=='svg')continue;$rel=sarah_bo_rel_path($f->getPathname(),$storage);$records[]=array('rel'=>$rel,'dir'=>dirname($rel),'slug'=>ac_slug(basename($rel)),'size'=>$f->getSize(),'mtime'=>$f->getMTime());}
$groups=array();foreach($records as $r){$groups[$r['dir'].'/'.$r['slug']][]=$r;}
$plan=array();foreach($groups as $k=>$g){usort($g,function($a,$b){return $b['size']-$a['size'];});foreach($g as $i=>$r){$target=$r['dir'].'/'.$r['slug'].'.svg';$plan[]=array('action'=>$i===0?($r['rel']===$target?'keep':'rename'):'backup_duplicate','source'=>$r['rel'],'target'=>$target,'winner'=>$g[0]['rel'],'size'=>$r['size']);}}
if($mode==='apply'){ $stamp=date('Ymd_His');$backup=$storage.'/_duplicates_backup/'.$stamp;@mkdir($backup,0775,true);foreach($plan as $p){if($p['action']==='backup_duplicate'){ $src=spath($storage,$p['source']); if(file_exists($src)){ $dst=$backup.'/'.str_replace('/','_',$p['source']); @rename($src,$dst);}}}foreach($plan as $p){if($p['action']==='rename'){ $src=spath($storage,$p['source']);$dst=spath($storage,$p['target']); if(file_exists($src)&&$src!==$dst){@rename($src,$dst);}}}audit_log('auto_cleanup','actions='.count($plan));$result='Aplicado. Backup: '.$backup;}
app_header('Limpeza automática');
?>
<h1>Limpeza automática</h1><p><a href="../dashboard.php">← Dashboard</a></p>
<div class="card"><p>Plano: <?=count($plan)?> ações. SVG: <?=count($records)?></p><?php if($result): ?><p class="goodText"><?=h($result)?></p><?php endif; ?><a class="btn" href="?mode=simulate">Simular</a><a class="btn danger" onclick="return confirm('Aplicar limpeza com backup?')" href="?mode=apply">Aplicar</a></div>
<div class="card"><table><tr><th>Ação</th><th>Origem</th><th>Destino/vencedor</th><th>Tamanho</th></tr><?php foreach(array_slice($plan,0,500) as $p): ?><tr><td><?=h($p['action'])?></td><td><?=h($p['source'])?></td><td><?=h($p['action']==='backup_duplicate'?$p['winner']:$p['target'])?></td><td><?=h($p['size'])?></td></tr><?php endforeach; ?></table></div>
<?php app_footer(); ?>
