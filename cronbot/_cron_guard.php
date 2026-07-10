<?php
/**
 * جلوگیری از اجرای هم‌زمان چند نمونه از یک cron (کاهش spike RAM/CPU روی سرورهای کم‌حافظه).
 */
if (!function_exists('vira_cron_try_lock')) {
    function vira_cron_try_lock(string $name): bool
    {
        $name = preg_replace('/[^a-z0-9_-]/i', '', $name);
        if ($name === '') {
            return true;
        }
        $path = sys_get_temp_dir() . '/viranaut_' . $name . '.lock';
        $fp = @fopen($path, 'c');
        if ($fp === false) {
            return true;
        }
        if (!flock($fp, LOCK_EX | LOCK_NB)) {
            fclose($fp);
            return false;
        }
        $GLOBALS['_vira_cron_lock_fp'] = $fp;
        return true;
    }
}
