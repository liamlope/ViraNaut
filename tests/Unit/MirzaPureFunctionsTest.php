<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class MirzaPureFunctionsTest extends TestCase
{
    public function test_mirza_card_autoconfirm_mode_default(): void
    {
        $this->assertSame('both', mirza_card_autoconfirm_mode());
    }

    public function test_mirza_tron_offline_receipt_contains_order(): void
    {
        $msg = mirza_tron_offline_receipt_message('ORD123', 'TWALLET', '10.5', '500,000');
        $this->assertStringContainsString('ORD123', $msg);
        $this->assertStringContainsString('TWALLET', $msg);
        $this->assertStringContainsString('500,000', $msg);
    }

    public function test_mirza_tron_offline_receipt_has_trc20_defaults(): void
    {
        $msg = mirza_tron_offline_receipt_message('X', 'W', '1', '100');
        $this->assertStringContainsString('TRC20', $msg);
        $this->assertStringContainsString('TRON', $msg);
    }

    public function test_mirza_normalize_panel_status_active_variants(): void
    {
        $this->assertSame('active', mirza_normalize_panel_status('active'));
        $this->assertSame('active', mirza_normalize_panel_status('activepanel'));
        $this->assertSame('deactive', mirza_normalize_panel_status('deactive'));
        $this->assertSame('deactive', mirza_normalize_panel_status('inactive'));
    }

    public function test_mirza_panel_is_active_status(): void
    {
        $this->assertTrue(mirza_panel_is_active_status('active'));
        $this->assertFalse(mirza_panel_is_active_status('deactive'));
    }

    public function test_mirza_normalize_domainhosts_value(): void
    {
        $this->assertSame('bot.example.com', mirza_normalize_domainhosts_value('https://bot.example.com/'));
        $this->assertSame('bot.example.com', mirza_normalize_domainhosts_value('http://bot.example.com'));
    }

    public function test_mirza_languagechange_from_json_fa(): void
    {
        $lang = mirza_languagechange_from_json(dirname(__DIR__, 2) . '/lang');
        $this->assertIsArray($lang);
        $this->assertArrayHasKey('users', $lang);
    }

    public function test_mirza_card_autoconfirm_receipt_delay_non_negative(): void
    {
        $delay = mirza_card_autoconfirm_receipt_delay_sec();
        $this->assertGreaterThanOrEqual(0, $delay);
    }

    public function test_mirza_card_sms_autoconfirm_disabled_by_default(): void
    {
        $this->assertFalse(mirza_card_sms_autoconfirm_enabled('offautoconfirm'));
        $this->assertTrue(mirza_card_sms_autoconfirm_enabled('onautoconfirm'));
    }
}
