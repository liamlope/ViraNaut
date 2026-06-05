<?php
/**
 * ایجاد لینک شارژ کیف پول برای مینی‌اپ (بدون ارجاع به چت ربات)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../function.php';

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Tehran');

if (!function_exists('getallheaders')) {
    function getallheaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) !== 'HTTP_') {
                continue;
            }
            $headerName = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
            $headers[$headerName] = $value;
        }
        return $headers;
    }
}

$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = '';
if (stripos($authHeader, 'Bearer ') === 0) {
    $token = trim(substr($authHeader, 7));
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '{}', true);
if (!is_array($data)) {
    $data = [];
}

$userId = isset($data['user_id']) ? (int) $data['user_id'] : 0;
$amount = isset($data['amount']) ? (int) $data['amount'] : 0;
$gateway = trim((string) ($data['gateway'] ?? 'zarinpal'));

$user = select('user', '*', 'id', $userId, 'select');
if (!$user || ($user['token'] ?? '') !== $token) {
    http_response_code(403);
    echo json_encode(['status' => false, 'msg' => 'Token invalid'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($amount < 5000) {
    echo json_encode(['status' => false, 'msg' => 'حداقل مبلغ شارژ ۵٬۰۰۰ تومان است'], JSON_UNESCAPED_UNICODE);
    exit;
}

$orderId = bin2hex(random_bytes(5));
$paymentUrl = null;
$methodLabel = '';

if ($gateway === 'zarinpal') {
    $st = select('PaySetting', 'ValuePay', 'NamePay', 'zarinpalstatus', 'select');
    if (($st['ValuePay'] ?? '') !== 'onzarinpal') {
        echo json_encode(['status' => false, 'msg' => 'زرین‌پال غیرفعال است'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $min = (int) (select('PaySetting', 'ValuePay', 'NamePay', 'minbalancezarinpal', 'select')['ValuePay'] ?? 5000);
    $max = (int) (select('PaySetting', 'ValuePay', 'NamePay', 'maxbalancezarinpal', 'select')['ValuePay'] ?? 50000000);
    if ($amount < $min || $amount > $max) {
        echo json_encode([
            'status' => false,
            'msg' => "مبلغ باید بین " . number_format($min) . " و " . number_format($max) . " تومان باشد",
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $pay = createPayZarinpal($amount, $orderId);
    if (!is_array($pay) || ($pay['data']['code'] ?? 0) != 100) {
        echo json_encode(['status' => false, 'msg' => 'خطا در ساخت لینک پرداخت'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $authority = $pay['data']['authority'];
    $paymentUrl = 'https://www.zarinpal.com/pg/StartPay/' . $authority;
    $methodLabel = 'زرین‌پال';
    $dateacc = date('Y/m/d H:i:s');
    $payment_Status = 'Unpaid';
    $invoice = $orderId . '|miniapp';
    global $connect;
    $stmt = $connect->prepare('INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice,dec_not_confirmed) VALUES (?,?,?,?,?,?,?,?)');
    $stmt->bind_param('ssssssss', $userId, $orderId, $dateacc, $amount, $payment_Status, $methodLabel, $invoice, $authority);
    $stmt->execute();
    $stmt->close();
} else {
    echo json_encode(['status' => false, 'msg' => 'درگاه پشتیبانی‌نشده'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'status' => true,
    'msg' => 'ok',
    'order_id' => $orderId,
    'payment_url' => $paymentUrl,
    'gateway' => $gateway,
    'amount' => $amount,
], JSON_UNESCAPED_UNICODE);
