(function () {
    document.querySelectorAll('.toggle-group').forEach(function (group) {
        group.addEventListener('change', function (e) {
            var input = e.target;
            if (input.type !== 'radio') return;
            group.querySelectorAll('.toggle-chip').forEach(function (chip) {
                chip.classList.toggle('active', chip.contains(input) && input.checked);
            });
            group.querySelectorAll('.toggle-chip').forEach(function (chip) {
                var r = chip.querySelector('input[type="radio"]');
                chip.classList.toggle('active', r && r.checked);
            });
        });
    });
}());
