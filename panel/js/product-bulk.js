(function () {
    function updateCount() {
        var boxes = document.querySelectorAll('.prod-row-cb');
        var checked = document.querySelectorAll('.prod-row-cb:checked');
        var el = document.getElementById('bulkSelectedCount');
        if (el) {
            el.textContent = String(checked.length);
        }
        var all = document.getElementById('prodSelectAll');
        if (all && boxes.length) {
            all.checked = checked.length === boxes.length;
            all.indeterminate = checked.length > 0 && checked.length < boxes.length;
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var selectAll = document.getElementById('prodSelectAll');
        if (selectAll) {
            selectAll.addEventListener('change', function () {
                document.querySelectorAll('.prod-row-cb').forEach(function (cb) {
                    cb.checked = selectAll.checked;
                });
                updateCount();
            });
        }
        document.querySelectorAll('.prod-row-cb').forEach(function (cb) {
            cb.addEventListener('change', updateCount);
        });
        var form = document.getElementById('productBulkForm');
        if (form) {
            form.addEventListener('submit', function (ev) {
                if (document.querySelectorAll('.prod-row-cb:checked').length === 0) {
                    ev.preventDefault();
                    alert('حداقل یک محصول انتخاب کنید.');
                }
            });
        }
        updateCount();
    });
}());
