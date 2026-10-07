import Alpine from 'alpinejs';

window.Alpine = Alpine;

document.dispatchEvent(new CustomEvent('alpine:init'));

Alpine.start();

window.copyTextToClipboard = function(text) {
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(text).then(() => {
      window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Tracking link copied!', type: 'success' } }));
    }).catch(err => {
      fallbackCopy(text);
    });
  } else {
    fallbackCopy(text);
  }

  function fallbackCopy(val) {
    const textArea = document.createElement('textarea');
    textArea.value = val;
    textArea.style.position = 'fixed';
    textArea.style.opacity = '0';
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    try {
      document.execCommand('copy');
      window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Tracking link copied!', type: 'success' } }));
    } catch (err) {
      console.error('Fallback copy failed', err);
    }
    document.body.removeChild(textArea);
  }
};

document.addEventListener('DOMContentLoaded', () => {
    // 1. Custom trailing mouse cursor
    (function initCustomCursor() {
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
    })();

    // 2. Scroll reveal fallback observer
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

    // 3. 3D Tilt card effect
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

    // 4. Cubic-ease statistics counter incrementation
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
            const eased = 1 - Math.pow(1 - p, 3); // Cubic ease out
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

    // 5. Initialize Swiper Slider (if exists)
    (function initSwiperSlider() {
      const el = document.querySelector('.mySwiper');
      if (!el || !window.Swiper) return;

      const updateHeight = (swiper) => {
        if (typeof swiper.updateAutoHeight === 'function') {
          swiper.updateAutoHeight(300);
        }
      };

      const swiper = new Swiper('.mySwiper', {
        effect: 'fade',
        fadeEffect: { crossFade: true },
        speed: 1000,
        loop: true,
        autoHeight: true,
        autoplay: { delay: 6000, disableOnInteraction: false },
        navigation: {
          nextEl: '#heroBanner .swiper-button-next',
          prevEl: '#heroBanner .swiper-button-prev',
        },
        pagination: {
          el: '#heroBanner .swiper-pagination',
          clickable: true,
        },
        on: {
          init(s) { updateHeight(s); },
          slideChangeTransitionEnd(s) { updateHeight(s); },
        },
      });

      window.addEventListener('resize', () => updateHeight(swiper), { passive: true });

      el.querySelectorAll('.hero-slide-image').forEach((img) => {
        if (img.complete) return;
        img.addEventListener('load', () => updateHeight(swiper), { once: true });
      });
    })();

    // 7. Global AJAX Cart Handlers
    window.updateCartDrawer = function() {
      return fetch(window.location.href)
        .then(res => res.text())
        .then(html => {
          const parser = new DOMParser();
          const doc = parser.parseFromString(html, 'text/html');
          
          const currentDrawer = document.querySelector('[x-show="cartOpen"] .relative');
          const newDrawer = doc.querySelector('[x-show="cartOpen"] .relative');
          if (currentDrawer && newDrawer) {
            currentDrawer.innerHTML = newDrawer.innerHTML;
            if (window.Alpine) {
              window.Alpine.initTree(currentDrawer);
            }
          }
          
          const newBadge = doc.querySelector('[data-cart-badge]');
          if (newBadge) {
            const count = Number.parseInt(newBadge.textContent.trim(), 10) || 0;
            document.querySelectorAll('[data-cart-badge]').forEach((badge) => {
              badge.textContent = count;
              badge.classList.toggle('hidden', count === 0);
            });
          }
        });
    };

    document.addEventListener('submit', (e) => {
      const form = e.target;
      if (!form.action) return;

      if (form.action.includes('/cart/add')) {
        e.preventDefault();
        const btn = form.querySelector('button[type="submit"]');
        if (btn) btn.disabled = true;

        const formData = new FormData(form);
        fetch(form.action, {
          method: 'POST',
          headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': formData.get('_token')
          },
          body: formData
        })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            window.dispatchEvent(new CustomEvent('toast', { detail: { message: data.message, type: 'success' } }));
            window.updateCartDrawer().then(() => {
              if (window.Alpine) {
                window.Alpine.$data(document.body).cartOpen = true;
              }
            });
          } else {
            window.dispatchEvent(new CustomEvent('toast', { detail: { message: data.message || 'Error adding to cart', type: 'error' } }));
          }
        })
        .catch(err => {
          console.error(err);
          window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Something went wrong. Please try again.', type: 'error' } }));
        })
        .finally(() => {
          if (btn) btn.disabled = false;
        });
      }

      if (form.action.includes('/cart/remove')) {
        e.preventDefault();
        const formData = new FormData(form);
        fetch(form.action, {
          method: 'POST',
          headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': formData.get('_token')
          },
          body: formData
        })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Item removed from cart.', type: 'info' } }));
            window.updateCartDrawer();
          }
        })
        .catch(err => console.error(err));
      }
    });
});

// 6. Review Scrolling Navigation
window.scrollReviews = function(dir) {
  const container = document.getElementById('reviewsScroll');
  if (container) {
    const scrollAmount = container.clientWidth * 0.8;
    container.scrollBy({ left: dir * scrollAmount, behavior: 'smooth' });
  }
};
