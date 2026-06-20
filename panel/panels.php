<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_auth();

if (function_exists('mirza_ensure_marzban_panel_columns')) {
    mirza_ensure_marzban_panel_columns();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_xui') {
    csrf_check_post();
    $namePanel = trim((string) ($_POST['name_panel'] ?? ''));
    $urlPanel = trim((string) ($_POST['url_panel'] ?? ''));
    $linksubx = trim((string) ($_POST['linksubx'] ?? ''));
    $userPanel = trim((string) ($_POST['username_panel'] ?? ''));
    $passPanel = trim((string) ($_POST['password_panel'] ?? ''));
    $token = trim((string) ($_POST['xui_api_token'] ?? ''));
    $limitPanel = trim((string) ($_POST['limit_panel'] ?? '0'));
    $agent = trim((string) ($_POST['agent'] ?? 'all'));

    if ($namePanel === '' || $urlPanel === '') {
        flash('error', 'نام پنل و آدرس API الزامی است.');
        header('Location: panels.php');
        exit;
    }
    if (db_count($pdo, 'SELECT COUNT(*) FROM marzban_panel WHERE name_panel = ?', [$namePanel]) > 0) {
        flash('error', 'پنلی با این نام وجود دارد.');
        header('Location: panels.php');
        exit;
    }
    if (function_exists('mirza_normalize_xui_panel_url')) {
        $urlPanel = mirza_normalize_xui_panel_url($urlPanel);
    }
    if ($linksubx === '') {
        $linksubx = $urlPanel;
    }
    $codePanel = bin2hex(random_bytes(2));
    $value = json_encode(['f' => '4000', 'n' => '4000', 'n2' => '4000']);
    $valuemain = json_encode(['f' => '1', 'n' => '1', 'n2' => '1']);
    $valuemax = json_encode(['f' => '1000', 'n' => '1000', 'n2' => '1000']);
    $customVol = json_encode(['f' => '0', 'n' => '0', 'n2' => '0']);
    $tokenDb = ($token === '' || $token === '-') ? null : $token;

    try {
        db_query(
            $pdo,
            "INSERT INTO marzban_panel (
                code_panel, name_panel, sublink, config, MethodUsername, TestAccount, status, limit_panel,
                namecustom, Methodextend, type, conecton, inboundid, agent, inbound_deactive, inboundstatus,
                url_panel, username_panel, password_panel, time_usertest, val_usertest, linksubx,
                priceextravolume, priceextratime, pricecustomvolume, pricecustomtime,
                mainvolume, maxvolume, maintime, maxtime, status_extend, subvip, changeloc, customvolume,
                on_hold_test, version_panel, xui_api_token
            ) VALUES (
                ?, ?, 'onsublink', 'offconfig', 'آیدی عددی + حروف و عدد رندوم', 'ONTestAccount', 'active', ?,
                'none', 'ریست حجم و زمان', 'x-ui_single', 'offconecton', '1', ?, '1', 'offinbounddisable',
                ?, ?, ?, '1', '100', ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                'on_extend', 'offsubvip', 'offchangeloc', ?,
                '1', '0', ?
            )",
            [
                $codePanel, $namePanel, $limitPanel, $agent,
                $urlPanel, $userPanel, $passPanel, $linksubx,
                $value, $value, $value, $value,
                $valuemain, $valuemax, $valuemain, $valuemax,
                $customVol, $tokenDb,
            ]
        );
        flash('success', 'پنل ۳x-ui «' . $namePanel . '» اضافه شد. اینباندها را از تلگرام تنظیم کنید.');
    } catch (Exception $e) {
        flash('error', 'خطا: ' . $e->getMessage());
    }
    header('Location: panels.php');
    exit;
}

$panels = [];
try {
    $panels = db_fetchAll(
        $pdo,
        'SELECT name_panel, type, url_panel, status, limit_panel, agent, version_panel, xui_api_token, linksubx, inboundid, inbounds, username_panel FROM marzban_panel ORDER BY name_panel ASC'
    );
} catch (Exception $e) {
    error_log('panels.php list: ' . $e->getMessage());
    try {
        $panels = db_fetchAll(
            $pdo,
            'SELECT name_panel, type, url_panel, status, limit_panel, agent, version_panel, linksubx, inboundid FROM marzban_panel ORDER BY name_panel ASC'
        );
    } catch (Exception $e2) {
        error_log('panels.php list fallback: ' . $e2->getMessage());
    }
}

$typeLabels = [
    'marzban' => 'Marzban',
    'pasarguard' => 'Pasarguard',
    'mirza_agent' => 'Mirza Agent',
    'ilan' => 'Ilan',
    'x-ui_single' => '3x-ui',
    'x-ui' => '3x-ui',
    's_ui' => 'S-UI',
    'alireza_single' => 'Alireza',
    'alireza' => 'Alireza',
    'wgdashboard' => 'WG',
    'ibsng' => 'IBSng',
];

$pageTitle = 'پنل‌های VPN';
$pageLede = 'افزودن پنل ۳x-ui از وب؛ سایر انواع از ربات تلگرام.';
$activeNav = 'panels';
$extraCss = ['css/panels-admin.css', 'css/product-inbounds.css'];
$extraJs = ['js/inbound-picker.js', 'js/panels.js'];
include __DIR__ . '/inc/layout_head.php';
?>

<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap" class="fade-up">
    <a href="bot.php" class="btn btn-ghost btn-sm">مرکز ربات</a>
    <button type="button" class="btn btn-primary btn-sm" onclick="openModal('addXuiModal')"><?= icon('plus', 14) ?> افزودن پنل ۳x-ui</button>
</div>

<div class="card fade-up">
    <div class="card-head">
        <div>
            <div class="card-title">فهرست پنل‌ها</div>
            <div class="card-subtitle"><?= count($panels) ?> پنل ثبت‌شده</div>
        </div>
    </div>
    <div class="tbl-wrap">
        <table class="tbl">
            <thead>
                <tr>
                    <th>نام</th>
                    <th>نوع</th>
                    <th>وضعیت</th>
                    <th>اتصال</th>
                    <th>نماینده</th>
                    <th>اینباند</th>
                    <th>آدرس</th>
                    <th>API</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($panels === []): ?>
                    <tr>
                        <td colspan="9">
                            <div class="empty" style="padding:28px">
                                <p>پنلی ثبت نشده. «افزودن پنل ۳x-ui» را بزنید یا از ربات تلگرام اضافه کنید.</p>
                            </div>
                        </td>
                    </tr>
                <?php else:
                    foreach ($panels as $p):
                        $type = $p['type'] ?? '';
                        $typeLabel = $typeLabels[$type] ?? $type;
                        $active = mirza_panel_is_active_status($p['status'] ?? '');
                        $hasToken = !empty(trim((string) ($p['xui_api_token'] ?? '')));
                        $url = $p['url_panel'] ?? '';
                        $ib = $p['inbounds'] ?? '';
                        if ($ib === '' || $ib === 'null') {
                            $ib = $p['inboundid'] ?? '—';
                        }
                        ?>
                        <tr>
                            <td class="cs"><?= htmlspecialchars($p['name_panel'] ?? '') ?></td>
                            <td><span class="tag"><?= htmlspecialchars($typeLabel) ?></span></td>
                            <td>
                                <?php if ($active): ?>
                                    <span class="tag tag-ok">فعال</span>
                                <?php else: ?>
                                    <span class="tag tag-no">غیرفعال</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($type === 'x-ui_single' || $type === 'x-ui' || $type === 'alireza_single'): ?>
                                    <span class="tag" id="conn-<?= htmlspecialchars($p['name_panel'], ENT_QUOTES) ?>">—</span>
                                    <button type="button" class="btn btn-ghost btn-sm" style="margin-top:4px;font-size:.68rem"
                                        data-panel-test="<?= htmlspecialchars($p['name_panel'], ENT_QUOTES) ?>">تست</button>
                                <?php else: ?>
                                    <span class="cf">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="cf"><?= htmlspecialchars($p['agent'] ?? '—') ?></td>
                            <td class="cm" style="font-size:.72rem;max-width:80px"><?= htmlspecialchars(trunc((string) $ib, 18)) ?></td>
                            <td class="cm" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;direction:ltr;text-align:left;font-size:.75rem" title="<?= htmlspecialchars($url) ?>">
                                <?= htmlspecialchars(trunc($url, 42)) ?>
                            </td>
                            <td>
                                <?php if ($type === 'x-ui_single' || $type === 'x-ui'): ?>
                                    <?= $hasToken ? '<span class="tag tag-ok">توکن</span>' : '<span class="tag tag-warn">بدون توکن</span>' ?>
                                <?php else: ?>
                                    <span class="cf">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($type === 'x-ui_single' || $type === 'x-ui' || $type === 'alireza_single'): ?>
                                    <button type="button" class="btn btn-ghost btn-sm btn-icon" title="ویرایش"
                                        onclick="openEditPanelModal(<?= htmlspecialchars(json_encode($p['name_panel']), ENT_QUOTES) ?>)">
                                        <?= icon('edit', 13) ?>
                                    </button>
                                <?php else: ?>
                                    <span class="cf" title="از ربات تلگرام">ربات</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach;
                endif; ?>
            </tbody>
        </table>
    </div>
</div>

<p class="field-hint fade-up d1" style="margin-top:14px">
    آدرس API بدون <code>/panel</code> در انتها. توکن: <b>Settings → Security → API Token</b> در 3x-ui.
</p>

<div class="modal-veil" id="addXuiModal">
    <div class="modal" style="max-width:520px">
        <div class="modal-head">
            <h3>افزودن پنل ۳x-ui</h3>
            <button type="button" class="modal-x" onclick="closeModal('addXuiModal')"><?= icon('close', 14) ?></button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="add_xui">
                <div class="form-grid">
                    <div class="field full">
                        <label>نام پنل (در ربات) *</label>
                        <input type="text" name="name_panel" class="input" required placeholder="مثلاً 💰اقتصادی💰">
                    </div>
                    <div class="field full">
                        <label>آدرس API پنل *</label>
                        <input type="url" name="url_panel" class="input" dir="ltr" required placeholder="https://panel.example.com/path">
                    </div>
                    <div class="field full">
                        <label>دامنه لینک ساب</label>
                        <input type="url" name="linksubx" class="input" dir="ltr" placeholder="خالی = همان آدرس API">
                    </div>
                    <div class="field">
                        <label>نام کاربری</label>
                        <input type="text" name="username_panel" class="input" dir="ltr">
                    </div>
                    <div class="field">
                        <label>رمز</label>
                        <input type="password" name="password_panel" class="input" dir="ltr">
                    </div>
                    <div class="field full">
                        <label>توکن API (اختیاری)</label>
                        <input type="text" name="xui_api_token" class="input" dir="ltr" placeholder="از Security → API Token">
                    </div>
                    <div class="field">
                        <label>محدودیت ساخت</label>
                        <input type="number" name="limit_panel" class="input" value="0" min="0">
                    </div>
                    <div class="field">
                        <label>گروه کاربری</label>
                        <select name="agent" class="select">
                            <option value="all">همه</option>
                            <option value="f">عادی</option>
                            <option value="n">نماینده</option>
                            <option value="n2">نماینده پیشرفته</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-foot">
                <button type="submit" class="btn btn-primary"><?= icon('plus', 13) ?> ذخیره پنل</button>
                <button type="button" class="btn btn-ghost" onclick="closeModal('addXuiModal')">انصراف</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-veil" id="editPanelModal">
    <div class="modal" style="max-width:560px">
        <div class="modal-head">
            <h3>ویرایش پنل — <span id="edit_panel_name_ro"></span></h3>
            <button type="button" class="modal-x" onclick="closeModal('editPanelModal')"><?= icon('close', 14) ?></button>
        </div>
        <form id="editPanelForm">
            <div class="modal-body" id="edit_xui_fields">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="name_panel" id="edit_panel_name">
                <input type="hidden" name="panel_inbounds" id="edit_panel_inbounds" value="">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
                    <span class="field-hint">وضعیت اتصال:</span>
                    <span class="tag" id="edit_conn_status">—</span>
                    <button type="button" class="btn btn-ghost btn-sm" id="btnTestPanelConn">تست اتصال</button>
                </div>
                <div class="form-grid">
                    <div class="field full">
                        <label>آدرس API *</label>
                        <input type="url" name="url_panel" id="edit_url_panel" class="input" dir="ltr" required>
                    </div>
                    <div class="field full">
                        <label>دامنه لینک ساب</label>
                        <input type="url" name="linksubx" id="edit_linksubx" class="input" dir="ltr">
                    </div>
                    <div class="field">
                        <label>نام کاربری</label>
                        <input type="text" name="username_panel" id="edit_username_panel" class="input" dir="ltr">
                    </div>
                    <div class="field">
                        <label>رمز (خالی = بدون تغییر)</label>
                        <input type="password" name="password_panel" id="edit_password_panel" class="input" dir="ltr" placeholder="********">
                    </div>
                    <div class="field full">
                        <label>توکن API</label>
                        <input type="text" name="xui_api_token" id="edit_xui_api_token" class="input" dir="ltr">
                    </div>
                    <div class="field">
                        <label>محدودیت ساخت</label>
                        <input type="number" name="limit_panel" id="edit_limit_panel" class="input" min="0">
                    </div>
                    <div class="field">
                        <label>گروه کاربری</label>
                        <select name="agent" id="edit_agent" class="select">
                            <option value="all">همه</option>
                            <option value="f">عادی</option>
                            <option value="n">نماینده</option>
                            <option value="n2">نماینده پیشرفته</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>وضعیت پنل در ربات</label>
                        <select name="status" id="edit_status" class="select">
                            <option value="active">فعال</option>
                            <option value="inactive">غیرفعال</option>
                        </select>
                    </div>
                    <div class="field full">
                        <label>اینباندها</label>
                        <div id="edit_panel_inbound_picker" class="inbound-picker-box"></div>
                    </div>
                </div>
            </div>
            <div class="modal-foot">
                <button type="submit" class="btn btn-primary"><?= icon('check', 13) ?> ذخیره</button>
                <button type="button" class="btn btn-ghost" onclick="closeModal('editPanelModal')">انصراف</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>
