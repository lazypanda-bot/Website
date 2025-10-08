// Avatar Preview
const avatarInput = document.getElementById('avatarInput');
const avatarPreview = document.getElementById('avatarPreview');

avatarInput.addEventListener('change', function () {
  const file = this.files[0];
  if (file) {
    const reader = new FileReader();
    reader.onload = function (e) {
      avatarPreview.src = e.target.result;
    };
    reader.readAsDataURL(file);
  }
});

// Tab Switching Logic
const tabLinks = document.querySelectorAll('.settings-nav a');
const tabSections = document.querySelectorAll('.settings-tab');

tabLinks.forEach(link => {
  link.addEventListener('click', e => {
    e.preventDefault();
    const targetTab = link.getAttribute('data-tab');

    // Remove active classes
    tabLinks.forEach(l => l.classList.remove('active'));
    tabSections.forEach(s => s.classList.remove('active'));

    // Activate clicked tab and matching section
    link.classList.add('active');
    document.getElementById(targetTab).classList.add('active');
  });
});

window.addEventListener('DOMContentLoaded', () => {
const currentPage = window.location.pathname.split('/').pop();

    document.querySelectorAll('.nav-links a').forEach(link => {
        const href = link.getAttribute('href');
        if (!href || href === '#') return;

        const linkPage = href.split('/').pop().split('#')[0];

        if (currentPage === linkPage) {
            link.classList.add('active');
        }
    });
});

    // --- Product Image Manager (Admin Settings) ---
    (function(){
      const productListEl = document.getElementById('product-list');
      if (!productListEl) return;

      function el(tag, attrs={}, children=[]) {
        const e=document.createElement(tag);
        Object.entries(attrs).forEach(([k,v])=>{ if(k==='class') e.className=v; else e.setAttribute(k,v); });
        (Array.isArray(children)?children:[children]).forEach(c=>{ if(typeof c==='string') e.appendChild(document.createTextNode(c)); else if(c) e.appendChild(c); });
        return e;
      }

      function renderProduct(p){
        const wrap = el('div',{class:'product-admin-item'});
        const title = el('div',{class:'product-admin-title'}, [el('strong',{},[p.product_name + ' (₱' + p.price + ')'])]);
        wrap.appendChild(title);
        // Current images as thumbnails (support JSON array, comma-separated, or single path)
        function parseImagesField(field) {
          if (!field) return [];
          const t = field.trim();
          if (t === '') return [];
          if (t.startsWith('[')) {
            try { const parsed = JSON.parse(t); if (Array.isArray(parsed)) return parsed; } catch(e) { /* fallthrough */ }
          }
          if (t.indexOf(',') !== -1) {
            return t.split(',').map(s=>s.trim()).filter(s=>s !== '');
          }
          return [t];
        }

        const images = parseImagesField(p.images);
        function normalizeSrc(u) {
          if (!u) return u;
          if (u.startsWith('http://') || u.startsWith('https://') || u.startsWith('/')) return u;
          // stored paths are like 'uploads/...', make them root-relative
          return '/' + u.replace(/^\/+/, '');
        }
        const thumbs = el('div',{class:'product-images-list'});
        console.debug('[AdminImages] product', p.product_id, 'parsed images:', images);
        if (images.length === 0) {
          const empty = el('div',{class:'no-images'},['No images for this product']);
          thumbs.appendChild(empty);
        }
        images.forEach((imgUrl)=>{
      const t = el('div',{class:'product-thumb'});
      const src = normalizeSrc(imgUrl);
      const img = el('img',{src: src, class:'thumb-img', 'data-src': imgUrl});
          const del = el('button',{type:'button',class:'icon-btn delete-thumb'},['Delete']);
          del.addEventListener('click', ()=>{ t.remove(); });
          // ensure delete button visible
          del.style.display = 'inline-block';
          del.style.marginLeft = '8px';
          t.appendChild(img); t.appendChild(del);
          thumbs.appendChild(t);
        });

        // File input for new uploads (hidden) and Add Images button
        const fileInput = el('input',{type:'file',multiple:'',class:'image-file-input'});
        fileInput.style.display = 'none';
        let selectedFiles = []; // array of File objects
        const addBtn = el('button',{type:'button',class:'edit-btn add-images-btn'},['Add Images']);
        addBtn.addEventListener('click', ()=> fileInput.click());

        // Preview selected files
        fileInput.addEventListener('change', (ev)=>{
          const files = Array.from(ev.target.files || []);
          files.forEach((f, idx) => {
            selectedFiles.push(f);
            const t = el('div',{class:'product-thumb new-thumb'});
            const img = el('img',{class:'thumb-img'});
            const reader = new FileReader();
            reader.onload = function(e){ img.src = e.target.result; };
            reader.readAsDataURL(f);
            const del = el('button',{type:'button',class:'icon-btn delete-thumb'},['Delete']);
            const fileIndex = selectedFiles.length - 1;
            del.addEventListener('click', ()=>{ selectedFiles[fileIndex] = null; t.remove(); });
            t.appendChild(img); t.appendChild(del);
            thumbs.appendChild(t);
          });
          // reset input so same file can be selected again if needed
          fileInput.value = '';
        });

        const saveBtn = el('button',{type:'button',class:'edit-btn'},['Save']);
        saveBtn.addEventListener('click', async ()=>{
          saveBtn.disabled = true;
          try{
            // Gather kept existing images (those with data-src)
            const kept = Array.from(thumbs.querySelectorAll('img.thumb-img')).map(i=>i.getAttribute('data-src')).filter(Boolean);
            // Normalize kept paths: strip leading slash so DB stores consistent 'uploads/...' paths
            const normalizedKept = kept.map(u => u ? u.replace(/^\/+/, '') : u);
            const fd = new FormData();
            fd.append('action','save');
            fd.append('product_id', p.product_id);
            fd.append('product_name', p.product_name);
            fd.append('service_type', p.service_type || '');
            fd.append('price', p.price || 0);
            fd.append('product_details', p.product_details || '');
            fd.append('images_current', JSON.stringify(normalizedKept));
            // append selectedFiles
            for(let i=0;i<selectedFiles.length;i++){ if (selectedFiles[i]) fd.append('images_files[]', selectedFiles[i]); }
            const res = await fetch('products_api.php?action=save',{method:'POST',body:fd});
            const data = await res.json();
            console.debug('[AdminSave] response', data);
            if(data.status==='ok'){
              alert('Saved');
              // reload product render
              load();
            } else { throw new Error(data.message || 'Save failed'); }
          }catch(e){ alert('Save failed: '+e.message); }
          saveBtn.disabled = false;
        });

  wrap.appendChild(thumbs);
  // add the hidden file input and the visible Add Images button
  wrap.appendChild(fileInput);
  wrap.appendChild(addBtn);
        wrap.appendChild(saveBtn);
        return wrap;
      }

      async function load(){
        productListEl.innerHTML = '<div class="loader">Loading products…</div>';
        try{
          const res = await fetch('products_api.php?action=list');
          const data = await res.json();
          if(data.status !== 'ok'){ productListEl.innerHTML = 'Failed to load'; return; }
          productListEl.innerHTML = '';
          data.products.forEach(p=> productListEl.appendChild(renderProduct(p)) );
        }catch(e){ productListEl.innerHTML = 'Error loading products: '+e.message; }
      }

      load();
    })();