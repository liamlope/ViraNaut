<?php
/**
 * بازگرداندن پرداخت‌های reject‌شده توسط purge اشتباه — بدون پیام به کاربر.
 * Usage: php tools/undo_bad_legacy_reverts.php
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/botapi.php';

vira_disable_legacy_unreviewed_autoconfirm_settings();
$stats = vira_undo_bad_legacy_reverts();
vira_set_pay_setting_value('legacy_bad_revert_undone', '1');

echo json_encode($stats, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
