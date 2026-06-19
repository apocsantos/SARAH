<?php
function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        CONFIG['db_host'],
        (int)(CONFIG['db_port'] ?? 3306),
        CONFIG['db_name'],
        CONFIG['db_charset'] ?? 'utf8mb4'
    );

    $pdo = new PDO($dsn, CONFIG['db_user'], CONFIG['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}
