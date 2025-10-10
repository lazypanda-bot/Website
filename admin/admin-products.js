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
      const rawImg = firstImage(p.images);
      const imgSrc = normalizeImagePath(rawImg);
      tr.innerHTML = `
        <td>${escapeHtml(p.service_type||'')}</td>
  <td><img src="${escapeAttr(imgSrc)}" alt="thumb" class="product-thumb" onerror="this.classList.add('broken'); this.src='/img/logo.png'" />
        </td>
        <td>${escapeHtml(p.product_name)}</td>
        <td>₱${Number(p.price).toFixed(2)}</td>
        <td style="max-width:260px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHtml(p.product_details||'')}</td>
        <td>
          <button class="action-icon-btn edit" title="Edit" data-edit="${p.product_id}"><i class="fas fa-edit"></i></button>
          <button class="action-icon-btn delete" title="Delete" data-del="${p.product_id}"><i class="fas fa-trash"></i></button>
          <a href="/product-details.php?id=${p.product_id}" target="_blank" class="action-icon-btn" title="View"><i class="fas fa-external-link-alt"></i></a>
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
    // clear previews and file info
    const preview = document.getElementById('imagePreview'); if(preview) preview.innerHTML='';
    const fileInfo = document.getElementById('fileInfo'); if(fileInfo) fileInfo.textContent='';
    const fileInput = document.getElementById('images_files'); if(fileInput) fileInput.value='';
    removedImages = [];
  }

  function firstImage(imagesField){
  if(!imagesField) return '/img/logo.png';
    const t = imagesField.trim();
  if(!t) return '/img/logo.png';
    if(t.startsWith('[')) {
      try { const arr = JSON.parse(t); if(Array.isArray(arr)&&arr.length>0) return arr[0]; } catch(e){}
    }
    if(t.includes(',')) { const p=t.split(',')[0].trim(); if(p) return p; }
    return t;
  }
  // Normalize image paths coming from DB (convert backslashes to slashes, ensure admin relative prefix)
  function normalizeImagePath(p){
  if(!p) return '/img/logo.png';
    p = String(p).trim().replace(/^\"|\"$/g,'').replace(/^'|'$/g,'');
    p = p.replace(/\\\\/g, '/');
    if(/^uploads\//i.test(p) && !p.startsWith('../') && !p.startsWith('/')) p = '../' + p;
    if(/^img\//i.test(p) && !p.startsWith('../') && !p.startsWith('/')) p = '../' + p;
    return p;
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
                // product images are handled via uploads; clear any text-input handling
                // populate preview area with existing images if any
                const preview = document.getElementById('imagePreview');
                preview.innerHTML = '';
                if(prod.images){
                  const t = prod.images.trim();
                  let imgs = [];
                  if(t.startsWith('[')){
                    try{ imgs = JSON.parse(t); }catch(e){ imgs = []; }
                  } else if(t.includes(',')) imgs = t.split(',').map(s=>s.trim()).filter(Boolean);
                  else imgs = [t];
                      imgs.slice(0,4).forEach(src=>{
                        const wrap = document.createElement('div'); wrap.className='preview-wrap'; wrap.style.display='inline-block'; wrap.style.position='relative'; wrap.style.marginRight='8px';
                        const img = document.createElement('img'); img.src = normalizeImagePath(src); img.style.maxWidth='120px'; img.style.maxHeight='120px'; img.style.objectFit='cover'; img.onerror = ()=>{ img.src='/img/logo.png'; img.classList.add('broken'); };
                        const removeBtn = document.createElement('button'); removeBtn.className='img-remove'; removeBtn.type='button'; removeBtn.title='Remove image'; removeBtn.setAttribute('data-src', src);
                        removeBtn.textContent='✕';
                        wrap.appendChild(img); wrap.appendChild(removeBtn); preview.appendChild(wrap);
                      });
                }
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
  // append any images marked for removal
  removedImages.forEach(r=> fd.append('images_removed[]', r));
    // Append files if selected
    const fileInput = document.getElementById('images_files');
    if(fileInput && fileInput.files && fileInput.files.length>0){
      Array.from(fileInput.files).forEach(f=> fd.append('images_files[]', f));
    }
    fetch('products_api.php', {method:'POST', body:fd})
      .then(r=>r.json())
      .then(d=>{
        if(d.status==='ok'){ closeModal(productModal); fetchProducts(); }
        else alert(d.message||'Save failed');
      })
      .catch(err=>alert('Save error '+err));
  });

  // Preview uploaded image
  const imagesFilesInput = document.getElementById('images_files');
  const imagePreview = document.getElementById('imagePreview');
  let removedImages = [];
  if(imagesFilesInput){
    imagesFilesInput.addEventListener('change', ()=>{
      imagePreview.innerHTML='';
      const fileInfo = document.getElementById('fileInfo');
      const files = Array.from(imagesFilesInput.files || []);
      if(fileInfo) fileInfo.textContent = files.length ? files.map(f=>f.name).join(', ') : '';
      files.forEach(f=>{
        const img = document.createElement('img'); img.style.maxWidth='120px'; img.style.maxHeight='120px'; img.style.objectFit='cover'; img.style.marginRight='8px';
        const reader = new FileReader(); reader.onload = ev => img.src = ev.target.result; reader.readAsDataURL(f);
        imagePreview.appendChild(img);
      });
    });
  }

  // Delegate click on remove buttons inside preview area
  imagePreview?.addEventListener('click', e=>{
    const btn = e.target.closest('.img-remove');
    if(!btn) return;
    const src = btn.getAttribute('data-src');
    // visually remove
    const wrap = btn.closest('.preview-wrap');
    if(wrap) wrap.remove();
    if(src) removedImages.push(src);
    // update fileInfo to show count of removed images (optional)
    const fileInfo = document.getElementById('fileInfo');
    if(fileInfo) fileInfo.textContent = removedImages.length ? `Removed: ${removedImages.length}` : '';
  });

  serviceForm.addEventListener('submit', e => {
    e.preventDefault();
    const fd = new FormData();
    const sid = (document.getElementById('service_id').value || '').trim();
    fd.append('action', sid ? 'edit' : 'add');
    if (sid) fd.append('service_id', sid);
    fd.append('name', document.getElementById('service_name').value.trim());
    // attach optional image file
    const svcFile = document.getElementById('service_image');
    if(svcFile && svcFile.files && svcFile.files.length>0){ fd.append('service_image', svcFile.files[0]); }
    fetch('services_api.php', {method:'POST', body:fd})
      .then(r => r.text().then(text => ({ status: r.status, ok: r.ok, text })))
      .then(resp => {
        let d;
        try {
          d = JSON.parse(resp.text);
        } catch (err) {
          // show raw response and status for debugging
          console.error('Service add raw response (status ' + resp.status + '):', resp.text);
          alert('Service error HTTP ' + resp.status + '\nResponse is not valid JSON. See console for raw response.');
          return;
        }
        if (d.status === 'ok') { loadServices(true); serviceForm.reset(); if(serviceImagePreview) serviceImagePreview.innerHTML=''; }
        else alert(d.message || 'Add failed');
      })
  .catch(err => { console.error('Fetch error', err); alert('Service error '+err); });
  });

  // Live preview for service image chooser
  const serviceImageInput = document.getElementById('service_image');
  const serviceImagePreview = document.getElementById('serviceImagePreview');
  const serviceFileInfo = document.getElementById('serviceFileInfo');
  if(serviceImageInput){
    serviceImageInput.addEventListener('change', ()=>{
      serviceImagePreview.innerHTML = '';
      const f = serviceImageInput.files && serviceImageInput.files[0];
      if(!f) { if(serviceFileInfo) serviceFileInfo.textContent=''; return; }
      if(serviceFileInfo) serviceFileInfo.textContent = f.name;
      const img = document.createElement('img'); img.style.maxWidth='120px'; img.style.maxHeight='80px'; img.style.objectFit='cover'; img.style.borderRadius='6px';
      const reader = new FileReader(); reader.onload = ev => img.src = ev.target.result; reader.readAsDataURL(f);
      serviceImagePreview.appendChild(img);
    });
  }

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
      const imgSrc = s.image ? (s.image.startsWith('http') ? s.image : ('../'+s.image.replace(/\\/g,'/'))) : '';
      const imgCell = `<td style="width:72px;">${imgSrc?`<img src="${escapeAttr(imgSrc)}" style="max-width:64px;height:auto;display:block;margin:auto;border-radius:6px;" />`:''}</td>`;
      // action buttons: edit + delete (use trash icon when image exists)
      const delBtn = s.image ? `<button class="svc-del" data-del-svc="${s.service_id}" title="Delete">🗑️</button>` : `<button class="svc-del" data-del-svc="${s.service_id}" title="Delete">✕</button>`;
      const editBtn = `<button class="svc-edit" data-edit-svc="${s.service_id}" title="Edit">✎</button>`;
      tr.innerHTML = `${imgCell}<td>${escapeHtml(s.name)}</td><td style="text-align:center;">${editBtn} ${delBtn}</td>`;
      servicesTbody.appendChild(tr);
    });
  }
  // Edit / delete handlers for services table (delegation)
  servicesTbody?.addEventListener('click', e => {
    const editBtn = e.target.closest('[data-edit-svc]');
    if (editBtn) {
      const id = editBtn.getAttribute('data-edit-svc');
      // find service in last-loaded list by making a fresh fetch
      fetch('services_api.php?action=list').then(r=>r.json()).then(d=>{
        if(d.status==='ok'){
          const svc = d.services.find(s=>String(s.service_id)===String(id));
          if(svc){
            document.getElementById('service_id').value = svc.service_id;
            document.getElementById('service_name').value = svc.name;
            // preview existing image
            const preview = document.getElementById('serviceImagePreview'); if(preview) preview.innerHTML='';
            if(svc.image){ const img = document.createElement('img'); img.src = svc.image.startsWith('http')?svc.image:('../'+svc.image.replace(/\\/g,'/')); img.style.maxWidth='120px'; img.style.maxHeight='80px'; img.style.objectFit='cover'; img.style.borderRadius='6px'; if(preview) preview.appendChild(img); }
            openModal(serviceModal);
          }
        }
      }).catch(console.error);
      return;
    }
    const delBtn = e.target.closest('[data-del-svc]');
    if (delBtn) {
      const id = delBtn.getAttribute('data-del-svc');
      if(confirm('Delete this service? This does not remove existing products.')){
        const fd = new FormData(); fd.append('action','delete'); fd.append('service_id', id);
        fetch('services_api.php', {method:'POST', body:fd}).then(r=>r.text().then(t=>({status:r.status,text:t}))).then(resp=>{
          try{ const d = JSON.parse(resp.text); if(d.status==='ok'){ loadServices(true); } else alert(d.message||'Delete failed'); }
          catch(e){ console.error('Delete raw', resp); alert('Delete failed; see console'); }
        }).catch(err=>alert('Delete error '+err));
      }
    }
  });
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