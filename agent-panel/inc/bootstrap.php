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

function agent_panel_require_auth(PDO $pdo): array
{
    if (empty($_SESSION['agent_user_id'])) {
        header('Location: login.php');
        exit;
    }
    $user = select('user', '*', 'id', $_SESSION['agent_user_id'], 'select');
    if (!$user || ($user['agent'] ?? 'f') === 'f') {
        session_destroy();
        header('Location: login.php');
        exit;
    }
    return $user;
}

function agent_panel_ensure_token(PDO $pdo, string $userId): string
{
    $row = db_fetch($pdo, 'SELECT api_token FROM agent_panel_tokens WHERE id_user = ? LIMIT 1', [$userId]);
    if ($row && !empty($row['api_token'])) {
        return (string) $row['api_token'];
    }
    $token = bin2hex(random_bytes(24));
    db_query($pdo, 'INSERT INTO agent_panel_tokens (id_user, api_token) VALUES (?, ?) ON DUPLICATE KEY UPDATE api_token = VALUES(api_token)', [$userId, $token]);
    return $token;
}

function agent_panel_sales_stats(PDO $pdo, string $userId): array
{
    $count = db_fetch($pdo, 'SELECT COUNT(*) AS c, COALESCE(SUM(price_product),0) AS s FROM invoice WHERE id_user = ?', [$userId]);
    return [
        'count' => (int) ($count['c'] ?? 0),
        'sum' => (int) ($count['s'] ?? 0),
    ];
}
