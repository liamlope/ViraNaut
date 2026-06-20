<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
agent_csrf_check();
$user = agent_panel_require_auth($pdo);
require_once __DIR__ . '/../../panels.php';

$action = $_POST['action'] ?? '';
$username = trim($_POST['username'] ?? '');
if ($username === '') {
    echo json_encode(['ok' => false, 'msg' => 'username required'], JSON_UNESCAPED_UNICODE);
    exit;
}
$inv = agent_invoice_owned($pdo, (string) $user['id'], $username);
if (!$inv) {
    echo json_encode(['ok' => false, 'msg' => 'not found'], JSON_UNESCAPED_UNICODE);
    exit;
}
$ManagePanel = new ManagePanel();
$textbotlang = languagechange();
$result = ['ok' => false, 'msg' => 'unknown'];

if ($action === 'revoke') {
    $r = $ManagePanel->Revoke_sub($inv['Location'], $username);
    $result = ['ok' => ($r['status'] ?? '') === 'successful', 'msg' => $r['msg'] ?? 'done', 'data' => $r];
} elseif ($action === 'renew') {
    $product = select('product', '*', 'name_product', $inv['name_product'], 'select');
    if (!$product || (int) $user['Balance'] < (int) $product['price_product']) {
        $result = ['ok' => false, 'msg' => 'balance or product error'];
    } else {
        $method = $textbotlang['keyboard']['resetVolumeTime'] ?? 'reset';
        $ext = $ManagePanel->extend($method, (int) $product['Volume_constraint'], (int) $product['Service_time'], $username, $product['code_product'], $inv['Location']);
        if (!empty($ext['status'])) {
            update('user', 'Balance', (int) $user['Balance'] - (int) $product['price_product'], 'id', $user['id']);
        }
        $result = ['ok' => !empty($ext['status']), 'msg' => $ext['msg'] ?? 'done'];
    }
} elseif ($action === 'add_volume') {
    $gb = max(1, (int) ($_POST['gb'] ?? 1));
    $ext = $ManagePanel->extra_volume($username, $inv['Location'], $gb);
    $result = ['ok' => !empty($ext['status']), 'msg' => $ext['msg'] ?? 'done'];
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);
