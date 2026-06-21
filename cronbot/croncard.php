<?php
/**
 * Card cron — ViraNaut: only SMS receipt-button prompt (no legacy «approve without review»).
 * Legacy Mirza croncard auto-approved waiting receipts — permanently removed.
 * Run every minute: * * * * * php /path/to/cronbot/croncard.php
 */
ini_set('error_log', 'error_log');
date_default_timezone_set('Asia/Tehran');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../function.php';

if (function_exists('mirza_ensure_legacy_unreviewed_autoconfirm_removed')) {
    mirza_ensure_legacy_unreviewed_autoconfirm_removed();
}

if (mirza_card_sms_autoconfirm_enabled()) {
    require_once __DIR__ . '/card_receipt_prompt.php';
}
