/* MP3 frame timing prototype, not yet connected to the production player. */
(function (root) {
  'use strict';
  function header(data, offset) {
    if (data.length - offset < 4 || data[offset] !== 0xff || (data[offset + 1] & 0xe0) !== 0xe0) return null;
    var version = (data[offset + 1] >> 3) & 3;
    var layer = (data[offset + 1] >> 1) & 3;
    var rateIndex = (data[offset + 2] >> 2) & 3;
    var bitrateIndex = (data[offset + 2] >> 4) & 15;
    if (version === 1 || layer !== 1 || rateIndex === 3 || bitrateIndex === 0 || bitrateIndex === 15) return null;
    var sampleRate = [44100, 48000, 32000][rateIndex] / (version === 3 ? 1 : version === 2 ? 2 : 4);
    var rates = version === 3 ? [0, 32, 40, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320] : [0, 8, 16, 24, 32, 40, 48, 56, 64, 80, 96, 112, 128, 144, 160];
    var bitrate = rates[bitrateIndex] * 1000;
    var samples = version === 3 ? 1152 : 576;
    return { length: Math.floor((version === 3 ? 144 : 72) * bitrate / sampleRate) + ((data[offset + 2] >> 1) & 1), duration: samples / sampleRate };
  }
  function Frames(onFrame) {
    this.pending = new Uint8Array(0);
    this.offset = 0;
    this.seconds = 0;
    this.onFrame = onFrame;
  }
  Frames.prototype.push = function (chunk) {
    var data = new Uint8Array(this.pending.length + chunk.length);
    data.set(this.pending); data.set(chunk, this.pending.length);
    var p = 0;
    while (data.length - p >= 4) {
      var info = header(data, p);
      if (!info) { p++; continue; }
      if (data.length - p < info.length) break;
      this.onFrame(data.slice(p, p + info.length), this.offset + p, this.seconds, info.duration);
      this.seconds += info.duration;
      p += info.length;
    }
    this.offset += p;
    this.pending = data.slice(p);
  };
  Frames.header = header;
  if (typeof module === 'object' && module.exports) module.exports = Frames;
  else root.NetFmMp3Frames = Frames;
}(typeof window !== 'undefined' ? window : globalThis));
