const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../assets/net-fm-player.js'), 'utf8');
function extract(name) {
  const start = source.indexOf('    function ' + name + '(');
  assert.notEqual(start, -1, name + ' must exist');
  let depth = 0;
  for (let i = source.indexOf('{', start); i < source.length; i++) {
    if (source[i] === '{') depth++;
    if (source[i] === '}' && --depth === 0) return source.slice(start, i + 1);
  }
  throw new Error('Unterminated function: ' + name);
}
const pending = [];
const context = vm.createContext({
  nextTrackCache: { key: '', art: '', metadata: null, coverSrc: '', ready: false },
  nextTrackGeneration: 0, destroyed: false,
  preloadCover: (src, speculative) => new Promise(resolve => pending.push({ src, speculative, resolve }))
});
vm.runInContext(['trackKey', 'trackOccurrenceKey', 'confirmedTrackAction', 'metadataSignature', 'hasMatchingPrefetchedCover', 'prepareNextTrack'].map(extract).join('\n'), context);
const encoding = vm.createContext({ TextDecoder, Uint8Array });
vm.runInContext(extract('repairMetadataEncoding') + '\n' + extract('usableMetadataText'), encoding);
assert.equal(encoding.usableMetadataText('Êîìàðîâî', ''), 'Комарово');
assert.equal(encoding.usableMetadataText('Èãîðü Ñêëÿð', ''), 'Игорь Скляр');
assert.equal(encoding.usableMetadataText('Комарово', ''), 'Комарово');
assert.equal(encoding.usableMetadataText('Beyoncé', ''), 'Beyoncé');
(async () => {
  const a = { id: 'a', art: 'a.jpg', title: 'A', artist: 'Artist' };
  const b = { id: 'b', art: 'b.jpg', title: 'Jingle' };
  context.prepareNextTrack({ song: a });
  assert.equal(context.nextTrackCache.metadata.title, 'A');
  context.prepareNextTrack({ song: b });
  context.prepareNextTrack({ song: a });
  pending[0].resolve('old-a.jpg');
  await Promise.resolve();
  assert.equal(context.nextTrackCache.ready, false, 'Old A request must not complete later A generation');
  pending[2].resolve('a.jpg');
  await Promise.resolve();
  assert.equal(context.hasMatchingPrefetchedCover(a), true);
  assert.equal(context.hasMatchingPrefetchedCover({ ...a, art: 'changed.jpg' }), false, 'Changed artwork must not reuse old cover');
  context.prepareNextTrack({ song: { ...a, art: 'changed.jpg' } });
  pending[3].resolve('');
  await Promise.resolve();
  assert.equal(context.nextTrackCache.ready, false, 'Failed speculative artwork must be retried when actually playing');
  context.prepareNextTrack(null);
  assert.equal(context.nextTrackCache.key, '', 'Empty queue clears prefetch');
  const first = context.trackOccurrenceKey(a, { played_at: 100 });
  const replay = context.trackOccurrenceKey(a, { played_at: 200 });
  assert.notEqual(first, replay, 'Repeated song after a jingle must be a new occurrence');
  assert.equal(context.confirmedTrackAction(first, 'b|150', replay), 'transition', 'Actual on-air change must supersede queued transition');
  assert.equal(context.confirmedTrackAction(first, 'b|150', first), 'transition', 'Return to current track must cancel stale transition');
  assert.equal(context.confirmedTrackAction(first, 'b|150', 'b|150'), 'wait');
  assert.equal(context.confirmedTrackAction(first, '', first), 'sync');
  assert.notEqual(context.metadataSignature({ title: 'A', art: 'old' }), context.metadataSignature({ title: 'A', art: 'new' }), 'Corrected artwork for the same song must be detected');
  let now = 100000;
  const images = [];
  const coverContext = vm.createContext({
    art: {}, cover: {}, failedArtUrls: {}, currentArt: '', ART_RETRY_DELAY_MS: 60000,
    Date: { now: () => now },
    window: { Image: function () { images.push(this); } }
  });
  vm.runInContext(extract('preloadCover'), coverContext);
  const speculative = coverContext.preloadCover('art.jpg', true);
  images[0].onerror();
  await speculative;
  assert.equal(coverContext.failedArtUrls['art.jpg'], undefined, 'Speculative failure must not block actual cover');
  const firstAttempt = coverContext.preloadCover('art.jpg', false);
  images[1].onerror();
  await firstAttempt;
  await coverContext.preloadCover('art.jpg', false);
  assert.equal(images.length, 2, 'Failed artwork should have a bounded cooldown');
  now += 60001;
  const retry = coverContext.preloadCover('art.jpg', false);
  images[2].onload();
  assert.equal(await retry, 'art.jpg', 'Artwork should recover after a temporary failure');
  let polls = 0;
  const boundary = vm.createContext({
    trackEndReached: false, destroyed: false, trackEndTimer: null, tonearmReturnTimer: null,
    startBoundaryPolling: () => polls++,
    returnTonearmToStart: () => { throw new Error('Prediction moved the tonearm'); },
    showStationPlaceholder: () => { throw new Error('Prediction hid the current cover'); },
    startMetadataScramble: () => { throw new Error('Prediction hid the current title'); },
    window: { clearTimeout() {} }
  });
  vm.runInContext(extract('beginEndOfTrackTransition'), boundary);
  boundary.beginEndOfTrackTransition();
  assert.equal(polls, 1, 'Predicted track end should check the server and retain current metadata');
  let timeoutCleared = false;
  const cancellation = vm.createContext({
    preloadCover: () => new Promise(() => {}),
    window: { setTimeout: () => 7, clearTimeout: () => { timeoutCleared = true; } }
  });
  vm.runInContext(extract('preloadCoverWithTimeout'), cancellation);
  const pendingCover = cancellation.preloadCoverWithTimeout('slow.jpg', 350);
  pendingCover.cancel();
  assert.equal(await pendingCover.initial, '', 'Cancelled artwork wait must settle');
  assert.equal(timeoutCleared, true, 'Cancelled artwork timeout must be cleared');
  console.log('Queue prefetch, stale generations, jingle replay and on-air confirmation checks passed');
})().catch(error => { console.error(error); process.exitCode = 1; });
