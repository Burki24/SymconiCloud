(function (global) {
    'use strict';

    const fallback = {
        title: 'iCloud WoIst', refresh: 'Refresh devices', fit: 'Show all', devices: 'Devices',
        noDevices: 'No devices available.', noLocation: 'No location available', stale: 'Last known location',
        current: 'Current location', offline: 'Offline', battery: 'Battery', updated: 'Updated',
        accuracy: 'Accuracy', mapUnavailable: 'Map unavailable. Device data remains available below.',
        loadError: 'Some map tiles could not be loaded. Check the internet connection.',
        refreshing: 'Refreshing…', cacheError: 'Account unavailable. Cached locations may be out of date.',
        ago: 'ago', unknown: 'Unknown',
        transportError: 'Live updates unavailable. Retrying…',
        transportConfiguration: 'Configure the Symcon server URL for IPSView live updates.'
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

    function transportURL(value, location) {
        try {
            if (typeof value !== 'string' || !value) return null;
            const absolute = /^https?:\/\//i.test(value);
            if (!absolute && (!value.startsWith('/') || value.startsWith('//') || !/^https?:$/.test(location.protocol))) return null;
            const url = absolute ? new URL(value) : new URL(value, location.origin);
            if (!absolute && url.origin !== location.origin) return null;
            return /^https?:$/.test(url.protocol) && !url.username && !url.password && !url.hash && !url.search ? url.href : null;
        } catch (_) { return null; }
    }

    // The public HTTP page receives its bearer only in the navigation fragment.
    // Remove it from the document URL before any map or state request starts.
    function consumeFragmentToken(configuration, environment) {
        if (!configuration || configuration.fragmentToken !== true) return configuration;
        const result = Object.assign({}, configuration, {token: ''});
        try {
            const url = new URL(environment.location.href);
            if (!/^https?:$/.test(url.protocol)) return result;
            const match = /^#token=([a-f0-9]{64})$/i.exec(url.hash);
            url.hash = '';
            environment.history.replaceState(null, '', url.pathname + url.search);
            if (match) result.token = match[1];
        } catch (_) { /* Fail closed if the credential cannot be removed. */ }
        return result;
    }

    // Only the cached map envelope is read. Tokens stay in a request header and
    // the standalone map document resolves its endpoint on the Symcon HTTP origin.
    function createIPSViewTransport(configuration, environment, receive, status) {
        const url = transportURL(configuration && configuration.url, environment.location || {});
        const token = configuration && configuration.token;
        const interval = Math.min(30000, Math.max(1000, Number(configuration && configuration.pollInterval) || 5000));
        let timer = null;
        let controller = null;
        let active = false;
        let epoch = 0;
        let failures = 0;
        const valid = url && typeof token === 'string' && /^[a-f0-9]{64}$/i.test(token)
            && typeof environment.fetch === 'function' && typeof environment.AbortController === 'function';
        const hidden = () => environment.document && environment.document.hidden;
        function cancel() {
            epoch++;
            if (timer !== null) environment.clearTimeout(timer);
            timer = null;
            if (controller) controller.abort();
            controller = null;
        }
        function schedule(delay) {
            if (active && !hidden()) timer = environment.setTimeout(poll, delay);
        }
        async function poll() {
            timer = null;
            if (!active || hidden()) return;
            const generation = epoch;
            const requestController = new environment.AbortController();
            controller = requestController;
            let deadline;
            try {
                const result = await Promise.race([
                    (async () => {
                        const response = await environment.fetch(url, {
                            method: 'POST', headers: {'X-WoIst-Token': token}, credentials: 'omit',
                            mode: 'cors', cache: 'no-store', redirect: 'error', signal: requestController.signal
                        });
                        if (!response.ok) throw new Error('Transport failed');
                        const envelope = await response.json();
                        if (!parseState(envelope)) throw new Error('Invalid map state');
                        return envelope;
                    })(),
                    new Promise((_, reject) => {
                        deadline = environment.setTimeout(() => {
                            requestController.abort();
                            reject(new Error('Transport timeout'));
                        }, 10000);
                    })
                ]);
                if (!active || hidden() || generation !== epoch) return;
                receive(result);
                failures = 0;
                status('');
            } catch (_) {
                if (!active || hidden() || generation !== epoch) return;
                failures++;
                status('transportError');
            } finally {
                environment.clearTimeout(deadline);
                if (generation === epoch) {
                    controller = null;
                    schedule(Math.min(30000, interval * (2 ** Math.min(failures, 5))));
                }
            }
        }
        function visibility() {
            cancel();
            if (active && !hidden()) schedule(0);
        }
        function pagehide() { cancel(); }
        const transport = {
            start() {
                if (active) return;
                if (!valid) { status('transportConfiguration'); return; }
                active = true;
                environment.document.addEventListener('visibilitychange', visibility);
                environment.addEventListener('pagehide', pagehide);
                environment.addEventListener('pageshow', visibility);
                environment.addEventListener('beforeunload', transport.stop);
                schedule(0);
            },
            stop() {
                active = false;
                cancel();
                environment.document.removeEventListener('visibilitychange', visibility);
                environment.removeEventListener('pagehide', pagehide);
                environment.removeEventListener('pageshow', visibility);
                environment.removeEventListener('beforeunload', transport.stop);
            }
        };
        return transport;
    }

    const api = {validLocation, ageSeconds, isStale, parseState, transportURL, consumeFragmentToken, createIPSViewTransport};
    if (typeof module !== 'undefined' && module.exports) module.exports = api;
    if (!global.document) return;

    let state = {success: false, devices: []};
    let labels = fallback;
    let design = {};
    let map = null;
    let vectorController = null;
    let vectorInitialization = null;
    let vectorOptions = null;
    let layer = null;
    let accuracy = null;
    let selected = null;
    let fitted = false;
    let ready = false;
    let outputMode = '';
    let resizing = false;
    let refreshTimer;
    let viewMemory = null;
    let viewPersistenceReady = false;
    let pendingViewSelection = null;
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
            lines.push(text(isStale(device.location, state, now()) ? 'stale' : 'current') + ' · ' + ageText(device.location.timestamp));
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

    function selectDevice(device, focus, open = true) {
        selected = device.id;
        if (accuracy && map) { map.removeLayer(accuracy); accuracy = null; }
        const marker = markers.get(device.id);
        if (marker && map) {
            if (focus) map.setView(marker.getLatLng(), Math.max(map.getZoom(), 15));
            if (open) marker.openPopup();
            const radius = device.location.horizontalAccuracy;
            if (typeof radius === 'number' && Number.isFinite(radius) && radius > 0) {
                accuracy = global.L.circle(marker.getLatLng(), {radius, color: design.currentColor || '#168d80', weight: 1, fillOpacity: 0.08, interactive: false}).addTo(map);
            }
        }
        global.document.querySelectorAll('.device').forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.id === selected)));
        saveView();
    }

    function saveView() {
        if (!viewMemory || !viewPersistenceReady || !map || state.success === false) return;
        const center = map.getCenter();
        const location = typeof center.wrap === 'function' ? center.wrap() : center;
        const marker = markers.get(selected);
        viewMemory.write({center: [location.lat, location.lng], zoom: map.getZoom(), selected,
            popup: Boolean(marker && marker.isPopupOpen && marker.isPopupOpen())});
    }

    function fit() {
        if (!map || markers.size === 0) return;
        const positions = Array.from(markers.values(), (marker) => marker.getLatLng());
        map.fitBounds(global.L.latLngBounds(positions), {padding: [35, 35], maxZoom: 15});
        fitted = true;
    }

    function configureTiles(options) {
        if (!map) return;
        vectorOptions = options.openFreeMap || vectorOptions;
        const status = (message) => {
            el('map-message').textContent = message ? text(message) : '';
            el('map-message').hidden = !message;
        };
        const initialize = () => {
            vectorController = global.SymconOpenFreeMap.createController(map, global, status);
            vectorController.update(vectorOptions);
        };
        try {
            if (vectorController) vectorController.update(vectorOptions);
            else if (!vectorInitialization) {
                if (global.SymconVectorAssetsReady) {
                    vectorInitialization = global.SymconVectorAssetsReady.then(initialize).catch(() => status('mapUnavailable'));
                } else initialize();
            }
        } catch (_) { status('mapUnavailable'); }
    }

    function configureTileHeader(options) {
        const root = global.document.documentElement;
        const inset = outputMode === 'symcon' ? options.tileHeaderInset : 0;
        if (root && root.style && Number.isFinite(inset) && inset >= 0) root.style.setProperty('--symc-tile-header-inset', inset + 'px');
    }

    function resizeMap() {
        if (!map) return;
        const center = map.getCenter();
        const zoom = map.getZoom();
        const wasFitted = fitted;
        resizing = true;
        try {
            map.invalidateSize({pan: false});
            map.setView(center, zoom, {animate: false, reset: true});
        } finally {
            fitted = wasFitted;
            resizing = false;
        }
    }

    function configureDesign(options) {
        if (!options || typeof options !== 'object') return;
        const layout = (value) => JSON.stringify([value.height, value.fontSize, value.showTitle, value.showDevices, value.showControls]);
        const layoutChanged = layout(design) !== layout(options);
        design = options;
        const root = global.document.documentElement;
        if (root && root.style) {
            for (const [key, variable, minimum, maximum] of [['height', '--woist-height', 0, 1200], ['fontSize', '--woist-font-size', 10, 28]]) {
                if (Number.isFinite(design[key])) root.style.setProperty(variable, (key === 'height' && design[key] === 0 ? 480 : Math.max(minimum, Math.min(maximum, design[key]))) + 'px');
            }
            for (const [key, variable] of [['currentColor', '--woist-current'], ['staleColor', '--woist-stale']]) {
                if (/^#[0-9a-f]{6}$/i.test(design[key])) root.style.setProperty(variable, design[key]);
            }
        }
        for (const [key, id] of [['showTitle', 'woist-heading'], ['showDevices', 'woist-devices'], ['showControls', 'woist-controls']]) {
            if (el(id) && typeof design[key] === 'boolean') el(id).hidden = !design[key];
        }
        const workspace = global.document.querySelector && global.document.querySelector('.workspace');
        if (workspace) {
            workspace.style.gridTemplateColumns = design.showDevices === false ? 'minmax(0, 1fr)' : '';
            workspace.style.gridTemplateRows = design.showDevices === false ? 'minmax(0, 1fr)' : '';
        }
        // ResizeObserver handles real size changes. Invalidating an unchanged
        // Leaflet map discards its exact center and introduces pixel rounding.
        if (map && layoutChanged && typeof global.ResizeObserver !== 'function') resizeMap();
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
        if (accuracy && map) { map.removeLayer(accuracy); accuracy = null; }
        const devices = state.devices.filter((device) => device && typeof device === 'object');
        const locatedIDs = new Set(devices.filter((device) => validLocation(device.location)).map((device) => device.id));
        for (const [id, marker] of markers) {
            if (!locatedIDs.has(id)) { layer.removeLayer(marker); markers.delete(id); }
        }
        if (!devices.some((device) => device.id === selected)) selected = null;
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
                const position = [device.location.latitude, device.location.longitude];
                const style = {
                    radius: design.markerSize || 9, color: '#ffffff', weight: 2, fillColor: stale ? (design.staleColor || '#b47722') : (design.currentColor || '#168d80'), fillOpacity: 0.95
                };
                const existing = markers.get(device.id);
                if (existing) {
                    existing.setLatLng(position).setStyle(style).setRadius(style.radius).setPopupContent(popup(device));
                } else {
                    const marker = global.L.circleMarker(position, style).addTo(layer).bindPopup(popup(device), {autoPan: false});
                    marker.on('click', () => {
                        const current = state.devices.find((entry) => entry.id === device.id);
                        if (current) selectDevice(current, false);
                    });
                    markers.set(device.id, marker);
                }
            }
        });
        el('fit').disabled = markers.size === 0;
        if (!fitted && markers.size > 0) fit();
        if (selected !== null) {
            const device = devices.find((entry) => entry.id === selected);
            if (device) selectDevice(device, false, Boolean(reopenPopup));
        }
        if (pendingViewSelection && state.success !== false) {
            const restored = pendingViewSelection;
            pendingViewSelection = null;
            const device = devices.find(entry => entry.id === restored.selected);
            if (device) selectDevice(device, false, restored.popup);
        }
        saveView();
    }

    global.handleMessage = function (value) {
        const next = parseState(value);
        if (!next) return;
        const envelope = typeof value === 'string' ? JSON.parse(value) : value;
        if (envelope.labels) labels = Object.assign({}, fallback, envelope.labels);
        if (envelope.options) { configureTileHeader(envelope.options); configureTiles(envelope.options); configureDesign(envelope.options.design); }
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
        const transportConfiguration = bootstrap.mode === 'ipsview'
            ? consumeFragmentToken((bootstrap.options || {}).ipsViewTransport, global) : null;
        outputMode = bootstrap.mode || '';
        if (outputMode === 'symcon' && global.SymconOpenFreeMap && global.SymconOpenFreeMap.createViewState) {
            viewMemory = global.SymconOpenFreeMap.createViewState((bootstrap.options || {}).viewStateKey, global);
        }
        const restoredView = viewMemory && viewMemory.read();
        pendingViewSelection = restoredView;
        configureTileHeader(bootstrap.options || {});
        labels = Object.assign({}, fallback, bootstrap.labels || {});
        state = parseState(bootstrap.state) || state;
        configureDesign((bootstrap.options || {}).design);
        if (bootstrap.mode === 'ipsview') el('refresh').hidden = true;
        ['title', 'refresh', 'fit'].forEach((key) => { el(key).textContent = text(key); });
        el('devices-title').textContent = text('devices');
        el('map').setAttribute('aria-label', text('title'));
        try {
            if (!global.L) throw new Error('Leaflet unavailable');
            map = global.L.map('map', {trackResize: false}).setView(restoredView ? restoredView.center : [20, 0], restoredView ? restoredView.zoom : 2);
            if (restoredView) fitted = true;
            map.on('movestart', () => { if (!resizing) fitted = true; });
            configureTiles(bootstrap.options || {});
            layer = global.L.layerGroup().addTo(map);
            if (typeof global.ResizeObserver === 'function') new global.ResizeObserver(resizeMap).observe(el('map'));
            else global.addEventListener('resize', resizeMap);
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
        viewPersistenceReady = true;
        if (map) {
            for (const event of ['moveend', 'zoomend', 'popupopen', 'popupclose']) map.on(event, saveView);
        }
        global.addEventListener('pagehide', saveView);
        saveView();
        global.setInterval(render, 30000);
        if (bootstrap.mode === 'ipsview') {
            createIPSViewTransport(transportConfiguration, global, global.handleMessage, (key) => {
                el('transport-message').textContent = key ? text(key) : '';
                el('transport-message').hidden = !key;
            }).start();
        } else if (typeof global.requestAction === 'function') {
            try { Promise.resolve(global.requestAction('GetState', '')).catch(() => {}); } catch (_) { /* Bootstrap remains usable. */ }
        }
    }

    global.WoIstMap = api;
    if (global.document.readyState === 'loading') global.document.addEventListener('DOMContentLoaded', start, {once: true});
    else start();
}(typeof window === 'undefined' ? globalThis : window));
