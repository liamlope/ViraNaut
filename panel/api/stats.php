<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/campaign_ops.php';

header('Content-Type: application/json; charset=utf-8');
require_auth_api();

$action = trim((string) ($_GET['action'] ?? ''));

function stats_json(bool $ok, string $msg = '', array $extra = []): void
{
    echo json_encode(array_merge(['ok' => $ok, 'msg' => $msg], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'summary') {
    stats_json(true, '', ['summary' => vira_user_stats_summary($pdo)]);
}

if ($action === 'growth') {
    $days = max(7, min(365, (int) ($_GET['days'] ?? 30)));
    stats_json(true, '', ['growth' => vira_user_growth_series($pdo, $days)]);
}

stats_json(false, 'اکشن نامعتبر');
