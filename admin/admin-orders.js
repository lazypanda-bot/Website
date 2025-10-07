window.addEventListener('DOMContentLoaded', () => {
  highlightNav();
  const tbody = document.getElementById('ordersTbody');

  const STATUS_OPTIONS = ['Pending','Shipped','Delivered','Cancelled']; // Removed Paid & Processing; Completed is customer-confirmed only

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
    if(!list || list.length===0){ tbody.innerHTML='<tr><td colspan="8" style="text-align:center; padding:25px; color:#555;">No orders found</td></tr>'; return; }
    list.forEach(o=>{
      const tr = document.createElement('tr');
      const total = Number(o.TotalAmount).toFixed(2);
      const currentStatus = o.OrderStatus || 'Pending';
      const isCompleted = currentStatus === 'Completed';
      tr.innerHTML = `
        <td>${o.order_id}</td>
        <td>${escapeHtml(o.customer_name||'')}</td>
        <td>${escapeHtml(o.phone||'')}</td>
        <td>${escapeHtml(o.address||'')}</td>
        <td>${escapeHtml(o.product_name||'')}</td>
        <td>${o.quantity||''}</td>
        <td class="status-cell${isCompleted ? ' completed-readonly' : ''}" data-id="${o.order_id}">
          ${ isCompleted ? `<div class='status-badge-wrapper'>${badge(currentStatus)}</div>` : buildSelect(currentStatus) }
          <div class="status-saving" style="display:none;">Saving...</div>
        </td>
        <td>₱${total}</td>`;
      tbody.appendChild(tr);
    });
  }

  function buildSelect(current){
    return `<span class="status-select-wrap"><select class="order-status-select ${statusClass(current)}" data-status-select>
      ${STATUS_OPTIONS.map(s=>`<option value="${s}" ${s===current?'selected':''}>${s}</option>`).join('')}
    </select></span>`;
  }

  function statusClass(s){ return 'os-' + s; }

  tbody.addEventListener('change', e => {
    const sel = e.target.closest('[data-status-select]');
    if(!sel) return;
    const cell = sel.closest('.status-cell');
    if(cell.classList.contains('completed-readonly')) return; // safety
    const id = cell.getAttribute('data-id');
    const newStatus = sel.value;
    updateStatus(id, newStatus, cell);
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
      } else {
        alert(d.message||'Update failed');
      }
    }).catch(err=>{ savingEl.style.display='none'; alert('Error '+err); });
  }

  fetchOrders();
});
