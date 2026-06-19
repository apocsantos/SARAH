<?php
$configFile = __DIR__ . '/../config.php';
if (!file_exists($configFile)) die('Falta config.php');
define('CONFIG', require $configFile);

session_name(CONFIG['session_name'] ?? 'SARAHSESSID');
session_set_cookie_params([
  'lifetime'=>0,'path'=>'/','domain'=>'',
  'secure'=>!empty(CONFIG['session_secure']),
  'httponly'=>true,'samesite'=>CONFIG['session_samesite'] ?? 'Strict'
]);
session_start();

require __DIR__ . '/db.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/totp.php';

if (!is_dir(CONFIG['storage_root'])) mkdir(CONFIG['storage_root'], 0775, true);
