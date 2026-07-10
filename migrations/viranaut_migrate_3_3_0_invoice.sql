-- ViraNaut 3.3.0 — code_product روی invoice (برای بازیابی در پنل)
ALTER TABLE invoice ADD COLUMN IF NOT EXISTS code_product VARCHAR(128) NULL;
