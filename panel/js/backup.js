(function () {
    if (!window.mirzaBotTools) return;

    var root = document.querySelector('.backup-page');
    var csrf = root ? root.getAttribute('data-csrf') : window.mirzaBotTools.csrf();

    function apiUrl(action, extra) {
        var u = window.mirzaBotTools.base() + 'api/bot_tools.php?action=' + encodeURIComponent(action);
        if (extra) {
            Object.keys(extra).forEach(function (k) {
                u += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(extra[k]);
            });
        }
        return u;
    }

    function doRestart(btn, statusEl) {
        if (!confirm('وب‌هوک ربات دوباره تنظیم شود؟ (معادل ری‌استارت برای ربات تلگرام)')) return;
        if (statusEl) statusEl.textContent = 'در حال ری‌استارت…';
        if (btn) btn.disabled = true;
        window.mirzaBotTools.post('bot_restart', {}).then(function (d) {
            if (statusEl) statusEl.textContent = d.msg || (d.ok ? 'انجام شد' : 'خطا');
            if (window.toast) toast(d.msg, d.ok ? 'ok' : 'no');
        }).finally(function () {
            if (btn) btn.disabled = false;
        });
    }

    var dlFull = document.getElementById('dlFullBackup');
    if (dlFull) {
        dlFull.onclick = function () {
            var st = document.getElementById('backupFullStatus');
            if (st) st.textContent = 'در حال ساخت ZIP…';
            dlFull.disabled = true;
            window.location.href = apiUrl('backup_full_zip', { _csrf: csrf });
            setTimeout(function () {
                dlFull.disabled = false;
                if (st) st.textContent = 'اگر دانلود شروع نشد، دوباره کلیک کنید.';
            }, 4000);
        };
    }

    var restartBtn = document.getElementById('restartBot');
    if (restartBtn) {
        restartBtn.onclick = function () {
            doRestart(restartBtn, document.getElementById('backupFullStatus'));
        };
    }

    var restoreForm = document.getElementById('restoreForm');
    if (restoreForm) {
        restoreForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var fileInput = document.getElementById('restoreZip');
            var st = document.getElementById('restoreStatus');
            if (!fileInput || !fileInput.files || !fileInput.files[0]) {
                if (window.toast) toast('فایل ZIP را انتخاب کنید', 'no');
                return;
            }
            if (!confirm('هشدار: دیتابیس فعلی با بکاپ جایگزین می‌شود. ادامه می‌دهید؟')) return;
            if (!confirm('آیا مطمئن هستید؟ این عملیات برگشت‌پذیر نیست.')) return;

            var fd = new FormData();
            fd.append('_csrf', csrf);
            fd.append('action', 'backup_restore');
            fd.append('confirm', 'yes');
            fd.append('backup_zip', fileInput.files[0]);
            if (document.getElementById('restoreRestart') && document.getElementById('restoreRestart').checked) {
                fd.append('restart_after', '1');
            }

            if (st) st.textContent = 'در حال بازیابی… ممکن است چند دقیقه طول بکشد.';
            restoreForm.querySelector('button[type=submit]').disabled = true;

            fetch(window.mirzaBotTools.base() + 'api/bot_tools.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
            }).then(function (r) { return r.json(); }).then(function (d) {
                if (st) st.textContent = d.msg || (d.ok ? 'بازیابی انجام شد' : 'خطا');
                if (window.toast) toast(d.msg, d.ok ? 'ok' : 'no');
                if (d.ok) restoreForm.reset();
            }).catch(function (err) {
                if (st) st.textContent = 'خطا: ' + err.message;
            }).finally(function () {
                var btn = restoreForm.querySelector('button[type=submit]');
                if (btn) btn.disabled = false;
            });
        });
    }

    var dlSql = document.getElementById('dlBackup');
    if (dlSql) {
        dlSql.onclick = function () {
            var st = document.getElementById('backupStatus');
            if (st) st.textContent = 'در حال آماده‌سازی…';
            window.mirzaBotTools.get('backup_sql').then(function (d) {
                if (!d.ok || !d.sql) {
                    if (st) st.textContent = d.msg || 'خطا';
                    return;
                }
                var blob = new Blob([d.sql], { type: 'application/sql;charset=utf-8' });
                var a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = 'mirza-backup-' + new Date().toISOString().slice(0, 10) + '.sql';
                a.click();
                if (st) st.textContent = 'دانلود آغاز شد';
            });
        };
    }
}());
