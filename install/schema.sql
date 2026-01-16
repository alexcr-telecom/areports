-- ============================================
-- aReports Database Schema
-- Database: areports
-- ============================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------
-- USERS AND AUTHENTICATION
-- --------------------------------------------

CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `first_name` VARCHAR(50) NOT NULL,
    `last_name` VARCHAR(50) NOT NULL,
    `role_id` INT UNSIGNED NOT NULL,
    `extension` VARCHAR(20) NULL COMMENT 'Linked Asterisk extension for agents',
    `avatar` VARCHAR(255) NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `last_login` DATETIME NULL,
    `password_reset_token` VARCHAR(100) NULL,
    `password_reset_expires` DATETIME NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_role` (`role_id`),
    INDEX `idx_extension` (`extension`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `roles` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL UNIQUE,
    `display_name` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `is_system` TINYINT(1) DEFAULT 0 COMMENT 'System roles cannot be deleted',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `permissions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `display_name` VARCHAR(150) NOT NULL,
    `category` VARCHAR(50) NOT NULL COMMENT 'For grouping in UI',
    `description` TEXT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `role_permissions` (
    `role_id` INT UNSIGNED NOT NULL,
    `permission_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`role_id`, `permission_id`),
    FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_sessions` (
    `id` VARCHAR(128) PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` VARCHAR(255) NULL,
    `payload` TEXT NOT NULL,
    `last_activity` INT UNSIGNED NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_activity` (`last_activity`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `attempted_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `success` TINYINT(1) DEFAULT 0,
    INDEX `idx_username_time` (`username`, `attempted_at`),
    INDEX `idx_ip_time` (`ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------
-- QUEUE AND AGENT CONFIGURATION
-- --------------------------------------------

CREATE TABLE IF NOT EXISTS `queue_settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `queue_number` VARCHAR(20) NOT NULL UNIQUE,
    `display_name` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `sla_threshold_seconds` INT UNSIGNED DEFAULT 60 COMMENT 'SLA target in seconds',
    `warning_threshold_seconds` INT UNSIGNED DEFAULT 45 COMMENT 'Warning before SLA breach',
    `max_wait_time` INT UNSIGNED DEFAULT 300 COMMENT 'Max acceptable wait time',
    `is_monitored` TINYINT(1) DEFAULT 1,
    `color_code` VARCHAR(7) DEFAULT '#3498db' COMMENT 'For charts/wallboard',
    `sort_order` INT UNSIGNED DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_monitored` (`is_monitored`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `agent_settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `extension` VARCHAR(20) NOT NULL UNIQUE,
    `display_name` VARCHAR(100) NOT NULL,
    `user_id` INT UNSIGNED NULL COMMENT 'Link to areports user if applicable',
    `team` VARCHAR(50) NULL,
    `is_monitored` TINYINT(1) DEFAULT 1,
    `wrap_up_time` INT UNSIGNED DEFAULT 30 COMMENT 'Expected wrap-up time in seconds',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_team` (`team`),
    INDEX `idx_monitored` (`is_monitored`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `queue_agent_mapping` (
    `queue_id` INT UNSIGNED NOT NULL,
    `agent_id` INT UNSIGNED NOT NULL,
    `priority` INT UNSIGNED DEFAULT 0,
    PRIMARY KEY (`queue_id`, `agent_id`),
    FOREIGN KEY (`queue_id`) REFERENCES `queue_settings`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`agent_id`) REFERENCES `agent_settings`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------
-- REPORTS AND SCHEDULING
-- --------------------------------------------

CREATE TABLE IF NOT EXISTS `saved_reports` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `report_type` VARCHAR(50) NOT NULL COMMENT 'agent, queue, cdr, etc.',
    `parameters` JSON NOT NULL COMMENT 'Filters, date range, columns, etc.',
    `is_public` TINYINT(1) DEFAULT 0 COMMENT 'Visible to others with permission',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_type` (`report_type`),
    INDEX `idx_public` (`is_public`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `scheduled_reports` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `saved_report_id` INT UNSIGNED NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `report_type` VARCHAR(50) NOT NULL,
    `parameters` JSON NOT NULL,
    `schedule_type` ENUM('daily', 'weekly', 'monthly') NOT NULL,
    `schedule_time` TIME NOT NULL COMMENT 'Time to run',
    `schedule_day` INT UNSIGNED NULL COMMENT 'Day of week (1-7) or month (1-31)',
    `export_format` ENUM('pdf', 'excel', 'csv') DEFAULT 'pdf',
    `recipients` JSON NOT NULL COMMENT 'Array of email addresses',
    `is_active` TINYINT(1) DEFAULT 1,
    `last_run` DATETIME NULL,
    `next_run` DATETIME NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_next_run` (`next_run`),
    INDEX `idx_active` (`is_active`),
    FOREIGN KEY (`saved_report_id`) REFERENCES `saved_reports`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `scheduled_report_logs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `scheduled_report_id` INT UNSIGNED NOT NULL,
    `status` ENUM('success', 'failed', 'partial') NOT NULL,
    `recipients_sent` INT UNSIGNED DEFAULT 0,
    `error_message` TEXT NULL,
    `file_path` VARCHAR(255) NULL,
    `executed_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_report` (`scheduled_report_id`),
    INDEX `idx_executed` (`executed_at`),
    FOREIGN KEY (`scheduled_report_id`) REFERENCES `scheduled_reports`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------
-- ALERTS AND NOTIFICATIONS
-- --------------------------------------------

CREATE TABLE IF NOT EXISTS `alerts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `alert_type` ENUM('queue', 'agent', 'system') NOT NULL,
    `metric` VARCHAR(50) NOT NULL COMMENT 'calls_waiting, avg_wait, abandoned_rate, etc.',
    `queue_id` INT UNSIGNED NULL COMMENT 'Specific queue or NULL for all',
    `operator` ENUM('gt', 'lt', 'eq', 'gte', 'lte') NOT NULL,
    `threshold_value` DECIMAL(10,2) NOT NULL,
    `threshold_duration` INT UNSIGNED DEFAULT 60 COMMENT 'Seconds condition must persist',
    `cooldown_minutes` INT UNSIGNED DEFAULT 15 COMMENT 'Minutes before re-alerting',
    `notification_channels` JSON NOT NULL COMMENT '["email", "browser", "sms"]',
    `recipients` JSON NULL COMMENT 'Override default recipients',
    `is_active` TINYINT(1) DEFAULT 1,
    `last_triggered` DATETIME NULL,
    `trigger_count` INT UNSIGNED DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_active` (`is_active`),
    INDEX `idx_type` (`alert_type`),
    INDEX `idx_user` (`user_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`queue_id`) REFERENCES `queue_settings`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `alert_history` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `alert_id` INT UNSIGNED NOT NULL,
    `triggered_value` DECIMAL(10,2) NOT NULL,
    `threshold_value` DECIMAL(10,2) NOT NULL,
    `message` TEXT NOT NULL,
    `acknowledged_by` INT UNSIGNED NULL,
    `acknowledged_at` DATETIME NULL,
    `triggered_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_alert` (`alert_id`),
    INDEX `idx_triggered` (`triggered_at`),
    INDEX `idx_acknowledged` (`acknowledged_at`),
    FOREIGN KEY (`alert_id`) REFERENCES `alerts`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`acknowledged_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------
-- QUALITY MONITORING
-- --------------------------------------------

CREATE TABLE IF NOT EXISTS `evaluation_forms` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `evaluation_criteria` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `form_id` INT UNSIGNED NOT NULL,
    `category` VARCHAR(50) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `max_score` DECIMAL(5,2) NOT NULL,
    `weight` DECIMAL(3,2) DEFAULT 1.00,
    `sort_order` INT UNSIGNED DEFAULT 0,
    FOREIGN KEY (`form_id`) REFERENCES `evaluation_forms`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `call_evaluations` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uniqueid` VARCHAR(32) NOT NULL COMMENT 'Links to CDR uniqueid',
    `form_id` INT UNSIGNED NOT NULL,
    `evaluator_id` INT UNSIGNED NOT NULL,
    `agent_extension` VARCHAR(20) NOT NULL,
    `total_score` DECIMAL(5,2) NOT NULL,
    `max_possible_score` DECIMAL(5,2) NOT NULL,
    `percentage` DECIMAL(5,2) NOT NULL,
    `notes` TEXT NULL,
    `evaluated_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_uniqueid` (`uniqueid`),
    INDEX `idx_agent` (`agent_extension`),
    INDEX `idx_evaluator` (`evaluator_id`),
    INDEX `idx_date` (`evaluated_at`),
    FOREIGN KEY (`form_id`) REFERENCES `evaluation_forms`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`evaluator_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `evaluation_scores` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `evaluation_id` INT UNSIGNED NOT NULL,
    `criteria_id` INT UNSIGNED NOT NULL,
    `score` DECIMAL(5,2) NOT NULL,
    `comment` TEXT NULL,
    FOREIGN KEY (`evaluation_id`) REFERENCES `call_evaluations`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`criteria_id`) REFERENCES `evaluation_criteria`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------
-- DASHBOARD AND USER PREFERENCES
-- --------------------------------------------

CREATE TABLE IF NOT EXISTS `dashboard_layouts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `layout_type` ENUM('dashboard', 'wallboard') DEFAULT 'dashboard',
    `name` VARCHAR(100) DEFAULT 'Default',
    `widgets` JSON NOT NULL COMMENT 'Widget positions, sizes, configs',
    `is_default` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_type` (`layout_type`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_preferences` (
    `user_id` INT UNSIGNED PRIMARY KEY,
    `timezone` VARCHAR(50) DEFAULT 'Asia/Jerusalem',
    `date_format` VARCHAR(20) DEFAULT 'Y-m-d',
    `time_format` VARCHAR(20) DEFAULT 'H:i:s',
    `items_per_page` INT UNSIGNED DEFAULT 25,
    `default_queue_filter` JSON NULL,
    `default_date_range` VARCHAR(20) DEFAULT 'today',
    `theme` VARCHAR(20) DEFAULT 'light',
    `sidebar_collapsed` TINYINT(1) DEFAULT 0,
    `email_notifications` TINYINT(1) DEFAULT 1,
    `browser_notifications` TINYINT(1) DEFAULT 1,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------
-- SYSTEM SETTINGS
-- --------------------------------------------

CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `category` VARCHAR(50) NOT NULL,
    `setting_key` VARCHAR(100) NOT NULL,
    `setting_value` TEXT NULL,
    `value_type` ENUM('string', 'int', 'bool', 'json') DEFAULT 'string',
    `description` TEXT NULL,
    `is_encrypted` TINYINT(1) DEFAULT 0,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_category_key` (`category`, `setting_key`),
    INDEX `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------
-- AUDIT LOG
-- --------------------------------------------

CREATE TABLE IF NOT EXISTS `audit_log` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NULL,
    `action` VARCHAR(50) NOT NULL,
    `entity_type` VARCHAR(50) NOT NULL,
    `entity_id` INT UNSIGNED NULL,
    `old_values` JSON NULL,
    `new_values` JSON NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` VARCHAR(255) NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_entity` (`entity_type`, `entity_id`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------
-- CACHE TABLE
-- --------------------------------------------

CREATE TABLE IF NOT EXISTS `cache` (
    `cache_key` VARCHAR(255) PRIMARY KEY,
    `cache_value` MEDIUMBLOB NOT NULL,
    `expires_at` INT UNSIGNED NOT NULL,
    INDEX `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
