'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

class Element {
    constructor(tag = 'div') {
        this.tagName = tag; this.children = []; this.dataset = {}; this.attrs = {}; this.events = {};
        this.style = {setProperty(name, value) { this[name] = value; }};
        this.scrollTop = 0; this.scrollLeft = 0; this.textContent = ''; this.hidden = false;
    }
    set innerHTML(_) {
        this.parts = {};
        for (const name of ['avatar', 'copy', 'name', 'status', 'detail', 'reason']) this.parts[name] = new Element();
    }
    querySelector(selector) { return this.parts[selector.slice(1)]; }
    addEventListener(name, fn) { this.events[name] = fn; }
    setAttribute(name, value) { this.attrs[name] = value; }
    appendChild(child) { return this.insertBefore(child, null); }
    insertBefore(child, before) {
        child.remove();
        const index = before ? this.children.indexOf(before) : this.children.length;
        this.children.splice(index, 0, child); child.parentElement = this; return child;
    }
    replaceChildren() { this.children = []; }
    remove() {
        if (this.parentElement) this.parentElement.children.splice(this.parentElement.children.indexOf(this), 1);
        this.parentElement = null;
    }
    get previousElementSibling() { return this.parentElement?.children[this.parentElement.children.indexOf(this) - 1] || null; }
    get nextElementSibling() { return this.parentElement?.children[this.parentElement.children.indexOf(this) + 1] || null; }
    get firstElementChild() { return this.children[0] || null; }
}
const source = fs.readFileSync(require('node:path').join(__dirname, '../iCloud Presence/visualization/app.js'), 'utf8');
const sample = {
    state: {title: 'Presence', summary: {label: 'Present', tone: 'positive', detail: '1 present'}, members: [
        {id: 'alpha', name: '<script>unsafe</script>', zone: 'Home', status: 'Present', tone: 'positive', detail: 'Stay: 20 s', image: ''},
        {id: 'beta', name: 'Second', zone: 'Unknown', status: 'Unknown', tone: 'unknown', reason: 'Inaccurate', image: 'https://example.invalid/image'}
    ]},
    options: {design: {photos: true, details: true, fontSize: '14px', spacing: 10}, tileHeaderInset: 58, empty: 'Empty', connectionError: 'Retry', transport: null}
};
function harness(transport, fragment, variant = 'Widget', storage = new Map()) {
    const nodes = Object.fromEntries(['widget-bootstrap', 'status-widget', 'members', 'summary-label', 'summary-value', 'summary-detail', 'summary', 'empty', 'widget-theme', 'connection', 'variant-switch', 'variant-widget', 'variant-compact'].map(id => [id, new Element()]));
    nodes.connection.hidden = true;
    const input = structuredClone(sample);
    input.options.variant = variant;
    input.options.variantSwitch = !transport;
    input.options.variantStorageKey = 'synthetic-instance';
    input.options.variantLabel = 'Widget variant';
    if (transport) input.options.transport = {url: '/hook/test', token: 'synthetic-token'};
    if (fragment !== undefined) Object.assign(input.options.transport, {token: '', fragmentToken: true});
    nodes['widget-bootstrap'].textContent = JSON.stringify(input);
    const tasks = new Map(); let seq = 0;
    const window = {document: {getElementById: id => nodes[id], createElement: tag => new Element(tag), documentElement: new Element('html')}, events: {},
        addEventListener(name, fn) { this.events[name] = fn; },
        setTimeout(fn, ms) { tasks.set(++seq, {fn, ms}); return seq; }, clearTimeout(id) { tasks.delete(id); },
        SymconHTTPOrigin: {resolve: () => 'http://symcon.example.invalid'}, fetch: async () => { throw new Error('offline'); },
        location: {hash: '#token=' + (fragment || ''), pathname: '/hook/test/page', search: ''},
        history: {replaceState(_state, _title, url) { this.url = url; }}
    };
    window.sessionStorage = {getItem: key => storage.get(key) ?? null, setItem: (key, value) => storage.set(key, value)};
    vm.runInNewContext(source, {window, URL, URLSearchParams, AbortController, console});
    return {window, nodes, tasks};
}
async function settle() { for (let i = 0; i < 12; i++) await Promise.resolve(); }
(async () => {
    const {window, nodes} = harness(false);
    const list = nodes.members;
    const first = list.children[0];
    assert.equal(first.parts.name.textContent, '<script>unsafe</script>', 'Names remain text.');
    assert.equal(list.children[1].parts.avatar.children.length, 0, 'Remote images are never loaded.');
    first.events.click(); list.scrollTop = 170; list.scrollLeft = 9;
    const next = structuredClone(sample); next.state.members[0].status = 'Absent';
    next.state.members[0].image = 'data:image/gif;base64,R0lGODlhAQABAIAAAAUEBAAAACwAAAAAAQABAAACAkQBADs=';
    window.handleMessage(JSON.stringify(next));
    assert.equal(list.children[0], first, 'Live updates preserve row identity and focus.');
    assert.equal(first.attrs['aria-pressed'], 'true');
    assert.equal(first.parts.status.textContent, 'Absent');
    assert.equal(first.parts.avatar.children[0].src, next.state.members[0].image, 'Uploaded raster pictures render through the existing safe avatar path.');
    assert.equal(list.scrollTop, 170); assert.equal(list.scrollLeft, 9);
    window.handleMessage('{invalid'); window.handleMessage({state: {}, options: {}});
    assert.equal(list.children[0], first, 'Malformed SDK messages keep the last usable view.');
    next.state.members.reverse(); next.options.design.photos = false; next.options.design.details = false;
    window.handleMessage(next);
    assert.equal(list.children[1], first, 'Reordering retains selection by ID.');
    assert.equal(first.parts.avatar.hidden, true); assert.equal(first.parts.detail.hidden, true);
    next.state.members = []; window.handleMessage(next);
    assert.equal(list.children.length, 0); assert.equal(nodes.empty.hidden, false);

    const web = harness(true); await settle();
    assert.equal(web.nodes.connection.hidden, false, 'Network errors are visible.');
    const row = web.nodes.members.children[0]; row.events.click(); web.nodes.members.scrollTop = 90;
    let request;
    web.window.fetch = async (url, options) => { request = {url, options}; return {ok: true, json: async () => sample}; };
    let retry = [...web.tasks.values()].find(task => task.ms === 5000); assert.ok(retry);
    retry.fn(); await settle();
    assert.equal(request.url, 'http://symcon.example.invalid/hook/test');
    assert.equal(request.options.headers['X-Status-Widget-Token'], 'synthetic-token');
    assert.equal(request.options.credentials, 'omit');
    assert.equal(web.nodes.connection.hidden, true, 'Polling recovers without a document reload.');
    assert.equal(web.nodes.members.children[0], row); assert.equal(row.attrs['aria-pressed'], 'true'); assert.equal(web.nodes.members.scrollTop, 90);
    web.window.events.pagehide(); await settle();
    const pending = web.tasks.size;
    web.window.events.pageshow({persisted: true}); await settle();
    assert.equal(web.tasks.size, pending + 1, 'Returning from back-forward cache starts one polling cycle.');
    const secret = 'b'.repeat(64);
    const framed = harness(true, secret); await settle();
    assert.equal(framed.window.history.url, '/hook/test/page', 'The frame removes the fragment before polling.');
    const selected = framed.nodes.members.children[1]; selected.events.click(); framed.nodes.members.scrollTop = 73;
    framed.window.fetch = async (url, options) => {
        assert.equal(url, 'http://symcon.example.invalid/hook/test', 'HTTP page polling stays on its own origin, avoiding an opaque-origin preflight.');
        assert.equal(options.headers['X-Status-Widget-Token'], secret);
        return {ok: true, json: async () => ({...sample, state: {...sample.state, members: sample.state.members.map(m => ({...m, status: 'Changed'}))}})};
    };
    [...framed.tasks.values()].find(task => task.ms === 5000).fn(); await settle();
    assert.equal(framed.nodes.connection.hidden, true);
    assert.equal(selected.attrs['aria-pressed'], 'true'); assert.equal(framed.nodes.members.scrollTop, 73);
    assert.equal(selected.parts.status.textContent, 'Changed', 'The same selected row receives the HTTP update.');
    const invalid = harness(true, 'invalid'); await settle();
    assert.equal(invalid.nodes.connection.hidden, false, 'Malformed/missing fragment tokens fail closed.');
    const compact = harness(false, undefined, 'WidgetCompact');
    const compactList = compact.nodes.members;
    const compactRow = compactList.children[0];
    assert.equal(compact.nodes['status-widget'].dataset.variant, 'compact');
    assert.equal(compactRow.parts.status.textContent, 'Home', 'Compact devices show the zone without redundant presence labels.');
    assert.equal(compactRow.parts.detail.textContent, ''); assert.equal(compactRow.parts.detail.hidden, true);
    assert.equal(compactList.children[1].parts.reason.textContent, ''); assert.equal(compactList.children[1].parts.reason.hidden, true);
    assert.equal(compactList.children[1].parts.status.textContent, 'Unknown', 'Unknown must remain visible even without diagnostic details.');
    assert.equal(compact.nodes['summary-detail'].hidden, true, 'Compact aggregate uses a single heading row.');
    compactRow.events.click(); compactList.scrollTop = 81; compactList.scrollLeft = 4;
    const compactUpdate = structuredClone(sample); compactUpdate.options.variant = 'WidgetCompact'; compactUpdate.state.members[0].zone = 'Work';
    compact.window.handleMessage(compactUpdate);
    assert.equal(compactList.children[0], compactRow); assert.equal(compactRow.parts.status.textContent, 'Work');
    assert.equal(compactRow.attrs['aria-pressed'], 'true'); assert.equal(compactList.scrollTop, 81); assert.equal(compactList.scrollLeft, 4);
    const storage = new Map();
    const switching = harness(false, undefined, 'WidgetCompact', storage);
    const switchingList = switching.nodes.members;
    const switchingRow = switchingList.children[0];
    switchingRow.events.click(); switchingList.scrollTop = 72; switchingList.scrollLeft = 3;
    switching.nodes['variant-widget'].events.click();
    assert.equal(switching.nodes['status-widget'].dataset.variant, 'normal');
    assert.equal(switchingRow.parts.detail.textContent, 'Stay: 20 s', 'Native compact default retains the full data for switching back.');
    assert.equal(switching.nodes['status-widget'].style['--widget-spacing'], '10px');
    assert.equal(switchingRow.attrs['aria-pressed'], 'true');
    assert.equal(switchingList.scrollTop, 72); assert.equal(switchingList.scrollLeft, 3);
    switchingList.scrollTop = 140;
    switching.nodes['variant-compact'].events.click();
    assert.equal(switchingList.scrollTop, 72, 'Each variant restores its own last scroll offset.');
    assert.equal(switchingRow.parts.status.textContent, 'Home');
    assert.equal(switching.nodes['status-widget'].style['--widget-spacing'], '8px');
    switching.window.handleMessage({...sample, options: {...sample.options, variant: 'Widget'}});
    assert.equal(switching.nodes['status-widget'].dataset.variant, 'compact', 'SDK data updates do not override the local choice.');
    assert.equal(switchingList.children[0], switchingRow); assert.equal(switchingRow.attrs['aria-pressed'], 'true');
    switching.nodes['variant-widget'].events.click();
    assert.equal(switchingList.scrollTop, 140);
    assert.equal(switching.nodes['variant-widget'].attrs['aria-pressed'], 'true');
    assert.equal(switching.nodes['variant-compact'].attrs['aria-pressed'], 'false');
    assert.equal(harness(false, undefined, 'WidgetCompact', storage).nodes['status-widget'].dataset.variant, 'normal', 'Session choice overrides the designer default when the tile is reopened.');
    assert.equal(harness(false, undefined, 'WidgetCompact').nodes['status-widget'].dataset.variant, 'compact', 'Other clients retain their own default.');
    assert.equal(web.nodes['variant-switch'].hidden, true, 'IPSView retains its two independent outputs without a native switch.');
    web.nodes['variant-compact'].events.click();
    assert.equal(web.nodes['status-widget'].dataset.variant, 'normal');
    storage.set('SymconiCloud.presence.variant:synthetic-instance', 'invalid');
    assert.equal(harness(false, undefined, 'WidgetCompact', storage).nodes['status-widget'].dataset.variant, 'compact', 'Invalid saved variants fall back to the designer default.');
    const unavailableStorage = {get() { throw new Error('denied'); }, set() { throw new Error('denied'); }};
    const withoutStorage = harness(false, undefined, 'Widget', unavailableStorage);
    withoutStorage.nodes['variant-compact'].events.click();
    assert.equal(withoutStorage.nodes['status-widget'].dataset.variant, 'compact', 'Blocked browser storage must not prevent switching.');
    console.log('Status widget SDK updates, keyed rows, selection, scroll, images and polling recovery passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
