// --- Product Details Thumbnail Row Logic ---

function addThumbnail() {
  var thumbnailRow = document.querySelector('.thumbnail-row');
  if (!thumbnailRow) return;
  var wrapper = document.createElement('div');
  wrapper.className = 'thumbnail-wrapper';
  var newImg = document.createElement('img');
  // Use the main image as the default for new thumbnails
  var mainImage = document.getElementById('mainImage');
  newImg.src = mainImage ? mainImage.src : 'img/snorlax.png';
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
  const file = fileInput.files[0];

  if (!file) {
    alert("Please select a file to upload.");
    return;
  }

  console.log("File uploaded:", file.name);
  closeUploadModal();
}

// drag & drop
const dropZone = document.getElementById('dropZone');
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
  fileInput.files = e.dataTransfer.files;
});
const selectedProduct = localStorage.getItem('selectedProduct');
document.getElementById('customizeBtn').setAttribute('data-product', selectedProduct);

function openViewerModal() {
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
}


function closeViewerModal() {
  document.getElementById('viewerModal').style.display = 'none';
}
