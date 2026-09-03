<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/request.php';
ini_set('error_log', 'error_log');

/** تایم‌اوت اتصال پنل ثنایی — برای پنل‌های دور و پاسخ کند پروکسی. */
define('VIRA_XUI_TIMEOUT_MS', 120000);
define('VIRA_XUI_CONNECT_TIMEOUT_MS', 45000);
define('VIRA_XUI_CSRF_TIMEOUT_MS', 90000);
define('VIRA_XUI_QUICK_TIMEOUT_MS', 30000);
define('VIRA_XUI_QUICK_CONNECT_MS', 15000);
define('VIRA_XUI_QUICK_CSRF_MS', 25000);
define('VIRA_XUI_API_RETRY_MAX', 2);
define('VIRA_XUI_API_RETRY_WRITE_MAX', 2);
define('VIRA_XUI_API_RETRY_DELAY_MS', 1500);
define('VIRA_XUI_PANEL_BUDGET_SEC', 90);

function vira_xui_panel_budget_start($seconds = VIRA_XUI_PANEL_BUDGET_SEC)
{
    $GLOBALS['vira_xui_panel_budget_end'] = microtime(true) + max(10, (int) $seconds);
}

function vira_xui_panel_budget_exceeded()
{
    return !empty($GLOBALS['vira_xui_panel_budget_end'])
        && microtime(true) >= $GLOBALS['vira_xui_panel_budget_end'];
}

function vira_xui_panel_budget_left_ms()
{
    if (empty($GLOBALS['vira_xui_panel_budget_end'])) {
        return VIRA_XUI_TIMEOUT_MS;
    }
    $left = (int) (($GLOBALS['vira_xui_panel_budget_end'] - microtime(true)) * 1000);
    return max(8000, min(VIRA_XUI_TIMEOUT_MS, $left));
}

function vira_xui_panel_log($message)
{
    error_log('[vira-xui] ' . $message);
}

function vira_xui_curl_timeout_ms($quick = false)
{
    if ($quick) {
        return VIRA_XUI_QUICK_TIMEOUT_MS;
    }
    global $request_exec_timeout;
    $base = VIRA_XUI_TIMEOUT_MS;
    $fromConfig = ($request_exec_timeout !== null && (int) $request_exec_timeout > 0)
        ? (int) $request_exec_timeout
        : 0;
    return max($base, $fromConfig);
}

function vira_xui_connect_timeout_ms($quick = false)
{
    return $quick ? VIRA_XUI_QUICK_CONNECT_MS : VIRA_XUI_CONNECT_TIMEOUT_MS;
}

function vira_xui_json_is_success($decoded)
{
    if (!is_array($decoded) || !array_key_exists('success', $decoded)) {
        return false;
    }
    $success = $decoded['success'];
    return $success === true || $success === 1 || $success === '1' || $success === 'true';
}

function vira_xui_format_panel_error($response)
{
    if (!is_array($response)) {
        return 'خطای نامشخص پنل';
    }
    if (!empty($response['error'])) {
        $err = (string) $response['error'];
        if (stripos($err, 'Failed to connect') !== false || stripos($err, 'Could not resolve host') !== false) {
            if (preg_match('/connect to ([^\s]+) port (\d+)/i', $err, $m)) {
                return '❌ سرور ربات به <b>آدرس API پنل</b> وصل نشد: ' . $m[1] . ':' . $m[2]
                    . "\n\nاین پورت «ورود/API پنل» است (نه پورت لینک ساب). پنل روشن، فایروال و Allow IP سرور ربات را چک کنید.";
            }
            return '❌ سرور ربات به آدرس پنل دسترسی ندارد (Timeout اتصال). فایروال، DNS یا پورت را چک کنید.';
        }
        if (stripos($err, 'timed out') !== false || stripos($err, 'timeout') !== false) {
            return '❌ پنل پاسخ نداد (Timeout). پنل کند است یا مسیر شبکه مشکل دارد.';
        }
        return '❌ ' . mb_substr($err, 0, 400);
    }
    return vira_xui_format_http_error($response['status'] ?? 0, $response['body'] ?? '');
}

function vira_xui_format_http_error($status, $body = '')
{
    $code = (int) $status;
    if ($code === 504) {
        return '504 — پنل دیر پاسخ داد (Gateway Timeout). ربات چند بار تلاش کرد؛ اگر ادامه داشت timeout پروکسی/Nginx جلوی پنل را بالا ببرید (مثلاً 180s).';
    }
    if ($code === 502 || $code === 503) {
        return $code . ' — پنل موقتاً در دسترس نیست؛ چند دقیقه بعد دوباره تست کنید.';
    }
    if ($code === 408) {
        return '408 — زمان درخواست تمام شد؛ اتصال پنل را بررسی کنید.';
    }
    $snippet = '';
    if (is_string($body) && $body !== '') {
        $snippet = function_exists('mb_substr') ? mb_substr(preg_replace('/\s+/', ' ', $body), 0, 120) : substr($body, 0, 120);
    }
    return $code > 0 ? ('HTTP ' . $code . ($snippet !== '' ? (': ' . $snippet) : '')) : 'خطای اتصال به پنل';
}

function vira_xui_http_should_retry(array $response)
{
    if (!empty($response['error'])) {
        $err = strtolower((string) $response['error']);
        if (strpos($err, 'timed out') !== false || strpos($err, 'timeout') !== false) {
            return true;
        }
        if (strpos($err, 'couldn\'t connect') !== false || strpos($err, 'connection') !== false) {
            return true;
        }
    }
    $status = (int) ($response['status'] ?? 0);
    return in_array($status, array(408, 429, 500, 502, 503, 504), true);
}

function vira_xui_new_request($url, $quick = false)
{
    $req = new CurlRequest($url);
    $timeout = !empty($GLOBALS['vira_xui_panel_budget_end'])
        ? vira_xui_panel_budget_left_ms()
        : vira_xui_curl_timeout_ms($quick);
    $req->setTimeout($timeout);
    $req->setConnectTimeout(min(vira_xui_connect_timeout_ms($quick), (int) max(8000, $timeout / 2)));
    return $req;
}

/** Cookie jar must be absolute — PHP CWD is often not the bot directory under Apache/cron. */
function vira_xui_cookie_jar_path($code_panel)
{
    return __DIR__ . DIRECTORY_SEPARATOR . '.xui_cookie_' . md5((string) $code_panel) . '.txt';
}

/**
 * یادآوری در داشبورد: در ۳x-ui پورت پنل (API) و پورت ساب معمولاً جدا هستند — طبیعی است.
 */
function vira_xui_panel_sub_port_note(array $panel)
{
    $url = (string) ($panel['url_panel'] ?? '');
    $sub = (string) ($panel['linksubx'] ?? '');
    if ($url === '' || $sub === '') {
        return '';
    }
    $pu = parse_url($url);
    $ps = parse_url($sub);
    $portUrl = isset($pu['port']) ? (int) $pu['port'] : (($pu['scheme'] ?? '') === 'https' ? 443 : 80);
    $portSub = isset($ps['port']) ? (int) $ps['port'] : (($ps['scheme'] ?? '') === 'https' ? 443 : 80);
    if ($portUrl === $portSub) {
        return '';
    }
    return "\nℹ️ پورت API پنل: <code>{$portUrl}</code> · پورت ساب: <code>{$portSub}</code> (در ثنایی جدا بودن طبیعی است).";
}

/** Panel root without trailing slash (…/URcgRCQhmQky5qmTOF). */
function vira_xui_public_base($url_panel)
{
    if (!empty($GLOBALS['vira_xui_url_override'])) {
        $url_panel = $GLOBALS['vira_xui_url_override'];
    }
    if (function_exists('vira_normalize_xui_panel_url')) {
        $url_panel = vira_normalize_xui_panel_url($url_panel);
    } elseif (function_exists('vira_normalize_panel_url')) {
        $url_panel = vira_normalize_panel_url($url_panel);
    }
    return rtrim(trim($url_panel), '/');
}

/** Origin/Referer host — بدون :443/:80 پیش‌فرض (سازگار با WAF و مرورگر). */
function vira_xui_http_origin($url_panel)
{
    $base = vira_xui_public_base($url_panel);
    $p = parse_url($base);
    if (empty($p['scheme']) || empty($p['host'])) {
        return '';
    }
    $origin = $p['scheme'] . '://' . $p['host'];
    $port = isset($p['port']) ? (int) $p['port'] : null;
    if ($port !== null) {
        $def = ($p['scheme'] === 'https') ? 443 : 80;
        if ($port !== $def) {
            $origin .= ':' . $port;
        }
    }
    return $origin;
}

/** Optional Bearer token — Settings → Security → API Token (بدون نیاز به کوکی/CSRF). */
function vira_xui_bearer_token(array $panel)
{
    if (!isset($panel['xui_api_token']) || $panel['xui_api_token'] === null || $panel['xui_api_token'] === '') {
        return '';
    }
    return trim((string) $panel['xui_api_token']);
}

function vira_xui_verify_bearer($url_panel, $token, $quick = false)
{
    $b = vira_xui_public_base($url_panel);
    $url = $b . '/panel/api/inbounds/list';
    $req = vira_xui_new_request($url, $quick);
    $req->setBearerToken($token);
    $req->setHeaders(vira_xui_spa_headers($url_panel));
    $res = $req->get();
    if (!empty($res['error'])) {
        return array('success' => false, 'msg' => $res['error']);
    }
    $status = isset($res['status']) ? (int) $res['status'] : 0;
    $body = isset($res['body']) ? $res['body'] : '';
    $dec = is_string($body) ? json_decode($body, true) : null;
    if ($status === 200 && is_array($dec) && !empty($dec['success'])) {
        return array('success' => true);
    }
    if (is_array($dec) && isset($dec['msg'])) {
        return array('success' => false, 'msg' => $dec['msg']);
    }
    $snippet = '';
    if ($body !== '' && is_string($body)) {
        $snippet = function_exists('mb_substr') ? mb_substr($body, 0, 200) : substr($body, 0, 200);
        $snippet = preg_replace('/\s+/', ' ', $snippet);
    }
    return array(
        'success' => false,
        'msg' => 'HTTP ' . $status . ($snippet !== '' ? (': ' . $snippet) : ' (بدنه خالی یا غیر JSON)'),
    );
}

/**
 * احراز هویت درخواست: یا Bearer (اگر توکن ذخیره شده)، یا کوکی + CSRF.
 */
function vira_xui_auth_request(CurlRequest $req, array $panel, $cookiePath)
{
    $t = vira_xui_bearer_token($panel);
    if ($t !== '') {
        $req->setBearerToken($t);
        return;
    }
    $req->setCookie($cookiePath);
    vira_xui_apply_session($req);
}

/**
 * Headers many reverse-proxies / WAFs expect (same as browser SPA on 3x-ui).
 * Without Referer+Origin, POST /login often returns 403 with empty body.
 */
function vira_xui_spa_headers($url_panel, $extraHeaders = array())
{
    $base = vira_xui_public_base($url_panel);
    $root = $base . '/';
    $origin = vira_xui_http_origin($url_panel);
    $h = array(
        'Accept: application/json, text/plain, */*',
        'Referer: ' . $root,
        'Origin: ' . $origin,
    );
    foreach ($extraHeaders as $line) {
        $h[] = $line;
    }
    return $h;
}

/**
 * 3x-ui v3+ expects POST /login with JSON body (see panel api-docs).
 * Older forks may still accept form-urlencoded — we fall back on failure.
 */
function panel_login_cookie($code_panel, $quick = false)
{
    $panel = select("marzban_panel", "*", "code_panel", $code_panel, "select");
    $base = vira_xui_public_base($panel['url_panel']);
    $loginCandidates = array($base . '/login', $base . '/login/');
    $timeoutMs = $quick ? VIRA_XUI_QUICK_TIMEOUT_MS : VIRA_XUI_TIMEOUT_MS;
    $connectMs = $quick ? VIRA_XUI_QUICK_CONNECT_MS : VIRA_XUI_CONNECT_TIMEOUT_MS;
    $cookiePath = vira_xui_cookie_jar_path($code_panel);
    @unlink($cookiePath);
    @touch($cookiePath);

    $payload = json_encode(array(
        'username' => $panel['username_panel'],
        'password' => $panel['password_panel'],
    ));

    $GLOBALS['vira_xui_last_login_meta'] = null;

    $chromeUa = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

    $do_curl = function ($postFields, $contentType, $loginUrl) use ($cookiePath, $panel, $chromeUa, $timeoutMs, $connectMs) {
        $headers = vira_xui_spa_headers($panel['url_panel'], array('Content-Type: ' . $contentType));
        $postRedir = 7;
        if (defined('CURL_REDIR_POST_ALL')) {
            $postRedir = CURL_REDIR_POST_ALL;
        }
        $ipResolve = 1;
        if (defined('CURL_IPRESOLVE_V4')) {
            $ipResolve = CURL_IPRESOLVE_V4;
        }
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $loginUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT_MS => $timeoutMs,
            CURLOPT_CONNECTTIMEOUT_MS => $connectMs,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_POSTREDIR => $postRedir,
            CURLOPT_IPRESOLVE => $ipResolve,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_COOKIEJAR => $cookiePath,
            CURLOPT_COOKIEFILE => $cookiePath,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_USERAGENT => $chromeUa,
        ));
        $response = curl_exec($curl);
        $err = curl_error($curl);
        $GLOBALS['vira_xui_last_login_meta'] = array(
            'http' => (int) curl_getinfo($curl, CURLINFO_HTTP_CODE),
            'effective_url' => (string) curl_getinfo($curl, CURLINFO_EFFECTIVE_URL),
            'content_type' => (string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE),
            'bytes' => is_string($response) ? strlen($response) : 0,
            'curl_err' => $err,
        );
        curl_close($curl);
        if ($err) {
            return json_encode(array('success' => false, 'msg' => $err));
        }
        return $response;
    };

    $try_login = function ($body, $ctype, $candidates) use ($do_curl) {
        $last = null;
        foreach ($candidates as $loginUrl) {
            $last = $do_curl($body, $ctype, $loginUrl);
            $d = is_string($last) ? json_decode($last, true) : null;
            if (is_array($d) && !empty($d['success'])) {
                return $last;
            }
            $m = $GLOBALS['vira_xui_last_login_meta'] ?? array();
            if (!empty($m['http']) && (int) $m['http'] !== 403 && (int) $m['http'] !== 404) {
                return $last;
            }
        }
        return $last;
    };

    $loginTry = $quick ? array($loginCandidates[0]) : $loginCandidates;

    $response = $try_login($payload, 'application/json', $loginTry);
    $decoded = is_string($response) ? json_decode($response, true) : null;
    $jsonOk = is_array($decoded) && !empty($decoded['success']);

    if (!$jsonOk && !$quick) {
        $form = 'username=' . rawurlencode($panel['username_panel'])
            . '&password=' . rawurlencode($panel['password_panel']);
        $response = $try_login($form, 'application/x-www-form-urlencoded', $loginCandidates);
    }

    return $response;
}

/** GET /csrf-token — required for cookie-based POST on 3x-ui v3. */
function fetch_xui_csrf_token($url_panel, $cookiePath, $quick = false)
{
    $base = vira_xui_public_base($url_panel);
    $candidates = array($base . '/csrf-token', $base . '/csrf-token/');
    $csrfTimeout = $quick ? VIRA_XUI_QUICK_CSRF_MS : VIRA_XUI_CSRF_TIMEOUT_MS;
    $ch = curl_init();
    $chromeUa = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';
    $body = '';
    foreach ($candidates as $url) {
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET => true,
            CURLOPT_COOKIEFILE => $cookiePath,
            CURLOPT_COOKIEJAR => $cookiePath,
            CURLOPT_TIMEOUT_MS => $csrfTimeout,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HTTPHEADER => vira_xui_spa_headers($url_panel),
            CURLOPT_USERAGENT => $chromeUa,
        ));
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($code !== 403 && $code !== 404) {
            break;
        }
    }
    curl_close($ch);
    $j = json_decode($body, true);
    if (is_array($j) && !empty($j['success']) && isset($j['obj']) && is_string($j['obj'])) {
        return $j['obj'];
    }
    return null;
}

function vira_xui_apply_session(CurlRequest $req)
{
    if (!empty($GLOBALS['vira_xui_csrf'])) {
        $req->setCsrfToken($GLOBALS['vira_xui_csrf']);
    }
}

function login($code_panel, $verify = true, $quick = false, $forceProbe = false)
{
    $GLOBALS['vira_xui_csrf'] = null;
    $panel = select("marzban_panel", "*", "code_panel", $code_panel, "select");
    $cookiePath = vira_xui_cookie_jar_path($code_panel);

    $bearer = vira_xui_bearer_token($panel);
    if ($bearer !== '') {
        if (!$forceProbe && $panel['datelogin'] != null) {
            $date = json_decode($panel['datelogin'], true);
            if (is_array($date) && isset($date['time'], $date['mode']) && $date['mode'] === 'bearer') {
                $start_date = time() - strtotime($date['time']);
                if ($start_date <= 3000) {
                    return array('success' => true, 'msg' => 'bearer cache');
                }
            }
        }
        $probe = vira_xui_verify_bearer($panel['url_panel'], $bearer, $quick);
        if (!empty($probe['success'])) {
            if ($verify) {
                $time = date('Y/m/d H:i:s');
                $data = json_encode(array(
                    'time' => $time,
                    'mode' => 'bearer',
                ));
                update("marzban_panel", "datelogin", $data, 'name_panel', $panel['name_panel']);
            }
            return array('success' => true, 'msg' => 'api token');
        }
        $failMsg = isset($probe['msg']) ? $probe['msg'] : 'توکن API معتبر نیست یا پنل پاسخ نداد.';
        if (!empty($probe['error'])) {
            $failMsg = $probe['error'];
        }
        return array(
            'success' => false,
            'msg' => function_exists('vira_xui_format_panel_error')
                ? vira_xui_format_panel_error(array('error' => $failMsg))
                : $failMsg,
        );
    }

    if (!$forceProbe && $panel['datelogin'] != null) {
        $date = json_decode($panel['datelogin'], true);
        if (
            is_array($date) && isset($date['time'], $date['access_token']) && $date['access_token'] !== ''
            && (!isset($date['mode']) || $date['mode'] !== 'bearer')
        ) {
            $start_date = time() - strtotime($date['time']);
            if ($start_date <= 3000) {
                file_put_contents($cookiePath, $date['access_token']);
                $GLOBALS['vira_xui_csrf'] = fetch_xui_csrf_token($panel['url_panel'], $cookiePath, $quick);
                return array('success' => true, 'msg' => 'session cache');
            }
        }
    }
    $response = panel_login_cookie($panel['code_panel'], $quick);
    $dec = is_string($response) ? json_decode($response, true) : null;
    $loginOk = is_array($dec) && !empty($dec['success']);
    if ($loginOk) {
        $time = date('Y/m/d H:i:s');
        $jar = @file_get_contents($cookiePath);
        if ($jar !== false && $jar !== '') {
            $data = json_encode(array(
                'time' => $time,
                'access_token' => $jar,
            ));
            update("marzban_panel", "datelogin", $data, 'name_panel', $panel['name_panel']);
        }
        $GLOBALS['vira_xui_csrf'] = fetch_xui_csrf_token($panel['url_panel'], $cookiePath, $quick);
    }
    if (!is_string($response)) {
        $m = $GLOBALS['vira_xui_last_login_meta'] ?? array();
        return array(
            'success' => false,
            'msg' => 'empty or invalid curl response. HTTP=' . ($m['http'] ?? '?')
                . ' url=' . ($m['effective_url'] ?? '') . ' err=' . ($m['curl_err'] ?? ''),
        );
    }
    if (!is_array($dec)) {
        $m = $GLOBALS['vira_xui_last_login_meta'] ?? array();
        $snippet = '';
        if ($response !== '') {
            $snippet = function_exists('mb_substr') ? mb_substr($response, 0, 120) : substr($response, 0, 120);
            $snippet = preg_replace('/\s+/', ' ', $snippet);
        }
        $final = isset($m['effective_url']) ? substr($m['effective_url'], 0, 120) : '';
        $hint = '';
        if (!empty($m['http']) && (int) $m['http'] === 403) {
            $hint = ' | اگر هنوز 403 است: در Cloudflare/WAF آی‌پی سرور ربات را Allow کنید یا در ربات از منوی پنل گزینهٔ «توکن API پنل (3x-ui)» را بزنید.';
        }
        return array(
            'success' => false,
            'msg' => substr(
                'Non-JSON from panel. HTTP=' . ($m['http'] ?? '?')
                . ' bytes=' . ($m['bytes'] ?? 0)
                . ' type=' . substr($m['content_type'] ?? '', 0, 40)
                . ' url=' . $final
                . ($snippet !== '' ? (' | ' . $snippet) : ' | body empty')
                . $hint,
                0,
                950
            ),
        );
    }
    if (!$loginOk) {
        return array(
            'success' => false,
            'msg' => isset($dec['msg']) ? $dec['msg'] : 'Login failed',
        );
    }
    return $dec;
}

/**
 * 3x-ui v3+: client-first API (/panel/api/clients/*). Legacy inbounds/* kept as fallback.
 * چند اینباند: panel.inbounds و product.inbounds به صورت JSON آرایهٔ عددی [1,2,3] (مثل چند سرویس در مرزبان).
 */
function vira_xui_parse_id_list($raw)
{
    if ($raw === null || $raw === '' || $raw === 'null') {
        return array();
    }
    if (is_array($raw)) {
        if ($raw === []) {
            return array();
        }
        $keys = array_keys($raw);
        $isList = ($keys === range(0, count($raw) - 1));
        if (!$isList) {
            return array();
        }
        $ids = array();
        foreach ($raw as $v) {
            if (is_numeric($v)) {
                $ids[] = (int) $v;
            }
        }
        return array_values(array_unique(array_filter($ids)));
    }
    if (is_numeric($raw)) {
        return array((int) $raw);
    }
    if (!is_string($raw)) {
        return array();
    }
    $trim = trim($raw);
    if ($trim === '') {
        return array();
    }
    if ($trim[0] === '[') {
        $decoded = json_decode($trim, true);
        if (is_array($decoded)) {
            return vira_xui_parse_id_list($decoded);
        }
    }
    if (strpos($trim, ',') !== false) {
        $parts = preg_split('/\s*,\s*/', $trim);
        return vira_xui_parse_id_list($parts);
    }
    if (is_numeric($trim)) {
        return array((int) $trim);
    }
    return array();
}

function vira_xui_inbound_ids($inboundid, array $panel)
{
    $ids = vira_xui_parse_id_list($inboundid);
    if ($ids !== []) {
        return $ids;
    }
    if (!empty($panel['inboundid']) && is_numeric($panel['inboundid'])) {
        return array((int) $panel['inboundid']);
    }
    return array();
}

/** انتخاب اینباندها: محصول → پنل → inboundid تکی (همان منطق marzban). */
function vira_xui_resolve_inbounds(array $panel, $product = null, $override = null)
{
    if ($override !== null) {
        $ids = vira_xui_inbound_ids($override, $panel);
        if ($ids !== []) {
            return $ids;
        }
    }
    if (is_array($product) && $product !== []) {
        $nameProduct = $product['name_product'] ?? '';
        if ($nameProduct === 'usertest') {
            $ids = vira_xui_parse_id_list($panel['inboundid'] ?? '');
            if ($ids !== []) {
                return $ids;
            }
        }
        if (
            $nameProduct !== ''
            && $nameProduct !== 'usertest'
            && !empty($product['inbounds'])
            && $product['inbounds'] !== 'null'
        ) {
            $ids = vira_xui_parse_id_list($product['inbounds']);
            if ($ids !== []) {
                return $ids;
            }
        }
    }
    if (!empty($panel['inbounds']) && $panel['inbounds'] !== 'null') {
        $ids = vira_xui_parse_id_list($panel['inbounds']);
        if ($ids !== []) {
            return $ids;
        }
    }
    return vira_xui_inbound_ids($panel['inboundid'] ?? null, $panel);
}

function vira_xui_save_panel_inbounds($namePanel, array $ids)
{
    $ids = array_values(array_unique(array_map('intval', $ids)));
    $first = $ids !== [] ? (string) $ids[0] : '1';
    update('marzban_panel', 'inbounds', json_encode($ids), 'name_panel', $namePanel);
    update('marzban_panel', 'inboundid', $first, 'name_panel', $namePanel);
}

/** IDهای اینباندی که واقعاً روی پنل وجود دارند (clients API). */
function vira_xui_fetch_inbound_id_set(array $panel, $cookiePath)
{
    $res = vira_xui_api_call($panel, $cookiePath, 'GET', '/panel/api/inbounds/options', null, 2);
    if ((int) ($res['status'] ?? 0) !== 200) {
        return null;
    }
    $dec = json_decode($res['body'] ?? '', true);
    if (!is_array($dec) || !vira_xui_json_is_success($dec) || !isset($dec['obj']) || !is_array($dec['obj'])) {
        return null;
    }
    $ids = array();
    foreach ($dec['obj'] as $ib) {
        if (isset($ib['id']) && is_numeric($ib['id'])) {
            $ids[(int) $ib['id']] = true;
        }
    }
    return $ids;
}

/** فیلتر inboundIds نامعتبر قبل از clients/add — جلوگیری از record not found. */
function vira_xui_validate_inbound_ids(array $panel, $cookiePath, array $requestedIds)
{
    $requestedIds = array_values(array_unique(array_filter(array_map('intval', $requestedIds))));
    if ($requestedIds === []) {
        return array(
            'ids' => array(),
            'error' => 'inbound id is required — از ادمین اینباند پنل را تنظیم کنید',
        );
    }
    $validSet = vira_xui_fetch_inbound_id_set($panel, $cookiePath);
    if ($validSet === null) {
        return array('ids' => $requestedIds, 'error' => null);
    }
    $valid = array_values(array_filter($requestedIds, static function ($id) use ($validSet) {
        return isset($validSet[$id]);
    }));
    $invalid = array_values(array_diff($requestedIds, $valid));
    if ($invalid !== []) {
        vira_xui_panel_log(
            'stale inbound ids=' . implode(',', $invalid)
            . ' requested=' . implode(',', $requestedIds)
            . ' panel=' . ($panel['name_panel'] ?? '')
        );
    }
    if ($valid === []) {
        $available = implode(', ', array_keys($validSet));
        return array(
            'ids' => array(),
            'error' => 'اینباند(های) تنظیم‌شده در ربات روی پنل 3x-ui وجود ندارند (record not found).'
                . ' از ادمین ربات → پنل → انتخاب اینباندها را دوباره ذخیره کنید.'
                . ($available !== '' ? ' اینباندهای موجود روی پنل: ' . $available : ' (هیچ اینباندی روی پنل نیست)'),
        );
    }
    return array('ids' => $valid, 'error' => null);
}

function vira_xui_heal_product_inbounds(array $productRow, array $validIds)
{
    if (empty($productRow['id'])) {
        return;
    }
    $val = json_encode(array_values(array_map('intval', $validIds)));
    update('product', 'inbounds', $val, 'id', $productRow['id']);
    vira_xui_panel_log(
        'healed product inbounds id=' . $productRow['id']
        . ' name=' . ($productRow['name_product'] ?? '')
        . ' -> ' . $val
    );
}

/** اینباند معتبر برای ساخت کاربر — در صورت inbounds قدیمی محصول، به پنل fallback می‌کند. */
function vira_xui_resolve_inbounds_for_client(array $panel, $cookiePath, $productRow = null, $override = null)
{
    $primary = vira_xui_resolve_inbounds($panel, $productRow, $override);
    $check = vira_xui_validate_inbound_ids($panel, $cookiePath, $primary);
    if (empty($check['error'])) {
        return $check;
    }
    $hadProductInbounds = is_array($productRow)
        && !empty($productRow['inbounds'])
        && $productRow['inbounds'] !== 'null';
    if (!$hadProductInbounds) {
        return $check;
    }
    $fallback = vira_xui_resolve_inbounds($panel, null, $override);
    if ($fallback === $primary) {
        return $check;
    }
    $check2 = vira_xui_validate_inbound_ids($panel, $cookiePath, $fallback);
    if (!empty($check2['error']) || $check2['ids'] === []) {
        return $check;
    }
    vira_xui_panel_log(
        'product stale inbounds fallback product=' . ($productRow['name_product'] ?? '')
        . ' old=' . implode(',', $primary)
        . ' new=' . implode(',', $check2['ids'])
    );
    vira_xui_heal_product_inbounds($productRow, $check2['ids']);
    return $check2;
}

/** همه محصولات یک پنل — inbounds منقضی را با اینباندهای فعلی پنل هم‌تراز می‌کند. */
function vira_xui_heal_stale_product_inbounds_for_panel($namePanel)
{
    global $pdo;
    $panel = select('marzban_panel', '*', 'name_panel', $namePanel, 'select');
    if (!$panel) {
        return 0;
    }
    $cookiePath = vira_xui_cookie_jar_path($panel['code_panel']);
    login($panel['code_panel']);
    $validSet = vira_xui_fetch_inbound_id_set($panel, $cookiePath);
    @unlink($cookiePath);
    if ($validSet === null || $validSet === []) {
        return 0;
    }
    $panelDefault = vira_xui_resolve_inbounds($panel, null);
    if ($panelDefault === []) {
        $panelDefault = array_map('intval', array_keys($validSet));
    }
    $fixed = 0;
    $stmt = $pdo->prepare(
        "SELECT id, name_product, inbounds FROM product WHERE Location = :loc AND inbounds IS NOT NULL AND inbounds != '' AND inbounds != 'null'"
    );
    $stmt->execute(array(':loc' => $namePanel));
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $ids = vira_xui_parse_id_list($row['inbounds']);
        $valid = array_values(array_filter($ids, static function ($id) use ($validSet) {
            return isset($validSet[$id]);
        }));
        if ($valid !== [] && $valid === $ids) {
            continue;
        }
        $newIds = $valid !== [] ? $valid : $panelDefault;
        vira_xui_heal_product_inbounds($row, $newIds);
        $fixed++;
    }
    return $fixed;
}

function vira_xui_api_call_once(array $panel, $cookiePath, $method, $apiPath, $body = null)
{
    $b = vira_xui_public_base($panel['url_panel']);
    $url = $b . $apiPath;
    $req = vira_xui_new_request($url);
    $req->setHeaders(array_merge(
        vira_xui_spa_headers($panel['url_panel']),
        array('Content-Type: application/json', 'Accept: application/json')
    ));
    vira_xui_auth_request($req, $panel, $cookiePath);
    $m = strtoupper($method);
    if ($m === 'GET') {
        return $req->get();
    }
    if ($m === 'DELETE') {
        return $req->delete($body);
    }
    return $req->post($body);
}

function vira_xui_api_call(array $panel, $cookiePath, $method, $apiPath, $body = null, $maxRetries = null)
{
    if (vira_xui_panel_budget_exceeded()) {
        return array(
            'status' => 504,
            'body' => null,
            'error' => 'panel time budget exceeded (' . VIRA_XUI_PANEL_BUDGET_SEC . 's)',
        );
    }
    $isWrite = in_array(strtoupper($method), array('POST', 'PUT', 'PATCH', 'DELETE'), true);
    $max = $maxRetries ?? ($isWrite ? VIRA_XUI_API_RETRY_WRITE_MAX : VIRA_XUI_API_RETRY_MAX);
    $max = max(1, (int) $max);
    $last = array('status' => 0, 'body' => null, 'error' => 'no attempt');
    for ($attempt = 1; $attempt <= $max; $attempt++) {
        if (vira_xui_panel_budget_exceeded()) {
            return array(
                'status' => 504,
                'body' => null,
                'error' => 'panel time budget exceeded',
            );
        }
        $last = vira_xui_api_call_once($panel, $cookiePath, $method, $apiPath, $body);
        $status = (int) ($last['status'] ?? 0);
        if (empty($last['error']) && $status >= 200 && $status < 300) {
            return $last;
        }
        if (!vira_xui_http_should_retry($last) || $attempt >= $max) {
            return $last;
        }
        usleep(VIRA_XUI_API_RETRY_DELAY_MS * 1000 * $attempt);
    }
    return $last;
}

function vira_xui_panel_api_mode_cache_path($code_panel)
{
    return __DIR__ . DIRECTORY_SEPARATOR . '.xui_api_mode_' . md5((string) $code_panel) . '.json';
}

function vira_xui_panel_uses_clients_api(array $panel, $cookiePath)
{
    $key = 'vira_xui_clients_api_' . ($panel['code_panel'] ?? '');
    if (isset($GLOBALS[$key])) {
        return (bool) $GLOBALS[$key];
    }
    $cacheFile = vira_xui_panel_api_mode_cache_path($panel['code_panel'] ?? '');
    if (is_file($cacheFile)) {
        $cached = json_decode((string) file_get_contents($cacheFile), true);
        if (is_array($cached) && isset($cached['uses_clients_api']) && (int) ($cached['ts'] ?? 0) > time() - 604800) {
            $GLOBALS[$key] = (bool) $cached['uses_clients_api'];
            return $GLOBALS[$key];
        }
    }
    $probe = vira_xui_api_call($panel, $cookiePath, 'GET', '/panel/api/clients/list', null, 2);
    $ok = false;
    if (empty($probe['error']) && (int) ($probe['status'] ?? 0) === 200) {
        $dec = json_decode($probe['body'] ?? '', true);
        $ok = is_array($dec) && !empty($dec['success']);
    }
    $GLOBALS[$key] = $ok;
    @file_put_contents($cacheFile, json_encode(array('uses_clients_api' => $ok, 'ts' => time())));
    return $ok;
}

/** بررسی سریع وجود کاربر روی پنل (بدون کشیدن لینک ساب). */
function vira_xui_user_exists_quick(array $panel, $email)
{
    $email = trim((string) $email);
    if ($email === '') {
        return false;
    }
    $cookiePath = vira_xui_cookie_jar_path($panel['code_panel']);
    $auth = login($panel['code_panel'], false, true);
    if (empty($auth['success'])) {
        return false;
    }
    if (vira_xui_panel_uses_clients_api($panel, $cookiePath)) {
        $res = vira_xui_api_call($panel, $cookiePath, 'GET', '/panel/api/clients/get/' . rawurlencode($email), null, 2);
        if (!empty($res['error']) || (int) ($res['status'] ?? 0) !== 200) {
            return false;
        }
        $dec = json_decode($res['body'] ?? '', true);
        return is_array($dec) && !empty($dec['success']) && !empty($dec['obj']);
    }
    $b = vira_xui_public_base($panel['url_panel']);
    $url = $b . '/panel/api/inbounds/getClientTraffics/' . rawurlencode($email);
    $req = vira_xui_new_request($url, true);
    $req->setHeaders(array_merge(
        vira_xui_spa_headers($panel['url_panel']),
        array('Content-Type: application/json')
    ));
    vira_xui_auth_request($req, $panel, $cookiePath);
    $res = $req->get();
    if (!empty($res['error']) || (int) ($res['status'] ?? 0) !== 200) {
        return false;
    }
    $dec = json_decode($res['body'] ?? '', true);
    return is_array($dec) && !empty($dec['obj']);
}

/** Map clients/get + traffic to legacy getClientTraffics obj shape (panels.php expects this). */
function vira_xui_normalize_client_obj(array $payload, array $panel, $traffic = null)
{
    $client = isset($payload['client']) && is_array($payload['client']) ? $payload['client'] : $payload;
    $inboundIds = isset($payload['inboundIds']) && is_array($payload['inboundIds']) ? $payload['inboundIds'] : array();
    if ($traffic === null && isset($client['traffic']) && is_array($client['traffic'])) {
        $traffic = $client['traffic'];
    }
    $inboundId = 0;
    if ($inboundIds !== []) {
        $inboundId = (int) $inboundIds[0];
    } elseif (!empty($panel['inboundid'])) {
        $inboundId = (int) $panel['inboundid'];
    }
    $total = 0;
    $up = 0;
    $down = 0;
    if (is_array($traffic)) {
        $total = (int) ($traffic['total'] ?? $traffic['totalGB'] ?? 0);
        $up = (int) ($traffic['up'] ?? 0);
        $down = (int) ($traffic['down'] ?? 0);
    }
    if ($total === 0) {
        $total = (int) ($client['totalGB'] ?? $client['total'] ?? 0);
    }
    $ids = array_values(array_unique(array_map('intval', $inboundIds)));
    if ($ids === [] && $inboundId > 0) {
        $ids = array($inboundId);
    }
    return array(
        'email' => $client['email'] ?? '',
        'uuid' => $client['uuid'] ?? $client['id'] ?? '',
        'subId' => $client['subId'] ?? '',
        'enable' => !empty($client['enable']),
        'expiryTime' => (int) ($client['expiryTime'] ?? 0),
        'total' => $total,
        'up' => $up,
        'down' => $down,
        'inboundId' => $inboundId,
        'inboundIds' => $ids,
        'lastOnline' => (int) ($client['lastOnline'] ?? 0),
    );
}

/** نرمال‌سازی پاسخ /panel/api/inbounds/options (آرایه یا آبجکت کلیددار). */
function vira_xui_normalize_inbound_options_obj($obj)
{
    if (!is_array($obj)) {
        return array();
    }
    if ($obj === [] || array_key_exists(0, $obj)) {
        return array_values($obj);
    }
    $list = array();
    foreach ($obj as $key => $ib) {
        if (!is_array($ib)) {
            continue;
        }
        if (!isset($ib['id']) && is_numeric($key)) {
            $ib['id'] = (int) $key;
        }
        $list[] = $ib;
    }
    return $list;
}

/** لیست اینباندها برای منوی ادمین — GET /panel/api/inbounds/options */
function vira_xui_list_inbound_options($namepanel)
{
    $GLOBALS['vira_xui_last_inbound_error'] = '';
    $panel = select('marzban_panel', '*', 'name_panel', $namepanel, 'select');
    if (!$panel) {
        $GLOBALS['vira_xui_last_inbound_error'] = 'panel not found';
        return array();
    }
    $cookiePath = vira_xui_cookie_jar_path($panel['code_panel']);
    $loginResult = login($panel['code_panel'], false, true);
    if (empty($loginResult['success'])) {
        $msg = is_array($loginResult) ? (string) ($loginResult['msg'] ?? '') : '';
        $GLOBALS['vira_xui_last_inbound_error'] = $msg !== '' ? strip_tags($msg) : 'ورود به پنل ناموفق — توکن API یا نام‌کاربری/رمز را بررسی کنید.';
        @unlink($cookiePath);
        $GLOBALS['vira_xui_csrf'] = null;
        return array();
    }
    $res = vira_xui_api_call($panel, $cookiePath, 'GET', '/panel/api/inbounds/options', null, 2);
    @unlink($cookiePath);
    $GLOBALS['vira_xui_csrf'] = null;
    if ((int) ($res['status'] ?? 0) !== 200) {
        $GLOBALS['vira_xui_last_inbound_error'] = function_exists('vira_xui_format_panel_error')
            ? strip_tags(vira_xui_format_panel_error($res))
            : 'HTTP ' . (int) ($res['status'] ?? 0);
        return array();
    }
    $dec = json_decode($res['body'] ?? '', true);
    if (!is_array($dec) || !vira_xui_json_is_success($dec) || !isset($dec['obj'])) {
        $GLOBALS['vira_xui_last_inbound_error'] = is_array($dec) && !empty($dec['msg'])
            ? (string) $dec['msg']
            : 'پاسخ نامعتبر از پنل برای لیست اینباندها';
        return array();
    }
    $list = vira_xui_normalize_inbound_options_obj($dec['obj']);
    if ($list === []) {
        $GLOBALS['vira_xui_last_inbound_error'] = 'پنل اینباندی برنگرداند';
    }
    return $list;
}

function vira_xui_last_inbound_fetch_error()
{
    return (string) ($GLOBALS['vira_xui_last_inbound_error'] ?? '');
}

/** هم‌تراز با مرزبان: attach/detach تا مجموعهٔ inboundIds کاربر دقیقاً target شود. */
function vira_xui_sync_client_inbounds(array $panel, $cookiePath, $email, array $targetIds, $syncExact = true)
{
    if (!vira_xui_panel_uses_clients_api($panel, $cookiePath)) {
        return array('success' => true);
    }
    $targetIds = array_values(array_unique(array_filter(array_map('intval', $targetIds))));
    if ($targetIds === []) {
        return array('success' => true);
    }
    $getRes = vira_xui_api_call($panel, $cookiePath, 'GET', '/panel/api/clients/get/' . rawurlencode($email));
    $dec = json_decode($getRes['body'] ?? '', true);
    if (!is_array($dec) || empty($dec['success']) || empty($dec['obj'])) {
        return array('success' => false, 'msg' => $dec['msg'] ?? 'client not found');
    }
    $current = array();
    if (!empty($dec['obj']['inboundIds']) && is_array($dec['obj']['inboundIds'])) {
        $current = array_map('intval', $dec['obj']['inboundIds']);
    }
    $toAttach = array_values(array_diff($targetIds, $current));
    $toDetach = $syncExact ? array_values(array_diff($current, $targetIds)) : array();
    if ($toAttach !== []) {
        $attachRes = vira_xui_api_call(
            $panel,
            $cookiePath,
            'POST',
            '/panel/api/clients/' . rawurlencode($email) . '/attach',
            json_encode(array('inboundIds' => $toAttach))
        );
        $attachDec = json_decode($attachRes['body'] ?? '', true);
        if (!is_array($attachDec) || empty($attachDec['success'])) {
            return array('success' => false, 'msg' => $attachDec['msg'] ?? 'attach failed');
        }
    }
    if ($toDetach !== []) {
        $detachRes = vira_xui_api_call(
            $panel,
            $cookiePath,
            'POST',
            '/panel/api/clients/' . rawurlencode($email) . '/detach',
            json_encode(array('inboundIds' => $toDetach))
        );
        $detachDec = json_decode($detachRes['body'] ?? '', true);
        if (!is_array($detachDec) || empty($detachDec['success'])) {
            return array('success' => false, 'msg' => $detachDec['msg'] ?? 'detach failed');
        }
    }
    return array('success' => true);
}

function vira_xui_finish_request(array $panel, $cookiePath, $response)
{
    if (isset($response['body'])) {
        $decodedBody = json_decode($response['body'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decodedBody)) {
            if (isset($decodedBody['success']) && $decodedBody['success'] === false) {
                $response['error'] = $decodedBody['msg'] ?? 'Unknown panel error';
            }
        }
    }
    if (!empty($response['error'])) {
        $errMsg = (string) $response['error'];
        if (stripos($errMsg, 'record not found') === false && stripos($errMsg, 'timed out') === false) {
            error_log(json_encode($response));
        }
    }
    if (is_file($cookiePath)) {
        @unlink($cookiePath);
    }
    $GLOBALS['vira_xui_csrf'] = null;
    return $response;
}

function vira_xui_expiry_ms($name_product, array $panel, $Expire)
{
    if ($name_product == 'usertest') {
        if ($panel['on_hold_test'] == '1') {
            if ($Expire == 0) {
                return 0;
            }
            $timelast = $Expire - time();
            return -intval(($timelast / 86400) * 86400000);
        }
        return $Expire * 1000;
    }
    if ($panel['conecton'] == 'onconecton') {
        if ($Expire == 0) {
            return 0;
        }
        $timelast = $Expire - time();
        return -intval(($timelast / 86400) * 86400000);
    }
    return $Expire * 1000;
}

function get_clinets($username, $namepanel)
{
    $marzban_list_get = select('marzban_panel', '*', 'name_panel', $namepanel, 'select');
    $cookiePath = vira_xui_cookie_jar_path($marzban_list_get['code_panel']);
    login($marzban_list_get['code_panel']);

    if (vira_xui_panel_uses_clients_api($marzban_list_get, $cookiePath)) {
        $emailPath = '/panel/api/clients/get/' . rawurlencode($username);
        $getRes = vira_xui_api_call($marzban_list_get, $cookiePath, 'GET', $emailPath);
        $status = (int) ($getRes['status'] ?? 0);
        $dec = json_decode($getRes['body'] ?? '', true);
        if ($status === 200 && is_array($dec) && !empty($dec['success']) && !empty($dec['obj'])) {
            $traffic = null;
            $trafRes = vira_xui_api_call(
                $marzban_list_get,
                $cookiePath,
                'GET',
                '/panel/api/clients/traffic/' . rawurlencode($username)
            );
            if ((int) ($trafRes['status'] ?? 0) === 200) {
                $trafDec = json_decode($trafRes['body'] ?? '', true);
                if (is_array($trafDec) && !empty($trafDec['success']) && isset($trafDec['obj'])) {
                    $traffic = $trafDec['obj'];
                }
            }
            $legacyObj = vira_xui_normalize_client_obj($dec['obj'], $marzban_list_get, $traffic);
            $getRes['body'] = json_encode(array('success' => true, 'obj' => $legacyObj));
            return vira_xui_finish_request($marzban_list_get, $cookiePath, $getRes);
        }
        if (is_array($dec) && isset($dec['success']) && $dec['success'] === false) {
            $getRes['error'] = $dec['msg'] ?? 'Unknown panel error';
            return vira_xui_finish_request($marzban_list_get, $cookiePath, $getRes);
        }
    }

    $b = vira_xui_public_base($marzban_list_get['url_panel']);
    $url = $b . '/panel/api/inbounds/getClientTraffics/' . rawurlencode($username);
    $req = vira_xui_new_request($url);
    $req->setHeaders(array_merge(
        vira_xui_spa_headers($marzban_list_get['url_panel']),
        array('Content-Type: application/json')
    ));
    vira_xui_auth_request($req, $marzban_list_get, $cookiePath);
    return vira_xui_finish_request($marzban_list_get, $cookiePath, $req->get());
}
function addClient($namepanel, $usernameac, $Expire, $Total, $Uuid, $Flow, $subid, $inboundid, $name_product, $note = '')
{
    vira_xui_panel_budget_start(VIRA_XUI_PANEL_BUDGET_SEC);
    vira_xui_panel_log('addClient start panel=' . $namepanel . ' user=' . $usernameac);
    if (!isset($usernameac)) {
        return array(
            'status' => 500,
            'msg' => 'username is null',
        );
    }
    $marzban_list_get = select('marzban_panel', '*', 'name_panel', $namepanel, 'select');
    $cookiePath = vira_xui_cookie_jar_path($marzban_list_get['code_panel']);
    $loginRes = login($marzban_list_get['code_panel'], false, true);
    if (empty($loginRes['success'])) {
        vira_xui_panel_log('addClient login failed: ' . ($loginRes['msg'] ?? ''));
        return array(
            'status' => 503,
            'body' => null,
            'error' => $loginRes['msg'] ?? 'panel login failed',
            'msg' => function_exists('vira_xui_format_panel_error')
                ? vira_xui_format_panel_error(array('error' => $loginRes['msg'] ?? 'panel login failed'))
                : ($loginRes['msg'] ?? 'panel login failed'),
        );
    }
    $timeservice = vira_xui_expiry_ms($name_product, $marzban_list_get, $Expire);
    $productRow = ($name_product !== false && $name_product !== '')
        ? select('product', '*', 'name_product', $name_product, 'select')
        : null;
    $limitIp = (is_array($productRow) && isset($productRow['limit_ip']))
        ? max(0, (int) $productRow['limit_ip'])
        : 0;

    if (vira_xui_panel_uses_clients_api($marzban_list_get, $cookiePath)) {
        $inboundCheck = vira_xui_resolve_inbounds_for_client($marzban_list_get, $cookiePath, $productRow, $inboundid);
        if (!empty($inboundCheck['error'])) {
            vira_xui_panel_log('addClient inbound validation: ' . $inboundCheck['error']);
            return array(
                'status' => 500,
                'body' => null,
                'error' => $inboundCheck['error'],
            );
        }
        $inboundIds = $inboundCheck['ids'];
        if ($inboundIds === []) {
            vira_xui_panel_log('addClient no inbounds panel=' . $namepanel);
            return array(
                'status' => 500,
                'body' => null,
                'error' => 'inbound id is required — از ادمین اینباند پنل را تنظیم کنید',
            );
        }
        $payload = json_encode(array(
            'client' => array(
                'id' => $Uuid,
                'email' => $usernameac,
                'flow' => $Flow,
                'totalGB' => (int) $Total,
                'expiryTime' => $timeservice,
                'enable' => true,
                'tgId' => 0,
                'subId' => $subid,
                'limitIp' => $limitIp,
                'reset' => 0,
                'comment' => (string) $note,
            ),
            'inboundIds' => $inboundIds,
        ));
        $response = vira_xui_api_call($marzban_list_get, $cookiePath, 'POST', '/panel/api/clients/add', $payload, VIRA_XUI_API_RETRY_WRITE_MAX);
        $bodySnippet = function_exists('mb_substr')
            ? mb_substr((string) ($response['body'] ?? ''), 0, 240)
            : substr((string) ($response['body'] ?? ''), 0, 240);
        vira_xui_panel_log('addClient clients/add status=' . ($response['status'] ?? 'null') . ' err=' . ($response['error'] ?? '') . ' body=' . $bodySnippet);
        return vira_xui_finish_request($marzban_list_get, $cookiePath, $response);
    }

    $config = array(
        'id' => (int) $inboundid,
        'settings' => json_encode(array(
            'clients' => array(
                array(
                    'id' => $Uuid,
                    'flow' => $Flow,
                    'email' => $usernameac,
                    'totalGB' => $Total,
                    'expiryTime' => $timeservice,
                    'enable' => true,
                    'tgId' => '',
                    'subId' => $subid,
                    'limitIp' => $limitIp,
                    'reset' => 0,
                    'comment' => $note,
                ),
            ),
            'decryption' => 'none',
            'fallbacks' => array(),
        )),
    );
    $response = vira_xui_api_call($marzban_list_get, $cookiePath, 'POST', '/panel/api/inbounds/addClient', json_encode($config), VIRA_XUI_API_RETRY_WRITE_MAX);
    return vira_xui_finish_request($marzban_list_get, $cookiePath, $response);
}

function updateClient($namepanel, $uuid, array $config)
{
    $marzban_list_get = select('marzban_panel', '*', 'name_panel', $namepanel, 'select');
    $cookiePath = vira_xui_cookie_jar_path($marzban_list_get['code_panel']);
    login($marzban_list_get['code_panel']);

    $settingsRaw = $config['settings'] ?? '{}';
    $settings = is_array($settingsRaw) ? $settingsRaw : json_decode($settingsRaw, true);
    $clientRow = array();
    if (is_array($settings) && !empty($settings['clients'][0])) {
        $clientRow = $settings['clients'][0];
    }
    $email = $clientRow['email'] ?? '';

    if ($email !== '' && vira_xui_panel_uses_clients_api($marzban_list_get, $cookiePath)) {
        $payload = array(
            'email' => $email,
            'id' => $clientRow['id'] ?? $uuid,
            'flow' => $clientRow['flow'] ?? '',
            'totalGB' => (int) ($clientRow['totalGB'] ?? $clientRow['total'] ?? 0),
            'expiryTime' => (int) ($clientRow['expiryTime'] ?? 0),
            'enable' => !isset($clientRow['enable']) || !empty($clientRow['enable']),
            'subId' => $clientRow['subId'] ?? '',
            'tgId' => isset($clientRow['tgId']) ? (int) $clientRow['tgId'] : 0,
            'limitIp' => (int) ($clientRow['limitIp'] ?? 0),
            'reset' => (int) ($clientRow['reset'] ?? 0),
        );
        if (isset($clientRow['comment'])) {
            $payload['comment'] = $clientRow['comment'];
        }
        $path = '/panel/api/clients/update/' . rawurlencode($email);
        $response = vira_xui_api_call($marzban_list_get, $cookiePath, 'POST', $path, json_encode($payload));
        if (
            empty($response['error'])
            && (int) ($response['status'] ?? 0) === 200
            && !empty($config['inboundIds'])
        ) {
            $sync = vira_xui_sync_client_inbounds(
                $marzban_list_get,
                $cookiePath,
                $email,
                vira_xui_parse_id_list($config['inboundIds']),
                true
            );
            if (empty($sync['success'])) {
                $response['error'] = $sync['msg'] ?? 'inbound sync failed';
            }
        }
        @unlink($cookiePath);
        $GLOBALS['vira_xui_csrf'] = null;
        return $response;
    }

    $response = vira_xui_api_call($marzban_list_get, $cookiePath, 'POST', '/panel/api/inbounds/updateClient/' . rawurlencode((string) $uuid), json_encode($config));
    @unlink($cookiePath);
    $GLOBALS['vira_xui_csrf'] = null;
    return $response;
}

function ResetUserDataUsagex_uisin($usernamepanel, $namepanel)
{
    $marzban_list_get = select('marzban_panel', '*', 'name_panel', $namepanel, 'select');
    $cookiePath = vira_xui_cookie_jar_path($marzban_list_get['code_panel']);
    login($marzban_list_get['code_panel']);

    if (vira_xui_panel_uses_clients_api($marzban_list_get, $cookiePath)) {
        $path = '/panel/api/clients/resetTraffic/' . rawurlencode($usernamepanel);
        $response = vira_xui_api_call($marzban_list_get, $cookiePath, 'POST', $path, '{}');
        @unlink($cookiePath);
        $GLOBALS['vira_xui_csrf'] = null;
        return $response;
    }

    $data_user = get_clinets($usernamepanel, $namepanel);
    $decoded = json_decode($data_user['body'] ?? '', true);
    $obj = is_array($decoded) ? ($decoded['obj'] ?? null) : null;
    if (!is_array($obj) || empty($obj['inboundId'])) {
        return array(
            'status' => 500,
            'body' => json_encode(array('success' => false, 'msg' => 'client not found for reset')),
        );
    }
    $path = '/panel/api/inbounds/' . (int) $obj['inboundId'] . '/resetClientTraffic/' . rawurlencode($usernamepanel);
    $response = vira_xui_api_call($marzban_list_get, $cookiePath, 'POST', $path, '{}');
    @unlink($cookiePath);
    $GLOBALS['vira_xui_csrf'] = null;
    return $response;
}

function removeClient($location, $username)
{
    $marzban_list_get = select('marzban_panel', '*', 'name_panel', $location, 'select');
    $cookiePath = vira_xui_cookie_jar_path($marzban_list_get['code_panel']);
    login($marzban_list_get['code_panel']);

    if (vira_xui_panel_uses_clients_api($marzban_list_get, $cookiePath)) {
        $path = '/panel/api/clients/del/' . rawurlencode($username);
        $response = vira_xui_api_call($marzban_list_get, $cookiePath, 'POST', $path, '{}');
        @unlink($cookiePath);
        $GLOBALS['vira_xui_csrf'] = null;
        return $response;
    }

    $path = '/panel/api/inbounds/' . (int) $marzban_list_get['inboundid'] . '/delClientByEmail/' . rawurlencode($username);
    $response = vira_xui_api_call($marzban_list_get, $cookiePath, 'POST', $path, '{}');
    @unlink($cookiePath);
    $GLOBALS['vira_xui_csrf'] = null;
    return $response;
}

/* ========== مدیریت پنل ۳x-ui در تلگرام (UX ادمین) ========== */

function vira_xui_panel_hash($name_panel)
{
    return substr(md5((string) $name_panel), 0, 8);
}

function vira_xui_admin_selection_load($from_id, $name_panel)
{
    $user = select('user', '*', 'id', $from_id, 'select');
    $data = json_decode($user['Processing_value_two'] ?? '', true);
    if (is_array($data) && ($data['panel'] ?? '') === $name_panel && isset($data['ids'])) {
        return vira_xui_parse_id_list($data['ids']);
    }
    $panel = select('marzban_panel', '*', 'name_panel', $name_panel, 'select');
    if (!$panel) {
        return array();
    }
    return vira_xui_resolve_inbounds($panel, null);
}

function vira_xui_admin_selection_save($from_id, $name_panel, array $ids, $listCache = null)
{
    $payload = array(
        'panel' => $name_panel,
        'ids' => array_values(array_unique(array_map('intval', $ids))),
    );
    // Prevent oversized payloads in user.Processing_value_two on old databases.
    // The picker can refetch the full list from panel when cache is missing.
    if (is_array($listCache) && count($listCache) <= 30) {
        $payload['list'] = $listCache;
    }
    update('user', 'Processing_value_two', json_encode($payload), 'id', $from_id);
}

function vira_xui_admin_picker_list_cached($from_id, $name_panel)
{
    $user = select('user', '*', 'id', $from_id, 'select');
    $data = json_decode($user['Processing_value_two'] ?? '', true);
    if (is_array($data) && ($data['panel'] ?? '') === $name_panel && !empty($data['list']) && is_array($data['list'])) {
        return $data['list'];
    }
    return null;
}

function vira_xui_fetch_server_status($name_panel)
{
    $panel = select('marzban_panel', '*', 'name_panel', $name_panel, 'select');
    if (!$panel) {
        return null;
    }
    $cookiePath = vira_xui_cookie_jar_path($panel['code_panel']);
    $login = login($panel['code_panel'], true, true);
    if (empty($login['success'])) {
        return null;
    }
    $res = vira_xui_api_call($panel, $cookiePath, 'GET', '/panel/api/server/status');
    @unlink($cookiePath);
    $GLOBALS['vira_xui_csrf'] = null;
    if ((int) ($res['status'] ?? 0) !== 200) {
        return null;
    }
    $dec = json_decode($res['body'] ?? '', true);
    if (!is_array($dec) || empty($dec['success']) || !isset($dec['obj'])) {
        return null;
    }
    return $dec['obj'];
}

function vira_xui_test_connection($code_panel)
{
    return login($code_panel, true, true, true);
}

function vira_xui_admin_dashboard_text(array $panel, $connect = null)
{
    $name = htmlspecialchars((string) ($panel['name_panel'] ?? ''), ENT_QUOTES, 'UTF-8');
    $ok = is_array($connect) && !empty($connect['success']);
    $stateLine = $ok ? '✅ <b>اتصال:</b> برقرار' : '❌ <b>اتصال:</b> قطع';
    $authMode = !empty(trim((string) ($panel['xui_api_token'] ?? ''))) ? 'توکن API' : 'نام کاربری و رمز';
    $inbounds = vira_xui_resolve_inbounds($panel, null);
    $ibText = $inbounds !== [] ? implode(', ', $inbounds) : 'انتخاب نشده';
    $subRaw = trim((string) ($panel['linksubx'] ?? ''));
    $panelRaw = trim((string) ($panel['url_panel'] ?? ''));

    $lines = array(
        "🎛 <b>مرکز پنل</b> — {$name}",
        '',
        $stateLine,
        "🔐 <b>احراز:</b> {$authMode}",
        "📡 <b>اینباندها:</b> <code>{$ibText}</code>",
    );

    if ($panelRaw !== '') {
        $panelEsc = htmlspecialchars($panelRaw, ENT_QUOTES, 'UTF-8');
        $lines[] = "🖥 <b>آدرس پنل:</b> <a href=\"{$panelEsc}\">{$panelEsc}</a>";
    }
    if ($subRaw !== '') {
        $subEsc = htmlspecialchars($subRaw, ENT_QUOTES, 'UTF-8');
        $lines[] = "🔗 <b>لینک اشتراک:</b> <a href=\"{$subEsc}\">باز کردن لینک ساب</a>";
    }

    if ($ok) {
        $stats = vira_xui_fetch_server_status($panel['name_panel']);
        if (is_array($stats)) {
            $cpu = isset($stats['cpu']) ? round((float) $stats['cpu'], 1) : '—';
            global $textbotlang;
            $uiLang = is_array($textbotlang ?? null) ? $textbotlang : (function_exists('languagechange') ? languagechange(null, 'fa') : []);
            $xray = vira_xray_state_label($stats['xray']['state'] ?? '', $uiLang);
            $xver = $stats['xray']['version'] ?? '';
            $memPct = '—';
            if (isset($stats['mem']['current'], $stats['mem']['total']) && (int) $stats['mem']['total'] > 0) {
                $memPct = round(((float) $stats['mem']['current'] / (float) $stats['mem']['total']) * 100, 1);
            }
            $lines[] = '';
            $lines[] = '📊 <b>وضعیت سرور</b>';
            $lines[] = "CPU: {$cpu}% · RAM: {$memPct}%";
            $lines[] = 'Xray: ' . htmlspecialchars((string) $xray, ENT_QUOTES, 'UTF-8')
                . ($xver !== '' ? ' (' . htmlspecialchars((string) $xver, ENT_QUOTES, 'UTF-8') . ')' : '');
        }
    } elseif (is_array($connect) && !empty($connect['msg'])) {
        $lines[] = '';
        $msg = (string) $connect['msg'];
        if (function_exists('vira_xui_format_panel_error') && stripos($msg, 'Failed to connect') !== false) {
            $msg = vira_xui_format_panel_error(array('error' => $msg));
        }
        $lines[] = '💬 ' . htmlspecialchars(mb_substr(strip_tags($msg), 0, 300), ENT_QUOTES, 'UTF-8');
    }

    if ($inbounds === []) {
        $lines[] = '';
        $lines[] = '👇 برای شروع، اینباند پنل را انتخاب کنید.';
    }

    return implode("\n", $lines);
}

function vira_xui_hub_inline_keyboard($name_panel)
{
    $h = vira_xui_panel_hash($name_panel);
    return json_encode(array(
        'inline_keyboard' => array(
            array(
                array('text' => '🎯 انتخاب اینباند', 'callback_data' => 'xuihub-ib-' . $h),
            ),
            array(
                array('text' => '🔑 توکن API', 'callback_data' => 'xuihub-tok-' . $h),
                array('text' => '🔄 تست اتصال', 'callback_data' => 'xuihub-tst-' . $h),
            ),
            array(
                array('text' => '📊 بروزرسانی', 'callback_data' => 'xuihub-ref-' . $h),
                array('text' => '📖 راهنما', 'callback_data' => 'xuihub-hlp-' . $h),
            ),
        ),
    ));
}

function vira_xui_inbound_picker_text(array $panel, array $selected)
{
    $count = count($selected);
    $hint = $count > 0
        ? 'انتخاب‌شده: <code>' . implode(', ', $selected) . '</code>'
        : 'هنوز اینباندی انتخاب نشده — روی دکمه‌ها بزنید.';
    return "🎯 <b>انتخاب اینباندها</b> (چندتایی مثل مرزبان)\n\n"
        . htmlspecialchars((string) $panel['name_panel'], ENT_QUOTES, 'UTF-8') . "\n\n"
        . $hint . "\n\n"
        . '⬜ خاموش · ✅ فعال — در پایان «💾 ذخیره» را بزنید.';
}

function vira_xui_inbound_picker_keyboard($name_panel, array $inbounds, array $selected)
{
    $h = vira_xui_panel_hash($name_panel);
    $rows = array();
    foreach ($inbounds as $ib) {
        $id = (int) ($ib['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $mark = in_array($id, $selected, true) ? '✅' : '⬜';
        $remark = (string) ($ib['remark'] ?? '');
        if ($remark === '') {
            $remark = (string) ($ib['protocol'] ?? 'inbound');
        }
        $remark = function_exists('mb_substr') ? mb_substr($remark, 0, 22) : substr($remark, 0, 22);
        $proto = (string) ($ib['protocol'] ?? '');
        $port = (int) ($ib['port'] ?? 0);
        $label = "{$mark} #{$id} {$remark}";
        if ($proto !== '') {
            $label .= " ({$proto}";
            if ($port > 0) {
                $label .= ":{$port}";
            }
            $label .= ')';
        }
        if (function_exists('mb_strlen') && mb_strlen($label) > 64) {
            $label = function_exists('mb_substr') ? mb_substr($label, 0, 61) . '…' : substr($label, 0, 61) . '…';
        }
        $rows[] = array(
            array(
                'text' => $label,
                'callback_data' => 'xuiib-' . $id . '-' . $h,
            ),
        );
    }
    $rows[] = array(
        array('text' => '💾 ذخیره', 'callback_data' => 'xuiib-save-' . $h),
        array('text' => '✅ همه', 'callback_data' => 'xuiib-all-' . $h),
        array('text' => '🗑 پاک', 'callback_data' => 'xuiib-none-' . $h),
    );
    $rows[] = array(
        array('text' => '◀️ بازگشت به مرکز', 'callback_data' => 'xuihub-ref-' . $h),
    );
    return json_encode(array('inline_keyboard' => $rows));
}

/** برچسب‌های منوی Reply کیبورد ۳x-ui (برای تشخیص قبل از step انتخاب پنل). */
function vira_xui_admin_reply_menu_labels()
{
    return array(
        '🎯 انتخاب اینباندها',
        '🔑 توکن API پنل (3x-ui)',
        '🔄 تست اتصال',
    );
}

function vira_xui_admin_is_reply_menu_label($text)
{
    return in_array((string) $text, vira_xui_admin_reply_menu_labels(), true);
}

function vira_xui_admin_can_manage($from_id, $adminrulecheck)
{
    global $admin_ids;

    return in_array($from_id, $admin_ids, true)
        && (($adminrulecheck['rule'] ?? '') === 'administrator');
}

function vira_xui_admin_resolve_panel_name(array $user)
{
    $name = trim((string) ($user['Processing_value'] ?? ''));
    if ($name === '') {
        return '';
    }
    $panel = select('marzban_panel', '*', 'name_panel', $name, 'select');
    if ($panel && ($panel['type'] ?? '') === 'x-ui_single') {
        return $name;
    }

    return '';
}

/**
 * دکمه‌های منوی ۳x-ui را پردازش می‌کند (حتی اگر step روی GetLocationEdit مانده باشد).
 *
 * @return bool true اگر پیام پردازش شد
 */
function vira_xui_admin_handle_reply_keyboard($from_id, $text, array $user, $adminrulecheck)
{
    global $optionX_ui_single, $backadmin;

    if (!vira_xui_admin_is_reply_menu_label($text) || !vira_xui_admin_can_manage($from_id, $adminrulecheck)) {
        return false;
    }

    step('home', $from_id);
    $namePanel = vira_xui_admin_resolve_panel_name($user);

    if ($text === '🔑 توکن API پنل (3x-ui)') {
        return false;
    }

    if ($namePanel === '') {
        sendmessage(
            $from_id,
            "❌ پنل ۳x-ui مشخص نیست.\n\nاز منوی ادمین → <b>مدیریت پنل</b> نام پنل را انتخاب کنید، سپس دوباره این دکمه را بزنید.",
            $backadmin ?? $optionX_ui_single,
            'HTML'
        );
        return true;
    }

    if ($text === '🎯 انتخاب اینباندها') {
        vira_xui_admin_open_inbound_picker($from_id, $namePanel);
        return true;
    }
    if ($text === '🔄 تست اتصال') {
        $panel = select('marzban_panel', '*', 'name_panel', $namePanel, 'select');
        if ($panel) {
            $xuiLogin = login($panel['code_panel'], false, true);
            $ok = !empty($xuiLogin['success']);
            $msg = $ok ? '✅ اتصال برقرار است.' : '⚠️ ' . mb_substr((string) ($xuiLogin['msg'] ?? 'خطای اتصال'), 0, 200);
            sendmessage($from_id, $msg . "\n\n" . vira_xui_admin_dashboard_text($panel, $xuiLogin), vira_xui_hub_inline_keyboard($namePanel), 'HTML');
        }
        return true;
    }

    return false;
}

function vira_xui_admin_guide_text()
{
    return "📖 <b>راهنمای سریع پنل ۳x-ui</b>\n\n"
        . "1️⃣ در پنل: <b>Settings → Security → API Token</b> توکن بسازید.\n"
        . "2️⃣ در ربات: <b>🔑 توکن API</b> را بفرستید (پایدارتر از رمز).\n"
        . "3️⃣ آدرس پنل <b>بدون /panel</b> در انتها باشد.\n"
        . "4️⃣ <b>🎯 انتخاب اینباند</b> — چند inbound مثل مرزبان.\n"
        . "5️⃣ برای هر محصول در فروشگاه: <b>🎛 تنظیم اینباند</b>.\n"
        . "6️⃣ <b>🔗 دامنه لینک ساب</b> را از یک لینک واقعی کاربر کپی کنید.\n\n"
        . "💡 مشکل 403؟ توکن API یا Allow IP سرور ربات در WAF/Cloudflare.";
}

function vira_xui_admin_open_hub($from_id, $name_panel)
{
    $panel = select('marzban_panel', '*', 'name_panel', $name_panel, 'select');
    if (!$panel || $panel['type'] !== 'x-ui_single') {
        return false;
    }
    update('user', 'Processing_value', $name_panel, 'id', $from_id);
    $xuiLogin = login($panel['code_panel'], true, true);
    $text = vira_xui_admin_dashboard_text($panel, $xuiLogin);
    sendmessage($from_id, $text, vira_xui_hub_inline_keyboard($name_panel), 'HTML');
    return true;
}

function vira_xui_admin_open_inbound_picker($from_id, $name_panel, $message_id = null)
{
    global $optionX_ui_single;
    $panel = select('marzban_panel', '*', 'name_panel', $name_panel, 'select');
    if (!$panel) {
        return false;
    }
    update('user', 'Processing_value', $name_panel, 'id', $from_id);
    $list = vira_xui_list_inbound_options($name_panel);
    if ($list === []) {
        sendmessage($from_id, '❌ لیست اینباند خالی است. اول توکن API یا اتصال را درست کنید.', $optionX_ui_single ?? null, 'HTML');
        return false;
    }
    $selected = vira_xui_admin_selection_load($from_id, $name_panel);
    vira_xui_admin_selection_save($from_id, $name_panel, $selected, $list);
    $text = vira_xui_inbound_picker_text($panel, $selected);
    $kb = vira_xui_inbound_picker_keyboard($name_panel, $list, $selected);
    if ($message_id !== null && $message_id > 0) {
        Editmessagetext($from_id, $message_id, $text, $kb, 'HTML');
    } else {
        sendmessage($from_id, $text, $kb, 'HTML');
    }
    return true;
}

function vira_xui_admin_verify_hash($name_panel, $hash)
{
    return vira_xui_panel_hash($name_panel) === $hash;
}
