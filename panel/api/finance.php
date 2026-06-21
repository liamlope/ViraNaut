<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/pay_settings_defs.php';

header('Content-Type: application/json; charset=utf-8');
require_auth_api();

$action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? ''));

function fin_json(bool $ok, string $msg = '', array $extra = []): void
{
    echo json_encode(array_merge(['ok' => $ok, 'msg' => $msg], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

function fin_method_map(): array
{
    return [
        'cart to cart' => 'کارت به کارت',
        'low balance by admin' => 'کسر موجودی ادمین',
        'add balance by admin' => 'افزایش توسط ادمین',
        'Currency Rial 1' => 'درگاه ریالی ۱',
        'Currency Rial tow' => 'درگاه ریالی ۲',
        'Currency Rial 3' => 'درگاه ریالی ۳',
        'aqayepardakht' => 'آقای پرداخت',
        'zarinpal' => 'زرین‌پال',
        'plisio' => 'Plisio',
        'arze digital offline' => 'ارز دیجیتال آفلاین',
        'Star Telegram' => 'استار تلگرام',
        'nowpayment' => 'NowPayments',
    ];
}

function fin_bootstrap_bot_globals(): void
{
    global $ManagePanel, $textbotlang, $from_id, $message_id, $keyboard, $Confirm_pay, $datatextbot, $setting;
    if (!class_exists('ManagePanel')) {
        require_once __DIR__ . '/../../botapi.php';
        require_once __DIR__ . '/../../panels.php';
        require_once __DIR__ . '/../../keyboard.php';
        require_once __DIR__ . '/../../jdf.php';
        $ManagePanel = new ManagePanel();
    }
    $textbotlang = languagechange(__DIR__ . '/../../text.json');
    if (!is_array($textbotlang)) {
        $textbotlang = [];
    }
    mirza_apply_textbotlang_compat($textbotlang);
    $from_id = 0;
    $message_id = 0;
    $setting = select('setting', '*');
}

$methodMap = fin_method_map();

if ($action === 'overview') {
    $todayStart = panel_today_start_string();
    try {
        $totalPaid = (int) db_query(
            $pdo,
            "SELECT COALESCE(SUM(price),0) FROM Payment_report WHERE payment_Status = 'paid'
             AND Payment_Method NOT IN ('add balance by admin','low balance by admin')"
        )->fetchColumn();
        $paidToday = (int) db_query(
            $pdo,
            "SELECT COALESCE(SUM(price),0) FROM Payment_report WHERE payment_Status = 'paid' AND time >= ?",
            [$todayStart]
        )->fetchColumn();
        $txToday = db_count($pdo, 'SELECT COUNT(*) FROM Payment_report WHERE time >= ?', [$todayStart]);
        $pending = db_count($pdo, "SELECT COUNT(*) FROM Payment_report WHERE payment_Status = 'waiting'");
        $unpaid = db_count($pdo, "SELECT COUNT(*) FROM Payment_report WHERE payment_Status = 'Unpaid'");
        $rejected = db_count($pdo, "SELECT COUNT(*) FROM Payment_report WHERE payment_Status = 'reject'");
        $invoiceRevenue = (int) db_query(
            $pdo,
            "SELECT COALESCE(SUM(price_product),0) FROM invoice WHERE Status != 'Unpaid' AND name_product != 'سرویس تست'"
        )->fetchColumn();
        $walletSum = (int) db_query($pdo, 'SELECT COALESCE(SUM(Balance),0) FROM user WHERE User_Status != ?', ['block'])->fetchColumn();
        $discountCount = db_count($pdo, 'SELECT COUNT(*) FROM Discount');

        $byMethod = db_fetchAll(
            $pdo,
            "SELECT Payment_Method, COUNT(*) AS cnt, COALESCE(SUM(price),0) AS total
             FROM Payment_report WHERE payment_Status = 'paid'
             AND Payment_Method NOT IN ('add balance by admin','low balance by admin')
             GROUP BY Payment_Method ORDER BY total DESC LIMIT 12"
        );
        foreach ($byMethod as &$row) {
            $row['label'] = $methodMap[$row['Payment_Method']] ?? $row['Payment_Method'];
            $row['total'] = (int) $row['total'];
            $row['cnt'] = (int) $row['cnt'];
        }
        unset($row);

        fin_json(true, '', [
            'stats' => [
                'total_paid' => $totalPaid,
                'paid_today' => $paidToday,
                'tx_today' => $txToday,
                'pending' => $pending,
                'unpaid' => $unpaid,
                'rejected' => $rejected,
                'invoice_revenue' => $invoiceRevenue,
                'wallet_sum' => $walletSum,
                'discount_codes' => $discountCount,
            ],
            'by_method' => $byMethod,
        ]);
    } catch (Exception $e) {
        fin_json(false, $e->getMessage());
    }
}

if ($action === 'gateways') {
    try {
        $profiles = [];
        foreach (mirza_pay_gateway_profiles() as $p) {
            $t = $p['toggle'];
            $tval = mirza_pay_get_value($pdo, $t['key']);
            $fields = [];
            foreach ($p['fields'] as $f) {
                $fields[] = array_merge($f, ['value' => mirza_pay_get_value($pdo, $f['key'])]);
            }
            $profiles[] = [
                'id' => $p['id'],
                'label' => $p['label'],
                'toggle' => $t,
                'toggle_value' => $tval,
                'toggle_on' => mirza_pay_is_on($t, $tval),
                'fields' => $fields,
            ];
        }
        $general = [];
        foreach (mirza_pay_general_defs() as $g) {
            $val = mirza_pay_get_value($pdo, $g['key']);
            if ($g['key'] === 'cardreceiptdelaymin' && ($val === '' || $val === '0')) {
                $val = '10';
            }
            $general[] = array_merge($g, ['value' => $val]);
        }
        $cards = db_fetchAll($pdo, 'SELECT cardnumber, namecard FROM card_number ORDER BY cardnumber ASC');
        $smsInfo = function_exists('mirza_card_sms_panel_info') ? mirza_card_sms_panel_info() : [];
        fin_json(true, '', ['profiles' => $profiles, 'general' => $general, 'cards' => $cards, 'sms' => $smsInfo]);
    } catch (Exception $e) {
        fin_json(false, $e->getMessage());
    }
}

if ($action === 'gateway_profile_save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $profileId = trim((string) ($_POST['profile_id'] ?? ''));
    $fieldsJson = $_POST['fields'] ?? '{}';
    $fields = is_array($fieldsJson) ? $fieldsJson : (json_decode((string) $fieldsJson, true) ?: []);
    $profile = null;
    foreach (mirza_pay_gateway_profiles() as $p) {
        if ($p['id'] === $profileId) {
            $profile = $p;
            break;
        }
    }
    if (!$profile) {
        fin_json(false, 'درگاه نامعتبر');
    }
    $allowed = array_column($profile['fields'], 'key');
    try {
        foreach ($fields as $k => $v) {
            if (in_array($k, $allowed, true)) {
                mirza_pay_set_value($pdo, $k, trim((string) $v));
            }
        }
        fin_json(true, 'ذخیره شد');
    } catch (Exception $e) {
        fin_json(false, $e->getMessage());
    }
}

if ($action === 'general_pay_save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $fieldsJson = $_POST['fields'] ?? '{}';
    $fields = is_array($fieldsJson) ? $fieldsJson : (json_decode((string) $fieldsJson, true) ?: []);
    $allowed = array_column(mirza_pay_general_defs(), 'key');
    try {
        foreach ($fields as $k => $v) {
            if (in_array($k, $allowed, true)) {
                $val = trim((string) $v);
                if ($k === 'cardreceiptdelaymin') {
                    $val = (string) max(1, min(1440, (int) $val));
                }
                mirza_pay_set_value($pdo, $k, $val);
            }
        }
        fin_json(true, 'تنظیمات عمومی ذخیره شد');
    } catch (Exception $e) {
        fin_json(false, $e->getMessage());
    }
}

if ($action === 'gateway_toggle' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $key = trim((string) ($_POST['key'] ?? ''));
    $def = null;
    foreach (mirza_pay_gateway_defs() as $g) {
        if ($g['key'] === $key) {
            $def = $g;
            break;
        }
    }
    if (!$def) {
        foreach (mirza_pay_gateway_profiles() as $p) {
            if (($p['toggle']['key'] ?? '') === $key) {
                $def = $p['toggle'];
                break;
            }
        }
    }
    if (!$def) {
        fin_json(false, 'درگاه نامعتبر');
    }
    try {
        $row = db_fetch($pdo, 'SELECT ValuePay FROM PaySetting WHERE NamePay = ?', [$key]);
        $cur = $row ? (string) $row['ValuePay'] : (string) $def['off'];
        $next = mirza_pay_toggle_next($def, $cur);
        $exists = db_count($pdo, 'SELECT COUNT(*) FROM PaySetting WHERE NamePay = ?', [$key]);
        if ($exists > 0) {
            db_query($pdo, 'UPDATE PaySetting SET ValuePay = ? WHERE NamePay = ?', [$next, $key]);
        } else {
            db_query($pdo, 'INSERT INTO PaySetting (NamePay, ValuePay) VALUES (?, ?)', [$key, $next]);
        }
        fin_json(true, 'وضعیت ذخیره شد', ['value' => $next, 'on' => mirza_pay_is_on($def, $next)]);
    } catch (Exception $e) {
        fin_json(false, $e->getMessage());
    }
}

if ($action === 'gateway_save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $key = trim((string) ($_POST['key'] ?? ''));
    $value = trim((string) ($_POST['value'] ?? ''));
    $allowed = array_column(mirza_pay_secret_defs(), 'key');
    if (!in_array($key, $allowed, true)) {
        fin_json(false, 'فیلد مجاز نیست');
    }
    try {
        $exists = db_count($pdo, 'SELECT COUNT(*) FROM PaySetting WHERE NamePay = ?', [$key]);
        if ($exists > 0) {
            db_query($pdo, 'UPDATE PaySetting SET ValuePay = ? WHERE NamePay = ?', [$value, $key]);
        } else {
            db_query($pdo, 'INSERT INTO PaySetting (NamePay, ValuePay) VALUES (?, ?)', [$key, $value]);
        }
        fin_json(true, 'ذخیره شد');
    } catch (Exception $e) {
        fin_json(false, $e->getMessage());
    }
}

if ($action === 'card_add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $num = preg_replace('/\D/', '', (string) ($_POST['cardnumber'] ?? ''));
    $name = trim((string) ($_POST['namecard'] ?? ''));
    if (strlen($num) < 16 || $name === '') {
        fin_json(false, 'شماره کارت (۱۶ رقم) و نام دارنده الزامی است');
    }
    try {
        db_query($pdo, 'INSERT INTO card_number (cardnumber, namecard) VALUES (?, ?)', [$num, $name]);
        fin_json(true, 'کارت اضافه شد');
    } catch (Exception $e) {
        fin_json(false, 'احتمالاً تکراری است یا خطای DB');
    }
}

if ($action === 'card_delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $num = trim((string) ($_POST['cardnumber'] ?? ''));
    if ($num === '') {
        fin_json(false, 'شماره کارت نامعتبر');
    }
    try {
        db_query($pdo, 'DELETE FROM card_number WHERE cardnumber = ?', [$num]);
        fin_json(true, 'حذف شد');
    } catch (Exception $e) {
        fin_json(false, $e->getMessage());
    }
}

if ($action === 'transactions') {
    $q = trim((string) ($_GET['q'] ?? ''));
    $status = trim((string) ($_GET['status'] ?? ''));
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = min(50, max(10, (int) ($_GET['per_page'] ?? 25)));
    $offset = ($page - 1) * $perPage;
    $where = [];
    $params = [];
    if ($q !== '') {
        $where[] = '(id_user LIKE ? OR id_order LIKE ? OR id_invoice LIKE ?)';
        $params[] = "%$q%";
        $params[] = "%$q%";
        $params[] = "%$q%";
    }
    if ($status !== '') {
        $where[] = 'payment_Status = ?';
        $params[] = $status;
    }
    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    try {
        $total = db_count($pdo, "SELECT COUNT(*) FROM Payment_report $whereSQL", $params);
        $rows = db_fetchAll(
            $pdo,
            "SELECT id_order, id_user, price, payment_Status, Payment_Method, time, id_invoice, dec_not_confirmed
             FROM Payment_report $whereSQL ORDER BY time DESC LIMIT $perPage OFFSET $offset",
            $params
        );
        foreach ($rows as &$r) {
            $r['method_label'] = $methodMap[$r['Payment_Method'] ?? ''] ?? ($r['Payment_Method'] ?? '—');
            $r['price'] = (int) ($r['price'] ?? 0);
            $r['time_label'] = panel_format_payment_time($r['time'] ?? '');
            $inv = explode('|', mirza_card_invoice_payment_payload((string) ($r['id_invoice'] ?? '')));
            $r['invoice_type'] = $inv[0] ?? '';
        }
        unset($r);
        fin_json(true, '', [
            'items' => $rows,
            'total' => $total,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / $perPage)),
        ]);
    } catch (Exception $e) {
        fin_json(false, $e->getMessage());
    }
}

if ($action === 'payment_approve' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $orderId = trim((string) ($_POST['id_order'] ?? ''));
    if ($orderId === '') {
        fin_json(false, 'شناسه سفارش نامعتبر');
    }
    try {
        $pay = db_fetch($pdo, 'SELECT * FROM Payment_report WHERE id_order = ? LIMIT 1', [$orderId]);
        if (!$pay) {
            fin_json(false, 'تراکنش یافت نشد');
        }
        if (in_array($pay['payment_Status'], ['paid', 'reject'], true)) {
            fin_json(false, 'این تراکنش قبلاً بررسی شده است');
        }
        $typepay = explode('|', mirza_card_invoice_payment_payload((string) ($pay['id_invoice'] ?? '')));
        $blockTypes = ['getconfigafterpay', 'getextenduser', 'getextravolumeuser', 'getextratimeuser'];
        if (!in_array($typepay[0] ?? '', $blockTypes, true)) {
            $cnt = db_count(
                $pdo,
                "SELECT COUNT(*) FROM Payment_report WHERE id_user = ? AND payment_Status NOT IN ('paid','Unpaid','expire','reject')
                 AND (id_invoice LIKE '%getconfigafterpay%' OR id_invoice LIKE '%getextenduser%'
                 OR id_invoice LIKE '%getextravolumeuser%' OR id_invoice LIKE '%getextratimeuser%')",
                [$pay['id_user']]
            );
            if ($cnt > 0) {
                fin_json(false, 'ابتدا رسیدهای خرید/تمدید سرویس این کاربر را در تلگرام تأیید کنید');
            }
        }
        fin_bootstrap_bot_globals();
        DirectPayment($orderId);
        $cashback = select('PaySetting', 'ValuePay', 'NamePay', 'chashbackcart', 'select');
        if ($cashback && (string) ($cashback['ValuePay'] ?? '0') !== '0') {
            $pct = (float) $cashback['ValuePay'];
            $result = (int) (($pay['price'] * $pct) / 100);
            if ($result > 0) {
                $u = select('user', '*', 'id', $pay['id_user'], 'select');
                if ($u) {
                    update('user', 'Balance', (int) $u['Balance'] + $result, 'id', $u['id']);
                    sendmessage($u['id'], "🎁 مبلغ $result تومان هدیه واریز شد.", null, 'HTML');
                }
            }
        }
        update('Payment_report', 'payment_Status', 'paid', 'id_order', $orderId);
        update('user', 'Processing_value_one', 'none', 'id', $pay['id_user']);
        fin_json(true, 'پرداخت تأیید و موجودی/سرویس اعمال شد');
    } catch (Throwable $e) {
        error_log('finance payment_approve: ' . $e->getMessage());
        fin_json(false, 'خطا در تأیید: ' . $e->getMessage());
    }
}

if ($action === 'payment_reject' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $orderId = trim((string) ($_POST['id_order'] ?? ''));
    $reason = trim((string) ($_POST['reason'] ?? 'رد شده از پنل وب'));
    if ($orderId === '') {
        fin_json(false, 'شناسه سفارش نامعتبر');
    }
    try {
        $pay = db_fetch($pdo, 'SELECT * FROM Payment_report WHERE id_order = ? LIMIT 1', [$orderId]);
        if (!$pay) {
            fin_json(false, 'تراکنش یافت نشد');
        }
        if (in_array($pay['payment_Status'], ['paid', 'reject'], true)) {
            fin_json(false, 'این تراکنش قبلاً بررسی شده است');
        }
        update('Payment_report', 'payment_Status', 'reject', 'id_order', $orderId);
        update('Payment_report', 'dec_not_confirmed', $reason, 'id_order', $orderId);
        fin_bootstrap_bot_globals();
        sendmessage(
            $pay['id_user'],
            "❌ پرداخت شما رد شد.\n✍️ $reason\n🛒 کد پیگیری: $orderId",
            null,
            'HTML'
        );
        fin_json(true, 'تراکنش رد شد و به کاربر اطلاع داده شد');
    } catch (Throwable $e) {
        fin_json(false, $e->getMessage());
    }
}

if ($action === 'invoices') {
    $q = trim((string) ($_GET['q'] ?? ''));
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = min(40, max(10, (int) ($_GET['per_page'] ?? 15)));
    $offset = ($page - 1) * $perPage;
    $where = ["Status != 'Unpaid'"];
    $params = [];
    if ($q !== '') {
        $where[] = '(id_user LIKE ? OR username LIKE ? OR name_product LIKE ?)';
        $params[] = "%$q%";
        $params[] = "%$q%";
        $params[] = "%$q%";
    }
    $whereSQL = 'WHERE ' . implode(' AND ', $where);
    try {
        $total = db_count($pdo, "SELECT COUNT(*) FROM invoice $whereSQL", $params);
        $rows = db_fetchAll(
            $pdo,
            "SELECT id_invoice, id_user, username, name_product, price_product, Status, time_sell, Service_location
             FROM invoice $whereSQL ORDER BY time_sell DESC LIMIT $perPage OFFSET $offset",
            $params
        );
        fin_json(true, '', ['items' => $rows, 'total' => $total, 'page' => $page]);
    } catch (Exception $e) {
        fin_json(false, $e->getMessage());
    }
}

if ($action === 'discounts') {
    try {
        $rows = db_fetchAll($pdo, 'SELECT id, code, price, limituse, limitused FROM Discount ORDER BY id DESC LIMIT 100');
        fin_json(true, '', ['items' => $rows]);
    } catch (Exception $e) {
        fin_json(false, $e->getMessage());
    }
}

if ($action === 'discount_save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $id = (int) ($_POST['id'] ?? 0);
    $code = trim((string) ($_POST['code'] ?? ''));
    $price = trim((string) ($_POST['price'] ?? '0'));
    $limituse = trim((string) ($_POST['limituse'] ?? '0'));
    if ($code === '') {
        fin_json(false, 'کد الزامی است');
    }
    try {
        if ($id > 0) {
            db_query($pdo, 'UPDATE Discount SET code = ?, price = ?, limituse = ? WHERE id = ?', [$code, $price, $limituse, $id]);
        } else {
            db_query($pdo, 'INSERT INTO Discount (code, price, limituse, limitused) VALUES (?, ?, ?, ?)', [$code, $price, $limituse, '0']);
        }
        fin_json(true, 'کد تخفیف ذخیره شد');
    } catch (Exception $e) {
        fin_json(false, $e->getMessage());
    }
}

if ($action === 'discount_delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) {
        fin_json(false, 'شناسه نامعتبر');
    }
    try {
        db_query($pdo, 'DELETE FROM Discount WHERE id = ?', [$id]);
        fin_json(true, 'حذف شد');
    } catch (Exception $e) {
        fin_json(false, $e->getMessage());
    }
}

if ($action === 'export' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $days = min(365, max(1, (int) ($_POST['days'] ?? 30)));
    $sinceStr = date('Y/m/d H:i:s', time() - ($days * 86400));
    try {
        $rows = db_fetchAll(
            $pdo,
            "SELECT id_order, id_user, price, payment_Status, Payment_Method, time, id_invoice
             FROM Payment_report WHERE time >= ? ORDER BY time DESC",
            [$sinceStr]
        );
        foreach ($rows as &$r) {
            $r['time_label'] = panel_format_payment_time($r['time'] ?? '');
        }
        unset($r);
        fin_json(true, '', ['rows' => $rows]);
    } catch (Exception $e) {
        fin_json(false, $e->getMessage());
    }
}

http_response_code(400);
fin_json(false, 'عملیات نامعتبر');
