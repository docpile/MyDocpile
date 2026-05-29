<?php
/**
 * ============================================================================
 * MODULE: Dynamic Stylesheet Generator
 * ============================================================================
 * Constructs, minifies, and serves the application's Cascading Style Sheets (CSS) 
 * dynamically, adjusting for active themes (e.g., Dark Mode) and configurations.
 */
 
 if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    die('Direct access not permitted');
}
?>
<style>
/* --- HELP MODAL LAYOUT --- */
.ce-help-modal { max-width: 1200px !important; width: 95vw !important; height: 90vh !important; display:flex; flex-direction:column; }
.ce-help-container { display: flex; flex: 1; height: 100%; overflow: hidden; font-family: "Segoe UI", "Roboto", Helvetica, Arial, sans-serif; }

.ce-help-modal-header { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; justify-content: space-between; position: relative; padding-inline-end: 40px !important; }
.ce-help-header-title { font-size: 16px; white-space: nowrap; }
.ce-help-header-tools { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.ce-help-close-btn { position: absolute; inset-inline-end: 15px; top: 12px; background: transparent; border: none; font-size: 20px; cursor: pointer; color: inherit; line-height: 1; padding: 0; }

/* Sidebar */
.ce-help-nav { width: 280px; flex-shrink: 0; background: #f9f9f9; border-inline-end: 1px solid var(--border-color); display: flex; flex-direction: column; min-height: 0; }
.ce-help-content { flex: 1; padding: 40px; overflow-y: auto; background: var(--bg-white); color: var(--text-color); scroll-behavior: smooth; min-height: 0; }

/* Search */
.ce-help-search-box { padding: 15px; border-bottom: 1px solid var(--border-color); background: #f0f0f0; }
.ce-help-search-input { width: 100%; padding: 10px 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; outline: none; transition: all 0.2s; }
.ce-help-search-input:focus { border-color: var(--accent-color); background:#fff; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }

/* List */
.ce-help-list { list-style: none; padding: 0; margin: 0; overflow-y: auto; flex: 1; }
.ce-help-item { padding: 12px 20px; cursor: pointer; border-bottom: 1px solid rgba(0,0,0,0.03); font-size: 14px; color: #555; display: flex; align-items: center; justify-content: space-between; transition: all 0.1s; }
.ce-help-item:hover { background: rgba(0,0,0,0.05); color: #000; }
.ce-help-item.active { background: #fff; color: var(--accent-color); font-weight: 600; border-inline-start: 4px solid var(--accent-color); box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
.ce-help-icon { margin-inline-end: 12px; font-size: 18px; width: 24px; text-align: center; opacity: 0.8; }

.ce-chevron { width: 10px; height: 10px; fill: #ccc; transition: fill 0.2s; }
/* RTL Support for Chevron */
[dir="rtl"] .ce-chevron, .ce-help-modal[dir="rtl"] .ce-chevron { transform: scaleX(-1); }

.ce-help-item:hover .ce-chevron { fill: #999; }
.ce-help-item.active .ce-chevron { fill: var(--accent-color); }

/* TYPOGRAPHY */
.ce-help-h1 { font-size: 32px; font-weight: 300; margin: 0 0 25px 0; color: var(--accent-color); padding-bottom: 15px; border-bottom: 1px solid var(--border-color); display:flex; align-items:center; gap:15px; }
.ce-help-h2 { font-size: 20px; font-weight: 700; margin: 35px 0 15px 0; color: var(--text-color); display:flex; align-items:center; gap:10px; border-bottom: 1px solid rgba(0,0,0,0.05); padding-bottom:5px; }
.ce-help-h3 { font-size: 15px; font-weight: 600; margin: 20px 0 5px 0; color: #555; text-transform: uppercase; letter-spacing: 0.5px; }
.ce-help-p { font-size: 15px; line-height: 1.6; margin-bottom: 15px; color: var(--text-color); max-width: 850px; }

/* DATA TABLES */
.ce-help-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 14px; }
.ce-help-table th { text-align: start; padding: 10px; background: rgba(0,0,0,0.05); border-bottom: 2px solid var(--border-color); font-weight: 600; color: #444; }
.ce-help-table td { padding: 10px; border-bottom: 1px solid rgba(0,0,0,0.05); color: var(--text-color); vertical-align: top; }
.ce-help-table tr:last-child td { border-bottom: none; }
.ce-help-subrow td { padding-top: 0; padding-bottom: 15px; color: #666; font-size: 13px; font-style: italic; border-bottom: 1px solid rgba(0,0,0,0.1); }

/* COMPONENTS */
.ce-help-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 15px; margin-bottom: 20px; }
.ce-help-card { background: #fcfcfc; border: 1px solid var(--border-color); border-radius: 6px; padding: 20px; transition: box-shadow 0.2s; }
.ce-help-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
.ce-help-card-title { font-weight: 700; margin-bottom: 8px; display:block; color: var(--accent-color); font-size: 15px; }
.ce-help-card-text { font-size: 13px; line-height: 1.5; color: #666; }

.ce-help-badge { background: #eee; color: #333; padding: 2px 8px; border-radius: 4px; font-family: monospace; font-size: 13px; border: 1px solid #ccc; white-space: nowrap; font-weight:600; vertical-align: middle; }
.ce-help-key { display: inline-block; padding: 4px 9px; border: 1px solid #bbb; border-bottom: 2px solid #999; border-radius: 5px; background: #fcfcfc; font-size: 12px; font-weight: 700; color: #333; margin: 0 3px; font-family: sans-serif; min-width: 20px; text-align:center; }
.ce-help-tip { background: rgba(0, 120, 212, 0.08); border-inline-start: 4px solid var(--accent-color); padding: 15px 20px; font-size: 14px; color: var(--text-color); margin: 25px 0; border-radius: 0 6px 6px 0; line-height: 1.5; display: flex; gap: 15px; align-items: flex-start; }
.ce-help-warn { background: rgba(232, 17, 35, 0.08); border-inline-start: 4px solid #e81123; padding: 15px 20px; font-size: 14px; color: var(--text-color); margin: 25px 0; border-radius: 0 6px 6px 0; line-height: 1.5; display: flex; gap: 15px; align-items: flex-start; }
.ce-help-block-icon { font-size: 20px; line-height: 1.2; flex-shrink: 0; opacity: 0.9; cursor: default; margin-top: -5px; }
.ce-help-treemap-ex { display:flex; gap:0; height:80px; width:200px; margin:15px auto; border:1px solid #999; }
.ce-help-tm-box { display:flex; align-items:center; justify-content:center; color:#fff; font-size:11px; font-weight:bold; border:1px solid rgba(255,255,255,0.8); box-sizing:border-box; }

/* --- TICKET STYLES --- */
.ce-tkt-wrapper { display: flex; flex-direction: column; height: 100%; }
.ce-tkt-list { flex: 1; overflow-y: auto; display: flex; flex-direction: column; gap: 10px; padding: 10px 0; }
.ce-tkt-card { background: #fff; border: 1px solid #ddd; border-radius: 6px; padding: 15px; margin-bottom:10px; }
.ce-tkt-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.ce-tkt-title { font-weight: 700; font-size: 15px; color: #333; }
.ce-tkt-meta { font-size: 11px; color: #888; display: flex; gap: 10px; }
.ce-tkt-desc { font-size: 13px; color: #555; line-height: 1.4; margin-top: 5px; white-space: pre-wrap; }
.ce-tkt-admin { margin-top: 10px; padding-top: 10px; border-top: 1px solid #eee; background: #fcfcfc; padding: 8px; font-size: 12px; color: #444; }
.ce-tkt-badge { padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
.ce-tkt-open { background: #e3f2fd; color: #1565c0; }
.ce-tkt-progress { background: #fff3e0; color: #ef6c00; }
.ce-tkt-later { background: #f3e5f5; color: #7b1fa2; }
.ce-tkt-closed { background: #e8f5e9; color: #2e7d32; }
.ce-tkt-form { background: #f0f0f0; padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #ddd; }
.ce-tkt-input { width: 100%; padding: 8px; margin-bottom: 10px; border: 1px solid #ccc; border-radius: 4px; font-family: inherit; }
.ce-tkt-row { display: flex; gap: 10px; margin-bottom: 10px; }

/* --- REFACTORED INLINE STYLES FOR DARK MODE COMPATIBILITY --- */
.ce-help-top-btn { background:#e0e0e0; border:1px solid #ccc; color:#333; padding:4px 10px; margin-inline-end:5px; border-radius:4px; font-weight:bold; cursor:pointer; font-size:12px; transition: all 0.2s; }
.ce-help-top-btn:hover { background:#d0d0d0; }
.ce-help-support-btn { background:#f0ad4e; border:none; color:#fff; padding:4px 10px; margin-inline-end:10px; border-radius:4px; font-weight:bold; cursor:pointer; font-size:12px; transition: all 0.2s; }
.ce-help-support-btn:hover { background:#e09d3e; }
.ce-help-top-select { margin-inline-end:10px; padding:2px 5px; font-size:12px; border:1px solid #ccc; border-radius:4px; background:#fff; margin-top:4px; margin-bottom:0px; color:#333; outline:none; }
.ce-tkt-admin-box { margin-top:10px; display:flex; gap:10px; border-top:1px solid #eee; padding-top:10px; background:#f9f9f9; padding:10px; border-radius:4px; align-items:center; }
.ce-tkt-admin-input { border: 1px solid #ddd; background: #fff; color: #333; padding:2px; font-size:11px; border-radius:3px; outline:none; }
.ce-tkt-admin-btn { background: #eee; border: 1px solid #ccc; color: #333; border-radius: 3px; padding:2px 6px; font-size:11px; cursor:pointer; transition:background 0.2s; }
.ce-tkt-admin-btn:hover { background: #ddd; }

/* --- UPDATED TICKET STYLES --- */
.ce-tkt-wrapper { display: flex; flex-direction: column; height: 100%; position: relative; }

/* Sticky Header Area for Controls */
.ce-tkt-controls { 
    flex-shrink: 0; 
    padding-bottom: 15px; 
    border-bottom: 1px solid var(--border-color); 
    margin-bottom: 15px;
    background: var(--bg-white);
}

/* Compact Create Form (Collapsible Logic handled in JS, but styled compact) */
.ce-tkt-form-compact { 
    display: none; /* Hidden by default */
    background: #f0f0f0; 
    padding: 15px; 
    border-radius: 6px; 
    border: 1px solid #ddd; 
    margin-bottom: 15px;
}
.ce-tkt-form-compact.visible { display: block; }

/* Create Toggle Button */
.ce-tkt-create-btn {
    width: 100%;
    padding: 8px;
    background: #f9f9f9;
    border: 1px dashed #ccc;
    color: #666;
    font-weight: 600;
    cursor: pointer;
    border-radius: 6px;
    transition: all 0.2s;
    text-align: center;
    font-size: 13px;
}
.ce-tkt-create-btn:hover { background: #eee; color: #333; border-color: #999; }

/* Scrollable List Area */
.ce-tkt-list { 
    flex: 1; 
    overflow-y: auto; 
    display: flex; 
    flex-direction: column; 
    gap: 10px; 
    padding-right: 5px; /* Space for scrollbar */
}


/* Dark Mode */
.ce-dark-mode .ce-help-nav { background: #1e1e1e; border-inline-end-color: #333; }
.ce-dark-mode .ce-help-content { background: var(--gray-05, #141414); color: var(--text-primary, #e0e0e0); }
.ce-dark-mode .ce-help-h2 { border-bottom-color: rgba(255,255,255,0.1); color: var(--text-primary, #fff); }
.ce-dark-mode .ce-help-h3 { color: #aaa; }
.ce-dark-mode .ce-help-p { color: var(--text-primary, #ccc); }
.ce-dark-mode .ce-help-search-box { background: #252526; border-bottom-color: #333; }
.ce-dark-mode .ce-help-search-input { background: #333; border-color: #555; color: #fff; }
.ce-dark-mode .ce-help-item { color: #ccc; }
.ce-dark-mode .ce-help-item:hover { background: #2a2a2a; color: #fff; }
.ce-dark-mode .ce-help-item.active { background: #2d2d2d; color: var(--accent-color); border-inline-start-color: var(--accent-color); }
.ce-dark-mode .ce-help-card { background: rgba(255,255,255,0.03); border-color: #444; }
.ce-dark-mode .ce-help-card:hover { background: rgba(255,255,255,0.06); }
.ce-dark-mode .ce-help-card-text { color: #aaa; }
.ce-dark-mode .ce-help-badge { background: #333; color: #eee; border-color: #555; }
.ce-dark-mode .ce-help-key { background: #333; border-color: #555; border-bottom-color: #000; color: #eee; }
.ce-dark-mode .ce-help-table th { background: var(--gray-15, #252526); color: #eee; border-bottom-color: var(--border-medium, #555); }
.ce-dark-mode .ce-help-table td { color: #ccc; border-bottom-color: var(--border-default, #444); }
.ce-dark-mode .ce-help-subrow td { color: #999; border-bottom-color: var(--border-subtle, #333); }
.ce-dark-mode .ce-tkt-card { background: var(--gray-10, #1e1e1e); border-color: var(--border-default, #444); }
.ce-dark-mode .ce-tkt-title { color: #fff; }
.ce-dark-mode .ce-tkt-meta { color: #aaa; }
.ce-dark-mode .ce-tkt-desc { color: #ccc; }
.ce-dark-mode .ce-tkt-admin { background: var(--gray-15, #252526); border-top-color: var(--border-default, #444); color: #aaa; }
.ce-dark-mode .ce-tkt-form-compact { background: var(--gray-10, #1e1e1e); border-color: var(--border-default, #444); }
.ce-dark-mode .ce-tkt-create-btn { background: var(--gray-15, #252526); border-color: var(--border-default, #555); color: #aaa; }
.ce-dark-mode .ce-tkt-create-btn:hover { background: var(--gray-20, #333); color: #fff; border-color: var(--border-strong, #777); }
.ce-dark-mode .ce-tkt-controls { background: var(--gray-05, #141414); border-bottom-color: var(--border-default, #444); }
.ce-dark-mode .ce-tkt-input { background: var(--gray-15, #252526); border-color: var(--border-default, #555); color: #fff; }
.ce-dark-mode .ce-help-top-btn { background: var(--gray-20, #333); border-color: var(--border-default, #555); color: #eee; }
.ce-dark-mode .ce-help-top-btn:hover { background: var(--gray-30, #444); }
.ce-dark-mode .ce-help-top-select { background: var(--gray-20, #333); border-color: var(--border-default, #555); color: #eee; }
.ce-dark-mode .ce-help-show-start { color: #ccc; }
.ce-dark-mode .ce-tkt-admin-box { background: var(--gray-15, #252526); border-top-color: var(--border-default, #444); }
.ce-dark-mode .ce-tkt-admin-input { background: var(--gray-20, #333); border-color: var(--border-default, #555); color: #eee; }
.ce-dark-mode .ce-tkt-admin-btn { background: var(--gray-30, #444); border-color: var(--border-default, #666); color: #eee; }
.ce-dark-mode .ce-tkt-admin-btn:hover { background: var(--gray-40, #555); }
.ce-dark-mode .ce-tkt-filter-bar { border-bottom-color: var(--border-default, #444) !important; }
.ce-dark-mode .ce-tkt-filter-title, .ce-dark-mode .ce-tkt-filter-label { color: #ccc !important; }

.ce-help-container svg { width: 1.2em; height: 1.2em; vertical-align: middle; fill: currentColor; }

/* Responsive */
@media (max-width: 768px) {
    .ce-help-container { flex-direction: column; }
    .ce-help-nav { width: 100%; height: 200px; border-inline-end: none; border-bottom: 1px solid var(--border-color); }
    .ce-help-content { padding: 20px; }
    .ce-help-modal-header { padding: 12px 15px; }
    .ce-help-header-tools { flex: 1 1 100%; margin-top: 5px; padding-top: 10px; border-top: 1px solid rgba(0,0,0,0.1); justify-content: space-between; }
    .ce-dark-mode .ce-help-header-tools { border-top-color: rgba(255,255,255,0.1); }
    .ce-help-show-start { width: 100%; margin-top: 5px !important; }
}

</style>