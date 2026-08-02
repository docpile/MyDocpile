<?php
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) die('Direct access not permitted');
?>
<script>
// ============================================================================
// MODULE: Email UI Manager (Native Explorer Tree Integration)
// ============================================================================

window.myCloudEmailState = {
    accounts: {},
    activeAccount: null,
    activeFolder: 'INBOX',
    openFolders: [],
    foldersData: {}, // Changed to Object to hold multiple accounts
    folderHashes: {},
    bodyCache: {},
    contacts: [],
    currentMessages: [],
    currentPage: 1,
    hasMore: false,
    inboxUnreadCounts: {},
    listSort: 'unread_desc',
    listFilter: 'all',
    readTimer: null,
    pendingSelectMsgKey: null,
	pendingDeletes: new Set(),
    lastFetchedFolder: null,
    bgPollTimer: null,
	autoSelectFirst: false,
    searchQuery: '',
    searchScope: 'folder',
    abortController: null,
	mobileView: 'list' 
};

if (typeof myCloudState !== 'undefined' && !window._emailTitleHooked) {
    window._emailTitleHooked = true;
    window._myCloudOriginalTitle = document.title || "My Document Pile";
    
    let _internalInterface = myCloudState.interface;
    Object.defineProperty(myCloudState, 'interface', {
        get: function() { return _internalInterface; },
        set: function(val) {
            _internalInterface = val;
            document.title = (val === 'email') ? "My Mail Pile" : window._myCloudOriginalTitle;
        },
        configurable: true,
        enumerable: true
    });
    if (_internalInterface === 'email') document.title = "My Mail Pile";
}

// --- DECENT EMAIL POPUP ENGINE ---
window._emailShowPopup = function(e, name, email, extraB64) {
    let popup = document.getElementById('ce-email-hover-popup');
    if (!popup) {
        popup = document.createElement('div');
        popup.id = 'ce-email-hover-popup';
        popup.style.cssText = 'position:fixed; z-index:999999; background:var(--gray-00); border:1px solid var(--border-medium); box-shadow:0 4px 15px rgba(0,0,0,0.2); border-radius:6px; padding:10px 14px; pointer-events:none; transition:opacity 0.2s; opacity:0; display:flex; flex-direction:column; gap:4px; min-width:180px;';
        document.body.appendChild(popup);
    }
    
					  
    let extraHtml = '';
    if (extraB64) {
        try { extraHtml = decodeURIComponent(escape(atob(extraB64))); } catch(err) {}
    }

    popup.innerHTML = (name && name !== email ? `<b style="color:var(--text-primary); font-size:14px; margin-bottom:2px;">${name}</b>` : '') + 
                      `<span style="color:var(--text-secondary); font-size:12px; display:flex; align-items:center; gap:6px;"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg> ${email}</span>` + extraHtml;
    
    popup.style.display = 'flex';
    const rect = e.target.closest('.ce-email-addr-pill, .ce-email-tile').getBoundingClientRect();
    let top = rect.bottom + 8;
    let left = rect.left;
    
    requestAnimationFrame(() => {
        const popRect = popup.getBoundingClientRect();
        if (top + popRect.height > window.innerHeight) top = rect.top - popRect.height - 8;
        if (left + popRect.width > window.innerWidth) left = window.innerWidth - popRect.width - 10;
        popup.style.top = top + 'px'; popup.style.left = left + 'px'; popup.style.opacity = '1';
    });
};
window._emailHidePopup = function() {
    const popup = document.getElementById('ce-email-hover-popup');
    if (popup) { popup.style.opacity = '0'; setTimeout(() => { if (popup.style.opacity === '0') popup.style.display = 'none'; }, 200); }
};

// --- COLOR CODING HELPER ---
window._emailGetAccColor = function(accId) {
    if (!window._emailAccColors) window._emailAccColors = {};
    if (window._emailAccColors[accId]) return window._emailAccColors[accId];
  // (ColorBrewer Set1)
  // const palette = ['#e6194b', '#3cb44b', '#ffe119', '#4363d8', '#f58231', '#911eb4', '#42d4f4', '#f032e6'];
    const palette = ['#e41a1c', '#377eb8', '#4daf4a', '#4363d8', '#ff7f00', '#e6ab02', '#a65628', '#f781bf'];

  //  const palette = ['#2196f3', '#4caf50', '#f44336', '#9c27b0', '#ff5722', '#009688', '#e91e63', '#ff9800'];
    const keys = Object.keys(myCloudEmailState.accounts);
    let idx = keys.indexOf(accId);
    if (idx === -1) idx = Object.keys(window._emailAccColors).length;
    const color = palette[idx % palette.length];
    window._emailAccColors[accId] = color;
    return color;
};

// --- MOBILE VIEW SWITCHER ---
window._emailSetMobileView = function(view, isPopState) {
    myCloudEmailState.mobileView = view;
    const t = document.getElementById('emailPaneTree');
    const l = document.getElementById('emailPaneList');
    const r = document.getElementById('emailPaneReading');
    if(t) t.classList.toggle('mobile-active', view === 'tree');
    if(l) l.classList.toggle('mobile-active', view === 'list');
    if(r) r.classList.toggle('mobile-active', view === 'reading');
    if (!isPopState && (view === 'reading' || view === 'tree')) {
        window.history.pushState({ ce_email_view: view }, '', null);
        window.myCloudHistoryTrapped = true;
    }
};

// --- CONTEXT MENU & TOUCH HELPERS ---
window._emailBindLongTouch = function(el, callback) {
    let touchTimer;
    let isMoving = false;
    el.addEventListener('touchstart', (e) => {
        isMoving = false;
        touchTimer = setTimeout(() => {
            if (!isMoving) {
                if (navigator.vibrate) navigator.vibrate(50);
                callback(e);
            }
        }, 600);
    }, {passive: true});
    el.addEventListener('touchmove', () => { isMoving = true; clearTimeout(touchTimer); }, {passive: true});
    el.addEventListener('touchend', () => clearTimeout(touchTimer), {passive: true});
    el.addEventListener('touchcancel', () => clearTimeout(touchTimer), {passive: true});
};

window._emailGetNextMsgKey = function(msgKey) {
    const listItems = Array.from(document.querySelectorAll('#ceEmailListContent .ce-email-list-item'));
    const currentIndex = listItems.findIndex(el => el.dataset.msgKey === String(msgKey));
    const selectedEl = document.querySelector('#ceEmailListContent .ce-email-list-item.selected');
    
    if (selectedEl && selectedEl.dataset.msgKey !== String(msgKey)) return selectedEl.dataset.msgKey;
    if (currentIndex !== -1) {
        if (currentIndex + 1 < listItems.length) return listItems[currentIndex + 1].dataset.msgKey;
        if (currentIndex - 1 >= 0) return listItems[currentIndex - 1].dataset.msgKey;
    }
    return null;
};


window._emailGroupSelectedMessages = function(keys) {
    const groups = {};
    keys.forEach(k => {
        const parts = k.split('|');
        if(parts.length === 3) {
            const acc = parts[0];
            const fld = parts[1];
            const id = parts[2];
            const grpKey = acc + '|' + fld;
            if(!groups[grpKey]) groups[grpKey] = { acc, fld, ids: [] };
            groups[grpKey].ids.push(id);
        }
    });
    return Object.values(groups);
};


window._emailBindSwipe = function(item) {
    if (item.dataset.swipeBound === 'true') return;
    item.dataset.swipeBound = 'true';

    let startX = 0, startY = 0, currentX = 0;
    let isSwiping = false, isScrolling = false;
    const threshold = 80;
    
    item.addEventListener('touchstart', (e) => {
        startX = e.touches[0].clientX;
        startY = e.touches[0].clientY;
        isSwiping = true;
        isScrolling = false;
        const front = item.querySelector('.ce-email-swipe-front');
        if(front) {
            front.style.transition = 'none';
            front.style.backgroundColor = 'var(--gray-00)';
            item.style.backgroundColor = 'transparent';
        }
    }, {passive: true});
    
    item.addEventListener('touchmove', (e) => {
        if (!isSwiping) return;
        const front = item.querySelector('.ce-email-swipe-front');
        const bg = item.querySelector('.ce-email-swipe-bg');
        if (!front || !bg) return;

        const iconLeft = bg.querySelector('.ce-swipe-left-icon');
        const iconRight = bg.querySelector('.ce-swipe-right-icon');
        
        const deltaX = e.touches[0].clientX - startX;
        const deltaY = e.touches[0].clientY - startY;
        
        if (!isScrolling && Math.abs(deltaY) > Math.abs(deltaX)) {
            isScrolling = true;
            isSwiping = false;
            front.style.transform = '';
            return;
        }
        
        if (isScrolling) return;
        if (e.cancelable && Math.abs(deltaX) > 10) e.preventDefault();
        
        currentX = deltaX;
        front.style.transform = `translateX(${deltaX}px)`;
        
        if (deltaX > 0) {
            bg.style.backgroundColor = '#e81123';
            iconLeft.style.opacity = Math.min(1, deltaX / threshold);
            iconLeft.style.transform = `scale(${Math.min(1, 0.5 + (deltaX / threshold) * 0.5)})`;
            iconRight.style.opacity = '0';
        } else {
            bg.style.backgroundColor = '#0078d4';
            iconRight.style.opacity = Math.min(1, Math.abs(deltaX) / threshold);
            iconRight.style.transform = `scale(${Math.min(1, 0.5 + (Math.abs(deltaX) / threshold) * 0.5)})`;
            iconLeft.style.opacity = '0';
        }
    }, {passive: false});
    
    const finalizeSwipe = () => {
        if (!isSwiping) return;
        isSwiping = false;
        
        const front = item.querySelector('.ce-email-swipe-front');
        const bg = item.querySelector('.ce-email-swipe-bg');
        if (!front || !bg) return;

        const msgId = item.dataset.msgId;
        const msgObj = myCloudEmailState.currentMessages.find(m => String(m.id) === String(msgId));
        
        front.style.transition = 'transform 0.25s ease-out';
        bg.style.transition = 'background-color 0.25s ease-out';
        
        if (msgObj && currentX > threshold) {
            front.style.transform = `translateX(100%)`;
            const metaSafe = encodeURIComponent(JSON.stringify(msgObj)).replace(/'/g, "%27");
            setTimeout(() => { if (typeof window.myCloudEmailAction === 'function') window.myCloudEmailAction('delete', msgId, metaSafe); }, 250);
        } else if (msgObj && currentX < -threshold) {
            front.style.transform = `translateX(-100%)`;
            setTimeout(() => {
                if (typeof window._emailToggleReadStatus === 'function') window._emailToggleReadStatus(msgId, !msgObj.is_read);
                front.style.transform = 'translateX(0)';
                setTimeout(() => { front.style.backgroundColor = 'inherit'; item.style.backgroundColor = ''; }, 250);
            }, 250);
        } else {
            front.style.transform = 'translateX(0)';
            bg.style.backgroundColor = 'transparent';
            setTimeout(() => { front.style.backgroundColor = 'inherit'; item.style.backgroundColor = ''; }, 250);
        }
        currentX = 0;
    };
    
    item.addEventListener('touchend', finalizeSwipe);
    item.addEventListener('touchcancel', finalizeSwipe);
};

// --- UNIVERSAL RESIZER LOGIC (MOUSE & TOUCH) ---
window._emailBindResizer = function(resizerEl, leftPaneEl, settingKey, minWidth, maxWidthPercent) {
    let startX = 0;
    let startWidth = 0;
    let containerWidth = 0;

    const onMove = (e) => {
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const delta = clientX - startX;
        let newWidth = startWidth + delta;
        
        const maxW = containerWidth * maxWidthPercent;
        if (newWidth < minWidth) newWidth = minWidth;
        if (newWidth > maxW) newWidth = maxW;

        leftPaneEl.style.width = newWidth + 'px';
    };

    const onEnd = () => {
        document.removeEventListener('mousemove', onMove);
        document.removeEventListener('mouseup', onEnd);
        document.removeEventListener('touchmove', onMove, { passive: false });
        document.removeEventListener('touchend', onEnd);
        resizerEl.classList.remove('active');
        document.body.style.cursor = '';

        document.cookie = "myCloud_" + settingKey + "=" + leftPaneEl.offsetWidth + "; path=/; max-age=31536000; SameSite=Lax";

        const devKey = typeof myCloudGetCurrentDeviceKey === 'function' ? myCloudGetCurrentDeviceKey() : 'desktop';
        if (myCloudState.settings && myCloudState.settings[devKey]) {
            myCloudState.settings[devKey][settingKey] = leftPaneEl.offsetWidth;
            if (typeof myCloudSaveSettings === 'function') myCloudSaveSettings();
        }
    };

    const onStart = (e) => {
        e.preventDefault();
        startX = e.touches ? e.touches[0].clientX : e.clientX;
        startWidth = leftPaneEl.offsetWidth;
        containerWidth = leftPaneEl.parentElement.offsetWidth;

        resizerEl.classList.add('active');
        document.body.style.cursor = 'col-resize';

        if (e.type === 'touchstart') {
            document.addEventListener('touchmove', onMove, { passive: false });
            document.addEventListener('touchend', onEnd);
        } else {
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onEnd);
        }
    };

    resizerEl.addEventListener('mousedown', onStart);
    resizerEl.addEventListener('touchstart', onStart, { passive: false });
};

window.myCloudShowEmailContextMenu = function(e, type, data) {
    if (e.preventDefault) e.preventDefault();
    if (e.stopPropagation) e.stopPropagation();
    if (typeof myCloudCloseContextMenus === 'function') myCloudCloseContextMenus();

    const menu = document.createElement('div');
    menu.id = 'myCloudContextMenu';
    menu.className = 'myCloudContextMenu';
    menu.style.position = 'fixed';
    menu.style.zIndex = '2000000';
    menu.style.visibility = 'hidden';

    const L = typeof myCloud_LANG !== 'undefined' ? myCloud_LANG : {};
    let actions = [];

    if (type === 'folder') {
        actions = [
            { label: L.refresh || 'Refresh', icon: '<svg viewBox="0 0 24 24" style="fill:none; stroke:currentColor; stroke-width:1.5; stroke-linecap:round; stroke-linejoin:round;"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>', act: () => myCloudEmailFetchMessages(data.id) },
            { sep: true },
            { label: L.new_folder || 'New Folder', icon: '<svg viewBox="0 0 24 24" style="fill:none; stroke:currentColor; stroke-width:1.5; stroke-linecap:round; stroke-linejoin:round;"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path><line x1="12" y1="11" x2="12" y2="17"></line><line x1="9" y1="14" x2="15" y2="14"></line></svg>', act: () => _emailPromptFolderAction('email_create_folder', data.id, 'New Folder Name:') },
            { label: L.rename || 'Rename', icon: '<svg viewBox="0 0 24 24" style="fill:none; stroke:currentColor; stroke-width:1.5; stroke-linecap:round; stroke-linejoin:round;"><polygon points="16 3 21 8 8 21 3 21 3 16 16 3"></polygon></svg>', act: () => _emailPromptFolderAction('email_rename_folder', data.id, 'Rename Folder:', data.name) },
            { label: L.delete || 'Delete', icon: '<svg viewBox="0 0 24 24" style="fill:none; stroke:currentColor; stroke-width:1.5; stroke-linecap:round; stroke-linejoin:round;"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>', act: () => _emailPromptFolderDelete(data.id), danger: true }
        ];
    } else if (type === 'message') {
        const isTrash = /trash|deleted|bin|papelera|corbeille|papierkorb|prullenbak/i.test(myCloudEmailState.activeFolder);
        const metaSafe = encodeURIComponent(JSON.stringify(data)).replace(/'/g, "%27");
        
        const isMyEmail = (email) => {
            if (!email) return false;
            const cleanEmail = email.toLowerCase().trim();
            return Object.keys(myCloudEmailState.accounts).some(aId => {
                const a = myCloudEmailState.accounts[aId];
                if (a.is_inactive) return false;
                if (a.email.toLowerCase().trim() === cleanEmail) return true;
                if (a.aliases) {
                    const aliases = typeof a.aliases === 'string' ? JSON.parse(a.aliases) : a.aliases;
                    return aliases.some(al => (typeof al === 'object' ? al.email : al).toLowerCase().trim() === cleanEmail);
                }
                return false;
            });
        };
        const canResend = isMyEmail(data.fromEmail);

        actions = [];
        if (window.myCloudActionAllowed('email_send')) {
            actions.push(
                { label: L.reply || 'Reply', icon: '<svg viewBox="0 0 24 24" style="fill:none; stroke:currentColor; stroke-width:1.5; stroke-linecap:round; stroke-linejoin:round;"><polyline points="9 17 4 12 9 7"></polyline><path d="M20 18v-2a4 4 0 0 0-4-4H4"></path></svg>', act: () => myCloudEmailAction('reply', data.id, metaSafe) },
                { label: L.reply_all || 'Reply All', icon: '<svg viewBox="0 0 24 24" style="fill:none; stroke:currentColor; stroke-width:1.5; stroke-linecap:round; stroke-linejoin:round;"><polyline points="7 17 2 12 7 7"></polyline><polyline points="12 17 7 12 12 7"></polyline><path d="M22 18v-2a4 4 0 0 0-4-4H7"></path></svg>', act: () => myCloudEmailAction('reply_all', data.id, metaSafe) },
                { label: L.forward || 'Forward', icon: '<svg viewBox="0 0 24 24" style="fill:none; stroke:currentColor; stroke-width:1.5; stroke-linecap:round; stroke-linejoin:round;"><polyline points="15 17 20 12 15 7"></polyline><path d="M4 18v-2a4 4 0 0 1 4-4h12"></path></svg>', act: () => myCloudEmailAction('forward', data.id, metaSafe) },
            );
            if (canResend) {
                actions.push({ label: L.resend || 'Resend', icon: '<svg viewBox="0 0 24 24" style="fill:none; stroke:currentColor; stroke-width:1.5; stroke-linecap:round; stroke-linejoin:round;"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>', act: () => myCloudEmailAction('resend', data.id, metaSafe) });
            }
            actions.push({ sep: true });
        }
        
        actions.push(
            { label: L.move_to || 'Move to...', icon: '<svg viewBox="0 0 24 24" style="fill:none; stroke:currentColor; stroke-width:1.5; stroke-linecap:round; stroke-linejoin:round;"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path><polyline points="10 14 14 14 14 18"></polyline><line x1="14" y1="14" x2="21" y2="7"></line></svg>', act: () => window._emailPromptMoveCopy('move', data) },
            { label: L.copy_to || 'Copy to...', icon: '<svg viewBox="0 0 24 24" style="fill:none; stroke:currentColor; stroke-width:1.5; stroke-linecap:round; stroke-linejoin:round;"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>', act: () => window._emailPromptMoveCopy('copy', data) },
            { sep: true },
            { label: L.mark_spam || 'Send to Spam', icon: '<svg viewBox="0 0 24 24" style="fill:none; stroke:currentColor; stroke-width:1.5; stroke-linecap:round; stroke-linejoin:round;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>', act: () => window._emailExecMoveSpam(targetKeys, srcAcc), danger: true },
            { sep: true },
            { label: data.is_read ? (L.mark_unread || 'Mark as Unread') : (L.mark_read || 'Mark as Read'), icon: data.is_read ? '<svg viewBox="0 0 24 24" style="fill:none; stroke:currentColor; stroke-width:1.5; stroke-linecap:round; stroke-linejoin:round;"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>' : '<svg viewBox="0 0 24 24" style="fill:none; stroke:currentColor; stroke-width:1.5; stroke-linecap:round; stroke-linejoin:round;"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><path d="M22 6l-10 7L2 6"></path></svg>', act: () => _emailToggleReadStatus(data.id, !data.is_read) },
            { sep: true }
        );

        if (isTrash) {
            actions.push({ label: L.restore || 'Restore', icon: '<svg viewBox="0 0 24 24" style="fill:none; stroke:currentColor; stroke-width:1.5; stroke-linecap:round; stroke-linejoin:round;"><polyline points="9 14 4 9 9 4"></polyline><path d="M20 20v-7a4 4 0 0 0-4-4H4"></path></svg>', act: () => myCloudEmailAction('restore', data.id, metaSafe) });
            actions.push({ label: L.delete_perm || 'Delete Forever', icon: '<svg viewBox="0 0 24 24" style="fill:none; stroke:currentColor; stroke-width:1.5; stroke-linecap:round; stroke-linejoin:round;"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>', act: () => myCloudEmailAction('delete', data.id, metaSafe), danger: true });
        } else {
            actions.push({ label: L.delete || 'Delete', icon: '<svg viewBox="0 0 24 24" style="fill:none; stroke:currentColor; stroke-width:1.5; stroke-linecap:round; stroke-linejoin:round;"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>', act: () => myCloudEmailAction('delete', data.id, metaSafe), danger: true });
        }
    }

    let hasItems = false;
    actions.forEach(a => {
        if (a.sep) {
            const sep = document.createElement('div');
            sep.className = 'myCloudContextSep';
            menu.appendChild(sep);
            return;
        }
        hasItems = true;
        const el = document.createElement('div');
        el.className = 'myCloudContextItem' + (a.danger ? ' danger' : '');
        el.innerHTML = '<span class="myCloudIcon" style="width:20px; height:20px; margin-right:12px; font-size:18px; display:inline-flex; align-items:center; justify-content:center;">' + a.icon + '</span> <span style="flex:1;">' + a.label + '</span>';
        el.onclick = function(ev) {
            ev.stopPropagation();
            menu.remove();
            if (a.act) a.act();
        };
        menu.appendChild(el);
    });

    if (!hasItems) return;

    document.body.appendChild(menu);
    menu.style.display = 'block';
    void menu.offsetHeight; 
    
    const menuRect = menu.getBoundingClientRect();
    const menuWidth = menuRect.width;
    const menuHeight = menuRect.height;
    
    const source = (e.touches && e.touches.length > 0) ? e.touches[0] : e;
    let leftPos = source.clientX + 5;
    let topPos = source.clientY + 2;

    if (leftPos + menuWidth > window.innerWidth - 5) leftPos = window.innerWidth - menuWidth - 5;
    if (leftPos < 5) leftPos = 5;

    if (topPos + menuHeight > window.innerHeight - 25) {
        menu.style.top = 'auto';
        menu.style.bottom = '15px';
        if (menuHeight > window.innerHeight - 30) {
            menu.style.maxHeight = (window.innerHeight - 30) + 'px';
            menu.style.overflowY = 'auto';
        }
    } else {
        menu.style.top = topPos + 'px';
        menu.style.bottom = 'auto';
    }
    menu.style.left = leftPos + 'px';

    if (typeof myCloudApplyTheme === 'function') myCloudApplyTheme();
    menu.style.visibility = 'visible';
};

window._emailPromptFolderAction = function(action, folderId, promptText, initialVal = '') {
    const L = typeof myCloud_LANG !== 'undefined' ? myCloud_LANG : {};
    if (typeof myCloudShowInputModal === 'function') {
        myCloudShowInputModal(L.folder_action || 'Folder Action', promptText, initialVal, (val) => {
            const fd = new URLSearchParams({ myCloud_action: action, myCloud_key: myCloudState.key, myCloud_token: window.myCloudCsrfToken, account_id: myCloudEmailState.activeAccount, folder: folderId, name: val });
            fetch('', {method:'POST', body:fd}).then(r=>r.json()).then(res => {
                if (res.status === 'OK') myCloudEmailFetchFolders(true);
                else if (typeof myCloudShowAlert === 'function') myCloudShowAlert(L.error_prefix || 'Error', res.msg || L.action_not_supported || 'Action not supported by server yet.');
            });
        });
    }
};

window._emailPromptFolderDelete = function(folderId) {
    const L = typeof myCloud_LANG !== 'undefined' ? myCloud_LANG : {};
    if (typeof myCloudShowAlert === 'function') {
        myCloudShowAlert(L.delete_folder || 'Delete Folder', L.confirm_del_msg || 'Are you sure you want to delete this folder?', () => {
            const fd = new URLSearchParams({ myCloud_action: 'email_delete_folder', myCloud_key: myCloudState.key, myCloud_token: window.myCloudCsrfToken, account_id: myCloudEmailState.activeAccount, folder: folderId });
            fetch('', {method:'POST', body:fd}).then(r=>r.json()).then(res => {
                if (res.status === 'OK') {
                    if (myCloudEmailState.activeFolder === folderId) myCloudEmailState.activeFolder = 'INBOX';
                    myCloudEmailFetchFolders(true);
                }
                else myCloudShowAlert(L.error_prefix || L.action_not_supported || 'Action not supported by server yet.');
            });
        });
    }
};

window._emailToggleReadStatus = function(msgId, markAsRead) {
    const clickedMsg = myCloudEmailState.currentMessages.find(m => String(m.id) === String(msgId));
    if (!clickedMsg) return;

    const targetAcc = clickedMsg.account_id || myCloudEmailState.activeAccount;
    const targetFolder = clickedMsg.folder || myCloudEmailState.activeFolder;

    const msgKey = targetAcc + '|' + targetFolder + '|' + msgId;
    let baseTargetKeys = [msgKey];
    
    // Support multi-selection toggling
    if (myCloudEmailState.selectedMessages && myCloudEmailState.selectedMessages.includes(msgKey)) {
        baseTargetKeys = [...myCloudEmailState.selectedMessages];
    }

    // --- THREAD EXPANSION ---
    let expandedKeys = [];
    baseTargetKeys.forEach(k => {
        expandedKeys.push(k);
        const parts = k.split('|');
        const renderedMsg = (window.myCloudEmailState.renderedMessages || []).find(m => String(m.id) === String(parts[2]) && (m.account_id || myCloudEmailState.activeAccount) === parts[0] && (m.folder || myCloudEmailState.activeFolder) === parts[1]);
        if (renderedMsg && renderedMsg.is_thread_parent && renderedMsg.children) {
            renderedMsg.children.forEach(child => {
                expandedKeys.push(parts[0] + '|' + parts[1] + '|' + child.id);
            });
        }
    });
    let targetKeys = [...new Set(expandedKeys)];

    const action = markAsRead ? 'email_mark_read' : 'email_mark_unread';
    
    let backendGroups = {};
    let revertState = []; 

    targetKeys.forEach(k => {
        const parts = k.split('|');
        const acc = parts[0];
        const fld = parts[1];
        const id = parts[2];
        
        // STRICT 1:1 TARGETING:
        // Find only the exact raw message ID that was clicked. 
        // We intentionally do NOT unroll thread children here to prevent the badge from multiplying.
        const msg = myCloudEmailState.currentMessages.find(m => String(m.id) === String(id) && (m.account_id || myCloudEmailState.activeAccount) === acc && (m.folder || myCloudEmailState.activeFolder) === fld);
        
        if (msg && msg.is_read !== markAsRead) {
            // Save the exact state in case the server fails and we need to revert
            revertState.push({ msg: msg, acc: acc, fld: fld, previous_read: msg.is_read });
            
            // Optimistically update the UI state
            msg.is_read = markAsRead;

            const grpKey = acc + '|' + fld;
            if (!backendGroups[grpKey]) backendGroups[grpKey] = { acc, fld, ids: new Set() };
            backendGroups[grpKey].ids.add(msg.id);

            // Update UI badge counts securely using uppercase comparison
            if (myCloudEmailState.foldersData[acc]) {
                const folderData = myCloudEmailState.foldersData[acc].find(f => f.id === fld);
                if (folderData) {
                    folderData.unread += markAsRead ? -1 : 1;
                    if (folderData.unread < 0) folderData.unread = 0;
                    if (fld.toUpperCase() === 'INBOX') myCloudEmailState.inboxUnreadCounts[acc] = folderData.unread;
                }
            }
        }
    });

    if (Object.keys(backendGroups).length === 0) return;

    // Flush DOM Updates
    myCloudEmailRenderTree();
    window._emailRenderMessageList();

    const processedGroups = Object.values(backendGroups).map(g => ({...g, ids: Array.from(g.ids)}));

    // Dispatch physical changes to the server
    const promises = processedGroups.map(g => {
        const fd = new URLSearchParams({ myCloud_action: action, myCloud_key: myCloudState.key, myCloud_token: window.myCloudCsrfToken, account_id: g.acc, folder: g.fld, message_id: g.ids.join(',') });
        return fetch('', {method:'POST', body:fd}).then(r=>r.json()).then(res => {
            if (res.status !== 'OK') throw new Error(res.msg);
            return { group: g, res: res };
        });
    });

    // Revert visual changes if the network fails
    Promise.all(promises).catch(err => {
        revertState.forEach(rev => {
            rev.msg.is_read = rev.previous_read;
            if (myCloudEmailState.foldersData[rev.acc]) {
                const folderData = myCloudEmailState.foldersData[rev.acc].find(f => f.id === rev.fld);
                if (folderData) {
                    folderData.unread += !markAsRead ? -1 : 1;
                    if (folderData.unread < 0) folderData.unread = 0;
                    if (rev.fld.toUpperCase() === 'INBOX') myCloudEmailState.inboxUnreadCounts[rev.acc] = folderData.unread;
                }
            }
        });
        myCloudEmailRenderTree();
        window._emailRenderMessageList();
    });
};


window._emailPromptMoveCopy = function(action, msgData) {
    const overlay = document.getElementById('myCloudModalOverlay');
    const modal = document.getElementById('myCloudModal');
    const L = typeof myCloud_LANG !== 'undefined' ? myCloud_LANG : {};
    if (typeof myCloudResetModal === 'function') myCloudResetModal();
    overlay.style.display = 'flex';
    modal.className = 'myCloudModal ce-email-app-root';

    const srcAcc = msgData.account_id || myCloudEmailState.activeAccount;
    const srcFolder = msgData.folder || myCloudEmailState.activeFolder;
    const msgKey = srcAcc + '|' + srcFolder + '|' + msgData.id;
    
    let targetKeys = [msgKey];
    if (myCloudEmailState.selectedMessages && myCloudEmailState.selectedMessages.includes(msgKey)) {
        targetKeys = [...myCloudEmailState.selectedMessages];
    }
    
    let accOpts = '';
    Object.keys(myCloudEmailState.accounts).forEach(id => {
        const a = myCloudEmailState.accounts[id];
        const sel = (id === srcAcc) ? 'selected' : '';
        accOpts += '<option value="' + id + '" ' + sel + '>' + myCloudEscapeHtml(a.name || a.email) + '</option>';
    });
    
    modal.innerHTML = 
        '<div class="myCloudModalHeader">' + myCloudSvgLogo + " "  + (action === 'move' ? (L.move_msg || 'Move Message(s)') : (L.copy_msg || 'Copy Message(s)')) + '</div>' +
        '<div class="myCloudModalBody" style="padding:20px; display:flex; flex-direction:column; gap:15px;">' +
            '<div><label style="font-size:12px; font-weight:bold; color:var(--text-secondary);">' + (L.dest_acc || 'Destination Account') + '</label>' +
            '<select id="ceEmlDestAcc" class="myCloudInlineInput" style="width:100%; margin-top:5px;">' + accOpts + '</select></div>' +
            '<div><label style="font-size:12px; font-weight:bold; color:var(--text-secondary);">' + (L.dest_folder || 'Destination Folder') + '</label>' +
            '<div id="ceEmlDestFolderWrap"><div class="ce-email-empty" style="padding:10px;">' + (L.loading_folders || 'Loading folders...') + '</div></div></div>' +
            '<div class="myCloudButtons"><button onclick="myCloudCloseModal()">' + (L.cancel || 'Cancel') + '</button>' +
            '<button id="ceEmlDestSubmit" style="background:var(--accent-primary); color:#fff; border:none;" disabled>' + (action === 'move' ? (L.move || 'Move') : (L.copy || 'Copy')) + '</button></div>' +
       '</div>';   
    const accSel = document.getElementById('ceEmlDestAcc');
    const folderWrap = document.getElementById('ceEmlDestFolderWrap');
    const btnSubmit = document.getElementById('ceEmlDestSubmit');
    
    const loadFolders = (accId) => {
        folderWrap.innerHTML = '<div class="ce-email-empty" style="padding:10px;">' + (L.loading_folders || 'Loading folders...') + '</div>';
        btnSubmit.disabled = true;
        const fd = new URLSearchParams({ myCloud_action: 'email_get_folders', myCloud_key: myCloudState.key, myCloud_token: window.myCloudCsrfToken, account_id: accId });
        fetch('', {method:'POST', body:fd}).then(r=>r.json()).then(res => {
            if (res.status === 'OK') {
                let fOpts = '';
                res.folders.forEach(f => { fOpts += '<option value="' + f.id + '">' + myCloudEscapeHtml(f.name || f.id) + '</option>'; });
                folderWrap.innerHTML = '<select id="ceEmlDestFolder" class="myCloudInlineInput" style="width:100%; margin:top:5px;">' + fOpts + '</select>';
                btnSubmit.disabled = false;
            } else { folderWrap.innerHTML = '<div class="ce-email-empty" style="color:var(--danger); padding:10px;">' + (L.err_load_folders || 'Error loading folders') + '</div>'; }
        });
    };
    
    accSel.onchange = () => loadFolders(accSel.value);
    loadFolders(accSel.value);
    
    btnSubmit.onclick = () => {
        const destFolder = document.getElementById('ceEmlDestFolder').value;
        if (typeof myCloudCloseModal === 'function') myCloudCloseModal();
        window._emailExecMoveCopy(action, targetKeys, accSel.value, destFolder);
    };
};

 window._emailExecMoveSpam = function(targetKeys, srcAcc) {
     let spamFolder = 'Spam';
     if (myCloudEmailState.foldersData[srcAcc]) {
         const spamF = myCloudEmailState.foldersData[srcAcc].find(f => /spam|junk/i.test(f.id));
         if (spamF) spamFolder = spamF.id;
     }
     window._emailExecMoveCopy('move', targetKeys, srcAcc, spamFolder);
 };

window._emailExecMoveCopy = function(action, baseTargetKeys	, destAcc, destFolder) {
    if (typeof myCloudShowLoading === 'function') myCloudShowLoading();
    const L = typeof myCloud_LANG !== 'undefined' ? myCloud_LANG : {};

    // --- THREAD EXPANSION ---
    let targetKeys = [];
    baseTargetKeys.forEach(k => {
        targetKeys.push(k);
        const parts = k.split('|');
        const renderedMsg = (window.myCloudEmailState.renderedMessages || []).find(m => String(m.id) === String(parts[2]) && (m.account_id || myCloudEmailState.activeAccount) === parts[0] && (m.folder || myCloudEmailState.activeFolder) === parts[1]);
        if (renderedMsg && renderedMsg.is_thread_parent && renderedMsg.children) {
            renderedMsg.children.forEach(child => {
                targetKeys.push(parts[0] + '|' + parts[1] + '|' + child.id);
            });
        }
    });
    targetKeys = [...new Set(targetKeys)];
    
    const groups = window._emailGroupSelectedMessages(targetKeys);

    let nextMsgKey = null;
    if (action === 'move') {
        const listItems = Array.from(document.querySelectorAll('#ceEmailListContent .ce-email-list-item'));
        const firstDeletedIndex = listItems.findIndex(el => baseTargetKeys.includes(el.dataset.msgKey));
        if (firstDeletedIndex !== -1) {
            for (let i = firstDeletedIndex + 1; i < listItems.length; i++) {
                if (!baseTargetKeys.includes(listItems[i].dataset.msgKey)) { nextMsgKey = listItems[i].dataset.msgKey; break; }
            }
            if (!nextMsgKey) {
                for (let i = firstDeletedIndex - 1; i >= 0; i--) {
                    if (!baseTargetKeys.includes(listItems[i].dataset.msgKey)) { nextMsgKey = listItems[i].dataset.msgKey; break; }
                }
            }
        }
        if (nextMsgKey) myCloudEmailState.pendingSelectMsgKey = nextMsgKey;
    }
    
    const promises = groups.map(g => {
        if (g.acc === destAcc && g.fld === destFolder) return Promise.resolve({status: 'OK'});
        const fd = new URLSearchParams({ 
            myCloud_action: action === 'move' ? 'email_move_msg' : 'email_copy_msg', 
            myCloud_key: myCloudState.key, 
            myCloud_token: window.myCloudCsrfToken, 
            account_id: g.acc, 
            folder: g.fld, 
            message_id: g.ids.join(','), 
            dest_account_id: destAcc, 
            dest_folder: destFolder 
        });
        return fetch('', { method: 'POST', body: fd }).then(r=>r.json());
    });

    Promise.all(promises).then(results => {
            if (typeof myCloudHideLoading === 'function') myCloudHideLoading();
            const failed = results.find(r => r.status !== 'OK');
            if (!failed) {
                if (action === 'move') {
                    if (!myCloudEmailState.pendingDeletes) myCloudEmailState.pendingDeletes = new Set();
                    targetKeys.forEach(k => myCloudEmailState.pendingDeletes.add(k));

                    // --- SUBTRACT UNREAD COUNT FOR MOVED MESSAGES ---
                    let unreadMovedCountByFolder = {};
                    targetKeys.forEach(k => {
                        const parts = k.split('|');
                        const msgObj = myCloudEmailState.currentMessages.find(m => String(m.id) === String(parts[2]) && (m.account_id || myCloudEmailState.activeAccount) === parts[0] && (m.folder || myCloudEmailState.activeFolder) === parts[1]);
                        if (msgObj && !msgObj.is_read) {
                            const grp = parts[0] + '|' + parts[1];
                            unreadMovedCountByFolder[grp] = (unreadMovedCountByFolder[grp] || 0) + 1;
                        }
                    });

                    Object.keys(unreadMovedCountByFolder).forEach(grp => {
                        const parts = grp.split('|');
                        const acc = parts[0], fld = parts[1];
                        const count = unreadMovedCountByFolder[grp];
                        if (myCloudEmailState.foldersData[acc]) {
                            const folderData = myCloudEmailState.foldersData[acc].find(f => f.id === fld);
                            if (folderData && folderData.unread > 0) {
                                folderData.unread = Math.max(0, folderData.unread - count);
                                if (fld.toUpperCase() === 'INBOX') myCloudEmailState.inboxUnreadCounts[acc] = folderData.unread;
                            }
                        }
                    });
                    myCloudEmailRenderTree();

                    if (!nextMsgKey) {
                        document.getElementById('emailPaneReading').innerHTML = '<div class="ce-email-empty">' + (L.msg_moved || 'Message(s) moved successfully.') + '</div>';
                        myCloudEmailState.currentMessages = myCloudEmailState.currentMessages.filter(m => {
                            const k = (m.account_id || myCloudEmailState.activeAccount) + '|' + (m.folder || myCloudEmailState.activeFolder) + '|' + m.id;
                            return !targetKeys.includes(k);
                        });
                        window._emailRenderMessageList();    
                    }
                }
                if (typeof myCloudShowAlert === 'function') myCloudShowAlert(L.success || 'Success', action === 'move' ? (L.msg_moved || 'Message(s) moved successfully.') : (L.msg_copied || 'Message(s) copied successfully.'));
				} else {
            if (typeof myCloudShowAlert === 'function') myCloudShowAlert(L.error_prefix || 'Error', failed.msg || (L.op_failed || 'Operation failed.'));
        }
		fetch('', { method: 'POST', body: new URLSearchParams({ myCloud_action: 'email_process_outbox', myCloud_key: myCloudState.key, myCloud_token: window.myCloudCsrfToken }) });
    }).catch(() => { if (typeof myCloudHideLoading === 'function') myCloudHideLoading(); if (typeof myCloudShowAlert === 'function') myCloudShowAlert(L.error_prefix || 'Error', L.net_error || 'Network error.'); });
};

window._emailImportAttachedKey = function(accId, folder, msgId, part, filename, senderEmail) {
    if (typeof myCloudCreateProgressUI === 'function') myCloudCreateProgressUI('Importing Key...');
    const fd = new URLSearchParams({ myCloud_action: 'email_dl_attach', myCloud_key: myCloudState.key, myCloud_token: window.myCloudCsrfToken, account_id: accId, folder: folder, message_id: msgId, part: part, filename: filename });
    
    fetch('', { method: 'POST', body: fd }).then(r => r.blob()).then(blob => {
        const reader = new FileReader();
        reader.onload = function(e) {
            const pubKeyBlock = e.target.result.trim();
            if (!pubKeyBlock.includes('BEGIN PGP PUBLIC KEY')) {
                if (typeof myCloudCloseProgressUI === 'function') myCloudCloseProgressUI();
                return myCloudShowAlert(L.error_prefix || 'Error', L.err_invalid_pgp_block || 'File does not contain a valid PGP Public Key block.');
			}

            const allContacts = [...(window.myCloudEmailState.contacts || []), ...(window.myCloudEmailState.autoContacts || [])];
            let contact = allContacts.find(c => c.emails && c.emails.some(em => em.val.toLowerCase() === senderEmail));
            
            if (!contact) {
                // Create a basic auto-contact if they don't exist yet
                // FIX: Replaced PHP uniqid() with native JS random string generator
                contact = { id: 'auto_' + Math.random().toString(36).substr(2, 9), name: senderEmail.split('@')[0], emails: [{type: 'Collected', val: senderEmail}] };
            }

            contact.pgp_public_key = pubKeyBlock;
            const saveFd = new URLSearchParams({ myCloud_action: 'email_save_contact', myCloud_key: myCloudState.key, myCloud_token: window.myCloudCsrfToken, book_type: window.myCloudEmailState.contacts.some(c => c.id === contact.id) ? 'main' : 'auto', contact_id: contact.id, name: contact.name || '', emails: JSON.stringify(contact.emails || []), pgp_public_key: pubKeyBlock });
            
            fetch('', { method: 'POST', body: saveFd }).then(r=>r.json()).then(res => {
                if (typeof myCloudCloseProgressUI === 'function') myCloudCloseProgressUI();
                if (res.status === 'OK') {
                    const L = typeof myCloud_LANG !== 'undefined' ? myCloud_LANG : {};
                    myCloudShowAlert(L.success || 'Success', L.pgp_key_added || 'PGP Key added to contact successfully.');
                }
            });
        };
        reader.readAsText(blob);
    });
};

window._emailPreviewAttachment = function(e, accId, folder, msgId, part, filename) {
    e.preventDefault();
    e.stopPropagation();
    
    if (typeof myCloudShowLoading === 'function') myCloudShowLoading();
    const fd = new URLSearchParams({
        myCloud_action: 'email_dl_attach',
        myCloud_key: myCloudState.key,
        myCloud_token: window.myCloudCsrfToken,
        account_id: accId,
        folder: folder,
        message_id: msgId,
        part: part,
        filename: filename
    });
    
    // Fetch directly into memory, then pipe to the existing image/pdf previewer
    fetch('', { method: 'POST', body: fd })
    .then(r => r.blob())
    .then(blob => {
        if (typeof myCloudHideLoading === 'function') myCloudHideLoading();
        const blobUrl = URL.createObjectURL(blob);
        if (typeof myCloudOpenPreview === 'function') myCloudOpenPreview(blobUrl, filename, filename);
    })
    .catch(() => {
        if (typeof myCloudHideLoading === 'function') myCloudHideLoading();
        if (typeof myCloudShowAlert === 'function') myCloudShowAlert(L.error_prefix || 'Error', L.err_load_attach_prev || 'Failed to load attachment for preview.');
    });
};



window.myCloudRenderEmailApp = function(container) {
    const L = typeof myCloud_LANG !== 'undefined' ? myCloud_LANG : {};
    
    // LOCK THE VIEWPORT: Force the container to fill the screen and stop the outer app from scrolling
    container.style.display = 'flex';
    container.style.flexDirection = 'column';
    container.style.height = '100%';
    container.style.overflow = 'hidden';
	container.classList.add('ce-email-app-root');

    container.innerHTML = '';
    const mainToolbar = document.getElementById('myCloudToolbar');
    if (mainToolbar) mainToolbar.style.display = 'none';

    // Permissions check for UI Rendering
    const canSend = window.myCloudActionAllowed('email_send');
    const canContacts = window.myCloudActionAllowed('email_contacts');
    const canSettings = window.myCloudActionAllowed('email_settings');

// 1. OWA NATIVE TOOLBAR (Completely Isolated Classes)
     const toolbarWrap = document.createElement('div');
     toolbarWrap.id = 'ceEmlMainToolbarWrap';
     toolbarWrap.className = 'myCloudToolbar-wrapper';
     toolbarWrap.style.flexShrink = '0';
     toolbarWrap.style.width = '100%';
     toolbarWrap.style.overflow = 'hidden';
     
     const toolbar = document.createElement('div');
     toolbar.id = 'ceEmlMainToolbar';
     toolbar.className = 'owa-toolbar';
     toolbar.style.flexWrap = 'nowrap';
     
     toolbar.innerHTML = 
       '<style>#ceEmlMainToolbar .owa-btn { flex-shrink: 0 !important; } @media (max-width: 767px) { #ceEmlMainToolbar .hide-mobile { display: none !important; } } @media (min-width: 768px) { #ceEmlMainToolbar .ce-email-mobile-only { display: none !important; } }</style>' +
             '<button class="owa-btn hide-mobile" onclick="window._emailToggleTree()" title="' + (L.toggle_folders || 'Toggle Folders') + '">' +
                 '<span class="owa-icon"><svg viewBox="0 0 24 24" style="fill:currentColor; stroke:none;"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg></span>' +
             '</button>' +
             '<button class="owa-btn ce-email-mobile-only" onclick="window._emailSetMobileView(\'tree\')" title="Menu">' +
                 '<span class="owa-icon"><svg viewBox="0 0 24 24" style="fill:currentColor; stroke:none;"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg></span>' +
             '</button>' +
            (canSend ? 
         '<button class="owa-btn owa-primary" onclick="myCloudShowEmailComposer()">' +
             '<span class="owa-icon"><svg viewBox="0 0 24 24"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg></span>' +
            '<span class="owa-label ce-main-tier-3">' + (L.compose || 'New mail') + '</span>' +
         '</button>' +
        '<div class="owa-divider ce-main-divider-collapse"></div>' : '') +
         '<button class="owa-btn" onclick="myCloudEmailFetchFolders(true); myCloudEmailFetchMessages(myCloudEmailState.activeFolder, false);" title="' + (L.refresh || 'Completely Rebuild Cache') + '">' +
             '<span class="owa-icon"><svg viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg></span>' +
            '<span class="owa-label ce-main-tier-2 hide-mobile">' + (L.refresh || 'Refresh') + '</span>' +
         '</button>' +
         '<button class="owa-btn" onclick="myCloudEmailFetchMessages(myCloudEmailState.activeFolder, false, false, true);" title="' + (L.rebuild_cache || 'Completely Rebuild Cache') + '">' +
             '<span style="color: var(--danger, #e81123);" class="owa-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
					'<path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.92-10.27l5.43 5.43"/>' +
					'<line x1="12" y1="6" x2="12" y2="12" stroke="#600000" stroke-width="3"/>' +
					'<circle  cx="12" cy="17" r="1.2"  fill="#a00000" stroke="#600000" stroke-width="0"/>' +
					'</svg> '  + '</span>' +
					'<span class="owa-label ce-main-tier-2 hide-mobile">' + (L.rebuild_cache || 'Completely Rebuild Cache') + '</span>' +
         '</button>' +
        '<div style="flex:1; min-width:10px; cursor:default; background:transparent;"></div>' + 
        '<div style="display:flex; flex-wrap:nowrap; flex-shrink:0; gap:4px; margin-inline-start:auto;">' +
        (canContacts ? 
        '<button class="owa-btn" onclick="myCloudShowEmailContacts()">' +
             '<span class="owa-icon"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg></span>' +
            '<span class="owa-label ce-main-tier-4 hide-mobile">' + (L.contacts || 'Contacts') + '</span>' +
         '</button>' : '') +
        (canSettings ? 
         '<button class="owa-btn" onclick="myCloudShowEmailSettings()">' +
             '<span class="owa-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h-.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg></span>' +
            '<span class="owa-label ce-main-tier-1 hide-mobile">' + (L.email_settings || 'Settings') + '</span>' +
         '</button>' : '') +
         (window.myCloudActionAllowed('settings') ? 
         '<button class="owa-btn" onclick="if(typeof myCloudShowSettings === \'function\') myCloudShowSettings(); else if(typeof myCloudToggleSettings === \'function\') myCloudToggleSettings();">' +
             '<span class="owa-icon"><svg viewBox="0 0 24 24"><polygon points="14 2 18 6 7 17 3 17 3 13 14 2"></polygon><line x1="3" y1="22" x2="21" y2="22"></line></svg></span>' +
            '<span class="owa-label ce-main-tier-1 hide-mobile">' + (L.options || 'Options') + '</span>' +
         '</button>' : '') +
         (window.myCloudActionAllowed('help') ? 
		 '<button class="owa-btn" onclick="if(typeof myCloudOpenHelp === \'function\') myCloudOpenHelp();">' +
             '<span class="owa-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg></span>' +
            '<span class="owa-label ce-main-tier-4 hide-mobile">' + (L.help_btn || 'Help') + '</span>' +
        '</button>' : '') +
        '</div>';

    toolbarWrap.appendChild(toolbar);

    // 2. 3-PANE LAYOUT
    const paneWrapper = document.createElement('div');
    paneWrapper.className = 'ce-email-panes';
    paneWrapper.style.display = 'flex';
    paneWrapper.style.flex = '1';
    paneWrapper.style.minHeight = '0'; // Crucial for Firefox flex behavior
    paneWrapper.style.overflow = 'hidden';

    const devKey = typeof myCloudGetCurrentDeviceKey === 'function' ? myCloudGetCurrentDeviceKey() : 'desktop';
    const settings = (myCloudState.settings && myCloudState.settings[devKey]) ? myCloudState.settings[devKey] : {};

    if (typeof settings.emailTreeVisible === 'undefined') settings.emailTreeVisible = true;

    if (!window._emailToggleTree) {
        window._emailToggleTree = function() {
            const dk = typeof myCloudGetCurrentDeviceKey === 'function' ? myCloudGetCurrentDeviceKey() : 'desktop';
            if (!myCloudState.settings) myCloudState.settings = {};
            const s = myCloudState.settings[dk] || {};
            s.emailTreeVisible = !s.emailTreeVisible;
            myCloudState.settings[dk] = s;
            
            const t = document.getElementById('emailPaneTree');
            const r = t ? t.nextElementSibling : null;
            
            if (s.emailTreeVisible) {
                if (t) t.style.display = '';
                if (r && r.classList.contains('ce-email-resizer')) r.style.display = '';
            } else {
                if (t) t.style.display = 'none';
                if (r && r.classList.contains('ce-email-resizer')) r.style.display = 'none';
            }
            if (typeof myCloudSaveSettings === 'function') myCloudSaveSettings();
            window.dispatchEvent(new Event('resize'));
        };
    }

    const matchTree = document.cookie.match(/(^| )myCloud_emailTreeSize=([^;]+)/);
    const matchList = document.cookie.match(/(^| )myCloud_emailListSize=([^;]+)/);

    const treeWidth = matchTree ? parseInt(matchTree[2], 10) : (settings.emailTreeSize || 250);
    const listWidth = matchList ? parseInt(matchList[2], 10) : (settings.emailListSize || 320);

    const paneTree = document.createElement('div');
    paneTree.id = 'emailPaneTree';
    paneTree.className = 'myCloudTree ce-email-tree'; 
    paneTree.style.width = treeWidth + 'px';
    paneTree.style.display = settings.emailTreeVisible ? '' : 'none';

    const resizerTree = document.createElement('div');
    resizerTree.className = 'ce-email-resizer';
    resizerTree.style.display = settings.emailTreeVisible ? '' : 'none';

    const paneList = document.createElement('div');
    paneList.id = 'emailPaneList';
    paneList.className = 'ce-email-list'; 
    paneList.style.display = 'flex'; 
    paneList.style.flexDirection = 'column';
    paneList.style.width = listWidth + 'px';

    const resizerList = document.createElement('div');
    resizerList.className = 'ce-email-resizer';

    const paneReading = document.createElement('div');
    paneReading.id = 'emailPaneReading';
    paneReading.className = 'myCloudDetails ce-email-reading';
    paneReading.innerHTML = '<div class="ce-email-empty">' + (L.email_select_msg || 'Select a message to read') + '</div>';

    const dynamicStyle = document.createElement('style');
        dynamicStyle.innerHTML =
            '#emailPaneTree .myCloudTreeList li > div { font-size: var(--font-size-base, 14px); height: var(--tree-row-height, 30px); }' +
            '#emailPaneTree .myCloudTreeList li > div .myCloudIcon { font-size: calc(var(--font-size-base, 14px) * 1.2); }' +
            '#emailPaneTree .myCloudTreeList li > div .myCloudToggle { font-size: var(--toggle-size, 12px); }' +
            '.ce-email-list-item { padding: calc(var(--font-size-base, 14px) * 0.5) 16px; min-height: var(--row-height, 32px); box-sizing: border-box; }' +
            '.ce-email-list-sender { font-size: var(--font-size-base, 14px); }' +
            '.ce-email-list-subject { font-size: calc(var(--font-size-base, 14px) * 0.95); }' +
            '.ce-email-list-date { font-size: calc(var(--font-size-base, 14px) * 0.85); }' +
            '.ce-email-thread-child { border-inline-start: 4px solid var(--border-medium) !important; border-inline-end: 4px solid var(--border-medium) !important; padding-inline-start: 15px !important; background-color: var(--gray-20); }' +
            '.ce-email-thread-child.selected { border-inline-start-color: var(--accent-primary) !important; }' +
            '.ce-email-thread-child .ce-email-unread-dot { inset-inline-start: 4px !important; }' +
            '.ce-email-empty { font-size: var(--font-size-base, 14px); }';
        paneWrapper.appendChild(dynamicStyle);

    paneWrapper.appendChild(paneTree);
    paneWrapper.appendChild(resizerTree);
    paneWrapper.appendChild(paneList);
    paneWrapper.appendChild(resizerList);
    paneWrapper.appendChild(paneReading);

    // Initialize List View and Sort State
   if (myCloudState.settings) {
       if (myCloudState.settings.emailListFilter) {
           myCloudEmailState.listFilter = myCloudState.settings.emailListFilter;
           myCloudEmailState.threadView = (myCloudState.settings.emailListFilter === 'threads');
       } else if (typeof myCloudEmailState.threadView === 'undefined') {
           const match = document.cookie.match(/(^| )myCloud_emailThreadView=([^;]+)/);
           myCloudEmailState.threadView = match ? (match[2] === '1') : false;
           if (myCloudEmailState.threadView) myCloudEmailState.listFilter = 'threads';
       }
       if (myCloudState.settings.emailListSort) {
           myCloudEmailState.listSort = myCloudState.settings.emailListSort;
       }
   }

    window._emailBindResizer(resizerTree, paneTree, 'emailTreeSize', 150, 0.4);

    window._emailBindResizer(resizerTree, paneTree, 'emailTreeSize', 150, 0.4);
    window._emailBindResizer(resizerList, paneList, 'emailListSize', 200, 0.5);

    const shieldEvents = (el) => {
        el.addEventListener('mousedown', (e) => e.stopPropagation());
        el.addEventListener('contextmenu', (e) => {
            if (!e.target.closest('.ce-email-list-item') && !e.target.closest('.myCloudTreeList li > div')) {
                e.stopPropagation();
                e.preventDefault();
            }
        });
    };
    shieldEvents(toolbarWrap);
    shieldEvents(paneWrapper);

    container.appendChild(toolbarWrap);
    container.appendChild(paneWrapper);

    window._emailSetMobileView('list');

    myCloudEmailLoadAccounts();
    myCloudEmailLoadContacts();

    if ("Notification" in window && Notification.permission === "default") {
        Notification.requestPermission();
    }

    // 3. ADAPTIVE SMART POLLER (FPM-Safe)
    if (window._emailPollerInterval) {
        clearInterval(window._emailPollerInterval);
    }
	if (window._emailPollerTimer) clearTimeout(window._emailPollerTimer);

    if (!window.myCloudEmailState.lastPolledHashes) {
        window.myCloudEmailState.lastPolledHashes = {};
    }

    const pollMailbox = async () => {
		if (myCloudState.interface !== 'email' || !myCloudEmailState.activeAccount) return;
        
        let accountsToPoll = [];
        if (myCloudEmailState.activeAccount === 'smartbox') {
            accountsToPoll = Object.keys(myCloudEmailState.accounts).filter(k => !myCloudEmailState.accounts[k].is_inactive);
        } else {
            accountsToPoll = [myCloudEmailState.activeAccount];
        }

        let changeDetected = false;

        const pollPromises = accountsToPoll.map(accId => {
            const folder = (myCloudEmailState.activeAccount === 'smartbox') ? 'INBOX' : (myCloudEmailState.activeFolder || 'INBOX');
            const hashKey = accId + '|' + folder;

            const fd = new URLSearchParams({
                myCloud_action: 'email_quick_check',
                myCloud_key: myCloudState.key,
                myCloud_token: window.myCloudCsrfToken,
                account_id: accId,
                folder: folder
            });

            return fetch('', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    if (res.status === 'OK' && res.hash) {
                        if (window.myCloudEmailState.lastPolledHashes[hashKey] && window.myCloudEmailState.lastPolledHashes[hashKey] !== res.hash) {
                            changeDetected = true;
                        }
                        window.myCloudEmailState.lastPolledHashes[hashKey] = res.hash;
                    }
                }).catch(() => {});
        });

        await Promise.all(pollPromises);

        if (changeDetected) {
            myCloudEmailState.folderHashes = {}; 
            if (myCloudEmailState.activeAccount === 'smartbox') {
                myCloudEmailFetchFolders(true); 
                myCloudEmailFetchMessages('SMARTBOX', true);
            } else {
                myCloudEmailFetchFolders(true, myCloudEmailState.activeAccount);
                myCloudEmailFetchMessages(myCloudEmailState.activeFolder, true);
            }
        }
    };

    const runPoller = async () => {
        await pollMailbox();
        const interval = document.hidden ? 300000 : 60000;
        window._emailPollerTimer = setTimeout(runPoller, interval);
    };
    
    // Start polling after initial load settles
    window._emailPollerTimer = setTimeout(runPoller, 30000);

    // Instant refresh when tab becomes active again
    if (!window._emailVisibilityBound) {
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && myCloudState.interface === 'email' && myCloudEmailState.activeAccount) {
                if (window._emailPollerTimer) clearTimeout(window._emailPollerTimer);
                runPoller();
            }
        });
        window._emailVisibilityBound = true;
    }

    // ResizeObserver for main toolbar labels
    const mainTbWrap = document.getElementById('ceEmlMainToolbarWrap');
    const mainTb = document.getElementById('ceEmlMainToolbar');
    if (mainTbWrap && mainTb) {
        const checkMainWrap = () => {
            if (mainTbWrap.offsetWidth === 0) return;
            
            const t1 = mainTb.querySelectorAll('.ce-main-tier-1');
            const t2 = mainTb.querySelectorAll('.ce-main-tier-2');
            const t3 = mainTb.querySelectorAll('.ce-main-tier-3');
            const t4 = mainTb.querySelectorAll('.ce-main-tier-4');
            const divs = mainTb.querySelectorAll('.ce-main-divider-collapse');

            [t1, t2, t3, t4].forEach(t => t.forEach(el => el.style.display = ''));
            divs.forEach(el => { el.style.display = ''; el.style.margin = ''; });

            if (mainTb.scrollWidth > mainTbWrap.offsetWidth) t1.forEach(el => el.style.display = 'none');
            if (mainTb.scrollWidth > mainTbWrap.offsetWidth) t2.forEach(el => el.style.display = 'none');
            if (mainTb.scrollWidth > mainTbWrap.offsetWidth) t3.forEach(el => el.style.display = 'none');
            if (mainTb.scrollWidth > mainTbWrap.offsetWidth) t4.forEach(el => el.style.display = 'none');
            if (mainTb.scrollWidth > mainTbWrap.offsetWidth) divs.forEach(el => el.style.display = 'none');
        };

        if (window._emlMainTbResizeObs) window._emlMainTbResizeObs.disconnect();
        window._emlMainTbResizeObs = new ResizeObserver(() => checkMainWrap());
        window._emlMainTbResizeObs.observe(mainTbWrap);
        checkMainWrap();
    }
};

window.myCloudEmailLoadAccounts = function() {
    const fd = new URLSearchParams({ myCloud_action: 'email_get_accounts', myCloud_key: myCloudState.key, myCloud_token: window.myCloudCsrfToken });
    return fetch('', { method: 'POST', body: fd }).then(myCloudCheckResponse).then(res => {
        if (res.status === 'OK') {
            myCloudEmailState.accounts = res.accounts;
            const accountIds = Object.keys(res.accounts);
            if (accountIds.length > 0) {
                if (!myCloudEmailState.activeAccount || (!res.accounts[myCloudEmailState.activeAccount] && myCloudEmailState.activeAccount !== 'smartbox')) {
                    myCloudEmailState.activeAccount = 'smartbox';
                    myCloudEmailState.activeFolder = 'SMARTBOX';
                    if (accountIds.length === 1 && !myCloudEmailState.openFolders.includes('__ROOT__' + accountIds[0])) {
                        myCloudEmailState.openFolders.push('__ROOT__' + accountIds[0]);
                    }
                }
                myCloudEmailFetchFolders();
            } else {
                const L = typeof myCloud_LANG !== 'undefined' ? myCloud_LANG : {};
                document.getElementById('emailPaneTree').innerHTML = '<div style="padding:20px; text-align:center; color:var(--text-secondary);">' + (L.no_accounts_setup || 'No accounts.') + '<br><br><button onclick="myCloudShowEmailSettings()" style="padding:6px 16px; margin-top:10px; cursor:pointer; background:var(--accent-primary); color:#fff; border:none; border-radius:4px;">' + (L.setup_account_btn || 'Setup Account') + '</button></div>';
                // Trigger First Run Assistant whenever empty state is hit
                const tryTriggerFRA = (attempts) => {
                    if (typeof window.myCloudEmailFirstRunAssistant === 'function') {
                        window.myCloudEmailFirstRunAssistant();
                    } else if (attempts > 0) {
                        setTimeout(() => tryTriggerFRA(attempts - 1), 100);
                    }
                };
                tryTriggerFRA(10); // Poll up to 1 second waiting for settings.php to load
           }
        }
        }).catch(() => { const L = typeof myCloud_LANG !== 'undefined' ? myCloud_LANG : {}; document.getElementById('emailPaneTree').innerHTML = '<div style="padding:20px; color:var(--danger);">' + (L.err_load_accs || 'Error loading accounts.') + '</div>'; });
};
	
	
window.myCloudEmailLoadContacts = function() {
    const fd = new URLSearchParams({ myCloud_action: 'email_get_contacts', myCloud_key: myCloudState.key, myCloud_token: window.myCloudCsrfToken });
    return fetch('', { method: 'POST', body: fd }).then(r=>r.json()).then(res => {
		if (res.status === 'OK') {
             myCloudEmailState.contacts = res.contacts;
             myCloudEmailState.autoContacts = res.auto_contacts || [];
             // If the contacts modal is open, refresh it automatically
             if (typeof window._emlRenderContactList === 'function' && document.getElementById('ceContactList')) {
                 window._emlRenderContactList();
             }
         }
		 return res;
	});
};

window.myCloudEmailFetchFolders = function(silent = false, specificAccId = null) {
    if (myCloudState.interface !== 'email') return;
    const targetAcc = specificAccId || myCloudEmailState.activeAccount;
    if (!targetAcc) return;
    const L = typeof myCloud_LANG !== 'undefined' ? myCloud_LANG : {};
    const tree = document.getElementById('emailPaneTree');
    
    if (targetAcc === 'smartbox') {
        if (!myCloudEmailState.foldersData) myCloudEmailState.foldersData = {};
        myCloudEmailState.foldersData['smartbox'] = [];

        // Fetch all underlying accounts silently to populate definitive IMAP unread counts
        Object.keys(myCloudEmailState.accounts).forEach(accId => {
            if (!myCloudEmailState.accounts[accId].is_inactive) {
                window.myCloudEmailFetchFolders(true, accId);
            }
        });

        myCloudEmailRenderTree();
        if (!silent && !specificAccId) myCloudEmailFetchMessages('SMARTBOX');
        return;
    }

    if (!silent && tree && !specificAccId) tree.classList.add('ce-pane-loading');

    const fd = new URLSearchParams({ myCloud_action: 'email_get_folders', myCloud_key: myCloudState.key, myCloud_token: window.myCloudCsrfToken, account_id: targetAcc });
    fetch('', { method: 'POST', body: fd }).then(myCloudCheckResponse).then(res => {
        if (myCloudState.interface !== 'email') return;
        if (tree && !specificAccId) tree.classList.remove('ce-pane-loading');
        
        if (res.status === 'OK') {
            if (!myCloudEmailState.foldersData) myCloudEmailState.foldersData = {};
            myCloudEmailState.foldersData[targetAcc] = res.folders;
            
            const inbox = res.folders.find(f => f.name.toUpperCase() === 'INBOX' || f.id.toUpperCase() === 'INBOX');
            if (inbox) myCloudEmailState.inboxUnreadCounts[targetAcc] = inbox.unread;
            
            myCloudEmailRenderTree();
            
            if (!silent && !specificAccId) myCloudEmailFetchMessages(myCloudEmailState.activeFolder);
        } else {
            if (!silent && tree && !specificAccId) tree.innerHTML = '<div class="ce-email-empty" style="color:var(--danger); font-weight:bold;">Error:<br><br>' + myCloudEscapeHtml(res.msg) + '</div>';
        }
    }).catch((err) => {
        if (err.name === 'AbortError') return;
        if (!silent && tree && !specificAccId) tree.innerHTML = '<div class="ce-email-empty" style="color:var(--danger); font-weight:bold;">Error:<br><br>' + myCloudEscapeHtml(err.message) + '</div>';
    });
};

window.myCloudEmailToggleRoot = function(e) {
    if (e) e.stopPropagation();
    const isOpen = myCloudEmailState.openFolders.includes('__ROOT__');
    if (isOpen) {
        myCloudEmailState.openFolders = myCloudEmailState.openFolders.filter(d => d !== '__ROOT__');
    } else {
        myCloudEmailState.openFolders.push('__ROOT__');
    }
    myCloudEmailRenderTree();
};

window.myCloudEmailRenderTree = function() {
  if (window._emailTreeDebounce) cancelAnimationFrame(window._emailTreeDebounce);
  window._emailTreeDebounce = requestAnimationFrame(() => {
    const treeContainer = document.getElementById('emailPaneTree');
    if (!treeContainer) return;
    const fragment = document.createDocumentFragment();

    const buildHtml = (obj, accountId) => {
        const ul = document.createElement('ul');
        ul.className = 'myCloudTreeList';
        
        Object.keys(obj).sort((a,b) => {
            if (a.toUpperCase() === 'INBOX') return -1;
            if (b.toUpperCase() === 'INBOX') return 1;
            return a.localeCompare(b);
        }).forEach(key => {
            if (key === '__children') return;
            const node = obj[key];
            const hasChildren = Object.keys(node.__children).length > 0;
            const fullId = node.fullId; 
            const isOpen = myCloudEmailState.openFolders.includes(fullId);
            
            const li = document.createElement('li');
            // Only highlight active folder if we are looking at the active account
            if (myCloudEmailState.activeFolder === fullId && myCloudEmailState.activeAccount === accountId) {
                li.classList.add('selectedFolder');
            }

            const div = document.createElement('div');
            
            const toggle = document.createElement('span');
            toggle.className = 'myCloudToggle';
            toggle.innerHTML = hasChildren ? (isOpen ? '▾' : '▸') : '';
            if (hasChildren) {
                toggle.onclick = (e) => {
                    e.stopPropagation();
                    if (isOpen) myCloudEmailState.openFolders = myCloudEmailState.openFolders.filter(d => d !== fullId);
                    else myCloudEmailState.openFolders.push(fullId);
                    myCloudEmailRenderTree(); 
                };
            }
            div.appendChild(toggle);

            const icon = document.createElement('span');
            icon.className = 'myCloudIcon';
            let lowerKey = node.dispName.toLowerCase();
            
            let iconSvg = '<svg viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>';
            
            if (lowerKey === 'inbox') iconSvg = '<svg viewBox="0 0 24 24"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"></polyline><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"></path></svg>';
            else if (lowerKey === 'sent' || lowerKey === 'sent items' || lowerKey === 'sent mail') iconSvg = '<svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>';
            else if (lowerKey === 'trash' || lowerKey === 'bin' || lowerKey === 'deleted items') iconSvg = '<svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>';
            else if (lowerKey === 'drafts' || lowerKey === 'entwürfe') iconSvg = '<svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>';
            else if (lowerKey === 'spam' || lowerKey === 'junk') iconSvg = '<svg viewBox="0 0 24 24"><polygon points="7.86 2 16.14 2 22 7.86 22 16.14 16.14 22 7.86 22 2 16.14 2 7.86 7.86 2"></polygon><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>';
            
            icon.innerHTML = iconSvg;
            div.appendChild(icon);

            const nameSpan = document.createElement('span');
            let dispName = node.dispName;
            if (dispName.toUpperCase() === 'INBOX') dispName = (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.inbox) ? myCloud_LANG.inbox : 'Inbox';
            nameSpan.textContent = dispName;
            div.appendChild(nameSpan);

            if (node.unread > 0) {
                const unreadBadge = document.createElement('span');
                unreadBadge.style.cssText = 'margin-left:auto; background:var(--accent-primary); color:#fff; border-radius:10px; padding:1px 6px; font-size:10px; font-weight:bold; margin-right:10px;';
                unreadBadge.textContent = node.unread;
                div.appendChild(unreadBadge);
            }

            div.onclick = (e) => { 
                e.stopPropagation(); 
                if (myCloudEmailState.activeAccount !== accountId) {
                    myCloudEmailState.activeAccount = accountId;
                }
                myCloudEmailFetchMessages(fullId); 
                if (myCloudEmailState.mobileView === 'tree' && window.history.state && window.history.state.ce_email_view) {
                    window.history.back();
                } else {
                    window._emailSetMobileView('list');
                }
            };

            div.oncontextmenu = (e) => {
                // Ensure context menu actions apply to the right account
                if (myCloudEmailState.activeAccount !== accountId) myCloudEmailState.activeAccount = accountId;
                window.myCloudShowEmailContextMenu(e, 'folder', { id: fullId, name: node.dispName });
            };
            window._emailBindLongTouch(div, (e) => {
                if (myCloudEmailState.activeAccount !== accountId) myCloudEmailState.activeAccount = accountId;
                window.myCloudShowEmailContextMenu(e, 'folder', { id: fullId, name: node.dispName });
            });

            div.addEventListener('dragover', (e) => { e.preventDefault(); div.classList.add('drop-target'); e.dataTransfer.dropEffect = e.ctrlKey ? 'copy' : 'move'; });
            div.addEventListener('dragleave', (e) => { div.classList.remove('drop-target'); });
            div.addEventListener('drop', (e) => {
                e.preventDefault(); div.classList.remove('drop-target');
                try {
                    const data = JSON.parse(e.dataTransfer.getData('text/plain'));
                    if (data.type === 'email_msg') {
                        const isCopy = e.ctrlKey;
                        let targetKeys = data.targetKeys || [data.account_id + '|' + data.folder + '|' + data.message_id];
                        window._emailExecMoveCopy(isCopy ? 'copy' : 'move', targetKeys, accountId, fullId);
                    }
                } catch (ex) { console.error("Drop failed", ex); }
            });

            li.appendChild(div);
            if (isOpen && hasChildren) {
                li.appendChild(buildHtml(node.__children, accountId));
            }
            ul.appendChild(li);
        });
        return ul;
    };

    const rootUl = document.createElement('ul');
    rootUl.className = 'myCloudTreeList';
    
    const accountIds = Object.keys(myCloudEmailState.accounts);
    if (accountIds.length > 0) {
        const sbLi = document.createElement('li');
        if (myCloudEmailState.activeAccount === 'smartbox') sbLi.classList.add('selectedFolder');
        
       const sbDiv = document.createElement('div');
        sbDiv.style.fontWeight = myCloudEmailState.activeAccount === 'smartbox' ? '600' : 'normal';
        
        const sbToggle = document.createElement('span');
       sbToggle.className = 'myCloudToggle';
        sbDiv.appendChild(sbToggle);

        const sbIcon = document.createElement('span');
        sbIcon.className = 'myCloudIcon';
        sbIcon.innerHTML = '<svg viewBox="0 0 24 24" style="stroke:var(--accent-primary);"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"></polyline><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"></path></svg>';
        sbDiv.appendChild(sbIcon);
        
        const sbName = document.createElement('span');
	    sbName.textContent = (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.smartbox) ? myCloud_LANG.smartbox : 'SmartBox';
        sbDiv.appendChild(sbName);
        
        let totalUnread = 0;
        Object.keys(myCloudEmailState.accounts).forEach(accId => {
            if (!myCloudEmailState.accounts[accId].is_inactive) {
                totalUnread += (myCloudEmailState.inboxUnreadCounts[accId] || 0);
            }
        });
        if (totalUnread > 0) {
            const unreadBadge = document.createElement('span');
            unreadBadge.style.cssText = 'margin-inline-start:auto; background:var(--accent-primary); color:#fff; border-radius:10px; padding-block:1px; padding-inline:6px; font-size:10px; font-weight:bold; margin-inline-end:10px;';
            unreadBadge.textContent = totalUnread;
            sbDiv.appendChild(unreadBadge);
        }

        sbDiv.onclick = (e) => {
			if (e) e.stopPropagation();
            if (myCloudEmailState.activeAccount !== 'smartbox') {
                myCloudEmailState.activeAccount = 'smartbox';
                myCloudEmailState.activeFolder = 'SMARTBOX';
                myCloudEmailFetchFolders();
            } else {
                myCloudEmailFetchMessages('SMARTBOX');
            }
            if (myCloudEmailState.mobileView === 'tree' && window.history.state && window.history.state.ce_email_view) {
                window.history.back();
            } else {
                window._emailSetMobileView('list');
            }
        };
        sbLi.appendChild(sbDiv);
        rootUl.appendChild(sbLi);
    }

    Object.keys(myCloudEmailState.accounts).forEach(accId => {
        const acc = myCloudEmailState.accounts[accId];
		if (acc.is_inactive) return;
        const isActive = (accId === myCloudEmailState.activeAccount);
        const rootOpenKey = '__ROOT__' + accId;
        const rootIsOpen = myCloudEmailState.openFolders.includes(rootOpenKey);
        
        const rootLi = document.createElement('li');
        
        const rootDiv = document.createElement('div');
        rootDiv.style.fontWeight = isActive ? '600' : 'normal'; 

        const accColor = window._emailGetAccColor(accId);
        
        const rootToggle = document.createElement('span');
        rootToggle.className = 'myCloudToggle';
        rootToggle.innerHTML = rootIsOpen ? '▾' : '▸';
        rootToggle.onclick = (e) => { 
            e.stopPropagation();
            if (rootIsOpen) {
                myCloudEmailState.openFolders = myCloudEmailState.openFolders.filter(d => d !== rootOpenKey);
                myCloudEmailRenderTree();
            } else {
                myCloudEmailState.openFolders.push(rootOpenKey);
                if (!myCloudEmailState.foldersData[accId]) {
                    myCloudEmailFetchFolders(true, accId);
                } else {
                    myCloudEmailRenderTree();
                }
            }
        };
        rootDiv.appendChild(rootToggle);
    
        const rootIcon = document.createElement('span');
        rootIcon.className = 'myCloudIcon';
        rootIcon.innerHTML = '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"></circle><path d="M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-3.92 7.94"></path></svg>';
        rootDiv.appendChild(rootIcon);

        const rootColorBar = document.createElement('span');
        rootColorBar.style.cssText = 'display:inline-block; width:3px; height:14px; background-color:' + accColor + '; border-radius:2px; margin-inline-start:2px; margin-inline-end:6px; vertical-align:middle;';
        rootDiv.appendChild(rootColorBar);
    
        const rootNameSpan = document.createElement('span');
        rootNameSpan.textContent = acc.name || acc.email;
        rootDiv.appendChild(rootNameSpan);
    
        rootDiv.onclick = (e) => { 
			if (e) e.stopPropagation();
            if (!isActive) {
                myCloudEmailState.activeAccount = accId;
                myCloudEmailState.activeFolder = 'INBOX'; // Reset to inbox on switch
                if (!myCloudEmailState.openFolders.includes(rootOpenKey)) {
                    myCloudEmailState.openFolders.push(rootOpenKey);
                }
                myCloudEmailFetchFolders();
            } else {
                rootToggle.onclick(e); 
            }
            if (myCloudEmailState.mobileView === 'tree' && window.history.state && window.history.state.ce_email_view) {
                window.history.back();
            } else {
                window._emailSetMobileView('list');
            }
        };
        rootLi.appendChild(rootDiv);
    
        if (rootIsOpen && myCloudEmailState.foldersData[accId]) {
            const treeMap = { __children: {} };
            myCloudEmailState.foldersData[accId].forEach(f => {
                const delim = f.delimiter || '.';
                const prefix = 'INBOX' + delim;
                
                let path = f.name;
                if (path.toUpperCase().startsWith(prefix.toUpperCase())) {
                    path = path.substring(prefix.length);
                }
                const parts = path.split(delim);
    
                let node = treeMap;
                parts.forEach((part, idx) => {
                    if (!node.__children[part]) node.__children[part] = { __children: {}, unread: 0, fullId: f.id, dispName: part };
                    if (idx === parts.length - 1) {
                        node.__children[part].unread = f.unread;
                        node.__children[part].fullId = f.id; 
                    }
                    node = node.__children[part];
                });
            });
            rootLi.appendChild(buildHtml(treeMap.__children, accId));
        }
    
        rootUl.appendChild(rootLi);
    });

    fragment.appendChild(rootUl);
    treeContainer.innerHTML = '';
    treeContainer.appendChild(fragment);
  });
};


window.myCloudEmailFetchMessages = function(folderId, silent = false, loadMore = false, rebuildCache = false) {
    if (myCloudState.interface !== 'email') return;

    // Only abort pending fetches if the user explicitly clicked a new folder.
    // Do NOT abort if this is a silent background push from the SSE Worker!
    if (!silent) {
        if (window.myCloudEmailState.abortController) window.myCloudEmailState.abortController.abort();
        window.myCloudEmailState.abortController = new AbortController();
    }
    const currentSignal = silent ? null : window.myCloudEmailState.abortController.signal;

    myCloudEmailState.activeFolder = folderId;
    myCloudEmailRenderTree();

    const list = document.getElementById('emailPaneList');
    const listContent = document.getElementById('ceEmailListContent');
    
    if (!list) return; 
    
    const isFolderSwitch = (myCloudEmailState.lastFetchedFolder !== folderId);
    myCloudEmailState.lastFetchedFolder = folderId;
	
	const L = typeof myCloud_LANG !== 'undefined' ? myCloud_LANG : {};

    if (isFolderSwitch && !silent) {
		if (list) list.classList.remove('ce-pane-loading');
        myCloudEmailState.searchQuery = '';
        myCloudEmailState.searchScope = 'folder';
		myCloudEmailState.autoSelectFirst = true;
        const sInput = document.getElementById('ceEmailSearchInput');
        if (sInput) sInput.value = '';
        
        if (listContent) {
            listContent.innerHTML = Array(8).fill(`
                <div class="ce-email-list-item" style="pointer-events:none; border-block-end:1px solid var(--border-subtle);">
                    <div style="display:flex; justify-content:space-between; margin-block-end:6px;">
                        <div class="ce-skeleton" style="width:40%; height:12px;"></div>
                        <div class="ce-skeleton" style="width:15%; height:10px;"></div>
                    </div>
                    <div class="ce-skeleton" style="width:80%; height:14px; margin-block-end:6px;"></div>
                    <div class="ce-skeleton" style="width:60%; height:12px;"></div>
                </div>
            `).join('');
        }
        
        const reading = document.getElementById('emailPaneReading');
        if (reading && !myCloudEmailState.pendingSelectMsgKey) {
            reading.innerHTML = '<div class="ce-email-empty" style="opacity:0; animation: ceFadeIn 0.3s forwards 0.2s;">' + (L.email_select_msg || 'Select a message to read') + '</div>';
        }
        myCloudEmailState.currentMessages = [];
        myCloudEmailState.currentPage = 1;
    } else if (!loadMore) {
        // CRITICAL FIX: Always reset page to 1 on manual refresh!
        if (!silent && list) list.classList.add('ce-pane-loading');
        myCloudEmailState.currentPage = 1;
    }
	if (rebuildCache && list) list.classList.add('ce-pane-loading');
    if (loadMore) {
        myCloudEmailState.currentPage++;
        const btn = document.getElementById('ceEmailLoadMoreBtn');
        if (btn) btn.innerHTML = '<span class="ce-skeleton" style="display:inline-block; width:60px; height:12px;"></span>';
    }

	const isInstantLoad = !silent && !loadMore && !myCloudEmailState.searchQuery;
	const trackKey = myCloudEmailState.activeAccount + '|' + folderId;

    const fd = new URLSearchParams({ 
        myCloud_action: 'email_get_messages', 
        myCloud_key: myCloudState.key, 
        myCloud_token: window.myCloudCsrfToken, 
        account_id: myCloudEmailState.activeAccount, 
        folder: folderId,
        page: myCloudEmailState.currentPage,
        search_query: myCloudEmailState.searchQuery || '',
        search_scope: myCloudEmailState.searchScope || 'folder',
        force_sync: (isInstantLoad && !rebuildCache) ? '0' : '1',
        folder_state_hash: myCloudEmailState.folderHashes[trackKey] || '',
        rebuild_cache: rebuildCache ? '1' : '0'
    });

    // --- REFINEMENT 4: Streaming Fetch Parser for Search ---
    if (myCloudEmailState.searchQuery) {
        const emptyEl = listContent.querySelector('.ce-email-empty');
        if (emptyEl) emptyEl.remove();
        listContent.innerHTML = ''; 
		// Clear skeleton
		
		myCloudEmailState.currentMessages = [];
        
        fetch('', { method: 'POST', body: fd, signal: currentSignal }).then(async response => {
            if (list) list.classList.remove('ce-pane-loading');
            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';
            
            while(true) {
                const {done, value} = await reader.read();
                if (done) break;
                buffer += decoder.decode(value, {stream: true});
                let lines = buffer.split("\n");
                buffer = lines.pop(); 
				// Keep partial line in buffer
                
                lines.forEach(line => {
                    if(line.trim()) {
                        try {
                            let msg = JSON.parse(line);
                            const exists = myCloudEmailState.currentMessages.some(m => m.id === msg.id && m.account_id === msg.account_id && m.folder === msg.folder);
                            if (!exists) myCloudEmailState.currentMessages.push(msg);
                        } catch(e) {}
                    }
                });
				window._emailRenderMessageList(false);
            }
            if (myCloudEmailState.currentMessages.length === 0) {
                listContent.innerHTML = '<div class="ce-email-empty">' + (L.no_results || 'No results found.') + '</div>';
            }
        }).catch((err) => { 
            if (err.name === 'AbortError') return;
            if (list) list.classList.remove('ce-pane-loading'); 
        });
        return;
    }

    // --- STANDARD FETCH FOR NORMAL VIEWS ---
    fetch('', { method: 'POST', body: fd, signal: currentSignal }).then(myCloudCheckResponse).then(res => {
        if (res.status === 'RATE_LIMIT') {
            if (list) list.classList.remove('ce-pane-loading');
            return; 
			// Fail silently, block spamming
        }
		if (myCloudState.interface !== 'email') return;
        let incomingMsgs = res.messages || [];
        const isInitialEmptyCache = isInstantLoad && incomingMsgs.length === 0;
        
        // UX FIX: Keep loading state active if cache is empty and network fetch is imminent
        if (list && !isInitialEmptyCache) list.classList.remove('ce-pane-loading');

        
        if (res.status === 'NOT_MODIFIED') {
            return; 
			// Cache is perfectly synced, abort expensive DOM redraw
        }
        if (res.status === 'OK') {
             if (res.folder_state_hash) myCloudEmailState.folderHashes[trackKey] = res.folder_state_hash;
			 // --- Graceful Offline Degradation UI ---
             const offlineBannerId = 'ceEmailOfflineBanner';
             let banner = document.getElementById(offlineBannerId);
             if (res.offline_mode) {
                 // Only show the offline banner if the user manually triggered the fetch.
                 // Background syncs that hit concurrent connection limits should fail silently.
                 if (!banner && !silent) {
                     banner = document.createElement('div');
                     banner.id = offlineBannerId;
                     banner.style.cssText = 'background: #fff3cd; color: #e65100; font-size: 12px; padding: 6px 12px; text-align: center; font-weight: bold; flex-shrink:0; border-bottom: 1px solid #ffe0b2;';
                     banner.innerHTML = L.offline_banner_msg || ' ️ Cannot reach mail server. Showing offline cache.';
                     list.insertBefore(banner, list.firstChild);
                 }
             } else if (banner) {
                 banner.remove();
             }

            if (res.offline_mode && incomingMsgs.length === 0 && myCloudEmailState.currentMessages.length > 0) {
                incomingMsgs = myCloudEmailState.currentMessages;
            }

            if (myCloudEmailState.pendingDeletes && myCloudEmailState.pendingDeletes.size > 0) {
                incomingMsgs = incomingMsgs.filter(m => {
                    const k = (m.account_id || myCloudEmailState.activeAccount) + '|' + (m.folder || myCloudEmailState.activeFolder) + '|' + m.id;
                    return !myCloudEmailState.pendingDeletes.has(k);
                });
            }

            // --- STRICT UID-BASED NOTIFICATION ENGINE ---
            if (incomingMsgs.length > 0 && (folderId.toUpperCase() === 'INBOX' || folderId === 'SMARTBOX')) {
                if (!myCloudEmailState.latestMsgIds) myCloudEmailState.latestMsgIds = {};
                
                let maxUid = 0;
                incomingMsgs.forEach(m => {
                    const uid = parseInt(m.id);
                    if (uid > maxUid) maxUid = uid;
                });
                
                const trackKey = myCloudEmailState.activeAccount + '_' + folderId;
                const knownMax = myCloudEmailState.latestMsgIds[trackKey];
                
                if (knownMax !== undefined && maxUid > knownMax) {
                    if (window.Notification && Notification.permission === "granted") {
                        const newMsgs = incomingMsgs.filter(m => parseInt(m.id) > knownMax);
                        let nTitle = (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.new_email ? myCloud_LANG.new_email : 'New Email');
                        let nBody = (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.new_msg_body ? myCloud_LANG.new_msg_body : 'You have new messages.');
                        
                        if (newMsgs.length === 1) {
                            nTitle = newMsgs[0].fromName || newMsgs[0].fromEmail || nTitle;
                            nBody = newMsgs[0].subject || nBody;
                        } else if (newMsgs.length > 1) {
                            nTitle = newMsgs.length + ' ' + (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.new_messages ? myCloud_LANG.new_messages : 'New Messages');
                            const senders = [...new Set(newMsgs.map(m => m.fromName || m.fromEmail))];
                            nBody = senders.slice(0, 3).join(', ') + (senders.length > 3 ? '...' : '');
                        }
                        new Notification(nTitle, { body: nBody });
                    }
                }
                if (knownMax === undefined || maxUid > knownMax) {
                    myCloudEmailState.latestMsgIds[trackKey] = maxUid;
                }
            }

            if (loadMore) {
                const existingIds = new Set(myCloudEmailState.currentMessages.map(m => String(m.id)));
                const newMsgs = incomingMsgs.filter(m => !existingIds.has(String(m.id)));
                myCloudEmailState.currentMessages = myCloudEmailState.currentMessages.concat(newMsgs);
            } else {
                myCloudEmailState.currentMessages = incomingMsgs;
            }
            myCloudEmailState.hasMore = res.has_more;
            
            if (!document.getElementById('ceEmailListContent')) {
                const L = typeof myCloud_LANG !== 'undefined' ? myCloud_LANG : {};
                list.innerHTML = 
                    '<div style="padding: 8px 10px; border-bottom: 1px solid var(--border-default); background: var(--gray-05); display: flex; gap: 8px; flex-direction: column; flex-shrink:0;">' +
                        '<div style="display:flex; gap:6px;">' +
                            '<input type="text" id="ceEmailSearchInput" placeholder="' + (L.search || 'Search...') + '" class="myCloudInlineInput" style="flex:1; margin:0; height:30px; border-radius:4px; padding:0 8px;">' +
                            '<select id="ceEmailSearchScope" class="myCloudInlineInput" style="width:auto; margin:0; height:30px; border-radius:4px; padding:0 4px; display:' + (myCloudEmailState.activeAccount === 'smartbox' ? 'none' : 'inline-block') + ';">' +
                                '<option value="folder">' + (L.current_folder || 'Current Folder') + '</option>' +
                                '<option value="all">' + (L.whole_mailbox || 'Whole Mailbox') + '</option>' +
                            '</select>' +
                            '<button id="ceEmailSearchClearBtn" class="owa-btn" style="height:30px; padding:0 6px; min-width:30px; display:' + (myCloudEmailState.searchQuery ? 'inline-flex' : 'none') + ';" title="' + (L.clear_search || 'Clear') + '"><svg viewBox="0 0 24 24" style="width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg></button>' +
                            '<button id="ceEmailSearchBtn" class="owa-btn owa-primary" style="height:30px; padding:0 10px; min-width:30px;" title="' + (L.search || 'Search') + '"><svg viewBox="0 0 24 24" style="width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:2;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg></button>' +
                            '<button id="ceEmailSearchHelpBtn" class="owa-btn" style="height:30px; width:30px; min-width:30px; border-radius:50%; padding:0; font-weight:bold; color:var(--text-secondary);" title="' + (L.help_btn || 'Help') + '">?</button>' +
                        '</div>' +
                        '<div style="display:flex; gap:10px; align-items:center;">' +
                       
                        '<select id="ceEmailFilter" onmousedown="event.stopPropagation()" style="margin:0; padding-block:4px; padding-inline:8px; flex:1; font-size:13px; height:30px; border:1px solid var(--border-default); border-radius:4px; background:var(--gray-00); color:var(--text-primary); cursor:pointer;">' +
                            '<option value="all">' + (L.all || 'All') + '</option>' +
                            '<option value="unread">' + (L.unread || 'Unread') + '</option>' +
                            '<option value="threads">' + (L.thread_view || 'Conversations') + '</option>' +
                        '</select>' +
                        '<select id="ceEmailSort" onmousedown="event.stopPropagation()" style="margin:0; padding-block:4px; padding-inline:8px; flex:1.5; font-size:13px; height:30px; border:1px solid var(--border-default); border-radius:4px; background:var(--gray-00); color:var(--text-primary); cursor:pointer;">' +
                            '<option value="unread_desc">' + (L.sort_unread_first || 'Unread First') + '</option>' +
                            '<option value="desc">' + (L.sort_newest || 'Newest') + '</option>' +
                            '<option value="asc">' + (L.sort_oldest || 'Oldest') + '</option>' +
                            '<option value="sender">' + (L.sort_sender || 'Sender (A-Z)') + '</option>' +
                        '</select>' +
                    '</div>' + '</div>' +
                    '<div id="ceEmailListContent" tabindex="0" style="flex:1; overflow-y:auto; padding-block-end: 20px; outline:none;"></div>';
                
                const devKey = typeof myCloudGetCurrentDeviceKey === 'function' ? myCloudGetCurrentDeviceKey() : 'desktop';
                if (!myCloudState.settings) myCloudState.settings = {};
                if (!myCloudState.settings[devKey]) myCloudState.settings[devKey] = {};
                const s = myCloudState.settings[devKey];

                if (myCloudEmailState.activeAccount === 'smartbox') {
                    myCloudEmailState.listFilter = (typeof s.emailListFilterSmartbox !== 'undefined') ? s.emailListFilterSmartbox : 'threads';
                } else {
                    myCloudEmailState.listFilter = (typeof s.emailListFilter !== 'undefined') ? s.emailListFilter : 'all';
                }
                myCloudEmailState.threadView = (myCloudEmailState.listFilter === 'threads');

                if (myCloudEmailState.threadView && myCloudEmailState.listFilter !== 'unread') myCloudEmailState.listFilter = 'threads';
				
				const filterEl = document.getElementById('ceEmailFilter');
                const sortEl = document.getElementById('ceEmailSort');
                filterEl.value = myCloudEmailState.listFilter;
                sortEl.value = myCloudEmailState.listSort;
                filterEl.onchange = (e) => { 
                    myCloudEmailState.listFilter = e.target.value; 
                    myCloudEmailState.threadView = (e.target.value === 'threads');
                    document.cookie = "myCloud_emailThreadView=" + (myCloudEmailState.threadView ? "1" : "0") + "; path=/; max-age=31536000; SameSite=Lax";
                    
                    if (myCloudEmailState.activeAccount === 'smartbox') {
                        myCloudState.settings[devKey].emailListFilterSmartbox = e.target.value;
                    } else {
                        myCloudState.settings[devKey].emailListFilter = e.target.value;
                    }
                    if (typeof myCloudSaveSettings === 'function') myCloudSaveSettings();
                    
                    window._emailRenderMessageList(); 
                };
                sortEl.onchange = (e) => { 
                    myCloudEmailState.listSort = e.target.value; 
                    if (myCloudState.settings) {
                        myCloudState.settings.emailListSort = e.target.value;
                        if (typeof myCloudSaveSettings === 'function') myCloudSaveSettings();
                    }
                    window._emailRenderMessageList(); 
                };

                const searchInput = document.getElementById('ceEmailSearchInput');
                const searchScope = document.getElementById('ceEmailSearchScope');
                const searchBtn = document.getElementById('ceEmailSearchBtn');
                const searchClearBtn = document.getElementById('ceEmailSearchClearBtn');
				const searchHelpBtn = document.getElementById('ceEmailSearchHelpBtn');

                if (searchInput) {
                     
                    searchInput.addEventListener('input', () => {
                        if (searchClearBtn) searchClearBtn.style.display = searchInput.value.length > 0 ? 'inline-flex' : 'none';
                    });

                    if (searchClearBtn) {
                        searchClearBtn.onclick = (e) => {
                            e.preventDefault();
                            searchInput.value = '';
                            myCloudEmailState.searchQuery = '';
                            myCloudEmailState.currentPage = 1;
                            myCloudEmailFetchMessages(myCloudEmailState.activeFolder, false);
                        };
                    }

                    searchBtn.onclick = (e) => {
                        e.preventDefault();
                        myCloudEmailState.searchQuery = searchInput.value.trim();
                        if (searchScope) myCloudEmailState.searchScope = searchScope.value;
                        myCloudEmailState.currentPage = 1;
                        myCloudEmailFetchMessages(myCloudEmailState.activeFolder, false);
                    };
                    searchInput.onkeydown = (e) => { if (e.key === 'Enter') { e.preventDefault(); searchBtn.click(); } };

                    if (searchHelpBtn) {
                        searchHelpBtn.onclick = (e) => {
                            e.preventDefault();
                            const title = L.search_help_title || 'Advanced Search';
                            const msg = (L.search_help_msg || 'You can use the following operators to refine your search:') + '<br><br>' +
                                        '<ul style="text-align:left; font-family:monospace; font-size:13px; display:inline-block; margin:0; background:var(--gray-05); border:1px solid var(--border-default); border-radius:4px; padding:15px 15px 15px 30px;">' +
                                        '<li>from:name@domain.com</li>' +
                                        '<li>to:name</li>' +
                                        '<li>subject:invoice</li>' +
                                        '<li>has:attachment</li>' +
                                        '<li>is:unread</li>' +
                                        '<li>is:flagged</li>' +
                                        '</ul>';
                            if (typeof myCloudShowAlert === 'function') myCloudShowAlert(title, msg);
                        };
                    }

                }

                const listContent = document.getElementById('ceEmailListContent');
                listContent.addEventListener('keydown', function(e) {
                    if (e.key === 'ArrowDown' || e.key === 'ArrowUp' || e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
                        e.preventDefault();
                        const items = Array.from(this.querySelectorAll('.ce-email-list-item:not(.ce-email-removing)')).filter(el => el.style.display !== 'none');
                        if (items.length === 0) return;
                        
                        let currentIndex = items.findIndex(el => el.classList.contains('selected'));
                        
                        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                            if (currentIndex === -1) currentIndex = 0;
                            else {
                                if (e.key === 'ArrowDown') currentIndex++;
                                if (e.key === 'ArrowUp') currentIndex--;
                            }
                            
                            if (currentIndex < 0) currentIndex = 0;
                            if (currentIndex >= items.length) currentIndex = items.length - 1;
                            
                            const targetItem = items[currentIndex];
                            if (targetItem) {
                                const msgKey = targetItem.dataset.msgKey;
                                const m = myCloudEmailState.currentMessages.find(msg => ((msg.account_id || myCloudEmailState.activeAccount) + '|' + (msg.folder || myCloudEmailState.activeFolder) + '|' + msg.id) === msgKey);
                                if (m) {
                                    window._emailHandleItemClick(targetItem, m, { isKeyboard: true });
                                    targetItem.scrollIntoView({ block: 'nearest' });
                                }
                            }
                        } else if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
                            if (currentIndex === -1) return;
                            const currentItem = items[currentIndex];
                            const msgKey = currentItem.dataset.msgKey;
                            const m = myCloudEmailState.currentMessages.find(msg => ((msg.account_id || myCloudEmailState.activeAccount) + '|' + (msg.folder || myCloudEmailState.activeFolder) + '|' + msg.id) === msgKey);
                            
                            if (!m) return;

                            if (e.key === 'ArrowRight') {
                                if (m.is_thread_parent) {
                                    if (!myCloudEmailState.expandedThreads) myCloudEmailState.expandedThreads = new Set();
                                    if (!myCloudEmailState.expandedThreads.has(m.thread_id_stable)) {
                                        myCloudEmailState.expandedThreads.add(m.thread_id_stable);
                                        window._emailRenderMessageList();
                                    }
                                }
                            } else if (e.key === 'ArrowLeft') {
                                if (m.is_thread_parent) {
                                    if (myCloudEmailState.expandedThreads && myCloudEmailState.expandedThreads.has(m.thread_id_stable)) {
                                        myCloudEmailState.expandedThreads.delete(m.thread_id_stable);
                                        window._emailRenderMessageList();
                                    }
                                } else if (currentItem.classList.contains('ce-email-thread-child')) {
                                    const parentId = currentItem.dataset.threadParent;
                                    const allNodes = Array.from(this.querySelectorAll('.ce-email-list-item'));
                                    const parentEl = allNodes.find(el => {
                                        const pMsg = myCloudEmailState.currentMessages.find(msg => ((msg.account_id || myCloudEmailState.activeAccount) + '|' + (msg.folder || myCloudEmailState.activeFolder) + '|' + msg.id) === el.dataset.msgKey);
                                        return pMsg && pMsg.thread_id_stable === parentId && pMsg.is_thread_parent;
                                    });
                                    
                                    if (parentEl) {
                                        const pMsgKey = parentEl.dataset.msgKey;
                                        const pM = myCloudEmailState.currentMessages.find(msg => ((msg.account_id || myCloudEmailState.activeAccount) + '|' + (msg.folder || myCloudEmailState.activeFolder) + '|' + msg.id) === pMsgKey);
                                        if (pM) {
                                            window._emailHandleItemClick(parentEl, pM, { isKeyboard: true });
                                            parentEl.scrollIntoView({ block: 'nearest' });
                                        }
                                    }
                                }
                            }
                        }
                    } else if (e.key === 'Delete' || e.key === 'Backspace') {
                        const selectedItem = this.querySelector('.ce-email-list-item.selected');
                        if (selectedItem) {
                            e.preventDefault();
                           const delBtn = selectedItem.querySelector('.ce-email-list-del-btn');
                            if (delBtn) {
                                e.stopPropagation();
                                delBtn.click();
                            }
                        }
                    }
                });
          }
            
            // DOM Sync: Triggers on EVERY folder fetch to ensure UI matches state perfectly
            if (isFolderSwitch) {
                const devKey = typeof myCloudGetCurrentDeviceKey === 'function' ? myCloudGetCurrentDeviceKey() : 'desktop';
                const s = (myCloudState.settings && myCloudState.settings[devKey]) ? myCloudState.settings[devKey] : {};
                if (myCloudEmailState.activeAccount === 'smartbox') {
                    myCloudEmailState.listFilter = (typeof s.emailListFilterSmartbox !== 'undefined') ? s.emailListFilterSmartbox : 'threads';
                } else {
                    myCloudEmailState.listFilter = (typeof s.emailListFilter !== 'undefined') ? s.emailListFilter : 'all';
                }
                myCloudEmailState.threadView = (myCloudEmailState.listFilter === 'threads');
            }

            const liveFilterEl = document.getElementById('ceEmailFilter');
            if (liveFilterEl) {
                liveFilterEl.value = myCloudEmailState.listFilter;
            }

            const liveSearchScope = document.getElementById('ceEmailSearchScope');
            if (liveSearchScope) {
                liveSearchScope.style.display = (myCloudEmailState.activeAccount === 'smartbox') ? 'none' : 'inline-block';
                liveSearchScope.value = myCloudEmailState.searchScope || 'folder';
            }
            const liveSearchInput = document.getElementById('ceEmailSearchInput');
            if (liveSearchInput) {
                liveSearchInput.value = myCloudEmailState.searchQuery || '';
            }
            const liveSearchClearBtn = document.getElementById('ceEmailSearchClearBtn');
            if (liveSearchClearBtn) {
                liveSearchClearBtn.style.display = (myCloudEmailState.searchQuery || (liveSearchInput && liveSearchInput.value.length > 0)) ? 'inline-flex' : 'none';
            }

            // Render inside an animation frame for smoothness
            requestAnimationFrame(() => {
                window._emailRenderMessageList();

                // --- INSTANT BACKGROUND REFRESH (PARALLELIZED) ---
                if (isInstantLoad) {
                    if (list) list.classList.add('ce-pane-refreshing');
                    
                    let syncTargets = [];
                    if (myCloudEmailState.activeAccount === 'smartbox') {
                        syncTargets = Object.keys(myCloudEmailState.accounts).filter(k => !myCloudEmailState.accounts[k].is_inactive);
                    } else {
                        syncTargets = [myCloudEmailState.activeAccount];
                    }

                    // Fan out concurrent requests for true parallel processing
                    const syncPromises = syncTargets.map(targetAcc => {
                        const syncFd = new URLSearchParams(fd);
                        syncFd.set('force_sync', '1');
                        syncFd.set('account_id', targetAcc);
						syncFd.set('rebuild_cache', rebuildCache ? '1' : '0');
                        
                        const targetFld = (myCloudEmailState.activeAccount === 'smartbox') ? 'INBOX' : folderId;
                        syncFd.set('folder', targetFld);
                        
                        const tk = targetAcc + '|' + targetFld;
                        syncFd.set('folder_state_hash', myCloudEmailState.folderHashes[tk] || '');
                        
                        return fetch('', { method: 'POST', body: syncFd, signal: currentSignal })
                            .then(r => r.json())
                            .then(res => {
                                if (res.status === 'OK' && res.folder_state_hash) {
                                    myCloudEmailState.folderHashes[tk] = res.folder_state_hash;
                                }
                                return res;
                            })
                            .catch(err => ({ status: 'ERR', error: err }));
                    });

                    Promise.all(syncPromises).then(results => {
                        if (list) list.classList.remove('ce-pane-refreshing');
                        
                        // If it's a single account, process normally
                        if (myCloudEmailState.activeAccount !== 'smartbox') {
                            const syncRes = results[0];
                            if (syncRes && syncRes.status === 'NOT_MODIFIED') {
                                if (list) list.classList.remove('ce-pane-loading');
                                if (isInitialEmptyCache) window._emailRenderMessageList(); 
                                return;
                            }
                            if (syncRes && syncRes.status === 'OK' && myCloudEmailState.activeFolder === folderId && syncRes.status !== 'RATE_LIMIT') {
                                if (list) list.classList.remove('ce-pane-loading');
                                let syncMsgs = syncRes.messages || [];

                                const hashParts = (syncRes.folder_state_hash || '').split('-');
                                const serverMsgCount = hashParts.length >= 3 ? parseInt(hashParts[2], 10) : -1;

                                if (syncMsgs.length === 0 && myCloudEmailState.currentMessages.length > 0) {
                                    if (syncRes.offline_mode || serverMsgCount > 0) {
                                        syncMsgs = myCloudEmailState.currentMessages;
                                    }
                                }

                                if (myCloudEmailState.pendingDeletes && myCloudEmailState.pendingDeletes.size > 0) {
                                    syncMsgs = syncMsgs.filter(m => {
                                        const k = (m.account_id || myCloudEmailState.activeAccount) + '|' + (m.folder || myCloudEmailState.activeFolder) + '|' + m.id;
                                        return !myCloudEmailState.pendingDeletes.has(k);
                                    });
                                }
                                myCloudEmailState.currentMessages = syncMsgs;
                                myCloudEmailState.hasMore = syncRes.has_more;
                                myCloudEmailRenderTree();
                                window._emailRenderMessageList();
                            }
                            return;
                        }

                        // Smartbox Parallel Merge Strategy
                        let needsRedraw = false;
                        results.forEach(syncRes => {
                            if (syncRes && syncRes.status === 'OK' && syncRes.status !== 'NOT_MODIFIED') {
                                needsRedraw = true;
                            }
                        });
                        
                        if (needsRedraw || isInitialEmptyCache) {
                            // Fire a rapid localized cache-read to merge the freshly synced disk caches
                            const cacheFd = new URLSearchParams(fd);
                            cacheFd.set('force_sync', '0'); 
                            fetch('', { method: 'POST', body: cacheFd, signal: currentSignal })
                                .then(r => r.json())
                                .then(cacheRes => {
                                    if (list) list.classList.remove('ce-pane-loading');
                                    if (cacheRes.status === 'OK' && myCloudEmailState.activeFolder === folderId) {
                                        let syncMsgs = cacheRes.messages || [];
                                        if (myCloudEmailState.pendingDeletes && myCloudEmailState.pendingDeletes.size > 0) {
                                            syncMsgs = syncMsgs.filter(m => {
                                                const k = (m.account_id || myCloudEmailState.activeAccount) + '|' + (m.folder || myCloudEmailState.activeFolder) + '|' + m.id;
                                                return !myCloudEmailState.pendingDeletes.has(k);
                                            });
                                        }
                                        myCloudEmailState.currentMessages = syncMsgs;
                                        myCloudEmailState.hasMore = cacheRes.has_more;
                                        myCloudEmailRenderTree();
                                        window._emailRenderMessageList();
                                    }
                                }).catch(()=>{
                                    if (list) list.classList.remove('ce-pane-loading');
                                });
                        } else {
                            if (list) list.classList.remove('ce-pane-loading');
                            if (isInitialEmptyCache) window._emailRenderMessageList();
                        }
                    }).catch(e => {
                        if (list) list.classList.remove('ce-pane-refreshing');
                        if (list) list.classList.remove('ce-pane-loading');
                    });
                }
            });
        } else {
            if (!silent) list.innerHTML = '<div class="ce-email-empty" style="color:var(--danger); font-weight:bold;">' + (L.error_prefix || 'Error:') + '<br><br>' + myCloudEscapeHtml(res.msg) + '</div>';
        }
    }).catch((err) => {
        // Gracefully swallow intentional aborts to keep the console clean
        if (err.name === 'AbortError') return;
        if (list) list.classList.remove('ce-pane-loading');
		if (list) list.classList.remove('ce-pane-refreshing');
    });
};


window._emailHandleItemClick = function(item, m, e) {
    const isKeyboard = e && (e.isKeyboard === true);
    const msgKey = (m.account_id || myCloudEmailState.activeAccount) + '|' + (m.folder || myCloudEmailState.activeFolder) + '|' + m.id;
    
    if (m.is_thread_parent && !isKeyboard) {
        if (!myCloudEmailState.expandedThreads) myCloudEmailState.expandedThreads = new Set();
        const tId = m.thread_id_stable;
        if (myCloudEmailState.activeMessageKey === msgKey) {
            if (myCloudEmailState.expandedThreads.has(tId)) {
                myCloudEmailState.expandedThreads.delete(tId);
            } else {
                myCloudEmailState.expandedThreads.add(tId);
            }
            window._emailRenderMessageList();
        } else {
            myCloudEmailState.expandedThreads.add(tId);
        }
    }

    if (!myCloudEmailState.selectedMessages) myCloudEmailState.selectedMessages = [];
    const listItems = Array.from(document.querySelectorAll('#ceEmailListContent .ce-email-list-item'));
    const currentIndex = listItems.indexOf(item);

    // Initialize anchor if it doesn't exist
    if (typeof myCloudEmailState.lastSelectedMsgIndex !== 'number' || myCloudEmailState.lastSelectedMsgIndex < 0) {
        myCloudEmailState.lastSelectedMsgIndex = currentIndex;
    }

    if (e && e.shiftKey) {
        // Prevent text selection highlighting
        if (document.getSelection) document.getSelection().removeAllRanges();
        
        const start = Math.min(myCloudEmailState.lastSelectedMsgIndex, currentIndex);
        const end = Math.max(myCloudEmailState.lastSelectedMsgIndex, currentIndex);
        const rangeKeys = listItems.slice(start, end + 1).map(el => el.dataset.msgKey);
        
        if (e.ctrlKey || e.metaKey) {
            // If Ctrl+Shift are held, add the range to the existing selection
            const newSelection = new Set([...myCloudEmailState.selectedMessages, ...rangeKeys]);
            myCloudEmailState.selectedMessages = Array.from(newSelection);
        } else {
            // Just Shift: Replace selection with the range
            myCloudEmailState.selectedMessages = rangeKeys;
        }
    } else if (e && (e.ctrlKey || e.metaKey)) {
        // Toggle single selection
        if (myCloudEmailState.selectedMessages.includes(msgKey)) {
            myCloudEmailState.selectedMessages = myCloudEmailState.selectedMessages.filter(k => k !== msgKey);
        } else {
            myCloudEmailState.selectedMessages.push(msgKey);
        }
        myCloudEmailState.lastSelectedMsgIndex = currentIndex; // Update anchor
    } else {
        // Single selection
        myCloudEmailState.selectedMessages = [msgKey];
        myCloudEmailState.lastSelectedMsgIndex = currentIndex; // Update anchor
    }

    // Apply visual classes based on the unique msgKey
    listItems.forEach(el => {
        if (myCloudEmailState.selectedMessages.includes(el.dataset.msgKey)) el.classList.add('selected');
        else el.classList.remove('selected');
    });

    if (myCloudEmailState.selectedMessages.length <= 1) {
        
        // --- RAM PROTECTION: PURGE OLD >5MB MESSAGES WHEN CLICKING AWAY ---
        if (myCloudEmailState.activeMessageKey && myCloudEmailState.activeMessageKey !== msgKey) {
            const oldKey = myCloudEmailState.activeMessageKey;
            const oldCache = myCloudEmailState.bodyCache[oldKey];
            if (oldCache && !(oldCache instanceof Promise)) {
                let totalAttSize = 0;
                if (oldCache.attachments && oldCache.attachments.length > 0) {
                    oldCache.attachments.forEach(att => { totalAttSize += parseInt(att.size || 0); });
                }
                if (totalAttSize > 5242880) { // 5MB Limit
                    delete myCloudEmailState.bodyCache[oldKey];
                }
            }
        }
        // ------------------------------------------------------------------

        // FIX: Prevent re-fetching and UI jumping if message is already loaded and active
        const isAlreadyActive = (myCloudEmailState.activeMessageKey === msgKey);

        if (!isAlreadyActive) {
            myCloudEmailState.activeMessageKey = msgKey;
            myCloudEmailState.activeMessageOriginalRead = m.is_read;
            window._emailRenderMessageList(); // Re-render for read status updates if needed
            item = document.querySelector(`.ce-email-list-item[data-msg-key="${CSS.escape(msgKey)}"]`) || item;
        }

        if (item && item.parentElement) {
            Array.from(item.parentElement.children).forEach(c => c.classList.remove('selected'));
            item.classList.add('selected');
        }

        if (!isAlreadyActive) {
            // --- PREDICTIVE UX: 3-MESSAGE DOWNWARD AUTOLOADER ---
            if (!myCloudEmailState.bodyCache) myCloudEmailState.bodyCache = {};
            
            const prefetchBody = (idx) => {
                if (idx < 0 || idx >= listItems.length) return;
                const mKey = listItems[idx].dataset.msgKey;
                const mObj = myCloudEmailState.currentMessages.find(msg => ((msg.account_id || myCloudEmailState.activeAccount) + '|' + (msg.folder || myCloudEmailState.activeFolder) + '|' + msg.id) === mKey);
                
                if (!mObj || myCloudEmailState.bodyCache[mKey]) return;
                
                const fetchFd = new URLSearchParams({ 
                    myCloud_action: 'email_get_body', 
                    myCloud_key: myCloudState.key, 
                    myCloud_token: window.myCloudCsrfToken, 
                    account_id: mObj.account_id || myCloudEmailState.activeAccount, 
                    message_id: mObj.id, 
                    folder: mObj.folder || myCloudEmailState.activeFolder 
                });
                
                myCloudEmailState.bodyCache[mKey] = fetch('', { method: 'POST', body: fetchFd }).then(myCloudCheckResponse).then(r => {
                    if (r.status === 'OK') {
                        // ALWAYS cache on fetch. The >5MB purge happens when we click away.
                        myCloudEmailState.bodyCache[mKey] = r;
                        return r;
                    } else {
                        delete myCloudEmailState.bodyCache[mKey];
                        throw new Error('Failed to load body');
                    }
                }).catch((e) => { 
                    delete myCloudEmailState.bodyCache[mKey];
                });
            };

            // Fire background fetches for the 3 messages directly below the current one
            prefetchBody(currentIndex - 1); // Look UP 1
            prefetchBody(currentIndex + 1); // Look DOWN 1
            prefetchBody(currentIndex + 2); // Look DOWN 2
            prefetchBody(currentIndex + 3); // Look DOWN 3
            // -----------------------------------------------------------------------

            myCloudEmailReadMessage(m.id, m);

            if (myCloudEmailState.readTimer) clearTimeout(myCloudEmailState.readTimer);
            
            if (!m.is_read) {
                const targetAcc = m.account_id || myCloudEmailState.activeAccount;
                const targetFolder = m.folder || myCloudEmailState.activeFolder;

                myCloudEmailState.readTimer = setTimeout(() => {

                    m.is_read = true;
                    let uidsToMark = [m.id];
                    let unreadCountToSubtract = 1;
        
                    const realParentMsg = myCloudEmailState.currentMessages.find(cm => String(cm.id) === String(m.id) && (cm.account_id || myCloudEmailState.activeAccount) === targetAcc && (cm.folder || myCloudEmailState.activeFolder) === targetFolder);
                    if (realParentMsg) realParentMsg.is_read = true;
        
                    if (m.is_thread_parent && m.children) {
                        m.children.forEach(child => {
                            if (!child.is_read) {
                                child.is_read = true;
                                uidsToMark.push(child.id);
                                unreadCountToSubtract++;
                                const realChildMsg = myCloudEmailState.currentMessages.find(cm => String(cm.id) === String(child.id) && (cm.account_id || myCloudEmailState.activeAccount) === targetAcc && (cm.folder || myCloudEmailState.activeFolder) === targetFolder);
                                if (realChildMsg) realChildMsg.is_read = true;
                            }
                        });
                    }

                    const listItem = document.querySelector(`.ce-email-list-item[data-msg-key="${CSS.escape(msgKey)}"]`);
                    if (listItem) {
                        const dot = listItem.querySelector('.ce-email-unread-dot');
                        if (dot) dot.remove();
                        listItem.querySelectorAll('.ce-email-list-sender, .ce-email-list-subject').forEach(el => el.classList.add('read'));
                    }
                    if (myCloudEmailState.foldersData[targetAcc]) {
                        const folderData = myCloudEmailState.foldersData[targetAcc].find(f => f.id === targetFolder);
                        if (folderData && folderData.unread > 0) {
                            folderData.unread = Math.max(0, folderData.unread - unreadCountToSubtract);
                            if (targetFolder.toUpperCase() === 'INBOX') myCloudEmailState.inboxUnreadCounts[targetAcc] = folderData.unread;
                            myCloudEmailRenderTree();
                        }
                    }

                    const fd = new URLSearchParams({ myCloud_action: 'email_mark_read', myCloud_key: myCloudState.key, myCloud_token: window.myCloudCsrfToken, account_id: targetAcc, folder: targetFolder, message_id: uidsToMark.join(',') });
                    fetch('', { method: 'POST', body: fd }).then(r => r.json()).then(res => {
                        if (res.status !== 'OK') {
                            m.is_read = false;
                            if (realParentMsg) realParentMsg.is_read = false;
                            if (m.is_thread_parent && m.children) {
                                m.children.forEach(child => {
                                    if (uidsToMark.includes(child.id)) {
                                        child.is_read = false;
                                        const realChildMsg = myCloudEmailState.currentMessages.find(cm => String(cm.id) === String(child.id) && (cm.account_id || myCloudEmailState.activeAccount) === targetAcc && (cm.folder || myCloudEmailState.activeFolder) === targetFolder);
                                        if (realChildMsg) realChildMsg.is_read = false;
                                    }
                                });
                            }
                            if (myCloudEmailState.foldersData[targetAcc]) {
                                const folderData = myCloudEmailState.foldersData[targetAcc].find(f => f.id === targetFolder);
                                if (folderData) {
                                    folderData.unread += unreadCountToSubtract;
                                    if (targetFolder.toUpperCase() === 'INBOX') myCloudEmailState.inboxUnreadCounts[targetAcc] = folderData.unread;
                                    myCloudEmailRenderTree();
                                }
                            }
                            window._emailRenderMessageList();
                        }

                    }).catch(err => console.error(err));
                }, 5000);
            }
        }
        
        window._emailSetMobileView('reading');

    } else {
        const L = typeof myCloud_LANG !== 'undefined' ? myCloud_LANG : {};
        document.getElementById('emailPaneReading').innerHTML = `<div class="ce-email-empty" style="font-size: 18px; color: var(--text-secondary); text-align: center; padding-top: 100px;">
            <svg viewBox="0 0 24 24" width="48" height="48" style="fill:none; stroke:currentColor; stroke-width:1; margin-bottom:15px; opacity:0.5;">
                <polyline points="9 11 12 14 22 4"></polyline>
                <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
            </svg><br>
            ${myCloudEmailState.selectedMessages.length} ${L.items_selected || 'messages selected'}
        </div>`;
    }
};


window._emailRenderMessageList = function(isAppendOnly = false) {
  if (window._emailListDebounce) cancelAnimationFrame(window._emailListDebounce);
  window._emailListDebounce = requestAnimationFrame(async () => {
    const listContent = document.getElementById('ceEmailListContent');
    if (!listContent) return;

    window._renderMessageListTask = (window._renderMessageListTask || 0) + 1;
    const currentTask = window._renderMessageListTask;

    let msgs = [...myCloudEmailState.currentMessages];
    
    if (myCloudEmailState.listFilter === 'unread') {
        msgs = msgs.filter(m => {
            const mKey = (m.account_id || myCloudEmailState.activeAccount) + '|' + (m.folder || myCloudEmailState.activeFolder) + '|' + m.id;
            if (mKey === myCloudEmailState.activeMessageKey && typeof myCloudEmailState.activeMessageOriginalRead !== 'undefined') {
                return !myCloudEmailState.activeMessageOriginalRead; // Keep it in the unread filter if it was unread when opened
            }
            return !m.is_read;
        });
    }
    
    msgs.sort((a, b) => {
        if (myCloudEmailState.listSort === 'unread_desc') {
            const aKey = (a.account_id || myCloudEmailState.activeAccount) + '|' + (a.folder || myCloudEmailState.activeFolder) + '|' + a.id;
            const bKey = (b.account_id || myCloudEmailState.activeAccount) + '|' + (b.folder || myCloudEmailState.activeFolder) + '|' + b.id;
            
            let aRead = (aKey === myCloudEmailState.activeMessageKey && typeof myCloudEmailState.activeMessageOriginalRead !== 'undefined') ? myCloudEmailState.activeMessageOriginalRead : a.is_read;
            let bRead = (bKey === myCloudEmailState.activeMessageKey && typeof myCloudEmailState.activeMessageOriginalRead !== 'undefined') ? myCloudEmailState.activeMessageOriginalRead : b.is_read;

            if (aRead !== bRead) return aRead ? 1 : -1;
           return b.ts - a.ts;
        } else if (myCloudEmailState.listSort === 'desc') {
            return b.ts - a.ts;
        } else if (myCloudEmailState.listSort === 'asc') {
            return a.ts - b.ts;
        } else if (myCloudEmailState.listSort === 'sender') {
            return a.fromName.localeCompare(b.fromName);
        }
        return 0;
    });
    
    // --- THREAD GROUPING ENGINE (Feature #2) ---
    let renderMsgs = [];
    if (myCloudEmailState.threadView) {
        const threads = {};
        msgs.forEach(m => {
            let normSubj = (m.subject || '').toLowerCase();
            let prev;
            do {
                prev = normSubj;
                normSubj = normSubj.replace(/^(re|fwd|aw|fw|wg|antwort|sv|tr)\s*\[?\d*\]?:\s*/i, '').trim();
            } while (normSubj !== prev);
            
            const threadKey = normSubj || String(m.id);

            if (!threads[threadKey]) threads[threadKey] = [];
            threads[threadKey].push(m);
        });

        Object.keys(threads).forEach(tKey => {
            const th = threads[tKey];
            if (th.length === 1) {
                renderMsgs.push(th[0]);
            } else {
                th.sort((a, b) => b.ts - a.ts); // Newest top
                let hash = 0;
                for(let i = 0; i < tKey.length; i++) hash = ((hash << 5) - hash) + tKey.charCodeAt(i);
                const stableId = 'th_' + (hash >>> 0).toString(16);
                const parent = { ...th[0], is_thread_parent: true, thread_id_stable: stableId, thread_count: th.length, children: th.slice(1) };

                // A thread is unread if ANY of its messages are unread (accounting for active message logic)
                parent.is_read = th.every(m => {
                    const mKey = (m.account_id || myCloudEmailState.activeAccount) + '|' + (m.folder || myCloudEmailState.activeFolder) + '|' + m.id;
                    return (mKey === myCloudEmailState.activeMessageKey && typeof myCloudEmailState.activeMessageOriginalRead !== 'undefined') ? myCloudEmailState.activeMessageOriginalRead : m.is_read;
                });
                renderMsgs.push(parent);
            }
        });
        // Maintain chosen sort order for threads
        renderMsgs.sort((a,b) => {
            if (myCloudEmailState.listSort === 'unread_desc') {
                let aRead = a.is_thread_parent ? a.is_read : ((a.account_id || myCloudEmailState.activeAccount) + '|' + (a.folder || myCloudEmailState.activeFolder) + '|' + a.id === myCloudEmailState.activeMessageKey && typeof myCloudEmailState.activeMessageOriginalRead !== 'undefined' ? myCloudEmailState.activeMessageOriginalRead : a.is_read);
                let bRead = b.is_thread_parent ? b.is_read : ((b.account_id || myCloudEmailState.activeAccount) + '|' + (b.folder || myCloudEmailState.activeFolder) + '|' + b.id === myCloudEmailState.activeMessageKey && typeof myCloudEmailState.activeMessageOriginalRead !== 'undefined' ? myCloudEmailState.activeMessageOriginalRead : b.is_read);
                if (aRead !== bRead) return aRead ? 1 : -1;
                return b.ts - a.ts;
            } else if (myCloudEmailState.listSort === 'sender') {
                return a.fromName.localeCompare(b.fromName);
            } else if (myCloudEmailState.listSort === 'asc') {
                return a.ts - b.ts;
            } else {
                return b.ts - a.ts;
            }
        });
    } else {
        renderMsgs = msgs;
    }

		window.myCloudEmailState.renderedMessages = renderMsgs;
		
		
        if (renderMsgs.length === 0) {
            const listPane = document.getElementById('emailPaneList');
            if (listPane && listPane.classList.contains('ce-pane-loading')) {
                listContent.innerHTML = Array(8).fill(`
                    <div class="ce-email-list-item" style="pointer-events:none; border-block-end:1px solid var(--border-subtle);">
                        <div style="display:flex; justify-content:space-between; margin-block-end:6px;">
                            <div class="ce-skeleton" style="width:40%; height:12px;"></div>
                            <div class="ce-skeleton" style="width:15%; height:10px;"></div>
                        </div>
                        <div class="ce-skeleton" style="width:80%; height:14px; margin-block-end:6px;"></div>
                        <div class="ce-skeleton" style="width:60%; height:12px;"></div>
                    </div>
                `).join('');
                return;
            }
		    const L = typeof myCloud_LANG !== 'undefined' ? myCloud_LANG : {};
            listContent.innerHTML = '<div class="ce-email-empty">' + (L.empty || 'Empty') + '</div>';
            return; 
        }

        const emptyEl = listContent.querySelector('.ce-email-empty');
        if (emptyEl) emptyEl.remove();
        const loadMoreEl = document.getElementById('ceEmailLoadMoreBtn');
        if (loadMoreEl) loadMoreEl.remove();

        listContent.querySelectorAll('.ce-email-list-item:not([data-msg-key])').forEach(el => el.remove());

    const existingNodes = new Map();
    Array.from(listContent.children).forEach(child => {
        if (child.dataset.msgKey) existingNodes.set(child.dataset.msgKey, child);
    });

    let currentDomNode = listContent.firstElementChild;
	// --- ITEM INJECTION ENGINE ---
    window._emailToggleThread = function(threadId) {
        if (!myCloudEmailState.expandedThreads) myCloudEmailState.expandedThreads = new Set();
        if (myCloudEmailState.expandedThreads.has(threadId)) {
            myCloudEmailState.expandedThreads.delete(threadId);
        } else {
            myCloudEmailState.expandedThreads.add(threadId);
        }
        const children = document.querySelectorAll(`.ce-email-thread-child[data-thread-parent="${threadId}"]`);
        children.forEach(c => { c.style.display = myCloudEmailState.expandedThreads.has(threadId) ? 'block' : 'none'; });
    };

    const appendMsgToDom = (m, isChild = false, parentId = null) => {
        const msgKey = (m.account_id || myCloudEmailState.activeAccount) + '|' + (m.folder || myCloudEmailState.activeFolder) + '|' + m.id;
        const readClass = m.is_read ? 'read' : '';
        const dotHtml = m.is_read ? '' : '<div class="ce-email-unread-dot"></div>';
        const metaSafe = encodeURIComponent(JSON.stringify(m)).replace(/'/g, "%27");
        
        const delIcon = '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>';
        const delBtnHtml = '<div class="ce-email-list-actions"><button class="ce-email-list-del-btn" title="' + (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.delete ? myCloud_LANG.delete : 'Delete') + '" onclick="event.stopPropagation(); window.myCloudEmailAction(\'delete\', \'' + m.id + '\', \'' + metaSafe + '\')">' + delIcon + '</button></div>';

        const flagIconFilled = '<svg viewBox="0 0 24 24" width="16" height="16"><path d="M5 4h2v17H5z" fill="var(--gray-70)"/><path d="M14.4 6L14 4H7v10h5.6l.4 2h7V6z" fill="#e81123"/></svg>';
        const flagIconEmpty = '<svg viewBox="0 0 24 24" width="16" height="16"><path d="M5 4h2v17H5z" fill="var(--gray-50)"/><path d="M14 6l-.4-2H7v10h5.6l.4 2h7V6h-6zm4 8h-4.36l-.4-2H7V6h5.36l.4 2H18v6z" fill="var(--gray-50)"/></svg>';
        
        const flagHtml = `<button class="ce-email-star-btn" style="position:absolute; bottom:8px; inset-inline-end: 42px; background:transparent; border:none; padding:4px; margin:0; display:flex; align-items:center; cursor:pointer; flex-shrink:0; z-index:5;" onclick="event.stopPropagation(); window._emailToggleFlag('${m.id}', ${!m.is_flagged}, '${m.account_id || ''}', '${m.folder || ''}')">${m.is_flagged ? flagIconFilled : flagIconEmpty}</button>`;
        const attachIcon = m.has_attachments ? '<span class="ce-email-attachment-indicator" style="inset-inline-end: 72px; transform:none !important;" title="Has Attachments"><svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M16.5 6v11.5c0 2.21-1.79 4-4 4s-4-1.79-4-4V5a2.5 2.5 0 0 1 5 0v10.5c0 .55-.45 1-1 1s-1-.45-1-1V6H10v9.5a2.5 2.5 0 0 0 5 0V5c0-2.21-1.79-4-4-4S6 2.79 6 5v11.5c0 3.87 3.13 7 7 7s7-3.13 7-7V6h-1.5z"/></svg></span>' : '';
        
        let threadBadge = '';
        if (m.is_thread_parent) {
            const isExpanded = myCloudEmailState.expandedThreads && myCloudEmailState.expandedThreads.has(m.thread_id_stable);
            const arrowStr = isExpanded ? '▾' : '▸';
            threadBadge = `<span class="ce-email-thread-badge" style="background:var(--gray-15); color:var(--text-secondary); border:1px solid var(--border-medium); padding:1px 6px; border-radius:12px; font-size:10px; font-weight:700; margin-inline-start:6px; cursor:pointer;" onclick="event.stopPropagation(); window._emailToggleThread('${m.thread_id_stable}')">${m.thread_count} ${arrowStr}</span>`;
        }

        let accBadge = '';
        let fldBadge = '';
        if (myCloudEmailState.activeAccount === 'smartbox') {
            if (m.account_name) {
                const accColor = window._emailGetAccColor(m.account_id || '');
                accBadge = `<div class="ce-email-acc-ribbon" style="background-color: ${accColor};">${myCloudEscapeHtml(m.account_name)}</div>`;
            }
            if (m.folder) {
                let cleanFld = m.folder.replace(/^INBOX[./]/i, '');
                if (cleanFld.toUpperCase() === 'INBOX' || cleanFld === '') cleanFld = 'Inbox';

                let fldColor = '';
                const fLower = cleanFld.toLowerCase();
                
                // Semantic color coding distinct from the account colors
                if (fLower === 'inbox') fldColor = '#5c6bc0'; // Indigo
                else if (fLower.includes('sent') || fLower.includes('gesendet') || fLower.includes('enviados') || fLower.includes('envoy')) fldColor = '#43a047'; // Green
                else if (fLower.includes('trash') || fLower.includes('bin') || fLower.includes('deleted') || fLower.includes('papierkorb')) fldColor = '#e53935'; // Red
                else if (fLower.includes('draft') || fLower.includes('entw')) fldColor = '#fb8c00'; // Orange
                else if (fLower.includes('spam') || fLower.includes('junk')) fldColor = '#8d6e63'; // Brown
                else {
                    if (!window._emailFolderColors) window._emailFolderColors = {};
                    fldColor = window._emailFolderColors[fLower];
                    if (!fldColor) {
                        const fldPalette = ['#00acc1', '#00897b', '#3949ab', '#8e24aa', '#d81b60', '#f4511e', '#7cb342'];
                        let hash = 0;
                        for(let i=0; i<fLower.length; i++) hash = fLower.charCodeAt(i) + ((hash << 5) - hash);
                        fldColor = fldPalette[Math.abs(hash) % fldPalette.length];
                        window._emailFolderColors[fLower] = fldColor;
                    }
                }

                fldBadge = `<div class="ce-email-acc-ribbon" style="background-color: ${fldColor}; inset-inline-end:auto; inset-inline-start:16px; border-end-start-radius:0; border-end-end-radius:4px;">${myCloudEscapeHtml(cleanFld)}</div>`;
            }
        }

        let item = existingNodes.get(msgKey);

        const isSentFld = /sent|gesendet|enviados|envoy/i.test(m.folder || myCloudEmailState.activeFolder);
        const displayEntity = isSentFld ? (m.to || m.bcc || m.fromName || m.fromEmail) : (m.fromName || m.fromEmail)

        const innerContent = accBadge + fldBadge + dotHtml +
            '<div class="ce-email-list-sender ' + readClass + '">' +
                '<div style="display:flex; align-items:center; gap:8px; overflow:hidden;">' +
                    '<span style="overflow:hidden; text-overflow:ellipsis;">' + myCloudEscapeHtml(displayEntity) + '</span>' + 
                    threadBadge + 
                '</div>' +
                '<span class="ce-email-list-date" style="margin:0;">' + m.date + '</span>' +
            '</div>' +
            '<div class="ce-email-list-subject ' + readClass + '" style="padding-inline-end: 90px !important;">' + myCloudEscapeHtml(m.subject) + '</div>' +
            flagHtml + attachIcon + delBtnHtml;

        const swipeIconRead = m.is_read ? '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><path d="M22 6l-10 7L2 6"></path></svg>' : '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>';
        const swipeIconDel = '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>';

        const newInner = '<div class="ce-email-swipe-bg" style="position:absolute; inset:0; display:flex; justify-content:space-between; align-items:center; padding:0 20px; z-index:0; transition: background-color 0.2s;">' +
                    '<div class="ce-swipe-left-icon" style="color:#fff; opacity:0; transform:scale(0.5); transition:all 0.2s;">' + swipeIconDel + '</div>' +
                    '<div class="ce-swipe-right-icon" style="color:#fff; opacity:0; transform:scale(0.5); transition:all 0.2s;">' + swipeIconRead + '</div>' +
                '</div>' +
                '<div class="ce-email-swipe-front" style="position:relative; z-index:1; background:inherit; width:100%; height:100%; display:flex; flex-direction:column; justify-content:center; padding: 12px 16px; box-sizing:border-box;">' +
                    innerContent +
                '</div>';

        if (item) {
            if (item.innerHTML !== newInner) item.innerHTML = newInner;
            item.style.position = 'relative';
            item.style.padding = '0';
            item.style.overflow = 'hidden';
            item.onclick = (e) => window._emailHandleItemClick(item, m, e);
            item.oncontextmenu = (e) => {
                if (!myCloudEmailState.selectedMessages) myCloudEmailState.selectedMessages = [];
                if (!myCloudEmailState.selectedMessages.includes(msgKey)) {
                    window._emailHandleItemClick(item, m, { ctrlKey: false, shiftKey: false });
                }
                window.myCloudShowEmailContextMenu(e, 'message', m);
            };
            window._emailBindLongTouch(item, (e) => {
                if (!myCloudEmailState.selectedMessages) myCloudEmailState.selectedMessages = [];
                if (!myCloudEmailState.selectedMessages.includes(msgKey)) {
                    window._emailHandleItemClick(item, m, { ctrlKey: false, shiftKey: false });
                }
                window.myCloudShowEmailContextMenu(e, 'message', m);
            });
            window._emailBindSwipe(item);

            if (myCloudEmailState.selectedMessages && myCloudEmailState.selectedMessages.includes(msgKey)) {
                item.classList.add('selected');
            } else {
                item.classList.remove('selected');
            }
            
            if (isChild) {
                item.classList.add('ce-email-thread-child');
                item.dataset.threadParent = parentId;
                item.style.display = (myCloudEmailState.expandedThreads && myCloudEmailState.expandedThreads.has(parentId)) ? 'block' : 'none';
            } else {
                item.classList.remove('ce-email-thread-child');
                item.removeAttribute('data-thread-parent');
                item.style.display = '';
            }

            if (typeof currentDomNode !== 'undefined' && currentDomNode !== item) {
                listContent.insertBefore(item, currentDomNode);
            } else if (typeof currentDomNode !== 'undefined') {
                currentDomNode = currentDomNode.nextElementSibling;
            } else {
                listContent.appendChild(item);
            }
            
            existingNodes.delete(msgKey);
        } else {
            item = document.createElement('div');
            item.className = 'ce-email-list-item';
            item.dataset.msgId = m.id;
            item.dataset.msgKey = msgKey;
            item.innerHTML = newInner;
            item.style.position = 'relative';
            item.style.padding = '0';
            item.style.overflow = 'hidden';
            
            if (myCloudEmailState.selectedMessages && myCloudEmailState.selectedMessages.includes(msgKey)) {
                item.classList.add('selected');
            }

            if (isChild) {
                item.classList.add('ce-email-thread-child');
                item.dataset.threadParent = parentId;
                item.style.display = (myCloudEmailState.expandedThreads && myCloudEmailState.expandedThreads.has(parentId)) ? 'block' : 'none';
            }

            item.draggable = true;
            item.addEventListener('dragstart', (e) => {
                let targetKeys = [msgKey];
                if (myCloudEmailState.selectedMessages && myCloudEmailState.selectedMessages.includes(msgKey)) {
                    targetKeys = [...myCloudEmailState.selectedMessages];
                }
				e.dataTransfer.setData('text/plain', JSON.stringify({ 
                     type: 'email_msg', 
                     account_id: m.account_id || myCloudEmailState.activeAccount, 
                     folder: m.folder || myCloudEmailState.activeFolder, 
                    message_id: m.id,
                    targetKeys: targetKeys
                 }));
				 if (typeof window.myCloudGetDragImage === 'function') e.dataTransfer.setDragImage(window.myCloudGetDragImage(targetKeys.length), 20, 20);
                e.dataTransfer.effectAllowed = 'copyMove';
            });

            item.onclick = (e) => window._emailHandleItemClick(item, m, e);
            item.oncontextmenu = (e) => {
                if (!myCloudEmailState.selectedMessages) myCloudEmailState.selectedMessages = [];
                if (!myCloudEmailState.selectedMessages.includes(msgKey)) {
                    window._emailHandleItemClick(item, m, { ctrlKey: false, shiftKey: false });
                }
                window.myCloudShowEmailContextMenu(e, 'message', m);
            };
            window._emailBindLongTouch(item, (e) => {
                if (!myCloudEmailState.selectedMessages) myCloudEmailState.selectedMessages = [];
                if (!myCloudEmailState.selectedMessages.includes(msgKey)) {
                    window._emailHandleItemClick(item, m, { ctrlKey: false, shiftKey: false });
                }
                window.myCloudShowEmailContextMenu(e, 'message', m);
            });
            window._emailBindSwipe(item);
            
            if (typeof currentDomNode !== 'undefined' && currentDomNode) {
                listContent.insertBefore(item, currentDomNode);
            } else {
                listContent.appendChild(item);
            }
        }
    };

    const msgsToRender = isAppendOnly ? [renderMsgs[renderMsgs.length - 1]] : renderMsgs;

    for (let i = 0; i < msgsToRender.length; i++) {
        if (window._renderMessageListTask !== currentTask) return;
        const m = msgsToRender[i];
        if (!m) continue;
        appendMsgToDom(m);
        if (m.children && m.children.length > 0) {
            m.children.forEach(child => appendMsgToDom(child, true, m.thread_id_stable));
        }
        if (i > 0 && i % 15 === 0) await new Promise(resolve => requestAnimationFrame(resolve));
    }
    if (window._renderMessageListTask !== currentTask) return;


    if (!isAppendOnly && myCloudEmailState.hasMore && myCloudEmailState.listFilter !== 'unread') {
        const btnHtml = document.createElement('button');
        btnHtml.id = 'ceEmailLoadMoreBtn';
        btnHtml.className = 'ce-email-btn';
        btnHtml.style.cssText = 'width: calc(100% - 20px); margin: 10px; padding: 10px; text-align: center; justify-content: center; background: var(--gray-10); border: 1px solid var(--border-default); cursor: pointer; color: var(--text-primary);';
        btnHtml.textContent = (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.load_more) ? myCloud_LANG.load_more : 'Load More';
        btnHtml.onclick = () => myCloudEmailFetchMessages(myCloudEmailState.activeFolder, false, true);
        listContent.appendChild(btnHtml);
    }

    existingNodes.forEach(node => node.remove());

    if (myCloudEmailState.pendingSelectMsgKey) {
       const targetEl = listContent.querySelector(`.ce-email-list-item[data-msg-key="${CSS.escape(myCloudEmailState.pendingSelectMsgKey)}"]`);
        if (targetEl) {
            setTimeout(() => targetEl.click(), 10);
        }
        myCloudEmailState.pendingSelectMsgKey = null;
        myCloudEmailState.autoSelectFirst = false;
    } else if (myCloudEmailState.autoSelectFirst && !isAppendOnly) {
        // Silent Prefetch: Cache the first message without triggering the read status or UI
        const firstEl = listContent.querySelector('.ce-email-list-item');
        if (firstEl) {
            const mKey = firstEl.dataset.msgKey;
            if (mKey && (!myCloudEmailState.bodyCache || !myCloudEmailState.bodyCache[mKey])) {
                const mObj = myCloudEmailState.currentMessages.find(m => ((m.account_id || myCloudEmailState.activeAccount) + '|' + (m.folder || myCloudEmailState.activeFolder) + '|' + m.id) === mKey);
                
               if (mObj) {
                    if (!myCloudEmailState.bodyCache) myCloudEmailState.bodyCache = {};
                    
                    const fetchFd = new URLSearchParams({ 
                        myCloud_action: 'email_get_body', 
                        myCloud_key: myCloudState.key, 
                        myCloud_token: window.myCloudCsrfToken, 
                        account_id: mObj.account_id || myCloudEmailState.activeAccount, 
                        message_id: mObj.id, 
                        folder: mObj.folder || myCloudEmailState.activeFolder 
                    });
                    
                    myCloudEmailState.bodyCache[mKey] = fetch('', { method: 'POST', body: fetchFd }).then(myCloudCheckResponse).then(r => {
                        if (r.status === 'OK') {
                            let totalAttSize = 0;
                            if (r.attachments && r.attachments.length > 0) {
                                r.attachments.forEach(att => { totalAttSize += parseInt(att.size || 0); });
                            }
                            if (totalAttSize <= 5242880) {
                                myCloudEmailState.bodyCache[mKey] = r;
                                return r;
                            } else {
                                delete myCloudEmailState.bodyCache[mKey];
                                throw new Error('Attachments too large for cache');
                            }
                        } else {
                            delete myCloudEmailState.bodyCache[mKey];
							throw new Error('Failed to load body');
                        }
                    }).catch((e) => { 
                        delete myCloudEmailState.bodyCache[mKey]; 
                    });
                }
            }
        }
        myCloudEmailState.autoSelectFirst = false;
    }
  });
};


window._emailHighlightRawSource = function(raw) {
    if (!raw) return typeof myCloud_LANG !== 'undefined' && myCloud_LANG.raw_not_avail ? myCloud_LANG.raw_not_avail : 'Raw message not available.';
    
    // SECURITY FIX 4: Flawless strict text escaping. Everything is guaranteed plain text.
    let text = String(raw).replace(/&/g, '&amp;')
                          .replace(/</g, '&lt;')
                          .replace(/>/g, '&gt;')
                          .replace(/"/g, '&quot;')
                          .replace(/'/g, '&#039;');
    
    // Separate Headers and Body to prevent accidental highlighting in the payload
    const splitIdx = text.indexOf('\n\n');
    const splitIdxR = text.indexOf('\r\n\r\n');
    const realSplitIdx = (splitIdxR !== -1 && (splitIdx === -1 || splitIdxR < splitIdx)) ? splitIdxR : splitIdx;
    
    let headers = text;
    let body = '';
    if (realSplitIdx !== -1) {
        headers = text.substring(0, realSplitIdx);
        body = text.substring(realSplitIdx);
    }

    // Process Headers purely using safe CSS styling on the strictly escaped strings
        // FORENSIC FIX: Do not strip colons, use $& to wrap the exact match perfectly
        headers = headers.replace(/^([A-Za-z0-9\-]+:)/gm, '<span style="color:var(--accent-primary); font-weight:bold;">$&</span>');
        headers = headers.replace(/\b(?:\d{1,3}\.){3}\d{1,3}\b/g, '<span style="color:#d84315; font-weight:600;">$&</span>');
        
        headers = headers.replace(/\b(dmarc|spf|dkim)=([a-z]+)/gi, (match, protocol, result) => {
            let color = '#e81123'; // fail/softfail/neutral (Red)
            let resLower = result.toLowerCase();
            if (resLower === 'pass' || resLower === 'ok') color = '#107c10'; // Green
            return `${protocol}=<span style="font-weight:bold; color:${color};">${result}</span>`;
        });
        
        headers = headers.replace(/\b(pass)\b/gi, '<span style="color:#107c10; font-weight:bold;">$&</span>');
        headers = headers.replace(/\b(fail|softfail|hardfail)\b/gi, '<span style="color:#e81123; font-weight:bold;">$&</span>');
        headers = headers.replace(/([a-zA-Z0-9._-]+@[a-zA-Z0-9_-]+(?:\.[a-zA-Z0-9_-]+)+)/g, '<span style="color:var(--accent-primary); opacity:0.8;">$&</span>');
		
        // Process Body (MIME Part Boundaries)
        // FORENSIC FIX: Lookahead for line endings ensures we do not consume \r or inject \n, preserving pristine payload data
        body = body.replace(/^(--[a-zA-Z0-9_=\-\.]+(--)?)(?=\r?$)/gm, 
            '<span style="display:inline-block; width:100%; background:var(--gray-10); color:var(--text-secondary); padding:4px 8px; border-left:3px solid var(--accent-primary); font-weight:bold; border-radius:0 4px 4px 0;">$1</span>'
        );

        return headers + body;
};

window._emailDownloadResource = async function(action, payload, fallbackFilename, loadingMsg) {
    // 1. Show appropriate loading UI
    if (typeof myCloudCreateProgressUI === 'function') myCloudCreateProgressUI(loadingMsg || 'Downloading...');
    else if (typeof myCloudShowLoading === 'function') myCloudShowLoading();

    // 2. Build secure POST payload
    const fd = new URLSearchParams(payload);
    fd.append('myCloud_action', action);
    fd.append('myCloud_key', myCloudState.key);
    fd.append('myCloud_token', window.myCloudCsrfToken);

    try {
        // 3. Fetch binary blob securely via POST
        const response = await fetch('', { method: 'POST', body: fd });
        if (!response.ok) throw new Error("Network error");
        
        // --- NATIVE FILENAME EXTRACTION FIX ---
        // Reads the PHP server's exact Content-Disposition header to get the real filename
        let finalFilename = fallbackFilename;
        const disposition = response.headers.get('Content-Disposition');
        if (disposition) {
            const utf8Match = disposition.match(/filename\*=UTF-8''([^;]+)/i);
            if (utf8Match && utf8Match[1]) {
                finalFilename = decodeURIComponent(utf8Match[1]);
            } else {
                const asciiMatch = disposition.match(/filename="([^"]+)"/i);
                if (asciiMatch && asciiMatch[1]) {
                    try { finalFilename = decodeURIComponent(asciiMatch[1]); } 
                    catch(e) { finalFilename = asciiMatch[1]; }
                }
            }
        }
        
        const blob = await response.blob();
        
        if (typeof myCloudCloseProgressUI === 'function') myCloudCloseProgressUI();
        else if (typeof myCloudHideLoading === 'function') myCloudHideLoading();

        // 4. Professional Download: Use native Save File Picker if supported (Chrome/Edge/Opera)
        if (window.showSaveFilePicker) {
            try {
                const handle = await window.showSaveFilePicker({ suggestedName: finalFilename });
                const writable = await handle.createWritable();
                await writable.write(blob);
                await writable.close();
                return true;
            } catch(e) {
                // User cancelled the prompt, gracefully abort
                if (e.name !== 'AbortError') console.error(e);
				throw e;
            }
        }
        
        // 5. Fallback Download: Classic Object URL injection (Firefox/Safari/Mobile)
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = finalFilename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        setTimeout(() => URL.revokeObjectURL(url), 60000); // Cleanup memory
		return 'fallback';
        
    } catch(err) {
        if (typeof myCloudCloseProgressUI === 'function') myCloudCloseProgressUI();
        else if (typeof myCloudHideLoading === 'function') myCloudHideLoading();
        if (typeof myCloudShowAlert === 'function' && err.name !== 'AbortError') myCloudShowAlert('Error', 'Download failed: ' + err.message);
        throw err;
    }
};


window._emailDownloadEml = function(accId, folder, msgId) {
    return window._emailDownloadResource('email_dl_eml', {
        account_id: accId,
        folder: folder,
        message_id: msgId
    }, 'email_' + msgId + '.eml', 'Preparing EML...');
};

window._emailDownloadPdf = function(accId, folder, msgId) {
    let loadImg = '1';
    if (document.getElementById('ceLoadEmailImgBtn')) loadImg = '0';
    return window._emailDownloadResource('email_dl_pdf', {
        account_id: accId,
        folder: folder,
        message_id: msgId,
        load_images: loadImg
    }, 'email_' + msgId + '.pdf', 'Generating PDF...');
};

window._emailDownloadAttachment = function(accId, folder, msgId, part, filename) {
    window._emailDownloadResource('email_dl_attach', {
        account_id: accId,
        folder: folder,
        message_id: msgId,
        part: part,
        filename: filename
    }, filename, 'Downloading ' + filename + '...');
};

window._emailShowSaveOptions = function(accId, folder, msgId) {
    const overlay = document.getElementById('myCloudModalOverlay');
    const modal = document.getElementById('myCloudModal');
    const L = typeof myCloud_LANG !== 'undefined' ? myCloud_LANG : {};
    if (typeof myCloudResetModal === 'function') myCloudResetModal();

    overlay.style.display = 'flex';
    modal.className = 'myCloudModal ce-email-app-root';
    modal.style.maxWidth = '450px';

    let lastFmt = 'pdf';
    const match = document.cookie.match(/(^| )myCloudEmlSaveFmt=([^;]+)/);
    if (match && match[2] === 'eml') lastFmt = 'eml';
    
    let saveTarget = 'local';
    const matchTarget = document.cookie.match(/(^| )myCloudEmlSaveTarget=([^;]+)/);
    if (matchTarget && matchTarget[2] === 'cloud') saveTarget = 'cloud';

    const pdfChecked = lastFmt === 'pdf' ? 'checked' : '';
    const emlChecked = lastFmt === 'eml' ? 'checked' : '';
    const svgLogo = typeof myCloudSvgLogo !== 'undefined' ? myCloudSvgLogo : '';

    const writeableClouds = [];
    if (typeof myCloudCloudConfig !== 'undefined') {
        for (const [k, c] of Object.entries(myCloudCloudConfig)) {
            if ((c.interface || 'default') !== 'email' && c.rights !== 'read-only' && c.rights !== 'no-access') {
                writeableClouds.push(k);
            }
        }
    }
    
    if (writeableClouds.length === 0) saveTarget = 'local';
    const localChecked = saveTarget === 'local' ? 'checked' : '';
    
    let delAfterChecked = '';
    const matchDel = document.cookie.match(/(^| )myCloudEmlSaveDelAfter=([^;]+)/);
    if (matchDel && matchDel[2] === '1') delAfterChecked = 'checked';

    let targetOptionsHtml = '';
    if (writeableClouds.length > 0) {
        targetOptionsHtml = 
            '<div style="margin-top: 15px; margin-bottom: 10px; font-weight: 600; font-size: 13px; color: var(--text-secondary);">' + (L.save_target || 'Save Location') + '</div>' +
            '<label style="display:flex; align-items:center; gap:12px; padding:10px 12px; border:1px solid var(--border-default); border-radius:6px; margin-bottom:8px; cursor:pointer; background:var(--gray-05);">' +
                '<input type="radio" name="emlSaveTarget" value="local" ' + localChecked + '>' +
                '<div style="color:var(--text-primary); font-weight:500;">' + (L.save_local || 'Local Device') + '</div>' +
            '</label>' +
            '<label style="display:flex; align-items:center; gap:12px; padding:10px 12px; border:1px solid var(--border-default); border-radius:6px; margin-bottom:20px; cursor:pointer; background:var(--gray-05);">' +
                '<input type="radio" name="emlSaveTarget" value="cloud" ' + (saveTarget==='cloud' ? 'checked' : '') + '>' +
                '<div style="color:var(--text-primary); font-weight:500;">' + (L.save_cloud || 'Save to Cloud') + '</div>' +
            '</label>';
    } else {
        targetOptionsHtml = '<input type="hidden" name="emlSaveTarget" value="local">';
    }

    modal.innerHTML = 
        '<div class="myCloudModalHeader" style="justify-content:space-between;">' +
            '<span style="display:flex; align-items:center;">' + svgLogo + '&nbsp;' + (L.save || 'Save') + '...</span>' +
            '<button class="myCloudClose" onclick="myCloudCloseModal()" style="background:transparent; border:none; color:var(--text-secondary); font-size:18px; cursor:pointer;">✕</button>' +
        '</div>' +
        '<div class="myCloudModalBody" style="padding:20px;">' +
            '<div style="margin-bottom: 10px; font-weight: 600; font-size: 13px; color: var(--text-secondary);">' + (L.save_format || 'File Format') + '</div>' +
            '<label style="display:flex; align-items:center; gap:12px; padding:12px; border:1px solid var(--border-default); border-radius:6px; margin-bottom:12px; cursor:pointer; background:var(--gray-05);">' +
                '<input type="radio" name="emlSaveFmt" value="pdf" ' + pdfChecked + '>' +
                '<div><b style="color:var(--text-primary);">' + (L.eml_pdf_doc || 'PDF Document') + '</b><div style="font-size:12px; color:var(--text-secondary); margin-top:4px;">' + (L.save_pdf_desc || 'Saves the email and attachments as a single PDF.') + '</div></div>' +
            '</label>' +
            '<label style="display:flex; align-items:center; gap:12px; padding:12px; border:1px solid var(--border-default); border-radius:6px; margin-bottom:12px; cursor:pointer; background:var(--gray-05);">' +
                '<input type="radio" name="emlSaveFmt" value="eml" ' + emlChecked + '>' +
                '<div><b style="color:var(--text-primary);">' + (L.eml_eml_file || 'EML File') + '</b><div style="font-size:12px; color:var(--text-secondary); margin-top:4px;">' + (L.save_eml_desc || 'Raw source format. Ideal for importing into mail clients.') + '</div></div>' +
            '</label>' +
            targetOptionsHtml +
            '<label style="display:flex; align-items:center; gap:8px; margin-top:15px; cursor:pointer;"><input type="checkbox" id="emlSaveDelCb" class="myCloudCheckbox" ' + delAfterChecked + '> <span style="font-size:13px; color:var(--text-primary);">' + (L.delete_after_save || 'Delete email after saving') + '</span></label>' +
            '<div class="myCloudButtons" style="justify-content:flex-end; margin-top:15px;">' +
                '<button onclick="myCloudCloseModal()">' + (L.cancel || 'Cancel') + '</button>' +
                '<button id="btnEmlDoSave" style="background:var(--accent-primary); color:#fff; border:none;">' + (writeableClouds.length > 0 ? (L.next || 'Next') : (L.save || 'Save')) + '</button>' +
            '</div>' +
        '</div>';

    document.getElementById('btnEmlDoSave').onclick = async () => {
        const fmt = document.querySelector('input[name="emlSaveFmt"]:checked').value;
        const target = document.querySelector('input[name="emlSaveTarget"]:checked') ? document.querySelector('input[name="emlSaveTarget"]:checked').value : 'local';
        const delAfter = document.getElementById('emlSaveDelCb') ? document.getElementById('emlSaveDelCb').checked : false;
		
        document.cookie = "myCloudEmlSaveFmt=" + fmt + "; path=/; max-age=31536000; SameSite=Lax";
        document.cookie = "myCloudEmlSaveTarget=" + target + "; path=/; max-age=31536000; SameSite=Lax";
		document.cookie = "myCloudEmlSaveDelAfter=" + (delAfter ? "1" : "0") + "; path=/; max-age=31536000; SameSite=Lax";
        
        myCloudCloseModal();

        if (target === 'local') {
            try {
                let dlSuccess = false;
                if (fmt === 'pdf') {
                    dlSuccess = await window._emailDownloadPdf(accId, folder, msgId);
                } else {
                    dlSuccess = await window._emailDownloadEml(accId, folder, msgId);
                }
                if (delAfter && dlSuccess) {
                    const metaSafe = encodeURIComponent(JSON.stringify({id: msgId, account_id: accId, folder: folder})).replace(/'/g, "%27");
                    if (dlSuccess === 'fallback') {
                        if (typeof myCloudShowAlert === 'function') {
                            myCloudShowAlert(L.confirm_delete || 'Confirm Deletion', L.confirm_del_after_save || 'Please confirm the file downloaded successfully before deleting the email.', () => {
                                window.myCloudEmailAction('delete', msgId, metaSafe);
                            });
                        }
                    } else {
                        window.myCloudEmailAction('delete', msgId, metaSafe);
                    }
                }
            } catch(e) {}
        } else {
            window._emailSaveToCloud(accId, folder, msgId, fmt, writeableClouds, delAfter);
        }
    };
};

window._emailShowCloudTreeModal = function(options) {
    const { mode, title, okText, requireWrite, onConfirm } = options;
    const L = typeof myCloud_LANG !== 'undefined' ? myCloud_LANG : {};
    const devKey = typeof myCloudGetCurrentDeviceKey === 'function' ? myCloudGetCurrentDeviceKey() : 'desktop';

    const availableClouds = [];
    if (typeof myCloudCloudConfig !== 'undefined') {
        for (const [k, c] of Object.entries(myCloudCloudConfig)) {
            if ((c.interface || 'default') === 'email') continue;
            if (c.rights === 'no-access') continue;
            if (requireWrite && c.rights === 'read-only') continue;
            availableClouds.push(k);
        }
    }

    if (availableClouds.length === 0) {
        if (typeof cxToast === 'function') cxToast(L.no_capable_clouds || "No capable clouds found.", false);
        else alert("No capable clouds found.");
        return;
    }

    if (!myCloudState.settings[devKey]) myCloudState.settings[devKey] = {};
    let currentCloud = myCloudState.settings[devKey].lastEmailSaveCloud || availableClouds[0];
    if (!availableClouds.includes(currentCloud)) currentCloud = availableClouds[0];
    let lastPathObj = myCloudState.settings[devKey].lastEmailSavePath || {};
    let currentPath = lastPathObj[currentCloud] || '/';
    let selectedItem = mode === 'folder' ? currentPath : null;

    let expandedPaths = new Set(['/']);
    if (currentPath !== '/') {
        let walker = '';
        currentPath.split('/').filter(p => p).forEach(part => { walker += '/' + part; expandedPaths.add(walker); });
    }

    let cloudCache = {};
    let treeFavs = [];

    const overlay = document.createElement('div');
    overlay.className = 'myCloudOverlay';
    overlay.style.display = 'flex';
    overlay.style.zIndex = '100050';

    const modal = document.createElement('div');
    modal.className = 'myCloudModal tree-selector ce-email-app-root';

    const curLang = myCloudState.settings.language || 'en';
    const isRtl = ['ar', 'fa', 'he', 'ur'].includes(curLang);
    modal.setAttribute('dir', isRtl ? 'rtl' : 'ltr');

    overlay.appendChild(modal);
    document.body.appendChild(overlay);

    const closeSelectorModal = () => { overlay.remove(); };
    
    let cloudOptions = '';
    availableClouds.forEach(c => { cloudOptions += `<option value="${c}" ${c === currentCloud ? 'selected' : ''}>${c.charAt(0).toUpperCase() + c.slice(1)}</option>`; });

    modal.innerHTML = `
        <div class="myCloudModalHeader" style="justify-content:space-between; align-items:center;">
            <span>${typeof myCloudSvgLogo !== 'undefined' ? myCloudSvgLogo : ''} <span style="font-weight:100;">- ${title}</span></span>
            <button class="ce-tree-close-btn" style="background:transparent; border:none; font-size:20px; cursor:pointer; color:inherit; line-height:1;">✕</button>
		</div>
        <div class="myCloudModalBody" style="display:flex; flex-direction:column; flex:1; overflow:hidden;">
            <div style="margin-bottom:10px; display:flex; justify-content:space-between; align-items:center; gap:10px;">
                <select id="ceEmlCloudSelect" class="myCloudInlineInput" style="flex:1; font-weight:bold;">${cloudOptions}</select>
                ${requireWrite ? `<button id="ceEmlNewFolderBtn" class="cx-btn" title="${L.new_folder || 'New Folder'}" style="padding:4px 8px; height:28px;" disabled><svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M20 6h-8l-2-2H4c-1.1 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-1 8h-3v3h-2v-3h-3v-2h3V9h2v3h3v2z"/></svg></button>` : ''}
            </div>
            <div id="ceEmlTreeFavsWrap" style="display:flex; flex-wrap:wrap; gap:6px; padding-bottom:8px; margin-bottom:8px; border-bottom:1px solid var(--border-default); min-height:36px; max-height:90px; overflow-y:auto; width:100%; box-sizing:border-box; flex-shrink:0;"></div>
            <div id="myCloudCloudTreeBox" class="myCloudTreeBox" style="flex:1; overflow:auto; margin-bottom:15px; margin-top:0;"></div>
			<div class="myCloudButtons" style="margin-top:0;">
			<button class="cx-btn ce-tree-close-btn">${L.cancel || 'Cancel'}</button>
			<button class="cx-btn cx-btn-primary" id="myCloudCloudSaveOk" disabled style="background:var(--accent-primary); color:#fff; border:none;">${okText}</button>
            </div></div>
        </div>
    `;

	modal.querySelectorAll('.ce-tree-close-btn').forEach(btn => btn.onclick = closeSelectorModal);

    const treeBox = document.getElementById('myCloudCloudTreeBox');
    const cloudSel = document.getElementById('ceEmlCloudSelect');
    const btnOk = document.getElementById('myCloudCloudSaveOk');
    const btnNewFolder = document.getElementById('ceEmlNewFolderBtn');

    const fetchFavs = async () => {
        const res = await fetch('', { method:'POST', body: new URLSearchParams({ myCloud_action: 'email_get_tree_favs', myCloud_key: myCloudState.key, myCloud_token: window.myCloudCsrfToken }) }).then(r=>r.json());
        if (res.status === 'OK') { treeFavs = res.favorites || []; renderFavs(); }
    };
    
    const saveFavs = async () => {
        await fetch('', { method:'POST', body: new URLSearchParams({ myCloud_action: 'email_save_tree_favs', myCloud_key: myCloudState.key, myCloud_token: window.myCloudCsrfToken, favorites: JSON.stringify(treeFavs) }) });
        renderFavs();
    };

    const toggleFav = (path, name) => {
        const idx = treeFavs.findIndex(f => f.cloud === currentCloud && f.path === path);
        if (idx !== -1) treeFavs.splice(idx, 1);
        else treeFavs.push({ cloud: currentCloud, path: path, name: name });
        saveFavs();
    };

    const renderFavs = () => {
        const wrap = document.getElementById('ceEmlTreeFavsWrap');
        if (!wrap) return;
        wrap.innerHTML = '';
        if (treeFavs.length === 0) {
            wrap.innerHTML = `<span style="color:var(--text-secondary); font-size:12px; font-style:italic; display:flex; align-items:center;">${L.fav_folders_empty || 'Favorite folders will appear here...'}</span>`;
        } else {
            treeFavs.forEach(f => {
                const btn = document.createElement('button');
                btn.className = 'cx-btn';
                btn.style.cssText = 'padding: 2px 8px; border-radius: 12px; font-size: 11px; white-space: nowrap; flex-shrink: 0; background: var(--gray-10); border: 1px solid var(--border-medium); display:flex; align-items:center; gap:4px;';
                btn.innerHTML = `<span style="color:#f0ad4e;">★</span> <span>${myCloudEscapeHtml(f.name)}</span>`;
                btn.onclick = () => {
                    if (!availableClouds.includes(f.cloud)) { if (typeof cxToast === 'function') cxToast('Cloud unavailable', false); return; }
                    cloudSel.value = f.cloud;
                    currentCloud = f.cloud;
                    currentPath = f.path;
                    selectedItem = mode === 'folder' ? f.path : null;
                    expandedPaths = new Set(['/']);
                    let walker = '';
                    f.path.split('/').filter(p => p).forEach(part => { walker += '/' + part; expandedPaths.add(walker); });
                    btnOk.disabled = true;
                    loadRoot();
                };
                wrap.appendChild(btn);
            });
        }
        if (treeBox && treeBox.innerHTML) loadRoot(true); 
    };

    const fetchDir = async (cloudKey, dirPath) => {
        const fd = new URLSearchParams({ myCloud_action: 'list', myCloud_key: cloudKey, myCloud_token: window.myCloudCsrfToken, path: dirPath, depth: 1 });
        const res = await fetch('', { method: 'POST', body: fd }).then(r=>r.json());
        if (res.status === 'OK') {
            if (!cloudCache[cloudKey]) cloudCache[cloudKey] = [];
            cloudCache[cloudKey] = cloudCache[cloudKey].filter(i => { const p = i.name.substring(0, i.name.lastIndexOf('/') || 0) || '/'; return p !== dirPath; });
            res.data.forEach(i => {
                if (i.name === '/.recycle_bin') return;
                if (mode === 'folder' && i.size !== 'DIR') return;
                cloudCache[cloudKey].push(i);
            });
        }
        return res;
    };

    const renderTree = async (container, path) => {
        container.innerHTML = '';
        const items = (cloudCache[currentCloud] || []).filter(i => { const p = i.name.substring(0, i.name.lastIndexOf('/') || 0) || '/'; return p === path; });
        items.sort((a,b) => {
            if (a.size === 'DIR' && b.size !== 'DIR') return -1;
            if (a.size !== 'DIR' && b.size === 'DIR') return 1;
            return a.name.split('/').pop().localeCompare(b.name.split('/').pop());
        });

        const ul = document.createElement('ul');
        if (items.length === 0 && path !== '/') { container.appendChild(ul); return; }

        items.forEach(item => {
            const li = document.createElement('li');
            const fullPath = item.name;
            const isDir = item.size === 'DIR';
            const displayName = fullPath.split('/').pop();
            
            const rowDiv = document.createElement('div');
            rowDiv.className = 'tree-item';
            rowDiv.dataset.path = fullPath;
            
            const toggle = document.createElement('span');
            toggle.className = 'tree-toggle';
            if (isDir) {
                const isExpanded = expandedPaths.has(fullPath);
                toggle.innerHTML = isExpanded ? '▾' : '▸';
                toggle.onclick = (e) => {
                    e.stopPropagation();
                    if (expandedPaths.has(fullPath)) {
                        expandedPaths.delete(fullPath); toggle.innerHTML = '▸';
                        const childContainer = li.querySelector('.tree-children');
                        if (childContainer) childContainer.remove();
                    } else {
                        expandedPaths.add(fullPath); toggle.innerHTML = '⌛';
                        const childContainer = document.createElement('div');
                        childContainer.className = 'tree-children'; li.appendChild(childContainer);
                        fetchDir(currentCloud, fullPath).then(() => { toggle.innerHTML = '▾'; renderTree(childContainer, fullPath); });
                    }
                };
            } else {
                toggle.innerHTML = ''; toggle.style.cursor = 'default';
            }
            
            const contentDiv = document.createElement('div');
            contentDiv.className = 'tree-content';
            if (fullPath === selectedItem) contentDiv.classList.add('selected');
            
            let iconHtml = typeof myCloudIconFolder !== 'undefined' ? myCloudIconFolder : '📁';
            if (!isDir) {
                const ext = displayName.split('.').pop().toLowerCase();
                iconHtml = (typeof myCloudTypeIcons !== 'undefined' && myCloudTypeIcons[ext]) ? myCloudTypeIcons[ext] : (typeof myCloudTypeIcons !== 'undefined' ? myCloudTypeIcons._default : '📄');
            }

            let favBtnHtml = '';
            if (isDir) {
                const isFav = treeFavs.some(f => f.cloud === currentCloud && f.path === fullPath);
                const starColor = isFav ? '#f0ad4e' : 'var(--border-medium)';
                favBtnHtml = `<span title="Favorite" style="margin-inline-start:auto; cursor:pointer; color:${starColor}; font-size:14px; padding:0 4px;" onclick="event.stopPropagation(); window._emailCloudTreeToggleFav('${fullPath.replace(/\\/g, "\\\\").replace(/'/g, "\\'")}', '${myCloudEscapeHtml(displayName).replace(/\\/g, "\\\\").replace(/'/g, "\\'")}')">★</span>`;
			}

            contentDiv.innerHTML = `<span class="tree-icon">${iconHtml}</span><span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${myCloudEscapeHtml(displayName)}</span>${favBtnHtml}`;
            
            contentDiv.onclick = () => {
                if (mode === 'file' && isDir) {
                    toggle.click(); return;
                }
                modal.querySelectorAll('.tree-content.selected').forEach(el => el.classList.remove('selected'));
                contentDiv.classList.add('selected');
                selectedItem = fullPath;
                if (isDir) currentPath = fullPath;
                btnOk.disabled = false;
                if (btnNewFolder && isDir) btnNewFolder.disabled = false;
            };

            rowDiv.appendChild(toggle);
            rowDiv.appendChild(contentDiv);
            li.appendChild(rowDiv);
            
            if (isDir && expandedPaths.has(fullPath)) {
                const childContainer = document.createElement('div');
                childContainer.className = 'tree-children'; li.appendChild(childContainer);
                renderTree(childContainer, fullPath);
            }
            ul.appendChild(li);
        });
        container.appendChild(ul);
    };

    window._emailCloudTreeToggleFav = function(path, name) { toggleFav(path, name); };

    const loadRoot = async (skipFetch = false) => {
        if (!skipFetch) {
            treeBox.innerHTML = '<div style="padding:10px; color:var(--text-secondary);">Loading...</div>';
            await fetchDir(currentCloud, '/');
        }
        treeBox.innerHTML = '';
        const rootUl = document.createElement('ul'); rootUl.style.paddingLeft = '0';
        const rootLi = document.createElement('li');
        const rootRow = document.createElement('div'); rootRow.className = 'tree-item';
        
        const rootToggle = document.createElement('span'); rootToggle.className = 'tree-toggle'; rootToggle.innerHTML = '▾';
        rootToggle.onclick = (e) => {
            e.stopPropagation();
            const childContainer = rootLi.querySelector('.tree-children');
            if (childContainer) {
                childContainer.style.display = childContainer.style.display === 'none' ? 'block' : 'none';
                rootToggle.innerHTML = childContainer.style.display === 'none' ? '▸' : '▾';
            }
        };
        
        const rootContent = document.createElement('div'); rootContent.className = 'tree-content';
        if (selectedItem === '/') rootContent.classList.add('selected');

        const isFav = treeFavs.some(f => f.cloud === currentCloud && f.path === '/');
        const starColor = isFav ? '#f0ad4e' : 'var(--border-medium)';
        const rootFavHtml = `<span title="Favorite" style="margin-inline-start:auto; cursor:pointer; color:${starColor}; font-size:14px; padding:0 4px;" onclick="event.stopPropagation(); window._emailCloudTreeToggleFav('/', '/ (Root)')">★</span>`;

        rootContent.innerHTML = `<span class="tree-icon">${typeof myCloudIconFolder !== 'undefined' ? myCloudIconFolder : '📁'}</span><span>/ (Root)</span>${rootFavHtml}`;
        rootContent.onclick = () => {
            if (mode === 'file') { rootToggle.click(); return; }
            modal.querySelectorAll('.tree-content.selected').forEach(el => el.classList.remove('selected'));
            rootContent.classList.add('selected');
            selectedItem = '/';
            currentPath = '/';
            btnOk.disabled = false;
            if (btnNewFolder) btnNewFolder.disabled = false;
        };
        
        rootRow.appendChild(rootToggle); rootRow.appendChild(rootContent); rootLi.appendChild(rootRow);
        const rootChildren = document.createElement('div'); rootChildren.className = 'tree-children'; rootLi.appendChild(rootChildren);
        
        if (!skipFetch) {
            for (let p of Array.from(expandedPaths)) { if (p !== '/') await fetchDir(currentCloud, p); }
        }
        await renderTree(rootChildren, '/'); 
        rootUl.appendChild(rootLi); treeBox.appendChild(rootUl);
        
        setTimeout(() => {
            const selEl = treeBox.querySelector('.tree-content.selected');
            if (selEl) selEl.scrollIntoView({block: 'center'});
            if (selectedItem) btnOk.disabled = false;
        }, 100);
    };

    cloudSel.onchange = (e) => {
        currentCloud = e.target.value;
        currentPath = lastPathObj[currentCloud] || '/';
        selectedItem = mode === 'folder' ? currentPath : null;
        expandedPaths = new Set(['/']);
        if (currentPath !== '/') {
            let walker = '';
            currentPath.split('/').filter(p => p).forEach(part => { walker += '/' + part; expandedPaths.add(walker); });
        }
        btnOk.disabled = true; if (btnNewFolder) btnNewFolder.disabled = true;
        loadRoot();
    };

    if (btnNewFolder) {
        btnNewFolder.onclick = () => {
            if (!currentPath) return;
            const selEl = treeBox.querySelector('.tree-content.selected');
            if (!selEl) return;
            const targetLi = selEl.closest('li');
            if (!targetLi) return;

            expandedPaths.add(currentPath);
            let childContainer = targetLi.querySelector('.tree-children');
            if(!childContainer) { childContainer = document.createElement('div'); childContainer.className = 'tree-children'; targetLi.appendChild(childContainer); }
            let ul = childContainer.querySelector('ul');
            if(!ul) { ul = document.createElement('ul'); childContainer.appendChild(ul); }

            const li = document.createElement('li'); li.className = 'ce-new-folder-node';
            li.innerHTML = `<div class="tree-item"><span class="tree-toggle"></span><div class="tree-content" style="padding:0;"><span class="tree-icon">${typeof myCloudIconFolder !== 'undefined' ? myCloudIconFolder : '📁'}</span><input type="text" class="myCloudInlineInput" style="height:22px; width:150px; font-size:13px; margin:0;"></div></div>`;

            if(ul.firstChild) ul.insertBefore(li, ul.firstChild); else ul.appendChild(li);
            const parentToggle = targetLi.querySelector('.tree-toggle');
            if(parentToggle) parentToggle.innerHTML = '▾';
            li.scrollIntoView({block: "nearest"});
            const input = li.querySelector('input'); input.focus();

            const save = async () => {
                const name = input.value.trim();
                if (!name) { li.remove(); return; }
                let finalName = name;
                if (typeof myCloudCrypto !== 'undefined' && myCloudCrypto.isDirEncrypted(currentPath)) {
                    if (!myCloudCrypto.isDirUnlocked(currentPath)) { alert('Directory is locked.'); li.remove(); return; }
                    finalName = await myCloudCrypto.encryptName(currentPath, name);
                }
                const fd = new URLSearchParams({ myCloud_action: 'mkdir', myCloud_key: currentCloud, myCloud_token: window.myCloudCsrfToken, parent: currentPath, name: finalName });
                fetch('', { method: 'POST', body: fd }).then(r => r.json()).then(resp => {
                    if (resp.status === 'OK') {
                        fetchDir(currentCloud, currentPath).then(() => {
                            renderTree(childContainer, currentPath).then(() => {
                                const newFullPath = (currentPath === '/' ? '' : currentPath) + '/' + finalName;
                                setTimeout(() => {
                                    const allItems = childContainer.querySelectorAll('.tree-item');
                                    for(let item of allItems) {
                                        if(item.dataset.path === newFullPath) {
                                            const content = item.querySelector('.tree-content');
                                            if (content) content.click();
                                            item.scrollIntoView({block: "center"});
                                            break;
                                        }
                                    }
                                }, 50);
                            });
                        });
                    } else { li.remove(); alert(resp.msg); }
                });
            };

            input.onkeydown = (e) => { if(e.key === 'Enter') save(); if(e.key === 'Escape') li.remove(); e.stopPropagation(); };
            input.onblur = () => { if(!input.value.trim()) li.remove(); };
        };
    }

    fetchFavs().then(() => loadRoot());

    btnOk.onclick = async () => {
        if (!selectedItem) return;
        myCloudState.settings[devKey].lastEmailSaveCloud = currentCloud;
        if (!myCloudState.settings[devKey].lastEmailSavePath) myCloudState.settings[devKey].lastEmailSavePath = {};
        myCloudState.settings[devKey].lastEmailSavePath[currentCloud] = currentPath;
        if (typeof myCloudSaveSettings === 'function') myCloudSaveSettings();
        
        closeSelectorModal();
        if (onConfirm) onConfirm(currentCloud, selectedItem, cloudCache[currentCloud].find(i => i.name === selectedItem));
    };
};

window._emailSaveToCloud = function(accId, folder, msgId, format, availableClouds, delAfter = false) {
    const L = typeof myCloud_LANG !== 'undefined' ? myCloud_LANG : {};
    window._emailShowCloudTreeModal({
        mode: 'folder',
        title: (L.save_to_cloud || 'Save to Cloud') + ' (' + format.toUpperCase() + ')',
        okText: L.save || 'Save',
        requireWrite: true,
        onConfirm: async (currentCloud, currentPath) => {
            if (typeof myCloudCreateProgressUI === 'function') myCloudCreateProgressUI((L.saving || 'Saving') + '...');
            
            const act = format === 'pdf' ? 'email_dl_pdf' : 'email_dl_eml';
            let loadImg = '1';
            if (document.getElementById('ceLoadEmailImgBtn')) loadImg = '0';
            
            const fd = new URLSearchParams({
                myCloud_action: act,
                myCloud_key: myCloudState.key,
                myCloud_token: window.myCloudCsrfToken,
                account_id: accId,
                folder: folder,
                message_id: msgId
            });
            if (format === 'pdf') fd.append('load_images', loadImg);
            
            try {
                const response = await fetch('', { method: 'POST', body: fd });
                if (!response.ok) throw new Error("Generation failed");
                
                let finalName = 'Email.' + format;
                const disposition = response.headers.get('Content-Disposition');
                if (disposition) {
                    const utf8Match = disposition.match(/filename\*=UTF-8''([^;]+)/i);
                    if (utf8Match && utf8Match[1]) {
                        finalName = decodeURIComponent(utf8Match[1]);
                    } else {
                        const asciiMatch = disposition.match(/filename="([^"]+)"/i);
                        if (asciiMatch && asciiMatch[1]) {
                            try { finalName = decodeURIComponent(asciiMatch[1]); } 
                            catch(e) { finalName = asciiMatch[1]; }
                        }
                    }
                }
                
                const blob = await response.blob();
                
                // THE FIX: Cast raw blob to a native File object immediately
                const fileObj = new File([blob], finalName, { type: blob.type || (format === 'pdf' ? 'application/pdf' : 'message/rfc822') });
                let finalBlob = fileObj;
                
                if (typeof myCloudCrypto !== 'undefined' && myCloudCrypto.isDirEncrypted(currentPath)) {
                    const cRoot = myCloudCrypto.getCryptoRoot(currentPath);
                    if (myCloudCrypto.isDirUnlocked(cRoot)) {
                        finalBlob = await myCloudCrypto.encryptFile(cRoot, fileObj);
                        finalName = await myCloudCrypto.encryptName(currentPath, finalName);
                    } else {
                        throw new Error(L.target_vault_locked || "Target folder is an encrypted vault and is currently locked.");
                    }
                }
                
                const upFd = new FormData();
                upFd.append('myCloud_action', 'upload');
                upFd.append('myCloud_key', currentCloud);
                upFd.append('myCloud_token', window.myCloudCsrfToken);
                upFd.append('dir', currentPath);
                upFd.append('resolution', 'keep_both');
                upFd.append('file', finalBlob, finalName);
                
                const upRes = await fetch('', { method: 'POST', body: upFd }).then(r=>r.json());
                if (typeof myCloudCloseProgressUI === 'function') myCloudCloseProgressUI();
                
                if (upRes.status === 'OK') {
                    if (typeof cxToast === 'function') cxToast(L.save_cloud_success || 'Saved successfully to cloud.', true);
                    else if (typeof myCloudShowAlert === 'function') myCloudShowAlert(L.success || 'Success', L.save_cloud_success || 'Saved successfully to cloud.');
                    if (delAfter) {
                        const metaSafe = encodeURIComponent(JSON.stringify({id: msgId, account_id: accId, folder: folder})).replace(/'/g, "%27");
                        window.myCloudEmailAction('delete', msgId, metaSafe);
                    }
                } else {
                    throw new Error(upRes.msg || 'Upload failed');
                }
            } catch (err) {
                if (typeof myCloudCloseProgressUI === 'function') myCloudCloseProgressUI();
                if (typeof myCloudShowAlert === 'function') myCloudShowAlert(L.error_prefix || 'Error', (L.save_cloud_error || 'Failed to save to cloud:') + ' ' + err.message);
            }
        }
    });
};

window._emailSaveAttachmentToCloud = function(accId, folder, msgId, part, filename) {
    const L = typeof myCloud_LANG !== 'undefined' ? myCloud_LANG : {};
    window._emailShowCloudTreeModal({
        mode: 'folder',
        title: (L.save_to_cloud || 'Save to Cloud') + ': ' + filename,
        okText: L.save || 'Save',
        requireWrite: true,
        onConfirm: async (currentCloud, currentPath) => {
            if (typeof myCloudCreateProgressUI === 'function') myCloudCreateProgressUI((L.saving || 'Saving') + '...');
            
            const fd = new URLSearchParams({
                myCloud_action: 'email_dl_attach',
                myCloud_key: myCloudState.key,
                myCloud_token: window.myCloudCsrfToken,
                account_id: accId,
                folder: folder,
                message_id: msgId,
                part: part,
                filename: filename,
                cloud_save: '1'
            });
            
            try {
                const response = await fetch('', { method: 'POST', body: fd });
                if (!response.ok) throw new Error("Download failed");
                
                let finalName = filename;
                const disposition = response.headers.get('Content-Disposition');
                if (disposition) {
                    const utf8Match = disposition.match(/filename\*=UTF-8''([^;]+)/i);
                    if (utf8Match && utf8Match[1]) {
                        finalName = decodeURIComponent(utf8Match[1]);
                    } else {
                        const asciiMatch = disposition.match(/filename="([^"]+)"/i);
                        if (asciiMatch && asciiMatch[1]) {
                            try { finalName = decodeURIComponent(asciiMatch[1]); } 
                            catch(e) { finalName = asciiMatch[1]; }
                        }
                    }
                }
                
                const blob = await response.blob();
                
                // THE FIX: Cast raw blob to a native File object immediately
                const fileObj = new File([blob], finalName, { type: blob.type || 'application/octet-stream' });
                let finalBlob = fileObj;
                
                if (typeof myCloudCrypto !== 'undefined' && myCloudCrypto.isDirEncrypted(currentPath)) {
                    const cRoot = myCloudCrypto.getCryptoRoot(currentPath);
                    if (myCloudCrypto.isDirUnlocked(cRoot)) {
                        finalBlob = await myCloudCrypto.encryptFile(cRoot, fileObj);
                        finalName = await myCloudCrypto.encryptName(currentPath, finalName);
                    } else {
                        throw new Error(L.target_vault_locked || "Target folder is an encrypted vault and is currently locked.");
                    }
                }
                
                const upFd = new FormData();
                upFd.append('myCloud_action', 'upload');
                upFd.append('myCloud_key', currentCloud);
                upFd.append('myCloud_token', window.myCloudCsrfToken);
                upFd.append('dir', currentPath);
                upFd.append('resolution', 'keep_both');
                upFd.append('file', finalBlob, finalName);
                
                const upRes = await fetch('', { method: 'POST', body: upFd }).then(r=>r.json());
                if (typeof myCloudCloseProgressUI === 'function') myCloudCloseProgressUI();
                
                if (upRes.status === 'OK') {
                    if (typeof cxToast === 'function') cxToast(L.save_cloud_success || 'Saved successfully to cloud.', true);
                    else if (typeof myCloudShowAlert === 'function') myCloudShowAlert(L.success || 'Success', L.save_cloud_success || 'Saved successfully to cloud.');
                    
                    if (delAfter) {
                        const metaSafe = encodeURIComponent(JSON.stringify({id: msgId, account_id: accId, folder: folder})).replace(/'/g, "%27");
                        window.myCloudEmailAction('delete', msgId, metaSafe);
                    }
                } else {
                    throw new Error(upRes.msg || 'Upload failed');
                }
            } catch (err) {
                if (typeof myCloudCloseProgressUI === 'function') myCloudCloseProgressUI();
                if (typeof myCloudShowAlert === 'function') myCloudShowAlert(L.error_prefix || 'Error', (L.save_cloud_error || 'Failed to save to cloud:') + ' ' + err.message);
            }
        }
    });
};

window.myCloudEmailReadMessage = function(msgId, meta) {
    const L = typeof myCloud_LANG !== 'undefined' ? myCloud_LANG : {};
    const reading = document.getElementById('emailPaneReading');

    // Prepare reading pane as a flex column
    reading.style.display = 'flex';
    reading.style.flexDirection = 'column';
    reading.style.height = '100%';
    reading.style.overflow = 'hidden';

    const targetAcc = meta.account_id || myCloudEmailState.activeAccount;
    const targetFolder = meta.folder || myCloudEmailState.activeFolder;
    const msgKey = targetAcc + '|' + targetFolder + '|' + msgId;
    
    const mobileBackBtn = '<button class="owa-btn ce-email-mobile-only" onclick="if(window.history.state && window.history.state.ce_email_view){window.history.back();}else{window._emailSetMobileView(\'list\');}" title="' + (L.back || 'Back') + '"><span class="owa-icon"><svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg></span></button>';

    const listItems = Array.from(document.querySelectorAll('#ceEmailListContent .ce-email-list-item'));
    const currentIndex = listItems.findIndex(function(el) { return el.dataset.msgKey === String(msgKey); });
    const prevKey = currentIndex > 0 ? listItems[currentIndex - 1].dataset.msgKey : null;
    const nextKey = (currentIndex !== -1 && currentIndex < listItems.length - 1) ? listItems[currentIndex + 1].dataset.msgKey : null;

    const prevBtn = '<button class="owa-btn ce-email-mobile-only" ' + (prevKey ? 'onclick="var el=document.querySelector(\'.ce-email-list-item[data-msg-key=&quot;' + prevKey + '&quot;]\'); if(el) el.click();"' : 'disabled style="opacity:0.5"') + ' title="Previous"><span class="owa-icon"><svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M15.41 16.59L10.83 12l4.58-4.59L14 6l-6 6 6 6 1.41-1.41z"/></svg></span></button>';
    const nextBtn = '<button class="owa-btn ce-email-mobile-only" ' + (nextKey ? 'onclick="var el=document.querySelector(\'.ce-email-list-item[data-msg-key=&quot;' + nextKey + '&quot;]\'); if(el) el.click();"' : 'disabled style="opacity:0.5"') + ' title="Next"><span class="owa-icon"><svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6-1.41-1.41z"/></svg></span></button>';
    const mobileDivider = '<div class="owa-divider ce-email-mobile-only"></div>';

    // FIX: Delay injecting the skeleton to completely eliminate UI flickering on cached messages
    const skeletonHtml = 
        '<div class="myCloudToolbar-wrapper" style="flex-shrink:0;">' +
            '<div class="owa-toolbar">' +
                mobileBackBtn + prevBtn + nextBtn + mobileDivider +
                '<div class="ce-skeleton" style="width:70px; height:28px; border-radius:4px; pointer-events:none;"></div>' +
                '<div class="ce-skeleton" style="width:80px; height:28px; border-radius:4px; margin-inline-start:6px; pointer-events:none;"></div>' +
                '<div class="owa-divider"></div>' +
                '<div style="flex:1;"></div>' +
                '<div class="ce-skeleton" style="width:70px; height:28px; border-radius:4px; pointer-events:none;"></div>' +
            '</div>' +
        '</div>' +
        '<div style="padding:20px; border-block-end:1px solid var(--border-default);">' +
            '<div class="ce-skeleton" style="width:65%; height:24px; margin-block-end:20px;"></div>' +
            '<div style="display:flex; gap:10px;">' +
                '<div class="ce-skeleton" style="width:36px; height:36px; border-radius:50%; flex-shrink:0;"></div>' +
                '<div style="flex:1;">' +
                    '<div class="ce-skeleton" style="width:200px; height:12px; margin-block-end:8px;"></div>' +
                    '<div class="ce-skeleton" style="width:120px; height:10px;"></div>' +
                '</div>' +
            '</div>' +
        '</div>' +
        '<div style="padding:30px 20px;">' +
            '<div class="ce-skeleton" style="width:100%; height:12px; margin-block-end:12px;"></div>' +
            '<div class="ce-skeleton" style="width:95%; height:12px; margin-block-end:12px;"></div>' +
            '<div class="ce-skeleton" style="width:98%; height:12px; margin-block-end:12px;"></div>' +
            '<div class="ce-skeleton" style="width:60%; height:12px; margin-block-end:30px;"></div>' +
            '<div class="ce-skeleton" style="width:100%; height:12px; margin-block-end:12px;"></div>' +
            '<div class="ce-skeleton" style="width:80%; height:12px;"></div>' +
        '</div>';

    const renderBodyPayload = async (res) => {
        const originalHtml = res.body;
        const metaSafe = encodeURIComponent(JSON.stringify(meta)).replace(/'/g, "%27");

        const isTrash = /trash|deleted|bin|papelera|corbeille|papierkorb|prullenbak/i.test(myCloudEmailState.activeFolder);
        const isDrafts = /drafts?|entw|brouillon/i.test(myCloudEmailState.activeFolder);
        const isSent = /sent|gesendet|enviados|envoy/i.test(myCloudEmailState.activeFolder) || /sent|gesendet|enviados|envoy/i.test(meta.folder);

        const safeSubj = meta.subject ? meta.subject.replace(/'/g, "\\'") : (L.no_subject || '(No Subject)');
        const safeFrom = (meta.fromName ? `${meta.fromName} <${meta.fromEmail}>` : (meta.fromEmail || L.unknown_sender || 'Unknown Sender')).replace(/'/g, "\\'");
        const safeDate = meta.date ? meta.date.replace(/'/g, "\\'") : '';
        const safeTo = meta.to ? meta.to.replace(/'/g, "\\'") : (myCloudEmailState.accounts[targetAcc] ? myCloudEmailState.accounts[targetAcc].email.replace(/'/g, "\\'") : '');
        const safeCc = meta.cc ? meta.cc.replace(/'/g, "\\'") : '';

        // --- ACTIVE THREAT HEURISTICS ---
        const isPhish = res.is_phishing || (res.spam_score !== null && res.spam_score >= 7.0);
        
        const replyToRaw = res.reply_to || '';
        let replyToEmail = '';
        if (replyToRaw) {
            const rMatch = replyToRaw.match(/<([^>]+)>/);
            replyToEmail = (rMatch ? rMatch[1] : replyToRaw).trim().toLowerCase();
        }
        const fromEmailClean = (meta.fromEmail || '').toLowerCase();
        const isBec = replyToEmail && replyToEmail !== fromEmailClean;
        
        let isBayesian = false;

        const isMyEmail = (email) => {
            if (!email) return false;
            const cleanEmail = email.toLowerCase().trim();
            return Object.keys(myCloudEmailState.accounts).some(aId => {
                const a = myCloudEmailState.accounts[aId];
                if (a.is_inactive) return false;
                if (a.email.toLowerCase().trim() === cleanEmail) return true;
                if (a.aliases) {
                    const aliases = typeof a.aliases === 'string' ? JSON.parse(a.aliases) : a.aliases;
                    return aliases.some(al => (typeof al === 'object' ? al.email : al).toLowerCase().trim() === cleanEmail);
                }
                return false;
            });
        };
        const canResend = isMyEmail(meta.fromEmail);

        if (meta.subject && meta.subject.includes('\u200B')) isBayesian = true;

        let isSuspect = isPhish || isBec || isBayesian;

        // --- THREAT INTELLIGENCE UI ---
        let threatBannerHtml = '';
        let threatBadge = '';
        if (!isSent && !isDrafts) {
            if (isPhish) {
            threatBannerHtml = 
                    '<div style="background:var(--danger, #e81123); color:#fff; padding:12px 20px; display:flex; align-items:flex-start; gap:12px; margin: 15px 20px 0 20px; border-radius:4px; box-shadow:0 2px 8px rgba(232, 17, 35, 0.3);">' +
                        '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>' +
                        '<div style="min-width:0; overflow-wrap:break-word; word-break:break-word;">' +
                            '<b style="display:block; font-size:14px; margin-bottom:4px;">' + (L.threat_warning_title || 'Caution: Suspected Phishing or Spam') + '</b>' +
                            '<span style="font-size:12px; opacity:0.9;">' + (L.threat_warning_msg || 'The server security gateway has flagged this message as malicious. Do not click any links or download attachments unless you are absolutely certain this is safe.') + '</span>' +
                        '</div>' +
                    '</div>';
            }
            if (res.spam_score !== null) {
                let scoreColor = 'var(--text-secondary)';
                if (res.spam_score > 4.0) scoreColor = '#fbc02d'; // Yellow
                if (res.spam_score >= 7.0) scoreColor = '#e81123'; // Red
                threatBadge = '<span title="' + (L.spam_score_lbl || 'Spam Filter Score') + '" style="background:var(--gray-10); border:1px solid var(--border-medium); color:' + scoreColor + '; padding:2px 6px; border-radius:4px; font-size:11px; font-weight:bold; margin-inline-start:8px; cursor: pointer;">' + (L.score || 'Score') + ': ' + res.spam_score + '</span>';
            }
        }

        let transportBadge = '';
        let trustBadge = '';
        
        // Inbound headers (Authentication & Transport) are generally irrelevant/missing for outbound folders
        if (!isSent && !isDrafts) {
            
            const tScore = res.trust_score || 'unknown';
            const tSec = res.transport_sec || 'none';
            
            let showFailBadge = true;
            if (tScore === 'fail' && tSec === 'internal') {
                showFailBadge = false;
            }

            if (tScore === 'bimi') trustBadge = '<span title="' + (L.trust_bimi || 'Verified Sender (BIMI)') + '" style="background:' + (isSuspect ? 'var(--gray-50)' : '#004d00') + '; color:#fff; padding:2px 6px; border-radius:4px; font-size:11px; font-weight:bold; margin-inline-start:8px; cursor: pointer;">✓' + '</span>';
            else if (tScore === 'perfect') trustBadge = '<span title="' + (L.trust_perfect || 'Passed DMARC & SPF') + '" style="background:' + (isSuspect ? 'var(--gray-50)' : '#107c10') + '; color:#fff; padding:2px 6px; border-radius:4px; font-size:11px; font-weight:bold; margin-inline-start:8px; cursor: pointer;">✓' + '</span>';
            else if (tScore === 'good') trustBadge = '<span title="' + (L.trust_good || 'Passed Partial Authentication') + '" style="background:' + (isSuspect ? 'var(--gray-50)' : '#fbc02d') + '; color:' + (isSuspect ? '#fff' : '#000') + '; padding:2px 6px; border-radius:4px; font-size:11px; font-weight:bold; margin-inline-start:8px; cursor: pointer;">' + (isSuspect ? (L.auth_sender || 'Authenticated') : ' ❓❓ ') + '</span>';
            else if (tScore === 'fail' && showFailBadge) trustBadge = '<span title="' + (L.trust_fail || 'Authentication Failed') + '" style="background:#e81123; color:#fff; padding:2px 6px; border-radius:4px; font-size:11px; font-weight:bold; margin-inline-start:8px; cursor: pointer;">  ' + (L.trust_untrusted || 'Untrusted') + '</span>';

//            if (tSec === 'dane') {
//                 transportBadge = '<span title="' + (L.sec_dane || 'Transport secured and verified via DNSSEC (DANE)') + '" style="background:' + (isSuspect ? 'var(--gray-50)' : '#c0d8c0') + '; color:#000; padding:2px 6px; border-radius:4px; font-size:15px; font-weight:bold; margin-inline-start:8px; cursor: pointer;">📤-🔒->📥<span style="font-size:9px; vertical-align:super;">DANE</span></span>';
//            } else if (tSec === 'tls') {
//                transportBadge = '<span title="' + (L.sec_tls || 'Transport secured via Standard TLS') + '" style="background:' + (isSuspect ? 'var(--gray-50)' : '#c0d8c0') + '; color:#000; padding:2px 6px; border-radius:4px; font-size:15px; font-weight:bold; margin-inline-start:8px; cursor: pointer;">📤-🔒->📥</span>';
//            } else if (tSec === 'internal') {
//                transportBadge = '<span title="' + (L.sec_internal || 'Message routed internally within the server') + '" style="background:#c0d8c0; color:var(--text-primary); padding:2px 6px; border-radius:4px; font-size:14px; font-weight:bold; margin-inline-start:8px; border:1px solid var(--border-medium); cursor: pointer;">✔️ ' + (L.internal || 'Internal') + '</span>';
//            } else {
//                transportBadge = '<span title="' + (L.sec_none || 'Message transported unencrypted over the internet') + '" style="background:#e81123; color:#fff; padding:2px 6px; border-radius:4px; font-size:14px; font-weight:bold; margin-inline-start:8px; cursor: pointer;">📤-🔓->📥 ' + (L.unencrypted || 'Unencrypted') + '</span>';
//            }
        }

        const renderAddressPill = (rawAddr, isFromBec = false, becMsg = '') => {
            if (!rawAddr) return '';
            const addrs = rawAddr.split(',').map(a => a.trim()).filter(Boolean);
            let html = '';
            addrs.forEach(addr => {
                let name = addr;
                let email = addr;
                const match = addr.match(/^(.*)\s*<([^>]+)>$/);
                if (match) {
                    name = match[1].trim().replace(/['"]/g, '');
                    email = match[2].trim();
                    if (name.toLowerCase() === email.toLowerCase()) name = '';
                } else if (addr.includes('@')) {
                    email = addr;
                    name = '';
                }
                const displayTxt = name && name !== email ? name : email;
                const safeName = myCloudEscapeHtml(name).replace(/\\/g, "\\\\").replace(/'/g, "\\'");
                const safeEmail = '<br><i><b>' + myCloudEscapeHtml(email).replace(/\\/g, "\\\\").replace(/'/g, "\\'") + '<br></b></i>';
                const styleStr = isFromBec ? 'color: var(--danger);' : 'color: inherit;';
                const popupExtra = isFromBec ? '<div style="color:var(--danger); margin-top:6px; padding-top:6px; border-top:1px solid rgba(232,17,35,0.2); font-size:10px; font-weight:normal; line-height:1.3; max-width: 350px; word-break:break-word; overflow-wrap:break-word; white-space:normal;">' + becMsg.replace(/'/g, "\\'") + '</div>' : '';
                
                html += '<div class="ce-email-addr-pill" style="' + styleStr + '" tabindex="0" onmouseenter="window._emailShowPopup(event, \'' + safeName + '\', \'' + safeEmail + '\', \'' + btoa(unescape(encodeURIComponent(popupExtra))) + '\')" onmouseleave="window._emailHidePopup()"><span class="ce-email-pill-label">' + myCloudEscapeHtml(displayTxt) + '</span></div>';
            });
            return html;
        };

        let restoreBtnHtml = '';
        let delLabel = L.delete || 'Delete';
        let delClass = 'owa-btn owa-danger';
        
        if (isTrash) {
            restoreBtnHtml = 
                    '<button class="owa-btn" title="' + (L.restore || 'Restore') + '" onclick="myCloudEmailAction(\'restore\', \''+msgId+'\', \''+metaSafe+'\')">' +
                        '<span class="owa-icon"><svg viewBox="0 0 24 24"><path d="M13 3c-4.97 0-9 4.03-9 9H1l3.89 3.89.07.14L9 12H6c0-3.87 3.13-7 7-7s7 3.13 7 7-3.13 7-7 7c-1.93 0-3.68-.79-4.94-2.06l-1.42 1.42C8.27 19.99 10.51 21 13 21c4.97 0 9-4.03 9-9s-4.03-9-9-9zm-1 5v5l4.28 2.54.72-1.21-3.5-2.08V8H12z"/></svg></span>' +
                    '<span class="owa-label ce-label-tier-2">' + (L.restore || 'Restore') + '</span>' +
                    '</button>';
            delLabel = L.delete_perm || 'Delete Forever';
        }

        let replyBtnHtml = '';
        if (window.myCloudActionAllowed('email_send')) {
            if (isDrafts) {
                replyBtnHtml = '<button class="owa-btn" title="' + (L.edit_draft || 'Edit Draft') + '" onclick="myCloudEmailAction(\'edit_draft\', \''+msgId+'\', \''+metaSafe+'\')">' +
                                        '<span class="owa-icon"><svg viewBox="0 0 24 24"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg></span>' +
                                        '<span class="owa-label ce-label-tier-4">' + (L.edit_draft || 'Edit Draft') + '</span></button>';
            } else {
                replyBtnHtml = '<button class="owa-btn" title="' + (L.reply || 'Reply') + '" onclick="myCloudEmailAction(\'reply\', \''+msgId+'\', \''+metaSafe+'\')">' +
                                        '<span class="owa-icon"><svg viewBox="0 0 24 24"><polyline points="9 17 4 12 9 7"></polyline><path d="M20 18v-2a4 4 0 0 0-4-4H4"></path></svg></span>' +
                                        '<span class="owa-label ce-label-tier-4">' + (L.reply || 'Reply') + '</span></button>' +
                                        '<button class="owa-btn" title="' + (L.reply_all || 'Reply All') + '" onclick="myCloudEmailAction(\'reply_all\', \''+msgId+'\', \''+metaSafe+'\')">' +
                                        '<span class="owa-icon"><svg viewBox="0 0 24 24"><polyline points="7 17 2 12 7 7"></polyline><polyline points="12 17 7 12 12 7"></polyline><path d="M22 18v-2a4 4 0 0 0-4-4H7"></path></svg></span>' +
                                        '<span class="owa-label ce-label-tier-4">' + (L.reply_all || 'Reply All') + '</span></button>' +
                                        '<button class="owa-btn" title="' + (L.forward || 'Forward') + '" onclick="myCloudEmailAction(\'forward\', \''+msgId+'\', \''+metaSafe+'\')">' +
                                        '<span class="owa-icon"><svg viewBox="0 0 24 24"><polyline points="15 17 20 12 15 7"></polyline><path d="M4 18v-2a4 4 0 0 1 4-4h12"></path></svg></span>' +
                                        '<span class="owa-label ce-label-tier-4">' + (L.forward || 'Forward') + '</span></button>';
                if (canResend) {
                    replyBtnHtml += '<button class="owa-btn" title="' + (L.resend || 'Resend') + '" onclick="myCloudEmailAction(\'resend\', \''+msgId+'\', \''+metaSafe+'\')">' +
                                    '<span class="owa-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg></span>' +
                                    '<span class="owa-label ce-label-tier-4">' + (L.resend || 'Resend') + '</span></button>';
                }
            }
        }
        let unsubHtml = '';
        if (res.unsubscribe) {
            let targetLink = null;
            let isMailto = false;

            const matches = res.unsubscribe.match(/<([^>]+)>|(https?:\/\/[^\s,]+)|(mailto:[^\s,]+)/ig);
            if (matches) {
                for (let i = 0; i < matches.length; i++) {
                    let link = matches[i].replace(/[<>]/g, '').trim();
                    if (link.toLowerCase().startsWith('http:') || link.toLowerCase().startsWith('https:')) {
                        targetLink = link; isMailto = false; break; 
                    } else if (link.toLowerCase().startsWith('mailto:') && !targetLink) {
                        targetLink = link; isMailto = true;
                    }
                }
            }
            if (targetLink) {
                const safeLink = targetLink.replace(/"/g, '&quot;').replace(/'/g, "\\'");
                const actionClick = isMailto 
                    ? "if(typeof myCloudShowEmailComposer === 'function'){ myCloudShowEmailComposer({to: '" + safeLink.substring(7) + "', subject: 'Unsubscribe'}); } else { window.location.href = '" + safeLink + "'; }"
                    : "window.open('" + safeLink + "', '_blank', 'noopener,noreferrer')";

                unsubHtml = 
                    '<button class="owa-btn" onclick="' + actionClick + '" style="height:22px; padding:0 6px; border:1px solid var(--border-medium); border-radius:4px; margin-inline-start:6px; font-size:11px; color:var(--text-secondary);" title="' + (L.unsubscribe || 'Unsubscribe') + '">' +
                        '<span class="owa-icon" style="margin-inline-end:4px;"><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line></svg></span>' +
                        '<span class="hide-mobile">' + (L.unsubscribe || 'Unsubscribe') + '</span>' +
                    '</button>';
            }
        }

        if (!window.DOMPurify) {
            await new Promise((res) => { 
                const s = document.createElement('script'); 
                s.src = '/script/dompurify/purify.min.js'; 
                s.onload = res; 
                document.head.appendChild(s); 
            });
        }

        // Make sure of Indirect Prompt Injection (IDPI) protection by stripping invisible elements
        window.DOMPurify.addHook('uponSanitizeAttribute', function (node, data) {
            if (data.attrName === 'style') {
                let style = data.attrValue.toLowerCase().replace(/\s+/g, '');
                if (style.includes('display:none') || 
                    style.includes('visibility:hidden') || 
                    style.includes('opacity:0') || 
                    style.includes('font-size:0') || 
                    style.includes('color:transparent')) {
						node.removeAttribute('style');
						if (node.tagName && node.tagName.toLowerCase() === 'style') {
							node.innerHTML = '';
						}
                }
            }
        });
        const cleanHtml = window.DOMPurify.sanitize(originalHtml, {
            FORBID_TAGS: ['script', 'link', 'iframe', 'object', 'embed', 'applet', 'meta', 'base', 'video', 'audio', 'source', 'track', 'picture', 'form', 'math', 'frameset', 'frame'],
            ALLOW_DATA_ATTR: false,
            WHOLE_DOCUMENT: true
        });
		
		window.DOMPurify.removeHook('uponSanitizeAttribute');

        const parser = new DOMParser();
        const doc = parser.parseFromString(cleanHtml, 'text/html');

        let hasExternalImages = false;

        const senderDomain = (meta.fromEmail || '').split('@').pop().toLowerCase();
        const devKey = typeof myCloudGetCurrentDeviceKey === 'function' ? myCloudGetCurrentDeviceKey() : 'desktop';
        if (myCloudState.settings && myCloudState.settings[devKey] && !myCloudState.settings[devKey].trustedEmailDomains) myCloudState.settings[devKey].trustedEmailDomains = [];
        const isTrusted = myCloudState.settings && myCloudState.settings[devKey] && myCloudState.settings[devKey].trustedEmailDomains.includes(senderDomain);

        // Initialize proxy logic before DOM traversal
        const useProxy = (typeof window.myCloudEmailProxyEnabled !== 'undefined') ? window.myCloudEmailProxyEnabled : true;
        const proxyUrl = (url) => {
            if (!useProxy || !url.match(/^https?:\/\//i)) return url;
            return '?myCloud_email_proxy_img=' + encodeURIComponent(btoa(url)) + '&proxy_token=' + window.myCloudCsrfToken;
        };

        doc.querySelectorAll('style').forEach(style => {
            let css = style.innerHTML;
            css = css.replace(/@import\s+(?:url\()?['"]?[^'")]*['"]?\)?\s*;/gi, '');
            if (css.match(/url\(['"]?(?!data:|cid:)[^)'"]+['"]?\)/i)) {
                style.setAttribute('data-safe-style', css);
                if (useProxy) {
                    css = css.replace(/url\(['"]?(https?:\/\/[^)'"]+)['"]?\)/gi, (match, url) => {
                        return 'url("' + proxyUrl(url) + '")';
                    });
                } else if (!isTrusted) {
                    css = css.replace(/url\(['"]?(?!data:|cid:)[^)'"]+['"]?\)/gi, 'url(data:image/gif;base64,R0lGODlhAQABAIAAAP///wAAACwAAAAAAQABAAACAkQBADs=)');
                    hasExternalImages = true;
                }
            }
            style.innerHTML = css;
        });

        doc.querySelectorAll('*').forEach(el => {
            Array.from(el.attributes).forEach(attr => {
                const attrName = attr.name.toLowerCase();
                if (attrName.startsWith('on') || attrName === 'xml:base') {
                    el.removeAttribute(attr.name);
                } else if (attrName === 'background') {
                    el.setAttribute('data-safe-background', attr.value);
                    if (useProxy) {
                        el.setAttribute('background', proxyUrl(attr.value));
                    } else if (!isTrusted) {
                        el.removeAttribute(attr.name);
                        hasExternalImages = true;
                    }
                }
            });

            if (el.hasAttribute('style')) {
                const origStyle = el.getAttribute('style');
                const styleStr = origStyle.toLowerCase();
                if (styleStr.includes('expression(') || styleStr.includes('javascript:') || styleStr.includes('behavior:')) {
                    el.removeAttribute('style');
                } else if (styleStr.includes('url(') || styleStr.includes('@import')) {
                    el.setAttribute('data-safe-style', origStyle);
                    if (useProxy) {
                        const safeStyle = origStyle.replace(/url\(['"]?(https?:\/\/[^)'"]+)['"]?\)/gi, (match, url) => {
                            return 'url("' + proxyUrl(url) + '")';
                        });
                        el.setAttribute('style', safeStyle);
                    } else if (!isTrusted) {
                        const safeStyle = origStyle.replace(/url\(['"]?[^)'"]+['"]?\)/gi, 'url(data:image/gif;base64,R0lGODlhAQABAIAAAP///wAAACwAAAAAAQABAAACAkQBADs=)');
                        el.setAttribute('style', safeStyle);
                        hasExternalImages = true;
                    }                  
                 }
            }

            if (el.tagName.toLowerCase() === 'img') {
                const src = el.getAttribute('src');

                // Anti-Tracking: Detect CSS-hidden and attribute-hidden micro-pixels
                const styleClean = (el.getAttribute('style') || '').toLowerCase().replace(/\s+/g, '');
                const wAttr = parseInt(el.getAttribute('width') || '999', 10);
                const hAttr = parseInt(el.getAttribute('height') || '999', 10);
                
                const isCssHidden = styleClean.includes('opacity:0') || 
                                    styleClean.includes('display:none') || 
                                    styleClean.includes('visibility:hidden') || 
                                    styleClean.includes('width:2px') || 
                                    styleClean.includes('height:2px') ||
                                    styleClean.includes('width:1px') || 
                                    styleClean.includes('height:1px') ||
                                    styleClean.includes('width:0') || 
                                    styleClean.includes('height:0');
                                    
                const isAttrHidden = wAttr <= 2 || hAttr <= 2;

                if (isCssHidden || isAttrHidden) {
                    // Permanently neutralize the tracker. Do not attach data-safe-src so it ignores the "Load Images" button.
                    el.setAttribute('src', 'data:image/gif;base64,R0lGODlhAQABAIAAAP///wAAACwAAAAAAQABAAACAkQBADs=');
                    el.setAttribute('data-tracker-blocked', 'true');
                } else if (src && !src.startsWith('data:') && !src.startsWith('cid:')) {
                    el.setAttribute('data-safe-src', src);
                    if (useProxy) {
                        el.setAttribute('src', proxyUrl(src));
                    } else if (!isTrusted) {
                        el.setAttribute('src', 'data:image/gif;base64,R0lGODlhAQABAIAAAP///wAAACwAAAAAAQABAAACAkQBADs=');
                        const origStyle = el.getAttribute('style') || '';
                        const origHeight = el.getAttribute('height') || '';
                        el.setAttribute('data-orig-style', origStyle);
                        el.setAttribute('data-orig-height', origHeight);
                        el.style.border = '1px dashed #ccc';
                        el.style.height = '24px';
                        el.style.width = '24px';
                        el.style.minHeight = '0';
                        el.style.display = 'inline-block';
                        el.removeAttribute('height');
                        hasExternalImages = true;
                    }                   
                }
            }

            if (el.tagName.toLowerCase() === 'a' || el.tagName.toLowerCase() === 'area') {
                const href = el.getAttribute('href');
                if (href) {
                    const cleanHref = href.toLowerCase().replace(/[\x00-\x20\x7F]/g, '').trim();
                    if (cleanHref.startsWith('javascript:') || cleanHref.startsWith('vbscript:') || cleanHref.startsWith('data:')) {
                        el.removeAttribute('href');
                    } else if (href.startsWith('http://') || href.startsWith('https://') || href.startsWith('//')) {
                        el.setAttribute('data-safe-href', href);
                        el.setAttribute('href', 'javascript:void(0);');
                        el.removeAttribute('target');
                    } else if (cleanHref.startsWith('mailto:')) {
                        el.setAttribute('data-safe-href', href);
                        el.setAttribute('href', 'javascript:void(0);');
                    } else {
                        el.removeAttribute('href');
                    }
                }
            }
        });

        let loadImgBtnHtml = '';
        if (hasExternalImages && !isTrusted) {
            loadImgBtnHtml = '<button id="ceLoadEmailImgBtn" class="owa-btn" title="' + (L.load_pictures || 'Load External Images') + '">' +
                '<span class="owa-icon"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg></span>' +
            '<span class="owa-label ce-label-tier-1">' + (L.load_pictures || 'Load Images') + '</span>' +
            '</button>' +
            '<button id="ceTrustDomainBtn" class="owa-btn" title="' + (L.always_trust || 'Always trust') + ' @' + senderDomain + '">' +
            '<span class="owa-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><polyline points="9 12 11 14 15 10"></polyline></svg></span>' +
            '<span class="owa-label ce-label-tier-2">' + (L.always_trust || 'Always trust') + ' @' + senderDomain + '</span>' +
            '</button>';
        }

        const tbHtml = 
        '<style>#ceEmlReadToolbar .owa-btn { flex-shrink: 0 !important; } @media (min-width: 768px) { .ce-email-mobile-only { display: none !important; } }</style>' +
        '<div class="myCloudToolbar-wrapper" id="ceEmlReadToolbarWrap" style="flex-shrink:0; overflow:hidden; width:100%;">' +
            '<div class="owa-toolbar" id="ceEmlReadToolbar" style="display:flex; justify-content:space-between; align-items:center; width:100%; flex-wrap:nowrap; gap:8px;">' +
                '<div class="ce-email-tb-left" style="display:flex; align-items:center; flex-shrink:0; gap:4px;">' + mobileBackBtn + prevBtn + nextBtn + mobileDivider + replyBtnHtml + '</div>' +
                '<div class="ce-email-tb-middle" style="display:flex; align-items:center; justify-content:center; flex-shrink:0; gap:4px;">' + loadImgBtnHtml + '</div>' +
                '<div class="ce-email-tb-right" style="display:flex; align-items:center; justify-content:flex-end; flex-shrink:0; gap:4px;">' +
                '<button class="owa-btn" title="' + (L.save || 'Save') + '" onclick="window._emailShowSaveOptions(\'' + targetAcc + '\', \'' + targetFolder + '\', \'' + msgId + '\')">' +
                    '<span class="owa-icon"><svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg></span>' +
                '<span class="owa-label ce-label-tier-3">' + (L.save || 'Save') + '</span>' +
                '</button>' +
                '<div class="owa-divider ce-divider-tier-collapse"></div>' +
                '<button class="owa-btn" title="' + (L.raw_source || 'Raw Source') + '" onclick="document.getElementById(\'ceEmlFlipper\').classList.toggle(\'flipped\')">' +
                    '<span class="owa-icon"><svg viewBox="0 0 24 24"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg></span>' +
                    '<span class="owa-label ce-label-tier-1">' + (L.raw_source || 'Raw Source') + '</span>' +
                '</button>' +
                '<div class="owa-divider ce-divider-tier-collapse"></div>' +
                restoreBtnHtml +
                '<button class="' + delClass + '" title="' + delLabel + '" onclick="myCloudEmailAction(\'delete\', \''+msgId+'\', \''+metaSafe+'\')">' +
                    '<span class="owa-icon"><svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg></span>' +
                    '<span class="owa-label ce-label-tier-2">' + delLabel + '</span>' +
                '</button>' +
                '</div>' +
            '</div>' +
        '</div>';

        let attHtml = '';
        if (res.attachments && res.attachments.length > 0) {
            attHtml = '<div class="ce-email-attachments">';
            res.attachments.forEach(att => {
                const safeName = myCloudEscapeHtml(att.filename);
                const attExt = att.filename.split('.').pop().toLowerCase();
                const isPrev = typeof myCloudIsPreviewable === 'function' && myCloudIsPreviewable(attExt);

                const doubleExtRegex = /\.[a-z0-9]+\.(exe|js|vbs|bat|cmd|ps1|scr|wsf)$/i;
                const isMaliciousExt = doubleExtRegex.test(att.filename);
                
                attHtml += 
                    '<div class="ce-attachment-pill-wrap" style="display:inline-flex; align-items:stretch; background-color: var(--gray-10); border: 1px solid var(--border-default); border-radius: 4px; overflow:hidden; max-width:100%; box-sizing:border-box; margin-bottom:4px; margin-right:4px;">' +
                        '<div style="padding:4px 10px; display:inline-flex; align-items:center; gap:6px; font-size:13px; font-family:inherit; color:var(--text-primary); border-right:1px solid var(--border-default);">' +
                            '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; opacity:0.6;"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path></svg>' +
                            '<span style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:200px; font-weight:' + (isMaliciousExt ? 'bold' : '500') + ';' + (isMaliciousExt ? 'color:var(--danger);' : '') + '">' + safeName + '</span>' +
                            '<span style="color:var(--text-secondary); font-size:11px; margin-left:4px; flex-shrink:0;">' + myCloudFormatBytes(att.size) + '</span>' +
                        '</div>';

                if (isMaliciousExt) {
                     attHtml += '<button type="button" title="' + (L.blocked_malicious || 'Blocked: Malicious File Type') + '" disabled style="background:var(--danger, #e81123); border:none; padding:4px 10px; cursor:not-allowed; color:#fff; display:inline-flex; align-items:center; font-weight:bold; font-size:11px; height:auto;">' +
                                '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line></svg>' + (L.blocked || 'Blocked') + '</button>';
                } else {
                     attHtml += '<button type="button" title="' + (L.download_device || 'Download to device') + '" onclick="window._emailDownloadAttachment(\'' + targetAcc + '\', \'' + targetFolder + '\', \'' + msgId + '\', \'' + att.part + '\', \'' + safeName.replace(/'/g, "\\'") + '\')" style="background:transparent; border:none; padding:4px 10px; cursor:pointer; color:var(--text-secondary); display:inline-flex; align-items:center; transition:background 0.15s; height:auto;" onmouseover="this.style.backgroundColor=\'var(--gray-20)\'; this.style.color=\'var(--text-primary)\'" onmouseout="this.style.backgroundColor=\'transparent\'; this.style.color=\'var(--text-secondary)\'">' +
                                '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>' +
                                '</button>';
                }
                
                if (isPrev && !isMaliciousExt) {
                    attHtml += 
                        '<button type="button" title="' + (L.preview || 'Preview') + '" onclick="window._emailPreviewAttachment(event, \'' + targetAcc + '\', \'' + targetFolder + '\', \'' + msgId + '\', \'' + att.part + '\', \'' + safeName.replace(/'/g, "\\'") + '\')" style="background:transparent; border:none; border-left:1px solid var(--border-default); padding:4px 10px; cursor:pointer; color:var(--text-secondary); display:inline-flex; align-items:center; transition:background 0.15s; height:auto;" onmouseover="this.style.backgroundColor=\'var(--gray-20)\'; this.style.color=\'var(--text-primary)\'" onmouseout="this.style.backgroundColor=\'transparent\'; this.style.color=\'var(--text-secondary)\'">' +
                            '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>' +
                        '</button>';
                }
                
                const writeableClouds = [];
                if (typeof myCloudCloudConfig !== 'undefined') {
                    for (const [k, c] of Object.entries(myCloudCloudConfig)) {
                        if ((c.interface || 'default') !== 'email' && c.rights !== 'read-only' && c.rights !== 'no-access') writeableClouds.push(k);
                    }
                }
                if (writeableClouds.length > 0) {
                    attHtml += 
                        '<button type="button" title="' + (L.save_to_cloud || 'Save to Cloud') + '" onclick="window._emailSaveAttachmentToCloud(\'' + targetAcc + '\', \'' + targetFolder + '\', \'' + msgId + '\', \'' + att.part + '\', \'' + safeName.replace(/'/g, "\\'") + '\')" style="background:transparent; border:none; border-left:1px solid var(--border-default); padding:4px 10px; cursor:pointer; color:var(--text-secondary); display:inline-flex; align-items:center; transition:background 0.15s; height:auto;" onmouseover="this.style.backgroundColor=\'var(--gray-20)\'; this.style.color=\'var(--text-primary)\'" onmouseout="this.style.backgroundColor=\'transparent\'; this.style.color=\'var(--text-secondary)\'">' +
                            '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM14 13v4h-4v-4H7l5-5 5 5h-3z"/></svg>' +
                        '</button>';
                }

                if (attExt === 'asc' || attExt === 'pgp' || attExt === 'gpg') {
                    attHtml += 
                        '<button type="button" title="' + (L.pgp_add_to_contact || 'Import Key') + '" onclick="window._emailImportAttachedKey(\'' + targetAcc + '\', \'' + targetFolder + '\', \'' + msgId + '\', \'' + att.part + '\', \'' + safeName.replace(/'/g, "\\'") + '\', \'' + (meta.fromEmail || '').toLowerCase() + '\')" style="background:transparent; border:none; border-left:1px solid var(--border-default); padding:4px 10px; cursor:pointer; color:var(--accent-primary); font-weight:600; display:inline-flex; align-items:center; transition:background 0.15s; height:auto;" onmouseover="this.style.backgroundColor=\'var(--gray-20)\'" onmouseout="this.style.backgroundColor=\'transparent\'">' +
                            '🔑 ' + (L.pgp_add_to_contact || 'Import Key') +
                        '</button>';
                }

                attHtml += '</div>';
            });
            attHtml += '</div>';
        }

        let extendedMetaHtml = '';
        const finalTo = meta.to || res.to;
        const finalCc = meta.cc || res.cc;
        const finalBcc = meta.bcc || res.bcc;

        if (finalTo && finalTo.trim() !== '') {
            extendedMetaHtml += '<div style="display:flex; align-items:flex-start; gap:8px;"><b style="margin-block-start:3px; width:40px; flex-shrink:0;">' + (L.to_label || 'To:') + '</b> <div style="display:flex; flex-wrap:wrap; gap:4px; flex:1;">' + renderAddressPill(finalTo) + '</div></div>';
        }
        if (finalCc && finalCc.trim() !== '') {
            extendedMetaHtml += '<div style="display:flex; align-items:flex-start; gap:8px;"><b style="margin-block-start:3px; width:40px; flex-shrink:0;">' + (L.cc_label || 'Cc:') + '</b> <div style="display:flex; flex-wrap:wrap; gap:4px; flex:1;">' + renderAddressPill(finalCc) + '</div></div>';
        }
        if (finalBcc && finalBcc.trim() !== '') {
            extendedMetaHtml += '<div style="display:flex; align-items:flex-start; gap:8px;"><b style="margin-block-start:3px; width:40px; flex-shrink:0;">' + (L.bcc_label || 'Bcc:') + '</b> <div style="display:flex; flex-wrap:wrap; gap:4px; flex:1;">' + renderAddressPill(finalBcc) + '</div></div>';
        }
        
        const match = document.cookie.match(/(^| )myCloudEmlMetaExpanded=([^;]+)/);
        const isExpanded = (match && match[2] === '1');
        const expClass = isExpanded ? ' expanded' : '';

        const fromRaw = meta.fromName ? meta.fromName + ' <' + meta.fromEmail + '>' : meta.fromEmail;
        const rawMessageContent = res.raw_message ? window._emailHighlightRawSource(res.raw_message) : (L.raw_not_avail || 'Raw message not available.');

        const extractedBody = doc.body ? doc.body.innerHTML : cleanHtml;
        const processedHtmlString = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{margin:0;padding:15px;font-family:Arial,sans-serif;font-size:14px;color:#333;background:#ffffff;overflow-wrap:break-word;} a{color:var(--accent-primary, #0078d4);}</style></head><body>' + extractedBody + '</body></html>';
        
        const escapeSrcDoc = (str) => {
            return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        };
        
        const iframeSandboxedHtml = '<iframe id="ceEmailIframeContent" sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox" srcdoc="' + escapeSrcDoc(processedHtmlString) + '" style="width:100%; height:100%; border:none; background:#ffffff; display:block; flex:1;"></iframe>';
        
        reading.innerHTML =
            tbHtml +
            '<div class="ce-email-flipper-wrap" style="flex:1; min-height:0; display:flex; flex-direction:column; height:100%;">' +
                '<div id="ceEmlFlipper" class="ce-email-flipper" style="flex:1; min-height:0; height:100%;">' +
                    '<div class="ce-email-front" style="display:flex; flex-direction:column; height:100%;">' +
                        '<div class="ce-email-read-header ce-selectable">' +
                            '<h2 class="ce-email-read-subject" style="margin-block-start:0;">' + myCloudEscapeHtml(meta.subject) + '</h2>' +
                            '<div class="ce-email-read-meta">' +
                                '<div style="display:flex; align-items:flex-start; gap:8px; flex:1; min-width:0;">' +
                                    '<b style="margin-block-start:3px; width:40px; flex-shrink:0;">' + (L.from_label || 'From:') + '</b>' +
                                    '<div style="display:flex; align-items:center; flex-wrap:wrap; gap:4px; flex:1;">' + renderAddressPill(fromRaw, isBec, isBec ? (L.bec_warn_msg || 'If you reply to this email, it will be sent to <b>%s</b>, NOT to the sender shown.').replace('%s', myCloudEscapeHtml(replyToEmail)) : '') + transportBadge + trustBadge + threatBadge + unsubHtml + '</div>' +
                                    '<button id="ceEmlMetaToggle" class="ce-email-meta-toggle' + expClass + '" title="' + (L.eml_toggle_details || 'Toggle details') + '">' +
                                        '<svg viewBox="0 0 24 24"><path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6 6 1.41-1.41z"/></svg>' +
                                    '</button>' +
                                '</div>' +
                                '<span style="margin-block-start:3px;">' + meta.date + '</span>' +
                            '</div>' +
                            '<div id="ceEmlMetaExtended" class="ce-email-read-meta-extended' + expClass + '">' + extendedMetaHtml + '</div>' +
                            threatBannerHtml + attHtml + 
                        '</div>' +
                        '<div class="ce-email-body-content" id="ceEmailContentWrapper" style="flex:1; display:flex; flex-direction:column; min-height:0;">' + iframeSandboxedHtml + '</div>' +
                    '</div>' +
                    '<div class="ce-email-back" style="display:flex; flex-direction:column; height:100%; pointer-events:auto;">' +
                        '<pre class="ce-selectable" onwheel="this.scrollTop += event.deltaY; if(event.deltaY !== 0) { event.preventDefault(); event.stopPropagation(); }" style="flex:1; min-height:0; box-sizing:border-box; margin:20px; font-family:monospace; font-size:12px; white-space:pre-wrap; word-break:break-all; overflow-y:auto; overflow-x:hidden; background: var(--gray-05); border: 1px solid var(--border-default); border-radius: 4px; padding: 15px; transform: translateZ(1px);">' + rawMessageContent + '</pre>' +
                    '</div>' +
                '</div>' +
            '</div>';

        const metaToggleBtn = document.getElementById('ceEmlMetaToggle');
        const metaExtended = document.getElementById('ceEmlMetaExtended');
        if (metaToggleBtn && metaExtended) {
            metaToggleBtn.onclick = () => {
                const isNowExpanded = metaExtended.classList.toggle('expanded');
                metaToggleBtn.classList.toggle('expanded');
                document.cookie = "myCloudEmlMetaExpanded=" + (isNowExpanded ? "1" : "0") + "; path=/; max-age=31536000; SameSite=Lax";
            };
        }

        const iframeEl = document.getElementById('ceEmailIframeContent');
        if (iframeEl) {
            iframeEl.onload = () => {
                const iframeDoc = iframeEl.contentDocument || iframeEl.contentWindow.document;
                if (!iframeDoc) return;

                // SECURE ZOOM: Attach listeners from the parent context, requiring no execution permissions inside the iframe payload.
                let iframeScale = 1;
                let iframeStartDist = 0;
                
                iframeDoc.addEventListener("wheel", e => {
                    if (e.ctrlKey || e.metaKey) {
                        e.preventDefault();
                        iframeScale += e.deltaY > 0 ? -0.15 : 0.15;
                        iframeScale = Math.max(0.3, Math.min(iframeScale, 5));
                        iframeDoc.body.style.transformOrigin = "top left";
                        iframeDoc.body.style.transform = "scale(" + iframeScale + ")";
                        iframeDoc.body.style.width = (100 / iframeScale) + "%";
                    }
                }, {passive: false});
                
                iframeDoc.addEventListener("touchstart", e => { 
                    if (e.touches.length === 2) iframeStartDist = Math.hypot(e.touches[0].pageX - e.touches[1].pageX, e.touches[0].pageY - e.touches[1].pageY); 
                }, {passive: false});
                
                iframeDoc.addEventListener("touchmove", e => {
                    if (e.touches.length === 2) {
                        e.preventDefault();
                        let dist = Math.hypot(e.touches[0].pageX - e.touches[1].pageX, e.touches[0].pageY - e.touches[1].pageY);
                        iframeScale += (dist - iframeStartDist) * 0.01; iframeScale = Math.max(0.3, Math.min(iframeScale, 5));
                        iframeDoc.body.style.transformOrigin = "top left"; iframeDoc.body.style.transform = "scale(" + iframeScale + ")"; iframeDoc.body.style.width = (100 / iframeScale) + "%"; iframeStartDist = dist;
                    }
                }, {passive: false});

               iframeDoc.addEventListener('click', (e) => {
                    const anchor = e.target.closest('a[data-safe-href]');
                    if (anchor) {
                        e.preventDefault();
                        e.stopPropagation();
                        const safeHref = anchor.getAttribute('data-safe-href');

                        if (safeHref.toLowerCase().startsWith('mailto:')) {
                            const raw = safeHref.substring(7);
                            const parts = raw.split('?');
                            const toAddress = decodeURIComponent(parts[0]);
                            let subject = '', body = '';
                            if (parts.length > 1) {
                                const params = new URLSearchParams(parts[1]);
                                subject = params.get('subject') || '';
                                body = params.get('body') || '';
                            }
                            if (typeof myCloudShowEmailComposer === 'function') {
                                if (body && window.DOMPurify) {
                                    body = window.DOMPurify.sanitize(body, { FORBID_TAGS: ['script', 'iframe', 'object', 'embed', 'applet', 'meta', 'base'], ALLOW_DATA_ATTR: false });
                                }
                                myCloudShowEmailComposer({ to: toAddress, subject: subject, body: body });
                            }
                            return;
                        }

                        try {
                            const urlObj = new URL(safeHref, window.location.origin);
                             
                             // 1. Phishing & Spoofing Heuristics
                             const senderDomain = (meta.fromEmail || '').includes('@') ? (meta.fromEmail || '').split('@').pop().toLowerCase() : '';
                             const linkDomain = urlObj.hostname.toLowerCase();
                             let isHomograph = false;
                             let isMismatch = false;
                             
                             // A. Punycode & Homograph Detection
                             const rawHrefMatch = safeHref.match(/^https?:\/\/([^/?#]+)/i);
                             const rawDomain = rawHrefMatch ? decodeURIComponent(rawHrefMatch[1]) : linkDomain;
                             
                             if (linkDomain.includes('xn--')) {
                                 // Flag if domain contains Cyrillic or Greek (Primary homograph vectors)
                                 if (/[\u0400-\u04FF\u0370-\u03FF]/.test(rawDomain)) {
                                     isHomograph = true;
                                 } else {
                                     // Flag if mixing Basic Latin (English) with Foreign Scripts (ignoring allowed CJK/Arabic/Umlauts)
                                     const hasBasicLatin = /[a-zA-Z]/.test(rawDomain);
                                     const hasNonAllowed = /[^\x00-\x7F\u0080-\u024F\u0600-\u06FF\u0590-\u05FF\u4E00-\u9FFF\u3040-\u30FF\uAC00-\uD7AF\.]/.test(rawDomain);
                                     if (hasBasicLatin && hasNonAllowed) isHomograph = true;
                                 }
                             }
                             
                             // B. Cross-Domain Mismatch Detection
                             if (senderDomain && linkDomain) {
                                 // Lightweight Public Suffix logic to extract the Organizational Domain
                                 const getRootDomain = (d) => {
                                     const p = d.split('.');
                                     if (p.length <= 2) return d;
                                     // Account for common ccTLDs (e.g., co.uk, com.au, org.nz)
                                     if (p[p.length - 2].length <= 3 && ['co','com','org','net','edu','gov','ac'].includes(p[p.length - 2])) {
                                         return p.slice(-3).join('.');
                                     }
                                     return p.slice(-2).join('.');
                                 };
                                 
                                 const senderRoot = getRootDomain(senderDomain);
                                 const linkRoot = getRootDomain(linkDomain);

                                 const isSameRoot = senderRoot === linkRoot;
                                 const globalSafeLinks = window.$cloud_mail_safe_mail_domains || ['mailchimp.com', 'list-manage.com', 'sendgrid.net', 'ct.sendgrid.net', 'constantcontact.com', 'hubspot.com', 'marketo.com', 'click.pstmrk.it', 'links.iterable.com', 'awstrack.me', 'mailgun.org', 'sendinblue.com', 'e.customeriomail.com', 'klaviyomail.com', 'mcsv.net', 'rsys2.com'];
                                 const isSafeThirdParty = globalSafeLinks.some(d => linkRoot === getRootDomain(d) || linkDomain.endsWith('.' + d));
                                  
                                 if (!isSameRoot && !isSafeThirdParty) isMismatch = true;
                             }
                             
                             // 2. Dynamic UI Construction
                             let warningHtml = (L.link_warning_msg || 'You are about to open an external link to:') + '<br><br><b style="font-size:16px;">' + myCloudEscapeHtml(linkDomain) + '</b><br><br>';
                             
                             if (isHomograph || isMismatch) {
                                 warningHtml += '<div style="background:var(--danger, #e81123); color:#fff; padding:12px; border-radius:6px; text-align:left; margin-bottom:15px; font-size:13px; line-height:1.4;">';
                                 warningHtml += '<div style="display:flex; align-items:center; gap:8px; margin-bottom:8px; font-weight:bold; font-size:14px;"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>' + (L.sec_warning || 'Security Warning') + '</div>';
                                 warningHtml += '<ul style="margin:0; padding-inline-start:20px;">';
                                 if (isHomograph) warningHtml += '<li><b>' + (L.homograph_warn || 'Deceptive Characters:') + '</b> ' + (L.homograph_desc || 'This link uses hidden foreign characters (Punycode) to impersonate a legitimate website.') + '</li>';
                                 if (isMismatch) warningHtml += '<li style="margin-top:4px;"><b>' + (L.mismatch_warn || 'Domain Mismatch:') + '</b> ' + (L.mismatch_desc || 'The link destination does not match the sender\'s domain (') + myCloudEscapeHtml(senderDomain) + ').</li>';
                                 warningHtml += '</ul></div>';
                             }
                             
                             warningHtml += (L.proceed_ask || 'Do you want to proceed?');

                            myCloudShowAlert(
                                L.link_warning || 'External Link', 
                                warningHtml,
                                () => { window.open(safeHref, '_blank', 'noopener,noreferrer'); }
                            );
                        } catch(err) {
                            myCloudShowAlert(L.error_prefix || 'Error', L.invalid_link || 'Invalid link.');
                        }
                    }
                });

                if (hasExternalImages && !isTrusted) {
                    const imgBtn = document.getElementById('ceLoadEmailImgBtn');
                    const trustBtn = document.getElementById('ceTrustDomainBtn');

                    const useProxy = (typeof window.myCloudEmailProxyEnabled !== 'undefined') ? window.myCloudEmailProxyEnabled : true;
                    const proxyUrl = (url) => {
                        if (!useProxy || !url.match(/^https?:\/\//i)) return url;
                        return '?myCloud_email_proxy_img=' + encodeURIComponent(btoa(url));
                    };

                    
                    const triggerImageLoad = () => {
                        iframeDoc.querySelectorAll('img[data-safe-src]').forEach(img => {
                            img.setAttribute('src', proxyUrl(img.getAttribute('data-safe-src')));
                            const origStyle = img.getAttribute('data-orig-style');
                            if (origStyle !== null) img.setAttribute('style', origStyle);
                            else img.removeAttribute('style');
                            const origHeight = img.getAttribute('data-orig-height');
                            if (origHeight) img.setAttribute('height', origHeight);
                        });
                        iframeDoc.querySelectorAll('[data-safe-style]').forEach(el => {
                            let css = el.getAttribute('data-safe-style');
                            if (useProxy) {
                                css = css.replace(/url\(['"]?(https?:\/\/[^)'"]+)['"]?\)/gi, (match, url) => {
                                    return 'url("' + proxyUrl(url) + '")';
                                });
                            }
                            if (el.tagName.toLowerCase() === 'style') el.innerHTML = css;
                            else el.setAttribute('style', css);
                        });
                        iframeDoc.querySelectorAll('[data-safe-background]').forEach(el => {
                            el.setAttribute('background', proxyUrl(el.getAttribute('data-safe-background')));
                        });
                        if (imgBtn) imgBtn.remove();
                        if (trustBtn) trustBtn.remove();
                    };

                    if (imgBtn) imgBtn.onclick = triggerImageLoad;
                    if (trustBtn) {
                        trustBtn.onclick = () => {
                            if (myCloudState.settings && myCloudState.settings[devKey] && myCloudState.settings[devKey].trustedEmailDomains) {
                                myCloudState.settings[devKey].trustedEmailDomains.push(senderDomain);
                                if (typeof myCloudSaveSettings === 'function') myCloudSaveSettings();
                            }
                            triggerImageLoad();
                        };
                    }
                }
            };
        }

        if (originalHtml.includes('-----BEGIN PGP MESSAGE-----')) {
            const pgpBanner = document.createElement('div');
            pgpBanner.style.cssText = 'background:var(--accent-primary); color:#fff; padding:10px 20px; display:flex; justify-content:space-between; align-items:center; border-radius:4px; margin: 10px 20px;';
            pgpBanner.innerHTML = '<span>' + (L.pgp_msg_banner || '🔒 This message contains PGP encrypted content.') + '</span> <button class="owa-btn" style="background:#fff; color:var(--accent-primary);">' + (L.pgp_decrypt_btn || 'Decrypt Message') + '</button>';
            
            let actualAcc = targetAcc;
            if (actualAcc === 'smartbox') actualAcc = meta.account_id || Object.keys(myCloudEmailState.accounts)[0];
            let accPrivKey = myCloudEmailState.accounts[actualAcc]?.pgp_private_key;

            if (!window.myCloudEmailState.unlockedPgpKeys) {
                window.myCloudEmailState.unlockedPgpKeys = {};
            }

            const sanitizeArmor = (text, headerLabel) => {
                if (!text) return "";
                let s = text.replace(/<br\s*\/?>/gi, '\n').replace(/<\/div>/gi, '\n').replace(/<\/p>/gi, '\n').replace(/<[^>]+>/g, '')
                            .replace(/&nbsp;/g, ' ').replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>')
                            .replace(/\\r\\n/g, '\n').replace(/\\n/g, '\n');
                const header = `-----BEGIN PGP ${headerLabel}-----`;
                const footer = `-----END PGP ${headerLabel}-----`;
                const startIdx = s.indexOf(header);
                const endIdx = s.indexOf(footer);
                if (startIdx === -1 || endIdx === -1) return text.trim();
                
                let rawBlock = s.substring(startIdx + header.length, endIdx).trim();
                let lines = rawBlock.split(/\r?\n/).map(l => l.trim()).filter(l => l.length > 0);
                
                let out = [header];
                let headersDone = false;
                
                for (let i = 0; i < lines.length; i++) {
                    if (!headersDone) {
                        if (/^[a-zA-Z0-9-]+:/.test(lines[i])) {
                            out.push(lines[i]);
                        } else {
                            out.push("");
                            out.push(lines[i]);
                            headersDone = true;
                        }
                    } else {
                        out.push(lines[i]);
                    }
                }
                out.push(footer);
                return out.join('\n');
            };

            const executeDecryption = async (isAuto = false) => {
                if (!accPrivKey) {
                    if (!isAuto) myCloudShowAlert(L.pgp_missing_key_title || 'Missing Key', L.pgp_missing_priv_key_msg || 'No PGP Private Key configured for this account. Please add it in Email Settings.');
                    else document.getElementById('ceEmlFlipper').querySelector('.ce-email-front').insertBefore(pgpBanner, document.getElementById('ceEmailContentWrapper'));
                    return;
                }

                const cleanPrivKey = sanitizeArmor(accPrivKey, "PRIVATE KEY BLOCK");

                if (!window.openpgp) {
                    if (!isAuto) myCloudCreateProgressUI(L.pgp_loading_engine || 'Loading Crypto Engine...');
                    await new Promise((res, rej) => { const s = document.createElement('script'); s.src = '/script/openpgp/openpgp.min.js'; s.onload = res; s.onerror = rej; document.head.appendChild(s); });
                }

                try {
                    const performActualDecryption = async (privKeyObj) => {
                        if (!isAuto) myCloudCreateProgressUI(L.pgp_decrypting || 'Decrypting...');
                        try {
                            const cleanMessage = sanitizeArmor(originalHtml, "MESSAGE");
                            const message = await window.openpgp.readMessage({ armoredMessage: cleanMessage });
                            const { data: decrypted } = await window.openpgp.decrypt({ message, decryptionKeys: privKeyObj });
                            
                            let finalContent = '';
                            if (/<\/?[a-z][\s\S]*>/i.test(decrypted)) {
                                if (window.DOMPurify) {
                                    finalContent = window.DOMPurify.sanitize(decrypted, {
                                        FORBID_TAGS: ['script', 'link', 'iframe', 'object', 'embed', 'applet', 'meta', 'base', 'video', 'audio', 'source', 'track', 'picture', 'form', 'math', 'frameset', 'frame'],
                                        ALLOW_DATA_ATTR: false, WHOLE_DOCUMENT: true
                                    });
                                } else {
                                    finalContent = decrypted;
                                }
                            } else {
                                finalContent = '<pre style="white-space: pre-wrap; font-family: inherit; font-size: 14px; margin: 0;">' + myCloudEscapeHtml(decrypted) + '</pre>';
                            }

                            const injectToIframe = () => {
                                const iframeDoc = iframeEl.contentDocument || iframeEl.contentWindow.document;
                                if (iframeDoc && iframeDoc.body && iframeDoc.body.innerHTML.includes('BEGIN PGP MESSAGE')) {
                                    iframeDoc.body.innerHTML = '<div style="padding: 15px; font-family: sans-serif;">' + finalContent + '</div>';
                                    pgpBanner.remove();
                                    if (!isAuto) myCloudCloseProgressUI();
                                } else {
                                    injectToIframe.attempts = (injectToIframe.attempts || 0) + 1;
                                    if (injectToIframe.attempts > 100) {
                                        if (!isAuto) myCloudCloseProgressUI();
                                        console.warn("Iframe timeout");
                                        return;
                                    }
                                    setTimeout(injectToIframe, 25);
                                }
                            };
                            injectToIframe();
                        } catch(e) {
                            if (!isAuto) {
                                myCloudCloseProgressUI();
                                myCloudShowAlert(L.pgp_decrypt_err || 'Decryption Error', e.message);
                            }
                        }
                    };

                    if (window.myCloudEmailState.unlockedPgpKeys[actualAcc]) {
                        return performActualDecryption(window.myCloudEmailState.unlockedPgpKeys[actualAcc]);
                    }

                    const privateKey = await window.openpgp.readPrivateKey({ armoredKey: cleanPrivKey });

                    if (privateKey.isDecrypted() === false) {
                        if (isAuto && document.getElementById('myCloudProgressPopup')) myCloudCloseProgressUI();
                        myCloudShowPasswordModal(L.pgp_unlock_title || 'PGP Unlock', L.pgp_unlock_msg || 'Enter passphrase to unlock your Private Key:', async (passphrase) => {
                            myCloudCreateProgressUI(L.pgp_unlocking || 'Unlocking Key...');
                            try {
                                const unlockedKey = await window.openpgp.decryptKey({ privateKey: privateKey, passphrase: passphrase });
                                window.myCloudEmailState.unlockedPgpKeys[actualAcc] = unlockedKey;
                                myCloudCloseProgressUI();
                                performActualDecryption(unlockedKey);
                            } catch(e) {
                                myCloudCloseProgressUI();
                                myCloudShowAlert(L.pgp_unlock_failed || 'Unlock Failed', L.pgp_bad_passphrase || 'Incorrect passphrase.');
                                if (isAuto) document.getElementById('ceEmlFlipper').querySelector('.ce-email-front').insertBefore(pgpBanner, document.getElementById('ceEmailContentWrapper'));
                            }
                        }, () => {
                            if (isAuto) document.getElementById('ceEmlFlipper').querySelector('.ce-email-front').insertBefore(pgpBanner, document.getElementById('ceEmailContentWrapper'));
                        });
                    } else {
                        performActualDecryption(privateKey);
                    }

                } catch (e) {
                    if (!isAuto) {
                        myCloudCloseProgressUI();
                        myCloudShowAlert(L.pgp_key_err || 'Key Error', (L.pgp_key_err_msg || 'Failed to parse key: ') + e.message);
                    }
                }
            };

            pgpBanner.querySelector('button').onclick = () => executeDecryption(false);
            executeDecryption(true);
        }

        const pubKeyRegex = /-----BEGIN PGP PUBLIC KEY BLOCK-----[\s\S]+?-----END PGP PUBLIC KEY BLOCK-----/;
        const pubKeyMatch = doc.body.innerText.match(pubKeyRegex);

        if (pubKeyMatch && window.myCloudActionAllowed('email_contacts')) {
            const pubKeyBlock = pubKeyMatch[0].trim();
            const senderEmail = (meta.fromEmail || '').toLowerCase();
            const allContacts = [...(window.myCloudEmailState.contacts || []), ...(window.myCloudEmailState.autoContacts || [])];
            const contact = allContacts.find(c => c.emails && c.emails.some(e => e.val.toLowerCase() === senderEmail));

            if (contact && (!contact.pgp_public_key || !contact.pgp_public_key.includes('BEGIN PGP PUBLIC KEY'))) {
                const addKeyBanner = document.createElement('div');
                addKeyBanner.style.cssText = 'background:var(--gray-15); color:var(--text-primary); padding:10px 20px; display:flex; justify-content:space-between; align-items:center; border-radius:4px; margin: 10px 20px; border:1px solid var(--border-medium);';
                addKeyBanner.innerHTML = '<span>🔑 ' + (L.pgp_pub_key_found || 'Public PGP Key found in this message.') + '</span> <button class="owa-btn owa-primary">' + (L.pgp_add_to_contact || 'Add Key to Contact') + '</button>';

                addKeyBanner.querySelector('button').onclick = () => {
                    contact.pgp_public_key = pubKeyBlock;
                    const fd = new URLSearchParams();
                    fd.append('myCloud_action', 'email_save_contact');
                    fd.append('myCloud_key', myCloudState.key);
                    fd.append('myCloud_token', window.myCloudCsrfToken);
                    fd.append('book_type', window.myCloudEmailState.contacts.some(c => c.id === contact.id) ? 'main' : 'auto');
                    fd.append('contact_id', contact.id);
                    fd.append('name', contact.name || '');
                    fd.append('first_name', contact.first_name || '');
                    fd.append('last_name', contact.last_name || '');
                    fd.append('emails', JSON.stringify(contact.emails || []));
                    fd.append('phones', JSON.stringify(contact.phones || []));
                    fd.append('company', contact.company || '');
                    fd.append('job_title', contact.job_title || '');
                    fd.append('address', contact.address || '');
                    fd.append('website', contact.website || '');
                    fd.append('labels', contact.labels || '');
                    fd.append('notes', contact.notes || '');
                    fd.append('pgp_public_key', pubKeyBlock);

                    if (typeof myCloudCreateProgressUI === 'function') myCloudCreateProgressUI('Saving...');
                    fetch('', { method: 'POST', body: fd }).then(r=>r.json()).then(resSave => {
                        if (typeof myCloudCloseProgressUI === 'function') myCloudCloseProgressUI();
                        if (resSave.status === 'OK') {
                            addKeyBanner.remove();
                            if(typeof myCloudShowAlert === 'function') myCloudShowAlert(L.success || 'Success', L.pgp_key_added || 'PGP Key added to contact successfully.');
                        } else {
                            if(typeof myCloudShowAlert === 'function') myCloudShowAlert(L.error_prefix || 'Error', resSave.msg || 'Save failed.');
                        }
                    }).catch(() => {
                        if (typeof myCloudCloseProgressUI === 'function') myCloudCloseProgressUI();
                        if(typeof myCloudShowAlert === 'function') myCloudShowAlert(L.error_prefix || 'Error', L.net_error || 'Network error.');
                    });
                };
                document.getElementById('ceEmlFlipper').querySelector('.ce-email-front').insertBefore(addKeyBanner, document.getElementById('ceEmailContentWrapper'));
            }
        }

        const readTbWrap = document.getElementById('ceEmlReadToolbarWrap');
        const readTb = document.getElementById('ceEmlReadToolbar');
        if (readTbWrap && readTb) {
            const checkWrap = () => {
                if (readTbWrap.offsetWidth === 0) return;
                
                const t1 = readTb.querySelectorAll('.ce-label-tier-1');
                const t2 = readTb.querySelectorAll('.ce-label-tier-2');
                const t3 = readTb.querySelectorAll('.ce-label-tier-3');
                const t4 = readTb.querySelectorAll('.ce-label-tier-4');
                const divs = readTb.querySelectorAll('.ce-divider-tier-collapse');

                [t1, t2, t3, t4].forEach(t => t.forEach(el => el.style.display = ''));
                divs.forEach(el => { el.style.display = ''; el.style.margin = ''; });

                if (readTb.scrollWidth > readTbWrap.offsetWidth) t1.forEach(el => el.style.display = 'none');
                if (readTb.scrollWidth > readTbWrap.offsetWidth) t2.forEach(el => el.style.display = 'none');
                if (readTb.scrollWidth > readTbWrap.offsetWidth) t3.forEach(el => el.style.display = 'none');
                if (readTb.scrollWidth > readTbWrap.offsetWidth) t4.forEach(el => el.style.display = 'none');
                if (readTb.scrollWidth > readTbWrap.offsetWidth) divs.forEach(el => el.style.display = 'none');
            };

            if (window._emlTbResizeObs) window._emlTbResizeObs.disconnect();
            window._emlTbResizeObs = new ResizeObserver(() => checkWrap());
            window._emlTbResizeObs.observe(readTbWrap);
            checkWrap();
        }
    };

    const executeDirectFetch = () => {
        reading.innerHTML = skeletonHtml;
        const fd = new URLSearchParams({ myCloud_action: 'email_get_body', myCloud_key: myCloudState.key, myCloud_token: window.myCloudCsrfToken, account_id: targetAcc, message_id: msgId, folder: targetFolder });
        
        fetch('', { method: 'POST', body: fd }).then(myCloudCheckResponse).then(res => {
            if (typeof myCloudHideLoading === 'function') myCloudHideLoading();
            
            if (res.status === 'OK') {
                if (!myCloudEmailState.bodyCache) myCloudEmailState.bodyCache = {};
                
                let totalAttSize = 0;
                if (res.attachments && res.attachments.length > 0) {
                    res.attachments.forEach(att => { totalAttSize += parseInt(att.size || 0); });
                }
                
                if (totalAttSize <= 5242880) { // 5MB Limit
                    myCloudEmailState.bodyCache[msgKey] = res;
                }

                requestAnimationFrame(() => renderBodyPayload(res));
            } else {
                reading.innerHTML = '<div class="ce-email-empty" style="color:var(--danger);">' + (L.error_prefix || 'Error:') + '<br><br>' + myCloudEscapeHtml(res.msg) + '</div>';

                // Auto-remove ghost messages from the UI and memory
                if (res.code === 'MSG_NOT_FOUND') {
                    myCloudEmailState.currentMessages = myCloudEmailState.currentMessages.filter(m => String(m.id) !== String(msgId));
                    const ghostItem = document.querySelector(`.ce-email-list-item[data-msg-key="${CSS.escape(msgKey)}"]`);
                    if (ghostItem) ghostItem.remove();
                    myCloudEmailState.selectedMessages = myCloudEmailState.selectedMessages.filter(k => k !== msgKey);
                }
            }
        });
    };

    // FIX: Completely bypass the skeleton render if the body is instantly available in cache.
    if (myCloudEmailState.bodyCache && myCloudEmailState.bodyCache[msgKey]) {
        const cached = myCloudEmailState.bodyCache[msgKey];
        if (cached instanceof Promise) {
            // Background fetch is currently active. Wait for it instead of duplicating.
            reading.innerHTML = skeletonHtml;
            cached.then(res => {
                if (myCloudEmailState.activeMessageKey === msgKey) {
                    requestAnimationFrame(() => renderBodyPayload(res));
                }
            }).catch(() => {
                // If prefetch failed or was too large to cache, fallback to direct fetch
                if (myCloudEmailState.activeMessageKey === msgKey) {
                    executeDirectFetch();
                }
            });
            return;
        } else {
            renderBodyPayload(cached);
            return;
        }
    }

    executeDirectFetch();
};


window._emailCreateContactFromEmail = function(name, email) {
    // Show the contacts modal first if it's not open
    const overlay = document.getElementById('myCloudModalOverlay');
    if (!overlay || overlay.style.display === 'none' || !document.getElementById('ceContactList')) {
        myCloudShowEmailContacts();
    }
    
    // Wait for modal to render, then trigger new contact with pre-filled data
    setTimeout(() => {
        window._emailEditContact(null); // Open "New Contact" sub-modal
        setTimeout(() => {
            const nameInp = document.getElementById('cntName');
            if (nameInp && name !== email) nameInp.value = name;
            
            // Add the email row
            window._addContactEmail();
            const valInputs = document.querySelectorAll('.cnt-email-val');
            if (valInputs.length > 0) {
                valInputs[valInputs.length - 1].value = email;
            }
        }, 50);
    }, 50);
};

window._emailSafeEditContact = function(id) {
    const overlay = document.getElementById('myCloudModalOverlay');
    if (!overlay || overlay.style.display === 'none' || !document.getElementById('ceContactList')) {
        myCloudShowEmailContacts();
    }
    setTimeout(() => {
        if (typeof window._emailEditContact === 'function') {
            window._emailEditContact(id);
        }
    }, 50);
};

window._emailAddEmailToContact = function(contactId, email) {
    // Show the contacts modal first if it's not open
    const overlay = document.getElementById('myCloudModalOverlay');
    if (!overlay || overlay.style.display === 'none' || !document.getElementById('ceContactList')) {
        myCloudShowEmailContacts();
    }
    
    setTimeout(() => {
        window._emailEditContact(contactId); // Open "Edit Contact" sub-modal
        setTimeout(() => {
            // Add the email row
            window._addContactEmail();
            const valInputs = document.querySelectorAll('.cnt-email-val');
            if (valInputs.length > 0) {
                valInputs[valInputs.length - 1].value = email;
            }
        }, 50);
    }, 50);
};

window.myCloudEmailAction = function(action, msgId, metaObjStr) {
    let meta = {};
    try { meta = JSON.parse(decodeURIComponent(metaObjStr)); } catch(e) { console.warn("Failed to parse email meta data."); }
    
    const L = typeof myCloud_LANG !== 'undefined' ? myCloud_LANG : {};
    const targetAcc = meta.account_id || myCloudEmailState.activeAccount;
    const targetFolder = meta.folder || myCloudEmailState.activeFolder;
    const msgKey = targetAcc + '|' + targetFolder + '|' + msgId;

    if (action === 'delete') {
        const isTrash = /trash|deleted|bin|papelera|corbeille|papierkorb|prullenbak/i.test(myCloudEmailState.activeFolder);
        
        let baseTargetKeys = [msgKey];
        if (myCloudEmailState.selectedMessages && myCloudEmailState.selectedMessages.includes(msgKey)) {
            baseTargetKeys = [...myCloudEmailState.selectedMessages];
        }

        // --- THREAD EXPANSION FIX ---
        let targetKeys = [];
        baseTargetKeys.forEach(k => {
            targetKeys.push(k);
            const parts = k.split('|');
            const renderedMsg = (window.myCloudEmailState.renderedMessages || []).find(m => String(m.id) === String(parts[2]) && (m.account_id || myCloudEmailState.activeAccount) === parts[0] && (m.folder || myCloudEmailState.activeFolder) === parts[1]);
            if (renderedMsg && renderedMsg.is_thread_parent && renderedMsg.children) {
                renderedMsg.children.forEach(child => {
                    targetKeys.push(parts[0] + '|' + parts[1] + '|' + child.id);
                });
            }
        });
        targetKeys = [...new Set(targetKeys)];

        const groups = window._emailGroupSelectedMessages(targetKeys);

        const execDelete = (skipUndo = false) => {
            // --- FIND NEXT MESSAGE TO AUTO-OPEN ---
            let nextMsgKey = null;
            const listItems = Array.from(document.querySelectorAll('#ceEmailListContent .ce-email-list-item'));
            const firstDeletedIndex = listItems.findIndex(el => baseTargetKeys.includes(el.dataset.msgKey));
            
            if (firstDeletedIndex !== -1) {
                // Look downwards first
                for (let i = firstDeletedIndex + 1; i < listItems.length; i++) {
                    if (!baseTargetKeys.includes(listItems[i].dataset.msgKey)) { nextMsgKey = listItems[i].dataset.msgKey; break; }
                }
                // Fallback upwards
                if (!nextMsgKey) {
                    for (let i = firstDeletedIndex - 1; i >= 0; i--) {
                        if (!baseTargetKeys.includes(listItems[i].dataset.msgKey)) { nextMsgKey = listItems[i].dataset.msgKey; break; }
                    }
                }
            }

            // If permanent delete, execute standard flow
            if (skipUndo || isTrash) {
                if (nextMsgKey) myCloudEmailState.pendingSelectMsgKey = nextMsgKey;
                doBackendDelete(true);
                return;
            }
            
            // --- OPTIMISTIC UI EXECUTION ---
            const removedMsgs = [];
            let unreadDeletedCountByFolder = {};
			
			if (!myCloudEmailState.pendingDeletes) myCloudEmailState.pendingDeletes = new Set();

            targetKeys.forEach(k => {
				myCloudEmailState.pendingDeletes.add(k);
                const id = k.split('|')[2];
                const el = document.querySelector(`.ce-email-list-item[data-msg-key="${CSS.escape(k)}"]`);
                if (el) el.classList.add('ce-email-removing');
                const msgObj = myCloudEmailState.currentMessages.find(m => String(m.id) === String(id));
                if (msgObj) {
                    removedMsgs.push(msgObj);
                    
                    // Track Unread Counter Math
                    if (!msgObj.is_read) {
                        const acc = msgObj.account_id || myCloudEmailState.activeAccount;
                        const fld = msgObj.folder || myCloudEmailState.activeFolder;
                        const grp = acc + '|' + fld;
                        unreadDeletedCountByFolder[grp] = (unreadDeletedCountByFolder[grp] || 0) + 1;
                    }
                }
            });
            

            // Update Folders Instantly
            Object.keys(unreadDeletedCountByFolder).forEach(grp => {
                const parts = grp.split('|');
                const acc = parts[0], fld = parts[1];
                const count = unreadDeletedCountByFolder[grp];
                if (myCloudEmailState.foldersData[acc]) {
                    const folderData = myCloudEmailState.foldersData[acc].find(f => f.id === fld);
                    if (folderData && folderData.unread > 0) {
                        folderData.unread = Math.max(0, folderData.unread - count);
                        if (fld.toUpperCase() === 'INBOX') myCloudEmailState.inboxUnreadCounts[acc] = folderData.unread;
                    }
                }
            });
			
            myCloudEmailRenderTree();


            // Clean up state immediately
            myCloudEmailState.currentMessages = myCloudEmailState.currentMessages.filter(m => {
                const k = (m.account_id || myCloudEmailState.activeAccount) + '|' + (m.folder || myCloudEmailState.activeFolder) + '|' + m.id;
                return !targetKeys.includes(k);
            });

            if (targetKeys.includes(msgKey) || targetKeys.includes(myCloudEmailState.activeMessageKey)) {
                if (nextMsgKey) {
                    const nextEl = document.querySelector(`.ce-email-list-item[data-msg-key="${CSS.escape(nextMsgKey)}"]`);
                    if (nextEl) {
                        setTimeout(() => {
                            const isMobile = (typeof myCloudDevice !== 'undefined' && myCloudDevice.type === 'phone') || window.innerWidth < 768;
                            if (isMobile) {
                                myCloudEmailState.selectedMessages = [nextMsgKey];
                                window._emailSetMobileView('list');
                                window._emailRenderMessageList();
                            } else {
                                nextEl.click();
                            }
                        }, 50);
                    }
                } else {
                    document.getElementById('emailPaneReading').innerHTML = '<div class="ce-email-empty">' + (L.msg_deleted || 'Message deleted') + '</div>';
                    const isMobile = (typeof myCloudDevice !== 'undefined' && myCloudDevice.type === 'phone') || window.innerWidth < 768;
                    if (isMobile) window._emailSetMobileView('list');
                }
            }

            // --- STACKABLE TOAST ENGINE WITH LIVE COUNTER ---
            let tc = document.getElementById('ce-email-toast-container');
            if (!tc) {
                tc = document.createElement('div');
                tc.id = 'ce-email-toast-container';
                document.body.appendChild(tc);
            }
            
            window._emailPendingUndoActions = (window._emailPendingUndoActions || 0) + 1;
            if (window._emailPendingUndoActions === 1) {
                window._emailBeforeUnloadHandler = (e) => { e.preventDefault(); e.returnValue = ''; return ''; };
                window.addEventListener('beforeunload', window._emailBeforeUnloadHandler);
            }

            const clearUndoBlock = () => {
                window._emailPendingUndoActions = Math.max(0, window._emailPendingUndoActions - 1);
                if (window._emailPendingUndoActions === 0 && window._emailBeforeUnloadHandler) window.removeEventListener('beforeunload', window._emailBeforeUnloadHandler);
            };

            let timeLeft = 4;
            const toast = document.createElement('div');
            toast.className = 'ce-email-undo-toast';
            
            const toastSpan = document.createElement('span');
            toastSpan.textContent = `${targetKeys.length} message(s) deleted. Undoing in ${timeLeft}s`;
            
            const undoBtn = document.createElement('button');
            undoBtn.className = 'ce-email-undo-btn';
            undoBtn.textContent = 'Undo';
            
            toast.appendChild(toastSpan);
            toast.appendChild(undoBtn);
            tc.appendChild(toast);
            
            let isUndone = false;
            
            const deleteInterval = setInterval(() => {
                timeLeft--;
                if (timeLeft > 0) {
                    toastSpan.textContent = `${targetKeys.length} message(s) deleted. Undoing in ${timeLeft}s`;
                } else {
                    clearInterval(deleteInterval);
                    if (!isUndone) {
                        toast.style.opacity = '0';
                        toast.style.transform = 'translateY(20px)';
                        setTimeout(() => toast.remove(), 300);
						clearUndoBlock();
                        doBackendDelete(); // Execute after countdown
                    }
                }
            }, 1000);
            
            undoBtn.onclick = () => {
                isUndone = true;
                clearInterval(deleteInterval);
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(20px)';
                setTimeout(() => toast.remove(), 300);
				clearUndoBlock();
                
                // Revert Visual Flow
                targetKeys.forEach(k => {
                    myCloudEmailState.pendingDeletes.delete(k);
                    const el = document.querySelector(`.ce-email-list-item[data-msg-key="${CSS.escape(k)}"]`);
                    if (el) el.classList.remove('ce-email-removing');
                });
                myCloudEmailState.currentMessages.push(...removedMsgs);

                // Revert Unread Counters
                Object.keys(unreadDeletedCountByFolder).forEach(grp => {
                    const parts = grp.split('|');
                    const acc = parts[0], fld = parts[1];
                    const count = unreadDeletedCountByFolder[grp];
                    if (myCloudEmailState.foldersData[acc]) {
                        const folderData = myCloudEmailState.foldersData[acc].find(f => f.id === fld);
                        if (folderData) {
                            folderData.unread += count;
                            if (fld.toUpperCase() === 'INBOX') myCloudEmailState.inboxUnreadCounts[acc] = folderData.unread;
                        }
                    }
                });
                myCloudEmailRenderTree();
                window._emailRenderMessageList(); 
            };
        };

        const doBackendDelete = (refreshUI = false) => {
            const promises = groups.map(g => {
                const fd = new URLSearchParams({ 
                    myCloud_action: 'email_delete_msg', 
                    myCloud_key: myCloudState.key, 
                    myCloud_token: window.myCloudCsrfToken, 
                    account_id: g.acc, 
                    folder: g.fld,
                    message_id: g.ids.join(',') 
                });
                return fetch('', { method: 'POST', body: fd }).then(r=>r.json());
            });
            
            Promise.all(promises).then(results => {

                // DOM Garbage Collection: Nuke invisible DOM elements
                targetKeys.forEach(k => {
                    // Intentionally DO NOT remove from pendingDeletes.
                    // Keep it as a tombstone for the session to prevent ghosting on subsequent fetches.
                    const el = document.querySelector(`.ce-email-list-item[data-msg-key="${CSS.escape(k)}"]`);
                    if (el) el.remove();
                });

                const failed = results.find(r => r.status !== 'OK');
                if (failed) {
                    if (typeof myCloudShowAlert === 'function') myCloudShowAlert(L.error_prefix || 'Error', failed.msg || 'Operation failed.');
                    myCloudEmailFetchMessages(myCloudEmailState.activeFolder, true); // reload to fix desync
                }
                fetch('', { method: 'POST', body: new URLSearchParams({ myCloud_action: 'email_process_outbox', myCloud_key: myCloudState.key, myCloud_token: window.myCloudCsrfToken }) });
            });
        };

        if (isTrash) {
            if (typeof myCloudShowAlert === 'function') {
                myCloudShowAlert(
                    L.delete_perm || "Delete Forever", 
                    L.confirm_perm_del || "Permanently delete " + targetKeys.length + " message(s)?", 
                    () => execDelete(true)
                );
            }
        } else {
            execDelete(false);
        }
        return; 
    }

    if (action === 'restore') {
        let targetKeys = [msgKey];
        if (myCloudEmailState.selectedMessages && myCloudEmailState.selectedMessages.includes(msgKey)) {
            targetKeys = [...myCloudEmailState.selectedMessages];
        }
        const groups = window._emailGroupSelectedMessages(targetKeys);
        
        if (typeof myCloudShowLoading === 'function') myCloudShowLoading();
        
        const promises = groups.map(g => {
            const fd = new URLSearchParams({ 
                myCloud_action: 'email_restore_msg', 
                myCloud_key: myCloudState.key, 
                myCloud_token: window.myCloudCsrfToken, 
                account_id: g.acc, 
                folder: g.fld,
                message_id: g.ids.join(',') 
            });
            return fetch('', { method: 'POST', body: fd }).then(r=>r.json());
        });
        
        Promise.all(promises).then(results => {
            if (typeof myCloudHideLoading === 'function') myCloudHideLoading();
            const failed = results.find(r => r.status !== 'OK');
            if (!failed) {
                document.getElementById('emailPaneReading').innerHTML = '<div class="ce-email-empty">' + (L.msg_restored || 'Message(s) restored to Inbox') + '</div>';
                myCloudEmailFetchMessages(myCloudEmailState.activeFolder, true);
            } else {
                myCloudShowAlert(L.error_prefix || 'Error', failed.msg || (L.op_failed || 'Operation failed.'));
            }
        }).catch(() => {
            if (typeof myCloudHideLoading === 'function') myCloudHideLoading();
            myCloudShowAlert(L.error_prefix || 'Error', L.net_error || 'Network error.');
        });
        return;
    }

    // --- STANDARD ACTION EXECUTION FOR REPLY/FORWARD ---
    if (typeof myCloudShowLoading === 'function') myCloudShowLoading();
    const bodyFd = new URLSearchParams({ 
        myCloud_action: 'email_get_body', 
        myCloud_key: myCloudState.key, 
        myCloud_token: window.myCloudCsrfToken, 
        account_id: targetAcc, 
        message_id: msgId, 
        folder: targetFolder 
    });

    if (action === 'edit_draft' || action === 'resend') {
        bodyFd.append('extract_draft_attachments', '1');
    }

    fetch('', { method: 'POST', body: bodyFd }).then(myCloudCheckResponse).then(async res => {
        if (typeof myCloudHideLoading === 'function') myCloudHideLoading();
        let originalHtml = (res.status === 'OK') ? res.body : '';

        // Purify HTML on the client-side before injecting into the composer
        if (originalHtml) {
            if (!window.DOMPurify) await new Promise((res) => { const s = document.createElement('script'); s.src = '/script/dompurify/purify.min.js'; s.onload = res; document.head.appendChild(s); });
            originalHtml = window.DOMPurify.sanitize(originalHtml, { FORBID_TAGS: ['script', 'iframe', 'object', 'embed', 'applet', 'meta', 'base'], ALLOW_DATA_ATTR: false });
        }

        let prefillTo = '';
        let prefillCc = '';
        let prefillSubj = '';
        
        const myEmail = myCloudEmailState.accounts[targetAcc] ? myCloudEmailState.accounts[targetAcc].email : '';
        const outlookHeader = 
            '<div style="border-top: 1px solid #E1E1E1; padding-top: 10px; margin-top: 20px; font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif; font-size: 10pt;">' +
                '<b>From:</b> ' + myCloudEscapeHtml(meta.fromName || '') + ' &lt;' + myCloudEscapeHtml(meta.fromEmail || '') + '&gt;<br>' +
                '<b>Sent:</b> ' + meta.date + '<br>' +
                '<b>To:</b> ' + myCloudEscapeHtml(myEmail) + '<br>' +
                '<b>Subject:</b> ' + myCloudEscapeHtml(meta.subject || '') + '<br>' +
            '</div><br>' + originalHtml;

        if (action === 'resend') {
            const searchStr = (meta.fromEmail || '').toLowerCase();
            let prefillFrom = '';
            Object.keys(myCloudEmailState.accounts).forEach(aId => {
                const a = myCloudEmailState.accounts[aId];
                if (a.is_inactive) return;
                if (a.email.toLowerCase() === searchStr) prefillFrom = aId + '|' + a.email;
                (a.aliases || []).forEach(al => {
                   const alEmail = (typeof al === 'object' ? al.email : al).toLowerCase();
                    if (alEmail === searchStr) prefillFrom = aId + '|' + alEmail;
                });
            });

            if (typeof myCloudShowEmailComposer === 'function') {
                myCloudShowEmailComposer({ 
                    from: prefillFrom,
                    to: meta.to || res.to, 
                    cc: meta.cc || res.cc,
                    bcc: meta.bcc || res.bcc,
                    subject: meta.subject, 
                    body: originalHtml, 
                    isResend: true,
                    attachments: res.attachments
                });
            }
            return;
        } else if (action === 'edit_draft') {
            if (typeof myCloudShowEmailComposer === 'function') {
                myCloudShowEmailComposer({ 
                    to: meta.to, 
                    cc: meta.cc, 
                    bcc: meta.bcc, 
                    subject: meta.subject, 
                    body: originalHtml, 
                    draftUid: action === 'edit_draft' ? msgId : null, 
                    draftFolder: action === 'edit_draft' ? targetFolder : null, 
                    isDraft: action === 'edit_draft',
                    attachments: res.attachments
                });
            }
            return;
        } else {
            let prefillTo = '';
            let prefillCc = '';
            let prefillSubj = '';
            let prefillFrom = '';
            
            if (action === 'reply' || action === 'reply_all' || action === 'forward') {
                // Find which of our aliases received this email to auto-select the sender address
                const searchStr = ((res.to || meta.to || '') + ',' + (res.cc || meta.cc || '') + ',' + (res.bcc || meta.bcc || '')).toLowerCase();
                const allMyEmails = [];
                Object.keys(myCloudEmailState.accounts).forEach(aId => {
                    const a = myCloudEmailState.accounts[aId];
                    if (a.is_inactive) return;
                    allMyEmails.push({ email: a.email.toLowerCase(), val: aId + '|' + a.email });
                    (a.aliases || []).forEach(al => {
                        const alEmail = (typeof al === 'object' ? al.email : al).toLowerCase();
                        allMyEmails.push({ email: alEmail, val: aId + '|' + (typeof al === 'object' ? al.email : al) });
                    });
                });
                for (let i = 0; i < allMyEmails.length; i++) {
                    if (searchStr.includes(allMyEmails[i].email)) {
                        prefillFrom = allMyEmails[i].val;
                        break;
                    }
                }
            }

            let isBec = false;
            let replyToEmail = '';
            if (action === 'reply' || action === 'reply_all') {
                const replyToRaw = meta.reply_to || res.reply_to || '';
                if (replyToRaw) {
                    const rMatch = replyToRaw.match(/<([^>]+)>/);
                    replyToEmail = (rMatch ? rMatch[1] : replyToRaw).trim().toLowerCase();
                }
                const fromEmailClean = (meta.fromEmail || '').toLowerCase();
                isBec = replyToEmail && replyToEmail !== fromEmailClean;

                prefillTo = meta.reply_to || res.reply_to || meta.fromEmail || '';
                if (action === 'reply_all') {
                    const finalTo = meta.to || res.to;
                    const finalCc = meta.cc || res.cc;
                    const replyToClean = replyToEmail;
                    if (finalTo) {
                        const otherTo = finalTo.split(',').map(e => e.trim()).filter(e => e && !e.toLowerCase().includes(myEmail.toLowerCase()) && !e.toLowerCase().includes((meta.fromEmail || '').toLowerCase()) && (!replyToClean || !e.toLowerCase().includes(replyToClean)));
                        if (otherTo.length > 0) prefillTo += ', ' + otherTo.join(', ');
                    }
                    if (finalCc) {
                        const ccArr = finalCc.split(',').map(e => e.trim()).filter(e => e && !e.toLowerCase().includes(myEmail.toLowerCase()) && !e.toLowerCase().includes((meta.fromEmail || '').toLowerCase()) && (!replyToClean || !e.toLowerCase().includes(replyToClean)));
                        if (ccArr.length > 0) prefillCc = ccArr.join(', ');
                    }
                }
                prefillSubj = 'Re: ' + (meta.subject || '').replace(/^Re:\s*/i, '');
            } else if (action === 'forward') {
                prefillSubj = 'Fwd: ' + (meta.subject || '').replace(/^Fwd:\s*/i, '');
            }

            const continueToComposer = () => {
                if (typeof myCloudShowEmailComposer === 'function') {
                    myCloudShowEmailComposer({ to: prefillTo, cc: prefillCc, subject: prefillSubj, body: outlookHeader, from: prefillFrom });
                }
            };

            if ((action === 'reply' || action === 'reply_all') && isBec) {
                const becTitle = L.bec_warn_title || 'Caution: Return Address Mismatch';
                const becMsg = (L.bec_warn_msg || 'If you reply to this email, it will be sent to <br><b>%s</b><br>, NOT to the sender shown.').replace('%s', '<span style="word-break: break-all; overflow-wrap: break-word;">' + myCloudEscapeHtml(replyToEmail) + '</span>');
                const fullWarning = '<div style="font-size: 0.9em;"><b style="color:var(--danger, #e65100); font-weight: 900">' + becTitle + '</b><br><br>' + becMsg + '</div>';
                myCloudShowAlert(L.sec_warning || 'Security Warning', fullWarning, continueToComposer);
            } else {
                continueToComposer();
            }
        }

    }).catch(() => { if (typeof myCloudHideLoading === 'function') myCloudHideLoading(); });
};

window._emailToggleFlag = function(msgId, flag, accId, folder) {
    const action = flag ? 'email_mark_flagged' : 'email_unmark_flagged';
    const msg = myCloudEmailState.currentMessages.find(m => String(m.id) === String(msgId));
    if (msg) msg.is_flagged = flag;
    window._emailRenderMessageList();

    const targetAcc = accId || myCloudEmailState.activeAccount;
    const targetFolder = folder || myCloudEmailState.activeFolder;

    const fd = new URLSearchParams({ myCloud_action: action, myCloud_key: myCloudState.key, myCloud_token: window.myCloudCsrfToken, account_id: targetAcc, folder: targetFolder, message_id: msgId });
    fetch('', {method:'POST', body:fd});
};

</script>