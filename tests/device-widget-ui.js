'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../iCloud Device/visualization/app.js'), 'utf8');
const sample = {
    state: {name: 'Example phone', model: 'Phone', online: false, available: true, battery: 42,
        location: {latitude: 0, longitude: 0, address: '', accuracy: 20, timestamp: Math.floor(Date.now() / 1000)},
        locationAvailable: true, locationOld: false, canPlaySound: true, result: '', error: '', soundRevision: 0},
    options: {tileHeaderInset: 58}, translations: {}
};
function harness() {
    const ids = [...fs.readFileSync(path.join(__dirname, '../iCloud Device/visualization/index.html'), 'utf8').matchAll(/id="([^"]+)"/g)].map(match => match[1]);
    const nodes = Object.fromEntries(ids.map(id => [id, {textContent: '', hidden: false, disabled: false, scrollTop: 0, scrollLeft: 0,
        style: {setProperty(key, value) { this[key] = value; }}, events: {}, addEventListener(name, fn) { this.events[name] = fn; }}]));
    nodes['device-bootstrap'].textContent = JSON.stringify(sample);
    const commands = []; const intervals = new Map(); const timeouts = new Map();
    let reject = false; let release;
    const window = {document: {getElementById: id => nodes[id]}, events: {},
        addEventListener(event, callback) { this.events[event] = callback; },
        setInterval(fn) { intervals.set(1, fn); return 1; }, clearInterval(id) { intervals.delete(id); },
        setTimeout(fn) { timeouts.set(1, fn); return 1; }, clearTimeout(id) { timeouts.delete(id); },
        requestAction(ident, value) {
            commands.push([ident, value]);
            if (ident === 'GetState') return Promise.resolve();
            return new Promise((resolve, fail) => { release = () => reject ? fail(new Error('offline')) : resolve(); });
        }
    };
    vm.runInNewContext(source, {window, console});
    return {window, nodes, commands, intervals, timeouts, release: () => release(), reject: () => { reject = true; }};
}
(async () => {
    const h = harness(); const button = h.nodes['play-sound']; const content = h.nodes['device-content'];
    assert.deepEqual(h.commands, [['GetState', '']], 'Opening the card only requests local state.');
    assert.equal(h.nodes['location-value'].textContent, '0.000000, 0.000000');
    assert.equal(h.nodes['location-warning'].hidden, true);
    const quality = structuredClone(sample);
    for (const [accuracy, caption] of [[0, 'Accuracy: 0 m'], [null, ''], [-1, ''], ['0', ''], [12.5, 'Accuracy: 13 m']]) {
        quality.state.location.accuracy = accuracy;
        h.window.handleMessage(quality);
        assert.equal(h.nodes['location-accuracy'].textContent, caption, 'Only known finite non-negative numeric accuracy is displayed, including zero.');
    }
    assert.deepEqual(h.commands, [['GetState', '']], 'Quality display updates never trigger actions.');
    content.scrollTop = 90; content.scrollLeft = 4;
    const next = structuredClone(sample);
    next.state.location.address = '<img src=x onerror=alert(1)>Example street';
    next.state.location.timestamp -= 3600;
    h.window.handleMessage(JSON.stringify(next));
    assert.equal(h.nodes['location-value'].textContent, next.state.location.address, 'Addresses must be text, never markup.');
    assert.equal(h.nodes['location-warning'].hidden, false, 'Old timestamps need a visible warning even without an Apple flag.');
    assert.equal(content.scrollTop, 90); assert.equal(content.scrollLeft, 4);
    h.window.handleMessage('{broken');
    assert.equal(content.scrollTop, 90);
    const firstClick = button.events.click();
    assert.equal(button.disabled, true);
    await button.events.click();
    assert.equal(h.commands.length, 2, 'A pending command must not be repeated by double click.');
    h.window.handleMessage(next);
    assert.equal(button.disabled, true, 'Data updates cannot reset an in-flight action.');
    h.release(); await firstClick;
    assert.equal(button.disabled, true, 'Resolved SDK calls are not proof of server acknowledgement.');
    next.state.soundRevision = 1; next.state.result = 'Accepted'; h.window.handleMessage(next);
    assert.equal(button.disabled, false);
    h.reject(); button.events.click(); h.release(); await new Promise(resolve => setImmediate(resolve));
    assert.match(h.nodes['sound-result'].textContent, /could not be confirmed/);
    h.window.handleMessage(next);
    assert.equal(h.commands.length, 3, 'Pushes never retry a failed command.');
    h.window.requestAction = (ident, value) => { h.commands.push([ident, value]); };
    button.events.click();
    assert.equal(button.disabled, true, 'Void SDK return values keep the command pending.');
    h.timeouts.get(1)();
    assert.equal(button.disabled, false, 'A missing acknowledgement eventually reports an uncertain result without retry.');
    next.state.location = null; next.state.canPlaySound = false;
    h.window.handleMessage(next);
    assert.equal(button.disabled, true);
    assert.equal(h.nodes['location-value'].textContent, 'No known location available.');
    assert.equal(h.nodes['location-time'].hidden, true);
    h.window.events.pagehide(); assert.equal(h.intervals.size, 0);
    console.log('Device card rendering, age, scrolling and explicit sound action checks passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
