-- ViraNaut 3.1.0 — version marker + schema fixes
INSERT INTO shopSetting (Namevalue, value) VALUES ('viranaut_version', '3.1.0-ViraNaut')
ON DUPLICATE KEY UPDATE value = '3.1.0-ViraNaut';

-- Per-user language (Mirza 0.2.2 / ViraNaut 3.0) — safe re-run: skip error if column exists
ALTER TABLE `user` ADD COLUMN `lang` VARCHAR(5) NULL DEFAULT 'fa';
