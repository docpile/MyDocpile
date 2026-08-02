<?php
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) die('Direct access not permitted');
?>
<script>

			console.log("Beta!!!!");

/* --- GLOBAL RECIPIENT TILING LOGIC --- */
window._emlAddRecipientTile = function(name, rawEmailStr, inputEl) {
    const input = inputEl || document.getElementById('emlTo');
    const container = input.parentElement;
    
    let finalName = name;
    let finalEmail = rawEmailStr;
    
    // Support formats like "John Doe <john@doe.com>"
    const match = rawEmailStr.match(/^(.*?)\s*<([^>]+)>$/);
    if (match) {
        finalName = match[1].trim().replace(/['"]/g, '');
        finalEmail = match[2].trim();
    }

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    
    if (!emailRegex.test(finalEmail)) {
        input.style.color = 'var(--danger)';
        return false; // Return false so the input handler knows it failed
    }
    input.style.color = '';

    const tile = document.createElement('div');
    tile.className = 'ce-email-tile';
    tile.dataset.email = finalEmail;
    
    const displayTxt = finalName && finalName !== finalEmail ? finalName : finalEmail;
    const safeName = myCloudEscapeHtml(finalName || '').replace(/\\/g, "\\\\").replace(/'/g, "\\'");
    const safeEmail = myCloudEscapeHtml(finalEmail).replace(/\\/g, "\\\\").replace(/'/g, "\\'");
    
    tile.innerHTML = `<span onmouseenter="window._emailShowPopup(event, '${safeName}', '${safeEmail}')" onmouseleave="window._emailHidePopup()">${myCloudEscapeHtml(displayTxt)}</span><span onclick="window._emailHidePopup(); this.parentElement.remove()" style="cursor:pointer; margin-left:6px; font-weight:bold;">×</span>`;
    container.insertBefore(tile, input);
    
    return true;
};

let myCloudEditorLoaded = false;
let myCloudEmailEditorInstance = null;

function myCloudLoadEmailEditor() {
    return new Promise((resolve, reject) => {
        if (myCloudEditorLoaded) return resolve();
        
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = '/script/emailedit/suneditor.min.css';
        document.head.appendChild(link);

        const script = document.createElement('script');
        script.src = '/script/emailedit/suneditor.min.js';
        script.onload = () => {
            myCloudEditorLoaded = true;
            resolve();
        };
        script.onerror = reject;
        document.head.appendChild(script);
    });
}

// --- ATTACHMENT HANDLING ---
window._emlComposerAttachments = [];

window._emlRemoveAttachment = function(idx) {
    if (window._emlComposerAttachments[idx] && window._emlComposerAttachments[idx].localUrl) {
        URL.revokeObjectURL(window._emlComposerAttachments[idx].localUrl);
    }
    window._emlComposerAttachments.splice(idx, 1);
    window._emlRenderAttachments();
    if (typeof window.markDirty === 'function') window.markDirty();
};

window._emlRenderAttachments = function() {
    const wrap = document.getElementById('emlAttachmentsWrap');
    if (!wrap) return;
    wrap.innerHTML = '';
    
    window._emlComposerAttachments.forEach((f, idx) => {
        const size = typeof myCloudFormatBytes === 'function' ? myCloudFormatBytes(f.size) : Math.round(f.size/1024)+' KB';
        const ext = (f.name || '').split('.').pop().toLowerCase();
        const isPrev = typeof myCloudConfig !== 'undefined' && myCloudConfig.preview && myCloudConfig.preview.includes(ext);
        
        const el = document.createElement('div');
        el.className = 'ce-email-tile';
        el.style.cssText = 'background:var(--gray-15); border-radius:4px; padding:4px 8px; font-size:12px; height:auto; margin-bottom:4px; display:inline-flex; align-items:center;';
        
        el.innerHTML = '<span class="myCloudIcon" style="margin-inline-end:4px;">📎</span>' +
                   '<span style="max-width:150px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">' + myCloudEscapeHtml(f.name) + '</span>' +
                   '<span style="opacity:0.6; margin-inline-start:6px;">' + size + '</span>';
                   
        if (isPrev && f.localUrl) {
            const prevBtn = document.createElement('button');
            prevBtn.type = 'button';
            prevBtn.title = typeof myCloud_LANG !== 'undefined' && myCloud_LANG.preview ? myCloud_LANG.preview : 'Preview';
            prevBtn.style.cssText = "background:transparent; border:none; margin-inline-start:8px; cursor:pointer; color:var(--text-secondary); display:inline-flex; align-items:center; padding:0 4px;";
            prevBtn.onmouseover = function() { this.style.color = 'var(--text-primary)'; };
            prevBtn.onmouseout = function() { this.style.color = 'var(--text-secondary)'; };
            prevBtn.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>';
            prevBtn.onclick = function(e) {
                e.stopPropagation();
                window.myCloudOpenPreview(f.localUrl, f.name, f.name);
            };
            el.appendChild(prevBtn);
        }
        
        const remBtn = document.createElement('span');
        remBtn.title = 'Remove';
        remBtn.style.cssText = "margin-inline-start:8px; cursor:pointer; color:var(--danger); font-weight:bold; font-size:16px;";
        remBtn.textContent = '×';
        remBtn.onclick = function(e) { e.stopPropagation(); window._emlRemoveAttachment(idx); };
        el.appendChild(remBtn);
		
        wrap.appendChild(el);
    });
};

window._emailAttachFromCloud = function() {
    const L = typeof myCloud_LANG !== 'undefined' ? myCloud_LANG : {};
    window._emailShowCloudTreeModal({
        mode: 'file',
        title: L.attach_from_cloud || 'Attach from Cloud',
        okText: L.attach_files || 'Attach',
        requireWrite: false,
        onConfirm: async (currentCloud, currentPath, selectedItemData) => {
            if (typeof myCloudCreateProgressUI === 'function') myCloudCreateProgressUI((L.loading || 'Loading') + '...');
            
            const filename = currentPath.split('/').pop();
            const fd = new URLSearchParams({
                myCloud_action: 'get_download_token',
                myCloud_key: currentCloud,
                myCloud_token: window.myCloudCsrfToken,
                path: currentPath,
                filename: filename,
                preview: '0'
            });

            try {
                const tokenRes = await fetch('', { method: 'POST', body: fd }).then(r => r.json());
                if (tokenRes.status !== 'OK') throw new Error(tokenRes.msg || "Token failed");
                
                const blob = await fetch('?myCloud_token=' + tokenRes.token).then(r => r.blob());
                
                let finalBlob = blob;
                let finalName = filename;

                if (typeof myCloudCrypto !== 'undefined' && myCloudCrypto.isDirEncrypted(currentPath)) {
                    const cRoot = myCloudCrypto.getCryptoRoot(currentPath);
                    if (myCloudCrypto.isDirUnlocked(cRoot)) {
                        finalBlob = await myCloudCrypto.decryptFile(cRoot, blob);
                        finalName = await myCloudCrypto.decryptName(cRoot, filename);
                    } else {
                        throw new Error("Source folder is an encrypted vault and is currently locked.");
                    }
                }
                
                const fileObj = new File([finalBlob], finalName, { type: finalBlob.type });
                if (typeof myCloudCloseProgressUI === 'function') myCloudCloseProgressUI();
                
                window._emlHandleFiles([fileObj]);
            } catch (e) {
                if (typeof myCloudCloseProgressUI === 'function') myCloudCloseProgressUI();
                if (typeof myCloudShowAlert === 'function') myCloudShowAlert(L.error_prefix || 'Error', e.message);
            }
        }
    });
};

window._emlHandleFiles = async function(eventOrFiles) {
    // Determine if we received an event (from the button) or a FileList (from drag and drop)
    let fileList = [];
    if (eventOrFiles && eventOrFiles.target && eventOrFiles.target.files) {
        fileList = Array.from(eventOrFiles.target.files);
    } else if (eventOrFiles && eventOrFiles.length) {
        fileList = Array.from(eventOrFiles);
    }

    if (!fileList || fileList.length === 0) return;

    const L = typeof myCloud_LANG !== 'undefined' ? myCloud_LANG : {};
    if (typeof myCloudCreateProgressUI === 'function') myCloudCreateProgressUI(L.uploading_attachments || 'Uploading attachments...');

    const textEl = document.getElementById('myCloudProgressText');
    if (textEl) textEl.textContent = (L.uploading_attachments || 'Uploading attachments...');

    try {
        const uploadPromises = fileList.map(async (file, i) => {
            const safeFileName = file.name;

            const fd = new FormData();
            fd.append('myCloud_action', 'email_upload_temp_attach');
            fd.append('myCloud_key', myCloudState.key);
            fd.append('myCloud_token', window.myCloudCsrfToken);
            fd.append('file', file);
            
            const response = await fetch('', { method: 'POST', body: fd });
            const res = await response.json();

            if (res.status === 'OK') {
                window._emlComposerAttachments.push({
                    name: res.name || safeFileName,
                    size: file.size,
                    tmp_path: res.tmp_path,
                    localUrl: URL.createObjectURL(file)
                });
            } else {
                throw new Error(res.msg || "Upload failed for " + safeFileName);
            }
        });

        // Execute all uploads concurrently
        await Promise.all(uploadPromises);
    } catch (e) {
        if (typeof myCloudShowAlert === 'function') myCloudShowAlert(L.error_prefix || 'Error', (L.failed_upload_attach || 'Failed to upload attachments: ') + e.message);
    }
    
    if (typeof myCloudCloseProgressUI === 'function') myCloudCloseProgressUI();
    if (typeof window._emlRenderAttachments === 'function') window._emlRenderAttachments();
    
    const inputEl = document.getElementById('emlAttachInput');
    if (inputEl) inputEl.value = '';
    
    if (typeof window.markDirty === 'function') window.markDirty();
};


// --- MAIN COMPOSER FUNCTION ---
// --- MAIN COMPOSER FUNCTION ---
window.myCloudShowEmailComposer = function(prefill = null) {
    const overlay = document.getElementById('myCloudModalOverlay');
    const modal = document.getElementById('myCloudModal');
    if (typeof myCloudResetModal === 'function') myCloudResetModal();

    window._emlIsDirty = false;
    window.markDirty = () => { window._emlIsDirty = true; };

    const L = typeof myCloud_LANG !== 'undefined' ? myCloud_LANG : {};
    let draftUid = prefill && prefill.draftUid ? prefill.draftUid : '';
    let draftFolder = prefill && prefill.draftFolder ? prefill.draftFolder : '';
    const isDraft = prefill && prefill.isDraft === true;
	const isResend = prefill && prefill.isResend === true;

    // Centralized Engine for Send/Draft
    const execAction = function(btnEl, phpAction, isAutoSave = false) {
        const fromEl = document.getElementById('emlFrom');
        if (!fromEl) return;
        const L = typeof myCloud_LANG !== 'undefined' ? myCloud_LANG : {};
        const fromRaw = fromEl.value;
        const fromParts = fromRaw.split('|');
        const targetAccId = fromParts[0];
        const from = fromParts.slice(1).join('|');
        
        const getRecipients = (inputId, tileContainerId) => {
            const tileEls = document.querySelectorAll(`#${tileContainerId} .ce-email-tile`);
            const tiled = Array.from(tileEls).map(t => t.dataset.email).join(', ');
            const inputField = document.getElementById(inputId);
            const manual = inputField ? inputField.value.trim() : '';
            return [tiled, manual].filter(Boolean).join(', ');
        };

        const to = getRecipients('emlTo', 'emlToTiles');
        const cc = getRecipients('emlCc', 'emlCcTiles');
        const bcc = getRecipients('emlBcc', 'emlBccTiles');
        const subjEl = document.getElementById('emlSubject');
        const subj = subjEl ? subjEl.value.trim() : '';
        const body = myCloudEmailEditorInstance ? myCloudEmailEditorInstance.getContents() : '';

        if (phpAction !== 'email_save_draft' && !to && !cc && !bcc) { 
            myCloudShowAlert(L.error_prefix || 'Error', L.enter_recipient || "Please enter a recipient."); 
            return; 
        }

        const devKey = typeof myCloudGetCurrentDeviceKey === 'function' ? myCloudGetCurrentDeviceKey() : 'desktop';

        const executeSend = async () => {
            if (btnEl) {
                btnEl.disabled = true;
                btnEl.textContent = (phpAction === 'email_save_draft' ? (L.saving || 'Saving...') : (L.sending || 'Preparing...'));
            } else {
                if (typeof myCloudShowLoading === 'function') myCloudShowLoading();
            }

            let finalBodyHtml = body;
            
            // --- NEW: SMART ATTACHMENT / CLOUD LINK INJECTION ---
            let attachmentsProcessed = false;
            let finalAttachments = [...(window._emlComposerAttachments || [])];

            if (phpAction === 'email_send' && finalAttachments.length > 0) {
                // Calculate Base64 overhead size (approx 1.37x the raw binary size)
                const totalRawSize = finalAttachments.reduce((acc, att) => acc + att.size, 0);
                const totalEncodedSize = totalRawSize * 1.37;
                const sizeThreshold = 20 * 1024 * 1024; // 20MB
                
                const writeableClouds = [];
                let hiddenAttachmentCloud = null;
				if (typeof myCloudCloudConfig !== 'undefined') {
                    for (const [k, c] of Object.entries(myCloudCloudConfig)) {
                        if (c.interface === 'hidden' && c.rights !== 'read-only' && c.rights !== 'no-access' ) {
                            hiddenAttachmentCloud = k;
                        } else if ((c.interface || 'default') !== 'email' && c.interface !== 'hidden' && c.rights !== 'read-only' && c.rights !== 'no-access' && c.is_private) {
							writeableClouds.push(k);
                        }
                    }
                }

                if (totalEncodedSize > sizeThreshold) {
                    if (writeableClouds.length === 0 && !hiddenAttachmentCloud) {
						return myCloudShowAlert(L.error_prefix || 'Error', L.email_too_large || 'Attachments exceed the 20MB limit and no writable cloud is available to host them.');
                    }
                    
                    const wantsCloud = await new Promise(resolve => {
                        myCloudShowAlert(
                            L.email_too_large_title || 'Attachments Too Large',
                            (L.email_too_large_msg || 'Your attachments exceed the 20MB email limit. They must be uploaded to the cloud and sent as a secure link.') + '<br><br>' + (L.proceed_ask || 'Do you want to proceed?'),
                            () => resolve(true)
                        );
                        
                        setTimeout(() => {
                            const alertBox = document.getElementById('myCloudAlertBox');
                            if (alertBox) {
                                const closeX = alertBox.querySelector('.myCloudClose');
                                if (closeX) {
                                    const oldClose = closeX.onclick;
                                    closeX.onclick = (e) => { if(oldClose) oldClose(e); resolve(false); };
                                }
                                alertBox.onkeydown = (e) => {
                                    if (e.key === 'Escape') { e.preventDefault(); window.myCloudCloseAlert(); resolve(false); }
                                };
                            }
                        }, 10);
                    });
                    
                    if (!wantsCloud) {
                        if (btnEl) { btnEl.disabled = false; btnEl.textContent = L.send || 'Send'; }
                        return;
                    }
                    attachmentsProcessed = 'mandatory';
                } else if ((writeableClouds.length > 0 || hiddenAttachmentCloud) && totalRawSize > 10 * 1024 * 1024) {
                    // Optional Cloud Detach if attachments are > 10MB in size
                    const wantsCloud = await new Promise(resolve => {
                        myCloudShowAlert(
                            L.cloud_detach_title || 'Optimize Attachments?',
                            (L.cloud_detach_msg || 'Would you like to host these attachments in the cloud and send a link instead of attaching them directly to the email?'),
                            () => resolve(true)
                        );
                        
                        setTimeout(() => {
                            const alertBox = document.getElementById('myCloudAlertBox');
                            if (alertBox) {
                                const btns = alertBox.querySelectorAll('button');
                                if (btns.length > 1) {
                                    btns[0].textContent = L.yes_cloud || 'Yes, use Cloud';
                                    btns[1].textContent = L.no_attach || 'No, attach normally';
                                    btns[1].onclick = () => { window.myCloudCloseAlert(); resolve(false); };
                                }
                                const closeX = alertBox.querySelector('.myCloudClose');
                                if (closeX) {
                                    const oldClose = closeX.onclick;
                                    closeX.onclick = (e) => { if(oldClose) oldClose(e); resolve(false); };
                                }
                                alertBox.onkeydown = (e) => {
                                    if (e.key === 'Escape') { e.preventDefault(); window.myCloudCloseAlert(); resolve(false); }
                                };
                            }
                        }, 10);
                    });
                    
                    if (wantsCloud) attachmentsProcessed = 'optional';
                }

                if (attachmentsProcessed) {
                    if (btnEl) btnEl.textContent = L.preparing_cloud || 'Preparing Cloud...';
                    else if (typeof myCloudShowLoading === 'function') myCloudShowLoading();

                    // Determine Target Cloud
                    let targetCloud = hiddenAttachmentCloud;
                    if (!targetCloud) {
                        targetCloud = myCloudState.settings[devKey].lastEmailSaveCloud || writeableClouds[0];
                        if (!writeableClouds.includes(targetCloud)) targetCloud = writeableClouds[0];
                    }

                    // Generate Name: YYYY-MM-DD_Recipient_Subject (Max 40 chars)
                    const dateStr = new Date().toISOString().split('T')[0];
                    const firstRcpt = to.split(',')[0].replace(/.*<([^>]+)>.*/, '$1').split('@')[0].trim();
                    const cleanSubj = subj.replace(/[^a-zA-Z0-9 ]/g, '').trim() || 'Files';
                    
                    let folderName = `${dateStr}_${firstRcpt}_${cleanSubj}`;
                    if (folderName.length > 40) folderName = folderName.substring(0, 40).trim();
                    folderName = folderName.replace(/\s+/g, '_');

                    const mailRoot = '/.mail';
                    const attRoot = '/.mail/attachments';
                    const targetFolder = `${attRoot}/${folderName}`;

                    // 1. Ensure Root Folders Exist
                    await fetch('', { method: 'POST', body: new URLSearchParams({ myCloud_action: 'mkdir', myCloud_key: targetCloud, myCloud_token: window.myCloudCsrfToken, parent: '/', name: '.mail' }) });
                    await fetch('', { method: 'POST', body: new URLSearchParams({ myCloud_action: 'mkdir', myCloud_key: targetCloud, myCloud_token: window.myCloudCsrfToken, parent: mailRoot, name: 'attachments' }) });

                    // 2. Create Specific Email Folder (Handle Collisions)
                    let finalFolderName = folderName;
                    let mkRes = await fetch('', { method: 'POST', body: new URLSearchParams({ myCloud_action: 'mkdir', myCloud_key: targetCloud, myCloud_token: window.myCloudCsrfToken, parent: attRoot, name: finalFolderName }) }).then(r=>r.json());
                    
                    if (mkRes.status === 'ERR' && mkRes.msg === 'Exists') {
                        finalFolderName = folderName + '_' + Math.random().toString(36).substr(2, 4);
                        mkRes = await fetch('', { method: 'POST', body: new URLSearchParams({ myCloud_action: 'mkdir', myCloud_key: targetCloud, myCloud_token: window.myCloudCsrfToken, parent: attRoot, name: finalFolderName }) }).then(r=>r.json());
                    }
                    
                    if (mkRes.status !== 'OK') {
                        if (typeof myCloudHideLoading === 'function') myCloudHideLoading();
                        if (btnEl) { btnEl.disabled = false; btnEl.textContent = L.send || 'Send'; }
                        return myCloudShowAlert(L.error_prefix || 'Error', 'Failed to create cloud attachment folder.');
                    }

                    const finalFolderPath = `${attRoot}/${finalFolderName}`;

                    // 3. Server-Side Ingestion of Temp Files
                    try {
                        const mvPromises = finalAttachments.map(att => {
                            const upFd = new URLSearchParams({
                                myCloud_action: 'cloud_ingest_att',
                                myCloud_key: targetCloud,
                                myCloud_token: window.myCloudCsrfToken,
                                tmp_path: att.tmp_path,
                                dest: finalFolderPath,
                                name: att.name
                            });
                            return fetch('', { method: 'POST', body: upFd })
                                .then(r => r.text())
                                .then(text => {
                                    try { return JSON.parse(text); } 
                                    catch(e) { return { status: 'ERR', msg: 'Server error: ' + text.substring(0, 40) }; }
                                });
                        });

                        const mvResults = await Promise.all(mvPromises);
                        const mvFailed = mvResults.find(r => r.status !== 'OK');
                        
                        if (mvFailed) throw new Error(mvFailed.msg || 'Copy operation failed.');
                        
                    } catch (ingestErr) {
                        if (typeof myCloudHideLoading === 'function') myCloudHideLoading();
                        if (btnEl) { btnEl.disabled = false; btnEl.textContent = L.send || 'Send'; }
                        return myCloudShowAlert(L.error_prefix || 'Error', (L.save_cloud_error || 'Failed to save to cloud:') + ' ' + ingestErr.message);
                    }

                    // 4. Create Share Link
                    const expiryDate = new Date();
                    expiryDate.setMonth(expiryDate.getMonth() + 1); // 1 Month Default

                    const shareFd = new URLSearchParams({
                        myCloud_action: 'share-create',
                        myCloud_key: targetCloud,
                        myCloud_token: window.myCloudCsrfToken,
                        path: finalFolderPath,
                        name: 'Email Attachments',
                        days: 'custom',
                        expire_date: expiryDate.toISOString().split('T')[0],
                        max_downloads: 0,
                        permission: 'read'
                    });

                    const shareRes = await fetch('', { method: 'POST', body: shareFd }).then(r=>r.json());
                    if (shareRes.status !== 'OK') {
                        if (typeof myCloudHideLoading === 'function') myCloudHideLoading();
                        if (btnEl) { btnEl.disabled = false; btnEl.textContent = L.send || 'Send'; }
                        return myCloudShowAlert(L.error_prefix || 'Error', L.failed_share_link || 'Failed to generate share link.');
                    }

                    // 5. Inject Link into Body
                    const expString = expiryDate.toLocaleDateString();
                    const linkHtml = 
                        '<div style="font-family: sans-serif; font-size: 14px; color: var(--text-primary); margin-bottom: 20px;">' +
                            '<b>' + (L.cloud_attachments || 'Attachments have been securely hosted in the cloud:') + '</b><br>' +
                            '<a href="' + shareRes.link + '" style="color: var(--accent-primary); text-decoration: none;">' + shareRes.link + '</a><br>' +
                            '<small style="color: var(--text-secondary);">' + (L.link_expires || 'Link expires on:') + ' ' + expString + '</small>' +
                        '</div><hr style="border: 0; border-top: 1px solid var(--border-default); margin-bottom: 20px;">';

                    finalBodyHtml = linkHtml + finalBodyHtml;
                    
                    // Clear attachments from the actual email payload
                    finalAttachments = [];
                }
            }

            // --- AUTO-ENCRYPT CHECK ON SEND ---
            if (phpAction === 'email_send') {
                const uniqueEmails = [...new Set([to, cc, bcc].join(',').split(',').map(e => e.replace(/.*<([^>]+)>.*/, '$1').trim().toLowerCase()).filter(Boolean))];
                
                if (uniqueEmails.length > 0) {
                    const pubKeys = [];
                    const ownPubKey = myCloudEmailState.accounts[targetAccId]?.pgp_public_key;
                    if (ownPubKey) pubKeys.push(ownPubKey);
                    let allFound = true;

                    for (let i = 0; i < uniqueEmails.length; i++) {
                        const cleanEmail = uniqueEmails[i];
                        const allContacts = [...(window.myCloudEmailState.contacts || []), ...(window.myCloudEmailState.autoContacts || [])];
                        let contact = allContacts.find(c => c.emails && c.emails.some(e => e.val.toLowerCase() === cleanEmail));
                        
                        if (contact && contact.pgp_public_key && contact.pgp_public_key.includes('BEGIN PGP PUBLIC KEY')) {
                            pubKeys.push(contact.pgp_public_key);
                            continue;
                        }

                        let foundExternalKey = null;
                        const sFd = new URLSearchParams({ myCloud_action: 'email_lookup_pubkey', myCloud_key: myCloudState.key, myCloud_token: window.myCloudCsrfToken, email: cleanEmail });
                        try {
                            const sRes = await fetch('', { method: 'POST', body: sFd }).then(r=>r.json());
                            if (sRes.status === 'OK' && sRes.pubkey) {
                                if (sRes.is_binary) {
                                    if (!window.openpgp) await new Promise((res, rej) => { const s = document.createElement('script'); s.src = '/script/openpgp/openpgp.min.js'; s.onload = res; s.onerror = rej; document.head.appendChild(s); });
                                    const binaryString = atob(sRes.pubkey);
                                    const bytes = new Uint8Array(binaryString.length);
                                    for (let j = 0; j < binaryString.length; j++) bytes[j] = binaryString.charCodeAt(j);
                                    const parsedKey = await window.openpgp.readKey({ binaryKey: bytes });
                                    foundExternalKey = parsedKey.armor(); 
                                } else { foundExternalKey = sRes.pubkey; }
                            }
                        } catch(e) {}

                        if (foundExternalKey) {
                            pubKeys.push(foundExternalKey);
                            if (!contact) contact = { id: 'auto_' + Math.random().toString(36).substr(2, 9), name: cleanEmail.split('@')[0], emails: [{type: 'Collected', val: cleanEmail}] };
                            contact.pgp_public_key = foundExternalKey;
                            const saveFd = new URLSearchParams({ myCloud_action: 'email_save_contact', myCloud_key: myCloudState.key, myCloud_token: window.myCloudCsrfToken, book_type: window.myCloudEmailState.contacts.some(c => c.id === contact.id) ? 'main' : 'auto', contact_id: contact.id, name: contact.name || '', emails: JSON.stringify(contact.emails || []), pgp_public_key: foundExternalKey });
                            fetch('', { method: 'POST', body: saveFd });
                            continue;
                        }
                        allFound = false; break;
                    }

                    if (allFound && pubKeys.length > 0) {
                        if (typeof myCloudHideLoading === 'function') myCloudHideLoading();
                        const wantsEncrypt = await new Promise((resolve) => {
                            myCloudShowAlert(
                                L.pgp_auto_encrypt_title || 'Encrypt Email?', 
                                L.pgp_auto_encrypt_msg || 'Secure delivery is available for all recipients. Do you want to encrypt this email end-to-end?', 
                                () => resolve(true)
                            );
                            setTimeout(() => {
                                const alertBox = document.getElementById('myCloudAlertBox');
                                if(alertBox){
                                    const btns = alertBox.querySelectorAll('button');
                                    if(btns.length > 1) {
                                        btns[0].textContent = L.send_encrypted || 'Send Encrypted';
                                        btns[1].textContent = L.send_unencrypted || 'Send Unencrypted';
                                        btns[1].onclick = () => { window.myCloudCloseAlert(); resolve(false); };
                                    }
                                    const closeX = alertBox.querySelector('.myCloudClose');
                                    if(closeX) {
                                        const oldClose = closeX.onclick;
                                        closeX.onclick = (e) => { if(oldClose) oldClose(e); resolve(null); };
                                    }
                                    alertBox.onkeydown = (e) => {
                                        if (e.key === 'Escape') {
                                            e.preventDefault(); e.stopPropagation();
                                            window.myCloudCloseAlert(); resolve(null);
                                        }
                                        if (e.key === 'Enter') {
                                            e.preventDefault(); e.stopPropagation();
                                            btns[0].click();
                                        }                                    }
                                }
                            }, 10);
                        });

                        if (wantsEncrypt === null) {
                            if (btnEl) { btnEl.disabled = false; btnEl.textContent = L.send || 'Send'; }
                            return;
                        }
                        if (wantsEncrypt) {
                            if (btnEl) btnEl.textContent = L.pgp_encrypting || 'Encrypting...';
                            else if (typeof myCloudShowLoading === 'function') myCloudShowLoading();
                            if (!window.openpgp) await new Promise((res, rej) => { const s = document.createElement('script'); s.src = '/script/openpgp/openpgp.min.js'; s.onload = res; s.onerror = rej; document.head.appendChild(s); });
                            try {
                                const parsedKeys = await Promise.all(pubKeys.map(k => window.openpgp.readKey({ armoredKey: k })));
                                const message = await window.openpgp.createMessage({ text: finalBodyHtml });
                                const encrypted = await window.openpgp.encrypt({ message: message, encryptionKeys: parsedKeys });
                                finalBodyHtml = encrypted;
                            } catch (e) {
                                if (typeof myCloudHideLoading === 'function') myCloudHideLoading();
                                myCloudShowAlert(L.pgp_encrypt_err || 'Encryption Error', e.message);
                                if (btnEl) { btnEl.disabled = false; btnEl.textContent = L.send || 'Send'; }
                                return;
                            }
                        } else {
                            if (btnEl) btnEl.textContent = L.sending || 'Preparing...';
                            else if (typeof myCloudShowLoading === 'function') myCloudShowLoading();
                        }
                    }
                }
            }

            const autoCollect = (myCloudState.settings && myCloudState.settings[devKey] && myCloudState.settings[devKey].emailAutoCollect !== false) ? '1' : '0';

            const fd = new FormData();
            fd.append('myCloud_action', phpAction);
            fd.append('myCloud_key', myCloudState.key);
            fd.append('myCloud_token', window.myCloudCsrfToken);
			fd.append('auto_collect', autoCollect);
            fd.append('account_id', targetAccId);
            fd.append('from', from);
            fd.append('to', to);
            fd.append('cc', cc);
            fd.append('bcc', bcc);
            fd.append('subject', subj);
            fd.append('body', finalBodyHtml);

            const reqReceipt = document.getElementById('emlReadReceiptCb');
            if (phpAction === 'email_send' && reqReceipt && reqReceipt.checked) fd.append('read_receipt', '1');

            // Add 10-second buffer for undo send
            if (phpAction === 'email_send') fd.append('undo_buffer', '5');

            if (draftUid) {
                fd.append('draft_uid', draftUid);
                fd.append('draft_folder', draftFolder);
            }

            // FLAT POST KEYS: No arrays, no JSON, no Base64. 100% PHP safe.
            if (finalAttachments && finalAttachments.length > 0) {
                fd.append('att_count', finalAttachments.length);
                finalAttachments.forEach((att, idx) => {
                    fd.append('att_name_' + idx, att.name);
                    fd.append('att_path_' + idx, att.tmp_path);
                });
            } else {
                fd.append('att_count', 0);
            }

            // Feature #4 Extension: Attach Public Key dynamically
            const attachPgpCb = document.getElementById('emlAttachPgpCb');
            if (phpAction === 'email_send' && attachPgpCb && attachPgpCb.checked) {
                const ownPubKey = myCloudEmailState.accounts[targetAccId]?.pgp_public_key;
                if (ownPubKey) {
                    try {
                        const keyFile = new File([ownPubKey], 'publickey.asc', { type: 'application/pgp-keys' });
                        const pFd = new FormData();
                        pFd.append('myCloud_action', 'email_upload_temp_attach');
                        pFd.append('myCloud_key', myCloudState.key);
                        pFd.append('myCloud_token', window.myCloudCsrfToken);
                        pFd.append('file', keyFile);
                        
                        const pRes = await fetch('', { method: 'POST', body: pFd }).then(r=>r.json());
                        if (pRes.status === 'OK') {
                            const attIdx = finalAttachments ? finalAttachments.length : 0;
                            fd.append('att_name_' + attIdx, 'publickey.asc');
                            fd.append('att_path_' + attIdx, pRes.tmp_path);
                            fd.set('att_count', attIdx + 1);
							finalAttachments.push({ name: 'publickey.asc', tmp_path: pRes.tmp_path });
                        }
                    } catch(e) { console.error("Failed to attach PGP key", e); }
                }
            }

            fetch('', { method: 'POST', body: fd }).then(myCloudCheckResponse).then(res => {
                if (!btnEl && typeof myCloudHideLoading === 'function') myCloudHideLoading();
                
                if (res.status === 'OK') {

                    // Update draft UID for subsequent auto-saves to prevent orphans
                    if (res.new_draft_uid && res.new_draft_folder) {
                        draftUid = res.new_draft_uid;
                        draftFolder = res.new_draft_folder;
                    }

                    if (phpAction === 'email_send') {
                        // Refresh contacts so auto-collected emails are instantly available
                        if (typeof window.myCloudEmailLoadContacts === 'function') {
                            window.myCloudEmailLoadContacts();
                        }

                         // --- SERVER-SIDE UNDO SEND TOAST (Updated for Countdown) ---
                         let tc = document.getElementById('ce-email-toast-container');
                         if (!tc) {
                             tc = document.createElement('div');
                             tc.id = 'ce-email-toast-container';
                             document.body.appendChild(tc);
                         }
                         
                         let timeLeft = 5;
                         const toast = document.createElement('div');
                         toast.className = 'ce-email-undo-toast';
                         toast.innerHTML = `<span>${(L.sending_delayed || 'Sending in %s...').replace('%s', timeLeft + 's')}</span> <button class="ce-email-undo-btn">${L.undo || 'Undo'}</button>`;
                         tc.appendChild(toast);
                         
                         let isUndone = false;
                         
                         // Countdown logic
                         const deleteInterval = setInterval(() => {
                             timeLeft--;
                             const span = toast.querySelector('span');
                             if (span) span.textContent = (L.sending_delayed || 'Sending in %s...').replace('%s', timeLeft + 's');
                             
                             if (timeLeft <= 0) {
                                 clearInterval(deleteInterval);
                                 if (!isUndone) {
                                     toast.style.opacity = '0'; 
                                     setTimeout(() => toast.remove(), 300);
                                     fetch('', { method: 'POST', body: new URLSearchParams({ myCloud_action: 'email_process_outbox', myCloud_key: myCloudState.key, myCloud_token: window.myCloudCsrfToken }) });
                                 }
                             }
                         }, 1000);
                         
                         toast.querySelector('button').onclick = () => {
                             isUndone = true;
                             clearInterval(deleteInterval);
                             toast.style.opacity = '0'; 
                             setTimeout(() => toast.remove(), 300);
                             
                             // Notify server to abort the job
                             fetch('', { method: 'POST', body: new URLSearchParams({ myCloud_action: 'email_undo_send', myCloud_key: myCloudState.key, myCloud_token: window.myCloudCsrfToken, task_id: res.task_id }) });
                             
                             if (typeof myCloudShowAlert === 'function') myCloudShowAlert(L.undone || 'Undone', L.send_cancelled || 'Message sending cancelled. It remains in your Drafts.');
                         };
						
                        // Background Poller Trigger
                        const checkStatus = () => {
                            const pollFd = new URLSearchParams({
                                myCloud_action: 'email_check_send_status',
                                myCloud_key: myCloudState.key,
                                myCloud_token: window.myCloudCsrfToken,
                                task_id: res.task_id
                            });
                            fetch('', { method: 'POST', body: pollFd }).then(r => r.json()).then(pollRes => {
                                if (pollRes.status === 'success') {
                                    if (typeof cxToast === 'function') cxToast(L.email_sent_success || "Email sent successfully!", true);
                                    else if (typeof myCloudShowAlert === 'function') myCloudShowAlert(L.success || "Success", L.email_sent_success || "Email sent successfully!");
                                } else if (pollRes.status === 'error') {
                                    myCloudShowAlert('Error', pollRes.msg || "Send failed");
                                } else {
                                    setTimeout(checkStatus, 2000); 
                                }
                            });
                        };
                        setTimeout(checkStatus, 2000);
                        
                    } else {
                        if (typeof myCloudNotify === 'function') myCloudNotify(res.msg || L.draft_saved || 'Draft saved.');
                    }
                    window._emlIsDirty = false;
                    if (!isAutoSave) {
                        if (myCloudEmailEditorInstance) myCloudEmailEditorInstance.destroy();
                        myCloudCloseModal();
                    }
                    if (phpAction === 'email_save_draft' && myCloudEmailState.activeFolder === 'Drafts') {
                        myCloudEmailFetchMessages('Drafts', true);
                    }
                } else {
                    myCloudShowAlert(L.error_prefix || 'Error', res.msg || L.send_failed || "Send failed");
                    if (btnEl) {
                        btnEl.disabled = false;
                        btnEl.textContent = (phpAction === 'email_save_draft' ? (L.save_draft || 'Save Draft') : (L.send || 'Send'));
                    }
                }
            }).catch(() => {
                if (!btnEl && typeof myCloudHideLoading === 'function') myCloudHideLoading();
                myCloudShowAlert(L.error_prefix || 'Error', L.net_error || "Network error");
                if (btnEl) {
                    btnEl.disabled = false;
                    btnEl.textContent = (phpAction === 'email_save_draft' ? (L.save_draft || 'Save Draft') : (L.send || 'Send'));
                }
            });
        };

        if (!subj && phpAction === 'email_send') {
            myCloudShowAlert(L.warning || 'Warning', L.empty_subject_ask || 'Send this email without a subject?', executeSend);
        } else {
            executeSend();
        }
    };

    window._emlCleanupTempFiles = function() {
        if (!window._emlComposerAttachments || window._emlComposerAttachments.length === 0) return;
        const paths = window._emlComposerAttachments.map(a => a.tmp_path);

        window._emlComposerAttachments.forEach(a => {
            if (a.localUrl) URL.revokeObjectURL(a.localUrl);
        });

        const fd = new URLSearchParams({
            myCloud_action: 'email_cleanup_temp',
            myCloud_key: myCloudState.key,
            myCloud_token: window.myCloudCsrfToken,
            paths: JSON.stringify(paths)
        });
        if (navigator.sendBeacon) navigator.sendBeacon('', fd);
        else fetch('', { method: 'POST', body: fd, keepalive: true }).catch(()=>{});
        
        window._emlComposerAttachments = [];
    };

    window.myCloudBeforeCloseCallback = () => {
        if (window._emlIsDirty) {
            let overlay = document.getElementById('myCloudAlertOverlay');
            let alertModal = document.getElementById('myCloudAlertBox');
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.id = 'myCloudAlertOverlay';
                overlay.className = 'myCloudOverlay';
                overlay.style.zIndex = '100000';
                alertModal = document.createElement('div');
                alertModal.id = 'myCloudAlertBox';
                overlay.appendChild(alertModal);
                document.body.appendChild(overlay);
            }
            overlay.classList.remove('closing');
            if (alertModal) {
                alertModal.classList.remove('closing');
                alertModal.className = 'myCloudModal conflict ce-email-app-root'; 
            }
            overlay.style.display = 'flex';
            alertModal.onkeydown = null;

            const title = L.warning || 'Warning';
            const msg = L.save_draft_ask || 'Do you want to save a draft before closing?';
            
            let buttonsHtml = 
                '<button id="ceAlertSave" style="background:var(--accent-primary); color:#fff; min-width:80px; margin-inline-end:10px;">' + (L.save || 'Save') + '</button>' +
                '<button id="ceAlertDiscard" style="background:#e81123; color:#fff; min-width:80px; margin-inline-end:10px;">' + (L.discard || 'Discard') + '</button>' +
                '<button onclick="window.myCloudCloseAlert()" style="min-width:80px;">' + (L.cancel || 'Cancel') + '</button>';

            alertModal.innerHTML = 
                '<div class="myCloudModalHeader" style="justify-content:space-between; align-items:center; border-bottom:none; padding-bottom:0;">' +
                    '<span style="display:flex; align-items:center;">' +  myCloudSvgLogo + '&nbsp;' + myCloudEscapeHtml(title) + '</span>' +
                    '<span class="myCloudClose" onclick="window.myCloudCloseAlert()" style="cursor:pointer; color:var(--text-secondary); font-size:18px; line-height:1;">✕</span>' +
                '</div>' +
                '<div class="myCloudModalBody" style="padding: 24px; text-align:center;">' +
                    '<div style="margin-bottom:24px; font-size:14px; color:var(--text-primary); line-height:1.5;">' + msg + '</div>' +
                    '<div class="myCloudButtons" style="justify-content: center; flex-wrap:wrap; gap:10px; margin:0;">' + buttonsHtml + '</div>' +
                '</div>';

            document.getElementById('ceAlertSave').onclick = () => {
                window.myCloudCloseAlert();
                execAction(null, 'email_save_draft');
            };
            document.getElementById('ceAlertDiscard').onclick = () => {
                window.myCloudCloseAlert();
                window._emlIsDirty = false;
				window._emlCleanupTempFiles();
                if (typeof myCloudCloseModal === 'function') myCloudCloseModal();
            };

            setTimeout(() => { const b = document.getElementById('ceAlertSave'); if(b) b.focus(); }, 50);

            alertModal.setAttribute('tabindex', '-1');
            alertModal.onkeydown = (e) => {
                if (e.key === 'Escape') {
                    e.preventDefault(); e.stopPropagation();
                    window.myCloudCloseAlert();
                }
            };
            
            if (typeof myCloudApplyTheme === 'function') myCloudApplyTheme();

            return false;
        }
        window._emlCleanupTempFiles();
		return true;
    };

    overlay.style.display = 'flex';
    modal.className = 'myCloudModal ce-email-app-root';
    modal.style.width = '1200px';
    modal.style.maxWidth = '98vw';
    modal.style.height = '94vh';
    modal.style.maxHeight = '94vh';
    modal.style.display = 'flex';
    modal.style.flexDirection = 'column';

    const toVal = prefill && prefill.to ? myCloudEscapeHtml(prefill.to) : '';
    const subVal = prefill && prefill.subject ? myCloudEscapeHtml(prefill.subject) : '';
    const bodyVal = prefill && prefill.body ? prefill.body : '';
    const prefaceVal = prefill && prefill.prefaceText ? prefill.prefaceText : '';

    let defaultAccId = myCloudEmailState.activeAccount;
    if (defaultAccId === 'smartbox' || !myCloudEmailState.accounts[defaultAccId] || myCloudEmailState.accounts[defaultAccId].is_inactive) {
        defaultAccId = Object.keys(myCloudEmailState.accounts).find(k => !myCloudEmailState.accounts[k].is_inactive);
    }

    if (!defaultAccId) {
        myCloudShowAlert(L.error_prefix || 'Error', L.no_accs || 'No accounts configured.');
        myCloudCloseModal();
        return;
    }

    let fromOptions = '';
    let hasSelectedFrom = false;
    const defaultFromVal = prefill && prefill.from ? prefill.from : '';

    Object.keys(myCloudEmailState.accounts).forEach(id => {
        const a = myCloudEmailState.accounts[id];
		if (a.is_inactive) return;
        const publicName = a.sender_name ? myCloudEscapeHtml(a.sender_name) + ' ' : '';

        const rawOptMain = id + '|' + a.email;
        let isSelectedMain = '';
        if (defaultFromVal === rawOptMain || (!defaultFromVal && id === defaultAccId && !hasSelectedFrom)) {
            isSelectedMain = 'selected';
            hasSelectedFrom = true;
        }
            
        fromOptions += '<option value="' + id + '|' + myCloudEscapeHtml(a.email) + '" ' + isSelectedMain + '>' + publicName + '&lt;' + myCloudEscapeHtml(a.email) + '&gt;</option>';
       
        (a.aliases || []).forEach(alias => {
            const alEmail = typeof alias === 'object' ? alias.email : alias;
            const alName = typeof alias === 'object' && alias.sender_name !== undefined && alias.sender_name !== '' ? alias.sender_name : a.sender_name;
            const alPublicName = alName ? myCloudEscapeHtml(alName) + ' ' : '';
                const rawOptAlias = id + '|' + alEmail;
                let isSelectedAlias = '';
                if (defaultFromVal === rawOptAlias && !hasSelectedFrom) {
                    isSelectedAlias = 'selected';
                    hasSelectedFrom = true;
                }
                
                fromOptions += '<option value="' + id + '|' + myCloudEscapeHtml(alEmail) + '" ' + isSelectedAlias + '>' + alPublicName + '&lt;' + myCloudEscapeHtml(alEmail) + '&gt;</option>';
        });
    });

     const rowStyle = 'display:flex; align-items:flex-start; border-bottom:1px solid var(--border-subtle); padding-block:4px;';
     const labelStyle = 'width:50px; color:var(--text-secondary); font-size:13px; flex-shrink:0; user-select:none; display:flex; align-items:center; height:34px; margin-inline-start:20px;';
     const selectStyle = 'border:none; outline:none; background:transparent; font-size:14px; color:var(--text-primary); padding:0; margin:0; width:100%; font-family:inherit; height:34px; cursor:pointer;';
     const fieldStyle = 'border:none; outline:none; background:transparent; font-size:14px; color:var(--text-primary); padding:0; margin:0; width:100%; font-family:inherit; height:34px;';
     const tilesStyle = 'position:relative; display:flex; flex-wrap:wrap; gap:5px; align-items:center; flex:1; min-width:0; min-height:34px; cursor:text; border:none; background:transparent; padding:0; margin:0 20px 0 0; box-sizing:border-box;';

    modal.innerHTML = 
        '<div class="myCloudModalHeader" style="justify-content:space-between; flex-shrink:0;">' +
            '<span>' + myCloudSvgLogo + " "  + (L.compose_email || 'Compose Email') + '</span>' +
            '<button class="myCloudClose" onclick="myCloudCloseModal()">✕</button>' +
        '</div>' +
        '<div class="myCloudModalBody" style="display:flex; flex-direction:column; gap:0; overflow:hidden; flex:1; min-height:0; padding:0;">' +
            '<div style="position:relative; z-index:100; display:flex; flex-direction:column; padding:10px 0 0 0; background:var(--gray-00); flex-shrink:0; max-height:35vh; overflow-y:auto;">' +
                '<div style="' + rowStyle + '"><div style="' + labelStyle + '">' + (L.from || 'From') + '</div><select id="emlFrom" style="' + selectStyle + ' margin-inline-end:20px;" onchange="window.markDirty(); if(typeof window._emlUpdateSignature === \'function\') window._emlUpdateSignature(this.value);">' + fromOptions + '</select></div>' +
                '<div style="' + rowStyle + '">' +
                    '<div style="' + labelStyle + '">' + (L.to_placeholder || 'To') + '</div>' +
                    '<div id="emlToTiles" class="myCloudInlineInput" style="' + tilesStyle + '" onclick="document.getElementById(\'emlTo\').focus()">' +
                        '<input type="text" id="emlTo" style="border:none; outline:none; flex:1; min-width:150px; background:transparent; color:inherit; font-family:inherit; font-size:inherit; padding:2px;" autocomplete="off">' +
                        '<div id="emlSharedAutocomplete" class="ce-autocomplete-menu" style="position:fixed; z-index:999999; background:var(--gray-00); border:1px solid var(--border-medium); box-shadow:0 4px 15px rgba(0,0,0,0.3); max-height:250px; overflow-y:auto; min-width:280px; max-width:400px; border-radius:4px; display:none;"></div>' +
                    '</div>' +
                    '<button type="button" onclick="document.getElementById(\'emlCcWrap\').style.display=\'flex\'; document.getElementById(\'emlBccWrap\').style.display=\'flex\'; this.style.display=\'none\';" style="background:transparent; border:none; color:var(--text-secondary); cursor:pointer; padding:0 20px 0 8px; height:34px; display:flex; align-items:center; font-size:12px; font-weight:600; transition:color 0.1s;" onmouseover="this.style.color=\'var(--accent-primary)\'" onmouseout="this.style.color=\'var(--text-secondary)\'">Cc/Bcc</button>' +
                '</div>' +
                '<div id="emlCcWrap" style="' + rowStyle + ' display:none;">' +
                    '<div style="' + labelStyle + '">' + (L.cc_placeholder || 'Cc') + '</div>' +
                    '<div id="emlCcTiles" class="myCloudInlineInput" style="' + tilesStyle + '" onclick="document.getElementById(\'emlCc\').focus()">' +
                        '<input type="text" id="emlCc" style="border:none; outline:none; flex:1; min-width:150px; background:transparent; color:inherit; font-family:inherit; font-size:inherit; padding:2px;" autocomplete="off">' +
                    '</div>' +
                '</div>' +
                '<div id="emlBccWrap" style="' + rowStyle + ' display:none;">' +
                    '<div style="' + labelStyle + '">' + (L.bcc_placeholder || 'Bcc') + '</div>' +
                    '<div id="emlBccTiles" class="myCloudInlineInput" style="' + tilesStyle + '" onclick="document.getElementById(\'emlBcc\').focus()">' +
                        '<input type="text" id="emlBcc" style="border:none; outline:none; flex:1; min-width:150px; background:transparent; color:inherit; font-family:inherit; font-size:inherit; padding:2px;" autocomplete="off">' +
                    '</div>' +
                '</div>' +
                '<div style="' + rowStyle + ' border-bottom:1px solid var(--border-default); padding-block:4px;">' +
                    '<input type="text" id="emlSubject" placeholder="' + (L.subj_placeholder || 'Subject') + '" value="' + subVal + '" style="' + fieldStyle + ' font-size:16px; font-weight:500; margin-inline:20px; width:calc(100% - 40px);">' +
                '</div>' +
                '<div id="emlAttachmentsWrap" style="display:flex; flex-wrap:wrap; gap:8px; padding:8px 20px 0 20px; min-height:0; flex-shrink:0;"></div>' +
            '</div>' +
            
            '<div style="padding: 10px 20px 20px 20px; flex: 1; display: flex; flex-direction: column; min-height:0;">' +
            '<style>#emlEditorWrap .sun-editor{height:100%!important;display:flex!important;flex-direction:column!important;border:none!important;background:transparent!important;} #emlEditorWrap .sun-editor .se-container{flex:1!important;display:flex!important;flex-direction:column!important; min-height:0!important;} #emlEditorWrap .sun-editor .se-wrapper{flex:1!important;height:auto!important; min-height:0!important;} #emlEditorWrap .sun-editor .sun-editor-editable{height:100%!important; overflow-y:auto!important;}</style>' +
            '<div id="emlEditorWrap" style="position:relative; border:1px solid var(--border-default); border-radius:4px; overflow:hidden; background:var(--gray-00); flex:1; display:flex; flex-direction:column; min-height:150px;">' +
                '<div id="se-loading-indicator" style="padding:40px; text-align:center; color:var(--text-secondary);">' + (L.loading_editor || 'Loading Editor...') + '</div>' +
                '<textarea id="emlBodyEditor" style="width:100%; height:100%; opacity:0;"></textarea>' +
            '</div>' + '</div>' +
        '</div>' +

        '<div style="padding:15px 20px; border-top:1px solid var(--border-default); background:var(--gray-05); flex-shrink:0; border-radius:0 0 6px 6px;">' +
            '<div class="myCloudButtons" style="justify-content:space-between; width:100%; margin:0;">' +
                '<div style="display:flex; gap:10px;">' +
                   '<input type="file" id="emlAttachInput" multiple style="display:none;" onchange="window._emlHandleFiles(event)">' +
                   '<button type="button" onclick="document.getElementById(\'emlAttachInput\').click()" style="background:transparent; color:var(--text-primary); border:1px solid var(--border-medium); display:flex; align-items:center; gap:6px;"><svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M16.5 6v11.5c0 2.21-1.79 4-4 4s-4-1.79-4-4V5a2.5 2.5 0 0 1 5 0v10.5c0 .55-.45 1-1 1s-1-.45-1-1V6H10v9.5a2.5 2.5 0 0 0 5 0V5c0-2.21-1.79-4-4-4S6 2.79 6 5v11.5c0 3.87 3.13 7 7 7s7-3.13 7-7V6h-1.5z"/></svg> ' + (L.attach_files || 'Attach Files') + '</button>' +
                   ((typeof myCloudCloudConfig !== 'undefined' && Object.values(myCloudCloudConfig).some(c => (c.interface || 'default') !== 'email' && c.interface !== 'hidden' && c.rights !== 'no-access')) ?
                   '<button type="button" onclick="window._emailAttachFromCloud()" style="background:transparent; color:var(--text-primary); border:1px solid var(--border-medium); display:flex; align-items:center; gap:6px;"><svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96z"/></svg><span class="hide-mobile">' + (L.attach_from_cloud || 'Attach from Cloud') + '</span></button>' : '') +
                 '</div>' +
                '<div style="display:flex; gap:10px; align-items:center;">' +
                     '<label style="font-size:12px; color:var(--text-secondary); display:flex; align-items:center; gap:6px; cursor:pointer;"><input type="checkbox" id="emlReadReceiptCb" class="myCloudCheckbox" style="margin:0;"> ' + (L.req_read_receipt || 'Request Read Receipt') + '</label>' +
                     '<div style="width:1px; height:20px; background:var(--border-default); margin:0 4px;"></div>' +
                    '<label style="font-size:12px; color:var(--text-secondary); display:flex; align-items:center; gap:6px; cursor:pointer;"><input type="checkbox" id="emlAttachPgpCb" class="myCloudCheckbox" style="margin:0;"> Attach my Public Key</label>' +
                    '<button type="button" onclick="myCloudCloseModal()">' + (L.cancel || 'Cancel') + '</button>' +
                   '<button type="button" id="emlSendBtn" style="background:var(--accent-primary); color:#fff; border-color:var(--accent-primary);">' + (L.send || 'Send') + '</button>' +
                '</div>' +
            '</div>' +
        '</div>';

    window._emlComposerAttachments = []; // reset attachments on open

    if (prefill && prefill.attachments && prefill.attachments.length > 0) {
        prefill.attachments.forEach(att => {
            if (att.tmp_name) {
                const attObj = {
                    name: att.filename || att.name,
                    size: att.size,
                    tmp_path: att.tmp_name,
					cid: att.cid || null
                };
                if (!att.is_inline) window._emlComposerAttachments.push(attObj);
                
                // Silently fetch previewable draft attachments into memory so they can be previewed
                const ext = (attObj.name || '').split('.').pop().toLowerCase();
                const isPrev = (typeof myCloudConfig !== 'undefined' && myCloudConfig.preview && myCloudConfig.preview.includes(ext)) || att.is_inline;
                
                if (isPrev && att.part && draftUid) {
                    const fd = new URLSearchParams({
                        myCloud_action: 'email_dl_attach',
                        myCloud_key: myCloudState.key,
                        myCloud_token: window.myCloudCsrfToken,
                        account_id: defaultAccId,
                        folder: draftFolder,
                        message_id: draftUid,
                        part: att.part,
                        filename: attObj.name
                    });
                    fetch('', { method: 'POST', body: fd })
                    .then(r => r.blob())
                    .then(blob => {
                        attObj.localUrl = URL.createObjectURL(blob);
                         if (att.is_inline && attObj.cid) {
                             // Inject the visual blob URL back into the editor payload to repair the broken image
                             if (myCloudEmailEditorInstance) {
                                 let content = myCloudEmailEditorInstance.getContents();
                                 content = content.replace(new RegExp('src="cid:' + attObj.cid.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '"', 'gi'), 'src="' + attObj.localUrl + '" data-cid="' + attObj.cid + '"');
                                 myCloudEmailEditorInstance.setContents(content);
                             }
                         } else {
                             if (typeof window._emlRenderAttachments === 'function') window._emlRenderAttachments();
                         }
                    }).catch(() => {});
                }
            }
        });
        setTimeout(() => { if (typeof window._emlRenderAttachments === 'function') window._emlRenderAttachments(); }, 100);
    }

    myCloudLoadEmailEditor().then(() => {
        const sunEditorGlobal = window.SUNEDITOR || window.suneditor;
        if (!sunEditorGlobal) throw new Error("SunEditor global missing.");

        const targetEditorEl = document.getElementById('emlBodyEditor');
        myCloudEmailEditorInstance = sunEditorGlobal.create(targetEditorEl, {
            width: '100%',
            height: '100%',
            minHeight: '250px',
            // Feature #3: Inline Image (CID) Upload Hook
            imageUploadHandler: function (xmlHttpRequest, info, core) {
                if (info && info.length > 0) {
                    const file = info[0];
                    if (typeof myCloudCreateProgressUI === 'function') myCloudCreateProgressUI(L.uploading_inline_img || 'Uploading inline image...');
                    const fd = new FormData();
                    fd.append('myCloud_action', 'email_upload_temp_attach');
                    fd.append('myCloud_key', myCloudState.key);
                    fd.append('myCloud_token', window.myCloudCsrfToken);
                    fd.append('file', file);
                    fd.append('is_inline', '1'); // Tell PHP to expect this as a CID

                    fetch('', { method: 'POST', body: fd }).then(r=>r.json()).then(res => {
                        if (typeof myCloudCloseProgressUI === 'function') myCloudCloseProgressUI();
                        if (res.status === 'OK') {
                            const cid = res.cid || res.tmp_path.split('/').pop();
                            window._emlComposerAttachments.push({ name: res.name || file.name, size: file.size, tmp_path: res.tmp_path, cid: cid, localUrl: URL.createObjectURL(file) });
                            window._emlRenderAttachments();
                            const imgUrl = res.tmp_url || URL.createObjectURL(file);
                            core.insertHTML('<img src="' + imgUrl + '" data-cid="' + cid + '" style="max-width:100%;">');
                            window.markDirty();
                        } else {
                            if (typeof myCloudShowAlert === 'function') myCloudShowAlert(L.error_prefix || 'Error', (L.img_upload_failed || 'Image upload failed: ') + res.msg);
                        }
                    }).catch(e => {
                        if (typeof myCloudCloseProgressUI === 'function') myCloudCloseProgressUI();
                        console.error("Inline image upload failed", e);
                    });
                }
            },
            buttonList: [
                ['undo', 'redo'],
                ['font', 'fontSize', 'formatBlock'],
                ['paragraphStyle', 'blockquote'],
                ['bold', 'underline', 'italic', 'strike', 'subscript', 'superscript'],
                ['fontColor', 'hiliteColor', 'textStyle'],
                ['removeFormat'],
                ['outdent', 'indent'],
                ['align', 'horizontalRule', 'list', 'lineHeight'],
                ['table', 'link', 'image']
            ],
            defaultStyle: 'font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #333333;'
        });

        myCloudEmailEditorInstance.onChange = window.markDirty;

        // Intercept drag and drop events inside the WYSIWYG editor
        if (myCloudEmailEditorInstance.core && myCloudEmailEditorInstance.core.context && myCloudEmailEditorInstance.core.context.element && myCloudEmailEditorInstance.core.context.element.wysiwyg) {
            const wysiwyg = myCloudEmailEditorInstance.core.context.element.wysiwyg;
            wysiwyg.addEventListener('dragover', (e) => { e.preventDefault(); });
            wysiwyg.addEventListener('drop', (e) => {
                const files = Array.from(e.dataTransfer.files || []);
                const nonImages = files.filter(f => !f.type.startsWith('image/'));
                if (nonImages.length > 0) {
                    window._emlHandleFiles(nonImages);
                    if (nonImages.length === files.length) { e.preventDefault(); e.stopPropagation(); }
                }
            });
        }

		const loadingInd = document.getElementById('se-loading-indicator');
        if (loadingInd) loadingInd.remove();

        let initialSig = '';
        if (!isResend) {
            if (defaultFromVal) {
                const parts = defaultFromVal.split('|');
                const initAcc = myCloudEmailState.accounts[parts[0]];
                if (initAcc) {
                    initialSig = initAcc.signature || '';
                    const fromEmail = parts.slice(1).join('|');
                    const alias = (initAcc.aliases || []).find(al => (typeof al === 'object' ? al.email : al) === fromEmail);
                    if (alias && typeof alias === 'object' && alias.signature !== undefined && alias.signature !== '') {
                        initialSig = alias.signature;
                    }
                }
            } else {
                const initAcc = myCloudEmailState.accounts[defaultAccId];
                if (initAcc) {
                    initialSig = initAcc.signature || '';
                }
            }
        }

        const SIG_START = '\u200B\u200C\u200D';
        const SIG_END = '\u200D\u200C\u200B';
        
        const isReplyOrForward = prefill && !isDraft && !isResend && (prefill.prefaceText || prefill.body);
        const willAddSignature = !isDraft && !isResend && initialSig;
        const topSpacing = (isReplyOrForward && !willAddSignature) ? '<p><br></p><p><br></p>' : '';

        const sigHtml = (isDraft || isResend || !initialSig) ? '' : '<p style="margin-bottom: 12pt;"><br></p>' + SIG_START + initialSig + SIG_END;
        const finalBody = topSpacing + prefaceVal + sigHtml + (bodyVal ? bodyVal : '');

        myCloudEmailEditorInstance.setContents(finalBody);

        if (isReplyOrForward) {
            setTimeout(() => {
                if (myCloudEmailEditorInstance && myCloudEmailEditorInstance.core) {
                    const wysiwyg = myCloudEmailEditorInstance.core.context.element.wysiwyg;
                    wysiwyg.focus();
                    if (wysiwyg.firstChild) {
                        const range = document.createRange();
                        const sel = window.getSelection();
                        range.setStart(wysiwyg.firstChild, 0);
                        range.collapse(true);
                        sel.removeAllRanges();
                        sel.addRange(range);
                    }
                }
            }, 150);
        }
       
        window._emlUpdateSignature = function(fromVal) {
            if (!myCloudEmailEditorInstance) return;
			if (isResend) return;
            const accId = fromVal.split('|')[0];
            const acc = myCloudEmailState.accounts[accId];
            if (!acc) return;
            
            let newSig = acc.signature || '';
            const fromEmail = fromVal.split('|').slice(1).join('|');
            const alias = (acc.aliases || []).find(al => (typeof al === 'object' ? al.email : al) === fromEmail);
            if (alias && typeof alias === 'object' && alias.signature !== undefined && alias.signature !== '') {
                newSig = alias.signature;
            }

            let content = myCloudEmailEditorInstance.getContents();
            const sigRegex = new RegExp('\u200B\u200C\u200D[\\s\\S]*?\u200D\u200C\u200B', 'g');
            
            if (sigRegex.test(content)) {
                content = content.replace(sigRegex, '\u200B\u200C\u200D' + newSig + '\u200D\u200C\u200B');
            } else {
                const replyMatch = content.match(/<div[^>]*style="[^"]*border-top:[^"]*"[^>]*>/i);
                if (replyMatch) {
                    content = content.replace(replyMatch[0], '\u200B\u200C\u200D' + newSig + '\u200D\u200C\u200B<br>' + replyMatch[0]);
                } else {
                    content += '<br>\u200B\u200C\u200D' + newSig + '\u200D\u200C\u200B';
                }
            }
            myCloudEmailEditorInstance.setContents(content);
        };
    }).catch((err) => {
        console.error("Editor Crash Details:", err);
        const loadingInd = document.getElementById('se-loading-indicator');
        if (loadingInd) loadingInd.innerHTML = '<span style="color:red; font-weight:bold;">' + (L.err_editor_load || 'Editor failed to load.') + '</span>';
    });

    const modalBody = modal.querySelector('.myCloudModalBody');
    modalBody.addEventListener('dragover', (e) => {
        e.preventDefault(); e.stopPropagation();
        modalBody.style.outline = '2px dashed var(--accent-primary)';
        modalBody.style.outlineOffset = '-4px';
    });
    modalBody.addEventListener('dragleave', (e) => {
        e.preventDefault(); e.stopPropagation();
        modalBody.style.outline = '';
    });
    modalBody.addEventListener('drop', (e) => {
        e.preventDefault(); e.stopPropagation();
        modalBody.style.outline = '';
        if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
            window._emlHandleFiles(e.dataTransfer.files);
        }
    });

    const autoMenu = document.getElementById('emlSharedAutocomplete');
    let activeInput = null;
    window._emlAutoIndex = -1;

    if (!window.myCloudEmailState.contacts || window.myCloudEmailState.contacts.length === 0) {
        const contactFd = new URLSearchParams({
            myCloud_action: 'email_get_contacts',
            myCloud_key: myCloudState.key,
            myCloud_token: window.myCloudCsrfToken
        });
        fetch('', { method: 'POST', body: contactFd }).then(r => r.json()).then(res => {
            if (res.status === 'OK') {
                window.myCloudEmailState.contacts = res.contacts || [];
				window.myCloudEmailState.autoContacts = res.auto_contacts || [];
                if (activeInput && activeInput.value.trim().length > 0) activeInput.dispatchEvent(new Event('input'));
            }
        }).catch(e => console.error("Contact sync failed", e));
    }

    const populateTiles = (str, inputId) => {
        if (!str) return;
        const inputEl = document.getElementById(inputId);
        if (!inputEl) return;
        str.split(',').forEach(addr => {
            const clean = addr.trim();
            if (!clean) return;
            let name = null, email = clean;
            const match = clean.match(/^(.*)\s*<([^>]+)>$/);
            if (match) { name = match[1].trim().replace(/['"]/g, ''); email = match[2].trim(); }
            window._emlAddRecipientTile(name, email, inputEl);
        });
    };
    
    if (prefill) {
        populateTiles(prefill.to, 'emlTo');
        populateTiles(prefill.cc, 'emlCc');
        populateTiles(prefill.bcc, 'emlBcc');
        
        if (prefill.cc || prefill.bcc) {
            const ccWrap = document.getElementById('emlCcWrap');
            const bccWrap = document.getElementById('emlBccWrap');
            if (ccWrap) ccWrap.style.display = 'flex';
            if (bccWrap) bccWrap.style.display = 'flex';
            const toggleBtn = document.querySelector('button[onclick*="emlCcWrap"]');
            if (toggleBtn) toggleBtn.style.display = 'none';
        }
    }

    document.getElementById('emlSubject').addEventListener('input', window.markDirty);

    const setupRecipientInput = (inputId) => {
        const inputEl = document.getElementById(inputId);
        const tileContainer = inputEl.parentElement;

        // --- Helper: Levenshtein Distance for Typo Tolerance ---
        const getLevenshtein = (a, b) => {
            if (a.length === 0) return b.length;
            if (b.length === 0) return a.length;
            const matrix = [];
            for (let i = 0; i <= b.length; i++) { matrix[i] = [i]; }
            for (let j = 0; j <= a.length; j++) { matrix[0][j] = j; }
            for (let i = 1; i <= b.length; i++) {
                for (let j = 1; j <= a.length; j++) {
                    if (b.charAt(i - 1) === a.charAt(j - 1)) {
                        matrix[i][j] = matrix[i - 1][j - 1];
                    } else {
                        matrix[i][j] = Math.min(matrix[i - 1][j - 1] + 1, Math.min(matrix[i][j - 1] + 1, matrix[i - 1][j] + 1));
                    }
                }
            }
            return matrix[b.length][a.length];
        };

        // --- Helper: Tokenized Fuzzy Matcher ---
        const isFlexibleMatch = (searchStr, targetStr) => {
            if (!searchStr || !targetStr) return false;
            
            const s = searchStr.toLowerCase().replace(/[^\w\s@.]/g, '');
            const t = targetStr.toLowerCase().replace(/[^\w\s@.]/g, '');
            if (t.includes(s)) return true; // Direct substring catch

            const sTokens = s.split(/\s+/).filter(Boolean);
            const tTokens = t.split(/\s+/).filter(Boolean);
            
            // Every search token must match at least one target token
            return sTokens.every(st => {
                return tTokens.some(tt => {
                    if (tt.includes(st)) return true;
                    // Allow up to 2 typos (e.g., transposed characters) for words >= 4 chars
                    if (st.length >= 4 && Math.abs(st.length - tt.length) <= 2) {
                        return getLevenshtein(st, tt) <= 2; 
                    }
                    return false;
                });
            });
        };

        inputEl.addEventListener('input', function() {
            activeInput = this;
            window.markDirty();

            let val = this.value;

            // 1. Explicit Delimiters (Comma or Semicolon)
            if (val.includes(',') || val.includes(';')) {
                const parts = val.split(/[,;]/);
                const remainder = parts.pop().trimStart();
                parts.forEach(p => { 
                    const clean = p.trim();
                    if (clean) window._emlAddRecipientTile('', clean, this); 
                });
                this.value = remainder;
                val = remainder;
                autoMenu.style.display = 'none';
            }
            
            // 2. Space Delimiter (Only if a fully valid email is typed)
            if (val.endsWith(' ') || val.endsWith('\u00A0')) {
                const clean = val.trim();
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (emailRegex.test(clean)) {
                    window._emlAddRecipientTile('', clean, this);
                    this.value = '';
                    val = '';
                    autoMenu.style.display = 'none';
                }
            }

            const currentSearch = val.trim().toLowerCase();
            
            // 3. MINIMUM CHARACTER GATE: Wait for 4 characters
            if (currentSearch.length < 4) { 
                autoMenu.style.display = 'none'; 
                return; 
            }

            autoMenu.innerHTML = '';

            // 4. RECENT INTERACTION SCORING
            const recentScores = new Map();
            if (window.myCloudEmailState && window.myCloudEmailState.currentMessages) {
                // Look at the latest 150 messages in the currently open view
                const msgs = window.myCloudEmailState.currentMessages.slice(0, 150);
                msgs.forEach((m, idx) => {
                    const score = 150 - idx; // Higher score = more recent
                    const addrs = [m.fromEmail, m.to, m.cc, m.bcc].filter(Boolean).join(',');
                    const matches = addrs.match(/[a-zA-Z0-9!#$%&'*+\-\/=?^_`{|}~.]+@[a-zA-Z0-9.-]+/g);
                    if (matches) {
                        matches.forEach(e => {
                            const cleanE = e.toLowerCase();
                            if (!recentScores.has(cleanE)) recentScores.set(cleanE, score);
                        });
                    }
                });
            }

            const allContacts = [...(window.myCloudEmailState.contacts || []), ...(window.myCloudEmailState.autoContacts || [])];
            let matchedResults = [];

            // 5. FIND AND SCORE MATCHES
            allContacts.forEach(c => {
                let emailsArray = c.emails;
                if (!emailsArray || !Array.isArray(emailsArray)) emailsArray = [];

                if (emailsArray.length > 0) {
                    emailsArray.forEach(e => {
                        const emailVal = e.val || '';
                        
                        if (isFlexibleMatch(currentSearch, c.name) || isFlexibleMatch(currentSearch, emailVal)) {
                            const recencyScore = recentScores.get(emailVal.toLowerCase()) || 0;
                            matchedResults.push({
                                contact: c,
                                email: e,
                                score: recencyScore
                            });
                        }
                    });
                }
            });

            // 6. SORTING: Sort primarily by Recency Score (descending), then alphabetically
            matchedResults.sort((a, b) => {
                if (a.score !== b.score) {
                    return b.score - a.score;
                }
                const nameA = a.contact.name || a.email.val;
                const nameB = b.contact.name || b.email.val;
                return nameA.localeCompare(nameB);
            });

            // 7. RENDER RESULTS
            if (matchedResults.length > 0) {
                
                // AUTO-SELECT FIRST ENTRY
                window._emlAutoIndex = 0;

                matchedResults.forEach((res, idx) => {
                    const c = res.contact;
                    const e = res.email;
                    
                    const div = document.createElement('div');
                    div.className = 'ce-autocomplete-item';
                    div.style.padding = '8px 12px';
                    div.style.cursor = 'pointer';
                    div.style.borderBottom = '1px solid var(--border-subtle)';
                    
                    // Highlight the first item automatically
                    div.style.backgroundColor = (idx === 0) ? 'var(--hover-bg-medium)' : 'transparent';
                    
                    const recentBadge = res.score > 0 ? `<span style="font-size:10px; color:var(--accent-primary); background:var(--gray-15); padding:1px 4px; border-radius:3px; margin-inline-end:6px;" title="Recent Contact">◷</span>` : '';

                    div.innerHTML = `<div style="display:flex; justify-content:space-between; margin-bottom:2px;"><b style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${recentBadge}${myCloudEscapeHtml(c.name || e.val)}</b><span style="font-size:11px; opacity:0.7; white-space:nowrap; margin-inline-start:8px;">${myCloudEscapeHtml(e.type || 'Email')}</span></div><div style="font-size:12px; opacity:0.8; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${myCloudEscapeHtml(e.val)}</div>`;
                    
                    div.onmouseenter = function() {
                        window._emlAutoIndex = Array.from(autoMenu.children).indexOf(this);
                        Array.from(autoMenu.children).forEach((child, index) => {
                            child.style.backgroundColor = (index === window._emlAutoIndex) ? 'var(--hover-bg-medium)' : 'transparent';
                        });
                    };
                    div.onmousedown = function(ev) {
                        ev.preventDefault(); ev.stopPropagation();
                        window._emlAddRecipientTile(c.name || '', e.val, activeInput);
                        activeInput.value = '';
                        autoMenu.style.display = 'none';
                        activeInput.focus();
                    };
                    div.onclick = div.onmousedown;
                    autoMenu.appendChild(div);
                });

                autoMenu.style.display = 'block';
                if (autoMenu.parentElement !== document.body) document.body.appendChild(autoMenu);
                 
                autoMenu.style.position = 'fixed';
                autoMenu.style.bottom = 'auto';
                autoMenu.style.margin = '0';

                const containerRect = tileContainer.getBoundingClientRect();
                const menuTop = containerRect.bottom + 4;
                
                const containerNode = document.getElementById('myCloudContainer');
                const isRtl = containerNode && containerNode.getAttribute('dir') === 'rtl';

                if (isRtl) {
                     autoMenu.style.right = (window.innerWidth - containerRect.right) + 'px';
                     autoMenu.style.left = 'auto';
                } else {
                     autoMenu.style.left = containerRect.left + 'px';
                     autoMenu.style.right = 'auto';
                }
                
                autoMenu.style.top = menuTop + 'px';
            } else {
                autoMenu.style.display = 'none';
            }
        });

        inputEl.addEventListener('focus', function() {
            activeInput = this;
            if (this.value.trim().length > 0) this.dispatchEvent(new Event('input'));
        });

        inputEl.addEventListener('blur', function() {
            setTimeout(() => { if (autoMenu && activeInput === this) autoMenu.style.display = 'none'; }, 200);
        });

        inputEl.onkeydown = function(e) {
            const items = autoMenu.querySelectorAll('.ce-autocomplete-item');
            const isMenuOpen = autoMenu.style.display === 'block' && items.length > 0;

            if (isMenuOpen && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) {
                e.preventDefault();
                if (e.key === 'ArrowDown') {
                    window._emlAutoIndex = (window._emlAutoIndex + 1) % items.length;
                } else {
                    window._emlAutoIndex = window._emlAutoIndex <= 0 ? items.length - 1 : window._emlAutoIndex - 1;
                }
                items.forEach((item, idx) => {
                    if (idx === window._emlAutoIndex) {
                        item.style.backgroundColor = 'var(--hover-bg-medium)';
                        item.scrollIntoView({ block: 'nearest' });
                    } else {
                        item.style.backgroundColor = 'transparent';
                    }
                });
                return;
            }

            const isEnter = (e.key === 'Enter' || e.keyCode === 13);
            const isCommaOrSemi = (e.key === ',' || e.key === ';' || e.keyCode === 188 || e.keyCode === 186);
            const isSpace = (e.key === ' ' || e.keyCode === 32);

            if ((isEnter || isCommaOrSemi || isSpace) && this.value.trim()) {
                const rawVal = this.value.replace(/[,;]/g, '').trim();
                
                if (isSpace && !rawVal.includes('@')) {
                    return; 
                }

                e.preventDefault();
                
                // If Enter is pressed, the menu is open, and we have a pre-selected item, click it!
                if (isEnter && isMenuOpen && window._emlAutoIndex >= 0 && items[window._emlAutoIndex]) {
                    items[window._emlAutoIndex].click();
                } else {
                    window._emlAddRecipientTile('', rawVal, this);
                    this.value = '';
                    autoMenu.style.display = 'none';
                }
                return;
            }

            if (e.key === 'Backspace' && !this.value) {
                const lastTile = this.previousElementSibling;
                if (lastTile && lastTile.classList.contains('ce-email-tile')) lastTile.remove();
            }
        };
    };
	
    setupRecipientInput('emlTo');
    setupRecipientInput('emlCc');
    setupRecipientInput('emlBcc');

    document.getElementById('emlSendBtn').onclick = function() { execAction(this, 'email_send'); };

//    // Feature #1: Draft Auto-Saving Engine
//    let autoSaveInterval = setInterval(() => {
//        if (window._emlIsDirty) {
//            execAction(null, 'email_save_draft', true);
//            window._emlIsDirty = false;
//        }
//    }, 60000); // 60 Seconds

//    // Cleanup interval on modal close
//    const cleanupAutoSave = () => clearInterval(autoSaveInterval);
//    document.querySelector('.myCloudModalHeader .myCloudClose').addEventListener('click', cleanupAutoSave);
//    document.getElementById('myCloudModalOverlay').addEventListener('click', (e) => {
//        if (e.target.id === 'myCloudModalOverlay') cleanupAutoSave();
//    });

    // Feature #4: PGP Encryption Logic (Professional Contact-Bound)
    window._emlTriggerPgpEncrypt = async function() {
        if (!myCloudEmailEditorInstance) return;

        // 1. Gather all intended recipients
        const manualTo = document.getElementById('emlTo').value.trim();
        const tileEls = document.querySelectorAll('#emlToTiles .ce-email-tile, #emlCcTiles .ce-email-tile, #emlBccTiles .ce-email-tile');
        const emails = Array.from(tileEls).map(t => t.dataset.email);
        if (manualTo) emails.push(...manualTo.split(',').map(e => e.trim()));

        const uniqueEmails = [...new Set(emails.filter(Boolean))];
        if (uniqueEmails.length === 0) {
            return myCloudShowAlert(L.error_prefix || 'Error', L.pgp_err_no_rcpt || 'Please add at least one recipient before encrypting.');
        }

        // 2. Cross-reference Address Book & Discovery Chain for Keys
        const pubKeys = [];
        const missing = [];
        
        myCloudCreateProgressUI(L.pgp_encrypting || 'Encrypting Message...');

        // On-demand load OpenPGP
        if (!window.openpgp) {
            await new Promise((res, rej) => { 
                const s = document.createElement('script'); 
                s.src = '/script/openpgp/openpgp.min.js'; 
                s.onload = res; s.onerror = rej; 
                document.head.appendChild(s); 
            });
        }

        // Include the Sender's own key
        const fromEl = document.getElementById('emlFrom');
        const activeAccId = fromEl ? fromEl.value.split('|')[0] : myCloudEmailState.activeAccount;
        const ownPubKey = myCloudEmailState.accounts[activeAccId]?.pgp_public_key;
        if (ownPubKey) pubKeys.push(ownPubKey);

        for (let i = 0; i < uniqueEmails.length; i++) {
            const email = uniqueEmails[i];
            const cleanEmail = email.replace(/.*<([^>]+)>.*/, '$1').trim().toLowerCase();
            
            // Step 1: Check Local Contacts
            const allContacts = [...(window.myCloudEmailState.contacts || []), ...(window.myCloudEmailState.autoContacts || [])];
            let contact = allContacts.find(c => c.emails && c.emails.some(e => e.val.toLowerCase() === cleanEmail));
            
            if (contact && contact.pgp_public_key && contact.pgp_public_key.includes('BEGIN PGP PUBLIC KEY')) {
                pubKeys.push(contact.pgp_public_key);
                continue;
            }

            // Step 2: Global Discovery Chain (Local -> Domain WKD -> Keyserver via PHP)
            let foundExternalKey = null;
            let sourceName = '';
            
            const sFd = new URLSearchParams({ myCloud_action: 'email_lookup_pubkey', myCloud_key: myCloudState.key, myCloud_token: window.myCloudCsrfToken, email: cleanEmail });
            try {
                const sRes = await fetch('', { method: 'POST', body: sFd }).then(r=>r.json());
                if (sRes.status === 'OK' && sRes.pubkey) {
                    if (sRes.is_binary) {
                        const binaryString = atob(sRes.pubkey);
                        const bytes = new Uint8Array(binaryString.length);
                        for (let j = 0; j < binaryString.length; j++) {
                            bytes[j] = binaryString.charCodeAt(j);
                        }
                        const parsedKey = await window.openpgp.readKey({ binaryKey: bytes });
                        foundExternalKey = parsedKey.armor(); 
                    } else {
                        foundExternalKey = sRes.pubkey; 
                    }
                    sourceName = sRes.source;
                }
            } catch(e) {
                console.error("Discovery failed/rejected for", cleanEmail, e);
            }

            if (foundExternalKey) {
                try {
                    // Failsafe: Dry-run the key to ensure it isn't corrupt garbage that will crash the composer
                    await window.openpgp.readKey({ armoredKey: foundExternalKey });
                    pubKeys.push(foundExternalKey);
                    
                    if (!contact) {
                        contact = { id: 'auto_' + Math.random().toString(36).substr(2, 9), name: cleanEmail.split('@')[0], emails: [{type: 'Collected', val: cleanEmail}] };
                    }
                    
                    contact.pgp_public_key = foundExternalKey;
                    const saveFd = new URLSearchParams({ myCloud_action: 'email_save_contact', myCloud_key: myCloudState.key, myCloud_token: window.myCloudCsrfToken, book_type: window.myCloudEmailState.contacts.some(c => c.id === contact.id) ? 'main' : 'auto', contact_id: contact.id, name: contact.name || '', emails: JSON.stringify(contact.emails || []), pgp_public_key: foundExternalKey });
                    fetch('', { method: 'POST', body: saveFd });
                    
                    if (typeof myCloudNotify === 'function') {
                        let msg = L.pgp_key_found_ext || 'Found public key for %s via %s.';
                        myCloudNotify(msg.replace('%s', cleanEmail).replace('%s', sourceName));
                    }
                    continue; 
                } catch (parseErr) {
                    console.error("Discovered key was invalid or corrupt:", parseErr);
                }
            }

            missing.push(cleanEmail);
        }

        if (missing.length > 0) {
            if (typeof myCloudCloseProgressUI === 'function') myCloudCloseProgressUI();
            return myCloudShowAlert(L.pgp_missing_keys_title || 'Missing PGP Keys', (L.pgp_missing_keys_msg_1 || 'Cannot encrypt. No PGP public key found for:<br><br><b>') + missing.join(', ') + (L.pgp_missing_keys_msg_2 || '</b><br><br>Please edit these contacts to add their keys manually.'));
        }

        try {
            // Read all keys
            const parsedKeys = await Promise.all(pubKeys.map(k => window.openpgp.readKey({ armoredKey: k })));
            // FIX: Use getContents() instead of getText() to preserve all HTML, styling, and line breaks
            const htmlPayload = myCloudEmailEditorInstance.getContents(); 
            const message = await window.openpgp.createMessage({ text: htmlPayload });
            
            const encrypted = await window.openpgp.encrypt({
                message: message,
                encryptionKeys: parsedKeys
            });
            
            myCloudEmailEditorInstance.setContents('<pre>' + encrypted + '</pre>');
            window.markDirty();
            myCloudCloseProgressUI();
            if (typeof myCloudShowAlert === 'function') myCloudShowAlert(L.success || 'Success', L.pgp_encrypt_success || 'Message encrypted for all recipients.');
        } catch (e) {
            myCloudCloseProgressUI();
            if (typeof myCloudShowAlert === 'function') myCloudShowAlert(L.pgp_encrypt_err || 'Encryption Error', e.message);
        }
    };


};


</script>