<?php
/**
 * MODULE: Dynamic JS - Admin Mode
 */
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) die('Direct access not permitted');

$currentUser = $_SESSION['username'] ?? '';
global $user_details;
$adminKeys = [];
if (!empty($user_details) && is_array($user_details)) {
    foreach ($user_details as $ud) {
        if (isset($ud['name']) && $ud['name'] === $currentUser && !empty($ud['cloud']) && is_array($ud['cloud'])) {
            foreach ($ud['cloud'] as $k => $c) {
                if (isset($c['rights']) && $c['rights'] === 'admin_mode') {
                    $adminKeys[] = $k;
                }
            }
        }
    }
}

if (!empty($adminKeys)):
?>
// EXPLICIT PHP INJECTION: JS perfectly knows which tabs are Admin tabs
const ceAdminKeys = <?php echo json_encode(array_values(array_unique($adminKeys))); ?>;

let _ceAdminLastTab = null;
let _ceAdminIsChecking = false;
window.myCloudAdminNonce = '';

// SMART SESSION: Keep alive while user is active on screen
let _ceAdminLastActivity = Date.now();
['mousedown', 'keydown', 'touchstart', 'mousemove'].forEach(ev => {
    window.addEventListener(ev, () => { _ceAdminLastActivity = Date.now(); }, {passive: true});
});

setInterval(() => {
    if (ceAdminKeys.includes(myCloudState.key) && (Date.now() - _ceAdminLastActivity < 60000)) {
        const fd = new URLSearchParams();
        fd.append('myCloud_action', 'admin_heartbeat');
        fd.append('myCloud_key', myCloudState.key);
        if (typeof myCloudCsrfToken !== 'undefined') fd.append('myCloud_token', myCloudCsrfToken);
        fetch('', {method: 'POST', body: fd}).then(r => r.json()).then(res => {
            if (res.admin_nonce) window.myCloudAdminNonce = res.admin_nonce;
        }).catch(() => {});
    }
}, 300000); // Pulse every 5 minutes if active

// PASSIVE OBSERVER
setInterval(() => {
    if (typeof myCloudState === 'undefined' || !myCloudState.key || _ceAdminIsChecking) return;
    
    // Check if the current tab is an admin tab using the hardcoded PHP array
    if (ceAdminKeys.includes(myCloudState.key)) {
        if (_ceAdminLastTab !== myCloudState.key) {
            _ceAdminIsChecking = true;
            _ceAdminLastTab = myCloudState.key;
            
            const fd = new URLSearchParams();
            fd.append('myCloud_action', 'admin_check');
            fd.append('myCloud_key', myCloudState.key);
            if (typeof myCloudCsrfToken !== 'undefined') fd.append('myCloud_token', myCloudCsrfToken);
            
            // Bypass our own interceptor for the status check
            (window._origFetch || window.fetch)('', {method: 'POST', body: fd}).then(r=>r.json()).then(res => {
                _ceAdminIsChecking = false;
                if (res.status !== 'OK') {
                    myCloudPromptAdminAuth(() => {
                        if (typeof myCloudFetchDirectory === 'function') myCloudFetchDirectory(myCloudState.currentDir || '/');
                    });
                } else {
                    window.myCloudAdminNonce = res.admin_nonce || '';
                    if (typeof myCloudFetchDirectory === 'function') myCloudFetchDirectory(myCloudState.currentDir || '/');
                }
            }).catch(() => { _ceAdminIsChecking = false; });
        }
    } else {
        _ceAdminLastTab = null;
    }
}, 500);

// NATIVE API INTERCEPT: Append Nonce to write requests safely by globally hooking fetch
if (typeof window._origFetch === 'undefined') {
    window._origFetch = window.fetch;
    window.fetch = function() {
        if (arguments[1] && arguments[1].body) {
            const body = arguments[1].body;
            let isReq = false;
            
            if (body instanceof URLSearchParams) {
                if (body.has('myCloud_key') && ceAdminKeys.includes(body.get('myCloud_key'))) {
                    body.append('admin_nonce', window.myCloudAdminNonce || '');
                    isReq = true;
                }
            } else if (body instanceof FormData) {
                if (body.has('myCloud_key') && ceAdminKeys.includes(body.get('myCloud_key'))) {
                    body.append('admin_nonce', window.myCloudAdminNonce || '');
                    isReq = true;
                }
            }
            
            if (isReq) {
                return window._origFetch.apply(this, arguments).then(async res => {
                     const clonedRes = res.clone();
                    try {
                        const data = await clonedRes.json();
                        if (data && data.admin_nonce) {
                            window.myCloudAdminNonce = data.admin_nonce;
                        }
                    } catch(e) {}
                     return res;
                 });
             }
        }
        return window._origFetch.apply(this, arguments);
    };
}

// STANDALONE MODAL
function myCloudPromptAdminAuth(onSuccess) {
    const L = typeof myCloud_LANG !== 'undefined' ? myCloud_LANG : {};
    const overlay = document.getElementById('myCloudModalOverlay');
    const modal = document.getElementById('myCloudModal');
    if (!overlay || !modal) return;

    if (typeof myCloudResetModal === 'function') myCloudResetModal();
    
    overlay.style.display = 'flex';
    overlay.style.zIndex = '16000'; 
    modal.className = 'myCloudModal'; 
    
    // HONEYPOT: Hidden from user, visible to browser heuristics.
    // Must not use display:none, otherwise aggressive managers ignore it.
    const honeypotHtml = 
        '<div style="position:absolute; left:-9999px; top:-9999px; opacity:0; height:0; width:0; overflow:hidden;" aria-hidden="true">' +
            '<input type="text" name="fake_username_honeypot" autocomplete="username" tabindex="-1">' +
            '<input type="password" name="fake_password_honeypot" autocomplete="current-password" tabindex="-1">' +
        '</div>';

    modal.innerHTML = 
        '<div class="myCloudModalHeader" style="background:#e81123; color:#fff;">' + (L.am_auth_title || 'Server Administration') + '</div>' +
        '<div class="myCloudModalBody" style="padding: 24px;">' +
            '<div style="margin-bottom:15px; font-size:14px; color:#333;">' + (L.am_auth_desc || 'Enter SSH password for this server.') + '</div>' +
            '<div id="ceAdminPwdContainer" style="position:relative; width:100%;">' +
                honeypotHtml +
                // REAL INPUT: Type password stops mobile dictionary learning.
                // Combined autocomplete tokens block both password managers AND keyboard learning.
                '<input type="password" id="ceAdminPwdInput" class="myCloudInlineInput" placeholder="' + (L.am_auth_pwd_ph || 'Password...') + '" ' +
                    'autocomplete="new-password one-time-code" spellcheck="false" autocorrect="off" autocapitalize="off" data-lpignore="true" ' +
                    'style="width:100%; box-sizing:border-box; padding:10px; padding-right:40px; margin-bottom:10px; border-radius:4px; font-size:16px;">' +
                '<div id="ceAdminToggleVis" style="position:absolute; right:10px; top:10px; cursor:pointer; opacity:0.5; user-select:none;" title="Show/Hide">' +
                    '<svg viewBox="0 0 24 24" width="20" height="20" fill="#333"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>' +
                '</div>' +
            '</div>' +
            '<div id="ceAdminErr" style="color:#e81123; font-size:12px; height:15px; margin-bottom:15px;"></div>' +
            '<div class="myCloudButtons" style="justify-content: flex-end; margin-top:0;">' +
                '<button onclick="myCloudCloseModal()" style="margin-right:10px;">' + (L.cancel || 'Cancel') + '</button>' +
                '<button id="ceAdminAuthBtn" style="background:var(--accent-primary); color:#fff; border:none; min-width:80px;">' + (L.am_auth_connect || 'Connect') + '</button>' +
            '</div>' +
        '</div>';

    const btn = document.getElementById('ceAdminAuthBtn');
    const err = document.getElementById('ceAdminErr');
    
    const bindInputEvents = () => {
        const input = document.getElementById('ceAdminPwdInput');
        const toggle = document.getElementById('ceAdminToggleVis');
        if (!input || !toggle) return;
        
        input.focus();
        
        toggle.onclick = () => {
            const isMasked = input.type === 'password';
            input.type = isMasked ? 'text' : 'password';
            toggle.style.opacity = isMasked ? '1' : '0.5';
        };

        input.onkeydown = (e) => { 
            if (e.key === 'Enter') {
                e.preventDefault(); // Stop native form triggers
                doAuth(); 
            }
        };
    };

    const restoreInput = () => {
        btn.innerHTML = L.am_auth_connect || 'Connect';
        btn.disabled = false;
        const container = document.getElementById('ceAdminPwdContainer');
        container.innerHTML =
            honeypotHtml +
            '<input type="password" id="ceAdminPwdInput" class="myCloudInlineInput" placeholder="' + (L.am_auth_pwd_ph || 'Password...') + '" ' +
                'autocomplete="new-password one-time-code" spellcheck="false" autocorrect="off" autocapitalize="off" data-lpignore="true" ' +
                'style="width:100%; box-sizing:border-box; padding:10px; padding-right:40px; margin-bottom:10px; border-radius:4px; font-size:16px;">' +
            '<div id="ceAdminToggleVis" style="position:absolute; right:10px; top:10px; cursor:pointer; opacity:0.5; user-select:none;" title="Show/Hide">' +
                '<svg viewBox="0 0 24 24" width="20" height="20" fill="#333"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>' +
            '</div>';
        bindInputEvents();
    };

    const doAuth = () => {
        const input = document.getElementById('ceAdminPwdInput');
        if (!input) return;
        
        const pwd = input.value;
        if (!pwd) return;
        
        btn.innerHTML = '...'; 
        btn.disabled = true; 
        
        // Remove from DOM to break the submit sequence tracking
        const container = document.getElementById('ceAdminPwdContainer');
        container.innerHTML = '<div style="height:42px; display:flex; align-items:center; justify-content:center; color:#666; font-size:14px; margin-bottom:10px;">' + (L.loading || 'Connecting...') + '</div>';

        setTimeout(() => {
            const fd = new URLSearchParams();
            fd.append('myCloud_action', 'admin_auth');
            fd.append('myCloud_key', myCloudState.key);
            if (typeof myCloudCsrfToken !== 'undefined') fd.append('myCloud_token', myCloudCsrfToken);
            fd.append('password', pwd);

            window._origFetch('', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(resp => {
                    if (resp.status === 'OK') {
                        window.myCloudAdminNonce = resp.admin_nonce || '';
                        if (typeof myCloudCloseModal === 'function') myCloudCloseModal();
                        else overlay.style.display = 'none';
                        if(onSuccess) setTimeout(onSuccess, 300);
                    } else {
                        err.textContent = resp.msg || L.am_auth_failed || 'Auth failed.';
                        restoreInput();
                    }
                }).catch(() => {
                    err.textContent = L.am_auth_net_err || 'Network Error';
                    restoreInput();
                });
        }, 400); 
    };

    btn.onclick = doAuth;
    setTimeout(bindInputEvents, 100);
}

window.myCloudAction_Permissions = function() {
    const L = typeof myCloud_LANG !== 'undefined' ? myCloud_LANG : {};
    const files = myCloudState.selectedFiles;
    if (files.length === 0) return;
    
    myCloudAPI('get_users_groups', {}, function(res) {
        const users = res.users || [];
        const groups = res.groups || [];
        
        const overlay = document.getElementById('myCloudModalOverlay');
        const modal = document.getElementById('myCloudModal');
        if (typeof myCloudResetModal === 'function') myCloudResetModal();
        
        overlay.style.display = 'flex';
        modal.className = 'myCloudModal';
        modal.style.maxWidth = '450px';
        
        const hasDir = files.some(function(f) {
            const item = myCloudState.allItems.find(function(i) { return i.name === f; });
            return item && item.size === 'DIR';
        });
        
        let curOwner = '', curGroup = '', curPerms = '';
        if (files.length === 1) {
            const item = myCloudState.allItems.find(function(i) { return i.name === files[0]; });
            if (item && item.owner) {
                const parts = item.owner.split(':');
                curOwner = parts[0] || '';
                curGroup = parts[1] || '';
            }
            if (item && item.perms) curPerms = item.perms;
        }

        const userOpts = users.map(function(u) { return '<option value="' + u + '" ' + (u === curOwner ? 'selected' : '') + '>' + u + '</option>'; }).join('');
        const groupOpts = groups.map(function(g) { return '<option value="' + g + '" ' + (g === curGroup ? 'selected' : '') + '>' + g + '</option>'; }).join('');

        const isR = function(idx, mask) { return curPerms ? (parseInt(curPerms[idx], 8) & mask) > 0 : false; };

        let html = 
            '<div class="myCloudModalHeader">' + (L.am_title || 'Change Ownership & Permissions') + '</div>' +
             '<div class="myCloudModalBody" style="padding: 20px;">' +
                 '<div style="display:flex; gap:15px; margin-bottom: 20px;">' +
                     '<div style="flex:1;">' +
                        '<label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">' + (L.am_owner || 'Owner') + '</label>' +
                        '<select id="am_owner" class="myCloudInlineInput" style="width:100%;"><option value="">' + (L.am_no_change || '-- No Change --') + '</option>' + userOpts + '</select>' +
                     '</div>' +
                     '<div style="flex:1;">' +
                        '<label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">' + (L.am_group || 'Group') + '</label>' +
                        '<select id="am_group" class="myCloudInlineInput" style="width:100%;"><option value="">' + (L.am_no_change || '-- No Change --') + '</option>' + groupOpts + '</select>' +
                     '</div>' +
                 '</div>' +
                 
                 '<div style="margin-bottom:12px;">' +
                     '<label style="font-size:13px; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:6px;">' +
                         '<input type="checkbox" id="am_change_perms" ' + (files.length === 1 ? 'checked' : '') + ' class="myCloudCheckbox" style="margin:0;">' +
                         (L.am_modify_perms || 'Modify Permissions') +
                    '</label>' +
                 '</div>' +
                 
                 '<table style="width:100%; text-align:center; border-collapse:collapse; margin-bottom:20px; font-size:13px;" id="am_perms_table">' +
                     '<tr style="background:var(--gray-10); border-bottom:1px solid var(--border-default);">' +
                        '<th style="padding:8px; text-align:left;">' + (L.am_class || 'Class') + '</th>' +
                        '<th>' + (L.am_read || 'Read') + '</th>' +
                        '<th>' + (L.am_write || 'Write') + '</th>' +
                        '<th>' + (L.am_execute || 'Execute') + '</th>' +
                     '</tr>' +
                     '<tr style="border-bottom:1px solid var(--border-subtle);">' +
                        '<td style="text-align:left; padding:8px;">' + (L.am_owner || 'Owner') + '</td>' +
                         '<td><input type="checkbox" id="am_u_r" class="myCloudCheckbox" ' + (isR(1,4)?'checked':'') + '></td>' +
                         '<td><input type="checkbox" id="am_u_w" class="myCloudCheckbox" ' + (isR(1,2)?'checked':'') + '></td>' +
                         '<td><input type="checkbox" id="am_u_x" class="myCloudCheckbox" ' + (isR(1,1)?'checked':'') + '></td>' +
                     '</tr>' +
                     '<tr style="border-bottom:1px solid var(--border-subtle);">' +
                        '<td style="text-align:left; padding:8px;">' + (L.am_group || 'Group') + '</td>' +
                         '<td><input type="checkbox" id="am_g_r" class="myCloudCheckbox" ' + (isR(2,4)?'checked':'') + '></td>' +
                         '<td><input type="checkbox" id="am_g_w" class="myCloudCheckbox" ' + (isR(2,2)?'checked':'') + '></td>' +
                         '<td><input type="checkbox" id="am_g_x" class="myCloudCheckbox" ' + (isR(2,1)?'checked':'') + '></td>' +
                     '</tr>' +
                     '<tr>' +
                        '<td style="text-align:left; padding:8px;">' + (L.am_others || 'Others') + '</td>' +
                         '<td><input type="checkbox" id="am_o_r" class="myCloudCheckbox" ' + (isR(3,4)?'checked':'') + '></td>' +
                         '<td><input type="checkbox" id="am_o_w" class="myCloudCheckbox" ' + (isR(3,2)?'checked':'') + '></td>' +
                         '<td><input type="checkbox" id="am_o_x" class="myCloudCheckbox" ' + (isR(3,1)?'checked':'') + '></td>' +
                     '</tr>' +
                 '</table>';
                 
         if (hasDir) {
             html += 
                 '<div style="margin-bottom:15px; padding:10px; background:var(--warning-light); border:1px solid rgba(216, 59, 1, 0.3); border-radius:4px;">' +
                     '<label style="font-size:13px; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:6px; color:var(--warning);">' +
                         '<input type="checkbox" id="am_recursive" class="myCloudCheckbox" style="margin:0;">' +
                        (L.am_recursive || 'Apply recursively to all contents') +
                     '</label>' +
                 '</div>';
         }
                 
         html += 
                 '<div class="myCloudButtons" style="margin-top:0;">' +
                    '<button onclick="myCloudCloseModal()" style="margin-right:10px;">' + (L.cancel || 'Cancel') + '</button>' +
                    '<button id="am_apply_btn" style="background:var(--accent-primary); color:#fff; border:none; padding:6px 20px;">' + (L.am_apply || 'Apply') + '</button>' +
                 '</div>' +
             '</div>';

        modal.innerHTML = html;

        const togglePerms = function() {
            const active = document.getElementById('am_change_perms').checked;
            const tbl = document.getElementById('am_perms_table');
            tbl.style.opacity = active ? '1' : '0.4';
            tbl.style.pointerEvents = active ? 'auto' : 'none';
        };
        document.getElementById('am_change_perms').onchange = togglePerms;
        togglePerms();

        document.getElementById('am_apply_btn').onclick = function() {
            const owner = document.getElementById('am_owner').value;
            const group = document.getElementById('am_group').value;
            let perms = '';
            if (document.getElementById('am_change_perms').checked) {
                const val = function(id) { return document.getElementById(id).checked; };
                const u = (val('am_u_r')?4:0) + (val('am_u_w')?2:0) + (val('am_u_x')?1:0);
                const g = (val('am_g_r')?4:0) + (val('am_g_w')?2:0) + (val('am_g_x')?1:0);
                const o = (val('am_o_r')?4:0) + (val('am_o_w')?2:0) + (val('am_o_x')?1:0);
                perms = '0' + u + g + o;
            }
            const rec = hasDir && document.getElementById('am_recursive').checked;

            myCloudCloseModal();
            myCloudAPI('apply_permissions', {
                paths: JSON.stringify(files),
                owner: owner,
                group: group,
                perms: perms,
                recursive: rec
            }, function() {
                if (myCloudState.isCommanderMode) {
                    refreshCommanderPane(myCloudState.commanderActive);
                } else {
                    myCloudFetchDirectory(myCloudState.currentDir);
                }
            });
        };
    });
};
<?php endif; ?>