

document.addEventListener('DOMContentLoaded', () => {
  // Update profile button handler
  const updateProfileBtn = document.getElementById('update-profile-btn');
  if (updateProfileBtn) {
    updateProfileBtn.addEventListener('click', function() {
      window.location.href = 'profile.php';
    });
  }
  const shippingFeeDiv = document.getElementById('shipping-fee');
  const cartItemsContainer = document.getElementById('cart-items');
  const cartSummary = document.getElementById('cart-summary');
  const checkoutBtn = document.querySelector('.checkout-btn');
  const cartIcon = document.getElementById('cart-icon');
  const checkoutForm = document.getElementById('checkout-form');
  const orderSummaryDiv = document.getElementById('order-summary');
  const deliveryAddressInput = document.getElementById('delivery_address');

  // Only run cart logic if cart container exists
  if (!cartItemsContainer || !cartSummary) return;
  const deliveryPhoneInput = document.getElementById('delivery_phone');
  const profileMissingInfo = document.getElementById('profile-missing-info');

  if (cartIcon) {
    cartIcon.classList.add('active');
  }

  // Determine if we use DB-backed cart (presence of data-source attribute)
  const useDb = cartItemsContainer && cartItemsContainer.getAttribute('data-source') === 'db' && window.isAuthenticated;
  let items = [];
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

  async function fetchDbCart() {
    try {
      const res = await fetch('cart_items.php');
      if (!res.ok) throw new Error('Failed to fetch cart');
      const data = await res.json();
      items = Array.isArray(data.items) ? data.items : [];
    } catch (e) {
      console.error(e);
      items = [];
    }
  }

  async function renderCart() {
    if (useDb) {
      await fetchDbCart();
    } else {
      items = JSON.parse(localStorage.getItem('cart') || '[]');
    }
    // regroup in case of deletion
    const grouped = {};
    items.forEach(product => {
      const key = getKey(product);
      if (!grouped[key]) {
        grouped[key] = { ...product, quantity: 0 };
      }
      grouped[key].quantity += parseInt(product.quantity, 10);
    });
    const groupedItems = Object.values(grouped);
  let total = 0;
  let shippingFee = 0;
  if (groupedItems.length > 0) {
      if (cartItemsContainer) {
        cartItemsContainer.innerHTML = '';
        cartItemsContainer.style.display = 'flex';
        cartItemsContainer.style.flexWrap = 'wrap';
        cartItemsContainer.style.gap = '20px';
      }
      let summaryHtml = '';
      groupedItems.forEach((product, idx) => {
        const pricePerItem = parseFloat(product.price || '0');
        const subtotal = pricePerItem * product.quantity;
        total += subtotal;
        if (cartItemsContainer) {
          cartItemsContainer.innerHTML += `
            <div class="cart-item cart-item-card">
              <h4>${product.name}</h4>
              <p>Size: ${product.size}</p>
              <p>Quantity: ${product.quantity}</p>
              <p>Design: ${product.design}</p>
              <p>Price: ₱${pricePerItem.toFixed(2)} each</p>
              <p><strong>Subtotal: ₱${subtotal.toFixed(2)}</strong></p>
              <button class="delete-cart-item" data-key="${getKey(product)}" data-id="${product.id || ''}" style="background:#9a4141;color:#fff;border:none;padding:6px 16px;border-radius:5px;cursor:pointer;margin-top:8px;"><i class="fa fa-trash"></i> Delete</button>
            </div>
          `;
        }
        summaryHtml += `<div style="margin-bottom:8px;"><strong>${product.name}</strong> (${product.size}) x${product.quantity} - ₱${subtotal.toFixed(2)}</div>`;
      });
      if (cartSummary && cartSummary.querySelector('h3')) cartSummary.querySelector('h3').textContent = `Total: ₱${total.toFixed(2)}`;
      if (checkoutBtn) checkoutBtn.disabled = false;
      if (orderSummaryDiv) {
        orderSummaryDiv.innerHTML = summaryHtml + `<div style='margin-top:10px;font-weight:bold;'>Subtotal: ₱${total.toFixed(2)}</div>`;
      }
      // Set initial shipping fee display
      if (shippingFeeDiv) {
        shippingFeeDiv.textContent = '';
      }
    } else {
      if (cartItemsContainer) {
        cartItemsContainer.innerHTML = `<p class=\"empty-cart-msg\">Your cart is currently empty.</p>`;
        cartItemsContainer.style.display = '';
      }
      if (cartSummary && cartSummary.querySelector('h3')) cartSummary.querySelector('h3').textContent = `Total: ₱0.00`;
      if (checkoutBtn) checkoutBtn.disabled = true;
      if (orderSummaryDiv) orderSummaryDiv.innerHTML = '';
    }

    // Add delete button logic
    document.querySelectorAll('.delete-cart-item').forEach(btn => {
      btn.addEventListener('click', async function() {
        if (useDb && this.dataset.id) {
          try {
            const formData = new FormData();
            formData.append('id', this.dataset.id);
            await fetch('delete_cart_item.php', { method: 'POST', body: formData });
          } catch (e) { console.error(e); }
          renderCart();
          return;
        }
        const key = this.getAttribute('data-key');
        let locItems = JSON.parse(localStorage.getItem('cart') || '[]');
        locItems = locItems.filter(item => getKey(item) !== key);
        localStorage.setItem('cart', JSON.stringify(locItems));
        renderCart();
  // Listen for delivery method change to update shipping fee
  function updateShippingFee() {
    let shippingFee = 0;
    const deliveryMethod = checkoutForm.querySelector('input[name="delivery_method"]:checked');
    if (deliveryMethod && deliveryMethod.value === 'standard') {
      shippingFee = 25;
    }
    if (shippingFeeDiv) {
      shippingFeeDiv.textContent = `Shipping Fee: ₱${shippingFee}`;
    }
    // Update total in summary
    let subtotal = 0;
    const grouped = {};
    let items = JSON.parse(localStorage.getItem('cart') || '[]');
    items.forEach(product => {
      const key = getKey(product);
      if (!grouped[key]) {
        grouped[key] = { ...product, quantity: 0 };
      }
      grouped[key].quantity += product.quantity;
    });
    const groupedItems = Object.values(grouped);
    groupedItems.forEach(product => {
      subtotal += parseFloat(product.price || '0') * product.quantity;
    });
    if (orderSummaryDiv) {
      orderSummaryDiv.innerHTML = groupedItems.map(product => `<div style="margin-bottom:8px;"><strong>${product.name}</strong> (${product.size}) x${product.quantity} - ₱${(parseFloat(product.price || '0') * product.quantity).toFixed(2)}</div>`).join('') + `<div style='margin-top:10px;font-weight:bold;'>Subtotal: ₱${subtotal.toFixed(2)}</div><div style='margin-top:5px;font-weight:bold;'>Shipping Fee: ₱${shippingFee}</div><div style='margin-top:5px;font-weight:bold;'>Total: ₱${(subtotal + shippingFee).toFixed(2)}</div>`;
    }
  }
  // Attach event listeners to delivery method radios
  const deliveryRadios = checkoutForm.querySelectorAll('input[name="delivery_method"]');
  deliveryRadios.forEach(radio => {
    radio.addEventListener('change', updateShippingFee);
  });
  // Initial call in case default is selected
  updateShippingFee();
      });
    });
  }

  renderCart();

  // Autofill address/phone from user profile if available
  if (checkoutForm && typeof window.userAddress !== 'undefined' && typeof window.userPhone !== 'undefined') {
    if (window.userAddress && window.userPhone) {
      deliveryAddressInput.value = window.userAddress;
      deliveryPhoneInput.value = window.userPhone;
      if (profileMissingInfo) profileMissingInfo.classList.remove('show');
      deliveryAddressInput.readOnly = true;
      deliveryPhoneInput.readOnly = true;
      checkoutForm.querySelector('button[type="submit"]').disabled = false;
    } else {
      // Show prompt and disable checkout
      if (profileMissingInfo) profileMissingInfo.classList.add('show');
      deliveryAddressInput.value = '';
      deliveryPhoneInput.value = '';
      deliveryAddressInput.readOnly = true;
      deliveryPhoneInput.readOnly = true;
      checkoutForm.querySelector('button[type="submit"]').disabled = true;
    }
  }

  // Checkout form validation and submission
  if (checkoutForm) {
    checkoutForm.addEventListener('submit', function(e) {
      e.preventDefault();
      // Validate delivery address
      const address = deliveryAddressInput.value.trim();
      const phone = deliveryPhoneInput.value.trim();
      if (!address) {
        alert('Please enter your delivery address.');
        deliveryAddressInput.focus();
        return;
      }
      if (!phone) {
        alert('Please enter your phone number.');
        deliveryPhoneInput.focus();
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
      alert('Order placed!\n\nDelivery Address: ' + address + '\nPhone: ' + phone + '\nDelivery Method: ' + deliveryMethod.value + '\nPayment Method: ' + paymentMethod.value + '\n\nThank you for your order!');
      // Optionally clear cart
      localStorage.removeItem('cart');
      renderCart();
      checkoutForm.reset();
      checkoutForm.style.display = 'none';
    });
  }
});
