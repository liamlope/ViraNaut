<?php

if (!function_exists('vira_ensure_product_panel_schema')) {
    function vira_ensure_product_panel_schema(PDO $pdo): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        foreach (['product' => 'btn_style', 'category' => 'btn_style'] as $table => $col) {
            try {
                $chk = $pdo->query("SHOW COLUMNS FROM `$table` LIKE " . $pdo->quote($col));
                if ($chk && $chk->rowCount() === 0) {
                    $pdo->exec("ALTER TABLE `$table` ADD `$col` VARCHAR(20) NULL DEFAULT NULL");
                }
            } catch (Throwable $e) {
                error_log('vira_ensure_product_panel_schema: ' . $e->getMessage());
            }
        }
    }
}

if (!function_exists('vira_sanitize_btn_style')) {
    function vira_sanitize_btn_style(?string $style): string
    {
        $style = trim((string) $style);
        return in_array($style, ['primary', 'success', 'danger'], true) ? $style : '';
    }
}

if (!function_exists('vira_product_clone_basename')) {
    function vira_product_clone_basename(string $name): string
    {
        $name = trim($name);
        $stripped = preg_replace('/\s*\(\d+\)\s*$/u', '', $name);
        return is_string($stripped) && $stripped !== '' ? trim($stripped) : $name;
    }
}

if (!function_exists('vira_product_next_clone_name')) {
    function vira_product_next_clone_name(PDO $pdo, string $name, ?string $category = null): string
    {
        $base = vira_product_clone_basename($name);
        for ($n = 1; $n < 999; $n++) {
            $candidate = $base . ' (' . str_pad((string) $n, 2, '0', STR_PAD_LEFT) . ')';
            if (!vira_product_name_taken($pdo, $candidate, $category)) {
                return $candidate;
            }
        }
        return $base . ' (' . bin2hex(random_bytes(2)) . ')';
    }
}

if (!function_exists('vira_panel_btn_style_select')) {
    /** @param array<string,string> $styles */
    function vira_panel_btn_style_select(string $name, string $id, string $selected = '', array $styles = [], string $extraClass = ''): void
    {
        if ($styles === [] && function_exists('vira_keyboard_button_styles')) {
            $styles = vira_keyboard_button_styles();
        }
        $cls = 'select select-sm prod-btn-style-select' . ($extraClass !== '' ? ' ' . $extraClass : '');
        echo '<select name="' . htmlspecialchars($name) . '" id="' . htmlspecialchars($id) . '" class="' . htmlspecialchars($cls) . '">';
        foreach ($styles as $val => $label) {
            $sel = ((string) $val === (string) $selected) ? ' selected' : '';
            echo '<option value="' . htmlspecialchars((string) $val) . '"' . $sel . '>' . htmlspecialchars($label) . '</option>';
        }
        echo '</select>';
    }
}
