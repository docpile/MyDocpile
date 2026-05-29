<?php
/**
 * ============================================================================
 * MODULE: Search Controller
 * ============================================================================
 * Search logic and UI. Interfaces with server based 
 * search engines (e.g., Recoll) to filter and return file matches.
 * NOTE: Executed exclusively by the client browser.
 * 
 * THIS IS PART OF THE PSEUDO "JS FILE" - SO THE NO PHP CODE IN THIS FILE!
 */
?><script>

window.myCloudSearchCursorIndex = 0;
window.myCloudSearchItems = [];

window.myCloudIndexWanted = true;

window.myCloudUpdateIndexCb = function() {
    const input = document.getElementById('myCloudSearchInput');
    const cb = document.getElementById('myCloudSearchContent');
    const lbl = document.getElementById('myCloudSearchContentLabel');
    if (!cb || !input || !lbl) return;

    if (input.value.trim() === '') {
        cb.disabled = true;
        cb.checked = false;
        lbl.style.opacity = '0.5';
        lbl.style.cursor = 'default';
    } else {
        cb.disabled = false;
        cb.checked = window.myCloudIndexWanted;
        lbl.style.opacity = '1';
        lbl.style.cursor = 'pointer';
    }
};

function myCloudSelectSearchRow(index, rows, focusOnly = false) {
    const target = rows[index];
    if (!target) return;

    window.myCloudSearchCursorIndex = index;

    if (!focusOnly) {
        rows.forEach(r => r.classList.remove('selected'));
        target.classList.add('selected');
        
        const item = window.myCloudSearchItems[index];
        if (item) {
            myCloudSearchSelection = { 
                path: item.name, 
                name: item.name.split('/').pop(), 
                isDir: item.size === 'DIR' 
            };
            myCloudUpdateSearchToolbar();
        }
    }

    rows.forEach((r, i) => {
        if (i === index) {
            r.classList.add('cursor-focus');
        } else {
            r.classList.remove('cursor-focus');
        }
    });

    target.scrollIntoView({ block: 'nearest', behavior: 'auto' });
}


// Initializes and opens the search modal dialog.
// Resets previous search state and sets up UI elements.
function myCloudAction_Search() {
    myCloudCloseContextMenus();
	window.myCloudIndexWanted = true;
    const overlay = document.getElementById('myCloudModalOverlay');
    const modal = document.getElementById('myCloudModal');

    // Maintain persistent search state
    if (!myCloudState.searchParams) {
        myCloudState.searchParams = { query: '', date: 'all', dateStart: '', dateEnd: '', size: 'all', sizeMin: '', sizeMax: '', tag: 'all', useIndex: true };
    }
    myCloudSearchSelection = null;

    const fd = new URLSearchParams({ myCloud_action: 'check_index', myCloud_key: myCloudState.key, myCloud_token: myCloudCsrfToken });
    fetch('', { method: 'POST', body: fd }).then(r=>r.json()).then(res => {
        const hasIndex = res.status === 'OK' && res.has_index;

        // Format the last update date if index exists
        const lastUpdateStr = (hasIndex && res.last_update) ? new Date(res.last_update * 1000).toLocaleString() : '';
        window.myCloudSearchLastUpdateStr = lastUpdateStr ? ((typeof myCloud_LANG !== 'undefined' && myCloud_LANG.last_indexed ? myCloud_LANG.last_indexed : 'Last updated:') + ' ' + lastUpdateStr) : '';

        overlay.style.display = 'flex';
        modal.className = 'myCloudModal search-modal';

        // Apply specific styling for the large search window.
        modal.style.maxWidth = '1400px';
        modal.style.width = '90%';
        modal.style.height = '80vh';
        modal.style.borderRadius = '0';
        modal.style.display = 'flex';
        modal.style.flexDirection = 'column';
        modal.style.overflow = 'hidden';

        let contentToggle = '';
        if (hasIndex) {
            // Start disabled & unchecked since the search field starts empty
            contentToggle = '<label id="myCloudSearchContentLabel" style="display:flex; align-items:center; gap:6px; margin-right:15px; cursor:default; font-size:13px; color:var(--text-primary); opacity:0.5;">' +
                            '<input type="checkbox" id="myCloudSearchContent" class="myCloudCheckbox" style="margin:0;" onchange="window.myCloudIndexWanted = this.checked;" disabled> ' +
                            (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.use_index ? myCloud_LANG.use_index : 'Use Index') +
                            '</label>';
        }
		

        let globalTag = myCloudState.activeTagFilter;
        let tagBtnHtml = '<button id="myCloudSearchTagBtn" class="myCloudInlineInput" data-value="' + (globalTag ? globalTag : 'all') + '" style="width:auto; min-width:115px; height:28px; margin:0 !important; cursor:pointer; padding: 0 10px; display:inline-flex; align-items:center; justify-content:space-between; gap:6px; background:var(--gray-00);" ' + 
                         (globalTag ? 'disabled title="Global tag filter active"' : '') + '>' +
                         '<span id="myCloudSearchTagLabel" style="display:flex; align-items:center; gap:6px;">' + 
                         (globalTag ? `<span class="ce-tag-dot" style="background-color:${globalTag}; width:10px; height:10px; margin:0; box-shadow:inset 0 1px 3px rgba(0,0,0,0.2);"></span> ` + window.myCloudGetTagName(globalTag) : (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.tag_any ? myCloud_LANG.tag_any : 'Any Tag')) + 
                         '</span> <span style="font-size:10px; opacity:0.5;">▼</span>' +
                         '</button>';

        // Construct modal HTML using standard string concatenation.
        modal.innerHTML = 
        '<div class="myCloudModalHeader" style="justify-content:space-between; align-items:center;">' +
            '<span><b>' + myCloudSvgLogo + '</b> <span style="font-weight:100;">- ' + myCloud_LANG.search_files + '</span></span>' +
            '<div style="display:flex; align-items:center;">' +
                '<button onclick="myCloudCloseModal()" style="background:transparent; border:none; font-size:20px; cursor:pointer; color:inherit; line-height:1;">✕</button>' +
            '</div>' +
        '</div>' +
        '<div class="myCloudModalBody ce-flex-column" style="padding:0; flex:1; overflow:hidden;">' +
            '<div class="ce-search-controls">' +
                '<div class="ce-search-row">' +
                    contentToggle +
                    '<input type="text" id="myCloudSearchInput" class="myCloudInlineInput" ' +
                           'placeholder="' + myCloud_LANG.search_ph + '" style="flex:1; height:28px; margin:0 !important;" autocomplete="off" oninput="window.myCloudUpdateIndexCb()">' +
                    '<button id="btnSearchHelp" onclick="window.myCloudShowSearchHelp(event, this)" style="height:28px; width:28px; border-radius:4px; border:1px solid var(--border-medium); background:var(--gray-05); color:var(--text-secondary); cursor:pointer; display:flex; align-items:center; justify-content:center; flex-shrink:0; font-weight:bold; margin-left:2px; margin-right:2px;" title="Syntax Help">?</button>' +
                    tagBtnHtml +
                    '<select id="myCloudSearchDate" class="myCloudInlineInput" ' +
                            'style="width:115px; height:28px; margin:0 !important; cursor:pointer;" ' +
                            'onchange="myCloudToggleSearchOptions()" autocomplete="off">' +
							'<option value="all" selected>' + (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.date_any ? myCloud_LANG.date_any : 'Any time') + '</option>' +
                        '<option value="1h">' + myCloud_LANG.date_1h + '</option>' +
                        '<option value="4h">' + myCloud_LANG.date_4h + '</option>' +
                        '<option value="24h">' + myCloud_LANG.date_24h + '</option>' +
                        '<option value="week">' + myCloud_LANG.date_week + '</option>' +
                        '<option value="month">' + myCloud_LANG.date_month + '</option>' +
                        '<option value="3months">' + myCloud_LANG.date_3m + '</option>' +
                        '<option value="year">' + myCloud_LANG.date_year + '</option>' +
                        '<option value="custom">' + myCloud_LANG.date_custom + '</option>' +
               '</select>' +
                    '<select id="myCloudSearchSize" class="myCloudInlineInput" ' +
                            'style="width:115px; height:28px; margin:0 !important; cursor:pointer;" ' +
                            'onchange="myCloudToggleSearchOptions()" autocomplete="off">' +
                        '<option value="all" selected>' + myCloud_LANG.size_any + '</option>' +
                        '<option value="small">' + myCloud_LANG.size_small + '</option>' +
                        '<option value="medium">' + myCloud_LANG.size_medium + '</option>' +
                        '<option value="large">' + myCloud_LANG.size_large + '</option>' +
                        '<option value="huge">' + myCloud_LANG.size_huge + '</option>' +
                        '<option value="custom">' + myCloud_LANG.size_custom + '</option>' +
                    '</select>' +
                        '<button onclick="window.myCloudResetSearch()" class="ce-btn-action ce-search-reset-btn" style="height:28px; padding:0 12px; border-radius:2px; font-size:13px; display:inline-flex; align-items:center; justify-content:center; background:transparent; border:1px solid var(--border-medium); color:var(--text-primary); cursor:pointer; margin-right:6px;">' +
                            (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.reset ? myCloud_LANG.reset : 'Reset') +
                        '</button>' +
                    '<button onclick="myCloudPerformSearch()" ' +
                            'class="ce-btn-action ce-btn-confirm ce-search-submit-btn" ' +
                            'style="height:28px; padding:0 12px; border-radius:2px; font-size:13px; display:inline-flex; align-items:center; justify-content:center;">' +
                        myCloud_LANG.search_btn +
                    '</button>' +
                '</div>' +
                '<div id="myCloudRowDate" class="ce-search-custom-row">' +
                    '<strong style="width:40px;">' + myCloud_LANG.col_date + ':</strong>' +
                    '<span>' + myCloud_LANG.date_from + '</span> <input type="date" id="myCloudDateStart" class="myCloudInlineInput ce-search-input-small" autocomplete="off">' +
                    '<span>' + myCloud_LANG.date_to + '</span> <input type="date" id="myCloudDateEnd" class="myCloudInlineInput ce-search-input-small" autocomplete="off">' +
                '</div>' +
                '<div id="myCloudRowSize" class="ce-search-custom-row">' +
                    '<strong style="width:40px;">' + myCloud_LANG.col_size + ':</strong>' +
                    '<span>' + myCloud_LANG.size_min + '</span> <input type="number" id="myCloudSizeMin" class="myCloudInlineInput ce-search-input-num" placeholder="0" autocomplete="off">' +
                    '<span>' + myCloud_LANG.size_max + '</span> <input type="number" id="myCloudSizeMax" class="myCloudInlineInput ce-search-input-num" placeholder="Max" autocomplete="off">' +
                '</div>' +
            '</div>' +
            '<div class="myCloudToolbar" style="flex: 0 0 auto;">' +
                '<button id="btnSearchPreview" disabled onclick="myCloudSearchAction(\'preview\')">' +
                    '<span class="myCloudIcon">' + myCloudSvg.preview + '</span>' +
                    '<span>' + myCloud_LANG.preview + '</span>' +
                '</button>' +
                (window.myCloudActionAllowed('edit_file') ? 
                '<div class="myCloudDivider"></div>' +
                '<button id="btnSearchEdit" disabled onclick="myCloudSearchAction(\'edit\')">' +
                    '<span class="myCloudIcon">' + myCloudSvg.edit_file + '</span>' +
                    '<span>' + (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.edit ? myCloud_LANG.edit : 'Edit') + '</span>' +
                '</button>' : '') +
                (window.myCloudActionAllowed('print') ? 
                '<div class="myCloudDivider"></div>' +
                '<button id="btnSearchPrint" disabled onclick="myCloudSearchAction(\'print\')">' +
                    '<span class="myCloudIcon"><svg viewBox="0 0 24 24" style="fill:#444"><path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-2-9H7v3h10V3z"/></svg></span>' +
                    '<span>' + (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.print ? myCloud_LANG.print : 'Print') + '</span>' +
                '</button>' : '') +
                '<div class="myCloudDivider"></div>' +
                '<button id="btnSearchDownload" disabled onclick="myCloudSearchAction(\'download\')">' +
                    '<span class="myCloudIcon">' + myCloudSvg.download + '</span>' +
                    '<span>' + myCloud_LANG.download + '</span>' +
                '</button>' +
                '<div class="myCloudDivider"></div>' +
                '<button id="btnSearchGoto" disabled onclick="myCloudSearchAction(\'goto\')">' +
                    '<span class="myCloudIcon"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 19H5V5h7V3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2v-7h-2v7zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7z"/></svg></span>' +
                    '<span>' + (myCloud_LANG.go_to || 'Go to') + '</span>' +
                '</button>' +
            '</div>' +
            '<div id="myCloudSearchResults" tabindex="0" style="flex:1; overflow:auto; background:transparent; outline:none;">' +
                '<div class="ce-flex-center" style="padding:20px; color:var(--text-secondary); height:100%;">' + myCloud_LANG.enter_criteria + '</div>' +
            '</div>' +
     	   '<div id="myCloudSearchStatusBar" style="height: 26px; border-top: 1px solid var(--border-default); background: var(--gray-05); display: flex; justify-content: space-between; align-items: center; padding: 0 16px; font-size: 11px; color: var(--text-secondary); flex-shrink: 0;">' +
     	       '<div id="myCloudSearchStatusLeft"></div>' +
    	        '<div id="myCloudSearchStatusRight"></div>' +
    	    '</div>' +
        '</div>';

        // Restore persistent input values
        const sParams = myCloudState.searchParams;
        document.getElementById('myCloudSearchInput').value = sParams.query;
        document.getElementById('myCloudSearchDate').value = sParams.date;
        document.getElementById('myCloudDateStart').value = sParams.dateStart;
        document.getElementById('myCloudDateEnd').value = sParams.dateEnd;
        document.getElementById('myCloudSearchSize').value = sParams.size;
        document.getElementById('myCloudSizeMin').value = sParams.sizeMin;
        document.getElementById('myCloudSizeMax').value = sParams.sizeMax;

        const tagBtn = document.getElementById('myCloudSearchTagBtn');
        if (tagBtn && !globalTag) {
            tagBtn.dataset.value = sParams.tag;
            const plainLabel = sParams.tag === 'all' ? (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.tag_any ? myCloud_LANG.tag_any : 'Any Tag') : window.myCloudGetTagName(sParams.tag);
            document.getElementById('myCloudSearchTagLabel').innerHTML = sParams.tag === 'all' ? plainLabel : `<span class="ce-tag-dot" style="background-color:${sParams.tag}; width:10px; height:10px; margin:0; box-shadow:inset 0 1px 3px rgba(0,0,0,0.2);"></span> ${plainLabel}`;

            tagBtn.onclick = (e) => {
                e.stopPropagation();
                if (typeof myCloudCloseContextMenus === 'function') myCloudCloseContextMenus();
                
                const rect = tagBtn.getBoundingClientRect();
                const menu = document.createElement('div');
                menu.className = 'myCloudContextMenu';
                menu.style.top = (rect.bottom + 2) + 'px';
                menu.style.left = rect.left + 'px';
                menu.style.maxHeight = '300px';
                menu.style.overflowY = 'auto';

                const addOption = (val, labelHtml, plainLabel) => {
                    const item = document.createElement('div');
                    item.className = 'myCloudContextItem';
                    item.innerHTML = labelHtml;
                    if (tagBtn.dataset.value === val) item.style.backgroundColor = 'var(--hover-bg-medium)';
                    item.onclick = () => {
                        menu.remove();
                        tagBtn.dataset.value = val;
                        document.getElementById('myCloudSearchTagLabel').innerHTML = val === 'all' ? plainLabel : `<span class="ce-tag-dot" style="background-color:${val}; width:10px; height:10px; margin:0; box-shadow:inset 0 1px 3px rgba(0,0,0,0.2);"></span> ${plainLabel}`;
                        
                    };
                    menu.appendChild(item);
                };

                addOption('all', `<span style="width:20px;"></span> ${typeof myCloud_LANG !== 'undefined' && myCloud_LANG.tag_any ? myCloud_LANG.tag_any : 'Any Tag'}`, typeof myCloud_LANG !== 'undefined' && myCloud_LANG.tag_any ? myCloud_LANG.tag_any : 'Any Tag');
                const colors = ['#e81123', '#0078d4', '#107c10', '#f0ad4e', '#888888'];
                colors.forEach(c => addOption(c, `<span class="ce-tag-dot" style="background-color:${c}; width:12px; height:12px; margin-right:8px; box-shadow:inset 0 1px 3px rgba(0,0,0,0.2);"></span> ${window.myCloudGetTagName(c)}`, window.myCloudGetTagName(c)));

                document.body.appendChild(menu);
                if (typeof myCloudApplyTheme === 'function') myCloudApplyTheme();
                setTimeout(() => { document.addEventListener('click', () => menu.remove(), {once: true}); }, 50);
            };
        }



        // Set focus to the search input field.
        const input = document.getElementById('myCloudSearchInput');
        setTimeout(() => input.focus(), 50);

        // Restore Index Checkbox State
        const contentCb = document.getElementById('myCloudSearchContent');
        if (contentCb) {
            window.myCloudIndexWanted = sParams.useIndex;
            if (typeof window.myCloudUpdateIndexCb === 'function') window.myCloudUpdateIndexCb();
        }
        myCloudToggleSearchOptions();

        // Bind global key handlers for the modal (Enter to search, Escape to close).
        modal.setAttribute('tabindex', '-1');
        modal.onkeydown = (e) => {
            if (e.key === 'Escape') {
                document.getElementById('myCloudModalOverlay').style.display = 'none';
                return;
            }
            
            const isInputFocused = document.activeElement.tagName === 'INPUT' || document.activeElement.tagName === 'SELECT';
            const searchContainer = document.getElementById('myCloudSearchResults');
            if (!searchContainer) return;
            const rows = Array.from(searchContainer.querySelectorAll('.myCloudRow'));

            if (e.key === 'Enter') {
                e.preventDefault();
                if (isInputFocused) {
                    myCloudPerformSearch();
                } else {
                    if (rows[window.myCloudSearchCursorIndex]) {
                        rows[window.myCloudSearchCursorIndex].ondblclick();
                    }
                }
                return;
            }

            if (rows.length > 0 && !isInputFocused) {
                let newIdx = window.myCloudSearchCursorIndex;
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    if (newIdx < rows.length - 1) newIdx++;
                    myCloudSelectSearchRow(newIdx, rows, false);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (newIdx > 0) newIdx--;
                    myCloudSelectSearchRow(newIdx, rows, false);
                } else if (e.key === ' ') {
                    e.preventDefault();
                    myCloudSelectSearchRow(window.myCloudSearchCursorIndex, rows, false);
                }
            } else if (rows.length > 0 && isInputFocused && e.key === 'ArrowDown') {
                e.preventDefault();
                document.getElementById('myCloudSearchResults').focus();
                myCloudSelectSearchRow(window.myCloudSearchCursorIndex, rows, true); // Just focus visually
            }
        };
        // Re-render results if they exist, or show empty state if we had a blank search
        if (myCloudState.searchResults && myCloudState.searchResults.length > 0) {
            myCloudRenderSearchResultsTable();
        } else if (sParams.query || sParams.date !== 'all' || sParams.size !== 'all' || sParams.tag !== 'all') {
            myCloudRenderSearchResultsTable();
        }
    }).catch(() => {});
}

// Displays an XXL tooltip explaining advanced search syntax.
// Auto-closes when clicking outside.
window.myCloudShowSearchHelp = function(e, btn) {
    e.stopPropagation();
    let existing = document.getElementById('myCloudSearchHelpTooltip');
    if (existing) {
        existing.remove();
        return;
    }

    const tip = document.createElement('div');
    tip.id = 'myCloudSearchHelpTooltip';
    tip.style.cssText = 'position:fixed; z-index:21000; background:var(--gray-00); border:1px solid var(--border-medium); box-shadow:0 8px 24px rgba(0,0,0,0.2); border-radius:6px; padding:16px; font-size:14px; color:var(--text-primary); width:350px; font-family:monospace; line-height:1.3;';
    
    tip.innerHTML = 
		'<table style="width:100%; border-collapse:collapse;">' +
             '<tr><td style="padding:2px 0; color:var(--accent-primary); width:30%;">INDEX:</td><td></td></tr>' +
             '<tr><td style="padding:2px 0; color:var(--accent-primary); width:30%;">*</td><td>inv*2024.pdf</td></tr>' +
             '<tr><td style="padding:2px 0; color:var(--accent-primary);">?</td><td>img_00?.jpg</td></tr>' +
             '<tr><td style="padding:2px 0; color:var(--accent-primary);">""</td><td>"exact phrase"</td></tr>' +
             '<tr><td style="padding:2px 0; color:var(--accent-primary);">" "N</td><td>"tax audit"10 <span style="font-size:10px; color:var(--text-secondary);">(proximity)</span></td></tr>' +
             '<tr><td style="padding:2px 0; color:var(--accent-primary);">ext:</td><td>tax ext:pdf</td></tr>' +
             '<tr><td style="padding:2px 0; color:var(--accent-primary);">dir:</td><td>dir:invoices 2024</td></tr>' +
             '<tr><td style="padding:2px 0; color:var(--accent-primary);">fn:</td><td>fn:receipt* <span style="font-size:10px; color:var(--text-secondary);">(filename)</span></td></tr>' +
             '<tr><td style="padding:2px 0; color:var(--accent-primary);">author:</td><td>author:john</td></tr>' +
             '<tr><td style="padding:2px 0; color:var(--accent-primary);">title:</td><td>title:report</td></tr>' +
             '<tr><td style="padding:2px 0; color:var(--accent-primary);">OR/AND</td><td>apple OR orange</td></tr>' +
             '<tr><td style="padding:2px 0; color:var(--accent-primary);">( )</td><td>(apple OR orange) AND juice</td></tr>' +
             '<tr><td style="padding:2px 0; color:var(--accent-primary);">-</td><td>apple -orange</td></tr>' +
         '</table>';
    document.body.appendChild(tip);
    if (typeof myCloudApplyTheme === 'function') myCloudApplyTheme();

    const rect = btn.getBoundingClientRect();
    tip.style.top = (rect.bottom + 6) + 'px';
    let left = rect.right - 300; // Align right edge with the button
    if (left < 10) left = 10; // Prevent clipping on small screens
    tip.style.left = left + 'px';

    setTimeout(() => {
        const closer = (ev) => {
            if (!tip.contains(ev.target)) {
                tip.remove();
                document.removeEventListener('click', closer);
            }
        };
        document.addEventListener('click', closer);
    }, 10);
};


// Collects search parameters and sends request to server.
// Updates UI with loading state and then results.
function myCloudPerformSearch() {
    const query = document.getElementById('myCloudSearchInput').value.trim();
    const dRange = document.getElementById('myCloudSearchDate').value;
    const sRange = document.getElementById('myCloudSearchSize').value;
    const contentCb = document.getElementById('myCloudSearchContent');
    
    const tagBtn = document.getElementById('myCloudSearchTagBtn');
    const tagFilter = tagBtn ? (tagBtn.dataset.value || 'all') : 'all';
    
    // Explicitly enforce file system search if query is blank
    const useIndex = (query !== '' && contentCb && contentCb.checked) ? '1' : '0';

    if (!query && (dRange === 'all' || dRange === 'custom') && sRange === 'all' && tagFilter === 'all' && useIndex === '0') return;

    // Save persistent params
    myCloudState.searchParams = {
        query: query,
        date: dRange,
        dateStart: document.getElementById('myCloudDateStart').value,
        dateEnd: document.getElementById('myCloudDateEnd').value,
        size: sRange,
        sizeMin: document.getElementById('myCloudSizeMin').value,
        sizeMax: document.getElementById('myCloudSizeMax').value,
        tag: tagFilter,
        useIndex: contentCb ? contentCb.checked : false
    };

    const container = document.getElementById('myCloudSearchResults');
    container.innerHTML = 
    '<div class="myCloud-loading-container" style="padding:40px 0; color:var(--text-secondary);">' +
        '<div class="myCloud-spinner dark"></div>' +
        '<div>' + myCloud_LANG.searching + '</div>' +
    '</div>';

    const statusRight = document.getElementById('myCloudSearchStatusRight');
    if (statusRight) statusRight.textContent = '';
    const statusLeft = document.getElementById('myCloudSearchStatusLeft');
    if (statusLeft) statusLeft.textContent = '';

    const params = new URLSearchParams({
        myCloud_action: 'search',
        myCloud_key: myCloudState.key,
        myCloud_token: myCloudCsrfToken,
        query: query,
        dir: myCloudState.currentDir,
        content_search: useIndex,
        
        // Date Params
        date_range: dRange,
        custom_date_start: document.getElementById('myCloudDateStart').value,
        custom_date_end:   document.getElementById('myCloudDateEnd').value,

        // Size Params
        size_range: sRange,
        custom_size_min: document.getElementById('myCloudSizeMin').value,
         custom_size_max: document.getElementById('myCloudSizeMax').value,
         tag_filter: tagFilter
    });

    fetch('', { method: 'POST', body: params })
        .then(myCloudCheckResponse)
        .then(resp => {
            if (resp.status === 'OK') {
                myCloudState.searchResults = resp.data;
                myCloudRenderSearchResultsTable(); 
            } else {
                container.innerHTML = 
                '<div class="ce-flex-center" style="padding:20px; color:var(--danger); height:100%;">' +
                    myCloud_LANG.error_prefix + ': ' + resp.msg +
                '</div>';
            }
        })
        .catch(err => {
            container.innerHTML = '<div class="ce-flex-center" style="height:100%;">' + myCloud_LANG.request_failed + '</div>';
        });
}

// Completely resets the search state and re-renders the empty dialog
window.myCloudResetSearch = function() {
    myCloudState.searchParams = { query: '', date: 'all', dateStart: '', dateEnd: '', size: 'all', sizeMin: '', sizeMax: '', tag: 'all', useIndex: true };
    myCloudState.searchResults = [];
    myCloudSearchSelection = null;
    
    document.getElementById('myCloudSearchInput').value = '';
    document.getElementById('myCloudSearchDate').value = 'all';
    document.getElementById('myCloudDateStart').value = '';
    document.getElementById('myCloudDateEnd').value = '';
    document.getElementById('myCloudSearchSize').value = 'all';
    document.getElementById('myCloudSizeMin').value = '';
    document.getElementById('myCloudSizeMax').value = '';
    
    const tagBtn = document.getElementById('myCloudSearchTagBtn');
    if (tagBtn) {
        tagBtn.dataset.value = 'all';
        const plainLabel = typeof myCloud_LANG !== 'undefined' && myCloud_LANG.tag_any ? myCloud_LANG.tag_any : 'Any Tag';
        document.getElementById('myCloudSearchTagLabel').innerHTML = plainLabel;
    }
    
    window.myCloudIndexWanted = true;
    if (typeof window.myCloudUpdateIndexCb === 'function') window.myCloudUpdateIndexCb();
    myCloudToggleSearchOptions();
    
    const container = document.getElementById('myCloudSearchResults');
    if (container) {
        container.innerHTML = '<div class="ce-flex-center" style="padding:20px; color:var(--text-secondary); height:100%;">' + (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.enter_criteria ? myCloud_LANG.enter_criteria : 'Enter search criteria') + '</div>';
    }
    myCloudUpdateSearchToolbar();
    document.getElementById('myCloudSearchInput').focus();
};

// Toggles visibility of custom date/size inputs.
// Triggered by dropdown changes.
function myCloudToggleSearchOptions() {
    const dVal = document.getElementById('myCloudSearchDate').value;
    const sVal = document.getElementById('myCloudSearchSize').value;
    
    document.getElementById('myCloudRowDate').style.display = (dVal === 'custom') ? 'flex' : 'none';
    document.getElementById('myCloudRowSize').style.display = (sVal === 'custom') ? 'flex' : 'none';
}

// Renders the search results table based on current state or provided data.
// Handles column sorting and row interactions.
function myCloudRenderSearchResultsTable(dataOverride = null) {
    const container = document.getElementById('myCloudSearchResults');
    container.innerHTML = '';

    // Determine data source: override or global state.
    let items = dataOverride || myCloudState.searchResults;
    const st = myCloudState;

    // Reset selection and toolbar UI.
    myCloudSearchSelection = null;
    myCloudUpdateSearchToolbar();

    // Display "No results" message if empty.
    if (!items || items.length === 0) {
        container.innerHTML = '<div style="padding:20px; text-align:center; color:var(--text-secondary);">' + myCloud_LANG.no_results + '</div>';'</div>';
        return;
    }

    // Sort items based on current sort configuration.
    // Handles name, size, date, and location sorting.
    let sortedItems = [...items].sort((a, b) => {
        const dir = st.searchSort.dir;
        const col = st.searchSort.col;

        const isADir = a.size === 'DIR';
        const isBDir = b.size === 'DIR';

        const aName = a.name.split('/').pop().toLowerCase();
        const bName = b.name.split('/').pop().toLowerCase();
        const aLoc = a.name.substring(0, a.name.lastIndexOf('/') || 0).toLowerCase();
        const bLoc = b.name.substring(0, b.name.lastIndexOf('/') || 0).toLowerCase();

        let val = 0;

        if (col === 'size') {
            if (isADir && !isBDir) return -1;
            if (!isADir && isBDir) return 1;
            val = (parseInt(a.size) - parseInt(b.size)) * dir;
        }
        else if (col === 'date') {
            if (a.date < b.date) val = -1 * dir;
            else if (a.date > b.date) val = 1 * dir;
        }
        else if (col === 'location') {
            if (aLoc < bLoc) val = -1 * dir;
            else if (aLoc > bLoc) val = 1 * dir;
        }
        else {
            if (aName < bName) val = -1 * dir;
            else if (aName > bName) val = 1 * dir;
        }
        return val === 0 ? aName.localeCompare(bName) : val;
    });

    // Build the results table structure.
    const table = document.createElement('table');
    table.className = 'myCloudTable';
    table.style.width = '100%';

    const thead = table.createTHead();
    const rowHeader = thead.insertRow();

    // Define table columns configuration.
    const cols = [
        { title: '', key: null, width: '10px' },
        { title: myCloud_LANG.col_name, key: 'name', width: 'auto' },
        { title: myCloud_LANG.col_size, key: 'size', width: '20px', align: 'center' },
        { title: myCloud_LANG.col_date, key: 'date', width: '30px', align: 'center' },
        { title: myCloud_LANG.col_loc, key: 'location', width: 'auto', align: 'left' }
    ];

    // Create table headers with sort click handlers.
    cols.forEach(c => {
        const th = document.createElement('th');
        th.textContent = c.title;
        th.style.setProperty('top', '0px', 'important'); 
        if (c.width && c.width !== 'auto') th.style.width = c.width;
        th.style.textAlign = c.align || 'left';

        if (c.key) {
            th.style.cursor = 'pointer';
            if (st.searchSort.col === c.key) {
                th.textContent += (st.searchSort.dir === 1 ? ' ▲' : ' ▼');
                th.style.color = 'var(--accent-color)';
            }
            th.onclick = () => {
                if (st.searchSort.col === c.key) {
                    st.searchSort.dir *= -1;
                } else {
                    st.searchSort.col = c.key;
                    st.searchSort.dir = 1;
                }
                myCloudRenderSearchResultsTable();
            };
        }
        rowHeader.appendChild(th);
    });

    const tbody = table.createTBody();
    const sizeFilter = document.getElementById('myCloudSearchSize') ? document.getElementById('myCloudSearchSize').value : 'all';

    window.myCloudSearchItems = [];
    window.myCloudSearchCursorIndex = 0;

    // Populate table rows.
    sortedItems.forEach(i => {
        const isDir = i.size === 'DIR';
        
        // Hide folders if filtering by file size.
        if (isDir && sizeFilter !== 'all') return;

        window.myCloudSearchItems.push(i);
        const rowIndex = window.myCloudSearchItems.length - 1;

        const row = tbody.insertRow();
        row.className = 'myCloudRow';
        const fullPath = i.name;
        const fileName = fullPath.split('/').pop();
        const parentDir = fullPath.substring(0, fullPath.lastIndexOf('/') || 0) || '/';
        const ext = fileName.split('.').pop().toLowerCase();
        const isPreviewable = !isDir && myCloudIsPreviewable(ext);

        // Render Icon Cell.
        const iconCell = row.insertCell();
        const iconSpan = document.createElement('span');
        iconSpan.className = 'myCloudIcon';
        iconSpan.innerHTML = isDir ? myCloudIconFolder : myCloudIconFile;
        iconCell.appendChild(iconSpan);

        // Render Name Cell with hover actions container.
        const nameCell = row.insertCell();
		nameCell.style.textAlign = 'left';
		nameCell.style.width = 'auto';
        nameCell.style.position = 'relative'; 
        nameCell.style.overflow = 'hidden';
        
        const contentDiv = document.createElement('div');
        contentDiv.className = 'ce-row-content';
        
        const textSpan = document.createElement('span');
        textSpan.className = 'ce-name-text';
        textSpan.textContent = i.name.split('/').pop();
        contentDiv.appendChild(textSpan);
        
        nameCell.appendChild(contentDiv);
//        nameCell.style.overflow = 'hidden';
//        nameCell.style.textOverflow = 'ellipsis';
//        nameCell.style.whiteSpace = 'nowrap';
//        nameCell.style.maxWidth = '250px';

        // Render Size Cell.
        const sizeCell = row.insertCell();
        sizeCell.textContent = isDir ? myCloud_LANG.folder : myCloudFormatBytes(parseInt(i.size));
        sizeCell.style.textAlign = 'right';

        // Render Date Cell.
        const dateCell = row.insertCell();
		dateCell.textContent = i.date;
		dateCell.style.setProperty('width', '21px', 'important');

        // Render Location Cell.
        const locCell = row.insertCell();
        locCell.textContent = parentDir;
        locCell.style.color = 'var(--text-secondary)';
        locCell.style.fontSize = '12px';
        locCell.style.setProperty('text-align', 'left', 'important');

        // Row click: Select item and update cursor focus.
        row.onclick = () => {
            const rows = Array.from(tbody.querySelectorAll('.myCloudRow'));
            myCloudSelectSearchRow(rowIndex, rows, false);
        };

        // Row double-click: Open folder or file.
        row.ondblclick = () => {
            if (isDir) {
                document.getElementById('myCloudModalOverlay').style.display = 'none';
                if (!myCloudState.allItems.some(item => item.name === fullPath)) {
                    myCloudState.allItems.push({ name: fullPath, size: 'DIR', date: i.date });
                }
                myCloudState.currentDir = fullPath;
                myCloudExpandToPath(fullPath);
                myCloudFetchDirectory(fullPath);
            } else {
                if (previewExts.includes(ext)) {
                    myCloudDownloadFile(fullPath, fileName, true);
                } else {
                    myCloudDownloadFile(fullPath, fileName, false);
                }
            }
        };
    });

    container.appendChild(table);

    // After populating, assign focus to the first item (visually only)
    if (window.myCloudSearchItems.length > 0) {
        setTimeout(() => {
            const rows = Array.from(tbody.querySelectorAll('.myCloudRow'));
            myCloudSelectSearchRow(0, rows, true); // Focus only, do not select
        }, 50);
    }

    // Update Status Bar
    const statusLeft = document.getElementById('myCloudSearchStatusLeft');
    const statusRight = document.getElementById('myCloudSearchStatusRight');
    if (statusRight) {
        statusRight.textContent = (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.search_hits ? myCloud_LANG.search_hits : 'Hits:') + ' ' + sortedItems.length;
    }
    if (statusLeft) {
        const contentCb = document.getElementById('myCloudSearchContent');
        statusLeft.textContent = (contentCb && contentCb.checked && window.myCloudSearchLastUpdateStr) ? window.myCloudSearchLastUpdateStr : '';
    }
	
}

// Updates the toolbar buttons in the search modal based on selection.
// Disables preview/download if no valid item is selected.
function myCloudUpdateSearchToolbar() {
    const sel = myCloudSearchSelection;
    const btnPreview = document.getElementById('btnSearchPreview');
    const btnEdit = document.getElementById('btnSearchEdit');
    const btnPrint = document.getElementById('btnSearchPrint');
    const btnDownload = document.getElementById('btnSearchDownload');
	const btnGoto = document.getElementById('btnSearchGoto');
    
    if (!btnPreview || !btnDownload) return;

    if (!sel) {
        btnPreview.disabled = true;
        if (btnEdit) btnEdit.disabled = true;
        if (btnPrint) btnPrint.disabled = true;
        btnDownload.disabled = true;
		if (btnGoto) btnGoto.disabled = true;
        return;
    }

    // Disable download for directories.
    btnDownload.disabled = sel.isDir;
	if (btnGoto) btnGoto.disabled = false;

    // Check if file extension supports preview.
    const ext = sel.name.split('.').pop().toLowerCase();
    btnPreview.disabled = sel.isDir || !previewExts.includes(ext);

    const isInsideZip = /\.zip(\/|$)/i.test(sel.path);
    if (btnEdit) {
        btnEdit.disabled = sel.isDir || !(typeof window.myCloudIsFileEditable === 'function' && window.myCloudIsFileEditable(sel.path, isInsideZip));
    }
    if (btnPrint) {
        const officeExts = ['docx', 'xlsx', 'pptx', 'doc', 'xls', 'ppt', 'odt', 'ods', 'odp', 'rtf', 'csv'];
        btnPrint.disabled = sel.isDir || !(typeof myCloudHasOnlyOffice !== 'undefined' && myCloudHasOnlyOffice && (officeExts.includes(ext) || ext === 'pdf') && !isInsideZip);
    }

}

// Handles click events for the search modal toolbar.
// Triggers file preview or download.
function myCloudSearchAction(action) {
    const sel = myCloudSearchSelection;
    if (!sel) return;

	if (action === 'preview') {
         myCloudDownloadFile(sel.path, sel.name, true);
    } else if (action === 'edit') {
        if (typeof window.myCloudAction_EditFile === 'function') {
            myCloudState.selectedFiles = [sel.path];
            window.myCloudAction_EditFile(sel.path);
        }
    } else if (action === 'print') {
        if (typeof window.myCloudAction_Print === 'function') {
            window.myCloudAction_Print(sel.path);
        }
    } else if (action === 'download') {
         myCloudDownloadFile(sel.path, sel.name, false);
    } else if (action === 'goto') {
        // Close the search modal
        document.getElementById('myCloudModalOverlay').style.display = 'none';
        
        // Navigate to the location in the main UI
        const parentDir = sel.path.substring(0, sel.path.lastIndexOf('/')) || '/';
        
        if (sel.isDir) {
            myCloudState.currentDir = sel.path;
            myCloudExpandToPath(sel.path);
            myCloudFetchDirectory(sel.path).then(() => {
                if (typeof myCloudRenderUI === 'function') myCloudRenderUI();
            });
        } else {
            myCloudState.currentDir = parentDir;
            myCloudExpandToPath(sel.path);
            myCloudFetchDirectory(parentDir).then(() => {
                if (typeof myCloudSeekAndSelect === 'function') {
                    myCloudSeekAndSelect(sel.path);
                }
            });
        }
    }
}
</script>