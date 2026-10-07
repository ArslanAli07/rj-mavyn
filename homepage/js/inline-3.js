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
