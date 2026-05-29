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

    .ce-settings-dropdown.settings-mode { display: flex; flex-direction: column; padding: 0; gap: 0; width: 700px; max-width: 95vw; overflow: visible !important; background: transparent; border: none; box-shadow: none; transform-origin: top center; animation: ceRibbonIn 0.2s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
    .ce-settings-dropdown.closing { animation: ceRibbonOut 0.2s cubic-bezier(0.16, 1, 0.3, 1) forwards; pointer-events: none; }
    .ce-settings-wrapper { display: flex; flex-direction: column; flex: 1; overflow: hidden; background: var(--gray-00); border: 1px solid var(--border-medium); box-shadow: 0 8px 30px rgba(0, 0, 0, 0.25); border-radius: 18px 18px 18px 0; }
    :dir(rtl) .ce-settings-wrapper { border-radius: 18px 18px 0 18px; }
    .ce-settings-content { flex: 1; overflow-y: auto; background: var(--gray-00); display: flex; flex-direction: column;  padding-bottom: 20px;}
    .ce-settings-tabs { display: flex; background: var(--gray-15); border-bottom: 1px solid var(--gray-35); border-top: 1px solid var(--gray-70); padding: 0; flex-shrink: 0; }
    .ce-tab-btn { flex: 1; padding: 12px; background: transparent; border: none; border-bottom: 3px solid transparent; cursor: pointer; font-weight: 500; color: var(--text-secondary); display: flex; align-items: center; justify-content: center; gap: 6px; font-size: 13px; transition: background 0.1s; }
    .ce-tab-icon svg { width: 22px; height: 22px; fill: currentColor; opacity: 0.8; }
    .ce-tab-btn:hover { background: var(--gray-20); color: var(--text-primary); }
    .ce-tab-btn.active { border-bottom-color: var(--accent-primary); color: var(--accent-primary); background: var(--gray-00); }
    .ce-setting-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 5px; background: transparent; border-bottom: none;}
    .ce-setting-row:last-child { border-bottom: none; }
    .ce-setting-row:hover { background: var(--gray-05); }
    .ce-setting-row span { font-size: 13px; color: var(--text-primary); }
	.ce-setting-header {
		padding: 12px 24px 4px 24px;
		font-size: 11px;
		font-weight: 700;
		text-transform: uppercase;
		letter-spacing: 0.5px;
		color: var(--accent-primary);
		margin-top: 4px;
		border-top: 1px solid var(--border-subtle);
	}
	.ce-setting-header:first-of-type {
		border-top: none;
		margin-top: 0;
	}
	.ce-two-col {
		display: flex;
		flex-direction: row;
		align-items: flex-start;
	}
	.ce-col {
		flex: 1;
		display: flex;
		flex-direction: column;
	}

	@media (max-width: 600px) {
		.ce-settings-dropdown.settings-mode {
			width: 360px; /* Switch to narrow width on mobile */
		}
	}
	@media (max-width: 700px) {
		.ce-two-col {
			flex-direction: column !important;
		}
		.ce-col {
			border-left: none !important;
			border-top: 1px solid var(--border-subtle);
		}
		.ce-col:first-child {
			border-top: none;
		}
	}
    .ce-setting-block { background: var(--gray-05); padding: 10px; border: 1px solid var(--border-default); margin-top: 10px; }
    .ce-settings-footer { display: flex; justify-content: flex-end; padding: 10px 15px !important; background: var(--gray-15); border-top: 1px solid var(--gray-35); gap: 8px; }
    .ce-settings-footer button { flex: 1; height: 32px; font-size: 13px; }
   
    .ce-toggle-switch { position: relative; display: inline-block; width: 34px; height: 18px; }
    .ce-toggle-switch input { opacity: 0; width: 0; height: 0; }
    .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: var(--gray-50); transition: .2s; border-radius: 18px; }
    .slider:before { position: absolute; content: ""; height: 14px; width: 14px; left: 2px; bottom: 2px; background-color: var(--gray-00); transition: .2s; border-radius: 50%; }
    input:checked + .slider { background-color: var(--accent-primary); }
    input:checked + .slider:before { transform: translateX(16px); }
   
    .ce-range-slider { -webkit-appearance: none; width: 100%; height: 6px; background: var(--gray-40); outline: none; opacity: 0.9; transition: opacity .2s; border-radius: 3px; margin: 10px 0; cursor: pointer; position: relative; z-index: 5000; pointer-events: auto !important; user-select: auto !important; }
    .ce-range-slider::-webkit-slider-thumb { -webkit-appearance: none; appearance: none; width: 18px; height: 18px; background: var(--accent-primary); border-radius: 50%; cursor: pointer; box-shadow: 0 1px 3px rgba(0,0,0,0.3); }
    .ce-range-slider::-moz-range-thumb { width: 18px; height: 18px; background: var(--accent-primary); border-radius: 50%; cursor: pointer; }


</style> 