// Read-only mobile measurement. Run before/after against the same warmed server.
const fs = require('node:fs');
const { chromium } = require('playwright');
const base = process.env.ATELIER_TEST_URL || 'http://127.0.0.1:18086';
(async () => {
  const browser = await chromium.launch({ headless: true, ...(process.env.CHROMIUM_PATH ? { executablePath: process.env.CHROMIUM_PATH } : {}) });
  const results = [];
  try {
    for (let run = 0; run < 3; run++) {
      const context = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 1 });
      const page = await context.newPage();
      const session = await context.newCDPSession(page);
      await session.send('Network.enable');
      await session.send('Network.setCacheDisabled', { cacheDisabled: true });
      await session.send('Network.emulateNetworkConditions', { offline: false, latency: 150, downloadThroughput: 200000, uploadThroughput: 100000 });
      await session.send('Emulation.setCPUThrottlingRate', { rate: 4 });
      await page.addInitScript(() => {
        window.auditLcp = [];
        new PerformanceObserver(list => window.auditLcp.push(...list.getEntries().map(e => ({ time: e.startTime, url: e.url })))).observe({ type: 'largest-contentful-paint', buffered: true });
      });
      const response = await page.goto(base + '/', { waitUntil: 'networkidle', timeout: 120000 });
      await page.waitForFunction(() => window.auditLcp.length > 0, null, { timeout: 30000 });
      await page.waitForTimeout(700);
      const metrics = await page.evaluate(() => ({
        ttfb: performance.getEntriesByType('navigation')[0].responseStart,
        lcp: window.auditLcp.at(-1),
        encodedResourceBytes: performance.getEntriesByType('resource').reduce((sum, r) => sum + r.encodedBodySize, 0),
        overflow: document.documentElement.scrollWidth > innerWidth,
      }));
      results.push({ run, status: response.status(), ...metrics });
      console.log(JSON.stringify(results.at(-1)));
      await context.close();
    }
    if (process.env.ATELIER_PERFORMANCE_OUTPUT) fs.writeFileSync(process.env.ATELIER_PERFORMANCE_OUTPUT, JSON.stringify(results, null, 2));
  } finally { await browser.close(); }
})().catch(e => { console.error(e.message); process.exit(1); });
