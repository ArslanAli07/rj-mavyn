
/* --- inline-2 --- */
// Mobile menu toggle
  (function() {
    const btn      = document.getElementById('mobileMenuBtn');
    const menu     = document.getElementById('mobileMenu');
    const drawer   = document.getElementById('mobileMenuDrawer');
    const backdrop = document.getElementById('mobileMenuBackdrop');
    const closeBtn = document.getElementById('mobileMenuClose');

    function openMenu() {
      menu.classList.remove('pointer-events-none');
      backdrop.classList.remove('opacity-0');
      drawer.classList.remove('translate-x-full');
    }
    function closeMenu() {
      backdrop.classList.add('opacity-0');
      drawer.classList.add('translate-x-full');
      setTimeout(() => menu.classList.add('pointer-events-none'), 300);
    }

    if (btn)      btn.addEventListener('click', openMenu);
    if (closeBtn) closeBtn.addEventListener('click', closeMenu);
    if (backdrop) backdrop.addEventListener('click', closeMenu);

    // Close the mobile nav drawer before the search drawer opens on top of it.
    const mobileSearchTrigger = document.getElementById('mobileSearchTrigger');
    if (mobileSearchTrigger) mobileSearchTrigger.addEventListener('click', closeMenu);
  })();

/* --- inline-3 --- */
// Minimal focus trap for the cart/search drawers (role="dialog"): keeps Tab/Shift+Tab
// cycling inside the open panel and restores focus to whatever triggered it on close.
window.HappyTeaFocusTrap = (function () {
  const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), textarea:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])';

  let activePanel = null;
  let previouslyFocused = null;

  function focusableEls() {
    if (!activePanel) return [];
    return Array.from(activePanel.querySelectorAll(FOCUSABLE)).filter((el) => el.offsetParent !== null);
  }

  function handleKeydown(event) {
    if (event.key !== 'Tab' || !activePanel) return;
    const els = focusableEls();
    if (els.length === 0) return;

    const first = els[0];
    const last = els[els.length - 1];

    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  }

  function trap(panel) {
    if (!panel) return;
    activePanel = panel;
    previouslyFocused = document.activeElement;
    document.addEventListener('keydown', handleKeydown, true);
    window.setTimeout(() => focusableEls()[0]?.focus(), 50);
  }

  function release() {
    document.removeEventListener('keydown', handleKeydown, true);
    if (previouslyFocused instanceof HTMLElement) previouslyFocused.focus();
    activePanel = null;
    previouslyFocused = null;
  }

  return { trap, release };
})();

/* --- inline-4 --- */
window.HappyTeaCart = (function () {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content
    || document.querySelector('input[name="_token"]')?.value
    || 'EWovunn336GFkcyzHxsTJNygMEfu4fHTAN7q0r2Y';
  const isCartPage = false;
  const defaultProductImage = "http:\/\/localhost\/product\/latte.jpg";
  const routes = {
    store: "http:\/\/localhost\/cart",
    summary: "http:\/\/localhost\/cart\/summary",
    update: (key) => "http:\/\/localhost\/cart".replace(/\/$/, '') + '/' + encodeURIComponent(key),
  };

  const drawer = document.getElementById('cartDrawer');
  const linesEl = document.getElementById('cartDrawerLines');
  const emptyEl = document.getElementById('cartDrawerEmpty');
  const footerEl = document.getElementById('cartDrawerFooter');
  const subtotalEl = document.getElementById('cartDrawerSubtotal');
  const shippingEl = document.getElementById('cartDrawerShipping');
  const totalEl = document.getElementById('cartDrawerTotal');

  function headers(json = true) {
    const h = {
      'X-CSRF-TOKEN': csrf,
      'X-Requested-With': 'XMLHttpRequest',
      'Accept': 'application/json',
    };
    if (json) h['Content-Type'] = 'application/json';
    return h;
  }

  function formHeaders() {
    return {
      'X-CSRF-TOKEN': csrf,
      'X-Requested-With': 'XMLHttpRequest',
      'Accept': 'application/json',
      'Content-Type': 'application/x-www-form-urlencoded',
    };
  }

  function updateBadges(count) {
    document.querySelectorAll('[data-cart-badge]').forEach((badge) => {
      badge.textContent = count;
      badge.classList.toggle('hidden', count <= 0);
    });
    document.querySelectorAll('[data-cart-badge-text]').forEach((badge) => {
      badge.textContent = count;
    });
    document.querySelectorAll('[data-cart-count-label]').forEach((label) => {
      label.textContent = count;
    });
  }

  function renderDrawer(summary) {
    if (!drawer || !linesEl) return;

    updateBadges(summary.count || 0);

    const lines = summary.lines || [];
    const isEmpty = lines.length === 0;

    if (emptyEl) emptyEl.classList.toggle('hidden', !isEmpty);
    if (footerEl) footerEl.classList.toggle('hidden', isEmpty);
    linesEl.innerHTML = '';

    lines.forEach((line) => {
      const row = document.createElement('div');
      row.className = 'cart-drawer-line';
      row.innerHTML = `
        <a href="/products/${line.slug}" class="w-16 h-16 rounded-xl overflow-hidden border border-border bg-bg flex-shrink-0">
          <img src="${line.image_url || defaultProductImage}" alt="${line.title}" class="w-full h-full object-cover">
        </a>
        <div class="flex-1 min-w-0">
          <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
              <a href="/products/${line.slug}" class="text-sm font-semibold text-ink hover:text-primary line-clamp-2">${line.title}</a>
              <p class="text-[11px] text-text-muted mt-0.5">${line.option_summary || line.type || ''}</p>
            </div>
            <button type="button" class="text-text-muted hover:text-red-600 text-lg leading-none js-cart-remove" data-key="${line.key}" aria-label="Remove">&times;</button>
          </div>
          <div class="flex items-center justify-between mt-2 gap-2">
            <div class="inline-flex items-center border border-border rounded-lg overflow-hidden">
              <button type="button" class="w-8 h-8 text-ink hover:bg-surface js-cart-qty" data-key="${line.key}" data-delta="-1">−</button>
              <span class="w-8 text-center text-xs font-semibold text-ink">${line.quantity}</span>
              <button type="button" class="w-8 h-8 text-ink hover:bg-surface js-cart-qty" data-key="${line.key}" data-delta="1">+</button>
            </div>
            <span class="text-sm font-semibold text-ink">${line.line_total}</span>
          </div>
        </div>
      `;
      linesEl.appendChild(row);
    });

    if (subtotalEl) subtotalEl.textContent = summary.subtotal || 'Rs. 0';
    if (shippingEl) shippingEl.textContent = summary.shipping_total || 'FREE';
    if (totalEl) totalEl.textContent = summary.total || 'Rs. 0';
  }

  function renderCartPage(summary) {
    const empty = document.getElementById('cartPageEmpty');
    const content = document.getElementById('cartPageContent');
    const linesWrap = document.getElementById('cartPageLines');
    const countLabel = document.getElementById('cartPageCountLabel');
    if (!content || !linesWrap) return;

    updateBadges(summary.count || 0);

    const lines = summary.lines || [];
    const isEmpty = lines.length === 0;

    if (empty) empty.classList.toggle('hidden', !isEmpty);
    content.classList.toggle('hidden', isEmpty);

    if (countLabel) {
      countLabel.textContent = `${summary.count || 0} item${summary.count === 1 ? '' : 's'} ready for pickup or delivery`;
    }

    if (isEmpty) {
      linesWrap.querySelectorAll('.cart-page-line').forEach((el) => el.remove());
      return;
    }

    const existing = new Map();
    linesWrap.querySelectorAll('.cart-page-line').forEach((el) => existing.set(el.dataset.key, el));

    lines.forEach((line) => {
      let row = existing.get(line.key);
      if (row) {
        existing.delete(line.key);
        row.querySelector('.cart-page-qty').textContent = line.quantity;
        row.querySelector('.cart-page-line-total').textContent = line.line_total;
        row.querySelector('.cart-page-unit-price').textContent = `${line.unit_price} each`;
        return;
      }

      row = document.createElement('article');
      row.className = 'cart-page-line flex gap-4 p-5 rounded-2xl bg-surface border border-border';
      row.dataset.key = line.key;
      row.innerHTML = `
        <a href="/products/${line.slug}" class="shrink-0 hover-trigger" aria-label="View ${line.title}">
          <div class="w-24 h-24 sm:w-28 sm:h-28 rounded-xl overflow-hidden border border-border bg-bg">
            <img src="${line.image_url || defaultProductImage}" alt="${line.title}" class="w-full h-full object-cover" loading="lazy">
          </div>
        </a>
        <div class="flex-1 min-w-0 flex flex-col gap-3">
          <div class="flex items-start justify-between gap-3">
            <div>
              <a href="/products/${line.slug}" class="font-fraunces text-lg font-semibold text-ink hover:text-primary transition-colors">${line.title}</a>
              <p class="text-xs text-text-muted font-medium mt-0.5 uppercase tracking-wide">${line.type || ''}</p>
              ${line.option_summary ? `<p class="text-xs text-text-muted mt-1">${line.option_summary}</p>` : ''}
            </div>
            <button type="button" class="js-cart-page-remove w-8 h-8 rounded-full flex items-center justify-center text-text-muted hover:text-red-500 hover:bg-red-50 transition-all" data-key="${line.key}" aria-label="Remove ${line.title}">x</button>
          </div>
          <div class="flex items-center justify-between mt-auto pt-1 flex-wrap gap-3">
            <div class="flex items-center gap-2">
              <span class="text-xs font-semibold text-text-muted uppercase tracking-widest">Qty</span>
              <div class="inline-flex items-center border border-border rounded-lg overflow-hidden">
                <button type="button" class="w-9 h-9 text-ink hover:bg-bg js-cart-page-qty" data-key="${line.key}" data-delta="-1">−</button>
                <span class="cart-page-qty w-10 text-center text-sm font-semibold text-ink">${line.quantity}</span>
                <button type="button" class="w-9 h-9 text-ink hover:bg-bg js-cart-page-qty" data-key="${line.key}" data-delta="1">+</button>
              </div>
            </div>
            <div class="text-right">
              <span class="cart-page-line-total font-fraunces text-xl font-semibold text-ink">${line.line_total}</span>
              <p class="cart-page-unit-price text-[10px] text-text-muted font-medium">${line.unit_price} each</p>
            </div>
          </div>
        </div>
      `;
      const continueLink = linesWrap.querySelector('.pt-2');
      if (continueLink) linesWrap.insertBefore(row, continueLink);
      else linesWrap.appendChild(row);
    });

    existing.forEach((el) => el.remove());

    const subtotal = document.getElementById('cartPageSubtotal');
    const shipping = document.getElementById('cartPageShipping');
    const total = document.getElementById('cartPageTotal');
    const discountRow = document.getElementById('cartPageDiscountRow');
    const discountLabel = document.getElementById('cartPageDiscountLabel');
    const discountAmount = document.getElementById('cartPageDiscountAmount');
    const freeDelivery = document.getElementById('cartPageFreeDelivery');
    const freeDeliveryGap = document.getElementById('cartPageFreeDeliveryGap');

    if (subtotal) subtotal.textContent = summary.subtotal;
    if (shipping) shipping.textContent = summary.shipping_total;
    if (total) total.textContent = summary.total;

    if (discountRow) {
      const hasDiscount = (summary.discount_amount_raw || 0) > 0;
      discountRow.classList.toggle('hidden', !hasDiscount);
      if (hasDiscount) {
        if (discountLabel) discountLabel.textContent = summary.discount_label || summary.discount_code || '';
        if (discountAmount) discountAmount.textContent = `-${summary.discount_amount}`;
      }
    }

    if (freeDelivery) {
      const gap = summary.free_delivery_gap_raw || 0;
      freeDelivery.classList.toggle('hidden', gap <= 0);
      if (gap > 0 && freeDeliveryGap) freeDeliveryGap.textContent = summary.free_delivery_gap;
    }
  }

  function applySummary(summary) {
    renderDrawer(summary);
    if (isCartPage) renderCartPage(summary);
  }

  function openDrawer() {
    if (!drawer || isCartPage) return;
    window.HappyTeaSearch?.closeDrawer?.();
    drawer.classList.add('is-open');
    drawer.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    window.HappyTeaFocusTrap?.trap(document.getElementById('cartDrawerPanel'));
  }

  function closeDrawer() {
    if (!drawer) return;
    drawer.classList.remove('is-open');
    drawer.setAttribute('aria-hidden', 'true');
    if (!document.getElementById('searchDrawer')?.classList.contains('is-open')) {
      document.body.style.overflow = '';
    }
    window.HappyTeaFocusTrap?.release();
  }

  async function fetchSummary() {
    const res = await fetch(routes.summary, { headers: headers(false) });
    if (!res.ok) throw new Error('Unable to load cart');
    const data = await res.json();
    applySummary(data.summary);
    return data.summary;
  }

  async function addToCart(productId, quantity = 1, options = {}) {
    const res = await fetch(routes.store, {
      method: 'POST',
      headers: headers(true),
      body: JSON.stringify({
        product_id: productId,
        quantity,
        size: options.size || 'M',
        sugar: options.sugar || '50%',
        ice: options.ice || 'Regular',
        toppings: options.toppings || [],
      }),
    });

    const data = await res.json();
    if (!res.ok) throw new Error(data.message || 'Unable to add item');

    applySummary(data.summary);
    if (!isCartPage) openDrawer();
    return data;
  }

  async function patchQty(key, quantity) {
    const body = new URLSearchParams();
    body.set('_token', csrf);
    body.set('_method', 'PATCH');
    body.set('quantity', String(quantity));

    const res = await fetch(routes.update(key), {
      method: 'POST',
      headers: formHeaders(),
      body: body.toString(),
    });

    const data = await res.json();
    if (!res.ok) throw new Error(data.message || 'Unable to update cart');
    applySummary(data.summary);
    return data;
  }

  async function removeLine(key) {
    const body = new URLSearchParams();
    body.set('_token', csrf);
    body.set('_method', 'DELETE');

    const res = await fetch(routes.update(key), {
      method: 'POST',
      headers: formHeaders(),
      body: body.toString(),
    });

    const data = await res.json();
    if (!res.ok) throw new Error(data.message || 'Unable to remove item');
    applySummary(data.summary);
    return data;
  }

  document.addEventListener('click', async (event) => {
    const addBtn = event.target.closest('.js-add-to-cart');
    if (addBtn) {
      event.preventDefault();
      event.stopPropagation();
      const productId = addBtn.dataset.productId;
      if (!productId) return;

      addBtn.classList.add('is-loading');
      try {
        await addToCart(Number(productId));
      } catch (error) {
        alert(error.message || 'Could not add to cart');
      } finally {
        addBtn.classList.remove('is-loading');
      }
      return;
    }

    const trigger = event.target.closest('[data-cart-trigger]');
    if (trigger && !isCartPage) {
      event.preventDefault();
      try {
        await fetchSummary();
        openDrawer();
      } catch (error) {
        window.location.href = trigger.getAttribute('href');
      }
      return;
    }

    if (event.target.closest('#cartDrawerClose') || event.target.closest('#cartDrawerBackdrop')) {
      closeDrawer();
      return;
    }

    const qtyBtn = event.target.closest('.js-cart-qty, .js-cart-page-qty');
    if (qtyBtn) {
      event.preventDefault();
      const key = qtyBtn.dataset.key;
      const delta = Number(qtyBtn.dataset.delta || 0);
      const row = qtyBtn.closest('.cart-drawer-line, .cart-page-line');
      const qtyEl = row?.querySelector('.cart-page-qty, span.w-8');
      const current = Number(qtyEl?.textContent || 1);
      const next = Math.max(0, current + delta);
      try {
        await patchQty(key, next);
      } catch (error) {
        alert(error.message || 'Could not update cart');
      }
      return;
    }

    const removeBtn = event.target.closest('.js-cart-remove, .js-cart-page-remove');
    if (removeBtn) {
      event.preventDefault();
      try {
        await removeLine(removeBtn.dataset.key);
      } catch (error) {
        alert(error.message || 'Could not remove item');
      }
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closeDrawer();
  });

  return { addToCart, openDrawer, closeDrawer, fetchSummary, renderDrawer, removeLine, patchQty };
})();

window.HappyTeaWishlist = (function () {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content
    || document.querySelector('input[name="_token"]')?.value
    || 'EWovunn336GFkcyzHxsTJNygMEfu4fHTAN7q0r2Y';
  const routes = {
    store: "http:\/\/localhost\/wishlist",
    destroy: (id) => "http:\/\/localhost\/wishlist".replace(/\/$/, '') + '/' + encodeURIComponent(id),
  };

  function formHeaders() {
    return {
      'X-CSRF-TOKEN': csrf,
      'X-Requested-With': 'XMLHttpRequest',
      'Accept': 'application/json',
      'Content-Type': 'application/x-www-form-urlencoded',
    };
  }

  function updateBadges(count) {
    document.querySelectorAll('[data-wishlist-badge]').forEach((badge) => {
      badge.textContent = count;
      badge.classList.toggle('hidden', count <= 0);
    });
    document.querySelectorAll('[data-wishlist-count]').forEach((el) => {
      el.textContent = count;
    });
  }

  function setProductButton(productId, onWishlist) {
    document.querySelectorAll(`[data-wishlist-toggle][data-product-id="${productId}"]`).forEach((btn) => {
      btn.dataset.onWishlist = onWishlist ? '1' : '0';
      btn.textContent = onWishlist ? 'Remove from Wishlist' : 'Save to Wishlist';
    });
  }

  async function add(productId) {
    const body = new URLSearchParams();
    body.set('_token', csrf);
    body.set('product_id', String(productId));

    const res = await fetch(routes.store, {
      method: 'POST',
      headers: formHeaders(),
      body: body.toString(),
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.message || 'Unable to update wishlist');
    updateBadges(data.count || 0);
    setProductButton(productId, true);
    return data;
  }

  async function remove(productId) {
    const body = new URLSearchParams();
    body.set('_token', csrf);
    body.set('_method', 'DELETE');

    const res = await fetch(routes.destroy(productId), {
      method: 'POST',
      headers: formHeaders(),
      body: body.toString(),
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.message || 'Unable to update wishlist');
    updateBadges(data.count || 0);
    setProductButton(productId, false);

    document.querySelectorAll(`[data-wishlist-item="${productId}"]`).forEach((el) => el.remove());

    const grid = document.getElementById('wishlistGrid');
    const empty = document.getElementById('wishlistEmpty');
    const countLabel = document.querySelector('[data-wishlist-page-count]');
    if (countLabel) countLabel.textContent = data.count || 0;

    if (grid && empty && !grid.querySelector('[data-wishlist-item]')) {
      grid.classList.add('hidden');
      empty.classList.remove('hidden');
    }

    return data;
  }

  async function toggle(productId, currentlyOnWishlist) {
    return currentlyOnWishlist ? remove(productId) : add(productId);
  }

  document.addEventListener('click', async (event) => {
    const toggleBtn = event.target.closest('[data-wishlist-toggle]');
    if (toggleBtn) {
      event.preventDefault();
      const productId = Number(toggleBtn.dataset.productId);
      const onWishlist = toggleBtn.dataset.onWishlist === '1';
      toggleBtn.disabled = true;
      try {
        await toggle(productId, onWishlist);
      } catch (error) {
        alert(error.message || 'Could not update wishlist');
      } finally {
        toggleBtn.disabled = false;
      }
      return;
    }

    const removeBtn = event.target.closest('[data-wishlist-remove]');
    if (removeBtn) {
      event.preventDefault();
      const productId = Number(removeBtn.dataset.productId);
      removeBtn.disabled = true;
      try {
        await remove(productId);
      } catch (error) {
        alert(error.message || 'Could not update wishlist');
        removeBtn.disabled = false;
      }
    }
  });

  return { add, remove, toggle, updateBadges };
})();

/* --- inline-5 --- */
window.HappyTeaSearch = (function () {
  const defaultProductImage = "http:\/\/localhost\/product\/latte.jpg";
  const catalogUrl = "http:\/\/localhost\/menu\/catalog";

  const drawer = document.getElementById('searchDrawer');
  const input = document.getElementById('searchDrawerInput');
  const resultsEl = document.getElementById('searchDrawerResults');
  const emptyEl = document.getElementById('searchDrawerEmpty');
  const loadingEl = document.getElementById('searchDrawerLoading');
  const countEl = document.getElementById('searchDrawerCount');

  let products = [];
  let loaded = false;

  function closeOtherDrawers() {
    window.HappyTeaCart?.closeDrawer?.();
  }

  function openDrawer() {
    if (!drawer) return;
    closeOtherDrawers();
    drawer.classList.add('is-open');
    drawer.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    window.HappyTeaFocusTrap?.trap(document.getElementById('searchDrawerPanel'));
    window.setTimeout(() => input?.focus(), 120);
  }

  function closeDrawer() {
    if (!drawer) return;
    drawer.classList.remove('is-open');
    drawer.setAttribute('aria-hidden', 'true');
    if (!document.getElementById('cartDrawer')?.classList.contains('is-open')) {
      document.body.style.overflow = '';
    }
    window.HappyTeaFocusTrap?.release();
  }

  function renderResults(query = '') {
    if (!resultsEl) return;

    const normalized = query.trim().toLowerCase();
    const filtered = products.filter((product) => {
      if (!normalized) return true;

      return product.title.toLowerCase().includes(normalized)
        || (product.type || '').toLowerCase().includes(normalized);
    });

    if (countEl) countEl.textContent = String(filtered.length);
    if (emptyEl) emptyEl.classList.toggle('hidden', filtered.length > 0);
    resultsEl.innerHTML = '';

    filtered.forEach((product) => {
      const row = document.createElement('a');
      row.href = `/products/${product.slug}`;
      row.className = 'cart-drawer-line hover:bg-surface/60 rounded-xl -mx-2 px-2 transition-colors';
      row.innerHTML = `
        <div class="w-16 h-16 rounded-xl overflow-hidden border border-border bg-bg flex-shrink-0">
          <img src="${product.image_url || defaultProductImage}" alt="${product.title}" class="w-full h-full object-cover">
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-semibold text-ink line-clamp-2">${product.title}</p>
          <p class="text-[11px] text-text-muted mt-0.5 uppercase tracking-wide">${product.type || ''}</p>
          <p class="text-sm font-semibold text-ink mt-2">${product.price}</p>
        </div>
      `;
      resultsEl.appendChild(row);
    });
  }

  async function ensureCatalog() {
    if (loaded) return products;

    if (loadingEl) loadingEl.classList.remove('hidden');
    if (emptyEl) emptyEl.classList.add('hidden');
    if (resultsEl) resultsEl.innerHTML = '';

    const res = await fetch(catalogUrl, {
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });

    if (!res.ok) throw new Error('Unable to load menu');

    const data = await res.json();
    products = data.products || [];
    loaded = true;

    if (loadingEl) loadingEl.classList.add('hidden');
    return products;
  }

  async function openWithCatalog() {
    try {
      await ensureCatalog();
      renderResults(input?.value || '');
      openDrawer();
    } catch (error) {
      window.location.href = "http:\/\/localhost\/shop";
    }
  }

  document.addEventListener('click', (event) => {
    if (event.target.closest('[data-search-trigger]')) {
      event.preventDefault();
      openWithCatalog();
      return;
    }

    if (event.target.closest('#searchDrawerClose') || event.target.closest('#searchDrawerBackdrop')) {
      closeDrawer();
    }
  });

  input?.addEventListener('input', () => {
    renderResults(input.value);
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && drawer?.classList.contains('is-open')) {
      closeDrawer();
    }
  });

  return { openDrawer, closeDrawer, ensureCatalog, renderResults };
})();

/* --- inline-6 --- */
// ============================================================
  // 1. CUSTOM CURSOR
  // ============================================================
  const cursor   = document.getElementById('customCursor');
  const follower = document.getElementById('customCursorFollower');

  if (cursor && follower && window.gsap) {
    let cursorX = 0, cursorY = 0, followerX = 0, followerY = 0;

    document.addEventListener('mousemove', (e) => {
      cursorX = e.clientX; cursorY = e.clientY;
      gsap.set(cursor, { x: cursorX, y: cursorY });
    });

    gsap.ticker.add(() => {
      const dt = 1.0 - Math.pow(1.0 - 0.15, gsap.ticker.deltaRatio());
      followerX += (cursorX - followerX) * dt;
      followerY += (cursorY - followerY) * dt;
      gsap.set(follower, { x: followerX, y: followerY });
    });

    document.addEventListener('mouseover', (e) => {
      if (e.target.closest('.hover-trigger')) {
        document.body.classList.add('hovering-link');
      } else {
        document.body.classList.remove('hovering-link');
      }
    });
  }

  // ============================================================
  // 2. SCROLL REVEALS (Fallback)
  // ============================================================
  if (!CSS.supports('(animation-timeline: view()) and (animation-range: entry)')) {
    const revealObserver = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) entry.target.classList.add('scroll-visible');
      });
    }, { threshold: 0.1, rootMargin: '0px 0px -80px 0px' });

    document.querySelectorAll('.scroll-fade-in-up, .scroll-fade-in, .scroll-scale-up').forEach(el => {
      revealObserver.observe(el);
    });
  }

  // ============================================================
  // 3. DARK SECTION DETECTION (cursor colour swap)
  // ============================================================
  const darkSections = document.querySelectorAll('[data-theme="dark"]');
  if (darkSections.length) {
    const darkThemeObserver = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          document.body.classList.add('dark-bg-active');
        } else {
          const anyVisible = Array.from(darkSections).some(sec => {
            const r = sec.getBoundingClientRect();
            return r.top < window.innerHeight && r.bottom > 0;
          });
          if (!anyVisible) document.body.classList.remove('dark-bg-active');
        }
      });
    }, { rootMargin: '-80px 0px -80px 0px', threshold: 0.05 });
    darkSections.forEach(sec => darkThemeObserver.observe(sec));
  }

  // ============================================================
  // 4. 3D TILT ON CATEGORY CARDS
  // ============================================================
  if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    document.querySelectorAll('.tilt-card').forEach(card => {
      card.addEventListener('mousemove', (e) => {
        const rect = card.getBoundingClientRect();
        const dx = (e.clientX - rect.left - rect.width  / 2) / (rect.width  / 2);
        const dy = (e.clientY - rect.top  - rect.height / 2) / (rect.height / 2);
        card.style.transform = `perspective(900px) rotateY(${dx * 8}deg) rotateX(${-dy * 8}deg) scale(1.02)`;
      });
      card.addEventListener('mouseleave', () => {
        card.style.transform = 'perspective(900px) rotateX(0deg) rotateY(0deg) scale(1)';
        card.style.transition = 'transform 0.5s cubic-bezier(0.25, 1, 0.5, 1), box-shadow 0.3s ease';
      });
      card.addEventListener('mouseenter', () => {
        card.style.transition = 'transform 0.08s linear, box-shadow 0.3s ease';
      });
    });
  }

  // ============================================================
  // 5. STAT COUNTER ANIMATION
  // ============================================================
  const statNumbers = document.querySelectorAll('.stat-number[data-count]');
  if (statNumbers.length) {
    const counterObserver = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (!entry.isIntersecting) return;
        const el = entry.target;
        const target    = parseFloat(el.dataset.count);
        const isDecimal = target % 1 !== 0;
        const duration  = 2000;
        const startTime = performance.now();
        function tick(now) {
          const p = Math.min((now - startTime) / duration, 1);
          const eased = 1 - Math.pow(1 - p, 3);
          const val = target * eased;
          el.textContent = isDecimal ? val.toFixed(1) : target >= 1000 ? Math.floor(val).toLocaleString() : Math.floor(val);
          if (p < 1) requestAnimationFrame(tick);
          else       el.textContent = isDecimal ? target.toFixed(1) : target >= 1000 ? target.toLocaleString() : target;
        }
        requestAnimationFrame(tick);
        counterObserver.unobserve(el);
      });
    }, { threshold: 0.5 });
    statNumbers.forEach(el => counterObserver.observe(el));
  }

  // ============================================================
  // 7. REVIEWS SCROLL NAV
  // ============================================================
  function scrollReviews(direction) {
    const container = document.getElementById('reviewsScroll');
    if (!container) return;
    container.scrollBy({ left: direction * (380 + 24), behavior: 'smooth' });
  }

  // ============================================================
  // 9. QTY STEPPERS
  // ============================================================
  document.querySelectorAll('.qty-stepper').forEach(stepper => {
    const decBtn = stepper.querySelector('[data-action="dec"]');
    const incBtn = stepper.querySelector('[data-action="inc"]');
    const valEl  = stepper.querySelector('.qty-value');
    if (!decBtn || !incBtn || !valEl) return;
    decBtn.addEventListener('click', () => {
      const current = parseInt(valEl.value || valEl.textContent) || 1;
      if (current > 1) valEl.value ? valEl.value = current - 1 : valEl.textContent = current - 1;
    });
    incBtn.addEventListener('click', () => {
      const current = parseInt(valEl.value || valEl.textContent) || 1;
      valEl.value ? valEl.value = current + 1 : valEl.textContent = current + 1;
    });
  });

  // ============================================================
  // 10. PILL OPTION SELECTORS
  // ============================================================
  document.querySelectorAll('.pill-group').forEach(group => {
    group.querySelectorAll('.pill-option').forEach(pill => {
      pill.addEventListener('click', () => {
        group.querySelectorAll('.pill-option').forEach(p => p.classList.remove('active'));
        pill.classList.add('active');
      });
    });
  });

/* --- inline-7 --- */
(function() {
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const kenBurnsOrigins = ['48% 46%', '54% 48%', '45% 54%', '56% 52%'];
  let activeSlideTimeline = null, bgImageAnimation = null, spinAnimation = null;

  function getActiveSlides(swiper) {
    const active = Array.from(swiper.slides).filter(s =>
      s.classList.contains('swiper-slide-active') ||
      s.classList.contains('swiper-slide-duplicate-active')
    );
    return active.length ? active : Array.from(swiper.el.querySelectorAll(`[data-swiper-slide-index="${swiper.realIndex}"]`));
  }

  function resetInactiveSlides(swiper) {
    const activeSet = new Set(getActiveSlides(swiper));
    swiper.slides.forEach(slide => {
      if (activeSet.has(slide)) return;
      const bg = slide.querySelector('.bg-image');
      if (bg) { gsap.killTweensOf(bg); gsap.set(bg, { scale:1.08, xPercent:0, yPercent:0, filter:'brightness(0.92)', transformOrigin:'50% 50%' }); }
      gsap.set(slide.querySelectorAll('.left-badge,.right-badge'), { scale:0, opacity:0 });
      gsap.set(slide.querySelectorAll('.left-title,.left-desc,.left-btn'), { y:50, scale:1, opacity:0 });
      gsap.set(slide.querySelectorAll('.right-spec'), { x:30, opacity:0 });
    });
  }

  function animateActiveSlides(swiper) {
    if (activeSlideTimeline) activeSlideTimeline.kill();
    if (bgImageAnimation) bgImageAnimation.kill();
    if (spinAnimation) spinAnimation.kill();
    const realIndex = swiper.realIndex;
    const slides = getActiveSlides(swiper);
    const bgOrigin = kenBurnsOrigins[realIndex % kenBurnsOrigins.length];

    activeSlideTimeline = gsap.timeline({
      defaults: { ease:'power4.out' },
      onComplete: () => {
        if (reduceMotion) return;
        const bgImages = slides.map(s => s.querySelector('.bg-image')).filter(Boolean);
        if (bgImages.length) {
          gsap.set(bgImages, { transformOrigin: bgOrigin });
          bgImageAnimation = gsap.fromTo(bgImages, { scale:1.08 }, { scale:1.15, xPercent:0.55, yPercent:-0.55, duration:18, ease:'sine.inOut', yoyo:true, repeat:-1 });
        }
        const rotates = slides.map(s => s.querySelector('.right-badge-rotate')).filter(Boolean);
        if (rotates.length) spinAnimation = gsap.fromTo(rotates, { rotation:0 }, { rotation:360, duration:25, ease:'none', repeat:-1 });
      }
    });

    slides.forEach(slide => {
      const bg = slide.querySelector('.bg-image');
      if (bg) { gsap.killTweensOf(bg); activeSlideTimeline.fromTo(bg, { scale:1.16, xPercent:-0.8, filter:'brightness(0.95)', transformOrigin:bgOrigin }, { scale:1.08, xPercent:0, filter:'brightness(1)', duration:1.6, ease:'power3.out' }, 0); }
      activeSlideTimeline.fromTo(slide.querySelector('.left-badge'),  { scale:0, opacity:0 }, { scale:1, opacity:1, duration:0.8, ease:'back.out(1.5)' }, 0.4);
      activeSlideTimeline.fromTo([slide.querySelector('.left-title'), slide.querySelector('.left-desc'), slide.querySelector('.left-btn')], { y:50, opacity:0 }, { y:0, opacity:(i) => i===1 ? 0.7 : 1, duration:0.8, stagger:0.1 }, 0.5);
      activeSlideTimeline.fromTo(slide.querySelector('.right-badge'), { scale:0, rotation:90, opacity:0 }, { scale:1, rotation:0, opacity:1, duration:1, ease:'back.out(1.5)' }, 0.4);
      const spec = slide.querySelector('.right-spec');
      if (spec) activeSlideTimeline.fromTo(spec, { x:30, opacity:0 }, { x:0, opacity:1, duration:0.8 }, 0.6);
    });
  }

  const swiper = new Swiper('.mySwiper', {
    effect: 'fade', fadeEffect: { crossFade:true },
    speed: 1000, loop: true,
    keyboard: { enabled: true },
    navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' },
    autoplay: { delay: 6000, disableOnInteraction: false },
    on: {
      autoplayTimeLeft(s, time, progress) {
        const bar = document.querySelector('.autoplay-progress-bar');
        const txt = document.querySelector('.autoplay-progress-time');
        if (bar) bar.style.width = `${(1-progress)*100}%`;
        if (txt)  txt.textContent = `${(time/1000).toFixed(1)}s`;
      },
      init(s) { resetInactiveSlides(s); animateActiveSlides(s); },
      slideChangeTransitionStart(s) { animateActiveSlides(s); },
      slideChangeTransitionEnd(s)   { resetInactiveSlides(s); }
    }
  });

})();
