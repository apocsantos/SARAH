<?php
require_once __DIR__ . '/lib/bootstrap.php';
audit_log('logout');
session_destroy();
header('Location: index.php');
exit;
