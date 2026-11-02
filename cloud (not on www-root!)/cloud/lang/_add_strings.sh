#!/bin/bash


# ==============================================================================
# CONFIGURATION
# ==============================================================================

TARGET_ANCHOR="obs_pdf_saved"

# ==============================================================================
# ARRAYS
# ==============================================================================


# 2. The Keys
KEYS=(
    "obs_settings_title"
    "obs_tab_general"
    "obs_tab_hidden"
    "obs_hidden_desc"
    "obs_no_subfolders"
    "obs_att_folder_path"
    "obs_new_file_loc"
    "obs_loc_root"
    "obs_loc_current"
    "obs_loc_folder"
    "obs_new_file_path"
    "obs_strict_breaks"
    "obs_show_yaml"
)

declare -A TRANSLATIONS

TRANSLATIONS[en]="Obsidian Settings:|General|Hidden Folders|Select folders to exclude from the tree and file list views:|No subfolders found|Attachment Folder Path:|New File Location:|Vault Root|Current Folder|Specific Folder|New File Folder Path:|Strict Line Breaks|Show YAML Frontmatter"
TRANSLATIONS[de]="Obsidian Einstellungen:|Allgemein|Versteckte Ordner|Wählen Sie Ordner aus, die von der Baum- und Dateilistenansicht ausgeschlossen werden sollen:|Keine Unterordner gefunden|Pfad für Anhänge:|Speicherort für neue Dateien:|Vault-Stammverzeichnis|Aktueller Ordner|Bestimmter Ordner|Ordnerpfad für neue Dateien:|Strikte Zeilenumbrüche|YAML Frontmatter anzeigen"
TRANSLATIONS[es]="Configuración de Obsidian:|General|Carpetas Ocultas|Seleccione las carpetas a excluir de las vistas de árbol y lista de archivos:|No se encontraron subcarpetas|Ruta de la carpeta de adjuntos:|Ubicación de nuevo archivo:|Raíz del Vault|Carpeta actual|Carpeta específica|Ruta de la carpeta de nuevo archivo:|Saltos de línea estrictos|Mostrar YAML Frontmatter"
TRANSLATIONS[fr]="Paramètres d'Obsidian :|Général|Dossiers cachés|Sélectionnez les dossiers   exclure des vues d'arborescence et de liste des fichiers :|Aucun sous-dossier trouvé|Chemin du dossier des pièces jointes :|Emplacement du nouveau fichier :|Racine du coffre|Dossier actuel|Dossier spécifique|Chemin du nouveau fichier :|Sauts de ligne stricts|Afficher YAML Frontmatter"
TRANSLATIONS[it]="Impostazioni Obsidian:|Generale|Cartelle Nascoste|Seleziona le cartelle da escludere dalle visualizzazioni ad albero e a elenco:|Nessuna sottocartella trovata|Percorso cartella allegati:|Posizione nuovo file:|Radice del Vault|Cartella corrente|Cartella specifica|Percorso cartella nuovo file:|Interruzioni di riga rigide|Mostra YAML Frontmatter"
TRANSLATIONS[pt]="Configurações do Obsidian:|Geral|Pastas Ocultas|Selecione as pastas a serem excluídas das exibições de árvore e lista de arquivos:|Nenhuma subpasta encontrada|Caminho da pasta de anexos:|Local do novo arquivo:|Raiz do Vault|Pasta atual|Pasta específica|Caminho da pasta de novo arquivo:|Quebras de linha estritas|Mostrar YAML Frontmatter"
TRANSLATIONS[ru]="Настройки Obsidian:|Общие|Скрытые папки|Выберите папки для исключения из просмотра дерева и списка файлов:|Подпапки не найдены|Путь к папке вложений:| асположение нового файла:|Корень хранилища|Текущая папка|Определенная папка|Путь к папке нового файла:|Строгие разрывы строк|Показывать YAML Frontmatter"
TRANSLATIONS[tr]="Obsidian Ayarları:|Genel|Gizli Klasörler|Ağaç ve dosya listesi görünümlerinden dışlanacak klasörleri seçin:|Alt klasör bulunamadı|Ek Klasör Yolu:|Yeni Dosya Konumu:|Kasa Kökü|Geçerli Klasör|Belirli Klasör|Yeni Dosya Klasör Yolu:|Katı Satır Sonları|YAML Frontmatter'ı Göster"
TRANSLATIONS[zh-cn]="Obsidian 设置：|常规|隐藏文件夹|选择要从 状和文件列表视图中排除的文件夹：|未找到子文件夹|附件文件夹路径：|新文件位置：|仓库 目录|当前文件夹|特定文件夹|新文件文件夹路径：|严 换行|显示 YAML Frontmatter"
TRANSLATIONS[ja]="Obsidian 設定:|一般|非表示のフォルダ|ツリーとファイルリストビューから除外するフォルダを選択してく さい:|サブフォルダが見つかりません|添付ファイルフォルダのパス:|新規ファイルの 所:|Vault のルート|現在のフォルダ|特定のフォルダ|新規ファイルのフォルダパス:|厳密な改行|YAML Frontmatter を表示"
TRANSLATIONS[ko]="Obsidian 설 :|일반|숨김 폴더|트리 및 파일 목록 보기에서  외  폴더를  택하세요:|하위 폴더를 찾을 수 없음|첨부 폴더 경로:|새 파일 위치:|보관소 루트|현재 폴더|특  폴더|새 파일 폴더 경로:|엄격한 줄바꿈|YAML Frontmatter 표시"
TRANSLATIONS[ar]="إعدادات Obsidian:|عام|المجلدات المخفية|حدد المجلدات المراد استبعادها من طرق عرض الشجرة وقائمة الملفات:|لم يتم العثور على مجلدات فرعية|مسار مجلد المرفقات:|موقع الملف الجديد:|جذر المخزن|المجلد الحالي|مجلد محدد|مسار مجلد الملف الجديد:|فواصل أسطر صارمة|إظهار YAML Frontmatter"
TRANSLATIONS[fa]="تنظیمات Obsidian:|عمومی|پوشه‌های مخفی|پوشه‌هایی را برای حذف از نمای درخت و لیست فایل انتخاب کنید:|هیچ زیرپوشه‌ای یافت نشد|مسیر پوشه پیوست‌ها:|مکان فایل جدید:|ریشه مخزن|پوشه فعلی|پوشه خاص|مسیر پوشه فایل جدید:|شکست‌های خط سخت‌گیرانه|نمایش YAML Frontmatter"
TRANSLATIONS[hi]="Obsidian सेटिंग्स:|सामान्य|छिपे हुए फ़ोल्डर|ट्री और फ़ाइल सूची दृश्यों से बाहर करने के लिए फ़ोल्डर चुनें:|कोई उप-फ़ोल्डर नहीं मिला|अनुलग्नक फ़ोल्डर पथ:|नया फ़ाइल स्थान:|वॉल्ट रूट|वर्तमान फ़ोल्डर|विशिष्ट फ़ोल्डर|नया फ़ाइल फ़ोल्डर पथ:|सख्त लाइन ब्रेक|YAML Frontmatter दिखाएं"
TRANSLATIONS[vi]="C i đặt Obsidian:|Chung|Thư mục bị ẩn|Chọn các thư mục để loại trừ khỏi chế độ xem cây v  danh sách tệp:|Không tìm thấy thư mục con n o|Đường dẫn thư mục đính kèm:|Vị trí tệp mới:|Gốc Vault|Thư mục hiện tại|Thư mục cụ thể|Đường dẫn thư mục tệp mới:|Ngắt dòng nghiêm ngặt|Hiển thị YAML Frontmatter"
TRANSLATIONS[uk]="Налаштування Obsidian:|Загальні|Приховані папки|Виберіть папки для виключення з перегляду дерева та списку файлів:|Підпапки не знайдено|Шлях до папки вкладень:| озташування нового файлу:|Корінь сховища|Поточна папка|Конкретна папка|Шлях до папки нового файлу:|Строгі розриви рядків|Показувати YAML Frontmatter"
TRANSLATIONS[bar]="Obsidian Eistellunga:|Allgemein|Versteckte Ordnar|Wähl Ordnar aus, de wo vom Baam und da Dateilistn ausgschlossn wern soin:|Koane Unterordnar gfundn|Pfad für Anhäng:|Speicherort für neie Datein:|Vault-Wurzl|Aktueller Ordnar|Bestimmter Ordnar|Ordnarpfad für neie Datein:|Strikte Zeilnumbrüch|YAML Frontmatter ozoang"
TRANSLATIONS[hes]="Obsidian Eistellunge:|Allgemein|Versteckte Ordner|Wähl Ordner aus, die von de Baum- und Dateiliste-Asicht ausgeschlosse wern solle:|Kaa Unnerordner gefunne|Pfad fer Anhäng:|Speicherort fer neie Dateie:|Vault-Wurzel|Aktueller Ordner|Bestimmter Ordner|Ordnerpfad fer neie Dateie:|Strikte Zeilenumbrüch|YAML Frontmatter aazeige"
TRANSLATIONS[lb]="Obsidian Astellungen:|Allgemeng|Verstoppt Classeuren|Wielt Classeuren aus, déi aus der Bam- an Dateilëscht-Usicht ausgeschloss solle ginn:|Keng Ënnerclasseuren fonnt|Pad fir Unhänk:|Späicheruert fir nei Dateien:|Vault-Wuerzel|Aktuelle Classeur|Spezifesche Classeur|Classeurpad fir nei Dateien:|Strikt Zeilenëmbréch|YAML Frontmatter weisen"
TRANSLATIONS[pcm]="Obsidian Settings:|General|Hide Folders|Choose folders wey you wan hide from di tree and file list:|No subfolders dey|Attachment Folder Path:|New File Location:|Vault Root|Current Folder|Specific Folder|New File Folder Path:|Strict Line Breaks|Show YAML Frontmatter"


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