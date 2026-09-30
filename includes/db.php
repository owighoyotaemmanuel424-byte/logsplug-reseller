<?php
declare(strict_types=1);
require_once __DIR__.'/config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $pdo = createDatabaseConnection(false);
    return $pdo;
}

function db_direct(): PDO
{
    return createDatabaseConnection(true);
}
