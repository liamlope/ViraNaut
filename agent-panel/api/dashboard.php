<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
$user = agent_panel_require_auth_json($pdo);
$days = min(90, max(7, (int) ($_GET['days'] ?? 30)));
$data = agent_chart_data($pdo, (string) $user['id'], $days);
$data['balance'] = (int) $user['Balance'];
echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
