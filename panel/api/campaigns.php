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
    camp_json(true, '', ['items' => vira_campaign_targets_with_counts($pdo)]);
}

if ($action === 'search_users') {
    $q = trim((string) ($_GET['q'] ?? $_POST['q'] ?? ''));
    camp_json(true, '', ['users' => vira_campaign_search_users($pdo, $q)]);
}

if ($action === 'list') {
    $filter = trim((string) ($_GET['filter'] ?? ''));
    camp_json(true, '', ['items' => vira_campaign_list($pdo, 50, $filter)]);
}

if ($action === 'get') {
    $id = (int) ($_GET['campaign_id'] ?? 0);
    $campaign = vira_campaign_get($pdo, $id);
    camp_json($campaign !== null, $campaign ? '' : 'یافت نشد', ['campaign' => $campaign]);
}

if ($action === 'progress') {
    $id = (int) ($_GET['campaign_id'] ?? 0);
    $campaign = vira_campaign_get($pdo, $id);
    if (!$campaign) {
        camp_json(false, 'یافت نشد');
    }
    camp_json(true, '', [
        'progress' => [
            'campaign_id' => $campaign['id'],
            'status' => $campaign['status'],
            'total' => $campaign['total_recipients'],
            'sent' => $campaign['sent_count'],
            'failed' => $campaign['failed_count'],
            'processed' => $campaign['offset_cursor'],
            'percent' => $campaign['progress'],
            'paused' => $campaign['paused'],
        ],
    ]);
}

if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    try {
        $upload = null;
        if (!empty($_FILES['media']) && is_array($_FILES['media']) && ($_FILES['media']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $upload = $_FILES['media'];
        }
        $campaign = vira_campaign_create($pdo, [
            'text_body' => $_POST['text'] ?? $_POST['text_body'] ?? '',
            'message_type' => $_POST['message_type'] ?? 'text',
            'target_type' => $_POST['target'] ?? $_POST['target_type'] ?? 'all',
            'user_ids' => $_POST['user_ids'] ?? '',
            'buttons_json' => $_POST['buttons_json'] ?? $_POST['reply_markup_json'] ?? '',
            'parse_mode' => $_POST['parse_mode'] ?? 'HTML',
            'disable_web_page_preview' => !empty($_POST['disable_web_page_preview']),
            'pin_after_send' => !empty($_POST['pin_after_send']),
            'auto_send_new_users' => !empty($_POST['auto_send_new_users']),
            'auto_send_delay_minutes' => (int) ($_POST['auto_send_delay_minutes'] ?? 5),
        ], $upload);
        camp_json(true, 'کمپین ایجاد شد', [
            'campaign' => $campaign,
            'campaign_id' => $campaign['id'],
            'total' => $campaign['total_recipients'],
            'status' => $campaign['status'],
        ]);
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
