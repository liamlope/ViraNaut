# ViraNaut · ویرانات

[![Version](https://img.shields.io/badge/version-1.9--ViraNaut-blue)](version)
[![GitHub](https://img.shields.io/badge/GitHub-liamlope%2FViraNaut-181717?logo=github)](https://github.com/liamlope/ViraNaut)
[![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?logo=php)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

**Free & open-source Telegram VPN sales bot** with a modern web admin panel, Telegram Mini App storefront, and multi-panel support.

**ربات تلگرام فروش VPN** با پنل مدیریت وب، مینی‌اپ تلگرام و پشتیبانی از چند پنل — رایگان و متن‌باز.

---

## English

### Overview

**ViraNaut** (ویرانات) is a full-featured Telegram bot for selling and managing VPN subscriptions. It includes:

- **Web admin panel** — dashboard, users, orders, finance, bot settings, optimization tools
- **Telegram Mini App** — 5 UI templates, in-app purchase flow
- **Multi-panel support** — Marzban, 3x-ui, Alireza, Pasarguard, IBSng, and more
- **Payments** — wallet, card-to-card, crypto gateways, receipt approval
- **Migration** — upgrade from legacy Mirza installations via SQL import
- **Management CLI** — `ViraNaut_manage.sh` for backups, logs, SSL, cron

### Requirements

| Component | Minimum |
|-----------|---------|
| OS | Ubuntu 20.04+ (recommended) |
| Web server | Apache 2.4 + PHP 8.2 |
| Database | MySQL / MariaDB |
| PHP extensions | mysql, mbstring, zip, gd, curl, soap, ssh2, pdo |
| Tools | git, curl, wget, unzip, jq, certbot |

### Quick install (recommended)

On a **fresh Ubuntu server** as `root`:

```bash
git clone https://github.com/liamlope/ViraNaut.git
cd ViraNaut
chmod +x install.sh ViraNaut_manage.sh
./install.sh
```

Choose **option 1** (Install) and follow the wizard. The installer will:

1. Install Apache, PHP 8.2, MySQL, Certbot, and dependencies  
2. Deploy the bot to `/var/www/html/viranaut`  
3. Create the database and `config.php`  
4. Configure SSL and Telegram webhook  
5. Run `migrations/viranaut_migrate.sql`

### Manual install

```bash
cp config.sample.php config.php
# Edit config.php — bot token, admin ID, domain, database credentials
mysql -u root -p -e "CREATE DATABASE viranaut CHARACTER SET utf8mb4;"
php table.php
```

Set webhook:

```bash
curl -F "url=https://YOUR_DOMAIN/index.php" \
     "https://api.telegram.org/botYOUR_TOKEN/setWebhook"
```

Open admin panel: `https://YOUR_DOMAIN/panel/`

### Update

```bash
./install.sh update
# or from menu: option 2
```

If the bot was installed via `git clone`, updates use **`git pull`**. Otherwise the installer falls back to a local zip or GitHub clone.

### Management script

```bash
chmod +x ViraNaut_manage.sh
./ViraNaut_manage.sh
# or after install: viranaut
```

Features: full backup/restore, log viewer, SSL renewal, cron jobs, service status.

### Migrate from Mirza

1. Backup your old Mirza database  
2. Install ViraNaut on the same or a new server  
3. Panel → **Migration** → upload `.sql` → run internal migration  
4. Or use installer menu option **10** (Mirza → ViraNaut)

### Project structure

```
ViraNaut/
├── index.php          # Telegram webhook entry
├── admin.php          # Admin bot commands
├── panel/             # Web admin panel
├── app/               # Telegram Mini App
├── api/               # REST APIs
├── migrations/        # SQL migrations
├── install.sh         # Installer / updater
└── ViraNaut_manage.sh # Server management CLI
```

### Security notes

- Never commit `config.php` — it contains secrets  
- Change default panel password after first login  
- Keep `panel/storage/` and logs out of public access  
- Run `install.sh` only from trusted sources

### Author & links

- **GitHub:** [liamlope](https://github.com/liamlope)
- **Repository:** [github.com/liamlope/ViraNaut](https://github.com/liamlope/ViraNaut)

---

## فارسی

### معرفی

**ویرانات (ViraNaut)** ربات تلگرام کامل برای فروش و مدیریت اشتراک VPN است:

- **پنل وب مدیریت** — داشبورد، کاربران، سفارشات، مالی، تنظیمات ربات، بهینه‌سازی
- **مینی‌اپ تلگرام** — ۵ قالب UI و خرید درون‌اپ
- **چند پنل** — Marzban، 3x-ui، Alireza، Pasarguard، IBSng و بیشتر
- **پرداخت** — کیف پول، کارت‌به‌کارت، درگاه ارزی، تأیید رسید
- **مهاجرت** — ارتقا از میرزا با import فایل SQL
- **اسکریپت مدیریت** — `ViraNaut_manage.sh` برای بکاپ، لاگ، SSL

### نصب سریع

روی سرور اوبونتو با کاربر `root`:

```bash
git clone https://github.com/liamlope/ViraNaut.git
cd ViraNaut
chmod +x install.sh ViraNaut_manage.sh
./install.sh
```

گزینه **۱** (نصب) را انتخاب کنید.

### به‌روزرسانی

```bash
./install.sh update
```

اگر نصب با `git clone` انجام شده باشد، به‌روزرسانی با **`git pull`** انجام می‌شود.

### مهاجرت از میرزا

۱. بکاپ دیتابیس میرزا  
۲. نصب ویرانات  
۳. پنل → **مهاجرت** → آپلود `.sql`  
۴. یا گزینه **۱۰** در منوی `install.sh`

### نکات امنیتی

- `config.php` را commit نکنید  
- رمز پنل را بعد از اولین ورود عوض کنید  
- فقط از منبع معتبر `install.sh` را اجرا کنید

---

## Support · حمایت

If this project helps you, consider a donation. Crypto addresses below are for supporting development only — they are **read-only** in the bot/panel UI and cannot be changed from the admin panel.

اگر این پروژه برایتان مفید بود، می‌توانید از توسعه‌دهنده حمایت کنید. آدرس‌های زیر فقط برای مشارکت هستند و در ربات/پنل قابل ویرایش نیستند.

| Network | Symbol | Address |
|---------|--------|---------|
| BSC | USDT | `0x01f77c91107cbd28191a1e897073ad053fd2867c` |
| Polygon | USDT | `0x01f77c91107cbd28191a1e897073ad053fd2867c` |
| Tron | TRX | `TQEW4TP8eGzmJNyzu6kdi4GJdZdNqmTFRL` |
| Bitcoin | BTC | `bc1q5xw4nyqc5s993eukq9udrcpfh8ky6pc0mzlfsn` |
| Solana | SOL | `GfKRLRTrKx7SYJHd76Rc7tVE6WwJKTNoZutSQitfppR6` |

---

## License

MIT — see [LICENSE](LICENSE) for details.
