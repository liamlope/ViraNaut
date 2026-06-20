<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
header('Content-Type: text/csv; charset=utf-8');
$user = agent_panel_require_auth($pdo);
$type = $_GET['type'] ?? 'services';
$agentId = (string) $user['id'];
header('Content-Disposition: attachment; filename="agent-export-' . $type . '.csv"');
$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
if ($type === 'products') {
    fputcsv($out, ['product', 'count', 'total']);
    foreach (agent_top_products($pdo, $agentId, 100) as $r) {
        fputcsv($out, [$r['name_product'], $r['cnt'], $r['total']]);
    }
} else {
    fputcsv($out, ['username', 'product', 'price', 'status', 'time']);
    foreach (agent_services_query($pdo, $agentId, [], 5000, 0) as $r) {
        fputcsv($out, [$r['username'], $r['name_product'], $r['price_product'], $r['Status'], $r['time_sell']]);
    }
}
fclose($out);
