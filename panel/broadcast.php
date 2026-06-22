<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/campaign_ops.php';
require_auth();
vira_campaign_ensure_tables($pdo);
$targets = vira_campaign_target_defs();
$pageTitle = 'ارسال پیام همگانی';
$activeNav = 'broadcast';
$extraCss = ['css/broadcast.css'];
$extraJs = ['js/broadcast.js'];
include __DIR__ . '/inc/layout_head.php';
?>
<div class="broadcast-hub fade-up" data-csrf="<?= htmlspecialchars(csrf_token()) ?>">
    <div class="broadcast-hero card">
        <div class="broadcast-hero-inner">
            <div>
                <h2 class="broadcast-title">مرکز پیام‌رسانی</h2>
                <p class="broadcast-sub">ارسال کمپین به مخاطبان هدف — مشابه پنل numeric_id_bot با ردیابی پیشرفت و توقف/ادامه</p>
            </div>
            <div class="broadcast-hero-badges">
                <span class="tag tag-info">حداکثر ۴۰ پیام در هر دسته</span>
                <span class="tag tag-ok">HTML پشتیبانی می‌شود</span>
            </div>
        </div>
    </div>

    <div class="broadcast-grid">
        <div class="card broadcast-compose">
            <div class="card-head">
                <div class="card-title">پیام جدید</div>
                <div class="card-subtitle">متن، مخاطب و دکمه اینلاین</div>
            </div>
            <div class="card-body">
                <label class="field-label">مخاطبان</label>
                <select id="bcTarget" class="input">
                    <?php foreach ($targets as $key => $label): ?>
                        <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>

                <label class="field-label" style="margin-top:12px">متن پیام (HTML)</label>
                <textarea id="bcText" class="input broadcast-textarea" rows="8" placeholder="متن پیام را بنویسید…"></textarea>

                <label class="field-label" style="margin-top:12px">دکمه‌های اینلاین (JSON اختیاری)</label>
                <textarea id="bcMarkup" class="input" rows="3" placeholder='{"inline_keyboard":[[{"text":"خرید","callback_data":"buy"}]]}'></textarea>

                <label class="broadcast-check" style="margin-top:10px">
                    <input type="checkbox" id="bcPin"> پین پیام بعد از ارسال
                </label>

                <div class="broadcast-actions">
                    <button type="button" class="btn btn-primary" id="bcStart">شروع کمپین</button>
                    <button type="button" class="btn btn-ghost" id="bcPreview">پیش‌نمایش</button>
                </div>

                <div id="bcActivePanel" class="broadcast-active hidden">
                    <div class="broadcast-progress-head">
                        <span id="bcActiveTitle">در حال ارسال…</span>
                        <span id="bcActivePct" class="tag tag-info">۰٪</span>
                    </div>
                    <div class="broadcast-progress-bar"><div id="bcActiveBar" style="width:0%"></div></div>
                    <p id="bcActiveMeta" class="field-hint"></p>
                    <div class="broadcast-actions">
                        <button type="button" class="btn btn-warn btn-sm" id="bcPause">توقف</button>
                        <button type="button" class="btn btn-primary btn-sm hidden" id="bcResume">ادامه</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card broadcast-preview-card">
            <div class="card-head">
                <div class="card-title">پیش‌نمایش تلگرام</div>
            </div>
            <div class="card-body">
                <div class="tg-preview">
                    <div class="tg-preview-bubble" id="bcPreviewBubble">متن پیام اینجا نمایش داده می‌شود…</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-top:16px">
        <div class="card-head">
            <div>
                <div class="card-title">کمپین‌های اخیر</div>
                <div class="card-subtitle">وضعیت ارسال و آمار</div>
            </div>
            <button type="button" class="btn btn-ghost btn-sm" id="bcReload">بروزرسانی</button>
        </div>
        <div class="card-body" id="bcCampaignList">
            <p class="field-hint">در حال بارگذاری…</p>
        </div>
    </div>
</div>
<?php include __DIR__ . '/inc/layout_foot.php';
