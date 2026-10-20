// Read-only checks against the real WordPress catalog.
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const base = process.env.ATELIER_TEST_URL || 'http://127.0.0.1:18086';
(async () => {
  const browser = await chromium.launch({headless:true});
  try {
    for (const width of [320, 1440]) {
      for (const language of ['en', 'ru']) {
        const page = await browser.newPage({viewport:{width,height:900}});
        await page.goto(`${base}/shop/?atelier_lang=${language}&orderby=price-desc&atelier_material[]=oak&atelier_material[]=stoneware&min_price=12.50&max_price=100&s=oak`);
        assert.equal(await page.locator('.active-filter').count(),5);
        assert.equal(await page.locator('.atelier-filters input[name="min_price"]').getAttribute('step'),'any');
        const chips=await page.locator('.active-filter').evaluateAll(a=>a.map(x=>({href:x.href, text:x.textContent, label:x.getAttribute('aria-label')})));
        for (const chip of chips) {
          const u=new URL(chip.href);
          assert.equal(u.searchParams.get('orderby'),'price-desc');
          assert.equal(u.searchParams.get('atelier_lang'),language);
          assert.equal(u.searchParams.has('paged'),false);
          assert(chip.label && chip.text.includes('×'));
        }
        const oak=chips.find(c=>c.text.includes('Oak')||c.text.includes('Дуб'));
        assert(oak);
        const removed=new URL(oak.href);
        assert.deepEqual(removed.searchParams.getAll('atelier_material[0]').concat(removed.searchParams.getAll('atelier_material[]')),['stoneware']);
        assert.equal(removed.searchParams.get('s'),'oak');
        assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1),false);
        await page.locator('.active-filter').first().click();
        await page.waitForURL(url=>!url.searchParams.has('s'));
        await page.locator('.woocommerce-breadcrumb').waitFor({state:'visible'});
        await page.locator('.active-filter').nth(3).waitFor({state:'visible'});
        assert.equal(new URL(page.url()).searchParams.has('s'),false);
        assert.equal(await page.locator('.active-filter').count(),4);
        const breadcrumb = await page.locator('.woocommerce-breadcrumb').innerText();
        assert(breadcrumb.includes(language === 'ru' ? 'Каталог' : 'Shop'));
        if(width<900) await page.locator('.filter-toggle').click();
        await page.locator('.atelier-filters input[name="min_price"]').fill('12.50');
        await page.locator('.atelier-filters button[type="submit"]').click();
        assert.equal(new URL(page.url()).searchParams.get('orderby'),'price-desc');
        assert.equal(new URL(page.url()).searchParams.get('atelier_lang'),language);
        assert.equal(await page.locator('html').getAttribute('lang'),language==='ru'?'ru-RU':'en-US');
        await page.goto(`${base}/product-category/objects/?atelier_lang=${language}&min_price=10&orderby=price`);
        if(width<900) await page.locator('.filter-toggle').click();
        await page.locator('.atelier-filters button[type="submit"]').click();
        assert.equal(new URL(page.url()).searchParams.get('product_cat'),'objects');
        assert.equal(new URL(page.url()).searchParams.get('orderby'),'price');
        await page.goto(`${base}/shop/?atelier_lang=${language}&atelier_material[0][bad]=oak&min_price[bad]=12`);
        assert.equal(await page.locator('.active-filter').count(),0);
        await page.close();
        console.log('passed:',width,language);
      }
    }
  } finally { await browser.close(); }
})().catch(e=>{console.error(e);process.exit(1);});
