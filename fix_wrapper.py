import re

filepath = "cloud (not on www-root!)/cloud/core.ui.toolbar_menues.php"
with open(filepath, 'r') as f:
    content = f.read()

# Also we need to make sure the main toolbar when stacked gets a specific styling to look like a ribbon container
# We can do this in the `if (isStacked)` block.

search_block = """    if (isStacked) {
        if (toolsActions.length > 0) {
            toolbar.appendChild(createRibbonGroup(
                myCloud_LANG.view,"""

replace_block = """    if (isStacked) {
        toolbar.style.display = 'flex';
        toolbar.style.flexDirection = 'row';
        toolbar.style.alignItems = 'stretch';
        toolbar.style.background = 'var(--gray-05, #f3f2f1)';
        toolbar.style.padding = '2px 0 0 0';
        toolbar.style.borderBottom = '1px solid var(--border-medium, #e1dfdd)';
        toolbar.style.height = 'auto';
        toolbar.style.minHeight = '76px';
        toolbar.style.overflowX = 'auto';
        toolbar.style.overflowY = 'hidden';

        if (toolsActions.length > 0) {
            toolbar.appendChild(createRibbonGroup(
                myCloud_LANG.view,"""

content = content.replace(search_block, replace_block)

with open(filepath, 'w') as f:
    f.write(content)
