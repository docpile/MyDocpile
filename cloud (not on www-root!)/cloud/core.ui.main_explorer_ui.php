<?php
/**
 * ============================================================================
 * MODULE: Primary Explorer Layout Generator
 * ============================================================================
 * Renders the core structural DOM of the file explorer, including the interactive 
 * file table, the collapsible directory tree, and the main application wrapper.
 * NOTE: Executed exclusively by the client browser.
 * 
 * THIS IS PART OF THE PSEUDO "JS FILE" - SO THE NO PHP CODE IN THIS FILE!
 */

?><script>

// List View Thumbnail Observer Constant
const listIconObserver = new IntersectionObserver((entries, obs) => {

    entries.forEach(entry => {
        if (entry.isIntersecting) {
            const el = entry.target;
            const path = el.dataset.path;
            const name = el.dataset.filename;
            obs.unobserve(el);

            const cacheKey = path + '_thumb';
            const st = myCloudState;

			// Helper to apply image
            const applyImg = (src, customImg = null) => {
                // Create image element
                const img = customImg || document.createElement('img');
                if (!customImg) img.src = src;
                img.className = 'ce-list-thumb-img';
                img.draggable = false;

                // [NEW] Mobile "Peek" (Touch & Hold to zoom)
                // We use a small delay to distinguish from a scrolling swipe
                let peekTimer = null;

                const startPeek = (e) => {
                    // Prevent default to stop browser context menu or image save
                    // e.preventDefault(); // Optional: Uncomment if context menu interferes, but might block scrolling start
                    peekTimer = setTimeout(() => {
                        img.classList.add('ce-touch-expanded');
                        // Mobile vibration feedback (if supported)
                        if (navigator.vibrate) navigator.vibrate(10);
                    }, 200);
                };

                const endPeek = (e) => {
                    if (peekTimer) clearTimeout(peekTimer);
                    img.classList.remove('ce-touch-expanded');
                };

                img.addEventListener('touchstart', startPeek, { passive: true });
                img.addEventListener('touchend', endPeek, { passive: true });
                img.addEventListener('touchcancel', endPeek, { passive: true });
                
                // Safety: Hide if scrolled away while holding
                window.addEventListener('scroll', endPeek, { passive: true });

                el.innerHTML = '';
                el.appendChild(img);
            };
            // Check Cache
            if (st.previewCache && st.previewCache[cacheKey]) {
                applyImg(st.previewCache[cacheKey]);
                return;
            }

            const root = typeof myCloudCrypto !== 'undefined' ? myCloudCrypto.getCryptoRoot(path) : null;
            const isEnc = path.endsWith('.enc') || root;
            if (isEnc) {
                if (!root || !myCloudCrypto.isDirUnlocked(root)) return;
                const fd = new URLSearchParams({ myCloud_action: 'get_download_token', myCloud_key: st.key, myCloud_token: typeof myCloudCsrfToken !== 'undefined' ? myCloudCsrfToken : '', path: path, filename: name, preview: '1', is_icon: '1' });
                fetch('', { method: 'POST', body: fd }).then(r=>r.json()).then(async resp => {
                    if (resp.status === 'OK') {
                        const r2 = await fetch(window.location.pathname + '?myCloud_token=' + resp.token);
                        const encBlob = await r2.blob();
                        const decBlob = await myCloudCrypto.decryptFile(root, encBlob);
                        const url = URL.createObjectURL(decBlob);
                        if (!st.previewCache) st.previewCache = {};
                        st.previewCache[cacheKey] = url;
                        applyImg(url);
                    }
                }).catch(()=>{});
                return;
            }

            // Fast Path
            const fastUrl = window.myCloudGetFastThumbUrl(path);
            if (fastUrl) {
                st.previewCache[cacheKey] = fastUrl;
                const img = document.createElement('img');
                
                img.onerror = () => {
                    const fd = new URLSearchParams({ myCloud_action: 'get_download_token', myCloud_key: st.key, myCloud_token: myCloudCsrfToken, path: path, filename: name, preview: '1', is_icon: '1' });
                    fetch('', { method: 'POST', body: fd }).then(r=>r.json()).then(resp => {
                        if (resp.status === 'OK') {
                            const url = '?myCloud_token=' + resp.token;
                            st.previewCache[cacheKey] = url;
                            img.src = url;
                        }
                    }).catch(() => {});
                };
                img.src = fastUrl;
                applyImg(fastUrl, img);
                return;
            }

            // Fetch
            const fd = new URLSearchParams();
            fd.append('myCloud_action', 'get_download_token');
            fd.append('myCloud_key', st.key);
            fd.append('myCloud_token', myCloudCsrfToken);
            fd.append('path', path);
            fd.append('filename', name);
            fd.append('preview', '1');
            fd.append('is_icon', '1');

            fetch('', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(resp => {
                if (resp.status === 'OK') {
                    const url = '?myCloud_token=' + resp.token;
                    if(!st.previewCache) st.previewCache = {};
                    st.previewCache[cacheKey] = url;
                    applyImg(url);
                }
            })
            .catch(() => {}); // Keep icon on error
        }
    });
}, { rootMargin: "100px" });

// ============================================================
// BREADCRUMB RENDERER
// ============================================================
function myCloudRenderMainBreadcrumbs(container, path) {
    container.innerHTML = '';
    
    const rootSpan = document.createElement('span');
    rootSpan.className = 'ce-crumb-segment';
    rootSpan.innerHTML = myCloudIconFolder; 
    rootSpan.onclick = (e) => { 
        e.stopPropagation(); 
        if (myCloudState.currentDir !== '/') myCloudHandleEnter({name: '/', size: 'DIR'}); 
    };
    container.appendChild(rootSpan);
    
    if (path !== '/') {
        const parts = path.split('/').filter(p => p);
        let walker = '';
        
        parts.forEach((part, index) => {
            walker += '/' + part;
            const currentPath = walker; 
            const parentPath = walker.substring(0, walker.lastIndexOf('/')) || '/';
            
            const sepWrap = document.createElement('span');
            sepWrap.className = 'ce-crumb-sep';
            sepWrap.textContent = '›';
            sepWrap.style.cursor = 'pointer';
            sepWrap.title = "View subfolders";
            
            sepWrap.onclick = (e) => {
                e.stopPropagation();
                myCloudCloseContextMenus();
                
                const rect = sepWrap.getBoundingClientRect();
                const menu = document.createElement('div');
                menu.className = 'myCloudContextMenu';
                menu.style.top = (rect.bottom + 2) + 'px';
                menu.style.left = rect.left + 'px';
                menu.style.maxHeight = '300px';
                menu.style.overflowY = 'auto';
                menu.innerHTML = '<div style="padding:10px; font-size:12px; color:#888;">Loading...</div>';
                document.body.appendChild(menu);

                myCloudFetchDirectory(parentPath, 1, true).then(resp => {
                    menu.innerHTML = '';
                    const subDirs = resp.data.filter(i => i.size === 'DIR' && i.name !== '/.recycle_bin');
                    if (subDirs.length === 0) {
                        menu.innerHTML = '<div style="padding:10px; font-size:12px; color:#888;">No subfolders</div>';
                        return;
                    }
                    subDirs.forEach(dir => {
                        const item = document.createElement('div');
                        item.className = 'myCloudContextItem';
                        item.innerHTML = '<span class="myCloudIcon" style="width:16px;height:16px;margin-right:8px;">' + myCloudIconFolder + '</span>' + dir.name.split('/').pop();
                        item.onclick = () => { menu.remove(); myCloudHandleEnter({name: dir.name, size: 'DIR'}); };
                        menu.appendChild(item);
                    });
                });
                setTimeout(() => { document.addEventListener('click', () => menu.remove(), {once: true}); }, 50);
            };
            container.appendChild(sepWrap);
            
            const seg = document.createElement('span');
            seg.className = 'ce-crumb-segment';
                let dName = (myCloudState.pathNames && myCloudState.pathNames[currentPath]) ? myCloudState.pathNames[currentPath] : part.replace('.enc', '');
                if (typeof myCloudCrypto !== 'undefined' && myCloudCrypto.isDirEncrypted(currentPath)) {
                    let isUnlocked = myCloudCrypto.isDirUnlocked(myCloudCrypto.getCryptoRoot(currentPath));
                    dName = (isUnlocked ? '🔓 ' : '🔒 ') + dName;
                }
                if (part === '.recycle_bin') {
                     seg.textContent = (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.recycle_bin) ? myCloud_LANG.recycle_bin : 'Recycle Bin';
                } else {
                    seg.textContent = dName;
                }
           
            if (index === parts.length - 1) {
                 seg.classList.add('active');
            } else {
                 seg.onclick = (e) => { e.stopPropagation(); myCloudHandleEnter({name: currentPath, size: 'DIR'}); };
            }
            
            container.appendChild(seg);
        });
    }
	
    // --- TAG FILTER UI (Standard View) ---
    const spacer = document.createElement('div');
    spacer.style.flex = '1';
    container.appendChild(spacer);

    if (window.myCloudActionAllowed('edit_tags')) {
        const filterWrap = document.createElement('div');
        filterWrap.style.display = 'flex';
        filterWrap.style.alignItems = 'center';
        filterWrap.style.gap = '6px';
        filterWrap.style.padding = '8px 16px';

        const colors = (myCloudState.settings && myCloudState.settings.visibleTags) ? myCloudState.settings.visibleTags : ['#e81123', '#0078d4', '#107c10', '#f0ad4e', '#888888'];
        colors.forEach(c => {
            const dot = document.createElement('div');
            dot.className = 'ce-tag-dot';
            dot.style.backgroundColor = c;
            dot.style.cursor = 'pointer';
            dot.style.border = '2px solid transparent';
            dot.style.backgroundClip = 'padding-box';
            dot.style.width = '18px'; /* 10px visual color + 10px invisible click border */
            dot.style.height = '18px';
            dot.style.margin = '0'; /* The invisible border acts as the gap now */
           dot.title = window.myCloudGetTagName(c);
            if (myCloudState.activeTagFilter === c) {
                dot.style.boxShadow = 'inset 0 0 0 2px var(--gray-00), 0 0 0 4px ' + c;
            }
            dot.onclick = (e) => {
                e.stopPropagation();
                myCloudState.activeTagFilter = (myCloudState.activeTagFilter === c) ? null : c;
                myCloudRenderUI();
            };
            filterWrap.appendChild(dot);
        });

        if (myCloudState.activeTagFilter) {
            const clear = document.createElement('span');
            clear.innerHTML = '✕';
            clear.style.cursor = 'pointer';
            clear.style.fontSize = '12px';
            clear.style.marginLeft = '4px';
            clear.style.color = 'var(--text-secondary)';
            clear.onclick = (e) => { e.stopPropagation(); myCloudState.activeTagFilter = null; myCloudRenderUI(); };
            filterWrap.appendChild(clear);
        }
        
        // --- Dropdown Button for Tags ---
        const ddWrap = document.createElement('div');
        ddWrap.className = 'myCloud-tag-dropdown-wrapper';
        
        const ddBtn = document.createElement('button');
        ddBtn.className = 'myCloud-tag-dropdown-btn';
        ddBtn.style.color = 'var(--text-primary)';
        ddBtn.style.borderColor = 'var(--border-default)';
        ddBtn.innerHTML = (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.tag_labels ? myCloud_LANG.tag_labels : 'Tags') + ' ▾';
        
        ddBtn.onclick = (e) => {
            e.stopPropagation();
            let existing = document.getElementById('myCloudTagDropdown');
            if (existing) {
                existing.remove();
                if (existing.dataset.source === 'main') return; 
            }
            
            const ddMenu = document.createElement('div');
            ddMenu.id = 'myCloudTagDropdown';
            ddMenu.dataset.source = 'main';
            ddMenu.className = 'myCloud-tag-dropdown-menu show';
            ddMenu.style.background = 'var(--gray-00)';
            ddMenu.style.borderColor = 'var(--border-default)';
            ddMenu.style.position = 'fixed';
            ddMenu.style.zIndex = '21000';
            
            colors.forEach(c => {
                const item = document.createElement('div');
                item.className = 'myCloud-tag-dropdown-item';
                item.style.color = 'var(--text-primary)';
                item.innerHTML = '<span class="myCloud-tag-color-dot" style="background-color:' + c + '"></span><span>' + window.myCloudGetTagName(c) + '</span>';
                if (myCloudState.activeTagFilter === c) item.style.backgroundColor = 'var(--hover-bg-medium)';
                
                item.onmouseenter = () => item.style.backgroundColor = 'var(--hover-bg-light)';
                item.onmouseleave = () => item.style.backgroundColor = (myCloudState.activeTagFilter === c) ? 'var(--hover-bg-medium)' : 'transparent';
                
                item.onclick = (ev) => {
                    ev.stopPropagation();
                    myCloudState.activeTagFilter = (myCloudState.activeTagFilter === c) ? null : c;
                    ddMenu.remove();
                    myCloudRenderUI();
                };
                ddMenu.appendChild(item);
            });
            
            document.body.appendChild(ddMenu);
			if (typeof myCloudApplyTheme === 'function') myCloudApplyTheme();
            
            const rect = ddBtn.getBoundingClientRect();
            ddMenu.style.top = (rect.bottom + 4) + 'px';
            let left = rect.right - ddMenu.offsetWidth;
            if (left < 5) left = 5;
            ddMenu.style.left = left + 'px';
            ddMenu.style.right = 'auto';
            
            setTimeout(() => {
                const closer = (ev) => {
                    if (!ddMenu.contains(ev.target)) {
                        ddMenu.remove();
                        document.removeEventListener('click', closer);
                    }
                };
                document.addEventListener('click', closer);
            }, 10);
        };
        
        ddWrap.appendChild(ddBtn);
        filterWrap.appendChild(ddWrap);

        container.appendChild(filterWrap);
    }

    // --- COMMAND PALETTE BUTTON (STANDARD) ---
    const cmdBtnWrap = document.createElement('div');
    cmdBtnWrap.style.marginLeft = '12px';
    cmdBtnWrap.style.display = 'flex';
    cmdBtnWrap.style.alignItems = 'center';

    const cmdBtn = document.createElement('button');
    cmdBtn.className = 'myCloud-tag-dropdown-btn';
    cmdBtn.style.color = 'var(--text-primary)';
    cmdBtn.style.borderColor = 'var(--border-default)';
    cmdBtn.title = 'Command Palette (Ctrl+P)';
    cmdBtn.innerHTML = '<span class="myCloudIcon" style="width:14px;height:14px;margin-right:2px;">' + (typeof myCloudSvg !== 'undefined' ? myCloudSvg.search : '🔍') + '</span> ' ;

    cmdBtn.onmousedown = (e) => e.stopPropagation();
	cmdBtn.onclick = (e) => {
        e.stopPropagation();
        if (typeof myCloudShowCommandPalette === 'function') myCloudShowCommandPalette();
    };
    cmdBtnWrap.appendChild(cmdBtn);
    container.appendChild(cmdBtnWrap);
   
    setTimeout(() => { container.scrollLeft = container.scrollWidth; }, 10);
}


// Main function to render the Explorer UI (Tree and File List)
// Handles state updates, visual layout, and event binding
function myCloudRenderUI() {
	
	//Close any lingering context menus when view refreshes
    if (typeof myCloudCloseContextMenus === 'function') myCloudCloseContextMenus();
    
	myCloudHideLoading();
	

    // --- WEBMAIL APP INTERCEPT ---
    // Must happen before ANY tree, list, or commander rendering logic
    if (myCloudState.interface === 'email') {
        const tree = document.querySelector('.myCloudTree');
        const resizer = document.querySelector('.myCloudResizer');
        const body = document.querySelector('.myCloudBody');
        
        if (tree) tree.style.display = 'none';
        if (resizer) resizer.style.display = 'none';
        if (body) body.classList.remove('office-mode', 'commander-mode');
        
        const details = document.querySelector('.myCloudDetails');
        if (details && typeof myCloudRenderEmailApp === 'function') {
            details.innerHTML = '';
            details.style.display = 'flex';
            details.style.flexDirection = 'column';
            myCloudRenderEmailApp(details);
        }
        return;
    }

    // --- COMMANDER MODE INTERCEPTION ---
    // If in Commander Mode, update specific panes instead of the standard list
    if (myCloudState.isCommanderMode) {
        // Update Left Pane if it exists
        const leftPane = document.querySelector('.myCloud-commander-pane[data-side="left"]');
        if (leftPane) {
            const content = leftPane.querySelector('.myCloud-commander-content');
            // Re-render using latest data
            renderCommanderContent(content, myCloudState.commanderLeft, 'left');
        }
        
        // Update Right Pane if it exists
        const rightPane = document.querySelector('.myCloud-commander-pane[data-side="right"]');
        if (rightPane) {
            const content = rightPane.querySelector('.myCloud-commander-content');
            renderCommanderContent(content, myCloudState.commanderRight, 'right');
        }
        return; // Stop standard rendering
    }
    // -----------------------------------
	
	
    var st = myCloudState;
    var items = st.allItems || [];
	
	
	// [FIX] Capture scroll position to prevent jumping to top on refresh/sort
    var details = document.querySelector('.myCloudDetails');
    var scrollTop = details ? details.scrollTop : 0;
    
    // Determine permission levels for the current directory
    var isInsideZip = myCloudIsInsideZip(st.currentDir);
	
    // [NEW] Single Click Mode Logic (Not for Gallery)
    const devKey = myCloudGetCurrentDeviceKey();
    const isSingleClick = st.settings && st.settings[devKey] && st.settings[devKey].singleClick && st.interface !== 'gallery' && st.viewMode !== 'gallery';
    
    const mainContainer = document.getElementById('myCloudContainer');
    if(isSingleClick) mainContainer.classList.add('ce-single-click-mode'); else mainContainer.classList.remove('ce-single-click-mode');

    // 1. DYNAMIC CSS INJECTION
    // Injects required styles for layout, scrollbars, and dropzones if missing
    if (!document.getElementById('myCloudExtraStyles')) {
        var style = document.createElement('style');
        style.id = 'myCloudExtraStyles';
		style.innerHTML = 
             ".myCloudDetails { display: flex; flex-direction: column; overflow-y: auto; overflow-x: auto; height: 100%; }" +
             ".myCloudTableContainer { display: flex; flex-direction: column; flex: 1; min-height: 0; }" +
            ".myCloud-dropzone { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; margin: 0; padding: 20px; background: transparent !important; border: 1px dashed transparent !important; color: var(--text-secondary) !important; opacity: 0; cursor: default; transition: all 0.2s ease; min-height: 150px; }" +
            ".myCloud-dropzone:hover { opacity: 0.5; border-color: var(--border-medium) !important; }" +
            ".myCloud-dropzone.drag-active { opacity: 1; background: var(--hover-bg-light) !important; border-color: var(--accent-primary) !important; color: var(--accent-primary) !important; }" +
            ".myCloudRow.drop-target { background-color: rgba(0, 120, 212, 0.1) !important; outline: 2px dashed var(--accent-primary) !important; outline-offset: -2px; }" +
             "@media (max-width: 768px) { .ce-admin-col { display: none !important; } }";
         document.head.appendChild(style);
		 }

    // 2. BUILD TREE DATA STRUCTURE
    // Transforms flat file list into a nested object for the sidebar tree
    var treeMap = {};
    // --- GLOBAL TAG FILTER: SPARSE TREE GENERATION ---
    // --- GLOBAL TAG FILTER: SPARSE TREE GENERATION ---
    let treeSourceItems = [...items];
    if (st.activeTagFilter) {
        const tags = (st.tags && st.tags[st.key]) ? st.tags[st.key] : {};
        const taggedPaths = Object.keys(tags).filter(p => {
            let t = tags[p];
            if (t && !Array.isArray(t)) t = [t];
            return t && t.includes(st.activeTagFilter);
        });
        
        // Inject virtual parent directories so the tree connects all the way to the root
        taggedPaths.forEach(tp => {
            let parts = tp.split('/').filter(x => x);
            let walker = '';
            parts.forEach(part => {
                walker += '/' + part;
                if (!treeSourceItems.some(i => i.name === walker)) treeSourceItems.push({ name: walker, size: 'DIR' });
            });
        });
        
        // Shrink tree to only show paths leading to tagged items
        treeSourceItems = treeSourceItems.filter(i => {
            let t = tags[i.name];
            if (t && !Array.isArray(t)) t = [t];
            if (t && t.includes(st.activeTagFilter)) return true;
            return taggedPaths.some(tp => {
                if (i.name === '/') return true;
                // 1. Path leads DOWN to a tag
                if (tp.startsWith(i.name + '/')) return true;
                // 2. Path inherits FROM a tag
                if (tp === '/') return true;
                if (i.name.startsWith(tp + '/')) return true;
                return false;
            });
        });
    }

    treeSourceItems.filter(function(i) {
	if (i.name.startsWith('/.recycle_bin/')) return false;
		if (i.name === '/.recycle_bin' && !window.myCloudActionAllowed('delete')) return false;
        return i.size === 'DIR' || (i.name.toLowerCase().endsWith('.zip') && st.loadedDirs.includes(i.name));
    }).forEach(function(i) {
        var parts = i.name.split('/').filter(function(p) { return p; });
        var node = treeMap;
        parts.forEach(function(part, idx) {
            node[part] = node[part] || { __children: {}, isLink: false };
            if (idx === parts.length - 1) {
                node[part].isLink = (i.isLink === true);
            }
            if (idx < parts.length - 1) node = node[part].__children;
        });
    });

    var tree = document.querySelector('.myCloudTree');
    tree.innerHTML = '';

    // Helper: Recursively builds the HTML UL/LI structure for the tree
    var buildTree = function(obj, path) {
        var ul = document.createElement('ul');
        ul.className = 'myCloudTreeList';
        
        Object.keys(obj).sort(function(a, b) {
            if (a === '.recycle_bin') return 1;
            if (b === '.recycle_bin') return -1;
            return a.toLowerCase().localeCompare(b.toLowerCase());
        }).forEach(function(key) {
            if (key === '__children') return;
            
            var fullPath = (path === '/') ? '/' + key : path + '/' + key;
            var hasChildren = Object.keys(obj[key].__children).length > 0;
            var isOpen = st.openDirs.includes(fullPath);
			var isRecycleBin = (key === '.recycle_bin');
            var isZip = fullPath.toLowerCase().endsWith('.zip');
			var isLink = obj[key].isLink === true;

            // Persistent encryption checking across directory boundaries
            var isEncrypted = myCloudState.encryptedDirs && myCloudState.encryptedDirs.has(fullPath);

            var li = document.createElement('li');
            if (fullPath === st.currentDir) li.classList.add('selectedFolder');

            var div = document.createElement('div');
            div.dataset.fullpath = fullPath;

            if (isZip) {
                div.style.color = '#673AB7';
                div.style.fontWeight = '500';
            }

            // Tree Item Interactions (Click, Context Menu, Touch)
             div.onclick = function() {
                 // E2E Pre-Navigation Trap for Tree View
                 const isNodeEnc = typeof myCloudCrypto !== 'undefined' && myCloudCrypto.isDirEncrypted(fullPath);
                 if (isNodeEnc && !myCloudCrypto.isDirUnlocked(fullPath)) {
                     myCloudAction_EncryptPrompt(myCloudCrypto.getCryptoRoot(fullPath), true, () => {
						div.click(); // Re-trigger automatically once unlocked
                     });
                     return;
                 }
                 // Use effective view mode of the TARGET folder immediately
                 if (typeof myCloudGetEffectiveViewMode === 'function') {
                     st.viewMode = myCloudGetEffectiveViewMode(fullPath);
                 } else { st.viewMode = 'list'; }
                 st.currentDir = fullPath;
                 st.selectedFiles = [];
                 st.currentFile = null;
                 if (!st.openDirs.includes(fullPath)) {
                     st.openDirs.push(fullPath);
                 }
                 // Always refresh Recycle Bin on click to show latest deleted items
                 if (isRecycleBin || !st.loadedDirs.includes(fullPath)) {
                     myCloudFetchDirectory(fullPath);
                 }
                 myCloudRenderUI();
				 if (typeof myCloudUpdateOfficePreview === 'function') myCloudUpdateOfficePreview();
             };

            div.oncontextmenu = function(e) {
                e.preventDefault(); e.stopPropagation();
                st.currentDir = fullPath;
                st.selectedFiles = [fullPath];
                myCloudRenderUI();
                myCloudShowContextMenu(e, { name: fullPath, size: 'DIR' }, true);
            };

             div.addEventListener('touchstart', function(e) {
                 myCloudTouchTimer = setTimeout(function() {
                     // Inherit view mode from parent before switching dir
                     if (typeof myCloudGetEffectiveViewMode === 'function') {
                         st.viewMode = myCloudGetEffectiveViewMode(fullPath);
                     }
                     st.currentDir = fullPath;
                     st.selectedFiles = [];
                     myCloudRenderUI();
                     myCloudTouchTimer = setTimeout(function() {
                         myCloudIsLongPress = true;
                         myCloudShowContextMenu(e, { name: fullPath, size: 'DIR' }, true);
                     }, 600);
                 }, 200);
             }, { passive: true });

            div.addEventListener('touchend', myCloudHandleTouchEnd, { passive: true });
            div.addEventListener('touchmove', myCloudHandleTouchMove, { passive: true });

            // Tree Drag & Drop Logic
            if (window.myCloudActionAllowed('move') && !isInsideZip) {
                div.addEventListener('dragover', function(e) {
                    e.preventDefault(); e.stopPropagation();
                    div.classList.add('drop-target');
                    e.dataTransfer.dropEffect = e.ctrlKey ? 'copy' : 'move';
                });

                div.addEventListener('dragleave', function(e) {
                    if (!div.contains(e.relatedTarget)) div.classList.remove('drop-target');
                });

                div.addEventListener('drop', function(e) {
					e.preventDefault(); e.stopPropagation();
					div.classList.remove('drop-target');
					// Handle Internal Move/Copy
					try {
						var textData = e.dataTransfer.getData('text/plain');
						if (textData) {
							var paths = JSON.parse(textData);
							if (!paths.includes(fullPath)) {
								// [FIX] FORCE MOVE if dragging TO the Recycle Bin (or already FROM it)
								var isSourceBin = paths.some(function(p) { return p.indexOf('/.recycle_bin') === 0; });
								var isTargetBin = fullPath.indexOf('/.recycle_bin') === 0 || fullPath === '/.recycle_bin';
								
								// If target is bin, ALWAYS delete (force move, show delete dialog)
								if (isTargetBin) {
									myCloudShowDeleteConfirm(paths.length, false, function() {
										myCloudBatchProcess('move', paths, fullPath);
									});
									return;
								}
								
								// Standard logic for non-bin targets
								var op = (e.ctrlKey && !isSourceBin) ? 'copy' : 'move';
								
								myCloudShowDragConfirm(op, paths, fullPath, function(preserve) {
									myCloudBatchProcess(op, paths, fullPath, preserve);
								});
							}
						}
					} catch (ex) {}
					// Handle External File Upload
					if (e.dataTransfer.items && e.dataTransfer.items.length > 0) {
						myCloudScanItems(e.dataTransfer.items, fullPath);
					}
				});			
            }

            // Render Toggle Arrow
            var toggle = document.createElement('span');
            toggle.className = 'myCloudToggle';
            toggle.innerHTML = hasChildren ? (isOpen ? '&#9662;' : '&#9656;') : '';
            
            if (hasChildren) {
                toggle.onclick = function(e) {
                    e.stopPropagation();
                    const isNodeEnc = typeof myCloudCrypto !== 'undefined' && myCloudCrypto.isDirEncrypted(fullPath);
                    if (!isOpen && isNodeEnc && !myCloudCrypto.isDirUnlocked(fullPath)) {
                        myCloudAction_EncryptPrompt(myCloudCrypto.getCryptoRoot(fullPath), true, () => {
                            toggle.click(); // Re-trigger automatically once unlocked
                        });
                        return;
                    }
					var childUl = li.querySelector('ul');
                    if (isOpen) {
                        st.openDirs = st.openDirs.filter(function(d) { return d !== fullPath; });
                        if (childUl) {
                            childUl.style.display = 'none';
                            toggle.innerHTML = '&#9656;';
                        }
                    } else {
                        st.openDirs.push(fullPath);
                        
                        // If we have children in DOM, just show them
                        if (childUl) {
                            childUl.style.display = 'block';
                            toggle.innerHTML = '&#9662;';
                        } else {
                            // If data missing, fetch and partial render
                            toggle.innerHTML = '...';
                            myCloudFetchDirectory(fullPath).then(function() {
                                // Re-render this specific node's children only
                                if (treeMap[key] && treeMap[key].__children) {
                                    var newUl = buildTree(treeMap[key].__children, fullPath);
                                    li.appendChild(newUl);
                                    toggle.innerHTML = '&#9662;';
                                }
                            });
                            return; // Skip full render
                        }
                    }
                    // myCloudRenderUI();
                };
            }
            div.appendChild(toggle);

            // Render Folder Icon
            var icon = document.createElement('span');
            icon.className = 'myCloudIcon';
           if (isRecycleBin) {
               icon.innerHTML = myCloudSvg.recycle_main;
               icon.style.color = '';
           } else if (isZip) {
               icon.innerHTML = myCloudIconZipFolder;
           } else if (isLink) {
               icon.innerHTML = myCloudIconLinkFolder;
		   } else {
               icon.innerHTML = myCloudIconFolder;
           }
            div.appendChild(icon);

            // Render Folder Name
            var nameSpan = document.createElement('span');
            if (isRecycleBin) {
                nameSpan.textContent = myCloud_LANG.recycle_bin;
                nameSpan.style.color = '#757575';
            } else {
                let dName = (myCloudState.pathNames && myCloudState.pathNames[fullPath]) ? myCloudState.pathNames[fullPath] : key.replace('.enc', '');
                if (typeof myCloudCrypto !== 'undefined' && myCloudCrypto.isDirEncrypted(fullPath)) {
                    let isUnlocked = myCloudCrypto.isDirUnlocked(myCloudCrypto.getCryptoRoot(fullPath));
                    dName = (isUnlocked ? '🔓 ' : '🔒 ') + dName;
                }
                nameSpan.textContent = dName;
            }
			
            div.appendChild(nameSpan);

            // Tag Logic for Tree View Nodes
            if (window.myCloudActionAllowed('edit_tags')) {
                const tags = (st.tags && st.tags[st.key]) ? st.tags[st.key] : {};
                if (tags[fullPath]) {
                    let itemTags = tags[fullPath];
                    if (!Array.isArray(itemTags)) itemTags = [itemTags];
                    const tagStack = document.createElement('div');
                    tagStack.className = 'ce-tag-stack';
                    itemTags.forEach((c, idx) => {
                        const tagDot = document.createElement('span');
                        tagDot.className = 'ce-tag-dot';
                        tagDot.style.backgroundColor = c;
                        tagDot.style.zIndex = itemTags.length - idx;
                        tagDot.title = window.myCloudGetTagName(c);
                        tagStack.appendChild(tagDot);
                    });
                    div.appendChild(tagStack);
                }
            }

            li.appendChild(div);
            if (isOpen && hasChildren) li.appendChild(buildTree(obj[key].__children, fullPath));
            ul.appendChild(li);
        });
        return ul;
    };

    // 3. RENDER ROOT NODE (/)
    var rootUl = document.createElement('ul');
    rootUl.className = 'myCloudTreeList';
    var rootLi = document.createElement('li');
    if (st.currentDir === '/') rootLi.classList.add('selectedFolder');
    
    var rootDiv = document.createElement('div');
    rootDiv.dataset.fullpath = '/';

    rootDiv.onclick = function() {
		if (typeof myCloudGetEffectiveViewMode === 'function') {
            st.viewMode = myCloudGetEffectiveViewMode('/');
        } else { st.viewMode = 'list'; }
        st.currentDir = '/';
        st.selectedFiles = [];
        st.currentFile = null;
        myCloudRenderUI();
    };

    rootDiv.oncontextmenu = function(e) {
        e.preventDefault();
        st.currentDir = '/';
        st.selectedFiles = [];
        myCloudRenderUI();
        myCloudShowContextMenu(e, { name: '/', size: 'DIR' }, true);
    };
 
     rootDiv.addEventListener('touchstart', function(e) {
         myCloudTouchTimer = setTimeout(function() {
             if (typeof myCloudGetEffectiveViewMode === 'function') {
                 st.viewMode = myCloudGetEffectiveViewMode('/');
             }
             st.currentDir = '/';
             st.selectedFiles = [];
             myCloudRenderUI();
             myCloudTouchTimer = setTimeout(function() {
                 myCloudShowContextMenu(e, { name: '/', size: 'DIR' }, true);
             }, 600);
         }, 200);
     }, { passive: true });
	
   rootDiv.addEventListener('touchend', myCloudHandleTouchEnd, { passive: true });
   rootDiv.addEventListener('touchmove', myCloudHandleTouchMove, { passive: true });

    // Root Node Drag & Drop
    if (window.myCloudActionAllowed('upload') && !isInsideZip) {
        rootDiv.addEventListener('dragover', function(e) {
            e.preventDefault(); e.stopPropagation();
            rootDiv.classList.add('drop-target');
            e.dataTransfer.dropEffect = e.ctrlKey ? 'copy' : 'move';
        });
        rootDiv.addEventListener('dragleave', function(e) {
            if (!rootDiv.contains(e.relatedTarget)) rootDiv.classList.remove('drop-target');
        });
        rootDiv.addEventListener('drop', function(e) {
			e.preventDefault(); e.stopPropagation();
			rootDiv.classList.remove('drop-target');
			
			try {
				var textData = e.dataTransfer.getData('text/plain');
				if (textData) {
					var paths = JSON.parse(textData);
					// [FIX] Force MOVE if dragging FROM the Recycle Bin
					var isSourceBin = paths.some(function(p) { return p.indexOf('/.recycle_bin') === 0; });
					var op = (e.ctrlKey && !isSourceBin) ? 'copy' : 'move';
					myCloudShowDragConfirm(op, paths, '/', function(preserve) {
						myCloudBatchProcess(op, paths, '/', preserve);
					});
				}
			} catch(ex){}
			if (e.dataTransfer.items && e.dataTransfer.items.length > 0) {
				myCloudScanItems(e.dataTransfer.items, '/');
			}
		}); 	
    }

    var rootIcon = document.createElement('span');
    rootIcon.className = 'myCloudIcon';
    rootIcon.innerHTML = myCloudIconFolder;
    rootDiv.appendChild(rootIcon);
	

    var rootName = document.createElement('span');
    rootName.textContent = '/';
    rootDiv.appendChild(rootName);
    // Tag Logic for Root Node
    if (window.myCloudActionAllowed('edit_tags')) {
        const tags = (st.tags && st.tags[st.key]) ? st.tags[st.key] : {};
        if (tags['/']) {
            let itemTags = tags['/'];
            if (!Array.isArray(itemTags)) itemTags = [itemTags];
            const tagStack = document.createElement('div');
            tagStack.className = 'ce-tag-stack';
            itemTags.forEach((c, idx) => {
                const rootTagDot = document.createElement('span');
                rootTagDot.className = 'ce-tag-dot';
                rootTagDot.style.backgroundColor = c;
                rootTagDot.style.zIndex = itemTags.length - idx;
                rootTagDot.title = window.myCloudGetTagName(c);
                tagStack.appendChild(rootTagDot);
            });
            rootDiv.appendChild(tagStack);
        }
    }

    rootLi.appendChild(rootDiv);
    rootLi.appendChild(buildTree(treeMap, '/'));
    rootUl.appendChild(rootLi);
    tree.appendChild(rootUl);

    // 4. PREPARE DETAILS PANE
    var details = document.querySelector('.myCloudDetails');
	details.classList.remove('symbol-dark-container');
    details.classList.remove('is-gallery-interface');
    
    if (st.interface === 'gallery') {
        details.classList.add('is-gallery-interface');
    }

    details.innerHTML = '';
    details.style.display = 'flex';
    details.style.flexDirection = 'column';
    details.style.overflow = '';
    details.style.height = '';
	
    //  Background Context Menu (Standard View)
    details.oncontextmenu = function(e) {
        if (e.target.closest('.myCloudRow, th, .myCloud-breadcrumb-bar, .myCloud-symbol-item')) return;
        e.preventDefault();
        e.stopPropagation();
        if (typeof myCloudShowBackgroundContextMenu === 'function') myCloudShowBackgroundContextMenu(e);
    };

    myCloudRenderToolbar();
    // [FIX] Restore toolbar visibility after loading sequence
    const tb = document.getElementById('myCloudToolbar');
    if (tb) tb.style.opacity = '';


     // Check for Symbol/Icon View Mode
     if (myCloudState.viewMode === 'symbol') {
         // Using the new function from ui_symbols.beta.php
         myCloudRenderSymbols(details, items, st, isInsideZip);
         
        var breadcrumb = document.createElement('div');
        breadcrumb.className = 'myCloud-breadcrumb-bar';
        myCloudRenderMainBreadcrumbs(breadcrumb, st.currentDir);
        details.insertBefore(breadcrumb, details.firstChild);

        if (details && scrollTop > 0) {
            details.scrollTop = scrollTop;
            requestAnimationFrame(() => { if(details.scrollTop !== scrollTop) details.scrollTop = scrollTop; });
        }
		if (typeof myCloudSaveCurrentPathState === 'function') myCloudSaveCurrentPathState();
		if (typeof myCloudRenderOfficeLayout === 'function' && st.isOfficeMode) myCloudRenderOfficeLayout();
		return;
    }
    
     var breadcrumb = document.createElement('div');
    breadcrumb.className = 'myCloud-breadcrumb-bar';
    myCloudRenderMainBreadcrumbs(breadcrumb, st.currentDir);
    details.appendChild(breadcrumb);
	
	var container = document.createElement('div');
    container.className = 'myCloudTableContainer';

    // 5. GLOBAL FILE LIST DROP TARGET
    if (window.myCloudActionAllowed('upload') && !isInsideZip) {
        container.addEventListener('dragover', function(e) {
            e.preventDefault();
            container.classList.add('drag-active-global');
            e.dataTransfer.dropEffect = e.ctrlKey ? 'copy' : 'move';
        });

        container.addEventListener('dragleave', function(e) {
            if (!container.contains(e.relatedTarget)) {
                container.classList.remove('drag-active-global');
            }
        });

        container.addEventListener('drop', function(e) {
            e.preventDefault(); e.stopPropagation();
            container.classList.remove('drag-active-global');
            
            // Handle upload to current folder
            if (e.dataTransfer.items && e.dataTransfer.items.length > 0) {
                myCloudScanItems(e.dataTransfer.items, null);
            }
        });
    }

    var table = document.createElement('table');
    table.className = 'myCloudTable';
	
	var isRecycleBin = (st.currentDir === '/.recycle_bin');

    // 6. TABLE HEADERS
    var thead = table.createTHead();
    var header = thead.insertRow();
    
    var columns = [
        { title: '✓', key: null },
        { title: '', key: null },
   		{ title: myCloud_LANG.col_name, key: 'name' },
		{ title: isRecycleBin ? myCloud_LANG.col_origin : myCloud_LANG.col_size, key: isRecycleBin ? 'origin' : 'size' },
   		{ title: isRecycleBin ? myCloud_LANG.col_deleted : myCloud_LANG.col_date, key: 'date' }
    ];

    if (myCloudUserRole === 'admin_mode') {
        columns.push({ title: 'Owner', key: 'owner', width: '110px' });
        columns.push({ title: 'Perms', key: 'perms', width: '60px' });
    }

	columns.forEach(function(col, index) {
        var th = document.createElement('th');
        th.textContent = col.title;

		// [FIX] Widen the Location column in Recycle Bin
		if (isRecycleBin && index === 3) {
			th.style.width = '40%';
			th.style.textAlign = 'left';
		}
		
        if (col.width) th.style.width = col.width;
        if (col.key === 'owner' || col.key === 'perms') th.classList.add('ce-admin-col');

        if (col.key) {            if (st.sort.col === col.key) th.textContent += (st.sort.dir === 1 ? ' ▲' : ' ▼');
            th.onclick = function() {
                if (st.sort.col === col.key) st.sort.dir *= -1;
                else { st.sort.col = col.key; st.sort.dir = 1; }
                myCloudRenderUI();
            };
        }
        header.appendChild(th);
    });

    var tbody = table.createTBody();

    // 7. RENDER "UP" DIRECTORY ROW
    if (st.currentDir !== '/' && st.currentDir !== '') {
        var rowUp = tbody.insertRow();
        rowUp.className = 'myCloudRow';
        rowUp.dataset.fullpath = '..';
        rowUp.dataset.isupdir = 'true';
		var upHtml = '<td></td><td class="ce-col-icon"><span class="myCloudIcon">' + myCloudIconFolder + '</span></td><td><div class="ce-row-content"><span class="ce-name-text" style="font-weight:bold">..</span></div></td><td></td><td></td>';
        if (myCloudUserRole === 'admin_mode') upHtml += '<td class="ce-admin-col"></td><td class="ce-admin-col"></td>';
        rowUp.innerHTML = upHtml;
       if (isSingleClick) {
           rowUp.onclick = function() { myCloudGoUp(); };
       } else {
           rowUp.ondblclick = function() { myCloudGoUp(); };
           rowUp.onclick = function() { myCloudState.selectedFiles = []; myCloudRenderUI(); };
       }
    }

	// 8. RENDER FILE ROWS
    var sortedList = myCloudGetSortedItems();
    
    // Check Settings for List Thumbnails
    
    const showListThumbs = st.settings && st.settings[devKey] && st.settings[devKey].showListThumbnails;

    sortedList.forEach(function(i) {
        if (i.name === '/.recycle_bin' && myCloudUserRole === 'read-only') return;
         var row = tbody.insertRow();
         row.className = 'myCloudRow';
         row.dataset.fullpath = i.name;
 
         // [NEW] Use display name if available (Recycle Bin)
         var displayName = i.displayName || (myCloudState.pathNames && myCloudState.pathNames[i.name]) || i.name.split('/').pop();
 		if (i.name === '/.recycle_bin') displayName = myCloud_LANG.recycle_bin;
         
		var realFilename = displayName;

        var ext = realFilename.split('.').pop().toLowerCase();
         var isDirEntry = i.size === 'DIR';
         var isZip = ext === 'zip';
 
         // [NEW] Check if it's an encrypted directory/file
         var isEncrypted = i.name.endsWith('.enc') || i.isEncrypted === true || (typeof myCloudCrypto !== 'undefined' && myCloudCrypto.isDirEncrypted(i.name) && i.size === 'DIR');
         let isUnlocked = typeof myCloudCrypto !== 'undefined' ? myCloudCrypto.isDirUnlocked(myCloudCrypto.getCryptoRoot(i.name)) : false;
		 if (isEncrypted && !i.isBrokenEncryption) {
             realFilename = displayName.replace(/\.enc$/, '');
             displayName = realFilename;
         }
 
         var isLink = i.isLink === true;
		
		// [FIX] Renamed to avoid shadowing the outer isRecycleBin (which checks currentDir)
		var isRecycleBinItem = (i.name === '/.recycle_bin');
        var isContainer = isDirEntry || isZip;
        var isPreviewable = !isDirEntry && myCloudIsPreviewable(ext);
		var isEditable = !isDirEntry && typeof window.myCloudIsFileEditable === 'function' && window.myCloudIsFileEditable(i.name, isInsideZip);
		var isSelected = st.selectedFiles.includes(i.name);

        if (isSelected) row.classList.add('selected');

        // Row Interactions
        row.onclick = function(e) { 
            if (isSingleClick) {
                // Single Click -> Action (Reverted)
                myCloudHandleEnterAction(i, ext, isContainer);
            } else {
                // Standard -> Select
                myCloudSelectRow(row, i.name, e); 
            }
        };

        row.oncontextmenu = function(e) {
            e.preventDefault(); e.stopPropagation();
			if (isRecycleBinItem) return;
            if (!isSelected) myCloudSelectRow(row, i.name, e);
            myCloudShowContextMenu(e, i);
        };

        row.addEventListener('touchstart', function(e) { myCloudHandleTouchStart(e, i, row); }, { passive: true });
        row.addEventListener('touchend', myCloudHandleTouchEnd, { passive: true });
        row.addEventListener('touchmove', function(e) { myCloudHandleTouchMove(e); }, { passive: true });

        // Row Drag & Drop Logic
        if ((window.myCloudActionAllowed('move') || isInsideZip) && !isRecycleBinItem) {
            row.draggable = true;
            row.addEventListener('dragstart', function(e) {
				if (e.target.tagName === 'INPUT' || e.target.closest('.myCloudInlineInput')) return e.preventDefault();
                if (!st.selectedFiles.includes(i.name)) myCloudSelectRow(row, i.name, {});
                // Generate Custom Avatar
                const dragImg = window.myCloudGetDragImage(st.selectedFiles.length);
                e.dataTransfer.setDragImage(dragImg, 20, 20);
                e.dataTransfer.setData('text/plain', JSON.stringify(st.selectedFiles));
                e.dataTransfer.effectAllowed = 'copyMove';
                // [RESTORED] Desktop Drag-Out Support
                // Generates the specific "DownloadURL" format: mime:filename:absolute_url
                if (st.selectedFiles.length === 1 && i.size !== 'DIR') {
				     const isEncDrag = typeof myCloudCrypto !== 'undefined' && myCloudCrypto.isDirEncrypted(i.name);
					if (!isEncDrag) {
						var dName = i.name.split('/').pop();
						var dExt = dName.split('.').pop().toLowerCase();
						// Basic MIME sniffing for the transfer object
						var dMime = (['jpg','png','gif'].includes(dExt)) ? 'image/'+dExt : 'application/octet-stream';
						if(dExt === 'pdf') dMime = 'application/pdf';
						
						const dUrl = window.location.origin + window.location.pathname + '?myCloud_drag=1&myCloud_key=' + encodeURIComponent(myCloudState.key) + '&file=' + encodeURIComponent(i.name);
						e.dataTransfer.setData("DownloadURL", dMime + ":" + dName + ":" + dUrl);
					}
				}				
            });

            if (isContainer || ext === 'pdf') {
                row.addEventListener('dragover', function(e) {
                    e.preventDefault(); e.stopPropagation();
                    row.classList.add('drop-target');
                    container.classList.remove('drag-active-global');
                    e.dataTransfer.dropEffect = e.ctrlKey ? 'copy' : 'move';
                });

                row.addEventListener('dragleave', function(e) {
                    if (!row.contains(e.relatedTarget)) row.classList.remove('drop-target');
                });

                row.addEventListener('drop', function(e) {
					e.preventDefault(); e.stopPropagation();
					row.classList.remove('drop-target');
					
					try {
						var textData = e.dataTransfer.getData('text/plain');
						if (textData) {
							var paths = JSON.parse(textData);
							
							// Office View PDF Stacking Integration
							if (ext === 'pdf') {
								var isAllPdf = paths.every(function(p) { return p.toLowerCase().endsWith('.pdf'); });
								if (isAllPdf && !paths.includes(i.name)) {
									window.myCloudShowPdfMergeDialog(paths, i.name, e.shiftKey);
									return;
								}
							}
							
							if (!paths.includes(i.name)) {
								//  Check if dropping INTO Recycle Bin
								var isSourceBin = paths.some(function(p) { return p.indexOf('/.recycle_bin') === 0; });
								var isTargetBin = i.name.indexOf('/.recycle_bin') === 0 || i.name === '/.recycle_bin';
								
								if (isTargetBin) {
									// Show delete confirmation instead of move/copy dialog
									myCloudShowDeleteConfirm(paths.length, false, function() {
										myCloudBatchProcess('move', paths, i.name);
									});
									return;
								}
								
								// Standard behavior for non-bin targets
								var op = (e.ctrlKey && !isSourceBin) ? 'copy' : 'move';
								myCloudShowDragConfirm(op, paths, i.name, function() {
									myCloudBatchProcess(op, paths, i.name);
								});
							}
						}
					} catch(ex) {}
					if (e.dataTransfer.items && e.dataTransfer.items.length > 0) {
						myCloudScanItems(e.dataTransfer.items, i.name);
					}
				});			
            }
        }

        // Col 1: Checkbox
        var checkCell = row.insertCell();
        checkCell.className = 'ce-col-check';
        var checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.className = 'myCloudCheckbox';
        checkbox.checked = isSelected;
        
        checkbox.onclick = function(e) {
            e.stopPropagation();
            myCloudSelectRow(row, i.name, { ctrlKey: true, shiftKey: e.shiftKey, button: 0 });
        };
        
        checkCell.onclick = function(e) {
            e.stopPropagation();
            checkbox.checked = !checkbox.checked;
            myCloudSelectRow(row, i.name, { ctrlKey: true, shiftKey: false, button: 0 });
        };
        checkCell.appendChild(checkbox);

	// Col 2: Icon
        var iconCell = row.insertCell();
        iconCell.className = 'ce-col-icon';
        var iconWrap = document.createElement('div');
        iconWrap.style.cssText = 'position:relative; display:inline-flex; align-items:center; justify-content:center; width:24px; height:24px; vertical-align:middle;';
        var iconSpan = document.createElement('span');
        iconSpan.className = 'myCloudIcon';
        
        if (isContainer) {
            if (isRecycleBinItem) {
               iconSpan.innerHTML = myCloudSvg.recycle_main;
               iconSpan.style.color = ''; 
            } else {
                if (i.name === '/.recycle_bin') { 
					iconSpan.innerHTML = myCloudSvg.recycle_main;
				} else if (isLink) {
					iconSpan.innerHTML = myCloudIconLinkFolder;
                } else { 
					iconSpan.innerHTML = isZip ? myCloudTypeIcons.zip : myCloudIconFolder;
				}
            }
        } else {
            if (myCloudTypeIcons[ext]) iconSpan.innerHTML = myCloudTypeIcons[ext];
            else if (imageExts.includes(ext)) iconSpan.innerHTML = myCloudTypeIcons.jpg;
            else if (['rar', '7z', 'tar', 'gz'].includes(ext)) iconSpan.innerHTML = myCloudTypeIcons.zip;
            else if (['js', 'css', 'py', 'java', 'c'].includes(ext)) iconSpan.innerHTML = myCloudTypeIcons.html;
            else iconSpan.innerHTML = myCloudTypeIcons._default;

            // [NEW] Logic for List Thumbnails (JPG Only)
            if (showListThumbs && (ext === 'jpg' || ext === 'jpeg' || ext === 'png')) {
                iconSpan.classList.add('ce-lazy-list-thumb');
                iconSpan.dataset.path = i.name;
                iconSpan.dataset.filename = i.name.split('/').pop();
                // We keep the iconHTML set above as the placeholder
            }
        }
        iconWrap.appendChild(iconSpan);

        if (isEncrypted || i.isBrokenEncryption) {
            var badge = document.createElement('div');
            badge.className = 'ce-icon-badge';
            badge.textContent = i.isBrokenEncryption ? ' ️' : (isUnlocked ? '🔓' : '🔒');
            iconWrap.appendChild(badge);
        }
		iconCell.appendChild(iconWrap);
		
		row.ondblclick = function() {
            if (isSingleClick) {
                // Double Click -> Select (Reverted)
                // Note: If single click already fired navigation, this might not trigger, but consistent with logic
                myCloudSelectRow(row, i.name, { ctrlKey: true }); 
            } else {
                // Standard -> Action
                myCloudHandleEnterAction(i, ext, isContainer);
            }
        };

        // Col 3: Name & Hover Actions
        var nameCell = row.insertCell();
        nameCell.style.position = 'relative';
        nameCell.style.overflow = 'hidden';
        
        var contentDiv = document.createElement('div');
        contentDiv.className = 'ce-row-content';

        // [NEW] Visible Favorite Indicator
        if (typeof myCloudIsFavorite === 'function' && myCloudIsFavorite(i.name)) {
            var favIcon = document.createElement('span');
            favIcon.style.marginRight = '6px';
            favIcon.style.color = '#f0ad4e'; // Gold color
            favIcon.style.display = 'flex';
            favIcon.innerHTML = '<svg viewBox="0 0 24 24" style="width:14px; height:14px; fill:currentColor;"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>';
            contentDiv.appendChild(favIcon);
        }
		
		var textSpan = document.createElement('span');
        textSpan.className = 'ce-name-text';
        if (isRecycleBinItem) {
            textSpan.textContent = myCloud_LANG.recycle_bin;
            textSpan.style.color = '#757575';
            if (i.isBrokenEncryption) {
                textSpan.style.color = 'var(--danger, #e81123)';
                textSpan.style.fontWeight = 'bold';
                textSpan.title = (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.broken_enc ? myCloud_LANG.broken_enc : 'Unencrypted file in Vault!') + ' - ' + displayName;
            }
        } else {
            textSpan.textContent = displayName;
			textSpan.title = displayName;
            if (i.isBrokenEncryption) {
                textSpan.style.color = '#e81123';
                textSpan.style.fontWeight = 'bold';
            }
        }
        textSpan.style.flex = '0 1 auto';;
        contentDiv.appendChild(textSpan);

		if (window.myCloudActionAllowed('edit_tags')) {
            const tags = (st.tags && st.tags[st.key]) ? st.tags[st.key] : {};
            if (tags[i.name]) {
                let itemTags = tags[i.name];
                if (!Array.isArray(itemTags)) itemTags = [itemTags];
                var tagStack = document.createElement('div');
                tagStack.className = 'ce-tag-stack';
                itemTags.forEach((c, idx) => {
                    const tagDot = document.createElement('span');
                    tagDot.className = 'ce-tag-dot';
                    tagDot.style.backgroundColor = c;
                    tagDot.style.zIndex = itemTags.length - idx;
                    tagDot.title = window.myCloudGetTagName(c);
                    tagStack.appendChild(tagDot);
                });
                contentDiv.appendChild(tagStack);
            }
        }

        var flexSpacer = document.createElement('div');
        flexSpacer.style.flex = '1';
        flexSpacer.style.minWidth = '10px';
        contentDiv.appendChild(flexSpacer);

        // Hover Menu Construction
        var actionsDiv = document.createElement('div');
        actionsDiv.className = 'ce-row-actions';

        if (!isRecycleBinItem) {
            var createAction = function(cls, svg, title, clickHandler) {
                var wrap = document.createElement('div');
                wrap.className = "ce-action-icon " + cls;
                wrap.title = title;
                wrap.innerHTML = svg;
                wrap.onclick = function(e) {
                    e.stopPropagation();
                    clickHandler();
                };
                return wrap;
            };
            
            if (st.currentDir === '/.recycle_bin') {
                actionsDiv.appendChild(createAction('ce-act-restore', '<svg viewBox="0 0 24 24"><path d="M13 3c-4.97 0-9 4.03-9 9H1l3.89 3.89.07.14L9 12H6c0-3.87 3.13-7 7-7s7 3.13 7 7-3.13 7-7 7c-1.93 0-3.68-.79-4.94-2.06l-1.42 1.42C8.27 19.99 10.51 21 13 21c4.97 0 9-4.03 9-9s-4.03-9-9-9zm-1 5v5l4.28 2.54.72-1.21-3.5-2.08V8H12z"/></svg>', myCloud_LANG.restore, function() {
                    st.selectedFiles = [i.name];
                    myCloudAction_Restore();
                }));
                actionsDiv.appendChild(createAction('ce-act-delete', myCloudSvg.delete, myCloud_LANG.delete, function() {
                    st.selectedFiles = [i.name];
                    myCloudAction_Delete();
                }));
            } else {
                if (isPreviewable && window.myCloudActionAllowed('preview')) {
                    actionsDiv.appendChild(createAction('ce-act-preview', myCloudSvg.preview, myCloud_LANG.preview, function() {
                        myCloudDownloadFile(i.name, i.name.split('/').pop(), true);
                    }));
                }

                if (isEditable) {
                    actionsDiv.appendChild(createAction('ce-act-edit', myCloudSvg.edit_file, myCloud_LANG.edit || 'Edit', function() {
                        myCloudHandleEnterAction(i, ext, isContainer);
                    }));
                }

                if (window.myCloudActionAllowed('download')) {
                    actionsDiv.appendChild(createAction('ce-act-download', myCloudSvg.download, myCloud_LANG.download, function() {
                        myCloudDownloadFile(i.name, i.name.split('/').pop(), false);
                    }));
                }

                const sep1 = document.createElement('div');
                sep1.className = 'ce-row-action-sep';
                actionsDiv.appendChild(sep1);

                var isFav = myCloudIsFavorite(i.name);
                if (!isInsideZip && window.myCloudActionAllowed('fav_toggle')) {
                    actionsDiv.appendChild(createAction('ce-act-fav', isFav ? myCloudSvg.star_filled : myCloudSvg.star, myCloud_LANG.fav_toggle, function() {
                        myCloudToggleFavorite(i.name);
                        var me = actionsDiv.lastChild;
                        me.innerHTML = myCloudIsFavorite(i.name) ? myCloudSvg.star_filled : myCloudSvg.star;
                        if (document.getElementById('myCloudContextMenu')) myCloudCloseContextMenus(); 
                    }));
                } 

                if (i.name !== '/') {
                    if (window.myCloudActionAllowed('duplicate') && !isInsideZip) {
                        actionsDiv.appendChild(createAction('ce-act-duplicate', myCloudSvg.duplicate, (typeof myCloud_LANG !== 'undefined' ? myCloud_LANG.duplicate : 'Duplicate'), function() {
                            st.selectedFiles = [i.name];
                            if (typeof myCloudAction_Duplicate === 'function') myCloudAction_Duplicate(i.name);
                        }));
                    }
                    if (window.myCloudActionAllowed('copy') || isInsideZip) {
                        actionsDiv.appendChild(createAction('ce-act-copy', myCloudSvg.copy, myCloud_LANG.copy, function() {
                            st.selectedFiles = [i.name];
                            myCloudAction_CopyMove(false);
                        }));
                    }
                    if (window.myCloudActionAllowed('move') && !isInsideZip) {
                        actionsDiv.appendChild(createAction('ce-act-move', myCloudSvg.move, myCloud_LANG.move, function() {
                            st.selectedFiles = [i.name];
                            myCloudAction_CopyMove(true);
                        }));
                    }
                    const sep2 = document.createElement('div');
                    sep2.className = 'ce-row-action-sep';
                    actionsDiv.appendChild(sep2);

                    if (window.myCloudActionAllowed('rename') && !isInsideZip) {
                        actionsDiv.appendChild(createAction('ce-act-rename', myCloudSvg.rename, myCloud_LANG.rename, function() {
                            if (!st.selectedFiles.includes(i.name) || st.selectedFiles.length <= 1) {
                                st.selectedFiles = [i.name];
                            }
                            setTimeout(myCloudAction_Rename, 0);
                        }));
                    }
                    if (window.myCloudActionAllowed('delete') && !isInsideZip) {
                        actionsDiv.appendChild(createAction('ce-act-delete', myCloudSvg.delete, myCloud_LANG.delete, function() {
                            st.selectedFiles = [i.name];
                            myCloudAction_Delete();
                        }));
                    }
                }
            }
        }

        if (actionsDiv.hasChildNodes()) {
            contentDiv.appendChild(actionsDiv);
        }
        nameCell.appendChild(contentDiv);

        // Col 4: Size
        var sizeCell = row.insertCell();
        if (isRecycleBin) {
            sizeCell.textContent = i.origin || '-';
            sizeCell.style.color = '#888';
            sizeCell.style.fontSize = '12px';
			sizeCell.style.textAlign = 'left';
        } else {
	        sizeCell.textContent = isDirEntry ? myCloud_LANG.folder : myCloudFormatBytes(parseInt(i.size));
            sizeCell.style.textAlign = 'right';
        }

        // Col 5: Date
        var dateCell = row.insertCell();
        dateCell.textContent = i.date;

        // [NEW] Owner & Permissions Columns
        if (myCloudUserRole === 'admin_mode') {
            var ownerCell = row.insertCell();
            ownerCell.className = 'ce-admin-col';
            ownerCell.textContent = i.owner || '-';
            ownerCell.style.cssText = 'color: var(--text-secondary); font-size: 12px;';

            var permsCell = row.insertCell();
            permsCell.className = 'ce-admin-col';
            permsCell.textContent = i.perms || '-';
            permsCell.style.cssText = 'color: var(--text-secondary); font-family: monospace; font-size: 12px;';
        }
    });

    container.appendChild(table);

	// [NEW] Trigger Observer for new rows
    if (showListThumbs) {
        container.querySelectorAll('.ce-lazy-list-thumb').forEach(el => listIconObserver.observe(el));
    }

    // 9. RENDER EMPTY DROPZONE
    if (window.myCloudActionAllowed('upload') && !isInsideZip) {
        var dropZone = document.createElement('div');
        dropZone.className = 'myCloud-dropzone';
		dropZone.innerHTML = '<div style="font-size:24px; margin-bottom:10px; opacity:0.4;"><svg viewBox="0 0 24 24" width="40" height="40" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg></div><div>' + myCloud_LANG.upload_drag + '</div>';
        
        dropZone.addEventListener('dragenter', function() { dropZone.classList.add('drag-active'); });
        dropZone.addEventListener('dragover', function(e) { e.preventDefault(); dropZone.classList.add('drag-active'); });
        dropZone.addEventListener('dragleave', function() { dropZone.classList.remove('drag-active'); });
        
        dropZone.addEventListener('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            dropZone.classList.remove('drag-active');
            if (e.dataTransfer.items && e.dataTransfer.items.length > 0) {
                myCloudScanItems(e.dataTransfer.items, null);
            }
        });
        container.appendChild(dropZone);
    }
    details.appendChild(container);
	
	// [FIX] Restore scroll position
   if (details && scrollTop > 0) {
        details.scrollTop = scrollTop;
        requestAnimationFrame(() => { if(details.scrollTop !== scrollTop) details.scrollTop = scrollTop; });
   }
	if (typeof myCloudSaveCurrentPathState === 'function') myCloudSaveCurrentPathState();
	if (typeof myCloudRenderOfficeLayout === 'function' && st.isOfficeMode) myCloudRenderOfficeLayout();
}


// ============================================================
// COMMANDER MODE RENDERING
// ============================================================
function myCloudRenderCommander() {
    console.log("Rendering Commander Layout...");
    const st = myCloudState;
    const body = document.querySelector('.myCloudBody');
    const tree = document.querySelector('.myCloudTree');
    const details = document.querySelector('.myCloudDetails');
    
    // 1. Hide Standard Elements
    if (tree) tree.style.display = 'none';
    if (details) details.style.display = 'none';
    
    // CRITICAL FIX: Hide the SIDEBAR resizer, but do not destroy it
    document.querySelectorAll('.myCloudResizer').forEach(r => {
        // Don't hide our new commander handle if it accidentally got this class
        if (!r.classList.contains('myCloud-commander-resizer-handle')) {
            r.style.display = 'none';
        }
    });
    
    body.classList.add('commander-mode');
    
    // 2. Remove existing commander elements to prevent duplicates
    document.querySelectorAll('.myCloud-commander-pane, .myCloud-commander-resizer-container').forEach(el => el.remove());
    
    // 3. Create Left Pane
    const leftPane = createCommanderPane('left', st.commanderLeft);
    
    // 4. Create Middle Toolbar (The Resizer Container)
    const toolbarContainer = document.createElement('div');
    // Apply class AND inline styles to force visibility against any CSS specificity issues
    toolbarContainer.className = 'myCloud-commander-resizer-container';
    toolbarContainer.style.cssText = "display:flex !important; flex-direction:column; align-items:center; justify-content:center; width:40px; min-width:40px; background:#f0f0f0; border-left:1px solid #ccc; border-right:1px solid #ccc; z-index:100; position:relative;";
    
    // Add Drag Handle Overlay
    const resizeHandle = document.createElement('div');
    resizeHandle.className = 'myCloud-commander-resizer-handle'; 
    resizeHandle.style.cssText = "position:absolute; top:0; bottom:0; left:0; width:100%; cursor:col-resize; z-index:5;";
    toolbarContainer.appendChild(resizeHandle);
    
    // Add Buttons safely
    try {
        renderCommanderButtons(toolbarContainer);
    } catch (err) {
        console.error(err);
        toolbarContainer.innerHTML += '<div style="color:red; font-size:10px;">Err</div>';
    }

    // 5. Create Right Pane
    const rightPane = createCommanderPane('right', st.commanderRight);
    
    // 6. Insert into DOM (Strict Order: Left -> Bar -> Right)
    body.appendChild(leftPane);
    body.appendChild(toolbarContainer);
    body.appendChild(rightPane);
    
    // 7. Apply Ratio
    const devKey = myCloudGetCurrentDeviceKey();
    const config = st.settings ? st.settings[devKey] : myCloudDefaultSettings.desktop;
    const ratio = config.commanderSplit || 0.5;
    
    leftPane.style.flex = ratio;
    rightPane.style.flex = (1 - ratio);
    
    // 8. Init Resizer & Focus
    if(typeof initCommanderResizer === 'function') initCommanderResizer();
    
    const activePane = st.commanderActive === 'left' ? leftPane : rightPane;
    const contentDiv = activePane.querySelector('.myCloud-commander-content');
    if (contentDiv) contentDiv.focus();
}

function renderCommanderButtons(container) {
    const st = myCloudState;
    const side = st.commanderActive || 'left';
    const activeState = (side === 'left') ? st.commanderLeft : st.commanderRight;
    const targetState = (side === 'left') ? st.commanderRight : st.commanderLeft;
    
    const selCount = activeState.selectedFiles.length;
    
    // Matrix Permissions Check
    const canCopy = window.myCloudActionAllowed('copy');
    const canMove = window.myCloudActionAllowed('move');
    const canDelete = window.myCloudActionAllowed('delete');
    const canNewFolder = window.myCloudActionAllowed('newfolder');
    const canSync = window.myCloudActionAllowed('sync_dir');
    const canZip = window.myCloudActionAllowed('zip_copy');
    
    // Zip Limit
    const zipLimit = (typeof window.zip_warn_limit !== 'undefined') ? window.zip_warn_limit : (300 * 1024 * 1024);
    let selectionSize = 0;
    activeState.selectedFiles.forEach(path => {
        const item = st.allItems.find(i => i.name === path);
        if (item && item.size !== 'DIR') selectionSize += parseInt(item.size);
    });
    const underZipLimit = (selCount > 0) && (selectionSize < zipLimit);
    
    const mkBtn = (iconHTML, title, action, disabled = false) => {
        const btn = document.createElement('button');
        btn.className = 'ce-cmd-btn';
        btn.style.cssText = "width:28px; height:28px; margin:4px 0; background:transparent; border:1px solid transparent; border-radius:4px; cursor:pointer; display:flex; align-items:center; justify-content:center; position:relative; z-index:20; padding:0;";
        btn.innerHTML = iconHTML || '?';
        btn.title = title || '';
        btn.disabled = disabled;
        btn.style.opacity = disabled ? '0.3' : '1';
        btn.onclick = (e) => { e.stopPropagation(); if(action) action(); };
        btn.onmouseenter = () => { if(!btn.disabled) btn.style.backgroundColor = 'var(--hover-bg-medium)'; };
        btn.onmouseleave = () => { if(!btn.disabled) btn.style.backgroundColor = 'transparent'; };
        return btn;
    };
    
    const S = (typeof myCloudSvg !== 'undefined') ? myCloudSvg : {};
    const L = (typeof myCloud_LANG !== 'undefined') ? myCloud_LANG : {};

    // 1. Copy
    container.appendChild(mkBtn(S.copy, (L.copy||'Copy') + ' -> Target', () => {
        myCloudShowDragConfirm('copy', activeState.selectedFiles, targetState.dir, (preserve) => {
            myCloudBatchProcess('copy', activeState.selectedFiles, targetState.dir, preserve).then(() => {
                refreshCommanderPane('right'); 
                refreshCommanderPane('left');
            });
        });
    }, selCount === 0 || !canCopy));

    // 2. Move
    container.appendChild(mkBtn(S.move, (L.move||'Move') + ' -> Target', () => {
        myCloudShowDragConfirm('move', activeState.selectedFiles, targetState.dir, (preserve) => {
            myCloudBatchProcess('move', activeState.selectedFiles, targetState.dir, preserve).then(() => {
                refreshCommanderPane('right'); 
                refreshCommanderPane('left');
            });
        });
    }, selCount === 0 || !canMove));

    // 3. Delete
    container.appendChild(mkBtn(S.delete, L.delete||'Delete', () => {
         myCloudState.selectedFiles = activeState.selectedFiles; 
         myCloudAction_Delete();
    }, selCount === 0 || !canDelete));

    // 4. New Folder
    container.appendChild(mkBtn(S.newfolder, L.new_folder||'New Folder', () => {
         myCloudAction_NewFolder(); 
    }, !canNewFolder));

    const spacer = document.createElement('div');
    spacer.style.height = '20px';
    container.appendChild(spacer);
    
    // 5. Directory Sync (Rclone/Rsync)
    const syncIcon = '<svg viewBox="0 0 24 24"><path d="M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6 0 1.01-.25 1.97-.7 2.8l1.46 1.46C19.54 15.03 20 13.57 20 12c0-4.42-3.58-8-8-8zm0 14c-3.31 0-6-2.69-6-6 0-1.01.25-1.97.7-2.8L5.24 7.74C4.46 8.97 4 10.43 4 12c0 4.42 3.58 8 8 8v3l4-4-4-4v3z" fill="currentColor"/></svg>';
    container.appendChild(mkBtn(syncIcon, L.sync_dir || 'Sync Directories', () => {
        myCloudShowSyncModal(activeState.dir, targetState.dir, side);
    }, !canSync));

    // 6. Zip
    container.appendChild(mkBtn(S.zip, (L.zip_copy||'Zip') + ' -> Target', () => {
        commanderZipAction('copy', activeState.selectedFiles, targetState.dir);
    }, !underZipLimit || !canZip));

    // 7. Zip & Move
    const moveZipIcon = '<svg viewBox="0 0 24 24"><path d="M20 6h-8l-2-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm-6 10h-2v2h-2v-2H8v-2h2v-2h2v2h2v2z" fill="currentColor" opacity="0.7"/></svg>';
    container.appendChild(mkBtn(moveZipIcon, (L.zip_move||'Zip & Move') + ' -> Target', () => {
        commanderZipAction('move', activeState.selectedFiles, targetState.dir);
    }, !underZipLimit || !canZip || !canMove));
}


// Helper for Zip Operations in Commander
function commanderZipAction(mode, files, targetDir) {
    if (files.length === 0) return;
    
    // We only support zipping folders nicely if it's a single folder selection in standard zip logic,
    // but the batch logic handles arrays.
    // However, the standard server zip function takes ONE source.
    // If multiple items are selected, we can't easily zip them into one file without a new API endpoint.
    // Assumption: Button active only if 1 item selected OR we accept standard behavior (zip each individually).
    // Better Assumption: The user expects "Add to Archive".
    // Current server limitation: 'zip' action takes 'src' (string). 
    
    // Workaround: We iterate.
    
    const processNext = (index) => {
        if (index >= files.length) {
            refreshCommanderPane('left');
            refreshCommanderPane('right');
            return;
        }
        
        const path = files[index];
        const fd = new URLSearchParams();
        fd.append('myCloud_action', 'zip');
        fd.append('myCloud_key', myCloudState.key);
        fd.append('myCloud_token', myCloudCsrfToken);
        fd.append('src', path);
        fd.append('mode', mode);
        fd.append('dest', targetDir); // NEW param
        
        myCloudStreamAction(fd, (mode === 'move' ? myCloud_LANG.moving_to_zip : myCloud_LANG.copying_to_zip));
        
        // Wait a bit for the stream to finish? myCloudStreamAction is async UI but synchronous fetch.
        // Actually myCloudStreamAction uses fetch/reader. We can't easily chain it without refactoring.
        // For now, let's just trigger them (might overlap progress bars).
        // Ideally, refactor `myCloudStreamAction` to return a promise.
    };

    // To avoid refactoring the entire streaming logic right now, let's just do the first item 
    // or tell user "Multi-zip not supported yet" if > 1.
    if (files.length > 1) {
        myCloudShowAlert("Info", "Please zip items individually or put them in a folder first.");
    } else {
        processNext(0);
    }
}


// Show Sync Modal
window.myCloudShowSyncModal = function(srcDir, destDir, activeSide) {
    const overlay = document.getElementById('myCloudModalOverlay');
    const modal = document.getElementById('myCloudModal');
    myCloudResetModal();
    
    overlay.style.display = 'flex';
    modal.className = 'myCloudModal';
    
    const isRtl = document.getElementById('myCloudContainer').getAttribute('dir') === 'rtl';
    const arrow = isRtl ? '←' : '→';
    
    modal.innerHTML = 
        '<div class="myCloudModalHeader">' + (myCloud_LANG.sync_dir || 'Directory Sync') + '</div>' +
        '<div class="myCloudModalBody" style="padding: 20px;">' +
            '<div style="background:var(--gray-10); padding:10px; border:1px solid var(--border-default); border-radius:4px; margin-bottom:15px; font-family:monospace; font-size:12px; word-break:break-all;">' +
                '<span style="color:var(--accent-primary)">SRC:</span> ' + srcDir + '<br>' +
                '<div style="text-align:center; margin:5px 0;"><b>' + arrow + '</b></div>' +
                '<span style="color:var(--success)">DST:</span> ' + destDir +
            '</div>' +
            '<div><strong>' + (myCloud_LANG.sync_options || 'Options') + '</strong></div>' +
            '<label style="display:flex; align-items:center; gap:8px; margin-top:10px; cursor:pointer;">' +
                '<input type="checkbox" id="syncMirror" class="myCloudCheckbox"> ' + (myCloud_LANG.sync_mirror || 'Mirror (Delete missing on destination)') +
            '</label>' +
            '<label style="display:flex; align-items:center; gap:8px; margin-top:10px; cursor:pointer;">' +
                '<input type="checkbox" id="syncUpdate" class="myCloudCheckbox" checked> ' + (myCloud_LANG.sync_update || 'Update (Skip newer files on destination)') +
            '</label>' +
            '<label style="display:flex; align-items:center; gap:8px; margin-top:10px; cursor:pointer;">' +
                '<input type="checkbox" id="syncDryRun" class="myCloudCheckbox"> ' + (myCloud_LANG.sync_dry_run || 'Dry Run (Test only)') +
            '</label>' +
            '<div class="myCloudButtons" style="margin-top:20px;">' +
                '<button onclick="myCloudCloseModal()">' + myCloud_LANG.cancel + '</button>' +
                '<button id="btnSyncExec" style="background:var(--accent-primary); color:#fff; border:none;">Start Sync</button>' +
            '</div>' +
        '</div>';
        
    document.getElementById('btnSyncExec').onclick = () => {
        const fd = new URLSearchParams();
        fd.append('myCloud_action', 'admin_sync');
        fd.append('src', srcDir);
        fd.append('dest', destDir);
        fd.append('mirror', document.getElementById('syncMirror').checked ? '1' : '0');
        fd.append('update', document.getElementById('syncUpdate').checked ? '1' : '0');
        fd.append('dry_run', document.getElementById('syncDryRun').checked ? '1' : '0');
        myCloudCloseModal();
        myCloudStreamAction(fd, 'Syncing Directories...');
    };
};

// 2. Create Commander Pane (With Breadcrumbs, Focus & Drag Handlers)
function createCommanderPane(side, paneState) {
    const st = myCloudState;
    const isActive = (st.commanderActive === side);
    
    const pane = document.createElement('div');
    pane.className = 'myCloud-commander-pane' + (isActive ? ' active' : '');
    pane.dataset.side = side;
    
    // Header (Breadcrumbs)
    const header = document.createElement('div');
    header.className = 'myCloud-commander-header';
    if (typeof renderCommanderBreadcrumbs === 'function') {
        renderCommanderBreadcrumbs(header, paneState.dir, side);
    } else {
        header.textContent = paneState.dir; // Fallback
    }
    pane.appendChild(header);
    
    // Content
    const content = document.createElement('div');
    content.className = 'myCloud-commander-content';
    content.setAttribute('tabindex', '0');
    
    const activate = () => {
        if (st.commanderActive !== side) {
            st.commanderActive = side;
            document.querySelectorAll('.myCloud-commander-pane').forEach(p => p.classList.remove('active'));
            pane.classList.add('active');
            
            st.currentDir = paneState.dir;
            st.selectedFiles = paneState.selectedFiles;
            st.visualCursorIndex = paneState.visualCursorIndex;
            if(typeof myCloudUpdateToolbarState === 'function') myCloudUpdateToolbarState();
        }
    };

    content.onclick = () => { activate(); content.focus(); };
    content.onfocus = () => { activate(); };

    content.addEventListener('keydown', (e) => commanderNavKey(e, side, content, paneState));

    // Drag & Drop
    if (window.myCloudActionAllowed('move')) {
        content.addEventListener('dragover', (e) => {
            e.preventDefault(); e.stopPropagation();
            content.classList.add('drag-active-global');
            e.dataTransfer.dropEffect = e.ctrlKey ? 'copy' : 'move';
        });
        content.addEventListener('dragleave', (e) => {
            if (!content.contains(e.relatedTarget)) content.classList.remove('drag-active-global');
        });
        content.addEventListener('drop', (e) => {
            e.preventDefault(); e.stopPropagation();
            content.classList.remove('drag-active-global');
            try {
                const textData = e.dataTransfer.getData('text/plain');
                if (textData) {
                    const paths = JSON.parse(textData);
                    if (!paths.includes(paneState.dir)) {
                        const op = e.ctrlKey ? 'copy' : 'move';
                        myCloudShowDragConfirm(op, paths, paneState.dir, (preserve) => {
                            myCloudBatchProcess(op, paths, paneState.dir, preserve).then(() => {
                                refreshCommanderPane('left'); refreshCommanderPane('right');
                            });
                        });
                    }
                }
            } catch(ex){}
            if (e.dataTransfer.items && e.dataTransfer.items.length > 0) {
                myCloudScanItems(e.dataTransfer.items, paneState.dir);
            }
        });
    }
    
    renderCommanderContent(content, paneState, side);
    pane.appendChild(content);
    return pane;
}


// Helper: Render Breadcrumbs
function renderCommanderBreadcrumbs(container, path, side) {
    container.innerHTML = '';
    
    // Root Segment
    const rootSpan = document.createElement('span');
    rootSpan.className = 'ce-crumb-segment';
    rootSpan.innerHTML = myCloudIconFolder; // Generic Folder Icon
    rootSpan.onclick = (e) => { e.stopPropagation(); commanderNavigateTo('/', side); };
    container.appendChild(rootSpan);

    if (path !== '/') {
        // Path Segments
        const parts = path.split('/').filter(p => p);
        let walker = '';
        
        parts.forEach((part, index) => {
            walker += '/' + part;
            const currentPath = walker; // Capture for closure
            const parentPath = walker.substring(0, walker.lastIndexOf('/')) || '/';
            
            const sepWrap = document.createElement('span');
            sepWrap.className = 'ce-crumb-sep';
            sepWrap.textContent = '›';
            sepWrap.style.cursor = 'pointer';
            sepWrap.title = "View subfolders";
            
            sepWrap.onclick = (e) => {
                e.stopPropagation();
                myCloudCloseContextMenus();
                
                const rect = sepWrap.getBoundingClientRect();
                const menu = document.createElement('div');
                menu.className = 'myCloudContextMenu';
                menu.style.top = (rect.bottom + 2) + 'px';
                menu.style.left = rect.left + 'px';
                menu.style.maxHeight = '300px';
                menu.style.overflowY = 'auto';
                menu.innerHTML = '<div style="padding:10px; font-size:12px; color:#888;">Loading...</div>';
                document.body.appendChild(menu);

                myCloudFetchDirectory(parentPath, 1, true).then(resp => {
                    menu.innerHTML = '';
                    const subDirs = resp.data.filter(i => i.size === 'DIR' && i.name !== '/.recycle_bin');
                    if (subDirs.length === 0) {
                        menu.innerHTML = '<div style="padding:10px; font-size:12px; color:#888;">No subfolders</div>';
                        return;
                    }
                    subDirs.forEach(dir => {
                        const item = document.createElement('div');
                        item.className = 'myCloudContextItem';
                        item.innerHTML = '<span class="myCloudIcon" style="width:16px;height:16px;margin-right:8px;">' + myCloudIconFolder + '</span>' + dir.name.split('/').pop();
                        item.onclick = () => { menu.remove(); commanderNavigateTo(dir.name, side); };
                        menu.appendChild(item);
                    });
                });
                setTimeout(() => { document.addEventListener('click', () => menu.remove(), {once: true}); }, 50);
            };
            container.appendChild(sepWrap);
            
            const seg = document.createElement('span');
            seg.className = 'ce-crumb-segment';
            let dName = (myCloudState.pathNames && myCloudState.pathNames[currentPath]) ? myCloudState.pathNames[currentPath] : part.replace('.enc', '');
            if (typeof myCloudCrypto !== 'undefined' && myCloudCrypto.isDirEncrypted(currentPath)) {
                let isUnlocked = myCloudCrypto.isDirUnlocked(myCloudCrypto.getCryptoRoot(currentPath));
                dName = (isUnlocked ? '🔓 ' : '🔒 ') + dName;
            }
            seg.textContent = dName;
           
            // Disable click on current (last) folder
            if (index === parts.length - 1) {
                 seg.classList.add('active');
            } else {
                 seg.onclick = (e) => { e.stopPropagation(); commanderNavigateTo(currentPath, side); };
            }
            
            container.appendChild(seg);
        });
    }
	
    // --- TAG FILTER UI (Commander View) ---
    const st = myCloudState;
    const paneState = (side === 'left') ? st.commanderLeft : st.commanderRight;

    const spacer = document.createElement('div');
    spacer.style.flex = '1';
    container.appendChild(spacer);

    if (window.myCloudActionAllowed('edit_tags')) {
		const filterWrap = document.createElement('div');
		filterWrap.style.display = 'flex';
		filterWrap.style.alignItems = 'center';
		filterWrap.style.gap = '4px';
		filterWrap.style.padding = '6px 12px';
	
		const colors = myCloudState.settings.visibleTags || ['#e81123', '#0078d4', '#107c10', '#f0ad4e', '#888888'];
		colors.forEach(c => {
			const dot = document.createElement('div');
			dot.className = 'ce-tag-dot';
			dot.style.backgroundColor = c;
			dot.style.cursor = 'pointer';
            dot.style.border = '2px solid transparent';
            dot.style.backgroundClip = 'padding-box';
            dot.style.width = '18px'; 
            dot.style.height = '18px';
            dot.style.margin = '0';

			dot.title = window.myCloudGetTagName(c);

			if (paneState.activeTagFilter === c) {
				dot.style.boxShadow = 'inset 0 0 0 1px var(--gray-10), 0 0 0 2px ' + c;
			}
			dot.onclick = (e) => {
				e.stopPropagation();
				paneState.activeTagFilter = (paneState.activeTagFilter === c) ? null : c;
				const pane = document.querySelector(`.myCloud-commander-pane[data-side="${side}"]`);
				if (pane) renderCommanderContent(pane.querySelector('.myCloud-commander-content'), paneState, side);
			};
			filterWrap.appendChild(dot);
		});
	
		if (paneState.activeTagFilter) {
			const clear = document.createElement('span');
			clear.innerHTML = '✕';
			clear.style.cursor = 'pointer';
			clear.style.fontSize = '10px';
			clear.style.marginLeft = '4px';
			clear.style.color = 'var(--text-secondary)';
			clear.onclick = (e) => { 
				e.stopPropagation(); 
				paneState.activeTagFilter = null; 
				const pane = document.querySelector(`.myCloud-commander-pane[data-side="${side}"]`);
				if (pane) renderCommanderContent(pane.querySelector('.myCloud-commander-content'), paneState, side);
			};
			filterWrap.appendChild(clear);
		}
		
	
		// --- Dropdown Button for Tags (Commander) ---
		const ddWrap = document.createElement('div');
		ddWrap.className = 'myCloud-tag-dropdown-wrapper';
		
		const ddBtn = document.createElement('button');
		ddBtn.className = 'myCloud-tag-dropdown-btn';
		ddBtn.style.color = 'var(--text-primary)';
		ddBtn.style.borderColor = 'var(--border-default)';
		ddBtn.innerHTML = (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.tag_labels ? myCloud_LANG.tag_labels : 'Tags') + ' &#9662;';
		
		ddBtn.onclick = (e) => {
			e.stopPropagation();
			let existing = document.getElementById('myCloudTagDropdown');
			if (existing) {
				existing.remove();
				if (existing.dataset.source === 'cmd_' + side) return; 
			}
			
			const ddMenu = document.createElement('div');
			ddMenu.id = 'myCloudTagDropdown';
			ddMenu.dataset.source = 'cmd_' + side;
			ddMenu.className = 'myCloud-tag-dropdown-menu show';
			ddMenu.style.background = 'var(--gray-00)';
			ddMenu.style.borderColor = 'var(--border-default)';
			ddMenu.style.position = 'fixed';
			ddMenu.style.zIndex = '21000';
			
			colors.forEach(c => {
				const item = document.createElement('div');
				item.className = 'myCloud-tag-dropdown-item';
				item.style.color = 'var(--text-primary)';
				item.innerHTML = '<span class="myCloud-tag-color-dot" style="background-color:' + c + '"></span><span>' + window.myCloudGetTagName(c) + '</span>';
				if (paneState.activeTagFilter === c) item.style.backgroundColor = 'var(--hover-bg-medium)';
				
				item.onmouseenter = () => item.style.backgroundColor = 'var(--hover-bg-light)';
				item.onmouseleave = () => item.style.backgroundColor = (paneState.activeTagFilter === c) ? 'var(--hover-bg-medium)' : 'transparent';
				
				item.onclick = (ev) => {
					ev.stopPropagation();
					paneState.activeTagFilter = (paneState.activeTagFilter === c) ? null : c;
					ddMenu.remove();
					const pane = document.querySelector(`.myCloud-commander-pane[data-side="${side}"]`);
					if (pane) renderCommanderContent(pane.querySelector('.myCloud-commander-content'), paneState, side);
				};
				ddMenu.appendChild(item);
			});
			
			document.body.appendChild(ddMenu);
			if (typeof myCloudApplyTheme === 'function') myCloudApplyTheme();
			
			const rect = ddBtn.getBoundingClientRect();
			ddMenu.style.top = (rect.bottom + 4) + 'px';
			let left = rect.right - ddMenu.offsetWidth;
			if (left < 5) left = 5;
			ddMenu.style.left = left + 'px';
			ddMenu.style.right = 'auto';
			
			setTimeout(() => {
				const closer = (ev) => {
					if (!ddMenu.contains(ev.target)) {
						ddMenu.remove();
						document.removeEventListener('click', closer);
					}
				};
				document.addEventListener('click', closer);
			}, 10);
		};
		
		ddWrap.appendChild(ddBtn);
		filterWrap.appendChild(ddWrap);
		
		container.appendChild(filterWrap);
	}
    // --- COMMAND PALETTE BUTTON (COMMANDER) ---
    const cmdBtnWrap = document.createElement('div');
    cmdBtnWrap.style.marginLeft = '8px';
    cmdBtnWrap.style.display = 'flex';
    cmdBtnWrap.style.alignItems = 'center';

    const cmdBtn = document.createElement('button');
    cmdBtn.className = 'myCloud-tag-dropdown-btn';
    cmdBtn.style.color = 'var(--text-primary)';
    cmdBtn.style.borderColor = 'var(--border-default)';
    cmdBtn.title = 'Command Palette (Ctrl+P)';
    cmdBtn.innerHTML = '<span class="myCloudIcon" style="width:14px;height:14px;">' + (typeof myCloudSvg !== 'undefined' ? myCloudSvg.search : '🔍') + '</span>';

    cmdBtn.onmousedown = (e) => e.stopPropagation();
	cmdBtn.onclick = (e) => {
        e.stopPropagation();
        if (typeof myCloudShowCommandPalette === 'function') myCloudShowCommandPalette();
    };
    cmdBtnWrap.appendChild(cmdBtn);
    container.appendChild(cmdBtnWrap);	
}

// Helper: Navigate to path via Breadcrumb
function commanderNavigateTo(path, side) {
    const st = myCloudState;
    const paneState = (side === 'left') ? st.commanderLeft : st.commanderRight;
    
    // Build a fake "dir" item to reuse existing logic
    const fakeItem = { name: path, size: 'DIR' };
    commanderHandleEnter(fakeItem, side);
}

// 2. Full Keyboard Navigation Handler
function commanderNavKey(e, side, container, paneState) {
    const rows = Array.from(container.querySelectorAll('.myCloudRow'));
    // Allow Tab even if empty
    if (rows.length === 0 && e.key !== 'Tab' && e.key !== 'F5') return;

    let idx = paneState.visualCursorIndex || 0;
    if (idx < 0) idx = 0;
    if (idx >= rows.length) idx = rows.length - 1;

    const role = typeof myCloudUserRole !== 'undefined' ? myCloudUserRole : 'no-access';
	
    const selectVisual = (newIdx) => {
        const target = rows[newIdx];
        if (!target) return;
        
        paneState.visualCursorIndex = newIdx;
        const path = target.dataset.fullpath;

        if (e.shiftKey) {
             if (!paneState.selectedFiles.includes(path)) paneState.selectedFiles.push(path);
        } else if (e.ctrlKey) {
             if (paneState.selectedFiles.includes(path)) paneState.selectedFiles = paneState.selectedFiles.filter(f => f !== path);
             else paneState.selectedFiles.push(path);
        } else {
             paneState.selectedFiles = [path];
        }
        
        // Sync Global State
        myCloudState.selectedFiles = paneState.selectedFiles;

        rows.forEach((r) => {
            const rPath = r.dataset.fullpath;
            const isSel = paneState.selectedFiles.includes(rPath);
            if (isSel) r.classList.add('selected'); else r.classList.remove('selected');
            const cb = r.querySelector('.myCloudCheckbox');
            if (cb) cb.checked = isSel;
        });

        target.scrollIntoView({ block: 'nearest' });
        myCloudUpdateToolbarState();
    };

    switch (e.key) {
        // --- PANE SWITCHING ---
        case 'Tab':
            e.preventDefault(); e.stopPropagation();
            const newSide = side === 'left' ? 'right' : 'left';
            const otherPane = document.querySelector('.myCloud-commander-pane[data-side="' + newSide + '"]');
            if (otherPane) {
                const otherContent = otherPane.querySelector('.myCloud-commander-content');
                if (otherContent) otherContent.focus();
            }
            break;

        // --- NAVIGATION ---
        case 'ArrowDown': e.preventDefault(); if (idx < rows.length - 1) selectVisual(idx + 1); break;
        case 'ArrowUp':   e.preventDefault(); if (idx > 0) selectVisual(idx - 1); break;
        case 'PageDown':  e.preventDefault(); let pd = idx + 10; selectVisual(pd >= rows.length ? rows.length - 1 : pd); break;
        case 'PageUp':    e.preventDefault(); let pu = idx - 10; selectVisual(pu < 0 ? 0 : pu); break;
        case 'Home':      e.preventDefault(); selectVisual(0); break;
        case 'End':       e.preventDefault(); selectVisual(rows.length - 1); break;
        
        case 'Enter':
            e.preventDefault();
            const targetRow = rows[idx];
            if (targetRow) {
                if (targetRow.dataset.fullpath === '..') {
                    commanderGoUp(side);
                } else {
                    const itemObj = paneState.items.find(i => i.name === targetRow.dataset.fullpath);
                    if (itemObj) commanderHandleEnter(itemObj, side);
                }
            }
            break;
            
        case 'Backspace':
            e.preventDefault();
            commanderGoUp(side);
            break;

        // --- ACTIONS ---
        case 'Delete':
            if (paneState.selectedFiles.length > 0 && window.myCloudActionAllowed('delete')) {
                e.preventDefault();
                myCloudState.selectedFiles = paneState.selectedFiles;
                myCloudAction_Delete(); 
            }
            break;
            
        case 'F2':
            if (paneState.selectedFiles.length === 1 && window.myCloudActionAllowed('rename')) {
                e.preventDefault();
                myCloudState.selectedFiles = paneState.selectedFiles;
                myCloudAction_Rename();
            }
            break;
            
        case 'F5':
            e.preventDefault();
            refreshCommanderPane(side);
            break;
            
        case 'a':
            if (e.ctrlKey) {
                e.preventDefault();
                // Select all (excluding ..)
                paneState.selectedFiles = rows.map(r => r.dataset.fullpath).filter(p => p !== '..');
                myCloudState.selectedFiles = paneState.selectedFiles;
                
                // Visual update
                rows.forEach(r => {
                    if (r.dataset.fullpath !== '..') {
                        r.classList.add('selected');
                        const cb = r.querySelector('.myCloudCheckbox');
                        if (cb) cb.checked = true;
                    }
                });
                myCloudUpdateToolbarState();
			    if (typeof myCloudUpdateOfficePreview === 'function' && myCloudState.isOfficeMode) {
			        myCloudUpdateOfficePreview();
			    }
            }
            break;

        // --- CLIPBOARD ---
        case 'c':
            if (e.ctrlKey) {
                e.preventDefault();
                if (paneState.selectedFiles.length > 0) {
                    window.myCloudClipboard = { action: 'copy', files: [...paneState.selectedFiles] };
                    document.querySelectorAll('.myCloudRow').forEach(r => r.style.opacity = '1');
                }
            }
            break;
            
        case 'x':
            if (e.ctrlKey && window.myCloudActionAllowed('move')) {
                e.preventDefault();
                if (paneState.selectedFiles.length > 0) {
                    window.myCloudClipboard = { action: 'move', files: [...paneState.selectedFiles] };
                    document.querySelectorAll('.myCloudRow').forEach(r => r.style.opacity = '1');
                    rows.forEach(r => {
                        if (paneState.selectedFiles.includes(r.dataset.fullpath)) r.style.opacity = '0.5';
                    });
                }
            }
            break;
            
        case 'v':
            if (e.ctrlKey && window.myCloudActionAllowed('move')) {
                e.preventDefault();
                if (window.myCloudClipboard && window.myCloudClipboard.files.length > 0) {
                    const dest = paneState.dir;
                    const executePaste = (preserve) => {
                        myCloudBatchProcess(window.myCloudClipboard.action, window.myCloudClipboard.files, dest, preserve)
                        .then(() => {
                            refreshCommanderPane('left');
                            refreshCommanderPane('right');
                            if (window.myCloudClipboard.action === 'move') {
                                window.myCloudClipboard = { action: null, files: [] };
                            }
                        });
                    };
                    if (typeof myCloudUserRole !== 'undefined' && myCloudUserRole === 'admin_mode') {
                        myCloudShowDragConfirm(window.myCloudClipboard.action, window.myCloudClipboard.files, dest, executePaste);
                    } else {
                        executePaste(true);
                    }
                }
            }
            break;

        // --- TYPE TO SEEK ---
        default:
            if (e.key.length === 1 && !e.ctrlKey && !e.altKey && !e.metaKey) {
                clearTimeout(window.ceTypeTimer);
                window.ceTypeBuffer = (window.ceTypeBuffer || '') + e.key.toLowerCase();
                
                const matchIdx = rows.findIndex(r => {
                    const nameSpan = r.querySelector('.ce-name-text');
                    return nameSpan && nameSpan.textContent.toLowerCase().startsWith(window.ceTypeBuffer);
                });

                if (matchIdx !== -1) {
                    selectVisual(matchIdx);
                }

                window.ceTypeTimer = setTimeout(() => { window.ceTypeBuffer = ''; }, 800);
            }
            break;
    }
}


function renderCommanderContent(container, paneState, side) {
    // --- NEW: Update Breadcrumb Header ---
    const pane = container.closest('.myCloud-commander-pane');
    if (pane) {
        const header = pane.querySelector('.myCloud-commander-header');
        if (header) renderCommanderBreadcrumbs(header, paneState.dir, side);
    }
    // -------------------------------------

    container.innerHTML = '';    
	
    // Background Context Menu (Commander View)
    container.oncontextmenu = function(e) {
        if (e.target.closest('.myCloudRow, th, .myCloud-commander-header')) return;
        e.preventDefault();
        e.stopPropagation();
        if (typeof myCloudShowBackgroundContextMenu === 'function') myCloudShowBackgroundContextMenu(e, side);
    };
	
    const st = myCloudState;
    const isRecycleBin = (paneState.dir === '/.recycle_bin');
	
    // --- COMMANDER SPARSE TAG FILTER ---
    if (paneState.activeTagFilter) {
        const tags = (st.tags && st.tags[st.key]) ? st.tags[st.key] : {};
        const taggedPaths = Object.keys(tags).filter(p => {
            let t = tags[p];
            if (t && !Array.isArray(t)) t = [t];
            return t && t.includes(paneState.activeTagFilter);
        });
        
        taggedPaths.forEach(tp => {
            let parts = tp.split('/').filter(x => x);
            let walker = '';
            parts.forEach(part => {
                walker += '/' + part;
                if (!st.allItems.some(i => i.name === walker)) st.allItems.push({ name: walker, size: 'DIR', date: '-' });
            });
        });

        paneState.items = st.allItems.filter(i => {
            const parent = i.name.substring(0, i.name.lastIndexOf('/') || 0) || '/';
            if (parent !== paneState.dir) return false;
            let t = tags[i.name];
            if (t && !Array.isArray(t)) t = [t];
            if (t && t.includes(paneState.activeTagFilter)) return true;
            return taggedPaths.some(tp => {
                if (i.name === '/') return true;
                if (tp.startsWith(i.name + '/')) return true; // Leads down to tag
                if (tp === '/') return true; 
                if (i.name.startsWith(tp + '/')) return true; // Inherits from tag
                return false;
            });
       });
    } else {
        paneState.items = st.allItems.filter(i => {
            const parent = i.name.substring(0, i.name.lastIndexOf('/') || 0) || '/';
            return parent === paneState.dir;
        });
    }

    // Sort items (using global sort settings for consistency)
    paneState.items.sort((a, b) => {
        const isADir = a.size === 'DIR', isBDir = b.size === 'DIR';
        if (isADir && !isBDir) return -1; if (!isADir && isBDir) return 1;
        
        let val = 0, dir = st.sort.dir;
        if (st.sort.col === 'size') { 
            if (!isADir && !isBDir) val = (parseInt(a.size) - parseInt(b.size)) * dir; 
        } else if (st.sort.col === 'date') { 
            if (a.date < b.date) val = -1 * dir; else if (a.date > b.date) val = 1 * dir; 
        } else { 
            const getSortName = (item) => {
                let n = item.displayName || item.name.split('/').pop();
                return n.replace(/\.enc$/, '');
            };
            const nameA = getSortName(a);
            const nameB = getSortName(b);
            val = nameA.localeCompare(nameB, undefined, { numeric: true, sensitivity: 'base' }) * dir;
        }
        const getSortNameFb = (item) => (item.displayName || item.name.split('/').pop()).replace(/\.enc$/, '');
        return val === 0 ? getSortNameFb(a).localeCompare(getSortNameFb(b), undefined, { numeric: true, sensitivity: 'base' }) : val;
    });

    // Render List View (Forced for Commander)
    const tableContainer = document.createElement('div');
    tableContainer.className = 'myCloudTableContainer';
    
    const table = document.createElement('table');
    table.className = 'myCloudTable';
    
    const thead = table.createTHead();
    const headerRow = thead.insertRow();
    const columns = [
        { title: '✓', key: null },
        { title: '', key: null },
        { title: myCloud_LANG.col_name, key: 'name' },
        { title: isRecycleBin ? myCloud_LANG.col_origin : myCloud_LANG.col_size, key: isRecycleBin ? 'origin' : 'size' },
        { title: isRecycleBin ? myCloud_LANG.col_deleted : myCloud_LANG.col_date, key: 'date' }
    ];
    
    columns.forEach((col) => {
        const th = document.createElement('th');
        th.textContent = col.title;
		th.style.setProperty('top', '0px', 'important');
        // Simple sort toggle (re-renders THIS pane only)
        if (col.key) {
            if (st.sort.col === col.key) th.textContent += (st.sort.dir === 1 ? ' ▲' : ' ▼');
            th.onclick = () => {
                if (st.sort.col === col.key) st.sort.dir *= -1;
                else { st.sort.col = col.key; st.sort.dir = 1; }
                renderCommanderContent(container, paneState, side);
            };
        }
        headerRow.appendChild(th);
    });
    
    const tbody = table.createTBody();
    
    // Up directory row
    if (paneState.dir !== '/') {
        const upRow = tbody.insertRow();
        upRow.className = 'myCloudRow';
        upRow.dataset.fullpath = '..';
        upRow.innerHTML = '<td></td><td class="ce-col-icon"><span class="myCloudIcon">' + myCloudIconFolder + '</span></td><td><div class="ce-row-content"><span class="ce-name-text" style="font-weight:bold">..</span></div></td><td></td><td></td>';
        upRow.ondblclick = () => commanderGoUp(side);
        // Add click handler for selection
        upRow.onclick = (e) => commanderSelectRow(upRow, '..', side, e);
    }
    
    // File rows
    paneState.items.forEach(i => {
        renderCommanderRow(i, tbody, paneState, side);
    });
    
    tableContainer.appendChild(table);
    container.appendChild(tableContainer);

    // [FIX 2] Trigger Thumbnail Observer for new elements
    const devKey = myCloudGetCurrentDeviceKey();
    if (st.settings && st.settings[devKey] && st.settings[devKey].showListThumbnails) {
        container.querySelectorAll('.ce-lazy-list-thumb').forEach(el => listIconObserver.observe(el));
    }
	if (typeof myCloudSaveCurrentPathState === 'function') myCloudSaveCurrentPathState();
}


function renderCommanderRow(item, tbody, paneState, side) {
    const row = tbody.insertRow();
    row.className = 'myCloudRow';
    row.dataset.fullpath = item.name;
    row.dataset.commanderSide = side;
    
    let displayName = item.displayName || (myCloudState.pathNames && myCloudState.pathNames[item.name]) || item.name.split('/').pop();
    let realFilename = displayName;

    const isEncrypted = item.name.endsWith('.enc') || item.isEncrypted === true || (typeof myCloudCrypto !== 'undefined' && myCloudCrypto.isDirEncrypted(item.name) && item.size === 'DIR');
    let isUnlocked = typeof myCloudCrypto !== 'undefined' ? myCloudCrypto.isDirUnlocked(myCloudCrypto.getCryptoRoot(item.name)) : false;
	if (isEncrypted && !item.isBrokenEncryption) {
         realFilename = displayName.replace(/\.enc$/, '');
         displayName = realFilename;
     }

    const ext = realFilename.split('.').pop().toLowerCase();
    const isDir = item.size === 'DIR';
    const isSelected = paneState.selectedFiles.includes(item.name);
    const isRecycleBin = (paneState.dir === '/.recycle_bin');
 

    if (isSelected) row.classList.add('selected');
    
    // Checkbox
    const checkCell = row.insertCell();
    checkCell.className = 'ce-col-check';
    const checkbox = document.createElement('input');
    checkbox.type = 'checkbox';
    checkbox.className = 'myCloudCheckbox';
    checkbox.checked = isSelected;
    checkbox.onclick = (e) => {
        e.stopPropagation();
        commanderSelectRow(row, item.name, side, { ctrlKey: true, shiftKey: e.shiftKey });
    };
    checkCell.appendChild(checkbox);
    
    // Icon
    const iconCell = row.insertCell();
    iconCell.className = 'ce-col-icon';
    const iconWrap = document.createElement('div');
    iconWrap.style.cssText = 'position:relative; display:inline-flex; align-items:center; justify-content:center; width:24px; height:24px; vertical-align:middle;';
    const iconSpan = document.createElement('span');
    iconSpan.className = 'myCloudIcon';
    
    // [FIX 3] Thumbnail Logic
    const devKey = myCloudGetCurrentDeviceKey();
    const showThumbs = myCloudState.settings && myCloudState.settings[devKey] && myCloudState.settings[devKey].showListThumbnails;
    const isImage = ['jpg','jpeg','png'].includes(ext);
    const isLink = item.isLink === true;

    if (isDir || isRecycleBin) {
        if (isRecycleBin) {
             // Items inside bin get generic file/folder icon or specific type icon, but usually we just want file type
             iconSpan.innerHTML = isDir ? myCloudIconFolder : (myCloudTypeIcons[ext] || myCloudTypeIcons._default);
        } else {
             if (item.name === '/.recycle_bin') {
                  iconSpan.innerHTML = myCloudSvg.recycle_main;
             } else if (isLink) {
                 iconSpan.innerHTML = myCloudIconLinkFolder;
             } else {
                 iconSpan.innerHTML = myCloudIconFolder;
             }
        }
    } else {
        if (showThumbs && isImage) {
            // Add Lazy Load Class
            iconSpan.classList.add('ce-lazy-list-thumb');
            iconSpan.dataset.path = item.name;
            iconSpan.dataset.filename = displayName;
            iconSpan.innerHTML = myCloudTypeIcons[ext] || myCloudTypeIcons._default; // Placeholder
        } else {
            iconSpan.innerHTML = myCloudTypeIcons[ext] || myCloudTypeIcons._default;
        }
    }
    iconWrap.appendChild(iconSpan);
    
    if (isEncrypted || item.isBrokenEncryption) {
        const badge = document.createElement('div');
        badge.className = 'ce-icon-badge';
        badge.textContent = item.isBrokenEncryption ? ' ️' : (isUnlocked ? '🔓' : '🔒');
        iconWrap.appendChild(badge);
    }
	iconCell.appendChild(iconWrap);

    // Name
    const nameCell = row.insertCell();
    let adminHtml = (typeof myCloudUserRole !== 'undefined' && myCloudUserRole === 'admin_mode') ? `<span style="font-family:monospace; font-size:11px; color:var(--text-secondary); background:var(--gray-15); padding:1px 6px; border-radius:4px; margin-left:8px; margin-right:8px; border:1px solid var(--border-default); flex-shrink:0;">${item.owner || '-'} ${item.perms || '-'}</span>` : '';

    let tagHtml = '';
	const st = myCloudState;
    const tags = (st.tags && st.tags[st.key]) ? st.tags[st.key] : {};
    if (tags[item.name]) {
        let itemTags = tags[item.name];
        if (!Array.isArray(itemTags)) itemTags = [itemTags];
        tagHtml = '<div class="ce-tag-stack">';
        itemTags.forEach((c, idx) => {
            tagHtml += '<span class="ce-tag-dot" title="' + window.myCloudGetTagName(c) + '" style="background-color:' + c + '; z-index:' + (itemTags.length - idx) + ';"></span>';
        });
        tagHtml += '</div>';
    }

    let safeTitle = typeof myCloudEscapeHtml === 'function' ? myCloudEscapeHtml(displayName) : displayName.replace(/"/g, '&quot;');
    let nameStyle = '';
    if (item.isBrokenEncryption) {
        nameStyle = 'color:var(--danger, #e81123); font-weight:bold;';
        safeTitle = (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.broken_enc ? myCloud_LANG.broken_enc : 'Unencrypted file in Vault!') + ' - ' + safeTitle;
    }
    nameCell.innerHTML = '<div class="ce-row-content"><span class="ce-name-text" style="flex: 0 1 auto; ' + nameStyle + '" title="' + safeTitle + '">' + displayName + '</span>' + tagHtml + '<div style="flex:1; min-width:10px;"></div>' + adminHtml + '</div>';

  
    // Size
    const sizeCell = row.insertCell();
    if (isRecycleBin) {
        sizeCell.textContent = item.origin || '-';
        sizeCell.style.color = '#888';
        sizeCell.style.fontSize = '12px';
        sizeCell.style.textAlign = 'left';
    } else {
        sizeCell.textContent = isDir ? myCloud_LANG.folder : myCloudFormatBytes(parseInt(item.size));
        sizeCell.style.textAlign = 'right';
    }
    
    // Date
    const dateCell = row.insertCell();
    dateCell.textContent = item.date;
    
    // Events
    row.onclick = (e) => commanderSelectRow(row, item.name, side, e);
    row.ondblclick = () => commanderHandleEnter(item, side);
    row.oncontextmenu = (e) => {
        e.preventDefault();
        e.stopPropagation();
        if (!isSelected) commanderSelectRow(row, item.name, side, e);
        commanderShowContextMenu(e, item, side);
    };
    
    // Draggable
    if (window.myCloudActionAllowed('move') && item.name !== '/.recycle_bin') {
        row.draggable = true;
        row.addEventListener('dragstart', (e) => {
            if (!paneState.selectedFiles.includes(item.name)) {
                commanderSelectRow(row, item.name, side, {});
            }
            const dragImg = window.myCloudGetDragImage(paneState.selectedFiles.length);
            e.dataTransfer.setDragImage(dragImg, 20, 20);
            e.dataTransfer.setData('text/plain', JSON.stringify(paneState.selectedFiles));
            e.dataTransfer.effectAllowed = 'copyMove';
        });
    }
    
    return row;
}


// Handles row selection (Mouse Click) in Commander Panes
function commanderSelectRow(row, path, side, event) {
    // Dismiss context menu on selection change
    if (event && event.button !== 2 && typeof myCloudCloseContextMenus === 'function') {
        myCloudCloseContextMenus();
    }

    const st = myCloudState;
    // Determine which pane state we are modifying
    const paneState = (side === 'left') ? st.commanderLeft : st.commanderRight;
    
    // 1. Activate Pane
    if (st.commanderActive !== side) {
        st.commanderActive = side;
        document.querySelectorAll('.myCloud-commander-pane').forEach(p => p.classList.remove('active'));
        const pane = document.querySelector('.myCloud-commander-pane[data-side="' + side + '"]');
        if (pane) pane.classList.add('active');
        st.currentDir = paneState.dir; // Sync Active Dir
    }

    // 2. Selection Logic
    if (event.ctrlKey || event.metaKey) {
        if (paneState.selectedFiles.includes(path)) {
            paneState.selectedFiles = paneState.selectedFiles.filter(f => f !== path);
        } else {
            paneState.selectedFiles.push(path);
        }
    } else if (event.shiftKey) {
        // Simple range extension (Anchor logic omitted for brevity, usually acceptable)
        if (!paneState.selectedFiles.includes(path)) paneState.selectedFiles.push(path);
    } else {
        // Single Select
        paneState.selectedFiles = [path];
    }
    
    // 3. Update Global State (CRITICAL for Restore/Delete to work)
    st.selectedFiles = paneState.selectedFiles;
    st.visualCursorIndex = Array.from(row.parentNode.children).indexOf(row); // Sync Cursor

    // 4. Update Visuals (Local Pane Only)
    const container = row.closest('tbody') || row.parentElement;
    const rows = container.querySelectorAll('.myCloudRow');
    
    rows.forEach(r => {
        const rPath = r.dataset.fullpath;
        const isSel = paneState.selectedFiles.includes(rPath);
        
        if (isSel) r.classList.add('selected');
        else r.classList.remove('selected');
        
        const cb = r.querySelector('.myCloudCheckbox');
        if (cb) cb.checked = isSel;
    });

    myCloudUpdateToolbarState();
}


// 4. Enter Directory (Reset Cursor to 0)
function commanderHandleEnter(item, side) {
    const st = myCloudState;
    const paneState = side === 'left' ? st.commanderLeft : st.commanderRight;
    
    if (item.name === '..' || item.isUpDir) {
        commanderGoUp(side);
        return;
    }
    
    const ext = item.name.split('.').pop().toLowerCase();

    // [NEW] E2E Pre-Navigation Trap for Commander Mode
    const isEnc = typeof myCloudCrypto !== 'undefined' && myCloudCrypto.isDirEncrypted(item.name);
    if (isEnc && !myCloudCrypto.isDirUnlocked(item.name)) {
        myCloudAction_EncryptPrompt(myCloudCrypto.getCryptoRoot(item.name), true, () => {
            commanderHandleEnter(item, side);
        });
        return;
    }

    if (item.size === 'DIR' || ext === 'zip') {
        paneState.dir = item.name;
        paneState.selectedFiles = [];
		
        // Sync global currentDir if operating on the active pane
        if (st.commanderActive === side) {
            st.currentDir = item.name;
        }

		// RESET CURSOR
        paneState.visualCursorIndex = 0; 
        paneState.viewMode = 'list';
        
        myCloudFetchDirectory(item.name, 2, true).then(() => {
            paneState.items = st.allItems.filter(i => {
                const parent = i.name.substring(0, i.name.lastIndexOf('/') || 0) || '/';
                return parent === item.name;
            });
            
            const pane = document.querySelector('.myCloud-commander-pane[data-side="' + side + '"]');
            const content = pane.querySelector('.myCloud-commander-content');
            
            // 1. HARD SCROLL RESET BEFORE RENDER
            // This prevents the browser from trying to "restore" the scroll position of the previous view
            content.scrollTop = 0;
            
            renderCommanderContent(content, paneState, side);
            
            // 2. FOCUS & HIGHLIGHT (with robust delay)
            requestAnimationFrame(() => {
                const rows = content.querySelectorAll('.myCloudRow');
                if (rows.length > 0) {
                    commanderSelectRow(rows[0], rows[0].dataset.fullpath, side, {});
                    // Force align to top
                    rows[0].scrollIntoView({ block: 'start', inline: 'nearest' });
                }
                content.focus();
                
                // Double-tap scroll reset to fight browser race conditions
                setTimeout(() => { 
                    if (content.scrollTop > 0 && paneState.visualCursorIndex === 0) {
                        content.scrollTop = 0; 
                    }
                }, 10);
            });
        });
    } else {
        const dKey = myCloudGetCurrentDeviceKey();
        const allowPreview = myCloudState.settings && myCloudState.settings[dKey] && myCloudState.settings[dKey].clickToPreview;
        
        const isInsideZip = myCloudIsInsideZip(item.name);
        const isEditableOfficeDoc = typeof officeExts !== 'undefined' && officeExts.includes(ext) && window.myCloudActionAllowed('edit_file') && !isInsideZip;
        const isEditablePdf = (ext === 'pdf' && window.myCloudActionAllowed('edit_file') && !isInsideZip);

        if (isEditableOfficeDoc || isEditablePdf) {
            myCloudOpenOnlyOffice(item.name);
        } else if (myCloudUserRole === 'admin_mode') {
             myCloudEditFile(item.name);
        } else if (typeof myCloudConfig !== 'undefined' && myCloudConfig.edit.includes(ext) && window.myCloudActionAllowed('edit_file') && !isInsideZip) {
             myCloudEditFile(item.name);
        } else if (typeof myCloudIsPreviewable === 'function' && myCloudIsPreviewable(ext) && allowPreview) {
             st.previewPath = item.name;
             myCloudDownloadFile(item.name, item.name.split('/').pop(), true);
         } else {
             myCloudDownloadFile(item.name, item.name.split('/').pop(), false);
         }
    }
}


// 5. Go Up (Focus on old directory)
function commanderGoUp(side) {
    const st = myCloudState;
    const paneState = side === 'left' ? st.commanderLeft : st.commanderRight;
    
    if (paneState.dir === '/') return;
    
    const parent = paneState.dir.substring(0, paneState.dir.lastIndexOf('/')) || '/';
    const oldDir = paneState.dir; // Remember where we came from
    
    paneState.dir = parent;
    paneState.selectedFiles = [];
    paneState.viewMode = 'list';
	
    //  Sync global currentDir if operating on the active pane
    if (st.commanderActive === side) {
        st.currentDir = parent;
    }
    
    myCloudFetchDirectory(parent, 2, true).then(() => {
        paneState.items = st.allItems.filter(i => {
            const p = i.name.substring(0, i.name.lastIndexOf('/') || 0) || '/';
            return p === parent;
        });
        
        const pane = document.querySelector('.myCloud-commander-pane[data-side="' + side + '"]');
        const content = pane.querySelector('.myCloud-commander-content');
        
        // Reset scroll to ensure clean slate
        content.scrollTop = 0;
        
        renderCommanderContent(content, paneState, side);
        
        requestAnimationFrame(() => {
            const rows = Array.from(content.querySelectorAll('.myCloudRow'));
            const oldRowIdx = rows.findIndex(r => r.dataset.fullpath === oldDir);
            
            if (oldRowIdx !== -1) {
                // Return to old folder position
                paneState.visualCursorIndex = oldRowIdx;
                commanderSelectRow(rows[oldRowIdx], oldDir, side, {});
                rows[oldRowIdx].scrollIntoView({ block: 'center', inline: 'nearest' });
            } else if (rows.length > 0) {
                // Fallback to top
                paneState.visualCursorIndex = 0;
                commanderSelectRow(rows[0], rows[0].dataset.fullpath, side, {});
                content.scrollTop = 0;
            }
            content.focus();
        });
    });
}

// Helper: Refresh a specific pane (used by F5 and Cross-Pane Drops)
// Helper: Refresh a specific pane (used by F5 and Middle Toolbar)
function refreshCommanderPane(side) {
    const st = myCloudState;
    const paneState = side === 'left' ? st.commanderLeft : st.commanderRight;
    
    // 1. Capture state BEFORE refresh
    // If this pane is active, we want to keep its cursor position. 
    // If items were just deleted, the index might point to a hole, but we'll clamp it after reload.
    let savedIndex = paneState.visualCursorIndex || 0;
    const wasActive = (st.commanderActive === side);

    myCloudFetchDirectory(paneState.dir, 2, true).then(() => {
        // 2. Update data items from global cache
        paneState.items = st.allItems.filter(i => {
            const parent = i.name.substring(0, i.name.lastIndexOf('/') || 0) || '/';
            return parent === paneState.dir;
        });
        
        // 3. Re-render DOM
        const pane = document.querySelector('.myCloud-commander-pane[data-side="' + side + '"]');
        if (pane) {
            const content = pane.querySelector('.myCloud-commander-content');
            renderCommanderContent(content, paneState, side);
            
            // 4. Restore Cursor & Selection
            requestAnimationFrame(() => {
                const rows = content.querySelectorAll('.myCloudRow');
                
                // If there are files in the list
                if (rows.length > 0) {
                    // Clamp index (prevents jumping to top if we were at the end)
                    if (savedIndex >= rows.length) savedIndex = rows.length - 1;
                    if (savedIndex < 0) savedIndex = 0;
                    
                    const row = rows[savedIndex];
                    
                    // CRITICAL: Only re-select if this pane was active OR it's the source of an action
                    // For the passive pane (Target), we just update the view, we don't select anything.
                    if (wasActive) {
                        // Pass {} to simulate simple click (clears old selection, selects new)
                        commanderSelectRow(row, row.dataset.fullpath, side, {});
                        row.scrollIntoView({block: 'nearest'});
                    }
                }
                
                // 5. Restore Focus (ONLY if it was active before)
                if (wasActive) {
                    content.focus();
                }
            });
        }
    });
}


function commanderShowContextMenu(e, item, side) {
    // Reuse existing context menu but adjust callbacks
    const st = myCloudState;
    const paneState = side === 'left' ? st.commanderLeft : st.commanderRight;
    
    // Temporarily swap state
    const backup = {
        currentDir: st.currentDir,
        selectedFiles: st.selectedFiles
    };
    
    st.currentDir = paneState.dir;
    st.selectedFiles = paneState.selectedFiles;
    
    myCloudShowContextMenu(e, item, false);
    
    // Restore
    Object.assign(st, backup);
}

// Re-Verify Resizer Init (Must use specific class)
function initCommanderResizer() {
    const resizer = document.querySelector('.myCloud-commander-resizer-handle');
    const body = document.querySelector('.myCloudBody');
    
    // [FIX] Define 'st' reference to the global state
    const st = myCloudState;

    if (!resizer || !body) return;
    if (resizer.dataset.commanderInit) return;
    resizer.dataset.commanderInit = 'true';
    
    let startX = 0;
    
    const onMouseMove = (e) => {
        const delta = e.clientX - startX;
        const totalWidth = body.clientWidth - 40; 
        const leftPane = document.querySelector('.myCloud-commander-pane[data-side="left"]');
        const rightPane = document.querySelector('.myCloud-commander-pane[data-side="right"]');
        if(!leftPane || !rightPane) return;
        
        const newLeftWidth = leftPane.offsetWidth + delta;
        const newRatio = newLeftWidth / totalWidth;
        
        if (newRatio >= 0.2 && newRatio <= 0.8) {
             leftPane.style.flex = newRatio;
             rightPane.style.flex = (1 - newRatio);
             startX = e.clientX; // Reset start to prevent drift
        }
    };
    
    const onMouseUp = () => {
        document.removeEventListener('mousemove', onMouseMove);
        document.removeEventListener('mouseup', onMouseUp);
        const leftPane = document.querySelector('.myCloud-commander-pane[data-side="left"]');
        if (leftPane) {
             const ratio = leftPane.offsetWidth / (body.clientWidth - 40);
             const devKey = myCloudGetCurrentDeviceKey();
             
             // [FIX] Now 'st' is defined and valid
             if (st.settings && st.settings[devKey]) {
                 st.settings[devKey].commanderSplit = ratio;
                 myCloudSaveSettings();
             }
        }
    };
    
    resizer.addEventListener('mousedown', (e) => {
        e.preventDefault(); e.stopPropagation();
        startX = e.clientX;
        document.addEventListener('mousemove', onMouseMove);
        document.addEventListener('mouseup', onMouseUp);
    });
}

// Refactored Action Handler for reuse in Single/Double click swap
// Refactored Action Handler for reuse in Single/Double click swap
function myCloudHandleEnterAction(i, ext, isContainer) {
    const st = myCloudState;
    
    if (isContainer && st.currentDir !== '/.recycle_bin') {
        myCloudHandleEnter({ name: i.name, size: 'DIR' });
    } else {
        if (st.currentDir === '/.recycle_bin') return; 
        const dKey = myCloudGetCurrentDeviceKey();
        const allowPreview = st.settings && st.settings[dKey] && st.settings[dKey].clickToPreview;
        
        // [NEW] Bypass checks in Admin Mode and send everything to the Editor
        const cfg = typeof myCloudCloudConfig !== 'undefined' ? myCloudCloudConfig[st.key] : null;
        const isAdminMode = cfg && cfg.rights === 'admin_mode';
		const isBinaryOrMedia = binaryExts.includes(ext);
        const isInsideZip = myCloudIsInsideZip(st.currentDir);
        const isEditableOfficeDoc = typeof officeExts !== 'undefined' && officeExts.includes(ext) && window.myCloudActionAllowed('edit_file') && !isInsideZip;
        const isEditablePdf = (ext === 'pdf' && window.myCloudActionAllowed('edit_file') && !isInsideZip);
        
        if (isEditableOfficeDoc || isEditablePdf) {
            myCloudOpenOnlyOffice(i.name);
		} else if (isAdminMode && !isBinaryOrMedia) {
            myCloudEditFile(i.name);
		} else if (myCloudConfig.edit.includes(ext) && window.myCloudActionAllowed('edit_file') && !isInsideZip) {
            myCloudEditFile(i.name);
        } else if (myCloudConfig.preview.includes(ext) && allowPreview) {
            myCloudDownloadFile(i.name, i.name.split('/').pop(), true);
        } else {
            myCloudDownloadFile(i.name, i.name.split('/').pop(), false);
        }
    }
}


window.myCloudRenderSkeletons = function(container) {
    const isGrid = myCloudState.viewMode === 'symbol';
    let html = '';
    if (isGrid) {
        const devKey = typeof myCloudGetCurrentDeviceKey === 'function' ? myCloudGetCurrentDeviceKey() : 'desktop';
        const size = (myCloudState.settings && myCloudState.settings[devKey] && myCloudState.settings[devKey].symbolSize) ? myCloudState.settings[devKey].symbolSize : 'medium';
        
        const iconSizes = { 'small': '24px', 'medium': '48px', 'large': '110px', 'xlarge': '200px' };
        const iconSize = iconSizes[size] || '48px';

        html += `<div class="myCloud-symbol-grid ce-sym-${size}" style="opacity:0.5;">`;
        for(let i=0; i<15; i++) {
            html += `<div class="myCloud-symbol-item" style="pointer-events:none; border:none;"><div class="ce-sym-icon-box"><div class="ce-skeleton ce-skeleton-icon" style="width:${iconSize};height:${iconSize};"></div></div><div class="ce-sym-label ce-skeleton ce-skeleton-text" style="width:60%; margin:0 auto;"></div></div>`;
        }
        html += '</div>';
    } else {
        html += '<table class="myCloudTable" style="width:100%; opacity:0.5;"><thead><tr><th></th><th></th><th>Name</th><th>Size</th><th>Date</th></tr></thead><tbody>';
        for(let i=0; i<10; i++) {
            html += `<tr class="myCloudRow"><td class="ce-col-check"></td><td class="ce-col-icon"><div class="ce-skeleton ce-skeleton-icon" style="width:24px;height:24px; margin:0 auto;"></div></td><td><div class="ce-skeleton ce-skeleton-text" style="width:${Math.random()*40+30}%"></div></td><td><div class="ce-skeleton ce-skeleton-text" style="width:40px; float:right;"></div></td><td><div class="ce-skeleton ce-skeleton-text" style="width:80px;"></div></td></tr>`;
        }
        html += '</tbody></table>';
    }
    container.innerHTML = html;
};

</script>