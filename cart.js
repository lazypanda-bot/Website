document.addEventListener('DOMContentLoaded', () => {
  const cartItemsContainer = document.getElementById('cart-items');
  const cartSummary = document.getElementById('cart-summary');
  const checkoutBtn = document.querySelector('.checkout-btn');
  const cartIcon = document.getElementById('cart-icon');
  const checkoutForm = document.getElementById('checkout-form');
  const orderSummaryDiv = document.getElementById('order-summary');
  const deliveryAddressInput = document.getElementById('delivery_address');

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
      let summaryHtml = '';
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
        summaryHtml += `<div style="margin-bottom:8px;"><strong>${product.name}</strong> (${product.size}) x${product.quantity} - ₱${subtotal.toFixed(2)}</div>`;
      });
      cartSummary.querySelector('h3').textContent = `Total: ₱${total.toFixed(2)}`;
      checkoutBtn.disabled = false;
      if (orderSummaryDiv) {
        orderSummaryDiv.innerHTML = summaryHtml + `<div style='margin-top:10px;font-weight:bold;'>Total: ₱${total.toFixed(2)}</div>`;
      }
    } else {
      cartItemsContainer.innerHTML = `<p class=\"empty-cart-msg\">Your cart is currently empty.</p>`;
      cartItemsContainer.style.display = '';
      cartSummary.querySelector('h3').textContent = `Total: ₱0.00`;
      checkoutBtn.disabled = true;
      if (orderSummaryDiv) orderSummaryDiv.innerHTML = '';
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

  // Checkout form validation and submission
  if (checkoutForm) {
    checkoutForm.addEventListener('submit', function(e) {
      e.preventDefault();
      // Validate delivery address
      const address = deliveryAddressInput.value.trim();
      if (!address) {
        alert('Please enter your delivery address.');
        deliveryAddressInput.focus();
        return;
      }
      // Validate delivery method
      const deliveryMethod = checkoutForm.querySelector('input[name="delivery_method"]:checked');
      if (!deliveryMethod) {
        alert('Please select a delivery method.');
        return;
      }
      // Validate payment method
      const paymentMethod = checkoutForm.querySelector('input[name="payment_method"]:checked');
      if (!paymentMethod) {
        alert('Please select a payment method.');
        return;
      }
      // Show order summary (simulate order placement)
      alert('Order placed!\n\nDelivery Address: ' + address + '\nDelivery Method: ' + deliveryMethod.value + '\nPayment Method: ' + paymentMethod.value + '\n\nThank you for your order!');
      // Optionally clear cart
      localStorage.removeItem('cart');
      renderCart();
      checkoutForm.reset();
      checkoutForm.style.display = 'none';
    });
  }
});
