<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
agent_csrf_check();
$user = agent_panel_require_auth_json($pdo);
db_query($pdo, 'UPDATE agent_panel_tokens SET onboarded = 1 WHERE id_user = ?', [(string) $user['id']]);
echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
