window.addEventListener('DOMContentLoaded', () => {
  const currentPage = window.location.pathname.split('/').pop(); // e.g. "admin-orders.html"

  document.querySelectorAll('.nav-links a').forEach(link => {
    const href = link.getAttribute('href');
    if (!href || href === '#') return;

    const linkPage = href.split('/').pop().split('#')[0];

    if (currentPage === linkPage) {
      link.classList.add('active');
    }
  });
});
