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
        fetch('reports_api.php?action=weekly').then(r=>r.json()).then(d=>{
            if(d.status==='ok') renderWeekly(d.weekly); else console.error(d);
        })
        .catch(e=>console.error(e));
    }
    function renderWeekly(list){
        tbody.innerHTML='';
        if(!list || list.length===0){ tbody.innerHTML='<tr><td colspan="6" style="text-align:center;padding:25px;color:#555;">No data</td></tr>'; return; }
        list.forEach(w=>{
            const rev = Number(w.revenue||0);
            const revCls = rev>0 ? 'rev-positive' : 'rev-zero';
            const tr = document.createElement('tr');
            tr.innerHTML = `
            <td>${w.yw}</td>
            <td>${w.total_orders}</td>
            <td>${w.paid}</td>
            <td>${w.partial}</td>
            <td>${w.pending}</td>
            <td class="${revCls}">₱${rev.toFixed(2)}</td>`;
            tbody.appendChild(tr);
        });
    }
    rangeSelect?.addEventListener('change', () => {
        // Currently only weekly implemented; placeholder for monthly/quarterly expansion.
        fetchWeekly();
    });
    fetchWeekly();
});
