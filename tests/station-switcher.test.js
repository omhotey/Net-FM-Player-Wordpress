const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../assets/net-fm-player.js'), 'utf8');
const start = source.indexOf('  function initAll(scope)');
const end = source.indexOf('  function destroyAll(scope)', start);
assert.ok(start >= 0 && end > start);
let click;
let destroyed = 0;
let initialized = 0;
let resumed = 0;
let replacement;
const root = {};
const choice = { hasAttribute: () => false, getAttribute: () => 'two' };
const template = { getAttribute: () => 'two', content: { cloneNode: () => ({ station: 'two' }) } };
const current = {
  querySelector: selector => selector === '[data-nfp-audio]' ? { paused: false } : selector === '[data-nfp-toggle]' ? { disabled: false, click: () => resumed++ } : { focus() {} },
  querySelectorAll: selector => selector === '[data-nfp]' ? [root] : [],
  contains: () => true,
  replaceChildren: child => { replacement = child; }
};
const attributes = {};
const switcher = {
  getAttribute: key => attributes[key],
  setAttribute: (key, value) => { attributes[key] = value; },
  querySelector: () => current,
  addEventListener: (event, handler) => { if (event === 'click') click = handler; },
  querySelectorAll: () => [template]
};
const scope = { querySelectorAll: selector => selector === '[data-nfp]' ? [root] : [switcher] };
const context = vm.createContext({ document: scope, initPlayer: () => initialized++, destroyAll: () => destroyed++ });
vm.runInContext(source.slice(start, end), context);
context.initAll(scope);
assert.equal(typeof click, 'function');
click({ target: { closest: selector => selector === '[data-nfp-station-target]' ? choice : null } });
assert.equal(destroyed, 1);
assert.equal(replacement.station, 'two');
assert.equal(initialized, 2);
assert.equal(resumed, 1);
context.initAll(scope);
assert.equal(attributes['data-nfp-switcher-ready'], '1');
console.log('Station switch lifecycle check passed');
