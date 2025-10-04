document.addEventListener('DOMContentLoaded', () => {
  const cartItemsContainer = document.getElementById('cart-items');
  const cartSummary = document.getElementById('cart-summary');
  const checkoutBtn = document.querySelector('.checkout-btn');
  const cartIcon = document.getElementById('cart-icon');

  if (cartIcon) {
    cartIcon.classList.add('active');
  }

  let items = JSON.parse(localStorage.getItem('cart') || '[]');
  let total = 0;

  // Group items by name, size, design, and price
  function getKey(product) {
    return [product.name, product.size, product.design, product.price].join('|');
  }
  const grouped = {};
  items.forEach(product => {
    const key = getKey(product);
    if (!grouped[key]) {
      grouped[key] = { ...product, quantity: 0 };
    }
    grouped[key].quantity += product.quantity;
  });
  const groupedItems = Object.values(grouped);

  function renderCart() {
    items = JSON.parse(localStorage.getItem('cart') || '[]');
    // regroup in case of deletion
    const grouped = {};
    items.forEach(product => {
      const key = getKey(product);
      if (!grouped[key]) {
        grouped[key] = { ...product, quantity: 0 };
      }
      grouped[key].quantity += product.quantity;
    });
    const groupedItems = Object.values(grouped);
    let total = 0;
    if (groupedItems.length > 0) {
      cartItemsContainer.innerHTML = '';
      cartItemsContainer.style.display = 'flex';
      cartItemsContainer.style.flexWrap = 'wrap';
      cartItemsContainer.style.gap = '20px';
      groupedItems.forEach((product, idx) => {
        const pricePerItem = parseFloat(product.price || '0');
        const subtotal = pricePerItem * product.quantity;
        total += subtotal;
        cartItemsContainer.innerHTML += `
          <div class="cart-item cart-item-card">
            <h4>${product.name}</h4>
            <p>Size: ${product.size}</p>
            <p>Quantity: ${product.quantity}</p>
            <p>Design: ${product.design}</p>
            <p>Price: ₱${pricePerItem.toFixed(2)} each</p>
            <p><strong>Subtotal: ₱${subtotal.toFixed(2)}</strong></p>
            <button class="delete-cart-item" data-key="${getKey(product)}" style="background:#9a4141;color:#fff;border:none;padding:6px 16px;border-radius:5px;cursor:pointer;margin-top:8px;"><i class="fa fa-trash"></i> Delete</button>
          </div>
        `;
      });
      cartSummary.querySelector('h3').textContent = `Total: ₱${total.toFixed(2)}`;
      checkoutBtn.disabled = false;
    } else {
      cartItemsContainer.innerHTML = `<p class=\"empty-cart-msg\">Your cart is currently empty.</p>`;
      cartItemsContainer.style.display = '';
      cartSummary.querySelector('h3').textContent = `Total: ₱0.00`;
      checkoutBtn.disabled = true;
    }

    // Add delete button logic
    document.querySelectorAll('.delete-cart-item').forEach(btn => {
      btn.addEventListener('click', function() {
        const key = this.getAttribute('data-key');
        // Remove all items with this key
        let items = JSON.parse(localStorage.getItem('cart') || '[]');
        items = items.filter(item => getKey(item) !== key);
        localStorage.setItem('cart', JSON.stringify(items));
        renderCart();
      });
    });
  }

  renderCart();
});
