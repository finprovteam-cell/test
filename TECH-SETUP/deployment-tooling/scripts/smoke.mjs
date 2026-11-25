import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { readFile, stat } from 'node:fs/promises';
import path from 'node:path';
import { chromium } from '@playwright/test';

const config = JSON.parse(await readFile('site.json', 'utf8'));
const root = path.resolve(config.artifactDir);
const prefix = new URL(config.productionUrl).pathname;
const types = { '.html':'text/html', '.js':'application/javascript', '.css':'text/css', '.svg':'image/svg+xml', '.png':'image/png', '.webp':'image/webp', '.jpg':'image/jpeg', '.woff2':'font/woff2' };
const server = createServer(async (req,res) => {
  try {
    let url = new URL(req.url, 'http://localhost');
    if (!url.pathname.startsWith(prefix)) { res.writeHead(404).end(); return; }
    let relative = decodeURIComponent(url.pathname.slice(prefix.length));
    let file = path.resolve(root, relative || 'index.html');
    if (file !== root && !file.startsWith(root + path.sep)) { res.writeHead(403).end(); return; }
    if ((await stat(file)).isDirectory()) file = path.join(file, 'index.html');
    res.writeHead(200, {'Content-Type':types[path.extname(file)] || 'application/octet-stream'});
    res.end(await readFile(file));
  } catch { res.writeHead(404).end(); }
});
await new Promise(resolve => server.listen(0,'127.0.0.1',resolve));
const origin = `http://127.0.0.1:${server.address().port}`;
const browser = await chromium.launch({headless:true});
try {
  for (const viewport of [{width:390,height:844},{width:1440,height:1000}]) {
    const context = await browser.newContext({viewport});
    // All third-party requests are stubbed. Tests never contact production CRM or analytics.
    await context.route('**/*', async route => {
      const request = route.request();
      const url = new URL(request.url());
      if (url.origin !== origin) {
        await route.fulfill({status:200,contentType:'application/javascript',body:''}); return;
      }
      if (/\/api\//.test(url.pathname)) {
        await route.fulfill({status:200,contentType:'application/json',body:'{"ok":true,"dryRun":false}'}); return;
      }
      await route.continue();
    });
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    await page.goto(origin + prefix, {waitUntil:'networkidle'});
    await page.locator('h1').first().waitFor({state:'visible'});
    assert((await page.locator('body').innerText()).length > 200, 'Page rendered blank');
    assert(await page.evaluate(()=>document.documentElement.scrollWidth <= innerWidth + 3), 'Horizontal overflow');
    const gtm = page.locator(`script[src*="googletagmanager.com/gtm.js?id=${config.gtmId}"]`);
    assert.equal(await gtm.count(),1,'Expected exactly one GTM container');
    assert.equal(await gtm.evaluate(el => el.parentElement.tagName), 'HEAD', 'GTM must load in head');
    assert.equal(await page.evaluate(() => window.dataLayer.filter(item => item.event === 'gtm.js').length), 1, 'GTM must bootstrap once');
    assert.equal(errors.length,0,errors.join('\n'));
    // Static pages use a real form: exercise the happy path against the local mock only.
    const form = page.locator('form.lead-form').first();
    if (await form.count()) {
      for (const input of await form.locator('input').all()) {
        if (!await input.isVisible()) continue;
        const type = await input.getAttribute('type');
        const name = await input.getAttribute('name');
        if (type === 'email') await input.fill('qa@example.invalid');
        else if (type === 'tel' || /phone/i.test(name || '')) await input.fill('9000000000');
        else if (type === 'text' && name !== 'website') await input.fill('Automated local QA');
      }
      for (const select of await form.locator('select').all()) {
        const value = await select.locator('option').evaluateAll(options=>options.find(o=>o.value)?.value);
        if (value) await select.selectOption(value);
      }
      await form.locator('button[type="submit"]').click();
      await page.waitForURL('**/thank-you/',{timeout:10000});
      assert.equal(await page.locator(`script[src*="googletagmanager.com/gtm.js?id=${config.gtmId}"]`).count(),1);
      assert.equal(await page.locator('head #finprov-gtm').count(), 1, 'Thank-you GTM must load in head');
      assert.equal(await page.evaluate(() => window.dataLayer.filter(item => item.event === 'gtm.js').length), 1, 'Thank-you GTM must bootstrap once');
      assert.equal(errors.length,0,errors.join('\n'));
    }
    await context.close();
    console.log(`PASS ${viewport.width}px: rendered, tags, layout and mock-only forms`);
  }
} finally { await browser.close(); await new Promise(resolve=>server.close(resolve)); }
