#!/bin/bash


# ==============================================================================
# CONFIGURATION
# ==============================================================================

TARGET_ANCHOR="cloud_link_desc"

# ==============================================================================
# ARRAYS
# ==============================================================================


# 2. The Keys
KEYS=(
    "deceptive_link_reason"
    "deceptive_link_title"
    "subdomain_link_reason"
    "subdomain_link_title"
    "cloud_link_reason"
    "cloud_link_title"
    "deceptive_link_short"
    "deceptive_link_desc"
    "subdomain_link_short"
    "subdomain_link_desc"
    "cloud_link_short"
    "cloud_link_desc"
    "displayed_url_lbl"
    "actual_url_lbl"
    "open_actual_url"
    "open_displayed_url"
)

declare -A TRANSLATIONS

TRANSLATIONS[en]="Deceptive Link: Text displays '%s' but actually leads to '%t'|Deceptive Link: Leads to|Tracking/Subdomain Link: Text displays '%s' and redirects via '%t'|Redirects via|Cloud/Shortener Host: Link points to '%s'|Cloud/Shortener Link:|Deceptive Link|link(s) display a different domain name in the text than their actual destination (underlined in red).|Subdomain Link|link(s) point to a tracking/measurement subdomain of the displayed domain.|Cloud/ShortURL|link(s) point to a URL shortener or free serverless/cloud storage host.|Displayed URL (Recommended):|Actual Link Target (Href):|Open Actual Target|Open Displayed URL"
TRANSLATIONS[de]="Trügerischer Link: Text zeigt '%s', führt aber tatsächlich zu '%t'|Trügerischer Link: Führt zu|Tracking-/Subdomain-Link: Text zeigt '%s' und leitet über '%t' um|Leitet um über|Cloud/Shortener-Host: Link führt zu '%s'|Cloud/Shortener-Link:|Trügerischer Link|Link(s) zeigen im Text einen anderen Domainnamen als das tatsächliche Ziel (rot unterstrichen).|Subdomain-Link|Link(s) verweisen auf eine Tracking-/Messungs-Subdomain der angezeigten Domain.|Cloud/Kurz-URL|Link(s) verweisen auf einen URL-Shortener oder einen kostenlosen Serverless/Cloud-Speicher-Host.|Angezeigte URL (Empfohlen):|Tatsächliches Link-Ziel (Href):|Tatsächliches Ziel öffnen|Angezeigte URL öffnen"
TRANSLATIONS[es]="Enlace engañoso: El texto muestra '%s' pero en realidad lleva a '%t'|Enlace engañoso: Lleva a|Enlace de seguimiento/subdominio: El texto muestra '%s' y redirige a través de '%t'|Redirige a través de|Host Cloud/Shortener: El enlace apunta a '%s'|Enlace Cloud/Shortener:|Enlace engañoso|enlace(s) muestran un nombre de dominio diferente en el texto que su destino real (subrayado en rojo).|Enlace de subdominio|enlace(s) apuntan a un subdominio de seguimiento/medición del dominio mostrado.|Cloud/URL corta|enlace(s) apuntan a un acortador de URL o host de almacenamiento en la nube/serverless gratuito.|URL mostrada (Recomendado):|Destino real del enlace (Href):|Abrir destino real|Abrir URL mostrada"
TRANSLATIONS[fr]="Lien trompeur : Le texte affiche '%s' mais mène en réalité   '%t'|Lien trompeur : Mène  |Lien de suivi/sous-domaine : Le texte affiche '%s' et redirige via '%t'|Redirige via|Hôte Cloud/Réducteur : Le lien pointe vers '%s'|Lien Cloud/Réducteur :|Lien trompeur|lien(s) affichent un nom de domaine différent dans le texte de leur destination réelle (souligné en rouge).|Lien sous-domaine|lien(s) pointent vers un sous-domaine de suivi/mesure du domaine affiché.|Cloud/URL courte|lien(s) pointent vers un réducteur d'URL ou un hôte de stockage cloud/serverless gratuit.|URL affichée (Recommandé) :|Cible réelle du lien (Href) :|Ouvrir la cible réelle|Ouvrir l'URL affichée"
TRANSLATIONS[it]="Link ingannevole: Il testo mostra '%s' ma in realt  porta a '%t'|Link ingannevole: Porta a|Link di tracciamento/sottodominio: Il testo mostra '%s' e reindirizza tramite '%t'|Reindirizza tramite|Host Cloud/Shortener: Il link punta a '%s'|Link Cloud/Shortener:|Link ingannevole|link mostrano un nome a dominio diverso nel testo rispetto alla loro destinazione reale (sottolineato in rosso).|Link sottodominio|link puntano a un sottodominio di tracciamento/misurazione del dominio visualizzato.|Cloud/URL breve|link puntano a un accorciatore di URL o host di archiviazione cloud/serverless gratuito.|URL visualizzato (Consigliato):|Destinazione reale del link (Href):|Apri destinazione reale|Apri URL visualizzato"
TRANSLATIONS[pt]="Link Enganoso: O texto exibe '%s', mas na verdade leva a '%t'|Link Enganoso: Leva a|Link de rastreamento/subdomínio: O texto exibe '%s' e redireciona via '%t'|Redireciona via|Host Cloud/Shortener: O link aponta para '%s'|Link Cloud/Shortener:|Link Enganoso|link(s) exibem um nome de domínio diferente no texto de seu destino real (sublinhado em vermelho).|Link de Subdomínio|link(s) apontam para um subdomínio de rastreamento/medição do domínio exibido.|Cloud/URL curta|link(s) apontam para um encurtador de URL ou host de armazenamento em nuvem/serverless gratuito.|URL Exibida (Recomendado):|Destino Real do Link (Href):|Abrir Destino Real|Abrir URL Exibida"
TRANSLATIONS[ru]="Обманчивая ссылка: Текст показывает '%s', но на самом деле ведет на '%t'|Обманчивая ссылка: Ведет на|Ссылка отслеживания/поддомена: Текст показывает '%s' и перенаправляет через '%t'|Перенаправляет через|Облако/Сокращатель: Ссылка указывает на '%s'|Ссылка на Облако/Сокращатель:|Обманчивая ссылка|ссылка(и) показывают в тексте доменное имя, отличное от их реального пункта назначения (подчеркнуто красным).|Ссылка поддомена|ссылка(и) указывают на поддомен отслеживания/измерения отображаемого домена.|Облако/Короткий URL|ссылка(и) указывают на сокращатель URL или бесплатный бессерверный/облачный хостинг.|Отображаемый URL ( екомендуется):| еальная цель ссылки (Href):|Открыть реальную цель|Открыть отображаемый URL"
TRANSLATIONS[tr]="Yanıltıcı Bağlantı: Metin '%s' gösteriyor ancak aslında '%t' adresine yönlendiriyor|Yanıltıcı Bağlantı: Şuraya yönlendirir|İzleme/Alt Alan Adı Bağlantısı: Metin '%s' gösteriyor ve '%t' üzerinden yönlendiriyor|Üzerinden yönlendirir|Bulut/Kısaltıcı Sunucusu: Bağlantı '%s' adresini işaret ediyor|Bulut/Kısaltıcı Bağlantısı:|Yanıltıcı Bağlantı|bağlantı(lar) metinde gerçek hedeflerinden farklı bir alan adı gösteriyor (kırmızı ile altı çizili).|Alt Alan Adı Bağlantısı|bağlantı(lar) görüntülenen alan adının bir izleme/ölçüm alt alan adını işaret ediyor.|Bulut/Kısa URL|bağlantı(lar) bir URL kısaltıcıya veya ücretsiz sunucusuz/bulut depolama sunucusuna işaret ediyor.|Görüntülenen URL (Önerilen):|Gerçek Bağlantı Hedefi (Href):|Gerçek Hedefi Aç|Görüntülenen URL'yi Aç"
TRANSLATIONS[zh-cn]="欺骗性链接：文本显示 '%s'，但实际指向 '%t'|欺骗性链接：指向|跟踪/子域名链接：文本显示 '%s' 并通过 '%t' 重定向|重定向通过|云/缩短器主机：链接指向 '%s'|云/缩短器链接：|欺骗性链接|链接在文本中显示的域名与其在实际目 不同（用红色下划线 出）。|子域名链接|链接指向所显示域名的跟踪/测量子域名。|云/短网址|链接指向网址缩短器或免费的 服务器/云存储主机。|显示的 URL (推荐)：|实际链接目  (Href)：|打开实际目 |打开显示的 URL"
TRANSLATIONS[ja]="欺瞞的なリンク: テキストは '%s' と表示していますが、実際には '%t' に移動します|欺瞞的なリンク: リンク先|追跡/サブドメインリンク: テキストは '%s' と表示しており、'%t' を経由してリダイレクトします|経由リダイレクト|クラウド/短縮ホスト: リンクは '%s' を指しています|クラウド/短縮リンク:|欺瞞的なリンク|リンクは実際の宛先とは異なるドメイン名をテキストで表示します（赤色で下線）。|サブドメインリンク|リンクは表示されているドメインの追跡/測定サブドメインを指しています。|クラウド/短縮URL|リンクはURL短縮サービスまたは無料のサーバーレス/クラウドストレージホストを指しています。|表示されているURL (推奨):|実際のリンク先 (Href):|実際の宛先を開く|表示されているURLを開く"
TRANSLATIONS[ko]="기만 인 링크: 텍스트에 '%s'이(가) 표시되지만 실 로는 '%t'(으)로 이동합니다.|기만 인 링크: 이동 경로|추 /하위 도메인 링크: 텍스트에 '%s'이(가) 표시되며 '%t'(을)를 통해 리디 션됩니다.|리디 션 경로|클라우드/단축기 호스트: 링크가 '%s'을(를) 가리킵니다.|클라우드/단축기 링크:|기만 인 링크|링크가 실  목 지와 다른 도메인 이름을 텍스트에 표시합니다(빨간색 밑줄).|하위 도메인 링크|링크가 표시된 도메인의 추 /측  하위 도메인을 가리킵니다.|클라우드/단축 URL|링크가 URL 단축기 또는 무료 서버리스/클라우드 스 리지 호스트를 가리킵니다.|표시된 URL (권장):|실  링크 대상 (Href):|실  대상 열기|표시된 URL 열기"
TRANSLATIONS[ar]="رابط خادع: يعرض النص '%s' ولكنه يؤدي في الواقع إلى '%t'|رابط خادع: يؤدي إلى|رابط تتبع/نطاق فرعي: يعرض النص '%s' ويعيد التوجيه عبر '%t'|يعيد التوجيه عبر|مضيف السحابة/التقصير: يشير الرابط إلى '%s'|رابط السحابة/التقصير:|رابط خادع|رابط (روابط) تعرض اسم نطاق مختلف في النص عن وجهتها الفعلية (تحتها خط أحمر).|رابط نطاق فرعي|رابط (روابط) تشير إلى نطاق فرعي للتتبع/القياس للنطاق المعروض.|سحابة/رابط قصير|رابط (روابط) تشير إلى مقصر URL أو مضيف تخزين سحابي/بدون خادم مجاني.|URL المعروض (موصى به):|الهدف الفعلي للرابط (Href):|فتح الهدف الفعلي|فتح URL المعروض"
TRANSLATIONS[fa]="پیوند فریبنده: متن '%s' را نشان می دهد اما در واقع به '%t' منتهی می شود|پیوند فریبنده: منتهی می شود به|پیوند ردیابی/زیردامنه: متن '%s' را نشان می دهد و از طریق '%t' هدایت می کند|هدایت از طریق|میزبان ابری/کوتاه کننده: پیوند به '%s' اشاره می کند|پیوند ابری/کوتاه کننده:|پیوند فریبنده|پیوند(ها) نام دامنه متفاوتی را در متن نسبت به مقصد واقعی خود نشان می دهند (زیر آن خط قرمز کشیده شده است).|پیوند زیردامنه|پیوند(ها) به یک زیردامنه ردیابی/اندازه‌گیری دامنه نمایش داده شده اشاره می‌کنند.|ابری/نشانی اینترنتی کوتاه|پیوند(ها) به یک کوتاه کننده URL یا میزبان ذخیره سازی ابری/بدون سرور رایگان اشاره می کنند.|URL نمایش داده شده (توصیه می شود):|هدف واقعی پیوند (Href):|باز کردن هدف واقعی|باز کردن URL نمایش داده شده"
TRANSLATIONS[hi]="भ्रामक लिंक: पा  '%s' दिखाता है लेकिन वास्तव में '%t' की ओर ले जाता है|भ्रामक लिंक: की ओर ले जाता है|ट्रैकिंग/उपडोमेन लिंक: पा  '%s' दिखाता है और '%t' के माध्यम से रीडायरेक्ट करता है|के माध्यम से रीडायरेक्ट करता है|क्लाउड/शॉर्टनर होस्ट: लिंक '%s' की ओर इशारा करता है|क्लाउड/शॉर्टनर लिंक:|भ्रामक लिंक|लिंक पा  में अपने वास्तविक गंतव्य से भिन्न डोमेन नाम प्रदर्शित करते हैं (लाल रंग में रेखांकित)।|उपडोमेन लिंक|लिंक प्रदर्शित डोमेन के ट्रैकिंग/मापन उपडोमेन की ओर इशारा करते हैं।|क्लाउड/लघु URL|लिंक URL शॉर्टनर या मुफ़्त सर्वरलेस/क्लाउड स्टोरेज होस्ट की ओर इशारा करते हैं।|प्रदर्शित URL (अनुशंसित):|वास्तविक लिंक गंतव्य (Href):|वास्तविक गंतव्य खोलें|प्रदर्शित URL खोलें"
TRANSLATIONS[vi]="Liên kết lừa đảo: Văn bản hiển thị '%s' nhưng thực tế dẫn đến '%t'|Liên kết lừa đảo: Dẫn đến|Liên kết theo dõi/tên miền phụ: Văn bản hiển thị '%s' v  chuyển hướng qua '%t'|Chuyển hướng qua|Máy lưu trữ Cloud/Rút gọn: Liên kết trỏ đến '%s'|Liên kết Cloud/Rút gọn:|Liên kết lừa đảo|liên kết hiển thị tên miền khác trong văn bản so với đích đến thực tế của chúng (được gạch dưới bằng m u đỏ).|Liên kết tên miền phụ|liên kết trỏ đến tên miền phụ theo dõi/đo lường của tên miền được hiển thị.|Cloud/URL ngắn|liên kết trỏ đến trình rút gọn URL hoặc máy lưu trữ đám mây/không máy chủ miễn phí.|URL được hiển thị (Khuyên dùng):|Đích liên kết thực tế (Href):|Mở đích thực tế|Mở URL được hiển thị"
TRANSLATIONS[uk]="Оманливе посилання: Текст показує '%s', але насправді веде на '%t'|Оманливе посилання: Веде на|Посилання відстеження/піддомену: Текст показує '%s' і перенаправляє через '%t'|Перенаправляє через|Хмара/Скорочувач: Посилання вказує на '%s'|Посилання на Хмару/Скорочувач:|Оманливе посилання|посилання показують у тексті доменне ім'я, відмінне від їхнього реального пункту призначення (підкреслено червоним).|Посилання піддомену|посилання вказують на піддомен відстеження/вимірювання відображуваного домену.|Хмара/Короткий URL|посилання вказують на скорочувач URL або безкоштовний безсерверний/хмарний хостинг.|Відображуваний URL ( екомендовано):| еальна мета посилання (Href):|Відкрити реальну мету|Відкрити відображуваний URL"
TRANSLATIONS[bar]="Täuschenda Link: Text zoagt '%s', führt aba wirklich zua '%t'|Täuschenda Link: Führt zua|Tracking-/Subdomain-Link: Text zoagt '%s' und leit üba '%t' um|Leit um üba|Cloud/Shortener-Host: Link zoagt auf '%s'|Cloud/Shortener-Link:|Täuschenda Link|Link(s) zoagn an andan Domainnaman im Text ois eahna echtes Zui (rot untastrichn).|Subdomain-Link|Link(s) zoagn auf a Tracking-/Mess-Subdomain vo da ozoagtn Domain.|Cloud/Kurz-URL|Link(s) zoagn auf an URL-Shortener oda an kostnlosn Serverless/Cloud-Speicha-Host.|Ozoagte URL (Empfohln):|Echtes Link-Zui (Href):|Echtes Zui aufmacha|Ozoagte URL aufmacha"
TRANSLATIONS[hes]="Täuschender Link: Text zeischt '%s', führt awwer in Echt zu '%t'|Täuschender Link: Führt zu|Tracking-/Subdomain-Link: Text zeischt '%s' un leit iwwer '%t' um|Leit um iwwer|Cloud/Shortener-Host: Link zeischt uff '%s'|Cloud/Shortener-Link:|Täuschender Link|Link(s) zeische e annern Domainname im Text als es echde Ziel (rot unnertstrichen).|Subdomain-Link|Link(s) weisen uff e Tracking-/Mess-Subdomain von der aagezeischte Domain.|Cloud/Kurz-URL|Link(s) weisen uff en URL-Shortener oder en kosdelose Serverless/Cloud-Speicher-Host.|Aagezeischte URL (Empfohle):|Echdes Link-Ziel (Href):|Echdes Ziel uffmache|Aagezeischte URL uffmache"
TRANSLATIONS[lb]="Täuschende Link: Text weist '%s', féiert awer tatsächlech op '%t'|Täuschende Link: Féiert op|Tracking-/Subdomain-Link: Text weist '%s' a leet iwwer '%t' ëm|Leet ëm iwwer|Cloud/Shortener-Host: Link weist op '%s'|Cloud/Shortener-Link:|Täuschende Link|Link(en) weisen en aneren Domain-Numm am Text wéi hir tatsächlech Destinatioun (rout ënnerstrach).|Subdomain-Link|Link(en) weisen op eng Tracking-/Mooss-Subdomain vun der gewisener Domain.|Cloud/Kuerz-URL|Link(en) weisen op en URL-Shortener oder e gratis Serverless/Cloud-Späicher-Host.|Gewisen URL (Recommandéiert):|Tatsächlecht Link-Zil (Href):|Tatsächlecht Zil opmaachen|Gewisen URL opmaachen"
TRANSLATIONS[pcm]="Trick Link: Text show '%s' but e actually lead go '%t'|Trick Link: Lead go|Tracking/Subdomain Link: Text show '%s' and e redirect pass '%t'|Redirect pass|Cloud/Shortener Host: Link dey point to '%s'|Cloud/Shortener Link:|Trick Link|link(s) display different domain name for di text than di actual destination (dem underline am red).|Subdomain Link|link(s) point to tracking/measurement subdomain for di displayed domain.|Cloud/ShortURL|link(s) point to URL shortener or free serverless/cloud storage host.|Displayed URL (Recommended):|Actual Link Target (Href):|Open Actual Target|Open Displayed URL"

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