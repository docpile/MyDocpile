<?php
/**
 * ============================================================================
 * MODULE: Media Preview 
 * ============================================================================
 * Processes images, videos, and documents to generate previews. 
 * UI component only 
 * NOTE: Executed exclusively by the client browser.
 * 
 * THIS IS PART OF THE PSEUDO "JS FILE" - SO THE NO PHP CODE IN THIS FILE!
 */ 

?><script>


// Helper for dynamic script injection
const myCloudLoadJS = (src) => new Promise((resolve, reject) => { 
    const existing = document.querySelector(`script[src="${src}"]`);
    if (existing) {
        if (existing.dataset.loaded === 'true') return resolve();
        existing.addEventListener('load', resolve);
        existing.addEventListener('error', () => reject(new Error(`Failed to load ${src}`)));
        return;
    }
    const s = document.createElement('script'); 
    s.src = src; 
    s.onload = () => { s.dataset.loaded = 'true'; resolve(); };
    s.onerror = () => reject(new Error(`Failed to load ${src}`)); 
    document.head.appendChild(s); 
});

// E2E Helper: Decrypts token URLs into ObjectURLs if the directory is locked
async function myCloudGetDecryptedUrl(path, tokenUrl) {
    if (typeof myCloudCrypto !== 'undefined' && myCloudCrypto.isDirEncrypted(path)) {
        const root = myCloudCrypto.getCryptoRoot(path);
        if (myCloudCrypto.isDirUnlocked(root)) {
            try {
                const encBlob = await fetch(tokenUrl).then(r => r.blob());
                const decBlob = await myCloudCrypto.decryptFile(root, encBlob);
                return URL.createObjectURL(decBlob);
            } catch(e) {
                console.error("E2E Decrypt failed for preview", e);
            }
        }
    }
   return tokenUrl;
}

// E2E Helper: Robust blob fetcher bypassing CSP blocks and handling in-memory decrypt
async function ceFetchPreviewBlob(url, path) {
    if (url.startsWith('blob:')) {
        try { return await fetch(url).then(r => r.blob()); } catch(e) {
            if (typeof myCloudCrypto !== 'undefined' && myCloudCrypto.isDirEncrypted(path)) {
                const root = myCloudCrypto.getCryptoRoot(path);
                if (myCloudCrypto.isDirUnlocked(root)) {
                    const fd = new URLSearchParams({ myCloud_action: 'get_download_token', myCloud_key: myCloudState.key, myCloud_token: (typeof myCloudCsrfToken !== 'undefined' ? myCloudCsrfToken : ''), path: path, filename: path.split('/').pop(), preview: '1' });
                    const tRes = await fetch('', { method: 'POST', body: fd }).then(r => r.json());
                    if (tRes.status === 'OK') return await myCloudCrypto.decryptFile(root, await fetch('?myCloud_token=' + tRes.token).then(r => r.blob()));
                }
            }
            throw e;
        }
    }
    const res = await fetch(url);
    if (!res.ok) throw new Error("HTTP " + res.status);
    const blob = await res.blob();
    if (typeof myCloudCrypto !== 'undefined' && myCloudCrypto.isDirEncrypted(path)) {
        const root = myCloudCrypto.getCryptoRoot(path);
        if (myCloudCrypto.isDirUnlocked(root)) return await myCloudCrypto.decryptFile(root, blob);
    }
    return blob;
}

// Initializes and opens the preview overlay for various file types.
// Manages image, video, audio, PDF, text, and office document rendering.
function myCloudOpenPreview(url, filename, path) {
    let overlay = document.getElementById('myCloudPreviewOverlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'myCloudPreviewOverlay';
        overlay.className = 'myCloudOverlay';
        document.body.appendChild(overlay);
        
        let startX = 0, startY = 0;
        const toggleUI = () => overlay.classList.toggle('ui-hidden');
        const showUI = () => overlay.classList.remove('ui-hidden');

        const isControl = (target) => target.closest('.myCloud-zoom-btn, .myCloud-floating-toggle, .myCloud-floating-info, .myCloud-floating-share, .myCloud-floating-download, .myCloud-floating-close, .myCloud-prev-nav, .myCloud-next-nav, .myCloud-filmstrip, .myCloud-exif-modal');

        let lastToggleTime = 0;

        overlay.addEventListener('mousedown', (e) => { startX = e.clientX; startY = e.clientY; });
        overlay.addEventListener('mouseup', (e) => {
            if (Date.now() - lastToggleTime < 300) return;
            if (!document.getElementById('myCloudPreviewImg')) return;
            if (isControl(e.target)) return;
            if (Math.hypot(e.clientX - startX, e.clientY - startY) < 10) {
                toggleUI();
                lastToggleTime = Date.now();
            }
        });

        overlay.addEventListener('touchstart', (e) => {
            if (e.touches.length === 1) { startX = e.touches[0].clientX; startY = e.touches[0].clientY; }
        }, {passive: true});
        
        overlay.addEventListener('touchend', (e) => {
            if (Date.now() - lastToggleTime < 300) return;
            if (!document.getElementById('myCloudPreviewImg')) return;
            if (isControl(e.target)) return;
            if (window.myCloudTransform && window.myCloudTransform.pinching) return;
            
            if (e.changedTouches.length === 1) {
                if (Math.hypot(e.changedTouches[0].clientX - startX, e.changedTouches[0].clientY - startY) < 10) {
                    toggleUI();
                    lastToggleTime = Date.now();
                }
            }
        }, {passive: true});

        let lastMoveX = -1, lastMoveY = -1;
        overlay.addEventListener('mousemove', (e) => {
            if (overlay.classList.contains('ui-hidden')) {
                if (lastMoveX !== -1 && Math.hypot(e.clientX - lastMoveX, e.clientY - lastMoveY) > 10) showUI();
                lastMoveX = e.clientX; lastMoveY = e.clientY;
            }
        });
    }
    
    overlay.classList.remove('ui-hidden');

    const curLang = (typeof myCloudState !== 'undefined' && myCloudState.settings) ? myCloudState.settings.language : 'en';
    const isRtl = ['ar', 'fa', 'he', 'ur'].includes(curLang);
    overlay.setAttribute('dir', isRtl ? 'rtl' : 'ltr');
    
    myCloudTransform = { scale: 1, translateX: 0, translateY: 0, panning: false, pinching: false, dismissing: false, pointX: 0, pointY: 0, startX: 0, startY: 0, rotate: 0, flipH: 1, flipV: 1, startClientY: 0 };
    
    const st = myCloudState;
    const searchModalOpen = document.getElementById('myCloudModal') && document.getElementById('myCloudModal').classList.contains('search-modal') && document.getElementById('myCloudModalOverlay').style.display !== 'none';
    const allItems = searchModalOpen ? (window.myCloudSearchItems || []) : myCloudGetSortedItems();
    const currentIndex = allItems.findIndex(i => i.name === st.previewPath);
    
    const getDecExt = (item) => {
        let name = item.displayName || item.name.split('/').pop();
        return name.replace(/\.enc$/, '').split('.').pop().toLowerCase();
    };
    
    let showPrev = false, showNext = false;

    if (currentIndex !== -1) {
        for (let i = currentIndex - 1; i >= 0; i--) {
            const extCheck = getDecExt(allItems[i]);
            if (allItems[i].size !== 'DIR' && myCloudConfig.navigable.includes(extCheck)) { showPrev = true; break; }
        }
        for (let i = currentIndex + 1; i < allItems.length; i++) {
            const extCheck = getDecExt(allItems[i]);
            if (allItems[i].size !== 'DIR' && myCloudConfig.navigable.includes(extCheck)) { showNext = true; break; }
        }
    }

    let cleanFilename = filename;
    if (cleanFilename.endsWith('.enc')) {
        cleanFilename = (st.pathNames && st.pathNames[path]) ? st.pathNames[path] : cleanFilename.replace(/\.enc$/, '');
    }
    filename = cleanFilename;

    const ext = cleanFilename.split('.').pop().toLowerCase();
    const isImage = typeof imageExts !== 'undefined' && imageExts.includes(ext);
    const isVideo = typeof videoExts !== 'undefined' && videoExts.includes(ext);
    const isAudio = typeof audioExts !== 'undefined' && audioExts.includes(ext);
    const isEpub = ext === 'epub';
    const isFont = ['ttf', 'woff', 'woff2', 'otf'].includes(ext);
    const isMap = ['kml', 'kmz'].includes(ext);
    const iconHD = myCloudSvg.hdIcon;
    const navStyle = isVideo ? 'bottom: 90px;' : '';

    let modalInnerHtml = '';
    
    if (isImage) {
        modalInnerHtml += 
        '<div class="myCloud-floating-toggle" onclick="myCloudToggleQuality(this, \'' + path.replace(/'/g, "\\'") + '\')" title="' + (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.switch_hd ? myCloud_LANG.switch_hd : 'Toggle HD') + '" data-quality="preview">' +
        iconHD +
        '</div>';
    }
    
    modalInnerHtml += 
    '<div id="myCloudShareBtn" class="myCloud-floating-share" style="display:none;" onclick="myCloudNativeShare()" title="' + (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.share_file ? myCloud_LANG.share_file : 'Share') + '">' +
        '<svg viewBox="0 0 24 24"><path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92 1.61 0 2.92-1.31 2.92-2.92s-1.31-2.92-2.92-2.92z"/></svg>' +
    '</div>' +

    '<div class="myCloud-floating-download" onclick="myCloudDownloadFromPreview()" title="' + (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.download ? myCloud_LANG.download : 'Download') + '">' +
        '<svg viewBox="0 0 24 24"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>' +
    '</div>' +
    '<div class="myCloud-floating-close" onclick="myCloudClosePreview()" title="' + (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.close ? myCloud_LANG.close : 'Close') + '">✕</div>' +
    
    '<div class="myCloudModalBody" id="myCloudImageContainer" style="overflow:hidden; position:relative; display:flex; align-items:center; justify-content:center;"></div>';
    
    if (isImage) {
        modalInnerHtml += 
        '<div id="myCloudPreviewSubmenu" class="myCloud-zoom-container" style="display:none; bottom: 80px; transition: bottom 0.2s ease-out;">' +
            '<button class="myCloud-zoom-btn" style="display:flex; align-items:center; justify-content:center;" onclick="myCloudRotate(-90)" title="Rotate Left"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg></button>' +
            '<button class="myCloud-zoom-btn" style="display:flex; align-items:center; justify-content:center;" onclick="myCloudRotate(90)" title="Rotate Right"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/></svg></button>' +
            '<div style="width:1px; height:24px; background:rgba(255,255,255,0.3); margin:0 2px;"></div>' +
            '<button class="myCloud-zoom-btn" style="display:flex; align-items:center; justify-content:center;" onclick="myCloudFlip(\'h\')" title="Flip Horizontal"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3L4 7l4 4"/><path d="M16 13l4 4-4 4"/><line x1="4" y1="7" x2="20" y2="7"/><line x1="20" y1="17" x2="4" y2="17"/></svg></button>' +
            '<button class="myCloud-zoom-btn" style="display:flex; align-items:center; justify-content:center;" onclick="myCloudFlip(\'v\')" title="Flip Vertical"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8l4-4 4 4"/><path d="M13 16l4 4 4-4"/><line x1="7" y1="4" x2="7" y2="20"/><line x1="17" y1="20" x2="17" y2="4"/></svg></button>' +
        '</div>' +
        '<div class="myCloud-zoom-container">' +
            '<button class="myCloud-zoom-btn" style="display:flex; align-items:center; justify-content:center;" onclick="myCloudZoomStep(-0.2)"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/></svg></button>' +
            '<button class="myCloud-zoom-btn" style="display:flex; align-items:center; justify-content:center;" onclick="myCloudResetZoom()" title="Reset"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6"/><path d="M9 21H3v-6"/><path d="M21 3l-7 7"/><path d="M3 21l7-7"/></svg></button>' +
            '<button class="myCloud-zoom-btn" style="display:flex; align-items:center; justify-content:center;" onclick="myCloudZoomStep(0.2)"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg></button>' +
            '<div style="width:1px; height:24px; background:rgba(255,255,255,0.3); margin:0 2px;"></div>' +
            '<button class="myCloud-zoom-btn" onclick="var sub = document.getElementById(\'myCloudPreviewSubmenu\'); sub.style.display = sub.style.display === \'none\' ? \'flex\' : \'none\'; sub.style.bottom = document.getElementById(\'myCloudFilmstrip\') ? \'170px\' : \'80px\';" title="More Tools" style="display:flex; align-items:center; justify-content:center;"><svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M6 10c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm12 0c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm-6 0c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/></svg></button>' +
            '<div style="width:1px; height:24px; background:rgba(255,255,255,0.3); margin:0 2px;"></div>' +
            '<button class="myCloud-zoom-btn" onclick="myCloudToggleFilmstrip()" title="' + (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.filmstrip ? myCloud_LANG.filmstrip : 'Filmstrip') + '">🎞️</button>' +
            '<button class="myCloud-zoom-btn" onclick="myCloudShowExif()" title="' + (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.metadata ? myCloud_LANG.metadata : 'EXIF Data') + '" style="display:flex; align-items:center; justify-content:center;"><svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M11 7h2v2h-2zm0 4h2v6h-2zm1-9C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/></svg></button>' +
        '</div>';
    }

    modalInnerHtml += 
    '<div class="myCloud-nav-container" style="' + navStyle + '">' +
         '<div class="myCloud-prev-nav" style="visibility: ' + (showPrev ? 'visible' : 'hidden') + '" onclick="myCloudNavigatePreview(-1)">&#10094;</div>' +
         '<div class="myCloud-prev-nav" style="visibility: ' + (showNext ? 'visible' : 'hidden') + '" onclick="myCloudNavigatePreview(1)">&#10095;</div>' +
    '</div>';

    let modal = overlay.querySelector('.myCloudModal.preview');
    if (modal) {
        modal.innerHTML = modalInnerHtml;
    } else {
        modal = document.createElement('div');
        modal.className = 'myCloudModal preview';
        modal.innerHTML = modalInnerHtml;
        overlay.insertBefore(modal, overlay.firstChild);
    }
    
    const strip = document.getElementById('myCloudFilmstrip');
    const exif = document.getElementById('myCloudExifModal');
    
    if (isImage) {
        if (strip) strip.style.display = '';
        setTimeout(myCloudCheckShareSupport, 0);
        setTimeout(myCloudInitFilmstrip, 50);
    } else {
        if (strip) strip.style.display = 'none';
        if (exif) exif.style.display = 'none';
    }
    
    const body = overlay.querySelector('#myCloudImageContainer');
    body.className = 'myCloudModalBody'; 

    // --- 1. IMAGE HANDLER ---
    if (isImage) {
        body.innerHTML = 
        '<div id="myCloudLoadingSpinner" class="myCloud-spinner" ' +
             'style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); z-index:10;">' +
        '</div>' +
        '<img id="myCloudPreviewImg" draggable="false" ' +
             'style="cursor: grab; user-select: none; -webkit-user-drag: none; ' +
                    'max-width: 100%; max-height: 100%; object-fit: contain;">';
        
        const img = document.getElementById('myCloudPreviewImg');
        const spinner = document.getElementById('myCloudLoadingSpinner');
        
        ceFetchPreviewBlob(url, path).then(blob => {
            img.src = URL.createObjectURL(blob);
        }).catch(err => {
            if (spinner) spinner.style.display = 'none';
            body.innerHTML = '<div style="color:#fff; padding:40px;">Error loading image: ' + err.message + '</div>';
        });

        img.onload = () => { 
            if (spinner) spinner.style.display = 'none';
            img.style.opacity = '1';

            if (img.dataset.keepZoom === 'true') {
                if (img.dataset.fixScaleRatio) {
                    const ratio = parseFloat(img.dataset.fixScaleRatio);
                    if (window.myCloudTransform && !isNaN(ratio)) {
                        window.myCloudTransform.scale *= ratio;
                    }
                }
                delete img.dataset.fixScaleRatio;
                delete img.dataset.keepZoom;
                myCloudUpdateImageTransform(false); 
            } else {
                myCloudResetZoom(); 
            }
        };
        
        img.onerror = () => {
            if (spinner) spinner.style.display = 'none';
            body.innerHTML = '<div style="color:#fff; padding:40px;">Error loading image</div>';
        };

        img.addEventListener('wheel', myCloudOnWheel, {passive: false});
        img.addEventListener('mousedown', myCloudOnMouseDown);
        document.addEventListener('mousemove', myCloudOnMouseMove);
        document.addEventListener('mouseup', myCloudOnMouseUp);

        img.addEventListener('touchstart', myCloudOnTouchStart, {passive: false});
        img.addEventListener('touchmove', myCloudOnTouchMove, {passive: false});
        img.addEventListener('touchend', myCloudOnTouchEnd, {passive: false});
        img.addEventListener('touchcancel', myCloudOnTouchEnd, {passive: false});
    }
    
    // --- 2. VIDEO HANDLER ---
    else if (isVideo) {
        body.style.background = '#000';
        const safeExt = ext.replace(/[<>&"']/g, '');
        body.innerHTML = 
        '<video controls autoplay style="max-width:100%; max-height:100%; outline:none;" name="media">' +
            '<source src="' + url + '" type="video/' + (ext === 'mov' ? 'mp4' : safeExt) + '">' +
             '<div style="color:#fff; padding:20px; text-align:center;">' +
                 (typeof myCloud_LANG !== 'undefined' ? myCloud_LANG.video_not_supported : 'Video not supported') + ' .' + safeExt + '.<br>' +
                 '<a href="' + url + '" style="color:#4da3ff;">' + (typeof myCloud_LANG !== 'undefined' ? myCloud_LANG.download_file : 'Download File') + '</a>' +
             '</div>' +
        '</video>';
    }

    // --- 3. AUDIO HANDLER ---
    else if (isAudio) {
        body.style.background = '#222';
        body.style.display = 'flex';
        body.style.flexDirection = 'column';
        body.style.alignItems = 'center';
        body.style.justifyContent = 'center';
        
        const safeFilename = filename.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");

        body.innerHTML = 
        '<div style="text-align:center; width:100%; max-width: 600px; display:flex; flex-direction:column; align-items:center;">' +
            '<div style="margin-bottom:30px; opacity:0.8;">' +
                '<svg viewBox="0 0 24 24" width="80" height="80" fill="#fff">' +
                    '<path d="M12 3v9.28c-.47-.17-.97-.28-1.5-.28C8.01 12 6 14.01 6 16.5S8.01 21 10.5 21c2.31 0 4.16-1.75 4.45-4H15V6h4V3h-7z"/>' +
                '</svg>' +
            '</div>' +
            '<div style="color:#fff; margin-bottom:25px; font-size:18px; font-weight:500; padding:0 15px; word-break:break-word;">' +
                safeFilename +
            '</div>' +
            '<audio controls autoplay style="width: 90%; max-width: 500px; height: 54px; outline: none; display: block;">' +
                '<source src="' + url + '" type="audio/' + (ext === 'm4a' ? 'mp4' : ext) + '">' +
                (typeof myCloud_LANG !== 'undefined' ? myCloud_LANG.audio_not_supported : 'Audio not supported') +
            '</audio>' +
        '</div>';
    }

    // --- 4. PDF HANDLER (LAZY LOAD + HYBRID) ---
    else if (ext === 'pdf') {
        overlay.style.display = 'flex';
//        const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
//		const isMobile = true;
		const isMobile = (myCloudDevice.category === 'mobile' || myCloudDevice.category === 'tablet');
        if (!isMobile && !url.startsWith('blob:')) {
            const oldToolbar = overlay.querySelector('.myCloud-pdf-toolbar');
            if (oldToolbar) oldToolbar.remove();

            body.style.background = '#323639';
            body.style.display = 'flex';
            body.style.flexDirection = 'column';
            body.style.overflow = 'hidden';
            body.style.padding = '0';

            const navContainer = overlay.querySelector('.myCloud-nav-container');
            if (navContainer) {
                navContainer.style.pointerEvents = 'none';
                navContainer.querySelectorAll('div').forEach(d => d.style.pointerEvents = 'auto');
            }

            const safeUrl = url + '#view=FitH&toolbar=1&navpanes=0';
            
            body.innerHTML = 
                '<iframe src="' + safeUrl + '" width="100%" height="100%" style="border:none; display:block;">' +
                    '<div style="text-align:center; padding-top:20%; color:#ccc;">' +
                        (typeof myCloud_LANG !== 'undefined' ? myCloud_LANG.preview_not_sup : 'Preview not supported.') + '<br><br>' +
                        '<a href="' + url + '" style="color:#4da3ff; text-decoration:underline;">Download PDF</a>' +
                    '</div>' +
                '</iframe>';
        } 
        else {
            body.innerHTML = 
                '<div class="myCloud-loading-container" style="color:#fff; display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%;">' +
                    '<div class="myCloud-spinner"></div>' +
                    '<div style="margin-top:15px;">Loading PDF Engine...</div>' +
                '</div>';

            const initPdf = async () => {
                let lib = window.pdfjsLib || window['pdfjs-dist/build/pdf'];
                
                if (!lib) {
                    try { await myCloudLoadJS('/script/pdf.js'); } catch(e) {}
                    lib = window.pdfjsLib || window['pdfjs-dist/build/pdf'];
                }
                
                if (!lib) {
                    console.warn("Local PDF.js failed or missing global export. Using CDN fallback.");
                    await myCloudLoadJS('https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js');
                    lib = window.pdfjsLib;
                    if (lib) lib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js';
                }
                
                if (!lib) throw new Error("PDF Engine failed to initialize from both local and CDN sources.");
                
                startMobileViewer(lib);
            };
            
            initPdf().catch(err => {
                console.error(err);
                body.innerHTML = '<div style="color:#fff;padding:20px;text-align:center;">Could not start PDF Engine.<br><small style="color:#ff6b6b;">' + (err.message || err) + '</small><br><br><a href="' + url + '" style="color:#4da3ff;text-decoration:underline;">Download PDF</a></div>';
            });

            function startMobileViewer(lib) {
                if (!lib.GlobalWorkerOptions.workerSrc) {
                    lib.GlobalWorkerOptions.workerSrc = '/script/pdf.worker.js';
                }

                const iconBook = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm16-4H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H8V4h12v12z"/></svg>';
                const iconClose = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>';

                const pdfStyle = 
                '<style>' +
                    '.myCloud-pdf-mobile-wrapper { width:100%; height:100%; position:relative; background:#202020; overflow:hidden; }' +
                    '.myCloud-pdf-scroller { width:100%; height:100%; overflow:auto; box-sizing: border-box; -webkit-overflow-scrolling:touch; display:block; }' +
                    '#myCloudPdfScrollerInner { min-width: 100%; width: min-content; padding: 10px; padding-bottom: 90px; box-sizing: border-box; transform-origin: 0 0; will-change: transform; }' +
                    '.pdf-page-container { margin: 0 auto 15px auto; position: relative; background: #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.5); min-height: 200px; }' +
                    '.pdf-page-canvas { display: block; width: 100%; height: 100%; }' +
                    '.myCloud-pdf-floating-toolbar { position: absolute; bottom: 30px; left: 50%; transform: translateX(-50%); display: flex; align-items: center; gap: 10px; background: rgba(35, 35, 35, 0.9); backdrop-filter: blur(10px); padding: 8px 16px; border-radius: 50px; border: 1px solid rgba(255,255,255,0.15); box-shadow: 0 8px 32px rgba(0,0,0,0.4); z-index: 100; white-space: nowrap; }' +
                    '.myCloud-pdf-btn { background: transparent; border: 1px solid rgba(255,255,255,0.2); color: #fff; width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0; font-size: 18px; display: flex; align-items: center; justify-content: center; cursor: pointer; padding: 0; margin:0; }' +
                    '.myCloud-pdf-btn:active { background: rgba(255,255,255,0.2); transform: scale(0.95); }' +
                    '.myCloud-pdf-btn svg { width: 18px; height: 18px; }' +
                    '.myCloud-pdf-counter { color: rgba(255,255,255,0.9); font-size: 13px; font-weight: 500; font-family: sans-serif; min-width: 60px; text-align: center; font-variant-numeric: tabular-nums; padding: 4px 8px; border-radius: 12px; transition: background 0.2s; }' +
                    '.myCloud-pdf-counter:active { background: rgba(255,255,255,0.1); }' +
                    '.myCloud-pdf-sep { width:1px; height:20px; background:rgba(255,255,255,0.2); }' +
                    '#myCloudPdfBookmarkDrawer { position: absolute; bottom: -60vh; left: 10px; right: 10px; height: 50vh; background: rgba(30, 30, 30, 0.95); backdrop-filter: blur(15px); border-radius: 16px 16px 0 0; border: 1px solid rgba(255,255,255,0.1); transition: bottom 0.3s cubic-bezier(0.1, 0.9, 0.2, 1); display: flex; flex-direction: column; z-index: 90; }' +
                    '#myCloudPdfBookmarkDrawer.active { bottom: 0; }' +
                    '.pdf-drawer-header { padding: 12px 16px; border-bottom: 1px solid rgba(255,255,255,0.1); display: flex; justify-content: space-between; align-items: center; color: #fff; font-weight: bold; }' +
                    '.pdf-drawer-content { flex: 1; overflow-y: auto; padding: 10px; }' +
                    '.pdf-bookmark-item { display: block; padding: 12px; color: #ddd; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 14px; text-decoration: none; }' +
                    '.pdf-bookmark-item:active { background: rgba(255,255,255,0.1); }' +
                    '#myCloudPdfJumpPopup { position: absolute; bottom: 85px; left: 50%; transform: translateX(-50%) scale(0.9); background: #333; padding: 10px; border-radius: 8px; display: none; box-shadow: 0 4px 20px rgba(0,0,0,0.5); opacity: 0; transition: all 0.2s; z-index: 110; align-items: center; gap: 8px; border: 1px solid #555; }' +
                    '#myCloudPdfJumpPopup.active { display: flex; transform: translateX(-50%) scale(1); opacity: 1; }' +
                    '#myCloudPdfJumpInput { width: 50px; background: #222; border: 1px solid #555; color: #fff; padding: 6px; border-radius: 4px; text-align: center; }' +
                    '.myCloud-pdf-go-btn { background: #0078d4; color: #fff; border: none; padding: 6px 12px; border-radius: 4px; font-weight: bold; }' +
                '</style>';

                body.innerHTML = pdfStyle + 
                '<div class="myCloud-pdf-mobile-wrapper">' +
                    '<div id="myCloudPdfScroller" class="myCloud-pdf-scroller">' +
                        '<div id="myCloudPdfScrollerInner" style="transition: transform 0.1s;"></div>' +
                    '</div>' +
                    
                    '<div id="myCloudPdfBookmarkDrawer">' +
                        '<div class="pdf-drawer-header">' +
                            '<span>Bookmarks</span>' +
                            '<div onclick="myCloudPdfToggleBookmarks(false)" style="padding:5px;">' + iconClose + '</div>' +
                        '</div>' +
                        '<div class="pdf-drawer-content" id="myCloudPdfBookmarkList"></div>' +
                    '</div>' +

                    '<div id="myCloudPdfJumpPopup">' +
                        '<input type="number" id="myCloudPdfJumpInput" min="1">' +
                        '<button class="myCloud-pdf-go-btn" onclick="myCloudPdfGo()">Go</button>' +
                    '</div>' +
                    
                    '<div class="myCloud-pdf-floating-toolbar">' +
                        '<button id="btnPdfBookmarks" class="myCloud-pdf-btn" onclick="myCloudPdfToggleBookmarks(true)" style="display:none;">' + iconBook + '</button>' +
                        '<div id="sepPdfBookmarks" class="myCloud-pdf-sep" style="display:none;"></div>' +
                        
                        '<button class="myCloud-pdf-btn" onclick="myCloudPdfZoom(-0.25)">−</button>' +
                        '<span id="myCloudPdfPageIndicator" class="myCloud-pdf-counter" onclick="myCloudPdfShowJump()">Loading...</span>' +
                        '<button class="myCloud-pdf-btn" onclick="myCloudPdfZoom(0.25)">+</button>' +
                    '</div>' +
                '</div>';

                const scroller = document.getElementById('myCloudPdfScroller');
                const scrollerInner = document.getElementById('myCloudPdfScrollerInner');
                const indicator = document.getElementById('myCloudPdfPageIndicator');
                const bookmarkDrawer = document.getElementById('myCloudPdfBookmarkDrawer');
                const jumpPopup = document.getElementById('myCloudPdfJumpPopup');
                const jumpInput = document.getElementById('myCloudPdfJumpInput');
                
                const state = {
                    pdf: null, zoom: 1.0, pageMap: new Map(), observer: null, currentPage: 1
                };

                const renderPageIntoContainer = (pageNum, containerDiv) => {
                    const status = state.pageMap.get(pageNum);
                    if (status === 'rendering' || status === 'done') return;
                    state.pageMap.set(pageNum, 'rendering');
                    
                    if (!state.renderTasks) state.renderTasks = {};
                    if (state.renderTasks[pageNum]) state.renderTasks[pageNum].cancel();

                    state.pdf.getPage(pageNum).then(page => {
                        const viewportBase = page.getViewport({scale: 1.0});
                        const availWidth = scroller.clientWidth - 20; 
                        const fitScale = availWidth / viewportBase.width;
                        const cssScale = fitScale * state.zoom; 
                        const cssViewport = page.getViewport({scale: cssScale});
                        
                        let outputScale = window.devicePixelRatio || 1;
                        const MAX_PIXELS = 16777216; 
                        const expectedPixels = (cssViewport.width * outputScale) * (cssViewport.height * outputScale);
                        if (expectedPixels > MAX_PIXELS) outputScale = Math.sqrt(MAX_PIXELS / (cssViewport.width * cssViewport.height));
                        
                        const renderViewport = page.getViewport({scale: cssScale * outputScale});

                        const canvas = document.createElement('canvas');
                        canvas.className = 'pdf-page-canvas';
                        canvas.width = Math.floor(renderViewport.width);
                        canvas.height = Math.floor(renderViewport.height);
                        canvas.style.width = "100%";
                        canvas.style.height = "100%";
                        
                        containerDiv.style.width = Math.floor(cssViewport.width) + "px";
                        containerDiv.style.height = Math.floor(cssViewport.height) + "px";

                        const renderContext = { canvasContext: canvas.getContext('2d', { alpha: false }), viewport: renderViewport };
                        const renderTask = page.render(renderContext);
                        state.renderTasks[pageNum] = renderTask;

                        renderTask.promise.then(() => {
                            containerDiv.innerHTML = '';
                            containerDiv.appendChild(canvas);
                            state.pageMap.set(pageNum, 'done');
                            delete state.renderTasks[pageNum];
                        }).catch(err => {
                            if (err.name === 'RenderingCancelledException') state.pageMap.delete(pageNum);
                        });
                    });
                };

                const initObserver = () => {
                    const opts = { root: scroller, rootMargin: '200px', threshold: 0.1 };
                    state.observer = new IntersectionObserver((entries) => {
                        entries.forEach(entry => {
                            const pageNum = parseInt(entry.target.dataset.page);
                            if (entry.isIntersecting) {
                                renderPageIntoContainer(pageNum, entry.target);
                                if (entry.intersectionRatio > 0.5) {
                                    state.currentPage = pageNum;
                                    indicator.textContent = pageNum + ' / ' + state.pdf.numPages;
                                }
                            }
                            else {
                                // Unload canvases when they scroll out of view to prevent mobile memory crashes
                                if (state.pageMap.get(pageNum) === 'done') {
                                    entry.target.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#ccc;font-size:20px;">' + pageNum + '</div>';
                                    state.pageMap.delete(pageNum);
                                }
                            }
						});
                    }, opts);
                };

                const layoutPages = () => {
                    scrollerInner.innerHTML = '';
                    state.pageMap.clear();
                    state.pdf.getPage(1).then(page => {
                        const vp = page.getViewport({scale: 1.0});
                        const ratio = vp.height / vp.width;
                        const availWidth = scroller.clientWidth - 20;
                        const initialWidth = Math.floor(availWidth * state.zoom);
                        const initialHeight = Math.floor(initialWidth * ratio);

                        for (let i = 1; i <= state.pdf.numPages; i++) {
                            const div = document.createElement('div');
                            div.className = 'pdf-page-container';
                            div.dataset.page = i;
                            div.id = 'pdf_page_' + i;
                            div.style.width = initialWidth + 'px';
                            div.style.height = initialHeight + 'px';
                            div.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#ccc;font-size:20px;">' + i + '</div>';
                            scrollerInner.appendChild(div);
                            state.observer.observe(div);
                        }
                        
                        indicator.textContent = state.currentPage + ' / ' + state.pdf.numPages;
                        
                        if (state.pendingJump) {
                            setTimeout(() => window.myCloudPdfJumpTo(state.pendingJump), 100);
                            state.pendingJump = null;
                        }
                    });
                };

                const applyZoom = (newZoom, viewportAnchorX = null, viewportAnchorY = null) => {
                    const centerX = viewportAnchorX !== null ? viewportAnchorX : scroller.clientWidth / 2;
                    const centerY = viewportAnchorY !== null ? viewportAnchorY : scroller.clientHeight / 2;
                    
                    const docX = scroller.scrollLeft + centerX;
                    const docY = scroller.scrollTop + centerY;
            
                    const firstPage = document.getElementById('pdf_page_1');
                    if (!firstPage) { layoutPages(); return; }
            
                    const pageRatio = firstPage.offsetHeight / firstPage.offsetWidth;
                    const availWidth = scroller.clientWidth - 20;
                    const newWidth = Math.floor(availWidth * newZoom);
                    const newHeight = Math.floor(newWidth * pageRatio);

                    let targetPage = null;
                    let relX = 0;
                    let relY = 0;
                    const pages = Array.from(document.querySelectorAll('.pdf-page-container'));
                    
                    for (let i = 0; i < pages.length; i++) {
                        let p = pages[i];
                        let top = p.offsetTop;
                        let bottom = top + p.offsetHeight;
                        if (docY >= top && docY <= bottom + 15) {
                            targetPage = p;
                            relY = (docY - top) / p.offsetHeight;
                            relX = (docX - p.offsetLeft) / p.offsetWidth;
                            break;
                        }
                    }
                    
                    state.zoom = newZoom;
            
                    pages.forEach(div => {
                        state.pageMap.delete(parseInt(div.dataset.page));
                        div.style.width = newWidth + 'px';
                        div.style.height = newHeight + 'px';
                    });
                    
                    if (targetPage) {
                        const newDocY = targetPage.offsetTop + (newHeight * relY);
                        const newDocX = targetPage.offsetLeft + (newWidth * relX);
                        scroller.scrollLeft = newDocX - centerX;
                        scroller.scrollTop = newDocY - centerY;
                    }

                    pages.forEach(div => {
                        state.observer.unobserve(div);
                        state.observer.observe(div);
                    });
                };

                window.myCloudPdfZoom = (delta) => {
                    const newZoom = Math.max(0.5, Math.min(15.0, state.zoom + delta));
                    if (newZoom !== state.zoom) applyZoom(newZoom);
                 };

                window.myCloudPdfShowJump = () => {
                    jumpInput.value = state.currentPage;
                    jumpInput.max = state.pdf.numPages;
                    if (jumpPopup.classList.contains('active')) {
                        jumpPopup.classList.remove('active');
                    } else {
                        jumpPopup.classList.add('active');
                        setTimeout(() => jumpInput.focus(), 100);
                    }
                };

                window.myCloudPdfGo = () => {
                    const p = parseInt(jumpInput.value);
                    if (p >= 1 && p <= state.pdf.numPages) {
                        window.myCloudPdfJumpTo(p);
                        jumpPopup.classList.remove('active');
                    }
                };

                window.myCloudPdfJumpTo = (pageNum) => {
                    const el = document.getElementById('pdf_page_' + pageNum);
                    if (el) {
                        el.scrollIntoView({ behavior: 'auto', block: 'start' });
                        state.currentPage = pageNum;
                    }
                };

                window.myCloudPdfToggleBookmarks = (show) => {
                    if (show) bookmarkDrawer.classList.add('active');
                    else bookmarkDrawer.classList.remove('active');
                };

                const loadOutlines = () => {
                    state.pdf.getOutline().then(outline => {
                        if (outline && outline.length > 0) {
                            document.getElementById('btnPdfBookmarks').style.display = 'flex';
                            document.getElementById('sepPdfBookmarks').style.display = 'block';
                            
                            const listDiv = document.getElementById('myCloudPdfBookmarkList');
                            listDiv.innerHTML = '';
                            
                            const renderItem = (items) => {
                                items.forEach(item => {
                                    const a = document.createElement('div');
                                    a.className = 'pdf-bookmark-item';
                                    a.textContent = item.title;
                                    a.onclick = () => {
                                        if (typeof item.dest === 'string') {
                                            state.pdf.getDestination(item.dest).then(dest => {
                                                state.pdf.getPageIndex(dest[0]).then(idx => {
                                                    window.myCloudPdfJumpTo(idx + 1);
                                                    window.myCloudPdfToggleBookmarks(false);
                                                });
                                            });
                                        } else if (Array.isArray(item.dest)) {
                                            state.pdf.getPageIndex(item.dest[0]).then(idx => {
                                                window.myCloudPdfJumpTo(idx + 1);
                                                window.myCloudPdfToggleBookmarks(false);
                                            });
                                        }
                                    };
                                    listDiv.appendChild(a);
                                    if (item.items && item.items.length > 0) renderItem(item.items);
                                });
                            };
                            renderItem(outline);
                        }
                    });
                };

                let pdfPinchStartDist = 0;
                let pdfPinchStartZoom = 1;
                let pdfPinchCurrentScale = 1;
                let pdfPinchAnchorX = 0;
                let pdfPinchAnchorY = 0;

                scroller.addEventListener('touchstart', function(e) {
                    if (e.touches.length === 2) {
                        e.preventDefault();
                        const t1 = e.touches[0];
                        const t2 = e.touches[1];
                        pdfPinchStartDist = Math.hypot(t1.clientX - t2.clientX, t1.clientY - t2.clientY);
                        pdfPinchStartZoom = state.zoom;
                        
                        const rect = scroller.getBoundingClientRect();
                        pdfPinchAnchorX = ((t1.clientX + t2.clientX) / 2) - rect.left;
                        pdfPinchAnchorY = ((t1.clientY + t2.clientY) / 2) - rect.top;
                        
                        const docX = pdfPinchAnchorX + scroller.scrollLeft;
                        const docY = pdfPinchAnchorY + scroller.scrollTop;
                        scrollerInner.style.transformOrigin = `${docX}px ${docY}px`;
                        scrollerInner.style.transition = 'none';
                   }
                }, {passive: false});

                scroller.addEventListener('touchmove', function(e) {
                    if (e.touches.length === 2 && pdfPinchStartDist > 0) {
                        e.preventDefault();
                        const currentDist = Math.hypot(e.touches[0].clientX - e.touches[1].clientX, e.touches[0].clientY - e.touches[1].clientY);
                        pdfPinchCurrentScale = currentDist / pdfPinchStartDist;
                        scrollerInner.style.transform = `scale(${pdfPinchCurrentScale})`;
                    }
                }, {passive: false});

                const endPinch = function() {
                    if (pdfPinchStartDist > 0) {
                        scrollerInner.style.transition = 'none';
                        scrollerInner.style.transform = 'none';
                        let finalZoom = Math.max(0.5, Math.min(15.0, pdfPinchStartZoom * pdfPinchCurrentScale));
                        
                        if (Math.abs(finalZoom - state.zoom) > 0.01) {
                            applyZoom(finalZoom, pdfPinchAnchorX, pdfPinchAnchorY);
                        }
                        pdfPinchStartDist = 0;
                    }
                };

                scroller.addEventListener('touchend', function(e) { if (e.touches.length < 2) endPinch(); });
                scroller.addEventListener('touchcancel', endPinch);

                ceFetchPreviewBlob(url, path).then(blob => blob.arrayBuffer()).then(buffer => {
                    lib.getDocument({ data: buffer }).promise.then(pdf => {
                        state.pdf = pdf;
                        initObserver();
                        layoutPages();
                        loadOutlines(); 
                   }).catch(err => {
                        body.innerHTML = '<div style="color:#fff;text-align:center;padding:20px;">PDF Error: ' + err.message + '</div>';
                    });
                }).catch(err => {
                    body.innerHTML = '<div style="color:#fff;text-align:center;padding:20px;">Fetch Error: ' + err.message + '</div>';
                });
            }
        }
    }
    
    // --- 5. TEXT / CODE HANDLER ---
    else if (editExts.includes(ext) || ext === 'txt') {
        body.style.overflow = 'auto';
        body.style.background = '#fff';
        body.style.alignItems = 'flex-start';
        
        body.innerHTML = 
        '<div class="myCloud-loading-container" style="color:#333">' +
            '<div class="myCloud-spinner dark"></div>' +
            '<div>' + myCloud_LANG.loading_text + '</div></div>' +
        '</div>';    
        ceFetchPreviewBlob(url, path).then(b => b.text()).then(text => {
            body.innerHTML = '<div style="padding:20px; white-space:pre-wrap; font-family:monospace; color:#333;">' + text.replace(/</g, '&lt;') + '</div>';
        }).catch(err => {
            body.innerHTML = '<div style="color:red; padding:20px;">Error loading text file: ' + err.message + '</div>';
        });
    }

    // --- 6. DOCX HANDLER ---
    else if (ext === 'docx') {
        body.style.display = 'block'; 
        body.style.overflow = 'auto'; 
        body.style.background = '#525659'; 
        body.style.padding = '0';
        body.style.position = 'relative';

        const styleId = 'myCloudDocxPrintFix';
        const oldStyle = document.getElementById(styleId);
        if (oldStyle) oldStyle.remove();

        const style = document.createElement('style');
        style.id = styleId;
        style.innerHTML = 
            '#docx_zoom_wrapper section { background-color: #ffffff !important; box-shadow: 0 4px 15px rgba(0,0,0,0.4) !important; margin-bottom: 30px !important; border: none !important; color: #000; }' +
            '#docx_zoom_wrapper article, #docx_zoom_wrapper header, #docx_zoom_wrapper footer { background-color: transparent !important; }';
       document.head.appendChild(style);

        body.innerHTML = 
        '<div class="myCloud-loading-container" style="color:#fff">' +
            '<div class="myCloud-spinner"></div>' +
            '<div>' + (typeof myCloud_LANG !== 'undefined' ? myCloud_LANG.processing_doc : 'Processing DOCX...') + '</div>' +
        '</div>';
    
            const initDocx = async () => {
                if (typeof window.docx === 'undefined') {
                    await myCloudLoadJS('https://unpkg.com/jszip/dist/jszip.min.js');
                    await myCloudLoadJS('https://unpkg.com/docx-preview/dist/docx-preview.js');
                }
                
                let blob = await ceFetchPreviewBlob(url, path);

                if (!blob.type || blob.type === '') {
                    blob = new Blob([blob], { type: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' });
                }

                body.innerHTML = ''; 
                const wrapper = document.createElement('div');
                wrapper.id = 'docx_zoom_wrapper';
                wrapper.style.transformOrigin = 'top center'; 
                wrapper.style.display = 'flex';
                wrapper.style.flexDirection = 'column';
                wrapper.style.alignItems = 'center';
                body.appendChild(wrapper);

                window.docx.renderAsync(blob, wrapper, null, {
                    className: "docx_viewer", inWrapper: true, ignoreWidth: false, ignoreHeight: false, breakPages: true, renderHeaders: true, renderFooters: true
                }).then(() => {
                    const applyZoom = () => {
                        const firstPage = wrapper.querySelector('section, article');
                        if (!firstPage) return;
                        firstPage.style.backgroundColor = '#ffffff';
                        firstPage.style.background = '#fff'; 
                        const pageWidth = firstPage.offsetWidth; 
                        const screenWidth = body.clientWidth;
                        let scaleFactor = (screenWidth - 40) / pageWidth;
                        wrapper.style.transform = 'scale(' + scaleFactor + ')';
                        const realHeight = wrapper.scrollHeight;
                        wrapper.style.height = (realHeight * scaleFactor) + 80 + 'px';
                        wrapper.style.width = '100%'; 
                        wrapper.style.paddingTop = '20px';
                    };
                    setTimeout(applyZoom, 150);
                    window.addEventListener('resize', applyZoom);
                    
                    const closeBtn = document.querySelector('.myCloud-floating-close');
                    if(closeBtn) {
                        const oldClick = closeBtn.onclick;
                        closeBtn.onclick = (e) => {
                            window.removeEventListener('resize', applyZoom);
                            if(oldClick) oldClick(e); else myCloudClosePreview();
                        };
                    }
                });
            };
            
            initDocx().catch(err => {
                console.error(err);
                body.innerHTML = '<div style="color:red; padding:20px;">Error loading DOCX previewer.</div>';
            });
    }
    
    // --- 7. XLSX HANDLER ---
    else if (ext === 'xlsx') {
        body.style.background = '#fff';
        body.style.display = 'block'; 
        body.innerHTML = 
        '<div class="myCloud-loading-container" style="color:#333">' +
            '<div class="myCloud-spinner dark"></div>' +
            '<div>' + (typeof myCloud_LANG !== 'undefined' ? myCloud_LANG.gen_sheet : 'Parsing Sheet...') + '</div>' +
        '</div>';
    
            const initXlsx = async () => {
                if (typeof window.XLSX === 'undefined') {
                    await myCloudLoadJS('https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js');
                }
                
                const blob = await ceFetchPreviewBlob(url, path);
                const ab = await blob.arrayBuffer();
                
                const workbook = window.XLSX.read(ab, {type: 'array'});
                let sheetsHtml = '<div class="myCloud-excel-sheets">';
                let tabsHtml = '<div class="myCloud-excel-tabs">';
                workbook.SheetNames.forEach((name, index) => {
                    const worksheet = workbook.Sheets[name];
                    if(!worksheet['!ref']) worksheet['!ref'] = 'A1:A1';
                    const safeName = typeof myCloudEscapeHtml === 'function' ? myCloudEscapeHtml(name) : name.replace(/</g, "&lt;").replace(/>/g, "&gt;");
                    const rawHtml = window.XLSX.utils.sheet_to_html(worksheet, { id: 'sheet_tbl_'+index, editable:false });
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = rawHtml;
                    const tableEl = tempDiv.querySelector('table');
                    const activeClass = index === 0 ? 'active' : '';
                    sheetsHtml += '<div id="myCloudSheet_' + index + '" class="myCloud-sheet-view ' + activeClass + '">';
                    if (tableEl) {
                        tableEl.className = 'myCloud-excel-table'; 
                        sheetsHtml += tableEl.outerHTML;
                    } else { sheetsHtml += '<div style="padding:10px; color:#999;">' + (typeof myCloud_LANG !== 'undefined' ? myCloud_LANG.empty_sheet : 'Empty Sheet') + '</div>'; }
                    sheetsHtml += '</div>';
                    tabsHtml += '<div class="myCloud-excel-tab ' + activeClass + '" onclick="myCloudSwitchSheet(' + index + ', this)">' + safeName + '</div>';
                });
                sheetsHtml += '</div>'; tabsHtml += '</div>';
                body.innerHTML = '<div class="myCloud-excel-wrapper">' + sheetsHtml + tabsHtml + '</div>';
            };
            
            initXlsx().catch(err => {
                console.error(err);
                body.innerHTML = '<div style="color:red; padding:20px;">' + (typeof myCloud_LANG !== 'undefined' ? myCloud_LANG.excel_render_err : 'Excel Render Error') + '<br><small>' + err.message + '</small></div>';
            });
    }
    
    // --- 8. EPUB HANDLER ---
    else if (isEpub) {
        body.style.background = '#fafafa';
        body.style.display = 'flex';
        body.style.flexDirection = 'column';
        body.style.overflow = 'hidden';
        body.style.padding = '0';
        body.style.position = 'relative';

        body.innerHTML = 
        '<div class="myCloud-loading-container" id="epubLoadingWrap" style="color:#333; z-index: 10;">' +
            '<div class="myCloud-spinner dark"></div>' +
            '<div style="margin-top: 15px;">' + (typeof myCloud_LANG !== 'undefined' ? (myCloud_LANG.loading || 'Loading eBook...') : 'Loading eBook...') + '</div>' +
        '</div>' +
        '<div id="epubViewer" style="width: 100%; height: 100%; overflow: hidden;"></div>' +
        '<div id="epubTocOverlay" style="display:none; position:absolute; top:0; left:0; width:300px; max-width:80%; height:100%; background:rgba(255,255,255,0.95); backdrop-filter:blur(10px); border-right:1px solid var(--border-default); z-index:100; overflow-y:auto; box-shadow: 2px 0 10px rgba(0,0,0,0.1); padding: 20px;"></div>' +
        '<div id="epubSearchOverlay" style="display:none; position:absolute; top:0; right:0; width:320px; max-width:80%; height:100%; background:rgba(255,255,255,0.95); backdrop-filter:blur(10px); border-left:1px solid var(--border-default); z-index:100; overflow-y:auto; box-shadow: -2px 0 10px rgba(0,0,0,0.1); padding: 20px;">' +
            '<button id="epubSearchCloseBtn" style="position:absolute; top:15px; left:15px; background:transparent; border:none; font-size:20px; color:var(--text-secondary); cursor:pointer;">✕</button>' +
            '<h3 style="margin-top:0; margin-left:30px; color:var(--text-primary); border-bottom: 1px solid var(--border-default); padding-bottom:10px;">' + (typeof myCloud_LANG !== 'undefined' ? (myCloud_LANG.search || 'Search') : 'Search') + '</h3>' +
            '<div style="display:flex; gap:8px; margin-bottom: 15px;">' +
                '<input type="text" id="epubSearchInput" class="myCloudInlineInput" placeholder="..." style="flex:1;">' +
                '<button id="epubSearchExecBtn" style="padding:6px 12px; background:var(--accent-primary); color:#fff; border:none; border-radius:4px; cursor:pointer;">Go</button>' +
            '</div>' +
            '<div id="epubSearchResults" style="font-size:13px; color:var(--text-primary);"></div>' +
        '</div>' +
        '<div class="myCloud-pdf-toolbar" style="bottom: 30px;">' + 
            '<button class="myCloud-pdf-btn" id="epubTocBtn" title="Table of Contents"><svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg></button>' +
            '<button class="myCloud-pdf-btn" id="epubSearchBtn" title="' + (typeof myCloud_LANG !== 'undefined' ? (myCloud_LANG.search || 'Search') : 'Search') + '"><svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg></button>' +
            '<div class="myCloud-pdf-sep" style="width:1px; height:20px; background:rgba(255,255,255,0.2); margin:0 5px;"></div>' +
            '<button class="myCloud-pdf-btn" id="epubPrevBtn" title="Previous Page">❮</button>' +
            '<span id="epubPageIndicator" class="myCloud-pdf-page-num" style="min-width: 60px; font-size:13px;">...</span>' +
            '<button class="myCloud-pdf-btn" id="epubNextBtn" title="Next Page">❯</button>' +
            '<div class="myCloud-pdf-sep" style="width:1px; height:20px; background:rgba(255,255,255,0.2); margin:0 5px;"></div>' +
            '<button class="myCloud-pdf-btn" id="epubZoomOut" title="Decrease Font">A-</button>' +
            '<button class="myCloud-pdf-btn" id="epubZoomIn" title="Increase Font">A+</button>' +
        '</div>';

        const initEpub = async () => {
            if (typeof window.ePub === 'undefined') {
                await myCloudLoadJS('/script/epub/jszip.min.js');
                await myCloudLoadJS('/script/epub/epub.min.js');
            }
            
            const viewer = document.getElementById('epubViewer');

            const blob = await ceFetchPreviewBlob(url, path);
            const buffer = await blob.arrayBuffer();
            const book = window.ePub(buffer);
            
            const isMobileEpub = window.innerWidth < 768;
            
            let currentSearchHighlights = [];
            
            const rendition = book.renderTo(viewer, {
                width: "100%",
                height: "100%",
                spread: isMobileEpub ? "none" : "auto",
                manager: "default",
                flow: "scrolled-doc"
            });
            
            let currentFontSize = 100;
            const fontMatch = document.cookie.match(/(^| )myCloudEpubFontSize=([^;]+)/);
            if (fontMatch && !isNaN(parseInt(fontMatch[2]))) {
                currentFontSize = parseInt(fontMatch[2]);
            }
            
            rendition.display().then(() => {
                rendition.themes.default({
                    "body": { "padding-bottom": "120px !important", "font-family": "sans-serif", "text-align": "justify" },
                    "a[epub\\:type~='noteref'], a[role='doc-noteref']": { "color": "#0078d4 !important", "text-decoration": "none", "font-weight": "bold" },
                    "aside[epub\\:type~='footnote'], [role='doc-footnote'], .footnote": { "background-color": "#f3f2f1", "border-left": "3px solid #0078d4", "padding": "10px", "font-size": "0.8em", "margin": "15px 0" },
                    "[epub\\:type~='endnote'], [role='doc-endnote'], .endnote": { "background-color": "#fdf8e6", "border": "1px solid #f0ad4e", "padding": "10px", "font-size": "0.8em", "margin": "15px 0" }
                });

                if (currentFontSize !== 100) rendition.themes.fontSize(currentFontSize + '%');
                const loading = body.querySelector('#epubLoadingWrap');
                if (loading) loading.style.display = 'none';
                
                book.loaded.navigation.then(toc => {
                    const tocOverlay = document.getElementById('epubTocOverlay');
                    let tocHtml = '<h3 style="margin-top:0; color:var(--text-primary); border-bottom: 1px solid var(--border-default); padding-bottom:10px;">Table of Contents</h3><ul style="list-style:none; padding:0; margin:0;">';
                    
                    const buildToc = (items) => {
                        items.forEach(item => {
                            tocHtml += '<li style="margin-bottom: 12px;"><a href="#" data-href="' + item.href + '" class="epub-toc-link" style="color:var(--accent-primary); text-decoration:none; font-size:14px; display:block;">' + item.label + '</a>';
                            if (item.subitems && item.subitems.length > 0) {
                                tocHtml += '<ul style="list-style:none; padding-left:15px; margin-top:8px;">';
                                buildToc(item.subitems);
                                tocHtml += '</ul>';
                            }
                            tocHtml += '</li>';
                        });
                    };
                    buildToc(toc);
                    tocHtml += '</ul>';
                    
                    tocOverlay.innerHTML = tocHtml + '<button onclick="this.parentElement.style.display=\'none\'" style="position:absolute; top:15px; right:15px; background:transparent; border:none; font-size:20px; color:var(--text-secondary); cursor:pointer;">✕</button>';
                    
                    tocOverlay.querySelectorAll('.epub-toc-link').forEach(link => {
                        link.onclick = (e) => {
                            e.preventDefault();
                            rendition.display(e.target.dataset.href);
                            tocOverlay.style.display = 'none';
                        };
                    });
                    tocOverlay.style.display = 'block';
                });
                
                rendition.on("relocated", (location) => {
                    const indicator = document.getElementById('epubPageIndicator');
                    if (indicator) {
                        if (book.locations.length() > 0) {
                            const percentage = Math.round(book.locations.percentageFromCfi(location.start.cfi) * 100);
                            indicator.textContent = percentage + '%';
                        } else {
                            indicator.textContent = '...';
                        }
                    }
                });
                
                book.ready.then(() => book.locations.generate(1600)).then(() => {
                    const currentLocation = rendition.currentLocation();
                    if (currentLocation) {
                        const percentage = Math.round(book.locations.percentageFromCfi(currentLocation.start.cfi) * 100);
                        const indicator = document.getElementById('epubPageIndicator');
                        if (indicator) indicator.textContent = percentage + '%';
                    }
                });
            });
            
            document.getElementById('epubPrevBtn').onclick = () => rendition.prev();
            document.getElementById('epubNextBtn').onclick = () => rendition.next();
            document.getElementById('epubTocBtn').onclick = () => {
                const toc = document.getElementById('epubTocOverlay');
                toc.style.display = toc.style.display === 'none' ? 'block' : 'none';
            };

            const closeSearch = () => {
                body.querySelector('#epubSearchOverlay').style.display = 'none';
                currentSearchHighlights.forEach(cfi => rendition.annotations.remove(cfi, "highlight"));
                currentSearchHighlights = [];
            };

            
            body.querySelector('#epubSearchBtn').onclick = () => {
                 const s = body.querySelector('#epubSearchOverlay');
                 if (s.style.display === 'none') {
                     s.style.display = 'block';
                     if (s.style.display === 'block') body.querySelector('#epubSearchInput').focus();
                } else {
                    closeSearch();
                 }
             };
            body.querySelector('#epubSearchCloseBtn').onclick = closeSearch;

            const doEpubSearch = () => {
                const query = document.getElementById('epubSearchInput').value.trim();
                const resDiv = document.getElementById('epubSearchResults');
                if (!query) return;
                
                resDiv.innerHTML = '<div class="myCloud-spinner dark" style="width:24px;height:24px;border-width:3px;margin:20px auto;display:block;"></div>';

                currentSearchHighlights.forEach(cfi => rendition.annotations.remove(cfi, "highlight"));
                currentSearchHighlights = []; 
 
                Promise.all(book.spine.spineItems.map(item => {
                    return item.load(book.load.bind(book)).then(() => {
                        let results = item.find(query);
                        item.unload();
                        return results;
                    }).catch(err => {
                        item.unload();
                        return [];
                    });
                })).then(results => {
                    const flatResults = [].concat.apply([], results);
                    
                    if (flatResults.length === 0) {
                        resDiv.innerHTML = '<div style="color:var(--text-secondary);">No results found.</div>';
                        return;
                    }
                    
                    let html = '<ul style="list-style:none; padding:0; margin:0;">';
                    flatResults.forEach(r => {
                        currentSearchHighlights.push(r.cfi);
                        rendition.annotations.highlight(r.cfi, {}, null, "search-highlight", {"fill": "yellow", "fill-opacity": "0.4"});

                        const excerpt = r.excerpt.replace(new RegExp('(' + query + ')', 'gi'), '<b style="background:var(--selection-bg); color:var(--accent-primary);">$1</b>');
                        html += '<li style="margin-bottom:12px; padding-bottom:8px; border-bottom:1px solid var(--border-default);"><a href="#" data-cfi="'+r.cfi+'" class="epub-search-link" style="color:inherit; text-decoration:none; display:block; line-height:1.4;">' + excerpt + '</a></li>';
                    });
                    html += '</ul>';
                    resDiv.innerHTML = html;
                    
                    resDiv.querySelectorAll('.epub-search-link').forEach(link => {
                        link.onclick = (e) => {
                            e.preventDefault();
                            const cfi = e.currentTarget.dataset.cfi;
                            rendition.display(cfi);
                            if (window.innerWidth < 768) closeSearch();
                        };
                    });
                }).catch(e => {
                    resDiv.innerHTML = '<div style="color:red;">Search error occurred.</div>';
                });
            };

            document.getElementById('epubSearchExecBtn').onclick = doEpubSearch;
            document.getElementById('epubSearchInput').onkeydown = (e) => {
                if (e.key === 'Enter') doEpubSearch();
            };

            const saveEpubFontSize = (size) => {
                const d = new Date(); d.setTime(d.getTime() + (365*24*60*60*1000));
                document.cookie = "myCloudEpubFontSize=" + size + ";expires=" + d.toUTCString() + ";path=/;SameSite=Lax";
            };            

            document.getElementById('epubZoomIn').onclick = () => {
                currentFontSize += 15;
                rendition.themes.fontSize(currentFontSize + '%');
                saveEpubFontSize(currentFontSize);
            };
            document.getElementById('epubZoomOut').onclick = () => {
                currentFontSize = Math.max(50, currentFontSize - 15);
                rendition.themes.fontSize(currentFontSize + '%');
                saveEpubFontSize(currentFontSize);
            };
            
            const epubKeyHandler = (e) => {
                if (e.key === 'ArrowLeft') { e.stopPropagation(); rendition.prev(); }
                if (e.key === 'ArrowRight') { e.stopPropagation(); rendition.next(); }
            };
            document.addEventListener('keydown', epubKeyHandler, true);
            
            const closeBtn = overlay.querySelector('.myCloud-floating-close');
            if (closeBtn) {
                const oldClick = closeBtn.onclick;
                closeBtn.onclick = (e) => {
                    document.removeEventListener('keydown', epubKeyHandler, true);
                    book.destroy();
                    if(oldClick) oldClick(e); else myCloudClosePreview();
                };
            }
        };

        initEpub().catch(err => {
            console.error(err);
            body.innerHTML = '<div style="color:red; padding:20px; text-align:center;">Error loading EPUB.<br><small>' + err.message + '</small></div>';
        });
    }

    // --- 9. FONT HANDLER ---
    else if (isFont) {
        body.style.background = '#fafafa';
        body.style.display = 'flex';
        body.style.alignItems = 'center';
        body.style.justifyContent = 'center';
        body.style.padding = '20px';
        body.style.overflow = 'auto';

        const safeFilename = filename.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");

        body.innerHTML = 
        '<div class="myCloud-loading-container" style="color:#333">' +
            '<div class="myCloud-spinner dark"></div>' +
            '<div>' + (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.loading ? myCloud_LANG.loading : 'Loading...') + '</div>' +
        '</div>';

        const fontName = 'previewFont_' + Date.now();
        const font = new FontFace(fontName, 'url(' + url + ')');
        
        font.load().then(function(loadedFont) {
            document.fonts.add(loadedFont);
            body.innerHTML = 
            '<div style="font-family: \'' + fontName + '\', sans-serif; color: #333; max-width: 800px; width: 100%; text-align: center;">' +
                '<div style="font-size: 28px; margin-bottom: 30px; word-break: break-all; opacity: 0.4; font-family: sans-serif;">' + safeFilename + '</div>' +
                '<div style="font-size: 48px; margin-bottom: 20px; line-height: 1.2;">ABCDEFGHIJKLMNOPQRSTUVWXYZ<br>abcdefghijklmnopqrstuvwxyz<br>0123456789</div>' +
                '<div style="font-size: 32px; margin-bottom: 20px;">The quick brown fox jumps over the lazy dog.</div>' +
                '<div style="font-size: 24px; margin-bottom: 20px;">The quick brown fox jumps over the lazy dog.</div>' +
                '<div style="font-size: 16px;">The quick brown fox jumps over the lazy dog.</div>' +
            '</div>';
        }).catch(function(error) {
            console.error("Font load error:", error);
            body.innerHTML = '<div style="color:red; padding:20px; text-align:center;">Error loading font preview.</div>';
        });
    }

    // --- 10. MAP HANDLER (KML / KMZ) ---
    else if (isMap) {
        body.style.background = '#e5e3df';
        body.style.padding = '0';
        body.style.display = 'block';
        
        body.innerHTML = 
        '<div class="myCloud-loading-container" style="color:#333; position:absolute; z-index:100; pointer-events:none; width:100%; height:100%;">' +
            '<div class="myCloud-spinner dark"></div>' +
            '<div>Rendering Map...</div>' +
        '</div>' +
        '<div id="kmlMapContainer" style="width:100%; height:100%;"></div>';

        const initMap = async () => {
            // Load Leaflet CSS
            if (!document.getElementById('leafletCss')) {
                const link = document.createElement('link');
                link.id = 'leafletCss';
                link.rel = 'stylesheet';
                link.href = 'https://unpkg.com/leaflet/dist/leaflet.css';
                document.head.appendChild(link);
            }
            
            // Load Leaflet JS & KML Plugin
            if (typeof window.L === 'undefined')await myCloudLoadJS('https://unpkg.com/leaflet/dist/leaflet.js');
            if (typeof window.L.KML === 'undefined') await myCloudLoadJS('https://unpkg.com/leaflet-kml/L.KML.js');

            const map = L.map('kmlMapContainer', { zoomControl: false });
            L.control.zoom({ position: 'bottomright' }).addTo(map); // Move zoom controls away from your top-right Close button
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap contributors' }).addTo(map);

            const blob = await ceFetchPreviewBlob(url, path);
            let kmlText = '';

            if (ext === 'kmz') {
                if (typeof window.JSZip === 'undefined') await myCloudLoadJS('https://unpkg.com/jszip/dist/jszip.min.js');
                const zip = await JSZip.loadAsync(blob);
                const kmlFile = Object.keys(zip.files).find(name => name.toLowerCase().endsWith('.kml'));
                if (!kmlFile) throw new Error("No KML data found inside KMZ archive.");
                kmlText = await zip.file(kmlFile).async('string');
            } else {
                kmlText = await blob.text();
            }

            const parser = new DOMParser();
            const kmlDoc = parser.parseFromString(kmlText, 'text/xml');
            const track = new L.KML(kmlDoc);
            map.addLayer(track);
            map.fitBounds(track.getBounds());
            body.querySelector('.myCloud-loading-container').style.display = 'none';
        };

        initMap().catch(err => {
            body.innerHTML = '<div style="color:red; padding:20px; text-align:center;">Failed to render Map Data.<br><small>' + err.message + '</small></div>';
        });
    }

    // --- 11. EML (EMAIL) HANDLER ---
    else if (ext === 'eml') {
        body.style.background = '#f3f2f1';
        body.style.padding = '0';
        body.style.display = 'block';
        
        body.innerHTML = 
        '<div class="myCloud-loading-container" style="color:#333; position:absolute; z-index:100; width:100%; height:100%;">' +
            '<div class="myCloud-spinner dark"></div>' +
            '<div>Parsing Email...</div>' +
        '</div>';

        const initEml = async () => {
            const blob = await ceFetchPreviewBlob(url, path);
            const arrayBuffer = await blob.arrayBuffer();
            
            // Dynamically import PostalMime directly from CDN
            const { default: PostalMime } = await import('https://cdn.jsdelivr.net/npm/postal-mime/+esm');
            const parser = new PostalMime();
            const email = await parser.parse(arrayBuffer);
            
            const esc = (str) => typeof myCloudEscapeHtml === 'function' ? myCloudEscapeHtml(str || '') : String(str || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');

            const safeSubject = esc(email.subject || '(No Subject)');
            
            const fromName = email.from && email.from.name ? email.from.name : (email.from ? email.from.address : 'Unknown Sender');
            const fromAddr = email.from && email.from.name && email.from.address ? `<span style="color:#605e5c; font-weight:normal;">&lt;${esc(email.from.address)}&gt;</span>` : '';
            const safeFrom = esc(fromName) + ' ' + fromAddr;
            const initial = esc(fromName.charAt(0).toUpperCase() || '?');
            
            let toStr = '';
            if (email.to && email.to.length > 0) toStr = email.to.map(t => esc(t.name || t.address)).join('; ');
            
            const dObj = new Date(email.date);
            const safeDate = isNaN(dObj) ? esc(email.date) : dObj.toLocaleString();

            const hasAttachments = email.attachments && email.attachments.length > 0;
            const headerHtml = 
                '<div style="background:#f1f1f1; border-left:5px solid #005a9e; margin-left:10px; ' + (hasAttachments ? '' : 'border-bottom:1px solid #005a9e; ') + 'padding:20px 24px 0 24px; flex-shrink:0; text-align:left; font-family:\'Segoe UI\', system-ui, sans-serif;">' +
                    '<div style="font-size:30px; font-weight:bold; color:#005a9e; margin-bottom:12px; user-select:text; line-height:1.3;">' + safeSubject + '</div>' +
                    '<div style="font-size:16px; color:#605e5c; margin-bottom:6px; user-select:text;">Received: <b>' + safeDate + '</b></div>' +
                    '<div style="font-size:16px; color:#201f1e; margin-bottom:6px; user-select:text;">Sender: <b>' + safeFrom + '</b></div>' +
                    (toStr ? '<div style="font-size:16px; color:#605e5c; margin-bottom:16px; user-select:text;">Recipient: <b>' + toStr + '</b></div>' : '<div style="margin-bottom:16px;"></div>') +
                    '<hr style="border:0; border-top:1px solid #005a9e; margin:0 0 16px 0;">' +
                '</div>';

            let attachmentsHtml = '';
            if (hasAttachments) {
                attachmentsHtml = '<div style="background:#f5f5f5;  border-left:5px solid #005a9e; border-bottom:1px solid #005a9e; margin-left:10px; padding:0 24px 16px 24px; flex-shrink:0; text-align:left; display:flex; flex-direction:column; gap:8px; font-family:\'Segoe UI\', system-ui, sans-serif;">' +
                                  '<div style="font-size:16px; font-weight:600; color:#005a9e;">Attachments:</div>' +
                                  '<div style="display:flex; flex-wrap:wrap; gap:10px;">';
               email.attachments.forEach((att, idx) => {
                    const blob = new Blob([att.content], { type: att.mimeType || 'application/octet-stream' });
                    const blobUrl = URL.createObjectURL(blob);
                    const safeAttName = typeof myCloudEscapeHtml === 'function' ? myCloudEscapeHtml(att.filename || 'attachment_' + idx) : (att.filename || 'attachment_' + idx);
                    const sizeStr = typeof myCloudFormatBytes === 'function' ? myCloudFormatBytes(att.content.length) : (att.content.length + ' B');
                    attachmentsHtml += '<a href="' + blobUrl + '" download="' + safeAttName + '" style="background:#fff; border:1px solid #edebe9; padding:8px 12px; border-radius:4px; font-size:13px; color:#201f1e; text-decoration:none; display:inline-flex; align-items:center; gap:10px; box-shadow:0 1px 2px rgba(0,0,0,0.02); transition:background 0.15s; max-width:250px;" onmouseover="this.style.background=\'#f3f2f1\'" onmouseout="this.style.background=\'#fff\'" title="Download ' + safeAttName + ' (' + sizeStr + ')">' +
                        '<svg viewBox="0 0 24 24" width="20" height="20" fill="var(--accent-primary, #0078d4)" style="flex-shrink:0;"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>' +
                        '<div style="display:flex; flex-direction:column; overflow:hidden;">' +
                            '<span style="font-weight:500; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">' + safeAttName + '</span>' +
                            '<span style="font-size:11px; color:#605e5c;">' + sizeStr + '</span>' +
                        '</div>' +
                    '</a>';
                });
                attachmentsHtml += '</div></div>';
            }



            // [SECURITY] Strict CSP to block tracking pixels and remote assets
            const cspMeta = '<meta http-equiv="Content-Security-Policy" content="default-src \'none\'; style-src \'unsafe-inline\'; img-src data: blob: cid:; font-src data:; frame-src \'none\';">';
		const customStyle = '<style>body, pre { font-size: 16px !important; font-family: \'Segoe UI\', system-ui, sans-serif; line-height: 1.5; color: #201f1e; }</style>';
            let contentHtml = email.html || ('<pre style="padding:20px; white-space:pre-wrap; font-family:sans-serif;">' + (email.text || 'No content found.') + '</pre>');
            
            if (contentHtml.toLowerCase().includes('<head>')) {
                contentHtml = contentHtml.replace(/<head>/i, '<head>' + cspMeta + customStyle);
            } else {
                contentHtml = cspMeta + customStyle + contentHtml;
            }
            
            body.style.display = 'flex';
            body.style.flexDirection = 'column';
            body.style.alignItems = 'stretch';
            body.style.justifyContent = 'flex-start';
            body.innerHTML = headerHtml + attachmentsHtml + '<div style="flex:1; width:100%; padding:24px; box-sizing:border-box; background:#fff; margin-left:12px; border-left:2px solid #005a9e;"><iframe id="emlIframe" sandbox="" style="width:100%; height:100%; border:none; background:transparent;"></iframe></div>';
            
            // Use srcdoc for security - renders the HTML string directly into the iframe
            document.getElementById('emlIframe').srcdoc = contentHtml;
        };

        initEml().catch(err => {
            console.error("EML Parse Error:", err);
            body.innerHTML = '<div style="color:red; padding:20px; text-align:center;">Failed to parse email.<br><small>' + err.message + '</small><br><br><a href="' + url + '" style="color:#4da3ff;">Download EML</a></div>';
        });
    }

    // --- 11. FALLBACK ---
    else {
         body.innerHTML = '<div style="text-align:center; color:#ccc; margin-top:20%">' + (typeof myCloud_LANG !== 'undefined' ? myCloud_LANG.preview_not_sup : 'Preview not supported') + ' .' + ext + '<br><br><a href="' + url + '" style="color:#4da3ff; font-size:16px;">' + (typeof myCloud_LANG !== 'undefined' ? myCloud_LANG.download_file : 'Download file') + '</a></div>';
    }
    
    overlay.style.display = 'flex';
    myCloudPrefetchNavTokens();
}


// Prefetches next/previous tokens for smoother navigation.
// Caches the token to avoid round-trips during navigation.
async function myCloudPrefetchNavTokens() {
    const st = myCloudState;
    if (!st.previewPath) return;

    const searchModalOpen = document.getElementById('myCloudModal') && document.getElementById('myCloudModal').classList.contains('search-modal') && document.getElementById('myCloudModalOverlay').style.display !== 'none';
    const allItems = searchModalOpen ? (window.myCloudSearchItems || []) : myCloudGetSortedItems();
    const currentIndex = allItems.findIndex(i => i.name === st.previewPath);
    if (currentIndex === -1) return;

    const getDecExt = (item) => {
        let name = item.displayName || item.name.split('/').pop();
        return name.replace(/\.enc$/, '').split('.').pop().toLowerCase();
    };

    const neighbors = [];
    
    for (let i = currentIndex - 1; i >= 0; i--) {
        const ext = getDecExt(allItems[i]);
        if (allItems[i].size !== 'DIR' && myCloudConfig.navigable.includes(ext)) {
            neighbors.push(allItems[i]);
            break;
        }
    }
    for (let i = currentIndex + 1; i < allItems.length; i++) {
        const ext = getDecExt(allItems[i]);
        if (allItems[i].size !== 'DIR' && myCloudConfig.navigable.includes(ext)) {
            neighbors.push(allItems[i]);
            break;
        }
    }

    neighbors.forEach(async (item) => {
        const path = item.name;
        const cacheKey = path + '_sd'; 

        if (st.previewCache[cacheKey]) return;

        try {
            const fd = new URLSearchParams();
            fd.append('myCloud_action', 'get_download_token');
            fd.append('myCloud_key', st.key);
            fd.append('myCloud_token', typeof myCloudCsrfToken !== 'undefined' ? myCloudCsrfToken : '');
            fd.append('path', path);
            fd.append('filename', path.split('/').pop());
            fd.append('preview', '1'); 
            fd.append('isZipContent', typeof myCloudIsInsideZip === 'function' && myCloudIsInsideZip(path) ? '1' : '0');

            const resp = await fetch(window.location.pathname, { method: 'POST', body: fd }).then(r => r.json());

            if (resp.status === 'OK') {
                myCloudGetDecryptedUrl(path, window.location.pathname + '?myCloud_token=' + resp.token).then(finalUrl => {
                    st.previewCache[cacheKey] = finalUrl;
                });
            }
        } catch (e) {
            console.warn("Prefetch failed for:", path);
        }
    });
}


// Shows a modal warning for large files before previewing.
// Prevents browser freezes on huge files.
function myCloudShowPreviewLimitModal(filename, bytes, onPreview, onDownload) {
    const overlay = document.getElementById('myCloudModalOverlay');
    const modal = document.getElementById('myCloudModal');
    
    overlay.style.display = 'flex';
    overlay.style.zIndex = "15000";
    modal.className = 'myCloudModal conflict'; 
    
    // [RTL FIX] Set direction for the Warning Modal
    const curLang = (typeof myCloudState !== 'undefined' && myCloudState.settings) ? myCloudState.settings.language : 'en';
    const isRtl = ['ar', 'fa', 'he', 'ur'].includes(curLang);
    const dirAttr = isRtl ? 'dir="rtl"' : 'dir="ltr"';
    const alignStyle = isRtl ? 'text-align:right;' : 'text-align:center;'; // Center looks better generally, but RTL might prefer right
    
    const formattedSize = myCloudFormatBytes(bytes);

    modal.innerHTML = 
        '<div class="myCloudModalHeader" ' + dirAttr + ' style="background:#fff; border-bottom:none; padding-bottom:0;">' + 
            myCloud_LANG.large_file_title + 
        '</div>' +
        '<div class="myCloudModalBody" ' + dirAttr + ' style="display:flex; flex-direction:column; align-items:center; padding: 0 24px 24px 24px;">' +
            '<div style="margin-bottom:15px; margin-top:15px">' +
                '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#0078d4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
                    '<circle cx="12" cy="12" r="10"></circle>' +
                    '<path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>' +
                    '<line x1="12" y1="17" x2="12.01" y2="17"></line>' +
                '</svg>' +
            '</div>' +
            '<div style="font-size:14px; color:#333; margin-bottom:10px; ' + alignStyle + '">' +
                myCloud_LANG.large_file_msg + ' <b>' + formattedSize + '</b>.' +
            '</div>' +
            '<div style="' +
                'background: #f3f2f1; ' +
                'border: 1px solid #e1dfdd; ' +
                'border-radius: 4px; ' +
                'padding: 10px; ' +
                'width: 100%; ' +
                'font-family: \'Consolas\', monospace; ' +
                'font-size: 13px; ' +
                'color: #202020; ' +
                'word-break: break-all; ' +
                'text-align: center; ' +
                'margin-bottom: 15px; ' +
                'max-height: 80px; ' +
                'overflow-y: auto;">' +
                filename.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;") +
            '</div>' +
            '<div style="font-size:13px; color:#666; margin-bottom:20px; line-height: 1.4; ' + alignStyle + '">' +
                myCloud_LANG.large_file_warn +
            '</div>' +
            '<div class="myCloudButtons" style="justify-content: center; gap: 8px; width: 100%; margin-top:0;">' +
                '<button onclick="window.myCloudLimitResolve(\'preview\')" style="font-size:13px; padding: 6px 12px; background:#0078d4; border:1px solid #0078d4; color:#fff;">' + myCloud_LANG.preview_anyway + '</button>' +
                '<button onclick="window.myCloudLimitResolve(\'download\')" style="font-size:13px; padding: 6px 12px; background:#fff; border:1px solid #0078d4; color:#0078d4;">' + myCloud_LANG.download + '</button>' +
                '<button onclick="window.myCloudLimitResolve(\'cancel\')" style="font-size:13px; padding: 6px 12px;">' + myCloud_LANG.cancel + '</button>' +
            '</div>' +
        '</div>';
    
    window.myCloudLimitResolve = (action) => {
        overlay.style.display = 'none';
        overlay.style.zIndex = '';
        modal.innerHTML = '';
        delete window.myCloudLimitResolve;
        
        if (action === 'preview') onPreview();
        if (action === 'download') onDownload();
    };
}

// Downloads the currently previewed file.
// Fetches the file using the current path.
function myCloudDownloadFromPreview() {
    const path = myCloudState.previewPath;
    if (!path) {
        console.error("Download failed: No preview path found in state.");
        return;
    }
    let filename = path.split('/').pop();
    if (filename.endsWith('.enc')) {
        filename = (myCloudState.pathNames && myCloudState.pathNames[path]) ? myCloudState.pathNames[path] : filename.replace(/\.enc$/, '');
    }
    _cloudExProceedDownload(path, filename, false);
}

// Switches between Excel sheets in the preview modal.
// Updates UI to show the selected sheet and hide others.
function myCloudSwitchSheet(index, tabElement) {
    document.querySelectorAll('.myCloud-sheet-view').forEach(el => el.classList.remove('active'));
    const target = document.getElementById('myCloudSheet_' + index);
    if(target) target.classList.add('active');

    document.querySelectorAll('.myCloud-excel-tab').forEach(el => el.classList.remove('active'));
    tabElement.classList.add('active');
}

// Applies CSS transforms (scale, rotate, flip) to the preview image.
// Uses global transform state variables.
function myCloudUpdateImageTransform(smooth = false) {
    const img = document.getElementById('myCloudPreviewImg');
    if (!img) return;

    const t = window.myCloudTransform;
    
    img.style.transition = smooth ? 'transform 0.18s ease-out' : 'none';
    img.style.transform = 
        'translate(' + t.translateX + 'px, ' + t.translateY + 'px) ' +
        'rotate(' + t.rotate + 'deg) ' +
        'scale(' + t.scale + ') ' +
        'scaleX(' + t.flipH + ') ' +
        'scaleY(' + t.flipV + ')';
    img.style.transformOrigin = 'center center';
    img.style.cursor = t.panning ? 'grabbing' : 'grab';
}

// Zooms the image towards a specific screen coordinate (mouse/pinch point).
// Updates transform state to keep the point under the cursor.
function myCloudZoomAtPoint(deltaMultiplier, clientX, clientY) {
    const t = window.myCloudTransform;
    const img = document.getElementById('myCloudPreviewImg');
    const container = document.getElementById('myCloudImageContainer');
    if (!img || !container) return;

    const oldScale = t.scale;
    const newScale = Math.max(MIN_SCALE, Math.min(MAX_SCALE, oldScale * (1 + deltaMultiplier)));

    if (newScale === oldScale) return;

    const rect = img.getBoundingClientRect();

    const relX = (clientX - rect.left) / rect.width;
    const relY = (clientY - rect.top) / rect.height;

    const newWidth  = rect.width  * (newScale / oldScale);
    const newHeight = rect.height * (newScale / oldScale);

    const desiredLeft = clientX - relX * newWidth;
    const desiredTop  = clientY - relY * newHeight;

    const containerRect = container.getBoundingClientRect();
    t.translateX = desiredLeft - containerRect.left;
    t.translateY = desiredTop  - containerRect.top;

    t.scale = newScale;

    myCloudUpdateImageTransform(true);
}

// Zooms the image relative to the center of the viewport (buttons).
// Updates transform state.
window.myCloudZoomStep = function(direction) {
    const t = window.myCloudTransform;
    const img = document.getElementById('myCloudPreviewImg');
    if (!img) return;

    const step = 0.25; 
    let newScale = (direction > 0) ? t.scale * (1 + step) : t.scale / (1 + step);

    if (newScale < 0.2) newScale = 0.2;
    if (newScale > 10) newScale = 10;
    
    const ratio = newScale / t.scale;

    if (Math.abs(ratio - 1) < 0.001) return;

    const rect = img.getBoundingClientRect();
    const winCX = window.innerWidth / 2;
    const winCY = window.innerHeight / 2;
    const imgCX = rect.left + rect.width / 2;
    const imgCY = rect.top + rect.height / 2;

    const currentDistX = imgCX - winCX;
    const currentDistY = imgCY - winCY;

    t.translateX = currentDistX * ratio;
    t.translateY = currentDistY * ratio;
    t.scale = newScale;

    myCloudUpdateImageTransform(true);
};

// Rotates the image by 90 degrees left/right.
// Calculates fit-to-screen scaling if orientation changes.
window.myCloudRotate = function(deg) {
    if (!window.myCloudTransform) return;
    const t = window.myCloudTransform;
    const img = document.getElementById('myCloudPreviewImg');
    const container = document.getElementById('myCloudImageContainer');

    if (img && container) {
        t.rotate = (t.rotate || 0) + deg;
        const normRot = (t.rotate % 360 + 360) % 360;
        const isPerpendicular = (normRot === 90 || normRot === 270);

        img.style.maxWidth = 'none';
        img.style.maxHeight = 'none';
        img.style.width = 'auto';
        img.style.height = 'auto';

        const natW = img.naturalWidth || img.width;
        const natH = img.naturalHeight || img.height;
        const cW = container.clientWidth;
        const cH = container.clientHeight;

        let fitScale;
        
        if (isPerpendicular) {
            const sW = cW / natH;
            const sH = cH / natW;
            fitScale = Math.min(sW, sH);
        } else {
            const sW = cW / natW;
            const sH = cH / natH;
            fitScale = Math.min(sW, sH);
        }

        t.scale = fitScale;
        t.translateX = 0;
        t.translateY = 0;
    } else {
        t.rotate = (t.rotate || 0) + deg;
    }
    
    myCloudUpdateImageTransform(true);
};

// Flips the image horizontally or vertically.
// Updates transform state.
window.myCloudFlip = function(axis) {
    if (!window.myCloudTransform) return;
    if (axis === 'h') window.myCloudTransform.flipH *= -1;
    else window.myCloudTransform.flipV *= -1;
    myCloudUpdateImageTransform(true);
};

// Resets zoom and position to default state.
// Re-centers image.
window.myCloudResetZoom = function() {
    window.myCloudTransform = {
        scale: 1, translateX: 0, translateY: 0, rotate: 0, flipH: 1, flipV: 1,
        panning: false, pinching: false, dismissing: false, startX: 0, startY: 0, startScale: 1, startDist: 0, startCenterX: 0, startCenterY: 0, startClientY: 0
    };
    myCloudUpdateImageTransform(true); 
};

// Helper for smooth updates of transform properties.
// Adds CSS transition.
function myCloudUpdateTransform(smooth = false) {
    const img = document.getElementById('myCloudPreviewImg');
    if (!img || !window.myCloudTransform) return;
    const t = window.myCloudTransform;
    
    img.style.transition = smooth ? 'transform 0.2s ease-out' : 'none';
    img.style.transform = 'translate(' + t.pointX + 'px, ' + t.pointY + 'px) rotate(' + t.rotate + 'deg) scale(' + t.scale + ') scaleX(' + t.flipH + ') scaleY(' + t.flipV + ')';
    img.style.cursor = t.panning ? 'grabbing' : 'grab';
}

// Handles mouse move events for panning the image.
// Updates translateX/Y based on delta from start position.
function myCloudOnMouseMove(e) {
    const t = window.myCloudTransform;
    if (!t.panning) return;
    e.preventDefault();
    t.translateX = e.clientX - t.startX;
    t.translateY = e.clientY - t.startY;
    myCloudUpdateImageTransform();
}

// Handles mouse up event.
// Ends panning state.
function myCloudOnMouseUp() {
    window.myCloudTransform.panning = false;
    myCloudUpdateImageTransform(true);
}

// Handles touch move for panning (1 finger) and zooming (2 fingers).
// Updates transform state accordingly.
function myCloudOnTouchMove(e) {
    const t = window.myCloudTransform;
    const img = document.getElementById('myCloudPreviewImg');
    if (!img) return;

    if (e.touches.length === 1) {
        if (t.panning) {
            e.preventDefault();
            t.translateX = e.touches[0].clientX - t.startX;
            t.translateY = e.touches[0].clientY - t.startY;
            myCloudUpdateImageTransform();
        } else if (t.dismissing) {
            e.preventDefault();
            t.swipeDeltaY = e.touches[0].clientY - t.startClientY;
            
            // Visual feedback: Shrink the image towards bottom, keep background solidly black
            const img = document.getElementById('myCloudPreviewImg');
            if (img && t.swipeDeltaY > 0) {
                const shrinkProgress = Math.min(1, t.swipeDeltaY / window.innerHeight);
                const currentScale = 1 - (shrinkProgress * 0.4);
                const currentY = t.swipeDeltaY * 0.4;
                img.style.transformOrigin = 'bottom center';
                img.style.transform = `translateY(${currentY}px) scale(${currentScale})`;
            }
        }
    }
    else if (e.touches.length === 2 && t.pinching) {
        e.preventDefault();
        const t1 = e.touches[0];
        const t2 = e.touches[1];

        const currentDist = Math.hypot(t1.clientX - t2.clientX, t1.clientY - t2.clientY);
        if (t.startDist === 0) return;

        const scaleFactor = currentDist / t.startDist;
        let newScale = t.startScale * scaleFactor;

        if (newScale < 0.2) newScale = 0.2;
        if (newScale > 10) newScale = 10;

        const ratio = newScale / t.scale;
        
        const rect = img.getBoundingClientRect();
        const imgCX = rect.left + rect.width / 2;
        const imgCY = rect.top + rect.height / 2;

        const pinchCX = (t1.clientX + t2.clientX) / 2;
        const pinchCY = (t1.clientY + t2.clientY) / 2;

        const distX = imgCX - pinchCX;
        const distY = imgCY - pinchCY;

        t.translateX += distX * (ratio - 1);
        t.translateY += distY * (ratio - 1);
        t.scale = newScale;

        myCloudUpdateImageTransform();
    }
}

// Handles touch end event.
// Resets pinch/pan flags and handles swipe-to-dismiss release.
function myCloudOnTouchEnd(e) {
    const t = window.myCloudTransform;
    if (e.touches.length < 2) {
        t.pinching = false;
    }
    if (e.touches.length === 0) {
        if (t.dismissing) {
            const deltaY = Math.max(0, t.swipeDeltaY || 0);
            const overlay = document.getElementById('myCloudPreviewOverlay');
            const img = document.getElementById('myCloudPreviewImg');
            
            if (deltaY > window.innerHeight * 0.15) {
                if (overlay) {
                    const modal = overlay.querySelector('.myCloudModal.preview');
                    if (modal) {
                        modal.classList.add('swipe-closing');
                        if (img) img.style.transform = ''; // Hand over to CSS animation
                    }
                }
                myCloudClosePreview();
            } else {
                if (img) {
                    img.style.transition = 'transform 0.2s ease-out';
                    img.style.transform = '';
                    setTimeout(() => { 
                        if (img) {
                            img.style.transition = '';
                            myCloudUpdateImageTransform(false); // Restore normal positioning
                        }
                    }, 200);
                }
            }
            t.dismissing = false;
            t.swipeDeltaY = 0;
        } else if (t.panning) {
            t.panning = false;
            myCloudUpdateImageTransform(true);
        }
    }
}

// Handles touch start event.
// Initializes state for pan or pinch.
function myCloudOnTouchStart(e) {
    const t = window.myCloudTransform;
    const img = document.getElementById('myCloudPreviewImg');
    if (img) img.style.transition = 'none';

    if (typeof t.translateX === 'undefined') t.translateX = 0;
    if (typeof t.translateY === 'undefined') t.translateY = 0;

    if (e.touches.length === 1) {
		if (e.cancelable) e.preventDefault();
        if (t.scale <= 1.01) {
            t.dismissing = true;
            t.panning = false;
        } else {
            t.panning = true;
            t.dismissing = false;
        }
        t.pinching = false;
        t.startX = e.touches[0].clientX - t.translateX;
        t.startY = e.touches[0].clientY - t.translateY;
		t.startClientY = e.touches[0].clientY;
    }
    else if (e.touches.length === 2) {
        e.preventDefault();
        t.pinching = true;
        t.panning = false;
        
        const t1 = e.touches[0];
        const t2 = e.touches[1];
        t.startDist = Math.hypot(t1.clientX - t2.clientX, t1.clientY - t2.clientY);
        t.startScale = t.scale;
    }
}

// Handles mouse wheel events for zooming.
// Delegates to myCloudZoomAtPoint.
function myCloudOnWheel(e) {
    e.preventDefault();
    const delta = e.deltaY < 0 ? ZOOM_BUTTON_STEP : -ZOOM_BUTTON_STEP;
    myCloudZoomAtPoint(delta, e.clientX, e.clientY);
}

// Handles mouse down event for desktop panning.
// Starts tracking drag movement.
function myCloudOnMouseDown(e) {
    if (e.button !== 0) return;
    e.preventDefault();
    const t = window.myCloudTransform;
    t.panning = true;
    t.startX = e.clientX - t.translateX;
    t.startY = e.clientY - t.translateY;
    const img = document.getElementById('myCloudPreviewImg');
    if (img) img.style.transition = 'none';
}

// Closes the preview overlay with animation.
// Cleans up modal and restores focus.
function myCloudClosePreview() {
    const overlay = document.getElementById('myCloudPreviewOverlay');
    const modal = overlay ? overlay.querySelector('.myCloudModal') : null;
    
    if (!overlay || overlay.style.display === 'none') return;
	
	// [FIX] Clean up PDF listeners if they exist
    if (overlay.pdfResizeHandler) {
        window.removeEventListener('resize', overlay.pdfResizeHandler);
        delete overlay.pdfResizeHandler;
    }

    overlay.classList.add('closing');
    if (modal) modal.classList.add('closing');
	
        // Hide Filmstrip and EXIF immediately with the preview
        const strip = document.getElementById('myCloudFilmstrip');
        if (strip) { strip.style.transition = 'opacity 0.3s ease-out'; strip.style.opacity = '0'; }
        
        const exif = document.getElementById('myCloudExifModal');
        if (exif) { exif.style.transition = 'opacity 0.3s ease-out'; exif.style.opacity = '0'; }

    setTimeout(() => {
        overlay.style.display = 'none';
        overlay.innerHTML = ''; 
        overlay.classList.remove('closing'); 
        overlay.classList.remove('ui-hidden');
        if (modal) {
            modal.classList.remove('closing');
            modal.classList.remove('swipe-closing');
        }
        
        // [FIX] Restore focus logic supports Commander Mode
        if (myCloudState.isCommanderMode) {
            const side = myCloudState.commanderActive || 'left';
            const pane = document.querySelector(`.myCloud-commander-pane[data-side="${side}"]`);
            if (pane) {
                const content = pane.querySelector('.myCloud-commander-content');
                if (content) {
                    content.focus();
                }
            }
        } else {
            // Standard Mode
            const details = document.querySelector('.myCloudDetails');
            if (details) {
                details.focus();
                
            }
        }
    }, 680); 
}


// Navigates to the next/previous previewable item.
// Updates the preview content without closing the modal.
function myCloudNavigatePreview(direction) {
    const st = myCloudState;
    if (!st.previewPath) return;

    const searchModalOpen = document.getElementById('myCloudModal') && document.getElementById('myCloudModal').classList.contains('search-modal') && document.getElementById('myCloudModalOverlay').style.display !== 'none';
    const allItems = searchModalOpen ? (window.myCloudSearchItems || []) : myCloudGetSortedItems();
    const currentIndex = allItems.findIndex(i => i.name === st.previewPath);
    if (currentIndex === -1) return;

    let nextIndex = currentIndex;
    let found = null;
    let count = 0;
    
    while (count < allItems.length) {
        nextIndex += direction;

        if (nextIndex < 0 || nextIndex >= allItems.length) {
            break; 
        }

        const item = allItems[nextIndex];
        const realName = item.displayName ? item.displayName : item.name.split('/').pop().replace(/\.enc$/, '');
        const ext = realName.split('.').pop().toLowerCase();
        
        if (item.size !== 'DIR' && typeof previewExts !== 'undefined' && previewExts.includes(ext)) {
            found = item;
            break;
        }
        count++;
    }

    if (found) {
        const fName = found.displayName ? found.displayName : found.name.split('/').pop().replace(/\.enc$/, '');
        if (typeof myCloudDownloadFile === 'function') myCloudDownloadFile(found.name, fName, true);
    }
}


// Opens the text editor for the given file.
// Falls back to download if editing is not supported.
function myCloudEditFile(path) {
    if (myCloudUserRole === 'read-only' || typeof myCloudEditor_open !== 'function') { 
        myCloudDownloadFile(path, path.split('/').pop(), true); 
        return; 
    }

    myCloudShowLoading();
    const reqUrl = window.location.pathname;

    // [NEW] E2E Interception: Decrypt locally before opening
    if (typeof myCloudCrypto !== 'undefined' && myCloudCrypto.isDirEncrypted(path)) {
        const root = myCloudCrypto.getCryptoRoot(path);
        if (!myCloudCrypto.isDirUnlocked(root)) {
            myCloudHideLoading();
            myCloudAction_EncryptPrompt(root, true, () => myCloudEditFile(path));
            return;
        }
        
        const filename = path.split('/').pop();
        const fd = new URLSearchParams({ 
            myCloud_action: 'get_download_token', 
            myCloud_key: myCloudState.key, 
            myCloud_token: typeof myCloudCsrfToken !== 'undefined' ? myCloudCsrfToken : '', 
            path: path, 
            filename: filename, 
            preview: '0' 
        });
        
        fetch(reqUrl, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.status !== 'OK') throw new Error(res.msg || 'Token failed');
            
            // STRICTLY CLEAN URL: Only the pathname and the specific token.
            const dlUrl = reqUrl + '?myCloud_token=' + res.token;
            return fetch(dlUrl).then(r => r.blob());
        })
        .then(async encBlob => {
            const decBlob = await myCloudCrypto.decryptFile(root, encBlob);
            const text = await decBlob.text();
            myCloudHideLoading();
            myCloudEditor_open(path, text);
        })
        .catch(err => {
            myCloudHideLoading();
            myCloudShowAlert('Error', 'Failed to read/decrypt file: ' + err.message);
            console.error("Editor Fetch/Decrypt Error:", err);
        });
        return;
    }

    // Standard Fetch for non-encrypted files
    fetch(reqUrl, { 
        method: 'POST', 
        body: new URLSearchParams({ 
            myCloud_action: 'edit-fetch', 
            myCloud_key: myCloudState.key, 
            myCloud_token: typeof myCloudCsrfToken !== 'undefined' ? myCloudCsrfToken : '', 
            path: path 
        }) 
    })
    .then(myCloudCheckResponse)
    .then(resp => {
        myCloudHideLoading();
        if (resp.status === 'OK') myCloudEditor_open(path, resp.content);
        else if (resp.code === 'AUTH_REQUIRED' && typeof myCloudPromptAdminAuth === 'function') myCloudPromptAdminAuth(() => myCloudEditFile(path));
        else myCloudShowAlert('Error', resp.msg || 'Unknown');
    })
    .catch(err => {
        myCloudHideLoading();
        myCloudShowAlert('Error', 'Failed to read file. It might contain invalid characters or be too large.');
        console.error("Editor Fetch Error:", err);
    });
}


// Toggles between HD and SD preview images.
// Fetches new secure token and reloads image.
async function myCloudToggleQuality(btn, path) {
    const img = document.getElementById('myCloudPreviewImg');
    if (!img) return;

    const currentIsRaw = (btn.dataset.quality === 'raw');
    const targetIsPreview = currentIsRaw; 
    const cacheKey = targetIsPreview ? path + '_sd' : path + '_hd';
    const st = myCloudState;

    const performSwap = (url) => {
        img.dataset.keepZoom = 'true';
        const tempImg = new Image();
        tempImg.src = url;
        tempImg.decode().then(() => {
            if (!document.body.contains(img)) return;
            if (img.style.maxWidth === 'none' && img.naturalWidth > 0 && tempImg.naturalWidth > 0) {
                img.dataset.fixScaleRatio = (img.naturalWidth / tempImg.naturalWidth).toString();
            }
            img.src = url;
            updateToggleUI(btn, targetIsPreview);
        }).catch(() => {
            if (!document.body.contains(img)) return;
            img.src = url;
            updateToggleUI(btn, targetIsPreview);
        });
    };

    // Client-side Memory Resizer for Encrypted Files
    if (typeof myCloudCrypto !== 'undefined' && myCloudCrypto.isDirEncrypted(path)) {
        if (st.previewCache[cacheKey]) {
            performSwap(st.previewCache[cacheKey]);
            return;
        }

        myCloudCreateProgressUI('Resizing in Memory...');
        try {
            const hdUrl = st.previewCache[path + '_hd'] || img.src;

            if (targetIsPreview) {
                const tempImg = new Image();
                tempImg.onload = () => {
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d');
                    const maxDim = 1024;
                    let w = tempImg.width, h = tempImg.height;
                    
                    if (w > maxDim || h > maxDim) {
                        const ratio = Math.min(maxDim / w, maxDim / h);
                        w *= ratio; h *= ratio;
                    }
                    
                    canvas.width = w; canvas.height = h;
                    ctx.drawImage(tempImg, 0, 0, w, h);
                    
                    canvas.toBlob((blob) => {
                        const sdUrl = URL.createObjectURL(blob);
                        st.previewCache[cacheKey] = sdUrl;
                        performSwap(sdUrl);
                        myCloudCloseProgressUI();
                    }, 'image/jpeg', 0.7);
                };
                tempImg.onerror = () => myCloudCloseProgressUI();
                tempImg.src = hdUrl;
            } else {
                performSwap(hdUrl);
                myCloudCloseProgressUI();
            }
        } catch (e) {
            myCloudCloseProgressUI();
        }
        return;
    }

    // Standard Server-Side Toggle
    if (st.previewCache[cacheKey]) {
        performSwap(st.previewCache[cacheKey]);
        return;
    }
    
    const spinner = document.getElementById('myCloudLoadingSpinner');
    if (spinner) spinner.style.display = 'block';
    const reqUrl = window.location.pathname;
    
    try {
        const fd = new URLSearchParams({
            myCloud_action: 'get_download_token',
            myCloud_key: st.key,
            myCloud_token: typeof myCloudCsrfToken !== 'undefined' ? myCloudCsrfToken : '',
            path: path,
            filename: path.split('/').pop(),
            preview: targetIsPreview ? '1' : '0', 
            isZipContent: myCloudIsInsideZip(path) ? '1' : '0'
        });

        const resp = await fetch(reqUrl, { method: 'POST', body: fd }).then(r => r.json());

        if (resp.status === 'OK') {
            const newUrl = reqUrl + '?myCloud_token=' + resp.token;
            myCloudGetDecryptedUrl(path, newUrl).then(finalUrl => {
                st.previewCache[cacheKey] = finalUrl;
                performSwap(finalUrl);
            });
       } else {
            if (spinner) spinner.style.display = 'none';
        }
    } catch (e) {
        if (spinner) spinner.style.display = 'none';
    }
}


// Calculates the center point of the image container.
// Used for zoom calculations.
function getViewportCenter() {
    const container = document.getElementById('myCloudImageContainer');
    if (!container) return { x: window.innerWidth / 2, y: window.innerHeight / 2 };
    
    const rect = container.getBoundingClientRect();
    return {
        x: rect.left + rect.width / 2,
        y: rect.top + rect.height / 2
    };
}

// Checks if a file extension supports preview.
function myCloudIsPreviewable(ext) {
	return myCloudConfig.previewIcons.includes(ext.toLowerCase());
}

// Filters sorted items for only those that can be previewed.
function myCloudGetPreviewableItems() {
    return myCloudGetSortedItems().filter(item => {
        if (item.size === 'DIR') return false;
        const realName = item.displayName ? item.displayName : item.name.split('/').pop().replace(/\.enc$/, '');
        const ext = realName.split('.').pop().toLowerCase();
        return myCloudConfig.navigable.includes(ext);
    });
}

// Triggers native sharing sheet on mobile/supported browsers.
// Shares the current preview image as a file.
async function myCloudNativeShare() {
    const img = document.getElementById('myCloudPreviewImg');
    if (!img) return;

    const btn = document.getElementById('myCloudShareBtn');
    btn.style.opacity = '0.5';

    try {
        const response = await fetch(img.src);
        const blob = await response.blob();
        
        const path = myCloudState.previewPath;
        const filename = path ? path.split('/').pop() : 'image.jpg';
        
        const file = new File([blob], filename, { type: blob.type });

        if (navigator.canShare && navigator.canShare({ files: [file] })) {
            await navigator.share({
                files: [file],
                title: filename,
                text: 'Shared Picture'
            });
        } else {
            myCloudShowAlert("Error", "Sharing this file type is not supported by your device.");
        }
    } catch (e) {
        if (e.name !== 'AbortError') {
            console.error("Share failed", e);
            myCloudShowAlert("Share Error", e.message);
        }
    } finally {
        if(btn) btn.style.opacity = '1';
    }
}

// Checks browser support for Web Share API.
// Shows/hides the Share button accordingly.
function myCloudCheckShareSupport() {
    const btn = document.getElementById('myCloudShareBtn');
    if (!btn) return;
    
    if (navigator.share && navigator.canShare) {
        btn.style.display = 'flex';
    } else {
        btn.style.display = 'none';
    }
}

// --- FILMSTRIP OBSERVER (LAZY LOAD THUMBNAILS) ---
// --- FILMSTRIP OBSERVER (LAZY LOAD THUMBNAILS) ---
window.myCloudFilmstripObserver = new IntersectionObserver((entries, obs) => {
    const reqUrl = window.location.pathname; // Strict routing

    entries.forEach(entry => {
        if (entry.isIntersecting) {
            const img = entry.target;
            obs.unobserve(img);
            
            const path = img.dataset.path;
            const cacheKey = path + '_thumb';
            
            const isEnc = typeof myCloudCrypto !== 'undefined' && myCloudCrypto.isDirEncrypted(path);
            const fastUrl = isEnc ? null : window.myCloudGetFastThumbUrl(path);
            
            if (fastUrl) {
                myCloudState.previewCache[cacheKey] = fastUrl;
                img.onerror = () => {
                    const reqIsIcon = isEnc ? '0' : '1';
                    const fd = new URLSearchParams({ myCloud_action: 'get_download_token', myCloud_key: myCloudState.key, myCloud_token: typeof myCloudCsrfToken !== 'undefined' ? myCloudCsrfToken : '', path: path, filename: img.title, preview: '1', is_icon: reqIsIcon });
                    fetch(reqUrl, { method: 'POST', body: fd }).then(r=>r.json()).then(res => {
                        if (res.status === 'OK') {
                            const url = reqUrl + '?myCloud_token=' + res.token;
                            ceFetchPreviewBlob(url, path).then(blob => {
                                const objUrl = URL.createObjectURL(blob);
                                myCloudState.previewCache[cacheKey] = objUrl;
                                img.src = objUrl;
                            }).catch(e => console.error(e));
                       }
                    });
                };
                img.src = fastUrl;
                return;
            }
            
            // For E2E encrypted files, we MUST set is_icon to 0. 
            // The server cannot generate thumbnails of encrypted ciphertext!
            const isIconParam = isEnc ? '0' : '1';

            const fd = new URLSearchParams({ 
                myCloud_action: 'get_download_token', 
                myCloud_key: myCloudState.key, 
                myCloud_token: typeof myCloudCsrfToken !== 'undefined' ? myCloudCsrfToken : '', 
                path: path, 
                filename: img.title, 
                preview: '1', 
                is_icon: isIconParam 
            });
            
            fetch(reqUrl, { method: 'POST', body: fd }).then(r=>r.json()).then(res => {
                if (res.status === 'OK') {
                    const url = reqUrl + '?myCloud_token=' + res.token;
                    myCloudGetDecryptedUrl(path, url).then(finalUrl => {
                        myCloudState.previewCache[cacheKey] = finalUrl;
                        img.src = finalUrl;
                    });
                }
            });
        }
    });
}, { rootMargin: "300px" });


// --- FILMSTRIP LOGIC ---
window.myCloudToggleFilmstrip = function() {
    const devKey = myCloudGetCurrentDeviceKey();
    myCloudState.settings[devKey].showFilmstrip = !myCloudState.settings[devKey].showFilmstrip;
    myCloudSaveSettings();
    
    if (myCloudState.settings[devKey].showFilmstrip) {
        myCloudInitFilmstrip();
    } else {
        const existing = document.getElementById('myCloudFilmstrip');
        if (existing) existing.remove();
    }
    
    // Shift the submenu out of the way dynamically if it's currently open
    const sub = document.getElementById('myCloudPreviewSubmenu');
    if (sub && sub.style.display !== 'none') {
        sub.style.bottom = myCloudState.settings[devKey].showFilmstrip ? '170px' : '80px';
    }
};

window.myCloudInitFilmstrip = function() {
    const devKey = myCloudGetCurrentDeviceKey();
    if (!myCloudState.settings[devKey].showFilmstrip) return;
    
    const currentPath = myCloudState.previewPath;
    if (!currentPath) return;
    const parentDir = currentPath.substring(0, currentPath.lastIndexOf('/')) || '/';
    
    let strip = document.getElementById('myCloudFilmstrip');
    let needsRebuild = false;
    
    if (!strip) {
        strip = document.createElement('div');
        strip.id = 'myCloudFilmstrip';
        strip.className = 'myCloud-filmstrip';
        const overlay = document.getElementById('myCloudPreviewOverlay');
        // Attach to overlay to survive image navigation updates
        if (overlay) overlay.appendChild(strip); 
        needsRebuild = true;
    } else if (strip.dataset.parentDir !== parentDir) {
        needsRebuild = true;
    }
    
    if (needsRebuild) {
        strip.dataset.parentDir = parentDir;
        strip.innerHTML = '';
        
        // Safely gather sibling images (Decodes Vault .enc extensions)
        const images = (myCloudState.allItems || []).filter(i => {
            if (i.size === 'DIR') return false;
            const p = i.name.substring(0, i.name.lastIndexOf('/')) || '/';
            if (p !== parentDir) return false;
            
            let realName = i.displayName || i.name.split('/').pop();
            if (realName.endsWith('.enc')) realName = realName.replace(/\.enc$/, '');
            realName = realName.replace(/^[🔓🔒 ️]\s*/, ''); // Strip visual locks
            
            const ext = realName.split('.').pop().toLowerCase();
            return typeof imageExts !== 'undefined' && imageExts.includes(ext);
        });
        
        images.forEach(imgData => {
            const img = document.createElement('img');
            
            let realName = imgData.displayName || imgData.name.split('/').pop();
            if (realName.endsWith('.enc')) realName = realName.replace(/\.enc$/, '');
            realName = realName.replace(/^[🔓🔒 ️]\s*/, '');
            
            img.title = realName;
            img.dataset.path = imgData.name;
            
            if (imgData.name === currentPath) img.classList.add('active');
            
            const cacheKey = imgData.name + '_thumb';
            if (myCloudState.previewCache[cacheKey]) {
                img.src = myCloudState.previewCache[cacheKey];
            } else {
                img.style.backgroundColor = 'var(--gray-90)';
                window.myCloudFilmstripObserver.observe(img);
            }
            
            img.onclick = () => { myCloudDownloadFile(imgData.name, img.title, true); };
            strip.appendChild(img);
        });
    } else {
        // Just update active class
        const oldActive = strip.querySelector('.active');
        if (oldActive) oldActive.classList.remove('active');
        
        const newActive = strip.querySelector(`img[data-path="${CSS.escape(currentPath)}"]`);
        if (newActive) newActive.classList.add('active');
    }
    
    // Auto-scroll to active cleanly
    setTimeout(() => {
        const activeImg = strip.querySelector('.active');
        if (activeImg) {
            if (needsRebuild) {
                // Instantly center on first load
                activeImg.scrollIntoView({ behavior: 'auto', inline: 'center', block: 'nearest' });
            } else {
                // Always smooth scroll to center the active image for a pleasant carousel effect
                activeImg.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
            }
        }
    }, needsRebuild ? 100 : 10);
};


// --- EXIF LOGIC ---
window.myCloudShowExif = async function() {
    const path = myCloudState.previewPath;
    if (!path) return;
    
    let modal = document.getElementById('myCloudExifModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'myCloudExifModal';
        modal.className = 'myCloud-exif-modal';
        const previewModal = document.querySelector('#myCloudPreviewOverlay .myCloudModal.preview');
        if (previewModal) previewModal.appendChild(modal);
    }
    
    if (modal.style.display === 'block') {
        modal.style.display = 'none';
        return;
    }
    
    modal.innerHTML = '<div class="myCloud-spinner"></div> Loading EXIF...';
    modal.style.display = 'block';

    // Client-side EXIF Extractor for Encrypted Files
    if (typeof myCloudCrypto !== 'undefined' && myCloudCrypto.isDirEncrypted(path)) {
        try {
            if (typeof EXIF === 'undefined') {
                await myCloudLoadJS('https://cdn.jsdelivr.net/npm/exif-js');
            }
            
            const cacheKey = path + '_hd';
            let blobUrl = myCloudState.previewCache[cacheKey] || myCloudState.previewCache[path + '_sd'];
            if (!blobUrl) throw new Error("Image not loaded in memory.");
            
            const blob = await fetch(blobUrl).then(r => r.blob());
            
            EXIF.getData(blob, function() {
                const tags = EXIF.getAllTags(this);
                if (tags && Object.keys(tags).length > 0) {
                    let html = '<div style="font-weight:bold; margin-bottom:10px; padding-bottom:5px; border-bottom:1px solid #444;">' + (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.metadata ? myCloud_LANG.metadata : 'Metadata') + '</div>';
                    const data = {};
                    
                    if (blob.size) data['Size'] = typeof myCloudFormatBytes === 'function' ? myCloudFormatBytes(blob.size) : blob.size;
                    if (tags.PixelXDimension && tags.PixelYDimension) data['Dimensions'] = tags.PixelXDimension + ' x ' + tags.PixelYDimension + ' px';
                    if (tags.Make) data['Camera'] = (tags.Make + ' ' + (tags.Model || '')).trim();
                    if (tags.ExposureTime) data['Exposure'] = (tags.ExposureTime.numerator / tags.ExposureTime.denominator).toFixed(4) + 's';
                    if (tags.FNumber) data['Aperture'] = 'f/' + (tags.FNumber.numerator / tags.FNumber.denominator).toFixed(1);
                    if (tags.ISOSpeedRatings) data['ISO'] = tags.ISOSpeedRatings;
                    if (tags.DateTimeOriginal) data['Date Taken'] = tags.DateTimeOriginal;
                    
                    if (Object.keys(data).length > 0) {
                        for (const [key, value] of Object.entries(data)) {
                             const safeKey = typeof myCloudEscapeHtml === 'function' ? myCloudEscapeHtml(key) : key.replace(/</g, "&lt;");
                             const safeVal = typeof myCloudEscapeHtml === 'function' ? myCloudEscapeHtml(String(value)) : String(value).replace(/</g, "&lt;");
                             html += '<div class="myCloud-exif-row"><span>' + safeKey + '</span><span>' + safeVal + '</span></div>';
                        }
                        modal.innerHTML = html;
                    } else {
                        modal.innerHTML = '<div style="padding:10px;">' + (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.exif_none ? myCloud_LANG.exif_none : 'No EXIF data found.') + '</div>';
                    }
                } else {
                    modal.innerHTML = '<div style="padding:10px;">' + (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.exif_none ? myCloud_LANG.exif_none : 'No EXIF data found.') + '</div>';
                }
            });
        } catch(e) {
            modal.innerHTML = '<div style="padding:10px; color:#ff6b6b;">Error reading local metadata.</div>';
        }
        return;
    }
    
    // Standard Server-side EXIF Extractor
    const fd = new URLSearchParams({ myCloud_action: 'get_exif', myCloud_key: myCloudState.key, myCloud_token: typeof myCloudCsrfToken !== 'undefined' ? myCloudCsrfToken : '', path: path });
    fetch(window.location.pathname, { method: 'POST', body: fd }).then(r=>r.json()).then(res => {
        if (res.status === 'OK' && res.data) {
            let html = '<div style="font-weight:bold; margin-bottom:10px; padding-bottom:5px; border-bottom:1px solid #444;">' + (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.metadata ? myCloud_LANG.metadata : 'Metadata') + '</div>';
            for (const [key, value] of Object.entries(res.data)) {
                 const safeKey = typeof myCloudEscapeHtml === 'function' ? myCloudEscapeHtml(key) : key.replace(/</g, "&lt;");
                 const safeVal = typeof myCloudEscapeHtml === 'function' ? myCloudEscapeHtml(String(value)) : String(value).replace(/</g, "&lt;");
                 html += '<div class="myCloud-exif-row"><span>' + safeKey + '</span><span>' + safeVal + '</span></div>';
            }
            modal.innerHTML = html;
        } else {
            modal.innerHTML = '<div style="padding:10px;">' + (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.exif_none ? myCloud_LANG.exif_none : 'No EXIF data found.') + '</div>';
        }
    }).catch(() => {
        modal.innerHTML = '<div style="padding:10px; color:#ff6b6b;">Error reading metadata.</div>';
    });
};

</script>