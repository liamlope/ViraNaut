<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../function.php';

if (!function_exists('db_query')) {
    function db_query(PDO $pdo, string $sql, array $params = []): PDOStatement
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    function db_fetch(PDO $pdo, string $sql, array $params = []): ?array
    {
        return db_query($pdo, $sql, $params)->fetch() ?: null;
    }
    function db_fetchAll(PDO $pdo, string $sql, array $params = []): array
    {
        return db_query($pdo, $sql, $params)->fetchAll();
    }
}

session_start();

function site_admin_require(): void
{
    if (empty($_SESSION['site_admin_id'])) {
        header('Location: login.php');
        exit;
    }
}

function site_admin_is_admin(string $pin): bool
{
    $ids = select('admin', 'id_admin', null, null, 'FETCH_COLUMN');
    if (!is_array($ids)) {
        return false;
    }
    return in_array($pin, $ids, true);
}
