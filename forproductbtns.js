document.addEventListener('DOMContentLoaded', () => {

  const buyNowBtn = document.querySelector('.buy-btn');
  const addToCartBtn = document.querySelector('.addcart-btn');
  const cartIcon = document.querySelector('.cart-icon i');

  function getSelectedProduct() {
    // Get product name
    let name = document.getElementById('product-name')?.value || '';
    // Get size
    let size = document.getElementById('size')?.value || '';
    // Get quantity
    let quantity = parseInt(document.getElementById('quantity')?.value || '1');
    // Get design
    let design = document.getElementById('design-option')?.value || '';
    // Get price from the price box (not from a hidden input)
    let priceText = document.querySelector('.price-box')?.textContent || '';
    let price = 0;
    // Extract numeric value from text like '₱150'
    let match = priceText.match(/([\d,.]+)/);
    if (match) {
      price = parseFloat(match[1].replace(/,/g, ''));
    }
    return {
      name,
      size,
      quantity,
      design,
      price
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
      const qtyInput = document.getElementById('quantity');
      const cartQtyInput = document.getElementById('cart_quantity');
      if (qtyInput && cartQtyInput) {
        cartQtyInput.value = qtyInput.value;
      }
      if (window.isAuthenticated) {
        const product = getSelectedProduct();
        if (!product.quantity || product.quantity < 1) {
          alert('Quantity must be at least 1 to add to cart.');
          return;
        }
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
        if (!product.quantity || product.quantity < 1) {
          alert('Quantity must be at least 1 to order.');
          return;
        }
        addToCart(product);
        window.location.href = 'cart.php';
      } else {
        showLoginModal();
      }
    });
  }
});

