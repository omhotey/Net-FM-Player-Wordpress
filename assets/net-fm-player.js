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
      stationLabel: 'NET FM'
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
        stationLabel: parsed.stationLabel || defaults.stationLabel
      };
    } catch (error) {
      return defaults;
    }
  }

  function storageKey(root, suffix) {
    var config = parseConfig(root);
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
    var analyserCheckUrl = '';
    var analyserCheckPromise = null;
    var analyserAllowedUrl = '';
    var analyserDeniedUrl = '';

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

    if (!audio || !toggle || !mute) return;

    audio.preload = 'auto';
    audio.volume = volume ? Number(volume.value || 0.85) : 0.85;

    function selectedStream() {
      return config.streams[streamKey] || config.streams.primary || config.streams.secondary;
    }

    function setStream(force) {
      var next = selectedStream();
      if (!next) {
        showError(true, 'Поток не настроен. Укажите Stream URL в настройках.');
        return;
      }

      if (force || audio.src !== next) {
        audio.src = next;
        audio.load();
      }
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
      if (eqNote) eqNote.hidden = false;
      setStaticBars();
    }

    function canUseAnalyser(streamUrl) {
      if (!streamUrl) return Promise.resolve(false);
      if (isSameOriginUrl(streamUrl) || analyserAllowedUrl === streamUrl) return Promise.resolve(true);
      if (analyserDeniedUrl === streamUrl) return Promise.resolve(false);
      if (analyserCheckUrl === streamUrl && analyserCheckPromise) return analyserCheckPromise;

      analyserCheckUrl = streamUrl;

      if (!window.fetch) {
        analyserDeniedUrl = streamUrl;
        return Promise.resolve(false);
      }

      var controller = window.AbortController ? new AbortController() : null;
      var timer = controller ? window.setTimeout(function () { controller.abort(); }, 1800) : null;
      var options = { method: 'GET', mode: 'cors', cache: 'no-store' };
      if (controller) options.signal = controller.signal;

      analyserCheckPromise = fetch(streamUrl, options)
        .then(function (response) {
          if (timer) window.clearTimeout(timer);
          analyserAllowedUrl = streamUrl;
          analyserDeniedUrl = '';
          if (controller) controller.abort();
          return Boolean(response);
        })
        .catch(function () {
          if (timer) window.clearTimeout(timer);
          analyserDeniedUrl = streamUrl;
          return false;
        })
        .then(function (allowed) {
          analyserCheckPromise = null;
          return allowed;
        });

      return analyserCheckPromise;
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
        if (eqNote) eqNote.hidden = false;
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
        if (eqNote) eqNote.hidden = false;
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
      canUseAnalyser(selectedStream()).then(function (allowed) {
        if (!allowed) {
          markAnalyserBlocked();
          return;
        }

        root.classList.remove('no-analyser');
        if (!setupAnalyser()) return;
        if (audioContext && audioContext.state === 'suspended') audioContext.resume().catch(function () {});
        if (!visualizerFrame) drawVisualizer();
      });
    }

function stopVisualizer(reset) {
      if (visualizerFrame) {
        window.cancelAnimationFrame(visualizerFrame);
        visualizerFrame = null;
      }
      if (reset !== false) setStaticBars();
    }

    function play() {
      showError(false);
      setStream(false);
      var playPromise = audio.play();
      if (playPromise && typeof playPromise.catch === 'function') {
        playPromise.catch(function () {
          showError(true, 'Поток не запустился. Проверьте Stream URL.');
          setPlaying(false);
          setBuffering(false);
        });
      }
    }

    function pause() {
      audio.pause();
      setPlaying(false);
    }

    function reconnect() {
      if (audio.paused || reconnectTimer || reconnects >= MAX_RECONNECTS) return;
      reconnects += 1;
      setBuffering(true);
      reconnectTimer = window.setTimeout(function () {
        reconnectTimer = null;
        setStream(true);
        audio.play().catch(function () {
          showError(true, 'Поток прервался. Проверьте Stream URL или сервер вещания.');
          setBuffering(false);
        });
      }, RECONNECT_DELAY_MS);
    }

    toggle.addEventListener('click', function () {
      if (audio.paused) play();
      else pause();
    });

    mute.addEventListener('click', function () {
      audio.muted = !audio.muted;
      root.classList.toggle('is-muted', audio.muted);
      mute.setAttribute('aria-label', audio.muted ? 'Включить звук' : 'Выключить звук');
    });

    if (volume) {
      volume.addEventListener('input', function () {
        audio.volume = Number(volume.value);
        if (audio.volume > 0 && audio.muted) {
          audio.muted = false;
          root.classList.remove('is-muted');
        }
      });
    }

    audio.addEventListener('play', function () {
      reconnects = 0;
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
      if (!audio.paused) reconnect();
      else showError(true, 'Поток не загрузился. Проверьте Stream URL.');
    });

    root.querySelectorAll('[data-nfp-stream]').forEach(function (button) {
      button.addEventListener('click', function () {
        streamKey = button.getAttribute('data-nfp-stream') === 'secondary' ? 'secondary' : 'primary';
        root.querySelectorAll('[data-nfp-stream]').forEach(function (item) {
          item.classList.toggle('is-active', item === button);
        });

        var wasPlaying = !audio.paused;
        setStream(true);
        if (wasPlaying) play();
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
          window.localStorage.setItem(storageKey(root, 'reaction:' + (currentSongId || 'current')), value);
        } catch (error) {}
        applyReaction(value);
      });
    });

    function restoreReaction(songId) {
      var value = '';
      try {
        value = window.localStorage.getItem(storageKey(root, 'reaction:' + (songId || 'current'))) || '';
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

          if (songId !== currentSongId) {
            currentSongId = songId;
            restoreReaction(currentSongId);
          }
        })
        .catch(function () {
          if (!root.classList.contains('is-buffering')) live.textContent = 'Метаданные недоступны';
          count.textContent = config.stationLabel;
          setCover(config.fallbackArt);
        });
    }

    setStream(false);
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


