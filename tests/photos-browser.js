'use strict';
// Optional: NODE_PATH must provide Playwright and an installed Chromium browser.
const {chromium} = require('playwright');
const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const root = path.join(__dirname, '..');
const native = execFileSync('php', [path.join(__dirname, 'render-photos-fixture.php')], {cwd: root}).toString('utf8');
const ips = execFileSync('php', [path.join(__dirname, 'render-photos-fixture.php'), 'ipsview'], {cwd: root}).toString('utf8');
const ipsDark = execFileSync('php', [path.join(__dirname, 'render-photos-fixture.php'), 'ipsview-dark'], {cwd: root}).toString('utf8');
const png = Buffer.concat([Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aD1sAAAAASUVORK5CYII=', 'base64'), require('node:crypto').randomBytes(1300000)]);
const chunks = png.toString('base64').match(/.{1,131072}/g);
const model = {success: true, source: 'browser-fixture', state: {items: [{id: 'photo-1', name: 'Synthetic first photo.jpg', date: 1700000000}, {id: 'photo-2', name: 'Synthetic second photo.jpg', date: 1700000000}], offset: 0, next: null, total: 2}};
let offline = false, denied = false, images = 0;
const server = http.createServer(async (req, res) => {
    if (req.method === 'GET') { res.writeHead(200, {'Content-Type': 'text/html; charset=utf-8'}); res.end(req.url.startsWith('/ips') ? ips : native); return; }
    let body = ''; for await (const chunk of req) body += chunk;
    assert.equal(req.headers['x-photo-token'], 'f'.repeat(64));
    if (offline || denied) { res.writeHead(denied ? 403 : 503); res.end(); return; }
    const request = JSON.parse(body);
    let value = model;
    if (request.operation === 'image') { images++; value = {success: true, mime: 'image/png', bytes: png.length, transfer: {Token: 'c'.repeat(32), PageCount: chunks.length, ItemCount: chunks.length}}; }
    if (request.operation === 'imagePage') value = {success: true, Token: 'c'.repeat(32), Page: request.page, PageCount: chunks.length, ItemCount: chunks.length, Complete: request.page === chunks.length - 1, Items: [chunks[request.page]]};
    if (request.operation === 'imageClear') value = {success: true};
    const response = JSON.stringify(value);
    assert.ok(Buffer.byteLength(response) < 245760, 'Every HTTP response must respect the Symcon buffer boundary.');
    res.writeHead(200, {'Content-Type': 'application/json'}); res.end(response);
});
(async () => {
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    const origin = 'http://127.0.0.1:' + server.address().port;
    const browser = await chromium.launch({headless: true, ...(process.env.PHOTO_BROWSER ? {executablePath: process.env.PHOTO_BROWSER} : {})});
    try {
        for (const mode of ['native', 'ipsview']) {
            const page = await browser.newPage({viewport: {width: 680, height: 390}});
            const failures = [];
            page.on('pageerror', error => failures.push(error.message));
            await page.goto(origin + (mode === 'ipsview' ? '/ips#token=' + 'f'.repeat(64) : '/tile'));
            await page.waitForFunction(() => document.getElementById('photo').complete && document.getElementById('photo').naturalWidth > 0);
            await page.locator('#pause').click();
            await page.locator('#next').click();
            await page.waitForFunction(() => document.getElementById('photo-caption').textContent.includes('second'));
            const before = await page.locator('#photo').getAttribute('src');
            offline = true;
            await page.waitForFunction(() => !document.getElementById('photo-error').hidden);
            assert.equal(await page.locator('#photo').getAttribute('src'), before);
            offline = false;
            await page.waitForFunction(() => document.getElementById('photo-error').hidden);
            assert.equal(await page.locator('#photo').getAttribute('src'), before);
            assert.equal(await page.locator('#pause').getAttribute('aria-pressed'), 'true');
            await page.locator('#photo-frame').hover(); await page.mouse.wheel(0, -120);
            await page.waitForFunction(() => document.getElementById('photo-caption').textContent.includes('first'));
            await page.setViewportSize({width: 230, height: 240});
            const dimensions = await page.evaluate(() => ({page: document.documentElement.scrollWidth, width: innerWidth, image: document.getElementById('photo-frame').getBoundingClientRect().height}));
            assert.ok(dimensions.page <= dimensions.width && dimensions.image > 20, 'Small widgets must retain a visible frame without horizontal overflow.');
            await page.emulateMedia({colorScheme: 'dark'});
            if (mode === 'ipsview') {
                const darkOptions = JSON.parse(ipsDark.match(/<script type="application\/json" id="photo-bootstrap">(.*?)<\/script>/s)[1]).options;
                await page.evaluate(options => window.handleMessage({options, state: null}), darkOptions);
                const color = await page.locator('body').evaluate(element => getComputedStyle(element).backgroundColor);
                assert.notEqual(color, 'rgb(244, 245, 247)', 'The real central Dark preset must replace the light widget background.');
            }
            fs.mkdirSync(path.join(root, '.preview/photos'), {recursive: true});
            await page.screenshot({path: path.join(root, '.preview/photos/' + mode + '-dark.png')});
            denied = true;
            await page.waitForFunction(() => document.getElementById('photo').hidden);
            denied = false;
            assert.deepEqual(failures, [], 'Rendered widget must not emit browser errors.');
            await page.close();
        }
        assert.ok(images >= 4);
        console.log('Rendered native/IPSView photo widgets: image navigation, pause, offline/recovery, mouse wheel, dark style, narrow layout and revoked access passed.');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; }).finally(() => server.close());
