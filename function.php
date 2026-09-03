<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/panel/inc/bot_emojis.php';
ini_set('error_log', 'error_log');

require_once __DIR__ . '/inc/buy_guard.php';

if (function_exists('vira_ensure_bot_custom_emoji_table')) {
    vira_ensure_bot_custom_emoji_table();
}

if (!function_exists('vira_normalize_panel_status')) {
    /** وضعیت پنل: فقط active یا deactive (مقادیر قدیمی یک‌بار نرمال می‌شوند). */
    function vira_normalize_panel_status($status): string
    {
        $s = strtolower(trim((string) $status));
        if (in_array($s, ['active', 'activepanel'], true)) {
            return 'active';
        }
        return 'deactive';
    }
}

if (!function_exists('vira_panel_is_active_status')) {
    function vira_panel_is_active_status($status): bool
    {
        return vira_normalize_panel_status($status) === 'active';
    }
}

if (!function_exists('vira_create_user_missing_username')) {
    function vira_create_user_missing_username($dataoutput): bool
    {
        return !is_array($dataoutput)
            || !array_key_exists('username', $dataoutput)
            || $dataoutput['username'] === null
            || $dataoutput['username'] === '';
    }
}

if (!function_exists('vira_decode_processing_panel')) {
    function vira_decode_processing_panel($processingValue): ?array
    {
        $decoded = json_decode((string) $processingValue, true);
        if (!is_array($decoded) || empty($decoded['name_panel'])) {
            return null;
        }
        return $decoded;
    }
}

if (!function_exists('vira_try_begin_buy_lock')) {
    function vira_try_begin_buy_lock($user_id, $from_step = 'payment'): bool
    {
        global $pdo;
        $stmt = $pdo->prepare('UPDATE user SET step = ? WHERE id = ? AND step = ?');
        $stmt->execute(['buying_service', (string) $user_id, (string) $from_step]);
        return $stmt->rowCount() > 0;
    }
}

if (!function_exists('vira_try_deduct_user_balance')) {
    function vira_try_deduct_user_balance($user_id, $amount): bool
    {
        global $pdo;
        $amount = (int) $amount;
        if ($amount <= 0) {
            return true;
        }
        $stmt = $pdo->prepare('UPDATE user SET Balance = Balance - :amt WHERE id = :id AND Balance >= :amt');
        $stmt->bindValue(':amt', $amount, PDO::PARAM_INT);
        $stmt->bindValue(':id', (string) $user_id, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }
}

if (!function_exists('vira_refund_user_balance')) {
    function vira_refund_user_balance($user_id, $amount): void
    {
        global $pdo;
        $amount = (int) $amount;
        if ($amount <= 0) {
            return;
        }
        $stmt = $pdo->prepare('UPDATE user SET Balance = Balance + :amt WHERE id = :id');
        $stmt->bindValue(':amt', $amount, PDO::PARAM_INT);
        $stmt->bindValue(':id', (string) $user_id, PDO::PARAM_STR);
        $stmt->execute();
    }
}

if (!function_exists('vira_panel_active_sql')) {
    function vira_panel_active_sql(string $prefix = ''): string
    {
        $col = ($prefix !== '' ? rtrim($prefix, '.') . '.' : '') . 'status';
        return "{$col} = 'active'";
    }
}

if (!function_exists('vira_count_active_panels')) {
    function vira_count_active_panels(): int
    {
        global $pdo;
        try {
            return (int) $pdo->query('SELECT COUNT(*) FROM marzban_panel WHERE ' . vira_panel_active_sql())->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }
}

if (!function_exists('vira_bot_text')) {
    /** متن از textbot با fallback امن */
    function vira_bot_text(array $datatextbot, string $key, $fallback = ''): string
    {
        $t = trim((string) ($datatextbot[$key] ?? ''));
        $fb = trim((string) ($fallback ?? ''));
        return $t !== '' ? $t : $fb;
    }
}

if (!function_exists('vira_lang_str')) {
    /** دسترسی امن به متن‌های تو در تو text.json */
    function vira_lang_str(array $lang, string $path, string $default = ''): string
    {
        $cur = $lang;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($cur) || !array_key_exists($segment, $cur)) {
                return $default;
            }
            $cur = $cur[$segment];
        }
        return is_string($cur) ? $cur : $default;
    }
}

if (!function_exists('vira_idfindeer_hint_html')) {
    /** راهنمای دریافت آیدی عددی تلگرام (HTML) */
    function vira_idfindeer_hint_html(): string
    {
        return "\n\n🆔 آیدی عددی را فقط از <a href=\"https://t.me/IDFindeerBot\">@IDFindeerBot</a> دریافت کنید.";
    }
}

if (!function_exists('vira_idfindeer_hint_plain')) {
    /** راهنمای دریافت آیدی عددی تلگرام (متن ساده) */
    function vira_idfindeer_hint_plain(): string
    {
        return "\n\n🆔 آیدی عددی را فقط از @IDFindeerBot دریافت کنید.";
    }
}

if (!function_exists('vira_prompt_with_idfindeer')) {
    /** افزودن پیشنهاد @IDFindeerBot به پیام‌هایی که آیدی عددی می‌خواهند */
    function vira_prompt_with_idfindeer(string $prompt): string
    {
        if ($prompt === '' || stripos($prompt, 'IDFindeerBot') !== false) {
            return $prompt;
        }
        return rtrim($prompt) . vira_idfindeer_hint_html();
    }
}

if (!function_exists('vira_lang_patch_string_by_path')) {
    function vira_lang_patch_string_by_path(array &$lang, array $path, callable $patcher): void
    {
        $ref = &$lang;
        foreach ($path as $i => $segment) {
            if (!is_array($ref) || !array_key_exists($segment, $ref)) {
                return;
            }
            if ($i === count($path) - 1) {
                if (is_string($ref[$segment])) {
                    $ref[$segment] = $patcher($ref[$segment]);
                }
                return;
            }
            $ref = &$ref[$segment];
        }
    }
}

if (!function_exists('vira_textbotlang_append_idfindeer_hints')) {
    /** افزودن راهنمای @IDFindeerBot به promptهای زبان که آیدی عددی می‌خواهند */
    function vira_textbotlang_append_idfindeer_hints(array &$lang): void
    {
        $paths = [
            ['Admin', 'manageadmin', 'getId'],
            ['Admin', 'manageadmin', 'getid'],
            ['Admin', 'manageUser', 'getIdMessage'],
            ['Admin', 'ManageUser', 'GetIDMessage'],
            ['Admin', 'manageUser', 'getIdUserUnblock'],
            ['Admin', 'ManageUser', 'GetIdUserunblock'],
            ['Admin', 'Balance', 'negativeBalance'],
            ['Admin', 'Balance', 'NegativeBalance'],
            ['Admin', 'transfer', 'description'],
            ['Admin', 'transfor', 'discription'],
            ['Admin', 'adminphp', 'ask_send_user_balance_2'],
            ['Admin', 'adminphp', 'ask_send_panel_user_number'],
            ['Admin', 'adminphp', 'ask_send_admin_number'],
            ['Admin', 'adminphp', 'ask_send_user_number_2'],
            ['Admin', 'adminphp', 'ask_send_user_delete_number'],
            ['extracted', 'admin_php', 'ask_send_user_balance_2'],
            ['extracted', 'admin_php', 'ask_send_panel_user_number'],
            ['extracted', 'admin_php', 'ask_send_admin_number'],
            ['extracted', 'admin_php', 'ask_send_user_number_2'],
            ['extracted', 'admin_php', 'ask_send_user_delete_number'],
        ];
        foreach ($paths as $path) {
            vira_lang_patch_string_by_path($lang, $path, 'vira_prompt_with_idfindeer');
        }
    }
}

if (!function_exists('vira_branding_replacements')) {
    /** جایگزینی نام/لینک legacy در متن‌های نمایشی */
    function vira_branding_replacements(): array
    {
        $brandEn = defined('VIRA_BRAND_NAME') ? VIRA_BRAND_NAME : 'ViraNaut';
        $brandFa = defined('VIRA_BRAND_NAME_FA') ? VIRA_BRAND_NAME_FA : 'ویرا';
        $panelTitle = defined('VIRA_PANEL_TITLE') ? VIRA_PANEL_TITLE : 'پنل مدیریت ویرا';
        $panelShort = defined('VIRA_PANEL_SHORT') ? VIRA_PANEL_SHORT : 'ویرا · پنل';
        $supportUrl = (defined('VIRA_SUPPORT_GROUP') && VIRA_SUPPORT_GROUP !== '')
            ? VIRA_SUPPORT_GROUP
            : (defined('VIRA_GITHUB_URL') ? VIRA_GITHUB_URL : 'https://github.com/liamlope/ViraNaut');

        return [
            'https://t.me/viranaut' => $supportUrl,
            'پنل مدیریت میرزا بات' => $panelTitle,
            'پنل مدیریت ربات میرزا' => $panelTitle,
            'پنل مدیریت میرزا' => $panelTitle,
            'Панель администратора Vira Bot' => $panelTitle,
            'Панель администратора Vira' => $panelTitle,
            '· نسخه 1.0 میرزا' => '· ' . $brandFa,
            '· Версия 1.0 Vira' => '· ' . $brandEn,
            '· نسخه میرزا' => '· ' . $brandFa,
            'پنل نمایندگی میرزا' => 'پنل نمایندگی',
            'نمایندگی میرزا' => 'پنل نمایندگی',
            'Vira Agent' => $brandEn . ' Agent',
            'Vira 代理' => $brandEn,
            'Агент Vira' => $brandEn . ' Agent',
            'Vira Group' => $brandEn,
            'Vira group' => $brandEn,
            'Vira 群组' => $brandEn,
            'группе Vira' => $brandEn,
            'گروه میرزا' => 'پشتیبانی ' . $brandFa,
            'تیم میرزا' => 'تیم ' . $brandFa,
            'Vira team' => $brandEn . ' team',
            'Vira 团队' => $brandEn,
            'Команда Vira' => $brandEn,
            'Vira Bot' => $brandEn,
            'Vira Pro' => $brandEn,
            'Vira' => $brandEn,
            'میرزا' => $brandFa,
            'پنل میرزا' => $panelShort,
        ];
    }
}

if (!function_exists('vira_replace_vira_branding_in_text')) {
    function vira_replace_vira_branding_in_text(string $text): string
    {
        if ($text === '') {
            return $text;
        }
        $lower = strtolower($text);
        if (strpos($lower, 'mirza') === false && mb_strpos($text, 'میرزا') === false && strpos($lower, 'legacypanel') === false) {
            return $text;
        }
        $map = vira_branding_replacements();
        uksort($map, static fn($a, $b) => strlen($b) <=> strlen($a));
        foreach ($map as $from => $to) {
            $text = str_replace($from, $to, $text);
        }
        return $text;
    }
}

if (!function_exists('vira_sanitize_lang_branding_recursive')) {
    function vira_sanitize_lang_branding_recursive(array &$node): void
    {
        foreach ($node as &$v) {
            if (is_array($v)) {
                vira_sanitize_lang_branding_recursive($v);
            } elseif (is_string($v)) {
                $v = vira_replace_vira_branding_in_text($v);
            }
        }
    }
}

if (!function_exists('vira_apply_viranaut_branding')) {
    /** حذف نام legacy از تمام رشته‌های زبان + override برند پنل */
    function vira_apply_viranaut_branding(array &$lang): void
    {
        if (!defined('VIRA_BRAND_NAME') && is_file(__DIR__ . '/panel/inc/brand.php')) {
            require_once __DIR__ . '/panel/inc/brand.php';
        }

        vira_sanitize_lang_branding_recursive($lang);

        $panelTitle = defined('VIRA_PANEL_TITLE') ? VIRA_PANEL_TITLE : 'پنل مدیریت ویرا';
        $panelShort = defined('VIRA_PANEL_SHORT') ? VIRA_PANEL_SHORT : 'ویرا · پنل';
        $brandFa = defined('VIRA_BRAND_NAME_FA') ? VIRA_BRAND_NAME_FA : 'ویرا';

        if (!isset($lang['panel']) || !is_array($lang['panel'])) {
            $lang['panel'] = [];
        }
        $lang['panel']['loginHeading'] = $panelTitle;
        $lang['panel']['loginPanelTitle'] = 'ورود — ' . $panelTitle;
        $lang['panel']['loginPasswordLabel'] = $panelTitle;
        $lang['panel']['loginPasswordPlaceholder'] = '· ' . $brandFa;
        $lang['panel']['layoutBrandName'] = $panelShort;
        $lang['panel']['layoutPageTitleSuffix'] = $brandFa;
        $lang['panel']['keyboardManageTitle'] = $panelTitle;

        if (isset($lang['extracted']['keyboard_php']['viraAgentPanel'])) {
            $lang['extracted']['keyboard_php']['viraAgentPanel'] = 'پنل نمایندگی';
        }
    }
}

if (!function_exists('vira_datatextbot_lang_map')) {
    /** نگاشت id_text → مسیر text.json (textbot) */
    function vira_datatextbot_lang_map(): array
    {
        return [
            'accountwallet' => 'textbot.accountWallet',
            'text_Add_Balance' => 'textbot.addBalance',
            'text_Discount' => 'textbot.discount',
            'text_sell' => 'textbot.sell',
            'text_extend' => 'textbot.extend',
            'text_usertest' => 'textbot.userTest',
            'text_Purchased_services' => 'textbot.purchasedServices',
            'text_support' => 'textbot.support',
            'text_help' => 'textbot.help',
            'text_start' => 'textbot.start',
            'text_bot_off' => 'textbot.botOff',
            'text_fq' => 'textbot.faq',
            'text_Tariff_list' => 'textbot.tariffList',
            'text_affiliates' => 'textbot.affiliates',
            'text_wheel_luck' => 'textbot.wheelLuck',
            'textselectlocation' => 'textbot.selectLocation',
            'text_cart' => 'textbot.cart',
            'text_cart_auto' => 'textbot.cartAuto',
            'carttocart' => 'textbot.cartToCart',
            'textnowpayment' => 'textbot.nowPayment',
            'zarinpal' => 'textbot.zarinPal',
            'textrequestagent' => 'textbot.requestAgent',
            'textpanelagent' => 'textbot.agentPanel',
            'text_select_category' => 'extracted.index_php.selectCategory',
            'text_service_select' => 'users.sell.serviceSelect',
            'text_service_select_first' => 'users.sell.serviceSelectFirst',
            'text_sell_notestep' => 'users.sell.notestep',
        ];
    }
}

if (!function_exists('vira_datatextbot_ensure_defaults')) {
    /** پر کردن کلیدهای خالی datatextbot از text.json */
    function vira_datatextbot_ensure_defaults(array &$datatextbot, array $textbotlang): void
    {
        foreach (vira_datatextbot_lang_map() as $id => $path) {
            if (trim((string) ($datatextbot[$id] ?? '')) !== '') {
                continue;
            }
            $fallback = vira_lang_str($textbotlang, $path);
            if ($fallback !== '') {
                $datatextbot[$id] = $fallback;
            }
        }
    }
}

if (!function_exists('vira_datatextbot_apply_db')) {
    /** ادغام متن‌های دیتابیس روی datatextbot — بدون حذف fallbackهای keyboard.php */
    function vira_datatextbot_apply_db(array &$datatextbot, $pdo): void
    {
        try {
            $rows = $pdo->query('SELECT id_text, text FROM textbot')->fetchAll(PDO::FETCH_KEY_PAIR);
            if (!is_array($rows)) {
                return;
            }
            foreach ($rows as $id => $txt) {
                if (trim((string) $txt) !== '') {
                    $datatextbot[$id] = $txt;
                }
            }
        } catch (Throwable $e) {
            error_log('vira_datatextbot_apply_db: ' . $e->getMessage());
        }
    }
}

if (!function_exists('vira_pay_agent_value')) {
    /** مقدار PaySetting به‌ازای گروه کاربر (f/n/n2) */
    function vira_pay_agent_value($raw, string $agent, $default = 0)
    {
        if (is_numeric($raw)) {
            return $raw;
        }
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return $default;
        }
        if (array_key_exists($agent, $decoded)) {
            return $decoded[$agent];
        }
        if (array_key_exists('f', $decoded)) {
            return $decoded['f'];
        }
        $first = reset($decoded);
        return $first !== false ? $first : $default;
    }
}

if (!function_exists('vira_user_menu_text_is_known')) {
    /** آیا متن، برچسب یکی از دکمه‌های منوی اصلی / نمایندگی است؟ */
    function vira_user_menu_text_is_known(?string $text, array $datatextbot, array $textbotlang, array $user = []): bool
    {
        if ($text === null || trim($text) === '') {
            return false;
        }
        if (function_exists('vira_textbot_matches')) {
            foreach (array_keys(vira_datatextbot_lang_map()) as $key) {
                if (vira_textbot_matches($text, $datatextbot[$key] ?? '')) {
                    return true;
                }
            }
        }
        $extras = [
            $textbotlang['users']['backbtn'] ?? '',
            $textbotlang['users']['agent']['customnameusername'] ?? '',
            $textbotlang['Admin']['textpaneladmin'] ?? '',
            $textbotlang['textbot']['purchasedServices'] ?? '',
            $textbotlang['textbot']['accountWallet'] ?? '',
            '🌐 پنل وب نمایندگی',
            '🗂 خرید انبوه',
            '👤 انتخاب نام دلخواه',
        ];
        foreach ($extras as $label) {
            if ($label === '') {
                continue;
            }
            if ($text === $label) {
                return true;
            }
            if (function_exists('vira_textbot_matches') && vira_textbot_matches($text, $label)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('vira_product_name_taken')) {
    /** نام تکراری فقط در همان دسته ممنوع — دسته‌های مختلف می‌توانند نام یکسان داشته باشند. */
    function vira_product_name_taken(PDO $pdo, string $name, ?string $category = null, ?int $excludeId = null): bool
    {
        $name = trim($name);
        if ($name === '') {
            return false;
        }
        $cat = trim((string) $category);
        $sql = "SELECT COUNT(*) FROM product WHERE name_product = ? AND COALESCE(category, '') = ?";
        $params = [$name, $cat];
        if ($excludeId !== null && $excludeId > 0) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('vira_buy_username_is_taken')) {
    /**
     * آیا نام کاربری در دیتابیس ربات یا پنل قبلاً استفاده شده؟ (سبک‌تر از DataUser کامل)
     */
    function vira_buy_username_is_taken($name_panel, $username_ac, array $usernameinvoice_list)
    {
        if (in_array($username_ac, $usernameinvoice_list, true)) {
            return true;
        }
        $panel = select('marzban_panel', '*', 'name_panel', $name_panel, 'select');
        if (!$panel) {
            return false;
        }
        if (($panel['type'] ?? '') === 'x-ui_single' && function_exists('vira_xui_user_exists_quick')) {
            return vira_xui_user_exists_quick($panel, $username_ac);
        }
        global $ManagePanel;
        $out = $ManagePanel->DataUser($name_panel, $username_ac);
        return isset($out['username']);
    }
}

if (!function_exists('vira_user_tg_username')) {
    /** نام کاربری تلگرام از ردیف user — برای کاربران قدیمی بدون ستون username. */
    function vira_user_tg_username($userRow)
    {
        if (!is_array($userRow)) {
            return 'NOT_USERNAME';
        }
        $u = $userRow['username'] ?? '';
        if ($u === '' || $u === null) {
            return 'NOT_USERNAME';
        }
        return (string) $u;
    }
}

if (!function_exists('vira_normalize_panel_url')) {
    /**
     * نرمال‌سازی آدرس پنل (۳x-ui و غیره).
     * پورت پیش‌فرض https:443 و http:80 حذف می‌شود — وارد کردن :443 اغلب باعث گیر کردن/خطای WAF می‌شود.
     */
    function vira_normalize_panel_url($url)
    {
        $url = trim(str_replace("\r", '', (string) $url));
        if ($url === '' || $url === 'null') {
            return $url;
        }
        $p = parse_url($url);
        if (empty($p['scheme']) || empty($p['host'])) {
            return rtrim($url, '/');
        }
        $scheme = strtolower($p['scheme']);
        $port = isset($p['port']) ? (int) $p['port'] : null;
        if ($scheme === 'https' && $port === 443) {
            $port = null;
        }
        if ($scheme === 'http' && $port === 80) {
            $port = null;
        }
        $host = $p['host'];
        if ($port !== null) {
            $host .= ':' . $port;
        }
        $path = isset($p['path']) ? rtrim($p['path'], '/') : '';
        $query = isset($p['query']) ? '?' . $p['query'] : '';
        $fragment = isset($p['fragment']) ? '#' . $p['fragment'] : '';
        $auth = '';
        if (!empty($p['user'])) {
            $auth = $p['user'];
            if (isset($p['pass'])) {
                $auth .= ':' . $p['pass'];
            }
            $auth .= '@';
        }
        return $scheme . '://' . $auth . $host . $path . $query . $fragment;
    }
}

if (!function_exists('vira_normalize_xui_panel_url')) {
    /**
     * آدرس پایه ۳x-ui = …/RandomPath (بدون /panel در انتها).
     * API خودش مسیر /panel/api/... را اضافه می‌کند.
     */
    function vira_normalize_xui_panel_url($url)
    {
        $url = vira_normalize_panel_url($url);
        if (preg_match('#/panel/?$#i', $url)) {
            $url = preg_replace('#/panel/?$#i', '', $url);
            $url = rtrim($url, '/');
        }
        return $url;
    }
}

if (!function_exists('vira_panel_url_is_valid')) {
    function vira_panel_url_is_valid($url)
    {
        $url = vira_normalize_panel_url($url);
        if ($url === '' || $url === 'null') {
            return false;
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        $p = parse_url($url);
        return !empty($p['scheme']) && !empty($p['host'])
            && in_array(strtolower($p['scheme']), array('http', 'https'), true);
    }
}

if (!function_exists('bot_site_https_url')) {
    /** Full https URL for this bot site (avoids https://https:// when $domainhosts already had a scheme). */
    function bot_site_https_url($path = '')
    {
        global $domainhosts;
        $host = function_exists('vira_normalize_domainhosts_value')
            ? vira_normalize_domainhosts_value($domainhosts ?? '')
            : trim((string) ($domainhosts ?? ''));
        if ($host === '') {
            return '';
        }
        $path = ltrim((string) $path, '/');
        return $path === '' ? "https://{$host}" : "https://{$host}/{$path}";
    }
}

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Label\Font\OpenSans;
use Endroid\QrCode\Label\LabelAlignment;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

#-----------shell helper utilities------------#
function isShellExecAvailable()
{
    static $isAvailable;

    if ($isAvailable !== null) {
        return $isAvailable;
    }

    if (!function_exists('shell_exec')) {
        $isAvailable = false;
        return $isAvailable;
    }

    $disabledFunctions = ini_get('disable_functions');
    if (!empty($disabledFunctions) && stripos($disabledFunctions, 'shell_exec') !== false) {
        $isAvailable = false;
        return $isAvailable;
    }

    $isAvailable = true;
    return $isAvailable;
}

function getCrontabBinary()
{
    static $resolvedPath;

    if ($resolvedPath !== null) {
        return $resolvedPath ?: null;
    }

    $candidateDirectories = [
        '/usr/local/bin',
        '/usr/bin',
        '/bin',
        '/usr/sbin',
        '/sbin',
    ];

    $environmentPath = getenv('PATH');
    if ($environmentPath !== false && $environmentPath !== '') {
        foreach (explode(PATH_SEPARATOR, $environmentPath) as $pathDirectory) {
            $pathDirectory = trim($pathDirectory);
            if ($pathDirectory !== '' && !in_array($pathDirectory, $candidateDirectories, true)) {
                $candidateDirectories[] = $pathDirectory;
            }
        }
    }

    foreach ($candidateDirectories as $directory) {
        $executablePath = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'crontab';
        if (@is_file($executablePath) && @is_executable($executablePath)) {
            $resolvedPath = $executablePath;
            return $resolvedPath;
        }
    }

    if (isShellExecAvailable()) {
        $whichOutput = @shell_exec('command -v crontab 2>/dev/null');
        if (is_string($whichOutput)) {
            $whichOutput = trim($whichOutput);
            if ($whichOutput !== '' && @is_executable($whichOutput)) {
                $resolvedPath = $whichOutput;
                return $resolvedPath;
            }
        }
    }

    $resolvedPath = '';
    error_log('Unable to locate the crontab executable on this system.');

    return null;
}

function runShellCommand($command)
{
    if (!isShellExecAvailable()) {
        error_log('shell_exec is not available; unable to run command: ' . $command);
        return null;
    }

    if (getenv('PATH') === false || trim((string) getenv('PATH')) === '') {
        putenv('PATH=/usr/local/bin:/usr/bin:/bin');
    }

    return shell_exec($command);
}

function deleteDirectory($directory)
{
    if (!file_exists($directory)) {
        return true;
    }

    if (!is_dir($directory)) {
        return @unlink($directory);
    }

    $items = scandir($directory);
    if ($items === false) {
        return false;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $directory . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            if (!deleteDirectory($path)) {
                return false;
            }
        } else {
            if (!@unlink($path)) {
                return false;
            }
        }
    }

    return @rmdir($directory);
}

function ensureTableUtf8mb4($table)
{
    global $pdo;

    try {
        $stmt = $pdo->prepare('SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $stmt->execute([$table]);
        $currentCollation = $stmt->fetchColumn();

        if ($currentCollation === false) {
            error_log("Failed to detect current collation for table {$table}");
            return false;
        }

        if (stripos((string) $currentCollation, 'utf8mb4') === 0) {
            return true;
        }

        $pdo->exec("ALTER TABLE `{$table}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        return true;
    } catch (PDOException $e) {
        error_log('Failed to convert table to utf8mb4: ' . $e->getMessage());
        return false;
    }
}

function ensureCardNumberTableSupportsUnicode()
{
    global $connect;

    if (!isset($connect) || !($connect instanceof mysqli)) {
        return;
    }

    try {
        if (method_exists($connect, 'character_set_name') && $connect->character_set_name() !== 'utf8mb4') {
            if (!$connect->set_charset('utf8mb4')) {
                error_log('Failed to enforce utf8mb4 charset on mysqli connection: ' . $connect->error);
            }
        }

        if (!$connect->query("SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'")) {
            error_log('Failed to execute SET NAMES utf8mb4 for card_number table: ' . $connect->error);
        }

        $createQuery = "CREATE TABLE IF NOT EXISTS card_number (" .
            "cardnumber varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci PRIMARY KEY," .
            "namecard varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL" .
            ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        if (!$connect->query($createQuery)) {
            error_log('Failed to create card_number table with utf8mb4 charset: ' . $connect->error);
        }

        ensureTableUtf8mb4('card_number');

        $columnInfo = $connect->query("SHOW FULL COLUMNS FROM card_number WHERE Field IN ('cardnumber', 'namecard')");
        if ($columnInfo instanceof mysqli_result) {
            while ($column = $columnInfo->fetch_assoc()) {
                $collation = $column['Collation'] ?? '';
                if (!is_string($collation) || stripos($collation, 'utf8mb4') === false) {
                    $field = $column['Field'];
                    $type = $field === 'cardnumber' ? 'varchar(500)' : 'varchar(1000)';
                    $alter = sprintf(
                        "ALTER TABLE card_number MODIFY %s %s CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci%s",
                        $field,
                        $type,
                        $field === 'cardnumber' ? ' PRIMARY KEY' : ' NOT NULL'
                    );
                    if (!$connect->query($alter)) {
                        error_log('Failed to update card_number column collation: ' . $connect->error);
                    }
                }
            }
            $columnInfo->free();
        } else {
            error_log('Unable to inspect card_number column collations: ' . $connect->error);
        }
    } catch (\Throwable $e) {
        error_log('Unexpected error while ensuring card_number utf8mb4 compatibility: ' . $e->getMessage());
    }
}

function normaliseUpdateValue($value)
{
    if (is_array($value) || is_object($value)) {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    return $value;
}

function copyDirectoryContents($source, $destination)
{
    if (!is_dir($source)) {
        return false;
    }

    if (!is_dir($destination) && !mkdir($destination, 0777, true) && !is_dir($destination)) {
        return false;
    }

    $items = scandir($source);
    if ($items === false) {
        return false;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $sourcePath = $source . DIRECTORY_SEPARATOR . $item;
        $destinationPath = $destination . DIRECTORY_SEPARATOR . $item;

        if (is_dir($sourcePath)) {
            if (!copyDirectoryContents($sourcePath, $destinationPath)) {
                return false;
            }
        } else {
            if (!@copy($sourcePath, $destinationPath)) {
                return false;
            }
        }
    }

    return true;
}

#-----------function------------#
function step($step, $from_id, array $options = [])
{
    global $pdo;

    if (empty($options['skip_card_cancel'])) {
        $prev = select('user', 'step', 'id', $from_id, 'select');
        $prevStep = is_array($prev) ? (string) ($prev['step'] ?? '') : '';
        $paymentSteps = vira_card_payment_flow_steps();
        $leavingPayment = in_array($prevStep, $paymentSteps, true)
            && !in_array($step, $paymentSteps, true);
        if ($leavingPayment) {
            vira_card_cancel_unpaid_invoices((string) $from_id);
        }
    }

    $stmt = $pdo->prepare('UPDATE user SET step = ? WHERE id = ?');
    $stmt->execute([$step, $from_id]);
    clearSelectCache('user');
}
function determineColumnTypeFromValue($value)
{
    if (is_bool($value)) {
        return 'TINYINT(1)';
    }

    if (is_int($value)) {
        return 'INT(11)';
    }

    if (is_float($value)) {
        return 'DOUBLE';
    }

    if ($value === null) {
        return 'VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
    }

    if (is_string($value)) {
        if (function_exists('mb_strlen')) {
            $length = mb_strlen($value, 'UTF-8');
        } else {
            $length = strlen($value);
        }

        if ($length <= 191) {
            return 'VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
        }

        if ($length <= 500) {
            return 'VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
        }

        return 'TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
    }

    return 'TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
}
function ensureColumnExistsForUpdate($tableName, $fieldName, $valueSample = null)
{
    global $pdo;

    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
        $stmt->execute([$tableName, $fieldName]);
        if ((int) $stmt->fetchColumn() > 0) {
            return;
        }

        $datatype = determineColumnTypeFromValue($valueSample);

        $defaultValue = null;
        if (is_bool($valueSample)) {
            $defaultValue = $valueSample ? '1' : '0';
        } elseif (is_scalar($valueSample) && $valueSample !== null) {
            $defaultValue = (string) $valueSample;
        }

        addFieldToTable($tableName, $fieldName, $defaultValue, $datatype);
    } catch (PDOException $e) {
        error_log('Failed to ensure column exists: ' . $e->getMessage());
    }
}
function ensureColumnCanStoreValue($tableName, $fieldName, $valueSample)
{
    global $pdo;

    if (!is_string($valueSample)) {
        return false;
    }

    try {
        $stmt = $pdo->prepare('SELECT data_type, character_maximum_length FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1');
        $stmt->execute([$tableName, $fieldName]);
        $column = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$column) {
            return false;
        }

        $dataType = strtolower((string) ($column['data_type'] ?? ''));
        $maxLength = isset($column['character_maximum_length']) ? (int) $column['character_maximum_length'] : 0;
        $valueLength = function_exists('mb_strlen') ? mb_strlen($valueSample, 'UTF-8') : strlen($valueSample);

        $targetType = null;
        if ($dataType === 'varchar' && $valueLength > $maxLength) {
            $targetType = $valueLength > 500 ? 'TEXT' : 'VARCHAR(500)';
        } elseif ($dataType === 'text' && $valueLength > 65000) {
            $targetType = 'LONGTEXT';
        }

        if ($targetType === null) {
            return false;
        }

        $sqlType = $targetType . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
        $pdo->exec("ALTER TABLE `$tableName` MODIFY `$fieldName` $sqlType NULL");
        return true;
    } catch (PDOException $e) {
        error_log('Failed to enlarge column for update: ' . $e->getMessage());
        return false;
    }
}
function update($table, $field, $newValue, $whereField = null, $whereValue = null)
{
    global $pdo, $user;

    $valueToStore = normaliseUpdateValue($newValue);

    ensureColumnExistsForUpdate($table, $field, $valueToStore);

    $executeUpdate = function ($value) use ($pdo, $table, $field, $whereField, $whereValue) {
        if ($whereField !== null) {
            $stmt = $pdo->prepare("SELECT $field FROM $table WHERE $whereField = ? FOR UPDATE");
            $stmt->execute([$whereValue]);
            $stmt = $pdo->prepare("UPDATE $table SET $field = ? WHERE $whereField = ?");
            $stmt->execute([$value, $whereValue]);
        } else {
            $stmt = $pdo->prepare("UPDATE $table SET $field = ?");
            $stmt->execute([$value]);
        }
    };

    try {
        $executeUpdate($valueToStore);
    } catch (PDOException $e) {
        $message = (string) $e->getMessage();
        if (strpos($message, 'Data too long for column') !== false || strpos($message, 'SQLSTATE[22001]') !== false) {
            if (ensureColumnCanStoreValue($table, $field, (string) $valueToStore)) {
                $executeUpdate($valueToStore);
            } else {
                throw $e;
            }
        } elseif (strpos($message, 'Incorrect string value') !== false) {
            $tableConverted = ensureTableUtf8mb4($table);
            if ($tableConverted) {
                try {
                    $executeUpdate($valueToStore);
                } catch (PDOException $retryException) {
                    error_log('Retry after charset conversion failed: ' . $retryException->getMessage());
                    throw $retryException;
                }
            } else {
                $fallbackValue = is_string($valueToStore) ? @iconv('UTF-8', 'UTF-8//IGNORE', $valueToStore) : $valueToStore;
                if ($fallbackValue === false) {
                    $fallbackValue = '';
                }
                $executeUpdate($fallbackValue);
            }
        } else {
            throw $e;
        }
    }

    $date = date("Y-m-d H:i:s");
    if (!isset($user['step'])) {
        $user['step'] = '';
    }
    $logValue = is_scalar($valueToStore) ? $valueToStore : json_encode($valueToStore, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $logss = "{$table}_{$field}_{$logValue}_{$whereField}_{$whereValue}_{$user['step']}_$date";
    if ($field != "message_count" || $field != "last_message_time") {
        file_put_contents('log.txt', "\n" . $logss, FILE_APPEND);
    }

    clearSelectCache($table);
}
function &getSelectCacheStore()
{
    static $store = [
    'results' => [],
    'tableIndex' => [],
    ];

    return $store;
}

function clearSelectCache($table = null)
{
    $store = &getSelectCacheStore();

    if ($table === null) {
        $store['results'] = [];
        $store['tableIndex'] = [];
        return;
    }

    if (!isset($store['tableIndex'][$table])) {
        return;
    }

    foreach (array_keys($store['tableIndex'][$table]) as $cacheKey) {
        unset($store['results'][$cacheKey]);
    }

    unset($store['tableIndex'][$table]);
}

/** migration/seed یک‌بار در هر request — فقط از کد tracked (git pull)، نه config.php */
function vira_runtime_bootstrap(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    global $pdo;
    if (!isset($pdo)) {
        return;
    }
    $done = true;
    if (function_exists('vira_ensure_user_lang_column')) {
        vira_ensure_user_lang_column();
    }
    if (function_exists('vira_ensure_marzban_panel_columns')) {
        vira_ensure_marzban_panel_columns();
    }
    if (function_exists('vira_ensure_legacy_unreviewed_autoconfirm_removed')) {
        vira_ensure_legacy_unreviewed_autoconfirm_removed();
    }
    if (function_exists('vira_ensure_setting_schema')) {
        vira_ensure_setting_schema();
    }
    if (function_exists('vira_setting_try_seed')) {
        vira_setting_try_seed();
    }
}

function select($table, $field, $whereField = null, $whereValue = null, $type = "select", $options = [])
{
    vira_runtime_bootstrap();
    global $pdo;

    $useCache = true;
    if (is_array($options) && array_key_exists('cache', $options)) {
        $useCache = (bool) $options['cache'];
    }

    $cacheKey = null;
    if ($useCache) {
        $cacheKey = hash('sha256', json_encode([
            $table,
            $field,
            $whereField,
            $whereValue,
            $type,
        ], JSON_UNESCAPED_UNICODE));

        $store = &getSelectCacheStore();
        if (isset($store['results'][$cacheKey])) {
            return $store['results'][$cacheKey];
        }
    }

    $query = "SELECT $field FROM $table";

    if ($whereField !== null) {
        $query .= " WHERE $whereField = :whereValue";
    }

    try {
        $stmt = $pdo->prepare($query);
        if ($whereField !== null) {
            $stmt->bindParam(':whereValue', $whereValue, PDO::PARAM_STR);
        }

        $stmt->execute();
        if ($type == "count") {
            $result = $stmt->rowCount();
        } elseif ($type == "FETCH_COLUMN") {
            $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if ($table === 'admin' && $field === 'id_admin') {
                global $adminnumber;
                if (!is_array($results)) {
                    $results = [];
                }

                $results = array_values(array_unique(array_filter($results, function ($value) {
                    return $value !== null && $value !== '';
                })));

                if (empty($results) && isset($adminnumber) && $adminnumber !== '') {
                    $results[] = (string) $adminnumber;
                }
            }
            $result = $results;
        } elseif ($type == "fetchAll") {
            $result = $stmt->fetchAll();
        } else {
            $fetched = $stmt->fetch(PDO::FETCH_ASSOC);
            $result = $fetched === false ? null : $fetched;
        }
    } catch (PDOException $e) {
        error_log($e->getMessage());
        die("Query failed: " . $e->getMessage());
    }

    if ($useCache && $cacheKey !== null) {
        $store = &getSelectCacheStore();
        $store['results'][$cacheKey] = $result;
        if (!isset($store['tableIndex'][$table])) {
            $store['tableIndex'][$table] = [];
        }
        $store['tableIndex'][$table][$cacheKey] = true;
    }

    return $result;
}

function getPaySettingValue($name, $default = null)
{
    $result = select("PaySetting", "ValuePay", "NamePay", $name, "select");
    if (!is_array($result) || !array_key_exists('ValuePay', $result)) {
        return $default;
    }

    return $result['ValuePay'];
}

/** تأیید خودکار کارت از طریق SMS بانک (statuscardautoconfirm) */
function vira_card_sms_autoconfirm_enabled(?string $value = null): bool
{
    if ($value === null) {
        $value = (string) getPaySettingValue('statuscardautoconfirm', 'offautoconfirm');
    }
    return $value === 'onautoconfirm';
}

function vira_card_sms_autoconfirm_inline_keyboard(?string $value = null): string
{
    global $textbotlang;
    $on = vira_card_sms_autoconfirm_enabled($value);
    $label = $on
        ? ($textbotlang['Admin']['Status']['statuson'] ?? '✅ روشن')
        : ($textbotlang['Admin']['Status']['statusoff'] ?? '❌ خاموش');
    $callback = $on ? 'onautoconfirm' : 'offautoconfirm';

    return json_encode([
        'inline_keyboard' => [
            [['text' => $label, 'callback_data' => $callback]],
        ],
    ]);
}

/** آیدی کانال تلگرام برای SMS Forwarder (DB key: card_sms_telegram_group_id) */
function vira_card_sms_effective_channel_id(): ?int
{
    $raw = trim((string) getPaySettingValue('card_sms_telegram_group_id', ''));
    if ($raw === '' || !preg_match('/^-?\d+$/', $raw)) {
        return null;
    }
    return (int) $raw;
}

/** @deprecated alias */
function vira_card_sms_effective_group_id(): ?int
{
    return vira_card_sms_effective_channel_id();
}

function vira_card_sms_normalize_digits(string $text): string
{
    static $from = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    static $to   = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    return str_replace($from, $to, $text);
}

/** رشته عددی (با کاما) → ریال صحیح */
function vira_card_sms_amount_string_to_rial(string $amountStr): int
{
    $amountStr = vira_card_sms_normalize_digits($amountStr);
    return (int) preg_replace('/\D/u', '', $amountStr);
}

/** ریال → تومان (فقط برای نمایش — تطبیق فاکتور همیشه با ریال است) */
function vira_card_sms_rial_to_toman(int $rial): int
{
    return intdiv($rial, 10);
}

/** مبلغ فاکتور (تومان در DB) → ریال مورد انتظار برای واریز */
function vira_card_sms_invoice_expected_rial(int $priceToman): int
{
    return $priceToman * 10;
}

function vira_card_sms_normalize_whitespace(string $text): string
{
    $text = preg_replace('/[\x{200C}\x{200B}\x{00A0}\x{202F}\x{FEFF}]/u', ' ', $text);
    return preg_replace('/[ \t]+/u', ' ', $text);
}

function vira_card_sms_clean_text(string $smsText): string
{
    $smsText = vira_card_sms_normalize_digits($smsText);
    $smsText = vira_card_sms_normalize_whitespace($smsText);
    $lines = [];
    foreach (preg_split('/\R/u', $smsText) as $line) {
        $stripped = trim($line);
        if ($stripped === '') {
            continue;
        }
        if (preg_match('/\(Incoming\s*-/iu', $stripped)) {
            continue;
        }
        if (preg_match('/^From\s*:/iu', $stripped)) {
            continue;
        }
        if (preg_match('/^(Sender|Phone|From\s*Number)\s*:/iu', $stripped)) {
            continue;
        }
        if (preg_match('/^\+\d{10,}\s*$/u', $stripped)) {
            continue;
        }
        if (preg_match('/^\d{1,2}:\d{2}\s*$/u', $stripped)) {
            continue;
        }
        if (preg_match('#^\d{4}[./]\d{1,2}[./]\d{1,2}\s*$#u', $stripped)) {
            continue;
        }
        if (preg_match('/^واریز\s*پول\s*$/u', $stripped)) {
            continue;
        }
        $lines[] = $stripped;
    }
    return implode("\n", $lines);
}

/** مبلغ ریالی خط «21,000,000+» (مهر / قرض‌الحسنه و مشابه) */
function vira_card_sms_parse_amount_suffix_plus(string $text): ?int
{
    foreach (preg_split('/\R/u', $text) as $line) {
        $line = trim($line);
        if ($line === '' || preg_match('/مانده\s*:/u', $line)) {
            continue;
        }
        if (preg_match('/^([\d,]+)\+$/u', $line, $m)) {
            $rial = vira_card_sms_amount_string_to_rial($m[1]);
            if ($rial > 0) {
                return $rial;
            }
        }
    }
    if (preg_match('/(\d{1,3}(?:,\d{3})*)\+/u', $text, $m)) {
        $rial = vira_card_sms_amount_string_to_rial($m[1]);
        if ($rial > 0) {
            return $rial;
        }
    }
    return null;
}

/** بانک‌هایی که مبلغ با «+» در انتهای خط مشخص است — فیلتر 000 اعمال نشود */
function vira_card_sms_skip_round_toman_reject(string $bankCode): bool
{
    return in_array(strtolower($bankCode), ['gharz', 'mehr', 'parsian'], true);
}

/** بلو — فقط خط واریز با «ریال … به حساب شما نشست» (نه موجودی) */
function vira_card_sms_parse_blu_amount(string $text): ?int
{
    $patterns = [
        '/(\d[\d,،٬]+)\s*ریال\s*به\s*حساب\s*شما\s*نشست/u',
        '/(\d[\d,،٬]+)\s*ريال\s*به\s*حساب\s*شما\s*نشست/u',
        '/(\d[\d,،٬]+)\s*ریال[^.\n]{0,80}?نشست/u',
        '/(\d[\d,،٬]+)\s*ريال[^.\n]{0,80}?نشست/u',
    ];
    foreach (preg_split('/\R/u', $text) as $line) {
        if (!preg_match('/نشست/u', $line)) {
            continue;
        }
        if (preg_match('/موجودی/u', $line) && !preg_match('/به\s*حساب/u', $line)) {
            continue;
        }
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $line, $m)) {
                $rial = vira_card_sms_amount_string_to_rial($m[1]);
                if ($rial > 0) {
                    return $rial;
                }
            }
        }
    }
    if (preg_match('/(\d[\d,،٬]+)\s*ریال[^.\n]{0,120}?به\s*حساب[^.\n]{0,40}?نشست/u', $text, $m)) {
        $rial = vira_card_sms_amount_string_to_rial($m[1]);
        if ($rial > 0) {
            return $rial;
        }
    }
    return null;
}

/** +مبلغ در انتهای خط — فقط خطوط مبلغ (نه شماره From/SMS Forwarder) */
function vira_card_sms_parse_plus_amount_line(string $text): ?int
{
    foreach (preg_split('/\R/u', $text) as $line) {
        $line = trim($line);
        if ($line === '' || preg_match('/From\s*:|موجودی|مانده|^\+\d{10,}/u', $line)) {
            continue;
        }
        if (preg_match('/\+([\d,]+)/u', $line, $m)) {
            $digits = vira_card_sms_amount_string_to_rial($m[1]);
            if ($digits > 0 && strlen((string) $digits) <= 12) {
                return $digits;
            }
        }
    }
    return null;
}

function vira_card_sms_parse_bank_rial(string $bankCode, string $smsText): ?int
{
    $text = vira_card_sms_clean_text($smsText);
    if ($text === '') {
        return null;
    }

    $amountRial = null;
    switch (strtolower($bankCode)) {
        case 'blu':
            $amountRial = vira_card_sms_parse_blu_amount($text);
            break;
        case 'meli':
            if (preg_match('/انتقال:(.*?)[+\-]/u', $text, $m)) {
                $amountRial = vira_card_sms_amount_string_to_rial($m[1]);
            }
            break;
        case 'grdsh':
            if (preg_match('/مبلغ:\s*([0-9,]+)/u', $text, $m)) {
                $amountRial = vira_card_sms_amount_string_to_rial($m[1]);
            }
            break;
        case 'sadhrat':
            if (preg_match('/انتقال:\s*([\d,]+)/u', $text, $m)) {
                $amountRial = vira_card_sms_amount_string_to_rial($m[1]);
            }
            break;
        case 'melet':
            if (preg_match('/واریز(\d{1,3}(?:,\d{3})*)/u', $text, $m)) {
                $amountRial = vira_card_sms_amount_string_to_rial($m[1]);
            }
            break;
        case 'terjart':
            if (preg_match('/واریز\s*:\s*([\d,]+)/u', $text, $m)) {
                $amountRial = vira_card_sms_amount_string_to_rial($m[1]);
            }
            break;
        case 'keshavarsi':
            if (preg_match('/واريز(\d+(?:,\d+)*)/u', $text, $m)) {
                $amountRial = vira_card_sms_amount_string_to_rial($m[1]);
            }
            break;
        case 'resalet':
            $amountRial = vira_card_sms_parse_plus_amount_line($text);
            break;
        case 'sheahr':
            if (preg_match('/مبلغ:(\d+(?:,\d+)*)ريال/u', $text, $m)) {
                $amountRial = vira_card_sms_amount_string_to_rial($m[1]);
            }
            break;
        case 'maskan':
            if (preg_match('/انتقال اينترنت:\D*([\d,]+)/u', $text, $m)) {
                $amountRial = vira_card_sms_amount_string_to_rial($m[1]);
            }
            break;
        case 'parsian':
            if (preg_match('/مبلغ:(\d{1,3}(?:,\d{3})*)\+/u', $text, $m)) {
                $amountRial = vira_card_sms_amount_string_to_rial($m[1]);
            }
            break;
        case 'sphe':
            if (preg_match('/مبلغ:\s*([\d,]+)\s*ريال/u', $text, $m)) {
                $amountRial = vira_card_sms_amount_string_to_rial($m[1]);
            }
            break;
        case 'paselc':
            $amountRial = vira_card_sms_parse_plus_amount_line($text);
            break;
        case 'gharz':
        case 'mehr':
            $amountRial = vira_card_sms_parse_amount_suffix_plus($text);
            break;
    }

    if ($amountRial === null || $amountRial <= 0) {
        return null;
    }
    $toman = vira_card_sms_rial_to_toman($amountRial);
    if (!vira_card_sms_skip_round_toman_reject($bankCode) && substr((string) $toman, -3) === '000') {
        return null;
    }
    return $amountRial;
}

/** @deprecated alias */
function vira_card_sms_parse_bank_amount(string $bankCode, string $smsText): ?int
{
    $rial = vira_card_sms_parse_bank_rial($bankCode, $smsText);
    return $rial !== null ? vira_card_sms_rial_to_toman($rial) : null;
}

function vira_card_sms_parsed_result(string $bank, int $amountRial): array
{
    return [
        'bank' => $bank,
        'amount_rial' => $amountRial,
        'amount_toman' => vira_card_sms_rial_to_toman($amountRial),
    ];
}

function vira_card_sms_parse_from_text(string $smsText, ?string $bankCode = null): ?array
{
    $text = vira_card_sms_clean_text($smsText);
    if ($text === '') {
        return null;
    }

    $banks = ['blu', 'meli', 'grdsh', 'sadhrat', 'melet', 'terjart', 'keshavarsi', 'resalet', 'sheahr', 'maskan', 'parsian', 'sphe', 'paselc', 'mehr', 'gharz'];

    // اگر نام بلو در متن باشد اول بلو را امتحان کن
    if ($bankCode === null && preg_match('/بلو/ui', $text)) {
        $rial = vira_card_sms_parse_bank_rial('blu', $text);
        if ($rial !== null) {
            return vira_card_sms_parsed_result('blu', $rial);
        }
    }

    if ($bankCode !== null && $bankCode !== '') {
        $code = strtolower($bankCode);
        if ($code === 'gharz') {
            $code = 'mehr';
        }
        $rial = vira_card_sms_parse_bank_rial($code, $text);
        return $rial !== null ? vira_card_sms_parsed_result($code, $rial) : null;
    }

    // مهر / قرض‌الحسنه: «21,000,000+» + «مانده:» — اولویت parse
    if (preg_match('/مانده\s*:/u', $text) && preg_match('/[\d,]+\+/u', $text)) {
        foreach (['mehr', 'gharz'] as $code) {
            $rial = vira_card_sms_parse_bank_rial($code, $text);
            if ($rial !== null) {
                return vira_card_sms_parsed_result($code, $rial);
            }
        }
    }

    foreach ($banks as $code) {
        $rial = vira_card_sms_parse_bank_rial($code, $text);
        if ($rial !== null) {
            return vira_card_sms_parsed_result($code, $rial);
        }
    }
    return null;
}

function vira_card_sms_extract_update_text(array $update): string
{
    $msg = $update['channel_post'] ?? $update['message'] ?? null;
    if (!is_array($msg)) {
        return '';
    }
    $parts = [];
    foreach (['text', 'caption'] as $key) {
        if (!empty($msg[$key]) && is_string($msg[$key])) {
            $parts[] = trim($msg[$key]);
        }
    }
    if (!empty($msg['quote']['text']) && is_string($msg['quote']['text'])) {
        $parts[] = trim($msg['quote']['text']);
    }
    return trim(implode("\n", array_filter($parts)));
}

function vira_card_sms_get_update_chat(array $update): ?array
{
    $msg = $update['channel_post'] ?? $update['message'] ?? null;
    if (!is_array($msg) || empty($msg['chat']) || !is_array($msg['chat'])) {
        return null;
    }
    return $msg['chat'];
}

/**
 * پردازش SMS و تأیید خودکار فاکتور کارت — کانال تلگرام + payment/card.php
 * @return array{ok:bool,reason?:string,order_id?:string,amount_rial?:int,amount_toman?:int,bank?:string}
 */
function vira_card_sms_process_and_approve(string $smsText, ?string $bankCode = null): array
{
    global $connect, $setting, $textbotlang;

    if (!vira_card_sms_autoconfirm_enabled()) {
        return ['ok' => false, 'reason' => 'disabled'];
    }

    vira_card_expire_abandoned_unpaid();

    $parsed = vira_card_sms_parse_from_text($smsText, $bankCode);
    if ($parsed === null) {
        $snippet = mb_substr(vira_card_sms_clean_text($smsText), 0, 280);
        error_log('[card-sms] parse_failed snippet=' . $snippet);
        return ['ok' => false, 'reason' => 'parse_failed'];
    }

    $amountRial = (int) $parsed['amount_rial'];
    $amountToman = (int) $parsed['amount_toman'];
    global $pdo;
    $orderId = null;
    $matchCutoff = date('Y/m/d H:i:s', time() - vira_card_sms_match_max_age_sec());
    try {
        $pdo->beginTransaction();
        $decSql = vira_card_sms_match_dec_sql_clause();
        $matchStmt = $pdo->prepare(
            "SELECT id_order, id_user FROM Payment_report
             WHERE Payment_Method = 'cart to cart'
               AND payment_Status IN ('Unpaid', 'waiting')
               AND {$decSql}
               AND (CAST(price AS UNSIGNED) * 10) = :rial
               AND time >= :cutoff
             ORDER BY time DESC, id_order DESC
             LIMIT 1
             FOR UPDATE"
        );
        $matchStmt->execute([':rial' => $amountRial, ':cutoff' => $matchCutoff]);
        $matchRow = $matchStmt->fetch(PDO::FETCH_ASSOC);
        if (!$matchRow || empty($matchRow['id_order'])) {
            $pdo->rollBack();
            return [
                'ok' => false,
                'reason' => 'no_match',
                'amount_rial' => $amountRial,
                'amount_toman' => $amountToman,
                'bank' => $parsed['bank'],
            ];
        }
        $orderId = (string) $matchRow['id_order'];
        $pdo->commit();
        error_log('[card-sms] matched order=' . $orderId . ' user=' . ($matchRow['id_user'] ?? '') . ' rial=' . $amountRial);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('vira_card_sms_process_and_approve: ' . $e->getMessage());
        return ['ok' => false, 'reason' => 'db_error'];
    }

    $Payment_report = select('Payment_report', '*', 'id_order', $orderId, 'select');
    if (!is_array($Payment_report) || empty($Payment_report['price'])) {
        return ['ok' => false, 'reason' => 'order_missing'];
    }
    if (!vira_card_sms_may_auto_approve($Payment_report)) {
        return [
            'ok' => false,
            'reason' => 'manual_receipt_only',
            'order_id' => $orderId,
            'amount_rial' => $amountRial,
            'amount_toman' => $amountToman,
            'bank' => $parsed['bank'],
        ];
    }
    $expectedRial = vira_card_sms_invoice_expected_rial((int) $Payment_report['price']);
    if ($amountRial !== $expectedRial) {
        return [
            'ok' => false,
            'reason' => 'no_match',
            'amount_rial' => $amountRial,
            'amount_toman' => $amountToman,
            'bank' => $parsed['bank'],
        ];
    }
    if (in_array($Payment_report['payment_Status'], ['paid', 'reject'], true)) {
        return ['ok' => false, 'reason' => 'already_reviewed', 'order_id' => $orderId];
    }

    if (!vira_payment_try_claim($orderId)) {
        return ['ok' => false, 'reason' => 'already_reviewed', 'order_id' => $orderId];
    }

    if (!DirectPayment($orderId, __DIR__ . '/images.jpg', true)) {
        vira_payment_revert_claim($orderId);
        error_log('[card-sms] process_failed order=' . $orderId);
        return ['ok' => false, 'reason' => 'process_failed', 'order_id' => $orderId];
    }

    update('Payment_report', 'dec_not_confirmed', 'sms_auto_confirmed', 'id_order', $orderId);

    if (!is_array($textbotlang)) {
        $textbotlang = languagechange('text.json');
    }
    $Balance_id = select('user', '*', 'id', $Payment_report['id_user'], 'select');
    $balanceformatsell = number_format((int) ($Balance_id['Balance'] ?? 0), 0);
    $paymentreports = select('topicid', 'idreport', 'report', 'paymentreport', 'select')['idreport'] ?? '';
    $text_report = sprintf(
        $textbotlang['paymentGateway']['reportCard'] ?? '💳 %s تومان — کاربر %s',
        $Payment_report['price'],
        $Balance_id['id'] ?? '',
        $Balance_id['username'] ?? '',
        $balanceformatsell,
        $orderId
    );
    if (is_array($setting) && strlen((string) ($setting['Channel_Report'] ?? '')) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $paymentreports,
            'text' => $text_report,
            'parse_mode' => 'HTML',
        ]);
    }

    return [
        'ok' => true,
        'order_id' => $orderId,
        'amount_rial' => $amountRial,
        'amount_toman' => $amountToman,
        'bank' => $parsed['bank'],
    ];
}

/** Handler کانال تلگرام برای SMS Forwarder — فقط channel_post (نه گروه) */
function vira_try_handle_card_sms_telegram_update(?array $update): bool
{
    if ($update === null || $update === []) {
        return false;
    }

    if (!vira_card_sms_autoconfirm_enabled()) {
        return false;
    }

    $channelId = vira_card_sms_effective_channel_id();
    if ($channelId === null) {
        return false;
    }

    if (empty($update['channel_post']) || !is_array($update['channel_post'])) {
        return false;
    }

    $chat = $update['channel_post']['chat'] ?? null;
    if (!is_array($chat) || (string) ($chat['type'] ?? '') !== 'channel') {
        return false;
    }
    if ((int) ($chat['id'] ?? 0) !== $channelId) {
        return false;
    }

    $smsText = vira_card_sms_extract_update_text($update);
    if ($smsText === '') {
        error_log('[card-sms-telegram] empty channel_post text chat=' . ($chat['id'] ?? ''));
        return true;
    }

    $result = vira_card_sms_process_and_approve($smsText);
    $level = !empty($result['ok']) ? 'OK' : ($result['reason'] ?? 'fail');
    error_log('[card-sms-telegram] ' . $level . ' ' . json_encode($result, JSON_UNESCAPED_UNICODE));

    return true;
}

function vira_card_sms_panel_info(): array
{
    $channelId = vira_card_sms_effective_channel_id();
    global $domainhosts;
    $domain = trim((string) ($domainhosts ?? ''));
    return [
        'sms_enabled' => vira_card_sms_autoconfirm_enabled(),
        'channel_configured' => $channelId !== null,
        'group_configured' => $channelId !== null,
        'receipt_delay_label' => vira_card_receipt_delay_label_fa(),
        'forwarder_note' => 'کانال خصوصی بسازید → ربات فروش + SMS Forwarder هر دو ادمین کانال → Forwarder را به همین کانال وصل کنید (نه گروه).',
        'http_path' => $domain !== '' ? ('https://' . $domain . '/payment/card.php') : '',
    ];
}

/** تأخیر نمایش دکمه «ارسال رسید» — دقیقه (پیش‌فرض ۱۰، از PaySetting: cardreceiptdelaymin) */
function vira_card_receipt_delay_minutes(): int
{
    $min = (int) getPaySettingValue('cardreceiptdelaymin', '10');
    if ($min < 1) {
        $min = 1;
    }
    if ($min > 1440) {
        $min = 1440;
    }
    return $min;
}

function vira_card_autoconfirm_receipt_delay_sec(): int
{
    return vira_card_receipt_delay_minutes() * 60;
}

/** برچسب فارسی تأخیر برای پیام‌های ربات، مثلاً «10 دقیقه» */
function vira_card_receipt_delay_label_fa(): string
{
    $min = vira_card_receipt_delay_minutes();
    if ($min >= 60 && $min % 60 === 0) {
        $hours = intdiv($min, 60);
        return $hours === 1 ? '۱ ساعت' : ($hours . ' ساعت');
    }
    return $min . ' دقیقه';
}

function vira_card_receipt_wait_user_message(): string
{
    $delay = vira_card_receipt_delay_label_fa();
    return "⏳ تأیید خودکار در حال انجام است.\nلطفاً تا {$delay} صبر کنید — بعد از آن دکمه «ارسال رسید» ظاهر می‌شود.";
}

function vira_card_sms_auto_pending_marker(): string
{
    return 'sms_auto:' . (time() + vira_card_autoconfirm_receipt_delay_sec());
}

function vira_card_is_sms_auto_pending(?string $dec): bool
{
    $dec = trim((string) $dec);
    return $dec === 'sms_auto' || str_starts_with($dec, 'sms_auto:');
}

/** SQL: فاکتورهای واجد تأیید خودکار SMS (پنجره SMS یا بعد از ارسال رسید دستی) */
function vira_card_sms_match_dec_sql_clause(): string
{
    return "(dec_not_confirmed = 'sms_auto'
        OR dec_not_confirmed LIKE 'sms_auto:%'
        OR dec_not_confirmed = 'receipt_ready'
        OR dec_not_confirmed = 'receipt_submitted')";
}

/** حداکثر سن فاکتور برای تطبیق SMS (۷۲ ساعت) */
function vira_card_sms_match_max_age_sec(): int
{
    return 72 * 3600;
}

/** آیا این فاکتور هنوز واجد تأیید خودکار SMS است؟ */
function vira_card_sms_may_auto_approve(array $row): bool
{
    $status = (string) ($row['payment_Status'] ?? '');
    if (!in_array($status, ['Unpaid', 'waiting'], true)) {
        return false;
    }
    $dec = trim((string) ($row['dec_not_confirmed'] ?? ''));
    if (in_array($dec, ['receipt_ready', 'receipt_submitted'], true)) {
        return true;
    }
    return vira_card_is_sms_auto_pending($dec);
}

function vira_card_sms_auto_receipt_due(?string $dec, array $row): bool
{
    $dec = trim((string) $dec);
    if (str_starts_with($dec, 'sms_auto:')) {
        $due = (int) substr($dec, 9);
        if ($due > 0) {
            return time() >= $due;
        }
    }
    if ($dec === 'sms_auto') {
        $ts = vira_parse_payment_report_time($row['time'] ?? null)
            ?? vira_parse_payment_report_time($row['at_updated'] ?? null);
        if ($ts === null) {
            return true;
        }
        return $ts <= time() - vira_card_autoconfirm_receipt_delay_sec();
    }
    return false;
}

function vira_card_receipt_prompt_sql_pending(): string
{
    // فقط پنجره SMS — نه receipt_submitted/ready (جدا از تطبیق SMS)
    return "(dec_not_confirmed = 'sms_auto' OR dec_not_confirmed LIKE 'sms_auto:%')";
}

/** فاکتورهایی که زمان تأخیر دکمه رسیدشان رسیده (برای cron) */
function vira_card_receipt_prompt_sql_due(): string
{
    $now = time();
    return "(dec_not_confirmed = 'sms_auto'
        OR (dec_not_confirmed LIKE 'sms_auto:%' AND CAST(SUBSTRING(dec_not_confirmed, 10) AS UNSIGNED) <= {$now}))";
}

/** آیا این فاکتور هنوز در فاز «فقط SMS» است؟ */
function vira_card_receipt_awaiting_sms_window(array $row): bool
{
    $dec = trim((string) ($row['dec_not_confirmed'] ?? ''));
    if (in_array($dec, ['receipt_ready', 'receipt_submitted', 'sms_auto_confirmed'], true)) {
        return false;
    }
    return vira_card_is_sms_auto_pending($dec);
}

/** شماره کارت ذخیره‌شده در id_invoice — card:NUMBER|... */
function vira_card_invoice_stored_card(array $row): string
{
    $inv = (string) ($row['id_invoice'] ?? '');
    if (preg_match('/^card:(\d+)\|/u', $inv, $m)) {
        return (string) $m[1];
    }
    return '';
}

/** بخش پرداخت id_invoice بدون پیشوند card: — برای DirectPayment و parse نوع فاکتور */
function vira_card_invoice_payment_payload(string $idInvoice): string
{
    $idInvoice = trim($idInvoice);
    if (preg_match('/^card:\d+\|(.+)$/u', $idInvoice, $m)) {
        return (string) $m[1];
    }
    return $idInvoice;
}

/** فاکتورهای رها‌شده Unpaid را expire می‌کند تا SMS روی فاکتور قدیمی نخورد */
function vira_card_expire_abandoned_unpaid(int $maxAgeHours = 72): int
{
    global $pdo;
    $cutoff = date('Y/m/d H:i:s', time() - ($maxAgeHours * 3600));
    $stmt = $pdo->prepare(
        "UPDATE Payment_report
         SET payment_Status = 'expire', dec_not_confirmed = 'stale_expired'
         WHERE Payment_Method = 'cart to cart'
           AND payment_Status = 'Unpaid'
           AND time < :cutoff"
    );
    $stmt->execute([':cutoff' => $cutoff]);
    return $stmt->rowCount();
}

/** اتمیک: فقط یک worker دکمه رسید را فعال می‌کند */
function vira_card_try_claim_receipt_prompt(string $orderId): bool
{
    global $pdo;
    if ($orderId === '') {
        return false;
    }
    $pending = vira_card_receipt_prompt_sql_pending();
    $stmt = $pdo->prepare(
        "UPDATE Payment_report SET dec_not_confirmed = 'receipt_ready'
         WHERE id_order = :oid
           AND payment_Status = 'Unpaid'
           AND {$pending}"
    );
    $stmt->execute([':oid' => $orderId]);
    return $stmt->rowCount() > 0;
}

/** پیام یادآور ارسال رسید دستی (بعد از تأخیر SMS) */
function vira_card_receipt_fallback_user_message(): string
{
    return "⏱ تأیید خودکار از SMS انجام نشد.\n\n"
        . "اگر واریز کردید → دکمه زیر را بزنید و عکس رسید را بفرستید.\n"
        . "اگر قبلاً رسید فرستادید → منتظر بررسی بمانید یا به پشتیبانی پیام دهید.\n"
        . "وضعیت پرداخت: منوی «حساب کاربری» در ربات.";
}

function vira_card_receipt_submitted_user_message(): string
{
    return "✅ رسید شما ثبت شده و در انتظار بررسی است.\n"
        . "پس از تأیید، موجودی یا سرویس به‌صورت خودکار اعمال می‌شود.\n"
        . "اگر تأیید نشد، به پشتیبانی پیام دهید.";
}

/** فقط از Unpaid → waiting — جلوگیری از بازنویسی paid و تأیید دوباره */
function vira_card_try_mark_receipt_waiting(string $orderId): bool
{
    global $pdo;
    if ($orderId === '') {
        return false;
    }
    $now = date('Y/m/d H:i:s');
    $stmt = $pdo->prepare(
        "UPDATE Payment_report
         SET payment_Status = 'waiting', dec_not_confirmed = 'receipt_submitted', at_updated = :now
         WHERE id_order = :oid AND payment_Status = 'Unpaid'"
    );
    $stmt->execute([':oid' => $orderId, ':now' => $now]);
    return $stmt->rowCount() > 0;
}

function vira_card_receipt_submission_blocked_reply(int|string $chatId, string $orderId): void
{
    $row = select('Payment_report', '*', 'id_order', $orderId, 'select');
    if (!is_array($row)) {
        sendmessage($chatId, '❌ خطایی در هنگام دریافت اطلاعات رخ داده است.', null, 'HTML');
        return;
    }
    $status = (string) ($row['payment_Status'] ?? '');
    if ($status === 'paid') {
        sendmessage($chatId, '❗️ این تراکنش قبلاً تأیید شده است.', null, 'HTML');
        return;
    }
    if ($status === 'waiting') {
        sendmessage($chatId, vira_card_receipt_submitted_user_message(), null, 'HTML');
        return;
    }
    if ($status === 'expire') {
        sendmessage($chatId, '❗ زمان این تراکنش به پایان رسیده است.', null, 'HTML');
        return;
    }
    if ($status === 'reject') {
        sendmessage($chatId, '❗ این تراکنش رد شده است.', null, 'HTML');
        return;
    }
    sendmessage($chatId, '❌ امکان ثبت رسید برای این فاکتور وجود ندارد.', null, 'HTML');
}

function vira_card_receipt_prompt_for_order(string $orderId): void
{
    global $pdo;
    if ($orderId === '' || !vira_card_sms_autoconfirm_enabled()) {
        return;
    }
    $pendingSql = vira_card_receipt_prompt_sql_pending();
    $stmt = $pdo->prepare(
        "SELECT * FROM Payment_report
         WHERE id_order = :oid
           AND Payment_Method = 'cart to cart'
           AND payment_Status = 'Unpaid'
           AND {$pendingSql}"
    );
    $stmt->bindValue(':oid', $orderId, PDO::PARAM_STR);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (is_array($row)) {
        vira_card_receipt_prompt_apply_row($row);
    }
}

/** متن قدیمی/طولانی کارت خودکار (نسخه legacy) */
function vira_is_legacy_cart_auto_text(string $text): bool
{
    return str_contains($text, 'تایید فوری')
        || str_contains($text, '====================')
        || str_contains($text, 'لزومی به ارسال رسید')
        || str_contains($text, 'واریز با تأیید خودکار')
        || (str_contains($text, 'شارژ کیف پول · تأیید خودکار') && !str_contains($text, '48 ساعت'))
        || str_contains($text, '۱۰ دقیقه') || str_contains($text, '10 دقیقه')
        || str_contains($text, 'بعد از ۱ دقیقه') || str_contains($text, 'بعد از 1 دقیقه');
}

/** متن فاکتور کارت خودکار — legacy را با نسخهٔ جدید جایگزین می‌کند */
function vira_resolve_cart_auto_text(string $stored, array $textbotlang): string
{
    $stored = trim($stored);
    if ($stored === '' || vira_is_legacy_cart_auto_text($stored)) {
        $stored = vira_lang_str($textbotlang, 'textbot.cartAuto', '');
    }
    return strtr($stored, ['{receipt_delay}' => vira_card_receipt_delay_label_fa()]);
}

/** کیبورد inline فاکتور کارت */
function vira_card_payment_inline_keyboard(string $orderId, string $cardNumber, $priceCopy, bool $showReceipt, bool $showCopy): ?string
{
    $rows = [];
    if ($showCopy && $cardNumber !== '') {
        $rows[] = [
            ['text' => 'کپی شماره کارت', 'copy_text' => ['text' => $cardNumber]],
            ['text' => 'کپی مبلغ', 'copy_text' => ['text' => (string) $priceCopy]],
        ];
    }
    if ($showReceipt) {
        $rows[] = [
            ['text' => '✅ پرداخت کردم | ارسال رسید', 'callback_data' => 'sendresidcart-' . $orderId],
        ];
    }
    if ($rows === []) {
        return null;
    }
    return json_encode(['inline_keyboard' => $rows]);
}

/** زمان ثبت فاکتور Payment_report → timestamp */
function vira_parse_payment_report_time(?string $timeStr): ?int
{
    $timeStr = trim((string) $timeStr);
    if ($timeStr === '') {
        return null;
    }
    $tz = new DateTimeZone('Asia/Tehran');
    foreach (['Y/m/d H:i:s', 'Y-m-d H:i:s', 'Y/m/d H:i', 'Y-m-d H:i'] as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $timeStr, $tz);
        if ($dt instanceof DateTime) {
            return $dt->getTimestamp();
        }
    }
    $ts = strtotime($timeStr);
    return $ts !== false ? (int) $ts : null;
}

function vira_card_payment_receipt_due(array $row): bool
{
    return vira_card_sms_auto_receipt_due($row['dec_not_confirmed'] ?? '', $row);
}

/** دکمه «ارسال رسید» را فقط روی پیام فاکتور اضافه می‌کند — بدون پیام جدا */
function vira_card_receipt_prompt_apply_row(array $row): bool
{
    if (!vira_card_receipt_awaiting_sms_window($row)) {
        return false;
    }
    if ((string) ($row['payment_Status'] ?? '') !== 'Unpaid') {
        return false;
    }
    if (!vira_card_payment_receipt_due($row)) {
        return false;
    }

    $orderId = (string) ($row['id_order'] ?? '');
    if ($orderId === '') {
        return false;
    }

    $msgId = (int) ($row['message_id'] ?? 0);
    $chatId = (int) ($row['id_user'] ?? 0);
    if ($chatId <= 0 || $msgId <= 0) {
        error_log('[card-receipt-prompt] missing message_id order=' . $orderId);
        return false;
    }

    if (!vira_card_try_claim_receipt_prompt($orderId)) {
        return false;
    }

    global $connect, $setting;
    if (!isset($setting) || !is_array($setting)) {
        $setting = select('setting', '*');
    }

    $cardNumber = vira_card_invoice_stored_card($row);
    if ($cardNumber === '' && isset($connect)) {
        $cardQuery = mysqli_query($connect, 'SELECT cardnumber FROM card_number ORDER BY RAND() LIMIT 1');
        if ($cardQuery) {
            $cardRow = mysqli_fetch_assoc($cardQuery);
            $cardNumber = (string) ($cardRow['cardnumber'] ?? '');
            mysqli_free_result($cardQuery);
        }
    }

    $priceCopy = (string) ((int) ($row['price'] ?? 0)) . '0';
    $showCopy = (($setting['statuscopycart'] ?? '0') == '1') && $cardNumber !== '';
    $keyboard = vira_card_payment_inline_keyboard($orderId, $cardNumber, $priceCopy, true, $showCopy);
    if ($keyboard === null) {
        return true;
    }

    $response = telegram('editMessageReplyMarkup', [
        'chat_id' => $chatId,
        'message_id' => $msgId,
        'reply_markup' => $keyboard,
    ]);
    if (!empty($response['ok'])) {
        return true;
    }
    $desc = (string) ($response['description'] ?? '');
    if (stripos($desc, 'message is not modified') !== false
        || stripos($desc, 'message to edit not found') !== false) {
        return true;
    }

    error_log('[card-receipt-prompt] edit failed order=' . $orderId . ' ' . json_encode($response, JSON_UNESCAPED_UNICODE));

    $keyboardReceiptOnly = vira_card_payment_inline_keyboard($orderId, '', $priceCopy, true, false);
    if ($keyboardReceiptOnly === null) {
        return true;
    }
    $retry = telegram('editMessageReplyMarkup', [
        'chat_id' => $chatId,
        'message_id' => $msgId,
        'reply_markup' => $keyboardReceiptOnly,
    ]);
    if (empty($retry['ok']) && stripos((string) ($retry['description'] ?? ''), 'message is not modified') === false) {
        error_log('[card-receipt-prompt] retry failed order=' . $orderId . ' ' . json_encode($retry, JSON_UNESCAPED_UNICODE));
        $prevDec = trim((string) ($row['dec_not_confirmed'] ?? 'sms_auto'));
        if ($prevDec !== '' && $prevDec !== 'receipt_ready') {
            update('Payment_report', 'dec_not_confirmed', $prevDec, 'id_order', $orderId);
        }
        return false;
    }
    return !empty($retry['ok']) || stripos((string) ($retry['description'] ?? ''), 'message is not modified') !== false;
}

function vira_card_receipt_prompt_for_user(string $userId): void
{
    global $pdo;

    if (!vira_card_sms_autoconfirm_enabled() || $userId === '' || $userId === '0') {
        return;
    }

    $pendingSql = vira_card_receipt_prompt_sql_pending();
    $stmt = $pdo->prepare(
        "SELECT * FROM Payment_report
         WHERE id_user = :uid
           AND Payment_Method = 'cart to cart'
           AND payment_Status = 'Unpaid'
           AND {$pendingSql}"
    );
    $stmt->bindValue(':uid', $userId, PDO::PARAM_STR);
    $stmt->execute();

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (vira_card_payment_receipt_due($row)) {
            vira_card_receipt_prompt_apply_row($row);
        }
    }
}

/** بعد از تأخیر تنظیم‌شده، دکمه ارسال رسید را به پیام فاکتور اضافه می‌کند */
function vira_card_receipt_prompt_run(): void
{
    global $pdo;

    if (!vira_card_sms_autoconfirm_enabled()) {
        return;
    }

    vira_card_expire_abandoned_unpaid();

    $dueSql = vira_card_receipt_prompt_sql_due();
    $stmt = $pdo->prepare(
        "SELECT * FROM Payment_report
         WHERE Payment_Method = 'cart to cart'
           AND payment_Status = 'Unpaid'
           AND {$dueSql}"
    );
    $stmt->execute();

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (vira_card_payment_receipt_due($row)) {
            vira_card_receipt_prompt_apply_row($row);
        }
    }
}

/** مراحل جریان پرداخت که با خروج کاربر باید فاکتور کارت لغو شود */
function vira_card_payment_flow_steps(): array
{
    return ['get_step_payment', 'card_invoice_pending', 'cart_to_cart_user'];
}

/** آیا کاربر فاکتور کارت Unpaid یا waiting دارد؟ */
function vira_card_user_has_pending(string $userId): bool
{
    global $pdo;

    if ($userId === '' || $userId === '0') {
        return false;
    }

    $stmt = $pdo->prepare(
        "SELECT 1 FROM Payment_report
         WHERE id_user = :uid
           AND Payment_Method = 'cart to cart'
           AND payment_Status IN ('Unpaid', 'waiting')
         LIMIT 1"
    );
    $stmt->bindValue(':uid', $userId, PDO::PARAM_STR);
    $stmt->execute();

    return (bool) $stmt->fetchColumn();
}

/** قبل از ساخت فاکتور جدید — همهٔ فاکتورهای باز کاربر لغو می‌شود */
function vira_card_prepare_new_invoice(string $userId): void
{
    vira_card_cancel_unpaid_invoices($userId, false, 'replaced_by_new');
    vira_card_expire_abandoned_unpaid();
}

/** مارکر legacy — cron «تأیید بدون بررسی» (فقط برای شناسایی در DB) */
function vira_card_legacy_unreviewed_autoconfirm_markers(): array
{
    return [
        'تایید توسط ربات بدون بررسی',
        'Approved by the bot without review',
        'Подтверждено ботом без проверки',
        '由机器人无需审核批准',
    ];
}

function vira_set_pay_setting_value(string $name, string $value): void
{
    global $pdo;
    $existing = select('PaySetting', 'ValuePay', 'NamePay', $name, 'select');
    if (is_array($existing) && array_key_exists('ValuePay', $existing)) {
        update('PaySetting', 'ValuePay', $value, 'NamePay', $name);
        return;
    }
    if (!isset($pdo)) {
        return;
    }
    $stmt = $pdo->prepare('INSERT INTO PaySetting (NamePay, ValuePay) VALUES (?, ?)');
    $stmt->execute([$name, $value]);
}

/** خاموش کردن cron قدیمی — تأیید waiting بدون ادمین (بدون لغو پرداخت) */
function vira_disable_legacy_unreviewed_autoconfirm_settings(): void
{
    global $pdo;
    if (!isset($pdo)) {
        return;
    }
    try {
        $stmt = $pdo->prepare('UPDATE setting SET timeauto_not_verify = ? LIMIT 1');
        $stmt->execute(['99999']);
    } catch (Throwable $e) {
        error_log('vira_disable_legacy_unreviewed_autoconfirm_settings: ' . $e->getMessage());
    }
    vira_set_pay_setting_value('card_autoconfirm_mode', 'receipt_only');
}

function vira_payment_is_balance_topup_row(array $row): bool
{
    $payload = vira_card_invoice_payment_payload((string) ($row['id_invoice'] ?? ''));
    $parts = explode('|', $payload);
    $type = (string) ($parts[0] ?? '');
    return !in_array($type, ['getconfigafterpay', 'getextenduser', 'getextravolumeuser', 'getextratimeuser'], true);
}

/**
 * بازگرداندن پرداخت‌هایی که به‌اشتباه توسط purge قبلی reject شده‌اند — بدون پیام به کاربر.
 * @return array{restored:int}
 */
function vira_undo_bad_legacy_reverts(): array
{
    global $pdo;

    $stats = ['restored' => 0];
    if (!isset($pdo)) {
        return $stats;
    }

    $stmt = $pdo->prepare(
        "SELECT * FROM Payment_report
         WHERE Payment_Method = 'cart to cart'
           AND payment_Status = 'reject'
           AND dec_not_confirmed = 'reverted_unreviewed_bot'"
    );
    $stmt->execute();

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!is_array($row)) {
            continue;
        }
        $orderId = (string) ($row['id_order'] ?? '');
        $userId = (string) ($row['id_user'] ?? '');
        $price = (int) ($row['price'] ?? 0);
        if ($orderId === '' || $userId === '') {
            continue;
        }

        if (vira_payment_is_balance_topup_row($row) && $price > 0) {
            $user = select('user', '*', 'id', $userId, 'select', ['cache' => false]);
            if (is_array($user)) {
                $cashbackPct = (int) getPaySettingValue('chashbackcart', '0');
                $cashback = $cashbackPct > 0 ? (int) floor($price * $cashbackPct / 100) : 0;
                $restore = $price + $cashback;
                $newBal = (int) ($user['Balance'] ?? 0) + $restore;
                update('user', 'Balance', $newBal, 'id', $userId);
            }
        }

        update('Payment_report', 'payment_Status', 'paid', 'id_order', $orderId);
        update('Payment_report', 'dec_not_confirmed', 'restored_after_bad_revert', 'id_order', $orderId);
        clearSelectCache('Payment_report');
        $stats['restored']++;
        error_log('[card-legacy] restored order=' . $orderId . ' user=' . $userId);
    }

    return $stats;
}

/** فقط cron legacy را خاموش می‌کند — هیچ پرداخت/سرویسی لغو نمی‌شود و پیامی ارسال نمی‌شود */
function vira_ensure_legacy_unreviewed_autoconfirm_removed(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    vira_disable_legacy_unreviewed_autoconfirm_settings();

    if (getPaySettingValue('legacy_bad_revert_undone', '0') === '1') {
        return;
    }

    $stats = vira_undo_bad_legacy_reverts();
    vira_set_pay_setting_value('legacy_bad_revert_undone', '1');
    error_log('[card-legacy] bad reverts undone restored=' . ($stats['restored'] ?? 0));
}

/** callback_data انتخاب روش پرداخت در get_step_payment */
function vira_is_payment_method_datain(?string $datain): bool
{
    if ($datain === null || $datain === '') {
        return false;
    }
    static $methods = [
        'cart_to_offline', 'aqayepardakht', 'zarinpal', 'plisio', 'nowpayment',
        'iranpay1', 'iranpay2', 'iranpay3', 'digitaltron', 'startelegrams',
    ];
    return in_array($datain, $methods, true);
}

/**
 * لغو فاکتورهای کارت فعال کاربر (Unpaid + waiting) — خروج از منو یا شروع پرداخت جدید
 * @return int تعداد فاکتورهای لغو‌شده
 */
function vira_card_cancel_unpaid_invoices(string $userId, bool $notifyUser = false, string $reason = 'user_cancelled'): int
{
    global $pdo;

    if ($userId === '' || $userId === '0') {
        return 0;
    }

    $stmt = $pdo->prepare(
        "SELECT id_order, message_id FROM Payment_report
         WHERE id_user = :uid
           AND Payment_Method = 'cart to cart'
           AND payment_Status IN ('Unpaid', 'waiting')"
    );
    $stmt->bindValue(':uid', $userId, PDO::PARAM_STR);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($rows === []) {
        return 0;
    }

    $cancelled = 0;
    foreach ($rows as $row) {
        $orderId = (string) ($row['id_order'] ?? '');
        if ($orderId === '') {
            continue;
        }
        update('Payment_report', 'payment_Status', 'expire', 'id_order', $orderId);
        update('Payment_report', 'dec_not_confirmed', $reason, 'id_order', $orderId);
        $msgId = (int) ($row['message_id'] ?? 0);
        if ($msgId > 0) {
            deletemessage((int) $userId, $msgId);
        }
        $cancelled++;
    }

    if ($notifyUser && $cancelled > 0) {
        sendmessage(
            (int) $userId,
            "ℹ️ فاکتور پرداخت کارت به کارت لغو شد.",
            null,
            'HTML'
        );
    }

    return $cancelled;
}

/** حداکثر سن فاکتور کارت (Unpaid/waiting) قبل از منقضی خودکار — ساعت (پیش‌فرض ۴۸) */
function vira_card_pending_expire_hours(): int
{
    $hours = (int) getPaySettingValue('cardpendingexpirehours', '48');
    if ($hours < 6) {
        $hours = 6;
    }
    if ($hours > 168) {
        $hours = 168;
    }
    return $hours;
}

function vira_card_pending_expire_sec(): int
{
    return vira_card_pending_expire_hours() * 3600;
}

/** آیا ردیف Payment_report کارت به کارت از نظر زمان «کهنه» است؟ */
function vira_card_payment_row_is_stale(array $row): bool
{
    $ts = vira_parse_payment_report_time($row['at_updated'] ?? null)
        ?? vira_parse_payment_report_time($row['time'] ?? null);
    if ($ts === null) {
        return true;
    }
    return $ts <= time() - vira_card_pending_expire_sec();
}

/**
 * منقضی کردن فاکتورهای کهنه کارت کاربر (Unpaid + waiting) تا خرید جدید مسدود نشود.
 * @return int تعداد منقضی‌شده
 */
function vira_card_expire_stale_user_pending(string $userId): int
{
    global $pdo;

    if ($userId === '' || $userId === '0') {
        return 0;
    }

    $stmt = $pdo->prepare(
        "SELECT id_order, message_id, payment_Status, time, at_updated
         FROM Payment_report
         WHERE id_user = :uid
           AND Payment_Method = 'cart to cart'
           AND payment_Status IN ('Unpaid', 'waiting')"
    );
    $stmt->bindValue(':uid', $userId, PDO::PARAM_STR);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($rows === []) {
        return 0;
    }

    $expired = 0;
    foreach ($rows as $row) {
        if (!vira_card_payment_row_is_stale($row)) {
            continue;
        }
        $orderId = (string) ($row['id_order'] ?? '');
        if ($orderId === '') {
            continue;
        }
        update('Payment_report', 'payment_Status', 'expire', 'id_order', $orderId);
        update('Payment_report', 'dec_not_confirmed', 'stale_expired', 'id_order', $orderId);
        $msgId = (int) ($row['message_id'] ?? 0);
        if ($msgId > 0) {
            deletemessage((int) $userId, $msgId);
        }
        $expired++;
    }

    return $expired;
}

/** آیا کاربر از جریان پرداخت خارج شده (منو / برگشت / start)؟ */
function vira_card_cancel_if_user_left_payment_flow(
    string $userId,
    array $user,
    ?string $text,
    ?string $datain,
    array $update,
    array $datatextbot,
    array $textbotlang
): void {
    $step = (string) ($user['step'] ?? '');
    if (!in_array($step, vira_card_payment_flow_steps(), true)) {
        return;
    }

    // ارسال رسید — لغو نکن
    if ($step === 'cart_to_cart_user' && !empty($update['message']['photo'])) {
        return;
    }
    if (preg_match('/^sendresidcart-/', (string) $datain)) {
        return;
    }
    if ($step === 'get_step_payment' && vira_is_payment_method_datain($datain)) {
        return;
    }
    if ($step === 'card_invoice_pending' && preg_match('/^sendresidcart-/', (string) $datain)) {
        return;
    }

    $notify = false;
    $left = false;

    // /start فاکتور را لغو نمی‌کند — کاربر می‌تواند برگردد و پرداخت کند.
    if ($datain === 'backuser' || $text === ($textbotlang['users']['backbtn'] ?? '')) {
        $left = true;
        $notify = true;
    } else {
        $menuKeys = [
            'text_sell', 'text_extend', 'text_usertest', 'text_wheel_luck',
            'text_Purchased_services', 'accountwallet', 'text_affiliates',
            'text_Tariff_list', 'text_support', 'text_help',
        ];
        foreach ($menuKeys as $key) {
            $label = (string) ($datatextbot[$key] ?? '');
            if ($label === '') {
                continue;
            }
            $matches = ($text === $label);
            if (!$matches && function_exists('vira_textbot_matches')) {
                $matches = vira_textbot_matches($text, $label);
            }
            if ($matches) {
                $left = true;
                $notify = true;
                break;
            }
        }
        if (!$left) {
            $menuDatain = ['buy', 'buyback', 'buybacktow', 'account', 'Add_Balance', 'support', 'backorder'];
            if (in_array((string) $datain, $menuDatain, true)) {
                $left = true;
                $notify = true;
            }
        }
        if (!$left && $text !== '') {
            $menuCommands = ['/buy', '/wallet', '/services', 'buy'];
            if (in_array($text, $menuCommands, true)) {
                $left = true;
                $notify = true;
            }
        }
    }

    if ($left) {
        vira_card_cancel_unpaid_invoices($userId, $notify);
    }
}

function generateUUID()
{
    $data = openssl_random_pseudo_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

    $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

    return $uuid;
}
function rate_arze()
{
    $arze_rate = [];
    $requests_tron = json_decode(file_get_contents('https://api.diadata.org/v1/assetQuotation/Tron/0x0000000000000000000000000000000000000000'), true);
    $html_read = file_get_contents("https://www.bon-bast.com/");
    preg_match('/<span>\s*([\d,]+)\s*<\/span>/', $html_read, $matches);
    if (!empty($matches[1])) {
        $requestsusd = str_replace(',', '', $matches[1]);
    }
    $arze_rate['USD'] = intval($requestsusd);
    $arze_rate['TRX'] = intval($requests_tron['Price'] * $arze_rate['USD']);

    return $arze_rate;
}
function updatePaymentMessageId($response, $orderId)
{
    if (!is_array($response)) {
        error_log("Failed to send payment message for order {$orderId}: unexpected response");
        return false;
    }

    if (empty($response['ok'])) {
        error_log("Failed to send payment message for order {$orderId}: " . json_encode($response));
        return false;
    }

    if (!isset($response['result']['message_id'])) {
        error_log("Missing message_id for order {$orderId}: " . json_encode($response));
        return false;
    }

    update("Payment_report", "message_id", intval($response['result']['message_id']), "id_order", $orderId);
    return true;
}
function nowPayments($payment, $price_amount, $order_id, $order_description)
{
    global $domainhosts;
    $apinowpayments = select("PaySetting", "*", "NamePay", "marchent_tronseller", "select")['ValuePay'];
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.nowpayments.io/v1/' . $payment,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT_MS => 7000,
        CURLOPT_ENCODING => '',
        CURLOPT_SSL_VERIFYPEER => 1,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => array(
            'x-api-key:' . $apinowpayments,
            'Content-Type: application/json'
        ),
    ));
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode([
        'price_amount' => $price_amount,
        'price_currency' => 'usd',
        'order_id' => $order_id,
        'order_description' => $order_description,
        'ipn_callback_url' => "https://" . $domainhosts . "/payment/nowpayment.php"
    ]));

    $response = curl_exec($curl);
    curl_close($curl);
    return json_decode($response, true);
}
function StatusPayment($paymentid)
{
    $apinowpayments = select("PaySetting", "*", "NamePay", "marchent_tronseller", "select")['ValuePay'];
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.nowpayments.io/v1/payment/' . $paymentid,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
            'x-api-key:' . $apinowpayments
        ),
    ));
    $response = curl_exec($curl);
    $response = json_decode($response, true);
    curl_close($curl);
    return $response;
}
function channel(array $id_channel)
{
    global $from_id;
    $channel_link = array();
    foreach ($id_channel as $channel) {
        $response = telegram('getChatMember', [
            'chat_id' => $channel,
            'user_id' => $from_id
        ]);
        if ($response['ok']) {
            if (!in_array($response['result']['status'], ['member', 'creator', 'administrator'])) {
                $channel_link[] = $channel;
            }
        }
    }
    if (count($channel_link) == 0) {
        return [];
    } else {
        return $channel_link;
    }
}
function isValidDate($date)
{
    return (strtotime($date) != false);
}
function trnado($order_id, $price)
{
    global $domainhosts;
    $apitronseller = select("PaySetting", "*", "NamePay", "apiternado", "select")['ValuePay'];
    $walletaddress = select("PaySetting", "*", "NamePay", "walletaddress", "select")['ValuePay'];
    $urlpay = select("PaySetting", "*", "NamePay", "urlpaymenttron", "select")['ValuePay'];
    $curl = curl_init();
    $data = array(
        "PaymentID" => $order_id,
        "WalletAddress" => $walletaddress,
        "TronAmount" => $price,
        "CallbackUrl" => "https://" . $domainhosts . "/payment/tronado.php"
    );
    $datasend = json_encode($data);
    curl_setopt_array($curl, array(
        CURLOPT_URL => "$urlpay",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_HTTPHEADER => array(
            'x-api-key:' . $apitronseller,
            'Content-Type: application/json',
            'Cookie: ASP.NET_SessionId=spou2s5lo4nnxkjtavscrrlo'
        ),
    ));
    curl_setopt($curl, CURLOPT_POSTFIELDS, $datasend);

    $response = curl_exec($curl);

    curl_close($curl);
    return json_decode($response, true);
}
function formatBytes($bytes, $precision = 2): string
{
    $base = log($bytes, 1024);
    $power = $bytes > 0 ? floor($base) : 0;
    $suffixes = ['بایت', 'کیلوبایت', 'مگابایت', 'گیگابایت', 'ترابایت'];
    return round(pow(1024, $base - $power), $precision) . ' ' . $suffixes[$power];
}
function generateUsername($from_id, $Metode, $username, $randomString, $text, $namecustome, $usernamecustom)
{
    $setting = select("setting", "*", null, null, "select");
    $user = select("user", "*", "id", $from_id, "select");
    if ($user == false) {
        $user = array();
        $user = array(
            'number_username' => '',
        );
    }
    if ($Metode == "آیدی عددی + حروف و عدد رندوم") {
        return $from_id . "_" . $randomString;
    } elseif ($Metode == "نام کاربری + عدد به ترتیب") {
        if ($username == "NOT_USERNAME") {
            if (preg_match('/^\w{3,32}$/', $namecustome)) {
                $username = $namecustome;
            }
        }
        return $username . "_" . $user['number_username'];
    } elseif ($Metode == "نام کاربری دلخواه")
        return $text;
    elseif ($Metode == "نام کاربری دلخواه + عدد رندوم") {
        $random_number = rand(1000000, 9999999);
        return $text . "_" . $random_number;
    } elseif ($Metode == "متن دلخواه + عدد رندوم") {
        return $namecustome . "_" . $randomString;
    } elseif ($Metode == "متن دلخواه + عدد ترتیبی") {
        return $namecustome . "_" . $setting['numbercount'];
    } elseif ($Metode == "آیدی عددی+عدد ترتیبی") {
        return $from_id . "_" . $user['number_username'];
    } elseif ($Metode == "متن دلخواه نماینده + عدد ترتیبی") {
        if ($usernamecustom == "none") {
            return $namecustome . "_" . $setting['numbercount'];
        }
        return $usernamecustom . "_" . $user['number_username'];
    }
}
function outputlink($text)
{
    global $request_exec_timeout;
    $configuredMs = ($request_exec_timeout !== null && (int) $request_exec_timeout > 0)
        ? (int) $request_exec_timeout
        : 0;
    $timeoutMs = $configuredMs > 0 ? min($configuredMs, 25000) : 20000;
    $connectMs = min(8000, (int) max(4000, $timeoutMs / 3));
    $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';
    for ($attempt = 1; $attempt <= 2; $attempt++) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $text);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, $timeoutMs);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, $connectMs);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);
        if ($response !== false && $httpCode >= 200 && $httpCode < 300) {
            return $response;
        }
        $retry = ($httpCode === 504 || $httpCode === 502 || $httpCode === 503 || $curlErr !== '');
        if (!$retry || $attempt >= 2) {
            return null;
        }
        usleep(1000000 * $attempt);
    }
    return null;
}

/** ManagePanel برای DirectPayment از webhook گروه SMS / payment/card.php */
function vira_ensure_manage_panel(): ManagePanel
{
    global $ManagePanel;
    if (!isset($ManagePanel) || !($ManagePanel instanceof ManagePanel)) {
        if (!class_exists('ManagePanel', false)) {
            require_once __DIR__ . '/panels.php';
        }
        $ManagePanel = new ManagePanel();
    }
    return $ManagePanel;
}

/** ویرایش پیام فاکتور کاربر (private) + پیام ادمین/webhook در صورت متفاوت بودن */
function vira_edit_payment_status_messages(array $Payment_report, string $adminText, $adminKeyboard = null): void
{
    global $from_id, $message_id, $update;

    $userChatId = (int) ($Payment_report['id_user'] ?? 0);
    $userMsgId = (int) ($Payment_report['message_id'] ?? 0);
    $orderId = (string) ($Payment_report['id_order'] ?? '');

    if ($userMsgId > 0 && $userChatId > 0) {
        $userText = "✅ پرداخت شما تأیید شد.\n🛒 کد پیگیری: {$orderId}";
        Editmessagetext($userChatId, $userMsgId, $userText, json_encode(['inline_keyboard' => []]));
    }

    $ctxChatId = (int) $from_id;
    if (is_array($update ?? null)) {
        if (!empty($update['message']['chat']['id'])) {
            $ctxChatId = (int) $update['message']['chat']['id'];
        } elseif (!empty($update['callback_query']['message']['chat']['id'])) {
            $ctxChatId = (int) $update['callback_query']['message']['chat']['id'];
        } elseif (!empty($update['channel_post']['chat']['id'])) {
            $ctxChatId = (int) $update['channel_post']['chat']['id'];
        }
    }
    $ctxMsgId = (int) $message_id;
    if ($ctxMsgId <= 0) {
        return;
    }
    if ($ctxChatId === $userChatId && $ctxMsgId === $userMsgId) {
        return;
    }
    // پیام گروه/کانال SMS را به متن ادمین تبدیل نکن
    if ($ctxChatId < 0) {
        return;
    }
    Editmessagetext($ctxChatId, $ctxMsgId, $adminText, $adminKeyboard);
}

/**
 * اتمیک: فقط اولین بار وضعیت را paid می‌کند — جلوگیری از پردازش دوباره DirectPayment
 */
function vira_payment_try_claim(string $orderId): bool
{
    global $pdo;
    if ($orderId === '') {
        return false;
    }
    $stmt = $pdo->prepare(
        "UPDATE Payment_report SET payment_Status = 'paid'
         WHERE id_order = :oid AND payment_Status NOT IN ('paid', 'reject', 'expire')"
    );
    $stmt->execute([':oid' => $orderId]);
    if ($stmt->rowCount() > 0) {
        clearSelectCache('Payment_report');
        return true;
    }
    return false;
}

/** برگرداندن claim ناموفق DirectPayment به Unpaid */
function vira_payment_revert_claim(string $orderId): void
{
    global $pdo;
    if ($orderId === '') {
        return;
    }
    $stmt = $pdo->prepare(
        "UPDATE Payment_report SET payment_Status = 'Unpaid'
         WHERE id_order = :oid AND payment_Status = 'paid'"
    );
    $stmt->execute([':oid' => $orderId]);
    clearSelectCache('Payment_report');
}

function DirectPayment($order_id, $image = 'images.jpg', bool $alreadyClaimed = false): bool
{
    global $pdo, $ManagePanel, $textbotlang, $keyboardextendfnished, $keyboard, $Confirm_pay, $from_id, $message_id, $datatextbot;
    vira_ensure_manage_panel();
    $Payment_report = select('Payment_report', '*', 'id_order', $order_id, 'select', ['cache' => false]);
    if (!is_array($Payment_report) || empty($Payment_report['id_order'])) {
        error_log('[DirectPayment] order_missing id=' . $order_id);
        return false;
    }
    if ($alreadyClaimed) {
        if (($Payment_report['payment_Status'] ?? '') !== 'paid') {
            error_log('[DirectPayment] alreadyClaimed stale status=' . ($Payment_report['payment_Status'] ?? 'null') . ' order=' . $order_id);
            return false;
        }
    } elseif (!vira_payment_try_claim($order_id)) {
        return false;
    }
    $buyreport = select("topicid", "idreport", "report", "buyreport", "select")['idreport'];
    $admin_ids = select("admin", "id_admin", null, null, "FETCH_COLUMN");
    $otherservice = select("topicid", "idreport", "report", "otherservice", "select")['idreport'];
    $otherreport = select("topicid", "idreport", "report", "otherreport", "select")['idreport'];
    $errorreport = select("topicid", "idreport", "report", "errorreport", "select")['idreport'];
    $porsantreport = select("topicid", "idreport", "report", "porsantreport", "select")['idreport'];
    $setting = vira_ensure_setting_ready();
    $format_price_cart = number_format($Payment_report['price']);
    $Balance_id = select("user", "*", "id", $Payment_report['id_user'], "select");
    $steppay = explode("|", vira_card_invoice_payment_payload((string) $Payment_report['id_invoice']));
    update("user", "Processing_value", "0", "id", $Balance_id['id']);
    update("user", "Processing_value_one", "0", "id", $Balance_id['id']);
    update("user", "Processing_value_tow", "0", "id", $Balance_id['id']);
    update("user", "Processing_value_four", "0", "id", $Balance_id['id']);
    if ($steppay[0] == "getconfigafterpay") {
        $get_invoice = select("invoice", "*", "username", $steppay[1], "select");
        $stmt = $pdo->prepare("SELECT * FROM product WHERE name_product = :name_product AND (Location = :Service_location  or Location = '/all')");
        $stmt->bindParam(':name_product', $get_invoice['name_product'], PDO::PARAM_STR);
        $stmt->bindParam(':Service_location', $get_invoice['Service_location'], PDO::PARAM_STR);
        $stmt->execute();
        $info_product = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($get_invoice['name_product'] == "🛍 حجم دلخواه" || $get_invoice['name_product'] == "⚙️ سرویس دلخواه") {
            $info_product['data_limit_reset'] = "no_reset";
            $info_product['Volume_constraint'] = $get_invoice['Volume'];
            $info_product['name_product'] = $textbotlang['users']['customsellvolume']['title'];
            $info_product['code_product'] = "customvolume";
            $info_product['Service_time'] = $get_invoice['Service_time'];
            $info_product['price_product'] = $get_invoice['price_product'];
        } else {
            $stmt = $pdo->prepare("SELECT * FROM product WHERE name_product = :name_product AND (Location = :Service_location  or Location = '/all')");
            $stmt->bindParam(':name_product', $get_invoice['name_product'], PDO::PARAM_STR);
            $stmt->bindParam(':Service_location', $get_invoice['Service_location'], PDO::PARAM_STR);
            $stmt->execute();
            $info_product = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        $username_ac = $get_invoice['username'];
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $get_invoice['Service_location'], "select");
        $date = strtotime("+" . $get_invoice['Service_time'] . "days");
        if (intval($get_invoice['Service_time']) == 0) {
            $timestamp = 0;
        } else {
            $timestamp = strtotime(date("Y-m-d H:i:s", $date));
        }
        $datac = array(
            'expire' => $timestamp,
            'data_limit' => $get_invoice['Volume'] * pow(1024, 3),
            'from_id' => $Balance_id['id'],
            'username' => vira_user_tg_username($Balance_id),
            'type' => 'buy',
            'id_invoice' => $get_invoice['id_invoice'],
        );
        $dataoutput = $ManagePanel->createUser($marzban_list_get['name_panel'], $info_product['code_product'], $username_ac, $datac);
        if (vira_create_user_missing_username($dataoutput)) {
            $dataoutput['msg'] = json_encode($dataoutput['msg']);
            $balance = $Balance_id['Balance'] + $Payment_report['price'];
            update("user", "Balance", $balance, "id", $Balance_id['id']);
            sendmessage($Balance_id['id'], $textbotlang['users']['sell']['ErrorConfig'], $keyboard, 'HTML');
            sendmessage($Balance_id['id'], "💎  کاربر عزیز بدلیل ساخته نشدن سرویس مبلغ $balance تومان به کیف پول شما اضافه گردید.", $keyboard, 'HTML');
            $texterros = "
⭕️ خطا در ساخت کانفیگ
✍️ دلیل خطا : 
{$dataoutput['msg']}
آیدی کابر : {$Balance_id['id']}
نام کاربری کاربر : @" . vira_user_tg_username($Balance_id) . "
نام پنل : {$marzban_list_get['name_panel']}";
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $texterros,
                    'parse_mode' => "HTML"
                ]);
            }
            return true;
        }
        $Shoppinginfo = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "📚 مشاهده آموزش استفاده ", 'callback_data' => "helpbtn"],
                ]
            ]
        ]);
        $output_config_link = "";
        $config = "";
        if ($marzban_list_get['config'] == "onconfig" && is_array($dataoutput['configs'])) {
            foreach ($dataoutput['configs'] as $link) {
                $config .= "\n" . $link;
            }
        }
        $output_config_link = $marzban_list_get['sublink'] == "onsublink" ? $dataoutput['subscription_url'] : "";
        $datatextbot['textafterpay'] = $marzban_list_get['type'] == "Manualsale" ? $datatextbot['textmanual'] : $datatextbot['textafterpay'];
        $datatextbot['textafterpay'] = $marzban_list_get['type'] == "WGDashboard" ? $datatextbot['text_wgdashboard'] : $datatextbot['textafterpay'];
        $datatextbot['textafterpay'] = $marzban_list_get['type'] == "ibsng" || $marzban_list_get['type'] == "mikrotik" ? $datatextbot['textafterpayibsng'] : $datatextbot['textafterpay'];
        if (intval($get_invoice['Service_time']) == 0)
            $get_invoice['Service_time'] = $textbotlang['users']['stateus']['Unlimited'];
        $textcreatuser = str_replace('{username}', $dataoutput['username'], $datatextbot['textafterpay']);
        $textcreatuser = str_replace('{name_service}', $get_invoice['name_product'], $textcreatuser);
        $textcreatuser = str_replace('{location}', $marzban_list_get['name_panel'], $textcreatuser);
        $textcreatuser = str_replace('{day}', $get_invoice['Service_time'], $textcreatuser);
        $textcreatuser = str_replace('{volume}', $get_invoice['Volume'], $textcreatuser);
        $textcreatuser = str_replace('{config}', "<code>{$output_config_link}</code>", $textcreatuser);
        $textcreatuser = str_replace('{links}', $config, $textcreatuser);
        $textcreatuser = str_replace('{links2}', "{$output_config_link}", $textcreatuser);
        if ($marzban_list_get['type'] == "Manualsale" || $marzban_list_get['type'] == "ibsng" || $marzban_list_get['type'] == "mikrotik") {
            $textcreatuser = str_replace('{password}', $dataoutput['subscription_url'], $textcreatuser);
            update("invoice", "user_info", $dataoutput['subscription_url'], "id_invoice", $get_invoice['id_invoice']);
        } elseif (!empty($dataoutput['subscription_url'])) {
            if (!function_exists('vira_invoice_after_purchase_success') && is_file(__DIR__ . '/inc/panel_service_repair.php')) {
                require_once __DIR__ . '/inc/panel_service_repair.php';
            }
            if (function_exists('vira_invoice_after_purchase_success')) {
                vira_invoice_after_purchase_success(
                    (string) $get_invoice['id_invoice'],
                    $dataoutput,
                    (int) $timestamp,
                    (int) ($get_invoice['Volume'] * pow(1024, 3)),
                    (string) ($info_product['code_product'] ?? '')
                );
            } elseif (function_exists('vira_invoice_persist_subscription_data')) {
                vira_invoice_persist_subscription_data((string) $get_invoice['id_invoice'], (string) $dataoutput['subscription_url'], $dataoutput);
            }
        }
        sendMessageService($marzban_list_get, $dataoutput['configs'], $output_config_link, $dataoutput['username'], $Shoppinginfo, $textcreatuser, $get_invoice['id_invoice'], $get_invoice['id_user'], $image);
        $partsdic = explode("_", $Balance_id['Processing_value_four'], $get_invoice['id_user']);
        if ($partsdic[0] == "dis") {
            $SellDiscountlimit = select("DiscountSell", "*", "codeDiscount", $partsdic[1], "select");
            $value = intval($SellDiscountlimit['usedDiscount']) + 1;
            update("DiscountSell", "usedDiscount", $value, "codeDiscount", $partsdic[1]);
            $stmt = $pdo->prepare("INSERT INTO Giftcodeconsumed (id_user,code) VALUES (:id_user,:code)");
            $stmt->bindParam(':id_user', $Balance_id['id']);
            $stmt->bindParam(':code', $partsdic[1]);
            $stmt->execute();
            $text_report = "⭕️ یک کاربر با نام کاربری " . '@' . vira_user_tg_username($Balance_id) . "  و آیدی عددی {$Balance_id['id']} از کد تخفیف {$partsdic[1]} استفاده کرد.";
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $otherreport,
                    'text' => $text_report,
                ]);
            }
        }
        $affiliatescommission = select("affiliates", "*", null, null, "select");
        $marzbanporsant_one_buy = select("affiliates", "*", null, null, "select");
        $stmt = $pdo->prepare("SELECT * FROM invoice WHERE name_product != 'سرویس تست'  AND id_user = :id_user AND Status != 'Unpaid'");
        $stmt->bindParam(':id_user', $Balance_id['id']);
        $stmt->execute();
        $countinvoice = $stmt->rowCount();
        if ($affiliatescommission['status_commission'] == "oncommission" && ($Balance_id['affiliates'] != null && intval($Balance_id['affiliates']) != 0)) {
            if ($marzbanporsant_one_buy['porsant_one_buy'] == "on_buy_porsant") {
                if ($countinvoice <= 1) {
                    $result = ($Payment_report['price'] * $setting['affiliatespercentage']) / 100;
                    $user_Balance = select("user", "*", "id", $Balance_id['affiliates'], "select");
                    if (is_array($user_Balance)) {
                    if (intval($setting['scorestatus']) == 1 and !in_array($Balance_id['affiliates'], $admin_ids)) {
                        sendmessage($Balance_id['affiliates'], "📌شما 2 امتیاز جدید کسب کردید.", null, 'html');
                        $scorenew = $user_Balance['score'] + 2;
                        update("user", "score", $scorenew, "id", $Balance_id['affiliates']);
                    }
                    $Balance_prim = $user_Balance['Balance'] + $result;
                    $dateacc = date('Y/m/d H:i:s');
                    update("user", "Balance", $Balance_prim, "id", $Balance_id['affiliates']);
                    $result = number_format($result);
                    $textadd = "🎁  پرداخت پورسانت 
        
        مبلغ $result تومان به حساب شما از طرف  زیر مجموعه تان به کیف پول شما واریز گردید";
                    $textreportport = "
مبلغ $result به کاربر {$Balance_id['affiliates']} برای پورسانت از کاربر {$Balance_id['id']} واریز گردید 
تایم : $dateacc";
                    if (strlen($setting['Channel_Report'] ?? '') > 0) {
                        telegram('sendmessage', [
                            'chat_id' => $setting['Channel_Report'],
                            'message_thread_id' => $porsantreport,
                            'text' => $textreportport,
                            'parse_mode' => "HTML"
                        ]);
                    }
                    sendmessage($Balance_id['affiliates'], $textadd, null, 'HTML');
                    }
                }
            } else {

                $result = ($Payment_report['price'] * $setting['affiliatespercentage']) / 100;
                $user_Balance = select("user", "*", "id", $Balance_id['affiliates'], "select");
                if (is_array($user_Balance)) {
                if (intval($setting['scorestatus']) == 1 and !in_array($Balance_id['affiliates'], $admin_ids)) {
                    sendmessage($Balance_id['affiliates'], "📌شما 2 امتیاز جدید کسب کردید.", null, 'html');
                    $scorenew = $user_Balance['score'] + 2;
                    update("user", "score", $scorenew, "id", $Balance_id['affiliates']);
                }
                $Balance_prim = $user_Balance['Balance'] + $result;
                $dateacc = date('Y/m/d H:i:s');
                update("user", "Balance", $Balance_prim, "id", $Balance_id['affiliates']);
                $result = number_format($result);
                $textadd = "🎁  پرداخت پورسانت 
        
        مبلغ $result تومان به حساب شما از طرف  زیر مجموعه تان به کیف پول شما واریز گردید";
                $textreportport = "
مبلغ $result به کاربر {$Balance_id['affiliates']} برای پورسانت از کاربر {$Balance_id['id']} واریز گردید 
تایم : $dateacc";
                if (strlen($setting['Channel_Report'] ?? '') > 0) {
                    telegram('sendmessage', [
                        'chat_id' => $setting['Channel_Report'],
                        'message_thread_id' => $porsantreport,
                        'text' => $textreportport,
                        'parse_mode' => "HTML"
                    ]);
                }
                sendmessage($Balance_id['affiliates'], $textadd, null, 'HTML');
                }
            }
        }
        if ($marzban_list_get['MethodUsername'] == "متن دلخواه + عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "نام کاربری + عدد به ترتیب" || $marzban_list_get['MethodUsername'] == "آیدی عددی+عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "متن دلخواه نماینده + عدد ترتیبی") {
            $value = intval($Balance_id['number_username']) + 1;
            update("user", "number_username", $value, "id", $Balance_id['id']);
            if ($marzban_list_get['MethodUsername'] == "متن دلخواه + عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "متن دلخواه نماینده + عدد ترتیبی") {
                $value = intval($setting['numbercount']) + 1;
                update("setting", "numbercount", $value);
            }
        }
        $Balance_prims = $Balance_id['Balance'] - $get_invoice['price_product'];
        if ($Balance_prims <= 0)
            $Balance_prims = 0;
        update("user", "Balance", $Balance_prims, "id", $Balance_id['id']);
        $balanceformatsell = select("user", "Balance", "id", $get_invoice['id_user'], "select")['Balance'];
        $balanceformatsell = number_format($balanceformatsell, 0);
        $balancebefore = number_format($Balance_id['Balance'], 0);
        $timejalali = jdate('Y/m/d H:i:s');
        $textonebuy = "";
        if ($countinvoice == 1) {
            $textonebuy = "📌 خرید اول کاربر";
        }
        $Response = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'], 'callback_data' => 'manageuser_' . $Balance_id['id']],
                ],
            ]
        ]);
        $text_report = "📣 جزئیات ساخت اکانت در ربات بعد پرداخت ثبت شد .

$textonebuy
▫️آیدی عددی کاربر : <code>{$Balance_id['id']}</code>
▫️نام کاربری کاربر :" . '@' . vira_user_tg_username($Balance_id) . "
▫️نام کاربری کانفیگ :$username_ac
▫️لوکیشن سرویس : {$get_invoice['Service_location']}
▫️زمان خریداری شده :{$get_invoice['Service_time']} روز
▫️نام محصول خریداری شده :{$get_invoice['name_product']}
▫️حجم خریداری شده : {$get_invoice['Volume']} GB
▫️موجودی قبل خرید : $balancebefore تومان
▫️موجودی بعد خرید : $balanceformatsell تومان
▫️کد پیگیری: {$get_invoice['id_invoice']}
▫️نوع کاربر : {$Balance_id['agent']}
▫️شماره تلفن کاربر : {$Balance_id['number']}
▫️قیمت محصول : {$get_invoice['price_product']} تومان
▫️قیمت نهایی : {$Payment_report['price']} تومان
▫️زمان خرید : $timejalali";
        if (strlen($setting['Channel_Report']) > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $buyreport,
                'text' => $text_report,
                'parse_mode' => "HTML",
                'reply_markup' => $Response
            ]);
        }
        if (intval($setting['scorestatus']) == 1 and !in_array($Balance_id['id'], $admin_ids)) {
            sendmessage($Balance_id['id'], "📌شما 1 امتیاز جدید کسب کردید.", null, 'html');
            $scorenew = $Balance_id['score'] + 1;
            update("user", "score", $scorenew, "id", $Balance_id['id']);
        }
        update("invoice", "Status", "active", "id_invoice", $get_invoice['id_invoice']);
        if ($Payment_report['Payment_Method'] == "cart to cart" or $Payment_report['Payment_Method'] == "arze digital offline") {
            $textconfrom = "✅ پرداخت تایید شده
 🛍خرید سرویس 
 ▫️نام کاربری کانفیگ :$username_ac
▫️لوکیشن سرویس : {$get_invoice['Service_location']}
👤 شناسه کاربر: <code>{$Balance_id['id']}</code>
🛒 کد پیگیری پرداخت: {$Payment_report['id_order']}
⚜️ نام کاربری: " . '@' . vira_user_tg_username($Balance_id) . "
💎 موجودی قبل خرید  : {$Balance_id['Balance']}
💸 مبلغ پرداختی: $format_price_cart تومان
✍️ توضیحات : {$Payment_report['dec_not_confirmed']}

";
            vira_edit_payment_status_messages($Payment_report, $textconfrom, $Confirm_pay);
        }
    } elseif ($steppay[0] == "getextenduser") {
        $balanceformatsell = number_format(select("user", "Balance", "id", $Balance_id['id'], "select")['Balance'], 0);
        $partsdic = explode("%", $steppay[1]);
        $usernamepanel = $partsdic[0];
        $sql = "SELECT * FROM service_other WHERE username = :username  AND value  LIKE CONCAT('%', :value, '%') AND id_user = :id_user ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':username', $usernamepanel, PDO::PARAM_STR);
        $stmt->bindParam(':value', $partsdic[1], PDO::PARAM_STR);
        $stmt->bindParam(':id_user', $Balance_id['id']);
        $stmt->execute();
        $data_order = $stmt->fetch(PDO::FETCH_ASSOC);
        $service_other = $data_order;
        if ($service_other == false) {
            sendmessage($Balance_id['id'], '❌ خطایی در هنگام تمدید رخ داده با پشتیبانی در ارتباط باشید', $keyboard, 'HTML');
            return true;
        }
        $service_other = json_decode($service_other['value'], true);
        $codeproduct = $service_other['code_product'];
        $nameloc = select("invoice", "*", "username", $usernamepanel, "select");
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
        if ($codeproduct == "custom_volume") {
            $prodcut['code_product'] = "custom_volume";
            $prodcut['name_product'] = $nameloc['name_product'];
            $prodcut['price_product'] = $data_order['price'];
            $prodcut['Service_time'] = $service_other['Service_time'];
            $prodcut['Volume_constraint'] = $service_other['volumebuy'];
        } else {
            $stmt = $pdo->prepare("SELECT * FROM product WHERE (Location = '{$nameloc['Service_location']}' OR Location = '/all') AND agent= '{$Balance_id['agent']}' AND code_product = '$codeproduct'");
            $stmt->execute();
            $prodcut = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        if ($nameloc['name_product'] == "سرویس تست") {
            update("invoice", "name_product", $prodcut['name_product'], "id_invoice", $nameloc['id_invoice']);
            update("invoice", "price_product", $prodcut['price_product'], "id_invoice", $nameloc['id_invoice']);
        }
        $dateacc = date('Y/m/d H:i:s');
        $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
        $Balance_Low_user = 0;
        update("user", "Balance", $Balance_Low_user, "id", $Balance_id['id']);
        $extend = $ManagePanel->extend($marzban_list_get['Methodextend'], $prodcut['Volume_constraint'], $prodcut['Service_time'], $nameloc['username'], $prodcut['code_product'], $marzban_list_get['code_panel']);
        if ($extend['status'] == false) {
            $balance = $Balance_id['Balance'] + $Payment_report['price'];
            update("user", "Balance", $balance, "id", $Balance_id['id']);
            sendmessage($Balance_id['id'], $textbotlang['users']['sell']['ErrorConfig'], $keyboard, 'HTML');
            sendmessage($Balance_id['id'], "💎  کاربر عزیز بدلیل تمدید نشدن سرویس مبلغ $balance تومان به کیف پول شما اضافه گردید.", $keyboard, 'HTML');
            $extend['msg'] = json_encode($extend['msg']);
            $textreports = "
        خطای تمدید سرویس
نام پنل : {$marzban_list_get['name_panel']}
نام کاربری سرویس : {$nameloc['username']}
دلیل خطا : {$extend['msg']}";
            sendmessage($nameloc['id_user'], "❌خطایی در تمدید سرویس رخ داده با پشتیبانی در ارتباط باشید", null, 'HTML');
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $textreports,
                    'parse_mode' => "HTML"
                ]);
            }
            return true;
        }

        update("service_other", "output", json_encode($extend), "id", $data_order['id']);
        update("service_other", "status", "paid", "id", $data_order['id']);
        $partsdic = explode("_", $Balance_id['Processing_value_four']);
        if ($partsdic[0] == "dis") {
            $SellDiscountlimit = select("DiscountSell", "*", "codeDiscount", $partsdic[1], "select");
            $value = intval($SellDiscountlimit['usedDiscount']) + 1;
            update("DiscountSell", "usedDiscount", $value, "codeDiscount", $partsdic[1]);
            $stmt = $pdo->prepare("INSERT INTO Giftcodeconsumed (id_user,code) VALUES (:id_user,:code)");
            $stmt->bindParam(':id_user', $Balance_id['id']);
            $stmt->bindParam(':code', $partsdic[1]);
            $stmt->execute();
            $text_report = "⭕️ یک کاربر با نام کاربری " . '@' . vira_user_tg_username($Balance_id) . "  و آیدی عددی {$Balance_id['id']} از کد تخفیف {$partsdic[1]} استفاده کرد.";
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $otherreport,
                    'text' => $text_report,
                ]);
            }
        }
        $keyboardextendfnished = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['stateus']['backlist'], 'callback_data' => "backorder"],
                ],
                [
                    ['text' => $textbotlang['users']['stateus']['backservice'], 'callback_data' => "product_" . $nameloc['id_invoice']],
                ]
            ]
        ]);
        if ($Balance_id['agent'] == "f") {
            $valurcashbackextend = select("shopSetting", "*", "Namevalue", "chashbackextend", "select")['value'];
        } else {
            $valurcashbackextend = json_decode(select("shopSetting", "*", "Namevalue", "chashbackextend_agent", "select")['value'], true)[$Balance_id['agenr']];
        }
        if (intval($valurcashbackextend) != 0) {
            $result = ($prodcut['price_product'] * $valurcashbackextend) / 100;
            $pricelastextend = $result;
            update("user", "Balance", $pricelastextend, "id", $Balance_id['id']);
            sendmessage($Balance_id['id'], "تبریک 🎉
📌 به عنوان هدیه تمدید مبلغ $result تومان حساب شما شارژ گردید", null, 'HTML');
        }
        $priceproductformat = number_format($prodcut['price_product']);
        $textextend = "✅ تمدید برای سرویس شما با موفقیت صورت گرفت
 
▫️نام سرویس : $usernamepanel
▫️نام محصول : {$prodcut['name_product']}
▫️مبلغ تمدید $priceproductformat تومان
";
        sendmessage($Balance_id['id'], $textextend, $keyboardextendfnished, 'HTML');
        if (intval($setting['scorestatus']) == 1 and !in_array($Balance_id['id'], $admin_ids)) {
            sendmessage($Balance_id['id'], "📌شما 2 امتیاز جدید کسب کردید.", null, 'html');
            $scorenew = $Balance_id['score'] + 2;
            update("user", "score", $scorenew, "id", $Balance_id['id']);
        }
        $timejalali = jdate('Y/m/d H:i:s');
        $text_report = "📣 جزئیات تمدید اکانت در ربات شما ثبت شد .
    
▫️آیدی عددی کاربر : <code>{$Balance_id['id']}</code>
▫️نام کاربری کاربر : @" . vira_user_tg_username($Balance_id) . "
▫️نام کاربری کانفیگ :$usernamepanel
▫️موقعیت سرویس سرویس : {$nameloc['Service_location']}
▫️نام محصول : {$prodcut['name_product']}
▫️حجم محصول : {$prodcut['Volume_constraint']}
▫️زمان محصول : {$prodcut['Service_time']}
▫️مبلغ تمدید : $priceproductformat تومان
▫️موجودی قبل از خرید : $balanceformatsell تومان
▫️زمان خرید : $timejalali";
        if (strlen($setting['Channel_Report']) > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $otherservice,
                'text' => $text_report,
                'parse_mode' => "HTML"
            ]);
        }
        update("invoice", "Status", "active", "id_invoice", $nameloc['id_invoice']);
        if ($Payment_report['Payment_Method'] == "cart to cart" or $Payment_report['Payment_Method'] == "arze digital offline") {

            $textconfrom = "✅ پرداخت تایید شده
🔋 تمدید سرویس
🪪 نام کاربری کانفیگ : $usernamepanel
🛍 نام محصول : {$prodcut['name_product']}
🌏 نام لوکیشن : {$nameloc['Service_location']}
👤 شناسه کاربر: <code>{$Balance_id['id']}</code>
🛒 کد پیگیری پرداخت: {$Payment_report['id_order']}
⚜️ نام کاربری: " . '@' . vira_user_tg_username($Balance_id) . "
💎 موجودی قبل تمدید  : {$Balance_id['Balance']}
💸 مبلغ پرداختی: $format_price_cart تومان
✍️ توضیحات : {$Payment_report['dec_not_confirmed']}

";
            vira_edit_payment_status_messages($Payment_report, $textconfrom, $Confirm_pay);
        }
    } elseif ($steppay[0] == "getextravolumeuser") {
        $steppay = explode("%", $steppay[1]);
        $volume = $steppay[1];
        $nameloc = select("invoice", "*", "username", $steppay[0], "select");
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
        $Balance_Low_user = 0;
        $inboundid = $marzban_list_get['inboundid'];
        if ($nameloc['inboundid'] != null) {
            $inboundid = $nameloc['inboundid'];
        }
        update("user", "Balance", $Balance_Low_user, "id", $Balance_id['id']);
        $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $steppay[0]);
        $data_for_database = json_encode(array(
            'volume_value' => $volume,
            'old_volume' => $DataUserOut['data_limit'],
            'expire_old' => $DataUserOut['expire']
        ));
        $dateacc = date('Y/m/d H:i:s');
        $type = "extra_user";
        $extra_volume = $ManagePanel->extra_volume($nameloc['username'], $marzban_list_get['code_panel'], $volume);
        if ($extra_volume['status'] == false) {
            $extra_volume['msg'] = json_encode($extra_volume['msg']);
            $textreports = "خطای خرید حجم اضافه
نام پنل : {$marzban_list_get['name_panel']}
نام کاربری سرویس : {$nameloc['username']}
دلیل خطا : {$extra_volume['msg']}";
            sendmessage($nameloc['id_user'], "❌خطایی در خرید حجم اضافه سرویس رخ داده با پشتیبانی در ارتباط باشید", null, 'HTML');
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $textreports,
                    'parse_mode' => "HTML"
                ]);
            }
            return true;
        }
        $stmt = $pdo->prepare("INSERT IGNORE INTO service_other (id_user, username,value,type,time,price,output) VALUES (:id_user,:username,:value,:type,:time,:price,:output)");
        $stmt->bindParam(':id_user', $Balance_id['id']);
        $stmt->bindParam(':username', $steppay[0]);
        $stmt->bindParam(':value', $data_for_database);
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':time', $dateacc);
        $stmt->bindParam(':price', $Payment_report['price']);
        $stmt->bindParam(':output', json_encode($extra_volume));
        $stmt->execute();
        $keyboardextrafnished = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['stateus']['backservice'], 'callback_data' => "product_" . $nameloc['id_invoice']],
                ]
            ]
        ]);
        $volumesformat = number_format($Payment_report['price'], 0);
        if (intval($setting['scorestatus']) == 1 and !in_array($Balance_id['id'], $admin_ids)) {
            sendmessage($Balance_id['id'], "📌شما 1 امتیاز جدید کسب کردید.", null, 'html');
            $scorenew = $Balance_id['score'] + 1;
            update("user", "score", $scorenew, "id", $Balance_id['id']);
        }
        $textvolume = "✅ افزایش حجم برای سرویس شما با موفقیت صورت گرفت
 
▫️نام سرویس  : {$steppay[0]}
▫️حجم اضافه : $volume گیگ

▫️مبلغ افزایش حجم : $volumesformat تومان";
        sendmessage($Balance_id['id'], $textvolume, $keyboardextrafnished, 'HTML');
        $volumes = $volume;
        if ($Payment_report['Payment_Method'] == "cart to cart") {
            $textconfrom = "✅ پرداخت تایید شده
🔋 خرید حجم اضافه
🛍 حجم خریداری شده  : $volumes گیگ
👤 نام کاربری کانفیگ {$steppay[0]}
👤 شناسه کاربر: <code>{$Balance_id['id']}</code>
🛒 کد پیگیری پرداخت: {$Payment_report['id_order']}
⚜️ نام کاربری: " . '@' . vira_user_tg_username($Balance_id) . "
💎 موجودی قبل ازافزایش موجودی : {$Balance_id['Balance']}
💸 مبلغ پرداختی: $format_price_cart تومان
";
            vira_edit_payment_status_messages($Payment_report, $textconfrom, $Confirm_pay);
        }
        update("invoice", "Status", "active", "id_invoice", $nameloc['id_invoice']);
        $text_report = "⭕️ یک کاربر حجم اضافه خریده است
        
اطلاعات کاربر : 
🪪 آیدی عددی : {$Balance_id['id']}
🛍 حجم خریداری شده  : $volumes گیگ
💰 مبلغ پرداختی : {$Payment_report['price']} تومان
👤 نام کاربری کانفیگ {$steppay[0]}
موجودی کاربر قبل خرید : {$Balance_id['Balance']}
";
        if (strlen($setting['Channel_Report']) > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $otherservice,
                'text' => $text_report,
                'parse_mode' => "HTML"
            ]);
        }
    } elseif ($steppay[0] == "getextratimeuser") {
        $steppay = explode("%", $steppay[1]);
        $tmieextra = $steppay[1];
        $nameloc = select("invoice", "*", "username", $steppay[0], "select");
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
        $Balance_Low_user = 0;
        $inboundid = $marzban_list_get['inboundid'];
        if ($nameloc['inboundid'] != false) {
            $inboundid = $nameloc['inboundid'];
        }
        update("user", "Balance", $Balance_Low_user, "id", $nameloc['id_user']);
        $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $steppay[0]);
        $data_for_database = json_encode(array(
            'day' => $tmieextra,
            'old_volume' => $DataUserOut['data_limit'],
            'expire_old' => $DataUserOut['expire']
        ));
        $dateacc = date('Y/m/d H:i:s');
        $type = "extra_time_user";
        $timeservice = $DataUserOut['expire'] - time();
        $day = floor($timeservice / 86400);
        $extra_time = $ManagePanel->extra_time($nameloc['username'], $marzban_list_get['code_panel'], $tmieextra);
        if ($extra_time['status'] == false) {
            $extra_time['msg'] = json_encode($extra_time['msg']);
            $textreports = "خطای خرید حجم اضافه
نام پنل : {$marzban_list_get['name_panel']}
نام کاربری سرویس : {$nameloc['username']}
دلیل خطا : {$extra_time['msg']}";
            sendmessage($from_id, "❌خطایی در خرید حجم اضافه سرویس رخ داده با پشتیبانی در ارتباط باشید", null, 'HTML');
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $textreports,
                    'parse_mode' => "HTML"
                ]);
            }
            return true;
        }
        $stmt = $pdo->prepare("INSERT IGNORE INTO service_other (id_user, username,value,type,time,price,output) VALUES (:id_user,:username,:value,:type,:time,:price,:output)");
        $stmt->bindParam(':id_user', $Balance_id['id']);
        $stmt->bindParam(':username', $steppay[0]);
        $stmt->bindParam(':value', $data_for_database);
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':time', $dateacc);
        $stmt->bindParam(':price', $Payment_report['price']);
        $stmt->bindParam(':output', json_encode($extra_time));
        $stmt->execute();
        $keyboardextrafnished = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['stateus']['backservice'], 'callback_data' => "product_" . $nameloc['id_invoice']],
                ]
            ]
        ]);
        $volumesformat = number_format($Payment_report['price']);
        if (intval($setting['scorestatus']) == 1 and !in_array($Balance_id['id'], $admin_ids)) {
            sendmessage($Balance_id['id'], "📌شما 1 امتیاز جدید کسب کردید.", null, 'html');
            $scorenew = $Balance_id['score'] + 1;
            update("user", "score", $scorenew, "id", $Balance_id['id']);
        }
        $textextratime = "✅ افزایش زمان برای سرویس شما با موفقیت صورت گرفت
 
▫️نام سرویس : {$steppay[0]}
▫️زمان اضافه : $tmieextra روز

▫️مبلغ افزایش زمان : $volumesformat تومان";
        sendmessage($Balance_id['id'], $textextratime, $keyboardextrafnished, 'HTML');
        if ($Payment_report['Payment_Method'] == "cart to cart") {
            $volumes = $tmieextra;
            $textconfrom = "✅ پرداخت تایید شده
🔋 خرید زمان اضافه
🛍 زمان خریداری شده  : $volumes روز
👤 نام کاربری کانفیگ {$steppay[0]}
👤 شناسه کاربر: <code>{$Balance_id['id']}</code>
🛒 کد پیگیری پرداخت: {$Payment_report['id_order']}
⚜️ نام کاربری: " . '@' . vira_user_tg_username($Balance_id) . "
💎 موجودی قبل ازافزایش موجودی : {$Balance_id['Balance']}
💸 مبلغ پرداختی: $format_price_cart تومان
";
            vira_edit_payment_status_messages($Payment_report, $textconfrom, $Confirm_pay);
        }
        update("invoice", "Status", "active", "id_invoice", $nameloc['id_invoice']);
        $text_report = "⭕️ یک کاربر زمان اضافه خریده است
        
اطلاعات کاربر : 
🪪 آیدی عددی : {$Balance_id['id']}
🛍 زمان خریداری شده  : $volumes روز
💰 مبلغ پرداختی : {$Payment_report['price']} تومان
👤 نام کاربری کانفیگ {$steppay[0]}";
        if (strlen($setting['Channel_Report']) > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $otherservice,
                'text' => $text_report,
            ]);
        }
    } else {
        if (vira_wallet_credit_user((string) $Payment_report['id_user'], 'pay:' . $order_id, (int) $Payment_report['price'], 'direct_payment')) {
            $Payment_report['price'] = number_format($Payment_report['price'], 0);
            $format_price_cart = $Payment_report['price'];
            if ($Payment_report['Payment_Method'] == "cart to cart" or $Payment_report['Payment_Method'] == "arze digital offline") {
                $textconfrom = "⭕️ یک پرداخت جدید انجام شده است
        افزایش موجودی.
👤 شناسه کاربر: <code>{$Balance_id['id']}</code>
🛒 کد پیگیری پرداخت: {$Payment_report['id_order']}
⚜️ نام کاربری: " . '@' . vira_user_tg_username($Balance_id) . "
💸 مبلغ پرداختی: $format_price_cart تومان
💎 موجودی قبل ازافزایش موجودی : {$Balance_id['Balance']}
✍️ توضیحات : {$Payment_report['dec_not_confirmed']}";
                vira_edit_payment_status_messages($Payment_report, $textconfrom, $Confirm_pay);
            }
            sendmessage($Payment_report['id_user'], "💎 کاربر گرامی مبلغ {$Payment_report['price']} تومان به کیف پول شما واریز گردید با تشکراز پرداخت شما.
                
🛒 کد پیگیری شما: {$Payment_report['id_order']}", null, 'HTML');
        }
    }
    return true;
}
function plisio($order_id, $price)
{
    $apinowpayments = select("PaySetting", "ValuePay", "NamePay", "apinowpayment", "select")['ValuePay'];
    $api_key = $apinowpayments;

    $url = 'https://api.plisio.net/api/v1/invoices/new';
    $url .= '?source_currency=USD';
    $url .= '&source_amount=' . urlencode($price);
    $url .= '&order_number=' . urlencode($order_id);
    $url .= '&email=customer@plisio.net';
    $url .= '&order_name=plisio';
    $url .= '&language=fa';
    $url .= '&api_key=' . urlencode($api_key);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = json_decode(curl_exec($ch), true);
    return $response['data'];
    curl_close($ch);
}
function checkConnection($address, $port)
{
    $socket = @stream_socket_client("tcp://$address:$port", $errno, $errstr, 5);
    if ($socket) {
        fclose($socket);
        return true;
    } else {
        return false;
    }
}
function savedata($type, $namefiled, $valuefiled)
{
    global $from_id;
    if ($type == "clear") {
        $datauser = [];
        $datauser[$namefiled] = $valuefiled;
        $data = json_encode($datauser);
        update("user", "Processing_value", $data, "id", $from_id);
    } elseif ($type == "save") {
        $userdata = select("user", "*", "id", $from_id, "select");
        $dataperevieos = json_decode($userdata['Processing_value'], true);
        $dataperevieos[$namefiled] = $valuefiled;
        update("user", "Processing_value", json_encode($dataperevieos), "id", $from_id);
    }
}
function addFieldToTable($tableName, $fieldName, $defaultValue = null, $datatype = "VARCHAR(500)")
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_name = :tableName");
    $stmt->bindParam(':tableName', $tableName);
    $stmt->execute();
    $tableExists = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($tableExists['count'] == 0)
        return;
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$pdo->query("SELECT DATABASE()")->fetchColumn(), $tableName, $fieldName]);
    $filedExists = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($filedExists['count'] != 0)
        return;
    $query = "ALTER TABLE $tableName ADD $fieldName $datatype";
    $statement = $pdo->prepare($query);
    $statement->execute();
    if ($defaultValue != null) {
        $stmt = $pdo->prepare("UPDATE $tableName SET $fieldName= ?");
        $stmt->bindParam(1, $defaultValue);
        $stmt->execute();
    }
    echo "The $fieldName field was added ✅";
}

/** Auto-migrate user.lang (per-user language — ViraNaut 3.0+). */
function vira_ensure_user_lang_column(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    global $pdo;
    if (!isset($pdo)) {
        return;
    }
    $done = true;
    try {
        $stmt = $pdo->query(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user' AND COLUMN_NAME = 'lang'"
        );
        if ($stmt && (int) $stmt->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE `user` ADD `lang` VARCHAR(5) NULL DEFAULT 'fa'");
        }
    } catch (Throwable $e) {
        error_log('vira_ensure_user_lang_column: ' . $e->getMessage());
    }
}

/** Auto-migrate marzban_panel columns (silent — safe on every webhook). */
function vira_ensure_marzban_panel_columns()
{
    static $done = false;
    if ($done) {
        return;
    }
    global $pdo;
    if (!isset($pdo)) {
        return;
    }
    $done = true;
    try {
        $stmt = $pdo->query(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'marzban_panel' AND COLUMN_NAME = 'xui_api_token'"
        );
        if ($stmt && (int) $stmt->fetchColumn() === 0) {
            $pdo->exec('ALTER TABLE marzban_panel ADD xui_api_token TEXT NULL');
        }
    } catch (Exception $e) {
        error_log('vira_ensure_marzban_panel_columns: ' . $e->getMessage());
    }
    try {
        $pdo->exec("UPDATE marzban_panel SET type = 'vira_agent' WHERE type = 'mirza_agent'");
    } catch (Exception $e) {
        error_log('vira_ensure_marzban_panel_columns type migrate: ' . $e->getMessage());
    }
}

function outtypepanel($typepanel, $message)
{
    global $from_id, $optionMarzban, $optionX_ui_single, $optionhiddfy, $optionalireza, $optionalireza_single, $optionmarzneshin, $option_mikrotik, $optionwg, $options_ui, $optioneylanpanel, $optionibsng;
    if ($typepanel == "marzban") {
        sendmessage($from_id, $message, $optionMarzban, 'HTML');
    } elseif ($typepanel == "x-ui_single") {
        sendmessage($from_id, $message, $optionX_ui_single, 'HTML');
    } elseif ($typepanel == "hiddify") {
        sendmessage($from_id, $message, $optionhiddfy, 'HTML');
    } elseif ($typepanel == "alireza_single") {
        sendmessage($from_id, $message, $optionalireza_single, 'HTML');
    } elseif ($typepanel == "marzneshin") {
        sendmessage($from_id, $message, $optionmarzneshin, 'HTML');
    } elseif ($typepanel == "WGDashboard") {
        sendmessage($from_id, $message, $optionwg, 'HTML');
    } elseif ($typepanel == "s_ui") {
        sendmessage($from_id, $message, $options_ui, 'HTML');
    } elseif ($typepanel == "ibsng") {
        sendmessage($from_id, $message, $optionibsng, 'HTML');
    } elseif ($typepanel == "mikrotik") {
        sendmessage($from_id, $message, $option_mikrotik, 'HTML');
    } elseif (in_array($typepanel, ['vira_agent', 'ilan', 'pasarguard'], true)) {
        sendmessage($from_id, $message, $optionMarzban, 'HTML');
    }
}

function addBackgroundImage($urlimage, $qrCodeResult, $backgroundPath)
{
    if (!file_exists($backgroundPath)) {
        error_log("addBackgroundImage: File not found at $backgroundPath");
        file_put_contents($urlimage, $qrCodeResult->getString());
        return;
    }

    $qrString = $qrCodeResult->getString();
    $qrCodeImage = imagecreatefromstring($qrString);
    if (!$qrCodeImage) {
        error_log("addBackgroundImage: Failed to create QR Code resource");
        return;
    }

    $backgroundImage = null;

    try {
        $backgroundImage = imagecreatefromjpeg($backgroundPath);
    } catch (Throwable $t) {
        error_log("addBackgroundImage::EXCEPTION loading image: " . $t->getMessage());
    }

    if (!$backgroundImage) {
        $lastError = error_get_last();
        error_log("addBackgroundImage::System Error: " . $lastError['message']);

        imagepng($qrCodeImage, $urlimage);
        imagedestroy($qrCodeImage);
        return;
    }

    $qrCodeWidth = imagesx($qrCodeImage);
    $qrCodeHeight = imagesy($qrCodeImage);
    $backgroundWidth = imagesx($backgroundImage);
    $backgroundHeight = imagesy($backgroundImage);

    $x = ($backgroundWidth - $qrCodeWidth) / 2;
    $y = ($backgroundHeight - $qrCodeHeight) / 2;

    imagecopy($backgroundImage, $qrCodeImage, $x, $y, 0, 0, $qrCodeWidth, $qrCodeHeight);

    imagepng($backgroundImage, $urlimage);

    imagedestroy($qrCodeImage);
    imagedestroy($backgroundImage);
}

function vira_client_ip_for_telegram_check()
{
    $candidates = [];
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
        if (empty($_SERVER[$key])) {
            continue;
        }
        if ($key === 'HTTP_X_FORWARDED_FOR') {
            foreach (explode(',', (string) $_SERVER[$key]) as $part) {
                $ip = trim($part);
                if ($ip !== '') {
                    $candidates[] = $ip;
                }
            }
            continue;
        }
        $ip = trim((string) $_SERVER[$key]);
        if ($ip !== '') {
            $candidates[] = $ip;
        }
    }
    foreach ($candidates as $ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }
    return '';
}

function vira_ip_in_telegram_ranges(string $ip): bool
{
    if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
        return false;
    }
    $telegramIpRanges = [
        ['lower' => '149.154.160.0', 'upper' => '149.154.175.255'],
        ['lower' => '91.108.4.0', 'upper' => '91.108.7.255'],
        ['lower' => '2001:67c:4e8::', 'upper' => '2001:67c:4e8:ffff:ffff:ffff:ffff:ffff'],
    ];
    foreach ($telegramIpRanges as $range) {
        if (isClientIpInRange($ip, $range['lower'], $range['upper'])) {
            return true;
        }
    }
    return false;
}

function checktelegramip()
{
    $candidates = [];
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
        if (empty($_SERVER[$key])) {
            continue;
        }
        if ($key === 'HTTP_X_FORWARDED_FOR') {
            foreach (explode(',', (string) $_SERVER[$key]) as $part) {
                $ip = trim($part);
                if ($ip !== '') {
                    $candidates[] = $ip;
                }
            }
            continue;
        }
        $ip = trim((string) $_SERVER[$key]);
        if ($ip !== '') {
            $candidates[] = $ip;
        }
    }
    foreach ($candidates as $ip) {
        if (vira_ip_in_telegram_ranges($ip)) {
            return true;
        }
    }
    return false;
}

function isClientIpInRange($clientIp, $lowerBound, $upperBound)
{
    $clientPacked = inet_pton($clientIp);
    $lowerPacked = inet_pton($lowerBound);
    $upperPacked = inet_pton($upperBound);

    if ($clientPacked === false || $lowerPacked === false || $upperPacked === false) {
        return false;
    }

    $length = strlen($clientPacked);
    if ($length !== strlen($lowerPacked) || $length !== strlen($upperPacked)) {
        return false;
    }

    return strcmp($clientPacked, $lowerPacked) >= 0 && strcmp($clientPacked, $upperPacked) <= 0;
}
function addCronIfNotExists($cronCommand)
{
    $commands = is_array($cronCommand) ? $cronCommand : [$cronCommand];
    $commands = array_values(array_filter(array_map('trim', $commands), static function ($command) {
        return $command !== '';
    }));

    if (empty($commands)) {
        return true;
    }

    $logContext = implode('; ', $commands);

    if (!isShellExecAvailable()) {
        error_log('shell_exec is not available; unable to register cron job(s): ' . $logContext);
        return false;
    }

    $crontabBinary = getCrontabBinary();
    if ($crontabBinary === null) {
        error_log('crontab executable not found; unable to register cron job(s): ' . $logContext);
        return false;
    }

    $existingCronJobs = runShellCommand(sprintf('%s -l 2>/dev/null', escapeshellarg($crontabBinary)));
    $existingCronJobs = trim((string) $existingCronJobs);
    $cronLines = $existingCronJobs === '' ? [] : preg_split('/\r?\n/', $existingCronJobs);
    $cronLines = array_values(array_filter(array_map('trim', $cronLines), static function ($line) {
        return $line !== '' && strpos($line, '#') !== 0;
    }));

    $newLineAdded = false;
    foreach ($commands as $command) {
        if (!in_array($command, $cronLines, true)) {
            $cronLines[] = $command;
            $newLineAdded = true;
        }
    }

    if (!$newLineAdded) {
        return true;
    }

    $cronLines = array_values(array_unique($cronLines));
    $cronContent = implode(PHP_EOL, $cronLines) . PHP_EOL;

    $temporaryFile = tempnam(sys_get_temp_dir(), 'cron');
    if ($temporaryFile === false) {
        error_log('Unable to create temporary file for cron job registration.');
        return false;
    }

    if (file_put_contents($temporaryFile, $cronContent) === false) {
        error_log('Unable to write cron configuration to temporary file: ' . $temporaryFile);
        unlink($temporaryFile);
        return false;
    }

    runShellCommand(sprintf('%s %s', escapeshellarg($crontabBinary), escapeshellarg($temporaryFile)));
    unlink($temporaryFile);

    return true;
}

function activecron()
{
    global $domainhosts;

    $cronCommands = [
        "*/15 * * * * curl https://$domainhosts/cronbot/statusday.php",
        "*/1 * * * * curl https://$domainhosts/cronbot/NoticationsService.php",
        "*/1 * * * * curl https://$domainhosts/cronbot/croncard.php",
        "*/5 * * * * curl https://$domainhosts/cronbot/payment_expire.php",
        "*/1 * * * * curl https://$domainhosts/cronbot/sendmessage.php",
        "*/3 * * * * curl https://$domainhosts/cronbot/plisio.php",
        "*/1 * * * * curl https://$domainhosts/cronbot/activeconfig.php",
        "*/1 * * * * curl https://$domainhosts/cronbot/disableconfig.php",
        "*/1 * * * * curl https://$domainhosts/cronbot/iranpay1.php",
        "0 */5 * * * curl https://$domainhosts/cronbot/backupbot.php",
        "*/2 * * * * curl https://$domainhosts/cronbot/gift.php",
        "*/30 * * * * curl https://$domainhosts/cronbot/expireagent.php",
        "*/15 * * * * curl https://$domainhosts/cronbot/on_hold.php",
        "*/2 * * * * curl https://$domainhosts/cronbot/configtest.php",
        "*/15 * * * * curl https://$domainhosts/cronbot/uptime_node.php",
        "*/15 * * * * curl https://$domainhosts/cronbot/uptime_panel.php",
    ];

    addCronIfNotExists($cronCommands);
}
function createInvoice($amount)
{
    global $from_id, $domainhosts;
    $PaySetting = select("PaySetting", "*", "NamePay", "apiiranpay", "select")['ValuePay'];
    $walletaddress = select("PaySetting", "*", "NamePay", "walletaddress", "select")['ValuePay'];

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://pay.melorinabeauty.com/api/factor/create',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => array('amount' => $amount, 'address' => $walletaddress, 'base' => 'trx'),
        CURLOPT_HTTPHEADER => array(
            'Authorization: Token ' . $PaySetting
        ),
    ));

    $response = curl_exec($curl);

    curl_close($curl);

    return json_decode($response, true);
}
function verifpay($id)
{
    global $from_id, $domainhosts;
    $PaySetting = select("PaySetting", "*", "NamePay", "apiiranpay", "select")['ValuePay'];
    $walletaddress = select("PaySetting", "*", "NamePay", "walletaddress", "select")['ValuePay'];
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://pay.melorinabeauty.ir/api/factor/status?id=' . $id,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
            'Authorization: Token ' . $PaySetting
        ),
    ));

    $response = curl_exec($curl);

    curl_close($curl);

    return $response;
}
function createInvoiceiranpay1($amount, $id_invoice)
{
    global $domainhosts;
    $PaySetting = select("PaySetting", "*", "NamePay", "marchent_floypay", "select")['ValuePay'];
    $curl = curl_init();
    $amount = intval($amount);
    $data = [
        "ApiKey" => $PaySetting,
        "Hash_id" => $id_invoice,
        "Amount" => $amount . "0",
        "CallbackURL" => "https://$domainhosts/payment/iranpay1.php"
    ];
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://tetra98.com/api/create_order",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => array(
            'accept: application/json',
            'Content-Type: application/json'
        ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);
    return json_decode($response, true);
}
function sanitizeUserName($userName)
{
    $forbiddenCharacters = [
        "'",
        "\"",
        "<",
        ">",
        "--",
        "#",
        ";",
        "\\",
        "%",
        "(",
        ")"
    ];

    foreach ($forbiddenCharacters as $char) {
        $userName = str_replace($char, "", $userName);
    }

    return $userName;
}
function publickey()
{
    $privateKey = sodium_crypto_box_keypair();
    $privateKeyEncoded = base64_encode(sodium_crypto_box_secretkey($privateKey));
    $publicKey = sodium_crypto_box_publickey($privateKey);
    $publicKeyEncoded = base64_encode($publicKey);
    $presharedKey = base64_encode(random_bytes(32));
    return [
        'private_key' => $privateKeyEncoded,
        'public_key' => $publicKeyEncoded,
        'preshared_key' => $presharedKey
    ];
}
/** Map legacy 0.1.5 textbotlang keys onto 0.1.7 text.json camelCase structure. */
function vira_textbotlang_normalize(string $s): string
{
    return strtolower(preg_replace('/[^a-z0-9]/', '', $s));
}

function vira_textbotlang_pascal_segments(string $key): string
{
    $parts = preg_split('/(?=[A-Z])/', $key, -1, PREG_SPLIT_NO_EMPTY);
    if (!$parts) {
        return $key;
    }
    return implode('', array_map(static fn($p) => ucfirst(strtolower($p)), $parts));
}

function vira_textbotlang_legacy_key_variants(string $key): array
{
    $variants = [];
    $lower = strtolower($key);
    if ($lower !== $key) {
        $variants[] = $lower;
    }
    $pascalLower = ucfirst(strtolower($key));
    if ($pascalLower !== $key) {
        $variants[] = $pascalLower;
    }
    $pascalSegments = vira_textbotlang_pascal_segments($key);
    if ($pascalSegments !== $key) {
        $variants[] = $pascalSegments;
    }
    $snake = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $key));
    if ($snake !== $key) {
        $variants[] = $snake;
    }
    $kebab = str_replace('_', '-', $snake);
    if ($kebab !== $key && $kebab !== $snake) {
        $variants[] = $kebab;
    }
    if ($key !== '' && preg_match('/^[a-z]/', $key)) {
        $parts = preg_split('/(?=[A-Z])/', $key, -1, PREG_SPLIT_NO_EMPTY);
        if (count($parts) > 1) {
            $legacy = ucfirst(strtolower($parts[0]));
            for ($i = 1, $c = count($parts); $i < $c; $i++) {
                $legacy .= '-' . strtolower($parts[$i]);
            }
            if ($legacy !== $key) {
                $variants[] = $legacy;
            }
        }
    }
    return array_values(array_unique($variants));
}

function vira_textbotlang_legacy_section_variants(string $key): array
{
    $variants = [];
    $lower = strtolower($key);
    if ($lower !== $key) {
        $variants[] = $lower;
    }
    $pascal = vira_textbotlang_pascal_segments($key);
    if ($pascal !== $key) {
        $variants[] = $pascal;
    }
    if ($key !== '' && preg_match('/^[a-z]/', $key)) {
        $parts = preg_split('/(?=[A-Z])/', $key, -1, PREG_SPLIT_NO_EMPTY);
        if (count($parts) > 1) {
            $legacy = ucfirst($parts[0]);
            for ($i = 1, $c = count($parts); $i < $c; $i++) {
                $legacy .= '_' . $parts[$i];
            }
            if ($legacy !== $key) {
                $variants[] = $legacy;
            }
        }
    }
    return array_values(array_unique($variants));
}

function vira_textbotlang_set_nested(array &$root, array $segments, $value): void
{
    if ($segments === []) {
        return;
    }
    $ref = &$root;
    $last = array_pop($segments);
    foreach ($segments as $segment) {
        if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
            $ref[$segment] = [];
        }
        $ref = &$ref[$segment];
    }
    if (!array_key_exists($last, $ref)) {
        $ref[$last] = $value;
    }
}

function vira_textbotlang_collect_string_leaves(array $node, array $prefix = []): array
{
    $out = [];
    foreach ($node as $key => $value) {
        if (is_array($value)) {
            $allStrings = $value !== [] && !array_filter($value, static fn($v) => !is_string($v));
            if ($allStrings) {
                foreach ($value as $leafKey => $leafValue) {
                    if (is_string($leafValue)) {
                        $out[implode('.', array_merge($prefix, [(string) $key, (string) $leafKey]))] = [$leafValue, array_merge($prefix, [(string) $key, (string) $leafKey])];
                    }
                }
            } else {
                $out += vira_textbotlang_collect_string_leaves($value, array_merge($prefix, [(string) $key]));
            }
        }
    }
    return $out;
}

function vira_textbotlang_explicit_typo_aliases(): array
{
    return [
        'users.sell.Service-select-first' => 'users.sell.serviceSelectFirst',
        'users.sell.Service-select' => 'users.sell.serviceSelect',
        'users.sell.selectCategory' => 'extracted.index_php.selectCategory',
        'users.sell.None-credit' => 'users.sell.noCredit',
        'users.sell.ErrorConfig' => 'users.sell.errorConfig',
        'users.Balance.addbalamceuser' => 'users.Balance.addBalanceUser',
        'users.Balance.reject_pay' => 'users.Balance.rejectPay',
        'users.Balance.selectPatment' => 'users.Balance.selectPayment',
        'users.Balance.Confirmpayadmin' => 'users.Balance.confirmPayAdmin',
        'users.Balance.Confirmpaying' => 'users.Balance.confirmPaying',
        'users.Balance.Send-receipt' => 'users.Balance.sendReceipt',
        'users.Balance.Send-receiptadnsendconfig' => 'users.Balance.sendReceiptAndConfig',
        'users.Discount.gift-deposit' => 'users.Discount.gift-deposit',
        'users.Extra_volume.ChangedPrice' => 'users.extraVolume.changedPrice',
        'users.Extra_volume.gettypeextra' => 'users.extraVolume.gettypeextra',
        'users.Extra_time.extratimecheck' => 'users.extraTime.extratimecheck',
        'Admin.agent.invalidvlue' => 'Admin.agent.invalidValue',
        'Admin.manageadmin.adminedsenduser' => 'Admin.manageadmin.adminAddedSendUser',
        'Admin.Product.nullpProduct' => 'Admin.Product.nullProduct',
        'Admin.SettingnowPayment.Savaapi' => 'Admin.SettingnowPayment.saveApi',
        'Admin.SettingPayment.Savacard' => 'Admin.SettingPayment.saveCard',
        'Admin.Balance.PriceBalancek' => 'Admin.Balance.priceBalance',
        'Admin.ManageUser.mangebtnuser' => 'Admin.manageUser.manageUserBtn',
        'Admin.ManageUser.mangebtnuserdec' => 'Admin.manageUser.manageUserBtnDesc',
        'Admin.ManageUser.addbalanceuserdec' => 'Admin.manageUser.addBalanceUserDesc',
        'Admin.ManageUser.lowbalanceuserdec' => 'Admin.manageUser.lowBalanceUserDesc',
        'Admin.ManageUser.sendpayemntlist' => 'Admin.manageUser.sendPaymentList',
        'Admin.ManageUser.GetIdUserunblock' => 'Admin.manageUser.getIdUserUnblock',
        'Admin.ManageUser.Acceptedphone' => 'Admin.manageUser.acceptedPhone',
        'Admin.ManageUser.Failedphone' => 'Admin.manageUser.failedPhone',
        'Admin.ManageUser.addbalanced' => 'Admin.manageUser.addBalanced',
        'Admin.ManageUser.lowbalanced' => 'Admin.manageUser.lowBalanced',
        'Admin.ManageUser.newuser' => 'Admin.manageUser.newUser',
        'Admin.ManageUser.MessageSent' => 'Admin.manageUser.messageSent',
        'Admin.Algortimeextend.SaveData' => 'Admin.algorithmExtend.saveData',
        'Admin.AlgortimeUsername.SaveData' => 'Admin.algorithmUsername.saveData',
        'Admin.Discount.invalidcodedis' => 'Admin.Discount.invalidCode',
        'Admin.Help.GetAddDecHelp' => 'Admin.Help.getAddDesc',
        'Admin.Help.GetAddNameHelp' => 'Admin.Help.getAddName',
        'Admin.Product.GettIime' => 'Admin.Product.getTime',
        'Admin.Product.RemoveedProduct' => 'Admin.Product.removedProduct',
        'Admin.Product.Rmove_location' => 'Admin.Product.removeLocation',
        'Admin.managepanel.ChangedNmaePanel' => 'Admin.managepanel.changedNamePanel',
        'Admin.managepanel.ChangedurlPanel' => 'Admin.managepanel.changedUrlPanel',
        'Admin.managepanel.ChangedusernamePanel' => 'Admin.managepanel.changedUsernamePanel',
        'Admin.managepanel.ChangedpasswordPanel' => 'Admin.managepanel.changedPasswordPanel',
        'Admin.managepanel.Inbound.gettypepanel' => 'Admin.managepanel.Inbound.getPanelType',
        'Admin.btnkeyboardadmin.managruser' => 'Admin.btnKeyboard.manageUser',
        'Admin.btnkeyboardadmin.addpanel' => 'Admin.btnKeyboard.addPanel',
        'Admin.btnkeyboardadmin.managementpanel' => 'Admin.btnKeyboard.managementPanel',
        'users.Discount.erorrlimit' => 'users.Discount.errorLimit',
        'users.Discount.erorrlimitdiscount' => 'users.Discount.errorLimitDiscount',
        'Admin.ManageUser.GetIDMessage' => 'Admin.manageUser.getIdMessage',
        'Admin.ManageUser.SendMessageuser' => 'Admin.manageUser.sendMessageUser',
        'Admin.Discount.PriceCodesell' => 'Admin.Discount.priceCodeSell',
        'Admin.Status.stautsbot' => 'Admin.Status.statusBot',
        'Admin.Status.stautsrolee' => 'Admin.Status.statusRole',
        'Admin.Status.cardTitlepv' => 'Admin.Status.cardTitlePv',
        'Admin.Status.cardStatusOffpv' => 'Admin.Status.cardStatusOffPv',
        'Admin.Status.cardStatusonpv' => 'Admin.Status.cardStatusOnPv',
        'Admin.Status.commissionStatusOff' => 'Admin.Status.commissionOff',
        'Admin.Status.commissionStatuson' => 'Admin.Status.commissionOn',
        'Admin.Status.DiscountaffiliatesStatusOff' => 'Admin.Status.discountAffiliatesOff',
        'Admin.Status.DiscountaffiliatesStatuson' => 'Admin.Status.discountAffiliatesOn',
        'Admin.Status.cardStatusOffautoconfirmcard' => 'Admin.Status.autoConfirmOff',
        'Admin.Status.cardStatusonautoconfirmcard' => 'Admin.Status.autoConfirmOn',
        'Admin.Status.activepanelStatusOff' => 'Admin.Status.activePanelOff',
        'Admin.Status.activepaneltatuson' => 'Admin.Status.activePanelOn',
        'users.affiliates.changedpriceDiscount' => 'users.affiliates.changedPriceDiscount',
        'users.stateus.accecptreqests' => 'users.status.acceptRequests',
        'users.stateus.notUsernameget' => 'users.status.notUsernameGet',
        'users.stateus.descriptions_removeservice' => 'users.status.descriptionsRemoveService',
        'users.stateus.exitsrequsts' => 'users.status.exitsRequests',
        'Admin.addorder.towstep' => 'Admin.addorder.stepTwo',
        'Admin.addorder.threestep' => 'Admin.addorder.stepThree',
        'Admin.addorder.fourstep' => 'Admin.addorder.stepFour',
        'Admin.addorder.fivestep' => 'Admin.addorder.stepFive',
        'Admin.change-location.title' => 'Admin.changeLocation.title',
        'Admin.change-location.confirm' => 'Admin.changeLocation.confirm',
        'Admin.transfor.title' => 'Admin.transfer.title',
        'Admin.transfor.transfornotvalid' => 'Admin.transfer.transferNotValid',
        'Admin.transfor.discription' => 'Admin.transfer.description',
        'Admin.transfor.notusertrns' => 'Admin.transfer.notUserTrans',
        'Admin.transfor.confirm' => 'Admin.transfer.confirm',
        'Admin.transfor.notsendserviceyou' => 'Admin.transfer.notSendServiceYou',
        'Admin.btnkeyboardadmin.addpanel' => 'Admin.btnKeyboard.addPanel',
        'Admin.btnkeyboardadmin.managementpanel' => 'Admin.btnKeyboard.managementPanel',
        'users.Discount.erorrlimitdiscount' => 'users.Discount.errorLimitDiscount',
        'textbot.snowPayment' => 'textbot.cryptoPayment',
    ];
}

function vira_textbotlang_resolve_dot_path(array $lang, string $path): ?string
{
    $segments = explode('.', $path);
    $cur = $lang;
    foreach ($segments as $segment) {
        if (!is_array($cur) || !array_key_exists($segment, $cur)) {
            return null;
        }
        $cur = $cur[$segment];
    }
    return is_string($cur) ? $cur : null;
}

function vira_textbotlang_apply_explicit_aliases(array &$lang): void
{
    foreach (vira_textbotlang_explicit_typo_aliases() as $legacyPath => $canonicalPath) {
        $value = vira_textbotlang_resolve_dot_path($lang, $canonicalPath);
        if ($value === null) {
            continue;
        }
        vira_textbotlang_set_nested($lang, explode('.', $legacyPath), $value);
    }
}

function vira_textbotlang_apply_legacy_aliases(array &$tree): void
{
    foreach ($tree as $key => $value) {
        if (is_array($value)) {
            vira_textbotlang_apply_legacy_aliases($tree[$key]);
            foreach (vira_textbotlang_legacy_section_variants((string) $key) as $alias) {
                if (!array_key_exists($alias, $tree)) {
                    $tree[$alias] = $tree[$key];
                }
            }
        } else {
            foreach (vira_textbotlang_legacy_key_variants((string) $key) as $alias) {
                if (!array_key_exists($alias, $tree)) {
                    $tree[$alias] = $value;
                }
            }
        }
    }
}

function vira_textbotlang_expand_path_aliases(array &$lang): void
{
    foreach (['users', 'Admin', 'textbot', 'keyboard'] as $branch) {
        if (!isset($lang[$branch]) || !is_array($lang[$branch])) {
            continue;
        }
        vira_textbotlang_apply_legacy_aliases($lang[$branch]);
        $leaves = vira_textbotlang_collect_string_leaves($lang[$branch], [$branch]);
        foreach ($leaves as $leafInfo) {
            [$value, $segments] = $leafInfo;
            $count = count($segments);
            for ($i = 1; $i < $count; $i++) {
                foreach (vira_textbotlang_legacy_section_variants($segments[$i]) as $variant) {
                    if ($variant === $segments[$i]) {
                        continue;
                    }
                    $alt = $segments;
                    $alt[$i] = $variant;
                    vira_textbotlang_set_nested($lang, $alt, $value);
                }
            }
            $leafKey = $segments[$count - 1];
            foreach (vira_textbotlang_legacy_key_variants($leafKey) as $variant) {
                if ($variant === $leafKey) {
                    continue;
                }
                $alt = $segments;
                $alt[$count - 1] = $variant;
                vira_textbotlang_set_nested($lang, $alt, $value);
            }
        }
    }
}

function vira_apply_textbotlang_compat(array &$lang)
{
    vira_textbotlang_apply_legacy_aliases($lang);
    vira_textbotlang_expand_path_aliases($lang);

    if (isset($lang['users']['status']) && is_array($lang['users']['status'])) {
        $stateusExtra = (isset($lang['users']['stateus']) && is_array($lang['users']['stateus']))
            ? $lang['users']['stateus']
            : [];
        $lang['users']['stateus'] = array_merge($lang['users']['status'], $stateusExtra);
        vira_textbotlang_apply_legacy_aliases($lang['users']['stateus']);
    }

    vira_textbotlang_apply_explicit_aliases($lang);

    if (!isset($lang['Admin']) || !is_array($lang['Admin'])) {
        $lang['Admin'] = $lang['Admin'] ?? [];
    }
    $A = &$lang['Admin'];
    $top = [
        'textpaneladmin' => 'panelAdmin',
        'activebottext' => 'activeBotText',
        'backadmin' => 'backAdminBtn',
        'backmenu' => 'backMenuBtn',
        'Back-menu' => 'backMenu',
        'Back-Admin' => 'backAdmin',
        'getstats' => 'getStats',
        'not-user' => 'notUser',
        'Algortimeextend' => 'algorithmExtend',
        'AlgortimeUsername' => 'algorithmUsername',
        'ManageUser' => 'manageUser',
        'btnkeyboardadmin' => 'btnKeyboard',
        'settingPayment' => 'SettingPayment',
        'settingNowPayment' => 'SettingnowPayment',
        'Discountsell' => 'discountSell',
    ];
    foreach ($top as $old => $new) {
        if (!isset($A[$old]) && isset($A[$new])) {
            $A[$old] = $A[$new];
        }
    }
    if (isset($A['manageUser']) && is_array($A['manageUser']) && !isset($A['ManageUser'])) {
        $A['ManageUser'] = $A['manageUser'];
    }
    if (isset($A['ManageUser']) && is_array($A['ManageUser'])) {
        $textDefaults = [
            'ChangeTextGet' => '📝 متن فعلی: ',
            'ErrorText' => '❌ متن نامعتبر است',
            'SaveText' => '✅ متن ذخیره شد',
        ];
        foreach ($textDefaults as $key => $value) {
            if (!isset($A['ManageUser'][$key])) {
                $A['ManageUser'][$key] = $value;
            }
        }
    }
    if (isset($lang['users']['agent']) && is_array($lang['users']['agent']) && !isset($lang['users']['agenttext'])) {
        $lang['users']['agenttext'] = $lang['users']['agent'];
    }
    if (isset($lang['textbot']) && is_array($lang['textbot'])) {
        if (!isset($lang['textbot']['start'])) {
            $lang['textbot']['start'] = $lang['users']['text_start'] ?? 'سلام خوش آمدید';
        }
        if (!isset($lang['textbot']['snowPayment']) && isset($lang['textbot']['cryptoPayment'])) {
            $lang['textbot']['snowPayment'] = $lang['textbot']['cryptoPayment'];
        }
    }
    if (!isset($lang['panel']) || !is_array($lang['panel'])) {
        $lang['panel'] = [];
    }
    $panelDefaults = [
        'configInvalidRequest' => 'درخواست نامعتبر.',
        'loginPanelTitle' => 'ورود — پنل مدیریت ویرا',
        'loginHeading' => 'پنل مدیریت ویرا',
        'loginSubtitle' => 'برای مدیریت ربات وارد شوید',
        'loginUsernameLabel' => 'نام کاربری',
        'loginUsernamePlaceholder' => 'admin',
        'loginPasswordLabel' => 'رمز عبور',
        'loginPasswordPlaceholder' => '••••••••',
        'loginButton' => 'ورود به پنل',
        'loginRememberMe' => 'مرا به خاطر بسپار',
        'loginEnterCredentials' => 'نام کاربری و رمز عبور را وارد کنید.',
        'loginTooManyAttempts' => 'تعداد تلاش‌های ناموفق بیش از حد. لطفاً ۱۵ دقیقه صبر کنید.',
        'loginWrongCredentials' => 'نام کاربری یا رمز عبور اشتباه است.',
        'loginWelcomeBack' => 'خوش آمدید، ',
        'loginFooter' => 'ViraNaut',
        'loginErrorTitle' => 'خطا',
        'loginShowPassword' => 'نمایش رمز',
        'loginHidePassword' => 'مخفی کردن رمز',
        'layoutBrandName' => 'ویرا · پنل',
        'layoutDefaultAdminName' => 'مدیر',
        'layoutPageTitleSuffix' => 'ویرا',
        'dashboardTitle' => 'داشبورد',
    ];
    foreach ($panelDefaults as $key => $value) {
        if (!isset($lang['panel'][$key]) || $lang['panel'][$key] === '') {
            $lang['panel'][$key] = $value;
        }
    }

    vira_textbotlang_append_idfindeer_hints($lang);
    vira_apply_viranaut_branding($lang);
}

function vira_language_path_is_textjson(?string $path_dir): bool
{
    if ($path_dir === null || $path_dir === '') {
        return false;
    }
    $norm = str_replace('\\', '/', (string) $path_dir);
    return str_ends_with($norm, 'text.json') || str_ends_with($norm, '/text.json');
}

function vira_resolve_user_lang(string $fallback = 'fa'): string
{
    return 'fa';
}

/** زبان UI ربات — برای ادمین‌ها همیشه فارسی (جلوگیری از مخلوط روسی/انگلیسی در پنل). */
function vira_is_bot_admin($userId): bool
{
    $userId = (string) $userId;
    if ($userId === '' || $userId === '0') {
        return false;
    }
    static $adminIdCache = null;
    if ($adminIdCache === null) {
        $ids = select('admin', 'id_admin', null, null, 'FETCH_COLUMN');
        $adminIdCache = is_array($ids) ? array_map('strval', $ids) : [];
    }
    return in_array($userId, $adminIdCache, true);
}

function vira_resolve_bot_ui_lang(string $fallback = 'fa'): string
{
    return 'fa';
}

function vira_support_group_url(): string
{
    if (defined('VIRA_SUPPORT_GROUP') && (string) VIRA_SUPPORT_GROUP !== '') {
        return (string) VIRA_SUPPORT_GROUP;
    }
    return 'https://t.me/ViraNautGroup';
}

function vira_donation_wallet_address(string $payKey, string $default): string
{
    if (function_exists('getPaySettingValue')) {
        $v = trim((string) getPaySettingValue($payKey, ''));
        if ($v !== '' && $v !== '0') {
            return $v;
        }
    }
    return $default;
}

function vira_donation_wallet_rows(): array
{
    return [
        ['icon' => '₿', 'label' => 'Bitcoin', 'key' => 'wallet_btc', 'default' => 'bc1q5xw4nyqc5s993eukq9udrcpfh8ky6pc0mzlfsn'],
        ['icon' => '⟠', 'label' => 'Ethereum · BNB · Polygon', 'key' => 'wallet_eth', 'default' => '0xb60a111813bae216e3b178a5f9e31a95549c000e', 'aliases' => ['wallet_bnb', 'wallet_polygon']],
        ['icon' => '◎', 'label' => 'Solana', 'key' => 'wallet_solana', 'default' => 'GfKRLRTrKx7SYJHd76Rc7tVE6WwJKTNoZutSQitfppR6'],
        ['icon' => '🔺', 'label' => 'Tron · USDT · USDC', 'key' => 'wallet_trx_tron', 'default' => 'TQEW4TP8eGzmJNyzu6kdi4GJdZdNqmTFRL'],
        ['icon' => 'Ð', 'label' => 'Dogecoin', 'key' => 'wallet_doge', 'default' => 'DFAfCU1LHdc7sKFVs9dD7MySA7Wt4EJQtX'],
        ['icon' => '💎', 'label' => 'Toncoin', 'key' => 'wallet_ton', 'default' => 'UQDpQupJJM8bcxk19XmEZtwe-oQ4XmIbxM8SB88z0MXmXYsu'],
    ];
}

function vira_developer_donation_wallets_html(): string
{
    $sep = "➖➖➖➖➖➖➖➖➖➖➖\n";
    $lines = $sep
        . "💎 <b>حمایت از توسعه‌دهنده</b> <i>(اختیاری)</i>\n\n";

    foreach (vira_donation_wallet_rows() as $row) {
        $addr = vira_donation_wallet_address($row['key'], $row['default']);
        $addr = htmlspecialchars($addr, ENT_QUOTES, 'UTF-8');
        $icon = $row['icon'];
        $label = htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8');
        $lines .= "{$icon} <b>{$label}</b>\n<code>{$addr}</code>\n\n";
    }

    $lines .= "🙏 از حمایت شما برای توسعه <b>ویرا</b> سپاسگزاریم";
    return $lines;
}

function vira_admin_panel_login_message(string $botVersion, string $miniAppVersion): string
{
    $botVersion = htmlspecialchars($botVersion, ENT_QUOTES, 'UTF-8');
    $miniAppVersion = htmlspecialchars($miniAppVersion, ENT_QUOTES, 'UTF-8');
    $sep = "➖➖➖➖➖➖➖➖➖➖➖\n";

    return "💎 <b>نسخه ربات:</b> {$botVersion}\n"
        . "📌 <b>نسخه مینی‌اپ:</b> {$miniAppVersion}\n\n"
        . $sep . "\n"
        . "<blockquote>"
        . "🔹 <b>ویرا ViraNaut</b> — ربات VPN رایگان و متن‌باز\n"
        . "🔹 توسعه‌یافته توسط تیم ویرا · نسخه 2.0.1\n"
        . "🔹 هرگونه فروش یا دریافت وجه بابت این ربات تخلف محسوب می‌شود.\n"
        . "🐞 گزارش باگ: دکمه <b>📬 گزارش ربات</b> در پنل ادمین"
        . "</blockquote>\n\n"
        . vira_developer_donation_wallets_html();
}

function vira_bot_report_message_html(): string
{
    $url = htmlspecialchars(vira_support_group_url(), ENT_QUOTES, 'UTF-8');
    return "💬 | گزارش ربات\n\n"
        . "🔹 | اگر در عملکرد ربات با <b>باگ یا مشکلی</b> روبه‌رو شدید، لطفاً مورد را برای بررسی به ما اطلاع دهید.\n"
        . "➖➖➖➖➖➖➖➖➖➖➖\n"
        . "🔹 | در صورتی که با <b>باگ جدی</b> یا رفتار غیرعادی مواجه شدید، سریع‌تر گزارش دهید تا رفع شود.\n"
        . "➖➖➖➖➖➖➖➖➖➖➖\n"
        . "🔹 | اگر پیشنهادی برای <b>افزودن قابلیت جدید</b> دارید یا ایده‌ای برای بهبود عملکرد ربات در نظر دارید، خوشحال می‌شویم بشنویم.\n"
        . "➖➖➖➖➖➖➖➖➖➖➖\n"
        . "🔹 | همچنین اگر نیاز به <b>راهنمایی</b> یا کمک دارید، می‌توانید از طریق دایرکت با تیم پشتیبانی در ارتباط باشید.\n\n"
        . "📩 | برای ارسال گزارش، پیشنهاد یا درخواست راهنمایی، در <b>گروه ویرا</b> پیام بگذارید:\n"
        . "<a href=\"{$url}\" rel=\"nofollow\" target=\"_blank\">ViraNaut Group</a>";
}

function vira_online_status_label($onlineAt, array $textbotlang): string
{
    $idx = $textbotlang['extracted']['index_php'] ?? [];
    if ($onlineAt === 'online') {
        return (string) ($idx['statusOnline'] ?? 'آنلاین');
    }
    if ($onlineAt === 'offline') {
        return (string) ($idx['statusOffline'] ?? 'آفلاین');
    }
    if ($onlineAt !== null && $onlineAt !== '') {
        try {
            $dateTime = new DateTime((string) $onlineAt, new DateTimeZone('UTC'));
            $dateTime->setTimezone(new DateTimeZone('Asia/Tehran'));
            return jdate('Y/m/d H:i:s', $dateTime->getTimestamp());
        } catch (Throwable $e) {
            return (string) $onlineAt;
        }
    }
    return (string) ($idx['statusNotConnected'] ?? 'متصل نشده');
}

/** برچسب وضعیت سرویس پنل — با fallback برای status نامعتبر */
function vira_service_status_label(?string $status, array $textbotlang): string
{
    $unknown = (string) ($textbotlang['users']['stateus']['Unknown'] ?? 'نامشخص');
    if ($status === null || $status === '') {
        return $unknown;
    }
    $map = [
        'active' => $textbotlang['users']['stateus']['active'] ?? 'فعال',
        'limited' => $textbotlang['users']['stateus']['limited'] ?? 'محدود',
        'disabled' => $textbotlang['users']['stateus']['disabled'] ?? 'غیرفعال',
        'deactivev' => $textbotlang['users']['stateus']['disabled'] ?? 'غیرفعال',
        'expired' => $textbotlang['users']['stateus']['expired'] ?? 'منقضی',
        'on_hold' => $textbotlang['users']['stateus']['on_hold'] ?? 'معلق',
        'Unknown' => $unknown,
    ];
    return (string) ($map[$status] ?? $unknown);
}

function vira_xray_state_label($state, array $textbotlang): string
{
    $stateKey = strtolower(trim((string) $state));
    $panels = $textbotlang['extracted']['panels_php'] ?? [];
    $map = [
        'running' => $panels['xrayActive'] ?? '🟢 فعال',
        'run' => $panels['xrayActive'] ?? '🟢 فعال',
        'active' => $panels['xrayActive'] ?? '🟢 فعال',
        'stop' => $panels['xrayStopped'] ?? '🔴 متوقف',
        'stopped' => $panels['xrayStopped'] ?? '🔴 متوقف',
        'inactive' => $panels['xrayStopped'] ?? '🔴 متوقف',
    ];
    if (isset($map[$stateKey])) {
        return (string) $map[$stateKey];
    }
    if ($stateKey === '' || $stateKey === '—' || $stateKey === '-') {
        return '—';
    }
    return (string) $state;
}

/**
 * Read per-agent value from JSON column (maintime, maxtime, mainvolume, …).
 */
function vira_json_agent_scalar($jsonValue, string $agent, $default = 0)
{
    if ($jsonValue === null || $jsonValue === '') {
        return $default;
    }
    if (is_array($jsonValue)) {
        $decoded = $jsonValue;
    } else {
        $decoded = json_decode((string) $jsonValue, true);
    }
    if (!is_array($decoded)) {
        return is_numeric($jsonValue) ? $jsonValue : $default;
    }
    if (array_key_exists($agent, $decoded)) {
        return $decoded[$agent];
    }
    if (array_key_exists('f', $decoded)) {
        return $decoded['f'];
    }
    $first = reset($decoded);
    return $first !== false ? $first : $default;
}

function vira_default_keyboardmain_json(): string
{
    // Compact glass-style main menu (styles apply when inlinebtnmain=oninline)
    return json_encode([
        'keyboard' => [
            [
                ['text' => 'text_sell', 'style' => 'primary'],
                ['text' => 'text_usertest', 'style' => 'success'],
            ],
            [
                ['text' => 'text_Purchased_services', 'style' => 'primary'],
                ['text' => 'accountwallet', 'style' => 'success'],
            ],
            [
                ['text' => 'text_extend'],
                ['text' => 'text_support', 'style' => 'danger'],
            ],
        ],
    ], JSON_UNESCAPED_UNICODE);
}

/** مقادیر پیش‌فرض جدول setting (مطابق table.php) */
function vira_setting_defaults(): array
{
    return [
        'Bot_Status' => 'botstatuson',
        'roll_Status' => 'rolleon',
        'get_number' => 'offAuthenticationphone',
        'limit_usertest_all' => '1',
        'iran_number' => 'offAuthenticationiran',
        'NotUser' => 'offnotuser',
        'Channel_Report' => '',
        'affiliatesstatus' => 'offaffiliates',
        'affiliatespercentage' => '0',
        'removedayc' => '0',
        'showcard' => '1',
        'statuscategory' => 'offcategory',
        'numbercount' => '0',
        'statusnewuser' => 'onnewuser',
        'statusagentrequest' => 'onrequestagent',
        'volumewarn' => '2',
        'inlinebtnmain' => 'offinline',
        'verifystart' => 'offverify',
        'id_support' => '',
        'statussupportpv' => 'offpvsupport',
        'statusnamecustom' => 'offnamecustom',
        'statuscategorygenral' => 'offcategorys',
        'agentreqprice' => '0',
        'cronvolumere' => '5',
        'bulkbuy' => 'onbulk',
        'on_hold_day' => '4',
        'verifybucodeuser' => 'offverify',
        'scorestatus' => '0',
        'Lottery_prize' => '[]',
        'wheelـluck' => '0',
        'wheelـluck_price' => '0',
        'iplogin' => '0',
        'daywarn' => '2',
        'categoryhelp' => '0',
        'linkappstatus' => '0',
        'languageen' => '0',
        'languageru' => '0',
        'wheelagent' => '1',
        'Lotteryagent' => '1',
        'statusfirstwheel' => '0',
        'statuslimitchangeloc' => '0',
        'limitnumber' => '{}',
        'Debtsettlement' => '1',
        'Dice' => '0',
        'statusnoteforf' => '1',
        'statuscopycart' => '0',
        'timeauto_not_verify' => '4',
        'status_keyboard_config' => '1',
        'unknowncommand_reply' => '1',
        'cron_status' => json_encode([
            'day' => true,
            'volume' => true,
            'remove' => false,
            'remove_volume' => false,
            'test' => false,
            'on_hold' => false,
            'uptime_node' => false,
            'uptime_panel' => false,
        ], JSON_UNESCAPED_UNICODE),
        'keyboardmain' => vira_default_keyboardmain_json(),
    ];
}

/** برچسب امن از map وضعیت — جلوگیری از null در دکمهٔ تلگرام */
function vira_setting_pick(array $map, $key, $fallbackKey = null): string
{
    if (is_bool($key) && array_key_exists($key, $map)) {
        return (string) $map[$key];
    }
    $key = (string) ($key ?? '');
    if ($key !== '' && array_key_exists($key, $map)) {
        return (string) $map[$key];
    }
    if ($fallbackKey !== null && array_key_exists($fallbackKey, $map)) {
        return (string) $map[$fallbackKey];
    }
    foreach (['0', 'offverify', 'offnotuser', 'botstatusoff', 'offinline', false] as $fb) {
        if (array_key_exists($fb, $map)) {
            return (string) $map[$fb];
        }
    }
    $first = reset($map);
    return $first !== false ? (string) $first : '—';
}

function vira_setting_cron_status(array $setting): array
{
    $defaults = [
        'day' => true,
        'volume' => true,
        'remove' => false,
        'remove_volume' => false,
        'test' => false,
        'on_hold' => false,
        'uptime_node' => false,
        'uptime_panel' => false,
    ];
    $raw = $setting['cron_status'] ?? '';
    if (is_array($raw)) {
        return array_merge($defaults, $raw);
    }
    $dec = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
    return is_array($dec) ? array_merge($defaults, $dec) : $defaults;
}

function vira_setting_merge_defaults(array $row): array
{
    $merged = array_merge(vira_setting_defaults(), $row);
    foreach (vira_setting_defaults() as $key => $default) {
        if (!array_key_exists($key, $merged) || $merged[$key] === null || $merged[$key] === '') {
            $merged[$key] = $default;
        }
    }
    return $merged;
}

function vira_setting_column_types(): array
{
    return [
        'Lottery_prize' => 'TEXT',
        'keyboardmain' => 'TEXT',
        'cron_status' => 'TEXT',
        'limitnumber' => 'VARCHAR(200)',
    ];
}

/** ستون‌های setting که در DB قدیمی نیستند را قبل از seed اضافه می‌کند */
function vira_ensure_setting_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    global $pdo;
    if (!isset($pdo)) {
        return;
    }
    try {
        $chk = $pdo->query("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'setting' LIMIT 1");
        if (!$chk || !$chk->fetchColumn()) {
            return;
        }
    } catch (Throwable $e) {
        error_log('[viranaut] setting schema check: ' . $e->getMessage());
        return;
    }
    $types = vira_setting_column_types();
    foreach (vira_setting_defaults() as $col => $default) {
        $datatype = $types[$col] ?? 'VARCHAR(600)';
        $seedVal = $default;
        if (is_array($seedVal)) {
            $seedVal = json_encode($seedVal, JSON_UNESCAPED_UNICODE);
        }
        vira_setting_add_column_if_missing('setting', (string) $col, $seedVal, $datatype);
    }
}

function vira_setting_add_column_if_missing(string $tableName, string $fieldName, $defaultValue = null, string $datatype = 'VARCHAR(600)'): void
{
    global $pdo;
    if (!isset($pdo)) {
        return;
    }
    try {
        $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([$db, $tableName, $fieldName]);
        if ((int) $stmt->fetchColumn() !== 0) {
            return;
        }
        $pdo->exec("ALTER TABLE `$tableName` ADD `$fieldName` $datatype NULL");
        if ($defaultValue !== null) {
            $upd = $pdo->prepare("UPDATE `$tableName` SET `$fieldName` = ?");
            $upd->execute([(string) $defaultValue]);
        }
    } catch (Throwable $e) {
        error_log("[viranaut] setting add column $fieldName: " . $e->getMessage());
    }
}

function vira_setting_try_seed(): bool
{
    global $pdo;
    static $attempted = false;
    if ($attempted) {
        return false;
    }
    $attempted = true;
    vira_ensure_setting_schema();
    try {
        $count = (int) $pdo->query('SELECT COUNT(*) FROM setting')->fetchColumn();
        if ($count > 0) {
            return false;
        }
        $defaults = vira_setting_defaults();
        $cols = array_keys($defaults);
        $colList = implode(',', array_map(static fn($c) => "`$c`", $cols));
        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $stmt = $pdo->prepare("INSERT INTO setting ($colList) VALUES ($placeholders)");
        $stmt->execute(array_values($defaults));
        clearSelectCache('setting');
        error_log('[viranaut] setting row seeded automatically');
        return true;
    } catch (Throwable $e) {
        error_log('[viranaut] setting auto-seed failed: ' . $e->getMessage());
        return false;
    }
}

/** setting خالی یا keyboardmain خراب → دیگر 500 ندهد؛ در صورت امکان در DB اصلاح می‌کند */
function vira_ensure_setting_ready(): array
{
    vira_ensure_setting_schema();
    $defaults = vira_setting_defaults();
    $row = select('setting', '*', null, null, 'select', ['cache' => false]);
    if (!is_array($row)) {
        if (vira_setting_try_seed()) {
            $row = select('setting', '*', null, null, 'select', ['cache' => false]);
        }
        if (!is_array($row)) {
            static $warned = false;
            if (!$warned) {
                error_log('[viranaut] setting table has no row — using defaults (run: cd BOT_DIR && php table.php)');
                $warned = true;
            }
            return $defaults;
        }
    }
    $row = vira_setting_merge_defaults($row);
    $km = trim((string) ($row['keyboardmain'] ?? ''));
    if ($km === '' || !is_array(json_decode($km, true))) {
        $km = vira_default_keyboardmain_json();
        update('setting', 'keyboardmain', $km, null, null);
        $row['keyboardmain'] = $km;
    }
    return $row;
}

function vira_handle_bot_start_command($from_id, array $datatextbot, $keyboard): void
{
    vira_send_datatextbot_message($from_id, 'text_start', $datatextbot['text_start'], $keyboard, 'html');
    update('user', 'Processing_value', '0', 'id', $from_id);
    update('user', 'Processing_value_one', '0', 'id', $from_id);
    update('user', 'Processing_value_tow', '0', 'id', $from_id);
    update('user', 'Processing_value_four', '0', 'id', $from_id);
    step('home', $from_id, ['skip_card_cancel' => true]);
}

function vira_admin_allows_user_lookup(string $step): bool
{
    $step = trim($step);
    return in_array($step, ['', 'home', 'none'], true);
}

function vira_admin_user_flow_step(string $step): bool
{
    static $steps = [
        'get_number', 'getusernameinfo', 'createusertest', 'getuseragnetservice',
        'gettimecustomvolomforextend', 'getvolumecustomuserforextend', 'getcodesellDiscountextend',
        'getvolumeextra', 'getdesdisorder', 'gettimeextra', 'getdisdeleteconfig', 'getidfortransfer',
        'gettextticket', 'getextsupport', 'getextuserfors', 'statusnamecustom', 'gettimecustomvol',
        'getvolumecustomusername', 'endstepuser', 'endstepusers', 'getvolumecustomuser', 'payment',
        'getcodesellDiscount', 'getcountconfig', 'gettimecustomvolom', 'getvolumecustomusernameom',
        'endstepuserom', 'endstepusersom', 'getvolumecustomuserom', 'payments', 'getprice',
        'get_step_payment', 'getresidcurrency', 'cart_to_cart_user', 'get_code_user', 'getvolumeextras',
        'getmessageAsuser', 'selectusernamecustom', 'confirmchannel', 'card_invoice_pending',
    ];
    return in_array(trim($step), $steps, true);
}

function vira_admin_run_exclusive(string $step): bool
{
    $step = trim($step);
    if (vira_admin_allows_user_lookup($step) || vira_admin_user_flow_step($step)) {
        return false;
    }
    return true;
}

function vira_languagechange_from_json(?string $path_dir = null): array
{
    static $cachePath = '';
    static $cacheMtime = 0;
    static $cacheLang = [];

    if ($path_dir === null || $path_dir === '') {
        $path_dir = __DIR__ . '/text.json';
    } elseif (!str_contains(str_replace('\\', '/', $path_dir), '/')) {
        $path_dir = __DIR__ . '/' . ltrim($path_dir, '/');
    }
    $mtime = (int) (@filemtime($path_dir) ?: 0);
    if ($cachePath === $path_dir && $cacheMtime === $mtime && $cacheLang !== []) {
        return $cacheLang;
    }
    $raw = @file_get_contents($path_dir);
    $all = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($all)) {
        error_log('languagechange: invalid or missing text file: ' . (string) $path_dir);
        $all = ['fa' => []];
    }
    $lang = $all['fa'] ?? [];
    if (!is_array($lang)) {
        $lang = [];
    }
    vira_apply_textbotlang_compat($lang);
    $cachePath = $path_dir;
    $cacheMtime = $mtime;
    $cacheLang = $lang;
    return $lang;
}

function languagechange($path_dir = null, string $lang_override = '')
{
    if (vira_language_path_is_textjson($path_dir)) {
        return vira_languagechange_from_json($path_dir);
    }
    $langFile = __DIR__ . '/lang/fa.php';
    if (is_file($langFile)) {
        $lang = require $langFile;
        if (is_array($lang)) {
            vira_apply_textbotlang_compat($lang);
            return $lang;
        }
    }
    return vira_languagechange_from_json(__DIR__ . '/text.json');
}

function vira_card_autoconfirm_mode(): string
{
    $mode = getPaySettingValue('card_autoconfirm_mode', 'both');
    return in_array($mode, ['receipt_only', 'auto_only', 'both'], true) ? $mode : 'both';
}

/**
 * TRON/TRC20 offline payment receipt (legacy Pro format).
 */
function vira_tron_offline_receipt_message(string $orderId, string $wallet, $trxAmount, string $tomanFormatted): string
{
    $network = getPaySettingValue('offlinearze_tron_network', 'TRC20');
    $coin = getPaySettingValue('offlinearze_tron_coin', 'TRON');
    $template = getPaySettingValue('offlinearze_tron_receipt_template', '');
    if ($template !== '' && $template !== '2') {
        return str_replace(
            ['{order}', '{wallet}', '{amount}', '{toman}', '{network}', '{coin}'],
            [$orderId, $wallet, (string) $trxAmount, $tomanFormatted, $network, $coin],
            $template
        );
    }
    return "✅ تراکنش شما ایجاد شد

🛒 کد پیگیری: <code>{$orderId}</code>
🌐 شبکه: {$network}
💎 ارز: {$coin}
💳 آدرس ولت: <code>{$wallet}</code>
💲 مبلغ تراکنش: {$trxAmount} {$coin}

📌 مبلغ {$tomanFormatted} تومان را واریز کنید؛ پس از واریز دکمه زیر را بزنید و رسید را ارسال نمایید.

💢 لطفاً به این نکات قبل از پرداخت توجه کنید 👇

🔸 در صورت اشتباه وارد کردن آدرس کیف پول، تراکنش تأیید نمی‌شود و بازگشت وجه امکان‌پذیر نیست
🔹 مبلغ ارسالی نباید کمتر یا بیشتر از مبلغ اعلام‌شده باشد
🔹 هر تراکنش یک ساعت معتبر است؛ پس از انقضا به هیچ عنوان واریز نکنید

✅ در صورت مشکل با پشتیبانی در ارتباط باشید";
}

function vira_site_admin_log_request(string $userId, string $message, ?string $photoFileId = null): void
{
    global $pdo;
    if (!isset($pdo)) {
        return;
    }
    try {
        $stmt = $pdo->prepare('INSERT INTO site_admin_requests (id_user, message, photo_file_id, status) VALUES (?, ?, ?, ?)');
        $stmt->execute([$userId, $message, $photoFileId, 'pending']);
    } catch (Throwable $e) {
        error_log('vira_site_admin_log_request: ' . $e->getMessage());
    }
}

/**
 * When agent hits maxbuy cap, redirect to payment instead of hard stop (legacy 6.7).
 */
function vira_maxbuyagent_payment_redirect($from_id, array $user, $price_deduct, $step_payment, string $processing_tow = 'maxbuy_topup'): bool
{
    global $textbotlang;
    if (intval($user['maxbuyagent']) == 0 || ($user['agent'] ?? '') !== 'n2') {
        return false;
    }
    $after = intval($user['Balance']) - intval($price_deduct);
    if ($after >= intval('-' . $user['maxbuyagent'])) {
        return false;
    }
    $need = abs($after + intval($user['maxbuyagent']));
    update('user', 'Processing_value', $need, 'id', $from_id);
    update('user', 'Processing_value_tow', $processing_tow, 'id', $from_id);
    $msg = ($textbotlang['users']['Balance']['maxpurchasereached'] ?? '❌ به سقف خرید رسیدید.')
        . "\n\n" . ($textbotlang['users']['sell']['None-credit'] ?? '💰 برای ادامه موجودی خود را افزایش دهید.');
    sendmessage($from_id, $msg, $step_payment, 'HTML');
    step('get_step_payment', $from_id);
    return true;
}
function generateAuthStr($length = 10)
{
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    return substr(str_shuffle(str_repeat($characters, ceil($length / strlen($characters)))), 0, $length);
}
function vira_filter_subscription_links($links)
{
    if (!is_array($links)) {
        return array();
    }
    return array_values(array_filter($links, static function ($link) {
        return is_string($link) && trim($link) !== '';
    }));
}

function createqrcode($contents)
{
    if (!is_string($contents) || trim($contents) === '') {
        return null;
    }
    $builder = new Builder(
        writer: new PngWriter(),
        writerOptions: [],
        data: $contents,
        encoding: new Encoding('UTF-8'),
        errorCorrectionLevel: ErrorCorrectionLevel::High,
        size: 500,
        margin: 10,
    );

    $result = $builder->build();
    return $result;
}
function sanitize_recursive(array $data): array
{
    $sanitized_data = [];
    foreach ($data as $key => $value) {
        $sanitized_key = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
        if (is_array($value)) {
            $sanitized_data[$sanitized_key] = sanitize_recursive($value);
        } elseif (is_string($value)) {
            $sanitized_data[$sanitized_key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        } elseif (is_int($value)) {
            $sanitized_data[$sanitized_key] = filter_var($value, FILTER_SANITIZE_NUMBER_INT);
        } elseif (is_float($value)) {
            $sanitized_data[$sanitized_key] = filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        } elseif (is_bool($value) || is_null($value)) {
            $sanitized_data[$sanitized_key] = $value;
        } else {
            $sanitized_data[$sanitized_key] = $value;
        }
    }
    return $sanitized_data;
}

function check_active_btn($keyboard, $text_var)
{
    $trace_keyboard = json_decode($keyboard, true)['keyboard'];
    $status = false;
    foreach ($trace_keyboard as $key => $callback_set) {
        foreach ($callback_set as $keyboard_key => $keyboard) {
            if ($keyboard['text'] == $text_var) {
                $status = true;
                break;
            }
        }
    }
    return $status;
}
function deleteFolder($folderPath)
{
    if (!is_dir($folderPath))
        return false;

    $files = array_diff(scandir($folderPath), ['.', '..']);

    foreach ($files as $file) {
        $filePath = $folderPath . DIRECTORY_SEPARATOR . $file;
        if (is_dir($filePath)) {
            deleteFolder($filePath);
        } else {
            unlink($filePath);
        }
    }

    return rmdir($folderPath);
}
function isBase64($string)
{
    if (base64_encode(base64_decode($string, true)) === $string) {
        return true;
    }
    return false;
}
function sendMessageService($panel_info, $config, $sub_link, $username_service, $reply_markup, $caption, $invoice_id, $user_id = null, $image = 'images.jpg')
{
    global $setting, $from_id;
    if (!check_active_btn($setting['keyboardmain'], "text_help"))
        $reply_markup = null;
    $user_id = $user_id == null ? $from_id : $user_id;
    $STATUS_SEND_MESSAGE_PHOTO = $panel_info['config'] == "onconfig" && count($config) != 1 ? false : true;
    $out_put_qrcode = "";
    if ($panel_info['type'] == "Manualsale" || $panel_info['type'] == "ibsng" || $panel_info['type'] == "mikrotik") {
    }
    if ($panel_info['sublink'] == "onsublink" && $panel_info['config']) {
        $out_put_qrcode = $sub_link;
    } elseif ($panel_info['sublink'] == "onsublink") {
        $out_put_qrcode = $sub_link;
    } elseif ($panel_info['config'] == "onconfig") {
        $out_put_qrcode = $config[0];
    }
    if ($STATUS_SEND_MESSAGE_PHOTO) {
        if ($panel_info['type'] == "WGDashboard") {
            $urlimage = "{$panel_info['inboundid']}_{$invoice_id}.conf";
            file_put_contents($urlimage, $sub_link);
            telegram('senddocument', [
                'chat_id' => $user_id,
                'document' => new CURLFile($urlimage),
                'reply_markup' => $reply_markup,
                'caption' => $caption,
                'parse_mode' => "HTML",
            ]);
            unlink($urlimage);
        } else {
            $urlimage = "$user_id$invoice_id.png";
            $qrCode = createqrcode($out_put_qrcode);
            file_put_contents($urlimage, $qrCode->getString());
            addBackgroundImage($urlimage, $qrCode, $image);
            telegram('sendphoto', [
                'chat_id' => $user_id,
                'photo' => new CURLFile($urlimage),
                'reply_markup' => $reply_markup,
                'caption' => $caption,
                'parse_mode' => "HTML",
            ]);
            unlink($urlimage);
        }
    } else {
        sendmessage($user_id, $caption, $reply_markup, 'HTML');
    }
    if ($panel_info['config'] == "onconfig" && $setting['status_keyboard_config'] == "1") {
        if (is_array($config)) {
            sendmessage($user_id, "📌 جهت دریافت کانفیگ روی دکمه دریافت کانفیگ کلیک کنید", keyboard_config($config, $invoice_id, false), 'HTML');
        }
    }
}
function isValidInvitationCode($setting, $fromId, $verfy_status)
{

    if ($setting['verifybucodeuser'] == "onverify" && $verfy_status != 1) {
        sendmessage($fromId, "حساب کاربری شما با موفقیت احرازهویت گردید", null, 'html');
        update("user", "verify", "1", "id", $fromId);
        update("user", "cardpayment", "1", "id", $fromId);
    }
}
function createPayZarinpal($price, $order_id)
{
    global $domainhosts;
    $marchent_zarinpal = select("PaySetting", "ValuePay", "NamePay", "merchant_zarinpal", "select")['ValuePay'];
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.zarinpal.com/pg/v4/payment/request.json',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'Accept: application/json'
        ),
    ));
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode([
        "merchant_id" => $marchent_zarinpal,
        "currency" => "IRT",
        "amount" => $price,
        "callback_url" => "https://$domainhosts/payment/zarinpal.php",
        "description" => $order_id,
        "metadata" => array(
            "order_id" => $order_id
        )
    ]));
    $response = curl_exec($curl);
    curl_close($curl);
    return json_decode($response, true);
}
function createPayaqayepardakht($price, $order_id)
{
    global $domainhosts;
    $merchant_aqayepardakht = select("PaySetting", "ValuePay", "NamePay", "merchant_id_aqayepardakht", "select")['ValuePay'];
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://panel.aqayepardakht.ir/api/v2/create',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'Accept: application/json'
        ),
    ));
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode([
        'pin' => $merchant_aqayepardakht,
        'amount' => $price,
        'callback' => $domainhosts . "/payment/aqayepardakht.php",
        'invoice_id' => $order_id,
    ]));
    $response = curl_exec($curl);
    curl_close($curl);
    return json_decode($response, true);
}
function parseConfigs($input)
{
    $lines = explode("\n", $input);
    $configs = [];

    $currentName = null;
    $currentData = [];

    foreach ($lines as $line) {
        $line = trim($line);

        if (strpos($line, '#') === 0) {
            if ($currentName && $currentData) {
                $configs[] = [
                    'name' => $currentName,
                    'config' => implode("\n", $currentData)
                ];
            }
            $currentName = trim(substr($line, 1));
            $currentData = [];
        } else {
            if ($line !== '') {
                $currentData[] = $line;
            }
        }
    }
    if ($currentName && $currentData) {
        $configs[] = [
            'name' => $currentName,
            'config' => implode("\n", $currentData)
        ];
    }

    return $configs;
}