
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
    // expose for debugging in the console
    try { window.shirtMeshList = shirtMeshList; } catch(e){}
    // keep a reference to the currently-applied design texture so user can flip/adjust it at runtime
    window.currentDesignTexture = null;
    let viewerInitialized = false;
    // Promise that resolves when a model has been loaded and shirtMeshList populated
    let _modelReadyResolve = null;
    const modelReady = new Promise((res) => { _modelReadyResolve = res; });
    let pickrInstance = null;
    let lastScrollY = 0;

    // Scene setup
    const scene = new THREE.Scene();
    scene.background = new THREE.Color(0xf4f4f4);
    // expose scene for debugging
    try { window.simScene = scene; } catch(e){}

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
    // decal dragging state
    let draggingDecal = false;
    let dragDecal = null;
    let lastDecalMove = 0;
    // preview mesh used while dragging (cheap plane); reused across drags
    let previewDecalPlane = null;
    let previewDecalMaterial = null;

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
    // Shirt 3d models
    const modelCandidates = [
        //defaults
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
            // signal that a model is ready for decals
            try { if (typeof _modelReadyResolve === 'function') _modelReadyResolve(true); } catch(e){}
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
                try { window.shirtMeshList = shirtMeshList; } catch(e){}
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

            // Prioritize decal selection for dragging if user clicked a decal
            try {
                const decalHits = raycaster.intersectObjects(logoDecals, true);
                if (decalHits && decalHits.length) {
                    // begin decal drag: create a lightweight preview plane to follow the pointer
                    draggingDecal = true;
                    dragDecal = decalHits[0].object;
                    // compute a drag plane using the local surface normal if available
                    let dNormal = null;
                    if (decalHits[0].face) {
                        dNormal = decalHits[0].face.normal.clone();
                        dNormal.applyMatrix3(new THREE.Matrix3().getNormalMatrix(decalHits[0].object.matrixWorld)).normalize();
                    } else {
                        dNormal = camera.getWorldDirection(new THREE.Vector3()).clone().negate().normalize();
                    }
                    dragPlane.setFromNormalAndCoplanarPoint(dNormal, decalHits[0].point);

                    // Create or reuse preview plane material (use existing decal texture if available)
                    try {
                        const existingTex = decalHits[0].object.material && decalHits[0].object.material.map ? decalHits[0].object.material.map : null;
                        if (!previewDecalMaterial) {
                            previewDecalMaterial = new THREE.MeshBasicMaterial({ transparent: true, depthTest: false, depthWrite: false, side: THREE.DoubleSide });
                        }
                        if (existingTex) {
                            previewDecalMaterial.map = existingTex;
                            previewDecalMaterial.needsUpdate = true;
                        } else if (window.lastDecalDataURL) {
                            // lazy load the texture for preview
                            const tloader = new THREE.TextureLoader();
                            tloader.load(window.lastDecalDataURL, (t) => { try { previewDecalMaterial.map = t; previewDecalMaterial.needsUpdate = true; } catch(e){} });
                        }

                        if (!previewDecalPlane) {
                            const planeGeom = new THREE.PlaneGeometry(0.5, 0.2);
                            previewDecalPlane = new THREE.Mesh(planeGeom, previewDecalMaterial);
                            previewDecalPlane.renderOrder = 2000;
                            scene.add(previewDecalPlane);
                        }
                    } catch (err) { console.warn('Failed to create preview plane', err); }

                    controls.enabled = false;
                    viewerCanvas.style.cursor = 'grabbing';
                    try { if (e.pointerId) renderer.domElement.setPointerCapture(e.pointerId); } catch(err){}
                    e.preventDefault(); e.stopPropagation(); if (e.stopImmediatePropagation) e.stopImmediatePropagation();
                    return;
                }
            } catch(err) { /* continue to shirt dragging fallback */ }

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
            // Handle decal dragging separately
            if (draggingDecal) {
                // Move preview plane only (cheap). We do not recreate DecalGeometry until drop.
                const now = Date.now();
                if (now - lastDecalMove < 40) return; // throttle update for smoothness
                lastDecalMove = now;
                const p = getPointerClient(e);
                const rect = renderer.domElement.getBoundingClientRect();
                pointer.x = ((p.x - rect.left) / rect.width) * 2 - 1;
                pointer.y = -((p.y - rect.top) / rect.height) * 2 + 1;
                raycaster.setFromCamera(pointer, camera);
                const hits = raycaster.intersectObjects(shirtMeshList, true);
                if (hits && hits.length) {
                    const hit = hits[0];
                    const pos = hit.point.clone();
                    let normal = null;
                    if (hit.face) {
                        normal = hit.face.normal.clone();
                        normal.applyMatrix3(new THREE.Matrix3().getNormalMatrix(hit.object.matrixWorld)).normalize();
                    } else {
                        normal = camera.getWorldDirection(new THREE.Vector3()).clone().negate().normalize();
                    }

                    // position and orient preview plane
                    if (previewDecalPlane) {
                        previewDecalPlane.position.copy(pos.clone().add(normal.clone().multiplyScalar( (window.lastDecalOpts && window.lastDecalOpts.offset) || 0.01 )));
                        previewDecalPlane.lookAt(previewDecalPlane.position.clone().add(normal));
                        // scale plane based on lastDecalOpts.scale or approximate texture aspect
                        try {
                            const w = (window.lastDecalOpts && window.lastDecalOpts.scale) ? window.lastDecalOpts.scale : Math.max(hit.object.geometry?.boundingBox?.getSize(new THREE.Vector3()).x * 0.4 || 0.4, 0.3);
                            // try to set plane size using material texture aspect
                            const tex = previewDecalMaterial && previewDecalMaterial.map;
                            const aspect = tex && tex.image ? (tex.image.width / Math.max(1, tex.image.height)) : 2;
                            previewDecalPlane.scale.set(w * aspect, w, 1);
                        } catch(e) { /* ignore scaling errors */ }
                    }
                }
                e.preventDefault(); e.stopPropagation();
                return;
            }

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
            // If we were dragging a decal, finalize using the preview plane's world position
            if (draggingDecal) {
                try {
                    const p = getPointerClient(e);
                    const rect = renderer.domElement.getBoundingClientRect();
                    pointer.x = ((p.x - rect.left) / rect.width) * 2 - 1;
                    pointer.y = -((p.y - rect.top) / rect.height) * 2 + 1;
                    raycaster.setFromCamera(pointer, camera);
                    const hits = raycaster.intersectObjects(shirtMeshList, true);
                    let finalPos, finalNormal;
                    if (hits && hits.length) {
                        const hit = hits[0];
                        finalPos = hit.point.clone();
                        if (hit.face) {
                            finalNormal = hit.face.normal.clone();
                            finalNormal.applyMatrix3(new THREE.Matrix3().getNormalMatrix(hit.object.matrixWorld)).normalize();
                        } else {
                            finalNormal = camera.getWorldDirection(new THREE.Vector3()).clone().negate().normalize();
                        }
                        // nudge out a touch to avoid z-fighting
                        finalPos.add(finalNormal.clone().multiplyScalar((window.lastDecalOpts && window.lastDecalOpts.offset) || 0.01));
                    } else {
                        // fallback: place on shirt center facing camera
                        try {
                            const box = new THREE.Box3().setFromObject(scene.getObjectByName('loadedShirt') || scene);
                            const center = box.getCenter(new THREE.Vector3());
                            finalNormal = camera.getWorldDirection(new THREE.Vector3()).clone().negate().normalize();
                            finalPos = center.clone().add(finalNormal.clone().multiplyScalar((window.lastDecalOpts && window.lastDecalOpts.offset) || 0.01));
                        } catch (e) {
                            finalNormal = camera.getWorldDirection(new THREE.Vector3()).clone().negate().normalize();
                            finalPos = camera.position.clone().add(finalNormal.clone().multiplyScalar(1.0));
                        }
                    }

                    // remove preview plane and release its resources (plane geometry is recreated later as decal)
                    try {
                        if (previewDecalPlane) {
                            scene.remove(previewDecalPlane);
                            try { previewDecalPlane.geometry.dispose(); } catch(e){}
                            previewDecalPlane = null;
                        }
                        if (previewDecalMaterial) {
                            previewDecalMaterial.map = null;
                            previewDecalMaterial.needsUpdate = true;
                        }
                    } catch (e) { /* ignore */ }

                    // finalize: create decal(s) at the computed world position/normal
                    try {
                        const opts = Object.assign({}, window.lastDecalOpts || {}, { position: finalPos, normal: finalNormal });
                        if (window.lastDecalDataURL) {
                            window.applyLogoDecalFromDataURL(window.lastDecalDataURL, opts);
                        }
                    } catch (err) { console.warn('Finalizing decal creation failed', err); }

                } catch (err) { console.warn('Pointer up decal finalize error', err); }
                draggingDecal = false;
                dragDecal = null;
                controls.enabled = true;
                viewerCanvas.style.cursor = 'grab';
                try {
                    if (e.pointerId) renderer.domElement.releasePointerCapture(e.pointerId);
                } catch (err) {}
                e.preventDefault();
                e.stopPropagation();
                return;
            }

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

        function showToast(msg){
            const c = document.getElementById('toast-container');
            if (c) {
                const t = document.createElement('div'); t.className='toast-msg'; t.textContent=msg; c.appendChild(t);
                setTimeout(()=>{ t.classList.add('toast-hide'); setTimeout(()=>t.remove(),300); }, 1800);
            } else {
                try { console.info(msg); } catch(e){}
            }
        }

        async function handleSaveClick(){
            try {
                const color = pickrInstance ? pickrInstance.getColor().toHEXA().toString() : '#ffffff';
                const size = 'Default';
                const product_id = (function(){ try { const url = new URL(window.location.href); return parseInt(url.searchParams.get('product_id') || url.searchParams.get('id') || '0',10) || 0; } catch(e){ return 0; } })();
                const meta = JSON.stringify({ camera: camera.position.toArray(), rotation: (scene.getObjectByName('loadedShirt') ? scene.getObjectByName('loadedShirt').rotation.toArray() : [0,0,0]) });

                const canvas = renderer.domElement;

                if (window.isAuthenticated) {
                    try {
                        renderer.render(scene, camera);
                        await new Promise((res) => requestAnimationFrame(res));
                    } catch (e) { /* continue even if render timing fails */ }

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
                            try {
                                if (document.getElementById('cart-items')) {
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
    try { window.logoDecals = logoDecals; } catch(e){}

    // Apply a logo decal to the shirt using DecalGeometry (requires DecalGeometry script)
    window.applyLogoDecalFromDataURL = function(dataURL, opts){
        if (!dataURL) return;
        try {
            // remember last applied decal so interactive dragging can reapply at a new position
            try { window.lastDecalDataURL = dataURL; window.lastDecalOpts = opts || {}; } catch(e){}
            // choose a target mesh. prefer the largest/front-most mesh instead of the simple first element
            let target = null;
            if (shirtMeshList && shirtMeshList.length) {
                let best = null;
                let bestArea = -Infinity;
                shirtMeshList.forEach(m => {
                    try {
                        const b = new THREE.Box3().setFromObject(m);
                        const s = new THREE.Vector3(); b.getSize(s);
                        const area = Math.abs(s.x * s.y);
                        if (area > bestArea) { bestArea = area; best = m; }
                    } catch(e) { /* ignore malformed meshes */ }
                });
                target = best || shirtMeshList[0];
            }
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
            console.debug('applyLogoDecalFromDataURL: loading texture from dataURL (len=' + (dataURL && dataURL.length) + ')');
            loader.load(dataURL, function(tex){
                tex.flipY = false; // match GLTF texture orientation
                tex.needsUpdate = true;
                // make decal material double-sided and use polygon offset to avoid z-fighting
                const decalMat = new THREE.MeshBasicMaterial({ map: tex, transparent: true, depthTest: true, depthWrite: false });
                try {
                    decalMat.side = THREE.DoubleSide;
                    decalMat.polygonOffset = true;
                    decalMat.polygonOffsetFactor = -4;
                    decalMat.polygonOffsetUnits = -4;
                } catch(e) { /* ignore if constants missing */ }
                // Determine an automatic placement and scale so the decal appears front-center
                try {
                    // compute bounding box of the target mesh in world space
                    const box = new THREE.Box3().setFromObject(target);
                    const boxSize = new THREE.Vector3(); box.getSize(boxSize);
                    const center = box.getCenter(new THREE.Vector3());

                    // attempt a raycast from camera through the screen center (visible front-most surface)
                    // this is more likely to hit the visible chest area than raycasting to the mesh geometric center
                    raycaster.setFromCamera(new THREE.Vector2(0, 0), camera);
                    const intersects = raycaster.intersectObjects(shirtMeshList, true);

                    // derive outward normal roughly pointing toward the camera as fallback
                    const camNormal = camera.getWorldDirection(new THREE.Vector3()).clone().negate().normalize();

                    // small offset along normal to avoid z-fighting with the mesh surface
                    const defaultOffset = (opts && typeof opts.offset !== 'undefined') ? opts.offset : Math.max(boxSize.length() * 0.01, 0.01);

                    let position, normal;
                    if (intersects && intersects.length) {
                        position = intersects[0].point.clone();
                        // compute world-space normal from face (if available)
                        if (intersects[0].face) {
                            normal = intersects[0].face.normal.clone();
                            // transform to world
                            normal.applyMatrix3(new THREE.Matrix3().getNormalMatrix(intersects[0].object.matrixWorld)).normalize();
                        } else {
                            normal = camNormal;
                        }
                        // push out a touch to avoid z-fighting
                        position.add(normal.clone().multiplyScalar(defaultOffset));
                        // choose the actual object we hit as the decal target so it projects onto the visible surface
                        if (intersects[0].object) target = intersects[0].object;
                    } else {
                        normal = camNormal;
                        position = center.clone().add(normal.clone().multiplyScalar(defaultOffset));
                    }

                    // decide decal orientation so its Z axis aligns with the computed normal
                    const quat = new THREE.Quaternion();
                    quat.setFromUnitVectors(new THREE.Vector3(0, 0, 1), normal);
                    const orientation = new THREE.Euler().setFromQuaternion(quat, 'XYZ');

                    // compute scale from mesh box so decal fits chest area while respecting texture aspect
                    // coverage multipliers to make text clearly visible on the chest
                    let maxWidth = boxSize.x * 0.7;   // use ~70% of mesh width
                    let maxHeight = boxSize.y * 0.55; // limit height to ~55% of mesh height
                    // fallback if box sizes are zero-ish
                    if (!isFinite(maxWidth) || maxWidth <= 0) maxWidth = (opts && opts.scale) ? opts.scale : 0.4;
                    if (!isFinite(maxHeight) || maxHeight <= 0) maxHeight = (opts && opts.scale) ? opts.scale : 0.15;

                    // texture aspect
                    const img = tex.image || {};
                    const texW = img.width || 256;
                    const texH = img.height || 128;
                    const aspect = texW / Math.max(1, texH);

                    let width, height;
                    if (aspect >= 1) {
                        width = Math.min(maxWidth, maxHeight * aspect);
                        height = width / aspect;
                    } else {
                        height = Math.min(maxHeight, maxWidth / aspect);
                        width = height * aspect;
                    }

                    // slight depth for decal projection
                    const depth = Math.max(boxSize.z * 0.02, 0.01);

                    const size = new THREE.Vector3(width, height, depth);

                    // If caller provided an explicit world position/normal, prefer that instead of raycast placement
                    if (opts && opts.position && opts.normal) {
                        try {
                            // accept either arrays or Vector3-like objects
                            const p = opts.position;
                            const n = opts.normal;
                            const posVec = (p.isVector3) ? p.clone() : new THREE.Vector3((p.x !== undefined) ? p.x : p[0], (p.y !== undefined) ? p.y : p[1], (p.z !== undefined) ? p.z : p[2]);
                            const normVec = (n.isVector3) ? n.clone() : new THREE.Vector3((n.x !== undefined) ? n.x : n[0], (n.y !== undefined) ? n.y : n[1], (n.z !== undefined) ? n.z : n[2]);
                            // apply offset if requested
                            const off = (opts && typeof opts.offset !== 'undefined') ? opts.offset : defaultOffset;
                            position = posVec.add(normVec.clone().multiplyScalar(off));
                            normal = normVec.normalize();
                        } catch (e) { /* fall back to computed placement below */ }
                    }

                    // Project the decal onto nearby shirt meshes so it conforms to the model topology
                    let createdAny = false;
                    try {
                        const proximityRadius = Math.max(boxSize.length() * 0.6, 0.25);
                        const candidates = (shirtMeshList || []).filter(m => {
                            try {
                                const mb = new THREE.Box3().setFromObject(m);
                                const mc = mb.getCenter(new THREE.Vector3());
                                const dist = mc.distanceTo(position);
                                return dist <= proximityRadius;
                            } catch(e) { return false; }
                        });

                        // If no nearby candidates found, fallback to using the chosen target only
                        const useList = (candidates && candidates.length) ? candidates : [target];

                        useList.forEach(m => {
                            try {
                                const dGeom = new THREE.DecalGeometry(m, position, orientation, size);
                                const dMesh = new THREE.Mesh(dGeom, decalMat);
                                dMesh.renderOrder = 999;
                                // store metadata so we can recreate/move the decal later if needed
                                try { dMesh.userData = { size: size.clone(), texW: tex.image?.width || 0, texH: tex.image?.height || 0, planeMode: false }; } catch(e){}
                                scene.add(dMesh);
                                logoDecals.push(dMesh);
                                createdAny = true;
                            } catch(e) { /* ignore per-mesh failures */ }
                        });
                    } catch(e) { console.warn('Multi-mesh decal projection failed', e); }

                    // If nothing was created (degenerate), create a single decal on target as fallback
                    if (!createdAny) {
                        try {
                            let decalGeom = new THREE.DecalGeometry(target, position, orientation, size);
                            let decalMesh = new THREE.Mesh(decalGeom, decalMat);
                            decalMesh.renderOrder = 999;
                            scene.add(decalMesh);
                            logoDecals.push(decalMesh);
                        } catch(e) { console.error('Decal creation failed fallback', e); }
                    }
                    // if the generated decal geometry is unexpectedly tiny, retry with a bigger multiplier
                    try {
                        const db = new THREE.Box3().setFromObject(decalMesh);
                        const ds = new THREE.Vector3(); db.getSize(ds);
                        const minDim = Math.min(ds.x, ds.y);
                        const threshold = Math.max(boxSize.length() * 0.02, 0.02);
                        if (minDim > 0 && minDim < threshold) {
                            // remove tiny decal
                            scene.remove(decalMesh);
                            try { decalGeom.dispose(); } catch(e){}
                            try { decalMesh.material && decalMesh.material.dispose(); } catch(e){}
                            logoDecals.pop();
                            // fallback: create a flat plane slightly in front of the hit point so the design is visible
                            try {
                                const planeW = Math.max(width * 1.6, boxSize.x * 0.15, 0.05);
                                const planeH = Math.max(height * 1.6, boxSize.y * 0.12, 0.03);
                                const planeGeom = new THREE.PlaneGeometry(planeW, planeH);
                                const planeMat = new THREE.MeshBasicMaterial({ map: tex, transparent: true, depthTest: false, depthWrite: false, side: THREE.DoubleSide });
                                    const plane = new THREE.Mesh(planeGeom, planeMat);
                                    plane.position.copy(position);
                                // orient plane to face the normal/camera
                                plane.lookAt(position.clone().add(normal));
                                // push out a bit more so it is not occluded
                                plane.position.add(normal.clone().multiplyScalar(defaultOffset * 0.6));
                                plane.renderOrder = 1000;
                                    try { plane.userData = { planeMode: true, texW: tex.image?.width || 0, texH: tex.image?.height || 0 }; } catch(e){}
                                    scene.add(plane);
                                    logoDecals.push(plane);
                            } catch(e) { console.warn('Plane fallback failed', e); }
                        }
                    } catch(e){ /* ignore fallback failures */ }
                    try { window.logoDecals = logoDecals; } catch(e){}
                    console.debug('applyLogoDecalFromDataURL: decal added (auto-centered/raycast)', { decal: decalMesh, position, size, boxSize, texW, texH, usedRaycast: !!(intersects && intersects.length) }, 'logoDecals.length=', logoDecals.length);
                } catch(e){
                    console.error('Decal creation failed', e);
                }
            }, undefined, function(err){ console.error('Logo texture load failed', err); });
        } catch(e){ console.warn('applyLogoDecalFromDataURL failed', e); }
    };

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

    // Real-time text wiring: reflect changes in the font text controls immediately on the 3D shirt
    try {
        // Expose a helper to clear existing decals
        function clearLogoDecals() {
            try {
                while (logoDecals.length) {
                    const d = logoDecals.pop();
                    try { scene.remove(d); } catch(e){}
                    try { if (d.geometry) d.geometry.dispose(); } catch(e){}
                    try { if (d.material && d.material.map) d.material.map.dispose(); } catch(e){}
                    try { if (d.material) d.material.dispose(); } catch(e){}
                }
            } catch (e) { console.warn('clearLogoDecals failed', e); }
        }
        window.clearLogoDecals = clearLogoDecals;

        function synthTextPNG(text, color, size, fontFamily) {
            const cw = Math.min(2048, Math.max(256, Math.round(size * Math.max(3, text.length))));
            const ch = Math.min(1024, Math.max(128, Math.round(size * 1.6)));
            const tmp = document.createElement('canvas'); tmp.width = cw; tmp.height = ch;
            const ctx = tmp.getContext('2d');
            ctx.clearRect(0,0,cw,ch);
            let ff = fontFamily || 'Poppins';
            if (ff.indexOf(',') === -1) ff = '"' + ff + '"';
            ctx.fillStyle = color || '#000000';
            ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
            ctx.font = size + 'px ' + ff;
            try {
                ctx.lineWidth = Math.max(2, Math.round(size / 18));
                ctx.strokeStyle = 'rgba(255,255,255,0.12)';
                ctx.strokeText(text, cw/2, ch/2);
            } catch(e){}
            ctx.fillText(text, cw/2, ch/2);
            try { return tmp.toDataURL('image/png'); } catch(e) { console.error('synthTextPNG failed', e); return null; }
        }

        async function applyTextLive() {
            const text = (document.getElementById('fontText')?.value || '').trim();
            const color = document.getElementById('fontColor')?.value || '#000000';
            const size = parseInt(document.getElementById('fontSize')?.value || '72', 10) || 72;
            const fontFamily = (document.getElementById('fontFamily')?.value || 'Poppins').trim();
            if (!text) { clearLogoDecals(); return; }
            const png = synthTextPNG(text, color, size, fontFamily);
            if (!png) return;
                try { initViewer(); } catch(e) { /* ignore */ }
                // wait briefly for model to be ready (resolve occurs when onModelLoaded adds the shirt)
                try { await Promise.race([modelReady, new Promise(res=>setTimeout(res,3000))]); } catch(e){}
                try { updateRendererSize(); } catch(e) {}
            const scale = Math.min(0.9, Math.max(0.02, size / 320));
            try { 
                // remove previous decals so updates replace instead of stacking
                try { clearLogoDecals(); } catch(e){}
                window.applyLogoDecalFromDataURL(png, { scale: scale }); 
            } catch(e) { console.warn('applyLogoDecalFromDataURL failed', e); }
            try { document.getElementById('view3DBtn').style.display = 'none'; } catch(e){}
            try { document.getElementById('backTo2DBtn').style.display = 'inline-block'; } catch(e){}
            try { movePickerIntoViewer(); } catch(e) { /* ignore */ }
        }

        const debouncedApply = (function(){ let t; return function(){ clearTimeout(t); t = setTimeout(()=>{ applyTextLive().catch?applyTextLive():applyTextLive(); }, 220); }; })();

        const fontTextEl = document.getElementById('fontText'); if (fontTextEl) fontTextEl.addEventListener('input', debouncedApply);
        const fontColorEl = document.getElementById('fontColor'); if (fontColorEl) fontColorEl.addEventListener('input', debouncedApply);
        const fontSizeEl = document.getElementById('fontSize'); if (fontSizeEl) fontSizeEl.addEventListener('input', debouncedApply);
        const fontFamEl = document.getElementById('fontFamily'); if (fontFamEl) fontFamEl.addEventListener('change', debouncedApply);

        // initial apply if text already present
        setTimeout(function(){ if ((document.getElementById('fontText')?.value || '').trim()) debouncedApply(); }, 300);
    } catch(e) { console.warn('Real-time text wiring failed', e); }

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