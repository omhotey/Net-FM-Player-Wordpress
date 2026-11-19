/* Experimental: match an audible ICY title to a unique AzuraCast song. */
(function (root) {
  'use strict';
  function normalize(value) {
    return String(value || '').normalize('NFKC').toLocaleLowerCase().replace(/\s+/g, ' ').trim();
  }
  function match(title, data) {
    var wanted = normalize(title);
    if (!wanted) return null;
    var entries = [];
    if (data && data.now_playing) entries.push(data.now_playing);
    if (data && Array.isArray(data.song_history)) entries = entries.concat(data.song_history);
    if (data && data.playing_next) entries.push(data.playing_next);
    var found = new Map();
    entries.forEach(function (entry) {
      var song = entry && entry.song;
      if (!song) return;
      var exact = normalize(song.text);
      var pair = normalize([song.artist, song.title].filter(Boolean).join(' - '));
      if (wanted !== exact && wanted !== pair && !(song.title && !song.artist && wanted === normalize(song.title))) return;
      var key = (song.id ? 'id:' + song.id : 'song:' + normalize(song.artist) + '|' + normalize(song.title)) + '|' + String(song.art || '');
      if (!found.has(key)) found.set(key, entry);
    });
    return found.size === 1 ? found.values().next().value : null;
  }
  if (typeof module === 'object' && module.exports) module.exports = match;
  else root.NetFmMatchTrack = match;
}(typeof window !== 'undefined' ? window : globalThis));
