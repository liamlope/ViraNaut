-- ViraNaut migration from Mirza bot database
-- Safe to run multiple times (INSERT IGNORE / ON DUPLICATE KEY UPDATE)

-- Crypto wallets (PaySetting)
INSERT INTO PaySetting (NamePay, ValuePay) VALUES
('wallet_usdt_bsc', '0x01f77c91107cbd28191a1e897073ad053fd2867c'),
('wallet_usdt_polygon', '0x01f77c91107cbd28191a1e897073ad053fd2867c'),
('wallet_trx_tron', 'TQEW4TP8eGzmJNyzu6kdi4GJdZdNqmTFRL'),
('wallet_btc', 'bc1q5xw4nyqc5s993eukq9udrcpfh8ky6pc0mzlfsn'),
('wallet_solana', 'GfKRLRTrKx7SYJHd76Rc7tVE6WwJKTNoZutSQitfppR6'),
('donation_enabled', '1'),
('donation_message', 'از حمایت شما برای توسعه ویرانات سپاسگزاریم 💎')
ON DUPLICATE KEY UPDATE ValuePay = IF(ValuePay = '' OR ValuePay IS NULL, VALUES(ValuePay), ValuePay);

-- Legacy TRON wallet alias
INSERT INTO PaySetting (NamePay, ValuePay) VALUES
('urlpaymenttron', 'TQEW4TP8eGzmJNyzu6kdi4GJdZdNqmTFRL')
ON DUPLICATE KEY UPDATE ValuePay = IF(ValuePay = '' OR ValuePay IS NULL, VALUES(ValuePay), ValuePay);

-- Mini app template default
INSERT INTO shopSetting (Namevalue, value) VALUES
('miniapp_template', 'midnight')
ON DUPLICATE KEY UPDATE value = IF(value = '' OR value IS NULL, VALUES(value), value);

-- Test account global toggle (setting.status_usertest: ontest / offtest)
-- Run once; ignore error if column already exists:
-- ALTER TABLE setting ADD COLUMN status_usertest VARCHAR(16) DEFAULT 'ontest';

-- Brand version marker
INSERT INTO shopSetting (Namevalue, value) VALUES
('viranaut_version', '1.9-ViraNaut')
ON DUPLICATE KEY UPDATE value = VALUES(value);

-- Panel status: فقط active / deactive
UPDATE marzban_panel SET status = 'active' WHERE status IN ('activepanel', 'active');
UPDATE marzban_panel SET status = 'deactive' WHERE status IN ('deactivepanel', 'deactive', 'disable', 'inactive');

-- متن‌های جدید فرایند خرید (textbot)
INSERT INTO textbot (id_text, text) VALUES
('text_select_category', '📌 دسته بندی خود را انتخاب نمایید!'),
('text_service_select', '📌 سرویس مورد نظر خود را انتخاب نمایید'),
('text_service_select_first', '📌 ابتدا سرویس مورد نظر را انتخاب نمایید'),
('text_sell_notestep', '📝 نام دلخواه سرویس خود را ارسال کنید')
ON DUPLICATE KEY UPDATE text = IF(text = '' OR text IS NULL, VALUES(text), text);

-- متن کوتاه کارت خودکار (جایگزینی قالب قدیمی Mirza)
UPDATE textbot SET text = '💳 <b>شارژ کیف پول · تأیید خودکار</b>

<b>مبلغ دقیق (ریال):</b> <code>{price}</code>

<code>{card_number}</code>
{name_card}

⚡ مبلغ دقیق = تأیید خودکار (معمولاً چند دقیقه)
⚠️ مبلغ غیردقیق → تأیید دستی تا ۴۸ ساعت
⏱ تأیید نشد؟ بعد از {receipt_delay} دکمه «ارسال رسید» فعال می‌شود.'
WHERE id_text = 'text_cart_auto'
  AND (text LIKE '%تایید فوری%' OR text LIKE '%====================%' OR text LIKE '%لزومی به ارسال رسید%' OR text LIKE '%واریز با تأیید خودکار%' OR text LIKE '%۱۰ دقیقه%' OR text LIKE '%10 دقیقه%' OR text LIKE '%بعد از ۱ دقیقه%' OR text LIKE '%بعد از 1 دقیقه%' OR (text LIKE '%شارژ کیف پول · تأیید خودکار%' AND text NOT LIKE '%{receipt_delay}%'));

INSERT INTO PaySetting (NamePay, ValuePay) VALUES
('cardreceiptdelaymin', '10')
ON DUPLICATE KEY UPDATE ValuePay = IF(ValuePay = '' OR ValuePay IS NULL OR ValuePay = '0', VALUES(ValuePay), ValuePay);
