<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/campaign_ops.php';

header('Content-Type: application/json; charset=utf-8');
require_auth_api();

vira_campaign_ensure_tables($pdo);

$action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? ''));

function camp_json(bool $ok, string $msg = '', array $extra = []): void
{
    echo json_encode(array_merge(['ok' => $ok, 'msg' => $msg], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'targets') {
    camp_json(true, '', ['targets' => vira_campaign_target_defs()]);
}

if ($action === 'list') {
    camp_json(true, '', ['items' => vira_campaign_list($pdo)]);
}

if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    try {
        $campaign = vira_campaign_create($pdo, [
            'text_body' => $_POST['text_body'] ?? '',
            'target_type' => $_POST['target_type'] ?? 'all_active',
            'reply_markup_json' => $_POST['reply_markup_json'] ?? '',
            'pin_after_send' => !empty($_POST['pin_after_send']),
        ]);
        camp_json(true, 'کمپین ایجاد شد', ['campaign' => $campaign]);
    } catch (Throwable $e) {
        camp_json(false, $e->getMessage());
    }
}

if ($action === 'send_batch' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $id = (int) ($_POST['campaign_id'] ?? 0);
    $batch = (int) ($_POST['batch'] ?? 25);
    if ($id <= 0) {
        camp_json(false, 'شناسه کمپین نامعتبر است');
    }
    try {
        if (!isset($ManagePanel) || !is_object($ManagePanel)) {
            require_once __DIR__ . '/../../panels.php';
            $ManagePanel = new ManagePanel();
        }
        $result = vira_campaign_send_batch($pdo, $id, $batch);
        camp_json(true, '', ['campaign' => $result]);
    } catch (Throwable $e) {
        camp_json(false, $e->getMessage());
    }
}

if ($action === 'pause' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $id = (int) ($_POST['campaign_id'] ?? 0);
    $campaign = vira_campaign_set_paused($pdo, $id, true);
    camp_json($campaign !== null, $campaign ? 'متوقف شد' : 'یافت نشد', ['campaign' => $campaign]);
}

if ($action === 'resume' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $id = (int) ($_POST['campaign_id'] ?? 0);
    $campaign = vira_campaign_set_paused($pdo, $id, false);
    camp_json($campaign !== null, $campaign ? 'ادامه یافت' : 'یافت نشد', ['campaign' => $campaign]);
}

if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $id = (int) ($_POST['campaign_id'] ?? 0);
    if ($id <= 0) {
        camp_json(false, 'شناسه نامعتبر');
    }
    vira_campaign_delete($pdo, $id);
    camp_json(true, 'حذف شد');
}

camp_json(false, 'اکشن نامعتبر');
