<?php
/**
 * ============================================================================
 * MODULE: Grid & Gallery View Generator
 * ============================================================================
 * Constructs the visual grid architecture and icon-based layout required for 
 * the icon and media gallery viewing modes.
 * NOTE: Executed exclusively by the client browser.
 * 
 * THIS IS PART OF THE PSEUDO "JS FILE" - SO THE NO PHP CODE IN THIS FILE!
 */

?><script>
// ============================================================
//  THUMBNAIL QUEUE MANAGER (True Parallel Loading)
// ============================================================
const myCloudThumbQueue = {
    queue: [],
    active: 0,
    maxConcurrent: 6, // Standard browser limit per domain
    
    clear: function() {
        this.queue = [];
        // Active requests will finish naturally and decrement 'active'
    },
    
    add: function(el, path, name) {
        if (el.dataset.inQueue === 'true') return;
        el.dataset.inQueue = 'true';
        this.queue.push({ el, path, name });
        this.process();
    },
    
    remove: function(el) {
        this.queue = this.queue.filter(item => item.el !== el);
        el.dataset.inQueue = 'false';
    },
    
    process: function() {
        // [FIX] Loop to fill ALL available slots immediately
        // Previous version only started ONE item per call, causing waterfall effect.
        while (this.active < this.maxConcurrent && this.queue.length > 0) {
            
            const item = this.queue.shift(); 
            
            // Skip detached elements (view changed/scrolled fast)
            if (!document.body.contains(item.el)) {
                continue; 
            }

            this.active++; // Mark slot as taken
            
            const { el, path, name } = item;
            const cacheKey = path + '_thumb';
            const st = myCloudState;
            
            // Define UI Update Helper
            const applyImg = (url) => {
                el.innerHTML = '<img src="' + url + '" draggable="false" style="width:100%; height:100%; object-fit:contain;">';
                el.classList.add('has-thumb');
            };

            // 1. Double check cache (maybe loaded by another thread)
            if (st.previewCache && st.previewCache[cacheKey]) {
                applyImg(st.previewCache[cacheKey]);
                this.active--; // Release slot immediately
                continue; // Loop again to fill this slot
            }

            const root = typeof myCloudCrypto !== 'undefined' ? myCloudCrypto.getCryptoRoot(path) : null;
            const isEnc = path.endsWith('.enc') || root;
            if (isEnc) {
                if (!root || !myCloudCrypto.isDirUnlocked(root)) { this.active--; this.process(); continue; }
                const fd = new URLSearchParams({ myCloud_action: 'get_download_token', myCloud_key: st.key, myCloud_token: typeof myCloudCsrfToken !== 'undefined' ? myCloudCsrfToken : '', path: path, filename: name, preview: '1', is_icon: '1' });
                fetch('', { method: 'POST', body: fd, priority: 'low' }).then(r=>r.json()).then(async resp => {
                    if (resp.status === 'OK') {
                        const r2 = await fetch(window.location.pathname + '?myCloud_token=' + resp.token);
                        const encBlob = await r2.blob();
                        const decBlob = await myCloudCrypto.decryptFile(root, encBlob);
                        const url = URL.createObjectURL(decBlob);
                        if (!st.previewCache) st.previewCache = {};
                        st.previewCache[cacheKey] = url;
                        if (document.body.contains(el)) applyImg(url);
                    }
                }).catch(()=>{}).finally(() => { this.active--; this.process(); });
                continue;
            }

            // 2. Secure Fast Path (Bypasses double HTTP handshake and JS queue locking)
            const fastUrl = window.myCloudGetFastThumbUrl(path);
            if (fastUrl) {
                const tempImg = new Image();
                tempImg.onload = () => {
                    st.previewCache[cacheKey] = fastUrl;
                    if (document.body.contains(el)) applyImg(fastUrl);
                    this.active--;
                    this.process();
                };
                tempImg.onerror = () => {
                    // Fallback to token if Fast Path fails (e.g. 204 No Content for unsupported files)
                    const fd = new URLSearchParams({ myCloud_action: 'get_download_token', myCloud_key: st.key, myCloud_token: myCloudCsrfToken, path: path, filename: name, preview: '1', is_icon: '1' });
                    fetch('', { method: 'POST', body: fd, priority: 'low' }).then(r => r.json()).then(resp => {
                        if (resp.status === 'OK') {
                            const url = '?myCloud_token=' + resp.token;
                            st.previewCache[cacheKey] = url;
                            if (document.body.contains(el)) applyImg(url);
                        }
                    }).catch(() => {}).finally(() => { this.active--; this.process(); });
                };
                tempImg.src = fastUrl;
               continue;
            }


            // 3. FALLBACK: Fetch Token (Used for ZIP contents)

            const fd = new URLSearchParams();
            fd.append('myCloud_action', 'get_download_token');
            fd.append('myCloud_key', st.key);
            fd.append('myCloud_token', myCloudCsrfToken);
            fd.append('path', path);
            fd.append('filename', name);
            fd.append('preview', '1');
            fd.append('is_icon', '1');

            fetch('', { 
                method: 'POST', 
                body: fd,
                priority: 'low' 
            })
            .then(r => r.json())
            .then(resp => {
                if (resp.status === 'OK') {
                    const url = '?myCloud_token=' + resp.token;
                    if (!st.previewCache) st.previewCache = {};
                    st.previewCache[cacheKey] = url;
                    
                    if (document.body.contains(el)) {
                        applyImg(url);
                    }
                }
            })
            .catch(() => { /* Ignore errors */ })
            .finally(() => {
                this.active--; // Release slot
                this.process(); // Trigger refill
            });
        }
    }
};

// ============================================================
//  INTERSECTION OBSERVER (Feeds the Queue)
// ============================================================
const symbolThumbObserver = new IntersectionObserver((entries, obs) => {
    entries.forEach(entry => {
        const el = entry.target; 
        
        if (entry.isIntersecting) {
            const path = el.dataset.path;
            const name = el.dataset.name;
            const cacheKey = path + '_thumb';
            
            // If cached, apply immediately (skip queue)
            if (myCloudState.previewCache && myCloudState.previewCache[cacheKey]) {
                obs.unobserve(el);
                el.innerHTML = '<img src="' + myCloudState.previewCache[cacheKey] + '" draggable="false" style="width:100%; height:100%; object-fit:contain;">';
                el.classList.add('has-thumb');
            } else {
                myCloudThumbQueue.add(el, path, name);
            }
        } else {
            myCloudThumbQueue.remove(el);
        }
    });
}, { rootMargin: "200px" }); // Increased margin to pre-load items just outside view

function myCloudRenderSymbols(containerEl, items, st, isReadOnly) {
    // [FIX] Clear any pending thumbnail downloads from previous view
    myCloudThumbQueue.clear();

    const devKey = myCloudGetCurrentDeviceKey();
    const config = st.settings ? st.settings[devKey] : myCloudDefaultSettings.desktop;
    const size = config.symbolSize || 'medium';
    const showCheckboxes = config.showCheckboxes;
	const useDarkMode = (st.interface === 'symbol-dark' || st.interface === 'gallery') || config.symbolDarkMode;
	
	const isSingleClick = config.singleClick && st.interface !== 'gallery' && st.viewMode !== 'gallery';
	
	if (useDarkMode) containerEl.classList.add('symbol-dark-container');
	
    let customTooltip = document.getElementById('ce-sym-tooltip');
    if (!customTooltip) {
        customTooltip = document.createElement('div');
        customTooltip.id = 'ce-sym-tooltip';
        customTooltip.style.cssText = 'position:fixed; z-index:200000; background:var(--gray-00, #fff); color:var(--text-primary, #000); border:1px solid var(--border-medium, #ccc); padding:10px 14px; border-radius:6px; box-shadow:0 8px 24px rgba(0,0,0,0.2); font-size:12px; pointer-events:auto; display:none; max-width:320px; word-break:break-word; line-height:1.5; font-family:var(--font-family, sans-serif);';
        
        // Allow hovering over the tooltip itself to click the maps link
        customTooltip.addEventListener('mouseenter', () => clearTimeout(window.symTooltipHideTimer));
        customTooltip.addEventListener('mouseleave', () => { customTooltip.style.display = 'none'; });
        document.body.appendChild(customTooltip);
    }

	
    const oldToast = document.getElementById('myCloudSymbolActionToast');
    if (oldToast) oldToast.remove();

    const grid = document.createElement('div');
    grid.className = 'myCloud-symbol-grid ce-sym-' + size;
	if (useDarkMode) grid.classList.add('symbol-dark-mode');
	
    // --- TOOLTIP EVENT DELEGATION ---
    grid.addEventListener('mouseover', function(e) {
        const itemEl = e.target.closest('.myCloud-symbol-item');
        if (!itemEl) return;
        
        // Stash the native title instantly to prevent browser OS overlap
        if (itemEl.hasAttribute('title')) {
            itemEl.dataset.title = itemEl.getAttribute('title');
            itemEl.removeAttribute('title');
        }

        if (window.ceHoveredItem === itemEl) return;
        window.ceHoveredItem = itemEl;
        
        clearTimeout(window.ceHoverTimer);
        clearTimeout(window.symTooltipHideTimer);
        
        window.ceHoverTimer = setTimeout(function() {
            const ttip = document.getElementById('ce-sym-tooltip');
            // CRITICAL FIX: Do not show hover tooltip if Context Menu is active
            if (document.querySelector('.myCloudContextMenu')) return;
            if (!ttip || grid.classList.contains('drag-active-global') || document.querySelector('.myCloud-marquee') || grid.classList.contains('multiselect-mode')) return;
            
            const path = itemEl.dataset.fullpath;
            if (!path) return;
            
            const i = myCloudState.allItems.find(x => x.name === path);
            if (!i) return;
            
            const isDir = i.size === 'DIR';
            const displayName = i.displayName || i.name.split('/').pop();
            const ext = displayName.split('.').pop().toLowerCase();
            
            const safeName = typeof myCloudEscapeHtml === 'function' ? myCloudEscapeHtml(displayName) : displayName;
            const fSize = isDir ? '' : (typeof myCloudFormatBytes === 'function' ? myCloudFormatBytes(parseInt(i.size) || 0) : i.size);
            
            // Format: Full file name, Last modified date, Size (human readable)
            let baseHtml = '<div style="font-weight:600; margin-bottom:4px; word-break:break-all;">' + safeName + '</div>';
            baseHtml += '<div style="color:var(--text-secondary, #666);">' + (i.date || '-') + '</div>';
            if (!isDir) baseHtml += '<div style="color:var(--text-secondary, #666);">' + fSize + '</div>';
            
            const isImage = ['jpg','jpeg','tiff','webp'].includes(ext);
            if (isImage) {
                baseHtml += '<div id="ce-tooltip-exif" style="margin-top:6px; border-top:1px solid var(--border-subtle, #eee); padding-top:6px; display:none;"></div>';
            }
            
            ttip.innerHTML = baseHtml;
            ttip.style.display = 'block';
            
            const rect = itemEl.getBoundingClientRect();
            let top = rect.bottom + 5;
            let left = rect.left + (rect.width/2) - (ttip.offsetWidth/2);
            
            if (top + ttip.offsetHeight > window.innerHeight) top = rect.top - ttip.offsetHeight - 5;
            if (left < 10) left = 10;
            if (left + ttip.offsetWidth > window.innerWidth) left = window.innerWidth - ttip.offsetWidth - 10;
            
            ttip.style.top = top + 'px';
            ttip.style.left = left + 'px';

            if (isImage) {
                const exifDiv = document.getElementById('ce-tooltip-exif');
                if (exifDiv) {
                    exifDiv.style.display = 'block';
                    exifDiv.innerHTML = '<span style="opacity:0.6;">Loading EXIF...</span>';
                    
                    const fd = new URLSearchParams();
                    fd.append('myCloud_action', 'get_exif');
                    fd.append('myCloud_key', myCloudState.key);
                    fd.append('myCloud_token', myCloudCsrfToken);
                    fd.append('path', i.name);
                    
                    fetch('', { method: 'POST', body: fd }).then(r=>r.json()).then(res => {
                        if (!document.body.contains(exifDiv)) return;
                        if (res.status === 'OK' && res.data && Object.keys(res.data).length > 0) {
                            let eHtml = '';
                            if (res.data['Dimensions']) eHtml += '<div>Dimensions: ' + res.data['Dimensions'] + '</div>';
                            if (res.data['Camera']) eHtml += '<div>Camera: ' + (typeof myCloudEscapeHtml === 'function' ? myCloudEscapeHtml(res.data['Camera']) : res.data['Camera']) + '</div>';
                            if (res.data['Color Profile']) eHtml += '<div>Color Profile: ' + (typeof myCloudEscapeHtml === 'function' ? myCloudEscapeHtml(res.data['Color Profile']) : res.data['Color Profile']) + '</div>';
                            if (res.data['GPS']) {
                                eHtml += '<div>GPS: <a href="https://www.google.com/maps?q=' + res.data['GPS'] + '" target="_blank" style="color:var(--accent-primary, #0078d4); text-decoration:underline; pointer-events:auto;">Open in Google Maps</a></div>';
                            }
                            exifDiv.innerHTML = eHtml || '<span style="opacity:0.6;">No EXIF data</span>';
                        } else {
                            exifDiv.style.display = 'none';
                        }
                    }).catch(() => {
                        if (document.body.contains(exifDiv)) exifDiv.style.display = 'none';
                    });
                }
            }
        }, 600);
    });
    
    grid.addEventListener('mouseout', function(e) {
        const itemEl = e.target.closest('.myCloud-symbol-item');
        if (!itemEl) return;
        
        if (itemEl.contains(e.relatedTarget)) return;
        
        // Restore the native title attribute
        if (itemEl.dataset.title) {
            itemEl.setAttribute('title', itemEl.dataset.title);
            itemEl.removeAttribute('data-title');
        }

        window.ceHoveredItem = null;
        clearTimeout(window.ceHoverTimer);
        
        window.symTooltipHideTimer = setTimeout(function() {
            const ttip = document.getElementById('ce-sym-tooltip');
            if (ttip) ttip.style.display = 'none';
        }, 300);
    });
    
    grid.addEventListener('mousedown', function() {
        clearTimeout(window.ceHoverTimer);
        const ttip = document.getElementById('ce-sym-tooltip');
        if (ttip) ttip.style.display = 'none';
    });

    // [FIX] 1. Define Toggle Helper with Global Click Listener
    const toggleSymbolMultiselect = (active) => {
        const toast = document.getElementById('myCloudSymbolActionToast');
        
        // Define the outside click handler
        const handleOutsideClick = (e) => {
            // Ignore clicks inside the Toast, the Grid, or if the element is gone
            if (!document.body.contains(e.target)) return;
            if (e.target.closest('#myCloudSymbolActionToast') || e.target.closest('.myCloud-symbol-grid')) return;
            
            // Clicked outside (Toolbar, Help, Sidebar, etc.) -> Close Mode
            toggleSymbolMultiselect(false);
        };

        if (active) {
            grid.classList.add('multiselect-mode');
            if (toast) toast.classList.add('active');
            
            // Add global listener to detect clicks outside (delayed to skip current click)
            setTimeout(() => {
                document.addEventListener('click', handleOutsideClick);
            }, 0);
            
            // Store cleanup function globally so other modules (init.php) can kill it
            window.myCloudSymbolCleanup = () => {
                document.removeEventListener('click', handleOutsideClick);
                delete window.myCloudSymbolCleanup;
            };
            
        } else {
            grid.classList.remove('multiselect-mode');
            if (toast) toast.classList.remove('active');
            
            // Remove global listener
            document.removeEventListener('click', handleOutsideClick);
            if (window.myCloudSymbolCleanup) delete window.myCloudSymbolCleanup;
        }
    };
    
    // Safety: Ensure previous listeners are gone before creating new grid
    if (typeof window.myCloudSymbolCleanup === 'function') window.myCloudSymbolCleanup();

    // [FIX] 2. Create Floating Toast & Grid Listener
    if (useDarkMode) {
        const oldToast = document.getElementById('myCloudSymbolActionToast');
        if (oldToast) oldToast.remove();

        const toast = document.createElement('div');
        toast.id = 'myCloudSymbolActionToast';
        
        const mkBtn = (icon, action) => {
            const b = document.createElement('button');
            b.className = 'ce-sym-toast-btn';
            b.innerHTML = icon;
            b.onclick = (e) => { e.stopPropagation(); action(); };
            return b;
        };

        const shareIcon = '<svg viewBox="0 0 24 24"><path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.66 0 1.61 1.31 2.92 2.92 2.92s2.92-1.31 2.92-2.92c0-1.61-1.31-2.92-2.92-2.92z"/></svg>';

        toast.appendChild(mkBtn(shareIcon, () => myCloudAction_ShareSelection()));
        toast.appendChild(mkBtn(myCloudSvg.copy, () => myCloudAction_CopyMove(false)));
        toast.appendChild(mkBtn(myCloudSvg.move, () => myCloudAction_CopyMove(true)));
        toast.appendChild(mkBtn(myCloudSvg.rename, () => myCloudAction_Rename()));
        toast.appendChild(mkBtn(myCloudSvg.delete, () => myCloudAction_Delete()));

        document.body.appendChild(toast);

        // Click outside to exit mode
        grid.addEventListener('click', (e) => {
            if (e.target.closest('.myCloud-symbol-item')) return;
            toggleSymbolMultiselect(false);
        }, true);
    }

    
    // Global Drag Drop Zone
    if (!isReadOnly) {
        grid.addEventListener('dragover', (e) => { e.preventDefault(); e.dataTransfer.dropEffect = 'copy'; });
        grid.addEventListener('drop', (e) => {
            e.preventDefault();
            if (e.dataTransfer.items && e.dataTransfer.items.length > 0) {
                myCloudScanItems(e.dataTransfer.items, null);
            }
        });
    }
 
    // 3. Parent "Up" Item
    if (st.currentDir !== '/' && st.currentDir !== '') {
        const upEl = document.createElement('div');
        upEl.className = 'myCloud-symbol-item';
        upEl.dataset.fullpath = '..';
        
        // [FIX] Handle interaction based on Mode for the Back Button
        if (useDarkMode) {
             // Dark Mode: Single click -> Go Up immediately
             upEl.onclick = (e) => {
                 e.stopPropagation();
                 myCloudGoUp();
             };
        } else {
            if (isSingleClick) {
                upEl.onclick = (e) => { e.stopPropagation(); myCloudGoUp(); };
            } else {
                // Standard: Double click -> Go Up
                upEl.ondblclick = () => myCloudGoUp();
                // Single click -> Deselect everything else
                upEl.onclick = () => {
                   st.selectedFiles = [];
                   st.currentFile = null;
                   myCloudRenderUI();
                };
            }
        }

        upEl.innerHTML = 
            '<div class="ce-sym-icon-box"><span class="myCloudIcon" style="font-size:24px;">' + myCloudIconFolder + '</span></div>' +
            '<div class="ce-sym-label">..</div>';
        grid.appendChild(upEl);
    }

    const sortedList = myCloudGetSortedItems();

    sortedList.forEach(i => {
        if (i.name === '/.recycle_bin' && myCloudUserRole === 'read-only') return;

        const isDir = i.size === 'DIR';
        const isRecycleBin = (i.name === '/.recycle_bin');
        const isSelected = st.selectedFiles.includes(i.name);
        let displayName = i.displayName || (myCloudState.pathNames && myCloudState.pathNames[i.name]) || i.name.split('/').pop();
        let realFilename = displayName;
        const isEnc = i.name.endsWith('.enc') || i.isEncrypted === true || (typeof myCloudCrypto !== 'undefined' && myCloudCrypto.isDirEncrypted(i.name) && i.size === 'DIR');
        let isUnlocked = typeof myCloudCrypto !== 'undefined' ? myCloudCrypto.isDirUnlocked(myCloudCrypto.getCryptoRoot(i.name)) : false;
		if (isEnc && !i.isBrokenEncryption) {
             realFilename = displayName.replace(/\.enc$/, '');
             displayName = realFilename;
         }
        const ext = realFilename.split('.').pop().toLowerCase();
        const isZip = ext === 'zip';

		const isBack = (displayName === '..');
		const isLink = i.isLink === true;

        // 4. Create Tile
        const el = document.createElement('div');
        el.className = 'myCloud-symbol-item';
        if (isSelected) el.classList.add('selected');
        
        el.dataset.fullpath = i.name;
        
        // Add native hover tooltip (one item per row)
        if (!isBack) {
            const readableSize = isDir ? (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.folder ? myCloud_LANG.folder : 'Folder') : (typeof myCloudFormatBytes === 'function' ? myCloudFormatBytes(parseInt(i.size) || 0) : i.size);
            el.title = displayName + "\n" + (i.date || '-') + "\n" + readableSize;
        }

        // 5. Checkbox (If enabled)
		if (!isBack) {
            const cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.className = 'ce-sym-checkbox myCloudCheckbox';
            cb.checked = isSelected;
            
            if (!showCheckboxes) grid.classList.add('ce-no-checkboxes');
            
            cb.onclick = (e) => {
                e.stopPropagation();
                // [FIX] Checkbox always ensures we are in multiselect mode
                if (useDarkMode && !grid.classList.contains('multiselect-mode')) {
                    toggleSymbolMultiselect(true);
                }
                myCloudSelectRow(el, i.name, { 
                    ctrlKey: true, 
                    shiftKey: false, 
                    button: 0,
                    preventDefault: () => {}, 
                    stopPropagation: () => {} 
                });
            };
            cb.onmousedown = (e) => e.stopPropagation();
            
            el.appendChild(cb);
        }

        // 6. Icon Logic
        let iconHtml = '';
        let canThumb = false;
        
        // [FIX] Read cache synchronously from global state
        const cacheKey = i.name + '_thumb';
        const cachedUrl = (st.previewCache && st.previewCache[cacheKey]) ? st.previewCache[cacheKey] : null;

        if (isBack) {
            iconHtml = '<span class="myCloudIcon">' + myCloudIconFolder + '</span>';
        } else if (isRecycleBin) {
			 iconHtml = '<span class="myCloudIcon">' + myCloudSvg.recycle_main + '</span>';
        } else if (isDir) {
            const safeLinkIcon = typeof myCloudIconLinkFolder !== 'undefined' ? myCloudIconLinkFolder : myCloudIconFolder.replace('fill="#ffc800"', 'fill="#ff6b6b"');
            iconHtml = '<span class="myCloudIcon">' + (i.isLink ? safeLinkIcon : myCloudIconFolder) + '</span>';
        } else if (isZip) {
            const safeZipIcon = typeof myCloudIconZipFolder !== 'undefined' ? myCloudIconZipFolder : myCloudIconFolder.replace('fill="#ffc800"', 'fill="#7E57C2"');
            iconHtml = '<span class="myCloudIcon">' + safeZipIcon + '</span>';
        } else {
            const staticIcon = myCloudTypeIcons[ext] || myCloudTypeIcons._default;
            if (imageExts.includes(ext)) {
                canThumb = true;
            }
            // Default placeholder HTML
            iconHtml = '<div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; transform: scale(0.8);">' + staticIcon + '</div>';
        }

        const iconBox = document.createElement('div');
        iconBox.className = 'ce-sym-icon-box';
        iconBox.dataset.path = i.name;
        iconBox.dataset.name = displayName;

        // [FIX] Instant Rendering if Cached
        // If we have the URL, insert the IMG directly. NO observers. NO placeholdering.
        if (canThumb && cachedUrl && !isBack) {
            // Create IMG directly
            const img = document.createElement('img');
            img.src = cachedUrl;
            img.draggable = false;
            img.style.cssText = "width:100%; height:100%; object-fit:contain;";
            
            // If the cached image is dead (410/404), fallback to icon and re-queue
            img.onerror = function() {
                delete st.previewCache[cacheKey]; // Clear dead cache
                iconBox.innerHTML = iconHtml; // Revert to icon
                iconBox.classList.remove('has-thumb');
                symbolThumbObserver.observe(iconBox); // Re-activate observer
            };
            
            iconBox.appendChild(img);
            iconBox.classList.add('has-thumb');
        } else {
            // Not cached or not thumb-able
            iconBox.innerHTML = iconHtml;
            // Only observe if it is a potential thumbnail candidate
            if (canThumb && !isBack) symbolThumbObserver.observe(iconBox);
        }

        const iconWrap = document.createElement('div');
        iconWrap.style.cssText = 'position:relative; display:flex; align-items:center; justify-content:center;';
        iconWrap.appendChild(iconBox);
		
        if (isEnc || i.isBrokenEncryption) {
            const badge = document.createElement('div');
            badge.className = 'ce-sym-icon-badge';
            badge.textContent = i.isBrokenEncryption ? ' ️' : (isUnlocked ? '🔓' : '🔒');
			iconWrap.appendChild(badge);
        }
        el.appendChild(iconWrap);

		// [NEW] Tag Logic for Symbol View
        const tags = (st.tags && st.tags[st.key]) ? st.tags[st.key] : {};
        if (tags[i.name]) {
            let itemTags = tags[i.name];
            if (!Array.isArray(itemTags)) itemTags = [itemTags];
            
            const tagsContainer = document.createElement('div');
            tagsContainer.className = 'ce-tag-stack';
			tagsContainer.style.position = 'absolute';
            tagsContainer.style.top = '4px';
            tagsContainer.style.right = '4px';
            tagsContainer.style.zIndex = '5';
            
            itemTags.forEach((c, idx) => {
                const tagDot = document.createElement('span');
                tagDot.className = 'ce-tag-dot';
                tagDot.style.backgroundColor = c;
                tagDot.title = window.myCloudGetTagName(c);
				tagDot.style.zIndex = itemTags.length - idx;
                tagsContainer.appendChild(tagDot);
            });
            el.appendChild(tagsContainer);
        }

        // 7. Label
        const lbl = document.createElement('div');
        lbl.className = 'ce-sym-label';
        lbl.textContent = displayName;
        if (i.isBrokenEncryption) {
            lbl.style.color = 'var(--danger, #e81123)';
            lbl.style.fontWeight = 'bold';
            el.title = (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.broken_enc ? myCloud_LANG.broken_enc : 'Unencrypted file in Vault!') + ' - ' + displayName;
        }
        el.appendChild(lbl);
		
		

        // 8. Interactions
       
        // [FIX] Forked Behavior for Symbol Dark Mode (Gallery)

        if (useDarkMode) {
            // A. Single Click / Tap Handler
            el.onclick = (e) => {
                // If clicking the checkbox, ignore (it has its own handler)
                if (!isBack && e.target.classList.contains('ce-sym-checkbox')) return;
                
                // Special case: Back Button always navigates
                if (isBack) {
                    myCloudHandleEnter(i);
                    return;
                }

                // 1. If in Multiselect Mode -> EXIT Multiselect
                if (grid.classList.contains('multiselect-mode')) {
                    toggleSymbolMultiselect(false);
                    return;
                }

                // 2. If Normal Mode -> Navigate / Preview directly
                myCloudHandleEnter(i);
            };

            // B. Right Click -> Context Menu (Only if NOT in multiselect)
            el.oncontextmenu = (e) => {
                if (isBack) return; // No context menu on Back button
                
                if (!grid.classList.contains('multiselect-mode')) {
                     e.preventDefault(); e.stopPropagation();
                     // Select it first if not selected
                     if (!isSelected) myCloudSelectRow(el, i.name, { ctrlKey: false });
                     myCloudShowContextMenu(e, i);
                }
            };
			
            el.ondblclick = (e) => {
                if (isBack) return;
                if (!grid.classList.contains('multiselect-mode')) {
                     e.preventDefault(); e.stopPropagation();
                     if (!isSelected) myCloudSelectRow(el, i.name, { ctrlKey: false });
                     myCloudShowContextMenu(e, i);
                }
            };

            // C. Long Touch -> Context Menu (Only if NOT in multiselect)
            let touchTimer;
            el.addEventListener('touchstart', (e) => {
                // Reset flags so a subsequent short tap works correctly
                window.myCloudIsLongPress = false;
                if (typeof myCloudIsLongPress !== 'undefined') myCloudIsLongPress = false;
                
                if (isBack) return; 

                touchTimer = setTimeout(() => {
                    if (!grid.classList.contains('multiselect-mode')) {
                         // [FIX] Trigger Multiselect on Long Press
                         window.myCloudIsLongPress = true;
                         if (typeof myCloudIsLongPress !== 'undefined') myCloudIsLongPress = true;
                         toggleSymbolMultiselect(true);
						 
						 if (!myCloudState.selectedFiles.includes(i.name)) {
                             myCloudSelectRow(el, i.name, { ctrlKey: true });
                         } else {
                             // Force visual refresh just in case
                             el.classList.add('selected');
                             const cb = el.querySelector('.myCloudCheckbox');
                             if(cb) cb.checked = true;
                         }
                         
                         if (navigator.vibrate) navigator.vibrate(10);
                    }
                }, 600); 
            }, { passive: true });
            
            el.addEventListener('touchend', () => clearTimeout(touchTimer), { passive: true });
            el.addEventListener('touchmove', () => clearTimeout(touchTimer), { passive: true });

        } else {
            // Standard Behavior (Original Light Mode)
            el.onclick = (e) => {
                if (isSingleClick) {
                    // Single Click -> Action
                    myCloudHandleEnter(i);
                } else {
                    // Standard -> Select
                    myCloudSelectRow(el, i.name, e);
                }
            };
            el.oncontextmenu = (e) => {
                e.preventDefault(); e.stopPropagation();
                if (!isSelected) myCloudSelectRow(el, i.name, e);
                myCloudShowContextMenu(e, i);
            };
            el.ondblclick = (e) => {
                if (isSingleClick) {
                    // Double Click -> Select (Reverted)
                    myCloudSelectRow(el, i.name, { ctrlKey: true });
                } else {
                    // Standard -> Action
                    myCloudHandleEnter(i);
                }
            };
            
            el.addEventListener('touchstart', (e) => myCloudHandleTouchStart(e, i, el, isSingleClick), { passive: true });
            el.addEventListener('touchend', myCloudHandleTouchEnd, { passive: true });
            el.addEventListener('touchmove', myCloudHandleTouchMove, { passive: true });
        }

        // Drag & Drop
        // [FIX] Allow Drag out of Zip
        if (!isBack && (!isReadOnly || st.currentDir.includes('.zip')) && !isRecycleBin) {
            el.draggable = true;
            
            el.addEventListener('dragstart', (e) => {
                if (!st.selectedFiles.includes(i.name)) {
                    myCloudSelectRow(el, i.name, { button: 0 }); 
                }
                
                const dragImg = window.myCloudGetDragImage(st.selectedFiles.length);
                e.dataTransfer.setDragImage(dragImg, 20, 20);

                e.dataTransfer.setData('text/plain', JSON.stringify(st.selectedFiles));
                e.dataTransfer.effectAllowed = 'copyMove';
                
                if (st.selectedFiles.length === 1 && !isDir) {
				const isEncDrag = typeof myCloudCrypto !== 'undefined' && myCloudCrypto.isDirEncrypted(i.name);
				if (!isEncDrag) {
					const dName = i.name.split('/').pop();
                    const dExt = dName.split('.').pop().toLowerCase();
                    let dMime = 'application/octet-stream';
                    if(['jpg','png','gif'].includes(dExt)) dMime = 'image/'+dExt;
                    else if(dExt === 'pdf') dMime = 'application/pdf';
                    else if(dExt === 'txt') dMime = 'text/plain';
                    
                    const dUrl = window.location.origin + window.location.pathname + '?myCloud_drag=1&myCloud_key=' + encodeURIComponent(myCloudState.key) + '&file=' + encodeURIComponent(i.name);
                    e.dataTransfer.setData("DownloadURL", dMime + ":" + dName + ":" + dUrl);
                }
				}
            });

            if (isDir || isZip || ext === 'pdf') {
                el.addEventListener('dragover', (e) => {
                    e.preventDefault(); e.stopPropagation();
                    el.classList.add('drop-target'); 
                    e.dataTransfer.dropEffect = e.ctrlKey ? 'copy' : 'move';
                });
                el.addEventListener('dragleave', (e) => {
                    if (!el.contains(e.relatedTarget)) el.classList.remove('drop-target');
                });
                el.addEventListener('drop', (e) => {
                    e.preventDefault(); e.stopPropagation();
                    el.classList.remove('drop-target');
                    
                    try {
                        const textData = e.dataTransfer.getData('text/plain');
                        if (textData) {
                            const paths = JSON.parse(textData);

                            // [NEW] PDF MERGE IN SYMBOLS
                            if (ext === 'pdf') {
                                const isAllPdf = paths.every(p => p.toLowerCase().endsWith('.pdf'));
                                if (isAllPdf && !paths.includes(i.name)) {
                                    if (typeof window.myCloudShowPdfMergeDialog === 'function') {
                                        window.myCloudShowPdfMergeDialog(paths, i.name, e.shiftKey);
                                        return;
                                    }
                                }
                            }							
							
                            if (!paths.includes(i.name)) {
                                const op = e.ctrlKey ? 'copy' : 'move';
                                myCloudShowDragConfirm(op, paths, i.name, (preserve) => myCloudBatchProcess(op, paths, i.name, preserve));
                            }
                        }
                    } catch(ex){}
                    
                    if (e.dataTransfer.items && e.dataTransfer.items.length > 0) {
                        myCloudScanItems(e.dataTransfer.items, i.name);
                    }
                });
            }
        }

        grid.appendChild(el);
    });

    containerEl.appendChild(grid);
}


</script>