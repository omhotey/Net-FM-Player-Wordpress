(function () {
  var REFRESH_MS = 15000;
  var RECONNECT_DELAY_MS = 1800;
  var MAX_RECONNECTS = 3;

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

  function storageKey(config, suffix) {
    return 'nfp:' + config.stationLabel + ':' + suffix;
  }

  function initPlayer(root) {
    if (!root || root.getAttribute('data-ready') === '1') return;
    root.setAttribute('data-ready', '1');

    var config = parseConfig(root);
    var streamKey = config.defaultStream;
    var reconnects = 0;
    var reconnectTimer = null;
    var currentSongId = '';
    var audioContext = null;
    var analyser = null;
    var sourceNode = null;
    var frequencyData = null;
    var visualizerFrame = null;
    var analyserUnavailable = false;
    var currentArt = '';
    var failedArtUrls = {};
    var playRequested = false;
    var hasMetadata = false;

    var audio = root.querySelector('[data-nfp-audio]');
    var toggle = root.querySelector('[data-nfp-toggle]');
    var mute = root.querySelector('[data-nfp-mute]');
    var volume = root.querySelector('[data-nfp-volume]');
    var cover = root.querySelector('[data-nfp-cover]');
    var art = root.querySelector('[data-nfp-art]');
    var placeholder = root.querySelector('[data-nfp-placeholder]');
    var title = root.querySelector('[data-nfp-title]');
    var artist = root.querySelector('[data-nfp-artist]');
    var live = root.querySelector('[data-nfp-live]');
    var count = root.querySelector('[data-nfp-count]');
    var error = root.querySelector('[data-nfp-error]');
    var eqNote = root.querySelector('[data-nfp-eq-note]');
    var eqBars = root.querySelectorAll('.nfp__eq i');
    var reactions = root.querySelectorAll('[data-nfp-reaction]');
    var streamButtons = root.querySelectorAll('[data-nfp-stream]');

    if (!audio || !toggle || !mute) return;

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

    function setBuffering(value) {
      root.classList.toggle('is-buffering', value);
      if (live) live.textContent = value ? 'Буферизация' : 'В эфире';
    }

    function showError(value, message) {
      if (!error) return;
      if (message) error.textContent = message;
      error.hidden = !value;
    }

    function setCover(src) {
      if (!art || !cover) return;
      if (src && failedArtUrls[src]) {
        src = src === config.fallbackArt ? '' : config.fallbackArt;
      }
      if (src === currentArt) return;
      currentArt = src || '';
      if (src) {
        art.src = src;
        art.hidden = false;
        cover.classList.remove('is-placeholder');
      } else {
        art.removeAttribute('src');
        art.hidden = true;
        cover.classList.add('is-placeholder');
        if (placeholder) placeholder.textContent = config.stationLabel;
      }
    }

    if (art) {
      art.addEventListener('error', function () {
        if (currentArt) failedArtUrls[currentArt] = true;
        setCover(currentArt === config.fallbackArt ? '' : config.fallbackArt);
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

    function applyReaction(value) {
      reactions.forEach(function (button) {
        var active = button.getAttribute('data-nfp-reaction') === value;
        button.classList.toggle('is-active', active);
        button.setAttribute('aria-pressed', active ? 'true' : 'false');
      });
    }

    reactions.forEach(function (button) {
      button.addEventListener('click', function () {
        var next = button.getAttribute('data-nfp-reaction');
        var active = button.classList.contains('is-active');
        var value = active ? '' : next;
        try {
          window.localStorage.setItem(storageKey(config, 'reaction:' + (currentSongId || 'current')), value);
        } catch (error) {}
        applyReaction(value);
      });
    });

    function restoreReaction(songId) {
      var value = '';
      try {
        value = window.localStorage.getItem(storageKey(config, 'reaction:' + (songId || 'current'))) || '';
      } catch (error) {}
      applyReaction(value);
    }

    function refresh() {
      if (!config.apiUrl) {
        title.textContent = config.stationLabel;
        artist.textContent = 'Прямой поток';
        live.textContent = 'В эфире';
        count.textContent = config.stationLabel;
        setCover(config.fallbackArt);
        return;
      }

      fetch(config.apiUrl, { cache: 'no-store' })
        .then(function (response) {
          if (!response.ok) throw new Error('Now Playing returned ' + response.status);
          return response.json();
        })
        .then(function (data) {
          var song = data && data.now_playing && data.now_playing.song ? data.now_playing.song : {};
          var songId = song.id || song.text || song.title || '';
          title.textContent = song.title || song.text || config.stationLabel;
          artist.textContent = song.artist || (data.station && data.station.description) || 'Вне всяких стилей';
          if (!root.classList.contains('is-buffering')) live.textContent = data.is_online ? 'В эфире' : 'Нет эфира';
          count.textContent = data.listeners && data.listeners.current != null
            ? data.listeners.current + ' слушают'
            : config.stationLabel;
          setCover(song.art || config.fallbackArt);
          hasMetadata = true;

          if (songId !== currentSongId) {
            currentSongId = songId;
            restoreReaction(currentSongId);
          }
        })
        .catch(function () {
          if (!root.classList.contains('is-buffering')) {
            live.textContent = hasMetadata ? 'Метаданные устарели' : 'Метаданные недоступны';
          }
        });
    }

    setCover(config.fallbackArt);
    setStaticBars();
    refresh();
    root._nfpTimer = window.setInterval(refresh, REFRESH_MS);
  }

  function initAll(scope) {
    var context = scope && scope.querySelectorAll ? scope : document;
    context.querySelectorAll('[data-nfp]').forEach(initPlayer);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      initAll(document);
    });
  } else {
    initAll(document);
  }

  window.NetFmPlayer = { init: initAll };

  if (window.jQuery && window.elementorFrontend && window.elementorFrontend.hooks) {
    window.elementorFrontend.hooks.addAction('frontend/element_ready/net-fm-player.default', function ($scope) {
      initAll($scope && $scope[0] ? $scope[0] : document);
    });
    window.elementorFrontend.hooks.addAction('frontend/element_ready/shortcode.default', function ($scope) {
      initAll($scope && $scope[0] ? $scope[0] : document);
    });
  }
})();


