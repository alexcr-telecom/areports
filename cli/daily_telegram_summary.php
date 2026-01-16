#!/usr/bin/env php
<?php
/**
 * Daily Telegram Summary CLI Script
 * Sends daily call center summary to Telegram
 *
 * Run via cron at configured time: 0 18 * * * php /var/www/html/areports/cli/daily_telegram_summary.php
 */

require_once dirname(__DIR__) . '/core/App.php';

use aReports\Core\App;
use aReports\Services\TelegramService;
use aReports\Services\QueueService;
use aReports\Services\AgentService;
use aReports\Services\CDRService;

class DailyTelegramSummary
{
    private App $app;
    private TelegramService $telegram;
    private QueueService $queueService;
    private AgentService $agentService;

    public function __construct()
    {
        $this->app = App::getInstance();
        $this->telegram = new TelegramService();
        $this->queueService = new QueueService();
        $this->agentService = new AgentService();
    }

    /**
     * Send daily summary
     */
    public function send(): void
    {
        // Check if daily telegram is enabled
        if (!$this->app->getSetting('telegram_notify_daily')) {
            $this->log("Daily Telegram summary is disabled");
            return;
        }

        if (!$this->telegram->isEnabled()) {
            $this->log("Telegram is not configured");
            return;
        }

        $chatId = $this->app->getSetting('telegram_default_chat');
        if (empty($chatId)) {
            $this->log("No default Telegram chat configured");
            return;
        }

        $this->log("Generating daily summary...");

        // Generate summary
        $message = $this->generateSummary();

        // Send to Telegram
        $result = $this->telegram->sendMessage($chatId, $message);

        if ($result['success']) {
            $this->log("Daily summary sent successfully");
        } else {
            $this->log("Failed to send summary: " . ($result['message'] ?? 'Unknown error'), 'ERROR');
        }
    }

    /**
     * Generate summary message
     */
    private function generateSummary(): string
    {
        $today = date('Y-m-d');

        // Get queue stats
        $queues = $this->queueService->getAllQueuesSummary($today, $today);

        // Calculate totals
        $totalCalls = 0;
        $totalAnswered = 0;
        $totalAbandoned = 0;
        $totalTalkTime = 0;

        foreach ($queues as $queue) {
            $totalCalls += $queue['total_calls'] ?? 0;
            $totalAnswered += $queue['answered'] ?? 0;
            $totalAbandoned += $queue['abandoned'] ?? 0;
            $totalTalkTime += $queue['total_talk_time'] ?? 0;
        }

        $answerRate = $totalCalls > 0 ? round(($totalAnswered / $totalCalls) * 100, 1) : 0;
        $abandonRate = $totalCalls > 0 ? round(($totalAbandoned / $totalCalls) * 100, 1) : 0;

        // Get top agents
        $agents = $this->agentService->getAllAgentsPerformance($today, $today);
        usort($agents, fn($a, $b) => ($b['calls_handled'] ?? 0) - ($a['calls_handled'] ?? 0));
        $topAgents = array_slice($agents, 0, 5);

        // Build message
        $message = "📊 <b>Daily Summary - " . date('d/m/Y') . "</b>\n\n";

        $message .= "📞 <b>Call Statistics</b>\n";
        $message .= "├ Total Calls: {$totalCalls}\n";
        $message .= "├ Answered: {$totalAnswered} ({$answerRate}%)\n";
        $message .= "├ Abandoned: {$totalAbandoned} ({$abandonRate}%)\n";
        $message .= "└ Talk Time: " . $this->formatDuration($totalTalkTime) . "\n\n";

        if (!empty($queues)) {
            $message .= "📋 <b>Queue Performance</b>\n";
            foreach ($queues as $queue) {
                $qName = $queue['queue_name'] ?? 'Unknown';
                $qCalls = $queue['total_calls'] ?? 0;
                $qSla = $queue['sla_percentage'] ?? 0;
                $slaEmoji = $qSla >= 80 ? '🟢' : ($qSla >= 60 ? '🟡' : '🔴');
                $message .= "├ {$qName}: {$qCalls} calls | {$slaEmoji} SLA: {$qSla}%\n";
            }
            $message .= "\n";
        }

        if (!empty($topAgents)) {
            $message .= "🏆 <b>Top Agents</b>\n";
            $rank = 1;
            foreach ($topAgents as $agent) {
                $name = $agent['agent_name'] ?? 'Unknown';
                $calls = $agent['calls_handled'] ?? 0;
                $emoji = $rank <= 3 ? ['🥇', '🥈', '🥉'][$rank - 1] : '▪️';
                $message .= "{$emoji} {$name}: {$calls} calls\n";
                $rank++;
            }
            $message .= "\n";
        }

        $message .= "⏰ Generated at " . date('H:i:s');

        return $message;
    }

    /**
     * Format duration
     */
    private function formatDuration(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        if ($hours > 0) {
            return "{$hours}h {$minutes}m";
        }
        return "{$minutes}m";
    }

    /**
     * Log message
     */
    private function log(string $message, string $type = 'INFO'): void
    {
        $timestamp = date('Y-m-d H:i:s');
        echo "[{$timestamp}] [{$type}] {$message}\n";
    }
}

// Run
$summary = new DailyTelegramSummary();
$summary->send();
