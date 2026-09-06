// Build first. Run against a dedicated headless Chrome debug port:
// node tests/browser/navigation.mjs [http://127.0.0.1:9333]
// Uses isolated fixture props and local assets; never submits a live form.
import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { readFile, mkdir, writeFile } from 'node:fs/promises';
import { resolve, extname } from 'node:path';

const root = resolve(import.meta.dirname, '../..');
const manifest = JSON.parse(await readFile(resolve(root, 'public/build/manifest.json')));
const entry = manifest['resources/js/app.jsx'];
const local = 'http://127.0.0.1:8140';
const link = (id, label, href, children = []) => ({ id, label, href, children, external: false, newTab: false });
const page = (url) => {
    const locale = url.startsWith('/en') ? 'en' : 'de';
    return {
        component: 'Errors/Show', url, version: 'navigation-test',
        props: {
            locale, currentYear: 2026, tenantId: 'navigation-test', settings: {},
            languages: [{ code: 'de', label: 'Deutsch' }, { code: 'en', label: 'English' }],
            status: 404, title: 'Navigation test', message: 'Isolated browser fixture.',
            siteShell: {
                name: 'Ömer Dogan Company GmbH', description: 'Unternehmen verbinden. Werte schaffen. Zukunft gestalten.',
                logo: '', footerLogo: '', home: link('home', 'Home', `/${locale}/`),
                contactLink: link('contact', 'Kontakt', `/${locale}/kontakt`),
                header: [link('home', 'Startseite', `/${locale}/`),
                    link('group', 'Unternehmensgruppe', `/${locale}/ueber-uns`, [
                        link('about', 'Über uns', `/${locale}/ueber-uns`),
                        link('areas', 'Geschäftsbereiche', `/${locale}/geschaeftsbereiche`, [link('hotel', 'Hospitality', `/${locale}/hospitality`)]),
                    ]),
                    link('areas', 'Geschäftsbereiche', `/${locale}/geschaeftsbereiche`),
                    link('companies', 'Unternehmen', `/${locale}/unternehmen`),
                    link('projects', 'Projekte', `/${locale}/projekte`),
                    link('career', 'Karriere', `/${locale}/karriere`),
                    { ...link('external', 'Partner', 'https://example.org'), external: true, newTab: true },
                    link('contact', 'Kontakt', `/${locale}/kontakt`)],
                footer: [link('business', 'Geschäftsbereiche', '', [link('hotel', 'Hospitality', `/${locale}/hospitality`)]),
                    link('legal', 'Rechtliches', '', [link('privacy', 'Datenschutz', `/${locale}/datenschutz`), link('imprint', 'Impressum', `/${locale}/impressum`)])],
                phone: '+49 30 12345', phoneHref: 'tel:+493012345', email: 'office@example.com', emailHref: 'mailto:office@example.com',
                address: 'Berlin, Deutschland', social: [],
            },
        },
    };
};
const server = createServer(async (req, res) => {
    try {
        const url = new URL(req.url, local);
        if (url.pathname.startsWith('/build/')) {
            const file = resolve(root, 'public', `.${url.pathname}`);
            assert.ok(file.startsWith(resolve(root, 'public/build') + '\\') || file.startsWith(resolve(root, 'public/build') + '/'));
            res.setHeader('Content-Type', extname(file) === '.css' ? 'text/css' : 'text/javascript');
            res.end(await readFile(file));
        } else if (url.pathname.startsWith('/api/')) {
            res.setHeader('Content-Type', 'application/json');
            res.end('{"data":[],"ok":true}');
        } else if (url.pathname.startsWith('/favicon') || url.pathname.endsWith('.webmanifest')) {
            res.writeHead(204); res.end();
        } else {
            const data = page(req.url);
            if (req.headers['x-inertia']) {
                res.setHeader('X-Inertia', 'true');
                res.setHeader('Content-Type', 'application/json');
                res.end(JSON.stringify(data));
            } else {
                const encoded = JSON.stringify(data).replaceAll('&', '&amp;').replaceAll("'", '&#39;').replaceAll('<', '&lt;');
                res.setHeader('Content-Type', 'text/html; charset=utf-8');
                res.end(`<!doctype html><html lang="de"><head><meta name="viewport" content="width=device-width,initial-scale=1">${(entry.css || []).map(css => `<link rel="stylesheet" href="/build/${css}">`).join('')}</head><body><div id="app" data-page='${encoded}'></div><script type="module" src="/build/${entry.file}"></script></body></html>`);
            }
        }
    } catch (error) { res.writeHead(500); res.end(error.message); }
});
await new Promise(done => server.listen(8140, '127.0.0.1', done));
const debug = process.argv[2] || 'http://127.0.0.1:9333';
let socket;
let target;
try {
    target = await fetch(`${debug}/json/new?about:blank`, { method: 'PUT' }).then(r => r.json());
    socket = new WebSocket(target.webSocketDebuggerUrl);
    await new Promise(done => socket.addEventListener('open', done, { once: true }));
    let serial = 0;
    const pending = new Map();
    const errors = [];
    socket.addEventListener('message', ({ data }) => {
        const event = JSON.parse(data);
        if (event.id) { pending.get(event.id)?.(event); pending.delete(event.id); }
        if (event.method === 'Runtime.exceptionThrown') errors.push(event.params.exceptionDetails.text);
        if (event.method === 'Runtime.consoleAPICalled' && event.params.type === 'error') errors.push(JSON.stringify(event.params.args));
    });
    const cdp = (method, params = {}) => new Promise((done, reject) => {
        const id = ++serial;
        const timeout = setTimeout(() => reject(new Error(`Timeout: ${method}`)), 15000);
        pending.set(id, response => { clearTimeout(timeout); response.error ? reject(response.error) : done(response.result); });
        socket.send(JSON.stringify({ id, method, params }));
    });
    const evaluate = async expression => {
        const result = await cdp('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
        if (result.exceptionDetails) throw new Error(JSON.stringify(result.exceptionDetails));
        return result.result.value;
    };
    const waitFor = async expression => {
        for (let i = 0; i < 100; i++) {
            if (await evaluate(expression)) return;
            await new Promise(done => setTimeout(done, 100));
        }
        throw new Error(`Not ready: ${expression}`);
    };
    await cdp('Runtime.enable');
    await cdp('Page.enable');
    await cdp('Network.enable');
    await cdp('Network.setCookie', { name: 'cookie_consent', value: encodeURIComponent('{"necessary":true,"analytics":false,"marketing":false}'), url: local });
    await cdp('Page.navigate', { url: `${local}/de/` });
    await waitFor("!!document.querySelector('.corporate-header')");
    const output = resolve(root, 'storage/app/stage3-checks');
    await mkdir(output, { recursive: true });
    for (const width of [320, 390, 768, 1024, 1440]) {
        await cdp('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: false });
        assert.equal(await evaluate('document.documentElement.scrollWidth <= innerWidth'), true, `overflow at ${width}`);
        const mobile = width < 960;
        assert.equal(await evaluate("getComputedStyle(document.querySelector('.corporate-header__toggle')).display !== 'none'"), mobile);
        if (mobile) {
            await evaluate("document.querySelector('.corporate-header__toggle').focus(); document.querySelector('.corporate-header__toggle').click()");
            await waitFor("document.querySelector('dialog').open");
            assert.equal(await evaluate('document.body.style.overflow'), 'hidden');
            assert.equal(await evaluate("document.querySelector('dialog').contains(document.activeElement)"), true);
            for (let n = 0; n < 20; n++) {
                await cdp('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9 });
                await cdp('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9 });
                assert.equal(await evaluate("document.querySelector('dialog').contains(document.activeElement) || document.activeElement === document.body"), true);
            }
            if (width === 390) {
                const screenshot = await cdp('Page.captureScreenshot');
                await writeFile(resolve(output, 'mobile-menu.png'), Buffer.from(screenshot.data, 'base64'));
            }
            await cdp('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27 });
            await waitFor("!document.querySelector('dialog').open");
            assert.notEqual(await evaluate('document.body.style.overflow'), 'hidden');
            assert.equal(await evaluate("document.activeElement.classList.contains('corporate-header__toggle')"), true);
        }
        console.log(`PASS viewport ${width}`);
    }
    await evaluate("document.querySelector('.corporate-header__nav summary').click()");
    assert.equal(await evaluate("document.querySelector('.corporate-header__nav details').open"), true);
    assert.equal(await evaluate("document.querySelector('.corporate-header a[href=\"https://example.org\"]').target"), '_blank');
    await evaluate("document.querySelector('.corporate-header__nav summary').focus()");
    await cdp('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27 });
    assert.equal(await evaluate("document.querySelector('.corporate-header__nav details').open"), false);
    await evaluate("document.querySelector('.corporate-header select').value = 'en'; document.querySelector('.corporate-header select').dispatchEvent(new Event('change', {bubbles:true}))");
    await waitFor("location.pathname === '/en/' && document.querySelector('.corporate-header select').value === 'en'");
    await evaluate("document.querySelector('.corporate-header__actions a').click()");
    await waitFor("location.pathname === '/en/kontakt'");
    assert.equal(await evaluate("document.querySelector('.corporate-footer a[href^=\"tel:\"]').dataset.trackAction"), 'call');
    const screenshot = await cdp('Page.captureScreenshot', { captureBeyondViewport: true });
    await writeFile(resolve(output, 'desktop.png'), Buffer.from(screenshot.data, 'base64'));
    await cdp('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-motion', value: 'reduce' }] });
    assert.equal(await evaluate("getComputedStyle(document.documentElement).getPropertyValue('--duration-fast').trim()"), '0ms');
    assert.deepEqual(errors, []);
    console.log('PASS dropdown, Escape, language navigation, CTA, tracking attributes, reduced motion, console');
    // Optional second URL verifies the real Laravel response using its cached panel data.
    if (process.argv[3]) {
        await cdp('Page.navigate', { url: process.argv[3] });
        await waitFor("!!document.querySelector('.corporate-header')");
        for (const width of [390, 768, 1440]) {
            await cdp('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: false });
            assert.equal(await evaluate('document.documentElement.scrollWidth <= innerWidth'), true, `live overflow at ${width}`);
            const shot = await cdp('Page.captureScreenshot');
            await writeFile(resolve(output, `live-${width}.png`), Buffer.from(shot.data, 'base64'));
        }
        assert.deepEqual(errors, []);
        console.log('PASS live Laravel shell, three viewport widths, console');
    }
} finally {
    socket?.close();
    if (target) await fetch(`${debug}/json/close/${target.id}`).catch(() => {});
    server.closeAllConnections();
    server.close();
}
