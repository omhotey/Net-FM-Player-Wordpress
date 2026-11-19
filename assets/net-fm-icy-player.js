/* Experimental one-connection ICY / MPEG audio transport. */
(function (root) {
  'use strict';
  function Player(audio, callbacks) {
    this.audio = audio;
    this.callbacks = callbacks;
    this.generation = 0;
  }
  Player.supported = function () {
    return !!(root.MediaSource && root.MediaSource.isTypeSupported('audio/mpeg') && root.NetFmIcyDemux && root.NetFmMp3Frames && root.NetFmMetadataClock && root.AbortController && root.TextDecoder);
  };
  Player.prototype.stop = function () {
    this.generation++;
    if (this.controller) this.controller.abort();
    if (this.timer) root.clearInterval(this.timer);
    if (this.startupTimer) root.clearTimeout(this.startupTimer);
    if (this.objectUrl) root.URL.revokeObjectURL(this.objectUrl);
    this.controller = null; this.timer = null; this.startupTimer = null; this.objectUrl = null;
    this.active = false;
  };
  Player.prototype.start = function (url) {
    var self = this;
    self.stop();
    var generation = self.generation;
    var controller = new root.AbortController();
    self.controller = controller; self.active = true;
    var media = new root.MediaSource();
    var objectUrl = root.URL.createObjectURL(media);
    self.objectUrl = objectUrl;
    var chunks = [], pending = [], sourceBuffer = null, batch = [];
    var clock = new root.NetFmMetadataClock(function (value) { self.callbacks.onTrack(value); }, function (value) { self.callbacks.onPrepare(value); }, 0.5);
    function alive() { return self.active && self.generation === generation; }
    function fail(error) {
      if (!alive()) return;
      self.stop();
      self.callbacks.onFailure(error);
    }
    function pump() {
      if (!alive() || !sourceBuffer || sourceBuffer.updating) return;
      try {
        if (sourceBuffer.buffered.length && self.audio.currentTime > 30 && sourceBuffer.buffered.start(0) < self.audio.currentTime - 20) {
          sourceBuffer.remove(0, self.audio.currentTime - 20); return;
        }
        if (chunks.length) sourceBuffer.appendBuffer(chunks.shift());
      } catch (error) { fail(error); }
    }
    self.startupTimer = root.setTimeout(function () { fail(new Error('ICY startup timed out')); }, 12000);
    var frames = new root.NetFmMp3Frames(function (bytes, offset, time) {
      if (self.startupTimer) { root.clearTimeout(self.startupTimer); self.startupTimer = null; }
      while (pending.length && pending[0].offset <= offset) {
        var tag = pending.shift();
        clock.enqueue(time, tag.text);
      }
      batch.push(bytes);
    });
    media.addEventListener('sourceopen', function () {
      if (!alive()) return;
      try {
        sourceBuffer = media.addSourceBuffer('audio/mpeg');
        sourceBuffer.mode = 'sequence';
        sourceBuffer.addEventListener('updateend', pump);
        sourceBuffer.addEventListener('error', function () { fail(new Error('MediaSource error')); });
      } catch (error) { fail(error); return; }
      fetch(url, { headers: { 'Icy-MetaData': '1' }, signal: controller.signal, cache: 'no-store' })
        .then(function (response) {
          if (!response.ok || !response.body) throw new Error('Stream unavailable');
          var interval = Number(response.headers.get('icy-metaint'));
          if (!Number.isSafeInteger(interval) || interval < 1024 || interval > 16777216) throw new Error('ICY metadata unavailable');
          var demux = new root.NetFmIcyDemux(interval, function (bytes) { frames.push(bytes); }, function (bytes, offset) {
            var text = self.callbacks.decode(bytes).replace(/\0+$/, '');
            var result = /StreamTitle='([^']*)'/.exec(text);
            if (result && result[1]) {
              if (pending.length >= 256) throw new Error('ICY metadata queue full');
              pending.push({ offset: offset, text: result[1] });
            }
          });
          var reader = response.body.getReader();
          function read() {
            return reader.read().then(function (result) {
              if (!alive()) return;
              if (result.done) throw new Error('Stream ended');
              batch = []; demux.push(result.value);
              if (batch.length) {
                var length = batch.reduce(function (sum, part) { return sum + part.length; }, 0);
                var bytes = new Uint8Array(length), at = 0;
                batch.forEach(function (part) { bytes.set(part, at); at += part.length; });
                chunks.push(bytes);
                if (chunks.reduce(function (sum, part) { return sum + part.length; }, 0) > 2 * 1024 * 1024) throw new Error('Audio buffer queue full');
                pump();
              }
              return read();
            });
          }
          return read();
        }).catch(function (error) { if (error.name !== 'AbortError') fail(error); });
    }, { once: true });
    self.audio.src = objectUrl;
    self.timer = root.setInterval(function () { if (alive()) clock.advance(self.audio.currentTime, !self.audio.paused && self.audio.readyState >= 3); }, 50);
    return self.audio.play();
  };
  root.NetFmIcyPlayer = Player;
}(window));
