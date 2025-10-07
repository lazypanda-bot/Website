window.addEventListener('DOMContentLoaded', () => {
  highlightNav();
  const tbody = document.getElementById('paymentsTbody');

  const PAYMENT_OPTIONS = ['Unpaid','Partial','Paid'];

  function highlightNav(){
    const currentPage = window.location.pathname.split('/').pop();
    document.querySelectorAll('.nav-links a').forEach(link => {
      const href = link.getAttribute('href'); if(!href) return; const linkPage = href.split('/').pop().split('#')[0];
      if(currentPage === linkPage) link.classList.add('active');
    });
  }
  function escapeHtml(s){ return (s||'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c])); }
  function orderBadge(status){ return `<span class="badge-status st-${escapeHtml(status)}">${escapeHtml(status)}</span>`; }
  // Payment badge removed; styling applied directly to select

  function fetchPayments(){
    fetch('payments_api.php?action=list').then(r=>r.json()).then(d=>{
      if(d.status==='ok') render(d.payments); else console.error(d);
    }).catch(e=>console.error(e));
  }

  function render(list){
    tbody.innerHTML='';
    if(!list || list.length===0){ tbody.innerHTML='<tr><td colspan="9" style="padding:25px;text-align:center;color:#555;">No payment records</td></tr>'; return; }
    list.forEach(p=>{
      const tr = document.createElement('tr');
      const paymentStatus = derivePaymentStatus(p);
      tr.innerHTML=`
        <td>${p.order_id}</td>
        <td>${escapeHtml(p.customer_name||'')}</td>
        <td>${escapeHtml(p.phone||'')}</td>
        <td>${escapeHtml(p.address||'')}</td>
        <td>${escapeHtml(p.product_name||'')}</td>
        <td>${p.quantity||''}</td>
        <td>${orderBadge(p.OrderStatus||'Pending')}</td>
        <td class="payment-cell" data-id="${p.order_id}">
          ${buildPaymentSelect(paymentStatus)}
          <div class="saving-text" style="display:none;">Saving...</div>
        </td>
        <td>₱${Number(p.TotalAmount).toFixed(2)}</td>`;
      tbody.appendChild(tr);
    });
  }

  function buildPaymentSelect(current){
    return `<span class="payment-select-wrap"><select class="payment-status-select ${statusClass(current)}" data-payment-select>
      ${PAYMENT_OPTIONS.map(s=>`<option value="${s}" ${s===current?'selected':''}>${s}</option>`).join('')}
    </select></span>`;
  }

  function statusClass(s){ return 'ps-' + s; }
  // (removed stray closing brace)

  function derivePaymentStatus(p){
    // Placeholder logic; refine when dedicated payment status column exists
    if(p.isPartialPayment && Number(p.isPartialPayment) == 1) return 'Partial';
    // If OrderStatus is Completed assume Paid else Unpaid
    if(p.OrderStatus === 'Completed') return 'Paid';
    return 'Unpaid';
  }

  tbody.addEventListener('change', e => {
    const sel = e.target.closest('[data-payment-select]');
    if(!sel) return;
    const cell = sel.closest('.payment-cell');
    const id = cell.getAttribute('data-id');
    const newStatus = sel.value;
    updatePaymentStatus(id, newStatus, cell);
  });

  function updatePaymentStatus(id, status, cell){
    const saving = cell.querySelector('.saving-text');
    saving.style.display='block';
    // Currently no API endpoint; placeholder to show immediate UI update.
    setTimeout(()=>{
      saving.style.display='none';
      const sel = cell.querySelector('.payment-status-select');
      sel.className = 'payment-status-select ' + statusClass(status);
      sel.value = status;
      // TODO: Persist via payments_api.php (add new action) once backend supports it.
      setTimeout(()=>{ window.location.reload(); }, 600);
    }, 400);
  }

  fetchPayments();
});
