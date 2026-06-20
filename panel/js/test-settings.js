(function () {
    var P = window.MirzaInboundPicker;

    document.querySelectorAll('.toggle-group').forEach(function (group) {
        group.addEventListener('change', function (e) {
            var input = e.target;
            if (input.type !== 'radio') return;
            group.querySelectorAll('.toggle-chip').forEach(function (chip) {
                var r = chip.querySelector('input[type="radio"]');
                chip.classList.toggle('active', r && r.checked);
            });
            var card = group.closest('.test-panel-card');
            if (!card) return;
            var panelOn = card.querySelector('input[name*="[status]"]:checked');
            var active = panelOn && panelOn.value === 'active';
            card.classList.toggle('is-inactive', !active);
        });
    });

    document.addEventListener('DOMContentLoaded', function () {
        if (!P) return;
        document.querySelectorAll('.inbound-picker-box[data-panel]').forEach(function (box) {
            var panel = box.getAttribute('data-panel') || '';
            var initial = box.getAttribute('data-initial') || '';
            var hidden = box.parentElement && box.parentElement.querySelector('input[type="hidden"][name*="test_inbounds"]');
            if (!hidden || !panel) return;
            var pickerId = box.id;
            P.loadPicker(pickerId, null, hidden.id, initial, panel, { required: true });
        });
        var form = document.getElementById('testPanelsForm');
        if (form) {
            form.addEventListener('submit', function (ev) {
                var ok = true;
                document.querySelectorAll('.test-panel-inbound').forEach(function (wrap) {
                    var picker = wrap.querySelector('.inbound-picker-box');
                    var hidden = wrap.querySelector('input[type="hidden"]');
                    var card = wrap.closest('.test-panel-card');
                    var testOn = card && card.querySelector('input[name*="[TestAccount]"]:checked');
                    if (!testOn || testOn.value !== 'ONTestAccount') return;
                    if (picker && hidden && !P.validateBeforeSubmit(picker.id, hidden.id, true)) {
                        ok = false;
                    } else if (picker && hidden) {
                        P.syncHiddenInput(picker, hidden, true);
                    }
                });
                if (!ok) ev.preventDefault();
            });
        }
    });
}());
