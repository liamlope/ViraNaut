<div dir="rtl" align="right">

<div align="center">

# ویرانات

**ربات تلگرام فروش VPN — نصب، مهاجرت از میرزا، مدیریت سرور**

[![نسخه](https://img.shields.io/badge/نسخه-3.2.0-blue?style=for-the-badge)](https://github.com/liamlope/ViraNaut)
[![لایسنس](https://img.shields.io/badge/لایسنس-آزاد-green?style=for-the-badge)](LICENSE)

[⭐ گیت‌هاب](https://github.com/liamlope/ViraNaut) · [📖 معرفی و مقایسه](docs/MOAREFI.md) · [📝 تغییرات](CHANGELOG.md)

</div>

---

## خلاصه

**ویرانات** فورک رایگان و پیشرفتهٔ [میرزا](https://github.com/mahdiMGF2/mirzabot) است.  
نصب و آپدیت با اسکریپت **`ViraNaut_manage.sh`** انجام می‌شود — مهاجرت از میرزا بدون از دست دادن دیتابیس و توکن.

> معرفی کامل، جدول مقایسه و لیست قابلیت پنل‌ها → **[docs/MOAREFI.md](docs/MOAREFI.md)**

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
  --domain bot.shop.ir \
  --token "توکن_ربات" \
  --admin "123456789" \
  --bot "نام_کاربری_ربات"
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
| فایل‌های ربات | 🔄 از گیت‌هاب ویرانات |
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

اگر در **نصب، پیاده‌سازی، آپدیت** یا **گزارش باگ** به کمک نیاز دارید:

**تلگرام:** [@eronum](https://t.me/eronum)

---

## حمایت مالی

| شبکه | ارز | آدرس |
|------|-----|------|
| بیت‌کوین | BTC | `bc1q24r7j79eghk0lcury2ly4hm04mt2yh59ejajxz` |
| اتریوم | ETH · USDT · USDC | `0xb60a111813bae216e3b178a5f9e31a95549c000e` |
| BNB | BNB · USDT · USDC | `0xb60a111813bae216e3b178a5f9e31a95549c000e` |
| پالیگان | MATIC · USDT | `0xb60a111813bae216e3b178a5f9e31a95549c000e` |
| سولانا | SOL | `8NE5a13aHCQF38mEHspRwikskEg8JMAG9ZA9qtgwRUdM` |
| ترون | TRX · USDT · USDC | `TFxj93JHJ9s2jybwcWQ3C4b4rppM8Vvuc5` |
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
