<?php
/**
 * ManagePanel bridge helpers for ilan — keeps panels.php DRY.
 */
require_once __DIR__ . '/ilan.php';

function ilan_bridge_data_user(array $panel, string $username, $invoice, string $domainhosts): array
{
    $resp = ilan_get_user($panel, $username);
    if (!ilan_http_ok($resp)) {
        return ['status' => 'Unsuccessful', 'msg' => $resp['error'] ?? ($resp['body'] ?? 'Ilan API error')];
    }
    $data = ilan_decode($resp);
    if (!$data || !empty($data['detail'])) {
        return ['status' => 'Unsuccessful', 'msg' => $data['detail'] ?? 'Ilan user not found'];
    }
    return ilan_map_user_output($panel, $username, $data, $invoice, $domainhosts);
}

function ilan_bridge_revoke(array $panel, string $username, ManagePanel $mp, string $name_panel): array
{
    $resp = ilan_revoke_link($panel, $username);
    if (!ilan_http_ok($resp)) {
        return ['status' => 'Unsuccessful', 'msg' => $resp['error'] ?? 'Ilan revoke failed'];
    }
    $du = $mp->DataUser($name_panel, $username);
    return [
        'status' => 'successful',
        'configs' => $du['links'] ?? [],
        'subscription_url' => $du['subscription_url'] ?? '',
    ];
}

function ilan_bridge_remove(array $panel, string $username): array
{
    $resp = ilan_delete_user($panel, $username);
    if (!ilan_http_ok($resp)) {
        $data = ilan_decode($resp);
        return ['status' => 'Unsuccessful', 'msg' => $data['detail'] ?? ($resp['error'] ?? 'Ilan delete failed')];
    }
    return ['status' => 'successful', 'username' => $username];
}

function ilan_bridge_reset(array $panel, string $username): array
{
    $resp = ilan_reset_usage($panel, $username);
    if (!ilan_http_ok($resp)) {
        return ['status' => false, 'msg' => $resp['error'] ?? 'Ilan reset failed'];
    }
    return ['status' => true];
}

function ilan_bridge_mirza_style_result(array $resp): array
{
    if (!ilan_http_ok($resp)) {
        return ['status' => false, 'msg' => $resp['error'] ?? ($resp['body'] ?? 'Ilan API error')];
    }
    $data = ilan_decode($resp);
    if ($data && isset($data['status']) && $data['status'] === false) {
        return ['status' => false, 'msg' => $data['msg'] ?? 'Ilan rejected'];
    }
    return ['status' => true, 'msg' => 'successful'];
}
