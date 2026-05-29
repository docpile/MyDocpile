<?php
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) die('Direct access not permitted');
?>
<script>

window.myCloudShowEmailSettings = function() {
    const overlay = document.getElementById('myCloudModalOverlay');
    const modal = document.getElementById('myCloudModal');
    if (typeof myCloudResetModal === 'function') myCloudResetModal();

    overlay.style.display = 'flex';
    modal.className = 'myCloudModal';
    modal.style.width = '500px';
    modal.style.maxHeight = '90vh';
    modal.style.display = 'flex';
    modal.style.flexDirection = 'column';

    const L = typeof myCloud_LANG !== 'undefined' ? myCloud_LANG : {};

    // Bulletproof HTML escaping function
    const esc = function(str) {
        return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    };


    const editIcon = '<span class="owa-icon"><svg viewBox="0 0 24 24"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg></span>';
    const delIcon = '<span class="owa-icon"><svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg></span>';
    const activeIcon = '<span class="owa-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg></span>';
    const inactiveIcon = '<span class="owa-icon"><svg viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="2" d="M21 12c-2.4 4 -5.4 6 -9 6-3.6 0-6.6-2-9-6 2.4-4 5.4-6 9-6 3.6 0 6.6 2 9 6Z"/><line x1="3" y1="3" x2="21" y2="21" stroke="currentColor" stroke-width="2"/></svg></span>';
    const addIcon = '<span class="owa-icon"><svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg></span>';
    const upIcon = '<span class="owa-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"></polyline></svg></span>';
    const downIcon = '<span class="owa-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg></span>';

    const renderForm = (acc = null) => {
        const isNew = !acc;
        const data = acc || {};

        // Explicit fallback variables to prevent 'undefined' string crashes
        const valId = data.id || '';
        const valName = data.name || '';
        const valEmail = data.email || '';
        const valLoginUser = data.login_user || data.email || '';
		const valAuthType = data.auth_type || 'basic';
        const valAliases = data.aliases || [];
        const valSignature = data.signature || '';
		const valServerType = data.server_type || 'imap';
        const valEasHost = data.eas_host || '';
        const valPgpPub = data.pgp_public_key || '';
        const valPgpPriv = data.pgp_private_key || '';
        const valImap = data.imap_host || '';
        const valImapPort = data.imap_port || (isNew ? '993' : '');
        const valImapEnc = data.imap_enc || 'ssl';
        const valSmtp = data.smtp_host || '';
        const valSmtpPort = data.smtp_port || (isNew ? '465' : '');
        const valSmtpEnc = data.smtp_enc || 'ssl';

        const optStr = function(val, match) { return val === match ? 'selected' : ''; };

        const canEditSettings = window.myCloudActionAllowed('email_settings');
        const hideForeign = window.myCloudMailOnlyLocalhost === true || !window.myCloudActionAllowed('email_add_foreign_servers');

        let serverFieldsHtml = '';
		let pgpContent = '';
        if (!hideForeign && canEditSettings) {
            serverFieldsHtml = 
                '<div style="margin-bottom:15px;">' +
                    '<label style="font-size:12px; color:var(--text-secondary); font-weight:bold; display:block; margin-bottom:4px;">' + (L.server_type || 'Server Type') + '</label>' +
                    '<select id="emlAccServerType" class="myCloudInlineInput" style="width:100%; margin:0; height:34px;">' +
                        '<option value="imap" ' + optStr(valServerType, 'imap') + '>IMAP / SMTP</option>' +
                        '<option value="eas" ' + optStr(valServerType, 'eas') + '>Exchange ActiveSync (EAS)</option>' +
                    '</select>' +
                '</div>' +
                '<div id="emlImapSmtpFields" style="display:' + (valServerType === 'imap' ? 'block' : 'none') + ';">' +
               '<div style="display:flex; gap:10px; align-items:flex-end;">' +
                    '<div style="flex:2;"><label style="font-size:12px; color:var(--text-secondary); font-weight:bold; display:block; margin-bottom:4px;">' + (L.imap_server || 'IMAP Host') + '</label>' +
                    '<input type="text" id="emlAccImap" class="myCloudInlineInput" value="' + esc(valImap) + '" style="width:100%; margin:0;"></div>' +
                    
                    '<div style="flex:1;"><label style="font-size:12px; color:var(--text-secondary); font-weight:bold; display:block; margin-bottom:4px;">' + (L.port || 'Port') + '</label>' +
                    '<input type="text" id="emlAccImapPort" class="myCloudInlineInput" value="' + esc(valImapPort) + '" style="width:100%; margin:0;"></div>' +
                    
                    '<div style="flex:1.5;"><label style="font-size:12px; color:var(--text-secondary); font-weight:bold; display:block; margin-bottom:4px;">' + (L.encryption || 'Encryption') + '</label>' +
                    '<select id="emlAccImapEnc" class="myCloudInlineInput" style="width:100%; margin:0; height:30px;">' +
                        '<option value="ssl" ' + optStr(valImapEnc, 'ssl') + '>' + (L.ssl_tls || 'SSL / TLS') + '</option>' +
                        '<option value="none" ' + optStr(valImapEnc, 'none') + '>' + (L.none_localhost || 'None (Localhost)') + '</option>' +
                    '</select></div>' +
                '</div>' +

                '<div style="display:flex; gap:10px; align-items:flex-end;">' +
                    '<div style="flex:2;"><label style="font-size:12px; color:var(--text-secondary); font-weight:bold; display:block; margin-bottom:4px;">' + (L.smtp_server || 'SMTP Host') + '</label>' +
                    '<input type="text" id="emlAccSmtp" class="myCloudInlineInput" value="' + esc(valSmtp) + '" style="width:100%; margin:0;"></div>' +
                    
                    '<div style="flex:1;"><label style="font-size:12px; color:var(--text-secondary); font-weight:bold; display:block; margin-bottom:4px;">' + (L.port || 'Port') + '</label>' +
                    '<input type="text" id="emlAccSmtpPort" class="myCloudInlineInput" value="' + esc(valSmtpPort) + '" style="width:100%; margin:0;"></div>' +
                    
                    '<div style="flex:1.5;"><label style="font-size:12px; color:var(--text-secondary); font-weight:bold; display:block; margin-bottom:4px;">' + (L.encryption || 'Encryption') + '</label>' +
                    '<select id="emlAccSmtpEnc" class="myCloudInlineInput" style="width:100%; margin:0; height:30px;">' +
                        '<option value="ssl" ' + optStr(valSmtpEnc, 'ssl') + '>' + (L.ssl_tls || 'SSL / TLS') + '</option>' +
                        '<option value="tls" ' + optStr(valSmtpEnc, 'tls') + '>' + (L.starttls || 'STARTTLS') + '</option>' +
                        '<option value="none" ' + optStr(valSmtpEnc, 'none') + '>' + (L.none_localhost || 'None (Localhost)') + '</option>' +
                    '</select></div>' +
                '</div></div>' +
                '<div id="emlEasFields" style="display:' + (valServerType === 'eas' ? 'block' : 'none') + ';">' +
                    '<label style="font-size:12px; color:var(--text-secondary); font-weight:bold; display:block; margin-bottom:4px;">' + (L.eas_server || 'EAS Server URL') + '</label>' +
                    '<input type="url" id="emlAccEasHost" class="myCloudInlineInput" value="' + esc(valEasHost) + '" placeholder="https://outlook.office365.com" style="width:100%; margin:0 0 10px 0;">' +
                '</div>';
        } else {
            serverFieldsHtml = 
                '<input type="hidden" id="emlAccServerType" value="' + esc(valServerType) + '">' +
                '<input type="hidden" id="emlAccEasHost" value="' + esc(valEasHost) + '">' +
                '<input type="hidden" id="emlAccImap" value="' + esc(valImap) + '">' +
                '<input type="hidden" id="emlAccImapPort" value="' + esc(valImapPort) + '">' +
                '<input type="hidden" id="emlAccImapEnc" value="' + esc(valImapEnc) + '">' +
                '<input type="hidden" id="emlAccSmtp" value="' + esc(valSmtp) + '">' +
                '<input type="hidden" id="emlAccSmtpPort" value="' + esc(valSmtpPort) + '">' +
                '<input type="hidden" id="emlAccSmtpEnc" value="' + esc(valSmtpEnc) + '">';
        }

        let formContent = '';
        if (canEditSettings) {
            formContent = 
                '<label style="font-size:12px; color:var(--text-secondary); font-weight:bold;">' + (L.acc_label || 'Account Label (Internal Use Only)') + '</label>' +
                '<input type="text" id="emlAccName" class="myCloudInlineInput" value="' + esc(valName) + '" placeholder="' + (L.acc_label_ph || 'e.g. My Secret Stash') + '" style="margin:0;">' +
                
                '<label style="font-size:12px; color:var(--text-secondary); font-weight:bold;">' + (L.sender_name || 'Sender Name (Public Display Name)') + '</label>' +
                '<input type="text" id="emlAccSenderName" class="myCloudInlineInput" value="' + esc(data.sender_name || '') + '" placeholder="' + (L.sender_name_ph || 'e.g. John Doe (Leave blank to use email only)') + '" style="margin:0;">' +
                
                '<label style="font-size:12px; color:var(--text-secondary); font-weight:bold;">' + (L.email_address || 'Email Address') + '</label>' +
                '<input type="email" id="emlAccEmail" class="myCloudInlineInput" value="' + esc(valEmail) + '" style="margin:0;">' +

                '<label style="font-size:12px; color:var(--text-secondary); font-weight:bold;">' + (L.login_user || 'Login Username') + '</label>' +
                '<input type="text" id="emlAccLoginUser" class="myCloudInlineInput" value="' + esc(valLoginUser) + '" style="margin:0;">' +

                '<label style="font-size:12px; color:var(--text-secondary); font-weight:bold;">' + (L.aliases || 'Aliases') + '</label>' +
                '<div id="emlAccAliasesContainer" style="display:flex; flex-direction:column; gap:8px;"></div>' +
                '<button type="button" class="owa-btn" onclick="window._addAccAlias()" style="width:fit-content; margin-bottom:10px; font-size:11px; padding: 4px 10px;">+ Add Alias</button>' +
               
                serverFieldsHtml +

                (hideForeign ? '<input type="hidden" id="emlAccAuthType" value="' + esc(valAuthType) + '">' :
                '<label style="font-size:12px; color:var(--text-secondary); font-weight:bold;">' + (L.auth_type || 'Authentication Type') + '</label>' +
                '<select id="emlAccAuthType" class="myCloudInlineInput" style="margin:0; width:100%;" onchange="document.getElementById(\'emlAuthBasicGrp\').style.display = this.value === \'basic\' ? \'block\' : \'none\'; document.getElementById(\'emlAuthOauthGrp\').style.display = this.value === \'oauth2\' ? \'block\' : \'none\';">' +
                    '<option value="basic" ' + (valAuthType === 'basic' ? 'selected' : '') + '>Basic (Password / App Password)</option>' +
                    '<option value="oauth2" ' + (valAuthType === 'oauth2' ? 'selected' : '') + '>OAuth2 (Outlook / Office 365)</option>' +
                '</select>') +

                '<div id="emlAuthBasicGrp" style="display:' + (valAuthType === 'basic' || hideForeign ? 'block' : 'none') + '; margin-top:10px;">' +
                    '<label style="font-size:12px; color:var(--text-secondary); font-weight:bold;">' + (L.pwd_app_rec || 'Password (App Password recommended)') + '</label>' +
                    '<div style="position:relative; display:flex; align-items:center; width:100%;">' +
                        '<input type="password" id="emlAccPwd" autocomplete="new-password" class="myCloudInlineInput" placeholder="' + (isNew ? '' : (L.pwd_unchanged || '(Leave blank to keep unchanged)')) + '" style="width:100%; padding-inline-end:32px; margin:0;">' +
                        '<button type="button" tabindex="-1" onclick="window._toggleEmlPwd(this, \'emlAccPwd\')" style="position:absolute; inset-inline-end:4px; background:none; border:none; cursor:pointer; color:var(--text-secondary); padding:4px;" title="' + (L.toggle_pwd_vis || 'Toggle visibility') + '">' +
                            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px; height:16px;"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>' +
                        '</button>' +
                    '</div>' +
                '</div>' +
                '<div id="emlAuthOauthGrp" style="display:' + (valAuthType === 'oauth2' ? 'block' : 'none') + '; margin-top:10px; background:var(--gray-05); padding:15px; border-radius:4px; border:1px solid var(--border-default);">' +
                    '<div style="font-size:13px; font-weight:bold; color:var(--text-primary); margin-bottom:10px;">Microsoft Azure AD Configuration</div>' +
                    '<div style="font-size:12px; color:var(--text-secondary); margin-bottom:10px;"><b>Redirect URI required in Azure:</b><br><span style="user-select:all; background:var(--gray-15); padding:3px 6px; border-radius:3px; display:inline-block; margin-top:4px;" id="emlOauthRedirectDisp"></span></div>' +
                    '<button type="button" class="owa-btn owa-primary" onclick="window._emlStartOauthFlow()" style="width:100%; justify-content:center; height:36px;">' + (L.auth_with_ms || 'Authorize with Microsoft') + '</button>' +
                    '<div id="oauthStatus" style="font-size:12px; margin-top:10px; text-align:center; font-weight:bold;"></div>' +
                '</div>'

            pgpContent =
                 '<div style="margin-top:20px; padding:15px; border:1px solid var(--border-default); border-radius:4px; background:var(--gray-05);">' +
                        '<div style="font-size:15px; font-weight:bold; margin-bottom:12px; color:var(--text-primary);">' + (L.pgp_key_mgmt || 'PGP Key Management') + '</div>' +
                        '<style>.pgp-btn-mod { flex: 1 1 calc(50% - 5px); min-width: 140px; height: auto !important; min-height: 36px; padding: 8px 12px; white-space: normal !important; line-height: 1.3; display: flex; align-items: center; justify-content: center; text-align: center; margin: 0; box-sizing: border-box; font-size:12px; }</style>' +
                        '<div style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom:15px; background:var(--gray-10); padding:12px; border-radius:6px; border:1px solid var(--border-medium);">' +
                            '<button type="button" class="owa-btn pgp-btn-mod" onclick="window._emlGeneratePgpKeys()" style="background:var(--accent-primary); color:#fff; border:none;">🔑 ' + (L.pgp_gen_keys_btn || 'Generate Key Pair') + '</button>' +
                            '<button type="button" class="owa-btn pgp-btn-mod" onclick="window._emlImportPgpKeys()" style="background:var(--gray-00);">📥 ' + (L.pgp_import_keys || 'Import Keys') + '</button>' +
                            '<button type="button" class="owa-btn pgp-btn-mod" onclick="window._emlExportKey(\'public\')" style="background:var(--gray-00);">📤 ' + (L.pgp_export_pub || 'Export Public Key') + '</button>' +
                            '<button type="button" class="owa-btn pgp-btn-mod" onclick="window._emlExportKey(\'private\')" style="background:var(--gray-00);">📤 ' + (L.pgp_export_priv || 'Export Private Key') + '</button>' +
                            '<button type="button" class="owa-btn pgp-btn-mod" onclick="window._emlChangePgpPassphrase()" style="background:var(--gray-00);">🔐 ' + (L.pgp_change_passphrase || 'Change Passphrase') + '</button>' +
                            '<a href="https://keys.openpgp.org/manage" target="_blank" class="owa-btn pgp-btn-mod" style="background:var(--gray-00); text-decoration:none; color:inherit;">⚙️ ' + (L.pgp_manage_published || 'Manage Global') + ' ↗</a>' +
                            '<div style="flex: 1 1 100%; height:1px; background:var(--border-medium); margin:4px 0;"></div>' +
                            '<button type="button" class="owa-btn pgp-btn-mod" onclick="window._emlPublishLocalKey()" style="background:var(--gray-00); color:var(--success);" title="Save to local storage">⬆️ ' + (L.pgp_publish_local || 'Publish to Local Storage') + '</button>' +
                            '<button type="button" class="owa-btn pgp-btn-mod" onclick="window._emlUnpublishLocalKey()" style="background:var(--gray-00); color:var(--danger);" title="Remove from local storage">🗑️ ' + (L.pgp_unpublish_local || 'Remove from Local Storage') + '</button>' +
                        '</div>' +

                     '<label style="font-size:12px; color:var(--text-secondary); font-weight:bold;">' + (L.pgp_your_pub_key || 'Your Public Key (ASCII Armored)') + '</label>' +
                     '<textarea id="emlAccPgpPub" class="myCloudInlineInput" style="min-height:100px; resize:vertical; font-family:monospace; font-size:11px; margin-bottom:10px;">' + esc(valPgpPub) + '</textarea>' +
                     '<label style="font-size:12px; color:var(--text-secondary); font-weight:bold;">' + (L.pgp_your_priv_key || 'Your Private Key (ASCII Armored - Will be encrypted locally)') + '</label>' +
                     '<textarea id="emlAccPgpPriv" class="myCloudInlineInput" style="min-height:100px; resize:vertical; font-family:monospace; font-size:11px; margin:0;">' + esc(valPgpPriv) + '</textarea>' +
                 '</div>';
        } else {
            formContent = 
                '<input type="hidden" id="emlAccName" value="' + esc(valName) + '">' +
                '<input type="hidden" id="emlAccEmail" value="' + esc(valEmail) + '">' +
                '<input type="hidden" id="emlAccLoginUser" value="' + esc(valLoginUser) + '">' +
                '<div id="emlAccAliasesContainer" style="display:none;"></div>' +
                '<input type="hidden" id="emlAccPwd" value="">' +
                serverFieldsHtml +
                '<label style="font-size:12px; color:var(--text-secondary); font-weight:bold;">' + (L.sender_name || 'Sender Name (Public Display Name)') + '</label>' +
                '<input type="text" id="emlAccSenderName" class="myCloudInlineInput" value="' + esc(data.sender_name || '') + '" placeholder="' + (L.sender_name_ph || 'e.g. John Doe (Leave blank to use email only)') + '" style="margin:0;">';
        }

        modal.innerHTML = 
            '<div class="myCloudModalHeader" style="justify-content:space-between;"><span>' + myCloudSvgLogo + ' ' + (isNew ? (L.add_account || 'Add Account') : (L.edit_account || 'Edit Account')) + '</span><button class="myCloudClose" onclick="myCloudShowEmailSettings()">✕</button></div>' +
            '<div class="myCloudModalBody" style="padding:20px; display:flex; flex-direction:column; gap:12px; overflow-y:auto; flex:1; min-height:0;">' +
                '<input type="hidden" id="emlAccId" value="' + esc(valId) + '">' +
				formContent +
                 '<label style="font-size:12px; color:var(--text-secondary); font-weight:bold; margin-top:16px; display:block; margin-bottom:6px; ">' + (L.signature || 'Signature') + '</label>' +
                 '<input type="hidden" id="emlAccSignatureVal" value="' + esc(valSignature) + '">' +
                 '<button type="button" class="owa-btn" onclick="window._emailOpenSignatureEditor(\'emlAccSignatureVal\')" style="display:inline-flex; align-items:center; width:fit-content; border:1px solid var(--border-medium); border-radius:4px; padding:0 16px; height:36px; gap:10px; background:var(--gray-00); color:var(--text-primary); cursor:pointer; transition:background 0.1s;" onmouseover="this.style.background=\'var(--hover-bg-light)\'" onmouseout="this.style.background=\'var(--gray-00)\'">' +
                     '<span class="owa-icon" style="display:flex; color:var(--accent-primary);"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg></span>' +
                     '<span class="owa-label" style="font-size:13px; font-weight:600; padding-top:12px; padding-bottom:12px;">' + (L.edit_html_sig || 'Edit HTML Signature') + '</span>' +
                '</button>' +
					pgpContent +
                '<div style="display:flex; justify-content:space-between; padding-top:15px; border-top:1px solid var(--border-default); margin-top:15px; align-items:center;">' +
                    '<button type="button" id="emlAccTestBtn" class="owa-btn" style="background:var(--gray-10); border:1px solid var(--border-medium);">' + (L.test_connection || 'Test Connection') + '</button>' +
                    '<button id="emlAccSaveBtn" class="owa-btn owa-primary">' + (L.save || 'Save') + '</button>' +
                '</div>' +
            '</div>';

        const typeSelect = document.getElementById('emlAccServerType');
        if (typeSelect) {
            typeSelect.onchange = function() {
                const imapF = document.getElementById('emlImapSmtpFields');
                const easF = document.getElementById('emlEasFields');
                if (imapF) imapF.style.display = this.value === 'imap' ? 'block' : 'none';
                if (easF) easF.style.display = this.value === 'eas' ? 'block' : 'none';
            };
        }

        if (!window._toggleEmlPwd) window._toggleEmlPwd = function(btn, inputId = 'emlAccPwd') {
            const input = document.getElementById(inputId);
            if (!input) return;
            const svg = btn.querySelector('svg');
            if (input.type === 'password') {
                input.type = 'text';
                btn.style.color = 'var(--accent-primary)';
                svg.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>';
            } else {
                input.type = 'password';
                btn.style.color = 'var(--text-secondary)';
                svg.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>';
            }
        };
		
        window._emlChangePgpPassphrase = async function() {
            const privKeyArmor = document.getElementById('emlAccPgpPriv').value.trim();
            if (!privKeyArmor) return myCloudShowAlert(L.error_prefix || 'Error', L.pgp_no_priv_key || 'No private key found to change.');

            if (!window.openpgp) await new Promise((res, rej) => { const s = document.createElement('script'); s.src = '/script/openpgp/openpgp.min.js'; s.onload = res; s.onerror = rej; document.head.appendChild(s); });

            const subOverlay = document.createElement('div');
            subOverlay.className = 'myCloudOverlay';
            subOverlay.style.display = 'flex';
            subOverlay.style.zIndex = '100010';

            const subModal = document.createElement('div');
            subModal.className = 'myCloudModal';
            subModal.style.maxWidth = '480px';
            subModal.style.width = '100%';

			subModal.innerHTML = 
                 '<div class="myCloudModalHeader" style="justify-content:space-between;"><span>' + (typeof myCloudSvgLogo !== 'undefined' ? myCloudSvgLogo : '') + ' ' + (L.pgp_change_passphrase || 'Change Passphrase') + '</span><button class="myCloudClose" id="pgpChangeClose">✕</button></div>' +
                 '<div class="myCloudModalBody" style="padding:20px;">' +
                     '<label id="pgpChangeLbl" style="display:block; margin-bottom:8px; font-weight:600; font-size:13px; color:var(--text-primary);">' + (L.pgp_unlock_msg || 'Enter your current passphrase:') + '</label>' +
                     '<div style="position:relative; display:flex; align-items:center; width:100%; margin-bottom:15px;">' +
                         '<input type="password" id="pgpChangePwdInput" autocomplete="new-password" class="myCloudInlineInput" style="width:100%; box-sizing:border-box; padding:8px 10px; padding-inline-end:32px; font-size:14px; margin:0;">' +
                         '<button type="button" tabindex="-1" onclick="window._toggleEmlPwd(this, \'pgpChangePwdInput\')" style="position:absolute; inset-inline-end:4px; background:none; border:none; cursor:pointer; color:var(--text-secondary); padding:4px;" title="' + (L.toggle_pwd_vis || 'Toggle visibility') + '">' +
                             '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px; height:16px;"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>' +
                         '</button>' +
                     '</div>' +
                    '<div class="myCloudButtons" style="justify-content:flex-end; flex-wrap:wrap; gap:10px; margin-top:20px; margin-bottom:0;">' +
                        '<button type="button" id="pgpChangeCancel" style="margin:0;">' + (L.cancel || 'Cancel') + '</button>' +
                        '<button type="button" id="pgpChangeSubmit" style="background:var(--accent-primary); color:#fff; border:none; margin:0;">' + (L.ok || 'OK') + '</button>' +
                    '</div>' +
                 '</div>';

            subOverlay.appendChild(subModal);
            document.body.appendChild(subOverlay);

            const closePrompt = () => { subOverlay.remove(); };
            document.getElementById('pgpChangeClose').onclick = closePrompt;
            document.getElementById('pgpChangeCancel').onclick = closePrompt;

            const input = document.getElementById('pgpChangePwdInput');
            setTimeout(() => input.focus(), 50);

            let step = 1;
            let decryptedKey = null;

            const submitPrompt = async () => {
                const passphrase = input.value;
                if (!passphrase) return;

                if (step === 1) {
                    try {
                        const privateKey = await window.openpgp.readPrivateKey({ armoredKey: privKeyArmor });
                        decryptedKey = await window.openpgp.decryptKey({ privateKey, passphrase: passphrase });
                        
                        step = 2;
                        input.value = '';
                        document.getElementById('pgpChangeLbl').textContent = L.pgp_new_passphrase_msg || 'Enter your new passphrase:';
                        input.focus();
                    } catch (e) {
                        myCloudShowAlert(L.error_prefix || 'Error', L.pgp_bad_passphrase || 'Incorrect current passphrase.');
                    }
                } else if (step === 2) {
                    closePrompt();
                    myCloudCreateProgressUI(L.pgp_generating || 'Updating Key...');
                    try {
                        const reencryptedKey = await window.openpgp.encryptKey({ privateKey: decryptedKey, passphrase: passphrase });
                        document.getElementById('emlAccPgpPriv').value = reencryptedKey.armor();
                        myCloudCloseProgressUI();
                        myCloudShowAlert(L.success || 'Success', L.pgp_passphrase_changed || 'Passphrase updated successfully. Remember to click Save.');
                    } catch (e) {
                        myCloudCloseProgressUI();
                        myCloudShowAlert(L.error_prefix || 'Error', e.message);
                    }
                }
            };

            document.getElementById('pgpChangeSubmit').onclick = submitPrompt;
            input.onkeydown = (e) => { if (e.key === 'Enter') { e.preventDefault(); submitPrompt(); } if (e.key === 'Escape') { e.preventDefault(); closePrompt(); } };
            if (typeof myCloudApplyTheme === 'function') myCloudApplyTheme();
      };
		

        // Professional PGP Key Generator
        window._emlGeneratePgpKeys = async function() {
            const email = document.getElementById('emlAccEmail').value.trim();
            const name = document.getElementById('emlAccSenderName').value.trim() || document.getElementById('emlAccName').value.trim() || 'MyCloud User';
            const existingKey = document.getElementById('emlAccPgpPriv') ? document.getElementById('emlAccPgpPriv').value.trim() : '';
			
            if (!email) {
                return myCloudShowAlert(L.error_prefix || 'Error', L.pgp_err_need_email || 'Please enter an Email Address in the account settings first.');
            }

            const doGenerate = () => {
                let userIds = [{ name: name, email: email }];
            const aliasInputs = document.querySelectorAll('#emlAccAliasesContainer .alias-email');
            aliasInputs.forEach(inp => {
                const alEmail = inp.value.trim();
                if (alEmail && alEmail.includes('@')) {
                    userIds.push({ name: name, email: alEmail });
                }
            });

            // We must build a stacked sub-overlay so we don't destroy the underlying settings modal
            const subOverlay = document.createElement('div');
            subOverlay.className = 'myCloudOverlay';
            subOverlay.style.display = 'flex';
            subOverlay.style.zIndex = '100010'; // Higher than main modal

            const subModal = document.createElement('div');
            subModal.className = 'myCloudModal';
            subModal.style.maxWidth = '480px';
            subModal.style.width = '100%';

            subModal.innerHTML = 
                 '<div class="myCloudModalHeader" style="justify-content:space-between;"><span>' + (typeof myCloudSvgLogo !== 'undefined' ? myCloudSvgLogo : '') + ' ' + (L.pgp_gen_keys_btn || 'Generate Key Pair') + '</span><button class="myCloudClose" id="pgpGenClose">✕</button></div>' +
                 '<div class="myCloudModalBody" style="padding:20px;">' +
                     '<label style="display:block; margin-bottom:8px; font-weight:600; font-size:13px; color:var(--text-primary);">' + (L.pgp_passphrase_prompt || 'Set a Passphrase to protect your Private Key:') + '</label>' +
                     '<div style="position:relative; display:flex; align-items:center; width:100%; margin-bottom:15px;">' +
                         '<input type="password" id="pgpGenPwdInput" autocomplete="new-password" class="myCloudInlineInput" style="width:100%; box-sizing:border-box; padding:8px 10px; padding-inline-end:32px; font-size:14px; margin:0;">' +
                         '<button type="button" tabindex="-1" onclick="window._toggleEmlPwd(this, \'pgpGenPwdInput\')" style="position:absolute; inset-inline-end:4px; background:none; border:none; cursor:pointer; color:var(--text-secondary); padding:4px;" title="' + (L.toggle_pwd_vis || 'Toggle visibility') + '">' +
                             '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px; height:16px;"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>' +
                         '</button>' +
                     '</div>' +
                    '<div class="myCloudButtons" style="justify-content:flex-end; flex-wrap:wrap; gap:10px; margin-top:20px; margin-bottom:0;">' +
                        '<button type="button" id="pgpGenCancel" style="margin:0;">' + (L.cancel || 'Cancel') + '</button>' +
                        '<button type="button" id="pgpGenSubmit" style="background:var(--accent-primary); color:#fff; border:none; margin:0;">' + (L.ok || 'OK') + '</button>' +
                    '</div>' +
                 '</div>';

            subOverlay.appendChild(subModal);
            document.body.appendChild(subOverlay);

            const closePrompt = () => { subOverlay.remove(); };
            document.getElementById('pgpGenClose').onclick = closePrompt;
            document.getElementById('pgpGenCancel').onclick = closePrompt;

            const input = document.getElementById('pgpGenPwdInput');
            setTimeout(() => input.focus(), 50);

            const submitPrompt = async () => {
                const passphrase = input.value;
                if (!passphrase) return;
                
                closePrompt();
                myCloudCreateProgressUI(L.pgp_generating || 'Generating RSA-4096 Keys (This may take a moment)...');
                
                if (!window.openpgp) {
                    await new Promise((res, rej) => { const s = document.createElement('script'); s.src = '/script/openpgp/openpgp.min.js'; s.onload = res; s.onerror = rej; document.head.appendChild(s); });
                }

                setTimeout(async () => {
                    try {
                        const { privateKey, publicKey } = await window.openpgp.generateKey({
                            type: 'ecc',
                            curve: 'curve25519',
                            userIDs: userIds,
                            passphrase: passphrase
							});

                        document.getElementById('emlAccPgpPub').value = publicKey;
                        document.getElementById('emlAccPgpPriv').value = privateKey;
                        
                        myCloudCloseProgressUI();
                        
                        // Force save of the new keys to the backend so the local WKD generates immediately
                        document.getElementById('emlAccSaveBtn').click();

                        // PGP BACKUP URGE
                        setTimeout(() => {
                            myCloudShowAlert(L.warning || 'Important: Backup Required', 
                                L.backup_required_msg || 'Your PGP Key Pair has been successfully generated. If you forget your password or lose access, you will <b>permanently lose the ability to read your encrypted emails</b>.<br><br>Please download a safe backup of your Private Key now.',
                                () => { window._emlExportKey('private'); }
                            );
                        }, 500);

                        // Look for this block inside _emlGeneratePgpKeys
						myCloudShowAlert(L.success || 'Success', (L.pgp_publish_ask || 'Your key was saved locally. Do you also want to publish it to the global directory (keys.openpgp.org)? You will receive a verification email.'), () => {
							myCloudCreateProgressUI('Publishing...');
							
							// Grab the account_id from the DOM
							const currentAccId = document.getElementById('emlAccId').value;
							
							const pFd = new URLSearchParams({ 
								myCloud_action: 'email_publish_keyserver', 
								myCloud_key: myCloudState.key, 
								myCloud_token: window.myCloudCsrfToken, 
								pgp_public_key: publicKey,
								account_id: currentAccId // Pass the ID, let the server do the lookup
							});
							
							fetch('', { method: 'POST', body: pFd }).then(r=>r.json()).then(pRes => {
								myCloudCloseProgressUI();
								if (pRes.status === 'OK') myCloudShowAlert('Success', L.pgp_publish_success || 'Key submitted! Please check your email to verify it.');
								else myCloudShowAlert('Error', pRes.msg || L.pgp_publish_err || 'Failed to publish key.');
							});
						});
                    } catch (e) {
                        myCloudCloseProgressUI();
                        myCloudShowAlert(L.error_prefix || 'Error', e.message);
                    }
                }, 100);
            };

            document.getElementById('pgpGenSubmit').onclick = submitPrompt;
            input.onkeydown = (e) => {
                if (e.key === 'Enter') { e.preventDefault(); submitPrompt(); }
                if (e.key === 'Escape') { e.preventDefault(); closePrompt(); }
            };
            if (typeof myCloudApplyTheme === 'function') myCloudApplyTheme();
            };

            if (existingKey) {
                myCloudShowAlert(L.warning || 'Warning', L.pgp_overwrite_warn || 'A PGP key is already present. Generating a new key pair will replace the existing one. You will permanently lose the ability to read emails encrypted with the old key unless it is backed up. Continue?', doGenerate);
            } else {
                doGenerate();
            }
        };
		
		window._addAccAlias = (al = null) => {
            const container = document.getElementById('emlAccAliasesContainer');
            const uid = 'alias_' + Math.random().toString(36).substr(2, 9);
            const alEmail = al ? (typeof al === 'string' ? al : al.email) : '';
            const alName = al && typeof al === 'object' ? (al.sender_name || '') : '';
            const alSig = al && typeof al === 'object' ? (al.signature || '') : '';

            const row = document.createElement('div');
            row.className = 'ce-dynamic-row alias-row';
            row.style.cssText = 'display:flex; flex-wrap:wrap; gap:8px; padding:10px; background:var(--gray-15); border:1px solid var(--border-default); border-radius:4px; align-items:center;';
            row.innerHTML = 
                '<input type="email" class="myCloudInlineInput alias-email" placeholder="' + (L.email_address || 'Email') + '" value="' + esc(alEmail) + '" style="flex:1; min-width:150px; margin:0;">' +
                '<input type="text" class="myCloudInlineInput alias-name" placeholder="' + (L.sender_name || 'Sender Name (Inherit Default)') + '" value="' + esc(alName) + '" style="flex:1; min-width:150px; margin:0;">' +
                '<input type="hidden" class="alias-sig" id="' + uid + '" value="' + esc(alSig) + '">' +
                '<button type="button" class="owa-btn" onclick="window._emailOpenSignatureEditor(\'' + uid + '\')" title="' + (L.edit_sig || 'Edit Signature') + '" style="padding:6px 12px; font-size:12px; white-space:nowrap;"><span class="owa-icon"><svg viewBox="0 0 24 24"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg></span> <span style="margin-left:4px;">' + (L.signature || 'Signature') + '</span></button>' +
                '<button type="button" class="owa-btn owa-danger" onclick="this.parentElement.remove()" style="padding:6px 12px; margin-inline-start:auto;">✕</button>';
           container.appendChild(row);
        };

        setTimeout(() => {
            const aliasContainer = document.getElementById('emlAccAliasesContainer');
            if (aliasContainer) valAliases.forEach(al => window._addAccAlias(al));
        }, 0);

        window._emailOpenSignatureEditor = function(targetId = 'emlAccSignatureVal') {
            const currentSig = document.getElementById(targetId).value;

            const subOverlay = document.createElement('div');
            subOverlay.className = 'myCloudOverlay';
            subOverlay.style.display = 'flex';
            subOverlay.style.zIndex = '100010';

            const subModal = document.createElement('div');
            subModal.className = 'myCloudModal';
            subModal.style.width = '800px';
            subModal.style.maxWidth = '95vw';
            subModal.style.display = 'flex';
            subModal.style.flexDirection = 'column';

            subModal.innerHTML =
                '<div class="myCloudModalHeader" style="justify-content:space-between;"><span>' + myCloudSvgLogo + ' ' + (L.edit_sig || 'Edit Signature') + '</span><button id="sigCloseXBtn" class="myCloudClose">✕</button></div>' +
                '<style>#sigEditorWrap .sun-editor{height:100%!important;display:flex!important;flex-direction:column!important;border:none!important;background:transparent!important;} #sigEditorWrap .sun-editor .se-container{flex:1!important;display:flex!important;flex-direction:column!important;} #sigEditorWrap .sun-editor .se-wrapper{flex:1!important;height:auto!important;} #sigEditorWrap .sun-editor .sun-editor-editable{height:100%!important;}</style>' +
                '<div class="myCloudModalBody" id="sigEditorWrap" style="padding:0; flex:1; display:flex; flex-direction:column; min-height:400px;">' +
                    '<textarea id="emlSigEditorArea" style="width:100%; height:100%; display:none;"></textarea>' +
                '</div>' +
                '<div style="display:flex; justify-content:flex-end; padding:15px 20px; background:var(--gray-10); border-top:1px solid var(--border-default); margin:0;">' +
                    '<button id="sigSaveBtn" class="owa-btn owa-primary">' + (L.save || 'Save') + '</button>' +
                '</div>';

            subOverlay.appendChild(subModal);
            document.body.appendChild(subOverlay);

            let sigEditorInstance = null;
            if (typeof myCloudLoadEmailEditor === 'function') {
                myCloudLoadEmailEditor().then(() => {
                    const sunEditorGlobal = window.SUNEDITOR || window.suneditor;
                    sigEditorInstance = sunEditorGlobal.create('emlSigEditorArea', {
                        width: '100%', height: '100%', minHeight: '300px',
                        buttonList: [ ['undo', 'redo'], ['font', 'fontSize', 'formatBlock'], ['bold', 'underline', 'italic', 'strike', 'subscript', 'superscript'], ['fontColor', 'hiliteColor'], ['removeFormat'], ['outdent', 'indent'], ['align', 'horizontalRule', 'list', 'lineHeight'], ['link', 'image', 'table'] ],
                        defaultStyle: 'font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #333333;'
                    });
                    sigEditorInstance.setContents(currentSig);
                });
            }

            document.getElementById('sigCloseXBtn').onclick = () => {
                if (sigEditorInstance) sigEditorInstance.destroy();
                subOverlay.remove();
            };
            document.getElementById('sigSaveBtn').onclick = () => {
                if (sigEditorInstance) document.getElementById(targetId).value = sigEditorInstance.getContents();
                if (sigEditorInstance) sigEditorInstance.destroy();
                subOverlay.remove();
            };
        };

        setTimeout(() => {
            const cleanUri = window.myCloudOAuthDomain || (window.location.origin + window.location.pathname);
            const disp = document.getElementById('emlOauthRedirectDisp'); 
			if (disp) disp.textContent = cleanUri;
        }, 10);

        const testBtn = document.getElementById('emlAccTestBtn');
        if (testBtn) {
            testBtn.onclick = () => {
                testBtn.disabled = true; testBtn.textContent = 'Testing...';
                const fd = new URLSearchParams();
                fd.append('myCloud_action', 'email_test_connection');
                fd.append('myCloud_key', myCloudState.key);
                fd.append('myCloud_token', myCloudCsrfToken);
                fd.append('account_id', document.getElementById('emlAccId').value);
                fd.append('email', document.getElementById('emlAccEmail').value);
                fd.append('login_user', document.getElementById('emlAccLoginUser').value);
                fd.append('auth_type', document.getElementById('emlAccAuthType').value);
                fd.append('password', document.getElementById('emlAccPwd') ? document.getElementById('emlAccPwd').value : '');
                
                if (document.getElementById('emlAccImap')) {
                    fd.append('server_type', document.getElementById('emlAccServerType').value);
                    fd.append('eas_host', document.getElementById('emlAccEasHost').value.trim());
                    let imapHost = document.getElementById('emlAccImap').value.trim();
                    let imapPort = document.getElementById('emlAccImapPort').value.trim();
                    let imapEnc = document.getElementById('emlAccImapEnc').value;
                    if (imapHost.toLowerCase() === 'localhost' || imapHost === '127.0.0.1') { imapHost = '127.0.0.1'; imapPort = '143'; imapEnc = 'none'; }
                    fd.append('imap_host', imapHost); fd.append('imap_port', imapPort); fd.append('imap_enc', imapEnc);
                }
                
                fetch('', { method: 'POST', body: fd }).then(r=>r.json()).then(res => {
                    testBtn.disabled = false; testBtn.textContent = (L.test_connection || 'Test Connection');
                    if (res.status === 'OK') myCloudShowAlert('Success', res.msg);
                    else myCloudShowAlert('Error', res.msg);
                }).catch(() => { testBtn.disabled = false; testBtn.textContent = (L.test_connection || 'Test Connection'); myCloudShowAlert('Error', 'Network Error'); });
            };
        }

        window._emlStartOauthFlow = function() {
            const accId = document.getElementById('emlAccId').value;
            const statusEl = document.getElementById('oauthStatus');
            
            statusEl.innerHTML = '<span style="color:var(--text-secondary);">Saving configuration...</span>';
            
            const btnSave = document.getElementById('emlAccSaveBtn');
            const originalSaveLogic = btnSave.onclick;
            btnSave.onclick = null; // Detach UI close logic
            
            // Trick the UI into saving the record but retaining the window to wait for OAuth popup
            const fd = new URLSearchParams();
            fd.append('myCloud_action', 'email_save_account');
            fd.append('myCloud_key', myCloudState.key);
            fd.append('myCloud_token', myCloudCsrfToken);
            fd.append('account_id', accId);
            fd.append('email', document.getElementById('emlAccEmail').value);
            fd.append('login_user', document.getElementById('emlAccLoginUser').value);
            fd.append('auth_type', 'oauth2');
            
            fetch('', { method: 'POST', body: fd }).then(r=>r.json()).then(res => {
                btnSave.onclick = originalSaveLogic;
                if (res.status === 'OK') {
                    const finalAccId = res.account_id || accId;
                    if (!document.getElementById('emlAccId').value) document.getElementById('emlAccId').value = finalAccId;
                    statusEl.innerHTML = '<span style="color:var(--text-secondary);">Contacting Microsoft...</span>';
                    
                    // MUST MATCH AZURE EXACTLY: Clean base URL
                    const cleanUri = window.myCloudOAuthDomain || (window.location.origin + window.location.pathname);
                    const initFd = new URLSearchParams({ myCloud_action: 'email_oauth_init', account_id: finalAccId, redirect_uri: cleanUri, myCloud_key: myCloudState.key, myCloud_token: myCloudCsrfToken });
                    
                    fetch('', { method: 'POST', body: initFd }).then(r=>r.json()).then(initRes => {
                        if (initRes.status === 'OK' && initRes.url) {
                            statusEl.innerHTML = '<span style="color:var(--text-secondary);">Waiting for authorization window...</span>';
                            const win = window.open(initRes.url, 'oauth', 'width=600,height=700');
                            
                            let isResolved = false;
                            const handler = function(e) {
                                // Listen specifically for the oauth_code pushed from core.bootstrap.php
                                if (e.data && e.data.type === 'oauth_code') {
                                    isResolved = true;
                                    window.removeEventListener('message', handler);
                                    statusEl.innerHTML = '<span style="color:var(--text-secondary);">Exchanging token securely...</span>';
                                    
                                    const cbFd = new URLSearchParams();
                                    cbFd.append('myCloud_action', 'email_oauth_callback');
                                    cbFd.append('myCloud_key', myCloudState.key);
                                    cbFd.append('myCloud_token', myCloudCsrfToken);
                                    cbFd.append('account_id', e.data.state.acc_id);
                                    cbFd.append('code', e.data.code);
                                    cbFd.append('redirect_uri', cleanUri);
                                    
                                    // Make the call entirely inside the authenticated main window
                                    fetch(window.location.origin + window.location.pathname, { method: 'POST', body: cbFd })
                                    .then(r => r.json()).then(cbRes => {
                                        if (cbRes.status === 'OK') {
                                            statusEl.innerHTML = '<b style="color:var(--success);">✓ Authorized Successfully!</b>';
                                            myCloudEmailLoadAccounts().then(() => myCloudShowEmailSettings());
                                        } else {
                                            statusEl.innerHTML = '<b style="color:var(--danger);">✕ Auth Failed: ' + myCloudEscapeHtml(cbRes.msg) + '</b>';
                                        }
                                    }).catch(() => { statusEl.innerHTML = '<b style="color:var(--danger);">✕ Network Error during token exchange.</b>'; });
                                }
                            };
                            window.addEventListener('message', handler);

                            const watchdog = setInterval(() => {
                                if (isResolved) { clearInterval(watchdog); return; }
                                if (win && win.closed) {
                                    clearInterval(watchdog);
                                    window.removeEventListener('message', handler);
                                    statusEl.innerHTML = '<b style="color:var(--danger);">✕ Auth Window Closed.</b>';
                                }
                            }, 500);
                        } else statusEl.innerHTML = '<span style="color:var(--danger);">Failed to initialize flow.</span>';
                    });
                } else statusEl.innerHTML = '<span style="color:var(--danger);">Failed to save config.</span>';
            });
        };

        // --- Auto-Port Switching Logic ---
        if (!hideForeign && canEditSettings) {
            const imapEncDrop = document.getElementById('emlAccImapEnc');
            const imapPortInp = document.getElementById('emlAccImapPort');
            imapEncDrop.onchange = function(e) {
                if (e.target.value === 'none') {
                    if (imapPortInp.value === '993' || imapPortInp.value === '') imapPortInp.value = '143';
                } else {
                    if (imapPortInp.value === '143' || imapPortInp.value === '') imapPortInp.value = '993';
                }
            };

            const smtpEncDrop = document.getElementById('emlAccSmtpEnc');
            const smtpPortInp = document.getElementById('emlAccSmtpPort');
            smtpEncDrop.onchange = function(e) {
                if (e.target.value === 'none') {
                    if (smtpPortInp.value === '465' || smtpPortInp.value === '587' || smtpPortInp.value === '') smtpPortInp.value = '25';
                } else if (e.target.value === 'tls') {
                    if (smtpPortInp.value === '465' || smtpPortInp.value === '25' || smtpPortInp.value === '') smtpPortInp.value = '587';
                } else {
                    if (smtpPortInp.value === '587' || smtpPortInp.value === '25' || smtpPortInp.value === '') smtpPortInp.value = '465';
                }
            };
        }

        // --- Save Logic ---
        document.getElementById('emlAccSaveBtn').onclick = () => {
            const btn = document.getElementById('emlAccSaveBtn');
            btn.disabled = true;
            btn.textContent = (L.saving || 'Saving...');

            const fd = new URLSearchParams();
            fd.append('myCloud_action', 'email_save_account');
            fd.append('myCloud_key', myCloudState.key);
            fd.append('myCloud_token', myCloudCsrfToken);
            
            // Extract safely
            fd.append('account_id', document.getElementById('emlAccId').value);
            fd.append('name', document.getElementById('emlAccName').value);
            fd.append('sender_name', document.getElementById('emlAccSenderName').value);
            fd.append('email', document.getElementById('emlAccEmail').value);
            fd.append('login_user', document.getElementById('emlAccLoginUser').value);
			fd.append('auth_type', document.getElementById('emlAccAuthType').value);

            const finalAliases = [];
            document.querySelectorAll('#emlAccAliasesContainer .alias-row').forEach(row => {
                const email = row.querySelector('.alias-email').value.trim();
                const name = row.querySelector('.alias-name').value.trim();
                const sig = row.querySelector('.alias-sig').value.trim();
                if (email) finalAliases.push({ email: email, sender_name: name, signature: sig });
            });
            fd.append('aliases', JSON.stringify(finalAliases));

            fd.append('signature', document.getElementById('emlAccSignatureVal').value);
            if (document.getElementById('emlAccImap')) {
                fd.append('server_type', document.getElementById('emlAccServerType').value);
                fd.append('eas_host', document.getElementById('emlAccEasHost').value.trim());
                let imapHost = document.getElementById('emlAccImap').value.trim();
                let imapPort = document.getElementById('emlAccImapPort').value.trim();
                let imapEnc = document.getElementById('emlAccImapEnc').value;
                let smtpHost = document.getElementById('emlAccSmtp').value.trim();
                let smtpPort = document.getElementById('emlAccSmtpPort').value.trim();
                let smtpEnc = document.getElementById('emlAccSmtpEnc').value;

                const canEditSettings = window.myCloudActionAllowed('email_settings');
                const hideForeign = window.myCloudMailOnlyLocalhost === true || !window.myCloudActionAllowed('email_add_foreign_servers');
                const isHiddenFields = hideForeign || !canEditSettings;

                if (isHiddenFields) {
                    if (imapHost === '') {
                        imapHost = '127.0.0.1'; imapPort = '143'; imapEnc = 'none';
                    }
                    if (smtpHost === '') {
                        smtpHost = '127.0.0.1'; smtpPort = '25'; smtpEnc = 'none';
                    }
                }

                if (imapHost.toLowerCase() === 'localhost' || imapHost === '127.0.0.1') {
                    imapHost = '127.0.0.1'; imapPort = '143'; imapEnc = 'none';
                }

                if (smtpHost.toLowerCase() === 'localhost' || smtpHost === '127.0.0.1') {
                    smtpHost = '127.0.0.1'; smtpPort = '25'; smtpEnc = 'none';
                }

                fd.append('imap_host', imapHost);
                fd.append('imap_port', imapPort);
                fd.append('imap_enc', imapEnc);
                fd.append('smtp_host', smtpHost);
                fd.append('smtp_port', smtpPort);
                fd.append('smtp_enc', smtpEnc);
            }
            if (document.getElementById('emlAccPwd')) fd.append('password', document.getElementById('emlAccPwd').value);
            
            if (document.getElementById('emlAccPgpPub')) {
                fd.append('pgp_public_key', document.getElementById('emlAccPgpPub').value);
                fd.append('pgp_private_key', document.getElementById('emlAccPgpPriv').value);
            }

            fetch('', { method: 'POST', body: fd }).then(r => r.json()).then(res => {
                if (res.status === 'OK') {
                    if (res.oauth_url) {
                        // Automatically trigger OAuth2 flow
                        const win = window.open(res.oauth_url, 'oauth', 'width=600,height=700');
                        if (win) win.focus();
                    } else {
                        myCloudEmailLoadAccounts().then(() => {
                            myCloudShowEmailSettings(); 
                        });
                    }
                } else {
                    myCloudShowAlert(L.error_prefix || 'Error', res.msg || (L.failed_save_acc || "Failed to save account."));
                    btn.disabled = false;
                    btn.textContent = L.save || 'Save';
                }
            }).catch(() => {
                myCloudShowAlert(L.error_prefix || 'Error', (L.net_error || "Network error."));
                btn.disabled = false;
                btn.textContent = L.save || 'Save';
            });
        };
    };

    let listHtml = '<div class="myCloudModalHeader" style="justify-content:space-between;"><span>' + myCloudSvgLogo + ' ' + (L.email_settings || 'Email Settings') + '</span><button class="myCloudClose" onclick="myCloudCloseModal()">✕</button></div><div class="myCloudModalBody" style="padding:20px; overflow-y:auto; flex:1; min-height:0;">';

        let pwdInfoHtml = '';
        if (typeof myCloudHasAdvancedPwd !== 'undefined' && myCloudHasAdvancedPwd) {
            pwdInfoHtml = 
            '<div style="margin-bottom: 20px; padding: 15px; background: var(--hover-bg-light); border: 1px solid var(--border-default); border-radius: 4px; display: flex; flex-direction: column; gap: 10px;">' +
                '<div style="font-size: 13px; color: var(--text-primary); line-height: 1.5;">' + 
                    (L.pwd_email_info || 'To change your email account or cloud passwords, please use the central Password Manager.') + 
                '</div>' +
                '<div>' +
                    '<button type="button" onclick="event.preventDefault(); event.stopPropagation(); window.myCloudShowAdvancedPwdModal && window.myCloudShowAdvancedPwdModal()" style="margin:0; color:var(--accent-primary) !important; background:var(--gray-00) !important; border:1px solid var(--accent-primary); padding: 6px 12px; border-radius: 4px; font-size: 13px; cursor: pointer;">🔒 ' + 
                        (L.open_pwd_manager || 'Open Password Manager') + 
                    '</button>' +
                '</div>' +
            '</div>';
        }
        listHtml += pwdInfoHtml;
    
    const canEditSettings = window.myCloudActionAllowed('email_settings');

    // --- ALIAS MANAGER MODULE BUTTON ---
    if (typeof window.myCloudEmailAliasManager !== 'undefined' && window.myCloudActionAllowed('mailbox_administration')) {
        const btnTitle = L.alias_btn_title || 'Mailbox Server Addresses';
        const btnDesc = L.alias_btn_desc || 'Manage physical aliases directly on the mail server.';
        const btnAction = L.alias_btn_action || '⚙️ Manage Server Aliases';

        listHtml += '<div style="margin-bottom:20px; padding:15px; background:var(--hover-bg-light); border:1px solid var(--border-default); border-radius:4px; display:flex; flex-direction:column; gap:10px;">' +
                        '<div>' +
                            '<b style="color:var(--text-primary); font-size:14px; display:block; margin-bottom:4px;">' + esc(btnTitle) + '</b>' +
                            '<span style="color:var(--text-secondary); font-size:13px;">' + esc(btnDesc) + '</span>' +
                        '</div>' +
                        '<div>' +
                            '<button type="button" onclick="myCloudEmailAliasManager.open()" style="margin:0; color:var(--accent-primary) !important; background:var(--gray-00) !important; border:1px solid var(--accent-primary); padding: 6px 12px; border-radius: 4px; font-size: 13px; cursor: pointer;">' + esc(btnAction) + '</button>' +
                        '</div>' +
                    '</div>';
    }

	const accIds = Object.keys(myCloudEmailState.accounts);
    if (accIds.length === 0) {
        listHtml += '<div style="color:var(--text-secondary); margin-bottom:15px; text-align:center; padding: 20px;">' + (L.no_accs || 'No accounts configured.') + '</div>';
    } else {
        accIds.forEach((id, index) => {
            const acc = myCloudEmailState.accounts[id];
            const accName = acc.name || L.unnamed || 'Unnamed';
            const accEmail = acc.email || L.no_email || 'No email';
            const isInactive = acc.is_inactive;
            const toggleIcon = isInactive ? inactiveIcon : activeIcon;
            const toggleBtnHtml = canEditSettings ? '<button class="owa-btn" title="' + (isInactive ? 'Activate' : 'Deactivate') + '" style="padding:4px 8px; color: ' + (isInactive ? 'var(--text-disabled)' : 'var(--success)') + ';" onclick="window._emailToggleAccActive(\'' + id + '\', ' + !isInactive + ', this)">' + toggleIcon + '</button>' : '';
			const removeBtnHtml = canEditSettings ? '<button class="owa-btn owa-danger" title="' + (L.remove || 'Remove') + '" style="padding:4px 8px;" onclick="window._emailDelAcc(\'' + id + '\')">' + delIcon + '</button>' : '';
			
            let moveHtml = '';
            if (canEditSettings && accIds.length > 1) {
                const btnUp = index > 0 ? '<button class="owa-btn" title="Move Up" style="padding:4px 6px;" onclick="window._emailMoveAcc(\'' + id + '\', -1)">' + upIcon + '</button>' : '<button class="owa-btn" disabled style="padding:4px 6px; opacity:0.3">' + upIcon + '</button>';
                const btnDown = index < accIds.length - 1 ? '<button class="owa-btn" title="Move Down" style="padding:4px 6px;" onclick="window._emailMoveAcc(\'' + id + '\', 1)">' + downIcon + '</button>' : '<button class="owa-btn" disabled style="padding:4px 6px; opacity:0.3">' + downIcon + '</button>';
                moveHtml = '<div style="display:flex; gap:2px; margin-inline-end:10px;">' + btnUp + btnDown + '</div>';
            }

            listHtml += 
                '<div style="display:flex; justify-content:space-between; padding:10px 15px; background:var(--gray-10); margin-bottom:10px; border-radius:6px; border:1px solid var(--border-default); align-items:center; flex-wrap:nowrap; overflow:hidden;">' +
                    '<div style="flex:1; min-width:0; margin-inline-end:15px;"><b style="color:var(--text-primary); display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">' + esc(accName) + '</b><span style="font-size:12px; color:var(--text-secondary); display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">' + esc(accEmail) + '</span></div>' +
                    '<div style="display:flex; gap:6px; flex-shrink:0; align-items:center;">' +
                         moveHtml +
                         toggleBtnHtml +
						 '<button class="owa-btn" style="padding:4px 10px; font-size:13px;" onclick="window._emailEditAcc(\'' + id + '\')">' + editIcon + '</button>' +
                         removeBtnHtml +
                     '</div>' +
                 '</div>';

        });
    }

    if (canEditSettings) {
        listHtml += 
            '<div style="display:flex; justify-content:flex-end; padding-top:15px; border-top:1px solid var(--border-default); margin-top:10px;">' +
                '<button class="owa-btn owa-primary" onclick="window.myCloudEmailFirstRunAssistant()">' + addIcon + ' ' + (L.add_account || 'Add Account') + '</button>' +
            '</div>';
    }
    listHtml += '</div>';

    modal.innerHTML = listHtml;

    window._emailEditAcc = (id) => {
        // Javascript strict truthy check (handles the old corrupted "" empty keys gracefully)
        if (id !== null && id !== undefined && myCloudEmailState.accounts[id]) {
            renderForm(myCloudEmailState.accounts[id]);
        } else {
            renderForm(null);
        }
    };

    window._emailToggleAccActive = (id, inactiveState, btn) => {
        if (btn) {
            btn.disabled = true;
            btn.style.opacity = '0.5';
            btn.innerHTML = inactiveState ? inactiveIcon : activeIcon;
            btn.style.color = inactiveState ? 'var(--text-disabled)' : 'var(--success)';
        }
        const fd = new URLSearchParams();
        fd.append('myCloud_action', 'email_toggle_account_active');
        fd.append('myCloud_key', myCloudState.key);
        fd.append('myCloud_token', myCloudCsrfToken);
        fd.append('account_id', id);
        if (inactiveState) fd.append('is_inactive', '1');
        
        fetch('', { method: 'POST', body: fd }).then(r => r.json()).then(res => {
            if (res.status === 'OK') {
                if (inactiveState && myCloudEmailState.activeAccount === id) myCloudEmailState.activeAccount = null;
                myCloudEmailLoadAccounts().then(() => {
                    myCloudShowEmailSettings();
                    if (typeof myCloudEmailRenderTree === 'function') myCloudEmailRenderTree();
                });
            }
                else if (btn) {
                    btn.disabled = false;
                    btn.style.opacity = '1';
                    btn.innerHTML = inactiveState ? activeIcon : inactiveIcon;
                    btn.style.color = inactiveState ? 'var(--success)' : 'var(--text-disabled)';
                }
            }).catch(() => {
                if (btn) {
                    btn.disabled = false;
                    btn.style.opacity = '1';
                    btn.innerHTML = inactiveState ? activeIcon : inactiveIcon;
                    btn.style.color = inactiveState ? 'var(--success)' : 'var(--text-disabled)';
                }
        });
    };

    window._emailDelAcc = (id) => {
        myCloudShowAlert(L.delete || "Delete", L.remove_acc_confirm || "Remove this account?", () => {
            const fd = new URLSearchParams();
            fd.append('myCloud_action', 'email_delete_account');
            fd.append('myCloud_key', myCloudState.key);
            fd.append('myCloud_token', myCloudCsrfToken);
            fd.append('account_id', id);

            fetch('', { method: 'POST', body: fd }).then(r => r.json()).then(res => {
                if (res.status === 'OK') {
                    if (myCloudEmailState.activeAccount === id) myCloudEmailState.activeAccount = null;
                    myCloudEmailLoadAccounts().then(() => {
                        myCloudShowEmailSettings();
                    });
                }
            });
        });
    };
    window._emailMoveAcc = (id, dir) => {
        const keys = Object.keys(myCloudEmailState.accounts);
        const idx = keys.indexOf(id);
        if (idx < 0) return;
        const newIdx = idx + dir;
        if (newIdx < 0 || newIdx >= keys.length) return;
        
        const temp = keys[idx];
        keys[idx] = keys[newIdx];
        keys[newIdx] = temp;
        
        const newAccs = {};
        keys.forEach(k => newAccs[k] = myCloudEmailState.accounts[k]);
        myCloudEmailState.accounts = newAccs;
        myCloudShowEmailSettings();

        const fd = new URLSearchParams({ 
            myCloud_action: 'email_reorder_accounts', 
            myCloud_key: myCloudState.key, 
            myCloud_token: myCloudCsrfToken,
            order: JSON.stringify(keys)
        });
        fetch('', { method: 'POST', body: fd }).then(r => r.json()).then(res => {
            if (res.status === 'OK') {
                if (typeof myCloudEmailRenderTree === 'function') myCloudEmailRenderTree();
            } else {
                myCloudShowAlert(L.error_prefix || 'Error', res.msg || 'Failed to reorder.');
            }
        });
    };

    window._emlExportKey = async (type) => {
        const elId = type === 'public' ? 'emlAccPgpPub' : 'emlAccPgpPriv';
        let keyData = document.getElementById(elId) ? document.getElementById(elId).value : '';
        
        if (type === 'private') {
            const pubData = document.getElementById('emlAccPgpPub') ? document.getElementById('emlAccPgpPub').value : '';
            if (pubData && keyData.trim() !== '' && !keyData.includes('PUBLIC KEY BLOCK')) {
                keyData = keyData.trim() + '\n\n' + pubData.trim();
            }
        }
        
        if (!keyData || keyData.trim() === '') {
            myCloudShowAlert(L.error_prefix || 'Error', 'No key found to export.');
            return;
        }
        
        const fileName = type === 'public' ? 'pgp_public_key.asc' : 'pgp_keypair_backup.asc';
        const fileContent = keyData.trim();

        try {
            // Modern approach: Native OS "Save As" Dialog
            if (window.showSaveFilePicker) {
                const handle = await window.showSaveFilePicker({
                    suggestedName: fileName,
                    types: [{ description: 'PGP Key File', accept: { 'text/plain': ['.asc', '.txt'] } }],
                });
                const writable = await handle.createWritable();
                await writable.write(fileContent);
                await writable.close();
                return;
            }
        } catch (err) {
            if (err.name === 'AbortError') return; // User clicked "Cancel" in dialog
            console.warn('FilePicker API failed, falling back to Blob method', err);
        }

        // Legacy Fallback for Firefox/Older Safari
        const blob = new Blob([fileContent], { type: 'text/plain' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.style.display = 'none';
        a.href = url;
        a.download = fileName;
        document.body.appendChild(a);
        a.click();
        setTimeout(() => {
            if (document.body.contains(a)) document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
       }, 150);
    };

        window._emlImportPgpKeys = () => {
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = '.asc,.txt,.pgp,.key';
            input.multiple = true;
            input.onchange = e => {
                Array.from(e.target.files).forEach(file => {
                    const reader = new FileReader();
                    reader.onload = (event) => {
                        const content = event.target.result;
                        if (content.includes('PUBLIC KEY BLOCK')) {
                            const pubEl = document.getElementById('emlAccPgpPub');
                            if (pubEl) pubEl.value = content;
                        }
                        if (content.includes('PRIVATE KEY BLOCK')) {
                            const privEl = document.getElementById('emlAccPgpPriv');
                            if (privEl) privEl.value = content;
                        }
                    };
                    reader.readAsText(file);
                });
            };
            input.click();
        };
	
        window._emlPromptKeyAction = function(actionType) {
            const pubKey = document.getElementById('emlAccPgpPub') ? document.getElementById('emlAccPgpPub').value.trim() : '';
            if (actionType === 'publish' && !pubKey) {
                return myCloudShowAlert(L.error_prefix || 'Error', L.pgp_no_pub_key || 'No public key found. Please generate or import one.');
            }

            const emails = [];
            const mainEmail = document.getElementById('emlAccEmail').value.trim();
            if (mainEmail) emails.push(mainEmail);
            document.querySelectorAll('#emlAccAliasesContainer .alias-email').forEach(el => {
                const val = el.value.trim();
                if (val && !emails.includes(val)) emails.push(val);
            });

            if (emails.length === 0) return myCloudShowAlert(L.error_prefix || 'Error', 'No email addresses configured.');

            const subOverlay = document.createElement('div');
            subOverlay.className = 'myCloudOverlay';
            subOverlay.style.display = 'flex';
            subOverlay.style.zIndex = '100010';

            const subModal = document.createElement('div');
            subModal.className = 'myCloudModal';
            subModal.style.maxWidth = '400px';
            subModal.style.width = '100%';

            const isPublish = actionType === 'publish';
            const title = isPublish ? 'Publish Local Key' : 'Remove Local Key';
            const btnText = isPublish ? 'Publish' : 'Remove';
            const btnClass = isPublish ? 'owa-primary' : 'owa-danger';
            const descText = isPublish 
                ? 'Select the email addresses to publish this key for in the local directory:' 
                : 'Select the email addresses to remove this key for from the local directory:';

            let checkboxesHtml = emails.map(e => 
                '<label style="display:flex; align-items:center; gap:10px; margin-bottom:10px; cursor:pointer; padding:8px; background:var(--gray-05); border:1px solid var(--border-medium); border-radius:4px;">' +
                '<input type="checkbox" class="pgp-target-email myCloudCheckbox" value="' + esc(e) + '" checked style="margin:0;">' +
                '<span style="font-size:13px; font-weight:500; color:var(--text-primary);">' + esc(e) + '</span>' +
                '</label>'
            ).join('');

            subModal.innerHTML = 
                '<div class="myCloudModalHeader" style="justify-content:space-between;"><span>' + (typeof myCloudSvgLogo !== 'undefined' ? myCloudSvgLogo : '') + ' ' + title + '</span><button class="myCloudClose" id="pgpActionClose">✕</button></div>' +
                '<div class="myCloudModalBody" style="padding:20px;">' +
                    '<div style="font-size:13px; color:var(--text-secondary); margin-bottom:15px; line-height:1.4;">' + descText + '</div>' +
                    '<div style="max-height:200px; overflow-y:auto; margin-bottom:20px;">' + checkboxesHtml + '</div>' +
                    '<div class="myCloudButtons" style="justify-content:flex-end; flex-wrap:wrap; gap:10px; margin:0;">' +
                        '<button type="button" id="pgpActionCancel" class="owa-btn" style="margin:0;">' + (L.cancel || 'Cancel') + '</button>' +
                        '<button type="button" id="pgpActionSubmit" class="owa-btn ' + btnClass + '" style="margin:0;">' + btnText + '</button>' +
                    '</div>' +
                '</div>';

            subOverlay.appendChild(subModal);
            document.body.appendChild(subOverlay);

            const closePrompt = () => { subOverlay.remove(); };
            document.getElementById('pgpActionClose').onclick = closePrompt;
            document.getElementById('pgpActionCancel').onclick = closePrompt;

            document.getElementById('pgpActionSubmit').onclick = () => {
                const selected = Array.from(subModal.querySelectorAll('.pgp-target-email:checked')).map(cb => cb.value);
                if (selected.length === 0) return myCloudShowAlert('Error', 'Please select at least one email address.');
                closePrompt();

                myCloudCreateProgressUI(isPublish ? (L.publishing || 'Publishing...') : 'Removing...');
                const fd = new URLSearchParams({
                    myCloud_action: isPublish ? 'email_publish_local_keyserver' : 'email_unpublish_local_keyserver',
                    myCloud_key: typeof myCloudState !== 'undefined' ? myCloudState.key : '',
                    myCloud_token: window.myCloudCsrfToken,
                    account_id: document.getElementById('emlAccId').value,
                    selected_emails: JSON.stringify(selected)
                });
                if (isPublish) fd.append('pgp_public_key', pubKey);

                fetch('', { method: 'POST', body: fd }).then(r=>r.json()).then(res => {
                    myCloudCloseProgressUI();
                    if (res.status === 'OK') myCloudShowAlert('Success', res.msg);
                    else myCloudShowAlert('Error', res.msg);
                }).catch(() => { myCloudCloseProgressUI(); myCloudShowAlert('Error', 'Network Error'); });
            };
        };

        window._emlPublishLocalKey = function() { window._emlPromptKeyAction('publish'); };
        window._emlUnpublishLocalKey = function() { window._emlPromptKeyAction('unpublish'); };
	
};

// --- FIRST RUN ASSISTANT ENGINE ---
    window.myCloudEmailFirstRunAssistant = function() {
        const overlay = document.getElementById('myCloudModalOverlay');
        const modal = document.getElementById('myCloudModal');
        if (typeof myCloudResetModal === 'function') myCloudResetModal();

        overlay.style.display = 'flex';
        modal.className = 'myCloudModal';
        modal.style.width = '520px';
        modal.style.maxWidth = '95vw';
        
        const L = typeof myCloud_LANG !== 'undefined' ? myCloud_LANG : {};
        const canEditSettings = typeof window.myCloudActionAllowed === 'function' ? window.myCloudActionAllowed('email_settings') : true;
        const hideForeign = window.myCloudMailOnlyLocalhost === true || (typeof window.myCloudActionAllowed === 'function' && !window.myCloudActionAllowed('email_add_foreign_servers'));
        const isHiddenFields = hideForeign || !canEditSettings;
        const canImportExport = typeof window.myCloudActionAllowed === 'function' ? window.myCloudActionAllowed('email_import_contacts') : true;

		const esc = function(str) { return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); };
		const isInitialSetup = !window.myCloudEmailState || !window.myCloudEmailState.accounts || Object.keys(window.myCloudEmailState.accounts).length === 0;

        if (!window._toggleEmlPwd) {
            window._toggleEmlPwd = function(btn, inputId = 'emlAccPwd') {
                const input = document.getElementById(inputId);
                if (!input) return;
                const svg = btn.querySelector('svg');
                if (input.type === 'password') {
                    input.type = 'text';
                    btn.style.color = 'var(--accent-primary)';
                    svg.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>';
                } else {
                    input.type = 'password';
                    btn.style.color = 'var(--text-secondary)';
                    svg.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>';
                }
            };
        }

        // Dynamically build required steps based on permissions
        const steps = ['basic', 'login'];
        if (!isHiddenFields) steps.push('server');
        steps.push('aliases', 'signatures', 'pgp');
        if (canImportExport && isInitialSetup) steps.push('import');

        let currentStepIndex = 0;
        
        // Comprehensive State Management
        window._fraTempData = { 
            accName: '', name: '', email: '', username: '', password: '', 
            authType: 'basic', serverType: 'imap', easH: '',
			imapH: '', imapP: '993', imapE: 'ssl', 
            smtpH: '', smtpP: '465', smtpE: 'ssl',
            pgpPub: '', pgpPriv: '',
			aliases: [],
            mainSig: '',
            aliasSigs: {},
            diffAliasSigs: false,
            activeAliasSig: '',
            importFile: null
        };

        const renderStep = () => {
            const stepId = steps[currentStepIndex];
            const isLastStep = currentStepIndex === steps.length - 1;
            let html = '';
            
            // Header & Progress Bar
            html += '<div class="myCloudModalHeader" style="border-bottom:none; flex-direction:column; align-items:flex-start; padding-bottom:10px;">' +
                        '<div style="display:flex; justify-content:space-between; width:100%; align-items:center;">' +
                            '<h2 style="margin:0; font-size:18px; display:flex; align-items:center; gap:8px;">' + (typeof myCloudSvgLogo !== 'undefined' ? myCloudSvgLogo : '✉️') + ' ' + (L.mail_fra_setup_wizard || 'Mailbox Setup Assistant') + '</h2>' +
                            '<span style="color:var(--text-secondary); font-size:12px; font-weight:bold;">' + (L.mail_fra_step || 'Step') + ' ' + (currentStepIndex + 1) + ' ' + (L.mail_fra_of || 'of') + ' ' + steps.length + '</span>' +
                        '</div>' +
                        '<div style="width:100%; background:var(--gray-10); height:4px; border-radius:2px; margin-top:15px; overflow:hidden;">' +
                            '<div style="height:100%; background:var(--accent-primary); width:' + (((currentStepIndex + 1) / steps.length) * 100) + '%; transition:width 0.3s ease;"></div>' +
                        '</div>' +
                    '</div>';

            html += '<div class="myCloudModalBody" style="padding: 10px 30px 30px 30px;">';

            // --- STEP 1: BASIC INFO ---
            if (stepId === 'basic') {
                html += 
                    '<h3 style="margin:0 0 5px 0;">' + (L.mail_fra_basic_info || 'Basic Information') + '</h3>' +
                    '<p style="color:var(--text-secondary); font-size:13px; margin:0 0 20px 0;">' + (L.mail_fra_basic_info_desc || 'What is your email address, and how should this mailbox be identified?') + '</p>' +
                    '<label style="font-size:12px; color:var(--text-secondary); font-weight:bold; display:block; margin-bottom:4px;">' + (L.mail_fra_account_name || 'Mailbox Display Name (e.g. Work, Personal)') + ' *</label>' +
                    '<input type="text" id="fraAccName" class="myCloudInlineInput" value="' + window._fraTempData.accName + '" placeholder="' + (L.mail_fra_account_name_ph || 'My Mailbox') + '" style="margin-bottom:15px; width:100%;">' +
                    '<label style="font-size:12px; color:var(--text-secondary); font-weight:bold; display:block; margin-bottom:4px;">' + (L.mail_fra_email_address || 'Email Address') + ' *</label>' +
                    '<input type="email" id="fraAccEmail" class="myCloudInlineInput" value="' + window._fraTempData.email + '" placeholder="name@example.com" style="margin-bottom:15px; width:100%;">' +
                    '<label style="font-size:12px; color:var(--text-secondary); font-weight:bold; display:block; margin-bottom:4px;">' + (L.mail_fra_sender_name || 'Your Name (Public display for recipients)') + ' *</label>' +
                    '<input type="text" id="fraAccSenderName" class="myCloudInlineInput" value="' + window._fraTempData.name + '" placeholder="' + (L.mail_fra_sender_name_ph || 'e.g. John Doe') + '" style="margin-bottom:15px; width:100%;">';
            } 
            
            // --- STEP 2: LOGIN CREDENTIALS ---
            else if (stepId === 'login') {
                html += 
                    '<h3 style="margin:0 0 5px 0;">' + (L.mail_fra_login_credentials || 'Login Credentials') + '</h3>' +
                    '<p style="color:var(--text-secondary); font-size:13px; margin:0 0 20px 0;">' + (L.mail_fra_login_desc || 'Please enter the specific Username and Password required by your mail server. If you are unsure what your username is, please check your provider\'s documentation.') + '</p>' +
                    '<label style="font-size:12px; color:var(--text-secondary); font-weight:bold; display:block; margin-bottom:4px;">' + (L.mail_fra_username || 'Username') + ' *</label>' +
                    '<input type="text" id="fraAccUsername" autocomplete="off" class="myCloudInlineInput" value="' + window._fraTempData.username + '" placeholder="' + window._fraTempData.email + '" style="margin-bottom:15px; width:100%;">' +
                    (isHiddenFields ? '<input type="hidden" id="fraAccAuthType" value="' + esc(window._fraTempData.authType) + '">' : 
                    '<label style="font-size:12px; color:var(--text-secondary); font-weight:bold; display:block; margin-bottom:4px;">' + (L.auth_type || 'Authentication Type') + '</label>' +
                    '<select id="fraAccAuthType" class="myCloudInlineInput" style="margin-bottom:15px; width:100%;" onchange="document.getElementById(\'fraAuthBasicGrp\').style.display = this.value === \'basic\' ? \'block\' : \'none\'; document.getElementById(\'fraAuthOauthGrp\').style.display = this.value === \'oauth2\' ? \'block\' : \'none\';">' +
                        '<option value="basic" ' + (window._fraTempData.authType === 'basic' ? 'selected' : '') + '>Basic (Password / App Password)</option>' +
                        '<option value="oauth2" ' + (window._fraTempData.authType === 'oauth2' ? 'selected' : '') + '>OAuth2 (Outlook / Office 365)</option>' +
                    '</select>') +
                    '<div id="fraAuthBasicGrp" style="display:' + (window._fraTempData.authType === 'basic' || isHiddenFields ? 'block' : 'none') + ';">' +
                        '<label style="font-size:12px; color:var(--text-secondary); font-weight:bold; display:block; margin-bottom:4px;">' + (L.mail_fra_password || 'Password') + ' *</label>' +
                        '<div style="position:relative; display:flex; align-items:center; width:100%; margin-bottom:15px;">' +
                            '<input type="password" id="fraAccPwd" autocomplete="new-password" class="myCloudInlineInput" value="' + window._fraTempData.password + '" style="width:100%; padding-inline-end:32px; margin:0;">' +
                            '<button type="button" tabindex="-1" onclick="window._toggleEmlPwd(this, \'fraAccPwd\')" style="position:absolute; inset-inline-end:4px; background:none; border:none; cursor:pointer; color:var(--text-secondary); padding:4px;" title="' + (L.toggle_pwd_vis || 'Toggle visibility') + '">' +
                                '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px; height:16px;"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>' +
                            '</button>' +
                        '</div>' +
                    '</div>' +
                    '<div id="fraAuthOauthGrp" style="display:' + (window._fraTempData.authType === 'oauth2' ? 'block' : 'none') + '; padding:15px; background:var(--gray-05); border:1px solid var(--border-default); border-radius:4px; margin-bottom:15px;">' +
                        '<div style="font-size:12px; color:var(--text-secondary); margin-bottom:0;"><b>Redirect URI required in Azure:</b><br><span style="user-select:all; background:var(--gray-15); padding:3px 6px; border-radius:3px; display:inline-block; margin-top:4px;" id="fraOauthRedirectDisp"></span></div>' +
                    '</div>';
            } 
            
            // --- STEP 3: SERVER CONFIG ---
            else if (stepId === 'server') {
                html += 
                    '<h3 style="margin:0 0 5px 0;">' + (L.mail_fra_server_settings || 'Server Details') + '</h3>' +
                    '<p style="color:var(--text-secondary); font-size:13px; margin:0 0 20px 0;">' + (L.mail_fra_server_desc || 'Specify your incoming (IMAP) and outgoing (SMTP) mail server configurations.') + '</p>' +
                    '<label style="font-size:12px; color:var(--text-secondary); font-weight:bold; display:block; margin-bottom:4px;">' + (L.server_type || 'Server Type') + '</label>' +
                    '<select id="fraAccServerType" class="myCloudInlineInput" style="width:100%; margin:0; margin-bottom:15px; height:34px;" onchange="document.getElementById(\'fraImapGroup\').style.display = this.value === \'imap\' ? \'block\' : \'none\'; document.getElementById(\'fraEasGroup\').style.display = this.value === \'eas\' ? \'block\' : \'none\';">' +
                        '<option value="imap" ' + (window._fraTempData.serverType === 'imap' ? 'selected' : '') + '>IMAP / SMTP</option>' +
                        '<option value="eas" ' + (window._fraTempData.serverType === 'eas' ? 'selected' : '') + '>Exchange ActiveSync (EAS)</option>' +
                    '</select>' +
                    '<div id="fraImapGroup" style="display:' + ((!window._fraTempData.serverType || window._fraTempData.serverType === 'imap') ? 'block' : 'none') + ';">' +
                    '<div style="display:flex; gap:10px; align-items:flex-end; margin-bottom:10px;">' +
                        '<div style="flex:2;"><label style="font-size:12px; color:var(--text-secondary); font-weight:bold; display:block; margin-bottom:4px;">' + (L.mail_fra_imap_server || 'IMAP Host') + '</label><input type="text" id="fraAccImap" class="myCloudInlineInput" value="' + window._fraTempData.imapH + '" style="width:100%; margin:0;"></div>' +
                        '<div style="flex:1;"><label style="font-size:12px; color:var(--text-secondary); font-weight:bold; display:block; margin-bottom:4px;">' + (L.mail_fra_port || 'Port') + '</label><input type="text" id="fraAccImapPort" class="myCloudInlineInput" value="' + window._fraTempData.imapP + '" style="width:100%; margin:0;"></div>' +
                    '</div>' +
                    '<label style="font-size:12px; color:var(--text-secondary); font-weight:bold; display:block; margin-bottom:4px;">' + (L.mail_fra_encryption || 'Encryption') + '</label>' +
                    '<select id="fraAccImapEnc" class="myCloudInlineInput" style="width:100%; margin:0; margin-bottom:20px; height:34px;">' +
                        '<option value="ssl" ' + (window._fraTempData.imapE === 'ssl' ? 'selected' : '') + '>' + (L.mail_fra_ssl_tls || 'SSL / TLS') + '</option>' +
                        '<option value="none" ' + (window._fraTempData.imapE === 'none' ? 'selected' : '') + '>' + (L.mail_fra_none_localhost || 'None (Localhost)') + '</option>' +
                    '</select>' +
                    '<div style="display:flex; gap:10px; align-items:flex-end; margin-bottom:10px;">' +
                        '<div style="flex:2;"><label style="font-size:12px; color:var(--text-secondary); font-weight:bold; display:block; margin-bottom:4px;">' + (L.mail_fra_smtp_server || 'SMTP Host') + '</label><input type="text" id="fraAccSmtp" class="myCloudInlineInput" value="' + window._fraTempData.smtpH + '" style="width:100%; margin:0;"></div>' +
                        '<div style="flex:1;"><label style="font-size:12px; color:var(--text-secondary); font-weight:bold; display:block; margin-bottom:4px;">' + (L.mail_fra_port || 'Port') + '</label><input type="text" id="fraAccSmtpPort" class="myCloudInlineInput" value="' + window._fraTempData.smtpP + '" style="width:100%; margin:0;"></div>' +
                    '</div>' +
                    '<label style="font-size:12px; color:var(--text-secondary); font-weight:bold; display:block; margin-bottom:4px;">' + (L.mail_fra_encryption || 'Encryption') + '</label>' +
                    '<select id="fraAccSmtpEnc" class="myCloudInlineInput" style="width:100%; margin:0; margin-bottom:15px; height:34px;">' +
                        '<option value="ssl" ' + (window._fraTempData.smtpE === 'ssl' ? 'selected' : '') + '>' + (L.mail_fra_ssl_tls || 'SSL / TLS') + '</option>' +
                        '<option value="tls" ' + (window._fraTempData.smtpE === 'tls' ? 'selected' : '') + '>' + (L.mail_fra_starttls || 'STARTTLS') + '</option>' +
                        '<option value="none" ' + (window._fraTempData.smtpE === 'none' ? 'selected' : '') + '>' + (L.mail_fra_none_localhost || 'None (Localhost)') + '</option>' +
                    '</select></div>' +
                    '<div id="fraEasGroup" style="display:' + (window._fraTempData.serverType === 'eas' ? 'block' : 'none') + ';">' +
                        '<label style="font-size:12px; color:var(--text-secondary); font-weight:bold; display:block; margin-bottom:4px;">' + (L.eas_server || 'EAS Server URL') + '</label>' +
                        '<input type="url" id="fraAccEasHost" class="myCloudInlineInput" value="' + window._fraTempData.easH + '" placeholder="https://outlook.office365.com" style="width:100%; margin:0 0 15px 0;">' +
                    '</div>';
            }
            
            // --- STEP PGP: KEY CREATION ---
            else if (stepId === 'pgp') {
                html += 
                    '<h3 style="margin:0 0 5px 0;">' + (L.mail_fra_pgp_title || 'PGP End-to-End Encryption') + '</h3>' +
                    '<p style="color:var(--text-secondary); font-size:13px; margin:0 0 20px 0;">' + (L.mail_fra_pgp_desc || 'Highly recommended: Secure your communications by setting up PGP encryption. You can generate a new key pair or paste your existing keys.') + '</p>' +
                    '<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:10px;">' +
                        '<div style="font-size:14px; font-weight:bold;">' + (L.pgp_key_mgmt || 'PGP Key Management') + '</div>' +
                        '<button type="button" class="owa-btn" onclick="window._emlGeneratePgpKeysFRA()" style="padding:4px 10px; font-size:11px; border:1px solid var(--border-medium); background:var(--gray-00);">' + (L.pgp_gen_keys_btn || 'Generate Key Pair') + '</button>' +
                    '</div>' +
                    '<label style="font-size:12px; color:var(--text-secondary); font-weight:bold; display:block; margin-bottom:4px;">' + (L.pgp_your_pub_key || 'Your Public Key (ASCII Armored)') + '</label>' +
                    '<textarea id="fraPgpPub" class="myCloudInlineInput" style="min-height:80px; resize:vertical; font-family:monospace; font-size:11px; margin-bottom:10px;">' + esc(window._fraTempData.pgpPub) + '</textarea>' +
                    '<label style="font-size:12px; color:var(--text-secondary); font-weight:bold; display:block; margin-bottom:4px;">' + (L.pgp_your_priv_key || 'Your Private Key (ASCII Armored)') + '</label>' +
                    '<textarea id="fraPgpPriv" class="myCloudInlineInput" style="min-height:80px; resize:vertical; font-family:monospace; font-size:11px; margin:0;">' + esc(window._fraTempData.pgpPriv) + '</textarea>';
            }

            // --- STEP 4: ALIASES ---
            else if (stepId === 'aliases') {
                html += 
                    '<h3 style="margin:0 0 5px 0;">' + (L.mail_fra_email_aliases || 'Email Aliases') + '</h3>' +
                    '<p style="color:var(--text-secondary); font-size:13px; margin:0 0 20px 0;">' + (L.mail_fra_alias_desc || 'If you receive emails for other addresses (e.g., info@, support@) in this same mailbox, add them below so you can also send emails from them.') + '</p>' +
                    '<div style="display:flex; gap:10px; margin-bottom:15px;">' +
                        '<input type="email" id="fraNewAlias" class="myCloudInlineInput" placeholder="alias@example.com" style="flex:1; margin:0;">' +
                        '<button type="button" id="fraAddAliasBtn" class="owa-btn" style="background:var(--gray-10); border:1px solid var(--border-medium);">' + (L.add || 'Add') + '</button>' +
                    '</div>' +
                    '<div id="fraAliasList" style="background:var(--gray-05); border:1px solid var(--border-default); border-radius:4px; min-height:80px; max-height:150px; overflow-y:auto; padding:5px;">';
                
                if (window._fraTempData.aliases.length === 0) {
                    html += '<div style="padding:10px; text-align:center; color:var(--text-disabled); font-size:12px;">' + (L.mail_fra_no_aliases || 'No aliases added.') + '</div>';
                } else {
                    window._fraTempData.aliases.forEach((alias, idx) => {
                        html += '<div style="display:flex; justify-content:space-between; align-items:center; padding:6px 10px; background:var(--gray-00); border:1px solid var(--border-subtle); margin-bottom:5px; border-radius:4px; font-size:13px;">' +
                                    '<span>' + alias + '</span>' +
                                    '<button type="button" onclick="window._fraRemoveAlias(' + idx + ')" style="background:none; border:none; color:var(--danger); cursor:pointer;" title="Remove">✕</button>' +
                                '</div>';
                    });
                }
                html += '</div>';
            }

            // --- STEP 5: SIGNATURES ---
            else if (stepId === 'signatures') {
                html += 
                    '<h3 style="margin:0 0 5px 0;">' + (L.mail_fra_signatures || 'Email Signatures') + '</h3>' +
                    '<p style="color:var(--text-secondary); font-size:13px; margin:0 0 20px 0;">' + (L.mail_fra_sig_desc || 'Create a signature that will automatically be appended to the bottom of your outgoing emails.') + '</p>' +
                    '<label style="font-size:12px; color:var(--text-secondary); font-weight:bold; display:block; margin-bottom:4px;">' + (L.mail_fra_main_signature || 'Main Account Signature') + ' (' + window._fraTempData.email + ')</label>' +
                    '<textarea id="fraMainSig" class="myCloudInlineInput" style="width:100%; height:80px; margin-bottom:15px; resize:vertical; font-family:sans-serif;" placeholder="Best regards,\n' + window._fraTempData.name + '">' + window._fraTempData.mainSig + '</textarea>';

                if (window._fraTempData.aliases.length > 0) {
                    html += '<label style="display:flex; align-items:center; gap:8px; font-size:13px; cursor:pointer; color:var(--text-primary); margin-bottom:15px;">' +
                                '<input type="checkbox" id="fraDiffSigCb" class="myCloudCheckbox" style="margin:0;" ' + (window._fraTempData.diffAliasSigs ? 'checked' : '') + '> ' + 
                                (L.mail_fra_diff_alias_sigs || 'Set up different signatures for my aliases') +
                            '</label>';
                            
                    if (window._fraTempData.diffAliasSigs) {
                        html += '<div style="background:var(--gray-05); padding:15px; border-radius:4px; border:1px solid var(--border-default);">' +
                                    '<select id="fraAliasSelect" class="myCloudInlineInput" style="width:100%; margin-bottom:10px;">';
                        
                        if (!window._fraTempData.activeAliasSig) window._fraTempData.activeAliasSig = window._fraTempData.aliases[0];
                        
                        window._fraTempData.aliases.forEach(alias => {
                            html += '<option value="' + alias + '" ' + (window._fraTempData.activeAliasSig === alias ? 'selected' : '') + '>' + alias + '</option>';
                        });
                        
                        let currentAliasSig = window._fraTempData.aliasSigs[window._fraTempData.activeAliasSig] || '';
                        
                        html +=     '</select>' +
                                    '<textarea id="fraAliasSigText" class="myCloudInlineInput" style="width:100%; height:70px; margin:0; resize:vertical; font-family:sans-serif;" placeholder="Signature for this alias...">' + currentAliasSig + '</textarea>' +
                                '</div>';
                    }
                }
            }

            // --- STEP 6: IMPORT CONTACTS ---
            else if (stepId === 'import') {
                html += 
                    '<h3 style="margin:0 0 5px 0;">' + (L.mail_fra_import_contacts || 'Import Address Book') + '</h3>' +
                    '<p style="color:var(--text-secondary); font-size:13px; margin:0 0 20px 0;">' + (L.mail_fra_import_desc || 'If you have an existing address book from a previous email provider, you can upload your CSV file now to import your contacts automatically.') + '</p>' +
                    '<div style="border:2px dashed var(--border-medium); padding:30px 20px; text-align:center; border-radius:6px; background:var(--gray-00);">' +
                        '<input type="file" id="fraImportFile" accept=".csv" style="display:none;">' +
                        '<button type="button" onclick="document.getElementById(\'fraImportFile\').click()" class="owa-btn" style="background:var(--gray-10); border:1px solid var(--border-default); margin-bottom:10px;">📁 ' + (L.mail_fra_select_csv_file || 'Select CSV File') + '</button>' +
                        '<div id="fraFileName" style="font-size:13px; color:var(--text-primary); font-weight:bold;">' + (window._fraTempData.importFile ? window._fraTempData.importFile.name : (L.mail_fra_no_file_selected || 'No file selected')) + '</div>' +
                    '</div>';
            }

            // Navigation Buttons
            html += '<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-top:25px; padding-top:15px; border-top:1px solid var(--border-default);">';
            
            if (currentStepIndex === 0) {
                html += '<button class="owa-btn" onclick="myCloudCloseModal()">' + (L.cancel || 'Cancel') + '</button>';
            } else {
                html += '<button type="button" id="fraBackBtn" class="owa-btn">&larr; ' + (L.back || 'Back') + '</button>';
            }

            if (isLastStep) {
                html += '<button type="button" id="fraSaveBtn" class="owa-btn owa-primary">' + (L.mail_fra_finish_setup || 'Save & Finish') + '</button>';
            } else {
                if (stepId === 'server') html += '<button type="button" id="fraTestConnBtn" class="owa-btn" style="border: 1px solid var(--border-medium); background: var(--gray-05); margin-inline-start: auto; margin-inline-end: 10px;">' + (L.test_connection || 'Test Connection') + '</button>';
				html += '<button type="button" id="fraNextBtn" class="owa-btn owa-primary">' + (L.next || 'Next') + ' &rarr;</button>';
            }
            
            html += '</div></div>'; // Close modal body
            modal.innerHTML = html;
            if (typeof myCloudApplyTheme === 'function') myCloudApplyTheme();
            
            if (stepId === 'login') {
                setTimeout(() => {
                    const cleanUri = window.myCloudOAuthDomain || (window.location.origin + window.location.pathname);
					const disp = document.getElementById('fraOauthRedirectDisp');
                    if (disp) disp.textContent = cleanUri;
                }, 10);
            }

            attachEvents(stepId, isLastStep);
        };

        const attachEvents = (stepId, isLastStep) => {
            // Data Binding on active inputs before navigation
            const syncData = () => {
                if (document.getElementById('fraAccName')) window._fraTempData.accName = document.getElementById('fraAccName').value.trim();
                if (document.getElementById('fraAccEmail')) window._fraTempData.email = document.getElementById('fraAccEmail').value.trim();
                if (document.getElementById('fraAccSenderName')) window._fraTempData.name = document.getElementById('fraAccSenderName').value.trim();
                if (document.getElementById('fraAccUsername')) window._fraTempData.username = document.getElementById('fraAccUsername').value.trim();
                if (document.getElementById('fraAccAuthType')) window._fraTempData.authType = document.getElementById('fraAccAuthType').value;
				if (document.getElementById('fraAccPwd')) window._fraTempData.password = document.getElementById('fraAccPwd').value;
                if (document.getElementById('fraAccServerType')) window._fraTempData.serverType = document.getElementById('fraAccServerType').value;
                if (document.getElementById('fraAccEasHost')) window._fraTempData.easH = document.getElementById('fraAccEasHost').value.trim();
                if (document.getElementById('fraAccImap')) {
                    window._fraTempData.imapH = document.getElementById('fraAccImap').value.trim();
                    window._fraTempData.imapP = document.getElementById('fraAccImapPort').value.trim();
                    window._fraTempData.imapE = document.getElementById('fraAccImapEnc').value;
                    window._fraTempData.smtpH = document.getElementById('fraAccSmtp').value.trim();
                    window._fraTempData.smtpP = document.getElementById('fraAccSmtpPort').value.trim();
                    window._fraTempData.smtpE = document.getElementById('fraAccSmtpEnc').value;
                }
                if (document.getElementById('fraPgpPub')) window._fraTempData.pgpPub = document.getElementById('fraPgpPub').value;
                if (document.getElementById('fraPgpPriv')) window._fraTempData.pgpPriv = document.getElementById('fraPgpPriv').value;
                if (document.getElementById('fraMainSig')) window._fraTempData.mainSig = document.getElementById('fraMainSig').value;
                if (document.getElementById('fraAliasSigText') && window._fraTempData.activeAliasSig) {
                    window._fraTempData.aliasSigs[window._fraTempData.activeAliasSig] = document.getElementById('fraAliasSigText').value;
                }
            };

            if (stepId === 'server') {
                const testBtn = document.getElementById('fraTestConnBtn');
                if (testBtn) testBtn.onclick = () => {
                    syncData();
                    testBtn.disabled = true; testBtn.textContent = (L.testing || 'Testing...');
                    
                    const fd = new URLSearchParams();
                    fd.append('myCloud_action', 'email_test_connection');
                    fd.append('myCloud_key', myCloudState.key);
                    fd.append('myCloud_token', myCloudCsrfToken);
                    fd.append('email', window._fraTempData.email);
                    fd.append('login_user', window._fraTempData.username);
                    fd.append('password', window._fraTempData.password);
                    fd.append('auth_type', window._fraTempData.authType);
                    fd.append('server_type', window._fraTempData.serverType || 'imap');
                    fd.append('eas_host', window._fraTempData.easH || '');
                    fd.append('imap_host', window._fraTempData.imapH || ''); fd.append('imap_port', window._fraTempData.imapP || ''); fd.append('imap_enc', window._fraTempData.imapE || 'ssl');
                    
                    fetch('', { method: 'POST', body: fd }).then(r=>r.json()).then(res => {
                        testBtn.disabled = false; testBtn.textContent = (L.test_connection || 'Test Connection');
                        if (res.status === 'OK') myCloudShowAlert('Success', res.msg);
                        else myCloudShowAlert('Error', res.msg);
                    }).catch(() => { testBtn.disabled = false; testBtn.textContent = (L.test_connection || 'Test Connection'); myCloudShowAlert('Error', 'Network Error'); });
                };
            }

            // Local specific actions
            if (stepId === 'server') {
                const imapEncDrop = document.getElementById('fraAccImapEnc');
                const imapPortInp = document.getElementById('fraAccImapPort');
                if (imapEncDrop && imapPortInp) {
                    imapEncDrop.onchange = function(e) {
                        if (e.target.value === 'none') {
                            if (imapPortInp.value === '993' || imapPortInp.value === '') imapPortInp.value = '143';
                        } else {
                            if (imapPortInp.value === '143' || imapPortInp.value === '') imapPortInp.value = '993';
                        }
                        syncData(); // Persist to state immediately
                    };
                }
                const smtpEncDrop = document.getElementById('fraAccSmtpEnc');
                const smtpPortInp = document.getElementById('fraAccSmtpPort');
                if (smtpEncDrop && smtpPortInp) {
                    smtpEncDrop.onchange = function(e) {
                        if (e.target.value === 'none') {
                            if (smtpPortInp.value === '465' || smtpPortInp.value === '587' || smtpPortInp.value === '') smtpPortInp.value = '25';
                        } else if (e.target.value === 'tls') {
                            if (smtpPortInp.value === '465' || smtpPortInp.value === '25' || smtpPortInp.value === '') smtpPortInp.value = '587';
                        } else {
                            if (smtpPortInp.value === '587' || smtpPortInp.value === '25' || smtpPortInp.value === '') smtpPortInp.value = '465';
                        }
                        syncData(); // Persist to state immediately
                    };
                }
            }

            if (stepId === 'aliases') {
                const addBtn = document.getElementById('fraAddAliasBtn');
                if (addBtn) addBtn.onclick = () => {
                    const val = document.getElementById('fraNewAlias').value.trim();
                    if (val && !window._fraTempData.aliases.includes(val)) {
                        window._fraTempData.aliases.push(val);
                        renderStep();
                    }
                };
                window._fraRemoveAlias = (idx) => {
                    window._fraTempData.aliases.splice(idx, 1);
                    renderStep();
                };
            }

            if (stepId === 'signatures') {
                const diffCb = document.getElementById('fraDiffSigCb');
                if (diffCb) {
                    diffCb.onchange = (e) => {
                        syncData(); // save main sig
                        window._fraTempData.diffAliasSigs = e.target.checked;
                        renderStep();
                    };
                }
                const alSelect = document.getElementById('fraAliasSelect');
                if (alSelect) {
                    alSelect.onchange = (e) => {
                        syncData(); // save currently active alias sig before switching
                        window._fraTempData.activeAliasSig = e.target.value;
                        renderStep();
                    };
                }
            }

            if (stepId === 'import') {
                const fileIn = document.getElementById('fraImportFile');
                if (fileIn) {
                    fileIn.onchange = (e) => {
                        if (e.target.files.length > 0) {
                            window._fraTempData.importFile = e.target.files[0];
                            document.getElementById('fraFileName').textContent = window._fraTempData.importFile.name;
                        }
                    };
                }
            }

            // Navigation Execution
            const backBtn = document.getElementById('fraBackBtn');
            if (backBtn) backBtn.onclick = () => {
                syncData();
                currentStepIndex--;
                renderStep();
            };

            const nextBtn = document.getElementById('fraNextBtn');
            if (nextBtn) nextBtn.onclick = () => {
                syncData();
                // Validation
                if (stepId === 'basic') {
                    if (!window._fraTempData.accName) return myCloudShowAlert('Error', L.mail_fra_enter_acc_name || 'Please enter a name for this mailbox.');
                    if (!window._fraTempData.email) return myCloudShowAlert('Error', L.mail_fra_enter_email || 'Please enter an email address.');
                    if (!window._fraTempData.name) return myCloudShowAlert('Error', L.mail_fra_enter_name || 'Please enter a sender name.');
                }
                if (stepId === 'login') {
                    if (!window._fraTempData.username) return myCloudShowAlert('Error', L.mail_fra_enter_username || 'Please enter your username.');
                    if (window._fraTempData.authType === 'basic' && !window._fraTempData.password) return myCloudShowAlert('Error', L.mail_fra_enter_password || 'Please enter your password.');
				}
                currentStepIndex++;
                renderStep();
            };

            const saveBtn = document.getElementById('fraSaveBtn');
            if (saveBtn) saveBtn.onclick = () => {
                syncData();
                finalizeAssistant();
            };
        };

        const finalizeAssistant = () => {
            const btn = document.getElementById('fraSaveBtn');
            btn.disabled = true;
            btn.textContent = L.mail_fra_processing || 'Processing...';

            // Construct Alias JSON payload
            let processedAliases = [];
            window._fraTempData.aliases.forEach(email => {
                let sig = '';
                if (window._fraTempData.diffAliasSigs && window._fraTempData.aliasSigs[email]) {
                    sig = window._fraTempData.aliasSigs[email];
                }
                processedAliases.push({ id: 'alias_' + Math.random().toString(36).substr(2, 6), email: email, name: window._fraTempData.name, signature: sig });
            });

            const fd = new URLSearchParams();
            fd.append('myCloud_action', 'email_save_account');
            fd.append('myCloud_key', typeof myCloudState !== 'undefined' ? myCloudState.key : '');
            fd.append('myCloud_token', typeof window.myCloudCsrfToken !== 'undefined' ? window.myCloudCsrfToken : '');
            
            fd.append('account_id', 'mail_' + Math.random().toString(36).substr(2, 9));
            fd.append('name', window._fraTempData.accName); // Display Name of the Mailbox
            fd.append('sender_name', window._fraTempData.name);
            fd.append('email', window._fraTempData.email);
            fd.append('login_user', window._fraTempData.username);
			fd.append('auth_type', window._fraTempData.authType);
            fd.append('password', window._fraTempData.password);
            fd.append('signature', window._fraTempData.mainSig);
            fd.append('aliases', JSON.stringify(processedAliases));
            fd.append('pgp_public_key', window._fraTempData.pgpPub);
            fd.append('pgp_private_key', window._fraTempData.pgpPriv);
            fd.append('server_type', window._fraTempData.serverType || 'imap');
            fd.append('eas_host', window._fraTempData.easH || '');

            let imapHost = '', imapPort = '', imapEnc = '', smtpHost = '', smtpPort = '', smtpEnc = '';
            if (isHiddenFields) {
                imapHost = '127.0.0.1'; imapPort = '143'; imapEnc = 'none';
                smtpHost = '127.0.0.1'; smtpPort = '25'; smtpEnc = 'none';
            } else {
                imapHost = window._fraTempData.imapH; imapPort = window._fraTempData.imapP; imapEnc = window._fraTempData.imapE;
                smtpHost = window._fraTempData.smtpH; smtpPort = window._fraTempData.smtpP; smtpEnc = window._fraTempData.smtpE;
            }

            if (imapHost.toLowerCase() === 'localhost' || imapHost === '127.0.0.1') { imapHost = '127.0.0.1'; imapPort = '143'; imapEnc = 'none'; }
            if (smtpHost.toLowerCase() === 'localhost' || smtpHost === '127.0.0.1') { smtpHost = '127.0.0.1'; smtpPort = '25'; smtpEnc = 'none'; }

            fd.append('imap_host', imapHost); fd.append('imap_port', imapPort); fd.append('imap_enc', imapEnc);
            fd.append('smtp_host', smtpHost); fd.append('smtp_port', smtpPort); fd.append('smtp_enc', smtpEnc);

            fetch('', { method: 'POST', body: fd }).then(r => r.json()).then(res => {
                if (res.status === 'OK') {
                    // Trigger OAuth popup if provided by backend ---
                    if (res.oauth_url) {
                        const win = window.open(res.oauth_url, 'oauth', 'width=600,height=700');
                        if (win) win.focus();
                        // We do NOT call closeAndReload() here yet, because the 
                        // OAuth callback will refresh the accounts.
                    }
            
                    // Trigger Contact Import if file exists
                    if (window._fraTempData.importFile && canImportExport) {
                        btn.textContent = L.mail_fra_importing || 'Importing Contacts...';
                        const impFd = new FormData();
                        impFd.append('myCloud_action', 'email_import_contacts');
                        impFd.append('myCloud_key', myCloudState.key);
                        impFd.append('myCloud_token', window.myCloudCsrfToken);
                        impFd.append('file', window._fraTempData.importFile);
                        
                        fetch('', { method: 'POST', body: impFd }).then(r=>r.json()).then(impRes => {
                            closeAndReload(impRes.status === 'OK' ? (L.imported_msg || "Imported %s contacts.").replace('%s', impRes.imported) : null);
                        }).catch(() => closeAndReload());
                    } else {
                        // Only close if we didn't trigger a popup (or if you prefer to close anyway)
                        if (!res.oauth_url) closeAndReload();
                    }
                } else {
                    if (typeof myCloudShowAlert === 'function') myCloudShowAlert(L.error_prefix || 'Error', res.msg || (L.failed_save_acc || "Failed to save account."));
                    btn.disabled = false; btn.textContent = L.mail_fra_finish_setup || 'Save & Finish';
                }
            }).catch(() => {
                if (typeof myCloudShowAlert === 'function') myCloudShowAlert(L.error_prefix || 'Error', (L.net_error || "Network error."));
                btn.disabled = false; btn.textContent = L.mail_fra_finish_setup || 'Save & Finish';
            });
        };

        const closeAndReload = (msg = null) => {
            if (msg && typeof myCloudShowAlert === 'function') myCloudShowAlert(L.success || "Success", msg);
            if (typeof myCloudEmailLoadAccounts === 'function') myCloudEmailLoadAccounts();
            if (typeof myCloudCloseModal === 'function') myCloudCloseModal();
        };

        renderStep();
        window._emlGeneratePgpKeysFRA = async function() {
            const email = window._fraTempData.email || (document.getElementById('fraAccEmail') ? document.getElementById('fraAccEmail').value.trim() : '');
            const name = window._fraTempData.name || (document.getElementById('fraAccSenderName') ? document.getElementById('fraAccSenderName').value.trim() : '') || 'MyCloud User';

            if (!email) {
                return myCloudShowAlert(L.error_prefix || 'Error', L.pgp_err_need_email || 'Please enter an Email Address in the Basic Information step first.');
            }

            let userIds = [{ name: name, email: email }];
            if (window._fraTempData.aliases && window._fraTempData.aliases.length > 0) {
                window._fraTempData.aliases.forEach(al => {
                    if (al && al.includes('@')) userIds.push({ name: name, email: al });
                });
            }


            const subOverlay = document.createElement('div');
            subOverlay.className = 'myCloudOverlay';
            subOverlay.style.display = 'flex';
            subOverlay.style.zIndex = '100010';

            const subModal = document.createElement('div');
            subModal.className = 'myCloudModal';
            subModal.style.maxWidth = '480px';
            subModal.style.width = '100%';

            subModal.innerHTML = 
                 '<div class="myCloudModalHeader" style="justify-content:space-between;"><span>' + (typeof myCloudSvgLogo !== 'undefined' ? myCloudSvgLogo : '') + ' ' + (L.pgp_gen_keys_btn || 'Generate Key Pair') + '</span><button class="myCloudClose" id="pgpGenClose">✕</button></div>' +
                 '<div class="myCloudModalBody" style="padding:20px;">' +
                     '<label style="display:block; margin-bottom:8px; font-weight:600; font-size:13px; color:var(--text-primary);">' + (L.pgp_passphrase_prompt || 'Set a Passphrase to protect your Private Key:') + '</label>' +
                     '<div style="position:relative; display:flex; align-items:center; width:100%; margin-bottom:15px;">' +
                         '<input type="password" id="pgpGenPwdInput" autocomplete="new-password" class="myCloudInlineInput" style="width:100%; box-sizing:border-box; padding:8px 10px; padding-inline-end:32px; font-size:14px; margin:0;">' +
                         '<button type="button" tabindex="-1" onclick="window._toggleEmlPwd(this, \'pgpGenPwdInput\')" style="position:absolute; inset-inline-end:4px; background:none; border:none; cursor:pointer; color:var(--text-secondary); padding:4px;" title="' + (L.toggle_pwd_vis || 'Toggle visibility') + '">' +
                             '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px; height:16px;"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>' +
                         '</button>' +
                     '</div>' +
                    '<div class="myCloudButtons" style="justify-content:flex-end; flex-wrap:wrap; gap:10px; margin-top:20px; margin-bottom:0;">' +
                        '<button type="button" id="pgpGenCancel" style="margin:0;">' + (L.cancel || 'Cancel') + '</button>' +
                        '<button type="button" id="pgpGenSubmit" style="background:var(--accent-primary); color:#fff; border:none; margin:0;">' + (L.ok || 'OK') + '</button>' +
                    '</div>' +
                 '</div>';

            subOverlay.appendChild(subModal);
            document.body.appendChild(subOverlay);

            const closePrompt = () => { subOverlay.remove(); };
           document.getElementById('pgpGenClose').onclick = closePrompt;
            document.getElementById('pgpGenCancel').onclick = closePrompt;

            const input = document.getElementById('pgpGenPwdInput');
            setTimeout(() => input.focus(), 50);

            const submitPrompt = async () => {
                const passphrase = input.value;
                if (!passphrase) return;
                closePrompt();
                if (typeof myCloudCreateProgressUI === 'function') myCloudCreateProgressUI(L.pgp_generating || 'Generating RSA-4096 Keys (This may take a moment)...');
                if (!window.openpgp) { await new Promise((res, rej) => { const s = document.createElement('script'); s.src = '/script/openpgp/openpgp.min.js'; s.onload = res; s.onerror = rej; document.head.appendChild(s); }); }
                setTimeout(async () => {
                    try {
                        const { privateKey, publicKey } = await window.openpgp.generateKey({ type: 'ecc', curve: 'curve25519', userIDs: userIds, passphrase: passphrase });
                        document.getElementById('fraPgpPub').value = publicKey;
                        document.getElementById('fraPgpPriv').value = privateKey;
                        window._fraTempData.pgpPub = publicKey;
                        window._fraTempData.pgpPriv = privateKey;
                        setTimeout(() => {
                            myCloudShowAlert(L.warning || 'Important: Backup Required', 
                                L.backup_required_msg || 'Your PGP Key Pair has been successfully generated. If you forget your password or lose access, you will <b>permanently lose the ability to read your encrypted emails</b>.<br><br>Please download a safe backup of your Private Key now.', 
                                () => { window._emlExportKey('private'); }
                            );
                        }, 500);
                        if (typeof myCloudCloseProgressUI === 'function') myCloudCloseProgressUI();
                    } catch (e) {
                        if (typeof myCloudCloseProgressUI === 'function') myCloudCloseProgressUI();
                        if (typeof myCloudShowAlert === 'function') myCloudShowAlert(L.error_prefix || 'Error', e.message);
                    }
                }, 100);
            };
            document.getElementById('pgpGenSubmit').onclick = submitPrompt;
            input.onkeydown = (e) => { if (e.key === 'Enter') { e.preventDefault(); submitPrompt(); } if (e.key === 'Escape') { e.preventDefault(); closePrompt(); } };
            if (typeof myCloudApplyTheme === 'function') myCloudApplyTheme();
        };
    };
	
</script>