/* Shared GSAP presentation animations. Core GSAP only; no plugins required. */
(() => {
    'use strict';

    const gsap = window.gsap;
    const preference = window.matchMedia('(prefers-reduced-motion: reduce)');
    const active = new Map();
    const canAnimate = () => Boolean(gsap) && !preference.matches;

    function stop(key) {
        const animation = active.get(key);
        if (!animation) return;
        active.delete(key);
        animation.kill();
    }

    function play(key, create) {
        stop(key);
        const animation = create();
        const complete = animation.eventCallback('onComplete');
        active.set(key, animation);
        animation.eventCallback('onComplete', () => {
            if (active.get(key) === animation) active.delete(key);
            complete?.();
        });
        return animation;
    }

    // Finish in-flight interactions immediately if the OS preference changes.
    preference.addEventListener('change', () => {
        if (preference.matches) [...active.values()].forEach(animation => animation.totalProgress(1));
    });

    function clear(targets, properties = 'transform,opacity') {
        if (!targets) return;
        if (typeof targets === 'object' && 'length' in targets && targets.length === 0) return;
        if (gsap) gsap.set(targets, { clearProps: properties });
    }

    function dialogPanel(dialog) {
        return dialog.querySelector('.dialog__panel') || dialog;
    }

    function dialogContents(dialog) {
        return [...dialogPanel(dialog).children].filter(child => !child.classList.contains('dialog-close'));
    }

    function resetDialog(dialog) {
        stop(dialog);
        delete dialog.dataset.closing;
        clear(dialogPanel(dialog), 'transform,opacity,willChange');
        clear(dialogContents(dialog));
    }

    function openDialog(dialog) {
        if (!dialog || (dialog.open && !dialog.dataset.closing)) return;
        const opening = !dialog.open;
        stop(dialog);
        delete dialog.dataset.closing;
        if (opening) {
            resetDialog(dialog);
            dialog.showModal();
        }
        if (!canAnimate()) { resetDialog(dialog); return; }

        /* The dialog element itself stays in the top layer: GSAP must never
           measure it, because measuring a modal dialog re-parents it and drops
           it out of the top layer, which loses the backdrop and the outside
           click. The animation therefore runs on the panel inside it. */
        const panel = dialogPanel(dialog);
        const drawer = dialog.matches('.filter-dialog, .drawer-dialog');
        panel.style.willChange = 'transform, opacity';
        play(dialog, () => {
            const timeline = gsap.timeline({ onComplete: () => clear(panel, 'transform,opacity,willChange') });
            const target = { xPercent: 0, y: 0, scale: 1, opacity: 1, duration: drawer ? 0.4 : 0.28, ease: 'power3.out' };
            if (opening) {
                timeline.fromTo(panel, drawer ? { xPercent: 100, opacity: 1 } : { y: 18, scale: 0.98, opacity: 0 }, target, 0);
                timeline.fromTo(dialogContents(dialog), { y: 10, opacity: 0 }, {
                    y: 0, opacity: 1, duration: 0.24, stagger: 0.018,
                    ease: 'power2.out', clearProps: 'transform,opacity'
                }, drawer ? 0.1 : 0.04);
            } else {
                timeline.to(panel, target, 0);
            }
            return timeline;
        });
    }

    function closeDialog(dialog) {
        if (!dialog?.open || dialog.dataset.closing) return;
        stop(dialog);
        clear(dialogContents(dialog));
        const finish = () => {
            dialog.close();
            resetDialog(dialog);
        };
        if (!canAnimate()) { finish(); return; }
        dialog.dataset.closing = 'true';
        const drawer = dialog.matches('.filter-dialog, .drawer-dialog');
        play(dialog, () => gsap.to(dialogPanel(dialog), {
            ...(drawer ? { xPercent: 100 } : { y: 12, scale: 0.985, opacity: 0 }),
            duration: 0.24, ease: 'power2.in', onComplete: finish
        }));
    }

    function resetMenu(menu) {
        stop(menu);
        clear(menu, 'height,opacity,overflow,willChange,paddingTop,paddingBottom,borderBottomWidth');
        clear([...menu.children]);
    }

    function openMenu(menu) {
        const opening = menu.hidden;
        const startHeight = opening ? 0 : menu.getBoundingClientRect().height;
        const previous = window.getComputedStyle(menu);
        const startPaddingTop = opening ? 0 : parseFloat(previous.paddingTop);
        const startPaddingBottom = opening ? 0 : parseFloat(previous.paddingBottom);
        const startBorder = opening ? 0 : parseFloat(previous.borderBottomWidth);
        stop(menu);
        menu.hidden = false;
        menu.inert = false;
        if (!canAnimate()) { resetMenu(menu); return; }

        menu.style.height = '';
        menu.style.paddingTop = '';
        menu.style.paddingBottom = '';
        menu.style.borderBottomWidth = '';
        const height = menu.getBoundingClientRect().height;
        const natural = window.getComputedStyle(menu);
        const paddingTop = parseFloat(natural.paddingTop);
        const paddingBottom = parseFloat(natural.paddingBottom);
        const borderBottomWidth = parseFloat(natural.borderBottomWidth);
        menu.style.overflow = 'hidden';
        menu.style.willChange = 'height';
        play(menu, () => {
            const timeline = gsap.timeline({ onComplete: () => resetMenu(menu) });
            timeline.fromTo(menu, { height: startHeight, paddingTop: startPaddingTop, paddingBottom: startPaddingBottom, borderBottomWidth: startBorder }, {
                height, paddingTop, paddingBottom, borderBottomWidth, duration: 0.32, ease: 'power3.out'
            }, 0);
            if (opening) timeline.fromTo([...menu.children], { x: -10, opacity: 0 }, {
                x: 0, opacity: 1, duration: 0.2, stagger: 0.02,
                ease: 'power2.out', clearProps: 'transform,opacity'
            }, 0.04);
            return timeline;
        });
    }

    function closeMenu(menu, immediate = false) {
        if (menu.hidden) return;
        stop(menu);
        clear([...menu.children]);
        menu.inert = true;
        const finish = () => {
            menu.hidden = true;
            menu.inert = false;
            resetMenu(menu);
        };
        if (immediate || !canAnimate()) { finish(); return; }
        menu.style.overflow = 'hidden';
        play(menu, () => gsap.to(menu, { height: 0, paddingTop: 0, paddingBottom: 0, borderBottomWidth: 0, duration: 0.24, ease: 'power2.inOut', onComplete: finish }));
    }

    function toggleAccordion(details) {
        const expanding = details.dataset.expanded !== 'true';
        const opening = !details.open;
        const startHeight = details.getBoundingClientRect().height;
        const content = details.querySelector('.accordion-content');
        const icon = details.querySelector('summary > .icon');
        const rotation = icon && gsap ? gsap.getProperty(icon, 'rotation') : 0;
        stop(details);
        details.dataset.expanded = String(expanding);
        const finish = () => {
            details.open = expanding;
            clear(details, 'height,overflow');
            clear(content);
            if (icon) clear(icon);
        };
        if (!canAnimate()) { finish(); return; }

        details.open = true;
        details.style.height = '';
        const border = window.getComputedStyle(details);
        const endHeight = expanding ? details.getBoundingClientRect().height : details.querySelector('summary').getBoundingClientRect().height + parseFloat(border.borderTopWidth) + parseFloat(border.borderBottomWidth);
        details.style.overflow = 'hidden';
        play(details, () => {
            const timeline = gsap.timeline({ onComplete: finish });
            timeline.fromTo(details, { height: startHeight }, { height: endHeight, duration: 0.3, ease: 'power2.inOut' }, 0);
            const fade = { opacity: expanding ? 1 : 0, duration: 0.18, ease: 'power1.out' };
            if (expanding && opening) timeline.fromTo(content, { opacity: 0 }, fade, 0.08);
            else timeline.to(content, fade, expanding ? 0.08 : 0);
            if (icon) timeline.fromTo(icon, { rotation }, { rotation: expanding ? 45 : 0, duration: 0.25, ease: 'power2.inOut' }, 0);
            return timeline;
        });
    }

    function scrollBy(element, distance) {
        stop(element);
        const maximum = Math.max(0, element.scrollWidth - element.clientWidth);
        let target = Math.max(0, Math.min(maximum, element.scrollLeft + distance));
        const children = [...element.children].filter(child => !child.hidden);
        if (children.length) {
            const start = children[0].offsetLeft;
            const positions = [...children.map(child => Math.min(maximum, child.offsetLeft - start)), maximum];
            target = positions.reduce((nearest, position) => Math.abs(position - target) < Math.abs(nearest - target) ? position : nearest);
        }
        if (!canAnimate()) { element.scrollTo({ left: target, behavior: 'instant' }); return; }

        const snap = element.style.scrollSnapType;
        const behavior = element.style.scrollBehavior;
        const restore = () => { element.style.scrollSnapType = snap; element.style.scrollBehavior = behavior; };
        element.style.scrollSnapType = 'none';
        element.style.scrollBehavior = 'auto';
        play(element, () => gsap.to(element, {
            scrollLeft: target, duration: 0.45, ease: 'power2.inOut',
            onComplete: restore, onInterrupt: restore
        }));
    }

    function reveal(targets, { key = targets, y = 12, duration = 0.3, stagger = 0.025 } = {}) {
        const elements = Array.from(targets);
        stop(key);
        if (!canAnimate() || !elements.length) { clear(elements); return; }
        play(key, () => gsap.fromTo(elements, { opacity: 0, y }, {
            opacity: 1, y: 0, duration, stagger, ease: 'power2.out', clearProps: 'transform,opacity',
            onInterrupt: () => clear(elements)
        }));
    }

    function captureLayout(elements) {
        return new Map(elements.filter(element => !element.hidden).map(element => [element, element.getBoundingClientRect()]));
    }

    function reflow(container, elements, before) {
        stop(container);
        clear(elements);
        if (!canAnimate()) return;
        const visible = elements.filter(element => !element.hidden);
        if (!visible.length) return;
        play(container, () => {
            const timeline = gsap.timeline({ onInterrupt: () => clear(elements) });
            visible.forEach(element => {
                const previous = before.get(element);
                if (!previous) return;
                const current = element.getBoundingClientRect();
                timeline.fromTo(element, { x: previous.left - current.left, y: previous.top - current.top }, {
                    x: 0, y: 0, duration: 0.35, ease: 'power2.inOut', clearProps: 'transform,opacity'
                }, 0);
            });
            return timeline;
        });
    }

    function pulse(element) {
        if (!element) return;
        stop(element);
        if (!canAnimate()) { clear(element); return; }
        play(element, () => gsap.timeline({ onInterrupt: () => clear(element) }).to(element, { scale: 0.9, duration: 0.08, ease: 'power1.in' })
            .to(element, { scale: 1, duration: 0.18, ease: 'power2.out', clearProps: 'transform' }));
    }

    function showToast(element) {
        stop(element);
        element.hidden = false;
        if (!canAnimate()) { clear(element); return; }
        play(element, () => gsap.fromTo(element, { y: 12, opacity: 0 }, {
            y: 0, opacity: 1, duration: 0.22, ease: 'power2.out', clearProps: 'transform,opacity',
            onInterrupt: () => clear(element)
        }));
    }

    function hideToast(element) {
        stop(element);
        const finish = () => { element.hidden = true; clear(element); };
        if (!canAnimate()) { finish(); return; }
        play(element, () => gsap.to(element, { y: 8, opacity: 0, duration: 0.18, ease: 'power1.in', onComplete: finish }));
    }

    window.SiteMotion = {
        openDialog, closeDialog, resetDialog, openMenu, closeMenu, toggleAccordion,
        scrollBy, cancelScroll: stop, reveal, captureLayout, reflow, pulse, showToast, hideToast
    };
})();
