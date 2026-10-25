#!/bin/bash


# ==============================================================================
# CONFIGURATION
# ==============================================================================

TARGET_ANCHOR="translate"

# ==============================================================================
# ARRAYS
# ==============================================================================


# 2. The Keys
KEYS=(
    "obs_new_note"
    "obs_note_title"
    "obs_untitled"
    "obs_create_failed"
    "obs_yaml_frontmatter"
    "obs_loading_excalidraw"
    "obs_excalidraw_missing"
    "obs_excalidraw_err"
    "obs_excalidraw_empty"
    "obs_save_error"
    "obs_prompt_link"
    "obs_default_task"
    "obs_default_callout"
    "obs_col_1"
    "obs_col_2"
    "obs_tbl_h1"
    "obs_tbl_h2"
    "obs_tbl_c1"
    "obs_tbl_c2"
    "obs_tip_back"
    "obs_tip_new_note"
    "obs_tip_undo"
    "obs_tip_redo"
    "obs_tip_ul"
    "obs_tip_ol"
    "obs_tip_task"
    "obs_tip_hr"
    "obs_tip_link"
    "obs_tip_callout"
    "obs_tip_multicol"
    "obs_tip_code"
    "obs_tip_table"
    "obs_tip_table_format"
    "obs_tip_attach"
    "obs_tip_find"
    "obs_tip_wysiwyg"
    "obs_tip_code_mode"
    "obs_tip_save_needed"
    "obs_tip_block_style"
    "obs_tip_font_size"
    "obs_tip_bold"
    "obs_tip_italic"
    "obs_tip_underline"
    "obs_tip_strike"
    "obs_tip_clear_fmt"
    "obs_tip_text_color"
    "obs_tip_choose_color"
    "obs_tip_bg_color"
    "obs_tip_choose_bg"
    "obs_tip_align_l"
    "obs_tip_align_c"
    "obs_tip_align_r"
    "obs_tip_outdent"
    "obs_tip_indent"
    "obs_tip_zoom_reset"
    "obs_words"
    "obs_chars"
    "obs_min_read"
    "obs_no_highlight"
    "obs_default_color"
    "obs_custom_color"
    "cut"
    "copy"
    "obs_paste_text"
    "obs_paste_shortcut_msg"
    "obs_bold"
    "obs_italic"
    "obs_underline"
    "obs_highlight"
    "obs_clear_format"
    "obs_search_web"
    "obs_translate_sel"
    "obs_translate"
    "obs_translating"
    "obs_replace_selection"
    "obs_open_gtranslate"
    "obs_translate_unavailable"
    "obs_find_placeholder"
    "obs_replace_placeholder"
    "obs_find_next"
    "obs_replace_all"
    "obs_rename_failed"
    "obs_copied"
)

declare -A TRANSLATIONS

TRANSLATIONS[en]="New Note|Note Title:|Untitled|Failed to create note.|YAML Frontmatter|Loading Excalidraw:|Excalidraw file not found:|Unable to render Excalidraw drawing.|Empty Excalidraw Canvas|Save error|Enter URL or [[WikiLink]]:|Task item|Callout text...|Column 1|Column 2|Header 1|Header 2|Cell 1|Cell 2|Back to Folder / Tree View|Create a New Note in Vault|Undo Last Action (Ctrl+Z)|Redo Last Action (Ctrl+Y)|Insert Bulleted List|Insert Numbered List|Insert Interactive Task Checkbox|Insert Horizontal Divider Line|Insert Hyperlink or [[WikiLink]] (Ctrl+K)|Insert Obsidian Callout Box|Insert Side-by-Side Multi-Column Layout|Insert Syntax-Highlighted Code Block|Insert Data Table|Auto-Format & Align Markdown Tables|Upload & Embed Image or Attachment|Find & Replace Text in Note (Ctrl+F)|Switch to Visual WYSIWYG Editor|Switch to Raw Markdown Code Editor|Unsaved changes — Click to save now (Ctrl+S)|Paragraph & Heading Level (P, H1–H5)|Text Font Size (15pt is default)|Bold Text (Ctrl+B)|Italic Text (Ctrl+I)|Underline Text (Ctrl+U)|Strikethrough Text|Remove Formatting & Reset to Clean Markdown|Apply Text Color|Choose Text Color|Apply Highlight / Background Color|Choose Highlight Color|Align Text Left|Center Text|Align Text Right|Decrease Indentation|Increase Indentation|Click to reset zoom to 100%|words|chars|min read|No Highlight / Default|Default Text Color|Custom:|Cut|Copy|Paste (Text)|Paste blocked by browser. Please use Ctrl+V.|Bold|Italic|Underline|Highlight|Clear Formatting|Search the Web|Translate Selection|Translate|Translating...|Replace Selection|Open in Google Translate|Inline translation unavailable. Click ↗ to open Google Translate.|Find in note...|Replace with...|Find|Replace All|Rename failed:|Copied!"
TRANSLATIONS[de]="Neue Notiz|Notiztitel:|Unbenannt|Notiz konnte nicht erstellt werden.|YAML Frontmatter|Lade Excalidraw:|Excalidraw-Datei nicht gefunden:|Excalidraw-Zeichnung konnte nicht gerendert werden.|Leere Excalidraw-Leinwand|Speicherfehler|URL oder [[WikiLink]] eingeben:|Aufgabe|Callout-Text...|Spalte 1|Spalte 2|Kopfzeile 1|Kopfzeile 2|Zelle 1|Zelle 2|Zurück zur Ordner-/Baumansicht|Neue Notiz im Vault erstellen|Letzte Aktion rückgängig machen (Strg+Z)|Letzte Aktion wiederholen (Strg+Y)|Aufzählungsliste einfügen|Nummerierte Liste einfügen|Interaktive Checkbox einfügen|Horizontale Trennlinie einfügen|Hyperlink oder [[WikiLink]] einfügen (Strg+K)|Obsidian Callout-Box einfügen|Mehrspaltiges Layout einfügen|Code-Block mit Syntax-Highlighting einfügen|Datentabelle einfügen|Markdown-Tabellen automatisch formatieren & ausrichten|Bild oder Anhang hochladen & einbetten|Suchen & Ersetzen in Notiz (Strg+F)|Zum visuellen WYSIWYG-Editor wechseln|Zum reinen Markdown-Code-Editor wechseln|Ungespeicherte Änderungen — Jetzt speichern (Strg+S)|Absatz- & Überschriftenebene (P, H1–H5)|Schriftgröße (15pt ist Standard)|Fettgedruckter Text (Strg+B)|Kursiver Text (Strg+I)|Unterstrichener Text (Strg+U)|Durchgestrichener Text|Formatierung entfernen & auf reines Markdown zurücksetzen|Textfarbe anwenden|Textfarbe wählen|Hervorhebungs- / Hintergrundfarbe anwenden|Hintergrundfarbe wählen|Text linksbündig ausrichten|Text zentrieren|Text rechtsbündig ausrichten|Einzug verkleinern|Einzug vergrößern|Klicken, um Zoom auf 100% zurückzusetzen|Wörter|Zeichen|Min Lesezeit|Keine Hervorhebung / Standard|Standard-Textfarbe|Benutzerdefiniert:|Ausschneiden|Kopieren|Einfügen (Text)|Einfügen durch Browser blockiert. Bitte Strg+V verwenden.|Fett|Kursiv|Unterstrichen|Hervorheben|Formatierung löschen|Im Web suchen|Auswahl übersetzen|Übersetzen|Übersetze...|Auswahl ersetzen|In Google Translate öffnen|Inline-Übersetzung nicht verfügbar. Klicken Sie auf ↗, um Google Translate zu öffnen.|In Notiz suchen...|Ersetzen durch...|Suchen|Alle ersetzen|Umbenennen fehlgeschlagen:|Kopiert!"
TRANSLATIONS[es]="Nueva nota|Título de la nota:|Sin título|Error al crear la nota.|YAML Frontmatter|Cargando Excalidraw:|No se encontró el archivo Excalidraw:|No se puede renderizar el dibujo Excalidraw.|Lienzo Excalidraw vacío|Error al guardar|Ingresar URL o [[WikiLink]]:|Elemento de tarea|Texto de la llamada...|Columna 1|Columna 2|Encabezado 1|Encabezado 2|Celda 1|Celda 2|Volver a la vista de carpeta / árbol|Crear una nueva nota en Vault|Deshacer última acción (Ctrl+Z)|Rehacer última acción (Ctrl+Y)|Insertar lista de viñetas|Insertar lista numerada|Insertar casilla de verificación interactiva|Insertar línea divisoria horizontal|Insertar hipervínculo o [[WikiLink]] (Ctrl+K)|Insertar caja de llamada de Obsidian|Insertar diseño de varias columnas lado a lado|Insertar bloque de código con resaltado de sintaxis|Insertar tabla de datos|Autoformatear y alinear tablas Markdown|Cargar e incrustar imagen o archivo adjunto|Buscar y reemplazar texto en nota (Ctrl+F)|Cambiar a editor visual WYSIWYG|Cambiar a editor de código Markdown sin formato|Cambios sin guardar: haga clic para guardar ahora (Ctrl+S)|Nivel de párrafo y encabezado (P, H1–H5)|Tamaño de fuente (15pt es predeterminado)|Texto en negrita (Ctrl+B)|Texto en cursiva (Ctrl+I)|Subrayar texto (Ctrl+U)|Tachar texto|Eliminar formato y restablecer a Markdown limpio|Aplicar color de texto|Elegir color de texto|Aplicar color de resaltado / fondo|Elegir color de resaltado|Alinear texto a la izquierda|Centrar texto|Alinear texto a la derecha|Disminuir sangría|Aumentar sangría|Haga clic para restablecer el zoom al 100%|palabras|caracteres|min de lectura|Sin resaltado / Predeterminado|Color de texto predeterminado|Personalizado:|Cortar|Copiar|Pegar (Texto)|Pegado bloqueado por el navegador. Use Ctrl+V.|Negrita|Cursiva|Subrayado|Resaltar|Borrar formato|Buscar en la web|Traducir selección|Traducir|Traduciendo...|Reemplazar selección|Abrir en Google Translate|Traducción en línea no disponible. Haga clic en ↗ para abrir Google Translate.|Buscar en nota...|Reemplazar con...|Buscar|Reemplazar todo|Error al renombrar:|¡Copiado!"
TRANSLATIONS[fr]="Nouvelle note|Titre de la note :|Sans titre|Échec de la création de la note.|YAML Frontmatter|Chargement d'Excalidraw :|Fichier Excalidraw introuvable :|Impossible de rendre le dessin Excalidraw.|Toile Excalidraw vide|Erreur de sauvegarde|Entrez l'URL ou le [[WikiLink]] :|Tâche|Texte de la légende...|Colonne 1|Colonne 2|En-tête 1|En-tête 2|Cellule 1|Cellule 2|Retour   la vue dossier / arborescence|Créer une nouvelle note dans le coffre|Annuler la dernière action (Ctrl+Z)|Refaire la dernière action (Ctrl+Y)|Insérer une liste   puces|Insérer une liste numérotée|Insérer une case   cocher de tâche interactive|Insérer une ligne de séparation horizontale|Insérer un hyperlien ou un [[WikiLink]] (Ctrl+K)|Insérer une boîte de légende Obsidian|Insérer une disposition en plusieurs colonnes côte   côte|Insérer un bloc de code avec coloration syntaxique|Insérer un tableau de données|Auto-formater & aligner les tableaux Markdown|Télécharger et intégrer une image ou une pièce jointe|Rechercher et remplacer du texte dans la note (Ctrl+F)|Passer   l'éditeur visuel WYSIWYG|Passer   l'éditeur de code Markdown brut|Modifications non enregistrées — Cliquez pour enregistrer maintenant (Ctrl+S)|Niveau de paragraphe et d'en-tête (P, H1-H5)|Taille de police (15pt par défaut)|Texte en gras (Ctrl+B)|Texte en italique (Ctrl+I)|Souligner le texte (Ctrl+U)|Barrer le texte|Supprimer le formatage et réinitialiser vers un Markdown propre|Appliquer la couleur du texte|Choisir la couleur du texte|Appliquer la couleur de surbrillance / d'arrière-plan|Choisir la couleur de surbrillance|Aligner le texte   gauche|Centrer le texte|Aligner le texte   droite|Diminuer le retrait|Augmenter le retrait|Cliquez pour réinitialiser le zoom   100 %|mots|caractères|min de lecture|Aucune surbrillance / Par défaut|Couleur de texte par défaut|Personnalisé :|Couper|Copier|Coller (Texte)|Collage bloqué par le navigateur. Veuillez utiliser Ctrl+V.|Gras|Italique|Souligné|Surbrillance|Effacer le formatage|Rechercher sur le Web|Traduire la sélection|Traduire|Traduction en cours...|Remplacer la sélection|Ouvrir dans Google Translate|Traduction en ligne indisponible. Cliquez sur ↗ pour ouvrir Google Translate.|Rechercher dans la note...|Remplacer par...|Rechercher|Remplacer tout|Échec du changement de nom :|Copié !"
TRANSLATIONS[it]="Nuova nota|Titolo della nota:|Senza titolo|Impossibile creare la nota.|YAML Frontmatter|Caricamento Excalidraw:|File Excalidraw non trovato:|Impossibile renderizzare il disegno Excalidraw.|Tela Excalidraw vuota|Errore di salvataggio|Inserisci URL o [[WikiLink]]:|Voce di attivit |Testo del richiamo...|Colonna 1|Colonna 2|Intestazione 1|Intestazione 2|Cella 1|Cella 2|Torna alla vista cartella / albero|Crea una nuova nota nel Vault|Annulla l'ultima azione (Ctrl+Z)|Ripeti l'ultima azione (Ctrl+Y)|Inserisci elenco puntato|Inserisci elenco numerato|Inserisci casella di controllo attivit  interattiva|Inserisci linea divisoria orizzontale|Inserisci collegamento ipertestuale o [[WikiLink]] (Ctrl+K)|Inserisci casella di richiamo Obsidian|Inserisci layout a più colonne affiancate|Inserisci blocco di codice con evidenziazione della sintassi|Inserisci tabella dati|Formatta e allinea automaticamente le tabelle Markdown|Carica e incorpora immagine o allegato|Trova e sostituisci testo nella nota (Ctrl+F)|Passa all'editor visivo WYSIWYG|Passa all'editor di codice Markdown non formattato|Modifiche non salvate — Fai clic per salvare ora (Ctrl+S)|Livello di paragrafo e intestazione (P, H1–H5)|Dimensione carattere (15pt è il valore predefinito)|Testo in grassetto (Ctrl+B)|Testo in corsivo (Ctrl+I)|Testo sottolineato (Ctrl+U)|Testo barrato|Rimuovi formattazione e ripristina a Markdown pulito|Applica colore del testo|Scegli il colore del testo|Applica colore di evidenziazione / sfondo|Scegli il colore di evidenziazione|Allinea testo a sinistra|Centra testo|Allinea testo a destra|Riduci rientro|Aumenta rientro|Fai clic per ripristinare lo zoom al 100%|parole|caratteri|min di lettura|Nessuna evidenziazione / Predefinito|Colore del testo predefinito|Personalizzato:|Taglia|Copia|Incolla (Testo)|Incolla bloccato dal browser. Utilizzare Ctrl+V.|Grassetto|Corsivo|Sottolineato|Evidenzia|Cancella formattazione|Cerca nel Web|Traduci selezione|Traduci|Traduzione in corso...|Sostituisci selezione|Apri in Google Translate|Traduzione in linea non disponibile. Fai clic su ↗ per aprire Google Translate.|Trova nella nota...|Sostituisci con...|Trova|Sostituisci tutto|Rinomina non riuscita:|Copiato!"
TRANSLATIONS[pt]="Nova Nota|Título da Nota:|Sem título|Falha ao criar nota.|YAML Frontmatter|Carregando Excalidraw:|Arquivo Excalidraw não encontrado:|Incapaz de renderizar o desenho Excalidraw.|Tela Excalidraw Vazia|Erro ao salvar|Insira a URL ou [[WikiLink]]:|Item de tarefa|Texto da chamada...|Coluna 1|Coluna 2|Cabeçalho 1|Cabeçalho 2|Célula 1|Célula 2|Voltar para visualização de pasta / árvore|Criar uma Nova Nota no Vault|Desfazer última ação (Ctrl+Z)|Refazer última ação (Ctrl+Y)|Inserir lista de marcadores|Inserir lista numerada|Inserir caixa de seleção de tarefa interativa|Inserir linha divisória horizontal|Inserir hiperlink ou [[WikiLink]] (Ctrl+K)|Inserir caixa de chamada Obsidian|Inserir layout de várias colunas lado a lado|Inserir bloco de código com realce de sintaxe|Inserir tabela de dados|Formatar e alinhar automaticamente tabelas Markdown|Fazer upload e incorporar imagem ou anexo|Localizar e substituir texto na nota (Ctrl+F)|Alternar para editor visual WYSIWYG|Alternar para editor de código Markdown bruto|Alterações não salvas — Clique para salvar agora (Ctrl+S)|Nível de parágrafo e título (P, H1–H5)|Tamanho da fonte (15pt é o padrão)|Texto em negrito (Ctrl+B)|Texto em itálico (Ctrl+I)|Sublinhar texto (Ctrl+U)|Texto tachado|Remover formatação e redefinir para Markdown limpo|Aplicar cor do texto|Escolher cor do texto|Aplicar cor de destaque / fundo|Escolher cor de destaque|Alinhar texto   esquerda|Centralizar texto|Alinhar texto   direita|Diminuir recuo|Aumentar recuo|Clique para redefinir o zoom para 100%|palavras|caracteres|min de leitura|Sem Destaque / Padrão|Cor de Texto Padrão|Personalizado:|Cortar|Copiar|Colar (Texto)|Colar bloqueado pelo navegador. Por favor, use Ctrl+V.|Negrito|Itálico|Sublinhado|Destaque|Limpar Formatação|Pesquisar na Web|Traduzir Seleção|Traduzir|Traduzindo...|Substituir Seleção|Abrir no Google Translate|Tradução inline indisponível. Clique em ↗ para abrir o Google Translate.|Localizar na nota...|Substituir por...|Localizar|Substituir Tudo|Falha ao renomear:|Copiado!"
TRANSLATIONS[ru]="Новая заметка|Название заметки:|Без названия|Не удалось создать заметку.|YAML Frontmatter|Загрузка Excalidraw:|Файл Excalidraw не найден:|Невозможно отобразить рисунок Excalidraw.|Пустой холст Excalidraw|Ошибка сохранения|Введите URL или [[WikiLink]]:|Задача|Текст выноски...|Столбец 1|Столбец 2|Заголовок 1|Заголовок 2|Ячейка 1|Ячейка 2|Вернуться к просмотру папки / дерева|Создать новую заметку в Хранилище|Отменить последнее действие (Ctrl+Z)|Повторить последнее действие (Ctrl+Y)|Вставить маркированный список|Вставить нумерованный список|Вставить интерактивный флажок задачи|Вставить горизонтальную разделительную линию|Вставить гиперссылку или [[WikiLink]] (Ctrl+K)|Вставить блок выноски Obsidian|Вставить многоколоночный макет|Вставить блок кода с подсветкой синтаксиса|Вставить таблицу данных|Автоформатирование и выравнивание таблиц Markdown|Загрузить и встроить изображение или вложение|Найти и заменить текст в заметке (Ctrl+F)|Переключиться на визуальный редактор WYSIWYG|Переключиться на редактор исходного кода Markdown|Несохраненные изменения — Нажмите, чтобы сохранить сейчас (Ctrl+S)|Уровень абзаца и заголовка (P, H1–H5)| азмер шрифта (по умолчанию 15pt)|Жирный текст (Ctrl+B)|Курсив (Ctrl+I)|Подчеркнутый текст (Ctrl+U)|Зачеркнутый текст|Удалить форматирование и сбросить до чистого Markdown|Применить цвет текста|Выбрать цвет текста|Применить цвет выделения / фона|Выбрать цвет выделения|Выровнять текст по левому краю|Выровнять текст по центру|Выровнять текст по правому краю|Уменьшить отступ|Увеличить отступ|Нажмите, чтобы сбросить масштаб до 100%|слов|символов|мин чтения|Без выделения / По умолчанию|Цвет текста по умолчанию|Пользовательский:|Вырезать|Копировать|Вставить (Текст)|Вставка заблокирована браузером. Пожалуйста, используйте Ctrl+V.|Жирный|Курсив|Подчеркнутый|Выделение|Очистить форматирование|Искать в Интернете|Перевести выделенное|Перевести|Перевод...|Заменить выделенное|Открыть в Google Translate|Встроенный перевод недоступен. Нажмите ↗, чтобы открыть Google Translate.|Найти в заметке...|Заменить на...|Найти|Заменить все|Не удалось переименовать:|Скопировано!"
TRANSLATIONS[tr]="Yeni Not|Not Başlığı:|İsimsiz|Not oluşturulamadı.|YAML Frontmatter|Excalidraw yükleniyor:|Excalidraw dosyası bulunamadı:|Excalidraw çizimi oluşturulamıyor.|Boş Excalidraw Tuvali|Kayıt hatası|URL veya [[WikiLink]] girin:|Görev öğesi|Ek bilgi metni...|Sütun 1|Sütun 2|Başlık 1|Başlık 2|Hücre 1|Hücre 2|Klasör / Ağaç Görünümüne Dön|Kasa içinde Yeni Bir Not Oluştur|Son İşlemi Geri Al (Ctrl+Z)|Son İşlemi Yinele (Ctrl+Y)|Madde İşaretli Liste Ekle|Numaralı Liste Ekle|Etkileşimli Görev Onay Kutusu Ekle|Yatay Bölücü Çizgi Ekle|Köprü veya [[WikiLink]] Ekle (Ctrl+K)|Obsidian Ek Bilgi Kutusu Ekle|Yan Yana Çoklu Sütun Düzeni Ekle|Sözdizimi Vurgulu Kod Bloğu Ekle|Veri Tablosu Ekle|Markdown Tablolarını Otomatik Biçimlendir ve Hizala|Resim veya Ek Yükle ve Göm|Notta Metin Bul ve Değiştir (Ctrl+F)|Görsel WYSIWYG Düzenleyiciye Geç|Ham Markdown Kod Düzenleyicisine Geç|Kaydedilmemiş değişiklikler — Şimdi kaydetmek için tıklayın (Ctrl+S)|Paragraf ve Başlık Düzeyi (P, H1–H5)|Metin Yazı Tipi Boyutu (Varsayılan 15pt)|Kalın Metin (Ctrl+B)|Eğik Metin (Ctrl+I)|Altı Çizili Metin (Ctrl+U)|Üstü Çizili Metin|Biçimlendirmeyi Kaldır ve Temiz Markdown'a Sıfırla|Metin Rengini Uygula|Metin Rengini Seç|Vurgu / Arka Plan Rengini Uygula|Vurgu Rengini Seç|Metni Sola Hizala|Metni Ortala|Metni Sağa Hizala|Girintiyi Azalt|Girintiyi Artır|Yakınlaştırmayı %100'e sıfırlamak için tıklayın|kelime|karakter|dk okuma|Vurgu Yok / Varsayılan|Varsayılan Metin Rengi|Özel:|Kes|Kopyala|Yapıştır (Metin)|Tarayıcı tarafından yapıştırma engellendi. Lütfen Ctrl+V kullanın.|Kalın|Eğik|Altı Çizili|Vurgula|Biçimlendirmeyi Temizle|Web'de Ara|Seçimi Çevir|Çevir|Çevriliyor...|Seçimi Değiştir|Google Çeviri'de Aç|Satır içi çeviri kullanılamıyor. Google Çeviri'yi açmak için ↗ işaretine tıklayın.|Not içinde bul...|Şununla değiştir...|Bul|Tümünü Değiştir|Yeniden adlandırma başarısız:|Kopyalandı!"
TRANSLATIONS[zh-cn]="新建笔记|笔记 题：|  题|创建笔记失败。|YAML Frontmatter|正在 载 Excalidraw：|找不到 Excalidraw 文件：| 法渲染 Excalidraw 绘图。|空的 Excalidraw 画布|保存错误|输入 URL 或 [[WikiLink]]：|任务项| 注文本...|第 1 列|第 2 列| 题 1| 题 2|单元  1|单元  2|返回文件夹 /  状视图|在仓库中创建新笔记|撤销上一步操作 (Ctrl+Z)|重做上一步操作 (Ctrl+Y)|插入 序列表|插入有序列表|插入交互式任务复选框|插入水平分割线|插入超链接或 [[WikiLink]] (Ctrl+K)|插入 Obsidian  注框|插入并排多列布局|插入带有语法高亮的代 块|插入数据表|自动 式化并对齐 Markdown 表 |上 并嵌入图像或附件|在笔记中查找和替换文本 (Ctrl+F)|切换到所见即所得 (WYSIWYG) 视觉编辑器|切换到纯 Markdown 代 编辑器|未保存的更改 — 点击立即保存 (Ctrl+S)|段落和 题级别 (P, H1–H5)|文本字体大小 (默认 15pt)|粗体文本 (Ctrl+B)|斜体文本 (Ctrl+I)|下划线文本 (Ctrl+U)| 除线文本| 除 式并重置为纯 Markdown|应用文本颜色|选择文本颜色|应用高亮 / 背景颜色|选择高亮颜色|文本左对齐|文本居中|文本右对齐|减少缩进|增 缩进|点击重置缩放至 100%|字|字符|分钟阅读| 高亮 / 默认|默认文本颜色|自定义：|剪切|复制|粘贴 (文本)|浏览器拦截了粘贴操作，请使用 Ctrl+V。|粗体|斜体|下划线|高亮|清除 式|在网上搜索|翻译选中内容|翻译|翻译中...|替换选中内容|在 Google 翻译中打开| 法提供内联翻译，请点击 ↗ 打开 Google 翻译。|在笔记中查找...|替换为...|查找|全部替换|重命名失败：|已复制！"
TRANSLATIONS[ja]="新規ノート|ノートのタイトル:|無題|ノートの作成に失敗しました。|YAML Frontmatter|Excalidraw を読み込み中:|Excalidraw ファイルが見つかりません:|Excalidraw の描画をレンダリングできません。|空の Excalidraw キャンバス|保存エラー|URL または [[WikiLink]] を入力:|タスク 目|コールアウトのテキスト...|列 1|列 2|見出し 1|見出し 2|セル 1|セル 2|フォルダ / ツリービューに戻る|Vault に新しいノートを作成|元に戻す (Ctrl+Z)|やり直し (Ctrl+Y)|箇条書きリストを挿入|番号付きリストを挿入|インタラクティブなタスクチェックボックスを挿入|水平分割線を挿入|ハイパーリンクまたは [[WikiLink]] を挿入 (Ctrl+K)|Obsidian コールアウトボックスを挿入|並列の複数列レイアウトを挿入|構文ハイライト付きコードブロックを挿入|データテーブルを挿入|Markdown テーブルの自動フォーマットと配置|画像または添付ファイルをアップロードして埋め込む|ノート内のテキストを検索して置換 (Ctrl+F)|ビジュアル WYSIWYG エディターに切り替え|生の Markdown コードエディターに切り替え|未保存の変更 — クリックして今すぐ保存 (Ctrl+S)|段落と見出しレベル (P, H1–H5)|テキストのフォントサイズ (デフォルトは 15pt)|太字テキスト (Ctrl+B)|斜体テキスト (Ctrl+I)|下線テキスト (Ctrl+U)|取り消し線テキスト|フォーマットを削除してクリーンな Markdown にリセット|テキストの色を適用|テキストの色を選択|ハイライト / 背景色を適用|ハイライトの色を選択|テキストを左揃え|テキストを中央揃え|テキストを右揃え|インデントを減らす|インデントを増やす|クリックしてズー を 100% にリセット|単語|文字|分で読める|ハイライトなし / デフォルト|デフォルトのテキスト色|カスタ :|切り取り|コピー|貼り付け (テキスト)|ブラウザにより貼り付けがブロックされました。Ctrl+V を使用してく さい。|太字|斜体|下線|ハイライト|フォーマットをクリア|ウェブで検索|選択範囲を翻訳|翻訳|翻訳中...|選択範囲を置換|Google 翻訳で開く|インライン翻訳は利用できません。↗ をクリックして Google 翻訳を開いてく さい。|ノート内を検索...|置換...|検索|すべて置換|名前の変更に失敗しました:|コピーしました！"
TRANSLATIONS[ko]="새 노트|노트  목:| 목 없음|노트 생성에 실패했습니다.|YAML Frontmatter|Excalidraw 로딩 중:|Excalidraw 파일을 찾을 수 없습니다:|Excalidraw  더링을   수 없습니다.|빈 Excalidraw 캔버스| 장 오류|URL 또는 [[WikiLink]] 입 :|작업 항목|콜아웃 텍스트...|열 1|열 2|헤더 1|헤더 2|셀 1|셀 2|폴더 / 트리 보기로 돌아가기|보관소에 새 노트 생성|마지막 작업 실행 취소 (Ctrl+Z)|마지막 작업 다시 실행 (Ctrl+Y)|글머리 기호 목록 삽입|번호 매기기 목록 삽입|대화형 작업 확인란 삽입|가로 구분  삽입|하이퍼링크 또는 [[WikiLink]] 삽입 (Ctrl+K)|Obsidian 콜아웃 상자 삽입|나란히 다중 열  이아웃 삽입|구문 강조 코드 블록 삽입|데이터 테이블 삽입|Markdown 테이블 자동 포맷 및   |이미지 또는 첨부 파일 업로드 및 포함|노트에서 텍스트 찾기 및 바꾸기 (Ctrl+F)|시각  WYSIWYG 편집기로  환|원시 Markdown 코드 편집기로  환| 장되지 않은 변경 사항 — 지금  장하 면 클릭하세요 (Ctrl+S)|문단 및  목 수준 (P, H1–H5)|텍스트 글꼴 크기 (기본값 15pt)|굵은 텍스트 (Ctrl+B)|기울임꼴 텍스트 (Ctrl+I)|밑줄 텍스트 (Ctrl+U)|취소  텍스트|서식  거 및 깔끔한 Markdown으로 재설 |텍스트 색상  용|텍스트 색상  택|강조 / 배경색  용|강조 색상  택|텍스트 왼쪽   |텍스트 가운데   |텍스트 오른쪽   |들여쓰기 감소|들여쓰기 증가|클릭하여 줌을 100%로 재설 |단어|문자|분 읽기|강조 없음 / 기본값|기본 텍스트 색상|사용자 지 :|잘라내기|복사|붙여넣기 (텍스트)|브라우 에 의해 붙여넣기가 차단되었습니다. Ctrl+V를 사용하세요.|굵게|기울임꼴|밑줄|강조|서식 지우기|웹에서 검색| 택 영역 번역|번역|번역 중...| 택 영역 바꾸기|Google 번역에서 열기|인라인 번역을 사용  수 없습니다. ↗ 를 클릭하여 Google 번역을 여세요.|노트에서 찾기...|바꿀 내용...|찾기|모두 바꾸기|이름 바꾸기 실패:|복사됨!"
TRANSLATIONS[ar]="ملاحظة جديدة|عنوان الملاحظة:|بدون عنوان|فشل في إنشاء ملاحظة.|YAML Frontmatter|جاري تحميل Excalidraw:|لم يتم العثور على ملف Excalidraw:|غير قادر على عرض رسم Excalidraw.|لوحة Excalidraw فارغة|خطأ في الحفظ|أدخل الرابط أو [[WikiLink]]:|عنصر مهمة|نص التنبيه...|العمود 1|العمود 2|رأس 1|رأس 2|خلية 1|خلية 2|العودة إلى عرض المجلد / الشجرة|إنشاء ملاحظة جديدة في المخزن|تراجع عن الإجراء الأخير (Ctrl+Z)|إعادة الإجراء الأخير (Ctrl+Y)|إدراج قائمة نقطية|إدراج قائمة مرقمة|إدراج مربع اختيار مهمة تفاعلي|إدراج خط تقسيم أفقي|إدراج رابط تشعبي أو [[WikiLink]] (Ctrl+K)|إدراج مربع تنبيه Obsidian|إدراج تخطيط متعدد الأعمدة جنباً إلى جنب|إدراج كتلة برمجية مع تمييز الصيغة|إدراج جدول بيانات|تنسيق ومحاذاة جداول Markdown تلقائياً|تحميل وتضمين صورة أو مرفق|بحث واستبدال النص في الملاحظة (Ctrl+F)|التبديل إلى المحرر المرئي WYSIWYG|التبديل إلى محرر كود Markdown الخام|تغييرات غير محفوظة — انقر للحفظ الآن (Ctrl+S)|مستوى الفقرة والعنوان (P, H1–H5)|حجم خط النص (15pt هو الافتراضي)|نص غامق (Ctrl+B)|نص مائل (Ctrl+I)|تسطير النص (Ctrl+U)|يتوسطه خط|إزالة التنسيق وإعادة التعيين إلى Markdown نظيف|تطبيق لون النص|اختر لون النص|تطبيق لون التمييز / الخلفية|اختر لون التمييز|محاذاة النص لليسار|توسيط النص|محاذاة النص لليمين|إنقاص المسافة البادئة|زيادة المسافة البادئة|انقر لإعادة تعيين التكبير إلى 100%|كلمات|حروف|دقيقة قراءة|بدون تمييز / افتراضي|لون النص الافتراضي|مخصص:|قص|نسخ|لصق (نص)|تم حظر اللصق بواسطة المتصفح. يرجى استخدام Ctrl+V.|غامق|مائل|تسطير|تمييز|مسح التنسيق|ابحث في الويب|ترجمة التحديد|ترجمة|جاري الترجمة...|استبدال التحديد|الفتح في ترجمة جوجل|الترجمة المضمنة غير متوفرة. انقر على ↗ لفتح ترجمة جوجل.|بحث في الملاحظة...|استبدال بـ...|بحث|استبدال الكل|فشل إعادة التسمية:|تم النسخ!"
TRANSLATIONS[fa]="یادداشت جدید|عنوان یادداشت:|بدون عنوان|ایجاد یادداشت ناموفق بود.|YAML Frontmatter|در حال بارگذاری Excalidraw:|فایل Excalidraw یافت نشد:|قادر به رندر کردن رسم Excalidraw نیست.|بوم خالی Excalidraw|خطای ذخیره|آدرس یا [[WikiLink]] را وارد کنید:|مورد وظیفه|متن فراخوانی...|ستون ۱|ستون ۲|سرآیند ۱|سرآیند ۲|سلول ۱|سلول ۲|بازگشت به نمای پوشه / درخت|ایجاد یک یادداشت جدید در مخزن|لغو آخرین عمل (Ctrl+Z)|انجام مجدد آخرین عمل (Ctrl+Y)|درج لیست نقطه‌ای|درج لیست شماره‌دار|درج کادر بررسی وظیفه تعاملی|درج خط تقسیم‌کننده افقی|درج پیوند یا [[WikiLink]] (Ctrl+K)|درج کادر فراخوانی Obsidian|درج چیدمان چند ستونی در کنار هم|درج بلوک کد با برجسته‌سازی نحو|درج جدول داده‌ها|قالب‌بندی و تراز کردن خودکار جداول Markdown|آپلود و جاسازی تصویر یا پیوست|یافتن و جایگزینی متن در یادداشت (Ctrl+F)|تغییر به ویرایشگر بصری WYSIWYG|تغییر به ویرایشگر کد خام Markdown|تغییرات ذخیره نشده — برای ذخیره اکنون کلیک کنید (Ctrl+S)|سطح پاراگراف و عنوان (P, H1–H5)|اندازه فونت متن (15pt پیش‌فرض است)|متن پررنگ (Ctrl+B)|متن ایتالیک (Ctrl+I)|زیرخط دار کردن متن (Ctrl+U)|متن خط خورده|حذف قالب‌بندی و بازنشانی به Markdown تمیز|اعمال رنگ متن|انتخاب رنگ متن|اعمال رنگ برجسته / پس‌زمینه|انتخاب رنگ برجسته|تراز کردن متن به چپ|وسط چین کردن متن|تراز کردن متن به راست|کاهش تو رفتگی|افزایش تو رفتگی|برای بازنشانی بزرگنمایی به ۱۰۰٪ کلیک کنید|کلمه|نویسه|دقیقه خواندن|بدون برجستگی / پیش‌فرض|رنگ متن پیش‌فرض|سفارشی:|برش|کپی|جای‌گذاری (متن)|جای‌گذاری توسط مرورگر مسدود شده است. لطفاً از Ctrl+V استفاده کنید.|پررنگ|ایتالیک|زیرخط|برجسته|پاک کردن قالب‌بندی|جستجو در وب|ترجمه انتخاب|ترجمه|در حال ترجمه...|جایگزینی انتخاب|باز کردن در مترجم گوگل|ترجمه درون‌خطی در دسترس نیست. برای باز کردن مترجم گوگل روی ↗ کلیک کنید.|یافتن در یادداشت...|جایگزینی با...|یافتن|جایگزینی همه|تغییر نام ناموفق بود:|کپی شد!"
TRANSLATIONS[hi]="नया नोट|नोट शीर्षक:|अनाम|नोट बनाने में विफल।|YAML Frontmatter|Excalidraw लोड हो रहा है:|Excalidraw फ़ाइल नहीं मिली:|Excalidraw चित्र प्रस्तुत करने में असमर्थ।|खाली Excalidraw कैनवास|सहेजने में त्रुटि|URL या [[WikiLink]] दर्ज करें:|कार्य आइटम|कॉलआउट टेक्स्ट...|कॉलम 1|कॉलम 2|शीर्षक 1|शीर्षक 2|सेल 1|सेल 2|फ़ोल्डर / ट्री दृश्य पर वापस जाएं|वॉल्ट में एक नया नोट बनाएं|अंतिम कार्रवाई पूर्ववत करें (Ctrl+Z)|अंतिम कार्रवाई फिर से करें (Ctrl+Y)|बुलेटेड सूची डालें|क्रमांकित सूची डालें|इंटरएक्टिव कार्य चेकबॉक्स डालें|क्षैतिज विभक्त रेखा डालें|हाइपरलिंक या [[WikiLink]] डालें (Ctrl+K)|Obsidian कॉलआउट बॉक्स डालें|साइड-बाय-साइड मल्टी-कॉलम लेआउट डालें|सिंटैक्स-हाइलाइटेड कोड ब्लॉक डालें|डेटा तालिका डालें|Markdown तालिकाओं को स्वतः-प्रारूप और संरेखित करें|छवि या अनुलग्नक अपलोड और एम्बेड करें|नोट में टेक्स्ट खोजें और बदलें (Ctrl+F)|विज़ुअल WYSIWYG संपादक पर स्विच करें|रॉ Markdown कोड संपादक पर स्विच करें|सहेजे नहीं गए परिवर्तन — अभी सहेजने के लिए क्लिक करें (Ctrl+S)|अनुच्छेद और शीर्षक स्तर (P, H1–H5)|टेक्स्ट फ़ॉन्ट आकार (15pt डिफ़ॉल्ट है)|बोल्ड टेक्स्ट (Ctrl+B)|इटैलिक टेक्स्ट (Ctrl+I)|अंडरलाइन टेक्स्ट (Ctrl+U)|स्ट्राइकथ्रू टेक्स्ट|फ़ॉर्मेटिंग निकालें और स्वच्छ Markdown पर रीसेट करें|टेक्स्ट रंग लागू करें|टेक्स्ट रंग चुनें|हाइलाइट / पृष् भूमि रंग लागू करें|हाइलाइट रंग चुनें|टेक्स्ट बाएँ संरेखित करें|केंद्र टेक्स्ट|टेक्स्ट दाएँ संरेखित करें|इंडेंट घटाएं|इंडेंट बढ़ाएं|ज़ूम को 100% पर रीसेट करने के लिए क्लिक करें|शब्द|अक्षर|मिनट पढ़ने का समय|कोई हाइलाइट नहीं / डिफ़ॉल्ट|डिफ़ॉल्ट टेक्स्ट रंग|कस्टम:|कट करें|कॉपी करें|पेस्ट करें (टेक्स्ट)|ब्राउज़र द्वारा पेस्ट अवरुद्ध कर दिया गया। कृपया Ctrl+V का उपयोग करें।|बोल्ड|इटैलिक|अंडरलाइन|हाइलाइट|फ़ॉर्मेटिंग साफ़ करें|वेब पर खोजें|चयन का अनुवाद करें|अनुवाद करें|अनुवाद हो रहा है...|चयन बदलें|Google अनुवाद में खोलें|इनलाइन अनुवाद अनुपलब्ध। Google अनुवाद खोलने के लिए ↗ पर क्लिक करें।|नोट में खोजें...|इसके साथ बदलें...|खोजें|सभी बदलें|नाम बदलना विफल:|कॉपी हो गया!"
TRANSLATIONS[vi]="Ghi chú mới|Tiêu đề ghi chú:|Chưa có tiêu đề|Không thể tạo ghi chú.|YAML Frontmatter|Đang tải Excalidraw:|Không tìm thấy tệp Excalidraw:|Không thể hiển thị bản vẽ Excalidraw.|Khung vẽ Excalidraw trống|Lỗi lưu|Nhập URL hoặc [[WikiLink]]:|Mục công việc|Văn bản chú thích...|Cột 1|Cột 2|Tiêu đề 1|Tiêu đề 2|Ô 1|Ô 2|Quay lại Chế độ xem Thư mục / Cây|Tạo một Ghi chú Mới trong Vault|Ho n tác h nh động trước (Ctrl+Z)|L m lại h nh động trước (Ctrl+Y)|Chèn Danh sách dấu đầu dòng|Chèn Danh sách được đánh số|Chèn Hộp kiểm công việc tương tác|Chèn Đường chia ngang|Chèn Siêu liên kết hoặc [[WikiLink]] (Ctrl+K)|Chèn Hộp chú thích Obsidian|Chèn Bố cục nhiều cột cạnh nhau|Chèn Khối mã có tô sáng cú pháp|Chèn Bảng dữ liệu|Tự động Định dạng & Căn chỉnh Bảng Markdown|Tải lên & Nhúng Hình ảnh hoặc Tệp đính kèm|Tìm & Thay thế Văn bản trong Ghi chú (Ctrl+F)|Chuyển sang Trình chỉnh sửa trực quan WYSIWYG|Chuyển sang Trình chỉnh sửa mã Markdown thô|Các thay đổi chưa được lưu — Nhấp để lưu ngay (Ctrl+S)|Đoạn văn & Mức tiêu đề (P, H1–H5)|Kích thước Phông chữ Văn bản (Mặc định l  15pt)|Văn bản In đậm (Ctrl+B)|Văn bản In nghiêng (Ctrl+I)|Gạch chân Văn bản (Ctrl+U)|Văn bản Gạch ngang|Xóa Định dạng & Đặt lại th nh Markdown sạch|Áp dụng M u văn bản|Chọn M u văn bản|Áp dụng M u tô sáng / Nền|Chọn M u tô sáng|Căn trái Văn bản|Căn giữa Văn bản|Căn phải Văn bản|Giảm Thụt lề|Tăng Thụt lề|Nhấp để đặt lại thu phóng về 100%|từ|ký tự|phút đọc|Không Tô sáng / Mặc định|M u văn bản Mặc định|Tùy chỉnh:|Cắt|Sao chép|Dán (Văn bản)|Dán bị trình duyệt chặn. Vui lòng sử dụng Ctrl+V.|In đậm|In nghiêng|Gạch chân|Tô sáng|Xóa Định dạng|Tìm kiếm trên Web|Dịch Lựa chọn|Dịch|Đang dịch...|Thay thế Lựa chọn|Mở trong Google Dịch|Bản dịch nội tuyến không khả dụng. Nhấp ↗ để mở Google Dịch.|Tìm trong ghi chú...|Thay thế bằng...|Tìm|Thay thế Tất cả|Đổi tên không th nh công:|Đã sao chép!"
TRANSLATIONS[uk]="Нова нотатка|Назва нотатки:|Без назви|Не вдалося створити нотатку.|YAML Frontmatter|Завантаження Excalidraw:|Файл Excalidraw не знайдено:|Не вдалося відрендерити малюнок Excalidraw.|Порожнє полотно Excalidraw|Помилка збереження|Введіть URL або [[WikiLink]]:|Елемент завдання|Текст виноски...|Стовпець 1|Стовпець 2|Заголовок 1|Заголовок 2|Комірка 1|Комірка 2|Повернутися до перегляду папки / дерева|Створити нову нотатку у Сховищі|Скасувати останню дію (Ctrl+Z)|Повторити останню дію (Ctrl+Y)|Вставити маркований список|Вставити нумерований список|Вставити інтерактивний прапорець завдання|Вставити горизонтальну роздільну лінію|Вставити гіперпосилання або [[WikiLink]] (Ctrl+K)|Вставити блок виноски Obsidian|Вставити багатоколонковий макет поруч|Вставити блок коду з підсвічуванням синтаксису|Вставити таблицю даних|Автоформатування та вирівнювання таблиць Markdown|Завантажити та вбудувати зображення або вкладення|Знайти та замінити текст у нотатці (Ctrl+F)|Перейти до візуального редактора WYSIWYG|Перейти до редактора чистого коду Markdown|Незбережені зміни — Натисніть, щоб зберегти зараз (Ctrl+S)| івень абзацу та заголовка (P, H1–H5)| озмір шрифту тексту (за замовчуванням 15pt)|Жирний текст (Ctrl+B)|Курсив (Ctrl+I)|Підкреслений текст (Ctrl+U)|Закреслений текст|Видалити форматування та скинути до чистого Markdown|Застосувати колір тексту|Вибрати колір тексту|Застосувати колір виділення / фону|Вибрати колір виділення|Вирівняти текст ліворуч|Вирівняти текст по центру|Вирівняти текст праворуч|Зменшити відступ|Збільшити відступ|Натисніть, щоб скинути масштаб до 100%|слів|символів|хв читання|Без виділення / За замовчуванням|Колір тексту за замовчуванням|Користувацький:|Вирізати|Копіювати|Вставити (Текст)|Вставка заблокована браузером. Будь ласка, використовуйте Ctrl+V.|Жирний|Курсив|Підкреслений|Виділення|Очистити форматування|Шукати в Інтернеті|Перекласти виділене|Перекласти|Переклад...|Замінити виділене|Відкрити в Google Translate|Вбудований переклад недоступний. Натисніть ↗, щоб відкрити Google Translate.|Знайти в нотатці...|Замінити на...|Знайти|Замінити все|Не вдалося перейменувати:|Скопійовано!"
TRANSLATIONS[bar]="Neie Notiz|Notiztitl:|Unbenannt|Notiz hod ned erstellt wern kinna.|YAML Frontmatter|Excalidraw ladt:|Excalidraw-Datei ned gfundn:|Excalidraw-Buidl konn ned renderd wern.|Laare Excalidraw-Leinwand|Speicherfehler|URL oda [[WikiLink]] eigehm:|Aufgab|Callout-Text...|Spoitn 1|Spoitn 2|Kopfzeiln 1|Kopfzeiln 2|Zelln 1|Zelln 2|Zruck zua Ordnar-/Baumansicht|Neie Notiz im Vault erstelln|Letzte Aktion ruckgängig macha (Strg+Z)|Letzte Aktion wiedahoin (Strg+Y)|Aufzählungslistn eifügn|Nummerierte Listn eifügn|Interaktive Checkbox eifügn|Horizontale Trennlinie eifügn|Hyperlink oda [[WikiLink]] eifügn (Strg+K)|Obsidian Callout-Box eifügn|Meahspaltigs Layout eifügn|Code-Block mit Syntax-Highlighting eifügn|Datentabelle eifügn|Markdown-Tabelln automatisch formatiern & ausrichtn|Buidl oda Anhang hochladn & eibettn|Suacha & Ersetzn in Notiz (Strg+F)|Zum visuellen WYSIWYG-Editor wechsln|Zum reinen Markdown-Code-Editor wechsln|Ungspeicherte Änderunga — Jetzt speichan (Strg+S)|Absatz- & Überschriftnebane (P, H1–H5)|Schriftgreaß (15pt is Standard)|Fettgdruckter Text (Strg+B)|Kursiver Text (Strg+I)|Unterstrichner Text (Strg+U)|Durchgstrichner Text|Formatierung entfern & auf reins Markdown zrucksetzn|Textfarb anwendn|Textfarb wähjn|Hervorhebungs- / Hintergrundfarb anwendn|Hintergrundfarb wähjn|Text linksbündig ausrichtn|Text zentriern|Text rechtsbündig ausrichtn|Eizug verkleinern|Eizug vergrößern|Klickn, um Zoom auf 100% zruckzsetzn|Wörter|Zeichn|Min Lesezeit|Koane Hervorhebung / Standard|Standard-Textfarb|Benutzerdefiniert:|Ausschneidn|Kopiern|Eifügn (Text)|Eifügn durch Browser blockiert. Bittschön Strg+V verwendn.|Fett|Kursiv|Unterstrichn|Hervorhebn|Formatierung löschn|Im Web suacha|Auswahl ibasetzn|Ibasetzn|Ibasetze...|Auswahl ersetzn|In Google Translate öffna|Inline-Ibasetzung ned verfügbar. Klick auf ↗, um Google Translate z'öffna.|In Notiz suacha...|Ersetzn durch...|Suacha|Alle ersetzn|Umbenenna fehgschlong:|Kopiert!"
TRANSLATIONS[hes]="Neue Notiz|Notiztitel:|Ohne Name|Notiz konnt net erstellt wern.|YAML Frontmatter|Lade Excalidraw:|Excalidraw-Datei net gefunne:|Excalidraw-Zeichnung konnt net gerendert wern.|Leere Excalidraw-Leinwand|Speicherfehler|URL oder [[WikiLink]] eingebbe:|Uffgab|Callout-Text...|Spalte 1|Spalte 2|Koppzeile 1|Koppzeile 2|Zell 1|Zell 2|Zurück zur Ordner-/Baumansicht|Neue Notiz im Vault erstelle|Letzte Aktion rückgängig mache (Strg+Z)|Letzte Aktion widderhole (Strg+Y)|Uffzählungslist eifüje|Nummerierte List eifüje|Interaktive Checkbox eifüje|Horizontale Trennlinie eifüje|Hyperlink oder [[WikiLink]] eifüje (Strg+K)|Obsidian Callout-Box eifüje|Mehrspaltigs Layout eifüje|Code-Block mit Syntax-Highlighting eifüje|Datetabell eifüje|Markdown-Tabelle automatisch formatiere & ausrichte|Bild oder Ahang hochlade & eibette|Suche & Ersetze in Notiz (Strg+F)|Zum visuelle WYSIWYG-Editor wechsle|Zum reine Markdown-Code-Editor wechsle|Uggespeicherte Änderunge — Jetzt speichere (Strg+S)|Absatz- & Überschrifteebene (P, H1–H5)|Schriftgröß (15pt is Standard)|Fettgedruckter Text (Strg+B)|Kursiver Text (Strg+I)|Unnerstrichener Text (Strg+U)|Dorchgestrichener Text|Formatierung entferne & uff reins Markdown zurücksetze|Textfarb awende|Textfarb wähle|Hervorhebungs- / Hinnergrundfarb awende|Hinnergrundfarb wähle|Text linksbündig ausrichte|Text zentriere|Text rechtsbündig ausrichte|Eizug verkleinern|Eizug vergrößern|Klicke, um Zoom uff 100% zurückzusetze|Wörter|Zeiche|Min Lesezeit|Kei Hervorhebung / Standard|Standard-Textfarb|Benutzerdefiniert:|Ausschneide|Kopiere|Eifüje (Text)|Eifüje dorch Browser blockiert. Bitte Strg+V verwende.|Fett|Kursiv|Unnerstrichen|Hervorhebe|Formatierung lösche|Im Web suche|Auswahl übersetze|Übersetze|Übersetze...|Auswahl ersetze|In Google Translate öffne|Inline-Übersetzung net verfügbar. Klick uff ↗, um Google Translate zu öffne.|In Notiz suche...|Ersetze dorch...|Suche|Alle ersetze|Umbennene fehlgeschlage:|Kopiert!"
TRANSLATIONS[lb]="Nei Notiz|Notiztitel:|Ouni Titel|Notiz konnt net erstallt ginn.|YAML Frontmatter|Excalidraw lueden:|Excalidraw-Fichier net fonnt:|Excalidraw-Zeechnung konnt net gerendert ginn.|Eidel Excalidraw-Canvas|Späicherfeeler|URL oder [[WikiLink]] aginn:|Aufgab|Callout-Text...|Kolonn 1|Kolonn 2|Kappzeil 1|Kappzeil 2|Zell 1|Zell 2|Zeréck op Classeur- / Baam-Usicht|Nei Notiz am Vault erstellen|Lescht Aktioun réckgängeg maachen (Ctrl+Z)|Lescht Aktioun widderhuelen (Ctrl+Y)|Oplëschtung mat Punkte asetzen|Nummeréiert Lëscht asetzen|Interaktiv Checkbox asetzen|Horizontal Trennlinn asetzen|Hyperlink oder [[WikiLink]] asetzen (Ctrl+K)|Obsidian Callout-Box asetzen|Méispaltescht Layout asetzen|Code-Block mat Syntax-Highlighting asetzen|Datentabell asetzen|Markdown-Tabellen automatesch formatéieren & ausriichten|Bild oder Unhang eroplueden & abannen|Sichen & Ersetzen an Notiz (Ctrl+F)|Op de visuellen WYSIWYG-Editor wiesselen|Op de pure Markdown-Code-Editor wiesselen|Ongespäichert Ännerungen — Elo späicheren (Ctrl+S)|Absatz- & Iwwerschrëftenniveau (P, H1–H5)|Schrëftgréisst (15pt ass Standard)|Fettgedréckten Text (Ctrl+B)|Kursiven Text (Ctrl+I)|Ënnerstrachen Text (Ctrl+U)|Duerchgestrachen Text|Formatéierung ewechhuelen & op proppert Markdown zerécksetzen|Textfaarf uwenden|Textfaarf wielen|Hervirhiewungs- / Hannergrondfaarf uwenden|Hannergrondfaarf wielen|Text lénksbündeg ausriichten|Text zentréieren|Text rietsbündeg ausriichten|Aneréckelen verklengeren|Aneréckelen vergréisseren|Klickt, fir den Zoom op 100% zeréckzusetzen|Wierder|Zeechen|Min Lieszäit|Keng Hervirhiewung / Standard|Standard-Textfaarf|Benotzerdefinéiert:|Ausschneiden|Kopéieren|Asetzen (Text)|Asetzen vum Browser blockéiert. Benotzt wgl. Ctrl+V.|Fett|Kursiv|Ënnerstrach|Hervirhiewen|Formatéierung läschen|Um Web sichen|Auswiel iwwersetzen|Iwwersetzen|Iwwersetzen...|Auswiel ersetzen|Op Google Translate opmaachen|Inline-Iwwersetzung net verfügbar. Klickt op ↗, fir Google Translate opzemaachen.|An Notiz sichen...|Ersetzen duerch...|Sichen|All ersetzen|Ëmbenennen feelgeschloen:|Kopéiert!"
TRANSLATIONS[pcm]="New Note|Note Title:|No Name|Fail to create note.|YAML Frontmatter|Excalidraw dey load:|Excalidraw file no dey:|Cannot render Excalidraw drawing.|Empty Excalidraw Canvas|Save error|Put URL or [[WikiLink]]:|Task item|Callout text...|Column 1|Column 2|Header 1|Header 2|Cell 1|Cell 2|Go back to Folder / Tree View|Create New Note inside Vault|Undo Wetin You Just Do (Ctrl+Z)|Redo Wetin You Just Do (Ctrl+Y)|Put Bullet List|Put Number List|Put Task Checkbox|Put Horizontal Line|Put Hyperlink or [[WikiLink]] (Ctrl+K)|Put Obsidian Callout Box|Put Side-by-Side Multi-Column|Put Code Block with Syntax|Put Data Table|Auto-Format & Align Markdown Tables|Upload & Put Image or Attachment|Find & Replace Text inside Note (Ctrl+F)|Change to Visual WYSIWYG Editor|Change to Raw Markdown Code Editor|Changes never save — Click to save now (Ctrl+S)|Paragraph & Heading Level (P, H1–H5)|Text Font Size (15pt na default)|Bold Text (Ctrl+B)|Italic Text (Ctrl+I)|Underline Text (Ctrl+U)|Strikethrough Text|Remove Formatting & Reset to Clean Markdown|Apply Text Color|Choose Text Color|Apply Highlight / Background Color|Choose Highlight Color|Align Text to Left|Center Text|Align Text to Right|Reduce Indent|Increase Indent|Click to reset zoom to 100%|words|chars|min read|No Highlight / Default|Default Text Color|Custom:|Cut|Copy|Paste (Text)|Browser block paste. Abeg use Ctrl+V.|Bold|Italic|Underline|Highlight|Clear Formatting|Search di Web|Translate Selection|Translate|Translating...|Replace Selection|Open inside Google Translate|Inline translation no dey work. Click ↗ to open Google Translate.|Find inside note...|Replace with...|Find|Replace All|Rename fail:|Copied!"

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