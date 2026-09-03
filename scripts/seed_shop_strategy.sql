-- Expanded duration ladder + unlimited single-user plans
-- Time filter should be ON (statuscategory=oncategory)

DELETE FROM product;
DELETE FROM category;

INSERT INTO category (remark, btn_style) VALUES
('👤 تک‌کاربره', 'primary'),
('👥 دوکاربره', 'success'),
('👨👩👧👦 خانوادگی', 'danger'),
('♾️ نامحدود', 'primary');

-- Pattern: {emoji} {name} · {volume} · {duration}
-- Inbounds: all anti-filter [1,3,5,8]

INSERT INTO product
(code_product, name_product, price_product, Volume_constraint, Service_time, Location, agent, note, data_limit_reset, one_buy_status, inbounds, proxies, category, hide_panel, btn_style, limit_ip)
VALUES
-- تک‌کاربره · ۷ روز
('s10_7',  '🚀 آغاز · ۱۰ گیگ · ۷ روز',       '59000',  '10',  '7',   'Poshesh-Abri', 'f', 'CDN ضد فیلتر', 'no_reset', '0', '[1,3,5,8]', NULL, '👤 تک‌کاربره', '{}', 'primary', 1),
('s30_7',  '⚡ پرطرفدار · ۳۰ گیگ · ۷ روز',   '99000',  '30',  '7',   'Poshesh-Abri', 'f', 'CDN ضد فیلتر', 'no_reset', '0', '[1,3,5,8]', NULL, '👤 تک‌کاربره', '{}', 'success', 1),

-- تک‌کاربره · ۱۵ روز
('s10_15', '🚀 آغاز · ۱۰ گیگ · ۱۵ روز',      '89000',  '10',  '15',  'Poshesh-Abri', 'f', 'CDN ضد فیلتر', 'no_reset', '0', '[1,3,5,8]', NULL, '👤 تک‌کاربره', '{}', 'primary', 1),
('s30_15', '⚡ پرطرفدار · ۳۰ گیگ · ۱۵ روز',  '149000', '30',  '15',  'Poshesh-Abri', 'f', 'CDN ضد فیلتر', 'no_reset', '0', '[1,3,5,8]', NULL, '👤 تک‌کاربره', '{}', 'success', 1),

-- تک‌کاربره · ۳۰ روز
('s10_30', '🚀 آغاز · ۱۰ گیگ · ۳۰ روز',      '119000', '10',  '30',  'Poshesh-Abri', 'f', 'CDN ضد فیلتر', 'no_reset', '0', '[1,3,5,8]', NULL, '👤 تک‌کاربره', '{}', 'primary', 1),
('s30_30', '⚡ پرطرفدار · ۳۰ گیگ · ۳۰ روز',  '199000', '30',  '30',  'Poshesh-Abri', 'f', 'CDN ضد فیلتر', 'no_reset', '0', '[1,3,5,8]', NULL, '👤 تک‌کاربره', '{}', 'success', 1),
('s20_30', '☁️ ویژه · ۲۰ گیگ · ۳۰ روز',      '249000', '20',  '30',  'Poshesh-Abri', 'f', 'CDN ضد فیلتر', 'no_reset', '0', '[1,3,5,8]', NULL, '👤 تک‌کاربره', '{}', 'primary', 1),

-- دوکاربره · ۱۵ / ۳۰ / ۶۰
('d50_15', '💎 دونفره · ۵۰ گیگ · ۱۵ روز',   '179000', '50',  '15',  'Poshesh-Abri', 'f', 'CDN ضد فیلتر · ۲ دستگاه', 'no_reset', '0', '[1,3,5,8]', NULL, '👥 دوکاربره', '{}', 'success', 2),
('d50_30', '💎 دونفره · ۵۰ گیگ · ۳۰ روز',   '299000', '50',  '30',  'Poshesh-Abri', 'f', 'CDN ضد فیلتر · ۲ دستگاه', 'no_reset', '0', '[1,3,5,8]', NULL, '👥 دوکاربره', '{}', 'success', 2),
('d50_60', '💎 دونفره · ۵۰ گیگ · ۶۰ روز',   '499000', '50',  '60',  'Poshesh-Abri', 'f', 'CDN ضد فیلتر · ۲ دستگاه', 'no_reset', '0', '[1,3,5,8]', NULL, '👥 دوکاربره', '{}', 'success', 2),

-- خانوادگی · ۳۰ / ۹۰ / ۱۸۰
('f100_30',  '🛡️ خانواده · ۱۰۰ گیگ · ۳۰ روز',  '499000',  '100', '30',  'Poshesh-Abri', 'f', 'CDN ضد فیلتر · ۵ دستگاه', 'no_reset', '0', '[1,3,5,8]', NULL, '👨👩👧👦 خانوادگی', '{}', 'danger', 5),
('f100_90',  '🛡️ خانواده · ۱۰۰ گیگ · ۹۰ روز',  '1199000', '100', '90',  'Poshesh-Abri', 'f', 'CDN ضد فیلتر · ۵ دستگاه', 'no_reset', '0', '[1,3,5,8]', NULL, '👨👩👧👦 خانوادگی', '{}', 'danger', 5),
('f100_180', '🛡️ خانواده · ۱۰۰ گیگ · ۱۸۰ روز', '1999000', '100', '180', 'Poshesh-Abri', 'f', 'CDN ضد فیلتر · ۵ دستگاه', 'no_reset', '0', '[1,3,5,8]', NULL, '👨👩👧👦 خانوادگی', '{}', 'danger', 5),

-- نامحدود · فقط تک‌کاربره (Volume=0)
('u0_30',  '♾️ نامحدود · ۳۰ روز',  '349000',  '0', '30',  'Poshesh-Abri', 'f', 'حجم نامحدود · ۱ دستگاه', 'no_reset', '0', '[1,3,5,8]', NULL, '♾️ نامحدود', '{}', 'primary', 1),
('u0_90',  '♾️ نامحدود · ۹۰ روز',  '849000',  '0', '90',  'Poshesh-Abri', 'f', 'حجم نامحدود · ۱ دستگاه', 'no_reset', '0', '[1,3,5,8]', NULL, '♾️ نامحدود', '{}', 'primary', 1),
('u0_180', '♾️ نامحدود · ۱۸۰ روز', '1499000', '0', '180', 'Poshesh-Abri', 'f', 'حجم نامحدود · ۱ دستگاه', 'no_reset', '0', '[1,3,5,8]', NULL, '♾️ نامحدود', '{}', 'primary', 1);
