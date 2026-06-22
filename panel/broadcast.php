<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/campaign_ops.php';
require_once __DIR__ . '/inc/brand.php';
require_auth();
vira_campaign_ensure_tables($pdo);
$targets = vira_campaign_targets_with_counts($pdo);
$pageTitle = 'ارسال پیام همگانی';
$activeNav = 'broadcast';
$extraCss = ['css/broadcast.css'];
$extraJs = ['js/broadcast.js'];
include __DIR__ . '/inc/layout_head.php';
?>
<div class="bc-hub fade-up" data-csrf="<?= htmlspecialchars(csrf_token()) ?>" data-brand="<?= htmlspecialchars(VIRA_BRAND_MARK) ?>">
    <div class="bc-topbar">
        <div>
            <h2 class="bc-page-title">پیام‌رسانی</h2>
            <p class="bc-page-sub">ارسال کمپین به مخاطبان هدف با دکمه شیشه‌ای، رسانه و ردیابی پیشرفت</p>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" id="bcReloadAll">بروزرسانی</button>
    </div>

    <div class="bc-layout">
        <div class="card bc-compose">
            <div class="card-body bc-compose-body">
                <div class="bc-seg" id="bcTypeSeg">
                    <button type="button" class="bc-seg-btn active" data-type="text">متن</button>
                    <button type="button" class="bc-seg-btn" data-type="photo">عکس</button>
                    <button type="button" class="bc-seg-btn" data-type="video">ویدیو</button>
                </div>

                <div class="bc-target-section">
                    <p class="bc-panel-title">مخاطبان</p>
                    <div class="bc-target-list" id="bcTargetList">
                        <?php foreach ($targets as $i => $t): ?>
                            <button type="button" class="bc-target-chip<?= $i === 0 ? ' active' : '' ?>" data-target="<?= htmlspecialchars($t['id']) ?>">
                                <?= htmlspecialchars($t['label']) ?>
                                <span class="bc-target-count" data-count-for="<?= htmlspecialchars($t['id']) ?>">(<?= number_format((int) $t['count']) ?>)</span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div id="bcUserPanel" class="bc-panel hidden">
                    <p class="bc-panel-title">جستجو و انتخاب کاربر</p>
                    <div class="bc-search-row">
                        <input type="text" id="bcUserSearch" class="input" placeholder="ID، @username، نام">
                        <button type="button" class="btn btn-ghost btn-sm" id="bcUserSearchBtn">جستجو</button>
                    </div>
                    <div id="bcUserResults" class="bc-user-results"></div>
                    <p id="bcUserSelected" class="field-hint"></p>
                </div>

                <label class="bc-field">
                    <span class="bc-field-head"><span id="bcTextLabel">متن پیام</span><span id="bcCharCount">۰/۴۰۹۶</span></span>
                    <textarea id="bcText" class="input bc-textarea" rows="5" placeholder="متن پیام… (HTML ساده پشتیبانی می‌شود)"></textarea>
                </label>

                <div class="bc-grid-2">
                    <label class="bc-field">
                        <span class="bc-field-head">فرمت متن (parse_mode)</span>
                        <select id="bcParseMode" class="input">
                            <option value="HTML">HTML</option>
                            <option value="Markdown">Markdown</option>
                            <option value="MarkdownV2">MarkdownV2</option>
                            <option value="none">بدون فرمت</option>
                        </select>
                    </label>
                    <label class="bc-check bc-check-end">
                        <input type="checkbox" id="bcDisablePreview"> غیرفعال کردن پیش‌نمایش لینک
                    </label>
                </div>

                <div class="bc-buttons-editor" id="bcButtonEditor">
                    <div class="bc-buttons-head">
                        <div>
                            <p class="bc-panel-title">دکمه‌های شیشه‌ای (Inline Keyboard)</p>
                            <p class="field-hint">لینک، Mini App، Callback و رنگ دکمه</p>
                        </div>
                        <button type="button" class="btn btn-ghost btn-sm" id="bcAddRow">+ ردیف دکمه</button>
                    </div>
                    <div id="bcButtonRows" class="bc-button-rows">
                        <p class="bc-empty-hint">دکمه‌ای اضافه نشده — برای لینک یا Mini App «ردیف دکمه» را بزنید</p>
                    </div>
                </div>

                <div id="bcMediaWrap" class="bc-panel hidden">
                    <label class="bc-field">
                        <span class="bc-field-head" id="bcMediaLabel">فایل رسانه</span>
                        <input type="file" id="bcMedia" class="input" accept="image/*">
                    </label>
                </div>

                <label class="bc-check">
                    <input type="checkbox" id="bcPin"> پین کردن پیام بعد از ارسال
                </label>

                <div class="bc-auto-box">
                    <div class="bc-auto-head">
                        <p class="bc-panel-title">ارسال خودکار بعد از broadcast</p>
                        <p class="field-hint">فقط کاربرانی که بعد از اتمام ارسال اولیه /start بزنند</p>
                    </div>
                    <div class="bc-auto-row">
                        <label class="bc-toggle-wrap">
                            <input type="checkbox" id="bcAutoSend" class="bc-toggle-input">
                            <span class="bc-toggle" aria-hidden="true"></span>
                            <span id="bcAutoSendLabel">غیرفعال</span>
                        </label>
                        <label class="bc-auto-delay">
                            تأخیر
                            <input type="number" id="bcAutoDelay" class="input bc-delay-input" min="0" max="1440" value="5" disabled>
                            دقیقه
                        </label>
                    </div>
                </div>

                <button type="button" class="btn btn-primary bc-send-btn" id="bcSend" disabled>ارسال</button>
                <p id="bcResult" class="bc-result hidden"></p>

                <div id="bcProgressBox" class="bc-progress-box hidden">
                    <div class="bc-progress-head">
                        <span id="bcProgressTitle">در حال ارسال…</span>
                        <span id="bcProgressPct">۰٪</span>
                    </div>
                    <div class="bc-progress-bar"><div id="bcProgressFill"></div></div>
                    <p id="bcProgressMeta" class="field-hint"></p>
                    <div class="bc-progress-actions">
                        <button type="button" class="btn btn-warn btn-sm" id="bcPause">توقف</button>
                        <button type="button" class="btn btn-primary btn-sm hidden" id="bcResume">ادامه</button>
                    </div>
                </div>
            </div>
        </div>

        <aside class="bc-preview-wrap">
            <div class="bc-preview-card" id="bcPreviewCard">
                <div class="bc-preview-head">
                    <div>
                        <p class="bc-preview-title">پیش‌نمایش تلگرام</p>
                        <p class="field-hint">نمای تقریبی — قبل از ارسال</p>
                    </div>
                    <span class="tag tag-info">Preview</span>
                </div>
                <div class="bc-preview-chat" id="bcPreviewChat">
                    <div class="bc-preview-bot">
                        <div class="bc-preview-avatar"><?= htmlspecialchars(VIRA_BRAND_MARK) ?></div>
                        <div>
                            <p class="bc-preview-bot-name"><?= htmlspecialchars(VIRA_BRAND_NAME) ?></p>
                            <p class="bc-preview-bot-sub">bot</p>
                        </div>
                    </div>
                    <div class="bc-preview-bubble-wrap">
                        <div class="bc-preview-bubble" id="bcPreviewMedia"></div>
                        <div class="bc-preview-bubble" id="bcPreviewText">متن پیام اینجا نمایش داده می‌شود…</div>
                        <div id="bcPreviewButtons" class="bc-preview-buttons"></div>
                    </div>
                    <p id="bcPreviewPin" class="bc-preview-pin hidden">📌 بعد از ارسال پین می‌شود</p>
                </div>
                <div class="bc-preview-foot">
                    <p><span class="field-hint">مخاطب: </span><span id="bcPreviewAudience">همه کاربران</span></p>
                    <p id="bcPreviewAudienceDetail" class="field-hint"></p>
                </div>
            </div>
            <p class="bc-preview-note">پیش‌نمایش زنده — با تغییر متن و دکمه‌ها به‌روز می‌شود</p>
        </aside>
    </div>

    <div class="card bc-campaigns-card">
        <div class="card-head">
            <div>
                <div class="card-title">پیام‌های ارسال‌شده</div>
                <div class="card-subtitle">وضعیت broadcast و آمار</div>
            </div>
            <div class="bc-filter-tabs" id="bcFilterTabs">
                <button type="button" class="active" data-filter="">همه</button>
                <button type="button" data-filter="active">فعال</button>
                <button type="button" data-filter="deleted">حذف‌شده</button>
            </div>
        </div>
        <div class="card-body" id="bcCampaignList">
            <p class="field-hint">در حال بارگذاری…</p>
        </div>
    </div>
</div>
<?php include __DIR__ . '/inc/layout_foot.php';
