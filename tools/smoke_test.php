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

$required = [
    'version', 'text.json', 'function.php', 'index.php', 'admin.php', 'panels.php',
    'mirza_agent.php', 'lang/fa.php', 'lang/en.php', 'lang/ru.php', 'lang/zh.php',
    'cronbot/croncard.php', 'cronbot/card_receipt_prompt.php',
    'migrations/viranaut_migrate.sql', 'migrations/viranaut_migrate_2_1_0.sql',
    'ViraNaut_manage.sh', 'panel/index.php', 'panel/finance.php',
    'agent-panel/index.php', 'site-admin/index.php', 'ilan.php', 'CHANGELOG.md',
];

foreach ($required as $rel) {
    check(is_file($root . '/' . $rel), "missing: {$rel}");
}

check(is_readable($root . '/lang/fa.php'), 'lang/fa.php not readable');
check(strpos(file_get_contents($root . '/panels.php'), 'mirza_agent') !== false, 'panels.php missing mirza_agent');
check(strpos(file_get_contents($root . '/function.php'), 'mirza_languagechange_from_json') !== false, 'unified languagechange missing');
check(strpos(file_get_contents($root . '/cronbot/croncard.php'), 'card_autoconfirm_mode') !== false, 'dual croncard missing');

$ver = trim((string) file_get_contents($root . '/version'));
check($ver !== '', 'version file empty');

echo "Smoke test: {$checks} checks, " . count($errors) . " failures\n";
if ($errors) {
    foreach ($errors as $e) {
        echo "  FAIL: {$e}\n";
    }
    exit(1);
}
echo "OK — version {$ver}\n";
exit(0);
