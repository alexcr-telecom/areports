#!/bin/bash
#
# aReports Installation Script
# Call Center Analytics Platform for Asterisk/FreePBX
#
# Usage: sudo bash install.sh
#

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
INSTALL_DIR="/var/www/html/areports"
APACHE_USER="www-data"
APACHE_GROUP="www-data"
PHP_MIN_VERSION="8.0"

# Default database settings
DB_HOST="localhost"
DB_NAME="areports"
DB_USER="areports"
DB_PASS=""

# FreePBX/Asterisk database settings
FREEPBX_DB_HOST="localhost"
FREEPBX_DB_NAME="asterisk"
CDR_DB_NAME="asteriskcdrdb"

echo -e "${BLUE}"
echo "╔════════════════════════════════════════════════════════════════╗"
echo "║                                                                ║"
echo "║     █████╗ ██████╗ ███████╗██████╗  ██████╗ ██████╗ ████████╗  ║"
echo "║    ██╔══██╗██╔══██╗██╔════╝██╔══██╗██╔═══██╗██╔══██╗╚══██╔══╝  ║"
echo "║    ███████║██████╔╝█████╗  ██████╔╝██║   ██║██████╔╝   ██║     ║"
echo "║    ██╔══██║██╔══██╗██╔══╝  ██╔═══╝ ██║   ██║██╔══██╗   ██║     ║"
echo "║    ██║  ██║██║  ██║███████╗██║     ╚██████╔╝██║  ██║   ██║     ║"
echo "║    ╚═╝  ╚═╝╚═╝  ╚═╝╚══════╝╚═╝      ╚═════╝ ╚═╝  ╚═╝   ╚═╝     ║"
echo "║                                                                ║"
echo "║           Call Center Analytics Platform v1.0                  ║"
echo "║                                                                ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo -e "${NC}"

# Functions
log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

log_step() {
    echo -e "\n${BLUE}==>${NC} $1"
}

check_root() {
    if [[ $EUID -ne 0 ]]; then
        log_error "This script must be run as root (use sudo)"
        exit 1
    fi
}

check_os() {
    log_step "Checking operating system..."

    if [ -f /etc/os-release ]; then
        . /etc/os-release
        OS=$NAME
        VER=$VERSION_ID
        log_info "Detected: $OS $VER"
    else
        log_warn "Could not detect OS version"
    fi
}

check_php() {
    log_step "Checking PHP installation..."

    if ! command -v php &> /dev/null; then
        log_error "PHP is not installed"
        echo "Please install PHP 8.0+ with the following extensions:"
        echo "  apt install php php-mysql php-curl php-json php-mbstring php-xml php-zip"
        exit 1
    fi

    PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
    log_info "PHP version: $PHP_VERSION"

    if ! php -r "exit(version_compare(PHP_VERSION, '$PHP_MIN_VERSION', '>=') ? 0 : 1);"; then
        log_error "PHP version must be $PHP_MIN_VERSION or higher"
        exit 1
    fi

    # Check required extensions
    REQUIRED_EXTENSIONS=("mysqli" "pdo" "pdo_mysql" "curl" "json" "mbstring")
    MISSING_EXTENSIONS=()

    for ext in "${REQUIRED_EXTENSIONS[@]}"; do
        if ! php -m | grep -qi "^$ext$"; then
            MISSING_EXTENSIONS+=($ext)
        fi
    done

    if [ ${#MISSING_EXTENSIONS[@]} -gt 0 ]; then
        log_error "Missing PHP extensions: ${MISSING_EXTENSIONS[*]}"
        echo "Install with: apt install php-${MISSING_EXTENSIONS[*]// / php-}"
        exit 1
    fi

    log_info "All required PHP extensions are installed"
}

check_apache() {
    log_step "Checking Apache installation..."

    if ! command -v apache2 &> /dev/null && ! command -v httpd &> /dev/null; then
        log_error "Apache is not installed"
        echo "Please install Apache: apt install apache2 libapache2-mod-php"
        exit 1
    fi

    # Check if mod_rewrite is enabled
    if command -v a2enmod &> /dev/null; then
        if ! apache2ctl -M 2>/dev/null | grep -q "rewrite_module"; then
            log_warn "Enabling mod_rewrite..."
            a2enmod rewrite
        fi
    fi

    log_info "Apache is installed"
}

check_mysql() {
    log_step "Checking MySQL/MariaDB..."

    if ! command -v mysql &> /dev/null; then
        log_error "MySQL/MariaDB client is not installed"
        exit 1
    fi

    log_info "MySQL/MariaDB client is installed"
}

get_database_credentials() {
    log_step "Database Configuration"

    echo ""
    read -p "MySQL Host [localhost]: " input
    DB_HOST=${input:-localhost}

    read -p "aReports Database Name [areports]: " input
    DB_NAME=${input:-areports}

    read -p "aReports Database User [areports]: " input
    DB_USER=${input:-areports}

    while true; do
        read -sp "aReports Database Password: " DB_PASS
        echo ""
        if [ -z "$DB_PASS" ]; then
            log_warn "Password cannot be empty"
        else
            read -sp "Confirm Password: " DB_PASS_CONFIRM
            echo ""
            if [ "$DB_PASS" = "$DB_PASS_CONFIRM" ]; then
                break
            else
                log_warn "Passwords do not match"
            fi
        fi
    done

    echo ""
    read -p "MySQL Root Password (for creating database): " -s MYSQL_ROOT_PASS
    echo ""

    # FreePBX database settings
    echo ""
    log_info "FreePBX/Asterisk Database Settings"
    read -p "FreePBX Database Host [localhost]: " input
    FREEPBX_DB_HOST=${input:-localhost}

    read -p "FreePBX Database Name [asterisk]: " input
    FREEPBX_DB_NAME=${input:-asterisk}

    read -p "CDR Database Name [asteriskcdrdb]: " input
    CDR_DB_NAME=${input:-asteriskcdrdb}

    read -p "FreePBX Database User [freepbxuser]: " FREEPBX_DB_USER
    FREEPBX_DB_USER=${FREEPBX_DB_USER:-freepbxuser}

    read -sp "FreePBX Database Password: " FREEPBX_DB_PASS
    echo ""
}

get_admin_credentials() {
    log_step "Admin User Configuration"

    echo ""
    read -p "Admin Username [admin]: " input
    ADMIN_USER=${input:-admin}

    read -p "Admin Email: " ADMIN_EMAIL
    while [ -z "$ADMIN_EMAIL" ]; do
        log_warn "Email is required"
        read -p "Admin Email: " ADMIN_EMAIL
    done

    while true; do
        read -sp "Admin Password: " ADMIN_PASS
        echo ""
        if [ ${#ADMIN_PASS} -lt 6 ]; then
            log_warn "Password must be at least 6 characters"
        else
            read -sp "Confirm Password: " ADMIN_PASS_CONFIRM
            echo ""
            if [ "$ADMIN_PASS" = "$ADMIN_PASS_CONFIRM" ]; then
                break
            else
                log_warn "Passwords do not match"
            fi
        fi
    done

    read -p "Admin First Name [Admin]: " input
    ADMIN_FIRST=${input:-Admin}

    read -p "Admin Last Name [User]: " input
    ADMIN_LAST=${input:-User}
}

get_ami_credentials() {
    log_step "Asterisk Manager Interface (AMI) Configuration"

    echo ""
    echo "AMI is used for real-time queue and agent monitoring."
    echo "You can configure this later in Settings if you skip now."
    echo ""

    read -p "Configure AMI now? [y/N]: " configure_ami

    if [[ "$configure_ami" =~ ^[Yy]$ ]]; then
        read -p "AMI Host [127.0.0.1]: " input
        AMI_HOST=${input:-127.0.0.1}

        read -p "AMI Port [5038]: " input
        AMI_PORT=${input:-5038}

        read -p "AMI Username: " AMI_USER

        read -sp "AMI Password: " AMI_PASS
        echo ""
    else
        AMI_HOST=""
        AMI_PORT=""
        AMI_USER=""
        AMI_PASS=""
    fi
}

create_database() {
    log_step "Creating database..."

    # Create database and user
    mysql -h "$DB_HOST" -u root -p"$MYSQL_ROOT_PASS" <<EOF
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'$DB_HOST' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'$DB_HOST';
GRANT SELECT ON \`$CDR_DB_NAME\`.* TO '$DB_USER'@'$DB_HOST';
GRANT SELECT ON \`$FREEPBX_DB_NAME\`.* TO '$DB_USER'@'$DB_HOST';
FLUSH PRIVILEGES;
EOF

    if [ $? -eq 0 ]; then
        log_info "Database created successfully"
    else
        log_error "Failed to create database"
        exit 1
    fi
}

import_schema() {
    log_step "Importing database schema..."

    SCHEMA_FILE="$INSTALL_DIR/install/schema.sql"

    if [ -f "$SCHEMA_FILE" ]; then
        mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$SCHEMA_FILE"
        log_info "Schema imported successfully"
    else
        log_error "Schema file not found: $SCHEMA_FILE"
        exit 1
    fi

    # Import schema updates if exists
    UPDATES_FILE="$INSTALL_DIR/install/schema_updates.sql"
    if [ -f "$UPDATES_FILE" ]; then
        log_info "Importing schema updates..."
        mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$UPDATES_FILE" 2>/dev/null || true
    fi
}

create_admin_user() {
    log_step "Creating admin user..."

    # Hash password using PHP
    HASHED_PASS=$(php -r "echo password_hash('$ADMIN_PASS', PASSWORD_DEFAULT);")

    mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" <<EOF
INSERT INTO roles (name, display_name, description, is_system) VALUES
('admin', 'Administrator', 'Full system access', 1)
ON DUPLICATE KEY UPDATE name=name;

SET @role_id = (SELECT id FROM roles WHERE name = 'admin' LIMIT 1);

INSERT INTO users (username, email, password, first_name, last_name, role_id, is_active, created_at)
VALUES ('$ADMIN_USER', '$ADMIN_EMAIL', '$HASHED_PASS', '$ADMIN_FIRST', '$ADMIN_LAST', @role_id, 1, NOW())
ON DUPLICATE KEY UPDATE email='$ADMIN_EMAIL';
EOF

    log_info "Admin user created: $ADMIN_USER"
}

create_config() {
    log_step "Creating configuration file..."

    CONFIG_FILE="$INSTALL_DIR/config/config.php"

    cat > "$CONFIG_FILE" <<EOF
<?php
/**
 * aReports Configuration File
 * Generated by installer on $(date)
 */

return [
    // Application
    'app' => [
        'name' => 'aReports',
        'version' => '1.0.0',
        'url' => '/areports',
        'timezone' => 'America/New_York',
        'debug' => false,
    ],

    // Main Database (aReports)
    'database' => [
        'host' => '$DB_HOST',
        'name' => '$DB_NAME',
        'user' => '$DB_USER',
        'pass' => '$DB_PASS',
        'charset' => 'utf8mb4',
    ],

    // CDR Database (Asterisk)
    'cdr_database' => [
        'host' => '$FREEPBX_DB_HOST',
        'name' => '$CDR_DB_NAME',
        'user' => '$FREEPBX_DB_USER',
        'pass' => '$FREEPBX_DB_PASS',
        'charset' => 'utf8mb4',
    ],

    // FreePBX Database
    'freepbx_database' => [
        'host' => '$FREEPBX_DB_HOST',
        'name' => '$FREEPBX_DB_NAME',
        'user' => '$FREEPBX_DB_USER',
        'pass' => '$FREEPBX_DB_PASS',
        'charset' => 'utf8mb4',
    ],

    // Asterisk Manager Interface
    'ami' => [
        'host' => '$AMI_HOST',
        'port' => ${AMI_PORT:-5038},
        'username' => '$AMI_USER',
        'password' => '$AMI_PASS',
        'timeout' => 5,
    ],

    // Session
    'session' => [
        'name' => 'areports_session',
        'lifetime' => 7200,
        'secure' => false,
        'httponly' => true,
    ],

    // Security
    'security' => [
        'secret_key' => '$(openssl rand -hex 32)',
        'password_min_length' => 6,
        'max_login_attempts' => 5,
        'lockout_time' => 900,
    ],

    // File Storage
    'storage' => [
        'recordings' => '/var/spool/asterisk/monitor',
        'exports' => '$INSTALL_DIR/storage/exports',
        'logs' => '$INSTALL_DIR/storage/logs',
        'temp' => '$INSTALL_DIR/storage/temp',
    ],

    // Email (configure in admin panel)
    'email' => [
        'enabled' => false,
        'smtp_host' => '',
        'smtp_port' => 587,
        'smtp_user' => '',
        'smtp_pass' => '',
        'smtp_secure' => 'tls',
        'from_email' => '',
        'from_name' => 'aReports',
    ],

    // Telegram (configure in admin panel)
    'telegram' => [
        'enabled' => false,
        'bot_token' => '',
        'default_chat_id' => '',
    ],
];
EOF

    chmod 640 "$CONFIG_FILE"
    chown $APACHE_USER:$APACHE_GROUP "$CONFIG_FILE"

    log_info "Configuration file created"
}

set_permissions() {
    log_step "Setting file permissions..."

    # Create storage directories
    mkdir -p "$INSTALL_DIR/storage/exports"
    mkdir -p "$INSTALL_DIR/storage/logs"
    mkdir -p "$INSTALL_DIR/storage/temp"
    mkdir -p "$INSTALL_DIR/storage/cache"

    # Set ownership
    chown -R $APACHE_USER:$APACHE_GROUP "$INSTALL_DIR"

    # Set directory permissions
    find "$INSTALL_DIR" -type d -exec chmod 755 {} \;

    # Set file permissions
    find "$INSTALL_DIR" -type f -exec chmod 644 {} \;

    # Make storage writable
    chmod -R 775 "$INSTALL_DIR/storage"

    # Protect config
    chmod 640 "$INSTALL_DIR/config/config.php"

    log_info "Permissions set successfully"
}

configure_apache() {
    log_step "Configuring Apache..."

    # Create virtual host or alias config
    APACHE_CONF="/etc/apache2/conf-available/areports.conf"

    cat > "$APACHE_CONF" <<EOF
# aReports Apache Configuration
Alias /areports $INSTALL_DIR/public

<Directory $INSTALL_DIR/public>
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted

    # URL Rewriting
    <IfModule mod_rewrite.c>
        RewriteEngine On
        RewriteBase /areports/

        # Handle Authorization Header
        RewriteCond %{HTTP:Authorization} .
        RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

        # Redirect to front controller
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteRule ^ index.php [L]
    </IfModule>
</Directory>

# Protect sensitive directories
<Directory $INSTALL_DIR/config>
    Require all denied
</Directory>

<Directory $INSTALL_DIR/core>
    Require all denied
</Directory>

<Directory $INSTALL_DIR/storage>
    Require all denied
</Directory>
EOF

    # Enable configuration
    if command -v a2enconf &> /dev/null; then
        a2enconf areports
        log_info "Apache configuration enabled"
    fi

    # Test Apache config
    if apache2ctl configtest 2>/dev/null; then
        log_info "Apache configuration is valid"
    else
        log_warn "Apache configuration test failed - please check manually"
    fi
}

restart_apache() {
    log_step "Restarting Apache..."

    if systemctl restart apache2 2>/dev/null; then
        log_info "Apache restarted successfully"
    elif service apache2 restart 2>/dev/null; then
        log_info "Apache restarted successfully"
    else
        log_warn "Could not restart Apache - please restart manually"
    fi
}

setup_cron() {
    log_step "Setting up cron jobs..."

    CRON_FILE="/etc/cron.d/areports"

    cat > "$CRON_FILE" <<EOF
# aReports Scheduled Tasks
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin

# Process alerts every minute
* * * * * $APACHE_USER php $INSTALL_DIR/cli/process_alerts.php > /dev/null 2>&1

# Process scheduled reports every minute
* * * * * $APACHE_USER php $INSTALL_DIR/cli/process_scheduled_reports.php > /dev/null 2>&1

# Daily Telegram summary (configure time in settings)
0 18 * * * $APACHE_USER php $INSTALL_DIR/cli/daily_telegram_summary.php > /dev/null 2>&1

# Clean old temp files daily
0 2 * * * $APACHE_USER find $INSTALL_DIR/storage/temp -type f -mtime +7 -delete > /dev/null 2>&1

# Clean old export files weekly
0 3 * * 0 $APACHE_USER find $INSTALL_DIR/storage/exports -type f -mtime +30 -delete > /dev/null 2>&1
EOF

    chmod 644 "$CRON_FILE"
    log_info "Cron jobs configured"
}

print_summary() {
    echo ""
    echo -e "${GREEN}"
    echo "╔════════════════════════════════════════════════════════════════╗"
    echo "║                 Installation Complete!                         ║"
    echo "╚════════════════════════════════════════════════════════════════╝"
    echo -e "${NC}"
    echo ""
    echo "Access aReports at: http://your-server/areports"
    echo ""
    echo "Login credentials:"
    echo "  Username: $ADMIN_USER"
    echo "  Password: (the password you entered)"
    echo ""
    echo "Important next steps:"
    echo "  1. Configure AMI settings if not done during install"
    echo "  2. Set up email notifications in Settings > Email"
    echo "  3. Configure Telegram bot in Settings > Telegram"
    echo "  4. Create user roles and permissions"
    echo "  5. Set up queues and agents"
    echo ""
    echo "Configuration file: $INSTALL_DIR/config/config.php"
    echo "Log files: $INSTALL_DIR/storage/logs/"
    echo ""
    echo -e "${YELLOW}Please secure your installation:${NC}"
    echo "  - Use HTTPS in production"
    echo "  - Change default passwords"
    echo "  - Restrict database access"
    echo ""
}

# Main installation flow
main() {
    check_root
    check_os
    check_php
    check_apache
    check_mysql

    echo ""
    echo -e "${YELLOW}This script will install aReports and configure your system.${NC}"
    echo ""
    read -p "Continue with installation? [y/N]: " confirm

    if [[ ! "$confirm" =~ ^[Yy]$ ]]; then
        echo "Installation cancelled."
        exit 0
    fi

    get_database_credentials
    get_admin_credentials
    get_ami_credentials

    echo ""
    log_step "Starting installation..."

    create_database
    import_schema
    create_admin_user
    create_config
    set_permissions
    configure_apache
    setup_cron
    restart_apache

    print_summary
}

# Run main function
main "$@"
