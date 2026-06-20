<?php
/**
 * Smoke test — run: php tools/smoke_test.php
 * Checks file integrity after upgrade (no DB required).
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];
$checks = 0;

function check(bool $ok, string $label): void
{
    global $errors, $checks;
    $checks++;
    if (!$ok) {
        $errors[] = $label;
    }
}

function check_php_syntax(string $root, string $rel): void
{
    global $errors, $checks;
    $checks++;
    $file = $root . '/' . $rel;
    if (!is_file($file)) {
        $errors[] = "syntax: missing {$rel}";
        return;
    }
    $out = [];
    $code = 0;
    exec('php -l ' . escapeshellarg($file) . ' 2>&1', $out, $code);
    if ($code !== 0) {
        $errors[] = "syntax fail: {$rel}";
    }
}

$required = [
    'version', 'text.json', 'function.php', 'index.php', 'admin.php', 'panels.php',
    'mirza_agent.php', 'lang/fa.php', 'lang/en.php', 'lang/ru.php', 'lang/zh.php',
    'cronbot/croncard.php', 'cronbot/card_receipt_prompt.php',
    'migrations/viranaut_migrate.sql', 'migrations/viranaut_migrate_2_1_0.sql',
    'migrations/viranaut_migrate_3_0_0.sql', 'migrations/viranaut_migrate_3_1_0.sql',
    'ViraNaut_manage.sh', 'panel/index.php', 'panel/finance.php',
    'agent-panel/index.php', 'agent-panel/api/dashboard.php', 'agent-panel/api/service_action.php',
    'site-admin/index.php', 'ilan.php', 'ilan_panels_bridge.php', 'api/agent.php',
    'docs/PANEL_SUPPORT.md', 'CHANGELOG.md', 'composer.json', 'phpunit.xml.dist',
];

foreach ($required as $rel) {
    check(is_file($root . '/' . $rel), "missing: {$rel}");
}

check(is_readable($root . '/lang/fa.php'), 'lang/fa.php not readable');
check(strpos(file_get_contents($root . '/panels.php'), 'mirza_agent') !== false, 'panels.php missing mirza_agent');
check(strpos(file_get_contents($root . '/panels.php'), "case 'ilan'") !== false, 'panels.php missing ilan hooks');
check(strpos(file_get_contents($root . '/function.php'), 'mirza_languagechange_from_json') !== false, 'unified languagechange missing');
check(strpos(file_get_contents($root . '/cronbot/croncard.php'), 'card_autoconfirm_mode') !== false, 'dual croncard missing');
check(strpos(file_get_contents($root . '/keyboard.php'), 'agent-panel') !== false, 'keyboard missing agent-panel link');
check(strpos(file_get_contents($root . '/index.php'), 'mirza_site_admin_log_request') !== false, 'site-admin wiring missing');

$ver = trim((string) file_get_contents($root . '/version'));
check($ver !== '', 'version file empty');
check((bool) preg_match('/^\d+\.\d+\.\d+-ViraNaut$/', $ver), 'version format invalid');

$jsonOk = true;
foreach (['text.json', 'tests/Fixtures/telegram/start.json'] as $jf) {
    $p = $root . '/' . $jf;
    if (is_file($p)) {
        json_decode(file_get_contents($p), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $jsonOk = false;
        }
    }
}
check($jsonOk, 'JSON fixture invalid');

if (is_executable('/usr/bin/php') || trim((string) shell_exec('where php 2>nul')) !== '' || trim((string) shell_exec('command -v php 2>/dev/null')) !== '') {
    foreach (['index.php', 'api/agent.php', 'agent-panel/index.php', 'ilan.php'] as $rel) {
        check_php_syntax($root, $rel);
    }
}

echo "Smoke test: {$checks} checks, " . count($errors) . " failures\n";
if ($errors) {
    foreach ($errors as $e) {
        echo "  FAIL: {$e}\n";
    }
    exit(1);
}
echo "OK — version {$ver}\n";
exit(0);
