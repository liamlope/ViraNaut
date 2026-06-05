<?php

function mirza_miniapp_template_ids(): array
{
    return ['midnight', 'aurora', 'emerald', 'sunset', 'ocean'];
}

function mirza_miniapp_templates(): array
{
    return [
        'midnight' => [
            'id' => 'midnight',
            'label' => 'نیمه‌شب',
            'layout' => 'dock-grid',
            'desc' => 'داک ۴ تب پایین + گرید واکنش‌گرا + شارژ از ربات و خرید درون‌اپ',
            'accent' => '#22d3ee',
            'accent2' => '#6366f1',
            'bg' => '#0f172a',
            'features' => [
                'داک پایین ۴ بخش با نشانگر فعال',
                'گرید ۲ ستونه (۱ ستون در موبایل باریک)',
                'دکمه شارژ — هدایت مستقیم به ربات',
                'خرید یک‌کلیکی + تأیید در sheet',
                'جزئیات سرویس + کپی لینک اشتراک',
                'نوار موجودی چسبان بالا',
                'فیلتر کشور/پنل با chip',
                'کد دعوت + کپی و اشتراک',
                'کشیدن برای بروزرسانی (pull-to-refresh)',
                'اسکلتون لود هنگام بارگذاری',
            ],
        ],
        'aurora' => [
            'id' => 'aurora',
            'label' => 'شفق قطبی',
            'layout' => 'vertical-feed',
            'desc' => 'فید عمودی موبایل‌اول + تب پایین + chip کشور + جستجو',
            'accent' => '#c084fc',
            'accent2' => '#f472b6',
            'bg' => '#0c0a1f',
            'features' => [
                'فید عمودی تمام‌عرض (بدون اسکرول افقی)',
                'ناوبری پایین native-style + safe-area',
                'هیرو فشرده + موجودی زنده',
                'chip انتخاب کشور/پنل',
                'کارت پلن عمودی با badge و قیمت',
                'تب پیشنهاد با پلن‌های دارای تخفیف',
                'جستجوی سریع در پلن‌ها',
                'خرید درون‌اپ + شارژ از ربات',
                'پروفایل با نام/عکس تلگرام',
                'جزئیات سرویس در bottom-sheet',
            ],
        ],
        'emerald' => [
            'id' => 'emerald',
            'label' => 'زمرد',
            'layout' => 'dashboard-table',
            'desc' => 'داشبورد KPI + کارت سرویس موبایل + منوی پایین',
            'accent' => '#34d399',
            'accent2' => '#14b8a6',
            'bg' => '#022c22',
            'features' => [
                'داشبورد ۳ KPI (موجودی / سرویس / پرداخت)',
                'نوار پیشرفت حجم در هر سرویس',
                'جدول دسکتاپ / کارت موبایل',
                'منوی پایین ۳ بخش + شارژ در ربات',
                'فیلتر وضعیت (فعال / هشدار / منقضی)',
                'خرید سریع از داشبورد',
                'جزئیات سرویس + کپی',
                'هشدار انقضای نزدیک',
                'دکمه شارژ در ربات از داشبورد',
                'pull-to-refresh داشبورد',
            ],
        ],
        'sunset' => [
            'id' => 'sunset',
            'label' => 'غروب',
            'layout' => 'scroll-story',
            'desc' => 'اسکرول داستان‌وار + پلن featured + CTA چسبان',
            'accent' => '#fb923c',
            'accent2' => '#f43f5e',
            'bg' => '#1c1017',
            'features' => [
                'اسکرول داستان‌وار عمودی',
                'پلن featured تمام‌عرض',
                'CTA چسبان پایین با safe-area',
                'نظرات کاربر (کاروسل عمودی)',
                'تایم‌لاین انتخاب → پرداخت → فعال',
                'نمایش badge تخفیف روی پلن',
                'خرید یک‌ضربه پلن پیشنهادی',
                'دسترسی سریع به سرویس‌ها',
                'کد دعوت + کپی',
                'هدر گرادیان + نوار موجودی',
            ],
        ],
        'ocean' => [
            'id' => 'ocean',
            'label' => 'اقیانوس',
            'layout' => 'search-list',
            'desc' => 'جستجوی زنده + فیلتر + مرتب‌سازی + لیست فشرده',
            'accent' => '#38bdf8',
            'accent2' => '#0ea5e9',
            'bg' => '#0a1628',
            'features' => [
                'جستجوی زنده نام/کشور',
                'فیلتر دسته از دسته‌بندی محصولات',
                'لیست فشرده تمام‌عرض',
                'مرتب‌سازی قیمت و حجم',
                'صفحه بیشتر: پشتیبانی و دعوت',
                'pull-to-refresh',
                'فیلتر کشور با chip',
                'خرید از ردیف لیست',
                'حالت خالی با راهنما',
                'ناوبری ۳+۱ مینیمال',
            ],
        ],
    ];
}

function mirza_miniapp_template_valid(string $id): bool
{
    return in_array($id, mirza_miniapp_template_ids(), true);
}

function mirza_miniapp_get_template(?PDO $pdo = null): string
{
    $default = 'midnight';
    try {
        $v = '';
        if ($pdo instanceof PDO) {
            $st = $pdo->query("SELECT value FROM shopSetting WHERE Namevalue = 'miniapp_template' LIMIT 1");
            $row = $st ? $st->fetch(PDO::FETCH_ASSOC) : false;
            $v = trim((string) ($row['value'] ?? ''));
        } elseif (function_exists('select')) {
            $row = select('shopSetting', 'value', 'Namevalue', 'miniapp_template', 'select');
            $v = trim((string) ($row['value'] ?? ''));
        }
        return mirza_miniapp_template_valid($v) ? $v : $default;
    } catch (Throwable $e) {
        return $default;
    }
}

function mirza_miniapp_set_template(PDO $pdo, string $id): void
{
    if (!mirza_miniapp_template_valid($id)) {
        throw new InvalidArgumentException('قالب نامعتبر است');
    }
    $st = $pdo->query("SELECT COUNT(*) FROM shopSetting WHERE Namevalue = 'miniapp_template'");
    $cnt = $st ? (int) $st->fetchColumn() : 0;
    if ($cnt > 0) {
        $pdo->prepare('UPDATE shopSetting SET value = ? WHERE Namevalue = ?')->execute([$id, 'miniapp_template']);
    } else {
        $pdo->prepare('INSERT INTO shopSetting (Namevalue, value) VALUES (?, ?)')->execute(['miniapp_template', $id]);
    }
}

function mirza_miniapp_preview_url(string $id, ?string $domain = null): string
{
    if (!mirza_miniapp_template_valid($id)) {
        $id = 'midnight';
    }
    $host = $domain ?: 'localhost';
    return 'https://' . $host . '/app/?tpl_preview=' . rawurlencode($id) . '&demo=1';
}

function mirza_miniapp_all_features_list(): array
{
    $out = [];
    foreach (mirza_miniapp_templates() as $t) {
        $out[$t['id']] = $t['features'];
    }
    return $out;
}
