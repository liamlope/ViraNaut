<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class IlanHelpersTest extends TestCase
{
    /** @dataProvider httpOkProvider */
    public function test_ilan_http_ok(array $resp, bool $expected): void
    {
        $this->assertSame($expected, ilan_http_ok($resp));
    }

    public static function httpOkProvider(): array
    {
        return [
            [['status' => 200, 'body' => '{}'], true],
            [['status' => 201, 'body' => '{}'], true],
            [['status' => 404, 'body' => ''], false],
            [['status' => 500, 'body' => ''], false],
            [['status' => 0, 'body' => '{}'], true],
            [['status' => 200, 'error' => 'timeout'], false],
            [['status' => 199, 'body' => ''], false],
        ];
    }

    public function test_ilan_decode_valid_json(): void
    {
        $data = ilan_decode(['body' => '{"username":"u1","ok":true}']);
        $this->assertIsArray($data);
        $this->assertSame('u1', $data['username']);
    }

    public function test_ilan_decode_invalid_json(): void
    {
        $this->assertNull(ilan_decode(['body' => 'not-json']));
    }

    public function test_ilan_decode_empty_body(): void
    {
        $this->assertNull(ilan_decode(['body' => '']));
    }
}
