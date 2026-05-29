<?php
/**
 * ============================================================================
 * MODULE: Bulk Rename Interface
 * ============================================================================
 * Implements the specialized frontend modal and client-side string manipulation 
 * logic for executing advanced, pattern-based batch file renaming.
 * NOTE: Executed exclusively by the client browser.
 * 
 * THIS IS PART OF THE PSEUDO "JS FILE" - SO THE NO PHP CODE IN THIS FILE!
 */

?><script>
function myCloudShowMultiRenameModal() {
    const st = myCloudState;
    const files = myCloudGetSortedItems().filter(function(i) { return st.selectedFiles.includes(i.name); });
    
    if (files.length === 0) return;

    // Load History
    const savedHistory = (st.settings.renameHistory) ? st.settings.renameHistory : [];
    
    // Build History Options for Custom Dropdown
    let histItems = '';
    if (savedHistory.length > 0) {
        savedHistory.forEach(function(h) {
            histItems += '<div class="mr-combo-item" onclick="mrUseHistory(\'' + h.replace(/'/g, "\\'") + '\')">' + h + '</div>';
        });
    } else {
        histItems = '<div class="mr-combo-item" style="color:#999; cursor:default;">(No History)</div>';
    }

    const overlay = document.getElementById('myCloudModalOverlay');
    const modal = document.getElementById('myCloudModal');
    myCloudResetModal();

    overlay.style.display = 'flex';
    modal.className = 'myCloudModal';
    
    // Dialog Styling
    modal.style.width = '750px';
    modal.style.maxWidth = '95%';
    modal.style.height = 'auto';
    modal.style.maxHeight = '90vh';
    modal.style.display = 'flex';
    modal.style.flexDirection = 'column';
    modal.style.overflow = 'hidden';
    modal.style.backgroundColor = '#f3f3f3'; 
    modal.style.color = '#202020';
    modal.style.boxShadow = '0 25px 50px rgba(0,0,0,0.25)';

    // RTL Logic
    const curLang = st.settings.language || 'en';
    const isRtl = ['ar', 'fa', 'he', 'ur'].includes(curLang);
    const dir = isRtl ? 'rtl' : 'ltr';
    const alignStart = isRtl ? 'right' : 'left';
    
    modal.setAttribute('dir', dir);

    // Common Input Style
    const inputStyle = 'border: 1px solid #8f8f9f; border-radius: 2px; padding: 4px 6px; font-size: 13px; outline: none; transition: border-color 0.1s;';
    const labelStyle = 'font-size: 12px; color: #444; margin-bottom: 4px; display: block;';

    modal.innerHTML = 
    '<div class="myCloudModalHeader" style="justify-content:space-between; align-items:center; background:#ffffff; border-bottom:1px solid #e5e5e5;">' +
        '<span>' +
            myCloudSvgLogo + ' <span style="font-weight:100;">- ' + myCloud_LANG.multi_rename + '</span>' +
            '<span style="font-weight:normal; color:#666; font-size:13px; margin-inline-start:8px;">(' + files.length + ' ' + myCloud_LANG.items_lc + ')</span>' +
        '</span>' +
        '<button onclick="myCloudCloseModal()" style="background:transparent; border:none; font-size:20px; cursor:pointer; color:inherit; line-height:1;">✕</button>' +
    '</div>' +
   
    '<div class="myCloudModalBody" style="padding: 20px; overflow-y:auto; display:flex; flex-direction:column; gap:18px; background-color: #f3f3f3; flex:1;">' +
        
        '<div style="display:flex; gap:15px;">' +
            '<div style="flex:1;">' +
                '<label style="' + labelStyle + '">' + myCloud_LANG.pattern_name + '</label>' +
                '<div class="mr-combo-wrapper">' +
                    '<input type="text" id="mrMaskName" class="mr-combo-input" value="[N]" autocomplete="off">' +
                    '<button class="mr-combo-btn" onclick="mrToggleHistory()" tabindex="-1" title="' + myCloud_LANG.history + '">' +
                        '<svg viewBox="0 0 24 24" style="width:10px; height:10px; fill:currentColor;"><path d="M7 10l5 5 5-5z"/></svg>' +
                    '</button>' +
                    '<div id="mrHistoryDropdown" class="mr-combo-dropdown">' +
                        histItems +
                    '</div>' +
                    '<button class="mr-helper-btn" onclick="mrShowHelpers(\'name\', this)" title="' + myCloud_LANG.insert_placeholder + '">+</button>' +
                '</div>' +
            '</div>' +

            '<div style="flex:0 0 100px;">' +
                '<label style="' + labelStyle + '">' + (myCloud_LANG.extension || 'Extension') + '</label>' +
                '<input type="text" id="mrMaskExt" value="[E]" autocomplete="off" style="width:100%; height:30px; ' + inputStyle + '">' +
            '</div>' +
        '</div>' +

        '<div style="background:#ffffff; padding:15px; border:1px solid #d0d0d0; border-radius:4px;">' +
            '<div style="display:flex; gap:15px; margin-bottom:12px;">' +
                '<div style="flex:1;">' +
                    '<label style="' + labelStyle + '">' + myCloud_LANG.search_for + '</label>' +
                    '<input type="text" id="mrSearch" style="width:100%; height:28px; ' + inputStyle + '">' +
                '</div>' +
                '<div style="flex:1;">' +
                    '<label style="' + labelStyle + '">' + myCloud_LANG.replace_with + '</label>' +
                    '<input type="text" id="mrReplace" style="width:100%; height:28px; ' + inputStyle + '">' +
                '</div>' +
            '</div>' +
            
            '<div style="display:flex; gap:20px; align-items:center;">' +
                '<label style="font-size:13px; display:flex; align-items:center; cursor:pointer; user-select:none; color:#202020;">' +
                    '<input type="checkbox" id="mrCase" class="myCloudCheckbox" style="margin-inline-end:6px;"> ' + myCloud_LANG.case_sensitive +
                '</label>' +
                '<label style="font-size:13px; display:flex; align-items:center; cursor:pointer; user-select:none; color:#202020;">' +
                    '<input type="checkbox" id="mrRegex" class="myCloudCheckbox" style="margin-inline-end:6px;"> ' + myCloud_LANG.use_regex +
                '</label>' +
                '<div style="flex:1;"></div>' +
                
                '<button id="btnDateFix" onclick="mrShowDateMenu(this)" style="font-size:12px; padding:4px 12px; background:#e1dfdd; border:1px solid #8f8f9f; border-radius:3px; cursor:pointer; color:#202020; font-weight:500; display:flex; align-items:center; gap:6px;" title="' + myCloud_LANG.date_fix_btn + '">' +
                    '<span>📅</span> ' + myCloud_LANG.date_fix_btn + ' ▼' +
                '</button>' +
            '</div>' +
        '</div>' +

        '<div style="display:flex; gap:20px; align-items:center; font-size:13px; height:32px;">' +
            '<div style="display:flex; align-items:center; gap:8px;">' +
                '<label style="color:#444; margin:0; line-height:28px;">' + myCloud_LANG.counter_start + ':</label>' +
                '<input type="number" id="mrCountStart" value="1" style="width:60px; height:28px; ' + inputStyle + ' padding:2px 6px; margin:0;">' +
            '</div>' +
            '<div style="display:flex; align-items:center; gap:8px;">' +
                '<label style="color:#444; margin:0; line-height:28px;">' + myCloud_LANG.counter_step + ':</label>' +
                '<input type="number" id="mrCountStep" value="1" style="width:60px; height:28px; ' + inputStyle + ' padding:2px 6px; margin:0;">' +
            '</div>' +
            '<div style="display:flex; align-items:center; gap:8px;">' +
                '<label style="color:#444; margin:0; line-height:28px;">' + myCloud_LANG.counter_digits + ':</label>' +
                '<input type="number" id="mrCountDigits" value="1" style="width:50px; height:28px; ' + inputStyle + ' padding:2px 6px; margin:0;">' +
            '</div>' +
        '</div>' +

        '<div style="flex:1; border:1px solid #d0d0d0; border-radius:2px; overflow-y:auto; min-height:250px; background:#ffffff;">' +
            '<table class="myCloudTable" style="width:100%; border-collapse:collapse; table-layout:fixed;">' +
                '<thead style="background:#f0f0f0; border-bottom:1px solid #d0d0d0; position:sticky; top:0; z-index:10;">' +
                    '<tr>' +
                        '<th style="width:auto; padding:8px 12px; text-align:' + alignStart + ' !important; font-size:12px; font-weight:600; color:#666; text-transform:uppercase;">' + myCloud_LANG.original_name + '</th>' +
                        '<th style="width:40px; padding:8px; text-align:center;"></th>' +
                        '<th style="width:auto; padding:8px 12px; text-align:' + alignStart + ' !important; font-size:12px; font-weight:600; color:#666; text-transform:uppercase;">' + myCloud_LANG.preview + '</th>' +
                    '</tr>' +
                '</thead>' +
                '<tbody id="mrPreviewBody" style="font-size:13px; font-family:\'Segoe UI\', sans-serif; background:#fff;"></tbody>' +
            '</table>' +
        '</div>' +

    '</div>' +

    '<div class="myCloudButtons" style="margin-top:0; padding:15px 20px; background:#f3f3f3; border-top:1px solid #e5e5e5; display:flex; align-items:center;">' +
        '<span id="mrStatus" style="margin-inline-end:auto; color:#d13438; font-weight:bold; font-size:13px; display:flex; align-items:center; gap:6px;"></span>' +
        
        '<button onclick="myCloudCloseModal()" style="min-width:90px; height:32px; border:1px solid #ccc; background:#fff; color:#333; border-radius:4px; margin-inline-end:10px;">' + myCloud_LANG.cancel + '</button>' +
        '<button id="btnMultiRenameOk" onclick="mrExecute()" style="min-width:110px; height:32px; background:#0078d4; color:#fff; border:1px solid #0078d4; border-radius:4px; font-weight:600;">' + myCloud_LANG.apply_rename + '</button>' +
    '</div>';

    // 1. Bind Events
    const inputs = ['mrMaskName','mrMaskExt','mrSearch','mrReplace','mrCase','mrRegex','mrCountStart','mrCountStep','mrCountDigits'];
    inputs.forEach(function(id) {
        const el = document.getElementById(id);
        if(el) {
            el.addEventListener('input', mrUpdatePreview);
            el.addEventListener('focus', function() { if(el.id!=='mrMaskName') mrCloseHistory(); el.style.borderColor = '#0078d4'; });
            el.addEventListener('blur', function() { el.style.borderColor = '#8f8f9f'; });
        }
    });
    
    // Close history when clicking outside
    document.addEventListener('click', function(e) {
        const wrap = document.querySelector('.mr-combo-wrapper');
        if (wrap && !wrap.contains(e.target)) {
            mrCloseHistory();
        }
    });
    
    // 2. Initial Preview
    window.mrFiles = files; 
    mrUpdatePreview();
    
    // 3. Focus Name Input
    setTimeout(function() {
        const inp = document.getElementById('mrMaskName');
        inp.focus(); 
        inp.select();
    }, 50);

    // 4. Default Button Handler (Enter Key)
    modal.setAttribute('tabindex', '-1');
    modal.onkeydown = function(e) {
        if (e.key === 'Escape') { e.preventDefault(); myCloudCloseModal(); }
        if (e.key === 'Enter') {
            if (modal.contains(document.activeElement) && 
                (document.activeElement.tagName === 'INPUT' || document.activeElement === modal)) {
                 e.preventDefault();
                 document.getElementById('btnMultiRenameOk').click();
            }
        }
    };
}

// [FIXED] History Helper Functions
function mrToggleHistory() {
    const dd = document.getElementById('mrHistoryDropdown');
    if (dd) {
        dd.style.display = (dd.style.display === 'block') ? 'none' : 'block';
    }
}

function mrCloseHistory() {
    const dd = document.getElementById('mrHistoryDropdown');
    if (dd) {
        dd.style.display = 'none';
    }
}

function mrUseHistory(val) {
    const input = document.getElementById('mrMaskName');
    if (input) {
        input.value = val;
        mrUpdatePreview();
    }
    mrCloseHistory();
}

/**
 * Helper Menu for Inserting Placeholders
 */
function mrShowHelpers(target, btn) {
    const inputId = (target === 'name') ? 'mrMaskName' : 'mrMaskExt';
    const input = document.getElementById(inputId);
    
    const items = [
        { l: '[N] Name', v: '[N]' },
        { l: '[N1-5] Range', v: '[N1-5]' },
        { l: '[E] Extension', v: '[E]' },
        { l: '---' },
        { l: 'Advanced: [N-5-] (Last 5)', v: '[N-5-]' },
        { l: 'Advanced: [N-8,5] (Start -8, len 5)', v: '[N-8,5]' },
        { l: '---' },
        { l: '<b>File Date (Modified)</b>' },
        { l: '[Y] Year / [M] Month / [D] Day', v: '[Y]-[M]-[D]' },
        { l: '[h] Hour / [m] Min / [s] Sec', v: '[h]-[m]-[s]' },
        { l: '[YMD] ISO Date', v: '[YMD]' },
        { l: '---' },
        { l: '<b>Current Date (Today)</b>' },
        { l: '[tY] Year / [tM] Month / [tD] Day', v: '[tY]-[tM]-[tD]' },
        { l: '[th] Hour / [tm] Min / [ts] Sec', v: '[th]-[tm]-[ts]' },
        { l: '[tYMD] ISO Date', v: '[tYMD]' },
        { l: '---' },
        { l: '[C] Counter', v: '[C]' }
    ];
    
    const rect = btn.getBoundingClientRect();
    const menu = document.createElement('div');
    menu.className = 'myCloudContextMenu';
    menu.style.top = (rect.bottom + 2) + 'px';
    
    const isRtl = document.getElementById('myCloudModal').getAttribute('dir') === 'rtl';
    if(isRtl) menu.style.left = rect.left + 'px';
    else menu.style.left = (rect.right - 220) + 'px';
    
    menu.style.width = 'auto';
    menu.style.visibility = 'visible';
    menu.style.backgroundColor = '#fff';
    menu.style.color = '#333';
    menu.style.border = '1px solid #ccc';
    menu.style.maxHeight = '300px';
    menu.style.overflowY = 'auto';
    menu.style.boxShadow = '0 4px 10px rgba(0,0,0,0.2)';

    items.forEach(function(i) {
        if (i.l === '---') {
            const hr = document.createElement('div');
            hr.style.height = '1px';
            hr.style.background = '#e0e0e0';
            hr.style.margin = '4px 0';
            menu.appendChild(hr);
        } else if (!i.v) {
             const div = document.createElement('div');
             div.style.padding = '4px 10px';
             div.style.fontSize = '11px';
             div.style.color = '#888';
             div.style.fontWeight = '600';
             div.innerHTML = i.l;
             menu.appendChild(div);
        } else {
            const div = document.createElement('div');
            div.className = 'myCloudContextItem';
            div.innerHTML = i.l;
            div.style.color = '#333';
            div.onclick = function(e) {
                e.stopPropagation();
                mrAppendToken(input, i.v);
                mrUpdatePreview();
                menu.remove();
            };
            menu.appendChild(div);
        }
    });
    
    document.body.appendChild(menu);
    const closer = function(e) { 
        if (!menu.contains(e.target) && e.target !== btn) { 
            menu.remove(); 
            document.removeEventListener('click', closer); 
        } 
    };
    setTimeout(function() { document.addEventListener('click', closer); }, 0);
}

// Appends token to the END of the input
function mrAppendToken(myField, myValue) {
    myField.value += myValue;
    myField.focus();
    myField.selectionStart = myField.selectionEnd = myField.value.length;
}

/**
 * Show Date Fix Options Menu
 */
function mrShowDateMenu(btn) {
    const rect = btn.getBoundingClientRect();
    const menu = document.createElement('div');
    menu.className = 'myCloudContextMenu';
    
    // RTL Check
    const isRtl = document.getElementById('myCloudModal').getAttribute('dir') === 'rtl';
    
    menu.style.top = (rect.bottom + 2) + 'px';
    if(isRtl) menu.style.left = rect.left + 'px';
    else menu.style.left = (rect.right - 350) + 'px'; // Increased width alignment
    
    menu.style.width = '350px'; // Wide enough for single line text
    menu.style.visibility = 'visible';
    menu.style.backgroundColor = '#fff';
    menu.style.color = '#333';
    menu.style.border = '1px solid #ccc';
    menu.style.boxShadow = '0 4px 10px rgba(0,0,0,0.2)';

    // Labels clarify TARGET format
    const options = [
        { l: 'Target: 2021-03-20 11-38-31 (Standard)', r: '$1-$2-$3 $4-$5-$6' },
        { l: 'Target: 2021-03-20_11-38-31 (Underscore)', r: '$1-$2-$3_$4-$5-$6' },
        { l: 'Target: 2021_03_20_11_38_31 (All Underscores)', r: '$1_$2_$3_$4_$5_$6' },
        { l: 'Target: 2021.03.20 11.38.31 (Dots)', r: '$1.$2.$3 $4.$5.$6' }
    ];

    options.forEach(function(opt) {
        const div = document.createElement('div');
        div.className = 'myCloudContextItem';
        div.innerHTML = opt.l;
        div.style.color = '#333';
        div.onclick = function(e) {
            e.stopPropagation();
            mrApplyDateFix(opt.r);
            menu.remove();
        };
        menu.appendChild(div);
    });

    document.body.appendChild(menu);
    const closer = function(e) { 
        if (!menu.contains(e.target) && e.target !== btn) { 
            menu.remove(); 
            document.removeEventListener('click', closer); 
        } 
    };
    setTimeout(function() { document.addEventListener('click', closer); }, 0);
}


function mrApplyDateFix(replacePattern) {
    // [FIX] Strict Date Regex (Applied to Name Only)
    // Matches YYYYMMDD_HHMMSS (and vars) at start of name.
    // Group 7: Captures the "Rest" of the name (excluding the separator after seconds).
    // The separator `[-_. ]?` is consumed and replaced by the space in the replacement string.
    document.getElementById('mrSearch').value = '^(\\d{4})[-_.]?(\\d{2})[-_.]?(\\d{2})[-_.]?(\\d{2})[-_.]?(\\d{2})[-_.]?(\\d{2})[-_. ]?(.*)$';
    
    // Pattern: "FormattedDate Space Rest"
    // If Rest ($7) is empty, the trailing space is removed by trim() in mrUpdatePreview.
    document.getElementById('mrReplace').value = replacePattern + ' $7'; 
    document.getElementById('mrRegex').checked = true;
    mrUpdatePreview();
}


/**
 * CORE LOGIC
 */
function mrParseName(fileObj, mask, counter, idx) {
    const fullname = fileObj.name.split('/').pop();
    const dotIdx = fullname.lastIndexOf('.');
    const name = (dotIdx > 0) ? fullname.substring(0, dotIdx) : fullname;
    const ext = (dotIdx > 0) ? fullname.substring(dotIdx + 1) : '';
    const date = new Date(fileObj.date); 
    const tDate = new Date(); 
    
    let res = mask;
    res = res.replace(/\[N\]/g, name);
    // Advanced Ranges
    res = res.replace(/\[N([^\]]*)\]/g, function(match, args) {
        if (!args) return name; 
        if (args.includes(',')) {
            const parts = args.split(',');
            const start = parseInt(parts[0]);
            const len = parseInt(parts[1]);
            if (isNaN(start) || isNaN(len)) return name;
            const jsStart = (start > 0) ? start - 1 : start;
            return name.substr(jsStart, len);
        }
        if (args.includes('-')) {
            const firstDash = args.indexOf('-');
            const lastDash = args.lastIndexOf('-');
            if (firstDash === 0 && lastDash === args.length - 1 && args.length > 1) {
                const val = parseInt(args.substring(1, args.length - 1)); 
                if (!isNaN(val)) return name.substr(-val);
            }
            const parts = args.split('-');
            let start = parts[0] ? parseInt(parts[0]) : 1;
            let end = parts[1] ? parseInt(parts[1]) : name.length;
            if (start > 0) start--; 
            return name.substring(start, end);
        }
        return name;
    });

    res = res.replace(/\[E\]/g, ext);
    const cStr = String(counter).padStart(parseInt(document.getElementById('mrCountDigits').value), '0');
    res = res.replace(/\[C\]/g, cStr);
    
    const pad = function(n) { return String(n).padStart(2,'0'); };
    // File Date
    res = res.replace(/\[Y\]/g, date.getFullYear());
    res = res.replace(/\[M\]/g, pad(date.getMonth()+1));
    res = res.replace(/\[D\]/g, pad(date.getDate()));
    res = res.replace(/\[h\]/g, pad(date.getHours()));
    res = res.replace(/\[m\]/g, pad(date.getMinutes()));
    res = res.replace(/\[s\]/g, pad(date.getSeconds()));
    res = res.replace(/\[YMD\]/g, date.getFullYear() + '-' + pad(date.getMonth()+1) + '-' + pad(date.getDate()));
    
    // Today
    res = res.replace(/\[tY\]/g, tDate.getFullYear());
    res = res.replace(/\[tM\]/g, pad(tDate.getMonth()+1));
    res = res.replace(/\[tD\]/g, pad(tDate.getDate()));
    res = res.replace(/\[th\]/g, pad(tDate.getHours()));
    res = res.replace(/\[tm\]/g, pad(tDate.getMinutes()));
    res = res.replace(/\[ts\]/g, pad(tDate.getSeconds()));
    res = res.replace(/\[tYMD\]/g, tDate.getFullYear() + '-' + pad(tDate.getMonth()+1) + '-' + pad(tDate.getDate()));

    return res;
}

function mrUpdatePreview() {
    const maskN = document.getElementById('mrMaskName').value;
    const maskE = document.getElementById('mrMaskExt').value;
    const cStart = parseInt(document.getElementById('mrCountStart').value) || 1;
    const cStep  = parseInt(document.getElementById('mrCountStep').value) || 1;
    const search = document.getElementById('mrSearch').value;
    const replace = document.getElementById('mrReplace').value;
    const useRegex = document.getElementById('mrRegex').checked;
    const useCase = document.getElementById('mrCase').checked;
    
    const isRtl = document.getElementById('myCloudModal').getAttribute('dir') === 'rtl';
    const align = isRtl ? 'right' : 'left';

    let regexObj = null;
    if (search) {
        try {
            const flags = useCase ? 'g' : 'gi';
            if (useRegex) { regexObj = new RegExp(search, flags); } 
            else { regexObj = new RegExp(search.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), flags); }
        } catch(e) {}
    }

    const tbody = document.getElementById('mrPreviewBody');
    tbody.innerHTML = '';
    const newNamesSet = new Set();
    let collision = false;
    let count = cStart;
    window.mrOps = []; 

    window.mrFiles.forEach(function(f, i) {
        let newName = mrParseName(f, maskN, count, i);
        let newExt = mrParseName(f, maskE, count, i); 
        
        // [FIX] Apply Search/Replace to NAME only.
        if (regexObj) {
            newName = newName.replace(regexObj, replace);
            newName = newName.trim(); 
        }

        let fullResult = newName + (newExt ? '.' + newExt : '');

        let isDup = newNamesSet.has(fullResult.toLowerCase());
        if (isDup) collision = true;
        newNamesSet.add(fullResult.toLowerCase());

        const oldName = f.name.split('/').pop();
        const bg = (i % 2 === 0) ? '#ffffff' : '#f9f9f9';
        
        const tr = document.createElement('tr');
        tr.style.backgroundColor = bg;
        tr.innerHTML = 
            '<td style="width:auto; padding:6px 12px; color:#333; border-bottom:1px solid #eee; text-align:' + align + '; word-break:break-all;">' + oldName + '</td>' +
            '<td style="width:40px; padding:6px 8px; color:#aaa; text-align:center; border-bottom:1px solid #eee;">➜</td>' +
            '<td style="width:auto; padding:6px 12px; border-bottom:1px solid #eee; text-align:' + align + '; word-break:break-all; ' + (isDup ? 'color:#d13438;font-weight:bold;' : 'color:#0078d4; font-weight:500;') + '">' + fullResult + '</td>';
        tbody.appendChild(tr);
        window.mrOps.push({ src: f.name, new: fullResult });
        count += cStep;
    });

    const status = document.getElementById('mrStatus');
    if (collision) {
        status.innerHTML = '<svg viewBox="0 0 24 24" style="width:18px;height:18px;fill:currentColor;"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>' + myCloud_LANG.collision_warn;
        window.mrCollision = true;
    } else {
        status.textContent = '';
        window.mrCollision = false;
    }
}


function mrExecute() {
    if (window.mrCollision) { if (!confirm(myCloud_LANG.collision_ask)) return; }
    
    const maskN = document.getElementById('mrMaskName').value;
    if (maskN && maskN !== '[N]') {
        if (!myCloudState.settings.renameHistory) myCloudState.settings.renameHistory = [];
        myCloudState.settings.renameHistory = [maskN, ...myCloudState.settings.renameHistory.filter(x => x !== maskN)].slice(0,10);
        myCloudSaveSettings();
    }

    myCloudShowLoading();
    const fd = new URLSearchParams();
    fd.append('myCloud_action', 'batch_rename');
    fd.append('myCloud_key', myCloudState.key);
    fd.append('myCloud_token', myCloudCsrfToken);
    fd.append('operations', JSON.stringify(window.mrOps));

    fetch('', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        myCloudHideLoading();
        if (res.status === 'OK' || res.status === 'PARTIAL') {
            myCloudCloseModal();
            if (myCloudState.isCommanderMode) {
                 const side = myCloudState.commanderActive;
                 if (typeof refreshCommanderPane === 'function') refreshCommanderPane(side); 
            } else {
                 myCloudFetchDirectory(myCloudState.currentDir);
            }
            if (res.errors && res.errors.length > 0) alert("Errors:\n" + res.errors.join("\n"));
        } else {
            alert(res.msg);
        }
    });
}
</script>