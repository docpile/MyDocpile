<?php
/**
 * ============================================================================
 * MODULE: Sharing User Interface
 * ============================================================================
 * Renders the frontend modals and interaction components allowing users to 
 * create and configure access permissions for shared links.
 * Features:
 * - Create/Edit/Delete Shares
 * - Password Protection & Expiration
 * - Max Download Limits
 * - Copy Link & Password Status in List
 */

?>
    <script>
    // Dynamically inject required containers using standard string concatenation
    if (!document.getElementById('cx-share-overlay')) {
        var cxHtml = '<div id="cx-share-overlay" onclick="if(event.target===this) cxHide()"></div>' +
                     '<div id="cx-dialog-overlay"></div>' +
                     '<div id="cx-toast-container"></div>';
        document.body.insertAdjacentHTML('beforeend', cxHtml);
    }
    (function() {
        window.cxSharedPaths = [];
        
        function cxAnimateClose(overlayId, modalClass) {
            const el = document.getElementById(overlayId);
            if (!el || el.style.display === 'none') return;
            const modal = el.querySelector(modalClass);
            el.classList.add('cx-overlay-closing');
            if (modal) modal.classList.add('cx-modal-closing');
            setTimeout(() => {
                el.style.display = 'none';
                el.classList.remove('cx-overlay-closing');
                if (modal) modal.classList.remove('cx-modal-closing');
                el.innerHTML = '';
            }, 680);
        }

        let isInitialized = false;
        
        var cxPoll = setInterval(function() {
            if (typeof window.myCloudRenderUI === 'function' && typeof window.myCloudRenderToolbar === 'function') {
                clearInterval(cxPoll);
                initShareModule();
            }
        }, 50);

        window.cxToast = function(msg, isSuccess=true) {
            const container = document.getElementById('cx-toast-container');
            const el = document.createElement('div');
            el.className = 'cx-toast';
            const icon = isSuccess ? '<svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>' : '<svg viewBox="0 0 24 24" style="fill:#e81123"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>';
            el.innerHTML = icon + '<span>'+msg+'</span>';
            container.appendChild(el);
            requestAnimationFrame(() => el.classList.add('show'));
            setTimeout(() => { el.classList.remove('show'); setTimeout(() => el.remove(), 300); }, 3000);
        };

        window.cxCopyLink = function(url) {
            navigator.clipboard.writeText(url);
            cxToast(myCloud_LANG.share_copied);
        };

        window.cxDialog = function(title, bodyHtml, buttonsHtml) {
            let ol = document.getElementById('cx-dialog-overlay');
            if (!ol) {
                const cxHtml = '<div id="cx-share-overlay" onclick="if(event.target===this) cxHide()"></div>' +
                               '<div id="cx-dialog-overlay"></div>' +
                               '<div id="cx-toast-container"></div>';
                document.body.insertAdjacentHTML('beforeend', cxHtml);
                ol = document.getElementById('cx-dialog-overlay');
            }
            ol.classList.remove('cx-overlay-closing');
            if (document.cookie.includes('myCloudDarkMode=1')) ol.classList.add('ce-dark-mode'); else ol.classList.remove('ce-dark-mode');
            ol.innerHTML = 
                '<div class="cx-dialog-box"><div class="cx-dialog-header">' + title + '</div><div class="cx-dialog-body">' + bodyHtml + '</div><div class="cx-dialog-footer">' + buttonsHtml + '</div></div>';
            ol.style.display = 'flex';
        };
        
        window.cxCloseDialog = function() { 
            cxAnimateClose('cx-dialog-overlay', '.cx-dialog-box');
        };
        
        window.cxAlert = function(title, msg) { cxDialog(title, msg, '<button class="cx-btn cx-btn-primary" onclick="cxCloseDialog()">OK</button>'); };
        window.cxConfirm = function(title, msg, yesCallback) {
            window._cxTempConfirm = function() { cxCloseDialog(); yesCallback(); };
            cxDialog(title, msg, '<button class="cx-btn" onclick="cxCloseDialog()">' + (myCloud_LANG.cancel || 'Cancel') + '</button><button class="cx-btn cx-btn-primary" onclick="_cxTempConfirm()">OK</button>');
        };

        function initShareModule() {
            if (isInitialized) return;
            isInitialized = true;
            if (typeof myCloudSvg === 'undefined') window.myCloudSvg = {};
            myCloudSvg.share = '<svg viewBox="0 0 24 24"><path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92 1.61 0 2.92-1.31 2.92-2.92s-1.31-2.92-2.92-2.92z"/></svg>';
            myCloudSvg.shareList = '<svg viewBox="0 0 24 24"><path d="M3 13h2v-2H3v2zm0 4h2v-2H3v2zm0-8h2V7H3v2zm4 4h14v-2H7v2zm0 4h14v-2H7v2zM7 7v2h14V7H7z"/></svg>';
            myCloudSvg.edit = '<svg viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>';
            myCloudSvg.trash = '<svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>';
            myCloudSvg.folder = '<svg viewBox="0 0 24 24"><path d="M10 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z" fill="#FFCA28"/></svg>';
            myCloudSvg.file = '<svg viewBox="0 0 24 24"><path d="M14 2H6C4.9 2 4.01 2.9 4.01 4L4 20C4 21.1 4.89 22 5.99 22H18C19.1 22 20 21.1 20 20V8L14 2ZM13 9V3.5L18.5 9H13Z" fill="#6c757d"/></svg>';
            myCloudSvg.copy = '<svg viewBox="0 0 24 24"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg>';
            myCloudSvg.lock = '<svg viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>';

            cxRefreshShareList();

            const originalRenderToolbar = window.myCloudRenderToolbar;
            window.myCloudRenderToolbar = function() {
                originalRenderToolbar.apply(this, arguments);

                if (typeof window.myCloudActionAllowed === 'function' && window.myCloudActionAllowed('share')) {
                    const tb = document.getElementById('myCloudToolbar');
                    if (tb) {
                        const isStacked = tb.querySelector('.ce-ribbon-btn') !== null;

                        if (isStacked) {
                            const svgShareRibbon = 
                            '<svg class="ce-group-svg" viewBox="0 0 70 32">' +
                                '<path class="ce-grp-icon" d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92 1.61 0 2.92-1.31 2.92-2.92s-1.31-2.92-2.92-2.92z" transform="translate(7, 6) scale(0.75)"/>' +
                                '<path class="ce-grp-icon" d="M3 13h2v-2H3v2zm0 4h2v-2H3v2zm0-8h2V7H3v2zm4 4h14v-2H7v2zm0 4h14v-2H7v2zM7 7v2h14V7H7z" transform="translate(30, 6) scale(0.75)"/>' +
                                '<path class="ce-grp-arrow" d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z" transform="translate(52, 6) scale(0.7)"/>' +
                            '</svg>';

                            const subActions = [
                                { label: myCloud_LANG.share_item, icon: myCloudSvg.share, act: 'share', show: true },
                                { label: myCloud_LANG.share_all, icon: myCloudSvg.shareList, act: 'share-list', show: true }
                            ];

                            const createRibbon = (label, visualSvg, tooltip) => {
                                const btn = document.createElement('button');
                                btn.id = 'cxShareRibbon';
                                btn.className = 'ce-ribbon-btn';
                                btn.dataset.cx = 'share-ribbon';
                                btn.title = tooltip;
                                btn.innerHTML = visualSvg + '<span class="ce-ribbon-label">' + label + '</span>';
                                btn.dataset.children = JSON.stringify(['share', 'share-list']);

                                const createBtn = (act) => {
                                    const item = subActions.find(s => s.act === act);
                                    const b = document.createElement('button');
                                    
                                    let currentLabel = item.label;
                                    if (act === 'share') {
                                        const sel = myCloudState.selectedFiles;
                                        if (sel.length === 1 && window.cxSharedPaths.includes(sel[0])) {
                                            currentLabel = myCloud_LANG.share_edit;
                                        }
                                    }
                                    
                                    b.innerHTML = '<span class="myCloudIcon">' + item.icon + '</span><span>' + currentLabel + '</span>';
                                    
                                    if (act === 'share') {
                                        if (typeof myCloudState !== 'undefined' && myCloudState.selectedFiles.length !== 1) {
                                            b.disabled = true;
                                        }
                                    }

                                    b.onclick = (e) => {
                                        e.stopPropagation();
                                        if (act === 'share') myCloudAction_Share();
                                        if (act === 'share-list') window.cxShowAllShares();
                                        if (typeof myCloudCloseFloatingMenu === 'function') myCloudCloseFloatingMenu();
                                    };
                                    return b;
                                };

                                btn.onmouseenter = () => {
                                    if (btn.disabled) return;
                                    if (window.myCloudMenuTimer) clearTimeout(window.myCloudMenuTimer);
                                    const existing = document.getElementById('myCloudFloatingMenu');
                                    if (existing && existing.dataset.owner === btn.innerHTML && existing.dataset.pinned === 'true') return;
                                    if (typeof myCloudShowFloatingMenu === 'function') myCloudShowFloatingMenu(btn, ['share', 'share-list'], createBtn, false);
                                };

                                btn.onmouseleave = () => {
                                    const m = document.getElementById('myCloudFloatingMenu');
                                    if (m && m.dataset.pinned === 'true') return;
                                    if (typeof myCloudCloseFloatingMenu === 'function') window.myCloudMenuTimer = setTimeout(() => { myCloudCloseFloatingMenu(); }, 300);
                                };
                                
                                btn.onclick = (e) => {
                                    e.stopPropagation();
                                    if (btn.disabled) return;
                                    if (window.myCloudMenuTimer) clearTimeout(window.myCloudMenuTimer);
                                    const existing = document.getElementById('myCloudFloatingMenu');
                                    const isMyMenu = existing && existing.dataset.owner === btn.innerHTML;
                                    if (isMyMenu) {
                                        if (existing.dataset.pinned === 'true' && typeof myCloudCloseFloatingMenu === 'function') myCloudCloseFloatingMenu();
                                        else existing.dataset.pinned = 'true';
                                    } else {
                                        if (typeof myCloudShowFloatingMenu === 'function') myCloudShowFloatingMenu(btn, ['share', 'share-list'], createBtn, true);
                                    }
                                };
                                return btn;
                            };

                            const shareRibbon = createRibbon(myCloud_LANG.share_btn, svgShareRibbon, myCloud_LANG.share_manage);
                            const settingsBtn = document.getElementById('ceSettingsBtn');
                            
                            if (settingsBtn) {
                                const divider = settingsBtn.previousElementSibling; 
                                if (divider) {
                                    tb.insertBefore(shareRibbon, divider);
                                } else {
                                    tb.insertBefore(shareRibbon, settingsBtn);
                                }
                            } else {
                                tb.appendChild(shareRibbon);
                            }

                        } else {
                            const uploadBtn = tb.querySelector('button[data-action="upload"]');
                            const insertPoint = uploadBtn ? uploadBtn.nextSibling : null;

                            const sep = document.createElement('div'); sep.className = 'myCloudDivider'; sep.dataset.cx = 'share';
                            const btn = document.createElement('button');
                            btn.dataset.act = 'share'; btn.dataset.action = 'share'; btn.type = "button";
                            btn.innerHTML = '<span class="myCloudIcon" style="display:flex;align-items:center;justify-content:center;width:24px;height:24px;font-size:24px;">' + myCloudSvg.share + '</span><span style="font-size:10px;margin-top:4px;">' + myCloud_LANG.share_btn + '</span>';
                            btn.onclick = (e) => { e.preventDefault(); e.stopPropagation(); myCloudAction_Share(); return false; };
                            btn.disabled = true; btn.style.opacity = '0.5';

                            const btnList = document.createElement('button');
                            btnList.dataset.act = 'share-list'; btnList.type = "button";
                            btnList.innerHTML = '<span class="myCloudIcon" style="display:flex;align-items:center;justify-content:center;width:24px;height:24px;font-size:24px;">' + myCloudSvg.shareList + '</span><span style="font-size:10px;margin-top:4px;">' + myCloud_LANG.share_all + '</span>';
                            btnList.onclick = (e) => { e.preventDefault(); e.stopPropagation(); window.cxShowAllShares(); return false; };

                            if (insertPoint) {
                                tb.insertBefore(sep, insertPoint);
                                tb.insertBefore(btn, sep.nextSibling);
                                tb.insertBefore(btnList, btn.nextSibling);
                            } else {
                                tb.appendChild(sep); tb.appendChild(btn); tb.appendChild(btnList);
                            }
                        }
                        
                        if (typeof window.myCloudUpdateToolbarState === 'function') window.myCloudUpdateToolbarState();
                    }
                }
            };

            const oldRen = window.myCloudRenderUI;
            window.myCloudRenderUI = function() {
                oldRen.apply(this, arguments);
                if (window.cxSharedPaths.length > 0) {
                    document.querySelectorAll('.myCloudRow').forEach(row => {
                        if (window.cxSharedPaths.includes(row.dataset.fullpath)) {
                            row.classList.add('cx-shared-file');
                        }
                    });
                }
            };

            const oldUpdateState = window.myCloudUpdateToolbarState;
            window.myCloudUpdateToolbarState = function() {
                if (oldUpdateState) oldUpdateState.apply(this, arguments);
                if (typeof window.myCloudActionAllowed === 'function' && window.myCloudActionAllowed('share')) {
                    const sel = myCloudState.selectedFiles;
                    const isShared = (sel.length === 1 && window.cxSharedPaths.includes(sel[0]));
                    const shareLabel = isShared ? myCloud_LANG.share_edit : myCloud_LANG.share_item;
                    const flatLabel = isShared ? myCloud_LANG.share_edit : myCloud_LANG.share_btn;

                    const btn = document.querySelector('button[data-act="share"]');
                    if (btn && typeof myCloudState !== 'undefined') {
                        const isMulti = sel.length !== 1;
                        btn.disabled = isMulti;
                        btn.style.opacity = btn.disabled ? '0.4' : '1';
                        const span = btn.querySelector('span:last-child');
                        if (span) span.textContent = flatLabel;
                    }
                    
                    const ribbon = document.getElementById('cxShareRibbon');
                    if (ribbon) {
                        ribbon.disabled = false;
                        ribbon.style.opacity = '1';
                        ribbon.classList.remove('ce-force-active'); 
                        
                        const openMenu = document.getElementById('myCloudFloatingMenu');
                        if (openMenu) {
                            const shareItemBtn = Array.from(openMenu.querySelectorAll('button')).find(b => b.innerHTML.includes(myCloudSvg.share));
                            if (shareItemBtn) {
                                if (sel.length !== 1) {
                                    shareItemBtn.disabled = true;
                                } else {
                                    shareItemBtn.disabled = false;
                                }
                                const span = shareItemBtn.querySelector('span:last-child');
                                if (span) span.textContent = shareLabel;
                            }
                        }
                    }
                }
            };

            if (window.myCloudShowContextMenu) {
                const originalShowCtx = window.myCloudShowContextMenu;
                window.myCloudShowContextMenu = function(e, item, isTree = false) {
                    originalShowCtx(e, item, isTree);
                    if (isTree) return;
                    const menu = document.querySelector('.myCloudContextMenu');
                    if (!menu || myCloudState.selectedFiles.length > 1 || !window.myCloudActionAllowed('share')) return;
                    
                    const div = document.createElement('div');
                    div.className = 'myCloudContextItem';
                    div.innerHTML = '<span class="myCloudIcon" style="width:20px; height:20px; margin-right:12px; font-size:18px; display:inline-flex; align-items:center; justify-content:center;">' + myCloudSvg.share + '</span> ' + myCloud_LANG.share_btn;
                    div.onclick = function(ev) { ev.preventDefault(); ev.stopPropagation(); menu.remove(); myCloudAction_Share(item.name); };
                    
                    if (menu.lastChild) menu.insertBefore(div, menu.lastChild); else menu.appendChild(div);
                };
            }
            if (typeof myCloudRenderToolbar === 'function') myCloudRenderToolbar();
        }
        
        window.cxRefreshShareList = function() {
            const fd = new URLSearchParams({ myCloud_action: 'share-list', myCloud_key: myCloudState.key, myCloud_token: window.myCloudCsrfToken });
            fetch('', { method: 'POST', body: fd })
                .then(typeof myCloudCheckResponse === 'function' ? myCloudCheckResponse : r => r.json())
                .then(resp => {
                    if(resp && resp.status === 'OK') {
                        window.cxSharedPaths = resp.data.map(s => s.path);
                        if (typeof myCloudRenderUI === 'function') myCloudRenderUI();
                    }
                }).catch(()=>{});
        };
        
        window.myCloudAction_Share = function(explicitPath) {
            let fullPath = explicitPath;
            if (!fullPath && typeof myCloudState !== 'undefined' && myCloudState.selectedFiles.length === 1) fullPath = myCloudState.selectedFiles[0];
            if (!fullPath) return;
            fullPath = fullPath.replace(/\\/g, '/');
            if(!fullPath.startsWith('/')) fullPath = '/' + fullPath;
            const name = fullPath.split('/').pop();

            const fd = new URLSearchParams({ myCloud_action: 'share-list', myCloud_key: myCloudState.key, myCloud_token: window.myCloudCsrfToken, check_path: fullPath });
            if (typeof myCloudShowLoading === 'function') myCloudShowLoading();
            
            fetch('', { method: 'POST', body: fd })
                .then(typeof myCloudCheckResponse === 'function' ? myCloudCheckResponse : r => r.json())
                .then(resp => {
                    if (typeof myCloudHideLoading === 'function') myCloudHideLoading();
                    if (!resp || typeof resp !== 'object' || resp.status !== 'OK') { cxAlert(myCloud_LANG.error_lbl || 'Error', "Server error."); return; }
                    if(resp.data) window.cxSharedPaths = resp.data.map(s => s.path);
                    
                    let existing = null;
                    const isDir = !!resp.target_is_dir;
                    if(resp.data && resp.data.length) existing = resp.data.find(s => s.path === fullPath);
                    
                    const link = (typeof cloud_share_url !== 'undefined' ? cloud_share_url : (location.protocol + '//' + location.host + location.pathname)) + '?cloudshare=' + (existing ? existing.guid : '');
                    if (existing) {
                        cxRenderManage(fullPath, link, existing.guid, existing.expires, existing.has_pass, existing.permission || 'read', isDir, existing.max_downloads || 0, false, existing.alias || '');
                    } else {
                        cxRenderManage(fullPath, '', null, 'Never', false, 'read', isDir, 0, false, '');
                    }
                }).catch((err) => { 
                    console.error("Share Fetch Error:", err);
                    if (typeof myCloudHideLoading === 'function') myCloudHideLoading(); 
                });
        };
        
        window.cxShowAllShares = function() {
            const fd = new URLSearchParams({ myCloud_action: 'share-list', myCloud_key: myCloudState.key, myCloud_token: window.myCloudCsrfToken });
            if (typeof myCloudShowLoading === 'function') myCloudShowLoading();
            
            fetch('', { method: 'POST', body: fd })
                .then(typeof myCloudCheckResponse === 'function' ? myCloudCheckResponse : r => r.json())
                .then(resp => {
                    if (typeof myCloudHideLoading === 'function') myCloudHideLoading();
                    if(!resp || resp.status !== 'OK') { cxAlert(myCloud_LANG.error_lbl || 'Error', (resp && resp.msg) ? resp.msg : 'Error loading shares'); return; }
                    
                    let rows = '';
                    const baseLink = (typeof cloud_share_url !== 'undefined' ? cloud_share_url : (location.protocol + '//' + location.host + location.pathname)) + '?cloudshare=';
                    resp.data.sort((a,b) => a.path.localeCompare(b.path));
                    
                    resp.data.forEach(s => {
                        const fullLink = baseLink + s.guid;
                        const typeIcon = s.is_dir ? myCloudSvg.folder : myCloudSvg.file;
                        
                        let rightsBadge = 'cx-badge-read';
                        if (s.permission === 'modify') rightsBadge = 'cx-badge-modify';
                        if (s.permission === 'upload') rightsBadge = 'cx-badge-upload';
                        
                        const downStr = (s.max_downloads > 0) ? (s.downloads + ' / ' + s.max_downloads) : (s.downloads + ' / ∞');
                        const permLabel = myCloud_LANG['share_perm_' + s.permission] || s.permission;
                        const displayExp = (s.expires === 'Never') ? (myCloud_LANG.share_exp_never || 'Never') : safe(s.expires);

                        rows += 
                            '<tr class="cx-share-row" data-search="' + safe(s.path.toLowerCase()) + ' ' + safe(s.name.toLowerCase()) + '">' +
                            '<td>' +
                                '<div style="display:flex; align-items:center; gap:8px;">' +
                                    '<div style="width:20px; height:20px;">' + typeIcon + '</div>' +
                                    '<div style="font-weight:500;">' + safe(s.path) + '</div>' +
                                '</div>' +
                            '</td>' +
                            '<td style="text-align:center;" class="' + (s.expires === 'Never' ? 'cx-color-green' : 'cx-color-muted') + '">' + displayExp + '</td>' +
                            '<td style="text-align:center;" class="' + ((s.max_downloads > 0 && s.downloads >= s.max_downloads) ? 'cx-color-red' : 'cx-color-muted') + '">' + downStr + '</td>' +
                            '<td><span class="cx-badge ' + rightsBadge + '">' + safe(permLabel) + '</span></td>' +
                            '<td style="text-align:center">' + (s.has_pass ? '<span class="cx-color-muted" style="display:inline-flex; align-items:center;" title="' + (myCloud_LANG.share_password || 'Password') + '"><div style="width:16px; height:16px;">' + myCloudSvg.lock + '</div></span>' : '<span style="color:var(--border-strong, #ddd); font-size:16px;">&minus;</span>') + '</td>' +
                            '<td style="text-align:right; white-space:nowrap;">' +
                                 '<button class="cx-action-btn" style="display:inline-flex; margin-right:10px; vertical-align:middle; width: 20px; height: 20px; padding-top: none; margin-top: -11px !important;  " title="' + (myCloud_LANG.share_link_standard || 'Link') + '" onclick="cxCopyLink(\'' + safe(fullLink) + '\')">' + myCloudSvg.copy + '</button>' +
                                 '<button class="cx-list-icon-btn cx-edit-btn" title="Edit" onclick="cxRenderManage(\'' + safe(s.name) + '\', \'' + safe(fullLink) + '\', \'' + safe(s.guid) + '\', \'' + safe(s.expires) + '\', ' + s.has_pass + ', \'' + safe(s.permission) + '\', ' + (s.is_dir ? 'true' : 'false') + ', ' + s.max_downloads + ', true, \'' + safe(s.alias||'') + '\')">' + myCloudSvg.edit + '</button>' +
                                 '<button class="cx-list-icon-btn cx-del-btn" title="Delete" onclick="cxDelete(\'' + safe(s.guid) + '\', true)">' + myCloudSvg.trash + '</button>' +
                            '</td>' +
                        '</tr>';
                    });

                    if (resp.data.length === 0) rows = '<tr><td colspan="6" style="text-align:center; padding:20px; color:var(--text-secondary);">'+ (myCloud_LANG.share_none || 'No active shares') +'</td></tr>';

                    cxShow(
                        '<div class="cx-share-modal" style="max-width:800px; width:90%; height:80vh;">' +
                        '<div class="myCloudModalHeader" style="justify-content:space-between; align-items:center;">' +
                            '<span><b>' + myCloudSvgLogo + '</b> <span style="font-weight:100;">- ' + (myCloud_LANG.share_all || 'Manage Shares') + '</span></span>' +
                            '<button onclick="cxHide()" style="background:transparent; border:none; font-size:20px; cursor:pointer; color:inherit; line-height:1;">✕</button>' +
                        '</div>' +
                       '<div class="cx-search-wrapper">' +
                            '<input type="text" id="cxSearchInput" class="cx-search-input" placeholder="' + (myCloud_LANG.share_search_ph || 'Search...') + '" onkeyup="cxFilterShares(this.value)">' +
                        '</div>' +
                        '<div class="cx-share-body" style="padding:0; overflow:auto; flex:1;">' +
                            '<table class="cx-list-table">' +
                                '<thead>' +
                                    '<tr>' +
                                        '<th></th>' +
                                        '<th style="text-align:center;">' + (myCloud_LANG.share_expiration || 'Expires') + '</th>' +
                                        '<th style="text-align:center; ">Downloads</th>' +
                                        '<th> </th>' +
                                        '<th style="text-align:center"></th>' +
                                        '<th style="text-align:right"> </th>' +
                                    '</tr>' +
                                '</thead>' +
                                '<tbody>' + rows + '</tbody>' +
                            '</table>' +
                        '</div>' +
                    '</div>');
                }).catch((err) => { 
                    console.error("Share-List Fetch Error:", err);
                    if (typeof myCloudHideLoading === 'function') myCloudHideLoading(); 
                });
        };             
        
        window.cxFilterShares = function(query) {
            const term = query.toLowerCase();
            const rows = document.querySelectorAll('.cx-share-row');
            rows.forEach(r => {
                const txt = r.dataset.search;
                r.style.display = txt.includes(term) ? '' : 'none';
            });
        };

        function safe(s) {
            return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }
        
        window.cxCopy = function(id) {
            var el = document.getElementById(id);
            el.select();
            navigator.clipboard.writeText(el.value);
            cxToast(myCloud_LANG.share_copied);
        };
        
        window.cxSetLoading = function(btn, isLoading) {
            if(!btn) return;
            if(isLoading) {
                btn.disabled = true;
                btn.dataset.orig = btn.innerHTML;
                btn.innerHTML = '<span class="cx-spinner"></span> ' + (btn.dataset.loadingText || myCloud_LANG.share_saving);
            } else {
                btn.disabled = false;
                btn.innerHTML = btn.dataset.orig;
            }
        };

        window.cxRenderManage = function(name, link, guid = null, currentExpires = 'Never', hasPassword = false, currentPermission = 'read', isDir = false, maxDownloads = 0, fromList = false, customName = '') {
            const isNew = !guid;
            const directLink = link ? link + (link.includes('?') ? '&' : '?') + 'direct=1' : '';
            const today = new Date().toISOString().split('T')[0];
            const displayName = name.split('/').pop() || name;
            let selectedDays = '0';
            let customDate = '';
            let customVisible = 'none';
            if (currentExpires !== 'Never') {
                const expDate = new Date(currentExpires);
                if (!isNaN(expDate)) {
                    const daysDiff = Math.round((expDate - new Date()) / 86400000);
                    if (daysDiff > 0 && [1,7,30].includes(daysDiff)) {
                        selectedDays = daysDiff.toString();
                    } else {
                        selectedDays = 'custom';
                        customDate = currentExpires;
                        customVisible = 'block';
                    }
                }
            }
            const fromListInput = '<input type="hidden" id="cxFromList" value="' + (fromList ? '1' : '0') + '">';
            const disablePerm = !isDir;
            const effectivePerm = disablePerm ? 'read' : currentPermission;
            const permHelp = disablePerm ? '<div style="font-size:11px;color:var(--text-secondary);margin-top:3px;">' + myCloud_LANG.share_perm_read_only + '</div>' : '';
            
            // Format current expiry for display label
            let displayExpLabel = '';
            if (currentExpires !== 'Never') {
                displayExpLabel = '<small>(' + myCloud_LANG.current_expiry + ' ' + safe(currentExpires) + ')</small>';
            }

            cxShow(
                '<div class="cx-share-modal" id="cx-share-modal">' +
                    '<div class="myCloudModalHeader" style="justify-content:space-between; align-items:center;">' +
                        '<span>' + myCloudSvgLogo + ' <span style="font-weight:100;">- ' + (isNew ? myCloud_LANG.share_btn : myCloud_LANG.sharing_title) + '</span></span>' +
                        '<button onclick="' + (fromList ? 'cxShowAllShares()' : 'cxHide()') + '" style="background:transparent; border:none; font-size:20px; cursor:pointer; color:inherit; line-height:1;">✕</button>' +
                    '</div>' +
                    '<div class="cx-share-body">' +
                        fromListInput +
                        '<div class="cx-share-group">' +
                            '<label>' + myCloud_LANG.col_name + '</label>' +
                            '<input type="text" id="cxShareName" class="cx-share-input" value="' + safe(customName) + '" placeholder="' + myCloud_LANG.share_pass_optional + '">' +
                        '</div>' +
                        (isNew ? '' : 
                            '<div class="cx-share-group">' +
                                '<label class="cx-color-blue" style="font-weight:600;">✔ ' + myCloud_LANG.share_link_standard + '</label>' +
                                '<div class="cx-share-link-box">' +
                                    '<input id="cxLink" value="' + safe(link) + '" class="cx-share-link-input" readonly onclick="this.select()">' +
                                    '<button class="cx-action-btn" title="Copy Link" onclick="cxCopy(\'cxLink\')">' + myCloudSvg.copy + '</button>' +
                                '</div>' +
                            '</div>' +
                            (isDir ? '' : 
                                '<div class="cx-share-group">' +
                                    '<label class="cx-color-blue" style="font-weight:600;">✔ ' + myCloud_LANG.share_link_direct + '</label>' +
                                    '<div class="cx-share-link-box">' +
                                        '<input id="cxLinkDirect" value="' + safe(directLink) + '" class="cx-share-link-input" readonly onclick="this.select()">' +
                                        '<button class="cx-action-btn" title="Copy Link" onclick="cxCopy(\'cxLinkDirect\')">' + myCloudSvg.copy + '</button>' +
                                    '</div>' +
                                '</div>'
                            )
                        ) +
                        '<div class="cx-share-group"><label>' + myCloud_LANG.share_expiration + ' ' + displayExpLabel + '</label>' +
                            '<select id="cxDays" class="cx-share-input" onchange="document.getElementById(\'cxCustomDate\').style.display=(this.value===\'custom\'?\'block\':\'none\')">' +
                                '<option value="0" ' + (selectedDays==='0'?'selected':'') + '>' + myCloud_LANG.share_exp_never + '</option>' +
                                '<option value="1" ' + (selectedDays==='1'?'selected':'') + '>' + myCloud_LANG.share_exp_1_day + '</option>' +
                                '<option value="7" ' + (selectedDays==='7'?'selected':'') + '>' + myCloud_LANG.share_exp_7_days + '</option>' +
                                '<option value="30" ' + (selectedDays==='30'?'selected':'') + '>' + myCloud_LANG.share_exp_30_days + '</option>' +
                                '<option value="custom" ' + (selectedDays==='custom'?'selected':'') + '>' + myCloud_LANG.share_exp_custom + '</option>' +
                            '</select>' +
                            '<input type="date" id="cxCustomDate" class="cx-share-input" style="display:' + customVisible + ';margin-top:5px;" min="' + today + '" value="' + customDate + '">' +
                        '</div>' +
                    
                        '<div class="cx-share-group">' +
                            '<label>' + myCloud_LANG.share_max_downloads + '</label>' +
                            '<input type="number" id="cxMaxDL" class="cx-share-input" min="0" value="' + maxDownloads + '" placeholder="0 for unlimited">' +
                        '</div>' +

                        '<div class="cx-share-group">' +
                            '<label>' + myCloud_LANG.share_perm + '</label>' +
                            '<select id="cxPermission" class="cx-share-input" ' + (disablePerm ? 'disabled' : '') + '>' +
                                '<option value="read" ' + (effectivePerm === 'read' ? 'selected' : '') + '>' + myCloud_LANG.share_perm_read + '</option>' +
                                '<option value="upload" ' + (effectivePerm === 'upload' ? 'selected' : '') + '>' + myCloud_LANG.share_perm_upload + '</option>' +
                                '<option value="modify" ' + (effectivePerm === 'modify' ? 'selected' : '') + '>' + myCloud_LANG.share_perm_modify + '</option>' +
                            '</select>' +
                            permHelp +
                        '</div>' +
                        '<div class="cx-share-group">' +
                            '<label>' + myCloud_LANG.share_password + ' ' + (hasPassword ? '<span class="cx-color-red">(' + myCloud_LANG.share_pass_protected + ')</span>' : '') + '</label>' +
                            '<input type="text" id="cxPass" class="cx-share-input" ' +
                               'placeholder="' + (hasPassword ? myCloud_LANG.share_pass_placeholder : myCloud_LANG.share_pass_optional) + '"' +
                               'title="' + (hasPassword ? myCloud_LANG.share_pass_help : myCloud_LANG.share_pass_help_opt) + '">' +
                            '<input type="hidden" id="cxHasExistingPass" value="' + (hasPassword ? '1' : '0') + '">' +
                        '</div>' +
                    '</div>' +
                    '<div class="cx-share-footer" style="justify-content:space-between; flex-wrap:wrap; gap:10px;">' +
                        (isNew ? '<div></div>' : '<button type="button" class="cx-btn cx-btn-danger" onclick="cxDelete(\'' + safe(guid) + '\', ' + fromList + ')">' + (myCloud_LANG.share_stop || 'Stop Sharing') + '</button>') +
                        '<div style="display:flex; gap:10px; flex-wrap:wrap; justify-content:flex-end;">' +
                            ((typeof myCloudCloudConfig !== 'undefined' && Object.values(myCloudCloudConfig).some(c => c.interface === 'email')) ? 
                                '<button type="button" class="cx-btn" onclick="' + (isNew ? 'cxCreate(\'' + safe(name) + '\', true)' : 'cxShareViaEmail(\'' + safe(displayName) + '\', \'' + safe(link) + '\')') + '" title="' + (myCloud_LANG.share_via_email || 'Email Link') + '"><svg viewBox="0 0 24 24" width="16" height="16" style="margin-right:6px;"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z" fill="currentColor"/></svg><span class="hide-mobile">' + (myCloud_LANG.share_via_email || 'Email') + '</span></button>' : '') +
                            '<button type="button" class="cx-btn" onclick="' + (fromList ? 'cxShowAllShares()' : 'cxHide()') + '">' + (myCloud_LANG.cancel || 'Cancel') + '</button>' +
                            '<button type="button" id="cxSaveBtn" class="cx-btn cx-btn-primary" data-loading-text="' + (isNew ? (myCloud_LANG.share_creating || 'Creating...') : (myCloud_LANG.share_saving || 'Saving...')) + '" onclick="' + (isNew ? 'cxCreate(\'' + safe(name) + '\')' : 'cxSaveChanges(\'' + safe(guid) + '\')') + '">' + (isNew ? (myCloud_LANG.share_create_link || 'Create Link') : (myCloud_LANG.save || 'Save')) + '</button>' +
                        '</div>' +
                    '</div>' +
                '</div>'
            );

            const cxPerm = document.getElementById('cxPermission');
            const cxPass = document.getElementById('cxPass');
            const cxHas = document.getElementById('cxHasExistingPass').value === '1';
            function cxUpd() {
                const req = (cxPerm.value === 'modify' || cxPerm.value === 'upload');
                if (!cxHas) {
                    cxPass.placeholder = req ? myCloud_LANG.share_pass_required : myCloud_LANG.share_pass_optional;
                    cxPass.title = req ? myCloud_LANG.share_pass_required : myCloud_LANG.share_pass_help_opt;
                }
            }
            cxPerm.onchange = function() { cxUpd(); if(this.value==='modify'||this.value==='upload') cxPass.focus(); };
            cxUpd();
        };

        window.cxShareViaEmail = async function(name, link) {
            if (typeof myCloudShowEmailComposer !== 'function') {
                cxToast("Email module not loaded.", false);
                return;
            }
            
            // Wait for accounts payload if jumping from cloud-only execution natively
            if (Object.keys(myCloudEmailState.accounts).length === 0) {
                if (typeof myCloudEmailLoadAccounts === 'function') {
                    if (typeof myCloudShowLoading === 'function') myCloudShowLoading();
                    await myCloudEmailLoadAccounts();
                    if (typeof myCloudHideLoading === 'function') myCloudHideLoading();
                }
            }
            
            // Abort if the user hasn't successfully configured an email identity yet
            if (Object.keys(myCloudEmailState.accounts).length === 0) {
                cxAlert(myCloud_LANG.error_lbl || 'Error', myCloud_LANG.no_accs || 'No email accounts configured.');
                return;
            }

            const L = typeof myCloud_LANG !== 'undefined' ? myCloud_LANG : {};
            const subject = (L.share_email_subj || 'Shared Link: %s').replace('%s', name);
            const body = '<div style="font-family: sans-serif; font-size: 14px; color: var(--text-primary);">' +
                            '<p>' + (L.share_email_body || 'I would like to share the following with you:') + '</p>' +
                            '<div style="padding: 12px 16px; background: var(--gray-05); border-left: 3px solid var(--accent-primary); border-radius: 0 4px 4px 0; margin: 15px 0;">' +
                                '<b style="font-size: 15px;">' + myCloudEscapeHtml(name) + '</b><br><br>' +
                                '<a href="' + link + '" style="color: var(--accent-primary); text-decoration: none; word-break: break-all;">' + link + '</a>' +
                            '</div>' +
                         '</div>';
            
            cxHide();
            myCloudShowEmailComposer({ subject: subject, prefaceText: body });
        };

        window.cxSaveChanges = function(guid) {
            const btn = document.getElementById('cxSaveBtn');
            const perm = document.getElementById('cxPermission').value;
            const pass = document.getElementById('cxPass').value.trim();
            const maxDL = document.getElementById('cxMaxDL').value;
            const hasExisting = document.getElementById('cxHasExistingPass')?.value === '1';
            const fromList = document.getElementById('cxFromList')?.value === '1';

            if ((perm === 'modify' || perm === 'upload') && !pass && !hasExisting) {
                cxAlert(myCloud_LANG.share_security_title, myCloud_LANG.share_security_msg);
                return;
            }
            
            cxSetLoading(btn, true);

            myCloudAPI('share-update', {
                guid: guid,
                days: document.getElementById('cxDays').value,
                name: document.getElementById('cxShareName').value,
                expire_date: document.getElementById('cxCustomDate').value,
                max_downloads: maxDL,
                password: pass,
                permission: perm
            }, function(res) {
                cxSetLoading(btn, false);
                if(res.status === 'OK') {
                    cxRefreshShareList();
                    cxToast(myCloud_LANG.share_updated);
                    if(fromList) {
                        cxShowAllShares();
                    } else {
                        cxHide();
                    }
                } else {
                    cxAlert(myCloud_LANG.error_lbl, res.msg || myCloud_LANG.share_update_err);
                }
            });
        };

window.cxCreate = function(path, autoEmail = false) {
            const btn = document.getElementById('cxSaveBtn');
            var temp = document.createElement("textarea");
            temp.innerHTML = path;
            var decodedPath = temp.value;
            var perm = document.getElementById('cxPermission').value;
            var pass = document.getElementById('cxPass').value.trim();
            var maxDL = document.getElementById('cxMaxDL').value;
            
            if ((perm === 'modify' || perm === 'upload') && !pass) {
                cxAlert(myCloud_LANG.share_security_title, myCloud_LANG.share_security_msg);
                return;
            }
            
            cxSetLoading(btn, true);
            
            myCloudAPI('share-create', {
                path: decodedPath,
                name: document.getElementById('cxShareName').value,
                days: document.getElementById('cxDays').value,
                expire_date: document.getElementById('cxCustomDate').value,
                max_downloads: maxDL,
                password: pass,
                permission: perm
            }, function(res) {
                cxSetLoading(btn, false);
                if(res.status === 'OK') {
                    cxRefreshShareList();
                    if (autoEmail) {
                        cxToast(myCloud_LANG.share_created || "Share link created");
                        const displayName = decodedPath.split('/').pop() || decodedPath;
                        cxShareViaEmail(displayName, res.link);
                    } else {
                        cxToast(myCloud_LANG.share_copied);
                        cxRenderManage(decodedPath.split('/').pop(), res.link, res.guid, res.expires || 'Never', !!res.password, res.permission || 'read', false, res.max_downloads);
                    }
                } else {
                    cxAlert(myCloud_LANG.error_lbl, res.msg);
                }
            });
        };
		
        
        window.cxDelete = function(g, fromList = false) {
            cxConfirm(myCloud_LANG.share_stop, myCloud_LANG.confirm_del_msg, function() {
                myCloudAPI('share-delete', {guid:g}, function(){
                    cxRefreshShareList();
                    cxToast(myCloud_LANG.share_deleted);
                    if(fromList) {
                        cxShowAllShares();
                    } else {
                        cxHide(); 
                    }
                });
            });
        };
        
        window.cxShow = function(html) { 
            let el = document.getElementById('cx-share-overlay'); 
            if (!el) {
                const cxHtml = '<div id="cx-share-overlay" onclick="if(event.target===this) cxHide()"></div>' +
                               '<div id="cx-dialog-overlay"></div>' +
                               '<div id="cx-toast-container"></div>';
                document.body.insertAdjacentHTML('beforeend', cxHtml);
                el = document.getElementById('cx-share-overlay');
                if (!el) return;
            }
            
            el.classList.remove('cx-overlay-closing');
            if (document.cookie.includes('myCloudDarkMode=1')) el.classList.add('ce-dark-mode'); else el.classList.remove('ce-dark-mode');
            
            el.innerHTML = html; 
            el.style.display = 'flex'; 
        };

        window.cxHide = function() { 
            cxAnimateClose('cx-share-overlay', '.cx-share-modal');
        };
        
        // [NEW] Global Escape Key for Share Modals
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                var dialog = document.getElementById('cx-dialog-overlay');
                var share = document.getElementById('cx-share-overlay');
                
                // Priority: Top-most dialog first
                if (dialog && dialog.style.display !== 'none' && !dialog.classList.contains('cx-overlay-closing')) {
                    window.cxCloseDialog();
                    e.preventDefault();
                    e.stopPropagation();
                    return;
                }
                
                if (share && share.style.display !== 'none' && !share.classList.contains('cx-overlay-closing')) {
                    window.cxHide();
                    e.preventDefault();
                    e.stopPropagation();
                    return;
                }
            }
            // [NEW] Enter Key for Default Action in Share Dialogs
            if (e.key === 'Enter') {
                var share = document.getElementById('cx-share-modal'); 
                // Only if focus is inside an input in the share modal
                if (share && share.contains(document.activeElement)) {
                    var primaryBtn = share.querySelector('.cx-btn-primary');
                    if (primaryBtn && !primaryBtn.disabled) {
                        e.preventDefault();
                        primaryBtn.click();
                    }
                }
            }
        });     
    })();
    </script>
