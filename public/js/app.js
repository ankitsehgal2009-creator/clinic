// SmileDesk screens: small helpers in plain JavaScript, no build step needed.
(function () {
  'use strict';

  var CSRF = document.querySelector('meta[name="csrf-token"]').content;
  var MON = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  var WD = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

  function $(sel, root) { return (root || document).querySelector(sel); }
  function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
  function el(tag, attrs, html) {
    var e = document.createElement(tag);
    for (var k in attrs || {}) e.setAttribute(k, attrs[k]);
    if (html != null) e.innerHTML = html;
    return e;
  }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function pad(n) { return String(n).padStart(2, '0'); }
  function key(y, m, d) { return y + '-' + pad(m + 1) + '-' + pad(d); }
  function todayKey() { return new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Kolkata' }).format(new Date()); }
  function fmtKey(k) { var p = k.split('-').map(Number); return WD[new Date(p[0], p[1] - 1, p[2]).getDay()] + ', ' + p[2] + ' ' + MON[p[1] - 1] + ' ' + p[0]; }
  function fmtTime(min) { var h = Math.floor(min / 60), ap = h >= 12 ? 'PM' : 'AM'; h = h % 12 || 12; return h + ':' + pad(min % 60) + ' ' + ap; }

  /** JSON request with the CSRF token. Rejects with a readable message. */
  function send(method, url, data) {
    return fetch(url, {
      method: method,
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': CSRF },
      body: data === undefined ? undefined : JSON.stringify(data),
    }).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (j) {
        if (r.ok) return j;
        if (r.status === 419) throw new Error('Your session expired. Reload the page and try again.');
        throw new Error(j.error || j.message || 'Something went wrong. Check your connection and try again.');
      });
    });
  }
  function get(url) {
    return fetch(url, { headers: { Accept: 'application/json' } }).then(function (r) { if (!r.ok) throw new Error('Could not load. Check your connection.'); return r.json(); });
  }

  // ---------- Date picker: month calendar in a popover; dates with appointments are bold ----------
  function datePicker(host, opts) {
    var value = opts.value || '';
    var today = todayKey();
    var start = value || (opts.birth ? '1990-01-01' : today);
    var ym = { y: +start.slice(0, 4), m: +start.slice(5, 7) - 1 };
    var counts = {};
    var wrap = el('div', { style: 'position:relative' });
    var hidden = opts.name ? el('input', { type: 'hidden', name: opts.name, value: value }) : null;
    var btn = el('button', { type: 'button', class: 'dp inp', 'aria-haspopup': 'dialog', 'aria-expanded': 'false' });
    var pop = el('div', { class: 'pk hide', role: 'dialog', 'aria-label': 'Choose a date' });
    if (hidden) wrap.appendChild(hidden);
    wrap.appendChild(btn); wrap.appendChild(pop); host.appendChild(wrap);

    function label() {
      btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>' +
        '<span class="' + (value ? '' : 'muted') + '">' + esc(value ? fmtKey(value) : opts.placeholder || 'Pick a date') + '</span>';
    }
    function loadCounts() {
      if (opts.birth) return;
      get('/api/appointments/counts?month=' + ym.y + '-' + pad(ym.m + 1)).then(function (c) { counts = c; draw(); }).catch(function () {});
    }
    function draw() {
      var thisYear = +today.slice(0, 4), years = [];
      for (var y = opts.birth ? thisYear : thisYear + 1; y >= (opts.birth ? 1920 : thisYear - 2); y--) years.push(y);
      var first = (new Date(ym.y, ym.m, 1).getDay() + 6) % 7, days = new Date(ym.y, ym.m + 1, 0).getDate();
      var h = '<div class="pk-h"><button type="button" class="btn sm" data-sh="-1" aria-label="Previous month">‹</button>' +
        '<select class="inp" data-m aria-label="Month">' + MON.map(function (n, i) { return '<option value="' + i + '"' + (i === ym.m ? ' selected' : '') + '>' + n + '</option>'; }).join('') + '</select>' +
        '<select class="inp" data-y aria-label="Year">' + years.map(function (y) { return '<option' + (y === ym.y ? ' selected' : '') + '>' + y + '</option>'; }).join('') + '</select>' +
        '<button type="button" class="btn sm" data-sh="1" aria-label="Next month">›</button></div>' +
        '<div class="pk-w">' + ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'].map(function (w) { return '<span>' + w + '</span>'; }).join('') + '</div><div class="pk-g">';
      for (var b = 0; b < first; b++) h += '<span></span>';
      for (var d = 1; d <= days; d++) {
        var k = key(ym.y, ym.m, d), c = counts[k] || 0, off = (opts.min && k < opts.min) || (opts.birth && k > today);
        h += '<button type="button" class="pd' + (k === value ? ' sel' : '') + (k === today ? ' today' : '') + (c ? ' has' : '') + '" data-k="' + k + '"' + (off ? ' disabled style="opacity:.3;cursor:not-allowed"' : '') +
          (opts.birth ? '' : ' title="' + (c ? c + ' appointments' : 'No appointments') + '"') + '>' + d + '</button>';
      }
      h += '</div><div class="pk-f"><span>' + (opts.birth ? 'Pick month and year above' : '<b style="color:var(--ink)">Bold</b> dates have appointments') + '</span>' +
        (opts.birth ? '' : '<button type="button" class="btn sm" data-k="' + today + '">Today</button>') + '</div>';
      pop.innerHTML = h;
    }
    function open(on) {
      pop.classList.toggle('hide', !on);
      btn.setAttribute('aria-expanded', on ? 'true' : 'false');
      if (on) { draw(); loadCounts(); }
    }
    function set(k) {
      value = k;
      if (hidden) hidden.value = k;
      label(); open(false);
      if (opts.onChange) opts.onChange(k);
    }
    btn.addEventListener('click', function () { open(pop.classList.contains('hide')); });
    pop.addEventListener('click', function (e) {
      var t = e.target.closest('button');
      if (!t) return;
      if (t.dataset.k) set(t.dataset.k);
      if (t.dataset.sh) { var d = new Date(ym.y, ym.m + +t.dataset.sh, 1); ym = { y: d.getFullYear(), m: d.getMonth() }; draw(); loadCounts(); }
    });
    pop.addEventListener('change', function (e) {
      if (e.target.matches('[data-m]')) ym.m = +e.target.value;
      if (e.target.matches('[data-y]')) ym.y = +e.target.value;
      draw(); loadCounts();
    });
    document.addEventListener('mousedown', function (e) { if (!wrap.contains(e.target)) open(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') open(false); });
    label();
    return { get: function () { return value; } };
  }

  $$('[data-datepicker]').forEach(function (host) {
    var d = host.dataset;
    datePicker(host, {
      value: d.value, name: d.name, birth: 'birth' in d, placeholder: d.placeholder, min: d.min,
      onChange: function (k) {
        if (d.go) location.href = d.go + (d.go.indexOf('?') < 0 ? '?' : '&') + 'date=' + k;
        var age = $('[data-age-field]');
        if ('birth' in d && age) age.classList.toggle('hide', !!k);
        host.dispatchEvent(new CustomEvent('datechange', { detail: k, bubbles: true }));
      },
    });
    if ('birth' in d && d.value) { var age = $('[data-age-field]'); if (age) age.classList.add('hide'); }
  });

  // ---------- Slot picker: 15-minute slots for one chair and day, Free (default) or Taken ----------
  function slotPicker(host, onChange) {
    var view = 'free', slots = null, value = null, req = 0, err = '';
    function draw() {
      var h = '<div class="row" style="justify-content:space-between"><span class="lbl">Time</span>' +
        '<select class="inp" style="width:auto;padding:4px 8px" aria-label="Show slots" data-view><option value="free"' + (view === 'free' ? ' selected' : '') + '>Free slots</option><option value="taken"' + (view === 'taken' ? ' selected' : '') + '>Taken slots</option></select></div>';
      if (err) h += '<div class="err">' + esc(err) + '</div>';
      else if (!slots) h += '<p class="muted">Loading slots…</p>';
      else if (view === 'free') {
        var free = slots.filter(function (s) { return !s.taken && !s.past; });
        [['Morning', free.filter(function (s) { return s.m < 900; })], ['Evening', free.filter(function (s) { return s.m >= 900; })]].forEach(function (g) {
          h += '<div style="margin-top:6px"><small class="muted">' + g[0] + ' · ' + g[1].length + ' free</small><div class="slots2">' +
            g[1].map(function (s) { return '<button type="button" data-m="' + s.m + '" class="' + (value === s.m ? 'on' : '') + '">' + fmtTime(s.m) + '</button>'; }).join('') +
            (g[1].length ? '' : '<span class="muted">No free slots</span>') + '</div></div>';
        });
      } else {
        var taken = slots.filter(function (s) { return s.taken; });
        h += '<div class="slots2">' + taken.map(function (s) { return '<span class="taken"><b>' + fmtTime(s.m) + '</b>' + esc(s.taken) + '</span>'; }).join('') +
          (taken.length ? '' : '<span class="muted">No slots taken on this chair yet.</span>') + '</div>';
      }
      host.innerHTML = h;
    }
    host.addEventListener('change', function (e) { if (e.target.matches('[data-view]')) { view = e.target.value; draw(); } });
    host.addEventListener('click', function (e) {
      var b = e.target.closest('button[data-m]');
      if (!b) return;
      var m = +b.dataset.m;
      value = value === m ? null : m;
      draw(); onChange(value);
    });
    return {
      load: function (date, chair, ignore) {
        var mine = ++req;
        slots = null; err = ''; value = null; onChange(null); draw();
        get('/api/slots?date=' + date + '&chair=' + chair + (ignore ? '&ignore=' + ignore : ''))
          .then(function (s) { if (mine === req) { slots = s; draw(); } })
          .catch(function () { if (mine === req) { err = 'Could not load slots. Check your connection.'; draw(); } });
      },
    };
  }

  // ---------- Book appointment ----------
  var booking = $('#booking');
  if (booking) (function () {
    var q = $('[data-search]', booking), list = $('#pq-list'), pid = $('[data-pid]', booking), picked = $('[data-picked]', booking), hint = $('[data-hint]', booking);
    var slotInput = $('[data-slot-value]', booking), bookBtn = $('[data-book]', booking), chair = $('[name="chair_id"]', booking);
    var hits = [], idx = 0, timer, pickedName = pid.value ? q.value : null, slot = null;
    var dateInput = $('[name="date"]', booking);

    function refresh() {
      var ok = pid.value && slot != null;
      bookBtn.disabled = !ok;
      bookBtn.textContent = slot != null ? 'Book ' + fmtKey(dateInput.value) + ', ' + fmtTime(slot) : 'Pick a patient and a free slot';
    }
    function show(open) { list.classList.toggle('hide', !open); q.setAttribute('aria-expanded', open ? 'true' : 'false'); }
    function drawList() {
      list.innerHTML = hits.map(function (h, i) {
        return '<div role="option" aria-selected="' + (i === idx) + '" class="sg' + (i === idx ? ' on' : '') + '" data-i="' + i + '"><div style="flex:1"><b>' + esc(h.name) + '</b><br><small class="muted"><span class="mono">' + esc(h.code) + '</span> · ' + esc(h.mobile) + (h.relative_name ? ' · ' + esc(h.relative_name) : '') + '</small></div>' +
          (h.age != null ? '<small class="muted">' + h.age + ' yrs</small>' : '') + '</div>';
      }).join('') + (hits.length ? '' : '<div class="sg muted">No registered patient matches "' + esc(q.value) + '".</div>') +
        '<a class="sg" href="' + esc(window.NEW_PATIENT_URL) + '" style="color:var(--primary);font-weight:500">+ Add a new patient</a>';
      show(true);
    }
    function choose(h) {
      pid.value = h.id; pickedName = h.name; q.value = h.name;
      picked.textContent = h.code + ' · ' + h.mobile; picked.classList.remove('hide'); hint.classList.add('hide');
      show(false); refresh();
    }
    q.addEventListener('input', function () {
      clearTimeout(timer);
      if (q.value !== pickedName) { pid.value = ''; picked.classList.add('hide'); hint.classList.remove('hide'); refresh(); }
      var term = q.value.trim();
      if (term.length < 3) { hits = []; show(false); return; }
      timer = setTimeout(function () {
        get(q.dataset.search + '?q=' + encodeURIComponent(term)).then(function (r) { hits = r; idx = 0; drawList(); }).catch(function () {});
      }, 150);
    });
    q.addEventListener('keydown', function (e) {
      if (list.classList.contains('hide') || !hits.length) return;
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); idx = (idx + (e.key === 'ArrowDown' ? 1 : -1) + hits.length) % hits.length; drawList(); }
      if (e.key === 'Enter') { e.preventDefault(); choose(hits[idx]); }
      if (e.key === 'Escape') show(false);
    });
    list.addEventListener('mousedown', function (e) { var o = e.target.closest('[data-i]'); if (o) { e.preventDefault(); choose(hits[+o.dataset.i]); } });
    q.addEventListener('blur', function () { setTimeout(function () { show(false); }, 150); });

    var sp = slotPicker($('[data-slots]', booking), function (m) { slot = m; slotInput.value = m == null ? '' : m; refresh(); });
    function reload() { sp.load(dateInput.value, chair.value); }
    booking.addEventListener('datechange', reload);
    chair.addEventListener('change', reload);
    booking.addEventListener('submit', function () { bookBtn.disabled = true; });
    reload(); refresh();
  })();

  // ---------- Reschedule from the day list ----------
  $$('[data-move]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var row = btn.closest('tr'), next = row.nextElementSibling;
      if (next && next.classList.contains('move-row')) { next.remove(); return; }
      var tr = el('tr', { class: 'move-row' }), td = el('td', { colspan: '5' });
      td.appendChild($('#move-tpl').content.cloneNode(true));
      tr.appendChild(td); row.after(tr);
      var chair = $('[data-mv-chair]', td), save = $('[data-mv-save]', td), errBox = $('[data-mv-err]', td), slot = null;
      if (btn.dataset.chair) chair.value = btn.dataset.chair;
      var date = btn.dataset.date < todayKey() ? todayKey() : btn.dataset.date;
      var sp = slotPicker($('[data-mv-slots]', td), function (m) { slot = m; save.disabled = m == null; });
      datePicker($('[data-mv-date]', td), { value: date, min: todayKey(), onChange: function (k) { date = k; sp.load(date, chair.value, btn.dataset.id); } });
      chair.addEventListener('change', function () { sp.load(date, chair.value, btn.dataset.id); });
      $('[data-mv-close]', td).addEventListener('click', function () { tr.remove(); });
      save.addEventListener('click', function () {
        save.disabled = true;
        send('POST', btn.dataset.move, { date: date, chair_id: +chair.value, start_min: slot })
          .then(function () { location.href = location.pathname + '?date=' + date; })
          .catch(function (e) { errBox.textContent = e.message; errBox.classList.remove('hide'); save.disabled = false; });
      });
      sp.load(date, chair.value, btn.dataset.id);
    });
  });

  // ---------- Status dropdowns, on/off toggles, page access, confirmations ----------
  $$('[data-status]').forEach(function (s) {
    var before = s.value;
    s.addEventListener('change', function () {
      s.disabled = true;
      send('POST', s.dataset.status, { status: s.value }).then(function () { before = s.value; s.disabled = false; })
        .catch(function (e) { alert(e.message); s.value = before; s.disabled = false; });
    });
  });

  $$('[data-toggle]').forEach(function (b) {
    b.addEventListener('click', function () {
      var on = !b.classList.contains('on');
      b.classList.toggle('on', on); b.setAttribute('aria-pressed', on);
      var tr = b.closest('tr'); if (tr) tr.classList.toggle('off', !on);
      send('POST', b.dataset.toggle, { active: on }).catch(function (e) {
        alert(e.message); b.classList.toggle('on', !on); b.setAttribute('aria-pressed', !on); if (tr) tr.classList.toggle('off', on);
      });
    });
  });

  $$('[data-page]').forEach(function (b) {
    b.addEventListener('click', function () {
      b.classList.toggle('on'); b.setAttribute('aria-pressed', b.classList.contains('on'));
      var pages = $$('[data-person="' + b.dataset.person + '"].on').map(function (x) { return x.dataset.page; });
      send('POST', b.dataset.pagesUrl, { pages: pages }).catch(function (e) { alert(e.message); b.classList.toggle('on'); });
    });
  });

  $$('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) { if (!confirm(f.dataset.confirm)) e.preventDefault(); });
  });

  // ---------- Photos: pick, crop to a 160px square, keep as a small JPEG ----------
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-photo]');
    if (!btn) return;
    var input = el('input', { type: 'file', accept: 'image/*' });
    input.addEventListener('change', function () {
      var f = input.files[0];
      if (!f) return;
      var img = new Image();
      img.onload = function () {
        var s = 160, c = document.createElement('canvas');
        c.width = c.height = s;
        var k = Math.max(s / img.width, s / img.height), w = img.width * k, h = img.height * k;
        c.getContext('2d').drawImage(img, (s - w) / 2, (s - h) / 2, w, h);
        var url = c.toDataURL('image/jpeg', 0.82);
        btn.nextElementSibling.value = url;
        btn.innerHTML = '<img src="' + url + '" alt="">';
        URL.revokeObjectURL(img.src);
      };
      img.src = URL.createObjectURL(f);
    });
    input.click();
  });

  // ---------- Setup: more receptionists ----------
  var addRec = $('[data-add-rec]');
  if (addRec) addRec.addEventListener('click', function () {
    var box = $('[data-recs]'), recs = $$('.rec', box), copy = recs[recs.length - 1].cloneNode(true), n = recs.length;
    $$('input', copy).forEach(function (i) { i.value = ''; i.name = i.name.replace(/receptionists\[\d+\]/, 'receptionists[' + n + ']'); });
    $('[data-photo]', copy).textContent = '+';
    box.appendChild(copy);
  });

  // ---------- Add patient: medical history "add another" ----------
  var condAdd = $('[data-cond-add]');
  if (condAdd) {
    var condInput = $('[data-cond-input]');
    var addCond = function () {
      var v = condInput.value.trim();
      if (!v) return;
      var existing = $$('[data-conds] input').filter(function (i) { return i.value.toLowerCase() === v.toLowerCase(); })[0];
      if (existing) existing.checked = true;
      else {
        var l = el('label', { class: 'chk' }, '<input type="checkbox" name="history[]" checked> ' + esc(v));
        l.firstChild.value = v;
        $('[data-conds]').appendChild(l);
      }
      condInput.value = '';
    };
    condAdd.addEventListener('click', addCond);
    condInput.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); addCond(); } });
  }

  // ---------- Add staff: qualification and registration only for doctors ----------
  var role = $('[data-role]');
  if (role) {
    var docOnly = function () { $$('[data-doc-only]').forEach(function (f) { f.classList.toggle('hide', role.value !== 'DOCTOR'); }); };
    role.addEventListener('change', docOnly); docOnly();
  }

  // ---------- Import old patients ----------
  var imp = $('#importer');
  if (imp) (function () {
    var FIELDS = [['skip', 'Skip this column'], ['name', 'Full name'], ['mobile', 'Mobile number'], ['gender', 'Gender'], ['dob', 'Date of birth'], ['age', 'Age'],
      ['relative_name', 'Father / husband / wife / mother'], ['address', 'Address'], ['old_id', 'Old patient ID'], ['notes', 'Medical history']];
    var pick = $('[data-step="pick"]', imp), map = $('[data-step="map"]', imp), done = $('[data-step="done"]', imp), errBox = $('[data-err]', imp), drop = $('[data-drop]', imp);
    var sheet = null;

    function guess(h) {
      h = h.toLowerCase();
      if (/mob|phone|contact|cell|whatsapp/.test(h)) return 'mobile';
      if (/father|husband|guardian|relat|spouse|mother|wife|s\/o|w\/o|d\/o/.test(h)) return 'relative_name';
      if (/name/.test(h)) return 'name';
      if (/sex|gender/.test(h)) return 'gender';
      if (/dob|birth/.test(h)) return 'dob';
      if (/^age/.test(h)) return 'age';
      if (/addr|city|area|locality/.test(h)) return 'address';
      if (/note|history|medical|allerg|disease/.test(h)) return 'notes';
      if (/\bid\b|reg|code|uhid|no\.?$/.test(h)) return 'old_id';
      return 'skip';
    }
    function digits(v) { var d = String(v).replace(/\D/g, ''); if (d.length === 12 && d.indexOf('91') === 0) d = d.slice(2); if (d.length === 11 && d[0] === '0') d = d.slice(1); return d; }
    function step(s) { pick.classList.toggle('hide', s !== 'pick'); map.classList.toggle('hide', s !== 'map'); done.classList.toggle('hide', s !== 'done'); }
    function fail(m) { errBox.textContent = m; errBox.classList.remove('hide'); }

    function upload(file) {
      if (!file) return;
      errBox.classList.add('hide');
      var fd = new FormData(); fd.append('file', file);
      drop.style.opacity = '.6';
      fetch(imp.dataset.read, { method: 'POST', body: fd, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF } })
        .then(function (r) { return r.json().then(function (j) { if (!r.ok) throw new Error(j.error || j.message || 'Could not read this file. Try saving it again as CSV.'); return j; }); })
        .then(function (j) { sheet = { name: j.name, headers: j.headers, rows: j.rows, map: j.headers.map(guess) }; drawMap(); step('map'); })
        .catch(function (e) { fail(e.message); })
        .then(function () { drop.style.opacity = ''; });
    }
    $('[data-file]', imp).addEventListener('change', function (e) { upload(e.target.files[0]); e.target.value = ''; });
    drop.addEventListener('dragover', function (e) { e.preventDefault(); drop.classList.add('over'); });
    drop.addEventListener('dragleave', function () { drop.classList.remove('over'); });
    drop.addEventListener('drop', function (e) { e.preventDefault(); drop.classList.remove('over'); upload(e.dataTransfer.files[0]); });

    function checked() {
      var ni = sheet.map.indexOf('name'), mi = sheet.map.indexOf('mobile'), seen = {};
      return sheet.rows.map(function (r, i) {
        var name = ni >= 0 ? r[ni] : '', mob = mi >= 0 ? digits(r[mi]) : '', st = 'OK', k = name.toLowerCase() + mob;
        if (!name) st = 'Missing name';
        else if (!/^[6-9]\d{9}$/.test(mob)) st = 'Fix mobile';
        else if (seen[k]) st = 'Repeated in file';
        seen[k] = true;
        return { r: r, i: i, name: name, mob: mob, st: st };
      });
    }
    function drawMap() {
      var ni = sheet.map.indexOf('name'), mi = sheet.map.indexOf('mobile'), rows = checked();
      var ok = rows.filter(function (x) { return x.st === 'OK'; }), bad = rows.length - ok.length;
      var preview = rows.filter(function (x, j) { return j < 5 || x.st !== 'OK'; }).slice(0, 12);
      map.innerHTML =
        '<div class="card"><div class="row" style="justify-content:space-between"><div><h2 style="margin:0;word-break:break-all">' + esc(sheet.name) + '</h2><p class="muted" style="margin:2px 0 12px">' + sheet.rows.length + ' rows · ' + sheet.headers.length + ' columns</p></div><button type="button" class="btn sm" data-again>Choose another file</button></div>' +
        '<div class="tw"><table><thead><tr><th>Column in your file</th><th>Example</th><th>Goes into</th></tr></thead><tbody>' +
        sheet.headers.map(function (h, i) {
          return '<tr><td class="mono">' + esc(h || '(no name)') + '</td><td class="muted" style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + esc((sheet.rows[0] || [])[i]) + '</td>' +
            '<td><select class="inp" style="padding:4px 8px" data-col="' + i + '" aria-label="Field for ' + esc(h) + '">' + FIELDS.map(function (f) { return '<option value="' + f[0] + '"' + (sheet.map[i] === f[0] ? ' selected' : '') + '>' + f[1] + '</option>'; }).join('') + '</select></td></tr>';
        }).join('') + '</tbody></table></div></div>' +
        '<div class="card stack"><h2 style="margin:0">Check before import</h2>' +
        (ni < 0 || mi < 0 ? '<div class="err">Choose which column is the full name and which is the mobile number.</div>' : '') +
        '<div class="err hide" data-imp-err></div>' +
        '<div><span class="chip c-ok">' + ok.length + ' ready</span> <span class="muted">New patient IDs will be given. Patients already in the clinic with the same name and mobile are skipped.</span></div>' +
        (bad ? '<div><span class="chip c-bad">' + bad + ' need fixing</span> <span class="muted">Mobile not 10 digits, name missing or repeated row. These are skipped.</span></div>' : '') +
        '<div class="tw"><table><thead><tr><th>Row</th><th>Name</th><th>Mobile</th><th></th></tr></thead><tbody>' +
        preview.map(function (x) { return '<tr><td>' + (x.i + 2) + '</td><td>' + esc(x.name || '—') + '</td><td class="mono">' + esc(x.mob || '—') + '</td><td><span class="chip ' + (x.st === 'OK' ? 'c-ok' : 'c-bad') + '">' + x.st + '</span></td></tr>'; }).join('') +
        '</tbody></table></div><div><button type="button" class="btn pri" data-go' + (!ok.length || ni < 0 || mi < 0 ? ' disabled' : '') + '>Import ' + ok.length + ' patients</button></div></div>';
    }
    map.addEventListener('change', function (e) { var s = e.target.closest('[data-col]'); if (s) { sheet.map[+s.dataset.col] = s.value; drawMap(); } });
    map.addEventListener('click', function (e) {
      if (e.target.closest('[data-again]')) { sheet = null; step('pick'); return; }
      var go = e.target.closest('[data-go]');
      if (!go) return;
      go.disabled = true; go.textContent = 'Importing…';
      var rows = checked().filter(function (x) { return x.st === 'OK'; }).map(function (x) {
        var o = {};
        sheet.map.forEach(function (f, i) { if (f !== 'skip' && x.r[i]) o[f] = String(x.r[i]); });
        return o;
      });
      send('POST', imp.dataset.store, { file: sheet.name, rows: rows }).then(function (res) {
        done.innerHTML = '<div class="okmsg">' + res.added + ' patients imported.' + (res.skipped ? ' ' + res.skipped + ' rows were skipped (already in the clinic or bad mobile number).' : '') + '</div>' +
          '<div class="row"><a class="btn pri" href="' + esc(imp.dataset.patients) + '">See patients</a><button type="button" class="btn" data-again>Import another file</button></div>';
        step('done');
      }).catch(function (e) {
        var b = $('[data-imp-err]', map); b.textContent = 'The import did not finish: ' + e.message; b.classList.remove('hide');
        go.disabled = false; go.textContent = 'Import ' + rows.length + ' patients';
      });
    });
    done.addEventListener('click', function (e) { if (e.target.closest('[data-again]')) { sheet = null; step('pick'); } });
  })();
})();
