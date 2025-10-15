
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
    camera.position.set(0, 0, 3.5); // Move camera back to fit larger shirt

    // Renderer
    const renderer = new THREE.WebGLRenderer({ antialias: true });
    const canvasWidth = 800; 
    const canvasHeight = 600; 
    renderer.setSize(canvasWidth, canvasHeight);
    const viewerCanvas = document.getElementById('viewerCanvas');
    if (!viewerCanvas) {
        console.error('viewerCanvas element not found!');
        return;
    }
    viewerCanvas.appendChild(renderer.domElement);

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
    controls.minPolarAngle = Math.PI / 2;
    controls.maxPolarAngle = Math.PI / 2;
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
            // Compute bounding box for uniform scaling
            const box = new THREE.Box3().setFromObject(shirt);
            const size = new THREE.Vector3();
            box.getSize(size);
            // Target width and height in scene units (make shirt bigger)
            const targetWidth = 2.2;
            const targetHeight = 2.8;
            const scaleX = targetWidth / size.x;
            const scaleY = targetHeight / size.y;
            const scale = Math.min(scaleX, scaleY); // uniform scale
            shirt.scale.setScalar(scale);
            // Center the shirt
            const center = new THREE.Vector3();
            box.getCenter(center);
            shirt.position.set(-center.x * scale, -center.y * scale, 0);
            scene.add(shirt);

            shirt.traverse((child) => {
                if (child.isMesh) {
                    child.material.color.set('#ffffff');
                    shirtMeshList.push(child);
                }
            });

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
        }, undefined, function (error) {
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