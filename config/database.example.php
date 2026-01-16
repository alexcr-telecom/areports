<?php
/**
 * Database Configuration Example
 *
 * Copy this file to database.php and update with your credentials.
 *
 * This file contains database connection settings for:
 * - aReports application database
 * - Asterisk CDR database
 * - FreePBX asterisk database
 */

return [
    // aReports application database
    'areports' => [
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'areports',
        'username' => 'areports',
        'password' => 'YOUR_AREPORTS_PASSWORD',
        'charset' => 'utf8mb4',
    ],

    // Asterisk CDR database (read-only access recommended)
    'asteriskcdrdb' => [
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'asteriskcdrdb',
        'username' => 'areports',
        'password' => 'YOUR_AREPORTS_PASSWORD',
        'charset' => 'utf8mb4',
    ],

    // FreePBX asterisk database (read-only for extensions/queues)
    'freepbx' => [
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'asterisk',
        'username' => 'areports',
        'password' => 'YOUR_AREPORTS_PASSWORD',
        'charset' => 'utf8mb4',
    ],
];
