<?php
/**
 * Application Configuration
 */

return [
    // Application name
    'name' => 'aReports',

    // Application version
    'version' => '1.0.0',

    // Debug mode (set to false in production)
    'debug' => true,

    // Default timezone
    'timezone' => 'Asia/Jerusalem',

    // Session lifetime in seconds (2 hours)
    'session_lifetime' => 7200,

    // Date/time formats
    'date_format' => 'd/m/Y',
    'time_format' => 'H:i:s',
    'datetime_format' => 'd/m/Y H:i:s',

    // Pagination
    'items_per_page' => 25,

    // Base URL path
    'base_path' => '/areports',

    // Storage paths
    'storage_path' => __DIR__ . '/../storage',
    'logs_path' => __DIR__ . '/../storage/logs',
    'cache_path' => __DIR__ . '/../storage/cache',
    'exports_path' => __DIR__ . '/../storage/exports',

    // Recording settings
    'recordings' => [
        'enabled' => true,
        'path' => '/var/spool/asterisk/monitor',
        'format' => 'wav',
    ],

    // Real-time settings
    'realtime' => [
        'refresh_interval' => 5000, // milliseconds
        'websocket_enabled' => true,
        'websocket_port' => 8080,
    ],

    // Security settings
    'security' => [
        'max_login_attempts' => 5,
        'lockout_duration' => 900, // 15 minutes
        'password_min_length' => 8,
        'csrf_enabled' => true,
    ],
];
