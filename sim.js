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
camera.position.set(0, 0, 2);

// Renderer
const renderer = new THREE.WebGLRenderer({ antialias: true });
renderer.setSize(window.innerWidth, window.innerHeight);
document.getElementById('viewerCanvas').appendChild(renderer.domElement);

// Lighting
const light = new THREE.HemisphereLight(0xffffff, 0x444444, 1);
scene.add(light);

// Controls
const controls = new THREE.OrbitControls(camera, renderer.domElement);
controls.enableDamping = true;
controls.dampingFactor = 0.05;
controls.enableZoom = false;
controls.enablePan = false;
controls.minPolarAngle = Math.PI / 2;
controls.maxPolarAngle = Math.PI / 2;

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

  const loader = new THREE.GLTFLoader();
  loader.load('t-shirt/scene.gltf', function (gltf) {
    console.log("Model loaded:", gltf.scene);
    const shirt = gltf.scene;
    shirt.position.set(0, -2.5, 0);
    shirt.scale.set(2, 2, 2);
    scene.add(shirt);

    shirt.traverse((child) => {
      if (child.isMesh) {
        child.material.color.set('#ffffff');
        shirtMeshList.push(child);
      }
    });

    // ✅ Wait for layout to stabilize before initializing Pickr
    setTimeout(() => {
      const container = document.getElementById('colorPickerContainer');
      if (!container) {
        console.error("Color picker container not found");
        return;
      }

      // Apply fallback styling directly
      container.style.border = '1px solid red';
      container.style.minHeight = '300px';
      container.style.backgroundColor = '#fff';
      container.style.padding = '10px';
      container.style.borderRadius = '8px';

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

        console.log("✅ Pickr initialized:", pickrInstance);
      }
    }, 300);
  }, undefined, function (error) {
    console.error('Error loading model:', error);
  });
}

// Modal open/close
function openViewerModal() {
  lastScrollY = window.scrollY;
  document.body.style.top = `-${lastScrollY}px`;
  document.body.style.position = 'fixed';
  document.getElementById('viewerModal').style.display = 'flex';

  initViewer();
}

function closeViewerModal() {
  document.getElementById('viewerModal').style.display = 'none';
  document.body.style.position = '';
  document.body.style.top = '';
  window.scrollTo(0, lastScrollY);
}

// Upload logic
const uploadZone = document.getElementById('uploadZone');
const browseBtn = document.getElementById('browseBtn');
const graphicUpload = document.getElementById('graphicUpload');
let uploadedFile = null;

uploadZone.addEventListener('click', () => graphicUpload.click());
browseBtn.addEventListener('click', () => graphicUpload.click());

graphicUpload.addEventListener('change', (e) => {
  uploadedFile = e.target.files[0];
});

document.getElementById('cancelUpload').addEventListener('click', () => {
  graphicUpload.value = '';
  uploadedFile = null;
});

document.getElementById('confirmUpload').addEventListener('click', () => {
  if (!uploadedFile) return;

  const reader = new FileReader();
  reader.onload = function (e) {
    const texture = new THREE.TextureLoader().load(e.target.result);
    shirtMeshList.forEach(mesh => {
      mesh.material.map = texture;
      mesh.material.needsUpdate = true;
    });
  };
  reader.readAsDataURL(uploadedFile);
});
