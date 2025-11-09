window.addEventListener('DOMContentLoaded', () => {
    highlightNav();
    const tbody = document.getElementById('paymentsTbody');

    // Remove 'Unpaid' from selectable options; status now driven by partial or paid amounts
    const PAYMENT_OPTIONS = ['Partial','Paid'];

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
            // Show receipt thumbnail whenever a receipt_url exists (even if method wasn't recorded yet)
            const receiptImg = p.receipt_url ? `<a href="${escapeHtml(p.receipt_url)}" target="_blank" rel="noopener"><img src="${escapeHtml(p.receipt_url)}" alt="Receipt" class="receipt-thumb" /></a>` : '';
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
        // Derive from payment data: if any AmountPaid > 0 but less than total => Partial, if >= total => Paid, else default Pending/processing mapping
        const total = Number(p.TotalAmount)||0;
        const paid = Number(p.AmountPaid)||0;
        if(paid >= total && total > 0) return 'Paid';
        if(paid > 0 && paid < total) return 'Partial';
        // no payment yet -> treat as Partial placeholder until downpayment is recorded; but not selectable 'Unpaid'
        return 'Partial';
    }

    tbody.addEventListener('change', e => {
        const sel = e.target.closest('[data-payment-select]');
        if(!sel) return;
        const cell = sel.closest('.payment-cell');
        const id = cell.getAttribute('data-id');
        const newStatus = sel.value;
        const row = sel.closest('tr');
        const methodText = row && row.children[3] ? row.children[3].textContent.trim().toLowerCase() : '';
        // Permit manual changes for both Cash and GCash
        if(methodText !== 'cash' && methodText !== 'gcash'){
            const prev = sel.getAttribute('data-prev') || sel.value;
            sel.value = prev;
            alert('Only Cash or GCash payments can be manually changed.');
            return;
        }
        sel.setAttribute('data-prev', newStatus);
        updatePaymentStatus(id, newStatus, cell, row);
    });

    function updatePaymentStatus(id, status, cell, row){
        const saving = cell.querySelector('.saving-text');
        saving.style.display='block';
        const fd = new FormData(); fd.append('action','update_payment_status'); fd.append('order_id', id); fd.append('PaymentStatus', status);
        fetch('payments-api.php',{method:'POST',body:fd})
                    .then(async r=>{
                        const raw = await r.text();
                        try { return JSON.parse(raw); } catch(e){ console.error('Bad JSON from payments-api:', raw); throw new Error('Bad JSON'); }
                    })
          .then(d=>{
            saving.style.display='none';
            if(d.status==='ok'){
                const sel = cell.querySelector('.payment-status-select');
                sel.className = 'payment-status-select ' + statusClass(status);
                sel.value = status;
                // Update Amount Paid & Balance cells (indexes: Total=6, AmountPaid=7, Balance=8)
                const amountPaidCell = row.children[7];
                const balanceCell = row.children[8];
                if(amountPaidCell && typeof d.AmountPaid!=='undefined') amountPaidCell.textContent = '₱'+ Number(d.AmountPaid).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});
                if(balanceCell && typeof d.Balance!=='undefined') balanceCell.textContent = '₱'+ Number(d.Balance).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});
            } else {
                alert('Update failed: '+(d.message||'Unknown error'));
            }
          })
          .catch(e=>{ saving.style.display='none'; console.error(e); alert('Network error updating status'); });
    }
    fetchPayments();
});
