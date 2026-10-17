// Builds public/images/share/mybooks-share.png from share-image.html (home
// page plan H5). Needs Playwright with a Chromium (not a project dependency):
//   npx playwright@1 install chromium   (once, on your own computer)
//   node scripts/share-image/build.mjs
import { chromium } from 'playwright';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const here = path.dirname(fileURLToPath(import.meta.url));
const browser = await chromium.launch(process.env.CHROMIUM ? { executablePath: process.env.CHROMIUM } : {});
const page = await browser.newPage({ viewport: { width: 1200, height: 630 } });
await page.goto('file://' + path.join(here, 'share-image.html'));
await page.evaluate(() => document.fonts.ready);
await page.screenshot({ path: path.join(here, '../../public/images/share/mybooks-share.png') });
await browser.close();
console.log('Wrote public/images/share/mybooks-share.png');
