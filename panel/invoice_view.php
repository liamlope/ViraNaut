<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/invoice_manage_ops.php';
require_auth();

$id = trim((string) ($_GET['id'] ?? ''));
if ($id === '') {
    header('Location: invoice.php');
    exit;
}

$invoice = im_get_invoice($pdo, $id);
if (!$invoice) {
    flash('error', 'سفارش یافت نشد.');
    header('Location: invoice.php');
    exit;
}

$panelSnap = im_panel_snapshot($invoice);
$panelData = $panelSnap['ok'] ? ($panelSnap['data'] ?? []) : [];
$panelFields = $panelSnap['ok'] ? im_build_panel_fields($panelData, $textbotlang) : [];
$serviceOthers = im_service_other_rows($pdo, (string) ($invoice['username'] ?? ''));

if (!function_exists('vira_invoice_subscription_url') && is_file(__DIR__ . '/../inc/panel_service_repair.php')) {
    require_once __DIR__ . '/../inc/panel_service_repair.php';
}
$subscriptionUrl = function_exists('vira_invoice_subscription_url')
    ? vira_invoice_subscription_url($invoice, $panelSnap['ok'] ? $panelData : null)
    : trim((string) ($invoice['user_info'] ?? ''));

$panelExpireLabel = '—';
$panelExpireTs = (int) ($invoice['panel_expire'] ?? 0);
if ($panelExpireTs > 0) {
    im_ensure_jdf();
    $panelExpireLabel = function_exists('jdate')
        ? jdate('Y/m/d H:i', $panelExpireTs)
        : date('Y/m/d H:i', $panelExpireTs);
    if ($panelExpireTs < time()) {
        $panelExpireLabel .= ' (منقضی)';
    }
} elseif ($panelSnap['ok'] && !empty($panelData['expire'])) {
    $panelExpireTs = (int) $panelData['expire'];
    im_ensure_jdf();
    $panelExpireLabel = function_exists('jdate')
        ? jdate('Y/m/d H:i', $panelExpireTs)
        : date('Y/m/d H:i', $panelExpireTs);
}

$panelMissing = !$panelSnap['ok'];

$botStatus = (string) ($invoice['Status'] ?? '');
[$statusCls, $statusLbl] = im_bot_status_map()[$botStatus] ?? ['tag-plain', $botStatus ?: '—'];

$isTest = ($invoice['name_product'] ?? '') === 'سرویس تست';
$volumeLabel = $isTest
    ? ((int) ($invoice['Volume'] ?? 0)) . ' مگابایت'
    : ((int) ($invoice['Volume'] ?? 0)) . ' گیگابایت';
$timeLabel = $isTest
    ? ((int) ($invoice['Service_time'] ?? 0)) . ' ساعت'
    : ((int) ($invoice['Service_time'] ?? 0)) . ' روز';

$panelStatus = (string) ($panelData['status'] ?? '');
$toggleLabel = $panelStatus === 'active' ? 'خاموش کردن اکانت' : 'روشن کردن اکانت';
$canToggle = $panelSnap['ok'] && $panelStatus !== '' && $panelStatus !== 'on_hold';

$csrf = csrf_token();
$actionBase = 'invoice_action.php?_csrf=' . urlencode($csrf) . '&id=' . urlencode($id);
$userId = (int) ($invoice['id_user'] ?? 0);

$pageTitle = 'سفارش ' . $id;
$pageLede = 'مدیریت سفارش و وضعیت سرویس در پنل.';
$activeNav = 'invoice';
$showPageHead = false;
$extraCss = ['css/user-manage.css'];
include __DIR__ . '/inc/layout_head.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:8px" class="fade-up">
  <a href="invoice.php?q=<?= urlencode((string) $invoice['id_user']) ?>" class="btn btn-ghost btn-sm"><?= icon('arrow-left', 14) ?> فهرست سفارشات</a>
  <?php if ($userId > 0): ?>
    <a href="user.php?id=<?= $userId ?>" class="btn btn-ghost btn-sm"><?= icon('user', 13) ?> پروفایل کاربر</a>
  <?php endif; ?>
</div>

<div class="card fade-up um-manage-card" style="margin-bottom:16px">
  <div class="card-head">
    <div>
      <h2 style="margin:0;font-size:1rem">🛒 <?= htmlspecialchars($invoice['name_product'] ?? '—') ?></h2>
      <p class="cf" style="margin:4px 0 0;font-size:.78rem">کد سفارش: <code><?= htmlspecialchars($id) ?></code></p>
    </div>
    <span class="tag <?= $statusCls ?>"><?= htmlspecialchars($statusLbl) ?></span>
  </div>

  <div class="um-actions">
    <a class="um-action" href="invoice_view.php?id=<?= urlencode($id) ?>">
      <strong>♻️ بروزرسانی</strong>
      <span>بارگذاری مجدد اطلاعات پنل</span>
    </a>
    <?php if ($panelMissing): ?>
      <a class="um-action um-action-ok" href="<?= $actionBase ?>&action=repair_panel"
         onclick="return confirm('سرویس در پنل یافت نشد. با همان لینک و حجم/زمان باقی‌مانده دوباره ساخته شود؟')">
        <strong>♻️ بازیابی در پنل</strong>
        <span>ساخت مجدد سرویس حذف‌شده از پنل VPN</span>
      </a>
    <?php endif; ?>
    <?php if ($canToggle): ?>
      <a class="um-action um-action-ok" href="<?= $actionBase ?>&action=toggle_status"
         onclick="return confirm('وضعیت سرویس در پنل تغییر کند؟')">
        <strong><?= $panelStatus === 'active' ? '❌' : '💡' ?> <?= htmlspecialchars($toggleLabel) ?></strong>
        <span>مثل دکمه روشن/خاموش در ربات</span>
      </a>
    <?php endif; ?>
    <a class="um-action" href="<?= $actionBase ?>&action=send_sub"
       onclick="return confirm('لینک اشتراک برای کاربر در تلگرام ارسال شود؟')">
      <strong>🔗 ارسال لینک اشتراک</strong>
      <span>ارسال sub link به کاربر</span>
    </a>
    <a class="um-action um-action-danger" href="<?= $actionBase ?>&action=remove"
       onclick="return confirm('سرویس از پنل حذف شود؟ (بدون بازگشت وجه)')">
      <strong>🗑 حذف سرویس</strong>
      <span>حذف از پنل — بدون بازگشت وجه</span>
    </a>
    <a class="um-action um-action-danger" href="<?= $actionBase ?>&action=remove_refund"
       onclick="return confirm('سرویس حذف و مبلغ به موجودی کاربر برگردد؟')">
      <strong>↩️ حذف + بازگشت وجه</strong>
      <span>حذف از پنل و افزایش موجودی</span>
    </a>
    <a class="um-action um-action-danger" href="<?= $actionBase ?>&action=delete_db"
       onclick="return confirm('فقط از دیتابیس ربات حذف شود؟ (پنل VPN دست نخورده می‌ماند)')">
      <strong>🗄 حذف از دیتابیس</strong>
      <span>مثل «حذف کامل سرویس» در ربات</span>
    </a>
  </div>
</div>

<div class="stats u-stats fade-up" style="margin-bottom:16px;grid-template-columns:repeat(auto-fit,minmax(140px,1fr))">
  <div class="stat">
    <div class="stat-lbl">کاربر</div>
    <div class="stat-val cm" style="font-size:.9rem"><?= htmlspecialchars((string) ($invoice['id_user'] ?? '—')) ?></div>
  </div>
  <div class="stat">
    <div class="stat-lbl">یوزرنیم سرویس</div>
    <div class="stat-val" style="font-size:.82rem"><code><?= htmlspecialchars($invoice['username'] ?? '—') ?></code></div>
  </div>
  <div class="stat">
    <div class="stat-lbl">پنل</div>
    <div class="stat-val" style="font-size:.82rem"><?= htmlspecialchars($invoice['Service_location'] ?? '—') ?></div>
  </div>
  <div class="stat">
    <div class="stat-lbl">قیمت</div>
    <div class="stat-val cn"><?= number_format((int) ($invoice['price_product'] ?? 0)) ?> <span class="cf">ت</span></div>
  </div>
  <div class="stat">
    <div class="stat-lbl">حجم / زمان</div>
    <div class="stat-val" style="font-size:.78rem"><?= htmlspecialchars($volumeLabel) ?> · <?= htmlspecialchars($timeLabel) ?></div>
  </div>
  <div class="stat">
    <div class="stat-lbl">تاریخ خرید</div>
    <div class="stat-val cf" style="font-size:.78rem"><?= im_format_time($invoice['time_sell'] ?? null) ?></div>
  </div>
  <div class="stat">
    <div class="stat-lbl">انقضا (پنل)</div>
    <div class="stat-val cf" style="font-size:.78rem"><?= htmlspecialchars($panelExpireLabel) ?></div>
  </div>
</div>

<?php if ($subscriptionUrl !== ''): ?>
<div class="card fade-up" style="margin-bottom:16px">
  <div class="card-head">
    <h3 style="margin:0;font-size:.92rem">🔗 لینک اشتراک (نمایش ادمین)</h3>
  </div>
  <div class="card-body" style="padding:12px 16px">
    <p class="field-hint" style="margin:0 0 8px">این لینک فقط در پنل وب نمایش داده می‌شود؛ برای ارسال به کاربر از دکمه «ارسال لینک اشتراک» استفاده کنید.</p>
    <code style="display:block;word-break:break-all;font-size:.75rem;line-height:1.5;padding:10px;background:var(--sf2);border-radius:8px"><?= htmlspecialchars($subscriptionUrl) ?></code>
    <?php if ($panelMissing): ?>
      <p class="cf" style="margin:10px 0 0;font-size:.8rem;color:var(--warn)">⚠️ کاربر در پنل VPN یافت نشد — لینک بالا از آخرین ذخیره ربات است. برای فعال‌سازی مجدد «بازیابی در پنل» را بزنید.</p>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<div class="grid-2 fade-up" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;margin-bottom:16px">
  <div class="card">
    <div class="card-head"><h3 style="margin:0;font-size:.92rem">📋 جزئیات سفارش در ربات</h3></div>
    <div class="card-body" style="padding:12px 16px">
      <dl class="kv-list">
        <dt>وضعیت ربات</dt><dd><span class="tag <?= $statusCls ?>"><?= htmlspecialchars($statusLbl) ?></span>
          <span class="cf" style="font-size:.72rem;display:block;margin-top:4px">وضعیت داخل ربات است، نه تاریخ انقضای پنل.</span>
        </dd>
        <dt>محصول</dt><dd><?= htmlspecialchars($invoice['name_product'] ?? '—') ?></dd>
        <dt>یادداشت</dt><dd><?= htmlspecialchars($invoice['note'] ?? '—') ?></dd>
        <dt>کد محصول</dt><dd><code><?= htmlspecialchars($invoice['code_product'] ?? '—') ?></code></dd>
      </dl>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h3 style="margin:0;font-size:.92rem">📡 وضعیت در پنل VPN</h3></div>
    <div class="card-body" style="padding:12px 16px">
      <?php if (!$panelSnap['ok']): ?>
        <p class="cf" style="font-size:.85rem;color:var(--no)">کاربر در پنل یافت نشد یا خطا در اتصال.</p>
      <?php else: ?>
        <dl class="kv-list">
          <?php foreach ($panelFields as $field): ?>
            <dt><?= htmlspecialchars($field['label']) ?></dt>
            <dd<?= !empty($field['mono']) ? ' class="cm" style="word-break:break-all;font-size:.75rem"' : '' ?>>
              <?= !empty($field['mono']) ? '<code>' . htmlspecialchars($field['value']) . '</code>' : htmlspecialchars($field['value']) ?>
            </dd>
          <?php endforeach; ?>
        </dl>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if ($serviceOthers): ?>
<div class="card fade-up">
  <div class="card-head"><h3 style="margin:0;font-size:.92rem">📌 سرویس‌های جانبی (تمدید / حجم / …)</h3></div>
  <div class="tbl-wrap">
    <table class="tbl-md">
      <thead>
        <tr><th>نوع</th><th>قیمت</th><th>زمان</th><th>وضعیت</th></tr>
      </thead>
      <tbody>
        <?php foreach ($serviceOthers as $row): ?>
          <tr>
            <td><?= htmlspecialchars(im_service_type_label((string) ($row['type'] ?? ''))) ?></td>
            <td class="cn"><?= number_format((int) ($row['price'] ?? 0)) ?> ت</td>
            <td class="cf"><?= im_format_time($row['time'] ?? null) ?></td>
            <td><?= htmlspecialchars($row['status'] ?? '—') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<style>
.kv-list { display: grid; grid-template-columns: minmax(110px, 38%) 1fr; gap: 8px 12px; margin: 0; font-size: .82rem; }
.kv-list dt { color: var(--mute); margin: 0; }
.kv-list dd { margin: 0; color: var(--tx); }
.inv-row-link { cursor: pointer; }
.inv-row-link:hover td { background: var(--sf2); }
</style>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>
