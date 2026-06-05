<?php
/** @var array<int, array{label:string,network:string,symbol:string,address:string}> $supportWallets */
$supportExtraClass = $supportExtraClass ?? '';
?>
<div class="auth-support<?= $supportExtraClass !== '' ? ' ' . htmlspecialchars($supportExtraClass) : '' ?>">
    <div class="auth-support-title">💎 حمایت از توسعه‌دهنده</div>
    <p class="auth-support-lede">آدرس‌های زیر فقط برای مشارکت‌کنندگان پروژه است — در ربات یا پنل قابل ویرایش نیستند.</p>
    <div class="auth-wallet-list">
        <?php foreach ($supportWallets as $w): ?>
            <div class="auth-wallet-item">
                <div class="auth-wallet-head">
                    <span class="auth-wallet-label"><?= htmlspecialchars($w['label']) ?></span>
                    <span class="auth-wallet-net"><?= htmlspecialchars($w['network']) ?> · <?= htmlspecialchars($w['symbol']) ?></span>
                </div>
                <code class="auth-wallet-addr"><?= htmlspecialchars($w['address']) ?></code>
                <button type="button" class="auth-wallet-copy" data-copy="<?= htmlspecialchars($w['address']) ?>">کپی آدرس</button>
            </div>
        <?php endforeach; ?>
    </div>
</div>
