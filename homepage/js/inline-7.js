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
