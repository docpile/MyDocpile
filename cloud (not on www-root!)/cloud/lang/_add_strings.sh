#!/bin/bash


# ==============================================================================
# CONFIGURATION
# ==============================================================================

TARGET_ANCHOR="ie_save_copy"

# ==============================================================================
# ARRAYS
# ==============================================================================

# 2. The Keys
KEYS=(
    "pin_qa"
    "qa_remove_tip"
    "qa_already_pinned"
    "qa_limit_title"
    "qa_limit_msg"
)

declare -A TRANSLATIONS

TRANSLATIONS[en]="Pin to Quick Access|Right click to remove|Already pinned.|Limit Reached|You can pin a maximum of 5 items."
TRANSLATIONS[de]="An Schnellzugriff anheften|Rechtsklick zum Entfernen|Bereits angeheftet.|Limit erreicht|Sie können maximal 5 Elemente anheften."
TRANSLATIONS[es]="Anclar a acceso rápido|Clic derecho para quitar|Ya está anclado.|Límite alcanzado|Puedes anclar un máximo de 5 elementos."
TRANSLATIONS[fr]="Épingler   l'accès rapide|Clic droit pour retirer|Déj  épinglé.|Limite atteinte|Vous pouvez épingler un maximum de 5 éléments."
TRANSLATIONS[it]="Aggiungi ad Accesso rapido|Clic destro per rimuovere|Gi  aggiunto.|Limite raggiunto|Puoi aggiungere un massimo di 5 elementi."
TRANSLATIONS[pt]="Afixar no Acesso Rápido|Clique com o botão direito para remover|Já afixado.|Limite atingido|Pode afixar um máximo de 5 itens."
TRANSLATIONS[ru]="Закрепить в быстром доступе|Правый клик для удаления|Уже закреплено.|Лимит достигнут|Можно закрепить не более 5 элементов."
TRANSLATIONS[tr]="Hızlı Erişime Sabitle|Kaldırmak için sağ tıklayın|Zaten sabitlenmiş.|Sınıra Ulaşıldı|En fazla 5 öğe sabitleyebilirsiniz."
TRANSLATIONS[zh-cn]="固定到快速访问|右键单击以移除|已固定。|达到限制|您最多可以固定 5 个项目。"
TRANSLATIONS[ja]="クイックアクセスにピン留め|右クリックで削除|すでにピン留めされています。|制限に達しました|最大5つのアイテ をピン留めできます。"
TRANSLATIONS[ko]=" 른 액세스에   |우클릭하여  거|이미   되었습니다.| 한 도달|최대 5개의 항목만     수 있습니다."
TRANSLATIONS[ar]="تثبيت في الوصول السريع|انقر بزر الماوس الأيمن للإزالة|مثبت بالفعل.|تم بلوغ الحد|يمكنك تثبيت 5 عناصر كحد أقصى."
TRANSLATIONS[fa]="سنجاق به دسترسی سریع|برای حذف راست کلیک کنید|قبلاً سنجاق شده است.|رسیدن به محدودیت|شما می‌توانید حداکثر ۵ مورد را سنجاق کنید."
TRANSLATIONS[hi]="त्वरित पहुँच में पिन करें|हटाने के लिए राइट-क्लिक करें|पहले से ही पिन किया हुआ है।|सीमा पहुँच गई|आप अधिकतम 5 आइटम पिन कर सकते हैं।"
TRANSLATIONS[vi]="Ghim v o Truy cập nhanh|Nhấp chuột phải để xóa|Đã được ghim.|Đã đạt giới hạn|Bạn có thể ghim tối đa 5 mục."
TRANSLATIONS[uk]="Закріпити у швидкому доступі|Правий клік для видалення|Вже закріплено.|Ліміт досягнуто|Можна закріпити не більше 5 елементів."
TRANSLATIONS[bar]="An Schnejzuagriff oheftn|Rechtsklick zum Entferna|Scho ogeheft.|Limit erreicht|Du konnst maximal 5 Elemente oheftn."
TRANSLATIONS[hes]="An de Schnellzugriff ahefte|Rechtsklick zum Entferne|Schun aageheft.|Limit erreicht|Du kannsch maximal 5 Elemente ahefte."
TRANSLATIONS[lb]="Un de Schnellzougrëff upennen|Rietsklick fir ze läschen|Scho festgemaach.|Limit erreecht|Dir kënnt maximal 5 Elementer upennen."
TRANSLATIONS[pcm]="Pin to Quick Access|Right click to remove|E don already pin.|Limit Reached|You fit pin maximum of 5 items."

#
# ==============================================================================
# EXECUTION ENGINE
# ==============================================================================


# ==============================================================================
# SELF-REPAIR PRE-FLIGHT CHECK
# ==============================================================================
# Prevents infinite loops by setting an environment flag during the restart
if [[ -z "$_SELF_REPAIR_ATTEMPTED" ]]; then
    # Check if the script contains hidden UTF-8 non-breaking spaces
    # Check for NBSP, typographic spaces (U+2000-U+200A), zero-width characters (U+200B-U+200F), and BOM
    if grep -qE $'\xC2\xA0|\xA0|\xE2\x80[\x80-\x8F]|\xEF\xBB\xBF' "$0" 2>/dev/null; then
        echo " ️ Invisible/rogue space characters detected in the script."
        echo "🔧 Initiating self-repair..."
        
        # Perl is used for safe, cross-platform in-place replacement (macOS/Linux)
        # 1. Replace NBSP and typographic spaces with standard spaces
        # 2. Strip zero-width formatting characters and Byte Order Mark (BOM)
        perl -pi -e 's/\xC2\xA0/ /g; s/\xA0/ /g; s/\xE2\x80[\x80-\x8A]/ /g; s/\xE2\x80[\x8B-\x8F]//g; s/\xEF\xBB\xBF//g' "$0"
       
        echo "✅ Self-repair complete! Restarting script..."
        export _SELF_REPAIR_ATTEMPTED=1
        exec bash "$0" "$@"
    fi
fi





remove_duplicate_keys() {
    local target_dir="${1:-.}"
    
    echo "Scanning for duplicate keys in $target_dir..."
    
    for file in "$target_dir"/*.php; do
        [[ -f "$file" ]] || continue
        
        # This awk block loads all lines, determines the *last* occurrence of each key,
        # and then prints the lines, skipping over non-last duplicates.
        local duplicates
        duplicates=$(awk '
        BEGIN { removed=0 }
        
        # Pass 1: Read all lines and find the index of the LAST occurrence of each key
        {
            lines[NR] = $0
            if (/^[ \t]*['\''"][^'\''"]+['\''"][ \t]*=>/) {
                split($0, parts, "=>")
                key = parts[1]
                gsub(/^[ \t]*['\''"]|['\''"][ \t]*$/, "", key)
                last_seen[key] = NR
            }
        }
        
        # Pass 2: Output lines, skipping keys that are not at their last_seen index
        END {
            for (i=1; i<=NR; i++) {
                is_duplicate = 0
                line = lines[i]
                
                if (line ~ /^[ \t]*['\''"][^'\''"]+['\''"][ \t]*=>/) {
                    split(line, parts, "=>")
                    key = parts[1]
                    gsub(/^[ \t]*['\''"]|['\''"][ \t]*$/, "", key)
                    
                    if (last_seen[key] != i) {
                        is_duplicate = 1
                        removed++
                    }
                }
                
                if (!is_duplicate) {
                    print line > tmp_file
                }
            }
            print removed
        }
        ' tmp_file="${file}.tmp" "$file")
        
        if [[ "$duplicates" -gt 0 ]]; then
            mv "${file}.tmp" "$file"
            echo "✅ Removed $duplicates duplicate key(s) from $(basename "$file")"
        else
            rm -f "${file}.tmp"
        fi
    done
    
    echo "Done."
}


for lang in "${!TRANSLATIONS[@]}"; do
    file="${lang}.php"
    
    if [[ -f "$file" ]]; then
        IFS='|' read -r -a vals <<< "${TRANSLATIONS[$lang]}"
        
        if [[ ${#KEYS[@]} -ne ${#vals[@]} ]]; then
            echo "❌ Error in $file: Provided ${#vals[@]} translations, but expected ${#KEYS[@]} keys. Skipping."
            continue
        fi

        payload=""
        
        for i in "${!KEYS[@]}"; do
            key="${KEYS[$i]}"
            val="${vals[$i]}"
            val_esc="${val//\'/\\\'}"
            
            # Check if key already exists (handles both single and double quotes)
            if grep -qE "^[ \t]*['\"]${key}['\"][ \t]*=>" "$file"; then
                # Edit value of existing key in-place
            # Edit value of existing key in-place
            KEY="$key" VAL="$val_esc" awk '
            $0 ~ "^[ \t]*[\"\\047]" ENVIRON["KEY"] "[\"\\047][ \t]*=>" {
                match($0, /^[ \t]*/)
                indent = substr($0, RSTART, RLENGTH)
                print indent "\047" ENVIRON["KEY"] "\047 => \047" ENVIRON["VAL"] "\047,"
                next
            }
            { print }
            ' "$file" > "${file}.tmp" && mv "${file}.tmp" "$file"
            else
                # Buffer new key for appending at target anchor
                payload+="    '${key}' => '${val_esc}',"$'\n'
            fi
        done

        # If there are brand new keys missing from the file, inject them at the anchor
        if [[ -n "$payload" ]]; then
            PAYLOAD="$payload" ANCHOR="$TARGET_ANCHOR" awk '
            { print $0 }
            $0 ~ ENVIRON["ANCHOR"] { printf "%s", ENVIRON["PAYLOAD"] }
            ' "$file" > "${file}.tmp" && mv "${file}.tmp" "$file"
        fi
        
        echo "✅ Updated $file"
    else
        echo " ️  Warning: $file not found in the current directory."
    fi
done

echo Removing duplicates now...
remove_duplicate_keys
echo "🎉 All done!"