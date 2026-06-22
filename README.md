<div dir="rtl" align="right">

<div align="center">

# ویرا · ViraNaut

**ربات تلگرام فروش VPN — پنل وب ادمین و نمایندگی، نصب یک‌خطی، مهاجرت از میرزا**

[![نسخه](https://img.shields.io/badge/نسخه-3.2.0-blue?style=for-the-badge)](https://github.com/liamlope/ViraNaut)
[![لایسنس](https://img.shields.io/badge/لایسنس-آزاد-green?style=for-the-badge)](LICENSE)

[⭐ گیت‌هاب](https://github.com/liamlope/ViraNaut) · [📖 معرفی و مقایسه](docs/MOAREFI.md) · [💬 گروه ویرا](https://t.me/ViraNautGroup) · [📝 تغییرات](CHANGELOG.md)

</div>

---

## خلاصه

**ویرا (ViraNaut)** فورک رایگان و متن‌باز از [میرزا](https://github.com/mahdiMGF2/mirzabot) است — ربات فروش VPN با **پنل وب ادمین** (۳۰+ صفحه)، **پنل وب نمایندگی**، تأیید خودکار کارت از پیامک، مینی‌اپ تلگرام و اسکریپت نصب **`ViraNaut_manage.sh`**.

- رابط پنل: **فقط فارسی**، راست‌چین، تم تیره/روشن
- نصب و آپدیت از گیت‌هاب؛ مهاجرت از میرزا **بدون از دست دادن دیتابیس و توکن**
- معرفی کامل، مقایسه و فهرست صفحات → **[docs/MOAREFI.md](docs/MOAREFI.md)**

---

## قابلیت‌های برجسته (نسخه فعلی)

| بخش | آنچه الان در دسترس است |
|-----|------------------------|
| **پنل ادمین** | داشبورد + نمودار فروش و **رشد کاربر**، کاربران، سفارشات، مرکز مالی، ۱۴ نوع پنل VPN |
| **پیام‌رسانی وب** | ارسال متن / عکس / ویدیو، **دکمه شیشه‌ای** (لینک، Mini App، Callback)، پیش‌نمایش زنده |
| **مخاطب پیام** | جستجوی کاربر (آیدی / نام / username)، ارسال به **یک نفر**، یا گروهی: همه، f، n، n2، خریدار، بدون خرید، مسدود |
| **کمپین** | پیشرفت ارسال، توقف / ادامه، پین بعد از ارسال |
| **پنل نمایندگی** | خرید، سرویس‌ها، مالی، زیرمجموعه، API، ورود دو مرحله‌ای |
| **ربات** | چیدمان منو از وب، متن‌ها، ایموجی پرمیوم، جوین اجباری، تأیید پیامکی کارت |
| **سرور** | نصب یک‌خطی، بکاپ قبل از آپدیت، عیب‌یابی و تعمیر خودکار |

---

## در دست توسعه

| قابلیت | وضعیت |
|--------|--------|
| ارسال خودکار به کاربر جدید بعد از اتمام broadcast | رابط پنل آماده · اجرای کرون در نسخه بعد |
| ویرایش / حذف / پین مجدد پیام کمپین در تلگرام | برنامه‌ریزی‌شده |
| فیلتر و مدیریت پیشرفته‌تر کمپین‌های قدیمی | برنامه‌ریزی‌شده |

پیشنهاد و گزارش باگ: [گروه ViraNaut](https://t.me/ViraNautGroup)

---

## نصب (یک خط)

**اوبونتو · کاربر ریشه · زیردامنه**

```bash
curl -fsSL https://raw.githubusercontent.com/liamlope/ViraNaut/main/ViraNaut_manage.sh -o /root/ViraNaut_manage.sh && chmod +x /root/ViraNaut_manage.sh && /root/ViraNaut_manage.sh
```

| مرحله | کار |
|-------|-----|
| ۱ | منو → **نصب** |
| ۲ | دامنه، توکن ربات، آیدی ادمین |
| ۳ | گواهی امن و وبهوک خودکار |

**نصب بدون سؤال:**

```bash
/root/ViraNaut_manage.sh install -y \
  --domain bot.example.com \
  --token "BOT_TOKEN" \
  --admin "123456789" \
  --bot "BotUsername"
```

**مسیر نصب روی سرور:** `/var/www/html/viranaut`

### ورود به پنل‌ها (بعد از نصب)

دامنه را با **دامنهٔ واقعی ربات** عوض کنید — مثلاً اگر دامنه `bot.shop.ir` است:

| بخش | آدرس |
|-----|------|
| پنل ادمین | `https://bot.shop.ir/panel/` |
| پنل نمایندگی | `https://bot.shop.ir/agent-panel/` |

---

## مهاجرت از میرزا

اسکریپت **`ViraNaut_manage.sh`** نصب قبلی میرزا را **خودکار پیدا می‌کند** و دیتابیس را حفظ می‌کند.

### مسیرهای شناخته‌شده

```
/var/www/html/mirzabotconfig
/var/www/html/mirzaprobotconfig
/var/www/html/viranautconfig
/var/www/mirza_pro
/var/www/html/viranaut
```

### مراحل

```bash
# ۱) دریافت اسکریپت
curl -fsSL https://raw.githubusercontent.com/liamlope/ViraNaut/main/ViraNaut_manage.sh -o /root/ViraNaut_manage.sh
chmod +x /root/ViraNaut_manage.sh

# ۲) اجرا
/root/ViraNaut_manage.sh
# → گزینه ۱) نصب
# → وقتی پرسید «مهاجرت از میرزا؟» → y
```

### چه چیزهایی حفظ می‌شود؟

| مورد | وضعیت |
|------|--------|
| دیتابیس | ✅ حفظ |
| تنظیمات (توکن، دامنه) | ✅ حفظ |
| کاربران، موجودی، فاکتور | ✅ حفظ |
| فایل‌های ربات | 🔄 از گیت‌هاب ویرا |
| سرور، گواهی امن، وبهوک | 🔄 بازسازی خودکار |

### بعد از مهاجرت

مهاجرت دیتابیس **خودکار** داخل همان اسکریپت اجرا می‌شود — **نیازی به کار دستی در مرورگر نیست.**

```bash
viranaut    # میانبر مدیریت — ربات باید بلافاصله آماده باشد
```

در صورت خطا: `viranaut` → **عیب‌یابی** → **تعمیر خودکار** یا [@eronum](https://t.me/eronum) در تلگرام.

---

## آپدیت

```bash
curl -fsSL https://raw.githubusercontent.com/liamlope/ViraNaut/main/ViraNaut_manage.sh -o /root/ViraNaut_manage.sh && chmod +x /root/ViraNaut_manage.sh && /root/ViraNaut_manage.sh update
```

- بکاپ فشرده خودکار → `/root/viranaut_backups/`
- تنظیمات و دیتابیس دست‌نخورده
- مهاجرت دیتابیس **خودکار** — بدون نیاز به مرورگر

---

## منوی اسکریپت مدیریت

بعد از نصب دستور **`viranaut`** در سرور فعال می‌شود.

| # | کار |
|---|-----|
| **1** | نصب (تشخیص میرزا) |
| **2** | آپدیت از گیت‌هاب |
| **3–5** | توقف / استارت / ری‌استارت سرویس‌ها |
| **6** | لاگ‌ها |
| **7** | عیب‌یابی |
| **8** | تعمیر خودکار |
| **9** | حذف کامل |
| **0** | خروج |

**دستورات مستقیم:**

```bash
/root/ViraNaut_manage.sh update
/root/ViraNaut_manage.sh restart
/root/ViraNaut_manage.sh fix
/root/ViraNaut_manage.sh diagnose
/root/ViraNaut_manage.sh logs
```

---

## پیش‌نیاز

| مورد | مقدار |
|------|--------|
| سیستم‌عامل | اوبونتو ۲۰.۰۴+ (توصیه: ۲۲.۰۴) |
| دسترسی | کاربر ریشه |
| دامنه | زیردامنه با DNS روی سرور |
| PHP | نسخه ۸.۰+ |
| دیتابیس | MySQL / MariaDB |

---

## عیب‌یابی سریع

| مشکل | راه‌حل |
|------|--------|
| ربات جواب نمی‌دهد | `viranaut` → عیب‌یابی → تعمیر خودکار |
| بعد از آپدیت | بکاپ: `/root/viranaut_backups/` · دوباره `update` |
| خطای دیتابیس | `viranaut update` یا تعمیر خودکار |
| تست | `php tools/smoke_test.php` |

---

## پشتیبانی

| کانال | لینک |
|-------|------|
| گروه ویرا (گزارش باگ و پیشنهاد) | [@ViraNautGroup](https://t.me/ViraNautGroup) |
| پیام مستقیم (نصب و پیاده‌سازی) | [@eronum](https://t.me/eronum) |

---

## حمایت مالی

| شبکه | ارز | آدرس |
|------|-----|------|
| بیت‌کوین | BTC | `bc1q5xw4nyqc5s993eukq9udrcpfh8ky6pc0mzlfsn` |
| اتریوم | ETH · USDT · USDC | `0xb60a111813bae216e3b178a5f9e31a95549c000e` |
| BNB | BNB · USDT · USDC | `0xb60a111813bae216e3b178a5f9e31a95549c000e` |
| پالیگان | MATIC · USDT | `0xb60a111813bae216e3b178a5f9e31a95549c000e` |
| سولانا | SOL | `GfKRLRTrKx7SYJHd76Rc7tVE6WwJKTNoZutSQitfppR6` |
| ترون | TRX · USDT · USDC | `TQEW4TP8eGzmJNyzu6kdi4GJdZdNqmTFRL` |
| دوج‌کوین | DOGE | `DFAfCU1LHdc7sKFVs9dD7MySA7Wt4EJQtX` |
| تون | TON | `UQDpQupJJM8bcxk19XmEZtwe-oQ4XmIbxM8SB88z0MXmXYsu` |

---

## مستندات

| سند | محتوا |
|-----|--------|
| [docs/MOAREFI.md](docs/MOAREFI.md) | معرفی، مقایسه میرزا، لیست قابلیت‌ها |
| [CHANGELOG.md](CHANGELOG.md) | تاریخچه نسخه‌ها |
| [docs/PANEL_SUPPORT.md](docs/PANEL_SUPPORT.md) | ماتریس پنل‌های VPN |

---

## مجوز

مجوز آزاد MIT — [LICENSE](LICENSE)

</div>
