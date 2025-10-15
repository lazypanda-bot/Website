document.addEventListener('DOMContentLoaded', function() {
    const addressInput = document.getElementById('address');
    const phoneInput = document.getElementById('phone');
    const editAddressBtn = document.getElementById('edit-address-btn');
    const editPhoneBtn = document.getElementById('edit-phone-btn');
    const saveBtn = document.getElementById('saveProfileBtn');
    const avatarEditBtn = document.getElementById('avatarEditBtn');
    const avatarFileInput = document.getElementById('avatarFileInput');
    const avatarImg = document.getElementById('profileAvatarImg');
    if (!addressInput || !phoneInput || !editAddressBtn || !editPhoneBtn) return;
    // Avatar editing
    if (avatarEditBtn && avatarFileInput && avatarImg) {
        avatarEditBtn.addEventListener('click', () => {
            avatarFileInput.click();
        });
        avatarFileInput.addEventListener('change', () => {
            const file = avatarFileInput.files[0];
            if (file) {
                if (!/^image\//.test(file.type)) { showToast('Please select an image file.', 'error'); return; }
                if (file.size > 2 * 1024 * 1024) { showToast('Image must be <= 2MB.', 'error'); return; }
                const reader = new FileReader();
                reader.onload = e => { avatarImg.src = e.target.result; };
                reader.readAsDataURL(file);
                // Auto-submit after small delay to show preview
                setTimeout(() => { document.getElementById('avatarUploadForm').submit(); }, 400);
            }
        });
        // Optional drag & drop on avatar ring
        const ring = avatarImg.closest('.profile-avatar-ring');
        if (ring) {
            ['dragenter','dragover'].forEach(evt => ring.addEventListener(evt, e => { e.preventDefault(); ring.classList.add('drag-over'); }));
            ['dragleave','drop'].forEach(evt => ring.addEventListener(evt, e => { e.preventDefault(); ring.classList.remove('drag-over'); }));
            ring.addEventListener('drop', e => {
                const file = e.dataTransfer.files[0];
                if (file) {
                    if (!/^image\//.test(file.type)) { showToast('Please select an image file.', 'error'); return; }
                    if (file.size > 2 * 1024 * 1024) { showToast('Image must be <= 2MB.', 'error'); return; }
                    avatarFileInput.files = e.dataTransfer.files;
                    const reader = new FileReader();
                    reader.onload = ev => { avatarImg.src = ev.target.result; };
                    reader.readAsDataURL(file);
                    setTimeout(() => { document.getElementById('avatarUploadForm').submit(); }, 400);
                }
            });
        }
    }

    let originalAddress = addressInput.value;
    let originalPhone = phoneInput.value;

    function enableEditing(targetInput, originalValueStoreCallback) {
        if (targetInput.readOnly) {
            targetInput.readOnly = false;
            targetInput.focus();
            targetInput.setSelectionRange(targetInput.value.length, targetInput.value.length);
        } else {
            // second click toggles back to readonly and restores original if unchanged
            targetInput.readOnly = true;
            originalValueStoreCallback();
        }
    }

    editAddressBtn.addEventListener('click', function(e) {
        e.preventDefault();
        // If phone was being edited, lock it & restore if untouched
        if (!phoneInput.readOnly) {
            phoneInput.readOnly = true;
            // keep updated value (do not overwrite)
        }
        enableEditing(addressInput, () => { originalAddress = addressInput.value; });
        checkDirty();
    });

    editPhoneBtn.addEventListener('click', function(e) {
        e.preventDefault();
        if (!addressInput.readOnly) {
            addressInput.readOnly = true;
        }
        enableEditing(phoneInput, () => { originalPhone = phoneInput.value; });
        checkDirty();
    });

    // Toast factory
    function showToast(message, type = 'error') {
        let toast = document.createElement('div');
        toast.className = 'profile-toast ' + (type === 'error' ? 'profile-toast-error' : 'profile-toast-success');
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(() => { toast.classList.add('show'); }, 20);
        setTimeout(() => { toast.classList.remove('show'); }, 2400);
        setTimeout(() => { toast.remove(); }, 3000);
    }

    function isPhoneValid(raw) {
        const digits = raw.replace(/\D+/g,'');
        // Updated rule: at least 11 digits (e.g., local mobile) and only allowed formatting characters
        const nonDigitChars = raw.replace(/[\d\s()+-]/g,'');
        return digits.length >= 11 && nonDigitChars.length === 0;
    }

    function checkDirty() {
        if (!saveBtn) return;
        const changed = addressInput.value !== originalAddress || phoneInput.value !== originalPhone;
        const editing = (!addressInput.readOnly) || (!phoneInput.readOnly);
        // Allow save whenever editing and any change made (validate on submit)
        saveBtn.disabled = !(editing && changed);
    }

    ['input','change','blur','keyup'].forEach(evt => {
        addressInput.addEventListener(evt, checkDirty);
        phoneInput.addEventListener(evt, () => { checkDirty(); });
    });

    // initial state ensure hidden
    checkDirty();

    // When submitting form, ensure readonly removed so values post
    const profileForm = document.getElementById('profileForm');
    if (profileForm) {
        profileForm.addEventListener('submit', (ev) => {
            addressInput.readOnly = false;
            phoneInput.readOnly = false;
            if (!isPhoneValid(phoneInput.value)) {
                showToast('Invalid phone number: need at least 11 digits.', 'error');
                ev.preventDefault();
                return false;
            }
            originalAddress = addressInput.value;
            originalPhone = phoneInput.value;
        });
    }

    phoneInput.addEventListener('blur', () => {
        if (!phoneInput.readOnly && phoneInput.value.trim() !== '' && !isPhoneValid(phoneInput.value)) {
            showToast('Phone must have at least 11 digits.', 'error');
        }
    });

    const toast = document.getElementById('profile-toast');
    if (toast) {
        toast.classList.add('show');
        setTimeout(function() {
            toast.classList.remove('show');
            if (window.history.replaceState) {
                const url = new URL(window.location);
                url.searchParams.delete('updated');
                window.history.replaceState({}, document.title, url.pathname + url.search);
            }
        }, 2200);
    }
    // Delivery confirmation buttons (card layout)
    document.querySelectorAll('.confirm-delivery-btn').forEach(btn=>{
        btn.addEventListener('click', ()=>{
            const card = btn.closest('.order-card'); if(!card) return; const orderId = card.getAttribute('data-order-id');
            if(!orderId) return;
            if(!confirm('Confirm you received order #' + orderId + '?')) return;
            const fd = new FormData(); fd.append('order_id', orderId);
            fetch('confirm_delivery.php', { method:'POST', body: fd }).then(r=>r.json()).then(d=>{
                if(d.status==='ok') {
                    // update order and delivery badges if server returned them
                    const orderData = d.order || null;
                    if(orderData){
                        const statusBadge = card.querySelector('.order-status-badge, .badge');
                        if(statusBadge){
                            statusBadge.textContent = orderData.OrderStatus || 'Completed';
                            statusBadge.className = 'badge status-'+(orderData.OrderStatus ? orderData.OrderStatus.toString().toLowerCase().replace(/\s+/g,'-') : 'completed')+' order-status-badge';
                        }
                        const deliveryBadge = card.querySelector('.delivery-status-badge');
                        if(deliveryBadge){
                            deliveryBadge.textContent = orderData.DeliveryStatus || 'Completed';
                            deliveryBadge.className = 'badge delivery-'+(orderData.DeliveryStatus ? orderData.DeliveryStatus.toString().toLowerCase().replace(/\s+/g,'-') : 'completed')+' delivery-status-badge';
                        } else {
                            // insert a delivery badge if none exists
                            const meta = card.querySelector('.order-card-meta');
                            if(meta){
                                const div = document.createElement('div'); div.className='oc-line';
                                const dv = orderData.DeliveryStatus || 'Completed';
                                div.innerHTML = '<span class="oc-label">Delivery Status</span><span class="badge delivery-'+dv.toString().toLowerCase().replace(/\s+/g,'-')+' delivery-status-badge">'+dv+'</span>';
                                meta.insertBefore(div, meta.querySelector('.order-card-separator'));
                            }
                        }
                    }
                    const dash = document.createElement('span');
                    dash.className='oc-dash';
                    dash.textContent='—';
                    btn.replaceWith(dash);
                } else alert(d.message||'Confirmation failed');
            }).catch(()=>alert('Network error'));
        });
    });
    // Expand / collapse order details
    document.querySelectorAll('.order-expand-btn').forEach(btn=>{
        btn.addEventListener('click', ()=>{
            const row = btn.closest('.order-summary-row'); if(!row) return;
            const id = row.getAttribute('data-order-id');
            const details = document.querySelector('.order-details-row[data-details-for="'+CSS.escape(id)+'"]');
            if(!details) return;
            const isOpen = row.classList.toggle('open');
            btn.setAttribute('data-expand', isOpen? '1':'0');
            btn.textContent = isOpen ? '▾' : '▸';
            details.style.display = isOpen ? 'table-row':'none';
        });
    });

    // Delivery status filtering for order cards
    const filterBar = document.querySelector('.orders-filter-bar');
    if (filterBar) {
        const buttons = filterBar.querySelectorAll('.orders-filter-btn');
        const cards = document.querySelectorAll('.order-card[data-order-status]');
        function applyFilter(filter){
            const synonymMap = {
                pending:['processing','pending'],
                shipped:['shipped'],
                delivered:['delivered'],
                cancelled:['cancelled']
            };
            cards.forEach(card => {
                const status = card.getAttribute('data-order-status'); // already normalized like 'completed','cancelled','shipped'
                let show=false;
                if(filter==='all') show=true; else if(status){
                    if(status===filter) show=true; else {
                        const syns = synonymMap[filter];
                        if(syns && syns.includes(status)) show=true;
                    }
                }
                card.style.display = show ? '' : 'none';
            });
        }
        buttons.forEach(btn=>{
            btn.addEventListener('click', ()=>{
                buttons.forEach(b=>b.classList.remove('active'));
                btn.classList.add('active');
                applyFilter(btn.getAttribute('data-filter'));
            });
        });
    }

    // Passive auto-refresh when user returns to tab (if data might be stale >30s)
    let lastVisibilityTs = Date.now();
    document.addEventListener('visibilitychange', () => {
        if(document.visibilityState === 'visible') {
            const now = Date.now();
            if(now - lastVisibilityTs > 30000) { // >30s away
                // fetch latest order statuses instead of full reload
                fetchAndUpdateOrderStatuses();
            }
        } else {
            lastVisibilityTs = Date.now();
        }
    });

    // Polling for live order status updates (every 20s)
    const POLL_ORDERS_INTERVAL = 20000;
    let pollOrdersTimer = setInterval(fetchAndUpdateOrderStatuses, POLL_ORDERS_INTERVAL);

    function fetchAndUpdateOrderStatuses(){
        if(!window.isAuthenticated) return;
        fetch('orders_user_api.php', { cache: 'no-store' }).then(r=>r.json()).then(d=>{
            if(d.status !== 'ok' || !Array.isArray(d.orders)) return;
            d.orders.forEach(o => {
                const card = document.querySelector('.order-card[data-order-id="'+CSS.escape(String(o.order_id))+'"]');
                if(!card) return;
                const orderBadge = card.querySelector('.order-status-badge');
                const deliveryBadge = card.querySelector('.delivery-status-badge');
                const currentOrder = (o.OrderStatus || '').toString();
                const currentDelivery = (o.DeliveryStatus || '').toString();
                // Update order badge text/class if changed
                if(orderBadge && orderBadge.getAttribute('data-order-status') !== currentOrder){
                    orderBadge.textContent = prettifyStatus(currentOrder);
                    orderBadge.setAttribute('data-order-status', currentOrder);
                    orderBadge.className = 'badge status-'+prettifyClass(currentOrder)+' order-status-badge';
                }
                // Update delivery badge or insert one
                if(currentDelivery){
                    if(deliveryBadge){
                        if(deliveryBadge.getAttribute('data-delivery-status') !== currentDelivery){
                            deliveryBadge.textContent = prettifyStatus(currentDelivery);
                            deliveryBadge.setAttribute('data-delivery-status', currentDelivery);
                            deliveryBadge.className = 'badge delivery-'+prettifyClass(currentDelivery)+' delivery-status-badge';
                        }
                    } else {
                        // add a delivery badge element in the card meta
                        const meta = card.querySelector('.order-card-meta');
                        if(meta){
                            const div = document.createElement('div');
                            div.className = 'oc-line';
                            div.innerHTML = '<span class="oc-label">Delivery Status</span><span class="badge delivery-'+prettifyClass(currentDelivery)+' delivery-status-badge" data-delivery-status="'+escapeHtml(currentDelivery)+'">'+escapeHtml(prettifyStatus(currentDelivery))+'</span>';
                            meta.insertBefore(div, meta.querySelector('.order-card-separator'));
                        }
                    }
                }
                // Manage confirm button visibility: show only when DeliveryStatus == 'Delivered' and OrderStatus != 'Completed'
                const showConfirm = (currentDelivery.toLowerCase() === 'delivered') && (currentOrder.toLowerCase() !== 'completed');
                const existingConfirm = card.querySelector('.confirm-delivery-btn');
                if(showConfirm && !existingConfirm){
                    const meta = card.querySelector('.order-card-meta');
                    if(meta){
                        const div = document.createElement('div'); div.className='oc-line';
                        div.innerHTML = '<span class="oc-label">Delivery Status</span><button type="button" class="confirm-delivery-btn inline">Confirm Delivery</button>';
                        // insert near delivery area (before separator)
                        meta.insertBefore(div, meta.querySelector('.order-card-separator'));
                        // attach handler
                        div.querySelector('.confirm-delivery-btn').addEventListener('click', ()=>{
                            const orderId = card.getAttribute('data-order-id');
                            if(!confirm('Confirm you received order #' + orderId + '?')) return;
                            const fd = new FormData(); fd.append('order_id', orderId);
                            fetch('confirm_delivery.php', { method:'POST', body: fd }).then(r=>r.json()).then(d=>{
                                if(d.status==='ok') window.location.reload(); else alert(d.message||'Confirmation failed');
                            }).catch(()=>alert('Network error'));
                        });
                    }
                } else if(!showConfirm && existingConfirm){
                    const dash = document.createElement('span'); dash.className='oc-dash'; dash.textContent='—';
                    existingConfirm.replaceWith(dash);
                }
            });
        }).catch(()=>{});
    }

    function prettifyStatus(s){
        if(!s) return '';
        // Convert internal values to friendly text
        const map = { 'processing':'Processing','pending':'Pending','shipped':'Shipped','delivered':'Delivered','cancelled':'Cancelled','completed':'Completed','picked up':'Picked up','ready for pickup':'Ready for Pickup','ready to ship':'Ready to Ship' };
        const key = s.toString().toLowerCase();
        return map[key] || s;
    }

    function prettifyClass(s){ return (s||'').toString().toLowerCase().replace(/\s+/g,'-'); }

    function escapeHtml(s){ return (s||'').toString().replace(/[&<>"]/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }
});
