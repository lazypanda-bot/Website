// Quick Order Modal accessibility & body scroll lock helpers (moved from inline script in product-details.php)
document.addEventListener('DOMContentLoaded', function() {
  const modal = document.getElementById('quickOrderModal');
  if(!modal) return;
  function openModal(){ modal.hidden=false; modal.setAttribute('aria-hidden','false'); document.body.classList.add('modal-open'); }
  function closeModal(){ modal.hidden=true; modal.setAttribute('aria-hidden','true'); document.body.classList.remove('modal-open'); }
  window.openQuickOrderModal = openModal;
  window.closeQuickOrderModal = closeModal;
  document.addEventListener('keydown',e=>{ if(e.key==='Escape' && !modal.hidden) closeModal(); });
  modal.addEventListener('click',e=>{ if(e.target===modal) closeModal(); });
});
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
      price,
      total: price * quantity
    };
  }

  async function addToCart(product, { serverPreferred=false } = {}) {
    // If user authenticated try server cart first
    if (serverPreferred && window.isAuthenticated && product && product.id) {
      try {
        const formData = new FormData();
        formData.append('product_id', product.id);
        formData.append('size', product.size || 'Default');
        formData.append('color', product.color || 'Standard');
        formData.append('quantity', product.quantity || 1);
        const res = await fetch('add_to_cart.php', { method: 'POST', body: formData });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();
        if (data.status !== 'ok') throw new Error(data.message || 'Cart error');
        return { server:true, data };
      } catch (e) {
        console.warn('Server cart add failed, falling back to local storage:', e.message);
      }
    }
    // local storage fallback
    let cart = JSON.parse(localStorage.getItem('cart')) || [];
    cart.push(product);
    localStorage.setItem('cart', JSON.stringify(cart));
    return { server:false };
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
    addToCartBtn.addEventListener('click', async (e) => {
      e.preventDefault();
      const qtyInput = document.getElementById('quantity');
      const cartQtyInput = document.getElementById('cart_quantity');
      if (qtyInput && cartQtyInput) {
        cartQtyInput.value = qtyInput.value;
      }
      const product = getSelectedProduct();
      if (!product.quantity || product.quantity < 1) {
        alert('Quantity must be at least 1 to add to cart.');
        return;
      }
      // Attach product id from hidden form field
      const prodIdInput = document.querySelector('input[name="product_id"]');
      if (prodIdInput && prodIdInput.value) product.id = parseInt(prodIdInput.value,10);
      await addToCart(product, { serverPreferred:true });
      if (cartIcon) cartIcon.classList.add('active');
      showCartNotification();
      if (!window.isAuthenticated) {
        showLoginModal();
      }
    });
  }

  // Quick Order Modal Logic
  const quickOrderModal = document.getElementById('quickOrderModal');
  const quickOrderSummary = document.getElementById('quickOrderSummary');
  const quickOrderCancelBtn = document.getElementById('quickOrderCancelBtn');
  const quickOrderConfirmBtn = document.getElementById('quickOrderConfirmBtn');
  const closeQuickOrderModalBtn = document.getElementById('closeQuickOrderModalBtn');
  const profileWarn = document.getElementById('quickOrderProfileWarn');

  function openQuickOrderModal() { if(quickOrderModal) { quickOrderModal.hidden = false; quickOrderModal.style.display='flex'; } }
  function closeQuickOrderModal() { if(quickOrderModal) { quickOrderModal.hidden = true; quickOrderModal.style.display='none'; } }

  if (buyNowBtn) {
    buyNowBtn.addEventListener('click', (e) => {
      e.preventDefault();
      const product = getSelectedProduct();
      const errors = [];
      if (!product.quantity || product.quantity < 1) errors.push('Quantity must be at least 1.');
      if (!product.size || /select/i.test(product.size)) errors.push('Please choose a size.');
      const colorInputEl = document.getElementById('color');
      if (colorInputEl && !colorInputEl.value.trim()) errors.push('Please choose a color.');
      if (errors.length) { alert(errors.join('\n')); return; }
      const prodIdInput = document.querySelector('input[name="product_id"]');
      if (prodIdInput && prodIdInput.value) product.id = parseInt(prodIdInput.value,10);
      // Build summary
      if (quickOrderSummary) {
        quickOrderSummary.innerHTML = `
          <strong>Product:</strong> ${product.name || 'Item'}<br>
          <strong>Size:</strong> ${product.size}<br>
          <strong>Quantity:</strong> ${product.quantity}<br>
          <strong>Unit Price:</strong> ₱${product.price.toFixed(2)}<br>
          <strong>Total:</strong> ₱${product.total.toFixed(2)}
        `;
      }
      buyNowBtn.dataset.pendingProduct = JSON.stringify(product);
      if (!window.isAuthenticated) {
        // If not logged in, keep behavior: add locally & login modal
        addToCart(product, { serverPreferred:false }).then(()=>{ showLoginModal(); });
        return;
      }
      openQuickOrderModal();
    });
  }

  [quickOrderCancelBtn, closeQuickOrderModalBtn].forEach(btn=>{ if(btn){ btn.addEventListener('click', ()=>{ closeQuickOrderModal(); }); }});

  if (quickOrderConfirmBtn) {
    quickOrderConfirmBtn.addEventListener('click', async () => {
      if (!buyNowBtn || !buyNowBtn.dataset.pendingProduct) return;
      let product;
      try { product = JSON.parse(buyNowBtn.dataset.pendingProduct); } catch { return; }
      if (quickOrderConfirmBtn.disabled) return;
      quickOrderConfirmBtn.disabled = true;
      quickOrderConfirmBtn.textContent = 'Placing...';
      // Call quick_order.php
      try {
        const fd = new FormData();
  fd.append('product_id', product.id);
  fd.append('product_name', product.name || '');
  fd.append('size', product.size || 'Default');
  fd.append('quantity', product.quantity);
    const res = await fetch('quick_order.php', { method:'POST', body: fd });
    let dataText = await res.text();
    let data;
    try { data = JSON.parse(dataText); } catch { console.warn('Non-JSON response:', dataText); data = { status:'error', message:'Invalid server response', raw:dataText }; }
    console.log('[QuickOrder] response', data);
    if (data.status === 'need_profile') {
      profileWarn.style.display='block';
      quickOrderConfirmBtn.disabled = false;
      quickOrderConfirmBtn.textContent = 'Place Order';
      return;
    }
    if (data.status === 'duplicate') {
      alert(data.message || 'Duplicate pending order detected. Please wait then try again.');
      quickOrderConfirmBtn.disabled = false;
      quickOrderConfirmBtn.textContent = 'Place Order';
      return;
    }
    if (data.status !== 'ok') {
      alert((data.message || 'Order failed') + '\n(Enable debug by opening quick_order.php?debug=1 in a new tab)');
      quickOrderConfirmBtn.disabled = false;
      quickOrderConfirmBtn.textContent = 'Place Order';
      return;
    }
        // Success: redirect to profile orders panel
        window.location.href = data.redirect || 'profile.php?order=1#ordersPanel';
      } catch (e) {
        alert('Network error placing order.');
        quickOrderConfirmBtn.disabled = false;
        quickOrderConfirmBtn.textContent = 'Place Order';
      }
    });
  }
});

