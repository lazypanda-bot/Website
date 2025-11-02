// Lightweight 2D editor - minimal text & drag support, exports PNG dataURL and JSON model
(function(){
  // whether the editor should flip the canvas vertically when saving/exporting
  // kept for compatibility but UI is simplified (no checkbox shown)
  window.editorFlipOnSave = (typeof window.editorFlipOnSave === 'boolean') ? window.editorFlipOnSave : true;
  function createEditorDOM(){
    if (document.getElementById('editor2dModal')) return;
    const modal = document.createElement('div');
    modal.id = 'editor2dModal';
    modal.className = 'editor2d-modal';
    modal.innerHTML = `
      <div class="editor2d-card">
        <button class="editor2d-close" id="editor2dCloseBtn" aria-label="Close">×</button>
          <div class="editor2d-left">
          <div class="editor2d-canvas-wrap">
            <canvas id="editor2dCanvas" width="800" height="600"></canvas>
          </div>
          <div class="editor2d-help"></div>
        </div>
        <div class="editor2d-right">
                <div class="editor2d-toolbar">
                  <div id="editor2dColorControls" style="display:flex;gap:8px;align-items:center;">
                      <div id="editor2dColorPicker" style="width:260px;min-width:200px;"></div>
                    </div>
                </div>
          <div style="height:12px"></div>
          <label style="font-size:13px;color:#666;margin-bottom:6px;">Canvas size</label>
          <div style="display:flex;gap:8px;margin-bottom:8px;"><input id="editor2dW" class="editor2d-input" value="800" /><input id="editor2dH" class="editor2d-input" value="600" /></div>
          <div class="editor2d-actions">
          </div>
        </div>
      </div>`;
    document.body.appendChild(modal);

  // event wiring (minimal UI for non-technical users)
    document.getElementById('editor2dCloseBtn').addEventListener('click', closeEditor);
  // Add text is handled from the left column control; no inline Add/Save in the modal
    document.getElementById('editor2dW').addEventListener('change', resizeCanvasFromInputs);
    document.getElementById('editor2dH').addEventListener('change', resizeCanvasFromInputs);
  // initialize color picker (modal)
  try { initEditorColorControls(); } catch(e) { console.warn('initEditorColorControls failed (modal)', e); }
  }

  // editor state
  let elements = []; // { type:'text', text, x,y, fontSize, color }
  let canvas, ctx;
  let bgImage = null;
  // image cache for element bitmaps
  const _imgCache = new WeakMap();
  const templateLog = [];
  window.editorTemplateLog = templateLog;
  let tintColor = null;
  let dragIndex = -1, dragOffsetX=0, dragOffsetY=0;
  let bgDrawRect = { x:0, y:0, w:0, h:0 };

  function initCanvas(){
    canvas = document.getElementById('editor2dCanvas');
    if (!canvas) return;
    ctx = canvas.getContext('2d');
    // high-DPI handling
    function fixDPI(){
      const w = parseInt(document.getElementById('editor2dW').value || canvas.width,10);
      const h = parseInt(document.getElementById('editor2dH').value || canvas.height,10);
      const ratio = window.devicePixelRatio || 1;
      canvas.style.width = w + 'px';
      canvas.style.height = h + 'px';
      canvas.width = Math.round(w * ratio);
      canvas.height = Math.round(h * ratio);
      ctx.setTransform(ratio,0,0,ratio,0,0);
    }
    fixDPI();
    window.editor2dResizeCanvas = fixDPI;

    // By default use the shipped shirt mockup as the template so users see
    // a realistic blank shirt. If another script explicitly sets
    // window.editorTemplateDataURL, that takes precedence.
    try {
      if (window.editorTemplateDataURL) {
        bgImage = new Image();
        bgImage.crossOrigin = 'anonymous';
        bgImage.onload = function(){ console.info('editor: template loaded from dataURL'); render(); };
        bgImage.onerror = function(e){ console.warn('editor: template dataURL failed to load', e); bgImage = null; };
        bgImage.src = window.editorTemplateDataURL;
      } else {
        // try a small fallback chain and log progress to assist debugging when
        // images don't appear in the editor.
        const trySources = ['Shirt/shirt-mockup.png','shirt/shirt-mockup.png','t-shirt-outline/9862556.jpg'];
        let tryIndex = 0;
        function tryNextSrc(){
          if (tryIndex >= trySources.length) { console.warn('editor: no template images found'); bgImage = null; return; }
          const src = trySources[tryIndex++];
          bgImage = new Image();
          bgImage.crossOrigin = 'anonymous';
          bgImage.onload = function(){ const msg = 'loaded:'+src; console.info(msg); templateLog.push(msg); render(); };
          bgImage.onerror = function(err){ const msg = 'failed:'+src; console.warn(msg, err); templateLog.push(msg + ' ' + (err && err.message ? err.message : 'error')); tryNextSrc(); };
          const msgTry = 'try:'+src; console.info(msgTry); templateLog.push(msgTry);
          bgImage.src = src;
        }
        tryNextSrc();
      }
    } catch (e) { bgImage = null; }

    // make the canvas size match the parent wrapper (#shirt3d-wrapper)
    updateCanvasSize();
    window.addEventListener('resize', updateCanvasSize);

    // pointer events for add/drag
    canvas.addEventListener('pointerdown', onPointerDown);
    window.addEventListener('pointermove', onPointerMove);
    window.addEventListener('pointerup', onPointerUp);
    canvas.addEventListener('dblclick', onDoubleClick);
    // re-render loop not necessary; redraw on changes
    render();
  }

  function addTextFromInput(text){
    // accept an optional text parameter; if not provided read from the left input
    const txt = (typeof text === 'string' && text.length) ? text : ((document.getElementById('editor2dTextInput') || {}).value || '');
    if (!txt) return;
    const color = getEditorColor();
    const el = { type:'text', text: txt, x: 40, y: 80 + elements.length * 30, fontSize: 28, color: color };
    elements.push(el);
    render();
  }

  // Add an image element from an Image object or file dataURL
  function addImageElement(img, opts){
    if (!img) return;
    const w = img.width || 200; const h = img.height || 200;
    const el = {
      type: 'image',
      x: 40,
      y: 40 + elements.length * 10,
      w: w,
      h: h,
      img: img,
      scale: 1
    };
    if (opts && typeof opts === 'object') {
      if (opts.x) el.x = opts.x;
      if (opts.y) el.y = opts.y;
      if (opts.w) el.w = opts.w;
      if (opts.h) el.h = opts.h;
    }
    elements.push(el);
    render();
    return el;
  }

  // Public API: accept a File object (from input/drop) and add it as an element
  window.editorAddImageFromFile = function(file){
    try {
      if (!file) return;
      const reader = new FileReader();
      reader.onload = function(ev){
        const img = new Image(); img.crossOrigin = 'anonymous';
        img.onload = function(){ addImageElement(img); };
        img.src = ev.target.result;
      };
      reader.readAsDataURL(file);
    } catch(e){ console.warn('editorAddImageFromFile failed', e); }
  };

  // Public API: set an explicit template/background from a dataURL
  window.editorSetTemplateFromDataURL = function(dataURL){
    try {
      if (!dataURL) return;
      window.editorTemplateDataURL = dataURL;
      bgImage = new Image(); bgImage.crossOrigin = 'anonymous';
      bgImage.onload = function(){ render(); };
      bgImage.onerror = function(){ bgImage = null; };
      bgImage.src = dataURL;
    } catch(e){ console.warn('editorSetTemplateFromDataURL failed', e); }
  };

  function render(){
    if (!ctx) return;
    // clear
    ctx.clearRect(0,0,canvas.width,canvas.height);
    // background (shirt base if available)
    if (bgImage && bgImage.complete) {
      try {
        // draw background using aspect-fit (use client sizes so CSS scaling is respected)
        const iw = bgImage.width, ih = bgImage.height;
        const cw = canvas.clientWidth || Math.max(320, canvas.width);
        const ch = canvas.clientHeight || Math.max(240, canvas.height);
        const scale = Math.min(cw / iw, ch / ih);
        const dw = Math.round(iw * scale);
        const dh = Math.round(ih * scale);
        const dx = Math.round((cw - dw) / 2);
        const dy = Math.round((ch - dh) / 2);
        // draw using CSS units (we set ctx transform to devicePixelRatio in updateCanvasSize)
        ctx.drawImage(bgImage, dx, dy, dw, dh);
        bgDrawRect.x = dx; bgDrawRect.y = dy; bgDrawRect.w = dw; bgDrawRect.h = dh;
      } catch(e) {
        ctx.fillStyle = '#ffffff'; ctx.fillRect(0,0,canvas.clientWidth||canvas.width,canvas.clientHeight||canvas.height);
        bgDrawRect = { x:0,y:0,w:canvas.clientWidth||canvas.width,h:canvas.clientHeight||canvas.height };
      }
    } else {
      ctx.fillStyle = '#ffffff';
      ctx.fillRect(0,0,canvas.clientWidth||canvas.width,canvas.clientHeight||canvas.height);
      bgDrawRect = { x:0,y:0,w:canvas.clientWidth||canvas.width,h:canvas.clientHeight||canvas.height };
      // Draw a small debug overlay when no template is loaded so it's obvious
      try {
        const lines = templateLog.slice(-4);
        ctx.save();
        ctx.fillStyle = 'rgba(0,0,0,0.55)';
        ctx.font = '14px Poppins, Arial, sans-serif';
        let y = 18;
        ctx.fillText('No template loaded', 8, y);
        y += 20;
        ctx.font = '12px Poppins, Arial, sans-serif';
        for (let i = 0; i < lines.length; i++){
          ctx.fillText(lines[i], 8, y);
          y += 16;
        }
        ctx.restore();
      } catch(e) { /* ignore */ }
    }
    // apply tint overlay if requested (attempt to limit tint to the shirt silhouette)
    if (tintColor) {
      try {
        // Attempt a pixel-mask approach: draw the background into a temporary canvas
        // at the same drawn size, build a mask from non-background pixels and then
        // composite a tinted layer only where the shirt pixels exist.
        const dw = bgDrawRect.w;
        const dh = bgDrawRect.h;
        const dx = bgDrawRect.x;
        const dy = bgDrawRect.y;

        // if sizes are valid, create temp canvases
        if (dw > 0 && dh > 0) {
          const tmp = document.createElement('canvas');
          tmp.width = dw; tmp.height = dh;
          const tctx = tmp.getContext('2d');
          // draw the shirt image into tmp at the scaled size
          try {
            tctx.drawImage(bgImage, 0, 0, dw, dh);
            // read pixel data to detect shirt pixels. Instead of a simple luminance
            // test, compute the dominant background color (sample edges) and
            // treat pixels similar to that background color as background. This
            // prevents the tint from covering the whole canvas when the image
            // contains a colored backdrop.
            let imgData;
            try {
              imgData = tctx.getImageData(0,0,dw,dh);
            } catch(e) {
              // getImageData can fail if the image is tainted (CORS). Fall back.
              throw e;
            }
            const data = imgData.data;
            const mask = tctx.createImageData(dw, dh);
            const mdata = mask.data;
            // sample a few points near the corners/edges to estimate background color
            function samplePixel(x,y){
              x = Math.max(0, Math.min(dw-1, x|0)); y = Math.max(0, Math.min(dh-1, y|0));
              const idx = (y*dw + x) * 4; return [data[idx], data[idx+1], data[idx+2], data[idx+3]];
            }
            const samples = [];
            samples.push(samplePixel(2,2));
            samples.push(samplePixel(dw-3,2));
            samples.push(samplePixel(2,dh-3));
            samples.push(samplePixel(dw-3,dh-3));
            samples.push(samplePixel(Math.floor(dw/2),2));
            // average samples (ignore fully transparent)
            let br=0,bg=0,bb=0,bcount=0;
            for (const s of samples){ if (s[3] > 8) { br += s[0]; bg += s[1]; bb += s[2]; bcount++; } }
            if (bcount === 0) { br = 255; bg = 255; bb = 255; bcount = 1; }
            br = Math.round(br / bcount); bg = Math.round(bg / bcount); bb = Math.round(bb / bcount);
            // color-distance threshold tuned empirically; smaller => stricter background match
            const threshold = 24;
            for (let i = 0; i < data.length; i += 4) {
              const r = data[i], g = data[i+1], b = data[i+2], a = data[i+3];
              if (a <= 8) { mdata[i+3] = 0; continue; }
              // Euclidean distance in RGB
              const dr = r - br, dg = g - bg, db = b - bb;
              const dist = Math.sqrt(dr*dr + dg*dg + db*db);
              if (dist > threshold) {
                mdata[i+3] = 255; // mark as shirt/content
              } else {
                mdata[i+3] = 0; // background
              }
            }
            // create mask canvas
            const mcanvas = document.createElement('canvas'); mcanvas.width = dw; mcanvas.height = dh;
            const mctx = mcanvas.getContext('2d');
            mctx.putImageData(mask, 0, 0);

            // draw the original (already drawn on main ctx above), but to be safe redraw from tmp
            ctx.drawImage(tmp, dx, dy, dw, dh);

            // create tinted layer and apply mask using destination-in
            const tinted = document.createElement('canvas'); tinted.width = dw; tinted.height = dh;
            const tintCtx = tinted.getContext('2d');
            tintCtx.fillStyle = tintColor;
            tintCtx.globalAlpha = 0.6;
            tintCtx.fillRect(0,0,dw,dh);
            // use mask to keep tint only on shirt pixels
            tintCtx.globalCompositeOperation = 'destination-in';
            tintCtx.drawImage(mcanvas, 0, 0);
            // draw tinted layer onto main ctx at the proper position
            ctx.drawImage(tinted, dx, dy, dw, dh);
          } catch(e) {
            // fallback: simple rectangle tint over draw rect
            ctx.save();
            ctx.globalAlpha = 0.6;
            ctx.fillStyle = tintColor;
            ctx.fillRect(bgDrawRect.x, bgDrawRect.y, bgDrawRect.w, bgDrawRect.h);
            ctx.restore();
          }
        }
      } catch(e) { console.warn('tint draw failed', e); }
    }
    // draw elements (images then text so text sits on top)
    elements.forEach((el, idx) => {
      if (el.type === 'image' && el.img && el.img.complete) {
        try {
          const iw = el.w || el.img.width;
          const ih = el.h || el.img.height;
          const dw = Math.round(iw * (el.scale || 1));
          const dh = Math.round(ih * (el.scale || 1));
          ctx.drawImage(el.img, el.x, el.y, dw, dh);
        } catch(e) { console.warn('draw image el failed', e); }
      }
    });
    elements.forEach((el, idx) => {
      if (el.type === 'text'){
        ctx.fillStyle = el.color || '#000';
        ctx.font = (el.fontSize || 28) + 'px sans-serif';
        // measure
        ctx.textBaseline='top';
        ctx.fillText(el.text, el.x, el.y);
        // optional: draw bounds for dragging (debug)
      }
    });

    // Always draw a small debug overlay (helps when canvas appears blank)
    try {
      const info = [];
      info.push('canvas: ' + (canvas ? (canvas.clientWidth + 'x' + canvas.clientHeight) : 'no-canvas'));
      info.push('bgImage: ' + (bgImage ? (bgImage.src ? bgImage.src.split('/').pop() + (bgImage.complete ? ' (loaded)' : ' (loading)') : 'image') : 'null'));
      info.push('elements: ' + elements.length);
      if (bgDrawRect) info.push('bgRect: ' + Math.round(bgDrawRect.x) + ',' + Math.round(bgDrawRect.y) + ' ' + Math.round(bgDrawRect.w) + 'x' + Math.round(bgDrawRect.h));
      const lines = (templateLog || []).slice(-4);
      ctx.save();
      ctx.globalAlpha = 0.9;
      ctx.fillStyle = '#fff';
      ctx.strokeStyle = '#c4c4c4';
      ctx.lineWidth = 1;
      ctx.fillRect(8,8,260, (14 * (2 + lines.length)) + 8);
      ctx.strokeRect(8,8,260, (14 * (2 + lines.length)) + 8);
      ctx.fillStyle = '#333';
      ctx.font = '12px Poppins, Arial, sans-serif';
      let yy = 22;
      for (let i = 0; i < info.length; i++){
        ctx.fillText(info[i], 14, yy); yy += 14;
      }
      if (lines.length) { yy += 4; ctx.fillText('log:', 14, yy); yy += 14; for (let l of lines) { ctx.fillText(l, 18, yy); yy += 14; } }
      ctx.restore();
    } catch(e) { /* ignore */ }
  }

  function getEventPos(e){
    const rect = canvas.getBoundingClientRect();
    const x = (e.clientX - rect.left);
    const y = (e.clientY - rect.top);
    return { x, y };
  }

  function hitTest(x,y){
    // simple hit: iterate reverse and check bounding box of measured text
    for (let i = elements.length-1; i >=0; --i){
      const el = elements[i];
      if (el.type === 'image'){
        const w = (el.w || el.img?.width || 0) * (el.scale || 1);
        const h = (el.h || el.img?.height || 0) * (el.scale || 1);
        if (x >= el.x && x <= el.x + w && y >= el.y && y <= el.y + h) return i;
      }
      if (el.type === 'text'){
        ctx.font = (el.fontSize||28) + 'px sans-serif';
        const w = ctx.measureText(el.text).width;
        const h = (el.fontSize||28) * 1.2;
        if (x >= el.x && x <= el.x + w && y >= el.y && y <= el.y + h) return i;
      }
    }
    return -1;
  }

  function onPointerDown(e){
    if (!canvas) return;
    const p = getEventPos(e);
    const idx = hitTest(p.x, p.y);
    if (idx >= 0){
      dragIndex = idx;
      const el = elements[idx];
      dragOffsetX = p.x - el.x;
      dragOffsetY = p.y - el.y;
      canvas.setPointerCapture && canvas.setPointerCapture(e.pointerId);
      e.preventDefault();
      return;
    }
    // if clicked empty area inside the shirt draw rect, add text; ignore clicks outside
    if (p.x >= bgDrawRect.x && p.x <= bgDrawRect.x + bgDrawRect.w && p.y >= bgDrawRect.y && p.y <= bgDrawRect.y + bgDrawRect.h) {
      const txt = (document.getElementById('editor2dTextInput') || {}).value || '';
      if (txt && txt.trim().length > 1) {
        const color = getEditorColor() || '#000000';
        const el = { type:'text', text: txt, x: p.x, y: p.y, fontSize: 28, color };
        elements.push(el);
        render();
      }
    }
  }

  function onPointerMove(e){
    if (dragIndex < 0) return;
    const p = getEventPos(e);
    const el = elements[dragIndex];
    el.x = p.x - dragOffsetX;
    el.y = p.y - dragOffsetY;
    render();
  }

  function onPointerUp(e){
    if (dragIndex >= 0) {
      dragIndex = -1;
    }
  }

  function onDoubleClick(e){
    const p = getEventPos(e);
    const idx = hitTest(p.x,p.y);
    if (idx >= 0){
      const newTxt = prompt('Edit text', elements[idx].text);
      if (newTxt !== null){ elements[idx].text = newTxt; render(); }
    }
  }

  function saveDesign(){
    if (!canvas) return;
    // Create (optionally flipped) copy of the canvas and save it so the 3D viewer
    // receives the intended orientation. The flip behavior is controlled by
    // window.editorFlipOnSave (default: true).
    try {
      let dataURL;
      if (window.editorFlipOnSave !== false) {
        const w = canvas.width;
        const h = canvas.height;
        const tmp = document.createElement('canvas');
        tmp.width = w;
        tmp.height = h;
        const tctx = tmp.getContext('2d');
        // flip vertically
        tctx.save();
        tctx.scale(1, -1);
        tctx.drawImage(canvas, 0, -h, w, h);
        tctx.restore();
        dataURL = tmp.toDataURL('image/png');
      } else {
        dataURL = canvas.toDataURL('image/png');
      }
      const model = { elements: elements.slice(), w: canvas.width, h: canvas.height };
      const j = JSON.stringify(model);
      const inputData = document.getElementById('designDataURL');
      const inputJson = document.getElementById('designJSON');
      if (inputData) inputData.value = dataURL;
      if (inputJson) inputJson.value = j;
      // user feedback
      alert('Design saved to the viewer. Use View in 3D to preview on the model.');
    } catch (e) {
      console.warn('Failed to create export during save', e);
      const dataURL = canvas.toDataURL('image/png');
      const model = { elements: elements.slice(), w: canvas.width, h: canvas.height };
      const j = JSON.stringify(model);
      const inputData = document.getElementById('designDataURL');
      const inputJson = document.getElementById('designJSON');
      if (inputData) inputData.value = dataURL;
      if (inputJson) inputJson.value = j;
    }
    closeEditor();
  }

  // Save then open 3D viewer (simple helper for non-technical users)
  function saveAndView(){
    try {
      saveDesign();
      // small delay to allow saveDesign to set hidden input
      setTimeout(()=>{
        const v = document.getElementById('view3DBtn');
        if (v) v.click();
      }, 250);
    } catch(e){ console.warn('saveAndView failed', e); }
  }

  // allow external code (sim.js) to set a shirt tint for the 2D preview
  window.editorSetTint = function(hex){
    try { tintColor = hex || null; render(); } catch(e){ console.warn('editorSetTint failed', e); }
  };

  // Editor color helpers: manage swatches, pickr and persistence
  let editorPickr = null;

  function applyEditorColor(hex){
    try {
      // normalize
      if (!hex) return;
      if (hex.charAt(0) !== '#') hex = '#' + hex;
      // set tint for preview
      tintColor = hex;
      // update any selected element default color (not changing existing elements)
      render();
      try { localStorage.setItem('editor2d_last_color', hex); } catch(e){}
      // update any visible native input if present
      const native = document.getElementById('editor2dColor'); if (native) native.value = hex;
    } catch(e){ console.warn('applyEditorColor failed', e); }
  }

  function getEditorColor(){
    try {
      // prefer pickr
      if (editorPickr && typeof editorPickr.getColor === 'function') {
        try { return editorPickr.getColor().toHEXA().toString(); } catch(e){}
      }
      // fallback to native input
      const native = document.getElementById('editor2dColor'); if (native) return native.value;
      // fallback to persisted
      try { return localStorage.getItem('editor2d_last_color') || '#000000'; } catch(e) { return '#000000'; }
    } catch(e) { return '#000000'; }
  }

  function initEditorColorControls(){
    // init Pickr in modal area if available
    try {
      if (window.Pickr && !editorPickr) {
        const container = document.getElementById('editor2dColorPicker');
        if (container) {
          editorPickr = Pickr.create({
            el: container,
            theme: 'classic',
            inline: true,
            // default to site primary color
            default: (function(){ try { return localStorage.getItem('editor2d_last_color')||'#a02b2b'; } catch(e){ return '#a02b2b'; } })(),
            components: {
              preview: true,
              opacity: false,
              hue: true,
              interaction: { hex: true, input: true, save: false }
            }
          });
          editorPickr.on('change', (color) => { try { const hex = color.toHEXA().toString(); applyEditorColor(hex); } catch(e){} });
        }
      }
    } catch(e){ console.warn('Pickr init in editor failed', e); }
    // set persisted color if present
    try { const last = localStorage.getItem('editor2d_last_color'); if (last) applyEditorColor(last); } catch(e){}
  }

  function exportPNG(){
    if (!canvas) return;
    // export PNG, optionally flipped according to window.editorFlipOnSave
    try {
      let url;
      if (window.editorFlipOnSave !== false) {
        const w = canvas.width;
        const h = canvas.height;
        const tmp = document.createElement('canvas');
        tmp.width = w; tmp.height = h;
        const tctx = tmp.getContext('2d');
        tctx.save();
        tctx.scale(1, -1);
        tctx.drawImage(canvas, 0, -h, w, h);
        tctx.restore();
        url = tmp.toDataURL('image/png');
      } else {
        url = canvas.toDataURL('image/png');
      }
      const a = document.createElement('a');
      a.href = url; a.download = 'design.png';
      document.body.appendChild(a); a.click(); a.remove();
      return;
    } catch (e) {
      console.warn('exportPNG failed, falling back', e);
      const url = canvas.toDataURL('image/png');
      const a = document.createElement('a');
      a.href = url; a.download = 'design.png';
      document.body.appendChild(a); a.click(); a.remove();
      return;
    }
  }

  function resizeCanvasFromInputs(){
    const w = parseInt(document.getElementById('editor2dW').value || '800',10);
    const h = parseInt(document.getElementById('editor2dH').value || '600',10);
    canvas.width = w; canvas.height = h; window.editor2dResizeCanvas && window.editor2dResizeCanvas(); render();
  }

  // Resize canvas to match the inline wrapper (#shirt3d-wrapper) or use fallback sizes
  function updateCanvasSize(){
    try {
      if (!canvas) canvas = document.getElementById('editor2dCanvas');
      if (!canvas) return;
      const wrapper = document.getElementById('shirt3d-wrapper') || canvas.parentElement;
      const cw = Math.max(320, Math.round((wrapper.clientWidth) || 800));
      const ch = Math.max(240, Math.round((wrapper.clientHeight) || 600));
      const ratio = window.devicePixelRatio || 1;
      // set CSS size then backing store size
      canvas.style.width = cw + 'px';
      canvas.style.height = ch + 'px';
      canvas.width = Math.round(cw * ratio);
      canvas.height = Math.round(ch * ratio);
      if (ctx) ctx.setTransform(ratio,0,0,ratio,0,0);
      render();
    } catch(e) { console.warn('updateCanvasSize failed', e); }
  }

  // expose a global to allow sim.js to trigger resize when switching back from 3D
  window.editor2dResizeCanvas = updateCanvasSize;

  function openEditor(){
    createEditorDOM();
    const modal = document.getElementById('editor2dModal');
    if (modal) modal.classList.remove('editor2d-hidden');
    // ensure DOM elements and canvas are initialized
    setTimeout(()=>{
      if (!canvas) initCanvas();
      // if there is existing design dataURL, import as background
      const dataURL = document.getElementById('designDataURL')?.value;
      if (dataURL && canvas && ctx){
        const img = new Image(); img.onload = function(){ ctx.drawImage(img,0,0,canvas.width,canvas.height); };
        img.src = dataURL;
      }
    },50);
  }

  function closeEditor(){
    const modal = document.getElementById('editor2dModal');
    if (modal) modal.classList.add('editor2d-hidden');
  }

  // expose API
  window.open2DEditor = openEditor;
  window.close2DEditor = closeEditor;

  // Create an inline editor inside #editorInlineContainer when present, otherwise create the hidden modal DOM
  function createInlineDOM(container){
    if (!container) return;
    if (container.querySelector('#editor2dCanvas')) return; // already created
    // create a full-bleed canvas area so the 2D editor fills the viewer
    container.innerHTML = `
      <div class="editor-inline-canvas-wrap" style="width:100%;height:100%;">
        <canvas id="editor2dCanvas" width="800" height="600"></canvas>
      </div>`;

    // ensure resize inputs exist (used by updateCanvasSize)
    const wInp = document.getElementById('editor2dW');
    const hInp = document.getElementById('editor2dH');
    if (!wInp){
      const wi = document.createElement('input'); wi.id='editor2dW'; wi.value='800'; wi.className='editor2d-hidden'; document.body.appendChild(wi);
    }
    if (!hInp){
      const hi = document.createElement('input'); hi.id='editor2dH'; hi.value='600'; hi.className='editor2d-hidden'; document.body.appendChild(hi);
    }
  }

  // Inline color controls init (for the inline editor area)
  function initEditorInlineColorControls(){
    try {
      if (window.Pickr) {
        const container = document.getElementById('editorInlineColorPicker');
        if (container) {
          // create a lightweight inline pickr for inline area (default to site primary)
          const p = Pickr.create({ el: container, theme: 'classic', inline: true, default: (localStorage.getItem('editor2d_last_color')||'#a02b2b'), components: { preview:true, opacity:false, hue:true, interaction:{hex:true,input:true,save:false} } });
          p.on('change', (color) => { try { applyEditorColor(color.toHEXA().toString()); } catch(e){} });
        }
      }
    } catch(e){ console.warn('inline pickr init failed', e); }
  }

  document.addEventListener('DOMContentLoaded', function(){
    try {
      const inline = document.getElementById('editorInlineContainer');
      if (inline) {
        createInlineDOM(inline);
        // wire pointer events after a small timeout to ensure canvas is in layout
        setTimeout(()=>{ initCanvas(); }, 50);
        // wire left-side Add text control (placed in sim.php left column)
        try {
          const addBtn = document.getElementById('editor2dAddText');
          const txtIn = document.getElementById('editor2dTextInput');
          if (addBtn) addBtn.addEventListener('click', function(){ addTextFromInput(); });
          if (txtIn) txtIn.addEventListener('keydown', function(e){ if (e.key === 'Enter') { addTextFromInput(); e.preventDefault(); } });
        } catch(e) { /* ignore */ }
        return;
      }
      // fallback: create the hidden modal DOM so openEditor() still works
      createEditorDOM();
      const modal = document.getElementById('editor2dModal');
      if (modal) modal.classList.add('editor2d-hidden');
    } catch(e){ console.warn('Editor init failed', e); }
  });

})();
