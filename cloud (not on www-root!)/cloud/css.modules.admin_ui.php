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
/* Admin UI */

   .ca-layout {
        /* Distinct Enterprise Dashboard Palette */
        --ca-bg-app: #f1f5f9;         /* Soft tinted background for depth */
        --ca-bg-sidebar: #ffffff;     /* Crisp white for navigation */
        --ca-bg-sidebar-hover: #f1f5f9;
        --ca-bg-tabs: #e2e8f0;        /* Darkest slate for top header */
        --ca-bg-card: #ffffff;        /* Crisp white for content blocks */
        --ca-bg-dynamic: #f8fafc;

        --ca-border-subtle: #e2e8f0;
        --ca-border-normal: #cbd5e1;
        --ca-border-strong: #94a3b8;

        --ca-text-main: #334155;
        --ca-text-dark: #0f172a;
        --ca-text-light: #ffffff;
        --ca-text-muted: #64748b;
        --ca-text-sidebar: #475569;
        --ca-text-tab-muted: #64748b;

        --ca-accent: #4f46e5;         /* Indigo for momentum */
        --ca-accent-hover: #4338ca;

        --ca-danger: #e11d48;
        --ca-success: #10b981;
        --ca-warning: #ea580c;

        display: flex; flex-direction: column; height: 65vh; min-height: 400px; max-height: 800px; width: 100%; overflow: hidden;
        color: var(--ca-text-main);
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    }
    
    /* Header Tabs (Light) */
    .ca-tabs { display: flex; background: var(--ca-bg-tabs); flex-shrink: 0; border-bottom: 1px solid var(--ca-border-normal); }
    .ca-tab-btn { flex: 1; padding: 12px; font-size: 13px; background: transparent; border: none; border-bottom: 3px solid transparent; cursor: pointer; font-weight: 600; color: var(--ca-text-tab-muted); transition: all 0.2s; letter-spacing: 0.02em; }
    .ca-tab-btn:hover { color: var(--ca-text-dark); background: rgba(0,0,0,0.04); }
    .ca-tab-btn.active { border-bottom-color: var(--ca-accent); color: var(--ca-accent); background: var(--ca-bg-app); }
    
    .ca-area { display: flex; flex: 1; overflow: hidden; background: var(--ca-bg-app); position: relative; }
	
    /* Sidebar (Light) */
    .ca-sidebar-panel { width: 300px; flex-shrink: 0; background: var(--ca-bg-sidebar); display: flex; flex-direction: column; border-right: 1px solid var(--ca-border-normal); }
    .ca-user-list-ul { flex: 1; overflow-y: auto; padding: 10px; margin: 0; list-style: none; }
    .ca-user-li { position: relative; padding: 8px 12px; cursor: pointer; border-radius: 6px; margin-bottom: 4px; font-size: 13px; font-weight: 500; color: var(--ca-text-sidebar); border: 1px solid transparent; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; transition: all 0.2s; }
    .ca-user-li:hover { background: var(--ca-bg-sidebar-hover); color: var(--ca-text-dark); }
    .ca-user-li.active { background: var(--ca-accent); color: #ffffff; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06); }
    .ca-sidebar-footer { padding: 12px; border-top: 1px solid var(--ca-border-subtle); background: var(--ca-bg-sidebar); }
    
    /* Main Content Area */
    .ca-editor-panel { flex: 1; padding: 20px 30px; overflow-y: auto; background: var(--ca-bg-app); }
    .ca-section-heading { font-size: 13px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--ca-text-dark); font-weight: 700; margin: 25px 0 15px 0; padding: 0 0 8px 10px; border-bottom: 1px solid var(--ca-border-normal); border-left: 4px solid var(--ca-accent); display: flex; justify-content: space-between; align-items: center; cursor: pointer; user-select: none; }
    .ca-section-heading:first-child { margin-top: 5px; }
    
    /* White Cards on Tinted Background */
    .ca-config-card { background: var(--ca-bg-card); border: 1px solid var(--ca-border-subtle); border-radius: 8px; padding: 16px 20px; margin-bottom: 20px; box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06); }
    
    .ca-form-group { margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid var(--ca-border-subtle); }
    .ca-form-group:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
    
    .ca-label { display: block; margin-bottom: 6px; font-size: 12px; font-weight: 600; color: var(--ca-text-dark); transition: color 0.2s; }
    .ca-input, .ca-select { width: 100%; padding: 8px 10px; border: 1px solid var(--ca-border-normal); border-radius: 6px; font-size: 13px; color: var(--ca-text-dark); background: var(--ca-bg-card); transition: border-color 0.2s, box-shadow 0.2s; box-sizing: border-box; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
    .ca-input:focus, .ca-select:focus { outline: none; border-color: var(--ca-accent); box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15); }
    
    /* Subfolder Permissions UI */
    .ca-subfolder-box { margin-top: 4px; padding: 12px 12px 12px 16px; margin-left: 34px; margin-bottom: 18px; background: var(--ca-bg-app); border: 1px solid var(--ca-border-subtle); border-left: 3px solid var(--ca-border-strong); border-radius: 6px; box-sizing: border-box; }
    .ce-dark-mode .ca-subfolder-box { background: rgba(0,0,0,0.15); border-left-color: var(--ca-border-strong); }
    .ca-subfolder-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700; color: var(--ca-text-muted); }
    .ca-subfolder-row { display: flex; gap: 8px; margin-bottom: 6px; align-items: center; background: var(--ca-bg-card); padding: 4px 6px; border: 1px solid var(--ca-border-subtle); border-radius: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
    .ca-subfolder-row:last-child { margin-bottom: 0; }
    .ca-sf-path, .ca-sf-rights { margin: 0; padding: 4px 8px; font-size: 12px; height: 26px; box-sizing: border-box; }
    

    /* Buttons */
    .ca-btn { display: inline-flex; align-items: center; justify-content: center; padding: 8px 16px; border: 1px solid transparent; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
    .ca-btn-primary { background: var(--ca-accent); color: #fff; box-shadow: 0 1px 2px rgba(79, 70, 229, 0.3); }
    .ca-btn-primary:hover { background: var(--ca-accent-hover); box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.4); transform: translateY(-1px); }
    .ca-btn-danger { background: transparent; color: var(--ca-danger); border-color: rgba(225, 29, 72, 0.3); }
    .ca-btn-danger:hover { background: var(--ca-danger); color: #fff; border-color: var(--ca-danger); }
    .ca-btn-outline { background: var(--ca-bg-card); border-color: var(--ca-border-strong); color: var(--ca-text-dark); box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
    .ca-btn-outline:hover { border-color: var(--ca-text-dark); }
    .ca-btn-sm { padding: 5px 12px; font-size: 12px; }
    
    .ca-actions-footer { margin-top: 25px; padding-top: 20px; border-top: 1px solid var(--ca-border-normal); display: flex; justify-content: flex-end; align-items: center; gap: 12px; }
    .ca-loading-spinner { display: flex; justify-content: center; align-items: center; height: 100%; color: var(--ca-text-muted); width: 100%; font-weight: 600; }
    

    
    /* Floating Save Button */
    .ca-floating-save { position: absolute; top: 20px; right: 30px; z-index: 50; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.4); border-radius: 20px; padding: 8px 24px; opacity: 0; transform: translateY(-10px); pointer-events: none; transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); }
    .ca-floating-save.visible { opacity: 1; transform: translateY(0); pointer-events: auto; }

    /* Drag & Drop */
    .ca-dynamic-row[draggable="true"] { cursor: grab; }
    .ca-dynamic-row.dragging { opacity: 0.4; border: 2px dashed var(--ca-accent); box-shadow: none; transform: scale(0.98); }
    
    /* Toggle Switch */
    .ca-toggle-switch { position: relative; display: inline-block; width: 36px; height: 20px; flex-shrink: 0; }
    .ca-toggle-switch input { opacity: 0; width: 0; height: 0; }
    .ca-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: var(--ca-border-strong); transition: .2s; border-radius: 20px; box-shadow: inset 0 1px 3px rgba(0,0,0,0.2); }
    .ca-slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 2px; bottom: 2px; background-color: white; transition: .2s; border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
    .ca-toggle-switch input:checked + .ca-slider { background-color: var(--ca-success); }
    .ca-toggle-switch input:checked + .ca-slider:before { transform: translateX(16px); }
    
    /* Badges & Dirty State */
    .ca-dirty-text { color: var(--ca-warning) !important; font-style: italic; }
    .ca-dirty-border { border-color: var(--ca-warning) !important; box-shadow: 0 0 0 1px var(--ca-warning) inset !important; }
    .ca-unsaved-indicator { font-size: 12px; color: var(--ca-warning); font-weight: 600; flex: 1; text-align: right; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; padding-right: 15px; }
    
    /* Tooltip */
    .ca-tooltip-icon {
        display: inline-flex; align-items: center; justify-content: center;
        width: 14px; height: 14px; border-radius: 50%;
        background: var(--ca-border-strong); color: #fff;
        font-size: 10px; font-style: italic; font-weight: bold; cursor: help;
        margin-left: 8px; position: relative; transition: background 0.2s;
    }
    .ca-tooltip-icon:hover { background: var(--ca-accent); }
    .ca-tooltip-icon::after {
        content: attr(data-tooltip);
        position: absolute; top: 100%; left: 50%; transform: translateX(-50%);
        background: var(--ca-text-dark); color: #fff; padding: 8px 12px; border-radius: 6px;
        font-size: 12px; font-style: normal; font-weight: 500; white-space: normal;
        width: max-content; max-width: 260px; text-align: center;
        opacity: 0; visibility: hidden; transition: opacity 0.2s, transform 0.2s;
        z-index: 1000; pointer-events: none; margin-top: 8px;
        box-shadow: 0 10px 15px -3px rgba(0,0,0,0.3); line-height: 1.5; font-family: sans-serif;
    }
    .ca-tooltip-icon:hover::after { opacity: 1; visibility: visible; transform: translate(-50%, 2px); }
    .ca-tooltip-icon::before {
        content: ''; position: absolute; top: 100%; left: 50%; transform: translateX(-50%);
        border: 6px solid transparent; border-bottom-color: var(--ca-text-dark);
        bottom: auto; margin-top: -3px; margin-bottom: 0;
        opacity: 0; visibility: hidden; transition: opacity 0.2s;
        z-index: 1000; pointer-events: none; margin-bottom: -2px;
    }
    .ca-tooltip-icon:hover::before { opacity: 1; visibility: visible; }
    
    .ca-workdir-badge {
        margin-left: 10px; font-size: 11px; background: var(--ca-bg-dynamic); color: var(--ca-text-muted);
        padding: 3px 8px; border-radius: 12px; font-weight: 500; border: 1px solid var(--ca-border-normal);
    }
    /* Custom Input & Label Hover Tooltips */
    .ca-hover-tooltip { position: relative; display: inline-block; font-style: normal !important; }
    .ca-label.ca-hover-tooltip { cursor: help; border-bottom: 1px dotted var(--ca-border-strong); }
    .ca-hover-tooltip::after {
        content: attr(data-tooltip); position: absolute; top: 100%; left: 0;
        background: var(--ca-text-dark); color: #fff; padding: 8px 12px; border-radius: 6px;
        font-size: 12px; font-weight: 500; white-space: normal;
        width: max-content; max-width: 260px; text-align: left;
        opacity: 0; visibility: hidden; transition: opacity 0.2s, transform 0.2s;
        z-index: 10000; pointer-events: none; margin-top: 8px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.3); line-height: 1.5; font-family: sans-serif;
    }
    .ca-hover-tooltip:hover::after { opacity: 1; visibility: visible; transform: translateY(2px); }
    .ca-hover-tooltip::before { content: ''; position: absolute; top: 100%; left: 10px; border: 6px solid transparent; border-bottom-color: var(--ca-text-dark); opacity: 0; visibility: hidden; transition: opacity 0.2s; z-index: 10000; pointer-events: none; margin-bottom: -2px; margin-top: -3px; }
    .ca-hover-tooltip:hover::before { opacity: 1; visibility: visible; }
    .ca-hover-tooltip-center::after { left: 50%; transform: translateX(-50%); text-align: center; }
    .ca-hover-tooltip-center:hover::after { transform: translate(-50%, 2px); }
    .ca-hover-tooltip-center::before { left: 50%; transform: translateX(-50%); }
	.ca-d-none { display: none !important; }

    /* Added UI Features */
    .ca-user-li.has-cloud span:first-child { color: #288a2f; font-weight: 700; }
	.ca-user-li.has-cloud span:first-child::after {
		content: "✅";
		display: inline-block;
		transform: scale(0.5);
		transform-origin: center;
	}

    .ca-user-li.active.has-cloud span:first-child { color: #ffffff; }
    .ca-admin-tag { display: inline-block; position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: #8b0000; color: white; padding: 2px 6px; border-radius: 4px; font-size: 10px; line-height: 1; font-weight: bold; text-transform: uppercase; box-shadow: 0 2px 4px rgba(0,0,0,0.2); z-index: 10; pointer-events: none; }
    .ca-user-li.is-admin { padding-right: 55px; } /* Prevents long usernames from overlapping the badge */

    /* Mobile Optimization */
    @media (max-width: 768px) {
        #ca_area_users { flex-direction: column; }
        .ca-sidebar-panel { width: 100%; border-right: none; border-bottom: 1px solid var(--ca-border-normal); flex: none; height: 35vh; min-height: 200px; }
        .ca-editor-panel { padding: 15px; }
        .ca-config-card { grid-template-columns: 1fr !important; }
        .ca-form-group { display: flex !important; flex-direction: column !important; align-items: stretch !important; gap: 6px; }
        .ca-dynamic-row { flex-direction: column; align-items: stretch; }
    }
 
 
/* =========================================
       WEBMAIL MODULE (OWA TOOLBARS + ORIGINAL LAYOUT)
       ========================================= */

/* --- NEW ISOLATED OWA TOOLBAR CLASSES --- */
.owa-toolbar { display: flex; flex-wrap: wrap; gap: 4px; padding-block: 8px; padding-inline: 16px; border-block-end: 1px solid #edebe9; background: #ffffff; align-items: center; flex-shrink: 0; height: auto; min-height: 48px; width: 100%; box-sizing: border-box; font-family: "Segoe UI", "Segoe UI Web (West European)", -apple-system, BlinkMacSystemFont, Roboto, "Helvetica Neue", sans-serif; }
.owa-toolbar::-webkit-scrollbar { display: none; }
.owa-divider { width: 1px; height: 20px; background-color: #edebe9; margin: 0 4px; flex-shrink: 0; }

/* Flat, borderless buttons with SVG strictly left of the label */
.owa-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding-block: 6px; padding-inline: 10px; background: transparent; border: 1px solid transparent; border-radius: 4px; cursor: pointer; color: #242424; font-size: 14px; font-weight: 400; transition: background-color 0.1s ease; white-space: nowrap; font-family: inherit; }
.owa-btn:hover { background-color: #f3f2f1; }
.owa-btn:active { background-color: #edebe9; }

.owa-btn.owa-primary { background-color: #0f6cbd; color: #ffffff; font-weight: 600; padding-inline: 16px; }
.owa-btn.owa-primary:hover { background-color: #0c5393; }
.owa-btn.owa-primary:active { background-color: #09477d; }

.owa-btn.owa-danger { color: #d13438; }
.owa-btn.owa-danger:hover { background-color: #fdf3f4; }

.owa-icon { display: inline-flex; align-items: center; justify-content: center; margin: 0; padding: 0; }
.owa-icon svg { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 1.5; stroke-linecap: round; stroke-linejoin: round; display: block; }
.owa-btn.owa-primary .owa-icon svg { stroke: #ffffff; }

.ce-dark-mode .owa-toolbar { background: #292929; border-block-end: 1px solid #484644; }
.ce-dark-mode .owa-divider { background-color: #484644; }
.ce-dark-mode .owa-btn { color: #ffffff; }
.ce-dark-mode .owa-btn:hover { background-color: #3b3a39; }
.ce-dark-mode .owa-btn:active { background-color: #323130; }

/* --- ORIGINAL PANES & LISTS --- */
.ce-email-panes { display: flex; flex: 1; height: 100%; overflow: hidden; flex-direction: row; }

    /* Mobile Responsive Overrides */
    .ce-email-mobile-only { display: none !important; }
    @media (max-width: 768px) {
        .ce-email-panes { position: relative; display: block; }
        .ce-email-tree, .ce-email-list, .ce-email-reading { position: absolute; inset: 0; width: 100% !important; z-index: 1; display: none !important; }
        .ce-email-resizer { display: none !important; }
        .ce-email-tree.mobile-active, .ce-email-list.mobile-active, .ce-email-reading.mobile-active { display: flex !important; z-index: 2; }
        .ce-email-mobile-only { display: inline-flex !important; }
        
        .owa-toolbar { min-height: 44px !important; padding-inline: 6px !important; gap: 2px !important; }
        .owa-btn { padding-inline: 8px !important; gap: 6px !important; font-size: 13px !important; }
        .owa-label.hide-mobile { display: none !important; }
        
        .ce-email-read-header { padding: 10px 12px 8px 12px !important; margin-block-end: 10px !important; }
        .ce-email-read-subject { font-size: 15px !important; margin-block-end: 6px !important; line-height: 1.2 !important; }
    }

.ce-email-tree { overflow-y: auto; display: flex; flex-direction: column; background: var(--gray-10); flex-shrink: 0; padding-block: 10px; }
.ce-email-list { overflow-y: auto; display: flex; flex-direction: column; background: var(--gray-00); flex-shrink: 0; }
.ce-email-reading { flex: 1; overflow-y: auto; background: var(--gray-00); display: flex; flex-direction: column; min-width: 0; }

.ce-email-resizer { position: relative; width: 8px; margin-inline: -4px; z-index: 100; cursor: col-resize; flex-shrink: 0; background: transparent; }
.ce-email-resizer::after { content: ''; position: absolute; inset-block: 0; inset-inline-start: 3px; width: 1px; background: var(--border-default); transition: background 0.2s, width 0.2s, inset-inline-start 0.2s; }
.ce-email-resizer:hover::after, .ce-email-resizer.active::after { background: var(--accent-primary); width: 3px; inset-inline-start: 2px; }

/* Tree View Modern Icons Adjustments */
.ce-email-tree .myCloudIcon svg { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 1.5; stroke-linecap: round; stroke-linejoin: round; margin-right: 6px; }

/* Middle Pane (Message List) */
.ce-email-list-item { padding-block-start: 22px; padding-block-end: 12px; padding-inline-end: 16px; padding-inline-start: 24px; border-block-end: 1px solid var(--border-subtle); cursor: pointer; color: var(--text-primary); background: var(--gray-00); transition: background 0.2s; position: relative; }
.ce-email-list-item:hover { background: var(--hover-bg-light); }
.ce-email-list-item.selected { background: var(--selection-bg); border-inline-start: 3px solid var(--accent-primary); padding-inline-start: 21px; }

.ce-email-acc-ribbon { position: absolute; top: 0; inset-inline-end: 16px; padding-block: 1px; padding-inline: 6px; font-size: 8px; line-height: 1; font-weight: 700; color: #fff; text-transform: uppercase; letter-spacing: 0.5px; border-end-start-radius: 4px; border-end-end-radius: 4px; opacity: 0.9; z-index: 2; pointer-events: none; }

.ce-email-list-sender { font-weight: 600; margin-block-end: 4px; font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: flex; justify-content: space-between; align-items: baseline; }
.ce-email-list-sender.read { font-weight: 400; }
.ce-email-list-subject { font-weight: 600; font-size: 13px; margin-block-end: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--text-primary); padding-inline-end: 24px; transition: padding-inline-end 0.15s ease-in-out; }
.ce-email-list-item:hover .ce-email-list-subject, .ce-email-list-item:focus-within .ce-email-list-subject { padding-inline-end: 54px; }
.ce-email-list-subject.read { font-weight: 400; color: var(--text-secondary); }
.ce-email-list-date { color: var(--text-disabled); font-size: 11px; white-space: nowrap; flex-shrink: 0; margin-inline-start: 8px; font-weight: normal; }

.ce-email-unread-dot { position: absolute; inset-inline-start: 8px; top: 18px; width: 8px; height: 8px; background-color: var(--accent-primary); border-radius: 50%; }

/* Quick Actions (List View) */
.ce-email-list-actions { position: absolute; bottom: 8px; inset-inline-end: 12px; opacity: 0; pointer-events: none; transition: opacity 0.15s ease-in-out; background: var(--gray-00); border-radius: 4px; box-shadow: -8px 0 8px var(--gray-00); z-index: 10; }
.ce-email-list-item:hover .ce-email-list-actions, .ce-email-list-item:focus-within .ce-email-list-actions { opacity: 1; pointer-events: auto; }
.ce-email-list-item.selected .ce-email-list-actions { background: var(--selection-bg); box-shadow: -8px 0 8px var(--selection-bg); }
:dir(rtl) .ce-email-list-actions { box-shadow: 8px 0 8px var(--gray-00); }
:dir(rtl) .ce-email-list-item.selected .ce-email-list-actions { box-shadow: 8px 0 8px var(--selection-bg); }

.ce-email-list-del-btn { background: transparent; border: none; color: var(--gray-70); cursor: pointer; padding: 4px; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
.ce-email-list-del-btn:hover { color: var(--gray-99); transform: scale(1.15); }

/* Reading Pane */
.ce-email-read-header { margin-block-end: 20px; padding-block-start: 20px; padding-inline: 20px; padding-block-end: 15px; border-block-end: 1px solid var(--border-default); }
.ce-email-read-subject { margin-block: 0 10px; color: var(--text-primary); font-size: 20px; line-height: 1.3; }
.ce-email-read-meta { display: flex; justify-content: space-between; color: var(--text-primary); font-size: 14px; gap: 10px; flex-wrap: wrap; align-items: flex-start; }
.ce-email-read-meta b { color: var(--text-secondary); font-weight: normal; font-size: 12px; }
.ce-email-read-meta-extended { display: none; margin-block-start: 10px; color: var(--text-primary); font-size: 14px; flex-direction: column; gap: 8px; }
.ce-email-read-meta-extended b { color: var(--text-secondary); font-weight: normal; font-size: 12px; display: inline-block; width: 40px; }
.ce-email-read-meta-extended.expanded { display: flex; }
.ce-email-meta-toggle { background: transparent; border: none; color: var(--text-secondary); cursor: pointer; padding-block: 2px; padding-inline: 6px; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center; transition: background 0.2s, color 0.2s; }
.ce-email-meta-toggle:hover { background: var(--gray-15); color: var(--text-primary); }
.ce-email-meta-toggle svg { width: 16px; height: 16px; fill: currentColor; transition: transform 0.2s; }
.ce-email-meta-toggle.expanded svg { transform: rotate(180deg); }
.ce-dark-mode .ce-email-meta-toggle:hover { background: var(--gray-20); }

.ce-email-addr-pill { position: relative; display: inline-flex; align-items: center; background: var(--gray-10); color: var(--text-primary); border-radius: 20px; padding-block: 6px; padding-inline: 14px; font-size: 14px; font-weight: 500; cursor: pointer; max-width: 100%; }
.ce-email-pill-label { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 200px; }
.ce-email-addr-pill:hover .ce-email-contact-card { display: flex; flex-direction: column; }
.ce-email-addr-pill:hover { background: var(--gray-20); border-color: var(--border-medium); }
.ce-email-addr-pill:focus-within .ce-email-contact-card, .ce-email-addr-pill:hover .ce-email-contact-card { display: flex; animation: ceFadeInScale 0.15s ease-out; }
.ce-dark-mode .ce-email-addr-pill { background: var(--gray-15); border-color: var(--border-default); color: var(--text-primary); }
.ce-dark-mode .ce-email-addr-pill:hover { background: var(--gray-20); border-color: var(--border-medium); }

.ce-email-contact-card { position: absolute; top: calc(100% + 6px); inset-inline-start: 0; z-index: 1000; display: none; min-width: 260px; max-width: 320px; background: var(--gray-00); border: 1px solid var(--border-medium); border-radius: 8px; padding: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); font-family: var(--font-family, sans-serif); cursor: default; }
/* Creates an invisible hover bridge over the 6px gap */
.ce-email-contact-card::before { content: ''; position: absolute; bottom: 100%; inset-inline-start: 0; width: 100%; height: 10px; background: transparent; }
.ce-dark-mode .ce-email-contact-card { background: var(--gray-10); border-color: var(--border-default); box-shadow: 0 4px 12px rgba(0,0,0,0.5); }

.ce-card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-block-end: 12px; gap: 12px; }
.ce-card-name { font-weight: 700; font-size: 16px; color: var(--text-primary); line-height: 1.2; word-break: break-word; }
.ce-contact-action-btn { background: transparent; border: none; color: var(--text-secondary); cursor: pointer; padding: 4px; border-radius: 4px; display: flex; align-items: center; justify-content: center; transition: background 0.2s, color 0.2s; margin-block-start: -2px; }
.ce-contact-action-btn:hover { background: var(--gray-10); color: var(--text-primary); }
.ce-email-cc-val-wrap { display: flex; align-items: center; justify-content: space-between; background: var(--gray-05); padding-block: 6px; padding-inline: 10px; border-radius: 6px; border: 1px solid var(--border-subtle); margin-block-end: 8px; gap: 10px; }
.ce-email-cc-val { font-size: 13px; color: var(--text-secondary); font-family: monospace; user-select: all; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ce-copy-btn { background: transparent; border: none; color: var(--text-secondary); cursor: pointer; padding: 4px; border-radius: 4px; display: flex; align-items: center; transition: all 0.2s; }
.ce-copy-btn:hover { color: var(--accent-primary); background: rgba(0, 120, 212, 0.1); }
.ce-card-detail { font-size: 13px; color: var(--text-secondary); display: flex; align-items: center; gap: 8px; margin-block-start: 6px; }
.ce-card-detail .myCloudIcon { width: 14px; height: 14px; font-size: 14px; display: inline-flex; justify-content: center; opacity: 0.7; }

.ce-email-body-content { color: var(--text-primary); line-height: 1.5; font-family: sans-serif; overflow-wrap: anywhere; padding-block-end: 20px; padding-inline: 20px; }
.ce-email-empty { color: var(--text-secondary); text-align: center; margin-block-start: 50px; padding: 20px; }

/* Address Book & Autocomplete */
.ce-contact-row { display: flex; justify-content: space-between; padding-block: 10px; padding-inline: 15px; border-block-end: 1px solid var(--border-subtle); align-items: center; transition: background 0.2s; }
.ce-contact-row:hover { background: var(--hover-bg-light); }
.ce-contact-avatar { width: 32px; height: 32px; border-radius: 50%; background: var(--accent-primary); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px; flex-shrink: 0; margin-inline-end: 12px; }
.ce-contact-info { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.ce-contact-name { font-weight: 600; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: 14px; }
.ce-contact-email { color: var(--text-secondary); font-size: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* ========================================= COMPOSER RECIPIENT PILLS ========================================= */
.ce-email-tile { display: inline-flex; align-items: center; gap: 6px; background: var(--gray-10); border: 1px solid var(--border-medium); border-radius: 16px; padding-block: 3px; padding-inline: 10px; font-size: 13px; font-weight: 500; color: var(--text-primary); cursor: default; user-select: none; transition: background 0.2s, border-color 0.2s; }
.ce-dark-mode .ce-email-tile { background: var(--gray-15); border-color: var(--border-default); }
.ce-email-tile:hover { background: var(--gray-20); border-color: var(--border-strong); }
/* The 'x' remove button */
.ce-email-tile span:last-child { cursor: pointer; color: var(--text-secondary); font-size: 16px; line-height: 1; margin-inline-start: 2px; display: flex; align-items: center; justify-content: center; border-radius: 50%; width: 16px; height: 16px; transition: color 0.2s, background 0.2s; }
.ce-email-tile span:last-child:hover { color: #fff; background: var(--danger, #e81123); }

/* Paperclip Hovering Indicator */
.ce-email-attachment-indicator { position: absolute; inset-inline-end: 16px; bottom: 12px; opacity: 0.5; display: flex; align-items: center; transition: transform 0.15s ease-in-out; pointer-events: none; }
/* Slide out of the way when the Quick Delete button fades in */
.ce-email-list-item:hover .ce-email-attachment-indicator, .ce-email-list-item:focus-within .ce-email-attachment-indicator { transform: translateX(-32px); }
:dir(rtl) .ce-email-list-item:hover .ce-email-attachment-indicator, :dir(rtl) .ce-email-list-item:focus-within .ce-email-attachment-indicator { transform: translateX(32px); }

/* Reading Pane Attachments (OWA Pill Style) */
.ce-attachment-pill { display: inline-flex; align-items: center; gap: 8px; padding: 4px 12px; background-color: #f3f2f1; border: 1px solid #edebe9; border-radius: 16px; color: #242424; font-size: 13px; font-family: "Segoe UI", "Segoe UI Web (West European)", sans-serif; cursor: pointer; transition: all 0.15s ease; max-width: 100%; box-sizing: border-box; }
.ce-attachment-pill:hover { background-color: #e1dfdd; border-color: #c8c6c4; }
.ce-attachment-pill svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 1.5; stroke-linecap: round; stroke-linejoin: round; flex-shrink: 0; }
.ce-attachment-pill-name { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 250px; font-weight: 500; }
.ce-attachment-pill-size { color: #605e5c; font-size: 12px; flex-shrink: 0; margin-inline-start: 4px; }

.ce-dark-mode .ce-attachment-pill { background-color: #3b3a39; border-color: #484644; color: #ffffff; }
.ce-dark-mode .ce-attachment-pill:hover { background-color: #484644; border-color: #605e5c; }
.ce-dark-mode .ce-attachment-pill-size { color: #a19f9d; }

.ce-email-attachments { padding-block: 16px; padding-inline: 24px; background: transparent; border-block-start: 1px solid var(--border-default); display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }


/* ========================================= UPDATED: Reading Pane 3D Flipper (Horizontal) ========================================= */
.ce-email-flipper-wrap { perspective: 1000px; flex: 1; position: relative; display: flex; flex-direction: column; min-height: 0; overflow: hidden; }
.ce-email-flipper { flex: 1; position: relative; transform-style: preserve-3d; transition: transform 0.6s cubic-bezier(0.4, 0.2, 0.2, 1); display: flex; flex-direction: column; min-height: 0; }
/* This class triggers the animation. We now rotate around the Y-axis (vertical axis) for a horizontal flip. */
.ce-email-flipper.flipped { transform: rotateY(-180deg); }
:dir(rtl) .ce-email-flipper.flipped { transform: rotateY(180deg); }

.ce-email-front, .ce-email-back { backface-visibility: hidden; position: absolute; inset-block: 0; inset-inline: 0; display: flex; flex-direction: column; overflow-y: auto; background: var(--gray-00); }
.ce-email-front { z-index: 2; }
.ce-email-back { transform: rotateY(180deg); color: var(--text-primary); z-index: 1; }
:dir(rtl) .ce-email-back { transform: rotateY(-180deg); }

/* Prevent the invisible face from intercepting mouse events */
.ce-email-flipper.flipped .ce-email-front { pointer-events: none; }
.ce-email-flipper:not(.flipped) .ce-email-back { pointer-events: none; }

/* =========================================
   NATIVE LOADING STATES & SKELETONS
   ========================================= */
.ce-pane-loading {
    position: relative;
    pointer-events: none;
}
/* Indeterminate top loading bar (like iOS/Android pull-to-refresh) */
.ce-pane-loading::after {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: var(--accent-primary);
    z-index: 1000;
    animation: ce-indeterminate-bar 1s infinite linear;
    opacity: 0.9;
}
.ce-pane-loading > * {
    opacity: 0.6;
    transition: opacity 0.2s ease;
}

@keyframes ce-indeterminate-bar {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

.ce-skeleton {
    background: linear-gradient(90deg, var(--gray-10) 25%, var(--gray-20) 50%, var(--gray-10) 75%);
    background-size: 200% 100%;
    animation: ceSkeletonShimmer 1.5s infinite;
    border-radius: 4px;
}
@keyframes ceSkeletonShimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}
@keyframes ceFadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}


/* --- Email Action Animations & Toasts --- */
.ce-email-removing {
    transform: translateX(-100%) !important;
    opacity: 0 !important;
    margin: 0 !important;
    padding-block: 0 !important;
    border: none !important;
    max-height: 0 !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
    overflow: hidden !important;
}
#ce-email-toast-container {
    position: fixed;
    bottom: 24px;
    inset-inline-start: 24px;
    z-index: 210000;
    display: flex;
    flex-direction: column-reverse;
    gap: 8px;
    pointer-events: none;
}
.ce-email-undo-toast {
    background: var(--gray-95);
    color: var(--gray-00);
    padding: 12px 16px;
    border-radius: 6px;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.2);
    animation: ceFadeInScale 0.2s ease-out;
    pointer-events: auto;
    transition: opacity 0.3s ease, transform 0.3s ease;
}
.ce-email-undo-btn {
    color: var(--accent-primary-light, #60cdff);
    background: transparent;
    border: none;
    cursor: pointer;
    font-weight: 700;
    text-transform: uppercase;
    padding: 0;
    font-size: 12px;
}
.ce-email-undo-btn:hover {
    text-decoration: underline;
}
.ce-email-list-item {
    max-height: 150px;
    transition: background 0.2s, max-height 0.3s, padding 0.3s, opacity 0.3s, transform 0.3s;
}

/* =========================================
   FORCE EMAIL TAB STYLES (Overrides everything)
   ========================================= */
#myCloudCloudSwitcher button.ce-cloud-btn.ce-email-tab {
    background: #e6f2ff !important;
    color: #0078d4 !important;
    border-color: #cce4ff !important;
    border-bottom-color: transparent !important;
}
#myCloudCloudSwitcher button.ce-cloud-btn.ce-email-tab:hover {
    background: #cce4ff !important;
}
#myCloudCloudSwitcher button.ce-cloud-btn.ce-email-tab.active {
    background: #f0f8ff !important;
    color: #0078d4 !important;
    box-shadow: inset 0 3px 0 #0078d4 !important;
    border: 1px solid #cce4ff !important;
    border-bottom-color: #f0f8ff !important;
    z-index: 2 !important;
}

/* DARK MODE FORCE */
.ce-dark-mode #myCloudCloudSwitcher button.ce-cloud-btn.ce-email-tab {
    background: rgba(0, 120, 212, 0.15) !important;
    color: #60cdff !important;
    border-color: rgba(0, 120, 212, 0.3) !important;
    border-bottom-color: transparent !important;
}
.ce-dark-mode #myCloudCloudSwitcher button.ce-cloud-btn.ce-email-tab:hover {
    background: rgba(0, 120, 212, 0.25) !important;
}
.ce-dark-mode #myCloudCloudSwitcher button.ce-cloud-btn.ce-email-tab.active {
    background: rgba(0, 120, 212, 0.2) !important;
    color: #60cdff !important;
    box-shadow: inset 0 3px 0 #60cdff !important;
    border: 1px solid rgba(0, 120, 212, 0.4) !important;
    border-bottom-color: rgba(0, 120, 212, 0.2) !important;
    z-index: 2 !important;
}

</style> 