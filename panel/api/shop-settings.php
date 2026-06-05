<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/shop_settings_defs.php';

header('Content-Type: application/json; charset=utf-8');
require_auth_api();

$action = trim((string) ($_GET['action'] ?? ''));

function shop_json(bool $ok, string $msg = '', array $extra = []): void
{
    echo json_encode(array_merge(['ok' => $ok, 'msg' => $msg], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'get') {
    shop_json(true, '', mirza_shop_load_values($pdo));
}

http_response_code(400);
shop_json(false, 'عملیات نامعتبر');
