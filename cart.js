document.addEventListener('DOMContentLoaded', () => {
  const cartItemsContainer = document.getElementById('cart-items');
  const cartSummary = document.getElementById('cart-summary');
  const checkoutBtn = document.querySelector('.checkout-btn');
  const cartIcon = document.getElementById('cart-icon');

  if (cartIcon) {
    cartIcon.classList.add('active');
  }

  const items = JSON.parse(localStorage.getItem('pendingCartItems') || '[]');
  let total = 0;

  if (items.length > 0) {
    cartItemsContainer.innerHTML = '';

    items.forEach(product => {
      const pricePerItem = parseFloat(product.price || '0');
      const subtotal = pricePerItem * product.quantity;
      total += subtotal;

      cartItemsContainer.innerHTML += `
        <div class="cart-item">
          <h4>${product.name}</h4>
          <p>Size: ${product.size}</p>
          <p>Quantity: ${product.quantity}</p>
          <p>Design: ${product.design}</p>
          <p>Price: ₱${pricePerItem.toFixed(2)} each</p>
          <p><strong>Subtotal: ₱${subtotal.toFixed(2)}</strong></p>
        </div>
      `;
    });

    cartSummary.querySelector('h3').textContent = `Total: ₱${total.toFixed(2)}`;
    checkoutBtn.disabled = false;

    localStorage.removeItem('pendingCartItems');
    sessionStorage.removeItem('justLoggedIn');
  } else {
    cartItemsContainer.innerHTML = `<p class="empty-cart-msg">Your cart is currently empty.</p>`;
    cartSummary.querySelector('h3').textContent = `Total: ₱0.00`;
    checkoutBtn.disabled = true;
  }
});
