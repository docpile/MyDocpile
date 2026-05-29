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
    /* Search Modal Specifics (Restored & Fixed) */
    .myCloudModal.search-modal {
        padding: 0;
        border: 1px solid var(--border-default);
        background: var(--gray-00);
        max-width: 800px;
        width: 90%;
        height: 80vh;
        border-radius: 6px;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    .myCloudModal.search-modal .myCloudModalBody {
        padding: 0 !important;
        display: flex;
        flex-direction: column;
        flex: 1;
        overflow: hidden;
    }
    .myCloudModal.search-modal .myCloudModalBody>div:first-child {
        padding: 8px 10px !important;
    }
    .myCloudModal.search-modal .myCloudInlineInput {
        margin: 0 !important;
    }
    .myCloudModal.search-modal .myCloudDivider {
        height: 20px !important;
        margin: 0 5px !important;
    }
    .myCloudModal.search-modal .myCloudToolbar {
        padding: 2px 8px !important;
        min-height: auto !important;
        border-bottom: 1px solid var(--border-default);
    }
    .ce-search-controls {
        padding: 8px 10px;
        border-bottom: 1px solid var(--border-default);
        background: var(--gray-10);
        flex-shrink: 0;
    }
    .myCloudModal.search-modal .myCloudToolbar button:hover:not(:disabled) {
        transform: scale(1.05) !important;
    }
    .myCloudModal.search-modal .myCloudToolbar button:not(:disabled):hover .myCloudIcon {
        transform: scale(1.05) !important;
    }                    	
    .ce-search-row {
        display: flex;
        gap: 6px;
        align-items: center;
        flex-wrap: wrap;
    }
    .ce-search-custom-row {
        display: none;
        gap: 8px;
        align-items: center;
        font-size: 12px;
        color: var(--text-secondary);
        margin-top: 6px;
		flex-wrap: wrap;
    }
    .ce-search-input-small {
        width: 125px;
        height: 24px;
        padding: 2px 4px;
        border: 1px solid var(--accent-primary);
        font-size: 12px;
    }
    .ce-search-input-num {
        width: 80px;
        height: 24px;
        padding: 2px 4px;
        border: 1px solid var(--accent-primary);
        font-size: 12px;
    }
   
    .myCloudModal.search-modal .myCloudTableContainer {
        width: 100% !important;
        overflow-x: auto !important;
    }
    .myCloudModal.search-modal .myCloudTable { width: max-content !important; min-width: 100% !important; table-layout: auto !important; }
    .myCloudModal.search-modal .myCloudTable th:nth-child(2), .myCloudModal.search-modal .myCloudTable td:nth-child(2) { width: auto !important; padding-inline-end: 20px; }
    .myCloudModal.search-modal .myCloudTable th:nth-child(3), .myCloudModal.search-modal .myCloudTable td:nth-child(3) { width: auto !important; padding-inline-end: 20px; }
    .myCloudModal.search-modal .myCloudTable th:nth-child(4) { width: 6.5em; text-align: end; }
    .myCloudModal.search-modal .myCloudTable th:nth-child(5) { width: 9.5em; }
    /* Force content to push the table width instead of bleeding */
    .myCloudModal.search-modal .ce-row-content { min-width: max-content !important; width: max-content !important; }
    .myCloudModal.search-modal .ce-name-text { min-width: max-content !important; flex-shrink: 0 !important; }
   /* [RTL FIX] Force LTR for Path and Name columns in Search Results */
    .myCloudModal.search-modal .myCloudTable td:nth-child(2),
    .myCloudModal.search-modal .myCloudTable td:nth-child(3) {
        direction: ltr !important;
        text-align: right ;
    }
    :dir(rtl) .myCloudModal.search-modal .myCloudTable th:nth-child(2),
    :dir(rtl) .myCloudModal.search-modal .myCloudTable th:nth-child(3) {
        text-align: left !important;
    }
    /* Fix Search Hover Menu visibility (Name is Col 2) */
    .myCloudModal.search-modal .myCloudTable td:nth-child(2) {
        overflow: visible !important; z-index: 1;
    }
    .myCloudModal.search-modal .myCloudRow:hover td:nth-child(2) {
        z-index: 100;
     }
	 
@media (max-width: 800px) {
    .myCloudModal.search-modal {
        width: 100% !important;
        height: 100% !important;
        height: 100dvh !important;
        max-width: none !important;
        border-radius: 0 !important;
        border: none !important;
        margin: 0 !important;
        overflow-x: auto;
        overflow-y: hidden;
    }
    .myCloudModal.search-modal .myCloudModalBody {
        border-radius: 0 !important;
    }
    .myCloudModal.search-modal .ce-search-row { flex-wrap: wrap !important; }
    .myCloudModal.search-modal .ce-search-row > label { width: 100%; margin-bottom: 2px; }
    .myCloudModal.search-modal .ce-search-row > #myCloudSearchInput { flex: 1 1 calc(100% - 40px) !important; min-width: 0; }
    .myCloudModal.search-modal .ce-search-row > #btnSearchHelp { flex-shrink: 0; }
    .myCloudModal.search-modal .ce-search-row > #myCloudSearchTagBtn,
    .myCloudModal.search-modal .ce-search-row > #myCloudSearchDate,
    .myCloudModal.search-modal .ce-search-row > #myCloudSearchSize { flex: 1 1 30%; min-width: 0 !important; width: auto !important; padding: 0 4px !important; font-size: 11px !important; }
    .myCloudModal.search-modal .ce-search-row > button.ce-search-submit-btn { flex: 1 1 100%; height: 36px !important; margin-top: 4px; }
    .myCloudModal.search-modal .ce-search-row > button.ce-search-reset-btn,
    .myCloudModal.search-modal .ce-search-row > button.ce-search-submit-btn { flex: 1 1 calc(50% - 6px); height: 36px !important; margin-top: 4px; }
    .ce-search-custom-row { gap: 4px !important; flex-direction: column; align-items: stretch !important; }
    .ce-search-custom-row > input { flex: 1 1 40%; width: 100% !important; }
    .ce-search-custom-row > strong { width: 100% !important; }
    .myCloudModal.search-modal .myCloudToolbar { overflow-x: auto; flex-wrap: nowrap; }
}

.ce-dark-mode .ce-search-controls { background: var(--gray-10) !important; color: var(--text-primary) !important; }
.ce-dark-mode .ce-search-input-small, .ce-dark-mode .ce-search-input-num { background: var(--gray-05) !important; color: var(--text-primary) !important; border-color: var(--border-default); }
.ce-dark-mode #myCloudSearchResults { background-color: transparent !important; }

</style> 