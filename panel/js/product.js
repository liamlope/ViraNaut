(function () {
    var P = window.MirzaInboundPicker;
    if (!P) {
        return;
    }

    window.openEditModal = function (p) {
        document.getElementById('edit_id').value = p.id || '';
        document.getElementById('edit_name').value = p.name_product || '';
        document.getElementById('edit_price').value = p.price_product || '';
        document.getElementById('edit_volume').value = p.Volume_constraint || '';
        document.getElementById('edit_time').value = p.Service_time || '';
        document.getElementById('edit_cat').value = p.category || '';
        document.getElementById('edit_agent').value = p.agent || '';
        document.getElementById('edit_note').value = p.note || '';
        var hidden = document.getElementById('edit_inbounds');
        if (hidden) {
            hidden.value = (p.inbounds && p.inbounds !== 'null') ? p.inbounds : '';
        }
        var sel = document.getElementById('edit_panel');
        if (sel) {
            for (var i = 0; i < sel.options.length; i++) {
                sel.options[i].selected = sel.options[i].value === (p.Location || '');
            }
        }
        openModal('editModal');
        setTimeout(function () {
            P.loadPicker('edit_inbound_picker', 'edit_panel', 'edit_inbounds', p.inbounds || '');
        }, 50);
    };

    function shouldValidateInbounds(pickerId) {
        var picker = document.getElementById(pickerId);
        return !!(picker && picker.querySelector('.inbound-id-cb'));
    }

    document.addEventListener('DOMContentLoaded', function () {
        var addPanel = document.getElementById('add_panel');
        if (addPanel) {
            addPanel.addEventListener('change', function () {
                P.loadPicker('add_inbound_picker', 'add_panel', 'add_inbounds', '');
            });
        }
        var editPanel = document.getElementById('edit_panel');
        if (editPanel) {
            editPanel.addEventListener('change', function () {
                P.loadPicker('edit_inbound_picker', 'edit_panel', 'edit_inbounds', document.getElementById('edit_inbounds').value);
            });
        }
        var addModal = document.getElementById('addModal');
        if (addModal) {
            var obs = new MutationObserver(function () {
                if (addModal.classList.contains('open')) {
                    P.loadPicker('add_inbound_picker', 'add_panel', 'add_inbounds', '');
                }
            });
            obs.observe(addModal, { attributes: true, attributeFilter: ['class'] });
        }
        var addForm = document.querySelector('#addModal form');
        if (addForm) {
            addForm.addEventListener('submit', function (ev) {
                if (shouldValidateInbounds('add_inbound_picker') && !P.validateBeforeSubmit('add_inbound_picker', 'add_inbounds', true)) {
                    ev.preventDefault();
                    return;
                }
                P.syncHiddenInput(document.getElementById('add_inbound_picker'), document.getElementById('add_inbounds'), false);
            });
        }
        var editForm = document.querySelector('#editModal form');
        if (editForm) {
            editForm.addEventListener('submit', function (ev) {
                if (shouldValidateInbounds('edit_inbound_picker') && !P.validateBeforeSubmit('edit_inbound_picker', 'edit_inbounds', true)) {
                    ev.preventDefault();
                    return;
                }
                P.syncHiddenInput(document.getElementById('edit_inbound_picker'), document.getElementById('edit_inbounds'), false);
            });
        }
    });
}());
