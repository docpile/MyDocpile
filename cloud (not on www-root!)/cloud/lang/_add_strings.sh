#!/bin/bash


# ==============================================================================
# CONFIGURATION
# ==============================================================================

TARGET_ANCHOR="obs_show_yaml"

# ==============================================================================
# ARRAYS
# ==============================================================================


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
    "obs_vault_bg_color"
    "obs_default_lilac"
    "obs_folder_color"
    "obs_vault_replace"
    "obs_vault_btn"
    "obs_vault_replace_title"
    "obs_vault_replace_hint"
    "obs_vr_case"
    "obs_vr_word"
    "obs_vr_regex"
    "obs_vr_ex_fm"
    "obs_vr_ex_code"
    "obs_vr_path_filter"
    "obs_vr_scan"
    "obs_vr_empty_prompt"
    "obs_vr_confirm_msg"
    "obs_vr_warn_irreversible"
    "obs_vr_ph_search"
    "obs_vr_ph_replace"
    "obs_vr_found_hits"
    "obs_vr_select_all"
    "obs_vr_no_matches"
    "obs_vr_line"
    "obs_vr_matches"
    "obs_vr_err_net"
)

declare -A TRANSLATIONS

TRANSLATIONS[en]="Obsidian Settings:|General|Hidden Folders|Select folders to exclude from the tree and file list views:|No subfolders found|Attachment Folder Path:|New File Location:|Vault Root|Current Folder|Specific Folder|New File Folder Path:|Strict Line Breaks|Show YAML Frontmatter|Vault Background Color:|Default (Light Lilac)|Folder Color|Search & Replace in Vault...|Vault...|Vault Raw Markdown Search & Replace:|Operates directly on raw Markdown (.md) files on disk (including HTML & Markdown syntax tags) without using the search index.|Case sensitive|Whole word|Regular Expression|Skip YAML Frontmatter|Skip Code Blocks (\`\`\`...\`\`\`)|Subfolder / glob filter (optional)|Scan Vault|Enter a search pattern and click \"Scan Vault\" to preview matches across all Markdown files.|Are you sure you want to permanently replace matches in|Batch replacements across vault files cannot be undone.|e.g. <span class=\"old\"> or [[OldLink]]|Replacement (supports $1 in Regex mode)|Found <b>%s1</b> match(es) across <b>%s2</b> note(s)|Select All|No matches found in this vault.|Line %s:|matches|Network error while scanning vault."
TRANSLATIONS[de]="Obsidian Einstellungen:|Allgemein|Versteckte Ordner|Wählen Sie Ordner aus, die von der Baum- und Dateilistenansicht ausgeschlossen werden sollen:|Keine Unterordner gefunden|Pfad für Anhänge:|Speicherort für neue Dateien:|Vault-Stammverzeichnis|Aktueller Ordner|Bestimmter Ordner|Ordnerpfad für neue Dateien:|Strikte Zeilenumbrüche|YAML Frontmatter anzeigen|Vault-Hintergrundfarbe:|Standard (Hellflieder)|Ordnerfarbe|Suchen & Ersetzen im Vault...|Vault...|Vault Roh-Markdown Suchen & Ersetzen:|Arbeitet direkt auf den rohen Markdown-Dateien (.md) auf der Festplatte (inkl. HTML- & Markdown-Tags) ohne Suchindex.|Groß-/Kleinschreibung|Ganzes Wort|Regulärer Ausdruck|YAML Frontmatter überspringen|Code-Blöcke überspringen (\`\`\`...\`\`\`)|Unterordner / Glob-Filter (optional)|Vault durchsuchen|Geben Sie ein Suchmuster ein und klicken Sie auf \"Vault durchsuchen\", um Treffer in allen Markdown-Dateien in der Vorschau anzuzeigen.|Sind Sie sicher, dass Sie die Treffer dauerhaft ersetzen möchten in|Massenersetzungen in Vault-Dateien können nicht rückgängig gemacht werden.|z.B. <span class=\"old\"> oder [[OldLink]]|Ersetzung (unterstützt $1 im Regex-Modus)|<b>%s1</b> Treffer in <b>%s2</b> Notiz(en) gefunden|Alle auswählen|Keine Treffer in diesem Vault gefunden.|Zeile %s:|Treffer|Netzwerkfehler beim Scannen des Vaults."
TRANSLATIONS[es]="Configuración de Obsidian:|General|Carpetas Ocultas|Seleccione las carpetas a excluir de las vistas de árbol y lista de archivos:|No se encontraron subcarpetas|Ruta de la carpeta de adjuntos:|Ubicación de nuevo archivo:|Raíz del Vault|Carpeta actual|Carpeta específica|Ruta de la carpeta de nuevo archivo:|Saltos de línea estrictos|Mostrar YAML Frontmatter|Color de fondo del Vault:|Predeterminado (Lila claro)|Color de carpeta|Buscar y reemplazar en Vault...|Vault...|Buscar y reemplazar Markdown sin procesar en Vault:|Opera directamente en archivos Markdown (.md) sin procesar en el disco (incluidas las etiquetas de sintaxis HTML y Markdown) sin usar el índice de búsqueda.|Distinguir mayúsculas|Palabra completa|Expresión regular|Omitir YAML Frontmatter|Omitir bloques de código (\`\`\`...\`\`\`)|Filtro de subcarpeta / glob (opcional)|Escanear Vault|Ingrese un patrón de búsqueda y haga clic en \"Escanear Vault\" para obtener una vista previa de las coincidencias en todos los archivos Markdown.|¿Está seguro de que desea reemplazar permanentemente las coincidencias en|Los reemplazos por lotes en los archivos del Vault no se pueden deshacer.|p. ej. <span class=\"old\"> o [[OldLink]]|Reemplazo (admite $1 en modo Regex)|Se encontraron <b>%s1</b> coincidencia(s) en <b>%s2</b> nota(s)|Seleccionar todo|No se encontraron coincidencias en este Vault.|Línea %s:|coincidencias|Error de red al escanear el Vault."
TRANSLATIONS[fr]="Paramètres d'Obsidian :|Général|Dossiers cachés|Sélectionnez les dossiers   exclure des vues d'arborescence et de liste des fichiers :|Aucun sous-dossier trouvé|Chemin du dossier des pièces jointes :|Emplacement du nouveau fichier :|Racine du coffre|Dossier actuel|Dossier spécifique|Chemin du nouveau fichier :|Sauts de ligne stricts|Afficher YAML Frontmatter|Couleur de fond du coffre :|Par défaut (Lilas clair)|Couleur du dossier|Chercher & Remplacer dans le coffre...|Coffre...|Recherche & Remplacement Markdown brut du coffre :|Opère directement sur les fichiers Markdown (.md) bruts sur le disque (y compris les balises HTML et la syntaxe Markdown) sans utiliser l'index de recherche.|Sensible   la casse|Mot entier|Expression régulière|Ignorer YAML Frontmatter|Ignorer les blocs de code (\`\`\`...\`\`\`)|Filtre sous-dossier / glob (optionnel)|Analyser le coffre|Entrez un motif de recherche et cliquez sur \"Analyser le coffre\" pour prévisualiser les correspondances dans tous les fichiers Markdown.|Êtes-vous sûr de vouloir remplacer définitivement les correspondances dans|Les remplacements par lots dans les fichiers du coffre ne peuvent pas être annulés.|par ex. <span class=\"old\"> ou [[OldLink]]|Remplacement (prend en charge $1 en mode Regex)|<b>%s1</b> correspondance(s) trouvée(s) dans <b>%s2</b> note(s)|Tout sélectionner|Aucune correspondance trouvée dans ce coffre.|Ligne %s :|correspondances|Erreur réseau lors de l'analyse du coffre."
TRANSLATIONS[it]="Impostazioni Obsidian:|Generale|Cartelle Nascoste|Seleziona le cartelle da escludere dalle visualizzazioni ad albero e a elenco:|Nessuna sottocartella trovata|Percorso cartella allegati:|Posizione nuovo file:|Radice del Vault|Cartella corrente|Cartella specifica|Percorso cartella nuovo file:|Interruzioni di riga rigide|Mostra YAML Frontmatter|Colore di sfondo Vault:|Predefinito (Lilla chiaro)|Colore cartella|Cerca e sostituisci nel Vault...|Vault...|Cerca e sostituisci Markdown non formattato nel Vault:|Opera direttamente sui file Markdown (.md) non formattati sul disco (inclusi i tag di sintassi HTML e Markdown) senza utilizzare l'indice di ricerca.|Maiuscole/minuscole|Parola intera|Espressione regolare|Ignora YAML Frontmatter|Ignora blocchi di codice (\`\`\`...\`\`\`)|Filtro sottocartella / glob (opzionale)|Scansiona Vault|Inserisci un modello di ricerca e fai clic su \"Scansiona Vault\" per visualizzare in anteprima le corrispondenze in tutti i file Markdown.|Sei sicuro di voler sostituire in modo permanente le corrispondenze in|Le sostituzioni batch nei file del Vault non possono essere annullate.|ad es. <span class=\"old\"> o [[OldLink]]|Sostituzione (supporta $1 in modalit  Regex)|Trovate <b>%s1</b> corrispondenza/e in <b>%s2</b> nota/e|Seleziona tutto|Nessuna corrispondenza trovata in questo vault.|Riga %s:|corrispondenze|Errore di rete durante la scansione del vault."
TRANSLATIONS[pt]="Configurações do Obsidian:|Geral|Pastas Ocultas|Selecione as pastas a serem excluídas das exibições de árvore e lista de arquivos:|Nenhuma subpasta encontrada|Caminho da pasta de anexos:|Local do novo arquivo:|Raiz do Vault|Pasta atual|Pasta específica|Caminho da pasta de novo arquivo:|Quebras de linha estritas|Mostrar YAML Frontmatter|Cor de Fundo do Vault:|Padrão (Lilás Claro)|Cor da Pasta|Buscar e Substituir no Vault...|Vault...|Buscar e Substituir Markdown Bruto no Vault:|Opera diretamente em arquivos Markdown (.md) brutos no disco (incluindo tags de sintaxe HTML e Markdown) sem usar o índice de pesquisa.|Diferenciar maiúsculas|Palavra inteira|Expressão Regular|Ignorar YAML Frontmatter|Ignorar blocos de código (\`\`\`...\`\`\`)|Filtro de subpasta / glob (opcional)|Escanear Vault|Insira um padrão de pesquisa e clique em \"Escanear Vault\" para visualizar as correspondências em todos os arquivos Markdown.|Tem certeza de que deseja substituir permanentemente as correspondências em|As substituições em lote em arquivos do vault não podem ser desfeitas.|ex. <span class=\"old\"> ou [[OldLink]]|Substituição (suporta $1 no modo Regex)|Encontradas <b>%s1</b> correspondência(s) em <b>%s2</b> nota(s)|Selecionar Tudo|Nenhuma correspondência encontrada neste vault.|Linha %s:|correspondências|Erro de rede ao analisar o vault."
TRANSLATIONS[ru]="Настройки Obsidian:|Общие|Скрытые папки|Выберите папки для исключения из просмотра дерева и списка файлов:|Подпапки не найдены|Путь к папке вложений:| асположение нового файла:|Корень хранилища|Текущая папка|Определенная папка|Путь к папке нового файла:|Строгие разрывы строк|Показывать YAML Frontmatter|Цвет фона хранилища:|По умолчанию (Светло-сиреневый)|Цвет папки|Найти и заменить в хранилище...|Хранилище...|Поиск и замена необработанного Markdown в хранилище:| аботает напрямую с необработанными файлами Markdown (.md) на диске (включая теги синтаксиса HTML и Markdown) без использования поискового индекса.|Учитывать регистр|Слово целиком| егулярное выражение|Пропустить YAML Frontmatter|Пропустить блоки кода (\`\`\`...\`\`\`)|Фильтр подпапок / glob (необязательно)|Сканировать хранилище|Введите шаблон поиска и нажмите «Сканировать хранилище» для предварительного просмотра совпадений во всех файлах Markdown.|Вы уверены, что хотите навсегда заменить совпадения в|Пакетные замены в файлах хранилища не могут быть отменены.|например, <span class=\"old\"> или [[OldLink]]|Замена (поддерживает $1 в режиме Regex)|Найдено <b>%s1</b> совпадений в <b>%s2</b> заметке(ах)|Выбрать все|В этом хранилище совпадений не найдено.|Строка %s:|совпадений|Сетевая ошибка при сканировании хранилища."
TRANSLATIONS[tr]="Obsidian Ayarları:|Genel|Gizli Klasörler|Ağaç ve dosya listesi görünümlerinden dışlanacak klasörleri seçin:|Alt klasör bulunamadı|Ek Klasör Yolu:|Yeni Dosya Konumu:|Kasa Kökü|Geçerli Klasör|Belirli Klasör|Yeni Dosya Klasör Yolu:|Katı Satır Sonları|YAML Frontmatter'ı Göster|Kasa Arka Plan Rengi:|Varsayılan (Açık Leylak)|Klasör Rengi|Kasa içinde Bul ve Değiştir...|Kasa...|Kasa Ham Markdown Bul ve Değiştir:|Arama dizinini kullanmadan doğrudan diskteki ham Markdown (.md) dosyaları (HTML ve Markdown sözdizimi etiketleri dahil) üzerinde çalışır.|Büyük/küçük harfe duyarlı|Tam kelime|Düzenli İfade|YAML Frontmatter'ı Atla|Kod Bloklarını Atla (\`\`\`...\`\`\`)|Alt klasör / glob filtresi (isteğe bağlı)|Kasayı Tara|Bir arama deseni girin ve tüm Markdown dosyalarındaki eşleşmeleri önizlemek için \"Kasayı Tara\"ya tıklayın.|Eşleşmeleri kalıcı olarak değiştirmek istediğinizden emin misiniz|Kasa dosyalarındaki toplu değiştirmeler geri alınamaz.|ör. <span class=\"old\"> veya [[OldLink]]|Değiştirme (Regex modunda $1 desteklenir)|<b>%s2</b> notta <b>%s1</b> eşleşme bulundu|Tümünü Seç|Bu kasada eşleşme bulunamadı.|Satır %s:|eşleşme|Kasayı tararken ağ hatası."
TRANSLATIONS[zh-cn]="Obsidian 设置：|常规|隐藏文件夹|选择要从 状和文件列表视图中排除的文件夹：|未找到子文件夹|附件文件夹路径：|新文件位置：|仓库 目录|当前文件夹|特定文件夹|新文件文件夹路径：|严 换行|显示 YAML Frontmatter|仓库背景颜色：|默认（浅紫罗兰色）|文件夹颜色|在仓库中查找和替换...|仓库...|仓库原始 Markdown 查找和替换：|直接在磁盘上的原始 Markdown (.md) 文件（包括 HTML 和 Markdown 语法 签）上操作，不使用搜索索引。|区分大小写|全字匹配|正则表达式|跳过 YAML Frontmatter|跳过代 块 (\`\`\`...\`\`\`)|子文件夹 / glob 过滤（可选）|扫描仓库|输入搜索模式，然后点击“扫描仓库”预览所有 Markdown 文件中的匹配项。|您确定要永久替换匹配项吗，在|仓库文件中的批量替换操作 法撤销。|例如 <span class=\"old\"> 或 [[OldLink]]|替换（在正则模式下支持 $1）|在 <b>%s2</b> 个笔记中找到 <b>%s1</b> 个匹配项|全选|在此仓库中未找到匹配项。|第 %s 行：|个匹配项|扫描仓库时发生网络错误。"
TRANSLATIONS[ja]="Obsidian 設定:|一般|非表示のフォルダ|ツリーとファイルリストビューから除外するフォルダを選択してく さい:|サブフォルダが見つかりません|添付ファイルフォルダのパス:|新規ファイルの 所:|Vault のルート|現在のフォルダ|特定のフォルダ|新規ファイルのフォルダパス:|厳密な改行|YAML Frontmatter を表示|Vaultの背景色:|デフォルト (ライトライラック)|フォルダの色|Vault内を検索して置換...|Vault...|Vaultの生のMarkdown検索と置換:|検索インデックスを使用せず、ディスク上の生のMarkdown（.md）ファイル（HTMLおよびMarkdown構文タグを含む）を直接操作します。|大文字と小文字を区別|単語全体|正規表現|YAML Frontmatter をスキップ|コードブロックをスキップ (\`\`\`...\`\`\`)|サブフォルダ / glob フィルタ (オプション)|Vault をスキャン|検索パターンを入力し、「Vault をスキャン」をクリックして、すべてのMarkdownファイルでの一致をプレビューします。|一致 目を完全に置換してもよろしいですか。対象:|Vault ファイル全体のバッチ置換は元に戻せません。|例: <span class=\"old\"> または [[OldLink]]|置換 (正規表現モードで $1 をサポート)|<b>%s2</b> 個のノートで <b>%s1</b> 件の一致が見つかりました|すべて選択|このVaultには一致が見つかりませんでした。|行 %s:|件の一致|Vaultのスキャン中にネットワークエラーが発生しました。"
TRANSLATIONS[ko]="Obsidian 설 :|일반|숨김 폴더|트리 및 파일 목록 보기에서  외  폴더를  택하세요:|하위 폴더를 찾을 수 없음|첨부 폴더 경로:|새 파일 위치:|보관소 루트|현재 폴더|특  폴더|새 파일 폴더 경로:|엄격한 줄바꿈|YAML Frontmatter 표시|보관소 배경색:|기본값 (밝은 라일락)|폴더 색상|보관소에서 찾기 및 바꾸기...|보관소...|보관소 원시 Markdown 찾기 및 바꾸기:|검색 인덱스를 사용하지 않  디스크의 원시 Markdown(.md) 파일(HTML 및 Markdown 구문 태그 포함)에서 직  작동합니다.|대소문자 구분| 체 단어| 규 표현식|YAML Frontmatter 건너뛰기|코드 블록 건너뛰기 (\`\`\`...\`\`\`)|하위 폴더 / glob 필터 ( 택 사항)|보관소 스캔|검색 패턴을 입 하  \"보관소 스캔\"을 클릭하여 모  Markdown 파일의 일치 항목을 미리 봅니다.|다음에서 일치 항목을 영구 으로 바꾸시 습니까?|보관소 파일  체의 일괄 바꾸기는 실행 취소  수 없습니다.|예: <span class=\"old\"> 또는 [[OldLink]]|바꾸기 (Regex 모드에서 $1 지원)|<b>%s2</b>개의 노트에서 <b>%s1</b>개의 일치 항목을 찾았습니다|모두  택|이 보관소에서 일치 항목을 찾을 수 없습니다.|%s행:|개 일치|보관소를 스캔하는 동안 네트워크 오류가 발생했습니다."
TRANSLATIONS[ar]="إعدادات Obsidian:|عام|المجلدات المخفية|حدد المجلدات المراد استبعادها من طرق عرض الشجرة وقائمة الملفات:|لم يتم العثور على مجلدات فرعية|مسار مجلد المرفقات:|موقع الملف الجديد:|جذر المخزن|المجلد الحالي|مجلد محدد|مسار مجلد الملف الجديد:|فواصل أسطر صارمة|إظهار YAML Frontmatter|لون خلفية المخزن:|الافتراضي (أرجواني فاتح)|لون المجلد|البحث والاستبدال في المخزن...|المخزن...|بحث واستبدال Markdown الخام في المخزن:|يعمل مباشرة على ملفات Markdown (.md) الخام على القرص (بما في ذلك علامات بناء جملة HTML و Markdown) دون استخدام فهرس البحث.|حساس لحالة الأحرف|كلمة كاملة|تعبير عادي|تخطي YAML Frontmatter|تخطي كتل التعليمات البرمجية (\`\`\`...\`\`\`)|عامل تصفية المجلد الفرعي / glob (اختياري)|فحص المخزن|أدخل نمط بحث وانقر على \"فحص المخزن\" لمعاينة التطابقات عبر جميع ملفات Markdown.|هل أنت متأكد أنك تريد استبدال التطابقات نهائيًا في|لا يمكن التراجع عن الاستبدالات المجمعة عبر ملفات المخزن.|على سبيل المثال <span class=\"old\"> أو [[OldLink]]|الاستبدال (يدعم $1 في وضع Regex)|تم العثور على <b>%s1</b> تطابق في <b>%s2</b> ملاحظة/ملاحظات|تحديد الكل|لم يتم العثور على تطابقات في هذا المخزن.|السطر %s:|تطابقات|خطأ في الشبكة أثناء فحص المخزن."
TRANSLATIONS[fa]="تنظیمات Obsidian:|عمومی|پوشه‌های مخفی|پوشه‌هایی را برای حذف از نمای درخت و لیست فایل انتخاب کنید:|هیچ زیرپوشه‌ای یافت نشد|مسیر پوشه پیوست‌ها:|مکان فایل جدید:|ریشه مخزن|پوشه فعلی|پوشه خاص|مسیر پوشه فایل جدید:|شکست‌های خط سخت‌گیرانه|نمایش YAML Frontmatter|رنگ پس‌زمینه مخزن:|پیش‌فرض (یاسی روشن)|رنگ پوشه|جستجو و جایگزینی در مخزن...|مخزن...|جستجو و جایگزینی Markdown خام در مخزن:|به طور مستقیم روی فایل‌های خام Markdown (.md) در دیسک (از جمله برچسب‌های نحوی HTML و Markdown) بدون استفاده از فهرست جستجو عمل می‌کند.|حساس به حروف بزرگ و کوچک|کلمه کامل|عبارت باقاعده|رد شدن از YAML Frontmatter|رد شدن از بلوک‌های کد (\`\`\`...\`\`\`)|فیلتر زیرپوشه / glob (اختیاری)|اسکن مخزن|یک الگوی جستجو وارد کنید و برای پیش‌نمایش تطابق‌ها در تمام فایل‌های Markdown، روی «اسکن مخزن» کلیک کنید.|آیا مطمئن هستید که می‌خواهید تطابق‌ها را برای همیشه در موارد زیر جایگزین کنید:|جایگزینی‌های دسته‌ای در سراسر فایل‌های مخزن قابل لغو نیستند.|مثلاً <span class=\"old\"> یا [[OldLink]]|جایگزینی (پشتیبانی از $1 در حالت Regex)|<b>%s1</b> تطابق در <b>%s2</b> یادداشت پیدا شد|انتخاب همه|هیچ تطابقی در این مخزن یافت نشد.|سطر %s:|تطابق|خطای شبکه هنگام اسکن مخزن."
TRANSLATIONS[hi]="Obsidian सेटिंग्स:|सामान्य|छिपे हुए फ़ोल्डर|ट्री और फ़ाइल सूची दृश्यों से बाहर करने के लिए फ़ोल्डर चुनें:|कोई उप-फ़ोल्डर नहीं मिला|अनुलग्नक फ़ोल्डर पथ:|नया फ़ाइल स्थान:|वॉल्ट रूट|वर्तमान फ़ोल्डर|विशिष्ट फ़ोल्डर|नया फ़ाइल फ़ोल्डर पथ:|सख्त लाइन ब्रेक|YAML Frontmatter दिखाएं|वॉल्ट पृष् भूमि रंग:|डिफ़ॉल्ट (हल्का बकाइन)|फ़ोल्डर रंग|वॉल्ट में खोजें और बदलें...|वॉल्ट...|वॉल्ट रॉ Markdown खोजें और बदलें:|खोज अनुक्रमणिका का उपयोग किए बिना डिस्क पर सीधे रॉ Markdown (.md) फ़ाइलों (HTML और Markdown सिंटैक्स टैग सहित) पर काम करता है।|केस संवेदी|पूरा शब्द|रेगुलर एक्सप्रेशन|YAML Frontmatter छोड़ें|कोड ब्लॉक छोड़ें (\`\`\`...\`\`\`)|उप-फ़ोल्डर / glob फ़िल्टर (वैकल्पिक)|वॉल्ट स्कैन करें|एक खोज पैटर्न दर्ज करें और सभी Markdown फ़ाइलों में मिलानों का पूर्वावलोकन करने के लिए \"वॉल्ट स्कैन करें\" पर क्लिक करें।|क्या आप वाकई में मिलानों को स्थायी रूप से बदलना चाहते हैं|वॉल्ट फ़ाइलों में बैच प्रतिस्थापन को पूर्ववत नहीं किया जा सकता है।|उदा. <span class=\"old\"> या [[OldLink]]|प्रतिस्थापन (रेगेक्स मोड में $1 का समर्थन करता है)|<b>%s2</b> नोट में <b>%s1</b> मिलान मिले|सभी चुनें|इस वॉल्ट में कोई मिलान नहीं मिला।|पंक्ति %s:|मिलान|वॉल्ट को स्कैन करते समय नेटवर्क त्रुटि।"
TRANSLATIONS[vi]="C i đặt Obsidian:|Chung|Thư mục bị ẩn|Chọn các thư mục để loại trừ khỏi chế độ xem cây v  danh sách tệp:|Không tìm thấy thư mục con n o|Đường dẫn thư mục đính kèm:|Vị trí tệp mới:|Gốc Vault|Thư mục hiện tại|Thư mục cụ thể|Đường dẫn thư mục tệp mới:|Ngắt dòng nghiêm ngặt|Hiển thị YAML Frontmatter|M u Nền Vault:|Mặc định (Tím nhạt)|M u Thư mục|Tìm kiếm & Thay thế trong Vault...|Vault...|Tìm kiếm & Thay thế Markdown Thô trong Vault:|Hoạt động trực tiếp trên các tệp Markdown (.md) thô trên đĩa (bao gồm các thẻ cú pháp HTML & Markdown) m  không sử dụng chỉ mục tìm kiếm.|Phân biệt chữ hoa chữ thường|To n bộ từ|Biểu thức chính quy|Bỏ qua YAML Frontmatter|Bỏ qua Khối mã (\`\`\`...\`\`\`)|Bộ lọc thư mục con / glob (tùy chọn)|Quét Vault|Nhập mẫu tìm kiếm v  nhấp v o \"Quét Vault\" để xem trước các kết quả khớp trên tất cả các tệp Markdown.|Bạn có chắc chắn muốn thay thế vĩnh viễn các kết quả khớp trong|Không thể ho n tác các thay thế h ng loạt trên các tệp vault.|vd: <span class=\"old\"> hoặc [[OldLink]]|Thay thế (hỗ trợ $1 trong chế độ Regex)|Đã tìm thấy <b>%s1</b> kết quả trong <b>%s2</b> ghi chú|Chọn Tất cả|Không tìm thấy kết quả n o trong vault n y.|Dòng %s:|kết quả|Lỗi mạng khi quét vault."
TRANSLATIONS[uk]="Налаштування Obsidian:|Загальні|Приховані папки|Виберіть папки для виключення з перегляду дерева та списку файлів:|Підпапки не знайдено|Шлях до папки вкладень:| озташування нового файлу:|Корінь сховища|Поточна папка|Конкретна папка|Шлях до папки нового файлу:|Строгі розриви рядків|Показувати YAML Frontmatter|Колір фону сховища:|За замовчуванням (Світло-бузковий)|Колір папки|Знайти та замінити у сховищі...|Сховище...|Пошук і заміна необробленого Markdown у сховищі:|Працює безпосередньо з необробленими файлами Markdown (.md) на диску (включаючи теги синтаксису HTML і Markdown) без використання пошукового індексу.|Враховувати регістр|Слово цілком| егулярний вираз|Пропустити YAML Frontmatter|Пропустити блоки коду (\`\`\`...\`\`\`)|Фільтр підпапок / glob (необов'язково)|Сканувати сховище|Введіть шаблон пошуку та натисніть «Сканувати сховище» для попереднього перегляду збігів у всіх файлах Markdown.|Ви впевнені, що хочете назавжди замінити збіги у|Пакетні заміни у файлах сховища не можуть бути скасовані.|напр. <span class=\"old\"> або [[OldLink]]|Заміна (підтримує $1 в режимі Regex)|Знайдено <b>%s1</b> збіг(ів) у <b>%s2</b> нотатці(ах)|Вибрати всі|Збігів у цьому сховищі не знайдено.| ядок %s:|збіг(ів)|Помилка мережі під час сканування сховища."
TRANSLATIONS[bar]="Obsidian Eistellunga:|Allgemein|Versteckte Ordnar|Wähl Ordnar aus, de wo vom Baam und da Dateilistn ausgschlossn wern soin:|Koane Unterordnar gfundn|Pfad für Anhäng:|Speicherort für neie Datein:|Vault-Wurzl|Aktueller Ordnar|Bestimmter Ordnar|Ordnarpfad für neie Datein:|Strikte Zeilnumbrüch|YAML Frontmatter ozoang|Vault-Hintergrundfarb:|Standard (Hejflieda)|Ordnarfarb|Suacha & Ersetzn im Vault...|Vault...|Vault Roh-Markdown Suacha & Ersetzn:|Arbat direkt auf de rohen Markdown-Datein (.md) auf da Festplottn (inkl. HTML- & Markdown-Tags) ohne Suchindex.|Groß-/Kloaschreibung|Ganzs Wort|Regulärer Ausdruck|YAML Frontmatter ibaspringa|Code-Bleck ibaspringa (\`\`\`...\`\`\`)|Unterordnar / Glob-Filter (optional)|Vault durchsuacha|Gib a Suchmuster ei und klick auf \"Vault durchsuacha\", um Treffa in oin Markdown-Datein in da Vorschau ozoang z'lossn.|Bist da sicha, dass de Treffa dauerhaft ersetzt wern soin in|Massenersetzunga in Vault-Datein kinna ned rückgängig gmacht wern.|z.B. <span class=\"old\"> oda [[OldLink]]|Ersetzung (unterstützt $1 im Regex-Modus)|<b>%s1</b> Treffa in <b>%s2</b> Notiz(n) gfundn|Olle auswähln|Koane Treffa in dem Vault gfundn.|Zeiln %s:|Treffa|Netzwerkfehla beim Scanna vom Vault."
TRANSLATIONS[hes]="Obsidian Eistellunge:|Allgemein|Versteckte Ordner|Wähl Ordner aus, die von de Baum- und Dateiliste-Asicht ausgeschlosse wern solle:|Kaa Unnerordner gefunne|Pfad fer Anhäng:|Speicherort fer neie Dateie:|Vault-Wurzel|Aktueller Ordner|Bestimmter Ordner|Ordnerpfad fer neie Dateie:|Strikte Zeilenumbrüch|YAML Frontmatter aazeige|Vault-Hinnergrundfarb:|Standard (Hellflieder)|Ordnerfarb|Suche & Ersetze im Vault...|Vault...|Vault Roh-Markdown Suche & Ersetze:|Arweidt direkt uff de rohe Markdown-Dateie (.md) uff de Festplatt (inkl. HTML- & Markdown-Tags) ohne Suchindex.|Groß-/Klaaschreibung|Ganzes Wort|Regulärer Ausdruck|YAML Frontmatter überspringe|Code-Blöcke überspringe (\`\`\`...\`\`\`)|Unnerordner / Glob-Filter (optional)|Vault durchsuche|Gib e Suchmuster ei unn klick uff \"Vault durchsuche\", um Treffer in alle Markdown-Dateie in de Vorschau aazuzeije.|Bist der sicher, dass de die Treffer dauerhaft ersetze willst in|Massenersetzunge in Vault-Dateie könne net rückgängig gemacht wern.|z.B. <span class=\"old\"> odder [[OldLink]]|Ersetzung (unnerstützt $1 im Regex-Modus)|<b>%s1</b> Treffer in <b>%s2</b> Notiz(e) gefunne|Alle auswähle|Kaa Treffer in dem Vault gefunne.|Zeile %s:|Treffer|Netzwerkfehler beim Scanne vum Vault."
TRANSLATIONS[lb]="Obsidian Astellungen:|Allgemeng|Verstoppt Classeuren|Wielt Classeuren aus, déi aus der Bam- an Dateilëscht-Usicht ausgeschloss solle ginn:|Keng Ënnerclasseuren fonnt|Pad fir Unhänk:|Späicheruert fir nei Dateien:|Vault-Wuerzel|Aktuelle Classeur|Spezifesche Classeur|Classeurpad fir nei Dateien:|Strikt Zeilenëmbréch|YAML Frontmatter weisen|Vault-Hannergrondfaarf:|Standard (Hellflieder)|Classeurfaarf|Sichen & Ersetzen am Vault...|Vault...|Vault Réi-Markdown Sichen & Ersetzen:|Schafft direkt op de réie Markdown-Dateien (.md) op der Festplack (inkl. HTML- & Markdown-Tags) ouni Suchindex.|Grouss-/Klengschreiwung|Ganzt Wuert|Regulären Ausdrock|YAML Frontmatter iwwersprangen|Code-Bléck iwwersprangen (\`\`\`...\`\`\`)|Ënnerclasseur / Glob-Filter (optional)|Vault duerchsichen|Gitt e Suchmuster an a klickt op \"Vault duerchsichen\", fir Treffer an allen Markdown-Dateien an der Virschau ze weisen.|Sidd Dir sécher, datt Dir d'Treffer dauerhaft ersetze wëllt an|Massenersetzungen a Vault-Dateien kënnen net réckgängeg gemaach ginn.|z.B. <span class=\"old\"> oder [[OldLink]]|Ersetzung (ënnerstëtzt $1 am Regex-Modus)|<b>%s1</b> Treffer an <b>%s2</b> Notiz(en) fonnt|All auswielen|Keng Treffer an dësem Vault fonnt.|Zeil %s:|Treffer|Netzwierkfeeler beim Scanne vum Vault."
TRANSLATIONS[pcm]="Obsidian Settings:|General|Hide Folders|Choose folders wey you wan hide from di tree and file list:|No subfolders dey|Attachment Folder Path:|New File Location:|Vault Root|Current Folder|Specific Folder|New File Folder Path:|Strict Line Breaks|Show YAML Frontmatter|Vault Background Color:|Default (Light Lilac)|Folder Color|Search & Replace inside Vault...|Vault...|Vault Raw Markdown Search & Replace:|E dey work straight on raw Markdown (.md) files on disk (including HTML & Markdown syntax tags) without using di search index.|Case sensitive|Whole word|Regular Expression|Skip YAML Frontmatter|Skip Code Blocks (\`\`\`...\`\`\`)|Subfolder / glob filter (optional)|Scan Vault|Type wetin you dey find and click \"Scan Vault\" to preview matches across all Markdown files.|You sure say you wan permanently replace matches inside|Batch replacements across vault files no fit undo.|e.g. <span class=\"old\"> or [[OldLink]]|Replacement (supports $1 inside Regex mode)|Found <b>%s1</b> match(es) across <b>%s2</b> note(s)|Select All|No matches inside dis vault.|Line %s:|matches|Network error as e dey scan vault."

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