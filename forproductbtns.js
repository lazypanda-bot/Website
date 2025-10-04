document.addEventListener('DOMContentLoaded', () => {
  // const buyNowBtn = document.querySelector('.buy-btn');
  // const addToCartBtn = document.querySelector('.addcart-btn');
  // const cartIcon = document.querySelector('.cart-icon i');
  // const modal = document.getElementById('auth-modal');

  // function getSelectedProduct() {
  //   return {
  //     name: document.getElementById('product-name')?.value || '',
  //     size: document.getElementById('size')?.value || '',
  //     quantity: parseInt(document.getElementById('quantity')?.value || '0'),
  //     design: document.getElementById('design-option')?.value || ''
  //   };
  // }

  // function storeCartData(action) {
  //   localStorage.setItem('pendingCartItem', JSON.stringify(getSelectedProduct()));
  //   localStorage.setItem('pendingCartAction', action); // 'buy' or 'add'
  //   sessionStorage.setItem('justLoggedIn', 'true');
  // }

  // function restoreCartData() {
  //   const justLoggedIn = sessionStorage.getItem('justLoggedIn');
  //   if (!justLoggedIn) return;

  //   const item = localStorage.getItem('pendingCartItem');
  //   const action = localStorage.getItem('pendingCartAction');

  //   if (item) {
  //     const product = JSON.parse(item);
  //     console.log('🛒 Restored cart item:', product);

  //     if (action === 'buy') {
  //       window.location.href = 'cart.php';
  //     } else {
  //       if (cartIcon) cartIcon.classList.add('active');
  //       alert('✅ Added to cart!');
  //     }

  //     localStorage.removeItem('pendingCartItem');
  //     localStorage.removeItem('pendingCartAction');
  //     sessionStorage.removeItem('justLoggedIn');
  //   }
  // }

  // function openLoginModal(redirectPath, action) {
  //   sessionStorage.setItem('redirect_after_auth', redirectPath);
  //   storeCartData(action);
  //   if (modal) modal.classList.add('active');
  // }

  // if (addToCartBtn) {
  //   addToCartBtn.addEventListener('click', (e) => {
  //     e.preventDefault();
  //     if (window.isAuthenticated) {
  //       console.log('🛒 Item added to cart');
  //       if (cartIcon) cartIcon.classList.add('active');
  //       alert('✅ Added to cart!');
  //     } else {
  //       openLoginModal(window.location.href, 'add');
  //     }
  //   });
  // }

  // if (buyNowBtn) {
  //   buyNowBtn.addEventListener('click', (e) => {
  //     e.preventDefault();
  //     if (window.isAuthenticated) {
  //       console.log('🛒 Item added and redirecting to cart');
  //       window.location.href = 'cart.php';
  //     } else {
  //       openLoginModal('cart.php', 'buy');
  //     }
  //   });
  // }

  // restoreCartData();

  function getSelectedProduct() {
  return {
    name: document.getElementById('product-name')?.value || '',
    size: document.getElementById('size')?.value || '',
    quantity: parseInt(document.getElementById('quantity')?.value || '1'),
    design: document.getElementById('design-option')?.value || '',
    price: parseFloat(document.getElementById('product-price')?.value || '0')
  };
}

function storeCartItem(product) {
  const existing = JSON.parse(localStorage.getItem('pendingCartItems') || '[]');
  existing.push(product);
  localStorage.setItem('pendingCartItems', JSON.stringify(existing));
}

});

