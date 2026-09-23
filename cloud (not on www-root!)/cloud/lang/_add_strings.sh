#!/bin/bash


# ==============================================================================
# CONFIGURATION
# ==============================================================================

TARGET_ANCHOR="share_not_supported"

# ==============================================================================
# ARRAYS
# ==============================================================================

# 2. The Keys
KEYS=(
    "image_convert"
    "ie_color_light"
    "ie_brightness"
    "ie_contrast"
    "ie_saturation"
    "ie_gamma"
	"ie_hdr"
    "ie_transform_crop"
    "ie_rotate"
    "ie_draw_crop"
    "ie_export_settings"
    "ie_format"
    "ie_quality"
    "ie_compression"
    "ie_color_depth"
    "ie_resize_w"
    "ie_resize_h"
    "ie_auto"
    "ie_reset"
    "ie_save_copy"
)

declare -A TRANSLATIONS

TRANSLATIONS[en]="Image Editor|Color & Light|Brightness|Contrast|Saturation|Gamma|Virtual HDR|Transform & Crop|Rotate|Draw Crop|Export Settings|Format|Quality|Compression|Color Depth|Resize W (opt)|Resize H (opt)|Auto|Reset|Save Copy"
TRANSLATIONS[de]="Bildbearbeitung|Farbe & Licht|Helligkeit|Kontrast|Sättigung|Gamma|Virtuelles HDR|Transformieren & Zuschneiden|Drehen|Rahmen aufziehen|Export-Einstellungen|Format|Qualität|Kompression|Farbtiefe|Breite (opt)|Höhe (opt)|Auto|Zurücksetzen|Kopie speichern"
TRANSLATIONS[es]="Editor de imágenes|Color y Luz|Brillo|Contraste|Saturación|Gamma|HDR Virtual|Transformar y Recortar|Rotar|Dibujar Recorte|Ajustes de Exportación|Formato|Calidad|Compresión|Profundidad de color|Redimensionar W (opc)|Redimensionar H (opc)|Auto|Restablecer|Guardar Copia"
TRANSLATIONS[fr]="Éditeur d'images|Couleur et Lumière|Luminosité|Contraste|Saturation|Gamma|HDR Virtuel|Transformer et Recadrer|Faire pivoter|Tracer le recadrage|Paramètres d'exportation|Format|Qualité|Compression|Profondeur de couleur|Redimensionner L (opt)|Redimensionner H (opt)|Auto|Réinitialiser|Enregistrer une copie"
TRANSLATIONS[it]="Editor di immagini|Colore e Luce|Luminosit |Contrasto|Saturazione|Gamma|HDR Virtuale|Trasforma e Ritaglia|Ruota|Disegna Ritaglio|Impostazioni di Esportazione|Formato|Qualit |Compressione|Profondit  colore|Ridimensiona L (opz)|Ridimensiona A (opz)|Auto|Ripristina|Salva Copia"
TRANSLATIONS[pt]="Editor de imagens|Cor e Luz|Brilho|Contraste|Saturação|Gama|HDR Virtual|Transformar e Cortar|Rodar|Desenhar Corte|Configurações de Exportação|Formato|Qualidade|Compressão|Profundidade de cor|Redimensionar L (opc)|Redimensionar A (opc)|Auto|Redefinir|Salvar Cópia"
TRANSLATIONS[ru]=" едактор изображений|Цвет и Свет|Яркость|Контрастность|Насыщенность|Гамма|Вирт. HDR|Трансформация и Обрезка|Повернуть|Выделить область|Настройки экспорта|Формат|Качество|Сжатие|Глубина цвета|Ширина (опц)|Высота (опц)|Авто|Сброс|Сохранить копию"
TRANSLATIONS[tr]="Görüntü Düzenleyici|Renk ve Işık|Parlaklık|Kontrast|Doygunluk|Gama|Sanal HDR|Dönüştür ve Kırp|Döndür|Kırpma Çiz|Dışa Aktarma Ayarları|Format|Kalite|Sıkıştırma|Renk Derinliği|Genişliği Değiştir (ops)|Yüksekliği Değiştir (ops)|Oto|Sıfırla|Kopyasını Kaydet"
TRANSLATIONS[zh-cn]="图像编辑器|色彩与光线|亮度|对比度|饱和度|伽马|虚拟HDR|变换与裁剪|旋转|绘制裁剪|导出设置| 式|质量|压缩|颜色深度|调整宽度 (可选)|调整高度 (可选)|自动|重置|保存副本"
TRANSLATIONS[ja]="画像エディター|色と光|明るさ|コントラスト|彩度|ガンマ|仮想HDR|変形と切り抜き|回転|切り抜きを描画|エクスポート設定|フォーマット|品質|圧縮|色深度|幅をリサイズ (任意)|高さをリサイズ (任意)|自動|リセット|コピーを保存"
TRANSLATIONS[ko]="이미지 편집기|색상 및 조명|밝기|대비|채도|감마|가상 HDR|변형 및 자르기|회 |자르기 그리기|내보내기 설 |형식|품질|압축|색상 심도|너비 크기 조  ( 택)|높이 크기 조  ( 택)|자동|초기화|사본  장"
TRANSLATIONS[ar]="محرر الصور|اللون والإضاءة|السطوع|التباين|التشبع|جاما|HDR افتراضي|تحويل واقتصاص|تدوير|رسم الاقتصاص|إعدادات التصدير|التنسيق|الجودة|ضغط|عمق اللون|تغيير العرض (اختياري)|تغيير الارتفاع (اختياري)|تلقائي|إعادة تعيين|حفظ نسخة"
TRANSLATIONS[fa]="ویرایشگر تصویر|رنگ و نور|روشنایی|کنتراست|اشباع|گاما|HDR مجازی|تغییر و برش|چرخش|کشیدن برش|تنظیمات خروجی|فرمت|کیفیت|فشرده‌سازی|عمق رنگ|تغییر عرض (اختیاری)|تغییر ارتفاع (اختیاری)|خودکار|بازنشانی|ذخیره یک کپی"
TRANSLATIONS[hi]="चित्र संपादक|रंग और प्रकाश|चमक|कंट्रास्ट|संतृप्ति|गामा|वर्चुअल HDR|ट्रांसफ़ॉर्म और क्रॉप|घुमाएँ|क्रॉप ड्रा करें|निर्यात सेटिंग्स|प्रारूप|गुणवत्ता|संपीड़न|रंग की गहराई|चौड़ाई बदलें (वैकल्पिक)|ऊँचाई बदलें (वैकल्पिक)|ऑटो|रीसेट|प्रतिलिपि सहेजें"
TRANSLATIONS[vi]="Trình chỉnh sửa ảnh|M u sắc & Ánh sáng|Độ sáng|Độ tương phản|Độ bão hòa|Gamma|HDR ảo|Chuyển đổi & Cắt ảnh|Xoay|Vẽ vùng cắt|C i đặt Xuất|Định dạng|Chất lượng|Nén|Độ sâu m u|Đổi kích cỡ Rộng (tùy chọn)|Đổi kích cỡ Cao (tùy chọn)|Tự động|Đặt lại|Lưu bản sao"
TRANSLATIONS[uk]=" едактор зображень|Колір і Світло|Яскравість|Контрастність|Насиченість|Гамма|Вірт. HDR|Трансформація та Обрізка|Повернути|Намалювати обрізку|Налаштування експорту|Формат|Якість|Стиснення|Глибина кольору|Змінити ширину (дод)|Змінити висоту (дод)|Авто|Скинути|Зберегти копію"
TRANSLATIONS[bar]="Buidlbearbatung|Foarb & Liacht|Hejigkeit|Kontrast|Sättigung|Gamma|Virtuells HDR|Transformiern & Zuaschneidn|Drahn|Rahma aufziang|Export-Eistellunga|Format|Qualität|Kompression|Foarbtiafn|Broadn (opt)|Hechn (opt)|Auto|Zrucksetzn|Kopie speichan"
TRANSLATIONS[hes]="Bildbearweidung|Faab & Licht|Helligkeit|Kontrast|Sättigung|Gamma|Virtuells HDR|Transformiern & Zuschniede|Drehe|Rahme uffziehe|Export-Eistellunge|Format|Qualität|Kompression|Faabdiefe|Braad (opt)|Heeh (opt)|Auto|Zuricksetze|Kopie speichere"
TRANSLATIONS[lb]="Bildbeaarbechtung|Faarf & Liicht|Hellegkeet|Kontrast|Sättigung|Gamma|Virtuellen HDR|Transforméieren & Ausschneiden|Dréien|Kader zéien|Export-Astellungen|Format|Qualitéit|Kompressioun|Faarfdéift|Breet (opt)|Héicht (opt)|Auto|Zerécksetzen|Kopie späicheren"
TRANSLATIONS[pcm]="Picture Editor|Color & Light|Brightness|Contrast|Saturation|Gamma|Virtual HDR|Transform & Crop|Turn am|Draw Crop|Export Settings|Format|Quality|Compression|Color Depth|Resize Width (opt)|Resize Height (opt)|Auto|Reset|Save Copy"
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