<?php
/**
 * Notification Service
 * Unified notification handling for all channels
 */

namespace aReports\Services;

class NotificationService
{
    private EmailService $emailService;
    private TelegramService $telegramService;
    private \aReports\Core\Database $db;
    private string $logFile;

    public function __construct()
    {
        $this->emailService = new EmailService();
        $this->telegramService = new TelegramService();
        $this->db = \aReports\Core\App::getInstance()->getDb();
        $this->logFile = dirname(__DIR__) . '/storage/logs/notifications.log';
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
     * Send notification through all configured channels
     */
    public function send(array $notification): array
    {
        $results = [];
        $channels = $notification['channels'] ?? ['email'];

        $this->log("Sending notification: {$notification['title']} via " . implode(', ', $channels));

        foreach ($channels as $channel) {
            switch ($channel) {
                case 'email':
                    $results['email'] = $this->sendEmail($notification);
                    break;
                case 'telegram':
                    $results['telegram'] = $this->sendTelegram($notification);
                    break;
                case 'browser':
                    $results['browser'] = $this->queueBrowserNotification($notification);
                    break;
            }
        }

        return $results;
    }

    /**
     * Send alert notification
     */
    public function sendAlert(array $alert): array
    {
        $channels = json_decode($alert['notification_channels'] ?? '["email"]', true) ?: ['email'];
        $recipients = json_decode($alert['recipients'] ?? '[]', true) ?: [];

        $notification = [
            'type' => 'alert',
            'title' => $alert['name'],
            'channels' => $channels,
            'data' => [
                'name' => $alert['name'],
                'metric' => $alert['metric'],
                'current_value' => $alert['triggered_value'] ?? 0,
                'threshold' => $alert['threshold_value'],
                'queue' => $alert['queue_name'] ?? 'All Queues',
                'severity' => $this->getAlertSeverity($alert),
                'time' => date('d/m/Y H:i:s'),
            ],
            'recipients' => $recipients,
        ];

        $results = [];

        // Send email notifications
        if (in_array('email', $channels) && !empty($recipients['email'] ?? [])) {
            foreach ($recipients['email'] as $email) {
                $results['email'][$email] = $this->emailService->sendAlert($email, $notification['data']);
            }
        }

        // Send Telegram notifications
        if (in_array('telegram', $channels) && !empty($recipients['telegram'] ?? [])) {
            foreach ($recipients['telegram'] as $chatId) {
                $results['telegram'][$chatId] = $this->telegramService->sendAlert($chatId, $notification['data']);
            }
        }

        // Log alert history
        $this->logAlertHistory($alert, $results);

        return $results;
    }

    /**
     * Send scheduled report notification
     */
    public function sendScheduledReport(array $report, string $filePath): array
    {
        $recipients = json_decode($report['recipients'] ?? '[]', true) ?: [];
        $results = [];

        $summary = $this->generateReportSummary($report);

        // Send email with attachment
        foreach ($recipients as $email) {
            $results['email'][$email] = $this->emailService->sendReport(
                $email,
                $report['name'],
                $summary,
                $filePath
            );
        }

        // Send Telegram notification (without file for now)
        $telegramChatIds = $this->getTelegramChatIds($recipients);
        foreach ($telegramChatIds as $chatId) {
            $results['telegram'][$chatId] = $this->telegramService->sendReport(
                $chatId,
                $report['name'],
                strip_tags($summary)
            );
        }

        return $results;
    }

    /**
     * Send email notification
     */
    private function sendEmail(array $notification): array
    {
        $results = [];
        $recipients = $notification['recipients']['email'] ?? [];

        foreach ($recipients as $email) {
            $subject = "[aReports] {$notification['title']}";
            $body = $notification['body'] ?? $notification['title'];

            $results[$email] = $this->emailService->send($email, $subject, $body, [
                'html' => $notification['html'] ?? true
            ]);
        }

        return $results;
    }

    /**
     * Send Telegram notification
     */
    private function sendTelegram(array $notification): array
    {
        $results = [];
        $chatIds = $notification['recipients']['telegram'] ?? [];

        $message = $this->formatTelegramMessage($notification);

        foreach ($chatIds as $chatId) {
            $results[$chatId] = $this->telegramService->sendMessage($chatId, $message);
        }

        return $results;
    }

    /**
     * Queue browser notification for polling
     */
    private function queueBrowserNotification(array $notification): array
    {
        try {
            $this->db->insert('browser_notifications', [
                'user_id' => $notification['user_id'] ?? null,
                'title' => $notification['title'],
                'body' => $notification['body'] ?? '',
                'type' => $notification['type'] ?? 'info',
                'data' => json_encode($notification['data'] ?? []),
                'read' => 0,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            return ['success' => true, 'message' => 'Browser notification queued'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Format message for Telegram
     */
    private function formatTelegramMessage(array $notification): string
    {
        $emoji = match ($notification['type'] ?? 'info') {
            'alert' => '⚠️',
            'report' => '📊',
            'success' => '✅',
            'error' => '❌',
            default => 'ℹ️',
        };

        $message = "{$emoji} <b>{$notification['title']}</b>\n\n";

        if (!empty($notification['body'])) {
            $message .= strip_tags($notification['body']) . "\n\n";
        }

        $message .= "⏰ " . date('d/m/Y H:i:s');

        return $message;
    }

    /**
     * Get alert severity based on conditions
     */
    private function getAlertSeverity(array $alert): string
    {
        $percentage = 0;
        if ($alert['threshold_value'] > 0) {
            $percentage = ($alert['triggered_value'] ?? 0) / $alert['threshold_value'] * 100;
        }

        if ($percentage >= 200) {
            return 'critical';
        } elseif ($percentage >= 150) {
            return 'high';
        } elseif ($percentage >= 100) {
            return 'warning';
        }

        return 'info';
    }

    /**
     * Log alert to history
     */
    private function logAlertHistory(array $alert, array $results): void
    {
        try {
            $this->db->insert('alert_history', [
                'alert_id' => $alert['id'],
                'triggered_value' => $alert['triggered_value'] ?? 0,
                'threshold_value' => $alert['threshold_value'],
                'message' => json_encode($results),
                'triggered_at' => date('Y-m-d H:i:s'),
            ]);

            // Update alert last_triggered
            $this->db->update('alerts', [
                'last_triggered' => date('Y-m-d H:i:s'),
                'trigger_count' => $this->db->fetchColumn(
                    "SELECT trigger_count FROM alerts WHERE id = ?",
                    [$alert['id']]
                ) + 1,
            ], ['id' => $alert['id']]);
        } catch (\Exception $e) {
            $this->log("Error logging alert history: " . $e->getMessage(), 'ERROR');
        }
    }

    /**
     * Generate report summary
     */
    private function generateReportSummary(array $report): string
    {
        $summary = "<p>Your scheduled report <strong>{$report['name']}</strong> has been generated.</p>";
        $summary .= "<p><strong>Report Type:</strong> {$report['report_type']}</p>";
        $summary .= "<p><strong>Schedule:</strong> {$report['schedule_type']}</p>";
        $summary .= "<p><strong>Format:</strong> " . strtoupper($report['export_format']) . "</p>";

        return $summary;
    }

    /**
     * Get Telegram chat IDs from recipients
     */
    private function getTelegramChatIds(array $recipients): array
    {
        $chatIds = [];

        // Check if there are user emails and get their Telegram chat IDs
        foreach ($recipients as $email) {
            $user = $this->db->fetch(
                "SELECT u.id, up.telegram_chat_id
                 FROM users u
                 LEFT JOIN user_preferences up ON u.id = up.user_id
                 WHERE u.email = ? AND up.telegram_chat_id IS NOT NULL",
                [$email]
            );

            if ($user && !empty($user['telegram_chat_id'])) {
                $chatIds[] = $user['telegram_chat_id'];
            }
        }

        return $chatIds;
    }

    /**
     * Get unread browser notifications for user
     */
    public function getUnreadNotifications(int $userId): array
    {
        try {
            return $this->db->fetchAll(
                "SELECT * FROM browser_notifications
                 WHERE user_id = ? AND read = 0
                 ORDER BY created_at DESC LIMIT 50",
                [$userId]
            );
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(int $notificationId, int $userId): bool
    {
        try {
            $this->db->update('browser_notifications',
                ['read' => 1, 'read_at' => date('Y-m-d H:i:s')],
                ['id' => $notificationId, 'user_id' => $userId]
            );
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(int $userId): bool
    {
        try {
            $this->db->query(
                "UPDATE browser_notifications SET `read` = 1, read_at = NOW() WHERE user_id = ? AND `read` = 0",
                [$userId]
            );
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
