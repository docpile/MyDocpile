#!/bin/bash

# ==========================================================================
#  UI HELPER FUNCTIONS & COLOR DETECTION
# ==========================================================================
if [ -t 1 ] && command -v tput >/dev/null 2>&1 && [ "$(tput colors 2>/dev/null || echo 0)" -ge 8 ]; then
    RESET='\033[0m'
    BOLD='\033[1m'
    BLUE='\033[34m'
    GREEN='\033[32m'
    YELLOW='\033[33m'
    RED='\033[31m'
    CYAN='\033[36m'

    msg_header()  { echo -e "\n${BOLD}${CYAN}=======================================================================${RESET}"; echo -e "${BOLD}${CYAN}  $1${RESET}"; echo -e "${BOLD}${CYAN}=======================================================================${RESET}\n"; }
    msg_info()    { echo -e "${BLUE}[ℹ]${RESET} $1"; }
    msg_success() { echo -e "${GREEN}[✔]${RESET} $1"; }
    msg_warn()    { echo -e "${YELLOW}[!]${RESET} $1"; }
    msg_error()   { echo -e "${RED}[✖]${RESET} $1"; }
    msg_ask()     { echo -ne "${BOLD}${CYAN}[?]${RESET} $1"; }
else
    msg_header()  { echo ""; echo "======================================================================="; echo "  $1"; echo "======================================================================="; echo ""; }
    msg_info()    { echo "[i] $1"; }
    msg_success() { echo "[v] $1"; }
    msg_warn()    { echo "[!] $1"; }
    msg_error()   { echo "[x] $1"; }
    msg_ask()     { echo -n "[?] $1"; }
fi

if [ "$EUID" -ne 0 ]; then
    msg_error "Please run as root (or use sudo)."
    exit 1
fi

# --------------------------------------------------------------------------
#   GLOBAL LOGGING SETUP
# --------------------------------------------------------------------------
CLOUD_DIR="/var/lib/mydocpile"
LOG_FILE="$CLOUD_DIR/install.log"
STATE_FILE="$CLOUD_DIR/.install_state"
PKG_STATE_FILE="$CLOUD_DIR/.installed_packages"

mkdir -p "$CLOUD_DIR"
touch "$LOG_FILE"

# Redirect normal stdout/stderr to tee. 
# This logs UI messages to the file with timestamps while keeping them visible on screen.
exec > >(tee >(while IFS= read -r line; do echo "[$(date +'%Y-%m-%d %H:%M:%S')] $line" >> "$LOG_FILE"; done)) 2>&1
echo -e "\n--- MyDocpile Setup Session Started ---"

msg_header "System Configuration & Installer: MyDocpile"

# --------------------------------------------------------------------------
#   OS & PACKAGE MANAGER DETECTION
# --------------------------------------------------------------------------
if [ -f /etc/os-release ]; then
    . /etc/os-release
    OS=$ID
    OS_LIKE=$ID_LIKE
else
    msg_error "Cannot detect OS. /etc/os-release is missing."
    exit 1
fi

PKG_MNGR=""
if [[ "$OS" == *"debian"* ]] || [[ "$OS_LIKE" == *"debian"* ]] || [[ "$OS" == *"ubuntu"* ]]; then
    PKG_MNGR="apt"
elif [[ "$OS" == *"fedora"* ]] || [[ "$OS_LIKE" == *"fedora"* ]] || [[ "$OS" == *"rhel"* ]] || [[ "$OS" == *"centos"* ]] || [[ "$OS" == *"almalinux"* ]]; then
    command -v dnf >/dev/null 2>&1 && PKG_MNGR="dnf" || PKG_MNGR="yum"
elif [[ "$OS" == *"arch"* ]] || [[ "$OS_LIKE" == *"arch"* ]]; then
    PKG_MNGR="pacman"
elif [[ "$OS" == *"suse"* ]] || [[ "$OS_LIKE" == *"suse"* ]]; then
    PKG_MNGR="zypper"
else
    msg_error "Unsupported Linux distribution."
    exit 1
fi

msg_info "Detected OS: $PRETTY_NAME"
msg_info "Using Package Manager: $PKG_MNGR"
msg_info "Log file located at: $LOG_FILE"

# --------------------------------------------------------------------------
#   STATE MANAGEMENT & LOGGING FUNCTIONS
# --------------------------------------------------------------------------
function save_state() {
    msg_info "Saving installation state..."
    cat << EOF > "$STATE_FILE"
main_domain="$main_domain"
wwwuser="$wwwuser"
www_group="$www_group"
PHP_VERSION="$PHP_VERSION"
www="$www"
opt_cloud="$opt_cloud"
ocr_langs="${ocr_langs[*]}"
opt_mailparse="$opt_mailparse"
PHP_BIN="$PHP_BIN"
EOF
    msg_success "State saved to $STATE_FILE."
}

function load_state() {
    if [ -f "$STATE_FILE" ]; then
        source "$STATE_FILE"
        ocr_langs=($ocr_langs) # Re-array
        msg_success "Loaded previous installation state."
    else
        msg_error "No installation state found. Please run a full install first."
        exit 1
    fi
}

function mark_package_installed() {
    echo "$1" >> "$PKG_STATE_FILE"
}

# Wrapper to execute commands silently on screen, but fully verbosely in the logfile
function execute_logged() {
    set -o pipefail
    "$@" 2>&1 | while IFS= read -r line; do 
        echo "[$(date +'%Y-%m-%d %H:%M:%S')] [CMD] $line" >> "$LOG_FILE"
    done
    local status=$?
    set +o pipefail
    return $status
}

# --------------------------------------------------------------------------
#   CORE FUNCTIONS
# --------------------------------------------------------------------------
function detect_php_binary() {
    msg_info "Detecting correct PHP executable for version $PHP_VERSION..."
    if command -v plesk >/dev/null 2>&1; then
        PHP_BIN="/opt/plesk/php/$PHP_VERSION/bin/php"
    else
        if command -v "php$PHP_VERSION" >/dev/null 2>&1; then
            PHP_BIN=$(command -v "php$PHP_VERSION")
        else
            PHP_BIN=$(command -v php)
        fi
    fi

    if [ ! -x "$PHP_BIN" ]; then
        msg_error "Could not locate PHP binary for version $PHP_VERSION."
        exit 1
    fi
    msg_success "Using PHP binary: $PHP_BIN"
}

function gather_configuration() {
    DEFAULT_USER="www-data"
    DEFAULT_GROUP="www-data"
    DEFAULT_WWW="/var/www/html"
    PHP_VERSION=${PHP_VERSION:-8.4}

    echo ""
    msg_info "Welcome to the MyDocpile Configuration!"
    msg_info "Ensure you have a web server, PHP, and local mail server ready."
    msg_ask "Is your environment ready to proceed? (y/N): " 
    read agree_start
    if [[ ! "$agree_start" =~ ^[Yy]$ ]]; then
        msg_warn "Aborted by user."
        exit 0
    fi

    echo ""
    msg_ask "Enter the main domain for the cloud (e.g., example.com): " 
    read main_domain

    if command -v plesk >/dev/null 2>&1; then
        msg_info "Plesk detected."
        DEFAULT_GROUP="psacln"
        DEFAULT_WWW="/var/www/vhosts/$main_domain/httpdocs"
        if [ -d "$DEFAULT_WWW" ]; then
            DETECTED_USER=$(stat -c '%U' "$DEFAULT_WWW" 2>/dev/null)
            DETECTED_GROUP=$(stat -c '%G' "$DEFAULT_WWW" 2>/dev/null)
        else
            DETECTED_USER=$(plesk bin domain -i "$main_domain" 2>/dev/null | grep "FTP Login" | awk '{print $3}')
            DETECTED_GROUP=$(id -gn "$DETECTED_USER" 2>/dev/null)
        fi
        wwwuser=${DETECTED_USER:-$main_domain}
        wwwuser=${wwwuser:-$DEFAULT_USER}
        www_group=${DETECTED_GROUP:-$DEFAULT_GROUP}
    else
        msg_info "Assuming standard Linux environment."
        DETECTED_USER=$(ps axo user,comm | grep -E '(apache2|httpd|nginx)' | grep -v root | head -n 1 | awk '{print $1}')
        DETECTED_USER=${DETECTED_USER:-$DEFAULT_USER}
        DETECTED_GROUP=$(id -gn "$DETECTED_USER" 2>/dev/null)
        DETECTED_GROUP=${DETECTED_GROUP:-$DEFAULT_GROUP}

        msg_ask "Enter web user [$DETECTED_USER]: " 
        read wwwuser
        wwwuser=${wwwuser:-$DETECTED_USER}

        msg_ask "Enter web group [$DETECTED_GROUP]: " 
        read www_group
        www_group=${www_group:-$DETECTED_GROUP}
    fi

    msg_ask "Enter PHP version to use (minimum 8.4) [$PHP_VERSION]: " 
    read PHP_VERSION
    PHP_VERSION=${PHP_VERSION:-8.4}

    msg_ask "Enter target www-root path [$DEFAULT_WWW]: " 
    read www
    www=${www:-$DEFAULT_WWW}

    msg_ask "Enter email sender address [root@$main_domain]: " 
    read email_sender
    email_sender=${email_sender:-root@$main_domain}

    msg_ask "Should logins be IP-bound? (true/false) [true]: " 
    read ip_bound
    ip_bound=${ip_bound:-true}

    msg_ask "Enter allowed domains (space-separated) [$main_domain]: " 
    read -a allowed_domains
    if [ ${#allowed_domains[@]} -eq 0 ]; then
        allowed_domains=("$main_domain")
    fi

    msg_ask "Enter webmail ONLY domains (no file cloud; space-separated, blank for none): " 
    read -a webmail_domains

    msg_ask "Enter timezone [Europe/Berlin]: " 
    read timezone
    timezone=${timezone:-Europe/Berlin}

    msg_ask "Max public share upload quota (e.g., 2G) [4G]: " 
    read public_quota
    public_quota=${public_quota:-4G}

    msg_ask "Max ZIP download size [1G]: " 
    read max_zip_size
    max_zip_size=${max_zip_size:-1G}

    msg_ask "Icon cache dir [/home/mydocpile/icon_cache]: " 
    read icon_cache
    icon_cache=${icon_cache:-/home/mydocpile/icon_cache}

    msg_ask "Preview cache dir [/home/mydocpile/preview_cache]: " 
    read preview_cache
    preview_cache=${preview_cache:-/home/mydocpile/preview_cache}

    msg_ask "Enable ClamAV? (true/false) [true]: " 
    read clamav_enabled
    clamav_enabled=${clamav_enabled:-true}

    msg_ask "Is OnlyOffice used and set up? (y/N): " 
    read use_oo
    if [[ "$use_oo" =~ ^[Yy]$ ]]; then
        msg_ask "OnlyOffice Shared JWT Secret: " 
        read oo_secret
        msg_ask "OnlyOffice URL (e.g., https://office.example.com/): " 
        read oo_url
    fi

    msg_ask "O365 Client ID (leave blank to skip): " 
    read o365_client_id
    if [ -n "$o365_client_id" ]; then
        msg_ask "O365 Client Secret: " 
        read o365_client_secret
    fi

    msg_ask "Install Cloud Search (Recoll, Tesseract, OCR)? (Y/n): " 
    read opt_cloud
    opt_cloud=${opt_cloud:-Y}

    if [[ "$opt_cloud" =~ ^[Yy]$ ]]; then
        msg_ask "OCR languages (space-separated) [eng deu]: " 
        read -a ocr_langs
        if [ ${#ocr_langs[@]} -eq 0 ]; then
            ocr_langs=("eng" "deu")
        fi
    fi

    msg_ask "Install Mailparse PHP extension? (Y/n): " 
    read opt_mailparse
    opt_mailparse=${opt_mailparse:-Y}

    prompt_admin_password
}

function prompt_admin_password() {
    echo ""
    msg_info "Please set the password for the 'cloudadmin' user."
    while true; do
        msg_ask "Enter new password: "
        read -s ADMIN_PWD
        echo ""
        msg_ask "Confirm password: "
        read -s ADMIN_PWD_CONFIRM
        echo ""
        if [ -n "$ADMIN_PWD" ] && [ "$ADMIN_PWD" == "$ADMIN_PWD_CONFIRM" ]; then
            break
        else
            msg_error "Passwords do not match or are empty. Please try again."
        fi
    done
}

function resolve_system_packages() {
    packages=()
    local php_pkgs=()
    local PHP_NO_DOT="${PHP_VERSION//./}"

    # 1. Determine PHP Package Names Dynamically
    if command -v plesk >/dev/null 2>&1; then
        local p="plesk-php${PHP_NO_DOT}"
        php_pkgs=("$p-imagick" "$p-intl" "$p-imap" "$p-pear" "$p-dev" "gcc" "make" "re2c")
    else
        if [[ "$PKG_MNGR" == "apt" ]]; then
            local p="php${PHP_VERSION}"
            php_pkgs=("$p-imagick" "$p-intl" "$p-imap" "$p-zip" "$p-apcu" "$p-pear" "$p-dev")
        elif [[ "$PKG_MNGR" == "dnf" || "$PKG_MNGR" == "yum" ]]; then
            php_pkgs=("php-pecl-imagick" "php-intl" "php-imap" "php-pecl-zip" "php-pecl-apcu" "php-pear" "php-devel")
        elif [[ "$PKG_MNGR" == "pacman" ]]; then
            php_pkgs=("php-imagick" "php-intl" "php-imap" "php-apcu" "php-pear" "php")
        elif [[ "$PKG_MNGR" == "zypper" ]]; then
            php_pkgs=("php-imagick" "php-intl" "php-imap" "php-zip" "php-apcu" "php-pear" "php-devel")
        fi
    fi

    # 2. Append OS Specific Non-PHP Packages
    if [[ "$PKG_MNGR" == "apt" ]]; then
        packages=("${php_pkgs[@]}" imagemagick libmemcached-dev memcached brotli ghostscript qpdf pdftk clamdscan ffmpeg antiword unrtf wkhtmltopdf)
        if [[ "$opt_cloud" =~ ^[Yy]$ ]]; then
            packages+=(recoll tesseract-ocr poppler-utils)
            for lang in "${ocr_langs[@]}"; do packages+=("tesseract-ocr-$lang"); done
        fi
    elif [[ "$PKG_MNGR" == "dnf" || "$PKG_MNGR" == "yum" ]]; then
        packages=("${php_pkgs[@]}" ImageMagick libmemcached-devel memcached brotli ghostscript qpdf pdftk clamav ffmpeg antiword unrtf wkhtmltopdf)
        if [[ "$opt_cloud" =~ ^[Yy]$ ]]; then
            packages+=(recoll tesseract poppler-utils)
            for lang in "${ocr_langs[@]}"; do packages+=("tesseract-langpack-$lang"); done
        fi
    elif [[ "$PKG_MNGR" == "pacman" ]]; then
        packages=("${php_pkgs[@]}" imagemagick libmemcached memcached brotli ghostscript qpdf pdftk clamav ffmpeg antiword unrtf wkhtmltopdf)
        if [[ "$opt_cloud" =~ ^[Yy]$ ]]; then
            packages+=(recoll tesseract poppler)
            for lang in "${ocr_langs[@]}"; do packages+=("tesseract-data-$lang"); done
        fi
    elif [[ "$PKG_MNGR" == "zypper" ]]; then
        packages=("${php_pkgs[@]}" ImageMagick libmemcached-devel memcached brotli ghostscript qpdf pdftk clamav ffmpeg antiword unrtf wkhtmltopdf)
        if [[ "$opt_cloud" =~ ^[Yy]$ ]]; then
            packages+=(recoll tesseract poppler-tools)
            for lang in "${ocr_langs[@]}"; do packages+=("tesseract-ocr-$lang"); done
        fi
    fi

    composer_packages=(
        geoip2/geoip2 sabre/dav matthiasmullie/minify phpseclib/phpseclib
        ezyang/htmlpurifier php-mime-mail-parser/php-mime-mail-parser
        league/oauth2-client league/oauth2-google thenetworg/oauth2-azure 
        webklex/php-imap
    )
}

function is_pkg_installed() {
    local pkg="$1"
    if [[ "$PKG_MNGR" == "apt" ]]; then
        dpkg-query -W -f='${Status}' "$pkg" 2>/dev/null | grep -q "ok installed"
    elif [[ "$PKG_MNGR" == "dnf" || "$PKG_MNGR" == "yum" || "$PKG_MNGR" == "zypper" ]]; then
        rpm -q "$pkg" >/dev/null 2>&1
    elif [[ "$PKG_MNGR" == "pacman" ]]; then
        pacman -Qq "$pkg" >/dev/null 2>&1
    fi
}

function show_configuration_summary() {
    local missing_packages=()
    for pkg in "${packages[@]}"; do
        if ! is_pkg_installed "$pkg"; then
            missing_packages+=("$pkg")
        fi
    done

    msg_header "Configuration Summary"
    msg_info "Please review the following settings before changes are made:"
    echo ""
    echo "  Main Domain:       $main_domain"
    echo "  Web User/Group:    $wwwuser / $www_group"
    echo "  PHP Version:       $PHP_VERSION"
    echo "  PHP Binary:        $PHP_BIN"
    echo "  Target Directory:  $www"
    echo "  Email Sender:      $email_sender"
    echo "  IP Bound Logins:   $ip_bound"
    echo "  Allowed Domains:   ${allowed_domains[*]}"
    echo "  Webmail Domains:   ${webmail_domains[*]:-None}"
    echo "  Timezone:          $timezone"
    echo "  Public Quota:      $public_quota"
    echo "  Max ZIP Size:      $max_zip_size"
    echo "  Icon Cache:        $icon_cache"
    echo "  Preview Cache:     $preview_cache"
    echo "  ClamAV Enabled:    $clamav_enabled"
    echo "  Use OnlyOffice:    ${use_oo:-N}"
    echo "  O365 Integration:  ${o365_client_id:-None}"
    echo "  Install Search:    $opt_cloud"
    if [[ "$opt_cloud" =~ ^[Yy]$ ]]; then
        echo "  OCR Languages:     ${ocr_langs[*]}"
    fi
    echo "  Install Mailparse: $opt_mailparse"
    echo ""

    msg_info "System Packages to be installed ($PKG_MNGR):"
    if [ ${#missing_packages[@]} -eq 0 ]; then
        echo "  -> (None. All required system packages are already installed.)"
    else
        echo "  -> ${missing_packages[*]}"
    fi
    echo ""

    msg_info "Composer Packages (PHP) to be installed:"
    echo "  -> ${composer_packages[*]}"
    echo ""
    msg_info "Note: Composer and its packages will only be installed LOCALLY"
    msg_info "within the application directory ($CLOUD_DIR)."
    msg_info "This is for explicit PHP version compatibility."
    echo ""

    msg_ask "${BOLD}${YELLOW}Are all settings correct? Proceed with installation?${RESET} (y/N): "
    read final_confirm
    if [[ ! "$final_confirm" =~ ^[Yy]$ ]]; then
        msg_warn "Installation aborted by user before making changes."
        exit 0
    fi
}

function install_system_packages() {
    msg_info "Updating system repositories..."
    if [[ "$PKG_MNGR" == "apt" ]]; then execute_logged apt-get update -y
    elif [[ "$PKG_MNGR" == "dnf" || "$PKG_MNGR" == "yum" ]]; then execute_logged $PKG_MNGR check-update
    elif [[ "$PKG_MNGR" == "pacman" ]]; then execute_logged pacman -Sy
    elif [[ "$PKG_MNGR" == "zypper" ]]; then execute_logged zypper refresh
    fi

    local failed=()
    for pkg in "${packages[@]}"; do
        if is_pkg_installed "$pkg"; then continue; fi

        msg_info "Installing package: $pkg..."
        if [[ "$PKG_MNGR" == "apt" ]]; then DEBIAN_FRONTEND=noninteractive execute_logged apt-get -y install "$pkg"
        elif [[ "$PKG_MNGR" == "dnf" || "$PKG_MNGR" == "yum" ]]; then execute_logged $PKG_MNGR install -y "$pkg"
        elif [[ "$PKG_MNGR" == "pacman" ]]; then execute_logged pacman -S --noconfirm "$pkg"
        elif [[ "$PKG_MNGR" == "zypper" ]]; then execute_logged zypper install -y "$pkg"
        fi

        if [ $? -eq 0 ]; then
            msg_success "$pkg installed successfully."
            mark_package_installed "$pkg"
        else
            msg_error "Failed to install: $pkg (check logfile for details)"
            failed+=("$pkg")
        fi
    done

    if [ ${#failed[@]} -gt 0 ]; then
        msg_warn "Some packages failed to install: ${failed[*]}"
    fi
}

function deploy_application_files() {
    local update_flag=$1 # Pass "-u" for update mode
    msg_info "Deploying application files..."

	mkdir -p "$CLOUD_DIR"
    cp ./nginx.conf.onlyoffice-proxy.sample "$CLOUD_DIR"/ 
    cp ./nginx.conf.sample "$CLOUD_DIR"/ 
    cp ./php.ini.sample "$CLOUD_DIR"/ 

    if [ -d "./cloud (not on www-root!)" ]; then
        cp -a $update_flag "./cloud (not on www-root!)"/* "$CLOUD_DIR"/ || true
		
        chown -R "$wwwuser":"$www_group" "$CLOUD_DIR"
        chmod -R ug+rwX "$CLOUD_DIR"
        msg_success "Cloud files deployed."
    else
        msg_warn "Source directory './cloud (not on www-root!)' not found."
    fi

    if [ -d "./www-root" ]; then
        mkdir -p "$www"
        cp -a $update_flag "./www-root"/* "$www"/ || true
        chown -R "$wwwuser":"$www_group" "$www"
        msg_success "WWW files deployed."
    else
        msg_warn "Source directory './www-root' not found."
    fi
}

function generate_config() {
    msg_info "Generating configuration file..."
    local config_dir="$CLOUD_DIR/configuration"
    mkdir -p "$config_dir"
    local config_file="$config_dir/config.php"

    local api_key=$(tr -dc 'A-Za-z0-9!@#$%^&*()_+-=<>?' </dev/urandom | head -c 62)
    local cookie_secret=$(tr -dc 'A-Za-z0-9!@#$%^&*()_+-=<>?' </dev/urandom | head -c 54)
    local php_allowed_domains=$(printf "'%s', " "${allowed_domains[@]}" | sed 's/, $//')
    local php_webmail_domains=$(printf "'%s', " "${webmail_domains[@]}" | sed 's/, $//')

    # Create cache directories and extra required directories
    mkdir -p "$icon_cache" "$preview_cache" /home/mydocpile/cloudadmin /home/mydocpile/dummy
    
    # Set permissions for /home/mydocpile base
    chown -R "$wwwuser":"$www_group" /home/mydocpile
    chmod -R ug+rwX /home/mydocpile

    # Ensure permissions for cache directories if they are located outside of /home/mydocpile
    if [[ "$icon_cache" != /home/mydocpile* ]] || [[ "$preview_cache" != /home/mydocpile* ]]; then
        chown -R "$wwwuser":"$www_group" "$icon_cache" "$preview_cache"
        chmod -R ug+rwX "$icon_cache" "$preview_cache"
    fi

    cat << EOF > "$config_file"
<?php
date_default_timezone_set('$timezone');

\$sys_www_dir = '$www';
\$email_sender_address = '$email_sender';
\$api_key = '$api_key';
\$cookie_secret = '$cookie_secret';
\$cookie_is_ip_bound = $ip_bound;

\$allowed_domain = [$php_allowed_domains];
\$cloud_only_domains = [$php_allowed_domains];
\$domain_webmail_only = [$php_webmail_domains];

\$cloud_share_url = 'https://$main_domain/cloud/index.php';
\$cloud_public_quotas = '$public_quota';
\$cloud_public_max_zip_size = '$max_zip_size';

\$cloud_icon_cache = '$icon_cache';
\$cloud_preview_cache = '$preview_cache';

\$cloud_clamav_enabled = $clamav_enabled;
\$cloud_oauth_my_domain = 'https://$main_domain/cloud/index.php';

\$MYCLOUD_O365_CLIENT_ID = '$o365_client_id';
\$MYCLOUD_O365_CLIENT_SECRET = '$o365_client_secret';
EOF

    if [[ "$use_oo" =~ ^[Yy]$ ]]; then
        cat << EOF >> "$config_file"
\$cloud_onlyoffice_Secret = '$oo_secret';
\$cloud_onlyoffice_URL = '$oo_url';
\$cloud_onlyoffice_ext_URL = '$oo_url';
EOF
    fi

    chown -R "$wwwuser":"$www_group" "$config_dir"
    chmod 640 "$config_file"
    msg_success "Configuration file created."
}

function apply_admin_password() {
    msg_info "Updating cloudadmin password..."
    local target_file="$CLOUD_DIR/configuration/users.php"
    
    if [ ! -f "$target_file" ]; then
        msg_warn "users.php not found at $target_file."
        msg_warn "Cannot set admin password automatically."
        return
    fi

    # Feed the password via STDIN to hide it from the process list (ps aux)
    echo "$ADMIN_PWD" | sudo -u "$wwwuser" "$PHP_BIN" -r '
        $pwd = trim(fgets(STDIN));
        $file = "'"$target_file"'";
        $content = file_get_contents($file);
        if ($content === false) exit(1);
        $hash = password_hash($pwd, PASSWORD_ARGON2ID);
        $pattern = "/([\047\"]cloudadmin[\047\"]\s*=>\s*)([\047\"][^\047\"]+[\047\"])/";
        $content = preg_replace_callback($pattern, function($m) use ($hash) {
            return $m[1] . "\047" . $hash . "\047";
        }, $content);
        if (file_put_contents($file, $content) !== false) {
            exit(0);
        }
        exit(1);
    '
    
    if [ $? -eq 0 ]; then
        msg_success "Admin password updated successfully."
    else
        msg_error "Failed to update admin password."
    fi
    
    # Clear sensitive variable from memory
    ADMIN_PWD=""
}

function install_composer_components() {
    msg_info "Installing Composer components locally..."
    mkdir -p "$CLOUD_DIR" 2>/dev/null
    chown "$wwwuser":"$www_group" "$CLOUD_DIR"
    cd "$CLOUD_DIR" || exit
    
    msg_info "Downloading local composer.phar to ensure exact PHP binary match..."
    execute_logged sudo -u "$wwwuser" "$PHP_BIN" -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    execute_logged sudo -u "$wwwuser" "$PHP_BIN" composer-setup.php --quiet
    execute_logged sudo -u "$wwwuser" "$PHP_BIN" -r "unlink('composer-setup.php');"
    
    local composer_cmd="$PHP_BIN composer.phar"
    msg_success "Local Composer downloaded."
    
    for pkg in "${composer_packages[@]}"; do
        msg_info "Requiring Composer package: $pkg..."
        execute_logged sudo -u "$wwwuser" $composer_cmd require --no-interaction "$pkg"
    done
    msg_success "Composer dependencies installed."
}

function optional_component_mailparse() {
    if command -v plesk >/dev/null 2>&1; then
        msg_info "Installing Mailparse for Plesk PHP..."
        PHP_PATH="/opt/plesk/php/$PHP_VERSION"
        if [[ "$PKG_MNGR" == "apt" ]]; then execute_logged apt-get install -y re2c gcc make plesk-php${PHP_VERSION//./}-dev
        elif [[ "$PKG_MNGR" == "dnf" || "$PKG_MNGR" == "yum" ]]; then execute_logged $PKG_MNGR install -y re2c gcc make plesk-php${PHP_VERSION//./}-devel
        fi
        
        execute_logged $PHP_PATH/bin/pecl install mailparse
        echo "extension=mailparse.so" > "$PHP_PATH/etc/php.d/mailparse.ini"
        execute_logged systemctl restart plesk-php${PHP_VERSION//./}-fpm || true
    else
        msg_info "Installing Mailparse for system PHP..."
        SYS_PHP_VERSION=$("$PHP_BIN" -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
        if [[ "$PKG_MNGR" == "apt" ]]; then execute_logged apt-get install -y php-dev php-pear gcc make re2c libmagic-dev
        elif [[ "$PKG_MNGR" == "dnf" || "$PKG_MNGR" == "yum" ]]; then execute_logged $PKG_MNGR install -y php-devel php-pear gcc make re2c file-devel
        fi
        
        printf "\n" | execute_logged pecl install mailparse
        
        if [[ "$PKG_MNGR" == "apt" ]]; then
            echo "extension=mailparse.so" > "/etc/php/$SYS_PHP_VERSION/mods-available/mailparse.ini"
            execute_logged phpenmod mailparse || true
        elif [[ "$PKG_MNGR" == "dnf" || "$PKG_MNGR" == "yum" || "$PKG_MNGR" == "zypper" ]]; then
            echo "extension=mailparse.so" > "/etc/php.d/mailparse.ini"
        elif [[ "$PKG_MNGR" == "pacman" ]]; then
            echo "extension=mailparse.so" > "/etc/php/conf.d/mailparse.ini"
        fi
        
        execute_logged systemctl restart "php$SYS_PHP_VERSION-fpm" php-fpm apache2 httpd nginx || true
    fi
}

function install_plesk_pecl_apcu() {
    if command -v plesk >/dev/null 2>&1; then
        msg_info "Installing APCu via PECL for Plesk PHP..."
        local PHP_PATH="/opt/plesk/php/$PHP_VERSION"
        
        if $PHP_PATH/bin/php -m | grep -q -i apcu; then
            msg_success "APCu is already installed."
            return
        fi
        
        printf "\n" | execute_logged $PHP_PATH/bin/pecl install apcu
        echo "extension=apcu.so" > "$PHP_PATH/etc/php.d/apcu.ini"
        execute_logged systemctl restart plesk-php${PHP_VERSION//./}-fpm || true
        
        if $PHP_PATH/bin/php -m | grep -q -i apcu; then
            msg_success "APCu installed successfully."
        else
            msg_error "Failed to install APCu (check logfile for details)."
        fi
    fi
}

function setup_cronjobs() {
    msg_info "Setting up system cronjobs..."
    local cron_file="/etc/cron.d/mydocpile"
    local list_dir="/var/lib/mydocpile/lists"
    
    mkdir -p "$list_dir"
    chown "$wwwuser":"$www_group" "$list_dir"

    cat << EOF > "$cron_file"
# MyDocpile Automated Tasks
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin

# Cache refresh and recyclers cleanup (every 5 minutes)
*/5 * * * * root MEM=\$(free -m | awk '/^Mem:/{print \$7}'); [[ -z "\$MEM" || "\$MEM" -lt 512 ]] && MEM=512; sudo -u $wwwuser $PHP_BIN -d memory_limit=\${MEM}M -d max_execution_time=3550 -d opcache.enable_cli=0 -d opcache.jit=disable /var/lib/mydocpile/cloud/cronjobs.php --cache-refresh --delete-recyclers >> $list_dir/cloud.housekeeping.log 2>&1
EOF

    if [[ "$opt_cloud" =~ ^[Yy]$ ]]; then
        cat << EOF >> "$cron_file"

# Nightly Search Indexer (runs at 03:00 AM)
0 3 * * * root MEM=\$(free -m | awk '/^Mem:/{print \$7}'); [[ -z "\$MEM" || "\$MEM" -lt 512 ]] && MEM=512; sudo -u $wwwuser $PHP_BIN -d memory_limit=\${MEM}M -d max_execution_time=3550 -d opcache.enable_cli=0 -d opcache.jit=disable /var/lib/mydocpile/cloud/cronjobs.php --search-index >> $list_dir/cloud.housekeeping.log 2>&1
EOF
    fi

    chmod 644 "$cron_file"
    msg_success "Cronjobs configured in $cron_file."
}

function execute_uninstall() {
    msg_info "Removing Application Files..."
    
    # Remove core cloud directory
    rm -rf "$CLOUD_DIR"
    msg_success "Removed $CLOUD_DIR"

    # Remove Cronjobs
    rm -f /etc/cron.d/mydocpile
    msg_success "Removed MyDocpile cronjobs"

    # Carefully remove ONLY the MyDocpile files from www-root if source exists
    if [ -d "./www-root" ]; then
        for item in ./www-root/*; do
            target="$www/$(basename "$item")"
            rm -rf "$target"
        done
        msg_success "Removed MyDocpile files from $www"
    else
        msg_warn "Source './www-root' missing. Cannot safely determine which files in $www to remove."
        msg_warn "Please clean up $www manually to avoid deleting unrelated web files."
    fi
}

function execute_uninstall_all() {
    execute_uninstall
    
    if [ -f "$PKG_STATE_FILE" ]; then
        msg_info "Removing explicitly installed system packages..."
        local pkgs_to_remove=$(cat "$PKG_STATE_FILE" | tr '\n' ' ')
        
        if [ -n "$pkgs_to_remove" ]; then
            if [[ "$PKG_MNGR" == "apt" ]]; then
                execute_logged apt-get remove -y $pkgs_to_remove
                execute_logged apt-get autoremove -y
            elif [[ "$PKG_MNGR" == "dnf" || "$PKG_MNGR" == "yum" ]]; then
                execute_logged $PKG_MNGR remove -y $pkgs_to_remove
            elif [[ "$PKG_MNGR" == "pacman" ]]; then
                execute_logged pacman -Rs --noconfirm $pkgs_to_remove
            elif [[ "$PKG_MNGR" == "zypper" ]]; then
                execute_logged zypper remove -y --clean-deps $pkgs_to_remove
            fi
            msg_success "Packages processed. Shared dependencies were preserved."
        fi
        rm -f "$PKG_STATE_FILE"
    else
        msg_info "No tracking file found for installed packages. Skipping package removal."
    fi
}

function show_post_install_instructions() {
    msg_header "Post-Installation Instructions"
    
    # Dynamically extract base directories, sort, and deduplicate them
    local dirs=("$www" "/var/lib/mydocpile" "$(dirname "$icon_cache")" "$(dirname "$preview_cache")" "/tmp" "/dev/urandom" "/dev/shm")
    local unique_dirs=($(printf "%s\n" "${dirs[@]}" | sort -u))
    local basedir_str=$(IFS=:; echo "${unique_dirs[*]}")
    
    msg_info "1. ${BOLD}${CYAN}PHP open_basedir Configuration:${RESET}"
    msg_info "   Please ensure your php.ini allows access at least to the following paths:"
    msg_info "   open_basedir = ${BOLD}$basedir_str ${RESET}"
    msg_info ""
    
    # Determine the directory where this script resides
    local SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)
    
    msg_info "2. ${BOLD}${CYAN}Reference Configurations (.sample files):${RESET}"
    msg_info "   Check the following sample configuration options in" 
	msg_info "   '$SCRIPT_DIR' to properly configure your environment:"
    msg_info "   - ${BOLD}${YELLOW}Nginx settings${RESET} (nginx.conf.sample)"
    msg_info "     (needed for webdav to work properly)"
    msg_info "   - ${BOLD}${YELLOW}PHP settings${RESET} (php.ini.sample)"
	msg_info "     (Additional PHP.PNI settings worth implementing)"
    msg_info "   - ${BOLD}${YELLOW}OnlyOffice Nginx proxy${RESET} (onlyoffice-nginx.conf.sample)"
	msg_info "     (For creating a proxy for an OnlyOffice local installation)"
    msg_info ""
}

# --------------------------------------------------------------------------
#   EXECUTION ROUTING
# --------------------------------------------------------------------------
if [ -f "$STATE_FILE" ]; then
    msg_header "MyDocpile Management"
    echo "  1) Update       (Copy newer files, install missing dependencies)"
    echo "  2) Refresh      (Re-run whole setup, keep existing config.php)"
    echo "  3) Reinit       (Remove and completely reconfigure config.php)"
    echo "  4) Reinstall    (Wipe configuration and reinstall from scratch)"
    echo "  5) Uninstall    (Remove MyDocpile software and files)"
    echo "  6) Uninstall All (Remove software AND installed system packages)"
    echo "  7) Reset Admin   (Reset the cloudadmin password only)"
    echo "  8) Exit"
    echo ""
    msg_ask "Select an option [1-8]: "
    read MODE_SEL
    
    case $MODE_SEL in
        1) MODE="update" ;;
        2) MODE="refresh" ;;
        3) MODE="reinit" ;;
        4) MODE="reinstall" ;;
        5) MODE="uninstall" ;;
        6) MODE="uninstall_all" ;;
        7) MODE="reset_pwd" ;;
        8) msg_info "Exiting."; exit 0 ;;
        *) msg_error "Invalid option."; exit 1 ;;
    esac
else
    MODE="install"
fi

msg_header "Executing Mode: $(echo "$MODE" | tr '[:lower:]' '[:upper:]')"

case $MODE in
    install)
        gather_configuration
        resolve_system_packages
        detect_php_binary
        show_configuration_summary
        install_system_packages
        install_plesk_pecl_apcu
        deploy_application_files ""
        generate_config
        apply_admin_password
        install_composer_components
        if [[ "$opt_mailparse" =~ ^[Yy]$ ]]; then optional_component_mailparse; fi
		setup_cronjobs
        save_state
        show_post_install_instructions
        ;;
    reinstall)
        load_state
        rm -f "$CLOUD_DIR/configuration/config.php"
        gather_configuration
        resolve_system_packages
        detect_php_binary
        show_configuration_summary
        install_system_packages
        install_plesk_pecl_apcu
        deploy_application_files ""
        generate_config
        apply_admin_password
        install_composer_components
        if [[ "$opt_mailparse" =~ ^[Yy]$ ]]; then optional_component_mailparse; fi
		setup_cronjobs
        save_state
        show_post_install_instructions
        ;;
    refresh)
        load_state
        resolve_system_packages
        detect_php_binary
        install_system_packages
        install_plesk_pecl_apcu
        deploy_application_files ""
        install_composer_components
        if [[ "$opt_mailparse" =~ ^[Yy]$ ]]; then optional_component_mailparse; fi
		setup_cronjobs
        msg_success "Refresh complete. Config was left untouched."
        show_post_install_instructions
        ;;
    reinit)
        load_state
        gather_configuration
        detect_php_binary
        generate_config
        save_state
        msg_success "Configuration successfully re-initialized."
        ;;
    reset_pwd)
        load_state
        detect_php_binary
        prompt_admin_password
        apply_admin_password
        ;;
    update)
        load_state
        resolve_system_packages
        detect_php_binary
        install_system_packages
        install_plesk_pecl_apcu
        deploy_application_files "-u"
        install_composer_components
 		setup_cronjobs
        msg_success "Update complete."
        show_post_install_instructions
        ;;
    uninstall)
        load_state
        execute_uninstall
        msg_success "Uninstallation complete."
        ;;
    uninstall_all)
        load_state
        execute_uninstall_all
        msg_success "Total uninstallation complete."
        ;;
esac

echo ""
msg_success "Operation Finished."
echo ""
exit 0