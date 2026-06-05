<?php
require_once __DIR__ . '/../inc/config.php';
header('Content-Type: application/json; charset=utf-8');
require_auth_api();

$days = max(7, min(30, (int) ($_GET['days'] ?? 14)));

$labels = [];
$orders = [];
$revenue = [];

for ($i = $days - 1; $i >= 0; $i--) {
    $ts = strtotime("-{$i} days", strtotime('today'));
    $labels[] = date('m/d', $ts);
    $next = $ts + 86400;
    try {
        $orders[] = db_count($pdo, 'SELECT COUNT(*) FROM invoice WHERE time_sell >= ? AND time_sell < ?', [$ts, $next]);
        $revenue[] = (int) db_query(
            $pdo,
            'SELECT COALESCE(SUM(price_product),0) FROM invoice WHERE time_sell >= ? AND time_sell < ?',
            [$ts, $next]
        )->fetchColumn();
    } catch (Exception $e) {
        $orders[] = 0;
        $revenue[] = 0;
    }
}

$health = ['db' => true, 'bot' => '—', 'cron' => '—', 'pending_pay' => 0];
try {
    $st = db_fetch($pdo, 'SELECT bot_status FROM setting LIMIT 1');
    $health['bot'] = (($st['bot_status'] ?? '') === 'offbot') ? 'خاموش' : 'روشن';
    $health['pending_pay'] = db_count($pdo, "SELECT COUNT(*) FROM Payment_report WHERE payment_Status='waiting'");
    $cronDir = dirname(__DIR__, 2) . '/cronbot';
    $health['cron'] = is_dir($cronDir) ? 'فعال' : 'نامشخص';
} catch (Exception $e) {
    $health['db'] = false;
}

echo json_encode([
    'ok' => true,
    'labels' => $labels,
    'orders' => $orders,
    'revenue' => $revenue,
    'health' => $health,
], JSON_UNESCAPED_UNICODE);
