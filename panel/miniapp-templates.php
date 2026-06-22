<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/miniapp_templates_defs.php';
require_auth();

$current = vira_miniapp_get_template($pdo);
$templates = vira_miniapp_templates();
$domain = '';
$dh = $GLOBALS['domainhosts'] ?? '';
if ($dh !== '' && strpos((string) $dh, '{') === false) {
    $domain = function_exists('vira_normalize_domainhosts_value')
        ? vira_normalize_domainhosts_value($dh)
        : preg_replace('#^https?://#i', '', trim((string) $dh));
}

$pageTitle = 'قالب مینی‌اپ';
$pageLede = '۵ ساختار UI متفاوت (نه فقط رنگ) — پیش‌نمایش با داده نمونه و اعمال برای کاربران واقعی.';
$activeNav = 'miniapp-templates';
$extraCss = ['css/miniapp-templates.css'];
$extraJs = ['js/miniapp-templates.js'];
include __DIR__ . '/inc/layout_head.php';
?>

<div class="miniapp-tpl-page fade-up"
    data-csrf="<?= htmlspecialchars(csrf_token()) ?>"
    data-current="<?= htmlspecialchars($current) ?>"
    data-domain="<?= htmlspecialchars($domain) ?>">

    <div class="miniapp-tpl-toolbar">
        <a href="shop-settings.php" class="btn btn-ghost btn-sm"><?= icon('package', 14) ?> تنظیمات فروشگاه</a>
        <?php if ($domain): ?>
            <a href="https://<?= htmlspecialchars($domain) ?>/app/" target="_blank" rel="noopener" class="btn btn-ghost btn-sm">
                <?= icon('chart', 14) ?> باز کردن مینی‌اپ
            </a>
        <?php endif; ?>
        <button type="button" class="btn btn-primary btn-sm" id="applyTemplateBtn" disabled>اعمال قالب انتخاب‌شده</button>
    </div>

    <div class="card miniapp-tpl-active-card">
        <div class="card-body">
            <span class="field-hint">قالب فعال:</span>
            <strong id="activeTemplateLabel"><?= htmlspecialchars($templates[$current]['label'] ?? $current) ?></strong>
        </div>
    </div>

    <div class="miniapp-tpl-grid" id="templateGrid">
        <?php foreach ($templates as $t): ?>
            <article class="card miniapp-tpl-card<?= $t['id'] === $current ? ' is-active' : '' ?>"
                data-id="<?= htmlspecialchars($t['id']) ?>"
                style="--tpl-accent:<?= htmlspecialchars($t['accent']) ?>;--tpl-accent2:<?= htmlspecialchars($t['accent2']) ?>;--tpl-bg:<?= htmlspecialchars($t['bg']) ?>">
                <div class="miniapp-tpl-preview-wrap">
                    <?php if ($domain): ?>
                        <iframe class="miniapp-tpl-iframe" title="پیش‌نمایش <?= htmlspecialchars($t['label']) ?>"
                            src="https://<?= htmlspecialchars($domain) ?>/app/?tpl_preview=<?= rawurlencode($t['id']) ?>&demo=1"
                            loading="lazy"></iframe>
                    <?php else: ?>
                        <div class="miniapp-tpl-mock" data-mock="<?= htmlspecialchars($t['id']) ?>"></div>
                    <?php endif; ?>
                </div>
                <div class="card-body miniapp-tpl-meta">
                    <div class="miniapp-tpl-head">
                        <h3 class="card-title"><?= htmlspecialchars($t['label']) ?></h3>
                        <?php if ($t['id'] === $current): ?><span class="tag tag-ok">فعال</span><?php endif; ?>
                    </div>
                        <p class="field-hint"><?= htmlspecialchars($t['desc']) ?></p>
                        <p class="field-hint" style="margin-top:4px"><code><?= htmlspecialchars($t['layout'] ?? '') ?></code></p>
                    <ul class="miniapp-tpl-features">
                        <?php foreach ($t['features'] as $f): ?>
                            <li><?= htmlspecialchars($f) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="miniapp-tpl-actions">
                        <button type="button" class="btn btn-ghost btn-sm btn-preview-tpl" data-id="<?= htmlspecialchars($t['id']) ?>">پیش‌نمایش بزرگ</button>
                        <button type="button" class="btn btn-primary btn-sm btn-select-tpl" data-id="<?= htmlspecialchars($t['id']) ?>">انتخاب</button>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</div>

<div class="modal-veil" id="previewModal">
    <div class="modal modal-lg miniapp-preview-modal">
        <div class="modal-head">
            <h3 id="previewModalTitle">پیش‌نمایش قالب</h3>
            <button type="button" class="modal-x" data-close-preview>✕</button>
        </div>
        <div class="modal-body miniapp-preview-body">
            <iframe id="previewModalFrame" class="miniapp-tpl-iframe-full" title="پیش‌نمایش"></iframe>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn btn-ghost" data-close-preview>بستن</button>
            <button type="button" class="btn btn-primary" id="applyFromModal">اعمال این قالب</button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/inc/layout_foot.php';
