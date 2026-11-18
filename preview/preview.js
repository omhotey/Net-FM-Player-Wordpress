(function () {
  var storageKey = 'nfp_preview_lab_settings_v4';
  var form = document.querySelector('[data-nfp-lab-form]');
  var playerHost = document.querySelector('[data-nfp-lab-player]');
  var status = document.querySelector('[data-nfp-lab-status]');
  var saveButton = document.querySelector('[data-nfp-lab-save]');
  var resetButton = document.querySelector('[data-nfp-lab-reset]');
  var uploadedFallbackArt = '';
  var uploadedPageBackground = '';

  if (!form || !playerHost) return;

  var initialValues = formValues();

  function formValues() {
    var values = {};
    Array.prototype.forEach.call(form.elements, function (field) {
      if (!field.name || field.type === 'file') return;
      values[field.name] = field.type === 'checkbox' ? field.checked : String(field.value);
    });
    return values;
  }

  function setFormValues(values) {
    Object.keys(values || {}).forEach(function (key) {
      var field = form.elements[key];
      if (!field || field.type === 'file') return;
      if (field.type === 'checkbox') field.checked = Boolean(values[key]);
      else field.value = values[key];
    });
  }

  function readUrlValues() {
    var values = {};
    var params = new URLSearchParams(window.location.search);
    params.forEach(function (value, key) {
      var field = form.elements[key];
      if (!field || field.type === 'file') return;
      values[key] = field.type === 'checkbox' ? value === '1' : value;
    });
    return values;
  }

  function rgba(hex, opacity) {
    var value = String(hex || '').replace('#', '');
    if (!/^[0-9a-f]{6}$/i.test(value)) return '';
    return 'rgba(' + parseInt(value.slice(0, 2), 16) + ',' + parseInt(value.slice(2, 4), 16) + ',' + parseInt(value.slice(4, 6), 16) + ',' + opacity + ')';
  }

  function contrastColor(hex) {
    var value = String(hex || '').replace('#', '');
    if (!/^[0-9a-f]{6}$/i.test(value)) return '#ffffff';
    var luminance = (parseInt(value.slice(0, 2), 16) * 299 + parseInt(value.slice(2, 4), 16) * 587 + parseInt(value.slice(4, 6), 16) * 114) / 1000;
    return luminance > 150 ? '#061006' : '#ffffff';
  }

  function apiUrl(values) {
    if (!values.metadataEnabled) return '';
    if (values.fixture && values.fixture !== 'direct') return '?api=' + encodeURIComponent(values.fixture);
    if (values.apiUrl) return values.apiUrl;
    if (!values.azuracastBaseUrl || !values.stationShortcode) return '';
    return values.azuracastBaseUrl.replace(/\/+$/, '') + '/api/nowplaying/' + encodeURIComponent(values.stationShortcode);
  }

  function playerStyle(values) {
    var styles = [];
    if (values.playerBackgroundEnabled) styles.push('--nfp-player-background:' + values.background);
    if (values.playButton) styles.push('--nfp-play-color:' + values.playButton, '--nfp-play-contrast:' + contrastColor(values.playButton));
    if (values.volume) styles.push('--nfp-volume-color:' + values.volume);
    return styles.join(';');
  }

  function updateOutputs(values) {
    var percentFields = [
      ['backgroundOpacity', 'background-opacity'],
      ['pageBackgroundOpacity', 'page-background-opacity'],
      ['shadowOpacity', 'shadow-opacity']
    ];
    percentFields.forEach(function (item) {
      var output = document.querySelector('[data-nfp-lab-' + item[1] + ']');
      if (output) output.textContent = Math.round(Number(values[item[0]]) * 100) + '%';
    });
    [['pageOffset', 'page-offset'], ['shadowBlur', 'shadow-blur'], ['shadowOffsetY', 'shadow-offset']].forEach(function (item) {
      var output = document.querySelector('[data-nfp-lab-' + item[1] + ']');
      if (output) output.textContent = values[item[0]] + 'px';
    });
  }

  function applyPage(values) {
    if (!values.pageBackgroundEnabled) {
      document.body.style.backgroundColor = '';
      document.body.style.backgroundImage = '';
      document.body.style.backgroundPosition = '';
      document.body.style.backgroundSize = '';
      document.body.style.backgroundAttachment = '';
      return;
    }

    document.body.style.backgroundColor = rgba(values.pageBackground, values.pageBackgroundOpacity);
    var image = uploadedPageBackground || values.pageBackgroundImage;
    document.body.style.backgroundImage = image ? 'linear-gradient(' + rgba(values.pageBackground, values.pageBackgroundOpacity) + ',' + rgba(values.pageBackground, values.pageBackgroundOpacity) + '),url("' + image.replace(/"/g, '%22') + '")' : '';
    document.body.style.backgroundPosition = 'center';
    document.body.style.backgroundSize = 'cover';
    document.body.style.backgroundAttachment = 'fixed';
  }

  function applyPosition(values) {
    var playerContainer = playerHost.querySelector('.nfp-container');
    if (!playerContainer) return;
    playerContainer.style.display = 'flex';
    playerContainer.style.minHeight = '100%';
    playerContainer.style.justifyContent = values.horizontalPosition === 'center' ? 'center' : values.horizontalPosition === 'right' ? 'flex-end' : 'flex-start';
    playerContainer.style.alignItems = values.verticalPosition === 'center' ? 'center' : values.verticalPosition === 'bottom' ? 'flex-end' : 'flex-start';
    playerHost.style.padding = values.pageOffset + 'px';
  }

  function apply() {
    var values = formValues();
    var root = playerHost.querySelector('[data-nfp]');
    if (!root) return;
    var layout = values.layout === 'vertical' ? 'vertical' : 'horizontal';
    var streams = { primary: values.primaryUrl || '', secondary: values.secondaryUrl || '', hls: values.hlsUrl || '' };
    var defaultStream = values.defaultStream || 'primary';
    if (!streams[defaultStream]) {
      ['primary', 'secondary', 'hls'].some(function (key) {
        if (!streams[key]) return false;
        defaultStream = key;
        return true;
      });
    }
    var config = {
      apiUrl: apiUrl(values),
      azuracastBaseUrl: values.fixture || !values.metadataEnabled ? '' : values.azuracastBaseUrl,
      stationShortcode: values.fixture || !values.metadataEnabled ? '' : values.stationShortcode,
      streams: streams,
      streamLabels: { primary: values.primaryLabel || 'Основной', secondary: values.secondaryLabel || 'Дополнительный', hls: values.hlsLabel || 'Авто' },
      defaultStream: defaultStream,
      fallbackArt: uploadedFallbackArt || values.fallbackArt || '',
      stationLabel: values.stationLabel || 'NET FM',
      stationLabelOverride: true,
      theme: values.theme === 'vinyl' ? 'vinyl' : 'studio',
      layout: layout
    };
    var classes = root.className.split(/\s+/).filter(function (name) {
      return name && name.indexOf('nfp--theme-') !== 0 && name.indexOf('nfp--layout-') !== 0 && name !== 'is-live' && name !== 'is-offline';
    });
    classes.push('nfp--theme-' + config.theme);
    classes.push('nfp--layout-' + config.layout);
    if (!config.apiUrl) classes.push(config.streams.primary || config.streams.secondary || config.streams.hls ? 'is-live' : 'is-offline');

    if (window.NetFmPlayer) window.NetFmPlayer.destroy(root);
    root.className = classes.join(' ');
    root.setAttribute('data-config', JSON.stringify(config));
    root.setAttribute('style', playerStyle(values));
    root.querySelector('[data-nfp-station-label]').textContent = config.stationLabel;
    root.querySelector('[data-nfp-placeholder-name]').textContent = config.stationLabel;
    var cover = root.querySelector('[data-nfp-cover]');
    var art = root.querySelector('[data-nfp-art]');
    if (config.fallbackArt) art.setAttribute('src', config.fallbackArt);
    else art.removeAttribute('src');
    if (cover) {
      cover.classList.toggle('is-fallback-art', Boolean(config.fallbackArt));
      cover.classList.toggle('is-placeholder', !config.fallbackArt);
    }
    var quality = root.querySelector('.nfp__quality');
    ['primary', 'secondary', 'hls'].forEach(function (key) {
      if (!quality || root.querySelector('[data-nfp-stream="' + key + '"]') || !config.streams[key]) return;
      var button = document.createElement('button');
      button.type = 'button';
      button.className = config.defaultStream === key ? 'is-active' : '';
      button.setAttribute('data-nfp-stream', key);
      button.setAttribute('aria-pressed', config.defaultStream === key ? 'true' : 'false');
      quality.appendChild(button);
    });
    root.querySelectorAll('[data-nfp-stream]').forEach(function (button) {
      var key = button.getAttribute('data-nfp-stream');
      button.hidden = !config.streams[key];
      button.textContent = config.streamLabels[key];
      button.classList.toggle('is-active', config.defaultStream === key);
      button.setAttribute('aria-pressed', config.defaultStream === key ? 'true' : 'false');
    });
    if (window.NetFmPlayer) window.NetFmPlayer.init(root.parentNode);

    applyPage(values);
    applyPosition(values);
    updateOutputs(values);
    window.localStorage.setItem(storageKey, JSON.stringify(values));
    updateUrl(values);
  }

  function updateUrl(values) {
    var params = new URLSearchParams();
    Object.keys(values).forEach(function (key) {
      if (typeof values[key] === 'boolean') {
        if (values[key]) params.set(key, '1');
      } else if (String(values[key]) !== String(initialValues[key] || '')) {
        params.set(key, values[key]);
      }
    });
    var query = params.toString();
    window.history.replaceState(null, '', window.location.pathname + (query ? '?' + query : ''));
  }

  function setUploadedImage(field, callback) {
    var file = field.files && field.files[0];
    if (!file) return;
    callback(URL.createObjectURL(file));
    status.textContent = 'Изображение загружено только для текущего preview.';
    apply();
  }

  function restore() {
    var urlValues = readUrlValues();
    if (urlValues.fixture === 'direct') {
      urlValues.fixture = '';
      urlValues.metadataEnabled = false;
    }
    if (Object.keys(urlValues).length) setFormValues(urlValues);
    else {
      try { setFormValues(JSON.parse(window.localStorage.getItem(storageKey) || '{}')); } catch (error) {}
    }
    apply();
  }

  form.addEventListener('input', function (event) {
    if (event.target.name === 'fallbackArt') uploadedFallbackArt = '';
    if (event.target.name === 'pageBackgroundImage') uploadedPageBackground = '';
    apply();
  });
  form.addEventListener('change', function (event) {
    if (event.target.name === 'fallbackArtFile') return setUploadedImage(event.target, function (url) { uploadedFallbackArt = url; });
    if (event.target.name === 'pageBackgroundFile') return setUploadedImage(event.target, function (url) { uploadedPageBackground = url; });
    apply();
  });
  saveButton.addEventListener('click', function () {
    window.localStorage.setItem(storageKey, JSON.stringify(formValues()));
    status.textContent = 'Настройки сохранены в этом браузере.';
  });
  resetButton.addEventListener('click', function () {
    window.localStorage.removeItem(storageKey);
    uploadedFallbackArt = '';
    uploadedPageBackground = '';
    form.reset();
    setFormValues(initialValues);
    status.textContent = 'Возвращены исходные значения.';
    apply();
  });

  restore();
})();
