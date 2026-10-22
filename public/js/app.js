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
  // ---------- Loading bar: page changes, form saves and every request to the server ----------
  var bar = el('div', { class: 'loadbar', 'aria-hidden': 'true' }, '<i></i>'), busyN = 0, barTimer;
  document.body.appendChild(bar);
  function loadOn() { clearTimeout(barTimer); bar.classList.remove('done'); bar.classList.add('on'); }
  function loadOff() { bar.classList.add('done'); barTimer = setTimeout(function () { bar.classList.remove('on', 'done'); }, 300); }
  function track(p) { busyN++; loadOn(); var end = function () { if (--busyN <= 0) { busyN = 0; loadOff(); } }; p.then(end, end); return p; }
  var leaveTimer;
  function leaving(btn) {
    loadOn(); document.documentElement.classList.add('leaving');
    if (btn && btn.classList.contains('btn')) btn.classList.add('busy');
    // Downloads and print pages never leave this page, so give up after a while.
    clearTimeout(leaveTimer); leaveTimer = setTimeout(function () { settle(); }, 12000);
  }
  function settle() { loadOff(); document.documentElement.classList.remove('leaving'); $$('.btn.busy').forEach(function (b) { b.classList.remove('busy'); }); }
  window.addEventListener('pageshow', settle);
  document.addEventListener('click', function (e) {
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    var a = e.target.closest('a[href]');
    if (!a || a.target === '_blank' || a.hasAttribute('download') || /[?&]download=|\/sample|\.(csv|png|jpe?g|pdf)$/i.test(a.href)) return;
    var u = new URL(a.href, location.href);
    if (u.origin !== location.origin || (u.pathname === location.pathname && u.search === location.search && u.hash)) return;
    leaving(a);
  });
  // Bubble phase on window runs after every form's own submit handler, so a cancelled submit shows nothing.
  window.addEventListener('submit', function (e) {
    if (e.defaultPrevented || e.target.target === '_blank') return;
    leaving(e.submitter || $('button:not([type=button])', e.target));
  });

  function send(method, url, data) {
    return track(fetch(url, {
      method: method,
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': CSRF },
      body: data === undefined ? undefined : JSON.stringify(data),
    }).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (j) {
        if (r.ok) return j;
        if (r.status === 419) throw new Error('Your session expired. Reload the page and try again.');
        throw new Error(j.error || j.message || 'Something went wrong. Check your connection and try again.');
      });
    }));
  }
  function get(url) {
    return track(fetch(url, { headers: { Accept: 'application/json' } }).then(function (r) { if (!r.ok) throw new Error('Could not load. Check your connection.'); return r.json(); }));
  }

  // ---------- Suggestions under a box (replaces <datalist>, which phones such as iPhone barely show) ----------
  var sgBox = null, sgFor = null, sgHits = [], sgIdx = -1;
  function sgClose() { if (sgBox) sgBox.remove(); sgBox = null; sgFor = null; }
  function sgPlace() {
    if (!sgBox || !sgFor) return;
    var r = sgFor.getBoundingClientRect(), below = innerHeight - r.bottom;
    sgBox.style.left = r.left + 'px'; sgBox.style.width = Math.max(r.width, 220) + 'px';
    if (below < 200 && r.top > below) { sgBox.style.top = ''; sgBox.style.bottom = (innerHeight - r.top + 4) + 'px'; sgBox.style.maxHeight = Math.min(300, r.top - 12) + 'px'; }
    else { sgBox.style.bottom = ''; sgBox.style.top = (r.bottom + 4) + 'px'; sgBox.style.maxHeight = Math.min(300, below - 12) + 'px'; }
  }
  var sgTimer, sgAsk = 0;
  function onlineOn(inp) { var f = inp.closest('form'), c = f && $('[data-online-meds]', f); return c && c.checked; }
  function sgShow(inp, online) {
    var dl = document.getElementById(inp.dataset.sugg); if (!dl) return;
    var q = inp.value.trim().toLowerCase(), opts = $$('option', dl);
    var starts = [], has = [];
    opts.forEach(function (o) { var v = o.value.toLowerCase(); if (!q || v.indexOf(q) === 0) starts.push(o); else if (v.indexOf(q) > 0) has.push(o); });
    var local = starts.concat(has).filter(function (o) { return o.value.toLowerCase() !== q; }).slice(0, 12);
    var known = {}; opts.forEach(function (o) { known[o.value.toLowerCase()] = 1; });
    var web = (online || []).filter(function (o) { return !known[o.value.toLowerCase()]; });
    var asking = inp.dataset.sugg === 'rx-meds' && q.length >= 3 && onlineOn(inp);
    sgHits = local.concat(web);
    if (!sgHits.length && !(asking && !online)) { sgClose(); }
    else {
      if (!sgBox) { sgBox = el('div', { class: 'sugg2', role: 'listbox' }); document.body.appendChild(sgBox); }
      sgFor = inp; sgIdx = -1;
      var row = function (o, i) {
        var d = o.dataset, sub = [d.form, d.dose, d.timing, d.days].filter(Boolean).join(' · ');
        return '<div class="sg" role="option" data-i="' + i + '"><div><b>' + esc(o.value) + '</b>' + (sub ? '<br><small class="muted">' + esc(sub) + '</small>' : '') + '</div></div>';
      };
      sgBox.innerHTML = local.map(row).join('') +
        (asking ? '<div class="sg-head">' + (online ? (web.length ? 'Online · generic names' : 'Nothing more found online') : 'Searching online…') + '</div>' : '') +
        web.map(function (o, i) { return row(o, local.length + i); }).join('');
      sgPlace();
    }
    if (asking && !online) {
      clearTimeout(sgTimer);
      var ask = ++sgAsk;
      sgTimer = setTimeout(function () {
        fetch('/api/rx/medicines/online?q=' + encodeURIComponent(inp.value.trim()), { headers: { Accept: 'application/json' } })
          .then(function (r) { return r.json().then(function (j) { if (!r.ok) throw new Error(j.error || 'Online search is not answering right now.'); return j; }); })
          .then(function (list) {
            if (ask !== sgAsk || document.activeElement !== inp) return;
            sgShow(inp, list.map(function (m) { return { value: m.name, dataset: { form: m.form || '' }, online: true }; }));
          })
          .catch(function (e) { if (ask === sgAsk && sgBox && sgFor === inp) { var h = $('.sg-head', sgBox); if (h) h.textContent = e.message || 'Online search is not answering right now.'; } });
      }, 350);
    }
  }
  function sgPick(i) {
    var inp = sgFor, o = sgHits[i]; if (!inp || !o) return;
    if (o.online) { var dl = document.getElementById(inp.dataset.sugg); if (dl) dl.appendChild(el('option', { value: o.value, 'data-form': o.dataset.form })); }
    inp.value = o.value; sgClose();
    inp.dispatchEvent(new Event('input', { bubbles: true })); inp.dispatchEvent(new Event('change', { bubbles: true }));
  }
  function sgUpgrade(root) {
    var list = root.matches && root.matches('input[list]') ? [root] : $$('input[list]', root);
    list.forEach(function (inp) { inp.dataset.sugg = inp.getAttribute('list'); inp.removeAttribute('list'); inp.setAttribute('autocomplete', 'off'); });
  }
  sgUpgrade(document);
  $$('[data-online-meds]').forEach(function (c) {
    try { c.checked = localStorage.getItem('sd-online-meds') === '1'; } catch (e) {}
    c.addEventListener('change', function () { try { localStorage.setItem('sd-online-meds', c.checked ? '1' : '0'); } catch (e) {} });
  });
  new MutationObserver(function (ms) { ms.forEach(function (m) { m.addedNodes.forEach(function (n) { if (n.nodeType === 1) sgUpgrade(n); }); }); }).observe(document.body, { childList: true, subtree: true });
  document.addEventListener('focusin', function (e) { if (e.target.dataset && e.target.dataset.sugg) sgShow(e.target); else if (sgBox && !sgBox.contains(e.target)) sgClose(); });
  document.addEventListener('input', function (e) { if (e.target.dataset && e.target.dataset.sugg && e.isTrusted) sgShow(e.target); });
  document.addEventListener('focusout', function (e) { if (e.target === sgFor) setTimeout(function () { if (document.activeElement !== sgFor) sgClose(); }, 120); });
  document.addEventListener('keydown', function (e) {
    if (!sgBox || e.target !== sgFor) return;
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault(); sgIdx = (sgIdx + (e.key === 'ArrowDown' ? 1 : -1) + sgHits.length) % sgHits.length;
      $$('.sg', sgBox).forEach(function (x, i) { x.classList.toggle('on', i === sgIdx); if (i === sgIdx) x.scrollIntoView({ block: 'nearest' }); });
    } else if (e.key === 'Enter' && sgIdx >= 0) { e.preventDefault(); sgPick(sgIdx); }
    else if (e.key === 'Escape') sgClose();
  });
  document.addEventListener('pointerdown', function (e) {
    if (!sgBox) return;
    var o = e.target.closest('.sugg2 [data-i]');
    if (o) {
      e.preventDefault(); sgPick(+o.dataset.i);
      // Phones send a click after the tap; once the list is gone it would land on whatever was underneath (e.g. "Edit this list").
      var swallow = function (ev) { ev.preventDefault(); ev.stopPropagation(); off(); };
      var off = function () { document.removeEventListener('click', swallow, true); };
      document.addEventListener('click', swallow, true); setTimeout(off, 450);
    } else if (!sgBox.contains(e.target) && e.target !== sgFor) sgClose();
  });
  window.addEventListener('resize', sgPlace);
  document.addEventListener('scroll', sgPlace, true);

  // ---------- Phone menu ----------
  $$('[data-menu-btn]').forEach(function (b) {
    b.addEventListener('click', function () { var side = b.closest('.side'), open = side.classList.toggle('open'); b.setAttribute('aria-expanded', open ? 'true' : 'false'); });
  });

  // ---------- Small note in the corner: "Saved" after an automatic save, or what went wrong ----------
  var toastBox = null;
  function toast(msg, bad) {
    if (!toastBox) { toastBox = el('div', { class: 'toasts', role: 'status', 'aria-live': 'polite' }); document.body.appendChild(toastBox); }
    var t = el('div', { class: 'toast' + (bad ? ' bad' : '') },
      '<span class="ti">' + (bad ? '!' : '<svg viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>') + '</span>' + esc(msg));
    toastBox.appendChild(t);
    while (toastBox.children.length > 3) toastBox.removeChild(toastBox.firstChild);
    setTimeout(function () { t.classList.add('out'); setTimeout(function () { t.remove(); }, 320); }, bad ? 5000 : 2200);
  }
  window.smileToast = toast;

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
      if (opts.birth || opts.plain) return;
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
        var k = key(ym.y, ym.m, d), c = counts[k] || 0, off = (opts.min && k < opts.min) || (opts.max && k > opts.max) || (opts.birth && k > today);
        h += '<button type="button" class="pd' + (k === value ? ' sel' : '') + (k === today ? ' today' : '') + (c ? ' has' : '') + '" data-k="' + k + '"' + (off ? ' disabled style="opacity:.3;cursor:not-allowed"' : '') +
          (opts.birth || opts.plain ? '' : ' title="' + (c ? c + ' appointments' : 'No appointments') + '"') + '>' + d + '</button>';
      }
      h += '</div><div class="pk-f"><span>' + (opts.birth ? 'Pick month and year above' : opts.plain ? '' : '<b style="color:var(--ink)">Bold</b> dates have appointments') + '</span>' +
        '<span class="row" style="gap:6px">' + (opts.clearable && value ? '<button type="button" class="btn sm" data-clear>Clear</button>' : '') +
        (opts.birth ? '' : '<button type="button" class="btn sm" data-k="' + today + '">Today</button>') + '</span></div>';
      pop.innerHTML = h;
    }
    function open(on) {
      pop.classList.toggle('hide', !on);
      btn.setAttribute('aria-expanded', on ? 'true' : 'false');
      // Lift the open calendar above later cards (their entry animation gives each one its own layer)
      for (var a = wrap.parentElement; a && a !== document.body; a = a.parentElement) {
        if (on && !/^(static|relative)$/.test(getComputedStyle(a).position)) break;
        a.classList.toggle('pk-up', on);
      }
      if (on) {
        draw(); loadCounts();
        pop.style.left = ''; pop.style.right = '';
        var r = pop.getBoundingClientRect();
        if (r.right > window.innerWidth - 8) { pop.style.left = 'auto'; pop.style.right = '0'; }
      }
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
      if ('clear' in t.dataset) set('');
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
      value: d.value, name: d.name, birth: 'birth' in d, plain: 'plain' in d, clearable: 'clearable' in d, placeholder: d.placeholder, min: d.min, max: d.max,
      onChange: function (k) {
        if (d.go) location.href = d.go + (d.go.indexOf('?') < 0 ? '?' : '&') + 'date=' + k;
        if ('birth' in d) fillAge(k);
        host.dispatchEvent(new CustomEvent('datechange', { detail: k, bubbles: true }));
      },
    });
    if ('birth' in d) fillAge(d.value);
  });
  // Age follows the date of birth: filled in and locked while a date of birth is picked
  function fillAge(dob) {
    var box = $('[data-age-field]'); if (!box) return;
    var inp = $('input', box), lbl = $('label', box);
    if (dob) {
      var p = dob.split('-').map(Number), t = todayKey().split('-').map(Number);
      var y = t[0] - p[0] - ((t[1] < p[1] || (t[1] === p[1] && t[2] < p[2])) ? 1 : 0);
      inp.value = Math.max(0, y); inp.readOnly = true; lbl.textContent = 'Age (from date of birth)';
    } else { inp.readOnly = false; lbl.textContent = 'Or age in years'; }
    box.classList.toggle('auto', !!dob);
  }

  // ---------- Slot picker: 15-minute slots for one chair and day, Free (default) or Taken ----------
  function slotPicker(host, onChange, url) {
    var view = 'free', slots = null, value = null, req = 0, err = '';
    function draw() {
      var h = '<div class="row" style="justify-content:space-between"><span class="lbl">Time</span>' +
        '<select class="inp" style="width:auto;padding:4px 8px" aria-label="Show slots" data-view>' + [['all', 'All slots'], ['free', 'Free slots'], ['taken', 'Taken slots']].map(function (o) { return '<option value="' + o[0] + '"' + (view === o[0] ? ' selected' : '') + '>' + o[1] + '</option>'; }).join('') + '</select></div>';
      if (err) h += '<div class="err">' + esc(err) + '</div>';
      else if (!slots) h += '<p class="muted">Loading slots…</p>';
      else if (view === 'all') {
        // Every slot of the day: free ones can be picked, taken ones show who has them, past ones are greyed
        [['Morning', slots.filter(function (s) { return s.m < 900; })], ['Evening', slots.filter(function (s) { return s.m >= 900; })]].forEach(function (g) {
          if (!g[1].length) return;
          var nFree = g[1].filter(function (s) { return !s.taken && !s.past; }).length;
          h += '<div style="margin-top:6px"><small class="muted">' + g[0] + ' · ' + nFree + ' free · ' + g[1].filter(function (s) { return s.taken; }).length + ' taken</small><div class="slots2">' +
            g[1].map(function (s) {
              if (s.taken) return '<span class="taken" title="' + esc(s.taken) + '"><b>' + fmtTime(s.m) + '</b>' + esc(s.taken) + '</span>';
              if (s.past) return '<span class="past">' + fmtTime(s.m) + '</span>';
              return '<button type="button" data-m="' + s.m + '" class="' + (value === s.m ? 'on' : '') + '">' + fmtTime(s.m) + '</button>';
            }).join('') + '</div></div>';
        });
      } else if (view === 'free') {
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
        get((url || '/api/slots') + '?date=' + date + '&chair=' + chair + (ignore ? '&ignore=' + ignore : ''))
          .then(function (s) { if (mine === req) { slots = s; draw(); } })
          .catch(function () { if (mine === req) { err = 'Could not load slots. Check your connection.'; draw(); } });
      },
    };
  }

  // ---------- Patient picker: suggestions after 3 letters, shows ID, mobile and relative ----------
  function patientPicker(root, onChange, onNew) {
    var q = $('[data-search]', root), list = $('.sugg', root), pid = $('[data-pid]', root), picked = $('[data-picked]', root), hint = $('[data-hint]', root);
    var hits = [], idx = 0, timer, pickedName = pid.value ? q.value : null;
    function show(open) { list.classList.toggle('hide', !open); q.setAttribute('aria-expanded', open ? 'true' : 'false'); }
    function drawList() {
      list.innerHTML = hits.map(function (h, i) {
        return '<div role="option" aria-selected="' + (i === idx) + '" class="sg' + (i === idx ? ' on' : '') + '" data-i="' + i + '"><div style="flex:1"><b>' + esc(h.name) + '</b><br><small class="muted"><span class="mono">' + esc(h.code) + '</span> · ' + esc(h.mobile) + (h.relative_name ? ' · ' + esc(h.relative_name) : '') + '</small></div>' +
          (h.age != null ? '<small class="muted">' + h.age + ' yrs</small>' : '') + '</div>';
      }).join('') + (hits.length ? '' : '<div class="sg muted">No registered patient matches "' + esc(q.value) + '".</div>') +
        (onNew ? '<button type="button" class="sg" data-new-sg style="color:var(--primary);font-weight:500;border:0;background:none;width:100%;text-align:left;font:inherit;cursor:pointer">+ Add "' + esc(q.value.trim()) + '" as a new patient</button>'
          : window.NEW_PATIENT_URL ? '<a class="sg" href="' + esc(window.NEW_PATIENT_URL) + '" style="color:var(--primary);font-weight:500">+ Add a new patient</a>' : '');
      show(true);
    }
    function choose(h) {
      pid.value = h.id; pickedName = h.name; q.value = h.name;
      picked.textContent = h.code + ' · ' + h.mobile; picked.classList.remove('hide'); hint.classList.add('hide');
      show(false); onChange();
    }
    q.addEventListener('input', function () {
      clearTimeout(timer);
      if (q.value !== pickedName) { pid.value = ''; picked.classList.add('hide'); hint.classList.remove('hide'); onChange(); }
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
    list.addEventListener('mousedown', function (e) {
      var o = e.target.closest('[data-i]'); if (o) { e.preventDefault(); choose(hits[+o.dataset.i]); }
      if (onNew && e.target.closest('[data-new-sg]')) { e.preventDefault(); show(false); onNew(q.value.trim()); }
    });
    q.addEventListener('blur', function () { setTimeout(function () { show(false); }, 150); });
    return { id: function () { return pid.value; }, focus: function () { q.focus(); } };
  }

  // ---------- Medicine library page: rows save on change; search filters ----------
  $$('tr[data-med]').forEach(function (tr) {
    $$('[data-f]', tr).forEach(function (i) {
      i.addEventListener('change', function () {
        var d = {}; $$('[data-f]', tr).forEach(function (x) { d[x.dataset.f] = x.value.trim(); });
        if (d.name.length < 2) { toast('Write the medicine name.', true); return; }
        send('POST', tr.dataset.med, d).then(function () { tr.dataset.name = d.name.toLowerCase(); toast('Saved'); }).catch(function (e) { toast(e.message, true); });
      });
    });
  });
  var medFind = $('[data-medfind]');
  if (medFind) medFind.addEventListener('input', function () {
    var q = medFind.value.trim().toLowerCase();
    $$('tr[data-med]').forEach(function (tr) { tr.hidden = q && tr.dataset.name.indexOf(q) < 0; });
  });

  // ---------- Day calendar: tap a free slot to book there ----------
  // Open the calendar where the day's work is: the first free slot from now, or the first appointment.
  $$('.cal-wrap').forEach(function (w) {
    var first = $('.cal-cell.free, .cal-appts', w), head = $('.cal-h', w);
    if (first && first.offsetTop > w.clientHeight * 0.6) w.scrollTop = first.offsetTop - (head ? head.offsetHeight : 0) - 8;
  });
  var calbook = $('[data-calbook]');
  if (calbook) $$('[data-cal-book]').forEach(function (cell) {
    cell.addEventListener('click', function () {
      var m = el('div', { class: 'modal', role: 'dialog', 'aria-modal': 'true', 'aria-label': 'Book appointment' });
      m.appendChild($('#calbook-tpl').content.cloneNode(true));
      document.body.appendChild(m);
      var form = $('form', m), err = $('[data-err]', m), go = $('[data-go]', m), pick = $('[data-pick-part]', m), np = $('[data-new-part]', m), isNew = false;
      $('[data-when]', m).textContent = fmtKey(calbook.dataset.date) + ' · ' + fmtTime(+cell.dataset.min) + ' · ' + cell.dataset.chairName;
      function close() { m.remove(); cell.focus(); }
      function fail(msg) { err.textContent = msg; err.classList.remove('hide'); go.classList.remove('busy'); go.disabled = false; }
      function mode(n, typed) {
        isNew = n; pick.classList.toggle('hide', n); np.classList.toggle('hide', !n); err.classList.add('hide');
        if (n) {
          var t = typed || '', digits = t.replace(/\D/g, '');
          $('[data-np=name]', m).value = digits.length >= 6 ? '' : t; $('[data-np=mobile]', m).value = digits.length >= 6 ? digits : '';
          $(digits.length >= 6 || !t ? '[data-np=name]' : '[data-np=mobile]', m).focus();
        } else pp.focus();
      }
      var pp = patientPicker(pick, function () { err.classList.add('hide'); }, np ? function (t) { mode(true, t); } : null);
      if (np) { $('[data-new]', m).addEventListener('click', function () { mode(true, $('#cb-q', m).value.trim()); }); $('[data-old]', m).addEventListener('click', function () { mode(false); }); }
      $('[data-x]', m).addEventListener('click', close);
      m.addEventListener('click', function (e) { if (e.target === m) close(); });
      m.addEventListener('keydown', function (e) { if (e.key === 'Escape' && $('.sugg', m).classList.contains('hide')) close(); });
      form.addEventListener('submit', function (e) {
        e.preventDefault(); err.classList.add('hide');
        var patient;
        if (isNew) {
          var d = {}; $$('[data-np]', m).forEach(function (i) { if (i.value.trim()) d[i.dataset.np] = i.value.trim(); });
          if (!d.name || d.name.length < 2) return fail("Enter the patient's full name.");
          if (!d.mobile) return fail('Enter a mobile number.');
          patient = send('POST', calbook.dataset.patientStore, d).then(function (p) { return p.id; });
        } else {
          if (!pp.id()) return fail('Pick a patient from the suggestions, or add a new patient.');
          patient = Promise.resolve(pp.id());
        }
        go.disabled = true; go.classList.add('busy');
        patient.then(function (id) {
          var doc = $('[data-doctor]', m);
          return send('POST', calbook.dataset.store, { patient_id: +id, date: calbook.dataset.date, chair_id: +cell.dataset.chair, start_min: +cell.dataset.min, doctor_id: doc ? +doc.value : null, treatment: $('[data-treatment]', m).value });
        }).then(function () { leaving(); location.reload(); }).catch(function (e) { fail(e.message); });
      });
      pp.focus();
    });
  });

  // ---------- Book appointment ----------
  var booking = $('#booking');
  if (booking) (function () {
    var slotInput = $('[data-slot-value]', booking), bookBtn = $('[data-book]', booking), chair = $('[name="chair_id"]', booking);
    var slot = null, dateInput = $('[name="date"]', booking);
    function refresh() {
      var ok = pp.id() && slot != null;
      bookBtn.disabled = !ok;
      bookBtn.textContent = slot != null ? 'Book ' + fmtKey(dateInput.value) + ', ' + fmtTime(slot) : 'Pick a patient and a free slot';
    }
    var pp = patientPicker(booking, function () { refresh(); });

    var sp = slotPicker($('[data-slots]', booking), function (m) { slot = m; slotInput.value = m == null ? '' : m; refresh(); });
    function reload() { sp.load(dateInput.value, chair.value); }
    booking.addEventListener('datechange', reload);
    chair.addEventListener('change', reload);
    booking.addEventListener('submit', function () { bookBtn.disabled = true; });
    reload(); refresh();
  })();

  // ---------- Money: paise in, ₹1,23,456 out (the server recalculates everything on save) ----------
  function paise(v) {
    var s = String(v == null ? '' : v).replace(/[,₹\s]/g, '');
    return /^\d{1,9}(\.\d{1,2})?$/.test(s) ? Math.round(parseFloat(s) * 100) : 0;
  }
  function inr(p) {
    var r = Math.floor(Math.abs(p) / 100), f = Math.abs(p) % 100, s = String(r);
    if (s.length > 3) s = s.slice(0, -3).replace(/\B(?=(\d{2})+(?!\d))/g, ',') + ',' + s.slice(-3);
    return (p < 0 ? '−' : '') + '₹' + s + (f ? '.' + pad(f) : '');
  }

  // ---------- New bill: treatment lines with live totals ----------
  var bill = $('#bill');
  if (bill) (function () {
    var prices = JSON.parse(bill.dataset.prices || '[]'), hasGst = bill.dataset.gst === '1';
    var body = $('[data-lines]', bill), tpl = $('#line-tpl'), saveBtn = $('[data-save]', bill), saveHint = $('[data-save-hint]', bill);
    var payAmt = $('#pay_amount'), total = 0, n = 0;
    var pp = patientPicker(bill, function () { refresh(); });

    function f(tr, k) { return $('[data-f="' + k + '"]', tr); }
    function addLine(v) {
      var tr = tpl.content.firstElementChild.cloneNode(true), i = n++;
      $$('[data-f]', tr).forEach(function (inp) { inp.name = 'items[' + i + '][' + inp.dataset.f + ']'; });
      v = v || {};
      ['name', 'tooth', 'qty', 'rate', 'discount', 'sac', 'gst'].forEach(function (k) { if (v[k] != null && f(tr, k)) f(tr, k).value = v[k]; });
      body.appendChild(tr);
      if (!v.name) f(tr, 'name').focus();
      refresh();
    }
    body.addEventListener('change', function (e) {
      if (!e.target.matches('[data-f="name"]')) return;
      var tr = e.target.closest('tr'), p = prices.filter(function (x) { return x.name === e.target.value; })[0];
      if (p) { f(tr, 'rate').value = p.rate; f(tr, 'sac').value = p.sac; if (f(tr, 'gst')) f(tr, 'gst').value = p.gst; }
      refresh();
    });
    body.addEventListener('input', refresh);
    body.addEventListener('click', function (e) {
      if (!e.target.closest('[data-del]')) return;
      e.target.closest('tr').remove();
      if (!body.children.length) addLine();
      refresh();
    });
    $('[data-add]', bill).addEventListener('click', function () { addLine(); });
    $('[data-full]', bill).addEventListener('click', function () { payAmt.value = total ? (total % 100 ? (total / 100).toFixed(2) : total / 100) : ''; refresh(); });
    payAmt.addEventListener('input', refresh);

    function refresh() {
      var t = { gross: 0, discount: 0, cgst: 0, sgst: 0 };
      $$('tr', body).forEach(function (tr) {
        var qty = Math.max(1, parseInt(f(tr, 'qty').value, 10) || 1), rate = paise(f(tr, 'rate').value);
        var gross = qty * rate, disc = Math.min(paise(f(tr, 'discount').value), gross), gst = hasGst ? +f(tr, 'gst').value : 0;
        var half = Math.round((gross - disc) * gst / 200);
        t.gross += gross; t.discount += disc; t.cgst += half; t.sgst += half;
        $('[data-amt]', tr).textContent = inr(gross - disc + 2 * half);
      });
      total = t.gross - t.discount + t.cgst + t.sgst;
      $('[data-t="gross"]', bill).textContent = inr(t.gross);
      $('[data-t="discount"]', bill).textContent = t.discount ? '− ' + inr(t.discount) : '₹0';
      if (hasGst) { $('[data-t="cgst"]', bill).textContent = inr(t.cgst); $('[data-t="sgst"]', bill).textContent = inr(t.sgst); }
      $('[data-t="total"]', bill).textContent = inr(total);
      var paid = paise(payAmt.value), problem = !pp.id() ? 'Pick the patient.' : !total ? 'Add a treatment with a price.' : paid > total ? 'Amount received is more than the total.' : '';
      saveBtn.disabled = !!problem;
      saveHint.textContent = problem || (paid ? (paid < total ? 'Balance ' + inr(total - paid) + ' stays due.' : 'Paid in full.') : 'Nothing received now; the full amount stays due.');
    }
    bill.addEventListener('submit', function () { saveBtn.disabled = true; });
    var old = JSON.parse(bill.dataset.old || '[]');
    (Array.isArray(old) ? old : Object.keys(old).map(function (k) { return old[k]; })).forEach(addLine);
    if (!body.children.length) addLine();
  })();

  // ---------- Cash closing: count notes, compare with expected ----------
  var cash = $('[data-cash]');
  if (cash) (function () {
    var expected = +cash.dataset.expected, reason = $('[data-reason]', cash), diffChip = $('[data-diff]', cash);
    function refresh() {
      var counted = 0;
      $$('[data-note]', cash).forEach(function (inp) {
        var sub = Math.max(0, parseInt(inp.value, 10) || 0) * +inp.dataset.note * 100;
        counted += sub;
        inp.nextElementSibling.textContent = inr(sub);
      });
      $('[data-counted]', cash).textContent = inr(counted);
      var d = counted - expected;
      diffChip.className = 'chip ' + (d ? 'c-bad' : 'c-ok');
      diffChip.textContent = d ? (d > 0 ? 'Extra ' : 'Short ') + inr(Math.abs(d)) : 'Matches ' + inr(expected);
      reason.classList.toggle('hide', !d);
      $('input', reason).required = !!d;
    }
    cash.addEventListener('input', refresh);
    refresh();
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
          .then(function () { location.href = location.pathname + '?layout=list&date=' + date; })
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
      send('POST', s.dataset.status, { status: s.value }).then(function () { before = s.value; s.disabled = false; toast('Status saved'); })
        .catch(function (e) { toast(e.message, true); s.value = before; s.disabled = false; });
    });
  });

  function pulse(b) { b.classList.remove('pulse'); void b.offsetWidth; b.classList.add('pulse'); }

  $$('[data-toggle]').forEach(function (b) {
    b.addEventListener('click', function () {
      var on = !b.classList.contains('on');
      b.classList.toggle('on', on); b.setAttribute('aria-pressed', on); pulse(b);
      var tr = b.closest('tr'); if (tr) tr.classList.toggle('off', !on);
      send('POST', b.dataset.toggle, { active: on }).then(function () { toast(on ? 'Turned on · saved' : 'Turned off · saved'); }).catch(function (e) {
        toast(e.message, true); b.classList.toggle('on', !on); b.setAttribute('aria-pressed', !on); if (tr) tr.classList.toggle('off', on);
      });
    });
  });

  $$('[data-page]').forEach(function (b) {
    b.addEventListener('click', function () {
      b.classList.toggle('on'); b.setAttribute('aria-pressed', b.classList.contains('on')); pulse(b);
      var pages = $$('[data-person="' + b.dataset.person + '"].on').map(function (x) { return x.dataset.page; });
      send('POST', b.dataset.pagesUrl, { pages: pages }).then(function () { toast('Page access saved'); })
        .catch(function (e) { toast(e.message, true); b.classList.toggle('on'); b.setAttribute('aria-pressed', b.classList.contains('on')); });
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
    $$('input', copy).forEach(function (i) { i.value = ''; delete i.dataset.touched; i.name = i.name.replace(/receptionists\[\d+\]/, 'receptionists[' + n + ']'); });
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
      track(fetch(imp.dataset.read, { method: 'POST', body: fd, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF } })
        .then(function (r) { return r.json().then(function (j) { if (!r.ok) throw new Error(j.error || j.message || 'Could not read this file. Try saving it again as CSV.'); return j; }); })
        .then(function (j) { sheet = { name: j.name, headers: j.headers, rows: j.rows, map: j.headers.map(guess) }; drawMap(); step('map'); })
        .catch(function (e) { fail(e.message); })
        .then(function () { drop.style.opacity = ''; }));
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

  // ---------- Usernames: suggested from the first name until the person types their own ----------
  function slug(name) {
    var first = name.trim().replace(/^dr\.?\s+/i, '').split(/\s+/)[0] || '';
    return first.toLowerCase().replace(/[^a-z0-9]/g, '').slice(0, 24);
  }
  document.addEventListener('input', function (e) {
    var t = e.target;
    if (t.matches('[data-username]')) {
      t.dataset.touched = t.value ? '1' : '';
      var clean = t.value.toLowerCase().replace(/[^a-z0-9._]/g, '');
      if (clean !== t.value) t.value = clean;
      return;
    }
    if (!t.matches('[data-name-src]')) return;
    var u = $('[data-username]', t.closest('.form'));
    if (u && !u.dataset.touched) u.value = slug(t.value);
  });

  // ---------- Clinic logo: shrink to at most 480x240 and keep PNG transparency ----------
  $$('[data-logo]').forEach(function (box) {
    var pickBtn = $('[data-logo-pick]', box), value = $('[data-logo-value]', box), clear = $('[data-logo-clear]', box), saveUrl = box.dataset.logoSave;
    function show(url) {
      pickBtn.innerHTML = url ? '<img src="' + url + '" alt="Clinic logo">' : '<span>+ Logo</span>';
      if (clear) clear.classList.toggle('hide', !url);
    }
    function store(url) {
      if (value) { value.value = url; show(url); return; }
      if (!saveUrl) return;
      pickBtn.classList.add('saving');
      send('POST', saveUrl, { logo: url }).then(function () {
        show(url); toast(url ? 'Logo saved' : 'Logo removed');
        $$('.side .brand .logo').forEach(function (l) {
          l.className = url ? 'logo img' : 'logo'; l.innerHTML = url ? '<img src="' + url + '" alt="">' : 'S';
        });
      }).catch(function (e) { toast(e.message, true); }).then(function () { pickBtn.classList.remove('saving'); });
    }
    pickBtn.addEventListener('click', function () {
      var input = el('input', { type: 'file', accept: 'image/png,image/jpeg,image/webp' });
      input.addEventListener('change', function () {
        var f = input.files[0];
        if (!f) return;
        var img = new Image();
        img.onload = function () {
          var k = Math.min(1, 480 / img.width, 240 / img.height), c = document.createElement('canvas');
          c.width = Math.max(1, Math.round(img.width * k)); c.height = Math.max(1, Math.round(img.height * k));
          c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
          var url = f.type === 'image/jpeg' ? c.toDataURL('image/jpeg', 0.88) : c.toDataURL('image/png');
          URL.revokeObjectURL(img.src);
          if (url.length > 390000) url = c.toDataURL('image/jpeg', 0.85);
          store(url);
        };
        img.onerror = function () { toast('That picture could not be read. Try a PNG or JPG file.', true); };
        img.src = URL.createObjectURL(f);
      });
      input.click();
    });
    if (clear) clear.addEventListener('click', function () { store(''); });
  });

  // ---------- Settings save by themselves when a field changes ----------
  $$('form[data-autosave]').forEach(function (form) {
    var timer;
    function data() { var o = {}; new FormData(form).forEach(function (v, k) { if (k !== '_token') o[k] = v; }); return o; }
    form.addEventListener('submit', function (e) { e.preventDefault(); });
    form.addEventListener('change', function (e) {
      var sw = e.target.closest('.sw');
      if (sw) pulse($('.toggle', sw));
      var field = e.target.closest('.field');
      clearTimeout(timer);
      timer = setTimeout(function () {
        if (field) field.classList.add('saving');
        send('POST', form.action, data()).then(function (res) {
          toast('Settings saved');
          form.dispatchEvent(new CustomEvent('autosaved', { detail: res }));
          if (e.target.id === 'name') $$('.side .brand span').forEach(function (s) { s.textContent = e.target.value; });
        }).catch(function (err) { toast(err.message, true); })
          .then(function () { if (field) field.classList.remove('saving'); });
      }, 250);
    });
  });

  // ---------- My appearance: change the look straight away and save it ----------
  var look = $('[data-appearance]');
  if (look) {
    look.addEventListener('submit', function (e) { e.preventDefault(); });
    look.addEventListener('change', function () {
      var o = {}, root = document.documentElement;
      new FormData(look).forEach(function (v, k) { o[k] = v; });
      root.classList.add('theming');
      Object.keys(o).forEach(function (k) { root.dataset[k] = o[k]; });
      setTimeout(function () { root.classList.remove('theming'); }, 450);
      send('POST', look.dataset.save, o).then(function () { toast('Appearance saved'); }).catch(function (e) { toast(e.message, true); });
    });
  }
  $$('[data-demo-toggle]').forEach(function (b) {
    b.addEventListener('click', function () { b.classList.toggle('on'); b.setAttribute('aria-pressed', b.classList.contains('on')); pulse(b); });
  });

  // ---------- Login: show password, busy button, rotating feature line ----------
  var eye = $('[data-eye]');
  if (eye) eye.addEventListener('click', function () {
    var pw = $('#password'), show = pw.type === 'password';
    pw.type = show ? 'text' : 'password';
    eye.setAttribute('aria-pressed', show); eye.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    pw.focus();
  });
  var loginForm = $('[data-login]');
  if (loginForm) loginForm.addEventListener('submit', function () { var b = $('[data-login-btn]'); b.classList.add('busy'); b.disabled = true; });
  $$('[data-rotate]').forEach(function (span) {
    var words = span.dataset.rotate.split('|'), i = 0;
    if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    setInterval(function () {
      span.classList.add('swap');
      setTimeout(function () { i = (i + 1) % words.length; span.textContent = words[i]; span.classList.remove('swap'); }, 350);
    }, 2600);
  });

  // ---------- Print with options: paper size, orientation, margins and fit to one page ----------
  var PAPER = { A4: [210, 297], A5: [148, 210], Letter: [216, 279] };
  var MARGIN = { normal: 12, narrow: 6, none: 0 };
  function printOpts(k) {
    try { return JSON.parse(localStorage.getItem('sd-print:' + k) || '') || {}; } catch (e) { return {}; }
  }
  function printWith(target, o) {
    var size = PAPER[o.paper] || PAPER.A4, land = o.orient === 'landscape', m = MARGIN[o.margin] != null ? MARGIN[o.margin] : 12;
    var wMm = (land ? size[1] : size[0]) - 2 * m, hMm = (land ? size[0] : size[1]) - 2 * m, px = 96 / 25.4;
    var zoom = 1;
    if (o.scale === 'fit' || o.scale === 'width') {
      target.classList.add('measure'); target.style.width = Math.floor(wMm * px) + 'px';
      var h = target.scrollHeight;
      target.classList.remove('measure'); target.style.width = '';
      if (o.scale === 'fit') zoom = Math.min(1, (hMm * px * 0.97) / Math.max(h, 1));
    } else zoom = (+o.scale || 100) / 100;
    var st = $('#sd-page') || document.head.appendChild(el('style', { id: 'sd-page' }));
    st.textContent = '@page{size:' + (o.paper || 'A4') + ' ' + (land ? 'landscape' : 'portrait') + ';margin:' + m + 'mm}';
    document.documentElement.style.setProperty('--pz', zoom.toFixed(3));
    return zoom;
  }
  $$('[data-print]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var target = $(btn.dataset.print), o = printOpts(btn.dataset.print);
      o = { paper: o.paper || btn.dataset.paper || 'A4', orient: o.orient || 'portrait', margin: o.margin || 'normal', scale: o.scale || 'fit' };
      var sel = function (name, label, opts) {
        return '<div class="field"><label>' + label + '</label><select class="inp" name="' + name + '">' + opts.map(function (x) {
          return '<option value="' + x[0] + '"' + (o[name] === x[0] ? ' selected' : '') + '>' + x[1] + '</option>'; }).join('') + '</select></div>';
      };
      var m = el('div', { class: 'modal', role: 'dialog', 'aria-modal': 'true', 'aria-label': 'Print options' },
        '<form class="dlg"><h2>Print</h2><div class="form">' +
        sel('paper', 'Paper', [['A4', 'A4'], ['A5', 'A5'], ['Letter', 'Letter']]) +
        sel('orient', 'Orientation', [['portrait', 'Portrait'], ['landscape', 'Landscape']]) +
        sel('scale', 'Size', [['fit', 'Fit to one page'], ['width', 'Fit to page width'], ['100', 'Actual size (100%)'], ['90', '90%'], ['80', '80%'], ['70', '70%'], ['60', '60%']]) +
        sel('margin', 'Margins', [['normal', 'Normal'], ['narrow', 'Narrow'], ['none', 'None']]) +
        '</div><div class="pfit" data-fit></div><p class="hint">In the next window, keep Scale at Default and turn off Headers and footers for a clean page.</p>' +
        '<div class="row" style="justify-content:flex-end;margin-top:6px"><button type="button" class="btn" data-x>Cancel</button><button class="btn pri">Print</button></div></form>');
      document.body.appendChild(m);
      var form = $('form', m);
      function read() { new FormData(form).forEach(function (v, k) { o[k] = v; }); }
      function info() {
        read();
        var z = printWith(target, o);
        $('[data-fit]', m).textContent = (o.paper + ' ' + o.orient) + ' · printed at ' + Math.round(z * 100) + '%' + (o.scale === 'fit' ? ', fits on one page' : '');
      }
      function close() { m.remove(); btn.focus(); }
      form.addEventListener('change', info);
      form.addEventListener('submit', function (e) {
        e.preventDefault(); read();
        try { localStorage.setItem('sd-print:' + btn.dataset.print, JSON.stringify(o)); } catch (err) {}
        printWith(target, o); m.remove();
        var iso = 'isolate' in btn.dataset;
        if (iso) { document.body.classList.add('print-isolate'); target.classList.add('print-target'); }
        setTimeout(function () {
          window.print();
          if (iso) setTimeout(function () { document.body.classList.remove('print-isolate'); target.classList.remove('print-target'); }, 500);
        }, 50);
      });
      $('[data-x]', m).addEventListener('click', close);
      m.addEventListener('click', function (e) { if (e.target === m) close(); });
      m.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
      info();
      $('select', m).focus();
    });
  });

  // ---------- Medicine rows: prescription form and template editor ----------
  function medRows(form) {
    var body = $('[data-meds]', form), tpl = $('#med-tpl', form);
    function add(v) {
      var tr = tpl.content.firstElementChild.cloneNode(true), i = body.children.length + Date.now() % 100000;
      $$('[data-f]', tr).forEach(function (inp) { inp.name = 'meds[' + i + '][' + inp.dataset.f + ']'; if (v && v[inp.dataset.f] != null) inp.value = v[inp.dataset.f]; });
      body.appendChild(tr);
      tr.style.animation = 'rise .3s var(--ease-out)';
      return tr;
    }
    function set(list) { body.innerHTML = ''; (list || []).forEach(add); if (!body.children.length) add(); }
    function rowsNow() {
      return [].map.call(body.children, function (tr) { var v = {}; $$('[data-f]', tr).forEach(function (i) { v[i.dataset.f] = i.value.trim(); }); return v; }).filter(function (v) { return v.name; });
    }
    // Picking a library medicine fills its usual form, dose, timing and days.
    body.addEventListener('input', function (e) {
      var n = e.target.closest('[data-f="name"]'); if (!n) return;
      var o = $$('#rx-meds option').filter(function (x) { return x.value.toLowerCase() === n.value.trim().toLowerCase(); })[0];
      if (!o) return;
      var tr = n.closest('tr');
      ['form', 'dose', 'timing', 'days'].forEach(function (k) { var i = $('[data-f="' + k + '"]', tr); if (i && o.dataset[k]) i.value = o.dataset[k]; });
    });
    body.addEventListener('click', function (e) {
      var d = e.target.closest('[data-del]');
      if (!d) return;
      d.closest('tr').remove();
      if (!body.children.length) add();
    });
    $('[data-add]', form).addEventListener('click', function () { $('[data-f="name"]', add()).focus(); });
    return { set: set, add: add, get: rowsNow };
  }

  var rxForm = $('#rxform');
  if (rxForm) (function () {
    var rows = medRows(rxForm), tpls = JSON.parse(rxForm.dataset.templates || '{}'), old = JSON.parse(rxForm.dataset.old || 'null');
    var sel = $('[data-tpl]', rxForm), advice = $('[data-advice]', rxForm);
    patientPicker(rxForm, function () {});
    function load(id, quiet) {
      var t = tpls[id];
      if (!t) return;
      rows.set(t.meds);
      advice.value = t.advice || '';
      if (!quiet) toast('Template loaded');
    }
    if (old) rows.set(Array.isArray(old) ? old : Object.keys(old).map(function (k) { return old[k]; }));
    else if (rxForm.dataset.default && tpls[rxForm.dataset.default]) { sel.value = rxForm.dataset.default; load(sel.value, true); }
    else rows.set([]);
    sel.addEventListener('change', function () { if (sel.value) load(sel.value); });
    rxForm.addEventListener('submit', function () { $('[data-save]', rxForm).disabled = true; });
    // Next visit: optionally book the appointment right here
    var nb = $('[data-nextbook]', rxForm);
    if (nb) (function () {
      var tick = $('[data-book-next]', nb), body = $('[data-nb-body]', nb), chair = $('[data-nb-chair]', nb), min = $('[data-nb-min]', nb);
      var dateHost = $('[data-next-date]', rxForm), first = nb.dataset.oldMin;
      var sp = slotPicker($('[data-nb-slots]', nb), function (m) { min.value = m == null ? '' : m; });
      function date() { var i = $('input[name="next_visit"]', rxForm); return i ? i.value : ''; }
      function load() {
        if (!tick.checked || !date()) return;
        sp.load(date(), chair.value);
        if (first) { var want = +first; first = null; setTimeout(function () { var b = $('[data-nb-slots] button[data-m="' + want + '"]', nb); if (b) b.click(); }, 600); }
      }
      dateHost.addEventListener('datechange', function () { nb.classList.toggle('hide', !date()); if (!date()) tick.checked = false, body.classList.add('hide'); min.value = ''; load(); });
      tick.addEventListener('change', function () { body.classList.toggle('hide', !tick.checked); if (!tick.checked) min.value = ''; load(); });
      chair.addEventListener('change', load);
      load();
    })();
    // Save what is typed now as a new template, without leaving the visit
    var saveTpl = $('[data-save-tpl]', rxForm);
    if (saveTpl) saveTpl.addEventListener('click', function () {
      var meds = rows.get();
      if (!meds.length) return toast('Add at least one medicine first.', true);
      var name = prompt('Name for this template, e.g. Wisdom tooth removal');
      if (!name || !name.trim()) return;
      saveTpl.disabled = true;
      send('POST', saveTpl.dataset.saveTpl, { name: name.trim(), meds: meds, advice: advice.value }).then(function (t) {
        tpls[t.id] = { meds: t.meds, advice: t.advice };
        var o = el('option', { value: t.id }); o.textContent = t.name; sel.appendChild(o); sel.value = t.id;
        toast('Template ' + t.name + ' saved'); pulse(saveTpl);
      }).catch(function (e) { toast(e.message, true); }).then(function () { saveTpl.disabled = false; });
    });
  })();

  var tplForm = $('#tplform');
  if (tplForm) (function () {
    var rows = medRows(tplForm), old = JSON.parse(tplForm.dataset.old || '[]');
    rows.set(Array.isArray(old) ? old : Object.keys(old).map(function (k) { return old[k]; }));
  })();

  // ---------- Tooth chart: tap a tooth, see its history, mark its state ----------
  $$('[data-chart]').forEach(function (card) {
    var cfg = JSON.parse(card.dataset.chart), panel = $('[data-panel]', card), cur = null;
    function draw(n) {
      var m = cfg.marks[n] || { state: 'healthy' }, hist = cfg.history[n] || [], st = m.state;
      var h = '<h3>Tooth ' + n + ' · ' + esc(cfg.names[n]) + '</h3><div class="muted">' + esc(cfg.states[st]) +
        (m.by ? ' · marked by ' + esc(m.by) + ' on ' + esc(m.at) : '') + '</div>' + (m.note ? '<p style="margin:6px 0 0">' + esc(m.note) + '</p>' : '');
      if (cfg.save) {
        h += '<div class="tstates" role="radiogroup" aria-label="State of tooth ' + n + '">' + Object.keys(cfg.states).map(function (k) {
          return '<button type="button" role="radio" aria-checked="' + (k === st) + '" class="' + (k === st ? 'on' : '') + '" data-st="' + k + '"><i class="st-' + k + '" style="background:var(--surface)"></i>' + esc(cfg.states[k]) + '</button>';
        }).join('') + '</div><div class="row" style="flex-wrap:nowrap"><input class="inp" data-note maxlength="250" placeholder="Note, e.g. deep caries, distal" value="' + esc(m.note || '') + '" aria-label="Note for tooth ' + n + '"><button type="button" class="btn pri" data-tsave>Save</button></div>';
      }
      h += '<b style="display:block;margin-top:12px">Visits for this tooth</b>' + (hist.length ? '<ul class="thist">' + hist.map(function (v) {
        return '<li><span class="mono muted" style="width:92px;flex:none">' + esc(v.date) + '</span><span style="flex:1">' + esc(v.what) + '</span>' + (cfg.rxShow ? '<a class="btn sm" href="' + cfg.rxShow + '/' + v.id + '">View</a>' : '') + '</li>';
      }).join('') + '</ul>' : '<p class="muted" style="margin:4px 0 0">No visits name this tooth yet.</p>') +
        (cfg.rx ? '<a class="btn" style="margin-top:10px" href="' + cfg.rx + '&tooth=' + n + '">New visit for tooth ' + n + '</a>' : '');
      panel.innerHTML = h;
      panel.style.animation = 'none'; void panel.offsetWidth; panel.style.animation = '';
    }
    card.addEventListener('click', function (e) {
      var t = e.target.closest('.tooth');
      if (t) {
        $$('.tooth.sel', card).forEach(function (x) { x.classList.remove('sel'); });
        t.classList.add('sel'); cur = +t.dataset.n; draw(cur);
        return;
      }
      var set = e.target.closest('[data-set]');
      if (set) {
        $$('[data-set]', card).forEach(function (b) { b.classList.toggle('on', b === set); });
        $$('[data-teeth]', card).forEach(function (d) { d.hidden = d.dataset.teeth !== set.dataset.set; });
        return;
      }
      var b = e.target.closest('[data-st]');
      if (b) { $$('[data-st]', panel).forEach(function (x) { x.classList.toggle('on', x === b); x.setAttribute('aria-checked', x === b); }); return; }
      if (e.target.closest('[data-tsave]')) {
        var on = $('[data-st].on', panel), note = $('[data-note]', panel).value.trim(), st = on ? on.dataset.st : 'healthy', btn = e.target.closest('[data-tsave]');
        btn.disabled = true;
        send('POST', cfg.save, { tooth: cur, state: st, note: note }).then(function (r) {
          if (st === 'healthy' && !note) delete cfg.marks[cur]; else cfg.marks[cur] = { state: st, note: note, by: r.by, at: r.at };
          var t = $('.tooth[data-n="' + cur + '"]', card);
          t.className = t.className.replace(/\bst-\w+/g, '').trim() + ' st-' + st;
          t.classList.remove('flash'); void t.offsetWidth; t.classList.add('flash');
          toast('Tooth ' + cur + ' saved'); draw(cur);
        }).catch(function (err) { toast(err.message, true); btn.disabled = false; });
      }
    });
  });
  var dashPick = $('#dashpick');
  if (dashPick) patientPicker(dashPick, function () {
    var id = $('[data-pid]', dashPick).value;
    if (id) location.href = dashPick.dataset.go + '/' + id;
  });
  var teethPick = $('#teethpick');
  if (teethPick) patientPicker(teethPick, function () {
    var id = $('[data-pid]', teethPick).value;
    if (id) location.href = teethPick.dataset.go + '?patient=' + id;
  });

  // ---------- Online booking: QR card, copy link, open slots preview ----------
  function drawQr(box, text) {
    if (!window.qrcode) return;
    var q = window.qrcode(0, 'M'); q.addData(text); q.make();
    box.innerHTML = q.createSvgTag({ cellSize: 6, margin: 2, scalable: true });
  }
  $$('[data-qr]').forEach(function (b) { drawQr(b, b.dataset.qr); });
  $$('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () {
      var done = function () { toast('Link copied'); };
      if (navigator.clipboard) navigator.clipboard.writeText(b.dataset.copy).then(done, function () { prompt('Copy this link', b.dataset.copy); });
      else prompt('Copy this link', b.dataset.copy);
    });
  });
  $$('[data-qr-download]').forEach(function (b) {
    b.addEventListener('click', function () {
      var svg = $('.qr svg'), card = $('#qrcard');
      if (!svg) return;
      var c = document.createElement('canvas'), W = 600, H = 820, x = c.getContext('2d'), img = new Image();
      c.width = W; c.height = H;
      x.fillStyle = '#ffffff'; x.fillRect(0, 0, W, H);
      x.fillStyle = '#10211f'; x.textAlign = 'center';
      x.font = '700 30px "Bricolage Grotesque", sans-serif'; x.fillText($('.qr-clinic', card).textContent, W / 2, 80);
      x.fillStyle = '#0d6e66'; x.font = '700 40px "Bricolage Grotesque", sans-serif'; x.fillText('Book your next visit', W / 2, 140);
      img.onload = function () {
        x.drawImage(img, 100, 180, 400, 400);
        x.fillStyle = '#555c59'; x.font = '24px "IBM Plex Sans", sans-serif'; x.fillText('Scan with your phone camera', W / 2, 640);
        x.fillStyle = '#10211f'; x.font = '20px "IBM Plex Mono", monospace'; x.fillText($('[data-link-text]', card).textContent, W / 2, 690);
        var a = el('a', { href: c.toDataURL('image/png'), download: b.dataset.qrDownload }); document.body.appendChild(a); a.click(); a.remove();
      };
      img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg.outerHTML);
    });
  });
  var bookForm = $('[data-booking-form]');
  if (bookForm) bookForm.addEventListener('autosaved', function (e) {
    var link = e.detail && e.detail.link;
    if (!link) return;
    $$('[data-copy]').forEach(function (b) { b.dataset.copy = link; });
    $$('[data-link-open]').forEach(function (a) { a.href = link; });
    $$('[data-link-text]').forEach(function (s) { s.textContent = link; });
    $$('[data-slug-echo]').forEach(function (s) { s.textContent = link.split('/').pop(); });
    $$('[data-qr]').forEach(function (q) { q.dataset.qr = link; drawQr(q, link); });
  });
  var openSlots = $('#openslots');
  if (openSlots) (function () {
    var sp = slotPicker($('[data-slots]', openSlots), function () {}, '/api/booking/slots'), chair = $('#obch'), date = $('input[name="obdate"]', openSlots);
    function load() { if (chair.value) sp.load(date.value, chair.value); }
    openSlots.addEventListener('datechange', load);
    chair.addEventListener('change', load);
    load();
  })();

  // ---------- Patient booking page: day, then a free time, then details ----------
  var pub = $('#pubbook');
  if (pub) (function () {
    var dateIn = $('[data-date-value]', pub), timeIn = $('[data-time-value]', pub), times = $('[data-times]', pub), btn = $('[data-book-btn]', pub), req = 0;
    function label(m) { return fmtTime(m); }
    function refresh() {
      var ok = dateIn.value && timeIn.value;
      btn.disabled = !ok;
      btn.textContent = ok ? 'Book ' + fmtKey(dateIn.value).replace(/ \d{4}$/, '') + ', ' + label(+timeIn.value) : 'Pick a day and time';
    }
    function pickDay(d, keepTime) {
      $$('[data-day]', pub).forEach(function (b) { b.setAttribute('aria-checked', b.dataset.day === d); });
      dateIn.value = d; if (!keepTime) timeIn.value = ''; refresh();
      var mine = ++req;
      times.innerHTML = '<p class="muted">Loading times…</p>';
      get(pub.dataset.slotsUrl + '?date=' + d).then(function (list) {
        if (mine !== req) return;
        if (!list.length) { times.innerHTML = '<p class="muted">No free times on this day. Please pick another day.</p>'; timeIn.value = ''; refresh(); return; }
        times.innerHTML = [['Morning', list.filter(function (m) { return m < 900; })], ['Evening', list.filter(function (m) { return m >= 900; })]].filter(function (g) { return g[1].length; }).map(function (g) {
          return '<div><small class="muted">' + g[0] + '</small><div class="tg">' + g[1].map(function (m, i) {
            return '<button type="button" data-m="' + m + '" style="animation-delay:' + Math.min(i * 15, 300) + 'ms" class="' + (String(m) === timeIn.value ? 'on' : '') + '">' + label(m).replace(' AM', ' am').replace(' PM', ' pm') + '</button>';
          }).join('') + '</div></div>';
        }).join('');
        if (timeIn.value && !$('[data-m="' + timeIn.value + '"]', times)) { timeIn.value = ''; refresh(); }
      }).catch(function () { times.innerHTML = '<p class="err">Could not load times. Check your internet and try again.</p>'; });
    }
    pub.addEventListener('click', function (e) {
      var d = e.target.closest('[data-day]');
      if (d) return pickDay(d.dataset.day);
      var t = e.target.closest('[data-m]');
      if (t) { $$('[data-m]', times).forEach(function (x) { x.classList.toggle('on', x === t); }); timeIn.value = t.dataset.m; refresh(); $('#bn').focus({ preventScroll: true }); }
    });
    pub.addEventListener('submit', function () { btn.disabled = true; btn.textContent = 'Booking…'; });
    if (pub.dataset.oldDate) { timeIn.value = pub.dataset.oldTime; pickDay(pub.dataset.oldDate, true); }
    else { var first = $('[data-day]', pub); if (first) pickDay(first.dataset.day); }
  })();
  // ---------- Visit photos: handwritten prescriptions (5) and X-rays (50), shrunk in the browser before upload ----------
  function shrink(file, max, quality) {
    return new Promise(function (ok, fail) {
      var img = new Image(), url = URL.createObjectURL(file);
      img.onload = function () {
        URL.revokeObjectURL(url);
        var s = Math.min(1, max / Math.max(img.naturalWidth, img.naturalHeight));
        var c = document.createElement('canvas'); c.width = Math.round(img.naturalWidth * s); c.height = Math.round(img.naturalHeight * s);
        var g = c.getContext('2d'); g.fillStyle = '#fff'; g.fillRect(0, 0, c.width, c.height); g.drawImage(img, 0, 0, c.width, c.height);
        c.toBlob(function (b) { b ? ok(b) : fail(new Error('Could not read this photo.')); }, 'image/jpeg', quality);
      };
      img.onerror = function () { URL.revokeObjectURL(url); fail(new Error(file.name + ' is not a photo this browser can open. Please use JPG or PNG.')); };
      img.src = url;
    });
  }
  // Live camera window (laptops). take(file) gets each photo; fallback() opens the normal file picker.
  function camera(left, take, fallback) {
    var m = el('div', { class: 'modal cam', role: 'dialog', 'aria-label': 'Camera' }), stream = null, facing = 'environment', shots = 0;
    m.innerHTML = '<div class="dlg cam-dlg"><div class="row" style="justify-content:space-between;margin-bottom:10px"><b>Camera</b><span class="muted" data-cam-n></span></div>' +
      '<div class="cam-view"><video playsinline muted autoplay></video><div class="cam-flash"></div><p class="cam-msg muted">Starting the camera…</p></div>' +
      '<div class="row cam-bar"><button type="button" class="btn" data-cam-x>Done</button><button type="button" class="btn hide" data-cam-flip>Switch camera</button>' +
      '<button type="button" class="btn pri cam-shot" data-cam-shot disabled aria-label="Take photo"><span></span>Take photo</button></div></div>';
    document.body.appendChild(m);
    var video = $('video', m), msg = $('.cam-msg', m), shot = $('[data-cam-shot]', m), flip = $('[data-cam-flip]', m), count = $('[data-cam-n]', m);
    function stop() { if (stream) stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; }
    function close() { stop(); m.remove(); document.removeEventListener('keydown', key); }
    function key(e) { if (e.key === 'Escape') close(); if ((e.key === ' ' || e.key === 'Enter') && !shot.disabled && document.activeElement === document.body) { e.preventDefault(); snap(); } }
    function showLeft() { var l = left(); count.textContent = shots ? shots + ' taken · ' + Math.max(0, l) + ' more allowed' : Math.max(0, l) + ' more allowed'; shot.disabled = !stream || l <= 0; }
    function start() {
      stop(); shot.disabled = true; msg.classList.remove('hide'); msg.textContent = 'Starting the camera…';
      navigator.mediaDevices.getUserMedia({ video: { facingMode: facing, width: { ideal: 1920 }, height: { ideal: 1080 } }, audio: false }).then(function (s) {
        stream = s; video.srcObject = s; msg.classList.add('hide'); showLeft();
        navigator.mediaDevices.enumerateDevices().then(function (d) { flip.classList.toggle('hide', d.filter(function (x) { return x.kind === 'videoinput'; }).length < 2); });
      }).catch(function (er) {
        var denied = er && (er.name === 'NotAllowedError' || er.name === 'SecurityError');
        msg.innerHTML = (denied ? 'The browser blocked the camera. Click the camera icon in the address bar, choose Allow, then try again.' : 'No camera was found on this device.') +
          '<br><button type="button" class="btn" style="margin-top:10px" data-cam-file>Choose a photo instead</button>';
      });
    }
    function snap() {
      if (!stream || left() <= 0) return;
      var c = document.createElement('canvas'); c.width = video.videoWidth; c.height = video.videoHeight; c.getContext('2d').drawImage(video, 0, 0);
      var fl = $('.cam-flash', m); fl.classList.remove('go'); void fl.offsetWidth; fl.classList.add('go');
      c.toBlob(function (b) {
        if (!b) return toast('Could not take the photo.', true);
        shots++; take(new File([b], 'camera-' + Date.now() + '.jpg', { type: 'image/jpeg' })); setTimeout(showLeft, 50);
      }, 'image/jpeg', 0.92);
    }
    m.addEventListener('click', function (e) {
      if (e.target.closest('[data-cam-x]') || e.target === m) close();
      else if (e.target.closest('[data-cam-shot]')) snap();
      else if (e.target.closest('[data-cam-flip]')) { facing = facing === 'environment' ? 'user' : 'environment'; start(); }
      else if (e.target.closest('[data-cam-file]')) { close(); fallback(); }
    });
    document.addEventListener('keydown', key);
    start();
  }
  function lightbox(list, i) {
    var m = el('div', { class: 'modal lbx', role: 'dialog', 'aria-label': 'Photo' });
    function draw() {
      var f = list[i];
      m.innerHTML = '<div class="lbx-bar"><span>' + esc(f.name || '') + ' · ' + (i + 1) + ' of ' + list.length + (f.at ? '<br><small class="lbx-at">Uploaded ' + esc(f.at) + (f.by ? ' by ' + esc(f.by) : '') + '</small>' : '') + '</span><span class="row"><a class="btn sm" href="' + f.download + '">Download</a><button type="button" class="btn sm" data-x>Close</button></span></div>' +
        '<img src="' + f.url + '" alt="">' +
        (list.length > 1 ? '<button type="button" class="lbx-nav prev" data-d="-1" aria-label="Previous">‹</button><button type="button" class="lbx-nav next" data-d="1" aria-label="Next">›</button>' : '');
    }
    function close() { m.remove(); document.removeEventListener('keydown', key); }
    function key(e) { if (e.key === 'Escape') close(); if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') { i = (i + (e.key === 'ArrowRight' ? 1 : -1) + list.length) % list.length; draw(); } }
    m.addEventListener('click', function (e) {
      var d = e.target.closest('[data-d]');
      if (d) { i = (i + +d.dataset.d + list.length) % list.length; draw(); return; }
      if (e.target.closest('[data-x]') || e.target === m) close();
    });
    document.addEventListener('keydown', key);
    draw(); document.body.appendChild(m);
  }
  // Patient file: all of a patient's photos and X-rays, each kind one viewer list.
  $$('[data-pgallery]').forEach(function (sec) {
    var list = JSON.parse(sec.dataset.pgallery);
    sec.addEventListener('click', function (e) { var b = e.target.closest('.vp-img[data-i]'); if (b) lightbox(list, +b.dataset.i); });
  });
  $$('[data-vphotos]').forEach(function (box) {
    var cfg = JSON.parse(box.dataset.vphotos), files = cfg.files;
    function of(kind) { return files.filter(function (f) { return f.kind === kind; }); }
    function render(kind) {
      var sec = $('[data-kind="' + kind + '"]', box), grid = $('[data-grid]', sec), list = of(kind), left = cfg.limits[kind] - list.length;
      $('[data-count]', sec).textContent = list.length + ' of ' + cfg.limits[kind];
      $('[data-add]', sec).classList.toggle('hide', left <= 0);
      $$('.vp-tile:not(.busy)', grid).forEach(function (t) { t.remove(); });
      list.forEach(function (f, i) {
        var t = el('div', { class: 'vp-tile' }, '<button type="button" class="vp-img" aria-label="Open ' + esc(f.name || 'photo') + '"><img src="' + f.thumb + '" alt="" loading="lazy"></button>' +
          (f.at ? '<span class="vp-at" title="Uploaded ' + esc(f.at) + (f.by ? ' by ' + esc(f.by) : '') + '">' + esc(f.at) + '</span>' : '') +
          (f.del ? '<button type="button" class="vp-del" aria-label="Delete photo" title="Delete permanently">✕</button>' : ''));
        $('.vp-img', t).addEventListener('click', function () { lightbox(list, i); });
        if (f.del) $('.vp-del', t).addEventListener('click', function () {
          if (!confirm('Delete this photo permanently?')) return;
          send('DELETE', f.del).then(function () { files = files.filter(function (x) { return x.id !== f.id; }); render(kind); toast('Photo deleted'); }).catch(function (e) { toast(e.message, true); });
        });
        grid.insertBefore(t, $('.vp-tile.busy', grid));
      });
      if (!list.length && !$('.vp-tile.busy', grid)) grid.innerHTML = '<p class="muted vp-empty">None yet.</p>'; else { var e = $('.vp-empty', grid); if (e) e.remove(); }
    }
    function upload(kind, file) {
      var grid = $('[data-kind="' + kind + '"] [data-grid]', box), e = $('.vp-empty', grid); if (e) e.remove();
      var t = el('div', { class: 'vp-tile busy' }, '<span class="vp-prog"><i></i></span>'); grid.appendChild(t);
      var bar = $('i', t);
      return Promise.all([shrink(file, 2400, 0.86), shrink(file, 360, 0.75)]).then(function (b) {
        var fd = new FormData(); fd.append('kind', kind); fd.append('name', file.name.replace(/\.[^.]+$/, '') + '.jpg');
        fd.append('file', b[0], 'photo.jpg'); fd.append('thumb', b[1], 'thumb.jpg');
        return new Promise(function (ok, fail) {
          var x = new XMLHttpRequest(); x.open('POST', cfg.upload); x.setRequestHeader('X-CSRF-TOKEN', CSRF); x.setRequestHeader('Accept', 'application/json');
          x.upload.onprogress = function (ev) { if (ev.lengthComputable) bar.style.width = Math.round(ev.loaded / ev.total * 100) + '%'; };
          x.onload = function () { var j = {}; try { j = JSON.parse(x.responseText); } catch (er) {} x.status < 300 ? ok(j) : fail(new Error(x.status === 419 ? 'Your session expired. Reload the page.' : (j.message || 'Upload failed.'))); };
          x.onerror = function () { fail(new Error('Upload failed. Check your internet.')); };
          x.send(fd);
        });
      }).then(function (j) { t.remove(); files.push(j); render(kind); }, function (er) { t.remove(); render(kind); throw er; });
    }
    function addFiles(kind, picked) {
      var left = cfg.limits[kind] - of(kind).length - $$('[data-kind="' + kind + '"] .vp-tile.busy', box).length;
      if (!picked.length) return;
      if (left <= 0) return toast('This visit already has the most allowed.', true);
      if (picked.length > left) { toast('Only ' + left + ' more can be added to this visit. The first ' + left + ' will be uploaded.', true); picked = picked.slice(0, left); }
      var done = 0;
      picked.reduce(function (p, f) { return p.then(function () { return upload(kind, f).then(function () { done++; }, function (er) { toast(er.message, true); }); }); }, Promise.resolve())
        .then(function () { if (done) toast(done === 1 ? 'Photo added' : done + ' photos added'); });
    }
    $$('[data-pick]', box).forEach(function (inp) {
      inp.addEventListener('change', function () {
        var kind = inp.closest('[data-kind]').dataset.kind, picked = [].slice.call(inp.files);
        inp.value = '';
        addFiles(kind, picked);
      });
    });
    // Camera: phones and tablets open their own camera app; laptops get a live camera window here
    var touch = window.matchMedia && matchMedia('(pointer: coarse)').matches;
    $$('[data-cam]', box).forEach(function (lbl) {
      lbl.addEventListener('click', function (e) {
        if (lbl.dataset.native) { delete lbl.dataset.native; return; } // "choose a photo instead"
        if (touch || !(navigator.mediaDevices && navigator.mediaDevices.getUserMedia)) return; // native camera input
        e.preventDefault();
        var kind = lbl.closest('[data-kind]').dataset.kind;
        camera(function () { return cfg.limits[kind] - of(kind).length - $$('[data-kind="' + kind + '"] .vp-tile.busy', box).length; },
          function (file) { addFiles(kind, [file]); },
          function () { lbl.dataset.native = '1'; $('input', lbl).click(); });
      });
    });
    Object.keys(cfg.limits).forEach(render);
  });
  // ---------- Month view: show who is booked when pointing at a date ----------
  var mtip = null;
  function hideTip() { if (mtip) { mtip.remove(); mtip = null; } }
  function showTip(a) {
    hideTip();
    var src = $('.mtip', a); if (!src) return;
    mtip = el('div', { class: 'mtip-float', role: 'tooltip' }, src.innerHTML); document.body.appendChild(mtip);
    var r = a.getBoundingClientRect(), w = mtip.offsetWidth, h = mtip.offsetHeight;
    var x = r.right + 8 + w > window.innerWidth - 8 ? r.left - w - 8 : r.right + 8;
    var y = Math.min(Math.max(8, r.top), window.innerHeight - h - 8);
    mtip.style.left = Math.max(8, x) + 'px'; mtip.style.top = y + 'px';
  }
  $$('[data-mtip]').forEach(function (a) {
    a.addEventListener('mouseenter', function () { showTip(a); });
    a.addEventListener('focus', function () { showTip(a); });
    a.addEventListener('mouseleave', hideTip); a.addEventListener('blur', hideTip);
  });
  window.addEventListener('scroll', hideTip, { passive: true });
})();
