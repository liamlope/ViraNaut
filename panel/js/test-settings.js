(function () {
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
}());
