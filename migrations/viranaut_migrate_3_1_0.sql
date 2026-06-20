-- ViraNaut 3.1.0 — version marker
INSERT INTO shopSetting (Namevalue, value) VALUES ('viranaut_version', '3.1.0-ViraNaut')
ON DUPLICATE KEY UPDATE value = '3.1.0-ViraNaut';
