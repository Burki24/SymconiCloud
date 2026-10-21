(function (global) {
    'use strict';

    const fallback = {
        title: 'iCloud WoIst', refresh: 'Refresh devices', fit: 'Show all', devices: 'Devices',
        noDevices: 'No devices available.', noLocation: 'No location available', stale: 'Last known location',
        current: 'Current location', offline: 'Offline', battery: 'Battery', updated: 'Updated',
        accuracy: 'Accuracy', mapUnavailable: 'Map unavailable. Device data remains available below.',
        loadError: 'Some map tiles could not be loaded. Check the internet connection.',
        refreshing: 'Refreshingâ€¦', cacheError: 'Account unavailable. Cached locations may be out of date.',
        ago: 'ago', unknown: 'Unknown'
    };

    function validLocation(location) {
        return location !== null && typeof location === 'object'
            && typeof location.latitude === 'number' && Number.isFinite(location.latitude)
            && typeof location.longitude === 'number' && Number.isFinite(location.longitude)
            && Math.abs(location.latitude) <= 90 && Math.abs(location.longitude) <= 180;
    }

    function ageSeconds(timestamp, now) {
        return typeof timestamp === 'number' && Number.isFinite(timestamp) && timestamp > 0
            ? Math.max(0, Math.floor(now - timestamp)) : null;
    }

    function isStale(location, state, now) {
        const age = ageSeconds(location.timestamp, now);
        return state.success === false || location.isOld === true || age === null || age > Math.max(1, Number(state.staleAfter) || 900);
    }

    function parseState(value) {
        try {
            let state = typeof value === 'string' ? JSON.parse(value) : value;
            if (state && state.state) state = state.state;
            return state && typeof state === 'object' && Array.isArray(state.devices) ? state : null;
        } catch (_) {
            return null;
        }
    }

    function escapeHTML(value) {
        return String(value).replace(/[&<>"']/g, (character) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[character]));
    }

    const api = {validLocation, ageSeconds, isStale, parseState};
    if (typeof module !== 'undefined' && module.exports) module.exports = api;
    if (!global.document) return;

    let state = {success: false, devices: []};
    let labels = fallback;
    let design = {};
    let map = null;
    let tiles = null;
    let tileConfiguration = '';
    let layer = null;
    let accuracy = null;
    let selected = null;
    let fitted = false;
    let ready = false;
    let refreshTimer;
    const markers = new Map();
    const el = (id) => global.document.getElementById(id);
    const text = (key) => labels[key] || fallback[key] || key;
    const now = () => Date.now() / 1000;

    function node(tag, className, value) {
        const element = global.document.createElement(tag);
        if (className) element.className = className;
        if (value !== undefined) element.textContent = String(value);
        return element;
    }

    function ageText(timestamp) {
        const age = ageSeconds(timestamp, now());
        if (age === null) return text('unknown');
        const amount = age < 60 ? age + ' s' : age < 3600 ? Math.floor(age / 60) + ' min' : age < 86400 ? Math.floor(age / 3600) + ' h' : Math.floor(age / 86400) + ' d';
        return amount + ' ' + text('ago');
    }

    function details(device) {
        const lines = [];
        if (device.model) lines.push(String(device.model));
        if (device.online === false) lines.push(text('offline'));
        if (typeof device.batteryLevel === 'number' && Number.isFinite(device.batteryLevel)) lines.push(text('battery') + ': ' + Math.round(device.batteryLevel * 100) + '%');
        if (validLocation(device.location)) {
            lines.push(text(isStale(device.location, state, now()) ? 'stale' : 'current') + ' Â· ' + ageText(device.location.timestamp));
            if (typeof device.location.horizontalAccuracy === 'number' && device.location.horizontalAccuracy >= 0) lines.push(text('accuracy') + ': ' + Math.round(device.location.horizontalAccuracy) + ' m');
        } else lines.push(text('noLocation'));
        return lines;
    }

    function popup(device) {
        const content = node('div');
        content.appendChild(node('div', 'popup-title', device.name || text('unknown')));
        details(device).forEach((line) => content.appendChild(node('div', 'popup-detail', line)));
        return content;
    }

    function selectDevice(device, focus) {
        selected = device.id;
        if (accuracy && map) { map.removeLayer(accuracy); accuracy = null; }
        const marker = markers.get(device.id);
        if (marker && map) {
            if (focus) map.setView(marker.getLatLng(), Math.max(map.getZoom(), 15));
            marker.openPopup();
            const radius = device.location.horizontalAccuracy;
            if (typeof radius === 'number' && Number.isFinite(radius) && radius > 0) {
                accuracy = global.L.circle(marker.getLatLng(), {radius, color: design.currentColor || '#168d80', weight: 1, fillOpacity: 0.08, interactive: false}).addTo(map);
            }
        }
        global.document.querySelectorAll('.device').forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.id === selected)));
    }

    function fit() {
        if (!map || markers.size === 0) return;
        const positions = Array.from(markers.values(), (marker) => marker.getLatLng());
        map.fitBounds(global.L.latLngBounds(positions), {padding: [35, 35], maxZoom: 15});
        fitted = true;
    }

    function configureTiles(options) {
        if (!map) return;
        const configuration = JSON.stringify(options);
        if (configuration === tileConfiguration) return;
        tileConfiguration = configuration;
        if (tiles) { map.removeLayer(tiles); tiles = null; }
        if (options.tileUrl === '') {
            el('map-message').textContent = text('mapUnavailable');
            el('map-message').hidden = false;
            return;
        }
        tiles = global.L.tileLayer(options.tileUrl || 'https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: options.maxZoom || 19,
            attribution: options.tileUrl && !options.tileUrl.startsWith('https://tile.openstreetmap.org/')
                ? escapeHTML(options.attribution || '')
                : '<a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">Â© OpenStreetMap contributors</a>'
        }).addTo(map);
        el('map-message').hidden = true;
        tiles.on('tileerror', () => { el('map-message').textContent = text('loadError'); el('map-message').hidden = false; });
    }

    function configureDesign(options) {
        if (!options || typeof options !== 'object') return;
        design = options;
        const root = global.document.documentElement;
        if (root && root.style) {
            for (const [key, variable, minimum, maximum] of [['height', '--woist-height', 240, 1200], ['fontSize', '--woist-font-size', 10, 28]]) {
                if (Number.isFinite(design[key])) root.style.setProperty(variable, Math.max(minimum, Math.min(maximum, design[key])) + 'px');
            }
            for (const [key, variable] of [['currentColor', '--woist-current'], ['staleColor', '--woist-stale']]) {
                if (/^#[0-9a-f]{6}$/i.test(design[key])) root.style.setProperty(variable, design[key]);
            }
        }
        for (const [key, id] of [['showTitle', 'woist-heading'], ['showDevices', 'woist-devices'], ['showControls', 'woist-controls']]) {
            if (el(id) && typeof design[key] === 'boolean') el(id).hidden = !design[key];
        }
        const workspace = global.document.querySelector && global.document.querySelector('.workspace');
        if (workspace) workspace.style.gridTemplateColumns = design.showDevices === false ? 'minmax(0, 1fr)' : '';
        if (map) map.invalidateSize();
    }

    function render() {
        if (!ready) return;
        const selectedMarker = markers.get(selected);
        const reopenPopup = selectedMarker && typeof selectedMarker.isPopupOpen === 'function' && selectedMarker.isPopupOpen();
        const error = state.success === false ? (state.error || text('cacheError')) : '';
        el('account-message').textContent = error;
        el('account-message').hidden = !error;
        el('updated').textContent = text('updated') + ': ' + ageText(state.updatedAt);
        el('device-list').replaceChildren();
        if (layer) layer.clearLayers();
        markers.clear();
        if (accuracy && map) { map.removeLayer(accuracy); accuracy = null; }
        const devices = state.devices.filter((device) => device && typeof device === 'object');
        el('empty').hidden = devices.length !== 0;
        el('empty').textContent = text('noDevices');
        devices.forEach((device) => {
            const located = validLocation(device.location);
            const stale = located && isStale(device.location, state, now());
            const button = node('button', 'device' + (!located ? ' unlocated' : stale ? ' stale' : ''));
            button.type = 'button';
            button.dataset.id = String(device.id);
            button.setAttribute('aria-pressed', String(selected === device.id));
            button.appendChild(node('span', 'device-name', device.name || text('unknown')));
            details(device).forEach((line) => button.appendChild(node('span', 'device-meta', line)));
            button.addEventListener('click', () => selectDevice(device, true));
            const item = node('li');
            item.appendChild(button);
            el('device-list').appendChild(item);
            if (located && layer) {
                const marker = global.L.circleMarker([device.location.latitude, device.location.longitude], {
                    radius: design.markerSize || 9, color: '#ffffff', weight: 2, fillColor: stale ? (design.staleColor || '#b47722') : (design.currentColor || '#168d80'), fillOpacity: 0.95
                }).addTo(layer).bindPopup(popup(device));
                marker.on('click', () => selectDevice(device, false));
                markers.set(device.id, marker);
            }
        });
        el('fit').disabled = markers.size === 0;
        if (!fitted && markers.size > 0) fit();
        if (reopenPopup) {
            const device = devices.find((entry) => entry.id === selected);
            if (device) selectDevice(device, false);
        }
    }

    global.handleMessage = function (value) {
        const next = parseState(value);
        if (!next) return;
        const envelope = typeof value === 'string' ? JSON.parse(value) : value;
        if (envelope.labels) labels = Object.assign({}, fallback, envelope.labels);
        if (envelope.options) { configureTiles(envelope.options); configureDesign(envelope.options.design); }
        state = next;
        if (refreshTimer) global.clearTimeout(refreshTimer);
        if (ready) {
            el('refresh').disabled = typeof global.requestAction !== 'function';
            el('refresh').textContent = text('refresh');
        }
        render();
    };

    function start() {
        let bootstrap = {};
        try { bootstrap = JSON.parse(el('woist-bootstrap').textContent); } catch (_) { /* Show empty state. */ }
        labels = Object.assign({}, fallback, bootstrap.labels || {});
        state = parseState(bootstrap.state) || state;
        configureDesign((bootstrap.options || {}).design);
        if (bootstrap.mode === 'ipsview') el('refresh').hidden = true;
        ['title', 'refresh', 'fit'].forEach((key) => { el(key).textContent = text(key); });
        el('devices-title').textContent = text('devices');
        el('map').setAttribute('aria-label', text('title'));
        try {
            if (!global.L) throw new Error('Leaflet unavailable');
            map = global.L.map('map').setView([20, 0], 2);
            configureTiles(bootstrap.options || {});
            layer = global.L.layerGroup().addTo(map);
            if (typeof global.ResizeObserver === 'function') new global.ResizeObserver(() => map.invalidateSize()).observe(el('map'));
            else global.addEventListener('resize', () => map.invalidateSize());
        } catch (_) {
            el('map-message').textContent = text('mapUnavailable');
            el('map-message').hidden = false;
        }
        ready = true;
        el('fit').addEventListener('click', fit);
        el('refresh').disabled = typeof global.requestAction !== 'function';
        el('refresh').addEventListener('click', () => {
            if (typeof global.requestAction !== 'function') return;
            el('refresh').disabled = true;
            el('refresh').textContent = text('refreshing');
            refreshTimer = global.setTimeout(() => {
                el('refresh').disabled = false;
                el('refresh').textContent = text('refresh');
            }, 30000);
            const failed = () => {
                el('account-message').textContent = text('cacheError');
                el('account-message').hidden = false;
                el('refresh').disabled = false;
                el('refresh').textContent = text('refresh');
            };
            try { Promise.resolve(global.requestAction('RefreshDevices', '')).catch(failed); } catch (_) { failed(); }
        });
        render();
        global.setInterval(render, 30000);
        if (typeof global.requestAction === 'function') {
            try { Promise.resolve(global.requestAction('GetState', '')).catch(() => {}); } catch (_) { /* Bootstrap remains usable. */ }
        }
    }

    global.WoIstMap = api;
    if (global.document.readyState === 'loading') global.document.addEventListener('DOMContentLoaded', start, {once: true});
    else start();
}(typeof window === 'undefined' ? globalThis : window));
