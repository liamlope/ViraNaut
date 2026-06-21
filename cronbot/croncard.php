<?php
/**
 * Card cron — after SMS autoconfirm delay, add «send receipt» button to invoice messages.
 * Run every minute: * * * * * php /path/to/cronbot/croncard.php
 */
ini_set('error_log', 'error_log');
date_default_timezone_set('Asia/Tehran');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../function.php';

if (mirza_card_sms_autoconfirm_enabled()) {
    require_once __DIR__ . '/card_receipt_prompt.php';
}
