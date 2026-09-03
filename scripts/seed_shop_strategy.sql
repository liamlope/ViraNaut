-- ViraNaut shop UX: compact glass menu + strategic anti-filter packages
-- Cost basis ~1500 Toman/GB; sell 5k–15k+/GB with value ladder.

ALTER TABLE product ADD COLUMN IF NOT EXISTS btn_style VARCHAR(20) NULL;
ALTER TABLE product ADD COLUMN IF NOT EXISTS limit_ip INT NOT NULL DEFAULT 0;
ALTER TABLE category ADD COLUMN IF NOT EXISTS btn_style VARCHAR(20) NULL;

UPDATE setting SET
  keyboardmain = '{"keyboard":[[{"text":"text_sell","style":"primary"},{"text":"text_usertest","style":"success"}],[{"text":"text_Purchased_services","style":"primary"},{"text":"accountwallet","style":"success"}],[{"text":"text_extend"},{"text":"text_support","style":"danger"}]]}',
  inlinebtnmain = 'oninline',
  statuscategorygenral = 'oncategorys',
  statuscategory = 'offcategory'
LIMIT 1;

INSERT INTO shopSetting (Namevalue, value)
SELECT 'statusshowprice', 'onshowprice'
WHERE NOT EXISTS (SELECT 1 FROM shopSetting WHERE Namevalue = 'statusshowprice');
UPDATE shopSetting SET value = 'onshowprice' WHERE Namevalue = 'statusshowprice';

-- Button labels
UPDATE textbot SET text = '🛍️ خرید اشتراک' WHERE id_text = 'text_sell';
UPDATE textbot SET text = '🔑 تست رایگان' WHERE id_text = 'text_usertest';
UPDATE textbot SET text = '📦 سرویس‌های من' WHERE id_text = 'text_Purchased_services';
UPDATE textbot SET text = '💳 کیف پول' WHERE id_text = 'accountwallet';
UPDATE textbot SET text = '♻️ تمدید' WHERE id_text = 'text_extend';
UPDATE textbot SET text = '☎️ پشتیبانی' WHERE id_text = 'text_support';
UPDATE textbot SET text = '📚 راهنما' WHERE id_text = 'text_help';
UPDATE textbot SET text = '📌 نوع اشتراک را انتخاب کنید' WHERE id_text = 'text_select_category';
UPDATE textbot SET text = '✨ سرویس ضد فیلتر را انتخاب کنید' WHERE id_text = 'text_service_select';

UPDATE textbot SET text = '✨ سلام {first_name}، به <b>ViraNaut</b> خوش آمدید!

🌐 اینترنت آزاد با کانفیگ‌های <b>ضد فیلتر</b> و پوشش ابری
☁️ پایدار · سریع · مناسب موبایل و دسکتاپ

از منوی شیشه‌ای زیر شروع کنید 👇' WHERE id_text = 'text_start';

UPDATE textbot SET text = '🛡 <b>تعرفه اشتراک‌های ضد فیلتر</b>

👤 <b>تک‌کاربره</b>
• 🚀 آغاز · ۱۰ گیگ · ۳۰ روز — <b>۱۱۹٬۰۰۰</b>
• ⚡ پرطرفدار · ۳۰ گیگ · ۳۰ روز — <b>۱۹۹٬۰۰۰</b> ⭐ به‌صرفه‌تر
• ☁️ VIP · ۲۰ گیگ · ۳۰ روز — <b>۲۴۹٬۰۰۰</b>

👥 <b>دوکاربره</b>
• 💎 دونفره · ۵۰ گیگ · ۳۰ روز — <b>۲۹۹٬۰۰۰</b>

👨👩👧👦 <b>خانوادگی (تا ۵ نفر)</b>
• 🛡️ خانواده · ۱۰۰ گیگ · ۳۰ روز — <b>۴۹۹٬۰۰۰</b> 🏆 بهترین ارزش

همه پلن‌ها روی اینباند ضد فیلتر CDN فعال می‌شوند.' WHERE id_text = 'text_dec_Tariff_list';

DELETE FROM product;
DELETE FROM category;

INSERT INTO category (remark, btn_style) VALUES
('👤 تک‌کاربره', 'primary'),
('👥 دوکاربره', 'success'),
('👨👩👧👦 خانوادگی', 'danger');

-- Panel: Poshesh-Abri / inbound 8
INSERT INTO product
(code_product, name_product, price_product, Volume_constraint, Service_time, Location, agent, note, data_limit_reset, one_buy_status, inbounds, proxies, category, hide_panel, btn_style, limit_ip)
VALUES
('v10',  '🚀 آغاز · ۱۰ گیگ · ۳۰ روز',     '119000', '10',  '30', 'Poshesh-Abri', 'f',
 'ضد فیلتر CDN · مناسب شروع · ۱ دستگاه همزمان', 'no_reset', '0', '8', NULL, '👤 تک‌کاربره', '{}', 'primary', 1),

('v30',  '⚡ پرطرفدار · ۳۰ گیگ · ۳۰ روز', '199000', '30',  '30', 'Poshesh-Abri', 'f',
 'پرفروش‌ترین · ارزش بهتر از پلن ۱۰ گیگ · ۱ دستگاه', 'no_reset', '0', '8', NULL, '👤 تک‌کاربره', '{}', 'success', 1),

('v20v', '☁️ VIP ضد فیلتر · ۲۰ گیگ',     '249000', '20',  '30', 'Poshesh-Abri', 'f',
 'پوشش ابری تقویت‌شده · اولویت پایداری · ۱ دستگاه', 'no_reset', '0', '8', NULL, '👤 تک‌کاربره', '{}', 'primary', 1),

('v50',  '💎 دونفره · ۵۰ گیگ · ۳۰ روز',  '299000', '50',  '30', 'Poshesh-Abri', 'f',
 'ضد فیلتر · تا ۲ دستگاه همزمان', 'no_reset', '0', '8', NULL, '👥 دوکاربره', '{}', 'success', 2),

('v100', '🛡️ خانواده · ۱۰۰ گیگ · ۳۰ روز','499000', '100', '30', 'Poshesh-Abri', 'f',
 'بهترین قیمت هر گیگ · تا ۵ دستگاه همزمان', 'no_reset', '0', '8', NULL, '👨👩👧👦 خانوادگی', '{}', 'danger', 5);
