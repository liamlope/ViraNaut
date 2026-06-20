<?php

function um_ensure_botapi(): void
{
    if (!function_exists('sendmessage')) {
        require_once __DIR__ . '/../../botapi.php';
    }
}

function um_setting(): array
{
    static $cached = null;
    if ($cached === null) {
        $cached = select('setting', '*') ?: [];
    }
    return $cached;
}

function um_topic_thread(string $report): ?int
{
    static $topics = null;
    if ($topics === null) {
        global $pdo;
        $topics = [];
        try {
            foreach (db_fetchAll($pdo, 'SELECT report, idreport FROM topicid') as $row) {
                $topics[(string) $row['report']] = (int) $row['idreport'];
            }
        } catch (Throwable $e) {
            $topics = [];
        }
    }
    $id = (int) ($topics[$report] ?? 0);
    return $id > 0 ? $id : null;
}

function um_notify_user(string $userId, string $text): void
{
    um_ensure_botapi();
    sendmessage($userId, $text, null, 'HTML');
}

function um_channel_report(string $text, string $topicReport = 'paymentreports'): void
{
    $setting = um_setting();
    $channel = trim((string) ($setting['Channel_Report'] ?? ''));
    if ($channel === '') {
        return;
    }
    um_ensure_botapi();
    $payload = [
        'chat_id' => $channel,
        'text' => $text,
        'parse_mode' => 'HTML',
    ];
    $thread = um_topic_thread($topicReport);
    if ($thread !== null) {
        $payload['message_thread_id'] = $thread;
    }
    telegram('sendmessage', $payload);
}

function um_insert_payment_report(PDO $pdo, string $userId, int $amount, string $method): void
{
    $orderId = bin2hex(random_bytes(5));
    $time = date('Y/m/d H:i:s');
    db_query(
        $pdo,
        'INSERT INTO Payment_report (id_user, id_order, time, price, payment_Status, Payment_Method, id_invoice)
         VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$userId, $orderId, $time, (string) $amount, 'paid', $method, null]
    );
}

function um_get_user(PDO $pdo, int $userId): ?array
{
    return db_fetch($pdo, 'SELECT * FROM user WHERE id = ?', [$userId]);
}

function um_validate_amount($amount, int $min = 1000, int $max = 100_000_000): ?int
{
    if (!ctype_digit((string) $amount)) {
        return null;
    }
    $val = (int) $amount;
    if ($val < $min || $val > $max) {
        return null;
    }
    return $val;
}

function um_add_balance(PDO $pdo, int $userId, int $amount, string $adminUser): array
{
    $user = um_get_user($pdo, $userId);
    if (!$user) {
        return ['ok' => false, 'msg' => 'کاربر یافت نشد.'];
    }
    um_insert_payment_report($pdo, (string) $userId, $amount, 'add balance by admin');
    $newBalance = (int) $user['Balance'] + $amount;
    db_query($pdo, 'UPDATE user SET Balance = ? WHERE id = ?', [$newBalance, $userId]);
    um_notify_user((string) $userId, '💎 کاربر عزیز مبلغ ' . number_format($amount) . ' تومان به موجودی کیف پول تان اضافه گردید.');
    um_channel_report(
        "📌 یک ادمین از پنل وب موجودی کاربر را افزایش داده است:\n\n"
        . "🪪 ادمین: {$adminUser}\n"
        . "👤 کاربر: {$userId}\n"
        . "💰 مبلغ: " . number_format($amount) . "\n"
        . "🔰 موجودی پس از افزایش: " . number_format($newBalance)
    );
    error_log("Panel admin {$adminUser} added {$amount} to user {$userId}");
    return ['ok' => true, 'msg' => number_format($amount) . ' تومان به موجودی افزوده شد.'];
}

function um_deduct_balance(PDO $pdo, int $userId, int $amount, string $adminUser): array
{
    $user = um_get_user($pdo, $userId);
    if (!$user) {
        return ['ok' => false, 'msg' => 'کاربر یافت نشد.'];
    }
    um_insert_payment_report($pdo, (string) $userId, $amount, 'low balance by admin');
    $newBalance = (int) $user['Balance'] - $amount;
    db_query($pdo, 'UPDATE user SET Balance = ? WHERE id = ?', [$newBalance, $userId]);
    um_notify_user((string) $userId, '❌ کاربر عزیز مبلغ ' . number_format($amount) . ' تومان از موجودی کیف پول تان کسر گردید.');
    um_channel_report(
        "📌 یک ادمین از پنل وب موجودی کاربر را کم کرده است:\n\n"
        . "🪪 ادمین: {$adminUser}\n"
        . "👤 کاربر: {$userId}\n"
        . "💰 مبلغ: " . number_format($amount) . "\n"
        . "🔰 موجودی پس از کسر: " . number_format($newBalance)
    );
    error_log("Panel admin {$adminUser} deducted {$amount} from user {$userId}");
    return ['ok' => true, 'msg' => number_format($amount) . ' تومان از موجودی کسر شد.'];
}

function um_set_balance(PDO $pdo, int $userId, int $target, string $adminUser): array
{
    $user = um_get_user($pdo, $userId);
    if (!$user) {
        return ['ok' => false, 'msg' => 'کاربر یافت نشد.'];
    }
    $current = (int) $user['Balance'];
    if ($target === $current) {
        return ['ok' => true, 'msg' => 'موجودی تغییری نکرد.'];
    }
    if ($target > $current) {
        return um_add_balance($pdo, $userId, $target - $current, $adminUser);
    }
    return um_deduct_balance($pdo, $userId, $current - $target, $adminUser);
}

function um_zero_balance(PDO $pdo, int $userId, string $adminUser): array
{
    $user = um_get_user($pdo, $userId);
    if (!$user) {
        return ['ok' => false, 'msg' => 'کاربر یافت نشد.'];
    }
    $prev = (int) $user['Balance'];
    if ($prev === 0) {
        return ['ok' => true, 'msg' => 'موجودی از قبل صفر است.'];
    }
    db_query($pdo, 'UPDATE user SET Balance = 0 WHERE id = ?', [$userId]);
    error_log("Panel admin {$adminUser} zeroed balance of user {$userId} (was {$prev})");
    return ['ok' => true, 'msg' => 'موجودی ' . number_format($prev) . ' تومان به صفر تنظیم شد.'];
}

function um_block_user(PDO $pdo, int $userId, string $adminUser): array
{
    $user = um_get_user($pdo, $userId);
    if (!$user) {
        return ['ok' => false, 'msg' => 'کاربر یافت نشد.'];
    }
    if (($user['User_Status'] ?? '') === 'block') {
        return ['ok' => false, 'msg' => 'کاربر از قبل مسدود است.'];
    }
    db_query($pdo, "UPDATE user SET User_Status = 'block' WHERE id = ?", [$userId]);
    um_channel_report("کاربر {$userId} توسط ادمین پنل ({$adminUser}) مسدود شد.", 'otherservice');
    error_log("Panel admin {$adminUser} blocked user {$userId}");
    return ['ok' => true, 'msg' => 'کاربر مسدود شد.'];
}

function um_unblock_user(PDO $pdo, int $userId, string $adminUser): array
{
    $user = um_get_user($pdo, $userId);
    if (!$user) {
        return ['ok' => false, 'msg' => 'کاربر یافت نشد.'];
    }
    if (($user['User_Status'] ?? '') === 'block') {
        db_query($pdo, "UPDATE user SET User_Status = 'Active', description_blocking = ' ' WHERE id = ?", [$userId]);
        um_notify_user((string) $userId, "✳️ حساب کاربری شما از مسدودی خارج شد ✳️\nاکنون میتوانید از ربات استفاده کنید ✔️");
        um_channel_report("کاربر {$userId} توسط ادمین پنل ({$adminUser}) رفع مسدودیت شد.", 'otherservice');
        error_log("Panel admin {$adminUser} unblocked user {$userId}");
        return ['ok' => true, 'msg' => 'مسدودیت کاربر برداشته شد.'];
    }
    return ['ok' => false, 'msg' => 'کاربر مسدود نیست.'];
}

function um_set_verify(PDO $pdo, int $userId, bool $verified): array
{
    db_query($pdo, 'UPDATE user SET verify = ? WHERE id = ?', [$verified ? '1' : '0', $userId]);
    if ($verified) {
        um_notify_user((string) $userId, '💎 کاربر گرامی حساب کاربری شما توسط ادمین با موفقیت احراز هویت گردید و هم اکنون می توانید خرید خود را انجام دهید');
    }
    return ['ok' => true, 'msg' => $verified ? 'کاربر احراز شد.' : 'احراز کاربر لغو شد.'];
}

function um_confirm_number(PDO $pdo, int $userId): array
{
    db_query($pdo, "UPDATE user SET number = 'confrim number by admin' WHERE id = ?", [$userId]);
    return ['ok' => true, 'msg' => 'شماره کاربر تأیید شد.'];
}

function um_confirm_channel(PDO $pdo, int $userId): array
{
    $user = um_get_user($pdo, $userId);
    if (!$user) {
        return ['ok' => false, 'msg' => 'کاربر یافت نشد.'];
    }
    if (($user['joinchannel'] ?? '') === 'active') {
        return ['ok' => false, 'msg' => 'عضویت کانال از قبل تأیید شده است.'];
    }
    db_query($pdo, "UPDATE user SET joinchannel = 'active' WHERE id = ?", [$userId]);
    return ['ok' => true, 'msg' => 'عضویت کانال برای کاربر تأیید شد.'];
}

function um_set_card(PDO $pdo, int $userId, bool $show): array
{
    db_query($pdo, 'UPDATE user SET cardpayment = ? WHERE id = ?', [$show ? '1' : '0', $userId]);
    if ($show) {
        um_notify_user((string) $userId, '💳 کاربر عزیز شماره کارت برای شما فعال شد هم اکنون می توانید خرید خود را انجام دهید.');
    }
    return ['ok' => true, 'msg' => $show ? 'نمایش کارت فعال شد.' : 'نمایش کارت غیرفعال شد.'];
}

function um_set_discount(PDO $pdo, int $userId, int $percent): array
{
    if ($percent < 0 || $percent > 100) {
        return ['ok' => false, 'msg' => 'درصد باید بین ۰ تا ۱۰۰ باشد.'];
    }
    db_query($pdo, 'UPDATE user SET pricediscount = ? WHERE id = ?', [(string) $percent, $userId]);
    return ['ok' => true, 'msg' => 'درصد تخفیف به ' . $percent . '٪ تنظیم شد.'];
}

function um_set_test_limit(PDO $pdo, int $userId, $limit): array
{
    if (!ctype_digit((string) $limit)) {
        return ['ok' => false, 'msg' => 'محدودیت تست باید عدد باشد.'];
    }
    db_query($pdo, 'UPDATE user SET limit_usertest = ? WHERE id = ?', [(string) $limit, $userId]);
    return ['ok' => true, 'msg' => 'محدودیت اکانت تست به ' . $limit . ' تنظیم شد.'];
}

function um_set_role(PDO $pdo, int $userId, string $role): array
{
    if (!in_array($role, ['f', 'n', 'n2', 'all'], true)) {
        return ['ok' => false, 'msg' => 'گروه کاربری نامعتبر است.'];
    }
    db_query($pdo, 'UPDATE user SET agent = ? WHERE id = ?', [$role, $userId]);
    return ['ok' => true, 'msg' => 'گروه کاربری به «' . user_role_label($role) . '» تغییر کرد.'];
}

function um_add_agent(PDO $pdo, int $userId, string $type): array
{
    if (!in_array($type, ['n', 'n2'], true)) {
        return ['ok' => false, 'msg' => 'نوع نمایندگی نامعتبر است.'];
    }
    db_query($pdo, 'UPDATE user SET agent = ?, expire = NULL WHERE id = ?', [$type, $userId]);
    return ['ok' => true, 'msg' => 'کاربر به نماینده (' . $type . ') تبدیل شد.'];
}

function um_remove_agent(PDO $pdo, int $userId): array
{
    db_query($pdo, "UPDATE user SET agent = 'f', pricediscount = '0', expire = NULL WHERE id = ?", [$userId]);
    try {
        db_query($pdo, 'DELETE FROM Requestagent WHERE id = ?', [$userId]);
    } catch (Throwable $e) {
    }
    return ['ok' => true, 'msg' => 'نمایندگی کاربر حذف شد.'];
}

function um_remove_affiliate(PDO $pdo, int $userId): array
{
    $user = um_get_user($pdo, $userId);
    if (!$user || empty($user['affiliates']) || $user['affiliates'] === '0') {
        return ['ok' => false, 'msg' => 'کاربر زیرمجموعه‌ای ندارد.'];
    }
    $parentId = $user['affiliates'];
    $parent = um_get_user($pdo, (int) $parentId);
    if ($parent) {
        $count = max(0, (int) $parent['affiliatescount'] - 1);
        db_query($pdo, 'UPDATE user SET affiliatescount = ? WHERE id = ?', [$count, (int) $parentId]);
    }
    db_query($pdo, "UPDATE user SET affiliates = '0' WHERE id = ?", [$userId]);
    return ['ok' => true, 'msg' => 'کاربر از زیرمجموعه خارج شد.'];
}

function um_remove_all_affiliates(PDO $pdo, int $userId): array
{
    db_query($pdo, "UPDATE user SET affiliates = '0' WHERE affiliates = ?", [(string) $userId]);
    db_query($pdo, 'UPDATE user SET affiliatescount = 0 WHERE id = ?', [$userId]);
    return ['ok' => true, 'msg' => 'زیرمجموعه‌های کاربر حذف شد.'];
}

function um_set_maxbuyagent(PDO $pdo, int $userId, $value): array
{
    if (!ctype_digit((string) $value)) {
        return ['ok' => false, 'msg' => 'مقدار باید عدد باشد.'];
    }
    db_query($pdo, 'UPDATE user SET maxbuyagent = ? WHERE id = ?', [(string) $value, $userId]);
    return ['ok' => true, 'msg' => 'سقف خرید نماینده ذخیره شد.'];
}

function um_send_message(PDO $pdo, int $userId, string $message, bool $allowReply): array
{
    $message = trim($message);
    if ($message === '') {
        return ['ok' => false, 'msg' => 'متن پیام خالی است.'];
    }
    um_ensure_botapi();
    $text = "👤 یک پیام از طرف ادمین ارسال شده است\nمتن پیام:\n\n{$message}";
    if ($allowReply) {
        global $textbotlang;
        if (!is_array($textbotlang ?? null)) {
            $textbotlang = languagechange(__DIR__ . '/../../text.json') ?: [];
            mirza_apply_textbotlang_compat($textbotlang);
        }
        $btnText = $textbotlang['users']['support']['answermessage'] ?? 'پاسخ به پیام';
        $keyboard = json_encode([
            'inline_keyboard' => [[['text' => $btnText, 'callback_data' => 'Responseuser']]],
        ], JSON_UNESCAPED_UNICODE);
        sendmessage((string) $userId, $text, $keyboard, 'HTML');
    } else {
        sendmessage((string) $userId, $text, null, 'HTML');
    }
    return ['ok' => true, 'msg' => 'پیام به کاربر ارسال شد.'];
}

function um_handle_get_action(PDO $pdo, int $userId, string $action, string $adminUser): array
{
    switch ($action) {
        case 'block':
            return um_block_user($pdo, $userId, $adminUser);
        case 'unblock':
            return um_unblock_user($pdo, $userId, $adminUser);
        case 'verify':
            return um_set_verify($pdo, $userId, true);
        case 'unverify':
            return um_set_verify($pdo, $userId, false);
        case 'confirm_number':
            return um_confirm_number($pdo, $userId);
        case 'confirm_channel':
            return um_confirm_channel($pdo, $userId);
        case 'show_card':
            return um_set_card($pdo, $userId, true);
        case 'hide_card':
            return um_set_card($pdo, $userId, false);
        case 'zero_balance':
            return um_zero_balance($pdo, $userId, $adminUser);
        case 'remove_affiliate':
            return um_remove_affiliate($pdo, $userId);
        case 'remove_affiliates':
            return um_remove_all_affiliates($pdo, $userId);
        case 'remove_agent':
            return um_remove_agent($pdo, $userId);
        default:
            return ['ok' => false, 'msg' => 'عملیات نامعتبر است.'];
    }
}

function um_handle_post(PDO $pdo, int $userId, array $post, string $adminUser): array
{
    $action = (string) ($post['action'] ?? '');

    if ($action === 'wallet') {
        $mode = (string) ($post['wallet_mode'] ?? '');
        if ($mode === 'zero') {
            return um_zero_balance($pdo, $userId, $adminUser);
        }
        if ($mode === 'set') {
            if (!ctype_digit((string) ($post['amount'] ?? ''))) {
                return ['ok' => false, 'msg' => 'مبلغ نامعتبر است.'];
            }
            $target = max(0, (int) $post['amount']);
            return um_set_balance($pdo, $userId, $target, $adminUser);
        }
        $amount = um_validate_amount($post['amount'] ?? '');
        if ($amount === null) {
            return ['ok' => false, 'msg' => 'مبلغ باید بین ۱٬۰۰۰ تا ۱۰۰٬۰۰۰٬۰۰۰ تومان باشد.'];
        }
        if ($mode === 'add') {
            return um_add_balance($pdo, $userId, $amount, $adminUser);
        }
        if ($mode === 'deduct') {
            return um_deduct_balance($pdo, $userId, $amount, $adminUser);
        }
        return ['ok' => false, 'msg' => 'نوع عملیات موجودی نامعتبر است.'];
    }

    if ($action === 'set_role') {
        return um_set_role($pdo, $userId, (string) ($post['new_role'] ?? 'f'));
    }
    if ($action === 'set_discount') {
        if (!ctype_digit((string) ($post['discount'] ?? ''))) {
            return ['ok' => false, 'msg' => 'درصد نامعتبر است.'];
        }
        return um_set_discount($pdo, $userId, (int) $post['discount']);
    }
    if ($action === 'set_test_limit') {
        return um_set_test_limit($pdo, $userId, $post['test_limit'] ?? '');
    }
    if ($action === 'add_agent') {
        return um_add_agent($pdo, $userId, (string) ($post['agent_type'] ?? ''));
    }
    if ($action === 'set_maxbuyagent') {
        return um_set_maxbuyagent($pdo, $userId, $post['maxbuyagent'] ?? '');
    }
    if ($action === 'send_message') {
        return um_send_message(
            $pdo,
            $userId,
            (string) ($post['message'] ?? ''),
            !empty($post['allow_reply'])
        );
    }

    return ['ok' => false, 'msg' => 'عملیات نامعتبر است.'];
}
