<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/bot_data.php';
require_once __DIR__ . '/inc/bot_emojis.php';
require_auth();

$catalog = vira_panel_keyboard_catalog();
$datatextbot = vira_panel_load_datatextbot($pdo);
$buttonStyles = vira_keyboard_button_styles();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $action = $_POST['action'] ?? 'save';
    if ($action === 'reset') {
        $default = json_decode(vira_panel_default_keyboard_json(), true);
        vira_panel_save_keyboardmain($pdo, $default['keyboard']);
        flash('success', 'چیدمان به حالت پیش‌فرض بازگشت.');
        header('Location: keyboard.php');
        exit;
    }
    $raw = $_POST['keyboard_json'] ?? '[]';
    $rows = json_decode($raw, true);
    if (!is_array($rows)) {
        flash('error', 'داده نامعتبر است.');
        header('Location: keyboard.php');
        exit;
    }
    $allowed = array_keys($catalog);
    $allowedStyles = ['primary', 'success', 'danger'];
    $clean = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $line = [];
        foreach ($row as $key) {
            $textKey = is_array($key) ? (string) ($key['text'] ?? '') : (string) $key;
            if (!in_array($textKey, $allowed, true)) {
                continue;
            }
            $entry = ['text' => $textKey];
            if (is_array($key)) {
                $style = trim((string) ($key['style'] ?? ''));
                if ($style !== '' && in_array($style, $allowedStyles, true)) {
                    $entry['style'] = $style;
                }
            }
            $line[] = $entry;
        }
        if ($line !== []) {
            $clean[] = $line;
        }
    }
    if ($clean === []) {
        flash('error', 'حداقل یک دکمه در منو لازم است.');
        header('Location: keyboard.php');
        exit;
    }
    vira_panel_save_keyboardmain($pdo, $clean);
    flash('success', 'چیدمان منوی استارت ذخیره شد.');
    header('Location: keyboard.php');
    exit;
}

$keyboardData = vira_panel_load_keyboardmain($pdo);
$usedKeys = [];
foreach ($keyboardData['keyboard'] as $row) {
    foreach ($row as $btn) {
        if (!empty($btn['text'])) {
            $usedKeys[$btn['text']] = true;
        }
    }
}
$availableKeys = [];
foreach ($catalog as $id => $title) {
    if (!isset($usedKeys[$id])) {
        $availableKeys[$id] = vira_panel_keyboard_label($id, $datatextbot, $catalog);
    }
}

$pageTitle = 'چیدمان منوی استارت';
$pageLede = 'چیدمان و استایل دکمه‌ها — متن و ایموجی از «متن‌های ربات»';
$activeNav = 'keyboard';
$extraCss = ['css/keyboard.css'];
$extraJs = ['js/keyboard.js'];
include __DIR__ . '/inc/layout_head.php';
?>

<div class="kb-layout fade-up">
    <div class="card kb-board-card">
        <div class="card-head">
            <div>
                <div class="card-title">پیش‌نمایش منوی کاربر</div>
                <div class="card-subtitle">متن دکمه‌ها از «متن‌های ربات» · اینجا فقط چیدمان و استایل</div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <a href="bot-texts.php" class="btn btn-ghost btn-sm">متن و ایموجی دکمه‌ها</a>
                <a href="bot-emojis.php" class="btn btn-ghost btn-sm">کتابخانه ایموجی</a>
                <form method="post" style="display:inline" onsubmit="return confirm('بازگشت به چیدمان پیش‌فرض؟');">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="action" value="reset">
                    <button type="submit" class="btn btn-ghost btn-sm">بازنشانی پیش‌فرض</button>
                </form>
                <a href="bot.php" class="btn btn-ghost btn-sm">مرکز ربات</a>
            </div>
        </div>
        <div class="card-body">
            <p class="field-hint" style="margin-bottom:12px">
                چیدمان دکمه‌ها مثل تلگرام است: <b>چپ</b> به <b>راست</b>.
                رنگ دکمه (تلگرام Bot API 9.4+): <b>آبی</b> · <b>سبز</b> · <b>قرمز</b> — یا پیش‌فرض بدون رنگ.
                متن: حداکثر یک <code>{emoji:slug}</code>
            </p>
            <div id="kbPreview" class="kb-preview">
                <?php foreach ($keyboardData['keyboard'] as $ri => $row): ?>
                    <div class="kb-row" data-row="<?= (int) $ri ?>">
                        <?php foreach ($row as $btn):
                            $kid = $btn['text'] ?? '';
                            $label = vira_panel_keyboard_label($kid, $datatextbot, $catalog);
                            $btnStyle = (string) ($btn['style'] ?? '');
                            ?>
                            <div class="kb-btn" draggable="true"
                                data-key="<?= htmlspecialchars($kid) ?>"
                                data-label="<?= htmlspecialchars($label) ?>"
                                data-style="<?= htmlspecialchars($btnStyle) ?>">
                                <span class="kb-btn-label"><?= htmlspecialchars($label) ?></span>
                                <span class="kb-btn-id"><?= htmlspecialchars($kid) ?></span>
                                <div class="kb-btn-meta kb-btn-meta-style-only">
                                    <label class="kb-mini-label">رنگ دکمه</label>
                                    <select class="kb-style-select input input-sm">
                                        <?php foreach ($buttonStyles as $styleVal => $styleLabel): ?>
                                            <option value="<?= htmlspecialchars($styleVal) ?>"<?= $btnStyle === $styleVal ? ' selected' : '' ?>>
                                                <?= htmlspecialchars($styleLabel) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="button" class="kb-btn-remove" title="حذف" aria-label="حذف">&times;</button>
                            </div>
                        <?php endforeach; ?>
                        <button type="button" class="kb-row-add" title="افزودن دکمه به این ردیف">+</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-ghost btn-sm" id="kbAddRow" style="margin-top:12px">+ ردیف جدید</button>
        </div>
    </div>

    <div class="kb-side">
        <div class="card fade-up d1">
            <div class="card-head">
                <div class="card-title">دکمه‌های قابل افزودن</div>
            </div>
            <div class="card-body">
                <p class="field-hint" style="margin-bottom:12px">روی دکمه کلیک کنید یا به ردیف منو بکشید.</p>
                <div id="kbPalette" class="kb-palette">
                    <?php if ($availableKeys === []): ?>
                        <p class="cf kb-palette-empty" style="font-size:.82rem">همه دکمه‌ها در منو هستند.</p>
                    <?php else: ?>
                        <?php foreach ($availableKeys as $id => $label): ?>
                            <div class="kb-palette-item" draggable="true" data-key="<?= htmlspecialchars($id) ?>" data-label="<?= htmlspecialchars($label) ?>">
                                <span><?= htmlspecialchars($label) ?></span>
                                <code><?= htmlspecialchars($id) ?></code>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <form method="post" id="kbSaveForm" class="card fade-up d2" style="margin-top:14px">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="keyboard_json" id="keyboardJson" value="">
            <div class="card-body" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
                <p class="field-hint" style="margin:0">پس از ذخیره، منو برای کاربران به‌روز می‌شود.</p>
                <button type="submit" class="btn btn-primary">ذخیره چیدمان</button>
            </div>
        </form>
    </div>
</div>

<script>
window.KB_STYLE_OPTIONS = <?= json_encode($buttonStyles, JSON_UNESCAPED_UNICODE) ?>;
window.KB_STYLE_COLORS = <?= json_encode(vira_keyboard_style_colors(), JSON_UNESCAPED_UNICODE) ?>;
</script>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>
