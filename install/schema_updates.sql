-- ============================================
-- aReports Database Schema Updates
-- Run these updates for new features
-- ============================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------
-- API Keys Table
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS `api_keys` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NULL,
    `name` VARCHAR(100) NOT NULL,
    `api_key` VARCHAR(64) NOT NULL UNIQUE,
    `permissions` JSON NULL COMMENT 'Allowed API endpoints/actions',
    `rate_limit` INT UNSIGNED DEFAULT 1000 COMMENT 'Requests per hour',
    `is_active` TINYINT(1) DEFAULT 1,
    `last_used_at` DATETIME NULL,
    `expires_at` DATETIME NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_api_key` (`api_key`),
    INDEX `idx_active` (`is_active`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------
-- Browser Notifications Table
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS `browser_notifications` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NULL,
    `title` VARCHAR(200) NOT NULL,
    `body` TEXT NULL,
    `type` ENUM('info', 'success', 'warning', 'error', 'alert') DEFAULT 'info',
    `data` JSON NULL,
    `read` TINYINT(1) DEFAULT 0,
    `read_at` DATETIME NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_read` (`read`),
    INDEX `idx_created` (`created_at`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------
-- Campaigns Table
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS `campaigns` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `type` ENUM('inbound', 'outbound', 'blended') NOT NULL DEFAULT 'inbound',
    `queue_id` INT UNSIGNED NULL,
    `status` ENUM('draft', 'active', 'paused', 'completed', 'archived') DEFAULT 'draft',
    `start_date` DATE NULL,
    `end_date` DATE NULL,
    `target_calls` INT UNSIGNED NULL,
    `target_conversion` DECIMAL(5,2) NULL COMMENT 'Target conversion rate %',
    `settings` JSON NULL COMMENT 'Campaign-specific settings',
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_status` (`status`),
    INDEX `idx_type` (`type`),
    INDEX `idx_dates` (`start_date`, `end_date`),
    FOREIGN KEY (`queue_id`) REFERENCES `queue_settings`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------
-- Campaign Lists (Lead Lists)
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS `campaign_lists` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `campaign_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `total_records` INT UNSIGNED DEFAULT 0,
    `called_records` INT UNSIGNED DEFAULT 0,
    `status` ENUM('active', 'paused', 'completed') DEFAULT 'active',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_campaign` (`campaign_id`),
    FOREIGN KEY (`campaign_id`) REFERENCES `campaigns`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------
-- Campaign Leads
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS `campaign_leads` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `list_id` INT UNSIGNED NOT NULL,
    `phone_number` VARCHAR(20) NOT NULL,
    `first_name` VARCHAR(50) NULL,
    `last_name` VARCHAR(50) NULL,
    `email` VARCHAR(100) NULL,
    `company` VARCHAR(100) NULL,
    `custom_fields` JSON NULL,
    `status` ENUM('new', 'called', 'callback', 'converted', 'dnc', 'invalid') DEFAULT 'new',
    `call_count` INT UNSIGNED DEFAULT 0,
    `last_call_at` DATETIME NULL,
    `last_result` VARCHAR(50) NULL,
    `assigned_agent` VARCHAR(20) NULL,
    `callback_at` DATETIME NULL,
    `notes` TEXT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_list` (`list_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_phone` (`phone_number`),
    INDEX `idx_callback` (`callback_at`),
    FOREIGN KEY (`list_id`) REFERENCES `campaign_lists`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------
-- Campaign Dispositions
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS `campaign_dispositions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `campaign_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(50) NOT NULL,
    `description` TEXT NULL,
    `category` ENUM('positive', 'negative', 'neutral', 'callback') NOT NULL,
    `is_conversion` TINYINT(1) DEFAULT 0,
    `requires_callback` TINYINT(1) DEFAULT 0,
    `sort_order` INT UNSIGNED DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    INDEX `idx_campaign` (`campaign_id`),
    FOREIGN KEY (`campaign_id`) REFERENCES `campaigns`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------
-- Campaign Call Results
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS `campaign_call_results` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `campaign_id` INT UNSIGNED NOT NULL,
    `lead_id` INT UNSIGNED NULL,
    `agent_extension` VARCHAR(20) NOT NULL,
    `phone_number` VARCHAR(20) NOT NULL,
    `disposition_id` INT UNSIGNED NULL,
    `uniqueid` VARCHAR(32) NULL COMMENT 'Link to CDR',
    `call_duration` INT UNSIGNED DEFAULT 0,
    `talk_time` INT UNSIGNED DEFAULT 0,
    `notes` TEXT NULL,
    `callback_scheduled` DATETIME NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_campaign` (`campaign_id`),
    INDEX `idx_lead` (`lead_id`),
    INDEX `idx_agent` (`agent_extension`),
    INDEX `idx_disposition` (`disposition_id`),
    INDEX `idx_date` (`created_at`),
    FOREIGN KEY (`campaign_id`) REFERENCES `campaigns`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`lead_id`) REFERENCES `campaign_leads`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`disposition_id`) REFERENCES `campaign_dispositions`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------
-- Wallboard Layouts
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS `wallboard_layouts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `layout_type` ENUM('grid', 'sidebar', 'fullwidth', 'custom') DEFAULT 'grid',
    `columns` INT UNSIGNED DEFAULT 3,
    `widgets` JSON NOT NULL COMMENT 'Widget configuration',
    `theme` VARCHAR(20) DEFAULT 'dark',
    `refresh_interval` INT UNSIGNED DEFAULT 5000,
    `is_public` TINYINT(1) DEFAULT 0,
    `is_default` TINYINT(1) DEFAULT 0,
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_public` (`is_public`),
    INDEX `idx_default` (`is_default`),
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------
-- Alert Escalation Rules
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS `alert_escalation_rules` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `alert_id` INT UNSIGNED NOT NULL,
    `level` INT UNSIGNED NOT NULL DEFAULT 1,
    `delay_minutes` INT UNSIGNED NOT NULL DEFAULT 15 COMMENT 'Minutes after alert before escalating',
    `notification_channels` JSON NOT NULL,
    `recipients` JSON NOT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_alert` (`alert_id`),
    INDEX `idx_level` (`level`),
    FOREIGN KEY (`alert_id`) REFERENCES `alerts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------
-- QA Calibration Sessions
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS `calibration_sessions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `uniqueid` VARCHAR(32) NOT NULL COMMENT 'Call to calibrate',
    `form_id` INT UNSIGNED NOT NULL,
    `status` ENUM('scheduled', 'in_progress', 'completed', 'cancelled') DEFAULT 'scheduled',
    `scheduled_at` DATETIME NOT NULL,
    `completed_at` DATETIME NULL,
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_status` (`status`),
    INDEX `idx_scheduled` (`scheduled_at`),
    FOREIGN KEY (`form_id`) REFERENCES `evaluation_forms`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------
-- Calibration Participants
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS `calibration_participants` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `session_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `status` ENUM('invited', 'joined', 'completed', 'declined') DEFAULT 'invited',
    `evaluation_id` INT UNSIGNED NULL COMMENT 'Their evaluation for this call',
    `invited_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `completed_at` DATETIME NULL,
    UNIQUE KEY `uk_session_user` (`session_id`, `user_id`),
    FOREIGN KEY (`session_id`) REFERENCES `calibration_sessions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`evaluation_id`) REFERENCES `call_evaluations`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------
-- Saved Report Filters
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS `saved_filters` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `report_type` VARCHAR(50) NOT NULL,
    `filters` JSON NOT NULL,
    `is_default` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_type` (`report_type`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------
-- Report Builder Templates
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS `report_templates` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `data_source` VARCHAR(50) NOT NULL COMMENT 'cdr, queuelog, agents, etc.',
    `columns` JSON NOT NULL COMMENT 'Selected columns',
    `filters` JSON NULL COMMENT 'Default filters',
    `grouping` JSON NULL COMMENT 'Group by settings',
    `sorting` JSON NULL COMMENT 'Sort settings',
    `chart_config` JSON NULL COMMENT 'Chart settings if applicable',
    `is_public` TINYINT(1) DEFAULT 0,
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_public` (`is_public`),
    INDEX `idx_source` (`data_source`),
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------
-- Add telegram_chat_id to user_preferences
-- --------------------------------------------
ALTER TABLE `user_preferences`
    ADD COLUMN IF NOT EXISTS `telegram_chat_id` VARCHAR(50) NULL AFTER `browser_notifications`,
    ADD COLUMN IF NOT EXISTS `telegram_notifications` TINYINT(1) DEFAULT 0 AFTER `telegram_chat_id`;

-- --------------------------------------------
-- Add notification fields to alerts table
-- --------------------------------------------
ALTER TABLE `alerts`
    ADD COLUMN IF NOT EXISTS `escalation_enabled` TINYINT(1) DEFAULT 0 AFTER `is_active`;

-- --------------------------------------------
-- Pause Causes Table (if not exists)
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS `pause_causes` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(20) NOT NULL UNIQUE,
    `name` VARCHAR(50) NOT NULL,
    `description` TEXT NULL,
    `is_billable` TINYINT(1) DEFAULT 0,
    `color_code` VARCHAR(7) DEFAULT '#6c757d',
    `sort_order` INT UNSIGNED DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default pause causes
INSERT IGNORE INTO `pause_causes` (`code`, `name`, `is_billable`, `color_code`, `sort_order`) VALUES
('BREAK', 'Break', 0, '#17a2b8', 1),
('LUNCH', 'Lunch', 0, '#28a745', 2),
('TRAINING', 'Training', 1, '#6f42c1', 3),
('MEETING', 'Meeting', 1, '#fd7e14', 4),
('ADMIN', 'Admin Work', 1, '#20c997', 5),
('PERSONAL', 'Personal', 0, '#6c757d', 6),
('TECHNICAL', 'Technical Issue', 1, '#dc3545', 7);

-- Insert default API key for testing (remove in production)
INSERT IGNORE INTO `api_keys` (`name`, `api_key`, `permissions`, `is_active`) VALUES
('Default API Key', 'areports_api_key_change_me_in_production', '["*"]', 1);

SET FOREIGN_KEY_CHECKS = 1;
