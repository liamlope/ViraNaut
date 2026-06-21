<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/panel_type_defs.php';
require_auth();

if (function_exists('mirza_ensure_marzban_panel_columns')) {
    mirza_ensure_marzban_panel_columns();
}

$panels = [];
try {
    $panels = db_fetchAll(
        $pdo,
        'SELECT name_panel, type, url_panel, status, limit_panel, agent, version_panel, xui_api_token, linksubx, inboundid, inbounds, username_panel, code_panel FROM marzban_panel ORDER BY name_panel ASC'
    );
} catch (Exception $e) {
    error_log('panels.php list: ' . $e->getMessage());
}

function panel_page_type_label(array $p): string
{
    $type = $p['type'] ?? '';
    if ($type === 'marzban' && (string) ($p['version_panel'] ?? '0') === '1') {
        return 'Pasarguard';
    }
    $defs = panel_web_type_defs();
    return $defs[$type]['label'] ?? $type;
}

function panel_page_is_web_crud(string $type, $version): bool
{
    $t = panel_web_normalize_type($type);
    if ($t === 'marzban' && (string) $version === '1') {
        $t = 'pasarguard';
    }
    return isset(panel_web_type_defs()[$t]);
}

$pageTitle = 'پنل‌های VPN';
$pageLede = 'مدیریت پنل‌ها — افزودن، ویرایش، تست اتصال و مرکز ۳x-ui.';
$activeNav = 'panels';
$extraCss = ['css/panels-admin.css', 'css/product-inbounds.css'];
$extraJs = ['js/inbound-picker.js', 'js/panels.js'];
include __DIR__ . '/inc/layout_head.php';
?>

<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap" class="fade-up">
    <a href="bot.php" class="btn btn-ghost btn-sm">مرکز ربات</a>
    <button type="button" class="btn btn-primary btn-sm" onclick="openAddPanelWizard()"><?= icon('plus', 14) ?> افزودن پنل</button>
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
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($panels === []): ?>
                    <tr>
                        <td colspan="8">
                            <div class="empty" style="padding:28px">
                                <p>پنلی ثبت نشده. «افزودن پنل» را بزنید.</p>
                            </div>
                        </td>
                    </tr>
                <?php else:
                    foreach ($panels as $p):
                        $type = $p['type'] ?? '';
                        $typeLabel = panel_page_type_label($p);
                        $active = mirza_panel_is_active_status($p['status'] ?? '');
                        $url = $p['url_panel'] ?? '';
                        $ib = $p['inbounds'] ?? '';
                        if ($ib === '' || $ib === 'null') {
                            $ib = $p['inboundid'] ?? '—';
                        }
                        $webCrud = panel_page_is_web_crud($type, $p['version_panel'] ?? '0');
                        $isXui = panel_web_is_xui_type($type);
                        $nameEsc = htmlspecialchars($p['name_panel'] ?? '', ENT_QUOTES);
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
                                <?php if ($webCrud): ?>
                                    <span class="tag" id="conn-<?= $nameEsc ?>">—</span>
                                    <button type="button" class="btn btn-ghost btn-sm" style="margin-top:4px;font-size:.68rem"
                                        data-panel-test="<?= $nameEsc ?>">تست اتصال</button>
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
                                <div class="panel-actions-row">
                                    <?php if ($isXui): ?>
                                        <button type="button" class="btn btn-ghost btn-sm" title="جزئیات"
                                            onclick="openPanelHub(<?= htmlspecialchars(json_encode($p['name_panel']), ENT_QUOTES) ?>)">جزئیات</button>
                                    <?php endif; ?>
                                    <?php if ($webCrud): ?>
                                        <button type="button" class="btn btn-ghost btn-sm btn-icon" title="ویرایش"
                                            onclick="openEditPanelModal(<?= htmlspecialchars(json_encode($p['name_panel']), ENT_QUOTES) ?>)">
                                            <?= icon('edit', 13) ?>
                                        </button>
                                        <button type="button" class="btn btn-no btn-sm btn-icon" title="حذف"
                                            onclick="openDeletePanelModal(<?= htmlspecialchars(json_encode($p['name_panel']), ENT_QUOTES) ?>)">
                                            <?= icon('trash', 13) ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach;
                endif; ?>
            </tbody>
        </table>
    </div>
</div>

<p class="field-hint fade-up d1" style="margin-top:14px">
    پنل ۳x-ui: آدرس API بدون <code>/panel</code> · توکن از <b>Settings → Security → API Token</b>
</p>

<div class="modal-veil" id="addPanelModal">
    <div class="modal" style="max-width:560px">
        <div class="modal-head">
            <h3>افزودن پنل</h3>
            <button type="button" class="modal-x" onclick="closeModal('addPanelModal')"><?= icon('close', 14) ?></button>
        </div>
        <form id="addPanelForm">
            <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <div class="field full">
                    <label>نوع پنل</label>
                    <select id="addPanelTypeSelect" class="select"></select>
                </div>
                <div id="addPanelDynamicFields" class="form-grid"></div>
            </div>
            <div class="modal-foot">
                <button type="submit" class="btn btn-primary"><?= icon('plus', 13) ?> ذخیره</button>
                <button type="button" class="btn btn-ghost" onclick="closeModal('addPanelModal')">انصراف</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-veil" id="editPanelModal">
    <div class="modal" style="max-width:560px">
        <div class="modal-head">
            <h3>ویرایش — <span id="edit_panel_name_ro"></span></h3>
            <button type="button" class="modal-x" onclick="closeModal('editPanelModal')"><?= icon('close', 14) ?></button>
        </div>
        <form id="editPanelForm">
            <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="name_panel" id="edit_panel_name">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
                    <span class="field-hint">اتصال:</span>
                    <span class="tag" id="edit_conn_status">—</span>
                    <button type="button" class="btn btn-ghost btn-sm" id="btnTestPanelConn">تست اتصال</button>
                </div>
                <div id="editDynamicFields" class="form-grid"></div>
                <div class="field" style="margin-top:8px">
                    <label>وضعیت در ربات</label>
                    <select name="status" id="edit_status" class="select">
                        <option value="active">فعال</option>
                        <option value="inactive">غیرفعال</option>
                    </select>
                </div>
            </div>
            <div class="modal-foot">
                <button type="submit" class="btn btn-primary"><?= icon('check', 13) ?> ذخیره</button>
                <button type="button" class="btn btn-ghost" onclick="closeModal('editPanelModal')">انصراف</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-veil" id="deletePanelModal">
    <div class="modal" style="max-width:420px">
        <div class="modal-head">
            <h3>حذف پنل</h3>
            <button type="button" class="modal-x" onclick="closeModal('deletePanelModal')"><?= icon('close', 14) ?></button>
        </div>
        <form id="deletePanelForm">
            <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="name_panel" id="delete_panel_name">
                <p>برای حذف پنل <strong id="delete_panel_label"></strong> نام را دقیقاً بنویسید:</p>
                <input type="text" name="confirm_name" id="delete_confirm_name" class="input" required autocomplete="off">
            </div>
            <div class="modal-foot">
                <button type="submit" class="btn btn-no"><?= icon('trash', 13) ?> حذف</button>
                <button type="button" class="btn btn-ghost" onclick="closeModal('deletePanelModal')">انصراف</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-veil" id="panelHubModal">
    <div class="modal panel-hub-modal" style="max-width:520px">
        <div class="modal-head">
            <h3>مرکز پنل — <span id="hubModalTitle"></span></h3>
            <button type="button" class="modal-x" onclick="closeModal('panelHubModal')"><?= icon('close', 14) ?></button>
        </div>
        <div class="modal-body" id="hubModalBody"></div>
    </div>
</div>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>
