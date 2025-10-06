document.addEventListener('DOMContentLoaded', function() {
    const addressInput = document.getElementById('address');
    const phoneInput = document.getElementById('phone');
    const editAddressBtn = document.getElementById('edit-address-btn');
    const editPhoneBtn = document.getElementById('edit-phone-btn');
    const saveBtnGroup = document.getElementById('save-btn-group');
    if (!addressInput || !phoneInput || !editAddressBtn || !editPhoneBtn || !saveBtnGroup) return;
    let editing = false;

    // Store original values to restore if switching
    let originalAddress = addressInput.value;
    let originalPhone = phoneInput.value;

    editAddressBtn.addEventListener('click', function() {
        // If phone is being edited, restore its value and set readonly
        if (!phoneInput.readOnly) {
            phoneInput.readOnly = true;
            phoneInput.value = originalPhone;
        }
        addressInput.readOnly = false;
        addressInput.focus();
        showSaveBtn();
        editing = true;
    });
    editPhoneBtn.addEventListener('click', function() {
        // If address is being edited, restore its value and set readonly
        if (!addressInput.readOnly) {
            addressInput.readOnly = true;
            addressInput.value = originalAddress;
        }
        phoneInput.readOnly = false;
        phoneInput.focus();
        showSaveBtn();
        editing = true;
    });

    // Optional: Hide save button if not editing (reset on page load)
    const profileForm = document.getElementById('profileForm');
    if (profileForm) {
        profileForm.addEventListener('reset', function() {
            addressInput.readOnly = true;
            phoneInput.readOnly = true;
            hideSaveBtn();
            editing = false;
        });
    }

    // Save button is always visible

    // Show toast if profile updated
    const toast = document.getElementById('profile-toast');
    if (toast) {
        toast.classList.add('show');
        setTimeout(function() {
            toast.classList.remove('show');
            // Remove ?updated=1 from URL
            if (window.history.replaceState) {
                const url = new URL(window.location);
                url.searchParams.delete('updated');
                window.history.replaceState({}, document.title, url.pathname + url.search);
            }
        }, 2200);
    }
});
