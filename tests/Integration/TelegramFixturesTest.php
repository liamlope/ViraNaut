<?php
declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

final class TelegramFixturesTest extends TestCase
{
    private function fixture(string $name): array
    {
        $path = dirname(__DIR__) . '/Fixtures/telegram/' . $name;
        $this->assertFileExists($path);
        $data = json_decode(file_get_contents($path), true);
        $this->assertIsArray($data);
        return $data;
    }

    public function test_start_fixture_valid(): void
    {
        $u = $this->fixture('start.json');
        $this->assertSame('/start', $u['message']['text']);
    }

    public function test_setlang_fixture_has_callback(): void
    {
        $u = $this->fixture('setlang.json');
        $this->assertStringStartsWith('setlang', $u['callback_query']['data']);
    }

    public function test_unknown_command_fixture(): void
    {
        $u = $this->fixture('unknown_command.json');
        $this->assertStringStartsWith('/unknown', $u['message']['text']);
    }

    public function test_text_json_valid(): void
    {
        $path = dirname(__DIR__, 2) . '/text.json';
        $data = json_decode(file_get_contents($path), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('btn_keyboard', $data);
    }

    public function test_lang_fa_valid_php_array(): void
    {
        $lang = include dirname(__DIR__, 2) . '/lang/fa.php';
        $this->assertIsArray($lang);
    }

    public function test_index_has_site_admin_hook(): void
    {
        $src = file_get_contents(dirname(__DIR__, 2) . '/index.php');
        $this->assertStringContainsString('mirza_site_admin_log_request', $src);
    }

    public function test_keyboard_has_agent_web_panel(): void
    {
        $src = file_get_contents(dirname(__DIR__, 2) . '/keyboard.php');
        $this->assertStringContainsString('پنل وب نمایندگی', $src);
        $this->assertStringContainsString('agent-panel', $src);
    }
}
