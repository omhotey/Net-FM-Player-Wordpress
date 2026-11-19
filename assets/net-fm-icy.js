/* ICY framing only. Not connected to playback until timing is validated. */
(function (root) {
  'use strict';

  function IcyDemux(interval, onAudio, onMetadata) {
    if (!Number.isSafeInteger(interval) || interval < 1) throw new TypeError('Invalid ICY interval');
    this.interval = interval;
    this.remaining = interval;
    this.metadataRemaining = -1;
    this.metadata = [];
    this.audioBytes = 0;
    this.onAudio = onAudio;
    this.onMetadata = onMetadata;
  }

  IcyDemux.prototype.push = function (chunk) {
    if (!(chunk instanceof Uint8Array)) throw new TypeError('Expected byte array');
    var position = 0;
    while (position < chunk.length) {
      if (this.remaining > 0) {
        var audioLength = Math.min(this.remaining, chunk.length - position);
        var audio = chunk.slice(position, position + audioLength);
        this.audioBytes += audioLength;
        this.remaining -= audioLength;
        position += audioLength;
        this.onAudio(audio);
      } else if (this.metadataRemaining === -1) {
        this.metadataRemaining = chunk[position++] * 16;
        this.metadata = [];
        if (this.metadataRemaining === 0) {
          this.metadataRemaining = -1;
          this.remaining = this.interval;
        }
      } else {
        var length = Math.min(this.metadataRemaining, chunk.length - position);
        for (var i = 0; i < length; i++) this.metadata.push(chunk[position + i]);
        position += length;
        this.metadataRemaining -= length;
        if (this.metadataRemaining === 0) {
          this.onMetadata(new Uint8Array(this.metadata), this.audioBytes);
          this.metadata = [];
          this.metadataRemaining = -1;
          this.remaining = this.interval;
        }
      }
    }
  };

  if (typeof module === 'object' && module.exports) module.exports = IcyDemux;
  else root.NetFmIcyDemux = IcyDemux;
}(typeof window !== 'undefined' ? window : globalThis));
