'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../iCloud WoIst/visualization/app.js'), 'utf8');
const api = require('../iCloud WoIst/visualization/app.js');

assert.equal(api.validLocation({latitude: 0, longitude: 0}), true, 'Zero coordinates are valid locations.');
for (const location of [null, {}, {latitude: 91, longitude: 0}, {latitude: 0, longitude: 181}, {latitude: NaN, longitude: 1}, {latitude: '0', longitude: 0}]) {
    assert.equal(api.validLocation(location), false, 'Invalid locations never create pins.');
}
assert.equal(api.ageSeconds(200, 100), 0, 'Future clocks do not show negative ages.');
assert.equal(api.ageSeconds(0, 100), null, 'Unknown timestamps are not current.');
assert.equal(api.isStale({timestamp: 100, isOld: false}, {staleAfter: 60}, 161), true);
assert.equal(api.isStale({timestamp: 100, isOld: true}, {staleAfter: 60}, 110), true);
assert.equal(api.isStale({timestamp: 200, isOld: false}, {staleAfter: 60}, 100), false);
assert.equal(api.isStale({timestamp: 100, isOld: false}, {success: false, staleAfter: 60}, 110), true, 'Account failure makes a recent coordinate last known.');
assert.equal(api.parseState('bad json'), null);
assert.equal(api.parseState('{"state":{"devices":[],"success":false}}').success, false);

class Element {
    constructor() { this.children = []; this.dataset = {}; this.attrs = {}; this.events = {}; this.textContent = ''; this.style = {setProperty(name, value) { this[name] = value; }}; }
    appendChild(child) { this.children.push(child); return child; }
    replaceChildren() { this.children = []; }
    setAttribute(name, value) { this.attrs[name] = value; }
    addEventListener(name, action) { this.events[name] = action; }
}

const elements = Object.fromEntries(['woist-heading', 'woist-devices', 'woist-controls', 'woist-bootstrap', 'title', 'updated', 'fit', 'refresh', 'devices-title', 'device-list', 'empty', 'account-message', 'transport-message', 'map-message', 'map'].map((id) => [id, new Element()]));
const timestamp = Math.floor(Date.now() / 1000);
const maliciousName = '<img src=x onerror=alert(1)>';
elements['woist-bootstrap'].textContent = JSON.stringify({state: {success: true, updatedAt: timestamp, staleAfter: 900, devices: [
    {id: 'synthetic-a', name: maliciousName, location: {latitude: 0, longitude: 0, timestamp, isOld: false}},
    {id: 'synthetic-b', name: 'No location', location: null}
]}});
const documentRoot = new Element();
const workspace = new Element();
const requests = [];
const markers = [];
const tileProviders = [];
let fits = 0;
let viewChanges = 0;
let sizeInvalidations = 0;
const map = {events: {}, setView(center, zoom) { this.center = center; this.zoom = zoom; viewChanges++; return this; }, on(name, action) { this.events[name] = action; }, getZoom() { return this.zoom; }, fitBounds() { fits++; }, removeLayer() {}, invalidateSize(options) { sizeInvalidations++; assert.equal(options.pan, false); }};
const layer = {addTo() { return this; }, removeLayer(marker) { markers.splice(markers.indexOf(marker), 1); }};
const window = {
    document: {readyState: 'complete', documentElement: documentRoot, querySelector(selector) { return selector === '.workspace' ? workspace : null; }, getElementById(id) { return elements[id]; }, createElement() { return new Element(); }, querySelectorAll() { return elements['device-list'].children.map((item) => item.children[0]); }},
    requestAction(name) { requests.push(name); }, fetch() { throw new Error('Native tile must not poll.'); }, addEventListener() {}, setInterval() {}, setTimeout() { return 1; }, clearTimeout() {},
    L: {
        map() { return map; }, tileLayer(url, options) { tileProviders.push({url, options}); return {addTo() { return this; }, on() { return this; }}; }, layerGroup() { return layer; }, latLngBounds(value) { return value; },
        circleMarker(position, options) {
            const marker = {position, options, addTo() { markers.push(this); return this; }, bindPopup(content, popupOptions) { this.popup = content; this.popupOptions = popupOptions; return this; }, setPopupContent(content) { this.popup = content; return this; }, setLatLng(value) { this.position = value; return this; }, setStyle(value) { this.options = value; return this; }, setRadius(value) { this.options.radius = value; return this; }, on() {}, getLatLng() { return this.position; }, openPopup() { this.opened = true; }, isPopupOpen() { return !!this.opened; }};
            return marker;
        }
    }
};
vm.runInNewContext(source, {window, Date, Map, Promise, console});
assert.deepEqual(requests, ['GetState'], 'Loading requests cached state only, never Apple refresh.');
assert.equal(markers.length, 1, 'Unavailable positions do not create a marker.');
assert.equal(markers[0].popup.children[0].textContent, maliciousName, 'Untrusted names become DOM text.');
assert.equal(elements['device-list'].children[0].children[0].children[0].textContent, maliciousName);
assert.equal(fits, 1);
elements['device-list'].children[0].children[0].events.click();
assert.equal(viewChanges, 2, 'Explicit device selection changes the viewport.');
const selectedMarker = markers[0];
assert.equal(selectedMarker.popupOptions.autoPan, false, 'Popup updates cannot pan an out-of-view selected marker into view.');
const chosenView = {center: map.center, zoom: map.zoom};
window.handleMessage(JSON.stringify({state: {success: false, error: 'Synthetic account failure', updatedAt: timestamp, devices: [{id: 'synthetic-a', name: maliciousName, location: {latitude: 1, longitude: 2, timestamp, isOld: false}}]}}));
assert.equal(elements['account-message'].hidden, false, 'Account failure is visible.');
assert.equal(markers.length, 1, 'Cached last known markers survive account failure.');
assert.match(elements['device-list'].children[0].children[0].className, /stale/, 'Account failure uses a stale marker/list presentation.');
assert.equal(markers[0].popup.children[1].textContent.startsWith('Last known location'), true, 'Failure does not label cached locations as current.');
assert.equal(fits, 1, 'Incoming updates preserve the chosen viewport.');
assert.equal(markers[0], selectedMarker, 'Device updates retain the existing marker and popup.');
assert.deepEqual(Array.from(markers[0].position), [1, 2], 'The selected marker receives its new coordinates.');
assert.equal(markers[0].opened, true);
assert.equal(elements['device-list'].children[0].children[0].attrs['aria-pressed'], 'true', 'Selection survives data updates.');
assert.equal(viewChanges, 2, 'Updating the selected marker does not setView.');
assert.deepEqual({center: map.center, zoom: map.zoom}, chosenView);
selectedMarker.opened = false;
window.handleMessage({state: {success: true, devices: [{id: 'synthetic-a', location: {latitude: 1, longitude: 2, timestamp}}]}});
assert.equal(selectedMarker.opened, false, 'A popup explicitly closed by the user stays closed during updates.');
window.handleMessage({state: {success: true, devices: [{id: 'synthetic-a', location: null}]}});
assert.equal(markers.length, 0, 'Loss of coordinates removes the old pin.');
assert.equal(elements['device-list'].children[0].children[0].attrs['aria-pressed'], 'true', 'An existing unlocated device remains selected.');
window.handleMessage({state: {success: true, devices: [{id: 'synthetic-a', location: {latitude: 3, longitude: 4, timestamp}}]}});
assert.equal(elements['device-list'].children[0].children[0].attrs['aria-pressed'], 'true');
assert.equal(markers[0].opened, undefined, 'Reappearing location keeps selection without unexpectedly reopening a lost popup.');
assert.equal(viewChanges, 2);
elements.refresh.events.click();
assert.deepEqual(requests, ['GetState', 'RefreshDevices'], 'Only an explicit click refreshes Apple data.');
elements.fit.events.click();
assert.equal(fits, 2, 'Show all explicitly fits current devices.');
window.handleMessage({state: {success: true, devices: []}, options: {tileUrl: 'https://example.invalid/{z}/{x}/{y}.png', attribution: '<img src=x onerror=alert(1)>'}});
assert.equal(tileProviders.length, 2, 'Provider settings update an already open tile.');
assert.equal(tileProviders[1].options.attribution, '&lt;img src=x onerror=alert(1)&gt;', 'Provider attribution is escaped text.');
assert.equal(markers.length, 0, 'Empty state removes old device pins.');
window.handleMessage({state: {success: false, devices: []}, options: {tileUrl: '', attribution: ''}});
assert.equal(tileProviders.length, 2, 'Invalid provider settings never silently load a default provider.');
window.handleMessage({state: {success: true, devices: [
    {id: 'current', name: 'Current', location: {latitude: 1, longitude: 2, timestamp, isOld: false}},
    {id: 'old', name: 'Old', location: {latitude: 3, longitude: 4, timestamp, isOld: true}}
]}, options: {design: {height: 720, fontSize: 18, markerSize: 15, currentColor: '#123456', staleColor: '#abcdef', showTitle: false, showDevices: false, showControls: false}}});
assert.equal(documentRoot.style['--woist-height'], '720px', 'Saved design dimensions reach the map.');
assert.equal(documentRoot.style['--woist-font-size'], '18px');
assert.equal(markers[0].options.radius, 15, 'Designer marker radius changes rendered markers.');
assert.equal(markers[0].options.fillColor, '#123456');
assert.equal(markers[1].options.fillColor, '#abcdef', 'Stale positions use their independent designer color.');
const providersBeforeDesign = tileProviders.length;
assert.equal(elements['woist-heading'].hidden, true);
assert.equal(elements['woist-devices'].hidden, true);
assert.equal(elements['woist-controls'].hidden, true);
assert.equal(workspace.style.gridTemplateColumns, 'minmax(0, 1fr)', 'Hidden device list gives its space back to the map.');
window.handleMessage({state: {success: true, devices: []}, options: {design: {height: 99999, fontSize: 1, currentColor: 'red;display:none', showTitle: true, showDevices: true, showControls: true}}});
assert.equal(documentRoot.style['--woist-height'], '1200px', 'Design dimensions stay within supported bounds.');
assert.equal(documentRoot.style['--woist-font-size'], '10px');
assert.equal(documentRoot.style['--woist-current'], '#123456', 'Malformed color values cannot become CSS.');
assert.equal(elements['woist-heading'].hidden, false, 'Live design changes can restore hidden elements.');
assert.equal(elements['woist-devices'].hidden, false);
assert.equal(elements['woist-controls'].hidden, false);
assert.equal(workspace.style.gridTemplateColumns, '');
assert.equal(tileProviders.length, providersBeforeDesign, 'Design-only changes do not recreate the background layer.');
const invalidationsBeforeData = sizeInvalidations;
window.handleMessage({state: {success: true, devices: []}, options: {design: {height: 99999, fontSize: 1, currentColor: '#123456', showTitle: true, showDevices: true, showControls: true}}});
assert.equal(sizeInvalidations, invalidationsBeforeData, 'Data and color changes do not invalidate map size or round the exact Leaflet center.');
window.handleMessage({state: {success: true, devices: [{id: 'synthetic-a', location: null}]}});
assert.equal(elements['device-list'].children[0].children[0].attrs['aria-pressed'], 'false', 'A removed device loses its selection even if it later returns.');
window.handleMessage({state: {success: true, devices: []}});
elements['woist-bootstrap'].textContent = JSON.stringify({state: {success: true, devices: []}});
vm.runInNewContext(source, {window, Date, Map, Set, Promise, console});
const fitsBeforeFirstLocation = fits;
const viewsBeforeFirstLocation = viewChanges;
map.events.movestart();
window.handleMessage({state: {success: true, devices: [{id: 'later', location: {latitude: 40, longitude: 50, timestamp}}]}});
assert.equal(fits, fitsBeforeFirstLocation, 'The first delayed location cannot override a viewport the user already chose.');
assert.equal(viewChanges, viewsBeforeFirstLocation);
elements['woist-bootstrap'].textContent = JSON.stringify({mode: 'ipsview', state: {success: true, devices: []}});
const requestsBeforeIPSView = requests.length;
vm.runInNewContext(source, {window, Date, Map, Set, Promise, URL, console});
assert.equal(requests.length, requestsBeforeIPSView, 'IPSView never calls the native SDK or an Apple refresh.');
assert.equal(elements['transport-message'].hidden, false, 'IPSView exposes a missing live endpoint without discarding the map.');
console.log('WoIst UI contracts passed.');
