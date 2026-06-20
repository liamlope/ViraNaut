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
    'migrations/viranaut_migrate_3_0_0.sql', 'migrations/viranaut_migrate_3_1_0.sql',
    'migrations/viranaut_migrate_3_2_0_agent_panel.sql', 'inc/agent_ops.php',
    'ViraNaut_manage.sh', 'panel/index.php', 'panel/finance.php',
    'agent-panel/index.php', 'agent-panel/login.php', 'agent-panel/buy.php',
    'agent-panel/services.php', 'agent-panel/inc/layout_head.php',
    'agent-panel/api/dashboard.php', 'agent-panel/api/buy.php', 'agent-panel/api/service_action.php',
    'agent-panel/.htaccess', 'cronbot/agent_webhooks.php',
    'site-admin/index.php', 'ilan.php', 'ilan_panels_bridge.php', 'api/agent.php',
    'docs/PANEL_SUPPORT.md', 'CHANGELOG.md',
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
check(strpos(file_get_contents($root . '/inc/agent_ops.php'), 'agent_buy_service') !== false, 'agent_ops missing');
check(strpos(file_get_contents($root . '/vpnbot/update/keyboard.php'), 'پنل وب نمایندگی') !== false, 'vpnbot missing web panel');
check(is_file($root . '/agent-panel/buy.php'), 'agent-panel buy.php missing');
check(strpos(file_get_contents($root . '/index.php'), 'mirza_site_admin_log_request') !== false, 'site-admin wiring missing');

$ver = trim((string) file_get_contents($root . '/version'));
check($ver !== '', 'version file empty');
check((bool) preg_match('/^\d+\.\d+\.\d+-ViraNaut$/', $ver), 'version format invalid');

$textJson = json_decode(file_get_contents($root . '/text.json'), true);
check(is_array($textJson), 'text.json invalid JSON');

echo "Smoke test: {$checks} checks, " . count($errors) . " failures\n";
if ($errors) {
    foreach ($errors as $e) {
        echo "  FAIL: {$e}\n";
    }
    exit(1);
}
echo "OK — version {$ver}\n";
exit(0);
