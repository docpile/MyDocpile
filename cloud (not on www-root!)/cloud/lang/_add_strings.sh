#!/bin/bash


# ==============================================================================
# CONFIGURATION
# ==============================================================================

TARGET_ANCHOR="obs_math_insert_graph"

# ==============================================================================
# ARRAYS
# ==============================================================================


KEYS=(
    "badge_color"
    "badge_color_default"
    "reset_default"
    "all_mailboxes"
    "unassigned_mailbox"
    "source_mailbox"
    "source_mailbox_all"
    "source_mailbox_hint"
)

declare -A TRANSLATIONS

TRANSLATIONS[en]="SmartBox Badge Color|Default|Reset to Default|All Mailboxes|Unassigned|Mailbox|All Mailboxes (Not restricted)|When set, this address is only suggested when composing from this mailbox."
TRANSLATIONS[de]="SmartBox-Badge-Farbe|Standard|Auf Standard zurücksetzen|Alle Postfächer|Nicht zugewiesen|Postfach|Alle Postfächer (Nicht eingeschränkt)|Wenn festgelegt, wird diese Adresse nur beim Verfassen aus diesem Postfach vorgeschlagen."
TRANSLATIONS[es]="Color de insignia SmartBox|Predeterminado|Restablecer predeterminado|Todos los buzones|Sin asignar|Buzón|Todos los buzones (Sin restricción)|Si se establece, esta dirección solo se sugerirá al redactar desde este buzón."
TRANSLATIONS[fr]="Couleur du badge SmartBox|Par défaut|Réinitialiser par défaut|Toutes les boîtes|Non attribué|Boîte aux lettres|Toutes les boîtes (Sans restriction)|Une fois définie, cette adresse n'est suggérée que lors de la rédaction depuis cette boîte."
TRANSLATIONS[it]="Colore badge SmartBox|Predefinito|Ripristina predefinito|Tutte le caselle|Non assegnata|Casella di posta|Tutte le caselle (Senza restrizioni)|Se impostato, questo indirizzo viene suggerito solo quando si scrive da questa casella."
TRANSLATIONS[pt]="Cor do emblema SmartBox|Padrão|Redefinir para o padrão|Todas as caixas|Não atribuída|Caixa de correio|Todas as caixas (Sem restrição)|Quando definido, este endereço só é sugerido ao redigir a partir desta caixa."
TRANSLATIONS[ru]="Цвет значка SmartBox|По умолчанию|Сбросить по умолчанию|Все ящики|Не назначен|Почтовый ящик|Все ящики (Без ограничений)|Если указан, этот адрес предлагается только при отправке из этого ящика."
TRANSLATIONS[tr]="SmartBox Rozet Rengi|Varsayılan|Varsayılana Sıfırla|Tüm Posta Kutuları|Atanmamış|Posta Kutusu|Tüm Posta Kutuları (Kısıtlama yok)|Ayarlandığında, bu adres yalnızca bu posta kutusundan ileti yazarken önerilir."
TRANSLATIONS[zh-cn]="SmartBox 徽 颜色|默认|恢复默认|所有邮箱|未分配|邮箱|所有邮箱（不受限制）|设置后，仅在从此邮箱撰写邮件时建议此地址。"
TRANSLATIONS[ja]="SmartBox バッジの色|デフォルト|デフォルトに戻す|すべてのメールボックス|未割り当て|メールボックス|すべてのメールボックス（制限なし）|設定すると、このメールボックスから作成するときのみこのアドレスが候補に表示されます。"
TRANSLATIONS[ko]="SmartBox 배지 색상|기본값|기본값으로 재설 |모  메일함| 당되지 않음|메일함|모  메일함 ( 한 없음)|설 하면 이 메일함에서 작성  때만 이 주소가  안됩니다."
TRANSLATIONS[ar]="لون شارة SmartBox|افتراضي|إعادة التعيين للافتراضي|جميع صناديق البريد|غير معين|صندوق البريد|جميع صناديق البريد (غير مقيد)|عند التعيين، يُقترح هذا العنوان فقط عند الإنشاء من صندوق البريد هذا."
TRANSLATIONS[fa]="رنگ نشان SmartBox|پیشفرض|بازنشانی به پیشفرض|همه صندوقها|تعییننشده|صندوق ایمیل|همه صندوقها (بدون محدودیت)|در صورت تنظیم، این نشانی فقط هنگام نوشتن ایمیل از این صندوق پیشنهاد میشود."
TRANSLATIONS[hi]="SmartBox बैज रंग|डिफ़ॉल्ट|डिफ़ॉल्ट पर रीसेट करें|सभी मेलबॉक्स|अनिर्दिष्ट|मेलबॉक्स|सभी मेलबॉक्स (प्रतिबंधित नहीं)|सेट होने पर, यह पता केवल इसी मेलबॉक्स से ईमेल लिखते समय सुझाया जाएगा।"
TRANSLATIONS[vi]="M u huy hiệu SmartBox|Mặc định|Đặt lại mặc định|Tất cả hộp thư|Chưa gán|Hộp thư|Tất cả hộp thư (Không giới hạn)|Khi được đặt, địa chỉ n y chỉ được gợi ý khi soạn thư từ hộp thư n y."
TRANSLATIONS[uk]="Колір значка SmartBox|За замовчуванням|Скинути за замовчуванням|Усі скриньки|Не призначено|Поштова скринька|Усі скриньки (Без обмежень)|Якщо вказано, ця адреса пропонується лише під час написання з цієї скриньки."
TRANSLATIONS[bar]="SmartBox-Badge-Farb|Standard|Auf Standard zrucksetzn|Olle Postfachl|Ned zuagwiesn|Postfachl|Olle Postfachl (Ned eigschränkt)|Wenn eigstellt, werd de Adress bloß beim Schreibn aus dem Postfachl vorgschlogn."
TRANSLATIONS[hes]="SmartBox-Badge-Farb|Standard|Uff Standard zuricksetze|Alle Postfächer|Net zugewiese|Postfach|Alle Postfächer (Net eigeschränkt)|Wann eigestellt, werd die Adress nur beim Schreiwe aus dem Postfach vorgeschlache."
TRANSLATIONS[lb]="SmartBox-Badge-Faarf|Standard|Op Standard zrécksetzen|All Postfächer|Net zougewisen|Postfach|All Postfächer (Net ageschränkt)|Wann agestallt, gëtt dës Adress nëmme beim Schreiwen aus dësem Postfach proposéiert."
TRANSLATIONS[pcm]="SmartBox Badge Color|Default|Reset to Default|All Mailbox dem|No Assign|Mailbox|All Mailbox dem (No restriction)|If you set am, dis address go only show when you dey write from dis mailbox."

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