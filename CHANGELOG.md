# Changelog — ViraNaut

All notable changes from Mirza 0.2.2 + Pro 6.7 upgrade path.

## [3.1.0-ViraNaut] — 2026-06-20

### Added
- **docs/PANEL_SUPPORT.md** — ماتریس ۱۴ نوع پنل و وضعیت عملیات
- **agent-panel Pro** — داشبورد + Chart.js، تمدید/حجم/لینک جدید، تم، CSRF، rate limit
- **api/agent.php** — API Bearer scoped به نماینده (dashboard, services, renew, volume, revoke)
- دکمه «پنل وب نمایندگی» در منوی نماینده (`keyboard.php` + handler در `index.php`)
- **site-admin** — نمایش عکس Telegram + پاسخ/وضعیت از وب
- wiring `mirza_site_admin_log_request()` هنگام تیکت پشتیبانی

### Changed
- **ilan** — از stub به full hooks (create, DataUser, revoke, extend, volume/time, reset)
- **mirza_agent** — reset usage واقعی؛ **hiddify** — revoke via UUID regen
- نسخه سراسری → `3.1.0-ViraNaut`
- `ViraNaut_manage.sh` — diagnose agent-panel، api/agent، PANEL_SUPPORT
- README redesign — جدول پنل‌ها، agent-panel Pro، راهنمای smoke test

### Fixed
- مسیر require در `api/agent.php` (`../config.php`)

---

## [3.0.0-ViraNaut] — 2026-06-20

### Added
- **agent-panel/** — پنل وب نمایندگی (داشبورد، سرویس‌ها، API token)
- **site-admin/** — ماژول سایت ادمین برای درخواست‌ها
- **ilan.php** — درایور پنل Ilan + hooks در `panels.php`
- `mirza_site_admin_log_request()` — ثبت درخواست در DB
- `mirza_tron_offline_receipt_message()` — قالب TRC20/TRON برای پرداخت آفلاین
- تنظیمات `offlinearze_tron_*` در پنل مالی
- انتخاب زبان per-user (`change_language` / `setlang:*`)
- `tools/smoke_test.php` و `tools/sync_lang_to_textjson.php`
- `docs/UPGRADE_MATRIX.md`

### Changed
- نسخه سراسری → `3.0.0-ViraNaut`
- vpnbot/update → `1.0.8`
- `ViraNaut_manage.sh` — migrate همه `viranaut_migrate*.sql`، diagnose، نسخه 3.0.0
- پروفایل: «شناسه کاربری» → «آیدی عددی»

### Fixed
- handler تکراری unknown command در `index.php`
- بلوک bootstrap ادمین (`require admin.php` همیشه برای ادمین‌ها)

---

## [2.2.0-ViraNaut] — Pro P0

### Added
- پرداخت بعد از سقف خرید نماینده (`mirza_maxbuyagent_payment_redirect`)
- پاسخ دستور نامعتبر + ستون `setting.unknowncommand_reply`
- Pasarguard alias (marzban + `version_panel=1`) + sub از دامنه ربات
- guard کد تخفیف حذف‌شده (موجود)
- فرمت TRON/TRC20 در رسید آفلاین

---

## [2.1.0-ViraNaut] — Mirza 0.2.2 parity

### Added
- **lang/** (fa, en, ru, zh) + `languagechange()` یکپارچه
- **mirza_agent.php** + hooks در panels/admin/keyboard
- **Pasarguard** در UI افزودن پنل
- **croncard** dual-mode: `receipt_only` | `auto_only` | `both`
- Migration `viranaut_migrate_2_1_0.sql`

### Preserved (Vira-only)
- پنل وب 31 صفحه، SMS card receipt، `x-ui_single.php` گسترده، `text.json` editor

---

## [2.0.2-pre-upgrade]

- Tag قبل از merge — پایه ViraNaut 2.0.2 بدون تغییر upstream
