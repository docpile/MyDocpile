<?php
/**
 * ============================================================================
 * MODULE: Onboarding & First Run Assistant
 * ============================================================================
 * Manages the initialization workflows, setup tutorials, and interactive 
 * configuration guides for new users accessing the application for the first time.
 * NOTE: Executed exclusively by the client browser.
 * 
 * THIS IS PART OF THE PSEUDO "JS FILE" - SO THE NO PHP CODE IN THIS FILE!
 */
?>
<script>
(function() {
    // --- DOM INJECTION ---
    function ceInitFRADOM() {
        if (document.getElementById('myCloudFRA')) return;
        const wrapper = document.createElement('div');
		wrapper.innerHTML = [
                '<div id="myCloudFRA" class="ce-fra-overlay">',
                '    <div class="ce-fra-card">',
                '        <div id="ceFraContent"></div>',
                '    </div>',
                '</div>'
            ].join('');
			document.body.appendChild(wrapper.firstElementChild);
    }

    var currentStep = 0;
    var devices = ['desktop', 'tablet', 'phone'];

    var applyToAll = function(updates) {
        devices.forEach(function(dev) {
            if (!myCloudState.settings[dev]) {
                myCloudState.settings[dev] = Object.assign({}, myCloudDefaultSettings[dev]);
            }
            Object.assign(myCloudState.settings[dev], updates);
        });
    };
	
    var backBtnIcon = '<svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>';
    var finishIcon = '<svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>';

    var getHeader = function(step, title) {
        var backHtml = (currentStep > 0) 
            ? '<button class="ce-fra-back-btn" onclick="myCloudFraBack()" title="Back">' + backBtnIcon + '</button>'
            : '<div class="ce-fra-spacer"></div>';
            
        return '<div class="ce-fra-header">' +
               backHtml +
               '<div class="ce-fra-header-content">' +
                   '<div class="ce-fra-step-indicator">' + step + ' / 3</div>' +
                   '<div class="ce-fra-title">' + title + '</div>' +
               '</div>' +
               '<div class="ce-fra-spacer"></div>' +
               '</div>';
    };

    var steps = [
        // STEP 1: LANGUAGE
        {
            render: function() {
                var langs = myCloud_LANG.available_languages || { 'en': 'English' };
                var buttons = '';
                Object.keys(langs).forEach(function(code) {
                    var label = langs[code];
                    var check = (code === myCloudState.settings.language) ? '<span>✓</span>' : '';
                    buttons += '<button class="ce-fra-btn" onclick="myCloudFraSetLang(\'' + code + '\')"><span>' + label + '</span>' + check + '</button>';
                });

				return getHeader(1, '<span class="ce-fra-title-mycloud">' + myCloudSvgLogo + '</span> ' + (myCloud_LANG.fra_title || 'First Run Assistant')) +
                       '<div class="ce-fra-body"><div style="margin-bottom:20px;">' + (myCloud_LANG.fra_step1_msg || "In which language should I be displayed?") + '</div><div class="ce-fra-grid">' + buttons + '</div></div>';
            }
        },
        // STEP 2: UI STYLE
        {
            render: function() {
               return getHeader(2, (myCloud_LANG.fra_ui_style || 'User Interface')) +
                       '<div class="ce-fra-body"><div>' + (myCloud_LANG.fra_step2_msg || "How do you want my user interface? More like the Windows Explorer, or like a Cloud (e.g. Nextcloud)?") + '</div>' +
                       '<div class="ce-fra-options">' +
                       '<button class="ce-fra-btn" onclick="myCloudFraSetUI(\'windows\')"><span>🪟 ' + (myCloud_LANG.fra_btn_win || "Windows Explorer") + '</span></button>' +
                       '<button class="ce-fra-btn" onclick="myCloudFraSetUI(\'mixed\')"><span>✨ ' + (myCloud_LANG.fra_btn_mixed || "Best of both worlds") + '</span></button>' +
                       '<button class="ce-fra-btn" onclick="myCloudFraSetUI(\'cloud\')"><span>☁️ ' + (myCloud_LANG.fra_btn_cloud || "Cloud") + '</span></button>' +
                       '</div><div class="ce-fra-remark">' + (myCloud_LANG.fra_step2_remark || "Remark: This can later easily be changed in the options") + '</div></div>';
            }
        },
        // STEP 3: INTERNET SPEED
        {
            render: function() {
                // Check current setting to determine active button
                var s = myCloudState.settings.desktop; // Use desktop as reference
                var current = '';
                if (s.clickToPreview && s.showListThumbnails) current = 'fast';
                else if (s.clickToPreview && !s.showListThumbnails) current = 'medium';
                else if (!s.clickToPreview) current = 'slow';

                var btnCls = function(type) { return 'ce-fra-btn' + (current === type ? ' active' : ''); };

                return getHeader(3, (myCloud_LANG.fra_net_speed || 'Connection')) +
                       '<div class="ce-fra-body"><div>' + (myCloud_LANG.fra_step3_msg || "How fast is your internet connection?") + '</div>' +
                       '<div class="ce-fra-options">' +
                       '<button class="' + btnCls('fast') + '" onclick="myCloudFraSetNet(\'fast\')"><span>🚀 ' + (myCloud_LANG.fra_btn_fast || "Fast") + '</span></button>' +
                       '<button class="' + btnCls('medium') + '" onclick="myCloudFraSetNet(\'medium\')"><span>🐎 ' + (myCloud_LANG.fra_btn_medium || "Medium") + '</span></button>' +
                       '<button class="' + btnCls('slow') + '" onclick="myCloudFraSetNet(\'slow\')"><span>🐌 ' + (myCloud_LANG.fra_btn_slow || "Slow") + '</span></button>' +
                       '</div></div>' +
                       '<div class="ce-fra-footer">' +
                       '<button class="ce-fra-finish-btn" onclick="myCloudFraFinish()" ' + (current === '' ? 'disabled' : '') + '>' + finishIcon + (myCloud_LANG.ok || 'OK') + '</button>' +
                       '</div>';
                       '</div></div>';
            }
        }
    ];

    window.myCloudStartFRA = function() {
        ceInitFRADOM();
        currentStep = 0;
        var overlay = document.getElementById('myCloudFRA');
        overlay.style.display = 'flex';
        void overlay.offsetWidth; 
        overlay.classList.add('visible');
        renderStep();
    };

    function renderStep() {
        var container = document.getElementById('ceFraContent');
        container.innerHTML = steps[currentStep].render();
    }

    // --- ACTIONS ---
	
    window.myCloudFraBack = function() {
        if (currentStep > 0) {
            currentStep--;
            renderStep();
        }
    };

    window.myCloudFraSetLang = function(code) {
        // 1. Update local state
        myCloudState.settings.language = code;
        
        // 2. Call Server to switch language (gets new strings)
        var fd = new URLSearchParams();
        fd.append('myCloud_action', 'switch_language');
        fd.append('myCloud_key', myCloudState.key);
        fd.append('myCloud_token', window.myCloudCsrfToken); // Ensure robust global access
        fd.append('lang', code);

        fetch('', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(resp) {
            if (resp.status === 'OK' && resp.strings) {
                // Update global Strings
                window.myCloud_LANG = resp.strings;
                // Apply RTL/LTR
                var rtlLangs = ['ar', 'fa', 'he', 'ur'];
                var dir = rtlLangs.includes(code) ? 'rtl' : 'ltr';
                document.getElementById('myCloudContainer').setAttribute('dir', dir);
				
                var helpOverlay = document.getElementById('myCloudModalOverlay');
                var helpModal = document.getElementById('myCloudModal');
                if (helpOverlay && helpOverlay.style.display !== 'none' && helpModal && helpModal.classList.contains('ce-help-modal') && typeof window.myCloudOpenHelp === 'function') {
                    window.myCloudOpenHelp();
                }
                
                // Advance
                currentStep++;
                renderStep();
            }
        });
    };

    window.myCloudFraSetUI = function(type) {
        // Reset to default first (clean slate)
        devices.forEach(function(dev) {
            myCloudState.settings[dev] = Object.assign({}, myCloudDefaultSettings[dev]);
        });

        if (type === 'windows') {
            applyToAll({
                showCheckboxes: false,
                showHoverMenu: false,
                singleClick: false // Ensure this is off for Windows style
            });
        } else if (type === 'cloud') {
            applyToAll({
                singleClick: true,
                showCheckboxes: true, // Force on for Single Click
                showHoverMenu: true
            });
        }
        // 'mixed' uses defaults, so nothing more to do

        currentStep++;
        renderStep();
    };

    window.myCloudFraSetNet = function(speed) {
        if (speed === 'fast') {
            applyToAll({
                clickToPreview: true,
                showListThumbnails: true,
                warnLargePreview: false
            });
        } else if (speed === 'medium') {
            applyToAll({
                clickToPreview: true,
                showListThumbnails: false,
                warnLargePreview: true
            });
        } else if (speed === 'slow') {
            applyToAll({
                clickToPreview: false,
                showListThumbnails: false,
                warnLargePreview: true
            });
        }

        renderStep();
    };

    window.myCloudFraFinish = function() {
        // Mark as completed
        myCloudState.settings.fra_completed = true;

        // Save to Server
        myCloudSaveSettings();

        // Apply immediately
        myCloudApplySettings();

        // Close UI
        var overlay = document.getElementById('myCloudFRA');
        overlay.classList.remove('visible');
        setTimeout(function() { overlay.style.display = 'none'; }, 300);
        
        // Refresh UI
        myCloudRenderUI();
    }

})();
</script>