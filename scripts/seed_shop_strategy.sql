-- Short smart names + day groups (statuscategory=oncategory). No unlimited.

DELETE FROM product;
DELETE FROM category;

INSERT INTO category (remark, btn_style) VALUES
('👤 تک‌کاربره', 'primary'),
('👥 دوکاربره', 'success'),
('👨👩👧👦 خانوادگی', 'danger');

-- name_product = short brand only; volume/days/price come from fields + smart button label
INSERT INTO product
(code_product, name_product, price_product, Volume_constraint, Service_time, Location, agent, note, data_limit_reset, one_buy_status, inbounds, proxies, category, hide_panel, btn_style, limit_ip)
VALUES
-- تک‌کاربره · ۷
('s10_7',  '🚀 آغاز',      '59000',  '10',  '7',   'Poshesh-Abri', 'f', 'CDN ضد فیلتر', 'no_reset', '0', '[1,3,5,8]', NULL, '👤 تک‌کاربره', '{}', 'primary', 1),
('s30_7',  '⚡ پرطرفدار',  '99000',  '30',  '7',   'Poshesh-Abri', 'f', 'CDN ضد فیلتر', 'no_reset', '0', '[1,3,5,8]', NULL, '👤 تک‌کاربره', '{}', 'success', 1),

-- تک‌کاربره · ۱۵
('s10_15', '🚀 آغاز',      '89000',  '10',  '15',  'Poshesh-Abri', 'f', 'CDN ضد فیلتر', 'no_reset', '0', '[1,3,5,8]', NULL, '👤 تک‌کاربره', '{}', 'primary', 1),
('s30_15', '⚡ پرطرفدار',  '149000', '30',  '15',  'Poshesh-Abri', 'f', 'CDN ضد فیلتر', 'no_reset', '0', '[1,3,5,8]', NULL, '👤 تک‌کاربره', '{}', 'success', 1),

-- تک‌کاربره · ۳۰
('s10_30', '🚀 آغاز',      '119000', '10',  '30',  'Poshesh-Abri', 'f', 'CDN ضد فیلتر', 'no_reset', '0', '[1,3,5,8]', NULL, '👤 تک‌کاربره', '{}', 'primary', 1),
('s30_30', '⚡ پرطرفدار',  '199000', '30',  '30',  'Poshesh-Abri', 'f', 'CDN ضد فیلتر', 'no_reset', '0', '[1,3,5,8]', NULL, '👤 تک‌کاربره', '{}', 'success', 1),
('s20_30', '☁️ ویژه',      '249000', '20',  '30',  'Poshesh-Abri', 'f', 'CDN ضد فیلتر', 'no_reset', '0', '[1,3,5,8]', NULL, '👤 تک‌کاربره', '{}', 'primary', 1),

-- دوکاربره · ۱۵ / ۳۰ / ۶۰
('d50_15', '💎 دونفره',   '179000', '50',  '15',  'Poshesh-Abri', 'f', 'CDN ضد فیلتر · ۲ دستگاه', 'no_reset', '0', '[1,3,5,8]', NULL, '👥 دوکاربره', '{}', 'success', 2),
('d50_30', '💎 دونفره',   '299000', '50',  '30',  'Poshesh-Abri', 'f', 'CDN ضد فیلتر · ۲ دستگاه', 'no_reset', '0', '[1,3,5,8]', NULL, '👥 دوکاربره', '{}', 'success', 2),
('d50_60', '💎 دونفره',   '499000', '50',  '60',  'Poshesh-Abri', 'f', 'CDN ضد فیلتر · ۲ دستگاه', 'no_reset', '0', '[1,3,5,8]', NULL, '👥 دوکاربره', '{}', 'success', 2),

-- خانوادگی · ۳۰ / ۹۰ / ۱۸۰
('f100_30',  '🛡️ خانواده', '499000',  '100', '30',  'Poshesh-Abri', 'f', 'CDN ضد فیلتر · ۵ دستگاه', 'no_reset', '0', '[1,3,5,8]', NULL, '👨👩👧👦 خانوادگی', '{}', 'danger', 5),
('f100_90',  '🛡️ خانواده', '1199000', '100', '90',  'Poshesh-Abri', 'f', 'CDN ضد فیلتر · ۵ دستگاه', 'no_reset', '0', '[1,3,5,8]', NULL, '👨👩👧👦 خانوادگی', '{}', 'danger', 5),
('f100_180', '🛡️ خانواده', '1999000', '100', '180', 'Poshesh-Abri', 'f', 'CDN ضد فیلتر · ۵ دستگاه', 'no_reset', '0', '[1,3,5,8]', NULL, '👨👩👧👦 خانوادگی', '{}', 'danger', 5);

UPDATE setting SET statuscategory = 'oncategory' WHERE 1 LIMIT 1;
