
document.addEventListener('DOMContentLoaded', function() {
    // If there is no #viewerCanvas on this page, quietly skip initialization.
    const viewerCanvas = document.getElementById('viewerCanvas');
    if (!viewerCanvas) {
        // Many pages include sim.js but don't render a 3D viewer; do nothing here.
        console.debug('sim.js: no #viewerCanvas found on this page — skipping 3D viewer init.');
        return;
    }

    if (typeof THREE === 'undefined') {
        // Only show the error inside the viewer area when the viewer is expected.
        try { viewerCanvas.innerHTML = '<div class="sim-error">Three.js is not loaded!<br>Check your script order and CDN loading.</div>'; } catch(e){}
        console.error('Three.js is not loaded! Please check your script order.');
        return;
    }
    console.log("sim.js is running");

    let shirtMeshList = [];
    // keep a reference to the currently-applied design texture so user can flip/adjust it at runtime
    window.currentDesignTexture = null;
    let viewerInitialized = false;
    let pickrInstance = null;
    let lastScrollY = 0;

    // Scene setup
    const scene = new THREE.Scene();
    scene.background = new THREE.Color(0xf4f4f4);

    // Camera setup
    const camera = new THREE.PerspectiveCamera(45, window.innerWidth / window.innerHeight, 0.1, 1000);
    camera.position.set(0, 0, 3.5); // initial camera position; will be adjusted after model loads

    // Renderer (enable preserveDrawingBuffer so snapshots capture the current frame)
    const renderer = new THREE.WebGLRenderer({ antialias: true, preserveDrawingBuffer: true });
    // set device pixel ratio for crisper snapshots (cap to 2)
    try { renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2)); } catch(e) {}
    viewerCanvas.appendChild(renderer.domElement);

    // simple loading overlay for the 3D viewer
    function ensureViewerSpinner() {
        try {
            const container = viewerCanvas.parentElement || document.body;
            if (container.querySelector('#sim-loading-overlay')) return;
            const overlay = document.createElement('div');
            overlay.id = 'sim-loading-overlay';
            overlay.style.position = 'absolute';
            overlay.style.left = '0';
            overlay.style.top = '0';
            overlay.style.right = '0';
            overlay.style.bottom = '0';
            overlay.style.display = 'none';
            overlay.style.alignItems = 'center';
            overlay.style.justifyContent = 'center';
            overlay.style.background = 'rgba(255,255,255,0.8)';
            overlay.style.zIndex = 1200;
            overlay.innerHTML = '<div style="padding:12px 18px;border-radius:8px;background:#fff;border:1px solid #eee;font-weight:600;color:#333;">Loading 3D preview…</div>';
            container.style.position = container.style.position || 'relative';
            container.appendChild(overlay);
        } catch(e) { console.warn('ensureViewerSpinner failed', e); }
    }

    function showViewerSpinner(){ try { ensureViewerSpinner(); const o = (viewerCanvas.parentElement||document.body).querySelector('#sim-loading-overlay'); if(o) o.style.display='flex'; } catch(e){} }
    function hideViewerSpinner(){ try { const o = (viewerCanvas.parentElement||document.body).querySelector('#sim-loading-overlay'); if(o) o.style.display='none'; } catch(e){} }

    // Responsive renderer sizing
    function updateRendererSize() {
        const w = Math.max(320, viewerCanvas.clientWidth || 800);
        const h = Math.max(240, viewerCanvas.clientHeight || 600);
        try { renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2)); } catch(e) {}
        renderer.setSize(w, h, true);
        camera.aspect = w / h;
        camera.updateProjectionMatrix();
    }
    updateRendererSize();
    window.addEventListener('resize', updateRendererSize);

    // Lighting
    const light = new THREE.HemisphereLight(0xffffff, 0x444444, 1);
    scene.add(light);

    // Controls (safe fallback for OrbitControls)
    let ControlsCtor = null;
    console.log('All script tags:');
    document.querySelectorAll('script').forEach(s => console.log(s.src));
    console.log('window.OrbitControls:', window.OrbitControls);
    console.log('THREE.OrbitControls:', THREE.OrbitControls);
    if (typeof window.OrbitControls === 'function') {
        ControlsCtor = window.OrbitControls;
        console.log('Using window.OrbitControls');
    } else if (typeof THREE.OrbitControls === 'function') {
        ControlsCtor = THREE.OrbitControls;
        console.log('Using THREE.OrbitControls');
    } else {
        // Show a visible error in the viewer
        if (viewerCanvas) {
            viewerCanvas.innerHTML = '<div class="sim-error">OrbitControls is not available!<br>Check your script order and CDN loading.</div>';
        }
        alert('OrbitControls is not available! Check your script order and CDN loading.');
        throw new Error('OrbitControls is not available!');
    }
    const controls = new ControlsCtor(camera, renderer.domElement);
    controls.enableDamping = true;
    controls.dampingFactor = 0.05;
    controls.enableZoom = true; // Enable zoom
    controls.enablePan = true; // Enable panning
    controls.screenSpacePanning = true; // Allow both directions
    // Lock vertical rotation so user can only rotate left-right (azimuth)
    controls.minPolarAngle = Math.PI / 2;
    controls.maxPolarAngle = Math.PI / 2;
    // Disable panning to keep view stable (optional)
    controls.enablePan = false;
    // Set left mouse to rotate, Shift+left mouse to pan (default behavior)
    if (controls.mouseButtons) {
        controls.mouseButtons.LEFT = THREE.MOUSE.ROTATE;
        controls.mouseButtons.MIDDLE = THREE.MOUSE.DOLLY;
        controls.mouseButtons.RIGHT = THREE.MOUSE.PAN;
    }

    // Animate loop
    function animate() {
        requestAnimationFrame(animate);
        controls.update();
        renderer.render(scene, camera);
    }
    animate();

    // Initialize Pickr early so the color picker is usable in 2D mode
    function initPickrIfNeeded(){
        if (pickrInstance) return;
        const container = document.getElementById('colorPickerContainer');
        if (!container) return;
        try {
            pickrInstance = Pickr.create({
                el: container,
                // ensure Pickr's popup / root is appended inside the left container
                // so it cannot escape into the right-side viewer area
                appendTo: container,
                theme: 'classic',
                inline: true,
                showAlways: true,
                // default to last editor color if present, otherwise site primary
                default: (function(){ try { return localStorage.getItem('editor2d_last_color') || '#a02b2b'; } catch(e){ return '#a02b2b'; } })(),
                components: {
                    preview: true,
                    opacity: false,
                    hue: true,
                    interaction: {
                        hex: true,
                        input: true,
                        save: false
                    }
                }
            });
            pickrInstance.on('change', (color) => {
                const hex = color.toHEXA().toString();
                shirtMeshList.forEach(mesh => { try { mesh.material.color.set(hex); } catch(e){} });
                try { if (typeof window.editorSetTint === 'function') window.editorSetTint(hex); } catch(e){}
                try { localStorage.setItem('editor2d_last_color', hex); } catch(e){}
            });

            pickrInstance.on('swatchselect', (color) => {
                const hex = color.toHEXA().toString();
                shirtMeshList.forEach(mesh => { try { mesh.material.color.set(hex); } catch(e){} });
                try { if (typeof window.editorSetTint === 'function') window.editorSetTint(hex); } catch(e){}
                try { localStorage.setItem('editor2d_last_color', hex); } catch(e){}
            });
        } catch (e) {
            console.error('Pickr init failed:', e);
            if (container) container.innerHTML = '<div class="sim-pickr-error">Color picker failed to initialize.</div>';
        }
    }
    // call early for 2D default
    setTimeout(initPickrIfNeeded, 50);

    // Wire the upload drop area to the 2D editor: click to open file picker, drop to add image
    try {
        const drop = document.getElementById('uploadDrop');
        if (drop) {
            let fileInput = document.getElementById('templateFileInput');
            if (!fileInput) {
                fileInput = document.createElement('input');
                fileInput.type = 'file';
                fileInput.accept = 'image/*,image/svg+xml';
                fileInput.id = 'templateFileInput';
                fileInput.style.display = 'none';
                document.body.appendChild(fileInput);
            }
            drop.addEventListener('click', () => fileInput.click());
            drop.addEventListener('dragover', (e) => { e.preventDefault(); drop.style.borderColor = '#c9baba'; });
            drop.addEventListener('dragleave', (e) => { e.preventDefault(); drop.style.borderColor = '#e6e6e6'; });
            drop.addEventListener('drop', (e) => {
                e.preventDefault(); drop.style.borderColor = '#e6e6e6';
                const f = (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0]) ? e.dataTransfer.files[0] : null;
                if (f) {
                    try { if (typeof window.editorAddImageFromFile === 'function') window.editorAddImageFromFile(f); } catch(err){ console.warn(err); }
                }
            });
            fileInput.addEventListener('change', (e) => {
                const f = (e.target && e.target.files && e.target.files[0]) ? e.target.files[0] : null;
                if (f) {
                    try { if (typeof window.editorAddImageFromFile === 'function') window.editorAddImageFromFile(f); } catch(err){ console.warn(err); }
                }
            });
        }
    } catch(e) { console.warn('template upload wiring failed', e); }

    // Left swatches removed — color selection is handled by the full color picker in the editor.

    // Dragging setup
    const raycaster = new THREE.Raycaster();
    const pointer = new THREE.Vector2();
    let dragging = false;
    let dragObject = null;
    let dragPlane = new THREE.Plane();
    let dragOffset = new THREE.Vector3();
    let intersection = new THREE.Vector3();

    // Viewer initialization
    function initViewer() {
        if (viewerInitialized) return;
        viewerInitialized = true;

        // Safe fallback for GLTFLoader
        let GLTFLoaderCtor = null;
        if (typeof window.GLTFLoader === 'function') {
            GLTFLoaderCtor = window.GLTFLoader;
            console.log('Using window.GLTFLoader');
        } else if (typeof THREE.GLTFLoader === 'function') {
            GLTFLoaderCtor = THREE.GLTFLoader;
            console.log('Using THREE.GLTFLoader');
        } else {
            if (viewerCanvas) {
                viewerCanvas.innerHTML = '<div class="sim-error">GLTFLoader is not available!<br>Check your script order and CDN loading.</div>';
            }
            alert('GLTFLoader is not available! Check your script order and CDN loading.');
            throw new Error('GLTFLoader is not available!');
        }
    const loader = new GLTFLoaderCtor();
    // show loading overlay while the model downloads and parses
    try { showViewerSpinner(); } catch(e){}
    // Try a list of plausible GLB paths to match where the React app and repo place the file.
    // The React client uses '/shirt_baked.glb' (served from its public/), so try that first,
    // then local repository paths, then fall back to the older t-shirt/scene.gltf.
    // Prefer the threejs-react-TDesigner model used by the React demo in the repo.
    // Try multiple likely served locations so the loader works whether assets are served
    // from the project root, a public folder, or kept inside the React src tree.
    // Prefer the t-shirt folder assets as requested by the user
    const modelCandidates = [
        't-shirt/scene.gltf',
        '/t-shirt/scene.gltf',
        't-shirt/scene.glb',
        '/t-shirt/scene.glb',
        // fallback to other known candidates
        'threejs-react-TDesigner-main/src/assets/3d/tshirt.glb',
        '/threejs-react-TDesigner-main/src/assets/3d/tshirt.glb',
        'threejs-react-TDesigner-main/public/tshirt.glb',
        '/threejs-react-TDesigner-main/public/tshirt.glb',
        '/shirt_baked.glb',
        'tshirt3d-master/client/public/shirt_baked.glb',
        'tshirt3d-master/public/shirt_baked.glb',
        'Shirt/shirt_baked.glb'
    ];

    function tryNextModel(i) {
        if (i >= modelCandidates.length) {
            console.error('All model candidates failed to load:', modelCandidates);
            try { hideViewerSpinner(); } catch(e){}
            return;
        }
        const src = modelCandidates[i];
        console.log('Attempting to load model candidate:', src);
        loader.load(src, function (gltf) {
            console.log('Model loaded from', src, gltf.scene);
            onModelLoaded(gltf, src);
        }, function (xhr) {
            if (xhr && xhr.lengthComputable) {
                const percent = (xhr.loaded / xhr.total) * 100;
                console.log('Model loading ('+src+'): ' + Math.round(percent) + '%');
            }
        }, function (error) {
            console.error('Error loading model', src, error && error.message ? error.message : error);
            // try next candidate
            tryNextModel(i+1);
        });
    }

    // helper extracted to keep the original success handler readable
    function onModelLoaded(gltf, src) {
        console.log("Model loaded:", gltf.scene);

        const shirt = gltf.scene;
        // Compute bounding box and sphere for framing
        const box = new THREE.Box3().setFromObject(shirt);
        const sphere = new THREE.Sphere();
        box.getBoundingSphere(sphere);
        const size = new THREE.Vector3();
        box.getSize(size);

        // Uniform scale to fit within a target size
        const targetSize = 2.6; // scene units
        const maxDim = Math.max(size.x, size.y, size.z);
        const scale = maxDim > 0 ? (targetSize / maxDim) : 1;
        shirt.scale.setScalar(scale);

        // Recompute bounding box and sphere after scaling
        const scaledBox = new THREE.Box3().setFromObject(shirt);
        const scaledSphere = new THREE.Sphere();
        scaledBox.getBoundingSphere(scaledSphere);

        // Reset shirt rotation so we start with front-facing orientation
        // Some exports need a 180° Y rotation; default to Math.PI so the front faces camera
        shirt.rotation.set(0, Math.PI, 0);

        // expose a small debug helper so you can flip orientation from console if needed
        // Usage in console: simFlip();
        window.simShirt = shirt;
        window.simFlip = function() {
            if (window.simShirt) {
                window.simShirt.rotation.y += Math.PI;
                controls.update();
                console.log('sim: flipped shirt orientation');
            }
        };

        // hide rotate hint once user interacts with the viewer
        const hint = document.querySelector('.rotate-hint');
        function hideHint() { if (hint) hint.classList.add('hidden'); }
        ['pointerdown','touchstart','mousedown'].forEach(e => {
            viewerCanvas.addEventListener(e, hideHint, { once: true });
        });

        // Position shirt so its center is at the scene origin (0,0,0) with a slight Y offset
        const center = scaledBox.getCenter(new THREE.Vector3());
        // Move the model so its center is at the origin
        shirt.position.set(-center.x, -center.y, -center.z);
        // apply a small downward shift so the shirt sits visually centered
        shirt.position.y -= 0.15;

        // Avoid adding model twice
        if (!scene.getObjectByName('loadedShirt')) {
            shirt.name = 'loadedShirt';
            scene.add(shirt);
        }

        // enable pointer cursor
        viewerCanvas.style.cursor = 'grab';

        // Frame camera: place camera directly in front of the shirt so initial view is frontal
        const fitOffset = 1.8;
        const dist = scaledSphere.radius * fitOffset;
        // Place camera along negative Z looking at origin where shirt is centered
        camera.position.set(0, scaledSphere.radius * 0.25, - (dist + 0.5));
        camera.lookAt(0, 0, 0);
        controls.target.set(0, 0, 0);
        controls.update();
        updateRendererSize();

        shirt.traverse((child) => {
            if (child.isMesh) {
                try { child.material.color.set('#ffffff'); } catch(e){}
                shirtMeshList.push(child);
            }
        });

        // Ensure any textures on the loaded model use sRGB encoding for correct colors
        try {
            shirt.traverse((child) => {
                if (child.isMesh && child.material) {
                    const mats = Array.isArray(child.material) ? child.material : [child.material];
                    mats.forEach(m => {
                        try {
                            if (m.map) {
                                if (typeof m.map.colorSpace !== 'undefined' && typeof THREE.SRGBColorSpace !== 'undefined') {
                                    m.map.colorSpace = THREE.SRGBColorSpace;
                                } else if (typeof m.map.encoding !== 'undefined' && typeof THREE.sRGBEncoding !== 'undefined') {
                                    m.map.encoding = THREE.sRGBEncoding;
                                }
                                m.map.needsUpdate = true;
                            }
                        } catch(e) { /* ignore */ }
                    });
                }
            });
        } catch(e) { console.warn('Failed to set sRGB on model textures', e); }

        // If there's a pending design dataURL saved in a hidden input, apply it as a texture
        try {
            const pending = document.getElementById('designDataURL')?.value;
            if (pending) {
                applyDesignTextureFromDataURL(pending);
            }
        } catch (e) { console.warn('No designDataURL to apply', e); }

        // Set up pointer handlers for dragging
        function getPointerClient(e) {
            if (e.touches && e.touches.length) return { x: e.touches[0].clientX, y: e.touches[0].clientY };
            return { x: e.clientX, y: e.clientY };
        }

        function onPointerDown(e) {
            // Start dragging when left mouse button is pressed over the shirt, or on touch
            const isTouch = (e.pointerType === 'touch') || (e.type === 'touchstart') || (e.touches && e.touches.length);
            // If this is a mouse event and not the left button, ignore
            if (!isTouch && typeof e.button === 'number' && e.button !== 0) return;

            const p = getPointerClient(e);
            const rect = renderer.domElement.getBoundingClientRect();
            pointer.x = ((p.x - rect.left) / rect.width) * 2 - 1;
            pointer.y = -((p.y - rect.top) / rect.height) * 2 + 1;
            raycaster.setFromCamera(pointer, camera);
            const intersects = raycaster.intersectObjects(shirtMeshList, true);
            if (intersects.length) {
                dragging = true;
                dragObject = shirt; // move the whole model
                // create plane for dragging parallel to camera
                dragPlane.setFromNormalAndCoplanarPoint(camera.getWorldDirection(new THREE.Vector3()).clone().negate(), intersects[0].point);
                // compute offset
                dragPlane.projectPoint(intersects[0].point, intersection);
                dragOffset.copy(intersection).sub(dragObject.position);
                controls.enabled = false;
                viewerCanvas.style.cursor = 'grabbing';
                // prevent OrbitControls from also handling this pointer
                try {
                    if (e.pointerId) renderer.domElement.setPointerCapture(e.pointerId);
                } catch (err) {}
                e.preventDefault();
                e.stopPropagation();
                if (e.stopImmediatePropagation) e.stopImmediatePropagation();
            }
        }

        function onPointerMove(e) {
            if (!dragging) return;
            const p = getPointerClient(e);
            const rect = renderer.domElement.getBoundingClientRect();
            pointer.x = ((p.x - rect.left) / rect.width) * 2 - 1;
            pointer.y = -((p.y - rect.top) / rect.height) * 2 + 1;
            raycaster.setFromCamera(pointer, camera);
            // intersect with drag plane
            if (raycaster.ray.intersectPlane(dragPlane, intersection)) {
                const newPos = intersection.clone().sub(dragOffset);
                dragObject.position.copy(newPos);
                // prevent other handlers
                e.preventDefault();
                e.stopPropagation();
            }
        }

        function onPointerUp(e) {
            if (dragging) {
                dragging = false;
                // release pointer capture if set
                try {
                    if (e.pointerId) renderer.domElement.releasePointerCapture(e.pointerId);
                } catch (err) {}
                dragObject = null;
                controls.enabled = true;
                viewerCanvas.style.cursor = 'grab';
                e.preventDefault();
                e.stopPropagation();
            }
        }

        // Attach events. Use capture on pointerdown so drag gets priority over OrbitControls
        renderer.domElement.style.touchAction = 'none';
        renderer.domElement.addEventListener('pointerdown', onPointerDown, { passive: false, capture: true });
        window.addEventListener('pointermove', onPointerMove, { passive: false });
        window.addEventListener('pointerup', onPointerUp, { passive: false });

        //Wait for layout to stabilize before initializing Pickr
        setTimeout(() => {
            // Once the model loads we still ensure Pickr exists (no-op if already created)
            try { initPickrIfNeeded(); } catch (e) { console.warn('initPickrIfNeeded failed after model load', e); }
        }, 300);

        // Save design button logic removed from here and attached globally below
        // hide spinner once model successfully loaded and added
        try { hideViewerSpinner(); } catch(e){}
    }
    // start model load with preferred first
    // NOTE: older code referenced loadModel/preferredModel; use tryNextModel(0) to iterate candidates
    tryNextModel(0);
        
    }

    // Auto-initialize the 3D viewer so the shirt appears by default (3D-first mode)
    try {
        initViewer();
    } catch(e) { console.warn('Auto initViewer failed', e); }

    // Attach Save Design handler (always present, not dependent on model load)
    (function attachSaveHandler(){
        const saveBtn = document.getElementById('saveDesignBtn');
        if (!saveBtn) return;

        // helper to create a toast (if toast container exists, otherwise alert)
        function showToast(msg){
            const c = document.getElementById('toast-container');
            if (c) {
                const t = document.createElement('div'); t.className='toast-msg'; t.textContent=msg; c.appendChild(t);
                setTimeout(()=>{ t.classList.add('toast-hide'); setTimeout(()=>t.remove(),300); }, 1800);
            } else {
                // fallback
                try { console.info(msg); } catch(e){}
            }
        }

        async function handleSaveClick(){
            try {
                const color = pickrInstance ? pickrInstance.getColor().toHEXA().toString() : '#ffffff';
                const size = 'Default';
                const product_id = (function(){ try { const url = new URL(window.location.href); return parseInt(url.searchParams.get('product_id') || url.searchParams.get('id') || '0',10) || 0; } catch(e){ return 0; } })();
                const meta = JSON.stringify({ camera: camera.position.toArray(), rotation: (scene.getObjectByName('loadedShirt') ? scene.getObjectByName('loadedShirt').rotation.toArray() : [0,0,0]) });

                // Capture canvas snapshot
                const canvas = renderer.domElement;

                // If user is authenticated, send to server with PNG blob
                if (window.isAuthenticated) {
                    // Ensure we render the latest frame to the drawing buffer before capture
                    try {
                        renderer.render(scene, camera);
                        await new Promise((res) => requestAnimationFrame(res));
                    } catch (e) { /* continue even if render timing fails */ }

                    // canvas.toBlob is async callback, wrap in promise
                    const blob = await new Promise((resolve) => {
                        try {
                            canvas.toBlob(function(b){ resolve(b); }, 'image/png');
                        } catch (e) { try { resolve(null); } catch(e){} }
                    });

                    const fd = new FormData();
                    fd.append('color', color);
                    fd.append('size', size);
                    fd.append('meta', meta);
                    fd.append('name', 'Custom Shirt');
                    if (product_id) fd.append('product_id', String(product_id));
                    if (blob) fd.append('design_png', blob, 'design.png');

                    try {
                        const res = await fetch('save&add.php', { method: 'POST', body: fd });
                        const data = await res.json();
                        if (data && data.status === 'ok') {
                            showToast('Design saved');
                            // If the save produced a thumbnail path, try to make the cart
                            // preview update immediately. If the current page shows the
                            // cart (element #cart-items exists) simply reload so cart.js
                            // re-queries the API and will render the thumbnail.
                            try {
                                if (document.getElementById('cart-items')) {
                                    // Reload the page so cart UI reflects the new item
                                    window.location.reload();
                                    return;
                                }
                            } catch(e) { /* ignore reload failures */ }

                            if (product_id) {
                                const did = data.designoption_id ? '&designoption_id=' + encodeURIComponent(data.designoption_id) : '';
                                window.location.href = 'product-details.php?id=' + encodeURIComponent(product_id) + did + '#order';
                                return;
                            } else {
                                window.location.href = 'products.php#order';
                                return;
                            }
                        } else {
                            console.warn('Server returned non-ok result', data);
                        }
                    } catch (err) { console.error('Server save failed', err); }
                }

                // Fallback: save an entry in localStorage including a PNG dataURL
                let pngData = null;
                try { pngData = canvas.toDataURL('image/png'); } catch(e) { pngData = null; }
                const item = { id:null, product_id: product_id||0, name:'Custom Shirt', size:size, design:'Custom 3D', color:color, price:150.00, quantity:1, is_design:true, meta: JSON.parse(meta), design_png: pngData, designoption_id: null };
                const cart = JSON.parse(localStorage.getItem('cart')||'[]'); cart.push(item); localStorage.setItem('cart', JSON.stringify(cart));
                try { if (typeof renderPreviewList === 'function') renderPreviewList(); } catch(e){}
                showToast('Design saved locally and added to cart');
            } catch (e) { console.error('Save handler failed', e); showToast('Save failed'); }
        }

        saveBtn.addEventListener('click', function(e){
            e.preventDefault();
            handleSaveClick();
        });
    })();

    // Close button logic (moved from inline script)
    var closeBtn = document.getElementById('simCloseBtn');
    if (closeBtn) {
        closeBtn.onclick = function() {
            window.close();
        };
    }

    // Do not auto-initialize the 3D viewer here. The 2D editor is the default; the
    // 3D viewer will be initialized when the user clicks "View in 3D".

    // Expose function to apply a PNG dataURL as texture to the shirt meshes
    window.applyDesignTextureFromDataURL = function(dataURL) {
        if (!dataURL) return;
        try {
            const loader = new THREE.TextureLoader();
            loader.load(dataURL, function(tex) {
                // Match the React demo: ensure the texture is treated as sRGB so colors look correct
                try {
                    if (typeof tex.colorSpace !== 'undefined' && typeof THREE.SRGBColorSpace !== 'undefined') {
                        tex.colorSpace = THREE.SRGBColorSpace;
                    } else if (typeof tex.encoding !== 'undefined' && typeof THREE.sRGBEncoding !== 'undefined') {
                        tex.encoding = THREE.sRGBEncoding;
                    }
                } catch(e) { /* ignore */ }

                // Some models / exporters and canvas exports disagree on the Y-origin
                // (top vs bottom). To robustly ensure the design appears upright on the
                // shirt we force a vertical flip on the texture using RepeatWrapping
                // with a negative Y repeat and center set to the texture midpoint.
                // This approach works regardless of the Image/GL conventions.
                try {
                    tex.flipY = false; // avoid three.js default flip interfering
                    tex.wrapS = THREE.RepeatWrapping;
                    tex.wrapT = THREE.RepeatWrapping;
                    tex.repeat.set(1, -1);
                    tex.center.set(0.5, 0.5);
                    tex.needsUpdate = true;
                } catch (e) { console.warn('Texture flip adjustment failed, continuing without it', e); }

                // keep a reference for runtime controls
                try { window.currentDesignTexture = tex; } catch(e) { /* ignore */ }

                shirtMeshList.forEach(mesh => {
                    if (mesh && mesh.material) {
                        try {
                            // assign the texture (clone material if you need unique materials per mesh)
                            mesh.material.map = tex;
                            mesh.material.needsUpdate = true;
                        } catch (err) { console.error('Failed to apply texture to mesh', err); }
                    }
                });
            }, undefined, function(err){ console.error('Texture load error', err); });
        } catch(e) { console.error('applyDesignTexture error', e); }
    };

    // Keep track of decal meshes so we can remove/replace them
    const logoDecals = [];

    // Apply a logo decal to the shirt using DecalGeometry (requires DecalGeometry script)
    window.applyLogoDecalFromDataURL = function(dataURL, opts){
        if (!dataURL) return;
        try {
            // find target mesh (first mesh in the shirtMeshList)
            const target = shirtMeshList && shirtMeshList.length ? shirtMeshList[0] : null;
            if (!target) { console.warn('No shirt mesh available to add decal'); return; }

            // remove previous decals
            while(logoDecals.length) {
                const d = logoDecals.pop();
                scene.remove(d);
                d.geometry.dispose();
                if (d.material && d.material.map) d.material.map.dispose();
                if (d.material) d.material.dispose();
            }

            const loader = new THREE.TextureLoader();
            loader.load(dataURL, function(tex){
                tex.flipY = false; // match GLTF texture orientation
                tex.needsUpdate = true;
                const decalMat = new THREE.MeshBasicMaterial({ map: tex, transparent: true, depthTest: true, depthWrite: false });

                // default options: align defaults to the threejs-react-TDesigner demo
                const scale = (opts && typeof opts.scale !== 'undefined') ? opts.scale : parseFloat(document.getElementById('logoScale')?.value || 0.12);
                // position the decal on front chest area (approx) — TDesigner uses [genP(), 0.08, 0.13]
                const position = (opts && opts.position) ? new THREE.Vector3(opts.position.x, opts.position.y, opts.position.z) : new THREE.Vector3(0, 0.08, 0.13);
                const orientation = (opts && opts.rotation) ? new THREE.Euler(opts.rotation.x, opts.rotation.y, opts.rotation.z) : new THREE.Euler(0,0,0);
                const size = new THREE.Vector3(scale, scale, scale);

                try {
                    // create decal geometry projecting onto the target mesh
                    const decalGeom = new THREE.DecalGeometry(target, position, orientation, size);
                    const decalMesh = new THREE.Mesh(decalGeom, decalMat);
                    decalMesh.renderOrder = 999;
                    scene.add(decalMesh);
                    logoDecals.push(decalMesh);
                } catch(e){ console.error('Decal creation failed', e); }
            }, undefined, function(err){ console.error('Logo texture load failed', err); });
        } catch(e){ console.warn('applyLogoDecalFromDataURL failed', e); }
    };

    // No flip UI by default (removed non-functional Flip buttons)

    // View in 3D button: read design data from hidden input or editor canvas and apply
    const view3DBtn = document.getElementById('view3DBtn');
    if (view3DBtn) {
        view3DBtn.addEventListener('click', function(){
            // Hide the inline 2D editor and reveal the 3D viewer area
            try {
                const inline = document.getElementById('editorInlineContainer');
                const viewer = document.getElementById('viewerCanvas');
                const hint = document.getElementById('rotateHint') || document.querySelector('.rotate-hint');
                if (inline) inline.style.display = 'none';
                if (viewer) viewer.style.display = 'block';
                if (hint) hint.style.display = 'block';
                try { movePickerIntoViewer(); } catch(e) { console.warn('movePickerIntoViewer call failed', e); }
            } catch (e) { console.warn('Visibility toggle failed', e); }

            // prefer hidden input
            const dataURL = document.getElementById('designDataURL')?.value;
            if (dataURL) {
                try { initViewer(); } catch(e){ console.warn('initViewer failed', e); }
                try { updateRendererSize(); } catch(e){}
                window.applyDesignTextureFromDataURL(dataURL);
                // toggle buttons
                try { document.getElementById('view3DBtn').style.display = 'none'; } catch(e){}
                try { document.getElementById('backTo2DBtn').style.display = 'inline-block'; } catch(e){}
                return;
            }
            // fallback: check for editor canvas in DOM
            const canvas = document.getElementById('editor2dCanvas');
            if (canvas && canvas.toDataURL) {
                const png = canvas.toDataURL('image/png');
                try { initViewer(); } catch(e){ console.warn('initViewer failed', e); }
                try { updateRendererSize(); } catch(e){}
                window.applyDesignTextureFromDataURL(png);
                try { document.getElementById('view3DBtn').style.display = 'none'; } catch(e){}
                try { document.getElementById('backTo2DBtn').style.display = 'inline-block'; } catch(e){}
                try { movePickerIntoViewer(); } catch(e) { console.warn('movePickerIntoViewer call failed', e); }
                return;
            }
            alert('No 2D design available. Open the 2D editor to create a design first.');
        });
    }

    // Wire the new Apply to 3D UI control (keeps UI but adds decal/full modes)
    try {
        const applyBtn = document.getElementById('applyTo3DBtn');
        if (applyBtn) {
            applyBtn.addEventListener('click', function(){
                // decide mode
                const mode = (document.getElementById('applyModeLogo')?.checked) ? 'logo' : 'full';
                // get current canvas PNG
                const canvas = document.getElementById('editor2dCanvas');
                if (!canvas) { alert('No 2D editor canvas available'); return; }
                const png = canvas.toDataURL('image/png');
                try { initViewer(); } catch(e) { console.warn('initViewer failed', e); }
                try { updateRendererSize(); } catch(e){}
                if (mode === 'full') {
                    window.applyDesignTextureFromDataURL(png);
                } else {
                    const scale = parseFloat(document.getElementById('logoScale')?.value || 0.15);
                    window.applyLogoDecalFromDataURL(png, { scale: scale });
                }
                // show viewer controls
                try { document.getElementById('view3DBtn').style.display = 'none'; } catch(e){}
                try { document.getElementById('backTo2DBtn').style.display = 'inline-block'; } catch(e){}
                try { movePickerIntoViewer(); } catch(e) { console.warn('movePickerIntoViewer call failed', e); }
            });
        }
        // toggle logo controls show/hide
        const logoRadio = document.getElementById('applyModeLogo');
        const fullRadio = document.getElementById('applyModeFull');
        function updateLogoControls(){ const show = !!(logoRadio && logoRadio.checked); const c = document.getElementById('logoControls'); if (c) c.style.display = show ? 'flex' : 'none'; }
        if (logoRadio) logoRadio.addEventListener('change', updateLogoControls);
        if (fullRadio) fullRadio.addEventListener('change', updateLogoControls);
        updateLogoControls();
    } catch(e) { console.warn('Apply-to-3D wiring failed', e); }

    // Keep track of the color picker original location so we can move it into the viewer
    let colorPickerOriginalParent = null;
    let colorPickerOriginalNext = null;

    function movePickerIntoViewer() {
        try {
            const picker = document.getElementById('colorPickerContainer');
            const wrapper = document.getElementById('shirt3d-wrapper');
            if (!picker || !wrapper) return;
            if (!colorPickerOriginalParent) {
                colorPickerOriginalParent = picker.parentElement;
                colorPickerOriginalNext = picker.nextElementSibling;
            }
            // append to the wrapper and add class for viewer styling
            wrapper.appendChild(picker);
            picker.classList.add('in-viewer');
        } catch (e) { console.warn('movePickerIntoViewer failed', e); }
    }

    function movePickerOutOfViewer() {
        try {
            const picker = document.getElementById('colorPickerContainer');
            if (!picker || !colorPickerOriginalParent) return;
            picker.classList.remove('in-viewer');
            if (colorPickerOriginalNext && colorPickerOriginalNext.parentElement === colorPickerOriginalParent) {
                colorPickerOriginalParent.insertBefore(picker, colorPickerOriginalNext);
            } else {
                colorPickerOriginalParent.appendChild(picker);
            }
        } catch (e) { console.warn('movePickerOutOfViewer failed', e); }
    }

    // Back to 2D button: show inline editor and hide 3D viewer
    const backTo2DBtn = document.getElementById('backTo2DBtn');
    if (backTo2DBtn) {
        backTo2DBtn.addEventListener('click', function(){
            try {
                const inline = document.getElementById('editorInlineContainer');
                const viewer = document.getElementById('viewerCanvas');
                const hint = document.getElementById('rotateHint') || document.querySelector('.rotate-hint');
                if (inline) inline.style.display = 'block';
                if (viewer) viewer.style.display = 'none';
                if (hint) hint.style.display = 'none';
                // show/hide buttons
                const viewBtn = document.getElementById('view3DBtn');
                if (viewBtn) viewBtn.style.display = 'inline-block';
                backTo2DBtn.style.display = 'none';
                // ensure the inline editor canvas resizes to the wrapper
                try { if (typeof window.editor2dResizeCanvas === 'function') window.editor2dResizeCanvas(); } catch(e) { console.warn(e); }
                try { movePickerOutOfViewer(); } catch(e) { console.warn('movePickerOutOfViewer call failed', e); }
            } catch (e) { console.warn('Back to 2D toggle failed', e); }
        });
    }

    // Move back button logic to external JS or here if not present in sim.js
    var backBtn = document.getElementById('sim-back-btn');
    if (backBtn) {
        backBtn.addEventListener('click', function(e) {
            e.preventDefault();
            history.back();
        });
    }
});