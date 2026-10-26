/* Sepo Forms — form stepping and sending, tabs, the submissions table, the forms index. */
(function () {
  'use strict';

  var CFG = window.SepoFormsConfig || { api: '', nonce: '', i18n: {} };
  var T = CFG.i18n || {};

  // ------------------------------------------------------------ helpers

  function esc(s) {
    return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }
  function fmt(s) {
    var args = Array.prototype.slice.call(arguments, 1), i = 0;
    return String(s || '').replace(/%(\d+)\$[sd]|%[sd]/g, function (m, n) { return String(n ? args[n - 1] : args[i++]); });
  }
  function qa(root, sel) { return Array.prototype.slice.call(root.querySelectorAll(sel)); }
  function children(el, cls) {
    return Array.prototype.filter.call(el ? el.children : [], function (c) { return c.classList.contains(cls); });
  }

  function api(method, path, body, isForm) {
    var headers = { 'Accept': 'application/json' };
    if (CFG.nonce) { headers['X-WP-Nonce'] = CFG.nonce; }
    var opts = { method: method, credentials: 'same-origin', headers: headers };
    if (body !== undefined) {
      if (isForm) { opts.body = body; }
      else { headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
    }
    return fetch(CFG.api + path, opts).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (b) {
        if (!r.ok || (b && b.status === 'error')) {
          var e = new Error((b && b.message) || (T.tryAgain || 'Please try again.'));
          e.data = b && b.data;
          e.status = r.status;
          throw e;
        }
        return b;
      });
    });
  }

  var ICON_CHECK = '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>';
  var ICON_X = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
  var ICON_SORT = '<svg class="sp-table-sort-icon" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>';

  // ------------------------------------------------------------ dark mode

  /** A declared theme marker wins; otherwise the page background decides. */
  function isDark() {
    var nodes = [document.documentElement, document.body];
    var attrs = ['data-theme', 'data-bs-theme', 'data-color-scheme', 'data-mode', 'data-color-mode'];
    for (var i = 0; i < nodes.length; i++) {
      var n = nodes[i];
      if (!n) { continue; }
      for (var j = 0; j < attrs.length; j++) {
        var v = (n.getAttribute(attrs[j]) || '').toLowerCase();
        if (v === 'dark') { return true; }
        if (v === 'light') { return false; }
      }
      if (/(^|\s)(dark|dark-mode|theme-dark|is-dark-theme|wp-dark-mode-active)(\s|$)/.test(n.className || '')) { return true; }
    }
    var bg = '';
    nodes.forEach(function (n) {
      if (!n) { return; }
      var c = getComputedStyle(n).backgroundColor;
      if (c && !/rgba\(\s*0,\s*0,\s*0,\s*0\s*\)|transparent/.test(c)) { bg = c; }
    });
    var m = bg.match(/rgba?\(\s*(\d+)[,\s]+(\d+)[,\s]+(\d+)/);
    return !!m && (0.2126 * m[1] + 0.7152 * m[2] + 0.0722 * m[3]) / 255 < 0.4;
  }
  function paintMode() {
    var dark = isDark();
    qa(document, '.sf-scope').forEach(function (s) { s.classList.toggle('sf-dark', dark); });
  }

  // ------------------------------------------------------------ dialog

  function modal(opts) {
    var scope = document.createElement('div');
    scope.className = 'sf-scope' + (isDark() ? ' sf-dark' : '');
    scope.innerHTML = '<div class="sf-modal" role="dialog" aria-modal="true" aria-labelledby="sf-modal-t">'
      + '<div class="sf-modal-card' + (opts.wide ? ' sf-modal-card--wide' : '') + '" tabindex="-1">'
      + '<h3 class="sf-modal-title" id="sf-modal-t">' + esc(opts.title) + '</h3>'
      + '<div class="sf-modal-body">' + (opts.html || esc(opts.body || '')) + '</div>'
      + '<div class="sf-modal-actions">'
      + (opts.cancel !== false ? '<button type="button" class="sf-btn" data-m="cancel">' + esc(opts.cancel || T.cancel || 'Cancel') + '</button>' : '')
      + (opts.ok ? '<button type="button" class="sf-btn ' + (opts.danger ? 'sf-btn-danger' : 'sf-btn-primary') + '" data-m="ok">' + esc(opts.ok) + '</button>' : '')
      + '</div></div></div>';
    document.body.appendChild(scope);
    var last = document.activeElement;
    var card = scope.querySelector('.sf-modal-card');
    return new Promise(function (resolve) {
      function done(v) {
        document.removeEventListener('keydown', onKey, true);
        scope.remove();
        if (last && last.focus) { last.focus(); }
        resolve(v);
      }
      function onKey(e) {
        if (e.key === 'Escape') { e.preventDefault(); done(false); }
        if (e.key === 'Tab') {
          var f = qa(card, 'button, a[href], input, [tabindex]:not([tabindex="-1"])');
          if (!f.length) { return; }
          if (e.shiftKey && (document.activeElement === f[0] || document.activeElement === card)) { e.preventDefault(); f[f.length - 1].focus(); }
          else if (!e.shiftKey && document.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
        }
      }
      document.addEventListener('keydown', onKey, true);
      scope.addEventListener('click', function (e) {
        var b = e.target.closest('[data-m]');
        if (b) { done(b.getAttribute('data-m') === 'ok'); }
        else if (e.target.classList.contains('sf-modal')) { done(false); }
      });
      (scope.querySelector('[data-m="ok"]') || card).focus();
    });
  }

  // ------------------------------------------------------------ tabs

  function initTabs(root) {
    qa(root, '.sp-tabs-lite').forEach(function (box) {
      if (box.getAttribute('data-sp-init') === '1') { return; }
      box.setAttribute('data-sp-init', '1');
      var nav = children(box, 'sp-tabs-lite-nav')[0];
      var wrap = children(box, 'sp-tabs-lite-panels')[0];
      if (!nav || !wrap) { return; }
      var btns = children(nav, 'sp-tabs-lite-btn');
      var panels = children(wrap, 'sp-tabs-lite-panel');
      function activate(i, focus) {
        btns.forEach(function (b, k) {
          var on = k === i;
          b.classList.toggle('active', on);
          b.setAttribute('aria-selected', on ? 'true' : 'false');
          b.tabIndex = on ? 0 : -1;
          if (on && focus) { b.focus(); }
        });
        panels.forEach(function (p, k) {
          p.classList.toggle('active', k === i);
          p.hidden = k !== i;
          if (k === i) { qa(p, '.sf-tab-lazy').forEach(loadLazy); }
        });
      }
      btns.forEach(function (b, i) {
        b.addEventListener('click', function () { activate(i); });
        b.addEventListener('keydown', function (e) {
          var n = { ArrowRight: i + 1, ArrowLeft: i - 1, Home: 0, End: btns.length - 1 }[e.key];
          if (n === undefined) { return; }
          e.preventDefault();
          activate((n + btns.length) % btns.length, true);
        });
      });
      box.spActivate = activate;
    });
  }

  // ------------------------------------------------------------ the form

  function initForm(shell) {
    if (shell.getAttribute('data-sf-init') === '1') { return; }
    shell.setAttribute('data-sf-init', '1');
    var form = shell.querySelector('[data-sf-form]');
    if (!form) { return; }
    var formId = shell.getAttribute('data-form-id');
    var total = parseInt(shell.getAttribute('data-total-steps'), 10) || 1;
    var submitText = shell.getAttribute('data-submit-text') || 'Submit';
    var pages = qa(form, '[data-sf-page]');
    var steps = qa(shell, '.sf-step');
    var lastBlock = form.querySelector('[data-sf-last]');
    var prev = form.querySelector('[data-sf-prev]');
    var next = form.querySelector('[data-sf-next]');
    var submit = form.querySelector('[data-sf-submit]');
    var result = form.querySelector('[data-sf-result]');
    var stepError = form.querySelector('[data-sf-step-error]');
    var current = 0;
    var sent = false;
    var dirty = false;

    function title(i) {
      var l = steps[i] && steps[i].querySelector('.sf-step-label');
      return l ? l.textContent.trim() : '';
    }

    function show(n, scroll) {
      current = Math.min(Math.max(n, 0), total - 1);
      pages.forEach(function (p) { p.hidden = parseInt(p.getAttribute('data-sf-page'), 10) !== current; });
      if (lastBlock) { lastBlock.hidden = current !== total - 1; }
      steps.forEach(function (s, i) {
        s.classList.toggle('is-current', i === current);
        s.classList.toggle('is-done', i < current);
        if (i === current) { s.setAttribute('aria-current', 'step'); } else { s.removeAttribute('aria-current'); }
      });
      if (prev) { prev.hidden = current === 0; }
      if (next) { next.hidden = current >= total - 1; }
      if (submit) { submit.hidden = current < total - 1 || sent; }
      var pct = Math.round((current + 1) / total * 100);
      var fill = shell.querySelector('[data-sf-progress-fill]');
      if (fill) {
        fill.style.width = pct + '%';
        fill.parentNode.setAttribute('aria-valuenow', String(pct));
      }
      var label = shell.querySelector('[data-sf-progress-label]');
      if (label) { label.textContent = fmt(T.step, current + 1, total) + (title(current) ? ' · ' + title(current) : ''); }
      var nx = shell.querySelector('[data-sf-progress-next]');
      if (nx) { nx.textContent = current < total - 1 ? fmt(T.next, title(current + 1)) : (total > 1 ? (T.last || '') : ''); }
      if (stepError) { stepError.hidden = true; }
      var cur = steps[current];
      if (cur && cur.scrollIntoView && cur.parentNode.scrollWidth > cur.parentNode.clientWidth) {
        cur.parentNode.scrollLeft = cur.offsetLeft - 20;
      }
      if (scroll) {
        var top = shell.getBoundingClientRect().top + window.pageYOffset - 90;
        if (Math.abs(window.pageYOffset - top) > 120) { window.scrollTo({ top: top, behavior: 'smooth' }); }
      }
    }

    function setError(q, msg) {
      if (!q) { return; }
      var box = q.querySelector('[data-sf-error]');
      q.classList.toggle('has-error', !!msg);
      if (box) {
        box.textContent = msg || '';
        box.hidden = !msg;
      }
      qa(q, 'input, textarea').forEach(function (el) {
        if (el.type !== 'hidden') { el.setAttribute('aria-invalid', msg ? 'true' : 'false'); }
      });
    }

    function checkQuestion(q) {
      var inputs = qa(q, 'input, textarea').filter(function (el) { return el.name && el.name !== 'sf_website'; });
      var answered = false;
      var msg = '';
      inputs.forEach(function (el) {
        if (el.type === 'radio' || el.type === 'checkbox') { if (el.checked) { answered = true; } }
        else if (el.type === 'file') { if (el.files && el.files.length) { answered = true; } }
        else if ((el.value || '').trim() !== '') {
          answered = true;
          var v = el.value.trim();
          if (el.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) { msg = T.email; }
          if (el.type === 'url' && !/^https?:\/\/\S+\.\S+/i.test(v)) { msg = T.url; }
          if (el.type === 'number' && isNaN(Number(v))) { msg = T.number; }
        }
      });
      if (!answered && q.getAttribute('data-required') === '1') { msg = T.required; }
      setError(q, msg);
      return !msg;
    }

    function checkStep(i) {
      var scope = [pages[i]];
      if (i === total - 1 && lastBlock) { scope.push(lastBlock); }
      var ok = true;
      var first = null;
      scope.forEach(function (s) {
        if (!s) { return; }
        qa(s, '.sf-question').forEach(function (q) {
          if (!checkQuestion(q)) {
            ok = false;
            first = first || q;
          }
        });
        qa(s, '.sf-upload-slot.has-error').forEach(function (q) { ok = false; first = first || q; });
      });
      if (first) {
        var el = first.querySelector('input, textarea');
        if (el) { el.focus({ preventScroll: true }); }
        first.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
      return ok;
    }

    function stepMessage(msg) {
      if (!stepError) { return; }
      stepError.textContent = msg;
      stepError.hidden = !msg;
    }

    function recheck(e) {
      dirty = true;
      var q = e.target.closest('.sf-question');
      if (q && q.classList.contains('has-error')) { checkQuestion(q); }
      if (stepError && !stepError.hidden && !form.querySelector('.has-error')) { stepError.hidden = true; }
    }
    form.addEventListener('input', recheck);
    form.addEventListener('change', recheck);

    if (prev) { prev.addEventListener('click', function () { show(current - 1, true); }); }
    if (next) {
      next.addEventListener('click', function () {
        if (!checkStep(current)) { stepMessage(T.requiredStep); return; }
        show(current + 1, true);
      });
    }

    // Arrow keys move between steps when focus is not in a field.
    shell.addEventListener('keydown', function (e) {
      if (/^(INPUT|TEXTAREA|SELECT|BUTTON)$/.test(e.target.tagName) || sent) { return; }
      if (e.key === 'ArrowRight' && next && !next.hidden) { next.click(); e.preventDefault(); }
      else if (e.key === 'ArrowLeft' && prev && !prev.hidden) { prev.click(); e.preventDefault(); }
    });

    // ---- file slots
    qa(form, '[data-sf-drop]').forEach(function (slot) {
      var input = slot.querySelector('input[type=file]');
      var name = slot.querySelector('[data-sf-file-name]');
      var clear = slot.querySelector('[data-sf-file-clear]');
      var max = parseInt((form.querySelector('.sf-upload-section') || {}).getAttribute ? form.querySelector('.sf-upload-section').getAttribute('data-max-mb') : '5', 10) || 5;
      function update() {
        var f = input.files && input.files[0];
        var err = slot.querySelector('[data-sf-error]');
        slot.classList.toggle('has-file', !!f);
        name.textContent = f ? f.name + ' · ' + (f.size >= 1048576 ? (f.size / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(f.size / 1024)) + ' KB') : T.chooseFile;
        clear.hidden = !f;
        var tooBig = f && f.size > max * 1048576;
        slot.classList.toggle('has-error', !!tooBig);
        err.textContent = tooBig ? fmt(T.tooBig, max) : '';
        err.hidden = !tooBig;
      }
      input.addEventListener('change', update);
      clear.addEventListener('click', function () { input.value = ''; update(); input.focus(); });
      ['dragenter', 'dragover'].forEach(function (ev) {
        slot.addEventListener(ev, function (e) { e.preventDefault(); slot.classList.add('is-over'); });
      });
      ['dragleave', 'drop'].forEach(function (ev) {
        slot.addEventListener(ev, function (e) { e.preventDefault(); slot.classList.remove('is-over'); });
      });
      slot.addEventListener('drop', function (e) {
        var files = e.dataTransfer && e.dataTransfer.files;
        if (!files || !files.length) { return; }
        try {
          var dt = new DataTransfer();
          dt.items.add(files[0]);
          input.files = dt.files;
        } catch (err) { return; }
        dirty = true;
        update();
      });
    });

    // ---- sending
    function collect() {
      var out = {};
      qa(form, '[name^="q_"]').forEach(function (el) {
        var m = el.name.match(/^q_(\d+)(\[\])?$/);
        if (!m) { return; }
        var id = m[1];
        if (el.type === 'checkbox') { if (el.checked) { (out[id] = out[id] || []).push(el.value); } }
        else if (el.type === 'radio') { if (el.checked) { out[id] = el.value; } }
        else {
          var v = (el.value || '').trim();
          if (v !== '') { out[id] = v; }
        }
      });
      return out;
    }

    function showResult(ok, heading, text) {
      result.className = 'sf-result' + (ok ? '' : ' error');
      result.innerHTML = '<div class="sf-result-icon">' + (ok ? ICON_CHECK : ICON_X) + '</div><h3>' + esc(heading) + '</h3><p>' + esc(text) + '</p>';
      result.hidden = false;
      result.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function fieldErrors(errors) {
      var firstStep = null;
      Object.keys(errors || {}).forEach(function (id) {
        var q = form.querySelector('.sf-question[data-question-id="' + id + '"]');
        setError(q, errors[id]);
        if (q) {
          var page = q.closest('[data-sf-page]');
          var n = page ? parseInt(page.getAttribute('data-sf-page'), 10) : total - 1;
          firstStep = firstStep === null ? n : Math.min(firstStep, n);
        }
      });
      return firstStep;
    }

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (sent) { return; }
      if (current < total - 1) { if (next) { next.click(); } return; }
      if (!checkStep(current)) { stepMessage(T.requiredSend); return; }
      stepMessage('');
      var fd = new FormData();
      fd.append('form', formId);
      fd.append('answers', JSON.stringify(collect()));
      var email = form.querySelector('[name="sf_email"]');
      fd.append('email', email ? email.value.trim() : '');
      fd.append('sf_website', (form.querySelector('[name="sf_website"]') || {}).value || '');
      qa(form, 'input[type=file]').forEach(function (inp) {
        if (inp.files && inp.files[0]) { fd.append(inp.name, inp.files[0]); }
      });
      sent = true;
      submit.disabled = true;
      submit.textContent = T.submitting;
      result.hidden = true;
      api('POST', '/submit', fd, true).then(function (res) {
        var d = (res && res.data) || {};
        dirty = false;
        if (d.redirect) { window.location.href = d.redirect; return; }
        qa(form, 'input, textarea, button').forEach(function (el) { el.disabled = true; });
        [prev, next, submit].forEach(function (b) { if (b) { b.hidden = true; } });
        steps.forEach(function (s) { s.classList.remove('is-current'); s.classList.add('is-done'); });
        var fill = shell.querySelector('[data-sf-progress-fill]');
        if (fill) { fill.style.width = '100%'; }
        showResult(true, T.submitted, d.message || T.recorded);
      }).catch(function (err) {
        sent = false;
        submit.disabled = false;
        submit.textContent = submitText;
        var step = err.data && err.data.errors ? fieldErrors(err.data.errors) : null;
        if (step !== null && step !== current) { show(step, true); }
        if (!err.status) { err.message = T.network; }
        showResult(false, T.couldNot, err.message || T.tryAgain);
      });
    });

    window.addEventListener('beforeunload', function (e) {
      if (dirty && !sent) {
        e.preventDefault();
        e.returnValue = '';
      }
    });

    show(0, false);
  }

  // ------------------------------------------------------------ lazy Submissions tab

  function loadLazy(el, force) {
    if (!force && (el.getAttribute('data-loaded') === 'true' || el.getAttribute('data-loading') === 'true')) { return; }
    el.setAttribute('data-loading', 'true');
    var form = el.getAttribute('data-sf-tab-form');
    if (force) { el.innerHTML = '<div class="sf-tab-spinner">' + esc(T.loading) + '</div>'; }
    api('GET', '/tab?form=' + encodeURIComponent(form) + '&kind=' + encodeURIComponent(el.getAttribute('data-sf-tab-kind') || 'submissions'))
      .then(function (res) {
        el.innerHTML = res.data.html;
        el.setAttribute('data-loaded', 'true');
        el.removeAttribute('data-loading');
        setBadge(el, res.data.count);
        initAll(el);
      })
      .catch(function (err) {
        el.removeAttribute('data-loading');
        el.innerHTML = '<div class="sf-empty">' + esc(T.loadFail + ' ' + err.message) + '</div>';
      });
  }

  function setBadge(el, count) {
    var panel = el.closest('.sp-tabs-lite-panel');
    var box = panel && panel.closest('.sp-tabs-lite');
    if (!box || count === undefined) { return; }
    var i = parseInt(panel.getAttribute('data-panel-index'), 10);
    var nav = children(box, 'sp-tabs-lite-nav')[0];
    var btn = nav && children(nav, 'sp-tabs-lite-btn')[i];
    var badge = btn && btn.querySelector('.sp-tabs-lite-badge');
    if (badge) { badge.textContent = String(count); }
  }

  // ------------------------------------------------------------ table

  function initTable(wrap) {
    if (wrap.getAttribute('data-sp-init') === '1') { return; }
    wrap.setAttribute('data-sp-init', '1');
    var rows, cols;
    try {
      rows = JSON.parse(wrap.getAttribute('data-rows') || '[]');
      cols = JSON.parse(wrap.getAttribute('data-columns') || '[]');
    } catch (e) { return; }
    var formId = wrap.getAttribute('data-form-id');
    var per = parseInt(wrap.getAttribute('data-per-page'), 10) || 10;
    var state = { q: '', sort: '_ts', dir: 'desc', page: 1, hidden: {} };

    wrap.innerHTML = '<div class="sp-table-toolbar">'
      + '<div class="sp-table-search"><span class="sp-table-search-icon"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></span>'
      + '<input type="search" class="sp-table-search-input" placeholder="' + esc(T.search) + '" aria-label="' + esc(T.search) + '"></div>'
      + '<div class="sp-table-actions">'
      + '<button type="button" class="sp-table-btn" data-t="cols" aria-expanded="false">' + esc(T.columns) + '</button>'
      + '<button type="button" class="sp-table-btn" data-t="export" aria-expanded="false">' + esc(T.exportCsv) + '</button>'
      + '<div class="sp-table-menu" data-menu="cols" hidden></div>'
      + '<div class="sp-table-menu" data-menu="export" hidden>'
      + '<button type="button" data-t="csv">' + esc(T.exportShown) + '</button>'
      + '<a href="' + esc(wrap.getAttribute('data-export-url')) + '">' + esc(T.exportAll) + '</a></div>'
      + '</div></div>'
      + '<div class="sp-table-scroll"><table class="sp-table"><thead></thead><tbody></tbody></table></div>'
      + '<div class="sp-table-pagination"><span class="sp-table-page-info"></span><div class="sp-table-page-buttons"></div></div>';

    var input = wrap.querySelector('.sp-table-search-input');
    var thead = wrap.querySelector('thead');
    var tbody = wrap.querySelector('tbody');
    var info = wrap.querySelector('.sp-table-page-info');
    var pager = wrap.querySelector('.sp-table-page-buttons');
    var colMenu = wrap.querySelector('[data-menu="cols"]');

    colMenu.innerHTML = cols.map(function (c, i) {
      return '<label><input type="checkbox" data-col="' + i + '" checked> ' + esc(c.label) + '</label>';
    }).join('');

    function visibleCols() { return cols.filter(function (c, i) { return !state.hidden[i]; }); }

    function filtered() {
      var q = state.q.toLowerCase();
      var list = rows.filter(function (r) {
        if (!q) { return true; }
        return cols.some(function (c) { return c.kind !== 'files' && String(r[c.field] || '').toLowerCase().indexOf(q) > -1; });
      });
      var key = state.sort;
      list.sort(function (a, b) {
        var x = a[key] == null ? '' : String(a[key]);
        var y = b[key] == null ? '' : String(b[key]);
        var nx = parseFloat(x), ny = parseFloat(y);
        var r = (!isNaN(nx) && !isNaN(ny) && /^-?[\d.]+$/.test(x) && /^-?[\d.]+$/.test(y)) ? nx - ny : x.localeCompare(y, undefined, { numeric: true, sensitivity: 'base' });
        return state.dir === 'asc' ? r : -r;
      });
      return list;
    }

    function cell(r, c) {
      if (c.kind === 'files') {
        var files = [];
        try { files = JSON.parse(r._files || '[]'); } catch (e) { files = []; }
        return '<td class="sp-table-files">' + files.map(function (f) {
          return '<a href="' + esc(f.url) + '">' + esc(f.label) + '</a>';
        }).join('') + '</td>';
      }
      return '<td>' + esc(r[c.field]) + '</td>';
    }

    function render() {
      var list = filtered();
      var pages = Math.max(1, Math.ceil(list.length / per));
      state.page = Math.min(state.page, pages);
      var start = (state.page - 1) * per;
      var slice = list.slice(start, start + per);
      var vc = visibleCols();
      thead.innerHTML = '<tr>' + vc.map(function (c) {
        var key = c.sortKey || c.field;
        var sorted = state.sort === key ? (state.dir === 'asc' ? 'ascending' : 'descending') : 'none';
        return '<th aria-sort="' + sorted + '">' + (c.sortable === false ? esc(c.label) : '<button type="button" data-sort="' + esc(key) + '" title="' + esc(c.label) + '">' + esc(c.label) + ICON_SORT + '</button>') + '</th>';
      }).join('') + '<th class="sp-table-actions-col">' + esc(T.actions) + '</th></tr>';
      tbody.innerHTML = slice.length ? slice.map(function (r) {
        return '<tr>' + vc.map(function (c) { return cell(r, c); }).join('')
          + '<td class="sp-table-actions-col"><div class="sp-table-row-actions">'
          + '<button type="button" class="sp-table-row-action" data-act="view" data-id="' + esc(r.id) + '">' + esc(T.view) + '</button>'
          + '<button type="button" class="sp-table-row-action sp-table-row-action--danger" data-act="remove" data-id="' + esc(r.id) + '">' + esc(T.remove) + '</button>'
          + '</div></td></tr>';
      }).join('') : '<tr><td class="sp-table-empty" colspan="' + (vc.length + 1) + '">' + esc(T.noMatch) + '</td></tr>';
      info.textContent = list.length ? fmt(T.showing, start + 1, start + slice.length, list.length) : T.none;
      var btns = '<button type="button" class="sp-table-page-btn" data-page="' + (state.page - 1) + '"' + (state.page <= 1 ? ' disabled' : '') + ' aria-label="' + esc(T.prev) + '">&lsaquo;</button>';
      for (var p = 1; p <= pages; p++) {
        if (pages > 7 && p > 2 && p < pages - 1 && Math.abs(p - state.page) > 1) {
          if (p === 3 || p === pages - 2) { btns += '<span class="sp-table-page-info">…</span>'; }
          continue;
        }
        btns += '<button type="button" class="sp-table-page-btn' + (p === state.page ? ' active' : '') + '" data-page="' + p + '"' + (p === state.page ? ' aria-current="page"' : '') + '>' + p + '</button>';
      }
      btns += '<button type="button" class="sp-table-page-btn" data-page="' + (state.page + 1) + '"' + (state.page >= pages ? ' disabled' : '') + ' aria-label="' + esc(T.nextPage) + '">&rsaquo;</button>';
      pager.innerHTML = btns;
    }

    function csv() {
      var vc = visibleCols().filter(function (c) { return c.kind !== 'files'; });
      var lines = [vc.map(function (c) { return c.label; })].concat(filtered().map(function (r) {
        return vc.map(function (c) { return r[c.field] == null ? '' : String(r[c.field]); });
      }));
      var text = '﻿' + lines.map(function (line) {
        return line.map(function (v) {
          if (/^[=+\-@]/.test(v)) { v = "'" + v; }
          return /[",\n\r]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v;
        }).join(',');
      }).join('\r\n');
      var a = document.createElement('a');
      a.href = URL.createObjectURL(new Blob([text], { type: 'text/csv;charset=utf-8' }));
      a.download = (wrap.getAttribute('data-export-name') || 'submissions') + '.csv';
      document.body.appendChild(a);
      a.click();
      setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 500);
    }

    function closeMenus(except) {
      qa(wrap, '[data-menu]').forEach(function (m) {
        if (m !== except) {
          m.hidden = true;
          var b = wrap.querySelector('[data-t="' + m.getAttribute('data-menu') + '"]');
          if (b) { b.setAttribute('aria-expanded', 'false'); }
        }
      });
    }

    input.addEventListener('input', function () { state.q = input.value.trim(); state.page = 1; render(); });
    colMenu.addEventListener('change', function (e) {
      var i = e.target.getAttribute('data-col');
      if (i !== null) { state.hidden[i] = !e.target.checked; render(); }
    });
    document.addEventListener('click', function (e) { if (!wrap.contains(e.target)) { closeMenus(null); } });
    wrap.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeMenus(null); } });

    wrap.addEventListener('click', function (e) {
      var t = e.target.closest('[data-t], [data-sort], [data-page], [data-act]');
      if (!t || t.disabled) { return; }
      if (t.hasAttribute('data-t')) {
        var which = t.getAttribute('data-t');
        if (which === 'csv') { closeMenus(null); csv(); return; }
        var menu = wrap.querySelector('[data-menu="' + which + '"]');
        if (menu) {
          closeMenus(menu);
          menu.hidden = !menu.hidden;
          t.setAttribute('aria-expanded', menu.hidden ? 'false' : 'true');
        }
        return;
      }
      if (t.hasAttribute('data-sort')) {
        var key = t.getAttribute('data-sort');
        state.dir = state.sort === key && state.dir === 'asc' ? 'desc' : 'asc';
        state.sort = key;
        render();
        return;
      }
      if (t.hasAttribute('data-page')) {
        state.page = parseInt(t.getAttribute('data-page'), 10) || 1;
        render();
        return;
      }
      var id = t.getAttribute('data-id');
      if (t.getAttribute('data-act') === 'view') {
        api('GET', '/submission?form=' + encodeURIComponent(formId) + '&submission_id=' + encodeURIComponent(id)).then(function (res) {
          modal({ title: T.response, html: res.data.html, cancel: T.close, wide: true });
        }).catch(function (err) { modal({ title: T.response, body: err.message, cancel: T.close }); });
        return;
      }
      modal({ title: T.removeTitle, body: T.removeBody, ok: T.remove, danger: true }).then(function (ok) {
        if (!ok) { return; }
        t.classList.add('loading');
        api('POST', '/submissions/remove', { form: parseInt(formId, 10), submission_id: id }).then(function () {
          var lazy = wrap.closest('.sf-tab-lazy');
          if (lazy) { loadLazy(lazy, true); return; }
          rows = rows.filter(function (r) { return r.id !== id; });
          render();
        }).catch(function (err) {
          t.classList.remove('loading');
          modal({ title: T.removeFail, body: err.message, cancel: T.close });
        });
      });
    });

    render();
  }

  // ------------------------------------------------------------ Google Forms

  function initGoogle(box) {
    var btn = box.querySelector('[data-sf-google-build]');
    if (!btn || btn.getAttribute('data-sp-init') === '1') { return; }
    btn.setAttribute('data-sp-init', '1');
    var out = box.querySelector('[data-sf-google-result]');
    btn.addEventListener('click', function () {
      btn.disabled = true;
      btn.textContent = T.building;
      api('POST', '/google/build', { form: parseInt(box.getAttribute('data-form-id'), 10) }).then(function (res) {
        var d = res.data || {};
        var h = '<strong>' + esc(T.googleOk) + '</strong><br><a href="' + esc(d.edit_uri) + '" target="_blank" rel="noopener">' + esc(T.googleEdit) + '</a> &middot; <a href="' + esc(d.responder_uri) + '" target="_blank" rel="noopener">' + esc(T.googleLive) + '</a>';
        if (d.skipped && d.skipped.length) {
          h += '<br><br><strong>' + esc(T.googleSkipped) + '</strong><ul>' + d.skipped.map(function (s) { return '<li>' + esc(s) + '</li>'; }).join('') + '</ul>';
        }
        out.className = 'sf-google-result ok';
        out.innerHTML = h;
        out.hidden = false;
        btn.disabled = false;
        btn.textContent = T.buildAnother;
      }).catch(function (err) {
        out.className = 'sf-google-result err';
        out.innerHTML = '<strong>' + esc(T.googleRefused) + '</strong><br>' + esc(err.message);
        out.hidden = false;
        btn.disabled = false;
        btn.textContent = T.createGoogle;
      });
    });
  }

  // ------------------------------------------------------------ forms index

  function initIndex(box) {
    if (box.getAttribute('data-sp-init') === '1') { return; }
    box.setAttribute('data-sp-init', '1');
    var forms = [];
    try { forms = JSON.parse(box.getAttribute('data-forms') || '[]'); } catch (e) { forms = []; }
    var per = parseInt(box.getAttribute('data-per-page'), 10) || 12;
    var list = box.querySelector('[data-sf-index-list]');
    var pagesEl = box.querySelector('[data-sf-index-pages]');
    var search = box.querySelector('[data-sf-index-search]');
    var view = box.getAttribute('data-view') || 'grid';
    try { view = localStorage.getItem('sepo_forms_view') || view; } catch (e) { /* stays as set */ }
    var page = 1;
    var q = '';

    function card(f) {
      var badges = '<span class="form-badge ' + (f.closed ? 'closed">' + esc(T.closed) : 'open">' + esc(T.open)) + '</span>'
        + (f.private ? '<span class="form-badge private">' + esc(T.private) + '</span>' : '')
        + (f.login ? '<span class="form-badge login">' + esc(T.signIn) + '</span>' : '');
      return '<article class="form-card"><div class="form-card-badges">' + badges + '</div>'
        + '<div class="form-card-main"><h3><a href="' + esc(f.url) + '">' + esc(f.title) + '</a></h3>'
        + (f.desc ? '<p class="form-card-desc">' + esc(f.desc) + '</p>' : '') + '</div>'
        + '<div class="form-card-foot"><a class="form-card-open" href="' + esc(f.url) + '">' + esc(T.openForm) + ' &rarr;</a>'
        + '<span class="form-card-count">' + esc(fmt(T.questionsN, f.count)) + '</span></div></article>';
    }

    function render() {
      var needle = q.toLowerCase();
      var shown = forms.filter(function (f) { return !needle || (f.title + ' ' + f.desc).toLowerCase().indexOf(needle) > -1; });
      var pages = Math.max(1, Math.ceil(shown.length / per));
      page = Math.min(page, pages);
      list.className = 'forms-wrapper component-view-' + view;
      list.innerHTML = shown.length ? shown.slice((page - 1) * per, page * per).map(card).join('') : '<div class="sf-empty">' + esc(T.noForms) + '</div>';
      qa(box, '[data-sf-view]').forEach(function (b) { b.setAttribute('aria-pressed', b.getAttribute('data-sf-view') === view ? 'true' : 'false'); });
      if (pages <= 1) { pagesEl.innerHTML = ''; return; }
      var h = '<span class="sp-table-page-info">' + esc(fmt(T.showing, (page - 1) * per + 1, Math.min(page * per, shown.length), shown.length)) + '</span><div class="sp-table-page-buttons">';
      for (var p = 1; p <= pages; p++) {
        h += '<button type="button" class="sp-table-page-btn' + (p === page ? ' active' : '') + '" data-page="' + p + '">' + p + '</button>';
      }
      pagesEl.innerHTML = h + '</div>';
    }

    if (search) { search.addEventListener('input', function () { q = search.value.trim(); page = 1; render(); }); }
    box.addEventListener('click', function (e) {
      var v = e.target.closest('[data-sf-view]');
      if (v) {
        view = v.getAttribute('data-sf-view');
        try { localStorage.setItem('sepo_forms_view', view); } catch (err) { /* remembered for this page only */ }
        render();
      }
      var p = e.target.closest('[data-page]');
      if (p) {
        page = parseInt(p.getAttribute('data-page'), 10) || 1;
        render();
        box.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
    render();
  }

  // ------------------------------------------------------------ start

  function initAll(root) {
    initTabs(root);
    qa(root, '.sf-shell[data-form-id]').forEach(initForm);
    qa(root, '[data-sp-table]').forEach(initTable);
    qa(root, '.sf-google').forEach(initGoogle);
    qa(root, '[data-sf-index]').forEach(initIndex);
    qa(root, '.sf-tab-lazy').forEach(function (el) {
      if (el.offsetParent !== null) { loadLazy(el); }
    });
  }

  function start() {
    paintMode();
    initAll(document);
    var mo = new MutationObserver(paintMode);
    mo.observe(document.documentElement, { attributes: true, attributeFilter: ['class', 'data-theme', 'data-bs-theme', 'data-color-scheme', 'data-mode', 'data-color-mode', 'style'] });
    mo.observe(document.body, { attributes: true, attributeFilter: ['class', 'data-theme', 'data-bs-theme', 'data-color-scheme', 'data-mode', 'data-color-mode', 'style'] });
    if (window.matchMedia) {
      var mq = window.matchMedia('(prefers-color-scheme: dark)');
      if (mq.addEventListener) { mq.addEventListener('change', function () { setTimeout(paintMode, 50); }); }
    }
  }

  window.SepoForms = { init: initAll };

  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', start); }
  else { start(); }
})();
