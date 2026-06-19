<?php
require __DIR__ . '/_safe_loader.php';
header('Content-Type: application/json; charset=utf-8');
$storage=sarah_bo_storage_root();
$icons=sarah_bo_icons_root();
$count=0;$sample=array();
if(is_dir($icons)){
 $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($icons, FilesystemIterator::SKIP_DOTS));
 foreach($it as $f){
  if(strtolower($f->getExtension())==='svg'){
   $count++;
   if(count($sample)<20) $sample[]=sarah_bo_rel_path($f->getPathname(), $storage);
  }
 }
}
echo json_encode(array('ok'=>true,'storage_root'=>$storage,'icons_root'=>$icons,'icons_root_exists'=>is_dir($icons),'svg_scanned_first_pass'=>$count,'sample_count'=>count($sample),'sample'=>$sample,'php_version'=>PHP_VERSION,'php_memory_limit'=>ini_get('memory_limit'),'php_max_execution_time'=>ini_get('max_execution_time')), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
