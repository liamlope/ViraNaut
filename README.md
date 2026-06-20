# ViraNaut · ویرانات

**نسخه فعلی:** `3.1.0-ViraNaut`

ربات تلگرام فروش VPN با پنل وب پیشرفته، **پنل نمایندگی Pro**، مینی‌اپ، تأیید خودکار کارت‌به‌کارت از SMS بانک، و مهاجرت یک‌کلیکی از Mirza.

**GitHub:** [github.com/liamlope/ViraNaut](https://github.com/liamlope/ViraNaut)

---

## رابطه با Mirza Bot (مهم)

**ViraNaut یک fork پیشرفته از [Mirza Bot رایگان (Free)](https://github.com/mahdiMGF2/mirzabot) است** — نه جایگزین ساده و نه کپی کور.

| | Mirza Free 0.2.2 | ViraNaut 3.1 |
|---|------------------|--------------|
| پایه کد | Mirza upstream | ViraNaut (پنل وب ۳۱+ صفحه، SMS، x-ui گسترده) |
| زبان per-user (`lang/`) | ✅ | ✅ |
| Pasarguard · mirza_agent · ilan | partial / Pro | ✅ hooks کامل ([ماتریس پنل](docs/PANEL_SUPPORT.md)) |
| cron کارت auto-confirm | ✅ | ✅ dual-mode + SMS Vira |
| پنل وب ادمین | ~۱۲ صفحه | **۳۱+ صفحه** |
| پنل وب نمایندگی | ❌ (Pro) | ✅ **Pro** — chart، تمدید/حجم/revoke، API Bearer |
| site-admin | ❌ | ✅ درخواست از ربات + UI پاسخ |
| تست / CI | ❌ | ✅ PHPUnit + GitHub Actions |

جزئیات: [CHANGELOG.md](CHANGELOG.md) · [docs/PANEL_SUPPORT.md](docs/PANEL_SUPPORT.md) · [docs/UPGRADE_MATRIX.md](docs/UPGRADE_MATRIX.md)

---

## پنل‌های پشتیبانی‌شده

| نوع | وضعیت |
|-----|--------|
| marzban, pasarguard, marzneshin | full |
| x-ui_single, alireza | full+ |
| hiddify, mirza_agent, ibsng, mikrotik | partial |
| **ilan** | full (REST generic + mock tests) |
| Manualsale | full (داخلی) |

لیست کامل عملیات: **[docs/PANEL_SUPPORT.md](docs/PANEL_SUPPORT.md)**

---

## agent-panel Pro

| URL | `https://YOUR_DOMAIN/agent-panel/` |
| API | `POST /api/agent.php` — `Authorization: Bearer {token}` |
| Actions | `dashboard`, `services`, `service_detail`, `renew`, `add_volume`, `revoke` |

- داشبورد + نمودار فروش (Chart.js)
- عملیات سرویس: تمدید، افزایش حجم، لینک جدید
- تم هم‌راستا با پنل ادمین
- توکن API از **تنظیمات** یا **login** نماینده
- دکمه «🌐 پنل وب نمایندگی» در منوی نماینده ربات

---

## توسعه و تست

```bash
composer install
composer smoke          # php tools/smoke_test.php
composer test           # vendor/bin/phpunit
```

CI روی هر push به `main` اجرا می‌شود (`.github/workflows/ci.yml`).

---

## نصب (یک خط)

روی سرور **Ubuntu** با کاربر **root**:

```bash
curl -fsSL https://raw.githubusercontent.com/liamlope/ViraNaut/main/ViraNaut_manage.sh -o /root/ViraNaut_manage.sh && chmod +x /root/ViraNaut_manage.sh && /root/ViraNaut_manage.sh
```

- **اولین بار:** منو → **1) Install**
- **قبلاً نصب کرده‌اید:** همان دستور → منو (آپدیت، ری‌استارت، …)
- **Mirza روی سرور دارید:** **1) Install** → مهاجرت خودکار به ViraNaut

بعد از نصب:

```bash
viranaut
```

---

## آپدیت (یک خط)

```bash
curl -fsSL https://raw.githubusercontent.com/liamlope/ViraNaut/main/ViraNaut_manage.sh -o /root/ViraNaut_manage.sh && chmod +x /root/ViraNaut_manage.sh && /root/ViraNaut_manage.sh update
```

یا اگر اسکریپت را دارید:

```bash
/root/ViraNaut_manage.sh update
```

- قبل از آپدیت **بکاپ ZIP** خودکار (`/root/viranaut_backups/`)
- `config.php` و دیتابیس **حفظ** می‌شوند
- migrationهای `viranaut_migrate*.sql` + `table.php` اجرا می‌شوند
- cron کارت → `cronbot/croncard.php` (SMS + auto-confirm)

بعد از آپدیت از Mirza/Vira قدیمی: پنل → **Migration** را یک‌بار بزنید.

---

## منوی مدیریت

| # | کار |
|---|-----|
| **1** | Install (GitHub — Mirza را هم تشخیص می‌دهد) |
| **2** | Update از GitHub (بکاپ خودکار) |
| **3** | Stop Apache |
| **4** | Start Apache |
| **5** | Restart کامل (MySQL + Apache + webhook + cron) |
| **6** | Logs |
| **7** | Diagnose (lang، mirza_agent، agent-panel، croncard) |
| **8** | Auto-fix (DB + vhost + SSL + webhook) |
| **9** | حذف کامل ربات |
| **0** | خروج |

---

## دستورات سریع

```bash
/root/ViraNaut_manage.sh update      # آپدیت از GitHub
/root/ViraNaut_manage.sh restart     # ری‌استارت کامل
/root/ViraNaut_manage.sh fix         # Auto-fix
/root/ViraNaut_manage.sh diagnose    # عیب‌یابی
/root/ViraNaut_manage.sh logs       # لاگ‌ها
/root/ViraNaut_manage.sh remove      # حذف کامل
```

نصب بدون سؤال:

```bash
/root/ViraNaut_manage.sh install -y \
  --domain bot.example.com \
  --token "TOKEN" \
  --admin "TELEGRAM_ID" \
  --bot "BotUsername"
```

---

## پیش‌نیاز

| مورد | مقدار |
|------|--------|
| OS | Ubuntu 20.04+ (توصیه: 22.04) |
| دسترسی | root |
| دامنه | subdomain (مثلاً `bot.example.com`) |

**مسیر نصب:** `/var/www/html/viranaut`  
**پنل ادمین:** `https://YOUR_DOMAIN/panel/`  
**پنل نماینده:** `https://YOUR_DOMAIN/agent-panel/`  
**سایت ادمین:** `https://YOUR_DOMAIN/site-admin/`

---

## مهاجرت از Mirza

```bash
viranaut
# 1) Install → Migrate Mirza → ViraNaut? y
```

- DB و توکن **حفظ** می‌شود  
- فایل‌ها از GitHub  
- vhost، SSL، webhook خودکار  

مسیرهای Mirza: `/var/www/html/mirzabotconfig` · `/var/www/html/mirzaprobotconfig` · `/var/www/mirza_pro`

---

## تأیید کارت (SMS + auto-confirm)

### حالت dual-mode (`card_autoconfirm_mode`)

| مقدار | رفتار |
|--------|--------|
| `both` | SMS/دکمه رسید Vira + تأیید خودکار Mirza (پیش‌فرض) |
| `receipt_only` | فقط SMS و دکمه «ارسال رسید» |
| `auto_only` | فقط تأیید خودکار cron |

تنظیم: پنل → **مرکز مالی** → **حالت cron کارت**

### SMS (ViraNaut)

1. **تأیید خودکار SMS** = روشن  
2. کانال خصوصی + ربات فروش + SMS Forwarder ادمین  
3. آیدی کانال در پنل → **آیدی کانال SMS**  

Cron (نصب/آپدیت خودکار):

```bash
*/1 * * * * php /var/www/html/viranaut/cronbot/croncard.php
```

---

## عیب‌یابی

| مشکل | راه‌حل |
|------|--------|
| ربات جواب نمی‌دهد | `viranaut` → **7) Diagnose** → **8) Auto-fix** |
| بعد از آپدیت | بکاپ: `/root/viranaut_backups/viranaut_preupdate_*.zip` |
| migration | `https://YOUR_DOMAIN/panel/migration.php` |

---

## English

**ViraNaut** is an **advanced open-source fork** of the free [Mirza Bot](https://github.com/mahdiMGF2/mirzabot), with a richer web panel, SMS card auto-verify, full panel driver matrix (v3.1), agent web panel Pro, PHPUnit CI, and Mirza 0.2.2 feature parity.

**One-line install (Ubuntu, root):**

```bash
curl -fsSL https://raw.githubusercontent.com/liamlope/ViraNaut/main/ViraNaut_manage.sh -o /root/ViraNaut_manage.sh && chmod +x /root/ViraNaut_manage.sh && /root/ViraNaut_manage.sh
```

**One-line update:**

```bash
curl -fsSL https://raw.githubusercontent.com/liamlope/ViraNaut/main/ViraNaut_manage.sh -o /root/ViraNaut_manage.sh && chmod +x /root/ViraNaut_manage.sh && /root/ViraNaut_manage.sh update
```

**CLI:** `update` · `restart` · `fix` · `diagnose` · `logs` · `remove`

---

## حمایت مالی (Donate)

| شبکه | ارز | آدرس |
|------|-----|------|
| **BSC** | USDT | `0x01f77c91107cbd28191a1e897073ad053fd2867c` |
| **Polygon** | USDT | `0x01f77c91107cbd28191a1e897073ad053fd2867c` |
| **Tron** | TRX / USDT (TRC20) | `TQEW4TP8eGzmJNyzu6kdi4GJdZdNqmTFRL` |
| **Bitcoin** | BTC | `bc1q5xw4nyqc5s993eukq9udrcpfh8ky6pc0mzlfsn` |
| **Solana** | SOL | `GfKRLRTrKx7SYJHd76Rc7tVE6WwJKTNoZutSQitfppR6` |

---

## License

MIT — see [LICENSE](LICENSE).
