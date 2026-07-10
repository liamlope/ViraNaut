<?php
/**
 * Sync keys from lang/fa.php into text.json (fa section) for bot-texts editor.
 * Run: php tools/sync_lang_to_textjson.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$langFile = $root . '/lang/fa.php';
$jsonFile = $root . '/text.json';

if (!is_file($langFile)) {
    fwrite(STDERR, "lang/fa.php not found\n");
    exit(1);
}
$lang = require $langFile;
if (!is_array($lang)) {
    fwrite(STDERR, "lang/fa.php did not return array\n");
    exit(1);
}

$raw = file_get_contents($jsonFile);
$all = json_decode($raw, true);
if (!is_array($all)) {
    $all = ['fa' => []];
}

function deep_merge(array $base, array $over): array
{
    foreach ($over as $k => $v) {
        if (is_array($v) && isset($base[$k]) && is_array($base[$k])) {
            $base[$k] = deep_merge($base[$k], $v);
        } else {
            $base[$k] = $v;
        }
    }
    return $base;
}

$all['fa'] = deep_merge($all['fa'] ?? [], $lang);
file_put_contents($jsonFile, json_encode($all, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");
echo "Synced lang/fa.php → text.json (fa)\n";
