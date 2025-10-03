const navLinks = document.querySelectorAll('.nav-links a');
const contentBoxes = document.querySelectorAll('.content-box');

navLinks.forEach(link => {
    link.addEventListener('click', e => {
      e.preventDefault();

      // Remove active from all links and boxes
      navLinks.forEach(l => l.classList.remove('active'));
      contentBoxes.forEach(box => box.classList.remove('active'));

      // Add active to clicked link and show its content
      link.classList.add('active');
      const targetId = link.getAttribute('data-target');
      document.getElementById(targetId).classList.add('active');
    });
  });

  //Show first section by default
  navLinks[0].classList.add('active');
  contentBoxes[0].classList.add('active');

  window.addEventListener('DOMContentLoaded', () => {
  const hash = window.location.hash.replace('#', '') || 'tarpaulin';
  const target = document.getElementById(hash);

  // Remove hash to prevent browser auto-scroll
  history.replaceState(null, '', window.location.pathname);

  // Remove all active states
  document.querySelectorAll('.content-box').forEach(box => box.classList.remove('active'));
  document.querySelectorAll('.nav-links a').forEach(link => link.classList.remove('active'));

  // Activate matching content box and sidebar link
  if (target) {
    target.classList.add('active');
    const activeLink = document.querySelector(`.nav-links a[data-target="${hash}"]`);
    if (activeLink) activeLink.classList.add('active');

    // ✅ Manually scroll to top of page
    window.scrollTo({ top: 0, behavior: 'instant' });
  }
});