(function (global) {
    'use strict';

    const originResolver = typeof module !== 'undefined' && module.exports
        ? require('../../libs/helper/visualization/http-origin.js') : global.SymconHTTPOrigin;

    function pageURL(configuration, location, environment) {
        try {
            if (!configuration || !/^[a-f0-9]{64}$/i.test(configuration.token)) return null;
            const value = configuration.url;
            if (typeof value !== 'string' || !value) return null;
            const absolute = /^https?:\/\//i.test(value);
            if (!absolute && (!value.startsWith('/') || value.startsWith('//'))) return null;
            const origin = originResolver.resolve(environment || {location});
            if (!absolute && !origin) return null;
            const url = absolute ? new URL(value) : new URL(value, origin);
            if (!/^https?:$/.test(url.protocol) || url.username || url.password || url.search || url.hash) return null;
            if (!absolute && url.origin !== origin) return null;
            // A fragment reaches only the map document, never the HTTP server or tile provider.
            url.hash = 'token=' + configuration.token;
            return url.href;
        } catch (_) { return null; }
    }

    function start(environment) {
        const document = environment.document;
        const frame = document.getElementById('woist-page');
        const message = document.getElementById('woist-loader-error');
        let configuration = {};
        try { configuration = JSON.parse(document.getElementById('woist-loader-config').textContent); } catch (_) { /* Show configuration error. */ }
        const url = pageURL(configuration, environment.location || {}, environment);
        if (!url) {
            message.textContent = configuration.error || 'Configure the Symcon server URL for IPSView live updates.';
            message.hidden = false;
            return;
        }
        frame.title = configuration.title || 'iCloud WoIst';
        frame.src = url;
        frame.hidden = false;
    }

    if (typeof module !== 'undefined' && module.exports) module.exports = {pageURL, start};
    if (!global.document) return;
    if (global.document.readyState === 'loading') global.document.addEventListener('DOMContentLoaded', () => start(global), {once: true});
    else start(global);
}(typeof window === 'undefined' ? globalThis : window));
