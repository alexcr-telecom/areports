<?php
/**
 * Alert Escalation Service
 * Handles alert escalation rules and notifications
 */

namespace aReports\Services;

class AlertEscalationService
{
    private \aReports\Core\Database $db;
    private NotificationService $notificationService;
    private string $logFile;

    public function __construct()
    {
        $this->db = \aReports\Core\App::getInstance()->getDb();
        $this->notificationService = new NotificationService();
        $this->logFile = dirname(__DIR__) . '/storage/logs/escalation.log';
    }

    /**
     * Log message
     */
    private function log(string $message, string $type = 'INFO'): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$type}] {$message}\n";

        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
    }

    /**
     * Process all active alerts and check for escalation
     */
    public function processEscalations(): array
    {
        $results = [];

        // Get unacknowledged alerts that have escalation enabled
        $alerts = $this->db->fetchAll(
            "SELECT ah.*, a.name as alert_name, a.escalation_enabled,
                    a.notification_channels, a.recipients
             FROM alert_history ah
             JOIN alerts a ON ah.alert_id = a.id
             WHERE ah.acknowledged_at IS NULL
             AND a.escalation_enabled = 1
             ORDER BY ah.triggered_at ASC"
        );

        foreach ($alerts as $alertHistory) {
            $result = $this->checkAndEscalate($alertHistory);
            if ($result) {
                $results[] = $result;
            }
        }

        return $results;
    }

    /**
     * Check if alert needs escalation and process it
     */
    public function checkAndEscalate(array $alertHistory): ?array
    {
        $alertId = $alertHistory['alert_id'];
        $triggeredAt = strtotime($alertHistory['triggered_at']);
        $minutesSinceTriggered = (time() - $triggeredAt) / 60;

        // Get escalation rules for this alert
        $rules = $this->db->fetchAll(
            "SELECT * FROM alert_escalation_rules
             WHERE alert_id = ? AND is_active = 1
             ORDER BY level ASC",
            [$alertId]
        );

        if (empty($rules)) {
            return null;
        }

        // Find the current escalation level
        $currentLevel = $this->getCurrentEscalationLevel($alertHistory['id']);

        foreach ($rules as $rule) {
            // Skip already processed levels
            if ($rule['level'] <= $currentLevel) {
                continue;
            }

            // Check if enough time has passed for this escalation level
            if ($minutesSinceTriggered >= $rule['delay_minutes']) {
                $this->log("Escalating alert {$alertId} to level {$rule['level']}");
                return $this->executeEscalation($alertHistory, $rule);
            }
        }

        return null;
    }

    /**
     * Execute escalation for a rule
     */
    private function executeEscalation(array $alertHistory, array $rule): array
    {
        $channels = json_decode($rule['notification_channels'], true) ?: ['email'];
        $recipients = json_decode($rule['recipients'], true) ?: [];

        $notification = [
            'type' => 'escalation',
            'alert_id' => $alertHistory['alert_id'],
            'alert_name' => $alertHistory['alert_name'],
            'level' => $rule['level'],
            'triggered_value' => $alertHistory['triggered_value'],
            'threshold_value' => $alertHistory['threshold_value'],
            'triggered_at' => $alertHistory['triggered_at'],
            'escalated_at' => date('Y-m-d H:i:s'),
        ];

        $results = [];

        // Send email escalations
        if (in_array('email', $channels) && !empty($recipients['email'])) {
            $emailService = new EmailService();
            foreach ($recipients['email'] as $email) {
                $results['email'][$email] = $emailService->send(
                    $email,
                    "[ESCALATION Level {$rule['level']}] {$alertHistory['alert_name']}",
                    $this->buildEscalationEmailBody($notification),
                    ['html' => true]
                );
            }
        }

        // Send Telegram escalations
        if (in_array('telegram', $channels) && !empty($recipients['telegram'])) {
            $telegramService = new TelegramService();
            $message = $this->buildEscalationTelegramMessage($notification);
            foreach ($recipients['telegram'] as $chatId) {
                $results['telegram'][$chatId] = $telegramService->sendMessage($chatId, $message);
            }
        }

        // Log the escalation
        $this->logEscalation($alertHistory['id'], $rule['level'], $results);

        return [
            'alert_id' => $alertHistory['alert_id'],
            'level' => $rule['level'],
            'results' => $results,
        ];
    }

    /**
     * Get current escalation level for an alert history entry
     */
    private function getCurrentEscalationLevel(int $alertHistoryId): int
    {
        $result = $this->db->fetchColumn(
            "SELECT MAX(level) FROM alert_escalation_log WHERE alert_history_id = ?",
            [$alertHistoryId]
        );

        return (int) $result;
    }

    /**
     * Log escalation execution
     */
    private function logEscalation(int $alertHistoryId, int $level, array $results): void
    {
        // Create escalation log table if not exists
        try {
            $this->db->insert('alert_escalation_log', [
                'alert_history_id' => $alertHistoryId,
                'level' => $level,
                'results' => json_encode($results),
                'escalated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            // Table might not exist, log to file instead
            $this->log("Escalation executed: history_id={$alertHistoryId}, level={$level}");
        }
    }

    /**
     * Build escalation email body
     */
    private function buildEscalationEmailBody(array $notification): string
    {
        $level = $notification['level'];
        $severity = $level >= 3 ? 'critical' : ($level >= 2 ? 'high' : 'warning');
        $severityColor = match ($severity) {
            'critical' => '#dc3545',
            'high' => '#fd7e14',
            default => '#ffc107',
        };

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: {$severityColor}; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f9f9f9; }
        .metric { margin: 10px 0; padding: 10px; background: white; border-left: 4px solid {$severityColor}; }
        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
        .escalation-badge { background: {$severityColor}; color: white; padding: 5px 15px; border-radius: 20px; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <span class="escalation-badge">ESCALATION LEVEL {$level}</span>
            <h1>⚠️ {$notification['alert_name']}</h1>
        </div>
        <div class="content">
            <p><strong>This alert has been escalated because it was not acknowledged.</strong></p>
            <div class="metric">
                <strong>Triggered Value:</strong> {$notification['triggered_value']}
            </div>
            <div class="metric">
                <strong>Threshold:</strong> {$notification['threshold_value']}
            </div>
            <div class="metric">
                <strong>Originally Triggered:</strong> {$notification['triggered_at']}
            </div>
            <div class="metric">
                <strong>Escalated At:</strong> {$notification['escalated_at']}
            </div>
            <p style="color: {$severityColor}; font-weight: bold;">
                Please acknowledge this alert immediately to prevent further escalation.
            </p>
        </div>
        <div class="footer">
            aReports Call Center Analytics - Alert Escalation System
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Build escalation Telegram message
     */
    private function buildEscalationTelegramMessage(array $notification): string
    {
        $level = $notification['level'];
        $emoji = $level >= 3 ? '🚨🚨🚨' : ($level >= 2 ? '⚠️⚠️' : '⚠️');

        $message = "{$emoji} <b>ESCALATION LEVEL {$level}</b>\n\n";
        $message .= "<b>Alert:</b> {$notification['alert_name']}\n";
        $message .= "<b>Value:</b> {$notification['triggered_value']}\n";
        $message .= "<b>Threshold:</b> {$notification['threshold_value']}\n";
        $message .= "<b>Triggered:</b> {$notification['triggered_at']}\n";
        $message .= "<b>Escalated:</b> {$notification['escalated_at']}\n\n";
        $message .= "⚡ <b>Please acknowledge immediately!</b>";

        return $message;
    }

    /**
     * Create escalation rules for an alert
     */
    public function createEscalationRules(int $alertId, array $rules): void
    {
        // Delete existing rules
        $this->db->delete('alert_escalation_rules', ['alert_id' => $alertId]);

        // Insert new rules
        foreach ($rules as $rule) {
            $this->db->insert('alert_escalation_rules', [
                'alert_id' => $alertId,
                'level' => $rule['level'],
                'delay_minutes' => $rule['delay_minutes'],
                'notification_channels' => json_encode($rule['channels'] ?? ['email']),
                'recipients' => json_encode($rule['recipients'] ?? []),
                'is_active' => 1,
            ]);
        }
    }

    /**
     * Get escalation rules for an alert
     */
    public function getEscalationRules(int $alertId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM alert_escalation_rules WHERE alert_id = ? ORDER BY level",
            [$alertId]
        );
    }

    /**
     * Disable escalation for an alert
     */
    public function disableEscalation(int $alertId): void
    {
        $this->db->update('alerts', ['escalation_enabled' => 0], ['id' => $alertId]);
    }

    /**
     * Enable escalation for an alert
     */
    public function enableEscalation(int $alertId): void
    {
        $this->db->update('alerts', ['escalation_enabled' => 1], ['id' => $alertId]);
    }
}
