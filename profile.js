document.addEventListener('DOMContentLoaded', function() {
    const addressInput = document.getElementById('address');
    const phoneInput = document.getElementById('phone');
    const editAddressBtn = document.getElementById('edit-address-btn');
    const editPhoneBtn = document.getElementById('edit-phone-btn');
    if (!addressInput || !phoneInput || !editAddressBtn || !editPhoneBtn) return;

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
    });

    editPhoneBtn.addEventListener('click', function(e) {
        e.preventDefault();
        if (!addressInput.readOnly) {
            addressInput.readOnly = true;
        }
        enableEditing(phoneInput, () => { originalPhone = phoneInput.value; });
    });

    // When submitting form, ensure readonly removed so values post
    const profileForm = document.getElementById('profileForm');
    if (profileForm) {
        profileForm.addEventListener('submit', () => {
            addressInput.readOnly = false;
            phoneInput.readOnly = false;
        });
    }

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
});
