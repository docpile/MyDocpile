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
       OFFICE VIEW MODE
       ========================================= */
    .myCloudBody.office-mode { display: flex; flex-direction: row; }
    .myCloudPreviewPane { display: none; flex-direction: column; background: var(--gray-05); overflow: hidden; z-index: 10; position: relative; }
    .myCloudBody.office-mode .myCloudPreviewPane { display: flex; }
    .myCloudBody.office-mode .myCloudResizer.preview-resizer { display: flex !important; }
    .myCloudResizer.preview-resizer { display: none !important; border-inline-start: none; border-inline-end: 2px solid var(--border-default); }
    .myCloudBody.office-mode .ce-row-actions { display: none !important; pointer-events: none !important; }
    .myCloudBody.office-mode .myCloudDetails { border-inline-end: 1px solid var(--border-default); overflow-y: scroll !important; scrollbar-gutter: stable !important; }
    .ce-office-empty { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; color: var(--text-disabled); font-size: 14px; text-align: center; padding: 20px; }

    /* NUCLEAR CSS GUARD: Physically hide the preview pane if Commander or Gallery mode is active */
    .myCloudBody.commander-mode .myCloudPreviewPane, .myCloudBody.commander-mode .preview-resizer,
    .myCloudBody[data-interface="gallery"] .myCloudPreviewPane, .myCloudBody[data-interface="gallery"] .preview-resizer {
        display: none !important;
    }

    @media (max-width: 1024px) {
        .myCloudBody.office-mode .myCloudPreviewPane { display: none !important; }
        .myCloudBody.office-mode .myCloudResizer.preview-resizer { display: none !important; }
        .myCloudBody.office-mode .myCloudDetails { border-inline-end: none; }
    }
	
    /* Shift the invisible drag handle into the preview pane so it doesn't block the scrollbar */
    .myCloudResizer.preview-resizer::after {
        inset-inline-start: 0px !important;
        inset-inline-end: -20px !important;
    }


    /* COMPLETELY DISABLE HOVER BAR IN OFFICE MODE */
    .myCloudBody.office-mode .ce-row-actions {
        display: none !important;
    }
	
    /* PDF TOOLKIT MODAL STYLES */
    .pdf-tool-btn {
        background: var(--gray-00);
        border: 1px solid var(--border-default);
        border-radius: 8px;
        padding: 18px 12px;
        text-align: center;
        font-weight: 500;
		font-size: 12px;
        color: var(--text-primary);
        cursor: pointer;
        transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 12px;
		box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    }
    .pdf-tool-btn:hover {
        background: var(--gray-05);
        border-color: var(--border-hover);
        transform: translateY(-2px);
		box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }
	
    .pdf-tool-btn svg {
        color: var(--text-secondary);
        transition: color 0.2s, transform 0.2s;
    }
	
    .pdf-tool-btn:hover svg {
        color: var(--accent-primary);
        transform: scale(1.1);
    }


    /* PDF SIDECAR FORM STYLES */
    .myCloudFormSidecar { width: 380px; background: var(--gray-00); border-inline-start: 1px solid var(--border-default); display: flex; flex-direction: column; flex-shrink: 0; }
    .ce-sidecar-header { padding: 15px 20px; border-bottom: 1px solid var(--border-default); display: flex; justify-content: space-between; align-items: center; background: var(--gray-05); }
    .ce-sidecar-body { padding: 20px; overflow-y: auto; flex: 1; display: flex; flex-direction: column; gap: 16px; }
    .ce-sidecar-footer { padding: 15px 20px; border-top: 1px solid var(--border-default); background: var(--gray-05); }
    
    .ce-pdf-field-group { display: flex; flex-direction: column; gap: 6px; }
    .ce-pdf-field-label { font-size: 12px; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 8px; }
    .ce-pdf-field-badge { background: var(--accent-primary); color: white; border-radius: 4px; padding: 2px 6px; font-size: 10px; font-weight: bold; }
    .ce-pdf-field-hint { font-size: 11px; color: var(--text-secondary); }
    
    .ce-pdf-input { width: 100%; padding: 10px; border: 1px solid var(--border-default); border-radius: 6px; background: var(--bg-primary); color: var(--text-primary); font-family: inherit; font-size: 13px; transition: border-color 0.2s; }
    .ce-pdf-input:focus { outline: none; border-color: var(--accent-primary); box-shadow: 0 0 0 3px rgba(0, 120, 212, 0.1); }
    textarea.ce-pdf-input { resize: vertical; min-height: 80px; }
    
    .ce-pdf-checkbox-wrap { display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer; }
    .ce-pdf-checkbox-wrap input { width: 16px; height: 16px; cursor: pointer; }
	
</style> 