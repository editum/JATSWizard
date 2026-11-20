(function () {
  'use strict';

  var root = document.getElementById('jv-flipbook');
  var sourceLink = document.getElementById('jv-flipbook-source');
  var bookElement = document.getElementById('jv-flipbook-book');
  var stageElement = root && root.querySelector('.jv-flipbook-stage');
  var status = root && root.querySelector('[data-status]');
  var pageInput = root && root.querySelector('#jv-flipbook-page');
  var pageCount = root && root.querySelector('[data-page-count]');
  var searchForm = root && root.querySelector('[data-search-form]');
  var searchInput = root && root.querySelector('[data-search-query]');
  var searchStatus = root && root.querySelector('[data-search-status]');
  var previousSearch = root && root.querySelector('[data-action=search-previous]');
  var nextSearch = root && root.querySelector('[data-action=search-next]');
  var soundButton = root && root.querySelector('[data-action=sound]');
  var fullScreenButton = root && root.querySelector('[data-action=fullscreen]');
  var pageFlip = null;
  var pdfDocument = null;
  var pdfReaderFrame = null;
  var pages = [];
  var renderScale = 1;
  var renderQueue = Promise.resolve();
  var pageText = [];
  var pageTextPromises = [];
  var pageRenderPromises = [];
  var searchMatches = [];
  var searchIndex = -1;
  var soundEnabled = false;
  var audioContext = null;
  var noiseBuffer = null;
  var lang = (document.documentElement.lang || 'es').slice(0, 2);
  var labels = lang === 'es'
    ? {loading: 'Cargando PDF...', downloading: 'Descargando PDF...', preparing: 'Preparando páginas...', searching: 'Buscando en página ', noResults: 'Sin resultados', results: 'Coincidencias: ', failed: 'No se pudo cargar el PDF.', pageFailed: 'No se pudieron preparar algunas páginas.', page: 'Página', soundOn: 'Sonido activado', soundOff: 'Sonido desactivado', soundUnavailable: 'El audio no está disponible en este navegador.', fullScreen: 'Pantalla completa', exitFullScreen: 'Salir de pantalla completa', fullScreenFailed: 'No se pudo activar la pantalla completa.'}
    : {loading: 'Loading PDF...', downloading: 'Downloading PDF...', preparing: 'Preparing pages...', searching: 'Searching page ', noResults: 'No results', results: 'Matches: ', failed: 'The PDF could not be loaded.', pageFailed: 'Some pages could not be prepared.', page: 'Page', soundOn: 'Sound on', soundOff: 'Sound off', soundUnavailable: 'Audio is not available in this browser.', fullScreen: 'Full screen', exitFullScreen: 'Exit full screen', fullScreenFailed: 'Full screen could not be enabled.'};

  if (!root || !sourceLink || !bookElement || !window.St || !window.St.PageFlip) {
    if (status) { status.textContent = labels.failed; }
    return;
  }

  if (!soundButton && fullScreenButton) {
    soundButton = document.createElement('button');
    soundButton.type = 'button';
    soundButton.dataset.action = 'sound';
    soundButton.setAttribute('aria-pressed', 'false');
    fullScreenButton.parentNode.insertBefore(soundButton, fullScreenButton);
  }

  function updateNavigation() {
    if (!pageFlip || !pdfDocument) { return; }
    var current = pageFlip.getCurrentPageIndex();
    pageInput.value = String(current + 1);
    pageInput.max = String(pdfDocument.numPages);
    pageCount.textContent = '/ ' + pdfDocument.numPages;
    root.querySelector('[data-action=previous]').disabled = current <= 0;
    root.querySelector('[data-action=next]').disabled = current >= pdfDocument.numPages - 1;
  }

  function updateFullScreenButton() {
    if (!fullScreenButton) { return; }
    var active = document.fullscreenElement === root;
    var label = active ? labels.exitFullScreen : labels.fullScreen;
    fullScreenButton.setAttribute('aria-pressed', active ? 'true' : 'false');
    fullScreenButton.setAttribute('aria-label', label);
    fullScreenButton.title = label;
  }

  function updateSoundButton() {
    if (!soundButton) { return; }
    var label = soundEnabled ? labels.soundOn : labels.soundOff;
    soundButton.setAttribute('aria-pressed', soundEnabled ? 'true' : 'false');
    soundButton.setAttribute('aria-label', label);
    soundButton.title = label;
    soundButton.textContent = soundEnabled ? '\u266B' : '\u266A';
  }

  function ensureAudio() {
    var AudioContextClass = window.AudioContext || window.webkitAudioContext;
    if (!AudioContextClass) { return Promise.resolve(false); }
    if (!audioContext) {
      audioContext = new AudioContextClass();
      noiseBuffer = audioContext.createBuffer(1, Math.ceil(audioContext.sampleRate * 0.16), audioContext.sampleRate);
      var samples = noiseBuffer.getChannelData(0);
      for (var index = 0; index < samples.length; index++) {
        samples[index] = (Math.random() * 2 - 1) * (1 - index / samples.length);
      }
    }
    if (audioContext.state === 'running') { return Promise.resolve(true); }
    return audioContext.resume().then(function () {
      return audioContext.state === 'running';
    }).catch(function (error) {
      console.warn('Could not resume FlipBook audio.', error);
      return false;
    });
  }

  function playPageSound() {
    if (!soundEnabled) { return; }
    ensureAudio().then(function (ready) {
      if (!ready || !soundEnabled) { return; }
      var source = audioContext.createBufferSource();
      var filter = audioContext.createBiquadFilter();
      var gain = audioContext.createGain();
      source.buffer = noiseBuffer;
      filter.type = 'lowpass';
      filter.frequency.value = 2600;
      gain.gain.setValueAtTime(0.0001, audioContext.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.28, audioContext.currentTime + 0.045);
      gain.gain.exponentialRampToValueAtTime(0.0001, audioContext.currentTime + 0.22);
      source.connect(filter);
      filter.connect(gain);
      gain.connect(audioContext.destination);
      source.start();
    });
  }

  function toggleFullScreen() {
    var exiting = document.fullscreenElement === root;
    var transition = exiting ? document.exitFullscreen : root.requestFullscreen;
    var context = exiting ? document : root;
    if (typeof transition !== 'function') { return; }
    try {
      var action = transition.call(context);
      if (action && typeof action.catch === 'function') {
        action.catch(function (error) {
          console.error('Could not change FlipBook full-screen mode.', error);
          status.textContent = labels.fullScreenFailed;
        });
      }
    } catch (error) {
        console.error('Could not change FlipBook full-screen mode.', error);
        status.textContent = labels.fullScreenFailed;
    }
  }

  function makePage(index) {
    var element = document.createElement('div');
    element.className = 'jv-flipbook-page is-loading';
    element.dataset.pageIndex = String(index);
    return element;
  }

  async function renderPage(pageNumber, element, scale) {
    var pageIndex = pageNumber - 1;
    if (element.dataset.rendered === 'true') { return; }
    if (pageRenderPromises[pageIndex]) { return pageRenderPromises[pageIndex]; }

    pageRenderPromises[pageIndex] = (async function () {
      var page = await pdfDocument.getPage(pageNumber);
      var pixelRatio = Math.min(window.devicePixelRatio || 1, 1.75);
      var viewport = page.getViewport({scale: scale * pixelRatio});
      var canvas = pdfReaderFrame.contentDocument.createElement('canvas');
      var context = canvas.getContext('2d', {alpha: false});
      canvas.width = Math.ceil(viewport.width);
      canvas.height = Math.ceil(viewport.height);
      canvas.style.width = '100%';
      canvas.style.height = '100%';
      canvas.setAttribute('role', 'img');
      canvas.setAttribute('aria-label', labels.page + ' ' + pageNumber);
      await page.render({canvasContext: context, viewport: viewport}).promise;
      element.textContent = '';
      element.classList.remove('is-loading');
      element.appendChild(canvas);
      element.dataset.rendered = 'true';
      page.cleanup();
    })().catch(function (error) {
      pageRenderPromises[pageIndex] = null;
      throw error;
    });
    return pageRenderPromises[pageIndex];
  }

  async function renderWindowNow(centerIndex) {
    centerIndex = Math.max(0, Math.min(pdfDocument.numPages - 1, centerIndex));
    var start = Math.max(0, centerIndex - 1);
    var end = Math.min(pdfDocument.numPages, centerIndex + 4);
    for (var index = start; index < end; index++) {
      if (pages[index].dataset.rendered === 'true') { continue; }
      status.textContent = labels.preparing + ' ' + (index + 1) + ' / ' + pdfDocument.numPages;
      await renderPage(index + 1, pages[index], renderScale);
    }
    status.textContent = '';

    var keepStart = Math.max(0, centerIndex - 2);
    var keepEnd = Math.min(pdfDocument.numPages, centerIndex + 5);
    for (var pageIndex = 0; pageIndex < pages.length; pageIndex++) {
      if (pageIndex >= keepStart && pageIndex < keepEnd) { continue; }
      if (pages[pageIndex].dataset.rendered === 'true') {
        var canvas = pages[pageIndex].querySelector('canvas');
        if (canvas) { canvas.width = canvas.height = 0; }
        pages[pageIndex].textContent = '';
        pages[pageIndex].classList.add('is-loading');
        delete pages[pageIndex].dataset.rendered;
        pageRenderPromises[pageIndex] = null;
      }
    }
  }

  function renderWindow(centerIndex) {
    renderQueue = renderQueue.catch(function () {}).then(function () {
      return renderWindowNow(centerIndex);
    });
    return renderQueue;
  }

  function waitForPdfDocument(frame) {
    return new Promise(function (resolve, reject) {
      var started = Date.now();
      var progressHandlerAttached = false;
      function check() {
        try {
          var app = frame.contentWindow && frame.contentWindow.PDFViewerApplication;
          if (app && app.pdfLoadingTask && !progressHandlerAttached) {
            progressHandlerAttached = true;
            var nativeProgressHandler = app.pdfLoadingTask.onProgress;
            app.pdfLoadingTask.onProgress = function (progress) {
              if (typeof nativeProgressHandler === 'function') { nativeProgressHandler(progress); }
              if (progress && progress.total > 0) {
                status.textContent = labels.downloading + ' ' + Math.floor(progress.loaded * 100 / progress.total) + '%';
              }
            };
          }
          if (app && app.pdfDocument) { resolve(app.pdfDocument); return; }
        } catch (error) {
          reject(error);
          return;
        }
        if (Date.now() - started > 90000) {
          reject(new Error('Timed out while opening the PDF in the OJS PDF.js viewer.'));
          return;
        }
        window.setTimeout(check, 150);
      }
      frame.addEventListener('error', function () { reject(new Error('The OJS PDF.js viewer could not be loaded.')); }, {once: true});
      frame.addEventListener('load', check, {once: true});
      window.setTimeout(check, 50);
    });
  }

  function getPageText(pageIndex) {
    if (pageText[pageIndex] !== undefined) { return Promise.resolve(pageText[pageIndex]); }
    if (pageTextPromises[pageIndex]) { return pageTextPromises[pageIndex]; }
    pageTextPromises[pageIndex] = pdfDocument.getPage(pageIndex + 1).then(function (page) {
      return page.getTextContent().then(function (content) {
        pageText[pageIndex] = content.items.map(function (item) { return item.str || ''; }).join(' ');
        page.cleanup();
        return pageText[pageIndex];
      });
    }).catch(function (error) {
      pageTextPromises[pageIndex] = null;
      throw error;
    });
    return pageTextPromises[pageIndex];
  }

  async function buildBook() {
    status.textContent = labels.loading;
    pdfReaderFrame = document.createElement('iframe');
    pdfReaderFrame.className = 'jv-flipbook-pdf-source';
    pdfReaderFrame.setAttribute('aria-hidden', 'true');
    pdfReaderFrame.setAttribute('tabindex', '-1');
    pdfReaderFrame.title = 'PDF source renderer';
    pdfReaderFrame.src = root.dataset.pdfViewer + '?file=' + encodeURIComponent(sourceLink.href);
    root.appendChild(pdfReaderFrame);
    pdfDocument = await waitForPdfDocument(pdfReaderFrame);
    var firstPage = await pdfDocument.getPage(1);
    var firstViewport = firstPage.getViewport({scale: 1});
    var columns = stageElement.clientWidth < 700 ? 1 : 2;
    var availablePageWidth = Math.max(1, (stageElement.clientWidth - 32) / columns);
    var availablePageHeight = Math.max(1, stageElement.clientHeight - 24);
    renderScale = Math.min(1.15, availablePageWidth / firstViewport.width, availablePageHeight / firstViewport.height);
    var pageWidth = Math.max(1, Math.round(firstViewport.width * renderScale));
    var pageHeight = Math.max(1, Math.round(firstViewport.height * renderScale));
    bookElement.style.width = (pageWidth * columns) + 'px';
    bookElement.style.height = pageHeight + 'px';
    firstPage.cleanup();

    pageCount.textContent = '/ ' + pdfDocument.numPages;
    for (var index = 0; index < pdfDocument.numPages; index++) {
      var pageElement = makePage(index);
      pages.push(pageElement);
      bookElement.appendChild(pageElement);
    }

    pageFlip = new window.St.PageFlip(bookElement, {
      width: pageWidth,
      height: pageHeight,
      size: 'fixed',
      drawShadow: true,
      maxShadowOpacity: 0.32,
      flippingTime: 820,
      usePortrait: true,
      showCover: true,
      mobileScrollSupport: false,
      clickEventForward: true,
    });
    pageFlip.on('init', updateNavigation);
    pageFlip.on('changeState', function (event) {
      if (event.data === 'flipping') { playPageSound(); }
    });
    pageFlip.on('flip', function () {
      updateNavigation();
      playPageSound();
      renderWindow(pageFlip.getCurrentPageIndex()).catch(function (error) {
        console.error('Could not render nearby FlipBook pages.', error);
        status.textContent = labels.pageFailed;
      });
    });
    pageFlip.loadFromHTML(pages);
    updateNavigation();
    await renderWindow(0);
  }

  function updateSearchNavigation() {
    previousSearch.disabled = searchIndex <= 0;
    nextSearch.disabled = searchIndex < 0 || searchIndex >= searchMatches.length - 1;
    if (!searchMatches.length) {
      searchStatus.textContent = labels.noResults;
      return;
    }
    searchStatus.textContent = labels.results + (searchIndex + 1) + ' / ' + searchMatches.length;
  }

  async function showSearchMatch(index) {
    if (!searchMatches.length) { return; }
    searchIndex = Math.max(0, Math.min(searchMatches.length - 1, index));
    await renderWindow(searchMatches[searchIndex]);
    pageFlip.flip(searchMatches[searchIndex]);
    updateSearchNavigation();
  }

  function turnPage(direction) {
    if (!pageFlip) { return; }
    var current = pageFlip.getCurrentPageIndex();
    var step = pageFlip.getOrientation() === 'landscape' ? 2 : 1;
    var target = direction === 'next' ? Math.min(pdfDocument.numPages - 1, current + step) : Math.max(0, current - step);
    renderWindow(target).then(function () {
      if (direction === 'next') { pageFlip.flipNext('top'); } else { pageFlip.flipPrev('top'); }
    }).catch(function (error) {
      console.error('Could not prepare the next FlipBook pages.', error);
      status.textContent = labels.pageFailed;
    });
  }

  async function searchBook(query) {
    searchMatches = [];
    searchIndex = -1;
    var needle = query.toLocaleLowerCase();
    for (var index = 0; index < pdfDocument.numPages; index++) {
      searchStatus.textContent = labels.searching + (index + 1) + ' / ' + pdfDocument.numPages;
      var text = await getPageText(index);
      if (text.toLocaleLowerCase().indexOf(needle) !== -1) {
        searchMatches.push(index);
      }
    }
    if (searchMatches.length) {
      await showSearchMatch(0);
    } else {
      updateSearchNavigation();
    }
  }

  root.addEventListener('click', function (event) {
    var button = event.target.closest('[data-action]');
    if (!button || !pageFlip) { return; }
    switch (button.dataset.action) {
      case 'previous': turnPage('previous'); break;
      case 'next': turnPage('next'); break;
      case 'search-previous': showSearchMatch(searchIndex - 1).catch(function () { searchStatus.textContent = labels.pageFailed; }); break;
      case 'search-next': showSearchMatch(searchIndex + 1).catch(function () { searchStatus.textContent = labels.pageFailed; }); break;
      case 'sound':
        if (soundEnabled) {
          soundEnabled = false;
          updateSoundButton();
        } else {
          button.disabled = true;
          ensureAudio().then(function (ready) {
            button.disabled = false;
            soundEnabled = ready;
            updateSoundButton();
            status.textContent = ready ? '' : labels.soundUnavailable;
          });
        }
        break;
      case 'fullscreen': toggleFullScreen(); break;
    }
  });

  pageInput.addEventListener('change', function () {
    if (!pageFlip || !pdfDocument) { return; }
    var pageNumber = Number(pageInput.value);
    if (!Number.isInteger(pageNumber) || pageNumber < 1 || pageNumber > pdfDocument.numPages) {
      updateNavigation();
      return;
    }
    renderWindow(pageNumber - 1).then(function () { pageFlip.flip(pageNumber - 1, 'top'); }).catch(function () {
      status.textContent = labels.pageFailed;
    });
  });

  searchForm.addEventListener('submit', function (event) {
    event.preventDefault();
    var query = searchInput.value.trim();
    if (query.length < 2) { return; }
    searchStatus.textContent = labels.searching + query;
    searchBook(query).catch(function () { searchStatus.textContent = labels.pageFailed; });
  });

  document.addEventListener('keydown', function (event) {
    if (event.target === searchInput || event.target === pageInput) { return; }
    if (event.key === 'ArrowLeft' && pageFlip) { turnPage('previous'); }
    if (event.key === 'ArrowRight' && pageFlip) { turnPage('next'); }
    if (event.key.toLowerCase() === 'f') { toggleFullScreen(); }
  });
  document.addEventListener('fullscreenchange', updateFullScreenButton);
  if (soundButton && !window.AudioContext && !window.webkitAudioContext) { soundButton.disabled = true; }
  updateSoundButton();
  if (fullScreenButton && !root.requestFullscreen) { fullScreenButton.disabled = true; }
  updateFullScreenButton();

  buildBook().catch(function (error) {
    console.error('Could not initialize the FlipBook reader.', error);
    status.textContent = labels.failed;
    status.title = error.message;
    if (pdfReaderFrame) { pdfReaderFrame.remove(); }
  });

  window.addEventListener('pagehide', function () {
    if (pdfReaderFrame) { pdfReaderFrame.remove(); }
  });
})();
