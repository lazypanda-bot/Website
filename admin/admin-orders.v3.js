// Admin Orders Script v9 – separate Order & Delivery status columns restored, additional columns (Design, Size, Amount Paid, Delivery Address), polling retained, Completed locked
// Prevent double-init if legacy script somehow also runs
if(window.__ADMIN_ORDERS_ACTIVE){
  console.warn('[admin-orders] abort: another orders script already active');
} else {
window.__ADMIN_ORDERS_ACTIVE = 'v9';
window.addEventListener('DOMContentLoaded', () => {
  console.log('[admin-orders] script version 9 (dual status + polling) loaded');

  const tbody = document.getElementById('ordersTbody');
  if(!tbody){ console.warn('No ordersTbody found'); return; }

  // Include 'Pending' as default initial state; admin can change it. Completed still customer-confirmation only.
  // Remove 'Pending' from selectable options; it is only the default when customer orders.
  const ORDER_STATUS_OPTIONS = ['Processing','Ready','Shipped','Cancelled'];
  const DELIVERY_STATUS_OPTIONS = ['Dispatched','Delivered','Failed'];
  const POLL_INTERVAL = 45000; // 45s
  let pollTimer=null;

  // Detect if legacy v2 script snuck in (by its console marker or global patterns)
  setTimeout(()=>{
    const legacyDetected = !!document.querySelector('script[src*="admin-orders.js"]');
    if(legacyDetected){
      console.warn('[admin-orders] Legacy script tag detected; enforcing structure');
    }
  },50);

  highlightNav();
  fetchOrders();
  // no mutation observer needed in single-column mode

  function highlightNav(){
    const currentPage = window.location.pathname.split('/').pop();
    document.querySelectorAll('.nav-links a').forEach(a=>{
      const linkPage = a.getAttribute('href')?.split('/').pop();
      if(linkPage === currentPage) a.classList.add('active');
    });
  }

  function escapeHtml(s){ return (s||'').replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[c])); }
  function badge(status){ return `<span class="badge-status st-${escapeHtml(status)}">${escapeHtml(status)}</span>`; }

  function fetchOrders(manual=false){
    fetch('orders_api.php?action=list', {cache:'no-store'})
      .then(async r => {
        if(!r.ok){
          const txt = await r.text().catch(()=>'<no body>');
          console.error('[admin-orders] HTTP error', r.status, txt);
          throw new Error('HTTP '+r.status);
        }
        let data; let raw = await r.text();
        try { data = JSON.parse(raw); } catch(parseErr){
          console.error('[admin-orders] JSON parse failed. Raw response:', raw);
          throw parseErr;
        }
        if(data.status==='ok') render(data.orders||[]);
        else {
          console.error('[admin-orders] API error payload:', data);
          tbody.innerHTML='<tr><td colspan="8" style="text-align:center;padding:25px;">API error</td></tr>';
        }
      })
      .catch(err => {
        console.error('[admin-orders] fetchOrders failed:', err);
        tbody.innerHTML='<tr><td colspan="8" style="text-align:center;padding:25px;">Network error</td></tr>';
      })
      .finally(()=> scheduleNextPoll(manual));
  }

  function scheduleNextPoll(manual){
    clearTimeout(pollTimer);
    const lu = document.getElementById('lastUpdated');
    if(lu){
      const stamp = new Date().toLocaleTimeString([], {hour:'2-digit', minute:'2-digit', second:'2-digit'});
  lu.textContent = 'Last updated: ' + stamp;
    }
    pollTimer = setTimeout(()=> fetchOrders(false), POLL_INTERVAL);
  }

  function render(rows){
    tbody.innerHTML='';
  if(!rows.length){ tbody.innerHTML='<tr><td colspan="10" style="text-align:center;padding:25px;">No orders found</td></tr>'; return; }
    rows.forEach(o=> tbody.appendChild(buildRow(o)) );
    console.log('[admin-orders] rendered rows:', rows.length);
  // Structural fixer removed in single-column mode
  }

  function buildRow(o){
    const tr = document.createElement('tr');
    const orderStatus = o.OrderStatus || 'Pending';
    const deliveryStatus = o.DeliveryStatus || 'Pending';
    const isCompleted = orderStatus === 'Completed';
    const isCancelled = orderStatus === 'Cancelled';

    const cellsPre = [
      o.order_id,
      escapeHtml(o.customer_name||''),
      escapeHtml(o.product_name||''),
      escapeHtml(o.size||''),
      o.quantity||''
    ];
    cellsPre.forEach(val=>{ const td=document.createElement('td'); td.innerHTML=val; tr.appendChild(td); });

    // Amount Paid
    const paidTd = document.createElement('td');
    paidTd.textContent = '₱' + Number(o.AmountPaid||0).toFixed(2);
    tr.appendChild(paidTd);
    // Total Amount
    const totalTd = document.createElement('td');
    totalTd.textContent = '₱' + Number(o.TotalAmount).toFixed(2);
    tr.appendChild(totalTd);

    // Order Status cell
    const orderTd = document.createElement('td');
    orderTd.className='status-cell' + (isCompleted||isCancelled? ' completed-readonly':'');
    orderTd.dataset.type='order';
    orderTd.dataset.id=o.order_id;
    if(isCompleted||isCancelled){
      orderTd.innerHTML = `<div class='status-badge-wrapper'>${badge(orderStatus)}</div>`;
    } else {
      orderTd.appendChild(buildSelect(orderStatus,'order'));
      orderTd.insertAdjacentHTML('beforeend','<div class="status-saving" style="display:none;">Saving...</div>');
    }
    if(isCompleted||isCancelled){
      orderTd.insertAdjacentHTML('beforeend','<div class="status-saving" style="display:none;">Saving...</div>');
      if(isCompleted && o.CompletedAt){
        const at = document.createElement('div');
        at.className='completed-at';
        at.textContent='Completed '+o.CompletedAt;
        orderTd.appendChild(at);
      }
    }
    tr.appendChild(orderTd);

    // Delivery Address
    const addrTd = document.createElement('td');
    addrTd.innerHTML = escapeHtml(o.address||'');
    tr.appendChild(addrTd);

    // Delivery Status cell (independent; if Completed we still show final delivery badge)
    const delTd = document.createElement('td');
    delTd.className='status-cell';
    delTd.dataset.type='delivery';
    delTd.dataset.id=o.order_id;
    if(isCompleted){
      // lock with final delivery status shown (if Delivered else existing state)
      delTd.innerHTML = `<div class='status-badge-wrapper'>${badge(deliveryStatus)}</div>`;
      delTd.classList.add('completed-readonly');
    } else {
      delTd.appendChild(buildSelect(deliveryStatus,'delivery'));
      delTd.insertAdjacentHTML('beforeend','<div class="status-saving" style="display:none;">Saving...</div>');
    }
    tr.appendChild(delTd);
    return tr;
  }

  function buildSelect(current, kind){
    const wrap = document.createElement('span');
    wrap.className='status-select-wrap';
    const sel = document.createElement('select');
    sel.className='order-status-select '+ statusClass(current);
    sel.dataset.statusSelect='1';
    sel.dataset.kind=kind;
    const opts = kind==='delivery'? DELIVERY_STATUS_OPTIONS : ORDER_STATUS_OPTIONS;
    // If current status is not in selectable options (e.g., Pending), show it as disabled selected
    if(current && !opts.includes(current)){
      const curOpt = document.createElement('option');
      curOpt.value = current;
      curOpt.textContent = current;
      curOpt.disabled = true;
      curOpt.selected = true;
      sel.appendChild(curOpt);
    }
    opts.forEach(o=>{ const opt = document.createElement('option'); opt.value=o; opt.textContent=o; if(o===current) opt.selected=true; sel.appendChild(opt); });
    wrap.appendChild(sel);
    return wrap;
  }

  function statusClass(s){ return 'os-'+s.replace(/\s+/g,'-'); }

  tbody.addEventListener('change', e=>{
    const sel = e.target.closest('select[data-status-select]');
    if(!sel) return;
    const cell = sel.closest('td.status-cell'); if(!cell) return;
    if(cell.classList.contains('completed-readonly')) return;
    const id = cell.dataset.id; const kind = sel.dataset.kind; const newVal = sel.value;
    if(kind==='order'){
      if(newVal==='Completed'){
        alert('Completed can only be set after customer confirmation.');
        fetchOrders(true); return;
      }
      updateOrderStatus(id,newVal,cell,sel);
      // If order status is set to Cancelled or Failed, set delivery status to Failed automatically
      if(newVal==='Cancelled'||newVal==='Failed'){
        // Find the delivery status cell in the same row
        const row = cell.parentElement;
        if(row){
          const deliveryCell = row.querySelector('td.status-cell[data-type="delivery"]');
          if(deliveryCell && !deliveryCell.classList.contains('completed-readonly')){
            // Set the select value and trigger change
            const deliverySel = deliveryCell.querySelector('select[data-status-select]');
            if(deliverySel && deliverySel.value!=='Failed'){
              deliverySel.value = 'Failed';
              // Manually trigger change event for delivery status
              deliverySel.dispatchEvent(new Event('change', {bubbles:true}));
            }
          }
        }
      }
    } else if(kind==='delivery') {
      updateDeliveryStatus(id,newVal,cell,sel);
    }
  });

  function updateOrderStatus(id,status,cell,sel){
    const saving = cell.querySelector('.status-saving'); if(saving) saving.style.display='block';
    const fd = new FormData(); fd.append('action','update_status'); fd.append('order_id',id); fd.append('OrderStatus',status);
    fetch('orders_api.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
      if(saving) saving.style.display='none';
      if(d.status==='ok'){
        sel.className='order-status-select '+statusClass(status);
      } else { alert(d.message||'Update failed'); }
    }).catch(err=>{ if(saving) saving.style.display='none'; alert('Error '+err); });
  }

  function updateDeliveryStatus(id,status,cell,sel){
    const saving = cell.querySelector('.status-saving'); if(saving) saving.style.display='block';
    const fd = new FormData(); fd.append('action','update_delivery_status'); fd.append('order_id',id); fd.append('DeliveryStatus',status);
    fetch('orders_api.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
      if(saving) saving.style.display='none';
      if(d.status==='ok'){
        sel.className='order-status-select '+statusClass(status);
      } else { alert(d.message||'Update failed'); }
    }).catch(err=>{ if(saving) saving.style.display='none'; alert('Error '+err); });
  }

  function lockCompleted(cell,status){
    const sel = cell.querySelector('select');
    if(!sel) return;
    const wrap = sel.closest('.status-select-wrap');
    const badgeWrapper = document.createElement('div');
    badgeWrapper.className='status-badge-wrapper';
    badgeWrapper.innerHTML = badge(status);
    if(wrap) wrap.replaceWith(badgeWrapper);
    cell.classList.add('completed-readonly');
  }

});
}
