window.addEventListener('DOMContentLoaded', () => {
  highlightNav();
  const tbody = document.getElementById('ordersTbody');
  const modal = document.getElementById('orderStatusModal');
  const form = document.getElementById('orderStatusForm');
  const statusOrderId = document.getElementById('status_order_id');
  const statusSelect = document.getElementById('status_OrderStatus');

  function highlightNav(){
    const currentPage = window.location.pathname.split('/').pop();
    document.querySelectorAll('.nav-links a').forEach(link => {
      const href = link.getAttribute('href'); if(!href) return; const linkPage = href.split('/').pop().split('#')[0];
      if (currentPage === linkPage) link.classList.add('active');
    });
  }

  function openModal(){ modal.style.display='flex'; }
  function closeModal(){ modal.style.display='none'; }
  document.querySelectorAll('[data-close]').forEach(btn=>btn.addEventListener('click',closeModal));

  function badge(status){
    return `<span class="badge-status st-${escapeHtml(status)}">${escapeHtml(status)}</span>`;
  }

  function escapeHtml(s){ return (s||'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c])); }

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
      tr.innerHTML = `
        <td>${o.order_id}</td>
        <td>${escapeHtml(o.customer_name||'')}</td>
        <td>${escapeHtml(o.phone||'')}</td>
        <td>${escapeHtml(o.address||'')}</td>
        <td>${escapeHtml(o.product_name||'')}</td>
        <td>${o.quantity||''}</td>
        <td>${badge(o.OrderStatus||'Pending')}</td>
        <td>₱${total}</td>
        <td>
          <button class="action-icon-btn edit" data-edit="${o.order_id}" title="Update Status"><i class="fas fa-pen"></i></button>
        </td>`;
      tbody.appendChild(tr);
    });
  }

  tbody.addEventListener('click', e => {
    const btn = e.target.closest('[data-edit]');
    if(btn){
      const id = btn.getAttribute('data-edit');
      statusOrderId.value = id;
      // Pre-select current status by scanning the row
      const row = btn.closest('tr');
      if(row){
        const statusText = row.querySelector('.badge-status')?.textContent.trim();
        if(statusText) statusSelect.value = statusText;
      }
      openModal();
    }
  });

  form.addEventListener('submit', e => {
    e.preventDefault();
    const fd = new FormData(form); fd.append('action','update_status');
    fetch('orders_api.php', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{
      if(d.status==='ok'){ closeModal(); fetchOrders(); } else alert(d.message||'Update failed');
    }).catch(err=>alert('Error '+err));
  });

  fetchOrders();
});
