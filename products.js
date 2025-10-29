const navLinks = document.querySelectorAll('.nav-links a');
const contentBoxes = document.querySelectorAll('.content-box');

navLinks.forEach(link => {
    link.addEventListener('click', e => {
        e.preventDefault();

        // remove active from all links and boxes
        navLinks.forEach(l => l.classList.remove('active'));
        contentBoxes.forEach(box => box.classList.remove('active'));

        // add active to clicked link and show its content
        link.classList.add('active');
        const targetId = link.getAttribute('data-target');
        document.getElementById(targetId).classList.add('active');
    });
});

// Defer initial activation until DOMContentLoaded so categories rendered server-side are in place
window.addEventListener('DOMContentLoaded', () => {
    if (!navLinks.length || !contentBoxes.length) return; // safety guard

    const rawHash = window.location.hash.replace('#','').trim();
    const hasValidHash = rawHash !== '' && document.getElementById(rawHash);

    // clear any pre-existing active states just in case
    navLinks.forEach(l => l.classList.remove('active'));
    contentBoxes.forEach(b => b.classList.remove('active'));

    if (hasValidHash) {
        // activate section referenced by hash
        document.getElementById(rawHash).classList.add('active');
        const activeLink = document.querySelector(`.nav-links a[data-target="${rawHash}"]`);
        if (activeLink) activeLink.classList.add('active');
        history.replaceState(null,'',window.location.pathname); // remove hash to prevent jump
    } else {
        // fallback: first category highlighted & shown
        navLinks[0].classList.add('active');
        contentBoxes[0].classList.add('active');
    }

    // Inline JS logic from products-inline-cleanup.js
    // Back button
    var backBtn = document.getElementById('backBtn');
    if (backBtn) {
        backBtn.addEventListener('click', function() {
            history.back();
        });
    }
    // Prevent default search form submit
    var searchForm = document.getElementById('productSearchForm');
    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            e.preventDefault();
        });
    }
});