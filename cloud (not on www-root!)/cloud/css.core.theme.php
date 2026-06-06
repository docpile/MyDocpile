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
       1. IMPORTS & VARIABLES
       ========================================= */
	   
	   
    :root {
        /* Core brand / accent colors */
        --accent-primary:        #0078d4;
        --accent-color:          #0078d4;
        --accent-primary-hover:  #106ebe;
        --accent-primary-active: #005a9e;
        --accent-primary-light:  #60cdff;

        /* Neutral grays – light theme base */
        --gray-00: #ffffff;
        --gray-05: #fcfcfc;
        --gray-10: #f9f9f9;
        --gray-15: #f5f5f5;
        --gray-20: #f0f0f0;
        --gray-30: #e8e8e8;
        --gray-35: #e0e0e0;
        --gray-40: #dcdcdc;
        --gray-45: #d0d0d0;
        --gray-50: #cdcdcd;
        --gray-60: #a6a6a6;
        --gray-70: #888888;
        --gray-80: #606060;
        --gray-90: #444;
        --gray-95: #333;
        --gray-99: #202020;
        --gray-100:#191919;

        /* Semantic / state colors */
        --success:       #107c10;
        --success-light: #dff6dd;
        --warning:       #d83b01;
        --warning-light: #fde7e9;
        --danger:        #e81123;
        --danger-hover:  #c50f1f;
        --info:          #0078d4;

        /* Selection / interaction */
        --selection-bg:           rgba(0, 120, 212, 0.2);
        --selection-border:       #99d1ff;
        --selection-bg-strong:    #6699cc;
        --hover-bg-very-light:    rgba(0, 0, 0, 0.05);
        --hover-bg-light:         rgba(0, 120, 212, 0.1);
        --hover-bg-medium:        rgba(0, 120, 212, 0.15);
        --active-bg:              rgba(0, 0, 0, 0.08);

        /* Borders & dividers */
        --border-subtle:    #e8e8e8;
        --border-default:   #e0e0e0;
        --border-medium:    #c0c0c0;
        --border-strong:    #aaa;

        /* Text */
        --text-primary:      #202020;
        --text-secondary:    #606060;
        --text-disabled:     #a6a6a6;

        /* Ribbon special text (Gold/Olive) */
        --ribbon-text:       #6d6d2c;
        --ribbon-text-hover: #48481e;

        /* Typography & sizing – unchanged */
        --font-family: 'Segoe UI', 'Roboto', 'Helvetica Neue', Arial, sans-serif;
        --font-size-base: 15px;
        --row-height: 32px;
        --tree-row-height: 30px;
    }

    .ce-dark-mode {
        /* Balanced Dark Mode: Tidy, distinct elements, proper contrast */
        --gray-00:  #121212; /* Deep grey, better than pure black for depth */
        --gray-05:  #1e1e1e; /* Elevated panels */
        --gray-10:  #252526; /* Hover states / Secondary panels */
        --gray-15:  #2d2d30;
        --gray-20:  #333333;
        --gray-30:  #3e3e42;
        --gray-35:  #454545;
        --gray-40:  #555555;
        --gray-45:  #666666;
        --gray-50:  #7a7a7a;
        --gray-60:  #999999;
        --gray-70:  #bbbbbb;
        --gray-80:  #d4d4d4;
        --gray-90:  #e0e0e0;
        --gray-95:  #f0f0f0;
        --gray-99:  #fafafa;
        --gray-100: #ffffff;

        --text-primary:   #ffffff;
        --text-secondary: #cccccc;
        --text-disabled:  #888888;

        /* Visible solid borders */
        --border-subtle:  #333333;
        --border-default: #444444;
        --border-medium:  #555555;
        --border-strong:  #777777;

        /* Highly visible selections and hovers */
        --selection-bg:           rgba(96, 205, 255, 0.25);
        --selection-border:       #60cdff;
        --selection-bg-strong:    #005a9e;
        --hover-bg-very-light:    rgba(255, 255, 255, 0.1);
        --hover-bg-light:         rgba(255, 255, 255, 0.15);
        --hover-bg-medium:        rgba(255, 255, 255, 0.20);
        --active-bg:              rgba(255, 255, 255, 0.25);

        --accent-primary:        #60cdff;
        --accent-primary-hover:  #7dd9ff;
        --accent-primary-active: #40c0ff;
        --accent-primary-light:  #a0e0ff;
		
		/* Ribbon special text in Dark Mode */
        --ribbon-text:       #e5e5a1;
        --ribbon-text-hover: #ffffff;

        --success:          #2ecc71;
        --success-light:    #2ecc7144;
        --danger:           #ff5252;
        --danger-hover:     #ff3333;
        --warning:          #ffaa33;
    }




body {
	font-family: "Segoe UI",Tahoma,"Lucida Grande",Helvetica,sans-serif;
}

/* Remove the outer browser scrollbar completely. 
   The app container handles all internal scrolling. */
html, body {
    width: 100%;
    height: 100%;
    margin: 0;
    padding: 0;
    overflow: hidden; 
	overscroll-behavior: none;
}


</style> 