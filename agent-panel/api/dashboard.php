<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
$user = agent_panel_require_auth($pdo);
$agentId = (string) $user['id'];
$days = max(7, min(30, (int) ($_GET['days'] ?? 14)));
$labels = [];
$orders = [];
$revenue = [];
for ($i = $days - 1; $i >= 0; $i--) {
    $ts = strtotime("-{$i} days", strtotime('today'));
    $labels[] = date('m/d', $ts);
    $next = $ts + 86400;
    $orders[] = db_count($pdo, 'SELECT COUNT(*) FROM invoice WHERE id_user = ? AND time_sell >= ? AND time_sell < ?', [$agentId, $ts, $next]);
    $revenue[] = (int) db_query($pdo, 'SELECT COALESCE(SUM(price_product),0) FROM invoice WHERE id_user = ? AND time_sell >= ? AND time_sell < ?', [$agentId, $ts, $next])->fetchColumn();
}
echo json_encode([
    'ok' => true,
    'labels' => $labels,
    'orders' => $orders,
    'revenue' => $revenue,
    'balance' => (int) $user['Balance'],
], JSON_UNESCAPED_UNICODE);
