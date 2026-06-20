<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
$user = agent_panel_require_auth_json($pdo);
$panel = $_GET['panel'] ?? '';
$items = $panel ? agent_product_list($pdo, $user, $panel) : [];
echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE);
