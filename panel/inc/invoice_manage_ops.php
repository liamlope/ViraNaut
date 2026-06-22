<?php

require_once __DIR__ . '/user_manage_ops.php';

function im_ensure_jdf(): void
{
    if (!function_exists('jdate') && is_file(__DIR__ . '/../../jdf.php')) {
        require_once __DIR__ . '/../../jdf.php';
    }
}

function im_format_time($raw, bool $withTime = true): string
{
    if ($raw === null || $raw === '' || $raw === '0') {
        return '—';
    }
    im_ensure_jdf();
    if (is_numeric($raw) && (int) $raw > 100000) {
        $ts = (int) $raw;
        if (function_exists('jdate')) {
            return jdate($withTime ? 'Y/m/d H:i:s' : 'Y/m/d', $ts);
        }
        return date($withTime ? 'Y/m/d H:i:s' : 'Y/m/d', $ts);
    }
    return htmlspecialchars(trim((string) $raw));
}

function im_get_invoice(PDO $pdo, string $idInvoice): ?array
{
    $idInvoice = trim($idInvoice);
    if ($idInvoice === '') {
        return null;
    }
    return db_fetch($pdo, 'SELECT * FROM invoice WHERE id_invoice = ?', [$idInvoice]);
}

function im_panel_snapshot(array $invoice): array
{
    if (empty($invoice['username']) || empty($invoice['Service_location'])) {
        return ['ok' => false, 'error' => 'missing_username'];
    }
    if (!function_exists('mirza_ensure_manage_panel')) {
        require_once __DIR__ . '/../../function.php';
    }
    if (!function_exists('formatBytes')) {
        require_once __DIR__ . '/../../function.php';
    }
    $panel = mirza_ensure_manage_panel();
    $data = $panel->DataUser((string) $invoice['Service_location'], (string) $invoice['username']);
    if (!is_array($data) || ($data['status'] ?? '') === 'Unsuccessful') {
        return ['ok' => false, 'error' => 'user_not_in_panel', 'raw' => is_array($data) ? $data : []];
    }
    return ['ok' => true, 'data' => $data];
}

function im_status_label(string $status, array $textbotlang): string
{
    $map = [
        'active' => $textbotlang['users']['stateus']['active'] ?? 'فعال',
        'limited' => $textbotlang['users']['stateus']['limited'] ?? 'محدود',
        'disabled' => $textbotlang['users']['stateus']['disabled'] ?? 'غیرفعال',
        'expired' => $textbotlang['users']['stateus']['expired'] ?? 'منقضی',
        'on_hold' => $textbotlang['users']['stateus']['on_hold'] ?? 'در انتظار اتصال',
        'Unknown' => $textbotlang['users']['stateus']['Unknown'] ?? 'نامشخص',
        'deactivev' => $textbotlang['users']['stateus']['disabled'] ?? 'غیرفعال',
    ];
    return $map[$status] ?? $status;
}

function im_bot_status_map(): array
{
    return [
        'active' => ['tag-ok', 'فعال'],
        'end_of_time' => ['tag-warn', 'اعلان پایان زمان'],
        'end_of_volume' => ['tag-no', 'اعلان پایان حجم'],
        'sendedwarn' => ['tag-warn', 'ارسال تمامی اعلان‌ها'],
        'send_on_hold' => ['tag-plain', 'اعلان متصل نشدن'],
        'unpaid' => ['tag-plain', 'پرداخت نشده'],
        'Unpaid' => ['tag-plain', 'پرداخت نشده'],
        'removebyadmin' => ['tag-no', 'حذف توسط ادمین'],
        'removebyuser' => ['tag-plain', 'حذف توسط کاربر'],
    ];
}

function im_build_panel_fields(array $panelData, array $textbotlang): array
{
    $status = (string) ($panelData['status'] ?? 'Unknown');
    $online = (string) ($panelData['online_at'] ?? '');
    if ($online === 'online') {
        $onlineLabel = function_exists('mirza_online_status_label')
            ? mirza_online_status_label('online', $textbotlang)
            : 'آنلاین';
    } elseif ($online === 'offline') {
        $onlineLabel = function_exists('mirza_online_status_label')
            ? mirza_online_status_label('offline', $textbotlang)
            : 'آفلاین';
    } elseif ($online !== '') {
        $onlineLabel = function_exists('mirza_online_status_label')
            ? mirza_online_status_label($online, $textbotlang)
            : $online;
    } else {
        $onlineLabel = '—';
    }

    im_ensure_jdf();
    $expireTs = isset($panelData['expire']) ? (int) $panelData['expire'] : 0;
    if ($expireTs > 0) {
        $expireLabel = function_exists('jdate') ? jdate('Y/m/d', $expireTs) : date('Y/m/d', $expireTs);
        $daysLeft = max(0, (int) floor(($expireTs - time()) / 86400));
        $expireLabel .= " ({$daysLeft} روز)";
    } else {
        $expireLabel = $textbotlang['users']['stateus']['Unlimited'] ?? 'نامحدود';
    }

    $limit = isset($panelData['data_limit']) ? (float) $panelData['data_limit'] : 0;
    $used = isset($panelData['used_traffic']) ? (float) $panelData['used_traffic'] : 0;
    $totalTraffic = $limit > 0 ? formatBytes($limit) : ($textbotlang['users']['stateus']['Unlimited'] ?? 'نامحدود');
    $usedTraffic = $used > 0 ? formatBytes($used) : ($textbotlang['users']['stateus']['Notconsumed'] ?? 'مصرف نشده');
    $remaining = $limit > 0 ? formatBytes(max(0, $limit - $used)) : 'نامحدود';
    $percent = $limit > 0 ? round(max(0, (($limit - $used) * 100) / $limit), 2) : 100;

    $subUpdated = '—';
    if (!empty($panelData['sub_updated_at'])) {
        try {
            $dt = new DateTime((string) $panelData['sub_updated_at'], new DateTimeZone('UTC'));
            $dt->setTimezone(new DateTimeZone('Asia/Tehran'));
            $subUpdated = function_exists('jdate')
                ? jdate('Y/m/d H:i:s', $dt->getTimestamp())
                : $dt->format('Y/m/d H:i:s');
        } catch (Throwable $e) {
            $subUpdated = (string) $panelData['sub_updated_at'];
        }
    }

    return [
        ['label' => 'وضعیت پنل', 'value' => im_status_label($status, $textbotlang)],
        ['label' => 'حجم کل', 'value' => $totalTraffic],
        ['label' => 'مصرف‌شده', 'value' => $usedTraffic],
        ['label' => 'باقی‌مانده', 'value' => "{$remaining} ({$percent}%)"],
        ['label' => 'انقضا', 'value' => $expireLabel],
        ['label' => 'آخرین اتصال', 'value' => $onlineLabel],
        ['label' => 'آخرین آپدیت ساب', 'value' => $subUpdated],
        ['label' => 'لینک اشتراک', 'value' => (string) ($panelData['subscription_url'] ?? '—'), 'mono' => true],
        ['label' => 'کلاینت', 'value' => (string) ($panelData['sub_last_user_agent'] ?? '—'), 'mono' => true],
    ];
}

function im_service_other_rows(PDO $pdo, string $username): array
{
    if ($username === '') {
        return [];
    }
    try {
        return db_fetchAll(
            $pdo,
            "SELECT * FROM service_other WHERE username = ? AND (status = 'paid' OR status IS NULL) ORDER BY time DESC LIMIT 30",
            [$username]
        );
    } catch (Throwable $e) {
        return [];
    }
}

function im_service_type_label(string $type): string
{
    return [
        'extend_user' => 'تمدید',
        'extend_user_by_admin' => 'تمدید توسط ادمین',
        'extra_user' => 'حجم اضافه',
        'extra_time_user' => 'زمان اضافه',
        'transfertouser' => 'انتقال',
        'extends_not_user' => 'تمدید (بدون یوزر)',
        'change_location' => 'تغییر لوکیشن',
        'gift_time' => 'هدیه زمان',
        'gift_volume' => 'هدیه حجم',
    ][$type] ?? $type;
}

function im_remove_service(PDO $pdo, string $idInvoice, bool $refund, string $adminUser): array
{
    $invoice = im_get_invoice($pdo, $idInvoice);
    if (!$invoice) {
        return ['ok' => false, 'msg' => 'سفارش یافت نشد.'];
    }
    if (($invoice['Status'] ?? '') === 'removebyadmin') {
        return ['ok' => false, 'msg' => 'سرویس از قبل حذف شده است.'];
    }

    if (!function_exists('mirza_ensure_manage_panel')) {
        require_once __DIR__ . '/../../function.php';
    }
    $panel = mirza_ensure_manage_panel();
    $panel->RemoveUser((string) $invoice['Service_location'], (string) $invoice['username']);
    db_query($pdo, "UPDATE invoice SET Status = 'removebyadmin' WHERE id_invoice = ?", [$idInvoice]);

    if ($refund) {
        $price = (int) ($invoice['price_product'] ?? 0);
        if ($price > 0) {
            $user = um_get_user($pdo, (int) $invoice['id_user']);
            if ($user) {
                $newBalance = (int) $user['Balance'] + $price;
                db_query($pdo, 'UPDATE user SET Balance = ? WHERE id = ?', [$newBalance, (int) $invoice['id_user']]);
                um_notify_user(
                    (string) $invoice['id_user'],
                    "💎 کاربر عزیز مبلغ {$price} تومان به موجودی کیف پول تان اضافه گردید."
                );
            }
        }
    }

    um_channel_report(
        "📌 ادمین پنل وب سرویس را حذف کرد.\n"
        . "🪪 ادمین: {$adminUser}\n"
        . "🛒 سفارش: {$idInvoice}\n"
        . "👤 کاربر: {$invoice['id_user']}\n"
        . "↩️ بازگشت وجه: " . ($refund ? 'بله' : 'خیر'),
        'otherservice'
    );
    error_log("Panel admin {$adminUser} removed invoice {$idInvoice} refund=" . ($refund ? '1' : '0'));

    return ['ok' => true, 'msg' => $refund ? 'سرویس حذف و مبلغ به موجودی برگشت.' : 'سرویس از پنل حذف شد.'];
}

function im_delete_db_only(PDO $pdo, string $idInvoice, string $adminUser): array
{
    $invoice = im_get_invoice($pdo, $idInvoice);
    if (!$invoice) {
        return ['ok' => false, 'msg' => 'سفارش یافت نشد.'];
    }
    db_query($pdo, 'DELETE FROM invoice WHERE id_invoice = ?', [$idInvoice]);
    um_channel_report(
        "🗑 ادمین پنل وب سفارش را فقط از دیتابیس ربات حذف کرد.\n"
        . "🪪 ادمین: {$adminUser}\n"
        . "👤 یوزرنیم: {$invoice['username']}",
        'otherservice'
    );
    return ['ok' => true, 'msg' => 'سفارش از دیتابیس ربات حذف شد.', 'redirect' => 'invoice.php'];
}

function im_toggle_status(PDO $pdo, string $idInvoice, string $adminUser): array
{
    $invoice = im_get_invoice($pdo, $idInvoice);
    if (!$invoice) {
        return ['ok' => false, 'msg' => 'سفارش یافت نشد.'];
    }
    if (!function_exists('mirza_ensure_manage_panel')) {
        require_once __DIR__ . '/../../function.php';
    }
    $panel = mirza_ensure_manage_panel();
    $before = $panel->DataUser((string) $invoice['Service_location'], (string) $invoice['username']);
    if (!is_array($before) || ($before['status'] ?? '') === 'Unsuccessful') {
        return ['ok' => false, 'msg' => 'کاربر در پنل یافت نشد.'];
    }
    if (($before['status'] ?? '') === 'on_hold') {
        return ['ok' => false, 'msg' => 'کاربر هنوز به کانفیگ متصل نشده؛ تغییر وضعیت ممکن نیست.'];
    }
    $result = $panel->Change_status((string) $invoice['username'], (string) $invoice['Service_location']);
    if (!is_array($result) || ($result['status'] ?? '') === 'Unsuccessful') {
        return ['ok' => false, 'msg' => 'تغییر وضعیت انجام نشد.'];
    }
    error_log("Panel admin {$adminUser} toggled status for invoice {$idInvoice}");
    return ['ok' => true, 'msg' => 'وضعیت سرویس در پنل تغییر کرد.'];
}

function im_send_subscription(PDO $pdo, string $idInvoice): array
{
    $invoice = im_get_invoice($pdo, $idInvoice);
    if (!$invoice) {
        return ['ok' => false, 'msg' => 'سفارش یافت نشد.'];
    }
    $snap = im_panel_snapshot($invoice);
    if (!$snap['ok']) {
        return ['ok' => false, 'msg' => 'اطلاعات پنل در دسترس نیست.'];
    }
    $url = trim((string) ($snap['data']['subscription_url'] ?? ''));
    if ($url === '') {
        return ['ok' => false, 'msg' => 'لینک اشتراک خالی است.'];
    }
    um_notify_user(
        (string) $invoice['id_user'],
        "🔗 لینک اشتراک سرویس <code>{$invoice['username']}</code>:\n\n<code>{$url}</code>"
    );
    return ['ok' => true, 'msg' => 'لینک اشتراک برای کاربر ارسال شد.'];
}

function im_handle_action(PDO $pdo, string $idInvoice, string $action, string $adminUser): array
{
    switch ($action) {
        case 'remove':
            return im_remove_service($pdo, $idInvoice, false, $adminUser);
        case 'remove_refund':
            return im_remove_service($pdo, $idInvoice, true, $adminUser);
        case 'delete_db':
            return im_delete_db_only($pdo, $idInvoice, $adminUser);
        case 'toggle_status':
            return im_toggle_status($pdo, $idInvoice, $adminUser);
        case 'send_sub':
            return im_send_subscription($pdo, $idInvoice);
        default:
            return ['ok' => false, 'msg' => 'عملیات نامعتبر است.'];
    }
}
