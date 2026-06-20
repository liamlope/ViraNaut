<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../panels.php';

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Tehran');

if (!function_exists('db_fetch')) {
    function db_fetch(PDO $pdo, string $sql, array $params = []): ?array
    {
        $s = $pdo->prepare($sql);
        $s->execute($params);
        return $s->fetch() ?: null;
    }
}

function agent_api_json(bool $ok, string $msg, array $obj = [], int $code = 200): void
{
    http_response_code($code);
    echo json_encode(['status' => $ok, 'msg' => $msg, 'obj' => $obj], JSON_UNESCAPED_UNICODE);
    exit;
}

$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!preg_match('/Bearer\s+(\S+)/i', $auth, $m)) {
    agent_api_json(false, 'Bearer token required', [], 401);
}
$row = db_fetch($pdo, 'SELECT t.id_user, u.* FROM agent_panel_tokens t INNER JOIN user u ON u.id = t.id_user WHERE t.api_token = ? LIMIT 1', [$m[1]]);
if (!$row || ($row['agent'] ?? 'f') === 'f') {
    agent_api_json(false, 'Invalid agent token', [], 403);
}
$agentId = (string) $row['id_user'];
$input = json_decode(file_get_contents('php://input') ?: '{}', true);
if (!is_array($input)) {
    $input = $_POST;
}
$action = $input['action'] ?? $input['actions'] ?? '';

if ($action === 'dashboard') {
    $stats = db_fetch($pdo, 'SELECT COUNT(*) AS c, COALESCE(SUM(price_product),0) AS s FROM invoice WHERE id_user = ?', [$agentId]);
    agent_api_json(true, 'ok', [
        'balance' => (int) $row['Balance'],
        'sales_count' => (int) ($stats['c'] ?? 0),
        'sales_sum' => (int) ($stats['s'] ?? 0),
    ]);
}

if ($action === 'services') {
    $limit = min(100, max(1, (int) ($input['limit'] ?? 50)));
    $stmt = $pdo->prepare('SELECT username, name_product, price_product, Status, time_sell FROM invoice WHERE id_user = ? ORDER BY time_sell DESC LIMIT ' . $limit);
    $stmt->execute([$agentId]);
    agent_api_json(true, 'ok', ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

$username = trim((string) ($input['username'] ?? ''));
if ($username === '') {
    agent_api_json(false, 'username required');
}
$inv = db_fetch($pdo, 'SELECT * FROM invoice WHERE id_user = ? AND username = ? LIMIT 1', [$agentId, $username]);
if (!$inv) {
    agent_api_json(false, 'service not found', [], 404);
}

$ManagePanel = new ManagePanel();
$textbotlang = languagechange();

if ($action === 'service_detail') {
    $panel = select('marzban_panel', '*', 'name_panel', $inv['Location'], 'select');
    if (!$panel) {
        agent_api_json(false, 'panel not found');
    }
    $du = $ManagePanel->DataUser($panel['name_panel'], $username);
    agent_api_json(true, 'ok', ['invoice' => $inv, 'panel_user' => $du]);
}

if ($action === 'revoke') {
    $rev = $ManagePanel->Revoke_sub($inv['Location'], $username);
    agent_api_json(($rev['status'] ?? '') === 'successful', $rev['msg'] ?? 'done', $rev);
}

if ($action === 'renew') {
    $product = select('product', '*', 'name_product', $inv['name_product'], 'select');
    if (!$product) {
        agent_api_json(false, 'product not found');
    }
    $price = (int) $product['price_product'];
    if ((int) $row['Balance'] < $price) {
        agent_api_json(false, 'insufficient balance');
    }
    $method = $textbotlang['keyboard']['resetVolumeTime'] ?? 'resetVolumeTime';
    $ext = $ManagePanel->extend($method, (int) $product['Volume_constraint'], (int) $product['Service_time'], $username, $product['code_product'], $inv['Location']);
    if (empty($ext['status'])) {
        agent_api_json(false, $ext['msg'] ?? 'extend failed');
    }
    update('user', 'Balance', (int) $row['Balance'] - $price, 'id', $agentId);
    agent_api_json(true, 'renewed', $ext);
}

if ($action === 'add_volume') {
    $gb = max(1, (int) ($input['gb'] ?? 1));
    $ext = $ManagePanel->extra_volume($username, $inv['Location'], $gb);
    agent_api_json(!empty($ext['status']), $ext['msg'] ?? 'done', $ext);
}

agent_api_json(false, 'unknown action');
