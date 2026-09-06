import assert from 'node:assert/strict';
import { browser } from './cdp.mjs';

const origin = process.argv[2] || 'http://127.0.0.1:8124';
const page = await browser(process.argv[3] || 'http://127.0.0.1:9333');
try {
    const routes = ['/', '/de/unternehmen', '/de/projekte', '/de/geschaeftsbereiche', '/de/karriere', '/de/kontakt', '/de/ueber-uns', '/de/impressum', '/de/datenschutz'];
    for (const width of [375, 768, 1024, 1440, 1920]) {
        await page.resize(width);
        for (const path of routes) {
            await page.navigate(origin + path);
            assert.ok(await page.evaluate("!!document.querySelector('.corporate-header')"));
            assert.equal(await page.evaluate("document.querySelectorAll('h1').length"), 1);
            assert.ok(await page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'));
            assert.ok(!(await page.evaluate("document.body.innerText.includes('Demo preview') || document.body.innerText.includes('Demo-Vorschau') || document.body.innerText.includes('Demo önizleme')")));
        }
    }
    for (const locale of ['de', 'en', 'tr']) {
        for (const section of ['unternehmen', 'projekte', 'geschaeftsbereiche', 'karriere', 'kontakt']) {
            await page.navigate(`${origin}/${locale}/${section}`);
            assert.equal(await page.evaluate('document.documentElement.lang'), locale);
            assert.equal(await page.evaluate("document.querySelectorAll('h1').length"), 1);
        }
    }
    for (const section of ['unternehmen', 'projekte', 'geschaeftsbereiche']) {
        await page.navigate(`${origin}/de/${section}`);
        assert.ok(await page.evaluate("!!document.querySelector('.catalog-empty') || !!document.querySelector('.catalog-grid')"));
        await page.navigate(`${origin}/de/${section}/nicht-vorhanden`);
        assert.ok(await page.evaluate("document.body.innerText.includes('404')"));
    }
    assert.deepEqual(page.errors, []);
    console.log('Live smoke: 9 routes at 5 widths, DE/EN/TR sections, detail 404s, headings, overflow, empty states and console checks passed.');
} finally { await page.close(); }
