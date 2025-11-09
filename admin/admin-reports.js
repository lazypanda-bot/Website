window.addEventListener('DOMContentLoaded', () => {
    highlightNav();
    const tbody = document.getElementById('reportsTbody');
    const rangeSelect = document.querySelector('.report-range');

    function highlightNav(){
        const currentPage = window.location.pathname.split('/').pop();
        document.querySelectorAll('.nav-links a').forEach(link => {
            const href = link.getAttribute('href'); if(!href) return; const linkPage = href.split('/').pop().split('#')[0];
            if (currentPage === linkPage) link.classList.add('active');
        });
    }
    function fetchWeekly(){
        tbody.innerHTML = '<tr><td colspan="7" class="table-msg">Loading…</td></tr>';
        fetch('reports-api.php?action=weekly').then(r=>r.json()).then(d=>{
            if(d.status==='ok') renderWeekly(d.weekly); else { console.error(d); tbody.innerHTML='<tr><td colspan="7" class="table-msg">Failed to load</td></tr>'; }
        })
        .catch(e=>{ console.error(e); tbody.innerHTML='<tr><td colspan="7" class="table-msg">Network error</td></tr>'; });
    }
    function renderWeekly(list){
        tbody.innerHTML='';
        if(!list || list.length===0){ tbody.innerHTML='<tr><td colspan="7" class="table-msg">No data</td></tr>'; return; }
        list.forEach(w=>{
            const profit = Number(w.profit||0);
            const revCls = profit>0 ? 'rev-positive' : 'rev-zero';
            const dateLabel = w.date_label || w.yw || '';
            const tr = document.createElement('tr');
            tr.innerHTML = `
            <td>${dateLabel}</td>
            <td>${w.total_orders||0}</td>
            <td>${w.completed_orders||0}</td>
            <td>${w.pending_orders||0}</td>
            <td>${w.cancelled_orders||0}</td>
            <td>${w.pending_payments||0}</td>
            <td class="${revCls}">₱${profit.toFixed(2)}</td>`;
            tbody.appendChild(tr);
        });
    }
    rangeSelect?.addEventListener('change', () => {
        // Currently only weekly implemented; placeholder for monthly/quarterly expansion.
        fetchWeekly();
    });
    fetchWeekly();
});
