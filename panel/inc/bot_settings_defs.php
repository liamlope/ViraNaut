<?php

function mirza_bot_toggle_opts(string $on, string $off): array
{
    return ['type' => 'toggle', 'options' => [$on => 'فعال', $off => 'غیرفعال']];
}

function mirza_bot_toggle01_opts(): array
{
    return ['type' => 'toggle', 'options' => ['1' => 'فعال', '0' => 'غیرفعال']];
}

function mirza_bot_settings_groups(): array
{
    return [
        'وضعیت و دسترسی' => [
            'icon' => 'bot',
            'desc' => 'روشن/خاموش، قوانین، احراز هویت',
            'fields' => [
                'Bot_Status' => array_merge(['label' => 'وضعیت ربات'], mirza_bot_toggle_opts('botstatuson', 'botstatusoff')),
                'NotUser' => array_merge(['label' => 'دکمه نام کاربری'], mirza_bot_toggle_opts('onnotuser', 'offnotuser')),
                'roll_Status' => array_merge(['label' => 'تأیید قوانین'], mirza_bot_toggle_opts('rolleon', 'rolleoff')),
                'get_number' => array_merge(['label' => 'احراز شماره'], mirza_bot_toggle_opts('onAuthenticationphone', 'offAuthenticationphone')),
                'iran_number' => array_merge(['label' => 'فقط شماره ایرانی'], mirza_bot_toggle_opts('onAuthenticationiran', 'offAuthenticationiran')),
                'inlinebtnmain' => array_merge(['label' => 'دکمه‌های اینلاین'], mirza_bot_toggle_opts('oninline', 'offinline')),
                'verifystart' => array_merge(['label' => 'احراز هویت'], mirza_bot_toggle_opts('onverify', 'offverify')),
                'statussupportpv' => array_merge(['label' => 'پشتیبانی در پیوی'], mirza_bot_toggle_opts('onpvsupport', 'offpvsupport')),
                'verifybucodeuser' => array_merge(['label' => 'احراز با لینک'], mirza_bot_toggle_opts('onverify', 'offverify')),
            ],
        ],
        'کاربران و فروش' => [
            'icon' => 'users',
            'fields' => [
                'statusnewuser' => array_merge(['label' => 'هدیه کاربر جدید'], mirza_bot_toggle_opts('onnewuser', 'offnewuser')),
                'statusagentrequest' => array_merge(['label' => 'درخواست نمایندگی'], mirza_bot_toggle_opts('onrequestagent', 'offrequestagent')),
                'affiliatesstatus' => array_merge(['label' => 'زیرمجموعه'], mirza_bot_toggle_opts('onaffiliates', 'offaffiliates')),
                'affiliatespercentage' => ['label' => 'درصد زیرمجموعه', 'type' => 'number'],
                'limit_usertest_all' => ['label' => 'محدودیت اکانت تست (کل)', 'type' => 'number'],
                'bulkbuy' => array_merge(['label' => 'خرید عمده'], mirza_bot_toggle_opts('onbulk', 'offbulk')),
                'statuscopycart' => array_merge(['label' => 'کپی شماره کارت'], mirza_bot_toggle01_opts()),
            ],
        ],
        'سرویس و کانفیگ' => [
            'icon' => 'server',
            'fields' => [
                'statusnamecustom' => array_merge(['label' => 'یادداشت کانفیگ'], mirza_bot_toggle_opts('onnamecustom', 'offnamecustom')),
                'statusnoteforf' => array_merge(['label' => 'یادداشت کاربر عادی'], mirza_bot_toggle01_opts()),
                'status_keyboard_config' => array_merge(['label' => 'کیبورد کانفیگی'], mirza_bot_toggle01_opts()),
                'statuslimitchangeloc' => array_merge(['label' => 'محدودیت تغییر لوکیشن'], mirza_bot_toggle01_opts()),
                'categoryhelp' => array_merge(['label' => 'دسته‌بندی آموزش'], mirza_bot_toggle01_opts()),
                'linkappstatus' => array_merge(['label' => 'لینک دانلود اپ'], mirza_bot_toggle01_opts()),
            ],
        ],
        'گزارش و پشتیبانی' => [
            'icon' => 'settings',
            'fields' => [
                'Channel_Report' => ['label' => 'کانال گزارش', 'type' => 'text', 'hint' => '-100...'],
                'id_support' => ['label' => 'آیدی پشتیبانی', 'type' => 'text'],
                'volumewarn' => ['label' => 'هشدار حجم (GB)', 'type' => 'number'],
            ],
        ],
        'قرعه‌کشی و گردونه' => [
            'icon' => 'chart',
            'fields' => [
                'wheelـluck' => array_merge(['label' => 'گردونه شانس'], mirza_bot_toggle01_opts()),
                'wheelagent' => array_merge(['label' => 'گردونه نمایندگان'], mirza_bot_toggle01_opts()),
                'statusfirstwheel' => array_merge(['label' => 'گردونه خرید اول'], mirza_bot_toggle01_opts()),
                'Lotteryagent' => array_merge(['label' => 'قرعه‌کشی نمایندگان'], mirza_bot_toggle01_opts()),
                'scorestatus' => array_merge(['label' => 'قرعه‌کشی شبانه'], mirza_bot_toggle01_opts()),
                'Dice' => array_merge(['label' => 'نمایش تاس'], mirza_bot_toggle01_opts()),
                'Debtsettlement' => array_merge(['label' => 'تسویه بدهی'], mirza_bot_toggle01_opts()),
            ],
        ],
        'زبان' => [
            'icon' => 'edit',
            'fields' => [
                'languageen' => array_merge(['label' => 'زبان انگلیسی'], mirza_bot_toggle01_opts()),
                'languageru' => array_merge(['label' => 'زبان روسی'], mirza_bot_toggle01_opts()),
            ],
        ],
    ];
}

function mirza_bot_cron_defs(): array
{
    return [
        'test' => 'کرون اکانت تست',
        'day' => 'کرون زمان (هشدار)',
        'volume' => 'کرون حجم',
        'remove' => 'کرون حذف سرویس',
        'remove_volume' => 'کرون حذف حجم',
        'uptime_node' => 'آپتایم نود',
        'uptime_panel' => 'آپتایم پنل',
        'on_hold' => 'اولین اتصال',
    ];
}

function mirza_bot_settings_flat_fields(): array
{
    $flat = [];
    foreach (mirza_bot_settings_groups() as $group) {
        foreach ($group['fields'] as $col => $meta) {
            $flat[$col] = $meta;
        }
    }
    return $flat;
}
