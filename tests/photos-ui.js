'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const file = require('node:path').join(__dirname, '../iCloud Photos/visualization/app.js');
const {selection} = require(file);
const state = selection();
const page = {items: [{id: 'one', name: 'First'}, {id: 'two', name: 'Second'}], offset: 0, next: 20, total: 100};
state.apply(page, 'album-one', false); state.paused = true; state.move(1);
state.apply({...page, items: [{id: 'new'}, ...page.items]}, 'album-one', false);
assert.equal(state.id, 'two', 'An update must retain the selected picture by ID when a new picture is inserted.');
assert.equal(state.paused, true);
state.apply(page, 'album-two', false);
assert.equal(state.id, 'one', 'Changing albums must not retain an image from the previous source.');
state.apply({items: [], next: null}, 'album-two', false);
assert.equal(state.id, '');

class Element {
    constructor() { this.style = {setProperty(name, value) { this[name] = value; }}; this.events = {}; this.attributes = {}; this.textContent = ''; this.scrollTop = 0; }
    addEventListener(name, fn) { this.events[name] = fn; }
    setAttribute(name, value) { this.attributes[name] = value; }
    removeAttribute(name) { delete this[name]; }
}
const sample = {state: page, runtime: {source: 'source-one'}, options: {instance: 123, endpoint: '/hook/photos', token: 'f'.repeat(64), mode: 'slideshow', seconds: 30, fit: 'contain', caption: true, themeCSS: ':root{}', tileHeaderInset: 58, text: {}}};
const storage = new Map();
function harness(input = sample, fragment = false) {
    const nodes = Object.fromEntries(['photo-bootstrap', 'photo-widget', 'photo-frame', 'photo', 'photo-caption', 'photo-theme', 'photo-error', 'empty', 'previous', 'pause', 'next'].map(id => [id, new Element()]));
    nodes['photo-bootstrap'].textContent = JSON.stringify(input);
    const tasks = new Map(), events = {}, requests = [], revoked = [];
    let seq = 0, available = true, status = 200, binaryDelay = false, waiting;
    class PhotoURL extends URL {}
    PhotoURL.createObjectURL = () => 'blob:synthetic-' + ++seq;
    PhotoURL.revokeObjectURL = value => revoked.push(value);
    const window = {document: {getElementById: id => nodes[id]},
        sessionStorage: {getItem: key => storage.get(key), setItem: (key, value) => storage.set(key, value)},
        addEventListener: (name, fn) => { events[name] = fn; },
        URL: PhotoURL, Blob, atob, setTimeout(fn, ms) { tasks.set(++seq, {fn, ms}); return seq; }, clearTimeout(id) { tasks.delete(id); },
        SymconHTTPOrigin: {resolve: () => 'http://symcon.example.invalid'},
        SymconVisualizationInteraction: {registerWheelRegion(_node, fn) { this.wheel = fn; }, delta: event => event},
        location: {hash: fragment ? '#token=' + 'a'.repeat(64) : '', pathname: '/hook/photos/page', search: ''}, history: {replaceState(_a, _b, value) { this.value = value; }},
        fetch: async (_url, configuration) => {
            const request = JSON.parse(configuration.body); requests.push({request, configuration});
            if (!available) throw new Error('Offline');
            if (request.operation === 'image' && binaryDelay) await new Promise(resolve => { waiting = resolve; });
            const token = 'c'.repeat(32);
            const value = request.operation === 'model' ? {success: true, state: page, source: 'source-one'}
                : request.operation === 'image' ? {success: true, mime: 'image/jpeg', bytes: 100, transfer: {Token: token, PageCount: 1, ItemCount: 1}}
                : request.operation === 'imagePage' ? {success: true, Token: token, Page: 0, PageCount: 1, ItemCount: 1, Complete: true, Items: [Buffer.alloc(100).toString('base64')]}
                : request.operation === 'imageClear' ? {success: true}
                : {success: true, items: [{id: 'later', name: 'Later'}], offset: request.offset, next: null};
            return {ok: status === 200, status, json: async () => value};
        }
    };
    vm.runInNewContext(fs.readFileSync(file, 'utf8'), {window, URL: PhotoURL, URLSearchParams, AbortController, console});
    return {window, nodes, tasks, events, requests, revoked, offline: value => { available = !value; }, deny: () => { status = 403; }, delay: () => { binaryDelay = true; }, release: () => { binaryDelay = false; if (waiting) waiting(); }};
}
const tick = async () => { for (let i = 0; i < 80; i++) await Promise.resolve(); };
const poll = async h => { const entry = [...h.tasks].find(([, task]) => task.ms === 5000); assert.ok(entry, 'Poll must be scheduled.'); h.tasks.delete(entry[0]); await entry[1].fn(); await tick(); };
(async () => {
    const h = harness(); await tick();
    assert.equal(h.nodes.photo.hidden, false);
    assert.equal(h.nodes['photo-widget'].style['--symc-tile-header-inset'], '58px');
    assert.equal(h.requests.filter(item => item.request.operation === 'image').length, 1, 'Initial push and poll must share an in-flight image.');
    h.nodes.pause.events.click();
    h.nodes['photo-caption'].scrollTop = 14;
    await h.nodes.next.events.click(); await tick();
    assert.match(h.nodes['photo-caption'].textContent, /Second/);
    const current = h.nodes.photo.src;
    await poll(h);
    assert.equal(h.nodes.photo.src, current, 'Metadata polls must not replace the current image or its Blob URL.');
    assert.equal(h.nodes.pause.attributes['aria-pressed'], 'true');
    assert.equal(h.nodes['photo-caption'].scrollTop, 14);
    h.offline(true); await poll(h);
    assert.equal(h.nodes.photo.src, current, 'A temporary connection loss must keep the current picture.');
    h.offline(false); await poll(h);
    assert.equal(h.nodes.photo.src, current);
    assert.equal(h.nodes['photo-error'].hidden, true, 'A recovered connection must dismiss its warning without reloading the image.');
    assert.equal(h.nodes.pause.attributes['aria-pressed'], 'true');
    h.events.pagehide();
    const restored = harness(); await tick();
    assert.match(restored.nodes['photo-caption'].textContent, /Second/, 'Document replacement must restore image selection from session storage.');
    assert.equal(restored.nodes.pause.attributes['aria-pressed'], 'true');
    assert.equal(restored.nodes['photo-caption'].scrollTop, 14);
    restored.window.SymconVisualizationInteraction.wheel({deltaY: 120, deltaX: 0}); await tick();
    assert.match(restored.nodes['photo-caption'].textContent, /Later/, 'The central wheel region must navigate across lazy page boundaries.');
    restored.deny(); await poll(restored);
    assert.equal(restored.nodes.photo.hidden, true, 'Revoked access must clear the displayed image, not just show a warning.');
    assert.ok(restored.revoked.length > 0);
    const ips = harness({...sample, options: {...sample.options, fragmentToken: true, token: '', tileHeaderInset: 0}}, true); await tick();
    assert.equal(ips.window.history.value, '/hook/photos/page');
    assert.equal(ips.requests[0].configuration.headers['X-Photo-Token'], 'a'.repeat(64), 'IPSView must use fragment authorization only in the POST header.');
    assert.ok(ips.requests.every(item => item.configuration.credentials === 'omit'));
    h.window.handleMessage({options: {...sample.options, token: 'b'.repeat(64)}, state: page, source: 'source-two'}); await tick();
    assert.equal(h.requests.at(-1).configuration.headers['X-Photo-Token'], 'b'.repeat(64), 'A native source change must adopt the rotated bearer token.');
    storage.clear();
    const slow = harness(); await tick(); slow.delay();
    slow.nodes.next.events.click(); await tick();
    const count = slow.requests.filter(item => item.request.operation === 'image').length;
    await poll(slow);
    assert.equal(slow.requests.filter(item => item.request.operation === 'image').length, count, 'Polling must not cancel or duplicate a slow in-flight download.');
    slow.release(); await tick();
    assert.equal(slow.nodes.photo.hidden, false);
    console.log('Photo selection, pause, lazy paging, wheel routing, reconnect and revocation passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
