(function () {
    var meta = document.getElementById('um-user-meta');
    var balance = meta ? parseInt(meta.dataset.userBalance || '0', 10) : 0;

    window.umSwitchCategory = function (cat) {
        document.querySelectorAll('.um-cat-btn').forEach(function (btn) {
            btn.classList.toggle('is-active', btn.dataset.cat === cat);
        });
        document.querySelectorAll('.um-cat-pane').forEach(function (pane) {
            pane.style.display = pane.dataset.cat === cat ? 'grid' : 'none';
        });
    };

    window.umOpenWallet = function (mode) {
        umSetWalletMode(mode || 'add');
        openModal('walletModal');
    };

    window.umSetWalletMode = function (mode) {
        var form = document.getElementById('walletForm');
        if (!form) return;
        form.querySelector('[name="wallet_mode"]').value = mode;
        document.querySelectorAll('.um-wallet-tab').forEach(function (tab) {
            tab.classList.toggle('is-active', tab.dataset.mode === mode);
        });
        var amountWrap = document.getElementById('walletAmountWrap');
        var zeroNote = document.getElementById('walletZeroNote');
        var submitBtn = document.getElementById('walletSubmitBtn');
        var amountInput = form.querySelector('[name="amount"]');
        if (mode === 'zero') {
            if (amountWrap) amountWrap.style.display = 'none';
            if (zeroNote) zeroNote.style.display = 'block';
            if (submitBtn) submitBtn.textContent = 'صفر کردن موجودی';
            if (amountInput) amountInput.removeAttribute('required');
        } else {
            if (amountWrap) amountWrap.style.display = 'block';
            if (zeroNote) zeroNote.style.display = 'none';
            if (amountInput) amountInput.setAttribute('required', 'required');
            if (mode === 'add') {
                if (submitBtn) submitBtn.textContent = 'افزودن به موجودی';
                if (amountInput) {
                    amountInput.min = '1000';
                    amountInput.placeholder = 'مثلاً ۵۰٬۰۰۰';
                }
            } else if (mode === 'deduct') {
                if (submitBtn) submitBtn.textContent = 'کسر از موجودی';
                if (amountInput) {
                    amountInput.min = '1000';
                    amountInput.placeholder = 'مثلاً ۱۰٬۰۰۰';
                }
            } else {
                if (submitBtn) submitBtn.textContent = 'تنظیم موجودی';
                if (amountInput) {
                    amountInput.min = '0';
                    amountInput.value = balance;
                }
            }
        }
        umUpdateWalletPreview();
    };

    window.umUpdateWalletPreview = function () {
        var preview = document.getElementById('walletPreview');
        var form = document.getElementById('walletForm');
        if (!preview || !form) return;
        var mode = form.querySelector('[name="wallet_mode"]').value;
        var amountEl = form.querySelector('[name="amount"]');
        var amount = parseInt(amountEl && amountEl.value ? amountEl.value : '0', 10) || 0;
        var next = balance;
        if (mode === 'add') next = balance + amount;
        else if (mode === 'deduct') next = Math.max(0, balance - amount);
        else if (mode === 'set') next = amount;
        else if (mode === 'zero') next = 0;
        preview.innerHTML = 'موجودی فعلی: <b>' + balance.toLocaleString('fa-IR') + '</b> ت → پس از عملیات: <b>' + next.toLocaleString('fa-IR') + '</b> ت';
    };

    document.addEventListener('DOMContentLoaded', function () {
        umSwitchCategory('fin');
        var amountInput = document.querySelector('#walletForm [name="amount"]');
        if (amountInput) {
            amountInput.addEventListener('input', umUpdateWalletPreview);
        }
    });
})();
