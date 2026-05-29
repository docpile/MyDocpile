<?php
/**
 * ============================================================================
 * MODULE: Editor UI JavaScript Engine
 * ============================================================================
 * Contains the frontend user interface components and instantiation logic 
 * required to render and interact with the integrated code/text editor.
 * Static JavaScript for the Editor Module. Safe for caching and minification.
 * NOTE: Executed exclusively by the client browser.
 * 
 * THIS IS PART OF THE PSEUDO "JS FILE" - SO THE NO PHP CODE IN THIS FILE!
 */
?>
<script>
// === DOM INJECTION ===
// Lazily injects the editor HTML into the DOM only when first needed
function ceInitEditorDOM() {
    if (document.getElementById('myCloudEditor_modal_wrap')) return;
    
    const wrapper = document.createElement('div');
    wrapper.innerHTML = `
        <div id="myCloudEditor_modal_wrap">
          <div class="myCloudEditor-window">
              <div id="myCloudEditor_toolbar">
                  <div id="myCloudEditor_tabs"></div>

                  <div class="editor-action-group">
                       <select id="ceSyntaxSelect" class="editor-syntax-select" title="Syntax Highlighting"></select>
                       <button id="btn_diff_toggle" class="editor-btn" onclick="myCloudEditor_toggleDiff()" title="Compare with Original (Split View)">
                           <svg viewBox="0 0 24 24" width="18" height="18"><path fill="#00d478" d="M3 3h8v18H3z"/><path fill="#ad4ef0" d="M13 3h8v18h-8z"/></svg>
                       </button>
                       <button id="btn_minimap_toggle" class="editor-btn" onclick="myCloudEditor_toggleMinimap()" title="Toggle Code Minimap">
                           <svg viewBox="0 0 24 24" width="18" height="18"><path fill="#cccccc" d="M3 3h14v18H3z"/><path fill="#0078d4" d="M17 3h4v18h-4z"/></svg>
                       </button>
                       <button class="editor-btn" onclick="myCloudEditor_toggle_wordwrap()" title="Toggle Word Wrap">
                          <svg viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M4 19h6v-2H4v2zM20 5H4v2h16V5zm-3 6H4v2h13.25c1.1 0 2 .9 2 2s-.9 2-2 2H15v-2l-3 3 3 3v-2h2.25c2.21 0 4-1.79 4-4s-1.79-4-4-4z"/></svg>
                       </button>
                       <button id="btn_invisibles" class="editor-btn" onclick="myCloudEditor_toggleInvisibles()" title="Show Invisible Characters">
                          <svg viewBox="0 0 24 24"><path d="M10 4v16h2V6h2v14h2V4h2V2H8c-2.21 0-4 1.79-4 4s1.79 4 4 4h2v10h-2v-2z"/></svg>
                       </button>
                  </div>

                  <div class="editor-action-group" style="display:flex; align-items:center; gap:4px; margin-right:8px;">
                       <button class="editor-btn" onclick="myCloudEditor_undo()" title="Undo">
                          <svg viewBox="0 0 24 24" width="18" height="18"><path fill="#0078d4" d="M12.5 8c-2.65 0-5.05.99-6.9 2.6L2 7v9h9l-3.62-3.62c1.39-1.16 3.16-1.88 5.12-1.88 3.54 0 6.55 2.31 7.6 5.5l2.37-.78C21.08 11.03 17.15 8 12.5 8z"/></svg>
                       </button>
                       <button class="editor-btn" onclick="myCloudEditor_redo()" title="Redo">
                           <svg viewBox="0 0 24 24" width="18" height="18"><path fill="#107c10" d="M18.4 10.6C16.55 9 14.15 8 11.5 8c-4.65 0-8.58 3.03-9.96 7.22L3.9 16c1.05-3.19 4.05-5.5 7.6-5.5 1.95 0 3.73.72 5.12 1.88L13 16h9V7l-3.6 3.6z"/></svg>
                       </button>
                  </div>
                  
                  <div class="editor-action-group" style="display:flex; align-items:center; gap:4px; margin-right:8px;">
                       <button class="editor-btn" onclick="myCloudEditor_zoom_out()" title="Zoom Out">
                          <svg viewBox="0 0 24 24" style="width: 14px !important; height: 14px !important;">
                              <path fill="currentColor" d="M 8.5 3 h 2 L 15 19 h -2.5 l -1.2 -3.5 h -4.6 L 5.5 19 H 3 L 8.5 3 z M 7.5 13.5 h 4 L 9.5 7.5 L 7.5 13.5 z"/>
                              <path fill="currentColor" d="M 19 21 L 14 14 H 17.5 V 4 H 20.5 V 14 H 24 L 19 21 Z"/>
                          </svg>
                       </button>
                       <button class="editor-btn" onclick="myCloudEditor_zoom_in()" title="Zoom In">
                          <svg viewBox="0 0 24 24" style="width: 22px !important; height: 22px !important;">
                              <path fill="currentColor" d="M 8.5 3 h 2 L 15 19 h -2.5 l -1.2 -3.5 h -4.6 L 5.5 19 H 3 L 8.5 3 z M 7.5 13.5 h 4 L 9.5 7.5 L 7.5 13.5 z"/>
                              <path fill="currentColor" d="M 19 3 L 14 10 H 17.5 V 20 H 20.5 V 10 H 24 L 19 3 Z"/>
                          </svg>
                       </button>
                  </div>

                  <div class="editor-action-group">
                       <button id="btn_search_toggle" class="editor-btn" onclick="myCloudEditor_toggleSearchBar()" title="Search & Replace (Ctrl+F)">
                           🔍
                       </button>
                  </div>

                  <div class="editor-action-group" style="margin-left: auto;">
                       <button class="editor-btn" onclick="document.getElementById('myCloudEditor_helpOverlay').style.display='flex'" title="Keyboard Shortcuts">
                           <svg viewBox="0 0 24 24" width="18" height="18"><path fill="#10107c" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2 .9-2 2H8c0-2.21 1.79-4 4-4s4 1.79 4 4c0 .88-.36 1.68-.93 2.25z"/></svg>
                       </button>
                       <button class="editor-btn save-btn" id="myCloud_save" onclick="myCloudEditor_save()" title="Save (Ctrl+S)">
                            💾
                        </button>
                       <div style="width:1px; height:20px; background:#e0e0e0; margin:0 4px;"></div>
                       <button class="editor-btn" onclick="myCloudEditor_minimize()" title="Minimize">
                           <svg viewBox="0 0 24 24"><path d="M19 13H5v-2h14v2z"/></svg>
                       </button>
                       <button class="editor-btn close-btn" onclick="myCloudEditor_close()" title="Close Tab">
                           <svg viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 17.59 17.59 13.41 12 19 6.41z"/></svg>
                       </button>
                  </div>
              </div>

              <div id="myCloudEditor_search_bar" style="display: none; align-items: center; gap: 8px; padding: 6px 12px; background: #fafafa; border-bottom: 1px solid #e5e5e5; height: 46px; box-sizing: border-box; white-space: nowrap; overflow-x: auto;">
                  <div style="display: flex; align-items: center; position: relative; height: 32px; flex: 1; min-width: 150px;">
                      <input type="text" id="myCloud_search_input" style="width: 100%; height: 100%; padding: 0 50px 0 8px; margin: 0; border: 1px solid #ccc; border-radius: 4px; font-size: 13px; box-sizing: border-box; outline: none;" placeholder="Find..." autocomplete="off">
                      <span id="myCloudEditor_searchCount" style="position: absolute; right: 8px; font-size: 11px; color: #888; font-weight: 600; pointer-events: none; line-height: 32px;"></span>
                  </div>

                  <button class="editor-btn" style="height:32px; width:36px; border-radius:4px; flex-shrink:0; background: #fff; border: 1px solid #ccc;" onclick="myCloudEditor_doSearch(true)" title="Previous">
                      <svg viewBox="0 0 24 24" style="width:20px;height:20px;fill:#444;"><path d="M15.41 16.59L10.83 12l4.58-4.59L14 6l-6 6 6 6 1.41-1.41z"/></svg>
                  </button>
                  <button class="editor-btn" style="height:32px; width:36px; border-radius:4px; flex-shrink:0; background: #fff; border: 1px solid #ccc;" onclick="myCloudEditor_doSearch(false)" title="Next">
                      <svg viewBox="0 0 24 24" style="width:20px;height:20px;fill:#444;"><path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6-1.41-1.41z"/></svg>
                  </button>
                  
                  <div style="width: 1px; height: 24px; background: #ccc; margin: 0 4px; flex-shrink: 0;"></div>
                  
                  <input type="text" id="myCloud_replace_input" style="height: 32px; padding: 0 8px; margin: 0; border: 1px solid #ccc; border-radius: 4px; font-size: 13px; box-sizing: border-box; outline: none; flex: 1; min-width: 150px;" placeholder="Replace with..." autocomplete="off">
                  
                  <button style="height:32px; padding:0 12px; margin:0; border:1px solid #ccc; border-radius:4px; background:#fff; cursor:pointer; font-size:13px; flex-shrink:0; color:#333;" onmouseover="this.style.background='#e6f2ff'; this.style.borderColor='#0078d4'; this.style.color='#0078d4';" onmouseout="this.style.background='#fff'; this.style.borderColor='#ccc'; this.style.color='#333';" onclick="myCloudEditor_replace()">Replace</button>
                  <button style="height:32px; padding:0 12px; margin:0; border:1px solid #ccc; border-radius:4px; background:#fff; cursor:pointer; font-size:13px; flex-shrink:0; color:#333;" onmouseover="this.style.background='#e6f2ff'; this.style.borderColor='#0078d4'; this.style.color='#0078d4';" onmouseout="this.style.background='#fff'; this.style.borderColor='#ccc'; this.style.color='#333';" onclick="myCloudEditor_replaceAll()">All</button>
              </div>

              <div class="myCloudEditor-body">
                  <div id="myCloudEditor_aceContainer"></div>
                  <div id="myCloudEditor_onlyOfficeContainer" style="display:none; flex:1; height:100%;"></div>
                  <div id="myCloudEditor_aceContainerSplit"></div>
                  <div id="myCloudEditor_minimap"></div>
              </div>

              <div id="myCloudEditor_statusbar">
                  <span id="ce_stat_line">Ln 1, Col 1</span>
                  <span id="ce_stat_sel" style="margin-left: 10px; color: #888;"></span>
                  <span id="ce_stat_total" style="margin-left: 15px;">Total: 1 lines</span>
                  <span id="ce_stat_chars" style="margin-left: 10px; color: #888;">0 chars</span>
                  <span style="flex:1"></span>
                  <span id="ce_stat_dirty" style="color:#d83b01; font-weight:bold; display:none; margin-right: 15px;">Unsaved Draft Active</span>
                  <span id="ce_stat_mode" style="font-weight: 500; text-transform:uppercase;">TEXT</span>
              </div>

              <div id="myCloudEditor_helpOverlay" onclick="this.style.display='none'">
                  <div class="ce-help-box" onclick="event.stopPropagation()">
                      <div style="padding: 16px 20px; border-bottom: 1px solid #e5e5e5; display: flex; align-items: center; justify-content: space-between;">
                          <h3 style="margin: 0; font-size: 16px; color: #111;">Keyboard Shortcuts</h3>
                          <button class="editor-btn close-btn" style="background:none; border:none;" onclick="document.getElementById('myCloudEditor_helpOverlay').style.display='none'">
                              <svg viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 17.59 17.59 13.41 12 19 6.41z"/></svg>
                          </button>
                      </div>
                      <div style="flex: 1; overflow-y: auto; padding: 10px 0;">
                          <table>
                              <tr><th>Action</th><th>Windows / Linux</th><th>Mac</th></tr>
                              <tr><td>Save Document</td><td><kbd>Ctrl</kbd> + <kbd>S</kbd></td><td><kbd>Cmd</kbd> + <kbd>S</kbd></td></tr>
                              <tr><td>Find / Replace</td><td><kbd>Ctrl</kbd> + <kbd>F</kbd></td><td><kbd>Cmd</kbd> + <kbd>F</kbd></td></tr>
                              <tr><td>Multi-Cursor Selection</td><td><kbd>Ctrl</kbd> + <kbd>Click</kbd></td><td><kbd>Cmd</kbd> + <kbd>Click</kbd></td></tr>
                              <tr><td>Select Next Match</td><td><kbd>Ctrl</kbd> + <kbd>D</kbd></td><td><kbd>Cmd</kbd> + <kbd>D</kbd></td></tr>
                              <tr><td>Move Line Up/Down</td><td><kbd>Alt</kbd> + <kbd>↑ / ↓</kbd></td><td><kbd>Opt</kbd> + <kbd>↑ / ↓</kbd></td></tr>
                              <tr><td>Copy Line Up/Down</td><td><kbd>Alt</kbd> + <kbd>Shift</kbd> + <kbd>↑ / ↓</kbd></td><td><kbd>Opt</kbd> + <kbd>Shift</kbd> + <kbd>↑ / ↓</kbd></td></tr>
                              <tr><td>Go To Line</td><td><kbd>Ctrl</kbd> + <kbd>L</kbd></td><td><kbd>Cmd</kbd> + <kbd>L</kbd></td></tr>
                              <tr><td>Fold/Unfold Code</td><td><kbd>Alt</kbd> + <kbd>L</kbd> / <kbd>Shift</kbd> + <kbd>L</kbd></td><td><kbd>Cmd</kbd> + <kbd>Opt</kbd> + <kbd>L</kbd></td></tr>
                          </table>
                      </div>
                  </div>
              </div>
          </div>
        </div>

        <div id="myCloudEditor_colorOverlay">
            <input type="color" id="myCloudEditor_colorInput">
        </div>

        <div id="myCloudEditor_minimized" onclick="myCloudEditor_restore()" title="Restore Editor">
          <svg viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
        </div>
    `;
    
    while (wrapper.firstChild) {
        document.body.appendChild(wrapper.firstChild);
    }

    const searchInput = document.getElementById('myCloud_search_input');
    if (searchInput) {
        searchInput.addEventListener('focus', function() { this.select(); });

        searchInput.addEventListener('input', function() {
            myCloudEditor_highlightMatches(this.value);
            if (!this.value) {
                const countEl = document.getElementById('myCloudEditor_searchCount');
                if (countEl) countEl.textContent = "";
            }
        });

        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                myCloudEditor_doSearch(e.shiftKey);
            }
        });
    }

    const replaceInput = document.getElementById('myCloud_replace_input');
    if (replaceInput) {
        replaceInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                myCloudEditor_replace();
            }
        });
    }
}

  // === STATE & ELEMENTS ===
  let myCloudEditor_files = [];
  let myCloudEditor_currentIndex = -1;
  let myCloudEditor_ace = null;
  let myCloudEditor_splitAce = null;
  let myCloudEditor_minimapAce = null;
  
  let editorZoom = 16;
  let showInvisibles = false;
  let isDiffMode = false;
  let isMinimapVisible = false;

  // Color picker state
  let ceColorWidgetRange = null;

  // Dynamic Ace Environment Initialization
  function ceInitAceEditor() {
      if (window.ace && !myCloudEditor_ace) {
      // Main Editor
          const isDark = document.cookie.includes('myCloudDarkMode=1');
          const aceTheme = isDark ? "ace/theme/twilight" : "ace/theme/chrome";
          myCloudEditor_ace = ace.edit("myCloudEditor_aceContainer");
          myCloudEditor_ace.setTheme(aceTheme);
          myCloudEditor_ace.setOptions({
              fontSize: editorZoom + "px",
              showPrintMargin: false,
              highlightActiveLine: true,
              wrap: false,
              foldStyle: "markbegin", 
              enableBasicAutocompletion: true, 
              enableLiveAutocompletion: true
          });

          // Intercept paste to silently strip hidden/unprintable characters
          myCloudEditor_ace.on('paste', function(e) {
              if (e && typeof e.text === 'string') {
                  let s = e.text;
                  
                  // 1. Convert all known space variants (NBSP, En Space, Em Space, etc.) to a standard space (0x20)
                  // \xA0 is the standard Non-Breaking Space which usually breaks PHP/JS.
                  s = s.replace(/[\xA0\u1680\u180E\u2000-\u200B\u202F\u205F\u3000\uFEFF]/g, ' ');
                  
                  // 2. Completely remove all zero-width, invisible formatting, and non-printable control characters 
                  // (excluding the standard \t, \n, and \r required for code formatting)
                  s = s.replace(/[\u200C-\u200F\u202A-\u202E\u2060-\u206F\u00AD\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/g, '');
                  
                  e.text = s;
              }
          });

          // Setup Syntax Dropdown
          const modelist = ace.require("ace/ext/modelist");
          if (modelist) {
              const topNames = ['php', 'sh', 'ini', 'css', 'html', 'javascript', 'mysql', 'nginx', 'xml', 'yaml', 'text', 'properties'];
              const topModes = [];
              const restModes = [];

              modelist.modes.forEach(m => {
                  const obj = { mode: m.mode, caption: m.caption, name: m.name };
                  if (topNames.includes(m.name.toLowerCase())) topModes.push(obj);
                  else restModes.push(obj);
              });

              topModes.sort((a, b) => a.caption.localeCompare(b.caption));
              restModes.sort((a, b) => a.caption.localeCompare(b.caption));
              
              const select = document.getElementById('ceSyntaxSelect');
              let html = '';

              topModes.forEach(m => { html += '<option value="' + m.mode + '">' + m.caption + '</option>'; });
              if (topModes.length > 0 && restModes.length > 0) html += '<option disabled>──────────</option>';
              restModes.forEach(m => { html += '<option value="' + m.mode + '">' + m.caption + '</option>'; });

              select.innerHTML = html;

              select.addEventListener('change', function() {
                  if (myCloudEditor_currentIndex > -1) {
                      const f = myCloudEditor_files[myCloudEditor_currentIndex];
                      f.session.setMode(this.value);
                      f.mode = this.value;
                      document.getElementById('ce_stat_mode').textContent = this.value.split('/').pop();
                      
                      // Update synchronised sessions
                      if (myCloudEditor_splitAce && isDiffMode) {
                          myCloudEditor_splitAce.getSession().setMode(this.value);
                      }
                      if (myCloudEditor_minimapAce && isMinimapVisible) {
                          myCloudEditor_minimapAce.getSession().setMode(this.value);
                      }
                  }
              });
          }
          
          // Custom Key Hooks
          myCloudEditor_ace.commands.addCommand({
              name: 'save', bindKey: {win: 'Ctrl-S',  mac: 'Command-S'},
              exec: function(editor) { myCloudEditor_save(); }, readOnly: true 
          });
          myCloudEditor_ace.commands.addCommand({
              name: 'find', bindKey: {win: 'Ctrl-F',  mac: 'Command-F'},
              exec: function(editor) { myCloudEditor_toggleSearchBar(true); }, readOnly: true 
          });

          // Change handler for Dirty state, Auto-Save, and Search highlights
          // Change handler for Dirty state, Line Indicators, Auto-Save, and Search highlights
          let debounceTimer;
          myCloudEditor_ace.on('change', function(e) {
              const f = myCloudEditor_files[myCloudEditor_currentIndex];
              if (f) {
                  const session = f.session;
                  
                  // Initialize tracking arrays if they don't exist
                  if (!session.$dirtyLines) session.$dirtyLines = [];
                  if (!session.$decoratedLines) session.$decoratedLines = new Set();
                  
                  // Extract delta(s) safely (handles legacy and modern Ace Editor versions)
                  let deltas = [];
                  if (Array.isArray(e)) deltas = e;
                  else if (e.action) deltas = [e];
                  else if (e.data && Array.isArray(e.data)) deltas = e.data;
                  else if (e.data && e.data.action) deltas = [e.data];
                  
                  deltas.forEach(delta => {
                      const stRow = delta.start.row;
                      const edRow = delta.end.row;
                      const linesCount = edRow - stRow;
                      const action = delta.action || '';

                      if (action.includes('insert')) {
                          if (linesCount > 0) {
                              const head = session.$dirtyLines.slice(0, stRow + 1);
                              const tail = session.$dirtyLines.slice(stRow + 1);
                              const mid = new Array(linesCount).fill(true);
                              session.$dirtyLines = head.concat(mid, tail);
                          }
                          session.$dirtyLines[stRow] = true;
                          if (stRow !== edRow) session.$dirtyLines[edRow] = true;
                      } else if (action.includes('remove')) {
                          if (linesCount > 0) {
                              session.$dirtyLines.splice(stRow + 1, linesCount);
                          }
                          session.$dirtyLines[stRow] = true;
                      }
                  });
                  
                  // Schedule Gutter Update (Wait for Ace to finish drawing)
                  clearTimeout(session.$gutterTimer);
                  session.$gutterTimer = setTimeout(() => {
                      if (session.$decoratedLines) {
                          session.$decoratedLines.forEach(r => session.removeGutterDecoration(r, "ce-dirty-gutter"));
                          session.$decoratedLines.clear();
                      }
                      session.$dirtyLines.forEach((isDirty, r) => {
                          if (isDirty) {
                              session.addGutterDecoration(r, "ce-dirty-gutter");
                              session.$decoratedLines.add(r);
                          }
                      });
                  }, 100);

                  const curVal = session.getValue();
                  const wasDirty = f.isDirty;
                  f.isDirty = (curVal !== f.original);

                  // If file is completely restored to original state, clear indicators
                  if (!f.isDirty) {
                      session.$dirtyLines = [];
                      if (session.$decoratedLines) {
                          session.$decoratedLines.forEach(r => session.removeGutterDecoration(r, "ce-dirty-gutter"));
                          session.$decoratedLines.clear();
                      }
                  }
                  
                  if (wasDirty !== f.isDirty) {
                      myCloudEditor_renderTabs();
                      document.getElementById('ce_stat_dirty').style.display = f.isDirty ? 'inline' : 'none';
                  }

                  clearTimeout(debounceTimer);
                  debounceTimer = setTimeout(() => {
                      if (f.isDirty) {
                          localStorage.setItem('myCloud_draft_' + f.path, curVal);
                      } else {
                          localStorage.removeItem('myCloud_draft_' + f.path);
                      }
                  }, 1000);
              }

              // Update Search Highlights
              const term = document.getElementById('myCloud_search_input').value;
              if (term) {
                  clearTimeout(myCloudEditor_ace.$searchTimer);
                  myCloudEditor_ace.$searchTimer = setTimeout(() => {
                      myCloudEditor_highlightMatches(term);
                  }, 250);
              }
          });

          // Status Bar & Native Color Picker Detection
          myCloudEditor_ace.on("changeSelection", function() {
              myCloudEditor_updateStatusBar();
              myCloudEditor_updateSearchCount();
              
              // Color Picker Overlay Logic
              const pos = myCloudEditor_ace.getCursorPosition();
              const token = myCloudEditor_ace.session.getTokenAt(pos.row, pos.column);
              const overlay = document.getElementById('myCloudEditor_colorOverlay');
              
              if (token && (/#(?:[0-9a-fA-F]{3}){1,2}\b/.test(token.value) || token.type.includes('color'))) {
                  const match = token.value.match(/#(?:[0-9a-fA-F]{3}){1,2}\b/);
                  if (match) {
                      let hex = match[0];
                      // Standardize to 6 chars for the HTML5 input
                      let fullHex = hex;
                      if (hex.length === 4) fullHex = '#' + hex[1]+hex[1]+hex[2]+hex[2]+hex[3]+hex[3];
                      
                      const screenCoords = myCloudEditor_ace.renderer.textToScreenCoordinates(pos.row, token.start);
                      
                      overlay.style.left = screenCoords.pageX + 'px';
                      overlay.style.top = screenCoords.pageY + 'px';
                      overlay.style.backgroundColor = fullHex;
                      overlay.style.display = 'block';
                      
                      document.getElementById('myCloudEditor_colorInput').value = fullHex;
                      
                      const Range = ace.require("ace/range").Range;
                      ceColorWidgetRange = new Range(pos.row, token.start + match.index, pos.row, token.start + match.index + hex.length);
                      return;
                  }
              }
              overlay.style.display = 'none';
          });
          
          // Color input replacement event
          document.getElementById('myCloudEditor_colorInput').addEventListener('input', function() {
              if (ceColorWidgetRange && myCloudEditor_ace) {
                  myCloudEditor_ace.session.replace(ceColorWidgetRange, this.value);
                  document.getElementById('myCloudEditor_colorOverlay').style.backgroundColor = this.value;
                  // Range length expands from 3 to 6 chars if a shortcode was picked
                  ceColorWidgetRange.end.column = ceColorWidgetRange.start.column + 7; 
              }
          });
      }
  }

  // === CONTENT DETECTION ===
  async function myCloudEditor_open(filePath, remoteContent) {
    // 0. Inject DOM dynamically if it's not present
    ceInitEditorDOM();
      
    if (typeof myCloudApplyTheme === 'function') myCloudApplyTheme();

    // 1. Dynamic ACE Loading
    if (typeof window.ace === 'undefined' || !myCloudEditor_ace) {
        myCloudShowLoading();
        try {
            const loadJS = (src) => new Promise((resolve, reject) => { 
                if (document.querySelector(`script[src="${src}"]`)) return resolve(); 
                const s = document.createElement('script'); 
                s.src = src; 
                s.onload = resolve; 
                s.onerror = () => reject(new Error(`Failed to load ${src}`)); 
                document.head.appendChild(s); 
            });
            
            if (typeof window.ace === 'undefined') {
                await loadJS('/script/ace-editor/ace.js');
                if (typeof window.ace === 'undefined') throw new Error("Ace object not found after load.");
                
                // CRITICAL: Prevent 404 errors when dynamically loading themes/workers
                window.ace.config.set('basePath', '/script/ace-editor');
                
                await loadJS('/script/ace-editor/ext-modelist.js');
                await loadJS('/script/ace-editor/ext-language_tools.js');
            }

            ceInitAceEditor();
        } catch (e) {
            console.error("Ace load error:", e);
            myCloudHideLoading(); 
            alert("Failed to load editor scripts."); 
            return;
        }
        myCloudHideLoading();
    }

    // 2. Safety check AFTER initialization
    if (!myCloudEditor_ace) return; 

    // Check if already open
    const existing = myCloudEditor_files.findIndex(f => f.path === filePath);
    if (existing > -1) {
      myCloudEditor_switchTab(existing);
      myCloudEditor_restore();
      return;
    }
    
    const localDraft = localStorage.getItem(`myCloud_draft_${filePath}`);
    
    const loadSession = (finalContent) => {
        const mode = myCloudEditor_detectMode(filePath, finalContent);
        const EditSession = window.ace.require("ace/edit_session").EditSession;
        let session = new EditSession(finalContent);
        session.setMode(mode);
        session.setUseWrapMode(myCloudEditor_ace.getSession().getUseWrapMode());
        
        const UndoManager = window.ace.require("ace/undomanager").UndoManager;
        session.setUndoManager(new UndoManager());
        session.setUndoSelect(true);
        
        session.$dirtyLines = [];
        session.$decoratedLines = new Set();

        document.getElementById('myCloudEditor_aceContainer').style.display = 'block';
        document.getElementById('myCloudEditor_onlyOfficeContainer').style.display = 'none';                

        myCloudEditor_files.push({ 
            path: filePath, 
            original: remoteContent, 
            session: session,
            mode: mode,
            isDirty: (finalContent !== remoteContent)
        });
        
        document.getElementById('myCloudEditor_minimized').style.display = 'none';
        document.getElementById('myCloudEditor_modal_wrap').style.display = 'flex';

        myCloudEditor_currentIndex = myCloudEditor_files.length - 1;
        myCloudEditor_renderTabs();
        myCloudEditor_switchTab(myCloudEditor_currentIndex);
        
        setTimeout(() => {
            if (myCloudEditor_ace) { myCloudEditor_ace.resize(); myCloudEditor_ace.focus(); }
        }, 50);
    };

    if (localDraft && localDraft !== remoteContent) {
        const langTitle = (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.warning) ? myCloud_LANG.warning : "Draft Found";
        const langMsg = `A locally unsaved draft exists for:\n${filePath.split('/').pop()}\n\nDo you want to restore it?`;
        
        if (typeof myCloudShowConfirm === 'function') {
            const overlay = document.getElementById('myCloudModalOverlay');
            if (overlay) overlay.style.zIndex = '15000'; 
            
            myCloudShowConfirm(langTitle, langMsg, 
                function() {
                    if (typeof myCloudCloseModal === 'function') myCloudCloseModal();
                    loadSession(localDraft);
                },
                function() {
                    if (typeof myCloudCloseModal === 'function') myCloudCloseModal();
                    localStorage.removeItem(`myCloud_draft_${filePath}`);
                    loadSession(remoteContent);
                }
            );
        } else {
            if (confirm(langMsg)) {
                loadSession(localDraft);
            } else {
                localStorage.removeItem(`myCloud_draft_${filePath}`);
                loadSession(remoteContent);
            }
        }
    } else {
        loadSession(remoteContent);
    }
  }
  
 
  function myCloudEditor_renderTabs() {
    const tabs = document.getElementById('myCloudEditor_tabs');
    tabs.innerHTML = '';
    
    myCloudEditor_files.forEach((f, i) => {
        const btn = document.createElement('div');
        btn.className = 'myCloudEditor-tab ' + (i === myCloudEditor_currentIndex ? 'active' : '');
        if (f.isDirty) btn.classList.add('dirty');
        
        const nameSpan = document.createElement('span');
        nameSpan.textContent = f.path.split('/').pop();
        btn.appendChild(nameSpan);
        
        const closeSpan = document.createElement('span');
        closeSpan.className = 'myCloudEditor-tab-close';
        closeSpan.innerHTML = '×';
        closeSpan.onclick = (e) => {
            e.stopPropagation();
            myCloudEditor_closeTab(i);
        };
        btn.appendChild(closeSpan);

        btn.onclick = () => myCloudEditor_switchTab(i);
        tabs.appendChild(btn);
    });
    
    const activeBtn = tabs.querySelector('.active');
    if(activeBtn) activeBtn.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function myCloudEditor_switchTab(i) {
    myCloudEditor_currentIndex = i;
    const f = myCloudEditor_files[i];
    if (f && f.session) {
        myCloudEditor_ace.setSession(f.session);
        document.getElementById('ceSyntaxSelect').value = f.mode;
        document.getElementById('ce_stat_mode').textContent = f.mode.split('/').pop();
        document.getElementById('ce_stat_dirty').style.display = f.isDirty ? 'inline' : 'none';
        
        // Sync Diff Pane
        if (isDiffMode && myCloudEditor_splitAce) {
            const diffSession = new (ace.require("ace/edit_session").EditSession)(f.original);
            diffSession.setMode(f.mode);
            myCloudEditor_splitAce.setSession(diffSession);
        }
        
        // Sync Minimap Pane (Uses shared Document, but own Session for independent zoom/scroll)
        if (isMinimapVisible && myCloudEditor_minimapAce) {
            const miniSession = new (ace.require("ace/edit_session").EditSession)(f.session.getDocument(), f.mode);
            myCloudEditor_minimapAce.setSession(miniSession);
        }
    }
    myCloudEditor_renderTabs();
    myCloudEditor_ace.focus();
    myCloudEditor_updateStatusBar();
    document.getElementById('myCloudEditor_colorOverlay').style.display = 'none';
    
    const term = document.getElementById('myCloud_search_input').value;
    myCloudEditor_highlightMatches(term);
    if (term) myCloudEditor_updateSearchCount();
  }
  
   function _performEditorCloseTab(index) {
      const f = myCloudEditor_files[index];
      if (f) localStorage.removeItem('myCloud_draft_' + f.path);

      myCloudEditor_files.splice(index, 1);
      if (myCloudEditor_files.length === 0) {
          myCloudEditor_currentIndex = -1;
          document.getElementById('myCloudEditor_modal_wrap').style.display = 'none';
          document.getElementById('myCloudEditor_minimized').style.display = 'none';
          if (isDiffMode) myCloudEditor_toggleDiff(); 
          if (isMinimapVisible) myCloudEditor_toggleMinimap();
      } else {
          if (myCloudEditor_currentIndex === index) {
              myCloudEditor_currentIndex = Math.max(0, index - 1);
              myCloudEditor_renderTabs();
              myCloudEditor_switchTab(myCloudEditor_currentIndex);
          } else {
              if (index < myCloudEditor_currentIndex) myCloudEditor_currentIndex--;
              myCloudEditor_renderTabs();
          }
      }
  }

  function myCloudEditor_closeTab(index) {
      const f = myCloudEditor_files[index];
      if (!f) return;
      if (f.isDirty) {
          const langTitle = (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.warning) ? myCloud_LANG.warning : "Warning";
          const langMsg = (typeof myCloud_LANG !== 'undefined' && myCloud_LANG.unsaved_changes) ? myCloud_LANG.unsaved_changes : "You have unsaved changes. Close anyway?";
          
          if (typeof myCloudShowAlert === 'function') {
                const overlay = document.getElementById('myCloudModalOverlay');
                if (overlay) overlay.style.zIndex = '15000'; 
                myCloudShowAlert(langTitle, langMsg, function() {
                  myCloudCloseModal();
                  _performEditorCloseTab(index);
                });
          } else {
              if (confirm(langMsg)) _performEditorCloseTab(index);
          }
      } else {
          _performEditorCloseTab(index);
       }
   }

  // === ACTIONS ===

  function myCloudEditor_save() {
    const f = myCloudEditor_files[myCloudEditor_currentIndex];
    if(!f) return;
    
    const cur = f.session.getValue();
    const saveBtn = document.getElementById('myCloud_save');
    const oldHtml = saveBtn.innerHTML;
    saveBtn.innerHTML = '<div class="myCloud-spinner dark" style="width:16px; height:16px; border-width:2px;"></div>';
    const reqUrl = window.location.pathname;

    // [NEW] E2E Interception: Encrypt locally before saving
    if (typeof myCloudCrypto !== 'undefined' && myCloudCrypto.isDirEncrypted(f.path)) {
        const root = myCloudCrypto.getCryptoRoot(f.path);
        if (!myCloudCrypto.isDirUnlocked(root)) {
            saveBtn.innerHTML = oldHtml;
            myCloudShowAlert('Error', 'Directory is locked. Cannot save.');
            return;
        }

        const filename = f.path.split('/').pop();
        const parentDir = f.path.substring(0, f.path.lastIndexOf('/')) || '/';
        
        // Convert the editor's text to a File Blob
        const plainFileObj = new File([new Blob([cur], {type: 'text/plain'})], filename, { type: 'text/plain' });
        
        myCloudCrypto.encryptFile(root, plainFileObj).then(encBlob => {
            const upFd = new FormData();
            upFd.append('myCloud_action', 'upload');
            upFd.append('dir', parentDir);
            upFd.append('myCloud_key', myCloudState.key);
            upFd.append('myCloud_token', typeof myCloudCsrfToken !== 'undefined' ? myCloudCsrfToken : '');
            upFd.append('file', encBlob, filename); // Uses the true .enc filename
            upFd.append('resolution', 'overwrite'); // Forces silent overwrite of the original encrypted file
            
            return fetch(reqUrl, { method: 'POST', body: upFd }).then(r => r.json());
        })
        .then(d => {
            saveBtn.innerHTML = oldHtml;
            if (d.status === 'OK') {
                f.original = cur;
                f.isDirty = false;
                
                f.session.$dirtyLines = [];
                if (f.session.$decoratedLines) {
                    f.session.$decoratedLines.forEach(r => f.session.removeGutterDecoration(r, "ce-dirty-gutter"));
                    f.session.$decoratedLines.clear();
                }

                if (typeof myCloudFetchDirectory === 'function') {
                    myCloudFetchDirectory(myCloudState.currentDir, 2, true).then(() => {
                        if (typeof myCloudRenderUI === 'function') myCloudRenderUI();
                    });
                }
    
                localStorage.removeItem('myCloud_draft_' + f.path);
                myCloudEditor_renderTabs();
                document.getElementById('ce_stat_dirty').style.display = 'none';
                myCloudEditor_showMessage('Saved successfully', true);
                
                if (typeof isDiffMode !== 'undefined' && isDiffMode && myCloudEditor_splitAce) {
                    myCloudEditor_splitAce.getSession().setValue(cur);
                }
            } else if (d.code === 'AUTH_REQUIRED' && typeof myCloudPromptAdminAuth === 'function') {
                myCloudPromptAdminAuth(() => myCloudEditor_save());
            } else {
                myCloudEditor_showMessage('Save failed: ' + d.msg, false);
            }
        })
        .catch(err => {
            saveBtn.innerHTML = oldHtml;
            myCloudEditor_showMessage('Network error', false);
            console.error("Editor Encrypted Save Error:", err);
        });
        return;
    }

    // Standard Fetch for non-encrypted files
    fetch(reqUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            myCloud_action: 'edit-save',
            myCloud_key: myCloudState.key,
            myCloud_token: typeof myCloudCsrfToken !== 'undefined' ? myCloudCsrfToken : '',
            path: f.path,
            content: cur
        })
    })
    .then(myCloudCheckResponse)
    .then(d => {
        saveBtn.innerHTML = oldHtml;
        if (d.status === 'OK') {
            f.original = cur;
            f.isDirty = false;
            
            f.session.$dirtyLines = [];
            if (f.session.$decoratedLines) {
                f.session.$decoratedLines.forEach(r => f.session.removeGutterDecoration(r, "ce-dirty-gutter"));
                f.session.$decoratedLines.clear();
            }

            if (typeof myCloudFetchDirectory === 'function') {
                myCloudFetchDirectory(myCloudState.currentDir, 2, true).then(() => {
                    if (typeof myCloudRenderUI === 'function') myCloudRenderUI();
                });
            }
  
            localStorage.removeItem('myCloud_draft_' + f.path);
            myCloudEditor_renderTabs();
            document.getElementById('ce_stat_dirty').style.display = 'none';
            myCloudEditor_showMessage('Saved successfully', true);
            
            if (typeof isDiffMode !== 'undefined' && isDiffMode && myCloudEditor_splitAce) {
                myCloudEditor_splitAce.getSession().setValue(cur);
            }
        } else if (d.code === 'AUTH_REQUIRED' && typeof myCloudPromptAdminAuth === 'function') {
            myCloudPromptAdminAuth(() => myCloudEditor_save());
        } else {
            myCloudEditor_showMessage('Save failed: ' + d.msg, false);
        }
    })
    .catch(err => {
        saveBtn.innerHTML = oldHtml;
        myCloudEditor_showMessage('Network error', false);
        console.error("Editor Save Error:", err);
    });
  }

  // === CONTENT DETECTION ===
  function myCloudEditor_detectMode(filePath, content) {
      if (typeof window.ace === 'undefined') return "ace/mode/text";
      const modelist = window.ace.require("ace/ext/modelist");
      if (!modelist) return "ace/mode/text";
      
      // [NEW] Ignore the .enc extension so syntax highlighting still works on encrypted files
      const cleanPath = filePath.replace(/\.enc$/, '');
      let modeInfo = modelist.getModeForPath(cleanPath);
      let mode = modeInfo ? modeInfo.mode : "ace/mode/text";

      if (mode === "ace/mode/text" || !mode) {
          if (/^#!.*bash/.test(content)) return "ace/mode/sh";
          if (/<\?php/.test(content)) return "ace/mode/php";
          if (/^<html/i.test(content) || /<!DOCTYPE html/i.test(content)) return "ace/mode/html";
          if (/^<\?xml/.test(content)) return "ace/mode/xml";
          if (/^[{[]/.test(content.trim()) && /["']/.test(content)) return "ace/mode/json";
          if (/(SELECT|INSERT|UPDATE|DELETE)\s/i.test(content)) return "ace/mode/sql";
      }
      return mode || "ace/mode/text";
  }  
  
  // === HIGHLIGHTING & SEARCH LOGIC ===
  function myCloudEditor_highlightMatches(term) {
      if (!myCloudEditor_ace) return;
      const session = myCloudEditor_ace.getSession();
      
      if (session.$searchMarkers) {
          session.$searchMarkers.forEach(id => session.removeMarker(id));
      }
      session.$searchMarkers = [];
      
      if (!term) return;
      
      const Search = ace.require("ace/search").Search;
      if (!Search) return;
      
      const search = new Search();
      search.setOptions({ needle: term, caseSensitive: false, wholeWord: false, regExp: false });
      
      const ranges = search.findAll(session);
      ranges.forEach(range => {
          const id = session.addMarker(range, "myCloudEditor_searchMatch", "text");
          session.$searchMarkers.push(id);
      });
      myCloudEditor_updateSearchCount(ranges);
  }

  function myCloudEditor_updateSearchCount(precalcRanges = null) {
      if (!myCloudEditor_ace) return;
      const term = document.getElementById('myCloud_search_input').value;
      const countEl = document.getElementById('myCloudEditor_searchCount');
      
      if (!term) {
          countEl.textContent = "";
          return;
      }

      let ranges = precalcRanges;
      if (!ranges) {
          const Search = ace.require("ace/search").Search;
          const search = new Search();
          search.setOptions({ needle: term, caseSensitive: false, wholeWord: false, regExp: false });
          ranges = search.findAll(myCloudEditor_ace.getSession());
      }
      
      const total = ranges.length;
      if (total === 0) {
          countEl.textContent = "0 / 0";
          return;
      }

      const currentRange = myCloudEditor_ace.getSelectionRange();
      let currentIndex = ranges.findIndex(r => 
          r.start.row === currentRange.start.row && 
          r.start.column === currentRange.start.column &&
          r.end.row === currentRange.end.row && 
          r.end.column === currentRange.end.column
      );

      if (currentIndex === -1) {
          currentIndex = ranges.findIndex(r => 
              r.start.row > currentRange.start.row || 
              (r.start.row === currentRange.start.row && r.start.column >= currentRange.start.column)
          );
          if (currentIndex === -1) currentIndex = 0;
      }

      countEl.textContent = (currentIndex + 1) + " / " + total;
  }

  function myCloudEditor_doSearch(backwards = false) {
      if (!myCloudEditor_ace) return;
      const term = document.getElementById('myCloud_search_input').value;
      if (!term) return;
      
      myCloudEditor_ace.find(term, {
          backwards: backwards,
          wrap: true,
          caseSensitive: false,
          wholeWord: false,
          regExp: false
      });
      myCloudEditor_updateSearchCount();
  }

  function myCloudEditor_toggleSearchBar(forceOpen = false) {
      const bar = document.getElementById('myCloudEditor_search_bar');
      const btn = document.getElementById('btn_search_toggle');
      if (forceOpen || bar.style.display === 'none' || bar.style.display === '') {
          bar.style.display = 'flex';
          btn.classList.add('active-tool');
          const input = document.getElementById('myCloud_search_input');
          input.focus();
          input.select();
      } else {
          bar.style.display = 'none';
          btn.classList.remove('active-tool');
          myCloudEditor_ace.focus();
      }
  }

  // === UTILS ===
  function myCloudEditor_updateStatusBar() {
      if (!myCloudEditor_ace) return;
      const pos = myCloudEditor_ace.getCursorPosition();
      const lines = myCloudEditor_ace.getSession().getLength();
      const chars = myCloudEditor_ace.getSession().getValue().length;
      const selText = myCloudEditor_ace.getSelectedText();
      const selLength = selText.length;
      
      document.getElementById('ce_stat_line').textContent = 'Ln ' + (pos.row + 1) + ', Col ' + (pos.column + 1);
      
      const selEl = document.getElementById('ce_stat_sel');
      if (selEl) selEl.textContent = selLength > 0 ? '(' + selLength + ' selected)' : '';
      
      document.getElementById('ce_stat_total').textContent = 'Total: ' + lines + ' lines';
      
      const charsEl = document.getElementById('ce_stat_chars');
      if (charsEl) charsEl.textContent = chars + ' chars';
  }

  function myCloudEditor_replace() {
      if (!myCloudEditor_ace) return;
      const r = document.getElementById('myCloud_replace_input').value;
      myCloudEditor_ace.replace(r);
      myCloudEditor_updateSearchCount();
  }

  function myCloudEditor_replaceAll() {
      if (!myCloudEditor_ace) return;
      const r = document.getElementById('myCloud_replace_input').value;
      myCloudEditor_ace.replaceAll(r);
      const term = document.getElementById('myCloud_search_input').value;
      myCloudEditor_highlightMatches(term);
  }
  
  function myCloudEditor_zoom_in() {
      editorZoom++;
      if (myCloudEditor_ace) myCloudEditor_ace.setFontSize(editorZoom + "px");
      if (myCloudEditor_splitAce) myCloudEditor_splitAce.setFontSize(editorZoom + "px");
  }
  
  function myCloudEditor_zoom_out() {
      if(editorZoom > 10) editorZoom--;
      if (myCloudEditor_ace) myCloudEditor_ace.setFontSize(editorZoom + "px");
      if (myCloudEditor_splitAce) myCloudEditor_splitAce.setFontSize(editorZoom + "px");
  }
  
  function myCloudEditor_toggle_wordwrap() {
      if (!myCloudEditor_ace) return;
      const session = myCloudEditor_ace.getSession();
      const st = !session.getUseWrapMode();
      session.setUseWrapMode(st);
      if (myCloudEditor_splitAce) myCloudEditor_splitAce.getSession().setUseWrapMode(st);
  }

  function myCloudEditor_toggleInvisibles() {
      if (!myCloudEditor_ace) return;
      showInvisibles = !showInvisibles;
      myCloudEditor_ace.setShowInvisibles(showInvisibles);
      if (myCloudEditor_splitAce) myCloudEditor_splitAce.setShowInvisibles(showInvisibles);
      
      const btn = document.getElementById('btn_invisibles');
      if (showInvisibles) btn.classList.add('active-tool'); else btn.classList.remove('active-tool');
  }

  function myCloudEditor_toggleDiff() {
      isDiffMode = !isDiffMode;
      const splitDiv = document.getElementById('myCloudEditor_aceContainerSplit');
      const btn = document.getElementById('btn_diff_toggle');
      
      if (isDiffMode) {
          splitDiv.style.display = 'block';
          btn.classList.add('active-tool');
          
          if (!myCloudEditor_splitAce) {
              const isDark = document.cookie.includes('myCloudDarkMode=1');
              myCloudEditor_splitAce = ace.edit("myCloudEditor_aceContainerSplit");
              myCloudEditor_splitAce.setTheme(isDark ? "ace/theme/twilight" : "ace/theme/chrome");
              myCloudEditor_splitAce.setReadOnly(true);
              myCloudEditor_splitAce.setOptions({
                  fontSize: editorZoom + "px",
                  showPrintMargin: false,
                  wrap: myCloudEditor_ace.getSession().getUseWrapMode(),
                  foldStyle: "markbegin"
              });
          }
          
          const f = myCloudEditor_files[myCloudEditor_currentIndex];
          if (f) {
              const diffSession = new (ace.require("ace/edit_session").EditSession)(f.original);
              diffSession.setMode(f.mode);
              myCloudEditor_splitAce.setSession(diffSession);
          }
      } else {
          splitDiv.style.display = 'none';
          btn.classList.remove('active-tool');
      }
      
      myCloudEditor_ace.resize();
      if(myCloudEditor_splitAce) myCloudEditor_splitAce.resize();
  }

  function myCloudEditor_toggleMinimap() {
      isMinimapVisible = !isMinimapVisible;
      const minimapDiv = document.getElementById('myCloudEditor_minimap');
      const btn = document.getElementById('btn_minimap_toggle');

      if (isMinimapVisible) {
          minimapDiv.style.display = 'block';
          btn.classList.add('active-tool');
          
          if (!myCloudEditor_minimapAce) {
              const isDark = document.cookie.includes('myCloudDarkMode=1');
              myCloudEditor_minimapAce = ace.edit("myCloudEditor_minimap");
              myCloudEditor_minimapAce.setTheme(isDark ? "ace/theme/twilight" : "ace/theme/chrome");
              myCloudEditor_minimapAce.setReadOnly(true);
              myCloudEditor_minimapAce.setOptions({
                  fontSize: "3px",
                  showGutter: false,
                  showPrintMargin: false,
                  highlightActiveLine: false,
                  highlightGutterLine: false,
                  showLineNumbers: false,
                  displayIndentGuides: false,
                  wrap: false
              });
              
              // Hide the cursor in the minimap completely
              myCloudEditor_minimapAce.renderer.$cursorLayer.element.style.display = "none";
              
              // Map minimap clicks to scroll the main editor
              myCloudEditor_minimapAce.on("click", function(e) {
                  const row = e.getDocumentPosition().row;
                  myCloudEditor_ace.scrollToLine(row, true, true);
                  myCloudEditor_ace.gotoLine(row + 1);
              });
          }

          const f = myCloudEditor_files[myCloudEditor_currentIndex];
          if (f) {
              const miniSession = new (ace.require("ace/edit_session").EditSession)(f.session.getDocument(), f.mode);
              myCloudEditor_minimapAce.setSession(miniSession);
          }
      } else {
          minimapDiv.style.display = 'none';
          btn.classList.remove('active-tool');
      }
      
      myCloudEditor_ace.resize();
      if(myCloudEditor_minimapAce) myCloudEditor_minimapAce.resize();
  }

   function myCloudEditor_undo() {
      if (myCloudEditor_ace) {
          myCloudEditor_ace.undo();
          myCloudEditor_ace.focus();      
         myCloudEditor_showMessage("Undo", true);
     }
  }
  
  function myCloudEditor_redo() {
      if (myCloudEditor_ace) {
          myCloudEditor_ace.redo();
          myCloudEditor_ace.focus();      
         myCloudEditor_showMessage("Redo", true);
      }
  }

  function myCloudEditor_showMessage(text, success) {
      const old = document.querySelector('.myCloudEditor_msg');
      if(old) old.remove();

      const div = document.createElement('div');
      div.className = 'myCloudEditor_msg ' + (success ? 'myCloudEditor_msg--success' : 'myCloudEditor_msg--error');
      div.textContent = text;
      document.querySelector('.myCloudEditor-window').appendChild(div);
      
      requestAnimationFrame(() => div.classList.add('show'));
      
      setTimeout(() => {
          div.classList.remove('show');
          setTimeout(() => div.remove(), 300);
      }, 3000);
  }

  // === WINDOW CONTROL ===
  function myCloudEditor_minimize() {
      document.getElementById('myCloudEditor_modal_wrap').style.display = 'none';
      document.getElementById('myCloudEditor_minimized').style.display = 'flex';
  }

  function myCloudEditor_restore() {
      document.getElementById('myCloudEditor_minimized').style.display = 'none';
      document.getElementById('myCloudEditor_modal_wrap').style.display = 'flex';
      
      setTimeout(() => {
          if(myCloudEditor_ace) {
              myCloudEditor_ace.resize();
              myCloudEditor_ace.focus();
          }
          if(myCloudEditor_splitAce && isDiffMode) {
              myCloudEditor_splitAce.resize();
          }
          if(myCloudEditor_minimapAce && isMinimapVisible) {
              myCloudEditor_minimapAce.resize();
          }
      }, 50);
  }

  function myCloudEditor_close() {
      if (myCloudEditor_currentIndex > -1) {
          myCloudEditor_closeTab(myCloudEditor_currentIndex);
       }
  }

  // Add this variable to your editor state
  let myCloudEditor_popupWindow = null;

  function myCloudEditor_openInPopup() {
      const w = 1200;
      const h = 800;
      const left = (window.screen.width / 2) - (w / 2);
      const top = (window.screen.height / 2) - (h / 2);

      // Open a real browser window
      myCloudEditor_popupWindow = window.open("", "myCloudEditorPopup", 
          `width=${w},height=${h},left=${left},top=${top},menubar=no,toolbar=no,location=no,status=no,resizable=yes`
      );

      if (!myCloudEditor_popupWindow) {
          alert("Popup blocked! Please allow popups for this site.");
          return;
      }

      // Move the Editor Container from the main page to the new window
      const editorNode = document.getElementById('myCloudEditor_modal_wrap');
      
      // Create a basic HTML structure in the new window
      myCloudEditor_popupWindow.document.write(`
          <html>
          <head>
              <title>Editor - Native Mode</title>
              <style>
                  body, html { margin:0; padding:0; width:100%; height:100%; overflow:hidden; }
                  /* Re-inject all your CSS variables and styles here or link your stylesheet */
              </style>
          </head>
          <body>
              <div id="popup_content"></div>
          </body>
          </html>
      `);

      // Transfer the Editor DOM
      const target = myCloudEditor_popupWindow.document.getElementById('popup_content');
      target.appendChild(editorNode);

      // Re-adjust Editor sizing for the new window
      myCloudEditor_ace.resize();
      
      // Handle closing the popup
      myCloudEditor_popupWindow.onbeforeunload = function() {
          // Move the node back to the main document before the window dies
          document.body.appendChild(editorNode);
          myCloudEditor_popupWindow = null;
      };
  }

</script>