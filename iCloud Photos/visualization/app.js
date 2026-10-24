(function (global) {
    'use strict';
    // Small pure state seam, also used by the offline behavioral tests.
    function selection() {
        return {
            source: '', page: {items: [], offset: 0, next: null}, id: '', paused: true, order: [],
            apply(page, source, random) {
                if (!page || !Array.isArray(page.items)) return;
                if (source !== this.source) { this.id = ''; this.source = source; this.order = []; }
                this.page = page;
                const ids = [...new Set(page.items.map(item => item.id).filter(id => typeof id === 'string'))];
                this.order = this.order.filter(id => ids.includes(id));
                const fresh = ids.filter(id => !this.order.includes(id));
                if (random) for (let i = fresh.length - 1; i > 0; i--) { const j = Math.floor(Math.random() * (i + 1)); [fresh[i], fresh[j]] = [fresh[j], fresh[i]]; }
                this.order = random ? this.order.concat(fresh) : ids;
                if (!ids.includes(this.id)) this.id = this.order[0] || '';
            },
            move(direction) {
                const index = this.order.indexOf(this.id) + direction;
                if (index < 0 || index >= this.order.length) return false;
                this.id = this.order[index]; return true;
            },
            item() { return this.page.items.find(item => item.id === this.id); }
        };
    }
    if (typeof module !== 'undefined' && module.exports) module.exports = {selection};
    const doc = global.document;
    if (!doc) return;
    const boot = JSON.parse(doc.getElementById('photo-bootstrap').textContent);
    let options = boot.options;
    const state = selection();
    const root = doc.getElementById('photo-widget'), image = doc.getElementById('photo'), caption = doc.getElementById('photo-caption');
    const message = doc.getElementById('photo-error');
    let token = options.token || '', stopped = false, pollTimer, slideTimer, blobURL = '', shown = '', generation = 0, navigating = false, restored = false;
    const controllers = new Set();
    const translate = value => (options.text || {})[value] || value;
    function error(value) { message.textContent = value; message.hidden = !value; }
    const storageKey = () => 'SymconiCloud.photos.' + options.instance + '.' + (boot.mode || 'symcon') + '.' + state.source;
    function remember() {
        try { global.sessionStorage.setItem(storageKey(), JSON.stringify({id: state.id, offset: state.page.offset || 0, paused: state.paused, scroll: caption.scrollTop})); } catch (_) { /* Private webviews may disable storage. */ }
    }
    function clearImage() {
        generation++; shown = ''; image.hidden = true; image.removeAttribute('src');
        if (blobURL) global.URL.revokeObjectURL(blobURL);
        blobURL = ''; caption.textContent = ''; global.clearTimeout(slideTimer);
    }
    function schedule() {
        global.clearTimeout(slideTimer);
        if (!stopped && !state.paused && state.id && options.mode === 'slideshow') slideTimer = global.setTimeout(() => { if (!state.paused) navigate(1); }, options.seconds * 1000);
    }
    function controls() {
        const previous = doc.getElementById('previous'), next = doc.getElementById('next'), pause = doc.getElementById('pause');
        for (const [button, label] of [[previous, 'Previous'], [next, 'Next'], [pause, state.paused ? 'Play' : 'Pause']]) {
            button.title = translate(label); button.setAttribute('aria-label', translate(label));
        }
        previous.disabled = next.disabled = !state.id && state.page.next === null;
        pause.hidden = options.mode !== 'slideshow'; pause.textContent = state.paused ? '▶' : 'Ⅱ'; pause.setAttribute('aria-pressed', String(state.paused));
        doc.getElementById('empty').textContent = translate('No photos loaded. Select an album and refresh photos.');
        doc.getElementById('empty').hidden = !!state.id;
        caption.hidden = options.caption === false;
    }
    if (options.fragmentToken) {
        try {
            const value = new URLSearchParams(global.location.hash.slice(1)).get('token') || '';
            if (/^[a-f0-9]{64}$/i.test(value)) token = value;
            global.history.replaceState(null, '', global.location.pathname + global.location.search);
        } catch (_) { /* Display an access error if authorization is missing. */ }
    }
    async function request(body) {
        const controller = new AbortController(); controllers.add(controller);
        const timeout = global.setTimeout(() => controller.abort(), 35000);
        try {
            const origin = options.origin || global.SymconHTTPOrigin.resolve(global);
            const endpoint = new URL(options.endpoint, origin || undefined);
            if (!/^https?:$/.test(endpoint.protocol) || !token) throw new Error('Unavailable');
            const response = await global.fetch(endpoint.href, {method: 'POST', credentials: 'omit', cache: 'no-store', headers: {'X-Photo-Token': token, 'Content-Type': 'application/json'}, body: JSON.stringify({...body, view: boot.mode === 'ipsview' ? 'ipsview' : 'tile'}), signal: controller.signal});
            if (response.status === 403 || response.status === 404) {
                clearImage(); state.apply({items: [], next: null, offset: 0}, state.source, false); controls();
            }
            if (!response.ok) {
                const failure = new Error('Unavailable');
                try { const detail = await response.json(); if (typeof detail.error === 'string') failure.photoMessage = detail.error.slice(0, 512); } catch (_) { /* A transport error need not contain JSON. */ }
                throw failure;
            }
            return await response.json();
        } finally { global.clearTimeout(timeout); controllers.delete(controller); }
    }
    async function readImage(id, current) {
        const start = await request({operation: 'image', id});
        const transfer = start.transfer;
        if (!start.success || !transfer || !/^[a-f0-9]{32}$/.test(transfer.Token) || !Number.isInteger(transfer.PageCount)
            || transfer.PageCount < 1 || transfer.PageCount > 100 || !Number.isInteger(transfer.ItemCount) || transfer.ItemCount < 1 || transfer.ItemCount > 100
            || !Number.isInteger(start.bytes) || start.bytes < 1 || start.bytes > 8388608 || !/^image\/(jpeg|png|webp)$/.test(start.mime)) throw new Error('Invalid image transfer');
        const chunks = []; let bytes = 0, items = 0;
        try {
            for (let number = 0; number < transfer.PageCount; number++) {
                if (stopped || current !== generation) throw new Error('Image selection changed');
                const page = await request({operation: 'imagePage', id, token: transfer.Token, page: number});
                if (!page.success || page.Token !== transfer.Token || page.Page !== number || page.PageCount !== transfer.PageCount
                    || page.ItemCount !== transfer.ItemCount || !Array.isArray(page.Items) || page.Complete !== (number === transfer.PageCount - 1)) throw new Error('Invalid image page');
                for (const encoded of page.Items) {
                    if (typeof encoded !== 'string' || encoded.length > 131072 || !/^[A-Za-z0-9+/]+={0,2}$/.test(encoded)) throw new Error('Invalid image chunk');
                    const decoded = global.atob(encoded);
                    bytes += decoded.length; items++;
                    if (bytes > start.bytes || items > transfer.ItemCount) throw new Error('Image exceeds declared limits');
                    chunks.push(Uint8Array.from(decoded, value => value.charCodeAt(0)));
                }
            }
            if (bytes !== start.bytes || items !== transfer.ItemCount) throw new Error('Incomplete image transfer');
            return new global.Blob(chunks, {type: start.mime});
        } finally {
            // The central helper also expires abandoned encrypted transfers after five minutes.
            try { await request({operation: 'imageClear', id, token: transfer.Token}); } catch (_) { /* May already be revoked or offline. */ }
        }
    }
    async function show() {
        controls();
        const item = state.item();
        if (!item) { clearImage(); return; }
        const scroll = caption.scrollTop;
        caption.textContent = [item.name, item.date > 0 ? new Date(item.date * 1000).toLocaleDateString() : ''].filter(Boolean).join(' · ');
        caption.scrollTop = scroll;
        image.style.objectFit = options.fit === 'cover' ? 'cover' : 'contain';
        if (shown === state.source + ':' + state.id) return;
        clearImage();
        caption.textContent = [item.name, item.date > 0 ? new Date(item.date * 1000).toLocaleDateString() : ''].filter(Boolean).join(' · ');
        caption.scrollTop = scroll;
        const current = ++generation, key = state.source + ':' + state.id;
        shown = key;
        try {
            const blob = await readImage(item.id, current);
            if (stopped || current !== generation) return;
            if (!/^image\/(jpeg|png|webp)$/.test(blob.type) || blob.size > 8388608) throw new Error('Invalid preview');
            blobURL = global.URL.createObjectURL(blob); image.src = blobURL; image.alt = item.name || ''; image.hidden = false; shown = key;
            error(''); schedule();
        } catch (_) { if (!stopped && current === generation) { shown = ''; error(translate('Preview unavailable. Try the next picture.')); } }
    }
    async function loadPage(offset) {
        const source = state.source;
        const page = await request({operation: 'page', offset});
        if (stopped || source !== state.source || !page.success) throw new Error('Page unavailable');
        state.apply(page, source, options.shuffle);
    }
    async function navigate(direction) {
        if (navigating || stopped) return;
        navigating = true; global.clearTimeout(slideTimer);
        try {
            if (!state.move(direction)) {
                let offset = direction > 0 ? (state.page.next === null ? 0 : state.page.next) : Math.max(0, (state.page.offset || 0) - 20);
                // Empty pages may contain videos only. Bound work per user action.
                for (let attempts = 0; attempts < 5; attempts++) {
                    await loadPage(offset);
                    if (state.order.length) { state.id = direction > 0 ? state.order[0] : state.order[state.order.length - 1]; break; }
                    if (state.page.next === null) break;
                    offset = state.page.next;
                }
            }
            remember(); await show();
        } catch (_) { error(translate('Photos unavailable. Retrying...')); }
        finally { navigating = false; }
    }
    async function render(payload) {
        if (!payload) return;
        if (payload.options) {
            options = payload.options;
            if (!options.fragmentToken) token = options.token || '';
            root.style.setProperty('--symc-tile-header-inset', Math.max(0, Math.min(100, Number(options.tileHeaderInset) || 0)) + 'px');
            doc.getElementById('photo-theme').textContent = options.themeCSS || '';
        }
        if (payload.revoked || (payload.runtime && payload.runtime.revoked)) { clearImage(); state.apply({items: [], next: null}, state.source, false); controls(); return; }
        const source = payload.source || (payload.runtime || {}).source;
        if (!source || !payload.state || !Array.isArray(payload.state.items)) { controls(); return; }
        const changed = source !== state.source;
        if (navigating && !changed) return;
        if (changed) { clearImage(); restored = false; state.paused = options.mode !== 'slideshow'; }
        if (changed || !state.page.offset) state.apply(payload.state, source, options.shuffle);
        if (!restored) {
            restored = true;
            try {
                const saved = JSON.parse(global.sessionStorage.getItem(storageKey()) || 'null');
                if (saved && typeof saved.paused === 'boolean') {
                    state.paused = saved.paused;
                    if (Number.isInteger(saved.offset) && saved.offset > 0) await loadPage(saved.offset);
                    if (state.order.includes(saved.id)) state.id = saved.id;
                    caption.scrollTop = saved.scroll || 0;
                }
            } catch (_) { /* Cached metadata remains usable if restoring a page fails. */ }
        }
        if (options.mode !== 'slideshow') state.paused = true;
        await show(); if (payload.error) error(payload.error);
    }
    doc.getElementById('previous').addEventListener('click', () => navigate(-1));
    doc.getElementById('next').addEventListener('click', () => navigate(1));
    doc.getElementById('pause').addEventListener('click', () => { state.paused = !state.paused; controls(); remember(); schedule(); });
    caption.addEventListener('scroll', remember, {passive: true});
    image.addEventListener('error', () => { clearImage(); error(translate('Preview unavailable. Try the next picture.')); });
    // Central native-webview wheel routing also handles forwarded zero-coordinate events.
    if (global.SymconVisualizationInteraction) global.SymconVisualizationInteraction.registerWheelRegion(doc.getElementById('photo-frame'), event => {
        const delta = global.SymconVisualizationInteraction.delta(event, root);
        if (!delta.deltaY && !delta.deltaX) return false;
        navigate((delta.deltaY || delta.deltaX) > 0 ? 1 : -1); return true;
    });
    global.handleMessage = value => { try { render(typeof value === 'string' ? JSON.parse(value) : value).catch(() => {}); } catch (_) { /* Keep current picture. */ } };
    async function poll() {
        if (stopped) return;
        try { const payload = await request({operation: 'model'}); error(''); await render(payload); }
        catch (failure) { if (!stopped) error(failure.photoMessage || translate('Photos unavailable. Retrying...')); }
        finally { if (!stopped) pollTimer = global.setTimeout(poll, 5000); }
    }
    global.addEventListener('pagehide', () => { remember(); stopped = true; global.clearTimeout(pollTimer); global.clearTimeout(slideTimer); controllers.forEach(controller => controller.abort()); clearImage(); });
    global.addEventListener('pageshow', event => { if (event.persisted && stopped) { stopped = false; poll(); } });
    render(boot).catch(() => {}); poll();
}(typeof window === 'undefined' ? globalThis : window));
