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
       SYMBOL DARK MODE (Local Override)
       ========================================= */
    .myCloud-symbol-grid.symbol-dark-mode {
            /* Re-declare Dark Mode variables for this scope */
            --text-primary:   #ffffff;
            --text-secondary: #cccccc;
            --border-subtle:  #444444;
            --selection-bg:           rgba(96, 205, 255, 0.25);
            --selection-border:       #60cdff;
            --selection-bg-strong:    #004275;
            --hover-bg-light:         rgba(255, 255, 255, 0.1);
            
            background-color: #121212 !important;
            border-color: var(--border-subtle) !important;
        }
    
    .myCloud-symbol-grid.symbol-dark-mode .myCloud-symbol-item,
    .myCloud-symbol-grid.symbol-dark-mode .ce-sym-label {
        color: var(--text-primary) !important;
    }

    .myCloud-symbol-grid.symbol-dark-mode .myCloud-symbol-item:hover {
        background-color: var(--hover-bg-light) !important;
        border-color: rgba(255, 255, 255, 0.2) !important;
    }

    .myCloud-symbol-grid.symbol-dark-mode .myCloud-symbol-item.selected {
        background-color: var(--selection-bg) !important;
        border-color: var(--selection-border) !important;
    }

    /* Fix Icons in Dark Mode to be visible/whiteish */
    .myCloud-symbol-grid.symbol-dark-mode .myCloudIcon svg path[fill="#757575"],
    .myCloud-symbol-grid.symbol-dark-mode .myCloudIcon svg path[fill="#555"] {
        fill: var(--text-secondary) !important;
    }
    .myCloud-symbol-grid.symbol-dark-mode .myCloudIcon svg path[fill="white"] {
        fill: var(--gray-95) !important; /* Invert white parts of icons like folder stripe */
        opacity: 0.8 !important;
    }
	
    /* --- CHECKBOX BEHAVIOR IN DARK MODE --- */
    /* 1. Hide by default (override options) */
    .myCloud-symbol-grid.symbol-dark-mode .ce-sym-checkbox {
        display: none !important;
        opacity: 0;
    }
    /* 2. Show only when mode is active */
    .myCloud-symbol-grid.symbol-dark-mode.multiselect-mode .ce-sym-checkbox {
        display: block !important;
        opacity: 1;
        animation: ceFadeInScale 0.2s ease-out;
    }

    /* --- SYMBOL DARK MODE FLOATING TOAST --- */
    #myCloudSymbolActionToast {
        position: fixed;
        bottom: 30px;
        left: 50%;
        transform: translateX(-50%) translateY(100px); /* Hidden down */
        display: flex;
        gap: 16px;
        background: rgba(30, 30, 30, 0.85);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        padding: 12px 24px;
        border-radius: 50px;
        z-index: 10000;
        transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275), opacity 0.3s ease;
        opacity: 0;
        pointer-events: none;
        box-shadow: 0 10px 25px rgba(0,0,0,0.5);
        border: 1px solid rgba(255,255,255,0.1);
    }
    #myCloudSymbolActionToast.active {
        transform: translateX(-50%) translateY(0);
        opacity: 1;
        pointer-events: auto;
    }
    .ce-sym-toast-btn {
        background: transparent;
        border: none;
        color: rgba(255,255,255,0.9);
        cursor: pointer;
        padding: 8px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }
    .ce-sym-toast-btn:hover {
        background: rgba(255,255,255,0.15);
        transform: scale(1.15);
        color: #fff;
    }
    .ce-sym-toast-btn svg {
        width: 28px; /* Slightly larger than standard */
        height: 28px;
        fill: currentColor !important;
    }
	
    .myCloudToolbar.gallery-hidden {
        display: none !important;
    }

    .myCloudDetails.symbol-dark-container {
        background-color: #202020 !important; /* Matches var(--gray-00) in dark scope */
    }
    
    /* Gallery / Symbol Dark Mode Breadcrumb Override */
    .myCloudDetails.symbol-dark-container .myCloud-breadcrumb-bar {
        background-color: #202020 !important;
        border-bottom-color: #444444 !important;
    }
    .myCloudDetails.symbol-dark-container .ce-crumb-segment {
        color: #eeeeee !important;
    }
    .myCloudDetails.symbol-dark-container .ce-crumb-segment.active {
        color: #ffffff !important;
    }
    .myCloudDetails.symbol-dark-container .ce-crumb-segment:hover {
        background-color: rgba(255, 255, 255, 0.1) !important;
        color: #60cdff !important;
    }
    .myCloudDetails.symbol-dark-container .ce-crumb-sep {
        color: #888888 !important;
    }
	
	/* Hide scrollbars exclusively in Gallery Mode */
    .myCloudDetails.is-gallery-interface::-webkit-scrollbar {
        display: none !important;
    }
    .myCloudDetails.is-gallery-interface {
        scrollbar-width: none !important;
        -ms-overflow-style: none !important;
    }

 /* Dark Mode Adjustments */
    .ce-dark-mode .myCloud-symbol-item:hover { background-color: var(--hover-bg-light); border-color: var(--gray-60); }
    .ce-dark-mode .myCloud-symbol-item.selected { background-color: var(--selection-bg-strong); border-color: var(--selection-border); }
    .ce-dark-mode .ce-sym-label { color: var(--text-primary); }
	
</style> 