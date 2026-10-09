document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.querySelector('.filter-toggle');
  const sidebar = document.querySelector('.shop-sidebar');
  const scrim = document.querySelector('.filter-scrim');
  const close = document.querySelector('.filter-close');
  const menu = document.querySelector('.menu-toggle');
  const nav = document.querySelector('.primary-nav');
  const setFilters = (open) => {
    if (!sidebar) return;
    sidebar.classList.toggle('is-open', open);
    scrim?.classList.toggle('is-open', open);
    toggle?.setAttribute('aria-expanded', String(open));
    document.body.classList.toggle('filters-open', open);
  };
  toggle?.addEventListener('click', () => setFilters(!sidebar.classList.contains('is-open')));
  close?.addEventListener('click', () => setFilters(false));
  scrim?.addEventListener('click', () => setFilters(false));
  menu?.addEventListener('click', () => {
    const open = !nav.classList.contains('is-open');
    nav.classList.toggle('is-open', open);
    menu.setAttribute('aria-expanded', String(open));
  });
});
