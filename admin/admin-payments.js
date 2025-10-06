window.addEventListener('DOMContentLoaded', () => {
  highlightNav();
  const tbody = document.getElementById('paymentsTbody');

  function highlightNav(){
    const currentPage = window.location.pathname.split('/').pop();
    document.querySelectorAll('.nav-links a').forEach(link => {
      const href = link.getAttribute('href'); if(!href) return; const linkPage = href.split('/').pop().split('#')[0];
      if(currentPage === linkPage) link.classList.add('active');
    });
  }
  function escapeHtml(s){ return (s||'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c])); }
  function badge(status){ return `<span class="badge-status st-${escapeHtml(status)}">${escapeHtml(status)}</span>`; }

  function fetchPayments(){
    fetch('payments_api.php?action=list').then(r=>r.json()).then(d=>{
      if(d.status==='ok') render(d.payments); else console.error(d);
    }).catch(e=>console.error(e));
  }
  function render(list){
    tbody.innerHTML='';
    if(!list || list.length===0){ tbody.innerHTML='<tr><td colspan="8" style="padding:25px;text-align:center;color:#555;">No payment records</td></tr>'; return; }
    list.forEach(p=>{
      const tr = document.createElement('tr');
      tr.innerHTML=`
        <td>${p.order_id}</td>
        <td>${escapeHtml(p.customer_name||'')}</td>
        <td>${escapeHtml(p.phone||'')}</td>
        <td>${escapeHtml(p.address||'')}</td>
        <td>${escapeHtml(p.product_name||'')}</td>
        <td>${p.quantity||''}</td>
        <td>${badge(p.OrderStatus||'Pending')}</td>
        <td>₱${Number(p.TotalAmount).toFixed(2)}</td>`;
      tbody.appendChild(tr);
    });
  }
  fetchPayments();
});
