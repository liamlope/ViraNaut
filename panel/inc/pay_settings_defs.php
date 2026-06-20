<?php

function mirza_pay_gateway_defs(): array
{
    return [
        ['key' => 'Cartstatus', 'label' => 'کارت به کارت', 'group' => 'gateway', 'type' => 'toggle', 'on' => 'oncard', 'off' => 'offcard'],
        ['key' => 'nowpaymentstatus', 'label' => 'Plisio', 'group' => 'gateway', 'type' => 'toggle', 'on' => 'onnowpayment', 'off' => 'offnowpayment', 'note' => 'کلید DB: nowpaymentstatus'],
        ['key' => 'statusnowpayment', 'label' => 'NowPayments', 'group' => 'gateway', 'type' => 'toggle01', 'on' => '1', 'off' => '0'],
        ['key' => 'zarinpalstatus', 'label' => 'زرین‌پال', 'group' => 'gateway', 'type' => 'toggle', 'on' => 'onzarinpal', 'off' => 'offzarinpal'],
        ['key' => 'statusaqayepardakht', 'label' => 'آقای پرداخت', 'group' => 'gateway', 'type' => 'toggle', 'on' => 'onaqayepardakht', 'off' => 'offaqayepardakht'],
        ['key' => 'statusSwapWallet', 'label' => 'ارز ریالی اول', 'group' => 'gateway', 'type' => 'toggle', 'on' => 'onSwapinoBot', 'off' => 'offSwapinoBot'],
        ['key' => 'statustarnado', 'label' => 'ارز ریالی دوم', 'group' => 'gateway', 'type' => 'toggle', 'on' => 'onternado', 'off' => 'offternado'],
        ['key' => 'statusiranpay3', 'label' => 'ارز ریالی سوم', 'group' => 'gateway', 'type' => 'toggle', 'on' => 'oniranpay3', 'off' => 'offiranpay3'],
        ['key' => 'digistatus', 'label' => 'ارز دیجیتال آفلاین', 'group' => 'gateway', 'type' => 'toggle', 'on' => 'ondigi', 'off' => 'offdigi'],
        ['key' => 'statusstar', 'label' => 'استار تلگرام', 'group' => 'gateway', 'type' => 'toggle01', 'on' => '1', 'off' => '0'],
    ];
}

/** پروفایل کامل هر درگاه برای UI */
function mirza_pay_gateway_profiles(): array
{
    return [
        [
            'id' => 'cart',
            'label' => 'کارت به کارت',
            'toggle' => ['key' => 'Cartstatus', 'type' => 'toggle', 'on' => 'oncard', 'off' => 'offcard'],
            'fields' => [
                ['key' => 'minbalancecart', 'label' => 'حداقل مبلغ', 'input' => 'number'],
                ['key' => 'maxbalancecart', 'label' => 'حداکثر مبلغ', 'input' => 'number'],
                ['key' => 'helpcart', 'label' => 'متن راهنما', 'input' => 'textarea'],
                ['key' => 'CartDirect', 'label' => 'متن مستقیم کارت', 'input' => 'textarea'],
            ],
        ],
        [
            'id' => 'plisio',
            'label' => 'Plisio',
            'toggle' => ['key' => 'nowpaymentstatus', 'type' => 'toggle', 'on' => 'onnowpayment', 'off' => 'offnowpayment'],
            'fields' => [
                ['key' => 'apinowpayment', 'label' => 'API Key', 'input' => 'password'],
                ['key' => 'minbalanceplisio', 'label' => 'حداقل', 'input' => 'number'],
                ['key' => 'maxbalanceplisio', 'label' => 'حداکثر', 'input' => 'number'],
                ['key' => 'helpplisio', 'label' => 'متن راهنما', 'input' => 'textarea'],
            ],
        ],
        [
            'id' => 'nowpay',
            'label' => 'NowPayments',
            'toggle' => ['key' => 'statusnowpayment', 'type' => 'toggle01', 'on' => '1', 'off' => '0'],
            'fields' => [
                ['key' => 'minbalancenowpayment', 'label' => 'حداقل', 'input' => 'number'],
                ['key' => 'maxbalancenowpayment', 'label' => 'حداکثر', 'input' => 'number'],
            ],
        ],
        [
            'id' => 'zarinpal',
            'label' => 'زرین‌پال',
            'toggle' => ['key' => 'zarinpalstatus', 'type' => 'toggle', 'on' => 'onzarinpal', 'off' => 'offzarinpal'],
            'fields' => [
                ['key' => 'merchant_zarinpal', 'label' => 'مرچنت', 'input' => 'text'],
                ['key' => 'minbalancezarinpal', 'label' => 'حداقل', 'input' => 'number'],
                ['key' => 'maxbalancezarinpal', 'label' => 'حداکثر', 'input' => 'number'],
                ['key' => 'helpzarinpal', 'label' => 'متن راهنما', 'input' => 'textarea'],
            ],
        ],
        [
            'id' => 'aqaye',
            'label' => 'آقای پرداخت',
            'toggle' => ['key' => 'statusaqayepardakht', 'type' => 'toggle', 'on' => 'onaqayepardakht', 'off' => 'offaqayepardakht'],
            'fields' => [
                ['key' => 'merchant_id_aqayepardakht', 'label' => 'مرچنت', 'input' => 'text'],
                ['key' => 'minbalanceaqayepardakht', 'label' => 'حداقل', 'input' => 'number'],
                ['key' => 'maxbalanceaqayepardakht', 'label' => 'حداکثر', 'input' => 'number'],
                ['key' => 'helpaqayepardakht', 'label' => 'متن راهنما', 'input' => 'textarea'],
            ],
        ],
        [
            'id' => 'iran1',
            'label' => 'ارز ریالی اول',
            'toggle' => ['key' => 'statusSwapWallet', 'type' => 'toggle', 'on' => 'onSwapinoBot', 'off' => 'offSwapinoBot'],
            'fields' => [
                ['key' => 'minbalanceiranpay1', 'label' => 'حداقل', 'input' => 'number'],
                ['key' => 'maxbalanceiranpay1', 'label' => 'حداکثر', 'input' => 'number'],
            ],
        ],
        [
            'id' => 'iran2',
            'label' => 'ارز ریالی دوم (Tornado)',
            'toggle' => ['key' => 'statustarnado', 'type' => 'toggle', 'on' => 'onternado', 'off' => 'offternado'],
            'fields' => [
                ['key' => 'apiternado', 'label' => 'API', 'input' => 'password'],
                ['key' => 'minbalanceiranpay2', 'label' => 'حداقل', 'input' => 'number'],
                ['key' => 'maxbalanceiranpay2', 'label' => 'حداکثر', 'input' => 'number'],
            ],
        ],
        [
            'id' => 'iran3',
            'label' => 'ارز ریالی سوم',
            'toggle' => ['key' => 'statusiranpay3', 'type' => 'toggle', 'on' => 'oniranpay3', 'off' => 'offiranpay3'],
            'fields' => [
                ['key' => 'minbalanceiranpay', 'label' => 'حداقل', 'input' => 'number'],
                ['key' => 'maxbalanceiranpay', 'label' => 'حداکثر', 'input' => 'number'],
            ],
        ],
        [
            'id' => 'digi',
            'label' => 'ارز آفلاین',
            'toggle' => ['key' => 'digistatus', 'type' => 'toggle', 'on' => 'ondigi', 'off' => 'offdigi'],
            'fields' => [
                ['key' => 'urlpaymenttron', 'label' => 'آدرس ولت/TRON', 'input' => 'text'],
                ['key' => 'marchent_tronseller', 'label' => 'مرچنت TRON', 'input' => 'text'],
                ['key' => 'offlinearze_tron_network', 'label' => 'شبکه TRON', 'input' => 'text', 'hint' => 'پیش‌فرض TRC20'],
                ['key' => 'offlinearze_tron_coin', 'label' => 'نام ارز', 'input' => 'text', 'hint' => 'پیش‌فرض TRON'],
                ['key' => 'offlinearze_tron_receipt_template', 'label' => 'قالب رسید آفلاین', 'input' => 'textarea', 'hint' => 'متغیرها: {order} {wallet} {amount} {toman} {network} {coin} — خالی = پیش‌فرض'],
            ],
        ],
        [
            'id' => 'star',
            'label' => 'استار تلگرام',
            'toggle' => ['key' => 'statusstar', 'type' => 'toggle01', 'on' => '1', 'off' => '0'],
            'fields' => [],
        ],
    ];
}

function mirza_pay_general_defs(): array
{
    return [
        ['key' => 'statuscardautoconfirm', 'label' => 'تأیید خودکار SMS', 'input' => 'select', 'options' => ['onautoconfirm' => 'روشن', 'offautoconfirm' => 'خاموش'], 'hint' => 'پیامک واریز بانک → کانال تلگرام → تطبیق با مبلغ ریالی فاکتور'],
        ['key' => 'cardreceiptdelaymin', 'label' => 'تأخیر دکمه «ارسال رسید» (دقیقه)', 'input' => 'number', 'hint' => 'پیش‌فرض ۱۰ — تا قبل از آن دکمه رسید نمایش داده نمی‌شود (۱ تا ۱۴۴۰)'],
        ['key' => 'cardpendingexpirehours', 'label' => 'انقضای فاکتور کارت تأییدنشده (ساعت)', 'input' => 'number', 'hint' => 'پیش‌فرض ۴۸ — بعد از این مدت فاکتور Unpaid/waiting منقضی می‌شود و کاربر می‌تواند دوباره پرداخت کند (۶ تا ۱۶۸)'],
        ['key' => 'card_autoconfirm_mode', 'label' => 'حالت cron کارت', 'input' => 'select', 'options' => ['both' => 'رسید + خودکار', 'receipt_only' => 'فقط دکمه رسید', 'auto_only' => 'فقط تأیید خودکار'], 'hint' => 'Mirza 0.2.2 auto-confirm + ViraNaut SMS receipt'],
        ['key' => 'card_sms_telegram_group_id', 'label' => 'آیدی کانال SMS', 'input' => 'text', 'hint' => 'کانال خصوصی → ربات فروش + SMS Forwarder ادمین کانال → Forwarder مقصد = همین کانال. آیدی از @IDFindeerBot در کانال.'],
        ['key' => 'chashbackcart', 'label' => 'کش‌بک کارت (درصد)', 'input' => 'number'],
        ['key' => 'minbalancebuybulk', 'label' => 'حداقل موجودی خرید عمده', 'input' => 'number'],
        ['key' => 'statuscopycart', 'label' => 'دکمه کپی کارت', 'input' => 'select', 'options' => ['1' => 'فعال', '0' => 'غیرفعال']],
        ['key' => 'paymentstatussnotverify', 'label' => 'وضعیت پرداخت بدون تأیید', 'input' => 'text'],
    ];
}

function mirza_pay_get_value(PDO $pdo, string $key): string
{
    $row = db_fetch($pdo, 'SELECT ValuePay FROM PaySetting WHERE NamePay = ?', [$key]);
    return $row ? (string) ($row['ValuePay'] ?? '') : '';
}

function mirza_pay_set_value(PDO $pdo, string $key, string $value): void
{
    $exists = db_count($pdo, 'SELECT COUNT(*) FROM PaySetting WHERE NamePay = ?', [$key]);
    if ($exists > 0) {
        db_query($pdo, 'UPDATE PaySetting SET ValuePay = ? WHERE NamePay = ?', [$value, $key]);
    } else {
        db_query($pdo, 'INSERT INTO PaySetting (NamePay, ValuePay) VALUES (?, ?)', [$key, $value]);
    }
}

function mirza_pay_is_on(array $def, string $val): bool
{
    if (($def['type'] ?? '') === 'toggle01') {
        return $val === '1' || $val === 1;
    }
    return isset($def['on']) && (string) $val === (string) $def['on'];
}

function mirza_pay_toggle_next(array $def, string $val): string
{
    return mirza_pay_is_on($def, $val) ? (string) $def['off'] : (string) $def['on'];
}

function mirza_pay_secret_defs(): array
{
    return [];
}
