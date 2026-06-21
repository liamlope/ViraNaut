<?php
ini_set('error_log', 'error.log');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jdf.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../Marzban.php';
require_once __DIR__ . '/../panels.php';
require_once __DIR__ . '/../function.php';
if (function_exists('mirza_ensure_user_lang_column')) {
    mirza_ensure_user_lang_column();
}
require_once __DIR__ . '/../keyboard.php';
$ManagePanel = new ManagePanel();
require __DIR__ . '/../vendor/autoload.php';

if (!mirza_card_sms_autoconfirm_enabled()) {
    http_response_code(200);
    exit;
}

$name_post = array_keys($_POST);
if (empty($name_post)) {
    http_response_code(200);
    exit;
}

$name_post = array_map('htmlspecialchars', $name_post);
$name_parts = preg_split('/_+/', $name_post[0], -1);
$secret_key = select('admin', '*', 'password', base64_decode($name_parts[0]), 'count');
if ($secret_key == 0) {
    http_response_code(403);
    exit;
}

$name_bank = $name_parts[1] ?? '';
$valuepost = $_POST["{$name_parts[0]}_$name_bank"] ?? '';
if (!is_string($valuepost) || trim($valuepost) === '') {
    http_response_code(200);
    exit;
}

$result = mirza_card_sms_process_and_approve($valuepost, $name_bank !== '' ? $name_bank : null);
if (!$result['ok']) {
    error_log('[card-sms-http] ' . ($result['reason'] ?? 'fail') . ' ' . json_encode($result, JSON_UNESCAPED_UNICODE));
}

http_response_code(200);
