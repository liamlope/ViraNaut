<?php
/**
 * لغو پرداخت‌های cart to cart که cron قدیمی Mirza «بدون بررسی» تأیید کرده است.
 * Usage: php tools/revert_legacy_autoconfirm.php [--no-notify]
 */
declare(strict_types=1);

$notify = !in_array('--no-notify', $argv ?? [], true);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/botapi.php';

mirza_disable_legacy_unreviewed_autoconfirm_settings();
$stats = mirza_revert_all_legacy_unreviewed_card_payments($notify);
mirza_set_pay_setting_value('legacy_unreviewed_autoconfirm_purged', '1');

echo json_encode($stats, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
