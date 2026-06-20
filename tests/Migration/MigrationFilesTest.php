<?php
declare(strict_types=1);

namespace Tests\Migration;

use PHPUnit\Framework\TestCase;

final class MigrationFilesTest extends TestCase
{
    /** @dataProvider migrationFileProvider */
    public function test_migration_sql_exists_and_has_create(string $file): void
    {
        $path = dirname(__DIR__, 2) . '/migrations/' . $file;
        $this->assertFileExists($path);
        $sql = file_get_contents($path);
        if ($file === 'viranaut_migrate_3_1_0.sql') {
            $this->assertStringContainsString('viranaut_version', $sql);
            return;
        }
        $this->assertMatchesRegularExpression('/CREATE TABLE/i', $sql);
    }

    public static function migrationFileProvider(): array
    {
        return [
            ['viranaut_migrate.sql'],
            ['viranaut_migrate_2_1_0.sql'],
            ['viranaut_migrate_3_0_0.sql'],
            ['viranaut_migrate_3_1_0.sql'],
        ];
    }

    public function test_version_file_format(): void
    {
        $ver = trim(file_get_contents(dirname(__DIR__, 2) . '/version'));
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+-ViraNaut$/', $ver);
    }

    public function test_agent_panel_tokens_in_migration(): void
    {
        $sql = file_get_contents(dirname(__DIR__, 2) . '/migrations/viranaut_migrate_3_0_0.sql');
        $this->assertStringContainsString('agent_panel_tokens', $sql);
        $this->assertStringContainsString('site_admin_requests', $sql);
    }
}
