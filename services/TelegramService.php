<?php
/**
 * Telegram Service
 * Sends notifications via Telegram Bot API
 */

namespace aReports\Services;

class TelegramService
{
    private string $botToken;
    private string $apiUrl = 'https://api.telegram.org/bot';
    private bool $enabled = false;
    private string $logFile;

    public function __construct()
    {
        $app = \aReports\Core\App::getInstance();
        $this->botToken = $app->getSetting('telegram_bot_token', '');
        $this->enabled = (bool) $app->getSetting('telegram_enabled', false);
        $this->logFile = dirname(__DIR__) . '/storage/logs/telegram.log';
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
     * Check if Telegram is enabled and configured
     */
    public function isEnabled(): bool
    {
        return $this->enabled && !empty($this->botToken);
    }

    /**
     * Send a message to a chat
     */
    public function sendMessage(string $chatId, string $message, array $options = []): array
    {
        if (!$this->isEnabled()) {
            return ['success' => false, 'message' => 'Telegram is not enabled or configured'];
        }

        $params = [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => $options['parse_mode'] ?? 'HTML',
            'disable_web_page_preview' => $options['disable_preview'] ?? true,
        ];

        if (isset($options['reply_markup'])) {
            $params['reply_markup'] = json_encode($options['reply_markup']);
        }

        return $this->request('sendMessage', $params);
    }

    /**
     * Send message to multiple chats
     */
    public function broadcast(array $chatIds, string $message, array $options = []): array
    {
        $results = [];
        foreach ($chatIds as $chatId) {
            $results[$chatId] = $this->sendMessage($chatId, $message, $options);
            // Small delay to avoid rate limiting
            usleep(50000); // 50ms
        }
        return $results;
    }

    /**
     * Send an alert notification
     */
    public function sendAlert(string $chatId, array $alert): array
    {
        $emoji = $this->getAlertEmoji($alert['severity'] ?? 'warning');

        $message = "{$emoji} <b>Alert: {$alert['name']}</b>\n\n";
        $message .= "📊 <b>Metric:</b> {$alert['metric']}\n";
        $message .= "📈 <b>Value:</b> {$alert['current_value']}\n";
        $message .= "🎯 <b>Threshold:</b> {$alert['threshold']}\n";

        if (!empty($alert['queue'])) {
            $message .= "📞 <b>Queue:</b> {$alert['queue']}\n";
        }

        $message .= "\n⏰ " . date('d/m/Y H:i:s');

        return $this->sendMessage($chatId, $message);
    }

    /**
     * Send a report notification
     */
    public function sendReport(string $chatId, string $reportName, string $summary): array
    {
        $message = "📊 <b>Scheduled Report: {$reportName}</b>\n\n";
        $message .= $summary;
        $message .= "\n\n⏰ " . date('d/m/Y H:i:s');

        return $this->sendMessage($chatId, $message);
    }

    /**
     * Send a document/file
     */
    public function sendDocument(string $chatId, string $filePath, string $caption = ''): array
    {
        if (!$this->isEnabled()) {
            return ['success' => false, 'message' => 'Telegram is not enabled or configured'];
        }

        if (!file_exists($filePath)) {
            return ['success' => false, 'message' => 'File not found'];
        }

        $params = [
            'chat_id' => $chatId,
            'caption' => $caption,
            'parse_mode' => 'HTML',
        ];

        $file = new \CURLFile($filePath);

        return $this->request('sendDocument', $params, ['document' => $file]);
    }

    /**
     * Get bot info (for testing connection)
     */
    public function getMe(): array
    {
        if (empty($this->botToken)) {
            return ['success' => false, 'message' => 'Bot token not configured'];
        }

        return $this->request('getMe', []);
    }

    /**
     * Get updates (for debugging)
     */
    public function getUpdates(int $offset = 0, int $limit = 100): array
    {
        return $this->request('getUpdates', [
            'offset' => $offset,
            'limit' => $limit,
        ]);
    }

    /**
     * Set webhook URL
     */
    public function setWebhook(string $url): array
    {
        return $this->request('setWebhook', ['url' => $url]);
    }

    /**
     * Delete webhook
     */
    public function deleteWebhook(): array
    {
        return $this->request('deleteWebhook', []);
    }

    /**
     * Make API request
     */
    private function request(string $method, array $params, array $files = []): array
    {
        $url = $this->apiUrl . $this->botToken . '/' . $method;

        $this->log("Request: {$method} - " . json_encode($params));

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
        ]);

        if (!empty($files)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, array_merge($params, $files));
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $this->log("cURL Error: {$error}", 'ERROR');
            return ['success' => false, 'message' => "cURL Error: {$error}"];
        }

        $result = json_decode($response, true);

        if ($result === null) {
            $this->log("Invalid JSON response: {$response}", 'ERROR');
            return ['success' => false, 'message' => 'Invalid response from Telegram'];
        }

        $this->log("Response: " . json_encode($result));

        if (!($result['ok'] ?? false)) {
            $errorMsg = $result['description'] ?? 'Unknown error';
            $this->log("Telegram Error: {$errorMsg}", 'ERROR');
            return ['success' => false, 'message' => $errorMsg];
        }

        return ['success' => true, 'data' => $result['result'] ?? []];
    }

    /**
     * Get emoji for alert severity
     */
    private function getAlertEmoji(string $severity): string
    {
        return match ($severity) {
            'critical' => '🚨',
            'high' => '⚠️',
            'warning' => '⚡',
            'info' => 'ℹ️',
            default => '🔔',
        };
    }

    /**
     * Format queue stats for Telegram
     */
    public function formatQueueStats(array $stats): string
    {
        $message = "📊 <b>Queue Statistics</b>\n\n";

        foreach ($stats as $queue) {
            $message .= "📞 <b>{$queue['name']}</b>\n";
            $message .= "   • Waiting: {$queue['waiting']}\n";
            $message .= "   • Agents: {$queue['agents_available']}/{$queue['agents_total']}\n";
            $message .= "   • SLA: {$queue['sla']}%\n\n";
        }

        return $message;
    }

    /**
     * Format agent stats for Telegram
     */
    public function formatAgentStats(array $stats): string
    {
        $message = "👥 <b>Agent Statistics</b>\n\n";

        foreach ($stats as $agent) {
            $status = $agent['status'] === 'available' ? '🟢' : ($agent['status'] === 'busy' ? '🔴' : '🟡');
            $message .= "{$status} <b>{$agent['name']}</b>\n";
            $message .= "   • Calls: {$agent['calls_handled']}\n";
            $message .= "   • Talk Time: {$agent['talk_time']}\n\n";
        }

        return $message;
    }

    /**
     * Create inline keyboard
     */
    public function createInlineKeyboard(array $buttons): array
    {
        $keyboard = [];
        foreach ($buttons as $row) {
            $keyboardRow = [];
            foreach ($row as $button) {
                $keyboardRow[] = [
                    'text' => $button['text'],
                    'callback_data' => $button['callback'] ?? '',
                    'url' => $button['url'] ?? null,
                ];
            }
            $keyboard[] = $keyboardRow;
        }

        return ['inline_keyboard' => $keyboard];
    }
}
