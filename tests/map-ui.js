'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../iCloud WoIst/visualization/app.js'), 'utf8');
const api = require('../iCloud WoIst/visualization/app.js');
assert.ok(fs.readFileSync(path.join(__dirname, '../libs/helper/maps/openfreemap.js'), 'utf8').includes('OpenStreetMap contributors'), 'The vector map must include attribution.');
assert.ok(source.includes(' \u00b7 '), 'Device details must preserve their UTF-8 separator.');

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
    constructor() { this.children = []; this.dataset = {}; this.attrs = {}; this.events = {}; this.textContent = ''; this.classList = {add() {}, remove() {}}; this.style = {setProperty(name, value) { this[name] = value; }}; }
    appendChild(child) { this.children.push(child); child.parentElement = this; return child; }
    replaceChildren() { this.children = []; }
    setAttribute(name, value) { this.attrs[name] = value; }
    addEventListener(name, action) { this.events[name] = action; }
}


const elements = Object.fromEntries(['woist-theme', 'woist-heading', 'woist-devices', 'woist-controls', 'woist-bootstrap', 'title', 'updated', 'fit', 'refresh', 'devices-title', 'device-list', 'empty', 'account-message', 'transport-message', 'map-message', 'map'].map((id) => [id, new Element()]));
const timestamp = Math.floor(Date.now() / 1000);
elements['geocoding-message'] = new Element();
elements['geocoding-attribution'] = new Element();
elements['woist-account-filter'] = new Element();
elements['account-filter-label'] = new Element();
elements['account-filter'] = new Element();
const maliciousName = '<img src=x onerror=alert(1)>';
const maliciousAddress = '<script>Example address</script>';
const maliciousNotice = '<b>Approximate – area only</b>';
elements['woist-bootstrap'].textContent = JSON.stringify({mode: 'symcon', options: {tileHeaderVisible: true, tileHeaderInset: 58, tileActionInset: 0}, state: {success: true, updatedAt: timestamp, staleAfter: 900, devices: [
    {id: 'synthetic-a', name: maliciousName, address: maliciousAddress, addressNotice: maliciousNotice, location: {latitude: 0, longitude: 0, timestamp, isOld: false}},
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
let lastFitPositions;
const map = {events: {}, setView(center, zoom) { this.center = center; this.zoom = zoom; viewChanges++; return this; }, on(name, action) { this.events[name] = action; }, getCenter() { return this.center; }, getZoom() { return this.zoom; }, fitBounds(positions) { fits++; lastFitPositions = positions; }, removeLayer() {}, invalidateSize(options) { sizeInvalidations++; assert.equal(options.pan, false); }};
const layer = {addTo() { return this; }, removeLayer(marker) { markers.splice(markers.indexOf(marker), 1); }};
const window = {
    document: {readyState: 'complete', documentElement: documentRoot, querySelector(selector) { return selector === '.workspace' ? workspace : null; }, getElementById(id) { return elements[id]; }, createElement() { return new Element(); }, querySelectorAll() { return elements['device-list'].children.map((item) => item.children[0]); }},
    requestAction(name) { requests.push(name); }, fetch() { throw new Error('Native tile must not poll.'); }, addEventListener() {}, setInterval() {}, setTimeout() { return 1; }, clearTimeout() {},
    SymconOpenFreeMap: {createController() { const controller = {updates: [], update(value) { if (value) this.updates.push(value); }}; tileProviders.push(controller); return controller; }},
    L: {
        map(_id, options) { assert.equal(options.trackResize, false, 'Only the view-preserving resize handler controls Leaflet resizing.'); return map; }, tileLayer(url, options) { tileProviders.push({url, options}); return {addTo() { return this; }, on() { return this; }}; }, layerGroup() { return layer; }, latLngBounds(value) { return value; },
        circleMarker(position, options) {
            const marker = {position, options, addTo() { markers.push(this); return this; }, bindPopup(content, popupOptions) { this.popup = content; this.popupOptions = popupOptions; return this; }, setPopupContent(content) { this.popup = content; return this; }, setLatLng(value) { this.position = value; return this; }, setStyle(value) { this.options = value; return this; }, setRadius(value) { this.options.radius = value; return this; }, on() {}, getLatLng() { return this.position; }, bringToFront() { this.front = true; return this; }, closePopup() { this.opened = false; return this; }, openPopup() { this.opened = true; }, isPopupOpen() { return !!this.opened; }};
            return marker;
        }
    }
};
vm.runInNewContext(source, {window, Date, Map, Promise, console});
assert.equal(documentRoot.style['--symc-tile-header-inset'], '58px', 'Native content reserves the established Symcon title area.');
assert.deepEqual(requests, ['GetState'], 'Loading requests cached state only, never Apple refresh.');
assert.equal(markers.length, 1, 'Unavailable positions do not create a marker.');
assert.equal(markers[0].popup.children[0].textContent, maliciousName, 'Untrusted names become DOM text.');
assert.ok(markers[0].popup.children.some(line => line.textContent === maliciousAddress), 'Untrusted address becomes popup text.');
assert.ok(elements['device-list'].children[0].children[0].children.some(line => line.textContent === maliciousAddress), 'Address is shown as plain list text.');
for (const children of [markers[0].popup.children, elements['device-list'].children[0].children[0].children]) {
    const warning = children.find(line => line.textContent === maliciousNotice);
    assert.ok(warning.className.includes('address-warning'), 'An approximate result must have a distinct warning style.');
    assert.equal(warning.attrs.role, 'note', 'The warning must also be exposed as an accessible note.');
    assert.equal(warning.textContent, maliciousNotice, 'Quality notices must be literal text, never HTML.');
}
assert.equal(elements['device-list'].children[0].children[0].children[0].textContent, maliciousName);
assert.equal(fits, 1);
elements['device-list'].children[0].children[0].events.click();
assert.equal(viewChanges, 2, 'Explicit device selection changes the viewport.');
const selectedMarker = markers[0];
assert.equal(selectedMarker.options.radius, 14, 'A selected device must have a visibly larger map marker.');
assert.equal(selectedMarker.options.weight, 4, 'Selection must strengthen the marker outline.');
assert.deepEqual(Array.from(map.center), [0, 0], 'List selection must center the map on the device, including zero coordinates.');
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
assert.equal(markers[0].options.radius, 14, 'A selected marker stays highlighted after data updates.');
assert.equal(elements['device-list'].children[0].children[0].attrs['aria-pressed'], 'true', 'Selection survives data updates.');
assert.equal(viewChanges, 2, 'Updating the selected marker does not setView.');
assert.deepEqual({center: map.center, zoom: map.zoom}, chosenView);
const listScrollBeforeLayout = 73;
elements['woist-devices'].scrollTop = listScrollBeforeLayout;
window.handleMessage({state: {success: true, devices: [{id: 'synthetic-a', location: {latitude: 1, longitude: 2, timestamp}}]}, options: {tileHeaderVisible: false, tileHeaderInset: 0, tileMaximizeVisible: true, tileActionInset: 44}});
assert.equal(documentRoot.style['--symc-tile-header-inset'], '0px', 'Hiding only the native title still releases its vertical space.');
assert.equal(documentRoot.style['--symc-tile-action-inset'], '44px', 'The toolbar must keep its buttons outside the native maximize overlay.');
assert.equal(elements['device-list'].children[0].children[0].attrs['aria-pressed'], 'true', 'Overlay layout updates preserve device selection.');
assert.equal(elements['woist-devices'].scrollTop, listScrollBeforeLayout, 'Overlay layout updates preserve list scroll position.');
assert.deepEqual({center: map.center, zoom: map.zoom}, chosenView, 'Overlay layout updates do not move or zoom the map.');
window.handleMessage({state: {success: true, devices: [{id: 'synthetic-a', location: {latitude: 1, longitude: 2, timestamp}}]}, options: {maxZoom: 19}});
assert.equal(documentRoot.style['--symc-tile-action-inset'], '44px', 'Partial data or design options must not discard the native overlay reservation.');
assert.ok(fs.readFileSync(path.join(__dirname, '../iCloud WoIst/visualization/index.html'), 'utf8').includes('<header class="toolbar" data-symc-tile-toolbar>'), 'The map toolbar opts into the central overlay reservation.');
window.handleMessage({state: {success: true, devices: [{id: 'synthetic-a', location: {latitude: 1, longitude: 2, timestamp}}]}, options: {tileHeaderVisible: false, tileHeaderInset: 0, tileMaximizeVisible: false, tileActionInset: 0}});
assert.equal(documentRoot.style['--symc-tile-action-inset'], '0px', 'Hiding the maximize control releases the horizontal space again.');
window.handleMessage({state: {success: true, devices: [{id: 'synthetic-a', location: {latitude: 1, longitude: 2, timestamp}}]}, options: {tileActionInset: -44}});
assert.equal(documentRoot.style['--symc-tile-action-inset'], '0px', 'Invalid overlay insets cannot create negative padding.');
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
window.handleMessage({state: {success: true, devices: []}, options: {openFreeMap: {theme: 'dark', customColors: true, darkWaterColor: '#123456'}}});
assert.equal(tileProviders.length, 1, 'Map settings update the existing vector layer.');
assert.equal(tileProviders[0].updates.at(-1).theme, 'dark', 'Map design reaches the central controller.');
assert.equal(markers.length, 0, 'Empty state removes old device pins.');
window.handleMessage({state: {success: false, devices: []}, options: {openFreeMap: null}});
assert.equal(tileProviders.length, 1, 'Missing map settings do not recreate the map layer.');
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
window.handleMessage({state: {success: true, devices: []}, options: {tileHeaderVisible: false, tileHeaderInset: 0}});
assert.equal(documentRoot.style['--symc-tile-header-inset'], '0px', 'A hidden native tile title does not leave an empty header gap.');
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
assert.equal(documentRoot.style['--symc-tile-header-inset'], '0px', 'The HTTP IPSView page must not reserve native tile title space.');
window.handleMessage({state: {success: true, devices: []}, options: {tileActionInset: 44}});
assert.equal(documentRoot.style['--symc-tile-action-inset'], '0px', 'IPSView cannot inherit a native maximize reservation.');
let resizeCallback;
window.ResizeObserver = class { constructor(callback) { resizeCallback = callback; } observe() {} };
vm.runInNewContext(source, {window, Date, Map, Set, Promise, URL, console});
const centerBeforeResize = map.center;
const zoomBeforeResize = map.zoom;
const invalidate = map.invalidateSize;
map.invalidateSize = function (options) { invalidate(options); this.center = [99, 99]; };
resizeCallback();
assert.equal(map.center, centerBeforeResize, 'Resizing restores the exact center after Leaflet invalidates its projection.');
assert.equal(map.zoom, zoomBeforeResize);
// Reconstruct the application exactly as a native host does after reconnect.
const sessionValues = new Map();
window.sessionStorage = {getItem(key) { return sessionValues.get(key) || null; }, setItem(key, value) { sessionValues.set(key, value); }};
window.location = {search: '?visuID=10000&visuUsername=synthetic'};
window.SymconOpenFreeMap.createViewState = require('../libs/helper/maps/openfreemap.js').createViewState;
map.getCenter = function () { return {lat: this.center[0], lng: this.center[1]}; };
map.invalidateSize = invalidate;
delete window.ResizeObserver;
const restart = (key, available = true) => {
    elements['woist-bootstrap'].textContent = JSON.stringify({mode: 'symcon', options: {viewStateKey: key}, state: {success: available, devices: available ? [
        {id: 'synthetic-a', name: 'Synthetic', location: {latitude: 1, longitude: 2, timestamp}}
    ] : []}});
    markers.length = 0;
    map.events = {};
    vm.runInNewContext(source, {window, Date, Map, Set, Promise, console});
};
restart('ICWI:100:200');
elements['device-list'].children[0].children[0].events.click();
map.setView([52.25, 13.5], 12);
map.events.moveend();
const fitsBeforeReconnect = fits;
restart('ICWI:100:200');
assert.deepEqual(Array.from(map.center), [52.25, 13.5], 'A reconstructed native iframe restores the camera center.');
assert.equal(map.zoom, 12, 'A reconstructed native iframe restores the zoom.');
assert.equal(fits, fitsBeforeReconnect, 'Restoration prevents initial fitBounds from replacing the saved view.');
assert.equal(elements['device-list'].children[0].children[0].attrs['aria-pressed'], 'true');
assert.equal(markers[0].opened, true, 'The selected device and its popup survive iframe reconstruction.');
assert.equal(markers[0].options.radius, 14, 'The restored selection must also restore marker emphasis.');
restart('ICWI:100:200', false);
window.handleMessage({state: {success: true, devices: [{id: 'synthetic-a', location: {latitude: 1, longitude: 2, timestamp}}]}});
assert.equal(elements['device-list'].children[0].children[0].attrs['aria-pressed'], 'true', 'A delayed valid inventory still restores the pending selection.');
assert.equal(markers[0].opened, true);
window.handleMessage({state: {success: true, devices: []}});
restart('ICWI:100:200');
assert.equal(elements['device-list'].children[0].children[0].attrs['aria-pressed'], 'false', 'A removed device cannot resurrect an old selection.');
restart('ICWI:100:201');
assert.deepEqual(Array.from(map.center), [20, 0], 'Another account connection starts with its own view.');
console.log('WoIst UI and native iframe reconstruction contracts passed.');

const cameraBeforeTheme = {center: map.center, zoom: map.zoom};
const fitsBeforeTheme = fits;
window.handleMessage({state: {success: true, devices: []}, options: {design: {themeCSS: ':root{--symc-text:#123456;}'}}});
assert.equal(elements['woist-theme'].textContent, ':root{--symc-text:#123456;}', 'Designer colors update the existing stylesheet.');
assert.deepEqual({center: map.center, zoom: map.zoom}, cameraBeforeTheme, 'Theme updates retain the camera.');
assert.equal(fits, fitsBeforeTheme, 'Theme updates do not fit all devices again.');

const selectionDevices = [
    {id: 'selection-a', location: {latitude: 10, longitude: 20, timestamp}},
    {id: 'selection-b', location: {latitude: 30, longitude: 40, timestamp, isOld: true}},
    {id: 'selection-missing', location: null}
];
window.handleMessage({state: {success: true, devices: selectionDevices}, options: {design: {markerSize: 12}}});
const selectionA = markers[0], selectionB = markers[1];
map.setView([0, 0], 18);
elements['device-list'].children[0].children[0].events.click();
assert.deepEqual(Array.from(map.center), [10, 20]);
assert.equal(map.zoom, 18, 'Selecting a device must preserve an already closer zoom.');
assert.equal(selectionA.options.radius, 17, 'Highlight size must follow the configured base size.');
assert.equal(selectionA.front, true, 'The selected pin must be brought above overlapping pins.');
elements['device-list'].children[1].children[0].events.click();
assert.deepEqual(Array.from(map.center), [30, 40], 'Switching list selection must center the newly selected device.');
assert.equal(selectionA.options.radius, 12, 'The previous pin returns to its configured size.');
assert.equal(selectionA.options.weight, 2);
assert.equal(selectionA.opened, false, 'The previous popup closes when selecting another device.');
assert.equal(selectionB.options.radius, 17);
assert.equal(selectionB.options.fillColor, '#b47722', 'Selection must retain the stale-location color.');
const selectedCamera = {center: map.center, zoom: map.zoom};
window.handleMessage({state: {success: true, devices: selectionDevices.map(device => device.id === 'selection-b'
    ? {...device, location: {...device.location, latitude: 31}} : device)}});
assert.deepEqual({center: map.center, zoom: map.zoom}, selectedCamera, 'A moving selected device must not recenter the user view on data updates.');
window.handleMessage({state: {success: true, devices: selectionDevices.map(device => device.id === 'selection-b'
    ? {...device, address: 'Updated Example Street 8, Example Town'} : device)}});
assert.ok(selectionB.popup.children.some(line => line.textContent === 'Updated Example Street 8, Example Town'), 'Resolved address updates the existing popup.');
assert.equal(selectionB.opened, true, 'Address arrival must preserve the open popup.');
assert.equal(elements['device-list'].children[1].children[0].attrs['aria-pressed'], 'true', 'Address arrival preserves device selection.');
assert.deepEqual({center: map.center, zoom: map.zoom}, selectedCamera, 'Address arrival preserves zoom and camera.');
assert.equal(selectionB.options.radius, 17);
window.handleMessage({state: {success: true, devices: selectionDevices.map(device => device.id === 'selection-b'
    ? {...device, address: 'Example Quarter, Example Town', addressNotice: 'Approximate – area only (about 368 m)'} : device)}});
assert.ok(selectionB.popup.children.some(line => line.className.includes('address-warning')), 'A precision downgrade must add a popup warning in place.');
assert.equal(elements['device-list'].children[1].children[0].attrs['aria-pressed'], 'true');
assert.equal(selectionB.opened, true);
assert.deepEqual({center: map.center, zoom: map.zoom}, selectedCamera, 'Precision changes must preserve camera, zoom and selection.');
window.handleMessage({state: {success: true, devices: selectionDevices.map(device => device.id === 'selection-b'
    ? {...device, address: '7, Example Street', addressNotice: ''} : device)}});
assert.ok(!selectionB.popup.children.some(line => line.className.includes('address-warning')), 'Improved precision must remove the old warning.');
assert.deepEqual({center: map.center, zoom: map.zoom}, selectedCamera);

elements['device-list'].children[2].children[0].events.click();
assert.deepEqual({center: map.center, zoom: map.zoom}, selectedCamera, 'Unlocated devices must not move the map.');
assert.equal(selectionB.options.radius, 12, 'Selecting an unlocated device clears the old marker emphasis.');
assert.equal(selectionB.opened, false);

const geoCamera = {center: map.center, zoom: map.zoom};
window.handleMessage({state: {success: true, devices: selectionDevices, geocoding: {provider: 'geoapify', notice: '<b>Daily limit reached</b>'}}});
assert.equal(elements['geocoding-attribution'].hidden, false, 'Geoapify must show its fixed provider attribution.');
assert.equal(elements['geocoding-message'].textContent, '<b>Daily limit reached</b>', 'Budget notices remain literal text.');
assert.equal(elements['geocoding-message'].hidden, false);
assert.deepEqual({center: map.center, zoom: map.zoom}, geoCamera, 'Quota updates preserve the camera.');
window.handleMessage({state: {success: true, devices: selectionDevices, geocoding: {provider: 'nominatim', notice: ''}}});
assert.equal(elements['geocoding-attribution'].hidden, true, 'Nominatim must not be attributed to Geoapify.');
assert.equal(elements['geocoding-message'].hidden, true);

const multiDevices = selectionDevices.map((device, index) => ({...device, location: device.location ? {...device.location, timestamp, isOld: false} : null, accountId: index === 0 ? 'one' : 'two', accountName: index === 0 ? 'First account' : 'Second account', accountAvailable: index !== 1}));
const multiState = {success: true, updatedAt: timestamp, accounts: [{id: 'one', name: 'First account', success: true}, {id: 'two', name: 'Second account', success: false}], devices: multiDevices};
window.handleMessage({state: multiState});
assert.equal(elements['account-filter'].children.length, 3, 'All accounts and both explicit account filters must be available.');
assert.equal(elements['woist-account-filter'].hidden, false);
elements['device-list'].children[1].children[0].events.click();
const filteredCamera = {center: map.center, zoom: map.zoom};
elements['woist-devices'].scrollTop = 93;
elements['account-filter'].value = 'one';
elements['account-filter'].events.change();
assert.equal(elements['device-list'].children.length, 1, 'Filtering affects list and marker scope only.');
assert.deepEqual({center: map.center, zoom: map.zoom}, filteredCamera, 'An account filter must not reset the camera.');
window.handleMessage({state: multiState});
assert.equal(elements['account-filter'].value, 'one', 'Data updates must preserve the account filter.');
assert.equal(elements['woist-devices'].scrollTop, 93, 'Data updates must preserve list scroll.');
elements['account-filter'].value = '';
elements['account-filter'].events.change();
assert.equal(elements['device-list'].children[1].children[0].attrs['aria-pressed'], 'true', 'A hidden selection returns when its account is shown again.');
assert.ok(elements['device-list'].children[1].children[0].className.includes('stale'), 'Only the unavailable source becomes stale in a partial failure.');
assert.ok(elements['device-list'].children[1].children[0].children.some(line => line.textContent.startsWith('Last known location')), 'A failed account must label even fresh cached coordinates as last known.');
assert.deepEqual({center: map.center, zoom: map.zoom}, filteredCamera);

// Exercise the actual shared persistence helper rather than a mock serializer.
for (const mode of ['symcon', 'ipsview']) {
    const reconnectState = {...multiState, accounts: multiState.accounts.map(account => ({...account, success: true}))};
    const restartMulti = available => {
        elements['woist-bootstrap'].textContent = JSON.stringify({mode, options: {viewStateKey: 'multi-account-' + mode}, state: available ? reconnectState : {success: false, devices: []}});
        markers.length = 0;
        map.events = {};
        vm.runInNewContext(source, {window, Date, Map, Set, Promise, URL, console});
    };
    restartMulti(true);
    elements['account-filter'].value = 'two';
    elements['account-filter'].events.change();
    elements['device-list'].children[0].children[0].events.click();
    map.setView([52.25, 13.5], 12);
    map.events.moveend();
    elements['woist-devices'].scrollTop = 75;
    elements['woist-devices'].events.scroll();
    restartMulti(false);
    window.handleMessage({state: reconnectState});
    assert.equal(elements['account-filter'].value, 'two', mode + ': iframe reconstruction must restore the source filter.');
    assert.equal(elements['woist-devices'].scrollTop, 75, mode + ': iframe reconstruction must restore list scroll.');
    assert.equal(elements['device-list'].children[0].children[0].attrs['aria-pressed'], 'true', mode + ': device selection must survive delayed data after reconnect.');
    assert.deepEqual(Array.from(map.center), [52.25, 13.5]);
    assert.equal(map.zoom, 12);
    const selectedOnly = {...reconnectState, devices: [reconnectState.devices[1]]};
    const selectionCamera = {center: map.center, zoom: map.zoom};
    window.handleMessage({state: selectedOnly});
    assert.equal(elements['device-list'].children.length, 1, mode + ': configured device selection narrows the list.');
    assert.equal(markers.length, 1, mode + ': configured device selection removes excluded markers.');
    assert.equal(elements['device-list'].children[0].children[0].attrs['aria-pressed'], 'true', mode + ': a retained selected device stays selected.');
    assert.equal(elements['woist-devices'].scrollTop, 75);
    assert.deepEqual({center: map.center, zoom: map.zoom}, selectionCamera);
    elements.fit.events.click();
    assert.deepEqual(Array.from(lastFitPositions, pair => Array.from(pair)), [[30, 40]], mode + ': show-all fits only configured visible devices.');
    elements['account-filter'].value = 'one';
    elements['account-filter'].events.change();
    assert.equal(elements['device-list'].children.length, 0, mode + ': the account filter further narrows the configured device selection.');
    assert.equal(elements.fit.disabled, true, mode + ': an empty intersection cannot fit excluded devices.');
    window.handleMessage({state: {...reconnectState, devices: [reconnectState.devices[0]]}});
    assert.equal(elements['device-list'].children[0].children[0].attrs['aria-pressed'], 'false', mode + ': excluding the selected device clears its selection without selecting another.');
    assert.deepEqual({center: map.center, zoom: map.zoom}, selectionCamera);
}

// Native order actions use account-scoped keys and never trigger device selection.
const orderKeyA = 'account:10000:' + 'a'.repeat(64), orderKeyB = 'account:10000:' + 'b'.repeat(64);
const orderDevices = [{id: 'order-a', orderKey: orderKeyA, name: 'Alpha', location: {latitude: 1, longitude: 2, timestamp}}, {id: 'order-b', orderKey: orderKeyB, name: 'Beta', location: {latitude: 3, longitude: 4, timestamp}}];
const orderCalls = [];
window.requestAction = (name, value) => { if (name === 'ChangeDeviceOrder') orderCalls.push(JSON.parse(value)); };
elements['woist-bootstrap'].textContent = JSON.stringify({mode: 'symcon', options: {viewStateKey: 'order-test'}, state: {success: true, devices: orderDevices}});
markers.length = 0;
vm.runInNewContext(source, {window, Date, Map, Set, Promise, URL, console});
elements['device-list'].children[0].children[0].events.click();
map.setView([11, 12], 13);
elements['woist-devices'].scrollTop = 45;
elements['device-list'].children[0].children[1].children[2].events.click();
assert.deepEqual(orderCalls.at(-1), {device: orderKeyA, before: ''}, 'Move down sends one explicit persistent order action.');
window.handleMessage({state: {success: true, devices: orderDevices.slice().reverse()}});
assert.equal(elements['device-list'].children[1].children[0].attrs['aria-pressed'], 'true');
assert.equal(elements['woist-devices'].scrollTop, 45);
assert.deepEqual(Array.from(map.center), [11, 12]);
assert.equal(map.zoom, 13);
const upHandle = elements['device-list'].children[1].children[1].children[0];
upHandle.events.keydown({key: 'ArrowUp', preventDefault() {}});
assert.deepEqual(orderCalls.at(-1), {device: orderKeyA, before: orderKeyB}, 'The drag handle also supports keyboard ordering.');
window.handleMessage({state: {success: true, devices: orderDevices}});
let row = elements['device-list'].children[0];
let handle = row.children[1].children[0];
const target = elements['device-list'].children[1];
target.closest = () => target;
target.getBoundingClientRect = () => ({top: 100, height: 100});
window.document.elementFromPoint = () => target;
const pointer = (type, y) => ({type, pointerId: 7, button: 0, isPrimary: true, clientX: 10, clientY: y, preventDefault() {}});
handle.events.pointerdown(pointer('pointerdown', 10));
handle.events.pointermove(pointer('pointermove', 180));
assert.equal(target.dataset.orderPlacement, 'after', 'The insertion marker must show which side of the target will receive the device.');
window.handleMessage({state: {success: true, devices: orderDevices}});
assert.equal(elements['device-list'].children[0], row, 'Incoming data cannot replace a captured drag handle.');
handle.events.pointerup(pointer('pointerup', 180));
assert.deepEqual(orderCalls.at(-1), {device: orderKeyA, before: ''}, 'Dropping below the last visible device moves to the end.');
window.handleMessage({state: {success: true, devices: orderDevices}});
const beforeCancel = orderCalls.length;
row = elements['device-list'].children[0]; handle = row.children[1].children[0];
handle.events.pointerdown(pointer('pointerdown', 10));
handle.events.pointercancel(pointer('pointercancel', 180));
assert.equal(orderCalls.length, beforeCancel, 'Canceled gestures must never persist an order change.');
