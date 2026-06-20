<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ViraHandlersTest extends TestCase
{
    /** @dataProvider startParamProvider */
    public function test_vira_is_start_param(string $text, string $param, bool $expected): void
    {
        $this->assertSame($expected, vira_is_start_param($text, $param));
    }

    public static function startParamProvider(): array
    {
        return [
            ['/start charge', 'charge', true],
            ['/start CHARGE', 'charge', true],
            [' /start charge ', 'charge', true],
            ['/start charge_extra', 'charge', false],
            ['/start', 'charge', false],
            ['charge', 'charge', false],
            ['/start setlang', 'setlang', true],
            ['/start help', 'help', true],
            ['/start help me', 'help', false],
        ];
    }

    public function test_vira_is_start_param_rejects_empty_param(): void
    {
        $this->assertFalse(vira_is_start_param('/start ', ''));
    }
}
