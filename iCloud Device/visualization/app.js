(function (global) {
    'use strict';
    const doc = global.document;
    const bootstrap = JSON.parse(doc.getElementById('device-bootstrap').textContent);
    const root = doc.getElementById('device-widget');
    const content = doc.getElementById('device-content');
    const button = doc.getElementById('play-sound');
    let payload = bootstrap;
    let busy = false;
    let actionTimer = null;
    let attempt = 0;
    let revision = 0;
    const tr = key => (payload.translations || {})[key] || key;
    const text = (id, value) => { const node = doc.getElementById(id); node.textContent = value; node.hidden = !value; };
    function render(next) {
        if (!next || !next.state || !next.options) return;
        const top = content.scrollTop;
        const left = content.scrollLeft;
        payload = next;
        const state = payload.state;
        const location = state.location;
        root.style.setProperty('--symc-tile-header-inset', Math.max(0, Math.min(100, Number(payload.options.tileHeaderInset) || 0)) + 'px');
        text('device-name', state.name || tr('Find phone'));
        text('device-model', state.model || '');
        const status = state.available ? tr(state.online ? 'Online' : 'Offline') : '';
        text('device-status', [status, Number.isFinite(state.battery) ? tr('BatteryLevel') + ': ' + Math.round(state.battery) + ' %' : ''].filter(Boolean).join(' · '));
        text('location-label', tr('Last known location'));
        text('location-value', location ? location.address || Number(location.latitude).toFixed(6) + ', ' + Number(location.longitude).toFixed(6) : tr('No known location available.'));
        const timestamp = location && Number(location.timestamp);
        const date = timestamp > 0 ? new Date(timestamp * 1000) : null;
        text('location-time', location ? date && Number.isFinite(date.getTime()) ? tr('LocationTimestamp') + ': ' + date.toLocaleString() : tr('Location time unknown.') : '');
        text('location-accuracy', location && Number.isFinite(location.accuracy) && location.accuracy >= 0 ? tr('Accuracy') + ': ' + Math.round(location.accuracy) + ' m' : '');
        text('location-warning', location && (!state.locationAvailable || state.locationOld || !date || Date.now() / 1000 - timestamp > 900) ? tr('Location is old or currently unavailable.') : '');
        text('device-error', state.error || '');
        text('sound-result', state.result || '');
        button.textContent = tr(busy ? 'Sending sound request...' : 'Play sound');
        button.disabled = busy || !state.canPlaySound || typeof global.requestAction !== 'function';
        content.scrollTop = top; content.scrollLeft = left;
    }
    global.handleMessage = message => {
        try {
            const next = typeof message === 'string' ? JSON.parse(message) : message;
            if (busy && next && next.state && next.state.soundRevision > revision) {
                busy = false; global.clearTimeout(actionTimer);
            }
            render(next);
        } catch (_) { /* Keep the last usable card. */ }
    };
    button.addEventListener('click', () => {
        if (busy || button.disabled) return;
        const current = ++attempt;
        revision = Number(payload.state.soundRevision) || 0;
        busy = true; render(payload);
        const failed = () => {
            if (!busy || current !== attempt) return;
            busy = false; global.clearTimeout(actionTimer);
            payload.state.result = tr('Sound request could not be confirmed. Check the device before trying again.');
            render(payload);
        };
        actionTimer = global.setTimeout(failed, 30000);
        try {
            // The SDK may return void. Only an explicit server acknowledgement releases the control.
            Promise.resolve(global.requestAction('PlaySound', 1)).catch(failed);
        } catch (_) { failed(); }
    });
    render(bootstrap);
    // Refresh only local presentation state after a newly created SDK frame; never replay commands.
    if (typeof global.requestAction === 'function') {
        try { Promise.resolve(global.requestAction('GetState', '')).catch(() => {}); } catch (_) { /* Bootstrap remains visible. */ }
    }
    const ageTimer = global.setInterval(() => render(payload), 30000);
    global.addEventListener('pagehide', () => { global.clearInterval(ageTimer); global.clearTimeout(actionTimer); }, {once: true});
}(window));
