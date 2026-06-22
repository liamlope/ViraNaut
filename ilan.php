<?php
/**
 * Ilan panel driver — generic REST (Vira Pro 6.7 pattern).
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/request.php';

function ilan_api_request(array $panel, string $path, string $method = 'GET', $body = null)
{
    $url = rtrim($panel['url_panel'], '/') . $path;
    $req = new CurlRequest($url);
    $req->setHeaders([
        'accept: application/json',
        'Content-Type: application/json',
        'Authorization: Bearer ' . ($panel['password_panel'] ?? ''),
    ]);
    if ($method === 'POST') {
        return $req->post(is_string($body) ? $body : json_encode($body));
    }
    if ($method === 'PUT' || $method === 'PATCH') {
        return $req->put(is_string($body) ? $body : json_encode($body));
    }
    if ($method === 'DELETE') {
        return $req->delete(is_string($body) ? $body : json_encode($body ?? []));
    }
    return $req->get();
}

function ilan_http_ok(array $resp): bool
{
    $code = (int) ($resp['status'] ?? 0);
    return empty($resp['error']) && ($code === 0 || ($code >= 200 && $code < 300));
}

function ilan_decode(array $resp): ?array
{
    $body = json_decode($resp['body'] ?? '', true);
    return is_array($body) ? $body : null;
}

function ilan_get_user(array $panel, string $username)
{
    return ilan_api_request($panel, '/api/users/' . rawurlencode($username));
}

function ilan_create_user(array $panel, string $username, int $data_limit_gb, int $expire_days)
{
    return ilan_api_request($panel, '/api/users', 'POST', [
        'username' => $username,
        'data_limit_gb' => $data_limit_gb,
        'expire_days' => $expire_days,
    ]);
}

function ilan_update_user(array $panel, string $username, array $fields)
{
    return ilan_api_request($panel, '/api/users/' . rawurlencode($username), 'PUT', $fields);
}

function ilan_delete_user(array $panel, string $username)
{
    return ilan_api_request($panel, '/api/users/' . rawurlencode($username), 'DELETE', []);
}

function ilan_revoke_link(array $panel, string $username)
{
    return ilan_api_request($panel, '/api/users/' . rawurlencode($username) . '/revoke', 'PUT', []);
}

function ilan_extend_user(array $panel, string $username, int $data_limit_gb, int $expire_days)
{
    return ilan_update_user($panel, $username, [
        'data_limit_gb' => $data_limit_gb,
        'expire_days' => $expire_days,
    ]);
}

function ilan_add_volume(array $panel, string $username, int $gb_add)
{
    return ilan_update_user($panel, $username, ['add_volume_gb' => $gb_add]);
}

function ilan_add_time(array $panel, string $username, int $days_add)
{
    return ilan_update_user($panel, $username, ['add_expire_days' => $days_add]);
}

function ilan_reset_usage(array $panel, string $username)
{
    return ilan_api_request($panel, '/api/users/' . rawurlencode($username) . '/reset', 'PUT', []);
}

function ilan_map_user_output(array $panel, string $username, ?array $data, $invoice, string $domainhosts): array
{
    if (!$data) {
        return ['status' => 'Unsuccessful', 'msg' => 'Ilan user not found'];
    }
    $sub = $data['subscription_url'] ?? (rtrim($panel['url_panel'], '/') . '/sub/' . ($data['username'] ?? $username));
    if ($invoice != false && is_array($invoice)) {
        $sub = "https://$domainhosts/sub/" . $invoice['id_invoice'];
    }
    $expire = $data['expire'] ?? $data['expire_timestamp'] ?? 0;
    if (!is_numeric($expire) && !empty($data['expire_at'])) {
        $expire = strtotime($data['expire_at']);
    }
    $limit = $data['data_limit'] ?? (($data['data_limit_gb'] ?? 0) * pow(1024, 3));
    return [
        'status' => $data['status'] ?? 'active',
        'username' => $data['username'] ?? $username,
        'data_limit' => (int) $limit,
        'expire' => (int) $expire,
        'online_at' => $data['online_at'] ?? 'offline',
        'used_traffic' => (int) ($data['used_traffic'] ?? 0),
        'links' => $data['links'] ?? [],
        'subscription_url' => $sub,
        'sub_updated_at' => $data['sub_updated_at'] ?? null,
        'sub_last_user_agent' => $data['sub_last_user_agent'] ?? null,
    ];
}
