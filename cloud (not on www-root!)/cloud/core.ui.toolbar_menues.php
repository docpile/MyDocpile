<?php
/**
 * ============================================================================
 * MODULE: Context-Aware Navigation Ribbon
 * ============================================================================
 * Constructs the primary UI toolbars, drop-down menus, and action buttons, 
 * dynamically hiding or disabling elements based on user permissions and selection state.
 * NOTE: Executed exclusively by the client browser.
 * 
 * THIS IS PART OF THE PSEUDO "JS FILE" - SO THE NO PHP CODE IN THIS FILE!
 */
?><script>

if (typeof myCloudSvg !== 'undefined') {
    if (!myCloudSvg.share) myCloudSvg.share = '<svg viewBox="0 0 24 24"><path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92 1.61 0 2.92-1.31 2.92-2.92s-1.31-2.92-2.92-2.92z"/></svg>';
    if (!myCloudSvg.share_list) myCloudSvg.share_list = '<svg viewBox="0 0 24 24"><path d="M3 13h2v-2H3v2zm0 4h2v-2H3v2zm0-8h2V7H3v2zm4 4h14v-2H7v2zm0 4h14v-2H7v2zM7 7v2h14V7H7z"/></svg>';
    if (!myCloudSvg.help) myCloudSvg.help = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>';
}

function getActionStatus(action) {
    const st = myCloudState;
    let disabled = false, hidden = false, active = false;

    // 1. Determine the right for the current context (Item level overrides global role)
    let currentRight = typeof myCloudUserRole !== 'undefined' ? myCloudUserRole : 'read-only';
    if (st.selectedFiles && st.selectedFiles.length === 1) {
        const item = st.allItems.find(i => i.name === st.selectedFiles[0]);
        if (item && item.rights) currentRight = item.rights;
    }
	
	// Action Name Mapping for the Matrix
    let matrixKey = action;
    if (action === 'commander_toggle') matrixKey = 'view_commander';
    if (action === 'office_toggle') matrixKey = 'view_office';
    if (action === 'pdf_stack_menu') matrixKey = 'pdf_stack';
    if (action === 'select_all' || action === 'invert_selection' || action === 'clear_selection') matrixKey = 'selection_buttons';
    if (action === 'toggle_tree') matrixKey = 'treeview_button';
    if (action === 'view_toggle') matrixKey = 'iconview_button';
    if (action === 'change_vault_pwd' || action === 'fix_encryption') matrixKey = 'encrypt';
	if (action === 'share' || action === 'share_list') matrixKey = 'share';
	if (action === 'help') matrixKey = 'help';

    // 2. Centralized Matrix Enforcement (Instantly hides restricted buttons)
    // Exception: encrypt_dir has dynamic visibility to allow unlocking existing vaults for everyone
    if (action !== 'encrypt_dir' && !window.myCloudActionAllowed(matrixKey, currentRight)) {
        return { disabled: true, hidden: true, active: false };
    }
	
    // Secondary Check: Office View requires the Preview right
    if (action === 'office_toggle' && !window.myCloudActionAllowed('preview', currentRight)) {
        return { disabled: true, hidden: true, active: false };
    }

    // 3. Fallback for physical UI states (multi-select requirements, etc.)
    const selCount = st.selectedFiles ? st.selectedFiles.length : 0;
    const isMulti = selCount > 1;

    // [NEW] Encryption Actions Logic
    if (action === 'encrypt_dir') {
        const isEnc = st.encryptedDirs && st.selectedFiles.length === 1 && st.encryptedDirs.has(st.selectedFiles[0]);
        const canEncrypt = window.myCloudActionAllowed('encrypt', currentRight);
        
        if (!isEnc && !canEncrypt) {
            return { disabled: true, hidden: true, active: false };
        }
        return { disabled: (selCount !== 1), hidden: false, active: false };
    }

	const isCurrentDirEncrypted = typeof myCloudCrypto !== 'undefined' && myCloudCrypto.isDirEncrypted(st.currentDir);
	
    switch (action) {
        case 'share':
            if (typeof window.myCloudAction_Share !== 'function') return { disabled: true, hidden: true, active: false };
            disabled = (selCount !== 1);
            break;
        case 'share_list':
            if (typeof window.cxShowAllShares !== 'function') return { disabled: true, hidden: true, active: false };
            disabled = false;
            break;
        case 'help':
            if (typeof window.myCloudOpenHelp !== 'function') return { disabled: true, hidden: true, active: false };
            disabled = false;
            break;
		case 'search':
			disabled = isCurrentDirEncrypted;
			hidden = isCurrentDirEncrypted;
			break;
        case 'preview':
        case 'edit_file':
        case 'properties':
            disabled = (selCount !== 1);
            break;
        case 'permissions':
            disabled = (selCount !== 1) || (currentRight !== 'admin_mode');
            hidden = (currentRight !== 'admin_mode');
            break;
        case 'rename':
        case 'download':
        case 'copy':
        case 'move':
        case 'delete':
        case 'zip_copy':
            disabled = (selCount === 0);
            break;
        case 'duplicate':
            disabled = (selCount === 0);
            break;
        case 'change_vault_pwd':
            const isVault = st.encryptedDirs && st.selectedFiles.length === 1 && st.encryptedDirs.has(st.selectedFiles[0]);
            disabled = (selCount !== 1) || !isVault;
            break;
        case 'select_all':
        case 'invert_selection':
            disabled = (!st.allItems || st.allItems.length === 0);
            break;
        case 'clear_selection':
            disabled = (selCount === 0);
            break;
        case 'fix_encryption':
            const fixItem = st.selectedFiles.length === 1 ? st.allItems.find(i => i.name === st.selectedFiles[0]) : null;
            disabled = (selCount !== 1) || !fixItem || !fixItem.isBrokenEncryption;
            break;
        case 'pdf_stack_menu':
            const oExts = ['docx', 'xlsx', 'pptx', 'doc', 'xls', 'ppt', 'odt', 'ods', 'odp', 'rtf', 'csv'];
            const allStack = isMulti && st.selectedFiles.every(f => {
                const x = f.split('.').pop().toLowerCase();
                return x === 'pdf' || oExts.includes(x);
            });
            disabled = !allStack;
            active = !disabled;
            break;
        case 'print':
            const printExts = ['docx', 'xlsx', 'pptx', 'doc', 'xls', 'ppt', 'odt', 'ods', 'odp', 'rtf', 'csv', 'pdf'];
            const allPrintable = selCount > 0 && st.selectedFiles.every(f => {
                return printExts.includes(f.split('.').pop().toLowerCase());
            });
            disabled = !allPrintable;
            break;
		default:
            disabled = false;
            active = false;
            break;
    }

    return { disabled, hidden, active };
}


// Updates the visual state of all toolbar buttons.
// Applies disabled/hidden states and active highlights based on current selection.
function myCloudUpdateToolbarState() {
    // Toggle Multi-Select Class on Body to hide Hover Menus
    const selCount = myCloudState.selectedFiles.length;
    if (selCount > 1) {
        document.body.classList.add('multi-select-active');
    } else {
        document.body.classList.remove('multi-select-active');
    }

    const devKey = typeof myCloudGetCurrentDeviceKey === 'function' ? myCloudGetCurrentDeviceKey() : 'desktop';
    const config = (myCloudState.settings && myCloudState.settings[devKey]) ? myCloudState.settings[devKey] : {};
    const toolbarEl = document.getElementById('myCloudToolbar');
    const isStacked = toolbarEl && toolbarEl.classList.contains('ce-stacked-toolbar');
    const hideDisabled = config.hideDisabled === true && (!isStacked || devKey === 'phone');
	
    // Update Standard Buttons
    document.querySelectorAll('.myCloudToolbar > button[data-action]').forEach(function(btn) {
        const status = getActionStatus(btn.dataset.action);
        
        btn.disabled = status.disabled;
        btn.style.display = (status.hidden || (hideDisabled && status.disabled)) ? 'none' : 'flex';

        if (status.active) {
            btn.classList.add('ce-force-active');
        } else {
            btn.classList.remove('ce-force-active');
        }
    });
	
    // Dynamic labeling for encrypt_dir in the toolbar
    document.querySelectorAll('.myCloudToolbar > button[data-action="encrypt_dir"], .ce-floating-item[data-action="encrypt_dir"]').forEach(function(btn) {
        if (myCloudState.selectedFiles.length === 1) {
            const isUnlocked = typeof myCloudCrypto !== 'undefined' && myCloudCrypto.isDirUnlocked(myCloudState.selectedFiles[0]);
            const isEnc = myCloudState.encryptedDirs && myCloudState.encryptedDirs.has(myCloudState.selectedFiles[0]);
            const labelSpan = btn.querySelector('span:last-child');
            if (labelSpan) {
                if (isEnc && isUnlocked) {
                    labelSpan.textContent = typeof myCloud_LANG !== 'undefined' && myCloud_LANG.lock_short ? myCloud_LANG.lock_short : 'Lock Vault';
                } else if (isEnc && !isUnlocked) {
                    labelSpan.textContent = typeof myCloud_LANG !== 'undefined' && myCloud_LANG.unlock_short ? myCloud_LANG.unlock_short : 'Unlock';
                } else {
                    labelSpan.textContent = typeof myCloud_LANG !== 'undefined' && myCloud_LANG.encrypt_short ? myCloud_LANG.encrypt_short : 'Encrypt';
				}
            }
        }
    });

    // Dynamic labeling for share
    document.querySelectorAll('.myCloudToolbar button[data-action="share"], .ce-floating-item[data-action="share"], .ce-ribbon-sub-btn[data-action="share"]').forEach(function(btn) {
        if (typeof window.cxSharedPaths !== 'undefined' && myCloudState.selectedFiles.length === 1) {
            const isShared = window.cxSharedPaths.includes(myCloudState.selectedFiles[0]);
            const labelSpan = btn.classList.contains('ce-ribbon-sub-btn') ? btn.querySelector('.ce-btn-text') : btn.querySelector('span:last-child');
            if (labelSpan) {
                labelSpan.textContent = isShared ? (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.share_edit ? myCloud_LANG.share_edit : 'Edit Share') : (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.share_item ? myCloud_LANG.share_item : 'Share');
            }
        }
    });

    // Update Ribbon Parents
    document.querySelectorAll('.ce-ribbon-btn:not(#ceSettingsBtn):not(#ceFavoritesBtn):not(#btnHelp)').forEach(function(ribbonBtn) {
        const actions = ribbonBtn.dataset.children ? JSON.parse(ribbonBtn.dataset.children) : [];
        
        let visibleChildrenCount = 0;
        let enabledChildrenCount = 0;
        let anyChildActive = false; 

        actions.forEach(function(act) {
            const status = getActionStatus(act);
            
            if (!status.hidden && !(hideDisabled && status.disabled)) {
                visibleChildrenCount++;
                if (!status.disabled) {
                    enabledChildrenCount++;
                }
            }
            if (status.active) anyChildActive = true;
        });

        if (visibleChildrenCount === 0) {
            ribbonBtn.style.setProperty('display', 'none', 'important');
        } else {
            ribbonBtn.style.setProperty('display', 'flex', 'important');
        }

        ribbonBtn.disabled = (enabledChildrenCount === 0);

        if (anyChildActive) {
            ribbonBtn.classList.add('active-parent');
        } else {
            ribbonBtn.classList.remove('active-parent');
        }
    });

    // Update Floating Menu if open
    const openMenu = document.getElementById('myCloudFloatingMenu');
    if (openMenu) {
        openMenu.querySelectorAll('.ce-ribbon-sub-btn, .ce-floating-item').forEach(function(btn) {
            const action = btn.dataset.action;
            if (action) {
                const status = getActionStatus(action);
                
                btn.disabled = status.disabled;
                btn.style.display = (status.hidden || (hideDisabled && status.disabled)) ? 'none' : 'flex';

                if (status.active) {
                    btn.classList.add('ce-force-active');
                } else {
                    btn.classList.remove('ce-force-active');
                }
            }
        });
        
    }

    // Update Gallery Toggle Availability
    const hasMedia = (myCloudState.allItems || []).some(function(i) {
        const ext = i.name.split('.').pop().toLowerCase();
        return i.size !== 'DIR' && (imageExts.includes(ext) || videoExts.includes(ext));
    });
	
    // Update Commander Middle Toolbar
    if (myCloudState.isCommanderMode) {
        const toolbarContainer = document.querySelector('.myCloud-commander-resizer-container');
        if (toolbarContainer) {
            const handle = toolbarContainer.querySelector('.myCloud-commander-resizer-handle');
            toolbarContainer.innerHTML = ''; 
            if (handle) toolbarContainer.appendChild(handle);
            renderCommanderButtons(toolbarContainer);
        }
    }
	
    const viewBtns = document.querySelectorAll('button[data-action="view_toggle"], .ce-floating-item[data-action="view_toggle"]');
    viewBtns.forEach(function(btn) {
         btn.disabled = false;
         if (myCloudState.viewMode === 'symbol') btn.classList.add('ce-force-active');
         else btn.classList.remove('ce-force-active');
    });

    // Clean up adjacent or dangling separators in the flat toolbar
    const flatToolbar = document.getElementById('myCloudToolbar');
    if (flatToolbar) {
        let lastVisibleWasButton = false;
        let pendingDivider = null;
        Array.from(flatToolbar.children).forEach(child => {
            if (child.classList.contains('myCloudDivider')) {
                child.style.display = 'none'; // Hide by default
                if (lastVisibleWasButton) pendingDivider = child;
            } else if (child.style.display !== 'none') {
                if (pendingDivider) { pendingDivider.style.display = ''; pendingDivider = null; }
                lastVisibleWasButton = true;
            }
        });
    }

}


// Renders the floating ribbon menu for grouped actions.
// Handles positioning, pinning, and item creation.
// Expects `tabData` which contains the columns and rows configuration.
function myCloudShowFloatingMenu(btn, tabData, createBtnFn, pinned) {
    if (typeof pinned === 'undefined') pinned = false;
    const existing = document.getElementById('myCloudFloatingMenu');
    
    if (existing && existing.dataset.owner === btn.innerHTML) {
        if (pinned) existing.dataset.pinned = 'true';
        return; 
    }
    
    myCloudCloseFloatingMenu(true);

    const menu = document.createElement('div');
    menu.id = 'myCloudFloatingMenu';
    menu.className = 'ce-floating-menu';
    menu.dataset.owner = btn.innerHTML; 
    
    if (pinned) menu.dataset.pinned = 'true';

    menu.onmouseenter = function() {
        if (window.myCloudMenuTimer) clearTimeout(window.myCloudMenuTimer);
    };
    
    menu.onmouseleave = function() {
        if (menu.dataset.pinned === 'true') return;

        window.myCloudMenuTimer = setTimeout(function() {
            myCloudCloseFloatingMenu();
        }, 300);
    };

    const devKey = typeof myCloudGetCurrentDeviceKey === 'function' ? myCloudGetCurrentDeviceKey() : 'desktop';
    const config = (myCloudState.settings && myCloudState.settings[devKey]) ? myCloudState.settings[devKey] : {};
    const toolbarEl = document.getElementById('myCloudToolbar');
    const isStacked = toolbarEl && toolbarEl.classList.contains('ce-stacked-toolbar');
    const hideDisabled = config.hideDisabled === true && (!isStacked || devKey === 'phone');

    const container = document.createElement('div');
    container.className = 'ce-ribbon-popup-container';
    let visibleCols = 0;

    tabData.columns.forEach((col) => {
        const colDiv = document.createElement('div');
        colDiv.className = 'ce-ribbon-sub-col';

        const header = document.createElement('div');
        header.className = 'ce-ribbon-sub-header';
        header.textContent = col.label;
        colDiv.appendChild(header);

        let visibleRows = 0;
        col.rows.forEach(row => {
            const rowDiv = document.createElement('div');
            rowDiv.className = 'ce-ribbon-sub-row';
            let visibleItems = 0;

            row.forEach(itemConfig => {
                if (itemConfig.type === 'divider') {
                    const hDiv = document.createElement('div');
                    hDiv.className = 'ce-ribbon-sub-h-divider';
                    rowDiv.appendChild(hDiv);
                    visibleItems++;
                    return;
                }
                const act = itemConfig.act;
                const status = getActionStatus(act);
                if (status.hidden || (hideDisabled && status.disabled)) return;

                const itemBtn = createBtnFn(act, itemConfig.type);
                itemBtn.classList.add('ce-ribbon-sub-btn');
                itemBtn.classList.add('ce-btn-type-' + itemConfig.type);
                itemBtn.dataset.action = act;
                itemBtn.disabled = status.disabled;
                if (status.active && !status.disabled) itemBtn.classList.add('ce-force-active');

                rowDiv.appendChild(itemBtn);
                visibleItems++;
            });

            if (visibleItems > 0) {
                colDiv.appendChild(rowDiv);
                visibleRows++;
            }
        });

        if (visibleRows > 0) {
            if (visibleCols > 0) {
                const divider = document.createElement('div');
                divider.className = 'ce-ribbon-sub-divider';
                container.appendChild(divider);
            }
            container.appendChild(colDiv);
            visibleCols++;
        }
    });

    if (visibleCols === 0) return;
    menu.appendChild(container);

    const handle = document.createElement('div');
    handle.className = 'ce-ribbon-handle';
    const labelSpan = btn.querySelector('.ce-ribbon-label');
    handle.textContent = labelSpan ? labelSpan.textContent : '';
    menu.appendChild(handle);

    document.body.appendChild(menu);

    const rect = btn.getBoundingClientRect();
    const menuWidth = menu.offsetWidth;
    const windowWidth = window.innerWidth;

    let left = rect.left + (rect.width / 2) - (menuWidth / 2);

    if (left < 5) left = 5; 
    if (left + menuWidth > windowWidth - 5) {
        left = windowWidth - menuWidth - 5;
    }
    
    menu.style.top = (rect.bottom + 1) + 'px'; 
    menu.style.left = left + 'px';

    myCloudApplyTheme();

    setTimeout(function() {
        const closer = function(e) {
            const m = document.getElementById('myCloudFloatingMenu');
            if (m && !m.contains(e.target) && e.target !== btn && !btn.contains(e.target)) {
                // Ignore clicks if a modal (like an alert dialog) is currently open
                const modalOverlay = document.getElementById('myCloudModalOverlay');
                if (modalOverlay && modalOverlay.style.display !== 'none') return;
                myCloudCloseFloatingMenu();
                document.removeEventListener('click', closer);
            }
        };
        document.addEventListener('click', closer);
    }, 0);
}

// Closes any open floating menu or settings panel.
// Supports immediate removal or animated fade-out.
function myCloudCloseFloatingMenu(immediate, force) {
    if (typeof immediate === 'undefined') immediate = false;
	if (typeof force === 'undefined') force = false;

    // 1. Ribbons
    const ribbon = document.getElementById('myCloudFloatingMenu');
    if (ribbon) {
        if (immediate) ribbon.remove();
        else {
            ribbon.classList.add('closing');
            setTimeout(function() { if (ribbon.parentNode) ribbon.remove(); }, 190);
        }
    }


    // 3. Favorites Panel (CRITICAL FIX: Added this block)
    const favPanel = document.getElementById('myCloudFavoritesPanel');
    if (favPanel) {
        if (!immediate && favPanel.dataset.pinned === 'true') { /* skip */ }
        else {
            if (immediate) {
                favPanel.remove();
            } else {
                favPanel.classList.add('closing');
                setTimeout(function() { if (favPanel.parentNode) favPanel.remove(); }, 190);
            }
        }
    }
}


// Renders the main toolbar based on configuration and permissions.
// Supports both flat and stacked (ribbon) layouts.
function myCloudRenderToolbar() {
    const toolbar = document.getElementById('myCloudToolbar');
    if (!toolbar) return;

    if (toolbar.parentElement && !toolbar.parentElement.classList.contains('myCloudToolbar-wrapper')) {
        const wrapper = document.createElement('div');
        wrapper.className = 'myCloudToolbar-wrapper';
        toolbar.parentNode.insertBefore(wrapper, toolbar);
        wrapper.appendChild(toolbar);
        
        const startInd = document.createElement('div');
        startInd.className = 'toolbar-indicator-start';
        wrapper.appendChild(startInd);
        
        const endInd = document.createElement('div');
        endInd.className = 'toolbar-indicator-end';
        wrapper.appendChild(endInd);
        
        const checkScroll = () => {
            if (toolbar.scrollWidth > toolbar.clientWidth) {
                let sL = Math.abs(toolbar.scrollLeft);
                let maxS = toolbar.scrollWidth - toolbar.clientWidth;
                let isAtStart = sL <= 5;
                let isAtEnd = sL >= maxS - 5;
                startInd.style.opacity = isAtStart ? '0' : '1';
                endInd.style.opacity = isAtEnd ? '0' : '1';
            } else {
                startInd.style.opacity = '0';
                endInd.style.opacity = '0';
            }
        };
        toolbar.addEventListener('scroll', checkScroll, { passive: true });
        window.addEventListener('resize', checkScroll, { passive: true });
        toolbar.updateIndicators = checkScroll;
    }	
	
    toolbar.innerHTML = '';

    const devKey = myCloudGetCurrentDeviceKey();
    const config = myCloudState.settings ? myCloudState.settings[devKey] : myCloudDefaultSettings[devKey];
    
    toolbar.style.display = 'none';

    const svgFavoritesRibbon = 
    '<svg class="ce-group-svg" viewBox="0 0 50 32">' +
        '<path class="ce-grp-icon" d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z" transform="translate(15, 5) scale(0.9)"/>' +
     '</svg>';
    
    const svgSettingsRibbon = 
    '<svg class="ce-group-svg" viewBox="0 0 50 32">' +
        '<path class="ce-grp-icon" d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z" transform="translate(13, 5) scale(0.9)"/>' +
    '</svg>';

    const filterAllowed = (actionsArray) => actionsArray.filter(act => {
        let key = act;
        if (act === 'commander_toggle') key = 'view_commander';
        if (act === 'office_toggle') key = 'view_office';
        if (act === 'pdf_stack_menu') key = 'pdf_stack';
        if (act === 'select_all' || act === 'invert_selection' || act === 'clear_selection') key = 'selection_buttons';
        if (act === 'toggle_tree') key = 'treeview_button';
        if (act === 'view_toggle') key = 'iconview_button';
        if (act === 'change_vault_pwd' || act === 'fix_encryption') key = 'encrypt';
        
        if (act !== 'encrypt_dir' && !window.myCloudActionAllowed(key)) return false;
        if (act === 'office_toggle' && !window.myCloudActionAllowed('preview')) return false;
        
        return true;
    });

    const toolsActions    = filterAllowed(['toggle_tree', 'view_toggle', 'office_toggle', 'commander_toggle', 'search', 'refresh']);
    const editActions     = filterAllowed(['edit_file', 'newfile', 'newfolder', 'copy', 'move', 'duplicate', 'rename', 'delete', 'permissions']);
    const standardActions = filterAllowed(['preview', 'download', 'upload', 'print', 'pdf_stack_menu', 'encrypt_dir', 'change_vault_pwd', 'fix_encryption']);
    const actionActions   = filterAllowed(['preview', 'download', 'upload', 'print', 'pdf_stack_menu', 'encrypt_dir', 'change_vault_pwd', 'fix_encryption']);

    if (myCloudUserRole === 'admin_mode' && window.myCloudActionAllowed('terminal')) toolsActions.push('terminal');

    const selectionActions = filterAllowed(['select_all', 'invert_selection', 'clear_selection']);

    let totalAllowedButtons = toolsActions.length + editActions.length + selectionActions.length + standardActions.length;
    if (window.myCloudActionAllowed('fav_toggle')) totalAllowedButtons++;
    if (window.myCloudActionAllowed('settings')) totalAllowedButtons++;
    
    let isStacked = config.stackedToolbar;
    if (totalAllowedButtons < ribbonThreshold) isStacked = false;

    const translations = {
        toggle_tree: myCloud_LANG.tree_view,
        view_toggle: myCloud_LANG.view_symbol,
        office_toggle: myCloud_LANG.view_office || 'Office View',
        commander_toggle: myCloud_LANG.view_commander || 'Commander',
        terminal: myCloud_LANG.terminal || 'Terminal',
        search: myCloud_LANG.search,
        refresh: myCloud_LANG.refresh,
        newfolder: myCloud_LANG.new_folder,
        newfile: myCloud_LANG.new_file || 'New File',
        encrypt_dir: myCloud_LANG.encrypt_short || 'Encrypt',
        change_vault_pwd: myCloud_LANG.change_vault_pwd || 'Change Vault Password',
        share: myCloud_LANG.share_item || 'Share',
        share_list: myCloud_LANG.share_all || 'Manage Shares',
        fix_encryption: myCloud_LANG.fix_encryption || 'Fix Encryption',
        copy: myCloud_LANG.copy,
        move: myCloud_LANG.move,
        duplicate: myCloud_LANG.duplicate || 'Duplicate',
        rename: myCloud_LANG.rename,
        delete: myCloud_LANG.delete,
        permissions: myCloud_LANG.permissions || 'Permissions',
        select_all: myCloud_LANG.select_all,
        invert_selection: myCloud_LANG.invert_selection,
        clear_selection: myCloud_LANG.clear_selection,
        preview: myCloud_LANG.preview,
        edit_file: myCloud_LANG.edit || 'Edit',
        download: myCloud_LANG.download,
        upload: myCloud_LANG.upload, 
        print: myCloud_LANG.print || 'Print',
        pdf_stack_menu: myCloud_LANG.pdf_stack || 'Stack PDFs'
    };

    const createBtn = function(action, type = 'flat') {
        const btn = document.createElement('button');
        let label = translations[action] || action;
        
        let iconHtml = myCloudSvg[action];

        if (action === 'encrypt_dir') {
            iconHtml = '<svg viewBox="0 0 24 24" style="fill:currentColor;"><path d="M12.65 10A5.99 5.99 0 0 0 7 6c-3.31 0-6 2.69-6 6s2.69 6 6 6a5.99 5.99 0 0 0 5.65-4H17v4h4v-4h2v-4H12.65zM7 14c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2z"/></svg>';
            label = typeof myCloud_LANG !== 'undefined' && myCloud_LANG.encrypt_short ? myCloud_LANG.encrypt_short : 'Encrypt';
            if (typeof myCloudCrypto !== 'undefined' && myCloudState.selectedFiles.length === 1) {
                const path = myCloudState.selectedFiles[0];
                const root = myCloudCrypto.getCryptoRoot(path);
                if (root || (myCloudState.encryptedDirs && myCloudState.encryptedDirs.has(path))) {
                    label = myCloudCrypto.isDirUnlocked(path) ? (myCloud_LANG.lock_short || 'Lock Vault') : (myCloud_LANG.unlock_short || 'Unlock');
                }
            }
        }
		if (action === 'change_vault_pwd') iconHtml = '<svg viewBox="0 0 24 24" style="fill:currentColor;"><path d="M2 16v2c2.78 0 5.42-.94 7.6-2.58l-1.5-1.5C6.46 15.19 4.3 16 2 16zm6.83-3.66l2.12-2.12c-1.63-1.63-3.8-2.54-6.04-2.54v2c1.78 0 3.48.71 4.75 1.98l-1.5 1.5 3.33 3.33L13.83 13l-1.5-1.5c-1.15 1.15-2.6 1.83-4.16 1.83v-2c1.02 0 1.99-.44 2.66-1.16zM20 4H4c-1.1 0-2 .9-2 2v4h2V6h16v12h-8v2h8c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2z"/></svg>';
        if (action === 'fix_encryption') iconHtml = '<svg viewBox="0 0 24 24" style="fill:currentColor;"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/></svg>';

        if (type === 'icon') {
            btn.innerHTML = '<span class="myCloudIcon">' + iconHtml + '</span>';
            btn.title = label;
        } else if (type === 'flat') {
            btn.innerHTML = '<span class="myCloudIcon">' + iconHtml + '</span><span>' + label + '</span>';
            btn.title = label;
        } else {
            btn.innerHTML = '<span class="myCloudIcon">' + iconHtml + '</span><span class="ce-btn-text">' + label + '</span>';
            btn.title = label;
        }

        btn.dataset.action = action;
        
        if (action === 'toggle_tree') {
            btn.id = 'btnToggleTree';
            btn.classList.add(myCloudTreeVisible ? 'tree-on' : 'tree-off');
        }
        
        if (action === 'commander_toggle') {
             if (myCloudState.isCommanderMode) btn.classList.add('ce-force-active');
        }
        
        if (action === 'office_toggle') {
             if (myCloudState.isOfficeMode) btn.classList.add('ce-force-active');
        }
        
        if (action === 'view_toggle') {
            btn.innerHTML = '<span class="myCloudIcon">' + myCloudSvg.view + '</span><span>' + translations.view_toggle + '</span>';
            if (myCloudState.viewMode === 'symbol') btn.classList.add('ce-force-active'); 
        }
        
        btn.onclick = function(e) { 
            e.stopPropagation(); 
            myCloudHandleToolbarClick(action); 
            myCloudCloseFloatingMenu(); 
        };
        return btn;
    };

    const createRibbon = function(tabData, tooltip, customRenderer) {
        const btn = document.createElement('button');
        btn.className = 'ce-ribbon-btn'; 
        btn.title = tooltip; 
        btn.innerHTML = '<span class="ce-ribbon-label">' + tabData.label + '</span>';
        window.myCloudRibbonData = window.myCloudRibbonData || {};
        window.myCloudRibbonData[tabData.id] = tabData;
        btn.dataset.tabId = tabData.id;
        
        // Maintain backward compatibility for the state updater logic
        let flatActions = [];
        tabData.columns.forEach(c => c.rows.forEach(r => r.forEach(i => {
            if (i.act) flatActions.push(i.act);
        })));
        btn.dataset.children = JSON.stringify(flatActions);

        btn.onmouseenter = function() {
            if (btn.disabled) return;
            if (window.myCloudMenuTimer) clearTimeout(window.myCloudMenuTimer);
            
            const existing = document.getElementById('myCloudFloatingMenu');
            if (existing && existing.dataset.owner === btn.innerHTML && existing.dataset.pinned === 'true') return;
            
            myCloudShowFloatingMenu(btn, window.myCloudRibbonData[btn.dataset.tabId], customRenderer || createBtn, false);
    };

        btn.onmouseleave = function() {
            const m = document.getElementById('myCloudFloatingMenu');
            if (m && m.dataset.pinned === 'true') return;
            window.myCloudMenuTimer = setTimeout(function() { myCloudCloseFloatingMenu(); }, 300);
        };
        
        btn.onclick = function(e) {
            e.stopPropagation();
            if (btn.disabled) return;
            if (window.myCloudMenuTimer) clearTimeout(window.myCloudMenuTimer);

            const existing = document.getElementById('myCloudFloatingMenu');
            const isMyMenu = existing && existing.dataset.owner === btn.innerHTML;

            if (isMyMenu) {
                if (existing.dataset.pinned === 'true') myCloudCloseFloatingMenu();
                else existing.dataset.pinned = 'true';
            } else {
                myCloudShowFloatingMenu(btn, window.myCloudRibbonData[btn.dataset.tabId], customRenderer || createBtn, true);
            }
        };
        return btn;
    };

    // FULL HIERARCHICAL RIBBON STRUCTURE
    // FULL HIERARCHICAL RIBBON STRUCTURE
    const ribbonTabs = [
        {
            id: 'tab_home',
            label: myCloud_LANG.actions || 'Actions',
            tooltip: myCloud_LANG.ribbon_edit_tooltip || 'Actions',
            columns: [
                {
                    label: myCloud_LANG.open || 'Open',
                    rows: [
                        [{ act: 'preview', type: 'full' }], 
                        [{ act: 'edit_file', type: 'full' }],
                        [{ act: 'print', type: 'full' }], 
                        [{ type: 'divider' }],
                        [{ act: 'pdf_stack_menu', type: 'full' }]
                    ]
                },
                {
                    label: myCloud_LANG.file_actions || 'File Actions',
                    rows: [
                        [{ act: 'search', type: 'full' }],
                        [{ type: 'divider' }],
                        [{ act: 'rename', type: 'half' }, { act: 'copy', type: 'half' }],
                        [{ act: 'move', type: 'half' }, { act: 'delete', type: 'half' }],
                        [{ act: 'duplicate', type: 'full' }]
                    ]
                },
                {
                    label: myCloud_LANG.transfer || 'Transfer',
                    rows: [
                        [{ act: 'upload', type: 'full' }],
                        [{ act: 'download', type: 'full' }]
                    ]
                },
                {
                    label: myCloud_LANG.new || 'New',
                    rows: [
                        [{ act: 'newfolder', type: 'full' }], 
                        [{ act: 'newfile', type: 'full' }]
                    ]
                }
            ]
        },
        {
            id: 'tab_view',
            label: myCloud_LANG.view || 'View',
            tooltip: myCloud_LANG.ribbon_view_tooltip || 'View',
            columns: [
                {
                    label: myCloud_LANG.selection || 'Selection',
                    rows: [
                        [{ act: 'select_all', type: 'full' }],
                        [{ act: 'clear_selection', type: 'full' }], 
                        [{ act: 'invert_selection', type: 'full' }]
                    ]
                },
                {
                    label: myCloud_LANG.layout || 'Layout',
                    rows: [
                        [{ act: 'view_toggle', type: 'full' }],
                        [{ act: 'toggle_tree', type: 'full' }]
                    ]
                },
                {
                    label: myCloud_LANG.modes || 'Workspaces',
                    rows: [
                        [{ act: 'commander_toggle', type: 'full' }],
                        [{ act: 'office_toggle', type: 'full' }]
                    ]
                },
                {
                    label: myCloud_LANG.view || 'View',
                    rows: [
                        [{ act: 'refresh', type: 'full' }]
                    ]
                }
            ]
        },
        {
            id: 'tab_share',
            label: myCloud_LANG.share_btn || 'Share',
            tooltip: myCloud_LANG.share_btn || 'Share',
            columns: [
                {
                    label: myCloud_LANG.share_btn || 'Share',
                    rows: [
                        [{ act: 'share', type: 'full' }],
                        [{ act: 'share_list', type: 'full' }]
                    ]
                }
            ]
        },
        {
            id: 'tab_security',
            label: myCloud_LANG.security || 'Security',
            tooltip: myCloud_LANG.security || 'Security',
            columns: [
                {
                    label: myCloud_LANG.security || 'Security',
                    rows: [
                        [{ act: 'encrypt_dir', type: 'full' }],
                        [{ act: 'change_vault_pwd', type: 'full' }],
                        [{ act: 'fix_encryption', type: 'full' }]
                    ]
                }
            ]
        }
    ];

    // Dynamically inject Terminal for Admins as its own tab (since the old Tools tab was repurposed)
    if (typeof myCloudUserRole !== 'undefined' && myCloudUserRole === 'admin_mode' && window.myCloudActionAllowed('terminal')) {
        ribbonTabs.push({
            id: 'tab_admin',
            label: 'Admin',
            tooltip: 'Admin Tools',
            columns: [
                {
                    label: 'System',
                    rows: [[{ act: 'terminal', type: 'full' }]]
                }
            ]
        });
    }

    // Dynamically inject Terminal for Admins into the Tools tab
    if (typeof myCloudUserRole !== 'undefined' && myCloudUserRole === 'admin_mode' && window.myCloudActionAllowed('terminal')) {
        const toolsTab = ribbonTabs.find(t => t.id === 'tab_tools');
        if (toolsTab) {
            toolsTab.columns.push({
                label: 'Admin',
                rows: [[{ act: 'terminal', type: 'full' }]]
            });
        }
    }

    if (myCloudUserRole === 'admin_mode' && window.myCloudActionAllowed('terminal')) {
        const toolsTab = ribbonTabs.find(t => t.id === 'tab_tools');
        if (toolsTab) {
            toolsTab.columns.push({
                label: myCloud_LANG.admin || 'Admin',
                rows: [[{ act: 'terminal', type: 'full' }]]
            });
        }
    }


    if (isStacked) {
        toolbar.classList.add('ce-stacked-toolbar');
		ribbonTabs.forEach(tab => {
            let hasVisible = false;
            tab.columns.forEach(col => {
                col.rows.forEach(row => {
                    row.forEach(item => {
                        if (item.act) {
                            const status = getActionStatus(item.act);
                            if (!status.hidden && window.myCloudActionAllowed(item.act)) hasVisible = true;
                        }
                    });
                });
            });
            if (hasVisible) {
                toolbar.appendChild(createRibbon(tab, tab.tooltip, createBtn));
            }
        });
        
    } else {
        toolbar.classList.remove('ce-stacked-toolbar');
		let needsDivider = false;
        ribbonTabs.forEach(tab => {
            let tabHasItems = false;
            if (needsDivider) { const d = document.createElement('div'); d.className = 'myCloudDivider'; toolbar.appendChild(d); needsDivider = false; }

            tab.columns.forEach(col => {
                col.rows.forEach(row => {
                    row.forEach(item => {
                        if (item.type === 'divider') {
                            if (tabHasItems) needsDivider = true;
                            return;
                        }
                        const status = getActionStatus(item.act);
                        if (!status.hidden && window.myCloudActionAllowed(item.act)) {
                            if (needsDivider) {
                                const d = document.createElement('div');
                                d.className = 'myCloudDivider';
                                toolbar.appendChild(d);
                                needsDivider = false;
                            }
                            toolbar.appendChild(createBtn(item.act, 'flat'));
                            tabHasItems = true;
                        }
                    });
                });
            });
            if (tabHasItems) needsDivider = true;
        });
    }

    // --- FAVORITES BUTTON ---
    const btnFav = document.createElement('button');
    btnFav.id = 'ceFavoritesBtn';
    btnFav.title = myCloud_LANG.fav_title;
    if (isStacked) {
        btnFav.className = 'ce-ribbon-btn';
        btnFav.innerHTML = '<span class="myCloudIcon">' + (myCloudSvg.star_filled || myCloudSvg.star || '★') + '</span><span class="ce-ribbon-label">' + myCloud_LANG.fav_title + '</span>';
    } else {
        btnFav.className = 'ce-ribbon-btn';
        btnFav.innerHTML = svgFavoritesRibbon + '<span class="ce-ribbon-label">' + myCloud_LANG.fav_title + '</span>';
    }
    
    btnFav.onclick = function(e) {
        e.stopPropagation();
        if (window.myCloudMenuTimer) clearTimeout(window.myCloudMenuTimer);
        myCloudShowFavorites(btnFav, true);
    };
    
    btnFav.onmouseleave = function() {
       const panel = document.getElementById('myCloudFavoritesPanel');
       if (panel && panel.dataset.pinned === 'true') return;
            if (panel) {
                const activeTabBtn = panel.querySelector('.ce-tab-btn.active');
                if (activeTabBtn && activeTabBtn.dataset.tab === 'admin') return;
            }
       
       window.myCloudMenuTimer = setTimeout(() => {
           myCloudCloseFloatingMenu();
       }, 300);
    };

    window._ceTempFavBtn = null;
	if (window.myCloudActionAllowed('fav_toggle')) {
        window._ceTempFavBtn = btnFav;
    }
    
    if (window.myCloudActionAllowed('settings')) {
        const btnSet = document.createElement('button');
        btnSet.id = 'ceSettingsBtn';
        btnSet.dataset.action = 'settings';
        btnSet.title = myCloud_LANG.options;
        const svgSettingsRibbon = 
        '<svg class="ce-group-svg" viewBox="0 0 50 32">' +
            '<path class="ce-grp-icon" d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z" transform="translate(13, 5) scale(0.9)"/>' +
        '</svg>';
		if (isStacked) {
            btnSet.className = 'ce-ribbon-btn';
            btnSet.innerHTML = '<span class="myCloudIcon"><svg viewBox="0 0 24 24"><polygon points="14 2 18 6 7 17 3 17 3 13 14 2"></polygon><line x1="3" y1="22" x2="21" y2="22"></line></svg></span><span class="ce-ribbon-label">' + myCloud_LANG.options + '</span>';
        } else {
            btnSet.className = 'ce-ribbon-btn';
            btnSet.innerHTML = svgSettingsRibbon + '<span class="ce-ribbon-label">' + myCloud_LANG.options + '</span>';
        }
        
        btnSet.onclick = function(e) { 
            e.stopPropagation(); 
            if (window.myCloudMenuTimer) clearTimeout(window.myCloudMenuTimer);
            myCloudShowSettings(); 
        };
    
        window._ceTempSettingsBtn = btnSet;
    }

    // --- HELP BUTTON ---
    window._ceTempHelpBtn = null;
	if (window.myCloudActionAllowed('help') && typeof window.myCloudOpenHelp === 'function') {
        const btnHelp = document.createElement('button');
        btnHelp.id = 'btnHelp';
        btnHelp.dataset.action = 'help';
        btnHelp.title = myCloud_LANG.help_tooltip || 'Help';
if (isStacked) {
            btnHelp.className = 'ce-ribbon-btn';
            btnHelp.innerHTML = '<span class="myCloudIcon" style="width: 24px !important; height: 24px !important;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg></span><span class="ce-ribbon-label">' + (myCloud_LANG.help_btn || 'Help') + '</span>';
		} else {
            btnHelp.className = 'ce-ribbon-btn';
            btnHelp.innerHTML = '<div class="myCloudIcon" style="width:34px; height:34px; margin-bottom:-2px;"><svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2 .9-2 2H8c0-2.21 1.79-4 4-4s4 1.79 4 4c0 .88-.36 1.68-.93 2.25z"/></svg></div><span class="ce-ribbon-label">' + (myCloud_LANG.help_btn || 'Help') + '</span>';
		}
        
        btnHelp.onclick = function(e) { 
            e.stopPropagation(); 
            if (window.myCloudMenuTimer) clearTimeout(window.myCloudMenuTimer);
            window.myCloudOpenHelp(); 
        };
    
        window._ceTempHelpBtn = btnHelp;
    }

    // Append utility buttons with a flex spacer if stacked
    if (isStacked) {
        const spacer = document.createElement('div');
        spacer.style.flex = '1';
        toolbar.appendChild(spacer);
        if (window._ceTempFavBtn || window._ceTempSettingsBtn || window._ceTempHelpBtn) {
            const divEnd = document.createElement('div'); divEnd.className = 'myCloudDivider ce-stacked-divider';
            toolbar.appendChild(divEnd);
        }
    } else {
        const divEnd = document.createElement('div'); divEnd.className = 'myCloudDivider';
        toolbar.appendChild(divEnd);
    }
	if (window._ceTempFavBtn) toolbar.appendChild(window._ceTempFavBtn);
    if (window._ceTempSettingsBtn) toolbar.appendChild(window._ceTempSettingsBtn);
    if (window._ceTempHelpBtn) toolbar.appendChild(window._ceTempHelpBtn);
    delete window._ceTempFavBtn;
	delete window._ceTempSettingsBtn;
    delete window._ceTempHelpBtn;

    myCloudUpdateToolbarState();
    // Show toolbar only after building is fully complete and state is updated
    if (myCloudState.interface === 'gallery') {
        toolbar.classList.add('gallery-hidden');
        toolbar.style.display = 'none'; 
    } else {
        toolbar.classList.remove('gallery-hidden');
        toolbar.style.display = 'flex';
        toolbar.style.opacity = '1';
    }
	
    if (toolbar.updateIndicators) {
        setTimeout(toolbar.updateIndicators, 50);
	}
}



// Routes tool button clicks to specific action functions.
// Handles both toolbar and ribbon menu clicks.
function myCloudHandleToolbarClick(action) {
    let matrixKey = action;
    if (action === 'commander_toggle') matrixKey = 'view_commander';
    if (action === 'office_toggle') matrixKey = 'view_office';
    if (action === 'pdf_stack_menu') matrixKey = 'pdf_stack';
    if (action === 'select_all' || action === 'invert_selection' || action === 'clear_selection') matrixKey = 'selection_buttons';
    if (action === 'toggle_tree') matrixKey = 'treeview_button';
    if (action === 'view_toggle') matrixKey = 'iconview_button';
    if (action === 'change_vault_pwd' || action === 'fix_encryption') matrixKey = 'encrypt';
	if (action === 'share' || action === 'share_list') matrixKey = 'share';

    if (action !== 'encrypt_dir' && !window.myCloudActionAllowed(matrixKey)) return;
    if (action === 'office_toggle' && !window.myCloudActionAllowed('preview')) return;

	const st = myCloudState;
    switch (action) {
        case 'toggle_tree': myCloudToggleTree(); break;
		case 'commander_toggle': myCloudToggleCommander(); break;
		case 'office_toggle': if (typeof myCloudToggleOffice === 'function') myCloudToggleOffice(); break;
        case 'view_toggle': 
            // 1. Calculate and set new mode immediately
            const newMode = (st.viewMode === 'list') ? 'symbol' : 'list';
            st.viewMode = newMode;
 
            // 2. Persist to Server
            const fd = new URLSearchParams();
            fd.append('myCloud_action', 'save_view');
            fd.append('myCloud_key', st.key);
            fd.append('myCloud_token', myCloudCsrfToken);
            fd.append('path', st.currentDir);
            fd.append('mode', newMode);

            fetch('', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(resp => {
                if(resp.status === 'OK') {
                    // 3. Update local inheritance map with server's optimized response
                    // This ensures navigation to subfolders uses the correct inherited value
                    st.viewSettings = resp.views;
                }
            });

            myCloudRenderUI(); 
            myCloudUpdateToolbarState(); 
            break;
        case 'search': myCloudAction_Search(); break;
        case 'refresh': myCloudFetchDirectory(st.currentDir); break;
        case 'newfolder': myCloudAction_NewFolder(); break;
		case 'newfile': myCloudAction_NewFile(); break;
        case 'rename': myCloudAction_Rename(); break;
		case 'duplicate': myCloudAction_Duplicate(); break;
        case 'delete': myCloudAction_Delete(); break;
		case 'permissions': if (typeof myCloudAction_Permissions === 'function') myCloudAction_Permissions(); break;
		case 'restore': myCloudAction_Restore(); break;
        case 'copy': myCloudAction_CopyMove(false); break;
        case 'move': myCloudAction_CopyMove(true); break;
        case 'upload': myCloudTriggerUpload(); break;
		case 'select_all': myCloudAction_SelectAll(); break;
        case 'share': if (typeof window.myCloudAction_Share === 'function') window.myCloudAction_Share(); break;
        case 'share_list': if (typeof window.cxShowAllShares === 'function') window.cxShowAllShares(); break;
		case 'terminal': myCloudToggleTerminal(); break;
        case 'invert_selection': myCloudAction_InvertSelection(); break;
        case 'clear_selection': myCloudAction_ClearSelection(); break;
        case 'view': 
            st.viewMode = (st.viewMode === 'list') ? 'symbol' : 'list';
            myCloudRenderUI(); 
            break;
        case 'preview': 
            if (st.selectedFiles.length === 1) {
                const f = st.selectedFiles[0];
                myCloudDownloadFile(f, f.split('/').pop(), true);
            }
            break;
        case 'edit_file': 
            if (st.selectedFiles.length === 1) window.myCloudAction_EditFile(st.selectedFiles[0]);
        case 'print':
            if (typeof myCloudOpenOnlyOffice === 'function') {
                if (st.selectedFiles.length > 0) window.myCloudAction_Print(st.selectedFiles);
            }
            break;
		case 'pdf_stack_menu': window.myCloudAction_PdfStackMenu(); break;
        case 'download': myCloudAction_DownloadBatch(); break;
        case 'encrypt_dir': myCloudAction_EncryptPrompt(st.selectedFiles[0]); break;
        case 'change_vault_pwd': if (typeof window.myCloudAction_ChangeVaultPassword === 'function') window.myCloudAction_ChangeVaultPassword(st.selectedFiles[0]); break;
        case 'fix_encryption': 
            const fixItem = st.allItems.find(i => i.name === st.selectedFiles[0]);
            if (typeof window.myCloudAction_FixEncryption === 'function' && fixItem) {
                window.myCloudAction_FixEncryption(fixItem.name, fixItem.size === 'DIR'); 
            }
            break;
    }
}


// Shows a confirmation modal for Drag & Drop operations.
// Asks user to confirm Move vs Copy action.
async function myCloudShowDragConfirm(action, paths, targetPath, onConfirm) {
    const overlay = document.getElementById('myCloudModalOverlay');
    const modal = document.getElementById('myCloudModal');
    
    const count = paths.length;
    const isCopy = (action === 'copy');
    
    const title = isCopy ? (myCloud_LANG.confirm_copy_title || 'Copy Files') : (myCloud_LANG.confirm_move_title || 'Move Files');
    
    let targetName = targetPath;
    if (!targetName || targetName === '/') {
        targetName = myCloud_LANG.root_folder || '/ (Root)';
    } else {
        const cleanPath = targetName.endsWith('/') ? targetName.slice(0, -1) : targetName;
        targetName = cleanPath.split('/').pop();
        
        if (typeof myCloudCrypto !== 'undefined' && myCloudCrypto.isDirEncrypted(cleanPath)) {
            const root = myCloudCrypto.getCryptoRoot(cleanPath);
            if (myCloudCrypto.isDirUnlocked(root)) {
                let decName = await myCloudCrypto.decryptName(root, targetName);
                targetName = (myCloudState.pathNames && myCloudState.pathNames[cleanPath]) ? myCloudState.pathNames[cleanPath] : decName.replace(/\.enc$/, '');
                targetName = targetName.replace(/^[🔓🔒 ️]\s*/, '');
            }
        }
    }

    let sourceName = paths[0].split('/').pop();
    if (typeof myCloudCrypto !== 'undefined' && myCloudCrypto.isDirEncrypted(paths[0])) {
        const rootSource = myCloudCrypto.getCryptoRoot(paths[0]);
        if (myCloudCrypto.isDirUnlocked(rootSource)) {
            let decSource = await myCloudCrypto.decryptName(rootSource, sourceName);
            sourceName = (myCloudState.pathNames && myCloudState.pathNames[paths[0]]) ? myCloudState.pathNames[paths[0]] : decSource.replace(/\.enc$/, '');
            sourceName = sourceName.replace(/^[🔓🔒 ️]\s*/, '');
        }
    }

    let msg = isCopy ? (myCloud_LANG.drag_copy_fmt || "Copy %c items to %t") : (myCloud_LANG.drag_move_fmt || "Move %c items to %t");
    msg = msg.replace('%c', count)
             .replace('%t', '<b>' + (typeof myCloudEscapeHtml === 'function' ? myCloudEscapeHtml(targetName) : targetName) + '</b>')
             .replace('%s', '<b>' + (typeof myCloudEscapeHtml === 'function' ? myCloudEscapeHtml(sourceName) : sourceName) + '</b>');

    let adminCheckbox = '';
    if (typeof myCloudUserRole !== 'undefined' && myCloudUserRole === 'admin_mode') {
        adminCheckbox = '<label style="font-size:13px; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:6px; margin-bottom:15px; color:var(--text-primary);"><input type="checkbox" id="ceDragPreserveRightsCb" checked class="myCloudCheckbox" style="margin:0;"> ' + (myCloud_LANG.preserve_rights || 'Preserve permissions & ownership') + '</label>';
    }

    overlay.style.display = 'flex';
    modal.className = 'myCloudModal conflict'; 

    modal.innerHTML = 
        '<div class="myCloudModalHeader" style="border-bottom:none; padding-bottom:0;">' +
            title + 
        '</div>' +
        '<div class="myCloudModalBody" style="display:flex; flex-direction:column; align-items:center; padding: 0 16px 24px 16px;">' +
            '<div style="margin-bottom:15px; margin-top:15px">' +
               '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#f0ad4e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
                    '<circle cx="12" cy="12" r="10"></circle>' +
                    '<line x1="12" y1="8" x2="12" y2="12"></line>' +
                    '<line x1="12" y1="16" x2="12.01" y2="16"></line>' +
               '</svg>' +
            '</div>' +
            '<div style="font-size:14px; text-align:center; margin-bottom:20px; line-height: 1.5; width: 100%;">' +
                msg +
            '</div>' +
            adminCheckbox +
            '<div class="myCloudButtons" style="justify-content: center; gap: 10px; width: 100%; margin-top:0;">' +
                '<button id="btnDragConfirm" style="background:var(--accent-color); color:#fff; border-color:var(--accent-color); min-width:80px;">' + (myCloud_LANG.yes || 'Yes') + '</button>' +
                '<button id="btnDragCancel" style="min-width:80px;">' + (myCloud_LANG.cancel || 'Cancel') + '</button>' +
            '</div>' +
        '</div>';

    const btnYes = document.getElementById('btnDragConfirm');
    const btnNo = document.getElementById('btnDragCancel');

    if (btnYes) {
        btnYes.onclick = function() {
            overlay.style.display = 'none';
            const cb = document.getElementById('ceDragPreserveRightsCb');
            const preserve = cb ? cb.checked : true;
            if (typeof onConfirm === 'function') onConfirm(preserve);
        };
    }

    if (btnNo) {
        btnNo.onclick = function() {
            overlay.style.display = 'none';
        };
    }

    modal.setAttribute('tabindex', '-1');
    modal.style.outline = 'none';
    modal.focus();
    modal.onkeydown = function(e) {
        if (e.key === 'Escape') overlay.style.display = 'none';
        if (e.key === 'Enter') {
            e.preventDefault(); 
            if(btnYes) btnYes.click();
        }
    };
}

// Triggers the "New Folder" dialog flow.
function myCloudAction_NewFolder() {
  const header = myCloud_LANG.new_folder;
  const label = myCloud_LANG.folder_name;
  
    myCloudShowInputModal(header, label, "", async function(name) {
        let finalName = name;
        if (typeof myCloudCrypto !== 'undefined' && myCloudCrypto.isDirEncrypted(myCloudState.currentDir)) {
            if (!myCloudCrypto.isDirUnlocked(myCloudState.currentDir)) return myCloudShowAlert('Error', 'Directory is locked.');
            finalName = await myCloudCrypto.encryptName(myCloudState.currentDir, name);
        }
        myCloudAPI('mkdir', { parent: myCloudState.currentDir, name: finalName });
    });
}

// Shows a confirmation dialog for file deletion.
function myCloudShowDeleteConfirm(count, isPermanent, onConfirm) {
    const overlay = document.getElementById('myCloudModalOverlay');
    const modal = document.getElementById('myCloudModal');
	const title = isPermanent ? myCloud_LANG.confirm_perm_del : myCloud_LANG.confirm_del_title;
    
    overlay.style.display = 'flex';
    modal.className = 'myCloudModal conflict'; 
    
    modal.innerHTML = 
        '<div class="myCloudModalHeader" style="border-bottom:none; padding-bottom:0;">' + title + '</div>' +
        '<div class="myCloudModalBody" style="display:flex; flex-direction:column; align-items:center; padding: 0 16px 24px 16px;">' +
             '<div style="margin-bottom:15px; margin-top:15px">' +
               '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#e81123" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
                    '<polyline points="3 6 5 6 21 6"></polyline>' +
                    '<path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>' +
                    '<line x1="10" y1="11" x2="10" y2="17"></line>' +
                    '<line x1="14" y1="11" x2="14" y2="17"></line>' +
               '</svg>' +
            '</div>' +
            '<div style="font-size:14px; text-align:center; margin-bottom:20px; line-height: 1.5; width: 100%;">' +
                myCloud_LANG.confirm_del_msg + ' ' +
                '<b>' + count + '</b> ' + myCloud_LANG.items_lc + '?' +
            '</div>' +
            '<div class="myCloudButtons" style="justify-content: center; gap: 10px; width: 100%; margin-top:0;">' +
                '<button id="btnDeleteConfirm" style="background:#e81123; color:#fff; border-color:#e81123; min-width:80px;">' + myCloud_LANG.delete + '</button>' +
                '<button onclick="myCloudCloseModal()" style="min-width:80px;">' + myCloud_LANG.cancel + '</button>' +
            '</div>' +
        '</div>';

    document.getElementById('btnDeleteConfirm').onclick = function() {
        document.getElementById('myCloudModalOverlay').style.display = 'none';
        onConfirm();
    };

    setTimeout(function() { document.getElementById('btnDeleteConfirm').focus(); }, 50);  
    modal.setAttribute('tabindex', '-1');
    modal.onkeydown = function(e) {
        if (e.key === 'Escape') document.getElementById('myCloudModalOverlay').style.display = 'none';
    };  
}


// [NEW] Unified Multi-Print Orchestrator
window.myCloudAction_Print = async function(paths) {
    const L = typeof myCloud_LANG !== 'undefined' ? myCloud_LANG : {};
    const files = Array.isArray(paths) ? paths : [paths];
    const officeExts = ['docx', 'xlsx', 'pptx', 'doc', 'xls', 'ppt', 'odt', 'ods', 'odp', 'rtf', 'csv'];

    const printTokenUrl = async (tokenUrl, printFilename) => {
        
		let isMobile = (typeof myCloudGetCurrentDeviceKey === 'function') ? (myCloudGetCurrentDeviceKey() !== 'desktop') : true;
		
        if (isMobile) {
            try {
                // Fetch the blob ONCE to consume the temp file securely from the server
                const fResp = await fetch(tokenUrl);
                if (!fResp.ok) throw new Error("HTTP " + fResp.status);
                const blob = await fResp.blob();
                
                // Close background loading UI since the file is now securely in the phone's RAM
                if (typeof window.myCloudCloseProgressUI === 'function') window.myCloudCloseProgressUI();
                if (typeof window.myCloudHideLoading === 'function') window.myCloudHideLoading();

                // Build a "Ready" Modal to acquire a fresh, trusted User Gesture
                const overlay = document.getElementById('myCloudModalOverlay');
                const modal = document.getElementById('myCloudModal');
                
                if (overlay && modal) {
                    if (typeof window.myCloudResetModal === 'function') window.myCloudResetModal();
                    
                    // [FIX] Ensure the overlay isn't stuck in a closing animation state!
                    overlay.classList.remove('closing');
                    modal.classList.remove('closing');
                    
                    overlay.style.display = 'flex';
                    overlay.style.zIndex = '25000';
                    modal.className = 'myCloudModal';
                    
                    modal.innerHTML = 
                        '<div class="myCloudModalHeader">' + (L.print || 'Print') + '</div>' +
                        '<div class="myCloudModalBody" style="padding: 24px; text-align:center;">' +
                            '<div style="margin-bottom:20px; font-size:14px; color:var(--text-secondary);">' + 
                                (L.print_ready || 'Your document is ready to print.') + 
                            '</div>' +
                            '<button id="ceMobilePrintBtn" style="background:var(--accent-primary); color:#fff; border:none; padding:10px 24px; font-size:16px; border-radius:4px; width:100%;">' + 
                                (L.open || 'Open') + ' / ' + (L.print || 'Print') + 
                            '</button>' +
                            '<button onclick="myCloudCloseModal()" style="margin-top:10px; width:100%; background:transparent; color:var(--text-secondary); border:1px solid var(--border-default); padding:10px 24px; font-size:16px; border-radius:4px;">' + 
                                (L.cancel || 'Cancel') + 
                            '</button>' +
                        '</div>';
                    
                    document.getElementById('ceMobilePrintBtn').onclick = async () => {
                        // SUCCESS! We now have a trusted tap event to satisfy the OS security.
                        if (navigator.share) {
                            try {
                                const file = new File([blob], printFilename, { type: 'application/pdf' });
                                if (navigator.canShare && navigator.canShare({ files: [file] })) {
                                    await navigator.share({ files: [file] });
                                    if (typeof window.myCloudCloseModal === 'function') window.myCloudCloseModal();
                                    return;
                                }
                            } catch (shareErr) {
                                // If the user simply swiped away or cancelled the share sheet, stop here.
                                if (shareErr.name === 'AbortError' || (shareErr.message && shareErr.message.includes('abort'))) return; 
                            }
                        }
                        
                        // Flawless Fallback: Because this is inside an onClick, popup blockers will ignore it!
                        const blobUrl = URL.createObjectURL(blob);
                        window.open(blobUrl, '_blank');
                        setTimeout(() => URL.revokeObjectURL(blobUrl), 120000);
                        if (typeof window.myCloudCloseModal === 'function') window.myCloudCloseModal();
                    };
                } else {
                    // Absolute fallback if the modal DOM elements are missing
                    const blobUrl = URL.createObjectURL(blob);
                    window.location.href = blobUrl;
                }
            } catch (err) {
                console.error("Print fetching failed:", err);
                if (typeof window.myCloudCloseProgressUI === 'function') window.myCloudCloseProgressUI();
                if (typeof window.myCloudHideLoading === 'function') window.myCloudHideLoading();
                if (typeof window.myCloudShowAlert === 'function') {
                    window.myCloudShowAlert(L.error_prefix || "Error", L.err_load_print || "Could not load file for printing.");
                }
            }
            return;
        }
        
        // Desktop Fallback: Hidden Iframe
        if (typeof window.myCloudCloseProgressUI === 'function') window.myCloudCloseProgressUI();
        if (typeof window.myCloudHideLoading === 'function') window.myCloudHideLoading();

        const iframe = document.createElement('iframe');
        iframe.style.position = 'fixed';
        iframe.style.right = '0';
        iframe.style.bottom = '0';
        iframe.style.width = '0';
        iframe.style.height = '0';
        iframe.style.border = '0';
        iframe.src = tokenUrl;
        document.body.appendChild(iframe);
        setTimeout(() => {
            try { iframe.contentWindow.focus(); iframe.contentWindow.print(); } catch (e) { console.error('Print blocked:', e); }
            setTimeout(() => iframe.remove(), 120000);
        }, 4000); 
    };

    // SINGLE FILE FAST-PATH
    if (files.length === 1) {
        const path = files[0];
        const ext = path.split('.').pop().toLowerCase();

        if (officeExts.includes(ext)) {
            myCloudCreateProgressUI(L.print_prep || 'Preparing Print...');
            const fd = new URLSearchParams();
            fd.append('myCloud_action', 'office_convert_pdf');
            fd.append('myCloud_key', myCloudState.key);
            fd.append('myCloud_token', myCloudCsrfToken);
            fd.append('path', path);

            fetch('', { method: 'POST', body: fd }).then(r => r.json()).then(res => {
                // Wait for the inner function to close the UI 
                if (res.status === 'OK' && res.token) {
                    printTokenUrl('?myCloud_token=' + res.token, path.split('/').pop().replace(/\.[^/.]+$/, "") + ".pdf");
                } else {
                    myCloudCloseProgressUI();
                    myCloudShowAlert(L.error_prefix || 'Error', res.msg || L.err_conversion || 'Conversion failed.');
                }
            }).catch(() => { myCloudCloseProgressUI(); myCloudShowAlert(L.error_prefix || 'Error', L.network_error || 'Network Error'); });
        } else {
            myCloudShowLoading();
            const filename = path.split('/').pop();
            const fd = new URLSearchParams({ myCloud_action: 'get_download_token', myCloud_key: myCloudState.key, myCloud_token: myCloudCsrfToken, path: path, filename: filename, preview: '1' });
            fetch('', { method: 'POST', body: fd }).then(r => r.json()).then(res => {
                // Wait for the inner function to close the UI
                if (res.status === 'OK') {
                    printTokenUrl('?myCloud_token=' + res.token, filename);
                } else {
                    myCloudHideLoading();
                    myCloudShowAlert(L.error_prefix || 'Error', res.msg || L.err_load_print || 'Failed to load file for printing.');
                }
            }).catch(() => { myCloudHideLoading(); myCloudShowAlert(L.error_prefix || 'Error', L.network_error || 'Network Error'); });
        }
        return;
    }

    // MULTI-FILE BATCH ORCHESTRATION
    myCloudCreateProgressUI(L.print_prep_multi || 'Preparing Print Job...');
    
    try {
        let pdfFilesToMerge = [];
        let tempFilesToCleanup = [];

        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            const ext = file.split('.').pop().toLowerCase();
            myCloudUpdateProgressUI((i / files.length) * 60); 

            if (officeExts.includes(ext)) {
                const fd = new URLSearchParams();
                fd.append('myCloud_action', 'office_convert_temp_pdf');
                fd.append('myCloud_key', myCloudState.key);
                fd.append('myCloud_token', myCloudCsrfToken);
                fd.append('path', file);

                const res = await fetch('', { method: 'POST', body: fd }).then(r => r.json());
                if (res.status === 'OK' && res.tempPath) {
                    pdfFilesToMerge.push(res.tempPath);
                    tempFilesToCleanup.push(res.tempPath);
                } else {
                    throw new Error((L.err_convert_file || 'Failed to convert ') + file.split('/').pop());
                }
            } else if (ext === 'pdf') {
                pdfFilesToMerge.push(file);
            } else {
                throw new Error((L.err_unsupported_print || 'Unsupported file for printing: ') + file.split('/').pop());
            }
        }

        myCloudUpdateProgressUI(70);

        const parentDir = files[0].substring(0, files[0].lastIndexOf('/')) || '';
        const finalTempPdf = parentDir + '/.myCloud_temp_print_' + Math.random().toString(36).substr(2, 8) + '.pdf';

        const mergeFd = new URLSearchParams();
        mergeFd.append('myCloud_action', 'pdf_stack');
        mergeFd.append('myCloud_key', myCloudState.key);
        mergeFd.append('myCloud_token', myCloudCsrfToken);
        mergeFd.append('files', JSON.stringify(pdfFilesToMerge));
        mergeFd.append('dest', finalTempPdf);
        mergeFd.append('delete_sources', 'false');
        mergeFd.append('temp_cleanup', JSON.stringify(tempFilesToCleanup));
        mergeFd.append('is_print_job', 'true');

        const mergeRes = await fetch('', { method: 'POST', body: mergeFd }).then(r => r.json());
        if (mergeRes.status !== 'OK') throw new Error(mergeRes.msg || L.err_merge || 'Failed to merge files.');

        myCloudUpdateProgressUI(90);

        const tokenFd = new URLSearchParams();
        tokenFd.append('myCloud_action', 'get_download_token');
        tokenFd.append('myCloud_key', myCloudState.key);
        tokenFd.append('myCloud_token', myCloudCsrfToken);
        tokenFd.append('path', finalTempPdf);
        tokenFd.append('filename', 'Print_Job.pdf');
        tokenFd.append('preview', '1');

        const tokenRes = await fetch('', { method: 'POST', body: tokenFd }).then(r => r.json());

        // Leave myCloudCloseProgressUI() out of here, the inner function manages it.
        if (tokenRes.status === 'OK') {
            printTokenUrl('?myCloud_token=' + tokenRes.token, 'Print_Job.pdf');
        } else {
            myCloudCloseProgressUI();
            throw new Error(L.err_preview || 'Failed to generate print preview.');
        }

    } catch (err) {
        myCloudCloseProgressUI();
        myCloudShowAlert(L.error_prefix || 'Error', err.message);
    }
};


// [NEW] Restore Action
function myCloudAction_Restore(targetDestOverride = null) {
    const files = myCloudState.selectedFiles;
    if (files.length === 0) return;
    
    myCloudShowLoading();
    
    // Process sequentially to handle conflicts per-file
    let chain = Promise.resolve();
    let dirsToRefresh = new Set();
	let restoredFiles = [];
    
    files.forEach(file => {
        chain = chain.then(() => {
			// Helper function to allow retrying with resolution options
            function attemptRestore(resOpt, destOverride) {
                const fd = new URLSearchParams();
                fd.append('myCloud_action', 'restore');
                fd.append('myCloud_key', myCloudState.key);
                fd.append('myCloud_token', myCloudCsrfToken);
                fd.append('src', file);
                
                // Add optional override destination (for "Restore To...")
                // Use the local override if provided (recursion), else the global one
                const finalDest = destOverride || targetDestOverride;
                if (finalDest) {
                    fd.append('custom_dest', finalDest);
                }
                
                // Add resolution strategy (overwrite/keep_both) if provided
                if (resOpt) {
                    fd.append('resolution', resOpt);
                }

                return fetch('', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(res => {
                        // [NEW] Handle Missing Original Path -> Prompt for new location
                        if (res.status === 'ERR' && (res.code === 'PATH_MISSING' || res.code === 'RESTORE_FAILED')) {
                            myCloudHideLoading();
                            return new Promise(resolve => {
                                // Show alert/confirm asking to choose new location
                                myCloudShowAlert(
                                    myCloud_LANG.restore,
                                    myCloud_LANG.restore_fail_msg || "Original folder missing. Select new location?",
                                    function() {
                                        // On YES: Open Tree Selector
                                        myCloudShowTreeSelector(myCloud_LANG.select_dest, myCloud_LANG.restore, function(newDest) {
                                            myCloudShowLoading();
                                            resolve(attemptRestore(null, newDest));
                                        });
                                    }
                                );
                                // If user cancels the alert, the chain stops (or we could resolve() to skip)
                            });
                        }

                        // Handle Filename Conflicts
                        if (res.status === 'CONFLICT') {
                            myCloudHideLoading();
                            return new Promise(resolve => {
                                // Show the conflict modal
                                myCloudShowConflictModal(res.file, (userChoice) => {
                                    if (userChoice) { 
                                        myCloudShowLoading(); 
                                        // Retry operation with the user's choice
                                        resolve(attemptRestore(userChoice, finalDest)); 
                                    } else { 
                                        myCloudShowLoading(); 
                                        // User clicked Skip/Cancel
                                        resolve(); 
                                    } 
                                });
                            });
                        }
                        
                        // Handle Errors
                        if (res.status !== 'OK') {
                            throw new Error(res.msg || 'Restore Failed');
                        }
                        
                        // Success: Track destination dir to refresh it later
                        if (res.dest_dir) {
							restoredFiles.push(file);
                            dirsToRefresh.add(res.dest_dir);
                        }
                    });
            }
            
            return attemptRestore();
        });
    });
    
    // Finalize
    chain.then(() => {
        myCloudHideLoading();
		
        if (restoredFiles.length > 0) {
            myCloudSurgicalRemove(restoredFiles);
        }
        
        // 1. Refresh the current view (The Recycle Bin)
        const p1 = myCloudFetchDirectory(myCloudState.currentDir);
        
        // 2. Silently refresh all destination folders where files were restored
        const promises = [p1];
        dirsToRefresh.forEach(d => {
            promises.push(myCloudFetchDirectory(d, 2, true));
        });
        
        // 3. Update UI once all data is fresh
        Promise.all(promises).then(() => {
            myCloudRenderUI();
        });

    }).catch(err => {
        myCloudHideLoading();
        myCloudShowAlert(myCloud_LANG.error_prefix, err.message);
        myCloudFetchDirectory(myCloudState.currentDir);
    });
}


// Initiates the delete action for selected files.
// Initiates the delete action for selected files.
function myCloudAction_Delete() {
    const files = myCloudState.selectedFiles;
    if (files.length === 0) return;
    
    // Determine context
    const isRecycleBin = (myCloudState.currentDir === '/.recycle_bin');
    const settings = myCloudState.settings || {};
    // Default to true if setting is undefined
    const useRecycle = (typeof settings.enableRecycleBin !== 'undefined') ? settings.enableRecycleBin : true;
    
    // Logic: Permanent if we are IN the bin, OR if recycling is globally disabled
    const isPermanent = isRecycleBin || !useRecycle;
    
    myCloudShowDeleteConfirm(files.length, isPermanent, function() {
        myCloudShowLoading();
        let chain = Promise.resolve();
        let recycledItems = [];
        
        files.forEach(f => {
            chain = chain.then(() => {
                const fd = new URLSearchParams();
                fd.append('myCloud_action', 'delete');
                fd.append('myCloud_key', myCloudState.key);
                fd.append('myCloud_token', myCloudCsrfToken);
                fd.append('src', f);
                
                // Explicitly send flags to server
                if(isPermanent) fd.append('permanent', 'true');
                if(useRecycle && !isRecycleBin) fd.append('useRecycleBin', 'true'); // Only ask for recycle bin if we aren't IN it
                
                return fetch('', {method:'POST', body:fd})
                    .then(r => r.json())
                    .then(res => {
                        if(res.status !== 'OK') throw new Error(res.msg || 'Delete Failed');
                        if(res.recycled) recycledItems.push(res.recycled);
                    });
            });
        });
        
        chain.then(() => {
            myCloudHideLoading();
            if (recycledItems.length > 0) {
                myCloudShowUndoToast(recycledItems);
            }
            
            // Remove items visually and update selection index
            myCloudSurgicalRemove(files);
            
            // Sync server state (Silent)
            if (myCloudState.isCommanderMode) {
                 // In commander mode, SurgicalRemove handles the view update.
                 // We just need to fetch fresh data for consistency.
                 const side = myCloudState.commanderActive;
                 refreshCommanderPane(side); // This will re-snap the cursor to the correct spot
            } else {
                 const p1 = myCloudFetchDirectory(myCloudState.currentDir, 2, true);
                 const p2 = (useRecycle) ? myCloudFetchDirectory('/.recycle_bin', 2, true) : Promise.resolve();
            }
            
        }).catch(err => {
            myCloudHideLoading();
            myCloudShowAlert(myCloud_LANG.error_prefix, err.message);
            myCloudFetchDirectory(myCloudState.currentDir);
        });
    });
}


// Duplicate Action
function myCloudAction_Duplicate(path) {
    const filesToDuplicate = path ? [path] : myCloudState.selectedFiles;
    if (!filesToDuplicate || filesToDuplicate.length === 0) return;

    myCloudShowLoading();

    const processNext = async (index) => {
        if (index >= filesToDuplicate.length) {
            myCloudHideLoading();
            if (myCloudState.isCommanderMode && typeof refreshCommanderPane === 'function') {
                refreshCommanderPane(myCloudState.commanderActive);
            } else {
                myCloudFetchDirectory(myCloudState.currentDir);
            }
            return;
        }
        
        const srcPath = filesToDuplicate[index];
        const parentDir = srcPath.substring(0, srcPath.lastIndexOf('/')) || '/';
        let newNameParam = null;

        // Decrypt, enumerate, and re-encrypt for Vault items
        if (typeof myCloudCrypto !== 'undefined' && myCloudCrypto.isDirEncrypted(parentDir)) {
            if (!myCloudCrypto.isDirUnlocked(parentDir)) {
                myCloudHideLoading();
                return myCloudShowAlert('Error', 'Directory is locked.');
            }
            
            const encFilename = srcPath.split('/').pop();
            const realName = await myCloudCrypto.decryptName(parentDir, encFilename);
            
            const lastDot = realName.lastIndexOf('.');
            let base = realName;
            let ext = '';
            if (lastDot > 0) {
                base = realName.substring(0, lastDot);
                ext = realName.substring(lastDot);
            }
            
            let counter = 0;
            if (base.match(/ \(\d+\)$/)) {
                const match = base.match(/^(.*) \((\d+)\)$/);
                base = match[1];
                counter = parseInt(match[2]);
            }
            
            counter++;
            let dupRealName = `${base} (${counter})${ext}`;
            
            // Validate locally against visible items to avoid collisions
            const currentFolderItems = myCloudState.allItems.filter(i => {
                return i.name.substring(0, i.name.lastIndexOf('/') || 0) === parentDir;
            });

            while (currentFolderItems.some(i => {
                let dName = (myCloudState.pathNames && myCloudState.pathNames[i.name]) ? myCloudState.pathNames[i.name] : i.name.split('/').pop().replace(/\.enc$/, '');
                return dName === dupRealName;
            })) {
                counter++;
                dupRealName = `${base} (${counter})${ext}`;
            }
            
            newNameParam = await myCloudCrypto.encryptName(parentDir, dupRealName);
        }

        const payload = { src: srcPath };
        if (newNameParam) payload.newName = newNameParam;

        myCloudAPI('duplicate', payload, function(res) {
            processNext(index + 1);
        });
    };

    processNext(0);
}

// Initiates Copy or Move action.
// Opens tree selector to choose destination.
function myCloudAction_CopyMove(isMove) {
  const st = myCloudState;
  const files = st.selectedFiles;
  if (files.length === 0) return;
  
  const action = isMove ? 'move' : 'copy';
    const verb = isMove ? myCloud_LANG.move : myCloud_LANG.copy;
    const title = verb + " " + files.length + " " + myCloud_LANG.item_uc;

  myCloudShowTreeSelector(title, verb, function(targetDir, preserveRights) {
      if (targetDir === st.currentDir) return;
      myCloudBatchProcess(action, files, targetDir, preserveRights);
  });
}

async function myCloudAction_DownloadBatch() {
    const files = myCloudState.selectedFiles;

    if (files.length === 0) return;

    let dirHandle = null;
    try {
        // THE PROFESSIONAL BATCH FIX: Use native Directory Picker.
        // Asks the user for a destination folder ONCE, then streams all files into it.
        // Bypasses all navigation locks, popup blockers, and multi-download spam limits.
        if (window.showDirectoryPicker) {
            dirHandle = await window.showDirectoryPicker({ mode: 'readwrite' });
        }
    } catch (err) {
        if (err.name === 'AbortError') return; // User cancelled the folder selection
        console.warn("Directory Picker not supported, falling back to legacy download.");
    }

    myCloudCreateProgressUI(myCloud_LANG.batch_dl);
    const titleEl = document.querySelector('.myCloudProgressTitle');
    const textEl = document.getElementById('myCloudProgressText');

    window.myCloudTaskStart();
	for (let i = 0; i < files.length; i++) {
        const path = files[i];
        const filename = path.split('/').pop();
        const current = i + 1;
        const total = files.length;

        if (titleEl) titleEl.textContent = myCloud_LANG.processing + " " + current + " " + myCloud_LANG.of + " " + total + ": " + filename;
        if (textEl) textEl.textContent = myCloud_LANG.fetching;
        
        myCloudUpdateProgressUI((i / total) * 100);

        try {
            // Pass the directory handle to the file fetcher
            await myCloudDownloadAsBlob(path, filename, dirHandle);
            
            myCloudUpdateProgressUI((current / total) * 100);
            if (textEl) textEl.textContent = myCloud_LANG.data_recv;
        } catch (err) {
            console.error("Download failed for " + filename, err);
            if (textEl) textEl.textContent = myCloud_LANG.error + " " + filename;
        }
    }

    if (textEl) textEl.textContent = myCloud_LANG.dl_complete;
    setTimeout(myCloudCloseProgressUI, 2000);
	window.myCloudTaskEnd();
}

// Fetches a file as a Blob and writes it to the disk.
// Used for batch downloads to prevent blocking.
async function myCloudDownloadAsBlob(path, filename, dirHandle = null) {
    const isEncrypted = typeof myCloudCrypto !== 'undefined' && myCloudCrypto.isDirEncrypted(path);
    const isInsideZip = typeof myCloudIsInsideZip === 'function' ? myCloudIsInsideZip(path) : false;

    const tokenResp = await fetch(window.location.pathname, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            myCloud_action: 'get_download_token',
            myCloud_key: myCloudState.key,
            myCloud_token: typeof myCloudCsrfToken !== 'undefined' ? myCloudCsrfToken : '',
            path: path,
            filename: filename,
            preview: '0',
            isZipContent: isInsideZip ? '1' : '0'
        })
    }).then(function(r) { return r.json(); });

    if (tokenResp.status !== 'OK') throw new Error(tokenResp.msg);

    const dlUrl = window.location.pathname + '?myCloud_token=' + tokenResp.token;
    let finalFilename = filename;
    let blobData = null;

    // Fetch and prepare the blob data
    if (isEncrypted) {
        const item = myCloudState.allItems.find(i => i.name === path);
        const isDir = item && item.size === 'DIR';

        if (!isDir) {
            const root = myCloudCrypto.getCryptoRoot(path);
            if (!myCloudCrypto.isDirUnlocked(root)) throw new Error("Vault is locked.");
            
            const fileResp = await fetch(dlUrl);
            if (!fileResp.ok) throw new Error("HTTP " + fileResp.status);
            const encBlob = await fileResp.blob();

            blobData = await myCloudCrypto.decryptFile(root, encBlob);
            finalFilename = await myCloudCrypto.decryptName(root, filename);
        } else {
            return; // Directories are skipped in file-level batch loops
        }
    } else {
        const fileResp = await fetch(dlUrl);
        if (!fileResp.ok) throw new Error("HTTP " + fileResp.status);
        blobData = await fileResp.blob();
    }
    
    // Write directly to the native file system
    if (dirHandle) {
        const fileHandle = await dirHandle.getFileHandle(finalFilename, { create: true });
        const writable = await fileHandle.createWritable();
        await writable.write(blobData);
        await writable.close();
    } else {
        // Legacy fallback if the browser doesn't support the File System Access API
        const url = window.URL.createObjectURL(blobData);
        const a = document.createElement('a');
        a.href = url;
        a.download = finalFilename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        setTimeout(() => window.URL.revokeObjectURL(url), 60000);
        
        // Grace delay to prevent spam-block in legacy browsers
        await new Promise(resolve => setTimeout(resolve, 800));
    }
}

// Triggers renaming logic
function myCloudAction_Rename() {
    const sel = myCloudState.selectedFiles;
    if (sel.length === 0) return;

    if (sel.length > 1) {
        // [NEW] Open Multi-Rename Tool
        myCloudShowMultiRenameModal();
        return;
    }

    // Inline Rename for single file
    const fullPath = sel[0];
    const rawOldName = fullPath.split('/').pop() || '/';
    let oldName = rawOldName;

    if (myCloudState.pathNames && myCloudState.pathNames[fullPath]) {
        oldName = myCloudState.pathNames[fullPath];
    } else if (oldName.endsWith('.enc')) {
        oldName = oldName.replace(/\.enc$/, '');
    }

    // [FIX] Scoped Selector for Commander Mode
    let searchScope = document;
    if (myCloudState.isCommanderMode) {
        const side = myCloudState.commanderActive || 'left';
        const pane = document.querySelector(`.myCloud-commander-pane[data-side="${side}"]`);
        if (pane) searchScope = pane;
    }

    // Selector: Supports List, Symbol, and Gallery views
    const targetEl = searchScope.querySelector(
        '.myCloudRow[data-fullpath="' + CSS.escape(fullPath) + '"], ' +
        '.myCloud-symbol-item[data-fullpath="' + CSS.escape(fullPath) + '"], ' +
        '.myCloud-gallery-item[data-path="' + CSS.escape(fullPath) + '"]'
    );

    if (!targetEl) return;

    // Determine container based on view type
    let container = null;
    if (targetEl.classList.contains('myCloudRow')) {
        // [FIX] Handle Name cell correctly in Table view (Cell 2 is Name)
        container = targetEl.cells[2].querySelector('.ce-row-content') || targetEl.cells[2];
        // If content wrapper exists, target the text span specifically if possible, else the wrapper
        const textSpan = container.querySelector('.ce-name-text');
        if (textSpan) container = textSpan;
    } else if (targetEl.classList.contains('myCloud-symbol-item')) {
        container = targetEl.querySelector('.ce-sym-label');
    } else if (targetEl.classList.contains('myCloud-gallery-item')) {
        container = targetEl.querySelector('.ce-tile-filename');
    }

    if (!container) return;

	targetEl.setAttribute('draggable', 'false');

    // Backup HTML
    const originalHTML = container.innerHTML;

    // Create Input
    const input = document.createElement('input');
    input.className = 'myCloudInlineInput';
	input.setAttribute('draggable', 'false');
	input.style.cursor = 'text';
    input.value = oldName;
	
    // Aggressive drag-blocking logic
    // 1. Strip the draggable attribute immediately when the mouse touches the input
    input.onmousedown = (e) => {
        e.stopPropagation(); 
        const row = input.closest('.myCloudRow, .myCloud-symbol-item');
        if (row) row.removeAttribute('draggable');
    };
    
    // 2. Prevent any drag events from starting here
    input.ondragstart = (e) => { e.preventDefault(); e.stopPropagation(); };
    
    // 3. Ensure selection events are allowed
    input.onselectstart = (e) => e.stopPropagation();


    // Styling for Grid Views
    if (!targetEl.classList.contains('myCloudRow')) {
        input.style.width = '100%';
        input.style.textAlign = 'center';
        input.style.minHeight = '22px';
    } else {
        // List View Styling
        input.style.width = '100%';
        input.style.minHeight = '20px';
        input.style.margin = '0';
    }

    // Enable pointer events for Symbol View label
    if (targetEl.classList.contains('myCloud-symbol-item')) {
        container.style.pointerEvents = 'auto';
    }
	
	targetEl.setAttribute('draggable', 'true');

    const stopAndPrevent = (e) => {
        e.stopPropagation();
    };

   // Ensure mouse events don't bubble up to the row's drag/click handlers
    const stopEvt = (e) => e.stopPropagation();
    input.addEventListener('mousedown', stopEvt);
    input.addEventListener('mouseup', stopEvt);
    input.addEventListener('click', stopEvt);
    input.addEventListener('dblclick', stopEvt);

    // Swap Content
    container.innerHTML = '';
    container.appendChild(input);

    let isProcessing = false;

    // Restore UI and Focus
    const restoreUI = () => {
        if (targetEl.classList.contains('myCloud-symbol-item')) {
            container.style.pointerEvents = '';
        }
        // Restore draggability to the row now that renaming is done
        targetEl.setAttribute('draggable', 'true');
        container.innerHTML = originalHTML;
        
        // Re-apply selection visual
        targetEl.classList.add('selected');
        
        // Return focus to appropriate container
        if (myCloudState.isCommanderMode) {
             const activePane = document.querySelector(`.myCloud-commander-pane.active .myCloud-commander-content`);
             if (activePane) activePane.focus();
        } else {
             const details = document.querySelector('.myCloudDetails');
             if (details) details.focus();
        }
    };

    // Centralized Finish Handler
    const finishRename = () => {
        if (isProcessing) return;
        isProcessing = true;

        if (input.dataset.cancelled === 'true') {
            restoreUI();
            return;
        }

        const newName = input.value.trim();

        if (newName && newName !== oldName) {
            const parentDir = fullPath.substring(0, fullPath.lastIndexOf('/')) || '/';
            let uploadNamePromise = Promise.resolve(newName);
            if (typeof myCloudCrypto !== 'undefined' && myCloudCrypto.isDirEncrypted(parentDir)) {
                if (!myCloudCrypto.isDirUnlocked(parentDir)) return myCloudShowAlert('Error', 'Directory is locked.');
                uploadNamePromise = myCloudCrypto.encryptName(parentDir, newName);
            }
            uploadNamePromise.then(uploadName => {
                myCloudAPI('rename', { src: fullPath, newName: uploadName }, function(resp) {
                // Use the exact path confirmed by the server
                const newFullPath = resp.newPath || ((parentDir === '' ? '' : parentDir) + '/' + uploadName);

                // Cache decrypted name to prevent visual flashes
                if (typeof myCloudCrypto !== 'undefined' && myCloudCrypto.isDirEncrypted(parentDir)) {
                    if (!myCloudState.pathNames) myCloudState.pathNames = {};
                    myCloudState.pathNames[newFullPath] = newName;
                }
                
                // Update State
                if (myCloudState.isCommanderMode) {
                    const side = myCloudState.commanderActive;
                    const paneState = side === 'left' ? myCloudState.commanderLeft : myCloudState.commanderRight;
                    
                    // Update Pane State
                    paneState.selectedFiles = [newFullPath];
                    
                    // Force refresh of specific pane
                    refreshCommanderPane(side);
                } else {
                    myCloudState.selectedFiles = [newFullPath];
                    myCloudState.currentFile = newFullPath;
                    
                    // Ensure it is scrolled into view after refresh
                    setTimeout(() => {
                        if (typeof myCloudSeekAndSelect === 'function') {
                            myCloudSeekAndSelect(newFullPath);
                        }
                    }, 200);
                }

                // Show Undo Toast
                myCloudShowRenameUndo(newFullPath, rawOldName, newName);
            });
			});
        } else {
            restoreUI();
        }
    };

    // Keyboard Handler
    input.onkeydown = function(e) {
        e.stopPropagation(); 

        if (e.key === 'Enter') {
            e.preventDefault();
            input.blur(); 
        } 
        else if (e.key === 'Escape') {
            e.preventDefault();
            input.dataset.cancelled = 'true';
            input.blur(); 
        }
    };

    input.onblur = finishRename;
    input.focus();

    // Selection Range Logic
    const isDir = myCloudState.allItems.find(i => i.name === fullPath)?.size === 'DIR';
    const lastDot = oldName.lastIndexOf('.');
    if (!isDir && lastDot > 0) {
        input.setSelectionRange(0, lastDot);
    } else {
        input.select();
    }
}


// Toast for Undoing Rename
window.myCloudShowRenameUndo = function(currentPath, rawOldName, displayNewName) {
    // 1. Destroy any existing toast to prevent DOM event collisions
    const existing = document.getElementById('myCloudRenameToast');
    if (existing) existing.remove();

    // 2. Create the new toast
    const div = document.createElement('div');
    div.id = 'myCloudRenameToast';
    // Match style of existing Recycle Bin toast
    div.style.cssText = 'position:fixed; bottom:70px; left:50%; transform:translateX(-50%); background:#323130; color:#fff; padding:12px 24px; border-radius:4px; box-shadow:0 4px 12px rgba(0,0,0,0.3); z-index:99999; display:flex; align-items:center; gap:15px; font-size:14px; animation:ceFadeInScale 0.3s; font-family:var(--font-family);';
    
    // Safely escape the name for HTML display
    const safeDisplay = typeof myCloudEscapeHtml === 'function' ? myCloudEscapeHtml(displayNewName || currentPath.split('/').pop().replace(/\.enc$/, '')) : (displayNewName || currentPath.split('/').pop().replace(/\.enc$/, ''));
    
    div.innerHTML = 
        '<span>Renamed to <b>' + safeDisplay + '</b></span>' +
        '<button class="ce-undo-action-btn" style="background:transparent; border:none; color:#60cdff; font-weight:bold; cursor:pointer; text-transform:uppercase; margin-left: auto;">' + (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.undo_btn ? myCloud_LANG.undo_btn : 'UNDO') + '</button>' +
        '<button class="ce-undo-close-btn" style="background:transparent; border:none; color:#aaa; cursor:pointer; margin-left:10px; font-size:16px;">✕</button>';
    
    document.body.appendChild(div);
    
    // 3. Scope click events strictly to this exact DOM element
    div.querySelector('.ce-undo-action-btn').onclick = () => {
        div.remove();
        // Perform Undo: Rename 'currentPath' back to 'rawOldName'
        myCloudAPI('rename', { src: currentPath, newName: rawOldName }, function() {
            myCloudFetchDirectory(myCloudState.currentDir);
        });
    };

    div.querySelector('.ce-undo-close-btn').onclick = () => {
        div.remove();
    };
    
    // 4. Auto dismiss after 8 seconds
    setTimeout(() => { if(div.parentNode) div.remove(); }, 8000);
};

// Handles server-side zipping of files/folders.
// Checks folder size first and warns if too large.
function myCloudAction_Zip(mode) {
    const st = myCloudState;
    if (st.selectedFiles.length !== 1) return;
    if (myCloudUserRole === 'read-only') return;

    const path = st.selectedFiles[0];
    const limit = 300 * 1024 * 1024; 

    myCloudCreateProgressUI(myCloud_LANG.check_size);

    const fd = new URLSearchParams();
    fd.append('myCloud_action', 'get_size');
    fd.append('myCloud_key', myCloudState.key);
    fd.append('myCloud_token', myCloudCsrfToken);
    fd.append('src', path);

    fetch('', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        const size = (data.status === 'OK') ? parseInt(data.size) : 0;

        const startZip = function() {
            const zipFd = new URLSearchParams();
            zipFd.append('myCloud_action', 'zip');
            zipFd.append('src', path);
            zipFd.append('mode', mode);
            myCloudStreamAction(zipFd, (mode === 'move' ? myCloud_LANG.moving_to_zip : myCloud_LANG.copying_to_zip));
        };

        if (size > limit) {
            const sizeStr = myCloudFormatBytes(size);
            
            const txtEl = document.getElementById('myCloudProgressText');
            const barEl = document.getElementById('myCloudProgressBar'); 
            
            if (barEl) barEl.style.display = 'none';

            txtEl.className = 'ce-warning-box';
            txtEl.innerHTML = 
                '<div class="ce-warning-title">' + myCloud_LANG.warn_large_vol + '</div>' +
                '<div style="margin-bottom:20px;">' +
                    myCloud_LANG.uncompressed_size_is + ' <b>' + sizeStr + '</b>.<br>' +
                    myCloud_LANG.process_time_warn +
                '</div>' +
                '<div class="ce-btn-group">' +
                    '<button id="btnZipYes" class="ce-btn-action ce-btn-confirm">' + myCloud_LANG.continue_btn + '</button>' +
                    '<button id="btnZipNo" class="ce-btn-action ce-btn-danger">' + myCloud_LANG.cancel + '</button>' +
                '</div>';
            
            document.getElementById('btnZipYes').onclick = function() {
                if (barEl) barEl.style.display = 'block'; 
                startZip();
            };
            
            document.getElementById('btnZipNo').onclick = function() {
                myCloudCloseProgressUI();
            };

        } else {
            startZip();
        }
    })
    .catch(function(e) {
        myCloudCloseProgressUI();
        console.error(e);
        alert("Server Error");
    });
}

// Triggers server-side unzipping.
function myCloudAction_Unzip() {
    const st = myCloudState;
    if (st.selectedFiles.length !== 1) return;
    
    if (myCloudUserRole === 'read-only') return;

    const path = st.selectedFiles[0];
    
    const fd = new URLSearchParams();
    fd.append('myCloud_action', 'unzip');
    fd.append('src', path);

    myCloudStreamAction(fd, myCloud_LANG.unzipping_verb);
}

function myCloudAction_SelectAll() {
    const st = myCloudState;

    // [FIX] Commander Mode Support
    if (st.isCommanderMode) {
        const side = st.commanderActive;
        const paneState = (side === 'left') ? st.commanderLeft : st.commanderRight;
        
        // Select all visible items in this pane (exclude recycle bin self)
        paneState.selectedFiles = paneState.items
            .map(i => i.name)
            .filter(name => name !== '/.recycle_bin');
            
        // Sync Global
        st.selectedFiles = paneState.selectedFiles;
        
        // Render Pane Only
        const pane = document.querySelector(`.myCloud-commander-pane[data-side="${side}"]`);
        const content = pane.querySelector('.myCloud-commander-content');
        renderCommanderContent(content, paneState, side);
        myCloudUpdateToolbarState();
        return;
    }
    
    // Standard Mode
    const currentViewItems = myCloudGetSortedItems();
    st.selectedFiles = currentViewItems
        .map(function(i) { return i.name; })
        .filter(function(name) { return name !== '/.recycle_bin'; });
        
    myCloudRenderUI();
    myCloudUpdateToolbarState();
}

function myCloudAction_InvertSelection() {
    const st = myCloudState;
    
    // [FIX] Commander Mode Support
    if (st.isCommanderMode) {
        const side = st.commanderActive;
        const paneState = (side === 'left') ? st.commanderLeft : st.commanderRight;
        
        const allNames = paneState.items.map(i => i.name);
        
        paneState.selectedFiles = allNames.filter(function(name) {
            return !paneState.selectedFiles.includes(name) && name !== '/.recycle_bin';
        });
        
        // Sync Global
        st.selectedFiles = paneState.selectedFiles;
        
        const pane = document.querySelector(`.myCloud-commander-pane[data-side="${side}"]`);
        const content = pane.querySelector('.myCloud-commander-content');
        renderCommanderContent(content, paneState, side);
        myCloudUpdateToolbarState();
        return;
    }
    
    // Standard Mode
    const currentViewItems = myCloudGetSortedItems();
    const visibleNames = currentViewItems.map(function(i) { return i.name; });
    
    st.selectedFiles = visibleNames.filter(function(name) {
        return !st.selectedFiles.includes(name) && name !== '/.recycle_bin';
    });
    
    myCloudRenderUI();
    myCloudUpdateToolbarState();
}

function myCloudAction_ClearSelection() {
    const st = myCloudState;
    
    // [FIX] Commander Mode Support
    if (st.isCommanderMode) {
        const side = st.commanderActive;
        const paneState = (side === 'left') ? st.commanderLeft : st.commanderRight;
        
        paneState.selectedFiles = [];
        st.selectedFiles = []; // Sync Global
        
        const pane = document.querySelector(`.myCloud-commander-pane[data-side="${side}"]`);
        const content = pane.querySelector('.myCloud-commander-content');
        // Optimization: Don't redraw, just de-select DOM classes
        content.querySelectorAll('.selected').forEach(el => {
            el.classList.remove('selected');
            const cb = el.querySelector('.myCloudCheckbox');
            if(cb) cb.checked = false;
        });
        
        myCloudUpdateToolbarState();
        return;
    }

    // Standard Mode
    st.selectedFiles = [];
    st.currentFile = null;
    myCloudRenderUI();
    myCloudUpdateToolbarState();
}


// Shows a custom context menu for file items.
// Replaces browser default right-click menu.
function myCloudShowContextMenu(e, item, isTree) {
    if (typeof isTree === 'undefined') isTree = false;
    if (e.preventDefault) e.preventDefault();
    if (e.stopPropagation) e.stopPropagation();
    const st = myCloudState;
	const capturedSelection = [...st.selectedFiles];
    
    const isMulti = st.selectedFiles.length > 1;
    const officeExtsMenu = ['docx', 'xlsx', 'pptx', 'doc', 'xls', 'ppt', 'odt', 'ods', 'odp', 'rtf', 'csv'];
    const allStackable = isMulti && st.selectedFiles.every(f => {
        const x = f.toLowerCase().split('.').pop();
        return x === 'pdf' || officeExtsMenu.includes(x);
    });
    const allJpgPng = isMulti && st.selectedFiles.every(f => {
        const px = f.toLowerCase().split('.').pop();
        return px === 'jpg' || px === 'jpeg' || px === 'png';
    });
    
    // Strict Guard: Abort if multiple files are selected but they aren't purely stackable or images
    if (isMulti && !allStackable && !allJpgPng) return;

    
    const filename = item.name.split('/').pop() || '/';
    const ext = filename.split('.').pop().toLowerCase();
    
    const role = (typeof myCloudUserRole !== 'undefined') ? myCloudUserRole : 'no-access';
    const isInsideZip = (st.currentDir && /\.zip(\/|$)/i.test(st.currentDir));

    const isDir = (item.size === 'DIR');
    const isZipFile = (!isDir && ext === 'zip'); 
    const isRecycleBin = (st.currentDir === '/.recycle_bin');
    
    const isOfficeMode = st.isOfficeMode;
    const isFav = myCloudIsFavorite(item.name);
	const isStackableGroup = isMulti && st.selectedFiles.every(f => { const x = f.toLowerCase().split('.').pop(); return x === 'pdf' || ['docx', 'xlsx', 'pptx', 'doc', 'xls', 'ppt', 'odt', 'ods', 'odp', 'rtf', 'csv'].includes(x); });
    
    const isPreviewable = !isDir && (typeof previewExts !== 'undefined' ? previewExts.includes(ext) : false);
    const isEditable = !isDir && typeof myCloudIsFileEditable === 'function' && myCloudIsFileEditable(item.name, isInsideZip);
	const isOfficeDoc = typeof officeExts !== 'undefined' && officeExts.includes(ext);
    const isPrintable = !isDir && typeof myCloudHasOnlyOffice !== 'undefined' && myCloudHasOnlyOffice === true && (isOfficeDoc || ext === 'pdf') && window.myCloudActionAllowed('print');

    let encryptLabel = typeof myCloud_LANG !== 'undefined' && myCloud_LANG.encrypt_short ? myCloud_LANG.encrypt_short : 'Encrypt';
    if (typeof myCloudCrypto !== 'undefined') {
        const root = myCloudCrypto.getCryptoRoot(item.name);
        if (root || item.isEncrypted || (myCloudState.encryptedDirs && myCloudState.encryptedDirs.has(item.name))) {
            encryptLabel = myCloudCrypto.isDirUnlocked(item.name) ? (myCloud_LANG.lock_short || 'Lock Vault') : (myCloud_LANG.unlock_short || 'Unlock');
        }
    }

    document.querySelectorAll('.myCloudContextMenu').forEach(function(m) { m.remove(); });
    const oldSpacer = document.getElementById('ceMenuSpacer');
    if (oldSpacer) oldSpacer.remove();

    const menu = document.createElement('div');
    menu.id = 'myCloudContextMenu';
    menu.className = 'myCloudContextMenu';
    menu.style.position = 'fixed'; 
    menu.style.zIndex = '2000000';
    menu.style.visibility = 'hidden';
    
    // --- TAG ROW ---
    if (!isRecycleBin && item.name !== '/' && window.myCloudActionAllowed('edit_tags')) {
        const tagRow = document.createElement('div');
        tagRow.className = 'myCloudContextTagRow'; // Suggested class for styles.php
        tagRow.style.padding = '8px 16px';
        tagRow.style.display = 'flex';
        tagRow.style.gap = '8px';
        tagRow.style.borderBottom = '1px solid var(--border-default)';
        const colors = [...(myCloudState.settings.visibleTags || ['#e81123', '#0078d4', '#107c10', '#f0ad4e', '#888888']), 'transparent'];
        colors.forEach(color => {
            const dot = document.createElement('div');
            dot.style.cssText = 'width:16px; height:16px; border-radius:50%; cursor:pointer; border:1px solid #ccc; box-shadow:inset 0 1px 3px rgba(0,0,0,0.2);';
            dot.style.backgroundColor = color === 'transparent' ? '#fff' : color;
            if (color === 'transparent') {
                dot.innerHTML = '<div style="text-align:center;line-height:14px;color:#f00;font-size:14px;">×</div>';
                dot.title = myCloud_LANG.clear || 'Clear';
            } else {
                dot.title = window.myCloudGetTagName(color);
            }
            
            let currentTags = (myCloudState.tags && myCloudState.tags[myCloudState.key]) ? myCloudState.tags[myCloudState.key][item.name] : [];
            if (currentTags && !Array.isArray(currentTags)) currentTags = [currentTags];
            if (!currentTags) currentTags = [];
            
            if (color !== 'transparent' && currentTags.includes(color)) {
                dot.style.boxShadow = '0 0 0 2px var(--gray-00), 0 0 0 4px ' + color;
            }

            dot.onclick = (evt) => {
                evt.stopPropagation();
                menu.remove();
                const sp = document.getElementById('ceMenuSpacer');
                if (sp) sp.remove();
                
                if (!myCloudState.tags || Array.isArray(myCloudState.tags)) myCloudState.tags = {};
                if (!myCloudState.tags[myCloudState.key] || Array.isArray(myCloudState.tags[myCloudState.key])) {
                    myCloudState.tags[myCloudState.key] = {};
                }
                
                let tagsArray = [];
                let existing = myCloudState.tags[myCloudState.key][item.name];
                if (Array.isArray(existing)) {
                    tagsArray = [...existing];
                } else if (typeof existing === 'string' && existing.trim() !== '') {
                    tagsArray = [existing];
                }

                if (color === 'transparent') {
                    tagsArray = [];
                } else {
                    if (tagsArray.includes(color)) {
                        tagsArray = tagsArray.filter(c => c !== color);
                    } else {
                        tagsArray.push(color);
                    }
                }
                
                if (tagsArray.length === 0) {
                    delete myCloudState.tags[myCloudState.key][item.name];
                } else {
                    myCloudState.tags[myCloudState.key][item.name] = tagsArray;
                }
                
                myCloudSaveTags();
                if (myCloudState.isCommanderMode) {
                    const side = myCloudState.commanderActive || 'left';
                    if (typeof refreshCommanderPane === 'function') refreshCommanderPane(side);
                } else {
                    myCloudRenderUI();
                }
            };
            tagRow.appendChild(dot);
        });
        menu.appendChild(tagRow);
    }

    if (typeof myCloudCrypto !== 'undefined') {
        const root = myCloudCrypto.getCryptoRoot(item.name);
        if (root) {
            encryptLabel = myCloudCrypto.isDirUnlocked(root) ? (myCloud_LANG.lock_short || 'Lock Vault') : (myCloud_LANG.unlock_short || 'Unlock');
        }
    }


    // --- DEFINE ACTIONS ---
    const actions = [
        // 1. Icon Grid Row (Duplicate first)
        {
            grid: [
				{ act: 'duplicate', tip: myCloud_LANG.duplicate || 'Duplicate', icon: myCloudSvg.duplicate, show: window.myCloudActionAllowed('duplicate') && !isInsideZip && item.name !== '/' && !isRecycleBin && !isMulti },
				{ act: 'copy',      tip: myCloud_LANG.copy,      icon: myCloudSvg.copy,      show: window.myCloudActionAllowed('copy') && item.name !== '/' && !isTree && !isRecycleBin },
				{ act: 'move',      tip: myCloud_LANG.move,      icon: myCloudSvg.move,      show: window.myCloudActionAllowed('move') && !isInsideZip && item.name !== '/' && !isTree && !isRecycleBin },
				{ act: 'rename',    tip: myCloud_LANG.rename,    icon: myCloudSvg.rename,    show: window.myCloudActionAllowed('rename') && !isInsideZip && item.name !== '/' && !isTree && !isRecycleBin },
				{ act: 'delete',    tip: myCloud_LANG.delete,    icon: myCloudSvg.delete,    show: window.myCloudActionAllowed('delete') && !isInsideZip && item.name !== '/' && !isTree, danger: true }
            ]
        },
        { sep: true },
        // 3. Main List Actions
        { label: myCloud_LANG.restore, icon: '<svg viewBox="0 0 24 24"><path d="M13 3c-4.97 0-9 4.03-9 9H1l3.89 3.89.07.14L9 12H6c0-3.87 3.13-7 7-7s7 3.13 7 7-3.13 7-7 7c-1.93 0-3.68-.79-4.94-2.06l-1.42 1.42C8.27 19.99 10.51 21 13 21c4.97 0 9-4.03 9-9s-4.03-9-9-9zm-1 5v5l4.28 2.54.72-1.21-3.5-2.08V8H12z"/></svg>', act: 'restore', show: isRecycleBin && window.myCloudActionAllowed('restore') },
        { label: 'Restore To...', icon: myCloudSvg.move, act: 'restore_to', show: isRecycleBin && window.myCloudActionAllowed('restore_to') },
        { label: myCloud_LANG.empty_bin || 'Empty Recycle Bin', icon: myCloudSvg.trash, act: 'empty_bin', show: (item.name.replace(/\/$/, '') === '/.recycle_bin' || item.name === '.recycle_bin') && window.myCloudActionAllowed('empty_bin'), danger: true },

        { label: myCloud_LANG.pdf_unstack || 'Unstack file', icon: '<svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V5h14v14zM7 10h2v7H7zm4-3h2v10h-2zm4 6h2v4h-2z" fill="currentColor"/></svg>', act: 'pdf_unstack', show: isPreviewable && ext === 'pdf' && !isMulti && !isRecycleBin && window.myCloudActionAllowed('pdf_unstack') && !isInsideZip },
        { label: myCloud_LANG.pdf_stack || 'Stack PDFs', icon: '<svg viewBox="0 0 24 24"><path d="M4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm16-4H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H8V4h12v12z" fill="currentColor"/></svg>', act: 'pdf_stack_menu', show: allStackable && !isRecycleBin && window.myCloudActionAllowed('pdf_stack_menu') && !isInsideZip },
        { label: myCloud_LANG.pdf_tools || 'PDF Toolkit...', icon: '<svg viewBox="0 0 24 24"><path d="M22.7 19l-9.1-9.1c.9-2.3.4-5-1.5-6.9-2-2-5-2.4-7.4-1.3L9 6 6 9 1.6 4.7C.4 7.1.9 10.1 2.9 12.1c1.9 1.9 4.6 2.4 6.9 1.5l9.1 9.1c.4.4 1 .4 1.4 0l2.3-2.3c.5-.4.5-1.1.1-1.4z" fill="currentColor"/></svg>', act: 'pdf_toolkit', show: ext === 'pdf' && !isMulti && !isRecycleBin && window.myCloudActionAllowed('pdf_tools') && !isInsideZip },
        { label: myCloud_LANG.pdf_combine_images || 'Combine to PDF', icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><circle cx="10" cy="13" r="2"/><polyline points="6 17 11 12 18 19"/></svg>', act: 'pdf_combine_images', show: isMulti && st.selectedFiles.every(f => { const px = f.toLowerCase().split('.').pop(); return px === 'jpg' || px === 'jpeg' || px === 'png'; }) && !isRecycleBin && window.myCloudActionAllowed('pdf_combine_images') && !isInsideZip },
        
        { label: myCloud_LANG.share_btn || 'Share', icon: myCloudSvg.share || '', act: 'share', show: !isMulti && !isInsideZip && !isRecycleBin && typeof window.myCloudAction_Share === 'function' && window.myCloudActionAllowed('share') },
        { label: myCloud_LANG.edit || 'Edit', icon: myCloudSvg.edit_file, act: 'edit_file', show: isEditable && !isMulti && !isRecycleBin && window.myCloudActionAllowed('edit_file') },
        { label: myCloud_LANG.preview, icon: myCloudSvg.preview, act: 'preview', show: isPreviewable && !isMulti && !isRecycleBin && window.myCloudActionAllowed('preview') },
        { label: myCloud_LANG.print || 'Print', icon: '<svg viewBox="0 0 24 24" style="fill:#444"><path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-2-9H7v3h10V3z"/></svg>', act: 'print', show: (isPrintable || allStackable) && !isRecycleBin && window.myCloudActionAllowed('print') },
        { label: myCloud_LANG.download, icon: myCloudSvg.download, act: 'download', show: !isRecycleBin && window.myCloudActionAllowed('download') },
        { label: encryptLabel, icon: '<svg viewBox="0 0 24 24" style="fill:currentColor;"><path d="M12.65 10A5.99 5.99 0 0 0 7 6c-3.31 0-6 2.69-6 6s2.69 6 6 6a5.99 5.99 0 0 0 5.65-4H17v4h4v-4h2v-4H12.65zM7 14c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2z"/></svg>', act: 'encrypt_dir', show: isDir && !isInsideZip && !isRecycleBin && window.myCloudActionAllowed('encrypt') },
		{ label: myCloud_LANG.change_vault_pwd || 'Change Vault Password', icon: '<svg viewBox="0 0 24 24" style="fill:currentColor;"><path d="M2 16v2c2.78 0 5.42-.94 7.6-2.58l-1.5-1.5C6.46 15.19 4.3 16 2 16zm6.83-3.66l2.12-2.12c-1.63-1.63-3.8-2.54-6.04-2.54v2c1.78 0 3.48.71 4.75 1.98l-1.5 1.5 3.33 3.33L13.83 13l-1.5-1.5c-1.15 1.15-2.6 1.83-4.16 1.83v-2c1.02 0 1.99-.44 2.66-1.16zM20 4H4c-1.1 0-2 .9-2 2v4h2V6h16v12h-8v2h8c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2z"/></svg>', act: 'change_vault_pwd', show: isDir && !isInsideZip && !isRecycleBin && typeof myCloudCrypto !== 'undefined' && myCloudCrypto.isDirEncrypted(item.name) },
		{ label: myCloud_LANG.fav_toggle, icon: isFav ? myCloudSvg.star_filled : myCloudSvg.star, act: 'toggle_fav', show: !isMulti && item.name !== '/' && !isRecycleBin && window.myCloudActionAllowed('fav_toggle') },
        { label: myCloud_LANG.refresh, icon: myCloudSvg.refresh, act: 'refresh', show: isDir && !isMulti && !isInsideZip && !isRecycleBin },
        { label: myCloud_LANG.zip_copy, icon: myCloudSvg.zip, act: 'zip_copy', show: isDir && !isMulti && window.myCloudActionAllowed('zip_copy') && !isInsideZip && !isTree && !isRecycleBin },
        { label: myCloud_LANG.unzip, icon: myCloudSvg.unzip, act: 'unzip', show: isZipFile && !isMulti && window.myCloudActionAllowed('unzip') && !isInsideZip && !isRecycleBin },

        // "New" Submenu
        { 
            label: (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.new) ? myCloud_LANG.new : 'New', 
            icon: myCloudSvg.newfile, 
            act: 'sub_new', 
            show: isDir && !isMulti && window.myCloudActionAllowed('newfile') && !isInsideZip && !isRecycleBin,
            sub: [
                { label: myCloud_LANG.new_file || 'New File', icon: myCloudSvg.newfile, act: 'newfile', show: window.myCloudActionAllowed('newfile') },
                { label: myCloud_LANG.new_folder, icon: myCloudSvg.newfolder, act: 'newfolder', show: window.myCloudActionAllowed('newfolder') }
            ]
        },

        { label: myCloud_LANG.permissions || 'Permissions', icon: myCloudSvg.permissions, act: 'permissions', show: window.myCloudActionAllowed('permissions') && !isInsideZip && myCloudUserRole === 'admin_mode' && !isRecycleBin && !isTree },
        { label: myCloud_LANG.properties, icon: '<svg viewBox="0 0 24 24"><path d="M11 7h2v2h-2zm0 4h2v6h-2zm1-9C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/></svg>', act: 'properties', show: isDir && !isMulti && !isRecycleBin && window.myCloudActionAllowed('properties') },
		{ label: typeof myCloud_LANG !== 'undefined' && myCloud_LANG.fix_encryption ? myCloud_LANG.fix_encryption : 'Fix Encryption', icon: '<svg viewBox="0 0 24 24" style="fill:currentColor;"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/></svg>', act: 'fix_encryption', show: item.isBrokenEncryption === true && !isMulti, danger: true },
    ];

    // --- HELPER: ACTION HANDLER ---
    const handleAction = (act) => {
        switch(act) {
            case 'preview':  myCloudDownloadFile(item.name, filename, true); break;
           case 'edit_file': window.myCloudAction_EditFile(item.name); break;
            case 'print':    window.myCloudAction_Print(item.name); break;
            case 'download': isMulti ? myCloudAction_DownloadBatch() : myCloudDownloadFile(item.name, filename, false); break;
            case 'refresh':  myCloudFetchDirectory(st.currentDir); break;
            case 'newfolder':myCloudAction_NewFolder(); break;
            case 'newfile':  myCloudAction_NewFile(); break;
            case 'duplicate': myCloudAction_Duplicate(item.name); break;
			case 'encrypt_dir': myCloudAction_EncryptPrompt(item.name); break;
			case 'change_vault_pwd': myCloudAction_ChangeVaultPassword(item.name); break;
			case 'help': if (typeof window.myCloudOpenHelp === 'function') window.myCloudOpenHelp(); break;
            case 'share': if(typeof window.myCloudAction_Share === 'function') window.myCloudAction_Share(item.name); break;
            case 'share_list': if(typeof window.cxShowAllShares === 'function') window.cxShowAllShares(); break;
            case 'rename':   myCloudAction_Rename(); break;
            case 'permissions': if(typeof myCloudAction_Permissions === 'function') myCloudAction_Permissions(); break;
            case 'copy':     myCloudAction_CopyMove(false); break;
            case 'move':     myCloudAction_CopyMove(true); break;
            case 'pdf_unstack': window.myCloudAction_PdfUnstack(item.name); break;
            case 'pdf_stack_menu': 
                try {
                    const selItems = capturedSelection;
                    if (selItems.length < 2) { alert("Please select at least 2 files."); break; }
                    
                    let targetDest = selItems[0];
                    const extD = targetDest.split('.').pop().toLowerCase();
                    const oExts = ['docx', 'xlsx', 'pptx', 'doc', 'xls', 'ppt', 'odt', 'ods', 'odp', 'rtf', 'csv'];
                    if (oExts.includes(extD)) {
                        targetDest = targetDest.substring(0, targetDest.lastIndexOf('.')) + '.pdf';
                    }
                    
                    if (typeof window.myCloudShowPdfMergeDialog === 'function') {
                        window.myCloudShowPdfMergeDialog(selItems, targetDest, false, true);
                    } else {
                        alert("Error: myCloudShowPdfMergeDialog is not loaded.");
                    }
                } catch (err) {
                    alert("Menu routing error: " + err.message);
                }
                break;
            case 'pdf_toolkit': window.myCloudShowPdfToolkit(item.name); break;
            case 'pdf_combine_images': window.myCloudAction_PdfCombineImages(); break;
            case 'delete':   myCloudAction_Delete(); break;
            case 'restore':  myCloudAction_Restore(); break;
            case 'restore_to': myCloudAction_RestoreTo(); break;
            case 'empty_bin': 
                myCloudShowAlert(
                    myCloud_LANG.empty_bin || 'Empty Recycle Bin', 
                    myCloud_LANG.confirm_perm_del || 'Are you sure?', 
                    function() {
                        myCloudCloseModal();
                        myCloudAPI('empty_bin', {}, function() {
                            if (st.currentDir === '/.recycle_bin') myCloudFetchDirectory(st.currentDir);
                            else myCloudFetchDirectory('/');
                        });
                    }
                );
                break;
            case 'toggle_fav': myCloudToggleFavorite(item.name); break;
            case 'zip_copy': myCloudAction_Zip('copy'); break;
            case 'unzip':    myCloudAction_Unzip(); break;
            case 'properties': myCloudShowProperties(item.name); break;
			case 'fix_encryption': if (typeof window.myCloudAction_FixEncryption === 'function') window.myCloudAction_FixEncryption(item.name, item.size === 'DIR'); break;
			}
    };

    // --- HELPER: RECURSIVE RENDERER ---
    let hasItems = false;

    const shortcutsMap = {
        'rename': 'F2',
        'delete': 'Del',
        'copy': 'Ctrl+C',
        'move': 'Ctrl+X',
        'duplicate': 'Ctrl+D',
        'select_all': 'Ctrl+A'
    };

    const render = (target, list) => {
        list.forEach(a => {
            const right = (typeof item !== 'undefined' && item.rights) ? item.rights : (typeof myCloudUserRole !== 'undefined' ? myCloudUserRole : 'read-only');
            if (a.show === false || !window.myCloudActionAllowed(a.act, right)) return;


            if (a.sep) {
                const sep = document.createElement('div');
                sep.className = 'myCloudContextSep';
                target.appendChild(sep);
                return;
            }

            if (a.grid) {
                const validGrid = a.grid.filter(g => g.show);
                if (validGrid.length === 0) return;
                hasItems = true;

                // If fewer than 3 items, render them as normal vertical list items
                if (validGrid.length < 3) {
                    validGrid.forEach(g => {
                        const el = document.createElement('div');
                        el.className = 'myCloudContextItem' + (g.danger ? ' danger' : '');
                        const kbdHint = (g.act && shortcutsMap[g.act]) ? '<span class="myCloudContextKbd">' + shortcutsMap[g.act] + '</span>' : '';
                        el.innerHTML = '<span class="myCloudIcon" style="width:20px; height:20px; margin-right:12px; font-size:18px; display:inline-flex; align-items:center; justify-content:center;">' + g.icon + '</span> <span style="flex:1;">' + (g.tip || g.act) + '</span>' + kbdHint;
                        
                        el.onclick = function(ev) {
                            ev.stopPropagation();
                            document.querySelectorAll('.myCloudContextMenu').forEach(m => m.remove());
                            const sp = document.getElementById('ceMenuSpacer');
                            if (sp) sp.remove();
                            handleAction(g.act);
                        };
                        target.appendChild(el);
                    });
                    return;
                }

                const gridRow = document.createElement('div');
                gridRow.className = 'myCloudContextGridRow';
                validGrid.forEach(g => {
                    const gItem = document.createElement('div');
                    gItem.className = 'myCloudContextGridItem' + (g.danger ? ' danger' : '');
                    gItem.innerHTML = '<div style="display:flex; align-items:center; justify-content:center; height:20px;">' + g.icon + '</div><span class="grid-label">' + (g.tip || '') + '</span>';
                    
                    gItem.onmouseenter = (ev) => {
                        const tip = document.createElement('div');
                        tip.className = 'myCloudContextTooltip';
                        tip.id = 'myCloudActiveTip';
                        let tipText = g.tip || '';
                        if (g.act && shortcutsMap[g.act]) {
                            tipText += ' (' + shortcutsMap[g.act] + ')';
                        }
                        tip.textContent = tipText;
                        // Detect current direction
                        const isRtl = document.documentElement.dir === 'rtl' || document.body.dir === 'rtl';
                        if (isRtl) tip.setAttribute('dir', 'rtl');
                        document.body.appendChild(tip);
                        
                        const rect = gItem.getBoundingClientRect();
                        tip.style.left = (rect.left + (rect.width / 2) - (tip.offsetWidth / 2)) + 'px';
                        tip.style.top = (rect.top - tip.offsetHeight - 8) + 'px';
                    };
                    
                    gItem.onmouseleave = () => {
                        const tip = document.getElementById('myCloudActiveTip');
                        if (tip) tip.remove();
                    };
                    gItem.onclick = (ev) => { 
                        ev.stopPropagation(); 
                        const tip = document.getElementById('myCloudActiveTip');
                        if (tip) tip.remove();
                        document.querySelectorAll('.myCloudContextMenu').forEach(m => m.remove());
                        handleAction(g.act); 
                    };
                    gridRow.appendChild(gItem);
                });
                target.appendChild(gridRow);
                return;
            }

            hasItems = true;
            const el = document.createElement('div');
            const kbdHint = (a.act && shortcutsMap[a.act]) ? '<span class="myCloudContextKbd">' + shortcutsMap[a.act] + '</span>' : '';
            // Use myCloudContextItem to match your existing styles
            el.className = 'myCloudContextItem' + (a.danger ? ' danger' : '') + (a.sub ? ' hasSub' : '');
            el.innerHTML = '<span class="myCloudIcon" style="width:20px; height:20px; margin-right:12px; font-size:18px; display:inline-flex; align-items:center; justify-content:center;">' + a.icon + '</span> <span style="flex:1;">' + (a.label || a.name) + '</span>' + kbdHint;
		   
            if (a.sub) {
                const sub = document.createElement('div');
                sub.className = 'myCloudContextSubMenu';
                render(sub, a.sub);
                el.appendChild(sub);
                
                // On mobile/touch, hover doesn't exist well, so we allow a click to toggle
                el.onclick = (ev) => {
                    ev.stopPropagation();
                    // Optional: toggle a 'force-show' class for mobile here
                };
            } else {
                el.onclick = function(ev) {
                    ev.stopPropagation();
                    // Close ALL menus (including parents)
                    document.querySelectorAll('.myCloudContextMenu').forEach(m => m.remove());
                    const sp = document.getElementById('ceMenuSpacer');
                    if (sp) sp.remove();
                    
                    handleAction(a.act);
                };
            }
            target.appendChild(el);
        });
    };

    render(menu, actions);

    // --- POSITIONING & DISPLAY ---
    // --- POSITIONING & DISPLAY ---
    if (hasItems) {
        document.body.appendChild(menu);
        
        menu.style.position = 'fixed';
        menu.style.visibility = 'hidden';
        menu.style.display = 'block'; 
        menu.style.maxHeight = 'none';
        menu.style.overflowY = 'visible';
        
        // KILL ALL ANIMATIONS temporarily to get the absolute final painted height
        menu.style.transition = 'none';
        menu.style.transform = 'none';
        menu.style.animation = 'none';
        
        // Force synchronous layout calculation
        void menu.offsetHeight;

        // Measure TRUE content height (scrollHeight ignores animation scale/clipping)
        const menuHeight = menu.scrollHeight;
        const menuWidth = menu.scrollWidth || 250;

        // Restore animation rules so it fades in nicely
        menu.style.transition = '';
        menu.style.transform = '';
        menu.style.animation = '';

        const source = (e.touches && e.touches.length > 0) ? e.touches[0] : e;
		
        let leftPos = source.clientX + 5;
        let topPos = source.clientY + 2;

        if (leftPos + menuWidth > window.innerWidth - 5) {
            leftPos = window.innerWidth - menuWidth - 5;
        }
        if (leftPos < 5) leftPos = 5;
        menu.style.left = leftPos + 'px';

        // THE BULLETPROOF FIX: Native CSS Bottom Anchoring
        if (topPos + menuHeight > window.innerHeight - 25) {
            menu.style.top = 'auto';
            menu.style.bottom = '15px'; // Anchor strictly to the bottom edge
            
            // Failsafe if the screen is physically shorter than the menu
            if (menuHeight > window.innerHeight - 30) {
                menu.style.maxHeight = (window.innerHeight - 30) + 'px';
                menu.style.overflowY = 'auto';
            }
        } else {
            menu.style.top = topPos + 'px';
            menu.style.bottom = 'auto';
        }

        if (typeof myCloudApplyTheme === 'function') myCloudApplyTheme();
        menu.style.visibility = 'visible';
    }
}


// Renders a context menu for the empty space (Dropzone / Background)
function myCloudShowBackgroundContextMenu(e, side) {
    if (e.preventDefault) e.preventDefault();
    if (e.stopPropagation) e.stopPropagation();
    const st = myCloudState;
    const dir = side ? (side === 'left' ? st.commanderLeft.dir : st.commanderRight.dir) : st.currentDir;
    
    const role = (typeof myCloudUserRole !== 'undefined') ? myCloudUserRole : 'no-access';
    const isInsideZip = /\.zip(\/|$)/i.test(dir);
    const isRecycleBin = (dir === '/.recycle_bin');

    document.querySelectorAll('.myCloudContextMenu').forEach(function(m) { m.remove(); });
	const oldSpacer2 = document.getElementById('ceMenuSpacer');
    if (oldSpacer2) oldSpacer2.remove();

    const menu = document.createElement('div');
    menu.id = 'myCloudContextMenu';
    menu.className = 'myCloudContextMenu';
    menu.style.position = 'fixed'; 
    menu.style.zIndex = '2000000';
    menu.style.visibility = 'hidden';

    const actions = [
        { label: myCloud_LANG.refresh || 'Update', icon: myCloudSvg.refresh, act: 'refresh', show: !isRecycleBin && !isInsideZip },
        { label: myCloud_LANG.new_file || 'New File', icon: myCloudSvg.newfile, act: 'newfile', show: window.myCloudActionAllowed('newfile') && !isInsideZip && !isRecycleBin },
        { label: myCloud_LANG.new_folder || 'New Folder', icon: myCloudSvg.newfolder, act: 'newfolder', show: window.myCloudActionAllowed('newfolder') && !isInsideZip && !isRecycleBin }
    ];
    
    let hasItems = false;
    actions.forEach(function(a) {
        if (!a.show) return;
        hasItems = true;
        const el = document.createElement('div');
        el.className = 'myCloudContextItem';
        el.innerHTML = '<span class="myCloudIcon" style="width:20px; height:20px; margin-right:12px; font-size:18px; display:inline-flex; align-items:center; justify-content:center;">' + a.icon + '</span> ' + a.label;
        
        el.onclick = function(evt) {
            evt.stopPropagation();
            menu.remove();
            const sp = document.getElementById('ceMenuSpacer');
            if (sp) sp.remove();
            
            if (side && st.isCommanderMode) {
                st.commanderActive = side;
                st.currentDir = dir;
            }

            switch(a.act) {
                case 'refresh':  
                    if (side && typeof refreshCommanderPane === 'function') refreshCommanderPane(side); 
                    else myCloudFetchDirectory(st.currentDir); 
                    break;
                case 'newfile':  myCloudAction_NewFile(); break;
                case 'newfolder':myCloudAction_NewFolder(); break;
            }
        };
        menu.appendChild(el);
    });

	if (hasItems) {
        document.body.appendChild(menu);
        
        // 1. Force the browser to render the element fully so we can measure it
        menu.style.position = 'fixed';
        menu.style.visibility = 'hidden';
        menu.style.display = 'block'; // Crucial: Overrides any CSS display:none
        menu.style.maxHeight = 'none';
        menu.style.overflowY = 'visible';
        
        // 2. Strip transitions/transforms temporarily to prevent scaled/shrunk height miscalculations
        const oldTrans = menu.style.transition;
        const oldTransform = menu.style.transform;
        menu.style.transition = 'none';
        menu.style.transform = 'none';
        
        // 3. Force a synchronous DOM reflow to guarantee flexbox children have expanded
        void menu.offsetHeight;
        
        const menuRect = menu.getBoundingClientRect();
        const menuWidth = menuRect.width;
        const menuHeight = menuRect.height;
        
        // Restore animations
        menu.style.transition = oldTrans;
        menu.style.transform = oldTransform;

        const source = (e.touches && e.touches.length > 0) ? e.touches[0] : e;
        let leftPos = source.clientX + 5;
        let topPos = source.clientY + 2;

        if (leftPos + menuWidth > window.innerWidth - 5) {
            leftPos = window.innerWidth - menuWidth - 5;
        }
        if (leftPos < 5) leftPos = 5;

        // 4. Bottom Collision with a larger 25px safety margin (clears bottom taskbars)
        if (topPos + menuHeight > window.innerHeight - 25) {
            topPos = window.innerHeight - menuHeight - 25;
        }
        
        if (topPos < 5) {
            topPos = 5;
            menu.style.maxHeight = (window.innerHeight - 30) + 'px';
            menu.style.overflowY = 'auto';
        }

        menu.style.left = leftPos + 'px';
        menu.style.top = topPos + 'px';

        if (typeof myCloudApplyTheme === 'function') myCloudApplyTheme();
        menu.style.visibility = 'visible';
    }
}



// Removes all open context menus from the DOM.
function myCloudCloseContextMenus() {
    document.querySelectorAll('.myCloudContextMenu').forEach(function(m) { m.remove(); });
    const tip = document.getElementById('myCloudActiveTip');
    if (tip) tip.remove();
}

// =========================================
// FAVORITES LOGIC
// =========================================

// API: Load
function myCloudLoadFavorites() {
    return fetch('', {
        method: 'POST',
        body: new URLSearchParams({
            myCloud_action: 'load_favorites',
            myCloud_key: myCloudState.key, 
            myCloud_token: myCloudCsrfToken
        })
    })
    .then(r => r.json())
    .then(resp => {
        if(resp.status === 'OK') {
            if (Array.isArray(resp.favorites)) {
                myCloudState.favorites = {};
            } else {
                myCloudState.favorites = resp.favorites || {};
            }
        }
    })
    .catch(e => console.warn("Favs load failed", e));
}

// API: Save
function myCloudSaveFavorites() {
    const fd = new URLSearchParams();
    fd.append('myCloud_action', 'save_favorites');
    fd.append('myCloud_key', myCloudState.key);
    fd.append('myCloud_token', myCloudCsrfToken);
    fd.append('favorites_json', JSON.stringify(myCloudState.favorites));
    fetch('', { method: 'POST', body: fd });
}

// API: Load Tags
function myCloudLoadTags() {
    return fetch('', {
        method: 'POST',
        body: new URLSearchParams({
            myCloud_action: 'load_tags',
            myCloud_key: myCloudState.key, 
            myCloud_token: myCloudCsrfToken
        })
    })
    .then(r => r.json())
    .then(resp => {
        if(resp.status === 'OK') {
            if (Array.isArray(resp.tags)) myCloudState.tags = {};
            else myCloudState.tags = resp.tags || {};
            
            // CRITICAL FIX: PHP json_encode turns empty assoc arrays into JSON arrays [].
            // We must ensure the specific cloud key holds an Object, not an Array, 
            // otherwise JSON.stringify will drop all string-based file paths when saving.
            Object.keys(myCloudState.tags).forEach(k => {
                if (Array.isArray(myCloudState.tags[k])) {
                    myCloudState.tags[k] = {};
                }
            });
        }
    }).catch(e => console.warn("Tags load failed", e));
}

// API: Save Tags
function myCloudSaveTags() {
    const fd = new URLSearchParams();
    fd.append('myCloud_action', 'save_tags');
    fd.append('myCloud_key', myCloudState.key);
    fd.append('myCloud_token', myCloudCsrfToken);
    fd.append('tags_json', JSON.stringify(myCloudState.tags));
    fetch('', { method: 'POST', body: fd });
}


function myCloudIsFavorite(path) {
    const st = myCloudState;
    if (!st.favorites) return false;
    const key = st.key;
    const list = st.favorites[key] || [];
    // [FIX] Check both Strings (Legacy) and Objects (New)
    return list.some(i => (typeof i === 'string' ? i : i.path) === path);
}

function myCloudToggleFavorite(path) {
    const st = myCloudState;
    if (!st.favorites) st.favorites = {};
    if (!st.favorites[st.key]) st.favorites[st.key] = [];
    
    const list = st.favorites[st.key];
    const idx = list.findIndex(i => (typeof i === 'string' ? i : i.path) === path);
    
    if (idx > -1) {
        list.splice(idx, 1);
    } else {
        // [FIX] Store isDir state when adding to prevent icon issues
        const item = st.allItems ? st.allItems.find(i => i.name === path) : null;
        const isDir = item ? (item.size === 'DIR') : (!path.includes('.'));
        list.push({ path: path, label: path.split('/').pop() || '/', isDir: isDir });
    }
    
    myCloudSaveFavorites(); 
    myCloudRenderUI(); 
}

function myCloudShowFavorites(btn, pinned) {
    if (typeof pinned === 'undefined') pinned = false;
    
    const existing = document.getElementById('myCloudFavoritesPanel');
    // Toggle Logic
    if (existing) {
        if (pinned) {
            // If clicking (pinned) and already pinned, Close.
            if (existing.dataset.pinned === 'true') myCloudCloseFloatingMenu(true); 
            else existing.dataset.pinned = 'true';
        }
        return;
    }

    myCloudCloseFloatingMenu(true); 

    const panel = document.createElement('div');
    panel.id = 'myCloudFavoritesPanel';
    panel.className = 'ce-settings-dropdown ce-floating-menu settings-mode';
    // [FIX] Widen dialog to prevent text cutting
    panel.style.width = 'auto';
    panel.style.minWidth = '200px';
    panel.style.maxWidth = '500px';
    if (pinned) panel.dataset.pinned = 'true';
    
    const curLang = (myCloudState.settings && myCloudState.settings.language) ? myCloudState.settings.language : 'en';
    panel.setAttribute('dir', ['ar', 'fa', 'he', 'ur'].includes(curLang) ? 'rtl' : 'ltr');

    // 1. Validation Logic
    const st = myCloudState;
    const list = (st.favorites && st.favorites[st.key]) ? st.favorites[st.key] : [];

    const render = () => myCloudRenderFavoritesContent(panel, btn);

    // 1. INSTANT RENDER & DISPLAY
    render();
    document.body.appendChild(panel);

    // 2. BACKGROUND VALIDATION CHECK
    if (list.length > 0) {
        // Extract paths for server check
        const pathsToCheck = list.map(i => (typeof i === 'string' ? i : i.path));

        const fd = new URLSearchParams();
        fd.append('myCloud_action', 'check_paths');
        fd.append('myCloud_key', st.key);
        fd.append('myCloud_token', myCloudCsrfToken);
        fd.append('paths', JSON.stringify(pathsToCheck));

        fetch('', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(resp => {
            if (resp.status === 'OK') {
                if (!st.favorites) st.favorites = {};
                
                // [FIX] Filter original objects based on valid paths to preserve names
                const currentList = st.favorites[st.key] || [];
                const validMap = resp.valid || {};
                
                const newList = currentList
                    .map(i => {
                        const p = typeof i === 'string' ? i : i.path;
                        const label = typeof i === 'string' || !i.label ? (p.split('/').pop() || '/') : i.label;
                        const isDir = validMap[p] !== undefined ? validMap[p] : (typeof i === 'string' ? (!p.includes('.')) : i.isDir);
                        const isMissing = !validMap.hasOwnProperty(p);
                        return { path: p, label: label, isDir: isDir, isMissing: isMissing };
                    });

                // NEVER auto-delete favorites based on connection state! Just update the icons.
                st.favorites[st.key] = newList;
                myCloudSaveFavorites(); 
                if (document.getElementById('myCloudFavoritesPanel')) render();
            }
        }).catch(() => {}); // Do nothing on error, keep cached view
    }

    // Hover Persistence
    panel.onmouseenter = function() { if(window.myCloudMenuTimer) clearTimeout(window.myCloudMenuTimer); };
    panel.onmouseleave = function() {
        if (panel.dataset.pinned === 'true') return;
        window.myCloudMenuTimer = setTimeout(() => {
            myCloudCloseFloatingMenu();
        }, 300);
    };
    
    const rect = btn.getBoundingClientRect();
    panel.style.top = (rect.bottom + 6) + 'px';
    let left = rect.left;
    const w = panel.offsetWidth;
    if (left + w > window.innerWidth) left = window.innerWidth - w - 10;
    panel.style.left = left + 'px';
	
	if (typeof myCloudApplyTheme === 'function') myCloudApplyTheme();

    setTimeout(() => {
        const closer = (e) => {
            if (!panel.contains(e.target) && !btn.contains(e.target)) {
                panel.remove();
                document.removeEventListener('click', closer);
            }
        };
        document.addEventListener('click', closer);
    }, 50);
}

function myCloudRenderFavoritesContent(panel, btnOrigin) {
    const st = myCloudState;
    // Use separate state container
    const list = (st.favorites && st.favorites[st.key]) ? st.favorites[st.key] : [];

    // 1. Build List HTML (Rows)
    // We keep string concatenation for rows for performance/simplicity, as row paths are already escaped
    let html = '<div class="ce-settings-wrapper"><div class="ce-settings-content" style="padding:0;">';
    
    if (list.length === 0) {
        html += '<div style="color:var(--text-secondary); text-align:center; padding:30px 20px;">' + myCloud_LANG.fav_empty + '</div>';
    } else {
        list.forEach((item, idx) => {
            // Handle Object vs String (Backward Compatibility)
            const path = (typeof item === 'string') ? item : item.path;
            const label = (typeof item === 'string' || !item.label) ? (path.split('/').pop() || '/') : item.label;
            
            // Icon Logic
            let iconHtml = myCloudIconFolder;
            let isDir = item.isDir;
            if (isDir === undefined) {
                const stateItem = st.allItems && st.allItems.find(x => x.name === path);
                isDir = stateItem ? (stateItem.size === 'DIR') : (!path.includes('.'));
            }
            
            if (!isDir && path !== '/') {
				const ext = path.split('.').pop().toLowerCase();
                 iconHtml = myCloudTypeIcons[ext] || myCloudTypeIcons._default;
            }

            // Path escaping for inline row handlers
            const safePath = path.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
			
            let textStyle = 'flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-weight:500; font-size:13px; margin-inline-end:12px;';
            let onClickHtml = '';
			let titleAttr = path;
            if (item.isMissing) {
                textStyle += ' color:var(--danger); font-style:italic; cursor:default;';
                const invalidMsg = (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.invalid_file) ? myCloud_LANG.invalid_file : 'Invalid';
                titleAttr = invalidMsg + ' - ' + path;
            } else {
                textStyle += ' color:var(--text-primary); cursor:pointer;';
                onClickHtml = ' onclick="myCloudGoFav(\''+safePath+'\', ' + isDir + ')"';
            }

            html += 
            '<div class="ce-fav-row">' +
                '<div class="ce-fav-icon">' + iconHtml + '</div>' +
				'<span id="ceFavLabel_'+idx+'" style="' + textStyle + '" title="' + titleAttr + '"' + onClickHtml + '>' +
				label + 
                '</span>' +
                '<div style="display:flex; gap:2px;">' +
                    '<button class="ce-fav-btn edit" onclick="myCloudRenameFav(event, '+idx+')" title="Rename"><svg viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg></button>' +
                    (list.length > 1 ? 
                        (idx > 0 ? '<button class="ce-fav-btn" onclick="myCloudMoveFav(event, '+idx+', -1)" title="Up"><svg viewBox="0 0 24 24"><path d="M7.41 15.41L12 10.83l4.59 4.58L18 14l-6-6-6 6z"/></svg></button>' : '<div style="width:28px;"></div>') +
                        (idx < list.length - 1 ? '<button class="ce-fav-btn" onclick="myCloudMoveFav(event, '+idx+', 1)" title="Down"><svg viewBox="0 0 24 24"><path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/></svg></button>' : '<div style="width:28px;"></div>')
                    : '') +
                    '<button class="ce-fav-btn delete" onclick="myCloudDeleteFav(event, \''+safePath+'\')" title="Remove"><svg viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 17.59 17.59 13.41 12 19 6.41z"/></svg></button>' +
                '</div>' +
            '</div>';
        });
    }
    html += '</div></div><div class="ce-ribbon-handle">' + myCloud_LANG.fav_title + '</div>';
    
    // 2. Set Row Content
    panel.innerHTML = html;

    // 3. Inject Footer & Add Button via DOM (Fixes "Can't Add" issue)
    const wrapper = panel.querySelector('.ce-settings-wrapper');
    const footer = document.createElement('div');
    footer.className = 'ce-settings-footer';
    
    const sel = st.selectedFiles && st.selectedFiles.length > 0 ? st.selectedFiles[0] : '';
    // Use .some() to check against both objects and strings in list
    const canAdd = sel !== '' && st.selectedFiles.length === 1 && !list.some(i => (typeof i==='string'?i:i.path) === sel);

    const btnAdd = document.createElement('button');
    btnAdd.className = 'ce-fav-add-btn';
    btnAdd.innerHTML = '<svg viewBox="0 0 24 24" style="width:16px; height:16px; fill:currentColor;"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>' + myCloud_LANG.fav_add;
    btnAdd.disabled = !canAdd;
    
    // Direct binding avoids quoting hell
    btnAdd.onclick = function(e) {
        e.stopPropagation();
        if (canAdd) {
            myCloudToggleFavorite(sel);
            // Refresh menu to show new item (pinned mode)
            myCloudShowFavorites(document.getElementById('ceFavoritesBtn'), true);
        }
    };

    footer.appendChild(btnAdd);
    wrapper.appendChild(footer);

    // --- Helper Functions attached to window for inline HTML calls ---

   window.myCloudGoFav = (path, isDirOverride) => {
        document.getElementById('myCloudFavoritesPanel').remove();
        
        const parent = path.substring(0, path.lastIndexOf('/')) || '/';
        const isFolder = typeof isDirOverride !== 'undefined' ? isDirOverride : (path === '/' || !path.includes('.'));
        const targetDir = isFolder ? path : parent;
        
        // [FIX] Commander Mode Logic
        if (myCloudState.isCommanderMode) {
            const side = myCloudState.commanderActive || 'left';
            const paneState = (side === 'left') ? myCloudState.commanderLeft : myCloudState.commanderRight;
            
            // Update Pane State
            paneState.dir = targetDir;
            paneState.selectedFiles = [];
            if (!isFolder) paneState.selectedFiles.push(path);
            paneState.visualCursorIndex = 0;
            
            // Sync Global (required for toolbar)
            myCloudState.currentDir = targetDir;
            myCloudState.selectedFiles = paneState.selectedFiles;
            
            // Fetch and Render
            myCloudFetchDirectory(targetDir, 2, true).then(() => {
                // Determine Items for this pane
                paneState.items = myCloudState.allItems.filter(i => {
                    const p = i.name.substring(0, i.name.lastIndexOf('/') || 0) || '/';
                    return p === targetDir;
                });
                
                const pane = document.querySelector(`.myCloud-commander-pane[data-side="${side}"]`);
                const content = pane.querySelector('.myCloud-commander-content');
                
                // Render
                renderCommanderContent(content, paneState, side);
                
                // If it was a file, scroll to it
                if (!isFolder) {
                    setTimeout(() => {
                        const row = content.querySelector(`.myCloudRow[data-fullpath="${CSS.escape(path)}"]`);
                        if (row) {
                            row.scrollIntoView({block: 'center'});
                            commanderSelectRow(row, path, side, {});
                        }
                    }, 50);
                }
            });
            return;
        }
        
        // [Standard Mode Logic - Unchanged]
        if (isFolder) {
             myCloudExpandToPath(path);
             myCloudFetchDirectory(path).then(() => { 
                 myCloudState.currentDir = path; 
                 myCloudRenderUI(); 
             });
        } else {
             // Assume file: Go to parent, select file
             myCloudState.currentDir = parent;
             myCloudExpandToPath(path);
             myCloudFetchDirectory(parent).then(() => myCloudSyncTableSelection(path));
        }
    };

    window.myCloudMoveFav = (e, idx, dir) => {
        e.stopPropagation(); 
        const arr = st.favorites[st.key];
        // Swap
        [arr[idx], arr[idx + dir]] = [arr[idx + dir], arr[idx]];
        myCloudSaveFavorites();
        myCloudRenderFavoritesContent(panel, btnOrigin);
    };

    window.myCloudDeleteFav = (e, path) => {
        e.stopPropagation(); 
        myCloudToggleFavorite(path); 
        myCloudRenderFavoritesContent(panel, btnOrigin); 
    };

    window.myCloudRenameFav = (e, idx) => {
        e.stopPropagation();
        const span = document.getElementById('ceFavLabel_' + idx);
        if (!span) return;

        const currentLabel = span.textContent.trim();
        const input = document.createElement('input');
        input.type = 'text';
        input.value = currentLabel;
        input.className = 'myCloudInlineInput'; 
        input.style.width = '100%';
        input.style.margin = '0';
        
        input.onclick = (ev) => ev.stopPropagation();

        const save = () => {
            const val = input.value.trim();
            if (val && val !== currentLabel) {
                const item = st.favorites[st.key][idx];
                // Ensure item is an object before setting label
                if (typeof item === 'string') {
                    st.favorites[st.key][idx] = { path: item, label: val };
                } else {
                    item.label = val;
                }
                myCloudSaveFavorites();
            }
            myCloudRenderFavoritesContent(panel, btnOrigin); 
        };

        input.onblur = save;
        input.onkeydown = (ev) => { 
            ev.stopPropagation(); 
            if(ev.key === 'Enter') { input.blur(); } 
        };
        
        span.replaceWith(input);
        input.focus();
        input.select();
    };
}

// ============================================================
// COMMANDER MODE TOGGLE
// ============================================================
function myCloudToggleCommander() {
	if (!window.myCloudActionAllowed('view_commander')) return;
    const st = myCloudState;
    const body = document.querySelector('.myCloudBody');
    const tree = document.querySelector('.myCloudTree');
    const details = document.querySelector('.myCloudDetails');
	
    const devKey = typeof myCloudGetCurrentDeviceKey === 'function' ? myCloudGetCurrentDeviceKey() : 'desktop';
    const savedPaths = (st.settings && st.settings[devKey] && st.settings[devKey].rememberLastFolder && st.lastPaths && st.lastPaths[st.key]) ? st.lastPaths[st.key] : null;
    
    if (!body) return;
    
    if (!st.isCommanderMode) {
        // --- ENTER COMMANDER MODE ---
        
        // 1. Save original sidebar size before hiding
        if (tree && tree.offsetParent !== null) {
            const isVert = window.getComputedStyle(body).flexDirection === 'column';
            st.originalSidebarSize = isVert ? tree.offsetHeight : tree.offsetWidth;
        }
        

        let initLeft = st.currentDir;
        let initRight = '/';
        if (savedPaths) {
            if (typeof savedPaths === 'string') {
                initLeft = savedPaths;
            } else {
                if (savedPaths.cmdLeft) initLeft = savedPaths.cmdLeft;
                if (savedPaths.cmdRight) initRight = savedPaths.cmdRight;
            }
        }

        // 2. Setup State
        st.commanderLeft = {
            dir: initLeft,
            selectedFiles: [],
            visualCursorIndex: 0,
            viewMode: 'list', 
            items: [],
            activeTagFilter: null
        };
        
        st.commanderRight = {
            dir: initRight,
            selectedFiles: [],
            visualCursorIndex: 0,
            viewMode: 'list',
            items: [],
            activeTagFilter: null
        };
        
        st.commanderActive = 'left';
        st.isCommanderMode = true;
        
        // 3. Render
        myCloudRenderCommander();
        
        // 4. Load both panes
        Promise.all([
            myCloudFetchDirectory(st.commanderLeft.dir, 2, true),
            myCloudFetchDirectory(st.commanderRight.dir, 2, true)
        ]).then(() => {
            if (typeof refreshCommanderPane === 'function') {
                refreshCommanderPane('left');
                refreshCommanderPane('right');
            }
        });
        
    } else {
        // --- EXIT COMMANDER MODE ---
        const activePane = st.commanderActive === 'left' ? st.commanderLeft : st.commanderRight;
        st.currentDir = activePane.dir;
        st.selectedFiles = [...activePane.selectedFiles];
        st.visualCursorIndex = activePane.visualCursorIndex;
        
        if (typeof myCloudGetEffectiveViewMode === 'function') {
            st.viewMode = myCloudGetEffectiveViewMode(st.currentDir);
        } else {
            st.viewMode = 'list';
        }
        
        // Cleanup DOM
        document.querySelectorAll('.myCloud-commander-pane, .myCloud-commander-resizer-container').forEach(el => el.remove());
        
        // Restore classes
        body.classList.remove('commander-mode');
        
        // Restore Standard UI Visibility
        if (details) details.style.display = 'flex';
        
        const config = st.settings ? st.settings[devKey] : myCloudDefaultSettings.desktop;
        myCloudTreeVisible = config.treeOpen;
        
        if (tree) {
            if (myCloudTreeVisible) {
                tree.style.display = '';
                // Show the standard sidebar resizer again
                const globalResizer = document.querySelector('.myCloudResizer');
                if(globalResizer) globalResizer.style.display = '';
                
                if (st.originalSidebarSize) {
                    const isVert = window.getComputedStyle(body).flexDirection === 'column';
                    if (isVert) {
                        tree.style.height = st.originalSidebarSize + 'px';
                        tree.style.width = '100%';
                    } else {
                        tree.style.width = st.originalSidebarSize + 'px';
                        tree.style.height = '100%';
                    }
                    tree.style.flex = 'none';
                }
            } else {
                tree.style.display = 'none';
            }
        }
        
        st.isCommanderMode = false;
        
        myCloudFetchDirectory(st.currentDir).then(() => {
            myCloudRenderUI();
            if (typeof myCloudUpdateToolbarState === 'function') myCloudUpdateToolbarState();
        });
    }
}


function myCloudAction_NewFile() {
    const st = myCloudState;
    let targetPane = document.querySelector('.myCloudTable tbody');
    if (st.isCommanderMode) {
        const side = st.commanderActive;
        const pane = document.querySelector(`.myCloud-commander-pane[data-side="${side}"]`);
        if (pane) targetPane = pane.querySelector('.myCloudTable tbody');
    }
    if (!targetPane) return; // Not supported in symbol/gallery view easily yet

    const tr = document.createElement('tr');
    tr.className = 'myCloudRow';
    tr.innerHTML = `<td></td><td class="ce-col-icon"><span class="myCloudIcon">${myCloudIconFile}</span></td><td colspan="3"><input type="text" class="myCloudInlineInput" value="new_file.txt" style="width:200px; margin:0;" id="ceNewFileInput"></td>`;
    
    targetPane.appendChild(tr);

    const inp = document.getElementById('ceNewFileInput');
    tr.scrollIntoView({block: 'nearest'});
    inp.focus();
    inp.select();

    let isProcessing = false;

    const save = () => {
        if (isProcessing) return;
        isProcessing = true;
        if (inp.dataset.cancelled === 'true') { if (tr.parentNode) tr.remove(); return; }

        const val = inp.value.trim();
        if (!val) { if (tr.parentNode) tr.remove(); return; }

        // [NEW] E2E Encryption Trap: Create encrypted empty blob instead of server-side touch
        if (typeof myCloudCrypto !== 'undefined' && myCloudCrypto.isDirEncrypted(st.currentDir)) {
            if (!myCloudCrypto.isDirUnlocked(st.currentDir)) {
                myCloudShowAlert('Error', 'Directory is locked.');
                if (tr.parentNode) tr.remove();
                return;
            }
            const emptyFile = new File([""], val, { type: "text/plain" });
            // myCloudUploadFile automatically handles encrypting the filename and the empty payload
            myCloudUploadFile(emptyFile, st.currentDir);
            if (tr.parentNode) tr.remove();
            return;
        }

        const fd = new URLSearchParams();
        fd.append('myCloud_action', 'mkfile');
        fd.append('myCloud_key', st.key);
        fd.append('myCloud_token', typeof myCloudCsrfToken !== 'undefined' ? myCloudCsrfToken : '');
        fd.append('parent', st.currentDir);
        fd.append('name', val);

        myCloudShowLoading();
        fetch(window.location.pathname, { method: 'POST', body: fd })
        .then(myCloudCheckResponse)
        .then(res => {
            myCloudHideLoading();
            if (res.status === 'OK') {
                if (st.isCommanderMode && typeof refreshCommanderPane === 'function') refreshCommanderPane(st.commanderActive);
                else myCloudFetchDirectory(st.currentDir);
            } else if (res.code === 'AUTH_REQUIRED' && typeof myCloudPromptAdminAuth === 'function') {
                myCloudPromptAdminAuth(() => { isProcessing = false; save(); });
            } else {
                myCloudShowAlert(typeof myCloud_LANG !== 'undefined' ? myCloud_LANG.error_prefix || 'Error' : 'Error', res.msg || 'Failed');
                isProcessing = false; 
                // Poll until the modal finishes its closing animation, then refocus
                const checkClosed = setInterval(() => {
                    const overlay = document.getElementById('myCloudModalOverlay');
                    if (!overlay || overlay.style.display === 'none') {
                        clearInterval(checkClosed);
                        inp.focus();
                    }
                }, 100);
            }
        }).catch(() => {
            myCloudHideLoading();
            isProcessing = false; 
            inp.focus();
        });
    };

    inp.onblur = () => {
        // Ignore blur events caused by opening the error modal
        const overlay = document.getElementById('myCloudModalOverlay');
        if (overlay && overlay.style.display !== 'none') return;
        save();
    };
    inp.onkeydown = (e) => {
        e.stopPropagation();
        if (e.key === 'Enter') { e.preventDefault(); inp.blur(); } 
        if (e.key === 'Escape') { e.preventDefault(); inp.dataset.cancelled = 'true'; inp.blur(); }
    };
}


// Smart Edit Handler: Spawns a timestamped copy if the file is a template
window.myCloudAction_EditFile = function(path) {
    const st = myCloudState;
    const filename = path.split('/').pop();
    const ext = filename.split('.').pop().toLowerCase();
    const baseName = filename.substring(0, filename.lastIndexOf('.')) || filename;

    // 1. Native Office Template Extensions mapping to their standard document extensions
    const templateExtMap = {
        'dotx': 'docx', 'dotm': 'docm', 'dot': 'doc',
        'xltx': 'xlsx', 'xltm': 'xlsm', 'xlt': 'xls',
        'potx': 'pptx', 'potm': 'pptm', 'pot': 'ppt',
        'ott': 'odt', 'ots': 'ods', 'otp': 'odp'
    };

    // 2. Multilingual fallback keywords
    const templateKeywords = [
        'template', 'vorlage', 'voalog', 'vorlaach', 'virlag', 
        'plantilla', 'modèle', 'modele', 'modello', 'modelo',  
        'шаблон', 'şablon', 'sablon',                          
        'قالب', 'الگو', '模板', 'テンプレート', '템플릿',      
        'mẫu', 'टेम्पलेट', 'खाका'                               
    ];

    const isNativeTemplate = templateExtMap.hasOwnProperty(ext);
    const isKeywordTemplate = templateKeywords.some(kw => baseName.toLowerCase().startsWith(kw));

    if (isNativeTemplate || isKeywordTemplate) {
        
        // If it's a native template (.dotx), the new file must be a standard document (.docx). 
        // If it's a keyword template (Vorlage.docx), it just keeps its current extension (.docx).
        const targetExt = isNativeTemplate ? templateExtMap[ext] : ext;

        // Generate YYYY-MM-DD HH-MM
        const now = new Date();
        const yyyy = now.getFullYear();
        const mm = String(now.getMonth() + 1).padStart(2, '0');
        const dd = String(now.getDate()).padStart(2, '0');
        const hh = String(now.getHours()).padStart(2, '0');
        const min = String(now.getMinutes()).padStart(2, '0');
        
        const defaultName = `${yyyy}-${mm}-${dd} ${hh}-${min}.${targetExt}`;
        const parentDir = path.substring(0, path.lastIndexOf('/')) || '/';
        
        const titleLbl = (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.new_name) ? myCloud_LANG.new_name : 'New Name:';

        // Trigger user input modal with selectBaseOnly = true
        myCloudShowInputModal(titleLbl, titleLbl, defaultName, function(userInput) {
            // Basic client-side sanitization (server handles strict sanitization)
            let safeName = userInput.replace(/[\/\\]/g, '').trim();
            if (!safeName) safeName = defaultName;
            
            // Ensure the target extension is preserved
            if (!safeName.toLowerCase().endsWith('.' + targetExt)) {
                safeName += '.' + targetExt;
            }

            const executeCopy = (finalName) => {
                myCloudShowLoading();
                const fd = new URLSearchParams();
                fd.append('myCloud_action', 'copy_as');
                fd.append('myCloud_key', st.key);
                fd.append('myCloud_token', typeof myCloudCsrfToken !== 'undefined' ? myCloudCsrfToken : '');
                fd.append('src', path);
                fd.append('destName', finalName);

                fetch('', { method: 'POST', body: fd }).then(r=>r.json()).then(res => {
                    myCloudHideLoading();
                    if (res.status === 'OK') {
                        // Refresh folder to show the new file, then open it in the editor
                        myCloudFetchDirectory(parentDir).then(() => {
                            const newItem = st.allItems.find(i => i.name === res.newPath);
                            if (newItem && typeof myCloudHandleEnterAction === 'function') {
                                // Make sure we select the newly created file visually
                                st.selectedFiles = [newItem.name];
                                myCloudHandleEnterAction(newItem, targetExt, false);
                            }
                        });
                    } else {
                        myCloudShowAlert('Error', res.msg || 'Template creation failed.');
                    }
                }).catch(() => {
                    myCloudHideLoading();
                    myCloudShowAlert('Error', 'Network error.');
                });
            };

            const newPath = (parentDir === '/' ? '' : parentDir) + '/' + safeName;
            
            // Local collision check
            if (st.allItems.some(i => i.name === newPath)) {
                const collisionMsg = (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.collision_ask) ? myCloud_LANG.collision_ask : 'Duplicate names detected. Resolve automatically?';
                
                myCloudShowAlert(titleLbl, collisionMsg, function() {
                    // YES: Auto-resolve with a random integer
                    if (typeof myCloudCloseModal === 'function') myCloudCloseModal();
                    
                    const nameNoExt = safeName.substring(0, safeName.lastIndexOf('.'));
                    executeCopy(nameNoExt + ' (' + Math.floor(Math.random() * 1000) + ').' + targetExt);
                });
            } else {
                executeCopy(safeName);
            }
        }, true); // <-- The true flag triggers extension ignoring

        return; // Stop execution here so we don't open the template directly
    }

    // Standard edit logic for all other files
    const item = st.allItems.find(i => i.name === path);
    if (item && typeof myCloudHandleEnterAction === 'function') {
        myCloudHandleEnterAction(item, ext, false);
    }
};


window.myCloudAction_FixEncryption = async function(path, isDir) {
    const root = typeof myCloudCrypto !== 'undefined' ? myCloudCrypto.getCryptoRoot(path) : null;
    if (!root) return;
    
    // Ensure vault is unlocked before proceeding
    if (!myCloudCrypto.isDirUnlocked(root)) {
        myCloudAction_EncryptPrompt(root, true, () => {
            window.myCloudAction_FixEncryption(path, isDir);
        });
        return;
    }
    
    myCloudShowLoading();
    try {
        const filename = path.split('/').pop();
        const fileParent = path.substring(0, path.lastIndexOf('/')) || '/';
        const encName = await myCloudCrypto.encryptName(root, filename);
        
        // Strip out all query parameters for all internal POST requests
        const reqUrl = window.location.pathname;
        
        if (isDir) {
            const fd = new URLSearchParams({ myCloud_action: 'rename', myCloud_key: myCloudState.key, myCloud_token: typeof myCloudCsrfToken !== 'undefined' ? myCloudCsrfToken : '', src: path, newName: encName });
            const res = await fetch(reqUrl, { method: 'POST', body: fd }).then(r => r.json());
            if (res.status !== 'OK') throw new Error("Rename failed: " + (res.msg || 'Unknown error'));
            
            const newPath = res.newPath || ((fileParent === '/' ? '' : fileParent) + '/' + encName);
            await myCloudMigrateDirectory(newPath, true);
        } else {
            // 1. Get Token
            const dlFd = new URLSearchParams({
                myCloud_action: 'get_download_token',
                myCloud_key: myCloudState.key,
                myCloud_token: typeof myCloudCsrfToken !== 'undefined' ? myCloudCsrfToken : '',
                path: path,
                filename: filename,
                preview: '0'
            });
            const tokenRes = await fetch(reqUrl, { method: 'POST', body: dlFd }).then(r => r.json());
            if (tokenRes.status !== 'OK') throw new Error("Could not get download token: " + (tokenRes.msg || 'Unknown error'));
            
            // 2. STRICTLY CLEAN URL: Only the pathname and the specific token. NO auth/login params.
            const dlUrl = reqUrl + '?myCloud_token=' + tokenRes.token;
            
            // 3. Download Blob
            const r2 = await fetch(dlUrl);
            if (!r2.ok) throw new Error("File download failed (HTTP " + r2.status + ")");
            const blob = await r2.blob();
            
            // 4. Encrypt Blob & Filename
            const plainFileObj = new File([blob], filename, { type: blob.type });
            const encBlob = await myCloudCrypto.encryptFile(root, plainFileObj);
            
            // 5. Upload Encrypted Version
            const upFd = new FormData();
            upFd.append('myCloud_action', 'upload');
            upFd.append('dir', fileParent);
            upFd.append('myCloud_key', myCloudState.key);
            upFd.append('myCloud_token', typeof myCloudCsrfToken !== 'undefined' ? myCloudCsrfToken : '');
            upFd.append('file', encBlob, encName);
            
            const upRes = await fetch(reqUrl, { method: 'POST', body: upFd }).then(r => r.json());
            if (upRes.status !== 'OK') throw new Error("Upload failed: " + (upRes.msg || 'Unknown error'));
            
            // 6. Permanently delete the plaintext original
            const delFd = new URLSearchParams({
                myCloud_action: 'delete',
                myCloud_key: myCloudState.key,
                myCloud_token: typeof myCloudCsrfToken !== 'undefined' ? myCloudCsrfToken : '',
                src: path,
                permanent: 'true'
            });
            const delRes = await fetch(reqUrl, { method: 'POST', body: delFd }).then(r => r.json());
            if (delRes.status !== 'OK') throw new Error("Delete original failed: " + (delRes.msg || 'Unknown error'));
        }
        
        myCloudFetchDirectory(myCloudState.currentDir);
    } catch (e) {
        myCloudShowAlert('Error', 'Failed to fix encryption: ' + e.message);
    }
    myCloudHideLoading();
};

window.myCloudAction_ChangeVaultPassword = function(dirPath) {
    const root = typeof myCloudCrypto !== 'undefined' ? myCloudCrypto.getCryptoRoot(dirPath) : null;
    if (!root) return;

    // Ensure vault is unlocked in RAM so we have the DEK available
    if (!myCloudCrypto.isDirUnlocked(root)) {
        myCloudAction_EncryptPrompt(root, true, () => {
            window.myCloudAction_ChangeVaultPassword(dirPath);
        });
        return;
    }

    const header = myCloud_LANG.change_vault_pwd || 'Change Vault Password';
    const label = myCloud_LANG.enter_new_vault_pwd || 'Enter New Encryption Password:';

    myCloudShowPasswordModal(header, label, async function(newPassword) {
        myCloudShowLoading();
        try {
            await myCloudCrypto.changeVaultPassword(root, newPassword);
            myCloudHideLoading();
            myCloudShowAlert(myCloud_LANG.success || 'Success', myCloud_LANG.pwd_success || 'Vault password changed successfully.');
        } catch(e) {
            myCloudHideLoading();
            myCloudShowAlert(myCloud_LANG.error_prefix || 'Error', 'Failed to change password: ' + e.message);
        }
    }, null, true);
};


// ============================================================
// GLOBAL TEMPLATE INTERCEPTOR (Catches Double-Clicks & Enter Key)
// Every edit of a template file results in it being copied and the copy being edited.
// Supports native template extensions (.dotx) OR localized keyword prefixes (Vorlage_*.docx)
// ============================================================
setTimeout(function() {
    if (typeof window.myCloudHandleEnterAction === 'function' && !window.ceTemplateGuardPatched) {
        window.ceTemplateGuardPatched = true;
        const origEnterAction = window.myCloudHandleEnterAction;
        
        const templateExts = ['dotx', 'dotm', 'dot', 'xltx', 'xltm', 'xlt', 'potx', 'potm', 'pot', 'ott', 'ots', 'otp'];
        const officeExts = ['docx', 'xlsx', 'pptx', 'doc', 'xls', 'ppt', 'odt', 'ods', 'odp', 'rtf', 'csv'];
        
        // Comprehensive multi-lingual template keywords covering all 20 supported languages and dialects
        const templateKeywords = [
            'template', 'vorlage', 'voalog', 'vorlaach', 'virlag', // EN, DE, BAR, HES, LB, PCM
            'plantilla', 'modèle', 'modele', 'modello', 'modelo',  // ES, FR, IT, PT
            'шаблон', 'şablon', 'sablon',                          // RU, UK, TR
            'قالب', 'الگو', '模板', 'テンプレート', '템플릿',      // AR, FA, ZH-CN, JA, KO
            'mẫu', 'टेम्पलेट', 'खाका'                               // VI, HI
        ];
        
        window.myCloudHandleEnterAction = function(item, ext, isEnter) {
            const filename = item.name.split('/').pop();
            const baseName = filename.substring(0, filename.lastIndexOf('.')) || filename;
            const lowerBase = baseName.toLowerCase();
            
            const isNativeTemplate = templateExts.includes(ext);
            const isKeywordTemplate = officeExts.includes(ext) && templateKeywords.some(kw => lowerBase.startsWith(kw));
            
            // If they double-clicked a template, reroute to the smart copy function
            if (isNativeTemplate || isKeywordTemplate) {
                window.myCloudAction_EditFile(item.name);
                return;
            }
            
            // Otherwise, open the file normally
            origEnterAction.apply(this, arguments);
        };
    }
}, 100);

</script>