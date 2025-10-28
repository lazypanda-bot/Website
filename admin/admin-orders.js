if(window.__ADMIN_ORDERS_ACTIVE){
    console.warn('[admin-orders] abort: another orders script already active');
} 
else {
    window.__ADMIN_ORDERS_ACTIVE = 'v9';
    window.addEventListener('DOMContentLoaded', () => {
        console.log('[admin-orders] script version 9 (merged) loaded');

        const tbody = document.getElementById('ordersTbody');
        if(!tbody){ console.warn('No ordersTbody found'); return; }

        // Keep 'Pending' as a visible-but-not-selectable default: omit it from the selectable
        // Updated options per admin request
        const ORDER_STATUS_OPTIONS = ['Processing','Cancelled','Ready for Pickup','Ready to Ship'];
        const DELIVERY_STATUS_OPTIONS = ['Shipped','Delivered','Picked up'];
        const POLL_INTERVAL = 45000; // 45s
        let pollTimer = null;

        setTimeout(()=>{
            const legacyDetected = !!document.querySelector('script[src*="admin-orders.js"]') && !document.querySelector('script[src*="admin-orders.js?v="]');
            if (legacyDetected){ 
              console.warn('[admin-orders] Legacy script tag detected; enforcing structure'); 
            }
        },50);

        highlightNav();
        fetchOrders();

        function highlightNav(){
            const currentPage = window.location.pathname.split('/').pop();
            document.querySelectorAll('.nav-links a').forEach(a=>{
                const linkPage = a.getAttribute('href')?.split('/').pop();
                if(linkPage === currentPage) a.classList.add('active');
            });
        }

        function escapeHtml(s){ return (s||'').replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[c])); }
        function normalizeClass(s){ return (s||'').toString().trim().toLowerCase().replace(/\s+/g,'-').replace(/[^a-z0-9\-]/g,''); }
        function badge(status){ const cls = 'status-' + normalizeClass(status); return `<span class="badge ${cls}">${escapeHtml(status)}</span>`; }

        function fetchOrders(manual=false){
            fetch('orders-api.php?action=list', {cache:'no-store'})
            .then(async r => {
                if(!r.ok){
                    const txt = await r.text().catch(()=>'<no body>');
                    console.error('[admin-orders] HTTP error', r.status, txt);
                    throw new Error('HTTP '+r.status);
                }
                let data; let raw = await r.text();
                try { data = JSON.parse(raw); } catch(parseErr){
                    console.error('[admin-orders] JSON parse failed. Raw response:', raw);
                    throw parseErr;
                }
                if (data.status==='ok') render(data.orders||[]);
                else {
                    console.error('[admin-orders] API error payload:', data);
                    tbody.innerHTML='<tr><td colspan="10" class="table-msg">API error</td></tr>';
                }
            })
            .catch(err => {
                console.error('[admin-orders] fetchOrders failed:', err);
                tbody.innerHTML='<tr><td colspan="10" class="table-msg">Network error</td></tr>';
            })
            .finally(()=> scheduleNextPoll(manual));
        }

        function scheduleNextPoll(manual){
            clearTimeout(pollTimer);
            const lu = document.getElementById('lastUpdated');
            if(lu){
                const stamp = new Date().toLocaleTimeString([], {hour:'2-digit', minute:'2-digit', second:'2-digit'});
                lu.textContent = 'Last updated: ' + stamp;
            }
            pollTimer = setTimeout(()=> fetchOrders(false), POLL_INTERVAL);
        }

        function render(rows){
            tbody.innerHTML='';
            if(!rows.length){ 
                tbody.innerHTML='<tr><td colspan="10" class="table-msg">No orders found</td></tr>'; 
                return; 
              }
            rows.forEach(o=> tbody.appendChild(buildRow(o)) );
            console.log('[admin-orders] rendered rows:', rows.length);
        }

        function buildRow(o){
            const tr = document.createElement('tr');
            const orderStatus = o.OrderStatus || 'Pending';
            const deliveryStatus = o.DeliveryStatus || 'Pending';
            const isCompleted = orderStatus === 'Completed';
            const isCancelled = orderStatus === 'Cancelled';

            const cellsPre = [
                o.order_id,
                escapeHtml(o.customer_name||''),
                // design cell: show thumbnail/swatch or product name fallback
                (function(){
                    if(o.designoption_id){
                        const thumb = o.designfilepath ? '<img src="'+escapeHtml(o.designfilepath)+'" alt="design" style="width:48px;height:48px;border-radius:6px;object-fit:cover" />' : '';
                        const sw = o.design_color ? '<div style="width:48px;height:48px;border-radius:6px;background:'+escapeHtml(o.design_color)+'"></div>' : '';
                        return (thumb || sw) + '<div style="display:inline-block;vertical-align:top;margin-left:8px;">' + escapeHtml(o.product_name||'') + '<div style="font-size:12px;color:#666">' + escapeHtml(o.design_note||o.request_design||'') + '</div></div>';
                    }
                    return escapeHtml(o.product_name||'');
                })(),
                escapeHtml(o.size||''),
                o.quantity||''
            ];
            cellsPre.forEach(val=>{ 
              const td=document.createElement('td'); td.innerHTML=val; tr.appendChild(td); 
            });

            const paidTd = document.createElement('td');
            paidTd.textContent = '₱' + Number(o.AmountPaid||0).toFixed(2);
            tr.appendChild(paidTd);
            const totalTd = document.createElement('td');
            totalTd.textContent = '₱' + Number(o.TotalAmount).toFixed(2);
            tr.appendChild(totalTd);

            const orderTd = document.createElement('td');
            orderTd.className='status-cell' + (isCompleted||isCancelled? ' completed-readonly':'');
            orderTd.dataset.type='order';
            orderTd.dataset.id=o.order_id;
            if(isCompleted||isCancelled){
                orderTd.innerHTML = `<div class='status-badge-wrapper'>${badge(orderStatus)}</div>`;
            } 
            else {
                orderTd.appendChild(buildSelect(orderStatus,'order'));
            }
            if(isCompleted||isCancelled){
                // removed inline 'Saving...' helper text to avoid visual clutter
                if(isCompleted && o.CompletedAt){
                    const at = document.createElement('div');
                    at.className='completed-at';
                    at.textContent='Completed '+o.CompletedAt;
                    orderTd.appendChild(at);
                }
            }
            tr.appendChild(orderTd);

            const addrTd = document.createElement('td');
            addrTd.innerHTML = escapeHtml(o.address||'');
            tr.appendChild(addrTd);

            const delTd = document.createElement('td');
            delTd.className='status-cell';
            delTd.dataset.type='delivery';
            delTd.dataset.id=o.order_id;
            if(isCompleted){
                delTd.innerHTML = `<div class='status-badge-wrapper'>${badge(deliveryStatus)}</div>`;
                delTd.classList.add('completed-readonly');
            } 
            else {
                delTd.appendChild(buildSelect(deliveryStatus,'delivery'));
            }
            tr.appendChild(delTd);
            return tr;
        }

        function buildSelect(current, kind){
            const wrap = document.createElement('span');
            wrap.className='status-select-wrap';
            const sel = document.createElement('select');
            sel.className='order-status-select '+ statusClass(current);
            sel.dataset.statusSelect='1';
            sel.dataset.kind=kind;
            const opts = kind==='delivery'? DELIVERY_STATUS_OPTIONS : ORDER_STATUS_OPTIONS;
            if(current && !opts.includes(current)){
                const curOpt = document.createElement('option');
                curOpt.value = current; curOpt.textContent = current; curOpt.disabled = true; curOpt.selected = true; sel.appendChild(curOpt);
            }
            opts.forEach(o=>{
                const opt = document.createElement('option'); opt.value=o; opt.textContent=o; 
                if(o===current) opt.selected=true; sel.appendChild(opt);
            });
            wrap.appendChild(sel);
            return wrap;
        }

        function statusClass(s){ return 'os-'+((s||'').toString().trim().replace(/\s+/g,'-')); }

        tbody.addEventListener('change', e=>{
            const sel = e.target.closest('select[data-status-select]');
            if(!sel) return;
            const cell = sel.closest('td.status-cell'); if(!cell) return;
            if(cell.classList.contains('completed-readonly')) return;
            const id = cell.dataset.id; const kind = sel.dataset.kind; const newVal = sel.value;
            if(kind==='order'){
                if(newVal==='Completed'){
                    alert('Completed can only be set after customer confirmation.');
                    fetchOrders(true); 
                    return;
                }
                updateOrderStatus(id,newVal,cell,sel);
                // for Cancelled we'll let the server-confirmed update replace the delivery control with a badge
            } 
            else if(kind==='delivery'){
                  updateDeliveryStatus(id,newVal,cell,sel);
            }
        });

        function updateOrderStatus(id,status,cell,sel){
            // disable select briefly as a lightweight saving indicator
            const selElem = sel; if(selElem) selElem.disabled = true;
            const fd = new FormData(); fd.append('action','update_status'); fd.append('order_id',id); fd.append('OrderStatus',status);
            fetch('orders-api.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
                try{ const _s = cell && cell.querySelector && cell.querySelector('select[data-status-select]'); if(_s) _s.disabled = false; }catch(e){}
                if(d.status==='ok'){
                    sel.className='order-status-select '+statusClass(status);
                    if(status==='Completed') {
                        lockCompleted(cell,status);
                    }
                    if(status==='Cancelled'){
                        lockCompleted(cell,status);  // replace order select with badge and lock
                        const row = cell.parentElement;  // find delivery cell in same row and request server to mark it as Failed
                        if(row){
                            const deliveryCell = row.querySelector('td.status-cell[data-type="delivery"]');
                            if(deliveryCell){
                                const selDelivery = deliveryCell.querySelector('select[data-status-select]'); if(selDelivery) selDelivery.disabled = true;
                                const fd2 = new FormData(); fd2.append('action','update_delivery_status'); fd2.append('order_id', id); fd2.append('DeliveryStatus','Failed');
                                fetch('orders-api.php',{method:'POST', body:fd2}).then(r=>r.json()).then(dd=>{
                                    if(selDelivery) selDelivery.disabled = false;
                                    if(dd.status==='ok'){
                                        deliveryCell.innerHTML = `<div class='status-badge-wrapper'>${badge('Failed')}</div>`;
                                        deliveryCell.classList.add('completed-readonly');
                                    } 
                                    else {
                                          alert('Failed to update delivery status: ' + (dd.message || 'unknown'));
                                    }
                                })
                      .catch(err=>{
                          try{ const _d = deliveryCell && deliveryCell.querySelector && deliveryCell.querySelector('select[data-status-select]'); if(_d) _d.disabled = false; }catch(e){}
                          alert('Error updating delivery status: ' + err);                              
                      });
                            }
                        }
                    }
                    // If order moved to Shipped, ensure delivery status becomes Shipped
                    if(status==='Shipped'){
                        const row = cell.parentElement;
                        if(row){
                            const deliveryCell = row.querySelector('td.status-cell[data-type="delivery"]');
                            if(deliveryCell && !deliveryCell.classList.contains('completed-readonly')){
                                const selDelivery = deliveryCell.querySelector('select[data-status-select]'); if(selDelivery) selDelivery.disabled = true;
                                const fd3 = new FormData(); fd3.append('action','update_delivery_status'); fd3.append('order_id', id); fd3.append('DeliveryStatus','Shipped');
                                fetch('orders-api.php',{method:'POST', body:fd3}).then(r=>r.json()).then(dd=>{
                                    if(selDelivery) selDelivery.disabled = false;
                                    if(dd.status==='ok'){
                                        // If delivery select exists, update its value, otherwise render badge
                                        const selDelivery = deliveryCell.querySelector('select[data-status-select]');
                                        if(selDelivery){
                                            selDelivery.value = 'Shipped';
                                            selDelivery.className = 'order-status-select ' + statusClass('Shipped');
                                        } 
                                        else {
                                              deliveryCell.innerHTML = `<div class='status-badge-wrapper'>${badge('Shipped')}</div>`;
                                        }
                                    } 
                                    else {
                                          console.warn('Failed to set delivery to Shipped:', dd.message);
                                    }
                                })
                      .catch(err=>{
                          try{ const _d = deliveryCell && deliveryCell.querySelector && deliveryCell.querySelector('select[data-status-select]'); if(_d) _d.disabled = false; }catch(e){}
                          console.warn('Error updating delivery status to Shipped:', err);
                      });
                            }
                        }
                    }
                } 
                else { 
                  alert(d.message||'Update failed'); 
                }
            })
            .catch(err=>{ try{ const _s = cell && cell.querySelector && cell.querySelector('select[data-status-select]'); if(_s) _s.disabled = false; }catch(e){}; alert('Error '+err); });
        }

        function updateDeliveryStatus(id,status,cell,sel){
            // disable the select during update to indicate progress
            const selElem2 = cell.querySelector('select[data-status-select]');
            if(selElem2) selElem2.disabled = true;
            const fd = new FormData(); fd.append('action','update_delivery_status'); 
            fd.append('order_id',id); 
            fd.append('DeliveryStatus',status);
            fetch('orders-api.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
                try{ const _s2 = cell && cell.querySelector && cell.querySelector('select[data-status-select]'); if(_s2) _s2.disabled = false; }catch(e){}
                if(d.status==='ok'){
                    sel.className='order-status-select '+statusClass(status);
                    // If delivery was picked up, server will have completed the order as well — reflect that in the UI
                    if(status === 'Picked up'){
                        const row = cell.parentElement;
                        // replace delivery select with a 'Picked up' badge
                        const badgeWrapper = document.createElement('div'); badgeWrapper.className='status-badge-wrapper'; badgeWrapper.innerHTML = badge('Picked up');
                        const wrap = sel.closest('.status-select-wrap'); if(wrap) wrap.replaceWith(badgeWrapper);
                        cell.classList.add('completed-readonly');
                        // also find order cell and replace with Completed badge
                        if(row){
                            const orderCell = row.querySelector('td.status-cell[data-type="order"]');
                            if(orderCell){
                                lockCompleted(orderCell,'Completed');
                            }
                        }
                    }
                } 
                else { 
                  alert(d.message||'Update failed'); 
                }
            })
            .catch(err=>{ try{ const _s2 = cell && cell.querySelector && cell.querySelector('select[data-status-select]'); if(_s2) _s2.disabled = false; }catch(e){}; alert('Error '+err); });
        }

        function lockCompleted(cell,status){
            const sel = cell.querySelector('select');
            if(!sel) return;
            const wrap = sel.closest('.status-select-wrap');
            const badgeWrapper = document.createElement('div');
            badgeWrapper.className='status-badge-wrapper';
            badgeWrapper.innerHTML = badge(status);
            if(wrap) wrap.replaceWith(badgeWrapper);
            cell.classList.add('completed-readonly');
        }

    });
}
