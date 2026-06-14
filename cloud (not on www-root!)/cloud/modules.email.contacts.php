<?php
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) die('Direct access not permitted');
?>
<script>
window.myCloudShowEmailContacts = function() {
    const overlay = document.getElementById('myCloudModalOverlay');
    const modal = document.getElementById('myCloudModal');
    if (typeof myCloudResetModal === 'function') myCloudResetModal();

    overlay.style.display = 'flex';
    modal.className = 'myCloudModal';
    modal.style.width = '700px';
    modal.style.maxWidth = '95vw';

    if (!myCloudEmailState.activeAddressBook) myCloudEmailState.activeAddressBook = 'main';
    const devKey = typeof myCloudGetCurrentDeviceKey === 'function' ? myCloudGetCurrentDeviceKey() : 'desktop';
    if (!myCloudState.settings) myCloudState.settings = {};
    if (!myCloudState.settings[devKey]) myCloudState.settings[devKey] = {};
    let isAutoCollect = myCloudState.settings[devKey].emailAutoCollect !== false;

    const L = typeof myCloud_LANG !== 'undefined' ? myCloud_LANG : {};
    const esc = function(str) { return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); };


    const delIcon = '<span class="owa-icon"><svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg></span>';
    const editIcon = '<span class="owa-icon"><svg viewBox="0 0 24 24"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg></span>';


    window._emailComposeTo = function(emailStr, e) {
        if (e) { e.stopPropagation(); e.preventDefault(); }
        if (typeof myCloudShowEmailComposer === 'function') myCloudShowEmailComposer({ to: emailStr });
    };

	const canImportExport = window.myCloudActionAllowed('email_import_contacts');

    modal.innerHTML = 
        '<div class="myCloudModalHeader" style="justify-content:space-between; gap:10px; flex-wrap:nowrap; overflow:hidden; border-bottom:none; padding-bottom:0;">' + 
            '<div style="display:flex; align-items:center; gap:8px; min-width:0; flex:1; font-weight:bold; font-size:16px;">' + myCloudSvgLogo + ' ' + (L.contacts || 'Address Book') + '</div>' +
            '<div style="display:flex; align-items:center; gap:4px; flex-shrink:0;">' +
                '<button class="myCloudClose" onclick="myCloudCloseModal()" style="margin:0; padding:4px;">✕</button>' +
            '</div>' +
        '</div>' +
        '<div class="ce-contact-tabs">' +
            '<div class="ce-contact-tab" data-book="main">' + (L.contacts || 'Address Book') + '</div>' +
            '<div class="ce-contact-tab" data-book="auto">' + (L.book_auto || 'Collected Addresses') + '</div>' +
        '</div>' +
        '<div class="myCloudModalBody" style="padding:0; display:flex; flex-direction:column; height:60vh; min-height:400px;">' +
            '<div style="padding:15px; border-bottom:1px solid var(--border-default); background:var(--gray-05); display:flex; gap:10px;">' +
                '<input type="text" id="ceContactSearch" class="myCloudInlineInput" placeholder="' + (L.search || 'Search contacts...') + '" style="flex:1; margin:0;">' +
                '<button class="owa-btn primary" onclick="window._emailEditContact(null)"><svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg> ' + (L.add || 'Add') + '</button>' +
            '</div>' +
            '<div id="ceContactList" style="flex:1; overflow-y:auto; background:var(--gray-00);"></div>' +
            '<div style="padding:15px; border-top:1px solid var(--border-default); background:var(--gray-05); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">' +
                '<div style="display:flex; align-items:center; gap:15px;">' +
                    '<label style="display:flex; align-items:center; gap:8px; font-size:13px; cursor:pointer; color:var(--text-primary);">' +
                        '<input type="checkbox" id="ceAutoCollectCb" class="myCloudCheckbox" ' + (isAutoCollect ? 'checked' : '') + '> ' + (L.auto_collect || 'Auto-collect recipients') +
                    '</label>' +
                    '<button id="ceClearAutoBtn" class="owa-btn owa-danger" style="display:none;">' + (L.clear_collected || 'Clear Collected') + '</button>' +
                '</div>' +
                (canImportExport ? 
                    '<div style="display:flex; gap:10px;">' +
                        '<input type="file" id="ceContactImportFile" accept=".csv,.vcf,.vcard,.ldif" style="display:none;">' +
                        '<button class="owa-btn" onclick="document.getElementById(\'ceContactImportFile\').click()"><svg viewBox="0 0 24 24"><path d="M9 16h6v-6h4l-7-7-7 7h4zm-4 2h14v2H5z"/></svg> ' + (L.import_csv || 'Import CSV') + '</button>' +
                        '<button class="owa-btn" onclick="window._emailExportContacts()"><svg viewBox="0 0 24 24"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg> ' + (L.export_csv || 'Export CSV') + '</button>' +
                    '</div>' : '') +
            '</div>' +
        '</div>';

    const listDiv = document.getElementById('ceContactList');
    const searchInput = document.getElementById('ceContactSearch');

    document.querySelectorAll('.ce-contact-tab').forEach(tab => {
        if (tab.dataset.book === myCloudEmailState.activeAddressBook) tab.classList.add('active');
        
        tab.onclick = (e) => {
            document.querySelectorAll('.ce-contact-tab').forEach(t => t.classList.remove('active'));
            e.currentTarget.classList.add('active');
            myCloudEmailState.activeAddressBook = e.currentTarget.dataset.book;
            renderList(searchInput.value);
        };
    });

    document.getElementById('ceClearAutoBtn').style.display = myCloudEmailState.activeAddressBook === 'auto' ? 'inline-flex' : 'none';
    document.getElementById('ceClearAutoBtn').onclick = () => {
        if (typeof myCloudShowAlert === 'function') {
            myCloudShowAlert(L.clear_auto_title || 'Clear Collected Addresses', L.clear_auto_msg || 'Are you sure you want to permanently delete all auto-collected addresses?', () => {
                fetch('', { method: 'POST', body: new URLSearchParams({ myCloud_action: 'email_clear_auto_contacts', myCloud_key: myCloudState.key, myCloud_token: window.myCloudCsrfToken }) })
                .then(r=>r.json()).then(res => {
                    if (res.status === 'OK') {
                        myCloudEmailState.autoContacts = [];
                        renderList(searchInput.value);
                    }
                });
            });
        }
    };

    document.getElementById('ceAutoCollectCb').onchange = (e) => {
        myCloudState.settings[devKey].emailAutoCollect = e.target.checked;
        if (typeof myCloudSaveSettings === 'function') myCloudSaveSettings();
    };

    const renderList = (query = '') => {
        listDiv.innerHTML = '';
        const q = query.toLowerCase();
        
        const sourceList = myCloudEmailState.activeAddressBook === 'auto' ? (myCloudEmailState.autoContacts || []) : (myCloudEmailState.contacts || []);

        sourceList.forEach(c => {
			if (!c.emails && (c.email || c.email2)) {
                c.emails = [];
                if (c.email) c.emails.push({type: c.email_type || 'Work', val: c.email.trim()});
                if (c.email2) c.email2.split(',').forEach(e => { if (e.trim()) c.emails.push({type: c.email2_type || 'Home', val: e.trim()}); });
            }
            if (!c.emails) c.emails = [];
            
            if (!c.phones && (c.phone || c.phone2)) {
                c.phones = [];
                if (c.phone) c.phones.push({type: c.phone_type || 'Mobile', val: c.phone.trim()});
                if (c.phone2) c.phone2.split(',').forEach(p => { if (p.trim()) c.phones.push({type: c.phone2_type || 'Work', val: p.trim()}); });
            }
            if (!c.phones) c.phones = [];
        });

        const filtered = sourceList.filter(c =>
            (c.name && c.name.toLowerCase().includes(q)) || 
            (c.company && c.company.toLowerCase().includes(q)) ||
            (c.labels && c.labels.toLowerCase().includes(q)) ||
            (c.emails.some(e => e.val.toLowerCase().includes(q))) ||
            (c.phones.some(p => p.val.toLowerCase().includes(q)))
        );

        if (filtered.length === 0) {
            listDiv.innerHTML = '<div style="padding:30px; text-align:center; color:var(--text-secondary);">' + (L.no_contacts || 'No contacts found.') + '</div>';
            return;
        }

        let html = '';
        filtered.forEach(c => {
            const initial = (c.name || (c.emails[0] ? c.emails[0].val : '?')).charAt(0).toUpperCase();

            let emailHtml = '';
            if (c.emails.length > 0) {
                emailHtml = '<div style="margin:4px 0; display:flex; flex-wrap:wrap; gap:6px;">' + c.emails.map(e => 
                    '<span style="display:inline-flex; align-items:center; background:var(--gray-10); border:1px solid var(--border-medium); border-radius:12px; padding:2px 8px; font-size:11px; cursor:pointer; color:var(--accent-primary); font-weight:500;" onclick="window._emailComposeTo(\'' + esc(e.val).replace(/'/g, "\\'") + '\', event)" title="' + (L.compose_to || 'Compose to ') + esc(e.val).replace(/"/g, '&quot;') + '">' +
                    '✉️ <span style="color:var(--text-secondary); font-weight:normal; margin-inline:4px; font-size:10px; opacity:0.8;">' + esc(e.type) + '</span> ' + esc(e.val) + '</span>'
                ).join('') + '</div>';
            }

            let extraInfo = '';
            if (c.company || c.job_title) {
                let prof = [c.job_title, c.company].filter(Boolean).join(L.contact_at || ' at ');
                extraInfo += '<div style="font-size:12px; color:var(--text-secondary); margin-top:2px;">💼 ' + esc(prof) + '</div>';
            }
            if (c.phones.length > 0) {
                extraInfo += '<div style="font-size:11px; color:var(--text-disabled); margin-top:4px; display:flex; flex-wrap:wrap; gap:12px;">' + 
                    c.phones.map(p => '<span style="display:inline-flex; align-items:center;">📞 <span style="opacity:0.7; margin-inline:4px; font-size:10px;">' + esc(p.type) + '</span> <span style="color:var(--text-primary); font-weight:500;">' + esc(p.val) + '</span></span>').join('') + 
                '</div>';
            }
            if (c.address) {
                extraInfo += '<div style="font-size:11px; color:var(--text-disabled); margin-top:6px; white-space:pre-wrap; line-height:1.3; background:var(--gray-05); padding:6px; border-radius:4px; border:1px solid var(--border-subtle);">' + esc(c.address) + '</div>';
            }
            if (c.labels) {
                let badgeHtml = c.labels.split(',').map(lbl => '<span style="background:var(--gray-15); border:1px solid var(--border-default); padding:1px 6px; border-radius:4px; font-size:10px; margin-inline-end:4px; display:inline-block;">' + esc(lbl.trim()) + '</span>').join('');
                extraInfo += '<div style="margin-top:6px;">' + badgeHtml + '</div>';
            }

            html += 
                '<div class="ce-contact-row" style="align-items:flex-start;">' +
                    '<div style="display:flex; min-width:0; flex:1;">' +
                        '<div class="ce-contact-avatar" style="margin-top:4px;">' + initial + '</div>' +
                        '<div class="ce-contact-info">' +
                            '<div class="ce-contact-name" style="font-size:15px;">' + esc(c.name) + '</div>' +
                            emailHtml +
                            extraInfo +
                        '</div>' +
                    '</div>' +
                    '<div style="display:flex; gap:8px; flex-shrink:0; margin-top:4px;">' +
                        '<button class="owa-btn" onclick="window._emailEditContact(\'' + c.id + '\')" style="padding:4px 8px;">' + editIcon + " " + (L.edit || 'Edit') + '</button>' +
                        '<button class="owa-btn owa-danger" onclick="window._emailDelContact(\'' + c.id + '\')" style="padding:4px 8px;">' + delIcon + " " +  (L.delete || 'Delete') + '</button>' +
                    '</div>' +
                '</div>';
        });
        listDiv.innerHTML = html;
    };

    window._emlRenderContactList = () => {
        if (document.getElementById('ceContactSearch')) {
            renderList(document.getElementById('ceContactSearch').value);
        }
    };

    searchInput.addEventListener('input', (e) => renderList(e.target.value));
    renderList();

    myCloudEmailLoadContacts().then(() => {
        window._emlRenderContactList();
    });

    // The Sub-Modal for Editing/Adding with Dynamic Rows
    window._emailEditContact = (id) => {
        const activeList = myCloudEmailState.activeAddressBook === 'auto' ? myCloudEmailState.autoContacts : myCloudEmailState.contacts;
        const c = id ? activeList.find(x => x.id === id) : { id: '', name: '', first_name: '', last_name: '', emails: [], phones: [], company: '', job_title: '', address: '', website: '', labels: '', notes: '', pgp_public_key: '' };
        const isNew = !c.id;

        const subOverlay = document.createElement('div');
        subOverlay.className = 'myCloudOverlay';
        subOverlay.style.display = 'flex';
        subOverlay.style.zIndex = '100010';

        const subModal = document.createElement('div');
        subModal.className = 'myCloudModal';
        subModal.style.width = '700px';
        subModal.style.maxWidth = '95vw';

        let emailInputsHtml = c.emails.map(e => `
            <div class="ce-dynamic-row" style="display:flex; gap:10px; margin-bottom:8px;">
                <input type="text" class="myCloudInlineInput cnt-email-type" value="${esc(e.type)}" placeholder="${esc(L.lbl_label || 'Label')}" style="width:100px;">
                <input type="email" class="myCloudInlineInput cnt-email-val" value="${esc(e.val)}" placeholder="${esc(L.email_address || 'Email Address')}" style="flex:1;">
                <button class="owa-btn danger" onclick="this.parentElement.remove()" style="padding:4px 8px;">✕</button>
            </div>
        `).join('');

        let phoneInputsHtml = c.phones.map(p => `
            <div class="ce-dynamic-row" style="display:flex; gap:10px; margin-bottom:8px;">
                <input type="text" class="myCloudInlineInput cnt-phone-type" value="${esc(p.type)}" placeholder="${esc(L.lbl_label || 'Label')}" style="width:100px;">
                <input type="text" class="myCloudInlineInput cnt-phone-val" value="${esc(p.val)}" placeholder="${esc(L.phone_number || 'Phone Number')}" style="flex:1;">
                <button class="owa-btn danger" onclick="this.parentElement.remove()" style="padding:4px 8px;">✕</button>
            </div>
        `).join('');

        subModal.innerHTML = 
            '<div class="myCloudModalHeader" style="justify-content:space-between;"><span>' + myCloudSvgLogo + " "  + (isNew ? (L.add_contact || 'Add Contact') : (L.edit_contact || 'Edit Contact')) + '</span><button class="myCloudClose" onclick="this.closest(\'.myCloudOverlay\').remove()">✕</button></div>' +
            '<div class="myCloudModalBody" style="padding:0; display:flex; flex-direction:column; max-height:75vh; overflow-y:auto; background:var(--gray-05);">' +
                '<div style="padding:20px; display:flex; flex-direction:column; gap:16px;">' +

                    // SECTION 1: Personal Information
                    '<div class="ce-setting-block" style="margin:0; padding:15px;">' +
                        '<div class="ce-setting-header" style="padding:0 0 10px 0;">' + (L.personal_info || 'Personal Information') + '</div>' +
                        '<div class="ce-two-col" style="gap:15px;">' +
                            '<div class="ce-col">' +
                                '<label style="font-size:12px; color:var(--text-secondary); font-weight:600; margin-bottom:4px;">' + (L.first_name || 'First Name') + '</label>' +
                                '<input type="text" id="cntFirstName" class="myCloudInlineInput" value="' + esc(c.first_name) + '">' +
                            '</div>' +
                            '<div class="ce-col">' +
                                '<label style="font-size:12px; color:var(--text-secondary); font-weight:600; margin-bottom:4px;">' + (L.last_name || 'Last Name') + '</label>' +
                                '<input type="text" id="cntLastName" class="myCloudInlineInput" value="' + esc(c.last_name) + '">' +
                            '</div>' +
                        '</div>' +
                        '<div style="margin-top:10px;">' +
                            '<label style="font-size:12px; color:var(--text-secondary); font-weight:600; margin-bottom:4px;">' + (L.display_name || 'Display Name') + ' (' + (L.optional || 'Optional') + ')</label>' +
                            '<input type="text" id="cntName" class="myCloudInlineInput" value="' + esc(c.name) + '" placeholder="' + (L.leave_blank_auto || 'Leave blank to auto-fill') + '">' +
                        '</div>' +
                    '</div>' +

                    // SECTION 2: Dynamic Contact Details
                    '<div class="ce-setting-block" style="margin:0; padding:15px;">' +
                        '<div class="ce-setting-header" style="padding:0 0 10px 0;">' + (L.contact_details || 'Contact Details') + '</div>' +
                        
                        '<label style="font-size:12px; color:var(--text-secondary); font-weight:600; margin-bottom:6px; display:block;">' + (L.emails || 'Email Addresses') + '</label>' +
                        '<div id="cntEmailsContainer">' + emailInputsHtml + '</div>' +
                        '<button class="owa-btn" onclick="window._addContactEmail()" style="margin-bottom:15px; padding:4px 10px; font-size:11px;">' + (L.add_email || '+ Add Email') + '</button>' +

                        '<label style="font-size:12px; color:var(--text-secondary); font-weight:600; margin-bottom:6px; display:block;">' + (L.phones || 'Phone Numbers') + '</label>' +
                        '<div id="cntPhonesContainer">' + phoneInputsHtml + '</div>' +
                        '<button class="owa-btn" onclick="window._addContactPhone()" style="padding:4px 10px; font-size:11px;">' + (L.add_phone || '+ Add Phone') + '</button>' +
                    '</div>' +

                    // SECTION 3: Professional & Additional
                    '<div class="ce-setting-block" style="margin:0; padding:15px;">' +
                        '<div class="ce-setting-header" style="padding:0 0 10px 0;">' + (L.prof_additional || 'Professional & Additional') + '</div>' +
                        '<div class="ce-two-col" style="gap:15px;">' +
                            '<div class="ce-col">' +
                                '<label style="font-size:12px; color:var(--text-secondary); font-weight:600; margin-bottom:4px;">' + (L.company || 'Company') + '</label>' +
                                '<input type="text" id="cntCompany" class="myCloudInlineInput" value="' + esc(c.company) + '">' +
                            '</div>' +
                            '<div class="ce-col">' +
                                '<label style="font-size:12px; color:var(--text-secondary); font-weight:600; margin-bottom:4px;">' + (L.job_title || 'Job Title') + '</label>' +
                                '<input type="text" id="cntJob" class="myCloudInlineInput" value="' + esc(c.job_title) + '">' +
                            '</div>' +
                        '</div>' +
                        '<div style="margin-top:10px;">' +
                            '<label style="font-size:12px; color:var(--text-secondary); font-weight:600; margin-bottom:4px;">' + (L.address || 'Address') + '</label>' +
                            '<textarea id="cntAddress" class="myCloudInlineInput" style="min-height:60px; resize:vertical; font-family:inherit;">' + esc(c.address) + '</textarea>' +
                        '</div>' +
                        '<div class="ce-two-col" style="gap:15px; margin-top:10px;">' +
                            '<div class="ce-col">' +
                                '<label style="font-size:12px; color:var(--text-secondary); font-weight:600; margin-bottom:4px;">' + (L.labels || 'Labels / Tags (Comma separated)') + '</label>' +
                                '<input type="text" id="cntLabels" class="myCloudInlineInput" value="' + esc(c.labels) + '">' +
                            '</div>' +
                            '<div class="ce-col">' +
                                '<label style="font-size:12px; color:var(--text-secondary); font-weight:600; margin-bottom:4px;">' + (L.website || 'Website') + '</label>' +
                                '<input type="text" id="cntWebsite" class="myCloudInlineInput" value="' + esc(c.website) + '">' +
                            '</div>' +
                        '</div>' +
                    '</div>' +

                    // SECTION 4: Notes & Security
                    '<div class="ce-setting-block" style="margin:0; padding:15px;">' +
                        '<div class="ce-setting-header" style="padding:0 0 10px 0;">' + (L.notes || 'Notes') + '</div>' +
                        '<textarea id="cntNotes" class="myCloudInlineInput" style="min-height:80px; resize:vertical; font-family:inherit; margin-bottom:15px;">' + esc(c.notes) + '</textarea>' +
						'<div class="ce-setting-header" style="padding:10px 0 10px 0; border-top: 1px solid var(--border-default);">' + (L.security || 'Security') + '</div>' +
                        '<label style="font-size:12px; color:var(--text-secondary); font-weight:600; margin-bottom:4px;">' + (L.pgp_pub_key_label || 'PGP Public Key (ASCII Armored)') + '</label>' +
                        '<textarea id="cntPgpKey" class="myCloudInlineInput" placeholder="-----BEGIN PGP PUBLIC KEY BLOCK-----..." style="min-height:120px; resize:vertical; font-family:monospace; font-size:11px;">' + esc(c.pgp_public_key) + '</textarea>' +
                    '</div>' +
                '</div>' +
            '</div>' +
            '<div class="myCloudButtons" style="justify-content:flex-end; gap:10px; padding:15px 20px; background:var(--gray-10); border-top:1px solid var(--border-default); margin:0;">' +
                '<button id="cntCancelBtn">' + (L.cancel || 'Cancel') + '</button>' +
                '<button id="cntSaveBtn" class="owa-btn primary">' + (L.save || 'Save') + '</button>' +
            '</div>';

        subOverlay.appendChild(subModal);
        document.body.appendChild(subOverlay);

        window._addContactEmail = () => {
            const div = document.createElement('div');
            div.className = 'ce-dynamic-row';
            div.style.cssText = 'display:flex; gap:10px; margin-bottom:8px;';
            div.innerHTML = '<input type="text" class="myCloudInlineInput cnt-email-type" value="Work" placeholder="' + esc(L.lbl_label || 'Label') + '" style="width:100px;"><input type="email" class="myCloudInlineInput cnt-email-val" value="" placeholder="' + esc(L.email_address || 'Email Address') + '" style="flex:1;"><button class="owa-btn danger" onclick="this.parentElement.remove()" style="padding:4px 8px;">✕</button>';
            document.getElementById('cntEmailsContainer').appendChild(div);
        };

        window._addContactPhone = () => {
            const div = document.createElement('div');
            div.className = 'ce-dynamic-row';
            div.style.cssText = 'display:flex; gap:10px; margin-bottom:8px;';
            div.innerHTML = '<input type="text" class="myCloudInlineInput cnt-phone-type" value="Mobile" placeholder="' + esc(L.lbl_label || 'Label') + '" style="width:100px;"><input type="text" class="myCloudInlineInput cnt-phone-val" value="" placeholder="' + esc(L.phone_number || 'Phone Number') + '" style="flex:1;"><button class="owa-btn danger" onclick="this.parentElement.remove()" style="padding:4px 8px;">✕</button>';
            document.getElementById('cntPhonesContainer').appendChild(div);
        };

        document.getElementById('cntCancelBtn').onclick = () => subOverlay.remove();
        
        document.getElementById('cntSaveBtn').onclick = () => {
            const btn = document.getElementById('cntSaveBtn');
            btn.disabled = true;
            btn.textContent = L.saving || 'Saving...';

            let finalEmails = [];
            document.querySelectorAll('#cntEmailsContainer .ce-dynamic-row').forEach(r => {
                let t = r.querySelector('.cnt-email-type').value.trim();
                let v = r.querySelector('.cnt-email-val').value.trim();
                if (v) finalEmails.push({type: t, val: v});
            });

            let finalPhones = [];
            document.querySelectorAll('#cntPhonesContainer .ce-dynamic-row').forEach(r => {
                let t = r.querySelector('.cnt-phone-type').value.trim();
                let v = r.querySelector('.cnt-phone-val').value.trim();
                if (v) finalPhones.push({type: t, val: v});
            });

            const fd = new URLSearchParams();
            fd.append('myCloud_action', 'email_save_contact');
            fd.append('myCloud_key', myCloudState.key);
            fd.append('myCloud_token', myCloudCsrfToken);
			fd.append('book_type', myCloudEmailState.activeAddressBook);
            fd.append('contact_id', c.id);
            fd.append('name', document.getElementById('cntName').value);
            fd.append('first_name', document.getElementById('cntFirstName').value);
            fd.append('last_name', document.getElementById('cntLastName').value);
            fd.append('emails', JSON.stringify(finalEmails));
            fd.append('phones', JSON.stringify(finalPhones));
            fd.append('company', document.getElementById('cntCompany').value);
            fd.append('job_title', document.getElementById('cntJob').value);
            fd.append('address', document.getElementById('cntAddress').value);
            fd.append('website', document.getElementById('cntWebsite').value);
            fd.append('labels', document.getElementById('cntLabels').value);
            fd.append('notes', document.getElementById('cntNotes').value);
            fd.append('pgp_public_key', document.getElementById('cntPgpKey').value);

            fetch('', { method: 'POST', body: fd }).then(r => r.json()).then(res => {
                if (res.status === 'OK') {
					const targetList = myCloudEmailState.activeAddressBook === 'auto' ? myCloudEmailState.autoContacts : myCloudEmailState.contacts;
                    if (isNew) {
                        targetList.push(res.contact);
                    } else {
                        const idx = targetList.findIndex(x => x.id === c.id);
                        if (idx > -1) targetList[idx] = res.contact;
                    }
                    targetList.sort((a,b) => a.name.localeCompare(b.name));
                    renderList(searchInput.value);
                    subOverlay.remove();
                } else {
                    myCloudShowAlert(L.error_prefix || 'Error', res.msg || "Failed to save");
                    btn.disabled = false;
                    btn.textContent = L.save || 'Save';
                }
            }).catch(() => {
                myCloudShowAlert(L.error_prefix || 'Error', "Network error");
                btn.disabled = false;
                btn.textContent = L.save || 'Save';
            });
        };
    };

    window._emailDelContact = (id) => {
        myCloudShowAlert(L.delete || "Delete", L.confirm_del_msg || "Delete this contact?", () => {
            const fd = new URLSearchParams({ myCloud_action: 'email_delete_contact', myCloud_key: myCloudState.key, myCloud_token: window.myCloudCsrfToken, contact_id: id, book_type: myCloudEmailState.activeAddressBook });
            fetch('', { method: 'POST', body: fd }).then(r => r.json()).then(res => {
                if (res.status === 'OK') {
                    if (myCloudEmailState.activeAddressBook === 'auto') {
                        myCloudEmailState.autoContacts = myCloudEmailState.autoContacts.filter(x => x.id !== id);
                    } else {
                        myCloudEmailState.contacts = myCloudEmailState.contacts.filter(x => x.id !== id);
                    }
                    renderList(searchInput.value);
                }
            });
        });
    };

    window._emailExportContacts = () => {
        const fd = new URLSearchParams({ myCloud_action: 'email_export_contacts', myCloud_key: myCloudState.key, myCloud_token: myCloudCsrfToken });
        fetch('', { method: 'POST', body: fd }).then(r => r.json()).then(res => {
            if (res.status === 'OK') {
                const blob = new Blob([res.csv], { type: 'text/csv;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'contacts_export.csv';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
            }
        });
    };

    const importInput = document.getElementById('ceContactImportFile');
    if (importInput) {
        importInput.onchange = function(e) {
            const file = e.target.files[0];
            if (!file) return;

            if (typeof myCloudShowLoading === 'function') myCloudShowLoading();
            const fd = new FormData();
            fd.append('myCloud_action', 'email_import_contacts');
            fd.append('myCloud_key', myCloudState.key);
            fd.append('myCloud_token', myCloudCsrfToken);
            fd.append('file', file);

            fetch('', { method: 'POST', body: fd }).then(r => r.json()).then(res => {
                if (typeof myCloudHideLoading === 'function') myCloudHideLoading();
                if (res.status === 'OK') {
                    myCloudShowAlert(L.success || "Success", (L.imported_msg || "Imported %s contacts.").replace('%s', res.imported));
                    const loadFd = new URLSearchParams({ myCloud_action: 'email_get_contacts', myCloud_key: myCloudState.key, myCloud_token: myCloudCsrfToken });
                    fetch('', { method: 'POST', body: loadFd }).then(r=>r.json()).then(res2 => {
                        if (res2.status === 'OK') {
                            myCloudEmailState.contacts = res2.contacts;
                            renderList(searchInput.value);
                        }
                    });
                } else {
                    myCloudShowAlert(L.error_prefix || 'Error', res.msg || "Import failed");
                }
            }).catch(() => { 
                if (typeof myCloudHideLoading === 'function') myCloudHideLoading();
                myCloudShowAlert(L.error_prefix || 'Error', "Network error during import."); 
            });
            
            importInput.value = ''; 
        };
    }
};
</script>