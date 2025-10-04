document.addEventListener('DOMContentLoaded', () => {
  const serviceItems = document.querySelectorAll('.service-item');

  serviceItems.forEach(item => {
    item.addEventListener('click', () => {
      const wrapper = item.closest('.service-item-wrapper');
      wrapper.classList.toggle('active');
    });
  });
});

const currentPath = window.location.pathname.toLowerCase();
  const navLinks = document.querySelectorAll('.desktop-nav .nav-link');

  navLinks.forEach(link => {
    const href = link.getAttribute('href').toLowerCase();

    // Highlight exact match
    if (currentPath.endsWith(href)) {
      link.classList.add('active');
    }

    // Highlight Products link on subpages like shirt.html
    if (
      href === 'products.php' &&
      (currentPath.includes('shirt.html') || currentPath.includes('hoodie.html') || currentPath.includes('mugs.html'))
    ) {
      link.classList.add('active');
    }
  });