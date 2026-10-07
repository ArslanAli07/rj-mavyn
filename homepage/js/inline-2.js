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
