const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const js = fs.readFileSync(path.join(__dirname, '../assets/net-fm-player.js'), 'utf8');
const css = fs.readFileSync(path.join(__dirname, '../assets/net-fm-player.css'), 'utf8');
const angle = name => Number(js.match(new RegExp('var ' + name + ' = ([0-9.]+)'))[1]);
const start = angle('TONEARM_START_ANGLE');
const end = angle('TONEARM_END_ANGLE');
assert.match(css, /--nfp-tonearm-pivot-x:\s*90%/);
assert.match(css, /--nfp-tonearm-pivot-y:\s*8%/);
assert.match(css, /--nfp-tonearm-length:\s*58%/);
assert.match(css, /width:\s*49%\s*!important/);
// The media is square in both layouts. Test its actual scale range including
// the wobble, cartridge dimensions, and the artwork's four-pixel border.
for (const size of [200, 240, 320, 380, 500]) {
  const thickness = Math.max(5, Math.min(7, size * 0.016));
  for (let degrees = start - 0.25; degrees <= end + 0.25; degrees += 0.25) {
    const a = degrees * Math.PI / 180;
    const b = (degrees + 8) * Math.PI / 180;
    const x = (0.9 + 0.58 * Math.cos(a) - 0.5) * size;
    const y = (0.08 + 0.58 * Math.sin(a) - 0.5) * size;
    const u = Math.cos(b) * x + Math.sin(b) * y;
    const v = -Math.sin(b) * x + Math.cos(b) * y;
    const distance = Math.hypot(Math.max(0, Math.abs(u) - 0.18 * 0.58 * size / 2), Math.max(0, Math.abs(v) - 3.2 * thickness / 2));
    const labelRadius = 0.49 * 0.98 * size / 2 + 4;
    assert.ok(distance > labelRadius, `Cartridge overlaps artwork at ${size}px / ${degrees} degrees`);
    assert.ok(Math.hypot(x, y) < 0.98 * size / 2, `Stylus leaves record at ${size}px / ${degrees} degrees`);
  }
}
console.log('Tonearm remains on grooves and outside artwork at tested sizes');
