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
    function db_count(PDO $pdo, string $sql, array $params = []): int
    {
        return (int) db_query($pdo, $sql, $params)->fetchColumn();
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function agent_csrf_token(): string
{
    if (empty($_SESSION['agent_csrf'])) {
        $_SESSION['agent_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['agent_csrf'];
}

function agent_csrf_check(): void
{
    $token = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($token === '' || !hash_equals(agent_csrf_token(), $token)) {
        http_response_code(403);
        die('CSRF validation failed');
    }
}

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

function agent_panel_get_theme(PDO $pdo, string $userId): string
{
    $row = db_fetch($pdo, 'SELECT theme FROM agent_panel_tokens WHERE id_user = ? LIMIT 1', [$userId]);
    $t = $row['theme'] ?? 'viranaut';
    $allowed = ['navy', 'purple', 'emerald', 'sunset', 'slate', 'light', 'linen', 'mint', 'lavender', 'viranaut'];
    return in_array($t, $allowed, true) ? $t : 'viranaut';
}

function agent_panel_set_theme(PDO $pdo, string $userId, string $theme): void
{
    $allowed = ['navy', 'purple', 'emerald', 'sunset', 'slate', 'light', 'linen', 'mint', 'lavender', 'viranaut'];
    if (!in_array($theme, $allowed, true)) {
        return;
    }
    agent_panel_ensure_token($pdo, $userId);
    db_query($pdo, 'UPDATE agent_panel_tokens SET theme = ? WHERE id_user = ?', [$theme, $userId]);
}

function agent_panel_ensure_token(PDO $pdo, string $userId): string
{
    $row = db_fetch($pdo, 'SELECT api_token FROM agent_panel_tokens WHERE id_user = ? LIMIT 1', [$userId]);
    if ($row && !empty($row['api_token'])) {
        return (string) $row['api_token'];
    }
    $token = bin2hex(random_bytes(24));
    db_query($pdo, 'INSERT INTO agent_panel_tokens (id_user, api_token, theme) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE api_token = VALUES(api_token)', [$userId, $token, 'viranaut']);
    return $token;
}

function agent_panel_rotate_token(PDO $pdo, string $userId): string
{
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

function agent_invoice_owned(PDO $pdo, string $agentId, string $username): ?array
{
    return db_fetch($pdo, 'SELECT * FROM invoice WHERE id_user = ? AND username = ? LIMIT 1', [$agentId, $username]);
}

function agent_api_resolve_user(PDO $pdo): ?array
{
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/Bearer\s+(\S+)/i', $auth, $m)) {
        return null;
    }
    $row = db_fetch($pdo, 'SELECT t.*, u.* FROM agent_panel_tokens t INNER JOIN user u ON u.id = t.id_user WHERE t.api_token = ? LIMIT 1', [$m[1]]);
    if (!$row || ($row['agent'] ?? 'f') === 'f') {
        return null;
    }
    return $row;
}

function agent_login_rate_ok(): bool
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0';
    $key = 'agent_login_' . md5($ip);
    $now = time();
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['c' => 0, 't' => $now];
    }
    if ($now - $_SESSION[$key]['t'] > 600) {
        $_SESSION[$key] = ['c' => 0, 't' => $now];
    }
    if ($_SESSION[$key]['c'] >= 10) {
        return false;
    }
    $_SESSION[$key]['c']++;
    return true;
}
