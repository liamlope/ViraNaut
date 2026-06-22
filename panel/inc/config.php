<?php

// config.php already loads function.php — never require it again (fatal redeclare).
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/brand.php';
require_once __DIR__ . '/vira_compat.php';

if (!isset($pdo)) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    die("Database connection failed. Check config.php and MySQL.\n");
}

// Panel UI language strings (loaded from project text.json)
$panelTextJson = __DIR__ . '/../../text.json';
if (!is_readable($panelTextJson)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    die("text.json not found or not readable at: {$panelTextJson}\n");
}
try {
    $textbotlang = languagechange($panelTextJson);
} catch (Throwable $e) {
    error_log('panel/inc/config.php languagechange: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    die("Panel language load failed: " . $e->getMessage() . "\n");
}
if (!is_array($textbotlang)) {
    $textbotlang = ['panel' => []];
    vira_apply_textbotlang_compat($textbotlang);
}

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
function require_auth_api(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    global $pdo;
    if (empty($_SESSION['admin_user'])) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'msg' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    try {
        $admin = db_fetch($pdo, 'SELECT id_admin FROM admin WHERE username = ?', [$_SESSION['admin_user']]);
        if (!$admin) {
            session_destroy();
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'msg' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    } catch (Exception $e) {
        http_response_code(503);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'msg' => 'Database error'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

function require_auth(): void
{
    if (session_status() === PHP_SESSION_NONE)
        session_start();
    global $pdo;
    if (empty($_SESSION['admin_user'])) {
        header('Location: login.php');
        exit;
    }
    try {
        $admin = db_fetch($pdo, "SELECT id_admin FROM admin WHERE username = ?", [$_SESSION['admin_user']]);
        if (!$admin) {
            session_destroy();
            header('Location: login.php');
            exit;
        }
    } catch (Exception $e) {
        session_destroy();
        header('Location: login.php');
        exit;
    }
}

function csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE)
        session_start();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check_post(): void
{
    global $textbotlang;
    $token = $_POST['_csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(403);
        die($textbotlang['panel']['configInvalidRequest']);
    }
}

function csrf_check_get(): void
{
    global $textbotlang;
    $token = $_GET['_csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(403);
        die($textbotlang['panel']['configInvalidRequest']);
    }
}

function flash(string $key, string $msg): void
{
    if (session_status() === PHP_SESSION_NONE)
        session_start();
    $_SESSION["flash_{$key}"] = $msg;
}

function get_flash(string $key): ?string
{
    if (session_status() === PHP_SESSION_NONE)
        session_start();
    $msg = $_SESSION["flash_{$key}"] ?? null;
    unset($_SESSION["flash_{$key}"]);
    return $msg;
}

function trunc(string $str, int $max = 30): string
{
    return mb_strlen($str, 'UTF-8') > $max
        ? mb_substr($str, 0, $max, 'UTF-8') . '…'
        : $str;
}

/** مسیر استاتیک پنل با bust کش (filemtime). */
function panel_asset(string $rel): string
{
    $rel = ltrim(str_replace('\\', '/', $rel), '/');
    $full = __DIR__ . '/../' . $rel;
    $v = is_file($full) ? (string) filemtime($full) : (string) time();
    return htmlspecialchars($rel . '?v=' . $v, ENT_QUOTES, 'UTF-8');
}

function safe_date($ts, string $fmt = 'Y/m/d'): string
{
    if (!$ts)
        return '—';
    if (!is_numeric($ts))
        return htmlspecialchars((string) $ts);
    return date($fmt, (int) $ts);
}

/** Payment_report.time — ربات رشته Y/m/d H:i:s ذخیره می‌کند، نه unix. */
function panel_format_payment_time($raw, bool $withTime = true): string
{
    if ($raw === null || $raw === '' || $raw === '0') {
        return '—';
    }
    if (is_numeric($raw) && (int) $raw > 100000) {
        return date($withTime ? 'Y/m/d H:i' : 'Y/m/d', (int) $raw);
    }
    return htmlspecialchars(trim((string) $raw));
}

function panel_today_start_string(): string
{
    return date('Y/m/d') . ' 00:00:00';
}
function check_login_rate(string $ip): bool
{
    $file = sys_get_temp_dir() . '/panel_login_' . md5($ip);
    $data = @json_decode(@file_get_contents($file) ?: '{}', true) ?: [];
    $now = time();
    $data = array_filter($data, fn($t) => ($now - $t) < 900);
    if (count($data) >= 10)
        return false;
    $data[] = $now;
    @file_put_contents($file, json_encode(array_values($data)), LOCK_EX);
    return true;
}

function clear_login_rate(string $ip): void
{
    @unlink(sys_get_temp_dir() . '/panel_login_' . md5($ip));
}

function user_role_label(string $agent): string
{
    global $textbotlang;
    return match ($agent) {
        'n' => $textbotlang['panel']['configRoleN'],
        'n2' => $textbotlang['panel']['configRoleN2'],
        'all' => $textbotlang['panel']['configRoleAll'],
        default => $textbotlang['panel']['configRoleDefault'],
    };
}

function user_role_tag(string $agent): string
{
    return match ($agent) {
        'f' => 'tag-info',
        'n' => 'tag-info',
        'n2' => 'tag-warn',
        'all' => 'tag-ok',
        default => 'tag-plain',
    };
}
