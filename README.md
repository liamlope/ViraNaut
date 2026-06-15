# ViraNaut · ویرانات

[![Version](https://img.shields.io/badge/version-2.0.2--ViraNaut-blue)](version)
[![GitHub](https://img.shields.io/badge/GitHub-liamlope%2FViraNaut-181717?logo=github)](https://github.com/liamlope/ViraNaut)
[![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?logo=php)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

**ربات تلگرام فروش VPN** با پنل وب، مینی‌اپ تلگرام، پشتیبانی چند پنل، **تأیید خودکار کارت‌به‌کارت از SMS بانک**، و ابزار مهاجرت از Mirza.

---

## فهرست

1. [نصب یک‌خطی](#-نصب-یکخطی)
2. [پیش‌نیازها](#پیشنیازها)
3. [نصب گام‌به‌گام](#نصب-گامبهگام)
4. [به‌روزرسانی](#بهروزرسانی)
5. [مهاجرت از Mirza](#مهاجرت-از-mirza)
6. [تأیید خودکار کارت‌به‌کارت (SMS)](#تأیید-خودکار-کارتبهکارت-sms)
7. [اسکریپت مدیریت (`ViraNaut_manage.sh`)](#اسکریپت-مدیریت-viranaut_managesh)
8. [ساختار پروژه](#ساختار-پروژه)
9. [امنیت](#امنیت)
10. [English quick reference](#english-quick-reference)

---

## ⚡ نصب یک‌خطی

روی **سرور Ubuntu** با کاربر `root`:

```bash
curl -fsSL https://raw.githubusercontent.com/liamlope/ViraNaut/main/ViraNaut_manage.sh -o /root/ViraNaut_manage.sh && chmod +x /root/ViraNaut_manage.sh && /root/ViraNaut_manage.sh install
```

- اگر **Mirza** روی سرور باشد → خودکار پیشنهاد **مهاجرت به ViraNaut** می‌دهد  
- اگر سرور تازه باشد → از **GitHub** clone + SSL + webhook + DB

**نصب بدون سؤال (با پارامتر):**

```bash
/root/ViraNaut_manage.sh install -y \
  --domain bot.example.com \
  --token "123456:ABC..." \
  --admin "YOUR_TELEGRAM_ID" \
  --bot "YourBotUsername"
```

**به‌روزرسانی یک‌خطی:**

```bash
/root/ViraNaut_manage.sh update
```

یا بعد از نصب: `viranaut` → **۲) Update**

---

## پیش‌نیازها

| مورد | حداقل |
|------|--------|
| سیستم‌عامل | Ubuntu 20.04+ (توصیه: 22.04) |
| وب‌سرور | Apache 2.4 + PHP 8.2 |
| دیتابیس | MySQL / MariaDB |
| افزونه‌های PHP | mysql, mbstring, zip, gd, curl, soap, ssh2, pdo |
| ابزارها | git, curl, wget, unzip, jq, certbot |
| دسترسی | `root` برای `ViraNaut_manage.sh` |

**مسیر پیش‌فرض نصب:** `/var/www/html/viranaut`  
**پنل مدیریت:** `https://YOUR_DOMAIN/panel/`

---

## نصب گام‌به‌گام

### روش ۱ — manage script (توصیه‌شده)

```bash
curl -fsSL https://raw.githubusercontent.com/liamlope/ViraNaut/main/ViraNaut_manage.sh -o /root/ViraNaut_manage.sh
chmod +x /root/ViraNaut_manage.sh
/root/ViraNaut_manage.sh install
# یا: viranaut → 1) Install
```

منبع فایل‌ها: **فقط GitHub** — [`github.com/liamlope/ViraNaut`](https://github.com/liamlope/ViraNaut)

### روش ۲ — clone + منو

```bash
git clone https://github.com/liamlope/ViraNaut.git
cp ViraNaut/ViraNaut_manage.sh /root/
/root/ViraNaut_manage.sh install
```

### روش ۳ — نصب دستی (پیشرفته)

```bash
# ویرایش config.php: توکن، ادمین، دامنه، DB
mysql -u root -p -e "CREATE DATABASE viranaut CHARACTER SET utf8mb4;"
php table.php
curl -F "url=https://YOUR_DOMAIN/index.php" \
     "https://api.telegram.org/botYOUR_TOKEN/setWebhook"
```

---

## به‌روزرسانی

| روش | دستور |
|-----|--------|
| CLI | `/root/ViraNaut_manage.sh update` |
| منو | `viranaut` → **۲) Update** |
| تعمیر | `viranaut` → **۸) Auto-fix** |
| بازگشت | بکاپ‌های `/root/viranaut_backups/viranaut_preupdate_*.zip` |

**Update (منو ۲):** بکاپ خودکار → `git pull` از GitHub (یا clone deploy) → `table.php` + migration — **بدون ZIP محلی**

**Cron مهم بعد از آپدیت** (برای SMS خودکار):

```bash
*/1 * * * * curl -s https://YOUR_DOMAIN/cronbot/card_receipt_prompt.php >/dev/null 2>&1
```

اسکریپت manage معمولاً cronها را هنگام نصب/restore تنظیم می‌کند. مسیر قدیمی `cronbot/croncard.php` هم alias همان cron است.

---

## مهاجرت از Mirza

ViraNaut جایگزین **Mirza Bot** (میرزا) است. داده‌های کاربران، سفارش‌ها، پنل‌ها و تنظیمات مالی قابل انتقال‌اند.

### مسیرهای شناخته‌شده Mirza

| مسیر | توضیح |
|------|--------|
| `/var/www/html/mirzabotconfig` | نسخه رایگان اصلی (پیش‌فرض migration) |
| `/var/www/html/mirzaprobotconfig` | نسخه Pro / مسیر جایگزین |
| `/var/www/html/mirzabotconfig` | نام‌های قدیمی |
| `/var/www/mirza_pro` | برخی نصب‌های Pro |

دیتابیس قدیمی معمولاً **`mirzabot`** نام دارد؛ ViraNaut از **`viranaut`** استفاده می‌کند.

---

### سناریو A — Mirza روی همان سرور

```bash
/root/ViraNaut_manage.sh install
# Mirza در /var/www/html/mirzabotconfig تشخیص داده می‌شود
# → Migrate Mirza → ViraNaut? y
```

- DB و توکن ربات **حفظ** می‌شود  
- فایل‌ها از **GitHub** در `/var/www/html/viranaut`  
- vhost + webhook + migration خودکار

---

### سناریو B — سرور جدید (انتقال با SQL)

**مناسب:** Mirza روی سرور A، ViraNaut روی سرور B.

1. **روی سرور Mirza** — export دیتابیس:
   ```bash
   mysqldump -u root -p mirzabot > mirza_full.sql
   ```
2. **روی سرور جدید** — `viranaut` → **۱) Install** (GitHub).
3. Import دیتابیس:
   ```bash
   mysql -u root -p viranaut < mirza_full.sql
   ```
   > اگر DB خالی ساخته شده، ممکن است لازم باشد اول جداول installer را drop کنید یا import را روی DB تازه انجام دهید.
4. **پنل وب** → **ابزارها / مهاجرت DB** (`/panel/migration.php`):
   - آپلود فایل `.sql` **یا**
   - **اجرای مهاجرت داخلی** (اعمال `migrations/viranaut_migrate.sql` روی DB فعلی)
5. `config.php` را با توکن و دامنه **سرور جدید** هماهنگ کنید.
6. manage → **۸) Auto-fix** برای SSL و webhook.

---

### سناریو C — بازگشت بعد از آپدیت (بکاپ pre-update)

**مناسب:** آپدیت (منو **۲**) ناموفق بود یا می‌خواهید نسخه قبلی را برگردانید.

قبل از هر آپدیت GitHub، اسکریپت خودکار ZIP می‌سازد:

```text
/root/viranaut_backups/viranaut_preupdate_*.zip
```

(آخرین **۳** بکاپ نگه داشته می‌شود.)

برای بازگردانی دستی: فایل ZIP را extract کنید، `config.php` و dump دیتابیس را جایگزین کنید، سپس **۸) Auto-fix**.

---

### سناریو D — مهاجرت تدریجی (Mirza و ViraNaut موازی)

1. ViraNaut را روی **دامنه یا subpath جدید** نصب کنید.  
2. SQL را import + migration پنل را اجرا کنید.  
3. webhook را به ربات ViraNaut منتقل کنید.  
4. پس از تست، Mirza را با manage → **۹) Full remove** حذف کنید (با احتیاط).

---

### چک‌لیست بعد از مهاجرت

- [ ] `/start` در ربات پاسخ می‌دهد  
- [ ] پنل `/panel/` باز می‌شود  
- [ ] پنل‌های VPN (Marzban و …) `active` هستند  
- [ ] درگاه کارت و شماره کارت درست است  
- [ ] cronها در `crontab -l` وجود دارند  
- [ ] SSL معتبر است (`https://`)  
- [ ] webhook: `curl "https://api.telegram.org/botTOKEN/getWebhookInfo"`

---

## تأیید خودکار کارت‌به‌کارت (SMS)

ViraNaut می‌تواند **پیامک واریز بانک** را بخواند و فاکتور کارت‌به‌کارت را **بدون تأیید دستی ادمین** تسویه کند. تطبیق بر اساس **مبلغ یکتا** (تومان) در جدول `Payment_report` است.

### نحوه کار (خلاصه)

```
کاربر فاکتور می‌گیرد → مبلغ یکتا (مثلاً ۵۰,۱۲۳ تومان)
        ↓
کاربر کارت‌به‌کارت می‌زند
        ↓
بانک SMS می‌فرستد → SMS Forwarder → گروه تلگرام
        ↓
ربات ViraNaut پیام گروه را parse می‌کند
        ↓
مبلغ = فاکتور Unpaid/waiting → تأیید خودکار + فعال‌سازی سرویس
```

**رفتارهای جانبی:**

- تا پایان **تأخیر ارسال رسید** (پیش‌فرض ۱۰ دقیقه، قابل تنظیم)، دکمه «ارسال رسید» مخفی می‌ماند.  
- اگر کاربر منو را ترک کند (`/start`، برگشت، منوی اصلی)، فاکتور **Unpaid** → `expire` می‌شود.  
- فاکتور **waiting** (بعد از ارسال رسید) لغو خودکار نمی‌شود.

---

### گام ۱ — فعال‌سازی در پنل

1. ورود به **`https://DOMAIN/panel/`**  
2. **مرکز مالی** → **درگاه‌ها** → **تنظیمات عمومی**  
3. تنظیمات:

| کلید | مقدار | توضیح |
|------|--------|--------|
| **تأیید خودکار SMS** | `روشن` (`onautoconfirm`) | فعال‌سازی کل سیستم |
| **تأخیر دکمه «ارسال رسید»** | ۱–۱۴۴۰ دقیقه | پیش‌فرض **۱۰**؛ بعد از این مدت دکمه رسید ظاهر می‌شود |
| **آیدی گروه SMS** | مثلاً `-1001234567890` | از **@IDFindeerBot** در گروه SMS بگیرید |

**جایگزین — ربات ادمین:**  
منوی ادمین → **♻️ تایید خودکار رسید** (روشن/خاموش)

---

### گام ۲ — گروه تلگرام برای SMS

1. یک **گروه خصوصی** بسازید (مثلاً «SMS Bank Bot»).  
2. **ربات فروش** (`@YourBot`) را به گروه اضافه کنید و **Admin** کنید (حداقل: Read messages).  
3. در **BotFather** → ربات → **Bot Settings** → **Group Privacy** → **Turn off**  
   > اگر Privacy روشن باشد، ربات پیام‌های forward‌شده را نمی‌بیند.  
4. آیدی گروه را بگیرید:
   - ربات **`@IDFindeerBot`** را به گروه اضافه کنید
   - آیدی supergroup را کپی کنید (فرمت: `-100xxxxxxxxxx`)  
5. آیدی را در پنل → **آیدی گروه SMS** ذخیره کنید.

---

### گام ۳ — SMS Forwarder روی گوشی

1. اپ **SMS Forwarder** (یا مشابه) را روی گوشی‌ای که **خط بانکی** دارد نصب کنید.  
2. Rule بسازید: **همه SMSهای بانک** (یا فیلتر «واریز») → **Forward to Telegram** → همان گروه.  
3. یک واریز تست کوچک بزنید؛ باید متن SMS در گروه ظاهر شود.  
4. ربات نباید به کاربران معمولی در گروه پاسخ دهد — فقط SMS parse می‌کند.

---

### گام ۴ — مبلغ یکتا

- هنگام صدور فاکتور کارت، سیستم مبلغ را طوری تنظیم می‌کند که **یکتا** باشد (مثلاً ۵۰,۰۰۰ → ۵۰,۱۲۳).  
- SMS بانک باید **همان مبلغ** را گزارش کند.  
- اگر دو فاکتور هم‌زمان با یک مبلغ داشته باشید، تطبیق اشتباه رخ می‌دهد — از overlap جلوگیری کنید.

---

### گام ۵ — Cron تأخیر رسید

هر **۱ دقیقه**:

```bash
*/1 * * * * curl -s https://YOUR_DOMAIN/cronbot/card_receipt_prompt.php >/dev/null 2>&1
```

این cron به کاربرانی که تأخیر تمام شده دکمه «ارسال رسید» را نشان می‌دهد.

بررسی دستی:

```bash
curl -s "https://YOUR_DOMAIN/cronbot/card_receipt_prompt.php"
```

---

### گام ۶ — تست end-to-end

1. تأیید خودکار SMS = **روشن**  
2. گروه و آیدی تنظیم شده  
3. Privacy BotFather = **خاموش**  
4. از یک اکانت تست **خرید کارت** کنید  
5. **دقیقاً همان مبلغ** را واریز کنید  
6. ظرف چند ثانیه سفارش باید **paid** شود و سرویس فعال گردد  
7. در `Payment_report` وضعیت را چک کنید  

**لاگ خطا:**

```bash
tail -f /var/www/html/viranaut/error_log
# یا لاگ Apache دامنه
grep card-sms /var/log/apache2/YOUR_DOMAIN-error.log
```

پیام‌های داخلی: `[card-sms-telegram] OK` یا `no_match` / `parse_failed`.

---

### بانک‌های پشتیبانی‌شده (parse SMS)

| کد | بانک |
|----|------|
| `meli` | ملی |
| `sadhrat` | صادرات |
| `paselc` | پاسارگاد |
| `parsian` | پارسیان |
| `maskan` | مسکن |
| `melet` | ملت |
| `grdsh` | شهر (قدیم) |
| `sheahr` | شهر |
| `keshavarsi` | کشاورزی |
| `terjart` | تجارت |
| `resalet` | رسالت |
| `sphe` | سپه |
| `blu` | بلو |
| `mehr` / `gharz` | مهر / قرض‌الحسنه |

اگر بانک شما در لیست نیست، SMS نمونه را برای افزودن parser ارسال کنید (Issue در GitHub).

---

### روش قدیمی HTTP (بدون گروه تلگرام)

اگر **آیدی گروه** خالی باشد، پنل حالت `http_legacy` را نشان می‌دهد:

```
https://YOUR_DOMAIN/payment/card.php
```

SMS Forwarder بعضی نسخه‌ها POST به این URL می‌زنند. **روش توصیه‌شده:** گروه تلگرام (ساده‌تر و پایدارتر).

---

### عیب‌یابی SMS

| مشکل | راه‌حل |
|------|--------|
| SMS در گروه هست ولی تأیید نمی‌شود | Privacy BotFather خاموش؟ آیدی گروه درست؟ |
| `parse_failed` در لاگ | فرمت SMS بانک متفاوت است — نمونه SMS را بفرستید |
| `no_match` | مبلغ واریز ≠ مبلغ فاکتور؛ یا فاکتور expire شده |
| دکمه رسید زود ظاهر می‌شود | `cardreceiptdelaymin` را در پنل بالا ببرید |
| cron کار نمی‌کند | `crontab -l` و URL دامنه را چک کنید |
| بعد از آپدیت 404 روی cron | از `card_receipt_prompt.php` استفاده کنید؛ `croncard.php` alias است |

---

## اسکریپت مدیریت (`ViraNaut_manage.sh`)

```bash
chmod +x ViraNaut_manage.sh
./ViraNaut_manage.sh
# بعد از نصب:
viranaut
```

| # | کار |
|---|-----|
| **1** | Install — GitHub + تشخیص Mirza → ViraNaut |
| **2** | Update — GitHub + بکاپ خودکار قبل از آپدیت |
| **3** | Stop Apache |
| **4** | Start Apache |
| **5** | Restart full (MySQL + Apache + webhook) |
| **6** | Logs |
| **7** | Diagnose bot |
| **8** | Auto-fix (DB + vhost + SSL + webhook) |
| **9** | Full remove bot |
| **0** | Exit |

**CLI:** `./ViraNaut_manage.sh install` · `./ViraNaut_manage.sh update`

---

## ساختار پروژه

```
ViraNaut/
├── index.php              # Webhook تلگرام
├── admin.php              # دستورات ادمین ربات
├── function.php           # منطق اصلی + SMS کارت
├── payment/card.php       # endpoint legacy SMS
├── cronbot/
│   ├── card_receipt_prompt.php   # cron تأخیر رسید
│   └── croncard.php              # alias همان cron
├── panel/                 # پنل وب مدیریت
├── app/                   # Telegram Mini App
├── api/                   # REST API
├── migrations/
│   └── viranaut_migrate.sql
└── ViraNaut_manage.sh   # نصب، آپدیت GitHub، بکاپ pre-update
```

---

## امنیت

- **`config.php` را commit نکنید** — توکن ربات و رمز DB داخل آن است (در `.gitignore`).  
- بعد از اولین ورود **رمز پنل** را عوض کنید.  
- `panel/storage/` و لاگ‌ها نباید از وب عمومی در دسترس باشند.  
- فقط `ViraNaut_manage.sh` را از [GitHub رسمی](https://github.com/liamlope/ViraNaut) بگیرید.  
- اگر توکن در چت یا لاگ لو رفت → **BotFather → Revoke** و webhook را دوباره set کنید.  
- برای گروه SMS فقط اعضای مورد اعتماد — متن SMS حاوی اطلاعات مالی است.

---

## English quick reference

**ViraNaut** is an open-source Telegram VPN sales bot with web panel, Mini App, multi-panel support, and **automatic card payment confirmation via bank SMS**.

**One-line install (Ubuntu, root):**

```bash
curl -fsSL https://raw.githubusercontent.com/liamlope/ViraNaut/main/ViraNaut_manage.sh -o /root/ViraNaut_manage.sh && chmod +x /root/ViraNaut_manage.sh && /root/ViraNaut_manage.sh install
```

**Update:** `/root/ViraNaut_manage.sh update` or `viranaut` → menu **2**.

**Mirza:** detected on install → auto migration to ViraNaut at `/var/www/html/viranaut`.

**Card SMS auto-confirm:** Panel → Finance → Gateways → enable SMS autoconfirm, set Telegram group ID, disable BotFather group privacy, forward bank SMS to the group via SMS Forwarder app. Matching is by **unique invoice amount** in Tomans.

**Links:** [github.com/liamlope/ViraNaut](https://github.com/liamlope/ViraNaut)

---

## Support · حمایت

اگر این پروژه برایتان مفید بود، می‌توانید از توسعه حمایت کنید. آدرس‌ها فقط برای مشارکت هستند.

| شبکه | نماد | آدرس |
|------|------|------|
| BSC | USDT | `0x01f77c91107cbd28191a1e897073ad053fd2867c` |
| Polygon | USDT | `0x01f77c91107cbd28191a1e897073ad053fd2867c` |
| Tron | TRX | `TQEW4TP8eGzmJNyzu6kdi4GJdZdNqmTFRL` |
| Bitcoin | BTC | `bc1q5xw4nyqc5s993eukq9udrcpfh8ky6pc0mzlfsn` |
| Solana | SOL | `GfKRLRTrKx7SYJHd76Rc7tVE6WwJKTNoZutSQitfppR6` |

---

## License

MIT — see [LICENSE](LICENSE).
