<?php
/**
 * ============================================================================
 * MODULE: Document Management View
 * ============================================================================
 * Handles the specific DOM layout, iframe integration, and UI adaptations 
 * required when launching the collaborative document management environment.
 * NOTE: Executed exclusively by the client browser.
 * 
 * THIS IS PART OF THE PSEUDO "JS FILE" - SO THE NO PHP CODE IN THIS FILE!
 */

?><script>

function myCloudToggleOffice(forceState) {
    const st = myCloudState;
    const devKey = typeof myCloudGetCurrentDeviceKey === 'function' ? myCloudGetCurrentDeviceKey() : 'desktop';
	
    // Guard against starting in Gallery Mode
    if (st.interface === 'gallery') {
        st.isOfficeMode = false;
        return;
    }
    
    const nextState = typeof forceState !== 'undefined' ? forceState : !st.isOfficeMode;
    if (st.isOfficeMode === nextState) return; 

    // Mutually exclusive with Commander Mode
    if (nextState && st.isCommanderMode) {
        if (typeof myCloudToggleCommander === 'function') myCloudToggleCommander(); 
    }

        // Mutually exclusive with Gallery Mode
        if (nextState && st.interface === 'gallery') {
            st.interface = 'grid'; // Force switch to Grid View
            if (st.settings && st.settings[devKey]) st.settings[devKey].interface = 'grid';
        }

    st.isOfficeMode = nextState;
    
    if (st.settings && st.settings[devKey]) {
        st.settings[devKey].isOfficeMode = st.isOfficeMode;
        if (typeof myCloudSaveSettings === 'function') myCloudSaveSettings();
    }

    const body = document.querySelector('.myCloudBody');
    const container = document.getElementById('myCloudContainer');
	const tree = document.querySelector('.myCloudTree') || document.getElementById('myCloudTree');

    if (st.isOfficeMode) {
        body.classList.add('office-mode');
        container.classList.add('ce-no-hover-menu'); 
        myCloudRenderOfficeLayout();
            // Apply separate Tree width for Office Mode
            if (tree && st.settings && st.settings[devKey]) {
                if (!st.normalTreeWidthCache) st.normalTreeWidthCache = st.settings[devKey].treeWidth;
                const officeWidth = st.settings[devKey].officeTreeWidth || st.normalTreeWidthCache || 250;
                tree.style.width = officeWidth + 'px';
                tree.style.flex = 'none';
            }
    } else {
        body.classList.remove('office-mode');
        if (st.settings && st.settings[devKey] && st.settings[devKey].showHoverMenu) {
            container.classList.remove('ce-no-hover-menu');
        }
        
        const pane = document.getElementById('myCloudPreviewPane');
        const resizer = document.getElementById('myCloudOfficeResizer');
        if (pane) pane.remove();
        if (resizer) resizer.remove();
            // Restore normal Tree width
            if (tree && st.settings && st.settings[devKey]) {
                const normalWidth = st.normalTreeWidthCache || st.settings[devKey].treeWidth || 250;
                tree.style.width = normalWidth + 'px';
                tree.style.flex = 'none';
            }
    }
    
    if (typeof myCloudUpdateToolbarState === 'function') myCloudUpdateToolbarState();
    if (typeof myCloudRenderUI === 'function') myCloudRenderUI(); 
}

function myCloudRenderOfficeLayout() {
    const st = myCloudState;
	

    // 1. HARD BLOCK: Never allow the pane to exist in Gallery or Commander mode
    if (st.isCommanderMode || st.interface === 'gallery') {
        st.isOfficeMode = false;
        document.querySelector('.myCloudBody')?.classList.remove('office-mode');
        document.getElementById('myCloudPreviewPane')?.remove();
        document.getElementById('myCloudOfficeResizer')?.remove();
        return;
    }

    if (!st.isOfficeMode) return;

    const body = document.querySelector('.myCloudBody');
    if (!body) return;

    let resizer = document.getElementById('myCloudOfficeResizer');
    let pane = document.getElementById('myCloudPreviewPane');

    if (!resizer) {
        resizer = document.createElement('div');
        resizer.id = 'myCloudOfficeResizer';
        resizer.className = 'myCloudResizer preview-resizer';
    }

    if (!pane) {
        pane = document.createElement('div');
        pane.id = 'myCloudPreviewPane';
        pane.className = 'myCloudPreviewPane';
    }

    if (!resizer.parentNode) body.appendChild(resizer);
    if (!pane.parentNode) body.appendChild(pane);

    myCloudInitOfficeResizer();

    const devKey = typeof myCloudGetCurrentDeviceKey === 'function' ? myCloudGetCurrentDeviceKey() : 'desktop';
    let savedWidth = 400;
    if (st.settings && st.settings[devKey] && st.settings[devKey].officePreviewWidth) {
        savedWidth = st.settings[devKey].officePreviewWidth;
    }
    
    if (savedWidth < 200) savedWidth = 200;
    if (savedWidth > window.innerWidth * 0.6) savedWidth = window.innerWidth * 0.6;

    pane.style.width = savedWidth + 'px';
    pane.style.flex = 'none';

    myCloudUpdateOfficePreview();
}

function myCloudInitOfficeResizer() {
    const resizer = document.getElementById('myCloudOfficeResizer');
    const previewPane = document.getElementById('myCloudPreviewPane');
    const container = document.querySelector('.myCloudBody');

    if (!resizer || !previewPane || !container) return;
    if (resizer.dataset.initialized) return;
    resizer.dataset.initialized = 'true';

    let startX = 0;
    let startWidth = 0;

    resizer.addEventListener('mousedown', (e) => {
        e.preventDefault();
        startX = e.pageX;
        startWidth = previewPane.offsetWidth;
        
        // Prevent iframes from stealing the mouse
        previewPane.style.pointerEvents = 'none';
        document.body.style.userSelect = 'none';

        const isRtl = document.getElementById('myCloudContainer').getAttribute('dir') === 'rtl';

        const onMouseMove = (moveEvent) => {
            const delta = moveEvent.pageX - startX;
            let newWidth = isRtl ? startWidth + delta : startWidth - delta;
            
            if (newWidth < 200) newWidth = 200;
            if (newWidth > container.offsetWidth * 0.7) newWidth = container.offsetWidth * 0.7;
            
            previewPane.style.width = newWidth + 'px';
            previewPane.style.flex = 'none';
        };

        const onMouseUp = () => {
            document.removeEventListener('mousemove', onMouseMove);
            document.removeEventListener('mouseup', onMouseUp);
            document.body.style.cursor = 'default';
            document.body.style.userSelect = '';
            previewPane.style.pointerEvents = '';
            resizer.classList.remove('active');

            const devKey = typeof myCloudGetCurrentDeviceKey === 'function' ? myCloudGetCurrentDeviceKey() : 'desktop';
            if (myCloudState.settings && myCloudState.settings[devKey]) {
                myCloudState.settings[devKey].officePreviewWidth = Math.round(previewPane.offsetWidth);
                if (typeof myCloudSaveSettings === 'function') myCloudSaveSettings();
            }
        };

        document.addEventListener('mousemove', onMouseMove);
        document.addEventListener('mouseup', onMouseUp);
        document.body.style.cursor = 'col-resize';
        resizer.classList.add('active');
    });
}

async function myCloudUpdateOfficePreview() {
    const pane = document.getElementById('myCloudPreviewPane');
    const st = myCloudState;
    if (!pane || !st.isOfficeMode) return;

    const sel = st.selectedFiles;
	
   if (sel.length !== 1) {
		delete pane.dataset.currentPreviewPath;
        pane.innerHTML = '<div class="ce-office-empty"><div style="opacity:0.3; margin-bottom:15px;"><svg viewBox="0 0 24 24" width="60" height="60" fill="currentColor"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 14h-3v3h-2v-3H8v-2h3v-3h2v3h3v2zm-3-7V3.5L18.5 9H13z"/></svg></div>' + myCloud_LANG.select_one_file + '</div>';
        return;
    }

    const path = sel[0];
	
    // Prevent redundant rebuilds when right-clicking the currently selected file
    if (pane.dataset.currentPreviewPath === path) return;
    pane.dataset.currentPreviewPath = path;
    
 
    const filename = path.split('/').pop();
    const item = st.allItems.find(i => i.name === path);
    
    if (!item || item.size === 'DIR') {
		delete pane.dataset.currentPreviewPath;
        pane.innerHTML = '<div class="ce-office-empty">' + myCloud_LANG.folder_no_prev + '</div>';
        return;
    }

    const ext = filename.split('.').pop().toLowerCase();

    const isImage = (typeof imageExts !== 'undefined' && imageExts.includes(ext));
    const isVideo = (typeof videoExts !== 'undefined' && videoExts.includes(ext));
    const isAudio = (typeof audioExts !== 'undefined' && audioExts.includes(ext));
    const isCode = (typeof editExts !== 'undefined' && editExts.includes(ext)) || ext === 'txt';
    const isPdf = ext === 'pdf';
    const isDocxXlsx = ['docx', 'xlsx'].includes(ext);
	const isEpub = ext === 'epub';

    if (!isImage && !isVideo && !isAudio && !isCode && !isPdf && !isDocxXlsx && !isEpub) {
         pane.innerHTML = '<div class="ce-office-empty">' + myCloud_LANG.preview_not_sup + ' .' + ext + '</div>';
         return;
    }

    // Size limit warning
    const size = parseInt(item.size);
    const devKey = typeof myCloudGetCurrentDeviceKey === 'function' ? myCloudGetCurrentDeviceKey() : 'desktop';
    const config = st.settings ? st.settings[devKey] : null;

    if (!isImage && config && config.warnLargePreview && size > (typeof myCloudMaxPreviewSize !== 'undefined' ? myCloudMaxPreviewSize : 50*1024*1024)) {
        pane.innerHTML = 
            '<div class="ce-office-empty">' + 
                '<div style="color:var(--warning); margin-bottom:10px;"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg></div>' +
                myCloud_LANG.large_file_msg + '<br>' + 
                '<button onclick="_cloudExProceedInlineDownload(\'' + path.replace(/'/g, "\\'") + '\', \'' + filename.replace(/'/g, "\\'") + '\')" style="margin-top:15px; padding:6px 12px; background:var(--accent-primary); color:#fff; border:none; border-radius:4px; cursor:pointer;">' + myCloud_LANG.preview_anyway + '</button>' +
            '</div>';
        return;
    }

    _cloudExProceedInlineDownload(path, filename);
}

async function _cloudExProceedInlineDownload(path, filename) {
    const pane = document.getElementById('myCloudPreviewPane');
    if (!pane) return;

    pane.innerHTML = '<div class="ce-office-empty"><div class="myCloud-spinner dark"></div></div>';

    const st = myCloudState;
    const isInsideZip = typeof myCloudIsInsideZip === 'function' ? myCloudIsInsideZip(path) : false;
    const cacheKey = path + '_sd';
    let url = st.previewCache[cacheKey];

    if (!url) {
        try {
            const fd = new URLSearchParams({
                myCloud_action: 'get_download_token',
                myCloud_key: st.key,
                myCloud_token: typeof myCloudCsrfToken !== 'undefined' ? myCloudCsrfToken : '',
                path: path,
                filename: filename,
                preview: '1',
                isZipContent: isInsideZip ? '1' : '0'
            });

            const res = await fetch('', { method: 'POST', body: fd }).then(r => r.json());

            if (res.status === 'OK') {
                url = '?myCloud_token=' + res.token;
                st.previewCache[cacheKey] = url;
            } else {
                pane.innerHTML = '<div class="ce-office-empty"><div style="color:var(--danger);">' + (res.msg || 'Error loading preview') + '</div></div>';
                return;
            }
        } catch (e) {
            pane.innerHTML = '<div class="ce-office-empty"><div style="color:var(--danger);">Network Error</div></div>';
            return;
        }
    }

    const ext = filename.split('.').pop().toLowerCase();
    myCloudRenderInlineHtml(pane, url, filename, ext);
}

function myCloudRenderInlineHtml(body, url, filename, ext) {
    // Only inject the content wrapper - NO title bar.
    body.innerHTML = '<div id="ceInlineContentWrap" style="flex:1; width:100%; height:100%; position:relative; overflow:hidden; display:flex; align-items:center; justify-content:center; background: var(--gray-05);"></div>';
    const wrap = body.querySelector('#ceInlineContentWrap');

    const isImage = (typeof imageExts !== 'undefined' && imageExts.includes(ext));
    const isVideo = (typeof videoExts !== 'undefined' && videoExts.includes(ext));
    const isAudio = (typeof audioExts !== 'undefined' && audioExts.includes(ext));
    const isCode = (typeof editExts !== 'undefined' && editExts.includes(ext)) || ext === 'txt';
    const isPdf = ext === 'pdf';
	const isEpub = ext === 'epub';

    // 1. IMAGE
    if (isImage) {
        wrap.innerHTML = 
            '<div id="myCloudInlineSpinner" class="myCloud-spinner dark" style="position:absolute; z-index:1;"></div>' +
            '<div style="width:100%; height:100%; overflow:auto; display:flex; align-items:center; justify-content:center;">' +
                '<img src="' + url + '" style="max-width:100%; max-height:100%; object-fit:contain; transition: transform 0.2s;" id="ceInlineImg">' +
            '</div>' +
            '<div style="position:absolute; bottom:15px; left:50%; transform:translateX(-50%); display:flex; gap:6px; background:rgba(0,0,0,0.6); padding:6px 12px; border-radius:20px; z-index:5;">' +
                '<button onclick="ceInlineZoom(-0.25)" style="background:transparent; border:1px solid rgba(255,255,255,0.4); color:#fff; width:30px; height:30px; border-radius:50%; cursor:pointer;">−</button>' +
                '<button onclick="ceInlineZoom(0)" style="background:transparent; border:1px solid rgba(255,255,255,0.4); color:#fff; width:30px; height:30px; border-radius:50%; cursor:pointer;"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6"/><path d="M9 21H3v-6"/><path d="M21 3l-7 7"/><path d="M3 21l7-7"/></svg></button>' +
                '<button onclick="ceInlineZoom(0.25)" style="background:transparent; border:1px solid rgba(255,255,255,0.4); color:#fff; width:30px; height:30px; border-radius:50%; cursor:pointer;">+</button>' +
            '</div>';

        const img = wrap.querySelector('#ceInlineImg');
        const spin = wrap.querySelector('#myCloudInlineSpinner');
        img.onload = () => { if(spin) spin.style.display = 'none'; };
        
        let currentZoom = 1;
        window.ceInlineZoom = (dir) => {
            if (dir === 0) currentZoom = 1;
            else currentZoom = Math.max(0.2, Math.min(5.0, currentZoom + dir));
            
            if (currentZoom === 1) {
                img.style.maxWidth = '100%';
                img.style.maxHeight = '100%';
                img.style.width = 'auto';
                img.style.height = 'auto';
                img.style.transform = 'none';
            } else {
                img.style.maxWidth = 'none';
                img.style.maxHeight = 'none';
                img.style.transform = `scale(${currentZoom})`;
            }
        };
    } 
    // 2. VIDEO
    else if (isVideo) {
        wrap.style.background = '#000';
        wrap.innerHTML = '<video controls autoplay style="width:100%; height:100%; outline:none;"><source src="' + url + '"></video>';
    } 
    // 3. AUDIO
    else if (isAudio) {
        wrap.style.background = '#222';
        wrap.innerHTML = 
            '<div style="text-align:center; width:90%;">' +
                '<div style="margin-bottom:20px; opacity:0.8;"><svg viewBox="0 0 24 24" width="60" height="60" fill="#fff"><path d="M12 3v9.28c-.47-.17-.97-.28-1.5-.28C8.01 12 6 14.01 6 16.5S8.01 21 10.5 21c2.31 0 4.16-1.75 4.45-4H15V6h4V3h-7z"/></svg></div>' +
                '<audio controls autoplay style="width:100%; outline:none;"><source src="' + url + '"></audio>' +
            '</div>';
    } 
    // 4. PDF
    else if (isPdf) {
        const isMobile = (typeof myCloudDevice !== 'undefined' && (myCloudDevice.category === 'mobile' || myCloudDevice.category === 'tablet'));

        if (!isMobile) {
            wrap.style.background = '#323639';
            wrap.innerHTML = '<iframe src="' + url + '#view=FitH&toolbar=1&navpanes=0" width="100%" height="100%" style="border:none; display:block;"></iframe>';
        } else {
            wrap.style.background = '#323639';
            wrap.style.display = 'flex';
            wrap.style.flexDirection = 'column';
            wrap.style.overflow = 'hidden';
            wrap.style.padding = '0';

            wrap.innerHTML = 
                '<div class="myCloud-loading-container" style="color:#fff; display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%;">' +
                    '<div class="myCloud-spinner"></div>' +
                    '<div style="margin-top:15px;">Loading PDF Engine...</div>' +
                '</div>';

            const initInlinePdf = async () => {
                let lib = window.pdfjsLib || window['pdfjs-dist/build/pdf'];
                
                if (!lib) {
                    try { if (typeof myCloudLoadJS === 'function') await myCloudLoadJS('/script/pdf.js'); } catch(e) {}
                    lib = window.pdfjsLib || window['pdfjs-dist/build/pdf'];
                }
                
                if (!lib) {
                    console.warn("Local PDF.js failed or missing global export. Using CDN fallback.");
                    if (typeof myCloudLoadJS === 'function') await myCloudLoadJS('https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js');
                    lib = window.pdfjsLib;
                    if (lib) lib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js';
                }
                
                if (!lib) throw new Error("PDF Engine failed to initialize from both local and CDN sources.");
                
                startInlineMobileViewer(lib);
            };
            
            initInlinePdf().catch(err => {
                console.error(err);
                wrap.innerHTML = '<div style="color:#fff;padding:20px;text-align:center;">Could not start PDF Engine.<br><small style="color:#ff6b6b;">' + (err.message || err) + '</small><br><br><a href="' + url + '" style="color:#4da3ff;text-decoration:underline;">Download PDF</a></div>';
            });

            function startInlineMobileViewer(lib) {
                if (!lib.GlobalWorkerOptions.workerSrc) {
                    lib.GlobalWorkerOptions.workerSrc = '/script/pdf.worker.js';
                }

                const iconBook = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm16-4H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H8V4h12v12z"/></svg>';
                const iconClose = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>';

                if (!document.getElementById('ceInlinePdfStyles')) {
                    const style = document.createElement('style');
                    style.id = 'ceInlinePdfStyles';
                    style.innerHTML = 
                        '.ce-inline-pdf-wrapper { width:100%; height:100%; position:relative; background:#202020; overflow:hidden; }' +
                        '.ce-inline-pdf-scroller { width:100%; height:100%; overflow:auto; box-sizing: border-box; -webkit-overflow-scrolling:touch; display:block; }' +
                        '#ceInlinePdfScrollerInner { min-width: 100%; width: min-content; padding: 10px; padding-bottom: 90px; box-sizing: border-box; transform-origin: 0 0; will-change: transform; }' +
                        '.ce-inline-pdf-page-container { margin: 0 auto 15px auto; position: relative; background: #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.5); min-height: 200px; }' +
                        '.ce-inline-pdf-page-canvas { display: block; width: 100%; height: 100%; }' +
                        '.ce-inline-pdf-toolbar { position: absolute; bottom: 30px; left: 50%; transform: translateX(-50%); display: flex; align-items: center; gap: 10px; background: rgba(35, 35, 35, 0.9); backdrop-filter: blur(10px); padding: 8px 16px; border-radius: 50px; border: 1px solid rgba(255,255,255,0.15); box-shadow: 0 8px 32px rgba(0,0,0,0.4); z-index: 100; white-space: nowrap; }' +
                        '.ce-inline-pdf-btn { background: transparent; border: 1px solid rgba(255,255,255,0.2); color: #fff; width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0; font-size: 18px; display: flex; align-items: center; justify-content: center; cursor: pointer; padding: 0; margin:0; }' +
                        '.ce-inline-pdf-btn:active { background: rgba(255,255,255,0.2); transform: scale(0.95); }' +
                        '.ce-inline-pdf-btn svg { width: 18px; height: 18px; }' +
                        '.ce-inline-pdf-counter { color: rgba(255,255,255,0.9); font-size: 13px; font-weight: 500; font-family: sans-serif; min-width: 60px; text-align: center; font-variant-numeric: tabular-nums; padding: 4px 8px; border-radius: 12px; transition: background 0.2s; cursor:pointer; }' +
                        '.ce-inline-pdf-counter:active { background: rgba(255,255,255,0.1); }' +
                        '.ce-inline-pdf-sep { width:1px; height:20px; background:rgba(255,255,255,0.2); }' +
                        '#ceInlinePdfBookmarkDrawer { position: absolute; bottom: -60vh; left: 10px; right: 10px; height: 50vh; background: rgba(30, 30, 30, 0.95); backdrop-filter: blur(15px); border-radius: 16px 16px 0 0; border: 1px solid rgba(255,255,255,0.1); transition: bottom 0.3s cubic-bezier(0.1, 0.9, 0.2, 1); display: flex; flex-direction: column; z-index: 90; }' +
                        '#ceInlinePdfBookmarkDrawer.active { bottom: 0; }' +
                        '.ce-inline-pdf-drawer-header { padding: 12px 16px; border-bottom: 1px solid rgba(255,255,255,0.1); display: flex; justify-content: space-between; align-items: center; color: #fff; font-weight: bold; }' +
                        '.ce-inline-pdf-drawer-content { flex: 1; overflow-y: auto; padding: 10px; }' +
                        '.ce-inline-pdf-bookmark-item { display: block; padding: 12px; color: #ddd; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 14px; text-decoration: none; cursor:pointer; }' +
                        '.ce-inline-pdf-bookmark-item:active { background: rgba(255,255,255,0.1); }' +
                        '#ceInlinePdfJumpPopup { position: absolute; bottom: 85px; left: 50%; transform: translateX(-50%) scale(0.9); background: #333; padding: 10px; border-radius: 8px; display: none; box-shadow: 0 4px 20px rgba(0,0,0,0.5); opacity: 0; transition: all 0.2s; z-index: 110; align-items: center; gap: 8px; border: 1px solid #555; }' +
                        '#ceInlinePdfJumpPopup.active { display: flex; transform: translateX(-50%) scale(1); opacity: 1; }' +
                        '#ceInlinePdfJumpInput { width: 50px; background: #222; border: 1px solid #555; color: #fff; padding: 6px; border-radius: 4px; text-align: center; }' +
                        '.ce-inline-pdf-go-btn { background: #0078d4; color: #fff; border: none; padding: 6px 12px; border-radius: 4px; font-weight: bold; cursor:pointer; }';
                    document.head.appendChild(style);
                }

                wrap.innerHTML = 
                '<div class="ce-inline-pdf-wrapper">' +
                    '<div id="ceInlinePdfScroller" class="ce-inline-pdf-scroller">' +
                        '<div id="ceInlinePdfScrollerInner" style="transition: transform 0.1s;"></div>' +
                    '</div>' +
                    
                    '<div id="ceInlinePdfBookmarkDrawer">' +
                        '<div class="ce-inline-pdf-drawer-header">' +
                            '<span>Bookmarks</span>' +
                            '<div onclick="window.ceInlinePdfToggleBookmarks(false)" style="padding:5px; cursor:pointer;">' + iconClose + '</div>' +
                        '</div>' +
                        '<div class="ce-inline-pdf-drawer-content" id="ceInlinePdfBookmarkList"></div>' +
                    '</div>' +

                    '<div id="ceInlinePdfJumpPopup">' +
                        '<input type="number" id="ceInlinePdfJumpInput" min="1">' +
                        '<button class="ce-inline-pdf-go-btn" onclick="window.ceInlinePdfGo()">Go</button>' +
                    '</div>' +
                    
                    '<div class="ce-inline-pdf-toolbar">' +
                        '<button id="ceInlineBtnPdfBookmarks" class="ce-inline-pdf-btn" onclick="window.ceInlinePdfToggleBookmarks(true)" style="display:none;">' + iconBook + '</button>' +
                        '<div id="ceInlineSepPdfBookmarks" class="ce-inline-pdf-sep" style="display:none;"></div>' +
                        
                        '<button class="ce-inline-pdf-btn" onclick="window.ceInlinePdfZoom(-0.25)">−</button>' +
                        '<span id="ceInlinePdfPageIndicator" class="ce-inline-pdf-counter" onclick="window.ceInlinePdfShowJump()">...</span>' +
                        '<button class="ce-inline-pdf-btn" onclick="window.ceInlinePdfZoom(0.25)">+</button>' +
                    '</div>' +
                '</div>';

                const scroller = document.getElementById('ceInlinePdfScroller');
                const scrollerInner = document.getElementById('ceInlinePdfScrollerInner');
                const indicator = document.getElementById('ceInlinePdfPageIndicator');
                const bookmarkDrawer = document.getElementById('ceInlinePdfBookmarkDrawer');
                const jumpPopup = document.getElementById('ceInlinePdfJumpPopup');
                const jumpInput = document.getElementById('ceInlinePdfJumpInput');
                
                const state = {
                    pdf: null, zoom: 1.0, pageMap: new Map(), observer: null, currentPage: 1, renderTasks: {}
                };

                const renderPageIntoContainer = (pageNum, containerDiv) => {
                    const status = state.pageMap.get(pageNum);
                    if (status === 'rendering' || status === 'done') return;
                    state.pageMap.set(pageNum, 'rendering');
                    
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
                        canvas.className = 'ce-inline-pdf-page-canvas';
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
                            } else {
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
                            div.className = 'ce-inline-pdf-page-container';
                            div.dataset.page = i;
                            div.id = 'inline_pdf_page_' + i;
                            div.style.width = initialWidth + 'px';
                            div.style.height = initialHeight + 'px';
                            div.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#ccc;font-size:20px;">' + i + '</div>';
                            scrollerInner.appendChild(div);
                            state.observer.observe(div);
                        }
                        
                        indicator.textContent = state.currentPage + ' / ' + state.pdf.numPages;
                        
                        if (state.pendingJump) {
                            setTimeout(() => window.ceInlinePdfJumpTo(state.pendingJump), 100);
                            state.pendingJump = null;
                        }
                    });
                };

                const applyZoom = (newZoom, viewportAnchorX = null, viewportAnchorY = null) => {
                    const centerX = viewportAnchorX !== null ? viewportAnchorX : scroller.clientWidth / 2;
                    const centerY = viewportAnchorY !== null ? viewportAnchorY : scroller.clientHeight / 2;
                    
                    const docX = scroller.scrollLeft + centerX;
                    const docY = scroller.scrollTop + centerY;
            
                    const firstPage = document.getElementById('inline_pdf_page_1');
                    if (!firstPage) { layoutPages(); return; }
            
                    const pageRatio = firstPage.offsetHeight / firstPage.offsetWidth;
                    const availWidth = scroller.clientWidth - 20;
                    const newWidth = Math.floor(availWidth * newZoom);
                    const newHeight = Math.floor(newWidth * pageRatio);

                    let targetPage = null;
                    let relX = 0;
                    let relY = 0;
                    const pages = Array.from(document.querySelectorAll('.ce-inline-pdf-page-container'));
                    
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

                window.ceInlinePdfZoom = (delta) => {
                    const newZoom = Math.max(0.5, Math.min(15.0, state.zoom + delta));
                    if (newZoom !== state.zoom) applyZoom(newZoom);
                 };

                window.ceInlinePdfShowJump = () => {
                    jumpInput.value = state.currentPage;
                    jumpInput.max = state.pdf.numPages;
                    if (jumpPopup.classList.contains('active')) {
                        jumpPopup.classList.remove('active');
                    } else {
                        jumpPopup.classList.add('active');
                        setTimeout(() => jumpInput.focus(), 100);
                    }
                };

                window.ceInlinePdfGo = () => {
                    const p = parseInt(jumpInput.value);
                    if (p >= 1 && p <= state.pdf.numPages) {
                        window.ceInlinePdfJumpTo(p);
                        jumpPopup.classList.remove('active');
                    }
                };

                window.ceInlinePdfJumpTo = (pageNum) => {
                    const el = document.getElementById('inline_pdf_page_' + pageNum);
                    if (el) {
                        el.scrollIntoView({ behavior: 'auto', block: 'start' });
                        state.currentPage = pageNum;
                    }
                };

                window.ceInlinePdfToggleBookmarks = (show) => {
                    if (show) bookmarkDrawer.classList.add('active');
                    else bookmarkDrawer.classList.remove('active');
                };

                const loadOutlines = () => {
                    state.pdf.getOutline().then(outline => {
                        if (outline && outline.length > 0) {
                            document.getElementById('ceInlineBtnPdfBookmarks').style.display = 'flex';
                            document.getElementById('ceInlineSepPdfBookmarks').style.display = 'block';
                            
                            const listDiv = document.getElementById('ceInlinePdfBookmarkList');
                            listDiv.innerHTML = '';
                            
                            const renderItem = (items) => {
                                items.forEach(item => {
                                    const a = document.createElement('div');
                                    a.className = 'ce-inline-pdf-bookmark-item';
                                    a.textContent = item.title;
                                    a.onclick = () => {
                                        if (typeof item.dest === 'string') {
                                            state.pdf.getDestination(item.dest).then(dest => {
                                                state.pdf.getPageIndex(dest[0]).then(idx => {
                                                    window.ceInlinePdfJumpTo(idx + 1);
                                                    window.ceInlinePdfToggleBookmarks(false);
                                                });
                                            });
                                        } else if (Array.isArray(item.dest)) {
                                            state.pdf.getPageIndex(item.dest[0]).then(idx => {
                                                window.ceInlinePdfJumpTo(idx + 1);
                                                window.ceInlinePdfToggleBookmarks(false);
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

                fetch(url).then(res => res.arrayBuffer()).then(buffer => {
                    lib.getDocument({ data: buffer }).promise.then(pdf => {
                        state.pdf = pdf;
                        initObserver();
                        layoutPages();
                        loadOutlines(); 
                   }).catch(err => {
                        wrap.innerHTML = '<div style="color:#fff;text-align:center;padding:20px;">PDF Error: ' + err.message + '</div>';
                    });
                }).catch(err => {
                    wrap.innerHTML = '<div style="color:#fff;text-align:center;padding:20px;">Fetch Error: ' + err.message + '</div>';
                });
            }
        }
    } 
    // 5. TEXT / CODE
    else if (isCode) {
        wrap.style.background = '#fff';
        wrap.style.alignItems = 'flex-start';
        wrap.style.justifyContent = 'flex-start';
        wrap.style.overflow = 'auto';
        wrap.innerHTML = '<div class="myCloud-loading-container" style="color:#333; margin:auto;"><div class="myCloud-spinner dark"></div></div>';
        
        fetch(url).then(r => r.text()).then(text => {
            const clean = text.replace(/</g, '&lt;');
            wrap.innerHTML = '<div style="padding:20px; white-space:pre-wrap; font-family:monospace; color:var(--text-primary); font-size:13px; text-align:left; width:100%;">' + clean + '</div>';
        }).catch(() => {
            wrap.innerHTML = '<div style="color:red; padding:20px;">Failed to load text file.</div>';
        });
    }
    // 6. DOCX (WITH FIXES FOR COLOR AND WRAPPING)
    else if (ext === 'docx') {
        wrap.style.background = '#525659';
        wrap.style.overflow = 'auto';
        wrap.style.alignItems = 'flex-start';
        wrap.style.justifyContent = 'center';
        wrap.innerHTML = '<div style="margin:auto; display:flex; flex-direction:column; align-items:center; gap:10px;"><div class="myCloud-spinner dark"></div><div style="color:#fff">Processing DOCX...</div></div>';
        
        // Inject specific styles for inline docx
        if (!document.getElementById('ceInlineDocxStyles')) {
            const s = document.createElement('style');
            s.id = 'ceInlineDocxStyles';
            s.innerHTML = 
                '#docx_zoom_wrapper_inline section { background-color: #ffffff !important; box-shadow: 0 4px 12px rgba(0,0,0,0.2) !important; margin-bottom: 25px !important; border: none !important; color: #000; }' +
                '#docx_zoom_wrapper_inline article, #docx_zoom_wrapper_inline header, #docx_zoom_wrapper_inline footer { background-color: transparent !important; }';
            document.head.appendChild(s);
        }

        const initDocx = async () => {
            if (typeof window.docx === 'undefined') {
                if (typeof myCloudLoadJS === 'function') {
                    await myCloudLoadJS('https://unpkg.com/jszip/dist/jszip.min.js');
                    await myCloudLoadJS('https://unpkg.com/docx-preview/dist/docx-preview.js');
                }
            }
            const res = await fetch(url);
            const blob = await res.blob();
            wrap.innerHTML = '';
            
            const docWrap = document.createElement('div');
            docWrap.id = 'docx_zoom_wrapper_inline';
            docWrap.style.transformOrigin = 'top center'; 
            docWrap.style.display = 'flex';
            docWrap.style.flexDirection = 'column';
            docWrap.style.alignItems = 'center';
            docWrap.style.color = '#000'; // Force black text
            wrap.appendChild(docWrap);

            window.docx.renderAsync(blob, docWrap, null, {
                className: "docx_viewer_inline", inWrapper: true, ignoreWidth: false, ignoreHeight: false, breakPages: true, renderHeaders: true, renderFooters: true
            }).then(() => {
                const applyZoom = () => {
                    const firstPage = docWrap.querySelector('section, article');
                    if (!firstPage) return;
                    
                    const pageWidth = firstPage.offsetWidth || 816; 
                    const screenWidth = wrap.clientWidth;
                    let scaleFactor = (screenWidth - 20) / pageWidth;
                    if (scaleFactor > 1.5) scaleFactor = 1.5;

                    // Lock width BEFORE scaling to prevent text wrapping/cropping
                    docWrap.style.width = pageWidth + 'px';
                    docWrap.style.transform = 'scale(' + scaleFactor + ')';
                    
                    let totalHeight = 0;
                    docWrap.querySelectorAll('section, article').forEach(s => {
                        totalHeight += s.offsetHeight + 20;
                    });

                    docWrap.style.height = (totalHeight * scaleFactor) + 60 + 'px';
                    docWrap.style.paddingTop = (20 / scaleFactor) + 'px';
                };
                setTimeout(applyZoom, 150);
                
                const resizeObserver = new ResizeObserver(() => applyZoom());
                resizeObserver.observe(wrap);
                
            }).catch(e => { wrap.innerHTML = '<div style="color:red; padding:20px;">Render Error</div>'; });
        };
        initDocx().catch(() => wrap.innerHTML = '<div style="color:red; padding:20px;">Library Load Error</div>');
    }
    // 7. EXCEL
    else if (ext === 'xlsx') {
        wrap.style.background = '#fff';
        wrap.innerHTML = '<div style="margin:auto; display:flex; flex-direction:column; align-items:center; gap:10px;"><div class="myCloud-spinner dark"></div><div style="color:#333">Parsing Sheet...</div></div>';
        
        const initXlsx = async () => {
            if (typeof window.XLSX === 'undefined') {
                if (typeof myCloudLoadJS === 'function') await myCloudLoadJS('https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js');
            }
            const res = await fetch(url);
            const ab = await res.arrayBuffer();
            const workbook = window.XLSX.read(ab, {type: 'array'});
            
            let sheetsHtml = '<div class="myCloud-excel-sheets">';
            let tabsHtml = '<div class="myCloud-excel-tabs">';
            
            workbook.SheetNames.forEach((name, index) => {
                const active = index === 0 ? 'active' : '';
                const ws = workbook.Sheets[name];
                if(!ws['!ref']) ws['!ref'] = 'A1:A1';
                
                tabsHtml += `<div class="myCloud-excel-tab ${active}" onclick="document.querySelectorAll('.ce-inline-sheet').forEach(e=>e.style.display='none'); document.getElementById('ce_sheet_inline_${index}').style.display='block'; document.querySelectorAll('.myCloud-excel-tab').forEach(e=>e.classList.remove('active')); this.classList.add('active');" style="flex:none; padding:6px 12px; font-size:12px; cursor:pointer; border-right:1px solid var(--border-default);">${name}</div>`;
                
                const tableHtml = window.XLSX.utils.sheet_to_html(ws, { editable:false });
                
                const temp = document.createElement('div');
                temp.innerHTML = tableHtml;
                const tbl = temp.querySelector('table');
                
                sheetsHtml += `<div id="ce_sheet_inline_${index}" class="ce-inline-sheet" style="display:${index===0?'block':'none'}; width:100%; min-height:100%; padding:20px; background:var(--gray-00);">`;
                if (tbl) {
                    tbl.className = 'myCloud-excel-table';
                    sheetsHtml += tbl.outerHTML;
                } else {
                    sheetsHtml += '<div style="padding:10px; color:#999;">Empty Sheet</div>';
                }
                sheetsHtml += '</div>';
            });
            
            sheetsHtml += '</div>'; 
            tabsHtml += '</div>';
            
            if (!document.getElementById('ceInlineExcelStyles')) {
                const s = document.createElement('style');
                s.id = 'ceInlineExcelStyles';
                s.innerHTML = '.ce-inline-sheet { display: none; width: 100%; min-height: 100%; padding: 20px; background: var(--gray-00); color: var(--text-primary); }';
                document.head.appendChild(s);
            }
            
            wrap.innerHTML = '<div class="myCloud-excel-wrapper" style="display:flex; flex-direction:column; height:100%; width:100%; overflow:hidden;">' + sheetsHtml + tabsHtml + '</div>';
        };
        initXlsx().catch(() => wrap.innerHTML = '<div style="color:red; padding:20px;">Library Load Error</div>');
    }
    // 8. EPUB
    else if (isEpub) {
        wrap.style.background = '#fafafa';
        wrap.style.flexDirection = 'column';
        wrap.style.alignItems = 'center';
        wrap.style.justifyContent = 'center';
        
        wrap.innerHTML = 
        '<div class="myCloud-loading-container" id="epubInlineLoadingWrap" style="color:#333; position:absolute; z-index: 10;">' +
            '<div class="myCloud-spinner dark"></div>' +
            '<div style="margin-top: 10px;">' + (typeof myCloud_LANG !== 'undefined' ? (myCloud_LANG.loading || 'Loading eBook...') : 'Loading eBook...') + '</div>' +
        '</div>' +
		'<div id="epubInlineViewer" style="width: 100%; height: 100%; overflow: hidden;"></div>' +
        '<div id="epubInlineTocOverlay" style="display:none; position:absolute; top:0; left:0; width:300px; max-width:80%; height:100%; background:rgba(255,255,255,0.95); backdrop-filter:blur(10px); border-right:1px solid var(--border-default); z-index:100; overflow-y:auto; box-shadow: 2px 0 10px rgba(0,0,0,0.1); padding: 20px;"></div>' +
        '<div class="myCloud-pdf-toolbar" style="bottom: 15px;">' + 
            '<button class="myCloud-pdf-btn" id="epubInlineTocBtn" title="Table of Contents"><svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg></button>' +
            '<div class="myCloud-pdf-sep" style="width:1px; height:20px; background:rgba(255,255,255,0.2); margin:0 5px;"></div>' +
            '<button class="myCloud-pdf-btn" id="epubInlinePrevBtn" title="Previous Page">❮</button>' +
            '<span id="epubInlinePageIndicator" class="myCloud-pdf-page-num" style="min-width: 60px; font-size:13px;">...</span>' +
            '<button class="myCloud-pdf-btn" id="epubInlineNextBtn" title="Next Page">❯</button>' +
            '<div class="myCloud-pdf-sep" style="width:1px; height:20px; background:rgba(255,255,255,0.2); margin:0 5px;"></div>' +
            '<button class="myCloud-pdf-btn" id="epubInlineZoomOut" title="Decrease Font">A-</button>' +
            '<button class="myCloud-pdf-btn" id="epubInlineZoomIn" title="Increase Font">A+</button>' +
        '</div>';

        const initEpubInline = async () => {
            if (typeof window.ePub === 'undefined') {
                await myCloudLoadJS('/script/epub/jszip.min.js');
                await myCloudLoadJS('/script/epub/epub.min.js');
            }
            
            const viewer = document.getElementById('epubInlineViewer');
            const res = await fetch(url);
            const buffer = await res.arrayBuffer();
            const book = window.ePub(buffer);

            const rendition = book.renderTo(viewer, {
                width: "100%",
                height: "100%",
                spread: "none",
                manager: "continuous",
                flow: "scrolled"
            });
			
            let currentFontSize = 100;
            const fontMatch = document.cookie.match(/(^| )myCloudEpubInlineFontSize=([^;]+)/);
            if (fontMatch && !isNaN(parseInt(fontMatch[2]))) {
                currentFontSize = parseInt(fontMatch[2]);
            }
            
            rendition.display().then(() => {
				if (currentFontSize !== 100) rendition.themes.fontSize(currentFontSize + '%');
                const loading = wrap.querySelector('#epubInlineLoadingWrap');
                if (loading) loading.style.display = 'none';			
 
                book.loaded.navigation.then(toc => {
                    const tocOverlay = document.getElementById('epubInlineTocOverlay');
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
                });

                rendition.on("relocated", (location) => {
                    const indicator = document.getElementById('epubInlinePageIndicator');
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
                        const indicator = document.getElementById('epubInlinePageIndicator');
                        if (indicator) indicator.textContent = percentage + '%';
                    }
                });
            });
            
            document.getElementById('epubInlinePrevBtn').onclick = () => rendition.prev();
            document.getElementById('epubInlineNextBtn').onclick = () => rendition.next();
            document.getElementById('epubInlineTocBtn').onclick = () => {
                const toc = document.getElementById('epubInlineTocOverlay');
                toc.style.display = toc.style.display === 'none' ? 'block' : 'none';
            };
            
            const saveEpubFontSize = (size) => {
                const d = new Date(); d.setTime(d.getTime() + (365*24*60*60*1000));
                document.cookie = "myCloudEpubInlineFontSize=" + size + ";expires=" + d.toUTCString() + ";path=/;SameSite=Lax";
            };
           
            document.getElementById('epubInlineZoomIn').onclick = () => {
                currentFontSize += 15;
                rendition.themes.fontSize(currentFontSize + '%');
                saveEpubFontSize(currentFontSize);
            };
            document.getElementById('epubInlineZoomOut').onclick = () => {
                currentFontSize = Math.max(50, currentFontSize - 15);
                rendition.themes.fontSize(currentFontSize + '%');
                saveEpubFontSize(currentFontSize);
            };
        };

        initEpubInline().catch(err => {
            console.error(err);
            wrap.innerHTML = '<div style="color:red; padding:20px;">Error loading EPUB previewer.</div>';
        });
    }	
}

// ============================================================
// PDF STACK / UNSTACK LOGIC (OFFICE VIEW ONLY)
// ============================================================

window.myCloudAction_PdfUnstack = function(path) {
    myCloudShowLoading();
    const fd = new URLSearchParams();
    fd.append('myCloud_action', 'pdf_unstack');
    fd.append('myCloud_key', myCloudState.key);
    fd.append('myCloud_token', typeof myCloudCsrfToken !== 'undefined' ? myCloudCsrfToken : '');
    fd.append('src', path);

    fetch('', { method: 'POST', body: fd }).then(r=>r.json()).then(res => {
        myCloudHideLoading();
        if (res.status === 'OK') {
            myCloudFetchDirectory(myCloudState.currentDir);
        } else {
            myCloudShowAlert('Error', res.msg || 'Unstack failed.');
        }
    }).catch(err => {
        myCloudHideLoading();
        myCloudShowAlert('Error', 'Network error.');
    });
};

window.myCloudAction_PdfStackMenu = function() {
    try {
        const sel = myCloudState.selectedFiles;
        if (!sel || sel.length < 2) {
            console.warn("PDF Stack aborted: Less than 2 files selected.");
            return;
        }
        
        let targetName = sel[0];
        const ext = targetName.split('.').pop().toLowerCase();
        const officeExts = ['docx', 'xlsx', 'pptx', 'doc', 'xls', 'ppt', 'odt', 'ods', 'odp', 'rtf', 'csv'];
        
        // If the target is an office document, ensure the output name ends in .pdf
        if (officeExts.includes(ext)) {
            targetName = targetName.substring(0, targetName.lastIndexOf('.')) + '.pdf';
        }
        
        // Create a fresh array copy to prevent state mutation issues
        const sources = Array.from(sel);
        window.myCloudShowPdfMergeDialog(sources, targetName, false, true);

    } catch (err) {
        alert("Stack Menu Init Error: " + err.message);
    }
};

window.myCloudShowPdfMergeDialog = function(draggedPaths, targetPath, isMove, isContextMenu) {
    try {
        // Fallback for older JS engines instead of inline default parameters
        if (typeof isContextMenu === 'undefined') isContextMenu = false;

        // If context menu, draggedPaths already contains all sources. targetPath is just the output string.
        const totalFiles = isContextMenu ? Array.from(draggedPaths) : [targetPath].concat(draggedPaths);
        const overlay = document.getElementById('myCloudModalOverlay');
        const modal = document.getElementById('myCloudModal');
        
        if (typeof myCloudResetModal === 'function') myCloudResetModal();
        overlay.style.display = 'flex';
        modal.className = 'myCloudModal conflict'; 
        
        const devKey = typeof myCloudGetCurrentDeviceKey === 'function' ? myCloudGetCurrentDeviceKey() : 'desktop';
        const pref = myCloudState.settings && myCloudState.settings[devKey] ? (myCloudState.settings[devKey].pdfMergePref || 'last') : 'last';

        const executeMerge = async (orderedFiles) => {
            overlay.style.display = 'none';

            const officeExts = ['docx', 'xlsx', 'pptx', 'doc', 'xls', 'ppt', 'odt', 'ods', 'odp', 'rtf', 'csv'];
            const needsConversion = orderedFiles.some(f => officeExts.includes(f.split('.').pop().toLowerCase()));
            let finalPdfs = Array.from(orderedFiles);
            let tempPdfsToCleanup = [];

            if (needsConversion) {
                myCloudCreateProgressUI(typeof myCloud_LANG !== 'undefined' && myCloud_LANG.converting ? myCloud_LANG.converting : "Converting documents...");
                const titleEl = document.querySelector('.myCloudProgressTitle');
                const textEl = document.getElementById('myCloudProgressText');
                
                for (let i = 0; i < orderedFiles.length; i++) {
                    let f = orderedFiles[i];
                    let ext = f.split('.').pop().toLowerCase();
                    let name = f.split('/').pop();
                    
                    if (officeExts.includes(ext)) {
                        if (titleEl) titleEl.textContent = (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.processing ? myCloud_LANG.processing : "Processing") + " " + (i+1) + " of " + orderedFiles.length + ": " + name;
                        if (textEl) textEl.textContent = "Converting to PDF...";
                        myCloudUpdateProgressUI((i / orderedFiles.length) * 100);

                        const fd = new URLSearchParams();
                        fd.append('myCloud_action', 'office_convert_temp_pdf');
                        fd.append('myCloud_key', myCloudState.key);
                        fd.append('myCloud_token', typeof myCloudCsrfToken !== 'undefined' ? myCloudCsrfToken : '');
                        fd.append('path', f);
                        
                        try {
                            const res = await fetch('', { method: 'POST', body: fd }).then(r=>r.json());
                            if (res.status === 'OK' && res.tempPath) {
                                finalPdfs[i] = res.tempPath;
                                tempPdfsToCleanup.push(res.tempPath);
                            } else {
                                myCloudCloseProgressUI();
                                myCloudShowAlert('Error', "Conversion failed for " + name + ": " + (res.msg || 'Unknown error'));
                                return;
                            }
                        } catch (e) {
                            myCloudCloseProgressUI();
                            myCloudShowAlert('Error', "Network error during conversion of " + name);
                            return;
                        }
                    }
                }
                if (textEl) textEl.textContent = "Merging PDFs...";
                myCloudUpdateProgressUI(95);
            } else {
                myCloudShowLoading();
            }
            
            const fd = new URLSearchParams();
            fd.append('myCloud_action', 'pdf_stack');
            fd.append('myCloud_key', myCloudState.key);
            fd.append('myCloud_token', typeof myCloudCsrfToken !== 'undefined' ? myCloudCsrfToken : '');
            fd.append('files', JSON.stringify(finalPdfs));
            fd.append('dest', targetPath);
            fd.append('delete_sources', isMove ? 'true' : 'false');
            
            if (needsConversion) {
                fd.append('temp_cleanup', JSON.stringify(tempPdfsToCleanup));
                if (isMove) fd.append('original_sources', JSON.stringify(orderedFiles));
            }
            
            const useRec = (myCloudState.settings && typeof myCloudState.settings.enableRecycleBin !== 'undefined') ? myCloudState.settings.enableRecycleBin : true;
            fd.append('useRecycleBin', useRec ? 'true' : 'false');

        fetch('', { method: 'POST', body: fd })
        .then(async r => {
            const text = await r.text();
            try {
                return JSON.parse(text);
            } catch (e) {
                throw new Error("Server crashed: " + text.replace(/(<([^>]+)>)/gi, "").substring(0, 150));
            }
        })
        .then(res => {
				if (needsConversion) myCloudCloseProgressUI();
                else myCloudHideLoading();
                
                if (res.status === 'OK') {
                    myCloudFetchDirectory(myCloudState.currentDir);
                } else {
                    myCloudShowAlert('Error', res.msg || 'Merge failed.');
                }
            }).catch(() => {
                if (needsConversion) myCloudCloseProgressUI();
                else myCloudHideLoading();
                myCloudShowAlert('Error', 'Network error.');
            });
        };

        // Scenario A: Exactly 2 files involved via Drag & Drop
        if (totalFiles.length === 2 && !isContextMenu) {
            const f1 = targetPath.split('/').pop();
            const f2 = draggedPaths[0].split('/').pop();
            
            modal.innerHTML = 
                '<div class="myCloudModalHeader" style="border-bottom:none; padding-bottom:0;">' + (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.pdf_stack ? myCloud_LANG.pdf_stack : 'Stack PDFs') + '</div>' +
                 '<div class="myCloudModalBody" style="padding: 20px;">' +
                     '<div style="text-align:center; margin-bottom:15px;"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#0078d4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg></div>' +
                    '<div style="text-align:center; font-size:14px; margin-bottom:20px;">' + (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.pdf_merge_where ? myCloud_LANG.pdf_merge_where.replace('%s', '<b>' + (typeof myCloudEscapeHtml === 'function' ? myCloudEscapeHtml(f2) : f2) + '</b>').replace('%s', '<b>' + (typeof myCloudEscapeHtml === 'function' ? myCloudEscapeHtml(f1) : f1) + '</b>') : 'Merge where?') + '</div>' +
                     '<div style="display:flex; justify-content:center; gap:10px; margin-bottom:10px;">' +
                        '<button id="btnPdfFirst" style="padding:8px 16px; background:' + (pref === 'first' ? 'var(--accent-primary)' : 'var(--gray-20)') + '; color:' + (pref === 'first' ? '#fff' : 'inherit') + '; border:none; border-radius:4px;">' + (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.pdf_place_first ? myCloud_LANG.pdf_place_first : 'First') + '</button>' +
                        '<button id="btnPdfLast" style="padding:8px 16px; background:' + (pref === 'last' ? 'var(--accent-primary)' : 'var(--gray-20)') + '; color:' + (pref === 'last' ? '#fff' : 'inherit') + '; border:none; border-radius:4px;">' + (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.pdf_place_last ? myCloud_LANG.pdf_place_last : 'Last') + '</button>' +
                     '</div>' +
                    '<div style="text-align:center; font-size:12px; color:var(--text-secondary); margin-bottom:20px;">' + (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.pdf_target_file ? myCloud_LANG.pdf_target_file.replace('%s', (typeof myCloudEscapeHtml === 'function' ? myCloudEscapeHtml(f1) : f1)) : 'Target: ' + (typeof myCloudEscapeHtml === 'function' ? myCloudEscapeHtml(f1) : f1)) + (isMove ? ' <span style="color:var(--danger);">' + (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.pdf_originals_deleted ? myCloud_LANG.pdf_originals_deleted : 'Originals deleted') + '</span>' : '') + '</div>' +
                    '<div class="myCloudButtons" style="justify-content:center;"><button onclick="document.getElementById(\'myCloudModalOverlay\').style.display=\'none\'">' + (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.cancel ? myCloud_LANG.cancel : 'Cancel') + '</button></div>' +
                 '</div>';
            
            const savePref = (val) => {
                if (myCloudState.settings && myCloudState.settings[devKey]) {
                    myCloudState.settings[devKey].pdfMergePref = val;
                    if (typeof myCloudSaveSettings === 'function') myCloudSaveSettings();
                }
            };

            document.getElementById('btnPdfFirst').onclick = () => { savePref('first'); executeMerge([draggedPaths[0], targetPath]); };
            document.getElementById('btnPdfLast').onclick = () => { savePref('last'); executeMerge([targetPath, draggedPaths[0]]); };
            return;
        }

        // Scenario B: More than 2 files OR Context Menu (Sortable List)
        let listHtml = '';
        totalFiles.forEach((p) => {
            const name = p.split('/').pop();
            const safePath = typeof myCloudEscapeHtml === 'function' ? myCloudEscapeHtml(p) : p;
            const safeName = typeof myCloudEscapeHtml === 'function' ? myCloudEscapeHtml(name) : name;
            
            listHtml += 
                '<div class="pdf-sort-item" draggable="true" data-path="' + safePath + '" style="display:flex; align-items:center; padding:8px 10px; background:var(--gray-10); border:1px solid var(--border-default); margin-bottom:4px; border-radius:4px; cursor:move;">' +
                    '<span style="margin-right:10px; color:var(--text-disabled);">☰</span>' +
                    '<span style="flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:13px;">' + safeName + '</span>' +
                    '<div style="display:flex; flex-direction:column; gap:2px;">' +
                        '<button onclick="window.cePdfSortMove(this, -1)" style="padding:2px 6px; font-size:10px; cursor:pointer; background:var(--gray-20); border:1px solid var(--border-medium);">▲</button>' +
                        '<button onclick="window.cePdfSortMove(this, 1)" style="padding:2px 6px; font-size:10px; cursor:pointer; background:var(--gray-20); border:1px solid var(--border-medium);">▼</button>' +
                    '</div>' +
                '</div>';
        });

        modal.innerHTML = 
            '<div class="myCloudModalHeader" style="border-bottom:none; padding-bottom:0;">' + (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.pdf_stack ? myCloud_LANG.pdf_stack : 'Stack PDFs') + '</div>' +
             '<div class="myCloudModalBody" style="padding: 20px;">' +
                '<div style="font-size:13px; margin-bottom:15px;">' + (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.pdf_drag_reorder ? myCloud_LANG.pdf_drag_reorder : 'Drag to reorder') + (isMove ? '<br><b style="color:var(--danger);">' + (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.pdf_originals_deleted ? myCloud_LANG.pdf_originals_deleted : 'Originals deleted') + '</b>' : '') + '</div>' +
                 '<div id="pdfSortContainer" style="max-height:250px; overflow-y:auto; margin-bottom:20px;">' + listHtml + '</div>' +
                 '<div class="myCloudButtons" style="justify-content:flex-end;">' +
                    '<button onclick="document.getElementById(\'myCloudModalOverlay\').style.display=\'none\'">' + (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.cancel ? myCloud_LANG.cancel : 'Cancel') + '</button>' +
                    '<button id="btnPdfExecute" style="background:var(--accent-primary); color:#fff; border:none;">' + (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.pdf_merge_btn ? myCloud_LANG.pdf_merge_btn : 'Merge') + '</button>' +
                 '</div>' +
            '</div>';

        // Button Action
        window.cePdfSortMove = (btn, dir) => {
            const item = btn.closest('.pdf-sort-item');
            const container = item.parentNode;
            const items = Array.from(container.children);
            const idx = items.indexOf(item);
            if (dir === -1 && idx > 0) {
                container.insertBefore(item, items[idx - 1]);
            } else if (dir === 1 && idx < items.length - 1) {
                container.insertBefore(item, items[idx + 2] || null);
            }
        };

        document.getElementById('btnPdfExecute').onclick = () => {
            const ordered = Array.from(document.querySelectorAll('.pdf-sort-item')).map(el => el.dataset.path);
            executeMerge(ordered);
        };

        // HTML5 Drag and Drop for sorting
        const sortContainer = document.getElementById('pdfSortContainer');
        let dragEl = null;

        sortContainer.querySelectorAll('.pdf-sort-item').forEach(item => {
            item.addEventListener('dragstart', function(e) {
                dragEl = this;
                e.dataTransfer.effectAllowed = 'move';
                this.style.opacity = '0.4';
            });
            item.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                return false;
            });
            item.addEventListener('drop', function(e) {
                e.stopPropagation();
                if (dragEl !== this) {
                    const all = Array.from(sortContainer.children);
                    if (all.indexOf(dragEl) < all.indexOf(this)) this.after(dragEl);
                    else this.before(dragEl);
                }
                return false;
            });
            item.addEventListener('dragend', function() {
                this.style.opacity = '1';
            });
        });

    } catch (err) {
        alert("Dialog Render Error: " + err.message);
    }
};


// ============================================================
// PDF ADVANCED TOOLKIT (MODAL SUBMENU)
// ============================================================

window.myCloudShowPdfToolkit = function(path) {
    const overlay = document.getElementById('myCloudModalOverlay');
    const modal = document.getElementById('myCloudModal');
    if (typeof myCloudResetModal === 'function') myCloudResetModal();
    
    overlay.style.display = 'flex';
    modal.className = 'myCloudModal';
    modal.style.maxWidth = '450px';

    const renderMain = () => {
        modal.innerHTML = 
             '<div class="myCloudModalHeader" style="border-bottom:1px solid var(--border-default);">' +
                '<div>' + myCloud_LANG.pdf_tools + '</div>' +
                 '<span onclick="document.getElementById(\'myCloudModalOverlay\').style.display=\'none\'" style="cursor:pointer; color:var(--text-secondary);">✕</span>' +
             '</div>' +
             '<div class="myCloudModalBody" style="padding:20px;">' +
                 '<div style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px;">' + 
					 '<button class="pdf-tool-btn" onclick="cePdfViewKeep()"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><line x1="20" y1="4" x2="8.12" y2="15.88"/><line x1="14.47" y1="14.48" x2="20" y2="20"/><line x1="8.12" y1="8.12" x2="12" y2="12"/></svg>' + myCloud_LANG.pdf_keep_pages + '</button>' +
                     '<button class="pdf-tool-btn" onclick="cePdfViewRotate()"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="21 3 21 9 15 9"/><path d="M20.49 14.5a9 9 0 1 1-2.12-9.36L21 9"/></svg>' + myCloud_LANG.pdf_rotate + '</button>' +
                    '<button class="pdf-tool-btn" onclick="cePdfRun(\'pdf_shrink\', null)"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14h6v6"/><path d="M20 10h-6V4"/><path d="M14 10l7-7"/><path d="M3 21l7-7"/></svg>' + myCloud_LANG.pdf_shrink + '</button>' +
                    '<button class="pdf-tool-btn" onclick="cePdfRun(\'pdf_flatten\', null)"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>' + (myCloud_LANG.pdf_flatten || 'Flatten PDF') + '</button>' +
                    '<button class="pdf-tool-btn" onclick="cePdfViewEncrypt()"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>' + (myCloud_LANG.pdf_encrypt || 'Protect / Encrypt') + '</button>' +
                    '<button class="pdf-tool-btn" onclick="cePdfViewUnlock()"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg>' + myCloud_LANG.pdf_unlock + '</button>' +
                     '<button class="pdf-tool-btn" onclick="cePdfRun(\'pdf_extract_text\', null)"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>' + myCloud_LANG.pdf_extract_text + '</button>' +
                     '<button class="pdf-tool-btn" onclick="cePdfRun(\'pdf_ocr_text\', null)"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/><line x1="3" y1="12" x2="21" y2="12"/></svg>' + myCloud_LANG.pdf_ocr_text + '</button>' +
                     '<button class="pdf-tool-btn" onclick="cePdfRun(\'pdf_extract_images\', null)"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>' + (myCloud_LANG.pdf_extract_images || 'Extract Images') + '</button>' +
                     '<button class="pdf-tool-btn" onclick="cePdfRun(\'pdf_repair\', null)"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>' + (myCloud_LANG.pdf_repair || 'Repair PDF') + '</button>' +
                    '<button class="pdf-tool-btn" onclick="myCloudAction_PdfStartFormFill()"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/></svg>' + (myCloud_LANG.pdf_fill_form || 'Fill Form') + '</button>' +
					 '</div>' +
            '</div>';
    };

    const renderInput = (title, label, inputHtml, onOk) => {
        modal.innerHTML = 
            '<div class="myCloudModalHeader" style="border-bottom:1px solid var(--border-default);">' +
                '<div>' + title + '</div>' +
            '</div>' +
            '<div class="myCloudModalBody" style="padding:20px;">' +
                '<div style="margin-bottom:15px; font-size:13px; color:var(--text-primary);">' + label + '</div>' +
                inputHtml +
                '<div class="myCloudButtons" style="justify-content:flex-end; margin-top:20px; gap:10px;">' +
                    '<button onclick="cePdfViewMain()">' + myCloud_LANG.cancel + '</button>' +
                    '<button id="pdfToolConfirm" style="background:var(--accent-primary); color:#fff; border:none;">' + myCloud_LANG.pdf_execute_btn + '</button>' +
                '</div>' +
            '</div>';
        document.getElementById('pdfToolConfirm').onclick = onOk;
    };

    window.cePdfViewMain = renderMain;

    window.cePdfRun = (action, extraData) => {
        overlay.style.display = 'none';
        myCloudShowLoading();
        const fd = new URLSearchParams();
        fd.append('myCloud_action', action);
        fd.append('myCloud_key', myCloudState.key);
        fd.append('myCloud_token', typeof myCloudCsrfToken !== 'undefined' ? myCloudCsrfToken : '');
        fd.append('src', path);
        if (extraData) {
            for (const [k, v] of Object.entries(extraData)) fd.append(k, v);
        }
        fetch('', { method: 'POST', body: fd }).then(r=>r.json()).then(res => {
            myCloudHideLoading();
            if (res.status === 'OK') {
                myCloudFetchDirectory(myCloudState.currentDir);
            } else {
                myCloudShowAlert('Error', res.msg || 'Operation failed.');
            }
        }).catch(() => { myCloudHideLoading(); myCloudShowAlert('Error', 'Network Error'); });
    };

    window.cePdfViewKeep = () => renderInput(myCloud_LANG.pdf_keep_pages, myCloud_LANG.pdf_keep_hint, '<input type="text" id="pdfPages" class="myCloudInlineInput" placeholder="1, 3-5" style="width:100%;">', () => { const val = document.getElementById('pdfPages').value.trim(); if (val) window.cePdfRun('pdf_keep_pages', { pages: val }); });
 
    window.cePdfViewRotate = () => renderInput(myCloud_LANG.pdf_rotate, myCloud_LANG.pdf_rotate_hint, '<select id="pdfAngle" class="myCloudInlineInput" style="width:100%;"><option value="+90">90° Clockwise</option><option value="+180">180°</option><option value="-90">90° Counter-Clockwise</option></select>', () => window.cePdfRun('pdf_rotate', { angle: document.getElementById('pdfAngle').value }));
 
    window.cePdfViewUnlock = () => renderInput(myCloud_LANG.pdf_unlock, myCloud_LANG.pdf_unlock_hint, '<input type="password" id="pdfPass" class="myCloudInlineInput" style="width:100%;">', () => { const val = document.getElementById('pdfPass').value; if (val) window.cePdfRun('pdf_unlock', { password: val }); });

    window.cePdfViewEncrypt = () => renderInput((myCloud_LANG.pdf_encrypt || 'Encrypt PDF'), (myCloud_LANG.pdf_encrypt_hint || 'Enter a password to lock this PDF:'), '<input type="password" id="pdfPassEnc" class="myCloudInlineInput" style="width:100%;">', () => { const val = document.getElementById('pdfPassEnc').value; if (val) window.cePdfRun('pdf_encrypt', { password: val }); });

    renderMain();
};

// ============================================================
// GLOBAL IMAGE TO PDF COMBINER
// ============================================================

window.myCloudAction_PdfCombineImages = function() {
    const sel = myCloudState.selectedFiles;
    if (sel.length < 2) return;
    
    myCloudShowLoading();
    const fd = new URLSearchParams();
    fd.append('myCloud_action', 'pdf_combine_images');
    fd.append('myCloud_key', myCloudState.key);
    fd.append('myCloud_token', typeof myCloudCsrfToken !== 'undefined' ? myCloudCsrfToken : '');
    fd.append('files', JSON.stringify(sel));

    fetch('', { method: 'POST', body: fd }).then(r=>r.json()).then(res => {
        myCloudHideLoading();
        if (res.status === 'OK') {
            myCloudFetchDirectory(myCloudState.currentDir);
        } else {
            myCloudShowAlert('Error', res.msg || 'Combine failed.');
        }
    }).catch(() => { myCloudHideLoading(); myCloudShowAlert('Error', 'Network Error'); });
};

// ============================================================
// WYSIWYG PDF FORM FILLING (PDF.JS FULLSCREEN MODAL)
// ============================================================

window.myCloudAction_PdfStartFormFill = async function() {
    const pane = document.getElementById('myCloudPreviewPane');
    const path = pane ? pane.dataset.currentPreviewPath : null;
    if (!path) return;
    
    document.getElementById('myCloudModalOverlay').style.display = 'none';
    myCloudShowLoading();

    // 1. Load Local PDF.js dynamically (as a modern ES Module)
    if (!window.pdfjsLib) {
        try {
            // IMPORTANT: Replace these strings with the exact local paths from your preview.php!
            const pdfjsModule = await import('/script/pdf.js');
            window.pdfjsLib = pdfjsModule.default || pdfjsModule;
            window.pdfjsLib.GlobalWorkerOptions.workerSrc = '/script/pdf.worker.js';
        } catch (e) {
            console.error(e);
            myCloudHideLoading();
            return myCloudShowAlert('Error', 'Failed to load local PDF engine as a module.');
        }
    }

    // 2. Build Full-Screen Modal
    let modal = document.getElementById('myCloudPdfWysiwygModal');
    if (modal) modal.remove();
    modal = document.createElement('div');
    modal.id = 'myCloudPdfWysiwygModal';
    modal.style.cssText = 'position:fixed; top:0; left:0; width:100vw; height:100vh; background:var(--gray-10); z-index:99999; display:flex; flex-direction:column;';
    
    modal.innerHTML = 
        '<div style="padding:15px 25px; background:var(--gray-00); border-bottom:1px solid var(--border-default); display:flex; justify-content:space-between; align-items:center; box-shadow:0 2px 5px rgba(0,0,0,0.05); z-index:2;">' +
            '<div style="font-weight:600; font-size:16px;">' + (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.pdf_fill_form ? myCloud_LANG.pdf_fill_form : 'Fill PDF Form') + '</div>' +
            '<div style="display:flex; gap:12px;">' +
                '<button class="myCloudBtnCancel" onclick="document.getElementById(\'myCloudPdfWysiwygModal\').remove()">' + (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.cancel ? myCloud_LANG.cancel : 'Cancel') + '</button>' +
                '<button class="myCloudBtnPrimary" onclick="myCloudAction_PdfSubmitWysiwyg(\'' + path.replace(/'/g, "\\'") + '\')">' + (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.save ? myCloud_LANG.save : 'Save to PDF') + '</button>' +
            '</div>' +
        '</div>' +
        '<div id="myCloudPdfWysiwygBody" style="flex:1; overflow:auto; padding:30px; display:flex; flex-direction:column; align-items:center; gap:30px;"></div>';
    
    document.body.appendChild(modal);
    const body = document.getElementById('myCloudPdfWysiwygBody');

    // 3. Render Pages & Overlay HTML Inputs
    try {
        // Fetch raw PDF via secure POST to bypass HTML/Auth routing issues
        const fd = new URLSearchParams();
        fd.append('myCloud_action', 'pdf_get_raw');
        fd.append('myCloud_key', myCloudState.key);
        fd.append('myCloud_token', typeof myCloudCsrfToken !== 'undefined' ? myCloudCsrfToken : (window.myCloudToken || ''));
        fd.append('src', path);

        const pdfRes = await fetch('', { method: 'POST', body: fd });

        if (!pdfRes.ok) {
            throw new Error('Server rejected download: ' + pdfRes.status + ' ' + pdfRes.statusText);
        }

        const pdfBuf = await pdfRes.arrayBuffer();
        const pdf = await pdfjsLib.getDocument({ data: pdfBuf }).promise;

        for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
            const page = await pdf.getPage(pageNum);
            const viewport = page.getViewport({ scale: 1.5 }); // High-res scale

            const pageWrap = document.createElement('div');
            pageWrap.style.cssText = `position:relative; width:${viewport.width}px; height:${viewport.height}px; background:white; box-shadow:0 4px 12px rgba(0,0,0,0.15);`;

            const canvas = document.createElement('canvas');
            canvas.width = viewport.width; canvas.height = viewport.height;
            canvas.style.display = 'block';
            pageWrap.appendChild(canvas);
            await page.render({ canvasContext: canvas.getContext('2d'), viewport: viewport }).promise;

            const annotations = await page.getAnnotations();
            annotations.forEach(anno => {
                if (anno.subtype === 'Widget' && anno.fieldName) {
                    const rect = viewport.convertToViewportRectangle(anno.rect);
                    const w = Math.abs(rect[2] - rect[0]);
                    const h = Math.abs(rect[3] - rect[1]);
                    
                    let input = document.createElement(anno.multiLine ? 'textarea' : 'input');
                    if (!anno.multiLine) input.type = (anno.fieldType === 'Btn' && anno.checkBox) ? 'checkbox' : 'text';
                    
                    if (input.type === 'checkbox') {
                        input.checked = anno.fieldValue && anno.fieldValue !== 'Off';
                        input.dataset.exportValue = anno.exportValue || 'Yes';
                    } else {
                        input.value = anno.fieldValue || '';
                    }

                    input.dataset.pdfName = anno.fieldName;
                    // Stylize the input to look like a fillable PDF zone
                    input.style.cssText = `position:absolute; left:${Math.min(rect[0], rect[2])}px; top:${Math.min(rect[1], rect[3])}px; width:${w}px; height:${h}px; background:rgba(0, 120, 212, 0.15); border:1px solid rgba(0, 120, 212, 0.4); font-family:inherit; font-size:14px; padding:2px; box-sizing:border-box; z-index:10; transition:background 0.2s; resize:none;`;
                    input.onfocus = () => input.style.background = 'rgba(255, 255, 255, 0.9)';
                    input.onblur = () => input.style.background = 'rgba(0, 120, 212, 0.15)';
                    
                    pageWrap.appendChild(input);
                }
            });
            body.appendChild(pageWrap);
        }
        myCloudHideLoading();
    } catch (err) {
        console.error(err);
        myCloudHideLoading();
        document.getElementById('myCloudPdfWysiwygModal').remove();
        myCloudShowAlert('Error', 'Could not parse PDF for filling.');
    }
};

window.myCloudAction_PdfSubmitWysiwyg = function(path) {
    const data = {};
    const inputs = document.getElementById('myCloudPdfWysiwygBody').querySelectorAll('[data-pdf-name]');
    inputs.forEach(el => {
        if (el.type === 'checkbox') data[el.dataset.pdfName] = el.checked ? (el.dataset.exportValue || 'Yes') : 'Off';
        else data[el.dataset.pdfName] = el.value;
    });
    myCloudShowLoading();
    window.cePdfRun('pdf_fill_form', { data: JSON.stringify(data) }, path);
    document.getElementById('myCloudPdfWysiwygModal').remove();
};

// ============================================================
// STATE GUARD: AGGRESSIVE MODE CONFLICT PREVENTION
// ============================================================
setTimeout(function initModeGuards() {
    // 1. Intercept Commander Toggle: Shut down Office Mode BEFORE Commander opens
    if (typeof window.myCloudToggleCommander === 'function' && !window.ceCmdOfficePatch) {
        window.ceCmdOfficePatch = true;
        const origCmd = window.myCloudToggleCommander;
        
        window.myCloudToggleCommander = function(force) {
            // Fetch state dynamically right when the button is clicked
            const st = window.myCloudState || (typeof myCloudState !== 'undefined' ? myCloudState : null);
            
            if (st) {
                const nextCmd = typeof force !== 'undefined' ? force : !st.isCommanderMode;
                if (nextCmd && st.isOfficeMode && typeof window.myCloudToggleOffice === 'function') {
                    window.myCloudToggleOffice(false); // Graceful shutdown
                }
            }
            return origCmd.apply(this, arguments);
        };
    }

    // 2. Tree Width Segregation Guard (Protects normal width from being overwritten while in Office Mode)
    const tree = document.querySelector('.myCloudTree') || document.getElementById('myCloudTree');
    if (tree && !window.ceTreeWidthGuardPatched) {
        window.ceTreeWidthGuardPatched = true;
        const observer = new MutationObserver(() => {
            const st = window.myCloudState || (typeof myCloudState !== 'undefined' ? myCloudState : null);
            const devKey = typeof myCloudGetCurrentDeviceKey === 'function' ? myCloudGetCurrentDeviceKey() : 'desktop';
            if (!st || !st.settings || !st.settings[devKey]) return;

            const currentWidth = parseInt(tree.style.width);
            if (!currentWidth) return;

            if (st.isOfficeMode) {
                st.settings[devKey].officeTreeWidth = currentWidth;
                if (st.normalTreeWidthCache) {
                    st.settings[devKey].treeWidth = st.normalTreeWidthCache; // Block main script from corrupting normal width
                }
            } else {
                st.normalTreeWidthCache = currentWidth;
            }
        });
        observer.observe(tree, { attributes: true, attributeFilter: ['style'] });
    }

    // 3. Failsafe loop: If state ever conflicts illegally, murder the Office View elements
    setInterval(() => {
        // Fetch state dynamically on every tick
        const st = window.myCloudState || (typeof myCloudState !== 'undefined' ? myCloudState : null);
        
        if (st && st.isOfficeMode && (st.isCommanderMode || st.interface === 'gallery')) {
            st.isOfficeMode = false;
            
            const body = document.querySelector('.myCloudBody');
            if (body) body.classList.remove('office-mode');
            
            const pane = document.getElementById('myCloudPreviewPane');
            if (pane) pane.remove();
            
            const resizer = document.getElementById('myCloudOfficeResizer');
            if (resizer) resizer.remove();
        }
    }, 500);
}, 100);

</script>