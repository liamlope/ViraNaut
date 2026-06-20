<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
agent_csrf_check();
$user = agent_panel_require_auth_json($pdo);

$action = $_POST['action'] ?? '';
$username = trim($_POST['username'] ?? '');
if ($username === '') {
    echo json_encode(['ok' => false, 'msg' => 'username required'], JSON_UNESCAPED_UNICODE);
    exit;
}

$result = ['ok' => false, 'msg' => 'unknown'];
if ($action === 'revoke') {
    $result = agent_revoke_service($pdo, $user, $username);
} elseif ($action === 'renew') {
    $result = agent_extend_service($pdo, $user, $username);
} elseif ($action === 'add_volume') {
    $result = agent_add_volume($pdo, $user, $username, max(1, (int) ($_POST['gb'] ?? 1)));
} elseif ($action === 'add_time') {
    $result = agent_add_time($pdo, $user, $username, max(1, (int) ($_POST['days'] ?? 1)));
} elseif ($action === 'bulk_renew') {
    $usernames = json_decode($_POST['usernames'] ?? '[]', true);
    $results = [];
    foreach ((array) $usernames as $u) {
        $user = select('user', '*', 'id', $user['id'], 'select');
        $results[] = agent_extend_service($pdo, $user, (string) $u);
    }
    $result = ['ok' => true, 'msg' => 'bulk done', 'results' => $results];
} elseif ($action === 'send_telegram') {
    $detail = agent_service_detail($pdo, $user, $username);
    $sub = $detail['panel_user']['subscription_url'] ?? $detail['panel_user']['link'] ?? '';
    if ($sub) {
        sendmessage((string) $user['id'], "🔗 لینk سرویس <code>{$username}</code>:\n{$sub}", null, 'HTML');
        $result = ['ok' => true, 'msg' => 'sent'];
    } else {
        $result = ['ok' => false, 'msg' => 'no link'];
    }
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);
