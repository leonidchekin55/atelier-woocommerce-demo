document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.querySelector('.filter-toggle');
  const sidebar = document.querySelector('.shop-sidebar');
  const scrim = document.querySelector('.filter-scrim');
  const close = document.querySelector('.filter-close');
  const menu = document.querySelector('.menu-toggle');
  const nav = document.querySelector('.primary-nav');
  const mobileFilters = window.matchMedia('(max-width: 900px)');
  const mobileMenu = window.matchMedia('(max-width: 620px)');
  const background = [...document.querySelectorAll('.site-header, .site-footer, .shop-main')];
  const setFilters = (open) => {
    if (!sidebar) return;
    sidebar.classList.toggle('is-open', open);
    scrim?.classList.toggle('is-open', open);
    toggle?.setAttribute('aria-expanded', String(open));
    document.body.classList.toggle('filters-open', open);
    if (open) {
      sidebar.setAttribute('role', 'dialog');
      sidebar.setAttribute('aria-modal', 'true');
      sidebar.setAttribute('aria-label', 'Filter products');
      background.forEach(element => { element.inert = true; });
      sidebar.querySelector('input, button, select')?.focus();
    }
    else {
      sidebar.removeAttribute('role');
      sidebar.removeAttribute('aria-modal');
      sidebar.removeAttribute('aria-label');
      background.forEach(element => { element.inert = false; });
      toggle?.focus();
    }
  };
  toggle?.addEventListener('click', () => setFilters(!sidebar.classList.contains('is-open')));
  close?.addEventListener('click', () => setFilters(false));
  scrim?.addEventListener('click', () => setFilters(false));
  document.addEventListener('keydown', (event) => {
    if (sidebar?.classList.contains('is-open')) {
      if (event.key === 'Escape') setFilters(false);
      if (event.key === 'Tab') {
        const focusable = [...sidebar.querySelectorAll('a[href], button, input, select, textarea')]
          .filter(element => !element.disabled && element.getClientRects().length);
        const first = focusable[0], last = focusable.at(-1);
        if (event.shiftKey && document.activeElement === first) {
          event.preventDefault(); last?.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
          event.preventDefault(); first?.focus();
        }
      }
    }
    if (event.key === 'Escape' && nav?.classList.contains('is-open')) {
      setMenu(false); menu?.focus();
    }
  });
  const setMenu = (open) => {
    nav?.classList.toggle('is-open', open);
    menu?.setAttribute('aria-expanded', String(open));
    menu?.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
  };
  menu?.addEventListener('click', () => {
    setMenu(!nav?.classList.contains('is-open'));
  });
  document.addEventListener('click', event => {
    if (!nav?.contains(event.target) && !menu?.contains(event.target)) setMenu(false);
  });
  nav?.addEventListener('click', event => {
    if (event.target.closest('a')) setMenu(false);
  });
  mobileMenu.addEventListener('change', () => setMenu(false));
  mobileFilters.addEventListener('change', () => {
    if (sidebar?.classList.contains('is-open')) setFilters(false);
  });
});
