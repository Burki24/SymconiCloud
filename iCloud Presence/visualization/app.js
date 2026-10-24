(function (global) {
    'use strict';
    const doc = global.document;
    if (!doc) return;
    const bootstrap = JSON.parse(doc.getElementById('widget-bootstrap').textContent);
    const root = doc.getElementById('status-widget');
    const list = doc.getElementById('members');
    const rows = new Map();
    const variantSwitch = doc.getElementById('variant-switch');
    const variantButtons = [doc.getElementById('variant-widget'), doc.getElementById('variant-compact')];
    const canSwitch = bootstrap.options.variantSwitch === true;
    const variantValid = value => value === 'Widget' || value === 'WidgetCompact';
    const storageKey = typeof bootstrap.options.variantStorageKey === 'string' && /^[a-zA-Z0-9_-]{1,128}$/.test(bootstrap.options.variantStorageKey)
        ? 'SymconiCloud.presence.variant:' + bootstrap.options.variantStorageKey : '';
    let chosenVariant = '';
    let currentPayload = bootstrap;
    let activeVariant = '';
    const scrollPositions = new Map();
    if (canSwitch && storageKey) {
        try {
            const saved = global.sessionStorage.getItem(storageKey);
            if (variantValid(saved)) chosenVariant = saved;
        } catch (_) { /* Browser storage is optional; switching remains available. */ }
    }
    let selected = '';
    let stopped = false;
    let timer = null;
    let activeRequest = null;
    let generation = 0;
    const text = (id, value) => { doc.getElementById(id).textContent = typeof value === 'string' ? value : ''; };
    const tone = value => ['positive', 'neutral'].includes(value) ? value : 'unknown';
    function render(payload, restorePosition) {
        if (!payload || !payload.state || !Array.isArray(payload.state.members) || !payload.options) return;
        currentPayload = payload;
        const state = payload.state;
        const options = payload.options;
        const design = options.design || {};
        activeVariant = canSwitch && chosenVariant ? chosenVariant : options.variant === 'WidgetCompact' ? 'WidgetCompact' : 'Widget';
        const compact = activeVariant === 'WidgetCompact';
        root.dataset.variant = compact ? 'compact' : 'normal';
        variantSwitch.hidden = !canSwitch;
        variantSwitch.setAttribute('aria-label', bootstrap.options.variantLabel || 'Widget variant');
        variantButtons.forEach((button, index) => button.setAttribute('aria-pressed', String(activeVariant === (index ? 'WidgetCompact' : 'Widget'))));
        const scrollTop = restorePosition ? restorePosition.top : list.scrollTop;
        const scrollLeft = restorePosition ? restorePosition.left : list.scrollLeft;
        root.style.setProperty('--symc-tile-header-inset', Math.max(0, Math.min(100, Number(options.tileHeaderInset) || 0)) + 'px');
        for (const [key, css] of [['pictureSize', 'picture-size'], ['radius', 'radius'], ['spacing', 'spacing']]) {
            const value = compact && ['pictureSize', 'spacing'].includes(key) ? Math.min(key === 'pictureSize' ? 36 : 8, design[key]) : design[key];
            if (Number.isFinite(value)) root.style.setProperty('--widget-' + css, value + 'px');
        }
        if (/^\d+(?:\.\d+)?px$/.test(design.fontSize)) doc.documentElement.style.fontSize = design.fontSize;
        if (typeof options.themeCSS === 'string') doc.getElementById('widget-theme').textContent = options.themeCSS;
        text('summary-label', state.title);
        text('summary-value', state.summary && state.summary.label);
        text('summary-detail', state.summary && state.summary.detail);
        doc.getElementById('summary-detail').hidden = compact;
        doc.getElementById('summary').dataset.tone = tone(state.summary && state.summary.tone);
        const members = Array.isArray(state.members) ? state.members : [];
        const keep = new Set();
        let previous = null;
        for (const member of members) {
            if (!member || typeof member.id !== 'string' || keep.has(member.id)) continue;
            keep.add(member.id);
            let row = rows.get(member.id);
            if (!row) {
                row = doc.createElement('button');
                row.type = 'button'; row.className = 'member'; row.dataset.id = member.id;
                row.innerHTML = '<span class="avatar"></span><span class="copy"><strong class="name"></strong><span class="status"></span><span class="detail"></span><span class="reason"></span></span>';
                row.addEventListener('click', () => {
                    selected = selected === member.id ? '' : member.id;
                    for (const [id, element] of rows) element.setAttribute('aria-pressed', String(id === selected));
                });
                rows.set(member.id, row);
            }
            if (row.previousElementSibling !== previous || row.parentElement !== list) list.insertBefore(row, previous ? previous.nextElementSibling : list.firstElementChild);
            previous = row;
            row.dataset.tone = tone(member.tone);
            row.setAttribute('aria-pressed', String(selected === member.id));
            for (const key of ['name', 'status', 'detail', 'reason']) {
                const value = key === 'status' && compact ? member.zone : compact && ['detail', 'reason'].includes(key) ? '' : member[key];
                row.querySelector('.' + key).textContent = typeof value === 'string' ? value : '';
            }
            row.querySelector('.detail').hidden = compact || design.details === false;
            row.querySelector('.reason').hidden = compact || design.details === false || !member.reason;
            const avatar = row.querySelector('.avatar');
            avatar.hidden = design.photos === false;
            const image = typeof member.image === 'string' && /^data:image\/(png|jpeg|gif|webp);base64,[A-Za-z0-9+/=]+$/.test(member.image) ? member.image : '';
            if (avatar.dataset.image !== image) {
                avatar.replaceChildren();
                if (image) {
                    const img = doc.createElement('img'); img.src = image; img.alt = ''; img.addEventListener('error', () => { avatar.textContent = (member.name || '?').slice(0, 2); }, {once: true}); avatar.appendChild(img);
                }
                avatar.dataset.image = image;
            }
            if (!image) avatar.textContent = (member.name || '?').trim().split(/\s+/).map(word => word[0]).slice(0, 2).join('').toUpperCase();
            row.style.gridTemplateColumns = design.photos === false ? 'minmax(0, 1fr)' : '';
        }
        for (const [id, row] of rows) if (!keep.has(id)) { row.remove(); rows.delete(id); }
        if (!keep.has(selected)) selected = '';
        text('empty', options.empty);
        doc.getElementById('empty').hidden = keep.size > 0;
        list.scrollTop = scrollTop; list.scrollLeft = scrollLeft;
    }
    variantButtons.forEach((button, index) => button.addEventListener('click', () => {
        if (!canSwitch) return;
        const next = index ? 'WidgetCompact' : 'Widget';
        if (next === activeVariant) return;
        const position = {top: list.scrollTop, left: list.scrollLeft};
        scrollPositions.set(activeVariant, position);
        chosenVariant = next;
        if (storageKey) {
            try { global.sessionStorage.setItem(storageKey, next); } catch (_) { /* Continue without persistence. */ }
        }
        render(currentPayload, scrollPositions.get(next) || position);
    }));
    global.handleMessage = value => {
        try { render(typeof value === 'string' ? JSON.parse(value) : value); } catch (_) { /* Keep last usable state. */ }
    };
    render(bootstrap);
    const transport = bootstrap.options.transport;
    if (transport && transport.fragmentToken === true) {
        transport.token = '';
        try {
            const fragment = new URLSearchParams(global.location.hash.slice(1));
            const token = fragment.get('token') || '';
            if (/^[a-f0-9]{64}$/i.test(token)) transport.token = token;
            // Keep the secret in memory and the authorization header, not in URLs/referrers/history.
            global.history.replaceState(null, '', global.location.pathname + global.location.search);
        } catch (_) { /* Missing/invalid access remains a visible transport error. */ }
    }
    async function poll() {
        if (stopped) return;
        const message = doc.getElementById('connection');
        const currentGeneration = generation;
        const controller = new AbortController();
        activeRequest = controller;
        const timeout = global.setTimeout(() => controller.abort(), 8000);
        try {
            if (!transport.url || !transport.token) throw new Error('Transport unavailable');
            const origin = global.SymconHTTPOrigin.resolve(global);
            const endpoint = new URL(transport.url, origin || undefined);
            if (!/^https?:$/.test(endpoint.protocol)) throw new Error('Invalid origin');
            const response = await global.fetch(endpoint.href, {method: 'POST', headers: {'X-Status-Widget-Token': transport.token}, cache: 'no-store', credentials: 'omit', signal: controller.signal});
            if (!response.ok) throw new Error('Transport unavailable');
            const payload = await response.json();
            if (!payload || !Array.isArray(payload.state && payload.state.members) || !payload.options) throw new Error('Invalid state');
            if (!stopped && currentGeneration === generation) { render(payload); message.hidden = true; }
        } catch (_) {
            if (!stopped && currentGeneration === generation) { message.textContent = bootstrap.options.connectionError; message.hidden = false; }
        } finally {
            global.clearTimeout(timeout); if (activeRequest === controller) activeRequest = null;
            if (!stopped && currentGeneration === generation) timer = global.setTimeout(poll, 5000);
        }
    }
    global.addEventListener('pagehide', () => { stopped = true; generation++; global.clearTimeout(timer); if (activeRequest) activeRequest.abort(); });
    global.addEventListener('pageshow', event => { if (event.persisted && stopped) { stopped = false; if (transport) poll(); } });
    if (transport) poll();
}(window));
