<?php
declare(strict_types=1);
ini_set('error_log', 'error_log');
date_default_timezone_set('Asia/Tehran');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../inc/agent_ops.php';
agent_ops_ensure_schema($pdo);

$agents = db_fetchAll($pdo, "SELECT id, Balance, agent FROM user WHERE agent IN ('n','n2')");
foreach ($agents as $ag) {
    $id = (string) $ag['id'];
    if ((int) $ag['Balance'] < 50000) {
        agent_push_notification($pdo, $id, 'low_balance', ['balance' => (int) $ag['Balance']]);
        $row = db_fetch($pdo, 'SELECT notify_telegram FROM agent_panel_tokens WHERE id_user = ? LIMIT 1', [$id]);
        if (!empty($row['notify_telegram'])) {
            sendmessage($id, '⚠️ موجودی پنل نمایندگی کم است: ' . number_format((int) $ag['Balance']) . ' تومان', null, 'HTML');
        }
    }
    $soon = db_fetchAll($pdo, "SELECT username, time_sell FROM invoice WHERE id_user = ? AND Status = 'active' ORDER BY time_sell DESC LIMIT 50", [$id]);
    foreach ($soon as $inv) {
        agent_notify_webhook($pdo, $id, 'expire_soon', ['username' => $inv['username']]);
    }
}
