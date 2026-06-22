<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_auth();

$pageTitle = 'درباره ویرا';
$pageLede = 'اطلاعات نسخه، برند و پروژه.';
$activeNav = 'about';
include __DIR__ . '/inc/layout_head.php';

$botVer = vira_brand_version();
$miniVer = is_readable(dirname(__DIR__) . '/app/version') ? trim(file_get_contents(dirname(__DIR__) . '/app/version')) : VIRA_MINIAPP_VERSION;
?>

<div class="fade-up">
    <div class="card">
        <div class="card-head">
            <div class="brand-mark" style="width:48px;height:48px;font-size:1.4rem;border-radius:12px;display:flex;align-items:center;justify-content:center;background:var(--ac);color:#fff"><?= VIRA_BRAND_MARK ?></div>
            <div>
                <div class="card-title"><?= VIRA_BRAND_NAME_FA ?> · <?= VIRA_BRAND_NAME ?></div>
                <div class="card-subtitle">ربات VPN رایگان و متن‌باز</div>
            </div>
        </div>
        <div class="card-body">
            <div class="stats" style="grid-template-columns:repeat(auto-fill,minmax(140px,1fr))">
                <div class="stat ok"><div class="stat-label">نسخه ربات</div><div class="stat-num"><?= htmlspecialchars($botVer) ?></div></div>
                <div class="stat"><div class="stat-label">نسخه مینی‌اپ</div><div class="stat-num"><?= htmlspecialchars($miniVer) ?></div></div>
                <div class="stat warn"><div class="stat-label">برند</div><div class="stat-num" style="font-size:1rem"><?= VIRA_BRAND_NAME_FA ?></div></div>
            </div>
            <p class="field-hint" style="margin-top:16px">
                ویرا (ViraNaut) جایگزین برند قبلی است. این پروژه برای مدیریت ربات VPN، مینی‌اپ تلگرام، درگاه‌های پرداخت و پنل ادمین طراحی شده است.
            </p>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
                <a href="<?= VIRA_GITHUB_URL ?>" class="btn btn-primary btn-sm" target="_blank" rel="noopener">گیت‌هاب / استار</a>
                <a href="migration.php" class="btn btn-ghost btn-sm">مهاجرت از میرزا</a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/inc/layout_foot.php';
