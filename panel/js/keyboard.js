(function () {
  var preview = document.getElementById('kbPreview');
  var palette = document.getElementById('kbPalette');
  var form = document.getElementById('kbSaveForm');
  var jsonInput = document.getElementById('keyboardJson');
  var addRowBtn = document.getElementById('kbAddRow');
  if (!preview || !form) return;

  var styleOptions = window.KB_STYLE_OPTIONS || { '': 'پیش‌فرض' };

  var dragKey = null;
  var dragLabel = null;
  var dragFromPalette = false;

  function buildStyleSelect(selectedStyle) {
    var sel = document.createElement('select');
    sel.className = 'kb-style-select input input-sm';
    Object.keys(styleOptions).forEach(function (key) {
      var opt = document.createElement('option');
      opt.value = key;
      opt.textContent = styleOptions[key];
      if ((selectedStyle || '') === key) {
        opt.selected = true;
      }
      sel.appendChild(opt);
    });
    return sel;
  }

  function createBtn(key, label, style) {
    var el = document.createElement('div');
    el.className = 'kb-btn';
    el.draggable = true;
    el.dataset.key = key;
    el.dataset.label = label;
    el.dataset.style = style || '';
    el.innerHTML =
      '<span class="kb-btn-label"></span>' +
      '<span class="kb-btn-id"></span>' +
      '<div class="kb-btn-meta kb-btn-meta-style-only"></div>' +
      '<button type="button" class="kb-btn-remove" title="حذف" aria-label="حذف">&times;</button>';
    el.querySelector('.kb-btn-label').textContent = label;
    el.querySelector('.kb-btn-id').textContent = key;
    var meta = el.querySelector('.kb-btn-meta');
    var styleLabel = document.createElement('label');
    styleLabel.className = 'kb-mini-label';
    styleLabel.textContent = 'استایل دکمه';
    meta.appendChild(styleLabel);
    meta.appendChild(buildStyleSelect(style));
    bindBtn(el);
    return el;
  }

  function createPaletteItem(key, label) {
    var empty = palette.querySelector('.kb-palette-empty');
    if (empty) empty.remove();
    var el = document.createElement('div');
    el.className = 'kb-palette-item';
    el.draggable = true;
    el.dataset.key = key;
    el.dataset.label = label;
    el.innerHTML = '<span></span><code></code>';
    el.querySelector('span').textContent = label;
    el.querySelector('code').textContent = key;
    bindPaletteItem(el);
    palette.appendChild(el);
    return el;
  }

  function removePaletteItem(key) {
    var item = palette.querySelector('.kb-palette-item[data-key="' + key + '"]');
    if (item) item.remove();
    if (!palette.querySelector('.kb-palette-item')) {
      var p = document.createElement('p');
      p.className = 'cf kb-palette-empty';
      p.style.fontSize = '.82rem';
      p.textContent = 'همه دکمه‌ها در منو هستند.';
      palette.appendChild(p);
    }
  }

  function createRow() {
    var row = document.createElement('div');
    row.className = 'kb-row';
    var add = document.createElement('button');
    add.type = 'button';
    add.className = 'kb-row-add';
    add.title = 'افزودن دکمه به این ردیف';
    add.textContent = '+';
    add.addEventListener('click', function () {
      pickForRow(row);
    });
    row.appendChild(add);
    bindRow(row);
    return row;
  }

  function pickForRow(row) {
    var first = palette.querySelector('.kb-palette-item');
    if (!first) {
      alert('دکمه‌ای برای افزودن باقی نمانده است.');
      return;
    }
    addBtnToRow(row, first.dataset.key, first.dataset.label);
    first.remove();
    if (!palette.querySelector('.kb-palette-item')) {
      var p = document.createElement('p');
      p.className = 'cf kb-palette-empty';
      p.style.fontSize = '.82rem';
      p.textContent = 'همه دکمه‌ها در منو هستند.';
      palette.appendChild(p);
    }
  }

  function addBtnToRow(row, key, label, style) {
    var addBtn = row.querySelector('.kb-row-add');
    var btn = createBtn(key, label, style);
    row.insertBefore(btn, addBtn);
  }

  function serialize() {
    var rows = [];
    preview.querySelectorAll('.kb-row').forEach(function (row) {
      var btns = [];
      row.querySelectorAll('.kb-btn').forEach(function (btn) {
        if (!btn.dataset.key) return;
        var item = { text: btn.dataset.key };
        var styleSel = btn.querySelector('.kb-style-select');
        if (styleSel && styleSel.value) {
          item.style = styleSel.value;
        }
        btns.push(item);
      });
      if (btns.length) rows.push(btns);
    });
    return rows;
  }

  function bindBtn(btn) {
    btn.addEventListener('dragstart', function (e) {
      dragKey = btn.dataset.key;
      dragLabel = btn.dataset.label;
      dragFromPalette = false;
      btn.classList.add('kb-dragging');
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', dragKey);
    });
    btn.addEventListener('dragend', function () {
      btn.classList.remove('kb-dragging');
      preview.querySelectorAll('.kb-row').forEach(function (r) {
        r.classList.remove('kb-drag-over');
      });
    });
    btn.querySelector('.kb-btn-remove').addEventListener('click', function (e) {
      e.stopPropagation();
      var key = btn.dataset.key;
      var label = btn.dataset.label;
      btn.remove();
      createPaletteItem(key, label);
      var row = btn.closest('.kb-row');
      if (row && !row.querySelector('.kb-btn')) row.remove();
    });
  }

  function bindPaletteItem(item) {
    item.addEventListener('click', function () {
      var rows = preview.querySelectorAll('.kb-row');
      var row = rows.length ? rows[rows.length - 1] : null;
      if (!row) {
        row = createRow();
        preview.appendChild(row);
      }
      addBtnToRow(row, item.dataset.key, item.dataset.label);
      item.remove();
      if (!palette.querySelector('.kb-palette-item')) {
        var p = document.createElement('p');
        p.className = 'cf kb-palette-empty';
        p.style.fontSize = '.82rem';
        p.textContent = 'همه دکمه‌ها در منو هستند.';
        palette.appendChild(p);
      }
    });
    item.addEventListener('dragstart', function (e) {
      dragKey = item.dataset.key;
      dragLabel = item.dataset.label;
      dragFromPalette = true;
      item.classList.add('kb-dragging');
      e.dataTransfer.effectAllowed = 'copy';
      e.dataTransfer.setData('text/plain', dragKey);
    });
    item.addEventListener('dragend', function () {
      item.classList.remove('kb-dragging');
    });
  }

  function bindRow(row) {
    row.addEventListener('dragover', function (e) {
      e.preventDefault();
      row.classList.add('kb-drag-over');
    });
    row.addEventListener('dragleave', function () {
      row.classList.remove('kb-drag-over');
    });
    row.addEventListener('drop', function (e) {
      e.preventDefault();
      row.classList.remove('kb-drag-over');
      var key = e.dataTransfer.getData('text/plain') || dragKey;
      if (!key) return;
      var label = dragLabel || key;
      if (dragFromPalette) {
        var pal = palette.querySelector('.kb-palette-item[data-key="' + key + '"]');
        if (pal) pal.remove();
        if (!palette.querySelector('.kb-palette-item')) {
          var empty = palette.querySelector('.kb-palette-empty');
          if (empty) empty.remove();
        }
        addBtnToRow(row, key, label);
        dragFromPalette = false;
        return;
      }
      var moving = preview.querySelector('.kb-btn[data-key="' + key + '"]');
      var style = '';
      if (moving) {
        label = moving.dataset.label || label;
        var styleSel = moving.querySelector('.kb-style-select');
        style = styleSel ? styleSel.value : '';
        moving.remove();
        var oldRow = moving.closest('.kb-row');
        if (oldRow && !oldRow.querySelector('.kb-btn')) oldRow.remove();
      }
      addBtnToRow(row, key, label, style);
    });
  }

  preview.querySelectorAll('.kb-btn').forEach(bindBtn);
  preview.querySelectorAll('.kb-row').forEach(function (row) {
    bindRow(row);
    var add = row.querySelector('.kb-row-add');
    if (add) {
      add.addEventListener('click', function () {
        pickForRow(row);
      });
    }
  });
  if (palette) palette.querySelectorAll('.kb-palette-item').forEach(bindPaletteItem);

  if (addRowBtn) {
    addRowBtn.addEventListener('click', function () {
      var row = createRow();
      preview.appendChild(row);
    });
  }

  form.addEventListener('submit', function (e) {
    var data = serialize();
    if (!data.length) {
      e.preventDefault();
      alert('حداقل یک دکمه در منو لازم است.');
      return;
    }
    jsonInput.value = JSON.stringify(data);
  });
})();
