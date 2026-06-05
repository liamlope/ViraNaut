<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_auth();

if (function_exists('mirza_ensure_marzban_panel_columns')) {
    mirza_ensure_marzban_panel_columns();
}

$setting = db_fetch($pdo, 'SELECT limit_usertest_all, status_usertest FROM setting LIMIT 1') ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $action = $_POST['action'] ?? 'global';

    if ($action === 'global') {
        $limit = trim((string) ($_POST['limit_usertest_all'] ?? '0'));
        if ($limit === '' || !ctype_digit($limit)) {
            $limit = '0';
        }
        $status = ($_POST['status_usertest'] ?? 'offtest') === 'ontest' ? 'ontest' : 'offtest';
        $exists = db_count($pdo, 'SELECT COUNT(*) FROM setting');
        if ($exists > 0) {
            try {
                db_query($pdo, 'UPDATE setting SET limit_usertest_all = ?, status_usertest = ?', [$limit, $status]);
            } catch (Exception $e) {
                db_query($pdo, 'UPDATE setting SET limit_usertest_all = ?', [$limit]);
            }
            flash('success', 'تنظیمات کلی اکانت تست ذخیره شد.');
        } else {
            flash('warning', 'رکورد setting وجود ندارد.');
        }
    }

    if ($action === 'panels_bulk' && !empty($_POST['panels']) && is_array($_POST['panels'])) {
        $saved = 0;
        foreach ($_POST['panels'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['name_panel'] ?? ''));
            if ($name === '') {
                continue;
            }
            $time = max(0, (int) ($row['time_usertest'] ?? 0));
            $vol = trim((string) ($row['val_usertest'] ?? '0'));
            if ($vol === '' || !is_numeric($vol)) {
                $vol = '0';
            }
            $testFlag = ($row['TestAccount'] ?? 'OFFTestAccount') === 'ONTestAccount' ? 'ONTestAccount' : 'OFFTestAccount';
            $status = (($row['status'] ?? 'deactive') === 'active') ? 'active' : 'deactive';
            db_query(
                $pdo,
                'UPDATE marzban_panel SET time_usertest = ?, val_usertest = ?, TestAccount = ?, status = ? WHERE name_panel = ?',
                [$time, $vol, $testFlag, $status, $name]
            );
            $saved++;
        }
        if ($saved > 0) {
            flash('success', 'تنظیمات ' . $saved . ' پنل ذخیره شد.');
        } else {
            flash('warning', 'پنلی برای ذخیره یافت نشد.');
        }
    }

    header('Location: test-settings.php');
    exit;
}

$panels = [];
try {
    $panels = db_fetchAll(
        $pdo,
        'SELECT name_panel, TestAccount, time_usertest, val_usertest, status FROM marzban_panel ORDER BY name_panel ASC'
    );
} catch (Exception $e) {
    flash('error', $e->getMessage());
}

$testOn = (($setting['status_usertest'] ?? 'ontest') !== 'offtest');
$limitAll = (string) ($setting['limit_usertest_all'] ?? '1');

$panelsTestOn = 0;
$panelsActive = 0;
foreach ($panels as $p) {
    if (mirza_panel_is_active_status($p['status'] ?? '')) {
        $panelsActive++;
    }
    if (($p['TestAccount'] ?? '') === 'ONTestAccount') {
        $panelsTestOn++;
    }
}

$pageTitle = 'اکانت تست';
$pageLede = 'فعال‌سازی سرویس تست، سقف مصرف کاربران و تنظیمات هر پنل (حجم، مدت و وضعیت).';
$activeNav = 'test-settings';
$extraCss = ['css/bot-settings.css', 'css/test-settings.css'];
$extraJs = ['js/bot-settings.js', 'js/test-settings.js'];
include __DIR__ . '/inc/layout_head.php';
?>

<div class="test-settings-page fade-up">
    <div class="test-settings-toolbar">
        <div class="test-settings-toolbar-links">
            <a href="bot-settings.php" class="btn btn-ghost btn-sm"><?= icon('settings', 14) ?> تنظیمات ربات</a>
            <a href="panels.php" class="btn btn-ghost btn-sm"><?= icon('server', 14) ?> مدیریت پنل‌ها</a>
        </div>
    </div>

    <div class="test-settings-stats">
        <div class="test-stat-card">
            <div class="test-stat-label">وضعیت کلی تست</div>
            <div class="test-stat-value <?= $testOn ? 'is-on' : 'is-off' ?>"><?= $testOn ? 'فعال' : 'غیرفعال' ?></div>
        </div>
        <div class="test-stat-card">
            <div class="test-stat-label">سقف تست هر کاربر جدید</div>
            <div class="test-stat-value"><?= htmlspecialchars($limitAll) ?></div>
        </div>
        <div class="test-stat-card">
            <div class="test-stat-label">پنل‌های دارای تست</div>
            <div class="test-stat-value"><?= (int) $panelsTestOn ?> <span style="font-size:0.75rem;font-weight:600;color:var(--mute)">از <?= count($panels) ?></span></div>
        </div>
    </div>

    <form method="post" class="card test-global-card">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="action" value="global">
        <div class="card-head">
            <div>
                <div class="card-title bot-settings-section-title">
                    <span class="bot-settings-section-icon"><?= icon('user', 18) ?></span>
                    تنظیمات کلی
                </div>
                <div class="card-subtitle">کنترل دسترسی کاربران به دکمه «اکانت تست» در ربات</div>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">ذخیره</button>
        </div>
        <div class="card-body">
            <div class="test-global-toggle">
                <div class="bot-settings-toggle-row" style="margin:0">
                    <div class="bot-settings-toggle-meta">
                        <span class="bot-settings-label">قابلیت اکانت تست در ربات</span>
                        <span class="field-hint">در حالت خاموش، هیچ کاربری (غیر از ادمین) نمی‌تواند تست بگیرد.</span>
                    </div>
                    <div class="toggle-group" role="group" aria-label="وضعیت اکانت تست">
                        <label class="toggle-chip<?= $testOn ? ' active' : '' ?>">
                            <input type="radio" name="status_usertest" value="ontest" <?= $testOn ? 'checked' : '' ?>> فعال
                        </label>
                        <label class="toggle-chip<?= !$testOn ? ' active' : '' ?>">
                            <input type="radio" name="status_usertest" value="offtest" <?= !$testOn ? 'checked' : '' ?>> خاموش
                        </label>
                    </div>
                </div>
            </div>
            <div class="test-global-fields">
                <label class="field">
                    <span class="field-label">تعداد مجاز تست برای هر کاربر</span>
                    <input type="number" name="limit_usertest_all" class="input" min="0" step="1"
                        value="<?= htmlspecialchars($limitAll) ?>">
                    <span class="field-hint">هنگام ثبت‌نام به فیلد limit_usertest کاربر اعمال می‌شود. ادمین‌ها محدودیت ندارند.</span>
                </label>
                <p class="field-hint" style="margin:0">برای تغییر تست یک کاربر خاص از پروفایل کاربر یا دکمه «محدودیت اکانت تست» در تلگرام استفاده کنید.</p>
            </div>
        </div>
    </form>

    <form method="post" class="card" style="margin-top:16px" id="testPanelsForm">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="action" value="panels_bulk">
        <div class="card-head test-panels-head">
            <div>
                <div class="card-title bot-settings-section-title">
                    <span class="bot-settings-section-icon"><?= icon('server', 18) ?></span>
                    تنظیمات تست هر پنل
                </div>
                <div class="card-subtitle">دو تنظیم جدا: <strong>فروش/خرید</strong> (فعال/غیرفعال) و <strong>اکانت تست رایگان</strong> (روشن/خاموش).</div>
            </div>
        </div>

        <?php if ($panels === []): ?>
            <div class="test-panels-empty">
                <p>هنوز پنلی ثبت نشده است.</p>
                <a href="panels.php" class="btn btn-primary btn-sm"><?= icon('plus', 14) ?> افزودن پنل</a>
            </div>
        <?php else: ?>
            <div class="test-panels-grid">
                <?php foreach ($panels as $i => $p):
                    $name = (string) ($p['name_panel'] ?? '');
                    $panelActive = mirza_panel_is_active_status($p['status'] ?? '');
                    $testActive = ($p['TestAccount'] ?? '') === 'ONTestAccount';
                    $timeH = (int) ($p['time_usertest'] ?? 0);
                    $volMb = (string) ($p['val_usertest'] ?? '');
                    $prefix = 'panels[' . $i . ']';
                    ?>
                    <article class="test-panel-card<?= $panelActive ? '' : ' is-inactive' ?>">
                        <input type="hidden" name="<?= $prefix ?>[name_panel]" value="<?= htmlspecialchars($name) ?>">
                        <div class="test-panel-top">
                            <div class="test-panel-name"><?= htmlspecialchars($name) ?></div>
                        </div>

                        <div class="test-panel-field-full">
                            <span class="field-label">فروش و خرید از این پنل</span>
                            <span class="field-hint" style="display:block;margin:0 0 8px">فعال = در لیست موقعیت‌ها هنگام خرید دیده می‌شود · غیرفعال = مخفی از فروش</span>
                            <div class="toggle-group" role="group" aria-label="فروش از پنل">
                                <label class="toggle-chip<?= $panelActive ? ' active' : '' ?>">
                                    <input type="radio" name="<?= $prefix ?>[status]" value="active" <?= $panelActive ? 'checked' : '' ?>> فعال
                                </label>
                                <label class="toggle-chip<?= !$panelActive ? ' active' : '' ?>">
                                    <input type="radio" name="<?= $prefix ?>[status]" value="deactive" <?= !$panelActive ? 'checked' : '' ?>> غیرفعال
                                </label>
                            </div>
                        </div>

                        <div class="test-panel-field-full">
                            <span class="field-label">اکانت تست رایگان</span>
                            <span class="field-hint" style="display:block;margin:0 0 8px">مستقل از فروش — فقط دکمه «اکانت تست» در ربات</span>
                            <div class="toggle-group" role="group" aria-label="اکانت تست">
                                <label class="toggle-chip<?= $testActive ? ' active' : '' ?>">
                                    <input type="radio" name="<?= $prefix ?>[TestAccount]" value="ONTestAccount" <?= $testActive ? 'checked' : '' ?>> روشن
                                </label>
                                <label class="toggle-chip<?= !$testActive ? ' active' : '' ?>">
                                    <input type="radio" name="<?= $prefix ?>[TestAccount]" value="OFFTestAccount" <?= !$testActive ? 'checked' : '' ?>> خاموش
                                </label>
                            </div>
                        </div>

                        <div class="test-panel-fields">
                            <label class="test-panel-field field">
                                <span class="field-label">مدت (ساعت)</span>
                                <input type="number" name="<?= $prefix ?>[time_usertest]" class="input" min="0" step="1"
                                    value="<?= htmlspecialchars((string) $timeH) ?>">
                            </label>
                            <label class="test-panel-field field">
                                <span class="field-label">حجم (مگابایت)</span>
                                <input type="number" name="<?= $prefix ?>[val_usertest]" class="input" min="0" step="1"
                                    value="<?= htmlspecialchars($volMb) ?>">
                            </label>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <div class="test-panels-footer">
                <span class="field-hint" style="margin:0"><?= count($panels) ?> پنل — <?= (int) $panelsActive ?> فعال (فروش)، <?= (int) $panelsTestOn ?> با تست روشن</span>
                <button type="submit" class="btn btn-primary">ذخیره همه پنل‌ها</button>
            </div>
        <?php endif; ?>
    </form>
</div>

<?php include __DIR__ . '/inc/layout_foot.php';
