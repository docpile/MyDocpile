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
	"copy_all"
	"preparing_print"
	"items_selected"
	"invalid_link"
	"save_to_cloud"
	"inbox"
	"new_messages"
	"from_uc"
	"req_read_receipt"
	"auth_with_ms"
	"pgp_import_keys"
	"pgp_publish_local"
	"pgp_unpublish_local"
	"pgp_overwrite_warn"
	"server_type"
	"eas_server"
	"testing"
)

declare -A TRANSLATIONS

TRANSLATIONS[en]="Copy All|Preparing Print...|messages selected|Invalid link.|Save to Cloud|Inbox|New Messages|From|Request Read Receipt|Authorize with Microsoft|Import Keys|Publish to Local Storage|Remove from Local Storage|A PGP key is already present. Generating a new key pair will replace the existing one. You will permanently lose the ability to read emails encrypted with the old key unless it is backed up. Continue?|Server Type|EAS Server URL|Testing..."
TRANSLATIONS[de]="Alles kopieren|Druck wird vorbereitet...|Nachrichten ausgewählt|Ungültiger Link.|In Cloud speichern|Posteingang|Neue Nachrichten|Von|Lesebestätigung anfordern|Mit Microsoft autorisieren|Schlüssel importieren|Im lokalen Speicher veröffentlichen|Aus lokalem Speicher entfernen|Es ist bereits ein PGP-Schlüssel vorhanden. Das Generieren eines neuen Schlüsselpaars ersetzt das bestehende. Sie verlieren dauerhaft die Möglichkeit, mit dem alten Schlüssel verschlüsselte E-Mails zu lesen, sofern dieser nicht gesichert wurde. Fortfahren?|Servertyp|EAS-Server-URL|Wird getestet..."
TRANSLATIONS[es]="Copiar todo|Preparando impresión...|mensajes seleccionados|Enlace no válido.|Guardar en la nube|Bandeja de entrada|Nuevos mensajes|De|Solicitar confirmación de lectura|Autorizar con Microsoft|Importar claves|Publicar en almacenamiento local|Eliminar del almacenamiento local|Ya existe una clave PGP. Generar un nuevo par de claves reemplazará el existente. Perderá permanentemente la capacidad de leer correos cifrados con la clave antigua a menos que tenga una copia de seguridad. ¿Continuar?|Tipo de servidor|URL del servidor EAS|Probando..."
TRANSLATIONS[fr]="Tout copier|Préparation de l'impression...|messages sélectionnés|Lien invalide.|Enregistrer dans le cloud|Boîte de réception|Nouveaux messages|De|Demander un accusé de réception|Autoriser avec Microsoft|Importer des clés|Publier dans le stockage local|Supprimer du stockage local|Une clé PGP est déj  présente. La génération d'une nouvelle paire de clés remplacera l'existante. Vous perdrez définitivement la possibilité de lire les e-mails chiffrés avec l'ancienne clé   moins qu'elle ne soit sauvegardée. Continuer ?|Type de serveur|URL du serveur EAS|Test en cours..."
TRANSLATIONS[it]="Copia tutto|Preparazione stampa...|messaggi selezionati|Link non valido.|Salva nel cloud|Posta in arrivo|Nuovi messaggi|Da|Richiedi conferma di lettura|Autorizza con Microsoft|Importa chiavi|Pubblica nell'archiviazione locale|Rimuovi dall'archiviazione locale|È gi  presente una chiave PGP. La generazione di una nuova coppia di chiavi sostituir  quella esistente. Perderai permanentemente la possibilit  di leggere le email crittografate con la vecchia chiave a meno che non ne venga fatto il backup. Continuare?|Tipo di server|URL del server EAS|Test in corso..."
TRANSLATIONS[pt]="Copiar tudo|Preparando impressão...|mensagens selecionadas|Link inválido.|Salvar na Nuvem|Caixa de entrada|Novas Mensagens|De|Solicitar recibo de leitura|Autorizar com Microsoft|Importar Chaves|Publicar no Armazenamento Local|Remover do Armazenamento Local|Uma chave PGP já está presente. Gerar um novo par de chaves substituirá o existente. Você perderá permanentemente a capacidade de ler e-mails criptografados com a chave antiga, a menos que tenha feito backup. Continuar?|Tipo de Servidor|URL do Servidor EAS|Testando..."
TRANSLATIONS[ru]="Копировать все|Подготовка к печати...|выбранных сообщений|Неверная ссылка.|Сохранить в облако|Входящие|Новые сообщения|От|Запросить уведомление о прочтении|Авторизоваться через Microsoft|Импорт ключей|Опубликовать в локальном хранилище|Удалить из локального хранилища|PGP-ключ уже существует. Создание новой пары ключей заменит существующую. Вы навсегда потеряете возможность читать письма, зашифрованные старым ключом, если не сделаете резервную копию. Продолжить?|Тип сервера|URL сервера EAS|Тестирование..."
TRANSLATIONS[tr]="Tümünü Kopyala|Yazdırmaya hazırlanıyor...|mesaj seçildi|Geçersiz bağlantı.|Buluta Kaydet|Gelen Kutusu|Yeni Mesajlar|Kimden|Okundu Bilgisi İste|Microsoft ile Yetkilendir|Anahtarları İçe Aktar|Yerel Depolamada Yayınla|Yerel Depolamadan Kaldır|Zaten bir PGP anahtarı var. Yeni bir anahtar çifti oluşturmak mevcut olanı değiştirecektir. Yedeklenmediği sürece eski anahtarla şifrelenmiş e-postaları okuma yeteneğinizi kalıcı olarak kaybedersiniz. Devam edilsin mi?|Sunucu Türü|EAS Sunucu URL'si|Test ediliyor..."
TRANSLATIONS[zh-cn]="全部复制|正在准备打印...|条消息已选择| 效链接。|保存到云端|收件箱|新消息|发件人|请求已读回执|使用 Microsoft 授权|导入密钥|发布到本地存储|从本地存储中 除|已存在 PGP 密钥。生成新密钥对将替换现有的密钥对。除非已备份，否则您将永久失去阅读使用旧密钥 密的电子邮件的能力。是否继续？|服务器类型|EAS 服务器 URL|测试中..."
TRANSLATIONS[ja]="すべてコピー|印刷の準備中...|件のメッセージを選択|無効なリンクです。|クラウドに保存|受信トレイ|新しいメッセージ|差出人|開封確認を要求|Microsoft で承認|キーをインポート|ローカルストレージに公開|ローカルストレージから削除|PGP キーは既に存在します。新しいキーペアを生成すると、既存のものが置き換えられます。バックアップされていない限り、古いキーで暗号化されたメールを読む能力を永久に失います。続行しますか？|サーバーの種類|EAS サーバー URL|テスト中..."
TRANSLATIONS[ko]="모두 복사|인쇄 준비 중...|개의 메시지  택됨|잘못된 링크입니다.|클라우드에  장|받은 편지함|새 메시지|보낸 사람|수  확인 요청|Microsoft로 승인|키 가 오기|로컬  장소에 게시|로컬  장소에서  거|PGP 키가 이미 존재합니다. 새 키 쌍을 생성하면 기존 키가 바뀝니다. 백업하지 않으면 이  키로 암호화된 이메일을 읽을 수 있는 권한을 영구 으로 잃게 됩니다. 계속하시 습니까?|서버  형|EAS 서버 URL|테스트 중..."
TRANSLATIONS[ar]="نسخ الكل|جاري التجهيز للطباعة...|رسائل محددة|رابط غير صالح.|حفظ في السحابة|البريد الوارد|رسائل جديدة|من|طلب إيصال القراءة|تفويض باستخدام Microsoft|استيراد المفاتيح|نشر في التخزين المحلي|إزالة من التخزين المحلي|مفتاح PGP موجود بالفعل. إنشاء زوج مفاتيح جديد سيحل محل الحالي. ستفقد بشكل دائم القدرة على قراءة رسائل البريد الإلكتروني المشفرة بالمفتاح القديم ما لم يتم نسخه احتياطياً. هل تريد المتابعة؟|نوع الخادم|رابط خادم EAS|جاري الاختبار..."
TRANSLATIONS[fa]="کپی همه|در حال آماده‌سازی چاپ...|پیام انتخاب شد|لینک نامعتبر.|ذخیره در ابری|صندوق ورودی|پیام‌های جدید|از|درخواست رسید خواندن|احراز هویت با Microsoft|وارد کردن کلیدها|انتشار در ذخیره‌سازی محلی|حذف از ذخیره‌سازی محلی|یک کلید PGP در حال حاضر وجود دارد. ایجاد یک جفت کلید جدید جایگزین کلید فعلی خواهد شد. شما به طور دائم توانایی خواندن ایمیل‌های رمزگذاری شده با کلید قدیمی را از دست خواهید داد، مگر اینکه پشتیبان‌گیری شده باشد. ادامه می‌دهید؟|نوع سرور|آدرس سرور EAS|در حال آزمایش..."
TRANSLATIONS[hi]="सभी कॉपी करें|प्रिंट तैयार किया जा रहा है...|संदेश चुने गए|अमान्य लिंक।|क्लाउड में सेव करें|इनबॉक्स|नए संदेश|से|रीड रसीद का अनुरोध करें|Microsoft के साथ अधिकृत करें|कुंजियाँ आयात करें|स्थानीय संग्रहण में प्रकाशित करें|स्थानीय संग्रहण से निकालें|एक PGP कुंजी पहले से ही मौजूद है। नया कुंजी जोड़ा जनरेट करने से मौजूदा एक बदल जाएगा। जब तक बैकअप नहीं लिया जाता, तब तक आप पुरानी कुंजी के साथ एन्क्रिप्ट किए गए ईमेल पढ़ने की क्षमता स्थायी रूप से खो देंगे। क्या आप जारी रखना चाहते हैं?|सर्वर प्रकार|EAS सर्वर URL|परीक्षण किया जा रहा है..."
TRANSLATIONS[vi]="Sao chép tất cả|Đang chuẩn bị in...|tin nhắn đã chọn|Liên kết không hợp lệ.|Lưu v o Đám mây|Hộp thư đến|Tin nhắn mới|Từ|Yêu cầu Biên nhận Đã đọc|Ủy quyền bằng Microsoft|Nhập Khóa|Xuất bản lên Bộ nhớ cục bộ|Xóa khỏi Bộ nhớ cục bộ|Khóa PGP đã tồn tại. Việc tạo cặp khóa mới sẽ thay thế cặp khóa hiện có. Bạn sẽ vĩnh viễn mất khả năng đọc các email được mã hóa bằng khóa cũ trừ khi nó được sao lưu. Tiếp tục?|Loại máy chủ|URL máy chủ EAS|Đang kiểm tra..."
TRANSLATIONS[uk]="Копіювати все|Підготовка до друку...|повідомлень вибрано|Недійсне посилання.|Зберегти в хмару|Вхідні|Нові повідомлення|Від|Запросити сповіщення про прочитання|Авторизоваться через Microsoft|Імпорт ключів|Опублікувати в локальному сховищі|Видалити з локального сховища|Ключ PGP вже існує. Створення нової пари ключів замінить існуючу. Ви назавжди втратите можливість читати електронні листи, зашифровані старим ключем, якщо не зробите резервну копію. Продовжити?|Тип сервера|URL-адреса сервера EAS|Тестування..."
TRANSLATIONS[bar]="Ois kopiern|Druck werd vorbereit...|Nachrichtn ausgwählt|Ungültiga Link.|In Cloud speichern|Posteingang|Neie Nachrichtn|Von|Lesebestätigung ofordern|Mit Microsoft autorisiern|Schlüssl importiern|Im lokaln Speicha veröffentlichn|Ausm lokaln Speicha entfern|Es is scho a PGP-Schlüssl do. Wennst a neichs Schlüsslpaar generierst, werd des oide dasezt. Du valiast dauahaft de Möglichkeit, E-Mails zum lesn, de mitm oidn Schlüssl vaschlüsslt wurdn, auẞa du host a Backup. Weitamocha?|Server-Typ|EAS-Server-URL|Werd test..."
TRANSLATIONS[hes]="Alles kopiern|Druck wird vorbereitet...|Nachrichten ausgewählt|Ungültiger Link.|In Cloud speichern|Posteingang|Neue Nachrichten|Von|Lesebestätigung anfordern|Mit Microsoft autorisieren|Schlüssel importiern|Im lokalen Speicher veröffentlichen|Aus lokalem Speicher entfernen|Es is scho e PGP-Schlüssel do. E neues Schlüsselpaar generiern ersetzt des alte. Du verlierst dauerhaft die Möglichkeit, E-Mails zu lese, die mit dem alte Schlüssel verschlüsselt worde, außer du hast e Backup. Weitermache?|Servertyp|EAS-Server-URL|Wird getest..."
TRANSLATIONS[lb]="Alles kopéieren|Drock gëtt virbereet...|Messagen ausgewielt|Ongültege Link.|An der Cloud späicheren|Boîte|Nei Messagen|Vun|Liesbestätegung ufroen|Mat Microsoft autoriséieren|Schlëssel importéieren|Am lokale Späicher verëffentlechen|Aus dem lokale Späicher ewechhuelen|E PGP-Schlëssel ass scho präsent. Wann Dir en neit Schlësselpuer generéiert, gëtt dat aalt ersat. Dir verléiert dauerhaft d'Méiglechkeet, E-Mailen ze liesen, déi mam ale Schlëssel verschlësselt goufen, ausser et gouf e Backup gemaach. Weidermaachen?|Servertyp|EAS-Server-URL|Gëtt getest..."
TRANSLATIONS[pcm]="Copy all|Dey prepare print...|messages selected|Bad link.|Save to Cloud|Inbox|New Messages|From|Ask for read receipt|Authorize with Microsoft|Import Keys|Publish to Local Storage|Remove from Local Storage|PGP key don already dey. If you make new key pair, e go replace di one wey dey. You no go fit read emails wey you encrypt with di old key again unless you get backup. You want continue?|Server Type|EAS Server URL|Testing..."

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