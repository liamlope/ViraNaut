# ViraNaut · ویرانات

ربات تلگرام فروش VPN با پنل وب، مینی‌اپ، تأیید خودکار کارت‌به‌کارت از SMS بانک، و مهاجرت از Mirza.

**GitHub:** [github.com/liamlope/ViraNaut](https://github.com/liamlope/ViraNaut)

---

## نصب (یک خط)

روی سرور **Ubuntu** با کاربر **root**:

```bash
curl -fsSL https://raw.githubusercontent.com/liamlope/ViraNaut/main/ViraNaut_manage.sh -o /root/ViraNaut_manage.sh && chmod +x /root/ViraNaut_manage.sh && /root/ViraNaut_manage.sh
```

- **اولین بار:** منو باز می‌شود → گزینه **1) Install**
- **قبلاً نصب کرده‌اید:** همان دستور → منوی اصلی (آپدیت، ری‌استارت، …)
- **Mirza روی سرور دارید:** گزینه **1) Install** → مهاجرت خودکار به ViraNaut

بعد از نصب همیشه می‌توانید بنویسید:

```bash
viranaut
```

---

## منوی مدیریت

| # | کار |
|---|-----|
| **1** | Install (GitHub — Mirza را هم تشخیص می‌دهد) |
| **2** | Update از GitHub (بکاپ خودکار قبل از آپدیت) |
| **3** | Stop Apache |
| **4** | Start Apache |
| **5** | Restart کامل (MySQL + Apache + webhook) |
| **6** | Logs |
| **7** | Diagnose |
| **8** | Auto-fix (DB + vhost + SSL + webhook) |
| **9** | حذف کامل ربات |
| **0** | خروج |

---

## دستورات سریع (بدون منو)

```bash
/root/ViraNaut_manage.sh update      # آپدیت از GitHub
/root/ViraNaut_manage.sh restart     # ری‌استارت کامل
/root/ViraNaut_manage.sh fix         # Auto-fix
/root/ViraNaut_manage.sh diagnose    # عیب‌یابی
/root/ViraNaut_manage.sh logs        # لاگ‌ها
/root/ViraNaut_manage.sh remove      # حذف کامل
```

نصب بدون سؤال (سرور تازه):

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
| دامنه | یک subdomain (مثلاً `bot.example.com`) |

اسکریپت Apache، PHP 8.2، MySQL، git و SSL را خودش نصب می‌کند.

**مسیر نصب:** `/var/www/html/viranaut`  
**پنل:** `https://YOUR_DOMAIN/panel/`

---

## مهاجرت از Mirza

اگر [Mirza Bot](https://github.com/mahdiMGF2/mirzabot) روی سرور دارید:

```bash
viranaut
# 1) Install → Migrate Mirza → ViraNaut? y
```

- دیتابیس و توکن **حفظ** می‌شود  
- فایل‌ها از GitHub در `/var/www/html/viranaut`  
- vhost، SSL و webhook خودکار تنظیم می‌شود  

مسیرهای شناخته‌شده Mirza: `/var/www/html/mirzabotconfig` · `/var/www/html/mirzaprobotconfig` · `/var/www/mirza_pro`

---

## تأیید خودکار کارت (SMS)

1. پنل → **مرکز مالی** → **درگاه‌ها** → **تأیید خودکار SMS** = روشن  
2. **مهم:** ربات تلگرام پیام **ربات‌های دیگر** را در گروه نمی‌بیند. اگر SMS Forwarder با ربات جدا می‌فرستد:
   - **روش ۱ (توصیه):** کانال خصوصی → ربات فروش + SMS Forwarder ادمین کانال → مقصد = **کانال** (نه گروه)  
   - **روش ۲:** اپی که با **اکانت شخصی** شما به گروه بفرستد (نه @Bot)  
   - **روش ۳:** HTTP به `https://DOMAIN/payment/card.php`  
3. BotFather → Group Privacy = خاموش (برای گروه)  
4. آیدی گروه/کانال از **@IDFindeerBot**  
5. کاربر **دقیقاً مبلغ ریالی** فاکتور را واریز کند  

Cron (معمولاً هنگام نصب تنظیم می‌شود):

```bash
*/1 * * * * curl -s https://YOUR_DOMAIN/cronbot/card_receipt_prompt.php >/dev/null 2>&1
```

---

## عیب‌یابی

| مشکل | راه‌حل |
|------|--------|
| ربات جواب نمی‌دهد | `viranaut` → **7) Diagnose** سپس **8) Auto-fix** |
| بعد از آپدیت مشکل دارید | بکاپ: `/root/viranaut_backups/viranaut_preupdate_*.zip` |
| Apache خاموش است | `viranaut` → **4) Start** یا **5) Restart** |

---

## English

**One-line setup (Ubuntu, root):**

```bash
curl -fsSL https://raw.githubusercontent.com/liamlope/ViraNaut/main/ViraNaut_manage.sh -o /root/ViraNaut_manage.sh && chmod +x /root/ViraNaut_manage.sh && /root/ViraNaut_manage.sh
```

Then pick **1) Install** (fresh) or use **2) Update** / **5) Restart** if already installed.

**CLI:** `update` · `restart` · `fix` · `diagnose` · `logs` · `remove`

---

## License

MIT — see [LICENSE](LICENSE).
