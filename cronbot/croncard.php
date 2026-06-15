<?php
/**
 * Legacy cron alias — old installs still hit croncard.php every minute.
 * Delegates to card_receipt_prompt (receipt button after SMS auto delay).
 */
require_once __DIR__ . '/card_receipt_prompt.php';
