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
/* SHARE DIALOG */
        .cx-shared-file td { color: #a80000 !important; font-weight: 500; }
        .cx-shared-file .myCloudIcon svg path { fill: #a80000 !important; }
        
        @keyframes cxFadeInScale { from { opacity: 0; transform: scale(0.76); } to { opacity: 1; transform: scale(1); } }
        @keyframes cxFadeOutScale { from { opacity: 1; transform: scale(1); } to { opacity: 0; transform: scale(0.76); } }

        #cx-share-overlay, #cx-dialog-overlay { 
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
            background: rgba(0,0,0,0.5); 
            z-index: 200000; align-items: center; justify-content: center; 
            font-family: 'Segoe UI', sans-serif;
            transition: background-color 0.2s ease-out; 
        }
        
        .cx-overlay-closing { background-color: transparent !important; }

        .cx-share-modal, .cx-dialog-box { 
            background: #fff; border-radius: 6px; 
            width: 90%; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25); 
            display: flex; flex-direction: column; overflow: hidden;
            animation: cxFadeInScale 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            transform-origin: center center;
        }
        
        .cx-share-modal { max-width: 520px; }
        .cx-dialog-box { max-width: 320px; z-index: 200010; }

        .cx-modal-closing { animation: cxFadeOutScale 0.7s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        
        @keyframes cxSpin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        
        .cx-spinner { display: inline-block; width: 12px; height: 12px; border: 2px solid rgba(255,255,255,0.3); border-radius: 50%; border-top-color: #fff; animation: cxSpin 1s ease-in-out infinite; margin-right: 8px; vertical-align: middle; }
        
        .cx-share-header { padding: 15px 20px; border-bottom: 1px solid #eee; background: #f9f9f9; font-weight: 600; display: flex; justify-content: space-between; align-items: center; font-size: 15px; color:#333; }
        .cx-dialog-header { padding: 15px 20px 10px; font-weight: 600; font-size: 16px; color: #333; }
        .cx-dialog-body { padding: 0 20px 20px; font-size: 14px; color: #555; line-height: 1.5; }
        
        .cx-share-body { padding: 20px; }
        .cx-share-group { margin-bottom: 15px; }
        .cx-share-group label { display: block; font-size: 12px; color: #666; margin-bottom: 5px; }
        .cx-share-input { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; font-size:14px; }
        
        .cx-share-link-box { background: #f0f6ff; border: 1px solid #cce4f7; padding: 10px; border-radius: 6px; display: flex; gap: 8px; align-items: center; }
        .cx-share-link-input { border: none; background: transparent; flex-grow: 1; color: #005a9e; outline: none; font-family: monospace; font-size: 13px; }
        
        .cx-share-footer { padding: 15px 20px; background: #f9f9f9; border-top: 1px solid #eee; display: flex; justify-content: flex-end; gap: 10px; }
        .cx-dialog-footer { padding: 15px 20px; background: #f9f9f9; border-top: 1px solid #eee; display: flex; justify-content: flex-end; gap: 8px; }
        
        .cx-btn { padding: 6px 16px; border-radius: 4px; border: 1px solid #ccc; background: #fff; cursor: pointer; font-size:13px; font-weight:500; transition: background 0.1s; display:inline-flex; align-items:center; justify-content:center;}
        .cx-btn:hover { background: #f3f3f3; }
        .cx-btn:disabled { opacity: 0.6; cursor: not-allowed; }
        .cx-btn-primary { background: #0078d4; color: white; border-color: #0078d4; }
        .cx-btn-primary:hover { background: #006abc; }
        .cx-btn-danger { color: #d13438; border-color: #fadadd; background: #fdf5f6; }
        .cx-btn-danger:hover { background: #fcebeb; }
        
        .cx-action-btn { border: none; background: #0078d4; color: #fff; width: 30px; height: 30px; border-radius: 4px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background 0.2s; }
        .cx-action-btn:hover { background: #005a9e; }
        .cx-action-btn svg { fill: #fff; width: 16px; height: 16px; }
        
        #cx-toast-container { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); z-index: 200020; display: flex; flex-direction: column; gap: 10px; pointer-events: none; }
        .cx-toast { background: #323130; color: #fff; padding: 10px 20px; border-radius: 4px; font-size: 13px; font-weight: 500; box-shadow: 0 4px 12px rgba(0,0,0,0.15); opacity: 0; transform: translateY(10px); transition: all 0.3s ease; display:flex; align-items:center; gap:8px; }
        .cx-toast.show { opacity: 1; transform: translateY(0); }
        .cx-toast svg { fill: #4caf50; width: 16px; height: 16px; }

        .cx-list-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .cx-list-table th { text-align: left; padding: 10px; border-bottom: 1px solid #ddd; background: #f3f3f3; color: #666; font-weight: 600; position: sticky; top: 0; }
        .cx-list-table td { padding: 10px; border-bottom: 1px solid #eee; vertical-align: middle; color: #333; }
        .cx-list-table tr:hover { background: #f9f9f9; }
        .cx-list-icon-btn { cursor: pointer; padding: 5px; border-radius: 4px; border: 1px solid transparent; background: transparent; display: inline-flex; align-items: center; justify-content: center; margin-left:2px; }
        .cx-list-icon-btn:hover { background: #f0f0f0; }
        .cx-list-icon-btn svg { width: 18px; height: 18px; }
        
        .cx-edit-btn svg { fill: #0078d4; }
        .cx-del-btn svg { fill: #d13438; }
        .cx-badge { padding: 3px 8px; border-radius: 4px; font-weight: 600; font-size: 11px; text-transform: uppercase; display: inline-block; }
        .cx-badge-read { background: #e6f4e6; color: #107c10; }
        .cx-badge-modify { background: #fde7e9; color: #d13438; }
        .cx-badge-upload { background: #fff4ce; color: #986f0b; }
        
        .cx-search-wrapper { padding: 10px 15px; background: #fff; border-bottom: 1px solid #eee; }
        .cx-search-input { width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 20px; outline: none; font-size: 13px; transition: border 0.2s; }
        .cx-search-input:focus { border-color: #0078d4; }

        /* Semantic Text Colors (Light Mode Defaults) */
        .cx-color-blue { color: #0078d4 !important; }
        .cx-color-green { color: #107c10 !important; }
        .cx-color-red { color: #d13438 !important; }
        .cx-color-muted { color: #666 !important; }

        /* --- DARK MODE --- */
        .ce-dark-mode .cx-share-modal,
        .ce-dark-mode .cx-dialog-box { background: #1e1e1e; border: 1px solid #444; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5); }
        .ce-dark-mode .cx-share-header,
        .ce-dark-mode .cx-dialog-header { background: #252526; border-bottom: 1px solid #444; color: #fff; }
        .ce-dark-mode .cx-dialog-body { color: #ddd; }
        .ce-dark-mode .cx-share-group label { color: #aaa; }
        .ce-dark-mode .cx-share-input { background: #2d2d30; color: #fff; border: 1px solid #555; }
        .ce-dark-mode .cx-share-input:focus { border-color: #60cdff; }
        .ce-dark-mode .cx-share-link-box { background: rgba(96, 205, 255, 0.1); border: 1px solid rgba(96, 205, 255, 0.3); }
        .ce-dark-mode .cx-share-link-input { color: #60cdff; }
        .ce-dark-mode .cx-share-footer,
        .ce-dark-mode .cx-dialog-footer { background: #252526; border-top: 1px solid #444; }
        .ce-dark-mode .cx-btn { background: #333; color: #fff; border: 1px solid #555; }
        .ce-dark-mode .cx-btn:hover:not(:disabled) { background: #444; }
        .ce-dark-mode .cx-btn-primary { background: #0078d4; border-color: #0078d4; }
        .ce-dark-mode .cx-btn-primary:hover:not(:disabled) { background: #106ebe; }
        .ce-dark-mode .cx-btn-danger { background: rgba(232, 17, 35, 0.15); color: #ff8a8a; border: 1px solid rgba(232, 17, 35, 0.3); }
        .ce-dark-mode .cx-btn-danger:hover:not(:disabled) { background: rgba(232, 17, 35, 0.25); }
        .ce-dark-mode .cx-list-table th { background: #252526; border-bottom: 1px solid #444; color: #aaa; }
        .ce-dark-mode .cx-list-table td { border-bottom: 1px solid #333; color: #eee; }
        .ce-dark-mode .cx-list-table tr:hover { background: rgba(255, 255, 255, 0.05); }
        .ce-dark-mode .cx-list-icon-btn:hover { background: #333; }
        .ce-dark-mode .cx-badge-read { background: rgba(46, 204, 113, 0.15); color: #4caf50; }
        .ce-dark-mode .cx-badge-modify { background: rgba(255, 82, 82, 0.15); color: #ff6b6b; }
        .ce-dark-mode .cx-badge-upload { background: rgba(255, 170, 51, 0.15); color: #ffaa33; }
        .ce-dark-mode .cx-search-wrapper { background: #1e1e1e; border-bottom: 1px solid #444; }
        .ce-dark-mode .cx-search-input { background: #2d2d30; color: #fff; border: 1px solid #555; }
        .ce-dark-mode .cx-search-input:focus { border-color: #60cdff; }
        .ce-dark-mode .cx-action-btn { background: #0078d4; }
        .ce-dark-mode .cx-action-btn:hover { background: #106ebe; }

        /* Semantic Text Colors (Dark Mode Legibility) */
        .ce-dark-mode .cx-color-blue { color: #60cdff !important; }
        .ce-dark-mode .cx-color-green { color: #4caf50 !important; }
        .ce-dark-mode .cx-color-red { color: #ff6b6b !important; }
        .ce-dark-mode .cx-color-muted { color: #aaa !important; }

        /* Shared File Red Highlight Fix for Dark Mode */
        .ce-dark-mode .cx-shared-file td { color: #ff6b6b !important; }
        .ce-dark-mode .cx-shared-file .myCloudIcon svg path { fill: #ff6b6b !important; }

</style> 