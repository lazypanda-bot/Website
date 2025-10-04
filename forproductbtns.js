document.addEventListener('DOMContentLoaded', () => {

  const buyNowBtn = document.querySelector('.buy-btn');
  const addToCartBtn = document.querySelector('.addcart-btn');
  const cartIcon = document.querySelector('.cart-icon i');

  function getSelectedProduct() {
    return {
      name: document.getElementById('product-name')?.value || '',
      size: document.getElementById('size')?.value || '',
      quantity: parseInt(document.getElementById('quantity')?.value || '1'),
      design: document.getElementById('design-option')?.value || '',
      price: parseFloat(document.getElementById('product-price')?.value || '0')
    };
  }

  function addToCart(product) {
    let cart = JSON.parse(localStorage.getItem('cart')) || [];
    cart.push(product);
    localStorage.setItem('cart', JSON.stringify(cart));
  }

  function showLoginModal() {
  // Always set redirect to current page (product-details.php)
    const redirectPath = window.location.pathname + window.location.search + window.location.hash;
    // Wait for window.openLoginModal to be available
    function tryShowModal(retries = 10) {
      if (typeof window.openLoginModal === 'function') {
        window.openLoginModal(redirectPath);
      } else if (retries > 0) {
        setTimeout(() => tryShowModal(retries - 1), 100);
      } else {
        alert('Please log in.');
      }
    }
    tryShowModal();
  }

  function showCartNotification() {
    const notif = document.getElementById('cart-notification');
    if (notif) {
      notif.style.display = 'block';
      notif.style.opacity = '1';
      setTimeout(() => {
        notif.style.opacity = '0';
        setTimeout(() => { notif.style.display = 'none'; }, 400);
      }, 1800);
    }
  }

  if (addToCartBtn) {
    addToCartBtn.addEventListener('click', (e) => {
      e.preventDefault();
      if (window.isAuthenticated) {
        const product = getSelectedProduct();
        addToCart(product);
        if (cartIcon) cartIcon.classList.add('active');
        showCartNotification();
      } else {
        showLoginModal();
      }
    });
  }

  if (buyNowBtn) {
    buyNowBtn.addEventListener('click', (e) => {
      e.preventDefault();
      if (window.isAuthenticated) {
        const product = getSelectedProduct();
        addToCart(product);
        window.location.href = 'cart.php';
      } else {
        showLoginModal();
      }
    });
  }
});

