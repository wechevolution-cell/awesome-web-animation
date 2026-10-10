// Usage: node tests/shots.mjs <baseUrl> <outDir> [paths...]  — full-page screenshots + console error capture
import { createRequire } from 'module';
const require = createRequire(import.meta.url);
const { chromium } = require(process.env.PW_PATH || 'playwright');
const [base, out, ...paths] = process.argv.slice(2);
const fs = require('fs'); fs.mkdirSync(out, { recursive: true });
const browser = await chromium.launch();
const errors = [];
for (const vp of (process.env.VIEWPORTS || '1280x800').split(',')) {
  const [w, h] = vp.split('x').map(Number);
  const ctx = await browser.newContext({ viewport: { width: w, height: h }, reducedMotion: 'reduce' });
  const page = await ctx.newPage();
  page.on('console', (m) => { if (m.type() === 'error') errors.push(`${vp} ${page.url()} ${m.text()}`); });
  page.on('pageerror', (e) => errors.push(`${vp} ${page.url()} ${e.message}`));
  for (const p of paths) {
    await page.goto(base + p, { waitUntil: 'networkidle' });
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1);
    if (overflow) errors.push(`${vp} ${p} HORIZONTAL OVERFLOW ${await page.evaluate(() => document.documentElement.scrollWidth)}`);
    const broken = await page.evaluate(() => [...document.images].filter((i) => i.complete && i.naturalWidth === 0 && i.loading !== 'lazy').map((i) => i.src));
    if (broken.length) errors.push(`${vp} ${p} BROKEN IMG ${broken.join(' ')}`);
    const name = (p.replace(/[^a-z0-9]+/gi, '_') || 'home') + `_${w}.png`;
    await page.screenshot({ path: `${out}/${name}`, fullPage: process.env.FULL !== '0' });
  }
  await ctx.close();
}
await browser.close();
console.log(errors.length ? errors.join('\n') : 'no errors');
