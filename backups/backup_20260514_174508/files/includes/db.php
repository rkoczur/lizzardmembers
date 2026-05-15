<?php
require_once __DIR__ . '/config.php';

function getDbConnection(bool $withDatabase = true): ?PDO
{
    try {
        $dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', DB_HOST, DB_PORT);
        if ($withDatabase) {
            $dsn .= ';dbname=' . DB_NAME;
        }
        return new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        return null;
    }
}

function canConnectToServer(): bool
{
    return getDbConnection(false) !== null;
}

function databaseExists(): bool
{
    $pdo = getDbConnection(false);
    if (!$pdo) return false;
    try {
        $stmt = $pdo->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?");
        $stmt->execute([DB_NAME]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

function getDb(): ?PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = getDbConnection(true);
    }
    return $pdo;
}
