<?php

/**
 * Phase 4.1 panel types supported from web CRUD.
 */
function panel_web_type_defs(): array
{
    return [
        'x-ui_single' => [
            'label' => '3x-ui',
            'xui' => true,
            'needs_inbounds' => true,
            'fields' => [
                ['key' => 'name_panel', 'label' => 'نام پنل', 'type' => 'text', 'required' => true],
                ['key' => 'url_panel', 'label' => 'آدرس API', 'type' => 'url', 'required' => true, 'dir' => 'ltr'],
                ['key' => 'linksubx', 'label' => 'دامنه لینک ساب', 'type' => 'url', 'dir' => 'ltr'],
                ['key' => 'username_panel', 'label' => 'نام کاربری', 'type' => 'text', 'dir' => 'ltr'],
                ['key' => 'password_panel', 'label' => 'رمز', 'type' => 'password', 'dir' => 'ltr'],
                ['key' => 'xui_api_token', 'label' => 'توکن API', 'type' => 'text', 'dir' => 'ltr'],
                ['key' => 'limit_panel', 'label' => 'محدودیت ساخت', 'type' => 'number', 'default' => '0'],
                ['key' => 'agent', 'label' => 'گروه کاربری', 'type' => 'agent'],
                ['key' => 'panel_inbounds', 'label' => 'اینباندها', 'type' => 'inbounds'],
            ],
        ],
        'alireza_single' => [
            'label' => 'Alireza',
            'xui' => true,
            'needs_inbounds' => true,
            'fields' => [
                ['key' => 'name_panel', 'label' => 'نام پنل', 'type' => 'text', 'required' => true],
                ['key' => 'url_panel', 'label' => 'آدرس API', 'type' => 'url', 'required' => true, 'dir' => 'ltr'],
                ['key' => 'linksubx', 'label' => 'دامنه لینک ساب', 'type' => 'url', 'dir' => 'ltr'],
                ['key' => 'username_panel', 'label' => 'نام کاربری', 'type' => 'text', 'dir' => 'ltr'],
                ['key' => 'password_panel', 'label' => 'رمز', 'type' => 'password', 'dir' => 'ltr'],
                ['key' => 'limit_panel', 'label' => 'محدودیت ساخت', 'type' => 'number', 'default' => '0'],
                ['key' => 'agent', 'label' => 'گروه کاربری', 'type' => 'agent'],
                ['key' => 'panel_inbounds', 'label' => 'اینباندها', 'type' => 'inbounds'],
            ],
        ],
        'marzban' => [
            'label' => 'Marzban',
            'xui' => false,
            'needs_inbounds' => false,
            'fields' => [
                ['key' => 'name_panel', 'label' => 'نام پنل', 'type' => 'text', 'required' => true],
                ['key' => 'url_panel', 'label' => 'آدرس پنل', 'type' => 'url', 'required' => true, 'dir' => 'ltr'],
                ['key' => 'username_panel', 'label' => 'نام کاربری', 'type' => 'text', 'required' => true, 'dir' => 'ltr'],
                ['key' => 'password_panel', 'label' => 'رمز', 'type' => 'password', 'required' => true, 'dir' => 'ltr'],
                ['key' => 'limit_panel', 'label' => 'محدودیت ساخت', 'type' => 'number', 'default' => '0'],
                ['key' => 'agent', 'label' => 'گروه کاربری', 'type' => 'agent'],
            ],
        ],
        'pasarguard' => [
            'label' => 'Pasarguard',
            'xui' => false,
            'needs_inbounds' => false,
            'db_type' => 'marzban',
            'version_panel' => '1',
            'fields' => [
                ['key' => 'name_panel', 'label' => 'نام پنل', 'type' => 'text', 'required' => true],
                ['key' => 'url_panel', 'label' => 'آدرس پنل', 'type' => 'url', 'required' => true, 'dir' => 'ltr'],
                ['key' => 'username_panel', 'label' => 'نام کاربری', 'type' => 'text', 'required' => true, 'dir' => 'ltr'],
                ['key' => 'password_panel', 'label' => 'رمز', 'type' => 'password', 'required' => true, 'dir' => 'ltr'],
                ['key' => 'limit_panel', 'label' => 'محدودیت ساخت', 'type' => 'number', 'default' => '0'],
                ['key' => 'agent', 'label' => 'گروه کاربری', 'type' => 'agent'],
            ],
        ],
        'hiddify' => [
            'label' => 'Hiddify',
            'xui' => false,
            'needs_inbounds' => false,
            'fields' => [
                ['key' => 'name_panel', 'label' => 'نام پنل', 'type' => 'text', 'required' => true],
                ['key' => 'url_panel', 'label' => 'آدرس پنل', 'type' => 'url', 'required' => true, 'dir' => 'ltr'],
                ['key' => 'secret_code', 'label' => 'کلید API (Hiddify-API-Key)', 'type' => 'text', 'required' => true, 'dir' => 'ltr'],
                ['key' => 'limit_panel', 'label' => 'محدودیت ساخت', 'type' => 'number', 'default' => '0'],
                ['key' => 'agent', 'label' => 'گروه کاربری', 'type' => 'agent'],
            ],
        ],
    ];
}

function panel_web_normalize_type(string $type): string
{
    $t = strtolower(trim($type));
    $aliases = ['3x-ui' => 'x-ui_single', '3xui' => 'x-ui_single', 'x_ui_single' => 'x-ui_single', 'x-ui' => 'x-ui_single'];
    return $aliases[$t] ?? $t;
}

function panel_web_is_xui_type(string $type): bool
{
    $defs = panel_web_type_defs();
    $t = panel_web_normalize_type($type);
    return !empty($defs[$t]['xui']);
}

function panel_web_type_needs_inbounds(string $type): bool
{
    $defs = panel_web_type_defs();
    $t = panel_web_normalize_type($type);
    return !empty($defs[$t]['needs_inbounds']);
}
