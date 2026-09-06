import { mkdir } from 'node:fs/promises';
import { resolve } from 'node:path';
import { browser } from './cdp.mjs';
const origin = process.argv[2] || 'http://127.0.0.1:8124';
const page = await browser(process.argv[3] || 'http://127.0.0.1:9333');
const output = resolve('storage/app/final-audit');
await mkdir(output, { recursive: true });
try {
  for (const width of [375, 768, 1024, 1440, 1920]) {
    await page.resize(width);
    for (const [name, path] of [['home','/'],['contact','/de/kontakt']]) {
      await page.navigate(origin + path);
      await page.screenshot(resolve(output, `${name}-${width}.png`));
    }
  }
  console.log(`Captured 10 screenshots in ${output}`);
} finally { await page.close(); }
