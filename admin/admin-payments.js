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

    function escapeHtml(s){ 
      return (s||'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c])); 
    }

    function fetchPayments(){
        tbody.innerHTML = '<tr><td colspan="10" class="table-msg">Loading payments...</td></tr>';
        fetch('payments-api.php?action=list')
            .then(r=>r.json())
            .then(d=>{
                if(d.status==='ok') render(d.payments); else { console.error(d); tbody.innerHTML='<tr><td colspan="10" class="table-msg">Failed to load payments</td></tr>'; }
            })
            .catch(e=>{ console.error(e); tbody.innerHTML='<tr><td colspan="10" class="table-msg">Error loading payments</td></tr>'; });
    }

    function render(list){
        tbody.innerHTML='';
        if(!list || list.length===0){ 
            tbody.innerHTML='<tr><td colspan="10" class="table-msg">No payment records</td></tr>'; 
            return; 
        }
        list.forEach(p=>{
            const tr = document.createElement('tr');
            // Use payment_status and payment_method from API if present
            const paymentStatus = p.payment_status || derivePaymentStatus(p);
            // Calculate balance
            const total = Number(p.TotalAmount)||0;
            // AmountPaid comes from API aggregate; payment_amount is latest payment (may be partial)
            const paid = Number(p.AmountPaid)||0;
            const balance = total - paid;
            const methodCell = buildMethodCell(p);
            const receiptImg = (p.payment_method||'').toLowerCase()==='gcash' && p.receipt_url ? `<a href="${escapeHtml(p.receipt_url)}" target="_blank" rel="noopener"><img src="${escapeHtml(p.receipt_url)}" alt="Receipt" class="receipt-thumb" /></a>` : '';
            tr.innerHTML=`
                <td>${p.order_id}</td>
                <td>${escapeHtml(p.customer_name||'')}</td>
                <td>${escapeHtml(p.payment_date||p.created_at||'')}</td>
                <td>${methodCell}</td>
                <td>${receiptImg}</td>
                <td>${escapeHtml(p.payment_type||'')}</td>
                <td>₱${total.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})}</td>
                <td>₱${paid.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})}</td>
                <td>₱${balance.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})}</td>
                <td class="payment-cell" data-id="${p.order_id}">
                    ${buildPaymentSelect(paymentStatus)}
                    <div class="saving-text">Saving...</div>
                </td>`;
            tbody.appendChild(tr);
        });
    }

    function buildPaymentSelect(current){
        return `<span class="payment-select-wrap"><select class="payment-status-select ${statusClass(current)}" data-payment-select>
        ${PAYMENT_OPTIONS.map(s=>`<option value="${s}" ${s===current?'selected':''}>${s}</option>`).join('')}
        </select></span>`;
    }

    function statusClass(s){ return 'ps-' + s; }

    function buildMethodCell(p){
        const m = (p.payment_method||'').toString().toLowerCase();
        if(m==='gcash') return 'GCash';
        if(m==='cash') return 'Cash';
        return escapeHtml(p.payment_method||'');
    }

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
        const fd = new FormData(); fd.append('action','update_status'); fd.append('order_id', id); fd.append('OrderStatus', status==='Paid' ? 'Completed' : (status==='Partial' ? 'Processing' : 'Pending'));
        fetch('orders-api.php',{method:'POST',body:fd})
            .then(r=>r.json())
            .then(d=>{
                saving.style.display='none';
                if(d.status==='ok'){
                    const sel = cell.querySelector('.payment-status-select');
                    sel.className = 'payment-status-select ' + statusClass(status);
                    sel.value = status;
                } else {
                    alert('Update failed: '+(d.message||'Unknown error'));
                }
            })
            .catch(e=>{ saving.style.display='none'; console.error(e); alert('Network error updating status'); });
    }
    fetchPayments();
});
