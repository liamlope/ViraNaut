    </main>
  </div>
</div>

<nav class="bottom-nav">
  <div class="bottom-nav-row">
    <a href="index.php" class="bnav-item <?= ($activeNav ?? '') === 'dashboard' ? 'active' : '' ?>"><?= icon('dashboard', 22) ?><span>داشبورد</span></a>
    <a href="buy.php" class="bnav-item <?= ($activeNav ?? '') === 'buy' ? 'active' : '' ?>"><?= icon('package', 22) ?><span>خرید</span></a>
    <a href="services.php" class="bnav-item <?= ($activeNav ?? '') === 'services' ? 'active' : '' ?>"><?= icon('server', 22) ?><span>سرویس</span></a>
    <a href="finance.php" class="bnav-item <?= in_array($activeNav ?? '', ['finance', 'transactions'], true) ? 'active' : '' ?>"><?= icon('wallet', 22) ?><span>مالی</span></a>
    <a href="settings.php" class="bnav-item <?= ($activeNav ?? '') === 'settings' ? 'active' : '' ?>"><?= icon('settings', 22) ?><span>تنظیم</span></a>
  </div>
</nav>

<div class="modal-veil" id="checkout-modal">
  <div class="modal">
    <div class="modal-head"><h3>پیش‌فاکتور</h3><button class="modal-x" onclick="closeModal('checkout-modal')">×</button></div>
    <div class="modal-body" id="checkout-body"></div>
    <div class="modal-foot">
      <button class="btn btn-primary" id="checkout-confirm"><?= agent_t('confirm', $lang ?? 'fa') ?></button>
      <button class="btn btn-ghost" onclick="closeModal('checkout-modal')"><?= agent_t('cancel', $lang ?? 'fa') ?></button>
    </div>
  </div>
</div>

<div class="modal-veil" id="onboard-modal">
  <div class="modal">
    <div class="modal-head"><h3>خوش آمدید</h3></div>
    <div class="modal-body">
      <ol style="line-height:2">
        <li>از بخش «خرید سرویس» کانفیگ جدید بسازید.</li>
        <li>سرویس‌ها را در «لیست سرویس‌ها» مدیریت کنید.</li>
        <li>توکن API را در تنظیمات کپی کنید.</li>
      </ol>
    </div>
    <div class="modal-foot"><button class="btn btn-primary" id="onboard-done">شروع</button></div>
  </div>
</div>

<script src="<?= agent_panel_asset('../panel/js/panel_progress.js') ?>"></script>
<?php foreach ($extraJs ?? [] as $src): ?>
<script src="<?= agent_panel_asset($src) ?>"></script>
<?php endforeach; ?>
<script src="<?= agent_panel_asset('../panel/js/app.js') ?>"></script>
<script src="<?= agent_panel_asset('assets/agent.js') ?>"></script>
<?php if (!empty($footerInlineJs)): ?>
<script><?= $footerInlineJs ?></script>
<?php endif; ?>
</body>
</html>
