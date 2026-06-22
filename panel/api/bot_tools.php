<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/backup_full.php';
require_once __DIR__ . '/../inc/optimize_ops.php';

require_auth_api();

$action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? ''));

if ($action !== 'backup_full_zip') {
    header('Content-Type: application/json; charset=utf-8');
}

function bt_json(bool $ok, string $msg = '', array $extra = []): void
{
    echo json_encode(array_merge(['ok' => $ok, 'msg' => $msg], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

function bt_bootstrap(): void
{
    global $ManagePanel, $textbotlang, $from_id, $message_id, $setting;
    if (!function_exists('sendmessage')) {
        require_once __DIR__ . '/../../botapi.php';
        require_once __DIR__ . '/../../panels.php';
        require_once __DIR__ . '/../../keyboard.php';
        require_once __DIR__ . '/../../jdf.php';
    }
    if (!isset($ManagePanel) || !is_object($ManagePanel)) {
        $ManagePanel = new ManagePanel();
    }
    $textbotlang = languagechange(__DIR__ . '/../../text.json');
    if (!is_array($textbotlang)) {
        $textbotlang = [];
    }
    vira_apply_textbotlang_compat($textbotlang);
    $from_id = 0;
    $message_id = 0;
    $setting = select('setting', '*');
}

if ($action === 'channels_list') {
    try {
        $rows = db_fetchAll($pdo, 'SELECT link, remark, linkjoin FROM channels ORDER BY remark ASC');
        bt_json(true, '', ['items' => $rows]);
    } catch (Exception $e) {
        bt_json(false, $e->getMessage());
    }
}

if ($action === 'channel_add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $link = trim((string) ($_POST['link'] ?? ''));
    $remark = trim((string) ($_POST['remark'] ?? ''));
    $linkjoin = trim((string) ($_POST['linkjoin'] ?? ''));
    if ($link === '' || $remark === '') {
        bt_json(false, 'لینک و نام الزامی است');
    }
    try {
        db_query($pdo, 'INSERT INTO channels (link, remark, linkjoin) VALUES (?, ?, ?)', [$link, $remark, $linkjoin]);
        bt_json(true, 'کانال اضافه شد');
    } catch (Exception $e) {
        bt_json(false, $e->getMessage());
    }
}

if ($action === 'channel_delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $link = trim((string) ($_POST['link'] ?? ''));
    try {
        db_query($pdo, 'DELETE FROM channels WHERE link = ?', [$link]);
        bt_json(true, 'حذف شد');
    } catch (Exception $e) {
        bt_json(false, $e->getMessage());
    }
}

if ($action === 'admins_list') {
    try {
        $rows = db_fetchAll($pdo, 'SELECT id_admin, username, rule FROM admin ORDER BY username ASC');
        bt_json(true, '', ['items' => $rows]);
    } catch (Exception $e) {
        bt_json(false, $e->getMessage());
    }
}

if ($action === 'admin_add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $id = trim((string) ($_POST['id_admin'] ?? ''));
    $user = trim((string) ($_POST['username'] ?? ''));
    $pass = (string) ($_POST['password'] ?? '');
    $rule = trim((string) ($_POST['rule'] ?? 'administrator'));
    if ($id === '' || $user === '' || $pass === '') {
        bt_json(false, 'آیدی، نام کاربری و رمز الزامی است');
    }
    if (!in_array($rule, ['administrator', 'Seller', 'support'], true)) {
        $rule = 'administrator';
    }
    try {
        $hash = password_hash($pass, PASSWORD_BCRYPT);
        $existing = db_fetch($pdo, 'SELECT id_admin FROM admin WHERE id_admin = ? LIMIT 1', [$id]);
        if ($existing) {
            db_query($pdo, 'UPDATE admin SET username = ?, password = ?, rule = ? WHERE id_admin = ?', [$user, $hash, $rule, $id]);
            bt_json(true, 'ادمین به‌روزرسانی شد');
        }
        db_query($pdo, 'INSERT INTO admin (id_admin, username, password, rule) VALUES (?, ?, ?, ?)', [$id, $user, $hash, $rule]);
        bt_json(true, 'ادمین اضافه شد');
    } catch (Exception $e) {
        bt_json(false, $e->getMessage());
    }
}

if ($action === 'admin_delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $id = trim((string) ($_POST['id_admin'] ?? ''));
    try {
        db_query($pdo, 'DELETE FROM admin WHERE id_admin = ?', [$id]);
        bt_json(true, 'حذف شد');
    } catch (Exception $e) {
        bt_json(false, $e->getMessage());
    }
}

if ($action === 'topics_list') {
    try {
        $rows = db_fetchAll($pdo, 'SELECT report, idreport FROM topicid ORDER BY report ASC');
        $channel = db_fetch($pdo, 'SELECT Channel_Report FROM setting LIMIT 1');
        bt_json(true, '', [
            'items' => $rows,
            'channel_report' => $channel['Channel_Report'] ?? '',
        ]);
    } catch (Exception $e) {
        bt_json(false, $e->getMessage());
    }
}

if ($action === 'topics_save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $channel = trim((string) ($_POST['channel_report'] ?? ''));
    try {
        db_query($pdo, 'UPDATE setting SET Channel_Report = ?', [$channel]);
        $topics = $_POST['topics'] ?? [];
        if (is_string($topics)) {
            $topics = json_decode($topics, true) ?: [];
        }
        foreach ($topics as $report => $idreport) {
            db_query($pdo, 'UPDATE topicid SET idreport = ? WHERE report = ?', [trim((string) $idreport), trim((string) $report)]);
        }
        bt_json(true, 'ذخیره شد');
    } catch (Exception $e) {
        bt_json(false, $e->getMessage());
    }
}

if ($action === 'broadcast_batch' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $message = trim((string) ($_POST['message'] ?? ''));
    $offset = max(0, (int) ($_POST['offset'] ?? 0));
    $batch = min(40, max(5, (int) ($_POST['batch'] ?? 25)));
    if ($message === '') {
        bt_json(false, 'متن پیام خالی است');
    }
    try {
        bt_bootstrap();
        $users = db_fetchAll($pdo, "SELECT id FROM user WHERE User_Status = 'Active' ORDER BY id ASC LIMIT $batch OFFSET $offset");
        $sent = 0;
        foreach ($users as $u) {
            sendmessage($u['id'], $message, null, 'HTML');
            $sent++;
            usleep(50000);
        }
        $total = db_count($pdo, "SELECT COUNT(*) FROM user WHERE User_Status = 'Active'");
        $next = $offset + $batch;
        bt_json(true, '', [
            'sent' => $sent,
            'offset' => $next,
            'total' => $total,
            'done' => $next >= $total,
        ]);
    } catch (Throwable $e) {
        bt_json(false, $e->getMessage());
    }
}

if ($action === 'optimize_stats') {
    try {
        $daysExpire = vira_optimize_sanitize_days($_GET['days_expire'] ?? $_POST['days_expire'] ?? 90, 90);
        $daysUnpaid = vira_optimize_sanitize_days($_GET['days_unpaid'] ?? $_POST['days_unpaid'] ?? 30, 30);
        bt_json(true, '', ['stats' => vira_optimize_preview($pdo, $daysExpire, $daysUnpaid)]);
    } catch (Throwable $e) {
        bt_json(false, $e->getMessage());
    }
}

if ($action === 'optimize_run' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    if (empty($_POST['confirm']) || $_POST['confirm'] !== 'yes') {
        bt_json(false, 'تأیید الزامی است');
    }
    @ini_set('max_execution_time', '300');
    try {
        $botRoot = realpath(__DIR__ . '/../..') ?: (__DIR__ . '/../..');
        $daysExpire = vira_optimize_sanitize_days($_POST['days_expire'] ?? 90, 90);
        $daysUnpaid = vira_optimize_sanitize_days($_POST['days_unpaid'] ?? 30, 30);
        $details = vira_optimize_run($pdo, $botRoot, $daysExpire, $daysUnpaid);
        $msg = sprintf(
            'بهینه‌سازی انجام شد — %d مورد حذف شد (%d سرویس تمام‌شده، %d سفارش بلااستفاده)',
            (int) $details['total_removed'],
            (int) $details['expired_deleted'],
            (int) $details['junk_deleted']
        );
        bt_json(true, $msg, ['details' => $details]);
    } catch (Throwable $e) {
        bt_json(false, $e->getMessage());
    }
}

if ($action === 'cleanup_expired' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    if (empty($_POST['confirm']) || $_POST['confirm'] !== 'yes') {
        bt_json(false, 'تأیید الزامی است');
    }
    try {
        $daysExpire = vira_optimize_sanitize_days($_POST['days_expire'] ?? 90, 90);
        $daysUnpaid = vira_optimize_sanitize_days($_POST['days_unpaid'] ?? 30, 30);
        $cleanup = vira_optimize_cleanup_payments($pdo, $daysExpire, $daysUnpaid);
        $n = (int) $cleanup['payments_deleted'] + (int) $cleanup['unpaid_payments_deleted'];
        bt_json(true, sprintf(
            'پاکسازی انجام شد (%d رکورد — منقضی/رد: %d روز، Unpaid: %d روز)',
            $n,
            $daysExpire,
            $daysUnpaid
        ), ['details' => $cleanup]);
    } catch (Exception $e) {
        bt_json(false, $e->getMessage());
    }
}

if ($action === 'backup_full_zip') {
    csrf_check_get();
    @ini_set('max_execution_time', '600');
    @ini_set('memory_limit', '512M');
    try {
        vira_backup_send_zip_download();
    } catch (Throwable $e) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        bt_json(false, $e->getMessage());
    }
}

if ($action === 'backup_restore' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    if (empty($_POST['confirm']) || $_POST['confirm'] !== 'yes') {
        bt_json(false, 'برای بازیابی باید تأیید کنید');
    }
    if (empty($_FILES['backup_zip']['tmp_name']) || !is_uploaded_file($_FILES['backup_zip']['tmp_name'])) {
        bt_json(false, 'فایل ZIP انتخاب نشده است');
    }
    $ext = strtolower(pathinfo((string) ($_FILES['backup_zip']['name'] ?? ''), PATHINFO_EXTENSION));
    if ($ext !== 'zip') {
        bt_json(false, 'فقط فایل .zip مجاز است');
    }
    @ini_set('max_execution_time', '600');
    @ini_set('memory_limit', '512M');
    try {
        $result = vira_backup_restore_zip($_FILES['backup_zip']['tmp_name']);
        $restartMsg = '';
        if (!empty($_POST['restart_after']) && $_POST['restart_after'] === '1') {
            $rr = vira_bot_restart();
            $restartMsg = $rr['ok'] ? ' — ' . $rr['msg'] : ' — ری‌استارت: ' . $rr['msg'];
        }
        bt_json(true, $result['msg'] . ' (' . ($result['files_restored'] ?? 0) . ' فایل)' . $restartMsg, $result);
    } catch (Throwable $e) {
        bt_json(false, $e->getMessage());
    }
}

if ($action === 'bot_restart' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    try {
        $rr = vira_bot_restart();
        bt_json($rr['ok'], $rr['msg'], ['webhook_url' => $rr['webhook_url'] ?? '']);
    } catch (Throwable $e) {
        bt_json(false, $e->getMessage());
    }
}

if ($action === 'backup_sql') {
    try {
        global $dbname, $dbhost, $usernamedb, $passworddb;
        $tables = ['user', 'invoice', 'product', 'Payment_report', 'PaySetting', 'setting', 'Discount', 'marzban_panel', 'admin', 'channels', 'topicid'];
        $out = "-- Vira backup " . date('c') . "\n";
        foreach ($tables as $t) {
            try {
                $rows = db_fetchAll($pdo, "SELECT * FROM `$t`");
                if ($rows === []) {
                    continue;
                }
                $out .= "\n-- Table $t\n";
                foreach ($rows as $row) {
                    $cols = array_map(fn($c) => "`$c`", array_keys($row));
                    $vals = array_map(function ($v) use ($pdo) {
                        return $v === null ? 'NULL' : $pdo->quote((string) $v);
                    }, array_values($row));
                    $out .= 'INSERT INTO `' . $t . '` (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ");\n";
                }
            } catch (Exception $e) {
                $out .= "-- skip $t: " . $e->getMessage() . "\n";
            }
        }
        bt_json(true, '', ['sql' => $out]);
    } catch (Exception $e) {
        bt_json(false, $e->getMessage());
    }
}

http_response_code(400);
bt_json(false, 'عملیات نامعتبر');
