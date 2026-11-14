import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import '../../dist/design/js/vendor/gsap.js';
import '../../dist/design/js/motion.js';

window.Alpine = Alpine;
Alpine.plugin(collapse);
// Fallback checkout registers its existing data component with this event.
document.dispatchEvent(new CustomEvent('alpine:init'));
Alpine.start();

const $ = (selector, root) => (root || document).querySelector(selector);
const $$ = (selector, root) => [...(root || document).querySelectorAll(selector)];
const motion = window.SiteMotion;
const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
let toastTimer;
function toast(message) {
    const element = $('.toast');
    if (!element) return;
    element.textContent = message;
    motion.showToast(element);
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => motion.hideToast(element), 4000);
}
window.addEventListener('toast', event => toast(event.detail?.message || ''));
window.copyTextToClipboard = async text => {
    try { await navigator.clipboard.writeText(text); toast('Tracking link copied.'); }
    catch { toast('Please copy the tracking link from your address bar.'); }
};
window.scrollReviews = direction => $('#reviewsScroll')?.scrollBy({left:direction * 400, behavior:reduced.matches ? 'instant' : 'smooth'});

function ready() {
    document.addEventListener('click', event => {
        const opener = event.target.closest('[data-dialog]');
        if (opener) motion.openDialog(document.getElementById(opener.dataset.dialog));
        const closer = event.target.closest('.dialog-close');
        if (closer) motion.closeDialog(closer.closest('dialog'));
    });
    $$('.dialog').forEach(dialog => {
        dialog.addEventListener('cancel', event => { event.preventDefault(); motion.closeDialog(dialog); });
        dialog.addEventListener('close', () => motion.resetDialog(dialog));
        dialog.addEventListener('click', event => {
            if (event.target !== dialog) return;
            // A backdrop click lands on the dialog element itself; it only
            // closes when it happened outside the visible panel.
            const panel = dialog.querySelector('.dialog__panel') || dialog;
            const bounds = panel.getBoundingClientRect();
            if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) motion.closeDialog(dialog);
        });
    });
    const toggle = $('.mobile-menu-toggle');
    const menu = $('#mobile-menu');
    function closeMenu(immediate = false) {
        if (!toggle || !menu) return;
        if (menu.contains(document.activeElement)) toggle.focus({preventScroll:true});
        motion.closeMenu(menu, immediate);
        toggle.setAttribute('aria-expanded','false');
        toggle.setAttribute('aria-label','Open menu');
    }
    toggle?.addEventListener('click', () => {
        if (toggle.getAttribute('aria-expanded') === 'true') return closeMenu();
        motion.openMenu(menu);
        toggle.setAttribute('aria-expanded','true'); toggle.setAttribute('aria-label','Close menu');
    });
    $$('[data-mobile-nav-toggle]').forEach(button => button.addEventListener('click', () => {
        const panel = document.getElementById(button.getAttribute('aria-controls'));
        const open = button.getAttribute('aria-expanded') === 'true';
        button.setAttribute('aria-expanded', String(!open));
        button.textContent = open ? '+' : '−';
        if (panel) panel.hidden = open;
    }));
    document.addEventListener('keydown', event => {
        const dialog = $('dialog[open]');
        if (event.key === 'Escape') {
            if (dialog) { event.preventDefault(); motion.closeDialog(dialog); }
            else closeMenu();
        }
        if (dialog?.id === 'gallery-dialog' && ['ArrowLeft','ArrowRight'].includes(event.key)) {
            event.preventDefault(); step(event.key === 'ArrowRight' ? 1 : -1);
        }
    });
    document.addEventListener('click', event => { if (menu && !menu.hidden && !event.target.closest('.rj-header')) closeMenu(); });
    window.addEventListener('resize', () => { if (innerWidth >= 1024) closeMenu(true); });
    $$('[data-back-to-top]').forEach(button => button.addEventListener('click', () => window.scrollTo({top:0,behavior:reduced.matches ? 'instant' : 'smooth'})));

    $$('details').forEach(details => {
        const summary = $('summary', details);
        if (!summary) return;
        const content = document.createElement('div'); content.className = 'accordion-content';
        [...details.childNodes].filter(node => node !== summary).forEach(node => content.append(node));
        details.append(content); details.dataset.expanded = String(details.open);
        summary.addEventListener('click', event => {
            if (event.target.closest('a,button,input,select')) return;
            event.preventDefault(); motion.toggleAccordion(details);
        });
    });
    motion.reveal($$('.hero-content > *, .rj-hero__content > *, .rj-page-head__title, .rj-page-head .rj-lede, .purchase-sticky > h1'), {key:document.body,y:16,duration:0.4,stagger:0.05});

    // Landing hero: dot pager and arrows over the administrator's hero slides.
    const hero = $('[data-hero]');
    if (hero) {
        const heroSlides = $$('[data-hero-slide]', hero);
        const heroDots = $$('[data-hero-dot]', hero);
        let heroIndex = Math.max(0, heroSlides.findIndex(slide => slide.classList.contains('is-active')));
        const heroTitle = $('.rj-hero__title');
        const heroText = $('.rj-hero__text');
        const heroButton = $('[data-hero-button]');
        const showSlide = next => {
            if (!heroSlides.length) return;
            heroIndex = (next + heroSlides.length) % heroSlides.length;
            heroSlides.forEach((slide, index) => slide.classList.toggle('is-active', index === heroIndex));
            heroDots.forEach((dot, index) => dot.setAttribute('aria-current', String(index === heroIndex)));
            const slide = heroSlides[heroIndex];
            // The slide owns its copy; the page keeps a single heading.
            if (heroTitle && slide.dataset.title !== undefined) {
                const highlight = slide.dataset.highlight ? ' <span>' + slide.dataset.highlight + '</span>' : '';
                heroTitle.innerHTML = slide.dataset.title + highlight;
                if (heroText) heroText.textContent = slide.dataset.description || '';
                if (heroButton) {
                    heroButton.href = slide.dataset.buttonLink || heroButton.href;
                    heroButton.firstChild.textContent = slide.dataset.buttonText ? slide.dataset.buttonText + ' ' : '';
                }
                const content = $('.rj-hero__content');
                if (content) motion.reveal(content.children, { key: content, y: 12, duration: 0.35, stagger: 0.04 });
            }
        };
        $('[data-hero-next]', hero)?.addEventListener('click', () => { showSlide(heroIndex + 1); restart(); });
        $('[data-hero-prev]', hero)?.addEventListener('click', () => { showSlide(heroIndex - 1); restart(); });
        heroDots.forEach((dot, index) => dot.addEventListener('click', () => { showSlide(index); restart(); }));
        let timer;
        const restart = () => {
            clearInterval(timer);
            if (heroSlides.length < 2 || reduced.matches) return;
            timer = setInterval(() => showSlide(heroIndex + 1), 7000);
        };
        hero.addEventListener('mouseenter', () => clearInterval(timer));
        hero.addEventListener('mouseleave', restart);
        restart();
    }

    // Horizontal scrollers used by the landing page sections.
    $$('[data-scroll-target]').forEach(button => {
        const scroller = document.querySelector(button.dataset.scrollTarget);
        if (!scroller) return;
        const step = Number(button.dataset.scrollStep || 1);
        button.addEventListener('click', () => motion.scrollBy(scroller, step * scroller.clientWidth * 0.8));
    });

    // Navigation mega-menu: hover is handled in CSS, but the pointer crossing the
    // short gap into the panel leaves the group's box, so JS keeps the open group
    // alive with a small grace period. The caret still toggles for touch and
    // keyboard users.
    // Search: the placeholder rotates through a few terms every few seconds
    // (paused for reduced-motion users) and focusing the field opens a quick
    // panel of the store's main categories.
    $$('[data-rotating-placeholder]').forEach(input => {
        const terms = (input.dataset.placeholderTerms || '').split(',').map(term => term.trim()).filter(Boolean);
        if (terms.length < 2) return;
        let index = 0;
        setInterval(() => {
            index = (index + 1) % terms.length;
            if (reduced.matches) { input.placeholder = `Search for... ${terms[index]}`; return; }
            input.style.opacity = '0';
            setTimeout(() => {
                input.placeholder = `Search for... ${terms[index]}`;
                input.style.opacity = '1';
            }, 300);
        }, 3500);
    });
    $$('[data-search-panel]').forEach(panel => {
        const wrap = panel.closest('.rj-searchwrap'); if (!wrap) return;
        const show = () => { panel.hidden = false; };
        const hide = () => { panel.hidden = true; };
        wrap.addEventListener('focusin', show);
        wrap.addEventListener('focusout', event => { if (!wrap.contains(event.relatedTarget)) hide(); });
        document.addEventListener('keydown', event => { if (event.key === 'Escape') hide(); });
    });

    const navGroups = $$('.rj-nav__group');
    const setGroup = (group, open) => {
        group.classList.toggle('is-open', open);
        group.querySelector('.rj-nav__caret')?.setAttribute('aria-expanded', String(open));
    };
    const alignNavPanels = () => {
        navGroups.forEach(group => {
            const panel = group.querySelector('.rj-nav__panel');
            if (!panel) return;
            // Measure even while hidden so the offset is ready before first hover.
            const wasHidden = getComputedStyle(panel).display === 'none';
            if (wasHidden) {
                panel.style.visibility = 'hidden';
                panel.style.display = 'block';
            }
            const groupRect = group.getBoundingClientRect();
            const panelWidth = panel.offsetWidth;
            if (wasHidden) {
                panel.style.display = '';
                panel.style.visibility = '';
            }
            // The panel's left edge tracks the hovered link (each menu further
            // right opens further right); slide it left only when it would run
            // off the right edge of the viewport.
            const margin = 20;
            let shift = 0;
            const overflow = groupRect.left + panelWidth + margin - window.innerWidth;
            if (overflow > 0) {
                shift = -overflow;
            }
            if (groupRect.left + shift < margin) {
                shift = margin - groupRect.left;
            }
            if (Math.abs(shift) > 1) {
                panel.style.setProperty('--nav-shift', `${Math.round(shift)}px`);
            } else {
                panel.style.removeProperty('--nav-shift');
            }
        });
    };
    alignNavPanels();
    window.addEventListener('resize', alignNavPanels);
    window.addEventListener('load', alignNavPanels);
    const closeNavGroups = except => navGroups.forEach(group => { if (group !== except) setGroup(group, false); });
    let navCloseTimer = null;
    navGroups.forEach(group => {
        if (!group.querySelector('.rj-nav__panel')) return;
        group.addEventListener('mouseenter', () => {
            clearTimeout(navCloseTimer);
            closeNavGroups(group);
            setGroup(group, true);
        });
        group.addEventListener('mouseleave', () => {
            clearTimeout(navCloseTimer);
            navCloseTimer = setTimeout(() => setGroup(group, false), 140);
        });
    });
    $$('.rj-nav__caret').forEach(caret => {
        caret.addEventListener('click', event => {
            event.preventDefault();
            event.stopPropagation();
            const group = caret.closest('.rj-nav__group');
            const open = !group.classList.contains('is-open');
            closeNavGroups(group);
            setGroup(group, open);
        });
    });
    document.addEventListener('click', event => { if (!event.target.closest('.rj-nav__group')) closeNavGroups(); });
    document.addEventListener('keydown', event => { if (event.key === 'Escape') closeNavGroups(); });

    $$('.product-section').forEach(section => {
        const slider = $('.product-slider',section), previous = $('.slider-prev',section), next = $('.slider-next',section), track = $('.slider-track span',section);
        function update() {
            const max = slider.scrollWidth - slider.clientWidth;
            previous.disabled = slider.scrollLeft < 5; next.disabled = max < 5 || slider.scrollLeft >= max - 5;
            track.style.width = `${Math.min(1, slider.clientWidth / Math.max(slider.scrollWidth,1)) * 100}%`;
            track.style.transform = `translateX(${slider.scrollLeft / Math.max(slider.clientWidth,1) * 100}%)`;
        }
        $$('[data-slide]',section).forEach(button => button.addEventListener('click', () => motion.scrollBy(slider,Number(button.dataset.slide)*slider.clientWidth*0.8)));
        slider.addEventListener('keydown',event => { if (event.target === slider && ['ArrowLeft','ArrowRight'].includes(event.key)) { event.preventDefault(); motion.scrollBy(slider,(event.key === 'ArrowRight' ? 1 : -1)*slider.clientWidth*0.5); } });
        ['pointerdown','wheel'].forEach(type => slider.addEventListener(type, () => motion.cancelScroll(slider), {passive:true}));
        slider.addEventListener('scroll',update,{passive:true}); new ResizeObserver(update).observe(slider);
        const tabs = $$('[data-product-tab]',section);
        tabs.forEach((tab,index) => {
            tab.addEventListener('click', () => {
                tabs.forEach(other => { const selected = other === tab; other.classList.toggle('active',selected); other.setAttribute('aria-selected',String(selected)); other.tabIndex = selected ? 0 : -1; });
                slider.setAttribute('aria-labelledby',tab.id);
                const cards = $$('.product-card',slider);
                cards.forEach(card => { card.hidden = tab.dataset.productTab !== 'trending' && card.dataset[tab.dataset.productTab] !== '1'; });
                $('.rj-featured-empty',section).hidden = cards.some(card => !card.hidden);
                motion.cancelScroll(slider); slider.scrollLeft = 0; update(); motion.reveal(cards.filter(card => !card.hidden),{key:section});
            });
            tab.addEventListener('keydown', event => {
                if (!['ArrowLeft','ArrowRight','Home','End'].includes(event.key)) return;
                event.preventDefault(); const target = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length-1 : (index+(event.key === 'ArrowRight' ? 1 : -1)+tabs.length)%tabs.length;
                tabs[target].focus(); tabs[target].click();
            });
        }); update();
    });
    const grid = $('.rj-catalog__grid');
    const original = grid ? $$('.rj-card',grid) : [];
    $('#product-sort')?.addEventListener('change', event => {
        const cards = [...original], before = motion.captureLayout(cards), value = event.target.value;
        if (value === 'price-low') cards.sort((a,b) => Number(a.dataset.price)-Number(b.dataset.price));
        if (value === 'price-high') cards.sort((a,b) => Number(b.dataset.price)-Number(a.dataset.price));
        if (value === 'new') cards.sort((a,b) => Number(b.dataset.new)-Number(a.dataset.new));
        cards.forEach(card => grid.append(card)); motion.reflow(grid,cards,before);
    });

    const readWishlist = () => { try { const ids = JSON.parse(localStorage.getItem('wishlist') || '[]'); return Array.isArray(ids) ? ids.filter(id => Number.isInteger(id)).slice(0,100) : []; } catch { return []; } };
    function updateWishlist() {
        const ids = readWishlist();
        $$('[data-favorite]:not([data-wishlist-url])').forEach(button => button.setAttribute('aria-pressed',String(ids.includes(Number(button.dataset.favorite)))));
        const authenticated = document.body.dataset.customerAuthenticated === 'true';
        const count = authenticated ? Number(document.body.dataset.wishlistCount || 0) : ids.length;
        $$('.wishlist-count').forEach(badge => { badge.textContent = count; badge.hidden = count === 0; });
        if (!authenticated) $$('[data-wishlist-link]').forEach(link => { const url = new URL(link.href); url.searchParams.set('ids',ids.join(',')); link.href = url.href; });
    }
    document.addEventListener('click', async event => {
        const button = event.target.closest('[data-favorite]'); if (!button || button.disabled) return;
        button.disabled = true;
        try {
            let added;
            if (button.dataset.wishlistUrl) {
                const response = await fetch(button.dataset.wishlistUrl,{method:'POST',headers:{'Accept':'application/json','X-CSRF-TOKEN':$('meta[name=csrf-token]').content}});
                const data = await response.json(); if (!response.ok) throw new Error(data.message || 'Unable to update wishlist.');
                added = data.added;
                document.body.dataset.wishlistCount = String(data.count);
                $$(`[data-favorite="${button.dataset.favorite}"]`).forEach(other => other.setAttribute('aria-pressed',String(added)));
            } else {
                const id = Number(button.dataset.favorite), ids = readWishlist(); added = !ids.includes(id);
                localStorage.setItem('wishlist',JSON.stringify(added ? [...ids,id] : ids.filter(item => item !== id)));
            }
            updateWishlist(); motion.pulse(button); toast(added ? 'Added to wishlist.' : 'Removed from wishlist.');
        } catch (error) { toast(error.message); } finally { button.disabled = false; }
    });
    updateWishlist(); window.addEventListener('storage',updateWishlist);
    // Guest wishlist URLs use the core's documented ids query parameter.
    if (document.body.dataset.customerAuthenticated !== 'true' && location.pathname === '/wishlist' && !location.search && readWishlist().length) {
        const link = $('[data-wishlist-link]'); if (link) location.replace(link.href);
    }

    const gallery = $('.rj-gallery[data-gallery]');
    const galleryTrack = gallery ? $('[data-gallery-thumbs]', gallery) : null;
    const galleryButtons = gallery ? $$('[data-gallery-index]', galleryTrack ?? gallery) : [];
    const stageImage = gallery ? $('[data-gallery-main-image]', gallery) : null;
    const stageButton = gallery ? $('[data-gallery-stage]', gallery) : null;
    const positionLabel = gallery ? $('[data-gallery-position]', gallery) : null;
    const lightboxDialog = $('#gallery-dialog');
    const lightboxImage = lightboxDialog ? $('.lightbox-image', lightboxDialog) : null;
    const lightboxRail = lightboxDialog ? $('[data-lightbox-thumbs]', lightboxDialog) : null;
    const lightboxPosition = lightboxDialog ? $('.lightbox-position', lightboxDialog) : null;
    const lightboxArrows = lightboxDialog ? $$('[data-lightbox-step]', lightboxDialog) : [];
    let lightboxIndex = 0;
    let activeIndex = 0;
    let lightboxZoom = 1;
    let lightboxRailBuilt = false;

    function setActiveThumb(index) {
        activeIndex = index;
        const button = galleryButtons[index];
        if (stageImage && button) {
            const source = $('img', button);
            stageImage.src = button.dataset.gallerySrc || source?.src || stageImage.src;
            const srcset = button.dataset.gallerySrcset || source?.srcset || '';
            if (srcset) stageImage.setAttribute('srcset', srcset); else stageImage.removeAttribute('srcset');
            stageImage.alt = source?.alt || stageImage.alt;
        }
        galleryButtons.forEach((thumb, thumbIndex) => {
            thumb.classList.toggle('is-active', thumbIndex === index);
            thumb.setAttribute('aria-current', thumbIndex === index ? 'true' : 'false');
        });
        if (positionLabel && galleryButtons.length) positionLabel.textContent = `${index + 1} / ${galleryButtons.length}`;
    }
    function zoomApply() {
        if (!lightboxImage) return;
        lightboxImage.style.transform = lightboxZoom > 1 ? `scale(${lightboxZoom})` : '';
        $$('[data-lightbox-zoom]', lightboxDialog).forEach(button => {
            const amount = Number(button.dataset.lightboxZoom);
            button.disabled = amount > 0 ? lightboxZoom >= 3 : lightboxZoom <= 1;
        });
    }
    function zoomSet(level) {
        lightboxZoom = Math.min(3, Math.max(1, level));
        zoomApply();
    }
    function buildRail() {
        if (!lightboxRail || lightboxRailBuilt || !galleryButtons.length) return;
        galleryButtons.forEach((button, index) => {
            const source = $('img', button);
            const thumb = document.createElement('button');
            thumb.type = 'button';
            thumb.className = 'lightbox-thumb';
            thumb.setAttribute('aria-label', `View image ${index + 1}`);
            if (source) {
                const clone = source.cloneNode(false);
                clone.loading = 'lazy';
                clone.removeAttribute('srcset');
                clone.removeAttribute('sizes');
                thumb.appendChild(clone);
            }
            thumb.addEventListener('click', () => showImage(index));
            lightboxRail.appendChild(thumb);
        });
        lightboxRail.hidden = galleryButtons.length <= 1;
        lightboxRailBuilt = true;
    }
    function showImage(index = lightboxIndex) {
        if (!lightboxImage) return;
        if (galleryButtons.length) {
            lightboxIndex = (index + galleryButtons.length) % galleryButtons.length;
            const button = galleryButtons[lightboxIndex];
            const source = $('img', button);
            if (!button) return;
            lightboxImage.src = button.dataset.gallerySrc || source?.src || lightboxImage.src;
            const srcset = button.dataset.gallerySrcset || source?.srcset || '';
            if (srcset) lightboxImage.setAttribute('srcset', srcset); else lightboxImage.removeAttribute('srcset');
            lightboxImage.alt = source?.alt || lightboxImage.alt;
            if (lightboxPosition) lightboxPosition.textContent = `${lightboxIndex + 1} / ${galleryButtons.length}`;
            if (lightboxRailBuilt) {
                $$('.lightbox-thumb', lightboxRail).forEach((thumb, thumbIndex) => {
                    thumb.classList.toggle('is-active', thumbIndex === lightboxIndex);
                    if (thumbIndex === lightboxIndex) thumb.scrollIntoView({block: 'nearest', inline: 'nearest'});
                });
            }
        } else if (stageImage) {
            lightboxImage.src = stageImage.currentSrc || stageImage.src;
            lightboxImage.alt = stageImage.alt;
            if (lightboxPosition) lightboxPosition.textContent = '1 / 1';
        } else {
            return;
        }
        zoomSet(1);
    }
    setActiveThumb(0);
    // Arrows, rail and the counter are meaningless for a single image.
    if (galleryButtons.length <= 1) {
        lightboxRail?.setAttribute('hidden','');
        lightboxArrows.forEach(button => button.hidden = true);
    }
    galleryButtons.forEach((button,index) => button.addEventListener('click', () => setActiveThumb(index)));
    stageButton?.addEventListener('click', () => { lightboxIndex = activeIndex; buildRail(); showImage(); motion.openDialog(lightboxDialog); });
    function step(amount) { if (!galleryButtons.length) return; lightboxIndex = (lightboxIndex+amount+galleryButtons.length)%galleryButtons.length; showImage(); }
    $$('[data-lightbox-step]').forEach(button => button.addEventListener('click', () => step(Number(button.dataset.lightboxStep))));
    $$('[data-lightbox-zoom]').forEach(button => button.addEventListener('click', () => zoomSet(lightboxZoom + Number(button.dataset.lightboxZoom) * 0.5)));
    $$('[data-gallery-scroll]').forEach(button => button.addEventListener('click', () => {
        const amount = Number(button.dataset.galleryScroll);
        const next = Math.min(galleryButtons.length - 1, Math.max(0, activeIndex + amount));
        if (next === activeIndex) return;
        galleryButtons[next]?.scrollIntoView({behavior: 'smooth', inline: 'center', block: 'nearest'});
        setActiveThumb(next);
    }));
    galleryTrack?.addEventListener('scroll', () => {
        const width = galleryButtons[0]?.getBoundingClientRect().width || 1;
        const index = Math.min(galleryButtons.length - 1, Math.max(0, Math.round(galleryTrack.scrollLeft/(width+2))));
        setActiveThumb(index);
    }, {passive:true});

    window.updateCartDrawer = async () => {
        const response = await fetch(location.href,{headers:{'Accept':'text/html'}});
        if (!response.ok) throw new Error('Unable to refresh the shopping bag.');
        const doc = new DOMParser().parseFromString(await response.text(),'text/html');
        const content = $('[data-bag-content]',doc), current = $('[data-bag-content]');
        if (content && current) current.replaceWith(content);
        const badge = $('[data-cart-badge]',doc);
        if (badge) $$('[data-cart-badge]').forEach(currentBadge => { currentBadge.textContent = badge.textContent; currentBadge.hidden = Number(badge.textContent) === 0; });
    };
    document.addEventListener('submit', async event => {
        const form = event.target;
        const isCart = new URL(form.action).pathname === '/cart/add';
        const isRemove = form.matches('[data-bag-remove]');
        const isNewsletter = form.matches('.rj-newsletter-form');
        if ((!isCart && !isRemove && !isNewsletter) || event.defaultPrevented) return;
        event.preventDefault();
        const button = $('button[type=submit]',form); if (form.dataset.submitting) return;
        form.dataset.submitting = 'true'; if (button) button.disabled = true;
        try {
            const body = new FormData(form);
            const response = await fetch(form.action,{method:'POST',body,headers:{'Accept':'application/json','X-CSRF-TOKEN':body.get('_token')}});
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || Object.values(data.errors || {}).flat()[0] || 'Please check your selections.');
            if (isNewsletter) { $('[data-form-status]',form).textContent = 'Thank you for subscribing.'; form.reset(); }
            else { await window.updateCartDrawer(); if (isCart) motion.openDialog($('#bag-dialog')); toast(isRemove ? 'Removed from bag.' : data.message); }
        } catch (error) { if (isNewsletter) $('[data-form-status]',form).textContent = error.message; else toast(error.message); }
        finally { delete form.dataset.submitting; if (button) button.disabled = false; }
    });
    // Product page narrative: the anchor row (and a visited hash) opens the
    // matching leaf before the browser smooth-scrolls down to it.
    const openProductSection = targetId => {
        const leaf = document.getElementById(targetId);
        if (leaf && leaf.matches('details') && !leaf.open) leaf.open = true;
    };
    document.addEventListener('click', event => {
        const link = event.target.closest('.rj-product__anchors a[href^="#"]');
        if (link) openProductSection(decodeURIComponent(link.getAttribute('href').slice(1)));
    });
    if (location.hash) openProductSection(decodeURIComponent(location.hash.slice(1)));
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded',ready); else ready();
