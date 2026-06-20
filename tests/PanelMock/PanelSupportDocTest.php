<?php
declare(strict_types=1);

namespace Tests\PanelMock;

use PHPUnit\Framework\TestCase;

final class PanelSupportDocTest extends TestCase
{
    public function test_panel_support_doc_exists(): void
    {
        $path = dirname(__DIR__, 2) . '/docs/PANEL_SUPPORT.md';
        $this->assertFileExists($path);
    }

    /** @dataProvider panelTypeProvider */
    public function test_panel_support_lists_type(string $type): void
    {
        $content = file_get_contents(dirname(__DIR__, 2) . '/docs/PANEL_SUPPORT.md');
        $this->assertStringContainsString($type, $content);
    }

    public static function panelTypeProvider(): array
    {
        return [
            ['marzban'],
            ['pasarguard'],
            ['mirza_agent'],
            ['ilan'],
            ['hiddify'],
            ['mikrotik'],
        ];
    }

    public function test_panels_php_has_ilan_hooks(): void
    {
        $src = file_get_contents(dirname(__DIR__, 2) . '/panels.php');
        $this->assertStringContainsString("case 'ilan'", $src);
        $this->assertStringContainsString("case 'mirza_agent'", $src);
        $this->assertStringContainsString("case 'pasarguard'", $src);
    }

    public function test_hiddify_has_revoke(): void
    {
        $src = file_get_contents(dirname(__DIR__, 2) . '/hiddify.php');
        $this->assertStringContainsString('revokeuserhi', $src);
    }

    public function test_mirza_agent_has_reset(): void
    {
        $src = file_get_contents(dirname(__DIR__, 2) . '/mirza_agent.php');
        $this->assertStringContainsString('reset_usage_service_mirza', $src);
    }
}
