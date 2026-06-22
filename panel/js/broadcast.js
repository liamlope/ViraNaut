(function () {
    if (!window.viraBotTools) return;
    document.getElementById('startBroadcast').onclick = function () {
        var msg = document.getElementById('broadcastMsg').value.trim();
        if (!msg) { if (window.toast) toast('متن را وارد کنید', 'no'); return; }
        if (!confirm('ارسال به همه کاربران فعال شروع شود؟')) return;
        var prog = document.getElementById('broadcastProgress');
        var offset = 0;
        function step() {
            prog.textContent = 'در حال ارسال… offset ' + offset;
            window.viraBotTools.post('broadcast_batch', { message: msg, offset: String(offset), batch: '25' }).then(function (d) {
                if (!d.ok) { prog.textContent = d.msg; return; }
                offset = d.offset;
                prog.textContent = 'ارسال شد: ' + offset + ' / ' + d.total;
                if (!d.done) setTimeout(step, 800);
                else { prog.textContent = 'تمام شد — ' + d.total + ' کاربر'; if (window.toast) toast('ارسال تمام شد', 'ok'); }
            });
        }
        step();
    };
}());
