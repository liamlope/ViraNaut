(function () {
    var search = document.getElementById('botTextsSearch');
    var clearBtn = document.getElementById('botTextsSearchClear');
    var noMatch = document.getElementById('botTextsNoMatch');
    var items = document.querySelectorAll('.bt-item');
    var sections = document.querySelectorAll('.bt-section');
    var catLinks = document.querySelectorAll('.bt-cat-link');
    var focusedTextarea = null;

    var varsVeil = document.getElementById('btVarsVeil');
    var varsDrawer = document.getElementById('btVarsDrawer');
    var varsOpen = document.getElementById('btVarsOpen');
    var varsClose = document.getElementById('btVarsClose');

    document.querySelectorAll('.bt-area').forEach(function (ta) {
        ta.addEventListener('focus', function () {
            focusedTextarea = ta;
        });
        ta.addEventListener('input', function () {
            syncSearch(ta);
            if (search && search.value.trim() !== '') {
                applySearch();
            }
        });
    });

    function syncSearch(ta) {
        var card = ta.closest('.bt-item');
        if (!card) return;
        var title = card.querySelector('.bt-item-title');
        var hint = card.querySelector('.bt-item-hint');
        var idEl = card.querySelector('.bt-item-id');
        var sec = card.closest('.bt-section');
        var parts = [];
        if (title) parts.push(title.textContent);
        if (idEl) parts.push(idEl.textContent);
        if (hint) parts.push(hint.textContent);
        if (sec) {
            var h = sec.querySelector('.bt-section-head h2');
            if (h) parts.push(h.textContent);
        }
        parts.push(ta.value);
        card.setAttribute('data-search', parts.join(' ').toLowerCase());
    }

    function insertVar(varName) {
        if (!focusedTextarea) {
            focusedTextarea = document.querySelector('.bt-area');
        }
        if (!focusedTextarea) return;
        var start = focusedTextarea.selectionStart;
        var end = focusedTextarea.selectionEnd;
        var text = focusedTextarea.value;
        focusedTextarea.value = text.slice(0, start) + varName + text.slice(end);
        focusedTextarea.focus();
        var pos = start + varName.length;
        focusedTextarea.setSelectionRange(pos, pos);
        syncSearch(focusedTextarea);
    }

    document.querySelectorAll('[data-insert-var]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            insertVar(btn.getAttribute('data-insert-var') || '');
        });
    });

    document.querySelectorAll('[data-insert-emoji]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            insertVar(btn.getAttribute('data-insert-emoji') || '');
        });
    });

    try {
        var pending = sessionStorage.getItem('viranaut_pending_emoji');
        if (pending) {
            sessionStorage.removeItem('viranaut_pending_emoji');
            var firstTa = document.querySelector('.bt-area');
            if (firstTa) {
                focusedTextarea = firstTa;
                firstTa.focus();
                insertVar(pending);
            }
        }
    } catch (e) { /* ignore */ }

    function applySearch() {
        var q = (search && search.value ? search.value : '').trim().toLowerCase();
        var anyVisible = false;

        items.forEach(function (item) {
            var hay = item.getAttribute('data-search') || '';
            var show = q === '' || hay.indexOf(q) >= 0;
            item.classList.toggle('is-hidden', !show);
            if (show) anyVisible = true;
        });

        sections.forEach(function (sec) {
            var visibleIn = sec.querySelectorAll('.bt-item:not(.is-hidden)').length > 0;
            sec.classList.toggle('is-hidden', q !== '' && !visibleIn);
        });

        if (noMatch) {
            noMatch.hidden = q === '' || anyVisible;
        }
    }

    if (search) {
        search.addEventListener('input', applySearch);
    }
    if (clearBtn && search) {
        clearBtn.addEventListener('click', function () {
            search.value = '';
            applySearch();
            search.focus();
        });
    }

    catLinks.forEach(function (link) {
        link.addEventListener('click', function (e) {
            catLinks.forEach(function (l) { l.classList.remove('active'); });
            link.classList.add('active');
            if (search && search.value.trim() !== '') {
                e.preventDefault();
                var secId = link.getAttribute('data-section');
                var sec = document.getElementById(secId);
                if (sec) {
                    search.value = '';
                    applySearch();
                    sec.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }
        });
    });

    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (!entry.isIntersecting) return;
            var id = entry.target.id;
            catLinks.forEach(function (link) {
                link.classList.toggle('active', link.getAttribute('data-section') === id);
            });
        });
    }, { rootMargin: '-160px 0px -60% 0px', threshold: 0 });

    sections.forEach(function (sec) {
        observer.observe(sec);
    });

    function openDrawer() {
        if (!varsDrawer || !varsVeil) return;
        varsVeil.hidden = false;
        varsDrawer.hidden = false;
        requestAnimationFrame(function () {
            varsDrawer.classList.add('is-open');
        });
    }

    function closeDrawer() {
        if (!varsDrawer || !varsVeil) return;
        varsDrawer.classList.remove('is-open');
        setTimeout(function () {
            varsVeil.hidden = true;
            varsDrawer.hidden = true;
        }, 200);
    }

    if (varsOpen) varsOpen.addEventListener('click', openDrawer);
    if (varsClose) varsClose.addEventListener('click', closeDrawer);
    if (varsVeil) varsVeil.addEventListener('click', closeDrawer);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeDrawer();
    });
}());
