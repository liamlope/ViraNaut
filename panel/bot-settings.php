<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/bot_settings_defs.php';
require_auth();

$groups = mirza_bot_settings_groups();
$fields = mirza_bot_settings_flat_fields();

$setting = [];
try {
    $setting = db_fetch($pdo, 'SELECT * FROM setting LIMIT 1') ?? [];
} catch (Exception $e) {
    error_log('bot-settings.php: ' . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $sets = [];
    $params = [];
    foreach ($fields as $col => $meta) {
        if (!isset($_POST[$col])) {
            continue;
        }
        $val = trim((string) $_POST[$col]);
        if (($meta['type'] ?? '') === 'toggle' || ($meta['type'] ?? '') === 'select') {
            if (!isset($meta['options'][$val])) {
                continue;
            }
        }
        $sets[] = "`$col` = ?";
        $params[] = $val;
    }
    $cron = [];
    foreach (mirza_bot_cron_defs() as $ck => $_) {
        if (isset($_POST['cron_' . $ck])) {
            $cron[$ck] = $_POST['cron_' . $ck] === '1' || $_POST['cron_' . $ck] === 'true';
        }
    }
    if ($cron !== []) {
        $sets[] = '`cron_status` = ?';
        $params[] = json_encode($cron, JSON_UNESCAPED_UNICODE);
    }
    if ($sets !== []) {
        $exists = db_count($pdo, 'SELECT COUNT(*) FROM setting');
        if ($exists > 0) {
            db_query($pdo, 'UPDATE setting SET ' . implode(', ', $sets), $params);
            flash('success', 'تنظیمات ربات ذخیره شد.');
        } else {
            flash('warning', 'رکورد setting وجود ندارد؛ ابتدا ربات را یک‌بار از تلگرام راه‌اندازی کنید.');
        }
    }
    header('Location: bot-settings.php');
    exit;
}

$pageTitle = 'تنظیمات ربات';
$pageLede = 'گزینه‌های پرکاربرد ربات — گروه‌بندی شده و هم‌سو با پنل مدیریت.';
$activeNav = 'bot-settings';
$extraCss = ['css/bot-settings.css'];
$extraJs = ['js/bot-settings.js'];
include __DIR__ . '/inc/layout_head.php';
?>

<form method="post" id="botSettingsForm" class="bot-settings-page fade-up">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">

    <div class="bot-settings-toolbar">
        <div class="bot-settings-toolbar-links">
            <a href="bot.php" class="btn btn-ghost btn-sm"><?= icon('bot', 14) ?> مرکز ربات</a>
            <a href="shop-settings.php" class="btn btn-ghost btn-sm"><?= icon('package', 14) ?> تنظیمات فروشگاه</a>
            <a href="keyboard.php" class="btn btn-ghost btn-sm">چیدمان منو</a>
            <a href="test-settings.php" class="btn btn-ghost btn-sm">اکانت تست</a>
            <a href="channels.php" class="btn btn-ghost btn-sm">جوین اجباری</a>
            <a href="reports-settings.php" class="btn btn-ghost btn-sm">گزارش‌ها</a>
            <a href="admins.php" class="btn btn-ghost btn-sm">ادمین‌ها</a>
            <a href="broadcast.php" class="btn btn-ghost btn-sm">ارسال همگانی</a>
            <a href="backup.php" class="btn btn-ghost btn-sm">بکاپ</a>
            <a href="optimize.php" class="btn btn-ghost btn-sm">بهینه‌سازی</a>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">ذخیره تنظیمات</button>
    </div>

    <div class="bot-settings-grid">
        <?php foreach ($groups as $groupTitle => $group):
            $iconName = $group['icon'] ?? 'settings';
            ?>
            <section class="card bot-settings-section">
                <div class="card-head">
                    <div>
                        <div class="card-title bot-settings-section-title">
                            <span class="bot-settings-section-icon"><?= icon($iconName, 18) ?></span>
                            <?= htmlspecialchars($groupTitle) ?>
                        </div>
                        <?php if (!empty($group['desc'])): ?>
                            <div class="card-subtitle"><?= htmlspecialchars($group['desc']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body bot-settings-fields">
                    <?php foreach ($group['fields'] as $col => $meta):
                        $val = (string) ($setting[$col] ?? '');
                        $type = $meta['type'] ?? 'text';
                        ?>
                        <div class="bot-settings-field" data-field="<?= htmlspecialchars($col) ?>">
                            <?php if ($type === 'toggle'): ?>
                                <div class="bot-settings-toggle-row">
                                    <div class="bot-settings-toggle-meta">
                                        <span class="bot-settings-label"><?= htmlspecialchars($meta['label']) ?></span>
                                        <?php if (!empty($meta['hint'])): ?>
                                            <span class="field-hint"><?= htmlspecialchars($meta['hint']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="toggle-group" role="group" aria-label="<?= htmlspecialchars($meta['label']) ?>">
                                        <?php foreach ($meta['options'] as $k => $lbl):
                                            $active = (string) $val === (string) $k;
                                            ?>
                                            <label class="toggle-chip<?= $active ? ' active' : '' ?>">
                                                <input type="radio" name="<?= htmlspecialchars($col) ?>"
                                                    value="<?= htmlspecialchars($k) ?>" <?= $active ? 'checked' : '' ?>>
                                                <?= htmlspecialchars($lbl) ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <label class="field">
                                    <span class="field-label"><?= htmlspecialchars($meta['label']) ?></span>
                                    <input type="<?= $type === 'number' ? 'number' : 'text' ?>"
                                        name="<?= htmlspecialchars($col) ?>" class="input"
                                        value="<?= htmlspecialchars($val) ?>"
                                        <?= !empty($meta['hint']) ? 'placeholder="' . htmlspecialchars($meta['hint']) . '"' : '' ?>>
                                    <?php if (!empty($meta['hint'])): ?>
                                        <span class="field-hint"><?= htmlspecialchars($meta['hint']) ?></span>
                                    <?php endif; ?>
                                </label>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>

        <?php
        $cronStatus = json_decode((string) ($setting['cron_status'] ?? '{}'), true) ?: [];
        ?>
        <section class="card bot-settings-section">
            <div class="card-head">
                <div class="card-title bot-settings-section-title">
                    <span class="bot-settings-section-icon"><?= icon('chart', 18) ?></span>
                    کرون و هشدارها
                </div>
                <div class="card-subtitle">هم‌تراز با منوی وضعیت قابلیت‌ها در تلگرام</div>
            </div>
            <div class="card-body bot-settings-fields">
                <?php foreach (mirza_bot_cron_defs() as $ck => $clbl):
                    $on = !empty($cronStatus[$ck]);
                    ?>
                    <div class="bot-settings-toggle-row">
                        <span class="bot-settings-label"><?= htmlspecialchars($clbl) ?></span>
                        <div class="toggle-group">
                            <label class="toggle-chip<?= $on ? ' active' : '' ?>">
                                <input type="radio" name="cron_<?= htmlspecialchars($ck) ?>" value="1" <?= $on ? 'checked' : '' ?>> فعال
                            </label>
                            <label class="toggle-chip<?= !$on ? ' active' : '' ?>">
                                <input type="radio" name="cron_<?= htmlspecialchars($ck) ?>" value="0" <?= !$on ? 'checked' : '' ?>> خاموش
                            </label>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>

    <div class="bot-settings-footer">
        <button type="submit" class="btn btn-primary">ذخیره تنظیمات</button>
    </div>
</form>

<?php include __DIR__ . '/inc/layout_foot.php';
