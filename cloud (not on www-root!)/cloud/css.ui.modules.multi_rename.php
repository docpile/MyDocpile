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
   15. MULTI-RENAME TOOL STYLES
   ========================================= */
.mr-combo-wrapper {
    position: relative;
    display: flex;
    flex: 1;
}

.mr-combo-input {
    flex: 1;
    height: 32px;
    border: 1px solid var(--border-strong);
    border-inline-end: none;
    padding: 4px 8px;
    font-size: 13px;
    outline: none;
    background: var(--gray-00);
    color: var(--text-primary);
    border-radius: 4px 0 0 4px;
}

:dir(rtl) .mr-combo-input {
    border-radius: 0 4px 4px 0;
}

.mr-combo-input:focus {
    border-color: var(--accent-primary);
    z-index: 2;
}

.mr-combo-btn {
    width: 24px;
    height: 32px;
    background: var(--gray-20);
    border: 1px solid var(--border-strong);
    border-inline-start: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-primary);
    transition: background 0.1s;
    border-radius: 0 4px 4px 0;
}

:dir(rtl) .mr-combo-btn {
    border-radius: 4px 0 0 4px;
}

.mr-combo-btn:hover {
    background: var(--gray-30);
}

.mr-combo-btn:active {
    background: var(--gray-40);
}

.mr-combo-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: var(--gray-00);
    border: 1px solid var(--border-strong);
    border-top: none;
    max-height: 200px;
    overflow-y: auto;
    z-index: 1000;
    display: none;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

.mr-combo-item {
    padding: 6px 10px;
    cursor: pointer;
    font-size: 13px;
    color: var(--text-primary);
}

.mr-combo-item:hover {
    background: var(--accent-primary);
    color: #ffffff;
}

/* Helper button specific override */
.mr-helper-btn {
    width: 30px;
    height: 32px;
    border: 1px solid var(--border-strong);
    border-inline-start: none;
    background: var(--gray-20);
    cursor: pointer;
    color: var(--text-primary);
    font-weight: bold;
    transition: background 0.2s;
    border-radius: 0 4px 4px 0;
}

:dir(rtl) .mr-helper-btn {
    border-radius: 4px 0 0 4px;
}

.mr-close-btn { width: 36px; height: 36px; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: #444; cursor: pointer; transition: background 0.1s; }
.mr-close-btn:hover { background-color: var(--danger); color: #fff; }

</style> 