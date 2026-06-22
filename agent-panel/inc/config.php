<?php

function agent_asset(string $path): string
{
    if (preg_match('#^https?://#', $path)) {
        return $path;
    }
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/agent-panel'), '/\\') . '/';
    return $base . ltrim(str_replace('\\', '/', $path), '/');
}

function agent_panel_asset(string $path): string
{
    if (str_starts_with($path, '../panel/')) {
        $panelBase = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/agent-panel')), '/\\') . '/panel/';
        return $panelBase . substr($path, 9);
    }
    return agent_asset($path);
}

function agent_flash(string $type, string $msg): void
{
    $_SESSION['agent_flash'] = ['type' => $type, 'msg' => $msg];
}

function agent_get_flash(): ?array
{
    if (empty($_SESSION['agent_flash'])) {
        return null;
    }
    $f = $_SESSION['agent_flash'];
    unset($_SESSION['agent_flash']);
    return $f;
}

function agent_lang(PDO $pdo, string $userId): string
{
    return 'fa';
}

function agent_t(string $key, string $lang = 'fa'): string
{
    static $cache = [];
    if (!isset($cache[$lang])) {
        $file = __DIR__ . '/../lang/' . $lang . '.php';
        $cache[$lang] = is_readable($file) ? require $file : [];
    }
    return $cache[$lang][$key] ?? $key;
}
