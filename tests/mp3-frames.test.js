const assert = require('node:assert/strict');
const Frames = require('../assets/net-fm-mp3-frames.js');
const frame = new Uint8Array(417);
frame.set([0xff, 0xfb, 0x90, 0x00]); // MPEG-1 Layer III, 128 kbps, 44100 Hz
const input = Uint8Array.from([0, 1, ...frame, ...frame, ...frame]);
for (const chunkSize of [1, 2, 3, 127, 417, 1000]) {
  const seen = [];
  const parser = new Frames((data, offset, time, duration) => seen.push({ length: data.length, offset, time, duration }));
  for (let p = 0; p < input.length; p += chunkSize) parser.push(input.slice(p, p + chunkSize));
  assert.equal(seen.length, 3);
  assert.deepEqual(seen.map(x => x.offset), [2, 419, 836]);
  assert.equal(seen[0].length, 417);
  assert.ok(Math.abs(seen[2].time - 2 * 1152 / 44100) < 1e-9);
}
assert.equal(Frames.header(Uint8Array.from([0xff, 0xfb, 0xf0, 0]), 0), null);
console.log('MPEG audio frames retain offsets and timing across split chunks');
