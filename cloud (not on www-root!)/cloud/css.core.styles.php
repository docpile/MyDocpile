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

/* ────────────────────────────────────────────────
   #2  –  ROW HOVER PILL MENU (.ce-row-actions)
   RTL: inset-inline-end instead of right
───────────────────────────────────────────────── */
.ce-row-actions {
    display: flex;
    align-items: center;
    gap: 6px;
    position: absolute;
    inset-inline-end: 25px; 
    top: 50%;
    transform: translateY(-50%);
    height: 40px; 
    padding: 0 8px;
    
    
    background: var(--gray-00); 
    
    /* Borders: Top and Bottom Only */
	border-top: 1px solid var(--border-default);
    border-bottom: 1px solid var(--border-default);
    border-left: none;
    border-right: none;
    border-radius: 99px;
    
    /* Unified Shadow (traces the arrow tips correctly) */
    filter: drop-shadow(0 6px 10px rgba(0, 0, 0, 0.15));
    
    opacity: 0;
    pointer-events: none;
    z-index: 5; /* AS REQUESTED: UNTOUCHED */
	transition: opacity 0.15s ease;

}



/* Staggered Children Base State */
.ce-row-actions > div {
    opacity: 0;
    transform: translateX(15px) scale(0.8);
    transition: opacity 0.2s ease, transform 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}



.myCloudRow:hover .ce-row-actions {
    opacity: 1;
    pointer-events: auto;
/*	transition-delay: 0.4s; */
}

/* Trigger Elegant Entrance on Hover */
.myCloudRow:hover .ce-row-actions > div {
    opacity: 1;
    transform: translateX(0) scale(1);
}
 
.myCloudRow:hover .ce-row-actions > div:nth-child(1) { animation-delay: 0.15s; }
.myCloudRow:hover .ce-row-actions > div:nth-child(2) { animation-delay: 0.18s; }
.myCloudRow:hover .ce-row-actions > div:nth-child(3) { animation-delay: 0.21s; }
.myCloudRow:hover .ce-row-actions > div:nth-child(4) { animation-delay: 0.24s; }
.myCloudRow:hover .ce-row-actions > div:nth-child(5) { animation-delay: 0.27s; }
.myCloudRow:hover .ce-row-actions > div:nth-child(6) { animation-delay: 0.30s; }
.myCloudRow:hover .ce-row-actions > div:nth-child(7) { animation-delay: 0.33s; }
.myCloudRow:hover .ce-row-actions > div:nth-child(8) { animation-delay: 0.36s; }
.myCloudRow:hover .ce-row-actions > div:nth-child(9) { animation-delay: 0.39s; }
.myCloudRow:hover .ce-row-actions > div:nth-child(10) { animation-delay: 0.42s; }

.ce-action-icon {
    width: 30px;
    height: 30px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    background: transparent;
    border: 1px solid transparent;
    transition: all 0.2s ease;
}

.ce-action-icon:hover {
    background: var(--gray-35);
    margin-top: -2px;
    margin-bottom: 2px; /* Replaces transform collision to retain hover bounce */
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

/* Category Separator Line */
.ce-row-action-sep {
    width: 1px;
    height: 16px;
    background: var(--gray-05);
    margin: 0 2px;
    flex-shrink: 0;
}
.ce-dark-mode .ce-row-action-sep { background: var(--border-default); }
/* Clean up dangling separators automatically */
.ce-row-action-sep:first-child,
.ce-row-action-sep:last-child,
.ce-row-action-sep + .ce-row-action-sep {
    display: none !important;
}


    /* Single Click Mode Hover Effect */
    .ce-single-click-mode .myCloudRow:hover .ce-name-text,
    .ce-single-click-mode .myCloud-symbol-item:hover .ce-sym-label { text-decoration: underline; }

/* Specific action icon colors (kept vivid) */
.ce-act-preview svg   { fill: #8D6E63 !important; }
.ce-act-download svg  { fill: #8D6E63 !important; }
.ce-act-edit svg      { fill: #8D6E63 !important; }
.ce-act-fav svg       { fill: var(--gray-60) !important; }
.ce-act-copy svg      { fill: var(--gray-60) !important; }
.ce-act-duplicate svg { fill: var(--gray-60) !important; }
.ce-act-move svg      { fill: var(--gray-60) !important; }
.ce-act-rename svg    { fill: var(--gray-60) !important; }
.ce-act-delete svg    { fill: #be8989 !important; }

/* ────────────────────────────────────────────────
   #3  –  CONTEXT MENU (right-click menu)
   RTL: no directional properties → no change needed
───────────────────────────────────────────────── */
/* Windows 11 Style Context Menu */
    .myCloudContextMenu {
        position: fixed;
        z-index: 20000;
        /* Win 11 Acrylic Light */
        background: rgba(255, 255, 255, 0.84)  !important;
        backdrop-filter: blur(20px) saturate(125%)  !important;
        -webkit-backdrop-filter: blur(20px) saturate(125%)  !important;
        
        border: 1px solid rgba(0, 0, 0, 0.06);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12), 0 2px 6px rgba(0,0,0,0.04);
        
        padding: 4px 0;
        min-width: 200px;
        border-radius: 8px; /* Larger radius */
        user-select: none;
        font-family: "Segoe UI Variable Text", "Segoe UI", sans-serif;
        animation: ceFadeInScale 0.12s ease-out forwards;
        transform-origin: top left;
        color: #202020;
		position: fixed !important;
		z-index: 999999 !important;
    }

    .myCloudContextItem {
        padding: 8px 16px;
        margin-bottom: 2px;
        cursor: default;
        display: flex;
        align-items: center;
        font-size: 14px;
		/* Items have rounded corners */
        border-radius: 4px; 
        transition: background-color 0.05s;
        color: inherit;
    }

    /* Keyboard Shortcut Hint for Context Menus */
    .myCloudContextKbd {
        margin-left: auto;
        font-size: 11px;
        color: var(--text-disabled);
        font-family: monospace;
    }

   .myCloudContextItem:last-child { margin-bottom: 0; }
    .myCloudContextItem:hover {
        background: var(--gray-15);
    }
    .myCloudContextItem .myCloudIcon {
        margin-inline-end: 12px;
    }
	
/* Context Menu Submenu & Grid */
.myCloudContextGridRow {
    display: flex;
    justify-content: space-around;
    padding: 6px 2px;
    border-bottom: 1px solid rgba(0,0,0,0.08);
    background: rgba(0,0,0,0.03);
}
.myCloudContextGridItem {
    cursor: pointer;
    padding: 6px 2px;
    border-radius: 4px;
    transition: background 0.1s;
    flex: 1;
    display: flex;
    justify-content: center;
    flex-direction: column;
    align-items: center;
	gap: 5px;
}
.myCloudContextGridItem:hover { background: rgba(0,0,0,0.1); }
.myCloudContextGridItem svg { width: 18px; height: 18px; fill: var(--text-primary, #444); }
.myCloudContextGridItem span.grid-label { font-size: 9px; line-height: 1; color: var(--text-secondary, #666); max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* The Submenu Logic */
.myCloudContextItem {
    position: relative; /* Essential for submenu positioning */
}
.myCloudContextItem.hasSub:after {
    content: '▶';
    font-size: 8px;
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    opacity: 0.5;
}
.myCloudContextSubMenu {
    position: absolute;
    left: 100%; /* Pops out to the right */
    top: 0;
    display: none;
    background: var(--bg-default, #fff);
    border: 1px solid var(--border-default, #ccc);
    box-shadow: 4px 4px 15px rgba(0,0,0,0.15);
    z-index: 2000001;
    min-width: 160px;
    padding: 4px 0;
}
/* Show submenu when hovering the parent row */
.myCloudContextItem.hasSub:hover > .myCloudContextSubMenu {
    display: block;
}

/* Fast Custom Tooltip */
.myCloudContextTooltip {
    position: fixed;
    background: #323130;
    color: #fff;
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 11px;
    white-space: nowrap;
    z-index: 2000010;
    pointer-events: none;
    box-shadow: 0 2px 8px rgba(0,0,0,0.4);
    animation: myCloudFadeIn 0.1s ease-out;
}
@keyframes myCloudFadeIn {
    from { opacity: 0; transform: translateY(5px); }
    to { opacity: 1; transform: translateY(0); }
}

/* RTL Context Menu Adjustments */
[dir="rtl"] .myCloudContextGridRow {
    flex-direction: row-reverse;
}

[dir="rtl"] .myCloudContextItem.hasSub:after {
    content: '◀'; /* Points left */
    right: auto;
    left: 10px;
}

[dir="rtl"] .myCloudContextSubMenu {
    left: auto;
    right: 100%; /* Flip to the left side */
    box-shadow: -4px 4px 15px rgba(0,0,0,0.15);
}

[dir="rtl"] .myCloudContextItem span.myCloudIcon {
    margin-right: 0;
    margin-left: 12px;
}

/* ────────────────────────────────────────────────
   #4  –  PROGRESS POPUP (bottom-right transfers)
   RTL: inset-inline-end instead of right
───────────────────────────────────────────────── */
.myCloudProgressPopup {
    position: fixed;
    bottom: 24px;
    inset-inline-end: 24px;
    width: 320px;
    background: var(--gray-00);
    border: 1px solid var(--border-default);
    border-radius: 10px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.22);
    padding: 16px;
    z-index: 21000;
    display: flex;
    flex-direction: column;
    gap: 10px;
    font-family: var(--font-family);
}


/* ────────────────────────────────────────────────
   #15  –  MARQUEE SELECTION BOX (lasso selection)
   RTL: symmetric → no change needed
───────────────────────────────────────────────── */
.myCloud-marquee {
    position: fixed;
    border: 1.5px solid var(--accent-primary);
    background-color: var(--selection-bg);
    box-shadow: 0 0 0 1px rgba(0,120,212,0.4) inset;
    pointer-events: none;
    z-index: 10000;
}

/* ────────────────────────────────────────────────
   #16  –  WARNING / ZIP / ACTION DIALOG BUTTONS
   RTL: centered group → no change needed
───────────────────────────────────────────────── */
.ce-warning-box {
    text-align: center;
    padding: 16px;
}

.ce-warning-title {
    margin-bottom: 16px;
    font-weight: 600;
    color: var(--danger);
    font-size: 16px;
}

.ce-btn-group {
    display: flex;
    justify-content: center;
    gap: 12px;
    margin-top: 20px;
}

.ce-btn-action {
    padding: 10px 24px;
    border: none;
    border-radius: 6px;
    color: var(--gray-00);
    font-weight: 500;
    cursor: pointer;
    transition: all 0.14s;
}

.ce-btn-confirm {
    background: var(--success);
}

.ce-btn-confirm:hover {
    background: #0e6b0e;
}

.ce-btn-danger {
    background: var(--danger);
}

.ce-btn-danger:hover {
    background: var(--danger-hover);
}

/* ────────────────────────────────────────────────
   #17  –  PROPERTIES DIALOG + TREEMAP
   RTL: flex row is symmetric → no change needed
───────────────────────────────────────────────── */
.myCloud-prop-stats {
    padding: 16px;
    border-bottom: 1px solid var(--gray-30);
    background: var(--gray-10);
}

.myCloud-prop-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    font-size: 14px;
}

.myCloud-prop-label {
    color: var(--text-secondary);
}

.myCloud-prop-val {
    font-weight: 600;
    color: var(--text-primary);
}

.myCloud-treemap-container {
    padding: 12px;
    height: 320px;
    display: flex;
    flex-direction: column;
}

.myCloud-treemap-canvas {
    flex: 1;
    position: relative;
    background: var(--gray-20);
    border: 1px solid var(--border-default);
    overflow: hidden;
    border-radius: 4px;
}

.myCloud-tm-node {
    position: absolute;
    box-sizing: border-box;
    border: 1px solid rgba(255,255,255,0.4);
    color: white;
    font-size: 11px;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    text-shadow: 0 1px 2px rgba(0,0,0,0.5);
    transition: filter 0.18s;
}

.myCloud-tm-node:hover {
    filter: brightness(1.12);
    z-index: 10;
    border-color: white;
}



    /* =========================================
       2. GLOBAL & MAIN LAYOUT
       ========================================= */
    .myCloudContainer, * {
        box-sizing: border-box;
    }
    .myCloudContainer {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: var(--gray-00);
        font: var(--font-size-base)/1.5 var(--font-family);
        color: var(--text-primary);
        flex-direction: column;
        z-index: 1001;
        transform-origin: center center;
        /* Comprehensive Safe Area Padding for the entire app shell */
        padding-top: env(safe-area-inset-top);
        padding-right: env(safe-area-inset-right);
        padding-bottom: env(safe-area-inset-bottom);
        padding-left: env(safe-area-inset-left);
    }
    /* Animation Classes */
    #myCloudContainer.ce-anim-open {
        display: flex !important;
        animation: ceFadeInScale 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
    #myCloudContainer.ce-anim-close {
        display: flex !important;
        animation: ceFadeOutScale 0.2s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
   
    .myCloudBody {
        flex: 1;
        display: flex;
        overflow: hidden;
        opacity: 0;
        transition: opacity 0.4s ease-out;
    }
    .myCloudBody.visible {
        opacity: 1;
    }
	
    /* Skeletons */
    .ce-skeleton {
        background: linear-gradient(90deg, var(--gray-20) 25%, var(--gray-30) 50%, var(--gray-20) 75%);
        background-size: 200% 100%;
        animation: ceSkeletonShimmer 1.5s infinite linear;
        border-radius: 4px;
    }
    @keyframes ceSkeletonShimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
    .ce-skeleton-text { height: 14px; margin-top: 4px; margin-bottom: 4px; }
    .ce-skeleton-icon { width: 24px; height: 24px; border-radius: 50%; display: inline-block; }

	
	
    /* Resizer (RTL Compatible) */
    .myCloudResizer {
        width: 6px;
        cursor: col-resize;
        background: transparent;
        z-index: 10;
        border-inline-start: 2px solid var(--border-default);
        transition: background 0.2s, border-color 0.2s;
        position: relative;
    }
    .myCloudResizer:hover {
        background: var(--accent-primary);
        border-color: var(--accent-primary);
        opacity: 0.5;
    }
    .myCloudResizer::after {
        content: '';
        position: absolute;
        top: 0;
        bottom: 0;
        inset-inline-start: -20px; 
        inset-inline-end: 0; 
        z-index: 20;
        background-color: transparent;
    }
   
    /* Scrollbars */
    ::-webkit-scrollbar {
        width: 16px;
        height: 16px;
		border-radius: 6px;
    }
    ::-webkit-scrollbar-track {
        background: var(--gray-20);
    }
    ::-webkit-scrollbar-thumb {
        background: var(--gray-50);
        border: 3px solid var(--gray-20);
        border-radius: 99px;
		min-height: 30px;
    }
    ::-webkit-scrollbar-thumb:hover {
        background: var(--gray-60);
    }
   
    /* Loading Spinner */
    .myCloud-spinner {
        display: inline-block;
        width: 40px;
        height: 40px;
        border: 4px solid rgba(255, 255, 255, 0.3);
        border-radius: 50%;
        border-top: 4px solid var(--gray-00);
        animation: spin 1s linear infinite;
        -webkit-animation: spin 1s linear infinite;
    }
    .myCloud-spinner.dark {
        border: 4px solid rgba(0, 0, 0, 0.1);
        border-top: 4px solid var(--gray-80);
    }
    .myCloud-loading-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 15px;
        width: 100%;
        height: 100%;
        color: inherit;
    }
   
    @-webkit-keyframes spin { 0% { -webkit-transform: rotate(0deg); } 100% { -webkit-transform: rotate(360deg); } }
    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    /* Focus States */
    .myCloudTree:focus, .myCloudDetails:focus {
        outline: none !important;
    }
    /* Utility Classes (Restored) */
    .ce-flex-center {
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .ce-flex-column {
        display: flex;
        flex-direction: column;
    }
    .ce-gap-small {
        gap: 8px;
    }
    .ce-gap-med {
        gap: 15px;
    }
	

    /* =========================================
       3. WINDOW TITLE BAR
       ========================================= */
    .myCloudTitleBar {
        background: var(--gray-00);
        height: 40px;
        padding-inline-start: max(16px, env(safe-area-inset-left));
        padding-inline-end: 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid var(--border-default);
        user-select: none;
        color: var(--text-primary);
    }
    .myCloudTitle {
        color: var(--text-primary);
        font-weight: 600;
        font-size: 14px;
        letter-spacing: 0.2px;
    }
    .myCloudTitleActions {
        display: flex;
        height: 100%;
    }
    .myCloudClose {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 46px;
        height: 100%;
        cursor: pointer;
        font-size: 16px;
        color: var(--text-primary);
        transition: all 0.1s ease;
    }
    .myCloudClose:hover {
        background-color: var(--danger);
        color: var(--gray-00);
    }
    /* =========================================
       4. TOOLBAR (Ribbon Style)
       ========================================= */
    .myCloudToolbar {
        background: var(--gray-05);
        border-bottom: 1px solid var(--border-default);
        display: flex;
        gap: 4px;
        padding: 4px 8px 5px;
        flex: 1;
		min-width: 0;
        overflow-x: auto;
        overflow-y: hidden;
        user-select: none;
        align-items: flex-start;
        position: relative;
        padding-inline-end: 10px; 
        z-index: 100;
		min-height: auto;
        scrollbar-width: none;
        -ms-overflow-style: none;
    }
    .myCloudToolbar::-webkit-scrollbar {
        display: none;
    }

    .myCloudToolbar-wrapper {
        position: relative;
        display: flex;
        width: 100%;
		max-width: 100%;
        flex-shrink: 0;
		min-width: 0;
    }
    .toolbar-indicator-start, .toolbar-indicator-end {
        position: absolute;
        top: 0; bottom: 0; width: 32px;
        pointer-events: none; z-index: 105;
        opacity: 0; transition: opacity 0.2s ease;
    }
    .toolbar-indicator-start {
        inset-inline-start: 0;
        background: linear-gradient(to right, rgba(0,0,0,0.12) 0%, transparent 100%);
    }
    :dir(rtl) .toolbar-indicator-start { background: linear-gradient(to left, rgba(0,0,0,0.12) 0%, transparent 100%); }
    .toolbar-indicator-end {
        inset-inline-end: 0px;
        background: linear-gradient(to left, rgba(0,0,0,0.12) 0%, transparent 100%);
    }
    :dir(rtl) .toolbar-indicator-end { background: linear-gradient(to right, rgba(0,0,0,0.12) 0%, transparent 100%); }

    .ce-dark-mode .toolbar-indicator-start { background: linear-gradient(to right, rgba(0,0,0,0.4) 0%, transparent 100%); }
    :dir(rtl) .ce-dark-mode .toolbar-indicator-start { background: linear-gradient(to left, rgba(0,0,0,0.4) 0%, transparent 100%); }
    .ce-dark-mode .toolbar-indicator-end { background: linear-gradient(to left, rgba(0,0,0,0.4) 0%, transparent 100%); }
    :dir(rtl) .ce-dark-mode .toolbar-indicator-end { background: linear-gradient(to right, rgba(0,0,0,0.4) 0%, transparent 100%); }

    .myCloudToolbar::before {
        display: none;
    }
    .myCloudToolbar button {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        background: var(--gray-05);
        border: 1px solid transparent;
        border-radius: 4px;
        min-width: 64px;
        height: 52px;
        cursor: pointer;
        padding: 4px;
        transition: background-color 0.1s ease;
    }
    .myCloudToolbar button:hover:not(:disabled) {
        background: var(--selection-bg);
        transform: scale(1.05);
        transform-origin: top;
        border: 1px solid var(--selection-border);
		box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    }
    .myCloudToolbar button:active:not(:disabled) {
        background: var(--active-bg);
        transform: scale(1.05);
        transform-origin: top;
		box-shadow: inset 0 1px 2px rgba(0,0,0,0.3);
    }
    .myCloudToolbar button:disabled {
        opacity: 0.6;
        cursor: default;
    }
    .myCloudToolbar button span:first-child {
        font-size: 18px;
    }
    .myCloudToolbar button span:last-child {
        margin-top: 4px;
        font-size: 11px;
        font-weight: 400;
        color: var(--text-primary);
    }
    .myCloudDivider {
        width: 1px;
        height: 32px;
        background: var(--border-default);
        margin: 0 8px;
        border: none;
        flex-shrink: 0;
    }
    .myCloudToolbar button .myCloudIcon {
        width: 32px !important;
        height: 32px !important;
        border-radius: 50%;
        background-color: var(--hover-bg-very-light);
        color: var(--gray-90);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 2px;
        transition: all 0.2s ease;
    }
    .myCloudToolbar button .myCloudIcon svg {
        width: 18px;
        height: 18px;
        fill: currentColor;
        stroke: none;
    }
    /* [FIX] Force all SVG paths/shapes to white when the button is hovered */
    .myCloudToolbar button:not(:disabled):hover .myCloudIcon svg,
    .myCloudToolbar button:not(:disabled):hover .myCloudIcon svg * {
        fill: var(--gray-00);
        stroke: var(--gray-00) ;
    }
    .myCloudToolbar button:not(:disabled):hover .myCloudIcon {
        background-color: var(--accent-primary);
        color: var(--gray-00);
        transform: scale(1.05);
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    }
    .myCloudToolbar button[data-action="delete"]:not(:disabled):hover .myCloudIcon {
        background-color: var(--danger);
        color: var(--gray-00);
    }
    .myCloudToolbar button:disabled .myCloudIcon {
        opacity: 0.5;
        background-color: transparent;
        border: 1px solid var(--text-disabled);
    }
    /* Toggle States (Tree View) */
    .myCloudToolbar button.tree-on .myCloudIcon,
    .ce-floating-item.tree-on .myCloudIcon {
     /*   background-color: var(--success-light) !important;
        color: var(--success) !important;
        border: 1px solid var(--success-light); 
		Removed green background */
    }
    .myCloudToolbar button.tree-on:hover .myCloudIcon,
    .ce-floating-item.tree-on:hover .myCloudIcon {
     /*   background-color: var(--success) !important;
        color: var(--gray-00) !important; */
    }
    .myCloudToolbar button.tree-off .myCloudIcon,
    .ce-floating-item.tree-off .myCloudIcon {
        background-color: var(--warning-light) !important;
        color: #d13438 !important;
        border: 1px solid var(--warning-light);
    }
    .myCloudToolbar button.tree-off:hover .myCloudIcon,
    .ce-floating-item.tree-off:hover .myCloudIcon {
        background-color: #d13438 !important;
        color: var(--gray-00) !important;
    }
    /* Active State (Gallery/Toggle Buttons) */
    .myCloudToolbar button.ce-force-active .myCloudIcon,
    .ce-floating-item.ce-force-active .myCloudIcon {
        background-color: #c7e0f4 !important;
        color: var(--accent-primary) !important;
        border: 1px solid #c7e0f4;
    }
    .myCloudToolbar button.ce-force-active:hover .myCloudIcon,
    .ce-floating-item.ce-force-active:hover .myCloudIcon {
        background-color: var(--accent-primary) !important;
        color: var(--gray-00) !important;
    }
    /* Cloud Switcher Tabs */
    .myCloudCloudSwitcher {
        display: flex;
        gap: 4px;
        padding: 8px 16px 0 16px;
        background: var(--gray-05);
        border-bottom: 1px solid var(--border-default);
        overflow-x: auto;
        flex-shrink: 0;
        scrollbar-width: none;
        -ms-overflow-style: none;
        user-select: none;
        -webkit-user-select: none;
    }
    .myCloudCloudSwitcher::-webkit-scrollbar {
        display: none;
    }
    .ce-cloud-btn {
        padding: 6px 24px;
        min-width: 120px;
        text-align: center;
        border: 1px solid transparent;
        background: transparent;
        color: var(--gray-80);
        border-radius: 6px 6px 0 0;
        margin-bottom: -1px;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        transition: background 0.1s ease, color 0.1s ease;
        white-space: nowrap;
        text-transform: capitalize;
        position: relative;
        z-index: 1;
    }
    .ce-cloud-btn:hover {
        background: var(--hover-bg-very-light);
        color: var(--text-primary);
    }
    .ce-cloud-btn.active {
        background: var(--gray-00);
        color: var(--accent-primary);
        font-weight: 600;
        border-color: var(--border-default);
        border-bottom-color: var(--gray-00);
        box-shadow: inset 0 2px 0 var(--accent-primary);
        z-index: 2;
    }
    /* =========================================
       5. SIDEBAR
       ========================================= */
    .myCloudTree {
        width: 33%;
        background: var(--gray-10);
        border-inline-end: 1px solid var(--border-default);
        overflow: auto;
        padding: 10px 0;
        user-select: none;
        font-size: var(--font-size-base);
        text-align: start;
    }
    .myCloudTreeList, .myCloudTreeList ul, .myCloudTreeList li {
        list-style-type: none !important;
        margin: 0;
        padding: 0;
        min-width: 100%;
        width: max-content;
    }
    .myCloudTree > .myCloudTreeList {
        width: max-content;
        min-width: 100%;
    }
    .myCloudTreeList, .myCloudTreeList ul {
        min-width: 100%;
        width: max-content;
        display: block;
    }
    .myCloudTreeList li::marker {
        content: none;
        display: none;
    }
    .myCloudTreeList li>ul {
        padding-inline-start: 18px;
    }
    .myCloudTreeList li > div {
        display: flex;
        align-items: center;
        padding: 0 4px;
        height: var(--tree-row-height, 30px);
        cursor: default;
        border: 1px solid transparent;
        white-space: nowrap;
        transition: background 0.2s ease, color 0.15s ease;
        width: max-content;
        min-width: 100%;
    }
    .myCloudTreeList li>div:hover {
        background: var(--gray-20);
    }
    .myCloudTreeList li.selectedFolder>div {
        background: var(--gray-45);
        font-weight: 500;
    }
    .myCloudToggle {
        display: inline-block;
        width: 20px;
        text-align: center;
        color: var(--gray-80);
        font-size: var(--toggle-size, 12px);
        cursor: pointer;
        flex-shrink: 0;
    }
    :dir(rtl) .myCloudToggle {
        transform: scaleX(-1);
    }
    .myCloudToggle:hover {
        color: var(--text-primary);
    }
   
    /* Drag Target for Tree Items (Restored) */
    .myCloudTreeList li > div.drop-target {
        background-color: var(--hover-bg-medium) !important;
        outline: 2px dashed var(--accent-primary);
        outline-offset: -2px;
        border-radius: 4px;
    }
    .myCloudTreeList li > div:hover {
        background: var(--hover-bg-very-light);
    }
    .myCloudTreeList li > div:hover .myCloudIcon {
        transform: scale(1.05);
        filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
        transition: transform 0.15s ease-out;
    }
    .myCloudTreeList li > div:hover span:last-child {
        font-weight: 700;
        color: var(--accent-primary);
        transition: all 0.15s ease-out;
        padding-inline-start: 2px;
    }
    .myCloudTreeList li.selectedFolder > div {
        background: var(--gray-45);
        font-weight: 500;
    }
    .myCloudTreeList li.selectedFolder > div:not(:hover) span:last-child {
        color: inherit;
        font-size: inherit;
    }
    .myCloudTreeList li > div.tree-focus {
        box-shadow: inset 0 0 0 2px var(--accent-primary);
        background-color: var(--hover-bg-very-light);
        z-index: 1;
        position: relative;
    }
    /* =========================================
       6. MAIN CONTENT
       ========================================= */
    .myCloudDetails {
        flex: 1;
        display: flex;
        flex-direction: column;
        overflow: auto;
    }
    .myCloudTableContainer {
        display: flex;
        flex-direction: column;
        flex: 1;
        min-height: 0;
        width: max-content;
        min-width: 100%;
    }
    /* Dropzone */
    .myCloud-dropzone {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        margin: 0;
        padding: 40px;
        min-height: 100px;
        background-color: transparent;
        border: 1px dashed transparent;
        color: color: var(--text-secondary);
        font-weight: 500;
        cursor: default;
        transition: all 0.2s ease;
        box-sizing: border-box;
        pointer-events: auto !important;
		opacity: 0;
    }
    .myCloud-dropzone:hover {
        opacity: 0.5;
        border-color: var(--border-medium);
    }
    .myCloud-dropzone.drag-active {
        opacity: 1;
        background-color: var(--hover-bg-light);
        border-color: var(--accent-primary);
        color: var(--accent-primary);
    }
    
    /* Main View Breadcrumb Bar */
    .myCloud-breadcrumb-bar {
        position: sticky;
        top: 0;
        left: 0;
        right: 0;
        z-index: 2001;
        background: var(--gray-05);
        border-bottom: 1px solid var(--border-default);
        padding: 4px 12px;
        font-size: 13px;
        color: var(--text-secondary);
        display: flex;
        align-items: center;
        flex-wrap: nowrap;
        overflow-x: auto;
        white-space: nowrap;
        min-height: 35px;
        max-height: 35px;
        flex-shrink: 0;
        scrollbar-width: none;
    }
    .myCloud-breadcrumb-bar::-webkit-scrollbar { display: none; }
	
 /* --- NEW TAGS DROPDOWN STYLES --- */
 .myCloud-tag-dropdown-wrapper {
     position: relative;
     display: inline-block;
     margin-left: 12px;
     vertical-align: middle;
 }
 .myCloud-tag-dropdown-btn {
     background: transparent;
     border: 1px solid var(--myCloud-border, #ccc);
     border-radius: 4px;
     padding: 2px 8px;
     font-size: 12px;
     cursor: pointer;
     color: var(--myCloud-text, #333);
     display: inline-flex;
     align-items: center;
     height: 24px;
 }
 .myCloud-tag-dropdown-btn:hover {
     background: var(--myCloud-hover, #f0f0f0);
 }
 .myCloud-tag-dropdown-menu {
     display: none;
     position: absolute;
     top: 100%;
     right: 0;
     margin-top: 4px;
     background: var(--myCloud-bg, #fff);
     border: 1px solid var(--myCloud-border, #ccc);
     box-shadow: 0 4px 6px rgba(0,0,0,0.1);
     border-radius: 4px;
     z-index: 1000;
     min-width: 140px;
     padding: 4px 0;
 }
 .myCloud-tag-dropdown-menu.show {
     display: block;
 }
 .myCloud-tag-dropdown-item {
     padding: 6px 12px;
     font-size: 13px;
     cursor: pointer;
     display: flex;
     align-items: center;
     gap: 8px;
     color: var(--myCloud-text, #333);
 }
 .myCloud-tag-dropdown-item:hover {
     background: var(--myCloud-hover, #f0f0f0);
 }
 .myCloud-tag-color-dot {
     width: 12px;
     height: 12px;
     border-radius: 50%;
     display: inline-block;
     box-shadow: inset 0 0 0 1px rgba(0,0,0,0.1);
 }	

   
    /* Global Drag Highlight (Restored) */
    .myCloudTableContainer.drag-active-global {
        background-color: var(--hover-bg-medium) !important;
        outline: 2px dashed var(--accent-primary);
        outline-offset: -2px;
        pointer-events: auto !important;
    }
    .myCloudTableContainer.drag-active-global .myCloud-dropzone {
        border-color: transparent;
    }
   
    /* Table & Rows */
    .myCloudTable {
        min-width: 100%;
        width: max-content;
        border-collapse: collapse;
        table-layout: auto;
        font-size: var(--font-size-base);
        user-select: none;
        -webkit-user-select: none;
        transition: all 0.2s ease;
        will-change: font-size, transform;
    }
    .myCloudTable th {
        background: var(--gray-05);
        border-inline-end: 1px solid var(--border-default);
        border-bottom: 1px solid var(--border-default);
        padding: 8px 10px;
        text-align: start;
        font-weight: 500;
        color: var(--gray-90);
        position: sticky !important;
        top: 35px;
        z-index: 2000 !important;
        transition: none !important;
        box-shadow: 0 1px 0 var(--border-default);
    }
    .myCloudTable th:hover {
        background: var(--gray-15);
    }
 
    .myCloudTable th:nth-child(1),
    .myCloudTable td:nth-child(1) {
        width: 32px;
        padding: 0;
        text-align: center;
        vertical-align: middle;
    }
	.myCloudTable th:nth-child(2),
    .myCloudTable td:nth-child(2) {
        width: 34px;
        padding: 0;
        text-align: center;
        overflow: visible !important;
        position: relative;
		/* Sit above Name column (z=1) and others */
        z-index: 200; 
    }
    .myCloudRow:hover td:nth-child(2) {
		/* Ensure expanded thumb sits above Name hover state (z=100) and subsequent rows */
        z-index: 300; 
    }
    .myCloudTable th:nth-child(3),
    .myCloudTable td:nth-child(3) {
        padding-inline-start: 5px;
        overflow: visible !important;
        position: relative;
        z-index: 1;
    }
    .myCloudRow:hover td:nth-child(3) {
        z-index: 100;
    }
    .myCloudTable th:nth-child(4),
    .myCloudTable td:nth-child(4) {
        width: 6.5em;
        text-align: end;
    }
    .myCloudTable th:nth-child(5),
    .myCloudTable td:nth-child(5) {
        width: 9.5em;
    }
    .myCloudRow {
        height: var(--row-height);
        border-bottom: 1px solid transparent;
        transition: background 0.2s ease;
    }
    .myCloudRow:hover .ce-name-text {
        color: var(--accent-primary);
        transition: font-size 0.1s ease-in-out;
    }
    .ce-no-checkboxes .myCloudTable th:nth-child(1),
    .ce-no-checkboxes .myCloudTable td:nth-child(1) {
        display: none;
    }
    .ce-no-hover-menu .ce-row-actions {
        display: none !important;
    }
    .myCloudRow td {
        padding: 0 8px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        color: var(--text-primary);
        cursor: default;
        vertical-align: middle;
    }
    .myCloudRow:hover {
        background: var(--hover-bg-very-light);
    }
   
    /* Inactive Selection */
    .myCloudRow.selected {
        background-color: var(--gray-40) !important;
        color: var(--gray-90) !important;
    }
    .myCloudRow.selected .ce-name-text,
    .myCloudRow.selected .myCloudIcon {
        color: var(--gray-90) !important;
    }
    .myCloudRow.selected:hover {
        background: #b1d6f0;
    }
   
    /* Active Selection (Focused) */
    .myCloudDetails:focus-within .myCloudRow.selected ,
    .myCloud-commander-pane.active .myCloudRow.selected td {
        background-color: var(--selection-bg-strong) !important;
        color: var(--gray-00) !important;
    }
    .myCloudDetails:focus-within .myCloudRow.selected .ce-name-text,
    .myCloud-commander-pane.active .myCloudRow.selected .ce-name-text,
    .myCloudDetails:focus-within .myCloudRow.selected .myCloudIcon svg,
    .myCloud-commander-pane.active .myCloudRow.selected .myCloudIcon svg,
    .myCloudDetails:focus-within .myCloudRow.selected .myCloudIcon svg *,
    .myCloud-commander-pane.active .myCloudRow.selected .myCloudIcon svg * {
		color: var(--gray-00) !important;
        fill: var(--gray-00) !important;
		opacity: 1 !important;
    }
    .myCloudDetails:focus-within .myCloudRow.selected .myCloudCheckbox {
        accent-color: var(--gray-00);
    }
    .ce-col-check {
        cursor: pointer !important;
        transition: background 0.1s;
    }
    .ce-col-check:hover {
        background: var(--hover-bg-light);
    }
    .ce-col-icon {
        width: 34px;
        text-align: center;
    }
	/* List view jpg thumbnails */
.ce-list-thumb-img {
        width: 30px; 
        height: 30px; 
        object-fit: cover; 
        border-radius: 3px; 
        vertical-align: middle;
        box-shadow: 0 1px 2px rgba(0,0,0,0.15);
        display: inline-block;
        animation: ceFadeInScale 0.3s ease;
        transition: transform 0.15s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.15s;
        position: relative;
        background-color: var(--gray-00);
        transform-origin: left center; /* Prevents cutting on the left */
    }
    :dir(rtl) .ce-list-thumb-img {
        transform-origin: right center;
    }
	.ce-list-thumb-img:hover,
    .ce-list-thumb-img.ce-touch-expanded {
        transform: scale(4);
        z-index: 1000;
        box-shadow: 0 8px 24px rgba(0,0,0,0.3);
        border: 1px solid var(--border-medium);
        border-radius: 4px;
    }
    .myCloudIcon {
        display: inline-flex !important;
        align-items: center;
        justify-content: center;
        width: 24px;
        height: 24px;
        margin-right: 0;
        vertical-align: middle;
    }
    .myCloudIcon svg {
        width: 22px;
        height: 22px;
    }
    .myCloudInlineInput {
        width: calc(100% - 10px);
        padding: 4px;
        border: 1px solid var(--accent-primary);
		/* Critical: Prevents iOS Safari from auto-zooming when focused */
		font-size: 16px !important; 
        font: inherit;
        user-select: text !important;
        cursor: text;
    }
    .ce-row-content {
        display: flex;
        align-items: center;
        position: static;
    }
    .ce-name-text {
        flex: 1;
        min-width: 0;
        display: block;
        white-space: nowrap;
        padding-inline-end: 5px;
    }
   
    .multi-select-active .ce-row-actions {
        display: none !important;
        pointer-events: none !important;
    }
    /* [FIXED] Ribbon Bar Multi-Select: Background on ITEM, Transparent ICON */
    .toolbar-multi-active .ce-floating-item[data-action="copy"]:not(:disabled),
    .toolbar-multi-active .ce-floating-item[data-action="move"]:not(:disabled),
    .toolbar-multi-active .ce-floating-item[data-action="delete"]:not(:disabled),
    .toolbar-multi-active .ce-floating-item[data-action="download"]:not(:disabled) {
        background-color: var(--hover-bg-medium) !important;
    }
    .toolbar-multi-active .ce-floating-item[data-action="copy"]:not(:disabled),
    .toolbar-multi-active .ce-floating-item[data-action="move"]:not(:disabled),
    .toolbar-multi-active .ce-floating-item[data-action="delete"]:not(:disabled) ,
    .toolbar-multi-active .ce-floating-item[data-action="download"]:not(:disabled) {
        background-color: transparent !important;
        border: none !important;
        box-shadow: none !important;
        color: var(--accent-primary) !important;
    }
    .toolbar-multi-active button[data-action="copy"]:not(:disabled),
    .toolbar-multi-active button[data-action="move"]:not(:disabled),
    .toolbar-multi-active button[data-action="delete"]:not(:disabled),
    .toolbar-multi-active button[data-action="download"]:not(:disabled) {
        background-color: var(--hover-bg-medium) !important;
     }



.myCloudCheckbox {
    /* Hide the default browser checkbox */
    appearance: none;
    -webkit-appearance: none; 
    width: 18px !important;
    height: 18px !important;
    border: 2px solid var(--gray-45);
    border-radius: 40%; /* Makes it circular; change to 4px for rounded square */
    background: transparent;
    cursor: pointer;
    position: relative;
    transition: all 0.2s ease;
    outline: none;
    vertical-align: middle;
}

/* Hover State */
.myCloudCheckbox:hover {
    border-color: var(--accent-primary);
    background: var(--hover-bg-light);
}

/* Checked State */
.myCloudCheckbox:checked {
    background: var(--accent-primary);
    border-color: var(--accent-primary);
}

/* The "Check" mark (white L-shape) */
.myCloudCheckbox:checked::after {
    content: "";
    position: absolute;
    left: 5px;
    top: 2px;
    width: 4px;
    height: 8px;
    border: solid white;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
}

/* Dark Mode Adjustments */
.ce-dark-mode .myCloudCheckbox {
    border-color: var(--gray-60) !important;
    background-color: rgba(255, 255, 255, 0.2) !important;
}

/* Focused Row styling (When you select with keys) */
.myCloudRow.selected .myCloudCheckbox {
    border-color: rgba(255,255,255,0.8);
}
.myCloudDetails:focus-within .myCloudRow.selected .myCloudCheckbox:checked {
    background: #fff;
    border-color: #fff;
}
.myCloudDetails:focus-within .myCloudRow.selected .myCloudCheckbox:checked::after {
    border-color: var(--accent-primary); /* Flip color when row is active blue */
}

    .myCloudRow:hover td:nth-child(2) .myCloudIcon {
        transform: scale(1.3);
        transform-origin: center center;
        transition: transform 0.15s ease-out;
        filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
    }
    .myCloudColResizer {
        position: absolute;
        top: 0;
        inset-inline-end: 0;
        width: 5px;
        height: 100%;
        cursor: col-resize;
        z-index: 20;
    }
    .myCloudColResizer:hover {
        border-right: 2px solid var(--accent-primary);
    }
    .myCloudTable th {
        position: relative;
        overflow: hidden;
        white-space: nowrap;
    }
    /* Marquee Selection (Restored) */
    .myCloud-marquee {
        position: fixed;
        border: 1px solid #3399ff;
        background-color: rgba(51, 153, 255, 0.2);
        z-index: 10000;
        pointer-events: none;
        display: none;
    }
    /* =========================================
       7. MENUS & POPUPS (FIXED Z-INDEX)
       ========================================= */
    .myCloudLoadingPopup {
        position: fixed;
        bottom: 30px; /* Moved back to the bottom */
        left: 50%;
        /* Start slightly lower for the slide-up effect */
        transform: translate(-50%, 20px) scale(0.95);
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(12px) saturate(150%);
        -webkit-backdrop-filter: blur(12px) saturate(150%);
        border: 1px solid rgba(0, 0, 0, 0.08);
        color: var(--text-primary);
        padding: 10px 20px;
        border-radius: 32px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12), 0 2px 6px rgba(0, 0, 0, 0.04);
        font-weight: 500;
        z-index: 200000;
        opacity: 0;
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        user-select: none;
        pointer-events: none;
    }
    .myCloudLoadingPopup.visible {
        /* Bounces up into resting position */
        transform: translate(-50%, 0) scale(1);
        opacity: 1;
    }
    .myCloudLoadingPopup.hide {
        /* Drops back down smoothly */
        transform: translate(-50%, 20px) scale(0.95);
        opacity: 0;
    }
    #myCloudLoadingPopup {
        z-index: 9999;
    }
	
    .myCloudProgressPopup {
        position: fixed;
        bottom: 20px;
        inset-inline-end: 20px;
        width: 300px;
        background: var(--gray-00);
        border: 1px solid var(--border-default);
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        padding: 16px;
        z-index: 21000;
        display: flex;
        flex-direction: column;
        gap: 8px;
        font-family: var(--font-family);
        user-select: none;
        pointer-events: none;
    }
    .myCloudProgressTitle {
        font-weight: 600;
        font-size: 14px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .myCloudProgressBar {
        width: 100%;
        height: 6px;
        background: var(--gray-30);
        border-radius: 3px;
        overflow: hidden;
    }
    .myCloudProgressFill {
        height: 100%;
        width: 0%;
        background: var(--accent-primary);
        transition: width 0.1s ease-out;
    }
    .myCloudProgressText {
        font-size: 12px;
        color: var(--text-secondary);
        text-align: end;
    }
    /* =========================================
       8. MODALS & DIALOGS
       ========================================= */
    @keyframes ceFadeInScale { from { opacity: 0; transform: scale(0.76); } to { opacity: 1; transform: scale(1); } }
    @keyframes ceFadeOutScale { from { opacity: 1; transform: scale(1); } to { opacity: 0; transform: scale(0.76); } }
    @keyframes ceBgIn { from { background-color: rgba(0, 0, 0, 0); } to { background-color: rgba(0, 0, 0, 0.5); } }
    @keyframes ceBgOut { from { background-color: rgba(0, 0, 0, 0.5); } to { background-color: rgba(0, 0, 0, 0); } }
    /* OVERLAY Z-INDEX FIX: 10000 > 2000 (Headers) */
    .myCloudOverlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 10000;
        animation: ceBgIn 0.3s ease-out forwards;
    }
    .myCloudOverlay.closing {
        animation: ceBgOut 0.2s ease-out forwards;
    }
    #myCloudPreviewOverlay {
        animation: none !important;
        z-index: 12000;
    }
    #myCloudPreviewOverlay.closing {
        background: transparent !important;
        animation: none !important;
    }
    .myCloudModal {
        /* Win 11 Acrylic Surface */
        background: var(--gray-00) !important;
		backdrop-filter: none  !important;	
        border: 1px solid rgba(0, 0, 0, 0.06);
        border-radius: 8px; /* Softer corners */
        width: 400px;
        max-width: 90%;
        box-shadow: 0 24px 48px rgba(0, 0, 0, 0.18), 0 8px 16px rgba(0,0,0,0.08);
        display: flex;
        flex-direction: column;
        animation: ceFadeInScale 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        transform-origin: center center;
        animation-fill-mode: forwards;
        pointer-events: auto;
    }
    .myCloudModal.closing {
        animation: ceFadeOutScale 0.2s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
	
	.myCloudOverlay.closing {
	    animation: ceBgOut 0.6s ease-out forwards;
	}


    .myCloudModalHeader {
        padding: 12px 16px;
		padding-inline-end: 10px;
        font-weight: 600;
        font-size: 16px;
        border-bottom: 1px solid var(--border-default);
        background: var(--gray-10);
        color: var(--text-primary);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex: none;
        user-select: none;
    }
    .myCloudModal:not(.search-modal):not(.preview):not(.ce-help-modal) .myCloudModalHeader {
        cursor: move;
    }
    .myCloudModalBody {
        padding: 16px;
		background: var(--gray-00);
    }
    .myCloudButtons {
        text-align: right;
        margin-top: 16px;
        display: flex;
        justify-content: flex-end;
        gap: 8px;
    }
    .myCloudButtons button {
        padding: 6px 16px;
        border-radius: 4px;
        cursor: pointer;
        font-weight: 500;
        border: 1px solid var(--border-medium);
        background: var(--gray-00);
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .myCloudButtons button#myCloud_ok {
        background: var(--accent-primary);
        border-color: var(--accent-primary);
        color: var(--gray-00);
    }
    .myCloudModal.conflict {
        width: 400px !important;
        height: auto !important;
        max-width: 90%;
    }
    .myCloudModal.conflict .myCloudModalBody {
        padding: 12px 16px;
    }
    .myCloudModal.conflict .myCloudButtons {
        margin-top: 12px;
        justify-content: center;
    }
    .myCloudModal.tree-selector {
        width: 450px;
        height: 550px;
    }
    .myCloudModal.tree-selector .myCloudModalBody {
        flex: 1;
        display: flex;
        flex-direction: column;
        padding: 10px;
        overflow: hidden;
        text-align: start;
    }
    .myCloudTreeBox {
        flex: 1;
        border: 1px solid var(--border-default);
        background: var(--gray-00);
        overflow: auto;
        padding: 8px;
        margin-top: 8px;
        text-align: start;
        box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.05);
    }
    .myCloudTreeBox ul {
        list-style: none;
        padding-inline-start: 16px;
        margin: 0;
        min-width: 100%;
        width: max-content;
    }
    .myCloudTreeBox > ul {
        width: max-content;
        min-width: 100%;
    }
    .myCloudTreeBox li {
        margin: 1px 0;
        min-width: 100%;
        width: max-content;
    }
    .myCloudTreeBox .tree-item {
        display: flex;
        align-items: center;
        padding: 2px 0;
        cursor: default;
        border: 1px solid transparent;
		white-space: nowrap ;
        width: max-content;
        min-width: 100%;
    }
    .myCloudTreeBox .tree-children {
        min-width: 100%;
        width: max-content;
     }
    .myCloudTreeBox .tree-item:hover {
        background-color: var(--gray-15);
    }
    .myCloudTreeBox .tree-item.selected {
        background-color: #cce8ff;
        border-color: var(--selection-border);
    }
    .myCloudTreeBox .tree-toggle {
        display: inline-flex;
        width: 24px;
        height: 24px;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        color: var(--text-secondary);
        font-size: var(--toggle-size, 12px);
        user-select: none;
    }
    :dir(rtl) .myCloudTreeBox .tree-toggle {
        transform: scaleX(-1);
    }
    .myCloudToggle:hover, .tree-toggle:hover {
        color: var(--accent-primary);
        transform: scale(1.05);
    }
    .myCloudTreeBox .tree-toggle:hover {
        color: var(--text-primary);
        font-weight: bold;
    }
    .myCloudTreeBox .tree-icon {
        width: 20px;
        height: 20px;
        margin-inline-end: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .myCloudTreeBox .tree-icon svg {
        width: 18px;
        height: 18px;
    }
    .myCloudTreeBox .tree-content {
        display: flex;
        align-items: center;
        padding: 2px 4px;
        flex: 1;
        border: 1px solid transparent;
    }
    .myCloudTreeBox .tree-content:hover {
        background-color: var(--gray-15);
    }
    .myCloudTreeBox .tree-content.selected {
        background-color: #cce8ff;
        border-color: var(--selection-border);
    }


.myCloud-symbol-grid { 
        display: grid; 
        gap: 6px; 
        padding: 10px; 
        align-content: start; 
        overflow-y: visible; 
        width: 100%; 
        height: auto;
        box-sizing: border-box; 
		flex: 1;         
        min-height: 100%;
    }
    
    .myCloud-symbol-item { 
        display: flex; 
        flex-direction: column; 
        align-items: center; 
        justify-content: flex-start; 
        text-align: center; 
        border: 1px solid transparent; 
        border-radius: 4px; 
        cursor: pointer; 
        position: relative; 
        box-sizing: border-box; 
        transition: background 0.1s, border-color 0.1s; 
        /* [CHANGED] Item takes full width of its grid cell */
        width: 100%; 
        margin-bottom: 0;
    }
    
    .myCloud-symbol-item:hover { background-color: var(--hover-bg-light); border-color: var(--border-subtle); }
    .myCloud-symbol-item.selected { background-color: var(--selection-bg); border-color: var(--selection-border); }
    .ce-sym-icon-box { display: flex; align-items: center; justify-content: center; overflow: hidden; pointer-events: none; }
    .ce-sym-label { margin-top: 2px; line-height: 1.3; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; font-size: 12px; color: var(--text-primary); pointer-events: none; width: 100%; padding: 0 4px; word-break: break-word; }
    
    .ce-sym-checkbox { position: absolute; top: 2px; left: 2px; z-index: 10; width: 16px; height: 16px; cursor: pointer; opacity: 1; transition: opacity 0.1s; }
    .myCloud-symbol-item:hover .ce-sym-checkbox, .myCloud-symbol-item.selected .ce-sym-checkbox { opacity: 1; }
    .ce-no-checkboxes .ce-sym-checkbox { display: none !important; }
	
    /* [FIX] Enlarge Checkbox Touch Target (Invisible Handle) */
    .ce-sym-checkbox::after {
        content: '';
        position: absolute;
        top: -15px; left: -15px; right: -25px; bottom: -25px;
        z-index: 1; /* Ensure it sits above the icon below */
    }
	
    /* Sizes */
	/* Small: List-like grid. Row layout. */
    .ce-sym-small { grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); }
    .ce-sym-small .myCloud-symbol-item { height: 40px; flex-direction: row; padding: 0 5px; text-align: left; }
    .ce-sym-small .ce-sym-icon-box { width: 24px; height: 24px; margin-right: 8px; flex-shrink: 0; }
    .ce-sym-small .ce-sym-label { text-align: left; -webkit-line-clamp: 1; font-size: 13px; }
    .ce-sym-small .ce-sym-checkbox { top: 12px; left: auto; right: 5px; }
    
    :dir(rtl) .ce-sym-small .ce-sym-icon-box { margin-right: 0; margin-left: 8px; }
    :dir(rtl) .ce-sym-small .ce-sym-label { text-align: right; }
    :dir(rtl) .ce-sym-small .ce-sym-checkbox { right: auto; left: 5px; }

    /* Medium (Default) */
    .ce-sym-medium { grid-template-columns: repeat(auto-fill, minmax(90px, 1fr)); }
    .ce-sym-medium .myCloud-symbol-item { height: 90px; padding: 2px 0; }
    .ce-sym-medium .ce-sym-icon-box { width: 48px; height: 48px; margin-bottom: 2px; }
    .ce-sym-medium .myCloudIcon { font-size: 38px !important; width: 38px !important; height: 38px !important; }
    .ce-sym-medium .myCloudIcon svg { width: 38px !important; height: 38px !important; }

    /* Large */
    .ce-sym-large { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); }
    .ce-sym-large .myCloud-symbol-item { height: 140px; padding: 4px 0; }
    .ce-sym-large .ce-sym-icon-box { width: 110px; height: 110px; margin-bottom: 2px; }
    .ce-sym-large .myCloudIcon { font-size: 75px !important; width: 75px !important; height: 75px !important; }
    .ce-sym-large .myCloudIcon svg { width: 75px !important; height: 75px !important; }
    
    /* Extra Large */
    .ce-sym-xlarge { grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); }
    .ce-sym-xlarge .myCloud-symbol-item { height: 230px; padding: 6px 0; }
    .ce-sym-xlarge .ce-sym-icon-box { width: 200px; height: 200px; margin-bottom: 4px; }
    .ce-sym-xlarge .myCloudIcon { font-size: 140px !important; width: 140px !important; height: 140px !important; }
    .ce-sym-xlarge .myCloudIcon svg { width: 140px !important; height: 140px !important; }
    .ce-sym-xlarge .ce-sym-label { font-size: 15px; -webkit-line-clamp: 3; }
	
/* --- FLAT BUTTON LEGACY (For Desktop without Ribbons) --- */
    .ce-floating-item { display: flex !important; flex-direction: column !important; align-items: center !important; justify-content: center !important; min-width: 68px !important; height: 62px !important; padding: 4px 8px !important; border: 1px solid var(--border-medium) !important; border-radius: 14px !important; background: transparent !important; color: var(--gray-90) !important; cursor: pointer; transition: all 0.2s ease; }
    .ce-floating-item:hover { background: var(--hover-bg-light) !important; transform: scale(1.05); border-color: rgba(0, 0, 0, 0.25) !important; z-index: 10; }
    .ce-floating-item:hover .myCloudIcon svg path, .ce-floating-item:hover .myCloudIcon svg rect { fill: var(--gray-60) !important; stroke: var(--gray-60) !important; }
    .ce-floating-item .myCloudIcon { width: 28px !important; height: 28px !important; margin-bottom: 4px !important; margin-right: 0 !important; background: transparent !important; }
    .ce-floating-item .myCloudIcon svg { width: 24px !important; height: 24px !important; fill: currentColor !important; }
    .ce-floating-item span:last-child { font-size: 11px !important; line-height: 1.1; text-align: center; color: inherit; font-weight: 500; margin-top: 0 !important; }
    .ce-floating-item:hover span:last-child { color: var(--text-primary) !important; }
    .ce-floating-item.ce-force-active { background-color: transparent !important; border-color: transparent !important; color: var(--accent-primary) !important; }
    .ce-floating-item:disabled { opacity: 0.3 !important; cursor: not-allowed; background-color: transparent !important; }
    .ce-floating-item:disabled span { color: var(--text-disabled) !important; }
    .ce-floating-item:disabled .myCloudIcon { background-color: transparent !important; border-color: var(--gray-30) !important; }
    .ce-floating-item:disabled .myCloudIcon svg, .ce-floating-item:disabled .myCloudIcon svg path { fill: var(--text-disabled) !important; stroke: var(--text-disabled) !important; filter: grayscale(100%); }
	
    /* --- MS OFFICE STYLE RIBBON TABS (OVERRIDES) --- */
    .myCloudToolbar.ce-stacked-toolbar { padding: 4px 8px !important; align-items: center !important; gap: 4px !important; }
    .ce-stacked-toolbar .ce-ribbon-btn { flex-direction: row !important; gap: 6px; height: calc(var(--font-size-base) * 2.2) !important; min-width: auto !important; border-radius: 4px !important; margin-inline-end: 2px; padding: 4px 14px !important; background-color: transparent !important; border: 1px solid transparent !important; transition: all 0.15s ease; }
    .ce-stacked-toolbar .ce-ribbon-btn .myCloudIcon { margin-bottom: 0 !important; width: calc(var(--font-size-base) * 1.2) !important; height: calc(var(--font-size-base) * 1.2) !important; display: flex !important; justify-content: center; align-items: center; background: transparent !important;}
    .ce-stacked-toolbar .ce-ribbon-btn .myCloudIcon svg { width: 100% !important; height: 100% !important; }
    .ce-stacked-toolbar .ce-ribbon-btn span.ce-ribbon-label { font-size: calc(var(--font-size-base) * 0.9) !important; margin-top: 0 !important; }
    .ce-stacked-toolbar .ce-ribbon-btn svg, .ce-stacked-toolbar .ce-ribbon-btn svg path { fill: var(--ribbon-text) !important; transition: fill 0.15s ease; }
    .ce-stacked-toolbar .ce-ribbon-btn:hover { background: var(--hover-bg-very-light) !important; border-color: transparent !important; }
	.ce-stacked-toolbar .ce-ribbon-btn.active-parent { background: var(--gray-00) !important; border: 1px solid var(--border-default) !important; margin-bottom: 0 !important; z-index: 2; }
    .ce-stacked-toolbar .ce-ribbon-btn.active-parent span.ce-ribbon-label { color: var(--accent-primary) !important; }
    .ce-stacked-toolbar .ce-ribbon-btn.active-parent svg, .ce-stacked-toolbar .ce-ribbon-btn.active-parent svg path { fill: var(--accent-primary) !important; }
	.ce-stacked-toolbar .ce-ribbon-btn.active-parent svg[stroke="currentColor"] { stroke: var(--accent-primary) !important; fill: none !important; }

    /* Utility Buttons inside Stacked Toolbar (Fav, Settings, Help) */
    .ce-stacked-toolbar #ceFavoritesBtn, .ce-stacked-toolbar #ceSettingsBtn, .ce-stacked-toolbar #btnHelp { border-radius: 4px !important; margin-bottom: 0 !important; border: 1px solid transparent !important; height: 2.0em !important; }
    .ce-stacked-toolbar #ceFavoritesBtn.active-parent, .ce-stacked-toolbar #ceSettingsBtn.active-parent, .ce-stacked-toolbar #btnHelp.active-parent { background: var(--hover-bg-light) !important; border-color: rgba(0, 120, 212, 0.3) !important; margin-bottom: 0 !important; }
    .ce-stacked-toolbar #btnHelp .myCloudIcon { width: 1.2em !important; height: 1.2em !important; margin: 0 !important; }
    .ce-stacked-toolbar #btnHelp .myCloudIcon svg { width: 100% !important; height: 100% !important; min-width: 0 !important; min-height: 0 !important; }

    /* Divider alignment for right-bound stacked utilities */
    .ce-stacked-toolbar .ce-stacked-divider {
        height: calc(var(--font-size-base) * 2.0) !important;
        margin-bottom: 0 !important;
        align-self: center;
    }

    .ce-stacked-toolbar .ce-ribbon-btn { flex-direction: row !important; gap: 6px; height: 2.2em !important; min-width: auto !important; border-radius: 4px 4px 0 0 !important; margin-inline-end: 2px; padding: 4px 14px !important; background-color: transparent !important; border: 1px solid transparent !important; border-bottom: none !important; transition: all 0.15s ease; }
    .ce-stacked-toolbar .ce-ribbon-btn .myCloudIcon { margin-bottom: 0 !important; width: 1.2em !important; height: 1.2em !important; display: flex !important; justify-content: center; align-items: center; background: transparent !important;}
    .ce-stacked-toolbar .ce-ribbon-btn span.ce-ribbon-label { font-size: 1.1em !important; margin-top: 0 !important; }
    
    /* Utility Buttons inside Stacked Toolbar (Fav, Settings, Help) */

    /* Lock utility icons to integer pixels to prevent sub-pixel blurring */

  /* Active Tab Styling */
    .ce-ribbon-btn.active-parent { background: var(--gray-00) !important; border: 1px solid var(--border-default) !important; margin-bottom: 0 !important; z-index: 2; }
    .ce-ribbon-btn.active-parent span.ce-ribbon-label { color: var(--accent-primary) !important; }
    .ce-ribbon-btn.active-parent svg, .ce-ribbon-btn.active-parent svg path { fill: var(--accent-primary) !important; }


    /* Fix Stroke-based SVGs (Settings, Help) turning white on hover due to global toolbar CSS */
    .ce-stacked-toolbar #ceSettingsBtn svg, .ce-stacked-toolbar #ceSettingsBtn svg *, 
	.ce-stacked-toolbar #btnHelp svg, .ce-stacked-toolbar #btnHelp svg * { 
        fill: none !important; stroke: var(--ribbon-text) !important; 
     }
	 .ce-stacked-toolbar #ceSettingsBtn:hover svg, .ce-stacked-toolbar #ceSettingsBtn:hover svg *, 
    .ce-stacked-toolbar #btnHelp:hover svg, .ce-stacked-toolbar #btnHelp:hover svg * { stroke: var(--ribbon-text-hover) !important; fill: none !important; }
    .ce-stacked-toolbar #ceSettingsBtn.active-parent svg, .ce-stacked-toolbar #ceSettingsBtn.active-parent svg *, 
    .ce-stacked-toolbar #btnHelp.active-parent svg, .ce-stacked-toolbar #btnHelp.active-parent svg * { stroke: var(--accent-primary) !important; fill: none !important; }

    /* --- UTILITY BUTTONS (Fav, Settings, Help) --- */
    /* 1. LEGACY MODE: Force 58px height using IDs to guarantee they beat any leftover CSS */
    #ceFavoritesBtn, #ceSettingsBtn, #btnHelp { 
        height: 58px !important; 
        border-radius: 6px !important; 
        margin-bottom: 0 !important; 
        flex-direction: column !important;
    }

    /* 2. STACKED MODE: Dynamic EM scaling */
    .ce-stacked-toolbar #ceFavoritesBtn, 
    .ce-stacked-toolbar #ceSettingsBtn, 
    .ce-stacked-toolbar #btnHelp { 
        height: 2.0em !important; 
        border-radius: 4px !important; 
        margin-bottom: 0 !important; 
        border: 1px solid transparent !important;
        flex-direction: row !important;
    }

    .ce-stacked-toolbar #ceFavoritesBtn.active-parent, 
    .ce-stacked-toolbar #ceSettingsBtn.active-parent, 
    .ce-stacked-toolbar #btnHelp.active-parent { 
        background: var(--hover-bg-light) !important; 
        border-color: rgba(0, 120, 212, 0.3) !important; 
        margin-bottom: 0 !important;
    }

    .ce-stacked-toolbar #btnHelp .myCloudIcon { 
        width: 1.2em !important; 
        height: 1.2em !important; 
        margin: 0 !important; 
    }
	
	
    @keyframes ceRibbonIn { from { opacity: 0; transform: scale(0.8) translateY(-10px); } to { opacity: 1; transform: scale(1) translateY(0); } }
    @keyframes ceRibbonOut { from { opacity: 1; transform: scale(1) translateY(0); } to { opacity: 0; transform: scale(0.8) translateY(-10px); } }
    .ce-floating-menu { position: fixed; z-index: 20000; background: var(--gray-00); border: 1px solid var(--border-medium); box-shadow: 0 8px 24px rgba(0, 0, 0, 0.18); padding: 6px 12px; display: flex; flex-direction: row; gap: 6px; border-radius: 18px 18px 18px 0; transform-origin: top center; animation: ceRibbonIn 0.2s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
    :dir(rtl) .ce-floating-menu { border-radius: 18px 18px 0 18px; }
    .ce-floating-menu.closing { animation: ceRibbonOut 0.2s cubic-bezier(0.16, 1, 0.3, 1) forwards; pointer-events: none; }

    /* --- MS OFFICE STYLE RIBBON SUBMENUS --- */
    .ce-ribbon-popup-container { display: flex; flex-direction: row; padding: 4px; gap: 8px; }
    .ce-ribbon-sub-col { display: flex; flex-direction: column; gap: 4px; min-width: 140px; }
    .ce-ribbon-sub-header { font-size: calc(var(--font-size-base) * 0.75); font-weight: 600; text-transform: uppercase; color: var(--ribbon-text); background: var(--gray-10); border: none; padding: 3px 8px; border-radius: 4px; text-align: left; margin-bottom: 4px; box-shadow: none; }
    :dir(rtl) .ce-ribbon-sub-header { text-align: right; }
    .ce-ribbon-sub-divider { width: 1px; background: var(--border-default); margin: 0 4px; flex-shrink: 0; }
    .ce-ribbon-sub-h-divider { width: 100%; height: 1px; background-color: var(--border-default); margin: 2px 0; align-self: center; }
    .ce-ribbon-sub-row { display: flex; flex-direction: row; gap: 4px; width: 100%; justify-content: flex-start; }
    
    .ce-ribbon-sub-btn { display: flex; align-items: center; justify-content: flex-start; background: transparent !important; border: 1px solid transparent !important; border-radius: 4px !important; cursor: pointer; color: var(--gray-90) !important; min-height: 2.4em !important; height: auto !important; padding: 4px 8px !important; transition: all 0.15s ease; flex: 1; }
    .ce-ribbon-sub-btn:hover:not(:disabled) { background: var(--hover-bg-light) !important; border-color: rgba(0,0,0,0.1) !important; transform: scale(1.02); z-index: 10; box-shadow: 0 2px 6px rgba(0,0,0,0.1); }
    .ce-ribbon-sub-btn.ce-force-active { background: var(--hover-bg-medium) !important; color: var(--accent-primary) !important; }
    .ce-ribbon-sub-btn:disabled { opacity: 0.4 !important; cursor: not-allowed; }
    
    .ce-ribbon-sub-btn .myCloudIcon { width: 1.6em !important; height: 1.6em !important; margin-right: 8px !important; margin-bottom: 0 !important; flex-shrink: 0; background: transparent !important; display: flex; align-items: center; justify-content: center; }
    .ce-ribbon-sub-btn .myCloudIcon svg { width: 1.3em !important; height: 1.3em !important; fill: currentColor !important; }
    
    .ce-ribbon-sub-btn span.ce-btn-text { font-size: 1.1em !important; font-weight: 500; white-space: nowrap; line-height: 1.2; margin-top: 0 !important; }
    
    /* Sizes */
    .ce-btn-type-full { min-width: 130px; }
    .ce-btn-type-half { min-width: 65px; }
    .ce-btn-type-icon { flex: 0 0 32px !important; max-width: 32px !important; justify-content: center !important; padding: 4px !important; }
    .ce-btn-type-icon .myCloudIcon { margin-right: 0 !important; }

    /* Forced Ribbon Hover Icon Fills */
    .ce-ribbon-sub-btn:hover:not(:disabled) .myCloudIcon svg,
    .ce-ribbon-sub-btn:hover:not(:disabled) .myCloudIcon svg path { fill: var(--text-primary) !important; stroke: var(--text-primary) !important; }

    /* --- FLAT BUTTON LEGACY (For Desktop without Ribbons) --- */
    .ce-floating-item { display: flex !important; flex-direction: column !important; align-items: center !important; justify-content: center !important; min-width: 68px !important; height: 62px !important; padding: 4px 8px !important; border: 1px solid var(--border-medium) !important; border-radius: 14px !important; background: transparent !important; color: var(--gray-90) !important; cursor: pointer; transition: all 0.2s ease; }
    .ce-floating-item:hover { background: var(--hover-bg-light) !important; transform: scale(1.05); border-color: rgba(0, 0, 0, 0.25) !important; z-index: 10; }
    .ce-floating-item:hover .myCloudIcon svg path, .ce-floating-item:hover .myCloudIcon svg rect { fill: var(--gray-60) !important; stroke: var(--gray-60) !important; }
    .ce-floating-item .myCloudIcon { width: 28px !important; height: 28px !important; margin-bottom: 4px !important; margin-right: 0 !important; background: transparent !important; }
    .ce-floating-item .myCloudIcon svg { width: 24px !important; height: 24px !important; fill: currentColor !important; }
    .ce-floating-item span:last-child { font-size: 11px !important; line-height: 1.1; text-align: center; color: inherit; font-weight: 500; }
    .ce-floating-item:hover span:last-child { color: var(--text-primary) !important; }
    .ce-floating-item.ce-force-active { background-color: transparent !important; border-color: transparent !important; color: var(--accent-primary) !important; }
    .ce-floating-item:disabled { opacity: 0.3 !important; cursor: not-allowed; background-color: transparent !important; }
    .ce-floating-item:disabled span { color: var(--text-disabled) !important; }
    .ce-floating-item:disabled .myCloudIcon { background-color: transparent !important; border-color: var(--gray-30) !important; }
    .ce-floating-item:disabled .myCloudIcon svg, .ce-floating-item:disabled .myCloudIcon svg path { fill: var(--text-disabled) !important; stroke: var(--text-disabled) !important; filter: grayscale(100%); }


/* --- FIX: Allow Ribbon Dropdown Columns & Buttons to Expand --- */
.ce-ribbon-sub-col {
    min-width: max-content !important;
}

.ce-ribbon-sub-btn {
    overflow: visible !important;
}

.ce-ribbon-sub-btn span.ce-btn-text {
    overflow: visible !important;
    text-overflow: clip !important;
    white-space: nowrap !important;
}

.ce-btn-type-half {
    min-width: max-content !important;
    flex: 1 1 auto !important;
}

   /* --- PROGRESSIVE COLLAPSE TO ICON-ONLY WORKSPACE --- */
   @media (max-width: 1024px) {
       .ce-stacked-toolbar #btnHelp span.ce-ribbon-label { display: none !important; }
       .ce-stacked-toolbar #btnHelp .myCloudIcon { margin-right: 0 !important; }
   }
   @media (max-width: 920px) {
       .ce-stacked-toolbar #ceSettingsBtn span.ce-ribbon-label { display: none !important; }
       .ce-stacked-toolbar #ceSettingsBtn .myCloudIcon { margin-right: 0 !important; }
   }
   @media (max-width: 840px) {
       .ce-stacked-toolbar #ceFavoritesBtn span.ce-ribbon-label { display: none !important; }
       .ce-stacked-toolbar #ceFavoritesBtn .myCloudIcon { margin-right: 0 !important; }
   }

    /* myCloudVer Modal */
	.myCloudVer-modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.4); display: flex; align-items: center; justify-content: center; z-index: 99999; backdrop-filter: blur(4px); }
	.myCloudVer-modal { background: var(--gray-00); color: var(--text-primary); width: 850px; max-width: 95vw; border-radius: 8px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.25); border: 1px solid var(--border-medium); font-family: "Segoe UI Variable Text", "Segoe UI", sans-serif; display: flex; flex-direction: column; animation: myCloudVerFadeIn 0.2s ease-out; }
	@keyframes myCloudVerFadeIn { from { opacity: 0; transform: scale(0.98); } to { opacity: 1; transform: scale(1); } }
	.myCloudVer-modal-header { padding: 16px 20px; font-weight: 600; font-size: 15px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-default); cursor: move; }
	.myCloudVer-modal-header button { background: transparent; border: none; font-size: 20px; cursor: pointer; color: var(--text-secondary); line-height: 1; }
	.myCloudVer-modal-body { padding: 0; max-height: 65vh; overflow-y: auto; background: var(--gray-05); }
	.myCloudVer-pre { background: var(--gray-10); padding: 10px; padding-inline-start: 10px !important; margin: 0; text-align: left; font-family: "Cascadia Code", "Consolas", "Courier New", monospace; font-size: 13px; line-height: 1.5; white-space: pre; overflow-x: auto; color: var(--text-primary); border: 1px solid transparent; direction: ltr; }
	.myCloudVer-modal-footer { padding: 12px 20px; background: var(--gray-10); border-top: 1px solid var(--border-default); border-radius: 0 0 8px 8px; text-align: end; }
	.myCloudVer-btn-primary { background: var(--accent-primary); color: var(--gray-00); border: none; padding: 8px 30px; border-radius: 4px; cursor: pointer; font-size: 14px; transition: background 0.2s; }
 
    .myCloudVer-btn-primary:hover { background: var(--accent-primary-hover); }
    #ceSettingsBtn svg path.ce-grp-icon { fill: var(--ribbon-text) !important; opacity: 1 !important; stroke: none !important; }
    #ceSettingsBtn svg path.ce-grp-arrow { fill: var(--text-primary) !important; opacity: 1 !important; stroke: none !important; }
    #ceSettingsBtn span.ce-ribbon-label { color: var(--ribbon-text) !important; opacity: 1 !important; font-weight: 500 !important; }
    #ceSettingsBtn:hover svg path.ce-grp-icon { fill: var(--ribbon-text-hover) !important; }
    #ceSettingsBtn:hover span.ce-ribbon-label { color: var(--ribbon-text-hover) !important; }
    .ce-settings-reset { background: var(--gray-20)  !important: transparent !important; border: none !important; color: var(--danger) !important; font-size: 13px; font-weight: 500; cursor: pointer; padding: 8px 12px; border-radius: 4px; display: inline-flex; align-items: center; gap: 6px; opacity: 0.9; transition: all 0.2s; text-decoration: none; margin-bottom:12px; }
    .ce-settings-reset:hover { background: rgba(232, 17, 35, 0.05) !important; opacity: 1; text-decoration: underline; }
    .myCloudCloudSwitcher { display: flex; gap: 4px; padding: 8px 16px 0 16px; background: var(--gray-05); border-bottom: 1px solid var(--border-default); overflow: hidden; flex-shrink: 0; user-select: none; -webkit-user-select: none; align-items: flex-end; }
    .myCloudCloudSwitcher::-webkit-scrollbar { display: none; }
    
    .cloud-indicator-start, .cloud-indicator-end {
        position: absolute;
        top: 0; bottom: 0; width: 24px;
        pointer-events: none; z-index: 5;
        opacity: 0; transition: opacity 0.2s ease;
    }
    .cloud-indicator-start { inset-inline-start: 0; background: linear-gradient(to right, var(--gray-05) 10%, transparent 100%); }
    :dir(rtl) .cloud-indicator-start { background: linear-gradient(to left, var(--gray-05) 10%, transparent 100%); }
    .cloud-indicator-end { inset-inline-end: 0; background: linear-gradient(to left, var(--gray-05) 10%, transparent 100%); }
    :dir(rtl) .cloud-indicator-end { background: linear-gradient(to right, var(--gray-05) 10%, transparent 100%); }
	

    .ce-cloud-btn { padding: 6px 24px; min-width: 120px; text-align: center; border: 1px solid transparent; background: transparent; color: var(--gray-80); border-radius: 6px 6px 0 0; margin-bottom: -1px; font-size: 13px; font-weight: 500; cursor: pointer; transition: background 0.1s ease, color 0.1s ease; white-space: nowrap; text-transform: capitalize; position: relative; z-index: 1; }
    .ce-cloud-btn:hover { background: var(--hover-bg-very-light); color: var(--text-primary); }
    .ce-cloud-btn.active { background: var(--gray-00); color: var(--accent-primary); font-weight: 600; border-color: var(--border-default); border-bottom-color: var(--gray-00); box-shadow: inset 0 2px 0 var(--accent-primary); z-index: 2; }
    .ce-cloud-btn.ce-admin-tab { background: linear-gradient(to bottom, rgba(232, 17, 35, 0.15) 0%, rgba(232, 17, 35, 0.02) 100%); color: #a51a24; }
    .ce-cloud-btn.ce-admin-tab:hover { background: rgba(232, 17, 35, 0.09); }
    .ce-cloud-btn.ce-admin-tab.active { background: var(--gray-00); color: var(--danger); box-shadow: inset 0 2px 0 var(--danger); }
    .ce-ribbon-handle { position: absolute; top: calc(100% - 1px); left: 0px; display: flex; align-items: center; padding: 2px 12px; border-radius: 0 0 10px 10px; background: linear-gradient(to bottom, var(--gray-00), var(--gray-15)); border: 1px solid var(--border-medium); border-top: none; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--ribbon-text); box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05); z-index: 20001; pointer-events: none; white-space: nowrap; }
    .ce-ribbon-handle::after { content: ''; position: absolute; top: 0; right: -10px; width: 10px; height: 10px; border-top-left-radius: 10px; background: transparent; box-shadow: -4px -4px 0 var(--gray-00); pointer-events: none; }
    :dir(rtl) .ce-ribbon-handle::after { right: auto; left: -10px; border-top-left-radius: 0; border-top-right-radius: 10px; box-shadow: 4px -4px 0 var(--gray-00); }

    .myCloud-switcher-logo { display: flex; align-items: center; justify-content: center; height: 28px;  margin-bottom: 1px; margin-top: 1px; flex-shrink: 0; align-self: flex-end; color: var(--text-primary); }
    .myCloud-switcher-logo svg { height: 100% !important; max-height: 100% !important; width: auto; }
	.ce-dark-mode svg g[filter="brightness(0.4)"] { filter: brightness(1) !important; }

    /* =========================================
       RESTORED UTILITY CLASSES (FROM OLD FILE)
       ========================================= */
   
    /* Warning Box / Alert Styles */
    .ce-warning-box { text-align: center; padding: 10px; }
    .ce-warning-title { margin-bottom: 15px; font-weight: bold; color: var(--danger); }
    .ce-btn-group { display: flex; justify-content: center; gap: 10px; margin-top: 20px; }
    .ce-btn-action { padding: 8px 20px; cursor: pointer; border: none; border-radius: 4px; color: var(--gray-00); font-weight: 500; }
    .ce-btn-confirm { background: var(--success); }
    .ce-btn-danger { background: var(--danger); }
   
    /* Properties Stats & Treemap */
    .myCloud-prop-stats { padding: 15px; border-bottom: 1px solid var(--gray-30); background: var(--gray-10); }
    .myCloud-prop-row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px; }
    .myCloud-prop-label { color: var(--text-secondary); }
    .myCloud-prop-val { font-weight: 600; color: var(--text-primary); }
   
    .myCloud-treemap-container { padding: 10px; height: 300px; display: flex; flex-direction: column; }
    .myCloud-treemap-canvas { flex: 1; position: relative; background: var(--gray-20); overflow: hidden; border: 1px solid var(--border-default); }
    .myCloud-tm-node { position: absolute; box-sizing: border-box; border: 1px solid rgba(255,255,255,0.5); color: var(--gray-00); font-size: 11px; overflow: hidden; cursor: pointer; display: flex; align-items: center; justify-content: center; text-align: center; text-shadow: 0 1px 2px rgba(0,0,0,0.4); transition: filter 0.2s; }
    .myCloud-tm-node:hover { filter: brightness(1.1); z-index: 10; border: 1px solid var(--gray-00); }
    .myCloud-tm-nav { padding: 5px 0; display: flex; gap: 10px; }
    .myCloud-tm-btn { padding: 4px 8px; background: var(--gray-20); border: none; cursor: pointer; font-size: 12px; border-radius: 3px; }
    .myCloud-tm-btn:hover { background: var(--gray-30); }
    /* =========================================
       11. MOBILE PORTRAIT OPTIMIZATION (RESTORED FULLY)
       ========================================= */
    .ce-mobile-os .myCloud-dropzone { display: none !important; pointer-events: none !important; }
    .ce-mobile-os .ce-row-actions { display: none !important; }
    @media (max-width: 768px) {
        .myCloud-dropzone { display: none !important; pointer-events: none; }
        .ce-row-actions { display: none !important; }
        .myCloudDetails { overflow-x: auto !important; -webkit-overflow-scrolling: touch; }
		.myCloudTable { min-width: 100% !important; width: max-content !important; table-layout: auto !important; }
        .myCloudTable th:nth-child(1), .myCloudTable td:nth-child(1),
        .myCloudTable th:nth-child(2), .myCloudTable td:nth-child(2) { width: 40px !important; }
        .myCloudTable th:nth-child(4), .myCloudTable td:nth-child(4),
        .myCloudTable th:nth-child(5), .myCloudTable td:nth-child(5) { width: 8.5em !important; display: table-cell !important; }
    }
    @media (max-width: 768px) and (orientation: portrait) {
        .myCloudBody { display: flex !important; flex-direction: column !important; }
        .myCloudTree { width: 100% !important; height: 35vh; min-height: 50px; border-inline-end: none !important; border-bottom: none; flex: none !important; order: -1; }
       
        .myCloudResizer { display: flex !important; align-items: center; justify-content: center; width: 100% !important; height: 12px !important; background: var(--gray-10); border-inline-start: none !important; border-top: 1px solid var(--gray-35); border-bottom: 1px solid var(--gray-35); cursor: row-resize !important; z-index: 50; flex: none !important; position: relative; }
        .myCloudResizer::after { content: ''; display: block; background-color: transparent; border-radius: 2px; left: 0; right: 0; top: -20px; bottom: -20px; }
       
        .myCloudDetails { width: 100% !important; height: auto !important; flex: 1 !important; min-height: 0; }
        .myCloudToolbar { overflow-x: auto; padding-bottom: 8px; }
        .myCloudToolbar button { min-width: 50px; }
        .myCloudTable th:nth-child(4), .myCloudTable td:nth-child(4),
        .myCloudTable th:nth-child(5), .myCloudTable td:nth-child(5) { display: table-cell !important; }
    }
/* Main Form Container */
    .ce-settings-form {
        padding: 12px 0 5px 0;
        display: flex;
        flex-direction: column;
        gap: 0;
    }
    /* Individual Setting Rows */
    .ce-setting-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 5px 24px;
        border-bottom: none;
        background: transparent;
    }
    /* Slider Area Box */
    .ce-setting-block {
        background: var(--gray-05);
        padding: 12px 24px;
        border: 1px solid var(--border-default);
        margin: 8px 20px 10px 20px;
        border-radius: 4px;
    }


	
	/* FAVORITES UI */
	.ce-fav-btn { width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; background: transparent; border: 1px solid transparent; border-radius: 4px; cursor: pointer; color: var(--text-secondary); transition: all 0.2s; flex-shrink: 0; }
	.ce-fav-btn:hover { background-color: var(--gray-20); color: var(--text-primary); border-color: var(--border-medium); }
	.ce-fav-btn svg { width: 16px; height: 16px; fill: currentColor; }
	.ce-fav-btn.delete:hover { background-color: var(--danger); color: var(--gray-00); border-color: var(--danger); }
	.ce-fav-btn.edit:hover { background-color: var(--accent-primary); color: var(--gray-00); border-color: var(--accent-primary); }
	.ce-fav-row { display: flex; align-items: center; padding: 6px 8px; border-bottom: 1px solid var(--border-subtle); transition: background 0.1s; }
	.ce-fav-row:hover { background-color: var(--gray-05); }
	.ce-fav-icon { width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; margin-inline-end: 10px; flex-shrink: 0; }
	.ce-fav-icon svg { width: 20px; height: 20px; }
	.ce-fav-add-btn { width: 100%; padding: 8px; background: transparent; border: 1px dashed var(--border-medium); border-radius: 4px; color: var(--accent-primary); cursor: pointer; font-size: 13px; font-weight: 500; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 6px; }
	.ce-fav-add-btn:hover:not(:disabled) { background: var(--hover-bg-light); border-color: var(--accent-primary); }
	.ce-fav-add-btn:disabled { border-color: var(--border-subtle); color: var(--text-disabled); cursor: default; }





/* =========================================
   14. VERSION BADGE
   ========================================= */
.myCloudVersionBadge {
    position: absolute;
    bottom: 5px;
    right: 5px;
    font-size: 11px;
    color: var(--text-subtle);
    background: rgba(255, 255, 255, 0.4);
    padding: 2px 8px;
    border-radius: 10px;
    z-index: 1002; /* Above main UI (1001), but below Modals (2000+) */
    transition: opacity 0.3s ease;
    pointer-events: auto;
    cursor: pointer;
    backdrop-filter: blur(2px);
}

/* Adjust for Dark Mode visibility */
.ce-dark-mode .myCloudVersionBadge {
    background: rgba(0, 0, 0, 0.3);
    color: rgba(255, 255, 255, 0.5);
}

/* =========================================
   BETA BADGE
   ========================================= */
.myCloudBetaBadge {
    position: absolute;
    bottom: 15px;
    left: 15px;
    font-size: 18px;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.75);
    background: rgba(255, 99, 1, 0.3); 
    padding: 4px 12px;
    border-radius: 19px;
    z-index: 999999; 
    pointer-events: none;
    backdrop-filter: blur(2px);
    text-transform: uppercase;
    letter-spacing: 1px;
    box-shadow: 0 1px 15px rgba(0,0,0,0.2);
}
.ce-dark-mode .myCloudBetaBadge {
    background: rgba(216, 59, 1, 0.6);
}

/* =========================================
   COMMANDER VIEW MODE
   ========================================= */
.myCloudBody.commander-mode {
    display: flex;
    flex-direction: row !important;
}

.myCloudBody.commander-mode .myCloudTree {
    display: none !important;
}

.myCloud-commander-pane {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border: 2px solid transparent;
    transition: border-color 0.2s;
}

.myCloud-commander-pane.active {
    border-color: var(--accent-primary);
 }


.myCloud-commander-header {
    background: var(--gray-10);
    border-bottom: 1px solid var(--border-default);
    padding: 6px 12px;
    font-size: 12px;
    font-weight: 600;
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    gap: 8px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.myCloud-commander-header .myCloudIcon {
    width: 16px;
    height: 16px;
    flex-shrink: 0;
}

.myCloud-commander-content {
    flex: 1;
    overflow: auto;
    display: flex;
    flex-direction: column;
}

.myCloudBody.commander-mode .myCloudResizer {
    width: 6px;
    cursor: col-resize;
    background: var(--border-default);
    flex-shrink: 0;
}

.myCloudBody.commander-mode .myCloudResizer:hover {
    background: var(--accent-primary);
    opacity: 0.8;
}

.myCloudBody.commander-mode .myCloudDetails {
    display: none !important;
}


.myCloud-commander-header {
    background: var(--gray-10);
    border-bottom: 1px solid var(--border-default);
    padding: 6px 12px;
    font-size: 13px;
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    flex-wrap: nowrap;
    overflow: hidden;
    white-space: nowrap;
    height: 34px;
    flex-shrink: 0;
}



.ce-crumb-segment {
    cursor: pointer;
    padding: 2px 4px;
    border-radius: 4px;
    transition: background 0.1s;
    display: inline-flex;
    align-items: center;
    color: var(--text-primary);
}

.ce-crumb-segment:hover {
    background: var(--hover-bg-medium);
    color: var(--accent-primary);
}

.ce-crumb-segment.active {
    font-weight: 600;
    cursor: default;
    background: transparent;
    color: var(--text-primary);
}

.ce-crumb-segment svg {
    width: 16px;
    height: 16px;
}

.ce-crumb-sep {
    color: var(--gray-60);
    margin: 0 2px;
    font-size: 14px;
    user-select: none;
}

/* --- SELECTION STYLE FIX --- */
/* Force Commander list rows to look exactly like normal list rows */

.myCloud-commander-content .myCloudRow.selected {
    background-color: var(--selection-bg-strong) !important;
    color: var(--gray-00) !important;
}

/* Ensure icons and text inside selected row turn white (like normal list) */
.myCloud-commander-content .myCloudRow.selected .ce-name-text,
.myCloud-commander-content .myCloudRow.selected .myCloudIcon,
.myCloud-commander-content .myCloudRow.selected .myCloudIcon svg,
.myCloud-commander-content .myCloudRow.selected td {
    color: var(--gray-00) !important;
    fill: var(--gray-00) !important;
}

/* Fix checkbox color in active selection */
.myCloud-commander-content .myCloudRow.selected .myCloudCheckbox {
    accent-color: var(--gray-00);
}

/* Inactive Pane Selection (Dimmed) - Optional Polish */
.myCloud-commander-pane:not(.active) .myCloudRow.selected {
    background-color: var(--gray-40) !important;
    color: var(--gray-90) !important;
}
.myCloud-commander-pane:not(.active) .myCloudRow.selected .ce-name-text,
.myCloud-commander-pane:not(.active) .myCloudRow.selected .myCloudIcon svg {
    color: var(--gray-90) !important;
    fill: var(--gray-90) !important;
}

/* --- COMMANDER MIDDLE TOOLBAR (Fix) --- */
.myCloud-commander-resizer-container {
    display: flex !important;
    flex-direction: column !important;
    align-items: center !important;
    justify-content: center !important;
    
    /* Strict Dimensions */
    width: 40px !important;
    min-width: 40px !important;
    flex: 0 0 40px !important; /* Do not grow, do not shrink */
    height: auto !important; /* Stretch to fill parent */
    
    /* Visuals */
    background-color: var(--gray-15) !important;
    border-left: 1px solid var(--border-default) !important;
    border-right: 1px solid var(--border-default) !important;
    
    /* Layering */
    position: relative !important;
    z-index: 100 !important;
}

/* The actual drag handle overlay */
.myCloud-commander-resizer-handle {
    position: absolute !important;
    top: 0; bottom: 0; left: 0; right: 0;
    width: 100% !important;
    cursor: col-resize !important;
    z-index: 10 !important;
    background: transparent !important;
}

.myCloud-commander-resizer-handle:hover {
    background: rgba(0, 120, 212, 0.1) !important; /* Subtle highlight */
}

 
/* Reset sticky offset for Commander Mode since header is outside the scroll area */
.myCloud-commander-container .myCloudTable th {
    top: 0 !important;
}

/* Buttons inside the bar */
.ce-cmd-btn {
    width: 28px !important;
    height: 28px !important;
    margin: 4px 0 !important;
    background: transparent !important;
    border: 1px solid transparent !important;
    border-radius: 4px !important;
    cursor: pointer !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    color: var(--text-secondary) !important;
    position: relative !important;
    z-index: 20 !important; /* Above drag handle */
    padding: 0 !important;
}

.ce-cmd-btn:hover:not(:disabled) {
    background-color: var(--hover-bg-medium) !important;
    color: var(--accent-primary) !important;
    border-color: var(--border-medium) !important;
}

.ce-cmd-btn:disabled {
    opacity: 0.3 !important;
    cursor: default !important;
}

.ce-cmd-btn svg {
    width: 18px !important;
    height: 18px !important;
    fill: currentColor !important;
}

.ce-cmd-spacer {
    height: 20px;
    width: 100%;
}

/* =========================================
   COMMAND PALETTE & TERMINAL
   ========================================= */
.myCloud-palette-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 30000; background: rgba(0,0,0,0.3); display: flex; justify-content: center; align-items: flex-start; padding-top: 10vh; backdrop-filter: blur(2px); }
.myCloud-palette { width: 600px; max-width: 90%; background: var(--gray-00); border-radius: 8px; box-shadow: 0 16px 40px rgba(0,0,0,0.3); overflow: hidden; display: flex; flex-direction: column; font-family: var(--font-family); color: var(--text-primary); border: 1px solid var(--border-medium); }
.myCloud-palette-input-wrap { padding: 12px; border-bottom: 1px solid var(--border-default); display: flex; align-items: center; gap: 10px; }
.myCloud-palette-input { flex: 1; border: none; outline: none; font-size: 16px; background: transparent; color: var(--text-primary); }
.myCloud-palette-list { max-height: 40vh; overflow-y: auto; list-style: none; margin: 0; padding: 0; }
.myCloud-palette-item { padding: 10px 16px; display: flex; align-items: center; gap: 12px; cursor: pointer; border-left: 3px solid transparent; font-size: 14px; }
.myCloud-palette-item.selected { background: var(--hover-bg-medium); border-left-color: var(--accent-primary); }
.myCloud-palette-item .myCloudIcon { width: 18px; height: 18px; opacity: 0.7; display: flex; align-items: center; justify-content: center; }
.myCloud-palette-item .myCloudIcon svg { width: 100%; height: 100%; }
.myCloud-palette-kbd { margin-left: auto; font-size: 11px; background: var(--gray-15); border: 1px solid var(--border-default); padding: 2px 6px; border-radius: 4px; color: var(--text-secondary); }

.myCloud-terminal-wrap { position: fixed; bottom: 20px; right: 20px; width: 700px; max-width: 90vw; height: 450px; background: #000; border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); display: flex; flex-direction: column; z-index: 25000; border: 1px solid #444; transition: transform 0.2s, opacity 0.2s; overflow: hidden; }
.myCloud-terminal-wrap.minimized { transform: scale(0.1) translate(400%, 400%); opacity: 0; pointer-events: none; }
.myCloud-terminal-header { background: #222; padding: 8px 12px; display: flex; justify-content: space-between; align-items: center; color: #ddd; font-size: 13px; font-family: var(--font-family); user-select: none; border-bottom: 1px solid #111; cursor: move; }
.myCloud-terminal-body { flex: 1; padding: 5px; overflow: hidden; }
.myCloud-terminal-minimized-icon { position: fixed; bottom: 20px; right: 20px; width: 50px; height: 50px; background: #333; border: 2px solid #555; border-radius: 50%; display: none; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 4px 15px rgba(0,0,0,0.5); z-index: 25001; transition: all 0.2s; color: #fff; }
.myCloud-terminal-minimized-icon:hover { transform: scale(1.1); border-color: #aaa; }




/* 1. Target the specific buttons to reduce the button-level hover scale */
#ceFavoritesBtn:hover,
#ceSettingsBtn:hover,
#btnHelp:hover {
    transform: scale(1.04) !important; 
	/* Overrides the global scale(1.05) */
}

/* 2. Target the icons inside these specific buttons to reduce icon-level hover scale */
#ceFavoritesBtn:hover .myCloudIcon,
#ceSettingsBtn:hover .myCloudIcon,
#btnHelp:hover .myCloudIcon {
    transform: scale(1.05) !important; 
	background-color: transparent !important;
    box-shadow: none !important;
}


/* 3. Force Help icon to match the Olive/Gold theme of the Ribbon */
#btnHelp .myCloudIcon svg path {
    fill: var(--ribbon-text) !important;
    transition: fill 0.2s ease;
}

#btnHelp:hover .myCloudIcon svg path {
    fill: var(--ribbon-text-hover) !important;
}

#btnHelp .myCloudIcon {
    width: 42px !important;
    height: 42px !important;
    display: flex !important;
	margin-bottom: 2px !important;
	margin-top: 2px !important;
}

/* FORCE the SVG size - this is usually the bottleneck */
#btnHelp .myCloudIcon svg {
    width: 26px !important;
    height: 26px !important;
    min-width: 26px !important;
    min-height: 26px !important;
}

/* Ensure no scaling or blue background (matching Favorites) */
#btnHelp:hover:not(:disabled) {
    transform: scale(1.05) !important;
    background: var(--hover-bg-very-light) !important;
}

#btnHelp:hover .myCloudIcon {
    transform: scale(1) !important;
    background: transparent !important;
    box-shadow: none !important;
}

    .ce-tag-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 0 !important;
        margin-left: -6px;
        flex-shrink: 0;
        box-shadow: 0 1px 2px rgba(0,0,0,0.2) inset;
    }


    /* --- ICON BADGING & OVERLAPPING TAGS --- */
    .ce-icon-badge {
        position: absolute;
        bottom: -2px;
        right: -2px;
        width: 14px;
        height: 14px;
        background: var(--gray-00);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 9px;
        line-height: 1;
		box-shadow: 0 1px 3px rgba(0,0,0,0.3);
        z-index: 10;
        pointer-events: none;
    }
    .ce-dark-mode .ce-icon-badge { background: var(--gray-10); border: 1px solid var(--border-default); }

    .ce-sym-icon-badge {
        position: absolute;
        bottom: 0px;
        right: 0px;
        width: 18px;
        height: 18px;
        background: var(--gray-00);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.4);
        z-index: 10;
        pointer-events: none;
    }
    .ce-dark-mode .ce-sym-icon-badge { background: var(--gray-10); border: 1px solid var(--border-default); }

    .ce-tag-stack {
        display: inline-flex;
        align-items: center;
        margin-left: 4px;
        vertical-align: middle;
        flex-shrink: 0;
    }
    .ce-tag-stack .ce-tag-dot {
        width: 14px !important;
        height: 14px !important;
        border-radius: 50% !important;
        border: 2px solid var(--gray-00) !important;
        margin-left: -5px !important;
        position: relative !important;
        display: block !important;
        box-sizing: border-box !important;
        box-shadow: 0 1px 3px rgba(0,0,0,0.2) !important;
        transition: margin 0.2s ease, transform 0.2s ease !important;
    }
    .ce-tag-stack .ce-tag-dot:first-child { margin-left: 0 !important; }
    .ce-dark-mode .ce-tag-stack .ce-tag-dot { border-color: var(--gray-10) !important; }
    .myCloudRow:hover .ce-tag-stack .ce-tag-dot,
    .myCloud-symbol-item:hover .ce-tag-stack .ce-tag-dot,
    .myCloudTreeList li > div:hover .ce-tag-stack .ce-tag-dot {
        margin-left: 2px !important;
    }


/* Backgrounds & Text */
#myCloudContainer.ce-dark-mode {
    background-color: var(--gray-00) !important;
    color: var(--text-primary) !important;
}
.myCloudModal.ce-dark-mode,
.ce-dark-mode .myCloudLoadingPopup {
    background-color: rgba(37, 37, 38, 0.85) !important;
    color: var(--text-primary) !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4) !important;
}

#myCloudModalOverlay.ce-dark-mode,
#myCloudPreviewOverlay.ce-dark-mode {
background-color: rgba(0, 0, 0, 0.5);
}
.ce-dark-mode .myCloud-gallery,
.ce-dark-mode .myCloudTableContainer,
.ce-dark-mode .myCloud-address-bar input,
.ce-dark-mode .myCloudToolbar,
.ce-dark-mode .myCloudDialog {
background-color: transparent !important;
color: var(--text-primary) !important;
border-color: var(--border-default) !important;
}
/* Tree View */
.ce-dark-mode .myCloudTreeBox,
.ce-dark-mode .myCloudTree {
background-color: var(--gray-05) !important;
border-right: 1px solid var(--border-default);
}
.ce-dark-mode .selectedFolder > div {
background-color: var(--selection-bg) !important;
color: var(--text-primary) !important;
border-left: 3px solid var(--accent-primary);
}
/* Table View */
.ce-dark-mode .myCloudTable thead th {
background-color: var(--gray-10) !important;
background-clip: padding-box !important;
color: var(--text-secondary) !important;
border-bottom: 1px solid var(--border-default);
}

.ce-dark-mode .myCloudRow { border-bottom: 1px solid transparent; }
.ce-dark-mode .myCloudRow:hover { background-color: var(--hover-bg-medium) !important; }
/* Force Dropzone background to strictly match Details view */
.ce-dark-mode .myCloud-dropzone {
    background-color: transparent !important;
	border-color: transparent !important;
}
.ce-dark-mode .myCloud-dropzone:hover {
    border-color: var(--border-medium) !important;
}
.ce-dark-mode .myCloud-dropzone.drag-active {
    background-color: var(--hover-bg-light) !important;
    border-color: var(--accent-primary) !important;
    color: var(--accent-primary) !important;
}

.ce-dark-mode .myCloudRow.selected { background-color: #4a4a4a !important; border: 1px solid transparent !important; color: #ffffff !important; }
.ce-dark-mode .myCloudRow.selected .ce-name-text, .ce-dark-mode .myCloudRow.selected .myCloudIcon { color: #ffffff !important; }
.ce-dark-mode .myCloudDetails:focus-within .myCloudRow.selected, .ce-dark-mode .myCloud-commander-pane.active .myCloudRow.selected td { background-color: var(--selection-bg-strong) !important; color: #ffffff !important; }
.ce-dark-mode .myCloudDetails:focus-within .myCloudRow.selected .ce-name-text, .ce-dark-mode .myCloud-commander-pane.active .myCloudRow.selected .ce-name-text, .ce-dark-mode .myCloudDetails:focus-within .myCloudRow.selected .myCloudIcon svg *, .ce-dark-mode .myCloud-commander-pane.active .myCloudRow.selected .myCloudIcon svg * { color: #ffffff !important; fill: #ffffff !important; opacity: 1 !important; }

/* Tag Dropdown Dark Mode Overrides */
.ce-dark-mode#myCloudTagDropdown { background-color: var(--gray-10) !important; border-color: var(--border-default) !important; box-shadow: 0 8px 24px rgba(0,0,0,0.6) !important; }
.ce-dark-mode#myCloudTagDropdown .myCloud-tag-dropdown-item { color: var(--text-primary) !important; }
.ce-dark-mode#myCloudTagDropdown .myCloud-tag-dropdown-item:hover { background-color: var(--hover-bg-light) !important; }
.ce-dark-mode .myCloud-tag-dropdown-btn { background-color: transparent !important; color: var(--text-primary) !important; border-color: var(--border-default) !important; }
.ce-dark-mode .myCloud-tag-dropdown-btn:hover { background-color: var(--gray-20) !important; }


/* Inputs & Buttons */
.ce-dark-mode input, .ce-dark-mode select, .ce-dark-mode textarea, .ce-dark-mode button:not([style*="background"]):not(.ce-btn-confirm):not(.ce-btn-danger):not(.ce-tab-btn) {
	background-color: var(--gray-10) !important;
color: var(--text-primary) !important;
border: 1px solid var(--border-default) !important;
}
.ce-dark-mode button:not([style*="background"]):not(.ce-btn-confirm):not(.ce-btn-danger):not(.ce-tab-btn):hover:not(:disabled) { background-color: var(--gray-20) !important; }
/* Scrollbars */
.ce-dark-mode ::-webkit-scrollbar-track { background: var(--gray-00); }
.ce-dark-mode ::-webkit-scrollbar-thumb { background: var(--gray-20); border-radius: 99px; border: 3px solid var(--gray-00); }
.ce-dark-mode ::-webkit-scrollbar-thumb:hover { background: var(--gray-30); }
/* SVG Colors */
.ce-dark-mode .myCloudIcon svg path[fill^="#"], .ce-dark-mode .myCloud-gallery-thumb svg path[fill^="#"] { stroke: none !important; }
.ce-dark-mode .ce-btn:hover svg { stroke: var(--gray-100) !important; filter: drop-shadow(0 0 2px rgba(96, 205, 255, 0.5)); }
/* Fix Icons */
.ce-dark-mode .ce-floating-item .myCloudIcon { color: var(--text-primary) !important; }

/* Overcome inline dark fills on SVGs and empty paths defaulting to black */
.ce-dark-mode .myCloudIcon svg[style*="fill:#444"],
.ce-dark-mode .myCloudIcon svg[style*="fill: #444"] {
    fill: var(--text-primary) !important;
}
.ce-dark-mode .myCloudIcon svg:not([fill]):not([style*="fill"]) {
    fill: var(--text-primary);
}
.ce-dark-mode .myCloudIcon svg[fill="#757575"] {
    fill: var(--text-secondary) !important;
}


.ce-dark-mode .ce-floating-item:not(:disabled):hover .myCloudIcon svg,
.ce-dark-mode .ce-floating-item:not(:disabled):hover .myCloudIcon svg path {
    fill: var(--text-primary) !important;
}

/* Apply light yellow (--ribbon-text) to ALL toolbar elements (Stacked & Unstacked) */
.ce-dark-mode .myCloudToolbar .myCloudIcon,
.ce-dark-mode .myCloudToolbar button span:last-child { color: var(--ribbon-text) !important; }

.ce-dark-mode .myCloudToolbar .myCloudIcon svg[style*="fill:#444"],
.ce-dark-mode .myCloudToolbar .myCloudIcon svg[style*="fill: #444"],
.ce-dark-mode .myCloudToolbar .myCloudIcon svg:not([fill]):not([style*="fill"]) {
    fill: var(--ribbon-text) !important;
}

/* Hover states for Toolbar (Unstacked) -> White */
.ce-dark-mode .myCloudToolbar button:not(:disabled):hover .myCloudIcon svg,
.ce-dark-mode .myCloudToolbar button:not(:disabled):hover .myCloudIcon svg path {
    fill: var(--ribbon-text-hover) !important;
}
.ce-dark-mode .myCloudToolbar button:not(:disabled):hover span:last-child {
    color: var(--ribbon-text-hover) !important;
}

/* Force Ribbon (Stacked) and Floating Menus to use Yellow/White logic */
.ce-dark-mode .ce-ribbon-btn svg,
.ce-dark-mode .ce-ribbon-btn svg path,
.ce-dark-mode .ce-floating-item svg,
.ce-dark-mode .ce-floating-item svg path,
.ce-dark-mode .ce-email-btn svg,
.ce-dark-mode .ce-email-btn svg path {
    fill: var(--ribbon-text) !important;
}
.ce-dark-mode .ce-stacked-toolbar #ceSettingsBtn svg, .ce-dark-mode .ce-stacked-toolbar #ceSettingsBtn svg *,
.ce-dark-mode .ce-stacked-toolbar #btnHelp svg, .ce-dark-mode .ce-stacked-toolbar #btnHelp svg * {
    stroke: var(--ribbon-text) !important;
    fill: none !important;
}
.ce-dark-mode .ce-ribbon-btn svg[fill="none"] path,
.ce-dark-mode .ce-floating-item svg[fill="none"] path {
    fill: none !important;
    stroke: var(--ribbon-text) !important;
}
.ce-dark-mode .ce-ribbon-btn span.ce-ribbon-label,
.ce-dark-mode .ce-floating-item span:last-child,
.ce-dark-mode .ce-email-btn span:last-child {
    color: var(--ribbon-text) !important;
}

/* Hover states for Ribbons and Floating Menus */
.ce-dark-mode .ce-ribbon-btn:not(:disabled):hover svg,
.ce-dark-mode .ce-ribbon-btn:not(:disabled):hover svg path,
.ce-dark-mode .ce-floating-item:not(:disabled):hover svg,
.ce-dark-mode .ce-floating-item:not(:disabled):hover svg path {
    fill: var(--ribbon-text-hover) !important;
}
.ce-dark-mode .ce-stacked-toolbar #ceSettingsBtn:not(:disabled):hover svg, .ce-dark-mode .ce-stacked-toolbar #ceSettingsBtn:not(:disabled):hover svg *,
.ce-dark-mode .ce-stacked-toolbar #btnHelp:not(:disabled):hover svg, .ce-dark-mode .ce-stacked-toolbar #btnHelp:not(:disabled):hover svg * {
    stroke: var(--ribbon-text-hover) !important;
    fill: none !important;
}

.ce-dark-mode .ce-ribbon-btn:not(:disabled):hover span.ce-ribbon-label,
.ce-dark-mode .ce-floating-item:not(:disabled):hover span:last-child {
    color: var(--ribbon-text-hover) !important;
}

.ce-dark-mode .myCloudIcon svg path[fill="#757575"],
.ce-dark-mode .myCloudIcon svg path[fill="#555"],
.ce-dark-mode .myCloudIcon svg path[fill="#000"],
.ce-dark-mode .myCloudIcon svg path[fill="#444"] { fill: var(--text-primary) !important; }

.ce-dark-mode .myCloudIcon svg path[stroke="#757575"],
.ce-dark-mode .myCloudIcon svg path[stroke="#555"],
.ce-dark-mode .myCloudIcon svg path[stroke="#000"],
.ce-dark-mode .myCloudIcon svg path[stroke="#444"] { stroke: var(--text-primary) !important; }
.ce-dark-mode .myCloudIcon svg path[fill="#E53935"] { fill: #ff5252 !important; }
.ce-dark-mode .myCloudIcon svg[fill="#9E9E9E"] path,
.ce-dark-mode .myCloudIcon svg[fill="#6c757d"] path { fill: var(--text-secondary, #cccccc) !important; }
.ce-dark-mode .myCloudIcon svg path[fill="white"], .ce-dark-mode .myCloudIcon svg path[fill="#ffffff"] { fill: #ffffff !important; opacity: 1 !important; }
.ce-dark-mode .myCloudToolbar button .myCloudIcon { background-color: rgba(255, 255, 255, 0.1) !important; color: var(--text-primary) !important; }
/* Restored Dark Mode Overrides for Components */
.ce-dark-mode .myCloud-prop-stats { background-color: var(--gray-15) !important; border-bottom: 1px solid var(--gray-50) !important; }
.ce-dark-mode .myCloud-prop-label { color: var(--text-secondary) !important; }
.ce-dark-mode .myCloud-prop-val { color: var(--text-primary) !important; }
.ce-dark-mode .myCloud-treemap-canvas { background-color: var(--gray-20) !important; border-color: var(--gray-50) !important; }
.ce-dark-mode .myCloudModal.prop-modal { background: var(--gray-10) !important; border: 1px solid var(--border-medium) !important; }
.ce-dark-mode .myCloudModal.prop-modal .myCloudModalHeader { background-color: transparent !important; }
.ce-dark-mode .myCloudModalHeader { background-color: var(--gray-05) !important; border-bottom: 1px solid var(--gray-50) !important; color: var(--text-primary) !important; }
ce-dark-mode .myCloudButtons { background-color: transparent !important; }
.ce-dark-mode .myCloudModalBody { color: var(--text-primary) !important; background-color: transparent !important; }
.ce-dark-mode .myCloudModalBody div[style="color:#333"], .ce-dark-mode .myCloudModalBody div[style*="color: #333"] { color: var(--text-primary) !important; }
.ce-dark-mode .myCloudModalBody svg[stroke="#e81123"] { stroke: #ff6b6b; }
.ce-dark-mode .myCloudModalBody svg[stroke="#f0ad4e"] { stroke: #f0ad4e; }
.ce-dark-mode .myCloudModalBody svg[stroke="#0078d4"] { stroke: #60cdff; }
/* Settings in Dark Mode */
.ce-dark-mode .ce-settings-wrapper, .ce-settings-dropdown.ce-dark-mode .ce-settings-wrapper { background-color: #252526 !important; border: 1px solid #555555 !important; box-shadow: 0 8px 30px rgba(0,0,0,0.8) !important; }
.ce-dark-mode .ce-settings-content, .ce-settings-dropdown.ce-dark-mode .ce-settings-content { background-color: #252526 !important; color: var(--text-primary) !important; }
.ce-dark-mode .ce-settings-body { background-color: var(--gray-05) !important; }
.ce-dark-mode .ce-settings-tabs { background-color: var(--gray-10) !important; border-bottom: 1px solid var(--gray-50) !important;}
.ce-dark-mode .ce-tab-btn { border: none !important; border-bottom: 3px solid transparent !important; background-color: transparent !important; color: var(--text-secondary) !important; }
.ce-dark-mode .ce-tab-btn:hover { background-color: var(--gray-20) !important; color: var(--text-primary) !important; }
.ce-dark-mode .ce-tab-btn.active { background-color: var(--gray-20) !important; color: var(--accent-primary) !important; border-bottom: 3px solid var(--accent-primary) !important; }
.ce-dark-mode .ce-setting-block { background-color: var(--gray-10) !important; border: 1px solid var(--gray-50) !important; }
.ce-dark-mode .ce-setting-block span { color: var(--text-primary) !important; }
.ce-dark-mode .ce-settings-footer { background-color: var(--gray-10) !important; border-top: 1px solid var(--gray-50) !important; }
.ce-dark-mode #ceInstallAppBtn, .ce-dark-mode #ceRestartFraBtn { border: 1px solid var(--border-medium) !important; }
.ce-dark-mode #ceResetBtn { color: #ff6b6b !important; border-color: #ff6b6b !important; }
.ce-dark-mode #ceApplyBtn { color: #60cdff !important; border-color: #60cdff !important; }
/* Ribbon Dark Mode */
.ce-dark-mode .ce-ribbon-handle { background-color: var(--gray-15); border-color: var(--gray-50); color: var(--text-secondary); box-shadow: none; }
.ce-floating-menu.ce-dark-mode, .ce-dark-mode .ce-floating-menu { background: var(--gray-10); border-color: var(--gray-50); color: var(--text-primary); }
.ce-dark-mode .ce-floating-item:hover { background: var(--gray-20) !important; border-color: var(--gray-40) !important; }

    .ce-dark-mode .ce-ribbon-sub-header { background: var(--gray-15) !important; border: none !important; }
    .ce-dark-mode .ce-ribbon-sub-btn { color: var(--text-primary) !important; }
    .ce-dark-mode .ce-ribbon-sub-btn:hover:not(:disabled) { background: var(--hover-bg-medium) !important; border-color: var(--border-medium) !important; }

   /* Restore Dark Mode Override for Base Ribbon Button */
   .ce-dark-mode .ce-ribbon-btn.active-parent { background: rgba(96, 205, 255, 0.15) !important; color: var(--accent-primary) !important; border-color: rgba(96, 205, 255, 0.3) !important; }
   .ce-dark-mode .ce-stacked-toolbar .ce-ribbon-btn.active-parent { background: var(--gray-10) !important; border-color: var(--border-default) !important; }
   .ce-dark-mode .ce-stacked-toolbar #ceFavoritesBtn.active-parent, .ce-dark-mode .ce-stacked-toolbar #ceSettingsBtn.active-parent, .ce-dark-mode .ce-stacked-toolbar #btnHelp.active-parent { background: rgba(96, 205, 255, 0.15) !important; border-color: rgba(96, 205, 255, 0.3) !important; }
.ce-dark-mode .ce-floating-item.active-item { color: var(--accent-primary) !important; }
.ce-dark-mode .ce-floating-item.active-item .myCloudIcon { background-color: rgba(96, 205, 255, 0.15) !important; color: var(--accent-primary) !important; border-color: rgba(96, 205, 255, 0.3) !important; }
.ce-dark-mode .ce-floating-item:disabled .myCloudIcon svg { fill: var(--gray-60) !important; stroke: var(--gray-60) !important; }
.ce-dark-mode .ce-floating-item:disabled .myCloudIcon { border-color: var(--gray-50) !important; }
.ce-dark-mode .ce-floating-item:disabled span { color: var(--gray-60) !important; }
.ce-dark-mode .myCloudCloudSwitcher { background: var(--gray-10); border-bottom: 1px solid var(--border-default); }

.ce-dark-mode .cloud-indicator-start { background: linear-gradient(to right, var(--gray-10) 10%, transparent 100%); }
:dir(rtl) .ce-dark-mode .cloud-indicator-start { background: linear-gradient(to left, var(--gray-10) 10%, transparent 100%); }
.ce-dark-mode .cloud-indicator-end { background: linear-gradient(to left, var(--gray-10) 10%, transparent 100%); }
:dir(rtl) .ce-dark-mode .cloud-indicator-end { background: linear-gradient(to right, var(--gray-10) 10%, transparent 100%); }

.ce-dark-mode .ce-cloud-btn { color: var(--text-secondary); }
.ce-dark-mode .ce-cloud-btn:hover { background: rgba(255,255,255,0.05); color: var(--text-primary); }
.ce-dark-mode .ce-cloud-btn.active { background: var(--gray-15); color: var(--accent-primary); font-weight: 600; border-color: var(--border-default); border-bottom-color: var(--gray-15); box-shadow: inset 0 2px 0 var(--accent-primary); z-index: 2; }
.ce-dark-mode .ce-cloud-btn.ce-admin-tab { background: rgba(255, 82, 82, 0.08); }
.ce-dark-mode .ce-cloud-btn.ce-admin-tab:hover { background: rgba(255, 82, 82, 0.15); }
.ce-dark-mode .ce-cloud-btn.ce-admin-tab.active { background: var(--gray-15); color: #ff6b6b; box-shadow: inset 0 2px 0 #ff6b6b; }
.ce-dark-mode .ce-cloud-btn.ce-email-tab { background: rgba(96, 205, 255, 0.08); color: #60cdff; }
.ce-dark-mode .ce-cloud-btn.ce-email-tab:hover { background: rgba(96, 205, 255, 0.15); }
.ce-dark-mode .ce-cloud-btn.ce-email-tab.active { background: var(--gray-15); color: #60cdff; box-shadow: inset 0 2px 0 #60cdff; }
.ce-dark-mode .ce-ribbon-handle::after { box-shadow: -4px -4px 0 var(--gray-15); }
:dir(rtl) .ce-dark-mode .ce-ribbon-handle::after { box-shadow: 4px -4px 0 var(--gray-15); }
/* Win 11 Dark Acrylic */
.myCloudContextMenu.ce-dark-mode,
.ce-dark-mode .myCloudContextSubMenu { 
    background: #1e1e1e !important; 
    border: 1px solid #444444 !important;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.8), 0 2px 6px rgba(0,0,0,0.6) !important;
    color: #ffffff !important;
}
.myCloudContextMenu.ce-dark-mode .myCloudContextItem { color: #ffffff !important; }
.myCloudContextMenu.ce-dark-mode .myCloudContextGridRow { background: rgba(255,255,255,0.05) !important; border-bottom: 1px solid #444 !important; }
.myCloudContextMenu.ce-dark-mode .myCloudContextGridItem:hover { background: rgba(255,255,255,0.1) !important; }
.myCloudContextMenu.ce-dark-mode .myCloudContextGridItem span.grid-label { color: #cccccc !important; }
.myCloudContextMenu.ce-dark-mode .myCloudContextItem:hover { background: #3e3e42 !important; }

.ce-dark-mode .myCloudTreeList li > div { color: var(--text-primary) !important; }
.ce-dark-mode .myCloudTreeList li.selectedFolder > div { color: var(--text-primary) !important; }
.ce-dark-mode .ce-ribbon-btn span.ce-ribbon-label { color: var(--ribbon-text) !important; }
.ce-dark-mode .myCloud-breadcrumb-bar { background: var(--gray-05) !important; }
.ce-dark-mode .myCloudClose:hover { color: var(--gray-00) !important; }
.ce-dark-mode .myCloudProgressPopup { background: var(--gray-10) !important; color: var(--text-primary) !important; }
.ce-dark-mode .myCloudProgressText { color: var(--text-secondary) !important; }
.ce-dark-mode .myCloudModal { background: var(--gray-10) !important; color: var(--text-primary) !important; }
.ce-dark-mode .myCloudModalHeader { color: var(--text-primary) !important; }
.ce-dark-mode .myCloudInlineInput { color: var(--text-primary) !important; }
.ce-dark-mode .myCloudTable td { color: var(--text-primary) !important; }
.ce-dark-mode .myCloud-spinner.dark { border-top-color: var(--text-secondary) !important; }
.ce-dark-mode .ce-tile-pic { background-color: var(--gray-20) !important; }
.ce-dark-mode .ce-tile-overlay { background: var(--gray-10) !important; color: var(--text-primary) !important; }
.ce-dark-mode .ce-overlay-info { color: var(--text-secondary) !important; }
.ce-dark-mode .myCloud-pdf-wrapper { background: var(--gray-00) !important; }
.ce-dark-mode .myCloud-pdf-toolbar { background: rgba(40, 40, 40, 0.95); color: var(--text-primary) !important; }
.ce-dark-mode .myCloud-pdf-btn { color: var(--text-primary) !important; }
.ce-dark-mode .myCloud-pdf-page-num { color: var(--text-primary) !important; }

/* Changelog Modal Dark Mode explicitly */
.ce-dark-mode .myCloudVer-modal { background-color: var(--gray-10) !important; border-color: var(--border-medium) !important; }
.ce-dark-mode .myCloudVer-modal-body { background-color: var(--gray-05) !important; }
.ce-dark-mode .myCloudVer-pre { background-color: var(--gray-15) !important; border: 1px solid var(--border-default) !important; }
.ce-dark-mode .myCloudVer-modal-footer { background-color: var(--gray-10) !important; }
.ce-dark-mode .myCloudVer-btn-primary { color: #ffffff !important; }


.ce-top-logout-btn {
    color: var(--danger, #e81123) !important;
    border-color: transparent !important;
    margin-inline-start: auto;
    position: sticky;
    inset-inline-end: 0;
    background-color: var(--gray-05) !important;
    z-index: 10;
}
.ce-top-logout-btn:hover {
    background-color: rgba(232, 17, 35, 0.1) !important;
    color: var(--danger, #e81123) !important;
}
.ce-dark-mode .ce-top-logout-btn {
    color: #ff6b6b !important;
	background-color: var(--gray-10) !important;
}
.ce-dark-mode .ce-top-logout-btn:hover {
    background-color: rgba(255, 107, 107, 0.15) !important;
    color: #ff6b6b !important;
}

/* #myCloudModalOverlay { z-index: 99999 !important; } */

/* --- GLOBAL OVERLAY HIERARCHY --- */
 #myCloudModalOverlay {
     z-index: 9000;
 }
 #myCloudPreviewOverlay {
     z-index: 9100;
 }
 #myCloudAlertOverlay {
     z-index: 9200;
 }

/style> 