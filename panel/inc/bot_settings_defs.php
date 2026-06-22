<?php

function vira_bot_toggle_opts(string $on, string $off): array
{
    return ['type' => 'toggle', 'options' => [$on => 'فعال', $off => 'غیرفعال']];
}

function vira_bot_toggle01_opts(): array
{
    return ['type' => 'toggle', 'options' => ['1' => 'فعال', '0' => 'غیرفعال']];
}

function vira_bot_settings_groups(): array
{
    return [
        'وضعیت و دسترسی' => [
            'icon' => 'bot',
            'desc' => 'روشن/خاموش، قوانین، احراز هویت',
            'fields' => [
                'Bot_Status' => array_merge(['label' => 'وضعیت ربات'], vira_bot_toggle_opts('botstatuson', 'botstatusoff')),
                'NotUser' => array_merge(['label' => 'دکمه نام کاربری'], vira_bot_toggle_opts('onnotuser', 'offnotuser')),
                'roll_Status' => array_merge(['label' => 'تأیید قوانین'], vira_bot_toggle_opts('rolleon', 'rolleoff')),
                'get_number' => array_merge(['label' => 'احراز شماره'], vira_bot_toggle_opts('onAuthenticationphone', 'offAuthenticationphone')),
                'iran_number' => array_merge(['label' => 'فقط شماره ایرانی'], vira_bot_toggle_opts('onAuthenticationiran', 'offAuthenticationiran')),
                'inlinebtnmain' => array_merge(['label' => 'دکمه‌های اینلاین'], vira_bot_toggle_opts('oninline', 'offinline')),
                'verifystart' => array_merge(['label' => 'احراز هویت'], vira_bot_toggle_opts('onverify', 'offverify')),
                'statussupportpv' => array_merge(['label' => 'پشتیبانی در پیوی'], vira_bot_toggle_opts('onpvsupport', 'offpvsupport')),
                'verifybucodeuser' => array_merge(['label' => 'احراز با لینک'], vira_bot_toggle_opts('onverify', 'offverify')),
            ],
        ],
        'کاربران و فروش' => [
            'icon' => 'users',
            'fields' => [
                'statusnewuser' => array_merge(['label' => 'هدیه کاربر جدید'], vira_bot_toggle_opts('onnewuser', 'offnewuser')),
                'statusagentrequest' => array_merge(['label' => 'درخواست نمایندگی'], vira_bot_toggle_opts('onrequestagent', 'offrequestagent')),
                'affiliatesstatus' => array_merge(['label' => 'زیرمجموعه'], vira_bot_toggle_opts('onaffiliates', 'offaffiliates')),
                'affiliatespercentage' => ['label' => 'درصد زیرمجموعه', 'type' => 'number'],
                'limit_usertest_all' => ['label' => 'محدودیت اکانت تست (کل)', 'type' => 'number'],
                'bulkbuy' => array_merge(['label' => 'خرید عمده'], vira_bot_toggle_opts('onbulk', 'offbulk')),
                'statuscopycart' => array_merge(['label' => 'کپی شماره کارت'], vira_bot_toggle01_opts()),
            ],
        ],
        'سرویس و کانفیگ' => [
            'icon' => 'server',
            'fields' => [
                'statusnamecustom' => array_merge(['label' => 'یادداشت کانفیگ'], vira_bot_toggle_opts('onnamecustom', 'offnamecustom')),
                'statusnoteforf' => array_merge(['label' => 'یادداشت کاربر عادی'], vira_bot_toggle01_opts()),
                'status_keyboard_config' => array_merge(['label' => 'کیبورد کانفیگی'], vira_bot_toggle01_opts()),
                'statuslimitchangeloc' => array_merge(['label' => 'محدودیت تغییر لوکیشن'], vira_bot_toggle01_opts()),
                'categoryhelp' => array_merge(['label' => 'دسته‌بندی آموزش'], vira_bot_toggle01_opts()),
                'linkappstatus' => array_merge(['label' => 'لینک دانلود اپ'], vira_bot_toggle01_opts()),
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
                'wheelـluck' => array_merge(['label' => 'گردونه شانس'], vira_bot_toggle01_opts()),
                'wheelagent' => array_merge(['label' => 'گردونه نمایندگان'], vira_bot_toggle01_opts()),
                'statusfirstwheel' => array_merge(['label' => 'گردونه خرید اول'], vira_bot_toggle01_opts()),
                'Lotteryagent' => array_merge(['label' => 'قرعه‌کشی نمایندگان'], vira_bot_toggle01_opts()),
                'scorestatus' => array_merge(['label' => 'قرعه‌کشی شبانه'], vira_bot_toggle01_opts()),
                'Dice' => array_merge(['label' => 'نمایش تاس'], vira_bot_toggle01_opts()),
                'Debtsettlement' => array_merge(['label' => 'تسویه بدهی'], vira_bot_toggle01_opts()),
            ],
        ],
    ];
}

function vira_bot_cron_defs(): array
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

function vira_bot_settings_flat_fields(): array
{
    $flat = [];
    foreach (vira_bot_settings_groups() as $group) {
        foreach ($group['fields'] as $col => $meta) {
            $flat[$col] = $meta;
        }
    }
    return $flat;
}
