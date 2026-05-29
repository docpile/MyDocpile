<?php
/**
 * ============================================================================
 * MODULE: OnlyOffice Integration
 * ============================================================================
 * Contains interface needed for OnlyOffice
 * Required to render and interact with OnlyOffice.
 * NOTE: Executed exclusively by the client browser.
 * 
 * THIS IS PART OF THE PSEUDO "JS FILE" - SO THE NO PHP CODE IN THIS FILE!
 */
?>

<script>
// Global safety net: If the user closes the tab or the browser crashes while an unencrypted E2E file is open, 
// the browser will fire this background beacon to nuke the temp file.
window.addEventListener('pagehide', function() {
    const ooContainer = document.getElementById('myCloudEditor_onlyOfficeContainer');
    if (ooContainer && ooContainer.dataset.tempPath) {
        const tempPath = ooContainer.dataset.tempPath;
        const key = typeof myCloudState !== 'undefined' ? myCloudState.key : '';
        const token = typeof myCloudCsrfToken !== 'undefined' ? window.myCloudCsrfToken : '';
        
        // CRITICAL FIX: Use FormData. sendBeacon with URLSearchParams is parsed as text/plain by some browsers,
        // causing the PHP backend to ignore the $_POST array completely. FormData forces multipart/form-data.
        const fd = new FormData();
        fd.append('myCloud_action', 'delete');
        fd.append('myCloud_key', key);
        fd.append('myCloud_token', token);
        fd.append('src', tempPath);
        fd.append('permanent', 'true');
        
        if (navigator.sendBeacon) {
            navigator.sendBeacon(window.location.pathname, fd);
        }
    }
});

function myCloudOpenOnlyOffice(filePath, action = null, originalEncPath = null) {
    // --- STRICT ROLE GUARD ---
    if (typeof myCloudUserRole !== 'undefined' && (myCloudUserRole === 'read-only' || myCloudUserRole === 'no-access')) {
        if (typeof myCloudShowAlert === 'function') myCloudShowAlert('Access Denied', 'You do not have permission to edit files.');
        return;
    }
    // [FIX] Lazily inject the Editor/OnlyOffice DOM wrapper if it doesn't exist yet
    if (typeof ceInitEditorDOM === 'function') {
        ceInitEditorDOM();
    }

    const st = myCloudState;
    const devKey = typeof myCloudGetCurrentDeviceKey === 'function' ? myCloudGetCurrentDeviceKey() : 'desktop';
    const isDark = (st.settings && st.settings[devKey] && st.settings[devKey].darkMode) ? '1' : '0';
    const isMobile = ['tablet', 'phone'].includes(devKey) ? '1' : '0';
    let appLang = 'en';
    if (st.settings && st.settings.language) appLang = st.settings.language;
    else if (typeof myCloudDetectedLang !== 'undefined') appLang = myCloudDetectedLang;

    let realName = filePath.split('/').pop();
    let isEnc = false;
    let root = null;
    
    if (typeof myCloudCrypto !== 'undefined') {
        root = myCloudCrypto.getCryptoRoot(originalEncPath || filePath);
        isEnc = (originalEncPath !== null) || (filePath.endsWith('.enc') || root);
    }

    if (isEnc && !originalEncPath) {
        realName = (st.pathNames && st.pathNames[filePath]) ? st.pathNames[filePath] : realName.replace(/\.enc$/, '');
    }
    
    const ext = realName.split('.').pop().toLowerCase();

    if (typeof myCloudHasOnlyOffice === 'undefined' || !myCloudHasOnlyOffice) {
        const editExts = (typeof myCloudConfig !== 'undefined' && myCloudConfig.edit) ? myCloudConfig.edit : ['txt', 'md', 'html', 'css', 'js', 'php', 'json', 'xml', 'csv', 'yaml', 'yml', 'ini', 'sh', 'bat'];
        if (action === 'edit' && editExts.includes(ext)) {
             myCloudEditFile(filePath);
        } else {
             myCloudDownloadFile(filePath, realName, true);
        }
        return;
    }

    if (isEnc && !originalEncPath) {
        if (!myCloudCrypto.isDirUnlocked(root)) {
            myCloudAction_EncryptPrompt(root, true, () => myCloudOpenOnlyOffice(filePath, action));
            return;
        }

        const reqUrl = window.location.pathname; 

        const proceedWithOffice = async () => {
            myCloudCreateProgressUI(typeof myCloud_LANG !== 'undefined' && myCloud_LANG.oo_e2e_decrypting ? myCloud_LANG.oo_e2e_decrypting : 'Decrypting for OnlyOffice...');
            
            try {
                const dlFd = new URLSearchParams({ myCloud_action: 'get_download_token', myCloud_key: st.key, myCloud_token: window.myCloudCsrfToken, path: filePath, filename: realName, preview: '0' });
                const tokenRes = await fetch(reqUrl, { method: 'POST', body: dlFd }).then(r => r.json());
                if (tokenRes.status !== 'OK') throw new Error("Download failed: " + (tokenRes.msg || ''));
                
                const encBlob = await fetch(reqUrl + '?myCloud_token=' + tokenRes.token).then(r => r.blob());
                const decBlob = await myCloudCrypto.decryptFile(root, encBlob);
                
                const parentDir = filePath.substring(0, filePath.lastIndexOf('/')) || '/';
                const tempName = '.myCloud_temp_OO_' + Date.now() + '_' + realName.replace(/[^a-zA-Z0-9.-]/g, '_');
                
                const upFd = new FormData();
                upFd.append('myCloud_action', 'upload');
                upFd.append('dir', parentDir);
                upFd.append('myCloud_key', st.key);
                upFd.append('myCloud_token', window.myCloudCsrfToken);
                upFd.append('file', decBlob, tempName);
                
                const upRes = await fetch(reqUrl, { method: 'POST', body: upFd }).then(r => r.json());
                if (upRes.status !== 'OK') throw new Error("Temp upload failed: " + (upRes.msg || ''));
                
                const tempPath = (parentDir === '/' ? '' : parentDir) + '/' + tempName;
                myCloudCloseProgressUI();
                
                myCloudOpenOnlyOffice(tempPath, action, filePath);
            } catch(e) {
                myCloudCloseProgressUI();
                myCloudShowAlert('Error', e.message);
            }
        };

        const proceedWithPreview = () => {
            myCloudDownloadFile(filePath, realName, true);
        };

        const cookieName = 'myCloud_OO_E2E_SkipWarn_' + encodeURIComponent(st.key);
        const match = document.cookie.match(new RegExp('(^| )' + cookieName + '=([^;]+)'));
        const savedChoice = match ? match[2] : null;

        if (!savedChoice) {
            const title = typeof myCloud_LANG !== 'undefined' && myCloud_LANG.oo_e2e_warning_title ? myCloud_LANG.oo_e2e_warning_title : 'Security Warning: OnlyOffice & Encryption';
            const msg = typeof myCloud_LANG !== 'undefined' && myCloud_LANG.oo_e2e_warning_msg ? myCloud_LANG.oo_e2e_warning_msg : 'Opening encrypted files in OnlyOffice requires temporarily sending the unencrypted document to the server.';
            const btnOo = typeof myCloud_LANG !== 'undefined' && myCloud_LANG.oo_e2e_btn_office ? myCloud_LANG.oo_e2e_btn_office : 'Use OnlyOffice';
            const btnPrev = typeof myCloud_LANG !== 'undefined' && myCloud_LANG.oo_e2e_btn_preview ? myCloud_LANG.oo_e2e_btn_preview : 'Secure Preview';

            const overlay = document.getElementById('myCloudModalOverlay');
            const modal = document.getElementById('myCloudModal');
            if (typeof myCloudResetModal === 'function') myCloudResetModal();
            overlay.style.display = 'flex';
            modal.className = 'myCloudModal conflict';
            
            modal.innerHTML = 
                '<div class="myCloudModalHeader" style="border-bottom:none; padding-bottom:0;">' + title + '</div>' +
                '<div class="myCloudModalBody" style="padding: 20px 24px; text-align:center;">' +
                    '<div style="margin-bottom:20px;">' +
                        '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#f0ad4e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>' +
                    '</div>' +
                    '<div style="font-size:14px; margin-bottom:25px; line-height: 1.5;">' + msg + '</div>' +
                    '<div style="margin-bottom:20px; font-size:13px; color:var(--text-secondary);">' +
                        '<label style="display:inline-flex; align-items:center; gap:8px; cursor:pointer;">' +
                            '<input type="checkbox" id="chkOoE2EShow" checked class="myCloudCheckbox">' +
                            (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.help_show_start ? myCloud_LANG.help_show_start : 'Show on start') +
                        '</label>' +
                    '</div>' +
                    '<div class="myCloudButtons" style="justify-content: center; gap: 10px; margin-top:0; flex-wrap:wrap;">' +
                        '<button id="btnOoE2EYes" style="background:#e81123; color:#fff; border:none; padding:6px 16px;">' + btnOo + '</button>' +
                        '<button id="btnOoE2ENo" style="padding:6px 16px; background:#0078d4; color:#fff; border:none;">' + btnPrev + '</button>' +
                        '<button id="btnOoE2ECancel" style="padding:6px 16px;">' + (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.cancel ? myCloud_LANG.cancel : 'Cancel') + '</button>' +
                    '</div>' +
                '</div>';

            const handleChoice = (choice) => {
                const chk = document.getElementById('chkOoE2EShow');
                if (chk && !chk.checked) {
                    const d = new Date();
                    d.setTime(d.getTime() + (90 * 24 * 60 * 60 * 1000)); // 3 Months
                    document.cookie = cookieName + "=" + choice + ";expires=" + d.toUTCString() + ";path=/;SameSite=Lax";
                } else {
                    document.cookie = cookieName + "=;expires=Thu, 01 Jan 1970 00:00:00 UTC;path=/;SameSite=Lax";
                }
                overlay.style.display = 'none';
                if (choice === 'office') proceedWithOffice();
                else proceedWithPreview();
            };

            document.getElementById('btnOoE2EYes').onclick = () => handleChoice('office');
            document.getElementById('btnOoE2ENo').onclick = () => handleChoice('preview');
            document.getElementById('btnOoE2ECancel').onclick = () => { overlay.style.display = 'none'; };
            return;
        } else {
            if (savedChoice === 'office') proceedWithOffice();
            else proceedWithPreview();
            return;
        }
    }

    fetch(window.location.pathname, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            myCloud_action: 'get_office_config',
            myCloud_key: st.key,
            myCloud_token: window.myCloudCsrfToken,
            path: filePath,
            dark_mode: isDark,
            is_mobile: isMobile,
            lang: appLang
        })
    })
    .then(r => r.json())
    .then(res => {
        if (res.status === 'OK') {
            const initOfficeUI = () => {
                document.getElementById('myCloudEditor_modal_wrap').style.display = 'flex';
                document.getElementById('myCloudEditor_toolbar').style.display = 'none';
                document.getElementById('myCloudEditor_statusbar').style.display = 'none';
                document.getElementById('myCloudEditor_aceContainer').style.display = 'none';
                
                const ooContainer = document.getElementById('myCloudEditor_onlyOfficeContainer');
                ooContainer.style.display = 'block';
                ooContainer.style.position = 'relative';
                
                ooContainer.dataset.encPath = originalEncPath || '';
                ooContainer.dataset.tempPath = originalEncPath ? filePath : '';
                ooContainer.dataset.docKey = res.config.document.key || '';
                ooContainer.dataset.isModified = 'false';

                let uiScale = 1.0;
                if (st.fontLevel === 0) uiScale = 0.9;
                else if (st.fontLevel === 2) uiScale = 1.1;
                else if (st.fontLevel === 3) uiScale = 1.25;
                else if (st.fontLevel === 4) uiScale = 1.4;
                else if (st.fontLevel === 5) uiScale = 1.6;

                const supportsZoom = CSS.supports('zoom: 1.5');
                const wScale = supportsZoom ? uiScale : 1.0;

                const emergencyClose = '<button id="myCloudOoEmergencyClose" onclick="myCloudCloseOnlyOffice()" style="position:absolute; top:10px; right:20px; z-index:9999; background:var(--danger, #e81123); color:#fff; border:none; padding:6px 12px; border-radius:4px; cursor:pointer; box-shadow:0 2px 5px rgba(0,0,0,0.3); font-size:13px;">✕ ' + (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.close ? myCloud_LANG.close : 'Close') + '</button>';

                ooContainer.innerHTML = 
                    '<div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; width: 100%; height: 100%; zoom: ' + wScale + ';">' +
                    emergencyClose +
                    '<div id="onlyoffice_placeholder" style="height:100%; width:100%;"></div></div>';            

                if (!res.config.events) res.config.events = {};
                
                res.config.events.onRequestClose = function() {
                    myCloudCloseOnlyOffice();
                };
				
                const oldAppReady = res.config.events.onAppReady;
                res.config.events.onAppReady = function() {
                    if (typeof oldAppReady === 'function') oldAppReady();
                    const emCloseBtn = document.getElementById('myCloudOoEmergencyClose');
                    if (emCloseBtn) emCloseBtn.style.display = 'none';
                };
                
                res.config.events.onDocumentStateChange = function(event) {
                    if (event.data) {
                        ooContainer.dataset.isModified = 'true';
                    }
                };

                window.myCloudOfficeEditor = new DocsAPI.DocEditor("onlyoffice_placeholder", res.config);
            };

            const errTitle = typeof myCloud_LANG !== 'undefined' && myCloud_LANG.error_prefix ? myCloud_LANG.error_prefix : 'Error';
            const errMsg = typeof myCloud_LANG !== 'undefined' && myCloud_LANG.oo_unavailable ? myCloud_LANG.oo_unavailable : 'Failed to connect to the OnlyOffice server. It may be offline or unreachable.';

            myCloudShowLoading();
            const checkScript = document.createElement("script");
            // Add timestamp parameter to bypass cache and verify the server is *currently* reachable
            checkScript.src = res.scriptUrl + (res.scriptUrl.includes('?') ? '&' : '?') + '_check=' + Date.now();
            checkScript.onload = () => {
                myCloudHideLoading();
                initOfficeUI();
            };
            checkScript.onerror = () => {
                myCloudHideLoading();
                myCloudShowAlert(errTitle, errMsg);
                checkScript.remove();
            };
            document.head.appendChild(checkScript);
        } else {
            myCloudShowAlert('Error', res.msg || 'Failed to initialize OnlyOffice config.');
        }
    }).catch(e => {
        myCloudShowAlert('Error', 'Network Error connecting to OnlyOffice server.');
    });
}


function myCloudCloseOnlyOffice() {
    const ooContainer = document.getElementById('myCloudEditor_onlyOfficeContainer');
    const encPath = ooContainer.dataset.encPath;
    const tempPath = ooContainer.dataset.tempPath;
    const docKey = ooContainer.dataset.docKey;
    const isModified = ooContainer.dataset.isModified === 'true';
    const reqUrl = window.location.pathname;

    if (window.myCloudOfficeEditor && window.myCloudOfficeEditor.destroyEditor) {
        window.myCloudOfficeEditor.destroyEditor();
    }
    window.myCloudOfficeEditor = null;

    document.getElementById('myCloudEditor_modal_wrap').style.display = 'none';
    ooContainer.style.display = 'none';
    ooContainer.innerHTML = '';
    document.getElementById('myCloudEditor_aceContainer').style.display = 'block';

    document.getElementById('myCloudEditor_toolbar').style.display = 'flex';
    document.getElementById('myCloudEditor_statusbar').style.display = 'flex';

    const cleanupAndFinish = () => {
        if (!tempPath) {
            if (typeof myCloudFetchDirectory === 'function' && typeof myCloudState !== 'undefined' && myCloudState.currentDir) {
                myCloudFetchDirectory(myCloudState.currentDir, 2, true).then(() => {
                    if (typeof myCloudRenderUI === 'function') myCloudRenderUI();
                });
            }
            return;
        }

        const delFd = new URLSearchParams({ myCloud_action: 'delete', myCloud_key: myCloudState.key, myCloud_token: window.myCloudCsrfToken, src: tempPath, permanent: 'true' });
        
        ooContainer.dataset.encPath = '';
        ooContainer.dataset.tempPath = '';
        ooContainer.dataset.docKey = '';

        fetch(reqUrl, { method: 'POST', body: delFd }).finally(() => {
            myCloudCloseProgressUI();
            if (typeof myCloudFetchDirectory === 'function') {
                myCloudFetchDirectory(myCloudState.currentDir, 2, true).then(() => {
                    if (typeof myCloudRenderUI === 'function') myCloudRenderUI();
                });
            }
        });
    };

    // 1. E2E RE-ENCRYPTION CLOSURE HOOK
    if (encPath && tempPath && typeof myCloudCrypto !== 'undefined') {
        const root = myCloudCrypto.getCryptoRoot(encPath);
        const uploadFilename = encPath.split('/').pop();
        
        // Instant close out if the user didn't change anything
        if (!isModified) {
            cleanupAndFinish();
            return;
        }
        
        myCloudCreateProgressUI(typeof myCloud_LANG !== 'undefined' && myCloud_LANG.oo_e2e_compiling ? myCloud_LANG.oo_e2e_compiling : 'Waiting for OnlyOffice to save...');

        const executeEncryption = () => {
            const textEl = document.getElementById('myCloudProgressText');
            if (textEl) textEl.textContent = typeof myCloud_LANG !== 'undefined' && myCloud_LANG.oo_e2e_encrypting ? myCloud_LANG.oo_e2e_encrypting : 'Saving & Re-encrypting...';

            const dlFd = new URLSearchParams({ myCloud_action: 'get_download_token', myCloud_key: myCloudState.key, myCloud_token: window.myCloudCsrfToken, path: tempPath, filename: tempPath.split('/').pop(), preview: '0' });
            
            fetch(reqUrl, { method: 'POST', body: dlFd })
            .then(r => r.json())
            .then(tokenRes => {
                if (tokenRes.status !== 'OK') throw new Error("Could not acquire download token for temp file.");
                return fetch(reqUrl + '?myCloud_token=' + tokenRes.token).then(r => {
                    if (!r.ok) throw new Error("Failed to download temp file for encryption.");
                    return r.blob();
                });
            })
            .then(async plainBlob => {
                const plainFileObj = new File([plainBlob], uploadFilename, { type: plainBlob.type });
                const encBlob = await myCloudCrypto.encryptFile(root, plainFileObj);
                
                const parentDir = encPath.substring(0, encPath.lastIndexOf('/')) || '/';
                const upFd = new FormData();
                upFd.append('myCloud_action', 'upload');
                upFd.append('dir', parentDir);
                upFd.append('myCloud_key', myCloudState.key);
                upFd.append('myCloud_token', window.myCloudCsrfToken);
                upFd.append('file', encBlob, uploadFilename);
                upFd.append('resolution', 'overwrite'); 
                
                return fetch(reqUrl, { method: 'POST', body: upFd }).then(r => r.json());
            })
            .then(upRes => {
                if (upRes.status !== 'OK') throw new Error(upRes.msg || "Server rejected the re-encrypted upload.");
            })
            .catch(err => {
                console.error("E2E OnlyOffice Save Error:", err);
                myCloudShowAlert('Encryption Error', 'Failed to securely save your document. To protect your privacy, the temporary file has been permanently deleted, but your most recent edits have been lost.<br><br>Error: ' + err.message);
            })
            .finally(() => {
                cleanupAndFinish();
            });
        };

        if (window.myCloudOoPolling) return;
        window.myCloudOoPolling = true;

        let attempts = 0;
        const maxAttempts = 30;
        const pollServer = () => {
            attempts++;
            if (attempts > maxAttempts) {
                window.myCloudOoPolling = false;
				myCloudShowAlert('Timeout', 'OnlyOffice took too long to save. Your latest changes may not have been captured.');
                executeEncryption();
                return;
            }

            const fd = new URLSearchParams({ 
                myCloud_action: 'check_office_state', 
                myCloud_key: myCloudState.key, 
                myCloud_token: window.myCloudCsrfToken, 
                docKey: docKey 
            });

            fetch(reqUrl, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.status === 'OK' && res.ready) {
                    window.myCloudOoPolling = false;
					executeEncryption(); 
                } else {
                    setTimeout(pollServer, 2000);
                }
            })
            .catch(() => setTimeout(pollServer, 2000));
        };

        pollServer();
        return;
    }

    // STANDARD REFRESH (No E2E hook)
    cleanupAndFinish();
}

</script>