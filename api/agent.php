<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../inc/agent_ops.php';

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Tehran');
agent_ops_ensure_schema($pdo);

function agent_api_json(bool $ok, string $msg, array $obj = [], int $code = 200): void
{
    http_response_code($code);
    echo json_encode(['status' => $ok, 'msg' => $msg, 'obj' => $obj], JSON_UNESCAPED_UNICODE);
    exit;
}

$row = agent_api_resolve_user($pdo);
if (!$row) {
    agent_api_json(false, 'Bearer token required', [], 401);
}
if (!agent_api_rate_ok($pdo, $row)) {
    agent_api_json(false, 'rate limit exceeded', [], 429);
}

$agentId = (string) $row['id_user'];
$user = select('user', '*', 'id', $agentId, 'select');
if (!$user || ($user['agent'] ?? 'f') === 'f') {
    agent_api_json(false, 'Invalid agent token', [], 403);
}

$input = json_decode(file_get_contents('php://input') ?: '{}', true);
if (!is_array($input)) {
    $input = $_POST;
}
$action = $input['action'] ?? $input['actions'] ?? '';
agent_api_log($pdo, $row, (string) $action);

if ($action === 'dashboard') {
    agent_api_json(true, 'ok', agent_dashboard_metrics($pdo, $user));
}

if ($action === 'services') {
    $limit = min(100, max(1, (int) ($input['limit'] ?? 50)));
    $items = agent_services_query($pdo, $agentId, ['q' => $input['q'] ?? ''], $limit, 0);
    agent_api_json(true, 'ok', ['items' => $items]);
}

if ($action === 'affiliates') {
    agent_api_json(true, 'ok', agent_affiliate_stats($pdo, $agentId));
}

if ($action === 'transactions') {
    $payments = db_fetchAll($pdo, 'SELECT id_order, price, payment_Status, Payment_Method, time FROM Payment_report WHERE id_user = ? ORDER BY time DESC LIMIT 100', [$agentId]);
    agent_api_json(true, 'ok', ['items' => $payments]);
}

if ($action === 'tariff') {
    agent_api_json(true, 'ok', agent_tariff_table($pdo, $user));
}

if ($action === 'buy') {
    $r = agent_buy_service($pdo, $user, (string) ($input['panel'] ?? ''), (string) ($input['product_code'] ?? ''), isset($input['custom_username']) ? (string) $input['custom_username'] : null);
    agent_api_json(!empty($r['ok']), $r['msg'] ?? 'done', $r);
}

if ($action === 'buy_custom') {
    $r = agent_buy_custom($pdo, $user, (string) ($input['panel'] ?? ''), (int) ($input['volume'] ?? 0), (int) ($input['days'] ?? 0));
    agent_api_json(!empty($r['ok']), $r['msg'] ?? 'done', $r);
}

if ($action === 'buy_bulk') {
    $r = agent_buy_bulk($pdo, $user, (string) ($input['panel'] ?? ''), (string) ($input['product_code'] ?? ''), (int) ($input['count'] ?? 1));
    agent_api_json(!empty($r['ok']), $r['msg'] ?? 'done', $r);
}

if ($action === 'test_account') {
    $r = agent_buy_test($pdo, $user, (string) ($input['panel'] ?? ''));
    agent_api_json(!empty($r['ok']), $r['msg'] ?? 'done', $r);
}

$noUsername = ['dashboard', 'services', 'affiliates', 'transactions', 'tariff', 'buy', 'buy_custom', 'buy_bulk', 'test_account', 'panels', 'products'];
$username = trim((string) ($input['username'] ?? ''));
if ($username === '' && !in_array($action, $noUsername, true)) {
    agent_api_json(false, 'username required');
}

if ($action === 'panels') {
    agent_api_json(true, 'ok', ['items' => agent_panel_list($pdo, $user)]);
}

if ($action === 'products') {
    $panel = (string) ($input['panel'] ?? '');
    agent_api_json(true, 'ok', ['items' => agent_product_list($pdo, $user, $panel)]);
}

$inv = null;
if ($username !== '') {
    $inv = db_fetch($pdo, 'SELECT * FROM invoice WHERE id_user = ? AND username = ? LIMIT 1', [$agentId, $username]);
}
if ($username !== '' && !$inv && !in_array($action, $noUsername, true)) {
    agent_api_json(false, 'service not found', [], 404);
}

if ($action === 'service_detail') {
    $d = agent_service_detail($pdo, $user, $username);
    agent_api_json($d['ok'], $d['msg'] ?? 'ok', $d);
}

if ($action === 'revoke') {
    $r = agent_revoke_service($pdo, $user, $username);
    agent_api_json(!empty($r['ok']), $r['msg'] ?? 'done', $r);
}

if ($action === 'renew') {
    $r = agent_extend_service($pdo, $user, $username);
    agent_api_json(!empty($r['ok']), $r['msg'] ?? 'done', $r);
}

if ($action === 'add_volume') {
    $gb = max(1, (int) ($input['gb'] ?? 1));
    $r = agent_add_volume($pdo, $user, $username, $gb);
    agent_api_json(!empty($r['ok']), $r['msg'] ?? 'done', $r);
}

if ($action === 'add_time') {
    $days = max(1, (int) ($input['days'] ?? 1));
    $r = agent_add_time($pdo, $user, $username, $days);
    agent_api_json(!empty($r['ok']), $r['msg'] ?? 'done', $r);
}

agent_api_json(false, 'unknown action');
