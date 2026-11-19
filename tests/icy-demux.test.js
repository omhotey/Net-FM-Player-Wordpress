const assert = require('node:assert/strict');
const IcyDemux = require('../assets/net-fm-icy.js');

const encoder = new TextEncoder();
const first = new Uint8Array(32);
first.set(encoder.encode("StreamTitle='A';"));
const second = new Uint8Array(32);
second.set(encoder.encode("StreamTitle='Jingle';"));
const input = Uint8Array.from([1, 2, 3, 4, 2, ...first, 5, 6, 7, 8, 0, 9, 10, 11, 12, 2, ...second, 13]);

for (const size of [1, 2, 3, 4, 5, 17, input.length]) {
  const audio = [];
  const metadata = [];
  const demux = new IcyDemux(4, bytes => audio.push(...bytes), (bytes, offset) => metadata.push([new TextDecoder().decode(bytes).replace(/\0+$/, ''), offset]));
  for (let start = 0; start < input.length; start += size) demux.push(input.slice(start, start + size));
  assert.deepEqual(audio, [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13]);
  assert.deepEqual(metadata, [["StreamTitle='A';", 4], ["StreamTitle='Jingle';", 12]]);
}
assert.throws(() => new IcyDemux(0, () => {}, () => {}), TypeError);
console.log('ICY framing handles arbitrary chunk boundaries, empty metadata, tracks and jingles');
