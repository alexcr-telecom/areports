-- ============================================
-- aReports Seed Data
-- ============================================

-- Default Roles
INSERT INTO `roles` (`id`, `name`, `display_name`, `description`, `is_system`) VALUES
(1, 'admin', 'Administrator', 'Full system access - can manage all settings, users, and view all reports', 1),
(2, 'supervisor', 'Supervisor', 'Queue and agent management, access to all reports and quality monitoring', 1),
(3, 'agent', 'Agent', 'View own statistics and limited queue reports', 1);

-- Permissions
INSERT INTO `permissions` (`name`, `display_name`, `category`) VALUES
-- Dashboard
('dashboard.view', 'View Dashboard', 'Dashboard'),
('dashboard.customize', 'Customize Dashboard', 'Dashboard'),
('wallboard.view', 'View Wallboard', 'Dashboard'),
('wallboard.customize', 'Customize Wallboard', 'Dashboard'),

-- Real-time Monitoring
('realtime.view', 'View Real-time Data', 'Monitoring'),
('realtime.queue_status', 'View Queue Status', 'Monitoring'),
('realtime.agent_status', 'View Agent Status', 'Monitoring'),
('realtime.active_calls', 'View Active Calls', 'Monitoring'),

-- Agent Reports
('reports.agent.view', 'View Agent Reports', 'Reports'),
('reports.agent.view_all', 'View All Agents', 'Reports'),
('reports.agent.view_own', 'View Own Stats Only', 'Reports'),

-- Queue Reports
('reports.queue.view', 'View Queue Reports', 'Reports'),
('reports.queue.sla', 'View SLA Reports', 'Reports'),

-- CDR Reports
('reports.cdr.view', 'View Call Detail Reports', 'Reports'),
('reports.cdr.export', 'Export CDR Data', 'Reports'),
('reports.cdr.listen', 'Listen to Recordings', 'Reports'),

-- Quality Monitoring
('quality.view', 'View Quality Reports', 'Quality'),
('quality.evaluate', 'Evaluate Calls', 'Quality'),
('quality.manage_forms', 'Manage Evaluation Forms', 'Quality'),

-- Report Builder
('reports.builder', 'Use Report Builder', 'Reports'),
('reports.schedule', 'Schedule Reports', 'Reports'),
('reports.export', 'Export Reports', 'Reports'),

-- Alerts
('alerts.view', 'View Alerts', 'Alerts'),
('alerts.manage', 'Manage Alerts', 'Alerts'),
('alerts.acknowledge', 'Acknowledge Alerts', 'Alerts'),

-- Administration
('admin.users.view', 'View Users', 'Administration'),
('admin.users.manage', 'Manage Users', 'Administration'),
('admin.roles.view', 'View Roles', 'Administration'),
('admin.roles.manage', 'Manage Roles', 'Administration'),
('admin.settings', 'Manage Settings', 'Administration'),
('admin.queues', 'Manage Queue Settings', 'Administration'),
('admin.agents', 'Manage Agent Settings', 'Administration'),
('admin.audit', 'View Audit Log', 'Administration');

-- Admin role gets all permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, `id` FROM `permissions`;

-- Supervisor permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 2, `id` FROM `permissions`
WHERE `name` IN (
    'dashboard.view', 'dashboard.customize', 'wallboard.view', 'wallboard.customize',
    'realtime.view', 'realtime.queue_status', 'realtime.agent_status', 'realtime.active_calls',
    'reports.agent.view', 'reports.agent.view_all',
    'reports.queue.view', 'reports.queue.sla',
    'reports.cdr.view', 'reports.cdr.export', 'reports.cdr.listen',
    'quality.view', 'quality.evaluate',
    'reports.builder', 'reports.schedule', 'reports.export',
    'alerts.view', 'alerts.manage', 'alerts.acknowledge'
);

-- Agent permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 3, `id` FROM `permissions`
WHERE `name` IN (
    'dashboard.view',
    'realtime.view', 'realtime.queue_status',
    'reports.agent.view', 'reports.agent.view_own'
);

-- Default Settings
INSERT INTO `settings` (`category`, `setting_key`, `setting_value`, `value_type`, `description`) VALUES
('general', 'app_name', 'aReports', 'string', 'Application name'),
('general', 'app_version', '1.0.0', 'string', 'Application version'),
('general', 'timezone', 'Asia/Jerusalem', 'string', 'Default timezone'),
('general', 'date_format', 'd/m/Y', 'string', 'Date format'),
('general', 'time_format', 'H:i:s', 'string', 'Time format'),
('general', 'datetime_format', 'd/m/Y H:i:s', 'string', 'DateTime format'),
('general', 'items_per_page', '25', 'int', 'Default pagination'),
('general', 'session_lifetime', '7200', 'int', 'Session lifetime in seconds'),

('ami', 'host', '127.0.0.1', 'string', 'AMI host'),
('ami', 'port', '5038', 'int', 'AMI port'),
('ami', 'username', '', 'string', 'AMI username'),
('ami', 'secret', '', 'string', 'AMI secret'),
('ami', 'connect_timeout', '5', 'int', 'Connection timeout in seconds'),
('ami', 'read_timeout', '10', 'int', 'Read timeout in seconds'),

('email', 'enabled', '0', 'bool', 'Enable email notifications'),
('email', 'smtp_host', '', 'string', 'SMTP server'),
('email', 'smtp_port', '587', 'int', 'SMTP port'),
('email', 'smtp_user', '', 'string', 'SMTP username'),
('email', 'smtp_pass', '', 'string', 'SMTP password'),
('email', 'smtp_encryption', 'tls', 'string', 'SMTP encryption (tls/ssl/none)'),
('email', 'from_address', 'noreply@example.com', 'string', 'From email address'),
('email', 'from_name', 'aReports', 'string', 'From name'),

('sla', 'default_threshold', '60', 'int', 'Default SLA threshold in seconds'),
('sla', 'warning_percentage', '80', 'int', 'Warning at % of threshold'),
('sla', 'critical_percentage', '100', 'int', 'Critical at % of threshold'),

('recordings', 'enabled', '1', 'bool', 'Enable recording playback'),
('recordings', 'path', '/var/spool/asterisk/monitor', 'string', 'Recordings directory'),
('recordings', 'format', 'wav', 'string', 'Recording format'),
('recordings', 'web_path', '/areports/recordings', 'string', 'Web path for recordings'),

('realtime', 'refresh_interval', '5000', 'int', 'Dashboard refresh interval in ms'),
('realtime', 'websocket_enabled', '1', 'bool', 'Enable WebSocket'),
('realtime', 'websocket_port', '8080', 'int', 'WebSocket server port'),

('security', 'max_login_attempts', '5', 'int', 'Max failed login attempts'),
('security', 'lockout_duration', '900', 'int', 'Account lockout duration in seconds'),
('security', 'password_min_length', '8', 'int', 'Minimum password length'),
('security', 'session_regenerate', '1', 'bool', 'Regenerate session ID on login');

-- Default admin user (password: admin123 - CHANGE IMMEDIATELY)
-- Password hash for 'admin123'
INSERT INTO `users` (`username`, `email`, `password_hash`, `first_name`, `last_name`, `role_id`, `is_active`) VALUES
('admin', 'admin@localhost', '$2y$10$.qQrztUzN2l1hoGGByiqmOCh0ycVcd1nRMFx7RwOcaAt.94QBufdy', 'System', 'Administrator', 1, 1);

INSERT INTO `user_preferences` (`user_id`, `timezone`, `theme`) VALUES (1, 'Asia/Jerusalem', 'light');

-- Sync existing queues from Asterisk (based on exploration)
INSERT INTO `queue_settings` (`queue_number`, `display_name`, `sla_threshold_seconds`, `color_code`) VALUES
('8601', 'ILOR', 60, '#3498db'),
('8602', 'Eliraz Atias', 60, '#2ecc71'),
('8603', 'TLV', 60, '#9b59b6'),
('8604', 'Queue 8604', 60, '#e74c3c'),
('8701', 'Queue 8701', 60, '#f39c12');

-- Default dashboard layout for admin
INSERT INTO `dashboard_layouts` (`user_id`, `layout_type`, `name`, `widgets`, `is_default`) VALUES
(1, 'dashboard', 'Default Dashboard', '[
    {"id": "stats-summary", "type": "stats", "title": "Today Summary", "col": 0, "row": 0, "width": 12, "height": 1},
    {"id": "call-volume", "type": "chart", "title": "Call Volume", "col": 0, "row": 1, "width": 6, "height": 2},
    {"id": "queue-status", "type": "table", "title": "Queue Status", "col": 6, "row": 1, "width": 6, "height": 2},
    {"id": "agent-status", "type": "table", "title": "Agent Status", "col": 0, "row": 3, "width": 6, "height": 2},
    {"id": "recent-calls", "type": "table", "title": "Recent Calls", "col": 6, "row": 3, "width": 6, "height": 2}
]', 1);

-- Default evaluation form
INSERT INTO `evaluation_forms` (`id`, `name`, `description`, `is_active`, `created_by`) VALUES
(1, 'Standard Call Evaluation', 'Default evaluation form for quality monitoring', 1, 1);

INSERT INTO `evaluation_criteria` (`form_id`, `category`, `name`, `description`, `max_score`, `weight`, `sort_order`) VALUES
(1, 'Greeting', 'Professional Greeting', 'Agent greeted caller professionally and identified themselves', 10, 1.00, 1),
(1, 'Greeting', 'Verified Caller', 'Agent properly verified caller identity', 10, 1.00, 2),
(1, 'Communication', 'Clear Communication', 'Agent communicated clearly and professionally', 10, 1.00, 3),
(1, 'Communication', 'Active Listening', 'Agent demonstrated active listening skills', 10, 1.00, 4),
(1, 'Problem Solving', 'Issue Resolution', 'Agent effectively resolved the caller issue', 20, 1.50, 5),
(1, 'Problem Solving', 'Knowledge', 'Agent demonstrated product/service knowledge', 15, 1.25, 6),
(1, 'Closing', 'Proper Closing', 'Agent properly closed the call', 10, 1.00, 7),
(1, 'Closing', 'Follow-up', 'Agent offered follow-up assistance if needed', 5, 0.75, 8),
(1, 'Compliance', 'Script Adherence', 'Agent followed required scripts and procedures', 10, 1.00, 9);
