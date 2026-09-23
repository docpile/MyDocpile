<?php
/**
 * ============================================================================
 * MODULE: High-Quality Image Converter & WYSIWYG Cropper
 * ============================================================================
 * NOTE: Executed exclusively by the client browser.
 * 
 * THIS IS PART OF THE PSEUDO "JS FILE" - SO NO PHP EXECUTABLE CODE IN THIS FILE!
 */
?><script>

window.myCloudShowImageEditor = function(path) {
    const overlay = document.getElementById('myCloudModalOverlay');
    const modal = document.getElementById('myCloudModal');
    if (typeof myCloudResetModal === 'function') myCloudResetModal();
    
    // --- ANTI-SCROLL JUMP TRACKER ---
    const scrollContainers = Array.from(document.querySelectorAll('.myCloud-commander-content, .myCloud-file-list-container, html, body'));
    const savedScrolls = scrollContainers.map(el => ({ el: el, top: el.scrollTop }));
    const oldBodyOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden'; // Lock background scrolling
    
    // Watch for ANY action that closes the modal to restore the scroll elegantly
    const modalObserver = new MutationObserver(() => {
        if (overlay.style.display === 'none') {
            document.body.style.overflow = oldBodyOverflow;
            savedScrolls.forEach(s => { if (s.el) s.el.scrollTop = s.top; });
            modalObserver.disconnect();
        }
    });
    modalObserver.observe(overlay, { attributes: true, attributeFilter: ['style'] });
    
    const L = typeof myCloud_LANG !== 'undefined' ? myCloud_LANG : {};
    const curLang = (typeof myCloudState !== 'undefined' && myCloudState.settings) ? myCloudState.settings.language : 'en';
    const isRtl = ['ar', 'fa', 'he', 'ur'].includes(curLang);
    modal.setAttribute('dir', isRtl ? 'rtl' : 'ltr');
    
    overlay.style.display = 'flex';
    overlay.style.zIndex = '90000';
    overlay.style.backgroundColor = 'rgba(0, 0, 0, 0.85)';
    
    modal.className = 'myCloudModal ce-image-editor-modal';
    modal.style.maxWidth = '1600px';
    modal.style.width = '96vw';
    modal.style.height = '96vh';
    modal.style.maxHeight = 'none';
    modal.style.display = 'flex';
    modal.style.flexDirection = 'column';

    const filename = path.split('/').pop().replace(/\.enc$/, '');
    const isE2E = typeof myCloudCrypto !== 'undefined' && myCloudCrypto.isDirEncrypted(path);

    const styleBlock = `
        <style>
            .ce-image-editor-modal { background: #202020 !important; color: #e0e0e0 !important; border: 1px solid #333 !important; box-shadow: 0 20px 50px rgba(0,0,0,0.8) !important; border-radius: 8px !important; font-family: 'Segoe UI', system-ui, sans-serif; }
            .ce-image-editor-modal .myCloudModalHeader { background: #181818 !important; border-bottom: 1px solid #2a2a2a !important; color: #fff !important; font-weight: 500; letter-spacing: 0.5px; padding: 12px 20px !important; font-size: 15px; }
            .ce-image-editor-modal .myCloudClose { color: #888 !important; transition: color 0.2s; }
            .ce-image-editor-modal .myCloudClose:hover { color: #fff !important; }
            .ie-panel-scroll::-webkit-scrollbar { width: 6px; }
            .ie-panel-scroll::-webkit-scrollbar-track { background: transparent; }
            .ie-panel-scroll::-webkit-scrollbar-thumb { background: #444; border-radius: 3px; }
            .ie-panel-scroll::-webkit-scrollbar-thumb:hover { background: #666; }
            
            .ie-slider { -webkit-appearance: none; width: 100%; height: 4px; background: #3a3a3a; border-radius: 2px; outline: none; margin: 6px 0 2px 0; }
            .ie-slider::-webkit-slider-thumb { -webkit-appearance: none; width: 14px; height: 14px; border-radius: 50%; background: var(--accent-primary, #0078d4); cursor: pointer; transition: transform 0.1s, box-shadow 0.1s; box-shadow: 0 2px 5px rgba(0,0,0,0.5); border: 2px solid #fff; }
            .ie-slider::-webkit-slider-thumb:hover { transform: scale(1.2); box-shadow: 0 3px 8px rgba(0,0,0,0.7); }
            
            .ie-input { background: #111 !important; color: #fff !important; border: 1px solid #3a3a3a !important; border-radius: 4px; padding: 6px 8px; font-size: 13px; transition: border-color 0.2s; width: 100%; box-sizing: border-box; }
            .ie-input:focus { border-color: var(--accent-primary, #0078d4) !important; outline: none; }
            
            .ie-btn { background: #2d2d2d; color: #e0e0e0; border: 1px solid #3d3d3d; border-radius: 4px; padding: 7px 12px; cursor: pointer; font-size: 13px; font-weight: 500; transition: background 0.2s, border 0.2s; text-align: center; display: flex; align-items: center; justify-content: center; gap: 6px; }
            .ie-btn:hover:not(:disabled) { background: #383838; border-color: #4a4a4a; color: #fff; }
            .ie-btn:disabled { opacity: 0.5; cursor: not-allowed; }
            
            .ie-btn-primary { background: var(--accent-primary, #0078d4) !important; border: 1px solid var(--accent-primary, #0078d4) !important; color: #fff !important; }
            .ie-btn-primary:hover:not(:disabled) { filter: brightness(1.15); box-shadow: 0 4px 12px rgba(0, 120, 212, 0.4); }
            
            .ie-btn-danger { background: transparent !important; border: 1px solid #d32f2f !important; color: #d32f2f !important; }
            .ie-btn-danger:hover:not(:disabled) { background: #d32f2f !important; color: #fff !important; }
            
            .ie-group-lbl { font-size: 11px; text-transform: uppercase; color: #888; letter-spacing: 1px; margin-bottom: 8px; font-weight: 600; display: block; border-bottom: 1px solid #333; padding-bottom: 4px; }
            .ie-control-row { margin-bottom: 10px; }
            .ie-label-row { display: flex; justify-content: space-between; align-items: baseline; }
            .ie-label { font-size: 12px; color: #ccc; }
            .ie-val { font-size: 12px; color: #999; font-variant-numeric: tabular-nums; }
        </style>
    `;

    modal.innerHTML = styleBlock + 
        '<svg width="0" height="0" style="position:absolute;z-index:-1;">' +
            '<defs>' +
                '<filter id="ie_gamma_filter">' +
                    '<feComponentTransfer>' +
                        '<feFuncR type="gamma" amplitude="1" exponent="1" id="ie_gamma_r"/>' +
                        '<feFuncG type="gamma" amplitude="1" exponent="1" id="ie_gamma_g"/>' +
                        '<feFuncB type="gamma" amplitude="1" exponent="1" id="ie_gamma_b"/>' +
                    '</feComponentTransfer>' +
                '</filter>' +
            '</defs>' +
        '</svg>' +
        '<div class="myCloudModalHeader" style="justify-content:space-between; flex-shrink: 0;">' +
            '<span>' + (L.image_convert || 'Image Editor') + ' &rsaquo; <span style="color:#aaa; font-weight:normal;">' + filename + '</span></span>' +
            '<span class="myCloudClose" onclick="myCloudCloseModal()" style="cursor:pointer; color:var(--text-secondary);" title="' + (L.close || 'Close') + '">✕</span>' +
        '</div>' +
        '<div class="myCloudModalBody" style="display:flex; flex-wrap:wrap; padding: 15px; gap: 20px; flex: 1; overflow-y: auto; background: #202020;">' +
            
            // Image Preview Area (Immersive Dark)
            '<div style="flex: 1 1 500px; min-height: 40vh; background: #080808; border: 1px solid #151515; box-shadow: inset 0 0 30px rgba(0,0,0,0.8); position: relative; overflow: hidden; border-radius: 6px; cursor: default;" id="ie_preview_wrap">' +
                '<canvas id="ie_canvas" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); transform-origin: 50% 50%; will-change: transform; filter: drop-shadow(0 4px 15px rgba(0,0,0,0.6));"></canvas>' +
                '<div id="ie_cropbox" style="position: absolute; border: 2px dashed #00a8ff; box-shadow: 0 0 0 9999px rgba(0,0,0,0.65); display: none; cursor: crosshair; z-index: 10;"></div>' +
                '<div id="ie_loading" class="myCloud-spinner" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); border-top-color: var(--accent-primary); border-right-color: #333; border-bottom-color: #333; border-left-color: #333; z-index: 20;"></div>' +
            '</div>' +
            
            // Control Panel (Right Side)
            '<div class="ie-panel-scroll" style="flex: 0 0 280px; display: flex; flex-direction: column; gap: 12px; overflow-y: auto; padding-inline-end: 8px;">' +
                
                // Color & Light Group
                '<div>' +
                    '<span class="ie-group-lbl">' + (L.ie_color_light || 'Color & Light') + '</span>' +
                    '<div class="ie-control-row">' +
                        '<div class="ie-label-row"><label class="ie-label">' + (L.ie_brightness || 'Brightness') + '</label><span id="ie_br_val" class="ie-val">0</span></div>' +
                        '<input type="range" id="ie_brightness" class="ie-slider" min="-100" max="100" value="0">' +
                    '</div>' +
                    '<div class="ie-control-row">' +
                        '<div class="ie-label-row"><label class="ie-label">' + (L.ie_contrast || 'Contrast') + '</label><span id="ie_ct_val" class="ie-val">0</span></div>' +
                        '<input type="range" id="ie_contrast" class="ie-slider" min="-100" max="100" value="0">' +
                    '</div>' +
                    '<div class="ie-control-row">' +
                        '<div class="ie-label-row"><label class="ie-label">' + (L.ie_saturation || 'Saturation') + '</label><span id="ie_sa_val" class="ie-val">100%</span></div>' +
                        '<input type="range" id="ie_saturation" class="ie-slider" min="0" max="200" value="100">' +
                    '</div>' +
                    '<div class="ie-control-row">' +
                        '<div class="ie-label-row"><label class="ie-label">' + (L.ie_gamma || 'Gamma') + '</label><span id="ie_ga_val" class="ie-val">1.0</span></div>' +
                        '<input type="range" id="ie_gamma" class="ie-slider" min="0.1" max="3.0" step="0.1" value="1.0">' +
                    '</div>' +
                    '<div class="ie-control-row" style="margin-bottom: 0;">' +
                        '<div class="ie-label-row"><label class="ie-label" style="color:var(--accent-primary);">' + (L.ie_hdr || 'Virtual HDR') + '</label><span id="ie_hdr_val" class="ie-val">0</span></div>' +
                        '<input type="range" id="ie_hdr" class="ie-slider" min="0" max="100" value="0">' +
                    '</div>' +
                '</div>' +

                // Geometry & Transform Group
                '<div>' +
                    '<span class="ie-group-lbl">' + (L.ie_transform_crop || 'Transform & Crop') + '</span>' +
                    '<div class="ie-control-row">' +
                        '<div class="ie-label-row"><label class="ie-label">' + (L.ie_rotate || 'Rotate') + '</label><span id="ie_r_val" class="ie-val">0&deg;</span></div>' +
                        '<input type="range" id="ie_rotate" class="ie-slider" min="-180" max="180" value="0">' +
                    '</div>' +
                    '<div class="ie-control-row" style="display:flex; gap:8px; margin-bottom: 0;">' +
                        '<button id="ie_btn_crop" class="ie-btn" style="flex:1;"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6.13 1L6 16a2 2 0 0 0 2 2h15"></path><path d="M1 6.13L16 6a2 2 0 0 1 2 2v15"></path></svg> ' + (L.ie_draw_crop || 'Draw Crop') + '</button>' +
                        '<button id="ie_btn_crop_clear" class="ie-btn ie-btn-danger" style="display:none; flex:1;">' + (L.clear || 'Clear') + '</button>' +
                    '</div>' +
                '</div>' +

                // Export Settings Group
                '<div>' +
                    '<span class="ie-group-lbl">' + (L.ie_export_settings || 'Export Settings') + '</span>' +
                    '<div class="ie-control-row" style="display:flex; gap:10px;">' +
                        '<div style="flex:1"><label class="ie-label">' + (L.ie_format || 'Format') + '</label><select id="ie_format" class="ie-input" style="margin-top:4px;"><option value="jpg">JPG</option><option value="png">PNG</option><option value="webp">WEBP</option><option value="gif">GIF</option><option value="bmp">BMP</option><option value="ico">ICO</option><option value="tiff">TIFF</option></select></div>' +
                        '<div style="flex:1" id="ie_dynamic_opt_wrap">' +
                            '<div class="ie-label-row"><label class="ie-label">' + (L.ie_quality || 'Quality') + '</label><span id="ie_opt_val" class="ie-val">85%</span></div>' +
                            '<input type="range" id="ie_opt_input" class="ie-slider" min="10" max="100" value="85" style="margin-top:10px;">' +
                        '</div>' +
                    '</div>' +
                    '<div class="ie-control-row" style="display:flex; gap:10px; margin-bottom:0;">' +
                        '<div style="flex:1"><label class="ie-label">' + (L.ie_resize_w || 'Resize W') + '</label><input type="number" id="ie_width" class="ie-input" style="margin-top:4px;" placeholder="' + (L.ie_auto || 'Auto') + '"></div>' +
                        '<div style="flex:1"><label class="ie-label">' + (L.ie_resize_h || 'Resize H') + '</label><input type="number" id="ie_height" class="ie-input" style="margin-top:4px;" placeholder="' + (L.ie_auto || 'Auto') + '"></div>' +
                    '</div>' +
                '</div>' +

                // Action Buttons
                '<div style="margin-top:auto; display:flex; gap:8px; padding-top: 15px; border-top: 1px solid #333;">' +
                    '<button id="ie_reset" class="ie-btn" style="flex:1;" title="' + (L.ie_reset || 'Reset') + '"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg></button>' +
                    '<button id="ie_save" class="ie-btn ie-btn-primary" style="flex:2;"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg> ' + (L.ie_save_copy || 'Save Copy') + '</button>' +
                '</div>' +
            '</div>' +
        '</div>';

    const canvas = document.getElementById('ie_canvas');
    const ctx = canvas.getContext('2d');
    const wrap = document.getElementById('ie_preview_wrap');
    const cropbox = document.getElementById('ie_cropbox');
    const img = new Image();
    
    let cropActive = false;
    let isDrawingCrop = false;
    let cropBoxStart = { x: 0, y: 0 };
    let cropData = { xPct: 0, yPct: 0, wPct: 0, hPct: 0 };

    const view = { scale: 1, x: 0, y: 0, isPanning: false, panStart: { x: 0, y: 0 }, pinchDist: 0 };

    const applyViewportTransform = () => {
        canvas.style.transform = `translate(-50%, -50%) translate(${view.x}px, ${view.y}px) scale(${view.scale})`;
        wrap.style.cursor = cropActive ? 'crosshair' : (view.isPanning ? 'grabbing' : (view.scale > 1.05 ? 'grab' : 'default'));
    };

    const resetViewportToFit = () => {
        if (!canvas.width || !canvas.height) return;
        const rect = wrap.getBoundingClientRect();
        const padding = 40;
        view.scale = Math.min(Math.max(50, rect.width - padding) / canvas.width, Math.max(50, rect.height - padding) / canvas.height);
        view.x = 0;
        view.y = 0;
        applyViewportTransform();
    };

    const updateCanvas = (keepFit = false) => {
        const rot = parseInt(document.getElementById('ie_rotate').value) || 0;
        const rad = rot * Math.PI / 180;
        const sin = Math.abs(Math.sin(rad));
        const cos = Math.abs(Math.cos(rad));
        
        const newW = img.naturalWidth * cos + img.naturalHeight * sin;
        const newH = img.naturalWidth * sin + img.naturalHeight * cos;
        
        if (canvas.width > 0 && canvas.height > 0 && !keepFit) {
            view.x -= ((newW - canvas.width) / 2) * view.scale;
            view.y -= ((newH - canvas.height) / 2) * view.scale;
        }
        
        canvas.width = newW;
        canvas.height = newH;
        
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        
        ctx.save();
        ctx.translate(canvas.width / 2, canvas.height / 2);
        ctx.rotate(rad);
        ctx.drawImage(img, -img.naturalWidth / 2, -img.naturalHeight / 2);
        ctx.restore();
        
        const br = parseInt(document.getElementById('ie_brightness').value) || 0;
        const ct = parseInt(document.getElementById('ie_contrast').value) || 0;
        const sa = parseInt(document.getElementById('ie_saturation').value) || 100;
        const ga = parseFloat(document.getElementById('ie_gamma').value) || 1.0;
        const hdr = parseInt(document.getElementById('ie_hdr').value) || 0;
        
        const exp = 1 / ga;
        document.getElementById('ie_gamma_r').setAttribute('exponent', exp);
        document.getElementById('ie_gamma_g').setAttribute('exponent', exp);
        document.getElementById('ie_gamma_b').setAttribute('exponent', exp);
        
        // Fast, hardware-accelerated HDR visualization (clean contrast/saturation boost without hazy brightness drops)
        const hdrCt = hdr * 0.30; 
        const hdrSa = hdr * 0.35; 
        
        canvas.style.filter = `brightness(${100 + br}%) contrast(${100 + ct + hdrCt}%) saturate(${sa + hdrSa}%) url(#ie_gamma_filter)`;
        
        cropActive = false;
        cropbox.style.display = 'none';
        cropData = { xPct: 0, yPct: 0, wPct: 0, hPct: 0 };
        document.getElementById('ie_btn_crop_clear').style.display = 'none';

        if (keepFit) resetViewportToFit();
        else applyViewportTransform();
    };

    const liveUpdate = (id, spanId, suffix = '') => {
        document.getElementById(id).oninput = (e) => {
            document.getElementById(spanId).innerHTML = e.target.value + suffix;
            updateCanvas(false);
        };
    };
    liveUpdate('ie_rotate', 'ie_r_val', '&deg;');
    liveUpdate('ie_brightness', 'ie_br_val');
    liveUpdate('ie_contrast', 'ie_ct_val');
    liveUpdate('ie_saturation', 'ie_sa_val', '%');
    liveUpdate('ie_gamma', 'ie_ga_val');
    liveUpdate('ie_hdr', 'ie_hdr_val');

    const updateFormatOptions = () => {
        const format = document.getElementById('ie_format').value;
        const wrap = document.getElementById('ie_dynamic_opt_wrap');
        if (format === 'jpg' || format === 'webp') {
            wrap.innerHTML = '<div class="ie-label-row"><label class="ie-label">' + (L.ie_quality || 'Quality') + '</label><span id="ie_opt_val" class="ie-val">85%</span></div><input type="range" id="ie_opt_input" class="ie-slider" min="10" max="100" value="85" style="margin-top:10px;">';
            document.getElementById('ie_opt_input').oninput = (e) => document.getElementById('ie_opt_val').innerHTML = e.target.value + '%';
        } else if (format === 'png') {
            wrap.innerHTML = '<div class="ie-label-row"><label class="ie-label">' + (L.ie_compression || 'Compression') + '</label><span id="ie_opt_val" class="ie-val">6</span></div><input type="range" id="ie_opt_input" class="ie-slider" min="0" max="9" value="6" style="margin-top:10px;">';
            document.getElementById('ie_opt_input').oninput = (e) => document.getElementById('ie_opt_val').innerHTML = e.target.value;
        } else if (format === 'tiff' || format === 'tif') {
            wrap.innerHTML = '<label class="ie-label">' + (L.ie_compression || 'Compression') + '</label><select id="ie_opt_input" class="ie-input" style="margin-top:4px;"><option value="LZW">LZW</option><option value="ZIP">ZIP</option><option value="JPEG">JPEG</option><option value="None">None</option></select>';
        } else if (format === 'ico') {
            wrap.innerHTML = '<label class="ie-label">' + (L.ie_color_depth || 'Color Depth') + '</label><select id="ie_opt_input" class="ie-input" style="margin-top:4px;"><option value="32">32-bit (Alpha)</option><option value="24">24-bit (True Color)</option><option value="8">256 Colors</option></select>';
        } else {
            wrap.innerHTML = '';
        }
    };
    document.getElementById('ie_format').onchange = updateFormatOptions;

    document.getElementById('ie_btn_crop').onclick = () => {
        cropActive = true;
        cropbox.style.display = 'block';
        cropbox.style.width = '0px';
        cropbox.style.height = '0px';
        document.getElementById('ie_btn_crop_clear').style.display = 'flex';
        applyViewportTransform();
    };

    document.getElementById('ie_btn_crop_clear').onclick = () => {
        cropActive = false;
        cropbox.style.display = 'none';
        cropData = { xPct: 0, yPct: 0, wPct: 0, hPct: 0 };
        document.getElementById('ie_btn_crop_clear').style.display = 'none';
        applyViewportTransform();
    };
    
    document.getElementById('ie_reset').onclick = () => {
        const resetVal = (id, val, textId, suffix = '') => {
            const el = document.getElementById(id);
            if (el) el.value = val;
            if (textId) {
                const tel = document.getElementById(textId);
                if (tel) tel.innerHTML = val + suffix;
            }
        };
        resetVal('ie_rotate', 0, 'ie_r_val', '&deg;');
        resetVal('ie_brightness', 0, 'ie_br_val');
        resetVal('ie_contrast', 0, 'ie_ct_val');
        resetVal('ie_saturation', 100, 'ie_sa_val', '%');
        resetVal('ie_gamma', 1.0, 'ie_ga_val');
        resetVal('ie_hdr', 0, 'ie_hdr_val');
        
        document.getElementById('ie_format').value = 'jpg';
        updateFormatOptions(); 
        
        document.getElementById('ie_width').value = '';
        document.getElementById('ie_height').value = '';
        
        cropActive = false;
        cropbox.style.display = 'none';
        cropData = { xPct: 0, yPct: 0, wPct: 0, hPct: 0 };
        document.getElementById('ie_btn_crop_clear').style.display = 'none';
        
        updateCanvas(true);
    };

    wrap.addEventListener('wheel', (e) => {
        e.preventDefault();
        const rect = wrap.getBoundingClientRect();
        const mouseX = e.clientX - rect.left - rect.width / 2;
        const mouseY = e.clientY - rect.top - rect.height / 2;

        const zoomFactor = e.deltaY < 0 ? 1.15 : 1 / 1.15;
        const newScale = Math.max(0.05, Math.min(25, view.scale * zoomFactor));

        view.x -= (mouseX - view.x) * (newScale / view.scale - 1);
        view.y -= (mouseY - view.y) * (newScale / view.scale - 1);
        view.scale = newScale;

        applyViewportTransform();
    }, { passive: false });

    wrap.addEventListener('mousedown', (e) => {
        const rect = wrap.getBoundingClientRect();
        const clientX = e.clientX - rect.left;
        const clientY = e.clientY - rect.top;

        if (cropActive) {
            isDrawingCrop = true;
            cropBoxStart.x = clientX;
            cropBoxStart.y = clientY;
            cropbox.style.left = clientX + 'px';
            cropbox.style.top = clientY + 'px';
            cropbox.style.width = '0px';
            cropbox.style.height = '0px';
            cropbox.style.display = 'block';
        } else {
            view.isPanning = true;
            view.panStart.x = e.clientX - view.x;
            view.panStart.y = e.clientY - view.y;
            applyViewportTransform();
        }
    });

    window.addEventListener('mousemove', (e) => {
        const rect = wrap.getBoundingClientRect();
        if (isDrawingCrop) {
            const curX = Math.max(0, Math.min(rect.width, e.clientX - rect.left));
            const curY = Math.max(0, Math.min(rect.height, e.clientY - rect.top));
            
            cropbox.style.left = Math.min(cropBoxStart.x, curX) + 'px';
            cropbox.style.top = Math.min(cropBoxStart.y, curY) + 'px';
            cropbox.style.width = Math.abs(curX - cropBoxStart.x) + 'px';
            cropbox.style.height = Math.abs(curY - cropBoxStart.y) + 'px';
        } else if (view.isPanning) {
            view.x = e.clientX - view.panStart.x;
            view.y = e.clientY - view.panStart.y;
            applyViewportTransform();
        }
    });

    window.addEventListener('mouseup', () => {
        if (isDrawingCrop) {
            isDrawingCrop = false;
            const boxL = parseFloat(cropbox.style.left);
            const boxT = parseFloat(cropbox.style.top);
            const boxW = parseFloat(cropbox.style.width);
            const boxH = parseFloat(cropbox.style.height);

            const cRect = canvas.getBoundingClientRect();
            const wrapRect = wrap.getBoundingClientRect();
            
            const scaleX = canvas.width / cRect.width;
            const scaleY = canvas.height / cRect.height;
            
            const canvasX = (boxL - (cRect.left - wrapRect.left)) * scaleX;
            const canvasY = (boxT - (cRect.top - wrapRect.top)) * scaleY;
            const canvasW = boxW * scaleX;
            const canvasH = boxH * scaleY;

            const clampedX = Math.max(0, Math.min(canvas.width, canvasX));
            const clampedY = Math.max(0, Math.min(canvas.height, canvasY));
            const clampedW = Math.max(0, Math.min(canvas.width - clampedX, canvasW));
            const clampedH = Math.max(0, Math.min(canvas.height - clampedY, canvasH));

            if (clampedW > 5 && clampedH > 5) {
                cropData.xPct = clampedX / canvas.width;
                cropData.yPct = clampedY / canvas.height;
                cropData.wPct = clampedW / canvas.width;
                cropData.hPct = clampedH / canvas.height;
            }
        }
        if (view.isPanning) {
            view.isPanning = false;
            applyViewportTransform();
        }
    });

    wrap.addEventListener('touchstart', (e) => {
        if (e.touches.length === 2) {
            e.preventDefault();
            view.pinchDist = Math.hypot(e.touches[0].clientX - e.touches[1].clientX, e.touches[0].clientY - e.touches[1].clientY);
        } else if (e.touches.length === 1 && !cropActive) {
            view.isPanning = true;
            view.panStart.x = e.touches[0].clientX - view.x;
            view.panStart.y = e.touches[0].clientY - view.y;
        }
    }, { passive: false });

    wrap.addEventListener('touchmove', (e) => {
        if (e.touches.length === 2 && view.pinchDist > 0) {
            e.preventDefault();
            const currentDist = Math.hypot(e.touches[0].clientX - e.touches[1].clientX, e.touches[0].clientY - e.touches[1].clientY);
            const factor = currentDist / view.pinchDist;
            
            const rect = wrap.getBoundingClientRect();
            const midX = (e.touches[0].clientX + e.touches[1].clientX) / 2 - rect.left - rect.width/2;
            const midY = (e.touches[0].clientY + e.touches[1].clientY) / 2 - rect.top - rect.height/2;

            const newScale = Math.max(0.05, Math.min(25, view.scale * factor));
            view.x -= (midX - view.x) * (newScale / view.scale - 1);
            view.y -= (midY - view.y) * (newScale / view.scale - 1);
            view.scale = newScale;
            view.pinchDist = currentDist;
            applyViewportTransform();
        } else if (e.touches.length === 1 && view.isPanning) {
            e.preventDefault();
            view.x = e.touches[0].clientX - view.panStart.x;
            view.y = e.touches[0].clientY - view.panStart.y;
            applyViewportTransform();
        }
    }, { passive: false });

    wrap.addEventListener('touchend', (e) => {
        if (e.touches.length < 2) view.pinchDist = 0;
        if (e.touches.length === 0) view.isPanning = false;
    });

    document.getElementById('ie_save').onclick = async () => {
        if (isE2E) return myCloudShowAlert('Error', 'Server-side processing is disabled for encrypted files.');
        
        const saveBtn = document.getElementById('ie_save');
        saveBtn.innerHTML = '<div class="myCloud-spinner" style="width:16px;height:16px;border-width:2px;display:inline-block;vertical-align:middle;margin-inline-end:8px;border-top-color:#fff;"></div>' + (L.saving || 'Saving...');
        saveBtn.disabled = true;
        saveBtn.style.opacity = '0.8';
        
        const loader = document.getElementById('ie_loading');
        loader.style.display = 'block';
        
        const optInput = document.getElementById('ie_opt_input');
        
        const fd = new URLSearchParams({
            myCloud_action: 'image_convert',
            myCloud_key: myCloudState.key,
            myCloud_token: typeof myCloudCsrfToken !== 'undefined' ? myCloudCsrfToken : window.myCloudCsrfToken,
            src: path,
            format: document.getElementById('ie_format').value,
            format_opt: optInput ? optInput.value : '',
            rotate: document.getElementById('ie_rotate').value,
            brightness: document.getElementById('ie_brightness').value,
            contrast: document.getElementById('ie_contrast').value,
            gamma: document.getElementById('ie_gamma').value,
            saturation: document.getElementById('ie_saturation').value,
            hdr: document.getElementById('ie_hdr').value,
            cropXPct: cropData.xPct, 
            cropYPct: cropData.yPct, 
            cropWPct: cropData.wPct, 
            cropHPct: cropData.hPct,
            resizeW: document.getElementById('ie_width').value || 0,
            resizeH: document.getElementById('ie_height').value || 0
        });
        
        try {
            const res = await fetch(window.location.pathname, { method: 'POST', body: fd }).then(r => r.json());
            if (res.status === 'OK') {
                myCloudCloseModal();
                myCloudFetchDirectory(myCloudState.currentDir);
                
                // Aggressive Scroll Tracker: Snap viewport back to the file while table redrawing occurs
                let attempts = 0;
                const restoreInt = setInterval(() => {
                    savedScrolls.forEach(s => { if (s.el) s.el.scrollTop = s.top; });
                    const row = document.querySelector(`.myCloudRow[data-fullpath="${CSS.escape(path)}"]`);
                    if (row) {
                        row.scrollIntoView({ block: 'center' });
                    }
                    if (attempts++ > 10) clearInterval(restoreInt); // Complete tracking after 1 second
                }, 100);
                
                if (typeof myCloudNotify === 'function') myCloudNotify(L.success || 'Image saved successfully!');
            } else {
                myCloudShowAlert(L.error_prefix || 'Error', res.msg || 'Conversion failed.');
                saveBtn.innerHTML = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg> ' + (L.ie_save_copy || 'Save Copy');
                saveBtn.disabled = false;
                saveBtn.style.opacity = '1';
                loader.style.display = 'none';
            }
        } catch(e) {
            myCloudShowAlert(L.error_prefix || 'Error', L.network_error || 'Network Error');
            saveBtn.innerHTML = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg> ' + (L.ie_save_copy || 'Save Copy');
            saveBtn.disabled = false;
            saveBtn.style.opacity = '1';
            loader.style.display = 'none';
        }
    };

    // Load the preview image safely into memory
    const fd = new URLSearchParams({ myCloud_action: 'get_download_token', myCloud_key: myCloudState.key, myCloud_token: typeof myCloudCsrfToken !== 'undefined' ? myCloudCsrfToken : window.myCloudCsrfToken, path: path, filename: filename, preview: '1' });
    fetch(window.location.pathname, { method: 'POST', body: fd }).then(r=>r.json()).then(res => {
        if (res.status === 'OK') {
            const tokenUrl = window.location.pathname + '?myCloud_token=' + res.token;
            fetch(tokenUrl).then(r => r.blob()).then(blob => {
                img.onload = () => { 
                    document.getElementById('ie_loading').style.display = 'none'; 
                    updateCanvas(true); 
                };
                img.src = URL.createObjectURL(blob);
            }).catch(() => {
                myCloudShowAlert(L.error_prefix || 'Error', 'Failed to load preview.');
                document.getElementById('ie_loading').style.display = 'none';
            });
        } else {
            myCloudShowAlert(L.error_prefix || 'Error', res.msg || 'Failed to fetch image token.');
            document.getElementById('ie_loading').style.display = 'none';
        }
    }).catch(err => {
        myCloudShowAlert(L.error_prefix || 'Error', L.network_error || 'Network error while preparing image.');
        document.getElementById('ie_loading').style.display = 'none';
    });
};
</script>