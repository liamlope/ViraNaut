<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_auth();

$pageTitle = 'مرکز مالی';
$pageLede = 'درگاه‌ها، کارت‌ها، رسیدهای در انتظار، تأیید/رد پرداخت و گزارش‌ها.';
$activeNav = 'finance';
$extraCss = ['css/finance.css'];
$extraJs = ['js/finance.js'];
include __DIR__ . '/inc/layout_head.php';
?>

<div class="finance-page fade-up" id="financeApp"
    data-csrf="<?= htmlspecialchars(csrf_token()) ?>"
    data-api="api/finance.php">

    <div class="finance-toolbar">
        <button type="button" class="btn btn-primary btn-sm" id="financeRefresh"><?= icon('chart', 14) ?> بروزرسانی</button>
        <button type="button" class="btn btn-ghost btn-sm" id="financeExport">خروجی CSV</button>
        <a href="user.php" class="btn btn-ghost btn-sm"><?= icon('users', 14) ?> کاربران</a>
    </div>

    <div class="stats finance-stats" id="financeStats">
        <div class="stat success"><div class="stat-label">پرداخت موفق</div><div class="stat-num" data-k="total_paid">—</div></div>
        <div class="stat"><div class="stat-label">امروز</div><div class="stat-num" data-k="paid_today">—</div></div>
        <div class="stat warn"><div class="stat-label">در انتظار تأیید</div><div class="stat-num" data-k="pending">—</div></div>
        <div class="stat"><div class="stat-label">پرداخت نشده</div><div class="stat-num" data-k="unpaid">—</div></div>
        <div class="stat"><div class="stat-label">رد شده</div><div class="stat-num" data-k="rejected">—</div></div>
        <div class="stat"><div class="stat-label">فروش فاکتور</div><div class="stat-num" data-k="invoice_revenue">—</div></div>
    </div>

    <div class="card finance-tabs-card">
        <div class="card-head finance-tabs-head">
            <div class="finance-tabs" role="tablist">
                <button type="button" class="finance-tab active" data-tab="pending">⏳ در انتظار</button>
                <button type="button" class="finance-tab" data-tab="tx">همه تراکنش‌ها</button>
                <button type="button" class="finance-tab" data-tab="gateways">درگاه‌ها و کارت</button>
                <button type="button" class="finance-tab" data-tab="inv">فاکتورها</button>
                <button type="button" class="finance-tab" data-tab="disc">تخفیف</button>
            </div>
            <div class="search-box finance-search" id="financeSearchWrap">
                <?= icon('search', 14) ?>
                <input type="search" id="financeSearch" placeholder="آیدی کاربر، کد پیگیری…" autocomplete="off">
            </div>
            <select id="financeStatusFilter" class="select finance-status-filter" style="display:none">
                <option value="">همه وضعیت‌ها</option>
                <option value="waiting">در انتظار</option>
                <option value="paid">پرداخت شده</option>
                <option value="Unpaid">پرداخت نشده</option>
                <option value="reject">رد شده</option>
                <option value="expire">منقضی</option>
            </select>
        </div>

        <div class="card-body finance-tab-panel" id="financeTabPending">
            <p class="field-hint finance-hint">رسیدهای کارت‌به‌کارت و روش‌های آفلاین — مثل تلگرام تأیید یا رد کنید. رسید تصویری در تلگرام ادمین است.</p>
            <div class="tbl-wrap">
                <table class="tbl-md">
                    <thead>
                        <tr>
                            <th>عملیات</th>
                            <th>سفارش</th>
                            <th>کاربر</th>
                            <th>مبلغ</th>
                            <th>روش</th>
                            <th>نوع</th>
                            <th>زمان</th>
                        </tr>
                    </thead>
                    <tbody id="financePendingBody"><tr><td colspan="7" class="finance-loading">بارگذاری…</td></tr></tbody>
                </table>
            </div>
        </div>

        <div class="card-body finance-tab-panel hidden" id="financeTabTx">
            <div class="tbl-wrap">
                <table class="tbl-md">
                    <thead>
                        <tr>
                            <th>عملیات</th>
                            <th>سفارش</th>
                            <th>کاربر</th>
                            <th>مبلغ</th>
                            <th>روش</th>
                            <th>وضعیت</th>
                            <th>زمان</th>
                        </tr>
                    </thead>
                    <tbody id="financeTxBody"><tr><td colspan="7" class="finance-loading">بارگذاری…</td></tr></tbody>
                </table>
            </div>
            <div class="tbl-foot">
                <span id="financeTxMeta"></span>
                <div class="pager" id="financeTxPager"></div>
            </div>
        </div>

        <div class="card-body finance-tab-panel hidden" id="financeTabGateways">
            <div id="financeSmsGuide" class="card finance-sms-guide full" style="margin-bottom:16px"></div>
            <div id="financeGatewayProfiles" class="finance-gw-profiles"></div>
            <div class="card finance-gw-card full" style="margin-top:16px">
                <div class="card-head"><div class="card-title">تنظیمات عمومی پرداخت</div></div>
                <div class="card-body" id="financeGeneralPay"></div>
                <button type="button" class="btn btn-primary btn-sm" id="saveGeneralPay" style="margin-top:8px">ذخیره عمومی</button>
            </div>
            <div class="card finance-gw-card full" style="margin-top:16px">
                    <div class="card-head"><div class="card-title">شماره کارت‌ها</div></div>
                    <div class="card-body">
                        <form id="financeCardForm" class="finance-card-form">
                            <input type="text" name="cardnumber" class="input" placeholder="شماره کارت (۱۶ رقم)" dir="ltr" maxlength="19">
                            <input type="text" name="namecard" class="input" placeholder="نام دارنده">
                            <button type="submit" class="btn btn-primary btn-sm">افزودن کارت</button>
                        </form>
                        <div id="financeCardsList" class="finance-cards-list"></div>
                    </div>
            </div>
            <div class="card" style="margin-top:16px">
                <div class="card-head"><div class="card-title">تفکیک روش پرداخت (موفق)</div></div>
                <div class="card-body" id="financeByMethod"></div>
            </div>
        </div>

        <div class="card-body finance-tab-panel hidden" id="financeTabInv">
            <div class="tbl-wrap">
                <table class="tbl-md">
                    <thead><tr><th>فاکتور</th><th>کاربر</th><th>محصول</th><th>مبلغ</th><th>وضعیت</th></tr></thead>
                    <tbody id="financeInvBody"></tbody>
                </table>
            </div>
        </div>

        <div class="card-body finance-tab-panel hidden" id="financeTabDisc">
            <form id="discountForm" style="margin-bottom:16px;display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:8px;align-items:end">
                <input type="hidden" id="disc_id" value="0">
                <label class="field"><span class="field-label">کد</span><input type="text" id="disc_code" class="input"></label>
                <label class="field"><span class="field-label">مبلغ</span><input type="text" id="disc_price" class="input"></label>
                <label class="field"><span class="field-label">سقف</span><input type="text" id="disc_limit" class="input"></label>
                <button type="submit" class="btn btn-primary btn-sm">ذخیره</button>
            </form>
            <div class="tbl-wrap">
                <table class="tbl-sm">
                    <thead><tr><th>کد</th><th>مبلغ</th><th>سقف</th><th>مصرف</th><th></th></tr></thead>
                    <tbody id="financeDiscBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal-veil" id="financeRejectModal">
    <div class="modal modal-sm">
        <div class="modal-head">
            <h3>رد پرداخت</h3>
            <button type="button" class="modal-x" data-close-reject>✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="financeRejectOrder">
            <label class="field">
                <span class="field-label">دلیل رد (برای کاربر)</span>
                <textarea id="financeRejectReason" class="input" rows="3" placeholder="مثلاً مبلغ یا رسید نامعتبر است"></textarea>
            </label>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn btn-ghost" data-close-reject>انصراف</button>
            <button type="button" class="btn btn-no" id="financeRejectConfirm">رد کردن</button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/inc/layout_foot.php';
