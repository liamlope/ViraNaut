<?php

/**
 * کاتالوگ متن‌های قابل ویرایش ربات (textbot.id_text).
 * هر آیتم: label, hint?, vars?, rows?
 */
function vira_panel_textbot_global_vars(): array
{
    return [
        ['{username}', 'نام کاربری تلگرام (@…)'],
        ['{first_name}', 'نام اکانت'],
        ['{last_name}', 'نام خانوادگی اکانت'],
        ['{time}', 'زمان فعلی'],
        ['{version}', 'نسخه ربات'],
        ['{userBalance}', 'موجودی کیف پول'],
        ['{price}', 'مبلغ'],
        ['{name_product}', 'نام محصول'],
        ['{Service_time}', 'مدت سرویس (روز)'],
        ['{Volume}', 'حجم (گیگ)'],
        ['{note}', 'یادداشت محصول'],
        ['{location}', 'موقعیت / پنل'],
        ['{config}', 'لینک کانفیگ'],
        ['{links}', 'لینک‌های اضافه'],
        ['{card_number}', 'شماره کارت'],
        ['{name_card}', 'نام دارنده کارت'],
    ];
}

function vira_panel_textbot_catalog(): array
{
    return [
        'منوی اصلی و استارت' => [
            'text_start' => [
                'label' => 'پیام خوش‌آمد (استارت)',
                'hint' => 'اولین پیام بعد از /start',
                'vars' => ['{first_name}', '{last_name}', '{username}', '{time}', '{version}'],
                'rows' => 10,
            ],
            'text_bot_off' => [
                'label' => 'ربات خاموش',
                'rows' => 2,
            ],
            'text_channel' => [
                'label' => 'عضویت اجباری کانال',
                'rows' => 6,
            ],
            'text_roll' => [
                'label' => 'قوانین استفاده',
                'rows' => 8,
            ],
        ],
        'دکمه‌های کیبورد' => [
            'text_sell' => ['label' => 'دکمه خرید اشتراک', 'rows' => 1],
            'text_extend' => ['label' => 'دکمه تمدید سرویس', 'rows' => 1],
            'text_usertest' => ['label' => 'دکمه اکانت تست', 'rows' => 1],
            'text_Purchased_services' => ['label' => 'دکمه سرویس‌های من', 'rows' => 1],
            'text_help' => ['label' => 'دکمه آموزش', 'rows' => 1],
            'text_support' => ['label' => 'دکمه پشتیبانی', 'rows' => 1],
            'accountwallet' => ['label' => 'دکمه کیف پول', 'rows' => 1],
            'text_affiliates' => ['label' => 'دکمه زیرمجموعه', 'rows' => 1],
            'text_Tariff_list' => ['label' => 'دکمه لیست تعرفه', 'rows' => 1],
            'text_dec_Tariff_list' => ['label' => 'متن توضیح لیست تعرفه', 'rows' => 6],
            'text_wheel_luck' => ['label' => 'دکمه گردونه شانس', 'rows' => 1],
            'text_Discount' => ['label' => 'دکمه کد هدیه', 'rows' => 1],
            'text_Add_Balance' => ['label' => 'دکمه افزایش موجودی', 'rows' => 1],
            'text_Account_op' => ['label' => 'دکمه حساب کاربری', 'rows' => 1],
            'text_fq' => ['label' => 'دکمه سوالات متداول', 'rows' => 1],
            'text_dec_fq' => ['label' => 'متن سوالات متداول', 'rows' => 12],
        ],
        'فرایند خرید' => [
            'textselectlocation' => [
                'label' => 'انتخاب موقعیت / پنل',
                'hint' => 'قبل از انتخاب محصول',
                'rows' => 2,
            ],
            'text_select_category' => [
                'label' => 'انتخاب دسته‌بندی محصول',
                'hint' => 'وقتی دسته‌بندی فروشگاه روشن است',
                'rows' => 2,
            ],
            'text_service_select_first' => [
                'label' => 'انتخاب سرویس (اولین مرحله)',
                'rows' => 2,
            ],
            'text_service_select' => [
                'label' => 'انتخاب سرویس / محصول',
                'rows' => 2,
            ],
            'text_sell_notestep' => [
                'label' => 'درخواست نام دلخواه سرویس',
                'rows' => 3,
            ],
            'text_pishinvoice' => [
                'label' => 'پیش‌فاکتور',
                'vars' => ['{username}', '{name_product}', '{Service_time}', '{price}', '{Volume}', '{note}', '{userBalance}'],
                'rows' => 12,
            ],
        ],
        'پس از پرداخت و تحویل' => [
            'textafterpay' => [
                'label' => 'پس از خرید موفق (عادی)',
                'vars' => ['{username}', '{name_service}', '{location}', '{day}', '{volume}', '{config}', '{links}'],
                'rows' => 12,
            ],
            'textafterpayibsng' => [
                'label' => 'پس از خرید (IBSng)',
                'vars' => ['{username}', '{password}', '{name_service}', '{location}', '{day}', '{volume}'],
                'rows' => 10,
            ],
            'textaftertext' => [
                'label' => 'پس از خرید (اکانت تست / ساعت)',
                'vars' => ['{username}', '{name_service}', '{location}', '{day}', '{volume}', '{config}'],
                'rows' => 10,
            ],
            'textmanual' => [
                'label' => 'تحویل دستی (Manual)',
                'vars' => ['{username}', '{name_service}', '{location}', '{config}'],
                'rows' => 8,
            ],
            'text_wgdashboard' => [
                'label' => 'تحویل WireGuard Dashboard',
                'vars' => ['{username}', '{name_service}', '{location}', '{day}', '{volume}'],
                'rows' => 8,
            ],
            'crontest' => [
                'label' => 'پایان اکانت تست',
                'vars' => ['{username}'],
                'rows' => 6,
            ],
        ],
        'پرداخت و درگاه‌ها' => [
            'text_cart' => [
                'label' => 'راهنمای کارت به کارت',
                'vars' => ['{price}', '{card_number}', '{name_card}'],
                'rows' => 12,
            ],
            'text_cart_auto' => [
                'label' => 'کارت به کارت خودکار',
                'vars' => ['{price}', '{card_number}', '{name_card}'],
                'rows' => 12,
            ],
            'carttocart' => ['label' => 'عنوان دکمه کارت به کارت', 'rows' => 1],
            'zarinpal' => ['label' => 'عنوان زرین‌پال', 'rows' => 1],
            'aqayepardakht' => ['label' => 'عنوان آقای پرداخت', 'rows' => 1],
            'textnowpayment' => ['label' => 'عنوان ارز دیجیتال ۱', 'rows' => 1],
            'textnowpaymenttron' => ['label' => 'عنوان واریز ترون', 'rows' => 1],
            'textsnowpayment' => ['label' => 'عنوان NowPayment', 'rows' => 1],
            'mowpayment' => ['label' => 'عنوان MowPayment', 'rows' => 1],
            'iranpay1' => ['label' => 'درگاه ریالی ۱', 'rows' => 1],
            'iranpay2' => ['label' => 'درگاه ریالی ۲', 'rows' => 1],
            'iranpay3' => ['label' => 'درگاه ریالی ۳', 'rows' => 1],
            'textpaymentnotverify' => ['label' => 'درگاه ریالی (تأیید نشده)', 'rows' => 1],
            'text_star_telegram' => ['label' => 'پرداخت استار تلگرام', 'rows' => 1],
        ],
        'نمایندگی' => [
            'textrequestagent' => ['label' => 'دکمه درخواست نمایندگی', 'rows' => 1],
            'textpanelagent' => ['label' => 'دکمه پنل نمایندگی', 'rows' => 1],
            'text_request_agent_dec' => ['label' => 'توضیح درخواست نمایندگی', 'rows' => 3],
        ],
    ];
}

function vira_panel_textbot_groups(): array
{
    $out = [];
    foreach (vira_panel_textbot_catalog() as $group => $items) {
        foreach ($items as $id => $meta) {
            $out[$group][$id] = $meta['label'];
        }
    }
    return $out;
}

function vira_panel_textbot_meta(string $id): array
{
    foreach (vira_panel_textbot_catalog() as $items) {
        if (isset($items[$id])) {
            return $items[$id];
        }
    }
    return ['label' => $id, 'rows' => 4];
}

function vira_panel_textbot_defaults_extra(): array
{
    return [
        ['text_select_category', '📌 دسته بندی خود را انتخاب نمایید!'],
        ['text_service_select', '📌 سرویس مورد نظر خود را انتخاب نمایید'],
        ['text_service_select_first', '📌 ابتدا سرویس مورد نظر را انتخاب کنید'],
        ['text_sell_notestep', '📝 نام دلخواه سرویس خود را ارسال کنید'],
        ['text_Account_op', '🎛 حساب کاربری'],
    ];
}
