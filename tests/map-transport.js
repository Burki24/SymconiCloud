'use strict';

const assert = require('node:assert/strict');
const {transportURL, consumeFragmentToken, createIPSViewTransport} = require('../iCloud WoIst/visualization/app.js');

const fragmentToken = 'a'.repeat(64);
let sanitizedURL;
const fragmentConfiguration = {url: '/hook/map', token: '', fragmentToken: true};
const fragmentEnvironment = {
    location: {href: 'https://symcon.example.invalid/hook/map/page#token=' + fragmentToken},
    history: {replaceState(_state, _unused, url) { sanitizedURL = url; }}
};
assert.equal(consumeFragmentToken(fragmentConfiguration, fragmentEnvironment).token, fragmentToken);
assert.equal(sanitizedURL, '/hook/map/page', 'The navigation fragment is erased before the credential is used.');
assert.equal(fragmentConfiguration.token, '', 'The public bootstrap remains free of credentials.');
for (const hash of ['', '#token=bad', '#token=' + fragmentToken + '&other=1']) {
    const result = consumeFragmentToken(fragmentConfiguration, {...fragmentEnvironment, location: {href: 'https://symcon.example.invalid/page' + hash}});
    assert.equal(result.token, '', 'Missing or malformed fragments cannot authorize reads.');
}
assert.equal(consumeFragmentToken(fragmentConfiguration, {...fragmentEnvironment, history: {replaceState() { throw new Error('Opaque sandbox'); }}}).token, '', 'A page unable to erase its fragment fails closed.');
assert.equal(consumeFragmentToken(fragmentConfiguration, {...fragmentEnvironment, location: {href: 'data:text/html,test#token=' + fragmentToken}}).token, '', 'Only a genuine HTTP(S) page consumes the bearer.');

const origin = {protocol: 'https:', origin: 'https://symcon.example.invalid:3777'};
assert.equal(transportURL('/hook/map', origin), 'https://symcon.example.invalid:3777/hook/map');
assert.equal(transportURL('/\\other.example.invalid/hook/map', origin), null, 'A relative endpoint cannot escape the page origin using URL slash normalization.');
assert.equal(transportURL('https://symcon.example.invalid/hook/map', {protocol: 'data:'}), 'https://symcon.example.invalid/hook/map');
for (const value of ['/hook/map', '//other.example.invalid/hook/map', 'javascript:alert(1)', 'https://user:password@example.invalid/hook/map', 'https://example.invalid/hook/map?token=secret', 'https://example.invalid/hook/map#secret']) {
    assert.equal(transportURL(value, {protocol: 'data:', origin: 'null'}), null, 'Opaque frames never infer an endpoint from referrers or ancestors.');
}
assert.equal(transportURL('/hook/map', {protocol: 'about:', origin: 'null'}), null);

function fixture() {
    const timers = new Map();
    const listeners = {};
    const calls = [];
    const received = [];
    const statuses = [];
    let identifier = 0;
    const environment = {
        location: origin,
        document: {
            hidden: false,
            addEventListener(name, handler) { listeners[name] = handler; },
            removeEventListener(name) { delete listeners[name]; }
        },
        addEventListener(name, handler) { listeners[name] = handler; },
        removeEventListener(name) { delete listeners[name]; },
        setTimeout(handler, delay) { const id = ++identifier; timers.set(id, {handler, delay}); return id; },
        clearTimeout(id) { timers.delete(id); },
        AbortController,
        fetch(url, options) {
            return new Promise((resolve, reject) => {
                options.signal.addEventListener('abort', () => reject(new Error('Aborted')), {once: true});
                calls.push({url, options, resolve, reject});
            });
        }
    };
    const configuration = {url: '/hook/map', token: 'a'.repeat(64), pollInterval: 5000};
    const transport = createIPSViewTransport(configuration, environment, (state) => received.push(state), (status) => statuses.push(status));
    async function flush() { for (let index = 0; index < 12; index++) await Promise.resolve(); }
    async function advance(delay) {
        const entry = Array.from(timers).find(([, timer]) => timer.delay === delay);
        assert.ok(entry, 'Expected a scheduled timer at ' + delay + ' ms');
        timers.delete(entry[0]);
        entry[1].handler();
        await flush();
    }
    function response(call, payload, ok = true) { call.resolve({ok, async json() { return payload; }}); }
    return {environment, transport, calls, received, statuses, timers, listeners, configuration, flush, advance, response};
}

(async () => {
    const test = fixture();
    test.transport.start();
    test.transport.start();
    assert.equal(test.timers.size, 1, 'Starting twice does not duplicate polling.');
    await test.advance(0);
    assert.equal(test.calls.length, 1);
    const first = test.calls[0];
    assert.equal(first.url, 'https://symcon.example.invalid:3777/hook/map');
    assert.equal(first.options.method, 'POST');
    assert.equal(first.options.headers['X-WoIst-Token'], 'a'.repeat(64));
    assert.equal(first.options.credentials, 'omit');
    assert.equal(first.options.redirect, 'error');
    assert.equal(first.options.cache, 'no-store');
    assert.equal(first.options.mode, 'cors');
    assert.equal('body' in first.options, false, 'Cache polling cannot submit an Apple refresh action.');
    assert.equal(test.timers.size, 1, 'An in-flight request has only its timeout, no overlapping poll.');
    const envelope = {state: {devices: [{id: 'synthetic-device'}], success: true}};
    test.response(first, envelope);
    await test.flush();
    assert.equal(test.received[0], envelope);
    assert.equal(test.statuses.at(-1), '');
    await test.advance(5000);
    test.calls[1].reject(new Error('Synthetic network failure'));
    await test.flush();
    assert.equal(test.statuses.at(-1), 'transportError');
    assert.equal(test.received.length, 1, 'Network failure leaves the last map state untouched.');
    await test.advance(10000);
    test.response(test.calls[2], {unexpected: true});
    await test.flush();
    assert.equal(test.received.length, 1, 'Invalid responses cannot replace the map state.');
    await test.advance(20000);
    test.response(test.calls[3], envelope, false);
    await test.flush();
    await test.advance(30000);
    await test.advance(10000);
    assert.equal(test.calls[4].options.signal.aborted, true, 'Requests time out after ten seconds.');
    assert.equal(test.statuses.at(-1), 'transportError');
    await test.advance(30000);
    test.response(test.calls[5], envelope);
    await test.flush();
    assert.equal(test.statuses.at(-1), '', 'A successful retry clears the connection message.');
    assert.equal(test.received.length, 2);
    test.environment.document.hidden = true;
    test.listeners.visibilitychange();
    assert.equal(test.timers.size, 0, 'Hidden pages do not poll.');
    test.environment.document.hidden = false;
    test.listeners.visibilitychange();
    await test.advance(0);
    assert.equal(test.calls.length, 7);
    test.environment.document.hidden = true;
    test.listeners.visibilitychange();
    assert.equal(test.calls[6].options.signal.aborted, true);
    await test.flush();
    assert.equal(test.timers.size, 0, 'A cancelled hidden request does not schedule a retry.');
    test.environment.document.hidden = false;
    test.listeners.visibilitychange();
    await test.advance(0);
    test.listeners.pagehide();
    assert.equal(test.calls[7].options.signal.aborted, true);
    await test.flush();
    assert.equal(test.timers.size, 0);
    test.listeners.pageshow();
    await test.advance(0);
    assert.equal(test.calls.length, 9, 'A restored page resumes without reloading its document.');
    test.listeners.beforeunload();
    await test.flush();
    assert.equal(test.timers.size, 0, 'Unloading stops polling and pending deadlines.');
    assert.deepEqual(Object.keys(test.listeners), []);
    test.transport.stop();

    for (const configuration of [{url: '/hook/map', token: 'a'.repeat(64)}, {url: 'https://example.invalid/hook/map', token: 'invalid'}, undefined]) {
        const opaque = fixture();
        opaque.environment.location = {protocol: 'data:', origin: 'null'};
        createIPSViewTransport(configuration, opaque.environment, () => assert.fail('Invalid transport must not publish'), (status) => opaque.statuses.push(status)).start();
        assert.equal(opaque.calls.length, 0);
        assert.equal(opaque.timers.size, 0);
        assert.equal(opaque.statuses[0], 'transportConfiguration', 'Missing origin/token is visible without sending any credentials.');
    }
    const refreshTest = fixture();
    refreshTest.transport.start();
    await refreshTest.advance(0);
    const explicitRefresh = refreshTest.transport.refresh();
    await refreshTest.flush();
    assert.equal(refreshTest.calls[0].options.signal.aborted, true, 'Explicit refresh cancels an in-flight cache poll.');
    const refreshCall = refreshTest.calls[1];
    assert.equal(refreshCall.url, 'https://symcon.example.invalid:3777/hook/map/refresh');
    assert.equal(refreshCall.options.method, 'POST');
    assert.equal(refreshCall.options.headers['X-WoIst-Token'], fragmentToken);
    assert.equal(refreshCall.options.redirect, 'error');
    assert.equal(await refreshTest.transport.refresh(), false, 'Concurrent refresh commands must not overlap.');
    assert.equal(refreshTest.calls.length, 2);
    refreshTest.response(refreshCall, envelope);
    assert.equal(await explicitRefresh, true);
    assert.equal(refreshTest.received.length, 1, 'The canceled poll cannot replace the refresh result.');
    await refreshTest.advance(5000);
    assert.equal(refreshTest.calls[2].url, first.url, 'Automatic polling resumes on the cache endpoint only.');
    const failedRefresh = refreshTest.transport.refresh();
    refreshTest.calls[3].reject(new Error('Refresh failed'));
    assert.equal(await failedRefresh, false);
    assert.equal(refreshTest.received.length, 1, 'A failed refresh retains the current map state.');
    await refreshTest.advance(10000);
    assert.equal(refreshTest.calls[4].url, first.url, 'Retries must never repeat an Apple refresh automatically.');
    refreshTest.transport.stop();
    await refreshTest.flush();
    const timeoutRefresh = fixture();
    timeoutRefresh.transport.start();
    const pendingRefresh = timeoutRefresh.transport.refresh();
    await timeoutRefresh.advance(30000);
    assert.equal(await pendingRefresh, false, 'Explicit refresh must release its busy state after a bounded timeout.');
    assert.equal(timeoutRefresh.calls[0].options.signal.aborted, true);
    timeoutRefresh.transport.stop();
    assert.equal(await timeoutRefresh.transport.refresh(), false, 'Stopped pages cannot refresh the account.');
    console.log('WoIst IPSView transport contracts passed.');
})().catch((error) => { console.error(error); process.exitCode = 1; });
