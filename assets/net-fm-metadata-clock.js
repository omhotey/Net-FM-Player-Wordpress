/* Experimental: dispatch in-band metadata against an audio playback clock. */
(function (root) {
  'use strict';
  function Clock(dispatch, prepare, leadSeconds) {
    this.dispatch = dispatch;
    this.prepare = prepare;
    this.lead = Math.max(0, Number(leadSeconds) || 0);
    this.queue = [];
    this.sequence = 0;
  }
  Clock.prototype.enqueue = function (time, value) {
    if (!Number.isFinite(time) || time < 0) throw new TypeError('Invalid media timestamp');
    if (this.queue.length >= 256) throw new RangeError('Metadata queue is full');
    var item = { time: time, value: value, prepared: false, sequence: this.sequence++ };
    this.queue.push(item);
    this.queue.sort(function (a, b) { return a.time - b.time || a.sequence - b.sequence; });
  };
  Clock.prototype.advance = function (time, playing) {
    if (!playing || !Number.isFinite(time)) return;
    while (this.queue.length && this.queue[0].time <= time) {
      this.dispatch(this.queue.shift().value);
    }
    for (var i = 0; i < this.queue.length; i++) {
      var item = this.queue[i];
      if (item.time > time + this.lead) break;
      if (!item.prepared) { item.prepared = true; this.prepare(item.value); }
    }
  };
  Clock.prototype.clear = function () { this.queue = []; };
  if (typeof module === 'object' && module.exports) module.exports = Clock;
  else root.NetFmMetadataClock = Clock;
}(typeof window !== 'undefined' ? window : globalThis));
