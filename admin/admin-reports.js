window.addEventListener('DOMContentLoaded', () => {
  const currentPage = window.location.pathname.split('/').pop();

  document.querySelectorAll('.nav-links a').forEach(link => {
    const href = link.getAttribute('href');
    if (!href || href === '#') return;

    const linkPage = href.split('/').pop().split('#')[0];

    if (currentPage === linkPage) {
      link.classList.add('active');
    }
  });
});
