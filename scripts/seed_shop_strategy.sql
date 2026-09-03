-- Clean package names + full anti-filter inbound set
-- Inbounds: 1 Azarakhsh-Abri, 3 Almas-Shab, 5 Separ-Paydar, 8 Poshesh-Abri

DELETE FROM product;
DELETE FROM category;

INSERT INTO category (remark, btn_style) VALUES
('👤 تک‌کاربره', 'primary'),
('👥 دوکاربره', 'success'),
('👨👩👧👦 خانوادگی', 'danger');

-- Same naming pattern for every plan: {emoji} {name} · {GB} گیگ · ۳۰ روز
-- تک‌کاربره + خانوادگی: all inbounds [1,3,5,8]
INSERT INTO product
(code_product, name_product, price_product, Volume_constraint, Service_time, Location, agent, note, data_limit_reset, one_buy_status, inbounds, proxies, category, hide_panel, btn_style, limit_ip)
VALUES
('v10',  '🚀 آغاز · ۱۰ گیگ · ۳۰ روز',      '119000', '10',  '30', 'Poshesh-Abri', 'f',
 'CDN ضد فیلتر', 'no_reset', '0', '[1,3,5,8]', NULL, '👤 تک‌کاربره', '{}', 'primary', 1),

('v30',  '⚡ پرطرفدار · ۳۰ گیگ · ۳۰ روز',  '199000', '30',  '30', 'Poshesh-Abri', 'f',
 'CDN ضد فیلتر', 'no_reset', '0', '[1,3,5,8]', NULL, '👤 تک‌کاربره', '{}', 'success', 1),

('v20',  '☁️ ویژه · ۲۰ گیگ · ۳۰ روز',      '249000', '20',  '30', 'Poshesh-Abri', 'f',
 'CDN ضد فیلتر', 'no_reset', '0', '[1,3,5,8]', NULL, '👤 تک‌کاربره', '{}', 'primary', 1),

('v50',  '💎 دونفره · ۵۰ گیگ · ۳۰ روز',   '299000', '50',  '30', 'Poshesh-Abri', 'f',
 'CDN ضد فیلتر · ۲ دستگاه', 'no_reset', '0', '[1,3,5,8]', NULL, '👥 دوکاربره', '{}', 'success', 2),

('v100', '🛡️ خانواده · ۱۰۰ گیگ · ۳۰ روز', '499000', '100', '30', 'Poshesh-Abri', 'f',
 'CDN ضد فیلتر · ۵ دستگاه', 'no_reset', '0', '[1,3,5,8]', NULL, '👨👩👧👦 خانوادگی', '{}', 'danger', 5);
