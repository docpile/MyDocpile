#!/bin/bash


# ==============================================================================
# CONFIGURATION
# ==============================================================================

TARGET_ANCHOR="toggle_folders"

# ==============================================================================
# ARRAYS
# ==============================================================================

# 2. The Keys
KEYS=(
	"btn_bulk_zip"
	"btn_bulk_files"
	"sel_items"
	"err_no_files"
	"btn_select_all"
	"btn_deselect_all"
	"msg_prep_zip"
)

declare -A TRANSLATIONS

TRANSLATIONS[en]="Download ZIP|Download Files|%s selected|No files selected|Select All|Deselect All|Preparing ZIP file... Please wait."
TRANSLATIONS[de]="Als ZIP laden|Dateien laden|%s ausgewählt|Keine Dateien ausgewählt|Alle auswählen|Auswahl aufheben|ZIP-Datei wird vorbereitet... Bitte warten."
TRANSLATIONS[es]="Descargar ZIP|Descargar archivos|%s seleccionado|No se seleccionaron archivos|Seleccionar todo|Deseleccionar todo|Preparando archivo ZIP... Por favor espere."
TRANSLATIONS[fr]="Télécharger le ZIP|Télécharger les fichiers|%s sélectionné|Aucun fichier sélectionné|Tout sélectionner|Tout désélectionner|Préparation du fichier ZIP... Veuillez patienter."
TRANSLATIONS[it]="Scarica ZIP|Scarica file|%s selezionato|Nessun file selezionato|Seleziona tutto|Deseleziona tutto|Preparazione file ZIP... Attendi prego."
TRANSLATIONS[pt]="Baixar ZIP|Baixar arquivos|%s selecionado|Nenhum arquivo selecionado|Selecionar tudo|Desmarcar tudo|Preparando arquivo ZIP... Por favor, aguarde."
TRANSLATIONS[ru]="Скачать ZIP|Скачать файлы|%s выбрано|Файлы не выбраны|Выбрать все|Отменить выбор|Подготовка ZIP-файла... Пожалуйста, подождите."
TRANSLATIONS[tr]="ZIP İndir|Dosyaları İndir|%s seçildi|Dosya seçilmedi|Tümünü Seç|Seçimi Kaldır|ZIP dosyası hazırlanıyor... Lütfen bekleyin."
TRANSLATIONS[zh-cn]="下载 ZIP|下载文件|已选择 %s 个|未选择文件|全选|取消全选|正在准备 ZIP 文件... 请稍候。"
TRANSLATIONS[ja]="ZIPをダウンロード|ファイルをダウンロード|%s 個選択|ファイルが選択されていません|すべて選択|選択解除|ZIPファイルを準備中... お待ちく さい。"
TRANSLATIONS[ko]="ZIP 다운로드|파일 다운로드|%s  택됨| 택된 파일 없음|모두  택| 택 해 |ZIP 파일 준비 중... 기다  주세요."
TRANSLATIONS[ar]="تنزيل ZIP|تنزيل الملفات|%s محدد|لم يتم تحديد ملفات|تحديد الكل|إلغاء التحديد|جاري تحضير ملف ZIP... يرجى الانتظار."
TRANSLATIONS[fa]="دانلود ZIP|دانلود فایل‌ها|%s انتخاب شد|فایلی انتخاب نشده|انتخاب همه|لغو انتخاب|در حال آماده‌سازی فایل ZIP... لطفاً صبر کنید."
TRANSLATIONS[hi]="ZIP डाउनलोड करें|फाइलें डाउनलोड करें|%s चयनित|कोई फाइल नहीं चुनी गई|सभी चुनें|चयन हटाएं|ZIP फाइल तैयार की जा रही है... कृपया प्रतीक्षा करें।"
TRANSLATIONS[vi]="Tải xuống ZIP|Tải xuống tệp|%s đã chọn|Không có tệp n o được chọn|Chọn tất cả|Bỏ chọn tất cả|Đang chuẩn bị tệp ZIP... Vui lòng chờ."
TRANSLATIONS[uk]="Завантажити ZIP|Завантажити файли|%s вибрано|Файли не вибрано|Вибрати всі|Скасувати вибір|Підготовка ZIP-файлу... Будь ласка, зачекайте."
TRANSLATIONS[bar]="ZIP oabruafa|Dateien oabruafa|%s ausgwählt|Koane Datein ausgwählt|Olloe auswähln|Auswahl aufhebn|ZIP-Datei wiad vorberait... Bitte wortn."
TRANSLATIONS[hes]="ZIP lade|Dateie lade|%s ausgewählt|Kei Dateie ausgewählt|Alles auswähle|Auswahl aufhewe|ZIP-Datei wird vorberait... Bitte warte."
TRANSLATIONS[lb]="ZIP eroflueden|Dateien eroflueden|%s ausgewielt|Keng Dateie ausgewielt|Alles auswielen|Auswiel ophiewen|ZIP-Datei gëtt virbereet... W.e.g. waarden."
TRANSLATIONS[pcm]="Download ZIP|Download Files|%s selected|No files selected|Select All|Deselect All|Preparing ZIP file... Please wait."
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