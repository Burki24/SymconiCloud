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
        transportConfiguration: 'Configure the Symcon server URL for IPSView live updates.',
        accounts: 'Account filter', allAccounts: 'All accounts', unavailableAccount: 'Unavailable account',
        dragDevice: 'Drag to reorder', moveUp: 'Move up', moveDown: 'Move down', resetOrder: 'Alphabetical order',
        orderError: 'Order could not be saved. Please try again.'
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
        let refreshing = false;
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
        async function poll(refresh = false, order = null) {
            timer = null;
            if (!active || hidden()) return false;
            const generation = epoch;
            const requestController = new environment.AbortController();
            controller = requestController;
            let deadline;
            try {
                const result = await Promise.race([
                    (async () => {
                        const response = await environment.fetch(order ? url + '/order' : refresh ? url + '/refresh' : url, {
                            method: 'POST', headers: Object.assign({'X-WoIst-Token': token}, order ? {'Content-Type': 'application/json'} : {}), credentials: 'omit',
                            ...(order ? {body: JSON.stringify(order)} : {}),
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
                        }, refresh ? 30000 : 10000);
                    })
                ]);
                if (!active || hidden() || generation !== epoch) return false;
                receive(result);
                failures = 0;
                status('');
                return true;
            } catch (_) {
                if (!active || hidden() || generation !== epoch) return false;
                failures++;
                status(order ? 'orderError' : 'transportError');
                return false;
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
            canRefresh: Boolean(valid),
            async refresh() {
                if (!active || hidden() || refreshing) return false;
                cancel();
                refreshing = true;
                try { return await poll(true); } finally { refreshing = false; }
            },
            async order(operation) {
                if (!active || hidden() || refreshing) return false;
                cancel();
                refreshing = true;
                try { return await poll(false, operation); } finally { refreshing = false; }
            },
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
    let accountFilter = '';
    let selectedAccount = '';
    let fitted = false;
    let ready = false;
    let outputMode = '';
    let resizing = false;
    let refreshTimer;
    let refreshPending = false;
    let dragOrder = null;
    let orderPending = false;
    let orderTimer = null;
    let ipsViewTransport = null;
    let viewMemory = null;
    let viewPersistenceReady = false;
    let pendingViewSelection = null;
    const markers = new Map();
    const el = (id) => global.document.getElementById(id);
    const text = (key) => labels[key] || fallback[key] || key;
    const now = () => Date.now() / 1000;
    const canRefresh = () => outputMode === 'ipsview'
        ? Boolean(ipsViewTransport && ipsViewTransport.canRefresh) : typeof global.requestAction === 'function';

    function orderFailure() {
        const message = el('order-message');
        if (message) { message.textContent = text('orderError'); message.hidden = false; }
    }

    function sendOrder(operation) {
        if (orderPending || !canRefresh()) return;
        orderPending = true;
        const message = el('order-message');
        if (message) message.hidden = true;
        orderTimer = global.setTimeout(() => { orderPending = false; orderFailure(); }, 12000);
        try {
            if (outputMode === 'ipsview') {
                ipsViewTransport.order(operation).then(ok => { orderPending = false; global.clearTimeout(orderTimer); if (!ok) orderFailure(); });
            } else {
                const result = global.requestAction('ChangeDeviceOrder', JSON.stringify(operation));
                if (result && typeof result.catch === 'function') result.catch(() => { orderPending = false; global.clearTimeout(orderTimer); orderFailure(); });
            }
        } catch (_) { orderPending = false; global.clearTimeout(orderTimer); orderFailure(); }
    }

    function locateOrderDrop() {
        if (!dragOrder) return;
        const target = global.document.elementFromPoint(dragOrder.x, dragOrder.y)?.closest('li[data-order-key]');
        global.document.querySelectorAll('.order-drop-target').forEach(row => { row.classList.remove('order-drop-target'); delete row.dataset.orderPlacement; });
        dragOrder.before = null;
        if (!target || target.parentElement !== el('device-list') || target.dataset.orderKey === dragOrder.key) return;
        const box = target.getBoundingClientRect();
        const below = dragOrder.y > box.top + box.height / 2;
        dragOrder.before = below ? target.nextElementSibling?.dataset.orderKey || '' : target.dataset.orderKey;
        target.dataset.orderPlacement = below ? 'after' : 'before';
        target.classList.add('order-drop-target');
    }

    function scrollOrderDrag() {
        if (!dragOrder || !dragOrder.moved) return;
        const list = el('woist-devices');
        const box = list.getBoundingClientRect();
        const y = dragOrder.y;
        const x = dragOrder.x;
        if (x >= box.left && x <= box.right && y >= box.top && y <= box.bottom) {
            const delta = y < box.top + 40 ? -10 : y > box.bottom - 40 ? 10 : 0;
            if (delta) { list.scrollTop += delta; locateOrderDrop(); }
        }
        dragOrder.frame = global.requestAnimationFrame(scrollOrderDrag);
    }

    function deviceOrderControls(device, devices, item) {
        if (!/^account:[1-9][0-9]{0,9}:[a-f0-9]{64}$/.test(device.orderKey || '')) return;
        const index = devices.indexOf(device);
        const tools = node('div', 'device-order');
        const handle = node('button', 'device-order-handle', '↕');
        const up = node('button', 'device-order-up', '↑');
        const down = node('button', 'device-order-down', '↓');
        for (const [button, label] of [[handle, 'dragDevice'], [up, 'moveUp'], [down, 'moveDown']]) {
            button.type = 'button'; button.title = text(label); button.setAttribute('aria-label', text(label));
            button.dataset.orderControl = label; button.dataset.orderDevice = device.orderKey;
            tools.appendChild(button);
        }
        up.disabled = index === 0 || !canRefresh();
        down.disabled = index === devices.length - 1 || !canRefresh();
        handle.disabled = !canRefresh();
        const move = direction => {
            if (direction < 0 && index > 0) sendOrder({device: device.orderKey, before: devices[index - 1].orderKey});
            if (direction > 0 && index < devices.length - 1) sendOrder({device: device.orderKey, before: devices[index + 2]?.orderKey || ''});
        };
        up.addEventListener('click', () => move(-1));
        down.addEventListener('click', () => move(1));
        handle.addEventListener('keydown', event => {
            if (event.key === 'ArrowUp' || event.key === 'ArrowDown') { event.preventDefault(); move(event.key === 'ArrowUp' ? -1 : 1); }
        });
        item.dataset.orderKey = device.orderKey;
        handle.addEventListener('pointerdown', event => {
            if (event.button !== 0 || event.isPrimary === false || orderPending || dragOrder) return;
            event.preventDefault();
            dragOrder = {key: device.orderKey, pointer: event.pointerId, start: event.clientY, x: event.clientX, y: event.clientY, moved: false, before: null};
            try { handle.setPointerCapture(event.pointerId); } catch (_) { /* Arrow controls remain available. */ }
        });
        handle.addEventListener('pointermove', event => {
            if (!dragOrder || event.pointerId !== dragOrder.pointer) return;
            if (Math.abs(event.clientY - dragOrder.start) < 6 && !dragOrder.moved) return;
            dragOrder.moved = true;
            dragOrder.x = event.clientX; dragOrder.y = event.clientY;
            event.preventDefault();
            locateOrderDrop();
            if (dragOrder.frame === undefined && typeof global.requestAnimationFrame === 'function') dragOrder.frame = global.requestAnimationFrame(scrollOrderDrag);
        });
        const finish = event => {
            if (!dragOrder || event.pointerId !== dragOrder.pointer) return;
            const operation = dragOrder;
            dragOrder = null;
            if (operation.frame !== undefined && typeof global.cancelAnimationFrame === 'function') global.cancelAnimationFrame(operation.frame);
            try { handle.releasePointerCapture(event.pointerId); } catch (_) { /* Capture may already be released. */ }
            if (event.type === 'pointerup' && operation.moved && operation.before !== null && operation.before !== operation.key) sendOrder({device: operation.key, before: operation.before});
            render();
        };
        handle.addEventListener('pointerup', finish);
        handle.addEventListener('pointercancel', finish);
        handle.addEventListener('lostpointercapture', finish);
        item.appendChild(tools);
    }

    function finishRefresh() {
        if (refreshTimer) global.clearTimeout(refreshTimer);
        refreshPending = false;
        el('refresh').disabled = !canRefresh();
        el('refresh').textContent = text('refresh');
    }

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
        if ((state.accounts || []).length > 1 && device.accountName) lines.push(String(device.accountName));
        if (device.model) lines.push(String(device.model));
        if (device.online === false) lines.push(text('offline'));
        if (typeof device.batteryLevel === 'number' && Number.isFinite(device.batteryLevel)) lines.push(text('battery') + ': ' + Math.round(device.batteryLevel * 100) + '%');
        if (validLocation(device.location)) {
            lines.push(text(device.accountAvailable === false || isStale(device.location, state, now()) ? 'stale' : 'current') + ' · ' + ageText(device.location.timestamp));
            if (typeof device.address === 'string' && device.address) lines.push(device.address);
            if (typeof device.addressNotice === 'string' && device.addressNotice) lines.push(device.addressNotice);
            if (typeof device.location.horizontalAccuracy === 'number' && device.location.horizontalAccuracy > 0) lines.push(text('accuracy') + ': ' + Math.ceil(device.location.horizontalAccuracy) + ' m');
        } else lines.push(text('noLocation'));
        return lines;
    }

    function popup(device) {
        const content = node('div');
        content.appendChild(node('div', 'popup-title', device.name || text('unknown')));
        details(device).forEach((line) => content.appendChild(detailNode(device, line, 'div', 'popup-detail')));
        return content;
    }

    function detailNode(device, line, tag, className) {
        const warning = typeof device.addressNotice === 'string' && device.addressNotice !== '' && line === device.addressNotice;
        const result = node(tag, className + (warning ? ' address-warning' : ''), line);
        if (warning) result.setAttribute('role', 'note');
        return result;
    }

    function markerStyle(device) {
        const chosen = device.id === selected;
        const stale = device.accountAvailable === false || isStale(device.location, state, now());
        return {
            radius: (design.markerSize || 9) + (chosen ? 5 : 0),
            color: '#ffffff', weight: chosen ? 4 : 2,
            fillColor: stale ? (design.staleColor || '#b47722') : (design.currentColor || '#168d80'),
            fillOpacity: chosen ? 1 : 0.95
        };
    }

    function selectDevice(device, focus, open = true) {
        const previous = markers.get(selected);
        if (selected !== device.id && previous && typeof previous.closePopup === 'function') previous.closePopup();
        selected = device.id;
        selectedAccount = device.accountId || '';
        for (const current of state.devices) {
            const item = current && markers.get(current.id);
            if (item) {
                const style = markerStyle(current);
                item.setStyle(style).setRadius(style.radius);
            }
        }
        if (accuracy && map) { map.removeLayer(accuracy); accuracy = null; }
        const marker = markers.get(device.id);
        if (marker && map) {
            marker.bringToFront();
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
            popup: Boolean(marker && marker.isPopupOpen && marker.isPopupOpen()), filter: accountFilter,
            scroll: Math.max(0, Number(el('woist-devices').scrollTop) || 0)});
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
        const actionInset = outputMode === 'symcon' ? options.tileActionInset : 0;
        if (root && root.style && actionInset !== undefined) root.style.setProperty('--symc-tile-action-inset', Number.isFinite(actionInset) && actionInset >= 0 ? Math.min(actionInset, 96) + 'px' : '0px');
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
        const theme = el('woist-theme');
        if (theme && typeof design.themeCSS === 'string' && theme.textContent !== design.themeCSS) theme.textContent = design.themeCSS;
        const root = global.document.documentElement;
        if (root && root.style) {
            for (const [key, variable, minimum, maximum] of [['height', '--woist-height', 0, 1200], ['fontSize', '--woist-font-size', outputMode === 'ipsview' ? 8 : 10, outputMode === 'ipsview' ? 64 : 28]]) {
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
        const orderFocus = global.document.activeElement?.dataset;
        if (!ready) return;
        const scroll = Number(el('woist-devices').scrollTop) || 0;
        let restoredScroll = null;
        const accounts = Array.isArray(state.accounts) ? state.accounts : [];
        const filter = el('account-filter');
        if (filter) {
            if (accounts.length && accountFilter && !accounts.some(account => account.id === accountFilter)) accountFilter = '';
            filter.replaceChildren();
            const all = node('option', '', text('allAccounts'));
            all.value = '';
            filter.appendChild(all);
            for (const account of accounts) {
                const option = node('option', '', String(account.name || text('unknown')) + (account.success === false ? ' · ' + text('unavailableAccount') : ''));
                option.value = account.id;
                filter.appendChild(option);
            }
            filter.value = accountFilter;
            el('account-filter-label').textContent = text('accounts');
            el('woist-account-filter').hidden = accounts.length < 2;
        }
        const geocoding = state.geocoding || {};
        if (el('geocoding-attribution')) el('geocoding-attribution').hidden = geocoding.provider !== 'geoapify';
        if (el('geocoding-message')) {
            el('geocoding-message').textContent = typeof geocoding.notice === 'string' ? geocoding.notice : '';
            el('geocoding-message').hidden = !el('geocoding-message').textContent;
        }
        const selectedMarker = markers.get(selected);
        const reopenPopup = selectedMarker && typeof selectedMarker.isPopupOpen === 'function' && selectedMarker.isPopupOpen();
        const error = state.success === false || accounts.some(account => account.success === false) ? (state.error || text('cacheError')) : '';
        el('account-message').textContent = error;
        el('account-message').hidden = !error;
        el('updated').textContent = text('updated') + ': ' + ageText(state.updatedAt);
        el('device-list').replaceChildren();
        if (accuracy && map) { map.removeLayer(accuracy); accuracy = null; }
        const allDevices = state.devices.filter((device) => device && typeof device === 'object');
        const devices = allDevices.filter(device => !accountFilter || device.accountId === accountFilter);
        if (el('reset-order')) el('reset-order').disabled = !canRefresh() || allDevices.length === 0;
        const locatedIDs = new Set(devices.filter((device) => validLocation(device.location)).map((device) => device.id));
        for (const [id, marker] of markers) {
            if (!locatedIDs.has(id)) { layer.removeLayer(marker); markers.delete(id); }
        }
        const missingSource = accounts.find(account => account.id === selectedAccount);
        if (!allDevices.some((device) => device.id === selected) && state.success !== false && (!missingSource || missingSource.success !== false)) selected = null;
        el('empty').hidden = devices.length !== 0;
        el('empty').textContent = text('noDevices');
        devices.forEach((device) => {
            const located = validLocation(device.location);
            const stale = located && (device.accountAvailable === false || isStale(device.location, state, now()));
            const button = node('button', 'device' + (!located ? ' unlocated' : stale ? ' stale' : ''));
            button.type = 'button';
            button.dataset.id = String(device.id);
            button.setAttribute('aria-pressed', String(selected === device.id));
            button.appendChild(node('span', 'device-name', device.name || text('unknown')));
            details(device).forEach((line) => button.appendChild(detailNode(device, line, 'span', 'device-meta')));
            button.addEventListener('click', () => selectDevice(device, true));
            const item = node('li');
            item.appendChild(button);
            deviceOrderControls(device, devices, item);
            el('device-list').appendChild(item);
            if (located && layer) {
                const position = [device.location.latitude, device.location.longitude];
                const style = markerStyle(device);
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
        if (orderFocus?.orderDevice) {
            global.document.querySelectorAll('button[data-order-device]').forEach(button => {
                if (button.dataset.orderDevice === orderFocus.orderDevice && button.dataset.orderControl === orderFocus.orderControl && typeof button.focus === 'function') button.focus({preventScroll: true});
            });
        }
        if (selected !== null) {
            const device = devices.find((entry) => entry.id === selected);
            if (device) selectDevice(device, false, Boolean(reopenPopup));
        }
        if (pendingViewSelection && state.success !== false) {
            const restored = pendingViewSelection;
            const device = allDevices.find(entry => entry.id === restored.selected);
            if (device) {
                pendingViewSelection = null;
                restoredScroll = restored.scroll;
                selectDevice(device, false, restored.popup && (!accountFilter || device.accountId === accountFilter));
            } else {
                const source = accounts.find(account => typeof restored.selected === 'string' && restored.selected.startsWith('account:' + account.id + ':')) || accounts[0];
                if (restored.selected === null || !source || source.success !== false) {
                    pendingViewSelection = null;
                    restoredScroll = restored.scroll;
                }
            }
        }
        el('woist-devices').scrollTop = Number.isFinite(restoredScroll) ? restoredScroll : pendingViewSelection ? pendingViewSelection.scroll || scroll : scroll;
        saveView();
    }

    global.handleMessage = function (value) {
        const next = parseState(value);
        if (!next) return;
        const envelope = typeof value === 'string' ? JSON.parse(value) : value;
        if (envelope.labels) labels = Object.assign({}, fallback, envelope.labels);
        if (envelope.options) { configureTileHeader(envelope.options); configureDesign(envelope.options.design); configureTiles(envelope.options); }
        state = next;
        if (orderPending) { orderPending = false; global.clearTimeout(orderTimer); }
        if (next.orderError === true) orderFailure();
        if (dragOrder) return;
        if (ready && (outputMode !== 'ipsview' || !refreshPending)) finishRefresh();
        render();
    };

    function start() {
        let bootstrap = {};
        try { bootstrap = JSON.parse(el('woist-bootstrap').textContent); } catch (_) { /* Show empty state. */ }
        const transportConfiguration = bootstrap.mode === 'ipsview'
            ? consumeFragmentToken((bootstrap.options || {}).ipsViewTransport, global) : null;
        outputMode = bootstrap.mode || '';
        if (global.SymconOpenFreeMap && global.SymconOpenFreeMap.createViewState) {
            viewMemory = global.SymconOpenFreeMap.createViewState((bootstrap.options || {}).viewStateKey, global);
        }
        const restoredView = viewMemory && viewMemory.read();
        pendingViewSelection = restoredView;
        accountFilter = restoredView && typeof restoredView.filter === 'string' ? restoredView.filter : '';
        if (restoredView && Number.isFinite(restoredView.scroll)) el('woist-devices').scrollTop = restoredView.scroll;
        configureTileHeader(bootstrap.options || {});
        labels = Object.assign({}, fallback, bootstrap.labels || {});
        state = parseState(bootstrap.state) || state;
        configureDesign((bootstrap.options || {}).design);
        ['title', 'refresh', 'fit'].forEach((key) => { el(key).textContent = text(key); });
        if (el('reset-order')) { el('reset-order').textContent = text('resetOrder'); el('reset-order').addEventListener('click', () => sendOrder({reset: true})); }
        el('devices-title').textContent = text('devices');
        el('map').setAttribute('aria-label', text('title'));
        try {
            if (!global.L) throw new Error('Leaflet unavailable');
            map = global.L.map('map', {trackResize: false}).setView(restoredView ? restoredView.center : [20, 0], restoredView ? restoredView.zoom : 2);
            if (bootstrap.mode === 'ipsview' && global.SymconVisualizationInteraction) {
                map.scrollWheelZoom.disable();
                global.SymconVisualizationInteraction.registerWheelRegion(el('map'), (event) => {
                    if (!event.deltaY) return false;
                    const box = el('map').getBoundingClientRect();
                    const change = Math.sign(event.deltaY) * Math.min(3, Math.max(1, Math.round(Math.abs(event.deltaY) / 120)));
                    map.setZoomAround(global.L.point(event.clientX - box.left, event.clientY - box.top), map.getZoom() - change);
                    return true;
                });
            }
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
        if (el('account-filter')) el('account-filter').addEventListener('change', () => {
            accountFilter = el('account-filter').value;
            render();
        });
        el('woist-devices').addEventListener('scroll', saveView, {passive: true});
        el('fit').addEventListener('click', fit);
        el('refresh').disabled = !canRefresh();
        el('refresh').addEventListener('click', () => {
            if (!canRefresh() || refreshPending) return;
            refreshPending = true;
            el('refresh').disabled = true;
            el('refresh').textContent = text('refreshing');
            if (outputMode !== 'ipsview') refreshTimer = global.setTimeout(finishRefresh, 30000);
            const failed = () => {
                el('account-message').textContent = text('cacheError');
                el('account-message').hidden = false;
                finishRefresh();
            };
            if (outputMode === 'ipsview') {
                ipsViewTransport.refresh().then((success) => { if (success) finishRefresh(); else failed(); }).catch(failed);
                return;
            }
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
            ipsViewTransport = createIPSViewTransport(transportConfiguration, global, global.handleMessage, (key) => {
                el('transport-message').textContent = key ? text(key) : '';
                el('transport-message').hidden = !key;
            });
            ipsViewTransport.start();
            el('refresh').disabled = !canRefresh();
        } else if (typeof global.requestAction === 'function') {
            try { Promise.resolve(global.requestAction('GetState', '')).catch(() => {}); } catch (_) { /* Bootstrap remains usable. */ }
        }
    }

    global.WoIstMap = api;
    if (global.document.readyState === 'loading') global.document.addEventListener('DOMContentLoaded', start, {once: true});
    else start();
}(typeof window === 'undefined' ? globalThis : window));
