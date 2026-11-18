(function () {
  var REFRESH_MS = 15000;
  var RECONNECT_DELAY_MS = 1800;
  var MAX_RECONNECTS = 3;
  var TONEARM_START_ANGLE = 87;
  // Keep the full cartridge on the grooves, outside the centre label.
  var TONEARM_END_ANGLE = 101;
  var TONEARM_REST_ANGLE = 76;
  var TRACK_CHANGE_LEAD_SECONDS = 4;
  var TONEARM_RETURN_LEAD_SECONDS = 0.55;
  var TRACK_CHANGE_DURATION_MS = 520;
  var TONEARM_SYNC_TOLERANCE_SECONDS = 2;
  var TONEARM_MAX_CATCHUP_RATE = 0.12;
  var STALE_PREVIOUS_TRACK_GRACE_MS = 30000;
  var activePlayers = [];
  var removalObserver = null;

  function parseConfig(root) {
    var defaults = {
      apiUrl: '',
      azuracastBaseUrl: '',
      stationShortcode: '',
      streams: { primary: '', secondary: '' },
      streamLabels: { primary: 'Primary', secondary: 'Secondary', hls: 'Auto' },
      defaultStream: 'primary',
      fallbackArt: '',
      stationLabel: 'NET FM',
      stationLabelOverride: false,
      theme: 'vinyl'
    };

    try {
      var raw = root.getAttribute('data-config');
      if (!raw) return defaults;
      var parsed = JSON.parse(raw);

      return {
        apiUrl: typeof parsed.apiUrl === 'string' ? parsed.apiUrl : defaults.apiUrl,
        azuracastBaseUrl: parsed.azuracastBaseUrl || defaults.azuracastBaseUrl,
        stationShortcode: parsed.stationShortcode || defaults.stationShortcode,
        streams: {
          primary: parsed.streams && parsed.streams.primary ? parsed.streams.primary : defaults.streams.primary,
          secondary: parsed.streams && parsed.streams.secondary ? parsed.streams.secondary : defaults.streams.secondary,
          hls: parsed.streams && parsed.streams.hls ? parsed.streams.hls : ''
        },
        streamLabels: {
          primary: parsed.streamLabels && parsed.streamLabels.primary ? parsed.streamLabels.primary : defaults.streamLabels.primary,
          secondary: parsed.streamLabels && parsed.streamLabels.secondary ? parsed.streamLabels.secondary : defaults.streamLabels.secondary,
          hls: parsed.streamLabels && parsed.streamLabels.hls ? parsed.streamLabels.hls : defaults.streamLabels.hls
        },
        defaultStream: parsed.defaultStream === 'secondary' || parsed.defaultStream === 'hls' ? parsed.defaultStream : 'primary',
        fallbackArt: parsed.fallbackArt || defaults.fallbackArt,
        stationLabel: parsed.stationLabel || defaults.stationLabel,
        stationLabelOverride: parsed.stationLabelOverride === true,
        theme: parsed.theme === 'vinyl' ? 'vinyl' : defaults.theme
      };
    } catch (error) {
      return defaults;
    }
  }

  function watchPlayer(root) {
    activePlayers.push(root);

    if (removalObserver || typeof window.MutationObserver !== 'function') return;
    removalObserver = new window.MutationObserver(function () {
      activePlayers.slice().forEach(function (playerRoot) {
        if (!playerRoot.isConnected && typeof playerRoot._nfpDestroy === 'function') {
          playerRoot._nfpDestroy();
        }
      });
    });
    removalObserver.observe(document.documentElement, { childList: true, subtree: true });
  }

  function unwatchPlayer(root) {
    activePlayers = activePlayers.filter(function (playerRoot) {
      return playerRoot !== root;
    });

    if (!activePlayers.length && removalObserver) {
      removalObserver.disconnect();
      removalObserver = null;
    }
  }

  function initPlayer(root) {
    if (!root || root.getAttribute('data-ready') === '1') return;
    root.setAttribute('data-ready', '1');

    var config = parseConfig(root);
    var streamKey = config.defaultStream;
    var reconnects = 0;
    var fallbackAttempted = false;
    var reconnectTimer = null;
    var bufferingTimer = null;
    var stalledTimer = null;
    var metadataController = null;
    var metadataRequestId = 0;
    var audioContext = null;
    var analyser = null;
    var sourceNode = null;
    var frequencyData = null;
    var visualizerFrame = null;
    var analyserUnavailable = false;
    var currentArt = '';
    var stationLogoAvailable = Boolean(config.fallbackArt);
    var pendingArt = '';
    var failedArtUrls = {};
    var activeCoverLoad = null;
    var lateArtTimer = null;
    var placeholderTimer = null;
    var placeholderFadeAnimation = null;
    var placeholderReadyPromise = null;
    var coverRevealTimer = null;
    var coverRevealAnimation = null;
    var playRequested = false;
    var hasMetadata = false;
    var trackElapsed = 0;
    var trackDuration = 0;
    var tonearmCatchupRemaining = 0;
    var tonearmFrameAt = 0;
    var tonearmClockInitialized = false;
    var tonearmClockRunning = false;
    var tonearmNeedsPlaybackSync = false;
    var tonearmFrame = null;
    var tonearmLastBaseAngle = TONEARM_START_ANGLE;
    var currentTrackKey = '';
    var pendingTrackKey = '';
    var previousTrackKey = '';
    var ignorePreviousTrackUntil = 0;
    var nextTrackCache = { key: '', coverSrc: '', ready: false };
    var trackChangeActive = false;
    var trackEndReached = false;
    var queuedTrackPayload = null;
    var boundaryPolling = false;
    var trackChangeTimer = null;
    var trackEndTimer = null;
    var tonearmReturnTimer = null;
    var trackChangePolls = 0;
    var trackTransitionTimer = null;
    var trackTransitionId = 0;
    var metadataScrambleTimer = null;
    var metadataRevealTimer = null;
    var metadataCharacterTimers = [];
    var metadataScrambleState = null;
    var initialMetadataRevealTimer = null;
    var metadataTransitionReadyPromise = null;
    var metadataTransitionReadyTimer = null;
    var resizeObserver = null;
    var resizeHandler = null;
    var destroyed = false;
    var currentStationName = config.stationLabel;
    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    var audio = root.querySelector('[data-nfp-audio]');
    var toggle = root.querySelector('[data-nfp-toggle]');
    var mute = root.querySelector('[data-nfp-mute]');
    var volume = root.querySelector('[data-nfp-volume]');
    var cover = root.querySelector('[data-nfp-cover]');
    var stationArt = root.querySelector('[data-nfp-station-art]');
    var art = root.querySelector('[data-nfp-art]');
    var placeholder = root.querySelector('[data-nfp-placeholder]');
    var placeholderName = root.querySelector('[data-nfp-placeholder-name]');
    var title = root.querySelector('[data-nfp-title]');
    var artist = root.querySelector('[data-nfp-artist]');
    var accessibleTrack = root.querySelector('[data-nfp-track-accessible]');
    var trackArea = root.querySelector('[data-nfp-track]');
    var titleWindow = root.querySelector('[data-nfp-title-window]');
    var copyTrackButton = root.querySelector('[data-nfp-copy-track]');
    var titleObserver = null;
    var titleMeasureFrame = null;
    var longPressTimer = null;
    var touchStart = null;
    var stationLabel = root.querySelector('[data-nfp-station-label]');
    var live = root.querySelector('[data-nfp-live]');
    var count = root.querySelector('[data-nfp-count]');
    var error = root.querySelector('[data-nfp-error]');
    var status = root.querySelector('[data-nfp-status]');
    var eqNote = root.querySelector('[data-nfp-eq-note]');
    var eqBars = root.querySelectorAll('.nfp__eq i');
    var streamButtons = root.querySelectorAll('[data-nfp-stream]');
    var liveState = root.classList.contains('is-live') ? 'live' : (root.classList.contains('is-offline') ? 'offline' : 'unknown');
    var liveText = live ? live.textContent : '';

    if (!audio || !toggle || !mute) return;

    currentArt = art ? (art.getAttribute('src') || '') : '';

    audio.preload = 'none';
    audio.volume = volume ? Number(volume.value || 0.85) : 0.85;

    function selectedStream() {
      if (streamKey === 'hls' && !supportsNativeHls()) return '';
      return config.streams[streamKey] || config.streams.primary || config.streams.secondary || (supportsNativeHls() ? config.streams.hls : '');
    }

    function supportsNativeHls() {
      return Boolean(audio && (audio.canPlayType('application/vnd.apple.mpegurl') || audio.canPlayType('application/x-mpegURL')));
    }

    function setActiveStreamButton() {
      streamButtons.forEach(function (button) {
        var active = button.getAttribute('data-nfp-stream') === streamKey;
        button.classList.toggle('is-active', active);
        button.setAttribute('aria-pressed', active ? 'true' : 'false');
      });
    }

    function updateResponsiveState() {
      if (destroyed) return;
      var width = root.getBoundingClientRect().width;
      root.classList.toggle('is-narrow', width <= 420);
      scheduleTitleMeasure();
    }

    function scheduleTitleMeasure() {
      if (!title || !titleWindow || titleMeasureFrame) return;
      titleMeasureFrame = window.requestAnimationFrame(function () {
        titleMeasureFrame = null;
        if (destroyed) return;
        var distance = Math.max(0, Math.ceil(title.scrollWidth - titleWindow.clientWidth));
        titleWindow.style.setProperty('--nfp-title-distance', distance + 'px');
        titleWindow.style.setProperty('--nfp-title-duration', Math.max(5, distance / 28 + 3) + 's');
        titleWindow.classList.toggle('is-overflowing', distance > 1);
      });
    }

    function cancelLongPress() {
      if (longPressTimer) window.clearTimeout(longPressTimer);
      longPressTimer = null;
      touchStart = null;
    }

    if (trackArea && copyTrackButton) {
      trackArea.addEventListener('contextmenu', function (event) {
        event.preventDefault();
        copyTrackButton.hidden = false;
        copyTrackButton.focus();
      });
      trackArea.addEventListener('pointerdown', function (event) {
        if (event.pointerType !== 'touch' || event.target === copyTrackButton) return;
        cancelLongPress();
        touchStart = { x: event.clientX, y: event.clientY };
        longPressTimer = window.setTimeout(function () {
          longPressTimer = null;
          copyTrackButton.hidden = false;
        }, 600);
      });
      trackArea.addEventListener('pointermove', function (event) {
        if (touchStart && (Math.abs(event.clientX - touchStart.x) > 10 || Math.abs(event.clientY - touchStart.y) > 10)) cancelLongPress();
      });
      trackArea.addEventListener('pointerup', cancelLongPress);
      trackArea.addEventListener('pointercancel', cancelLongPress);
      copyTrackButton.addEventListener('click', function () {
        var text = accessibleTrack ? accessibleTrack.textContent.trim() : '';
        if (!text || !navigator.clipboard || !navigator.clipboard.writeText) {
          showStatus('Не удалось скопировать название');
          return;
        }
        navigator.clipboard.writeText(text).then(function () {
          if (!destroyed) { copyTrackButton.hidden = true; showStatus('Название скопировано'); }
        }).catch(function () { if (!destroyed) showStatus('Не удалось скопировать название'); });
      });
    }
    if (title && typeof window.MutationObserver === 'function') {
      titleObserver = new window.MutationObserver(scheduleTitleMeasure);
      titleObserver.observe(title, { childList: true, characterData: true, subtree: true });
    }

    function isCurrentStream(url) {
      try {
        return new URL(audio.src, window.location.href).href === new URL(url, window.location.href).href;
      } catch (error) {
        return audio.src === url;
      }
    }

    function setStream(force, reportMissing) {
      if (streamKey === 'hls' && !supportsNativeHls()) {
        streamKey = config.streams.primary ? 'primary' : 'secondary';
        setActiveStreamButton();
        if (reportMissing) showError(true, 'Автоматический HLS-поток не поддерживается этим браузером. Выбран обычный поток.');
      }
      var next = selectedStream();
      if (!next) {
        if (reportMissing) showError(true, 'Поток не настроен. Укажите Stream URL в настройках.');
        return false;
      }

      if (force || !isCurrentStream(next)) {
        audio.src = next;
        audio.load();
      }

      return true;
    }

    function setPlaying(value) {
      if (value) {
        root.classList.remove('is-vinyl-frozen');
      } else {
        if (config.theme === 'vinyl' && root.classList.contains('is-playing')) {
          root.classList.add('is-vinyl-frozen');
        }
      }
      root.classList.toggle('is-playing', value);
      if (value) resumeTonearmClock();
      else pauseTonearmClock();
      toggle.setAttribute('aria-label', value ? 'Остановить радио' : 'Включить радио');
      if (value) startVisualizer();
      else stopVisualizer(false);
    }

    function renderLiveStatus() {
      if (!live) return;
      var nextText = root.classList.contains('is-buffering') ? 'Буферизация ' + currentStationName : liveText;
      if (live.textContent !== nextText) live.textContent = nextText;
    }

    function setLiveStatus(state, text) {
      var nextState = state === 'live' || state === 'offline' ? state : 'unknown';
      var nextText = text || (nextState === 'live' ? 'В эфире' : (nextState === 'offline' ? 'Нет эфира' : 'Статус неизвестен'));
      var changed = liveState !== nextState || liveText !== nextText;
      liveState = nextState;
      liveText = nextText;
      root.classList.toggle('is-live', liveState === 'live');
      root.classList.toggle('is-offline', liveState === 'offline');
      if (changed) renderLiveStatus();
    }

    function setBuffering(value) {
      if (!value && bufferingTimer) {
        window.clearTimeout(bufferingTimer);
        bufferingTimer = null;
      }
      var changed = root.classList.contains('is-buffering') !== Boolean(value);
      root.classList.toggle('is-buffering', value);
      if (changed) renderLiveStatus();
    }

    function scheduleBuffering(delay) {
      if (bufferingTimer || root.classList.contains('is-buffering')) return;
      bufferingTimer = window.setTimeout(function () {
        bufferingTimer = null;
        if (playRequested && !audio.paused && audio.readyState < 3) setBuffering(true);
      }, Math.max(150, delay || 350));
    }

    function clearStalledTimer() {
      if (!stalledTimer) return;
      window.clearTimeout(stalledTimer);
      stalledTimer = null;
    }

    function scheduleStreamRecovery(delay) {
      if (audio.paused || !playRequested || stalledTimer) return;
      var stalledStream = audio.currentSrc;
      stalledTimer = window.setTimeout(function () {
        stalledTimer = null;
        if (!playRequested || audio.paused || audio.currentSrc !== stalledStream) return;
        if (audio.readyState < 3) reconnect();
      }, Math.max(2500, delay || 6500));
    }

    function clearReconnectTimer() {
      if (!reconnectTimer) return;
      window.clearTimeout(reconnectTimer);
      reconnectTimer = null;
    }

    function markPlaybackReady() {
      if (audio.paused || !playRequested) {
        clearStalledTimer();
        setBuffering(false);
        return;
      }
      reconnects = 0;
      clearStalledTimer();
      showError(false);
      if (fallbackAttempted) showStatus('Используется резервный поток.');
      setPlaying(true);
      setBuffering(false);
    }

    function showError(value, message) {
      if (!error) return;
      if (message) error.textContent = message;
      error.hidden = !value;
    }

    function showStatus(message) {
      if (!status) return;
      status.textContent = message || '';
      status.hidden = !message;
    }

    function applyCover(src) {
      currentArt = src || '';
      if (src) {
        art.src = src;
        art.hidden = false;
        cover.classList.remove('is-placeholder');
      } else {
        art.removeAttribute('src');
        art.hidden = true;
        cover.classList.toggle('is-placeholder', !stationLogoAvailable);
        setStationPlaceholderName();
      }
    }

    function stationNameForPlaceholder() {
      var stationName = String(currentStationName || config.stationLabel || '').trim();
      return stationName.replace(/\s+radio\s*$/i, '').trim() || stationName;
    }

    function fitStationPlaceholderName() {
      if (!placeholder || !placeholderName) return;
      window.requestAnimationFrame(function () {
        var maximumSize = 18;
        var availableWidth = Math.max(1, placeholder.clientWidth - 16);
        placeholder.style.setProperty('--nfp-station-name-size', maximumSize + 'px');
        var fittedSize = Math.max(7, Math.min(maximumSize, maximumSize * (availableWidth / Math.max(1, placeholderName.scrollWidth))));
        placeholder.style.setProperty('--nfp-station-name-size', fittedSize.toFixed(2) + 'px');
      });
    }

    function setStationPlaceholderName() {
      if (!placeholderName) return;
      placeholderName.textContent = stationNameForPlaceholder();
      fitStationPlaceholderName();
    }

    function preloadCover(src) {
      if (!art || !cover) return Promise.resolve('');
      if (src && failedArtUrls[src]) return Promise.resolve('');
      if (!src || src === currentArt) return Promise.resolve(src || '');

      return new Promise(function (resolve) {
        var loader = new window.Image();
        loader.onload = function () {
          resolve(src);
        };
        loader.onerror = function () {
          failedArtUrls[src] = true;
          resolve('');
        };
        loader.src = src;
      });
    }

    function preloadCoverWithTimeout(src, timeout) {
      var fallback = '';
      var finalCover = preloadCover(src);
      var timeoutId = null;
      var resolveInitial = null;
      var initial = new Promise(function (resolve) {
        resolveInitial = resolve;
        timeoutId = window.setTimeout(function () {
          timeoutId = null;
          resolve(fallback);
        }, timeout);
      });

      finalCover.then(function (resolvedSrc) {
        if (!timeoutId) return;
        window.clearTimeout(timeoutId);
        timeoutId = null;
        resolveInitial(resolvedSrc);
      });

      return {
        initial: initial,
        final: finalCover,
        cancel: function () {
          if (!timeoutId) return;
          window.clearTimeout(timeoutId);
          timeoutId = null;
        }
      };
    }

    function setCover(src) {
      if (!art || !cover) return;
      if (src === currentArt || src === pendingArt) return;

      if (!src) {
        pendingArt = '';
        applyCover('');
        return;
      }

      pendingArt = src;
      preloadCover(src).then(function (resolvedSrc) {
        if (pendingArt !== src) return;
        pendingArt = '';
        applyCover(resolvedSrc);
      });
    }

    function revealCover(src, duration) {
      var transitionDuration = duration || 420;
      if (!src || reduceMotion || !art || !cover || typeof art.animate !== 'function') {
        applyCover(src);
        return;
      }

      if (coverRevealTimer) window.clearTimeout(coverRevealTimer);
      if (coverRevealAnimation) coverRevealAnimation.cancel();
      cover.classList.add('is-cover-revealing');
      art.style.opacity = '0';
      applyCover(src);
      coverRevealAnimation = art.animate(
        [{ opacity: 0 }, { opacity: 1 }],
        { duration: transitionDuration, easing: 'cubic-bezier(.2, .75, .2, 1)', fill: 'forwards' }
      );
      if (placeholder && typeof placeholder.animate === 'function') {
        placeholder.animate([{ opacity: 1 }, { opacity: 0 }], { duration: transitionDuration - 80, easing: 'ease-out', fill: 'forwards' });
      }
      coverRevealTimer = window.setTimeout(function () {
        coverRevealTimer = null;
        if (coverRevealAnimation) {
          coverRevealAnimation.cancel();
          coverRevealAnimation = null;
        }
        art.style.removeProperty('opacity');
        cover.classList.remove('is-cover-revealing');
      }, transitionDuration);
    }

    function showStationPlaceholder(generation) {
      if (!cover || !art) return Promise.resolve();
      if (placeholderReadyPromise) return placeholderReadyPromise;
      pendingArt = '';
      root.classList.add('is-track-pending');
      setStationPlaceholderName();

      if (stationLogoAvailable) {
        applyCover('');
        return Promise.resolve();
      }

      cover.classList.add('is-placeholder-revealing');

      placeholderReadyPromise = new Promise(function (resolve) {
        var applyPlaceholder = function () {
          placeholderTimer = null;
          if (destroyed || generation !== trackTransitionId) {
            placeholderReadyPromise = null;
            resolve();
            return;
          }
          // A configured station logo is the transition state. The generic
          // placeholder is reserved for installations without a usable logo.
          applyCover(config.fallbackArt);
          cover.classList.remove('is-placeholder-revealing');
          if (placeholderFadeAnimation) {
            placeholderFadeAnimation.cancel();
            placeholderFadeAnimation = null;
          }
          resolve();
        };

        if (!art.hidden && typeof art.animate === 'function' && !reduceMotion) {
          if (placeholder && typeof placeholder.animate === 'function') {
            placeholder.animate([{ opacity: 0 }, { opacity: 1 }], { duration: 340, easing: 'ease-out', fill: 'forwards' });
          }
          placeholderFadeAnimation = art.animate([{ opacity: 1 }, { opacity: 0 }], { duration: 420, easing: 'ease-out', fill: 'forwards' });
          placeholderTimer = window.setTimeout(applyPlaceholder, 420);
        } else {
          applyPlaceholder();
        }
      });

      return placeholderReadyPromise;
    }

    function randomMetadataCharacter() {
      var alphabet = 'АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯабвгдеёжзийклмнопрстуфхцчшщъыьэюяABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
      return alphabet.charAt(Math.floor(Math.random() * alphabet.length));
    }

    function metadataCharacterLength(value, fallback) {
      return Math.max(4, Math.min(20, String(value || '').length || fallback));
    }

    function setMetadataCharacter(span, value) {
      span.textContent = value === ' ' ? '\u00a0' : value;
    }

    function animateMetadataCharacter(span, className) {
      span.classList.remove('is-changing', 'is-revealing', 'is-fading');
      void span.offsetWidth;
      span.classList.add(className);
    }

    function replaceMetadataCharacter(span, value, className) {
      span.classList.remove('is-changing', 'is-revealing', 'is-fading');
      void span.offsetWidth;
      span.classList.add('is-fading');
      scheduleMetadataCharacter(function () {
        setMetadataCharacter(span, value);
        animateMetadataCharacter(span, className);
      }, 130);
    }

    function renderMetadataCharacters(element, value, randomize) {
      var fragment = document.createDocumentFragment();
      var characters = [];
      var text = String(value || '');

      for (var index = 0; index < text.length; index += 1) {
        var span = document.createElement('span');
        span.className = 'nfp__metadata-char';
        span.style.setProperty('--nfp-char-duration', (260 + Math.round(Math.random() * 260)) + 'ms');
        setMetadataCharacter(span, randomize && text.charAt(index) !== ' ' ? randomMetadataCharacter() : text.charAt(index));
        fragment.appendChild(span);
        characters.push(span);
      }

      element.textContent = '';
      element.appendChild(fragment);
      return characters;
    }

    function clearMetadataAnimation() {
      if (metadataScrambleTimer) {
        window.clearInterval(metadataScrambleTimer);
        metadataScrambleTimer = null;
      }
      if (metadataRevealTimer) {
        window.clearInterval(metadataRevealTimer);
        metadataRevealTimer = null;
      }
      metadataCharacterTimers.forEach(function (timer) {
        window.clearTimeout(timer);
      });
      metadataCharacterTimers = [];
      metadataScrambleState = null;
      if (metadataTransitionReadyTimer) {
        window.clearTimeout(metadataTransitionReadyTimer);
        metadataTransitionReadyTimer = null;
      }
      metadataTransitionReadyPromise = null;
    }

    function createScrambleField(element, length, preserveText) {
      var characterCount = metadataCharacterLength('', length);
      var sourceText = String(element.textContent || '').slice(0, characterCount);
      var initialText = preserveText
        ? sourceText + new Array(Math.max(0, characterCount - sourceText.length) + 1).join(' ')
        : new Array(characterCount + 1).join('X');
      var characters = renderMetadataCharacters(element, initialText, !preserveText);
      var now = Date.now();
      return {
        characters: characters,
        nextChanges: characters.map(function () {
          return now + 100 + Math.round(Math.random() * 420);
        })
      };
    }

    function createDisassemblingField(element, length) {
      var field = createScrambleField(element, length, true);
      var characterCount = Math.max(1, field.characters.length - 1);
      var now = Date.now();
      field.nextChanges = field.nextChanges.map(function (_, index) {
        var progress = index / characterCount;
        var easedProgress = 1 - Math.pow(1 - progress, 2);
        return now + 120 + (easedProgress * 720) + Math.round(Math.random() * 45);
      });
      return field;
    }

    function updateScrambleField(field, now) {
      field.characters.forEach(function (span, index) {
        if (now < field.nextChanges[index]) return;
        replaceMetadataCharacter(span, randomMetadataCharacter(), 'is-changing');
        field.nextChanges[index] = now + 150 + Math.round(Math.random() * 520);
      });
    }

    function playLoadingMessage() {
      var message = 'Идёт загрузка';
      var characters = renderMetadataCharacters(title, message, true);
      var revealOrder = characters.map(function (_, index) { return index; });
      var appearDelay = 180;
      var disappearDelay;

      if (revealOrder.length > 7) {
        revealOrder.splice(revealOrder.indexOf(3), 1);
        revealOrder.splice(revealOrder.indexOf(7), 1);
        revealOrder = [3, 7].concat(revealOrder.sort(function () { return Math.random() - .5; }));
      }

      root.classList.add('is-metadata-loading-copy');
      revealOrder.forEach(function (characterIndex, index) {
        var span = characters[characterIndex];
        scheduleMetadataCharacter(function () {
          replaceMetadataCharacter(span, message.charAt(characterIndex), 'is-revealing');
        }, appearDelay + (index * 135));
      });

      disappearDelay = appearDelay + (characters.length * 135) + 950;
      revealOrder.slice().reverse().forEach(function (characterIndex, index) {
        var span = characters[characterIndex];
        scheduleMetadataCharacter(function () {
          replaceMetadataCharacter(span, randomMetadataCharacter(), 'is-changing');
        }, disappearDelay + (index * 115));
      });

      return disappearDelay + (characters.length * 115) + 280;
    }

    function startMetadataScramble(showLoadingImmediately) {
      clearMetadataAnimation();
      root.classList.add('is-metadata-pending');
      root.classList.remove('is-metadata-revealing');
      var titleLength = metadataCharacterLength(title.textContent, 14);
      var artistLength = metadataCharacterLength(artist.textContent, 10);
      var startedAt = Date.now();
      metadataScrambleState = {
        title: showLoadingImmediately ? createScrambleField(title, titleLength) : createDisassemblingField(title, titleLength),
        artist: showLoadingImmediately ? createScrambleField(artist, artistLength) : createDisassemblingField(artist, artistLength),
        titleLength: titleLength,
        loadingUntil: 0,
        nextLoadingAt: startedAt + 1800
      };
      if (showLoadingImmediately) {
        metadataScrambleState.loadingUntil = startedAt + playLoadingMessage();
        metadataScrambleState.nextLoadingAt = metadataScrambleState.loadingUntil + 1800;
      } else {
        metadataTransitionReadyPromise = new Promise(function (resolve) {
          metadataTransitionReadyTimer = window.setTimeout(function () {
            metadataTransitionReadyTimer = null;
            resolve();
          }, 900);
        });
      }
      metadataScrambleTimer = window.setInterval(function () {
        var now = Date.now();
        if (!metadataScrambleState) return;

        if (metadataScrambleState.loadingUntil && now >= metadataScrambleState.loadingUntil) {
          metadataScrambleState.title = createScrambleField(title, metadataScrambleState.titleLength);
          metadataScrambleState.loadingUntil = 0;
          metadataScrambleState.nextLoadingAt = now + 1800 + Math.round(Math.random() * 900);
          root.classList.remove('is-metadata-loading-copy');
        } else if (!metadataScrambleState.loadingUntil && now >= metadataScrambleState.nextLoadingAt) {
          metadataScrambleState.loadingUntil = now + playLoadingMessage();
        } else if (!metadataScrambleState.loadingUntil) {
          updateScrambleField(metadataScrambleState.title, now);
        }

        updateScrambleField(metadataScrambleState.artist, now);
      }, 70);
    }

    function scheduleMetadataCharacter(callback, delay) {
      var timer = window.setTimeout(callback, delay);
      metadataCharacterTimers.push(timer);
      return timer;
    }

    function revealTrackMetadata(nextTitle, nextArtist) {
      clearMetadataAnimation();
      updateAccessibleTrack(nextTitle, nextArtist);
      root.classList.remove('is-metadata-loading-copy');
      root.classList.add('is-metadata-revealing');
      if (reduceMotion) {
        title.textContent = nextTitle;
        artist.textContent = nextArtist;
        root.classList.remove('is-metadata-pending');
        root.classList.remove('is-metadata-revealing');
        return;
      }

      var titleCharacters = renderMetadataCharacters(title, nextTitle, true);
      var artistCharacters = renderMetadataCharacters(artist, nextArtist, true);
      var longestDelay = 0;

      [
        { characters: titleCharacters, value: nextTitle, offset: 0 },
        { characters: artistCharacters, value: nextArtist, offset: 90 }
      ].forEach(function (field) {
        field.characters.forEach(function (span, index) {
          var delay = field.offset + (index * 48) + Math.round(Math.random() * 90);
          longestDelay = Math.max(longestDelay, delay);
          scheduleMetadataCharacter(function () {
            replaceMetadataCharacter(span, field.value.charAt(index), 'is-revealing');
          }, delay);
        });
      });

      metadataRevealTimer = scheduleMetadataCharacter(function () {
        metadataRevealTimer = null;
        title.textContent = nextTitle;
        artist.textContent = nextArtist;
        root.classList.remove('is-metadata-pending');
        root.classList.remove('is-metadata-revealing');
      }, longestDelay + 460);
    }

    function updateAccessibleTrack(nextTitle, nextArtist) {
      if (!accessibleTrack) return;
      var text = [nextTitle, nextArtist].filter(Boolean).join(' — ');
      if (accessibleTrack.textContent !== text) accessibleTrack.textContent = text;
    }

    function commitInitialMetadata(payload) {
      initialMetadataRevealTimer = null;
      if (destroyed) return;
      setCover(payload.art);
      revealTrackMetadata(payload.title, payload.artist);
      syncTonearm(payload.nowPlaying);
    }

    function showInitialMetadata(payload) {
      var pendingDuration = metadataScrambleState && metadataScrambleState.loadingUntil
        ? Math.max(0, metadataScrambleState.loadingUntil - Date.now())
        : 0;

      currentTrackKey = payload.key;
      syncTonearm(payload.nowPlaying);
      if (pendingDuration > 0 && !reduceMotion) {
        if (initialMetadataRevealTimer) window.clearTimeout(initialMetadataRevealTimer);
        initialMetadataRevealTimer = window.setTimeout(function () {
          initialMetadataRevealTimer = null;
          if (destroyed || currentTrackKey !== payload.key || pendingTrackKey) return;
          commitInitialMetadata(payload);
        }, pendingDuration);
        return;
      }

      commitInitialMetadata(payload);
    }

    function setTonearmAngle(angle) {
      var requestedAngle = Number(angle);
      var safeAngle = Math.max(TONEARM_START_ANGLE, Math.min(TONEARM_END_ANGLE, Number.isFinite(requestedAngle) ? requestedAngle : TONEARM_REST_ANGLE));
      tonearmLastBaseAngle = Math.max(tonearmLastBaseAngle, safeAngle);
      root.style.setProperty('--nfp-tonearm-angle', safeAngle.toFixed(2) + 'deg');
    }

    function stopTonearmAnimation() {
      if (!tonearmFrame) return;
      window.cancelAnimationFrame(tonearmFrame);
      tonearmFrame = null;
    }

    function resetTonearmClock() {
      stopTonearmAnimation();
      tonearmClockInitialized = false;
      tonearmClockRunning = false;
      trackElapsed = 0;
      trackDuration = 0;
      tonearmCatchupRemaining = 0;
      tonearmFrameAt = 0;
      tonearmLastBaseAngle = TONEARM_START_ANGLE;
      setTonearmAngle(TONEARM_START_ANGLE);
    }

    function updateTonearmFrame(now) {
      tonearmFrame = null;
      if (destroyed || !tonearmClockRunning || trackChangeActive || !(trackDuration > 0)) return;

      if (tonearmFrameAt > 0) {
        var frameSeconds = Math.max(0, now - tonearmFrameAt) / 1000;
        var catchupStep = Math.min(tonearmCatchupRemaining, frameSeconds * TONEARM_MAX_CATCHUP_RATE);
        tonearmCatchupRemaining -= catchupStep;
        trackElapsed = Math.min(trackDuration, trackElapsed + frameSeconds + catchupStep);
      }
      tonearmFrameAt = now;

      var remaining = Math.max(0, trackDuration - trackElapsed);
      var progress = Math.max(0, Math.min(1, trackElapsed / trackDuration));
      var nextAngle = TONEARM_START_ANGLE + ((TONEARM_END_ANGLE - TONEARM_START_ANGLE) * progress);

      setTonearmAngle(Math.max(tonearmLastBaseAngle, nextAngle));

      if (remaining <= TRACK_CHANGE_LEAD_SECONDS) {
        startBoundaryPolling();
        scheduleEndOfTrackTransition(remaining);
        if (remaining <= 0) beginEndOfTrackTransition();
      }

      if (!trackEndReached && tonearmClockRunning) {
        tonearmFrame = window.requestAnimationFrame(updateTonearmFrame);
      }
    }

    function startTonearmAnimation() {
      if (tonearmFrame || !tonearmClockRunning || trackChangeActive || !(trackDuration > 0)) return;
      tonearmFrame = window.requestAnimationFrame(updateTonearmFrame);
    }

    function resumeTonearmClock() {
      if (!tonearmClockInitialized || !(trackDuration > 0)) return;
      tonearmClockRunning = true;
      tonearmFrameAt = performance.now();
      startTonearmAnimation();
    }

    function pauseTonearmClock() {
      if (!tonearmClockRunning) return;
      tonearmFrameAt = 0;
      tonearmClockRunning = false;
      stopTonearmAnimation();
    }

    function stopBoundaryPolling() {
      boundaryPolling = false;
      trackChangePolls = 0;
      if (trackChangeTimer) {
        window.clearTimeout(trackChangeTimer);
        trackChangeTimer = null;
      }
    }

    function scheduleBoundaryRefresh(delay) {
      if (!config.apiUrl || destroyed || !boundaryPolling || trackChangeTimer || trackChangePolls >= 24) return;

      trackChangeTimer = window.setTimeout(function () {
        trackChangeTimer = null;
        trackChangePolls += 1;
        refresh();
      }, Math.max(350, delay));
    }

    function startBoundaryPolling() {
      if (boundaryPolling) return;
      boundaryPolling = true;
      trackChangePolls = 0;
      scheduleBoundaryRefresh(350);
    }

    function trackKey(song) {
      song = song || {};
      var textKey = song.artist || song.title ? [song.artist || '', song.title || ''].join('|') : '';
      return song.id || song.text || textKey;
    }

    function usableMetadataText(value, fallback) {
      var text = typeof value === 'string' ? value.trim() : '';
      if (!text || /\uFFFD/.test(text)) return fallback;
      return text;
    }

    function prepareNextTrack(playingNext) {
      var nextSong = playingNext && playingNext.song ? playingNext.song : playingNext;
      var nextKey = trackKey(nextSong);
      if (!nextKey) {
        nextTrackCache = { key: '', coverSrc: '', ready: false };
        return;
      }
      if (nextTrackCache.key === nextKey) return;

      nextTrackCache = { key: nextKey, coverSrc: '', ready: false };
      var expectedKey = nextKey;
      preloadCover(nextSong && nextSong.art).then(function (coverSrc) {
        if (destroyed || nextTrackCache.key !== expectedKey) return;
        nextTrackCache.coverSrc = coverSrc;
        nextTrackCache.ready = true;
      });
    }

    function getCoverLoad(payload) {
      if (payload.preloadedCoverReady) {
        return {
          initial: Promise.resolve(payload.preloadedCover),
          final: Promise.resolve(payload.preloadedCover),
          cancel: function () {}
        };
      }
      return preloadCoverWithTimeout(payload.art, 350);
    }

    function returnTonearmToStart() {
      if (trackChangeActive || config.theme !== 'vinyl' || reduceMotion) return;
      trackChangeActive = true;
      stopTonearmAnimation();
      tonearmLastBaseAngle = TONEARM_START_ANGLE;
      root.classList.add('is-track-changing');
      setTonearmAngle(TONEARM_START_ANGLE);
    }

    function scheduleTonearmReturn(remaining) {
      if (trackEndReached || trackChangeActive || tonearmReturnTimer || !Number.isFinite(remaining)) return;
      tonearmReturnTimer = window.setTimeout(function () {
        tonearmReturnTimer = null;
        returnTonearmToStart();
      }, Math.max(0, (remaining - TONEARM_RETURN_LEAD_SECONDS) * 1000));
    }

    function beginEndOfTrackTransition() {
      if (trackEndReached || destroyed) return;
      trackEndReached = true;
      if (trackEndTimer) {
        window.clearTimeout(trackEndTimer);
        trackEndTimer = null;
      }
      if (tonearmReturnTimer) {
        window.clearTimeout(tonearmReturnTimer);
        tonearmReturnTimer = null;
      }
      returnTonearmToStart();
      showStationPlaceholder(trackTransitionId);
      startMetadataScramble();
      if (queuedTrackPayload) beginTrackTransition(queuedTrackPayload);
    }

    function scheduleEndOfTrackTransition(remaining) {
      if (trackEndReached || trackEndTimer || !Number.isFinite(remaining)) return;
      trackEndTimer = window.setTimeout(function () {
        trackEndTimer = null;
        beginEndOfTrackTransition();
      }, Math.max(0, remaining * 1000));
    }

    function adjustedNowPlaying(nowPlaying, receivedAt) {
      var adjusted = Object.assign({}, nowPlaying || {});
      var elapsed = Number(adjusted.elapsed);
      if (Number.isFinite(elapsed) && receivedAt) {
        adjusted.elapsed = elapsed + Math.max(0, (Date.now() - receivedAt) / 1000);
      }
      return adjusted;
    }

    function commitTrackTransition(transitionId, payload, coverSrc) {
      if (destroyed || transitionId !== trackTransitionId) return;
      previousTrackKey = currentTrackKey;
      ignorePreviousTrackUntil = previousTrackKey ? Date.now() + STALE_PREVIOUS_TRACK_GRACE_MS : 0;
      currentTrackKey = payload.key;
      pendingTrackKey = '';
      queuedTrackPayload = null;
      trackEndReached = false;
      if (tonearmReturnTimer) {
        window.clearTimeout(tonearmReturnTimer);
        tonearmReturnTimer = null;
      }
      if (placeholderTimer) {
        window.clearTimeout(placeholderTimer);
        placeholderTimer = null;
      }
      if (placeholderFadeAnimation) {
        placeholderFadeAnimation.cancel();
        placeholderFadeAnimation = null;
      }
      placeholderReadyPromise = null;
      pendingArt = '';
      revealCover(coverSrc);
      root.classList.remove('is-track-pending');
      revealTrackMetadata(payload.title, payload.artist);
      syncTonearm(adjustedNowPlaying(payload.nowPlaying, payload.receivedAt));
    }

    function finishTrackTransition(transitionId, payload) {
      if (destroyed || transitionId !== trackTransitionId) return;
      trackChangeActive = false;
      root.classList.remove('is-track-changing');
      syncTonearm(adjustedNowPlaying(payload.nowPlaying, payload.receivedAt));
    }

    function beginTrackTransition(payload) {
      var transitionId = ++trackTransitionId;
      pendingTrackKey = payload.key;
      if (initialMetadataRevealTimer) {
        window.clearTimeout(initialMetadataRevealTimer);
        initialMetadataRevealTimer = null;
      }
      stopBoundaryPolling();
      if (activeCoverLoad) activeCoverLoad.cancel();
      if (lateArtTimer) {
        window.clearTimeout(lateArtTimer);
        lateArtTimer = null;
      }
      if (trackTransitionTimer) {
        window.clearTimeout(trackTransitionTimer);
        trackTransitionTimer = null;
      }
      if (placeholderTimer) {
        window.clearTimeout(placeholderTimer);
        placeholderTimer = null;
      }
      if (placeholderFadeAnimation) {
        placeholderFadeAnimation.cancel();
        placeholderFadeAnimation = null;
      }
      placeholderReadyPromise = null;
      // A generation owns every visible transition write. Reset mechanics and
      // remove old art before waiting for the new artwork request.
      trackChangeActive = true;
      root.classList.add('is-track-changing');
      resetTonearmClock();
      startMetadataScramble();
      var placeholderReady = showStationPlaceholder(transitionId);
      var coverLoad = getCoverLoad(payload);
      activeCoverLoad = coverLoad;

      Promise.all([coverLoad.initial, placeholderReady, metadataTransitionReadyPromise || Promise.resolve()]).then(function (results) {
        var coverSrc = results[0];
        if (destroyed || transitionId !== trackTransitionId) return;
        activeCoverLoad = null;
        commitTrackTransition(transitionId, payload, coverSrc);
        finishTrackTransition(transitionId, payload);

        // A slow artwork request must not keep the player in transition.
        // The station logo remains until this generation's final image arrives.
        coverLoad.final.then(function (finalCoverSrc) {
          if (!finalCoverSrc || finalCoverSrc === coverSrc || destroyed || transitionId !== trackTransitionId || currentTrackKey !== payload.key) return;
          revealCover(finalCoverSrc, 360);
        });
      });
    }

    function syncTonearm(nowPlaying) {
      var duration = Number(nowPlaying && nowPlaying.duration);
      var elapsed = Number(nowPlaying && nowPlaying.elapsed);

      if (!nowPlaying) {
        currentTrackKey = '';
        stopBoundaryPolling();
        if (trackEndTimer) {
          window.clearTimeout(trackEndTimer);
          trackEndTimer = null;
        }
        if (tonearmReturnTimer) {
          window.clearTimeout(tonearmReturnTimer);
          tonearmReturnTimer = null;
        }
      }

      if (Number.isFinite(duration) && duration > 0 && Number.isFinite(elapsed) && elapsed >= 0) {
        trackDuration = duration;
        if (playRequested && tonearmNeedsPlaybackSync && !trackChangeActive && !trackEndReached) {
          // A live stream keeps advancing while paused. Align once on play;
          // ordinary metadata drift still uses the slower bounded correction.
          var arm = root.querySelector('.nfp__vinyl-arm');
          var before = arm && !reduceMotion && typeof arm.animate === 'function' ? window.getComputedStyle(arm).transform : '';
          trackElapsed = Math.min(elapsed, duration);
          tonearmClockInitialized = true;
          tonearmCatchupRemaining = 0;
          tonearmLastBaseAngle = TONEARM_START_ANGLE;
          setTonearmAngle(TONEARM_START_ANGLE + ((TONEARM_END_ANGLE - TONEARM_START_ANGLE) * (trackElapsed / duration)));
          if (before && before !== 'none') {
            var after = window.getComputedStyle(arm).transform;
            if (after && after !== before) arm.animate([{ transform: before }, { transform: after }], { duration: 550, easing: 'ease-out' });
          }
          tonearmNeedsPlaybackSync = false;
          if (tonearmClockRunning) tonearmFrameAt = performance.now();
        } else if (!tonearmClockInitialized) {
          trackElapsed = Math.min(elapsed, duration);
          tonearmClockInitialized = true;
          tonearmLastBaseAngle = TONEARM_START_ANGLE;
          if (config.theme === 'vinyl' && !reduceMotion) {
            setTonearmAngle(TONEARM_START_ANGLE + ((TONEARM_END_ANGLE - TONEARM_START_ANGLE) * (trackElapsed / duration)));
          }
        } else if (!trackChangeActive && !trackEndReached) {
          // API timing is authoritative, but applying forward drift in one poll
          // teleports the arm. Catch up at a bounded rate above normal playback.
          var forwardDrift = elapsed - trackElapsed;
          if (forwardDrift > TONEARM_SYNC_TOLERANCE_SECONDS) {
            tonearmCatchupRemaining = Math.max(
              tonearmCatchupRemaining,
              forwardDrift - TONEARM_SYNC_TOLERANCE_SECONDS
            );
          }
          if (tonearmClockRunning) tonearmFrameAt = performance.now();
        }

        if (root.classList.contains('is-playing') && !trackChangeActive && !trackEndReached) {
          if (!tonearmClockRunning) resumeTonearmClock();
          else startTonearmAnimation();
        }
      } else {
        resetTonearmClock();
      }
    }

    if (art) {
      art.addEventListener('error', function () {
        var failedArt = currentArt;
        if (failedArt) failedArtUrls[failedArt] = true;
        currentArt = '';
        setCover(failedArt === config.fallbackArt ? '' : config.fallbackArt);
      });
    }

    function setStaticBars() {
      eqBars.forEach(function (bar, index) {
        bar.style.setProperty('height', (16 + (index % 6) * 6) + '%', 'important');
      });
    }

    function isSameOriginUrl(url) {
      try {
        return new URL(url, window.location.href).origin === window.location.origin;
      } catch (error) {
        return false;
      }
    }

    function markAnalyserBlocked() {
      root.classList.add('no-analyser');
      root.classList.remove('has-analyser');
      if (eqNote) eqNote.hidden = true;
      setStaticBars();
    }

    function setupAnalyser() {
      if (analyser || analyserUnavailable) return Boolean(analyser);

      try {
        var AudioContextClass = window.AudioContext || window.webkitAudioContext;
        if (!AudioContextClass) throw new Error('Web Audio API is unavailable');

        audioContext = audioContext || new AudioContextClass();
        sourceNode = sourceNode || audioContext.createMediaElementSource(audio);
        analyser = audioContext.createAnalyser();
        analyser.fftSize = 128;
        analyser.smoothingTimeConstant = 0.72;
        frequencyData = new Uint8Array(analyser.frequencyBinCount);
        sourceNode.connect(analyser);
        analyser.connect(audioContext.destination);
        root.classList.add('has-analyser');
        if (eqNote) eqNote.hidden = true;
        return true;
      } catch (error) {
        analyserUnavailable = true;
        root.classList.add('no-analyser');
        if (eqNote) eqNote.hidden = true;
        setStaticBars();
        return false;
      }
    }

    function drawVisualizer() {
      if (!analyser || !frequencyData) return;

      analyser.getByteFrequencyData(frequencyData);
      var max = 0;
      for (var i = 0; i < frequencyData.length; i += 1) max = Math.max(max, frequencyData[i]);

      // Cross-origin streams without audio CORS commonly produce silence here.
      if (max < 2) {
        root.classList.add('no-analyser-signal');
        if (eqNote) eqNote.hidden = true;
        setStaticBars();
      } else {
        root.classList.remove('no-analyser-signal');
        if (eqNote) eqNote.hidden = true;
        eqBars.forEach(function (bar, index) {
          var bin = Math.min(frequencyData.length - 1, Math.floor(index * frequencyData.length / eqBars.length));
          var height = 10 + Math.round((frequencyData[bin] / 255) * 88);
          bar.style.setProperty('height', height + '%', 'important');
        });
      }

      visualizerFrame = window.requestAnimationFrame(drawVisualizer);
    }

    function startVisualizer() {
      // Connecting an external stream to Web Audio can mute native playback
      // unless the streaming server grants audio CORS access.
      if (!isSameOriginUrl(selectedStream())) {
        markAnalyserBlocked();
        return;
      }

      root.classList.remove('no-analyser');
      if (!setupAnalyser()) {
        markAnalyserBlocked();
        return;
      }
      if (audioContext && audioContext.state === 'suspended') audioContext.resume().catch(function () {});
      if (!visualizerFrame) drawVisualizer();
    }

    function stopVisualizer(reset) {
      if (visualizerFrame) {
        window.cancelAnimationFrame(visualizerFrame);
        visualizerFrame = null;
      }
      if (reset !== false) setStaticBars();
    }

    function startPlayback(forceStream) {
      if (!setStream(forceStream, true)) return;
      playRequested = true;
      scheduleBuffering(250);
      var playPromise = audio.play();
      if (playPromise && typeof playPromise.catch === 'function') {
        playPromise.catch(function () {
          reconnect();
        });
      }
    }

    function play() {
      showError(false);
      showStatus('');
      reconnects = 0;
      fallbackAttempted = false;
      tonearmNeedsPlaybackSync = true;
      startPlayback(false);
      refresh();
    }

    function pause() {
      playRequested = false;
      clearReconnectTimer();
      clearStalledTimer();
      audio.pause();
      setPlaying(false);
      setBuffering(false);
    }

    function reconnect() {
      if (!playRequested || reconnectTimer) return;
      if (reconnects >= MAX_RECONNECTS) {
        var alternative = streamKey === 'primary' ? 'secondary' : 'primary';
        if (!fallbackAttempted && config.streams[alternative] && config.streams[alternative] !== selectedStream()) {
          fallbackAttempted = true;
          reconnects = 0;
          streamKey = alternative;
          setActiveStreamButton();
          showStatus('Поток недоступен. Подключаем резервный поток.');
          startPlayback(true);
          return;
        }
        playRequested = false;
        showError(true, 'Поток не запустился. Проверьте Stream URL или сервер вещания.');
        setPlaying(false);
        setBuffering(false);
        return;
      }

      reconnects += 1;
      setBuffering(true);
      reconnectTimer = window.setTimeout(function () {
        reconnectTimer = null;
        startPlayback(true);
      }, RECONNECT_DELAY_MS);
    }

    toggle.addEventListener('click', function () {
      if (audio.paused) play();
      else pause();
    });

    function setMuted(value) {
      audio.muted = Boolean(value);
      root.classList.toggle('is-muted', audio.muted);
      mute.setAttribute('aria-label', audio.muted ? 'Включить звук' : 'Выключить звук');
      mute.setAttribute('aria-pressed', audio.muted ? 'true' : 'false');
    }

    mute.addEventListener('click', function () {
      setMuted(!audio.muted);
    });

    if (volume) {
      volume.addEventListener('input', function () {
        audio.volume = Number(volume.value);
        if (audio.volume > 0 && audio.muted) {
          setMuted(false);
        }
      });
    }

    audio.addEventListener('play', function () {
      showError(false);
      setPlaying(true);
    });

    audio.addEventListener('playing', function () {
      markPlaybackReady();
    });

    audio.addEventListener('pause', function () {
      clearStalledTimer();
      setPlaying(false);
      setBuffering(false);
    });

    audio.addEventListener('waiting', function () {
      if (audio.paused || !playRequested) return;
      scheduleBuffering(350);
      scheduleStreamRecovery(6500);
    });

    audio.addEventListener('stalled', function () {
      if (audio.paused || !playRequested) return;
      scheduleBuffering(500);
      scheduleStreamRecovery(4500);
    });

    audio.addEventListener('suspend', function () {
      if (audio.paused || !playRequested || audio.readyState >= 3) return;
      scheduleStreamRecovery(7000);
    });

    audio.addEventListener('ended', function () {
      if (playRequested) reconnect();
    });

    audio.addEventListener('canplay', function () {
      markPlaybackReady();
    });

    audio.addEventListener('progress', function () {
      if (audio.readyState >= 3) markPlaybackReady();
    });

    audio.addEventListener('timeupdate', function () {
      if (audio.readyState >= 3) markPlaybackReady();
    });

    audio.addEventListener('error', function () {
      clearStalledTimer();
      reconnect();
    });

    streamButtons.forEach(function (button) {
      button.addEventListener('click', function () {
        var requestedStream = button.getAttribute('data-nfp-stream');
        streamKey = requestedStream === 'secondary' || requestedStream === 'hls' ? requestedStream : 'primary';
        showStatus('');
        clearStalledTimer();
        clearReconnectTimer();
        setBuffering(false);
        setActiveStreamButton();

        var shouldContinuePlayback = playRequested || !audio.paused;
        if (shouldContinuePlayback) {
          reconnects = 0;
          fallbackAttempted = false;
          startPlayback(true);
        }
      });
    });

    function refresh() {
      if (destroyed) return;
      var requestId = ++metadataRequestId;

      if (!config.apiUrl) {
        title.textContent = config.stationLabel;
        artist.textContent = 'Прямой поток';
        updateAccessibleTrack(config.stationLabel, 'Прямой поток');
        setLiveStatus('unknown', selectedStream() ? 'Состояние эфира неизвестно' : 'Поток недоступен');
        count.textContent = '';
        setCover('');
        syncTonearm(null);
        return;
      }

      if (metadataController) metadataController.abort();
      var requestController = typeof window.AbortController === 'function' ? new window.AbortController() : null;
      metadataController = requestController;
      var requestOptions = { cache: 'no-store' };
      if (requestController) requestOptions.signal = requestController.signal;

      fetch(config.apiUrl, requestOptions)
        .then(function (response) {
          if (!response.ok) throw new Error('Now Playing returned ' + response.status);
          return response.json();
        })
        .then(function (data) {
          if (destroyed || requestId !== metadataRequestId) return;
          if (!fallbackAttempted) showStatus('');
          var nowPlaying = data && data.now_playing ? data.now_playing : {};
          var song = nowPlaying.song || {};
          var nextTrackKey = trackKey(song);
          var elapsed = Number(nowPlaying.elapsed);
          var trackRestarted = Boolean(currentTrackKey) && Number.isFinite(elapsed) && elapsed >= 0 && elapsed + 2 < trackElapsed;
          var hasPreloadedCover = nextTrackCache.key === nextTrackKey && nextTrackCache.ready;
          var payload = {
            key: nextTrackKey || currentTrackKey,
            title: usableMetadataText(song.title || song.text, config.stationLabel),
            artist: usableMetadataText(song.artist, usableMetadataText(data.station && data.station.description, 'Прямой эфир')),
            art: song.art || '',
            preloadedCoverReady: hasPreloadedCover,
            preloadedCover: hasPreloadedCover ? nextTrackCache.coverSrc : '',
            nowPlaying: nowPlaying,
            receivedAt: Date.now()
          };
          currentStationName = config.stationLabelOverride
            ? config.stationLabel
            : (data.station && data.station.name ? data.station.name : config.stationLabel);
          if (stationLabel) stationLabel.textContent = currentStationName;
          setStationPlaceholderName();
          setLiveStatus(typeof data.is_online === 'boolean' ? (data.is_online ? 'live' : 'offline') : 'unknown', typeof data.is_online === 'boolean' ? ((data.is_online ? 'В эфире ' : 'Нет эфира: ') + currentStationName) : 'Состояние эфира неизвестно');
          count.textContent = data.listeners && data.listeners.current != null
            ? data.listeners.current + ' СЛУШАЮТ'
            : '— СЛУШАЮТ';

          if (!currentTrackKey) {
            showInitialMetadata(payload);
          } else if (pendingTrackKey) {
            // During a transition AzuraCast may briefly repeat either the old
            // committed payload or the pending one. Neither starts generation two.
            if (payload.key !== pendingTrackKey && payload.key !== currentTrackKey) {
              beginTrackTransition(payload);
            }
          } else if (payload.key && payload.key !== currentTrackKey) {
            if (payload.key === previousTrackKey && Date.now() < ignorePreviousTrackUntil) {
              return;
            }
            // A fresh API identity is authoritative. Waiting for the old local
            // duration leaves live audio, metadata, artwork and tonearm out of sync.
            beginTrackTransition(payload);
          } else if (!trackChangeActive && !pendingTrackKey) {
            syncTonearm(nowPlaying);
          }
          prepareNextTrack(data && data.playing_next ? data.playing_next : null);
          hasMetadata = true;
        })
        .catch(function (fetchError) {
          if (destroyed || requestId !== metadataRequestId) return;
          if (fetchError && fetchError.name === 'AbortError') return;
          if (!hasMetadata) {
            clearMetadataAnimation();
            title.textContent = config.stationLabel;
            artist.textContent = 'Метаданные недоступны';
            updateAccessibleTrack(config.stationLabel, 'Метаданные недоступны');
          }
          if (!fallbackAttempted) showStatus(hasMetadata ? 'Метаданные устарели' : 'Метаданные недоступны');
          setLiveStatus(hasMetadata ? liveState : 'unknown', hasMetadata ? 'Метаданные устарели' : 'Метаданные недоступны');
        })
        .finally(function () {
          var isCurrentRequest = metadataController === requestController;
          if (isCurrentRequest) metadataController = null;
          if (isCurrentRequest && boundaryPolling) scheduleBoundaryRefresh(700);
        });
    }

    function destroy() {
      if (destroyed) return;
      destroyed = true;
      metadataRequestId++;
      playRequested = false;

      if (root._nfpTimer) window.clearInterval(root._nfpTimer);
      stopTonearmAnimation();
      clearReconnectTimer();
      if (bufferingTimer) window.clearTimeout(bufferingTimer);
      if (stalledTimer) window.clearTimeout(stalledTimer);
      if (trackChangeTimer) window.clearTimeout(trackChangeTimer);
      if (trackEndTimer) window.clearTimeout(trackEndTimer);
      if (tonearmReturnTimer) window.clearTimeout(tonearmReturnTimer);
      if (trackTransitionTimer) window.clearTimeout(trackTransitionTimer);
      if (lateArtTimer) window.clearTimeout(lateArtTimer);
      if (placeholderTimer) window.clearTimeout(placeholderTimer);
      if (placeholderFadeAnimation) placeholderFadeAnimation.cancel();
      if (coverRevealTimer) window.clearTimeout(coverRevealTimer);
      if (coverRevealAnimation) coverRevealAnimation.cancel();
      if (activeCoverLoad) activeCoverLoad.cancel();
      if (metadataController) metadataController.abort();
      if (titleObserver) titleObserver.disconnect();
      if (titleMeasureFrame) window.cancelAnimationFrame(titleMeasureFrame);
      cancelLongPress();
      if (initialMetadataRevealTimer) window.clearTimeout(initialMetadataRevealTimer);
      clearMetadataAnimation();
      stopVisualizer(false);
      if (resizeObserver) resizeObserver.disconnect();
      if (resizeHandler) window.removeEventListener('resize', resizeHandler);

      audio.pause();
      audio.removeAttribute('src');
      audio.load();
      if (audioContext && typeof audioContext.close === 'function') audioContext.close().catch(function () {});

      root.removeAttribute('data-ready');
      delete root._nfpDestroy;
      unwatchPlayer(root);
    }

    if (typeof window.ResizeObserver === 'function') {
      resizeObserver = new window.ResizeObserver(updateResponsiveState);
      resizeObserver.observe(root);
    } else {
      resizeHandler = updateResponsiveState;
      window.addEventListener('resize', resizeHandler);
    }
    updateResponsiveState();

    if (config.streams.hls && !supportsNativeHls()) {
      streamButtons.forEach(function (button) {
        if (button.getAttribute('data-nfp-stream') === 'hls') button.hidden = true;
      });
      if (streamKey === 'hls') streamKey = config.streams.primary ? 'primary' : 'secondary';
      setActiveStreamButton();
    }

    setCover('');

    if (stationArt) {
      stationArt.addEventListener('error', function () {
        stationLogoAvailable = false;
        if (!currentArt) cover.classList.add('is-placeholder');
      });
    }
    setStaticBars();
    setTonearmAngle(TONEARM_START_ANGLE);
    if (config.apiUrl) startMetadataScramble(true);
    refresh();
    root._nfpTimer = window.setInterval(refresh, REFRESH_MS);
    root._nfpDestroy = destroy;
    watchPlayer(root);
  }

  function initAll(scope) {
    var context = scope && scope.querySelectorAll ? scope : document;
    context.querySelectorAll('[data-nfp]').forEach(initPlayer);
    context.querySelectorAll('[data-nfp-switcher]').forEach(function (switcher) {
      if (switcher.getAttribute('data-nfp-switcher-ready')) return;
      var current = switcher.querySelector('[data-nfp-station-current]');
      if (!current) return;
      switcher.setAttribute('data-nfp-switcher-ready', '1');
      switcher.addEventListener('click', function (event) {
        var toggleMenu = event.target.closest('[data-nfp-menu-toggle]');
        if (toggleMenu && current.contains(toggleMenu)) {
          var list = current.querySelector('[data-nfp-station-list]');
          if (!list) return;
          list.hidden = !list.hidden;
          toggleMenu.setAttribute('aria-expanded', list.hidden ? 'false' : 'true');
          return;
        }
        var choice = event.target.closest('[data-nfp-station-target]');
        if (!choice || !current.contains(choice)) return;
        if (choice.hasAttribute('aria-current')) {
          current.querySelector('[data-nfp-station-list]').hidden = true;
          current.querySelector('[data-nfp-menu-toggle]').setAttribute('aria-expanded', 'false');
          current.querySelector('[data-nfp-menu-toggle]').focus();
          return;
        }
        var templates = switcher.querySelectorAll('template[data-nfp-station-template]');
        var template = Array.prototype.find.call(templates, function (item) {
          return item.getAttribute('data-nfp-station-template') === choice.getAttribute('data-nfp-station-target');
        });
        if (!template) return;
        var audio = current.querySelector('[data-nfp-audio]');
        var wasPlaying = audio && !audio.paused;
        destroyAll(current);
        current.replaceChildren(template.content.cloneNode(true));
        initAll(current);
        var toggle = current.querySelector('[data-nfp-toggle]');
        if (wasPlaying && toggle && !toggle.disabled) toggle.click();
        var newMenu = current.querySelector('[data-nfp-menu-toggle]');
        if (newMenu) newMenu.focus();
      });
      switcher.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        var list = current.querySelector('[data-nfp-station-list]');
        var menu = current.querySelector('[data-nfp-menu-toggle]');
        if (list && !list.hidden && menu) {
          list.hidden = true;
          menu.setAttribute('aria-expanded', 'false');
          menu.focus();
        }
      });
      switcher.addEventListener('focusout', function () {
        window.setTimeout(function () {
          if (switcher.contains(document.activeElement)) return;
          var list = current.querySelector('[data-nfp-station-list]');
          var menu = current.querySelector('[data-nfp-menu-toggle]');
          if (list && menu) {
            list.hidden = true;
            menu.setAttribute('aria-expanded', 'false');
          }
        }, 0);
      });
    });
  }

  function destroyAll(scope) {
    var context = scope && scope.querySelectorAll ? scope : document;
    var roots = [];
    if (context.matches && context.matches('[data-nfp]')) roots.push(context);
    context.querySelectorAll('[data-nfp]').forEach(function (root) {
      roots.push(root);
    });
    roots.forEach(function (root) {
      if (typeof root._nfpDestroy === 'function') root._nfpDestroy();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      initAll(document);
    });
  } else {
    initAll(document);
  }

  window.NetFmPlayer = { init: initAll, destroy: destroyAll };

  function bindElementor() {
    if (!window.elementorFrontend || !window.elementorFrontend.hooks || window.NetFmPlayerElementorBound) return;
    window.NetFmPlayerElementorBound = true;
    window.elementorFrontend.hooks.addAction('frontend/element_ready/net-fm-player.default', function ($scope) {
      initAll($scope && $scope[0] ? $scope[0] : document);
    });
    window.elementorFrontend.hooks.addAction('frontend/element_ready/shortcode.default', function ($scope) {
      initAll($scope && $scope[0] ? $scope[0] : document);
    });
  }
  if (window.jQuery) {
    window.jQuery(window).on('elementor/frontend/init', bindElementor);
    bindElementor();
  }
})();


