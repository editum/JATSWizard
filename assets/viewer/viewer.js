(function () {
  'use strict';
  var doc = document;
  var lang = (doc.documentElement.getAttribute('lang') || 'es').toLowerCase();
  var STR = {
    es: {sections: 'Secciones', abstract: 'Resumen', refs: 'Referencias', cite: 'Cómo citar', search: 'Búsqueda', font: 'Fuente', smaller: 'Reducir letra', larger: 'Aumentar letra', trans: 'Idiomas', color: 'Color base', width: 'Ancho de página', wNarrow: 'Estrecho', wWide: 'Ancho', wFull: 'Completo', typeface: 'Tipografía', reset: 'Restablecer estilo', images: 'Imágenes', ph: 'Buscar en el texto…', prev: 'Anterior', next: 'Siguiente', close: 'Cerrar', pick: 'Haga clic en un formato de citación', copy: 'Copiar', copied: 'Copiado', none: 'Sin resultados', fail: 'No se pudo crear el ZIP; se descargan las imágenes una a una.', noimg: 'El artículo no tiene imágenes.', strip: 'Galería de imágenes', ctx: 'Ver contexto', dl: 'Descargar imagen', pdf: 'PDF', epub: 'ePub', epubTitle: 'Libro electrónico (ePub)', epubRead: 'Leer en línea', epubGet: 'Descargar ePub', epubBusy: 'Generando ePub…', epubFail: 'No se pudo generar el ePub.', epubTheme: 'Tema: claro / sepia / noche', toc: 'Contenidos', coverCh: 'Portada', doi: 'DOI', issnE: 'ISSN electrónico', issnP: 'ISSN impreso', issn: 'ISSN'},
    en: {sections: 'Sections', abstract: 'Abstract', refs: 'References', cite: 'How to cite', search: 'Search', font: 'Font', smaller: 'Smaller text', larger: 'Larger text', trans: 'Languages', color: 'Base colour', width: 'Page width', wNarrow: 'Narrow', wWide: 'Wide', wFull: 'Full', typeface: 'Typeface', reset: 'Reset style', images: 'Images', ph: 'Search the text…', prev: 'Previous', next: 'Next', close: 'Close', pick: 'Click a citation format', copy: 'Copy', copied: 'Copied', none: 'No results', fail: 'The ZIP could not be created; images are downloaded one by one.', noimg: 'The article has no images.', strip: 'Image gallery', ctx: 'View in context', dl: 'Download image', pdf: 'PDF', epub: 'ePub', epubTitle: 'E-book (ePub)', epubRead: 'Read online', epubGet: 'Download ePub', epubBusy: 'Building ePub…', epubFail: 'The ePub could not be generated.', epubTheme: 'Theme: light / sepia / night', toc: 'Contents', coverCh: 'Cover', doi: 'DOI', issnE: 'Online ISSN', issnP: 'Print ISSN', issn: 'ISSN'}
  };
  var T = STR[lang.slice(0, 2)] || STR.en;

  function el(tag, attrs, text) {
    var e = doc.createElement(tag);
    if (attrs) { for (var k in attrs) { e.setAttribute(k, attrs[k]); } }
    if (text) { e.textContent = text; }
    return e;
  }
  function meta(name) {
    var out = [];
    var m = doc.querySelectorAll('meta[name="' + name + '"]');
    for (var i = 0; i < m.length; i++) { out.push(m[i].getAttribute('content')); }
    return out;
  }
  function store(k, v) { try { if (v === undefined) { return localStorage.getItem(k); } localStorage.setItem(k, v); } catch (e) { return null; } }

  var body = doc.body;

  /* ---------- page skeleton: head, menu, side index, content ---------- */
  var page = el('div', {id: 'jv-page'});
  var head = el('div', {id: 'jv-head'});
  var titles = meta('citation_title');
  var journal = meta('citation_journal_title')[0] || '';
  var logoUrl = meta('citation_journal_logo')[0];
  if (logoUrl) { head.appendChild(el('img', {'class': 'jv-logo', src: logoUrl, alt: journal})); }
  var jbox = el('div', {'class': 'jv-jbox'});
  jbox.appendChild(el('div', {'class': 'jv-journal'}, journal));
  [['citation_issn_epub', T.issnE], ['citation_issn_ppub', T.issnP], ['citation_issn', T.issn]].forEach(function (p) {
    var v = meta(p[0])[0];
    if (v) { jbox.appendChild(el('div', {'class': 'jv-issn'}, p[1] + ': ' + v)); }
  });
  head.appendChild(jbox);
  var menu = el('div', {id: 'jv-menu', role: 'toolbar'});
  var main = el('div', {id: 'jv-main'});
  var toc = el('nav', {id: 'jv-toc', 'aria-label': T.sections});
  var contentWrap = el('div', {id: 'jv-content'});
  var originals = Array.prototype.slice.call(body.childNodes);
  originals.forEach(function (n) { if (n.nodeName !== 'SCRIPT') { contentWrap.appendChild(n); } });
  var script = body.querySelector('script');
  main.appendChild(toc); main.appendChild(contentWrap);
  page.appendChild(head); page.appendChild(menu);
  var sBox = el('div', {id: 'jv-search'});
  page.appendChild(sBox); page.appendChild(main);
  body.insertBefore(page, script);

  var content = doc.getElementById('article-body') || contentWrap;
  var front = doc.getElementById('article-front');

  /* ---------- title block (title + authors + journal) ---------- */
  var tb = el('div', {id: 'jv-titleblock'});
  if (titles[0]) { tb.appendChild(el('h1', null, titles[0])); }
  var ficha = el('div', {'class': 'jv-ficha'});
  var au = meta('citation_author').map(function (n) { var p = n.split(','); return (p[1] ? p[1].trim() + ' ' : '') + p[0].trim(); });
  var auOrcid = meta('citation_author_orcid');
  var ORCID_ICON = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNzJweCIgaGVpZ2h0PSI3MnB4IiB2aWV3Qm94PSIwIDAgNzIgNzIiIHZlcnNpb249IjEuMSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB4bWxuczp4bGluaz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayI+PHRpdGxlPk9yY2lkIGxvZ288L3RpdGxlPjxnIHN0cm9rZT0ibm9uZSIgc3Ryb2tlLXdpZHRoPSIxIiBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxnIHRyYW5zZm9ybT0idHJhbnNsYXRlKC05MjQuMDAwMDAwLCAtNzIuMDAwMDAwKSIgZmlsbC1ydWxlPSJub256ZXJvIj48Zz48ZyB0cmFuc2Zvcm09InRyYW5zbGF0ZSg5MjQuMDAwMDAwLCA3Mi4wMDAwMDApIj48cGF0aCBkPSJNNzIsMzYgQzcyLDU1Ljg4NDM3NSA1NS44ODQzNzUsNzIgMzYsNzIgQzE2LjExNTYyNSw3MiAwLDU1Ljg4NDM3NSAwLDM2IEMwLDE2LjExNTYyNSAxNi4xMTU2MjUsMCAzNiwwIEM1NS44ODQzNzUsMCA3MiwxNi4xMTU2MjUgNzIsMzYgWiIgZmlsbD0iI0E2Q0UzOSI+PC9wYXRoPjxnIHRyYW5zZm9ybT0idHJhbnNsYXRlKDE4Ljg2ODk2NiwgMTIuOTEwMzQ1KSIgZmlsbD0iI0ZGRkZGRiI+PHBvbHlnb24gcG9pbnRzPSI1LjAzNzM0OTI5IDM5LjEyNTA4NzggMC42OTU0Mjk4NjEgMzkuMTI1MDg3OCAwLjY5NTQyOTg2MSA5LjE0NDMxNzg3IDUuMDM3MzQ5MjkgOS4xNDQzMTc4NyA1LjAzNzM0OTI5IDIyLjY5MzA1MDUgNS4wMzczNDkyOSAzOS4xMjUwODc4Ij48L3BvbHlnb24+PHBhdGggZD0iTTExLjQwOTI1Nyw5LjE0NDMxNzg3IEwyMy4xMzgwNzg0LDkuMTQ0MzE3ODcgQzM0LjMwMzAxNCw5LjE0NDMxNzg3IDM5LjIwODgxOTEsMTcuMDY2NDA3NCAzOS4yMDg4MTkxLDI0LjE0ODY5OTUgQzM5LjIwODgxOTEsMzEuODQ2ODQzIDMzLjE0NzA0ODUsMzkuMTUzMDgxMSAyMy4xOTQ0NjY5LDM5LjE1MzA4MTEgTDExLjQwOTI1NywzOS4xNTMwODExIEwxMS40MDkyNTcsOS4xNDQzMTc4NyBaIE0xNS43NTExNzY1LDM1LjI2MjAxOTQgTDIyLjY1ODc3NTYsMzUuMjYyMDE5NCBDMzIuNDk4NTgsMzUuMjYyMDE5NCAzNC43NTQxMjI2LDI3Ljg0MzgwODQgMzQuNzU0MTIyNiwyNC4xNDg2OTk1IEMzNC43NTQxMjI2LDE4LjEzMDE1MDkgMzAuODkxNTA1OSwxMy4wMzUzNzk1IDIyLjQzMzIyMTMsMTMuMDM1Mzc5NSBMMTUuNzUxMTc2NSwxMy4wMzUzNzk1IEwxNS43NTExNzY1LDM1LjI2MjAxOTQgWiI+PC9wYXRoPjxwYXRoIGQ9Ik01LjcxNDAxMjA2LDIuOTAxODIzMjkgQzUuNzE0MDEyMDYsNC40NDE0NTIgNC40NDUyNjkzNyw1LjcyOTE0MTQ2IDIuODY2Mzg5NTgsNS43MjkxNDE0NiBDMS4yODc1MDk3OCw1LjcyOTE0MTQ2IDAuMDE4NzY3MDkxOCw0LjQ0MTQ1MiAwLjAxODc2NzA5MTgsMi45MDE4MjMyOSBDMC4wMTg3NjcwOTE4LDEuMzM0MjAxMzMgMS4yODc1MDk3OCwwLjA3NDUwNTEwOTYgMi44NjYzODk1OCwwLjA3NDUwNTEwOTYgQzQuNDQ1MjY5MzcsMC4wNzQ1MDUxMDk2IDUuNzE0MDEyMDYsMS4zNjIxOTQ1OCA1LjcxNDAxMjA2LDIuOTAxODIzMjkgWiI+PC9wYXRoPjwvZz48L2c+PC9nPjwvZz48L2c+PC9zdmc+';
  if (au.length) {
    var b = el('b');
    au.forEach(function (name, i) {
      if (i) { b.appendChild(doc.createTextNode(', ')); }
      b.appendChild(doc.createTextNode(name));
      var o = (auOrcid[i] || '').trim();
      if (o) {
        var href = /^https?:/i.test(o) ? o : 'https://orcid.org/' + o;
        var a = el('a', {href: href, target: '_blank', rel: 'noopener', 'class': 'jv-orcid', title: 'ORCID: ' + o.replace(/^https?:\/\/orcid\.org\//i, '')});
        a.appendChild(el('img', {src: ORCID_ICON, alt: 'ORCID'}));
        b.appendChild(doc.createTextNode(' '));
        b.appendChild(a);
      }
    });
    ficha.appendChild(b); ficha.appendChild(el('br'));
  }
  var vol = meta('citation_volume')[0], iss = meta('citation_issue')[0], yr = meta('citation_publication_date')[0];
  var src = [journal, vol ? 'vol. ' + vol : '', iss ? 'n.º ' + iss : '', yr || ''].filter(Boolean).join(', ');
  if (src) { ficha.appendChild(doc.createTextNode(src)); }
  if (ficha.childNodes.length) { tb.appendChild(ficha); }
  contentWrap.insertBefore(tb, contentWrap.firstChild);

  /* ---------- ORCID iDs in the body (affiliations, notes...) -> official logo ---------- */
  (function () {
    var RE = /\[?\s*(?:https?:\/\/(?:www\.)?orcid\.org\/)(\d{4}-\d{4}-\d{4}-\d{3}[\dXx])\s*\]?/;
    function icon(id) {
      var a = el('a', {href: 'https://orcid.org/' + id, target: '_blank', rel: 'noopener', 'class': 'jv-orcid', title: 'ORCID: ' + id});
      a.appendChild(el('img', {src: ORCID_ICON, alt: 'ORCID'}));
      return a;
    }
    var walker = doc.createTreeWalker(contentWrap, NodeFilter.SHOW_TEXT, null, false), nodes = [], n;
    while ((n = walker.nextNode())) {
      if (RE.test(n.nodeValue) && !(n.parentNode.closest && n.parentNode.closest('#jv-titleblock, script, style'))) { nodes.push(n); }
    }
    nodes.forEach(function (t) {
      var p = t.parentNode, m = RE.exec(t.nodeValue);
      if (!m) { return; }
      var link = icon(m[1].toUpperCase());
      var anchor = p.nodeName === 'A' && p.textContent.trim().replace(/^\[|\]$/g, '').replace(/^https?:\/\/(www\.)?orcid\.org\//, '') === m[1] ? p : null;
      var rest = t.nodeValue.slice(m.index + m[0].length), before = t.nodeValue.slice(0, m.index);
      function dropBracket(node, ch) {
        if (node && node.nodeType === 1 && node.className === 'generated' && node.textContent.trim() === ch) { node.parentNode.removeChild(node); }
      }
      if (anchor) {
        dropBracket(anchor.previousSibling, '['); dropBracket(anchor.nextSibling, ']');
        anchor.parentNode.replaceChild(link, anchor);
        return;
      }
      if (before) { p.insertBefore(doc.createTextNode(before), t); }
      p.insertBefore(link, t);
      t.nodeValue = rest.replace(/^\s+/, '') ? ' ' + rest.replace(/^\s+/, '') : ' ';
      dropBracket(link.previousSibling, '[');
      dropBracket(t.nextSibling, ']');
    });
  })();

  /* ---------- menu ---------- */
  var buttons = {};
  function btn(key, icon, label, fn) {
    var b = el('button', {type: 'button', title: label, 'aria-label': label});
    b.appendChild(el('span', null, label));
    b.appendChild(el('span', {'class': 'jv-ic', 'aria-hidden': 'true'}, icon));
    b.addEventListener('click', fn);
    menu.appendChild(b);
    buttons[key] = b;
    return b;
  }
  btn('sections', '☰', T.sections, function () { toc.classList.toggle('jv-hidden'); store('jv-toc', toc.classList.contains('jv-hidden') ? '0' : '1'); });
  btn('abstract', '✎', T.abstract, function () { scrollTo(front && front.querySelector('.jv-abs, .callout-title') || front); });
  btn('refs', '📌', T.refs, function () { scrollTo(doc.querySelector('.ref-list') || doc.querySelector('.back-section')); });
  btn('cite', '❝', T.cite, function () { showCite(); });
  btn('search', '🔍', T.search, function () { toggleSearch(); });
  var fontBox = el('div', {'class': 'jv-font'});
  fontBox.appendChild(el('span', null, T.font));
  [['A−', T.smaller, -15], ['A+', T.larger, 15]].forEach(function (d) {
    var fb = el('button', {type: 'button', title: d[1], 'aria-label': d[1]}, d[0]);
    fb.addEventListener('click', function () { setSize(d[2]); });
    fontBox.appendChild(fb);
  });
  menu.appendChild(fontBox);

  /* ---------- theme: base colour + typeface ---------- */
  var DEFAULT_COLOR = '#2c5697';
  var FONTS = [
    ['Open Sans', '"Open Sans","Segoe UI",Helvetica,Arial,sans-serif'],
    ['Segoe UI / Arial', '"Segoe UI",Helvetica,Arial,sans-serif'],
    ['Verdana', 'Verdana,Geneva,sans-serif'],
    ['Trebuchet', '"Trebuchet MS",Tahoma,sans-serif'],
    ['Georgia', 'Georgia,"Times New Roman",serif'],
    ['Times New Roman', '"Times New Roman",Times,serif'],
    ['Palatino', '"Palatino Linotype",Palatino,"Book Antiqua",serif'],
    ['Courier', '"Courier New",Courier,monospace']
  ];
  var baseColor = DEFAULT_COLOR;
  function readableColor(hex) {
    var n = parseInt(hex.slice(1), 16), c = [(n >> 16) & 255, (n >> 8) & 255, n & 255];
    function lum(a) { return (0.2126 * a[0] + 0.7152 * a[1] + 0.0722 * a[2]) / 255; }
    while (lum(c) > 0.55) { c = c.map(function (v) { return Math.round(v * 0.9); }); }
    return c;
  }
  function applyColor(hex) {
    if (!/^#[0-9a-f]{6}$/i.test(hex)) { hex = DEFAULT_COLOR; }
    var c = readableColor(hex), s = doc.documentElement.style;
    baseColor = '#' + c.map(function (v) { return ('0' + v.toString(16)).slice(-2); }).join('');
    s.setProperty('--jv-blue', baseColor);
    s.setProperty('--jv-blue-a', 'color-mix(in srgb, var(--jv-blue) 90%, #fff)');
    s.setProperty('--jv-blue-b', 'color-mix(in srgb, var(--jv-blue) 70%, #fff)');
    s.setProperty('--jv-blue-s', 'rgba(' + c.join(',') + ',.95)');
    store('jv-color', hex);
    return hex;
  }
  function applyFont(i) {
    i = FONTS[i] ? i : 0;
    doc.documentElement.style.setProperty('--jv-font', FONTS[i][1]);
    store('jv-font', String(i));
    return i;
  }
  var themeBox = el('div', {'class': 'jv-theme'});
  var colorIn = el('input', {type: 'color', title: T.color, 'aria-label': T.color});
  var fontSel = el('select', {title: T.typeface, 'aria-label': T.typeface});
  FONTS.forEach(function (f, i) { fontSel.appendChild(el('option', {value: String(i)}, f[0])); });
  var resetBtn = el('button', {type: 'button', title: T.reset, 'aria-label': T.reset}, '↺');
  var WIDTHS = [['1140px', T.wNarrow], ['1680px', T.wWide], ['100%', T.wFull, '100%']];
  var widthSel = el('select', {title: T.width, 'aria-label': T.width});
  WIDTHS.forEach(function (w, i) { widthSel.appendChild(el('option', {value: String(i)}, '↔ ' + w[1])); });
  function applyWidth(i) {
    i = WIDTHS[i] ? i : 1;
    doc.documentElement.style.setProperty('--jv-width', WIDTHS[i][0]);
    doc.documentElement.style.setProperty('--jv-pw', WIDTHS[i][2] || '96%');
    store('jv-width', String(i));
    updateScale();
    return i;
  }
  var wStored = parseInt(store('jv-width'), 10);
  widthSel.value = String(applyWidth(isNaN(wStored) ? 1 : wStored));
  widthSel.addEventListener('change', function () { applyWidth(parseInt(widthSel.value, 10)); });
  colorIn.value = applyColor(store('jv-color') || DEFAULT_COLOR);
  fontSel.value = String(applyFont(parseInt(store('jv-font'), 10) || 0));
  colorIn.addEventListener('input', function () { applyColor(colorIn.value); });
  fontSel.addEventListener('change', function () { applyFont(parseInt(fontSel.value, 10)); });
  resetBtn.addEventListener('click', function () { colorIn.value = applyColor(DEFAULT_COLOR); fontSel.value = String(applyFont(0)); widthSel.value = String(applyWidth(1)); });
  [colorIn, fontSel, widthSel, resetBtn].forEach(function (n) { themeBox.appendChild(n); });
  menu.appendChild(themeBox);
  btn('lang', '🌐', T.trans, function () { cycleLang(); });
  btn('images', '⬇', T.images, function () { downloadImages(); });

  function scrollTo(node) {
    if (!node) { return; }
    node.scrollIntoView({behavior: 'smooth', block: 'start'});
    node.classList.remove('jv-flash'); void node.offsetWidth; node.classList.add('jv-flash');
  }

  /* ---------- font size ---------- */
  var size = parseInt(store('jv-size'), 10) || 100;
  function setSize(d) {
    size = Math.max(70, Math.min(260, size + d));
    contentWrap.style.fontSize = (14 * size / 100) + 'px';
    store('jv-size', String(size));
    doc.documentElement.style.setProperty('--jv-fs', (14 * size / 100) + 'px');
    updateScale();
  }
  contentWrap.style.fontSize = (14 * size / 100) + 'px';
  doc.documentElement.style.setProperty('--jv-fs', (14 * size / 100) + 'px');

  /* ---------- note markers as superscripts ---------- */
  Array.prototype.forEach.call(contentWrap.querySelectorAll('a[href^="#"]'), function (a) {
    var rid = a.getAttribute('href').slice(1), txt = a.textContent.trim();
    if (!rid || txt.length > 6 || a.closest('sup, #jv-toc') || /^(bib|fig|tbl|table|sec|aff|corresp)/i.test(rid)) { return; }
    var tgt = doc.getElementById(rid);
    var isNote = /^(fn|ftn|nota|note|tfn)/i.test(rid) || (tgt && (tgt.matches('.fn, .footnote, [id^="fn"]') || tgt.closest('.fn-group, .footnote')));
    if (!isNote) { return; }
    var m = /^\[(.+)\]$/.exec(txt);
    if (m) { a.textContent = m[1]; }
    var sup = el('sup', {'class': 'jv-note'});
    a.parentNode.insertBefore(sup, a);
    sup.appendChild(a);
  });

  /* ---------- section index ---------- */
  var heads = content.querySelectorAll('h2.main-title, h3.section-title, h4.subsection-title');
  var tocLinks = [], seen = [];
  Array.prototype.forEach.call(heads, function (h) {
    var txt = h.textContent.replace(/\s+/g, ' ').trim();
    if (!txt || h.closest('.fig, .table-wrap, .caption')) { return; }
    var a = el('a', {'class': h.tagName === 'H2' ? 'jv-sec' : 'jv-sub', tabindex: '0'}, txt);
    a.addEventListener('click', function () {
      scrollTo(h);
      if (window.innerWidth <= 800) { toc.classList.add('jv-hidden'); }
    });
    toc.appendChild(a);
    tocLinks.push(a); seen.push(h);
  });
  if (!tocLinks.length) { toc.classList.add('jv-hidden'); buttons.sections.style.display = 'none'; }
  else if (store('jv-toc') === '0' || window.innerWidth <= 800) { toc.classList.add('jv-hidden'); }
  var ticking = false;
  window.addEventListener('scroll', function () {
    if (ticking || !tocLinks.length) { return; }
    ticking = true;
    requestAnimationFrame(function () {
      var current = 0;
      for (var i = 0; i < seen.length; i++) { if (seen[i].getBoundingClientRect().top < 120) { current = i; } }
      tocLinks.forEach(function (a, i) { a.classList.toggle('jv-active', i === current); });
      ticking = false;
    });
  });

  /* ---------- search ---------- */
  var sIn = el('input', {type: 'search', placeholder: T.ph, 'aria-label': T.search});
  var sCount = el('span', {id: 'jv-count'});
  var sPrev = el('button', {type: 'button', title: T.prev}, '▲');
  var sNext = el('button', {type: 'button', title: T.next}, '▼');
  var sClose = el('button', {type: 'button', title: T.close}, '✕');
  [sIn, sCount, sPrev, sNext, sClose].forEach(function (n) { sBox.appendChild(n); });
  var hits = [], cur = -1;

  function clearHits() {
    hits.forEach(function (m) {
      var p = m.parentNode;
      if (p) { p.replaceChild(doc.createTextNode(m.textContent), m); p.normalize(); }
    });
    hits = []; cur = -1; sCount.textContent = '';
  }
  function runSearch() {
    clearHits();
    var q = sIn.value.trim();
    if (q.length < 2) { return; }
    var re = new RegExp(q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'gi');
    var walker = doc.createTreeWalker(contentWrap, NodeFilter.SHOW_TEXT, {acceptNode: function (n) {
      var p = n.parentNode;
      return (!p || /^(SCRIPT|STYLE|MARK)$/.test(p.nodeName) || !n.nodeValue.trim()) ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT;
    }});
    var nodes = [];
    while (walker.nextNode()) { nodes.push(walker.currentNode); }
    nodes.forEach(function (n) {
      var t = n.nodeValue, m, last = 0, frag = null;
      re.lastIndex = 0;
      while ((m = re.exec(t)) !== null) {
        if (!m[0]) { re.lastIndex++; continue; }
        frag = frag || doc.createDocumentFragment();
        frag.appendChild(doc.createTextNode(t.slice(last, m.index)));
        var mk = el('mark', {'class': 'jv-hit'}, m[0]);
        frag.appendChild(mk); hits.push(mk);
        last = m.index + m[0].length;
      }
      if (frag) { frag.appendChild(doc.createTextNode(t.slice(last))); n.parentNode.replaceChild(frag, n); }
    });
    if (hits.length) { go(0); } else { sCount.textContent = T.none; }
  }
  function go(i) {
    if (!hits.length) { return; }
    if (cur >= 0 && hits[cur]) { hits[cur].classList.remove('jv-cur'); }
    cur = (i + hits.length) % hits.length;
    hits[cur].classList.add('jv-cur');
    hits[cur].scrollIntoView({behavior: 'smooth', block: 'center'});
    sCount.textContent = (cur + 1) + ' / ' + hits.length;
  }
  function toggleSearch(force) {
    var on = force === undefined ? !sBox.classList.contains('jv-on') : force;
    sBox.classList.toggle('jv-on', on);
    buttons.search.classList.toggle('jv-on', on);
    if (on) { sIn.focus(); sIn.select(); } else { clearHits(); }
  }
  var timer;
  sIn.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(runSearch, 200); });
  sIn.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); if (hits.length) { go(cur + (e.shiftKey ? -1 : 1)); } else { runSearch(); } }
  });
  sNext.addEventListener('click', function () { go(cur + 1); });
  sPrev.addEventListener('click', function () { go(cur - 1); });
  sClose.addEventListener('click', function () { toggleSearch(false); });

  /* ---------- image viewer + bottom gallery strip ---------- */
  var imgs = Array.prototype.filter.call(content.querySelectorAll('img'), function (i) { return i.getAttribute('src'); });
  var ov = el('div', {id: 'jv-overlay', role: 'dialog', 'aria-modal': 'true'});
  var ovImg = el('img', {alt: ''});
  var ovCap = el('div', {'class': 'jv-cap'});
  var ovX = el('button', {type: 'button', 'class': 'jv-x', title: T.close}, '✕');
  var ovP = el('button', {type: 'button', 'class': 'jv-prev', title: T.prev}, '‹');
  var ovN = el('button', {type: 'button', 'class': 'jv-next', title: T.next}, '›');
  var ovBar = el('div', {'class': 'jv-bar'});
  var ovCtx = el('button', {type: 'button'}, T.ctx);
  var ovDl = el('button', {type: 'button'}, T.dl);
  ovBar.appendChild(ovCtx); ovBar.appendChild(ovDl);
  [ovX, ovP, ovN, ovImg, ovCap, ovBar].forEach(function (n) { ov.appendChild(n); });
  body.insertBefore(ov, script);
  var ovIdx = 0;
  ovCtx.addEventListener('click', function () {
    var im = imgs[ovIdx];
    ov.classList.remove('jv-on');
    scrollTo(im && (im.closest('.fig, .panel, .table-wrap') || im));
  });
  ovDl.addEventListener('click', function () {
    var u = imgs[ovIdx].currentSrc || imgs[ovIdx].src;
    clickDownload(u, fileName(u, ovIdx));
  });
  function showImg(i) {
    ovIdx = (i + imgs.length) % imgs.length;
    var im = imgs[ovIdx];
    ovImg.src = im.currentSrc || im.src;
    ovImg.alt = im.alt || '';
    var f = im.closest('.fig, .panel, .table-wrap');
    var c = f && f.querySelector('.caption');
    ovCap.textContent = c ? c.textContent.replace(/\s+/g, ' ').trim() : '';
    ov.classList.add('jv-on');
  }
  imgs.forEach(function (im, i) {
    im.addEventListener('click', function (e) { e.preventDefault(); showImg(i); });
  });
  ovX.addEventListener('click', function () { ov.classList.remove('jv-on'); });
  ovImg.addEventListener('click', function () { ov.classList.remove('jv-on'); });
  ovP.addEventListener('click', function () { showImg(ovIdx - 1); });
  ovN.addEventListener('click', function () { showImg(ovIdx + 1); });

  if (imgs.length) {
    var strip = el('div', {id: 'jv-strip'});
    var thumbs = el('div', {'class': 'jv-thumbs'});
    imgs.forEach(function (im, i) {
      var t = el('img', {src: im.getAttribute('src'), alt: im.alt || '', loading: 'lazy'});
      t.addEventListener('click', function () { showImg(i); });
      thumbs.appendChild(t);
    });
    var al = el('button', {type: 'button', 'class': 'jv-arrow l', title: T.prev}, '‹');
    var ar = el('button', {type: 'button', 'class': 'jv-arrow r', title: T.next}, '›');
    al.addEventListener('click', function () { thumbs.scrollLeft -= 240; });
    ar.addEventListener('click', function () { thumbs.scrollLeft += 240; });
    strip.appendChild(thumbs); strip.appendChild(al); strip.appendChild(ar);
    var sb = el('button', {type: 'button', id: 'jv-stripbtn', 'class': 'jv-has', title: T.strip, 'aria-label': T.strip}, '🖼');
    // The floating carousel starts open on wide screens unless the reader closed it before
    if (store('jv-strip') !== '0' && window.innerWidth > 900) { body.classList.add('jv-strip-open'); }
    sb.addEventListener('click', function () { store('jv-strip', body.classList.toggle('jv-strip-open') ? '1' : '0'); });
    body.insertBefore(strip, script); body.insertBefore(sb, script);
  }

  /* ---------- download all images as ZIP (stored, no compression) ---------- */
  var crcTable = (function () {
    var t = [], c, n, k;
    for (n = 0; n < 256; n++) { c = n; for (k = 0; k < 8; k++) { c = c & 1 ? 0xEDB88320 ^ (c >>> 1) : c >>> 1; } t[n] = c >>> 0; }
    return t;
  })();
  function crc32(u8) {
    var c = 0xFFFFFFFF;
    for (var i = 0; i < u8.length; i++) { c = crcTable[(c ^ u8[i]) & 0xFF] ^ (c >>> 8); }
    return (c ^ 0xFFFFFFFF) >>> 0;
  }
  function buildZip(files) {
    var enc = new TextEncoder(), parts = [], central = [], offset = 0, csize = 0;
    files.forEach(function (f) {
      var name = enc.encode(f.name), crc = crc32(f.data);
      var h = new DataView(new ArrayBuffer(30));
      h.setUint32(0, 0x04034b50, true); h.setUint16(4, 20, true); h.setUint16(6, 0x0800, true);
      h.setUint32(14, crc, true); h.setUint32(18, f.data.length, true); h.setUint32(22, f.data.length, true);
      h.setUint16(26, name.length, true);
      parts.push(h.buffer, name, f.data);
      var c = new DataView(new ArrayBuffer(46));
      c.setUint32(0, 0x02014b50, true); c.setUint16(4, 20, true); c.setUint16(6, 20, true); c.setUint16(8, 0x0800, true);
      c.setUint32(16, crc, true); c.setUint32(20, f.data.length, true); c.setUint32(24, f.data.length, true);
      c.setUint16(28, name.length, true); c.setUint32(42, offset, true);
      central.push(c.buffer, name);
      csize += 46 + name.length;
      offset += 30 + name.length + f.data.length;
    });
    var e = new DataView(new ArrayBuffer(22));
    e.setUint32(0, 0x06054b50, true); e.setUint16(8, files.length, true); e.setUint16(10, files.length, true);
    e.setUint32(12, csize, true); e.setUint32(16, offset, true);
    return new Blob(parts.concat(central, [e.buffer]), {type: 'application/zip'});
  }
  function fileName(u, idx) { return decodeURIComponent(u.split('?')[0].split('/').pop()) || ('image' + (idx + 1)); }
  function clickDownload(href, name) {
    var a = el('a', {href: href, download: name});
    body.appendChild(a); a.click(); body.removeChild(a);
  }
  function downloadImages() {
    var urls = [];
    imgs.forEach(function (i) { var s = i.currentSrc || i.src; if (urls.indexOf(s) < 0) { urls.push(s); } });
    if (!urls.length) { alert(T.noimg); return; }
    Promise.all(urls.map(function (u, idx) {
      return fetch(u).then(function (r) { if (!r.ok) { throw new Error(r.status); } return r.arrayBuffer(); }).then(function (b) {
        return {name: fileName(u, idx), data: new Uint8Array(b)};
      });
    })).then(function (files) {
      var used = {};
      files.forEach(function (f) { while (used[f.name]) { f.name = '_' + f.name; } used[f.name] = 1; });
      var url = URL.createObjectURL(buildZip(files));
      clickDownload(url, 'images.zip');
      setTimeout(function () { URL.revokeObjectURL(url); }, 4000);
    }).catch(function () {
      alert(T.fail);
      urls.forEach(function (u, i) { setTimeout(function () { clickDownload(u, fileName(u, i)); }, i * 300); });
    });
  }

  /* ---------- cite ---------- */
  var modal = el('div', {id: 'jv-modal', role: 'dialog', 'aria-modal': 'true'});
  var box = el('div', {'class': 'jv-box'});
  modal.appendChild(box); body.insertBefore(modal, script);
  modal.addEventListener('click', function (e) { if (e.target === modal) { modal.classList.remove('jv-on'); } });
  var authors = meta('citation_author').map(function (n) {
    var p = n.split(',');
    var given = (p[1] || '').trim();
    return {sur: p[0].trim(), given: given, ini: given.split(/\s+/).filter(Boolean).map(function (w) { return w.charAt(0).toUpperCase() + '.'; }).join(' '), inip: given.split(/\s+/).filter(Boolean).map(function (w) { return w.charAt(0).toUpperCase(); }).join('')};
  });
  var fpage = meta('citation_firstpage')[0] || '', lpage = meta('citation_lastpage')[0] || '';
  var pages = fpage ? (lpage && lpage !== fpage ? fpage + '-' + lpage : fpage) : '';
  var month = meta('citation_month')[0] || '', publisher = meta('citation_publisher')[0] || '';
  var doiV = meta('citation_doi')[0] || '';
  var title = titles[0] || '';
  function esc(s) { return String(s).replace(/&/g, '&amp;').split('<').join('&lt;'); }
  function join(list, last) { return list.length > 1 ? list.slice(0, -1).join(', ') + last + list[list.length - 1] : (list[0] || ''); }
  var isEs = lang.slice(0, 2) === 'es';
  var AND = isEs ? ' y ' : ' & ';
  var doiTxt = doiV ? ' https://doi.org/' + esc(doiV) : '';
  var pp = pages.indexOf('-') > 0 ? 'pp. ' : 'p. ';
  function numIss(sep) { var v = esc(vol || '') + (iss ? '(' + esc(iss) + ')' : ''); return v + (pages ? (v ? sep : '') + esc(pages) : ''); }
  // Each formatter returns HTML so the journal name can be italicised
  var FORMATS = [
    ['APA', function () {
      var a = authors.map(function (x) { return esc(x.sur) + (x.ini ? ', ' + esc(x.ini) : ''); });
      return join(a, ', &amp; ') + ' (' + esc(yr || 's.f.') + '). ' + esc(title) + '. <i>' + esc(journal) + '</i>' + (vol ? ', <i>' + esc(vol) + '</i>' : '') + (iss ? '(' + esc(iss) + ')' : '') + (pages ? ', ' + esc(pages) : '') + '.' + doiTxt;
    }],
    ['ISO 690-2', function () {
      var a = authors.map(function (x) { return esc(x.sur.toUpperCase()) + (x.given ? ', ' + esc(x.given) : ''); });
      return a.join('; ') + '. ' + esc(title) + '. <i>' + esc(journal) + '</i>' + (numIss(': ') ? ', ' + numIss(': ') : '') + (month || yr ? ', ' + esc((month ? month + ' ' : '') + (yr || '')) : '') + '.' + (doiV ? ' DOI ' + esc(doiV) : '');
    }],
    ['Harvard', function () {
      var a = authors.map(function (x) { return esc(x.sur) + (x.ini ? ', ' + esc(x.ini) : ''); });
      return join(a, ' and ') + ' (' + esc(yr || 'n.d.') + ') \'' + esc(title) + '\', <i>' + esc(journal) + '</i>' + (vol ? ', ' + esc(vol) : '') + (iss ? '(' + esc(iss) + ')' : '') + (pages ? ', ' + pp + esc(pages) : '') + '.' + doiTxt;
    }],
    ['MLA', function () {
      var a = authors.map(function (x, i) { return i === 0 ? esc(x.sur) + (x.given ? ', ' + esc(x.given) : '') : esc((x.given ? x.given + ' ' : '') + x.sur); });
      var as = a.length > 2 ? a[0] + ', et al' : a.length === 2 ? a[0] + ', and ' + a[1] : (a[0] || '');
      return as + '. "' + esc(title) + '." <i>' + esc(journal) + '</i>' + (vol ? ', vol. ' + esc(vol) : '') + (iss ? ', no. ' + esc(iss) : '') + (yr ? ', ' + esc(yr) : '') + (pages ? ', ' + pp + esc(pages) : '') + '.' + (doiV ? ' https://doi.org/' + esc(doiV) + '.' : '');
    }],
    ['Chicago', function () {
      var a = authors.map(function (x, i) { return i === 0 ? esc(x.sur) + (x.given ? ', ' + esc(x.given) : '') : esc((x.given ? x.given + ' ' : '') + x.sur); });
      return join(a, ', and ') + '. ' + (yr ? esc(yr) + '. ' : '') + '"' + esc(title) + '." <i>' + esc(journal) + '</i>' + (vol ? ' ' + esc(vol) : '') + (iss ? ' (' + esc(iss) + ')' : '') + (pages ? ': ' + esc(pages) : '') + '.' + doiTxt;
    }],
    ['Vancouver', function () {
      var a = authors.map(function (x) { return esc(x.sur) + (x.inip ? ' ' + esc(x.inip) : ''); });
      return a.slice(0, 6).join(', ') + (a.length > 6 ? ', et al' : '') + '. ' + esc(title) + '. ' + esc(journal) + '. ' + esc(yr || '') + (vol ? ';' + numIss(':') : '') + '.' + (doiV ? ' doi:' + esc(doiV) : '');
    }],
    ['IEEE', function () {
      var a = authors.map(function (x) { return (x.ini ? esc(x.ini) + ' ' : '') + esc(x.sur); });
      return (a.length > 6 ? a[0] + ' et al.' : join(a, ', and ')) + ', "' + esc(title) + '," <i>' + esc(journal) + '</i>' + (vol ? ', vol. ' + esc(vol) : '') + (iss ? ', no. ' + esc(iss) : '') + (pages ? ', ' + pp + esc(pages) : '') + (yr ? ', ' + esc(yr) : '') + (doiV ? ', doi: ' + esc(doiV) : '') + '.';
    }]
  ];
  function bibtex() {
    var a = meta('citation_author'), doi = meta('citation_doi')[0];
    var key = ((a[0] || 'article').split(',')[0].replace(/\W/g, '') + (yr || '')).toLowerCase();
    var lines = ['@article{' + key + ','];
    lines.push('  author = {' + a.map(function (n) { var p = n.split(','); return p[0].trim() + (p[1] ? ', ' + p[1].trim() : ''); }).join(' and ') + '},');
    lines.push('  title = {' + (titles[0] || '') + '},');
    lines.push('  journal = {' + journal + '},');
    if (yr) { lines.push('  year = {' + yr + '},'); }
    if (vol) { lines.push('  volume = {' + vol + '},'); }
    if (iss) { lines.push('  number = {' + iss + '},'); }
    if (pages) { lines.push('  pages = {' + pages.replace('-', '--') + '},'); }
    if (publisher) { lines.push('  publisher = {' + publisher + '},'); }
    if (doi) { lines.push('  doi = {' + doi + '},'); }
    lines.push('}');
    return lines.join('\n');
  }
  function copy(text, b) {
    var done = function () { var o = b.textContent; b.textContent = T.copied; setTimeout(function () { b.textContent = o; }, 1500); };
    if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(text).then(done, function () {}); return; }
    var ta = el('textarea'); ta.value = text; body.appendChild(ta); ta.select();
    try { doc.execCommand('copy'); done(); } catch (e) { /* the text stays selectable in the dialog */ }
    body.removeChild(ta);
  }
  function showCite() {
    box.textContent = '';
    box.appendChild(el('h3', null, T.cite));
    var tabs = el('div', {'class': 'jv-tabs'});
    var out = el('div', {'class': 'jv-cite'});
    out.textContent = T.pick;
    var cb = el('button', {type: 'button'}, T.copy);
    cb.style.display = 'none';
    var current = '';
    var tabEls = [];
    FORMATS.concat([['BibTeX', null]]).forEach(function (f) {
      var t = el('button', {type: 'button', 'class': 'jv-tab'}, f[0]);
      t.addEventListener('click', function () {
        tabEls.forEach(function (x) { x.classList.remove('jv-sel'); });
        t.classList.add('jv-sel');
        if (f[1]) { out.innerHTML = f[1](); current = out.textContent; out.classList.remove('jv-mono'); }
        else { current = bibtex(); out.textContent = current; out.classList.add('jv-mono'); }
        cb.style.display = '';
      });
      tabEls.push(t); tabs.appendChild(t);
    });
    cb.addEventListener('click', function () { copy(current, cb); });
    box.appendChild(tabs); box.appendChild(out); box.appendChild(cb);
    var c = el('button', {type: 'button'}, T.close);
    c.addEventListener('click', function () { modal.classList.remove('jv-on'); });
    box.appendChild(el('div'));
    box.appendChild(c);
    modal.classList.add('jv-on');
  }

  /* ---------- hover popups (references, notes, figures, tables) ---------- */
  var pop = el('div', {id: 'jv-pop'});
  body.insertBefore(pop, script);
  var popTimer = null, popA = null;
  function clip(s) { s = String(s || '').replace(/\s+/g, ' ').trim(); return s.length > 600 ? s.slice(0, 600) + '…' : s; }
  function popSource(id) {
    var n = id ? doc.getElementById(id) : null;
    if (!n) { return null; }
    if (n.nodeName === 'A' && !n.textContent.trim()) {
      return n.closest('.fig, .table-wrap, .row, .fn, .footnote') || n.parentElement;
    }
    return n;
  }
  function hidePop() { pop.classList.remove('jv-on'); popA = null; }
  function showPop(a) {
    var s = popSource(a.getAttribute('href').slice(1));
    if (!s || s.contains(a) || s === contentWrap || s === content) { popA = null; return; }
    pop.textContent = '';
    if (s.matches('.fig, .table-wrap')) {
      var im = s.querySelector('img');
      if (im) { pop.appendChild(el('img', {src: im.currentSrc || im.src, alt: ''})); }
      var lab = s.querySelector('.label'), cp = s.querySelector('.caption');
      pop.appendChild(el('div', {'class': 'jv-pt'}, clip([lab && lab.textContent, cp && cp.textContent].filter(Boolean).join('. '))));
    } else {
      var rc = s.matches('.row') && s.querySelector('.ref-content');
      pop.appendChild(el('div', {'class': 'jv-pt'}, clip((rc || s).textContent)));
    }
    if (!pop.textContent.trim() && !pop.querySelector('img')) { popA = null; return; }
    pop.style.visibility = 'hidden';
    pop.classList.add('jv-on');
    var r = a.getBoundingClientRect(), w = pop.offsetWidth, h = pop.offsetHeight;
    var left = Math.max(8, Math.min(r.left, window.innerWidth - w - 8));
    var top = r.bottom + 6;
    if (top + h > window.innerHeight - 8) { top = Math.max(8, r.top - h - 6); }
    pop.style.left = left + 'px'; pop.style.top = top + 'px';
    pop.style.visibility = '';
  }
  function popLink(e) {
    var a = e.target.closest ? e.target.closest('a[href^="#"]') : null;
    return a && a.getAttribute('href').length > 1 && contentWrap.contains(a) ? a : null;
  }
  doc.addEventListener('mouseover', function (e) {
    var a = popLink(e);
    if (!a) { return; }
    clearTimeout(popTimer);
    if (a !== popA) { popA = a; showPop(a); }
  });
  doc.addEventListener('mouseout', function (e) {
    if (popLink(e) === popA && popA) { popTimer = setTimeout(hidePop, 250); }
  });
  pop.addEventListener('mouseenter', function () { clearTimeout(popTimer); });
  pop.addEventListener('mouseleave', function () { popTimer = setTimeout(hidePop, 150); });

  /* ---------- shared helpers for PDF / ePub ---------- */
  function escA(s) { return String(s).split('&').join('&amp;').split('<').join('&lt;').split('"').join('&quot;'); }
  function slug() { return (titles[0] || 'article').normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^A-Za-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 60) || 'article'; }
  function authorLine() { return authors.map(function (a) { return (a.given ? a.given + ' ' : '') + a.sur; }).join(', '); }
  function sourceLine() { return [journal, vol ? 'vol. ' + vol : '', iss ? 'n.º ' + iss : '', yr || ''].filter(Boolean).join(', '); }
  function cleanClone(node) {
    var c = node.cloneNode(true);
    Array.prototype.forEach.call(c.querySelectorAll('mark.jv-hit'), function (m) { m.parentNode.replaceChild(doc.createTextNode(m.textContent), m); });
    Array.prototype.forEach.call(c.querySelectorAll('script, style, [id^="jv-"]'), function (n) { n.parentNode.removeChild(n); });
    Array.prototype.forEach.call(c.querySelectorAll('.jv-flash'), function (n) { n.classList.remove('jv-flash'); });
    return c;
  }
  function abstractClones() {
    return blocks.map(function (b) { return cleanClone(b); });
  }

  /* ---------- PDF: elegant print template ---------- */
  var PDF_CSS = '@page{size:A4;margin:24mm 20mm 24mm 20mm;@top-right{content:"__J__";font:italic 8.5pt Georgia,serif;color:#777}@bottom-center{content:counter(page);font:9pt Georgia,serif;color:#555}}' +
    'html{-webkit-print-color-adjust:exact;print-color-adjust:exact}' +
    'body{margin:0;color:#222;font:10.5pt/1.55 Georgia,"Times New Roman",serif;text-align:justify;hyphens:auto}' +
    '.cover{border-top:5px solid #2c5697;padding-top:10mm;margin-bottom:8mm}' +
    '.cover .j{font:600 9pt/1.2 Helvetica,Arial,sans-serif;letter-spacing:.14em;text-transform:uppercase;color:#2c5697}' +
    '.cover h1{font:700 22pt/1.25 Georgia,serif;margin:6mm 0 5mm;color:#111;text-align:left;hyphens:manual}' +
    '.cover .a{font:600 11.5pt/1.5 Georgia,serif;color:#2c5697;text-align:left}' +
    '.cover .s{font:9.5pt/1.5 Helvetica,Arial,sans-serif;color:#666;margin-top:2mm;text-align:left}' +
    '.abs{border-left:3px solid #c9a227;background:#f6f6f2;padding:3mm 5mm;margin:0 0 8mm;font-size:9.8pt;break-inside:avoid}' +
    '.abs h4,.abs .callout-title{font:700 9pt Helvetica,Arial,sans-serif;text-transform:uppercase;letter-spacing:.08em;color:#2c5697;margin:0 0 1mm}' +
    '.abs .metadata,.abs .table,.abs .row,.abs .cell{display:block;width:auto;border:0}' +
    'h2{font:700 14pt Georgia,serif;color:#2c5697;border-bottom:1px solid #2c5697;padding-bottom:1mm;margin:9mm 0 3mm;text-align:left;break-after:avoid}' +
    'h3,h4,h5{font:700 11pt Georgia,serif;color:#333;margin:6mm 0 2mm;text-align:left;break-after:avoid}' +
    'p{margin:0 0 2.6mm;orphans:3;widows:3}a{color:inherit;text-decoration:none}' +
    '.fig,.table-wrap{margin:5mm 0;text-align:center;break-inside:avoid}.fig img,img{max-width:100%;max-height:200mm;height:auto}' +
    '.caption{font:9pt/1.4 Helvetica,Arial,sans-serif;color:#555;text-align:left;margin-top:1.5mm}' +
    'table{border-collapse:collapse;width:100%;font:8.5pt/1.35 Helvetica,Arial,sans-serif}th,td{border-bottom:.5pt solid #bbb;padding:1.2mm 1.5mm;vertical-align:top;text-align:left}th{border-bottom:1pt solid #2c5697;color:#2c5697}' +
    '.ref-list .row,.ref-list .table,.ref-list .cell{display:block;border:0}.ref-label{display:none}.ref-content p,.citation{font-size:9pt;line-height:1.4;padding-left:6mm;text-indent:-6mm;text-align:left;margin:0 0 1.6mm}';
  function printPdf() {
    var frame = el('iframe', {'aria-hidden': 'true', tabindex: '-1'});
    frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden';
    body.appendChild(frame);
    var d = frame.contentDocument;
    var wrap = doc.createElement('div');
    var cover = el('div', {'class': 'cover'});
    cover.appendChild(el('div', {'class': 'j'}, journal));
    cover.appendChild(el('h1', null, titles[0] || ''));
    cover.appendChild(el('div', {'class': 'a'}, authorLine()));
    cover.appendChild(el('div', {'class': 's'}, sourceLine() + (doiV ? ' · DOI ' + doiV : '')));
    wrap.appendChild(cover);
    abstractClones().forEach(function (a) { var box2 = el('div', {'class': 'abs'}); box2.appendChild(a); wrap.appendChild(box2); });
    var b = doc.getElementById('article-body'), back = doc.getElementById('article-back');
    if (b) { wrap.appendChild(cleanClone(b)); }
    if (back) { wrap.appendChild(cleanClone(back)); }
    Array.prototype.forEach.call(wrap.querySelectorAll('img'), function (im) {
      im.setAttribute('src', new URL(im.getAttribute('src'), doc.baseURI).href);
    });
    var css = PDF_CSS.split('#2c5697').join(baseColor).replace('__J__', String(journal).split('\\').join('').split('"').join("'"));
    d.open();
    d.write('<!DOCTYPE html><html lang="' + escA(lang) + '"><' + 'head><meta charset="utf-8"><title>' + escA(titles[0] || slug()) + '</title><style>' + css + '</style></head><body>' + wrap.innerHTML + '</body></html>');
    d.close();
    var waits = Array.prototype.map.call(d.images, function (im) {
      return im.complete ? Promise.resolve() : new Promise(function (r) { im.onload = im.onerror = r; });
    });
    Promise.all(waits).then(function () {
      frame.contentWindow.focus();
      frame.contentWindow.print();
      setTimeout(function () { if (frame.parentNode) { frame.parentNode.removeChild(frame); } }, 60000);
    });
  }

  /* ---------- ePub 3, generated on the fly in the browser ---------- */
  function epubCss() { return EPUB_CSS_BASE.split('#2c5697').join(baseColor); }
  var EPUB_CSS_BASE = 'body{font-family:Georgia,serif;line-height:1.5;margin:5%;text-align:justify}h1{font-size:1.6em;line-height:1.25;text-align:left}h2{font-size:1.25em;color:#2c5697;border-bottom:1px solid #2c5697;text-align:left}h3,h4,h5{font-size:1em;text-align:left}img{max-width:100%;height:auto}.fig,.table-wrap{text-align:center;margin:1em 0}.caption{font-size:.85em;text-align:left}table{border-collapse:collapse;max-width:100%;font-size:.8em}td,th{border-bottom:1px solid #bbb;padding:.2em .4em;vertical-align:top}a{color:#2c5697;text-decoration:none}.ref-list .table,.ref-list .row,.ref-list .cell{display:block}.ref-label{display:none}.citation{font-size:.9em;margin:0 0 .6em;text-align:left}.meta{color:#666;font-size:.9em;text-align:left}';
  var MIME = {png: 'image/png', jpg: 'image/jpeg', jpeg: 'image/jpeg', gif: 'image/gif', svg: 'image/svg+xml', webp: 'image/webp'};
  var epubPromise = null;
  function xhtmlDoc(title, inner) {
    return '<?xml version="1.0" encoding="UTF-8"?>\n<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops" lang="' + escA(lang) + '" xml:lang="' + escA(lang) + '"><' + 'head><meta charset="utf-8"/><title>' + escA(title) + '</title><link rel="stylesheet" type="text/css" href="style.css"/></head><body>' + inner + '</body></html>';
  }
  function serialize(node) { return new XMLSerializer().serializeToString(node); }
  function collectChapters() {
    var list = [];
    var first = doc.createElement('div');
    first.appendChild(el('h1', null, titles[0] || ''));
    first.appendChild(el('p', {'class': 'meta'}, authorLine()));
    first.appendChild(el('p', {'class': 'meta'}, sourceLine() + (doiV ? ' · DOI ' + doiV : '')));
    abstractClones().forEach(function (a) { first.appendChild(a); });
    list.push({title: titles[0] || T.coverCh, root: first});
    var b = doc.getElementById('article-body'), cur = null;
    if (b) {
      Array.prototype.forEach.call(cleanClone(b).childNodes, function (n) {
        var isSec = n.nodeType === 1 && n.classList.contains('section');
        if (isSec || !cur) {
          var h = isSec ? n.querySelector('h2') : null;
          cur = {title: h ? h.textContent.replace(/\s+/g, ' ').trim() : T.abstract, root: doc.createElement('div')};
          list.push(cur);
        }
        cur.root.appendChild(n);
      });
    }
    var back = doc.getElementById('article-back');
    if (back) { list.push({title: T.refs, root: cleanClone(back)}); }
    return list.filter(function (c) { return c.root.textContent.trim() || c.root.querySelector('img'); });
  }
  function buildEpub() {
    var chapters = collectChapters(), idMap = {}, imgMap = {}, imgList = [];
    chapters.forEach(function (c, i) {
      c.file = 'chap' + (i + 1) + '.xhtml';
      if (c.root.id) { idMap[c.root.id] = c.file; }
      Array.prototype.forEach.call(c.root.querySelectorAll('[id]'), function (n) { idMap[n.id] = c.file; });
    });
    chapters.forEach(function (c) {
      Array.prototype.forEach.call(c.root.querySelectorAll('a[href]'), function (a) {
        var h = a.getAttribute('href');
        if (h.charAt(0) !== '#') { return; }
        var id = h.slice(1);
        if (idMap[id]) { a.setAttribute('href', (idMap[id] === c.file ? '' : idMap[id]) + '#' + id); } else { a.removeAttribute('href'); }
      });
      Array.prototype.forEach.call(c.root.querySelectorAll('img'), function (im) {
        var abs = new URL(im.getAttribute('src'), doc.baseURI).href;
        if (!imgMap[abs]) {
          var ext = (abs.split('?')[0].split('.').pop() || 'png').toLowerCase();
          if (!MIME[ext]) { ext = 'png'; }
          imgMap[abs] = {name: 'images/img' + (imgList.length + 1) + '.' + ext, url: abs, ext: ext};
          imgList.push(imgMap[abs]);
        }
        im.setAttribute('src', imgMap[abs].name);
        im.removeAttribute('style'); im.removeAttribute('width'); im.removeAttribute('height');
        if (!im.hasAttribute('alt')) { im.setAttribute('alt', ''); }
      });
      Array.prototype.forEach.call(c.root.querySelectorAll('[style]'), function (n) { n.removeAttribute('style'); });
      c.html = xhtmlDoc(c.title, serialize(c.root));
    });
    return Promise.all(imgList.map(function (im) {
      return fetch(im.url).then(function (r) { if (!r.ok) { throw new Error(r.status); } return r.arrayBuffer(); }).then(function (buf) { im.data = new Uint8Array(buf); });
    })).then(function () {
      var enc = new TextEncoder(), id = 'urn:uuid:' + (window.crypto && crypto.randomUUID ? crypto.randomUUID() : String(Date.now()));
      var files = [
        {name: 'mimetype', data: enc.encode('application/epub+zip')},
        {name: 'META-INF/container.xml', data: enc.encode('<?xml version="1.0" encoding="UTF-8"?>\n<container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="OEBPS/content.opf" media-type="application/oebps-package+xml"/></rootfiles></container>')},
        {name: 'OEBPS/style.css', data: enc.encode(epubCss())}
      ];
      var items = '<item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/><item id="ncx" href="toc.ncx" media-type="application/x-dtbncx+xml"/><item id="css" href="style.css" media-type="text/css"/>';
      var spine = '', li = '', np = '';
      chapters.forEach(function (c, i) {
        files.push({name: 'OEBPS/' + c.file, data: enc.encode(c.html)});
        items += '<item id="c' + i + '" href="' + c.file + '" media-type="application/xhtml+xml"/>';
        spine += '<itemref idref="c' + i + '"/>';
        li += '<li><a href="' + c.file + '">' + escA(c.title) + '</a></li>';
        np += '<navPoint id="n' + i + '" playOrder="' + (i + 1) + '"><navLabel><text>' + escA(c.title) + '</text></navLabel><content src="' + c.file + '"/></navPoint>';
      });
      imgList.forEach(function (im, i) {
        files.push({name: 'OEBPS/' + im.name, data: im.data});
        items += '<item id="i' + i + '" href="' + im.name + '" media-type="' + MIME[im.ext] + '"/>';
      });
      files.push({name: 'OEBPS/nav.xhtml', data: enc.encode(xhtmlDoc(T.toc, '<nav epub:type="toc" id="toc"><h1>' + escA(T.toc) + '</h1><ol>' + li + '</ol></nav>'))});
      files.push({name: 'OEBPS/toc.ncx', data: enc.encode('<?xml version="1.0" encoding="UTF-8"?>\n<ncx xmlns="http://www.daisy.org/z3986/2005/ncx/" version="2005-1"><' + 'head><meta name="dtb:uid" content="' + escA(id) + '"/></head><docTitle><text>' + escA(titles[0] || '') + '</text></docTitle><navMap>' + np + '</navMap></ncx>')});
      var opf = '<?xml version="1.0" encoding="UTF-8"?>\n<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="uid" xml:lang="' + escA(lang) + '"><metadata xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:identifier id="uid">' + escA(id) + '</dc:identifier><dc:title>' + escA(titles[0] || slug()) + '</dc:title><dc:language>' + escA(lang.slice(0, 2)) + '</dc:language>' +
        authors.map(function (a) { return '<dc:creator>' + escA((a.given ? a.given + ' ' : '') + a.sur) + '</dc:creator>'; }).join('') +
        (journal ? '<dc:source>' + escA(journal) + '</dc:source>' : '') + (publisher ? '<dc:publisher>' + escA(publisher) + '</dc:publisher>' : '') + (yr ? '<dc:date>' + escA(yr) + '</dc:date>' : '') +
        '<meta property="dcterms:modified">' + new Date().toISOString().replace(/\.\d+Z$/, 'Z') + '</meta></metadata><manifest>' + items + '</manifest><spine toc="ncx">' + spine + '</spine></package>';
      files.splice(3, 0, {name: 'OEBPS/content.opf', data: enc.encode(opf)});
      return {blob: buildZip(files).slice(0, undefined, 'application/epub+zip'), chapters: chapters, images: imgList};
    });
  }
  function getEpub() {
    if (!epubPromise) { epubPromise = buildEpub().catch(function (e) { epubPromise = null; throw e; }); }
    return epubPromise;
  }

  /* ---------- ePub dialog + online reader ---------- */
  var reader = el('div', {id: 'jv-reader', role: 'dialog', 'aria-modal': 'true'});
  var rBar = el('div', {'class': 'jv-rbar'});
  var rSel = el('select', {'aria-label': T.toc});
  var rPrev = el('button', {type: 'button', title: T.prev}, '‹');
  var rNext = el('button', {type: 'button', title: T.next}, '›');
  var rGet = el('button', {type: 'button'}, T.epubGet);
  var rClose = el('button', {type: 'button', title: T.close}, '✕');
  [rPrev, rSel, rNext, rGet, rClose].forEach(function (n) { rBar.appendChild(n); });
  var rFrame = el('iframe', {title: T.epubTitle});
  reader.appendChild(rBar); reader.appendChild(rFrame);
  body.insertBefore(reader, script);
  var rBook = null, rIdx = 0, rUrls = [];
  function readerHtml(i) {
    var h = rBook.chapters[i].html.replace(/^<\?xml[^>]*\?>\s*/, '').replace(/<link [^>]*style\.css"\/>/, '<style>' + epubCss() + '</style>');
    rBook.images.forEach(function (im, k) { h = h.split('src="' + im.name + '"').join('src="' + rUrls[k] + '"'); });
    return h;
  }
  function openChapter(i, anchor) {
    rIdx = Math.max(0, Math.min(rBook.chapters.length - 1, i));
    rSel.value = String(rIdx);
    rFrame.onload = function () {
      var d = rFrame.contentDocument;
      Array.prototype.forEach.call(d.querySelectorAll('a[href]'), function (a) {
        var m = /^(chap\d+\.xhtml)#?(.*)$/.exec(a.getAttribute('href'));
        if (!m) { if (/^https?:/.test(a.getAttribute('href'))) { a.target = '_blank'; a.rel = 'noopener'; } return; }
        a.addEventListener('click', function (e) {
          e.preventDefault();
          var k = -1;
          rBook.chapters.forEach(function (c, j) { if (c.file === m[1]) { k = j; } });
          if (k === rIdx) { var t = d.getElementById(m[2]); if (t) { t.scrollIntoView(); } } else if (k >= 0) { openChapter(k, m[2]); }
        });
      });
      if (anchor) { var t2 = d.getElementById(anchor); if (t2) { t2.scrollIntoView(); } }
    };
    rFrame.srcdoc = readerHtml(rIdx);
  }
  function openReader(book) {
    reader.classList.remove('jv-srv');
    rBook = book;
    rUrls.forEach(function (u) { URL.revokeObjectURL(u); });
    rUrls = book.images.map(function (im) { return URL.createObjectURL(new Blob([im.data], {type: MIME[im.ext]})); });
    rSel.textContent = '';
    book.chapters.forEach(function (c, i) { var o = el('option', {value: String(i)}, c.title.slice(0, 80)); rSel.appendChild(o); });
    reader.classList.add('jv-on');
    openChapter(0);
  }
  rSel.addEventListener('change', function () {
    if (reader.classList.contains('jv-srv')) { srvState && srvState.rendition.display(rSel.value); } else { openChapter(parseInt(rSel.value, 10)); }
  });
  rPrev.addEventListener('click', function () { if (reader.classList.contains('jv-srv')) { srvState && srvState.rendition.prev(); } else { openChapter(rIdx - 1); } });
  rNext.addEventListener('click', function () { if (reader.classList.contains('jv-srv')) { srvState && srvState.rendition.next(); } else { openChapter(rIdx + 1); } });
  rClose.addEventListener('click', function () { reader.classList.remove('jv-on'); });
  function downloadEpub(book) {
    var url = URL.createObjectURL(book.blob);
    clickDownload(url, slug() + '.epub');
    setTimeout(function () { URL.revokeObjectURL(url); }, 4000);
  }
  /* Server-built ePub (generated from the JATS at export time), read with epub.js */
  var srvUrl = (meta('jv-epub')[0] || '').replace(/^@@.*@@$/, '');
  var srvAssets = (meta('jv-assets')[0] || '').replace(/^@@.*@@$/, '');
  var rHost = el('div', {id: 'jv-rhost'});
  var rFontDn = el('button', {type: 'button', title: T.smaller}, 'A−');
  var rFontUp = el('button', {type: 'button', title: T.larger}, 'A+');
  var rTheme = el('button', {type: 'button', title: T.epubTheme}, '◐');
  rBar.insertBefore(rFontDn, rGet); rBar.insertBefore(rFontUp, rGet); rBar.insertBefore(rTheme, rGet);
  reader.appendChild(rHost);
  var srvState = null, srvPct = 110, srvThemeIdx = 0;
  var SRV_THEMES = ['day', 'sepia', 'night'];
  function loadScript(src) {
    return new Promise(function (ok, ko) {
      var s = document.createElement('script');
      s.src = src; s.onload = ok; s.onerror = function () { ko(new Error('load ' + src)); };
      document.head.appendChild(s);
    });
  }
  function loadEpubJs() {
    if (window.ePub) { return Promise.resolve(); }
    var p = window.JSZip ? Promise.resolve() : loadScript(srvAssets + 'jszip.min.js');
    return p.then(function () { return loadScript(srvAssets + 'epub.min.js'); });
  }
  function flattenToc(items, depth, out) {
    items.forEach(function (it) {
      out.push({href: it.href, label: new Array(depth + 1).join('\u2003') + (it.label || '').trim()});
      if (it.subitems && it.subitems.length) { flattenToc(it.subitems, depth + 1, out); }
    });
    return out;
  }
  function applySrvStyle() {
    if (!srvState) { return; }
    srvState.rendition.themes.fontSize(srvPct + '%');
    srvState.rendition.themes.select(SRV_THEMES[srvThemeIdx]);
    var bg = {day: '#fff', sepia: '#f4ecd8', night: '#1b1b1b'}[SRV_THEMES[srvThemeIdx]];
    rHost.style.background = bg;
  }
  function openServerReader() {
    reader.classList.add('jv-on', 'jv-srv');
    if (srvState) { return Promise.resolve(); }
    rHost.textContent = T.epubBusy;
    return Promise.all([loadEpubJs(), fetch(srvUrl, {credentials: 'same-origin'}).then(function (r) {
      if (!r.ok) { throw new Error('http ' + r.status); }
      return r.arrayBuffer();
    })]).then(function (res) {
      rHost.textContent = '';
      var book = ePub(res[1]);
      var rendition = book.renderTo(rHost, {width: '100%', height: '100%', flow: 'paginated', spread: 'none'});
      rendition.themes.register('day', {body: {background: '#fff', color: '#1a1a1a'}, a: {color: '#0b4a8f'}});
      rendition.themes.register('sepia', {body: {background: '#f4ecd8', color: '#3b2f1e'}, a: {color: '#7a3e00'}});
      rendition.themes.register('night', {body: {background: '#1b1b1b', color: '#e8e8e8'}, a: {color: '#8ec5ff'}});
      srvState = {book: book, rendition: rendition};
      applySrvStyle();
      rendition.on('relocated', function (loc) {
        var h = loc && loc.start && loc.start.href;
        if (!h) { return; }
        Array.prototype.forEach.call(rSel.options, function (o) { if (o.value.split('#')[0] === h) { rSel.value = o.value; } });
      });
      book.loaded.navigation.then(function (nav) {
        rSel.textContent = '';
        flattenToc(nav.toc, 0, []).forEach(function (t) { rSel.appendChild(el('option', {value: t.href}, t.label)); });
      });
      return rendition.display();
    }).catch(function () {
      rHost.textContent = T.epubFail;
      srvState = null;
    });
  }
  rFontDn.addEventListener('click', function () { srvPct = Math.max(70, srvPct - 10); applySrvStyle(); });
  rFontUp.addEventListener('click', function () { srvPct = Math.min(250, srvPct + 10); applySrvStyle(); });
  rTheme.addEventListener('click', function () { srvThemeIdx = (srvThemeIdx + 1) % SRV_THEMES.length; applySrvStyle(); });
  document.addEventListener('keydown', function (e) {
    if (!srvState || !reader.classList.contains('jv-on')) { return; }
    if (e.key === 'ArrowRight') { srvState.rendition.next(); } else if (e.key === 'ArrowLeft') { srvState.rendition.prev(); } else if (e.key === 'Escape') { reader.classList.remove('jv-on'); }
  });
  rGet.addEventListener('click', function () {
    if (srvUrl) { clickDownload(srvUrl, slug() + '.epub'); } else { getEpub().then(downloadEpub); }
  });
  function showEpub() {
    box.textContent = '';
    box.appendChild(el('h3', null, T.epubTitle));
    var msg = el('p', null, '');
    var read = el('button', {type: 'button'}, T.epubRead);
    var get = el('button', {type: 'button'}, T.epubGet);
    var c = el('button', {type: 'button'}, T.close);
    function run(fn) {
      msg.textContent = T.epubBusy;
      getEpub().then(function (bk) { msg.textContent = ''; modal.classList.remove('jv-on'); fn(bk); }, function () { msg.textContent = T.epubFail; });
    }
    read.addEventListener('click', function () {
      if (srvUrl) { modal.classList.remove('jv-on'); openServerReader(); } else { run(openReader); }
    });
    get.addEventListener('click', function () {
      if (srvUrl) { modal.classList.remove('jv-on'); clickDownload(srvUrl, slug() + '.epub'); } else { run(downloadEpub); }
    });
    c.addEventListener('click', function () { modal.classList.remove('jv-on'); });
    [read, get, c].forEach(function (n) { box.appendChild(n); });
    box.appendChild(msg);
    modal.classList.add('jv-on');
  }

  /* ---------- language (abstract blocks) ---------- */
  var blocks = [];
  if (front) {
    Array.prototype.forEach.call(front.querySelectorAll('.jv-abs'), function (b) { blocks.push(b); });
    if (!blocks.length) {
      Array.prototype.forEach.call(front.querySelectorAll('.callout-title'), function (t) {
        var b = t.closest('.metadata');
        if (b && blocks.indexOf(b) < 0) { blocks.push(b); }
      });
    }
  }
  var langIdx = 0;
  function applyLang() { blocks.forEach(function (b, i) { b.style.display = i === langIdx ? '' : 'none'; }); }
  function cycleLang() { langIdx = (langIdx + 1) % blocks.length; applyLang(); }
  if (blocks.length < 2) { buttons.lang.style.display = 'none'; } else { applyLang(); }

  /* ---------- scale menu, index and icons with font size and page width ---------- */
  function syncSticky() {
    var st = doc.documentElement.style;
    st.setProperty('--jv-head-h', head.offsetHeight + 'px');
    st.setProperty('--jv-menu-h', menu.offsetHeight + 'px');
  }
  window.addEventListener('load', syncSticky);
  function updateScale() {
    var pw = (doc.getElementById('jv-page') || {}).offsetWidth || 1140;
    var s = ((typeof size === 'number' ? size : 100) / 100) * Math.max(0.7, Math.min(1.4, pw / 1680));
    doc.documentElement.style.setProperty('--jv-s', s.toFixed(3));
    syncSticky();
  }
  window.addEventListener('resize', updateScale);
  updateScale();

  /* ---------- custom tooltips (native title tooltips cannot be scaled) ---------- */
  var tip = el('div', {id: 'jv-tip'});
  doc.body.appendChild(tip);
  function tipTarget(t) { return t && t.closest ? t.closest('[title],[data-jv-tip]') : null; }
  function tipShow(e) {
    var t = tipTarget(e.target);
    if (!t || t.closest('#jv-pop')) { return; }
    if (t.hasAttribute('title')) { t.setAttribute('data-jv-tip', t.getAttribute('title')); t.removeAttribute('title'); }
    var txt = t.getAttribute('data-jv-tip');
    if (!txt) { return; }
    tip.textContent = txt;
    tip.classList.add('jv-on');
    var r = t.getBoundingClientRect(), w = tip.offsetWidth;
    tip.style.left = Math.max(4, Math.min(window.innerWidth - w - 4, r.left + r.width / 2 - w / 2)) + 'px';
    var top = r.bottom + 6;
    if (top + tip.offsetHeight > window.innerHeight) { top = r.top - tip.offsetHeight - 6; }
    tip.style.top = Math.max(4, top) + 'px';
  }
  function tipHide() { tip.classList.remove('jv-on'); }
  doc.addEventListener('mouseover', tipShow);
  doc.addEventListener('focusin', tipShow);
  doc.addEventListener('mouseout', tipHide);
  doc.addEventListener('focusout', tipHide);
  doc.addEventListener('click', tipHide, true);
  /* ---------- keyboard ---------- */
  doc.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      if (ov.classList.contains('jv-on')) { ov.classList.remove('jv-on'); }
      else if (modal.classList.contains('jv-on')) { modal.classList.remove('jv-on'); }
      else if (sBox.classList.contains('jv-on')) { toggleSearch(false); }
    } else if (ov.classList.contains('jv-on')) {
      if (e.key === 'ArrowLeft') { showImg(ovIdx - 1); } else if (e.key === 'ArrowRight') { showImg(ovIdx + 1); }
    } else if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'f') {
      e.preventDefault(); toggleSearch(true);
    }
  });
})();
