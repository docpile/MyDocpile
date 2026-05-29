#!/bin/bash


# ==============================================================================
# CONFIGURATION
# ==============================================================================

TARGET_ANCHOR="link_expires"

# ==============================================================================
# ARRAYS
# ==============================================================================

# 2. The Keys
KEYS=(
	"resend"
	"rebuild_cache"
	"toggle_folders"
)

declare -A TRANSLATIONS

TRANSLATIONS[en]="Resend|Reload all mails|Toggle Folders"
TRANSLATIONS[de]="Erneut senden|Alle E-Mails neu laden|Ordner umschalten"
TRANSLATIONS[es]="Reenviar|Recargar todos los correos|Alternar carpetas"
TRANSLATIONS[fr]="Renvoyer|Recharger tous les e-mails|Basculer les dossiers"
TRANSLATIONS[it]="Invia di nuovo|Ricarica tutte le email|Alterna cartelle"
TRANSLATIONS[pt]="Reenviar|Recarregar todos os e-mails|Alternar pastas"
TRANSLATIONS[ru]="Отправить снова|Перезагрузить все письма|Переключить папки"
TRANSLATIONS[tr]="Yeniden Gönder|Tüm postaları yeniden yükle|Klasörleri aç/kapat"
TRANSLATIONS[zh-cn]="重新发送|重新 载所有邮件|切换文件夹"
TRANSLATIONS[ja]="再送|すべてのメールを再読み込み|フォルダーの切り替え"
TRANSLATIONS[ko]="재 송|모  메일 다시 불러오기|폴더  환"
TRANSLATIONS[ar]="إعادة إرسال|إعادة تحميل جميع الرسائل|تبديل المجلدات"
TRANSLATIONS[fa]="ارسال مجدد|بارگذاری مجدد تمام ایمیل‌ها|تغییر وضعیت پوشه‌ها"
TRANSLATIONS[hi]="पुनः भेजें|सभी मेल को पुनः लोड करें|फोल्डर्स टॉगल करें"
TRANSLATIONS[vi]="Gửi lại|Tải lại tất cả email|Chuyển đổi thư mục"
TRANSLATIONS[uk]="Надіслати знову|Перезавантажити всі листи|Перемикання папок"
TRANSLATIONS[bar]="Noamoi schicka|Alle E-Mails neu lodn|Ordner umschoidn"
TRANSLATIONS[hes]="Nochemal schigge|Alle E-Mails neu lade|Ordner umschalte"
TRANSLATIONS[lb]="Nach eng Kéier schécken|All E-Maile nei lueden|Ordner wiesselen"
TRANSLATIONS[pcm]="Send am again|Reload all mails|Toggle folders"


#
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