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
     EDITOR: WINDOWS 11 / FLUENT STYLE OVERHAUL
     ========================================= */

  #myCloudEditor_modal_wrap {
    all: initial;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.4);
    z-index: 11000;
    display: none;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(2px);
  }

  .myCloudEditor-window {
    width: 100%;
    height: 100%;
    background: #fff;
    border-radius: 0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    position: relative;
    font-family: var(--font-family);
  }

  /* Toolbars */
  #myCloudEditor_toolbar {
    background: #f3f3f3;
    border-bottom: 1px solid #e5e5e5;
    height: 48px;
    display: flex;
    align-items: center;
    padding: 0 8px;
    user-select: none;
    gap: 8px;
    flex-shrink: 0;
    overflow-x: auto;
    scrollbar-width: none;
  }
  #myCloudEditor_toolbar::-webkit-scrollbar { display: none; }

  /* Action Groups */
  .editor-action-group {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 0 8px;
    border-left: 1px solid #e0e0e0;
    height: 32px;
    flex-shrink: 0;
  }
  .editor-action-group:first-child { border-left: none; }

  /* Buttons */
  .editor-btn {
    width: 32px;
    height: 32px;
    background: rgba(0, 0, 0, 0.05);
    border: 1px solid transparent;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #444;
    transition: all 0.2s ease;
    padding: 0;
    flex-shrink: 0;
    box-sizing: border-box;
  }

  .editor-btn svg { width: 18px; height: 18px; fill: currentColor; }

  .editor-btn:hover {
    background-color: var(--accent-color, #0078d4); 
    color: #fff;
    transform: scale(1.1);
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
  }
  
  .editor-btn:active { transform: scale(0.95); }
  .editor-btn.active-tool { background-color: #cce8ff; color: #0078d4; border: 1px solid #99d1ff; }
  .editor-btn.close-btn:hover { background-color: #e81123 !important; color: #fff !important; }

  /* Inputs & Selects */
  .editor-syntax-select {
    border: 1px solid #d1d1d1;
    border-radius: 4px;
    padding: 2px 6px;
    height: 28px;
    font-size: 13px;
    color: #333;
    outline: none;
    background: #fff;
    cursor: pointer;
  }
  .editor-syntax-select:focus { border-color: var(--accent-color, #0078d4); }

  /* Tabs */
  #myCloudEditor_tabs {
    flex: 1;
    display: flex;
    align-items: flex-end;
    height: 100%;
    padding-top: 10px;
    margin-right: 12px;
  }

  .myCloudEditor-tab {
    display: flex;
    align-items: center;
    padding: 0 12px;
    margin-right: 2px;
    background: transparent;
    border-radius: 6px 6px 0 0;
    font-size: 13px;
    color: #666;
    cursor: pointer;
    height: 34px;
    transition: background 0.1s;
    user-select: none;
    white-space: nowrap;
	max-width: 150px;
	overflow: hidden;
	text-overflow: ellipsis;
  }

  .myCloudEditor-tab.dirty { font-style: italic; }
  .myCloudEditor-tab.dirty::before { content: '* '; color: #d83b01; font-weight: bold; }
  .myCloudEditor-tab:hover { background: rgba(0,0,0,0.05); color: #333; }
  .myCloudEditor-tab.active { background: #fff; color: #0078d4; font-weight: 500; box-shadow: 0 -1px 3px rgba(0,0,0,0.05); }
  
  .myCloudEditor-tab-close {
      margin-left: 8px;
      font-size: 14px;
      opacity: 0.5;
      border-radius: 50%;
      width: 16px;
      height: 16px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
  }
  .myCloudEditor-tab-close:hover { background: #e81123; color: #fff; opacity: 1; }

  /* Body, Split View & Minimap */
  .myCloudEditor-body { display: flex; flex: 1; overflow: hidden; position: relative; background: #fff; }
  
  #myCloudEditor_aceContainer, #myCloudEditor_aceContainerSplit {
    flex: 1;
    height: 100%;
  }
  #myCloudEditor_aceContainerSplit { display: none; border-left: 1px solid #ccc; }
  
  #myCloudEditor_minimap {
    display: none;
    width: 100px;
    height: 100%;
    border-left: 1px solid #e0e0e0;
    background: #fafafa;
    flex-shrink: 0;
    position: relative;
    z-index: 10;
  }

  /* Ace Overrides */
  .ace_editor { line-height: 1.35 !important; }
  .ace_gutter { background: #fdfdfd !important; border-right: 1px solid #f0f0f0 !important; color: #aaa !important; }
  .ace_marker-layer .ace_selection { background: rgba(0, 120, 212, 0.3) !important; }
  .ace_marker-layer .ace_selected-word { border: 1px solid #0078d4 !important; background: rgba(0, 120, 212, 0.1) !important; }
  .myCloudEditor_searchMatch { position: absolute; background-color: rgba(255, 215, 0, 0.4) !important; border: 1px solid rgba(255, 215, 0, 0.8) !important; border-radius: 2px; z-index: 4; }


  /* Dirty Line Indicators (High Specificity to override Ace) */
  .ace_gutter-cell.ce-dirty-gutter {
      box-shadow: inset 3px 0 0 0 var(--accent-primary, #0078d4) !important;
      background-color: rgba(0, 120, 212, 0.15) !important;
  }
  .ce-dark-mode .ace_gutter-cell.ce-dirty-gutter {
      box-shadow: inset 3px 0 0 0 var(--accent-primary-light, #60cdff) !important;
      background-color: rgba(96, 205, 255, 0.25) !important;
  }

  /* OVERRIDE: Massive Code Folding Handles for Touch (Scale only, no margin breaks) */
  .ace_gutter-cell { overflow: visible !important; }
  .ace_editor .ace_fold-widget {
      transform: scale(2) !important;
      transform-origin: center center !important;
      z-index: 100 !important;
  }

  /* Status Bar */
  #myCloudEditor_statusbar {
      height: 26px;
      background: #fdfdfd;
      border-top: 1px solid #e5e5e5;
      display: flex;
      align-items: center;
      padding: 0 12px;
      font-size: 11.5px;
      color: #666;
      gap: 16px;
      flex-shrink: 0;
      user-select: none;
  }

  /* Messages & Overlays */
  .myCloudEditor_msg {
    position: absolute; bottom: 40px; right: 20px; padding: 10px 16px; border-radius: 4px;
    font-size: 13px; font-weight: 500; color: #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    opacity: 0; transform: translateY(10px); transition: all 0.3s ease; z-index: 100;
  }
  .myCloudEditor_msg.show { opacity: 1; transform: translateY(0); }
  .myCloudEditor_msg--success { background: #107c10; }
  .myCloudEditor_msg--error { background: #d13438; }

  #myCloudEditor_minimized {
    position: fixed; bottom: 20px; left: 20px; width: 50px; height: 50px;
    background: #0078d4; border-radius: 50%; display: none; align-items: center; justify-content: center;
    cursor: pointer; box-shadow: 0 4px 15px rgba(0, 120, 212, 0.4); z-index: 12000; transition: transform 0.2s; color: #fff;
  }
  #myCloudEditor_minimized:hover { transform: scale(1.1); background: #106ebe; }
  #myCloudEditor_minimized svg { width: 24px; height: 24px; fill: #fff; }

  /* Help Overlay Modal */
  #myCloudEditor_helpOverlay {
      position: absolute; inset: 0; background: rgba(255,255,255,0.8);
      z-index: 500; display: none; align-items: center; justify-content: center;
      backdrop-filter: blur(4px);
  }
  .ce-help-box {
      background: #fff; width: 500px; max-width: 90%; border-radius: 8px;
      box-shadow: 0 10px 40px rgba(0,0,0,0.15); border: 1px solid #e5e5e5;
      overflow: hidden; display: flex; flex-direction: column;
  }
  .ce-help-box table { width: 100%; border-collapse: collapse; font-size: 13px; color: #333; }
  .ce-help-box th, .ce-help-box td { padding: 8px 16px; border-bottom: 1px solid #f0f0f0; text-align: left; }
  .ce-help-box th { background: #fafafa; font-weight: 600; color: #555; }
  .ce-help-box kbd { background: #f0f0f0; border: 1px solid #ccc; border-radius: 3px; padding: 2px 6px; font-size: 11px; font-family: monospace; box-shadow: 0 1px 0 rgba(0,0,0,0.2); }

  /* Floating Color Picker Widget */
  #myCloudEditor_colorOverlay {
      position: fixed;
      width: 20px; height: 20px;
      border: 2px solid #fff;
      border-radius: 50%;
      box-shadow: 0 2px 5px rgba(0,0,0,0.3);
      cursor: pointer;
      display: none;
      z-index: 1000;
      overflow: hidden;
      transform: translate(-50%, -100%); /* Center above cursor */
      margin-top: -5px;
  }
  #myCloudEditor_colorInput {
      opacity: 0; width: 200%; height: 200%;
      cursor: pointer; position: absolute; top: -50%; left: -50%;
  }
  
/* Mobile Adjustments: Swipeable Toolbar with Fade Handles */
  @media (max-width: 768px) {
      #myCloudEditor_toolbar { 
          display: flex;
          flex-wrap: nowrap !important; 
          overflow-x: auto; 
          overflow-y: hidden;
          -webkit-overflow-scrolling: touch; 
          scrollbar-width: none; 
          padding: 0 !important; 
          gap: 12px;
          height: 48px;
          position: relative;
      }

      #myCloudEditor_toolbar::-webkit-scrollbar { display: none; }

     /* Shield the background: Ensure the modal wrap blocks all pointer interactions with the page below */
     #myCloudEditor_modal_wrap {
         pointer-events: auto !important;
         touch-action: none; /* Prevents background "pull-to-refresh" or scrolling */
     }

     /* Re-enable touch for the window inside the wrap */
     .myCloudEditor-window {
         touch-action: auto;
     }

     /* The "Handles" - Sticky shadows that stay at the edges while you swipe */
     #myCloudEditor_toolbar::before,
     #myCloudEditor_toolbar::after {
         content: '';
         position: sticky;
         top: 0;
         z-index: 10;
         min-width: 25px;
         height: 48px;
         pointer-events: none; /* Let clicks pass through to buttons */
         flex-shrink: 0;
		 border-inline-start: 2px solid rgba(0,0,0,0.3);
     }

     /* Left side indicator */
     #myCloudEditor_toolbar::before {
		left: 0;
         background: linear-gradient(to right, rgba(0,0,0,0.25) 0%, transparent 100%);
         margin-right: -15px; /* Offset width so it doesn't push buttons */
     }

     /* Right side indicator */
     #myCloudEditor_toolbar::after {
		right: 0;
         background: linear-gradient(to left, rgba(0,0,0,0.25) 0%, transparent 100%);
         margin-left: -15px; /* Offset width so it doesn't push buttons */
         border-inline-start: none;
         border-inline-end: 2px solid rgba(0,0,0,0.1);
     }

      #myCloudEditor_tabs { 
          flex: 0 0 auto !important; 
          margin-right: 0 !important;
          padding-top: 0 !important;
          height: 100% !important;
      }

      .editor-action-group { 
          border-left: 1px solid rgba(0,0,0,0.1) !important; 
          padding: 0 8px !important; 
          flex-shrink: 0; 
          display: flex;
          align-items: center;
      }
      
      .editor-action-group:first-child { border-left: none !important; }
      
      .editor-action-group[style*="margin-left: auto"] { margin-left: 0 !important; }

      .editor-syntax-select { max-width: 80px; }
      #myCloudEditor_statusbar { display: none; } 
      #myCloudEditor_minimap { display: none !important; } 
      
      #btn_minimap_toggle, #btn_invisibles, button[title="Keyboard Shortcuts"] {
          display: none !important;
      }
   }
   
  /* --- DARK MODE OVERRIDES --- */
  .ce-dark-mode .myCloudEditor-window { background: var(--gray-05, #1e1e1e); color: var(--text-primary, #fff); }
  .ce-dark-mode #myCloudEditor_toolbar { background: var(--gray-10, #252526); border-bottom-color: var(--border-default, #444); }
  .ce-dark-mode .editor-action-group { border-left-color: var(--border-default, #444); }
  .ce-dark-mode .editor-btn { color: #ccc; }
  .ce-dark-mode .myCloudEditor-body { background: transparent !important; }
  .ce-dark-mode .editor-btn:not(.active-tool):hover { background-color: var(--gray-20, #333); color: #fff; }
  .ce-dark-mode .editor-btn.active-tool { background-color: var(--selection-bg, rgba(96,205,255,0.25)); color: var(--accent-primary, #60cdff); border-color: var(--selection-border, #60cdff); }
  .ce-dark-mode .editor-btn.close-btn:hover { background-color: #e81123 !important; color: #fff !important; }
  .ce-dark-mode .editor-syntax-select { background: var(--gray-15, #2d2d30); color: #eee; border-color: var(--border-default, #444); }
  .ce-dark-mode .editor-syntax-select:focus { border-color: var(--accent-primary, #60cdff); }
  .ce-dark-mode .myCloudEditor-tab { color: #999; }
  .ce-dark-mode .myCloudEditor-tab:hover { background: var(--hover-bg-light, rgba(255,255,255,0.1)); color: #eee; }
  .ce-dark-mode .myCloudEditor-tab.active { background: var(--gray-05, #1e1e1e); color: var(--accent-primary, #60cdff); box-shadow: none; border-bottom: 2px solid var(--accent-primary, #60cdff); }
  .ce-dark-mode #myCloudEditor_search_bar { background: var(--gray-10, #252526); border-bottom-color: var(--border-default, #444); }
  .ce-dark-mode #myCloud_search_input, .ce-dark-mode #myCloud_replace_input { background: var(--gray-15, #2d2d30); color: #eee; border-color: var(--border-default, #444); }
  .ce-dark-mode #myCloudEditor_search_bar button { background: var(--gray-15, #2d2d30) !important; color: #eee !important; border-color: var(--border-default, #444) !important; }
  .ce-dark-mode #myCloudEditor_search_bar button:hover { background: var(--gray-20, #333) !important; color: #fff !important; border-color: var(--border-strong, #777) !important; }
  .ce-dark-mode #myCloudEditor_statusbar { background: var(--gray-10, #252526); border-top-color: var(--border-default, #444); color: #aaa; }
  .ce-dark-mode #myCloudEditor_minimap { background: var(--gray-00, #121212); border-left-color: var(--border-default, #444); }
  .ce-dark-mode #myCloudEditor_aceContainerSplit { border-left-color: var(--border-default, #444); }
  .ce-dark-mode #myCloudEditor_helpOverlay { background: rgba(0,0,0,0.6); }
  .ce-dark-mode .ce-help-box { background: var(--gray-10, #252526); border-color: var(--border-default, #444); color: #eee; }
  .ce-dark-mode .ce-help-box th { background: var(--gray-15, #2d2d30); color: #ddd; border-bottom-color: var(--border-default, #444); }
  .ce-dark-mode .ce-help-box td { border-bottom-color: var(--border-subtle, #333); }
  .ce-dark-mode .ce-help-box kbd { background: var(--gray-20, #333); border-color: var(--border-default, #444); color: #eee; box-shadow: none; }
  .ce-dark-mode .ace_gutter { background: var(--gray-10, #252526) !important; border-right: 1px solid var(--border-default, #444) !important; color: var(--text-secondary, #aaa) !important; }


   /* First Run Assistant Modal Styling */
    .ce-fra-overlay {
        position: fixed;
        top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0, 0, 0, 0.4);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        z-index: 99999;
        display: none;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    .ce-fra-overlay.visible { opacity: 1; }

    .ce-fra-card {
        background: var(--gray-00);
        width: 500px;
        max-width: 90%;
        border-radius: 12px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        border: 1px solid var(--border-medium);
        overflow: hidden;
        display: flex;
        flex-direction: column;
        animation: ceFadeInScale 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        color: var(--text-primary);
    }

    .ce-fra-header {
        padding: 20px 24px 0;
        text-align: center;
        padding: 15px 20px 0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        min-height: 60px;
    }
    .ce-fra-header-content { flex: 1; text-align: center; }
    .ce-fra-back-btn {
        width: 36px; height: 36px; background: transparent; border: none; cursor: pointer;
        border-radius: 50%; display: flex; align-items: center; justify-content: center;
        color: var(--text-secondary); transition: background 0.2s;
    }
    .ce-fra-back-btn:hover { background: var(--gray-10); color: var(--text-primary); }
    .ce-fra-back-btn svg { width: 24px; height: 24px; fill: currentColor; }
    .ce-fra-spacer { width: 36px; }

    .ce-fra-title { font-size: 20px; font-weight: 900; color: var(--gray-80); margin-bottom: 0px; margin-top: 20px; }
    .ce-fra-title-mycloud { font-size: 20px; font-weight: 200; color: var(--text-primary); margin-bottom: 0px; margin-top: 20px; }
    .ce-fra-step-indicator { font-size: 11px; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 1px;  margin-bottom: 10px; }

    .ce-fra-body {
        padding: 20px 30px;
        text-align: center;
        font-size: 15px;
        line-height: 1.5;
    }
    
    .ce-fra-remark {
        margin-top: 15px;
        font-size: 12px;
        color: var(--text-secondary);
        font-style: italic;
        background: var(--gray-10);
        padding: 8px;
        border-radius: 6px;
    }

    .ce-fra-options {
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin-top: 20px;
    }

    .ce-fra-btn {
        padding: 12px 20px;
        border: 1px solid var(--border-strong);
        background: var(--gray-05);
        color: var(--text-primary);
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: space-between;
        text-align: left;
    }
    .ce-fra-btn:hover {
        background: var(--hover-bg-light);
        border-color: var(--accent-primary);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }
    .ce-fra-btn.active {
        background: var(--selection-bg);
        border-color: var(--accent-primary);
        color: var(--accent-primary);
    }
    .ce-fra-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }
    .ce-fra-footer {
        padding: 10px 30px 25px;
        display: flex;
        justify-content: center;
    }
    .ce-fra-finish-btn {
        background: var(--accent-primary); color: #fff; border: none;
        padding: 10px 40px; border-radius: 30px; font-size: 16px; font-weight: 600;
        cursor: pointer; display: flex; align-items: center; gap: 8px;
        box-shadow: 0 4px 15px rgba(0, 120, 212, 0.3); transition: all 0.2s;
    }
    .ce-fra-finish-btn:hover { transform: scale(1.05); box-shadow: 0 6px 20px rgba(0, 120, 212, 0.4); }
    .ce-fra-finish-btn:disabled { background: var(--gray-40); cursor: default; transform: none; box-shadow: none; }
    .ce-fra-finish-btn svg { width: 20px; height: 20px; fill: currentColor; }



</style> 