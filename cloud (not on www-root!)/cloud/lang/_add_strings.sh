#!/bin/bash


# ==============================================================================
# CONFIGURATION
# ==============================================================================

TARGET_ANCHOR="unencrypted"

# ==============================================================================
# ARRAYS
# ==============================================================================

# 2. The Keys
KEYS=(
	"templates"
	"add_template"
	"no_templates"
	"insert"
	"edit_template"
	"template_body"
	"enter_name"
)

declare -A TRANSLATIONS

TRANSLATIONS[en]="Templates|New Template|No templates found.|Insert|Edit Template|Body|Please enter a name"
TRANSLATIONS[de]="Vorlagen|Neue Vorlage|Keine Vorlagen gefunden.|Einfügen|Vorlage bearbeiten|Textkörper|Bitte einen Namen eingeben"
TRANSLATIONS[es]="Plantillas|Nueva plantilla|No se encontraron plantillas.|Insertar|Editar plantilla|Cuerpo|Por favor, introduzca un nombre"
TRANSLATIONS[fr]="Modèles|Nouveau modèle|Aucun modèle trouvé.|Insérer|Modifier le modèle|Corps|Veuillez entrer un nom"
TRANSLATIONS[it]="Modelli|Nuovo modello|Nessun modello trovato.|Inserisci|Modifica modello|Corpo|Inserisci un nome"
TRANSLATIONS[pt]="Modelos|Novo modelo|Nenhum modelo encontrado.|Inserir|Editar modelo|Corpo|Por favor, insira um nome"
TRANSLATIONS[ru]="Шаблоны|Новый шаблон|Шаблоны не найдены.|Вставить| едактировать шаблон|Тело|Пожалуйста, введите имя"
TRANSLATIONS[tr]="Şablonlar|Yeni Şablon|Şablon bulunamadı.|Ekle|Şablonu Düzenle|Gövde|Lütfen bir isim girin"
TRANSLATIONS[zh-cn]="模板|新模板|未找到模板。|插入|编辑模板|正文|请输入名称"
TRANSLATIONS[ja]="テンプレート|新しいテンプレート|テンプレートが見つかりません。|挿入|テンプレートを編集|本文|名前を入力してく さい"
TRANSLATIONS[ko]="템플릿|새 템플릿|템플릿을 찾을 수 없습니다.|삽입|템플릿 편집|본문|이름을 입 하세요"
TRANSLATIONS[ar]="القوالب|قالب جديد|لم يتم العثور على قوالب.|إدراج|تعديل القالب|النص|الرجاء إدخال اسم"
TRANSLATIONS[fa]="الگوها|الگوی جدید|هیچ الگویی یافت نشد.|درج|ویرایش الگو|بدنه|لطفاً یک نام وارد کنید"
TRANSLATIONS[hi]="टेम्प्लेट|नया टेम्प्लेट|कोई टेम्प्लेट नहीं मिला।|डालें|टेम्प्लेट संपादित करें|बॉडी|कृपया एक नाम दर्ज करें"
TRANSLATIONS[vi]="Mẫu|Mẫu mới|Không tìm thấy mẫu n o.|Chèn|Chỉnh sửa mẫu|Nội dung|Vui lòng nhập tên"
TRANSLATIONS[uk]="Шаблони|Новий шаблон|Шаблонів не знайдено.|Вставити| едагувати шаблон|Тіло|Будь ласка, введіть ім'я"
TRANSLATIONS[bar]="Vorlagn|Neie Vorlog|Koane Vorlagn gfundn.|Einfügn|Vorlog beorbatn|Text|Bitte an Nåma eigm"
TRANSLATIONS[hes]="Vorlaache|Neie Vorlaach|Kaa Vorlaache gefunne.|Einfieche|Vorlaach beawwede|Text|Bidde en Name oigewwe"
TRANSLATIONS[lb]="Virlagen|Nei Virlag|Keng Virlagen fonnt.|Afügen|Virlag änneren|Text|Gitt w.e.g. en Numm an"
TRANSLATIONS[pcm]="Templates|New Template|No templates found.|Insert|Edit Template|Body|Abeg enter name"#
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