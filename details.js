function changeImage(thumbnail) {
  const mainImage = document.getElementById('mainImage');
  mainImage.src = thumbnail.src;
}

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

// Product Detail — Dropdown Logic
function toggleDropdown() {
  const productMenu = document.querySelector("#productDropdown .dropdown-menu");
  const sizeMenu = document.querySelector("#sizeDropdown .dropdown-menu");

  sizeMenu.style.display = "none"; // close size
  productMenu.style.display = productMenu.style.display === "block" ? "none" : "block";
}

function toggleSizeDropdown() {
  const productMenu = document.querySelector("#productDropdown .dropdown-menu");
  const sizeMenu = document.querySelector("#sizeDropdown .dropdown-menu");

  productMenu.style.display = "none"; // close product
  sizeMenu.style.display = sizeMenu.style.display === "block" ? "none" : "block";
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
}

function adjustQuantity(change) {
  const input = document.getElementById('quantity');
  let value = parseInt(input.value);
  value = Math.max(0, value + change);
  input.value = value;
}

// Design Option Selection
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

// Drag & Drop support
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
  document.getElementById('viewerModal').style.display = 'flex';

  // Optional: initialize viewer only once
  if (!window.viewerInitialized) {
    initViewer(); // defined in sim.js
    window.viewerInitialized = true;
  }
}

function closeViewerModal() {
  document.getElementById('viewerModal').style.display = 'none';
}
