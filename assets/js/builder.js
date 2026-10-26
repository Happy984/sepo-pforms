/* Sepo Forms — the question builder on the form editing screen. */
(function () {
  'use strict';

  var B = window.SepoFormsBuilder;
  var root = document.getElementById('spf-builder');
  var store = document.getElementById('spf-sections');
  if (!B || !root || !store) { return; }
  var T = B.i18n;
  var CHOICE = { multiplechoice: 1, multipleselect: 1 };
  var sections = clone(B.sections || []);
  var dragging = null;

  function clone(o) { return JSON.parse(JSON.stringify(o)); }
  function esc(s) {
    return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }
  function fmt(s, n) { return String(s).replace('%d', n); }
  function blankQuestion() { return { id: 0, question: '', type: 'text', required: false, options: [], image: '', help: '', placeholder: '' }; }
  function count() { return sections.reduce(function (n, s) { return n + s.questions.length; }, 0); }

  function sync() {
    store.value = JSON.stringify(sections);
    var c = root.querySelector('[data-b-count]');
    if (c) { c.textContent = fmt(T.qCount, count()); }
  }

  // ------------------------------------------------------------ drawing

  var ICON = {
    grip: '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><circle cx="9" cy="6" r="1.6"/><circle cx="15" cy="6" r="1.6"/><circle cx="9" cy="12" r="1.6"/><circle cx="15" cy="12" r="1.6"/><circle cx="9" cy="18" r="1.6"/><circle cx="15" cy="18" r="1.6"/></svg>',
    up: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><polyline points="18 15 12 9 6 15"/></svg>',
    down: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>',
    copy: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>',
    trash: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>',
    more: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-2.82 1.16V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-2.82-1.16l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 3.09 14H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1.16-2.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9.9 3.09V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 2.82 1.16l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 20.91 10H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>'
  };

  function typeOptions(sel) {
    return Object.keys(B.types).map(function (k) {
      return '<option value="' + k + '"' + (k === sel ? ' selected' : '') + '>' + esc(B.types[k]) + '</option>';
    }).join('');
  }

  function questionHtml(q, si, qi, number) {
    var choice = !!CHOICE[q.type];
    var open = q._open ? ' is-open' : '';
    return '<div class="spf-q' + open + '" data-s="' + si + '" data-q="' + qi + '">'
      + '<div class="spf-q-row">'
      + '<span class="spf-grip" title="' + esc(T.moveUp + ' / ' + T.moveDown) + '" aria-hidden="true">' + ICON.grip + '</span>'
      + '<span class="spf-q-num">' + number + '</span>'
      + '<input type="text" class="spf-q-text" data-f="question" value="' + esc(q.question) + '" placeholder="' + esc(T.questionPh) + '" aria-label="' + esc(T.question + ' ' + number) + '">'
      + '<select data-f="type" aria-label="' + esc(T.type) + '">' + typeOptions(q.type) + '</select>'
      + '<label class="spf-req"><input type="checkbox" data-f="required"' + (q.required ? ' checked' : '') + '> ' + esc(T.required) + '</label>'
      + '<span class="spf-q-actions">'
      + '<button type="button" class="spf-icon" data-a="more" aria-expanded="' + (q._open ? 'true' : 'false') + '" title="' + esc(T.more) + '" aria-label="' + esc(T.more) + '">' + ICON.more + '</button>'
      + '<button type="button" class="spf-icon" data-a="up" title="' + esc(T.moveUp) + '" aria-label="' + esc(T.moveUp) + '">' + ICON.up + '</button>'
      + '<button type="button" class="spf-icon" data-a="down" title="' + esc(T.moveDown) + '" aria-label="' + esc(T.moveDown) + '">' + ICON.down + '</button>'
      + '<button type="button" class="spf-icon" data-a="dup" title="' + esc(T.duplicate) + '" aria-label="' + esc(T.duplicate) + '">' + ICON.copy + '</button>'
      + '<button type="button" class="spf-icon spf-icon--danger" data-a="del" title="' + esc(T.delete) + '" aria-label="' + esc(T.delete) + '">' + ICON.trash + '</button>'
      + '</span></div>'
      + (choice ? '<div class="spf-q-options"><label>' + esc(T.options) + '<textarea rows="' + Math.max(3, Math.min(8, q.options.length + 1)) + '" data-f="options">' + esc(q.options.join('\n')) + '</textarea></label></div>' : '')
      + '<div class="spf-q-more"' + (q._open ? '' : ' hidden') + '>'
      + '<label>' + esc(T.help) + '<input type="text" data-f="help" value="' + esc(q.help) + '"></label>'
      + (choice || q.type === 'date' ? '' : '<label>' + esc(T.placeholder) + '<input type="text" data-f="placeholder" value="' + esc(q.placeholder) + '"></label>')
      + '<div class="spf-q-image"><span>' + esc(T.image) + '</span>'
      + (q.image ? '<img src="' + esc(q.image) + '" alt=""><button type="button" class="button-link" data-a="noimg">' + esc(T.removeImage) + '</button>' : '')
      + '<button type="button" class="button" data-a="img">' + esc(T.chooseImage) + '</button></div>'
      + '</div></div>';
  }

  function render() {
    var n = 0;
    var html = '<div class="spf-toolbar">';
    if (B.templates && B.templates.length && count() === 0) {
      html += '<button type="button" class="button" data-a="templates">' + esc(T.templates) + '</button>';
    }
    html += '<button type="button" class="button" data-a="import">' + esc(T.import) + '</button>'
      + '<button type="button" class="button" data-a="copytext">' + esc(T.copyText) + '</button>'
      + '<span class="spf-count" data-b-count>' + esc(fmt(T.qCount, count())) + '</span></div>';

    if (!sections.length) {
      html += '<div class="spf-empty">' + esc(T.empty) + '</div>';
    }
    sections.forEach(function (s, si) {
      html += '<section class="spf-section" data-s="' + si + '">'
        + '<div class="spf-section-head"><span class="spf-badge">' + esc(T.section) + ' ' + (si + 1) + '</span>'
        + '<input type="text" class="spf-section-title" data-sf="title" value="' + esc(s.title) + '" placeholder="' + esc(T.sectionTitle) + '" aria-label="' + esc(T.sectionTitle) + '">'
        + '<span class="spf-q-actions">'
        + '<button type="button" class="spf-icon" data-a="sup" title="' + esc(T.moveUp) + '" aria-label="' + esc(T.moveUp) + '">' + ICON.up + '</button>'
        + '<button type="button" class="spf-icon" data-a="sdown" title="' + esc(T.moveDown) + '" aria-label="' + esc(T.moveDown) + '">' + ICON.down + '</button>'
        + '<button type="button" class="spf-icon spf-icon--danger" data-a="sdel" title="' + esc(T.deleteSection) + '" aria-label="' + esc(T.deleteSection) + '">' + ICON.trash + '</button>'
        + '</span></div>'
        + (si === 0 && !s.title ? '<p class="spf-hint">' + esc(T.firstHint) + '</p>' : '')
        + '<input type="text" class="spf-section-help" data-sf="help" value="' + esc(s.help) + '" placeholder="' + esc(T.sectionHelp) + '" aria-label="' + esc(T.sectionHelp) + '">'
        + '<div class="spf-qs" data-s="' + si + '">';
      s.questions.forEach(function (q, qi) { html += questionHtml(q, si, qi, ++n); });
      html += '</div><button type="button" class="spf-add" data-a="addq">+ ' + esc(T.addQuestion) + '</button></section>';
    });
    html += '<button type="button" class="button spf-add-section" data-a="adds">+ ' + esc(T.addSection) + '</button>'
      + '<p class="description spf-note">' + esc(T.answersKept) + '</p>';
    root.innerHTML = html;
    sync();
  }

  function focusQuestion(si, qi) {
    var el = root.querySelector('.spf-q[data-s="' + si + '"][data-q="' + qi + '"] .spf-q-text');
    if (el) { el.focus(); }
  }

  // ------------------------------------------------------------ editing

  root.addEventListener('input', function (e) {
    var t = e.target;
    var qEl = t.closest('.spf-q');
    var sEl = t.closest('.spf-section');
    if (qEl && t.hasAttribute('data-f')) {
      var q = sections[+qEl.getAttribute('data-s')].questions[+qEl.getAttribute('data-q')];
      var f = t.getAttribute('data-f');
      if (f === 'required') { q.required = t.checked; }
      else if (f === 'options') { q.options = t.value.split('\n').map(function (s) { return s.trim(); }).filter(Boolean); }
      else if (f !== 'type') { q[f] = t.value; }
      sync();
    } else if (sEl && t.hasAttribute('data-sf')) {
      sections[+sEl.getAttribute('data-s')][t.getAttribute('data-sf')] = t.value;
      sync();
    }
  });
  root.addEventListener('change', function (e) {
    var t = e.target;
    if (t.getAttribute('data-f') === 'type') {
      var qEl = t.closest('.spf-q');
      var si = +qEl.getAttribute('data-s'), qi = +qEl.getAttribute('data-q');
      sections[si].questions[qi].type = t.value;
      render();
      var box = root.querySelector('.spf-q[data-s="' + si + '"][data-q="' + qi + '"] textarea[data-f="options"]');
      (box || root.querySelector('.spf-q[data-s="' + si + '"][data-q="' + qi + '"] select')).focus();
    } else if (t.getAttribute('data-f') === 'required') {
      sync();
    }
  });

  root.addEventListener('click', function (e) {
    var b = e.target.closest('[data-a]');
    if (!b) { return; }
    var a = b.getAttribute('data-a');
    var qEl = b.closest('.spf-q');
    var sEl = b.closest('.spf-section');
    var si = sEl ? +sEl.getAttribute('data-s') : -1;
    var qi = qEl ? +qEl.getAttribute('data-q') : -1;
    var list = si > -1 ? sections[si].questions : null;

    switch (a) {
      case 'addq':
        list.push(blankQuestion());
        render();
        focusQuestion(si, list.length - 1);
        break;
      case 'adds':
        sections.push({ title: '', help: '', questions: [blankQuestion()] });
        render();
        var t = root.querySelector('.spf-section[data-s="' + (sections.length - 1) + '"] .spf-section-title');
        if (t) { t.focus(); }
        break;
      case 'more':
        list[qi]._open = !list[qi]._open;
        render();
        break;
      case 'up':
      case 'down':
        move(si, qi, a === 'up' ? -1 : 1);
        break;
      case 'dup':
        var copy = clone(list[qi]);
        copy.id = 0;
        list.splice(qi + 1, 0, copy);
        render();
        focusQuestion(si, qi + 1);
        break;
      case 'del':
        list.splice(qi, 1);
        render();
        break;
      case 'sup':
      case 'sdown':
        var to = si + (a === 'sup' ? -1 : 1);
        if (to < 0 || to >= sections.length) { return; }
        var s = sections.splice(si, 1)[0];
        sections.splice(to, 0, s);
        render();
        break;
      case 'sdel':
        if (!sections[si].questions.length) {
          sections.splice(si, 1);
          render();
          return;
        }
        dialog(T.deleteSection, '<p>' + esc(T.confirmDelete) + '</p>', T.delete, function (wrap, close) {
          sections.splice(si, 1);
          close();
          render();
        });
        break;
      case 'img':
        pickImage(list[qi]);
        break;
      case 'noimg':
        list[qi].image = '';
        render();
        break;
      case 'templates':
        showTemplates();
        break;
      case 'import':
        showImport();
        break;
      case 'copytext':
        copyText(b);
        break;
    }
  });

  /** Move a question one place, crossing into the next or previous section at the ends. */
  function move(si, qi, dir) {
    var list = sections[si].questions;
    var to = qi + dir;
    var q = list[qi];
    if (to >= 0 && to < list.length) {
      list.splice(qi, 1);
      list.splice(to, 0, q);
      render();
      focusAction(si, to, dir < 0 ? 'up' : 'down');
      return;
    }
    var ns = si + dir;
    if (ns < 0 || ns >= sections.length) { return; }
    list.splice(qi, 1);
    if (dir < 0) { sections[ns].questions.push(q); } else { sections[ns].questions.unshift(q); }
    render();
    focusAction(ns, dir < 0 ? sections[ns].questions.length - 1 : 0, dir < 0 ? 'up' : 'down');
  }
  function focusAction(si, qi, a) {
    var el = root.querySelector('.spf-q[data-s="' + si + '"][data-q="' + qi + '"] [data-a="' + a + '"]');
    if (el) { el.focus(); }
  }

  function pickImage(q) {
    if (!window.wp || !wp.media) { return; }
    var frame = wp.media({ title: T.chooseImage, library: { type: 'image' }, multiple: false });
    frame.on('select', function () {
      var att = frame.state().get('selection').first().toJSON();
      q.image = (att.sizes && att.sizes.medium_large ? att.sizes.medium_large.url : att.url) || '';
      q._open = true;
      render();
    });
    frame.open();
  }

  // ------------------------------------------------------------ drag and drop

  var line = document.createElement('div');
  line.className = 'spf-drop-line';

  // Pointer events cover mouse, touch and pen alike; the grip has touch-action: none
  // so a finger on it moves the question instead of scrolling the page.
  function placeLine(x, y) {
    var under = document.elementFromPoint(x, y);
    var section = under && under.closest('.spf-section');
    var list = under && (under.closest('.spf-qs') || (section && section.querySelector('.spf-qs')));
    if (!list || !root.contains(list)) { return; }
    var items = Array.prototype.filter.call(list.children, function (c) { return c.classList.contains('spf-q') && c !== dragging.el; });
    var before = null;
    for (var i = 0; i < items.length; i++) {
      var r = items[i].getBoundingClientRect();
      if (y < r.top + r.height / 2) { before = items[i]; break; }
    }
    if (before) { list.insertBefore(line, before); } else { list.appendChild(line); }
  }
  function autoScroll(y) {
    var edge = 60;
    if (y < edge) { window.scrollBy(0, -12); } else if (y > window.innerHeight - edge) { window.scrollBy(0, 12); }
  }
  function endDrag(commit) {
    if (!dragging) { return; }
    var d = dragging;
    dragging = null;
    d.el.classList.remove('is-dragging');
    document.body.classList.remove('spf-is-dragging');
    if (!commit || !line.parentNode) { line.remove(); return; }
    var list = line.parentNode;
    var ts = +list.getAttribute('data-s');
    var idx = 0;
    Array.prototype.some.call(list.children, function (c) {
      if (c === line) { return true; }
      if (c.classList.contains('spf-q') && c !== d.el) { idx++; }
      return false;
    });
    line.remove();
    if (ts === d.s && idx === d.q) { return; }
    var q = sections[d.s].questions.splice(d.q, 1)[0];
    sections[ts].questions.splice(idx, 0, q);
    render();
    var moved = root.querySelector('.spf-q[data-s="' + ts + '"][data-q="' + idx + '"]');
    if (moved) {
      moved.classList.add('is-dropped');
      setTimeout(function () { moved.classList.remove('is-dropped'); }, 700);
    }
  }

  root.addEventListener('pointerdown', function (e) {
    var grip = e.target.closest && e.target.closest('.spf-grip');
    if (!grip || (e.pointerType === 'mouse' && e.button !== 0)) { return; }
    e.preventDefault();
    var qEl = grip.closest('.spf-q');
    dragging = { s: +qEl.getAttribute('data-s'), q: +qEl.getAttribute('data-q'), el: qEl, id: e.pointerId, started: false, y0: e.clientY };
    try { grip.setPointerCapture(e.pointerId); } catch (err) { /* capture is optional */ }
  });
  root.addEventListener('pointermove', function (e) {
    if (!dragging || e.pointerId !== dragging.id) { return; }
    if (!dragging.started) {
      if (Math.abs(e.clientY - dragging.y0) < 4) { return; }
      dragging.started = true;
      dragging.el.classList.add('is-dragging');
      document.body.classList.add('spf-is-dragging');
    }
    e.preventDefault();
    placeLine(e.clientX, e.clientY);
    autoScroll(e.clientY);
  });
  root.addEventListener('pointerup', function (e) {
    if (dragging && e.pointerId === dragging.id) { endDrag(dragging.started); }
  });
  root.addEventListener('pointercancel', function () { endDrag(false); });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && dragging) { endDrag(false); }
  });

  // ------------------------------------------------------------ templates and text

  function dialog(title, body, okLabel, onOk) {
    var wrap = document.createElement('div');
    wrap.className = 'spf-modal';
    wrap.innerHTML = '<div class="spf-modal-card" role="dialog" aria-modal="true" aria-label="' + esc(title) + '"><h2>' + esc(title) + '</h2><div class="spf-modal-body">' + body + '</div>'
      + '<div class="spf-modal-actions"><button type="button" class="button" data-m="cancel">' + esc(T.cancel) + '</button>'
      + (okLabel ? '<button type="button" class="button button-primary" data-m="ok">' + esc(okLabel) + '</button>' : '') + '</div></div>';
    document.body.appendChild(wrap);
    function close() { wrap.remove(); document.removeEventListener('keydown', key); }
    function key(e) { if (e.key === 'Escape') { close(); } }
    document.addEventListener('keydown', key);
    wrap.addEventListener('click', function (e) {
      var m = e.target.closest('[data-m]');
      if (e.target === wrap || (m && m.getAttribute('data-m') === 'cancel')) { close(); return; }
      if (m && m.getAttribute('data-m') === 'ok') { onOk(wrap, close); }
    });
    var first = wrap.querySelector('textarea, button[data-tpl], [data-m="ok"]');
    if (first) { first.focus(); }
    return wrap;
  }

  function apply(form) {
    sections = clone(form.sections || []);
    var title = document.getElementById('title');
    if (title && !title.value && form.title) {
      title.value = form.title;
      var label = document.getElementById('title-prompt-text');
      if (label) { label.classList.add('screen-reader-text'); }
    }
    var ex = document.getElementById('spf-excerpt');
    if (ex && !ex.value && form.description) { ex.value = form.description; }
    var s = form.settings || {};
    Object.keys(s).forEach(function (k) {
      var el = document.querySelector('[name="spf_settings[' + k + ']"]');
      if (!el) { return; }
      if (el.type === 'checkbox') {
        el.checked = k === 'visibility' ? s[k] === 'private' : !!s[k];
        el.dispatchEvent(new Event('change', { bubbles: true }));
      } else {
        el.value = s[k];
      }
    });
    var topics = document.querySelector('[name="spf_topics"]');
    if (topics && s.uploadTopics) {
      topics.value = s.uploadTopics.map(function (t) { return t.label + (t.category ? ' | ' + t.category : ''); }).join('\n');
    }
    B.templates = [];
    render();
  }

  function showTemplates() {
    var body = '<div class="spf-templates">' + B.templates.map(function (t, i) {
      var n = t.form.sections.reduce(function (c, s) { return c + s.questions.length; }, 0);
      return '<button type="button" class="spf-template" data-tpl="' + i + '"><strong>' + esc(t.label) + '</strong><span>' + esc(t.description) + '</span><em>' + esc(fmt(T.qCount, n)) + '</em></button>';
    }).join('') + '</div>';
    var d = dialog(T.templates, body, '', function () {});
    d.addEventListener('click', function (e) {
      var b = e.target.closest('[data-tpl]');
      if (!b) { return; }
      apply(B.templates[+b.getAttribute('data-tpl')].form);
      d.remove();
    });
  }

  function showImport() {
    dialog(T.importTitle, '<p class="description">' + esc(T.importHelp) + '</p><textarea class="widefat code" rows="14" data-import></textarea><p class="spf-import-msg" hidden></p>', T.importGo, function (wrap, close) {
      var text = wrap.querySelector('[data-import]').value;
      var btn = wrap.querySelector('[data-m="ok"]');
      btn.disabled = true;
      fetch(B.api + '/parse', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': B.nonce },
        body: JSON.stringify({ text: text })
      }).then(function (r) { return r.json(); }).then(function (res) {
        var form = res && res.data;
        if (!form || !form.sections || !form.sections.length) {
          var m = wrap.querySelector('.spf-import-msg');
          m.textContent = T.nothing;
          m.hidden = false;
          btn.disabled = false;
          return;
        }
        apply(form);
        close();
        notice(T.importedNote);
      }).catch(function () { btn.disabled = false; });
    });
  }

  /** The builder's questions written in the text syntax (settings come from the server's last save). */
  function asText() {
    var lines = [];
    var head = (B.text || '').split('\n');
    head.some(function (l) {
      if (/^(group|question)/i.test(l) || l === '') { return l === '' && lines.length > 0; }
      lines.push(l);
      return false;
    });
    if (!lines.length) {
      var t = document.getElementById('title');
      lines.push('title: ' + (t ? t.value : ''));
    }
    lines.push('');
    var names = { longtext: 'longText', multiplechoice: 'multipleChoice', multipleselect: 'multipleSelect' };
    var next = 1;
    sections.forEach(function (s) { s.questions.forEach(function (q) { next = Math.max(next, (q.id || 0) + 1); }); });
    sections.forEach(function (s, i) {
      if (s.title || i > 0) { lines.push('group' + (i + 1) + ': ' + (s.title || T.section + ' ' + (i + 1))); }
      if (s.help) { lines.push('groupHelp' + (i + 1) + ': ' + s.help); }
      s.questions.forEach(function (q) {
        var id = q.id || next++;
        lines.push('question' + id + ': ' + q.question);
        if (q.type !== 'text') { lines.push('questionType' + id + ': ' + (names[q.type] || q.type)); }
        if (q.required) { lines.push('questionRequired' + id + ': true'); }
        if (q.options && q.options.length && CHOICE[q.type]) { lines.push('questionOptions' + id + ': ' + q.options.join(' | ')); }
        if (q.help) { lines.push('questionHelp' + id + ': ' + q.help); }
        if (q.placeholder) { lines.push('questionPlaceholder' + id + ': ' + q.placeholder); }
        if (q.image) { lines.push('questionImage' + id + ': ' + q.image); }
      });
      lines.push('');
    });
    return lines.join('\n');
  }

  function copyText(btn) {
    var text = asText();
    var done = function () {
      var old = btn.textContent;
      btn.textContent = T.copied;
      setTimeout(function () { btn.textContent = old; }, 1400);
    };
    if (navigator.clipboard) { navigator.clipboard.writeText(text).then(done, function () { dialog(T.copyText, '<textarea class="widefat code" rows="14" readonly>' + esc(text) + '</textarea>', '', function () {}); }); }
    else { dialog(T.copyText, '<textarea class="widefat code" rows="14" readonly>' + esc(text) + '</textarea>', '', function () {}); }
  }

  function notice(msg) {
    var n = document.createElement('div');
    n.className = 'spf-inline-notice';
    n.setAttribute('role', 'status');
    n.textContent = msg;
    root.insertBefore(n, root.firstChild.nextSibling);
    setTimeout(function () { n.remove(); }, 6000);
  }

  // ------------------------------------------------------------ the other boxes

  document.addEventListener('change', function (e) {
    var id = e.target.getAttribute && e.target.getAttribute('data-spf-toggle');
    if (id) {
      var box = document.getElementById(id);
      if (box) { box.hidden = !e.target.checked; }
    }
  });
  document.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('[data-spf-copy]');
    if (!b) { return; }
    var text = b.getAttribute('data-spf-copy');
    if (navigator.clipboard) {
      navigator.clipboard.writeText(text).then(function () {
        var old = b.textContent;
        b.textContent = T.copied;
        setTimeout(function () { b.textContent = old; }, 1400);
      });
    }
  });

  render();
  if (B.templates && B.templates.length && !count()) {
    showTemplates();
  }
})();
