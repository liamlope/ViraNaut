<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../function.php';
require_once __DIR__ . '/../../inc/agent_ops.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/brand.php';

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

agent_ops_ensure_schema($pdo);

const AGENT_SESSION_TTL = 259200; // 72h
const AGENT_REMEMBER_DAYS = 7;

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => AGENT_SESSION_TTL,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
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
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'msg' => 'CSRF validation failed'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

function agent_csrf_verify(): void
{
    $token = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($token === '' || !hash_equals(agent_csrf_token(), $token)) {
        agent_flash('no', 'CSRF نامعتبر');
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
        exit;
    }
}

function agent_remember_key(): string
{
    global $ApiToken;
    $secret = $ApiToken ?? 'viranaut-agent';
    return hash_hmac('sha256', 'agent_remember', $secret);
}

function agent_set_remember_cookie(string $userId): void
{
    $exp = time() + (AGENT_REMEMBER_DAYS * 86400);
    $payload = $userId . '|' . $exp;
    $sig = hash_hmac('sha256', $payload, agent_remember_key());
    setcookie('agent_remember', base64_encode($payload . '|' . $sig), [
        'expires' => $exp,
        'path' => '/agent-panel/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function agent_clear_remember_cookie(): void
{
    setcookie('agent_remember', '', ['expires' => time() - 3600, 'path' => '/agent-panel/']);
}

function agent_try_remember_login(PDO $pdo): void
{
    if (!empty($_SESSION['agent_user_id']) || empty($_COOKIE['agent_remember'])) {
        return;
    }
    $raw = base64_decode($_COOKIE['agent_remember'], true);
    if (!$raw || substr_count($raw, '|') < 2) {
        return;
    }
    [$uid, $exp, $sig] = explode('|', $raw, 3);
    if ((int) $exp < time()) {
        agent_clear_remember_cookie();
        return;
    }
    $check = hash_hmac('sha256', $uid . '|' . $exp, agent_remember_key());
    if (!hash_equals($check, $sig ?? '')) {
        return;
    }
    $user = select('user', '*', 'id', $uid, 'select');
    if ($user && ($user['agent'] ?? 'f') !== 'f') {
        agent_panel_ensure_token($pdo, (string) $uid);
        $_SESSION['agent_user_id'] = $uid;
        $_SESSION['agent_login_at'] = time();
        $sv = db_fetch($pdo, 'SELECT session_version FROM agent_panel_tokens WHERE id_user = ? LIMIT 1', [(string) $uid]);
        $_SESSION['agent_session_version'] = (int) ($sv['session_version'] ?? 0);
    }
}

agent_try_remember_login($pdo);

function agent_session_valid(PDO $pdo): bool
{
    if (empty($_SESSION['agent_user_id'])) {
        return false;
    }
    $loginAt = (int) ($_SESSION['agent_login_at'] ?? 0);
    if ($loginAt > 0 && (time() - $loginAt) > AGENT_SESSION_TTL) {
        return false;
    }
    $row = db_fetch($pdo, 'SELECT session_version FROM agent_panel_tokens WHERE id_user = ? LIMIT 1', [(string) $_SESSION['agent_user_id']]);
    $sv = (int) ($row['session_version'] ?? 0);
    if ($sv > 0 && (int) ($_SESSION['agent_session_version'] ?? 0) !== $sv) {
        return false;
    }
    return true;
}

function agent_panel_require_auth(PDO $pdo, bool $json = false): array
{
    if (!agent_session_valid($pdo)) {
        session_destroy();
        if ($json) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'msg' => 'session expired', 'code' => 'session'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        header('Location: login.php?err=session');
        exit;
    }
    $user = select('user', '*', 'id', $_SESSION['agent_user_id'], 'select');
    if (!$user || ($user['agent'] ?? 'f') === 'f') {
        session_destroy();
        if ($json) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'msg' => 'not an agent', 'code' => 'not_agent'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        header('Location: login.php?err=not_agent');
        exit;
    }
    if (!empty($user['expire']) && (int) $user['expire'] < time()) {
        update('user', 'agent', 'f', 'id', $user['id']);
        session_destroy();
        if ($json) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'msg' => 'agent expired', 'code' => 'expired'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        header('Location: login.php?err=expired');
        exit;
    }
    return $user;
}

function agent_panel_require_auth_json(PDO $pdo): array
{
    return agent_panel_require_auth($pdo, true);
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

function agent_panel_list_tokens(PDO $pdo, string $userId): array
{
    agent_panel_ensure_token($pdo, $userId);
    return db_fetchAll($pdo, 'SELECT id, label, api_token, rate_limit, last_used_at, created_at FROM agent_panel_tokens_multi WHERE id_user = ? ORDER BY id ASC', [$userId]);
}

function agent_panel_add_token(PDO $pdo, string $userId, string $label): string
{
    $token = bin2hex(random_bytes(24));
    db_query($pdo, 'INSERT INTO agent_panel_tokens_multi (id_user, api_token, label) VALUES (?,?,?)', [$userId, $token, $label ?: 'token']);
    return $token;
}

function agent_invoice_owned(PDO $pdo, string $agentId, string $username): ?array
{
    return db_fetch($pdo, 'SELECT * FROM invoice WHERE id_user = ? AND username = ? LIMIT 1', [$agentId, $username]);
}

function agent_api_resolve_user(PDO $pdo, ?string $token = null): ?array
{
    if ($token === null) {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!preg_match('/Bearer\s+(\S+)/i', $auth, $m)) {
            return null;
        }
        $token = $m[1];
    }
    $row = db_fetch($pdo, 'SELECT t.id_user, t.id AS token_row_id, t.rate_limit, u.* FROM agent_panel_tokens_multi t INNER JOIN user u ON u.id = t.id_user WHERE t.api_token = ? LIMIT 1', [$token]);
    if (!$row) {
        $row = db_fetch($pdo, 'SELECT t.id_user, t.id AS token_row_id, 60 AS rate_limit, u.* FROM agent_panel_tokens t INNER JOIN user u ON u.id = t.id_user WHERE t.api_token = ? LIMIT 1', [$token]);
    }
    if (!$row || ($row['agent'] ?? 'f') === 'f') {
        return null;
    }
    return $row;
}

function agent_api_rate_ok(PDO $pdo, array $row): bool
{
    $limit = max(10, (int) ($row['rate_limit'] ?? 60));
    $tid = (int) ($row['token_row_id'] ?? 0);
    $uid = (string) $row['id_user'];
    $cnt = db_count($pdo, 'SELECT COUNT(*) FROM agent_api_log WHERE id_user = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)', [$uid]);
    return $cnt < $limit;
}

function agent_api_log(PDO $pdo, array $row, string $action): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    db_query($pdo, 'INSERT INTO agent_api_log (token_id, id_user, action, ip) VALUES (?,?,?,?)', [
        $row['token_row_id'] ?? null, $row['id_user'], $action, $ip,
    ]);
    if (!empty($row['token_row_id'])) {
        db_query($pdo, 'UPDATE agent_panel_tokens_multi SET last_used_at = NOW() WHERE id = ?', [$row['token_row_id']]);
    }
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

function agent_log_login(PDO $pdo, string $userId): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512);
    db_query($pdo, 'INSERT INTO agent_login_log (id_user, ip, user_agent) VALUES (?,?,?)', [$userId, $ip, $ua]);
}

function agent_invalidate_all_sessions(PDO $pdo, string $userId): void
{
    db_query($pdo, 'UPDATE agent_panel_tokens SET session_version = session_version + 1 WHERE id_user = ?', [$userId]);
}

function agent_unread_notifications(PDO $pdo, string $userId): int
{
    return db_count($pdo, 'SELECT COUNT(*) FROM agent_notifications WHERE id_user = ? AND read_at IS NULL', [$userId]);
}

function agent_expiry_warning(array $user): ?string
{
    if (empty($user['expire'])) {
        return null;
    }
    $left = (int) $user['expire'] - time();
    if ($left > 0 && $left < 7 * 86400) {
        return 'نمایندگی شما تا ' . date('Y/m/d', (int) $user['expire']) . ' منقضی می‌شود.';
    }
    return null;
}

function agent_send_2fa_code(PDO $pdo, string $userId): bool
{
    $code = (string) random_int(100000, 999999);
    $exp = time() + 300;
    db_query($pdo, 'REPLACE INTO agent_2fa_pending (id_user, code, expires_at) VALUES (?,?,?)', [$userId, $code, $exp]);
    $msg = "🔐 کد ورود پنل نمایندگی: <code>{$code}</code>\n(۵ دقیقه اعتبار)";
    sendmessage($userId, $msg, null, 'HTML');
    return true;
}

function agent_verify_2fa(PDO $pdo, string $userId, string $code): bool
{
    $row = db_fetch($pdo, 'SELECT * FROM agent_2fa_pending WHERE id_user = ? LIMIT 1', [$userId]);
    if (!$row || (int) $row['expires_at'] < time() || !hash_equals($row['code'], $code)) {
        return false;
    }
    db_query($pdo, 'DELETE FROM agent_2fa_pending WHERE id_user = ?', [$userId]);
    return true;
}

function agent_is_2fa_enabled(PDO $pdo, string $userId): bool
{
    $row = db_fetch($pdo, 'SELECT twofa_enabled FROM agent_panel_tokens WHERE id_user = ? LIMIT 1', [$userId]);
    return !empty($row['twofa_enabled']);
}
