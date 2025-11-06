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

// Fetch order stats for dashboard boxes
function fetchOrderStats(){
    fetch('orders-stats-api.php', {cache:'no-store'})
        .then(async r => {
            const ct = r.headers.get('Content-Type')||'';
            if(!r.ok) {
                throw new Error('HTTP '+r.status);
            }
            if(!ct.includes('application/json')) {
                const text = await r.text();
                throw new Error('Non-JSON response: '+text.slice(0,120));
            }
            return r.json();
        })
        .then(d => {
            if(d && d.status==='ok'){
                const c = d.counts || {}; 
                const set=(id,val)=>{ const el=document.getElementById(id); if(el) el.textContent=val; };
                set('countPending', c.Pending||0);
                set('countDelivered', c.Delivered||0);
                set('countCompleted', c.Completed||0);
                set('countCancelled', c.Cancelled||0);
            }
        })
        .catch(err => { console.error('fetchOrderStats error:', err); });
}
fetchOrderStats();
// refresh every 10s
setInterval(fetchOrderStats,10000);

// Click boxes to go to orders with (future) filtering
['boxPending','boxDelivered','boxCompleted','boxCancelled'].forEach(id=>{
    const el=document.getElementById(id); if(!el) return;
    el.style.cursor='pointer';
    el.addEventListener('click',()=>{ window.location.href='admin-orders.php'; });
});

const links = document.querySelectorAll('.nav-links a');
const currentPage = window.location.pathname.split('/').pop();

links.forEach(link => {
    const linkPage = link.getAttribute('href').split('/').pop(); 
    if (linkPage === currentPage) {
        link.classList.add('active');
    }
});