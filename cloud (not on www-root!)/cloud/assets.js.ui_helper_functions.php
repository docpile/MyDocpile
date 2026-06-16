<?php
/**
 * ============================================================================
 * MODULE: Global UI & Modal Helpers
 * ============================================================================
 * Provides reusable frontend JavaScript definitions for generating alert dialogues, 
 * confirmation modals, password prompts, and generic DOM manipulation utilities.
 * NOTE: Executed exclusively by the client browser.
 * 
 * THIS IS PART OF THE PSEUDO "JS FILE" - SO THE NO PHP CODE IN THIS FILE!
 */
 
?><script>
 
<script>

function updateToggleUI(btn, targetIsPreview) {

    btn.dataset.quality = targetIsPreview ? 'preview' : 'raw';
    btn.style.background = targetIsPreview ? 'rgba(0, 0, 0, 0.4)' : 'rgba(0, 120, 212, 0.9)';
    btn.innerHTML = targetIsPreview ? myCloudSvg.hdIcon : myCloudSvg.sdIcon;
	btn.title = targetIsPreview ? myCloud_LANG.switch_hd : myCloud_LANG.switch_sd;
}


// Helper to reset the modal to a clean state prevents "New Folder" inheriting "Search" size
function myCloudResetModal() {
    const modal = document.getElementById('myCloudModal');
    if (!modal) return;
    
    // 1. Remove all specific classes (keep base myCloudModal)
    modal.className = 'myCloudModal'; 
    
    // 2. CRITICAL: Wipe inline styles set by Search/Properties (width, height, maxWidth)
    modal.removeAttribute('style');
	modal.onkeydown = null;
}

// Helper function for HTML escaping
function myCloudEscapeHtml(str) {
    return String(str).replace(/&/g, "&amp;")
                     .replace(/</g, "&lt;")
                     .replace(/>/g, "&gt;")
                     .replace(/"/g, "&quot;")
                     .replace(/'/g, "&#039;");
}

// Replace the complete myCloudShowInputModal routine:

// Replace the complete myCloudShowInputModal routine in ui_helpers.php:

function myCloudShowInputModal(header, labelText, initialValue, onConfirm, selectBaseOnly = false) {
    const overlay = document.getElementById('myCloudModalOverlay');
    const modal = document.getElementById('myCloudModal');
    myCloudResetModal();
    safeHeader = myCloudEscapeHtml(header);
    overlay.style.display = 'flex';
	overlay.style.zIndex = '85000';
    modal.className = 'myCloudModal'; 
    
    modal.innerHTML = 
        '<div class="myCloudModalHeader">' + safeHeader + '</div>' +
        '<div class="myCloudModalBody" style="padding: 20px;">' +
            '<label style="display:block; margin-bottom:8px; font-weight:600; font-size:13px; color:var(--text-primary);">' + labelText + '</label>' +
            '<input type="text" id="ceModalInput" class="myCloudInlineInput" value="' + initialValue + '" style="width:100%; box-sizing:border-box; padding:8px 10px; font-size:14px; margin:0;">' +
            '<div class="myCloudButtons" style="justify-content: flex-end; margin-top:20px;">' +
                '<button onclick="myCloudCloseModal()" style="margin-right:10px;">' + myCloud_LANG.cancel + '</button>' +
                '<button id="ceModalBtnSave" style="background:var(--accent-primary); color:#fff; border:none;">' + myCloud_LANG.ok + '</button>' +
            '</div>' +
        '</div>';

    const input = document.getElementById('ceModalInput');
    const btn = document.getElementById('ceModalBtnSave');
    
    // UX: Focus input and select text smartly
    input.focus();
    if (initialValue) {
        if (selectBaseOnly) {
            const lastDot = initialValue.lastIndexOf('.');
            if (lastDot > 0) {
                input.setSelectionRange(0, lastDot);
            } else {
                input.select();
            }
        } else {
            input.select();
        }
    }

    const submit = () => {
        const val = input.value.trim();
        // FIX: Always trigger the confirm callback as long as the input is not blank
        if (val) {
            document.getElementById('myCloudModalOverlay').style.display = 'none';
            onConfirm(val);
        }
    };

   btn.onclick = submit;
    input.onkeydown = (e) => { 
        if (e.key === 'Enter') { 
            e.preventDefault(); 
            submit(); 
        } else if (e.key === 'Escape') {
            e.preventDefault();
            myCloudCloseModal();
        }
    };
    // Ensure focus allows Escape to work globally on modal
    modal.setAttribute('tabindex', '-1');
    modal.onkeydown = (e) => {
        if (e.key === 'Escape') {
             e.preventDefault();
             myCloudCloseModal();
        }
    };
}

/// Password-specific modal with visibility toggle for Encryption 
function myCloudShowPasswordModal(header, labelText, onConfirm, onCancel, isNewPassword = false) {
    const overlay = document.getElementById('myCloudModalOverlay');
    const modal = document.getElementById('myCloudModal');
    myCloudResetModal();
    const safeHeader = myCloudEscapeHtml(header);
    
    overlay.style.display = 'flex';
    overlay.style.zIndex = '85000';
    modal.className = 'myCloudModal'; 
    
    // Wrapped the body in a <form onsubmit="return false;"> and explicitly set button types
    modal.innerHTML = 
        '<div class="myCloudModalHeader">' + safeHeader + '</div>' +
        '<form class="myCloudModalBody" onsubmit="return false;" style="padding: 20px; margin: 0; display: block;">' +
            '<label style="display:block; margin-bottom:8px; font-weight:600; font-size:13px; color:var(--text-primary);">' + labelText + '</label>' +
            '<div style="position:relative; display:flex; align-items:center; width:100%;">' +
                '<input type="password" autocomplete="one-time-code" id="ceModalPwdInput" class="myCloudInlineInput" style="width:100%; box-sizing:border-box; padding:8px 10px; padding-inline-end:32px; font-size:14px; margin:0;">' +
                '<button type="button" tabindex="-1" onclick="ce_toggle_my_pwd_modal(this)" style="position:absolute; inset-inline-end:4px; background:none; border:none; cursor:pointer; color:var(--text-secondary); padding:4px; display:flex; align-items:center; justify-content:center; transition:color 0.2s;" title="' + (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.toggle_pwd_vis ? myCloud_LANG.toggle_pwd_vis : 'Toggle visibility') + '">' +
                    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px; height:16px;"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>' +
                '</button>' +
            '</div>' +
            '<div class="myCloudButtons" style="justify-content: flex-end; margin-top:20px;">' +
                '<button type="button" id="ceModalBtnCancel" style="margin-right:10px;">' + (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.cancel ? myCloud_LANG.cancel : 'Cancel') + '</button>' +
                '<button type="submit" id="ceModalBtnSave" style="background:var(--accent-primary); color:#fff; border:none;">' + (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.ok ? myCloud_LANG.ok : 'OK') + '</button>' +
            '</div>' +
        '</form>';

    const input = document.getElementById('ceModalPwdInput');
    const btnSave = document.getElementById('ceModalBtnSave');
    const btnCancel = document.getElementById('ceModalBtnCancel');
    
    window.ce_toggle_my_pwd_modal = function(btn) {
        const svg = btn.querySelector('svg');
        if (input.type === 'password') {
            input.type = 'text';
            const cInp = document.getElementById('ceModalPwdConfirmInput');
            if (cInp) cInp.type = 'text';
            btn.style.color = 'var(--accent-primary)';
            svg.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>';
        } else {
            input.type = 'password';
            const cInp = document.getElementById('ceModalPwdConfirmInput');
            if (cInp) cInp.type = 'password';
            btn.style.color = 'var(--text-secondary)';
            svg.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>';
        }
    };

    input.focus();

    const submit = async () => {
        const val = input.value; 
        if (!val) return;

        if (isNewPassword) {
            const origText = btnSave.innerText;
            const confirmWrap = document.getElementById('ceModalPwdConfirmWrap');
            
            // 1. Initial Validation Phase
            if (!confirmWrap) {
                if (typeof window.ce_validate_my_pwd === 'function') {
                    const vErrs = window.ce_validate_my_pwd(val);
                    if (vErrs.length > 0) {
                        myCloudShowAlert(typeof myCloud_LANG !== 'undefined' && myCloud_LANG.pwd_sec_policy ? myCloud_LANG.pwd_sec_policy : 'Security Policy', (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.pwd_policy_fail ? myCloud_LANG.pwd_policy_fail : 'Password policy not met:') + '<br>' + vErrs.join('<br>'));
                        return;
                    }
                }
                
                if (typeof window.ce_is_my_pwd_pwned === 'function') {
                    btnSave.disabled = true;
                    btnSave.innerText = typeof myCloud_LANG !== 'undefined' && myCloud_LANG.pwd_checking ? myCloud_LANG.pwd_checking : "Checking...";
                    const isPwned = await window.ce_is_my_pwd_pwned(val);
                    if (isPwned) {
                        btnSave.disabled = false;
                        btnSave.innerText = origText;
                        myCloudShowAlert(typeof myCloud_LANG !== 'undefined' && myCloud_LANG.pwd_sec_warn ? myCloud_LANG.pwd_sec_warn : 'Security Warning', typeof myCloud_LANG !== 'undefined' && myCloud_LANG.pwd_pwned_msg ? myCloud_LANG.pwd_pwned_msg : 'This password has been exposed in a data breach.<br><br>Please choose a different, more secure password.');
                        return;
                    }
                    btnSave.disabled = false;
                    btnSave.innerText = origText;
                }
            }

            // 2. Masked Input Confirmation Phase
            if (input.type === 'password') {
                if (!confirmWrap) {
                    const wrap = document.createElement('div');
                    wrap.id = 'ceModalPwdConfirmWrap';
                    wrap.style.marginTop = '15px';
                    wrap.innerHTML = '<label style="display:block; margin-bottom:8px; font-weight:600; font-size:13px; color:var(--text-primary);">' + (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.confirm_pwd ? myCloud_LANG.confirm_pwd : 'Confirm Password:') + '</label>' +
                                     '<input type="password" id="ceModalPwdConfirmInput" class="myCloudInlineInput" style="width:100%; box-sizing:border-box; padding:8px 10px; font-size:14px; margin:0;">';
                    const buttons = modal.querySelector('.myCloudButtons');
                    buttons.parentNode.insertBefore(wrap, buttons);
                    
                    const confirmInput = document.getElementById('ceModalPwdConfirmInput');
                    confirmInput.focus();
                    confirmInput.onkeydown = (e) => { 
                        if (e.key === 'Enter') { e.preventDefault(); submit(); } 
                        else if (e.key === 'Escape') { e.preventDefault(); cancel(); }
                    };
                    btnSave.innerText = typeof myCloud_LANG !== 'undefined' && myCloud_LANG.confirm ? myCloud_LANG.confirm : 'Confirm';
                    return; // Pause sequence until user confirms
                } else {
                    const confirmInput = document.getElementById('ceModalPwdConfirmInput');
                    if (val !== confirmInput.value) {
                        myCloudShowAlert(typeof myCloud_LANG !== 'undefined' && myCloud_LANG.error_prefix ? myCloud_LANG.error_prefix : 'Error', typeof myCloud_LANG !== 'undefined' && myCloud_LANG.pwd_mismatch ? myCloud_LANG.pwd_mismatch : 'Passwords do not match. Please try again.');
                        confirmInput.value = '';
                        confirmInput.focus();
                        return;
                    }
                }
            }
        }

        // 3. Execution Phase
        document.getElementById('myCloudModalOverlay').style.display = 'none';
        if (onConfirm) onConfirm(val);
    };

    const cancel = () => {
        document.getElementById('myCloudModalOverlay').style.display = 'none';
        if (onCancel) onCancel();
    };

    btnSave.onclick = submit;
    btnCancel.onclick = cancel;
    
    input.onkeydown = (e) => { 
        if (e.key === 'Enter') { e.preventDefault(); submit(); } 
        else if (e.key === 'Escape') { e.preventDefault(); cancel(); }
    };

    modal.setAttribute('tabindex', '-1');
    modal.onkeydown = (e) => {
        if (e.key === 'Escape') { e.preventDefault(); cancel(); }
    };
}



window.myCloudCloseAlert = function() {
    const overlay = document.getElementById('myCloudAlertOverlay');
    const modal = document.getElementById('myCloudAlertBox');
    if (!overlay) return;

    overlay.classList.add('closing');
    if (modal) modal.classList.add('closing');

    if (overlay.closeTimer) clearTimeout(overlay.closeTimer);
    overlay.closeTimer = setTimeout(() => {
		if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
		overlay.closeTimer = null;
    }, 680);
};

function myCloudShowAlert(title, message, onConfirm = null) {
    let overlay = document.getElementById('myCloudAlertOverlay');
    let modal = document.getElementById('myCloudAlertBox');

    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'myCloudAlertOverlay';
        overlay.className = 'myCloudOverlay';
        overlay.style.zIndex = '100000';
        
        modal = document.createElement('div');
        modal.id = 'myCloudAlertBox';
        modal.className = 'myCloudModal';
        
        overlay.appendChild(modal);
        document.body.appendChild(overlay);
    } else {
        if (overlay.closeTimer) {
           clearTimeout(overlay.closeTimer);
            overlay.closeTimer = null;
        }
    }

    const displayTitle = title || (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.error_prefix ? myCloud_LANG.error_prefix : 'Error');
    const safetitle = typeof myCloudEscapeHtml === 'function' ? myCloudEscapeHtml(displayTitle) : displayTitle;
    
    // [FIX] Stop any active closing animations immediately to prevent disappearance
    overlay.classList.remove('closing');
    if (modal) {
        modal.classList.remove('closing');
        modal.className = 'myCloudModal'; // Reset styling
        if (onConfirm) modal.classList.add('conflict'); // Optional styling for questions
    }
    
    overlay.style.display = 'flex';
    
    // [NEW] Reset any previous inline key handlers to prevent zombies
    modal.onkeydown = null;
    
    // Build Buttons
    let buttonsHtml = '<button type="button" onclick="window.myCloudCloseAlert()" style="background:var(--accent-primary); color:#fff; min-width:80px;">OK</button>';
     
    if (onConfirm) {
        buttonsHtml = 
            '<button type="button" id="ceAlertConfirm" style="background:#e81123; color:#fff; min-width:80px; margin-right:10px;">' + (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.yes ? myCloud_LANG.yes : 'Yes') + '</button>' +
            '<button type="button" onclick="window.myCloudCloseAlert()" style="min-width:80px;">' + (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.cancel ? myCloud_LANG.cancel : 'Cancel') + '</button>';
     }
 

    modal.innerHTML = 
        '<div class="myCloudModalHeader" style="justify-content:space-between; align-items:center; ' + (onConfirm ? 'border-bottom:none; padding-bottom:0;' : '') + '">' +
            '<span style="display:flex; align-items:center;">' + (typeof myCloudSvgLogo !== 'undefined' ? myCloudSvgLogo : '') + '&nbsp;' + safetitle + '</span>' +
            '<span class="myCloudClose" onclick="window.myCloudCloseAlert()" style="cursor:pointer; color:var(--text-secondary); font-size:18px; line-height:1;">✕</span>' +
        '</div>' +
        '<div class="myCloudModalBody" style="padding: 24px; text-align:center;">' +
            '<div style="margin-bottom:24px; font-size:14px; color:var(--text-primary); line-height:1.5;">' + message + '</div>' +
            '<div class="myCloudButtons" style="justify-content: center;">' + buttonsHtml + '</div>' +
        '</div>';

    // Bind Confirm Button
    if (onConfirm) {
        const btn = document.getElementById('ceAlertConfirm');
        btn.onclick = () => {
            window.myCloudCloseAlert();
            onConfirm();
        };
        setTimeout(() => btn.focus(), 50);
        
        // [NEW] Add Escape AND Enter handlers for Confirmation dialogs
        modal.setAttribute('tabindex', '-1');
        modal.onkeydown = (e) => {
            if (e.key === 'Escape') {
                e.preventDefault();
                e.stopPropagation();
                window.myCloudCloseAlert();
            }
            if (e.key === 'Enter') {
                e.preventDefault();
                e.stopPropagation();
                btn.click();
            }
        };
    } else {
        setTimeout(() => { const b = modal.querySelector('button'); if(b) b.focus(); }, 50);
        // [NEW] Default Alert: Enter/Esc both close
        modal.setAttribute('tabindex', '-1');
        modal.onkeydown = (e) => {
            if (e.key === 'Escape' || e.key === 'Enter') {
                e.preventDefault();
                e.stopPropagation();
                window.myCloudCloseAlert();
            }
        };
    }

    // Apply Theme to the newly generated element
    if (typeof myCloudApplyTheme === 'function') myCloudApplyTheme();
}


// [NEW] Toggle Tree Function
function myCloudToggleTree() {
    const tree = document.querySelector('.myCloudTree');
    const resizer = document.querySelector('.myCloudResizer');
    const btn = document.getElementById('btnToggleTree');

    myCloudTreeVisible = !myCloudTreeVisible;

    if (myCloudTreeVisible) {
        // [IMPORTANT] Clear inline styles so CSS classes control the layout (Top vs Left)
        tree.style.display = ''; 
        myCloudRestoreSidebarSize();
        
        if (resizer) resizer.style.display = '';
        if (btn) {
            btn.classList.add('tree-on');
            btn.classList.remove('tree-off');
        }
    } else {
        tree.style.display = 'none';
        if (resizer) resizer.style.display = 'none';
        if (btn) {
            btn.classList.add('tree-off');
            btn.classList.remove('tree-on');
        }
    }
}

// Universal SVG Paths (24x24 viewBox)


function myCloudToggleFontSize(specificLevel = null) {
    // Cycle through 6 levels (0 to 5)
	if (specificLevel !== null) {
        myCloudState.fontLevel = parseInt(specificLevel);
    } else {
        myCloudState.fontLevel = (myCloudState.fontLevel + 1) % 6;
    }
	
	if (myCloudState.fontLevel < 0) myCloudState.fontLevel = 0;
    if (myCloudState.fontLevel > 5) myCloudState.fontLevel = 5;
    
    const containers = [
        document.documentElement,
        document.body,
        document.getElementById('myCloudContainer'),
        document.getElementById('myCloudModalOverlay'),
        document.getElementById('myCloudPreviewOverlay'),
        document.getElementById('myCloudFloatingMenu'),
        document.getElementById('myCloudContextMenu'),
        document.getElementById('myCloudPaletteOverlay'),
        document.getElementById('myCloudAlertOverlay')
    ];

    // Define the 6 steps with smaller increments
    const levels = [
        { size: '14px', row: '30px', tree: '28px', hover: '16px', toggle: '11px' }, // Level 0 (Smallest)
        { size: '15px', row: '32px', tree: '30px', hover: '17px', toggle: '12px' }, // Level 1 (Default)
        { size: '17px', row: '36px', tree: '33px', hover: '19px', toggle: '14px' }, // Level 2
        { size: '19px', row: '40px', tree: '36px', hover: '21px', toggle: '16px' }, // Level 3
        { size: '21px', row: '44px', tree: '39px', hover: '23px', toggle: '18px' }, // Level 4
        { size: '23px', row: '48px', tree: '42px', hover: '25px', toggle: '20px' }  // Level 5 (Largest)
    ];

    const config = levels[myCloudState.fontLevel];

    containers.forEach(c => {
        if (!c) return;
        c.style.setProperty('--font-size-base', config.size);
        c.style.setProperty('--row-height', config.row);
        c.style.setProperty('--tree-row-height', config.tree);
        c.style.setProperty('--hover-font-size', config.hover);
        c.style.setProperty('--toggle-size', config.toggle);
    });
}


window.myCloudShowLoading = function() {
    if (document.getElementById('myCloudLoadingPopup')) return;
    
    const div = document.createElement('div');
    div.id = 'myCloudLoadingPopup';
    div.className = 'myCloudLoadingPopup';
    
    // Added flex-shrink:0, min-width, and white-space:nowrap to perfectly lock the layout
    div.innerHTML = 
        '<div style="display:flex; align-items:center; justify-content:center; gap:12px; margin:0; padding:0;">' +
            '<div class="myCloud-spinner" style="width:16px; height:16px; min-width:16px; min-height:16px; flex-shrink:0; border-width:2px; border-color:rgba(0,120,212,0.25); border-top-color:var(--accent-primary); box-sizing:border-box; margin:0; padding:0;"></div>' +
            '<span style="font-size:13px; font-weight:600; letter-spacing:0.3px; line-height:1; margin:0; padding:0; white-space:nowrap;">' + (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.loading ? myCloud_LANG.loading : 'Loading...') + '</span>' +
        '</div>';
        
    document.body.appendChild(div);
    
    // Force reflow to trigger the CSS transition
    void div.offsetWidth;
    div.classList.add('visible');
};

function myCloudHideLoading() {
  const div = document.getElementById('myCloudLoadingPopup');
  if (!div) return;
  div.classList.remove('visible');
  div.classList.add('hide');
  // Ensure removal happens even if transitionend misses (e.g. tab inactive)
  const removeFn = () => { if(div.parentNode) div.remove(); };
  div.addEventListener('transitionend', removeFn, { once: true });
  setTimeout(removeFn, 350);
}


// Applies the saved sidebar size to the DOM
function myCloudRestoreSidebarSize() {
    const key = myCloudGetCurrentDeviceKey();
    if (!myCloudState.settings || !myCloudState.settings[key]) return;
    
    // Get saved size or fall back to default from config
    let savedSize = parseInt(myCloudState.settings[key].sidebarSize);
    if (!savedSize || isNaN(savedSize)) {
        savedSize = myCloudDefaultSettings[key].sidebarSize || 250;
    }

    const tree = document.querySelector('.myCloudTree');
    const container = document.querySelector('.myCloudBody');
    if (!tree || !container) return;

    // Determine layout direction
    const isVertical = window.getComputedStyle(container).flexDirection === 'column';
    const totalSpace = isVertical ? container.offsetHeight : container.offsetWidth;

    // CRITICAL FIX: Only clamp if the container is actually visible (size > 0)
    // If invisible, trust the saved value.
    let finalSize = savedSize;
    if (totalSpace > 0) {
        finalSize = Math.min(savedSize, totalSpace * 0.6);
        // Ensure a minimum usable size (e.g. 50px) to prevent total collapse
        if (finalSize < 50) finalSize = 50; 
    }

    if (isVertical) {
        tree.style.height = finalSize + 'px';
        tree.style.width = '100%';
    } else {
        tree.style.width = finalSize + 'px';
        tree.style.height = '100%';
    }
    tree.style.flex = 'none'; 
}



// [NEW] Helper to Render the Cloud Buttons
function myCloudRenderCloudSwitcher() {
    const bar = document.getElementById('myCloudCloudSwitcher');
    if (!bar) return;
    
    // Safety check: Don't render if data is missing or 0 keys exist
    if (typeof myCloudUserKeys === 'undefined' || !Array.isArray(myCloudUserKeys) || myCloudUserKeys.length === 0) {
	bar.style.display = 'none';
        return;
    }

    // Clear existing to avoid duplicates
    bar.innerHTML = '';
	
    // Render Logo
    const logoWrap = document.createElement('div');
    logoWrap.className = 'myCloud-switcher-logo';
    // Bypass CSS cache entirely using inline styles
    logoWrap.style.display = 'flex';
    logoWrap.style.alignItems = 'center';
    logoWrap.style.marginInlineEnd = '22px';
	logoWrap.style.color = '#6d632c';
    logoWrap.style.paddingBottom = '2px'; // Minor visual tweak to align with text baseline
    // Force exact pixel height on the SVG inline (24px enlarges it without breaking the row)
    logoWrap.innerHTML = myCloudSvgLogo.replace(/height:[^;]+;/, 'height: 26px;');
    logoWrap.addEventListener('dblclick', (e) => {
        if (e.button === 0) {
            e.preventDefault();
            e.stopPropagation();
            if(typeof _sys_diag_init === 'function') _sys_diag_init();
        }
    });
	bar.appendChild(logoWrap);

    myCloudUserKeys.forEach(k => {
        const btn = document.createElement('button');
        btn.className = 'ce-cloud-btn';
        btn.dataset.key = k;
        // Capitalize first letter
        btn.textContent = k.charAt(0).toUpperCase() + k.slice(1);
		
        if (typeof myCloudCloudConfig !== 'undefined' && myCloudCloudConfig[k] && myCloudCloudConfig[k].rights === 'admin_mode') {
            btn.classList.add('ce-admin-tab');
        }
       
        if (typeof myCloudCloudConfig !== 'undefined' && myCloudCloudConfig[k] && myCloudCloudConfig[k].interface === 'email') {
            btn.classList.add('ce-email-tab');
        }

        btn.onclick = () => {
            // Re-run start explorer with specific key, maintaining multi-cloud mode
            myCloudStartExplorer(k);
        };
        
        bar.appendChild(btn);
    });

    const spacer = document.createElement('div');
    spacer.style.flex = '1';
    bar.appendChild(spacer);

    const logoutBtn = document.createElement('button');
    const isCloudOnly = <?php echo !empty($GLOBALS['isCloudOnly']) ? 'true' : 'false'; ?>;
    const L = typeof myCloud_LANG !== 'undefined' ? myCloud_LANG : {};
    logoutBtn.className = 'ce-cloud-btn ce-top-logout-btn';
    
    if (isCloudOnly) {
        logoutBtn.innerHTML = '<span style="font-weight:bold;">' + (L.logout || 'Logout') + '</span>';
        logoutBtn.title = L.logout || 'Logout';
        logoutBtn.onclick = () => { if(typeof myCloudDoLogout === 'function') myCloudDoLogout(); };
    } else {
        logoutBtn.innerHTML = '<span style="font-weight:bold;">' + (L.close || 'Close') + '</span>';
        logoutBtn.title = L.close || 'Close';
        logoutBtn.onclick = () => { if(typeof myCloudCloseExplorer === 'function') myCloudCloseExplorer(); };
    }
    bar.appendChild(logoutBtn);


}

// ============================================================
// BACKGROUND PRELOADER 
// ============================================================
window.cePreloadQueue = [];
window.cePreloadTimer = null;

function ceProcessPreloadQueue() {
    if (window.cePreloadQueue.length === 0) return;
    
    const dirToLoad = window.cePreloadQueue.shift();
    
    if (myCloudState.loadedDirs.includes(dirToLoad)) {
        ceProcessPreloadQueue();
        return;
    }

    // Fetch silently.
    myCloudFetchDirectory(dirToLoad, 2, true).finally(() => {
        // Small delay to let the connection breathe before next request
        window.cePreloadTimer = setTimeout(ceProcessPreloadQueue, 250);
    });
}

window.myCloudFetchController = null;

function myCloudFetchDirectory(path, depth = 2, silent = false) {
    // Abort any pending directory fetches to prevent overlapping state corruption
    if (window.myCloudFetchController) {
        window.myCloudFetchController.abort();
    }
	
    if (myCloudState && myCloudState.interface === 'email') {
        if (!silent) myCloudHideLoading();
        return Promise.resolve({ status: 'OK', data: [], role: 'full' });
    }

    window.myCloudFetchController = new AbortController();

    if (!silent) {
        const details = document.querySelector('.myCloudDetails');
        if (details && !myCloudState.isCommanderMode && typeof window.myCloudRenderSkeletons === 'function') {
            window.myCloudRenderSkeletons(details);
        } else {
            myCloudShowLoading();
        }
    }
    const requestEpoch = myCloudState.sessionEpoch;
    
    if (typeof myCloudSaveCurrentPathState === 'function') myCloudSaveCurrentPathState();

    return fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            myCloud_action: 'list',
            myCloud_key: myCloudState.key,
            myCloud_token: myCloudCsrfToken,
            path: path,
            depth: depth,
            enableRecycleBin: (myCloudState.settings && myCloudState.settings.enableRecycleBin) ? 'true' : 'false',
            _t: Date.now()
        })
    })
    .then(myCloudCheckResponse)
    .then(async resp => { // Make callback async for filename decryption
        if (!silent) myCloudHideLoading();
        
        if (resp && (resp.code === 'CSRF_FAILED' || (resp.msg && resp.msg.includes('CSRF')))) {
            return new Promise((resolve) => {
                myCloudRefreshCsrfToken((success) => { if (success) resolve(myCloudFetchDirectory(path, depth, silent)); else { myCloudShowAlert('Error', 'Session expired. Please refresh.'); resolve(resp); } });
            });
        }

        // Clear the controller once the request successfully completes
        if (window.myCloudFetchController && !window.myCloudFetchController.signal.aborted) {
            window.myCloudFetchController = null;
        }
        
        if (resp.status === 'OK') {
            if (resp.role) myCloudUserRole = resp.role;

                if (resp.is_encrypted_root && typeof myCloudCrypto !== 'undefined') {
                    myCloudState.encryptedDirs.add(resp.crypto_root);
                    if (!myCloudCrypto.isDirUnlocked(resp.crypto_root)) {
                        myCloudHideLoading();
                        return new Promise((resolve) => {
                            myCloudAction_EncryptPrompt(resp.crypto_root, true, 
                                () => { resolve(myCloudFetchDirectory(path, depth, silent)); },
                                () => { myCloudGoUp(); resolve(resp); }
                            );
                        });
                    }
                }
			
            // Build Global Registry of Encrypted Directories
            if (!myCloudState.encryptedDirs) myCloudState.encryptedDirs = new Set();
            if (resp.is_encrypted_root) myCloudState.encryptedDirs.add(resp.crypto_root);
            resp.data.forEach(newItem => {
				if (newItem.isEncrypted) myCloudState.encryptedDirs.add(newItem.name);
			});

            // SECURITY CHECK: Discard stale data if session reset
            if (myCloudState.sessionEpoch !== requestEpoch) return resp;

            // 1. Create a Map of ALL existing items
            const itemMap = new Map();
            (myCloudState.allItems || []).forEach(item => itemMap.set(item.name, item));

            // 2. ROBUST PARENT CLEANUP
            const targetParent = path === '/' ? '/' : path.replace(/\/$/, '');
            
            for (const [itemName, item] of itemMap) {
                const lastSlash = itemName.lastIndexOf('/');
                const parent = (lastSlash === 0) ? '/' : itemName.substring(0, lastSlash);
                
                if (parent === targetParent) {
                    itemMap.delete(itemName);
                }
            }

            // [NEW] E2E Dynamic Decryption and Broken State Check
            if (typeof myCloudCrypto !== 'undefined') {
                for (let i = 0; i < resp.data.length; i++) {
                    let item = resp.data[i];
                    let cRoot = myCloudCrypto.getCryptoRoot(item.name);
                    
                    if (cRoot) {
                        if (item.size !== 'DIR' && !item.name.endsWith('.enc') && !item.name.endsWith('.mycloud_crypto_salt')) {
                            item.isBrokenEncryption = true;
                        }
                        
                        if (myCloudCrypto.isDirUnlocked(cRoot) && item.name.endsWith('.enc')) {
                            if (myCloudState.pathNames && myCloudState.pathNames[item.name]) {
                                item.displayName = myCloudState.pathNames[item.name];
                            } else {
                                const encName = item.name.split('/').pop();
                                item.displayName = await myCloudCrypto.decryptName(cRoot, encName);
                                if (!myCloudState.pathNames) myCloudState.pathNames = {};
                                myCloudState.pathNames[item.name] = item.displayName;
                            }
                        }
                    }
                }
            }

            // 3. Insert New Data from Server
            resp.data.forEach(newItem => {
                itemMap.set(newItem.name, newItem);
            });

            // 4. Update State
            myCloudState.allItems = Array.from(itemMap.values());
            myCloudState.items = myCloudState.allItems;
            
            // Check for Media existence & Auto Switch
            const hasMedia = myCloudState.allItems.some(i => {
                const ext = i.name.split('.').pop().toLowerCase();
                return i.size !== 'DIR' && (imageExts.includes(ext) || videoExts.includes(ext));
            });
 
            if (!hasMedia && myCloudState.viewMode === 'gallery' && myCloudState.interface !== 'gallery') {
                myCloudState.viewMode = 'list';
            }            

            if (!myCloudState.loadedDirs.includes(path)) {
                myCloudState.loadedDirs.push(path);
            }

            if (!silent) myCloudRenderUI();
            
            // --- BACKGROUND PRELOADING ---
            if (!silent && typeof myCloudUserRole !== 'undefined' && myCloudUserRole === 'admin_mode') {
                window.cePreloadQueue = [];
                clearTimeout(window.cePreloadTimer);

                const subDirs = resp.data.filter(i => {
                    if (i.size !== 'DIR' || i.name === '/.recycle_bin') return false;
                    const itemParent = i.name.substring(0, i.name.lastIndexOf('/') || 0) || '/';
                    return itemParent === path;
                });

                subDirs.forEach(dir => {
                    if (!myCloudState.loadedDirs.includes(dir.name)) window.cePreloadQueue.push(dir.name);
                });

                if (window.cePreloadQueue.length > 0) {
                    window.cePreloadTimer = setTimeout(ceProcessPreloadQueue, 500);
                }
            }            

            return resp;
        }
    });
}

window.myCloudAction_EncryptPrompt = function(dirPath, forceUnlock = false, onSuccess = null, onCancel = null) {
    const isAlreadyEncrypted = forceUnlock || (myCloudState.encryptedDirs && myCloudState.encryptedDirs.has(dirPath));
    
    // [NEW] Toggle to Lock Vault if it is already open
    if (isAlreadyEncrypted && !forceUnlock && typeof myCloudCrypto !== 'undefined' && myCloudCrypto.isDirUnlocked(dirPath)) {
        const root = myCloudCrypto.getCryptoRoot(dirPath);
        myCloudShowAlert(
            typeof myCloud_LANG !== 'undefined' && myCloud_LANG.lock_dir ? myCloud_LANG.lock_dir : 'Lock Vault',
            typeof myCloud_LANG !== 'undefined' && myCloud_LANG.lock_dir_msg ? myCloud_LANG.lock_dir_msg : 'Do you want to lock this vault? You will need to enter your password again to access the files.',
            function() {
                myCloudCrypto.lockDirectory(root);
                
                // --- CACHE PURGE: Remove subtree from memory to ensure security ---
                const rootPrefix = root === '/' ? '/' : root + '/';
                
                if (myCloudState.allItems) {
                    // Keep the root folder itself, but drop everything inside it
                    myCloudState.allItems = myCloudState.allItems.filter(item => item.name === root || !item.name.startsWith(rootPrefix));
                }
                if (myCloudState.loadedDirs) {
                    // Remove root and children so they are forced to fetch again upon unlock
                    myCloudState.loadedDirs = myCloudState.loadedDirs.filter(dir => dir !== root && !dir.startsWith(rootPrefix));
                }
                if (myCloudState.pathNames) {
                    // Clear decrypted filename cache
                    Object.keys(myCloudState.pathNames).forEach(path => {
                        if (path === root || path.startsWith(rootPrefix)) delete myCloudState.pathNames[path];
                    });
                }
                if (myCloudState.previewCache) {
                    // Clear decrypted image blobs and free up RAM
                    Object.keys(myCloudState.previewCache).forEach(key => {
                        if (key.startsWith(rootPrefix) || key.startsWith(root + '_')) {
                            if (myCloudState.previewCache[key].startsWith('blob:')) {
                                URL.revokeObjectURL(myCloudState.previewCache[key]);
                            }
                            delete myCloudState.previewCache[key];
                        }
                    });
                }
                
                // If the user is currently inside the vault, kick them out to the parent directory
                if (myCloudState.currentDir.startsWith(root)) {
                    const parentDir = root === '/' ? '/' : root.substring(0, root.lastIndexOf('/')) || '/';
                    myCloudHandleEnter({ name: parentDir, size: 'DIR' });
                } else {
                    // Otherwise just refresh the current view to update the lock icons
                    myCloudFetchDirectory(myCloudState.currentDir);
                }
            }
        );
        return;
    }
 
    const header = isAlreadyEncrypted ? (myCloud_LANG.unlock_dir ?? 'Unlock Directory') : (myCloud_LANG.encrypt_dir ?? 'Encrypt / Unlock');
    const label = isAlreadyEncrypted ? (myCloud_LANG.enter_password ?? 'Enter Encryption Password:') : (myCloud_LANG.set_password ?? 'Set Encryption Password (DO NOT LOSE THIS):');
    
    // USE THE PASSWORD MODAL FROM UI_HELPERS
    myCloudShowPasswordModal(header, label, async function(password) {
    myCloudShowLoading();
        try {
            if (isAlreadyEncrypted) {
                // 1. UNLOCK PHASE
                const fd = new URLSearchParams({ 
                    myCloud_action: 'crypto_get_salt', 
                    myCloud_key: myCloudState.key, 
                    myCloud_token: typeof myCloudCsrfToken !== 'undefined' ? myCloudCsrfToken : '', 
                    path: dirPath 
                });
                const res = await fetch('', { method: 'POST', body: fd }).then(r => r.json());
                
                if (res.status !== 'OK') throw new Error(res.msg || "Could not fetch directory salt from server.");
                
                // res.salt now contains the JSON payload for V2, or the raw string for V1
                await myCloudCrypto.unlockDirectory(dirPath, password, res.salt);
				
                if (!myCloudState.encryptedDirs) myCloudState.encryptedDirs = new Set();
                myCloudState.encryptedDirs.add(dirPath);

                myCloudState.loadedDirs = myCloudState.loadedDirs.filter(d => !d.startsWith(dirPath));
                myCloudState.allItems = myCloudState.allItems.filter(i => !i.name.startsWith(dirPath + '/'));
                
                if (onSuccess) onSuccess();
                else {
                    myCloudShowAlert(myCloud_LANG.success ?? 'Success', myCloud_LANG.dir_unlocked ?? 'Directory unlocked securely in memory.');
                    myCloudHandleEnter({ name: dirPath, size: 'DIR' });
                }
                
            } else {
                // 2. SETUP/ENCRYPT PHASE
                const { payload } = await myCloudCrypto.unlockDirectory(dirPath, password, null);
               
                // Tell server to create the .enc_salt file and mark this as an encryption root
                const fd = new URLSearchParams({ 
                    myCloud_action: 'crypto_init', 
                    myCloud_key: myCloudState.key, 
                    myCloud_token: typeof myCloudCsrfToken !== 'undefined' ? myCloudCsrfToken : '', 
                    path: dirPath, 
                    salt: payload 
                });
                const res = await fetch('', { method: 'POST', body: fd }).then(r => r.json());
                
                if (res.status !== 'OK') throw new Error(res.msg || "Failed to initialize encryption on server.");
                
                if (!myCloudState.encryptedDirs) myCloudState.encryptedDirs = new Set();
                myCloudState.encryptedDirs.add(dirPath);
                
                // --- MIGRATE EXISTING FILES ---
				myCloudShowAlert(myCloud_LANG.setup_complete ?? 'Setup Complete', myCloud_LANG.setup_complete_msg ?? 'Directory initialized. We will now encrypt existing files. Please do not close the page.', async function() {
					await myCloudMigrateDirectory(dirPath);
					if (onSuccess) onSuccess();
                });
            }
            
        } catch(e) {
            myCloudHideLoading();
            myCloudShowAlert(myCloud_LANG.error_prefix ?? 'Error', (myCloud_LANG.crypto_error ?? 'Crypto Error:') + ' ' + e.message);
            if (onCancel) onCancel();
        }
        myCloudHideLoading();
    }, function() {
        if (onCancel) onCancel();
    }, !isAlreadyEncrypted);
};

function myCloudHandleEnter(item) {
    if (!item) return;

    if (item.isUpDir || item.name === '..') {
        myCloudGoUp();
        return;
    }
    const realName = item.displayName ? item.displayName : item.name.split('/').pop().replace(/\.enc$/, '');
    const ext = realName.split('.').pop().toLowerCase();
   
    // [NEW] Pre-Navigation E2E Encryption Trap
    const isEnc = typeof myCloudCrypto !== 'undefined' && myCloudCrypto.isDirEncrypted(item.name);
    if (isEnc && !myCloudCrypto.isDirUnlocked(item.name)) {
       	 myCloudAction_EncryptPrompt(myCloudCrypto.getCryptoRoot(item.name), true, () => {
			// Successfully unlocked, proceed with navigation
			myCloudHandleEnter(item);
        });
        return;
    }

    if (item.size === 'DIR' || ext === 'zip') {
       // [FIX] Prevent browsing into deleted folders (preserves structure but blocks view)
       if (myCloudState.currentDir === '/.recycle_bin') {
           return; 
       }

        const treeEl = document.querySelector('.myCloudTree');
        const wasTreeFocused = treeEl && (document.activeElement === treeEl || treeEl.contains(document.activeElement));

        myCloudState.currentDir = item.name; 
        myCloudState.selectedFiles = [];
        myCloudExpandToPath(item.name); 
        
        // [FIX] 1. Update View Mode synchronously
        if (['gallery', 'symbol', 'symbol-dark'].includes(myCloudState.interface)) {
            myCloudState.viewMode = 'symbol';
        } else if (typeof myCloudGetEffectiveViewMode === 'function') {
            myCloudState.viewMode = myCloudGetEffectiveViewMode(item.name);
        }
       
        // [FIX] 2. Clear UI immediately to prevent "flash" of old view
        var details = document.querySelector('.myCloudDetails');
        if (details) details.innerHTML = '';

        // CRITICAL FIX: Reset the internal cursor tracker to 0 immediately
        myCloudState.visualCursorIndex = 0; 
        
        myCloudFetchDirectory(item.name).then(() => {
            if (wasTreeFocused && treeEl) {
                treeEl.focus();
                setTimeout(() => myCloudSetTreeFocus(document.querySelector('.myCloudTreeList div[data-fullpath="' + CSS.escape(item.name) + '"]')), 50);
            } else {
                const d = document.querySelector('.myCloudDetails');
                if(d) d.focus(); 
                myCloudResetListCursor();
            }
        });
    } else {
        // File Handling
        const dKey = myCloudGetCurrentDeviceKey();
        const allowPreview = myCloudState.settings && myCloudState.settings[dKey] && myCloudState.settings[dKey].clickToPreview;
        if (myCloudUserRole === 'admin_mode') {
            myCloudEditFile(item.name);
        } else if (typeof myCloudConfig !== 'undefined' && myCloudConfig.edit.includes(ext) && typeof myCloudUserRole !== 'undefined' && myCloudUserRole !== 'read-only') {
             myCloudEditFile(item.name);
        } else if (typeof myCloudIsPreviewable === 'function' && myCloudIsPreviewable(ext) && allowPreview) {
             myCloudDownloadFile(item.name, item.name.split('/').pop(), true);
        } else {
             myCloudDownloadFile(item.name, item.name.split('/').pop(), false);
        }
    }
}


function myCloudSelectRow(row, fullpath, event) {
	
	// Dismiss context menu on selection change
    if (event && event.button !== 2 && typeof myCloudCloseContextMenus === 'function') {
        myCloudCloseContextMenus();
    }
	
    const st = myCloudState;
    const sortedItems = myCloudGetSortedItems();
    
    // 1. SYNC VISUAL CURSOR (Crucial for Keyboard continuity)
    const rows = Array.from(document.querySelectorAll('.myCloudRow, .myCloud-symbol-item'));
    const visualIndex = rows.findIndex(r => r === row);
    if (visualIndex !== -1) st.visualCursorIndex = visualIndex;

    // 2. Determine Index in Data (for Anchor)
    let dataIndex = sortedItems.findIndex(i => i.name === fullpath);
    if (dataIndex === -1 && fullpath === '..') dataIndex = 0;

    // 3. Selection Logic
    // Right Click
    if (event.button === 2) {
        if (!st.selectedFiles.includes(fullpath)) st.selectedFiles = [fullpath];
    } 
    // Ctrl Click
    else if (event.ctrlKey || event.metaKey) {
        if (st.selectedFiles.includes(fullpath)) st.selectedFiles = st.selectedFiles.filter(f => f !== fullpath);
        else st.selectedFiles.push(fullpath);
        st.lastSelectedIndex = visualIndex; 
    } 
    // Shift Click
    else if (event.shiftKey && st.lastSelectedIndex !== -1) {
        const start = Math.min(st.lastSelectedIndex, visualIndex);
        const end = Math.max(st.lastSelectedIndex, visualIndex);
        
        // Select range based on Visual DOM Order
        st.selectedFiles = rows.slice(start, end + 1)
            .map(r => r.dataset.fullpath)
            .filter(p => p !== '..');
            
        // Anchor (lastSelectedIndex) stays put!
    } 
    // Normal Click
    else {
        st.selectedFiles = [fullpath];
        st.lastSelectedIndex = visualIndex; 
    }

    st.currentFile = st.selectedFiles.length === 1 ? st.selectedFiles[0] : null;

    // 4. Visual Update
    rows.forEach(r => {
        const rPath = r.dataset.fullpath;
        const isSel = st.selectedFiles.includes(rPath);
        if(isSel) r.classList.add('selected'); else r.classList.remove('selected');
        const cb = r.querySelector('.myCloudCheckbox');
        if (cb) cb.checked = isSel;
    });

    myCloudUpdateToolbarState();
	
    if (typeof myCloudUpdateOfficePreview === 'function' && myCloudState.isOfficeMode) {
        myCloudUpdateOfficePreview();
    }
}




function myCloudExpandToPath(path) {
    if (!path || path === '/') {
        if (!myCloudState.openDirs.includes('/')) myCloudState.openDirs.push('/');
        return;
    }
    const parts = path.split('/').filter(p => p);
    let walker = '';
    // Always ensure root is open
    if (!myCloudState.openDirs.includes('/')) myCloudState.openDirs.push('/');
    
    parts.forEach(part => {
        walker += '/' + part;
        if (!myCloudState.openDirs.includes(walker)) {
            myCloudState.openDirs.push(walker);
        }
    });
}





function myCloudGetSortedItems() {
    const st = myCloudState;
    if (!st.allItems) return [];
    let list = [];

    // --- GLOBAL TAG FILTER: SPARSE LIST GENERATION ---
    if (st.activeTagFilter) {
        const tags = (st.tags && st.tags[st.key]) ? st.tags[st.key] : {};
        const taggedPaths = Object.keys(tags).filter(p => {
            let t = tags[p];
            if (t && !Array.isArray(t)) t = [t];
            return t && t.includes(st.activeTagFilter);
        });
        
        // Inject virtual parent directories for unloaded tagged items
        taggedPaths.forEach(tp => {
            let parts = tp.split('/').filter(x => x);
            let walker = '';
            parts.forEach(part => {
                walker += '/' + part;
                if (!st.allItems.some(i => i.name === walker)) st.allItems.push({ name: walker, size: 'DIR', date: '-' });
            });
        });

        list = st.allItems.filter(i => {
            const parent = i.name.substring(0, i.name.lastIndexOf('/') || 0) || '/';
            if (parent !== st.currentDir) return false;
            
            let t = tags[i.name];
            if (t && !Array.isArray(t)) t = [t];
            if (t && t.includes(st.activeTagFilter)) return true;
            
            return taggedPaths.some(tp => {
                if (i.name === '/') return true;
                if (tp.startsWith(i.name + '/')) return true; // Leads down to tag
                if (tp === '/') return true;
                if (i.name.startsWith(tp + '/')) return true; // Inherits from tag
                return false;
            });
        });
    } else {
        list = st.allItems.filter(i => {
            const parent = i.name.substring(0, i.name.lastIndexOf('/') || 0) || '/';
            return parent === st.currentDir;
        });
    }

    list.sort((a, b) => {
        const isADir = a.size === 'DIR', isBDir = b.size === 'DIR';
        if (isADir && isBDir) {
            if (a.name === '/.recycle_bin') return 1;
            if (b.name === '/.recycle_bin') return -1;
        }
        if (isADir && !isBDir) return -1; if (!isADir && isBDir) return 1;
        let val = 0, dir = st.sort.dir;
        
        if (st.sort.col === 'size') { 
            if (!isADir && !isBDir) val = (parseInt(a.size) - parseInt(b.size)) * dir; 
        } 
        else if (st.sort.col === 'date') { 
            if (a.date < b.date) val = -1 * dir; else if (a.date > b.date) val = 1 * dir; 
        } 
        else if (st.sort.col === 'owner') { 
            const oA = (a.owner || '').toLowerCase(), oB = (b.owner || '').toLowerCase(); 
            if (oA < oB) val = -1 * dir; else if (oA > oB) val = 1 * dir; 
        }
        else if (st.sort.col === 'perms') { 
            const pA = (a.perms || '').toLowerCase(), pB = (b.perms || '').toLowerCase(); 
            if (pA < pB) val = -1 * dir; else if (pA > pB) val = 1 * dir; 
        }
        else { 
            const nameA = a.displayName || a.name.split('/').pop();
            const nameB = b.displayName || b.name.split('/').pop();
            val = nameA.localeCompare(nameB, undefined, { numeric: true, sensitivity: 'base' }) * dir;
        }
        const fallbackA = a.displayName || a.name.split('/').pop();
        const fallbackB = b.displayName || b.name.split('/').pop();
        return val === 0 ? fallbackA.localeCompare(fallbackB, undefined, { numeric: true, sensitivity: 'base' }) : val;
    });
    return list;
}



function myCloudSyncTableSelection(fullpath) {
    document.querySelectorAll('.myCloudRow.selected, .myCloud-symbol-item.selected').forEach(r => {
        r.classList.remove('selected');
        const cb = r.querySelector('.myCloudCheckbox');
        if (cb) cb.checked = false;
    });
    myCloudState.selectedFiles = [fullpath];
    const targetRow = document.querySelector('.myCloudRow[data-fullpath="' + fullpath + '"], .myCloud-symbol-item[data-fullpath="' + fullpath + '"]');
    if (targetRow) {
        targetRow.classList.add('selected');
//        targetRow.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        // [FIX] Use 'center' to ensure row isn't hidden behind sticky headers
        targetRow.scrollIntoView({ block: 'center', behavior: 'auto' });

        // Sync Checkbox (Visual only, state already set)
        const cb = targetRow.querySelector('.myCloudCheckbox');
        if (cb) cb.checked = true;
		myCloudState.visualCursorIndex = Array.from(document.querySelectorAll('.myCloudRow, .myCloud-symbol-item')).indexOf(targetRow);
		myCloudState.lastSelectedIndex = myCloudState.visualCursorIndex;
	}
    myCloudUpdateToolbarState();
}

// --- TOUCH HANDLERS ---
function myCloudTriggerUpload() {
    const input = document.createElement('input');
    input.type = 'file';
    input.multiple = true;
    input.onchange = e => { if (e.target.files.length) for(let f of e.target.files) myCloudUploadFile(f, null); };
    input.click();
}

// Wrapper to check permissions/sizes before downloading/previewing
function myCloudDownloadFile(path, filename, isPreview = false) {
	if (!path || !filename) return;
    let cleanFilename = filename;
    if (cleanFilename.endsWith('.enc')) {
        cleanFilename = (myCloudState.pathNames && myCloudState.pathNames[path]) ? myCloudState.pathNames[path] : cleanFilename.replace(/\.enc$/, '');
    }
    const ext = cleanFilename.split('.').pop().toLowerCase();
    const isImage = imageExts.includes(ext);

    // 1. CHECK SIZE LIMIT FOR PREVIEWS (Skip images as they are server-resized)
    if (isPreview && !isImage) {
        // Find the item in our loaded state to get the size
        const item = myCloudState.allItems.find(i => i.name === path);
        
        if (item && item.size !== 'DIR') {
            const size = parseInt(item.size); // Convert size to int
            
            // [UPDATED] Get Current Device Settings
            const devKey = myCloudGetCurrentDeviceKey();
            const config = myCloudState.settings ? myCloudState.settings[devKey] : myCloudDefaultSettings[devKey];
            
            // [UPDATED] Only warn if the setting is TRUE
            // If warnLargePreview is false, we skip this block and proceed directly
            if (config.warnLargePreview && size > myCloudMaxPreviewSize) {
                // Show confirmation modal
                myCloudShowPreviewLimitModal(filename, size, 
                    // On Preview Anyway
                    () => { _cloudExProceedDownload(path, cleanFilename, true); },
                    // On Download Instead
                    () => { _cloudExProceedDownload(path, cleanFilename, false); }
                );
                return; // Stop here, wait for user input
            }
        }
    }

    // Proceed normally if no checks failed (or warning is disabled)
    _cloudExProceedDownload(path, cleanFilename, isPreview);
}

// The actual Fetch/Download Logic (formerly myCloudDownloadFile)
async function _cloudExProceedDownload(path, filename, isPreview) {
    const isInsideZip = typeof myCloudIsInsideZip === 'function' ? myCloudIsInsideZip(path) : false;
    const cacheKey = isPreview ? path + '_sd' : path + '_hd';
    const isEncrypted = typeof myCloudCrypto !== 'undefined' && myCloudCrypto.isDirEncrypted(path);

    // 1. Check if the specific quality requested is already cached
    // Bypass cache for standard downloads to prevent hitting dead tokens/deleted zips
    if (isPreview && myCloudState.previewCache[cacheKey]) {
        const cachedUrl = myCloudState.previewCache[cacheKey];
        myCloudState.previewPath = path;
        myCloudState.selectedFiles = [path];

        if (myCloudState.isCommanderMode) {
            const side = myCloudState.commanderActive || 'left';
            const cmdPane = document.querySelector(`.myCloud-commander-pane[data-side="${side}"]`);
            if (cmdPane) {
                const content = cmdPane.querySelector('.myCloud-commander-content');
                if (content) {
                    const row = content.querySelector(`.myCloudRow[data-fullpath="${CSS.escape(path)}"]`);
                    if (row) {
                        if (typeof commanderSelectRow === 'function') commanderSelectRow(row, path, side, {});
                        row.scrollIntoView({ block: 'center', behavior: 'auto' });
                    }
                }
            }
        } else if (typeof myCloudSyncTableSelection === 'function') {
            myCloudSyncTableSelection(path);
        }

        myCloudOpenPreview(cachedUrl, filename, path);
        return;
    }

    // --- E2E Unlock Check ---
    if (isEncrypted) {
        const root = myCloudCrypto.getCryptoRoot(path);
        if (!myCloudCrypto.isDirUnlocked(root)) {
            myCloudAction_EncryptPrompt(root, true, () => {
                _cloudExProceedDownload(path, filename, isPreview);
            });
            return;
        }
    }

    // --- Progress UI ---
    // Unconditionally show progress bar for standard downloads to prevent freezing
    if (!isPreview && typeof myCloudCreateProgressUI === 'function') {
        myCloudCreateProgressUI((typeof myCloud_LANG !== 'undefined' && myCloud_LANG.fetching) ? myCloud_LANG.fetching : 'Preparing Download...');
    }

    try {
        let	response = await fetch(window.location.pathname, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                myCloud_action: 'get_download_token',
                myCloud_key: myCloudState.key,
                myCloud_token: typeof myCloudCsrfToken !== 'undefined' ? myCloudCsrfToken : '',
                path: path,
                filename: filename,
                preview: isPreview ? '1' : '0',
                isZipContent: isInsideZip ? '1' : '0'
            })
        });

        if (!response.ok) throw new Error("Server returned " + response.status);
        let res = await response.json();

        if (res.status !== 'OK') {
            if (!isPreview && typeof myCloudCloseProgressUI === 'function') myCloudCloseProgressUI();
            if (res.code === 'AUTH_REQUIRED' && typeof myCloudPromptAdminAuth === 'function') {
                myCloudPromptAdminAuth(() => _cloudExProceedDownload(path, filename, isPreview));
                return;
            }
            throw new Error(res.msg || res.message || 'Server Error');
        }

        let downloadUrl = window.location.pathname + '?myCloud_token=' + res.token + '&nocache=' + Date.now();;
        let finalFilename = filename;
        let isDecryptedBlob = false;
		let memoryBlob = null;
		
         const item = myCloudState.allItems.find(i => i.name === path);
         const isDir = item && item.size === 'DIR';
 
         if (isDir && !finalFilename.toLowerCase().endsWith('.zip')) {
             finalFilename += '.zip';
         }

        // --- E2E Decryption Logic ---
        if (isEncrypted) {
             if (!isDir) {
                if (!isPreview && typeof document !== 'undefined') {
                    const textEl = document.getElementById('myCloudProgressText');
                    if (textEl) textEl.textContent = 'Decrypting...';
                } else if (isPreview && typeof myCloudCreateProgressUI === 'function') {
                    myCloudCreateProgressUI('Decrypting...');
                }

                try {
                    const encryptedBlob = await fetch(downloadUrl).then(r => r.blob());
                    const root = myCloudCrypto.getCryptoRoot(path);
                    const decryptedBlob = await myCloudCrypto.decryptFile(root, encryptedBlob);
                    downloadUrl = URL.createObjectURL(decryptedBlob);
                    finalFilename = await myCloudCrypto.decryptName(root, filename);
                    isDecryptedBlob = true;
					memoryBlob = decryptedBlob;
                    if (isPreview && typeof myCloudCloseProgressUI === 'function') myCloudCloseProgressUI();
                } catch (decErr) {
                    if (isPreview && typeof myCloudCloseProgressUI === 'function') myCloudCloseProgressUI();
                    throw new Error("Decryption failed. " + decErr.message);
                }
            } else {
                 // CLIENT-SIDE ARCHIVE DECRYPTION
                 if (!isPreview && typeof document !== 'undefined') {
                     const textEl = document.getElementById('myCloudProgressText');
                     if (textEl) textEl.textContent = 'Decrypting Archive...';
                 }
                 
                 try {
                     // FIX 1: Fetch the ZIP strictly as an ArrayBuffer. JSZip silently fails 
                     // to read Blobs asynchronously in loops on some browsers, resulting in 0-byte files.
                     const encryptedZipBuffer = await fetch(downloadUrl).then(r => r.arrayBuffer());
                     const root = myCloudCrypto.getCryptoRoot(path);
                   
                     if (typeof window.JSZip === 'undefined') {
                         if (typeof myCloudLoadJS === 'function') {
                             await myCloudLoadJS('https://unpkg.com/jszip/dist/jszip.min.js');
                        } else {
                             await new Promise((r) => { const s = document.createElement('script'); s.src = 'https://unpkg.com/jszip/dist/jszip.min.js'; s.onload = r; document.head.appendChild(s); });
                         }
                     }
                     
                     const encZip = await JSZip.loadAsync(encryptedZipBuffer);
                     const decZip = new JSZip();
                     const files = Object.keys(encZip.files);
                     
                     for (let i = 0; i < files.length; i++) {
                         const relativePath = files[i];
                         const zipEntry = encZip.files[relativePath];
                         if (zipEntry.dir) continue;
                         
                         const fName = relativePath.split('/').pop();
                         if (fName === '.mycloud_crypto_salt') continue; 
                         
                         // FIX 2: Extract exactly as Uint8Array for perfect binary bridging
                         const encFileData = await zipEntry.async("uint8array");
                         let decFileData = encFileData;
                         
                         const pathParts = relativePath.split('/');
                         const decPathParts = [];
                         for (let p of pathParts) {
                             if (p.endsWith('.enc')) {
                                 try { decPathParts.push(await myCloudCrypto.decryptName(root, p)); } 
                                 catch(e) { decPathParts.push(p); }
                             } else {
                                 decPathParts.push(p);
                             }
                         }
                         const finalRelativePath = decPathParts.join('/');
                         
                         if (fName.endsWith('.enc')) {
                             try { 
                                 const decBlob = await myCloudCrypto.decryptFile(root, new Blob([encFileData]));
                                 decFileData = new Uint8Array(await decBlob.arrayBuffer()); 
                             } catch(e) { 
                                 console.warn("Could not decrypt file inside zip:", fName, e);
                             }
                         }
                         decZip.file(finalRelativePath, decFileData);
                     }
                     
                     const finalZipBlob = await decZip.generateAsync({type:"blob"});
                     downloadUrl = URL.createObjectURL(finalZipBlob);
                     isDecryptedBlob = true;
					 memoryBlob = finalZipBlob;
                 } catch (decErr) {
                     throw new Error("Folder decryption failed. " + decErr.message);
               }
			}
        }

        if (isPreview) {
            myCloudState.previewCache[cacheKey] = downloadUrl;
            myCloudState.previewPath = path;
            myCloudState.selectedFiles = [path];

            if (myCloudState.isCommanderMode) {
                const side = myCloudState.commanderActive || 'left';
                const cmdPane = document.querySelector(`.myCloud-commander-pane[data-side="${side}"]`);
                if (cmdPane) {
                    const content = cmdPane.querySelector('.myCloud-commander-content');
                    if (content) {
                        const row = content.querySelector(`.myCloudRow[data-fullpath="${CSS.escape(path)}"]`);
                        if (row) {
                            if (typeof commanderSelectRow === 'function') commanderSelectRow(row, path, side, {});
                            row.scrollIntoView({ block: 'center', behavior: 'auto' });
                        }
                    }
                }
            } else if (typeof myCloudSyncTableSelection === 'function') {
                myCloudSyncTableSelection(path);
            }
            
            myCloudOpenPreview(downloadUrl, finalFilename, path);
        } else {
            if (isDecryptedBlob) {
                 // THE PROFESSIONAL FIX: Use native Save Picker for encrypted blobs.
                 (async () => {
                     try {
                         const handle = await window.showSaveFilePicker({ suggestedName: finalFilename });
                         const response = await fetch(downloadUrl);
                         const writable = await handle.createWritable();
                         await response.body.pipeTo(writable);
                         setTimeout(() => URL.revokeObjectURL(downloadUrl), 60000);
                     } catch (err) {
                         console.error(err);
                     }
                 })();
            } else {
//                console.log(downloadUrl + "|started");
	          // THE PROFESSIONAL FIX: Use native Save Picker.
    	       // This bypasses all navigation locks and window issues.
	          (async () => {
	              try {
	                  // 1. Trigger the Save Dialog immediately
	                  const handle = await window.showSaveFilePicker({ suggestedName: finalFilename });
	                  
                     const writable = await handle.createWritable();
 
                     // If we already hold the decrypted file in RAM, write it directly (Bypasses CSP fetch block)
                     if (isDecryptedBlob && memoryBlob) {
                         await writable.write(memoryBlob);
                         await writable.close();
                     } else {
                         // Otherwise stream standard unencrypted files directly from the server
                         const response = await fetch(downloadUrl);
                         await response.body.pipeTo(writable);
                     }
	                  
	                  if (typeof myCloudCloseProgressUI === 'function') myCloudCloseProgressUI();
	               } catch (err) {
    	               // User cancelled or error occurred
    	               if (typeof myCloudCloseProgressUI === 'function') myCloudCloseProgressUI();
    	           }
   		      })();
//						console.log(downloadUrl + "|finished");
			}
        }

        if (!isPreview && typeof myCloudCloseProgressUI === 'function') myCloudCloseProgressUI();
    } catch (err) {
        if (!isPreview && typeof myCloudCloseProgressUI === 'function') myCloudCloseProgressUI();
        if (typeof myCloudShowAlert === 'function') myCloudShowAlert("Error", "Error accessing file: " + err.message);
    }
}


function myCloudCreateProgressUI(filename) {
    const existing = document.getElementById('myCloudProgressPopup');
    if (existing) existing.remove();
    const div = document.createElement('div');
    div.id = 'myCloudProgressPopup';
    div.className = 'myCloudProgressPopup';
    div.innerHTML = '<div class="myCloudProgressTitle">' + filename + '</div><div class="myCloudProgressBar"><div id="myCloudProgressFill" class="myCloudProgressFill"></div></div><div id="myCloudProgressText" class="myCloudProgressText">' + myCloud_LANG.loading + '</div>';
    document.body.appendChild(div);
    void div.offsetWidth; 
    return div;
}

function myCloudUpdateProgressUI(percent) {
    requestAnimationFrame(() => {
        const fill = document.getElementById('myCloudProgressFill'), text = document.getElementById('myCloudProgressText');
        if (fill && text) { fill.style.width = percent + '%'; text.textContent = (percent >= 98) ? myCloud_LANG.wait_last_steps : Math.floor(percent) + '%'; }
    });
}

function myCloudCloseProgressUI() {
    const div = document.getElementById('myCloudProgressPopup');
	if (div) {
		setTimeout(() => { 
			div.style.opacity = '0'; 
			div.style.transition = 'opacity 0.5s'; 
			
			// Ensure removal happens even if transitionend misses
			const removeFn = () => { if(div.parentNode) div.remove(); };
			div.addEventListener('transitionend', removeFn, { once: true });
			setTimeout(removeFn, 550); // Fallback timer
		}, 800);
	}
}
 

// --- NEW HELPER: Streaming Request ---
function myCloudStreamAction(actionParams, title) {
    myCloudCreateProgressUI(title);
    
    // Add auth tokens
    actionParams.append('myCloud_key', myCloudState.key);
    actionParams.append('myCloud_token', myCloudCsrfToken);

    fetch('', {
        method: 'POST',
        body: actionParams
    }).then(response => {
        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';

        function read() {
            reader.read().then(({done, value}) => {
                if (done) {
                    // Safety cleanup if connection closes without explicit DONE
                    setTimeout(() => {
                        myCloudCloseProgressUI();
                        myCloudFetchDirectory(myCloudState.currentDir);
                    }, 500);
                    return;
                }

                buffer += decoder.decode(value, {stream: true});
                const lines = buffer.split('\n\n'); 
                buffer = lines.pop(); 

                lines.forEach(line => {
                    if (line.startsWith('data: ')) {
                        try {
                            const data = JSON.parse(line.substring(6));
                            
                            // Update UI
                            if (data.msg) {
                                const el = document.getElementById('myCloudProgressText');
                                if(el) el.textContent = data.msg;
                            }
                            if (typeof data.percent !== 'undefined') {
                                myCloudUpdateProgressUI(data.percent);
                            }

                            // Handle Completion or Error
                            if (data.status === 'OK' || data.percent >= 100) {
                                myCloudUpdateProgressUI(100);
                                setTimeout(() => {
                                    myCloudCloseProgressUI();
                                    myCloudFetchDirectory(myCloudState.currentDir);
                                }, 800);
                            } else if (data.status === 'ERR') {
                                myCloudShowAlert(myCloud_LANG.error_prefix, data.msg);
                                myCloudCloseProgressUI();
                            }

                        } catch(e) { console.error("Parse error", e); }
                    }
                });
                read();
            }).catch(e => {
                console.error("Stream Error", e);
                myCloudCloseProgressUI();
            });
        }
        read();
    });
}


async function myCloudUploadFile(file, targetDir = null, resolution = null) {
    if (!file) return;
    if (myCloudUserRole === 'read-only' || myCloudUserRole === 'no-access') return;

    const actualDir = targetDir ? targetDir : myCloudState.currentDir;
    let fileToUpload = file;
    let uploadName = file.name;

     // 1. Client-Side Collision Check (Critical for E2E where server can't read filenames)
     const isEncryptedTarget = typeof myCloudCrypto !== 'undefined' && myCloudCrypto.isDirUnlocked(actualDir);
     
     // Ensure target directory is loaded in memory for accurate collision checks
     if (isEncryptedTarget && !myCloudState.loadedDirs.includes(actualDir)) {
         await myCloudFetchDirectory(actualDir, 1, true);
     }
 
     const currentFolderItems = myCloudState.allItems.filter(i => {
         const parent = i.name.substring(0, i.name.lastIndexOf('/') || 0) || '/';
         return parent === actualDir;
     });
 
     let existingItem = null;
     for (let i of currentFolderItems) {
         let dName = (myCloudState.pathNames && myCloudState.pathNames[i.name]) ? myCloudState.pathNames[i.name] : i.name.split('/').pop().replace(/\.enc$/, '');
         if (dName === uploadName) {
             existingItem = i;
             break;
         }
     }
 
     if (existingItem && !resolution) {
         myCloudShowConflictModal(uploadName, (res) => {
             if (res) myCloudUploadFile(file, targetDir, res);
         });
         return;
     }
 
     if (resolution === 'keep_both') {
         let base = uploadName;
         let ext = '';
         const lastDot = uploadName.lastIndexOf('.');
         if (lastDot > 0) {
             base = uploadName.substring(0, lastDot);
             ext = uploadName.substring(lastDot);
         }
         let counter = 1;
         let newName = `${base} (${counter})${ext}`;
         while (currentFolderItems.some(i => {
             let dName = (myCloudState.pathNames && myCloudState.pathNames[i.name]) ? myCloudState.pathNames[i.name] : i.name.split('/').pop().replace(/\.enc$/, '');
             return dName === newName;
         })) {
             counter++;
             newName = `${base} (${counter})${ext}`;
         }
         uploadName = newName;
         existingItem = null; // Renamed, so no longer overwriting
     }

     // 2. Intercept and Encrypt if Directory is Unlocked
     if (isEncryptedTarget) {
		 try {
            myCloudCreateProgressUI('Encrypting: ' + uploadName);
            fileToUpload = await myCloudCrypto.encryptFile(actualDir, file);
             // Reuse the exact physical encrypted filename if overwriting, else generate a new one
             if (resolution === 'overwrite' && existingItem && existingItem.name.endsWith('.enc')) {
                 uploadName = existingItem.name.split('/').pop();
             } else {
                 uploadName = await myCloudCrypto.encryptName(actualDir, uploadName);
             }
            myCloudCloseProgressUI();
        } catch (e) {
            myCloudCloseProgressUI();
            myCloudShowAlert('Encryption Error', e.message);
            return;
        }
     } else if (resolution === 'overwrite' && existingItem) {
         // Guarantee physical overwrite for unencrypted files
         uploadName = existingItem.name.split('/').pop();
    }

    if (!document.getElementById('myCloudProgressPopup')) {
       myCloudCreateProgressUI('Uploading: ' + (resolution === 'keep_both' ? uploadName : file.name));
    }
	
	window.myCloudTaskStart();
	
    const fd = new FormData();
    fd.append('myCloud_action', 'upload');
    fd.append('dir', actualDir);
    fd.append('myCloud_key', myCloudState.key);
    fd.append('myCloud_token', window.myCloudCsrfToken);
    
    // Use the potentially encrypted blob and filename
    fd.append('file', fileToUpload, uploadName);
   
    if (file.uploadRelativePath) {
        fd.append('relativePath', file.uploadRelativePath);
    }

    if (file.lastModified) {
        fd.append('lastModified', Math.floor(file.lastModified / 1000));
    }

    if (resolution) fd.append('resolution', resolution);

    const xhr = new XMLHttpRequest();
    let responseReceived = false;
    let uploadFinished = false;

    xhr.open('POST', '', true);
    xhr.timeout = 15 * 60 * 1000; 

    xhr.upload.onprogress = (e) => {
        if (e.lengthComputable) {
            myCloudUpdateProgressUI((e.loaded / e.total) * 100);
        }
    };

    xhr.upload.onloadend = () => {
        uploadFinished = true;
        setTimeout(() => {
            if (!responseReceived) {
                myCloudUpdateProgressUI(100);
                myCloudFetchDirectory(myCloudState.currentDir);
                myCloudCloseProgressUI();
				window.myCloudTaskEnd();
                myCloudNotify(myCloud_LANG.upload_complete_delayed);
                xhr.abort();
            }
        }, 15000);
    };

    xhr.onload = () => {
        responseReceived = true;
		window.myCloudTaskEnd();
        if (xhr.status !== 200) {
            myCloudCloseProgressUI();
            myCloudNotify(myCloud_LANG.upload_failed + " (HTTP " + xhr.status + ")"); 
            return;
        }
        try {
            const resp = JSON.parse(xhr.responseText);
            if (resp.code === 'CSRF_FAILED' || (resp.msg && resp.msg.includes('CSRF'))) {
                myCloudRefreshCsrfToken((success) => {
                    if (success) { myCloudCloseProgressUI(); myCloudUploadFile(file, targetDir, resolution); }
                    else { myCloudCloseProgressUI(); myCloudNotify("Session expired. Please refresh."); }
                });
                return;
            }
            if (resp.status === 'OK') {
                myCloudUpdateProgressUI(100);
                if (window.myCloudUploadTimer) clearTimeout(window.myCloudUploadTimer);
                window.myCloudUploadTimer = setTimeout(() => {
                    myCloudFetchDirectory(myCloudState.currentDir, 2, true).then(() => myCloudRenderUI());
                }, 1000);
                myCloudCloseProgressUI();
            } else if (resp.status === 'CONFLICT') {
                myCloudCloseProgressUI();
                myCloudShowConflictModal(resp.file, (r) => {
                    if (r) myCloudUploadFile(file, targetDir, r);
                });
            } else if (resp.code === 'AUTH_REQUIRED' && typeof myCloudPromptAdminAuth === 'function') {
                myCloudCloseProgressUI();
                myCloudPromptAdminAuth(() => myCloudUploadFile(file, targetDir, resolution));
            } else {
                myCloudCloseProgressUI();
                var errorReason = resp.msg || myCloud_LANG.upload_failed;
                myCloudNotify("Upload Failed: " + errorReason);
                console.error("Upload Failed Response:", resp);
            }
        } catch {
            myCloudCloseProgressUI();
            myCloudNotify(myCloud_LANG.invalid_response); 
        }
    };

    xhr.onerror = () => {
        window.myCloudTaskEnd();
		myCloudCloseProgressUI();
        myCloudNotify(myCloud_LANG.upload_failed_net); 
    };

    xhr.onabort = () => {
        window.myCloudTaskEnd();
		if (!uploadFinished) {
            myCloudCloseProgressUI();
            myCloudNotify(myCloud_LANG.upload_aborted); 
        }
    };

    xhr.ontimeout = () => {
        window.myCloudTaskEnd();
		myCloudCloseProgressUI();
        myCloudNotify(myCloud_LANG.upload_timed_out); 
    };
    
    xhr.send(fd);
}


function myCloudIsInsideZip(path) {
    // Returns true if the path contains ".zip/" or ends with ".zip"
    return /\.zip(\/|$)/i.test(path);
}

let myCloudTouchTimer;
let myCloudIsLongPress = false;

function myCloudHandleTouchStart(e, item, element, isSingleClickMode = false) {
    myCloudIsLongPress = false;
    
    // Stage 1: Short delay to enable Drag-and-Drop selection
    myCloudTouchTimer = setTimeout(() => {
        if (isSingleClickMode) {
            // Single Click Mode: Short Tap -> Navigate
            myCloudHandleEnter(item);
        } else {
            // Standard Mode: Short Tap -> Select
            if (!myCloudState.selectedFiles.includes(item.name)) {
                myCloudSelectRow(element, item.name, e);
            }
        }
        
        // Stage 2: Longer delay to switch to Context Menu
        myCloudTouchTimer = setTimeout(() => {
            myCloudIsLongPress = true;
            // Note: We don't call preventDefault here; 
            // the myCloudShowContextMenu handles its own positioning
            if (isSingleClickMode) {
                // Single Click Mode: Long Tap -> Select (Reverted)
                myCloudSelectRow(element, item.name, { ctrlKey: true });
                // Visual feedback?
            } else {
                // Standard Mode: Long Tap -> Context Menu
                myCloudShowContextMenu(e, item);
            }
        }, 600); 
    }, 200); 
}

function myCloudHandleTouchEnd() {
    clearTimeout(myCloudTouchTimer);
}

function myCloudHandleTouchMove(e) {
    // If the user moves their finger (scrolling), cancel the context menu timer
    clearTimeout(myCloudTouchTimer);
}

function myCloudShowTreeSelector(title, btnLabel, callback) {
    myCloudCloseContextMenus();
    const overlay = document.getElementById('myCloudModalOverlay');
    const modal = document.getElementById('myCloudModal');
    myCloudResetModal();

    // 1. Prepare Modal HTML
    const curLang = (typeof myCloudState !== 'undefined' && myCloudState.settings) ? myCloudState.settings.language : 'en';
    const isRtl = ['ar', 'fa', 'he', 'ur'].includes(curLang);
    modal.setAttribute('dir', isRtl ? 'rtl' : 'ltr');
    overlay.style.display = 'flex';
    modal.className = 'myCloudModal tree-selector';
    
    // Added New Folder Button to Header area
    modal.innerHTML = 
         '<div class="myCloudModalHeader" style="justify-content:space-between; align-items:center;">' +
             '<span>' + myCloudSvgLogo + ' <span style="font-weight:100;">- ' + title + '</span></span>' +
             '<button onclick="myCloudCloseModal()" style="background:transparent; border:none; font-size:20px; cursor:pointer; color:inherit; line-height:1;">✕</button>' +
         '</div>' +
         '<div class="myCloudModalBody">' +
             '<div style="margin-bottom:10px; display:flex; justify-content:space-between; align-items:center;">' +
                 '<span>' + (myCloud_LANG.select_dest || 'Select Destination') + '</span>' +
                 '<button id="btnTreeNewFolder" title="' + (myCloud_LANG.new_folder || 'New Folder') + '" style="background:transparent; border:1px solid #ccc; border-radius:3px; padding:2px 6px; cursor:pointer;" disabled>' +
                    '<span class="myCloudIcon" style="width:16px; height:16px;">' + myCloudSvg.newfolder + '</span>' +
                 '</button>' +
             '</div>' +
             '<div id="myCloudTreeSelector" class="myCloudTreeBox"></div>' +
             (typeof myCloudUserRole !== 'undefined' && myCloudUserRole === 'admin_mode' ? 
             '<div style="margin-top:10px;"><label style="font-size:13px; cursor:pointer; display:flex; align-items:center; gap:6px; color:var(--text-primary);"><input type="checkbox" id="ceTreePreserveRightsCb" checked class="myCloudCheckbox" style="margin:0;"> ' + (myCloud_LANG.preserve_rights || 'Preserve permissions & ownership') + '</label></div>' : '') +

             '<div class="myCloudButtons">' +
                 '<button onclick="myCloudCloseModal()">' + (myCloud_LANG.cancel || 'Cancel') + '</button>' +
                 '<button id="myCloudTreeOk" disabled>' + (myCloud_LANG.ok || 'OK') + '</button>' +
             '</div>' +
         '</div>';

    const st = myCloudState;

    // Open at current directory
    let selectedPath = st.currentDir || null;
    const expandedPaths = new Set(['/']);
    
    if (selectedPath && selectedPath !== '/') {
        let walker = '';
        selectedPath.split('/').filter(p => p).forEach(part => {
            walker += '/' + part;
            expandedPaths.add(walker);
        });
    }
    
    const hasSubDirs = (path) => {
        return st.allItems.some(i => {
            const p = i.name.substring(0, i.name.lastIndexOf('/') || 0) || '/';
            if (p !== path || i.size !== 'DIR') return false;
            
            const isEnc = i.isEncrypted === true || i.name.endsWith('.enc') || (typeof myCloudCrypto !== 'undefined' && myCloudCrypto.isDirEncrypted(i.name));
            if (isEnc) {
                const root = (typeof myCloudCrypto !== 'undefined') ? myCloudCrypto.getCryptoRoot(i.name) : null;
                if (!root || !myCloudCrypto.isDirUnlocked(root)) return false;
            }
            return true;
        });
    };
    
    // 2. Recursive Render Function
    const renderNode = (parentPath, container) => {
        container.innerHTML = ''; 
        
        const children = st.allItems.filter(i => {
             // Filter out children of recycle bin from Tree selector
             if (i.name.startsWith('/.recycle_bin/')) return false;

             const p = i.name.substring(0, i.name.lastIndexOf('/') || 0) || '/';
             if (p !== parentPath || i.size !== 'DIR') return false;

             const isEnc = i.isEncrypted === true || i.name.endsWith('.enc') || (typeof myCloudCrypto !== 'undefined' && myCloudCrypto.isDirEncrypted(i.name));
             if (isEnc) {
                 const root = (typeof myCloudCrypto !== 'undefined') ? myCloudCrypto.getCryptoRoot(i.name) : null;
                 if (!root || !myCloudCrypto.isDirUnlocked(root)) return false;
             }

             return true;
        });

        // Sort by decrypted names natively
        children.sort((a, b) => {
            let nameA = (st.pathNames && st.pathNames[a.name]) ? st.pathNames[a.name] : a.name.split('/').pop().replace(/\.enc$/, '');
            let nameB = (st.pathNames && st.pathNames[b.name]) ? st.pathNames[b.name] : b.name.split('/').pop().replace(/\.enc$/, '');
            return nameA.toLowerCase().localeCompare(nameB.toLowerCase());
        });

        const ul = document.createElement('ul');
        if (children.length === 0) {
             container.appendChild(ul); // Empty UL for potential new folders
             return;
        }

        children.forEach(item => {
            const li = document.createElement('li');
            const fullPath = item.name;
            let displayName = fullPath.split('/').pop();
            
            // [NEW] E2E: Display real name for open vaults
            if (typeof myCloudCrypto !== 'undefined' && myCloudCrypto.isDirEncrypted(fullPath)) {
                const root = myCloudCrypto.getCryptoRoot(fullPath);
                if (myCloudCrypto.isDirUnlocked(root)) {
                    displayName = (st.pathNames && st.pathNames[fullPath]) ? st.pathNames[fullPath] : displayName.replace(/\.enc$/, '');
                    displayName = displayName.replace(/^[🔓🔒 ️]\s*/, '');
                }
            }
            
            const rowDiv = document.createElement('div');
            rowDiv.className = 'tree-item';
            rowDiv.dataset.path = fullPath; // Store path for easy finding

            const toggle = document.createElement('span');
            toggle.className = 'tree-toggle';
            
            const isLoaded = st.loadedDirs.includes(fullPath);
            const childrenExist = hasSubDirs(fullPath);
            const isExpanded = expandedPaths.has(fullPath);
            const showArrow = !isLoaded || childrenExist;

            if (showArrow) {
                toggle.innerHTML = isExpanded ? '▾' : '▸'; 
                toggle.onclick = (e) => {
                    e.stopPropagation();
                    if (expandedPaths.has(fullPath)) {
                        expandedPaths.delete(fullPath);
                        toggle.innerHTML = '▸';
                        const childContainer = li.querySelector('.tree-children');
                        if (childContainer) childContainer.remove();
                    } else {
                        expandedPaths.add(fullPath);
                        toggle.innerHTML = '▾';
                        const childContainer = document.createElement('div');
                        childContainer.className = 'tree-children';
                        li.appendChild(childContainer);
                        
                        if (isLoaded) {
                            renderNode(fullPath, childContainer);
                        } else {
                            toggle.innerHTML = '⌛';
                            myCloudFetchDirectory(fullPath).then(() => {
                               if (hasSubDirs(fullPath)) {
                                   toggle.innerHTML = '▾';
                                   renderNode(fullPath, childContainer);
                               } else {
                                   toggle.innerHTML = '';
                                   toggle.style.cursor = 'default';
                                   toggle.onclick = null;
                                   renderNode(fullPath, childContainer);
                               }
                            });
                        }
                    }
                };
            } else {
                toggle.innerHTML = '';
                toggle.style.cursor = 'default';
            }

            const contentDiv = document.createElement('div');
            contentDiv.className = 'tree-content';
            if (fullPath === selectedPath) contentDiv.classList.add('selected');
            
            const iconSpan = document.createElement('span');
            iconSpan.className = 'tree-icon';
            iconSpan.innerHTML = myCloudIconFolder;

            const textSpan = document.createElement('span');
            textSpan.textContent = displayName;

            contentDiv.appendChild(iconSpan);
            contentDiv.appendChild(textSpan);

            contentDiv.onclick = () => {
                selectNode(fullPath, contentDiv);
            };

            rowDiv.appendChild(toggle);
            rowDiv.appendChild(contentDiv);
            li.appendChild(rowDiv);

            if (isExpanded && isLoaded) {
                 const childContainer = document.createElement('div');
                 childContainer.className = 'tree-children';
                 li.appendChild(childContainer);
                 renderNode(fullPath, childContainer);
            }

            ul.appendChild(li);
        });
        container.appendChild(ul);
    };

    // Helper to select a node logically and visually
    const selectNode = (path, domEl) => {
        modal.querySelectorAll('.tree-content.selected').forEach(el => el.classList.remove('selected'));
        if(domEl) domEl.classList.add('selected');
        
        selectedPath = path;
        
        const btnOk = document.getElementById('myCloudTreeOk');
        const btnNew = document.getElementById('btnTreeNewFolder');
        
        if(btnOk) { btnOk.disabled = false; btnOk.style.opacity = '1'; }
        
        // Enable New Folder button only if NOT in Recycle Bin
        if(btnNew) {
            btnNew.disabled = path.startsWith('/.recycle_bin');
            btnNew.style.opacity = btnNew.disabled ? '0.5' : '1';
        }
        modal.focus();
    };

    // 3. Render Root
    const container = document.getElementById('myCloudTreeSelector');
    
    let rootIsLocked = false;
    if (typeof myCloudCrypto !== 'undefined' && myCloudCrypto.isDirEncrypted('/')) {
        if (!myCloudCrypto.isDirUnlocked('/')) rootIsLocked = true;
    }

    const rootUl = document.createElement('ul'); 
    rootUl.style.paddingLeft = '0';
    
    if (!rootIsLocked) {
        const rootLi = document.createElement('li');
        
        const rootRow = document.createElement('div');
        rootRow.className = 'tree-item';
        rootRow.dataset.path = '/';
        
        const rootToggle = document.createElement('span');
        rootToggle.className = 'tree-toggle';
        rootToggle.innerHTML = '▾'; 
        rootToggle.onclick = (e) => {
            e.stopPropagation();
            const childContainer = rootLi.querySelector('.tree-children');
            if (childContainer) {
                childContainer.style.display = childContainer.style.display === 'none' ? 'block' : 'none';
                rootToggle.innerHTML = childContainer.style.display === 'none' ? '▸' : '▾';
            }
        };

        const rootContent = document.createElement('div');
        rootContent.className = 'tree-content';
        
        if (selectedPath === '/') {
            rootContent.classList.add('selected');
            setTimeout(() => {
                 const btnOk = document.getElementById('myCloudTreeOk');
                 const btnNew = document.getElementById('btnTreeNewFolder');
                 if(btnOk) { btnOk.disabled = false; btnOk.style.opacity = '1'; }
                 if(btnNew) { btnNew.disabled = false; btnNew.style.opacity = '1'; }
            }, 0);
        }
        
        rootContent.innerHTML = '<span class="tree-icon">' + myCloudIconFolder + '</span><span>/ (Root)</span>';
        rootContent.onclick = () => selectNode('/', rootContent);

        rootRow.appendChild(rootToggle);
        rootRow.appendChild(rootContent);
        rootLi.appendChild(rootRow);

        const rootChildrenContainer = document.createElement('div');
        rootChildrenContainer.className = 'tree-children';
        rootLi.appendChild(rootChildrenContainer);
        
        renderNode('/', rootChildrenContainer);
        
        rootUl.appendChild(rootLi);
    } else {
        // Root is locked, so we only render its children (which will be empty due to the lock)
        renderNode('/', rootUl);
    }
    
    container.appendChild(rootUl);
    
    // Scroll to selection
    setTimeout(() => {
        const selEl = container.querySelector('.tree-content.selected');
        if (selEl) selEl.scrollIntoView({block: 'center'});
        const btnOk = document.getElementById('myCloudTreeOk');
        if (btnOk && selectedPath) { btnOk.disabled = false; btnOk.style.opacity = '1'; }
    }, 100);

    // 4. "New Folder" Inline Logic
    document.getElementById('btnTreeNewFolder').onclick = () => {
        if (!selectedPath) return;

        let targetLi = null;
        if(selectedPath === '/') {
            targetLi = rootLi;
        } else {
            const allItems = container.querySelectorAll('.tree-item');
            for(let item of allItems) {
                if(item.dataset.path === selectedPath) {
                    targetLi = item.parentElement;
                    break;
                }
            }
        }

        if(!targetLi) return;

        expandedPaths.add(selectedPath);
        
        let childContainer = targetLi.querySelector('.tree-children');
        if(!childContainer) {
            childContainer = document.createElement('div');
            childContainer.className = 'tree-children';
            targetLi.appendChild(childContainer);
        }
        
        let ul = childContainer.querySelector('ul');
        if(!ul) {
            ul = document.createElement('ul');
            childContainer.appendChild(ul);
        }

        const li = document.createElement('li');
        li.className = 'ce-new-folder-node';
        li.innerHTML = 
            '<div class="tree-item">' +
                '<span class="tree-toggle"></span>' +
                '<div class="tree-content" style="padding:0;">' +
                    '<span class="tree-icon">' + myCloudIconFolder + '</span>' +
                    '<input type="text" class="myCloudInlineInput" style="height:22px; width:150px; font-size:13px; margin:0;">' +
                '</div>' +
            '</div>';
        
        if(ul.firstChild) ul.insertBefore(li, ul.firstChild);
        else ul.appendChild(li);

        const parentToggle = targetLi.querySelector('.tree-toggle');
        if(parentToggle) parentToggle.innerHTML = '▾';

        li.scrollIntoView({block: "nearest"});

        const input = li.querySelector('input');
        input.focus();

        const save = async () => {
            const name = input.value.trim();
            if (!name) { li.remove(); return; }

            // [NEW] E2E Encrypt folder name securely before sending to server
            let finalName = name;
            if (typeof myCloudCrypto !== 'undefined' && myCloudCrypto.isDirEncrypted(selectedPath)) {
                if (!myCloudCrypto.isDirUnlocked(selectedPath)) {
                    alert(L.dir_locked || 'Directory is locked.');
                    li.remove();
                    return;
                }
                finalName = await myCloudCrypto.encryptName(selectedPath, name);
            }

            // API Call
            myCloudAPI('mkdir', { parent: selectedPath, name: finalName }, (resp) => {
                if (resp.status === 'OK') {
                    myCloudFetchDirectory(selectedPath).then(() => {
                        renderNode(selectedPath, childContainer);
                        
                        const newFullPath = (selectedPath === '/' ? '' : selectedPath) + '/' + finalName;
                        
                        setTimeout(() => {
                            const allItems = container.querySelectorAll('.tree-item');
                            for(let item of allItems) {
                                if(item.dataset.path === newFullPath) {
                                    const content = item.querySelector('.tree-content');
                                    selectNode(newFullPath, content);
                                    item.scrollIntoView({block: "center"});
                                    modal.focus();
                                    break;
                                }
                            }
                        }, 50);
                    });
                } else {
                    li.remove(); 
                    alert(resp.msg);
                }
            });
        };

        input.onkeydown = (e) => {
            if(e.key === 'Enter') save();
            if(e.key === 'Escape') li.remove();
            e.stopPropagation(); 
        };
        
        input.onblur = () => { 
            if(!input.value.trim()) li.remove();
        };
    };

    // 5. Submit
    document.getElementById('myCloudTreeOk').onclick = () => {
        if (selectedPath) {
            document.getElementById('myCloudModalOverlay').style.display = 'none';
            const cb = document.getElementById('ceTreePreserveRightsCb');
            const preserve = cb ? cb.checked : true;
            callback(selectedPath, preserve);
        }
    };

    modal.setAttribute('tabindex', '-1'); 
    modal.style.outline = 'none';
    modal.focus();
    modal.onkeydown = (e) => {
       if (e.key === 'Escape') {
           document.getElementById('myCloudModalOverlay').style.display = 'none';
           return;
       }
       
       if (['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(e.key)) {
           if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName) || document.activeElement.isContentEditable) return;
           e.preventDefault();
           e.stopPropagation();

           const visibleItems = Array.from(container.querySelectorAll('.tree-content')).filter(el => el.offsetParent !== null);
           const current = container.querySelector('.tree-content.selected');
           let idx = visibleItems.indexOf(current);
           
           if (e.key === 'ArrowDown') {
               if (idx < visibleItems.length - 1) {
                   const target = visibleItems[idx + 1];
                   const row = target.closest('.tree-item');
                   if (row) selectNode(row.dataset.path, target);
                   target.scrollIntoView({block: 'nearest'});
               }
           } else if (e.key === 'ArrowUp') {
               if (idx > 0) {
                   const target = visibleItems[idx - 1];
                   const row = target.closest('.tree-item');
                   if (row) selectNode(row.dataset.path, target);
                   target.scrollIntoView({block: 'nearest'});
               }
           } else if (e.key === 'ArrowRight') {
               if (current) {
                   const row = current.closest('.tree-item');
                   const toggle = row.querySelector('.tree-toggle');
                   if (toggle && toggle.textContent.charCodeAt(0) === 9656) toggle.click();
               }
           } else if (e.key === 'ArrowLeft') {
               if (current) {
                   const row = current.closest('.tree-item');
                   const toggle = row.querySelector('.tree-toggle');
                   if (toggle && toggle.textContent.charCodeAt(0) === 9662) {
                       toggle.click();
                   } else {
                       const parentUl = row.closest('ul');
                       const parentLi = parentUl ? parentUl.closest('li') : null;
                       if (parentLi) {
                           const parentContent = parentLi.querySelector('.tree-item > .tree-content');
                           const parentRow = parentLi.querySelector('.tree-item');
                           if (parentContent && parentRow) {
                               selectNode(parentRow.dataset.path, parentContent);
                               parentContent.scrollIntoView({block: 'nearest'});
                           }
                       }
                   }
               }
           }
           return;
       }
       
        if (e.key === 'Enter') {
            if (document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
                const btn = document.getElementById('myCloudTreeOk');
                if (btn && !btn.disabled) {
                    e.preventDefault();
                    e.stopPropagation();
                    btn.click();
                }
            }
        }
    };    
}
  
  
function myCloudShowConflictModal(filename, callback) {
    const overlay = document.getElementById('myCloudModalOverlay');
    const modal = document.getElementById('myCloudModal');
    
    overlay.style.display = 'flex';
    modal.className = 'myCloudModal conflict'; 
	
	// SECURITY: Sanitize filename to prevent XSS
    const safeFilename = filename.replace(/&/g, "&amp;")
                                 .replace(/</g, "&lt;")
                                 .replace(/>/g, "&gt;")
                                 .replace(/"/g, "&quot;")
                                 .replace(/'/g, "&#039;");
    const canOverwrite = typeof window.myCloudActionAllowed === 'function' ? window.myCloudActionAllowed('overwrite') : true;
    const overwriteHtml = canOverwrite ? '<button onclick="window.myCloudConflictResolve(\'overwrite\')" style="font-size:13px; padding: 8px 16px; color:#e81123; border-color:#e81123;">' + myCloud_LANG.overwrite + '</button>' : '';

    
    modal.innerHTML = 
        '<div class="myCloudModalHeader" style="border-bottom:none; padding-bottom:0; justify-content:space-between; align-items:center;">' +
            '<span>' + myCloud_LANG.conflict_title + '</span>' +
            '<span class="myCloudClose" onclick="window.myCloudConflictResolve(null)" style="cursor:pointer; color:var(--text-secondary); font-size:18px; line-height:1;">✕</span>' +
        '</div>' +
        '<div class="myCloudModalBody" style="padding: 20px 24px;">' +
            '<div style="text-align:center; margin-bottom:20px;">' +
                '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#f0ad4e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
                    '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>' +
                    '<line x1="12" y1="9" x2="12" y2="13"></line>' +
                    '<line x1="12" y1="17" x2="12.01" y2="17"></line>' +
                '</svg>' +
            '</div>' +
            '<div style="font-size:14px; text-align:center; margin-bottom:25px; line-height: 1.5;">' +
                myCloud_LANG.conflict_msg + '<br><b>' + safeFilename + '</b>.' +
            '</div>' +
            '<div class="myCloudButtons" style="justify-content: center; gap: 10px; margin-top:0; flex-wrap: wrap;">' +
                overwriteHtml +
                '<button onclick="window.myCloudConflictResolve(\'keep_both\')" style="font-size:13px; padding: 8px 16px; color:#0078d4; border-color:#0078d4;">' + myCloud_LANG.keep_both + '</button>' +
                '<button onclick="window.myCloudConflictResolve(null)" style="font-size:13px; padding: 8px 16px;">' + myCloud_LANG.skip + '</button>' +
            '</div>' +
        '</div>';
    
    window.myCloudConflictResolve = (resolution) => {
        overlay.style.display = 'none';
        modal.innerHTML = '';
        delete window.myCloudConflictResolve; 
        callback(resolution);
    };

    modal.setAttribute('tabindex', '-1');
    modal.style.outline = 'none';
    modal.focus();
    modal.onkeydown = (e) => {
       if (e.key === 'Escape') { window.myCloudConflictResolve(null); return; }       
    };
}


function myCloudInitSidebarResizer() {
    const resizer = document.querySelector('.myCloudResizer');
    const sidebar = document.querySelector('.myCloudTree');
    const container = document.querySelector('.myCloudBody');

    if (!resizer || !sidebar || !container) return;
    if (resizer.dataset.initialized) return;
    resizer.dataset.initialized = 'true';

    // Helper: Save size to server profile
    const saveSize = (finalSize) => {
        const devKey = myCloudGetCurrentDeviceKey();
        if (myCloudState.settings && myCloudState.settings[devKey]) {
            myCloudState.settings[devKey].sidebarSize = Math.round(finalSize);
            myCloudSaveSettings(); 
        }
    };

    // Shared Calculation Logic
    const handleResize = (clientPos, startPos, startSize, totalSize, isVertical) => {
        let newSize = startSize + (clientPos - startPos);
        const minSize = isVertical ? 100 : 150;
        if (newSize < minSize) newSize = minSize;
        if (newSize > totalSize * 0.6) newSize = totalSize * 0.6;

        if (isVertical) {
            sidebar.style.height = newSize + 'px';
            sidebar.style.width = '100%';
        } else {
            sidebar.style.width = newSize + 'px';
            sidebar.style.height = '100%';
        }
        sidebar.style.flex = 'none';
    };

    // --- MOUSE (Desktop) ---
    resizer.addEventListener('mousedown', (e) => {
        e.preventDefault();
        const isVertical = window.getComputedStyle(container).flexDirection === 'column';
        const startPos = isVertical ? e.pageY : e.pageX;
        const startSize = isVertical ? sidebar.offsetHeight : sidebar.offsetWidth;
        const totalSize = isVertical ? container.offsetHeight : container.offsetWidth;

        const onMouseMove = (moveEvent) => {
            const currentPos = isVertical ? moveEvent.pageY : moveEvent.pageX;
            handleResize(currentPos, startPos, startSize, totalSize, isVertical);
        };

        const onMouseUp = () => {
            document.removeEventListener('mousemove', onMouseMove);
            document.removeEventListener('mouseup', onMouseUp);
            document.body.style.cursor = 'default';
            resizer.classList.remove('active');
            
            // [NEW] Save on Release
            const finalSize = isVertical ? sidebar.offsetHeight : sidebar.offsetWidth;
            saveSize(finalSize);
        };

        document.addEventListener('mousemove', onMouseMove);
        document.addEventListener('mouseup', onMouseUp);
        document.body.style.cursor = isVertical ? 'row-resize' : 'col-resize';
        resizer.classList.add('active');
    });

    // --- TOUCH (Mobile) ---
    resizer.addEventListener('touchstart', (e) => {
        e.preventDefault();
        const isVertical = window.getComputedStyle(container).flexDirection === 'column';
        const touch = e.touches[0];
        const startPos = isVertical ? touch.pageY : touch.pageX;
        const startSize = isVertical ? sidebar.offsetHeight : sidebar.offsetWidth;
        const totalSize = isVertical ? container.offsetHeight : container.offsetWidth;

        const onTouchMove = (moveEvent) => {
            const moveTouch = moveEvent.touches[0];
            const currentPos = isVertical ? moveTouch.pageY : moveTouch.pageX;
            handleResize(currentPos, startPos, startSize, totalSize, isVertical);
        };

        const onTouchEnd = () => {
            document.removeEventListener('touchmove', onTouchMove);
            document.removeEventListener('touchend', onTouchEnd);
            resizer.classList.remove('active');

            // [NEW] Save on Release
            const finalSize = isVertical ? sidebar.offsetHeight : sidebar.offsetWidth;
            saveSize(finalSize);
        };

        document.addEventListener('touchmove', onTouchMove, { passive: false });
        document.addEventListener('touchend', onTouchEnd);
        resizer.classList.add('active');
    }, { passive: false });

    // Reset layout logic on rotate
    let lastDir = window.getComputedStyle(container).flexDirection;
    window.addEventListener('resize', () => {
        const newDir = window.getComputedStyle(container).flexDirection;
        if (newDir !== lastDir) {
            // If orientation changes axis, revert to saved setting for that context
            myCloudRestoreSidebarSize(); 
            lastDir = newDir;
        }
    });    
}


// Helper: Applies the .ce-dark-mode class to specific myCloud containers (not body)
function myCloudApplyTheme() {
	
    // 1. READ the cookie
    var match = document.cookie.match(/(^| )myCloudDarkMode=([^;]+)/);
    var isDark = (match && match[2] === '1'); 
    
    var cls = 'ce-dark-mode';
    
    // 2. IDENTIFY the target containers
    var targets = [
        'myCloudContainer', 
        'myCloudModalOverlay', 
        'myCloudPreviewOverlay', 
        'myCloudLoadingPopup', 
        'myCloudContextMenu',
        'myCloudVerOverlay',
        'myCloudFloatingMenu',
        'myCloudSettingsPanel',
        'myCloudFavoritesPanel',
        'myCloudPaletteOverlay',
        'myCloudTagDropdown',
        'myCloudEditor_modal_wrap',
        'myCloudAlertOverlay'
    ];

    // 3. APPLY or REMOVE based on the cookie value
    targets.forEach(function(id) {
        var el = document.getElementById(id);
        if (el) {
            if (isDark) {
                el.classList.add(cls); 
            } else {
                el.classList.remove(cls); 
            }
        }
    });
}


function myCloudShowProperties(path) {
    myCloudCloseContextMenus();
    myCloudPropStack = [];
    myCloudLoadProperties(path);
}

function myCloudLoadProperties(path) {
    const overlay = document.getElementById('myCloudModalOverlay');
    const modal = document.getElementById('myCloudModal');

    // Remove the grey dimmed background
	overlay.style.backgroundColor = 'rgba(0,0,0,0.2)';
	
    // Setup Modal
    overlay.style.display = 'flex';
    modal.className = 'myCloudModal prop-modal';
    // Specific styling for properties to look like Windows
    modal.style.maxWidth = '500px';
    modal.style.height = 'auto';
    
    const title = path.split('/').pop() || '/';
    
    modal.innerHTML = 
        '<div class="myCloudModalHeader">' +
            '<span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">' + myCloud_LANG.prop_of + ' "' + title + '"</span>' +
            '<span class="myCloudClose" onclick="myCloudCloseModal()">✕</span>' +
        '</div>' +
        '<div class="myCloudModalBody" id="myCloudPropBody" style="padding:0; min-height:400px; display:flex; align-items:center; justify-content:center;">' +
             '<div class="myCloud-spinner dark"></div>' +
        '</div>';

    // Fetch Recursive Stats
    const fd = new URLSearchParams();
    fd.append('myCloud_action', 'get_dir_stats');
    fd.append('myCloud_key', myCloudState.key);
    fd.append('myCloud_token', myCloudCsrfToken);
    fd.append('path', path);

    fetch('', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if(res.status === 'OK') {
            myCloudRenderProperties(path, res.data);
        } else {
            document.getElementById('myCloudPropBody').innerHTML = '<div style="padding:20px; color:red;">' + res.msg + '</div>';
        }
    })
    .catch(() => {
        const body = document.getElementById('myCloudPropBody');
        if(body) body.innerHTML = '<div style="padding:20px; color:red;">Connection Error</div>';
    });
}

function myCloudRenderProperties(path, data) {
    const body = document.getElementById('myCloudPropBody');
    if(!body) return;
	
	const safePath = typeof myCloudEscapeHtml === 'function' ? myCloudEscapeHtml(path) : path.replace(/</g, "&lt;").replace(/>/g, "&gt;");
	    
    // Header Info
    const html = 
        '<div class="myCloud-prop-stats" style="padding:15px; border-bottom:1px solid var(--border-default);">' +
            '<div style="display:flex; align-items:center; gap:15px; margin-bottom:22px; font-size:13px;">' +
                '<div style="width:24px; height:24px; flex-shrink:0;">' + myCloudIconFolder + '</div>' +
                '<div style="display:flex; flex-direction:column;">' +
                    '<span style="color:var(--text-secondary); white-space:nowrap; margin-bottom: 4px;">' + myCloud_LANG.full_path + ':</span>' +
                    '<span style="font-weight:600; color:var(--text-primary); word-break: break-all;">' + safePath + '</span>' +
                '</div>' +
            '</div>' +
            '<hr style="border:0; border-top:1px solid var(--border-strong)">' +
            '<div style="display:flex; justify-content:space-between; margin-top:25px; margin-bottom:5px; font-size:13px;">' +
                '<span style="color:var(--text-secondary); margin-bottom: 7px;">' + myCloud_LANG.size_uc + ':</span>' +
                '<span style="font-weight:600; color:var(--text-primary);">' + myCloudFormatBytes(data.size) + ' (' + data.size.toLocaleString() + ' bytes)</span>' +
            '</div>' +
            '<div style="display:flex; justify-content:space-between; margin-bottom:5px; font-size:13px;">' +
                '<span style="color:var(--text-secondary);">' + myCloud_LANG.contains_uc + ':</span>' +
                '<span style="font-weight:600; color:var(--text-primary);">' + data.files.toLocaleString() + ' ' + myCloud_LANG.files_uc + '</span>' +
            '</div>' +
            '<div style="display:flex; justify-content:space-between; margin-bottom:5px; font-size:13px;">' +
                '<span style="color:var(--text-secondary);"></span>' +
                '<span style="font-weight:600; color:var(--text-primary);">' + data.dirs.toLocaleString() + ' ' + myCloud_LANG.folders_uc + '</span>' +
            '</div>' +
        '</div>' +
        '<hr style="border:0; border-top:1px solid var(--border-strong)">' +
        '<div class="myCloud-treemap-container" style="padding:10px; height:320px; display:flex; flex-direction:column;">' +
            '<div class="myCloud-tm-nav" style="display:flex; gap:10px; margin-bottom:8px; align-items:center;">' +
			 '<button id="myCloudPropUpBtn" class="myCloud-tm-btn" disabled>⮜ Back</button>' +
                '<span style="font-size:11px; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.5px;">' + 
                    myCloud_LANG.click_nav + 
                '</span>' +
            '</div>' +
            '<div id="myCloudTreemap" class="myCloud-treemap-canvas" style="flex:1; position:relative; border:1px solid var(--border-default); overflow:hidden;"></div>' +
        '</div>';
    
    body.style.display = 'block';
    body.innerHTML = html;

    // Up Button Logic
    const btn = document.getElementById('myCloudPropUpBtn');
    if(btn && myCloudPropStack.length > 0) {
        btn.disabled = false;
        btn.onclick = () => {
            const parentPath = myCloudPropStack.pop();
            myCloudLoadProperties(parentPath);
        };
    }

    // === NEW LOGIC: Add "Files" block ===
    // 1. Get existing children (subdirectories)
    let tmItems = data.children ? [...data.children] : [];

    // 2. Calculate size of files in THIS directory
    // (Total Size of Folder) - (Sum of sizes of all sub-folders)
    const childrenSize = tmItems.reduce((acc, item) => acc + item.size, 0);
    const filesSize = data.size - childrenSize;

    // 3. If there is remaining size, that represents the files
    if (filesSize > 0) {
        tmItems.push({
            name: myCloud_LANG.files_uc, 
            size: filesSize,
            type: 'files_group'
        });
    }

    // Render Treemap if we have ANY items (dirs or files)
    if (tmItems.length > 0) {
        myCloudDrawTreemap(tmItems, document.getElementById('myCloudTreemap'), data.size, path);
    } else {
        document.getElementById('myCloudTreemap').innerHTML = 
			'<div style="padding:20px; text-align:center; color:var(--text-secondary); margin-top:100px;">' + myCloud_LANG.empty_lbl + '</div>';
    }
}

function myCloudDrawTreemap(items, container, totalSize, currentPath) {
    // Sort items by size descending
    items.sort((a, b) => b.size - a.size);
    
	const colors = [
	'#5b9bd5', '#ed7d31', '#a5a5a5', '#ffc000',
	'#4472c4', '#70ad47', '#255e91', '#9e480e',
	
	// additional distinct colors
	'#2f5597', 
	'#548235', 
	'#7f6000', 
	'#7030a0', 
	'#c00000', 
	'#00b0f0', 
	'#bf9000', 
	'#a64d79'  
	];


    // Helper to render rectangles recursively
    const renderRect = (rectItems, x, y, w, h) => {
        if (rectItems.length === 0) return;

        // If only 1 item left, draw it
        if (rectItems.length === 1) {
            const item = rectItems[0];
            const div = document.createElement('div');
            div.className = 'myCloud-tm-node';
            div.style.position = 'absolute';
            div.style.left = x + '%';
            div.style.top = y + '%';
            div.style.width = w + '%';
            div.style.height = h + '%';
            div.style.boxSizing = 'border-box';
            div.style.border = '1px solid white';
            
            // Color logic
            if (item.type === 'files_group') {
                div.style.backgroundColor = '#607D8B'; 
            } else {
                let hash = 0;
                for(let i=0;i<item.name.length;i++) hash = item.name.charCodeAt(i) + ((hash << 5) - hash);
                const color = colors[Math.abs(hash) % colors.length];
                div.style.backgroundColor = color;
            }
            
            div.style.color = 'white';
            div.style.fontSize = '11px';
            div.style.overflow = 'hidden';
            div.style.display = 'flex';
            div.style.alignItems = 'center';
            div.style.justifyContent = 'center';
            div.style.textAlign = 'center';
            div.style.cursor = 'pointer';
            div.style.textShadow = '0 1px 2px rgba(0,0,0,0.5)';
            div.title = item.name + ' (' + myCloudFormatBytes(item.size) + ')';
            
            div.innerHTML = '<span style="pointer-events:none; padding:2px;">' + item.name + '</span>';

            // Drill down
            if (item.type === 'dir') {
                 div.onclick = (e) => {
                    e.stopPropagation();
                    myCloudPropStack.push(currentPath);
                    const newPath = currentPath.replace(/\/$/, '') + '/' + item.name;
                    myCloudLoadProperties(newPath);
                 };
            } else if (item.type === 'files_group') {
                 // Files are not clickable/drillable
                 div.style.cursor = 'default';
            } else {
                 div.style.opacity = '0.8'; 
            }

            container.appendChild(div);
            return;
        }

        // Split Logic (Squarified-ish / Binary Split)
        let halfSize = 0;
        let subTotal = rectItems.reduce((acc, i) => acc + i.size, 0);
        let splitIdx = 0;

        for (let i = 0; i < rectItems.length; i++) {
            halfSize += rectItems[i].size;
            if (halfSize >= subTotal / 2) {
                splitIdx = i + 1;
                break;
            }
        }
        
        if(splitIdx === 0 && rectItems.length > 0) splitIdx = 1;

        const groupA = rectItems.slice(0, splitIdx);
        const groupB = rectItems.slice(splitIdx);
        const sizeA = groupA.reduce((acc, i) => acc + i.size, 0);

        if (w >= h) {
            // Split Vertically
            const wA = (subTotal > 0) ? (sizeA / subTotal) * w : 0;
            renderRect(groupA, x, y, wA, h);
            renderRect(groupB, x + wA, y, w - wA, h);
        } else {
            // Split Horizontally
            const hA = (subTotal > 0) ? (sizeA / subTotal) * h : 0;
            renderRect(groupA, x, y, w, hA);
            renderRect(groupB, x, y + hA, w, h - hA);
        }
    };

    renderRect(items, 0, 0, 100, 100);
}

	/**
     * Shows the version info in a modern Windows 11 style modal
     */
function myCloudVerShowInfo() {
        // json_encode adds the surrounding double quotes automatically.
        const safeInfoText = window.myCloudVersionInfo || "Version info could not be loaded.";


        // Create Modal Overlay
        const overlay = document.createElement('div');
        overlay.id = 'myCloudVerOverlay';
        overlay.className = 'myCloudVer-modal-overlay';
        
        // Create Modal Content
        overlay.innerHTML = 
            '<div class="myCloudVer-modal">' +
                '<div class="myCloudVer-modal-header">' +
                    '<span><span style="font-weight: 100;">' + myCloudSvgLogo + '&nbsp;-&nbsp;Changelog</span></span>' +
                    '<button onclick="document.getElementById(\'myCloudVerOverlay\').remove()">✕</button>' +
                '</div>' +
                '<div class="myCloudVer-modal-body">' +
                    '<pre class="myCloudVer-pre">' + safeInfoText + '</pre>' +
                '</div>' +
                '<div class="myCloudVer-modal-footer">' +
                    '<button class="myCloudVer-btn-primary" onclick="document.getElementById(\'myCloudVerOverlay\').remove()">Close</button>' +
                '</div>' +
            '</div>';
        
        document.body.appendChild(overlay);
		
        if (typeof myCloudApplyTheme === 'function') {
            myCloudApplyTheme();
        }


        // Close on clicking overlay background
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) overlay.remove();
        });
    }

// [NEW] Recursive Directory Scanner for Drag & Drop
function myCloudScanItems(items, targetDir) {
    let totalFiles = 0;

    const traverse = (entry, path = '') => {
        if (entry.isFile) {
            entry.file(file => {
                // Attach the relative path to the file object for the uploader
                file.uploadRelativePath = path;
                myCloudUploadFile(file, targetDir);
            });
        } else if (entry.isDirectory) {
            const dirReader = entry.createReader();
            const readEntries = () => {
                dirReader.readEntries(entries => {
                    if (entries.length > 0) {
                        entries.forEach(e => traverse(e, path + entry.name + '/'));
                        readEntries(); 
                    }
                });
            };
            readEntries();
        }
    };

    for (let i = 0; i < items.length; i++) {
        const item = items[i].webkitGetAsEntry ? items[i].webkitGetAsEntry() : items[i].getAsEntry ? items[i].getAsEntry() : null;
        if (item) traverse(item);
    }
}


/* ==========================================
   KEYBOARD NAVIGATION & CLIPBOARD SHORTCUTS
   ========================================== */
// Global Clipboard State (Buffer)
window.myCloudClipboard = { action: null, files: [] };

// 6. Modified InitNav (Removing Commander Tab logic to prevent conflicts)
function myCloudInitKeyboardNav() {
    const container = document.getElementById('myCloudContainer');
    const tree = document.querySelector('.myCloudTree');
    const details = document.querySelector('.myCloudDetails');

    if (!container) return;
	
    if (container.dataset.keyNavBound === 'true') return;
    container.dataset.keyNavBound = 'true';

    if (tree) { tree.setAttribute('tabindex', '0'); tree.style.outline = 'none'; }
    if (details) { details.setAttribute('tabindex', '0'); details.style.outline = 'none'; }

    // GLOBAL KEY HANDLER
    container.addEventListener('keydown', (e) => {
        // --- TAB KEY SWITCHING ---
        if (e.key === 'Tab') {
            const st = myCloudState;
            
            if (st.isCommanderMode) return;
        
            // 2. STANDARD MODE LOGIC (Tree <-> List)
            if (tree && details) {
                e.preventDefault(); 
                if (document.activeElement === tree || tree.contains(document.activeElement)) {
					details.focus();
                    // Set visual focus to first item if nothing selected, without selecting it
                    if (st.selectedFiles.length === 0) myCloudSelectVisualRow(0, null, false, false, true);
                } else {
                    tree.focus();
                        if (!document.querySelector('.tree-focus')) {
                            const currentTreeNode = document.querySelector('.myCloudTreeList div[data-fullpath="' + CSS.escape(st.currentDir) + '"]');
                            if (currentTreeNode) myCloudSetTreeFocus(currentTreeNode);
                        }
                }
            }
        }
    }, true);
    
    // COMMAND PALETTE SHORTCUT (Ctrl+P / Cmd+P)
    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'p') {
            if (document.getElementById('myCloudPaletteOverlay')) return; // already open
            e.preventDefault(); // Prevent browser print dialog
            e.stopPropagation();
            myCloudShowCommandPalette();
        }
    }, true);


    // 2. TREE KEYS
    tree.addEventListener('keydown', (e) => {
        if (e.key === 'Tab') return; // Handled by global
        if (document.activeElement !== tree && !tree.contains(document.activeElement)) return;
        
		e.stopPropagation();
		
        const visibleNodes = Array.from(tree.querySelectorAll('li > div')).filter(d => d.offsetParent !== null);
        if (visibleNodes.length === 0) return;
        
        let idx = visibleNodes.findIndex(n => n.classList.contains('tree-focus'));
        if (idx === -1) idx = visibleNodes.findIndex(n => n.parentElement.classList.contains('selectedFolder')) || 0;

        const node = visibleNodes[idx];
        const path = node.dataset.fullpath;
        const hasKids = node.parentElement.querySelector('ul') !== null;
        const isOpen = hasKids && node.parentElement.querySelector('ul').style.display !== 'none';
		e.stopPropagation();
		
        switch (e.key) {
            case 'ArrowDown': e.preventDefault(); if (idx < visibleNodes.length - 1) myCloudSetTreeFocus(visibleNodes[idx + 1]); break;
            case 'ArrowUp':   e.preventDefault(); if (idx > 0) myCloudSetTreeFocus(visibleNodes[idx - 1]); break;
            case 'ArrowRight':e.preventDefault(); hasKids && !isOpen ? myCloudToggleTreeDir(path) : (idx < visibleNodes.length - 1 && myCloudSetTreeFocus(visibleNodes[idx + 1])); break;
            case 'ArrowLeft': e.preventDefault(); isOpen ? myCloudToggleTreeDir(path) : (node.parentElement.parentElement.tagName === 'LI' && myCloudSetTreeFocus(node.parentElement.parentElement.parentElement.querySelector('div'))); break;
            case 'Enter':     e.preventDefault(); 
                if (myCloudState.currentDir !== path) {
                    myCloudHandleEnter({ name: path, size: 'DIR' });
                } else { 
                     myCloudToggleTreeDir(path);
                }
                break;
        }
    });

    // 3. GLOBAL KEYBOARD HANDLER (Replaces previous local handler)
    // Using 'document' ensures keys work even if specific focus is lost
    document.addEventListener('keydown', (e) => {
        // --- GUARD CLAUSES ---
        // Ignore if typing in an input/textarea
        if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName) || document.activeElement.isContentEditable) return;
        // Ignore if Commander Mode (it has its own handler)
        if (myCloudState.isCommanderMode) return;
        // Ignore if Terminal is open and focused
        if (document.getElementById('myCloudTerminalWrap') && document.getElementById('myCloudTerminalWrap').contains(document.activeElement)) return;
        // Ignore if a Modal is open (e.g. Settings, Search, New Folder)
        if (document.getElementById('myCloudModalOverlay').style.display === 'flex') return;
        // Ignore Tab (Global Focus switching)
        if (e.key === 'Tab') return;

        if (tree && (document.activeElement === tree || tree.contains(document.activeElement))) {
              if (e.key === 'Backspace') {
                 e.preventDefault();
                 myCloudGoUp();
              }
             return;
         }

        // --- SPACEBAR QUICK LOOK ---
        if (e.code === 'Space') {
            e.preventDefault();
            const previewOverlay = document.getElementById('myCloudPreviewOverlay');
            if (previewOverlay && previewOverlay.style.display !== 'none') {
                myCloudClosePreview();
            } else if (myCloudState.selectedFiles.length === 1) {
                const f = myCloudState.selectedFiles[0];
                const ext = f.split('.').pop().toLowerCase();
                const item = myCloudState.allItems.find(i => i.name === f);
                if (item && item.size !== 'DIR') {
                    if (typeof myCloudIsPreviewable === 'function' && myCloudIsPreviewable(ext)) {
                        myCloudDownloadFile(f, f.split('/').pop(), true);
                    }
                }
            }
            return;
        }

        // Get visible items
        const rows = Array.from(document.querySelectorAll('.myCloudRow, .myCloud-symbol-item'));
        if (rows.length === 0) return;

        // --- CALCULATE GRID LAYOUT ---
        // Determines how many columns are currently visible for "Spatial Navigation"
        let columns = 1; // Default to List View (1 column)
        
        if (myCloudState.viewMode === 'symbol') {
            const container = document.querySelector('.myCloud-symbol-grid');
            if (container && rows.length > 0) {
                const itemWidth = rows[0].offsetWidth; // includes border/padding
                // Add gap calculation if needed, but offsetWidth usually suffices for rough grid math
                const containerWidth = container.clientWidth;
                // Calculate columns: Container Width / Item Width
                // We add a tiny buffer to itemWidth to account for gaps
                const gap = 6; // matched from CSS .myCloud-symbol-grid gap
                if (itemWidth > 0) {
                    columns = Math.floor((containerWidth + gap) / (itemWidth + gap));
                }
                if (columns < 1) columns = 1;
            }
        }

        // Current Cursor Position
        let idx = myCloudState.visualCursorIndex;
        if (idx < 0 || idx >= rows.length) idx = 0;

        const role = typeof myCloudUserRole !== 'undefined' ? myCloudUserRole : 'no-access';
        
        // Helper to move selection
        const moveSel = (newIndex) => {
            e.preventDefault(); // Stop Browser Scroll
            e.stopPropagation();
            if (newIndex < 0) newIndex = 0;
            if (newIndex >= rows.length) newIndex = rows.length - 1;
            myCloudSelectVisualRow(newIndex, rows, e.shiftKey, e.ctrlKey);
        };

        switch (e.key) {
            // --- DIRECTIONAL NAVIGATION ---
            case 'ArrowDown': 
                moveSel(idx + columns);
                break;
            case 'ArrowUp': 
                moveSel(idx - columns); 
                break;
            case 'ArrowRight':
                if (columns > 1) { // In Grid: Next Item
                    moveSel(idx + 1);
                } else {
                    // In List: Right usually does nothing or expands tree. 
                    // Windows Explorer: Right arrow in list view does nothing if flat file.
                    e.preventDefault(); 
                }
                break;
            case 'ArrowLeft':
                if (columns > 1) { // In Grid: Prev Item
                    moveSel(idx - 1);
                } else {
                    e.preventDefault();
                }
                break;

            case 'PageDown': moveSel(idx + (columns * 4)); break; // Jump ~4 rows
            case 'PageUp':   moveSel(idx - (columns * 4)); break;
            case 'Home':     moveSel(0); break;
            case 'End':      moveSel(rows.length - 1); break;
            
            // --- ACTIONS ---
            case 'Enter': 
                e.preventDefault(); 
                if (rows[idx]) rows[idx].ondblclick(); 
                break;
            case 'Backspace': 
                e.preventDefault(); 
                myCloudGoUp(); 
                break;
            
            case 'a': 
                if (e.ctrlKey) { 
                    e.preventDefault(); 
                    // Select all except ".." (if present)
                    myCloudState.selectedFiles = rows.map(r => r.dataset.fullpath).filter(p => p !== '..');
                    myCloudRenderUI(); 
                } 
                break;

            case 'Delete': if(myCloudState.selectedFiles.length > 0 && window.myCloudActionAllowed('delete')) { e.preventDefault(); myCloudAction_Delete(); } break;
            case 'F2': if(myCloudState.selectedFiles.length === 1 && window.myCloudActionAllowed('rename')) { e.preventDefault(); myCloudAction_Rename(); } break;
            case 'F5': 
            case 'r': if (e.key === 'F5' || (e.key === 'r' && e.ctrlKey)) { e.preventDefault(); myCloudFetchDirectory(myCloudState.currentDir); } break;

            // --- CLIPBOARD ---
            case 'c': if (e.ctrlKey) { 
                e.preventDefault();
                if (myCloudState.selectedFiles.length > 0) {
                    window.myCloudClipboard = { action: 'copy', files: [...myCloudState.selectedFiles] };
                    document.querySelectorAll('.myCloudRow, .myCloud-symbol-item').forEach(r => r.style.opacity = '1');
                }
            } break;
            case 'x': if (e.ctrlKey && window.myCloudActionAllowed('move')) {
                e.preventDefault();
                if (myCloudState.selectedFiles.length > 0) {
                    window.myCloudClipboard = { action: 'move', files: [...myCloudState.selectedFiles] };
                    document.querySelectorAll('.myCloudRow, .myCloud-symbol-item').forEach(r => r.style.opacity = '1');
                    myCloudState.selectedFiles.forEach(path => {
                        const r = document.querySelector('.myCloudRow[data-fullpath="' + path + '"], .myCloud-symbol-item[data-fullpath="' + path + '"]');
                        if(r) r.style.opacity = '0.5';
                    });
                }
            } break;
            case 'v': if (e.ctrlKey && window.myCloudActionAllowed('move')) {
                e.preventDefault();
                if (window.myCloudClipboard && window.myCloudClipboard.files.length > 0) {
                    const dest = myCloudState.currentDir;
                    const newPaths = window.myCloudClipboard.files.map(f => {
                         const name = f.split('/').pop(); return dest === '/' ? '/' + name : dest + '/' + name;
                    });
                    const executePaste = (preserve) => {
                        myCloudBatchProcess(window.myCloudClipboard.action, window.myCloudClipboard.files, dest, preserve)
                        .then(() => {
                            myCloudState.selectedFiles = newPaths; 
                            myCloudRenderUI(); 
                            setTimeout(() => {
                                 const r = document.querySelector('.myCloudRow[data-fullpath="' + newPaths[0] + '"], .myCloud-symbol-item[data-fullpath="' + newPaths[0] + '"]');
                                 if(r) { 
                                     r.scrollIntoView({block: 'center'}); 
                                     myCloudState.visualCursorIndex = Array.from(document.querySelectorAll('.myCloudRow, .myCloud-symbol-item')).indexOf(r); 
                                 }
                            }, 50);
                            if (window.myCloudClipboard.action === 'move') window.myCloudClipboard = { action: null, files: [] };
                        });
                    };
                    if (typeof myCloudUserRole !== 'undefined' && myCloudUserRole === 'admin_mode') {
                        myCloudShowDragConfirm(window.myCloudClipboard.action, window.myCloudClipboard.files, dest, executePaste);
                    } else {
                        executePaste(true);
                    }
                }
            } break;

            // --- TYPE TO SEEK ---
            default:
                if (e.key.length === 1 && !e.ctrlKey && !e.altKey && !e.metaKey) {
                    clearTimeout(window.ceTypeTimer);
                    window.ceTypeBuffer = (window.ceTypeBuffer || '') + e.key.toLowerCase();
                    const matchIdx = rows.findIndex(r => {
                        const nameSpan = r.querySelector('.ce-name-text, .ce-sym-label');
                        return nameSpan && nameSpan.textContent.toLowerCase().startsWith(window.ceTypeBuffer);
                    });
                    if (matchIdx !== -1) myCloudSelectVisualRow(matchIdx, rows);
                    window.ceTypeTimer = setTimeout(() => { window.ceTypeBuffer = ''; }, 800);
                }
                break;
        }
    });
}

// --- NAVIGATION & SELECTION HELPERS ---

window.myCloudMigrateDirectory = async function(rootDirPath, silent = false) {
    if (!silent) myCloudCreateProgressUI(myCloud_LANG.migrating ?? 'Scanning directory...');
    
    const fd = new URLSearchParams({
        myCloud_action: 'list',
        myCloud_key: myCloudState.key,
        myCloud_token: typeof myCloudCsrfToken !== 'undefined' ? myCloudCsrfToken : '',
        path: rootDirPath,
        depth: 100 
    });
    
    const res = await fetch('', { method: 'POST', body: fd }).then(r => r.json());
    if (res.status !== 'OK') {
        myCloudCloseProgressUI();
        return myCloudShowAlert('Error', 'Failed to scan directory for migration.');
    }
    
    
    const filesToEncrypt = res.data.filter(i => 
        !i.name.endsWith('.enc') && 
        !i.name.endsWith('.mycloud_crypto_salt')
    );
    
    if (filesToEncrypt.length === 0) {
        if (!silent) {
            myCloudCloseProgressUI();
            return myCloudShowAlert('Done', 'No existing files needed encryption.');
        }
        myCloudFetchDirectory(rootDirPath);
        return;
    }

    // Process deepest paths first to avoid breaking parent directory references during rename
    filesToEncrypt.sort((a, b) => b.name.length - a.name.length);
    for (let i = 0; i < filesToEncrypt.length; i++) {
        const file = filesToEncrypt[i];
        const filename = file.name.split('/').pop();
        const fileParent = file.name.substring(0, file.name.lastIndexOf('/')) || '/';
        const isDir = file.size === 'DIR';
		
        myCloudUpdateProgressUI((i / filesToEncrypt.length) * 100);
        const textEl = document.getElementById('myCloudProgressText');
        let msg = myCloud_LANG.encrypting_file ?? 'Encrypting %s1/%s2: %s3';
        msg = msg.replace('%s1', i+1).replace('%s2', filesToEncrypt.length).replace('%s3', filename);
        if (textEl) textEl.textContent = msg;
        
        try {
            const encName = await myCloudCrypto.encryptName(rootDirPath, filename);

            if (isDir) {
                const renFd = new URLSearchParams({ myCloud_action: 'rename', myCloud_key: myCloudState.key, myCloud_token: typeof myCloudCsrfToken !== 'undefined' ? myCloudCsrfToken : '', src: file.name, newName: encName });
                const renRes = await fetch('', { method: 'POST', body: renFd }).then(r => r.json());
                if (renRes.status !== 'OK') throw new Error("Rename failed");
            } else {            
				const dlFd = new URLSearchParams({
					myCloud_action: 'get_download_token',
					myCloud_key: myCloudState.key,
					myCloud_token: typeof myCloudCsrfToken !== 'undefined' ? myCloudCsrfToken : '',
					path: file.name,
					filename: filename,
					preview: '0'
				});
				const tokenRes = await fetch('', { method: 'POST', body: dlFd }).then(r => r.json());
				if (tokenRes.status !== 'OK') throw new Error("Could not get download token");
				
				const blob = await fetch('?myCloud_token=' + tokenRes.token).then(r => r.blob());
				
				const plainFileObj = new File([blob], filename, { type: blob.type });
				const encBlob = await myCloudCrypto.encryptFile(rootDirPath, plainFileObj);
				
				const upFd = new FormData();
				upFd.append('myCloud_action', 'upload');
				upFd.append('dir', fileParent);
				upFd.append('myCloud_key', myCloudState.key);
				upFd.append('myCloud_token', typeof myCloudCsrfToken !== 'undefined' ? myCloudCsrfToken : '');
				upFd.append('file', encBlob, encName);
				
				const upRes = await fetch('', { method: 'POST', body: upFd }).then(r => r.json());
				if (upRes.status !== 'OK') throw new Error("Upload failed");
				
				const delFd = new URLSearchParams({
					myCloud_action: 'delete',
					myCloud_key: myCloudState.key,
					myCloud_token: typeof myCloudCsrfToken !== 'undefined' ? myCloudCsrfToken : '',
					src: file.name,
					permanent: 'true'
				});
				await fetch('', { method: 'POST', body: delFd });
			}    
        } catch (err) {
            console.error("Migration failed for", file.name, err);
            // We continue the loop so one broken file doesn't halt the whole migration
        }
    }
    
    if (!silent) {
        myCloudCloseProgressUI();
        myCloudShowAlert(myCloud_LANG.success ?? 'Success', myCloud_LANG.migration_done || 'All existing files have been E2E encrypted.');
    }
    myCloudFetchDirectory(rootDirPath);
};

function myCloudGoUp() {
    const cur = myCloudState.currentDir;
    if (!cur || cur === '/') return;
    
    const treeEl = document.querySelector('.myCloudTree');
    const wasTreeFocused = treeEl && (document.activeElement === treeEl || treeEl.contains(document.activeElement));

    // 1. Remember where we are right now (strip trailing slash for comparison)
    const oldPath = cur.replace(/\/$/, '');
    
    // 2. Calculate parent path
    let par = oldPath.substring(0, oldPath.lastIndexOf('/')) || '/';
    
    // 3. Update State
    myCloudState.currentDir = par; 
	// Clear selection
    myCloudState.selectedFiles = []; 
	
    // [FIX] 1. Update View Mode synchronously
    if (['gallery', 'symbol', 'symbol-dark'].includes(myCloudState.interface)) {
        myCloudState.viewMode = 'symbol';
    } 
    // Only check folder settings if NOT in a forced interface mode
    else if (typeof myCloudGetEffectiveViewMode === 'function') {
        myCloudState.viewMode = myCloudGetEffectiveViewMode(par);
    }
    
    // [FIX] 2. Clear UI immediately
    var details = document.querySelector('.myCloudDetails');
    if (details) details.innerHTML = '';
	
	// 4. Fetch Parent -> Then Select Old Folder
    myCloudFetchDirectory(par).then(() => {
        if (wasTreeFocused && treeEl) {
            treeEl.focus();
            setTimeout(() => myCloudSetTreeFocus(document.querySelector('.myCloudTreeList div[data-fullpath="' + CSS.escape(par) + '"]')), 50);
        } else {
            const d = document.querySelector('.myCloudDetails');
            if(d) d.focus();
            // ACTIVELY SEEK the old folder
            myCloudSeekAndSelect(oldPath);
        }
    });
}

// Robust Seeker: Polls DOM until the target row appears
function myCloudSeekAndSelect(targetPathToFind, noSelect = false) {
    let attempts = 0;
    const maxAttempts = 30; 
	if (!targetPathToFind) return;

    const poll = () => {
        const rows = Array.from(document.querySelectorAll('.myCloudRow, .myCloud-symbol-item'));
        
        // A. FAST PATH: If we want Top and rows exist, select immediately
        if (targetPathToFind === '__FORCE_TOP__' && rows.length > 0) {
            myCloudSelectVisualRow(0, rows, false, false, noSelect);
            return;
        }

        // B. If table is empty, wait for render
        if (rows.length === 0 && attempts < maxAttempts) {
            attempts++; setTimeout(poll, 20); return;
        }

        // C. Try to find the specific folder (Strip slashes to ensure match)
        const idx = rows.findIndex(r => r.dataset.fullpath && r.dataset.fullpath.replace(/\/$/, '') === targetPathToFind.replace(/\/$/, ''));
        
        if (idx !== -1) {
            myCloudSelectVisualRow(idx, rows, false, false, noSelect);
        } 
        else if (attempts < maxAttempts) {
             attempts++; setTimeout(poll, 20);
        }
        else {
            // D. Fallback: Select Top row if specific folder not found
            if(rows.length > 0) myCloudSelectVisualRow(0, rows, false, false, noSelect);
        }
    };
    poll();
}

function myCloudResetListCursor() {
    myCloudSeekAndSelect('__FORCE_TOP__', true); // Pass true to only focus, not select
}

function myCloudSelectVisualRow(index, rowsArray, isShift, isCtrl, noSelect = false) {
    
    // Close context menu if navigating via keyboard
    if (typeof myCloudCloseContextMenus === 'function') myCloudCloseContextMenus();
    
    if (!rowsArray) rowsArray = Array.from(document.querySelectorAll('.myCloudRow, .myCloud-symbol-item'));
    const target = rowsArray[index];
    if (!target) return;

    const details = document.querySelector('.myCloudDetails');
    if(details) details.focus();

    const path = target.dataset.fullpath;
    myCloudState.visualCursorIndex = index; 
    
    if (typeof path === 'undefined') return;

    // DATA UPDATE
    if (path === '..') {
        myCloudState.selectedFiles = [];
        myCloudState.currentFile = null;
        myCloudState.lastSelectedIndex = 0; 
    } else if (noSelect) {
        myCloudState.selectedFiles = [];
        myCloudState.currentFile = null;
        myCloudState.lastSelectedIndex = index; 
    } else {
        if (isShift) {
            // Range Selection
            if (myCloudState.lastSelectedIndex === -1) myCloudState.lastSelectedIndex = index;
            const start = Math.min(myCloudState.lastSelectedIndex, index);
            const end = Math.max(myCloudState.lastSelectedIndex, index);
            myCloudState.selectedFiles = rowsArray.slice(start, end + 1)
                .map(r => r.dataset.fullpath).filter(p => p !== '..');
        } else if (isCtrl) {
            // Toggle Selection
            if (myCloudState.selectedFiles.includes(path)) myCloudState.selectedFiles = myCloudState.selectedFiles.filter(p => p !== path);
            else myCloudState.selectedFiles.push(path);
            myCloudState.lastSelectedIndex = index; 
        } else {
            // Single Selection
            myCloudState.selectedFiles = [path];
            myCloudState.lastSelectedIndex = index; 
        }
        if (!isCtrl && !isShift) myCloudState.currentFile = path;
    }

    // VISUAL UPDATE
    rowsArray.forEach((r, i) => {
        const rPath = r.dataset.fullpath;
        // Strict mapping: Selection class only applied if in the selectedFiles array
        const isSel = (rPath !== '..' && myCloudState.selectedFiles.includes(rPath));
        if (isSel) r.classList.add('selected'); else r.classList.remove('selected');
        
        // Add a visual cursor indicator independent of selection
        if (i === index) {
            r.classList.add('cursor-focus');
            r.style.outline = '1px dotted var(--text-secondary)';
            r.style.outlineOffset = '-2px';
        } else {
            r.classList.remove('cursor-focus');
            r.style.outline = '';
        }

        const cb = r.querySelector('.myCloudCheckbox');
        if (cb) cb.checked = isSel;
    });

    if (index === 0 && details) details.scrollTop = 0;
    else target.scrollIntoView({ block: 'nearest', behavior: 'auto' });
    
    if(typeof myCloudUpdateToolbarState === 'function') myCloudUpdateToolbarState();

    if (typeof myCloudUpdateOfficePreview === 'function' && myCloudState.isOfficeMode) {
        myCloudUpdateOfficePreview();
    }
}


function myCloudSetTreeFocus(node) {
    document.querySelectorAll('.tree-focus').forEach(el => el.classList.remove('tree-focus'));
    if (node) { node.classList.add('tree-focus'); node.scrollIntoView({ block: 'nearest' }); }
}

function myCloudToggleTreeDir(path) {
    const isNodeEnc = typeof myCloudCrypto !== 'undefined' && myCloudCrypto.isDirEncrypted(path);
    if (!myCloudState.openDirs.includes(path) && isNodeEnc && !myCloudCrypto.isDirUnlocked(path)) {
        myCloudAction_EncryptPrompt(myCloudCrypto.getCryptoRoot(path), true, () => {
            myCloudToggleTreeDir(path);
        });
        return;
    }
    if (myCloudState.openDirs.includes(path)) {
        myCloudState.openDirs = myCloudState.openDirs.filter(d => d !== path);
        myCloudRenderUI();
    } else {
        myCloudState.openDirs.push(path);
        if (!myCloudState.loadedDirs.includes(path)) {
                 myCloudFetchDirectory(path, 2, true).then(() => setTimeout(() => {
                     const treeEl = document.querySelector('.myCloudTree');
                     if (treeEl) treeEl.focus();
                     myCloudSetTreeFocus(document.querySelector('.myCloudTreeList div[data-fullpath="' + CSS.escape(path) + '"]'));
                 }, 50));
             return;
        }
        myCloudRenderUI();
    }
    setTimeout(() => {
        const treeEl = document.querySelector('.myCloudTree');
        if (treeEl) treeEl.focus();
        myCloudSetTreeFocus(document.querySelector('.myCloudTreeList div[data-fullpath="' + CSS.escape(path) + '"]'));
    }, 0);
}


// GLOBAL CLICK/TOUCH TO CLOSE CONTEXT MENUS
const ceGlobalCloseCtx = (e) => {
    if (e.type === 'mousedown' && e.button === 2) return; // Ignore right click
    
    const ctxMenu = document.getElementById('myCloudContextMenu');
    if (ctxMenu && !ctxMenu.contains(e.target)) {
        if (typeof myCloudCloseContextMenus === 'function') myCloudCloseContextMenus();
    }
};
// Use 'capture: true' to guarantee it fires before elements can stop propagation
document.addEventListener('mousedown', ceGlobalCloseCtx, true);
document.addEventListener('touchstart', ceGlobalCloseCtx, { capture: true, passive: true });

// GLOBAL ESCAPE KEY HANDLER
document.addEventListener('keydown', (e) => {
    // --- 1. ESCAPE KEY (Close Logic) ---
    if (e.key === 'Escape') {
        // Context Menus
        if (document.querySelector('.myCloudContextMenu')) {
            myCloudCloseContextMenus();
            e.preventDefault(); e.stopPropagation();
            return;
        }
        // Ribbons/Settings
        const floatMenu = document.getElementById('myCloudFloatingMenu');
        if (floatMenu) {
            myCloudCloseFloatingMenu(true);
            e.preventDefault(); e.stopPropagation();
            return;
        }
        // Modals
        const modalOverlay = document.getElementById('myCloudModalOverlay');
        if (modalOverlay && modalOverlay.style.display !== 'none') {
            if (document.getElementById('myCloudSettingsPanel') && typeof window.myCloudCloseSettingsModal === 'function') {
                window.myCloudCloseSettingsModal();
            } else {
                myCloudCloseModal();
            }
            e.preventDefault(); e.stopPropagation();
            return;
        }
        // Previews
        const previewOverlay = document.getElementById('myCloudPreviewOverlay');
        if (previewOverlay && previewOverlay.style.display !== 'none') {
            myCloudClosePreview();
            e.preventDefault(); e.stopPropagation();
            return;
        }
    }

    // --- 2. PREVIEW NAVIGATION (Next/Prev) ---
    const previewOverlay = document.getElementById('myCloudPreviewOverlay');
    if (previewOverlay && previewOverlay.style.display !== 'none') {
        switch (e.key) {
            case 'ArrowRight':
            case 'PageDown':
                e.preventDefault(); e.stopPropagation();
                myCloudNavigatePreview(1); 
                break;
            case 'ArrowLeft':
            case 'PageUp':
                e.preventDefault(); e.stopPropagation();
                myCloudNavigatePreview(-1); 
                break;
        }
    }
}, true); 


// GLOBAL BROWSER ZOOM PREVENTION (Ctrl + Mousewheel)
document.addEventListener('wheel', (e) => {
    if (e.ctrlKey || e.metaKey) {
        // Allow zooming inside specific viewing/editing containers
        if (e.target.closest('#myCloudPreviewOverlay, #myCloudPreviewPane, #myCloudTerminalWrap, .myCloudEditor_modal_wrap')) {
            return; 
        }
        e.preventDefault(); // Block native browser page zoom everywhere else
    }
}, { passive: false });

/* ==========================================
   APP-LIKE HARDWARE BACK BUTTON HANDLER
   ========================================== */
window.myCloudHistoryTrapped = false;

window.myCloudEnsureHistoryTrap = function() {
    if (!window.myCloudHistoryTrapped) {
        window.history.pushState({ ce_trap: true }, '', null);
        window.myCloudHistoryTrapped = true;
    }
};

window.addEventListener('popstate', function(e) {
    window.myCloudHistoryTrapped = false;
	// Trap was sprung by the back button

    // 1. Modals & Overlays (Highest Priority)
    const modalOverlay = document.getElementById('myCloudModalOverlay');
    if (modalOverlay && modalOverlay.style.display !== 'none') {
        if (document.getElementById('myCloudSettingsPanel') && typeof window.myCloudCloseSettingsModal === 'function') {
            window.myCloudCloseSettingsModal();
        } else {
            if (typeof myCloudCloseModal === 'function') myCloudCloseModal();
        }
        window.myCloudEnsureHistoryTrap(); // Re-trap
        return;
    }

    const previewOverlay = document.getElementById('myCloudPreviewOverlay');
    if (previewOverlay && previewOverlay.style.display !== 'none') {
        if (typeof myCloudClosePreview === 'function') myCloudClosePreview();
        window.myCloudEnsureHistoryTrap(); 
		// Re-trap
        return;
    }

    // 2. Floating Menus & Palettes
    const floatMenu = document.getElementById('myCloudFloatingMenu');
    const favPanel = document.getElementById('myCloudFavoritesPanel');
    const palette = document.getElementById('myCloudPaletteOverlay');
    if (floatMenu || favPanel || palette) {
        if (typeof myCloudCloseFloatingMenu === 'function') myCloudCloseFloatingMenu(true);
        if (palette) palette.remove();
        window.myCloudEnsureHistoryTrap(); 
		// Re-trap
        return;
    }

    // 2.5 Email Mobile Navigation
    if (typeof myCloudState !== 'undefined' && myCloudState && myCloudState.interface === 'email' && typeof myCloudEmailState !== 'undefined') {
        if (myCloudEmailState.mobileView === 'reading' || myCloudEmailState.mobileView === 'tree') {
            if (typeof window._emailSetMobileView === 'function') window._emailSetMobileView('list', true);
            window.myCloudHistoryTrapped = true;
            return;
        }
    }

    // 3. Folder Navigation
    if (myCloudState && myCloudState.currentDir && myCloudState.currentDir !== '/') {
        if (myCloudState.isCommanderMode && typeof commanderGoUp === 'function') commanderGoUp(myCloudState.commanderActive || 'left');
        else if (typeof myCloudGoUp === 'function') myCloudGoUp();
        window.myCloudEnsureHistoryTrap(); 
		// Re-trap
    }
    // 4. If at root ('/'), we do nothing. The browser naturally exits the app.
});

/* ==========================================
   MARQUEE (RUBBER BAND) SELECTION - FIXED
   ========================================== */
function myCloudInitMarquee() {
    // Bind to the PERMANENT scrolling container, not the dynamic inner content
    const container = document.querySelector('.myCloudDetails');
    if (!container || container.dataset.marqueeBound) return;
    container.dataset.marqueeBound = "true"; 

    let marquee = null;
    let startX = 0, startY = 0;
    let initialSelection = [];
    let isDragging = false;
    let scrollTimer = null;
    let startScrollTop = 0;

    container.addEventListener('mousedown', (e) => {
        // 1. IGNORE if clicking on a file row (Let row drag handler work)
        if (e.target.closest('.myCloudRow') || e.target.closest('.myCloud-symbol-item')) return;

        // 2. IGNORE if clicking on scrollbar (Approximate check)
        if (e.offsetX > e.target.clientWidth) return;

        // 3. IGNORE Right Clicks
        if (e.button !== 0) return;
        
        // 4. STOP NATIVE DRAGGING (Fixes "Handler takes over" issue)
        // This prevents the browser from trying to drag the "Empty Folder" icon/text
        e.preventDefault(); 
        
        // 5. Setup
        startX = e.clientX;
        startY = e.clientY;
		startScrollTop = container.scrollTop;
        
        // If Ctrl is NOT held, clear previous selection immediately
        if (!e.ctrlKey) {
            myCloudState.selectedFiles = []; 
            document.querySelectorAll('.myCloudRow.selected, .myCloud-symbol-item.selected').forEach(r => {
                r.classList.remove('selected');
                const cb = r.querySelector('.myCloudCheckbox'); if(cb) cb.checked = false;
            });
        }
        
        // Snapshot current state
        initialSelection = [...myCloudState.selectedFiles];
        isDragging = true;

        // 6. Create Visual Box
        marquee = document.createElement('div');
        marquee.className = 'myCloud-marquee';
        document.body.appendChild(marquee);

        // 7. Movement Logic
        const onMove = (ev) => {
            if (!isDragging) return;
			
            // --- MARQUEE AUTO SCROLL ---
            const cRect = container.getBoundingClientRect();
            const scrollZone = 40;
            const speed = 15;
            clearInterval(scrollTimer);
            if (ev.clientY < cRect.top + scrollZone) {
                scrollTimer = setInterval(() => { container.scrollTop -= speed; onMove(ev); }, 30);
            } else if (ev.clientY > cRect.bottom - scrollZone) {
                scrollTimer = setInterval(() => { container.scrollTop += speed; onMove(ev); }, 30);
            }

            // Compensate Y start coordinate based on how much the container has scrolled since mousedown
            const scrollDelta = container.scrollTop - startScrollTop;
            const adjustedStartY = startY - scrollDelta;

            const x = Math.min(ev.clientX, startX);
            const y = Math.min(ev.clientY, adjustedStartY);
            const w = Math.abs(ev.clientX - startX);
            const h = Math.abs(ev.clientY - adjustedStartY);
            
            marquee.style.display = 'block';
            marquee.style.left = x + 'px';
            marquee.style.top = y + 'px';
            marquee.style.width = w + 'px';
            marquee.style.height = h + 'px';

            const boxRect = marquee.getBoundingClientRect();
            const rows = document.querySelectorAll('.myCloudRow, .myCloud-symbol-item');
            const newSelection = new Set(e.ctrlKey ? initialSelection : []);

            rows.forEach(r => {
                const rRect = r.getBoundingClientRect();
                // Check Intersection (AABB)
                if (boxRect.left < rRect.right && boxRect.right > rRect.left &&
                    boxRect.top < rRect.bottom && boxRect.bottom > rRect.top) {
                    
                    if (e.ctrlKey && initialSelection.includes(r.dataset.fullpath)) {
                        // In Ctrl mode, re-selecting an item toggles it OFF? 
                        // Windows behavior: Marquee ADDS to selection usually.
                        // Let's stick to ADDING for simplicity.
                        newSelection.add(r.dataset.fullpath);
                    } else {
                        newSelection.add(r.dataset.fullpath);
                    }
                }
            });

            // Live Visual Update
            myCloudState.selectedFiles = Array.from(newSelection);
            rows.forEach(r => {
                const isSel = newSelection.has(r.dataset.fullpath);
                if (isSel) r.classList.add('selected'); else r.classList.remove('selected');
                const cb = r.querySelector('.myCloudCheckbox'); if(cb) cb.checked = isSel;
            });
        };

        const onUp = () => {
            isDragging = false;
			clearInterval(scrollTimer);
            document.removeEventListener('mousemove', onMove);
            document.removeEventListener('mouseup', onUp);
            if(marquee) marquee.remove();
            myCloudUpdateToolbarState();
            if (typeof myCloudUpdateOfficePreview === 'function' && myCloudState.isOfficeMode) {
                myCloudUpdateOfficePreview();
            }
        };

        document.addEventListener('mousemove', onMove);
        document.addEventListener('mouseup', onUp);
    });
}

function myCloudAction_RestoreTo() {
    const files = myCloudState.selectedFiles;
    if (files.length === 0) return;
    myCloudShowTreeSelector('Restore To...', 'Restore', function(targetDir) {
        myCloudAction_Restore(targetDir);
    });
}

function myCloudShowUndoToast(recycledFiles) {
    const div = document.createElement('div');
    div.style.cssText = 'position:fixed; bottom:20px; left:50%; transform:translateX(-50%); background:#333; color:#fff; padding:12px 24px; border-radius:4px; box-shadow:0 4px 12px rgba(0,0,0,0.3); z-index:99999; display:flex; align-items:center; gap:15px; font-size:14px; animation:ceFadeInScale 0.3s;';
    div.innerHTML = '<span>' + recycledFiles.length + ' ' + myCloud_LANG.items_recycled + '</span><button id="ceUndoBtn" style="background:transparent; border:none; color:#60cdff; font-weight:bold; cursor:pointer; text-transform:uppercase;">' + myCloud_LANG.undo_btn + '</button><button onclick="this.parentElement.remove()" style="background:transparent; border:none; color:#aaa; cursor:pointer; margin-left:10px;">✕</button>';
    document.body.appendChild(div);
    
    document.getElementById('ceUndoBtn').onclick = () => {
        div.remove();
        myCloudState.selectedFiles = recycledFiles;
        myCloudAction_Restore();
    };
    
    setTimeout(() => { if(div.parentNode) div.remove(); }, 5000);
}

// Displays a transient "toast" notification at the bottom of the screen.
// Used for non-critical status updates (e.g., upload failures).
function myCloudNotify(message) {
    // 1. Remove any existing notification to prevent stacking
    const existing = document.getElementById('myCloudNotification');
    if (existing) existing.remove();

    // 2. Create the notification container
    const div = document.createElement('div');
    div.id = 'myCloudNotification';
    
    // 3. Apply Styling
    // Matches the dark style of 'myCloudShowUndoToast' and 'myCloud-floating-logout'
    div.style.cssText = 
        'position: fixed; ' +
        'bottom: 70px; ' + // Sits above the version badge/nav bars
        'left: 50%; ' +
        'transform: translateX(-50%); ' +
        'background: rgba(32, 32, 32, 0.95); ' +
        'color: #fff; ' +
        'padding: 10px 20px; ' +
        'border-radius: 6px; ' +
        'box-shadow: 0 4px 12px rgba(0,0,0,0.3); ' +
        'z-index: 99999; ' +
        'font-size: 14px; ' +
        'font-family: var(--font-family); ' +
        'text-align: center; ' +
        'max-width: 90vw; ' +
        'cursor: pointer; ' +
        'pointer-events: auto; ' +
        'opacity: 0; ' +
        'transition: opacity 0.3s ease;';

    // 4. Set Content
    div.textContent = message;

    // 5. Append to DOM
    document.body.appendChild(div);

    // 6. Trigger Fade-In (Force reflow to ensure transition works)
    void div.offsetWidth; 
    div.style.opacity = '1';

    // 7. Define Dismiss Logic
    const dismiss = () => {
        div.style.opacity = '0';
        // Remove from DOM after fade-out transition completes
        setTimeout(() => { 
            if (div.parentNode) div.remove(); 
        }, 300);
    };

    // Dismiss on click immediately
    div.onclick = dismiss;

    // Auto-dismiss after 4 seconds
    setTimeout(dismiss, 4000);
}


// [NEW] API Call to load the separate _views.json
function myCloudLoadViewSettings() {
    const fd = new URLSearchParams();
    fd.append('myCloud_action', 'load_views');
    fd.append('myCloud_key', myCloudState.key);
    fd.append('myCloud_token', myCloudCsrfToken);
    
    return fetch('', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(resp => {
            if(resp.status === 'OK') {
                myCloudState.viewSettings = resp.views;
            }
        });
}

function myCloudSurgicalRemove(paths) {
    if (!paths || !Array.isArray(paths) || paths.length === 0) return;
    
    const st = myCloudState;
    let indexToRestore = -1;

    // 1. Find the visual index of the first item being removed (to preserve focus position)
    if (st.isCommanderMode) {
        const side = st.commanderActive || 'left';
        const paneState = (side === 'left') ? st.commanderLeft : st.commanderRight;
        indexToRestore = paneState.visualCursorIndex || 0;
    } else {
        indexToRestore = st.visualCursorIndex || 0;
    }

    // 2. Remove from DOM
    paths.forEach(p => {
        const selector = `.myCloudRow[data-fullpath="${CSS.escape(p)}"], .myCloud-symbol-item[data-fullpath="${CSS.escape(p)}"], .myCloud-gallery-item[data-path="${CSS.escape(p)}"]`;
        const el = document.querySelector(selector);
        if (el) el.remove();
    });

    // 3. Update Global Data State
    st.allItems = st.allItems.filter(item => !paths.includes(item.name));
    st.selectedFiles = []; // Clear selection as the specific items are gone
    
    // 4. Update Commander Data State
    if (st.isCommanderMode) {
        if (st.commanderLeft) {
            st.commanderLeft.items = st.commanderLeft.items.filter(item => !paths.includes(item.name));
            st.commanderLeft.selectedFiles = [];
        }
        if (st.commanderRight) {
            st.commanderRight.items = st.commanderRight.items.filter(item => !paths.includes(item.name));
            st.commanderRight.selectedFiles = [];
        }
    }

    // 5. RESTORE SELECTION / FOCUS
    // We select the item that is now at the previous index (or the last one if we were at the end)
    if (st.isCommanderMode) {
        const side = st.commanderActive || 'left';
        const pane = document.querySelector(`.myCloud-commander-pane[data-side="${side}"]`);
        if (pane) {
            const content = pane.querySelector('.myCloud-commander-content');
            const rows = Array.from(content.querySelectorAll('.myCloudRow'));
            
            if (rows.length > 0) {
                // Clamp index
                if (indexToRestore >= rows.length) indexToRestore = rows.length - 1;
                if (indexToRestore < 0) indexToRestore = 0;
                
                const targetRow = rows[indexToRestore];
                if (targetRow) {
                    const path = targetRow.dataset.fullpath;
                    commanderSelectRow(targetRow, path, side, {}); // Select without modifiers
                    
                    // Keep Focus for Keyboard
                    content.focus();
                }
            } else {
                // Empty folder
                myCloudUpdateToolbarState();
                content.focus();
            }
        }
    } else {
        // Standard View
        const rows = Array.from(document.querySelectorAll('.myCloudRow, .myCloud-symbol-item'));
        if (rows.length > 0) {
            if (indexToRestore >= rows.length) indexToRestore = rows.length - 1;
            if (indexToRestore < 0) indexToRestore = 0;
            
            myCloudSelectVisualRow(indexToRestore, rows);
            const details = document.querySelector('.myCloudDetails');
            if(details) details.focus();
        } else {
            myCloudUpdateToolbarState();
        }
    }
}


async function myCloudAction_ShareSelection() {
    const files = myCloudState.selectedFiles;
    if (files.length === 0) return;

    if (!navigator.share) {
        myCloudNotify("Sharing not supported on this device.");
        return;
    }

    const shareData = {
        files: []
    };
    
    myCloudCreateProgressUI(myCloud_LANG.loading || "Preparing...");

    try {
        // Fetch blobs for all selected files
        const blobs = await Promise.all(files.map(async (path) => {
            const name = path.split('/').pop();
            // Get Token
            const tokenResp = await fetch('', {
                method: 'POST',
                body: new URLSearchParams({
                    myCloud_action: 'get_download_token',
                    myCloud_key: myCloudState.key,
                    myCloud_token: myCloudCsrfToken,
                    path: path,
                    filename: name
                })
            }).then(r => r.json());

            if (tokenResp.status !== 'OK') throw new Error("Token Error");

            // Fetch Blob with absolute URL
            const dlUrl = window.location.origin + window.location.pathname + '?myCloud_token=' + tokenResp.token;
            const fResp = await fetch(dlUrl);
            if (!fResp.ok) throw new Error("HTTP " + fResp.status);
            const blob = await fResp.blob();
            return new File([blob], name, { type: blob.type });
        }));

        shareData.files = blobs;
        myCloudCloseProgressUI();
        
        await navigator.share(shareData);
    } catch (err) {
        myCloudCloseProgressUI();
        console.error(err);
        // Fallback or silence (User might have cancelled share dialog)
        if (err.name !== 'AbortError') {
             myCloudNotify("Share failed: " + err.message);
        }
    }
}

// --- SYSTEM DIAGNOSTICS ROUTINE ---
function _sys_diag_init() {
    if(document.getElementById('_sys_chk')) return;
    let w = document.createElement('div'), c = document.createElement('canvas'), x = c.getContext('2d');
    let cls = document.createElement('div'), h = document.createElement('div');
    w.id = '_sys_chk'; w.style.cssText = 'position:fixed;inset:0;background:#000d;z-index:2147483647;display:flex;align-items:center;justify-content:center;outline:none;';
    cls.innerHTML = '✕'; cls.style.cssText = 'position:absolute;top:20px;right:30px;color:#fff;font-size:36px;cursor:pointer;font-family:sans-serif;z-index:9e6;padding:10px;';
    cls.onclick = () => { clearInterval(T); w.remove(); };
    h.innerHTML = '&larr;&rarr; Move &nbsp;&nbsp;&nbsp; &darr; Drop &nbsp;&nbsp;&nbsp; &uarr; / Spc / W Turn &nbsp;&nbsp;&nbsp; ESC Quit';
    h.style.cssText = 'position:absolute;bottom:40px;color:#888;font-family:monospace;font-size:12px;pointer-events:none;';
    c.width = 200; c.height = 400; c.style.border = '2px solid #555'; c.style.boxShadow = '0 10px 30px #000';
    w.appendChild(c); w.appendChild(cls); w.appendChild(h); document.body.appendChild(w); w.tabIndex = 0; w.focus();
    let B = Array.from({length:20}, () => Array(10).fill(0));
    let S = [ [[1,1,1,1]], [[1,1],[1,1]], [[0,1,0],[1,1,1]], [[1,0,0],[1,1,1]], [[0,0,1],[1,1,1]], [[1,1,0],[0,1,1]], [[0,1,1],[1,1,0]] ];
    let C = ['#222','#0ff','#ff0','#f0f','#00f','#f80','#f00','#0f0'];
    let p, px, py, id, T;
    function D() {
        x.fillStyle = '#000'; x.fillRect(0,0,200,400);
        for(let y=0; y<20; y++) for(let i=0; i<10; i++) { x.fillStyle = B[y][i] ? C[B[y][i]] : C[0]; x.fillRect(i*20, y*20, 19, 19); }
        if(!p) return; x.fillStyle = C[id];
        for(let y=0; y<p.length; y++) for(let i=0; i<p[y].length; i++) if(p[y][i]) x.fillRect((px+i)*20, (py+y)*20, 19, 19);
    }
    function K(np, nx, ny) {
        for(let y=0; y<np.length; y++) for(let i=0; i<np[y].length; i++) if(np[y][i] && (ny+y < 0 || ny+y >= 20 || nx+i < 0 || nx+i >= 10 || B[ny+y][nx+i])) return 1;
        return 0;
    }
    function R() { let np = p[0].map((_, i) => p.map(r => r[i]).reverse()); if(!K(np, px, py)) { p = np; D(); } }
    function N() { let i = Math.floor(Math.random()*7); p = S[i]; id = i+1; px = 3; py = 0; if(K(p, px, py)) { clearInterval(T); w.remove(); } }
    function U() { if(K(p, px, py+1)) { for(let y=0; y<p.length; y++) for(let i=0; i<p[y].length; i++) if(p[y][i]) B[py+y][px+i] = id; for(let y=19; y>=0; y--) if(B[y].every(v=>v)) { B.splice(y,1); B.unshift(Array(10).fill(0)); y++; } N(); } else py++; D(); }
    w.onkeydown = e => {
        if(['Escape', 'ArrowLeft', 'ArrowRight', 'ArrowDown', 'ArrowUp', ' ', 'w', 'W'].includes(e.key)) e.preventDefault();
        if(e.key == 'Escape') { clearInterval(T); w.remove(); } 
        if(e.key == 'ArrowLeft' && !K(p, px-1, py)) { px--; D(); } 
        if(e.key == 'ArrowRight' && !K(p, px+1, py)) { px++; D(); } 
        if(e.key == 'ArrowDown') U(); 
        if(['ArrowUp', ' ', 'w', 'W'].includes(e.key) && p) R();
    }; N(); T = setInterval(U, 400);
}

</script>