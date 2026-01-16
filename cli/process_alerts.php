#!/usr/bin/env php
<?php
/**
 * Alert Processing CLI Script
 * Checks alert conditions and sends notifications
 *
 * Run via cron: * * * * * php /var/www/html/areports/cli/process_alerts.php
 */

require_once dirname(__DIR__) . '/core/App.php';

use aReports\Core\App;
use aReports\Services\AMIService;
use aReports\Services\NotificationService;
use aReports\Services\AlertEscalationService;

class AlertProcessor
{
    private App $app;
    private \aReports\Core\Database $db;
    private NotificationService $notificationService;
    private AlertEscalationService $escalationService;

    public function __construct()
    {
        $this->app = App::getInstance();
        $this->db = $this->app->getDb();
        $this->notificationService = new NotificationService();
        $this->escalationService = new AlertEscalationService();
    }

    /**
     * Process all active alerts
     */
    public function process(): void
    {
        $this->log("Starting alert processing...");

        // Get active alerts
        $alerts = $this->db->fetchAll(
            "SELECT * FROM alerts WHERE is_active = 1"
        );

        $this->log("Found " . count($alerts) . " active alerts");

        // Get current metrics
        $metrics = $this->getCurrentMetrics();

        foreach ($alerts as $alert) {
            $this->checkAlert($alert, $metrics);
        }

        // Process escalations
        $this->log("Processing escalations...");
        $escalations = $this->escalationService->processEscalations();
        $this->log("Processed " . count($escalations) . " escalations");

        $this->log("Alert processing completed.");
    }

    /**
     * Get current metrics from AMI
     */
    private function getCurrentMetrics(): array
    {
        try {
            $ami = new AMIService();
            $queues = $ami->getQueueStatus();

            $metrics = [
                'total_calls_waiting' => 0,
                'total_agents_available' => 0,
                'total_agents_busy' => 0,
                'queues' => [],
            ];

            foreach ($queues as $queue) {
                $available = 0;
                $busy = 0;
                $paused = 0;

                foreach ($queue['members'] ?? [] as $member) {
                    if ($member['paused']) {
                        $paused++;
                    } elseif ($member['status'] == 1) {
                        $available++;
                    } elseif ($member['in_call']) {
                        $busy++;
                    }
                }

                $metrics['queues'][$queue['name']] = [
                    'calls_waiting' => $queue['calls'],
                    'agents_available' => $available,
                    'agents_busy' => $busy,
                    'agents_paused' => $paused,
                    'agents_total' => count($queue['members'] ?? []),
                    'avg_hold_time' => $queue['holdtime'],
                    'sla_perf' => $queue['servicelevelperf'],
                    'abandoned' => $queue['abandoned'],
                    'completed' => $queue['completed'],
                ];

                $metrics['total_calls_waiting'] += $queue['calls'];
                $metrics['total_agents_available'] += $available;
                $metrics['total_agents_busy'] += $busy;
            }

            return $metrics;
        } catch (\Exception $e) {
            $this->log("Error getting metrics: " . $e->getMessage(), 'ERROR');
            return [];
        }
    }

    /**
     * Check single alert against metrics
     */
    private function checkAlert(array $alert, array $metrics): void
    {
        // Check cooldown
        if ($alert['last_triggered']) {
            $cooldownEnd = strtotime($alert['last_triggered']) + ($alert['cooldown_minutes'] * 60);
            if (time() < $cooldownEnd) {
                return; // Still in cooldown
            }
        }

        // Get the metric value
        $value = $this->getMetricValue($alert, $metrics);
        if ($value === null) {
            return;
        }

        // Check condition
        $triggered = $this->checkCondition($value, $alert['operator'], $alert['threshold_value']);

        if ($triggered) {
            $this->triggerAlert($alert, $value);
        }
    }

    /**
     * Get metric value based on alert configuration
     */
    private function getMetricValue(array $alert, array $metrics): ?float
    {
        $metric = $alert['metric'];
        $queueId = $alert['queue_id'];

        // Get queue name if specific queue
        $queueName = null;
        if ($queueId) {
            $queue = $this->db->fetch("SELECT queue_number FROM queue_settings WHERE id = ?", [$queueId]);
            $queueName = $queue['queue_number'] ?? null;
        }

        switch ($metric) {
            case 'calls_waiting':
                if ($queueName && isset($metrics['queues'][$queueName])) {
                    return $metrics['queues'][$queueName]['calls_waiting'];
                }
                return $metrics['total_calls_waiting'];

            case 'agents_available':
                if ($queueName && isset($metrics['queues'][$queueName])) {
                    return $metrics['queues'][$queueName]['agents_available'];
                }
                return $metrics['total_agents_available'];

            case 'avg_wait_time':
                if ($queueName && isset($metrics['queues'][$queueName])) {
                    return $metrics['queues'][$queueName]['avg_hold_time'];
                }
                return null;

            case 'sla_percentage':
                if ($queueName && isset($metrics['queues'][$queueName])) {
                    return $metrics['queues'][$queueName]['sla_perf'] * 100;
                }
                return null;

            case 'abandoned_rate':
                if ($queueName && isset($metrics['queues'][$queueName])) {
                    $q = $metrics['queues'][$queueName];
                    $total = $q['abandoned'] + $q['completed'];
                    return $total > 0 ? ($q['abandoned'] / $total) * 100 : 0;
                }
                return null;

            default:
                return null;
        }
    }

    /**
     * Check if condition is met
     */
    private function checkCondition(float $value, string $operator, float $threshold): bool
    {
        return match ($operator) {
            'gt' => $value > $threshold,
            'gte' => $value >= $threshold,
            'lt' => $value < $threshold,
            'lte' => $value <= $threshold,
            'eq' => $value == $threshold,
            default => false,
        };
    }

    /**
     * Trigger alert and send notifications
     */
    private function triggerAlert(array $alert, float $value): void
    {
        $this->log("Alert triggered: {$alert['name']} (value: {$value}, threshold: {$alert['threshold_value']})");

        // Get queue name
        $queueName = 'All Queues';
        if ($alert['queue_id']) {
            $queue = $this->db->fetch("SELECT display_name FROM queue_settings WHERE id = ?", [$alert['queue_id']]);
            $queueName = $queue['display_name'] ?? 'Unknown';
        }

        // Prepare alert data
        $alertData = array_merge($alert, [
            'triggered_value' => $value,
            'queue_name' => $queueName,
            'time' => date('d/m/Y H:i:s'),
        ]);

        // Send notifications
        $this->notificationService->sendAlert($alertData);

        // Update alert last_triggered
        $this->db->update('alerts', [
            'last_triggered' => date('Y-m-d H:i:s'),
            'trigger_count' => $alert['trigger_count'] + 1,
        ], ['id' => $alert['id']]);

        // Log to history
        $this->db->insert('alert_history', [
            'alert_id' => $alert['id'],
            'triggered_value' => $value,
            'threshold_value' => $alert['threshold_value'],
            'message' => "Alert triggered: {$alert['name']} - Value: {$value}, Threshold: {$alert['threshold_value']}",
            'triggered_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Log message
     */
    private function log(string $message, string $type = 'INFO'): void
    {
        $timestamp = date('Y-m-d H:i:s');
        echo "[{$timestamp}] [{$type}] {$message}\n";

        $logFile = dirname(__DIR__) . '/storage/logs/alert_processor.log';
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        file_put_contents($logFile, "[{$timestamp}] [{$type}] {$message}\n", FILE_APPEND);
    }
}

// Run
$processor = new AlertProcessor();
$processor->process();
