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
