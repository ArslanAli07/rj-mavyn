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
