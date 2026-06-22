<?php

if (!defined('VIRA_BOT_BOOTSTRAP') && !defined('VIRA_BOT_BOOTSTRAP')) {
    return;
}

/**
 * Parse admin user lookup: /id, /@user, /user, t.me links, legacy /user /id.
 */
function vira_admin_parse_user_lookup(string $text): ?string
{
    $text = trim($text);
    if ($text === '') {
        return null;
    }

    if (preg_match('#(?:https?://)?(?:www\.)?t\.me/([a-zA-Z0-9_]{3,32})#iu', $text, $m)) {
        return $m[1];
    }

    if (preg_match('#^/(?:user|id)\s+(\S+)#iu', $text, $m)) {
        return ltrim($m[1], '@');
    }

    if (preg_match('#^/(panel|savemoji|start|help|buy|wallet|services|admin)(?:@\w+)?(?:\s|$)#iu', $text)) {
        return null;
    }

    if (preg_match('#^/@?([a-zA-Z][a-zA-Z0-9_]{2,31})(?:@\w+)?$#u', $text, $m)) {
        return $m[1];
    }

    if (preg_match('#^/(\d{5,20})(?:@\w+)?$#u', $text, $m)) {
        return $m[1];
    }

    return null;
}

function vira_admin_resolve_user_id(string $lookup, $pdo = null): ?string
{
    $lookup = trim($lookup);
    if ($lookup === '') {
        return null;
    }

    if (ctype_digit($lookup)) {
        $row = select('user', 'id', 'id', $lookup, 'select');
        return $row ? (string) $row['id'] : null;
    }

    $uname = ltrim($lookup, '@');
    $row = select('user', 'id', 'username', $uname, 'select');
    if ($row) {
        return (string) $row['id'];
    }
    if ($pdo instanceof PDO) {
        $row = $pdo->prepare('SELECT id FROM user WHERE LOWER(username) = LOWER(?) LIMIT 1');
        $row->execute([$uname]);
        $found = $row->fetch(PDO::FETCH_ASSOC);
        return $found ? (string) $found['id'] : null;
    }
    return null;
}

function vira_admin_user_manage_keyboard(string $id_user, array $userRow, array $setting, array $textbotlang, string $category = 'main'): string
{
    $id = $id_user;
    $backMain = [['text' => '⬅️ بازگشت', 'callback_data' => 'manageuser_' . $id]];

    if ($category === 'main') {
        $rows = [
            [['text' => '♻️ بروزرسانی اطلاعات', 'callback_data' => 'updateinfouser_' . $id]],
            [
                ['text' => '💰 مالی و موجودی', 'callback_data' => 'manageusercat_fin_' . $id],
                ['text' => '👤 وضعیت و دسترسی', 'callback_data' => 'manageusercat_st_' . $id],
            ],
            [
                ['text' => '🛒 سفارش و سرویس', 'callback_data' => 'manageusercat_ord_' . $id],
                ['text' => '💳 کارت و احراز', 'callback_data' => 'manageusercat_card_' . $id],
            ],
            [
                ['text' => '👥 نمایندگی و زیرمجموعه', 'callback_data' => 'manageusercat_ag_' . $id],
                ['text' => '✉️ پیام و انتقال', 'callback_data' => 'manageusercat_msg_' . $id],
            ],
        ];
        return json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE);
    }

    $rows = [];

    if ($category === 'fin') {
        $rows[] = [
            ['text' => $textbotlang['Admin']['ManageUser']['addbalanceuser'], 'callback_data' => 'addbalanceuser_' . $id],
            ['text' => $textbotlang['Admin']['ManageUser']['lowbalanceuser'], 'callback_data' => 'lowbalanceuser_' . $id],
        ];
        $rows[] = [['text' => '0️⃣ صفر کردن موجودی', 'callback_data' => 'zerobalance-' . $id]];
        $rows[] = [
            ['text' => '🎁 درصد تخفیف', 'callback_data' => 'Percentlow_' . $id],
            ['text' => $textbotlang['Admin']['ManageUser']['viewpaymentuser'], 'callback_data' => 'viewpaymentuser_' . $id],
        ];
    } elseif ($category === 'st') {
        $rows[] = [
            ['text' => $textbotlang['Admin']['ManageUser']['banuserlist'], 'callback_data' => 'banuserlist_' . $id],
            ['text' => $textbotlang['Admin']['ManageUser']['unbanuserlist'], 'callback_data' => 'unbanuserr_' . $id],
        ];
        $rows[] = [
            ['text' => 'احراز هویت کاربر', 'callback_data' => 'verify_' . $id],
            ['text' => 'عدم احراز کاربر', 'callback_data' => 'unverify-' . $id],
        ];
        $rows[] = [['text' => $textbotlang['Admin']['ManageUser']['confirmnumber'], 'callback_data' => 'confirmnumber_' . $id]];
        $rows[] = [['text' => '📑 احراز عضویت کانال', 'callback_data' => 'confirmchannel-' . $id]];
        $rows[] = [
            ['text' => '💡 خاموش کردن اکانت', 'callback_data' => 'disableconfig-' . $id],
            ['text' => '💡 روشن کردن اکانت', 'callback_data' => 'activeconfig-' . $id],
        ];
        $rows[] = [['text' => '🕚 وضعیت ارسال پیام های کرون', 'callback_data' => 'statuscronuser-' . $id]];
    } elseif ($category === 'ord') {
        $rows[] = [['text' => $textbotlang['Admin']['ManageUser']['vieworderuser'], 'callback_data' => 'vieworderuser_' . $id]];
        $rows[] = [
            ['text' => '🛒 افزودن سفارش', 'callback_data' => 'addordermanualـ' . $id],
            ['text' => '➕ محدودیت اکانت تست', 'callback_data' => 'limitusertest_' . $id],
        ];
        if (intval($setting['statuslimitchangeloc'] ?? 0) === 1) {
            $rows[] = [['text' => 'محدودیت تغییر لوکیشن', 'callback_data' => 'changeloclimitbyuser_' . $id]];
        }
    } elseif ($category === 'card') {
        $rows[] = [['text' => '💳 فعالسازی شماره کارت', 'callback_data' => 'showcarduser-' . $id]];
        $rows[] = [['text' => '💳 غیرفعالسازی شماره کارت', 'callback_data' => 'carduserhide-' . $id]];
    } elseif ($category === 'ag') {
        $rows[] = [
            ['text' => $textbotlang['Admin']['ManageUser']['addagent'], 'callback_data' => 'addagent_' . $id],
            ['text' => $textbotlang['Admin']['ManageUser']['removeagent'], 'callback_data' => 'removeagent_' . $id],
        ];
        $rows[] = [['text' => '👥 زیرمجموعه های کاربر', 'callback_data' => 'affiliates-' . $id]];
        $rows[] = [
            ['text' => '🔄 خارج کردن از زیرمجموعه', 'callback_data' => 'removeaffiliate-' . $id],
            ['text' => '🔄 حذف زیرمجموعه های کاربر', 'callback_data' => 'removeaffiliateuser-' . $id],
        ];
        if (($userRow['agent'] ?? 'f') === 'n2') {
            $rows[] = [['text' => 'سقف خرید نماینده', 'callback_data' => 'maxbuyagent_' . $id]];
        }
        if (($userRow['agent'] ?? 'f') !== 'f') {
            $rows[] = [
                ['text' => '🤖 فعالسازی ربات فروش', 'callback_data' => 'createbot_' . $id],
                ['text' => '❌ حذف ربات فروش', 'callback_data' => 'removebotsell_' . $id],
            ];
            $rows[] = [
                ['text' => '🔋 قیمت پایه حجم', 'callback_data' => 'setvolumesrc_' . $id],
                ['text' => '⏳ قیمت پایه زمان', 'callback_data' => 'settimepricesrc_' . $id],
            ];
            $rows[] = [['text' => '❌ مخفی کردن یک پنل برای نماینده', 'callback_data' => 'hidepanel_' . $id]];
            $rows[] = [['text' => '🗑 نمایش پنل های مخفی شده', 'callback_data' => 'removehide_' . $id]];
            $rows[] = [['text' => '⏱️ زمان انقضا نمایندگی', 'callback_data' => 'expireset_' . $id]];
        }
    } elseif ($category === 'msg') {
        $rows[] = [['text' => '✍️ ارسال پیام به کاربر', 'callback_data' => 'sendmessageuser_' . $id]];
        $rows[] = [['text' => 'انتقال حساب کاربری', 'callback_data' => 'transferaccount_' . $id]];
    }

    $rows[] = $backMain;

    return json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE);
}

function vira_admin_user_manage_category_title(string $category): string
{
    $map = [
        'fin' => '💰 مالی و موجودی',
        'st' => '👤 وضعیت و دسترسی',
        'ord' => '🛒 سفارش و سرویس',
        'card' => '💳 کارت و احراز',
        'ag' => '👥 نمایندگی و زیرمجموعه',
        'msg' => '✉️ پیام و انتقال',
    ];
    return $map[$category] ?? '';
}

function vira_admin_show_user_manage($from_id, string $id_user, array $opts = []): bool
{
    global $connect, $pdo, $textbotlang, $setting, $users_ids, $keyboardadmin, $message_id, $callback_query_id;

    if (!in_array($id_user, $users_ids)) {
        sendmessage($from_id, $textbotlang['Admin']['not-user'], null, 'HTML');
        return false;
    }

    $category = (string) ($opts['category'] ?? 'main');
    $edit = !empty($opts['edit']);
    $refreshAlert = !empty($opts['refresh_alert']);

    $dayListSell = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) FROM invoice WHERE (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND id_user = '$id_user'"));
    $balanceall = mysqli_fetch_assoc(mysqli_query($connect, "SELECT SUM(price) FROM Payment_report WHERE payment_Status = 'paid' AND id_user = '$id_user' AND Payment_Method != 'low balance by admin'"));
    $subbuyuser = mysqli_fetch_assoc(mysqli_query($connect, "SELECT SUM(price_product) FROM invoice WHERE (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND id_user = '$id_user'"));
    $invoicecount = select('invoice', '*', 'id_user', $id_user, 'count');
    if ($invoicecount == 0) {
        $sumvolume = ['SUM(Volume)' => 0];
    } else {
        $sumvolume = mysqli_fetch_assoc(mysqli_query($connect, "SELECT SUM(Volume) FROM invoice WHERE (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND id_user = '$id_user' AND name_product != 'سرویس تست'"));
    }

    $userRow = select('user', '*', 'id', $id_user, 'select');
    $roll_Status = [
        '1' => $textbotlang['Admin']['ManageUser']['Acceptedphone'],
        '0' => $textbotlang['Admin']['ManageUser']['Failedphone'],
    ][$userRow['roll_Status']];

    if ($subbuyuser['SUM(price_product)'] == null) {
        $subbuyuser['SUM(price_product)'] = 0;
    }

    $keyboardmanage = vira_admin_user_manage_keyboard($id_user, $userRow, $setting, $textbotlang, $category);
    $balanceFmt = number_format((int) $userRow['Balance']);

    if ($userRow['register'] != 'none' && $userRow['register'] != null) {
        $userjoin = jdate('Y/m/d H:i:s', $userRow['register']);
    } else {
        $userjoin = 'نامشخص';
    }

    $userverify = ['0' => 'احراز نشده', '1' => 'احراز شده'][$userRow['verify']];
    $showcart = ['0' => 'مخفی', '1' => 'نمایش داده می شود'][$userRow['cardpayment']];
    $lastmessage = $userRow['last_message_time'] == null ? '' : jdate('Y/m/d H:i:s', $userRow['last_message_time']);

    $desired_date_time_start = time() - 3600;
    $month_date_time_start = time() - 2592000;

    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE time_sell > :requestedDate AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND name_product != 'سرویس تست' AND id_user = :id_user");
    $stmt->bindParam(':id_user', $id_user);
    $stmt->bindParam(':requestedDate', $desired_date_time_start);
    $stmt->execute();
    $listhours = $stmt->rowCount();

    $stmt = $pdo->prepare("SELECT SUM(price_product) FROM invoice WHERE time_sell > :requestedDate AND (Status = 'active' OR Status = 'end_of_time'  OR Status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND name_product != 'سرویس تست' AND id_user = :id_user");
    $stmt->bindParam(':id_user', $id_user);
    $stmt->bindParam(':requestedDate', $desired_date_time_start);
    $stmt->execute();
    $suminvoicehours = $stmt->fetchColumn() ?: '0';

    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE time_sell > :requestedDate AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND name_product != 'سرویس تست' AND id_user = :id_user");
    $stmt->bindParam(':id_user', $id_user);
    $stmt->bindParam(':requestedDate', $month_date_time_start);
    $stmt->execute();
    $listmonth = $stmt->rowCount();

    $stmt = $pdo->prepare("SELECT SUM(price_product) FROM invoice WHERE time_sell > :requestedDate AND (Status = 'active' OR Status = 'end_of_time'  OR Status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND name_product != 'سرویس تست' AND id_user = :id_user");
    $stmt->bindParam(':id_user', $id_user);
    $stmt->bindParam(':requestedDate', $month_date_time_start);
    $stmt->execute();
    $suminvoicemonth = $stmt->fetchColumn() ?: '0';

    if ($userRow['agent'] != 'f' && $userRow['expire'] != null) {
        $text_expie_agent = '⭕️ تاریخ پایان نمایندگی : ' . jdate('Y/m/d H:i:s', $userRow['expire']);
    } else {
        $text_expie_agent = '';
    }

    $uname = htmlspecialchars((string) ($userRow['username'] ?? ''), ENT_QUOTES, 'UTF-8');
    $textinfouser = "👀 اطلاعات کاربر:

🔗 اطلاعات کاربری کاربر

⭕️ وضعیت کاربر : {$userRow['User_Status']}
⭕️ نام کاربری کاربر : @{$uname}
⭕️ آیدی عددی کاربر :  <a href = \"tg://user?id=$id_user\">$id_user</a>
⭕️ کد معرف کاربر : {$userRow['codeInvitation']}
⭕️ زمان عضویت کاربر : $userjoin
⭕️ آخرین زمان  استفاده کاربر از ربات : $lastmessage
⭕️ محدودیت اکانت تست :  {$userRow['limit_usertest']} 
⭕️ وضعیت تایید قانون : $roll_Status
⭕️ شماره موبایل : <code>{$userRow['number']}</code>
⭕️ نوع کاربری : {$userRow['agent']}
⭕️ تعداد زیرمجموعه کاربر : {$userRow['affiliatescount']}
⭕  معرف کاربر : {$userRow['affiliates']}
⭕  وضعیت احراز هویت: $userverify   
⭕  نمایش شماره کارت :‌$showcart
⭕ امتیاز کاربر : {$userRow['score']}
⭕️  مجموع حجم خریداری شده فعال ( برای آمار دقیق حجم باید کرون روشن باشد): {$sumvolume['SUM(Volume)']}
$text_expie_agent

💎 گزارشات مالی

🔰 موجودی کاربر : {$balanceFmt}
🔰 تعداد خرید کل کاربر : {$dayListSell['COUNT(*)']}
🔰️ مبلغ کل پرداختی  :  {$balanceall['SUM(price)']}
🔰 جمع کل خرید : {$subbuyuser['SUM(price_product)']}
🔰 درصد تخفیف کاربر : {$userRow['pricediscount']}
🔰 تعداد فروش یک ساعت گذشته : $listhours عدد
🔰 مجموع فروش یک ساعت گذشته : $suminvoicehours تومان
🔰 تعداد فروش یک ماه گذشته : $listmonth عدد
🔰 مجموع فروش یک ماه گذشته : $suminvoicemonth تومان

";

    if ($category !== 'main') {
        $catTitle = vira_admin_user_manage_category_title($category);
        $textinfouser = "👤 کاربر <a href=\"tg://user?id=$id_user\">$id_user</a> · @$uname\n\n<b>$catTitle</b>\n\nیک گزینه را انتخاب کنید:";
    } else {
        $textinfouser .= "\n📂 گزینه مورد نظر را از دسته‌بندی زیر انتخاب کنید.";
    }

    if ($refreshAlert && $callback_query_id) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'اطلاعات بروزرسانی گردید',
            'show_alert' => true,
            'cache_time' => 5,
        ]);
    }

    if ($edit && !empty($opts['message_id'])) {
        Editmessagetext($from_id, (int) $opts['message_id'], $textinfouser, $keyboardmanage);
    } else {
        sendmessage($from_id, $textinfouser, $keyboardmanage, 'HTML');
        if ($category === 'main') {
            sendmessage($from_id, $textbotlang['users']['selectoption'], $keyboardadmin, 'HTML');
        }
    }

    step('home', $from_id);
    return true;
}
