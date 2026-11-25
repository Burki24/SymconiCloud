(function (global) {
    'use strict';

    const styles = new Set(['liberty', 'bright', 'positron', 'fiord', 'dark']);
    const attribution = '<a href="https://openfreemap.org/" target="_blank" rel="noopener">OpenFreeMap</a> © <a href="https://openmaptiles.org/" target="_blank" rel="noopener">OpenMapTiles</a> · <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap contributors</a>';
    const emptyStyle = {version: 8, sources: {}, layers: []};

    function loadRenderer(configuration, environment) {
        return new Promise((resolve, reject) => {
            const origin = configuration.serverURL || environment.SymconHTTPOrigin.resolve(environment);
            let url;
            try {
                if (!origin) throw new Error('Missing HTTP origin');
                url = new URL(configuration.path, origin);
                if (!/^https?:$/.test(url.protocol) || url.username || url.password || url.search || url.hash) throw new Error('Invalid renderer URL');
            } catch (error) { reject(error); return; }
            const script = environment.document.createElement('script');
            const timer = environment.setTimeout(() => reject(new Error('Renderer timeout')), 15000);
            script.src = url.href;
            script.onload = () => {
                environment.clearTimeout(timer);
                try {
                    const bridge = environment.document.createElement('script');
                    bridge.textContent = configuration.bridge;
                    environment.document.head.appendChild(bridge);
                    if (!environment.maplibregl || !environment.L.maplibreGL) throw new Error('Renderer unavailable');
                    resolve();
                } catch (error) { reject(error); }
            };
            script.onerror = () => { environment.clearTimeout(timer); reject(new Error('Renderer load failed')); };
            environment.document.head.appendChild(script);
        });
    }

    function styleURL(configuration, dark) {
        const custom = configuration[dark ? 'darkStyleURL' : 'lightStyleURL'];
        if (custom) {
            try {
                const url = new URL(custom);
                return url.protocol === 'https:' && !url.username && !url.password && !url.hash ? url.href : null;
            } catch (_) { return null; }
        }
        const name = configuration[dark ? 'darkStyle' : 'lightStyle'] || (dark ? 'dark' : 'liberty');
        return styles.has(name) ? 'https://tiles.openfreemap.org/styles/' + name : null;
    }

    function darkMode(configuration, environment) {
        if (configuration.theme === 'dark') return true;
        if (configuration.theme === 'light') return false;
        try {
            const color = environment.getComputedStyle(environment.document.body).backgroundColor;
            const rgb = color.match(/^rgba?\(\s*(\d+(?:\.\d+)?)\D+(\d+(?:\.\d+)?)\D+(\d+(?:\.\d+)?)/);
            if (rgb && color !== 'rgba(0, 0, 0, 0)') {
                return Number(rgb[1]) * 0.2126 + Number(rgb[2]) * 0.7152 + Number(rgb[3]) * 0.0722 < 128;
            }
        } catch (_) { /* Fall back to the browser preference. */ }
        return Boolean(environment.matchMedia && environment.matchMedia('(prefers-color-scheme: dark)').matches);
    }

    function layerGroup(layer) {
        const source = layer['source-layer'] || '';
        const id = layer.id || '';
        if (/water/.test(source)) return 'Water';
        if (/building/.test(source)) return 'Building';
        if (/transportation/.test(source)) return 'Road';
        if (/park|landcover/.test(source) || /park|grass|forest|wood/.test(id)) return 'Park';
        if (layer.type === 'background' || /landuse/.test(source)) return 'Land';
        return null;
    }

    // Work on a copy of the upstream style so disabling custom options restores
    // its original colors and expressions. Never carry camera defaults across.
    function prepareStyle(original, configuration, dark) {
        if (!original || original.version !== 8 || !Array.isArray(original.layers) || !original.sources) throw new Error('Invalid map style');
        const style = JSON.parse(JSON.stringify(original));
        for (const key of ['center', 'zoom', 'bearing', 'pitch']) delete style[key];
        const colorPrefix = dark ? 'dark' : 'light';
        for (const layer of style.layers) {
            const source = layer['source-layer'] || '';
            if (configuration.showLabels === false && layer.type === 'symbol') layer.paint = Object.assign({}, layer.paint, {'text-opacity': 0});
            const hidden = (configuration.showPOI === false && /poi/.test(source))
                || (configuration.showBuildings === false && /building/.test(source))
                || (configuration.showBoundaries === false && /boundary/.test(source));
            if (hidden) layer.layout = Object.assign({}, layer.layout, {visibility: 'none'});
            if (layer.type === 'symbol' && layer.layout && layer.layout['text-field']) {
                const field = layer.layout['text-field'];
                if (['local', 'de', 'en'].includes(configuration.language) && JSON.stringify(field).includes('name')) {
                    layer.layout['text-field'] = configuration.language === 'local'
                        ? ['coalesce', ['get', 'name'], ['get', 'name:latin']]
                        : ['coalesce', ['get', 'name:' + configuration.language], ['get', 'name'], ['get', 'name:latin']];
                }
            }
            if (!configuration.customColors) continue;
            const group = layer.type === 'symbol' ? 'Text' : layerGroup(layer);
            if (!group) continue;
            const color = configuration[colorPrefix + group + 'Color'];
            if (!/^#[a-f0-9]{6}$/i.test(color || '')) continue;
            const property = {background: 'background-color', fill: 'fill-color', line: 'line-color', symbol: 'text-color', 'fill-extrusion': 'fill-extrusion-color'}[layer.type];
            if (!property || (layer.type === 'symbol' && !(layer.layout || {})['text-field'])) continue;
            layer.paint = Object.assign({}, layer.paint, {[property]: color});
            if (layer.type === 'symbol') {
                const halo = configuration[colorPrefix + 'LandColor'];
                if (/^#[a-f0-9]{6}$/i.test(halo || '')) layer.paint['text-halo-color'] = halo;
            }
        }
        return style;
    }

    function createController(map, environment, status) {
        let configuration = null;
        let applied = '';
        let pending = '';
        let generation = 0;
        let request = null;
        let disposed = false;
        let styleFailure = false;
        const cache = new Map();
        const factory = environment.L.maplibreGL || (environment.MaplibreGLLeaflet || {}).maplibreGL;
        if (!factory || !environment.maplibregl) throw new Error('Vector renderer unavailable');
        // Keep one Leaflet layer and one GL map throughout data/design updates.
        const layer = factory({style: emptyStyle, interactive: false, attributionControl: false}).addTo(map);
        const vector = layer.getMaplibreMap();
        if (map.attributionControl) map.attributionControl.addAttribution(attribution);
        vector.on('error', () => { styleFailure = true; status('loadError'); });
        vector.on('idle', () => { if (!styleFailure) status(''); });

        async function update(next) {
            if (disposed || !next) return;
            configuration = next;
            const dark = darkMode(next, environment);
            const url = styleURL(next, dark);
            if (!url) {
                generation++;
                if (request) request.abort();
                request = null; pending = ''; applied = ''; styleFailure = true;
                status('mapUnavailable'); return;
            }
            const key = JSON.stringify([next, dark]);
            if (key === applied || key === pending) return;
            pending = key;
            const sequence = ++generation;
            if (request) request.abort();
            request = new environment.AbortController();
            const controller = request;
            const deadline = environment.setTimeout(() => controller.abort(), 15000);
            try {
                let original = cache.get(url);
                if (!original) {
                    const response = await environment.fetch(url, {credentials: 'omit', mode: 'cors', redirect: 'error', signal: controller.signal});
                    if (!response.ok) throw new Error('Map style unavailable');
                    original = await response.json();
                }
                const prepared = prepareStyle(original, next, dark);
                if (disposed || sequence !== generation) return;
                cache.set(url, original);
                if (cache.size > 6) cache.delete(cache.keys().next().value);
                styleFailure = false;
                vector.setStyle(prepared);
                applied = key;
                const container = map.getContainer && map.getContainer();
                if (container && container.dataset) {
                    container.dataset.mapTheme = dark ? 'dark' : 'light';
                    container.dataset.mapStyle = next[dark ? 'darkStyle' : 'lightStyle'] || (dark ? 'dark' : 'liberty');
                }
                status('');
            } catch (_) {
                if (!disposed && sequence === generation) { applied = ''; styleFailure = true; status('loadError'); }
            } finally {
                environment.clearTimeout(deadline);
                if (sequence === generation) { pending = ''; request = null; }
            }
        }

        const refreshTheme = () => { if (configuration) update(configuration); };
        const media = environment.matchMedia && environment.matchMedia('(prefers-color-scheme: dark)');
        if (media && media.addEventListener) media.addEventListener('change', refreshTheme);
        let observer = null;
        if (environment.MutationObserver && environment.document) {
            observer = new environment.MutationObserver(refreshTheme);
            observer.observe(environment.document.documentElement, {attributes: true});
            observer.observe(environment.document.body, {attributes: true});
            observer.observe(environment.document.head, {childList: true, subtree: true, characterData: true});
        }
        return {
            update,
            destroy() {
                disposed = true;
                generation++;
                if (request) request.abort();
                if (observer) observer.disconnect();
                if (media && media.removeEventListener) media.removeEventListener('change', refreshTheme);
                map.removeLayer(layer);
                if (map.attributionControl) map.attributionControl.removeAttribution(attribution);
            }
        };
    }

    // A native host may replace the complete iframe on reconnect. Keep only
    // validated view data in this tab's storage, never an SDK URL or credential.
    function createViewState(key, environment) {
        let storageKey = null;
        try {
            if (typeof key === 'string' && key.length > 0 && key.length <= 256) {
                const query = new URLSearchParams((environment.location || {}).search || '');
                storageKey = 'burki24:map-view:v1:' + JSON.stringify([key, query.get('visuID') || '', query.get('visuUsername') || '']);
            }
        } catch (_) { /* An unavailable origin must not prevent the map loading. */ }
        const now = () => (environment.Date || Date).now();
        const valid = value => value && Array.isArray(value.center) && value.center.length === 2
            && value.center.every(Number.isFinite) && Math.abs(value.center[0]) <= 90 && Math.abs(value.center[1]) <= 180
            && Number.isFinite(value.zoom) && value.zoom >= 0 && value.zoom <= 30
            && (value.selected === null || (typeof value.selected === 'string' && value.selected.length > 0 && value.selected.length <= 512))
            && typeof value.popup === 'boolean'
            && (value.filter === undefined || (typeof value.filter === 'string' && value.filter.length <= 256))
            && (value.scroll === undefined || (Number.isFinite(value.scroll) && value.scroll >= 0 && value.scroll <= 10000000));
        const cleanView = value => {
            const clean = {center: value.center, zoom: value.zoom, selected: value.selected, popup: value.popup};
            if (value.filter !== undefined) clean.filter = value.filter;
            if (value.scroll !== undefined) clean.scroll = value.scroll;
            return clean;
        };
        return {
            read() {
                try {
                    if (!storageKey) return null;
                    const raw = environment.sessionStorage.getItem(storageKey);
                    if (!raw || raw.length > 4096) return null;
                    const envelope = JSON.parse(raw);
                    if (envelope.version !== 1 || !Number.isFinite(envelope.time) || envelope.time > now()
                        || now() - envelope.time > 4 * 60 * 60 * 1000 || !valid(envelope.value)) return null;
                    return cleanView(envelope.value);
                } catch (_) { return null; }
            },
            write(value) {
                try {
                    if (!storageKey || !valid(value)) return;
                    const clean = cleanView(value);
                    environment.sessionStorage.setItem(storageKey, JSON.stringify({version: 1, time: now(), value: clean}));
                } catch (_) { /* Sandboxing, quotas and disabled storage leave the live map usable. */ }
            }
        };
    }

    const api = {styleURL, darkMode, prepareStyle, createController, loadRenderer, createViewState};
    if (typeof module !== 'undefined' && module.exports) module.exports = api;
    global.SymconOpenFreeMap = api;
}(typeof window === 'undefined' ? globalThis : window));
