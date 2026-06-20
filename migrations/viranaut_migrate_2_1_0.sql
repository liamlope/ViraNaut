-- ViraNaut 2.1.0 — Mirza 0.2.2 parity migration
INSERT INTO PaySetting (NamePay, ValuePay) VALUES ('card_autoconfirm_mode', 'both')
ON DUPLICATE KEY UPDATE ValuePay = IF(ValuePay = '' OR ValuePay IS NULL, 'both', ValuePay);

INSERT INTO shopSetting (Namevalue, value) VALUES ('viranaut_version', '2.1.0-ViraNaut')
ON DUPLICATE KEY UPDATE value = '2.1.0-ViraNaut';
