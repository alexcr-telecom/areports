# aReports Installation Guide

## Requirements

- **Operating System**: Debian/Ubuntu or CentOS/RHEL
- **Web Server**: Apache 2.4+
- **PHP**: 8.0 or higher
- **Database**: MySQL 5.7+ or MariaDB 10.3+
- **Asterisk**: 16+ with FreePBX (optional but recommended)

### PHP Extensions Required
- mysqli / pdo_mysql
- curl
- json
- mbstring
- xml
- zip

## Quick Installation

### 1. Automated Install (Recommended)

```bash
# Download and extract aReports to /var/www/html/areports
cd /var/www/html/areports/install

# Run the installer
sudo bash install.sh
```

The installer will:
- Check system requirements
- Create the database and user
- Import the database schema
- Create an admin user
- Configure Apache
- Set up cron jobs
- Generate configuration file

### 2. Manual Installation

#### Step 1: Create Database

```sql
CREATE DATABASE areports CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'areports'@'localhost' IDENTIFIED BY 'your-password';
GRANT ALL PRIVILEGES ON areports.* TO 'areports'@'localhost';
GRANT SELECT ON asteriskcdrdb.* TO 'areports'@'localhost';
GRANT SELECT ON asterisk.* TO 'areports'@'localhost';
FLUSH PRIVILEGES;
```

#### Step 2: Import Schema

```bash
mysql -u areports -p areports < /var/www/html/areports/install/schema.sql
mysql -u areports -p areports < /var/www/html/areports/install/seed.sql
mysql -u areports -p areports < /var/www/html/areports/install/schema_updates.sql
```

#### Step 3: Configure Application

Copy and edit the configuration file:

```bash
cp /var/www/html/areports/config/config.example.php /var/www/html/areports/config/config.php
nano /var/www/html/areports/config/config.php
```

Update the following settings:
- Database credentials (areports, CDR, FreePBX)
- AMI connection settings
- Application timezone
- Security secret key

#### Step 4: Set Permissions

```bash
# Set ownership
sudo chown -R www-data:www-data /var/www/html/areports

# Set directory permissions
sudo find /var/www/html/areports -type d -exec chmod 755 {} \;

# Set file permissions
sudo find /var/www/html/areports -type f -exec chmod 644 {} \;

# Make storage writable
sudo chmod -R 775 /var/www/html/areports/storage

# Protect config
sudo chmod 640 /var/www/html/areports/config/config.php
```

#### Step 5: Configure Apache

Create `/etc/apache2/conf-available/areports.conf`:

```apache
Alias /areports /var/www/html/areports/public

<Directory /var/www/html/areports/public>
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>

<Directory /var/www/html/areports/config>
    Require all denied
</Directory>

<Directory /var/www/html/areports/storage>
    Require all denied
</Directory>
```

Enable the configuration:

```bash
sudo a2enmod rewrite
sudo a2enconf areports
sudo systemctl restart apache2
```

#### Step 6: Create Admin User

```bash
# Using PHP CLI
php /var/www/html/areports/cli/create_admin.php
```

Or via SQL:

```sql
INSERT INTO users (username, email, password_hash, first_name, last_name, role_id, is_active)
VALUES ('admin', 'admin@example.com', '$2y$10$...', 'Admin', 'User', 1, 1);
```

#### Step 7: Set Up Cron Jobs

Add to `/etc/cron.d/areports`:

```cron
# Process alerts every minute
* * * * * www-data php /var/www/html/areports/cli/process_alerts.php > /dev/null 2>&1

# Process scheduled reports
* * * * * www-data php /var/www/html/areports/cli/process_scheduled_reports.php > /dev/null 2>&1

# Daily Telegram summary
0 18 * * * www-data php /var/www/html/areports/cli/daily_telegram_summary.php > /dev/null 2>&1
```

## Post-Installation

### Access the Application

Open your browser and navigate to:
```
http://your-server/areports
```

### Default Login

If you used the automated installer, use the credentials you entered.

For manual installation with seed data:
- Username: `admin`
- Password: `admin123` (change immediately!)

### Configure AMI

For real-time monitoring, configure Asterisk Manager Interface:

1. Edit `/etc/asterisk/manager.conf`:

```ini
[areports]
secret = your-ami-password
deny = 0.0.0.0/0.0.0.0
permit = 127.0.0.1/255.255.255.255
read = system,call,agent,user,config,command,reporting
write = system,call,agent,user,config,command,reporting
```

2. Reload Asterisk:
```bash
asterisk -rx "manager reload"
```

3. Update aReports settings with AMI credentials

### Configure Email (Optional)

1. Go to Settings > Email Settings
2. Enter SMTP server details
3. Test the connection

### Configure Telegram (Optional)

1. Create a Telegram bot via @BotFather
2. Get the bot token
3. Go to Settings > Telegram Settings
4. Enter the bot token and chat ID
5. Test the connection

## Troubleshooting

### Permission Denied Errors

```bash
sudo chown -R www-data:www-data /var/www/html/areports
sudo chmod -R 775 /var/www/html/areports/storage
```

### Database Connection Failed

1. Check credentials in `config/config.php`
2. Verify MySQL user has proper permissions
3. Check if MySQL is running: `systemctl status mysql`

### AMI Connection Failed

1. Verify AMI is enabled in `/etc/asterisk/manager.conf`
2. Check AMI credentials
3. Verify firewall allows connection to port 5038
4. Test with: `telnet localhost 5038`

### Apache Errors

```bash
# Check Apache error log
tail -f /var/log/apache2/error.log

# Test configuration
apache2ctl configtest
```

## Upgrading

1. Backup database and config:
```bash
mysqldump areports > areports_backup.sql
cp config/config.php config/config.php.bak
```

2. Download new version

3. Run schema updates:
```bash
mysql -u areports -p areports < install/schema_updates.sql
```

4. Clear cache:
```bash
rm -rf storage/cache/*
```

## Support

For issues and feature requests, please visit:
https://github.com/your-repo/areports/issues
