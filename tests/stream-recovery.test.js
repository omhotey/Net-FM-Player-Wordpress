const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../assets/net-fm-player.js'), 'utf8');

function extract(name) {
  const start = source.indexOf('    function ' + name + '(');
  assert.notEqual(start, -1, name + ' must exist');
  let depth = 0;
  let body = source.indexOf('{', start);
  for (let i = body; i < source.length; i++) {
    if (source[i] === '{') depth++;
    if (source[i] === '}' && --depth === 0) return source.slice(start, i + 1);
  }
  throw new Error('Unterminated function: ' + name);
}

const context = vm.createContext({
  config: { streams: { primary: '', secondary: '', hls: 'https://example.test/live.m3u8' } },
  streamKey: 'secondary', supportsNativeHls: () => false,
  playRequested: true, reconnectTimer: null, reconnects: 3, MAX_RECONNECTS: 3,
  fallbackAttempted: false, setActiveStreamButton() {}, setBuffering() {}, setPlaying() {},
  messages: [], attempts: [],
  window: { setTimeout() { throw new Error('Unexpected retry timer'); } }
});
context.showStatus = message => context.messages.push(message);
context.showError = (value, message) => context.messages.push(message);
context.startPlayback = force => context.attempts.push({ stream: context.streamKey, force });
vm.runInContext(extract('selectedStream') + '\n' + extract('reconnect'), context);
assert.equal(context.selectedStream(), '', 'Unsupported HLS-only stream must not be selected');
context.config.streams.primary = 'https://example.test/primary';
context.config.streams.secondary = 'https://example.test/secondary';
context.streamKey = 'primary';
context.reconnect();
assert.equal(context.streamKey, 'secondary');
assert.equal(context.fallbackAttempted, true);
assert.equal(context.attempts.length, 1, 'The backup must be attempted once');
context.reconnects = 3;
context.reconnect();
assert.equal(context.playRequested, false, 'Failure of the backup must stop rather than loop');
assert.equal(context.attempts.length, 1);
console.log('Stream selection and bounded fallback checks passed');
