<?php
/**
 * ============================================================================
 * MODULE: Core JavaScript Engine
 * ============================================================================
 * Provides the foundational client-side logic, defining global application state, 
 * base configuration, event listeners, and essential utility functions.
 * NOTE: Executed exclusively by the client browser.
 * 
 * THIS IS PART OF THE PSEUDO "JS FILE" - SO THE NO PHP CODE IN THIS FILE!
 */
?><script>

// ============================================================================
// OAUTH2 POPUP INTERCEPTOR (Full Implementation)
// ============================================================================
(function() {
    if (window.location.search.includes('code=') && window.location.search.includes('state=')) {
        document.body.innerHTML = '<div style="display:flex; justify-content:center; align-items:center; height:100vh; font-family:sans-serif; background:#fff; color:#333;">Authenticating with Microsoft...</div>';
        
        try {
            const urlParams = new URLSearchParams(window.location.search);
            const stateStr = urlParams.get('state');
            const stateObj = JSON.parse(atob(stateStr));
            
            if (stateObj && stateObj.myCloud_action === 'oauth_callback') {
                // 1. Get tokens safely
                let csrf = typeof window.myCloudCsrfToken !== 'undefined' ? window.myCloudCsrfToken : '';
                if (!csrf && window.opener && window.opener.myCloudCsrfToken) csrf = window.opener.myCloudCsrfToken;
                
                let key = stateObj.key || '';
                if (!key && window.opener && window.opener.myCloudState) key = window.opener.myCloudState.key;
                
                const targetUrl = window.location.origin + window.location.pathname;
                console.log("OAuth Callback: Sending POST to", targetUrl);

                // 2. Use XHR for maximum compatibility in popup contexts
                const xhr = new XMLHttpRequest();
                xhr.open('POST', targetUrl, true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                
                const fd = new URLSearchParams({
                    myCloud_action: 'email_oauth_callback',
                    myCloud_key: key,
                    myCloud_token: csrf,
                    code: urlParams.get('code'),
                    account_id: stateObj.acc_id,
                    redirect_uri: stateObj.uri || targetUrl
                });

                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        if (xhr.status === 200) {
                            try {
                                const res = JSON.parse(xhr.responseText);
                                if (res.status === 'OK') {
                                    document.body.innerHTML = '<div style="display:flex; justify-content:center; align-items:center; height:100vh; font-family:sans-serif; color:#107c10; font-weight:bold;">Success! Closing...</div>';
                                    if (window.opener && typeof window.opener.myCloudEmailFetchFolders === 'function') {
                                        window.opener.myCloudEmailFetchFolders(true);
                                    }
                                    setTimeout(() => window.close(), 1500);
                                } else {
                                    document.body.innerHTML = '<div style="display:flex; justify-content:center; align-items:center; height:100vh; font-family:sans-serif; color:#e81123; text-align:center; padding:20px;">Server Error:<br><br>' + (res.msg || 'Unknown error') + '</div>';
                                }
                            } catch (e) {
                                document.body.innerHTML = '<div style="display:flex; justify-content:center; align-items:center; height:100vh; font-family:sans-serif; color:#e81123; text-align:center; padding:20px;">Parse Error:<br><br>' + xhr.responseText.substring(0, 200) + '</div>';
                            }
                        } else {
                            document.body.innerHTML = '<div style="display:flex; justify-content:center; align-items:center; height:100vh; font-family:sans-serif; color:#e81123;">HTTP Error ' + xhr.status + ': Could not reach server.</div>';
                        }
                    }
                };
                xhr.onerror = () => {
                    document.body.innerHTML = '<div style="display:flex; justify-content:center; align-items:center; height:100vh; font-family:sans-serif; color:#e81123;">Network Error during authentication.</div>';
                };
                xhr.send(fd.toString());
            }
        } catch(e) {
            document.body.innerHTML = '<div style="display:flex; justify-content:center; align-items:center; height:100vh; font-family:sans-serif; color:#e81123;">Invalid OAuth State.</div>';
            console.error(e);
        }
    }
})();


// Universal Rights Checker
window.myCloudActionAllowed = function(action, currentRight) {
    if (!action) return true;
    const right = currentRight || (typeof myCloudUserRole !== 'undefined' ? myCloudUserRole : 'read-only');
    if (right === 'admin_mode') return true;
    if (typeof MYCLOUD_RIGHTS_MATRIX === 'undefined') return false;

    // Use a Set to track visited roles and prevent infinite recursion/deadlocks
    const visited = new Set();
    
    function isBlocked(role) {
        if (visited.has(role)) return false; 
        visited.add(role);
        
        const roleConfig = MYCLOUD_RIGHTS_MATRIX[role];
        if (!roleConfig || !roleConfig.blocked) return false;
        
        // Wildcard blocks everything
        if (roleConfig.blocked === '*') return true;
        
        // Direct block check
        if (roleConfig.blocked.includes(action)) return true;
        
        // Deep inheritance check
        for (let i = 0; i < roleConfig.blocked.length; i++) {
            const parentKey = roleConfig.blocked[i];
            if (MYCLOUD_RIGHTS_MATRIX[parentKey] && isBlocked(parentKey)) {
                return true;
            }
        }
        return false;
    }

    return !isBlocked(right);
};

// Zoom limits and sensitivity settings.
// Used by image previewer logic.
const MIN_SCALE = 0.2;
const MAX_SCALE = 10;
const ZOOM_BUTTON_STEP = 0.4;
const WHEEL_ZOOM_SENSITIVITY = 0.25;

// Localization strings for JS-generated modals.
// Used by Logout and Alert functions.
const myCloud_I18N = {
    signOut: myCloud_LANG.sign_out,
    logoutConfirm: myCloud_LANG.logout_confirm,
    cancel: myCloud_LANG.cancel
};

// Main configuration for file handling and icon mapping.
// Used throughout the app to determine file types and capabilities.
const myCloudConfig = {
    preview: ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'txt', 'log', 'docx', 'xlsx', 'mp4', 'webm', 'ogg', 'mov', 'mkv', 'mp3', 'wav', 'm4a', 'flac', 'epub', 'ttf', 'woff', 'woff2', 'kml', 'kmz', 'eml'],
    previewIcons: ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico', 'txt', 'ini', 'md', 'json', 'xml', 'html', 'css', 'js', 'php', 'sql', 'log', 'conf', 'sh', 'py', 'pdf', 'docx', 'xlsx', 'mp4', 'webm', 'ogg', 'mov', 'mkv', 'avi', 'mpg', 'mpeg', 'mp3', 'wav', 'm4a', 'flac', 'epub', 'ttf', 'woff', 'woff2', 'kml', 'kmz', 'eml'],
    navigable: ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'pdf', 'txt', 'log', 'docx', 'xlsx', 'mp4', 'webm', 'mp3', 'wav', 'ogg', 'm4a', 'epub', 'ttf', 'woff', 'woff2', 'kml', 'kmz', 'eml'],
    edit: ['htm', 'html', 'css', 'php', 'reg', 'ini', 'js', 'url', 'c', 'cpp', 'h', 'md', 'json', 'xml', 'csv', 'tsv', 'yaml', 'yml', 'cfg', 'conf', 'config', 'log', 'txt', 'sh', 'py', 'hpp', 'sql', 'ps1', 'bat', 'cmd'],
    image: ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'],
    video: ['mp4', 'webm', 'ogg', 'mov', 'mkv'],
    audio: ['mp3', 'wav', 'm4a', 'flac', 'aac'],
    binary: ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'ico', 'mp4', 'webm', 'ogg', 'mov', 'mkv', 'mp3', 'wav', 'm4a', 'flac', 'docx', 'xlsx', 'zip', 'tar', 'gz', '7z', 'rar', 'epub', 'ttf', 'woff', 'woff2', 'kml', 'kmz'],
    office: ['docx', 'doc', 'xlsx', 'xls', 'pptx', 'ppt', 'odt', 'ods', 'odp', 'csv', 'rtf']
};

// Shortcuts for file type arrays.
// Used in conditions to check file capabilities.
const previewExts = myCloudConfig.preview;
const editExts = myCloudConfig.edit;
const imageExts = myCloudConfig.image;
const videoExts = myCloudConfig.video;
const audioExts = myCloudConfig.audio;
const binaryExts = myCloudConfig.binary;
const officeExts = myCloudConfig.office;

// Global Drag Avatar Generator
window.myCloudGetDragImage = function(count) {
    let dragEl = document.getElementById('ce-drag-avatar');
    if (!dragEl) {
        dragEl = document.createElement('div');
        dragEl.id = 'ce-drag-avatar';
        dragEl.style.cssText = 'position:absolute; top:-1000px; left:-1000px; background:var(--accent-primary); color:#fff; border-radius:20px; padding:6px 12px; font-size:13px; font-weight:600; box-shadow:0 4px 15px rgba(0,0,0,0.4); display:flex; align-items:center; gap:8px; z-index:-1; font-family:var(--font-family, sans-serif);';
        document.body.appendChild(dragEl);
    }
    const itemText = count === 1 ? (typeof myCloud_LANG !== 'undefined' ? myCloud_LANG.item_uc || 'Item' : 'Item') : (typeof myCloud_LANG !== 'undefined' ? myCloud_LANG.items_uc || 'Items' : 'Items');
    dragEl.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path><polyline points="13 2 13 9 20 9"></polyline></svg>' + 
                       '<span>' + count + ' ' + itemText + '</span>';
    return dragEl;
};

// Determines if the explicit "Edit" buttons should be shown for a file
window.myCloudIsFileEditable = function(itemName, isInsideZip) {
    if (typeof myCloudHasOnlyOffice === 'undefined' || !myCloudHasOnlyOffice) return false;
    if (isInsideZip || !window.myCloudActionAllowed('edit_file')) return false;
    if (!itemName || itemName.endsWith('/')) return false;
    const ext = itemName.split('.').pop().toLowerCase();
    const isOffice = typeof officeExts !== 'undefined' && officeExts.includes(ext);
    const isPdf = ext === 'pdf';
    const isAdminMode = typeof myCloudUserRole !== 'undefined' && myCloudUserRole === 'admin_mode';
    const isText = (typeof editExts !== 'undefined' && editExts.includes(ext)) || (isAdminMode && typeof binaryExts !== 'undefined' && !binaryExts.includes(ext));
    return isOffice || isPdf || isText;
};


// SVG Icon definitions.
// Used by toolbar and context menus.
const myCloudSvg = {
    toggle_tree: '<svg viewBox="0 0 24 24" style="fill:#444"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg>',
    search: '<svg viewBox="0 0 24 24" style="fill:#444"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>',
	commander_toggle: '<svg viewBox="0 0 24 24"><path d="M4 18h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-7V4H4c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2zm9-10h7v8h-7V8zm-9 0h7v8H4V8z"/></svg>',
    office_toggle: '<svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-8 14H5V7h6v10zm8 0h-6V7h6v10z"/></svg>',
    refresh: '<svg viewBox="0 0 24 24" style="fill:#444"><path d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/></svg>',
    newfolder: '<svg viewBox="0 0 24 24" style="fill:#444"><path d="M20 6h-8l-2-2H4c-1.1 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-1 8h-3v3h-2v-3h-3v-2h3V9h2v3h3v2z"/></svg>',
    newfile: '<svg viewBox="0 0 24 24" style="fill:#444"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 14h-3v3h-2v-3H8v-2h3v-3h2v3h3v2zm-3-7V3.5L18.5 9H13z"/></svg>',
	copy: '<svg viewBox="0 0 24 24" style="fill:#444"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg>',
    move: '<svg viewBox="0 0 24 24" style="fill:#444"><path d="M20 6h-8l-2-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm-6 12v-3h-4v-4h4V8l5 5-5 5z"/></svg>',
    edit_file: '<svg viewBox="0 0 24 24" style="fill:#444"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>',
    duplicate: '<svg viewBox="0 0 24 24" style="fill:#444"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm-2 9h-3v3h-2v-3H9v-2h3V9h2v3h3v2z"/></svg>',
    print: '<svg viewBox="0 0 24 24" style="fill:#444"><path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-2-9H7v3h10V3z"/></svg>',
    delete: '<svg viewBox="0 0 24 24" style="fill:#444"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>',
    preview: '<svg viewBox="0 0 24 24" style="fill:#444"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>',
	rename: '<svg viewBox="0 0 24 24" style="fill:#444"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V5h14v14z"/><path d="M11 7h2v10h-2z"/><path d="M9 7h6v2H9z"/><path d="M9 15h6v2H9z"/></svg>',
	select_all: '<svg viewBox="0 0 24 24"><path d="M18 7l-1.41-1.41-6.34 6.34 1.41 1.41L18 7zm4.24-1.41L11.66 16.17 7.48 12l-1.41 1.41L11.66 19l12-12-1.42-1.41zM.41 13.41L6 19l1.41-1.41L1.83 12 .41 13.41z"/></svg>',
    invert_selection: '<svg viewBox="0 0 24 24"><path d="M14 12c0-1.1-.9-2-2-2s-2 .9-2 2 .9 2 2 2 2-.9 2-2zM12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/></svg>',
    clear_selection: '<svg viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>',
	download: '<svg viewBox="0 0 24 24" style="fill:#444"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>',
    upload: '<svg viewBox="0 0 24 24" style="fill:#444"><path d="M9 16h6v-6h4l-7-7-7 7h4v6zm-4 2h14v2H5v-2z"/></svg>',
    terminal: '<svg viewBox="0 0 24 24"><path d="M20 4H4c-1.11 0-2 .9-2 2v12c0 1.1.89 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 14H4V8h16v10zm-2-1h-6v-2h6v2zM7.5 17l-1.41-1.41L8.67 13l-2.58-2.59L7.5 9l4 4-4 4z"/></svg>',
    palette: '<svg viewBox="0 0 24 24"><path d="M2 17h20v2H2v-2zm4.25-2l.66-1.56L11.48 2l2.36.95-4.22 10.05H6.25zM15 11l-1.34-3L11 9.34l1.34 3L15 11zm3.8-3.4l-1.35-3L14.8 6l1.35 3L18.8 7.6z"/></svg>',
    view: '<svg viewBox="0 0 24 24" style="fill:#444"><path d="M4 11h5V5H4v6zm0 7h5v-6H4v6zm6 0h5v-6h-5v6zm6 0h5v-6h-5v6zm-6-7h5V5h-5v6zm6-6v6h5V5h-5z"/></svg>',
    zip: '<svg viewBox="0 0 24 24"><path d="M14 2H6C4.9 2 4.01 2.9 4.01 4L4 20C4 21.1 4.89 22 5.99 22H18C19.1 22 20 21.1 20 20V8L14 2ZM18 20H6V4H13V9H18V20ZM10 14H12V18H10V14ZM10 10H12V12H10V10ZM14 10H16V12H14V10ZM14 14H16V18H14V14Z"/></svg>',
    unzip: '<svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V5h14v14zm-8-2h2v-4h4v-2h-4V7h-2v4H7v2h4z"/></svg>',
    star: '<svg viewBox="0 0 24 24"><path d="M22 9.24l-7.19-.62L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21 12 17.27 18.18 21l-1.63-7.03L22 9.24zM12 15.4l-3.76 2.27 1-4.28-3.32-2.88 4.38-.38L12 6.1l1.71 4.01 4.38.38-3.32 2.88 1 4.28L12 15.4z"/></svg>',
    star_filled: '<svg viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>',
    hdIcon: '<svg viewBox="0 0 24 24"><path d="M7.5 5.6L10 7 8.6 4.5 10 2 7.5 3.4 5 2l1.4 2.5L5 7zm12 9.8L17 14l1.4 2.5L17 19l2.5-1.4L22 19l-1.4-2.5L22 14zM22 2l-2.5 1.4L17 2l1.4 2.5L17 7l2.5-1.4L22 7l-1.4-2.5zm-7.63 5.29c-.39-.39-1.02-.39-1.41 0L1.29 18.96c-.39.39-.39 1.02 0 1.41l2.34 2.34c.39.39 1.02.39 1.41 0L16.7 11.05c.39-.39.39-1.02 0-1.41l-2.33-2.35zm-1.03 5.41l-2.12-2.12 2.44-2.44 2.12 2.12-2.44 2.44z"/></svg>',
    sdIcon: '<svg viewBox="0 0 24 24"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12L19 18H5l3.5-4.5z"/></svg>',
    tools: '<svg viewBox="0 0 24 24"><path d="M22.7 19l-9.1-9.1c.9-2.3.4-5-1.5-6.9-2-2-5-2.4-7.4-1.3L9 6 6 9 1.6 4.7C.4 7.1.9 10.1 2.9 12.1c1.9 1.9 4.6 2.4 6.9 1.5l9.1 9.1c.4.4 1 .4 1.4 0l2.3-2.3c.5-.4.5-1.1.1-1.4z"/><path d="M16 20h6v-6l-6 6z" fill="currentColor"/></svg>',
    edit: '<svg viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/><path d="M16 20h6v-6l-6 6z" fill="currentColor"/></svg>',
    settings: '<svg viewBox="0 0 24 24"><path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg>',
    desktop: '<svg viewBox="0 0 24 24"><path d="M21 2H3c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h7v2H8v2h8v-2h-2v-2h7c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H3V4h18v12z"/></svg>',
    tablet: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M21 4H3c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h18c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm-2 14H5V6h14v12zm-1-6c0 .55-.45 1-1 1s-1-.45-1-1 .45-1 1-1 1 .45 1 1z"/></svg>',
	phone: '<svg viewBox="0 0 24 24"><path d="M17 1.01L7 1c-1.1 0-2 .9-2 2v18c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2V3c0-1.1-.9-1.99-2-1.99zM17 19H7V5h10v14z"/></svg>',
	trash: '<svg viewBox="0 0 24 24"><path d="M16 9v10H8V9h8m-1.5-6h-5l-1 1H5v2h14V4h-3.5l-1-1zM18 7H6v12c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7z"/></svg>',
	recycle_main: '<svg viewBox="0 0 24 24" fill="#757575"><path d="M19 4h-3.5l-1-1h-5l-1 1H5v2h14M6 19a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V7H6v12Z"/></svg>',
	permissions: '<svg viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/></svg>',
	pdf_stack_menu: '<svg viewBox="0 0 24 24"><path d="M4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm16-4H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H8V4h12v12z" fill="currentColor"/></svg>',
};

// File extension specific icons.
// Used for List/Gallery views.
const myCloudTypeIcons = {
    doc: '<svg viewBox="0 0 24 24" fill="none"><path d="M14 2H6C4.9 2 4.01 2.9 4.01 4L4 20C4 21.1 4.89 22 5.99 22H18C19.1 22 20 21.1 20 20V8L14 2Z" fill="#2B579A"/><path d="M14 2V8H20" fill="#2B579A" fill-opacity="0.3"/><path d="M9.17 16.83L7.59 10.66H9.25L10 14.5H10.04L10.91 10.66H12.41L13.29 14.5H13.33L14.08 10.66H15.74L14.16 16.83H12.5L11.54 12.75H11.5L10.58 16.83H9.17Z" fill="white"/></svg>',
    docx: '<svg viewBox="0 0 24 24" fill="none"><path d="M14 2H6C4.9 2 4.01 2.9 4.01 4L4 20C4 21.1 4.89 22 5.99 22H18C19.1 22 20 21.1 20 20V8L14 2Z" fill="#2B579A"/><path d="M14 2V8H20" fill="#2B579A" fill-opacity="0.3"/><path d="M9.17 16.83L7.59 10.66H9.25L10 14.5H10.04L10.91 10.66H12.41L13.29 14.5H13.33L14.08 10.66H15.74L14.16 16.83H12.5L11.54 12.75H11.5L10.58 16.83H9.17Z" fill="white"/></svg>',
    xls: '<svg viewBox="0 0 24 24" fill="none"><path d="M14 2H6C4.9 2 4.01 2.9 4.01 4L4 20C4 21.1 4.89 22 5.99 22H18C19.1 22 20 21.1 20 20V8L14 2Z" fill="#217346"/><path d="M14 2V8H20" fill="#217346" fill-opacity="0.3"/><path d="M9.17 16.83L11.5 13L9.33 9.17H11.16L12.33 11.5H12.37L13.54 9.17H15.29L13.17 13L15.5 16.83H13.67L12.33 14.2H12.29L10.96 16.83H9.17Z" fill="white"/></svg>',
    xlsx: '<svg viewBox="0 0 24 24" fill="none"><path d="M14 2H6C4.9 2 4.01 2.9 4.01 4L4 20C4 21.1 4.89 22 5.99 22H18C19.1 22 20 21.1 20 20V8L14 2Z" fill="#217346"/><path d="M14 2V8H20" fill="#217346" fill-opacity="0.3"/><path d="M9.17 16.83L11.5 13L9.33 9.17H11.16L12.33 11.5H12.37L13.54 9.17H15.29L13.17 13L15.5 16.83H13.67L12.33 14.2H12.29L10.96 16.83H9.17Z" fill="white"/></svg>',
    pdf: '<svg viewBox="0 0 24 24" fill="none"><path d="M20 2H8C6.9 2 6 2.9 6 4V16C6 17.1 6.9 18 8 18H20C21.1 18 22 17.1 22 16V4C22 2.9 21.1 2 20 2ZM11.5 9.5C11.5 10.33 10.83 11 10 11H9V13H7.5V7H10C10.83 7 11.5 7.67 11.5 8.5V9.5ZM13.5 13.5H11V7H13.5C14.33 7 15 7.67 15 8.5V12C15 12.83 14.33 13.5 13.5 13.5ZM19.5 10H17V11.5H19V13H15.5V7H19.5V8.5Z" fill="#E53935"/><path d="M4 6H2V20C2 21.1 2.9 22 4 22H18V20H4V6Z" fill="#E53935"/></svg>',
    txt: '<svg viewBox="0 0 24 24" fill="none"><path d="M14 2H6C4.9 2 4.01 2.9 4.01 4L4 20C4 21.1 4.89 22 5.99 22H18C19.1 22 20 21.1 20 20V8L14 2Z" fill="#757575"/><path d="M14 2V8H20" fill="#757575" fill-opacity="0.3"/><path d="M7 10H17V12H7V10ZM7 14H17V16H7V14ZM7 18H13V20H7V18Z" fill="white"/></svg>',
    jpg: '<svg viewBox="0 0 24 24" fill="none"><path d="M14 2H6C4.9 2 4.01 2.9 4.01 4L4 20C4 21.1 4.89 22 5.99 22H18C19.1 22 20 21.1 20 20V8L14 2Z" fill="#AB47BC"/><path d="M14 2V8H20" fill="#AB47BC" fill-opacity="0.3"/><path d="M8.5 13.5L11 16.51L14.5 12L19 18H5L8.5 13.5Z" fill="white"/></svg>',
    png: '<svg viewBox="0 0 24 24" fill="none"><path d="M14 2H6C4.9 2 4.01 2.9 4.01 4L4 20C4 21.1 4.89 22 5.99 22H18C19.1 22 20 21.1 20 20V8L14 2Z" fill="#AB47BC"/><path d="M14 2V8H20" fill="#AB47BC" fill-opacity="0.3"/><path d="M8.5 13.5L11 16.51L14.5 12L19 18H5L8.5 13.5Z" fill="white"/></svg>',
    zip: '<svg viewBox="0 0 24 24" fill="none"><path d="M20 6H16V4C16 2.89 15.11 2 14 2H10C8.89 2 8 2.89 8 4V6H4C2.9 6 2 6.9 2 8V20C2 21.1 2.9 22 4 22H20C21.1 22 22 21.1 22 20V8C22 6.9 21.1 6 20 6ZM10 4H14V6H10V4ZM20 20H4V8H8V10H10V8H14V10H16V8H20V20Z" fill="#FBC02D"/><path d="M12 12V14H10V12H12ZM14 14V16H12V14H14ZM12 16V18H10V16H12ZM14 18V20H12V18H14Z" fill="#F57F17"/></svg>',
    html: '<svg viewBox="0 0 24 24" fill="none"><path d="M14 2H6C4.9 2 4.01 2.9 4.01 4L4 20C4 21.1 4.89 22 5.99 22H18C19.1 22 20 21.1 20 20V8L14 2Z" fill="#009688"/><path d="M14 2V8H20" fill="#009688" fill-opacity="0.3"/><path d="M9.4 16.6L4.8 12L9.4 7.4L8 6L2 12L8 18L9.4 16.6ZM14.6 16.6L19.2 12L14.6 7.4L16 6L22 12L16 18L14.6 16.6Z" fill="white"/></svg>',
    php: '<svg viewBox="0 0 24 24" fill="none"><path d="M14 2H6C4.9 2 4.01 2.9 4.01 4L4 20C4 21.1 4.89 22 5.99 22H18C19.1 22 20 21.1 20 20V8L14 2Z" fill="#5C6BC0"/><path d="M14 2V8H20" fill="#5C6BC0" fill-opacity="0.3"/><path d="M13 9H11.5V11H13C14.1 11 15 10.1 15 9V9C15 7.9 14.1 7 13 7ZM13 15H11.5V17H10V7H13C15.2 7 17 8.8 17 11V11C17 13.2 15.2 15 13 15Z" fill="white"/></svg>',
    mp3: '<svg viewBox="0 0 24 24" fill="#00BCD4"><path d="M12 3v9.28c-.47-.17-.97-.28-1.5-.28C8.01 12 6 14.01 6 16.5S8.01 21 10.5 21c2.31 0 4.16-1.75 4.45-4H15V6h4V3h-7z"/></svg>',
    mp4: '<svg viewBox="0 0 24 24" fill="#FF9800"><path d="M18 4l2 4h-3l-2-4h-2l2 4h-3l-2-4H8l2 4H7L5 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V4h-4z"/></svg>',
	epub: '<svg viewBox="0 0 24 24" fill="none"><path d="M14 2H6C4.9 2 4.01 2.9 4.01 4L4 20C4 21.1 4.89 22 5.99 22H18C19.1 22 20 21.1 20 20V8L14 2Z" fill="#8D6E63"/><path d="M14 2V8H20" fill="#8D6E63" fill-opacity="0.3"/><path d="M6 6h12v2H6zm0 4h12v2H6zm0 4h8v2H6zm0 4h12v2H6z" fill="white"/></svg>',
    _default: '<svg viewBox="0 0 24 24" fill="#9E9E9E"><path d="M13 9h5.5L13 3.5V9M6 2h8l6 6v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2m0 2v16h12V11h-7V4H6z"/></svg>'
};

// Generic Folder Icon.
// Default icon for directory items.
const myCloudIconFolder = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#ffc800"><path d="M10 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"></path></svg>';
const myCloudIconLinkFolder = myCloudIconFolder.replace('fill="#ffc800"', 'fill="#ff9000"');
const myCloudIconZipFolder = myCloudIconFolder.replace('fill="#ffc800"', 'fill="#7E57C2"');

// Generic File Icon.
// Fallback icon for unknown file types.
const myCloudIconFile = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#6c757d"><path fill-rule="evenodd" clip-rule="evenodd" d="M14 2H6C4.9 2 4.01 2.9 4.01 4L4 20C4 21.1 4.89 22 5.99 22H18C19.1 22 20 21.1 20 20V8L14 2ZM13 9V3.5L18.5 9H13Z"></path></svg>';




// ============================================================================
// SECTION 2: GLOBAL STATE & VARIABLES
// ============================================================================

// Help Shown Flag.
// Prevents help modal from showing multiple times in one session.
window.myCloudHelpShown = false;

// Search Selection.
// Holds the currently selected item object in the search modal.
let myCloudSearchSelection = null;

// Property Stack.
// History stack for navigating into folders within the Properties window.
let myCloudPropStack = [];

// Tree Visibility Flag.
// State for showing/hiding the sidebar tree view.
let myCloudTreeVisible = true;

// Settings Panel Reference.
// DOM reference to the open settings panel to manage its lifecycle.
let myCloudSettingsPanel = null;

// Multi-Cloud Flag.
// Indicates if UI should render tabs for multiple cloud accounts.
let myCloudIsMultiCloudMode = false;

// Settings Save Timer.
// Debounce timer ID for syncing settings to the server.
let myCloudSaveTimer = null;

// Menu Close Timer.
// Delay timer for closing hover-based ribbon menus.
let myCloudMenuTimer = null;

// Type-to-Seek Buffer.
// Accumulated keystrokes for quick search in lists.
window.ceTypeBuffer = '';

// Type-to-Seek Reset Timer.
// Timer to clear the search buffer after inactivity.
window.ceTypeTimer = null;

// ============================================================================
// NATIVE APP INTEGRATIONS (Wake Lock & Badging)
// ============================================================================
window.myCloudActiveTasks = 0;
window.myCloudWakeLock = null;

window.myCloudTaskStart = async function() {
    window.myCloudActiveTasks++;
    if ('setAppBadge' in navigator) navigator.setAppBadge(window.myCloudActiveTasks).catch(()=>{});
    
    if ('wakeLock' in navigator && window.myCloudWakeLock === null) {
        try {
            window.myCloudWakeLock = await navigator.wakeLock.request('screen');
        } catch (err) {}
    }
};

window.myCloudTaskEnd = async function() {
    window.myCloudActiveTasks = Math.max(0, window.myCloudActiveTasks - 1);
    if ('clearAppBadge' in navigator && window.myCloudActiveTasks === 0) navigator.clearAppBadge().catch(()=>{});
    else if ('setAppBadge' in navigator) navigator.setAppBadge(window.myCloudActiveTasks).catch(()=>{});

    if (window.myCloudActiveTasks === 0 && window.myCloudWakeLock !== null) {
        await window.myCloudWakeLock.release().catch(()=>{});
        window.myCloudWakeLock = null;
    }
};

// Global Transform State.
// Coordinates and scale for image manipulation (pan/zoom/rotate).
window.myCloudTransform = {
    scale: 1, translateX: 0, translateY: 0, rotate: 0, flipH: 1, flipV: 1,
    panning: false, pinching: false, startX: 0, startY: 0,
    startScale: 1, startDist: 0, startCenterX: 0, startCenterY: 0
};

// Main Application State.
// Central store for current directory, file list, selection, and config.
const myCloudState = {
    sessionEpoch: 0, interface: 'default', key: (typeof myCloudUserKeys !== 'undefined' && myCloudUserKeys.length > 0) ? myCloudUserKeys[0] : '',
    openDirs: ['/'], currentDir: '/', currentFile: null, selectedFiles: [], lastSelectedIndex: -1,
    viewMode: 'list', viewSettings: {}, sort: { col: 'name', dir: 1 }, items: [], allItems: [], loadedDirs: [],
    searchResults: [], searchSort: { col: 'name', dir: 1 },
    favorites: {}, tags: {}, activeTagFilter: null, lastPaths: {},
    autoDownload: (typeof window.myCloudAutoDownload !== 'undefined' && window.myCloudAutoDownload),
    fontLevel: 1, previewCache: {}, settings: null, visualCursorIndex: 0,
   // Commander Mode State
   isCommanderMode: false,
   isOfficeMode: false,
   commanderLeft: { dir: '/', selectedFiles: [], visualCursorIndex: 0, viewMode: 'list', items: [] },
   commanderRight: { dir: '/', selectedFiles: [], visualCursorIndex: 0, viewMode: 'list', items: [] },
   commanderActive: 'left', // 'left' or 'right'
   commanderSplitRatio: 0.5, // 50%
   originalSidebarSize: null // Store to restore later
};

// ============================================================================
// CSRF AUTO-RECOVERY ENGINE (Protects all fetch calls globally)
// ============================================================================
let isRefreshingCsrf = false;
let csrfSubscribers = [];
function myCloudRefreshCsrfToken(callback) {
    if (isRefreshingCsrf) {
        csrfSubscribers.push(callback);
        return;
    }
    isRefreshingCsrf = true;
    const fd = new URLSearchParams({ myCloud_action: 'refresh_csrf', myCloud_key: myCloudState.key });
    fetch('', { method: 'POST', body: fd }).then(r => r.json()).then(res => {
        if (res.status === 'OK' && res.token) {
            window.myCloudCsrfToken = res.token;
            callback(true);
            csrfSubscribers.forEach(cb => cb(true));
        } else {
            callback(false); csrfSubscribers.forEach(cb => cb(false));
        }
    }).catch(() => { callback(false); csrfSubscribers.forEach(cb => cb(false)); })
    .finally(() => { isRefreshingCsrf = false; csrfSubscribers = []; });
}

const originalFetch = window.fetch;
window.fetch = async function(...args) {
	// [FIX] Force Accept header so PHP recognizes this as an AJAX request
	let reqOpts = args[1] || {};
	reqOpts.headers = reqOpts.headers || {};
	if (reqOpts.headers instanceof Headers) { 
        if (!reqOpts.headers.has('Accept')) reqOpts.headers.set('Accept', 'application/json, text/plain, */*'); 
        if (window.myCloudAcceptLang) reqOpts.headers.set('Accept-Language', window.myCloudAcceptLang);
    } else { 
        if (!reqOpts.headers['Accept']) reqOpts.headers['Accept'] = 'application/json, text/plain, */*'; 
        if (window.myCloudAcceptLang) reqOpts.headers['Accept-Language'] = window.myCloudAcceptLang;
    }
	args[1] = reqOpts;

    let response = await originalFetch.apply(this, args);
    const clone = response.clone();
    try {
        const cType = clone.headers.get("content-type");
        if (cType && cType.includes("application/json")) {
            const data = await clone.json();
            if (data && (data.code === 'CSRF_FAILED' || (data.msg && data.msg.includes('CSRF')))) {
                return new Promise((resolve) => {
                    myCloudRefreshCsrfToken((success) => {
                        if (success) {
                            let req = args[1] || {};
                            if (req.body && req.body.set) req.body.set('myCloud_token', window.myCloudCsrfToken);
                            resolve(originalFetch.apply(this, [args[0], req]));
                        } else {
                            resolve(response);
                        }
                    });
                });
            }
        }
    } catch (e) {}
    return response;
};


// ============================================================================
// SECTION 3: DEVICE INTELLIGENCE MODULE
// ============================================================================

// Device Detection Module.
// Analyzes User Agent and screen metrics to classify device type.
const myCloudDevice = (function() {
    const ua = navigator.userAgent, nav = navigator;
    const width = window.innerWidth, height = window.innerHeight;

    // Capability Checks
    const hasTouchHardware = (nav.maxTouchPoints > 0) || ('ontouchstart' in window);
    const isPrimaryTouch = window.matchMedia("(pointer: coarse)").matches;
    const isHighDPI = (window.devicePixelRatio || 1) > 1;

    // OS Detection
    let os = 'unknown';
    if (/Windows/.test(ua)) os = /Phone/.test(ua) ? 'windows-phone' : 'windows';
    else if (/Android/.test(ua)) os = 'android';
    else if (/iPad|iPhone|iPod/.test(ua)) os = 'ios';
    else if (/Mac|Macintosh/.test(ua)) os = (hasTouchHardware && nav.maxTouchPoints > 1) ? 'ios' : 'macos';
    else if (/Linux/.test(ua)) os = 'linux';

    // Foldable Detection
    const ratio = width / height;
    const isSquareish = (ratio > 0.8 && ratio < 1.25);
    const isFoldableHardware = /Fold|SamsungBrowser/i.test(ua) && (height > width * 1.8 || width > height * 1.8 || isSquareish) ||
        window.matchMedia('(screen-spanning: single-fold-horizontal)').matches ||
        window.matchMedia('(screen-spanning: single-fold-vertical)').matches ||
        (hasTouchHardware && isSquareish && width > 500 && width < 1200);

    // Form Factor Logic
    let type = 'desktop', category = 'pc';
    if (os === 'android' || os === 'ios' || os === 'windows-phone') {
        const minDim = Math.min(width, height);
        if (minDim < 600) { type = 'phone'; category = 'mobile'; }
        else { type = 'tablet'; category = 'tablet'; }
    } else if (hasTouchHardware) {
        type = (isPrimaryTouch || width < 1024) ? 'tablet' : 'laptop-touch';
        category = (type === 'tablet') ? 'tablet' : 'pc';
    }

    if (isFoldableHardware) {
        if (width < 600) { type = 'foldable-folded'; category = 'mobile'; }
        else { type = 'foldable-unfolded'; category = 'tablet'; }
    }

    return { os, isTouch: hasTouchHardware, isPrimaryTouch, type, category, isFoldable: isFoldableHardware, orientation: width > height ? 'landscape' : 'portrait', width, height };
})();

// Apply Environment Classes.
// Adds CSS classes for OS-specific styling tweaks.
(function myCloudApplyEnvironment() {
    if (myCloudDevice.os === 'android' || myCloudDevice.os === 'ios') document.documentElement.classList.add('ce-mobile-os');
})();

// Global Secure Fast Thumbnail URL Generator
window.myCloudGetFastThumbUrl = function(path) {
    if (/\.zip(\/|$)/i.test(path)) return null; 
    // Encode to URL-safe Base64 to obscure the clear-text path in the Network tab
    const b64Path = window.btoa(unescape(encodeURIComponent(path)))
                          .replace(/\+/g, '-')
                          .replace(/\//g, '_')
                          .replace(/=+$/, '');

    const b64Key = window.btoa(unescape(encodeURIComponent(myCloudState.key)))
                          .replace(/\+/g, '-')
                          .replace(/\//g, '_')
                          .replace(/=+$/, '');

    return '?myCloud_thumb=' + b64Path + '&myCloud_key=' + b64Key + '&t=' + encodeURIComponent(myCloudCsrfToken);
};

// ============================================================================
// SECTION 4: CORE EXPLORER LIFECYCLE
// ============================================================================

// Starts the Explorer logic.
// Handles auth check, settings load, and initial data fetch.
async function myCloudStartExplorer(key = null) {
    if (key === 'all') { myCloudIsMultiCloudMode = true; key = (typeof myCloudUserKeys !== 'undefined' && myCloudUserKeys.length > 0) ? myCloudUserKeys[0] : ''; }
    if (!key) key = (typeof myCloudUserKeys !== 'undefined' && myCloudUserKeys.length > 0) ? myCloudUserKeys[0] : '';

    myCloudState.isInitializing = true;
    myCloudState.pathsLoaded = false;

    // Kill Email Background Routines if switching away
    try {
        if (window._emailSseWorker && typeof window._emailSseWorker.abort === 'function') window._emailSseWorker.abort();
    } catch(e) {}
    if (window._emailBgPoller) clearInterval(window._emailBgPoller);
    try {
        if (window.myCloudEmailState && window.myCloudEmailState.abortController) {
            window.myCloudEmailState.abortController.abort();
        }
    } catch(e) {}
	
	myCloudShowLoading();

    // Force Exit Commander Mode before switching context
    if (typeof myCloudState !== 'undefined' && myCloudState.isCommanderMode) {
        const body = document.querySelector('.myCloudBody');
        document.querySelectorAll('.myCloud-commander-pane, .myCloud-commander-resizer-container').forEach(el => el.remove());
        if (body) {
            body.classList.remove('commander-mode');
            const resizers = body.querySelectorAll('.myCloudResizer');
            for(let i = 1; i < resizers.length; i++) resizers[i].remove();
        }
        myCloudState.isCommanderMode = false;
        const tree = document.querySelector('.myCloudTree');
        const details = document.querySelector('.myCloudDetails');
        const resizer = document.querySelector('.myCloudResizer');
        if (tree) tree.style.display = '';
        if (details) details.style.display = '';
        if (resizer) resizer.style.display = '';
    }

    const conf = (typeof myCloudCloudConfig !== 'undefined') ? myCloudCloudConfig[key] : null;
    const targetRights = conf ? conf.rights : 'no-access';

    if (targetRights === 'no-access') { 
        myCloudShowAlert(typeof myCloud_LANG !== 'undefined' ? myCloud_LANG.error_lbl : 'Error', typeof myCloud_LANG !== 'undefined' ? myCloud_LANG.access_denied : 'Access Denied'); 
        return; 
    }

    const container = document.getElementById('myCloudContainer');
    const body = document.querySelector('.myCloudBody');
    const isAlreadyOpen = (container.style.display && container.style.display !== 'none');

    if (isAlreadyOpen && body) { body.style.transition = 'none'; body.style.opacity = '0'; body.classList.remove('visible'); }

    myCloudClearSensitiveState();
	
    myCloudUserRole = targetRights;
    if (document.getElementById('myCloudToolbar')) document.getElementById('myCloudToolbar').style.display = 'none';
	
    myCloudState.key = key;
    myCloudState.interface = (conf && conf.interface) ? conf.interface : 'default';
    myCloudState.openDirs = ['/']; myCloudState.currentDir = '/'; myCloudState.currentFile = null;

    const switcher = document.getElementById('myCloudCloudSwitcher');
	if (switcher) {
        myCloudRenderCloudSwitcher();
        document.querySelectorAll('.ce-cloud-btn').forEach(btn => btn.classList.toggle('active', btn.dataset.key === key));
        if (typeof myCloudUserKeys !== 'undefined' && myCloudUserKeys.length > 1) {
            switcher.style.display = 'flex';
        } else {
            switcher.style.display = 'none';
        }
    }	
    const toolbar = document.getElementById('myCloudToolbar');
    if (toolbar) {
        if (myCloudState.interface === 'gallery') toolbar.classList.add('gallery-hidden');
        else toolbar.classList.remove('gallery-hidden');
    }

    // --- THE BULLETPROOF VIRTUAL APP BYPASS ---
    // If the interface is email, we completely skip all file-system API calls
    const isVirtualApp = (myCloudState.interface === 'email');

    const initPromises = [myCloudLoadSettings(true)];
    if (!isVirtualApp) {
        if (typeof myCloudLoadFavorites === 'function') initPromises.push(myCloudLoadFavorites());
        if (typeof myCloudLoadTags === 'function') initPromises.push(myCloudLoadTags());
        if (typeof myCloudLoadPaths === 'function') initPromises.push(myCloudLoadPaths());
        if (typeof myCloudLoadViewSettings === 'function') initPromises.push(myCloudLoadViewSettings());
    } else {
        myCloudState.pathsLoaded = true;
    }
    await Promise.all(initPromises);

	if (!window.myCloudHelpShown && myCloudState.settings.showHelpOnStart && typeof window.myCloudOpenHelp === 'function') { window.myCloudOpenHelp(); window.myCloudHelpShown = true; }
	if (typeof window.myCloudStartFRA === 'function' && !myCloudState.settings.fra_completed) {
		window.myCloudStartFRA();
	}

    const devKey = myCloudGetCurrentDeviceKey();
    myCloudTreeVisible = myCloudState.settings[devKey].treeOpen;
    
    if (['gallery', 'symbol', 'symbol-dark'].includes(myCloudState.interface)) {
        myCloudState.viewMode = 'symbol';
        myCloudTreeVisible = false; 
    } else if (!isVirtualApp) { 
        const startPath = (myCloudState.settings[devKey].rememberLastFolder && myCloudState.settings.lastPaths && myCloudState.settings.lastPaths[key]) ? myCloudState.settings.lastPaths[key] : '/';
        if (typeof myCloudGetEffectiveViewMode === 'function') {
            myCloudState.currentDir = startPath; 
            myCloudState.viewMode = myCloudGetEffectiveViewMode(startPath);
        } else {
            myCloudState.viewMode = 'list';
        }
		myCloudTreeVisible = myCloudState.settings[devKey].treeOpen;
    }
    
    const tree = document.querySelector('.myCloudTree'), resizer = document.querySelector('.myCloudResizer'), btnTree = document.getElementById('btnToggleTree');
    if (myCloudTreeVisible && !isVirtualApp) {
        if (tree) tree.style.display = ''; if (resizer) resizer.style.display = '';
        if (btnTree) { btnTree.classList.add('tree-on'); btnTree.classList.remove('tree-off'); }
    } else {
        if (tree) tree.style.display = 'none'; if (resizer) resizer.style.display = 'none';
        if (btnTree) { btnTree.classList.add('tree-off'); btnTree.classList.remove('tree-on'); }
    }
 
    const shouldRemember = myCloudState.settings[devKey].rememberLastFolder;
    let restorePath = null;
    if (shouldRemember && myCloudState.lastPaths && myCloudState.lastPaths[key] && !isVirtualApp) {
        if (typeof myCloudState.lastPaths[key] === 'string') {
            restorePath = myCloudState.lastPaths[key];
        } else if (myCloudState.lastPaths[key].std) {
            restorePath = myCloudState.lastPaths[key].std;
        }
    }

    const initialPath = restorePath || '/';
    if (['gallery', 'symbol', 'symbol-dark'].includes(myCloudState.interface)) {
        myCloudState.viewMode = 'symbol';
        myCloudTreeVisible = false;
    } else if (!isVirtualApp) {
        myCloudState.viewMode = myCloudGetEffectiveViewMode(initialPath);
    }
    
	myCloudState.currentDir = initialPath;

    if (!isVirtualApp) {
        await myCloudFetchDirectory('/', 3, true);

        if (restorePath && restorePath !== '/') {
            const result = await myCloudFetchDirectory(restorePath, 2, true);
            if (result && result.status === 'OK') { myCloudExpandToPath(restorePath); }
            else if (result && result.code !== 'AUTH_REQUIRED') { myCloudState.currentDir = '/'; }
        }
    }

    // 1. Render Standard UI (Which intercepts and draws email if needed)
    myCloudRenderUI();

	// 2. Auto-Switch to Commander if configured
    const startCmdSettings = myCloudState.settings[devKey].startInCommander;
    if (startCmdSettings && startCmdSettings[key] && myCloudUserRole !== 'read-only' && !isVirtualApp) {
        myCloudToggleCommander();
    }
	
    if (myCloudState.settings[devKey].isOfficeMode && typeof myCloudToggleOffice === 'function' && !isVirtualApp) {
	      myCloudToggleOffice(true);
    }

    if (!isVirtualApp) {
        myCloudRestoreSidebarSize(); myCloudInitSidebarResizer(); myCloudInitKeyboardNav(); myCloudInitMarquee();
    }

    if (body) {
        myCloudHideLoading();
        if (!isAlreadyOpen) { 
            container.style.display = 'flex';
            void container.offsetWidth; 
            container.classList.remove('ce-anim-close'); 
            container.classList.add('ce-anim-open'); 
            body.classList.add('visible');
            setTimeout(() => { container.classList.remove('ce-anim-open'); body.style.transition = ''; body.style.opacity = ''; }, 800); 
        } else { 
            requestAnimationFrame(() => { void body.offsetWidth; body.style.transition = ''; body.style.opacity = ''; body.classList.add('visible'); }); 
        }
    }
    const details = document.querySelector('.myCloudDetails'); 
    if (details && !isVirtualApp) { 
        details.focus(); 
        if (typeof myCloudResetListCursor === 'function') myCloudResetListCursor(); 
    }

    // --- CHECK FOR INCOMING OS SHARE FILES ---
    if (window.myCloudPendingShares && window.myCloudPendingShares > 0) {
        setTimeout(() => {
            const msg = window.myCloudPendingShares + " " + (myCloud_LANG.items_lc || "items") + " received. " + (myCloud_LANG.select_dest || "Select destination:");
            myCloudShowTreeSelector(msg, myCloud_LANG.save || "Save", function(targetDir) {
                myCloudCreateProgressUI(myCloud_LANG.saving || "Saving...");
                const fd = new URLSearchParams({
                    myCloud_action: 'commit_share',
                    myCloud_key: myCloudState.key,
                    myCloud_token: window.myCloudCsrfToken,
                    dest: targetDir
                });
                fetch('', {method: 'POST', body: fd}).then(r => r.json()).then(res => {
                    myCloudCloseProgressUI();
                    window.myCloudPendingShares = 0;
                    if (res.status === 'OK') {
                        myCloudFetchDirectory(targetDir).then(() => {
                            myCloudExpandToPath(targetDir);
                            myCloudState.currentDir = targetDir;
                            myCloudRenderUI();
                        });
                    } else {
                        myCloudShowAlert('Error', res.msg);
                    }
                });
            });

            const cleanupStash = () => {
                if (window.myCloudPendingShares > 0) {
                    window.myCloudPendingShares = 0;
                    fetch('', {method: 'POST', body: new URLSearchParams({myCloud_action: 'cancel_share', myCloud_key: myCloudState.key, myCloud_token: window.myCloudCsrfToken})});
                }
            };
            const modal = document.getElementById('myCloudModal');
            const cancelBtn = modal.querySelector('.myCloudButtons button:first-child');
            const xBtn = modal.querySelector('.myCloudModalHeader button');
            if (cancelBtn) { const oldOnClick = cancelBtn.onclick; cancelBtn.onclick = (e) => { cleanupStash(); if(oldOnClick) oldOnClick(e); }; }
            if (xBtn) { const oldXClick = xBtn.onclick; xBtn.onclick = (e) => { cleanupStash(); if(oldXClick) oldXClick(e); }; }
            const oldOnKeyDown = modal.onkeydown;
            modal.onkeydown = (e) => { if (e.key === 'Escape') cleanupStash(); if (oldOnKeyDown) oldOnKeyDown(e); };
        }, 800);
    }

    myCloudState.isInitializing = false;
    if (typeof myCloudSaveCurrentPathState === 'function') myCloudSaveCurrentPathState();
    window.myCloudEnsureHistoryTrap();
}


// Closes the Explorer UI.
// Triggers exit animation and resets state.
function myCloudCloseExplorer() {
    const container = document.getElementById('myCloudContainer');
    myCloudIsMultiCloudMode = false;

    // [NEW] Save Sidebar Position for Current Device on Exit
    const tree = document.querySelector('.myCloudTree');
    const body = document.querySelector('.myCloudBody');
    if (tree && body && myCloudState.settings && typeof myCloudGetCurrentDeviceKey === 'function') {
        const devKey = myCloudGetCurrentDeviceKey(); // 'desktop', 'tablet', or 'phone'
        const isVert = window.getComputedStyle(body).flexDirection === 'column';
        const currentSize = isVert ? tree.offsetHeight : tree.offsetWidth;
        // Only save if tree is actually visible (> 0) and has a valid size
        if (tree.offsetParent !== null && currentSize > 50 && myCloudState.settings[devKey]) {
            myCloudState.settings[devKey].sidebarSize = Math.round(currentSize);
            myCloudSaveSettings();
        }
    }


    if (container && container.style.display !== 'none') {
        container.classList.remove('ce-anim-open'); container.classList.add('ce-anim-close');
        setTimeout(() => {
            container.style.display = 'none'; container.classList.remove('ce-anim-close');
            document.body.style.overflow = ''; document.body.classList.remove('dark-mode');
            document.getElementById('myCloudModalOverlay').style.display = 'none';
            myCloudHideLoading(); myCloudClearSensitiveState();
        }, 680);
    } else {
        document.body.style.overflow = ''; document.body.classList.remove('dark-mode');
        if (container) container.style.display = 'none';
        document.getElementById('myCloudModalOverlay').style.display = 'none';
        myCloudHideLoading(); myCloudClearSensitiveState();
    }
}

// ============================================================================
// SECTION 4.5: LAST PATHS SYNC
// ============================================================================

window.myCloudLoadPaths = async function() {
    try {
        const fd = new URLSearchParams({ myCloud_action: 'load_paths', myCloud_key: myCloudState.key, myCloud_token: myCloudCsrfToken });
        const resp = await fetch('', { method: 'POST', body: fd }).then(r => r.json());
        if (resp.status === 'OK') {
            myCloudState.lastPaths = Array.isArray(resp.paths) ? {} : (resp.paths || {});
        }
        myCloudState.pathsLoaded = true;
    } catch (e) { 
        console.warn("Paths load failed", e); 
        myCloudState.pathsLoaded = true; 
    }
};

window.myCloudSaveCurrentPathState = function() {
	// Guard against startup race condition overwriting path history
	if (myCloudState.isInitializing || !myCloudState.pathsLoaded || !myCloudState.key) return;
    // Email interface has no path history
    if (myCloudState.interface === 'email') return;	
	
    const devKey = typeof myCloudGetCurrentDeviceKey === 'function' ? myCloudGetCurrentDeviceKey() : 'desktop';
    if (!myCloudState.settings || !myCloudState.settings[devKey] || !myCloudState.settings[devKey].rememberLastFolder) return;

    if (!myCloudState.lastPaths) myCloudState.lastPaths = {};
    // MIGRATION / SAFETY: Convert legacy strings to the required Object format and ensure all keys exist
    if (!myCloudState.lastPaths[myCloudState.key]) {
        myCloudState.lastPaths[myCloudState.key] = { std: '/', cmdLeft: '/', cmdRight: '/' };
    } else if (typeof myCloudState.lastPaths[myCloudState.key] === 'string') {
		const oldPath = myCloudState.lastPaths[myCloudState.key];
        myCloudState.lastPaths[myCloudState.key] = { std: oldPath, cmdLeft: oldPath, cmdRight: '/' };
    } else {
        if (!myCloudState.lastPaths[myCloudState.key].std) myCloudState.lastPaths[myCloudState.key].std = '/';
        if (!myCloudState.lastPaths[myCloudState.key].cmdLeft) myCloudState.lastPaths[myCloudState.key].cmdLeft = '/';
        if (!myCloudState.lastPaths[myCloudState.key].cmdRight) myCloudState.lastPaths[myCloudState.key].cmdRight = '/';
     }

    const st = myCloudState;
    const pathObj = myCloudState.lastPaths[st.key];
    let changed = false;

    if (st.isCommanderMode) {
        if (st.commanderLeft && pathObj.cmdLeft !== st.commanderLeft.dir) { pathObj.cmdLeft = st.commanderLeft.dir; changed = true; }
        if (st.commanderRight && pathObj.cmdRight !== st.commanderRight.dir) { pathObj.cmdRight = st.commanderRight.dir; changed = true; }
    } else {
        const savePath = (st.currentDir === '/.recycle_bin') ? '/' : st.currentDir;
        if (pathObj.std !== savePath) { pathObj.std = savePath; changed = true; }
    }

    if (changed) {
        const fd = new URLSearchParams({ myCloud_action: 'save_paths', myCloud_key: st.key, myCloud_token: myCloudCsrfToken, paths_json: JSON.stringify(myCloudState.lastPaths) });
        fetch('', { method: 'POST', body: fd }).catch(() => {});
    }
};


// Resets global state variables.
// Ensures no data leaks between sessions.
function myCloudClearSensitiveState() {
    if (typeof myCloudState.sessionEpoch === 'undefined') myCloudState.sessionEpoch = 0; myCloudState.sessionEpoch++;
    myCloudState.allItems = []; myCloudState.items = []; myCloudState.loadedDirs = []; myCloudState.selectedFiles = [];
    myCloudState.searchResults = []; myCloudState.currentFile = null; myCloudState.previewPath = null; myCloudState.previewCache = {};
    const tree = document.querySelector('.myCloudTree'), details = document.querySelector('.myCloudDetails'), toolbar = document.getElementById('myCloudToolbar');
    if (tree) tree.innerHTML = ''; if (details) details.innerHTML = ''; if (toolbar) toolbar.innerHTML = '';
    myCloudState.lastSelectedIndex = -1;
	
	// [FIX] Remove lingering Symbol Mode Toast and Body Classes on reset/switch
    const toast = document.getElementById('myCloudSymbolActionToast');
    if (toast) toast.remove();
    document.body.classList.remove('multi-select-active');
    
    // [FIX] Clean up global event listeners from Symbol View
    if (typeof window.myCloudSymbolCleanup === 'function') {
        window.myCloudSymbolCleanup();
    }
}

// ============================================================================
// SECTION 5: AUTHENTICATION
// ============================================================================

// Shows logout confirmation modal.
// Provides buttons to cancel or confirm logout.
function myCloudDoLogout() {
    myCloudCloseContextMenus();
    const overlay = document.getElementById('myCloudModalOverlay');
    const modal = document.getElementById('myCloudModal');
    modal.className = 'myCloudModal'; modal.style.maxWidth = '300px'; modal.style.height = 'auto';

    modal.innerHTML = 
    '<div class="myCloudModalHeader" style="background: var(--gray-10); color: var(--text-primary); padding: 10px 14px; font-size: 15px;">' +
    '    <span>' + myCloudSvgLogo + '&nbsp;' + myCloud_I18N.signOut + '</span><span class="myCloudClose" style="color:var(--text-secondary); font-size:18px;" onclick="myCloudCloseModal()">✕</span>' +
    '</div>' +
    '<div class="myCloudModalBody" style="padding: 20px 15px;">' +
    '    <div style="display:flex; align-items:center; gap:14px; margin-bottom: 20px;">' +
    '        <div style="width:40px; height:40px; background:var(--gray-30); border-radius:50%; display:flex; align-items:center; justify-content:center; flex-shrink:0;">' +
    '            <svg viewBox="0 0 24 24" width="22" height="22" fill="#555"><path d="M16 13v-2H7V8l-5 4 5 4v-3h9zM20 3H4c-1.1 0-2 .9-2 2v4h2V5h16v14H4v-4H2v4c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg>' +
    '        </div>' +
    '        <div style="color: var(--text-primary); font-size: 14px; font-weight:400;">' + myCloud_I18N.logoutConfirm + '</div>' +
    '    </div>' +
    '    <div class="myCloudButtons" style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 0;">' +
    '        <button onclick="myCloudCloseModal()" style="min-width: 70px; font-size:13px; padding: 6px 12px;">' + myCloud_I18N.cancel + '</button>' +
    '        <button id="myCloudLogoutBtn" onclick="myCloudPerformLogout()" style="background: #e81123; border-color: #e81123; color: #fff; min-width: 70px; font-size:13px; padding: 6px 12px; font-weight:500;">' + myCloud_LANG.logout + '</button>' +
 	'    </div>' +
    '</div>';
	
    overlay.style.display = 'flex';
    setTimeout(() => {
        const btn = document.getElementById('myCloudLogoutBtn'); if (btn) btn.focus();
        modal.onkeydown = (e) => { if (e.key === 'Escape') document.getElementById('myCloudModalOverlay').style.display = 'none'; };
    }, 50);
}

// Executes logout sequence.
// Animates UI out and redirects.
window.myCloudPerformLogout = async function() {
    // [NEW] Save Sidebar Position on Logout
    const tree = document.querySelector('.myCloudTree');
    const body = document.querySelector('.myCloudBody');
    if (tree && body && myCloudState.settings && typeof myCloudGetCurrentDeviceKey === 'function') {
        const devKey = myCloudGetCurrentDeviceKey();
        const isVert = window.getComputedStyle(body).flexDirection === 'column';
        const currentSize = isVert ? tree.offsetHeight : tree.offsetWidth;
        if (tree.offsetParent !== null && currentSize > 50 && myCloudState.settings[devKey]) {
            myCloudState.settings[devKey].sidebarSize = Math.round(currentSize);
            await myCloudSaveSettings(); // Fire AJAX immediately; the setTimeout below gives it time to send
        }
    }

    sessionStorage.removeItem('myCloud_MasterKey');
    sessionStorage.removeItem('myCloud_WrappedKeys');

    const overlay = document.getElementById('myCloudModalOverlay'); if (overlay) overlay.style.display = 'none';
    const container = document.getElementById('myCloudContainer');
    if (container) { container.classList.remove('ce-anim-open'); container.classList.add('ce-anim-close'); }
    setTimeout(() => { window.location.href = '?logout=true'; }, 300);
};

// ============================================================================
// SECTION 6: API & NETWORKING
// ============================================================================

// Validates network response.
// Handles auth errors and redirects.
function myCloudCheckResponse(r) {
    if (r.status === 401 || r.status === 403 || r.redirected) { 
        window.location.reload(); 
        return new Promise(() => {}); 
    }
    const cType = r.headers.get("content-type");
    if (cType && cType.includes("text/html")) { 
        window.location.reload(); 
        return new Promise(() => {}); 
    }
    
    // Safely parse JSON to prevent SyntaxErrors from halting the JS thread on 500/502 errors
    return r.text().then(text => {
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error("MyCloud API Error: Invalid JSON response.", text);
            return { status: 'ERR', msg: 'Server returned an invalid response. Check server logs.' };
        }
    });
}

// Generic API caller.
// Manages loading state and error handling.
function myCloudAPI(action, payload, onSuccess) {
    const execute = () => {
        const fd = new URLSearchParams();
        fd.append('myCloud_action', action); 
        fd.append('myCloud_key', myCloudState.key); 
        fd.append('myCloud_token', window.myCloudCsrfToken);
        for (const key in payload) { fd.append(key, payload[key]); }

        myCloudShowLoading();
        fetch('', { method: 'POST', body: fd }).then(myCloudCheckResponse).then(resp => {
            myCloudHideLoading();
            if (resp.status === 'OK') {
                if (onSuccess) onSuccess(resp);
                myCloudFetchDirectory(myCloudState.currentDir);
            } else if (resp.code === 'CSRF_FAILED' || (resp.msg && resp.msg.includes('CSRF'))) {
                myCloudRefreshCsrfToken((success) => { 
                    if (success) execute(); 
                    else myCloudShowAlert('Error', 'Session expired. Please refresh the page.'); 
                });
            } else if (resp.code === 'AUTH_REQUIRED' && typeof myCloudPromptAdminAuth === 'function') {
                myCloudPromptAdminAuth(() => execute());
            } else if (resp.status === 'CONFLICT') {
                myCloudShowConflictModal(resp.file, (resolution) => {
                    if (resolution) { myCloudAPI(action, { ...payload, resolution: resolution }, onSuccess); }
                });
            } else {
                myCloudShowAlert(myCloud_LANG.error_lbl || 'Error', resp.msg || myCloud_LANG.error_lbl);
                if (action === 'rename') myCloudFetchDirectory(myCloudState.currentDir);
            }
        }).catch(err => {
            myCloudHideLoading(); console.error(err); myCloudShowAlert(myCloud_LANG.error_lbl, myCloud_LANG.request_failed);
        });
    };
    execute();
}


// ============================================================
// COMMAND PALETTE (Ctrl+P / Cmd+P)
// ============================================================
function myCloudShowCommandPalette() {
    if (document.getElementById('myCloudPaletteOverlay')) return;

    const overlay = document.createElement('div');
    overlay.id = 'myCloudPaletteOverlay';
    overlay.className = 'myCloud-palette-overlay';

    const palette = document.createElement('div');
    palette.className = 'myCloud-palette';

    const inputWrap = document.createElement('div');
    inputWrap.className = 'myCloud-palette-input-wrap';
    inputWrap.innerHTML = '<span class="myCloudIcon" style="opacity:0.5">' + myCloudSvg.search + '</span>' +
        '<input type="text" id="myCloudPaletteInput" class="myCloud-palette-input" placeholder="Search files or type > for commands..." autocomplete="off">';
    
    const list = document.createElement('ul');
    list.id = 'myCloudPaletteList';
    list.className = 'myCloud-palette-list';

    palette.appendChild(inputWrap);
    palette.appendChild(list);
    overlay.appendChild(palette);
    document.body.appendChild(overlay);

    const input = document.getElementById('myCloudPaletteInput');
    let selectedIndex = 0;
    let currentItems = [];
    let searchTimeout;

    // Check if the search index is available
    let hasIndex = false;
    fetch('', { method: 'POST', body: new URLSearchParams({ myCloud_action: 'check_index', myCloud_key: myCloudState.key, myCloud_token: typeof myCloudCsrfToken !== 'undefined' ? myCloudCsrfToken : '' }) })
        .then(r => r.json())
        .then(res => { if (res.status === 'OK') hasIndex = res.has_index; })
        .catch(() => {});

    const st = myCloudState;
    const L = typeof myCloud_LANG !== 'undefined' ? myCloud_LANG : {};
    const S = typeof myCloudSvg !== 'undefined' ? myCloudSvg : {};

    // --- CONTEXT CALCULATION ---
    const selCount = st.selectedFiles ? st.selectedFiles.length : 0;
    const isMulti = selCount > 1;
    const isRecycleBin = st.currentDir === '/.recycle_bin';
    const isInsideZip = typeof myCloudIsInsideZip === 'function' ? myCloudIsInsideZip(st.currentDir) : false;
    
    let ext = '';
    let isDir = false;
    if (selCount === 1) {
        const item = st.allItems.find(i => i.name === st.selectedFiles[0]);
        if (item) {
            isDir = item.size === 'DIR';
            ext = item.name.split('.').pop().toLowerCase();
        }
    }
    const isZipFile = !isDir && ext === 'zip';
    
    const previewExts = (typeof myCloudConfig !== 'undefined' && myCloudConfig.preview) ? myCloudConfig.preview : ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'txt', 'log', 'docx', 'xlsx', 'mp4', 'webm', 'ogg', 'mov', 'mkv', 'mp3', 'wav', 'm4a', 'flac'];
    const isPreviewable = !isDir && previewExts.includes(ext);
    
    const isEditable = !isDir && typeof window.myCloudIsFileEditable === 'function' && window.myCloudIsFileEditable(st.selectedFiles[0], isInsideZip);
    const officeExts = ['docx', 'xlsx', 'pptx', 'doc', 'xls', 'ppt', 'odt', 'ods', 'odp', 'rtf', 'csv'];
    const isPrintable = !isDir && typeof myCloudHasOnlyOffice !== 'undefined' && myCloudHasOnlyOffice && (officeExts.includes(ext) || ext === 'pdf') && window.myCloudActionAllowed('print');
    const allStackable = isMulti && st.selectedFiles.every(f => {
        const x = f.toLowerCase().split('.').pop();
        return x === 'pdf' || officeExts.includes(x);
    });

    // --- COMMAND DEFINITIONS WITH STRICT CONTEXTUAL SHOW LOGIC & SYNONYMS ---
    // --- COMMAND DEFINITIONS WITH STRICT CONTEXTUAL SHOW LOGIC & SYNONYMS ---
    const rawCommands = [
        { name: L.upload || 'Upload File', action: () => myCloudTriggerUpload(), icon: S.upload, prefix: '>', req: 'upload', show: !isInsideZip && !isRecycleBin, kw: 'upload,send,put,hochladen,subir,uploader,envoyer' },
        { name: L.new_folder || 'New Folder', action: () => myCloudAction_NewFolder(), icon: S.newfolder, prefix: '>', req: 'newfolder', show: !isInsideZip && !isRecycleBin, kw: 'new,folder,mkdir,create,make,directory,ordner,neuer,carpeta,nueva,nouveau,dossier' },
        { name: L.new_file || 'New File', action: () => myCloudAction_NewFile(), icon: S.newfile, prefix: '>', req: 'newfile', show: !isInsideZip && !isRecycleBin, kw: 'new,file,touch,create,make,document,datei,neue,archivo,nuevo,fichier' },
        { name: L.refresh || 'Refresh View', action: () => myCloudFetchDirectory(st.currentDir), icon: S.refresh, prefix: '>', req: 'refresh', show: true, kw: 'refresh,reload,f5,update,aktualisieren,neu laden,recargar,actualizar,rafraichir,actualiser' },
        { name: L.select_all || 'Select All', action: () => myCloudAction_SelectAll(), icon: S.select_all, prefix: '>', req: 'select_all', show: st.allItems && st.allItems.length > 0, kw: 'select,all,mark,everything,alles,auswählen,markieren,seleccionar,todo,tout,sélectionner' },
        { name: L.clear_selection || 'Clear Selection', action: () => myCloudAction_ClearSelection(), icon: S.clear_selection, prefix: '>', req: 'clear_selection', show: selCount > 0, kw: 'clear,deselect,unmark,none,aufheben,abwählen,desmarcar,effacer' },
        { name: L.invert_selection || 'Invert Selection', action: () => myCloudAction_InvertSelection(), icon: S.invert_selection, prefix: '>', req: 'invert_selection', show: st.allItems && st.allItems.length > 0, kw: 'invert,reverse,toggle,umkehren,invertir,inverser' },
        { name: 'Go Up a Directory', action: () => myCloudGoUp(), icon: S.move, prefix: '>', req: null, show: st.currentDir !== '/', kw: 'up,back,return,parent,cd ..,hoch,zurück,arriba,volver,haut,retour' },
        
        // Item-specific actions
        { name: L.copy || 'Copy', action: () => myCloudAction_CopyMove(false), icon: S.copy, prefix: '>', req: 'copy', show: selCount > 0 && (!isRecycleBin || isInsideZip), kw: 'copy,cp,kopieren,copiar,copier' },
        { name: L.move || 'Move', action: () => myCloudAction_CopyMove(true), icon: S.move, prefix: '>', req: 'move', show: selCount > 0 && !isInsideZip && !isRecycleBin, kw: 'move,mv,cut,verschieben,ausschneiden,mover,cortar,déplacer,couper' },
        { name: L.duplicate || 'Duplicate', action: () => myCloudAction_Duplicate(), icon: S.duplicate, prefix: '>', req: 'duplicate', show: selCount > 0 && !isInsideZip && !isRecycleBin, kw: 'duplicate,clone,duplizieren,duplicar,dupliquer' },
        { name: L.rename || 'Rename', action: () => myCloudAction_Rename(), icon: S.rename, prefix: '>', req: 'rename', show: selCount === 1 && !isInsideZip && !isRecycleBin, kw: 'rename,edit,title,umbenennen,renombrar,renommer' },
        { name: L.delete || 'Delete', action: () => myCloudAction_Delete(), icon: S.delete, prefix: '>', req: 'delete', show: selCount > 0 && !isInsideZip, kw: 'delete,rm,remove,trash,kill,löschen,entfernen,borrar,eliminar,supprimer,effacer' },
        { name: L.download || 'Download', action: () => myCloudAction_DownloadBatch(), icon: S.download, prefix: '>', req: 'download', show: selCount > 0 && !isRecycleBin, kw: 'download,get,pull,save,herunterladen,descargar,télécharger' },
        { name: L.preview || 'Preview', action: () => { if(selCount===1) myCloudDownloadFile(st.selectedFiles[0], st.selectedFiles[0].split('/').pop(), true); }, icon: S.preview, prefix: '>', req: 'preview', show: selCount === 1 && isPreviewable && !isRecycleBin, kw: 'preview,view,open,show,vorschau,ansehen,vista previa,ver,aperçu,voir' },
        { name: L.edit || 'Edit', action: () => { if(selCount===1) window.myCloudAction_EditFile(st.selectedFiles[0]); }, icon: S.edit_file || S.edit || '', prefix: '>', req: 'edit_file', show: selCount === 1 && isEditable && !isRecycleBin, kw: 'edit,modify,change,bearbeiten,editar,modifier,éditer' },
        
        // PDF Tools
        { name: L.pdf_unstack || 'Unstack PDF', action: () => window.myCloudAction_PdfUnstack(st.selectedFiles[0]), icon: '<svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V5h14v14zM7 10h2v7H7zm4-3h2v10h-2zm4 6h2v4h-2z" fill="currentColor"/></svg>', prefix: '>', req: 'pdf_unstack', show: selCount === 1 && ext === 'pdf' && !isRecycleBin && !isInsideZip, kw: 'pdf,unstack,split,pages,teilen,seiten,separar,páginas,unstacker,diviser' },
        { name: L.pdf_stack || 'Stack/Merge PDFs', action: () => window.myCloudAction_PdfStackMenu(), icon: '<svg viewBox="0 0 24 24"><path d="M4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm16-4H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H8V4h12v12z" fill="currentColor"/></svg>', prefix: '>', req: 'pdf_stack_menu', show: allStackable && !isRecycleBin && !isInsideZip, kw: 'pdf,stack,merge,combine,join,zusammenfügen,verbinden,combinar,unir,fusionner,combiner' },
        { name: L.pdf_tools || 'PDF Toolkit', action: () => window.myCloudShowPdfToolkit(st.selectedFiles[0]), icon: '<svg viewBox="0 0 24 24"><path d="M22.7 19l-9.1-9.1c.9-2.3.4-5-1.5-6.9-2-2-5-2.4-7.4-1.3L9 6 6 9 1.6 4.7C.4 7.1.9 10.1 2.9 12.1c1.9 1.9 4.6 2.4 6.9 1.5l9.1 9.1c.4.4 1 .4 1.4 0l2.3-2.3c.5-.4.5-1.1.1-1.4z" fill="currentColor"/></svg>', prefix: '>', req: 'pdf_toolkit', show: selCount === 1 && ext === 'pdf' && !isRecycleBin && !isInsideZip, kw: 'pdf,toolkit,tools,repair,rotate,shrink,ocr,text,images,werkzeuge,drehen,verkleinern,reparieren,herramientas,rotar,reducir,reparar,outils,pivoter,réduire,réparer' },
        { name: L.pdf_combine_images || 'Combine Images to PDF', action: () => window.myCloudAction_PdfCombineImages(), icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><circle cx="10" cy="13" r="2"/><polyline points="6 17 11 12 18 19"/></svg>', prefix: '>', req: 'pdf_combine_images', show: isMulti && st.selectedFiles.every(f => ['jpg','jpeg','png'].includes(f.toLowerCase().split('.').pop())) && !isRecycleBin && !isInsideZip, kw: 'pdf,convert,images to pdf,jpeg to pdf,png to pdf,umwandeln,bilder zu pdf,convertir,imágenes a pdf,images en pdf' },

        // Advanced Contexts
        { name: L.zip_copy || 'Zip (Compress)', action: () => myCloudAction_Zip('copy'), icon: S.zip, prefix: '>', req: 'zip_copy', show: selCount === 1 && isDir && !isInsideZip && !isRecycleBin, kw: 'zip,compress,archive,tar,rar,packen,komprimieren,comprimir,compresser' },
        { name: L.unzip || 'Unzip (Extract)', action: () => myCloudAction_Unzip(), icon: S.unzip, prefix: '>', req: 'unzip', show: selCount === 1 && isZipFile && !isInsideZip && !isRecycleBin, kw: 'unzip,extract,uncompress,entpacken,extrahieren,extraer,extraire' },
        { name: L.print || 'Print', action: () => { if (st.selectedFiles.length > 0) window.myCloudAction_Print(st.selectedFiles); }, icon: S.print, prefix: '>', req: 'print', show: (isPrintable || allStackable) && !isRecycleBin, kw: 'print,pdf,paper,drucken,imprimir,imprimer' },
        { name: L.empty_bin || 'Empty Recycle Bin', action: () => { if (typeof myCloudHandleToolbarClick === 'function') myCloudHandleToolbarClick('empty_bin'); }, icon: S.trash, prefix: '>', req: 'empty_bin', show: isRecycleBin, kw: 'empty,trash,clear,purge,permanently,leeren,papierkorb,vaciar,vider,corbeille' },
        
        // View Modes
        { name: L.view_symbol || 'Toggle Icon/List View', action: () => { if (typeof myCloudHandleToolbarClick === 'function') myCloudHandleToolbarClick('view_toggle'); }, icon: S.view, prefix: '>', req: 'view_toggle', show: true, kw: 'view,list,grid,gallery,ansicht,liste,vista,lista,vue' },
        { name: L.view_commander || 'Toggle Commander Mode', action: () => { if (typeof myCloudToggleCommander === 'function') myCloudToggleCommander(); }, icon: S.commander_toggle || '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M4 18h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-7V4H4c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2zm9-10h7v8h-7V8zm-9 0h7v8H4V8z"/></svg>', prefix: '>', req: 'commander_toggle', show: true, kw: 'commander,split,dual,panel,norton,midnight,zweispaltig,panneau' },
        { name: L.view_office || 'Toggle Office View', action: () => { if (typeof myCloudToggleOffice === 'function') myCloudToggleOffice(); }, icon: S.office_toggle || '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-8 14H5V7h6v10zm8 0h-6V7h6v10z"/></svg>', prefix: '>', req: 'office_toggle', show: true, kw: 'office,word,excel,document,büro,bureau' }
    ];

    if (typeof myCloudUserRole !== 'undefined' && myCloudUserRole === 'admin_mode') {
        rawCommands.push({ name: L.terminal || 'Open SSH Terminal', action: () => myCloudToggleTerminal(), icon: S.terminal, prefix: '>', req: 'terminal', show: true, kw: 'terminal,ssh,console,cli,bash,shell,konsole,consola' });
    }

    // Dynamically filter contextually allowed actions using the matrix and our dynamic 'show' boolean
    const getCommands = () => {
        return rawCommands.filter(c => {
            if (!c.show) return false;
            if (!c.req) return true;
            if (typeof getActionStatus === 'function') {
                const status = getActionStatus(c.req);
                return !status.hidden && !status.disabled;
            }
            return window.myCloudActionAllowed(c.req);
        });
    };

    const renderItems = () => {
        list.innerHTML = '';
        selectedIndex = 0;
        if (currentItems.length === 0) {
            list.innerHTML = '<li style="padding:15px; text-align:center; color:var(--text-secondary);">No results found</li>';
            return;
        }

        currentItems.forEach((item, idx) => {
            const li = document.createElement('li');
            li.className = 'myCloud-palette-item' + (idx === 0 ? ' selected' : '');
            li.innerHTML = '<span class="myCloudIcon">' + item.icon + '</span><span>' + item.name + '</span>' + 
                           (item.prefix ? '<span class="myCloud-palette-kbd">Command</span>' : '');
            li.onclick = () => { closePalette(); item.action(); };
            list.appendChild(li);
        });
    };

    const renderList = (query) => {
        clearTimeout(searchTimeout);
        const isCommand = query.startsWith('>');
        const cleanQuery = isCommand ? query.substring(1).toLowerCase().trim() : query.toLowerCase().trim();

        if (isCommand || query === '') {
            const availableCommands = getCommands();
            currentItems = availableCommands.filter(c => 
                c.name.toLowerCase().includes(cleanQuery) || (c.kw && c.kw.includes(cleanQuery))
            );
            renderItems();
        } else {
			// E2E SECURITY: Block file search queries when inside a Vault
			if (typeof myCloudCrypto !== 'undefined' && myCloudCrypto.isDirEncrypted(st.currentDir)) {
				currentItems = [];
				list.innerHTML = '<li style="padding:15px; text-align:center; color:var(--text-secondary);">Search is disabled inside encrypted Vaults.</li>';
				return;
			}
            if (hasIndex) {
                list.innerHTML = '<li style="padding:15px; text-align:center; color:var(--text-secondary);">' + (L.searching || 'Searching...') + '</li>';
                searchTimeout = setTimeout(() => {
                    const params = new URLSearchParams({
                        myCloud_action: 'search',
                        myCloud_key: st.key,
                        myCloud_token: typeof myCloudCsrfToken !== 'undefined' ? myCloudCsrfToken : '',
                        query: cleanQuery,
                        dir: st.currentDir,
                        content_search: '0',
                        date_range: 'all',
                        size_range: 'all'
                    });
                    fetch('', { method: 'POST', body: params })
                        .then(r => r.json())
                        .then(res => {
                            currentItems = [];
                            if (res.status === 'OK' && res.data) {
                                res.data.forEach(f => {
                                    if (f.name !== '/.recycle_bin') {
                                        currentItems.push({
                                            name: f.name,
                                            icon: f.size === 'DIR' ? myCloudIconFolder : (myCloudTypeIcons[f.name.split('.').pop().toLowerCase()] || myCloudTypeIcons._default),
                                            action: () => {
                                                const parentDir = f.name.substring(0, f.name.lastIndexOf('/')) || '/';
                                                if (f.size === 'DIR') {
                                                    st.currentDir = f.name;
                                                    myCloudExpandToPath(f.name);
                                                    myCloudFetchDirectory(f.name).then(() => { if (typeof myCloudRenderUI === 'function') myCloudRenderUI(); });
                                                } else {
                                                    st.currentDir = parentDir;
                                                    myCloudExpandToPath(f.name);
                                                    myCloudFetchDirectory(parentDir).then(() => { if (typeof myCloudSeekAndSelect === 'function') myCloudSeekAndSelect(f.name); });
                                                }
                                            }
                                        });
                                    }
                                });
                            }
                            currentItems = currentItems.slice(0, 50);
                            renderItems();
                        }).catch(() => {
                            list.innerHTML = '<li style="padding:15px; text-align:center; color:var(--danger);">Error</li>';
                        });
                }, 300); // debounce API requests
            } else {
                // Fallback: Search loaded files globally
                currentItems = [];
                const files = st.allItems || [];
                files.forEach(f => {
                    const n = f.name.toLowerCase();
                    if (n !== '/.recycle_bin' && n.includes(cleanQuery)) {
                        currentItems.push({
                            name: f.name,
                            icon: f.size === 'DIR' ? myCloudIconFolder : (myCloudTypeIcons[f.name.split('.').pop().toLowerCase()] || myCloudTypeIcons._default),
                            action: () => myCloudHandleEnter({ name: f.name, size: f.size })
                        });
                    }
                });
                currentItems = currentItems.slice(0, 50);
                renderItems();
            }
        }
    };

    const closePalette = () => {
        document.removeEventListener('keydown', keyHandler);
        overlay.remove();
    };

    overlay.onclick = (e) => { if (e.target === overlay) closePalette(); };
    input.addEventListener('input', () => renderList(input.value));

    const keyHandler = (e) => {
        if (e.key === 'Escape') closePalette();
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            const nodes = list.querySelectorAll('li');
            if (nodes.length === 0) return;
            nodes[selectedIndex].classList.remove('selected');
            if (e.key === 'ArrowDown') selectedIndex = (selectedIndex + 1) % currentItems.length;
            else selectedIndex = (selectedIndex - 1 + currentItems.length) % currentItems.length;
            nodes[selectedIndex].classList.add('selected');
            nodes[selectedIndex].scrollIntoView({ block: 'nearest' });
        }
        if (e.key === 'Enter') {
            e.preventDefault();
            if (currentItems[selectedIndex]) {
                closePalette();
                currentItems[selectedIndex].action();
            }
        }
    };
    document.addEventListener('keydown', keyHandler);

    renderList('');
    setTimeout(() => input.focus(), 50);
}


// ============================================================
// SSH TERMINAL EMULATOR (Xterm.js)
// ============================================================
let ceTerminalInstance = null;

async function myCloudToggleTerminal() {
    if (myCloudUserRole !== 'admin_mode') return;
	
    // [NEW] Lazy Load Xterm.js
    if (typeof Terminal === 'undefined') {
        myCloudShowLoading();
        try {
            if (!document.querySelector('link[href="/script/xterm/xterm.css"]')) {
                const link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = '/script/xterm/xterm.css';
                document.head.appendChild(link);
            }
            const loadJS = (src) => new Promise((res, rej) => { if (document.querySelector(`script[src="${src}"]`)) return res(); const s = document.createElement('script'); s.src = src; s.onload = res; s.onerror = rej; document.head.appendChild(s); });
            await loadJS('/script/xterm/xterm.js');
            await loadJS('/script/xterm/xterm-addon-fit.js');
        } catch (e) {
            myCloudHideLoading();
            alert("Failed to load terminal scripts.");
            return;
        }
        myCloudHideLoading();
    }

    let wrap = document.getElementById('myCloudTerminalWrap');
    const mini = document.getElementById('myCloudTerminalMini');

    if (!wrap) {
        wrap = document.createElement('div');
        wrap.id = 'myCloudTerminalWrap';
        wrap.className = 'myCloud-terminal-wrap';
		
        
        wrap.innerHTML = 
            '<div class="myCloud-terminal-header" id="ceTermHeader">' +
                '<span>SSH Terminal - ' + (myCloudState.key || '') + '</span>' +
                '<div>' +
                    '<button onclick="document.getElementById(\'myCloudTerminalWrap\').classList.add(\'minimized\'); document.getElementById(\'myCloudTerminalMini\').style.display=\'flex\';" style="background:transparent;border:none;color:#fff;cursor:pointer;font-size:16px;">_</button>' +
                    '<button onclick="myCloudToggleTerminal();" style="background:transparent;border:none;color:#ff5252;cursor:pointer;font-size:16px;margin-left:10px;">✕</button>' +
                '</div>' +
            '</div>' +
            '<div class="myCloud-terminal-body" id="ceTermBody"></div>';
        
        document.body.appendChild(wrap);

        const miniIcon = document.createElement('div');
        miniIcon.id = 'myCloudTerminalMini';
        miniIcon.className = 'myCloud-terminal-minimized-icon';
        miniIcon.innerHTML = '<svg viewBox="0 0 24 24" style="width:24px;height:24px;fill:currentColor;"><path d="M20 4H4c-1.11 0-2 .9-2 2v12c0 1.1.89 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 14H4V8h16v10zm-2-1h-6v-2h6v2zM7.5 17l-1.41-1.41L8.67 13l-2.58-2.59L7.5 9l4 4-4 4z"/></svg>';
        miniIcon.title = 'Restore Terminal';
        miniIcon.onclick = () => {
            wrap.classList.remove('minimized');
            miniIcon.style.display = 'none';
            setTimeout(() => { if(ceTerminalInstance) ceTerminalInstance.focus(); }, 100);
        };
        document.body.appendChild(miniIcon);

        const header = document.getElementById('ceTermHeader');
        let isDragging = false, startX, startY, initX, initY;
        header.onmousedown = (e) => {
            if (e.target.tagName === 'BUTTON') return;
            isDragging = true; startX = e.clientX; startY = e.clientY;
            const rect = wrap.getBoundingClientRect();
            initX = rect.left; initY = rect.top;
            wrap.style.right = 'auto'; wrap.style.bottom = 'auto';
            wrap.style.left = initX + 'px'; wrap.style.top = initY + 'px';
            document.body.style.userSelect = 'none';
        };
        window.addEventListener('mousemove', (e) => {
            if (!isDragging) return;
            wrap.style.left = (initX + e.clientX - startX) + 'px';
            wrap.style.top = (initY + e.clientY - startY) + 'px';
        });
        window.addEventListener('mouseup', () => { isDragging = false; document.body.style.userSelect = ''; });

        ceTerminalInstance = new Terminal({
            cursorBlink: true,
            theme: { background: '#000000' },
            fontFamily: '"Cascadia Code", Menlo, monospace',
            fontSize: 13
        });
        const fitAddon = new FitAddon.FitAddon();
        ceTerminalInstance.loadAddon(fitAddon);
        ceTerminalInstance.open(document.getElementById('ceTermBody'));
		
         // THE FIX: Target the body directly with a debounce to let the DOM settle
         let resizeTimeout;
         new ResizeObserver(() => {
             clearTimeout(resizeTimeout);
             resizeTimeout = setTimeout(() => {
                 if (ceTerminalInstance && ceTerminalInstance.element) {
                     try { fitAddon.fit(); } catch(e) {}
                 }
             }, 20); // 20ms debounce prevents math errors while dragging
         }).observe(document.getElementById('ceTermBody'));
        
        ceTerminalInstance.onResize(size => {
            const fd = new URLSearchParams();
            fd.append('myCloud_action', 'ssh_resize');
            fd.append('myCloud_key', myCloudState.key);
            if (typeof myCloudCsrfToken !== 'undefined') fd.append('myCloud_token', myCloudCsrfToken);
            fd.append('cols', size.cols);
            fd.append('rows', size.rows);
            // Removed keepalive to prevent silent browser network drops
            fetch('', { method: 'POST', body: fd }).catch(()=>{});
        });

        const attemptFit = setInterval(() => {
            const bodyEl = document.getElementById('ceTermBody');
            if (bodyEl && bodyEl.clientWidth > 0 && ceTerminalInstance.element) { 
                clearInterval(attemptFit);
                try { fitAddon.fit(); } catch(e){} 
                startSshStream(ceTerminalInstance.cols, ceTerminalInstance.rows);
            }
        }, 50);

        window.addEventListener('resize', () => { try { fitAddon.fit(); } catch(e){} });

        // --- FAST TYPING QUEUE ---
        let ceSshInputBuffer = "";
        let ceSshInputTimer = null;

        ceTerminalInstance.onData(data => {
            ceSshInputBuffer += data;
            if (ceSshInputTimer) return;
            
            ceSshInputTimer = setTimeout(() => {
                const chunk = ceSshInputBuffer;
                ceSshInputBuffer = "";
                ceSshInputTimer = null;

                if (!chunk) return;

                const fd = new URLSearchParams();
                fd.append('myCloud_action', 'ssh_write');
                fd.append('myCloud_key', myCloudState.key);
                if (typeof myCloudCsrfToken !== 'undefined') fd.append('myCloud_token', myCloudCsrfToken);
                
                const uint8 = new TextEncoder().encode(chunk);
                let binary = '';
                for (let i = 0; i < uint8.length; i++) binary += String.fromCharCode(uint8[i]);
                fd.append('data', btoa(binary));

                // [CRITICAL FIX] Ensure requests are actually sent
                fetch('', { method: 'POST', body: fd }).catch(()=>{});
            }, 10);
        });

		// --- FETCH STREAM (Guaranteed Connection & OOM Prevention) ---
        function startSshStream(cols, rows) {
            const abortController = new AbortController();
            ceTerminalInstance.activeStream = { abort: () => abortController.abort() };

            const params = new URLSearchParams({
                myCloud_action: 'ssh_stream',
                myCloud_key: myCloudState.key,
                cols: cols || 120,
                rows: rows || 40
            });
            if (typeof myCloudCsrfToken !== 'undefined') params.append('myCloud_token', myCloudCsrfToken);

            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params,
            }).then(async response => {
                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let streamBuffer = '';
                
                while(true) {
                    const { done, value } = await reader.read();
                    if (done) {
                        ceTerminalInstance.write('\r\n\x1b[31m[SSH Session Ended]\x1b[0m\r\n');
                        break;
                    }
                    streamBuffer += decoder.decode(value, { stream: true });
                    
                    let boundary = streamBuffer.indexOf('\n\n');
                    while (boundary !== -1) {
                        let chunk = streamBuffer.substring(0, boundary);
                        streamBuffer = streamBuffer.substring(boundary + 2);
                        
                        if (chunk.startsWith('data: ')) {
                            try {
                                let payload = chunk.substring(6);
                                if (payload !== '":ping"') {
                                    let b64 = JSON.parse(payload);
                                    if (b64) {
                                        let raw = atob(b64);
                                        let u8 = new Uint8Array(raw.length);
                                        for (let i = 0; i < raw.length; i++) u8[i] = raw.charCodeAt(i);
                                        ceTerminalInstance.write(u8);
                                    }
                                }
                            } catch(e) {}
                        }
                        boundary = streamBuffer.indexOf('\n\n');
                    }
                }
            }).catch((e) => {
                if (e.name !== 'AbortError') ceTerminalInstance.write('\r\n\x1b[31m[Network Error]\x1b[0m\r\n');
            });

            setTimeout(() => { if(ceTerminalInstance) ceTerminalInstance.focus(); }, 100);
        }
		
    } else {
        if (wrap.classList.contains('minimized')) {
            wrap.classList.remove('minimized');
            if(mini) mini.style.display = 'none';
            setTimeout(() => { if(ceTerminalInstance) ceTerminalInstance.focus(); }, 100);
        } else {
            if (ceTerminalInstance && ceTerminalInstance.activeStream) {
                ceTerminalInstance.activeStream.abort();
            }
            wrap.remove();
            if(mini) mini.remove();
            ceTerminalInstance = null;
        }
    }
}



// E2E Cross-Boundary Recursive Transfer Orchestrator
window.myCloudE2ETransfer = async function(srcPath, destDir, action, sourceRoot, targetRoot, forcedDestName) {
    const st = myCloudState;
    
    // Ensure vaults are unlocked
    if (sourceRoot && !myCloudCrypto.isDirUnlocked(sourceRoot)) throw new Error("Source vault is locked.");
    if (targetRoot && !myCloudCrypto.isDirUnlocked(targetRoot)) throw new Error("Target vault is locked.");

    const item = st.allItems.find(i => i.name === srcPath);
    const isDir = item ? item.size === 'DIR' : false;
    const filename = srcPath.split('/').pop();
    const plainName = forcedDestName || (st.pathNames && st.pathNames[srcPath] ? st.pathNames[srcPath] : filename.replace(/\.enc$/, ''));

    const textEl = document.getElementById('myCloudProgressText');
    if (textEl) textEl.textContent = (action === 'move' ? myCloud_LANG.moving : myCloud_LANG.copying) + ' E2E: ' + plainName;

    if (isDir) {
        let targetName = plainName;
        if (targetRoot) targetName = await myCloudCrypto.encryptName(destDir, plainName);
        
        const mkdirFd = new URLSearchParams({ myCloud_action: 'mkdir', myCloud_key: st.key, myCloud_token: myCloudCsrfToken, parent: destDir, name: targetName });
        const mkdirRes = await fetch('', { method: 'POST', body: mkdirFd }).then(r => r.json());
        if (mkdirRes.status !== 'OK' && mkdirRes.msg !== 'Exists') throw new Error("Failed to create directory: " + plainName);
        
        const newDestDir = (destDir === '/' ? '' : destDir) + '/' + targetName;

        const listFd = new URLSearchParams({ myCloud_action: 'list', myCloud_key: st.key, myCloud_token: myCloudCsrfToken, path: srcPath, depth: 1 });
        const listRes = await fetch('', { method: 'POST', body: listFd }).then(r => r.json());
        
        if (listRes.status === 'OK') {
            for (let child of listRes.data) {
                if (child.name === '/.recycle_bin') continue;
                // Inject into allItems temporarily so the recursive call knows if it's a DIR
                if (!st.allItems.some(i => i.name === child.name)) st.allItems.push(child);
                await window.myCloudE2ETransfer(child.name, newDestDir, action, sourceRoot, targetRoot, null);
            }
        }
        
        if (action === 'move') {
            const delFd = new URLSearchParams({ myCloud_action: 'delete', myCloud_key: st.key, myCloud_token: myCloudCsrfToken, src: srcPath, permanent: 'true' });
            await fetch('', { method: 'POST', body: delFd });
        }
    } else {
        const dlFd = new URLSearchParams({ myCloud_action: 'get_download_token', myCloud_key: st.key, myCloud_token: myCloudCsrfToken, path: srcPath, filename: filename, preview: '0' });
        const tokenRes = await fetch('', { method: 'POST', body: dlFd }).then(r => r.json());
        if (tokenRes.status !== 'OK') throw new Error("Could not get download token for " + plainName);
        
        const r2 = await fetch('?myCloud_token=' + tokenRes.token);
        if (!r2.ok) throw new Error("Failed to download " + plainName);
        let blob = await r2.blob();
        
        if (sourceRoot) blob = await myCloudCrypto.decryptFile(sourceRoot, blob);
        
        let uploadBlob = blob;
        let uploadName = plainName;
        if (targetRoot) {
            const plainFileObj = new File([blob], plainName, { type: blob.type });
            uploadBlob = await myCloudCrypto.encryptFile(targetRoot, plainFileObj);
            uploadName = await myCloudCrypto.encryptName(destDir, plainName);
        }
        
        const upFd = new FormData();
        upFd.append('myCloud_action', 'upload');
        upFd.append('dir', destDir);
        upFd.append('myCloud_key', st.key);
        upFd.append('myCloud_token', myCloudCsrfToken);
        upFd.append('file', uploadBlob, uploadName);
        upFd.append('resolution', 'overwrite'); 
        
        const upRes = await fetch('', { method: 'POST', body: upFd }).then(r => r.json());
        if (upRes.status !== 'OK') throw new Error("Failed to upload " + plainName);
        
        if (action === 'move') {
            const delFd = new URLSearchParams({ myCloud_action: 'delete', myCloud_key: st.key, myCloud_token: myCloudCsrfToken, src: srcPath, permanent: 'true' });
            await fetch('', { method: 'POST', body: delFd });
        }
    }
};

// Sequential batch processor for file operations.
async function myCloudBatchProcess(action, paths, targetDir, preserveRights = true) {
    if (!paths || paths.length === 0) return;
    myCloudShowLoading();

     // [NEW] E2E Pre-load Target Directory for Accurate Collision Checking
     const isEncryptedTarget = typeof myCloudCrypto !== 'undefined' && myCloudCrypto.isDirEncrypted(targetDir);
     const targetRoot = isEncryptedTarget ? myCloudCrypto.getCryptoRoot(targetDir) : null;

     if (isEncryptedTarget && !myCloudState.loadedDirs.includes(targetDir) && action !== 'delete') {
         await myCloudFetchDirectory(targetDir, 1, true);
     }

    for (const itemPath of paths) {
        await new Promise((resolve) => {
             let srcItem = myCloudState.allItems.find(i => i.name === itemPath);
             let srcDisplayName = srcItem ? (srcItem.displayName || (myCloudState.pathNames && myCloudState.pathNames[itemPath]) || srcItem.name.split('/').pop().replace(/\.enc$/, '')) : itemPath.split('/').pop();
             let existingItem = null;
 
             if (isEncryptedTarget && action !== 'delete') {
                 const targetFolderItems = myCloudState.allItems.filter(i => {
                     const parent = i.name.substring(0, i.name.lastIndexOf('/') || 0) || '/';
                     return parent === targetDir;
                 });
                 existingItem = targetFolderItems.find(i => {
                     let dName = (myCloudState.pathNames && myCloudState.pathNames[i.name]) ? myCloudState.pathNames[i.name] : i.name.split('/').pop().replace(/\.enc$/, '');
                     return dName === srcDisplayName && i.name !== itemPath;
                 });
             }
 
             async function attempt(resolution = null) {
                 // Trigger Dialog
                 if (isEncryptedTarget && existingItem && !resolution) {
                     myCloudHideLoading();
                     myCloudShowConflictModal(srcDisplayName, (res) => {
                         if (res) { myCloudShowLoading(); attempt(res); } 
                         else { myCloudShowLoading(); resolve(); }
                     });
                     return;
                 }
 
                 // Enforce Overwrite
                 if (isEncryptedTarget && resolution === 'overwrite' && existingItem) {
                     const delFd = new URLSearchParams({ myCloud_action: 'delete', myCloud_key: myCloudState.key, myCloud_token: myCloudCsrfToken, src: existingItem.name, permanent: 'true' });
                     await fetch('', { method: 'POST', body: delFd });
                     existingItem = null; 
                 }
 
                 // Enforce Rename (Keep Both)
                 let customDestName = null;
                 if (isEncryptedTarget && resolution === 'keep_both') {
                     let base = srcDisplayName;
                     let ext = '';
                     const lastDot = srcDisplayName.lastIndexOf('.');
                     if (lastDot > 0 && (!srcItem || srcItem.size !== 'DIR')) {
                         base = srcDisplayName.substring(0, lastDot);
                         ext = srcDisplayName.substring(lastDot);
                     }
                     let counter = 1;
                     let newName = `${base} (${counter})${ext}`;
                     const targetFolderItems = myCloudState.allItems.filter(i => (i.name.substring(0, i.name.lastIndexOf('/') || 0) || '/') === targetDir);
                     while (targetFolderItems.some(i => {
                         let dName = (myCloudState.pathNames && myCloudState.pathNames[i.name]) ? myCloudState.pathNames[i.name] : i.name.split('/').pop().replace(/\.enc$/, '');
                         return dName === newName;
                     })) {
                         counter++;
                         newName = `${base} (${counter})${ext}`;
                     }
                     
                     customDestName = (srcItem && srcItem.name.endsWith('.enc')) ? await myCloudCrypto.encryptName(targetDir, newName) : newName;
                     srcDisplayName = newName; // Update display name for E2E processor
                 }

                 // [CRITICAL FIX] Check for Cryptographic Boundary Crossing
                 const sourceRoot = typeof myCloudCrypto !== 'undefined' ? myCloudCrypto.getCryptoRoot(itemPath) : null;
                 const isCrossBoundary = (sourceRoot !== targetRoot) && (action === 'move' || action === 'copy');

                 if (isCrossBoundary) {
                     try {
                         if (!document.getElementById('myCloudProgressPopup')) {
                             myCloudCreateProgressUI((action === 'move' ? 'Moving' : 'Copying') + ' E2E: ' + srcDisplayName);
                         }
                         await window.myCloudE2ETransfer(itemPath, targetDir, action, sourceRoot, targetRoot, srcDisplayName);
                         
                         if (action === 'move' || action === 'delete') {
                             const prefix = itemPath + '/';
                             myCloudState.allItems = myCloudState.allItems.filter(item => item.name !== itemPath && !item.name.startsWith(prefix));
                         }
                         resolve();
                     } catch (e) {
                         myCloudShowAlert('Transfer Error', e.message);
                         resolve();
                     }
                     return;
                 }

                 // Standard Server-Side Fast Operation
                 const fd = new URLSearchParams();
                 fd.append('myCloud_action', action); 
                 fd.append('myCloud_key', myCloudState.key); 
                 fd.append('myCloud_token', myCloudCsrfToken); 
                 fd.append('src', itemPath);
                 
                 if (action === 'move' || action === 'copy') fd.append('dest', targetDir);
                 fd.append('preserve_rights', preserveRights ? '1' : '0');
                 if (resolution && !isEncryptedTarget) fd.append('resolution', resolution);

                 fetch('', { method: 'POST', body: fd }).then(myCloudCheckResponse).then(async resp => {
                     if (resp.status === 'OK') {
                         // Update memory state
                         if (action === 'move' || action === 'delete') {
                             const prefix = itemPath + '/';
                             myCloudState.allItems = myCloudState.allItems.filter(item => item.name !== itemPath && !item.name.startsWith(prefix));
                         }
                          if (customDestName && action !== 'delete') {
                              const copiedPath = targetDir === '/' ? '/' + itemPath.split('/').pop() : targetDir + '/' + itemPath.split('/').pop();
                              const renFd = new URLSearchParams({ myCloud_action: 'rename', myCloud_key: myCloudState.key, myCloud_token: myCloudCsrfToken, src: copiedPath, newName: customDestName });
                              await fetch('', { method: 'POST', body: renFd });
                          }
                         resolve();
                     } else if (resp.code === 'AUTH_REQUIRED' && typeof myCloudPromptAdminAuth === 'function') {
                         myCloudHideLoading();
                         myCloudPromptAdminAuth(() => {
                             myCloudShowLoading();
                             attempt(resolution);
                         });
                     } else if (resp.status === 'CONFLICT') {
                         myCloudHideLoading();
                         myCloudShowConflictModal(resp.file || itemPath.split('/').pop(), (res) => {
                             if (res) { myCloudShowLoading(); attempt(res); } else { myCloudShowLoading(); resolve(); }
                         });
                     } else { resolve(); }
                 }).catch(() => resolve());
             }
             attempt();
        });
    }
    
    myCloudHideLoading();
    if (document.getElementById('myCloudProgressPopup')) myCloudCloseProgressUI();

    // 1. Surgical DOM Update (Visual Feedback for Move/Delete)
    if ((action === 'move' || action === 'delete') && targetDir !== myCloudState.currentDir) {
        myCloudSurgicalRemove(paths);
    }
    
    // 2. Sync server state
    if (!myCloudState.isCommanderMode) {
        await myCloudFetchDirectory(myCloudState.currentDir, 2, true);
        if (targetDir && targetDir !== myCloudState.currentDir) {
            await myCloudFetchDirectory(targetDir, 2, true);
        }
        if (action === 'copy') myCloudRenderUI(); 
    } else {
        await myCloudFetchDirectory(myCloudState.currentDir, 2, true);
        if (targetDir) await myCloudFetchDirectory(targetDir, 2, true);
    }
}


// ============================================================================
// SECTION 7: UTILITIES
// ============================================================================

// Closes active modal with animation.
// Cleans up overlays and timers.
function myCloudCloseModal() {
    if (typeof window.myCloudBeforeCloseCallback === 'function') {
		// Intercepted
        if (window.myCloudBeforeCloseCallback() === false) return; 
    }
    window.myCloudBeforeCloseCallback = null;
    const overlay = document.getElementById('myCloudModalOverlay');
    const modal = document.getElementById('myCloudModal');
    if (!overlay || overlay.style.display === 'none') return;

    overlay.classList.add('closing');
    if (modal) modal.classList.add('closing');
    if (overlay.closeTimer) clearTimeout(overlay.closeTimer);

    overlay.closeTimer = setTimeout(() => {
        overlay.style.display = 'none'; overlay.classList.remove('closing');
        if (modal) modal.classList.remove('closing');
        overlay.style.backgroundColor = ''; myCloudResetModal(); overlay.closeTimer = null;
    }, 680);
}

// Formats bytes to human readable string.
// Supports B, KB, MB, GB, TB.
function myCloudFormatBytes(bytes, decimals = 1) {
    if (bytes == 0) return '0 B';
    const k = 1024; const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}

// [NEW] View Inheritance Logic (Client Side)
function myCloudGetEffectiveViewMode(path) {
    // Normalize path
	if (!path || path === '') path = '/';
    if (!path.startsWith('/')) path = '/' + path;
    if (path.length > 1 && path.endsWith('/')) path = path.slice(0, -1);

    const settings = myCloudState.viewSettings || {};
    let walker = path;
    
    // Walk up the tree to find nearest setting
    while (true) {
        if (settings[walker]) {
			// Found explicit setting
            return settings[walker]; 
        }
        if (walker === '/') break;
        walker = walker.substring(0, walker.lastIndexOf('/')) || '/';
    }
	// Default
    return 'list'; 
}

// [NEW] Helper to retrieve custom tag names with language fallbacks
window.myCloudGetTagName = function(color, returnDefaultOnly = false) {
    if (!returnDefaultOnly && myCloudState.settings && myCloudState.settings.tagNames && myCloudState.settings.tagNames[color]) {
        return myCloudState.settings.tagNames[color];
    }
    const L = typeof myCloud_LANG !== 'undefined' ? myCloud_LANG : {};
    const defaults = {
        '#e81123': L.color_red || 'Red',
        '#0078d4': L.color_blue || 'Blue',
        '#107c10': L.color_green || 'Green',
        '#f0ad4e': L.color_orange || 'Orange',
		'#888888': L.color_gray || 'Gray',
        '#673ab7': L.color_purple || 'Purple',
        '#e91e63': L.color_pink || 'Pink',
        '#009688': L.color_teal || 'Teal',
        '#795548': L.color_brown || 'Brown',
        '#607d8b': L.color_bluegrey || 'Blue Grey'
    };
    return defaults[color] || 'Tag';
};


// ============================================================
// MODAL DRAG HANDLER
// ============================================================
(function initModalDragger() {
    let currentModal = null;
    let startX = 0, startY = 0;
    let startLeft = 0, startTop = 0;

    function onMove(e) {
        if (!currentModal) return;
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        
        const dx = clientX - startX;
        const dy = clientY - startY;
        
        currentModal.style.left = (startLeft + dx) + 'px';
        currentModal.style.top = (startTop + dy) + 'px';
    }

    function onEnd() {
        if (currentModal) {
            document.body.style.userSelect = '';
            currentModal = null;
        }
        document.removeEventListener('mousemove', onMove);
        document.removeEventListener('mouseup', onEnd);
        document.removeEventListener('touchmove', onMove);
        document.removeEventListener('touchend', onEnd);
    }

    function onStart(e) {
        const header = e.target.closest('.myCloudModalHeader, .myCloudVer-modal-header, .ce-fra-header');
        if (!header) return;

        // Ignore if clicking an interactive element inside the header
        if (e.target.closest('button, input, select, .myCloudClose, .myCloud-zoom-btn, .ce-help-close-btn, .ce-fra-back-btn')) return;

        const modal = header.closest('.myCloudModal, .myCloudVer-modal, .ce-fra-card');
        if (!modal) return;

        // Exclude fullscreen or almost fullscreen modals
        if (modal.classList.contains('preview') || 
            modal.classList.contains('search-modal') || 
            modal.classList.contains('ce-help-modal')) {
            return;
        }

        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;

        startX = clientX;
        startY = clientY;
        currentModal = modal;

        if (window.getComputedStyle(modal).position === 'static') {
            modal.style.position = 'relative';
        }

        startLeft = parseFloat(modal.style.left) || 0;
        startTop = parseFloat(modal.style.top) || 0;

        document.body.style.userSelect = 'none';

        if (e.type === 'touchstart') {
            document.addEventListener('touchmove', onMove, { passive: false });
            document.addEventListener('touchend', onEnd);
        } else {
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onEnd);
        }
    }

    document.addEventListener('mousedown', onStart);
    document.addEventListener('touchstart', onStart, { passive: true });
})();

// ============================================================
// FOCUS TRAPPING FOR MODALS
// ============================================================
document.addEventListener('keydown', function(e) {
    if (e.key === 'Tab') {
        const overlay = document.getElementById('myCloudModalOverlay');
        if (overlay && overlay.style.display !== 'none') {
            const modal = document.getElementById('myCloudModal');
            if (!modal) return;
            const focusable = modal.querySelectorAll('button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])');
            if (focusable.length) {
                const first = focusable[0];
                const last = focusable[focusable.length - 1];
                if (e.shiftKey && document.activeElement === first) {
                    e.preventDefault(); last.focus();
                } else if (!e.shiftKey && document.activeElement === last) {
                    e.preventDefault(); first.focus();
                }
            }
        }
    }
});

// ============================================================
// FLUSH PENDING FOLDER STATE SAVES ON TAB CLOSE / BACKGROUND
// ============================================================
['pagehide', 'visibilitychange'].forEach(evt => {
    window.addEventListener(evt, function() {
        if ((evt === 'visibilitychange' && document.visibilityState !== 'hidden') || !myCloudSaveTimer) return;
        clearTimeout(myCloudSaveTimer);
        myCloudSaveTimer = null;
        if (typeof myCloudSaveSettings === 'function') myCloudSaveSettings();
    });
});

// ============================================================
// AUTO-REFRESH ON APP RESUME (Magic Sync)
// ============================================================
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
        // Don't interrupt if the user has a modal open or is actively uploading
        if (document.getElementById('myCloudModalOverlay').style.display !== 'none') return;
        if (document.getElementById('myCloudProgressPopup')) return;
        
        if (myCloudState && myCloudState.currentDir && myCloudState.interface !== 'email') {
            myCloudFetchDirectory(myCloudState.currentDir, 2, true); // Silent refresh
        }
    }
});

// ============================================================
// DELAYED FONT LOADING (Wait for app to settle completely)
// ============================================================
window.addEventListener('load', function() {
    const loadFonts = function() {
        if (!document.getElementById('myCloudFontsLink')) {
            const link = document.createElement('link');
            link.id = 'myCloudFontsLink';
            link.rel = 'stylesheet';
            link.href = '/fonts/_fonts.css';
            document.head.appendChild(link);
        }
    };
    if (window.requestIdleCallback) window.requestIdleCallback(loadFonts, { timeout: 2000 });
    else setTimeout(loadFonts, 1500);
});

// ============================================================
// GLOBAL NATIVE CONTEXT MENU BLOCKER
// ============================================================
 document.addEventListener('contextmenu', function(e) {
    // Allow native right-click on inputs, textareas, Ace Editor, xterm, and the top cloud tabs
    // Allow native right-click on inputs, textareas, Ace Editor, xterm, and the top cloud switcher
    if (['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName) || e.target.isContentEditable || e.target.closest('.ace_editor') || e.target.closest('.xterm') || e.target.closest('.myCloudCloudSwitcher')) {
		return;    
		}
    // Block the browser's native context menu everywhere else (Except in Beta)
    if (typeof myCloudIsBeta !== 'undefined' && !myCloudIsBeta) {
        e.preventDefault();
    }
 });

// ============================================================
// PWA INSTALLATION PROMPT HANDLER
// ============================================================
// We check repeatedly for the stashed event to avoid race conditions.
let pwaCheckAttempts = 0;
const pwaCheckInterval = setInterval(() => {
    // 1. Stop checking if already running as an installed App
    if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true) {
        clearInterval(pwaCheckInterval);
        console.log("PWA: Already running in App mode. Install prompt aborted.");
        return;
    }

    // 2. Wait for the browser to fire the event
    if (!window.myCloudDeferredPrompt) {
        pwaCheckAttempts++;
        // Give up after 15 seconds
		if (pwaCheckAttempts > 15) { 
            clearInterval(pwaCheckInterval);
            console.warn("PWA: Browser did not fire 'beforeinstallprompt'. Reasons: Firefox/Safari used, already installed, no HTTPS, or invalid manifest.json.");
        }
        return;
    }
    clearInterval(pwaCheckInterval);

    // Check if user permanently dismissed the prompt
    if (localStorage.getItem('myCloudPwaDismissed') === 'permanent') return; 
    
    // Delay the UI prompt slightly so it doesn't interrupt startup workflows
    setTimeout(() => {
        const overlay = document.getElementById('myCloudModalOverlay');
        // Don't interrupt other modals
		if (overlay && overlay.style.display !== 'none') return; 
        
        const modal = document.getElementById('myCloudModal');
        if (typeof myCloudResetModal === 'function') myCloudResetModal();
        
        overlay.style.display = 'flex';
        modal.className = 'myCloudModal conflict'; 
        
        const title = (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.install_app) ? myCloud_LANG.install_app : 'Install App';
        const msg = (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.install_app_msg) ? myCloud_LANG.install_app_msg : 'Would you like to install this application on your device for a faster, native experience?';
        const yesTxt = (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.install_now) ? myCloud_LANG.install_now : 'Install now';
        const laterTxt = (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.ask_later) ? myCloud_LANG.ask_later : 'Ask me later';
        const noTxt = (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.no) ? myCloud_LANG.no : 'No';

        modal.innerHTML = 
            '<div class="myCloudModalHeader" style="border-bottom:none; padding-bottom:0;">' + title + '</div>' +
            '<div class="myCloudModalBody" style="padding: 20px 24px; text-align:center;">' +
                '<div style="margin-bottom:20px;">' +
                    '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--accent-primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>' +
                '</div>' +
                '<div style="font-size:14px; margin-bottom:25px; line-height: 1.5;">' + msg + '</div>' +
                '<div class="myCloudButtons" style="justify-content: center; gap: 10px; margin-top:0; flex-wrap:wrap;">' +
                    '<button id="btnInstallYes" style="background:var(--accent-primary); color:#fff; border-color:var(--accent-primary); padding:6px 16px;">' + yesTxt + '</button>' +
                    '<button id="btnInstallLater" style="padding:6px 16px;">' + laterTxt + '</button>' +
                    '<button id="btnInstallNo" style="padding:6px 16px; color:var(--danger); border-color:var(--border-default);">' + noTxt + '</button>' +
                '</div>' +
            '</div>';

        document.getElementById('btnInstallYes').onclick = () => {
            if (typeof myCloudCloseModal === 'function') myCloudCloseModal();
            window.myCloudDeferredPrompt.prompt();
            window.myCloudDeferredPrompt.userChoice.then((res) => {
                // If they opened the native prompt but explicitly cancelled it, treat it as a hard "No"
                if (res.outcome !== 'accepted') localStorage.setItem('myCloudPwaDismissed', 'permanent');
                window.myCloudDeferredPrompt = null;
            });
        };

        document.getElementById('btnInstallLater').onclick = () => {
            if (typeof myCloudCloseModal === 'function') myCloudCloseModal();
            // Doing nothing here ensures it will ask again next time the app loads
        };

        document.getElementById('btnInstallNo').onclick = () => {
            if (typeof myCloudCloseModal === 'function') myCloudCloseModal();
            localStorage.setItem('myCloudPwaDismissed', 'permanent');
        };
    }, 2500);
// Check every second
}, 1000); 


// ============================================================
// MANUAL PWA INSTALL TRIGGER (For Settings Menu)
// ============================================================
window.myCloudInstallAppManual = function() {
    if (window.myCloudDeferredPrompt) {
        if (typeof myCloudCloseFloatingMenu === 'function') myCloudCloseFloatingMenu(true);
        window.myCloudDeferredPrompt.prompt();
        window.myCloudDeferredPrompt.userChoice.then((res) => {
            if (res.outcome !== 'accepted') localStorage.setItem('myCloudPwaDismissed', 'permanent');
            window.myCloudDeferredPrompt = null;
        });
    } else {
        // Safari / iOS / Firefox Fallback Check
        const ua = navigator.userAgent.toLowerCase();
        const isIos = /ipad|iphone|ipod/.test(ua) && !window.MSStream;
        const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
        const isFirefox = ua.includes('firefox') || ua.includes('fxios');
        const isMobile = /android|iphone|ipad|ipod/i.test(ua);
		const isMacSafari = isSafari && ua.includes('macintosh');
        
        if (isIos || isSafari || isFirefox) {
            if (typeof myCloudCloseFloatingMenu === 'function') myCloudCloseFloatingMenu(true);
            const overlay = document.getElementById('myCloudModalOverlay');
            const modal = document.getElementById('myCloudModal');
            if (typeof myCloudResetModal === 'function') myCloudResetModal();
            
            overlay.style.display = 'flex';
            modal.className = 'myCloudModal';
            
            const title = (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.install_app) ? myCloud_LANG.install_app : 'Install App';
            const okTxt = (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.ok) ? myCloud_LANG.ok : 'OK';
            
            let instructionHtml = '';
            if (isFirefox && !isMobile) {
                instructionHtml = 'To install this app on Firefox, click the small button on the right of the address bar (left of the favorite toggle star) named <b>"Add Tab to Taskbar"</b> or <b>"Install"</b>.';
            } else if (isFirefox && isMobile) {
                instructionHtml = 'To install this app on Firefox Mobile, tap the <b>Menu</b> icon (three dots) <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" style="vertical-align:middle; margin:0 4px;"><circle cx="12" cy="5.5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="18.5" r="1.5"/></svg>' +
                    '<br>and select <b>"Install"</b> or <b>"Add to Home screen"</b> <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle; margin:0 4px;"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>.';
            } else if (isMacSafari) {
                instructionHtml = 'To install this app on Mac, click the <b>Share</b> icon <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle; margin:0 4px;"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"></path><polyline points="16 6 12 2 8 6"></polyline><line x1="12" y1="2" x2="12" y2="15"></line></svg> in the top right toolbar' +
                    '<br>and select <b>"Add to Dock"</b>.';
			} else {
                instructionHtml = 'To install this app on your device, tap the <b>Share</b> icon <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle; margin:0 4px;"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"></path><polyline points="16 6 12 2 8 6"></polyline><line x1="12" y1="2" x2="12" y2="15"></line></svg>' +
                    '<br>and select <b>"Add to Home Screen"</b> <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle; margin:0 4px;"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>.';
            }

            modal.innerHTML = 
                '<div class="myCloudModalHeader" style="border-bottom:1px solid var(--border-default);">' + title + '</div>' +
                '<div class="myCloudModalBody" style="padding: 24px; text-align:center;">' +
                    '<div style="font-size:15px; margin-bottom:20px; color:var(--text-primary); line-height:1.5;">' + 
                        instructionHtml +
                    '</div>' +
                    '<div class="myCloudButtons" style="justify-content: center; margin-top:20px;">' +
                        '<button onclick="myCloudCloseModal()" style="background:var(--accent-primary); color:#fff; border:none; padding:8px 30px;">' + okTxt + '</button>' +
                    '</div>' +
                '</div>';
        } else {
            // Unrecognized setup or already installed
            alert((typeof myCloud_LANG !== 'undefined' && myCloud_LANG.install_unsupported) ? myCloud_LANG.install_unsupported : "App is already installed or your browser does not support this feature.");
        }
    }
};


</script>