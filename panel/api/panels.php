<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/panel_web_helpers.php';

header('Content-Type: application/json; charset=utf-8');
require_auth_api();

if (function_exists('vira_ensure_marzban_panel_columns')) {
    vira_ensure_marzban_panel_columns();
}

$action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? ''));

function panel_json(bool $ok, string $msg = '', array $extra = []): void
{
    echo json_encode(array_merge(['ok' => $ok, 'msg' => $msg], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'types') {
    $out = [];
    foreach (panel_web_type_defs() as $key => $def) {
        $out[] = ['type' => $key, 'label' => $def['label'] ?? $key, 'fields' => $def['fields'] ?? []];
    }
    panel_json(true, '', ['types' => $out]);
}

if ($action === 'get') {
    $name = trim((string) ($_GET['name'] ?? ''));
    if ($name === '') {
        panel_json(false, 'نام پنل الزامی است');
    }
    $row = db_fetch($pdo, 'SELECT * FROM marzban_panel WHERE name_panel = ? LIMIT 1', [$name]);
    if (!$row) {
        panel_json(false, 'پنل یافت نشد');
    }
    unset($row['password_panel']);
    panel_json(true, '', ['panel' => $row]);
}

if ($action === 'test') {
    $name = trim((string) ($_GET['name'] ?? ''));
    if ($name === '') {
        panel_json(false, 'نام پنل الزامی است');
    }
    $row = db_fetch($pdo, 'SELECT * FROM marzban_panel WHERE name_panel = ? LIMIT 1', [$name]);
    if (!$row) {
        panel_json(false, 'پنل یافت نشد');
    }
    $result = panel_web_test_connection($row);
    if (!empty($result['skipped'])) {
        panel_json(true, $result['msg'], ['online' => null, 'skipped' => true]);
    }
    $online = !empty($result['online']);
    panel_json($online, $result['msg'], ['online' => $online]);
}

if ($action === 'dashboard') {
    $name = trim((string) ($_GET['name'] ?? ''));
    if ($name === '') {
        panel_json(false, 'نام پنل الزامی است');
    }
    $row = db_fetch($pdo, 'SELECT * FROM marzban_panel WHERE name_panel = ? LIMIT 1', [$name]);
    if (!$row) {
        panel_json(false, 'پنل یافت نشد');
    }
    $dash = panel_web_dashboard_data($row);
    if (empty($dash['ok'])) {
        panel_json(false, $dash['msg'] ?? 'خطا');
    }
    panel_json((bool) $dash['online'], (string) $dash['msg'], $dash);
}

if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $type = panel_web_normalize_type(trim((string) ($_POST['panel_type'] ?? '')));
    $result = panel_web_insert_panel($pdo, $type, $_POST);
    panel_json(!empty($result['ok']), $result['msg'] ?? '', $result);
}

if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $name = trim((string) ($_POST['name_panel'] ?? ''));
    if ($name === '') {
        panel_json(false, 'نام پنل الزامی است');
    }
    $result = panel_web_update_panel($pdo, $name, $_POST);
    panel_json(!empty($result['ok']), $result['msg'] ?? '');
}

if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $name = trim((string) ($_POST['name_panel'] ?? ''));
    $confirm = trim((string) ($_POST['confirm_name'] ?? ''));
    $result = panel_web_delete_panel($pdo, $name, $confirm);
    panel_json(!empty($result['ok']), $result['msg'] ?? '');
}

http_response_code(400);
panel_json(false, 'عملیات نامعتبر');
