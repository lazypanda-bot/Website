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
    // Track the product row currently hovered so header + buttons know which product to target
    let currentHoverPid = '';
    const productModal = document.getElementById('productModal');
    const serviceModal = document.getElementById('serviceModal');
    const openProductBtn = document.getElementById('openProductModal');
    const openServiceBtn = document.getElementById('openServiceModal');
    const productForm = document.getElementById('productForm');
    const serviceForm = document.getElementById('serviceForm');
    const servicesTbody = document.getElementById('servicesTbody');
    const productModalTitle = document.getElementById('productModalTitle');
    const serviceTypeList = document.getElementById('serviceTypeList');
    // Variants table elements
    const variantsRows = document.getElementById('variants_rows');
    const variantsSection = document.getElementById('variantsSection');
    const toggleVariantsNow = document.getElementById('toggleVariantsNow');
    const addVariantBtn = document.getElementById('addVariantBtn');
    // Quick Variant Modal elements
    const quickVariantModal = document.getElementById('quickVariantModal');
    const quickVariantForm = document.getElementById('quickVariantForm');
    const qv_product_id = document.getElementById('qv_product_id');
    // Quick modal panels and controls
    const qv_variants_panel = document.getElementById('qv_variants_panel');
    const qv_types_panel = document.getElementById('qv_types_panel');
    const qv_attrs_panel = document.getElementById('qv_attrs_panel');
    const qv_sizes_panel = document.getElementById('qv_sizes_panel');
    const qv_var_list = document.getElementById('qv_var_list');
    const qv_type_list = document.getElementById('qv_type_list');
    const qv_attr_list = document.getElementById('qv_attr_list');
    const qv_size_list = document.getElementById('qv_size_list');
    const qv_add_var_row = document.getElementById('qv_add_var_row');
    const qv_add_type_row = document.getElementById('qv_add_type_row');
    const qv_add_attr_row = document.getElementById('qv_add_attr_row');
    const qv_add_size_row = document.getElementById('qv_add_size_row');
    // Track existing-image removals and new selected files
    let removedImages = [];
    const newFilesMap = new Map();
    // Cache of last fetched products
    let productsCache = [];

    function openModal(modal){ modal.style.display='flex'; }
    function closeModal(modal){ modal.style.display='none'; }
    document.querySelectorAll('[data-close]').forEach(btn=>btn.addEventListener('click',()=>closeModal(btn.closest('.modal'))));

    openProductBtn?.addEventListener('click', () => { resetProductForm(); productModalTitle.textContent='Add Product'; setVariantsVisible(false); openModal(productModal); });
    openServiceBtn?.addEventListener('click', () => { serviceForm.reset(); loadServices(); openModal(serviceModal); });

    function renderProducts(list, subsMap){
        tbody.innerHTML='';
        if(!list || list.length===0){
            tbody.innerHTML = '<tr><td colspan="9" class="empty-row">No products yet</td></tr>';
            return;
        }
                // Collect service types for datalist and filter select
                const serviceTypes = new Set();
                list.forEach(p=>{ if(p.service_type) serviceTypes.add(p.service_type); });
                serviceTypeList.innerHTML = Array.from(serviceTypes).map(s=>`<option value="${escapeHtml(s)}"></option>`).join('');
                // populate service filter select as well (if present)
                if(typeof populateServiceFilter === 'function') populateServiceFilter(Array.from(serviceTypes));

    list.forEach(p=>{
    const tr = document.createElement('tr');
        const imgs = parseImages(p.images);
        const hasReal = imgs && imgs.length>0;
        const imgSrc = hasReal ? normalizeImagePath(imgs[0]) : '';
        // render variants: if stored as JSON array, join; if string, show raw string
        let variantsDisplay = '';
        try {
            if (p.variants) {
                if (typeof p.variants === 'string') {
                    const trimmed = p.variants.trim();
                    if (trimmed.startsWith('[') || trimmed.startsWith('{')) {
                        const parsed = JSON.parse(trimmed);
                        if (Array.isArray(parsed)) {
                            // array may contain simple strings or objects
                            if (parsed.length>0 && typeof parsed[0] === 'object') {
                                variantsDisplay = parsed.map(it => {
                                    if(!it) return '';
                                    const n = it.name || it.variant || '';
                                    const p = (typeof it.price !== 'undefined' && it.price !== '') ? ('₱'+Number(it.price).toFixed(2)) : '';
                                    return (n || p) ? (n + (p ? ' ' + p : '')) : JSON.stringify(it);
                                }).filter(Boolean).join(', ');
                            } else {
                                variantsDisplay = parsed.join(', ');
                            }
                        }
                        else if (parsed && typeof parsed === 'object') {
                            const parts = [];
                            if (parsed.type) parts.push('Type: ' + (Array.isArray(parsed.type) ? parsed.type.join(', ') : String(parsed.type)));
                            if (parsed.color) parts.push('Color: ' + (Array.isArray(parsed.color) ? parsed.color.join(', ') : String(parsed.color)));
                            if (parsed.size) parts.push('Size: ' + (Array.isArray(parsed.size) ? parsed.size.join(', ') : String(parsed.size)));
                            if (parsed.price) {
                                // price may be array of objects or strings
                                if (Array.isArray(parsed.price)) {
                                    const pr = parsed.price.map(it => {
                                        if (it && typeof it === 'object') return (it.name || '') + (typeof it.price !== 'undefined' && it.price !== '' ? '(' + Number(it.price).toFixed(2) + ')' : '');
                                        return String(it || '');
                                    }).filter(Boolean).join(', ');
                                    if (pr) parts.push('Price: ' + pr);
                                } else {
                                    parts.push('Price: ' + String(parsed.price));
                                }
                            }
                            variantsDisplay = parts.join('; ');
                        } else variantsDisplay = trimmed;
                    } else {
                        variantsDisplay = trimmed;
                    }
                } else if (Array.isArray(p.variants)) {
                    const arr = p.variants;
                    if (arr.length>0 && typeof arr[0] === 'object') {
                        variantsDisplay = arr.map(it => {
                            if(!it) return '';
                            const n = it.name || it.variant || '';
                            const pr = (typeof it.price !== 'undefined' && it.price !== '') ? ('₱'+Number(it.price).toFixed(2)) : '';
                            return (n || pr) ? (n + (pr ? ' ' + pr : '')) : JSON.stringify(it);
                        }).filter(Boolean).join(', ');
                    } else {
                        variantsDisplay = arr.join(', ');
                    }
                } else if (typeof p.variants === 'object') {
                    // already parsed object
                    const parsed = p.variants;
                    const parts = [];
                    if (parsed.type) parts.push('Type: ' + (Array.isArray(parsed.type) ? parsed.type.join(', ') : String(parsed.type)));
                    if (parsed.color) parts.push('Color: ' + (Array.isArray(parsed.color) ? parsed.color.join(', ') : String(parsed.color)));
                    if (parsed.size) parts.push('Size: ' + (Array.isArray(parsed.size) ? parsed.size.join(', ') : String(parsed.size)));
                    if (parsed.price) {
                        if (Array.isArray(parsed.price)) {
                            const pr = parsed.price.map(it => (it && typeof it === 'object') ? (it.name || '') + (typeof it.price !== 'undefined' && it.price !== '' ? '(' + Number(it.price).toFixed(2) + ')' : '') : String(it || '') ).filter(Boolean).join(', ');
                            if (pr) parts.push('Price: ' + pr);
                        } else parts.push('Price: ' + String(parsed.price));
                    }
                    variantsDisplay = parts.join('; ');
                } else {
                    variantsDisplay = String(p.variants || '');
                }
            }
        } catch (err) { variantsDisplay = String(p.variants || ''); }

        // Build attributes (price-dependent) dropdown
        let attrs = [];
        if (subsMap && subsMap[p.product_id] && Array.isArray(subsMap[p.product_id].attributes)) {
            attrs = subsMap[p.product_id].attributes.slice();
        } else {
            try{
                const rawWp = p.wherepricedepends;
                if (rawWp) {
                    const wp = (typeof rawWp === 'string') ? JSON.parse(rawWp) : rawWp;
                    if (Array.isArray(wp)){
                        const seen = new Map();
                        wp.forEach(entry => {
                            if (entry && Array.isArray(entry.others)){
                                entry.others.forEach(o => {
                                    const nm = (o && o.name) ? String(o.name).trim() : '';
                                    if (!nm) return;
                                    const key = nm.toLowerCase();
                                    const pr = (o && (o.price !== undefined && o.price !== '')) ? Number(o.price) : 0;
                                    if (!seen.has(key)) { seen.set(key, true); attrs.push({ name: nm, price: pr }); }
                                });
                            }
                        });
                    }
                }
            } catch(e) { /* ignore */ }
        }
        const attrsHtml = (
            `<div class="attrs-wrap">`
            + `<select class="attr-select" data-pid="${p.product_id}" style="display:none">`
            + `<option value="">Select attribute</option>`
            + attrs.map(a => `<option value="${escapeAttr(a.name)}" data-price="${Number(a.price).toFixed(2)}">${escapeHtml(a.name)}</option>`).join('')
            + `</select>`
            + `<div class="dd" data-kind="attribute" data-pid="${p.product_id}">`
            + `  <button type="button" class="dd-btn">Select attribute</button>`
            + `  <div class="dd-menu">`
            + attrs.map(a => `<div class="dd-item" data-value="${escapeAttr(a.name)}" data-price="${Number(a.price).toFixed(2)}"><span class="dd-label">${escapeHtml(a.name)}</span><button class="dd-x" title="Remove" data-kind="attribute" data-pid="${p.product_id}" data-value="${escapeAttr(a.name)}">✕</button></div>`).join('')
            + `  </div>`
            + `</div>`
            + `</div>`
        );

        // Build variant-name, type, and size dropdowns (prefer products_sub map; fallback to variants JSON)
        let nameOptions = [];
        let typeOptions = [];
        let sizeOptions = [];
        if (subsMap && subsMap[p.product_id]) {
            typeOptions = Array.isArray(subsMap[p.product_id].types) ? subsMap[p.product_id].types.slice() : [];
            sizeOptions = Array.isArray(subsMap[p.product_id].sizes) ? subsMap[p.product_id].sizes.slice() : [];
        }
        try{
            let pv = null;
            if (typeof p.variants === 'string'){
                const t = p.variants.trim();
                if (t.startsWith('[') || t.startsWith('{')) pv = JSON.parse(t);
                else pv = t ? t.split(',').map(s=>s.trim()).filter(Boolean) : [];
            } else {
                pv = p.variants;
            }
            if (Array.isArray(pv)){
                if (pv.length>0 && typeof pv[0] === 'object'){
                    pv.forEach(it => {
                        if(!it) return;
                        const nm = String(it.name || it.variant || '').trim();
                        const tp = String(it.type || '').trim();
                        const sz = String(it.size || '').trim();
                        if (nm) nameOptions.push(nm);
                        if (tp) typeOptions.push(tp);
                        if (sz) sizeOptions.push(sz);
                    });
                } else {
                    pv.forEach(v => { if (v) nameOptions.push(String(v)); });
                }
            } else if (pv && typeof pv === 'object'){
                // legacy object: if price array present
                if (Array.isArray(pv.price)){
                    pv.price.forEach(it => {
                        if (it && typeof it === 'object'){
                            const nm = String(it.name || '').trim();
                            if(nm) nameOptions.push(nm);
                        }
                    });
                }
            }
        } catch(e){ /* ignore */ }
        // de-duplicate (case-insensitive)
        if (nameOptions.length){ const s = new Set(); nameOptions = nameOptions.filter(n=>{ const k=n.toLowerCase(); if(s.has(k)) return false; s.add(k); return true; }); }
        if (typeOptions.length){ const s2 = new Set(); typeOptions = typeOptions.filter(n=>{ const k=n.toLowerCase(); if(s2.has(k)) return false; s2.add(k); return true; }); }
        if (sizeOptions.length){ const s3 = new Set(); sizeOptions = sizeOptions.filter(n=>{ const k=n.toLowerCase(); if(s3.has(k)) return false; s3.add(k); return true; }); }
        // We currently don't render a separate Variant column in the table; keep namesHtml unused
        const namesHtml = '';
        const typesHtml = (
            `<div class="types-wrap">`
            + `<select class="type-select" data-pid="${p.product_id}" style="display:none">`
            + `<option value="">Select type</option>`
            + (typeOptions.map(t => `<option value="${escapeAttr(t)}">${escapeHtml(t)}</option>`).join(''))
            + `</select>`
            + `<div class="dd" data-kind="type" data-pid="${p.product_id}">`
            + `  <button type="button" class="dd-btn">Select type</button>`
            + `  <div class="dd-menu">`
            + typeOptions.map(t => `<div class="dd-item" data-value="${escapeAttr(t)}"><span class="dd-label">${escapeHtml(t)}</span><button class="dd-x" title="Remove" data-kind="type" data-pid="${p.product_id}" data-value="${escapeAttr(t)}">✕</button></div>`).join('')
            + `  </div>`
            + `</div>`
            + `</div>`
        );
        const sizesHtml = (
            `<div class="sizes-wrap">`
            + `<select class="size-select" data-pid="${p.product_id}" style="display:none">`
            + `<option value="">Select size</option>`
            + (sizeOptions.map(s => `<option value="${escapeAttr(s)}">${escapeHtml(s)}</option>`).join(''))
            + `</select>`
            + `<div class="dd" data-kind="size" data-pid="${p.product_id}">`
            + `  <button type="button" class="dd-btn">Select size</button>`
            + `  <div class="dd-menu">`
            + sizeOptions.map(s => `<div class="dd-item" data-value="${escapeAttr(s)}"><span class="dd-label">${escapeHtml(s)}</span><button class="dd-x" title="Remove" data-kind="size" data-pid="${p.product_id}" data-value="${escapeAttr(s)}">✕</button></div>`).join('')
            + `  </div>`
            + `</div>`
            + `</div>`
        );

        tr.innerHTML = `
            <td>${escapeHtml(p.service_type||'')}</td>
            <td>${imgSrc?`<img src="${escapeAttr(imgSrc)}" alt="thumb" class="product-thumb" onerror="this.classList.add('broken'); this.src='../img/logo.png'" />`:`<div class="no-thumb" aria-hidden="true"></div>`}</td>
            <td>${escapeHtml(p.product_name)}</td>
            <td class="td-type">${typesHtml}</td>
            <td class="td-size">${sizesHtml}</td>
            <td class="td-attribute">${attrsHtml}</td>
            <td class="td-price" data-base="${Number(p.price).toFixed(2)}">₱<span class="price-amount">${Number(p.price).toFixed(2)}</span></td>
            <td class="td-ellipsis">${escapeHtml(p.product_details||'')}</td>
            <td class="td-actions">
                <div class="actions-top">
                    <button class="action-icon-btn edit" title="Edit" data-edit="${p.product_id}"><i class="fas fa-edit"></i></button>
                    <button class="action-icon-btn delete" title="Delete" data-del="${p.product_id}"><i class="fas fa-trash"></i></button>
                </div>
            </td>`;
            tbody.appendChild(tr);
        });
    }

    function fetchProducts(){
        // Fetch products first, then sub-items in parallel for mapping
        fetch('products-api.php?action=list')
            .then(async r => {
                const text = await r.text();
                let data;
                try { data = JSON.parse(text); } catch(e){ console.error('products list parse', e, text); return; }
                if (data.status !== 'ok') { console.error('products list error', data); return; }
                productsCache = data.products || [];
                // fetch subs
                return fetch('products-api.php?action=sub_list_all')
                    .then(r2 => r2.json())
                    .then(sub => {
                        const map = (sub && sub.status==='ok') ? (sub.byProduct || {}) : {};
                        renderProducts(productsCache, map);
                    })
                    .catch(()=> renderProducts(productsCache, {}));
            })
            .catch(err=>console.error('List error',err));
    }
    // Update currentHoverPid when hovering rows
    tbody.addEventListener('mouseover', (e)=>{
        const tr = e.target.closest('tr');
        if (!tr) return;
        // find any control inside row with data-pid to read product id
        const anyCtl = tr.querySelector('[data-pid]');
        if (anyCtl) currentHoverPid = String(anyCtl.getAttribute('data-pid')) || '';
    });

    // Header + buttons for Type/Size/Attribute
    document.addEventListener('click', (e)=>{
        const btn = e.target.closest('.qv-head-add');
        if (!btn) return;
        const kind = btn.getAttribute('data-kind') || 'types';
        if (!currentHoverPid) { alert('Hover a product row, then click + to add.'); return; }
        if (qv_product_id) qv_product_id.value = currentHoverPid;
        if (quickVariantForm) quickVariantForm.reset();
        if (qv_type_list) qv_type_list.innerHTML = '';
        if (qv_size_list) qv_size_list.innerHTML = '';
        if (qv_attr_list) qv_attr_list.innerHTML = '';
        if (kind === 'types') {
            setQVTab('types');
            if (qv_type_list && qv_type_list.childElementCount === 0) qv_type_list.appendChild(createQVTypeRow());
        } else if (kind === 'sizes') {
            setQVTab('sizes');
            if (qv_size_list && qv_size_list.childElementCount === 0) qv_size_list.appendChild(createQVSizeRow());
        } else {
            setQVTab('attributes');
            if (qv_attr_list && qv_attr_list.childElementCount === 0) qv_attr_list.appendChild(createQVAttrRow());
        }
        if (quickVariantModal) openModal(quickVariantModal);
    });

    function resetProductForm(){
        productForm.reset();
        document.getElementById('product_id').value='';
        // clear variants table rows (do not auto-add card unless variants are visible)
        if(variantsRows) variantsRows.innerHTML = '';
        // clear previews and file info
        const preview = document.getElementById('imagePreview'); if(preview) preview.innerHTML='';
        const fileInfo = document.getElementById('fileInfo'); if(fileInfo) fileInfo.textContent='';
        const fileInput = document.getElementById('images_files'); if(fileInput) fileInput.value='';
        removedImages = [];
        // clear any staged new files
        newFilesMap.clear();
    }

    // variant row factories
    // Create a table row for a variant: name, type, size, nested colors table (color + price pairs)
    // plus optional separate attributes (box, pockets, custom attributes)
    function createVariantRow(name, type, size, colors, wherepriced){
        // Create a card-like block for each variant (separate boxes for each attribute)
        const card = document.createElement('div'); card.className = 'variant-card';
        card.dataset.variantId = 'v' + Date.now().toString(36) + Math.floor(Math.random()*9000+1000).toString(36);

    const nameBox = document.createElement('div'); nameBox.className = 'variant-box variant-box-name';
    const typeBox = document.createElement('div'); typeBox.className = 'variant-box variant-box-type';
    const sizeBox = document.createElement('div'); sizeBox.className = 'variant-box variant-box-size';
    const colorsBox = document.createElement('div'); colorsBox.className = 'variant-box variant-box-colors';

        // simple list makers for name/type/size (allow multiple entries)
        function createSimpleRow(value, cls, placeholder){
            const r = document.createElement('div'); r.className='simple-row';
            const col = document.createElement('div'); col.className='simple-col';
            const inp = document.createElement('input'); inp.type='text'; inp.className=cls; inp.placeholder=placeholder; inp.value = value || '';
            col.appendChild(inp);
            r.appendChild(col);
            return r;
        }
        const nameList = document.createElement('div'); nameList.className='simple-list name-list';
        const typeList = document.createElement('div'); typeList.className='simple-list type-list';
        const sizeList = document.createElement('div'); sizeList.className='simple-list size-list';
        // seed initial rows
        const seedNames = Array.isArray(name) ? name : [(name||'')];
        const seedTypes = Array.isArray(type) ? type : [(type||'')];
        const seedSizes = Array.isArray(size) ? size : [(size||'')];
        seedNames.forEach(v=> nameList.appendChild(createSimpleRow(v, 'variant-name', 'Variant name')));
        seedTypes.forEach(v=> typeList.appendChild(createSimpleRow(v, 'variant-type', 'Type')));
        seedSizes.forEach(v=> sizeList.appendChild(createSimpleRow(v, 'variant-size', 'Size')));

        // colors removed per request; keep attributes only

    // Optional separate attribute inputs (custom attributes list only)
        const attrWrap = document.createElement('div'); attrWrap.className = 'variant-attrs-wrap';
    // Box/Pockets removed
    // Custom attributes list (stacked rows)
    const othersList = document.createElement('div'); othersList.className = 'others-list';

        function createOtherRow(key, pr){
            const r = document.createElement('div'); r.className = 'other-row';
            const ktd = document.createElement('div'); ktd.className = 'other-col';
            const ptd = document.createElement('div'); ptd.className = 'other-price-col';
            const act = document.createElement('div'); act.className = 'other-act';
            const keyInp = document.createElement('input'); keyInp.type='text'; keyInp.className='other-attr-name'; keyInp.placeholder='Attribute (e.g., 2-color print, With box)'; keyInp.value = key || '';
            const priceInp = document.createElement('input'); priceInp.type='number'; priceInp.className='other-attr-price'; priceInp.placeholder='Price'; priceInp.step='0.01'; priceInp.min='0'; priceInp.value = (typeof pr !== 'undefined' && pr !== null && pr !== '') ? String(pr) : '';
            const rem = document.createElement('button'); rem.type='button'; rem.className='remove-other'; rem.textContent='✕'; rem.title='Remove attribute'; rem.addEventListener('click', ()=>r.remove());
            ktd.appendChild(keyInp); ptd.appendChild(priceInp); act.appendChild(rem);
            r.appendChild(ktd); r.appendChild(ptd); r.appendChild(act);
            return r;
        }

        // populate provided wherepriced info if present
        try{
            if(wherepriced && typeof wherepriced === 'object'){
                if(Array.isArray(wherepriced.others)){
                    wherepriced.others.forEach(o=> othersList.appendChild(createOtherRow(o.name||'', o.price||'')));
                }
            }
        }catch(e){ /* ignore malformed */ }

    // header buttons will handle add actions (attributes only)

        // remove button appended to actions box below

    // assemble boxes into card; add headers with small action buttons
    const nameHeader = document.createElement('div'); nameHeader.className='box-header';
    const nameTitle = document.createElement('div'); nameTitle.className='title'; nameTitle.textContent='Variant Name';
    nameHeader.appendChild(nameTitle);
    nameBox.appendChild(nameHeader); nameBox.appendChild(nameList);

    const typeHeader = document.createElement('div'); typeHeader.className='box-header';
    const typeTitle = document.createElement('div'); typeTitle.className='title'; typeTitle.textContent='Type';
    typeHeader.appendChild(typeTitle);
    typeBox.appendChild(typeHeader); typeBox.appendChild(typeList);

    const sizeHeader = document.createElement('div'); sizeHeader.className='box-header';
    const sizeTitle = document.createElement('div'); sizeTitle.className='title'; sizeTitle.textContent='Size';
    sizeHeader.appendChild(sizeTitle);
    sizeBox.appendChild(sizeHeader); sizeBox.appendChild(sizeList);

    // removed attribute add button in header per request

        // attributes container (others only)
    const attrsContainer = document.createElement('div'); attrsContainer.className = 'variant-attrs-container';
    attrsContainer.appendChild(othersList);
        colorsBox.appendChild(attrsContainer);

        card.appendChild(nameBox);
        // Type box is distinct from Variant Name
        card.appendChild(typeBox);
        card.appendChild(sizeBox);
    card.appendChild(colorsBox);
        return card;
    }

    // show/hide variants section
    function setVariantsVisible(show){
        if(!variantsSection) return;
        if (show) {
            variantsSection.classList.remove('hidden');
            if (toggleVariantsNow) toggleVariantsNow.checked = true;
            // seed one card if empty
            if (variantsRows && variantsRows.childElementCount === 0) {
                const r = createVariantRow('', '', '', [], null);
                variantsRows.appendChild(r);
            }
        } else {
            variantsSection.classList.add('hidden');
            if (toggleVariantsNow) toggleVariantsNow.checked = false;
        }
    }
    toggleVariantsNow?.addEventListener('change', ()=> setVariantsVisible(toggleVariantsNow.checked));
    // initial state hidden by default
    if (variantsSection) setVariantsVisible(false);

    // wire add variant button
    if(addVariantBtn) addVariantBtn.addEventListener('click', ()=>{ 
        if(!variantsRows) return;
        const r = createVariantRow('','', '', [], null);
        variantsRows.appendChild(r);
        // focus the name input of the newly added card for faster entry
        const nameInput = r.querySelector('.variant-name'); if(nameInput) nameInput.focus();
    });
    // Parse an images field from the DB into an array of real image paths.
    // This will filter out empty values and known fallback logo entries so
    // previews/thumbnails only show when there are actual uploaded images.
    function parseImages(imagesField){
        if(!imagesField) return [];
        const t = String(imagesField).trim();
        if(!t) return [];
        let arr = [];
        if(t.startsWith('[')){
            try { const parsed = JSON.parse(t); if(Array.isArray(parsed)) arr = parsed.slice(); }
            catch(e){ arr = []; }
        } else if(t.includes(',')){
            arr = t.split(',').map(s=>s.trim());
        } else arr = [t];
        // Normalize entries and remove empties
        arr = arr.map(s=>String(s||'').trim()).filter(Boolean);
        // Deduplicate while preserving order (normalize slashes and case for comparison)
        const seen = new Set();
        const unique = [];
        arr.forEach(p => {
            if(!p) return;
            const key = String(p).replace(/\\/g,'/').replace(/^\/+/, '').toLowerCase();
            if(seen.has(key)) return;
            seen.add(key);
            unique.push(p);
        });
        arr = unique;
        // Treat any reference to the site logo as a fallback (not a real uploaded image)
        const fallbacks = new Set(['/img/logo.png','img/logo.png','../img/logo.png','logo.png']);
        arr = arr.filter(s => !fallbacks.has(s));
        return arr;
    }

    function firstImage(imagesField){
        const imgs = parseImages(imagesField);
        if(imgs.length>0) return imgs[0];
        // no real images, return the site logo as a fallback
        return '/img/logo.png';
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
        const qvBtn = e.target.closest('[data-qv-add]');
        if(qvBtn){
            const id = qvBtn.getAttribute('data-qv-add');
            if(qv_product_id) qv_product_id.value = id;
            if(quickVariantForm){ quickVariantForm.reset(); }
            // clear lists first then activate Types so it seeds a first row
            if(qv_type_list){ qv_type_list.innerHTML=''; }
            if(qv_size_list){ qv_size_list.innerHTML=''; }
            if(qv_attr_list){ qv_attr_list.innerHTML=''; }
            setQVTab('types');
            // Ensure at least one input row exists for Types
            if(qv_type_list && qv_type_list.childElementCount===0){ qv_type_list.appendChild(createQVTypeRow()); }
            // Keep price typed as number field empty by default
            openModal(quickVariantModal);
            return;
        }
        const editBtn = e.target.closest('[data-edit]');
        if(editBtn){
            const id = editBtn.getAttribute('data-edit');
            // Find row data by reading current table (could also store last fetch list)
            // Simpler: refetch and fill when found
            fetch('products-api.php?action=list').then(async r => {
                const text = await r.text();
                try {
                    const d = JSON.parse(text);
                    if (d.status === 'ok'){
                        const prod = d.products.find(p=>String(p.product_id)===String(id));
                        if(prod){
                            productModalTitle.textContent='Edit Product';
                            openModal(productModal);
                            document.getElementById('product_id').value = prod.product_id;
                            document.getElementById('product_name').value = prod.product_name;
                            document.getElementById('service_type').value = prod.service_type || '';
                            const priceEl = document.getElementById('price');
                            if (priceEl) priceEl.value = prod.price;
                            document.getElementById('product_details').value = prod.product_details || '';
                            setVariantsVisible(false); // default to details edit only
                            // product images are handled via uploads; clear any text-input handling
                            // populate preview area with existing images if any
                            const preview = document.getElementById('imagePreview');
                            preview.innerHTML = '';
                            if(prod.images){
                                const imgs = parseImages(prod.images);
                                // Only show previews when there are actual uploaded images (not just the fallback)
                                imgs.slice(0,4).forEach(src=>{
                                    const wrap = document.createElement('div'); wrap.className='preview-wrap'; wrap.style.display='inline-block'; wrap.style.position='relative'; wrap.style.marginRight='8px';
                                    // store the original server path on the wrapper for later serialization
                                    wrap.setAttribute('data-src', src);
                                    const img = document.createElement('img'); img.src = normalizeImagePath(src); img.style.maxWidth='120px'; img.style.maxHeight='120px'; img.style.objectFit='cover'; img.onerror = ()=>{ img.src='/img/logo.png'; img.classList.add('broken'); };
                                    const removeBtn = document.createElement('button'); removeBtn.className='img-remove'; removeBtn.type='button'; removeBtn.title='Remove image'; removeBtn.setAttribute('data-src', src);
                                    removeBtn.textContent='✕';
                                    wrap.appendChild(img); wrap.appendChild(removeBtn); preview.appendChild(wrap);
                                });
                            }
                            // populate variants table rows
                            if(variantsRows) variantsRows.innerHTML = '';
                            if(prod.variants){
                                try{
                                    let pv = null;
                                    if (typeof prod.variants === 'string'){
                                        const t = prod.variants.trim();
                                        if (t.startsWith('[') || t.startsWith('{')) pv = JSON.parse(t);
                                        else pv = t ? t.split(',').map(s=>s.trim()).filter(Boolean) : [];
                                    } else {
                                        pv = prod.variants;
                                    }
                                    // try to parse wherepricedepends (may be string or object)
                                    let wp = null;
                                    try{ const raw = prod.wherepricedepends || prod.wherePriceDepends || prod.wherepriced; wp = (typeof raw === 'string' && raw.trim()) ? JSON.parse(raw) : raw; } catch(e){ wp = null; }

                                    if(Array.isArray(pv)){
                                        // array of variant items -> if items are objects use fields, otherwise treat as name-only
                                        pv.forEach((it, idx) => {
                                            const wpEntry = Array.isArray(wp) ? wp[idx] : (wp && typeof wp === 'object' ? ( (it && it.name && wp[it.name]) ? wp[it.name] : null ) : null);
                                            if(it && typeof it === 'object'){
                                                const name = it.name || it.variant || '';
                                                const type = it.type || '';
                                                const size = it.size || '';
                                                // support nested colors array or legacy single color+price
                                                if(Array.isArray(it.colors) && it.colors.length>0){
                                                    variantsRows.appendChild(createVariantRow(name, type, size, it.colors, wpEntry));
                                                } else if (Array.isArray(it.color) || Array.isArray(it.price)){
                                                    const cols = [];
                                                    const maxLen = Math.max((it.color||[]).length, (it.price||[]).length);
                                                    for(let i=0;i<maxLen;i++) cols.push({ color: (it.color||[])[i]||'', price: (it.price||[])[i]||'' });
                                                    variantsRows.appendChild(createVariantRow(name, type, size, cols, wpEntry));
                                                } else {
                                                    const singleColor = (typeof it.color !== 'undefined') ? it.color : '';
                                                    const singlePrice = (typeof it.price !== 'undefined') ? it.price : '';
                                                    const cols = (singleColor !== '' || singlePrice !== '') ? [{color: singleColor, price: singlePrice}] : [];
                                                    variantsRows.appendChild(createVariantRow(name, type, size, cols, wpEntry));
                                                }
                                            } else {
                                                variantsRows.appendChild(createVariantRow(String(it||''),'','',[], wpEntry));
                                            }
                                        });
                                    } else if (pv && typeof pv === 'object') {
                                        // legacy structured object with separate arrays: try to build rows from price array if available
                                        if(Array.isArray(pv.price) && pv.price.length>0){
                                            pv.price.forEach((item, idx) => {
                                                const wpEntry = Array.isArray(wp) ? wp[idx] : null;
                                                if(typeof item === 'object'){
                                                    const n1 = item.name || (pv.type && pv.type[idx] ? pv.type[idx] : '') || '';
                                                    const t1 = (pv.type && pv.type[idx]) ? pv.type[idx] : (pv.type && pv.type[0]) || '';
                                                    const c1 = (pv.color && pv.color[idx]) ? pv.color[idx] : (pv.color && pv.color[0]) || '';
                                                    const s1 = item.size || (pv.size && pv.size[idx]) || '';
                                                    const p1 = item.price || '';
                                                    variantsRows.appendChild(createVariantRow(n1, t1, s1, [{ color: c1, price: p1 }], wpEntry));
                                                } else {
                                                    const n2 = (pv.type && pv.type[idx]) ? pv.type[idx] : '';
                                                    const t2 = (pv.type && pv.type[idx]) ? pv.type[idx] : '';
                                                    const c2 = (pv.color && pv.color[idx]) ? pv.color[idx] : '';
                                                    const s2 = (pv.size && pv.size[idx]) ? pv.size[idx] : '';
                                                    variantsRows.appendChild(createVariantRow(String(item||''), t2, s2, [{ color: c2, price: '' }], wpEntry));
                                                }
                                            });
                                        } else {
                                            // fallback: try to use type/color arrays to create rows
                                            const maxLen = Math.max((pv.type||[]).length, (pv.color||[]).length, (pv.size||[]).length);
                                            if(maxLen>0){
                                                for(let i=0;i<maxLen;i++){
                                                    const name = (pv.type && pv.type[i]) ? pv.type[i] : '';
                                                    const type = (pv.type && pv.type[i]) ? pv.type[i] : '';
                                                    const color = (pv.color && pv.color[i]) ? pv.color[i] : '';
                                                    const size = (pv.size && pv.size[i]) ? pv.size[i] : '';
                                                    const price = '';
                                                    const wpEntry = Array.isArray(wp) ? wp[i] : null;
                                                    variantsRows.appendChild(createVariantRow(name, type, size, [{ color: color, price: price }], wpEntry));
                                                }
                                            } else {
                                                // as last resort, stringify the object into a single row
                                                variantsRows.appendChild(createVariantRow(JSON.stringify(pv),'','','',''));
                                            }
                                        }
                                    } else if (typeof pv === 'string') {
                                        // CSV string -> one row per entry
                                        pv.split(',').map(s=>s.trim()).filter(Boolean).forEach(v=>{ variantsRows.appendChild(createVariantRow(v,'','',[], null)); });
                                    }
                                }catch(err){ console.warn('Could not parse variants', err); }
                            }
                            // If no variant cards were added (no existing data), show one empty card by default
                            // keep hidden until user toggles or opens via Variants button
                        }
                    } else {
                        console.error('products list error', d);
                    }
                } catch (e) {
                    console.error('products-api returned non-JSON (edit fetch)', r.status, text);
                }
            }).catch(err=>console.error('Edit fetch error', err));
        }
        const variantsBtn = e.target.closest('[data-variants]');
        if(variantsBtn){
            const id = variantsBtn.getAttribute('data-variants');
            // Reuse edit flow then show variants
            const fake = document.createElement('span'); fake.setAttribute('data-edit', id); // trigger edit branch above
            fake.click = null; // no-op
            // call same fetch logic by simulating click on edit or reusing code
            fetch('products-api.php?action=list').then(async r => {
                const text = await r.text();
                try {
                    const d = JSON.parse(text);
                    if (d.status === 'ok'){
                        const prod = d.products.find(p=>String(p.product_id)===String(id));
                        if(prod){
                            productModalTitle.textContent='Edit Product';
                            openModal(productModal);
                            document.getElementById('product_id').value = prod.product_id;
                            document.getElementById('product_name').value = prod.product_name;
                            document.getElementById('service_type').value = prod.service_type || '';
                            const priceEl = document.getElementById('price'); if (priceEl) priceEl.value = prod.price;
                            document.getElementById('product_details').value = prod.product_details || '';
                            // build variants
                            if(variantsRows) variantsRows.innerHTML = '';
                            try{
                                let pv = null;
                                if (typeof prod.variants === 'string'){
                                    const t = prod.variants.trim();
                                    if (t.startsWith('[') || t.startsWith('{')) pv = JSON.parse(t);
                                    else pv = t ? t.split(',').map(s=>s.trim()).filter(Boolean) : [];
                                } else {
                                    pv = prod.variants;
                                }
                                if(Array.isArray(pv)){
                                    pv.forEach(it=>{
                                        if(it && typeof it === 'object') variantsRows.appendChild(createVariantRow(it.name||it.variant||'', it.type||'', it.size||'', Array.isArray(it.colors)?it.colors:[], null));
                                        else variantsRows.appendChild(createVariantRow(String(it||''),'','',[], null));
                                    });
                                }
                            }catch(e){}
                            setVariantsVisible(true);
                            // scroll into view
                            setTimeout(()=> variantsSection?.scrollIntoView({behavior:'smooth'}), 50);
                        }
                    }
                } catch(e){ console.error('variants open parse error', e); }
            });
            return;
        }
        const delBtn = e.target.closest('[data-del]');
        if(delBtn){
            const id = delBtn.getAttribute('data-del');
            if(confirm('Delete this product?')){
                const fd = new FormData(); fd.append('action','delete'); fd.append('product_id', id);
                fetch('products-api.php', {method:'POST', body:fd})
                    .then(async r => {
                        const text = await r.text();
                        try {
                            const d = JSON.parse(text);
                            if (d.status === 'ok') { fetchProducts(); }
                            else alert(d.message || 'Delete failed');
                        } catch (e) {
                            console.error('Delete returned non-JSON', r.status, text);
                            alert('Delete failed; server returned unexpected response. See console.');
                        }
                    })
                    .catch(err=>alert('Delete error '+err));
            }
        }
    });

    // Handle attribute selection in list view to update price column
    tbody.addEventListener('change', e => {
        const tr = e.target.closest('tr');
        if(!tr) return;
        const priceTd = tr.querySelector('.td-price');
        if(!priceTd) return;
        const base = parseFloat(priceTd.getAttribute('data-base')||'0') || 0;
        let display = base;
        // If type-select changed and has a price, prefer it
        const typeSel = tr.querySelector('.type-select');
        const typeOpt = typeSel && typeSel.selectedOptions ? typeSel.selectedOptions[0] : null;
        if(typeOpt && typeOpt.dataset && typeOpt.dataset.price){
            const p = parseFloat(String(typeOpt.dataset.price).replace(/,/g,''));
            if(!isNaN(p)) display = p;
        }
        // Otherwise, if attribute selected with a price, use that
        const attrSel = tr.querySelector('.attr-select');
        const attrOpt = attrSel && attrSel.selectedOptions ? attrSel.selectedOptions[0] : null;
        if((!typeOpt || !typeOpt.dataset || !typeOpt.dataset.price) && attrOpt && attrOpt.dataset && attrOpt.dataset.price){
            const ap = parseFloat(String(attrOpt.dataset.price).replace(/,/g,''));
            if(!isNaN(ap)) display = ap;
        }
        const amt = priceTd.querySelector('.price-amount');
        if(amt) amt.textContent = display.toFixed(2);
    });

    // Custom dropdown interactions (open/close/select/delete)
    tbody.addEventListener('click', async e => {
        // Toggle menu
        const ddBtn = e.target.closest('.dd-btn');
        if (ddBtn) {
            const dd = ddBtn.closest('.dd');
            // close any other open dropdowns in the table
            tbody.querySelectorAll('.dd.open').forEach(x => { if (x !== dd) x.classList.remove('open'); });
            dd.classList.toggle('open');
            e.stopPropagation();
            return;
        }
        // Inline delete inside dropdown list
        const delBtn = e.target.closest('.dd-x');
        if (delBtn) {
            const pid = delBtn.getAttribute('data-pid');
            const kind = delBtn.getAttribute('data-kind');
            const val = delBtn.getAttribute('data-value');
            if (!pid || !kind || !val) return;
            if(!confirm(`Remove "${val}" ${kind} from this product?`)) return;
            const fd = new FormData();
            fd.append('action','delete_sub');
            fd.append('product_id', pid);
            fd.append('kind', kind);
            fd.append('value', val);
            try{
                const r = await fetch('products-api.php', { method:'POST', body: fd });
                const t = await r.text();
                const d = JSON.parse(t);
                if(d.status==='ok'){ fetchProducts(); }
                else alert(d.message||'Remove failed');
            }catch(err){ console.error('delete_sub error', err); alert('Remove failed: ' + err); }
            e.stopPropagation();
            return;
        }
        // Select an item from dropdown (ignore if clicking the delete button region)
        const item = e.target.closest('.dd-item');
        if (item) {
            // Don't treat clicks on dd-x as selection (handled above)
            if (e.target.closest('.dd-x')) return;
            const dd = item.closest('.dd');
            if (!dd) return;
            const kind = dd.getAttribute('data-kind');
            const pid = dd.getAttribute('data-pid');
            const value = item.getAttribute('data-value') || '';
            const price = item.getAttribute('data-price');
            // Update hidden select to keep existing logic working
            const wrap = dd.parentElement;
            const sel = wrap ? wrap.querySelector('select') : null;
            if (sel) {
                sel.value = value;
                // Ensure the option exists; if not, create it (defensive)
                let opt = Array.from(sel.options).find(o => o.value === value);
                if (!opt) {
                    opt = document.createElement('option');
                    opt.value = value;
                    opt.textContent = value;
                    if (price != null) opt.dataset.price = price;
                    sel.appendChild(opt);
                }
                if (price != null) opt.dataset.price = price;
                // Dispatch change so price column updates
                sel.dispatchEvent(new Event('change', { bubbles:true }));
            }
            // Update button label to reflect selection
            const btn = dd.querySelector('.dd-btn');
            if (btn) { btn.textContent = value || `Select ${kind}`; }
            // Close menu
            dd.classList.remove('open');
            e.stopPropagation();
            return;
        }
    });

    // Close any open dropdown when clicking outside
    document.addEventListener('click', (e)=>{
        const openDd = tbody.querySelector('.dd.open');
        if (!openDd) return;
        const inside = e.target.closest('.dd');
        if (!inside) openDd.classList.remove('open');
    });

    // Quick Variant form submit handler
    if(quickVariantForm){
        quickVariantForm.addEventListener('submit', async (e)=>{
            e.preventDefault();
            const pid = (qv_product_id?.value||'').trim();
            if(!pid){ alert('Missing product id'); return; }
            // Determine active tab
            const activeTab = document.querySelector('.qv-tab.active')?.getAttribute('data-qv-tab') || 'variants';
            let varEntries = [];
            let typeEntries = [];
            let attrEntries = [];
            let sizeEntries = [];
            if(activeTab === 'variants'){
                const items = Array.from(qv_var_list?.querySelectorAll('.qv-item')||[]);
                if(items.length===0){ alert('Add at least one variant'); return; }
                let invalid = false;
                items.forEach(row=>{
                    const vName = (row.querySelector('.qv_variant_name')?.value||'').trim();
                    if(!vName){ invalid = true; row.classList.add('invalid'); return; }
                    row.classList.remove('invalid');
                    varEntries.push({ vName });
                });
                if(invalid){ alert('Please fill Variant Name for all rows.'); return; }
            } else if(activeTab === 'types'){
                const items = Array.from(qv_type_list?.querySelectorAll('.qv-item')||[]);
                if(items.length===0){ alert('Add at least one type'); return; }
                let invalid = false;
                items.forEach(row=>{
                    const tName = (row.querySelector('.qv_type_name')?.value||'').trim();
                    if(!tName){ invalid = true; row.classList.add('invalid'); return; }
                    row.classList.remove('invalid');
                    typeEntries.push({ tName });
                });
                if(invalid){ alert('Please fill Type for all rows.'); return; }
            } else if(activeTab === 'sizes'){
                const items = Array.from(qv_size_list?.querySelectorAll('.qv-item')||[]);
                if(items.length===0){ alert('Add at least one size'); return; }
                let invalid = false;
                items.forEach(row=>{
                    const sName = (row.querySelector('.qv_size_name')?.value||'').trim();
                    if(!sName){ invalid = true; row.classList.add('invalid'); return; }
                    row.classList.remove('invalid');
                    sizeEntries.push({ sName });
                });
                if(invalid){ alert('Please fill Size for all rows.'); return; }
            } else {
                const items = Array.from(qv_attr_list?.querySelectorAll('.qv-item')||[]);
                if(items.length===0){ alert('Add at least one attribute'); return; }
                let invalid = false;
                items.forEach(row=>{
                    const aName = (row.querySelector('.qv_attr_name')?.value||'').trim();
                    const aPriceRaw = (row.querySelector('.qv_attr_price')?.value||'').trim();
                    const aPrice = aPriceRaw === '' ? 0 : (parseFloat(aPriceRaw.replace(/,/g,''))||0);
                    if(!aName){ invalid = true; row.classList.add('invalid'); return; }
                    row.classList.remove('invalid');
                    attrEntries.push({ aName, aPrice });
                });
                if(invalid){ alert('Please fill Attribute name for all rows.'); return; }
            }
            try{
                // find product in cache; fallback to fetch
                let prod = (productsCache||[]).find(p=>String(p.product_id)===String(pid));
                if(!prod){
                    const r = await fetch('products-api.php?action=list');
                    const t = await r.text();
                    const d = JSON.parse(t);
                    if(d.status==='ok'){
                        productsCache = d.products||[];
                        prod = productsCache.find(p=>String(p.product_id)===String(pid));
                    }
                }
                if(!prod){ alert('Product not found'); return; }
                // If user is only adding types or only sizes, hit quick_add_sub for a minimal insert
                if(activeTab==='types' && typeEntries.length>0 && varEntries.length===0 && attrEntries.length===0){
                    const fd2 = new FormData();
                    fd2.append('action','quick_add_sub');
                    fd2.append('product_id', pid);
                    fd2.append('sub_types', JSON.stringify(typeEntries.map(t=>t.tName)));
                    const resp2 = await fetch('products-api.php', { method:'POST', body: fd2 });
                    const text2 = await resp2.text();
                    let d2; try{ d2 = JSON.parse(text2); } catch(_){ alert('Save failed; server returned unexpected response.'); return; }
                    if(d2.status==='ok'){ if(quickVariantModal) closeModal(quickVariantModal); fetchProducts(); return; }
                    else { alert(d2.message || 'Save failed'); return; }
                }
                if(activeTab==='sizes' && sizeEntries.length>0 && varEntries.length===0 && attrEntries.length===0){
                    const fd2 = new FormData();
                    fd2.append('action','quick_add_sub');
                    fd2.append('product_id', pid);
                    fd2.append('sub_sizes', JSON.stringify(sizeEntries.map(s=>s.sName)));
                    const resp2 = await fetch('products-api.php', { method:'POST', body: fd2 });
                    const text2 = await resp2.text();
                    let d2; try{ d2 = JSON.parse(text2); } catch(_){ alert('Save failed; server returned unexpected response.'); return; }
                    if(d2.status==='ok'){ if(quickVariantModal) closeModal(quickVariantModal); fetchProducts(); return; }
                    else { alert(d2.message || 'Save failed'); return; }
                }
                // Build updated variants array
                const parsedVariants = (()=>{
                    try{
                        if(!prod.variants) return [];
                        if(typeof prod.variants === 'string'){
                            const t = prod.variants.trim();
                            if(!t) return [];
                            if(t.startsWith('[') || t.startsWith('{')){ const pv = JSON.parse(t); return Array.isArray(pv) ? pv.slice() : []; }
                            return t.split(',').map(s=>s.trim()).filter(Boolean);
                        } else if(Array.isArray(prod.variants)) return prod.variants.slice();
                        else return [];
                    }catch(_){ return []; }
                })();
                // Append new variants if in Variants tab
                if(varEntries.length){
                    varEntries.forEach(en => { parsedVariants.push({ name: en.vName }); });
                }
                if(typeEntries.length){
                    typeEntries.forEach(en => { parsedVariants.push({ type: en.tName }); });
                }
                if(sizeEntries.length){
                    sizeEntries.forEach(en => { parsedVariants.push({ size: en.sName }); });
                }

                // Build/merge wherepricedepends
                const parsedWP = (()=>{
                    try{
                        if(!prod.wherepricedepends) return [];
                        if(typeof prod.wherepricedepends === 'string'){
                            const t = prod.wherepricedepends.trim(); if(!t) return [];
                            const w = JSON.parse(t); return Array.isArray(w)? w.slice(): [];
                        } else if(Array.isArray(prod.wherepricedepends)) return prod.wherepricedepends.slice();
                        else return [];
                    }catch(_){ return []; }
                })();
                // If Attributes tab, merge globally (not tied to a specific variant)
                if(attrEntries.length){
                    // use a single global entry (no variant_name)
                    let globalEntry = parsedWP.find(x=> x && typeof x==='object' && !x.variant_name);
                    if(!globalEntry){ globalEntry = { others: [] }; parsedWP.push(globalEntry); }
                    if(!Array.isArray(globalEntry.others)) globalEntry.others = [];
                    attrEntries.forEach(en => {
                        const ex = globalEntry.others.find(o=> o && String(o.name||'').toLowerCase() === en.aName.toLowerCase());
                        if(ex){ ex.price = en.aPrice; }
                        else { globalEntry.others.push({ name: en.aName, price: en.aPrice }); }
                    });
                }

                // Preserve images
                const images_current = (()=>{
                    try{
                        if(!prod.images) return [];
                        const t = String(prod.images).trim();
                        if(!t) return [];
                        if(t.startsWith('[')){
                            const arr = JSON.parse(t); return Array.isArray(arr)? arr.filter(Boolean): [];
                        }
                        if(t.includes(',')) return t.split(',').map(s=>s.trim()).filter(Boolean);
                        return [t];
                    }catch(_){ return []; }
                })();

                // Build payload
                const fd = new FormData();
                fd.append('action','save');
                fd.append('product_id', pid);
                fd.append('product_name', prod.product_name || '');
                fd.append('service_type', prod.service_type || '');
                fd.append('price', (prod.price!==undefined && prod.price!==null) ? String(prod.price) : '0');
                fd.append('product_details', prod.product_details || '');
                fd.append('images_current', JSON.stringify(images_current));
                fd.append('variants', JSON.stringify(parsedVariants));
                fd.append('wherepricedepends', JSON.stringify(parsedWP));
                // Also persist to products_sub table (server handles if available)
                if(typeEntries.length) fd.append('sub_types', JSON.stringify(typeEntries.map(t=>t.tName)));
                if(sizeEntries.length) fd.append('sub_sizes', JSON.stringify(sizeEntries.map(s=>s.sName)));
                if(attrEntries.length) fd.append('sub_attrs', JSON.stringify(attrEntries.map(a=>({name:a.aName, price:a.aPrice}))));

                const resp = await fetch('products-api.php', { method:'POST', body: fd });
                const text = await resp.text();
                let d;
                try{ d = JSON.parse(text); }catch(_){ console.error('Quick add returned non-JSON', resp.status, text); alert('Save failed; server returned unexpected response.'); return; }
                if(d.status==='ok'){
                    if(quickVariantModal) closeModal(quickVariantModal);
                    fetchProducts();
                } else {
                    alert(d.message || 'Save failed');
                }
            }catch(err){ console.error('Quick add error', err); alert('Save error '+err); }
        });
    }

    // Helpers for Quick Variant modal rows
    function createQVVariantRow(){
        const wrap = document.createElement('div');
        wrap.className = 'qv-item';
        wrap.style.display = 'flex';
        wrap.style.gap = '8px';
        wrap.style.alignItems = 'flex-start';
        wrap.style.marginBottom = '8px';
        wrap.innerHTML = `
            <input type="text" class="qv_variant_name" placeholder="Variant name" style="flex:1;min-width:220px;" required />
            <button type="button" class="qv_remove inline-mini-btn" title="Remove">✕</button>`;
        return wrap;
    }
    function createQVTypeRow(){
        const wrap = document.createElement('div');
        wrap.className = 'qv-item';
        wrap.style.display = 'flex';
        wrap.style.gap = '8px';
        wrap.style.alignItems = 'flex-start';
        wrap.style.marginBottom = '8px';
        wrap.innerHTML = `
            <input type="text" class="qv_type_name" placeholder="Type" style="flex:1;min-width:220px;" required />
            <button type="button" class="qv_remove inline-mini-btn" title="Remove">✕</button>`;
        return wrap;
    }
    function createQVSizeRow(){
        const wrap = document.createElement('div');
        wrap.className = 'qv-item';
        wrap.style.display = 'flex';
        wrap.style.gap = '8px';
        wrap.style.alignItems = 'flex-start';
        wrap.style.marginBottom = '8px';
        wrap.innerHTML = `
            <input type="text" class="qv_size_name" placeholder="Size" style="flex:1;min-width:220px;" required />
            <button type="button" class="qv_remove inline-mini-btn" title="Remove">✕</button>`;
        return wrap;
    }
    function createQVAttrRow(){
        const wrap = document.createElement('div');
        wrap.className = 'qv-item';
        wrap.style.display = 'flex';
        wrap.style.gap = '8px';
        wrap.style.alignItems = 'flex-start';
        wrap.style.marginBottom = '8px';
        wrap.innerHTML = `
            <input type="text" class="qv_attr_name" placeholder="Attribute name" style="flex:1;min-width:220px;" required />
            <input type="number" class="qv_attr_price" placeholder="₱" min="0" step="0.01" style="width:160px;" required />
            <button type="button" class="qv_remove inline-mini-btn" title="Remove">✕</button>`;
        return wrap;
    }
    qv_add_var_row?.addEventListener('click', ()=>{ if(qv_var_list) qv_var_list.appendChild(createQVVariantRow()); });
    qv_add_attr_row?.addEventListener('click', ()=>{ if(qv_attr_list) qv_attr_list.appendChild(createQVAttrRow()); });
    qv_add_type_row?.addEventListener('click', ()=>{ if(qv_type_list) qv_type_list.appendChild(createQVTypeRow()); });
    qv_add_size_row?.addEventListener('click', ()=>{ if(qv_size_list) qv_size_list.appendChild(createQVSizeRow()); });
    quickVariantModal?.addEventListener('click', (e)=>{
        const btn = e.target.closest('.qv_remove');
        if(btn){ const row = btn.closest('.qv-item'); if(row) row.remove(); }
        const tabBtn = e.target.closest('.qv-tab');
        if(tabBtn){ const tab = tabBtn.getAttribute('data-qv-tab'); setQVTab(tab); }
    });

    function setQVTab(tab){
        document.querySelectorAll('.qv-tab').forEach(b=> b.classList.toggle('active', b.getAttribute('data-qv-tab')===tab));
        if(qv_variants_panel) qv_variants_panel.hidden = (tab!=='variants');
        if(qv_types_panel) qv_types_panel.hidden = (tab!=='types');
        if(qv_sizes_panel) qv_sizes_panel.hidden = (tab!=='sizes');
        if(qv_attrs_panel) qv_attrs_panel.hidden = (tab!=='attributes');
        // Seed a first row if empty
        if(tab==='variants' && qv_var_list && qv_var_list.childElementCount===0) qv_var_list.appendChild(createQVVariantRow());
        if(tab==='types' && qv_type_list && qv_type_list.childElementCount===0) qv_type_list.appendChild(createQVTypeRow());
        if(tab==='sizes' && qv_size_list && qv_size_list.childElementCount===0) qv_size_list.appendChild(createQVSizeRow());
        if(tab==='attributes' && qv_attr_list && qv_attr_list.childElementCount===0) qv_attr_list.appendChild(createQVAttrRow());
    }

    productForm.addEventListener('submit', e => {
        e.preventDefault();
        const fd = new FormData(productForm);
        fd.append('action','save');
        // collect variants table rows into an array of objects and append as JSON under 'variants'
        try{
            const rows = [];
            // only collect when visible (user opted in)
            if(variantsRows && !variantsSection.classList.contains('hidden')){
                const trNodes = Array.from(variantsRows.querySelectorAll('.variant-card'));
                let filledRowCount = 0;
                // Count filled top-level variant rows (any field or any nested color filled)
                trNodes.forEach(tr => {
                    const names = Array.from(tr.querySelectorAll('.variant-name')).map(i=>i.value.trim()).filter(Boolean);
                    const types = Array.from(tr.querySelectorAll('.variant-type')).map(i=>i.value.trim()).filter(Boolean);
                    const sizes = Array.from(tr.querySelectorAll('.variant-size')).map(i=>i.value.trim()).filter(Boolean);
                    const anyFilled = names.length>0 || types.length>0 || sizes.length>0;
                    if(anyFilled) filledRowCount++;
                });

                // collect rows now and mark invalid inputs if validation fails
                let invalid = false;
                const wherePriceArr = [];
                trNodes.forEach(tr => {
                    const nameInputs = Array.from(tr.querySelectorAll('.variant-name'));
                    const typeInputs = Array.from(tr.querySelectorAll('.variant-type'));
                    const sizeInputs = Array.from(tr.querySelectorAll('.variant-size'));
                    const names = nameInputs.map(i=>i.value.trim()).filter(v=>v!== '');
                    const types = typeInputs.map(i=>i.value.trim()).filter(v=>v!== '');
                    const sizes = sizeInputs.map(i=>i.value.trim()).filter(v=>v!== '');

                    // colors removed; keep empty array
                    const colorsArr = [];

                    const anyFilled = names.length>0 || types.length>0 || sizes.length>0 || colorsArr.length>0;
                    // require at least one name when any field filled
                    if(anyFilled && names.length===0){ invalid = true; if(nameInputs[0]) nameInputs[0].classList.add('invalid'); }
                    else if(nameInputs[0]) nameInputs[0].classList.remove('invalid');
                    // removed color-based validation

                    if(anyFilled) {
                        const out = { colors: colorsArr };
                        if (names.length>1) out.name = names; else out.name = (names[0]||'');
                        if (types.length>1) out.type = types; else out.type = (types[0]||'');
                        if (sizes.length>1) out.size = sizes; else out.size = (sizes[0]||'');
                        rows.push(out);
                    }

                    // collect simple attribute-based price dependencies (only when at least something provided)
                    try{
                        const otherRows = Array.from(tr.querySelectorAll('.other-row'));
                        const others = [];
                        otherRows.forEach(or => {
                            const k = (or.querySelector('.other-attr-name')?.value||'').trim();
                            const pv = (or.querySelector('.other-attr-price')?.value||'').trim();
                            if(k || pv !== ''){
                                let num = '';
                                if(pv !== ''){
                                    const parsed = parseFloat(pv.replace(/,/g,''));
                                    num = isNaN(parsed) ? 0 : parsed;
                                }
                                others.push({ name: k, price: num });
                            }
                        });
                        const priceDep = {};
                        if(others.length) priceDep.others = others;
                        if(Object.keys(priceDep).length){
                            const vName = names[0] || '';
                            priceDep.variant_name = vName;
                            wherePriceArr.push(priceDep);
                        }
                    }catch(e){ /* ignore */ }
                });
                if(invalid){ alert('Please fill a name for each variant row.'); return; }
            }
            if(rows.length) fd.append('variants', JSON.stringify(rows));
            if(wherePriceArr.length) fd.append('wherepricedepends', JSON.stringify(wherePriceArr));
        } catch(e){ console.warn('Failed to collect variants table rows', e); }
        // Collect existing images still present in previews.
        // Important: only include server-side image paths (data-src) here.
        // Do NOT include data: URIs from newly selected files (they would bloat the payload).
        const existingImgs = [];
        productForm.querySelectorAll('.preview-wrap').forEach(w => {
            // existing (saved) preview wraps include a data-src attribute set when populated from the product
            const existingPath = w.getAttribute('data-src');
            if (existingPath && !removedImages.includes(existingPath)) existingImgs.push(existingPath);
        });
        if (existingImgs.length) fd.append('images_current', JSON.stringify(existingImgs));
        removedImages.forEach(r=> fd.append('images_removed[]', r));
        // append only the remaining new files from newFilesMap
        Array.from(newFilesMap.values()).forEach(f=> fd.append('images_files[]', f));
        fetch('products-api.php', {method:'POST', body:fd})
            .then(async r => {
                const text = await r.text();
                try {
                    const d = JSON.parse(text);
                            if (d.status === 'ok') { closeModal(productModal); fetchProducts(); }
                    else alert(d.message || 'Save failed');
                } catch (e) {
                    console.error('Save returned non-JSON', r.status, text);
                    alert('Save failed; server returned unexpected response. See console.');
                }
            })
            .catch(err=>alert('Save error '+err));
    });

    // Preview uploaded image
    const imagesFilesInput = document.getElementById('images_files');
    const imagePreview = document.getElementById('imagePreview');
    if(imagesFilesInput){
        imagesFilesInput.addEventListener('change', ()=>{
            const fileInfo = document.getElementById('fileInfo');
            const files = Array.from(imagesFilesInput.files || []);
            // Compute existing image basenames to avoid adding duplicates
            const existingPreviewNodesForBasenames = productForm.querySelectorAll('.preview-wrap');
            const existingBasenames = new Set();
            existingPreviewNodesForBasenames.forEach(node => {
                const imgEl = node.querySelector('img');
                const src = imgEl ? (imgEl.getAttribute('src') || node.getAttribute('data-src')) : node.getAttribute('data-src');
                if (!src) return;
                try { existingBasenames.add((src.split('/').pop() || '').toLowerCase()); } catch(e){}
            });
            // Add each new file into newFilesMap using fingerprint to avoid duplicates
            files.forEach(f=>{
                const nameBasename = (f.name || '').split('/').pop().toLowerCase();
                if (existingBasenames.has(nameBasename)) {
                    // skip adding since the same filename already exists for this product
                    return;
                }
                const key = `${f.name}:${f.size}:${f.lastModified}`;
                if (!newFilesMap.has(key)) newFilesMap.set(key, f);
            });
            if(fileInfo) fileInfo.textContent = newFilesMap.size ? Array.from(newFilesMap.values()).map(f=>f.name).join(', ') : '';
            // Re-render preview area: existing preview-wraps (kept) + new files
            imagePreview.innerHTML = '';
            // Show any existing preview-wrap elements that weren't removed
            const existingPreviewNodes = productForm.querySelectorAll('.preview-wrap');
            existingPreviewNodes.forEach(node => {
                const imgEl = node.querySelector('img');
                const src = imgEl ? (imgEl.getAttribute('src') || node.getAttribute('data-src')) : node.getAttribute('data-src');
                if (!src) return;
                if (removedImages.includes(src)) return; // skip removed
                const clone = node.cloneNode(true);
                clone.style.display = 'inline-block'; clone.style.position = 'relative'; clone.style.marginRight = '8px';
                imagePreview.appendChild(clone);
            });
            // Add previews for new files
            Array.from(newFilesMap.values()).forEach(f=>{
                const wrap = document.createElement('div'); wrap.className='preview-wrap'; wrap.style.display='inline-block'; wrap.style.position='relative'; wrap.style.marginRight='8px';
                const img = document.createElement('img'); img.style.maxWidth='120px'; img.style.maxHeight='120px'; img.style.objectFit='cover';
                const reader = new FileReader(); reader.onload = ev => img.src = ev.target.result; reader.readAsDataURL(f);
                const removeBtn = document.createElement('button'); removeBtn.className='img-remove new-file-remove'; removeBtn.type='button'; removeBtn.title='Remove image'; removeBtn.setAttribute('data-fp', `${f.name}:${f.size}:${f.lastModified}`);
                removeBtn.textContent='✕';
                wrap.appendChild(img); wrap.appendChild(removeBtn); imagePreview.appendChild(wrap);
            });
        });
    }

    // Delegate click on remove buttons inside preview area for both existing and new files
    imagePreview?.addEventListener('click', e=>{
        const btn = e.target.closest('.img-remove');
        if(!btn) return;
        const src = btn.getAttribute('data-src');
        const fp = btn.getAttribute('data-fp');
        const wrap = btn.closest('.preview-wrap');
        if(wrap) wrap.remove();
        if(src) {
            removedImages.push(src);
        }
        if(fp) {
            newFilesMap.delete(fp);
        }
        const fileInfo = document.getElementById('fileInfo');
        if(fileInfo) fileInfo.textContent = newFilesMap.size ? Array.from(newFilesMap.values()).map(f=>f.name).join(', ') : '';
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
        if(svcFile && svcFile.files && svcFile.files.length>0){ 
            fd.append('service_image', svcFile.files[0]); 
        }
    fetch('services-api.php', {method:'POST', body:fd})
        .then(r => r.text().then(text => ({ status: r.status, ok: r.ok, text })))
        .then(resp => {
            let d;
            try {
                d = JSON.parse(resp.text);
            } 
            catch (err) {
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
    const servicesListEl = document.getElementById('servicesList');
    if (servicesListEl) servicesListEl.innerHTML = '<div class="msg msg-loading">Loading services…</div>';
    fetch('services-api.php?action=list')
            .then(r => r.json())
            .then(d => {
                console.log('services_api list response:', d);
                if (d.status === 'ok') {
                    renderServices(d.services);
                    if (refreshDatalist) syncServiceDatalist(d.services);
                    else if (serviceTypeList.children.length === 0) syncServiceDatalist(d.services);
                    // also populate the header filter select
                    if (typeof populateServiceFilter === 'function') populateServiceFilter((d.services||[]).map(s=>s.name));
                } else {
                    if (servicesListEl) servicesListEl.innerHTML = '<div class="msg msg-error">Failed to load services</div>';
                    console.error('services list failed', d);
                }
            })
            .catch(e => {
                console.error('services fetch error', e);
                if (servicesListEl) servicesListEl.innerHTML = '<div class="msg msg-error">Error fetching services; see console.</div>';
            });
    }
    function renderServices(list){
    if (servicesTbody) servicesTbody.innerHTML='';
        const servicesList = document.getElementById('servicesList');
        if (servicesList) servicesList.innerHTML = '';
        if(!list || list.length===0){
            if (servicesList) servicesList.innerHTML = '<div class="msg msg-empty">No services</div>';
            return;
        }
        list.forEach(s=>{
            // Render table row for backwards compatibility (hidden) and render card in modal
            const tr=document.createElement('tr');
            const imgSrc = s.image ? (s.image.startsWith('http') ? s.image : ('../'+s.image.replace(/\\/g,'/'))) : '';
            const imgCell = `<td class="svc-img-cell">${imgSrc?`<img src="${escapeAttr(imgSrc)}" class="svc-img-thumb"/>`:''}</td>`;
            const delBtnHtml = `<button class="svc-del" data-del-svc="${s.service_id}" title="Delete">🗑️</button>`;
            const editBtnHtml = `<button class="svc-edit" data-edit-svc="${s.service_id}" title="Edit">✎</button>`;
            tr.innerHTML = `${imgCell}<td>${escapeHtml(s.name)}</td><td class="text-center">${editBtnHtml} ${delBtnHtml}</td>`;
            if (servicesTbody) servicesTbody.appendChild(tr);

            if (servicesList) {
                const card = document.createElement('div');
                card.className = 'service-card';

                const imgWrap = document.createElement('div');
                imgWrap.className = 'svc-thumb';
                if (imgSrc) {
                    const img = document.createElement('img');
                    img.src = imgSrc;
                    img.className = 'svc-img-thumb';
                    imgWrap.appendChild(img);
                }
                card.appendChild(imgWrap);

                const content = document.createElement('div');
                content.className = 'svc-content';

                const nameEl = document.createElement('div');
                nameEl.className = 'svc-name';
                nameEl.textContent = s.name;
                content.appendChild(nameEl);

                const dep = document.createElement('div');
                dep.className = 'svc-deps';
                dep.textContent = (s.product_count && s.product_count>0) ? `${s.product_count} product(s) use this service` : 'No product dependencies';
                content.appendChild(dep);

                card.appendChild(content);

                const btns = document.createElement('div');
                btns.className = 'svc-actions';

                const editBtn = document.createElement('button');
                editBtn.className = 'svc-edit';
                editBtn.setAttribute('data-edit-svc', s.service_id);
                editBtn.textContent = 'Edit';
                // visual styling handled by CSS
                btns.appendChild(editBtn);

                const delBtn = document.createElement('button');
                delBtn.className = 'svc-del';
                delBtn.setAttribute('data-del-svc', s.service_id);
                delBtn.textContent = 'Delete';
                if (s.product_count && s.product_count>0) { delBtn.disabled = true; delBtn.title = 'Cannot delete — has dependent products'; }
                btns.appendChild(delBtn);

                card.appendChild(btns);

                servicesList.appendChild(card);
            }
        });
    }
    // Unified edit / delete handler for service cards and rows
    document.addEventListener('click', function(e) {
        const editBtn = e.target.closest('[data-edit-svc]');
        if (editBtn) {
            const id = editBtn.getAttribute('data-edit-svc');
            // fetch single list and open modal with data
            fetch('services-api.php?action=list').then(r=>r.json()).then(d=>{
                if(d.status==='ok'){
                    const svc = d.services.find(s=>String(s.service_id)===String(id));
                    if(svc){
                        document.getElementById('service_id').value = svc.service_id;
                        document.getElementById('service_name').value = svc.name;
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
                fetch('services-api.php', {method:'POST', body:fd}).then(r=>r.text().then(t=>({status:r.status,text:t}))).then(resp=>{
                    try{ const d = JSON.parse(resp.text); if(d.status==='ok'){ loadServices(true); } else alert(d.message||'Delete failed'); }
                    catch(e){ console.error('Delete raw', resp); alert('Delete failed; see console'); }
                })
                .catch(err=>alert('Delete error '+err));
            }
            return;
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
                fetch('services-api.php', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{
                    if(d.status==='ok'){ loadServices(true); }
                    else alert(d.message||'Delete failed');
                })
                .catch(err=>alert('Delete error '+err));
            }
        }
    });

    // initial services load for datalist
    loadServices();

    // Service filter UI
    const toggleFilterBtn = document.getElementById('toggleFilterBtn');
    const serviceFilterDropdown = document.getElementById('serviceFilterDropdown');
    const serviceFilterList = document.getElementById('serviceFilterList');
    let currentServiceFilter = '';

    function populateServiceFilter(options){
        if(!serviceFilterList) return;
        // Do not remove existing items; merge the provided options into the list.
        const existing = new Set();
        Array.from(serviceFilterList.children).forEach(li=> existing.add(li.getAttribute('data-value')||''));
        options.forEach(opt => {
            const v = String(opt || '').trim();
            if(!v) return;
            if(!existing.has(v)){
                const li = document.createElement('li'); li.setAttribute('data-value', v); li.textContent = v; serviceFilterList.appendChild(li); existing.add(v);
            }
        });
    }

    function applyServiceFilter(){
        const val = (currentServiceFilter||'').toLowerCase();
        Array.from(tbody.querySelectorAll('tr')).forEach(tr => {
            if(!val) { tr.style.display = ''; return; }
            // service cell is the first td
            const svc = (tr.querySelector('td')?.textContent || '').toLowerCase();
            tr.style.display = svc === val ? '' : 'none';
        });
    }

    // Toggle dropdown visibility
    toggleFilterBtn?.addEventListener('click', (ev)=>{
        if(!serviceFilterDropdown) return;
        const isHidden = serviceFilterDropdown.hasAttribute('hidden');
        if(isHidden){
            serviceFilterDropdown.removeAttribute('hidden');
            serviceFilterDropdown.setAttribute('aria-hidden','false');
            toggleFilterBtn.setAttribute('aria-expanded','true');
        } else {
            serviceFilterDropdown.setAttribute('hidden','');
            serviceFilterDropdown.setAttribute('aria-hidden','true');
            toggleFilterBtn.setAttribute('aria-expanded','false');
        }
    });

    // Handle option clicks in the dropdown (event delegation)
    serviceFilterList?.addEventListener('click', (e)=>{
        const li = e.target.closest('li');
        if(!li) return;
        const val = li.getAttribute('data-value') || '';
        currentServiceFilter = val;
        applyServiceFilter();
        // close dropdown
        if(serviceFilterDropdown){ serviceFilterDropdown.setAttribute('hidden',''); serviceFilterDropdown.setAttribute('aria-hidden','true'); }
        if(toggleFilterBtn) toggleFilterBtn.setAttribute('aria-expanded','false');
    });

    // Close dropdown on outside click
    document.addEventListener('click', (e)=>{
        if(!serviceFilterDropdown) return;
        const isOpen = !serviceFilterDropdown.hasAttribute('hidden');
        if(!isOpen) return;
        const inside = e.target.closest('#serviceFilterDropdown') || e.target.closest('#toggleFilterBtn');
        if(!inside){
            serviceFilterDropdown.setAttribute('hidden','');
            serviceFilterDropdown.setAttribute('aria-hidden','true');
            if(toggleFilterBtn) toggleFilterBtn.setAttribute('aria-expanded','false');
        }
    });

    // Initial load
    fetchProducts();
});