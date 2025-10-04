// Avatar Preview
const avatarInput = document.getElementById('avatarInput');
const avatarPreview = document.getElementById('avatarPreview');

avatarInput.addEventListener('change', function () {
  const file = this.files[0];
  if (file) {
    const reader = new FileReader();
    reader.onload = function (e) {
      avatarPreview.src = e.target.result;
    };
    reader.readAsDataURL(file);
  }
});

// Tab Switching Logic
const tabLinks = document.querySelectorAll('.settings-nav a');
const tabSections = document.querySelectorAll('.settings-tab');

tabLinks.forEach(link => {
  link.addEventListener('click', e => {
    e.preventDefault();
    const targetTab = link.getAttribute('data-tab');

    // Remove active classes
    tabLinks.forEach(l => l.classList.remove('active'));
    tabSections.forEach(s => s.classList.remove('active'));

    // Activate clicked tab and matching section
    link.classList.add('active');
    document.getElementById(targetTab).classList.add('active');
  });
});

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