(function () {
    function fallbackCopy(text) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.cssText = 'position:fixed;left:-9999px;top:0;opacity:0';
        document.body.appendChild(ta);
        ta.focus();
        ta.select();
        ta.setSelectionRange(0, text.length);
        var ok = false;
        try {
            ok = document.execCommand('copy');
        } catch (e) {
            ok = false;
        }
        document.body.removeChild(ta);
        return ok;
    }

    function copyText(text) {
        text = String(text || '').trim();
        if (!text) {
            return Promise.reject(new Error('empty'));
        }
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text).catch(function () {
                return fallbackCopy(text) ? Promise.resolve() : Promise.reject(new Error('clipboard'));
            });
        }
        return fallbackCopy(text) ? Promise.resolve() : Promise.reject(new Error('clipboard'));
    }

    function markCopied(btn) {
        var label = btn.getAttribute('data-label') || 'کپی آدرس';
        btn.textContent = 'کپی شد ✓';
        btn.classList.add('is-copied');
        setTimeout(function () {
            btn.textContent = label;
            btn.classList.remove('is-copied');
        }, 2200);
    }

    function flashFail(btn) {
        btn.textContent = 'خطا — دوباره بزنید';
        btn.classList.add('is-fail');
        setTimeout(function () {
            btn.textContent = btn.getAttribute('data-label') || 'کپی آدرس';
            btn.classList.remove('is-fail');
        }, 2200);
    }

    function handleCopy(btn) {
        var text = btn.getAttribute('data-copy') || '';
        if (!text) {
            var item = btn.closest('.auth-wallet-item');
            var code = item ? item.querySelector('.auth-wallet-addr') : null;
            text = code ? code.textContent : '';
        }
        copyText(text).then(function () {
            markCopied(btn);
        }).catch(function () {
            var item = btn.closest('.auth-wallet-item');
            var code = item ? item.querySelector('.auth-wallet-addr') : null;
            if (code && window.getSelection) {
                var range = document.createRange();
                range.selectNodeContents(code);
                var sel = window.getSelection();
                sel.removeAllRanges();
                sel.addRange(range);
            }
            flashFail(btn);
        });
    }

    document.querySelectorAll('.auth-wallet-copy').forEach(function (btn) {
        if (!btn.getAttribute('data-label')) {
            btn.setAttribute('data-label', btn.textContent.trim());
        }
    });

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.auth-wallet-copy');
        if (btn) {
            e.preventDefault();
            e.stopPropagation();
            handleCopy(btn);
            return;
        }
        var addr = e.target.closest('.auth-wallet-addr');
        if (addr) {
            var item = addr.closest('.auth-wallet-item');
            var copyBtn = item ? item.querySelector('.auth-wallet-copy') : null;
            if (copyBtn) {
                handleCopy(copyBtn);
            }
        }
    });

    var form = document.querySelector('.auth-form');
    if (form) {
        form.addEventListener('submit', function () {
            var loginText = document.getElementById('loginText');
            var loginSpin = document.getElementById('loginSpin');
            var loginBtn = document.getElementById('loginBtn');
            if (loginText) loginText.style.display = 'none';
            if (loginSpin) loginSpin.style.display = 'inline-block';
            if (loginBtn) loginBtn.disabled = true;
        });
    }
}());
