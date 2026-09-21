#!/bin/bash


# ==============================================================================
# CONFIGURATION
# ==============================================================================

TARGET_ANCHOR="unencrypted"

# ==============================================================================
# ARRAYS
# ==============================================================================

# 2. The Keys
# 2. The Keys
KEYS=(
    "set_default_mail"
    "pull_to_refresh"
    "release_to_refresh"
    "prep_sharing"
    "share_not_supported"
)

declare -A TRANSLATIONS

TRANSLATIONS[en]="Set as Default Mail App|Pull to refresh|Release to refresh|Preparing for sharing...|File sharing not supported on this browser."
TRANSLATIONS[de]="Als Standard-Mail-App festlegen|Zieh zum Aktualisieren|Lass los zum Aktualisieren|Bereite zum Teilen vor...|Dateifreigabe wird von diesem Browser nicht unterstützt."
TRANSLATIONS[es]="Establecer como app de correo predeterminada|Desliza para actualizar|Suelta para actualizar|Preparando para compartir...|Compartir archivos no es compatible en este navegador."
TRANSLATIONS[fr]="Définir comme appli de messagerie par défaut|Tire pour actualiser|Relâche pour actualiser|Préparation pour le partage...|Le partage de fichiers n'est pas pris en charge sur ce navigateur."
TRANSLATIONS[it]="Imposta come app di posta predefinita|Tira per aggiornare|Rilascia per aggiornare|Preparazione per la condivisione...|Condivisione file non supportata su questo browser."
TRANSLATIONS[pt]="Definir como app de e-mail padrão|Puxe para atualizar|Solte para atualizar|Preparando para compartilhar...|Compartilhamento de arquivos não suportado neste navegador."
TRANSLATIONS[ru]="Сделать почтовым приложением по умолчанию|Потяни для обновления|Отпусти для обновления|Подготовка к отправке...|Отправка файлов не поддерживается в этом браузере."
TRANSLATIONS[tr]="Varsayılan Posta Uygulaması Yap|Yenilemek için çek|Yenilemek için bırak|Paylaşım için hazırlanıyor...|Dosya paylaşımı bu tarayıcıda desteklenmiyor."
TRANSLATIONS[zh-cn]="设为默认邮件应用|下拉刷新|释放刷新|正在准备分享...|此浏览器不支持文件共享。"
TRANSLATIONS[ja]="デフォルトのメールアプリに設定|引っ張って更新|離して更新|共有の準備中...|このブラウザはファイル共有をサポートしていません。"
TRANSLATIONS[ko]="기본 메일 앱으로 설 |당겨서 새로 침|놓아서 새로 침|공  준비 중...|이 브라우 에서는 파일 공 가 지원되지 않습니다."
TRANSLATIONS[ar]="تعيين كتطبيق البريد الافتراضي|اسحب للتحديث|أفلت للتحديث|جاري التحضير للمشاركة...|مشاركة الملفات غير مدعومة في هذا المتصفح."
TRANSLATIONS[fa]="تنظیم به عنوان برنامه ایمیل پیش‌فرض|برای بروزرسانی بکش|برای بروزرسانی رها کن|در حال آماده‌سازی برای اشتراک‌گذاری...|اشتراک‌گذاری فایل در این مرورگر پشتیبانی نمی‌شود."
TRANSLATIONS[hi]="डिफ़ॉल्ट मेल ऐप सेट करें|रिफ्रेश करने के लिए खींचो|रिफ्रेश करने के लिए छोड़ दो|शेयर करने के लिए तैयार कर रहा है...|इस ब्राउज़र पर फ़ाइल शेयरिंग समर्थित नहीं है।"
TRANSLATIONS[vi]="Đặt l m ứng dụng thư mặc định|Kéo để l m mới|Thả ra để l m mới|Đang chuẩn bị chia sẻ...|Trình duyệt n y không hỗ trợ chia sẻ tệp."
TRANSLATIONS[uk]="Зробити поштовою програмою за замовчуванням|Потягни для оновлення|Відпусти для оновлення|Підготовка до відправки...|Відправка файлів не підтримується у цьому браузері."
TRANSLATIONS[bar]="Ois Standard-Mail-App festlegn|Ziang zum Aktualisiern|Loslassn zum Aktualisiern|Richt zum Teiln her...|Dateifreigabe geht in dem Browser ned."
TRANSLATIONS[hes]="Als Standard-Mail-App festleje|Zieh zum Aktualisiere|Loslasse zum Aktualisiere|Machs bereit zum Teile...|Dateifreigabe geht in dem Browser net."
TRANSLATIONS[lb]="Als Standard-Mail-App festleeën|Zéi fir ze aktualiséieren|Lassloosse fir ze aktualiséieren|Gëtt fir d'Deelen virbereet...|Dateifreigabe gëtt an dësem Browser net ënnerstëtzt."
TRANSLATIONS[pcm]="Set am as default mail app|Draw down to refresh|Leave am to refresh|De prepare to share...|Dis browser no gree make you share files."
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