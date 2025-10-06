window.addEventListener('DOMContentLoaded', () => {
  // Highlight active nav
  const currentPage = window.location.pathname.split('/').pop();
  document.querySelectorAll('.nav-links a').forEach(link => {
    const href = link.getAttribute('href');
    if (!href || href === '#') return;
    const linkPage = href.split('/').pop().split('#')[0];
    if (currentPage === linkPage) link.classList.add('active');
  });

  const tbody = document.getElementById('productsTbody');
  const productModal = document.getElementById('productModal');
  const serviceModal = document.getElementById('serviceModal');
  const openProductBtn = document.getElementById('openProductModal');
  const openServiceBtn = document.getElementById('openServiceModal');
  const productForm = document.getElementById('productForm');
  const serviceForm = document.getElementById('serviceForm');
  const servicesTbody = document.getElementById('servicesTbody');
  const productModalTitle = document.getElementById('productModalTitle');
  const serviceTypeList = document.getElementById('serviceTypeList');

  function openModal(modal){ modal.style.display='flex'; }
  function closeModal(modal){ modal.style.display='none'; }
  document.querySelectorAll('[data-close]').forEach(btn=>btn.addEventListener('click',()=>closeModal(btn.closest('.modal'))));

  openProductBtn?.addEventListener('click', () => { resetProductForm(); productModalTitle.textContent='Add Product'; openModal(productModal); });
  openServiceBtn?.addEventListener('click', () => { serviceForm.reset(); loadServices(); openModal(serviceModal); });

  function renderProducts(list){
    tbody.innerHTML='';
    if(!list || list.length===0){
      tbody.innerHTML = '<tr><td colspan="6" class="empty-row">No products yet</td></tr>';
      return;
    }
    // Collect service types for datalist
    const serviceTypes = new Set();
    list.forEach(p=>{ if(p.service_type) serviceTypes.add(p.service_type); });
    serviceTypeList.innerHTML = Array.from(serviceTypes).map(s=>`<option value="${escapeHtml(s)}"></option>`).join('');

    list.forEach(p=>{
      const tr = document.createElement('tr');
      const imgSrc = firstImage(p.images);
      tr.innerHTML = `
        <td>${escapeHtml(p.service_type||'')}</td>
        <td><img src="${escapeAttr(imgSrc)}" alt="thumb" class="product-thumb" /></td>
        <td>${escapeHtml(p.product_name)}</td>
        <td>₱${Number(p.price).toFixed(2)}</td>
        <td style="max-width:260px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHtml(p.product_details||'')}</td>
        <td>
          <button class="action-icon-btn edit" title="Edit" data-edit="${p.product_id}"><i class="fas fa-edit"></i></button>
          <button class="action-icon-btn delete" title="Delete" data-del="${p.product_id}"><i class="fas fa-trash"></i></button>
          <a href="../product-details.php?id=${p.product_id}" target="_blank" class="action-icon-btn" title="View"><i class="fas fa-external-link-alt"></i></a>
        </td>`;
      tbody.appendChild(tr);
    });
  }

  function fetchProducts(){
    fetch('products_api.php?action=list')
      .then(r=>r.json())
      .then(data=>{
        if(data.status==='ok') renderProducts(data.products); else console.error(data);
      }).catch(err=>console.error('List error',err));
  }

  function resetProductForm(){
    productForm.reset();
    document.getElementById('product_id').value='';
  }

  function firstImage(imagesField){
    if(!imagesField) return '../img/snorlax.png';
    const t = imagesField.trim();
    if(!t) return '../img/snorlax.png';
    if(t.startsWith('[')) {
      try { const arr = JSON.parse(t); if(Array.isArray(arr)&&arr.length>0) return arr[0]; } catch(e){}
    }
    if(t.includes(',')) { const p=t.split(',')[0].trim(); if(p) return p; }
    return t;
  }
  function escapeHtml(s){ return (s||'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c])); }
  function escapeAttr(s){ return escapeHtml(s); }

  tbody.addEventListener('click', e => {
    const editBtn = e.target.closest('[data-edit]');
    if(editBtn){
      const id = editBtn.getAttribute('data-edit');
      // Find row data by reading current table (could also store last fetch list)
      // Simpler: refetch and fill when found
      fetch('products_api.php?action=list').then(r=>r.json()).then(d=>{
        if(d.status==='ok'){
          const prod = d.products.find(p=>String(p.product_id)===String(id));
          if(prod){
            productModalTitle.textContent='Edit Product';
            openModal(productModal);
            document.getElementById('product_id').value = prod.product_id;
            document.getElementById('product_name').value = prod.product_name;
            document.getElementById('service_type').value = prod.service_type || '';
            document.getElementById('price').value = prod.price;
            document.getElementById('product_details').value = prod.product_details || '';
            document.getElementById('images').value = prod.images || '';
          }
        }
      });
    }
    const delBtn = e.target.closest('[data-del]');
    if(delBtn){
      const id = delBtn.getAttribute('data-del');
      if(confirm('Delete this product?')){
        const fd = new FormData(); fd.append('action','delete'); fd.append('product_id', id);
        fetch('products_api.php', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{
          if(d.status==='ok'){ fetchProducts(); } else alert(d.message||'Delete failed');
        }).catch(err=>alert('Delete error '+err));
      }
    }
  });

  productForm.addEventListener('submit', e => {
    e.preventDefault();
    const fd = new FormData(productForm);
    fd.append('action','save');
    fetch('products_api.php', {method:'POST', body:fd})
      .then(r=>r.json())
      .then(d=>{
        if(d.status==='ok'){ closeModal(productModal); fetchProducts(); }
        else alert(d.message||'Save failed');
      })
      .catch(err=>alert('Save error '+err));
  });

  serviceForm.addEventListener('submit', e => {
    e.preventDefault();
    const fd = new FormData();
    fd.append('action','add');
    fd.append('name', document.getElementById('service_name').value.trim());
    fd.append('description', document.getElementById('service_description').value.trim());
    fetch('services_api.php', {method:'POST', body:fd})
      .then(r=>r.json())
      .then(d=>{
        if(d.status==='ok'){ loadServices(true); serviceForm.reset(); }
        else alert(d.message||'Add failed');
      })
      .catch(err=>alert('Service error '+err));
  });

  function loadServices(refreshDatalist=false){
    fetch('services_api.php?action=list').then(r=>r.json()).then(d=>{
      if(d.status==='ok'){
        renderServices(d.services);
        if(refreshDatalist) syncServiceDatalist(d.services);
        else if(serviceTypeList.children.length===0) syncServiceDatalist(d.services);
      }
    }).catch(e=>console.error(e));
  }
  function renderServices(list){
    servicesTbody.innerHTML='';
    if(!list || list.length===0){ servicesTbody.innerHTML='<tr><td colspan="3" style="text-align:center;padding:14px;color:#666;">No services</td></tr>'; return; }
    list.forEach(s=>{
      const tr=document.createElement('tr');
      tr.innerHTML=`<td>${escapeHtml(s.name)}</td><td>${escapeHtml(s.description||'')}</td><td style="text-align:center;">`+
        `<button class="svc-del" data-del-svc="${s.service_id}" title="Delete">✕</button></td>`;
      servicesTbody.appendChild(tr);
    });
  }
  function syncServiceDatalist(list){
    serviceTypeList.innerHTML = (list||[]).map(s=>`<option value="${escapeHtml(s.name)}"></option>`).join('');
  }
  servicesTbody?.addEventListener('click', e => {
    const btn = e.target.closest('[data-del-svc]');
    if(btn){
      const id = btn.getAttribute('data-del-svc');
      if(confirm('Delete this service? This does not remove existing products.')){
        const fd = new FormData(); fd.append('action','delete'); fd.append('service_id', id);
        fetch('services_api.php', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{
          if(d.status==='ok'){ loadServices(true); }
          else alert(d.message||'Delete failed');
        }).catch(err=>alert('Delete error '+err));
      }
    }
  });

  // initial services load for datalist
  loadServices();

  // Search filter
  const searchInput = document.querySelector('.search-input');
  searchInput?.addEventListener('input', () => {
    const term = searchInput.value.toLowerCase();
    Array.from(tbody.querySelectorAll('tr')).forEach(tr => {
      const text = tr.innerText.toLowerCase();
      tr.style.display = text.includes(term) ? '' : 'none';
    });
  });

  // Initial load
  fetchProducts();
});