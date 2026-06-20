<div align="center">

# ViraNaut · ویرانات

**ربات تلگرام فروش VPN — متن‌باز، حرفه‌ای، آمادهٔ production**

[![Version](https://img.shields.io/badge/version-3.2.0--ViraNaut-blue?style=for-the-badge)](https://github.com/liamlope/ViraNaut)
[![License](https://img.shields.io/badge/license-MIT-green?style=for-the-badge)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8+-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![Telegram](https://img.shields.io/badge/Telegram-Bot-26A5E4?style=for-the-badge&logo=telegram&logoColor=white)](https://telegram.org)

Fork پیشرفته و رایگان از [Mirza Bot Free](https://github.com/mahdiMGF2/mirzabot)  
با **پنل وب ادمین ۳۱+ صفحه** · **پنل وب نمایندگی Pro** · SMS تأیید کارت · مهاجرت یک‌کلیکی

**[⭐ Star on GitHub](https://github.com/liamlope/ViraNaut)** · **[📖 Changelog](CHANGELOG.md)** · **[🔧 Panel Matrix](docs/PANEL_SUPPORT.md)**

</div>

---

## ویرانات چیست؟

**ViraNaut (ویرانات)** یک ربات تلگرام کامل برای فروش VPN است که روی Mirza Free ساخته شده، اما مسیر خودش را رفته: پنل وب مدرن، مدیریت مالی از مرورگر، پنل نمایندگی مستقل، API برنامه‌نویسی، و ابزار نصب/آپدیت یک‌خطی روی Ubuntu.

> مناسب برای یوتیوبرها، فروشندگان VPN، و تیم‌هایی که می‌خواهند **بدون Mirza Pro پولی**، تجربهٔ Pro داشته باشند.

| آدرس | URL |
|------|-----|
| پنل ادمین | `https://YOUR_DOMAIN/panel/` |
| پنل نمایندگی | `https://YOUR_DOMAIN/agent-panel/` |
| API نماینده | `POST /api/agent.php` |
| Site Admin | `https://YOUR_DOMAIN/site-admin/` |

---

## مقایسه Mirza Free و ViraNaut

| قابلیت | Mirza Free `0.2.2` | ViraNaut `3.2.0` |
|--------|:------------------:|:----------------:|
| **لایسنس** | MIT · رایگان | MIT · رایگان (Fork) |
| **ربات تلگرام فروش VPN** | ✅ | ✅ + بهبود UX |
| **پنل وب ادمین** | ~۱۲ صفحه · محدود | **۳۱+ صفحه · کامل** |
| **پنل وب نمایندگی** | ❌ (فقط Mirza Pro) | ✅ **Pro رایگان** |
| **مدیریت کاربر از وب** | پایه | کامل — موجودی، سرویس، تمدید، حجم، revoke |
| **مرکز مالی وب** | ❌ / بسیار محدود | ✅ درگاه، کارت، تأیید رسید، CSV |
| **SMS تأیید خودکار کارت** | ❌ | ✅ dual-mode + کانال SMS |
| **cron تأیید کارت (auto)** | ✅ | ✅ `both` · `receipt_only` · `auto_only` |
| **چندزبانه per-user** | ✅ fa/en/ru/zh | ✅ |
| **ویرایش متن ربات از وب** | محدود | ✅ `bot-texts` + `text.json` |
| **چیدمان منو / ایموجی پرمیوم** | از تلگرام | ✅ از پنل وب |
| **مدیریت پنل VPN از وب** | محدود | ✅ Marzban، x-ui، Hiddify، … |
| **درایور ilan** | ❌ | ✅ REST generic |
| **mirza_agent hooks** | partial | ✅ reset usage + sync |
| **Pasarguard** | partial | ✅ alias کامل |
| **site-admin** | ❌ | ✅ درخواست + پاسخ از وب |
| **API Bearer نماینده** | ❌ | ✅ ۱۵+ action |
| **vpnbot (ربات فروش نماینده)** | ✅ | ✅ + sync + دکمه پنل وب |
| **مینی‌اپ تلگرام** | ✅ | ✅ + قالب از وب |
| **Chart.js داشبورد** | ❌ | ✅ ادمین + نماینده |
| **Export CSV** | محدود | ✅ کاربر، مالی، گزارش |
| **2FA پنل نمایندگی** | ❌ | ✅ تلگرام OTP |
| **اسکریپت نصب یک‌خطی** | ❌ | ✅ `ViraNaut_manage.sh` |
| **مهاجرت از Mirza** | — | ✅ یک‌کلیک · DB حفظ |
| **بکاپ خودکار قبل آپدیت** | ❌ | ✅ ZIP |
| **Diagnose + Auto-fix** | ❌ | ✅ |
| **جستجوی سریع کاربر `/id`** | ✅ | ✅ + فرمت‌های `/username` `/t.me/…` |
| **TRON آفلاین + قالب رسید** | basic | ✅ قابل تنظیم از پنل |
| **تعداد درایور پنل VPN** | ~۱۰ | **۱۴ نوع** ([ماتریس](docs/PANEL_SUPPORT.md)) |

**خلاصه:** Mirza Free ربات قوی تلگرام است. ViraNaut همان هسته را نگه می‌دارد و **لایهٔ وب، نمایندگی، API و DevOps** را اضافه می‌کند — بدون paywall.

---

## پنل وب ادمین — لیست کامل قابلیت‌ها

> مسیر: `/panel/` · UI: sidebar · bottom-nav موبایل · تم تیره/روشن · RTL

### پیشخوان
| صفحه | قابلیت‌ها |
|------|-----------|
| **داشبورد** | آمار لحظه‌ای، نمودار فروش، کاربران جدید، وضعیت سیستم |

### مشتریان و سفارش
| صفحه | قابلیت‌ها |
|------|-----------|
| **کاربران** | جستجو، فیلتر وضعیت/نقش (نماینده n/n2)، مسدود، export CSV |
| **مدیریت کاربر** (`user.php`) | موجودی (افزایش/کسر/تنظیم/صفر)، لیست سرویس‌ها، تمدید، حجم، زمان، revoke، لینک جدید، حذف، یادداشت، نقش نماینده |
| **سفارشات** | فاکتورها، فیلتر، جزئیات خرید |
| **سرویس‌های دستی** | استخر Manualsale، تخصیص دستی |

### فروشگاه
| صفحه | قابلیت‌ها |
|------|-----------|
| **محصولات** | CRUD محصول، حجم/زمان/قیمت، پنل، دسته نماینده |
| **تنظیمات فروشگاه** | قوانین فروش، محدودیت‌ها، گزینه‌های خرید |
| **قالب مینی‌اپ** | ویرایش قالب Telegram Mini App از وب |

### مالی
| صفحه | قابلیت‌ها |
|------|-----------|
| **مرکز مالی** | آمار پرداخت، رسید در انتظار، تأیید/رد، همه تراکنش‌ها |
| | تنظیم درگاه‌ها: زرین‌پال، آقای پرداخت، NowPayments، کارت، TRX آفلاین |
| | مدیریت شماره کارت، حداقل/حداکثر شارژ، dual-mode cron |
| | SMS auto-confirm، آیدی کانال SMS، فاکتور، کد تخفیف |
| | export CSV · تفکیک روش پرداخت |

### ربات تلگرام
| صفحه | قابلیت‌ها |
|------|-----------|
| **مرکز ربات** | hub تنظیمات ربات |
| **چیدمان منو** | drag-free keyboard editor |
| **ایموجی پرمیوم** | کتابخانه custom emoji |
| **متن‌های ربات** | ویرایش همه متن‌ها (استارت، پرداخت، فروش، …) |
| **پنل‌های VPN** | افزودن/ویرایش/تست اتصال · ۱۴ نوع پنل |
| **اکانت تست** | تنظیم سرویس تست رایگان |

### تنظیمات ربات
| صفحه | قابلیت‌ها |
|------|-----------|
| **تنظیمات عمومی** | توکن، ادمین، دامنه، … |
| **جوین اجباری** | کانال‌های اجباری |
| **گزارش و کانال** | کانال گزارش فروش/خطا |
| **ادمین‌های ربات** | مدیریت سطح دسترسی |
| **ارسال همگانی** | broadcast به کاربران |

### نگهداری
| صفحه | قابلیت‌ها |
|------|-----------|
| **بکاپ** | دانلود/بازیابی |
| **بهینه‌سازی** | پاکسازی DB، cache |
| **مهاجرت DB** | اجرای migration + seed wallet |
| **درباره** | نسخه، لینک GitHub |

### پنل وب
| صفحه | قابلیت‌ها |
|------|-----------|
| **ظاهر و امنیت** | تم، session، CSRF |
| **ورود** | auth + نمایش آدرس‌های حمایت (crypto) |

---

## پنل وب نمایندگی — لیست کامل قابلیت‌ها

> مسیر: `/agent-panel/` · UI هم‌تراز پنل ادمین · موبایل‌فرست · fa/en

### پیشخوان
| صفحه | قابلیت‌ها |
|------|-----------|
| **داشبورد** | موجودی، فروش، بدهی n2، نمودار ۷/۳۰/۹۰ روز (Chart.js) |
| **پروفایل** | اطلاعات حساب، کد دعوت، لینک زیرمجموعه، کپی |

### خرید
| صفحه | قابلیت‌ها |
|------|-----------|
| **خرید سرویس** | انتخاب پنل + محصول، یوزرنیم دلخواه، پیش‌فاکتور |
| **سرویس دلخواه** | حجم + روز دلخواه، قیمت‌گذاری custom |
| **خرید انبوه** | چند اکانت یکجا |
| **اکانت تست** | ساخت تست از پنل‌های مجاز |
| | auto-redirect به **افزایش موجودی** وقتی balance کافی نیست |

### سرویس‌ها
| صفحه | قابلیت‌ها |
|------|-----------|
| **لیست سرویس‌ها** | فیلتر وضعیت/پنل/تاریخ/جستجو، عملیات گروهی تمدید |
| **جزئیات سرویس** | داده live پنل، QR، تمدید، +حجم، +زمان، لینک جدید، ارسال به تلگرام |

### مالی
| صفحه | قابلیت‌ها |
|------|-----------|
| **مرکز مالی** | موجودی، بدهی/سقف n2، افزایش موجودی |
| | درگاه‌های **وب**: زرین‌پال، آقای پرداخت (redirect واقعی StartPay) |
| **تراکنش‌ها** | تاریخچه پرداخت و خرید |
| **تعرفه** | جدول قیمت محصولات و extras |

### نمایندگی
| صفحه | قابلیت‌ها |
|------|-----------|
| **زیرمجموعه** | لیست referral، لینک دعوت |
| **تنظیمات n2** | تعرفه اختصاصی (read-only)، انقضای نمایندگی |
| **گزارش‌ها** | نمودار ۹۰ روز، پرفروش‌ترین محصول/پنل، export CSV، لاگ عملیات |

### ابزار
| صفحه | قابلیت‌ها |
|------|-----------|
| **API** | تست تعاملی، مستندات action، چند توکن |
| **تنظیمات** | 2FA تلگرام، اعلان تلگرام، تم، زبان fa/en، خروج همه دستگاه‌ها |
| **ورود** | آیدی عددی تلگرام · remember-me · session امن |

### API REST (`POST /api/agent.php`)

`Authorization: Bearer {token}`

| Action | توضیح |
|--------|--------|
| `dashboard` | آمار + chart data |
| `services` | لیست سرویس‌ها |
| `service_detail` | جزئیات + panel user |
| `buy` · `buy_custom` · `buy_bulk` · `test_account` | خرید |
| `renew` · `add_volume` · `add_time` · `revoke` | مدیریت سرویس |
| `affiliates` · `transactions` · `tariff` | نمایندگی و مالی |
| `panels` · `products` | کاتالوگ |

Rate limit · api_log · webhook cron · onboarding modal

---

## پنل‌های VPN پشتیبانی‌شده

| نوع | وضعیت |
|-----|--------|
| Marzban · Pasarguard · Marzneshin | ✅ full |
| x-ui_single · Alireza | ✅ full+ |
| Hiddify · mirza_agent · IBSng · Mikrotik | partial |
| **Ilan** | ✅ REST generic |
| WGDashboard · s-ui · Manualsale | ✅ |

جزئیات عملیات: **[docs/PANEL_SUPPORT.md](docs/PANEL_SUPPORT.md)**

---

## نصب — یک خط

روی **Ubuntu** با **root**:

```bash
curl -fsSL https://raw.githubusercontent.com/liamlope/ViraNaut/main/ViraNaut_manage.sh -o /root/ViraNaut_manage.sh && chmod +x /root/ViraNaut_manage.sh && /root/ViraNaut_manage.sh
```

| مرحله | کار |
|-------|-----|
| اولین بار | منو → **1) Install** |
| Mirza روی سرور دارید | Install → migrate خودکار |
| بعد از نصب | `viranaut` |

نصب بدون سؤال:

```bash
/root/ViraNaut_manage.sh install -y \
  --domain bot.example.com \
  --token "BOT_TOKEN" \
  --admin "TELEGRAM_ID" \
  --bot "BotUsername"
```

---

## آپدیت — یک خط

```bash
curl -fsSL https://raw.githubusercontent.com/liamlope/ViraNaut/main/ViraNaut_manage.sh -o /root/ViraNaut_manage.sh && chmod +x /root/ViraNaut_manage.sh && /root/ViraNaut_manage.sh update
```

- بکاپ ZIP خودکار → `/root/viranaut_backups/`
- `config.php` و DB **حفظ** می‌شوند
- migrationهای `viranaut_migrate*.sql` اجرا می‌شوند

---

## منوی `viranaut`

| # | کار |
|---|-----|
| **1** | Install (تشخیص Mirza) |
| **2** | Update از GitHub |
| **3–5** | Stop / Start / Restart Apache + MySQL + webhook |
| **6** | Logs |
| **7** | Diagnose |
| **8** | Auto-fix |
| **9** | حذف کامل |
| **0** | خروج |

```bash
/root/ViraNaut_manage.sh update | restart | fix | diagnose | logs | remove
```

---

## پیش‌نیاز

| مورد | مقدار |
|------|--------|
| OS | Ubuntu 20.04+ (توصیه: 22.04) |
| دسترسی | root |
| دامنه | subdomain · SSL خودکار |
| PHP | 8.0+ |
| DB | MySQL / MariaDB |

**مسیر نصب:** `/var/www/html/viranaut`

---

## مهاجرت از Mirza

```bash
viranaut   # → 1) Install → Migrate? y
```

DB و توکن حفظ · فایل‌ها از GitHub · vhost + SSL + webhook خودکار

مسیرهای شناخته‌شده Mirza: `/var/www/html/mirzabotconfig` · `/var/www/mirza_pro`

---

## SMS + تأیید کارت

| حالت `card_autoconfirm_mode` | رفتار |
|------------------------------|--------|
| `both` | SMS Vira + auto cron Mirza (پیش‌فرض) |
| `receipt_only` | فقط SMS و دکمه رسید |
| `auto_only` | فقط cron خودکار |

Cron (نصب خودکار):

```bash
*/1 * * * * php /var/www/html/viranaut/cronbot/croncard.php
```

---

## عیب‌یابی

| مشکل | راه‌حل |
|------|--------|
| ربات جواب نمی‌دهد | `viranaut` → Diagnose → Auto-fix |
| بعد از آپدیت | بکاپ: `/root/viranaut_backups/` |
| migration | `https://YOUR_DOMAIN/panel/migration.php` |
| smoke test | `php tools/smoke_test.php` |

---

## حمایت مالی · Donate

اگر ویرانات برای کسب‌وکار یا آموزش شما مفید بود، می‌توانید از توسعه حمایت کنید:

| شبکه | ارز | آدرس |
|------|-----|------|
| **Bitcoin** | BTC | `bc1q24r7j79eghk0lcury2ly4hm04mt2yh59ejajxz` |
| **Ethereum** | ETH · USDT · USDC | `0xb60a111813bae216e3b178a5f9e31a95549c000e` |
| **BNB Smart Chain** | BNB · USDT · USDC | `0xb60a111813bae216e3b178a5f9e31a95549c000e` |
| **Polygon** | MATIC · USDT | `0xb60a111813bae216e3b178a5f9e31a95549c000e` |
| **Solana** | SOL | `8NE5a13aHCQF38mEHspRwikskEg8JMAG9ZA9qtgwRUdM` |
| **Tron** | TRX · USDT · USDC | `TFxj93JHJ9s2jybwcWQ3C4b4rppM8Vvuc5` |
| **Dogecoin** | DOGE | `DFAfCU1LHdc7sKFVs9dD7MySA7Wt4EJQtX` |
| **TON** | TON (Gram) | `UQDpQupJJM8bcxk19XmEZtwe-oQ4XmIbxM8SB88z0MXmXYsu` |

> آدرس‌ها در صفحه ورود پنل ادمین (`/panel/login.php`) هم نمایش داده می‌شوند.

---

## English (short)

**ViraNaut** is an advanced **MIT-licensed fork** of free [Mirza Bot](https://github.com/mahdiMGF2/mirzabot): full **admin web panel** (31+ pages), **agent web panel Pro**, SMS card verification, 14 VPN panel drivers, REST agent API, one-line Ubuntu installer, and Mirza migration.

```bash
# Install
curl -fsSL https://raw.githubusercontent.com/liamlope/ViraNaut/main/ViraNaut_manage.sh | bash

# Update
/root/ViraNaut_manage.sh update
```

**URLs:** `/panel/` · `/agent-panel/` · `/api/agent.php`

---

## License

MIT — see [LICENSE](LICENSE).

---

<div align="center">

**ساخته شده با ❤️ برای جامعهٔ فروش VPN ایران**

[GitHub](https://github.com/liamlope/ViraNaut) · [Changelog](CHANGELOG.md) · [Panel Support](docs/PANEL_SUPPORT.md)

</div>
