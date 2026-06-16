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
	"search_global"
	"last_indexed"
	"use_index"
	"home"
	"transfer"
	"create"
	"organize"
	"modes"
	"tools"
	"utilities"
	"admin"
)

declare -A TRANSLATIONS

TRANSLATIONS[en]="Search entire cloud|Last updated:|Use Full-Text Index|Home|Transfer|Create & Edit|Organize|Workspaces|Tools|Utilities|Admin"
TRANSLATIONS[de]="Gesamte Cloud durchsuchen|Zuletzt aktualisiert:|Volltextindex verwenden|Start|Übertragen|Erstellen & Bearbeiten|Organisieren|Arbeitsbereiche|Werkzeuge|Dienstprogramme|Admin"
TRANSLATIONS[es]="Buscar en toda la nube|Última actualización:|Usar índice de texto completo|Inicio|Transferir|Crear y editar|Organizar|Espacios de trabajo|Herramientas|Utilidades|Admin"
TRANSLATIONS[fr]="Rechercher dans tout le cloud|Dernière mise   jour :|Utiliser l'indexation plein texte|Accueil|Transférer|Créer et modifier|Organiser|Espaces de travail|Outils|Utilitaires|Admin"
TRANSLATIONS[it]="Cerca in tutto il cloud|Ultimo aggiornamento:|Usa indice full-text|Home|Trasferisci|Crea e Modifica|Organizza|Aree di lavoro|Strumenti|Utilit |Amministratore"
TRANSLATIONS[pt]="Pesquisar em toda a nuvem|Última atualização:|Usar índice de texto completo|Início|Transferir|Criar e Editar|Organizar|Espaços de trabalho|Ferramentas|Utilitários|Admin"
TRANSLATIONS[ru]="Искать по всему облаку|Последнее обновление:|Использовать полнотекстовый индекс|Главная|Передача|Создать и изменить|Организовать| абочие области|Инструменты|Утилиты|Админ"
TRANSLATIONS[tr]="Tüm bulutta ara|Son güncelleme:|Tam Metin Dizinini Kullan|Ana Sayfa|Aktar|Oluştur ve Düzenle|Düzenle|Çalışma Alanları|Araçlar|İzlenceler|Yönetici"
TRANSLATIONS[zh-cn]="搜索整个云端|最后更新：|使用全文索引|主页| 输|创建与编辑|组织|工作区|工具|实用程序|管理员"
TRANSLATIONS[ja]="クラウド全体を検索|最終更新：|フルテキストインデックスを使用|ホー |転送|作成と編集|整理|ワークスペース|ツール|ユーティリティ|管理"
TRANSLATIONS[ko]="클라우드  체 검색|마지막 업데이트:| 체 텍스트 색인 사용|홈| 송|만들기 및 편집| 리|작업 공간|도구| 틸리티|관리자"
TRANSLATIONS[ar]="البحث في السحابة بأكملها|آخر تحديث:|استخدام فهرس النص الكامل|الرئيسية|نقل|إنشاء وتعديل|تنظيم|مساحات العمل|أدوات|أدوات مساعدة|إدارة"
TRANSLATIONS[fa]="جستجو در کل ابری|آخرین بروزرسانی:|استفاده از نمایه متن کامل|خانه|انتقال|ایجاد و ویرایش|سازماندهی|فضاهای کاری|ابزارها|برنامه‌های کاربردی|مدیریت"
TRANSLATIONS[hi]="पूरे क्लाउड में खोजें|अंतिम अपडेट:|पूर्ण-पा  अनुक्रमणिका का उपयोग करें|होम|ट्रांसफर|बनाएं और संपादित करें|व्यवस्थित करें|कार्यस्थान|उपकरण|उपयोगिताएँ|व्यवस्थापक"
TRANSLATIONS[vi]="Tìm kiếm to n bộ đám mây|Cập nhật lần cuối:|Sử dụng chỉ mục to n văn|Trang chủ|Chuyển giao|Tạo & Chỉnh sửa|Tổ chức|Không gian l m việc|Công cụ|Tiện ích|Quản trị viên"
TRANSLATIONS[uk]="Шукати по всій хмарі|Останнє оновлення:|Використовувати повнотекстовий індекс|Головна|Передача|Створити та редагувати|Організувати| обочі області|Інструменти|Утиліти|Адмін"
TRANSLATIONS[bar]="Ganze Cloud durchsuacha|Zletzt aktualisiert:|Volltextindex heanema|Start|Übertrong|Erstein & Bearbatn|Organisiern|Arbatsbereiche|Werkzeig|Dienstprogramme|Admin"
TRANSLATIONS[hes]="Ganze Cloud durschsuche|Zuletzt aktualisiert:|Volltextindex nutze|Start|Überdraache|Erstelle & Bearweide|Organisiere|Arbeidsbereische|Werkszeusch|Dienstprogramme|Admin"
TRANSLATIONS[lb]="Ganz Cloud duerchsichen|Zulescht aktualiséiert:|Volltextindex benotzen|Start|Iwwerdroen|Erstellen & Änneren|Organiséieren|Aarbechtsberäicher|Handwierksgeschir|Déngschtprogrammer|Admin"
TRANSLATIONS[pcm]="Search entire cloud|Last updated:|Use Full-Text Index|Home|Transfer|Create & Edit|Organize|Workspaces|Tools|Utilities|Admin"

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