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

# AMI settings
AMI_HOST="127.0.0.1"
AMI_PORT="5038"
AMI_USER="areports"
AMI_PASS=""

# Auto-generate passwords
generate_password() {
    openssl rand -base64 16 | tr -dc 'a-zA-Z0-9' | head -c 16
}

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
    echo "aReports needs its own database and a MySQL user with access to"
    echo "Asterisk CDR database for reporting."
    echo ""

    read -p "MySQL Host [localhost]: " input
    DB_HOST=${input:-localhost}

    read -p "aReports Database Name [areports]: " input
    DB_NAME=${input:-areports}

    read -p "aReports Database User [areports]: " input
    DB_USER=${input:-areports}

    # Auto-generate or manual password
    read -p "Auto-generate database password? [Y/n]: " auto_pass
    if [[ ! "$auto_pass" =~ ^[Nn]$ ]]; then
        DB_PASS=$(generate_password)
        log_info "Database password auto-generated"
    else
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
    fi

    echo ""
    read -p "MySQL Root Password (for creating database): " -s MYSQL_ROOT_PASS
    echo ""

    # FreePBX database settings
    echo ""
    log_info "FreePBX/Asterisk Database Settings"
    echo "The aReports user will be granted SELECT access to these databases."
    echo ""

    read -p "FreePBX Database Host [localhost]: " input
    FREEPBX_DB_HOST=${input:-localhost}

    read -p "FreePBX Database Name [asterisk]: " input
    FREEPBX_DB_NAME=${input:-asterisk}

    read -p "CDR Database Name [asteriskcdrdb]: " input
    CDR_DB_NAME=${input:-asteriskcdrdb}

    # Check if we should use same user for FreePBX access
    echo ""
    read -p "Use aReports user for FreePBX database access? [Y/n]: " same_user
    if [[ ! "$same_user" =~ ^[Nn]$ ]]; then
        FREEPBX_DB_USER="$DB_USER"
        FREEPBX_DB_PASS="$DB_PASS"
        log_info "Using aReports user for all database access"
    else
        read -p "FreePBX Database User [freepbxuser]: " FREEPBX_DB_USER
        FREEPBX_DB_USER=${FREEPBX_DB_USER:-freepbxuser}
        read -sp "FreePBX Database Password: " FREEPBX_DB_PASS
        echo ""
    fi
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
    echo ""

    read -p "Configure AMI automatically? [Y/n]: " configure_ami

    if [[ ! "$configure_ami" =~ ^[Nn]$ ]]; then
        # Auto-generate AMI credentials
        AMI_HOST="127.0.0.1"
        AMI_PORT="5038"
        AMI_USER="areports"
        AMI_PASS=$(generate_password)

        log_info "AMI credentials will be auto-generated:"
        log_info "  Username: $AMI_USER"
        log_info "  Password: (auto-generated)"

        read -p "Use custom AMI username? [y/N]: " custom_ami
        if [[ "$custom_ami" =~ ^[Yy]$ ]]; then
            read -p "AMI Username [areports]: " input
            AMI_USER=${input:-areports}
        fi
    else
        AMI_HOST=""
        AMI_PORT=""
        AMI_USER=""
        AMI_PASS=""
        log_info "Skipping AMI configuration. Configure later in Settings."
    fi
}

create_database() {
    log_step "Creating database and MySQL user..."

    # Test MySQL connection first
    if ! mysql -h "$DB_HOST" -u root -p"$MYSQL_ROOT_PASS" -e "SELECT 1" &>/dev/null; then
        log_error "Cannot connect to MySQL. Check root password."
        exit 1
    fi

    log_info "Creating aReports database..."

    # Create database and user with all required permissions
    mysql -h "$DB_HOST" -u root -p"$MYSQL_ROOT_PASS" <<EOF
-- Drop existing database if exists (fresh install)
DROP DATABASE IF EXISTS \`$DB_NAME\`;

-- Create aReports database
CREATE DATABASE \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create or update user
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
CREATE USER IF NOT EXISTS '$DB_USER'@'127.0.0.1' IDENTIFIED BY '$DB_PASS';
CREATE USER IF NOT EXISTS '$DB_USER'@'$DB_HOST' IDENTIFIED BY '$DB_PASS';

-- Update password in case user exists
ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
ALTER USER '$DB_USER'@'127.0.0.1' IDENTIFIED BY '$DB_PASS';

-- Grant full access to aReports database
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'127.0.0.1';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'$DB_HOST';

-- Grant SELECT access to Asterisk CDR database
GRANT SELECT ON \`$CDR_DB_NAME\`.* TO '$DB_USER'@'localhost';
GRANT SELECT ON \`$CDR_DB_NAME\`.* TO '$DB_USER'@'127.0.0.1';
GRANT SELECT ON \`$CDR_DB_NAME\`.* TO '$DB_USER'@'$DB_HOST';

-- Grant SELECT access to FreePBX database
GRANT SELECT ON \`$FREEPBX_DB_NAME\`.* TO '$DB_USER'@'localhost';
GRANT SELECT ON \`$FREEPBX_DB_NAME\`.* TO '$DB_USER'@'127.0.0.1';
GRANT SELECT ON \`$FREEPBX_DB_NAME\`.* TO '$DB_USER'@'$DB_HOST';

FLUSH PRIVILEGES;
EOF

    if [ $? -eq 0 ]; then
        log_info "Database and user created successfully"
        echo ""
        log_info "MySQL Credentials:"
        echo "  Host:     $DB_HOST"
        echo "  Database: $DB_NAME"
        echo "  Username: $DB_USER"
        echo "  Password: $DB_PASS"
        echo ""
        log_info "Permissions granted:"
        echo "  - Full access to: $DB_NAME"
        echo "  - SELECT access to: $CDR_DB_NAME"
        echo "  - SELECT access to: $FREEPBX_DB_NAME"
        echo ""
    else
        log_error "Failed to create database"
        exit 1
    fi
}

import_schema() {
    log_step "Importing database schema..."

    SCHEMA_FILE="$INSTALL_DIR/install/schema.sql"
    SEED_FILE="$INSTALL_DIR/install/seed.sql"

    if [ -f "$SCHEMA_FILE" ]; then
        mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$SCHEMA_FILE"
        log_info "Schema imported successfully"
    else
        log_error "Schema file not found: $SCHEMA_FILE"
        exit 1
    fi

    # Import seed data
    if [ -f "$SEED_FILE" ]; then
        log_info "Importing seed data..."
        mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$SEED_FILE"
        log_info "Seed data imported successfully"
    fi
}

create_admin_user() {
    log_step "Updating admin user..."

    # Hash password using PHP
    HASHED_PASS=$(php -r "echo password_hash('$ADMIN_PASS', PASSWORD_DEFAULT);")

    mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" <<EOF
UPDATE users SET
    username = '$ADMIN_USER',
    email = '$ADMIN_EMAIL',
    password_hash = '$HASHED_PASS',
    first_name = '$ADMIN_FIRST',
    last_name = '$ADMIN_LAST'
WHERE id = 1;
EOF

    log_info "Admin user updated: $ADMIN_USER"
}

create_ami_user() {
    log_step "Creating AMI user in Asterisk..."

    if [ -z "$AMI_USER" ]; then
        log_info "Skipping AMI configuration (not configured)"
        return
    fi

    AMI_CONFIG="/etc/asterisk/manager_custom.conf"

    # Check if Asterisk directory exists
    if [ ! -d "/etc/asterisk" ]; then
        log_warn "Asterisk config directory not found. Skipping AMI setup."
        log_warn "Manually add AMI user to your Asterisk installation."
        return
    fi

    # Create manager_custom.conf if it doesn't exist
    if [ ! -f "$AMI_CONFIG" ]; then
        touch "$AMI_CONFIG"
        chown asterisk:asterisk "$AMI_CONFIG" 2>/dev/null || true
        log_info "Created $AMI_CONFIG"
    fi

    # Check if areports user already exists
    if grep -q "^\[areports\]" "$AMI_CONFIG" 2>/dev/null; then
        log_warn "AMI user 'areports' already exists in $AMI_CONFIG"
        log_info "Updating existing configuration..."
        # Remove existing areports section
        sed -i '/^\[areports\]/,/^$/d' "$AMI_CONFIG"
    fi

    # Add AMI user configuration
    cat >> "$AMI_CONFIG" <<EOF

; aReports AMI User - Auto-generated $(date '+%Y-%m-%d %H:%M:%S')
[$AMI_USER]
secret = $AMI_PASS
deny = 0.0.0.0/0.0.0.0
permit = 127.0.0.1/255.255.255.255
read = system,call,agent,user,config,command,reporting
write = system,call,agent,user,config,command,reporting,originate
writetimeout = 5000
eventfilter = !Event: RTCP*
eventfilter = !Event: VarSet
eventfilter = !Event: Newexten
EOF

    log_info "AMI user added to $AMI_CONFIG"

    # Reload Asterisk manager
    if command -v asterisk &> /dev/null; then
        if asterisk -rx "manager reload" 2>/dev/null; then
            log_info "Asterisk manager reloaded successfully"
        else
            log_warn "Could not reload Asterisk. Run manually: asterisk -rx 'manager reload'"
        fi
    else
        log_warn "Asterisk not found. Reload manager manually after installing Asterisk."
    fi

    echo ""
    log_info "AMI Credentials:"
    echo "  Host:     $AMI_HOST"
    echo "  Port:     $AMI_PORT"
    echo "  Username: $AMI_USER"
    echo "  Password: $AMI_PASS"
    echo ""
}

create_config() {
    log_step "Creating configuration files..."

    # Create database.php config
    DB_CONFIG_FILE="$INSTALL_DIR/config/database.php"
    cat > "$DB_CONFIG_FILE" <<EOF
<?php
/**
 * Database Configuration
 * Generated by installer on $(date)
 */

return [
    // Main aReports database
    'areports' => [
        'host' => '$DB_HOST',
        'port' => 3306,
        'database' => '$DB_NAME',
        'username' => '$DB_USER',
        'password' => '$DB_PASS',
        'charset' => 'utf8mb4',
    ],

    // Asterisk CDR database
    'asteriskcdrdb' => [
        'host' => '$FREEPBX_DB_HOST',
        'port' => 3306,
        'database' => '$CDR_DB_NAME',
        'username' => '$DB_USER',
        'password' => '$DB_PASS',
        'charset' => 'utf8mb4',
    ],

    // FreePBX database
    'freepbx' => [
        'host' => '$FREEPBX_DB_HOST',
        'port' => 3306,
        'database' => '$FREEPBX_DB_NAME',
        'username' => '$DB_USER',
        'password' => '$DB_PASS',
        'charset' => 'utf8mb4',
    ],
];
EOF

    chmod 640 "$DB_CONFIG_FILE"
    chown $APACHE_USER:$APACHE_GROUP "$DB_CONFIG_FILE" 2>/dev/null || true
    log_info "Database config created: $DB_CONFIG_FILE"

    # Create ami.php config
    AMI_CONFIG_FILE="$INSTALL_DIR/config/ami.php"
    cat > "$AMI_CONFIG_FILE" <<EOF
<?php
/**
 * Asterisk Manager Interface Configuration
 * Generated by installer on $(date)
 */

return [
    'host' => '${AMI_HOST:-127.0.0.1}',
    'port' => ${AMI_PORT:-5038},
    'username' => '${AMI_USER:-}',
    'secret' => '${AMI_PASS:-}',
    'connect_timeout' => 5,
    'read_timeout' => 5,
];
EOF

    chmod 640 "$AMI_CONFIG_FILE"
    chown $APACHE_USER:$APACHE_GROUP "$AMI_CONFIG_FILE" 2>/dev/null || true
    log_info "AMI config created: $AMI_CONFIG_FILE"

    # Update app.php debug setting
    APP_CONFIG_FILE="$INSTALL_DIR/config/app.php"
    if [ -f "$APP_CONFIG_FILE" ]; then
        sed -i "s/'debug' => true/'debug' => false/" "$APP_CONFIG_FILE"
        log_info "Debug mode disabled in app.php"
    fi

    log_info "Configuration files created successfully"
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

    # Protect config files with sensitive credentials
    if [ -f "$INSTALL_DIR/config/database.php" ]; then
        chmod 640 "$INSTALL_DIR/config/database.php"
    fi
    if [ -f "$INSTALL_DIR/config/ami.php" ]; then
        chmod 640 "$INSTALL_DIR/config/ami.php"
    fi

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

save_credentials() {
    log_step "Saving credentials to file..."

    CREDS_FILE="$INSTALL_DIR/install/credentials.txt"

    cat > "$CREDS_FILE" <<EOF
================================================================================
aReports Installation Credentials
Generated: $(date '+%Y-%m-%d %H:%M:%S')
================================================================================

WEB ACCESS
----------
URL:      http://your-server/areports
Username: $ADMIN_USER
Password: (as entered during installation)

MYSQL DATABASE
--------------
Host:     $DB_HOST
Database: $DB_NAME
Username: $DB_USER
Password: $DB_PASS

Database Permissions:
  - Full access to: $DB_NAME
  - SELECT access to: $CDR_DB_NAME
  - SELECT access to: $FREEPBX_DB_NAME

AMI (ASTERISK MANAGER INTERFACE)
--------------------------------
Host:     ${AMI_HOST:-Not configured}
Port:     ${AMI_PORT:-5038}
Username: ${AMI_USER:-Not configured}
Password: ${AMI_PASS:-Not configured}

Config file: /etc/asterisk/manager_custom.conf

CONFIGURATION FILES
-------------------
Main config:  $INSTALL_DIR/config/database.php
AMI config:   $INSTALL_DIR/config/ami.php
App config:   $INSTALL_DIR/config/app.php

================================================================================
IMPORTANT: Delete this file after noting down the credentials!
           File location: $CREDS_FILE
================================================================================
EOF

    chmod 600 "$CREDS_FILE"
    log_info "Credentials saved to: $CREDS_FILE"
}

print_summary() {
    # Save credentials to file
    save_credentials

    echo ""
    echo -e "${GREEN}"
    echo "╔════════════════════════════════════════════════════════════════╗"
    echo "║                 Installation Complete!                         ║"
    echo "╚════════════════════════════════════════════════════════════════╝"
    echo -e "${NC}"
    echo ""
    echo "Access aReports at: http://your-server/areports"
    echo ""
    echo -e "${YELLOW}=== LOGIN CREDENTIALS ===${NC}"
    echo "  Username: $ADMIN_USER"
    echo "  Password: (the password you entered)"
    echo ""
    echo -e "${YELLOW}=== MYSQL DATABASE ===${NC}"
    echo "  Host:     $DB_HOST"
    echo "  Database: $DB_NAME"
    echo "  Username: $DB_USER"
    echo "  Password: $DB_PASS"
    echo ""
    if [ -n "$AMI_USER" ]; then
        echo -e "${YELLOW}=== AMI CREDENTIALS ===${NC}"
        echo "  Host:     $AMI_HOST"
        echo "  Port:     $AMI_PORT"
        echo "  Username: $AMI_USER"
        echo "  Password: $AMI_PASS"
        echo "  Config:   /etc/asterisk/manager_custom.conf"
        echo ""
    fi
    echo -e "${YELLOW}=== FILES ===${NC}"
    echo "  Credentials file: $INSTALL_DIR/install/credentials.txt"
    echo "  Configuration:    $INSTALL_DIR/config/"
    echo "  Logs:             $INSTALL_DIR/storage/logs/"
    echo ""
    echo -e "${RED}IMPORTANT:${NC}"
    echo "  1. Note down the credentials above"
    echo "  2. Delete credentials.txt after saving: rm $INSTALL_DIR/install/credentials.txt"
    echo "  3. Use HTTPS in production"
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
    create_ami_user
    create_config
    set_permissions
    configure_apache
    setup_cron
    restart_apache

    print_summary
}

# Run main function
main "$@"
