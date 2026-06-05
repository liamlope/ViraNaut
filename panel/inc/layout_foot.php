<?php ?>
  </main>
</div>

<nav class="bottom-nav">
  <div class="bottom-nav-row">
    <a href="index.php"   class="bnav-item <?= ($activeNav??'')==='dashboard'?'active':''?>"><?= icon('dashboard',22) ?><span>داشبورد</span></a>
    <a href="users.php"   class="bnav-item <?= ($activeNav??'')==='users'?'active':''?>"><?= icon('users',22) ?><span>کاربران</span></a>
    <a href="invoice.php" class="bnav-item <?= ($activeNav??'')==='invoice'?'active':''?>"><?= icon('invoice',22) ?><span>سفارش</span></a>
    <a href="finance.php" class="bnav-item <?= in_array($activeNav??'', ['finance','payment'], true)?'active':''?>"><?= icon('card',22) ?><span>مالی</span></a>
    <a href="bot.php" class="bnav-item <?= !empty($botNavActive)?'active':''?>"><?= icon('bot',22) ?><span>ربات</span></a>
  </div>
</nav>
</div>

<script src="<?= panel_asset('js/panel_progress.js') ?>"></script>
<script src="<?= panel_asset('js/bot_tools.js') ?>"></script>
<?php foreach ($extraJs ?? [] as $src): ?>
<script src="<?= panel_asset($src) ?>"></script>
<?php endforeach; ?>
<script src="<?= panel_asset('js/app.js') ?>"></script>
<?php if (!empty($footerInlineJs)): ?>
<script><?= $footerInlineJs ?></script>
<?php endif; ?>
</body>
</html>
