<?php
/**
 * Ilan panel driver (Mirza Pro 6.7 compatibility stub).
 * Extend with full API when panel documentation is available.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/request.php';

function ilan_api_request(array $panel, string $path, string $method = 'GET', $body = null)
{
    $url = rtrim($panel['url_panel'], '/') . $path;
    $req = new CurlRequest($url);
    $req->setHeaders([
        'accept: application/json',
        'Authorization: Bearer ' . ($panel['password_panel'] ?? ''),
    ]);
    if ($method === 'POST') {
        return $req->post(is_string($body) ? $body : json_encode($body));
    }
    if ($method === 'PUT') {
        return $req->put(is_string($body) ? $body : json_encode($body));
    }
    if ($method === 'DELETE') {
        return $req->delete(is_string($body) ? $body : json_encode($body ?? []));
    }
    return $req->get();
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

function ilan_revoke_link(array $panel, string $username)
{
    return ilan_api_request($panel, '/api/users/' . rawurlencode($username) . '/revoke', 'PUT', []);
}

function ilan_connection_status(array $panel, string $username)
{
    return ilan_api_request($panel, '/api/users/' . rawurlencode($username) . '/status');
}
