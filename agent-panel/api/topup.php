<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
agent_csrf_check();
$user = agent_panel_require_auth_json($pdo);

$amount = max(0, (int) ($_POST['amount'] ?? 0));
$gateway = trim($_POST['gateway'] ?? 'zarinpal');
$result = agent_create_topup_payment($pdo, $user, $amount, $gateway);
echo json_encode($result, JSON_UNESCAPED_UNICODE);
