#!/bin/bash


# ==============================================================================
# CONFIGURATION
# ==============================================================================

TARGET_ANCHOR="obs_show_yaml"

# ==============================================================================
# ARRAYS
# ==============================================================================


KEYS=(
    "paste_plain"
    "paste_fmt"
    "obs_tab_start"
    "obs_tab_insert"
    "obs_tab_view"
    "obs_grp_note"
    "obs_grp_clipboard"
    "obs_grp_font"
    "obs_grp_paragraph"
    "obs_grp_tags"
    "obs_grp_insert"
    "obs_grp_view"
    "obs_grp_output"
    "obs_tip_hist_back"
    "obs_tip_hist_fwd"
    "obs_tip_quicktag"
    "obs_tip_timestamp"
    "obs_tip_fold"
    "obs_section_title"
    "obs_section_body"
    "obs_tag_todo"
    "obs_tag_important"
    "obs_tag_question"
    "obs_tag_idea"
    "obs_tag_warning"
    "obs_insert_timestamp"
    "obs_tbl_row_above"
    "obs_tbl_row_below"
    "obs_tbl_col_left"
    "obs_tbl_col_right"
    "obs_tbl_del_row"
    "obs_tbl_del_col"
    "obs_link_new_note"
)

declare -A TRANSLATIONS

TRANSLATIONS[en]="Paste (Unformatted)|Paste (Keep Formatting)|Start|Insert|View & Export|Note|Clipboard|Font|Paragraph|Tags & Blocks|Insert|View & Export|Export|Previous Note in History (Alt+Left)|Next Note in History (Alt+Right)|OneNote Quick Tag: ⭐ Important (Ctrl+2), ❓ Question (Ctrl+3), 💡 Idea (Ctrl+4),  ️ Warning (Ctrl+5)|Insert Date & Time Stamp (Alt+Shift+D)|Insert Collapsible Outline Section|Collapsible Section|Details...|To-Do Checkbox|Important|Question|Idea / Definition|Critical / Warning|Insert Timestamp (Alt+Shift+D)|Table: Insert Row Above|Table: Insert Row Below|Table: Insert Column Left|Table: Insert Column Right|Table: Delete Row|Table: Delete Column|Link to new note:"
TRANSLATIONS[de]="Einfügen (Unformatiert)|Einfügen (Formatierung behalten)|Start|Einfügen|Ansicht & Export|Notiz|Zwischenablage|Schriftart|Absatz|Tags & Blöcke|Einfügen|Ansicht & Export|Export|Vorherige Notiz im Verlauf (Alt+Links)|Nächste Notiz im Verlauf (Alt+Rechts)|OneNote Schnell-Tag: ⭐ Wichtig (Strg+2), ❓ Frage (Strg+3), 💡 Idee (Strg+4),  ️ Warnung (Strg+5)|Datum- & Zeitstempel einfügen (Alt+Umschalt+D)|Ausklappbaren Gliederungsabschnitt einfügen|Ausklappbarer Abschnitt|Details...|Aufgaben-Kontrollkästchen|Wichtig|Frage|Idee / Definition|Kritisch / Warnung|Zeitstempel einfügen (Alt+Umschalt+D)|Tabelle: Zeile oberhalb einfügen|Tabelle: Zeile unterhalb einfügen|Tabelle: Spalte links einfügen|Tabelle: Spalte rechts einfügen|Tabelle: Zeile löschen|Tabelle: Spalte löschen|Verknüpfung zu neuer Notiz:"
TRANSLATIONS[es]="Pegar (Sin formato)|Pegar (Mantener formato)|Inicio|Insertar|Vista y Exportación|Nota|Portapapeles|Fuente|Párrafo|Etiquetas y Bloques|Insertar|Vista y Exportación|Exportar|Nota anterior en el historial (Alt+Izquierda)|Nota siguiente en el historial (Alt+Derecha)|Etiqueta rápida: ⭐ Importante (Ctrl+2), ❓ Pregunta (Ctrl+3), 💡 Idea (Ctrl+4),  ️ Advertencia (Ctrl+5)|Insertar marca de fecha y hora (Alt+Mayús+D)|Insertar sección plegable|Sección plegable|Detalles...|Casilla de tareas|Importante|Pregunta|Idea / Definición|Crítico / Advertencia|Insertar marca de tiempo (Alt+Mayús+D)|Tabla: Insertar fila arriba|Tabla: Insertar fila abajo|Tabla: Insertar columna a la izquierda|Tabla: Insertar columna a la derecha|Tabla: Eliminar fila|Tabla: Eliminar columna|Enlazar a nota nueva:"
TRANSLATIONS[fr]="Coller (Sans mise en forme)|Coller (Conserver la mise en forme)|Accueil|Insertion|Affichage & Export|Note|Presse-papiers|Police|Paragraphe|Balises & Blocs|Insertion|Affichage & Export|Exporter|Note précédente dans l'historique (Alt+Gauche)|Note suivante dans l'historique (Alt+Droite)|Balise rapide : ⭐ Important (Ctrl+2), ❓ Question (Ctrl+3), 💡 Idée (Ctrl+4),  ️ Avertissement (Ctrl+5)|Insérer la date et l'heure (Alt+Maj+D)|Insérer une section pliable|Section pliable|Détails...|Case   cocher|Important|Question|Idée / Définition|Critique / Avertissement|Insérer l'horodatage (Alt+Maj+D)|Tableau : Insérer une ligne au-dessus|Tableau : Insérer une ligne en dessous|Tableau : Insérer une colonne   gauche|Tableau : Insérer une colonne   droite|Tableau : Supprimer la ligne|Tableau : Supprimer la colonne|Lier vers une nouvelle note :"
TRANSLATIONS[it]="Incolla (Senza formattazione)|Incolla (Mantieni formattazione)|Home|Inserisci|Vista ed Esporta|Nota|Appunti|Carattere|Paragrafo|Tag e Blocchi|Inserisci|Vista ed Esporta|Esporta|Nota precedente nella cronologia (Alt+Sinistra)|Nota successiva nella cronologia (Alt+Destra)|Tag rapido: ⭐ Importante (Ctrl+2), ❓ Domanda (Ctrl+3), 💡 Idea (Ctrl+4),  ️ Avviso (Ctrl+5)|Inserisci data e ora (Alt+Maiusc+D)|Inserisci sezione comprimibile|Sezione comprimibile|Dettagli...|Casella di controllo|Importante|Domanda|Idea / Definizione|Critico / Avviso|Inserisci timestamp (Alt+Maiusc+D)|Tabella: Inserisci riga sopra|Tabella: Inserisci riga sotto|Tabella: Inserisci colonna a sinistra|Tabella: Inserisci colonna a destra|Tabella: Elimina riga|Tabella: Elimina colonna|Collega a nuova nota:"
TRANSLATIONS[pt]="Colar (Sem formatação)|Colar (Manter formatação)|Início|Inserir|Vista e Exportação|Nota|Área de Transferência|Fonte|Parágrafo|Tags e Blocos|Inserir|Vista e Exportação|Exportar|Nota anterior no histórico (Alt+Esquerda)|Próxima nota no histórico (Alt+Direita)|Tag rápida: ⭐ Importante (Ctrl+2), ❓ Pergunta (Ctrl+3), 💡 Ideia (Ctrl+4),  ️ Aviso (Ctrl+5)|Inserir data e hora (Alt+Shift+D)|Inserir seção recolhível|Seção recolhível|Detalhes...|Caixa de tarefas|Importante|Pergunta|Ideia / Definição|Crítico / Aviso|Inserir carimbo de data/hora (Alt+Shift+D)|Tabela: Inserir linha acima|Tabela: Inserir linha abaixo|Tabela: Inserir coluna   esquerda|Tabela: Inserir coluna   direita|Tabela: Excluir linha|Tabela: Excluir coluna|Vincular a nova nota:"
TRANSLATIONS[ru]="Вставить (без форматирования)|Вставить (сохранить форматирование)|Главная|Вставка|Вид и экспорт|Заметка|Буфер обмена|Шрифт|Абзац|Теги и блоки|Вставка|Вид и экспорт|Экспорт|Предыдущая заметка в истории (Alt+Влево)|Следующая заметка в истории (Alt+Вправо)|Быстрый тег: ⭐ Важно (Ctrl+2), ❓ Вопрос (Ctrl+3), 💡 Идея (Ctrl+4),  ️ Внимание (Ctrl+5)|Вставить дату и время (Alt+Shift+D)|Вставить сворачиваемый раздел|Сворачиваемый раздел|Подробности...|Флажок задачи|Важно|Вопрос|Идея / Определение|Критично / Внимание|Вставить отметку времени (Alt+Shift+D)|Таблица: Вставить строку выше|Таблица: Вставить строку ниже|Таблица: Вставить столбец слева|Таблица: Вставить столбец справа|Таблица: Удалить строку|Таблица: Удалить столбец|Ссылка на новую заметку:"
TRANSLATIONS[tr]="Yapıştır (Biçimsiz)|Yapıştır (Biçimlendirmeyi Koru)|Giriş|Ekle|Görünüm ve Dışa Aktar|Not|Pano|Yazı Tipi|Paragraf|Etiketler ve Bloklar|Ekle|Görünüm ve Dışa Aktar|Dışa Aktar|Geçmişteki Önceki Not (Alt+Sol)|Geçmişteki Sonraki Not (Alt+Sağ)|Hızlı Etiket: ⭐ Önemli (Ctrl+2), ❓ Soru (Ctrl+3), 💡 Fikir (Ctrl+4),  ️ Uyarı (Ctrl+5)|Tarih ve Saat Damgası Ekle (Alt+Shift+D)|Daraltılabilir Bölüm Ekle|Daraltılabilir Bölüm|Ayrıntılar...|Görev Onay Kutusu|Önemli|Soru|Fikir / Tanım|Kritik / Uyarı|Zaman Damgası Ekle (Alt+Shift+D)|Tablo: Üste Satır Ekle|Tablo: Alta Satır Ekle|Tablo: Sola Sütun Ekle|Tablo: Sağa Sütun Ekle|Tablo: Satırı Sil|Tablo: Sütunu Sil|Yeni nota bağlantı:"
TRANSLATIONS[zh-cn]="粘贴（  式）|粘贴（保留 式）|开始|插入|视图与导出|笔记|剪贴板|字体|段落| 签与块|插入|视图与导出|导出|历史记录中的上一篇笔记 (Alt+Left)|历史记录中的下一篇笔记 (Alt+Right)|快速 签：⭐ 重要 (Ctrl+2)、❓ 问题 (Ctrl+3)、💡 想法 (Ctrl+4)、 ️ 警告 (Ctrl+5)|插入日期与时间戳 (Alt+Shift+D)|插入可折  节|可折  节|详情...|待办复选框|重要|问题|想法 / 定义|严重 / 警告|插入时间戳 (Alt+Shift+D)|表 ：在上方插入行|表 ：在下方插入行|表 ：在左侧插入列|表 ：在右侧插入列|表 ： 除行|表 ： 除列|链接到新笔记："
TRANSLATIONS[ja]="貼り付け（書式なし）|貼り付け（書式を保持）|ホー |挿入|表示とエクスポート|ノート|クリップボード|フォント|段落|タグとブロック|挿入|表示とエクスポート|エクスポート|履歴の前のノート (Alt+左)|履歴の次のノート (Alt+右)|クイックタグ: ⭐ 重要 (Ctrl+2)、❓ 質問 (Ctrl+3)、💡 アイデア (Ctrl+4)、 ️ 警告 (Ctrl+5)|日時スタンプを挿入 (Alt+Shift+D)|折りたたみセクションを挿入|折りたたみセクション|詳細...|ToDoチェックボックス|重要|質問|アイデア / 定義|重要 / 警告|タイ スタンプを挿入 (Alt+Shift+D)|表: 上に行を挿入|表: 下に行を挿入|表: 左に列を挿入|表: 右に列を挿入|表: 行を削除|表: 列を削除|新規ノートへリンク:"
TRANSLATIONS[ko]="붙여넣기 (서식 없음)|붙여넣기 (서식  지)|홈|삽입|보기 및 내보내기|노트|클립보드|글꼴|단락|태그 및 블록|삽입|보기 및 내보내기|내보내기|기록의 이  노트 (Alt+왼쪽)|기록의 다음 노트 (Alt+오른쪽)| 른 태그: ⭐ 중요 (Ctrl+2), ❓ 질문 (Ctrl+3), 💡 아이디어 (Ctrl+4),  ️ 경  (Ctrl+5)| 짜 및 시간 스탬프 삽입 (Alt+Shift+D)| 을 수 있는 섹션 삽입| 을 수 있는 섹션|세부  보...|  일 체크박스|중요|질문|아이디어 /  의|치명  / 경 |타임스탬프 삽입 (Alt+Shift+D)|표: 위에 행 삽입|표: 아래에 행 삽입|표: 왼쪽에 열 삽입|표: 오른쪽에 열 삽입|표: 행 삭 |표: 열 삭 |새 노트에 링크:"
TRANSLATIONS[ar]="لصق (بدون تنسيق)|لصق (مع الاحتفاظ بالتنسيق)|البداية|إدراج|عرض وتصدير|ملاحظة|الحافظة|الخط|فقرة|العلامات والكتل|إدراج|عرض وتصدير|تصدير|الملاحظة السابقة في السجل (Alt+Left)|الملاحظة التالية في السجل (Alt+Right)|علامة سريعة: ⭐ مهم (Ctrl+2)، ❓ سؤال (Ctrl+3)، 💡 فكرة (Ctrl+4)،  ️ تحذير (Ctrl+5)|إدراج طابع التاريخ والوقت (Alt+Shift+D)|إدراج قسم قابل للطي|قسم قابل للطي|التفاصيل...|مربع مهام|مهم|سؤال|فكرة / تعريف|حرج / تحذير|إدراج طابع زمني (Alt+Shift+D)|جدول: إدراج صف لأعلى|جدول: إدراج صف لأسفل|جدول: إدراج عمود لليسار|جدول: إدراج عمود لليمين|جدول: حذف الصف|جدول: حذف العمود|رابط لملاحظة جديدة:"
TRANSLATIONS[fa]="جای‌گذاری (بدون قالب‌بندی)|جای‌گذاری (حفظ قالب‌بندی)|شروع|درج|نما و خروجی|یادداشت|کلیپ‌بورد|قلم|پاراگراف|برچسب‌ها و بلوک‌ها|درج|نما و خروجی|خروجی|یادداشت قبلی در تاریخچه (Alt+Left)|یادداشت بعدی در تاریخچه (Alt+Right)|برچسب سریع: ⭐ مهم (Ctrl+2)، ❓ سوال (Ctrl+3)، 💡 ایده (Ctrl+4)،  ️ هشدار (Ctrl+5)|درج مهر تاریخ و زمان (Alt+Shift+D)|درج بخش جمع‌شونده|بخش جمع‌شونده|جزئیات...|کادر انجام کار|مهم|سوال|ایده / تعریف|بحرانی / هشدار|درج مهر زمانی (Alt+Shift+D)|جدول: درج سطر در بالا|جدول: درج سطر در پایین|جدول: درج ستون در چپ|جدول: درج ستون در راست|جدول: حذف سطر|جدول: حذف ستون|پیوند به یادداشت جدید:"
TRANSLATIONS[hi]="पेस्ट करें (बिना फ़ॉर्मेटिंग)|पेस्ट करें (फ़ॉर्मेटिंग रखें)|प्रारंभ|इन्सर्ट करें|दृश्य और निर्यात|नोट|क्लिपबोर्ड|फ़ॉन्ट|पैराग्राफ़|टैग और ब्लॉक|इन्सर्ट करें|दृश्य और निर्यात|निर्यात|इतिहास में पिछला नोट (Alt+Left)|इतिहास में अगला नोट (Alt+Right)|त्वरित टैग: ⭐ महत्वपूर्ण (Ctrl+2), ❓ प्रश्न (Ctrl+3), 💡 विचार (Ctrl+4),  ️ चेतावनी (Ctrl+5)|दिनांक और समय स्टैम्प डालें (Alt+Shift+D)|संक्षिप्त करने योग्य अनुभाग डालें|संक्षिप्त अनुभाग|विवरण...|कार्य चेकबॉक्स|महत्वपूर्ण|प्रश्न|विचार / परिभाषा|गंभीर / चेतावनी|टाइमस्टैम्प डालें (Alt+Shift+D)|तालिका: ऊपर पंक्ति डालें|तालिका: नीचे पंक्ति डालें|तालिका: बाएं कॉलम डालें|तालिका: दाएं कॉलम डालें|तालिका: पंक्ति हटाएं|तालिका: कॉलम हटाएं|नए नोट से लिंक करें:"
TRANSLATIONS[vi]="Dán (Không định dạng)|Dán (Giữ định dạng)|Bắt đầu|Chèn|Xem & Xuất|Ghi chú|Bảng nhớ tạm|Phông chữ|Đoạn văn|Thẻ & Khối|Chèn|Xem & Xuất|Xuất|Ghi chú trước trong lịch sử (Alt+Trái)|Ghi chú tiếp theo trong lịch sử (Alt+Phải)|Thẻ nhanh: ⭐ Quan trọng (Ctrl+2), ❓ Câu hỏi (Ctrl+3), 💡 Ý tưởng (Ctrl+4),  ️ Cảnh báo (Ctrl+5)|Chèn dấu ng y & giờ (Alt+Shift+D)|Chèn mục thu gọn|Mục thu gọn|Chi tiết...|Hộp kiểm việc cần l m|Quan trọng|Câu hỏi|Ý tưởng / Định nghĩa|Quan trọng / Cảnh báo|Chèn dấu thời gian (Alt+Shift+D)|Bảng: Chèn h ng phía trên|Bảng: Chèn h ng phía dưới|Bảng: Chèn cột bên trái|Bảng: Chèn cột bên phải|Bảng: Xóa h ng|Bảng: Xóa cột|Liên kết tới ghi chú mới:"
TRANSLATIONS[uk]="Вставити (без форматування)|Вставити (зберегти форматування)|Початок|Вставка|Вигляд та експорт|Нотатка|Буфер обміну|Шрифт|Абзац|Теги та блоки|Вставка|Вигляд та експорт|Експорт|Попередня нотатка в історії (Alt+Вліво)|Наступна нотатка в історії (Alt+Вправо)|Швидкий тег: ⭐ Важливо (Ctrl+2), ❓ Питання (Ctrl+3), 💡 Ідея (Ctrl+4),  ️ Увага (Ctrl+5)|Вставити дату й час (Alt+Shift+D)|Вставити згортаємий розділ|Згортаємий розділ|Деталі...|Прапорець завдання|Важливо|Питання|Ідея / Визначення|Критично / Увага|Вставити позначку часу (Alt+Shift+D)|Таблиця: Вставити рядок вище|Таблиця: Вставити рядок нижче|Таблиця: Вставити стовпець ліворуч|Таблиця: Вставити стовпець праворуч|Таблиця: Видалити рядок|Таблиця: Видалити стовпець|Посилання на нову нотатку:"
TRANSLATIONS[bar]="Eifügn (Unformatiert)|Eifügn (Formatierung bhoitn)|Start|Eifügn|Osicht & Export|Notiz|Zwischnablag|Schriftart|Absatz|Tags & Bleck|Eifügn|Osicht & Export|Export|Vorige Notiz im Verlauf (Alt+Links)|Nächste Notiz im Verlauf (Alt+Rechts)|OneNote Schnej-Tag: ⭐ Wichtig (Strg+2), ❓ Frog (Strg+3), 💡 Idee (Strg+4),  ️ Warnung (Strg+5)|Datum- & Zeitstempfl eifügn (Alt+Umschalt+D)|Ausklappbarn Abschnitt eifügn|Ausklappbara Abschnitt|Details...|Aufgabn-Kastl|Wichtig|Frog|Idee / Definition|Kritisch / Warnung|Zeitstempfl eifügn (Alt+Umschalt+D)|Tabejn: Zeiln obahoib eifügn|Tabejn: Zeiln untahoib eifügn|Tabejn: Spoitn links eifügn|Tabejn: Spoitn rechts eifügn|Tabejn: Zeiln löschn|Tabejn: Spoitn löschn|Vaknüpfung zu neia Notiz:"
TRANSLATIONS[hes]="Eifüge (Unformatiert)|Eifüge (Formatierung behalde)|Start|Eifüge|Aasicht & Export|Notiz|Zwischeablag|Schriftart|Absatz|Tags & Blöck|Eifüge|Aasicht & Export|Export|Vorherige Notiz im Verlauf (Alt+Links)|Nächste Notiz im Verlauf (Alt+Rechts)|OneNote Schnell-Tag: ⭐ Wischdisch (Strg+2), ❓ Frooch (Strg+3), 💡 Idee (Strg+4),  ️ Warnung (Strg+5)|Datum- & Zeitstempel eifüge (Alt+Umschalt+D)|Ausklappbare Abschnitt eifüge|Ausklappbarer Abschnitt|Details...|Uffgawe-Kästsche|Wischdisch|Frooch|Idee / Definition|Kritisch / Warnung|Zeitstempel eifüge (Alt+Umschalt+D)|Tabell: Zeil owwe eifüge|Tabell: Zeil unne eifüge|Tabell: Spalt links eifüge|Tabell: Spalt rechts eifüge|Tabell: Zeil lösche|Tabell: Spalt lösche|Verknüpfung zu neier Notiz:"
TRANSLATIONS[lb]="Aféieren (Onformatéiert)|Aféieren (Formatéierung behalen)|Start|Aféieren|Usicht & Export|Notiz|Zwëschenablag|Schrëftart|Absaz|Tags & Bléck|Aféieren|Usicht & Export|Export|Vireg Notiz am Verlaf (Alt+Lénks)|Nächst Notiz am Verlaf (Alt+Riets)|OneNote Schnell-Tag: ⭐ Wichteg (Strg+2), ❓ Fro (Strg+3), 💡 Iddi (Strg+4),  ️ Warnung (Strg+5)|Datum- & Zäitstempel aféieren (Alt+Shift+D)|Ausklappbare Sektioun aféieren|Ausklappbar Sektioun|Detailer...|Aufgaben-Këschtchen|Wichteg|Fro|Iddi / Definitioun|Kritesch / Warnung|Zäitstempel aféieren (Alt+Shift+D)|Tabell: Zeil uewen aféieren|Tabell: Zeil ënnen aféieren|Tabell: Spalt lénks aféieren|Tabell: Spalt riets aféieren|Tabell: Zeil läschen|Tabell: Spalt läschen|Verknëppung mat neier Notiz:"
TRANSLATIONS[pcm]="Paste (Plain Text)|Paste (Keep Style)|Start|Put Inside|View & Export|Note|Clipboard|Font|Paragraph|Tags & Blocks|Put Inside|View & Export|Export|Previous Note inside History (Alt+Left)|Next Note inside History (Alt+Right)|Quick Tag: ⭐ Important (Ctrl+2), ❓ Question (Ctrl+3), 💡 Idea (Ctrl+4),  ️ Warning (Ctrl+5)|Put Date & Time Stamp (Alt+Shift+D)|Put Fold Section|Fold Section|Details...|To-Do Box|Important|Question|Idea / Meaning|Serious / Warning|Put Timestamp (Alt+Shift+D)|Table: Put Row On Top|Table: Put Row Under|Table: Put Column Left|Table: Put Column Right|Table: Delete Row|Table: Delete Column|Link go new note:"

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