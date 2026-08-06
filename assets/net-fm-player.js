(function () {
  var REFRESH_MS = 15000;
  var RECONNECT_DELAY_MS = 1800;
  var MAX_RECONNECTS = 3;
  var TONEARM_START_ANGLE = 4;
  var TONEARM_END_ANGLE = 19;
  var TONEARM_REST_ANGLE = 12;
  var TRACK_CHANGE_LEAD_SECONDS = 4;
  var TRACK_SCRATCH_DURATION_MS = 1500;
  var TRACK_SCRATCH_COMMIT_MS = 780;
  var activePlayers = [];
  var removalObserver = null;

  function parseConfig(root) {
    var defaults = {
      apiUrl: '',
      azuracastBaseUrl: '',
      stationShortcode: '',
      streams: { primary: '', secondary: '' },
      streamLabels: { primary: 'Primary', secondary: 'Secondary' },
      defaultStream: 'primary',
      fallbackArt: '',
      stationLabel: 'NET FM',
      theme: 'studio'
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
          secondary: parsed.streams && parsed.streams.secondary ? parsed.streams.secondary : defaults.streams.secondary
        },
        streamLabels: {
          primary: parsed.streamLabels && parsed.streamLabels.primary ? parsed.streamLabels.primary : defaults.streamLabels.primary,
          secondary: parsed.streamLabels && parsed.streamLabels.secondary ? parsed.streamLabels.secondary : defaults.streamLabels.secondary
        },
        defaultStream: parsed.defaultStream === 'secondary' ? 'secondary' : 'primary',
        fallbackArt: parsed.fallbackArt || defaults.fallbackArt,
        stationLabel: parsed.stationLabel || defaults.stationLabel,
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
    var reconnectTimer = null;
    var metadataController = null;
    var audioContext = null;
    var analyser = null;
    var sourceNode = null;
    var frequencyData = null;
    var visualizerFrame = null;
    var analyserUnavailable = false;
    var currentArt = '';
    var pendingArt = '';
    var failedArtUrls = {};
    var playRequested = false;
    var hasMetadata = false;
    var trackElapsed = 0;
    var trackDuration = 0;
    var trackSyncedAt = 0;
    var currentTrackKey = '';
    var pendingTrackKey = '';
    var trackChangeActive = false;
    var boundaryPolling = false;
    var trackChangeTimer = null;
    var trackChangePolls = 0;
    var trackTransitionTimer = null;
    var trackTransitionId = 0;
    var scratchAnimation = null;
    var destroyed = false;
    var currentStationName = config.stationLabel;
    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    var audio = root.querySelector('[data-nfp-audio]');
    var toggle = root.querySelector('[data-nfp-toggle]');
    var mute = root.querySelector('[data-nfp-mute]');
    var volume = root.querySelector('[data-nfp-volume]');
    var cover = root.querySelector('[data-nfp-cover]');
    var art = root.querySelector('[data-nfp-art]');
    var placeholder = root.querySelector('[data-nfp-placeholder]');
    var title = root.querySelector('[data-nfp-title]');
    var artist = root.querySelector('[data-nfp-artist]');
    var info = root.querySelector('.nfp__info');
    var live = root.querySelector('[data-nfp-live]');
    var count = root.querySelector('[data-nfp-count]');
    var error = root.querySelector('[data-nfp-error]');
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
      return config.streams[streamKey] || config.streams.primary || config.streams.secondary;
    }

    function isCurrentStream(url) {
      try {
        return new URL(audio.src, window.location.href).href === new URL(url, window.location.href).href;
      } catch (error) {
        return audio.src === url;
      }
    }

    function setStream(force, reportMissing) {
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
      root.classList.toggle('is-playing', value);
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
      var changed = root.classList.contains('is-buffering') !== Boolean(value);
      root.classList.toggle('is-buffering', value);
      if (changed) renderLiveStatus();
    }

    function showError(value, message) {
      if (!error) return;
      if (message) error.textContent = message;
      error.hidden = !value;
    }

    function applyCover(src) {
      currentArt = src || '';
      if (src) {
        art.src = src;
        art.hidden = false;
        cover.classList.remove('is-placeholder');
        cover.classList.toggle('is-fallback-art', Boolean(config.fallbackArt && src === config.fallbackArt));
      } else {
        art.removeAttribute('src');
        art.hidden = true;
        cover.classList.add('is-placeholder');
        cover.classList.remove('is-fallback-art');
        if (placeholder) placeholder.textContent = config.stationLabel;
      }
    }

    function preloadCover(src) {
      if (!art || !cover) return Promise.resolve('');
      if (src && failedArtUrls[src]) {
        src = src === config.fallbackArt ? '' : config.fallbackArt;
      }
      if (!src || src === currentArt) return Promise.resolve(src || '');

      return new Promise(function (resolve) {
        var loader = new window.Image();
        loader.onload = function () {
          resolve(src);
        };
        loader.onerror = function () {
          failedArtUrls[src] = true;
          if (src !== config.fallbackArt && config.fallbackArt) {
            preloadCover(config.fallbackArt).then(resolve);
          } else {
            resolve('');
          }
        };
        loader.src = src;
      });
    }

    function preloadCoverWithTimeout(src, timeout) {
      var fallback = config.fallbackArt || currentArt || '';
      var finalCover = preloadCover(src);
      return {
        initial: Promise.race([
          finalCover,
          new Promise(function (resolve) {
            window.setTimeout(function () {
              resolve(fallback);
            }, timeout);
          })
        ]),
        final: finalCover
      };
    }

    function setCover(src) {
      if (!art || !cover) return;
      if (src && failedArtUrls[src]) {
        src = src === config.fallbackArt ? '' : config.fallbackArt;
      }
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

    function setTonearmAngle(angle) {
      var safeAngle = Math.max(TONEARM_START_ANGLE, Math.min(TONEARM_END_ANGLE, Number(angle) || TONEARM_REST_ANGLE));
      root.style.setProperty('--nfp-tonearm-angle', safeAngle.toFixed(2) + 'deg');
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
      currentTrackKey = payload.key;
      pendingTrackKey = '';
      title.textContent = payload.title;
      artist.textContent = payload.artist;
      pendingArt = '';
      applyCover(coverSrc);
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
      stopBoundaryPolling();
      if (trackTransitionTimer) {
        window.clearTimeout(trackTransitionTimer);
        trackTransitionTimer = null;
      }
      if (scratchAnimation) {
        scratchAnimation.cancel();
        scratchAnimation = null;
      }

      var coverLoad = preloadCoverWithTimeout(payload.art, 650);
      coverLoad.initial.then(function (coverSrc) {
        if (destroyed || transitionId !== trackTransitionId) return;

        var shouldAnimate = config.theme === 'vinyl' && !reduceMotion && root.classList.contains('is-playing') && cover && typeof cover.animate === 'function';
        coverLoad.final.then(function (finalCoverSrc) {
          if (!finalCoverSrc || finalCoverSrc === coverSrc || destroyed || transitionId !== trackTransitionId) return;

          var applyLateCover = function () {
            if (destroyed || currentTrackKey !== payload.key) return;
            applyCover(finalCoverSrc);
            if (!reduceMotion && art && typeof art.animate === 'function') {
              art.animate([{ opacity: 0.55 }, { opacity: 1 }], { duration: 240, easing: 'ease-out' });
            }
          };

          if (scratchAnimation && scratchAnimation.playState !== 'finished') {
            scratchAnimation.finished.then(applyLateCover).catch(function () {});
          } else {
            applyLateCover();
          }
        });

        if (!shouldAnimate) {
          commitTrackTransition(transitionId, payload, coverSrc);
          finishTrackTransition(transitionId, payload);
          return;
        }

        trackChangeActive = true;
        root.classList.add('is-track-changing');
        setTonearmAngle(TONEARM_START_ANGLE);

        scratchAnimation = cover.animate(
          [
            { rotate: '0deg', offset: 0 },
            { rotate: '-13deg', offset: 0.10 },
            { rotate: '9deg', offset: 0.22 },
            { rotate: '-7deg', offset: 0.33 },
            { rotate: '14deg', offset: 0.45 },
            { rotate: '-10deg', offset: 0.57 },
            { rotate: '6deg', offset: 0.67 },
            { rotate: '-12deg', offset: 0.76 },
            { rotate: '-5deg', offset: 0.83 },
            { rotate: '7deg', offset: 0.91 },
            { rotate: '0deg', offset: 1 }
          ],
          {
            duration: TRACK_SCRATCH_DURATION_MS,
            easing: 'cubic-bezier(.32, 0, .18, 1)',
            iterations: 1
          }
        );

        if (info && typeof info.animate === 'function') {
          info.animate(
            [
              { opacity: 1, offset: 0 },
              { opacity: 0.22, offset: 0.48 },
              { opacity: 0.22, offset: 0.54 },
              { opacity: 1, offset: 0.68 },
              { opacity: 1, offset: 1 }
            ],
            { duration: TRACK_SCRATCH_DURATION_MS, easing: 'ease-out', iterations: 1 }
          );
        }

        trackTransitionTimer = window.setTimeout(function () {
          trackTransitionTimer = null;
          commitTrackTransition(transitionId, payload, coverSrc);
        }, TRACK_SCRATCH_COMMIT_MS);

        scratchAnimation.finished.then(function () {
          finishTrackTransition(transitionId, payload);
        }).catch(function () {});

      });
    }

    function updateTonearm() {
      if (trackChangeActive) return;
      if (!(trackDuration > 0)) {
        if (config.theme === 'vinyl') setTonearmAngle(TONEARM_REST_ANGLE);
        return;
      }

      var elapsed = trackElapsed;
      if (trackSyncedAt > 0) {
        elapsed += Math.max(0, (Date.now() - trackSyncedAt) / 1000);
      }
      var remaining = Math.max(0, trackDuration - elapsed);
      if (remaining <= TRACK_CHANGE_LEAD_SECONDS) {
        startBoundaryPolling();
      }

      var progress = Math.max(0, Math.min(1, elapsed / trackDuration));
      if (config.theme === 'vinyl' && !reduceMotion) {
        setTonearmAngle(TONEARM_START_ANGLE + ((TONEARM_END_ANGLE - TONEARM_START_ANGLE) * progress));
      }
    }

    function syncTonearm(nowPlaying) {
      var duration = Number(nowPlaying && nowPlaying.duration);
      var elapsed = Number(nowPlaying && nowPlaying.elapsed);

      if (!nowPlaying) {
        currentTrackKey = '';
        stopBoundaryPolling();
      }

      if (Number.isFinite(duration) && duration > 0 && Number.isFinite(elapsed) && elapsed >= 0) {
        trackDuration = duration;
        trackElapsed = Math.min(elapsed, duration);
        trackSyncedAt = Date.now();
      } else {
        trackDuration = 0;
        trackElapsed = 0;
        trackSyncedAt = 0;
      }

      updateTonearm();
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
      var playPromise = audio.play();
      if (playPromise && typeof playPromise.catch === 'function') {
        playPromise.catch(function () {
          reconnect();
        });
      }
    }

    function play() {
      showError(false);
      reconnects = 0;
      startPlayback(false);
    }

    function pause() {
      playRequested = false;
      if (reconnectTimer) {
        window.clearTimeout(reconnectTimer);
        reconnectTimer = null;
      }
      audio.pause();
      setPlaying(false);
    }

    function reconnect() {
      if (!playRequested || reconnectTimer) return;
      if (reconnects >= MAX_RECONNECTS) {
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
      reconnects = 0;
      showError(false);
      setPlaying(true);
      setBuffering(false);
    });

    audio.addEventListener('playing', function () {
      reconnects = 0;
      setPlaying(true);
      setBuffering(false);
    });

    audio.addEventListener('pause', function () {
      setPlaying(false);
      setBuffering(false);
    });

    audio.addEventListener('waiting', function () {
      if (!audio.paused) setBuffering(true);
    });

    audio.addEventListener('stalled', reconnect);
    audio.addEventListener('suspend', function () {
      if (!audio.paused && audio.readyState < 3) setBuffering(true);
    });

    audio.addEventListener('canplay', function () {
      setBuffering(false);
    });

    audio.addEventListener('error', function () {
      reconnect();
    });

    streamButtons.forEach(function (button) {
      button.addEventListener('click', function () {
        streamKey = button.getAttribute('data-nfp-stream') === 'secondary' ? 'secondary' : 'primary';
        streamButtons.forEach(function (item) {
          item.classList.toggle('is-active', item === button);
          item.setAttribute('aria-pressed', item === button ? 'true' : 'false');
        });

        var wasPlaying = !audio.paused;
        if (wasPlaying) {
          reconnects = 0;
          startPlayback(true);
        }
      });
    });

    function refresh() {
      if (destroyed) return;

      if (!config.apiUrl) {
        title.textContent = config.stationLabel;
        artist.textContent = 'Прямой поток';
        setLiveStatus(selectedStream() ? 'live' : 'offline', selectedStream() ? 'В эфире ' + currentStationName : 'Не настроено: ' + currentStationName);
        count.textContent = '';
        setCover(config.fallbackArt);
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
          var nowPlaying = data && data.now_playing ? data.now_playing : {};
          var song = nowPlaying.song || {};
          var nextTrackKey = trackKey(song);
          var elapsed = Number(nowPlaying.elapsed);
          var trackRestarted = Boolean(currentTrackKey) && Number.isFinite(elapsed) && elapsed >= 0 && elapsed + 2 < trackElapsed;
          var payload = {
            key: nextTrackKey || currentTrackKey,
            title: song.title || song.text || config.stationLabel,
            artist: song.artist || (data.station && data.station.description) || 'Вне всяких стилей',
            art: song.art || config.fallbackArt,
            nowPlaying: nowPlaying,
            receivedAt: Date.now()
          };
          currentStationName = data.station && data.station.name ? data.station.name : config.stationLabel;
          setLiveStatus(data.is_online ? 'live' : 'offline', (data.is_online ? 'В эфире ' : 'Нет эфира: ') + currentStationName);
          count.textContent = data.listeners && data.listeners.current != null
            ? data.listeners.current + ' слушают'
            : config.stationLabel;

          if (!currentTrackKey) {
            currentTrackKey = payload.key;
            title.textContent = payload.title;
            artist.textContent = payload.artist;
            setCover(payload.art);
            syncTonearm(nowPlaying);
          } else if ((payload.key && payload.key !== currentTrackKey && payload.key !== pendingTrackKey) || (trackRestarted && !pendingTrackKey)) {
            beginTrackTransition(payload);
          } else if (!trackChangeActive && !pendingTrackKey) {
            syncTonearm(nowPlaying);
          }
          hasMetadata = true;
        })
        .catch(function (fetchError) {
          if (fetchError && fetchError.name === 'AbortError') return;
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
      playRequested = false;

      if (root._nfpTimer) window.clearInterval(root._nfpTimer);
      if (root._nfpTonearmTimer) window.clearInterval(root._nfpTonearmTimer);
      if (reconnectTimer) window.clearTimeout(reconnectTimer);
      if (trackChangeTimer) window.clearTimeout(trackChangeTimer);
      if (trackTransitionTimer) window.clearTimeout(trackTransitionTimer);
      if (metadataController) metadataController.abort();
      if (scratchAnimation) scratchAnimation.cancel();
      stopVisualizer(false);

      audio.pause();
      audio.removeAttribute('src');
      audio.load();
      if (audioContext && typeof audioContext.close === 'function') audioContext.close().catch(function () {});

      root.removeAttribute('data-ready');
      delete root._nfpDestroy;
      unwatchPlayer(root);
    }

    setCover(config.fallbackArt);
    setStaticBars();
    setTonearmAngle(TONEARM_REST_ANGLE);
    refresh();
    root._nfpTimer = window.setInterval(refresh, REFRESH_MS);
    root._nfpTonearmTimer = window.setInterval(updateTonearm, 1000);
    root._nfpDestroy = destroy;
    watchPlayer(root);
  }

  function initAll(scope) {
    var context = scope && scope.querySelectorAll ? scope : document;
    context.querySelectorAll('[data-nfp]').forEach(initPlayer);
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

  if (window.jQuery && window.elementorFrontend && window.elementorFrontend.hooks) {
    window.elementorFrontend.hooks.addAction('frontend/element_ready/net-fm-player.default', function ($scope) {
      initAll($scope && $scope[0] ? $scope[0] : document);
    });
    window.elementorFrontend.hooks.addAction('frontend/element_ready/shortcode.default', function ($scope) {
      initAll($scope && $scope[0] ? $scope[0] : document);
    });
  }
})();


