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

const playback = vm.createContext({
  explicitlyPaused: false, playRequested: false, reconnects: 0, fallbackAttempted: false,
  tonearmNeedsPlaybackSync: false, attempts: [],
  showError() {}, showStatus() {}, clearReconnectTimer() {}, clearStalledTimer() {},
  setPlaying() {}, setBuffering() {}, refresh() {},
  audio: { pause() {} },
  startPlayback(force) { playback.attempts.push(force); }
});
vm.runInContext(extract('play') + '\n' + extract('pause'), playback);
playback.play();
assert.equal(playback.attempts[0], false, 'First play does not unnecessarily reload the stream');
playback.pause();
assert.equal(playback.explicitlyPaused, true);
playback.play();
assert.equal(playback.attempts[1], true, 'Resume reopens the live stream instead of old buffered audio');
assert.equal(playback.explicitlyPaused, false);
console.log('Explicit pause and live resume checks passed');

const classes = new Set(['is-playing']);
const mechanics = vm.createContext({
  bufferingTimer: null, trackEndTimer: 42, tonearmClockRunning: true, tonearmFrameAt: 1,
  audio: { paused: false }, events: [],
  root: { classList: { contains: value => classes.has(value), toggle(value, enabled) { enabled ? classes.add(value) : classes.delete(value); } } },
  window: { clearTimeout: id => mechanics.events.push('clear:' + id) },
  stopTonearmAnimation() { mechanics.events.push('stop'); },
  resumeTonearmClock() { mechanics.events.push('resume'); },
  renderLiveStatus() {}
});
vm.runInContext(extract('pauseTonearmClock') + '\n' + extract('setBuffering'), mechanics);
mechanics.setBuffering(true);
assert.equal(mechanics.trackEndTimer, null, 'Buffering cancels predicted track-end timer');
assert.equal(mechanics.tonearmClockRunning, false, 'Buffering freezes tonearm');
assert.ok(mechanics.events.includes('clear:42'));
mechanics.setBuffering(false);
assert.ok(mechanics.events.includes('resume'), 'Playback recovery resumes tonearm');
console.log('Tonearm freezes while buffering and clears predicted end timer');
