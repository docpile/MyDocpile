#!/bin/bash


# ==============================================================================
# CONFIGURATION
# ==============================================================================

TARGET_ANCHOR="obs_font_size_default"

# ==============================================================================
# ARRAYS
# ==============================================================================


KEYS=(
    "obs_onenote_pages"
    "obs_onenote_new_page"
    "obs_onenote_root_sec"
    "obs_onenote_new_sec"
    "obs_tip_pages_pane"
    "obs_tip_math"
    "obs_paste_opts"
    "obs_paste_web_mirror"
    "obs_paste_web_mirror_tip"
    "obs_mirroring_images"
    "obs_outline_grip_tip"
    "obs_click_remove_tag"
    "obs_math_title"
    "obs_math_input_lbl"
    "obs_math_solution"
    "obs_math_steps"
    "obs_math_ready_latex"
    "obs_math_insert_latex"
    "obs_math_insert_graph"
)

declare -A TRANSLATIONS

TRANSLATIONS[en]="Pages|Add Page in Section|General|New Section (Folder)|Show/Hide OneNote Pages Pane|Smart Math Solver & 2D Function Grapher (or type expr=)|Paste Options:|Formatted + Local Images|Keep formatting, download & attach remote images locally into vault, and cite source|Downloading & attaching web images locally...|Double-click to collapse/expand sub-items|Click to remove|OneNote Math Assistant & 2D Grapher|Equation or Function (e.g. 2x + 5 = 15, sin(x), x^2 - 4):|Solution:|Steps:|Ready to insert as LaTeX expression.|Insert LaTeX ($...$)|Insert 2D Graph"
TRANSLATIONS[de]="Seiten|Seite in Abschnitt hinzufügen|Allgemein|Neuer Abschnitt (Ordner)|OneNote-Seitenleiste ein-/ausblenden|Mathe-Assistent & 2D-Funktionsplotter (oder Ausdruck= tippen)|Einfügeoptionen:|Formatiert + Lokale Bilder|Formatierung behalten, Web-Bilder lokal im Vault speichern und Quelle zitieren|Web-Bilder werden lokal heruntergeladen und angehängt...|Doppelklick zum Ein-/Ausklappen von Unterpunkten|Klicken zum Entfernen|OneNote Mathe-Assistent & 2D-Plotter|Gleichung oder Funktion (z. B. 2x + 5 = 15, sin(x), x^2 - 4):|Lösung:|Schritte:|Bereit zum Einfügen als LaTeX-Ausdruck.|LaTeX einfügen ($...$)|2D-Graph einfügen"
TRANSLATIONS[es]="Páginas|Añadir página en la sección|General|Nueva sección (Carpeta)|Mostrar/Ocultar panel de páginas OneNote|Asistente matemático y graficador 2D (o escriba expr=)|Opciones de pegado:|Con formato + Imágenes locales|Mantener formato, descargar imágenes web localmente en la bóveda y citar fuente|Descargando y adjuntando imágenes web localmente...|Doble clic para contraer/expandir subelementos|Clic para quitar|Asistente matemático OneNote y graficador 2D|Ecuación o función (ej. 2x + 5 = 15, sin(x), x^2 - 4):|Solución:|Pasos:|Listo para insertar como expresión LaTeX.|Insertar LaTeX ($...$)|Insertar gráfico 2D"
TRANSLATIONS[fr]="Pages|Ajouter une page dans la section|Général|Nouvelle section (Dossier)|Afficher/Masquer le volet des pages OneNote|Solveur mathématique & Grapheur 2D (ou tapez expr=)|Options de collage :|Mis en forme + Images locales|Conserver la mise en forme, télécharger les images web localement et citer la source|Téléchargement et ajout local des images web...|Double-cliquez pour réduire/développer les sous-éléments|Cliquer pour supprimer|Assistant mathématique OneNote & Grapheur 2D|Équation ou fonction (ex. 2x + 5 = 15, sin(x), x^2 - 4) :|Solution :|Étapes :|Prêt   insérer en tant qu'expression LaTeX.|Insérer LaTeX ($...$)|Insérer graphique 2D"
TRANSLATIONS[it]="Pagine|Aggiungi pagina nella sezione|Generale|Nuova sezione (Cartella)|Mostra/Nascondi riquadro pagine OneNote|Risolutore matematico e grafico 2D (o digita espr=)|Opzioni incolla:|Formattato + Immagini locali|Mantieni formattazione, scarica immagini web localmente nel vault e cita la fonte|Download e allegato locale delle immagini web...|Doppio clic per comprimere/espandere i sottoelementi|Clicca per rimuovere|Assistente matematico OneNote e grafico 2D|Equazione o funzione (es. 2x + 5 = 15, sin(x), x^2 - 4):|Soluzione:|Passaggi:|Pronto per l'inserimento come espressione LaTeX.|Inserisci LaTeX ($...$)|Inserisci grafico 2D"
TRANSLATIONS[pt]="Páginas|Adicionar página na seção|Geral|Nova seção (Pasta)|Mostrar/Ocultar painel de páginas OneNote|Solucionador matemático e gráfico 2D (ou digite expr=)|Opções de colagem:|Formatado + Imagens locais|Manter formatação, baixar imagens da web localmente no cofre e citar fonte|Baixando e anexando imagens da web localmente...|Clique duas vezes para recolher/expandir subitens|Clique para remover|Assistente de Matemática OneNote e Gráfico 2D|Equação ou função (ex. 2x + 5 = 15, sin(x), x^2 - 4):|Solução:|Passos:|Pronto para inserir como expressão LaTeX.|Inserir LaTeX ($...$)|Inserir gráfico 2D"
TRANSLATIONS[ru]="Страницы|Добавить страницу в раздел|Общее|Новый раздел (Папка)|Показать/скрыть панель страниц OneNote|Математический решатель и 2D-графики (или введите выр=)|Параметры вставки:|С форматированием + Локальные изображения|Сохранить форматирование, скачать веб-изображения локально в хранилище и указать источник|Загрузка и локальное прикрепление веб-изображений...|Двойной клик для сворачивания/разворачивания подпунктов|Нажмите для удаления|Математический помощник OneNote и 2D-графики|Уравнение или функция (напр., 2x + 5 = 15, sin(x), x^2 - 4):| ешение:|Шаги:|Готово к вставке как выражение LaTeX.|Вставить LaTeX ($...$)|Вставить 2D-график"
TRANSLATIONS[tr]="Sayfalar|Bölüme Sayfa Ekle|Genel|Yeni Bölüm (Klasör)|OneNote Sayfalar Bölmesini Göster/Gizle|Akıllı Matematik Çözücü ve 2D Grafikler (veya ifade= yazın)|Yapıştırma Seçenekleri:|Biçimli + Yerel Resimler|Biçimlendirmeyi koru, web resimlerini kasaya yerel olarak indir ve kaynağı belirt|Web resimleri yerel olarak indiriliyor ve ekleniyor...|Alt öğeleri daraltmak/genişletmek için çift tıklayın|Kaldırmak için tıklayın|OneNote Matematik Asistanı ve 2D Grafikler|Denklem veya Fonksiyon (örn. 2x + 5 = 15, sin(x), x^2 - 4):|Çözüm:|Adımlar:|LaTeX ifadesi olarak eklemeye hazır.|LaTeX Ekle ($...$)|2D Grafik Ekle"
TRANSLATIONS[zh-cn]="页面|在分区中添 页面|常规|新建分区（文件夹）|显示/隐藏 OneNote 页面窗 |智能数学求解器与 2D 函数绘图（或输入 表达式=）|粘贴选项：|保留 式 + 本地化图片|保留 式，将远程网页图片下载到知识库本地并附上来源链接|正在下载并将网页图片存为本地附件...|双击折 /展开子项|点击移除|OneNote 数学助手与 2D 绘图|方程或函数（例如 2x + 5 = 15, sin(x), x^2 - 4）：|解：|步骤：|已准备好作为 LaTeX 表达式插入。|插入 LaTeX ($...$)|插入 2D 图像"
TRANSLATIONS[ja]="ページ|セクションにページを追 |一般|新しいセクション（フォルダ）|OneNote ページウィンドウの表示/非表示|スマート数式ソルバー & 2Dグラフ（または 式= と入力）|貼り付けオプション:|書式付き + 画像をローカル保存|書式を保持し、Web画像を保管庫にローカル保存して出典を記載|Web画像をローカルにダウンロードして添付中...|ダブルクリックでサブ 目を折りたたみ/展開|クリックして削除|OneNote 数式アシスタント & 2Dグラフ|方程式または関数 (例: 2x + 5 = 15, sin(x), x^2 - 4):|解:|ステップ:|LaTeX 数式として挿入できます。|LaTeX を挿入 ($...$)|2D グラフを挿入"
TRANSLATIONS[ko]="페이지|섹션에 페이지 추가|일반|새 섹션 (폴더)|OneNote 페이지 창 표시/숨기기|스마트 수학 풀이 및 2D 함수 그래프 (또는 수식= 입 )|붙여넣기 옵션:|서식  지 + 로컬 이미지  장|서식을  지하  웹 이미지를 보관소에 로컬로 다운로드하여 첨부 및 출처 표시|웹 이미지를 로컬로 다운로드하여 첨부하는 중...|두 번 클릭하여 하위 항목  기/펼치기|클릭하여  거|OneNote 수학 도우미 및 2D 그래프|방 식 또는 함수 (예: 2x + 5 = 15, sin(x), x^2 - 4):|해:|단계:|LaTeX 수식으로 삽입  준비가 되었습니다.|LaTeX 삽입 ($...$)|2D 그래프 삽입"
TRANSLATIONS[ar]="الصفحات|إضافة صفحة في القسم|عام|قسم جديد (مجلد)|إظهار/إخفاء جزء صفحات OneNote|حلال الرياضيات الذكي وراسم الدوال ثنائي الأبعاد (أو اكتب معادلة=)|خيارات اللصق:|منسق + صور محلية|الاحتفاظ بالتنسيق وتنزيل صور الويب محلياً في الخزنة مع ذكر المصدر|جاري تنزيل وإرفاق صور الويب محلياً...|انقر مرتين لطي/توسيع العناصر الفرعية|انقر للإزالة|مساعد الرياضيات في OneNote وراسم 2D|معادلة أو دالة (مثل 2x + 5 = 15, sin(x), x^2 - 4):|الحل:|الخطوات:|جاهز للإدراج كصيغة LaTeX.|إدراج LaTeX ($...$)|إدراج رسم بياني 2D"
TRANSLATIONS[fa]="صفحه‌ها|افزودن صفحه در بخش|عمومی|بخش جدید (پوشه)|نمایش/پنهان کردن قاب صفحه‌های OneNote|حل‌کننده هوشمند ریاضی و رسم نمودار دوبعدی (یا عبارت= بنویسید)|گزینه‌های جای‌گذاری:|قالب‌بندی‌شده + تصاویر محلی|حفظ قالب‌بندی، دانلود تصاویر وب به صورت محلی در مخزن و درج منبع|در حال دانلود و پیوست محلی تصاویر وب...|برای جمع/باز کردن زیرمجموعه‌ها دوبار کلیک کنید|برای حذف کلیک کنید|دستیار ریاضی OneNote و نمودار دوبعدی|معادله یا تابع (مثلاً 2x + 5 = 15, sin(x), x^2 - 4):|پاسخ:|مراحل:|آماده درج به عنوان عبارت LaTeX.|درج LaTeX ($...$)|درج نمودار دوبعدی"
TRANSLATIONS[hi]="पृष् |अनुभाग में पृष्  जोड़ें|सामान्य|नया अनुभाग (फ़ोल्डर)|OneNote पृष्  पैन दिखाएँ/छिपाएँ|स्मार्ट गणित सॉल्वर और 2D ग्राफ़र (या expr= टाइप करें)|पेस्ट विकल्प:|फ़ॉर्मेटेड + लोकल चित्र|फ़ॉर्मेटिंग रखें, वेब चित्रों को वॉल्ट में लोकल रूप से डाउनलोड करें और स्रोत जोड़ें|वेब चित्र लोकल रूप से डाउनलोड और संलग्न किए जा रहे हैं...|उप-आइटम संक्षिप्त/विस्तारित करने के लिए डबल-क्लिक करें|हटाने के लिए क्लिक करें|OneNote गणित सहायक और 2D ग्राफ़र|समीकरण या फ़ंक्शन (उदा. 2x + 5 = 15, sin(x), x^2 - 4):|हल:|चरण:|LaTeX व्यंजक के रूप में डालने के लिए तैयार।|LaTeX डालें ($...$)|2D ग्राफ़ डालें"
TRANSLATIONS[vi]="Trang|Thêm trang v o mục|Chung|Mục mới (Thư mục)|Hiện/Ẩn ngăn trang OneNote|Bộ giải toán thông minh & Vẽ đồ thị 2D (hoặc gõ biểu_thức=)|Tùy chọn dán:|Giữ định dạng + Lưu ảnh cục bộ|Giữ định dạng, tải ảnh web về kho lưu trữ cục bộ v  trích dẫn nguồn|Đang tải v  đính kèm ảnh web cục bộ...|Nhấp đúp để thu gọn/mở rộng mục con|Nhấp để xóa|Trợ lý Toán học OneNote & Đồ thị 2D|Phương trình hoặc h m số (VD: 2x + 5 = 15, sin(x), x^2 - 4):|Lời giải:|Các bước:|Sẵn s ng chèn dưới dạng biểu thức LaTeX.|Chèn LaTeX ($...$)|Chèn đồ thị 2D"
TRANSLATIONS[uk]="Сторінки|Додати сторінку в розділ|Загальне|Новий розділ (Папка)|Показати/приховати панель сторінок OneNote|Математичний розв'язувач і 2D-графіки (або введіть вир=)|Параметри вставки:|З форматуванням + Локальні зображення|Зберегти форматування, завантажити веб-зображення локально у сховище та вказати джерело|Завантаження та локальне прикріплення веб-зображень...|Двічі клацніть для згортання/розгортання підпунктів|Натисніть для видалення|Математичний помічник OneNote і 2D-графіки| івняння або функція (напр., 2x + 5 = 15, sin(x), x^2 - 4):| озв'язок:|Кроки:|Готово до вставки як вираз LaTeX.|Вставити LaTeX ($...$)|Вставити 2D-графік"
TRANSLATIONS[bar]="Seitn|Seitn im Abschnitt dazua|Oigemein|Neia Abschnitt (Ordna)|OneNote-Seitnleistn ozoagn/ausblendn|Mathe-Assistent & 2D-Plotter (oda Ausdruck= tippn)|Eifügeoptionen:|Formatiert + Lokale Buidln|Formatierung bhoitn, Web-Buidln lokal im Vault speichan und Quejn ogebn|Web-Buidln wean lokal obagladn und oghengt...|Doppeklick zum Ei-/Ausklappn vo Untapunktn|Klickn zum Wegdoa|OneNote Mathe-Assistent & 2D-Plotter|Gleichung oda Funktion (z. B. 2x + 5 = 15, sin(x), x^2 - 4):|Lösung:|Schritt:|Fertig zum Eifügn ois LaTeX-Ausdruck.|LaTeX eifügn ($...$)|2D-Graph eifügn"
TRANSLATIONS[hes]="Seide|Seid im Abschnitt dezu|Allgemein|Neier Abschnitt (Ordner)|OneNote-Seideleisd aazeige/ausblende|Mathe-Assistent & 2D-Plotter (odder Ausdruck= tippe)|Eifügeoptione:|Formadiert + Lokale Bilder|Formadierung behalde, Web-Bilder lokal im Vault speichere un Quell aagewwe|Web-Bilder wern lokal runnergelade un aagehängt...|Doppelklick zum Ei-/Ausklappe vun Unnerpunkde|Klicke zum Entferne|OneNote Mathe-Assistent & 2D-Plotter|Gleichung odder Funkdion (z. B. 2x + 5 = 15, sin(x), x^2 - 4):|Lösung:|Schridde:|Ferdisch zum Eifüge als LaTeX-Ausdruck.|LaTeX eifüge ($...$)|2D-Graph eifüge"
TRANSLATIONS[lb]="Säiten|Säit an der Sektioun derbäisetzen|Allgemeng|Nei Sektioun (Dossier)|OneNote-Säitepanel uweisen/ausblenden|Mathe-Assistent & 2D-Plotter (oder Ausdrock= tippen)|Aféieroptiounen:|Formatéiert + Lokal Biller|Formatéierung behalen, Web-Biller lokal am Vault späicheren a Quell zitéieren|Web-Biller ginn lokal erofgelueden an ugehaangen...|Duebelklick fir Ënnerpunkten an-/auszeklappen|Klicke fir ze läschen|OneNote Mathe-Assistent & 2D-Plotter|Equatioun oder Funktioun (z. B. 2x + 5 = 15, sin(x), x^2 - 4):|Léisung:|Schrëtt:|Bereet fir als LaTeX-Ausdrock anzeféieren.|LaTeX aféieren ($...$)|2D-Graph aféieren"
TRANSLATIONS[pcm]="Page dem|Put Page Inside Section|General|New Section (Folder)|Show/Hide OneNote Page Corner|Smart Math Solver & 2D Graph (or type expr=)|Paste Option dem:|Keep Style + Local Picture|Keep style, download web picture put inside vault locally, and show source|E dey download web picture put inside vault locally...|Double-click to fold/open sub-item dem|Click to comot am|OneNote Math Helper & 2D Graph|Equation or Function (e.g. 2x + 5 = 15, sin(x), x^2 - 4):|Answer:|Step dem:|Ready to put inside as LaTeX.|Put LaTeX ($...$)|Put 2D Graph"

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