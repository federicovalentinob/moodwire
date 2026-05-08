<?php

require_once __DIR__ . '/config.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        // Pin session timezone to UTC so CURRENT_TIMESTAMP / NOW() match PHP's date.timezone=UTC.
        // Without this, mixed CEST/UTC writes produce negative ages and break "fetch <15m" guards.
        $pdo->exec("SET time_zone = '+00:00'");
    }
    return $pdo;
}
