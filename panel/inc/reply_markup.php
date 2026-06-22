<?php

class ViraReplyMarkupError extends InvalidArgumentException
{
}

function vira_reply_markup_allowed_styles(): array
{
    return ['primary', 'success', 'danger'];
}

function vira_reply_markup_allowed_types(): array
{
    return ['url', 'web_app', 'callback'];
}

function vira_reply_markup_validate_url(string $url, bool $httpsOnly = false): string
{
    $url = trim($url);
    $parts = parse_url($url);
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    if (!in_array($scheme, ['http', 'https', 'tg'], true)) {
        throw new ViraReplyMarkupError('آدرس نامعتبر — از http(s) یا tg:// استفاده کنید');
    }
    if ($httpsOnly && $scheme !== 'https') {
        throw new ViraReplyMarkupError('آدرس Mini App باید https باشد');
    }
    if ($scheme !== 'tg' && empty($parts['host'])) {
        throw new ViraReplyMarkupError('آدرس نامعتبر');
    }
    return $url;
}

function vira_reply_markup_infer_type(array $btn): string
{
    $explicit = strtolower(trim((string) ($btn['type'] ?? '')));
    if (in_array($explicit, vira_reply_markup_allowed_types(), true)) {
        return $explicit;
    }
    if (isset($btn['web_app']) && is_array($btn['web_app'])) {
        return 'web_app';
    }
    if (!empty($btn['callback_data']) || !empty($btn['data'])) {
        return 'callback';
    }
    return 'url';
}

function vira_reply_markup_web_app_url(array $btn): string
{
    $webApp = $btn['web_app'] ?? null;
    if (is_array($webApp) && !empty($webApp['url'])) {
        return (string) $webApp['url'];
    }
    return (string) ($btn['url'] ?? '');
}

function vira_reply_markup_build_button(array $btn): array
{
    $text = trim((string) ($btn['text'] ?? ''));
    if ($text === '') {
        throw new ViraReplyMarkupError('متن دکمه الزامی است');
    }
    if (mb_strlen($text, 'UTF-8') > 64) {
        throw new ViraReplyMarkupError('متن دکمه حداکثر ۶۴ کاراکتر');
    }

    $btnType = vira_reply_markup_infer_type($btn);
    if (!in_array($btnType, vira_reply_markup_allowed_types(), true)) {
        throw new ViraReplyMarkupError('نوع دکمه نامعتبر است');
    }

    $out = ['text' => $text];
    $style = strtolower(trim((string) ($btn['style'] ?? '')));
    if ($style !== '') {
        if (!in_array($style, vira_reply_markup_allowed_styles(), true)) {
            throw new ViraReplyMarkupError('رنگ دکمه نامعتبر است');
        }
        $out['style'] = $style;
    }

    $icon = trim((string) ($btn['icon_custom_emoji_id'] ?? ''));
    if ($icon !== '') {
        $out['icon_custom_emoji_id'] = $icon;
    }

    if ($btnType === 'url') {
        $out['url'] = vira_reply_markup_validate_url((string) ($btn['url'] ?? ''));
    } elseif ($btnType === 'web_app') {
        $out['web_app'] = ['url' => vira_reply_markup_validate_url(vira_reply_markup_web_app_url($btn), true)];
    } else {
        $data = trim((string) ($btn['callback_data'] ?? $btn['data'] ?? ''));
        if ($data === '') {
            throw new ViraReplyMarkupError('callback_data برای دکمه Callback الزامی است');
        }
        if (strlen($data) > 64) {
            throw new ViraReplyMarkupError('callback_data حداکثر ۶۴ بایت');
        }
        $out['callback_data'] = $data;
    }

    return $out;
}

function vira_reply_markup_parse_rows(?string $raw): ?array
{
    if ($raw === null || trim($raw) === '') {
        return null;
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new ViraReplyMarkupError('JSON دکمه‌ها نامعتبر است');
    }
    if ($data === []) {
        return null;
    }

    $rowsRaw = $data['rows'] ?? $data;
    if (!is_array($rowsRaw) || $rowsRaw === []) {
        return null;
    }
    if (count($rowsRaw) > 20) {
        throw new ViraReplyMarkupError('حداکثر ۲۰ ردیف دکمه');
    }

    $rows = [];
    foreach ($rowsRaw as $row) {
        if (!is_array($row) || $row === []) {
            throw new ViraReplyMarkupError('هر ردیف باید لیست دکمه باشد');
        }
        if (count($row) > 8) {
            throw new ViraReplyMarkupError('حداکثر ۸ دکمه در هر ردیف');
        }
        $built = [];
        foreach ($row as $btn) {
            if (!is_array($btn)) {
                continue;
            }
            $built[] = vira_reply_markup_build_button($btn);
        }
        if ($built !== []) {
            $rows[] = $built;
        }
    }

    return $rows === [] ? null : $rows;
}

function vira_reply_markup_parse_rows_lenient(?string $raw): ?array
{
    if ($raw === null || trim($raw) === '') {
        return null;
    }
    try {
        return vira_reply_markup_parse_rows($raw);
    } catch (ViraReplyMarkupError $e) {
        $data = json_decode($raw, true);
        if (is_array($data) && isset($data['inline_keyboard']) && is_array($data['inline_keyboard'])) {
            return $data['inline_keyboard'];
        }
        throw $e;
    }
}

function vira_reply_markup_telegram_keyboard(?array $rows): ?array
{
    if ($rows === null || $rows === []) {
        return null;
    }
    return ['inline_keyboard' => $rows];
}

function vira_reply_markup_telegram_json(?string $raw): ?string
{
    $rows = vira_reply_markup_parse_rows_lenient($raw);
    $keyboard = vira_reply_markup_telegram_keyboard($rows);
    return $keyboard ? json_encode($keyboard, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
}

function vira_reply_markup_serialize_rows(?array $rows): ?string
{
    if ($rows === null || $rows === []) {
        return null;
    }
    return json_encode(['rows' => $rows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function vira_reply_markup_normalize_parse_mode(?string $value): ?string
{
    if ($value === null || trim($value) === '') {
        return 'HTML';
    }
    $mode = trim($value);
    if (in_array(strtolower($mode), ['none', 'off', 'plain'], true)) {
        return null;
    }
    if (!in_array($mode, ['HTML', 'Markdown', 'MarkdownV2'], true)) {
        throw new ViraReplyMarkupError('parse_mode باید HTML، Markdown، MarkdownV2 یا none باشد');
    }
    return $mode;
}
