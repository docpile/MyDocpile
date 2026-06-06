import re

filepath = "cloud (not on www-root!)/cloud/core.ui.toolbar_menues.php"
with open(filepath, 'r') as f:
    content = f.read()

# Replace the createRibbonGroup implementation to enforce a much stronger "Office Ribbon" style via inline styles.

search_block = """    const createRibbonGroup = function(label, subActions, tooltip, customRenderer) {
        const group = document.createElement('div');
        group.className = 'ce-ribbon-group';
        group.style.display = 'flex';
        group.style.flexDirection = 'column';
        group.style.alignItems = 'center';
        group.style.justifyContent = 'space-between';
        group.style.borderRight = '1px solid var(--border-medium, rgba(0,0,0,0.12))';
        group.style.padding = '0 4px';
        group.style.margin = '2px 0';
        group.title = tooltip;

        const btnsContainer = document.createElement('div');
        btnsContainer.className = 'ce-ribbon-group-btns';
        btnsContainer.style.display = 'flex';
        btnsContainer.style.flexDirection = 'row';
        btnsContainer.style.alignItems = 'flex-start';
        btnsContainer.style.flexGrow = '1';

        subActions.forEach(act => {
            const btn = customRenderer ? customRenderer(act) : createBtn(act);
            btn.className = 'ce-ribbon-btn';
            btnsContainer.appendChild(btn);
        });

        const labelDiv = document.createElement('div');
        labelDiv.className = 'ce-ribbon-group-label';
        labelDiv.style.fontSize = '11px';
        labelDiv.style.color = 'var(--text-secondary, #605e5c)';
        labelDiv.style.textAlign = 'center';
        labelDiv.style.marginTop = '2px';
        labelDiv.style.marginBottom = '2px';
        labelDiv.style.whiteSpace = 'nowrap';
        labelDiv.textContent = label;

        group.appendChild(btnsContainer);
        group.appendChild(labelDiv);

        return group;
    };"""

replace_block = """    const createRibbonGroup = function(label, subActions, tooltip, customRenderer) {
        const group = document.createElement('div');
        group.className = 'ce-office-ribbon-group';
        group.style.display = 'flex';
        group.style.flexDirection = 'column';
        group.style.justifyContent = 'space-between';
        group.style.borderRight = '1px solid var(--border-medium, rgba(150, 150, 150, 0.3))';
        group.style.padding = '2px 6px';
        group.style.margin = '0 2px';
        group.style.height = '100%';
        group.style.minHeight = '72px';
        group.title = tooltip;

        const btnsContainer = document.createElement('div');
        btnsContainer.className = 'ce-office-ribbon-group-btns';
        btnsContainer.style.display = 'flex';
        btnsContainer.style.flexDirection = 'row';
        btnsContainer.style.gap = '2px';
        btnsContainer.style.flexGrow = '1';
        btnsContainer.style.alignItems = 'flex-start';

        subActions.forEach(act => {
            const btn = customRenderer ? customRenderer(act) : createBtn(act);

            // Force MS Office Ribbon Button styles
            btn.className = 'ce-office-ribbon-btn';
            btn.style.display = 'flex';
            btn.style.flexDirection = 'column';
            btn.style.alignItems = 'center';
            btn.style.justifyContent = 'flex-start';
            btn.style.background = 'transparent';
            btn.style.border = '1px solid transparent';
            btn.style.borderRadius = '4px';
            btn.style.padding = '4px 6px';
            btn.style.minWidth = '52px';
            btn.style.height = '54px';
            btn.style.cursor = 'pointer';
            btn.style.transition = 'all 0.1s ease';

            // Adjust icon and text inside button
            const iconSpan = btn.querySelector('.myCloudIcon');
            if (iconSpan) {
                iconSpan.style.display = 'flex';
                iconSpan.style.alignItems = 'center';
                iconSpan.style.justifyContent = 'center';
                iconSpan.style.width = '24px';
                iconSpan.style.height = '24px';
                iconSpan.style.marginBottom = '2px';
                // Force SVG sizing
                const svg = iconSpan.querySelector('svg');
                if (svg) {
                    svg.style.width = '24px';
                    svg.style.height = '24px';
                }
            }

            const textSpans = btn.querySelectorAll('span:not(.myCloudIcon)');
            textSpans.forEach(span => {
                span.style.fontSize = '11px';
                span.style.lineHeight = '1.1';
                span.style.textAlign = 'center';
                span.style.fontWeight = '400';
                span.style.whiteSpace = 'normal';
                span.style.color = 'var(--text-primary, #333)';
                span.style.maxWidth = '60px';
                span.style.display = 'block';
                span.style.marginTop = '2px';
            });

            // Hover effect
            btn.onmouseenter = function() {
                btn.style.background = 'var(--hover-bg-light, rgba(0, 120, 212, 0.1))';
                btn.style.borderColor = 'var(--accent-primary, rgba(0, 120, 212, 0.3))';
            };
            btn.onmouseleave = function() {
                btn.style.background = 'transparent';
                btn.style.borderColor = 'transparent';
            };

            btnsContainer.appendChild(btn);
        });

        const labelDiv = document.createElement('div');
        labelDiv.className = 'ce-office-ribbon-group-label';
        labelDiv.style.fontSize = '11px';
        labelDiv.style.color = 'var(--text-secondary, #666)';
        labelDiv.style.textAlign = 'center';
        labelDiv.style.marginTop = '2px';
        labelDiv.style.marginBottom = '0px';
        labelDiv.style.fontWeight = '600';
        labelDiv.textContent = label;

        group.appendChild(btnsContainer);
        group.appendChild(labelDiv);

        return group;
    };"""

content = content.replace(search_block, replace_block)

with open(filepath, 'w') as f:
    f.write(content)
