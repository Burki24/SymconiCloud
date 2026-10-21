'use strict';

const assert = require('node:assert/strict');
const {pageURL, start} = require('../iCloud WoIst/visualization/ipsview-loader.js');
const token = 'b'.repeat(64);
const configuration = {url: 'https://symcon.example.invalid/hook/map/page', token, title: 'WoIst', error: 'Configure server URL.'};
const opaque = {protocol: 'data:', origin: 'null'};
assert.equal(pageURL(configuration, opaque), configuration.url + '#token=' + token);
const location = {protocol: 'https:', origin: 'https://symcon.example.invalid'};
assert.equal(pageURL({...configuration, url: '/hook/map/page'}, location), configuration.url + '#token=' + token);
for (const url of ['/hook/map/page', '//foreign.example.invalid/page', 'javascript:alert(1)', 'https://user:pass@example.invalid/page', 'https://example.invalid/page?token=secret', 'https://example.invalid/page#other']) {
    assert.equal(pageURL({...configuration, url}, opaque), null, 'Ambiguous origins and credential-bearing URLs cannot launch a frame.');
}
assert.equal(pageURL({...configuration, url: '/\\foreign.example.invalid/page'}, location), null);
assert.equal(pageURL({...configuration, token: ''}, location), null);
const frame = {hidden: true};
const message = {hidden: true};
const elements = {'woist-page': frame, 'woist-loader-error': message, 'woist-loader-config': {textContent: JSON.stringify(configuration)}};
const environment = {location: opaque, document: {getElementById(id) { return elements[id]; }}};
start(environment);
assert.equal(frame.src, configuration.url + '#token=' + token, 'The real map is navigated over HTTP(S), never placed in srcdoc/data HTML.');
assert.equal(frame.hidden, false);
assert.equal(message.hidden, true);
assert.equal(frame.title, 'WoIst');
elements['woist-loader-config'].textContent = JSON.stringify({...configuration, url: '/hook/map/page'});
start(environment);
assert.equal(message.textContent, configuration.error);
assert.equal(message.hidden, false, 'Missing server origin produces an actionable message.');
console.log('WoIst HTTP page loader contracts passed.');
