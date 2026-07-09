<?php
/**
 * ============================================================================
 * MODULE: User Preferences & Settings Interface
 * ============================================================================
 * Generates the interactive modal interface allowing users to configure 
 * account preferences, device-specific layouts, and theme behaviors.
 * NOTE: Executed exclusively by the client browser.
 * 
 * THIS IS PART OF THE PSEUDO "JS FILE" - SO THE NO PHP CODE IN THIS FILE!
 */

?><script>
// +++ SETTINGS MODULE +++
 
// Default configuration settings for various device types.
// Defines initial UI state like tree visibility, theme, and font size.
const myCloudDefaultSettings = {
    desktop: { 
        treeOpen: true, darkMode: false, fontSize: 1, hideDisabled: true, singleClick: false,
        stackedToolbar: true, showCheckboxes: true, showHoverMenu: true, clickToPreview: true, showFilmstrip: false,
        rememberLastFolder: false, warnLargePreview: true, sidebarSize: 280, officePreviewWidth: 400, symbolDarkMode: false,
        showListThumbnails: false, symbolSize: 'medium', commanderSplit: 0.5, startInCommander: {}, isOfficeMode: false
    },
    tablet: { 
        treeOpen: true, darkMode: false, fontSize: 2, hideDisabled: true, singleClick: false,
        stackedToolbar: true, showCheckboxes: true, showHoverMenu: false, clickToPreview: true, showFilmstrip: false,
        rememberLastFolder: false, warnLargePreview: true, sidebarSize: 250, officePreviewWidth: 350, symbolDarkMode: false,
        showListThumbnails: false, symbolSize: 'medium', commanderSplit: 0.5, startInCommander: {}, isOfficeMode: false
    },
    phone: { 
        treeOpen: false, darkMode: false, fontSize: 3, hideDisabled: true, singleClick: false, 
        stackedToolbar: true, showCheckboxes: true, showHoverMenu: false, clickToPreview: true, showFilmstrip: false,
        rememberLastFolder: false, warnLargePreview: true, sidebarSize: 200, officePreviewWidth: 0, symbolDarkMode: false,
        showListThumbnails: false, symbolSize: 'medium', commanderSplit: 0.5, startInCommander: {}, isOfficeMode: false
    },
    showHelpOnStart: true,
	enableRecycleBin: true,
	fra_completed: false,
	tagNames: {},
	visibleTags: ['#e81123', '#0078d4', '#107c10', '#f0ad4e', '#888888'],
	tagOrder: ['#e81123', '#0078d4', '#107c10', '#f0ad4e', '#888888', '#673ab7', '#e91e63', '#009688', '#795548', '#607d8b'],
};

// Determines the current settings profile key based on device characteristics.
// Returns 'phone', 'tablet', or 'desktop'.
function myCloudGetCurrentDeviceKey() {
    if (myCloudDevice.type === 'phone' || myCloudDevice.type === 'foldable-folded') return 'phone';
    if (myCloudDevice.category === 'tablet' || myCloudDevice.type === 'foldable-unfolded') return 'tablet';
    return 'desktop';
}

// Fetches user settings from the server.
// Merges server settings with defaults to ensure compatibility.
function myCloudLoadSettings(isStartup = false) {
    return fetch('', {
        method: 'POST',
        body: new URLSearchParams({
            myCloud_action: 'load_settings',
            myCloud_key: myCloudState.key,
            myCloud_token: myCloudCsrfToken,
            inc_launch: window.myCloudHelpShown ? '0' : '1'
        })
    })
    .then(function(r) { return r.json(); })
    .then(function(resp) {
		if (resp.status === 'OK' && resp.settings) {
            myCloudState.settings = {
                desktop: Object.assign({}, myCloudDefaultSettings.desktop, resp.settings.desktop),
                tablet: Object.assign({}, myCloudDefaultSettings.tablet, resp.settings.tablet),
                phone: Object.assign({}, myCloudDefaultSettings.phone, resp.settings.phone),
                showHelpOnStart: (typeof resp.settings.showHelpOnStart !== 'undefined') ? resp.settings.showHelpOnStart : myCloudDefaultSettings.showHelpOnStart,
                language: resp.settings.language || (typeof myCloudDetectedLang !== 'undefined' ? myCloudDetectedLang : 'en'),
                enableRecycleBin: (typeof resp.settings.enableRecycleBin !== 'undefined') ? resp.settings.enableRecycleBin : myCloudDefaultSettings.enableRecycleBin,
                renameHistory: Array.isArray(resp.settings.renameHistory) ? resp.settings.renameHistory : [],
				fra_completed: (typeof resp.settings.fra_completed !== 'undefined') ? resp.settings.fra_completed : false,	
				tagNames: resp.settings.tagNames || {},	
				visibleTags: Array.isArray(resp.settings.visibleTags) ? resp.settings.visibleTags : myCloudDefaultSettings.visibleTags,
				tagOrder: Array.isArray(resp.settings.tagOrder) ? resp.settings.tagOrder : myCloudDefaultSettings.tagOrder,
            };

            // [FIX] Sanitize startInCommander: Convert Array [] to Object {}
            // PHP json_encode sends [] for empty associative arrays. JS treats this as an Array.
            // JSON.stringify ignores named properties on Arrays, causing data loss on save.
            ['desktop', 'tablet', 'phone'].forEach(dev => {
                let s = myCloudState.settings[dev];
                if (!s.startInCommander || Array.isArray(s.startInCommander)) {
                    s.startInCommander = {};
                }
            });

 
        } else {
            myCloudState.settings = JSON.parse(JSON.stringify(myCloudDefaultSettings));
        }
        myCloudApplySettings(isStartup);
    })
    .catch(function(e) {
        console.warn("Settings load failed, using defaults", e);
        myCloudState.settings = JSON.parse(JSON.stringify(myCloudDefaultSettings));
        myCloudApplySettings(isStartup);
    });
}


// ---  USER PASSWORD HANDLERS ---
window.ce_toggle_my_pwd = function(btn) {
    const p = document.getElementById('ce_pwd_new');
    const svg = btn.querySelector('svg');
    if (p.type === 'password') {
        p.type = 'text';
        const cp = document.getElementById('ce_pwd_confirm');
        if (cp) cp.type = 'text';
        btn.style.color = 'var(--accent-primary)';
        svg.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>';
    } else {
        p.type = 'password';
        const cp = document.getElementById('ce_pwd_confirm');
        if (cp) cp.type = 'password';
        btn.style.color = 'var(--text-secondary)';
        svg.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>';
    }
};

window.ce_validate_my_pwd = function(password) {
	const L = typeof myCloud_LANG !== 'undefined' ? myCloud_LANG : {};
    const errors = [];
    if (password.length < 8) errors.push(L.pwd_err_length || '- Must be at least 8 characters long.');
    if (!/[-_.:,;()/+#!§%&]/.test(password)) errors.push(L.pwd_err_special || '- Must contain at least one special character (e.g., -_.:,;()#!)');
    if (/[$"\']/.test(password)) errors.push(L.pwd_err_invalid_char || '- Must not contain the characters $, ", or \'');
    if (!/\d/.test(password)) errors.push(L.pwd_err_digit || '- Must contain at least one digit.');
    return errors;
};

window.ce_is_my_pwd_pwned = async function(password) {
    try {
        const msgBuffer = new TextEncoder().encode(password);
        const hashBuffer = await crypto.subtle.digest('SHA-1', msgBuffer);
        const hashArray = Array.from(new Uint8Array(hashBuffer));
        const hash = hashArray.map(b => b.toString(16).padStart(2, '0')).join('').toUpperCase();
        const prefix = hash.substring(0, 5);
        const suffix = hash.substring(5);
        const response = await fetch('https://api.pwnedpasswords.com/range/' + prefix);
        if (!response.ok) return false;
        const text = await response.text();
        const lines = text.split('\n');
        for (const line of lines) { if (line.split(':')[0].trim() === suffix) return true; }
        return false;
    } catch (e) { return false; }
};

window.ce_submit_password_change = async function() {
    const L = typeof myCloud_LANG !== 'undefined' ? myCloud_LANG : {};
    const oldP = document.getElementById('ce_pwd_old').value;
    const newP = document.getElementById('ce_pwd_new').value;
    const errTitle = L.error_prefix || 'Error';
    if (!oldP || !newP) { myCloudShowAlert(errTitle, L.pwd_err_empty || 'Please fill in both password fields.'); return; }

    const vErrs = ce_validate_my_pwd(newP);
    if (vErrs.length > 0) { myCloudShowAlert(L.pwd_sec_policy || 'Security Policy', (L.pwd_policy_fail || 'Password policy not met:') + '<br>' + vErrs.join('<br>')); return; }

    const btn = document.getElementById('ce_pwd_btn');
    const origText = btn.innerText;
    btn.disabled = true; btn.innerText = L.pwd_checking || "Checking...";

    const isPwned = await ce_is_my_pwd_pwned(newP);
    if (isPwned) {
        btn.disabled = false; btn.innerText = origText;
        myCloudShowAlert(L.pwd_sec_warn || 'Security Warning', L.pwd_pwned_msg || 'This password has been exposed in a data breach.<br><br>Please choose a different, more secure password.');
        return;
    }
	
    const inputNew = document.getElementById('ce_pwd_new');
    if (inputNew.type === 'password') {
        const confirmWrap = document.getElementById('cePwdConfirmWrap');
        if (!confirmWrap) {
            const wrap = document.createElement('div');
            wrap.id = 'cePwdConfirmWrap';
            wrap.style.cssText = 'position:relative; display:flex; align-items:center; width:100%; margin-top:10px;';
            wrap.innerHTML = '<input type="password" id="ce_pwd_confirm" class="myCloudInlineInput" placeholder="' + (L.confirm_pwd || 'Confirm Password') + '" style="margin:0; height:32px; font-size:13px; width:100%;">';
            inputNew.parentNode.parentNode.insertBefore(wrap, inputNew.parentNode.nextSibling);
            document.getElementById('ce_pwd_confirm').focus();
            btn.disabled = false; btn.innerText = L.confirm || "Confirm";
            return;
        } else {
            const confirmVal = document.getElementById('ce_pwd_confirm').value;
            if (newP !== confirmVal) {
                btn.disabled = false; btn.innerText = origText;
                myCloudShowAlert(errTitle, L.pwd_mismatch || 'Passwords do not match. Please try again.');
                return;
            }
        }
    }	
	
    btn.innerText = L.pwd_saving || "Saving...";
    const fd = new URLSearchParams({ myCloud_action: 'change_password', myCloud_key: myCloudState.key, myCloud_token: myCloudCsrfToken, old_pass: oldP, new_pass: newP });

    fetch('', { method: 'POST', body: fd }).then(r => r.json()).then(res => {
        btn.disabled = false; btn.innerText = origText;
        if (res.status === 'OK') {
            document.getElementById('ce_pwd_old').value = ''; document.getElementById('ce_pwd_new').value = '';
            myCloudShowAlert(L.success || 'Success', L.pwd_success || 'Password changed successfully!');
        } else myCloudShowAlert(errTitle, res.msg || L.pwd_fail || 'Failed to change password.');
    }).catch(() => { btn.disabled = false; btn.innerText = origText; myCloudShowAlert(errTitle, L.network_error || 'Network error.'); });
};

// Saves the current settings state to the server via AJAX.
function myCloudSaveSettings() {
    const fd = new URLSearchParams();
    fd.append('myCloud_action', 'save_settings');
    fd.append('myCloud_key', myCloudState.key);
    fd.append('myCloud_token', myCloudCsrfToken);
    fd.append('settings_json', JSON.stringify(myCloudState.settings));
    
    return fetch('', { method: 'POST', body: fd, keepalive: true }).catch(() => {});
}

// Resets settings to default values on both client and server.
// Triggers an alert upon completion.
function myCloudResetSettings() {
	const savedFavs = myCloudState.settings.favorites;
    const fd = new URLSearchParams();
    fd.append('myCloud_action', 'reset_settings');
    fd.append('myCloud_key', myCloudState.key);
    fd.append('myCloud_token', myCloudCsrfToken);
    
    fetch('', { method: 'POST', body: fd }).then(function() {
        myCloudState.settings = JSON.parse(JSON.stringify(myCloudDefaultSettings));
		if (savedFavs) myCloudState.settings.favorites = savedFavs;
        myCloudApplySettings();
		myCloudSaveSettings();
        
        myCloudShowAlert(
            myCloud_LANG.set_reset_title, 
            myCloud_LANG.set_reset_success
        );
    });
}

// Applies current settings to the UI (Dark Mode, Font Size, Layout).
// Updates cookies and CSS classes.
function myCloudApplySettings(skipRender = false) {
    const devKey = myCloudGetCurrentDeviceKey();
    const config = myCloudState.settings[devKey];
    const container = document.getElementById('myCloudContainer');

    myCloudToggleFontSize(config.fontSize);

    const isDark = config.darkMode;
    var d = new Date(); d.setTime(d.getTime() + (365*24*60*60*1000));
    document.cookie = "myCloudDarkMode=" + (isDark ? '1' : '0') + ";expires=" + d.toUTCString() + ";path=/;SameSite=Lax";
    myCloudApplyTheme(); 

    if (container) {
        if (config.showCheckboxes) container.classList.remove('ce-no-checkboxes');
        else container.classList.add('ce-no-checkboxes');

        if (config.showHoverMenu && !myCloudState.isOfficeMode) container.classList.remove('ce-no-hover-menu');
        else container.classList.add('ce-no-hover-menu');
    }

    if (!skipRender) {
        if (typeof myCloudRenderUI === 'function') myCloudRenderUI();
        const toolbar = document.getElementById('myCloudToolbar');
        if (toolbar && myCloudState.interface !== 'email') myCloudRenderToolbar(); 
    }
}

// Opens the settings panel in a standard modal dialog and pre-calculates tab heights.
function myCloudShowSettings() {
    if (!window.myCloudActionAllowed('settings')) return;

    const overlay = document.getElementById('myCloudModalOverlay');
    const modal = document.getElementById('myCloudModal');
    if (typeof myCloudResetModal === 'function') myCloudResetModal();

    overlay.style.display = 'flex';
    modal.className = 'myCloudModal';
    modal.style.transition = 'width 0.3s cubic-bezier(0.16, 1, 0.3, 1), max-width 0.3s cubic-bezier(0.16, 1, 0.3, 1)';
    
    const title = typeof myCloud_LANG !== 'undefined' && myCloud_LANG.options ? myCloud_LANG.options : 'Settings';

    modal.innerHTML = 
        '<div class="myCloudModalHeader" style="justify-content:space-between; align-items:center;">' +
            '<span>' + myCloudSvgLogo + ' <span style="font-weight:100;">- ' + title + '</span></span>' +
            '<button onclick="window.myCloudCloseSettingsModal()" style="background:transparent; border:none; font-size:20px; cursor:pointer; color:inherit; line-height:1;">✕</button>' +
        '</div>' +
        '<div id="myCloudSettingsPanel" class="myCloudModalBody" style="padding: 0; display: flex; flex-direction: column; overflow: hidden; transition: height 0.3s cubic-bezier(0.16, 1, 0.3, 1);"></div>';
		
    const panel = document.getElementById('myCloudSettingsPanel');
    myCloudSettingsPanel = panel;

    const curLang = myCloudState.settings.language || 'en';
    const isRtl = ['ar', 'fa', 'he', 'ur'].includes(curLang);
    modal.setAttribute('dir', isRtl ? 'rtl' : 'ltr');

    // 1. Measure General Tab
    _cloudExRenderSettingsContent(panel, 'all');
    const h1 = panel.querySelector('.ce-settings-content').offsetHeight || 0;
    
    // 2. Measure Tags Tab
    _cloudExRenderSettingsContent(panel, 'tags');
    const h2 = panel.querySelector('.ce-settings-content').offsetHeight || 0;
    
    // 3. Measure Device Tab
    const devKey = myCloudGetCurrentDeviceKey();
    _cloudExRenderSettingsContent(panel, devKey);
    const h3 = panel.querySelector('.ce-settings-content').offsetHeight || 0;
    
    // Store Max & Restore View
    panel.dataset.minContentHeight = Math.max(h1, h2, h3) + 'px';
    _cloudExRenderSettingsContent(panel, devKey);
	if (panel.offsetHeight > 0) panel.dataset.standardHeight = panel.offsetHeight + 'px';
    if (typeof myCloudApplyTheme === 'function') myCloudApplyTheme();

    modal.setAttribute('tabindex', '-1');
    modal.style.outline = 'none';
    modal.focus();
    modal.onkeydown = (e) => {
        if (e.key === 'Escape') {
            e.preventDefault();
            e.stopPropagation();
            window.myCloudCloseSettingsModal();
        }
    };
}

// Closes the settings modal, intercepting to warn about unsaved Admin tab changes if necessary.
window.myCloudCloseSettingsModal = function() {
    const panel = document.getElementById('myCloudSettingsPanel');
    if (panel) {
        const activeTabBtn = panel.querySelector('.ce-tab-btn.active');
        if (activeTabBtn && activeTabBtn.dataset.tab === 'admin') {
            if ((typeof ca_Is_Dirty !== 'undefined' && ca_Is_Dirty) || (typeof ca_Is_Dirty_Cfg !== 'undefined' && ca_Is_Dirty_Cfg)) {
                const msg = "You have unsaved changes in the Admin module.<br><br>Are you sure you want to close and discard them?";
                myCloudShowAlert('Unsaved Changes', msg, function() {
                    if (typeof ca_clean_dirty === 'function') ca_clean_dirty();
                    if (typeof ca_clean_dirty_cfg === 'function') ca_clean_dirty_cfg();
                    myCloudCloseModal();
                });
                return;
            }
        }
    }
    myCloudCloseModal();
};

// Renders the specific content for the selected settings tab and handles dynamic modal resizing.
function _cloudExRenderSettingsContent(panel, activeTab) {
    // Ensure settings object exists if we are on a specific device tab
    if (activeTab !== 'all' && activeTab !== 'tags' && !myCloudState.settings[activeTab]) {
        myCloudState.settings[activeTab] = JSON.parse(JSON.stringify(myCloudDefaultSettings[activeTab] || myCloudDefaultSettings['desktop']));
    }
    
    // Config context: null if 'all' or 'tags', otherwise the specific device settings
    const conf = (activeTab !== 'all' && activeTab !== 'tags') ? myCloudState.settings[activeTab] : null;
    
    const role = (typeof myCloudUserRole !== 'undefined') ? myCloudUserRole : 'no-access';
    const canEdit = window.myCloudActionAllowed('newfolder');

    // Get current global language
    const currentLang = myCloudState.settings.language || 'en';
    
    // Identify current device for the dot highlight
    const currentDeviceKey = (typeof myCloudGetCurrentDeviceKey === 'function') ? myCloudGetCurrentDeviceKey() : 'desktop';

    // --- Helper Functions ---
    const renderHeader = function(title) {
        return '<div class="ce-setting-header">' + title + '</div>';
    };

    const renderToggle = function(label, key, checked, isGlobal = false, disabled = false) {
        const style = isGlobal ? '' : '';
        return '<label class="ce-setting-row" style="cursor:'+(disabled?'default':'pointer')+'; border: none !important;' + style + '; opacity:'+(disabled?'0.6':'1')+';">' +
            '<span style="flex:1; padding-right:10px; line-height:1.2;">' + label + '</span>' +
            '<div class="ce-toggle-switch">' +
                '<input type="checkbox" data-key="' + key + '" ' + (checked ? 'checked' : '') + ' ' + (disabled ? 'disabled' : '') + '>' +
                '<span class="slider round"></span>' +
            '</div>' +
        '</label>';
    };

    // --- 1. Build Tab Navigation (ALL is First) ---
    const tabList = ['all', 'tags', 'desktop', 'tablet', 'phone'];
    if (window.myCloudIsGlobalAdmin) {
        tabList.push('admin');
    }
    if (window.myCloudLogviewerEnabled) {
        tabList.push('logviewer');
    }
    const tabs = tabList.map(function(t) {
        if (t === 'tags' && !window.myCloudActionAllowed('edit_tags')) return '';
        // Mark the respective tab based on the 2nd parameter
        const isActive = (t === activeTab) ? 'active' : '';
        
        // "All" gets a generic slider icon, others get device icons
        let icon = '';
        if (t === 'all') icon = '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>';
        else if (t === 'tags') icon = '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M21.41 11.58l-9-9C12.05 2.22 11.55 2 11 2H4c-1.1 0-2 .9-2 2v7c0 .55.22 1.05.59 1.41l9 9c.36.36.86.58 1.41.58.55 0 1.05-.22 1.41-.59l7-7c.37-.36.59-.86.59-1.41 0-.55-.23-1.06-.59-1.41zM5.5 7C4.67 7 4 6.33 4 5.5S4.67 4 5.5 4 7 4.67 7 5.5 6.33 7 5.5 7z"/></svg>';
        else if (t === 'admin') icon = '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/></svg>';
        else if (t === 'logviewer') icon = '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>';
        else icon = `<span style="display:inline-flex; transform: scale(${t === 'phone' ? '0.85' : (t === 'tablet' ? '1.1' : '1')});">${myCloudSvg[t] || ''}</span>`;
        
        // Use myCloud_LANG.all for title
        let label = '';
        if (t === 'all') label = myCloud_LANG.all || 'All';
        else if (t === 'tags') label = myCloud_LANG.tag_labels || 'Tags';
        else if (t === 'admin') label = 'Admin';
        else if (t === 'logviewer') label = 'Logviewer';
        else label = t.charAt(0).toUpperCase() + t.slice(1);


        // Highlight the current physical device
        if (t === currentDeviceKey) {
            label += '<span style="color:var(--accent-primary); font-size:10px; line-height:0; position:relative; top:1px; margin-inline-start:1px;" title="Current Device">✅</span>';
        }

        return '<button class="ce-tab-btn ' + isActive + '" data-tab="' + t + '" style="flex: 1 0 auto; white-space: nowrap;"><span class="ce-tab-icon">' + icon + '</span> ' + label + '</button>';
	}).join('');

    // --- 2. Build Language Options (Dynamic) ---
    const rawLangs = myCloud_LANG.available_languages || { 'en': 'English' };
    let langArray = Object.entries(rawLangs).map(([code, label]) => {
        return { code: code, label: label || code.toUpperCase() };
    });

    // Sort: Current Language Top, then Alphabetical
    langArray.sort((a, b) => {
        if (a.code === currentLang) return -1;
        if (b.code === currentLang) return 1;
        return a.label.localeCompare(b.label);
    });

    let optionsHtml = '';
    langArray.forEach(lang => {
        const isSel = (lang.code === currentLang) ? 'selected' : '';
        optionsHtml += '<option value="' + lang.code + '" ' + isSel + '>' + lang.label + '</option>';
    });

    let pwdChangeHtml = '';
    if (typeof myCloudHasAdvancedPwd !== 'undefined' && myCloudHasAdvancedPwd) {
        pwdChangeHtml = 
        '<div style="margin-top:10px; padding-top:10px; border-top:1px solid var(--border-subtle);">' +
            renderHeader(myCloud_LANG.change_password || 'Change Password') +
            '<div style="padding: 5px 24px 10px 24px;">' +
                '<button type="button" class="ce-settings-reset" onclick="event.preventDefault(); event.stopPropagation(); window.myCloudShowAdvancedPwdModal && window.myCloudShowAdvancedPwdModal()" style="margin:0; color:var(--accent-primary) !important; background:var(--hover-bg-light) !important; border:1px solid var(--accent-primary); padding: 8px 16px; border-radius: 4px;">' + (myCloud_LANG.open_pwd_manager || 'Open Password Manager') + '</button>' +
            '</div>' +
        '</div>';

    } else {
        pwdChangeHtml = 
        '<div style="margin-top:10px; padding-top:10px; border-top:1px solid var(--border-subtle);">' +
            renderHeader(myCloud_LANG.change_password || 'Change Password') +
            '<div style="padding: 5px 24px 10px 24px; display: flex; flex-direction: row; align-items: center; gap: 10px; flex-wrap: wrap;">' +
            '<input type="password" id="ce_pwd_old" autocomplete="new-password" data-lpignore="true" class="myCloudInlineInput" placeholder="' + (myCloud_LANG.old_password || 'Current Password') + '" style="margin:0; height:32px; font-size:13px; flex: 1; min-width: 130px;">' +
                '<div style="position:relative; display:flex; align-items:center; flex: 1; min-width: 130px;">' +
                    '<input type="password" id="ce_pwd_new" autocomplete="new-password" data-lpignore="true" class="myCloudInlineInput" placeholder="' + (myCloud_LANG.new_password || 'New Password') + '" style="margin:0; height:32px; font-size:13px; padding-inline-end:32px; width:100%;">' +
                    '<button type="button" tabindex="-1" onclick="ce_toggle_my_pwd(this)" style="position:absolute; inset-inline-end:4px; background:none; border:none; cursor:pointer; color:var(--text-secondary); padding:4px; display:flex; align-items:center; justify-content:center; transition:color 0.2s;" title="' + (myCloud_LANG.toggle_pwd_vis || 'Toggle visibility') + '">' +
                        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px; height:16px;"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>' +
                    '</button>' +
                '</div>' +
                '<button id="ce_pwd_btn" class="ce-settings-reset" onclick="ce_submit_password_change()" style="margin:0; color:var(--accent-primary) !important; background:var(--hover-bg-light) !important; border:1px solid var(--accent-primary); padding: 0 16px; height:32px; border-radius: 4px; flex-shrink: 0;">' + (myCloud_LANG.change_password || 'Change Password') + '</button>' +
            '</div>' +
        '</div>';
    }

    
    // --- Build Tag Renaming UI ---
    const tagColors = myCloudState.settings.tagOrder || ['#e81123', '#0078d4', '#107c10', '#f0ad4e', '#888888', '#673ab7', '#e91e63', '#009688', '#795548', '#607d8b'];
    let tagsHtml = '<div style="padding-top:10px;">';
    if (!window.myCloudActionAllowed('edit_tags')) {
        tagsHtml += '<div style="padding:20px; text-align:center; color:var(--text-secondary);">' + (myCloud_LANG.access_denied || 'Access Denied') + '</div></div>';
    } else {
        tagsHtml += renderHeader(myCloud_LANG.tag_labels || 'Tag Labels');
        tagsHtml += '<div style="font-size:12px; color:var(--text-secondary); margin-top:8px; margin-bottom:14px; padding:0 24px;">' +
                    (myCloud_LANG.tag_labels_hint || 'Assign custom names to your color tags. These will appear as tooltips.') + '</div>';
        tagsHtml += '<div class="ce-setting-block" style="display:flex; gap:0; padding:6px 0;">';
        const visibleArray = myCloudState.settings.visibleTags || ['#e81123', '#0078d4', '#107c10', '#f0ad4e', '#888888'];
        const renderCol = (colorsChunk, isLeftCol) => {
            let colHtml = '<div style="flex:1; display:flex; flex-direction:column;' + (isLeftCol ? ' border-inline-end: 1px solid var(--border-subtle);' : '') + '">';
            colorsChunk.forEach((c, idx) => {
                const currentName = (myCloudState.settings.tagNames && myCloudState.settings.tagNames[c]) ? myCloudState.settings.tagNames[c] : '';
                const defaultName = window.myCloudGetTagName(c, true);
                const isVisible = visibleArray.includes(c);
                const hasBottomBorder = idx < colorsChunk.length - 1;
                let borderStyle = hasBottomBorder ? 'border-bottom: 1px solid var(--border-subtle); ' : '';
                
                colHtml += '<div class="ce-tag-row-item" draggable="true" data-color="' + c + '" style="display:flex; align-items:center; gap:8px; padding:8px 16px; transition:background 0.2s; ' + borderStyle + '">' +
                '<span class="ca-drag-handle" style="cursor:grab; margin-right:2px; color:var(--border-strong); font-size:16px; user-select:none;">☰</span>' +
                '<label class="ce-toggle-switch" title="Show/Hide"><input type="checkbox" class="ce-tag-vis-cb" data-color="' + c + '" ' + (isVisible ? 'checked' : '') + '><span class="slider round"></span></label>' +
                '<div class="ce-tag-dot" style="background-color:' + c + '; width:16px; height:16px; margin:0; flex-shrink:0; box-shadow:inset 0 1px 3px rgba(0,0,0,0.2);"></div>' +
                '<input type="text" class="myCloudInlineInput ce-tag-name-input" data-color="' + c + '" placeholder="' + defaultName + '" value="' + currentName + '" style="flex:1; height:26px; font-size:13px; min-width:0; margin:0;">' +
                '<button class="ce-tag-clear-btn" data-color="' + c + '" title="' + (myCloud_LANG.clear_tags || 'Remove this tag from all items') + '" style="background:transparent; border:none; cursor:pointer; color:var(--danger); padding:4px; border-radius:4px; display:flex; align-items:center; justify-content:center; transition:background 0.2s;" onmouseenter="this.style.background=\'rgba(232, 17, 35, 0.1)\'" onmouseleave="this.style.background=\'transparent\'">' +
                    '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M16 9v10H8V9h8m-1.5-6h-5l-1 1H5v2h14V4h-3.5l-1-1zM18 7H6v12c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>' +
                '</button>' +
            '</div>';
            });
            colHtml += '</div>';
            return colHtml;
        };
        
        const mid = Math.ceil(tagColors.length / 2);
        tagsHtml += renderCol(tagColors.slice(0, mid), true);
        tagsHtml += renderCol(tagColors.slice(mid), false);

        tagsHtml += '</div></div>';
    }

    // --- 3. Build Content Blocks ---
    
    // Check if already running as an installed PWA
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    const installBtnHtml = isStandalone ? '<span></span>' : 
        '<button id="ceInstallAppBtn" class="ce-settings-reset" onclick="window.myCloudInstallAppManual && window.myCloudInstallAppManual();" style="margin:0; color:var(--success) !important;" title="' + (myCloud_LANG.install_app || 'Install App') + '">' +
            '<span style="font-size:16px; margin-right:6px; display:inline-flex; align-items:center;"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg></span> ' + (myCloud_LANG.install_app || 'Install App') +
        '</button>';


    // A. Global Settings Content (Tab: All)
    const langSelectHtml = 
        '<div style="padding:15px;">' +
            renderHeader(myCloud_LANG.general || 'General') +
            '<div class="ce-setting-row" style="padding-top: 10px; padding-bottom: 10px; border: none !important;">' +
                '<span>' + myCloud_LANG.set_language + '</span>' +
                '<select id="ceLangSelect" class="myCloudInlineInput" style="width:auto; height:22px; padding:0 8px; padding-top: 0px; margin:0; margin-top: -3px; font-size:12px;">' +
                    optionsHtml +
                '</select>' +
            '</div>' +
            // Note: 'rememberLastFolder' logic syncs across devices, so reading from 'desktop' as reference is safe
            renderToggle('<span style="font-size:13px; color:var(--text-primary); font-weight:normal;">' + 
                myCloud_LANG.set_remember_last + 
                '</span>', 'rememberLastFolder', myCloudState.settings.desktop.rememberLastFolder, true) +
            
            (canEdit ? renderToggle('<span style="font-size:13px; color:var(--text-primary); font-weight:normal;">' + 
                myCloud_LANG.enable_recycle + '</span>', 'enableRecycleBin', myCloudState.settings.enableRecycleBin, true) : '') +
             pwdChangeHtml +
            '<div style="display:flex; flex-wrap:wrap; gap:8px; justify-content:space-between; align-items:center; margin-top:15px; border-top:1px solid var(--border-subtle); padding-top:15px; padding-left:10px; padding-right:10px;">' +
                installBtnHtml +
                '<div style="display:flex; gap:8px; margin-left:auto;">' +
                    '<button id="ceRestartFraBtn" class="ce-settings-reset" onclick="window.myCloudStartFRA && window.myCloudStartFRA(); window.myCloudCloseSettingsModal();" style="margin:0; color:var(--accent-primary) !important;">' +
                        '<span style="font-size:16px;">🪄</span> ' + (myCloud_LANG.fra_restart || "Restart Assistant") +
                    '</button>' +
                    '<button class="ce-settings-reset" id="ceResetBtn" style="margin:0;">' +
                        '<span style="font-size:19px;"> </span> ' + myCloud_LANG.reset_settings +
                    '</button>' +
                '</div>' +
            '</div>' +
        '</div>';

    // B. Device Specific Settings Content (Tab: Desktop/Tablet/Phone)
    // Only generated if conf is valid (not 'all')
    const deviceSettingsHtml = conf ? (
        '<div class="ce-two-col">' + 
            // --- LEFT COLUMN: Layout & Appearance ---
            '<div class="ce-col">' +
                renderHeader(myCloud_LANG.layout || 'Layout') +
                renderToggle(myCloud_LANG.set_tree_open, 'treeOpen', conf.treeOpen) +
                
                (canEdit ? renderToggle(
                    myCloud_LANG.set_start_commander + 
                    ' <span style="font-size:10px; color:var(--text-secondary); display:block; margin-top:2px;">' + myCloud_LANG.set_start_cmd_hint + '</span>', 
                    'startInCommander', 
                    (conf.startInCommander && conf.startInCommander[myCloudState.key])
                ) : '') +       
                
                // Symbol Size Dropdown
                '<label class="ce-setting-row" >' +
                    '<span>' + myCloud_LANG.symbol_size + '</span>' +
                    '<select id="ceSymSize" class="myCloudInlineInput" style="width:110px; height:22px; padding:0; margin:0; margin-top: -3px; font-size:12px;">' +
                        '<option value="small" ' + (conf.symbolSize === 'small' ? 'selected' : '') + '>' + myCloud_LANG.sym_small + '</option>' +
                        '<option value="medium" ' + (conf.symbolSize === 'medium' ? 'selected' : '') + '>' + myCloud_LANG.sym_medium + '</option>' +
                        '<option value="large" ' + (conf.symbolSize === 'large' ? 'selected' : '') + '>' + myCloud_LANG.sym_large + '</option>' +
                        '<option value="xlarge" ' + (conf.symbolSize === 'xlarge' ? 'selected' : '') + '>' + myCloud_LANG.sym_xlarge + '</option>' +
                    '</select>' +
                '</label>' +
                
                // Font Size Slider
                '<label class="ce-setting-row" >' +
                    '<span>' + myCloud_LANG.set_font_size + ': <strong id="ceFontSizeLabel" style="color:var(--accent-primary); margin-inline-start:4px;">' + conf.fontSize + '</strong></span>' +
                    '<input type="range" class="ce-range-slider" min="0" max="5" step="1" value="' + conf.fontSize + '" id="ceFontSlider" style="width:140px; margin:0; flex-shrink:0;">' +
                '</label>' +
                
                renderHeader(myCloud_LANG.appearance || 'Appearance') +
                renderToggle(myCloud_LANG.set_dark_mode, 'darkMode', conf.darkMode) +
                renderToggle(myCloud_LANG.set_symbol_dark, 'symbolDarkMode', conf.symbolDarkMode) +
                (canEdit ? renderToggle(myCloud_LANG.set_ribbon, 'stackedToolbar', conf.stackedToolbar) : '') +
            '</div>' +

            // --- RIGHT COLUMN: Preview & Behavior ---
            '<div class="ce-col" style="border-left:1px solid var(--border-subtle);">' +
                renderHeader(myCloud_LANG.preview || 'Preview') +
                renderToggle(myCloud_LANG.set_click_preview, 'clickToPreview', conf.clickToPreview) +
                renderToggle(myCloud_LANG.set_list_thumbs, 'showListThumbnails', conf.showListThumbnails) +
                renderToggle(myCloud_LANG.set_warn_large, 'warnLargePreview', conf.warnLargePreview) +                    
                
                renderHeader(myCloud_LANG.behavior || 'Behavior') +
                renderToggle((myCloud_LANG.set_single_click || 'Single Click Mode') +
                    ' <span style="font-size:10px; color:var(--text-secondary); display:block; margin-top:2px;">' + (myCloud_LANG.set_single_click_hint || 'Cloud style: Click opens, Hover underlines') + '</span>', 
                    'singleClick', conf.singleClick) +
                renderToggle((myCloud_LANG.set_hide_disabled || 'Hide Disabled Items') + ((conf.stackedToolbar && activeTab !== 'phone') ? '' : ''), 'hideDisabled', (conf.stackedToolbar && activeTab !== 'phone') ? false : conf.hideDisabled, false, (conf.stackedToolbar && activeTab !== 'phone')) +
                renderToggle(myCloud_LANG.set_checkboxes, 'showCheckboxes', conf.singleClick ? true : conf.showCheckboxes, false, conf.singleClick) +
                renderToggle(myCloud_LANG.set_hover_menu, 'showHoverMenu', conf.showHoverMenu) +
            '</div>' +
        '</div>'
    ) : '';
    
    let activeContent = '';
    if (activeTab === 'all') activeContent = langSelectHtml;
    else if (activeTab === 'tags') activeContent = tagsHtml;
    else if (activeTab === 'admin') {
        // Consume the pre-rendered HTML payload securely passed via the data bridge
        if (window.myCloudAdminHtml) {
            activeContent = window.myCloudAdminHtml;
        } else {
            activeContent = '<div style="padding:20px; color:var(--danger);">Admin module failed to load.</div>';
        }
    }
    else if (activeTab === 'logviewer') {
        if (window.myCloudLogviewerHtml) {
            activeContent = window.myCloudLogviewerHtml;
        } else {
            activeContent = '<div style="padding:20px; color:var(--danger);">Logviewer module failed to load.</div>';
        }
    }
    else activeContent = deviceSettingsHtml;

    const modal = document.getElementById('myCloudModal');
    let wrapperStyle = '';
    let contentStyle = '';

    if (activeTab === 'admin' || activeTab === 'logviewer') {
        if (modal) {
            modal.style.width = '1000px';
            modal.style.maxWidth = '95vw';
        }
        // Enlarge the height for the Admin view
        panel.style.height = '75vh';
        panel.style.minHeight = '500px';
        wrapperStyle = 'border:none; box-shadow:none; border-radius:0; height:100%; display:flex; flex-direction:column;';
        contentStyle = 'flex:1; overflow-y:auto; padding: ' + (activeTab === 'logviewer' ? '20px;' : '0;');
    } else {
        if (modal) {
            modal.style.width = (window.innerWidth <= 600) ? '360px' : '700px';
            modal.style.maxWidth = '95vw';
        }
        panel.style.height = panel.dataset.standardHeight || 'auto';
        panel.style.minHeight = '';
        wrapperStyle = 'border:none; box-shadow:none; border-radius:0; display:flex; flex-direction:column;';
        contentStyle = 'min-height:' + (panel.dataset.minContentHeight || 'auto') + '; flex: 1; overflow-y:auto;';
    }

    // --- 4. Final HTML Injection ---
    panel.innerHTML = 
        '<style>.ce-settings-tabs::-webkit-scrollbar { display: none; }</style>' +
		'<div class="ce-settings-wrapper" style="' + wrapperStyle + '">' +
            '<div class="ce-settings-tabs" style="margin-top: 0px; flex-shrink:0; overflow-x:auto; display:flex; flex-wrap:nowrap; scrollbar-width:none; -ms-overflow-style:none;">' +
                tabs +
            '</div>' +
            '<div class="ce-settings-content" style="' + contentStyle + '">' +
                activeContent +
            '</div>' +
        '</div>';

    // --- 5. Event Binding ---
    
    // Tag Name Inputs
    panel.querySelectorAll('.ce-tag-name-input').forEach(inp => {
        inp.ondragstart = (e) => { e.preventDefault(); e.stopPropagation(); }; // Prevent inputs from triggering row drag
		inp.onchange = (e) => {
            e.stopPropagation();
            if (!myCloudState.settings.tagNames) myCloudState.settings.tagNames = {};
            myCloudState.settings.tagNames[e.target.dataset.color] = e.target.value.trim();
            myCloudSaveSettings();
            // Refresh UI to update existing tooltips
            if (typeof myCloudRenderUI === 'function') myCloudRenderUI();
        };
    });

    // Tag Drag & Drop Reordering
    let draggedColor = null;
    panel.querySelectorAll('.ce-tag-row-item').forEach(row => {
        row.ondragstart = (e) => {
            draggedColor = row.dataset.color;
            e.dataTransfer.effectAllowed = 'move';
            row.style.opacity = '0.4';
        };
        row.ondragover = (e) => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            row.style.background = 'var(--hover-bg-light)';
        };
        row.ondragleave = (e) => {
            row.style.background = 'transparent';
        };
        row.ondrop = (e) => {
            e.preventDefault();
            row.style.background = 'transparent';
            const targetColor = row.dataset.color;
            if (draggedColor && draggedColor !== targetColor) {
                let arr = myCloudState.settings.tagOrder || ['#e81123', '#0078d4', '#107c10', '#f0ad4e', '#888888', '#673ab7', '#e91e63', '#009688', '#795548', '#607d8b'];
                const srcIdx = arr.indexOf(draggedColor);
                arr.splice(srcIdx, 1);
                const dstIdx = arr.indexOf(targetColor);
                arr.splice(dstIdx, 0, draggedColor);
                myCloudState.settings.tagOrder = arr;
                
                if (myCloudState.settings.visibleTags) {
                    myCloudState.settings.visibleTags.sort((a, b) => arr.indexOf(a) - arr.indexOf(b));
                }
                
                myCloudSaveSettings();
                _cloudExRenderSettingsContent(panel, 'tags');
                if (typeof myCloudRenderUI === 'function') myCloudRenderUI();
            }
        };
        row.ondragend = (e) => {
            row.style.opacity = '1';
        };
    });


    // Tag Visibility Checkboxes
    panel.querySelectorAll('.ce-tag-vis-cb').forEach(cb => {
        cb.onchange = (e) => {
            e.stopPropagation();
            const color = e.target.dataset.color;
			const masterOrder = myCloudState.settings.tagOrder || ['#e81123', '#0078d4', '#107c10', '#f0ad4e', '#888888', '#673ab7', '#e91e63', '#009688', '#795548', '#607d8b'];
            if (!myCloudState.settings.visibleTags) myCloudState.settings.visibleTags = ['#e81123', '#0078d4', '#107c10', '#f0ad4e', '#888888'];
            if (e.target.checked) {
                if (!myCloudState.settings.visibleTags.includes(color)) myCloudState.settings.visibleTags.push(color);
            } else {
                myCloudState.settings.visibleTags = myCloudState.settings.visibleTags.filter(c => c !== color);
            }

            // Auto-sort to guarantee visual sequence integrity matches master sequence
            myCloudState.settings.visibleTags.sort((a, b) => masterOrder.indexOf(a) - masterOrder.indexOf(b));
			
            myCloudSaveSettings();
            if (typeof myCloudRenderUI === 'function') myCloudRenderUI();
        };
    });


    // Clear Tagged Items Buttons
    panel.querySelectorAll('.ce-tag-clear-btn').forEach(btn => {
        btn.onclick = (e) => {
            e.stopPropagation();
            const color = btn.dataset.color;
            const tagName = window.myCloudGetTagName(color);
            
            myCloudShowAlert(
                myCloud_LANG.tag_labels || 'Tag Labels',
                (myCloud_LANG.clear_tags_msg || 'Remove the tag <b>%s</b> from all items?').replace('%s', myCloudEscapeHtml(tagName)),
                function() {
                    if (myCloudState.tags && myCloudState.tags[myCloudState.key]) {
                        let changed = false;
                        const list = myCloudState.tags[myCloudState.key];
                        Object.keys(list).forEach(p => {
                            let t = list[p];
                            if (t === color) {
                                delete list[p];
                                changed = true;
                            } else if (Array.isArray(t) && t.includes(color)) {
                                list[p] = t.filter(c => c !== color);
                                if (list[p].length === 0) delete list[p];
                                changed = true;
                            }
                        });
                        if (changed) {
                            myCloudSaveTags();
                            // Reset active filters if we just deleted the filtered tag
                            if (myCloudState.activeTagFilter === color) myCloudState.activeTagFilter = null;
                            if (myCloudState.commanderLeft && myCloudState.commanderLeft.activeTagFilter === color) myCloudState.commanderLeft.activeTagFilter = null;
                            if (myCloudState.commanderRight && myCloudState.commanderRight.activeTagFilter === color) myCloudState.commanderRight.activeTagFilter = null;
                            
                            if (myCloudState.isCommanderMode && typeof refreshCommanderPane === 'function') {
                                refreshCommanderPane('left');
                                refreshCommanderPane('right');
                            } else {
                                myCloudRenderUI();
                            }
                        }
                    }
                }
            );
        };
    });

    // Language Select (Hot Swap)
    const langSel = panel.querySelector('#ceLangSelect');
    if (langSel) {
        langSel.onchange = function(e) {
            e.stopPropagation();
            const newLang = e.target.value;
            langSel.disabled = true;
            langSel.style.opacity = '0.5';
            
            const fd = new URLSearchParams();
            fd.append('myCloud_action', 'switch_language');
            fd.append('myCloud_key', myCloudState.key);
            fd.append('myCloud_token', myCloudCsrfToken);
            fd.append('lang', newLang);

            fetch('', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(resp => {
                if (resp.status === 'OK' && resp.strings) {
                    window.myCloud_LANG = resp.strings;
                    myCloudState.settings.language = newLang;

                    // Apply RTL/LTR
                    const rtlLangs = ['ar', 'fa', 'he', 'ur'];
                    const dir = rtlLangs.includes(newLang) ? 'rtl' : 'ltr';
                    document.getElementById('myCloudContainer').setAttribute('dir', dir);
                    
                    myCloudRenderUI(); 
                    _cloudExRenderSettingsContent(panel, activeTab); // Re-render self
                    
                    if (typeof window.cxToast === 'function') {
                        window.cxToast(newLang === 'de' ? 'Sprache geändert' : 'Language changed');
                    }
                }
            })
            .catch(err => {
                console.error(err);
                alert("Error switching language");
                langSel.disabled = false;
                langSel.style.opacity = '1';
            });
        };
    }

    // Tab Buttons
    panel.querySelectorAll('.ce-tab-btn').forEach(function(btn) {
        btn.onclick = function(e) {
            e.stopPropagation(); 
            _cloudExRenderSettingsContent(panel, btn.dataset.tab);
            if (btn.dataset.tab === 'admin' && typeof ca_init === 'function') {
                setTimeout(ca_init, 50);
            }
            if (btn.dataset.tab === 'logviewer' && typeof fetchAdminLogStats === 'function') {
                setTimeout(fetchAdminLogStats, 50);
            }
        };
    });

    // Checkboxes
    panel.querySelectorAll('input[type="checkbox"]:not(.ce-tag-vis-cb)').forEach(function(cb) {
        cb.onchange = function(e) {
            e.stopPropagation();
            const key = e.target.dataset.key;
            const val = e.target.checked;

            if (key === 'rememberLastFolder') {
                ['desktop', 'tablet', 'phone'].forEach(function(dev) {
                    if (myCloudState.settings[dev]) {
                        myCloudState.settings[dev][key] = val;
                    }
                });
            } else if (key === 'enableRecycleBin') {
                myCloudState.settings.enableRecycleBin = val;
                if(myCloudState.currentDir === '/') myCloudFetchDirectory('/');
            } else if (key === 'startInCommander') {
                if (!myCloudState.settings[activeTab].startInCommander) {
                    myCloudState.settings[activeTab].startInCommander = {};
                }
                // Ensure 'startInCommander' is a plain Object so JSON.stringify doesn't drop the keys.
                if (Array.isArray(myCloudState.settings[activeTab].startInCommander)) {
                   myCloudState.settings[activeTab].startInCommander = {};
                }
               
                const safeKey = (typeof myCloudState.key !== 'undefined') ? myCloudState.key : '';
                myCloudState.settings[activeTab].startInCommander[safeKey] = val;
            } else if (key === 'stackedToolbar') {
                myCloudState.settings[activeTab][key] = val;
                if (!val) {
                    myCloudState.settings[activeTab].hideDisabled = true;
                }
                _cloudExRenderSettingsContent(panel, activeTab);
            } else if (key === 'singleClick') {
                myCloudState.settings[activeTab][key] = val;
                // Enforce checkboxes if Single Click is ON
                if (val) {
                    myCloudState.settings[activeTab].showCheckboxes = true;
                }
                // Re-render settings panel to update the disabled state of checkbox toggle
                _cloudExRenderSettingsContent(panel, activeTab);
            } else {
                myCloudState.settings[activeTab][key] = val;
            }
            
            myCloudApplySettings(); 
            myCloudSaveSettings();
        };
    });
    
    // Symbol Size
    const symSelect = panel.querySelector('#ceSymSize');
    if (symSelect) {
        symSelect.onchange = function(e) {
            e.stopPropagation();
            myCloudState.settings[activeTab].symbolSize = e.target.value;
            myCloudSaveSettings();
            if (myCloudState.viewMode === 'symbol') myCloudRenderUI();
        };
    }

    // Font Slider
    const slider = panel.querySelector('#ceFontSlider');
    if (slider) {
        slider.oninput = function(e) {
            e.stopPropagation();
            panel.querySelector('#ceFontSizeLabel').textContent = e.target.value;
            myCloudState.settings[activeTab].fontSize = parseInt(e.target.value);
            myCloudApplySettings();
        };
        slider.onchange = function(e) { 
            e.stopPropagation();
            myCloudSaveSettings(); 
        };
        slider.onclick = function(e) { e.stopPropagation(); };
    }

    // Reset Button
    const resetBtn = panel.querySelector('#ceResetBtn');
    if (resetBtn) {
        resetBtn.onclick = function(e) {
            e.stopPropagation();
            
            const warningIcon = 
            '<div style="margin-bottom:15px; margin-top:5px">' +
               '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#f0ad4e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
                    '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>' +
                    '<line x1="12" y1="9" x2="12" y2="13"></line>' +
                    '<line x1="12" y1="17" x2="12.01" y2="17"></line>' +
               '</svg>' +
            '</div>';
            
            const msg = warningIcon + myCloud_LANG.reset_msg_confirm;

            myCloudShowAlert(
                myCloud_LANG.reset_title_short,
                msg,
                function() { myCloudResetSettings(); }
            );
        };
    }
}


// ============================================================================
// LOGVIEWER MODULE GLOBAL JAVASCRIPT
// ============================================================================

window.adminLogHandleIpClick = function(event, ip) {
    event.preventDefault();
    event.stopPropagation();
    
    const observer = new MutationObserver(function(mutations, obs) {
        const inp = document.getElementById("authadm_checkip_input_ip");
        if (inp && !inp.value) {
            inp.value = ip;
            obs.disconnect();
        }
    });
    observer.observe(document.body, { childList: true, subtree: true });
    setTimeout(function() { observer.disconnect(); }, 2000);

    if (typeof showIpMenu === "function") {
        showIpMenu(event, ip);
    } else if (typeof authadm_show_checkip_popup === "function") {
        authadm_show_checkip_popup(ip);
    }
};

window.toggleDetails = function(row) {
    const tbody = row.closest("tbody");
    const detailsRow = tbody.querySelector(".details-row");
    const icon = row.querySelector(".expand-icon");
    
    if (detailsRow.style.display === "none") {
        detailsRow.style.display = "table-row";
        icon.style.transform = "rotate(90deg)";
        icon.style.color = "var(--accent-primary)";
    } else {
        detailsRow.style.display = "none";
        icon.style.transform = "rotate(0deg)";
        icon.style.color = "var(--text-secondary)";
    }
};

window.filterUserStats = function() {
    const filterInput = document.getElementById("userStatsFilter");
    if (!filterInput) return;
    
    const term = filterInput.value.toLowerCase();
    const tbodies = document.querySelectorAll("#userStatsTable tbody.user-group");
    
    for (let i = 0; i < tbodies.length; i++) {
        const tbody = tbodies[i];
        const userNameCell = tbody.querySelector(".main-row td:nth-child(2)");
        if (userNameCell && userNameCell.textContent.toLowerCase().indexOf(term) !== -1) {
            tbody.style.display = "";
        } else {
            tbody.style.display = "none";
        }
    }
};

window.logViewerNaturalSort = function(a, b, direction) {
    const dateRegex = /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/;
    if (dateRegex.test(a) && dateRegex.test(b)) {
        return (new Date(a.replace(" ", "T")) - new Date(b.replace(" ", "T"))) * direction;
    }
    return a.localeCompare(b, undefined, { numeric: true, sensitivity: "base" }) * direction;
};

window.initTableSorting = function() {
    const tables = document.querySelectorAll("table.sortable-table");
    
    for (let i = 0; i < tables.length; i++) {
        const table = tables[i];
        const thead = table.querySelector("thead");
        if (!thead) continue;
        
        const headers = thead.querySelectorAll("th:not(.no-sort)");
        const isGrouped = table.querySelector("tbody.user-group") !== null;
        
        for (let j = 0; j < headers.length; j++) {
            const th = headers[j];
            th.addEventListener("click", function() {
                const index = Array.prototype.indexOf.call(th.parentNode.children, th);
                const isDescFirstCol = /Last|Failed|Week|Month|Year/i.test(th.textContent.trim());
                const currentlyAsc = th.classList.contains("sort-asc");
                const currentlyDesc = th.classList.contains("sort-desc");
                
                let nextIsAsc;
                if (!currentlyAsc && !currentlyDesc) {
                    nextIsAsc = isDescFirstCol ? false : true;
                } else {
                    nextIsAsc = !currentlyAsc;
                }

                const direction = nextIsAsc ? 1 : -1;

                for (let k = 0; k < headers.length; k++) {
                    headers[k].classList.remove("sort-asc", "sort-desc");
                }
                th.classList.add(nextIsAsc ? "sort-asc" : "sort-desc");

                if (isGrouped) {
                    const tbodies = Array.prototype.slice.call(table.querySelectorAll("tbody.user-group"));
                    tbodies.sort(function(a, b) {
                        const aText = a.querySelector(".main-row").children[index].textContent.trim();
                        const bText = b.querySelector(".main-row").children[index].textContent.trim();
                        return window.logViewerNaturalSort(aText, bText, direction);
                    });
                    for (let k = 0; k < tbodies.length; k++) {
                        table.appendChild(tbodies[k]);
                    }
                } else {
                    const tbody = table.querySelector("tbody.standard-group");
                    if (!tbody) return;
                    
                    const rows = Array.prototype.slice.call(tbody.querySelectorAll("tr"));
                    if (rows.length === 1 && rows[0].children.length === 1) return;
                    
                    rows.sort(function(a, b) {
                        const aText = a.children[index].textContent.trim();
                        const bText = b.children[index].textContent.trim();
                        return window.logViewerNaturalSort(aText, bText, direction);
                    });
                    for (let k = 0; k < rows.length; k++) {
                        tbody.appendChild(rows[k]);
                    }
                }
            });
        }
    }
};

window.fetchAdminLogStats = function() {
    var content = document.getElementById("adminLogStatsContent");
    var timeframeSelect = document.getElementById("adminLogTimeframe");
    if (!content || !timeframeSelect) return;
    
    var timeframe = timeframeSelect.value;
    var filterValue = "";
    var filterInput = document.getElementById("userStatsFilter");
    if (filterInput) {
        filterValue = filterInput.value;
    }
    
    content.innerHTML = '<div class="admin-log-loader"><div class="admin-log-spinner"></div><div style="font-weight:500;">Analyzing log files...</div></div>';

    var xhr = new XMLHttpRequest();
    xhr.open("POST", window.location.href, true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
    
    xhr.onload = function() {
        if (xhr.status === 200) {
            content.innerHTML = xhr.responseText;
            window.initTableSorting(); 
            
            var newFilterInput = document.getElementById("userStatsFilter");
            if (newFilterInput && filterValue) {
                newFilterInput.value = filterValue;
                window.filterUserStats();
            }
        } else {
            content.innerHTML = "<h2>Error</h2><p class='error-message'>Failed to fetch statistics (HTTP " + xhr.status + ").</p>";
        }
    };
    
    xhr.onerror = function() {
        content.innerHTML = "<h2>Error</h2><p class='error-message'>Network error occurred.</p>";
    };
    
    xhr.send("action=get_admin_log_stats&timeframe=" + encodeURIComponent(timeframe));
};

</script>