<?php
/**
 * Main Application Class
 * Bootstrap and dependency container
 */

namespace aReports\Core;

class App
{
    private static ?App $instance = null;
    private ?Database $db = null;
    private ?Database $cdrDb = null;
    private ?Database $freepbxDb = null;
    private ?Session $session = null;
    private ?Auth $auth = null;
    private ?Router $router = null;
    private array $config = [];
    private array $settings = [];
    private bool $settingsLoaded = false;

    private function __construct()
    {
        $this->loadConfig();
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Load configuration files
     */
    private function loadConfig(): void
    {
        $configPath = dirname(__DIR__) . '/config';

        $this->config = [
            'app' => require $configPath . '/app.php',
            'database' => require $configPath . '/database.php',
        ];

        // Set timezone
        date_default_timezone_set($this->config['app']['timezone'] ?? 'UTC');

        // Set error reporting based on debug mode
        if ($this->config['app']['debug'] ?? false) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        } else {
            error_reporting(0);
            ini_set('display_errors', '0');
        }
    }

    /**
     * Get configuration value
     */
    public function config(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Get aReports database connection
     */
    public function getDb(): Database
    {
        if ($this->db === null) {
            $this->db = new Database($this->config['database']['areports'], 'areports');
        }
        return $this->db;
    }

    /**
     * Get asteriskcdrdb database connection
     */
    public function getCdrDb(): Database
    {
        if ($this->cdrDb === null) {
            $this->cdrDb = new Database($this->config['database']['asteriskcdrdb'], 'cdrdb');
        }
        return $this->cdrDb;
    }

    /**
     * Get FreePBX asterisk database connection
     */
    public function getFreepbxDb(): Database
    {
        if ($this->freepbxDb === null) {
            $this->freepbxDb = new Database($this->config['database']['freepbx'], 'freepbx');
        }
        return $this->freepbxDb;
    }

    /**
     * Get session manager
     */
    public function getSession(): Session
    {
        if ($this->session === null) {
            $this->session = new Session([
                'lifetime' => $this->config['app']['session_lifetime'] ?? 7200
            ]);
            $this->session->start();
        }
        return $this->session;
    }

    /**
     * Get auth manager
     */
    public function getAuth(): Auth
    {
        if ($this->auth === null) {
            $this->auth = new Auth($this->getSession(), $this->getDb());
        }
        return $this->auth;
    }

    /**
     * Get router
     */
    public function getRouter(): Router
    {
        if ($this->router === null) {
            $this->router = new Router('/areports');
        }
        return $this->router;
    }

    /**
     * Load settings from database
     */
    private function loadSettings(): void
    {
        if ($this->settingsLoaded) {
            return;
        }

        try {
            $sql = "SELECT category, setting_key, setting_value, value_type FROM settings";
            $rows = $this->getDb()->fetchAll($sql);

            foreach ($rows as $row) {
                $key = $row['category'] . '.' . $row['setting_key'];
                $value = $row['setting_value'];

                // Cast to appropriate type
                $value = match ($row['value_type']) {
                    'int' => (int)$value,
                    'bool' => (bool)$value,
                    'json' => json_decode($value, true),
                    default => $value
                };

                $this->settings[$key] = $value;
            }

            $this->settingsLoaded = true;
        } catch (\Exception $e) {
            // Settings table may not exist during installation
            $this->settingsLoaded = true;
        }
    }

    /**
     * Get a setting value
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        $this->loadSettings();
        return $this->settings[$key] ?? $default;
    }

    /**
     * Set a setting value
     */
    public function setSetting(string $category, string $key, mixed $value, string $type = 'string'): void
    {
        $dbValue = match ($type) {
            'json' => json_encode($value),
            'bool' => $value ? '1' : '0',
            default => (string)$value
        };

        $exists = $this->getDb()->count('settings', [
            'category' => $category,
            'setting_key' => $key
        ]);

        if ($exists) {
            $this->getDb()->update('settings', [
                'setting_value' => $dbValue,
                'value_type' => $type
            ], [
                'category' => $category,
                'setting_key' => $key
            ]);
        } else {
            $this->getDb()->insert('settings', [
                'category' => $category,
                'setting_key' => $key,
                'setting_value' => $dbValue,
                'value_type' => $type
            ]);
        }

        $this->settings[$category . '.' . $key] = $value;
    }

    /**
     * Run the application
     */
    public function run(): void
    {
        // Start session
        $this->getSession();

        // Load routes
        require dirname(__DIR__) . '/config/routes.php';

        // Dispatch request
        $this->getRouter()->dispatch($this);
    }

    /**
     * Get the base URL
     */
    public function baseUrl(): string
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host . '/areports';
    }

    /**
     * Get asset URL
     */
    public function asset(string $path): string
    {
        return '/areports/public/' . ltrim($path, '/');
    }

    /**
     * Generate URL for a route
     */
    public function url(string $path): string
    {
        return '/areports/' . ltrim($path, '/');
    }

    /**
     * Check if in debug mode
     */
    public function isDebug(): bool
    {
        return $this->config['app']['debug'] ?? false;
    }

    /**
     * Log a message
     */
    public function log(string $level, string $message, array $context = []): void
    {
        $logFile = dirname(__DIR__) . '/storage/logs/app.log';
        $timestamp = date('Y-m-d H:i:s');

        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
        $logLine = "[{$timestamp}] {$level}: {$message}{$contextStr}\n";

        file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
    }

    /**
     * Log error
     */
    public function logError(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    /**
     * Log info
     */
    public function logInfo(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    /**
     * Log debug
     */
    public function logDebug(string $message, array $context = []): void
    {
        if ($this->isDebug()) {
            $this->log('DEBUG', $message, $context);
        }
    }
}
