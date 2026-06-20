<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class LangFilesTest extends TestCase
{
    /** @dataProvider langProvider */
    public function test_lang_file_returns_array(string $code): void
    {
        $path = dirname(__DIR__, 2) . '/lang/' . $code . '.php';
        $this->assertFileExists($path);
        $lang = include $path;
        $this->assertIsArray($lang);
        $this->assertNotEmpty($lang);
    }

    public static function langProvider(): array
    {
        return [['fa'], ['en'], ['ru'], ['zh']];
    }

    public function test_text_json_decode(): void
    {
        $j = json_decode(file_get_contents(dirname(__DIR__, 2) . '/text.json'), true);
        $this->assertIsArray($j);
    }
}
