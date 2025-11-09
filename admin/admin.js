const calendarDays = document.getElementById("calendarDays");
const monthYear = document.getElementById("monthYear");
const prevBtn = document.getElementById("prev");
const nextBtn = document.getElementById("next");

let currentDate = new Date();
const today = new Date(); 

function renderCalendar(date) {
    const year = date.getFullYear();
    const month = date.getMonth();
    // If calendar elements are not present on this page, skip rendering to avoid errors
    if (!monthYear || !calendarDays) return;

    const firstDay = new Date(year, month, 1).getDay();
    const lastDate = new Date(year, month + 1, 0).getDate();

    monthYear.textContent = date.toLocaleString("default", {
        month: "long",
        year: "numeric"
    });

    calendarDays.innerHTML = "";

    for (let i = 0; i < firstDay; i++) {
        calendarDays.innerHTML += `<div></div>`;
    }

    for (let day = 1; day <= lastDate; day++) {
        const isToday =
        day === today.getDate() &&
        month === today.getMonth() &&
        year === today.getFullYear();

        const highlight = isToday ? "today" : "";
        calendarDays.innerHTML += `<div class="${highlight}">${day}</div>`;
    }
}

if (prevBtn) {
    prevBtn.addEventListener("click", () => {
        currentDate.setMonth(currentDate.getMonth() - 1);
        renderCalendar(currentDate);
    });
}

if (nextBtn) {
    nextBtn.addEventListener("click", () => {
        currentDate.setMonth(currentDate.getMonth() + 1);
        renderCalendar(currentDate);
    });
}

// Only render calendar if the required DOM elements exist
if (monthYear && calendarDays) renderCalendar(currentDate);

// Dashboard data
function fetchDashboardCounts(){
    fetch('dashboard-api.php?action=counts', {cache:'no-store'})
        .then(r=>r.json())
        .then(d=>{
            if(d.status==='ok'){
                const c=d.counts||{}; const set=(id,val)=>{ const el=document.getElementById(id); if(el) el.textContent=val; };
                set('countToday', c.today||0);
                set('countPending', c.pending||0);
                set('countCompleted', c.completed||0);
                set('countCancelled', c.cancelled||0);
            }
        })
        .catch(e=>console.error('counts error',e));
}
function fetchRecentOrders(){
    const tbody=document.getElementById('recentOrdersTbody'); if(!tbody) return;
    fetch('dashboard-api.php?action=recent_orders', {cache:'no-store'})
        .then(r=>r.json()).then(d=>{
            tbody.innerHTML='';
            if(d.status!=='ok' || !d.orders || d.orders.length===0){ tbody.innerHTML='<tr><td colspan="5" class="table-msg">No recent orders</td></tr>'; return; }
            d.orders.forEach(o=>{
                const tr=document.createElement('tr');
                const obadge = makeStatusBadge(o.order_status);
                const dbadge = makeDeliveryBadge(o.delivery_status);
                tr.innerHTML=`<td>#${o.order_id}</td>
                               <td>${(o.customer_name||'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c]))}</td>
                               <td class="amount">₱${Number(o.total_amount||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})}</td>
                               <td>${obadge}</td>
                               <td>${dbadge}</td>`;
                tbody.appendChild(tr);
            });
    }).catch(e=>{ tbody.innerHTML='<tr><td colspan="5" class="table-msg">Failed to load</td></tr>'; console.error('recent error',e); })
    .finally(()=>{ ensureMinimalScrollbar('recentOrdersTable'); });
}
function fetchOutstandingPayments(){
    const tbody=document.getElementById('outstandingPaymentsTbody'); if(!tbody) return;
    fetch('dashboard-api.php?action=outstanding_payments', {cache:'no-store'})
        .then(r=>r.json()).then(d=>{
            tbody.innerHTML='';
            const list=d.status==='ok' ? (d.payments||[]) : [];
            if(list.length===0){ tbody.innerHTML='<tr><td colspan="6" class="table-msg">No outstanding payments</td></tr>'; return; }
            list.forEach(p=>{
                const tr=document.createElement('tr');
                tr.innerHTML=`<td>#${p.order_id}</td>
                               <td>${(p.customer_name||'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c]))}</td>
                               <td>${(p.created_at||'').toString().slice(0,10)}</td>
                               <td class="amount">₱${Number(p.total_amount||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})}</td>
                               <td class="paid">₱${Number(p.paid_amount||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})}</td>
                               <td class="balance">₱${Number(p.balance||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})}</td>`;
                tbody.appendChild(tr);
            });
        }).catch(e=>{ tbody.innerHTML='<tr><td colspan="6" class="table-msg">Failed to load</td></tr>'; console.error('outstanding error',e); })
        .finally(()=>{ ensureMinimalScrollbar('outstandingPaymentsTable'); });
}
// Force a scrollbar by adding a 1px overflow if content fits exactly (without visually stretching)
function ensureMinimalScrollbar(tableId){
    const tbl=document.getElementById(tableId); if(!tbl) return;
    const wrapper=tbl.parentElement; if(!wrapper) return;
    tbl.style.minWidth='';
    // If table fits (no scroll), widen by 1px to activate scrollbar track
    if(tbl.scrollWidth <= wrapper.clientWidth){
        tbl.style.minWidth = (tbl.scrollWidth + 1) + 'px';
    }
}
// Debounce resize to re-check
let _resizeTimer = null; window.addEventListener('resize', ()=>{ clearTimeout(_resizeTimer); _resizeTimer=setTimeout(()=>{ ensureMinimalScrollbar('recentOrdersTable'); ensureMinimalScrollbar('outstandingPaymentsTable'); },120); });

// Helpers to render badges like on orders page
function normalizeClassName(s){ return (s||'').toString().trim().toLowerCase().replace(/\s+/g,'-'); }
function escapeHtml(s){ return (s||'').toString().replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c])); }
function makeStatusBadge(status){
    const v = status||''; const cls = 'status-'+normalizeClassName(v);
    return `<span class="badge ${cls}">${escapeHtml(v)}</span>`;
}
function makeDeliveryBadge(status){
    const v = status||''; const norm = normalizeClassName(v);
    // map to delivery- classes for shipped/delivered/picked-up/failed when present
    const dcls = (norm==='shipped' || norm==='delivered' || norm==='picked-up' || norm==='failed') ? ('delivery-'+norm) : ('status-'+norm);
    return `<span class="badge ${dcls}">${escapeHtml(v)}</span>`;
}
fetchDashboardCounts(); fetchRecentOrders(); fetchOutstandingPayments();
setInterval(fetchDashboardCounts, 15000);

const links = document.querySelectorAll('.nav-links a');
const currentPage = window.location.pathname.split('/').pop();

links.forEach(link => {
    const linkPage = link.getAttribute('href').split('/').pop(); 
    if (linkPage === currentPage) {
        link.classList.add('active');
    }
});