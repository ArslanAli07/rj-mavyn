const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const base = process.env.THEME_TEST_URL || 'http://127.0.0.1:8193';
const output = '/tmp/rjleather-browser';
const state = JSON.parse(fs.readFileSync(output+'/state.json','utf8'));
const productPath = state.product;
const categoryPath = state.category;
(async () => {
    const browser = await chromium.launch({headless:true,executablePath:process.env.CHROMIUM_PATH || undefined,args:['--no-sandbox']});
    const context = await browser.newContext({viewport:{width:1440,height:1000}});
    const page = await context.newPage();
    const errors = [], failedAssets = [], checks = [];
    page.on('pageerror',error=>errors.push(error.message));
    page.on('response',response=>{if(response.url().includes('/theme-assets/') && response.status() >= 400) failedAssets.push(response.url());});
    const visit = async path => {const response=await page.goto(base+path,{waitUntil:'networkidle'});assert.equal(response.status(),200,path);};
    const fits = async label => assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth > innerWidth+1),false,label+' overflows');
    try {
        await visit('/');
        assert.equal(await page.title(),'RJ Motorbike');
        assert.equal(await page.locator('.rj-hero__title').count(),1);
        assert.equal(await page.locator('.rj-hero__dot').count(),3);
        assert.equal(await page.locator('.rj-tile').count(),5);
        assert.ok(await page.locator('.rj-card').count()>=16);
        assert.equal(await page.locator('.rj-band .rj-card').count()>0,true);
        assert.equal(await page.locator('.rj-showcase__card').count(),3);
        assert.equal(await page.locator('.rj-footer__columns > div').count(),4);
        assert.equal(await page.locator('.rj-subscribe__form input[name=email]').count(),1);
        // The hero pager moves between the administrator's slides.
        await page.locator('.rj-hero__dot').nth(1).click();
        assert.equal(await page.locator('.rj-hero__slide').nth(1).getAttribute('class').then(c=>c.includes('is-active')),true);
        await page.screenshot({path:output+'/home-desktop.png',fullPage:true});
        checks.push('landing page design: hero pager, category tiles, product rows, sellers band, showcase and newsletter');

        // Navigation: group dropdowns open on hover and reach real category pages.
        assert.equal(await page.locator('.rj-nav__group').count(),5);
        assert.equal(await page.locator('.rj-nav__caret').count(),5);
        await page.locator('.rj-nav__group').first().hover();
        await page.waitForTimeout(300);
        assert.equal(await page.locator('.rj-nav__panel').first().isVisible(),true);
        const navHref=await page.locator('.rj-nav__panel a').first().getAttribute('href');
        assert.ok(navHref.includes('/category/'));
        await visit(navHref.replace(base,''));
        await page.locator('.rj-nav__caret').first().click();
        assert.equal(await page.locator('.rj-nav__caret').first().getAttribute('aria-expanded'),'true');
        await page.keyboard.press('Escape');
        assert.equal(await page.locator('.rj-nav__caret').first().getAttribute('aria-expanded'),'false');
        checks.push('navigation dropdowns: hover, caret toggle, escape and real category pages');

        await visit('/shop');
        assert.equal(await page.locator('.rj-catalog__grid .rj-card').count(),12);
        await page.locator('#product-sort').selectOption('price-high');
        const prices=await page.locator('.rj-catalog__grid .rj-card').evaluateAll(cards=>cards.map(card=>Number(card.dataset.price)));
        assert.deepEqual(prices,[...prices].sort((a,b)=>b-a));
        await page.locator('[data-dialog=filter-dialog]').click();
        await page.locator('#filter-dialog input[name=min_price]').fill('170');
        await page.locator('#filter-dialog input[name=max_price]').fill('200');
        await Promise.all([page.waitForURL('**/shop?**'),page.locator('#filter-dialog button[type=submit]').click()]);
        assert.equal(new URL(page.url()).searchParams.get('min_price'),'170');
        assert.equal(new URL(page.url()).searchParams.get('max_price'),'200');
        // SQLite binds these raw numeric comparisons differently from MySQL.
        // Actual price-filter results are checked against MySQL in verify-install.php.
        await visit('/shop?category=for-men&in_stock=1');
        assert.equal(await page.locator('.rj-catalog__grid .rj-card').count(),6);
        await visit('/shop?page=2');assert.equal(await page.locator('.rj-catalog__grid .rj-card').count(),12);
        await visit(categoryPath);
        await visit('/search?q=Oiled%20Field%20Jacket%20For%20Men');assert.equal(await page.locator('.rj-card').count(),1);
        await visit('/search?q=does-not-exist');assert.ok(await page.locator('.rj-empty').isVisible());
        checks.push('server filters, category/search, pagination, empty results and sorting');

        await visit(productPath);
        const add=page.locator('.rj-product-form button[type=submit]');
        assert.equal(await add.isDisabled(),true,'required option must be selected');
        await page.locator('.rj-size-grid button').filter({hasText:'Premium'}).click();
        await page.locator('.rj-option-grid button').filter({hasText:'Polished'}).click();
        assert.equal(await add.isDisabled(),false);
        assert.ok((await page.locator('.rj-product__price').innerText()).includes('18,025'));
        await page.locator('#special-request').fill('Browser configuration check');
        await page.locator('[data-gallery-index="0"]').click();
        assert.ok(await page.locator('#gallery-dialog').isVisible());
        await page.locator('#gallery-dialog').focus();await page.keyboard.press('ArrowRight');await page.waitForTimeout(100);assert.equal(await page.locator('.lightbox-position').innerText(),'2 / 2');
        await page.keyboard.press('Escape');await page.locator('#gallery-dialog').waitFor({state:'hidden'});
        await page.locator('.purchase-favorite').click();
        assert.equal(await page.locator('.purchase-favorite').getAttribute('aria-pressed'),'true');
        await page.reload({waitUntil:'networkidle'});
        assert.equal(await page.locator('.purchase-favorite').getAttribute('aria-pressed'),'true');
        await page.locator('.rj-size-grid button').filter({hasText:'Premium'}).click();
        await page.locator('.rj-option-grid button').filter({hasText:'Polished'}).click();
        await page.locator('#special-request').fill('Browser configuration check');
        await page.screenshot({path:output+'/product-desktop.png',fullPage:true});
        await add.click();
        await page.locator('#bag-dialog[open]').waitFor();
        assert.ok((await page.locator('[data-bag-content]').innerText()).includes('18,025'));
        assert.ok((await page.locator('[data-bag-content]').innerText()).includes('Polished'));
        await page.keyboard.press('Escape');await page.locator('#bag-dialog').waitFor({state:'hidden'});
        await visit('/cart');
        assert.ok((await page.locator('#main-content').innerText()).includes('18,025'));
        assert.ok((await page.locator('#main-content').innerText()).includes('Premium'));
        await visit('/checkout');
        assert.equal(await page.locator('#checkout-form').count(),1);
        assert.equal(await page.locator('#review-order').count(),1);
        assert.ok(await page.locator('#confirm-order').isDisabled());
        assert.ok((await page.locator('#main-content').innerText()).includes('No payment method supports'));
        checks.push('required configuration, priced variant/options, gallery, AJAX bag and checkout notices');

        await visit('/wishlist?ids='+state.wishlist_id);assert.equal(await page.locator('.rj-card').count(),1);
        await visit('/login');
        await page.locator('#main-content input[name=email]').fill('customer@example.test');
        await page.locator('#main-content input[name=password]').fill('fixture-password-12345');
        await page.waitForTimeout(3300); // Respect the host's honeypot minimum form time.
        await Promise.all([page.waitForURL('**/account'),page.locator('#main-content button[type=submit]').click()]);
        await visit(productPath);
        const favourite = page.locator('.purchase-favorite');
        if (await favourite.getAttribute('aria-pressed') !== 'true') await favourite.click();
        await visit('/wishlist');assert.equal(await page.locator('.rj-card').count(),1);
        checks.push('guest wishlist persistence and authenticated wishlist API');

        for (const path of ['/about','/contact','/gallery','/faq','/privacy-policy','/terms','/refund-policy','/shipping-policy','/account','/account/profile','/account/addresses','/account/password']) await visit(path);
        await visit('/');await page.locator('[data-dialog=newsletter-dialog]').click();
        await page.locator('#newsletter-email').fill('newsletter-browser@example.test');
        await page.waitForTimeout(3300); // Preserve the newsletter honeypot timing as well.
        await page.locator('#newsletter-dialog button[type=submit]').click();
        await page.waitForFunction(()=>document.querySelector('#newsletter-dialog [data-form-status]').textContent.includes('Thank you'));
        await page.keyboard.press('Escape');
        checks.push('inherited account/content pages and real newsletter submission');

        await page.setViewportSize({width:390,height:844});
        for (const [path,name] of [['/','home'],['/shop','catalog'],[productPath,'product'],['/cart','cart'],['/checkout','checkout']]) {
            await visit(path);await fits('mobile '+name);
            await page.screenshot({path:output+'/'+name+'-mobile.png',fullPage:true});
        }
        await visit('/');
        await page.locator('.mobile-menu-toggle').click();
        assert.equal(await page.locator('.mobile-menu-toggle').getAttribute('aria-expanded'),'true');
        assert.ok(await page.locator('#mobile-menu .rj-mobile-menu__child').count()>0,'mobile menu lists subcategories');
        await page.keyboard.press('Escape');await page.locator('#mobile-menu').waitFor({state:'hidden'});
        await page.emulateMedia({reducedMotion:'reduce'});await page.locator('.bag-toggle').click();
        assert.ok(await page.locator('#bag-dialog').isVisible());await page.keyboard.press('Escape');
        checks.push('390px mobile layouts, keyboard menu/dialogs and reduced motion');

        await page.setViewportSize({width:1440,height:1000});
        await visit('/admin/login');
        assert.equal(await page.locator('[data-storefront-theme]').count(),0);
        await page.locator('input[name=email]').fill('fixture@example.test');await page.locator('input[name=password]').fill('fixture-password-12345');
        await Promise.all([page.waitForURL('**/admin/workspace'),page.getByRole('button',{name:'Sign in to admin'}).click()]);
        await visit('/admin/themes');
        assert.ok((await page.locator('body').innerText()).includes('RJ Motorbike'));
        checks.push('unchanged admin login/workspace and theme registration');
        assert.deepEqual(errors,[]);assert.deepEqual(failedAssets,[]);
        const result={result:'passed',checks,javascript_errors:errors,failed_theme_assets:failedAssets};
        fs.writeFileSync(output+'/verification.json',JSON.stringify(result,null,2));console.log(JSON.stringify(result));
    } finally {await browser.close();}
})().catch(error=>{console.error(error.stack);process.exit(1);});
