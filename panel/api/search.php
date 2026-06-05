<?php
require_once __DIR__ . '/../inc/config.php';
header('Content-Type: application/json; charset=utf-8');
require_auth_api();

$q = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($q) < 2) {
    echo json_encode(['ok' => true, 'items' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$like = '%' . $q . '%';
$items = [];

try {
    foreach (db_fetchAll($pdo, 'SELECT id, namecustom, username, Balance FROM user WHERE id LIKE ? OR namecustom LIKE ? OR username LIKE ? LIMIT 6', [$like, $like, $like]) as $u) {
        $items[] = ['type' => 'user', 'label' => ($u['namecustom'] ?? '') . ' (' . $u['id'] . ')', 'url' => 'user.php?id=' . urlencode($u['id'])];
    }
    foreach (db_fetchAll($pdo, 'SELECT id_invoice, id_user, name_product, price_product FROM invoice WHERE id_user LIKE ? OR name_product LIKE ? ORDER BY time_sell DESC LIMIT 6', [$like, $like]) as $inv) {
        $items[] = ['type' => 'invoice', 'label' => 'فاکتور ' . $inv['id_invoice'] . ' — ' . ($inv['name_product'] ?? ''), 'url' => 'invoice.php'];
    }
    foreach (db_fetchAll($pdo, 'SELECT id_order, id_user, price, payment_Status FROM Payment_report WHERE id_user LIKE ? OR id_order LIKE ? ORDER BY time DESC LIMIT 6', [$like, $like]) as $p) {
        $items[] = ['type' => 'payment', 'label' => 'تراکنش ' . trunc((string) $p['id_order'], 16) . ' — ' . number_format((int) $p['price']), 'url' => 'finance.php'];
    }
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE);
