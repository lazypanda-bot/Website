// Bootstrap fallback – ensures window.cartBootstrap exists
(function(){ if(!window.cartBootstrap){ window.cartBootstrap = {}; } })();

document.addEventListener('DOMContentLoaded', () => {
    if(window.cartBootstrap){
        Object.assign(window, window.cartBootstrap);
  }

  // Back button action (previously inline onclick)
  document.querySelectorAll('[data-action="go-back"]').forEach(btn=>{
      btn.addEventListener('click', e=>{ e.preventDefault(); history.back(); });
  });

  // Show checkout form when clicking checkout button (removing inline onclick / style)
  const checkoutBtnEl = document.querySelector('.checkout-btn');
  const checkoutFormEl = document.getElementById('checkout-form');
  if(checkoutBtnEl && checkoutFormEl){
      checkoutBtnEl.addEventListener('click', ()=>{
          checkoutFormEl.classList.remove('is-hidden');
    });
  }

  // If the page is loaded with an explicit checkout request, show checkout form
  try {
      const url = new URL(window.location.href);
      const hasHashCheckout = window.location.hash && window.location.hash.toLowerCase().includes('checkout');
      const hasQueryCheckout = url.searchParams.get('checkout') === '1';
      if ((hasHashCheckout || hasQueryCheckout) && checkoutFormEl) {
          // ensure cart summary and form are visible
          checkoutFormEl.classList.remove('is-hidden');
          checkoutFormEl.style.display = '';
          if (checkoutBtnEl) checkoutBtnEl.disabled = false;
          // scroll to checkout form for better UX
          setTimeout(()=>{ try { checkoutFormEl.scrollIntoView({behavior:'smooth', block:'center'}); } catch(e){} }, 120);
      }
  } catch(e) { /* ignore URL parse errors */ }

  // Load animation class moved from inline script
  window.addEventListener('load', ()=> document.body.classList.add('loaded'));
  // Profile shortcut button
  const updateProfileBtn = document.getElementById('update-profile-btn');
  if (updateProfileBtn) updateProfileBtn.addEventListener('click', () => window.location.href='profile.php');

  // Removed standalone shipping fee display; fee now shown only inside order summary box
  const shippingFeeDiv       = null; // document.getElementById('shipping-fee');
  const cartItemsContainer   = document.getElementById('cart-items');
  const cartSummary          = document.getElementById('cart-summary');
  const checkoutBtn          = document.querySelector('.checkout-btn');
  const cartIcon             = document.getElementById('cart-icon');
  const checkoutForm         = document.getElementById('checkout-form');
  const orderSummaryDiv      = document.getElementById('order-summary');
  const deliveryAddressInput = document.getElementById('delivery_address');
  const deliveryPhoneInput   = document.getElementById('delivery_phone');
  const profileMissingInfo   = document.getElementById('profile-missing-info');

  if (!cartItemsContainer || !cartSummary) return; // nothing to do
  if (cartIcon) cartIcon.classList.add('active');

  const useDb = cartItemsContainer.getAttribute('data-source') === 'db' && window.isAuthenticated;
  let items = [];

  function getKey(p){ return [p.name,p.size,p.design,p.price].join('|'); }

  async function fetchDbCart(){
    try {
        const res = await fetch('cart_items.php');
        if(!res.ok) throw new Error('Fetch failed');
        const data = await res.json();
        items = Array.isArray(data.items)? data.items : [];
    } catch(err){ console.error(err); items=[]; }
  }

  function groupLocalItems(raw){
    const map={};
    raw.forEach(it=>{ const k=getKey(it); if(!map[k]) map[k]={...it,quantity:0}; map[k].quantity += parseInt(it.quantity,10); });
    return Object.values(map);
  }

  function hideCheckoutIfEmpty(isEmpty){
    if(isEmpty) {
        if(cartSummary) cartSummary.style.display='none';
        if(checkoutForm) checkoutForm.style.display='none';
    } else {
        if(cartSummary) cartSummary.style.display='flex';
    }
  }

  async function renderCart(){
    if(useDb) await fetchDbCart(); else items = JSON.parse(localStorage.getItem('cart')||'[]');
    const grouped = useDb ? items.slice() : groupLocalItems(items);
    if(grouped.length===0) {
        if(cartItemsContainer){ cartItemsContainer.innerHTML = '<p class="empty-cart-msg">Your cart is currently empty.</p>'; cartItemsContainer.style.display=''; }
        if(orderSummaryDiv) orderSummaryDiv.innerHTML='';
        if(cartSummary && cartSummary.querySelector('h3')) cartSummary.querySelector('h3').textContent='Total: ₱0.00';
        if(checkoutBtn) checkoutBtn.disabled=true;
        hideCheckoutIfEmpty(true);
        return;
    }

    hideCheckoutIfEmpty(false);
    if(cartItemsContainer) {
        cartItemsContainer.innerHTML='';
        cartItemsContainer.style.display='flex';
        cartItemsContainer.style.flexWrap='wrap';
        cartItemsContainer.style.gap='20px';
    }

    let subtotal=0;
    grouped.forEach((p,idx)=>{
        const price = parseFloat(p.price||0);
        const line  = price * p.quantity;
        subtotal += line;
            if(cartItemsContainer){
            const idAttr = p.id ? `data-id="${p.id}"` : '';
            cartItemsContainer.innerHTML += `
            <div class="cart-item cart-item-card" data-line-index="${idx}">
                <label class="cart-item-label">
                    <input type="checkbox" class="select-cart-item" checked data-key="${getKey(p)}" ${idAttr} />
                    <strong>${p.name}</strong>
                </label>
                <p>Size: ${p.size||''}</p>
                <p>Design: ${p.design||''}</p>
                <div class="qty-row">
                    <label for="qty_${idx}" class="qty-label">QTY</label>
                    <input id="qty_${idx}" type="number" class="qty-input" min="1" value="${p.quantity}" data-key="${getKey(p)}" ${idAttr} />
                </div>
                <p>Price: ₱${price.toFixed(2)} each</p>
                <p><strong>Subtotal: ₱${line.toFixed(2)}</strong></p>
                <div class="cart-item-actions">
                    <button class="delete-cart-item" data-key="${getKey(p)}" ${idAttr}><i class="fa fa-trash"></i> Delete</button>
              </div>
          </div>`;
        }
    });

    if(cartSummary && cartSummary.querySelector('h3')) cartSummary.querySelector('h3').textContent = `Total: ₱${subtotal.toFixed(2)}`;
    if(orderSummaryDiv) orderSummaryDiv.innerHTML = grouped.map(g=>`<div class="order-summary-line"><strong>${g.name}</strong> (${g.size}) x${g.quantity} - ₱${(parseFloat(g.price||0)*g.quantity).toFixed(2)}</div>`).join('') + `<div class="order-summary-subtotal">Subtotal: ₱${subtotal.toFixed(2)}</div>`;
    if(checkoutBtn) checkoutBtn.disabled=false;

    attachItemEvents();
    attachDeleteEvents();
    attachSelectionEvents();
    updateShippingFee();
  }

  function attachDeleteEvents() {
      document.querySelectorAll('.delete-cart-item').forEach(btn=> {
          btn.addEventListener('click', async e=> {
              e.preventDefault();
              if(!confirm('Remove this item from cart?')) return;
              if(useDb && btn.dataset.id) {
                  try {
                      const fd = new FormData(); fd.append('id', btn.dataset.id);
                      await fetch('delete_cart_item.php',{method:'POST',body:fd});
                  } catch(err){ console.error(err); }
              } else {
                    const key = btn.getAttribute('data-key');
                    let local = JSON.parse(localStorage.getItem('cart')||'[]');
                    local = local.filter(i=> getKey(i)!==key);
                    localStorage.setItem('cart', JSON.stringify(local));
              }
              await renderCart();
          });
      });
  }

  function attachItemEvents(){
    // quantity change
      document.querySelectorAll('.qty-input').forEach(inp=> {
          inp.addEventListener('change', async ()=> {
              let val = parseInt(inp.value,10); if(isNaN(val)||val<1) val=1; inp.value=val;
              if(useDb && inp.dataset.id) {
                  try { const fd=new FormData(); fd.append('id', inp.dataset.id); fd.append('quantity', val); await fetch('update_cart_item.php',{method:'POST',body:fd}); } catch(e){ console.error(e);} }
              else {
                  let local = JSON.parse(localStorage.getItem('cart')||'[]');
                  local.forEach(it=>{ if(getKey(it)===inp.getAttribute('data-key')) it.quantity = val; });
                  localStorage.setItem('cart', JSON.stringify(local));
              }
              await renderCart();
          });
      });
  }

  function attachSelectionEvents() {
      document.querySelectorAll('.select-cart-item').forEach(cb=> {
          cb.addEventListener('change', ()=>{ updateSelectedSummary(); updateShippingFee(); });
      });
  }

  function getSelectedSummaryData() {
      const selected = Array.from(document.querySelectorAll('.select-cart-item:checked'));
      const source = useDb? items : JSON.parse(localStorage.getItem('cart')||'[]');
      let subtotal=0; let html='';
      selected.forEach(cb=> {
          const id = cb.getAttribute('data-id');
          const key = cb.getAttribute('data-key');
          const it = source.find(p=> useDb ? (p.id && id && String(p.id)===id) : getKey(p)===key);
          if(!it) return;
          const line = parseFloat(it.price||0)*it.quantity;
          subtotal += line;
          html += `<div><strong>${it.name}</strong> (${it.size}) x${it.quantity} - ₱${line.toFixed(2)}</div>`;
      });
      return { count: selected.length, subtotal, html };
  }

  function updateSelectedSummary(){
      const {count, subtotal, html} = getSelectedSummaryData();
      if(count===0) {
          if(orderSummaryDiv) orderSummaryDiv.innerHTML='';
          if(cartSummary && cartSummary.querySelector('h3')) cartSummary.querySelector('h3').textContent='Total: ₱0.00';
          if(checkoutBtn) checkoutBtn.disabled=true;
          return;
      }
      if(orderSummaryDiv) {
          const info = buildUserMetaBlock();
          const meta = buildChoiceMetaLines();
          const totals = `<div class='order-summary-totals'><div class='ost-sub'>Subtotal: ₱${subtotal.toFixed(2)}</div></div>`;
          orderSummaryDiv.innerHTML = info + html + meta + totals;
      }
      if(cartSummary && cartSummary.querySelector('h3')) cartSummary.querySelector('h3').textContent=`Total: ₱${subtotal.toFixed(2)}`;
      if(checkoutBtn) checkoutBtn.disabled=false;
  }

  function updateShippingFee() {
      if(!checkoutForm) return;
      const {count, subtotal, html} = getSelectedSummaryData();
      if(count===0) {return; }
      const dm = checkoutForm.querySelector('input[name="delivery_method"]:checked');
      let fee = 0; if(dm && dm.value==='standard') fee=25; // adjust if more methods later
      if(orderSummaryDiv) {
          const info = buildUserMetaBlock();
          const meta = buildChoiceMetaLines();
          const feeLine = fee? `<div class='ost-fee'>Shipping Fee: ₱${fee}</div>` : '';
          const totalLine = `<div class='ost-total'>Total: ₱${(subtotal+fee).toFixed(2)}</div>`;
          const totals = `<div class='order-summary-totals'><div class='ost-sub'>Subtotal: ₱${subtotal.toFixed(2)}</div>${feeLine}${totalLine}</div>`;
        orderSummaryDiv.innerHTML = info + html + meta + totals;
      }
      if(cartSummary && cartSummary.querySelector('h3')) {
          const displayTotal = fee ? subtotal+fee : subtotal;
          cartSummary.querySelector('h3').textContent = `Total: ₱${displayTotal.toFixed(2)}`;
      }
  }

  function buildUserMetaBlock() {
      const name = (window.userName||'').trim();
      const addr = (deliveryAddressInput?.value||'').trim();
      const phone = (deliveryPhoneInput?.value||'').trim();
      let out = '<div class="order-summary-header">';
      out += '<div class="order-summary-logo"><img src="img/logo.png" alt="Logo" /></div>';
      out += '<div class="order-summary-userinfo">';
      if(name) out += `<div class='os-user-name'>${escapeHtml(name)}</div>`;
      if(addr) out += `<div class='os-user-address'>${escapeHtml(addr)}</div>`;
      if(phone) out += `<div class='os-user-phone'>${escapeHtml(phone)}</div>`;
      out += '</div>';
      out += '</div>';
      return out;
  }

  function buildChoiceMetaLines() {
      if(!checkoutForm) return '';
      const delivery = checkoutForm.querySelector('input[name="delivery_method"]:checked');
      const paymentMethod = checkoutForm.querySelector('input[name="payment_method"]:checked');
      const partial = checkoutForm.querySelector('input[name="isPartialPayment"]:checked');
      const deliveryLabel = delivery ? (delivery.value==='standard' ? 'Standard Delivery' : 'Pick Up') : '';
      const payMethodLabel = paymentMethod ? (paymentMethod.value==='gcash' ? 'GCash' : 'Cash') : '';
      const partialLabel = partial ? (partial.value==='1' ? 'Partial Payment' : 'Full Payment') : '';
      let lines='';
      if(deliveryLabel) lines += `<div class="meta-line meta-delivery">Delivery: <strong>${deliveryLabel}</strong></div>`;
      if(payMethodLabel) lines += `<div class="meta-line meta-payment">Payment Method: <strong>${payMethodLabel}</strong></div>`;
      if(partialLabel) lines += `<div class="meta-line meta-partial">Payment Type: <strong>${partialLabel}</strong></div>`;
      return lines;
  }

  function escapeHtml(str) {
      return str.replace(/[&<>"]+/g, s=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;"}[s]));
  }

  // initial rendering
  renderCart();

  if(checkoutForm) {
      // Delivery method changes (already handled) + new listeners for payment method and payment type
      checkoutForm.querySelectorAll('input[name="delivery_method"]').forEach(r=> r.addEventListener('change', ()=>{ updateShippingFee(); }));
      checkoutForm.querySelectorAll('input[name="payment_method"]').forEach(r=> r.addEventListener('change', ()=>{ updateShippingFee(); }));
      checkoutForm.querySelectorAll('input[name="isPartialPayment"]').forEach(r=> r.addEventListener('change', ()=>{ updateShippingFee(); }));
      // Update header info if user edits address/phone
      [deliveryAddressInput, deliveryPhoneInput].forEach(inp=>{ if(inp){ inp.addEventListener('blur', ()=>{ updateShippingFee(); }); } });
  }

  // Autofill profile info
  if (checkoutForm && typeof window.userAddress !== 'undefined' && typeof window.userPhone !== 'undefined') {
      if (window.userAddress && window.userPhone) {
          deliveryAddressInput.value = window.userAddress;
          deliveryPhoneInput.value = window.userPhone;
          if (profileMissingInfo) profileMissingInfo.classList.remove('show');
          deliveryAddressInput.readOnly = true;
          deliveryPhoneInput.readOnly = true;
          checkoutForm.querySelector('button[type="submit"]').disabled = false;
      } else {
          if (profileMissingInfo) profileMissingInfo.classList.add('show');
          deliveryAddressInput.value = '';
          deliveryPhoneInput.value = '';
          deliveryAddressInput.readOnly = true;
          deliveryPhoneInput.readOnly = true;
          checkoutForm.querySelector('button[type="submit"]').disabled = true;
      }
  }

  if(checkoutForm){
      checkoutForm.addEventListener('submit', async e=> {
          e.preventDefault();
          const address = (deliveryAddressInput?.value||'').trim();
          const phone   = (deliveryPhoneInput?.value||'').trim();
          const dm = checkoutForm.querySelector('input[name="delivery_method"]:checked');
          const pm = checkoutForm.querySelector('input[name="payment_method"]:checked');
          const partial = checkoutForm.querySelector('input[name="isPartialPayment"]:checked');
          if(!address){ alert('Please enter your delivery address.'); return; }
          if(!phone){ alert('Please enter your phone number.'); return; }
          if(!dm){ alert('Please select a delivery method.'); return; }
          if(!pm){ alert('Please select a payment method.'); return; }
          const selected = Array.from(document.querySelectorAll('.select-cart-item:checked'));
          if(!selected.length){ alert('Please select at least one item.'); return; }
          const src = useDb? items : JSON.parse(localStorage.getItem('cart')||'[]');
          const payload=[];
          selected.forEach(cb=>{ const id = cb.getAttribute('data-id'); const key = cb.getAttribute('data-key'); const it = src.find(p=> useDb ? (p.id && id && String(p.id)===id) : getKey(p)===key); if(!it) return; payload.push({product_id: it.product_id||it.id, quantity: it.quantity, size: it.size||'Default'}); });
          const valid = payload.filter(p=> Number(p.product_id)>0 && Number(p.quantity)>0);
          if(!valid.length){ alert('No valid items to order.'); return; }
          const fd = new FormData();
          fd.append('items', JSON.stringify(valid));
          fd.append('delivery_address', address);
          fd.append('delivery_phone', phone);
          fd.append('isPartialPayment', partial ? partial.value : '0');
          try {
              const btn = checkoutForm.querySelector('.place-order-btn');
              if(btn){ btn.disabled=true; btn.textContent='Placing'; }
              const res = await fetch('quick_order.php',{method:'POST',body:fd});
              const text = await res.text(); let data; try{ data=JSON.parse(text);}catch(parseErr){ console.error(parseErr,text); alert('Order failed: Unexpected response'); if(btn){ btn.disabled=false; btn.textContent='Place Order'; } return; }
              if(data.status==='ok') {
                  if(!useDb){ let loc = JSON.parse(localStorage.getItem('cart')||'[]'); valid.forEach(v=>{ loc = loc.filter(p=> !((p.product_id||p.id)===v.product_id && (p.size||'Default')===v.size)); }); localStorage.setItem('cart', JSON.stringify(loc)); }
                  alert('Order placed successfully!');
                  window.location.href = data.redirect || 'profile.php#ordersPanel';
              } else if(data.status==='need_profile') {
                  alert(data.message||'Please complete your profile.');
                  window.location.href = data.redirect || 'profile.php?complete_profile=1';
              } else if(data.status==='duplicate') {
                  alert(data.message||'Duplicate recent order.');
              } else {
                  alert('Order failed: '+(data.message||'Unknown error'));
                  console.error('Order error', data);
            }
            if(btn){ btn.disabled=false; btn.textContent='Place Order'; }
          } catch(err) {
                console.error(err);
                alert('Order failed due to network or server error.');
                const btn = checkoutForm.querySelector('.place-order-btn');
                if(btn){ btn.disabled=false; btn.textContent='Place Order'; }
            }
        });
    }
});
