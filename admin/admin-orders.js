window.addEventListener('DOMContentLoaded', () => {
  console.log('[admin-orders] script version 2 loaded');
  highlightNav();
  const tbody = document.getElementById('ordersTbody');

  const STATUS_OPTIONS = ['Pending','Shipped','Cancelled','Completed']; // OrderStatus only (Delivered moved to DeliveryStatus)
  const DELIVERY_OPTIONS = ['Pending','In Transit','Out for Delivery','Delivered'];

  function highlightNav(){
    const currentPage = window.location.pathname.split('/').pop();
    document.querySelectorAll('.nav-links a').forEach(link => {
      const href = link.getAttribute('href'); if(!href) return; const linkPage = href.split('/').pop().split('#')[0];
      if (currentPage === linkPage) link.classList.add('active');
    });
  }

  function escapeHtml(s){ return (s||'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c])); }
  function badge(status){ return `<span class="badge-status st-${escapeHtml(status)}">${escapeHtml(status)}</span>`; }

  function fetchOrders(){
    fetch('orders_api.php?action=list').then(r=>r.json()).then(d=>{
      if(d.status==='ok') render(d.orders); else console.error(d);
    }).catch(e=>console.error(e));
  }

  function render(list){
    tbody.innerHTML='';
  if(!list || list.length===0){ tbody.innerHTML='<tr><td colspan="9" style="text-align:center; padding:25px; color:#555;">No orders found</td></tr>'; return; }
    list.forEach(o=>{
      const tr = document.createElement('tr');
      const total = Number(o.TotalAmount).toFixed(2);
  const currentStatus = o.OrderStatus || 'Pending';
  const currentDelivery = o.DeliveryStatus || 'Pending';
      const isCompleted = currentStatus === 'Completed';
      const isCancelled = currentStatus === 'Cancelled';
      tr.innerHTML = `
        <td>${o.order_id}</td>
        <td>${escapeHtml(o.customer_name||'')}</td>
        <td>${escapeHtml(o.phone||'')}</td>
        <td>${escapeHtml(o.address||'')}</td>
        <td>${escapeHtml(o.product_name||'')}</td>
        <td>${o.quantity||''}</td>
        <td class="status-cell${(isCompleted||isCancelled) ? ' completed-readonly' : ''}" data-id="${o.order_id}" data-type="order">
          ${ (isCompleted||isCancelled) ? `<div class='status-badge-wrapper'>${badge(currentStatus)}</div>` : buildSelect(currentStatus,'order') }
          <div class="status-saving" style="display:none;">Saving...</div>
        </td>
        <td class="status-cell delivery-cell" data-id="${o.order_id}" data-type="delivery">
          ${ buildSelect(currentDelivery,'delivery') }
          <div class="status-saving" style="display:none;">Saving...</div>
        </td>
        <td>₱${total}</td>`;
      tbody.appendChild(tr);
    });
    fixDeliveryColumn();
  }

  // Safety: if delivery select accidentally rendered inside order status cell (cache / malformed insertion), move it to its own td
  function fixDeliveryColumn(){
    const rows = tbody.querySelectorAll('tr');
    rows.forEach(r=>{
      const orderCell = r.querySelector('td.status-cell[data-type="order"]');
      const deliveryCell = r.querySelector('td.status-cell[data-type="delivery"]');
      if(orderCell){
        const strayDeliverySelect = orderCell.querySelector('select[data-kind="delivery"]');
        if(strayDeliverySelect){
          // If delivery cell missing, create it before the total cell (second last position)
            let targetDeliveryCell = deliveryCell;
            if(!targetDeliveryCell){
              const cells = Array.from(r.children);
              const totalCell = cells[cells.length-1];
              targetDeliveryCell = document.createElement('td');
              targetDeliveryCell.className = 'status-cell delivery-cell';
              targetDeliveryCell.setAttribute('data-type','delivery');
              targetDeliveryCell.setAttribute('data-id', orderCell.getAttribute('data-id')||'');
              r.insertBefore(targetDeliveryCell, totalCell);
            }
            // Move select
            const wrap = strayDeliverySelect.closest('.status-select-wrap');
            if(wrap){ targetDeliveryCell.appendChild(wrap); }
            // Ensure order cell no longer contains delivery select
        }
      }
    });
  }

  function buildSelect(current, kind){
    const opts = (kind==='delivery'? DELIVERY_OPTIONS : STATUS_OPTIONS);
    return `<span class="status-select-wrap"><select class="order-status-select ${statusClass(current)}" data-status-select data-kind="${kind}">
      ${opts.map(s=>`<option value="${s}" ${s===current?'selected':''}>${s}</option>`).join('')}
    </select></span>`;
  }

  function statusClass(s){ return 'os-' + s; }

  tbody.addEventListener('change', e => {
    const sel = e.target.closest('[data-status-select]');
    if(!sel) return;
    const cell = sel.closest('.status-cell');
    const kind = sel.getAttribute('data-kind');
    if(kind==='order' && cell.classList.contains('completed-readonly')) return; // Completed locked
    const id = cell.getAttribute('data-id');
    const newVal = sel.value;
    if(kind==='delivery') {
      updateDeliveryStatus(id, newVal, cell);
    } else {
      updateStatus(id, newVal, cell);
    }
  });

  function updateStatus(id, status, cell){
    const savingEl = cell.querySelector('.status-saving');
    savingEl.style.display='block';
    const fd = new FormData(); fd.append('action','update_status'); fd.append('order_id', id); fd.append('OrderStatus', status);
    fetch('orders_api.php', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{
      savingEl.style.display='none';
      if(d.status==='ok'){
        const sel = cell.querySelector('.order-status-select');
        if(sel) sel.className = 'order-status-select ' + statusClass(status);
        if(status === 'Completed') {
          // Create badge wrapper if not present
          let bw = cell.querySelector('.status-badge-wrapper');
          if(!bw){
            bw = document.createElement('div');
            bw.className='status-badge-wrapper';
            cell.insertBefore(bw, cell.firstChild);
          }
          bw.innerHTML = badge(status);
          // Remove select wrapper
          const wrap = sel?.closest('.status-select-wrap');
          if(wrap) wrap.remove();
          cell.classList.add('completed-readonly');
        }
        // Auto-refresh page shortly after any successful status change for real-time sync
        setTimeout(()=>{ window.location.reload(); }, 700);
      } else {
        alert(d.message||'Update failed');
      }
    }).catch(err=>{ savingEl.style.display='none'; alert('Error '+err); });
  }

  function updateDeliveryStatus(id, status, cell){
    const savingEl = cell.querySelector('.status-saving');
    savingEl.style.display='block';
    const fd = new FormData(); fd.append('action','update_delivery_status'); fd.append('order_id', id); fd.append('DeliveryStatus', status);
    fetch('orders_api.php', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{
      savingEl.style.display='none';
      if(d.status==='ok'){
        const sel = cell.querySelector('.order-status-select');
        if(sel) sel.className = 'order-status-select ' + statusClass(status);
        // Optional: if delivery becomes Delivered and order not completed, no lock; leave as-is.
      } else {
        alert(d.message||'Update failed');
      }
    }).catch(err=>{ savingEl.style.display='none'; alert('Error '+err); });
  }

  fetchOrders();
});
