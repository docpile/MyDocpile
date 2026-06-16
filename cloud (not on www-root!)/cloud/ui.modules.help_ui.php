<?php
/**
 * ============================================================================
 * MODULE: Help UI JavaScript Engine
 * ============================================================================
 * Static JavaScript for the Help Module. Safe for caching and minification.
 * NOTE: Executed exclusively by the client browser.
 * 
 * THIS IS PART OF THE PSEUDO "JS FILE" - SO THE NO PHP CODE IN THIS FILE!
 */
?>
<script>
(function() {
    // --- CONFIGURATION & STATE ---
    // Read from globals established in index.php and the init function
    var initLang = window.myCloudDetectedLang || 'en';
    var userRole = window.myCloudUserRole || 'no-access'; 
    var canWrite = (userRole === 'full' || userRole === 'modify');
    
    var rawData = null; // Help Manual Data
    
    // Language Setup
    var activeLang = initLang;
    var validHelpCodes = window.myCloudHelpLangs || ['en']; 
    var rtlLangs = ['fa', 'ar', 'he', 'ur'];

    // Ticket System State
    var tktCache = [];
    var tktIsAdmin = false;
    var tktShowClosed = false; // Default filter: Hide closed

    // --- ICONS & HELPERS ---
    var icons = {
        basic: '📂',
        cloud: '☁️',
        controls: '🕹️',
        desktop: '💻',
        email: '📬',
		edit: '✏️',
        holeinone: '🥏',
        input: '⌨️',
        office: '📎',
        lock: '🔒',
        pro: '⚡',
        prop: '📊',
        search: '🔍',
        settings: '⚙️',
        star: '⭐',
        start: '🚀',
        ui: '🖥️',
        view: '👁️'
    };

    function fmt(str) {
       if (!str) return '';
       if (typeof myCloudSvg !== 'undefined') {
           str = str.replace(/\[desktop\]/g, myCloudSvg.desktop);
           str = str.replace(/\[tablet\]/g, myCloudSvg.tablet);
           str = str.replace(/\[phone\]/g, myCloudSvg.phone);
       }
       str = str.replace(/\[key:(.*?)\]/g, '<span class="ce-help-key">$1</span>');
       str = str.replace(/\[badge:(.*?)\]/g, '<span class="ce-help-badge">$1</span>');
       str = str.replace(/\*(.*?)\*/g, '<strong>$1</strong>');
       str = str.replace(/\[i\](.*?)\[\/i\]/g, '<em>$1</em>');
       str = str.replace(/\[br\]/g, '<br>');
       return str;
    }

    function getLangStr(obj) {
       if (!obj) return '';
       if (typeof obj === 'string') return fmt(obj);
       var currentLang = activeLang;
       var text = obj[currentLang] || obj['en'];
       if (!text) { var keys = Object.keys(obj); if (keys.length > 0) text = obj[keys[0]]; }
       return fmt(text || '');
    }

    function renderBlocks(blocks) {
        var html = '';
        if (!blocks || !Array.isArray(blocks)) return html;
        blocks.forEach(function(b) {
            if (b.role === 'write' && !canWrite) return;
            if (b.role === 'read' && canWrite) return;
            var txt = getLangStr(b.text);
            switch (b.type) {
                case 'h1': html += '<div class="ce-help-h1">' + (icons[b.icon] || '') + ' ' + txt + '</div>'; break;
                case 'h2': html += '<div class="ce-help-h2">' + txt + '</div>'; break;
                case 'h3': html += '<div class="ce-help-h3">' + txt + '</div>'; break;
                case 'p': html += '<p class="ce-help-p">' + txt + '</p>'; break;
                case 'tip': html += '<div class="ce-help-tip"><span class="ce-help-block-icon">💡</span><div>' + txt + '</div></div>'; break;
                case 'warn': html += '<div class="ce-help-warn"><span class="ce-help-block-icon"> ️</span><div>' + txt + '</div></div>'; break;
                case 'ul':
                    html += '<ul>';
                    b.items.forEach(function(li) {
                        if (li.role === 'write' && !canWrite) return;
                        html += '<li style="padding-bottom: 8px;">' + getLangStr(li) + '</li>';
                    });
                    html += '</ul>'; break;
                case 'grid':
                    html += '<div class="ce-help-grid">';
                    b.cards.forEach(function(c) {
                        html += '<div class="ce-help-card"><span class="ce-help-card-title">' + getLangStr(c.title) + '</span><span class="ce-help-card-text">' + getLangStr(c.text) + '</span></div>';
                    });
                    html += '</div>'; break;
                case 'table':
                    html += '<table class="ce-help-table">';
                    if (b.headers) html += '<tr><th>' + getLangStr(b.headers[0]) + '</th><th>' + getLangStr(b.headers[1]) + '</th></tr>';
                    b.rows.forEach(function(r) {
                        var cls = r.isSub ? 'ce-help-subrow' : '';
                        html += '<tr class="' + cls + '"><td>' + getLangStr(r.c1) + '</td><td>' + getLangStr(r.c2) + '</td></tr>';
                    });
                    html += '</table>'; break;
                case 'treemap':
                    html += '<div class="ce-help-treemap-ex"><div style="flex:2;background:#0078d4;border:1px solid #fff;" class="ce-help-tm-box">' + getLangStr(b.l1) + '</div><div style="flex:1;display:flex;flex-direction:column;"><div style="flex:1;background:#107c10;border:1px solid #fff;" class="ce-help-tm-box">' + getLangStr(b.l2) + '</div><div style="flex:1;background:#d13438;border:1px solid #fff;" class="ce-help-tm-box">' + getLangStr(b.l3) + '</div></div></div>'; break;
            }
        });
        return html;
    }

    // ============================================================
    // SUPPORT TICKET SYSTEM LOGIC
    // ============================================================

    // Sorting Logic
    function sortTickets(tickets) {
        return tickets.sort(function(a, b) {
            // 1. Status Weight (In Progress > Open > Later > Closed)
            var weights = { 'In Progress': 2, 'Open': 3, 'Later': 1, 'Closed': 0 };
            var wa = weights[a.status] || 0;
            var wb = weights[b.status] || 0;
            
            if (wa !== wb) return wb - wa;

            if (a.status === 'Closed') {
                return b.timestamp - a.timestamp; // Date desc
            } else {
                // Priority High->Low, then Date desc
                var pa = parseInt(a.priority) || 0; var pb = parseInt(b.priority) || 0;
                if (pa !== pb) return pb - pa;
                return b.timestamp - a.timestamp;
            }
        });
    }

    // 1. Load Tickets via AJAX
    window.ceLoadTickets = function() {
        var container = document.getElementById('ceHelpContent');
        container.innerHTML = '<div style="text-align:center; padding:40px;"><div class="myCloud-spinner dark"></div></div>';

        var fd = new URLSearchParams();
        fd.append('myCloud_action', 'ticket-list');
        fd.append('myCloud_token', myCloudCsrfToken);
        if(typeof myCloudState !== 'undefined') fd.append('myCloud_key', myCloudState.key);

        fetch('', { method: 'POST', body: fd })
        .then(function(r){ return r.json(); })
        .then(function(resp){
            if(resp.status === 'OK') {
                tktCache = sortTickets(resp.data);
                tktIsAdmin = resp.isAdmin;
                renderTicketUI();
            } else {
                container.innerHTML = '<div style="color:red; padding:20px;">' + resp.msg + '</div>';
            }
        });
    };

    // 2. Toggle "Create Ticket" Form visibility
    window.ceToggleTicketForm = function() {
        var form = document.getElementById('tktCreateForm');
        var btn = document.getElementById('tktCreateToggle');
        if (form.classList.contains('visible')) {
            form.classList.remove('visible');
            btn.innerHTML = '+ Create New Ticket';
            btn.style.borderColor = '#ccc';
            btn.style.color = '#666';
        } else {
            form.classList.add('visible');
            btn.innerHTML = 'Cancel';
            btn.style.borderColor = '#d13438';
            btn.style.color = '#d13438';
            setTimeout(function() { document.getElementById('tktTitle').focus(); }, 100);
        }
    };

    // 3. Filter Toggle (Show/Hide Closed)
    window.ceFilterTickets = function() {
        var cb = document.getElementById('tktShowClosedCb');
        if(cb) tktShowClosed = cb.checked;
        tktCache = sortTickets(tktCache);
        renderTicketList();
    };

    // 4. Render Main Ticket UI Structure
    function renderTicketUI() {
        var container = document.getElementById('ceHelpContent');
        
        var html = '<div class="ce-tkt-wrapper">';
        
        // --- A. Controls Area (Sticky Top) ---
        html += '<div class="ce-tkt-controls">';
        html += '<div class="ce-help-h1" style="margin-bottom:15px; border:none; padding-bottom:0;">🎫 ' + (tktIsAdmin ? 'Ticket Management' : 'Support & Feedback') + '</div>';
        
        // Toggle Button
        html += '<div class="ce-tkt-create-btn" id="tktCreateToggle" onclick="ceToggleTicketForm()">+ Create New Ticket</div>';
        
        // Compact Form (Hidden by default)
        html += '<div id="tktCreateForm" class="ce-tkt-form-compact">' +
            '<div class="ce-tkt-row">' +
               '<select id="tktType" class="ce-tkt-input" style="width:140px;">' + 
                    '<option value="Bug">🐛 Bug</option>' +
                    '<option value="Feature">✨ Feature</option>' +
                    '<option value="Improvement">🔨 Improvement</option>' +
                    '<option value="Security">🔓 Security</option>' +
                    '<option value="Task">📋 Task</option>' +
                '</select>' +
                '<input type="text" id="tktTitle" class="ce-tkt-input" placeholder="Short Subject" style="flex:1;">' +
            '</div>' +
            '<textarea id="tktDesc" class="ce-tkt-input" rows="3" placeholder="Describe issue or wish..."></textarea>' +
            '<div style="text-align:right;">' +
                '<button onclick="ceSubmitTicket()" style="background:var(--accent-primary); color:#fff; border:none; padding:8px 16px; border-radius:4px; cursor:pointer;">Submit Ticket</button>' +
            '</div>' +
        '</div>'; // End Form

        // Filter Bar
        html += '<div class="ce-tkt-filter-bar" style="display:flex; justify-content:space-between; align-items:center; margin-top:15px; padding-bottom:5px; border-bottom:1px solid #eee;">' +
            '<strong class="ce-tkt-filter-title" style="font-size:14px; color:#333;">' + (tktIsAdmin ? 'All Tickets' : 'Your Tickets') + '</strong>' +
            '<label class="ce-tkt-filter-label" style="font-size:12px; cursor:pointer; user-select:none; display:flex; align-items:center; color:#333;"><input type="checkbox" id="tktShowClosedCb" onchange="ceFilterTickets()" ' + (tktShowClosed ? 'checked' : '') + ' style="margin-right:5px;"> Show Closed</label>' +
        '</div>';
        
        html += '</div>'; // End Controls

        // --- B. List Area (Scrollable) ---
        html += '<div id="tktListContainer" class="ce-tkt-list"></div>';
        html += '</div>'; // End Wrapper

        container.innerHTML = html;
        
        // Initial List Render
        renderTicketList();
    }

    // 5. Render the actual list of cards
    function renderTicketList() {
        var listContainer = document.getElementById('tktListContainer');
        if(!listContainer) return;

        // Filter logic: Always show Open/Progress. Show Closed only if checked.
        var visibleTickets = tktCache.filter(function(t) {
            return tktShowClosed || t.status !== 'Closed';
        });
        
        if (visibleTickets.length === 0) {
            listContainer.innerHTML = '<div style="text-align:center; color:#999; padding:40px;">No ' + (tktShowClosed ? '' : 'open ') + 'tickets found.</div>';
            return;
        }

        var html = '';
        var typeIcons = { 'Bug': '🐛', 'Feature': '✨', 'Improvement': '🔓', 'Security': '🔨', 'Task': '📋' };
        visibleTickets.forEach(function(t) {
            var badgeClass = 'ce-tkt-open';
            if(t.status === 'In Progress') badgeClass = 'ce-tkt-progress';
            if(t.status === 'Later') badgeClass = 'ce-tkt-later';
            if(t.status === 'Closed') badgeClass = 'ce-tkt-closed';

            var dateStr = new Date(t.timestamp * 1000).toLocaleString();
            var icon = typeIcons[t.type] || '🎫';
            
            var prioStr = (t.priority && t.priority > 0 && t.status !== 'Closed') ? '<span style="color:#d83b01; font-weight:bold; font-size:11px; margin-left:5px;">(Prio '+t.priority+')</span>' : '';

            html += '<div class="ce-tkt-card">' +
                '<div class="ce-tkt-header">' +
                    '<div class="ce-tkt-title">' + icon + ' ' + t.title + '</div>' +
                    '<span class="ce-tkt-badge '+badgeClass+'">' + t.status + '</span>' +
                '</div>' +
                '<div class="ce-tkt-meta">' +
                    '<span>👤 ' + t.user + '</span>' +
                    '<span>📅 ' + dateStr + '</span>' +
                '</div>' +
                '<div class="ce-tkt-desc">' + t.desc + '</div>';
            
            // Admin Controls
            if (tktIsAdmin) {
                html += '<div class="ce-tkt-admin-box">' +
                    '<strong style="font-size:11px;">Admin:</strong>' +
                    '<select id="status_'+t.id+'" class="ce-tkt-admin-input" style="width:90px;">' +
                        '<option value="Open" '+(t.status==='Open'?'selected':'')+'>Open</option>' +
                        '<option value="In Progress" '+(t.status==='In Progress'?'selected':'')+'>In Progress</option>' +
                        '<option value="Later" '+(t.status==='Later'?'selected':'')+'>Later</option>' +
                        '<option value="Closed" '+(t.status==='Closed'?'selected':'')+'>Closed</option>' +
                    '</select>' +
                    '<input type="number" id="prio_'+t.id+'" class="ce-tkt-admin-input" title="Priority" value="'+(t.priority||0)+'" style="width:40px;" min="0" max="99">' +
                    '<input type="text" id="comment_'+t.id+'" class="ce-tkt-admin-input" placeholder="Admin Comment..." value="'+(t.admin_comment||"")+'" style="flex:1;">' +
                    '<button onclick="ceUpdateTicket(\''+t.id+'\')" class="ce-tkt-admin-btn" title="Save Status/Comment">💾 Save</button>' +
                    '<select id="ver_mode_'+t.id+'" class="ce-tkt-admin-input" style="margin-left:5px; width:80px;">' +
                        '<option value="none">No Ver</option>' +
                        '<option value="patch">Patch (+.1)</option>' +
                        '<option value="minor">Minor (+.1.0)</option>' +
                        '<option value="major">Major (1.0.0)</option>' +
                    '</select>' +
                    '<button onclick="ceCopyToChangelog(\''+t.id+'\')" class="ce-tkt-admin-btn" title="Add to Version Info & Close">📜 To Changelog</button>' +
                '</div>';
            } else if (t.admin_comment) {
                // User View of Admin Comment
                html += '<div class="ce-tkt-admin"><strong>Admin:</strong> ' + t.admin_comment + '</div>';
            }

            html += '</div>';
        });
        
        listContainer.innerHTML = html;
    }

    // --- ACTIONS ---

   window.ceSubmitTicket = function() {
        var title = document.getElementById('tktTitle').value.trim();
        var desc = document.getElementById('tktDesc').value.trim();
        var type = document.getElementById('tktType').value;

        // MODIFIED: Removed !desc check. Only Title is mandatory now.
        if (!title) { alert("Please enter a subject"); return; }

        var fd = new URLSearchParams();
        fd.append('myCloud_action', 'ticket-create');
        fd.append('myCloud_token', myCloudCsrfToken);
        if(typeof myCloudState !== 'undefined') fd.append('myCloud_key', myCloudState.key);
        fd.append('title', title);
        fd.append('desc', desc);
        fd.append('type', type);

        fetch('', { method:'POST', body:fd })
        .then(function(r){ return r.json(); })
        .then(function(res){
            if(res.status==='OK') {
                ceLoadTickets(); 
            } else {
                alert(res.msg);
            }
        });
    };

    window.ceUpdateTicket = function(id) {
        var status = document.getElementById('status_'+id).value;
        var comment = document.getElementById('comment_'+id).value;
        var priority = document.getElementById('prio_'+id).value;

        var fd = new URLSearchParams();
        fd.append('myCloud_action', 'ticket-update');
        fd.append('myCloud_token', myCloudCsrfToken);
        if(typeof myCloudState !== 'undefined') fd.append('myCloud_key', myCloudState.key);
        fd.append('id', id);
        fd.append('status', status);
        fd.append('comment', comment);
        fd.append('priority', priority);
        
        fetch('', { method:'POST', body:fd })
        .then(function(r){ return r.json(); })
        .then(function(res){
            if(res.status==='OK') {
                // Refresh data but try to stay on list view
                ceLoadTickets(); 
            } else {
                alert(res.msg);
            }
        });
    };

    window.ceCopyToChangelog = function(id) {
        var mode = document.getElementById('ver_mode_'+id).value;
      //  if(!confirm("Add to Changelog " + (isNew ? "(New Version)" : "(Current Version)") + " and close ticket?")) return;
        
        var fd = new URLSearchParams();
        fd.append('myCloud_action', 'ticket-changelog');
        fd.append('myCloud_token', myCloudCsrfToken);
        if(typeof myCloudState !== 'undefined') fd.append('myCloud_key', myCloudState.key);
        fd.append('id', id);
        fd.append('increment_mode', mode);
        
        fetch('', { method:'POST', body:fd })
        .then(function(r){ return r.json(); })
        .then(function(res){
            if (res.status !== 'OK') {
                alert(res.msg);
            }
            ceLoadTickets();
        });
    };

    window.myCloudCloseHelp = function() {
        var overlay = document.getElementById('myCloudModalOverlay');
        var modal = document.getElementById('myCloudModal');
        if (!overlay || !modal) return;

        modal.classList.add('closing');
        overlay.classList.add('closing');

        setTimeout(function() {
            overlay.style.display = 'none';
            modal.classList.remove('closing', 'ce-help-modal');
            overlay.classList.remove('closing');
        }, 800); // Matches your existing ceFadeOutScale duration
    };


    // --- MAIN HELP MODAL RENDER ---
    window.myCloudOpenHelp = function() {
        if (typeof myCloudCloseFloatingMenu === 'function') myCloudCloseFloatingMenu(true);
        var overlay = document.getElementById('myCloudModalOverlay');
        var modal = document.getElementById('myCloudModal');
        
        // Settings / Language setup
        var isChecked = true; 
        var target = (typeof window.myCloudDetectedLang !== 'undefined') ? window.myCloudDetectedLang : 'en';
        if (typeof myCloudState !== 'undefined' && myCloudState.settings && typeof myCloudState.settings.showHelpOnStart !== 'undefined') {
            isChecked = myCloudState.settings.showHelpOnStart;
        }
        if (typeof myCloudState !== 'undefined' && myCloudState.settings && myCloudState.settings.language) {
            target = myCloudState.settings.language;
        }
        
        activeLang = validHelpCodes.includes(target) ? target : 'en';
      

        modal.className = 'myCloudModal ce-help-modal'; 
        modal.style.maxWidth = ''; 
        modal.style.height = ''; 
		modal.onkeydown = null;
		
        overlay.style.display = 'flex';
        
        var isRtl = rtlLangs.includes(activeLang);
        modal.setAttribute('dir', isRtl ? 'rtl' : 'ltr');

        // Header Buttons
        var addAsAppBtn = '<button class="ce-help-top-btn" onclick="window.myCloudInstallAppManual && window.myCloudInstallAppManual();">💻 Install as App</button>';
        var changelogBtn = '<button class="ce-help-top-btn" onclick="myCloudVerShowInfo()">🕒 Changelog</button>';
        var supportBtn = '<button class="ce-help-support-btn" onclick="ceLoadTickets()">🎫 Support</button>';
        
        var selectHtml = '<select id="ceHelpLangSelect" class="ce-help-top-select">';
        var globalLangLabels = (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.available_languages) ? myCloud_LANG.available_languages : { 'en': 'English' };
        validHelpCodes.forEach(function(code) {
            var label = globalLangLabels[code] || code.toUpperCase();
            var sel = (activeLang === code) ? 'selected' : '';
            selectHtml += '<option value="' + code + '" ' + sel + '>' + label + '</option>';
        });
        selectHtml += '</select>';
        
        modal.innerHTML = '<div class="myCloudModalHeader ce-help-modal-header">' +
            '<span class="ce-help-header-title">' + myCloudSvgLogo + '<span style="font-weight:100;"> - ' + myCloud_LANG.help_header + '</span></span>' +
            '<div class="ce-help-header-tools">' +
                addAsAppBtn + changelogBtn + supportBtn + selectHtml +
                '<label class="ce-help-show-start" style="font-size:13px; font-weight:400; display:flex; align-items:center; gap:6px; cursor:pointer; user-select:none; margin:0;">' +
                    '<input type="checkbox" id="ceHelpShowOnStart" ' + (isChecked ? 'checked' : '') + ' style="margin:0; width:16px; height:16px;">' +
                    '<span>' + myCloud_LANG.help_show_start + '</span>' +
                '</label>' +
             '</div>' +
          '<button onclick="myCloudCloseHelp()" class="ce-help-close-btn" title="Close">✕</button>' +
       '</div>' +
        '<div class="myCloudModalBody" style="padding:0; flex:1; display:flex; flex-direction:column; overflow:hidden;">' +
            '<div class="ce-help-container">' +
                '<div class="ce-help-nav">' +
                    '<div class="ce-help-search-box">' +
                        '<input type="text" id="ceHelpSearch" class="ce-help-search-input" placeholder="' + myCloud_LANG.help_search_ph + '" autocomplete="off">' +
                    '</div>' +
                    '<ul id="ceHelpList" class="ce-help-list"></ul>' +
                '</div>' +
                '<div id="ceHelpContent" class="ce-help-content"></div>' +
            '</div>' +
        '</div>';

        // Bind Events
        var cb = document.getElementById('ceHelpShowOnStart');
        if (cb) {
            cb.onchange = function(e) {
                if (typeof myCloudState !== 'undefined' && myCloudState.settings) {
                    myCloudState.settings.showHelpOnStart = e.target.checked;
                    if (typeof myCloudSaveSettings === 'function') myCloudSaveSettings();
                }
            };
        }
        
        var langSelect = document.getElementById('ceHelpLangSelect');
        if (langSelect) {
            langSelect.onchange = function(e) {
                activeLang = e.target.value;
                var isRtlNew = rtlLangs.includes(activeLang);
                modal.setAttribute('dir', isRtlNew ? 'rtl' : 'ltr');
                fetchHelpData();
            };
        }

        var searchInput = document.getElementById('ceHelpSearch');
        // Pass rawData (the array), not the search string. renderList reads the input value internally.
        if(searchInput) { searchInput.addEventListener('input', function(e) { renderList(rawData); }); setTimeout(function(){ searchInput.focus(); }, 100); }

        // Load Content
        // Function to load data (called on open and on lang change)
        var fetchHelpData = function() {
            
            console.log("Fetching Help for Language:", activeLang);
            document.getElementById('ceHelpList').innerHTML = '<li style="padding:20px; text-align:center;">Loading...</li>';
            document.getElementById('ceHelpContent').innerHTML = '';
            var fd = new URLSearchParams();
            fd.append('myCloud_action', 'get_help_data');
            if (typeof myCloudCsrfToken !== 'undefined') fd.append('myCloud_token', myCloudCsrfToken);
            fd.append('lang', activeLang); // Send requested language
            
            fetch('', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                rawData = data.filter(function(item) {
                    if (item.role === 'write' && !canWrite) return false;
                    return true;
                });
                renderList(rawData);
            })
            .catch(function(e) { 
                console.error("Help JSON error:", e); 
                document.getElementById('ceHelpList').innerHTML = '<li style="padding:20px; color:red; text-align:center;">Manual unavailable.<br><small>JSON Syntax Error</small></li>';
            });
        };

        // Initial Load
        fetchHelpData();

    };

    function renderList(data) {
        var listEl = document.getElementById('ceHelpList');
        if(!listEl) return;
        listEl.innerHTML = '';
        if(!data || !Array.isArray(data)) return;
        
        var found = false;
        var filter = document.getElementById('ceHelpSearch') ? document.getElementById('ceHelpSearch').value.toLowerCase().trim() : '';

        data.forEach(function(item, idx) {
            var title = getLangStr(item.title);
            
            // --- SEARCH LOGIC START ---
            var isMatch = true;
            if (filter) {
                // 1. Check Title & Keywords (Fast)
                var matchBasic = title.toLowerCase().includes(filter) || 
                                 (item.keywords || '').toLowerCase().includes(filter);
                
                // 2. Check Full Content (Deep)
                var matchContent = false;
                if (!matchBasic && item.content && Array.isArray(item.content)) {
                    // Helper to check a localized value against filter
                    var check = function(val) { 
                        return val && getLangStr(val).toLowerCase().includes(filter); 
                    };

                    matchContent = item.content.some(function(b) {
                        // Standard Text Blocks (p, h1, tip, warn)
                        if (b.text && check(b.text)) return true;
                        
                        // List Items (ul)
                        if (b.items && b.items.some(check)) return true;

                        // Grid Cards (grid)
                        if (b.cards && b.cards.some(function(c) { 
                            return check(c.title) || check(c.text); 
                        })) return true;

                        // Tables
                        if (b.headers && (check(b.headers[0]) || check(b.headers[1]))) return true;
                        if (b.rows && b.rows.some(function(r) { 
                            return check(r.c1) || check(r.c2); 
                        })) return true;

                        // Treemap Labels
                        if (b.type === 'treemap' && (check(b.l1) || check(b.l2) || check(b.l3))) return true;

                        return false;
                    });
                }

                if (!matchBasic && !matchContent) isMatch = false;
            }
            // --- SEARCH LOGIC END ---

            if (!isMatch) return;

            var li = document.createElement('li'), activeClass = (idx === 0 && !filter) ? 'active' : '';
            li.className = 'ce-help-item ' + activeClass;
            li.innerHTML = '<div style="display:flex; align-items:center"><span class="ce-help-icon">' + (icons[item.icon] || '📂') + '</span><span>' + title + '</span></div><svg class="ce-chevron" viewBox="0 0 24 24"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>';
            li.onclick = function() {
                document.querySelectorAll('.ce-help-item').forEach(function(el){el.classList.remove('active');});
                li.classList.add('active');
                document.getElementById('ceHelpContent').innerHTML = renderBlocks(item.content);
                document.getElementById('ceHelpContent').scrollTop = 0;
            };
            listEl.appendChild(li);
            if (idx === 0 && !filter) document.getElementById('ceHelpContent').innerHTML = renderBlocks(item.content);
            if (!found) found = true;
        });
        if (!found) document.getElementById('ceHelpContent').innerHTML = '<div style="text-align:center; padding:40px; color:#999;">' + myCloud_LANG.help_no_results + '</div>';
    }
	if (typeof myCloudRenderToolbar === 'function') myCloudRenderToolbar();
})();
</script>