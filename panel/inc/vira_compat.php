<?php
/**
 * سازگاری نام توابع mirza_* → vira_* (پنل)
 * توابع قدیمی به‌عنوان alias نگه داشته می‌شوند تا مهاجرت تدریجی ممکن باشد.
 */

if (!function_exists('vira_pay_gateway_defs')) {
    function vira_pay_gateway_defs(): array { return mirza_pay_gateway_defs(); }
    function vira_pay_gateway_profiles(): array { return mirza_pay_gateway_profiles(); }
    function vira_pay_general_defs(): array { return mirza_pay_general_defs(); }
    function vira_pay_get_value(PDO $pdo, string $key): string { return mirza_pay_get_value($pdo, $key); }
    function vira_pay_set_value(PDO $pdo, string $key, string $value): void { mirza_pay_set_value($pdo, $key, $value); }
    function vira_pay_is_on(array $def, string $val): bool { return mirza_pay_is_on($def, $val); }
    function vira_pay_toggle_next(array $def, string $val): string { return mirza_pay_toggle_next($def, $val); }
    function vira_pay_secret_defs(): array { return mirza_pay_secret_defs(); }

    function vira_miniapp_template_ids(): array { return mirza_miniapp_template_ids(); }
    function vira_miniapp_templates(): array { return mirza_miniapp_templates(); }
    function vira_miniapp_template_valid(string $id): bool { return mirza_miniapp_template_valid($id); }
    function vira_miniapp_get_template(?PDO $pdo = null): string { return mirza_miniapp_get_template($pdo); }
    function vira_miniapp_set_template(PDO $pdo, string $id): void { mirza_miniapp_set_template($pdo, $id); }
    function vira_miniapp_preview_url(string $id, ?string $domain = null): string { return mirza_miniapp_preview_url($id, $domain); }
    function vira_miniapp_all_features_list(): array { return mirza_miniapp_all_features_list(); }

    function vira_bot_settings_groups(): array { return mirza_bot_settings_groups(); }
    function vira_bot_cron_defs(): array { return mirza_bot_cron_defs(); }
    function vira_shop_settings_groups(): array { return mirza_shop_settings_groups(); }
    function vira_shop_load_values(PDO $pdo): array { return mirza_shop_load_values($pdo); }
}
