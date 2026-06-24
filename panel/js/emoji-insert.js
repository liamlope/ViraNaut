(function () {
    var EMOJI_RE = /\{emoji:[^}]+\}/g;

    function countEmojiPlaceholders(text) {
        if (!text) return 0;
        var m = text.match(EMOJI_RE);
        return m ? m.length : 0;
    }

    function maxEmoji(field) {
        var m = field.getAttribute('data-emoji-max');
        if (m === null || m === '') return 1;
        var n = parseInt(m, 10);
        return isNaN(n) ? 1 : n;
    }

    function warnField(field) {
        var wrap = field.closest('.vira-emoji-wrap') || field.parentElement;
        var warn = wrap ? wrap.querySelector('.vira-emoji-warn') : null;
        if (!warn) return;
        var over = countEmojiPlaceholders(field.value) > maxEmoji(field);
        warn.hidden = !over;
        field.classList.toggle('vira-emoji-over', over);
    }

    function insertAtCursor(field, text) {
        var max = maxEmoji(field);
        if (/^\{emoji:[^}]+\}$/.test(text) && countEmojiPlaceholders(field.value) >= max) {
            warnField(field);
            return false;
        }
        var start = field.selectionStart;
        var end = field.selectionEnd;
        if (start == null) {
            field.value += text;
            warnField(field);
            return true;
        }
        var val = field.value;
        field.value = val.slice(0, start) + text + val.slice(end);
        var pos = start + text.length;
        field.setSelectionRange(pos, pos);
        warnField(field);
        return true;
    }

    var focusedField = null;
    var dragEmojiCode = null;

    document.addEventListener('focusin', function (e) {
        if (e.target && e.target.classList && e.target.classList.contains('vira-emoji-field')) {
            focusedField = e.target;
        }
    });

    document.addEventListener('input', function (e) {
        if (e.target && e.target.classList && e.target.classList.contains('vira-emoji-field')) {
            warnField(e.target);
        }
    });

    document.addEventListener('dragover', function (e) {
        var field = e.target && e.target.classList && e.target.classList.contains('vira-emoji-field') ? e.target : null;
        if (!field || !dragEmojiCode) return;
        e.preventDefault();
        field.classList.add('bt-drag-over');
    });

    document.addEventListener('dragleave', function (e) {
        if (e.target && e.target.classList && e.target.classList.contains('vira-emoji-field')) {
            e.target.classList.remove('bt-drag-over');
        }
    });

    document.addEventListener('drop', function (e) {
        var field = e.target && e.target.classList && e.target.classList.contains('vira-emoji-field') ? e.target : null;
        if (!field || !dragEmojiCode) return;
        e.preventDefault();
        field.classList.remove('bt-drag-over');
        focusedField = field;
        field.focus();
        insertAtCursor(field, dragEmojiCode);
        dragEmojiCode = null;
    });

    function insertEmoji(code) {
        if (!focusedField) {
            focusedField = document.querySelector('.vira-emoji-field');
        }
        if (!focusedField) return;
        if (!insertAtCursor(focusedField, code)) return;
        focusedField.focus();
    }

    document.addEventListener('click', function (e) {
        var chip = e.target && e.target.closest ? e.target.closest('[data-insert-emoji]') : null;
        if (!chip) return;
        insertEmoji(chip.getAttribute('data-insert-emoji') || '');
    });

    document.addEventListener('dragstart', function (e) {
        var chip = e.target && e.target.closest ? e.target.closest('[data-insert-emoji]') : null;
        if (!chip) return;
        dragEmojiCode = chip.getAttribute('data-insert-emoji') || '';
        chip.classList.add('bt-emoji-dragging');
        if (e.dataTransfer) {
            e.dataTransfer.effectAllowed = 'copy';
            e.dataTransfer.setData('text/plain', dragEmojiCode);
        }
    });

    document.addEventListener('dragend', function (e) {
        var chip = e.target && e.target.closest ? e.target.closest('[data-insert-emoji]') : null;
        if (!chip) return;
        chip.classList.remove('bt-emoji-dragging');
        dragEmojiCode = null;
    });

    document.querySelectorAll('.vira-emoji-field').forEach(warnField);

    var pending = sessionStorage.getItem('viranaut_pending_emoji');
    if (pending) {
        sessionStorage.removeItem('viranaut_pending_emoji');
        setTimeout(function () {
            insertEmoji(pending);
        }, 80);
    }
})();
