<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/shop_settings_defs.php';
require_auth();

$groups = mirza_shop_settings_groups();
$toggles = mirza_shop_toggle_options();
$data = mirza_shop_load_values($pdo);
$shop = $data['shop'];
$setting = $data['setting'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $updated = 0;
    foreach ($toggles as $name => $_opts) {
        if (!isset($_POST['shop_' . $name]) && !isset($_POST['set_' . $name])) {
            continue;
        }
        $val = trim((string) ($_POST['shop_' . $name] ?? $_POST['set_' . $name] ?? ''));
        if (!isset($_opts[$val])) {
            continue;
        }
        if (isset($_POST['set_' . $name])) {
            if (!in_array($name, ['statuscategorygenral', 'statuscategory'], true)) {
                continue;
            }
            db_query($pdo, 'UPDATE setting SET `' . $name . '` = ?', [$val]);
        } else {
            $cnt = db_count($pdo, 'SELECT COUNT(*) FROM shopSetting WHERE Namevalue = ?', [$name]);
            if ($cnt > 0) {
                db_query($pdo, 'UPDATE shopSetting SET value = ? WHERE Namevalue = ?', [$val, $name]);
            } else {
                db_query($pdo, 'INSERT INTO shopSetting (Namevalue, value) VALUES (?, ?)', [$name, $val]);
            }
        }
        $updated++;
    }
    $numericKeys = ['customvolmef', 'customvolmen', 'customvolmen2', 'customtimepricef', 'customtimepricen', 'customtimepricen2', 'minbalancebuybulk', 'chashbackextend'];
    foreach ($numericKeys as $nk) {
        if (!isset($_POST['num_' . $nk])) {
            continue;
        }
        $val = trim((string) $_POST['num_' . $nk]);
        if ($val === '' || !ctype_digit($val)) {
            continue;
        }
        $cnt = db_count($pdo, 'SELECT COUNT(*) FROM shopSetting WHERE Namevalue = ?', [$nk]);
        if ($cnt > 0) {
            db_query($pdo, 'UPDATE shopSetting SET value = ? WHERE Namevalue = ?', [$val, $nk]);
        } else {
            db_query($pdo, 'INSERT INTO shopSetting (Namevalue, value) VALUES (?, ?)', [$nk, $val]);
        }
        $updated++;
    }
    flash('success', $updated > 0 ? 'تنظیمات فروشگاه ذخیره شد.' : 'تغییری ارسال نشد.');
    header('Location: shop-settings.php');
    exit;
}

$pageTitle = 'تنظیمات فروشگاه';
$pageLede = 'قابلیت‌ها، دسته‌بندی و قیمت‌گذاری — همان فیلدهای ادمین تلگرام (shopSetting + setting).';
$activeNav = 'shop-settings';
$extraCss = ['css/bot-settings.css', 'css/shop-settings.css'];
$extraJs = ['js/bot-settings.js'];
include __DIR__ . '/inc/layout_head.php';
?>

<form method="post" class="bot-settings-page shop-settings-page fade-up">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">

    <div class="bot-settings-toolbar">
        <div class="bot-settings-toolbar-links">
            <a href="product.php" class="btn btn-ghost btn-sm"><?= icon('package', 14) ?> محصولات</a>
            <a href="miniapp-templates.php" class="btn btn-ghost btn-sm"><?= icon('edit', 14) ?> قالب مینی‌اپ</a>
            <a href="bot-settings.php" class="btn btn-ghost btn-sm"><?= icon('settings', 14) ?> تنظیمات ربات</a>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">ذخیره</button>
    </div>

    <?php foreach ($groups as $title => $group): ?>
        <section class="card bot-settings-section">
            <div class="card-head">
                <div>
                    <div class="card-title"><?= htmlspecialchars($title) ?></div>
                    <?php if (!empty($group['desc'])): ?>
                        <div class="card-subtitle"><?= htmlspecialchars($group['desc']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body bot-settings-fields">
                <?php
                if (!empty($group['shop'])):
                    foreach ($group['shop'] as $name => $meta):
                        $val = (string) ($shop[$name] ?? '');
                        ?>
                        <div class="bot-settings-field">
                            <div class="bot-settings-toggle-row">
                                <span class="bot-settings-label"><?= htmlspecialchars($meta['label']) ?></span>
                                <div class="toggle-group">
                                    <?php foreach ($meta['options'] as $k => $lbl): ?>
                                        <label class="toggle-chip<?= (string) $val === (string) $k ? ' active' : '' ?>">
                                            <input type="radio" name="shop_<?= htmlspecialchars($name) ?>"
                                                value="<?= htmlspecialchars($k) ?>" <?= (string) $val === (string) $k ? 'checked' : '' ?>>
                                            <?= htmlspecialchars($lbl) ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach;
                endif;
                if (!empty($group['setting'])):
                    foreach ($group['setting'] as $name => $meta):
                        $val = (string) ($setting[$name] ?? '');
                        ?>
                        <div class="bot-settings-field">
                            <div class="bot-settings-toggle-row">
                                <span class="bot-settings-label"><?= htmlspecialchars($meta['label']) ?></span>
                                <div class="toggle-group">
                                    <?php foreach ($meta['options'] as $k => $lbl): ?>
                                        <label class="toggle-chip<?= (string) $val === (string) $k ? ' active' : '' ?>">
                                            <input type="radio" name="set_<?= htmlspecialchars($name) ?>"
                                                value="<?= htmlspecialchars($k) ?>" <?= (string) $val === (string) $k ? 'checked' : '' ?>>
                                            <?= htmlspecialchars($lbl) ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach;
                endif;
                if (!empty($group['numeric_shop'])):
                    foreach ($group['numeric_shop'] as $name => $label):
                        $val = (string) ($shop[$name] ?? '');
                        ?>
                        <label class="field">
                            <span class="field-label"><?= htmlspecialchars($label) ?></span>
                            <input type="number" name="num_<?= htmlspecialchars($name) ?>" class="field-input input"
                                value="<?= htmlspecialchars($val) ?>" min="0" step="1">
                        </label>
                    <?php endforeach;
                endif;
                ?>
            </div>
        </section>
    <?php endforeach; ?>

    <p class="shop-settings-note">کدهای هدیه/تخفیف و مدیریت محصول از منوی «محصولات» و بخش مالی قابل مشاهده‌اند. کش‌بک نمایندگان (JSON) فقط از تلگرام قابل ویرایش است.</p>

    <div class="bot-settings-footer">
        <button type="submit" class="btn btn-primary">ذخیره تنظیمات فروشگاه</button>
    </div>
</form>

<?php include __DIR__ . '/inc/layout_foot.php';
