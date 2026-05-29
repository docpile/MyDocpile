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
   PREVIEW OVERLAY & BASE
───────────────────────────────────────────────── */
    .myCloudModal.preview {
    width: 100% !important;     
    height: 100% !important;   
    max-width: none !important;
    max-height: none !important;
    margin: 0 !important;
    border-radius: 0 !important;
    border: none !important;
    background-color: #000000 !important; 
    color: #ffffff !important;
    display: flex;
    flex-direction: column;
    position: fixed;            
    top: 0;
    left: 0;
    z-index: 12001;              
    overflow: hidden;
}

    /* [FIX] Hide Scrollbars inside Preview (All Browsers) */
    .myCloudModal.preview ::-webkit-scrollbar { 
        display: none !important; 
    }
    .myCloudModal.preview * { 
        scrollbar-width: none !important; 
        -ms-overflow-style: none !important; 
    }

    .myCloudModal.preview .myCloudModalHeader {
        display: none !important;
    }
	
	#myCloudPreviewOverlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    height: 100vh;
    height: 100dvh;
    background-color: #000000 !important;
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 12000;
    overflow: hidden;
}

#myCloudPreviewOverlay .myCloud-floating-close,
#myCloudPreviewOverlay .myCloud-floating-download,
#myCloudPreviewOverlay .myCloud-floating-toggle,
#myCloudPreviewOverlay .myCloud-floating-share,
#myCloudPreviewOverlay .myCloud-floating-info,
#myCloudPreviewOverlay .myCloud-zoom-container,
#myCloudPreviewOverlay .myCloud-nav-container,
#myCloudPreviewOverlay .myCloud-filmstrip,
#myCloudPreviewOverlay .myCloud-exif-modal {
    transition: opacity 0.3s ease, transform 0.2s, background 0.2s;
}

#myCloudPreviewOverlay.ui-hidden .myCloud-floating-close,
#myCloudPreviewOverlay.ui-hidden .myCloud-floating-download,
#myCloudPreviewOverlay.ui-hidden .myCloud-floating-toggle,
#myCloudPreviewOverlay.ui-hidden .myCloud-floating-share,
#myCloudPreviewOverlay.ui-hidden .myCloud-floating-info,
#myCloudPreviewOverlay.ui-hidden .myCloud-zoom-container,
#myCloudPreviewOverlay.ui-hidden .myCloud-nav-container,
#myCloudPreviewOverlay.ui-hidden .myCloud-filmstrip,
#myCloudPreviewOverlay.ui-hidden .myCloud-exif-modal {
    opacity: 0 !important;
    pointer-events: none !important;
}

.myCloudModal.preview .myCloudModalBody {
    padding: 0;
    background: transparent;
    flex: 1;
    min-height: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    position: relative;
    width: 100%;
}

.myCloudModal.preview .myCloudModalBody img,
.myCloudModal.preview .myCloudModalBody video,
.myCloudModal.preview .myCloudModalBody audio,
.myCloudModal.preview .myCloudModalBody embed,
.myCloudModal.preview .myCloudModalBody iframe,
.myCloudModal.preview .myCloudModalBody object {
    width: 100%;
    height: 100%;
    border: none;
    display: block;
    object-fit: contain;
}

.myCloudModal.preview .myCloudModalBody audio {
    height: auto;
    width: 80%;
}

#myCloudPreviewImg {
    transform-origin: center center;
    max-width: 100%;
    max-height: 100%;
    touch-action: none;
    cursor: grab;
    will-change: transform;
}

#myCloudPreviewImg:active {
    cursor: grabbing;
}

/* ────────────────────────────────────────────────
   FLOATING HEADER CONTROLS
───────────────────────────────────────────────── */
.myCloud-floating-close,
.myCloud-floating-download,
.myCloud-floating-toggle,
.myCloud-floating-share {
    position: absolute;
    top: max(5px, env(safe-area-inset-top));
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: rgba(0, 0, 0, 0.4);
    color: rgba(255, 255, 255, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    z-index: 3200;
    transition: all 0.2s ease;
    user-select: none;
    backdrop-filter: blur(4px);
    -webkit-tap-highlight-color: transparent;
}

.myCloud-floating-close svg,
.myCloud-floating-download svg,
.myCloud-floating-toggle svg,
.myCloud-floating-share svg {
    width: 24px;
    height: 24px;
    fill: currentColor;
}

.myCloud-floating-close       { inset-inline-end: 5px; }
.myCloud-floating-download    { inset-inline-end: 60px; }
.myCloud-floating-share       { inset-inline-end: 115px; }
.myCloud-floating-toggle      { inset-inline-end: 170px !important; }

:dir(rtl) .myCloud-floating-toggle { transform: none; }

.myCloud-floating-close:hover,
.myCloud-floating-download:hover,
.myCloud-floating-toggle:hover,
.myCloud-floating-share:hover {
    transform: scale(1.05);
    background: var(--accent-primary-hover);
    color: var(--gray-00);
}

.myCloud-floating-close:hover {
    background: var(--danger-hover);
    transform-origin: top right;
}

/* ────────────────────────────────────────────────
   ZOOM & NAVIGATION
───────────────────────────────────────────────── */
.myCloud-zoom-container {
    position: absolute;
    bottom: max(20px, env(safe-area-inset-bottom));
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 10px;
    z-index: 3100;
    background: rgba(0, 0, 0, 0.6);
    padding: 8px 20px;
    border-radius: 30px;
    backdrop-filter: blur(6px);
}

.myCloud-zoom-btn {
    background: transparent;
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: var(--gray-00);
    width: 40px;
    height: 40px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    transition: all 0.2s;
    user-select: none;
}

.myCloud-zoom-btn:hover {
    background: var(--accent-primary-hover);
    border-color: var(--gray-00);
    color: var(--gray-00);
}

:dir(rtl) .myCloud-zoom-btn:nth-child(1),
:dir(rtl) .myCloud-zoom-btn:nth-child(2) {
    transform: scaleX(-1);
}

.myCloud-nav-container {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    inset-inline-start: 0;
    width: 100%;
    height: 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0 16px;
    pointer-events: none;
    z-index: 3100;
}

.myCloud-nav-container.video-mode {
    bottom: 80px; /* Fallback for video layout */
}

.myCloud-prev-nav,
.myCloud-next-nav {
    pointer-events: auto;
    width: 50px;
    height: 50px;
    background: rgba(0, 0, 0, 0.4);
    border-radius: 50%;
    color: rgba(255, 255, 255, 0.8);
    font-size: 2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    user-select: none;
    cursor: pointer;
    transition: all 0.2s ease;
}

.myCloud-prev-nav:hover,
.myCloud-next-nav:hover {
    background: var(--accent-primary-hover);
    color: var(--gray-00);
    transform: scale(1.05);
    box-shadow: 0 0 15px rgba(0, 120, 212, 0.4);
}

:dir(rtl) .myCloud-prev-nav,
:dir(rtl) .myCloud-next-nav {
    transform: scaleX(-1);
}

:dir(rtl) .myCloud-prev-nav:hover,
:dir(rtl) .myCloud-next-nav:hover {
    transform: scaleX(-1) scale(1.05);
}

/* ────────────────────────────────────────────────
   FILMSTRIP & EXIF
───────────────────────────────────────────────── */
.myCloud-filmstrip {
    position: absolute;
    bottom: calc(85px + env(safe-area-inset-bottom, 0px));
    left: 0; right: 0;
    height: 75px;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(5px);
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 0 15px;
    overflow-x: auto;
    z-index: 12500;
    scroll-behavior: smooth;
    scrollbar-width: none;
    -ms-overflow-style: none;
}

.myCloud-filmstrip::before,
.myCloud-filmstrip::after {
    content: "";
    margin: auto;
}

.myCloud-filmstrip img {
    height: 60px;
    width: 60px;
    object-fit: cover;
    border-radius: 4px;
    cursor: pointer;
    opacity: 0.5;
    transition: all 0.2s;
    flex-shrink: 0;
    border: 2px solid transparent;
}

.myCloud-filmstrip img:hover { opacity: 0.9; }
.myCloud-filmstrip img.active { opacity: 1; border-color: var(--accent-primary); transform: scale(1.05); }

.myCloud-exif-modal {
    position: absolute;
    top: 20px;
    left: 20px;
    width: 320px;
    max-width: 90vw;
    max-height: 80vh;
    overflow-y: auto;
    background: rgba(30, 30, 30, 0.95);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    color: #fff;
    z-index: 12500;
    display: none;
    padding: 15px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
}

:dir(rtl) .myCloud-exif-modal { left: auto; right: 20px; }

.myCloud-exif-row { display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.05); padding: 6px 0; font-size: 13px; }
.myCloud-exif-row span:first-child { color: #aaa; }

/* ────────────────────────────────────────────────
   DOCUMENT & OFFICE VIEWERS
───────────────────────────────────────────────── */
.docx_viewer { box-shadow: none; margin: 0; }
.docx_page { margin: 0 auto 20px auto !important; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3); }

.myCloud-excel-wrapper,
.docx_viewer_wrapper,
.myCloud-pdf-wrapper {
    height: 100%; width: 100%; overflow: hidden;
}

.myCloud-excel-wrapper {
    display: flex; flex-direction: column;
    color: var(--text-primary) !important;
    font-family: 'Calibri', 'Segoe UI', sans-serif;
    font-size: 14px;
    background: var(--gray-00);
}

.myCloud-excel-sheets { flex: 1; overflow: auto; background: var(--gray-00); position: relative; }
.myCloud-sheet-view { display: none; width: 100%; min-height: 100%; padding: 20px; background: var(--gray-00); }
.myCloud-sheet-view.active { display: block; }
.myCloud-excel-table { border-collapse: collapse; min-width: 100%; background: var(--gray-00); }
.myCloud-excel-table td, .myCloud-excel-table th { border: 1px solid var(--gray-35); padding: 2px 6px; white-space: nowrap; height: 20px; color: var(--text-primary); vertical-align: bottom; }
.myCloud-excel-table tr:first-child td { background: var(--gray-10); font-weight: bold; text-align: center; border-bottom: 2px solid var(--gray-30); }

.myCloud-excel-tabs { flex: 0 0 36px; background: var(--gray-15); border-top: 1px solid var(--gray-35); display: flex; align-items: flex-end; padding-inline-start: 8px; overflow-x: auto; white-space: nowrap; }
.myCloud-excel-tabs::-webkit-scrollbar { height: 6px; }
.myCloud-excel-tabs::-webkit-scrollbar-thumb { background: var(--gray-60); border-radius: 3px; }

.myCloud-excel-tab {
    flex: 0 0 auto;
    padding: 8px 16px;
    margin-inline-end: 4px;
    cursor: pointer;
    background: var(--gray-20);
    border: 1px solid var(--gray-35);
    border-bottom: none;
    border-radius: 6px 6px 0 0;
    font-size: 13px;
    color: var(--gray-70);
    position: relative;
    top: 1px;
    transition: all 0.14s;
    user-select: none;
}
.myCloud-excel-tab:hover { background: var(--gray-10); }
.myCloud-excel-tab.active { background: var(--gray-00); border-bottom: 2px solid var(--gray-00); font-weight: 600; color: #217346; z-index: 5; }

.myCloud-pdf-wrapper { display: flex; flex-direction: column; background: var(--gray-99); position: relative; }
.myCloud-pdf-scroll { flex: 1; overflow: auto; display: flex; justify-content: center; padding: 30px; -webkit-overflow-scrolling: touch; cursor: grab; user-select: none; }
.myCloud-pdf-scroll.grabbing { cursor: grabbing; }

#myCloudPdfCanvas { box-shadow: 0 4px 15px rgba(0,0,0,0.4); background: var(--gray-00); display: block; max-width: none !important; }

.myCloud-pdf-toolbar {
    position: absolute;
    bottom: max(32px, env(safe-area-inset-bottom));
    left: 50%;
    transform: translateX(-50%);
    background: rgba(35, 35, 35, 0.92);
    backdrop-filter: blur(8px);
    border-radius: 999px;
    padding: 8px 20px;
    display: flex;
    gap: 10px;
    align-items: center;
    box-shadow: 0 8px 28px rgba(0, 0, 0, 0.45);
    z-index: 100;
    max-width: 94%;
    overflow-x: auto;
}

.myCloud-pdf-btn { background: transparent; border: 1px solid rgba(255,255,255,0.25); color: white; width: 38px; height: 38px; border-radius: 50%; flex-shrink: 0; font-size: 15px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.16s; user-select: none; }
:dir(rtl) .myCloud-pdf-btn { transform: scaleX(-1); }
.myCloud-pdf-btn:hover { background: rgba(255,255,255,0.18); }
.myCloud-pdf-page-num { color: var(--gray-00); font-family: monospace; font-size: 14px; min-width: 60px; text-align: center; user-select: none; }

/* ────────────────────────────────────────────────
   LOGOUT FLOATING BUTTON
───────────────────────────────────────────────── */
.myCloud-floating-logout {
    position: fixed;
    top: max(2px, env(safe-area-inset-top));
    inset-inline-end: 20px;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: rgba(32, 32, 32, 0.55);
    backdrop-filter: blur(4px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    z-index: 99999;
    color: #ffffff;
    transition: all 0.2s ease;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    user-select: none;
}
.myCloud-floating-logout:hover {
    background: var(--danger-hover);
    border-color: var(--gray-00);
    transform: scale(1.05);
    transform-origin: top right;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
}
.myCloud-floating-logout svg { width: 15px; height: 15px; fill: currentColor; flex-shrink: 0; }
.myCloud-logout-text {
    position: absolute;
    top: 100%;
    left: 50%;
    transform: translateX(-50%);
    margin-top: 2px;
    font-size: 8px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-primary);
    text-shadow: 0 1px 3px var(--gray-00);
    white-space: nowrap;
    pointer-events: none;
    transition: color 0.2s;
}
.myCloud-floating-logout:hover .myCloud-logout-text { color: var(--danger-hover); }

/* ────────────────────────────────────────────────
   GALLERY / GRID VIEW
───────────────────────────────────────────────── */
.myCloud-gallery {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(114px, 1fr));
    padding: 12px;
    gap: 6px;
    width: 100%;
    box-sizing: border-box;
    grid-auto-rows: min-content;
    overflow-y: visible;
    background-color: var(--gray-99) !important;
    height: auto;
    min-height: 100%;
}

.myCloud-gallery-item {
    position: relative;
    width: 100%;
    aspect-ratio: 1 / 1;
    height: auto;
    border: 1px solid transparent;
    border-radius: 6px;
    overflow: hidden;
    cursor: pointer;
    transition: transform 0.15s cubic-bezier(0.25, 0.46, 0.45, 0.94), box-shadow 0.18s, z-index 0s 0.15s;
    box-sizing: border-box;
    z-index: 1;
    display: flex;
    flex-direction: column;
}

.myCloud-gallery-item.selected { border: 2px solid var(--accent-primary) !important; }
.myCloud-gallery-item.selected::after {
    content: '✓';
    position: absolute;
    top: 4px;
    right: 4px;
    background: var(--accent-primary);
    color: var(--gray-00);
    font-size: 10px;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 101;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
}
:dir(rtl) .myCloud-gallery-item.selected::after { right: auto; left: 4px; }

.ce-tile-pic {
    background-color: var(--gray-90);
    width: 100%;
    height: 100%;
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.ce-tile-pic .myCloud-gallery-thumb { width: 100%; height: 100%; flex: 1; display: flex; align-items: center; justify-content: center; overflow: hidden; }

.ce-gallery-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    opacity: 0;
    transition: opacity 0.3s;
    display: block;
}
.ce-gallery-img.loaded { opacity: 1; }

.ce-thumb-placeholder { display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; }

.myCloud-gallery .myCloud-spinner { border-color: rgba(255, 255, 255, 0.2) !important; border-top-color: var(--gray-00) !important; opacity: 1 !important; }

.ce-tile-pic:hover {
    transform: scale(1.38);
    z-index: 1000;
    box-shadow: 0 16px 48px rgba(0, 0, 0, 0.6);
    border: 1px solid var(--gray-70);
    overflow: visible;
    transition: transform 0.15s cubic-bezier(0.25, 0.46, 0.45, 0.94), box-shadow 0.18s, z-index 0s;
}

.ce-tile-overlay {
    position: absolute;
    top: 100%;
    inset-inline-start: -1px;
    inset-inline-end: -1px;
    background: var(--gray-100);
    border: 1px solid var(--gray-70);
    border-top: none;
    color: var(--gray-00);
    padding: 6px 8px;
    opacity: 0;
    transition: opacity 0.14s 0.04s;
    pointer-events: none;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.4);
    display: flex;
    flex-direction: column;
    z-index: 1001;
}

.ce-tile-pic:hover .ce-tile-overlay { opacity: 1; }

.ce-overlay-name { font-size: 10px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 1px; color: var(--gray-00); }
.ce-overlay-info { font-size: 8px; color: var(--gray-60); }

.ce-tile-file {
    background-color: var(--gray-90) !important;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 8px;
    border: 1px solid var(--gray-95);
}
.ce-tile-file:hover { background-color: var(--gray-80) !important; border-color: var(--gray-70); z-index: 50; }
.ce-tile-file .myCloud-gallery-thumb { flex: 1; display: flex; align-items: center; justify-content: center; width: 100%; min-height: 0; overflow: hidden; }
.ce-tile-file .myCloudIcon, .ce-tile-file svg { filter: brightness(1.2); max-width: 100%; max-height: 100%; }

.ce-tile-filename {
    width: 100%;
    text-align: center;
    font-size: 12px;
    color: var(--gray-10);
    margin-top: 4px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    line-height: 1;
    flex-shrink: 0;
}

.myCloud-gallery svg path[fill="white"],
.myCloud-gallery svg path[fill="#ffffff"] { fill: var(--gray-80) !important; opacity: 1 !important; }

.myCloud-gallery svg path[fill="#757575"],
.myCloud-gallery svg path[fill="#555"] { fill: var(--gray-60) !important; }

</style>