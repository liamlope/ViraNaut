<?php

function readJsonFileIfExists($path, $default = [])
{
    if (!is_file($path)) {
        return $default;
    }

    $content = file_get_contents($path);
    if ($content === false || $content === '') {
        return $default;
    }

    $decoded = json_decode($content, true);
    return is_array($decoded) ? $decoded : $default;
}

function vpnbot_sync_user_balance_json(string $userId, int $balance): void
{
    $jsonPath = "data/{$userId}/{$userId}.json";
    if (!is_file($jsonPath)) {
        return;
    }
    $userbalance = readJsonFileIfExists($jsonPath, []);
    $userbalance['Balance'] = $balance;
    file_put_contents($jsonPath, json_encode($userbalance));
}

function DirectPaymentbot($order_id, $image = 'images.jpg')
{
    global $pdo, $ManagePanel, $textbotlang, $keyboardextendfnished, $keyboard, $Confirm_pay, $from_id, $message_id, $datatextbot;
    if (function_exists('mirza_payment_try_claim') && !mirza_payment_try_claim((string) $order_id)) {
        return false;
    }
    $setting = select("setting", "*");
    $Payment_report = select("Payment_report", "*", "id_order", $order_id, "select");
    if (!is_array($Payment_report) || empty($Payment_report['id_order'])) {
        return false;
    }
    $format_price_cart = number_format($Payment_report['price']);
    $Balance_id = select("user", "*", "id", $Payment_report['id_user'], "select");
    if (!$Balance_id) {
        return false;
    }
    update("user", "Processing_value", "0", "id", $Balance_id['id']);
    update("user", "Processing_value_one", "0", "id", $Balance_id['id']);
    update("user", "Processing_value_tow", "0", "id", $Balance_id['id']);
    update("user", "Processing_value_four", "0", "id", $Balance_id['id']);
    $Balance_confrim = (int) $Balance_id['Balance'] + (int) $Payment_report['price'];
    update("user", "Balance", $Balance_confrim, "id", $Balance_id['id']);
    vpnbot_sync_user_balance_json((string) $Balance_id['id'], $Balance_confrim);
    if (!function_exists('mirza_payment_try_claim')) {
        update("Payment_report", "payment_Status", "paid", "id_order", $Payment_report['id_order']);
    }
    $Payment_report['price'] = number_format($Payment_report['price'], 0);
    $format_price_cart = $Payment_report['price'];
    if ($Payment_report['Payment_Method'] == "cart to cart" or $Payment_report['Payment_Method'] == "arze digital offline") {
        $textconfrom = "⭕️ یک پرداخت جدید انجام شده است
        افزایش موجودی.
👤 شناسه کاربر: <code>{$Balance_id['id']}</code>
🛒 کد پیگیری پرداخت: {$Payment_report['id_order']}
⚜️ نام کاربری: @{$Balance_id['username']}
💸 مبلغ پرداختی: $format_price_cart تومان
✍️ توضیحات : {$Payment_report['dec_not_confirmed']}";
        Editmessagetext($from_id, $message_id, $textconfrom, $Confirm_pay);
    }
    sendmessage($Payment_report['id_user'], "💎 کاربر گرامی مبلغ {$Payment_report['price']} تومان به کیف پول شما واریز گردید با تشکراز پرداخت شما.
                
🛒 کد پیگیری شما: {$Payment_report['id_order']}", null, 'HTML');
    return true;
}

function channel_check($id_channel)
{
    global $from_id;
    $channel_link = array();
    $response = telegram('getChatMember', [
        'chat_id' => $id_channel,
        'user_id' => $from_id
    ]);
    if ($response['ok']) {
        if (!in_array($response['result']['status'], ['member', 'creator', 'administrator'])) {
            $channel_link[] = $id_channel;
        }
    }

    if (count($channel_link) == 0) {
        return [];
    }
    return $channel_link;
}
