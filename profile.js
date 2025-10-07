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
                    const statusBadge = card.querySelector('.badge');
                    if(statusBadge){
                        statusBadge.textContent='Completed';
                        statusBadge.className = 'badge status-completed';
                    }
                    const dash = document.createElement('span');
                    dash.className='oc-dash';
                    dash.textContent='—';
                    btn.replaceWith(dash);
                    // Auto refresh after short delay so admin/orders pages & other aggregates reflect completion
                    setTimeout(()=>{ window.location.reload(); }, 800);
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
        const cards = document.querySelectorAll('.order-card[data-delivery-status]');
        function applyFilter(filter){
            const synonymMap = {
                shipped:['dispatched','shipped'],
                cancelled:['failed','cancelled']
            };
            cards.forEach(card => {
                const status = card.getAttribute('data-delivery-status');
                let show=false;
                if(filter==='all') show=true; else if(status){
                    if(status.indexOf(filter)!==-1) show=true; else {
                        // check synonyms
                        for(const key in synonymMap){
                            if(key===filter){
                                if(synonymMap[key].some(s=>status.indexOf(s)!==-1)) { show=true; break; }
                            }
                        }
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
});
