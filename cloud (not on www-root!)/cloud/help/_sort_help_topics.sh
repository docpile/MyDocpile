#!/bin/bash
# ----------------------------------------------------------------



sort_help_topics() {
    local target_dir="$1"

    # Define your required sequence here
    local ids=(
        "intro"          # First Steps
        "ui"             # The Interface
        "mail_tab"       # The Webmail Tab
        "usage_guide"    # Devices & Speed
        "basic"          # Controls & Selection
        "commander"      # Commander View
        "office_mode"    # Document Management
        "online_office"  # Online Office
        "text_editor"    # Code & Text Editor
        "settings"       # Dialog: Settings
        "view"           # Preview & Media
        "search"         # Dialog: Search
        "favorites"      # Favorites & Bookmarks
        "vaults"         # Sichere Tresore (Verschlüsselung)
        "mail_setup"     # Mail Setup
        "mail_security"  # Mail Security
        "prop"           # Dialog: Properties
        "multi_rename"   # Batch Rename
        "accessibility"  # Accessibility & Comfort
        "actions"        # Actions & Conflicts
    )

    # Verify jq is installed
    if ! command -v jq &> /dev/null; then
        echo "Error: jq is required but not installed."
        return 1
    fi

    # Convert the bash array of IDs into a formatted JSON array string
    local json_ids_array
    json_ids_array=$(printf '"%s",' "${ids[@]}" | sed 's/,$//')
    json_ids_array="[${json_ids_array}]"

    # Iterate through all .json files in the target directory
    for file in "$target_dir"/*.json; do
        if [[ -f "$file" ]]; then
            local tmp_file="${file}.tmp"
            
            # Use jq to index the objects by ID, then map them to the new order
            jq --argjson order "$json_ids_array" '
                INDEX(.[] ; .id) as $dict |
                $order | map($dict[.]) | map(select(. != null))
            ' "$file" > "$tmp_file"

            # If the jq command succeeded, replace the original file
            if [[ $? -eq 0 ]]; then
                mv "$tmp_file" "$file"
                echo "Successfully sorted: $file"
            else
                rm -f "$tmp_file"
                echo "Error processing: $file"
            fi
        fi
    done
}

sort_help_topics .