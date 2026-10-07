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
