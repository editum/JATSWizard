(function () {
  var app = document.getElementById('jv-epub-app');
  var view = document.getElementById('jv-epub-view');
  if (!app || !view || !window.ePub) { return; }

  var themeBackgrounds = {day: '#fcfbfa', sepia: '#f4ecd8', night: '#1b1b1b', white: '#ffffff'};
  var highlightColors = {yellow: '#fff08a', pink: '#ffc1d9', blue: '#a9dcff', mint: '#b8ead1'};
  var fonts = {
    literata: 'Literata, "Bookerly", Georgia, "Times New Roman", serif',
    bookerly: 'Bookerly, Literata, Georgia, "Times New Roman", serif',
    serif: 'Georgia, "Times New Roman", serif',
    'sans-serif': 'Arial, Helvetica, sans-serif',
    monospace: 'monospace'
  };
  var allowedThemes = ['day', 'sepia', 'night', 'white'];
  var allowedFonts = ['literata', 'bookerly', 'serif', 'sans-serif', 'monospace'];
  var storageKey = 'jatsWizard.reader.' + new URL(app.getAttribute('data-epub'), window.location.href).pathname;
  var messages = app.dataset;
  var tocPanel = app.querySelector('#jv-epub-toc');
  var searchPanel = app.querySelector('#jv-epub-search');
  var settingsPanel = app.querySelector('#jv-epub-settings');
  var selectionMenu = app.querySelector('.jv-epub-selection-menu');
  var searchForm = app.querySelector('[data-search-form]');
  var searchInput = app.querySelector('[data-search-query]');
  var searchStatus = app.querySelector('[data-search-status]');
  var searchResults = app.querySelector('[data-search-results]');
  var searchCurrent = app.querySelector('[data-search-current]');
  var searchPrevious = app.querySelector('[data-act=search-prev]');
  var searchNext = app.querySelector('[data-act=search-next]');
  var progress = app.querySelector('[data-progress]');
  var progressLabel = app.querySelector('[data-progress-label]');
  var fontSizeOutput = app.querySelector('[data-font-size]');
  var tocList = app.querySelector('[data-toc-list]');
  var notesList = app.querySelector('[data-notes-list]');
  var bookmarkButton = app.querySelector('[data-act=bookmark]');
  var bookmarkGoButton = app.querySelector('[data-act=bookmark-go]');
  var returnReadingButton = app.querySelector('[data-act=return-reading]');
  var settingsButton = app.querySelector('[data-act=settings]');
  var tocButtons = app.querySelectorAll('[data-act=toc-toggle]');
  var searchButtons = app.querySelectorAll('[data-act=search-toggle]');
  var fontSelect = app.querySelector('[data-act=font]');
  var highlightSelect = app.querySelector('[data-highlight-color]');
  var stylusToggle = app.querySelector('[data-act=stylus]');

  var theme = 'day';
  var font = 'bookerly';
  var fontSize = 110;
  var highlightColor = 'yellow';
  var paged = false;
  var lastLocation = null;
  var returnCfi = null;
  var pendingReferenceId = null;
  var highlightedReferenceElement = null;
  var highlightedReferenceBackground = '';
  var highlightedReferencePriority = '';
  var locationsReady = false;
  var ebookBuffer;
  var book;
  var rendition;
  var searchBook;
  var searchRendition;
  var searchEngineHost;
  var searchEnginePromise;
  var isSearching = false;
  var searchMatches = [];
  var searchMatchIndex = -1;
  var isTurning = false;
  var suppressClickUntil = 0;
  var selectedCfi = null;
  var selectedText = '';
  var stored = readStoredState();

  function defaultState() {
    return {
      bookmark: null,
      location: null,
      highlights: [],
      notes: [],
      preferences: {}
    };
  }

  function readStoredState() {
    try {
      var raw = window.localStorage.getItem(storageKey);
      if (!raw) { return defaultState(); }
      var parsed = JSON.parse(raw);
      if (!parsed || typeof parsed !== 'object') { throw new Error('Stored reader data is not an object.'); }
      var state = defaultState();
      state.bookmark = typeof parsed.bookmark === 'string' ? parsed.bookmark : null;
      state.location = typeof parsed.location === 'string' ? parsed.location : null;
      state.highlights = Array.isArray(parsed.highlights) ? parsed.highlights : [];
      state.notes = Array.isArray(parsed.notes) ? parsed.notes : [];
      state.preferences = parsed.preferences && typeof parsed.preferences === 'object' ? parsed.preferences : {};
      return state;
    } catch (error) {
      console.error('Could not read the saved ePub reader state.', error);
      announce(messages.storageError);
      return defaultState();
    }
  }

  function persistState() {
    try {
      window.localStorage.setItem(storageKey, JSON.stringify(stored));
      return true;
    } catch (error) {
      console.error('Could not save the ePub reader state.', error);
      announce(messages.storageError);
      return false;
    }
  }

  function announce(message) {
    if (progressLabel && message) { progressLabel.textContent = message; }
  }

  function savePreferences() {
    stored.preferences = {
      theme: theme,
      font: font,
      fontSize: fontSize,
      highlightColor: highlightColor,
      paged: paged,
      stylus: stylusToggle.checked
    };
    persistState();
  }

  function applySavedPreferences() {
    var preferences = stored.preferences || {};
    theme = allowedThemes.indexOf(preferences.theme) !== -1 ? preferences.theme : theme;
    font = allowedFonts.indexOf(preferences.font) !== -1 ? preferences.font : font;
    fontSize = typeof preferences.fontSize === 'number' ? Math.max(70, Math.min(250, preferences.fontSize)) : fontSize;
    highlightColor = highlightColors[preferences.highlightColor] ? preferences.highlightColor : highlightColor;
    paged = preferences.paged === true;
    stylusToggle.checked = preferences.stylus === true;
    app.dataset.theme = theme;
    fontSelect.value = font;
    highlightSelect.value = highlightColor;
  }

  function closePanels() {
    settingsPanel.hidden = true;
    tocPanel.hidden = true;
    searchPanel.hidden = true;
    settingsButton.setAttribute('aria-expanded', 'false');
    Array.prototype.forEach.call(tocButtons, function (button) { button.setAttribute('aria-expanded', 'false'); });
    Array.prototype.forEach.call(searchButtons, function (button) { button.setAttribute('aria-expanded', 'false'); });
  }

  function setPanel(panel, buttonGroup, open) {
    closePanels();
    if (!open) { return; }
    panel.hidden = false;
    Array.prototype.forEach.call(buttonGroup, function (button) { button.setAttribute('aria-expanded', 'true'); });
  }

  function updateBookmarkControls() {
    bookmarkButton.setAttribute('aria-pressed', stored.bookmark ? 'true' : 'false');
    bookmarkGoButton.disabled = !stored.bookmark;
  }

  function updateProgress(location) {
    if (!location || !location.start || !progress) { return; }
    var percentage = locationsReady ? book.locations.percentageFromCfi(location.start.cfi) : location.start.percentage;
    if (typeof percentage !== 'number' || percentage < 0) { percentage = 0; }
    var value = Math.max(0, Math.min(100, Math.round(percentage * 100)));
    progress.value = value;
    progressLabel.textContent = value + '%';
  }

  function updateTocCurrent(href) {
    Array.prototype.forEach.call(tocList.querySelectorAll('button[data-href]'), function (button) {
      var sameChapter = button.dataset.href.split('#')[0] === href;
      if (sameChapter) {
        button.setAttribute('aria-current', 'location');
      } else {
        button.removeAttribute('aria-current');
      }
    });
  }

  function flattenToc(items, depth) {
    items.forEach(function (item) {
      var button = document.createElement('button');
      button.type = 'button';
      button.dataset.act = 'toc-entry';
      button.dataset.href = item.href;
      button.textContent = (item.label || '').trim();
      button.style.paddingInlineStart = (0.6 + depth * 0.9) + 'rem';
      tocList.appendChild(button);
      if (item.subitems && item.subitems.length) { flattenToc(item.subitems, depth + 1); }
    });
  }

  function renderNotes() {
    notesList.textContent = '';
    if (!stored.notes.length) {
      var empty = document.createElement('li');
      empty.textContent = messages.noNotes;
      notesList.appendChild(empty);
      return;
    }
    stored.notes.forEach(function (note, index) {
      var item = document.createElement('li');
      var button = document.createElement('button');
      button.type = 'button';
      button.dataset.act = 'note-go';
      button.dataset.noteIndex = String(index);
      button.textContent = note.text;
      item.appendChild(button);
      notesList.appendChild(item);
    });
  }

  function applyStoredHighlights() {
    stored.highlights.forEach(function (highlight) {
      if (!highlight || typeof highlight.cfi !== 'string' || !highlightColors[highlight.color]) { return; }
      rendition.annotations.highlight(highlight.cfi, {color: highlight.color}, null, 'jv-epub-highlight', {
        fill: highlightColors[highlight.color],
        'fill-opacity': '0.42',
        'mix-blend-mode': 'multiply'
      });
    });
  }

  function applyReferenceHighlight(contents) {
    if (!pendingReferenceId || !contents || !contents.document) { return; }
    var target = contents.document.getElementById(pendingReferenceId) ||
      contents.document.getElementsByName(pendingReferenceId)[0];
    if (!target) { return; }
    var reference = target.closest('li, p, div, section, tr') || target.parentElement || target;
    if (highlightedReferenceElement && highlightedReferenceElement !== reference) {
      highlightedReferenceElement.style.setProperty('background-color', highlightedReferenceBackground, highlightedReferencePriority);
    }
    if (highlightedReferenceElement !== reference) {
      highlightedReferenceBackground = reference.style.getPropertyValue('background-color');
      highlightedReferencePriority = reference.style.getPropertyPriority('background-color');
    }
    highlightedReferenceElement = reference;
    reference.style.setProperty('background-color', 'rgba(255, 211, 92, 0.24)', 'important');
    reference.style.transition = 'background-color 180ms ease';
    pendingReferenceId = null;
  }

  function applySettings() {
    rendition.themes.fontSize(fontSize + '%');
    rendition.themes.font(fonts[font]);
    rendition.themes.select(theme);
    view.style.background = themeBackgrounds[theme];
    app.dataset.theme = theme;
    fontSizeOutput.textContent = fontSize + '%';
    Array.prototype.forEach.call(app.querySelectorAll('[data-theme]'), function (button) {
      button.setAttribute('aria-pressed', button.getAttribute('data-theme') === theme ? 'true' : 'false');
    });
    Array.prototype.forEach.call(app.querySelectorAll('[data-act=flow]'), function (button) {
      button.setAttribute('aria-pressed', paged ? 'true' : 'false');
    });
    if (rendition) {
      rendition.getContents().forEach(applyReaderStyles);
    }
    savePreferences();
  }

  function applyDefaultReaderStyles() {
    rendition.themes.default({
      body: {
        'box-sizing': 'border-box !important',
        'max-width': 'none !important',
        margin: '0 !important',
        padding: (paged ? '0' : '1rem') + ' !important'
      }
    });
  }

  function applyReaderStyles(contents) {
    var contentDocument = contents && contents.document;
    if (!contentDocument || !contentDocument.body) { return; }
    contentDocument.body.style.setProperty('box-sizing', 'border-box', 'important');
    contentDocument.body.style.setProperty('max-width', 'none', 'important');
    contentDocument.body.style.setProperty('margin', '0', 'important');
    contentDocument.body.style.setProperty('padding', paged ? '0' : '1rem', 'important');
    contentDocument.body.style.setProperty('line-height', '1.62', 'important');
    Array.prototype.forEach.call(contentDocument.querySelectorAll('p'), function (paragraph) {
      if ((paragraph.dataset.jvReaderAlign !== 'true' && paragraph.style.textAlign) ||
        paragraph.classList.contains('attrib') ||
        paragraph.classList.contains('al-center') || paragraph.classList.contains('al-right')) { return; }
      paragraph.style.setProperty('text-align', 'justify', 'important');
      paragraph.dataset.jvReaderAlign = 'true';
    });
  }

  function turnPage(direction) {
    if (!paged || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      rendition[direction]();
      return;
    }
    if (isTurning) { return; }
    isTurning = true;
    var sheet = app.querySelector('.jv-epub-page-turn');
    sheet.className = 'jv-epub-page-turn is-turning is-turning-' + direction;
    window.setTimeout(function () { rendition[direction](); }, 180);
    window.setTimeout(function () {
      sheet.className = 'jv-epub-page-turn';
      isTurning = false;
    }, 450);
  }

  function handleReaderClick(event, renditionInstance, documentRef) {
    if (Date.now() < suppressClickUntil) {
      event.preventDefault();
      return;
    }
    var target = event.target;
    if (target.closest && target.closest('a, button, input, select, textarea, [role="button"]')) { return; }
    var selection = documentRef.getSelection();
    if (selection && !selection.isCollapsed) { return; }
    var width = documentRef.documentElement.clientWidth;
    if (!width) { return; }
    if (event.clientX <= width * 0.15) {
      turnPage('prev');
    } else if (event.clientX >= width * 0.85) {
      turnPage('next');
    } else if (event.clientX >= width * 0.3 && event.clientX <= width * 0.7) {
      closePanels();
    }
  }

  function handleKeydown(event, renditionInstance) {
    if (event.target && /^(INPUT|SELECT|TEXTAREA|BUTTON|A)$/.test(event.target.tagName)) { return; }
    if (event.key === 'Escape') {
      closePanels();
      selectionMenu.hidden = true;
    } else if (event.key === 'ArrowRight') {
      event.preventDefault();
      turnPage('next');
    } else if (event.key === 'ArrowLeft') {
      event.preventDefault();
      turnPage('prev');
    }
  }

  function showSelectionMenu(contents, iframe) {
    var selection = contents.document.getSelection();
    if (!selection || selection.isCollapsed || !selection.toString().trim()) {
      selectionMenu.hidden = true;
      selectedCfi = null;
      selectedText = '';
      return;
    }
    var range = selection.getRangeAt(0);
    selectedCfi = contents.cfiFromRange(range);
    selectedText = selection.toString().trim();
    var selectionRect = range.getBoundingClientRect();
    var frameRect = iframe.getBoundingClientRect();
    var deviceRect = app.querySelector('.jv-epub-device').getBoundingClientRect();
    selectionMenu.hidden = false;
    var left = frameRect.left + selectionRect.left - deviceRect.left;
    var top = frameRect.top + selectionRect.bottom - deviceRect.top + 8;
    selectionMenu.style.left = Math.max(8, Math.min(left, deviceRect.width - selectionMenu.offsetWidth - 8)) + 'px';
    selectionMenu.style.top = Math.max(8, Math.min(top, deviceRect.height - selectionMenu.offsetHeight - 8)) + 'px';
    selectionMenu.hidden = false;
    app.querySelector('[data-lookup=rae]').href = 'https://dle.rae.es/' + encodeURIComponent(selectedText);
    app.querySelector('[data-lookup=wikipedia]').href = 'https://es.wikipedia.org/w/index.php?search=' + encodeURIComponent(selectedText);
    if (stylusToggle.checked) { addHighlight(selectedCfi, highlightColor); }
  }

  function addHighlight(cfi, color, noteText) {
    if (!cfi || !highlightColors[color]) { return; }
    var existing = stored.highlights.some(function (item) { return item.cfi === cfi; });
    if (!existing) {
      stored.highlights.push({cfi: cfi, color: color});
      rendition.annotations.highlight(cfi, {color: color}, null, 'jv-epub-highlight', {
        fill: highlightColors[color],
        'fill-opacity': '0.42',
        'mix-blend-mode': 'multiply'
      });
    }
    if (noteText) {
      stored.notes.push({cfi: cfi, text: noteText, excerpt: selectedText, color: color});
      renderNotes();
    }
    persistState();
    selectionMenu.hidden = true;
  }

  function bindContentEvents(renderedView) {
    var contents = renderedView.contents;
    var contentDocument = contents.document;
    var iframe = renderedView.iframe;
    var touchStart = null;
    contentDocument.addEventListener('click', function (event) {
      var targetElement = event.target.nodeType === 1 ? event.target : event.target.parentElement;
      var link = targetElement && targetElement.closest('a[href]');
      if (!link) { return; }
      var href = link.getAttribute('href');
      if (!href || href.indexOf('#') === -1 || /^(?:[a-z][a-z0-9+.-]*:|\/\/)/i.test(href)) { return; }

      try {
        var range = contentDocument.caretRangeFromPoint
          ? contentDocument.caretRangeFromPoint(event.clientX, event.clientY)
          : null;
        if (!range && contentDocument.caretPositionFromPoint) {
          var caret = contentDocument.caretPositionFromPoint(event.clientX, event.clientY);
          if (caret) {
            range = contentDocument.createRange();
            range.setStart(caret.offsetNode, caret.offset);
            range.collapse(true);
          }
        }
        if (!range || !link.contains(range.startContainer)) {
          throw new Error('The clicked citation position is unavailable.');
        }
        range.collapse(true);
        returnCfi = contents.cfiFromRange(range);
        if (!returnCfi) { throw new Error('The clicked citation has no EPUB location.'); }
        pendingReferenceId = decodeURIComponent(href.slice(href.indexOf('#') + 1));
        if (highlightedReferenceElement) {
          highlightedReferenceElement.style.setProperty('background-color', highlightedReferenceBackground, highlightedReferencePriority);
          highlightedReferenceElement = null;
        }
      } catch (error) {
        console.error('Could not save the ePub citation position.', error);
        returnCfi = null;
        pendingReferenceId = null;
      }
      returnReadingButton.hidden = !returnCfi;
    }, true);
    contentDocument.addEventListener('click', function (event) {
      handleReaderClick(event, rendition, contentDocument);
    });
    contentDocument.addEventListener('keydown', function (event) {
      handleKeydown(event, rendition);
    });
    contentDocument.addEventListener('mouseup', function () {
      window.setTimeout(function () { showSelectionMenu(contents, iframe); }, 0);
    });
    contentDocument.addEventListener('touchstart', function (event) {
      if (event.touches.length !== 1) { touchStart = null; return; }
      touchStart = {x: event.touches[0].clientX, y: event.touches[0].clientY, target: event.target};
    }, {passive: true});
    contentDocument.addEventListener('touchend', function (event) {
      window.setTimeout(function () { showSelectionMenu(contents, iframe); }, 0);
      if (!paged || !touchStart || event.changedTouches.length !== 1) { touchStart = null; return; }
      var target = touchStart.target;
      var touch = event.changedTouches[0];
      var deltaX = touch.clientX - touchStart.x;
      var deltaY = touch.clientY - touchStart.y;
      touchStart = null;
      if (target.closest && target.closest('a, button, input, select, textarea, [role="button"]')) { return; }
      if (Math.abs(deltaX) < 50 || Math.abs(deltaX) < Math.abs(deltaY) * 1.25) { return; }
      event.preventDefault();
      suppressClickUntil = Date.now() + 600;
      turnPage(deltaX < 0 ? 'next' : 'prev');
    }, {passive: false});
  }

  function appendMatches(contents, query, sectionHref, matches) {
    var contentDocument = contents.document;
    if (!contentDocument || !contentDocument.body) { return; }
    var walker = contentDocument.createTreeWalker(contentDocument.body, NodeFilter.SHOW_TEXT);
    var node;
    var lowerQuery = query.toLocaleLowerCase();
    while ((node = walker.nextNode()) && matches.length < 100) {
      if (!node.nodeValue || !node.nodeValue.trim() || (node.parentElement && node.parentElement.closest('script, style, noscript'))) { continue; }
      var lowerText = node.nodeValue.toLocaleLowerCase();
      var offset = 0;
      var matchAt;
      while ((matchAt = lowerText.indexOf(lowerQuery, offset)) !== -1 && matches.length < 100) {
        var range = contentDocument.createRange();
        range.setStart(node, matchAt);
        range.setEnd(node, matchAt + query.length);
        var excerptStart = Math.max(0, matchAt - 55);
        var excerptEnd = Math.min(node.nodeValue.length, matchAt + query.length + 75);
        matches.push({
          cfi: contents.cfiFromRange(range),
          href: sectionHref,
          excerpt: node.nodeValue.slice(excerptStart, excerptEnd).trim()
        });
        offset = matchAt + Math.max(query.length, 1);
      }
    }
  }

  function createSearchRenderer() {
    if (searchEnginePromise) { return searchEnginePromise; }
    searchEngineHost = document.createElement('div');
    searchEngineHost.className = 'jv-epub-search-engine';
    searchEngineHost.setAttribute('aria-hidden', 'true');
    document.body.appendChild(searchEngineHost);
    searchBook = ePub(ebookBuffer.slice(0));
    searchEnginePromise = searchBook.loaded.spine.then(function () {
      searchRendition = searchBook.renderTo(searchEngineHost, {
        width: '100%',
        height: '100%',
        flow: 'paginated',
        spread: 'none',
        gap: 0,
        allowScriptedContent: false
      });
    }).then(function () {
      return searchRendition;
    });
    return searchEnginePromise;
  }

  function searchWholeBook(query) {
    if (isSearching) { return; }
    isSearching = true;
    searchMatches = [];
    searchMatchIndex = -1;
    updateSearchNavigation();
    searchStatus.textContent = messages.searching;
    searchResults.textContent = '';
    createSearchRenderer().then(async function () {
      var matches = [];
      var sections = searchBook.spine.items;
      for (var index = 0; index < sections.length && matches.length < 100; index++) {
        var section = sections[index];
        await searchRendition.display(section.href);
        searchRendition.getContents().forEach(function (contents) {
          appendMatches(contents, query, section.href, matches);
        });
      }
      return matches;
    }).then(function (matches) {
      isSearching = false;
      searchMatches = matches;
      if (!matches.length) {
        searchStatus.textContent = messages.noResults;
        return;
      }
      searchStatus.textContent = matches.length + ' ' + (messages.results || 'results');
      updateSearchNavigation();
      matches.forEach(function (match, index) {
        var item = document.createElement('li');
        var button = document.createElement('button');
        button.type = 'button';
        button.dataset.act = 'search-result';
        button.dataset.cfi = match.cfi;
        button.textContent = (index + 1) + '. ' + match.excerpt;
        item.appendChild(button);
        searchResults.appendChild(item);
      });
    }).catch(function (error) {
      isSearching = false;
      console.error('Could not search the ePub contents.', error);
      searchStatus.textContent = messages.searchError;
    });
  }

  function updateSearchNavigation() {
    var total = searchMatches.length;
    searchCurrent.textContent = (searchMatchIndex < 0 ? 0 : searchMatchIndex + 1) + ' / ' + total;
    searchPrevious.disabled = total < 2;
    searchNext.disabled = total < 2;
    Array.prototype.forEach.call(searchResults.querySelectorAll('button[data-act=search-result]'), function (button, index) {
      if (index === searchMatchIndex) {
        button.setAttribute('aria-current', 'location');
      } else {
        button.removeAttribute('aria-current');
      }
    });
  }

  function showSearchMatch(index) {
    if (!searchMatches.length) { return; }
    searchMatchIndex = (index + searchMatches.length) % searchMatches.length;
    var match = searchMatches[searchMatchIndex];
    searchCurrent.textContent = (searchMatchIndex + 1) + ' / ' + searchMatches.length;
    Array.prototype.forEach.call(searchResults.querySelectorAll('button[data-act=search-result]'), function (button, buttonIndex) {
      if (buttonIndex === searchMatchIndex) {
        button.setAttribute('aria-current', 'location');
      } else {
        button.removeAttribute('aria-current');
      }
    });
    rendition.display(match.cfi);
  }

  function saveBookmark() {
    if (!lastLocation || !lastLocation.start) {
      announce(messages.bookmarkEmpty);
      return;
    }
    stored.bookmark = lastLocation.start.cfi;
    updateBookmarkControls();
    if (persistState()) { announce(messages.bookmarkSaved); }
  }

  applySavedPreferences();
  updateBookmarkControls();
  renderNotes();

  fetch(app.getAttribute('data-epub'), {credentials: 'same-origin'}).then(function (response) {
    if (!response.ok) { throw new Error('ePub request failed: ' + response.status); }
    return response.arrayBuffer();
  }).then(function (buffer) {
    ebookBuffer = buffer;
    view.textContent = '';
    book = ePub(buffer);
    rendition = book.renderTo(view, {
      width: '100%',
      height: '100%',
      flow: paged ? 'paginated' : 'scrolled-doc',
      spread: 'none',
      gap: 0,
      allowScriptedContent: false
    });

    applyDefaultReaderStyles();
    rendition.themes.register('day', {body: {background: themeBackgrounds.day, color: '#2c2c2c'}, a: {color: '#0b4a8f'}});
    rendition.themes.register('sepia', {body: {background: themeBackgrounds.sepia, color: '#3b2f1e'}, a: {color: '#7a3e00'}});
    rendition.themes.register('night', {body: {background: themeBackgrounds.night, color: '#e8e8e8'}, a: {color: '#8ec5ff'}});
    rendition.themes.register('white', {body: {background: themeBackgrounds.white, color: '#111111'}, a: {color: '#0645ad'}});

    book.loaded.navigation.then(function (navigation) {
      flattenToc(navigation.toc, 0);
    }).catch(function (error) {
      console.error('Could not load the ePub table of contents.', error);
    });

    rendition.on('rendered', function (section, renderedView) {
      bindContentEvents(renderedView);
      applyReaderStyles(renderedView.contents);
      applyReferenceHighlight(renderedView.contents);
    });

    rendition.on('relocated', function (location) {
      lastLocation = location;
      window.setTimeout(function () {
        rendition.getContents().forEach(applyReaderStyles);
      }, 0);
      if (location && location.start) {
        stored.location = location.start.cfi;
        persistState();
        updateTocCurrent(location.start.href);
      }
      updateProgress(location);
      updateBookmarkControls();
      rendition.getContents().forEach(applyReferenceHighlight);
    });

    app.addEventListener('click', function (event) {
      var control = event.target.closest('[data-act]');
      if (!control) { return; }
      var action = control.getAttribute('data-act');
      if (action === 'settings') {
        setPanel(settingsPanel, [settingsButton], settingsPanel.hidden);
      } else if (action === 'toc-toggle') {
        setPanel(tocPanel, tocButtons, tocPanel.hidden);
      } else if (action === 'search-toggle') {
        setPanel(searchPanel, searchButtons, searchPanel.hidden);
        if (!searchPanel.hidden) { searchInput.focus(); }
      } else if (action === 'prev' || action === 'next') {
        selectionMenu.hidden = true;
        turnPage(action);
      } else if (action === 'return-reading' && returnCfi) {
        var targetCfi = returnCfi;
        returnCfi = null;
        returnReadingButton.hidden = true;
        closePanels();
        rendition.display(targetCfi).catch(function (error) {
          console.error('Could not return to the ePub citation position.', error);
          returnReadingButton.hidden = false;
          returnCfi = targetCfi;
        });
      } else if (action === 'smaller' && fontSize > 70) {
        fontSize = Math.max(70, fontSize - 10);
        applySettings();
      } else if (action === 'larger' && fontSize < 250) {
        fontSize = Math.min(250, fontSize + 10);
        applySettings();
      } else if (action === 'theme') {
        theme = control.dataset.theme;
        applySettings();
      } else if (action === 'flow') {
        var currentCfi = lastLocation && lastLocation.start ? lastLocation.start.cfi : stored.location;
        paged = !paged;
        rendition.flow(paged ? 'paginated' : 'scrolled-doc');
        applyDefaultReaderStyles();
        rendition.getContents().forEach(applyReaderStyles);
        if (currentCfi) {
          rendition.display(currentCfi).catch(function (error) {
            console.error('Could not restore the reading position after changing ePub layout.', error);
          });
        }
        control.setAttribute('aria-pressed', paged ? 'true' : 'false');
        view.style.overflow = paged ? 'hidden' : 'auto';
        savePreferences();
      } else if (action === 'toc-entry') {
        closePanels();
        rendition.display(control.dataset.href);
      } else if (action === 'bookmark') {
        saveBookmark();
      } else if (action === 'bookmark-go' && stored.bookmark) {
        closePanels();
        rendition.display(stored.bookmark);
      } else if (action === 'search-result') {
        var resultIndex = Array.prototype.indexOf.call(searchResults.querySelectorAll('button[data-act=search-result]'), control);
        searchMatchIndex = resultIndex;
        updateSearchNavigation();
        rendition.display(control.dataset.cfi);
      } else if (action === 'search-prev') {
        showSearchMatch(searchMatchIndex <= 0 ? searchMatches.length - 1 : searchMatchIndex - 1);
      } else if (action === 'search-next') {
        showSearchMatch(searchMatchIndex + 1);
      } else if (action === 'note-go') {
        var note = stored.notes[Number(control.dataset.noteIndex)];
        if (note) { closePanels(); rendition.display(note.cfi); }
      } else if (action === 'highlight') {
        addHighlight(selectedCfi, control.dataset.color);
      } else if (action === 'note') {
        if (!selectedCfi) { return; }
        var noteText = window.prompt(messages.notePrompt, selectedText);
        if (noteText && noteText.trim()) { addHighlight(selectedCfi, highlightColor, noteText.trim()); }
      }
    });

    app.addEventListener('change', function (event) {
      var control = event.target;
      if (control.matches('[data-act=font]')) {
        font = control.value;
        applySettings();
      } else if (control.matches('[data-highlight-color]')) {
        highlightColor = control.value;
        savePreferences();
      } else if (control.matches('[data-act=stylus]')) {
        savePreferences();
        if (control.checked) { announce(messages.stylusActive); }
      }
    });

    searchForm.addEventListener('submit', function (event) {
      event.preventDefault();
      var query = searchInput.value.trim();
      if (query.length >= 2) { searchWholeBook(query); }
    });

    document.addEventListener('keydown', function (event) {
      handleKeydown(event, rendition);
    });

    applySettings();
    var initialCfi = stored.location || undefined;
    return rendition.display(initialCfi).then(function () {
      rendition.getContents().forEach(applyReaderStyles);
      applyStoredHighlights();
      return book.locations.generate(1000);
    }).then(function () {
      locationsReady = true;
      updateProgress(lastLocation);
    }).catch(function (error) {
      console.error('Could not generate ePub reading locations.', error);
    });
  }).catch(function (error) {
    console.error('Could not open ePub.', error);
    view.textContent = '';
    var message = document.createElement('p');
    message.className = 'jv-epub-msg';
    message.setAttribute('role', 'alert');
    message.textContent = 'No se pudo abrir el ePub / Could not open the ePub.';
    view.appendChild(message);
  });

  window.addEventListener('pagehide', function () {
    if (searchRendition) { searchRendition.destroy(); }
    if (searchBook) { searchBook.destroy(); }
    if (searchEngineHost && searchEngineHost.parentNode) { searchEngineHost.parentNode.removeChild(searchEngineHost); }
  });
})();
