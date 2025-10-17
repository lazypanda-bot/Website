
document.addEventListener('DOMContentLoaded', function() {
    if (typeof THREE === 'undefined') {
        alert('Three.js is not loaded! Please check your script order.');
        const viewerCanvas = document.getElementById('viewerCanvas');
        if (viewerCanvas) {
            viewerCanvas.innerHTML = '<div class="sim-error">Three.js is not loaded!<br>Check your script order and CDN loading.</div>';
        }
        return;
    }
    console.log("sim.js is running");

    let shirtMeshList = [];
    let viewerInitialized = false;
    let pickrInstance = null;
    let lastScrollY = 0;

    // Scene setup
    const scene = new THREE.Scene();
    scene.background = new THREE.Color(0xf4f4f4);

    // Camera setup
    const camera = new THREE.PerspectiveCamera(45, window.innerWidth / window.innerHeight, 0.1, 1000);
    camera.position.set(0, 0, 3.5); // initial camera position; will be adjusted after model loads

    // Renderer
    const renderer = new THREE.WebGLRenderer({ antialias: true });
    const viewerCanvas = document.getElementById('viewerCanvas');
    if (!viewerCanvas) {
        console.error('viewerCanvas element not found!');
        return;
    }
    viewerCanvas.appendChild(renderer.domElement);

    // Responsive renderer sizing
    function updateRendererSize() {
        const w = Math.max(320, viewerCanvas.clientWidth || 800);
        const h = Math.max(240, viewerCanvas.clientHeight || 600);
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
        loader.load('t-shirt/scene.gltf', function (gltf) {
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
                    child.material.color.set('#ffffff');
                    shirtMeshList.push(child);
                }
            });

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
                const container = document.getElementById('colorPickerContainer');
                if (!container) {
                    console.error("Color picker container not found");
                    // Show visible error in the UI
                    const rightPanel = document.querySelector('.sim-viewer-right');
                    if (rightPanel) {
                        rightPanel.insertAdjacentHTML('afterbegin', '<div class="sim-pickr-error">Error: Color picker container not found.</div>');
                    }
                    return;
                }
                try {
                    // Initialize Pickr only once
                    if (!pickrInstance) {
                        pickrInstance = Pickr.create({
                            el: container,
                            theme: 'classic',
                            inline: true,
                            showAlways: true,
                            default: '#ffffff',
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
                            shirtMeshList.forEach(mesh => {
                                mesh.material.color.set(hex);
                            });
                        });

                        pickrInstance.on('swatchselect', (color) => {
                            const hex = color.toHEXA().toString();
                            shirtMeshList.forEach(mesh => {
                                mesh.material.color.set(hex);
                            });
                        });
                        console.log("Pickr initialized:", pickrInstance);
                    }
                    } catch (e) {
                    console.error('Pickr failed to initialize:', e);
                    container.innerHTML = '<div class="sim-pickr-error">Error: Color picker failed to initialize.<br>' + e + '</div>';
                }
            }, 300);

            // Save design button logic
            const saveBtn = document.getElementById('saveDesignBtn');
            const previewList = document.getElementById('designPreviewList');
            function renderPreviewList() {
                const local = JSON.parse(localStorage.getItem('cart')||'[]');
                const previews = local.filter(i=> i.is_design);
                previewList.innerHTML = '';
                previews.forEach((d,idx)=>{
                    const card = document.createElement('div'); card.className='design-preview-card';
                    card.innerHTML = `<div class="design-preview-thumb"></div><div class="design-preview-meta"><strong>${d.name||'Custom Shirt'}</strong><div>Color: ${d.color||''}</div><div>Size: ${d.size||'Default'}</div></div>`;
                    previewList.appendChild(card);
                });
            }
            renderPreviewList();

            if (saveBtn) {
                saveBtn.addEventListener('click', async function(){
                    const color = pickrInstance ? pickrInstance.getColor().toHEXA().toString() : '#ffffff';
                    const size = 'Default';
                    const meta = JSON.stringify({ camera: camera.position.toArray(), rotation: shirt ? shirt.rotation.toArray() : [0,0,0] });

                    // get optional product_id from URL
                    let product_id = 0;
                    try { const url = new URL(window.location.href); product_id = parseInt(url.searchParams.get('product_id')||'0',10) || 0; } catch(e){}

                    // Prefer server save when authenticated
                    if (window.isAuthenticated) {
                        const fd = new FormData();
                        fd.append('color', color);
                        fd.append('size', size);
                        fd.append('meta', meta);
                        fd.append('name', 'Custom Shirt');
                        if (product_id) fd.append('product_id', String(product_id));
                        try {
                            const res = await fetch('save&add.php', { method: 'POST', body: fd });
                            const data = await res.json();
                            if (data.status === 'ok') {
                                // if server added to cart, also refresh cart preview list (we'll still keep local preview for speed)
                                const t = document.createElement('div'); t.className='toast-msg'; t.textContent='Design saved to your account and added to cart';
                                const c = document.getElementById('toast-container') || document.body; c.appendChild(t); setTimeout(()=>{ t.classList.add('toast-hide'); setTimeout(()=>t.remove(),300); }, 2000);
                                // add local preview marker
                                const cart = JSON.parse(localStorage.getItem('cart')||'[]');
                                cart.push({ id: data.cart_id||null, product_id: product_id||0, name:'Custom Shirt', size:size, design:'Custom 3D', color:color, price:150.00, quantity:1, is_design:true, meta: JSON.parse(meta) });
                                localStorage.setItem('cart', JSON.stringify(cart));
                                renderPreviewList();
                                return;
                            }
                        } catch(err){ console.error('Server save failed', err); }
                    }

                    // Fallback: localStorage only
                    const item = { id:null, product_id: product_id||0, name:'Custom Shirt', size:size, design:'Custom 3D', color:color, price:150.00, quantity:1, is_design:true, meta: JSON.parse(meta) };
                    const cart = JSON.parse(localStorage.getItem('cart')||'[]'); cart.push(item); localStorage.setItem('cart', JSON.stringify(cart));
                    renderPreviewList();
                    const t = document.createElement('div'); t.className='toast-msg'; t.textContent='Design saved and added to cart'; const c = document.getElementById('toast-container') || document.body; c.appendChild(t); setTimeout(()=>{ t.classList.add('toast-hide'); setTimeout(()=>t.remove(),300); }, 2000);
                });
            }
        }, function (xhr) {
            if (xhr && xhr.lengthComputable) {
                const percent = (xhr.loaded / xhr.total) * 100;
                console.log('Model loading: ' + Math.round(percent) + '%');
            }
        }, function (error) {
            console.error('Error loading model:', error);
        });
    }

    // Close button logic (moved from inline script)
    var closeBtn = document.getElementById('simCloseBtn');
    if (closeBtn) {
        closeBtn.onclick = function() {
            window.close();
        };
    }

    try {
        initViewer();
    } catch (e) {
        console.error('initViewer error:', e);
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