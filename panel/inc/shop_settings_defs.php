<?php

function mirza_shop_toggle_options(): array
{
    return [
        'statusextra' => ['onextra' => 'فعال', 'offextra' => 'غیرفعال'],
        'statusdirectpabuy' => ['ondirectbuy' => 'فعال', 'offdirectbuy' => 'غیرفعال'],
        'statustimeextra' => ['ontimeextraa' => 'فعال', 'offtimeextraa' => 'غیرفعال'],
        'statusdisorder' => ['ondisorder' => 'فعال', 'offdisorder' => 'غیرفعال'],
        'statuschangeservice' => ['onstatus' => 'فعال', 'offstatus' => 'غیرفعال'],
        'statusshowprice' => ['onshowprice' => 'فعال', 'offshowprice' => 'غیرفعال'],
        'configshow' => ['onconfig' => 'فعال', 'offconfig' => 'غیرفعال'],
        'backserviecstatus' => ['on' => 'فعال', 'off' => 'غیرفعال'],
        'statuscategorygenral' => ['oncategorys' => 'فعال', 'offcategorys' => 'غیرفعال'],
        'statuscategory' => ['oncategory' => 'فعال', 'offcategory' => 'غیرفعال'],
    ];
}

function mirza_shop_settings_groups(): array
{
    $t = mirza_shop_toggle_options();
    return [
        'قابلیت‌های فروشگاه' => [
            'desc' => 'همان گزینه‌های «وضعیت قابلیت‌های فروشگاه» در ادمین تلگرام',
            'shop' => [
                'statusextra' => ['label' => 'حجم اضافه (Extra Volume)', 'options' => $t['statusextra']],
                'statusdirectpabuy' => ['label' => 'خرید مستقیم', 'options' => $t['statusdirectpabuy']],
                'statustimeextra' => ['label' => 'زمان اضافه', 'options' => $t['statustimeextra']],
                'statusdisorder' => ['label' => 'گزارش اختلال', 'options' => $t['statusdisorder']],
                'statuschangeservice' => ['label' => 'غیرفعال‌سازی اکانت', 'options' => $t['statuschangeservice']],
                'statusshowprice' => ['label' => 'نمایش قیمت محصول', 'options' => $t['statusshowprice']],
                'configshow' => ['label' => 'دکمه دریافت کانفیگ', 'options' => $t['configshow']],
                'backserviecstatus' => ['label' => 'دکمه بازگشت وجه', 'options' => $t['backserviecstatus']],
            ],
            'setting' => [
                'statuscategorygenral' => ['label' => 'دسته‌بندی محصولات', 'options' => $t['statuscategorygenral']],
                'statuscategory' => ['label' => 'دسته‌بندی بر اساس زمان', 'options' => $t['statuscategory']],
            ],
        ],
        'قیمت‌گذاری سفارشی' => [
            'desc' => 'مبالغ پیش‌فرض حجم/زمان اضافه (جدول shopSetting)',
            'numeric_shop' => [
                'customvolmef' => 'قیمت حجم اضافه (فروشنده)',
                'customvolmen' => 'قیمت حجم اضافه (عادی)',
                'customvolmen2' => 'قیمت حجم اضافه (عادی ۲)',
                'customtimepricef' => 'قیمت زمان اضافه (فروشنده)',
                'customtimepricen' => 'قیمت زمان اضافه (عادی)',
                'customtimepricen2' => 'قیمت زمان اضافه (عادی ۲)',
                'minbalancebuybulk' => 'حداقل موجودی خرید عمده',
                'chashbackextend' => 'کش‌بک تمدید (درصد)',
            ],
        ],
    ];
}

function mirza_shop_load_values(PDO $pdo): array
{
    $shop = [];
    try {
        $rows = db_fetchAll($pdo, 'SELECT Namevalue, value FROM shopSetting');
        foreach ($rows as $r) {
            $shop[$r['Namevalue']] = $r['value'];
        }
    } catch (Exception $e) {
        error_log('shop settings load: ' . $e->getMessage());
    }
    $setting = db_fetch($pdo, 'SELECT statuscategorygenral, statuscategory FROM setting LIMIT 1') ?? [];
    return ['shop' => $shop, 'setting' => $setting];
}
