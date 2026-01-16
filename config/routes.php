<?php
/**
 * Application Routes
 */

use aReports\Core\App;

$app = App::getInstance();
$router = $app->getRouter();

// Public routes (no authentication required)
$router->get('/', 'AuthController@showLogin', 'home');
$router->get('/login', 'AuthController@showLogin', 'login');
$router->post('/login', 'AuthController@login', 'login.post');
$router->get('/logout', 'AuthController@logout', 'logout');
$router->get('/forgot-password', 'AuthController@showForgotPassword', 'forgot-password');
$router->post('/forgot-password', 'AuthController@forgotPassword', 'forgot-password.post');
$router->get('/reset-password/{token}', 'AuthController@showResetPassword', 'reset-password');
$router->post('/reset-password', 'AuthController@resetPassword', 'reset-password.post');

// Protected routes (require authentication)
$router->group(['middleware' => ['aReports\\Middleware\\AuthMiddleware']], function ($router) {

    // Dashboard
    $router->get('/dashboard', 'DashboardController@index', 'dashboard');
    $router->get('/dashboard/data', 'DashboardController@getData', 'dashboard.data');

    // Wallboard
    $router->get('/wallboard', 'WallboardController@index', 'wallboard');
    $router->get('/wallboard/data', 'WallboardController@getData', 'wallboard.data');

    // Real-time API
    $router->get('/api/realtime/queues', 'RealtimeController@queues', 'api.realtime.queues');
    $router->get('/api/realtime/agents', 'RealtimeController@agents', 'api.realtime.agents');
    $router->get('/api/realtime/calls', 'RealtimeController@calls', 'api.realtime.calls');
    $router->get('/api/realtime/stats', 'RealtimeController@stats', 'api.realtime.stats');

    // CDR Reports
    $router->get('/reports/cdr', 'CDRController@index', 'reports.cdr');
    $router->get('/reports/cdr/data', 'CDRController@data', 'reports.cdr.data');
    $router->get('/reports/cdr/{id}', 'CDRController@show', 'reports.cdr.show');
    $router->get('/reports/cdr/export', 'CDRController@export', 'reports.cdr.export');
    $router->get('/reports/cdr/lost-calls', 'CDRController@lostCalls', 'reports.cdr.lost');
    $router->get('/reports/cdr/call-flow/{linkedid}', 'CDRController@callFlow', 'reports.cdr.flow');

    // Agent Reports
    $router->get('/reports/agent', 'AgentReportController@performance', 'reports.agent');
    $router->get('/reports/agent/performance', 'AgentReportController@performance', 'reports.agent.performance');
    $router->get('/reports/agent/activity', 'AgentReportController@activity', 'reports.agent.activity');
    $router->get('/reports/agent/efficiency', 'AgentReportController@efficiency', 'reports.agent.efficiency');
    $router->get('/reports/agent/export', 'AgentReportController@export', 'reports.agent.export');
    $router->get('/reports/agent/{agent}', 'AgentReportController@detail', 'reports.agent.detail');

    // Queue Reports
    $router->get('/reports/queue', 'QueueReportController@summary', 'reports.queue');
    $router->get('/reports/queue/summary', 'QueueReportController@summary', 'reports.queue.summary');
    $router->get('/reports/queue/sla', 'QueueReportController@sla', 'reports.queue.sla');
    $router->get('/reports/queue/abandonment', 'QueueReportController@abandonment', 'reports.queue.abandonment');
    $router->get('/reports/queue/wait-times', 'QueueReportController@waitTimes', 'reports.queue.waittimes');
    $router->get('/reports/queue/export', 'QueueReportController@export', 'reports.queue.export');

    // Quality Monitoring
    $router->get('/quality/recordings', 'QualityController@recordings', 'quality.recordings');
    $router->get('/quality/recordings/{uniqueid}/play', 'QualityController@playRecording', 'quality.recording.play');
    $router->get('/quality/recordings/{uniqueid}/download', 'QualityController@downloadRecording', 'quality.recording.download');
    $router->get('/quality/evaluations', 'QualityController@evaluations', 'quality.evaluations');
    $router->get('/quality/evaluate/{uniqueid}', 'QualityController@showEvaluate', 'quality.evaluate');
    $router->post('/quality/evaluate', 'QualityController@storeEvaluation', 'quality.evaluate.store');

    // Trend Reports
    $router->get('/reports/trends', 'TrendController@index', 'reports.trends');
    $router->get('/reports/trends/hourly', 'TrendController@hourly', 'reports.trends.hourly');
    $router->get('/reports/trends/daily', 'TrendController@daily', 'reports.trends.daily');
    $router->get('/reports/trends/comparison', 'TrendController@comparison', 'reports.trends.comparison');

    // SLA Reports
    $router->get('/reports/sla', 'SLAController@index', 'reports.sla');
    $router->get('/reports/sla/compliance', 'SLAController@compliance', 'reports.sla.compliance');

    // Alerts
    $router->get('/alerts', 'AlertController@index', 'alerts');
    $router->get('/alerts/history', 'AlertController@history', 'alerts.history');
    $router->get('/alerts/create', 'AlertController@create', 'alerts.create');
    $router->post('/alerts', 'AlertController@store', 'alerts.store');
    $router->get('/alerts/{id}/edit', 'AlertController@edit', 'alerts.edit');
    $router->post('/alerts/{id}', 'AlertController@update', 'alerts.update');
    $router->post('/alerts/{id}/delete', 'AlertController@delete', 'alerts.delete');
    $router->post('/alerts/{id}/acknowledge', 'AlertController@acknowledge', 'alerts.acknowledge');

    // Scheduled Reports
    $router->get('/scheduled-reports', 'ScheduledReportController@index', 'scheduled-reports');
    $router->get('/scheduled-reports/create', 'ScheduledReportController@create', 'scheduled-reports.create');
    $router->post('/scheduled-reports', 'ScheduledReportController@store', 'scheduled-reports.store');
    $router->get('/scheduled-reports/{id}/edit', 'ScheduledReportController@edit', 'scheduled-reports.edit');
    $router->post('/scheduled-reports/{id}', 'ScheduledReportController@update', 'scheduled-reports.update');
    $router->post('/scheduled-reports/{id}/delete', 'ScheduledReportController@delete', 'scheduled-reports.delete');

    // Export
    $router->get('/export/{type}', 'ExportController@export', 'export');
    $router->post('/export/{type}', 'ExportController@export', 'export.post');

    // User Profile
    $router->get('/profile', 'ProfileController@index', 'profile');
    $router->post('/profile', 'ProfileController@update', 'profile.update');
    $router->post('/profile/password', 'ProfileController@changePassword', 'profile.password');
    $router->post('/profile/preferences', 'ProfileController@updatePreferences', 'profile.preferences');

    // Agent Panel
    $router->get('/agent', 'AgentPanelController@index', 'agent.panel');
    $router->get('/agent/status', 'AgentPanelController@status', 'agent.status');
    $router->post('/agent/queue-login', 'AgentPanelController@queueLogin', 'agent.queue.login');
    $router->post('/agent/queue-logout', 'AgentPanelController@queueLogout', 'agent.queue.logout');
    $router->post('/agent/queue-pause', 'AgentPanelController@queuePause', 'agent.queue.pause');
    $router->post('/agent/queue-unpause', 'AgentPanelController@queueUnpause', 'agent.queue.unpause');
    $router->post('/agent/login-all', 'AgentPanelController@loginAll', 'agent.login.all');
    $router->post('/agent/logout-all', 'AgentPanelController@logoutAll', 'agent.logout.all');

    // Admin routes
    $router->group(['prefix' => 'admin', 'middleware' => ['aReports\\Middleware\\AdminMiddleware']], function ($router) {

        // Users
        $router->get('/users', 'Admin\\UserController@index', 'admin.users');
        $router->get('/users/create', 'Admin\\UserController@create', 'admin.users.create');
        $router->post('/users', 'Admin\\UserController@store', 'admin.users.store');
        $router->get('/users/{id}', 'Admin\\UserController@show', 'admin.users.show');
        $router->get('/users/{id}/edit', 'Admin\\UserController@edit', 'admin.users.edit');
        $router->post('/users/{id}', 'Admin\\UserController@update', 'admin.users.update');
        $router->post('/users/{id}/delete', 'Admin\\UserController@delete', 'admin.users.delete');
        $router->post('/users/{id}/toggle', 'Admin\\UserController@toggleActive', 'admin.users.toggle');

        // Roles
        $router->get('/roles', 'Admin\\RoleController@index', 'admin.roles');
        $router->get('/roles/create', 'Admin\\RoleController@create', 'admin.roles.create');
        $router->post('/roles', 'Admin\\RoleController@store', 'admin.roles.store');
        $router->get('/roles/{id}/edit', 'Admin\\RoleController@edit', 'admin.roles.edit');
        $router->post('/roles/{id}', 'Admin\\RoleController@update', 'admin.roles.update');
        $router->post('/roles/{id}/delete', 'Admin\\RoleController@delete', 'admin.roles.delete');

        // Queue Settings
        $router->get('/queues', 'Admin\\QueueController@index', 'admin.queues');
        $router->get('/queues/{id}/edit', 'Admin\\QueueController@edit', 'admin.queues.edit');
        $router->post('/queues/{id}', 'Admin\\QueueController@update', 'admin.queues.update');
        $router->post('/queues/sync', 'Admin\\QueueController@sync', 'admin.queues.sync');

        // Agent Settings
        $router->get('/agents', 'Admin\\AgentController@index', 'admin.agents');
        $router->get('/agents/{id}/edit', 'Admin\\AgentController@edit', 'admin.agents.edit');
        $router->post('/agents/{id}', 'Admin\\AgentController@update', 'admin.agents.update');
        $router->post('/agents/sync', 'Admin\\AgentController@sync', 'admin.agents.sync');

        // Pause Causes
        $router->get('/pause-causes', 'Admin\\PauseCauseController@index', 'admin.pause_causes');
        $router->get('/pause-causes/create', 'Admin\\PauseCauseController@create', 'admin.pause_causes.create');
        $router->post('/pause-causes', 'Admin\\PauseCauseController@store', 'admin.pause_causes.store');
        $router->get('/pause-causes/{id}/edit', 'Admin\\PauseCauseController@edit', 'admin.pause_causes.edit');
        $router->post('/pause-causes/{id}', 'Admin\\PauseCauseController@update', 'admin.pause_causes.update');
        $router->post('/pause-causes/{id}/delete', 'Admin\\PauseCauseController@delete', 'admin.pause_causes.delete');
        $router->post('/pause-causes/{id}/toggle', 'Admin\\PauseCauseController@toggleActive', 'admin.pause_causes.toggle');
        $router->post('/pause-causes/reorder', 'Admin\\PauseCauseController@reorder', 'admin.pause_causes.reorder');

        // Settings
        $router->get('/settings', 'Admin\\SettingsController@index', 'admin.settings');
        $router->get('/settings/general', 'Admin\\SettingsController@general', 'admin.settings.general');
        $router->post('/settings/general', 'Admin\\SettingsController@updateGeneral', 'admin.settings.general.update');
        $router->get('/settings/email', 'Admin\\SettingsController@email', 'admin.settings.email');
        $router->post('/settings/email', 'Admin\\SettingsController@updateEmail', 'admin.settings.email.update');
        $router->post('/settings/email/test', 'Admin\\SettingsController@testEmail', 'admin.settings.email.test');
        $router->get('/settings/ami', 'Admin\\SettingsController@ami', 'admin.settings.ami');
        $router->post('/settings/ami', 'Admin\\SettingsController@updateAmi', 'admin.settings.ami.update');
        $router->post('/settings/ami/test', 'Admin\\SettingsController@testAmi', 'admin.settings.ami.test');

        // Evaluation Forms
        $router->get('/evaluation-forms', 'Admin\\EvaluationFormController@index', 'admin.forms');
        $router->get('/evaluation-forms/create', 'Admin\\EvaluationFormController@create', 'admin.forms.create');
        $router->post('/evaluation-forms', 'Admin\\EvaluationFormController@store', 'admin.forms.store');
        $router->get('/evaluation-forms/{id}/edit', 'Admin\\EvaluationFormController@edit', 'admin.forms.edit');
        $router->post('/evaluation-forms/{id}', 'Admin\\EvaluationFormController@update', 'admin.forms.update');
        $router->post('/evaluation-forms/{id}/delete', 'Admin\\EvaluationFormController@delete', 'admin.forms.delete');

        // Telegram Settings
        $router->get('/settings/telegram', 'Admin\\SettingsController@telegram', 'admin.settings.telegram');
        $router->post('/settings/telegram', 'Admin\\SettingsController@updateTelegram', 'admin.settings.telegram.update');
        $router->post('/settings/telegram/test', 'Admin\\SettingsController@testTelegram', 'admin.settings.telegram.test');

        // API Keys
        $router->get('/api-keys', 'Admin\\ApiKeyController@index', 'admin.api_keys');
        $router->get('/api-keys/create', 'Admin\\ApiKeyController@create', 'admin.api_keys.create');
        $router->post('/api-keys', 'Admin\\ApiKeyController@store', 'admin.api_keys.store');
        $router->post('/api-keys/{id}/delete', 'Admin\\ApiKeyController@delete', 'admin.api_keys.delete');
        $router->post('/api-keys/{id}/toggle', 'Admin\\ApiKeyController@toggle', 'admin.api_keys.toggle');

        // Wallboard Layouts
        $router->get('/wallboard-layouts', 'Admin\\WallboardLayoutController@index', 'admin.wallboard_layouts');
        $router->get('/wallboard-layouts/create', 'Admin\\WallboardLayoutController@create', 'admin.wallboard_layouts.create');
        $router->post('/wallboard-layouts', 'Admin\\WallboardLayoutController@store', 'admin.wallboard_layouts.store');
        $router->get('/wallboard-layouts/{id}/edit', 'Admin\\WallboardLayoutController@edit', 'admin.wallboard_layouts.edit');
        $router->post('/wallboard-layouts/{id}', 'Admin\\WallboardLayoutController@update', 'admin.wallboard_layouts.update');
        $router->post('/wallboard-layouts/{id}/delete', 'Admin\\WallboardLayoutController@delete', 'admin.wallboard_layouts.delete');

        // Audit Log
        $router->get('/audit-log', 'Admin\\AuditController@index', 'admin.audit');
    });

    // Campaigns
    $router->get('/campaigns', 'CampaignController@index', 'campaigns');
    $router->get('/campaigns/create', 'CampaignController@create', 'campaigns.create');
    $router->post('/campaigns', 'CampaignController@store', 'campaigns.store');
    $router->get('/campaigns/{id}', 'CampaignController@show', 'campaigns.show');
    $router->get('/campaigns/{id}/edit', 'CampaignController@edit', 'campaigns.edit');
    $router->post('/campaigns/{id}', 'CampaignController@update', 'campaigns.update');
    $router->post('/campaigns/{id}/delete', 'CampaignController@delete', 'campaigns.delete');
    $router->post('/campaigns/{id}/status', 'CampaignController@updateStatus', 'campaigns.status');
    $router->get('/campaigns/{id}/stats', 'CampaignController@stats', 'campaigns.stats');
    $router->get('/campaigns/{id}/leads', 'CampaignController@leads', 'campaigns.leads');
    $router->post('/campaigns/{id}/leads/import', 'CampaignController@importLeads', 'campaigns.leads.import');
    $router->get('/campaigns/{id}/dispositions', 'CampaignController@dispositions', 'campaigns.dispositions');

    // Disposition Analysis Report
    $router->get('/reports/disposition', 'DispositionController@index', 'reports.disposition');
    $router->get('/reports/disposition/data', 'DispositionController@data', 'reports.disposition.data');
    $router->get('/reports/disposition/export', 'DispositionController@export', 'reports.disposition.export');

    // Report Builder
    $router->get('/report-builder', 'ReportBuilderController@index', 'report_builder');
    $router->get('/report-builder/create', 'ReportBuilderController@create', 'report_builder.create');
    $router->post('/report-builder/preview', 'ReportBuilderController@preview', 'report_builder.preview');
    $router->post('/report-builder', 'ReportBuilderController@store', 'report_builder.store');
    $router->get('/report-builder/{id}', 'ReportBuilderController@show', 'report_builder.show');
    $router->get('/report-builder/{id}/edit', 'ReportBuilderController@edit', 'report_builder.edit');
    $router->post('/report-builder/{id}', 'ReportBuilderController@update', 'report_builder.update');
    $router->post('/report-builder/{id}/delete', 'ReportBuilderController@delete', 'report_builder.delete');
    $router->post('/report-builder/{id}/export', 'ReportBuilderController@export', 'report_builder.export');

    // Calibration Sessions
    $router->get('/quality/calibration', 'CalibrationController@index', 'calibration');
    $router->get('/quality/calibration/create', 'CalibrationController@create', 'calibration.create');
    $router->post('/quality/calibration', 'CalibrationController@store', 'calibration.store');
    $router->get('/quality/calibration/{id}', 'CalibrationController@show', 'calibration.show');
    $router->post('/quality/calibration/{id}/start', 'CalibrationController@start', 'calibration.start');
    $router->post('/quality/calibration/{id}/complete', 'CalibrationController@complete', 'calibration.complete');
    $router->get('/quality/calibration/{id}/results', 'CalibrationController@results', 'calibration.results');

    // Wallboard with layout selection
    $router->get('/wallboard/{layout}', 'WallboardController@withLayout', 'wallboard.layout');

    // Notifications API
    $router->get('/notifications', 'NotificationController@index', 'notifications');
    $router->get('/notifications/unread', 'NotificationController@unread', 'notifications.unread');
    $router->post('/notifications/{id}/read', 'NotificationController@markRead', 'notifications.read');
    $router->post('/notifications/read-all', 'NotificationController@markAllRead', 'notifications.read_all');
});
