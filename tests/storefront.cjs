// Run against a disposable local shop only. Requires Playwright + Chromium.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { chromium } = require('playwright');
const base = process.env.ATELIER_TEST_URL || 'http://127.0.0.1:18086';
assert(['127.0.0.1', 'localhost'].includes(new URL(base).hostname), 'Tests create fictional records; use a disposable local shop.');

(async () => {
  const browser = await chromium.launch({ headless: true, ...(process.env.CHROMIUM_PATH ? { executablePath: process.env.CHROMIUM_PATH } : {}) });
  const errors = [], checks = [];
  const folder = process.env.ATELIER_SCREENSHOTS;
  if (folder) fs.mkdirSync(folder, { recursive: true });
  try {
    for (const width of [320, 390, 1440]) {
      const context = await browser.newContext({ viewport: { width, height: 844 } });
      const page = await context.newPage();
      page.setDefaultNavigationTimeout(60000);
      page.on('pageerror', e => errors.push(e.message));
      for (const language of ['en', 'ru']) {
        for (const path of ['/', '/shop/', '/about/', '/journal/', '/contact/', '/faq/', '/product/low-oak-candleholder/']) {
          const response = await page.goto(`${base}${path}?atelier_lang=${language}`, { waitUntil: 'networkidle' });
          assert.equal(response.status(), 200, path);
          assert.equal(await page.locator('html').getAttribute('lang'), language === 'ru' ? 'ru-RU' : 'en-US');
          assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1), false, `${path}: overflow at ${width}`);
          if (path === '/') {
            assert.equal(await page.locator('.hero-photo').getAttribute('fetchpriority'), 'high');
            assert.equal(await page.locator('.hero-photo').getAttribute('loading'), 'eager');
            await page.locator('.category-two').scrollIntoViewIfNeeded();
            await page.waitForFunction(() => [...document.querySelectorAll('.editorial-photo')].every(img => img.complete && img.naturalWidth > 0));
            await page.evaluate(async () => {
              for (let y = 0; y < document.documentElement.scrollHeight; y += 600) { scrollTo(0, y); await new Promise(r => setTimeout(r, 60)); }
              await Promise.all([...document.images].map(img => img.decode().catch(() => {})));
              scrollTo(0, 0);
            });
            if (folder) await page.screenshot({ path: `${folder}/home-${width}-${language}.png`, fullPage: true });
          }
          if (path === '/shop/' && width < 900) {
            await page.locator('.menu-toggle').click();
            assert.equal(await page.locator('.menu-toggle').getAttribute('aria-label'), language === 'ru' ? 'Закрыть меню' : 'Close menu');
            await page.keyboard.press('Escape');
            await page.locator('.filter-toggle').click();
            assert.equal(await page.locator('.shop-sidebar').getAttribute('aria-label'), language === 'ru' ? 'Фильтры товаров' : 'Filter products');
            assert.equal(await page.locator('.shop-main').evaluate(el => el.inert), true);
            await page.keyboard.press('Escape');
            assert.equal(await page.locator('.shop-main').evaluate(el => el.inert), false);
          }
          if (path.startsWith('/product/')) {
            const products = await page.evaluate(() => {
              const found = [];
              function visit(x) {
                if (!x || typeof x !== 'object') return;
                if (x['@type'] === 'Product') found.push(x);
                Object.values(x).forEach(v => Array.isArray(v) ? v.forEach(visit) : visit(v));
              }
              document.querySelectorAll('script[type="application/ld+json"]').forEach(s => visit(JSON.parse(s.textContent)));
              return found;
            });
            assert(products.length, 'Product structured data missing');
            const offer = Array.isArray(products[0].offers) ? products[0].offers[0] : products[0].offers;
            assert.equal(offer.priceCurrency, 'USD');
            assert(Number(offer.price ?? offer.lowPrice) > 0);
          }
          checks.push(`${width} ${language} ${path}`);
        }
      }
      await context.close();
    }
    const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
    page.setDefaultNavigationTimeout(60000);
    page.on('pageerror', e => errors.push(e.message));
    await page.goto(`${base}/shop/?s=zz-no-such-product&post_type=product&atelier_lang=ru`);
    assert.match(await page.locator('link#atelier-commerce-css').getAttribute('href'), /\/assets\/css\/commerce\.css(?:\?|$)/);
    assert((await page.locator('body').getAttribute('class')).split(/\s+/).includes('atelier-commerce'));
    assert.equal(await page.locator('.empty-catalog-action a').innerText(), 'Посмотреть все товары');
    const reset = new URL(await page.locator('.filter-reset').getAttribute('href'), base);
    assert.equal(reset.searchParams.has('s'), false);
    await page.locator('.empty-catalog-action a').click();
    assert(await page.locator('.product-card').count() > 0);
    await page.goto(`${base}/shop/?atelier_lang=en`);
    await page.locator('.add_to_cart_button').first().click();
    await page.waitForFunction(() => Number(document.querySelector('.cart-count').textContent) > 0);
    await page.goto(`${base}/cart/`);
    assert(await page.locator('.cart_item').count() > 0);
    await page.locator('input.qty').first().fill('2');
    await page.locator('[name="update_cart"]').click();
    await page.waitForFunction(() => document.querySelector('input.qty')?.value === '2' && !document.querySelector('.blockUI'));
    await page.goto(`${base}/checkout/`);
    await page.locator('#place_order').click();
    await page.locator('.woocommerce-error').first().waitFor();
    for (const [field, value] of Object.entries({ first_name: 'Fictional', last_name: 'Test', address_1: '123 Test Street', city: 'San Francisco', postcode: '94105', phone: '2025550100', email: 'fictional-checkout@example.invalid' })) {
      await page.locator(`#billing_${field}`).fill(value);
    }
    await page.locator('#billing_country').selectOption('US');
    await page.locator('#billing_state').selectOption('CA');
    await page.waitForFunction(() => !document.querySelector('.blockUI'));
    await page.locator('#place_order').click();
    await page.waitForURL(/order-received/, { timeout: 60000 });
    assert(await page.locator('.woocommerce-order-overview').count() > 0);
    checks.push('empty search/reset, AJAX cart, quantity, invalid checkout, fictional COD order');
    assert.deepEqual(errors, [], 'JavaScript errors');
    console.log(`PASS: ${checks.length} checks, no JavaScript errors.`);
  } finally { await browser.close(); }
})().catch(e => { console.error(e.message); process.exit(1); });
