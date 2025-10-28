// --- Product Details Thumbnail Row Logic ---

function addThumbnail() {
    var thumbnailRow = document.querySelector('.thumbnail-row');
    if (!thumbnailRow) return;
    var wrapper = document.createElement('div');
    wrapper.className = 'thumbnail-wrapper';
    var newImg = document.createElement('img');
    // Use the main image as the default for new thumbnails
    var mainImage = document.getElementById('mainImage');
    newImg.src = mainImage ? mainImage.src : 'img/logo.png';
    newImg.alt = 'New Mug';
    newImg.className = 'thumbnail';
    newImg.onclick = function() { changeImage(newImg); };
    var delBtn = document.createElement('button');
    delBtn.className = 'delete-thumbnail-btn';
    delBtn.type = 'button';
    delBtn.title = 'Delete thumbnail';
    delBtn.textContent = '-';
    delBtn.onclick = function() { deleteThumbnail(delBtn); };
    wrapper.appendChild(newImg);
    wrapper.appendChild(delBtn);
    var addBtn = document.getElementById('add-thumbnail-btn');
    thumbnailRow.insertBefore(wrapper, addBtn);
}

window.addThumbnail = addThumbnail;

function changeImage(thumbnail) {
    var mainImage = document.getElementById('mainImage');
    mainImage.src = thumbnail.src;
}
window.changeImage = changeImage;

function deleteThumbnail(btn) {
    var wrapper = btn.closest('.thumbnail-wrapper');
    if (wrapper) wrapper.remove();
}
window.deleteThumbnail = deleteThumbnail;

function showTab(tabId, button) {
    const tabs = document.querySelectorAll('.tab-content');
    const buttons = document.querySelectorAll('.tab-btn');

    tabs.forEach(tab => {
        tab.style.display = 'none';
        tab.classList.remove('fade');
    });

    buttons.forEach(btn => btn.classList.remove('active'));

    const activeTab = document.getElementById(tabId);
    activeTab.style.display = 'block';
    activeTab.classList.add('fade');
    button.classList.add('active');
}

// Ensure showTab is available globally for inline onclick
window.showTab = showTab;

// product deatail dropdown
function toggleDropdown() {
    const productMenu = document.querySelector("#productDropdown .dropdown-menu");
    const sizeMenu = document.querySelector("#sizeDropdown .dropdown-menu");
    sizeMenu.style.display = "none";
    productMenu.style.display = productMenu.style.display === "block" ? "none" : "block";
}

function toggleSizeDropdown() {
    const productMenu = document.querySelector("#productDropdown .dropdown-menu");
    const sizeMenu = document.querySelector("#sizeDropdown .dropdown-menu");
    productMenu && (productMenu.style.display = "none");
	  if (sizeMenu) {
	      const isOpen = sizeMenu.style.display === "block";
	      sizeMenu.style.display = isOpen ? "none" : "block";
	      if (!isOpen) {
	          document.addEventListener('mousedown', handleClickOutsideSizeDropdown);
	      } else {
	          document.removeEventListener('mousedown', handleClickOutsideSizeDropdown);
	      }
	  }
}

function handleClickOutsideSizeDropdown(event) {
    const dropdown = document.getElementById('sizeDropdown');
    if (dropdown && !dropdown.contains(event.target)) {
      const menu = dropdown.querySelector('.dropdown-menu');
      if (menu) menu.style.display = 'none';
      document.removeEventListener('mousedown', handleClickOutsideSizeDropdown);
    }
}

function selectOption(el) {
    const selectedText = el.textContent;
    document.querySelector("#productDropdown .dropdown-toggle").textContent = selectedText;
    document.querySelector("#product-name").value = selectedText;
    document.querySelector("#productDropdown .dropdown-menu").style.display = "none";
}

function selectSize(el) {
    const selectedText = el.textContent;
    document.querySelector("#sizeDropdown .dropdown-toggle").textContent = selectedText;
    document.querySelector("#size").value = selectedText;
    document.querySelector("#sizeDropdown .dropdown-menu").style.display = "none";
    document.removeEventListener('mousedown', handleClickOutsideSizeDropdown);
}

function adjustQuantity(change) {
    const input = document.getElementById('quantity');
    let value = parseInt(input.value);
    value = Math.max(1, value + change);
    input.value = value;
}

// design selection
function selectDesign(option) {
    document.getElementById('design-option').value = option;

    const buttons = document.querySelectorAll('.design-btn');
    buttons.forEach(btn => btn.classList.remove('selected'));

    const selectedBtn = option === 'upload'
        ? buttons[0]
        : option === 'customize'
        ? buttons[1]
        : buttons[2];

    selectedBtn.classList.add('selected');

    if (option === 'request') {
        openModal();
    }
    if (option === 'upload') {
        openUploadModal();
    }
    if (option === 'customize') {
        openViewerModal();
    }
}
function openModal() {
    const modal = document.getElementById('designModal');
    if (modal) {
        modal.style.display = 'flex';
    } else {
        console.warn("Modal element with ID 'designModal' not found.");
    }
}

function closeModal() {
    const modal = document.getElementById('designModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function submitDesign() {
    const input = document.querySelector('.design-input');
    if (!input || input.value.trim() === "") {
        alert("Please describe your design before submitting.");
        return;
  }

  console.log("Design submitted:", input.value);
  closeModal();
}
function openUploadModal() {
    document.getElementById('uploadModal').style.display = 'flex';
}

function closeUploadModal() {
    document.getElementById('uploadModal').style.display = 'none';
}

function submitUpload() {
    const fileInput = document.getElementById('designFile');
    const file = fileInput ? fileInput.files[0] : null;

    if (!file) {
        alert("Please select a file to upload.");
        return;
    }

    console.log("File uploaded:", file.name);
    closeUploadModal();
}


// drag & drop setup
document.addEventListener('DOMContentLoaded', function() {
    // Back button
    const backBtn = document.getElementById('backBtn');
    if (backBtn) {
        backBtn.addEventListener('click', function(e) {
            e.preventDefault();
            // Prefer returning to products.php. If the referrer is the products listing, go there; otherwise navigate to products.php.
            try {
                const ref = document.referrer || '';
                if (ref.indexOf('products.php') !== -1) {
                    window.location.href = ref;
                } else {
                    window.location.href = 'products.php';
                }
            } catch (err) {
                window.location.href = 'products.php';
            }
        });
    }

    // Thumbnails
    // Delegated handler for flash message dismiss buttons
    document.addEventListener('click', function (e) {
        try {
            var btn = e.target && e.target.closest && e.target.closest('.flash-dismiss-btn');
            if (!btn) return;
            var flash = btn.closest('.flash-order-success');
            if (flash) flash.remove();
        } catch (err) {
        // defensive: don't break other scripts
            console.error('Error handling flash dismiss:', err);
        }
    }); 
});

  // Thumbnails - only admin can add/delete thumbnails
const isAdmin = !!window.isAdmin;
// Support thumbnails rendered either server-side or client-side: use delegation so
// thumbnails added later (e.g. by admin preview) will also respond to clicks.
document.addEventListener('click', function(e){
    const thumb = e.target && e.target.closest && e.target.closest('.thumbnail');
    if (!thumb) return;
    changeImage(thumb);
});
if (isAdmin) {
    const addThumbBtn = document.getElementById('add-thumbnail-btn');
    if (addThumbBtn) addThumbBtn.addEventListener('click', addThumbnail);
    document.querySelectorAll('.delete-thumbnail-btn').forEach(btn => {
        btn.addEventListener('click', function() { deleteThumbnail(btn); });
    });
}

// Tab switching
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        console.log('[TabBtn] Clicked:', btn, 'Event:', e);
        const tabId = btn.getAttribute('data-tab');
        const tabContent = document.getElementById(tabId);
        if (!tabContent) {
            console.warn('[TabBtn] Tab content not found for id:', tabId);
            return;
        }
        // Remove hidden attribute if present
        if (tabContent.hasAttribute('hidden')) {
            tabContent.removeAttribute('hidden');
            console.log('[TabBtn] Removed hidden attribute from:', tabContent);
        }
        showTab(tabId, btn);
    });
});

// Dropdowns
const productDropdownToggle = document.getElementById('productDropdownToggle');
if (productDropdownToggle) productDropdownToggle.addEventListener('click', toggleDropdown);
document.querySelectorAll('#productDropdown .dropdown-menu li[data-action="select-option"]').forEach(li => {
    li.addEventListener('click', function() { selectOption(li); });
});
const sizeDropdownToggle = document.getElementById('sizeDropdownToggle');
if (sizeDropdownToggle) sizeDropdownToggle.addEventListener('click', toggleSizeDropdown);
document.querySelectorAll('#sizeDropdown .dropdown-menu li[data-action="select-size"]').forEach(li => {
    li.addEventListener('click', function() { selectSize(li); });
});

// Quantity
const qtyMinus = document.getElementById('qtyMinus');
const qtyPlus = document.getElementById('qtyPlus');
if (qtyMinus) qtyMinus.addEventListener('click', function() { adjustQuantity(-1); });
if (qtyPlus) qtyPlus.addEventListener('click', function() { adjustQuantity(1); });

// Design option buttons
const uploadDesignBtn = document.getElementById('uploadDesignBtn');
if (uploadDesignBtn) uploadDesignBtn.addEventListener('click', function() { selectDesign('upload'); });
const requestDesignBtn = document.getElementById('requestDesignBtn');
if (requestDesignBtn) requestDesignBtn.addEventListener('click', function() { selectDesign('request'); });

// Modal actions
const closeModalBtn = document.getElementById('closeModalBtn');
if (closeModalBtn) closeModalBtn.addEventListener('click', closeModal);
const submitDesignBtn = document.getElementById('submitDesignBtn');
if (submitDesignBtn) submitDesignBtn.addEventListener('click', submitDesign);
const closeUploadModalBtn = document.getElementById('closeUploadModalBtn');
if (closeUploadModalBtn) closeUploadModalBtn.addEventListener('click', closeUploadModal);
const submitUploadBtn = document.getElementById('submitUploadBtn');
if (submitUploadBtn) submitUploadBtn.addEventListener('click', submitUpload);

// Cart notification (no inline style)
const cartNotif = document.getElementById('cart-notification');
if (cartNotif) cartNotif.style.display = 'none';

// Drag & drop setup
const dropZone = document.getElementById('dropZone');
if (dropZone) {
    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.style.backgroundColor = '#f5eaea';
    });
    dropZone.addEventListener('dragleave', () => {
        dropZone.style.backgroundColor = '#fafafa';
    });
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.style.backgroundColor = '#fafafa';
        const fileInput = document.getElementById('designFile');
        if (fileInput) {
            fileInput.files = e.dataTransfer.files;
        }
    });
}

// Customize button
const selectedProduct = localStorage.getItem('selectedProduct');
const customizeBtn = document.getElementById('customizeBtn');
if (customizeBtn) {
    customizeBtn.setAttribute('data-product', selectedProduct);
}

// Ensure initial active tab is visible
const firstActive = document.querySelector('.tab-btn.active');
if (firstActive) {
    const target = firstActive.getAttribute('data-tab');
    const tabEl = document.getElementById(target);
    if (tabEl) { tabEl.hidden = false; tabEl.style.display = 'block'; }
}
// If URL contains #order, open that tab automatically
if (window.location && window.location.hash && window.location.hash.toLowerCase().includes('order')) {
    const orderBtn = document.querySelector('.tab-btn[data-tab="order"]');
    if (orderBtn) {
        try { orderBtn.click(); } catch (e) { console.warn('Failed to auto-open order tab', e); }
    }
}
const modal = document.getElementById('viewerModal');
if (modal) {
    modal.style.display = 'flex';
    // Wait for the modal to be visible before initializing viewer
    setTimeout(() => {
        if (!window.viewerInitialized) {
            if (typeof initViewer === 'function') {
                initViewer(); // defined in sim.js
                window.viewerInitialized = true;
            }
        }
    }, 100);
}
  
function closeViewerModal() {
    document.getElementById('viewerModal').style.display = 'none';
}
// Add to Cart (AJAX) handler
const addCartBtn = document.querySelector('.addcart-btn');
const cartForm = document.getElementById('cartForm');
if (addCartBtn && cartForm) {
    addCartBtn.addEventListener('click', async () => {
        const quantityInput = document.getElementById('quantity');
        const sizeHidden = document.getElementById('cart_size');
        const qtyValue = quantityInput ? parseInt(quantityInput.value || '1', 10) : 1;
        if (quantityInput && qtyValue < 1) { quantityInput.value = 1; }
        if (sizeHidden) {
            const ddToggle = document.getElementById('sizeDropdownToggle');
            if (ddToggle) sizeHidden.value = ddToggle.textContent.trim();
        }
        // Mirror displayed quantity into hidden cart form quantity
        const cartQty = document.getElementById('cart_quantity');
        if (cartQty) cartQty.value = Math.max(1, qtyValue);

        // Build form data
        const fd = new FormData(cartForm);
        try {
            const res = await fetch('add-to-cart.php', { method: 'POST', body: fd });
            const text = await res.text();
            let data = null;
            try { data = JSON.parse(text); } catch (_) {}
            if (!res.ok || !data || data.status !== 'ok') {
                const msg = (data && data.message) ? data.message : ('Failed to add to cart' + (text && !data ? ' (non-JSON response)' : ''));
                throw new Error(msg);
            }
            let msg = 'Added to cart';
            if (data.action === 'updated') msg = 'Cart updated';
            if (data.action === 'replaced') msg = 'Quantity set';
            showAddCartToast(msg);
        } catch (err) {
            console.error(err);
            showAddCartToast(err.message || 'Error adding to cart', true);
        }
    });
}

function showAddCartToast(message, isError = false) {
    let toast = document.getElementById('cart-toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'cart-toast';
        toast.style.position = 'fixed';
        toast.style.bottom = '30px';
        toast.style.right = '30px';
        toast.style.zIndex = '9999';
        toast.style.padding = '14px 26px';
        toast.style.borderRadius = '40px';
        toast.style.fontWeight = '600';
        toast.style.fontFamily = 'Poppins, sans-serif';
        toast.style.boxShadow = '0 4px 12px rgba(0,0,0,0.18)';
        document.body.appendChild(toast);
    }
    toast.textContent = message;
    toast.style.background = isError ? '#b32626' : '#3a0d0d';
    toast.style.color = '#fff';
    toast.style.opacity = '0';
    toast.style.transition = 'opacity .25s, transform .25s';
    requestAnimationFrame(() => {
        toast.style.opacity = '1';
        toast.style.transform = 'translateY(0)';
    });
    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => { if (toast && toast.parentNode) toast.parentNode.removeChild(toast); }, 320);
    }, 1800);
}