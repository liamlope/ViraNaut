<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
$user = agent_panel_require_auth_json($pdo);
$filters = [
    'status' => $_GET['status'] ?? '',
    'location' => $_GET['location'] ?? '',
    'q' => $_GET['q'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
];
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = min(100, max(10, (int) ($_GET['limit'] ?? 50)));
$offset = ($page - 1) * $limit;
$items = agent_services_query($pdo, (string) $user['id'], $filters, $limit, $offset);
echo json_encode(['ok' => true, 'items' => $items, 'page' => $page], JSON_UNESCAPED_UNICODE);
