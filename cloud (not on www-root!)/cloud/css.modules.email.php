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
/* FIX: Enforce flex stretching and minimum height to prevent collapse */
.ce-email-panes { display: flex; flex: 1 1 auto; height: 100%; min-height: 0; width: 100%; overflow: hidden; flex-direction: row; position: relative; }

    /* Mobile Responsive Overrides */
    .ce-email-mobile-only { display: none !important; }
    @media (max-width: 768px) {
        .ce-email-panes { display: flex; flex-direction: column; }
        .ce-email-tree, .ce-email-list, .ce-email-reading { position: absolute; inset: 0; width: 100% !important; height: 100% !important; z-index: 1; display: none !important; }
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
.ce-email-list-actions { position: absolute; bottom: 8px; inset-inline-end: 12px; opacity: 0; pointer-events: none; transition: opacity 0.15s ease-in-out; background: var(--gray-00); border-radius: 4px; box-shadow: none; z-index: 10; }
.ce-email-list-item:hover .ce-email-list-actions, .ce-email-list-item:focus-within .ce-email-list-actions { opacity: 1; pointer-events: auto; }
.ce-email-list-item.selected .ce-email-list-actions { background: var(--selection-bg); box-shadow: none; }
:dir(rtl) .ce-email-list-actions { box-shadow: none; }
:dir(rtl) .ce-email-list-item.selected .ce-email-list-actions { box-shadow: none; }

.ce-email-list-del-btn { background: transparent; border: none; color: var(--gray-70); cursor: pointer; padding: 4px; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
.ce-email-list-del-btn:hover { color: var(--gray-99); transform: scale(1.15); }

/* Reading Pane */
.ce-email-read-header { margin-block-end: 10px; padding-block-start: 12px; padding-inline: 20px; padding-block-end: 10px; border-block-end: 1px solid var(--border-default); }
.ce-email-read-subject { margin-block: 0 6px; color: var(--text-primary); font-size: 18px; line-height: 1.3; }
.ce-email-read-meta { display: flex; justify-content: space-between; color: var(--text-primary); font-size: 14px; gap: 10px; flex-wrap: wrap; align-items: flex-start; }
.ce-email-read-meta b { color: var(--text-secondary); font-weight: normal; font-size: 12px; }
.ce-email-read-meta-extended { display: none; margin-block-start: 6px; color: var(--text-primary); font-size: 14px; flex-direction: column; gap: 4px; }
.ce-email-read-meta-extended b { color: var(--text-secondary); font-weight: normal; font-size: 12px; display: inline-block; width: 40px; }
.ce-email-read-meta-extended.expanded { display: flex; }
.ce-email-meta-toggle { background: transparent; border: none; color: var(--text-secondary); cursor: pointer; padding-block: 2px; padding-inline: 6px; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center; transition: background 0.2s, color 0.2s; }
.ce-email-meta-toggle:hover { background: var(--gray-15); color: var(--text-primary); }
.ce-email-meta-toggle svg { width: 16px; height: 16px; fill: currentColor; transition: transform 0.2s; }
.ce-email-meta-toggle.expanded svg { transform: rotate(180deg); }
.ce-dark-mode .ce-email-meta-toggle:hover { background: var(--gray-20); }

.ce-email-addr-pill { position: relative; display: inline-flex; align-items: center; background: var(--gray-10); color: var(--text-primary); border-radius: 16px; padding-block: 2px; padding-inline: 10px; font-size: 13px; font-weight: 500; cursor: pointer; max-width: 100%; }
.ce-email-pill-label { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 350px; }
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

.ce-email-attachments { padding-block: 8px; padding-inline: 20px; background: transparent; border-block-start: 1px solid var(--border-default); display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }


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
.ce-pane-refreshing {
    position: relative;
    pointer-events: auto;
}
/* Indeterminate top loading bar (like iOS/Android pull-to-refresh) */
.ce-pane-loading::after, .ce-pane-refreshing::after {
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

/* --- CONTACT BOOK TABS --- */
.ce-contact-tabs { display: flex; gap: 20px; padding: 0 15px; border-bottom: 1px solid var(--border-default); background: var(--gray-00); flex-shrink: 0; }
.ce-contact-tab { padding: 12px 4px; font-size: 14px; font-weight: 600; color: var(--text-secondary); cursor: pointer; border-bottom: 2px solid transparent; transition: color 0.2s, border-color 0.2s; white-space: nowrap; }
.ce-contact-tab:hover { color: var(--text-primary); }
.ce-contact-tab.active { color: var(--accent-primary); border-bottom-color: var(--accent-primary); }
.ce-dark-mode .ce-contact-tab.active { color: var(--accent-primary-light, #60cdff); border-bottom-color: var(--accent-primary-light, #60cdff); }

.owa-toolbar, 
.ce-email-read-header,
.ce-email-panes,
.ce-email-flipper-wrap,
.ce-email-app-root *:not(input):not(textarea):not(select):not(option):not([contenteditable="true"]):not(.ce-selectable):not(.ce-selectable *) {
    -webkit-user-select: none !important;
    -moz-user-select: none !important;
    -ms-user-select: none !important;
    user-select: none !important;
}

input,
textarea,
select,
option,
[contenteditable="true"] *,
.ce-selectable,
.ce-selectable * {
    -webkit-user-select: text !important;
    -moz-user-select: text !important;
    -ms-user-select: text !important;
    user-select: text !important;
}

</style> 