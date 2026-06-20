<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
agent_csrf_check();
$user = agent_panel_require_auth_json($pdo);
require_once __DIR__ . '/../../panels.php';

$action = $_POST['action'] ?? '';
$username = trim($_POST['username'] ?? '');

if ($action === 'preview') {
    $type = $_POST['type'] ?? 'buy';
    $price = 0;
    if ($type === 'buy') {
        $product = db_fetch($pdo, 'SELECT * FROM product WHERE code_product = ? LIMIT 1', [$_POST['product_code'] ?? '']);
        $price = (int) ($product['price_product'] ?? 0);
    } elseif ($type === 'custom') {
        $panel = select('marzban_panel', '*', 'name_panel', $_POST['panel'] ?? '', 'select');
        $cp = $panel ? agent_custom_pricing($panel, $user['agent'] ?? 'f') : ['price_volume' => 0, 'price_time' => 0];
        $price = ((int) ($_POST['volume'] ?? 0)) * $cp['price_volume'] + ((int) ($_POST['days'] ?? 0)) * $cp['price_time'];
    } elseif ($type === 'bulk') {
        $product = db_fetch($pdo, 'SELECT * FROM product WHERE code_product = ? LIMIT 1', [$_POST['product_code'] ?? '']);
        $cnt = max(1, min(15, (int) ($_POST['count'] ?? 1)));
        $price = (int) ($product['price_product'] ?? 0) * $cnt;
    } elseif ($type === 'extend') {
        $inv = agent_invoice_owned($pdo, (string) $user['id'], $username);
        $product = $inv ? select('product', '*', 'name_product', $inv['name_product'], 'select') : null;
        $price = (int) ($product['price_product'] ?? 0);
    } elseif ($type === 'volume') {
        $price = agent_extra_volume_price($user['agent'] ?? 'f') * max(1, (int) ($_POST['gb'] ?? 1));
    } elseif ($type === 'time') {
        $price = agent_extra_time_price($user['agent'] ?? 'f') * max(1, (int) ($_POST['days'] ?? 1));
    }
    $preview = agent_checkout_preview($user, $price);
    echo json_encode(['ok' => true, 'preview' => $preview], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'buy') {
    $r = agent_buy_service($pdo, $user, $_POST['panel'] ?? '', $_POST['product_code'] ?? '', trim($_POST['custom_username'] ?? '') ?: null);
    echo json_encode($r, JSON_UNESCAPED_UNICODE);
    exit;
}
if ($action === 'buy_custom') {
    $r = agent_buy_custom($pdo, $user, $_POST['panel'] ?? '', (int) ($_POST['volume'] ?? 0), (int) ($_POST['days'] ?? 0), trim($_POST['custom_username'] ?? '') ?: null);
    echo json_encode($r, JSON_UNESCAPED_UNICODE);
    exit;
}
if ($action === 'buy_bulk') {
    $r = agent_buy_bulk($pdo, $user, $_POST['panel'] ?? '', $_POST['product_code'] ?? '', (int) ($_POST['count'] ?? 1));
    echo json_encode($r, JSON_UNESCAPED_UNICODE);
    exit;
}
if ($action === 'buy_test') {
    $r = agent_buy_test($pdo, $user, $_POST['panel'] ?? '');
    echo json_encode($r, JSON_UNESCAPED_UNICODE);
    exit;
}
if ($action === 'gateway_intent') {
    $amount = max(1000, (int) ($_POST['amount'] ?? 0));
    $ctx = $_POST['context'] ?? 'agent_topup';
    agent_store_gateway_intent($pdo, (string) $user['id'], $amount, $ctx);
    $gates = agent_payment_gateways();
    echo json_encode(['ok' => true, 'gateways' => $gates, 'amount' => $amount], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => false, 'msg' => 'unknown action'], JSON_UNESCAPED_UNICODE);
