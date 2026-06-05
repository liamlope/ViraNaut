(function () {
    var veil = null;
    var fill = null;
    var titleEl = null;
    var subEl = null;
    var stepsEl = null;
    var tickTimer = null;
    var stepIdx = 0;
    var steps = [];

    function loadBar(action) {
        if (window.panelLoadBar && typeof window.panelLoadBar[action] === 'function') {
            window.panelLoadBar[action]();
        }
    }

    function ensure() {
        if (veil && fill && titleEl) return;
        veil = document.getElementById('panelProgressVeil');
        if (!veil) {
            veil = document.createElement('div');
            veil.id = 'panelProgressVeil';
            veil.className = 'pp-veil';
            veil.hidden = true;
            veil.setAttribute('aria-live', 'polite');
            veil.innerHTML =
                '<div class="pp-card">' +
                '<div class="pp-head">' +
                '<div class="pp-spinner" aria-hidden="true"></div>' +
                '<div><h3 class="pp-title" id="panelProgressTitle">در حال انجام…</h3>' +
                '<p class="pp-sub" id="panelProgressSub"></p></div></div>' +
                '<div class="pp-bar-wrap"><div class="pp-bar-fill" id="panelProgressFill"></div></div>' +
                '<ul class="pp-steps" id="panelProgressSteps"></ul>' +
                '<p class="pp-hint">لطفاً صبر کنید — پنجره را نبندید.</p></div>';
            document.body.appendChild(veil);
        }
        fill = document.getElementById('panelProgressFill');
        titleEl = document.getElementById('panelProgressTitle');
        subEl = document.getElementById('panelProgressSub');
        stepsEl = document.getElementById('panelProgressSteps');
    }

    function renderSteps() {
        if (!stepsEl) return;
        stepsEl.innerHTML = '';
        steps.forEach(function (s, i) {
            var li = document.createElement('li');
            li.className = 'pp-step';
            if (i < stepIdx) li.classList.add('is-done');
            if (i === stepIdx) li.classList.add('is-active');
            var icon = i < stepIdx ? '✓' : (i === stepIdx ? '◉' : '○');
            li.innerHTML = '<span class="pp-step-icon">' + icon + '</span><span>' + s + '</span>';
            stepsEl.appendChild(li);
        });
    }

    function setPct(pct) {
        if (fill) fill.style.width = Math.min(100, Math.max(0, pct)) + '%';
    }

    function start(opts) {
        opts = opts || {};
        ensure();
        stepIdx = 0;
        steps = opts.steps || [];
        veil.classList.remove('is-success', 'is-error');
        veil.hidden = false;
        if (titleEl) titleEl.textContent = opts.title || 'در حال انجام…';
        if (subEl) subEl.textContent = opts.subtitle || '';
        setPct(opts.pct || 5);
        renderSteps();
        loadBar('start');
        clearInterval(tickTimer);
        if (steps.length > 1) {
            tickTimer = setInterval(function () {
                if (stepIdx < steps.length - 1) {
                    stepIdx++;
                    var pct = 10 + Math.round((stepIdx / (steps.length - 1)) * 75);
                    setPct(pct);
                    renderSteps();
                    if (subEl && steps[stepIdx]) subEl.textContent = steps[stepIdx];
                }
            }, opts.stepMs || 650);
        }
    }

    function finish(ok, msg) {
        clearInterval(tickTimer);
        tickTimer = null;
        if (!veil) return;
        stepIdx = steps.length;
        renderSteps();
        setPct(100);
        veil.classList.add(ok ? 'is-success' : 'is-error');
        if (titleEl) titleEl.textContent = ok ? (msg || 'انجام شد') : (msg || 'خطا');
        if (subEl) subEl.textContent = ok ? 'عملیات با موفقیت تمام شد' : 'لطفاً دوباره تلاش کنید';
        loadBar('done');
        setTimeout(function () {
            veil.hidden = true;
            veil.classList.remove('is-success', 'is-error');
            setPct(0);
        }, ok ? 1400 : 2200);
    }

    function run(opts, work) {
        start(opts);
        return Promise.resolve().then(work).then(function (result) {
            var ok = true;
            var msg = opts.doneTitle || 'انجام شد';
            if (result && typeof result.ok === 'boolean') {
                ok = result.ok;
                if (!ok && result.msg) msg = result.msg;
            }
            finish(ok, msg);
            return result;
        }).catch(function (err) {
            finish(false, (err && err.message) ? err.message : 'خطا در ارتباط');
            throw err;
        });
    }

    window.PanelProgress = {
        start: start,
        finish: finish,
        run: run,
        setPct: setPct
    };
}());
