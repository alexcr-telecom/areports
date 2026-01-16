#!/usr/bin/env php
<?php
/**
 * Scheduled Reports CLI Script
 * Generates and sends scheduled reports
 *
 * Run via cron: * * * * * php /var/www/html/areports/cli/process_scheduled_reports.php
 */

require_once dirname(__DIR__) . '/core/App.php';

use aReports\Core\App;
use aReports\Services\PDFService;
use aReports\Services\ExcelService;
use aReports\Services\NotificationService;
use aReports\Services\QueueService;
use aReports\Services\AgentService;
use aReports\Services\CDRService;

class ScheduledReportProcessor
{
    private App $app;
    private \aReports\Core\Database $db;
    private NotificationService $notificationService;

    public function __construct()
    {
        $this->app = App::getInstance();
        $this->db = $this->app->getDb();
        $this->notificationService = new NotificationService();
    }

    /**
     * Process due scheduled reports
     */
    public function process(): void
    {
        $this->log("Starting scheduled report processing...");

        // Get reports that are due
        $reports = $this->db->fetchAll(
            "SELECT * FROM scheduled_reports
             WHERE is_active = 1
             AND (next_run IS NULL OR next_run <= NOW())
             ORDER BY next_run ASC"
        );

        $this->log("Found " . count($reports) . " reports to process");

        foreach ($reports as $report) {
            $this->processReport($report);
        }

        $this->log("Scheduled report processing completed.");
    }

    /**
     * Process single report
     */
    private function processReport(array $report): void
    {
        $this->log("Processing report: {$report['name']}");

        try {
            // Generate report data
            $data = $this->generateReportData($report);

            if (empty($data)) {
                $this->log("No data for report: {$report['name']}", 'WARN');
                $this->updateNextRun($report);
                return;
            }

            // Generate file
            $filePath = $this->generateReportFile($report, $data);

            if (!$filePath) {
                throw new \Exception("Failed to generate report file");
            }

            // Send notifications
            $this->sendReport($report, $filePath);

            // Log success
            $this->logExecution($report['id'], 'success', count($data), $filePath);

            $this->log("Report sent successfully: {$report['name']}");

        } catch (\Exception $e) {
            $this->log("Error processing report {$report['name']}: " . $e->getMessage(), 'ERROR');
            $this->logExecution($report['id'], 'failed', 0, null, $e->getMessage());
        }

        // Update next run time
        $this->updateNextRun($report);
    }

    /**
     * Generate report data based on type
     */
    private function generateReportData(array $report): array
    {
        $params = json_decode($report['parameters'], true) ?: [];

        // Calculate date range based on schedule type
        $dateRange = $this->calculateDateRange($report['schedule_type']);

        switch ($report['report_type']) {
            case 'agent':
                $service = new AgentService();
                return $service->getAllAgentsPerformance($dateRange['from'], $dateRange['to']);

            case 'queue':
                $service = new QueueService();
                return $service->getAllQueuesSummary($dateRange['from'], $dateRange['to']);

            case 'cdr':
                $service = new CDRService();
                $filters = array_merge($params, [
                    'date_from' => $dateRange['from'],
                    'date_to' => $dateRange['to'],
                ]);
                return $service->getCalls($filters, 1, 10000);

            default:
                return [];
        }
    }

    /**
     * Calculate date range based on schedule type
     */
    private function calculateDateRange(string $scheduleType): array
    {
        switch ($scheduleType) {
            case 'daily':
                return [
                    'from' => date('Y-m-d', strtotime('-1 day')),
                    'to' => date('Y-m-d', strtotime('-1 day')),
                ];

            case 'weekly':
                return [
                    'from' => date('Y-m-d', strtotime('-7 days')),
                    'to' => date('Y-m-d', strtotime('-1 day')),
                ];

            case 'monthly':
                return [
                    'from' => date('Y-m-01', strtotime('-1 month')),
                    'to' => date('Y-m-t', strtotime('-1 month')),
                ];

            default:
                return [
                    'from' => date('Y-m-d'),
                    'to' => date('Y-m-d'),
                ];
        }
    }

    /**
     * Generate report file
     */
    private function generateReportFile(array $report, array $data): ?string
    {
        $format = $report['export_format'] ?? 'pdf';
        $columns = $this->getColumnsForType($report['report_type']);
        $options = [
            'filename' => 'report_' . $report['id'] . '_' . date('Y-m-d_His') . '.' . $format,
            'date_range' => $this->calculateDateRange($report['schedule_type']),
        ];

        switch ($format) {
            case 'pdf':
                $service = new PDFService();
                $result = $service->generateReport($report['name'], $data, $columns, $options);
                break;

            case 'excel':
                $service = new ExcelService();
                $result = $service->generateReport($report['name'], $data, $columns, $options);
                break;

            case 'csv':
                $service = new ExcelService();
                $result = $service->exportToCsv($data, $columns, $options['filename']);
                break;

            default:
                return null;
        }

        return $result['success'] ? $result['filepath'] : null;
    }

    /**
     * Get columns for report type
     */
    private function getColumnsForType(string $type): array
    {
        switch ($type) {
            case 'agent':
                return [
                    'agent_name' => 'Agent',
                    'calls_handled' => 'Calls Handled',
                    'calls_missed' => 'Missed',
                    'total_talk_time' => 'Talk Time',
                    'answer_rate' => 'Answer Rate',
                ];

            case 'queue':
                return [
                    'queue_name' => 'Queue',
                    'total_calls' => 'Total Calls',
                    'answered' => 'Answered',
                    'abandoned' => 'Abandoned',
                    'sla_percentage' => 'SLA %',
                ];

            case 'cdr':
                return [
                    'calldate' => 'Date/Time',
                    'src' => 'Source',
                    'dst' => 'Destination',
                    'duration' => 'Duration',
                    'disposition' => 'Disposition',
                ];

            default:
                return [];
        }
    }

    /**
     * Send report to recipients
     */
    private function sendReport(array $report, string $filePath): void
    {
        $this->notificationService->sendScheduledReport($report, $filePath);
    }

    /**
     * Update next run time
     */
    private function updateNextRun(array $report): void
    {
        $nextRun = $this->calculateNextRun($report);

        $this->db->update('scheduled_reports', [
            'last_run' => date('Y-m-d H:i:s'),
            'next_run' => $nextRun,
        ], ['id' => $report['id']]);
    }

    /**
     * Calculate next run time
     */
    private function calculateNextRun(array $report): string
    {
        $time = $report['schedule_time'];

        switch ($report['schedule_type']) {
            case 'daily':
                return date('Y-m-d', strtotime('+1 day')) . ' ' . $time;

            case 'weekly':
                $dayOfWeek = $report['schedule_day'] ?? 1; // Monday by default
                $next = new \DateTime('next ' . $this->getDayName($dayOfWeek));
                return $next->format('Y-m-d') . ' ' . $time;

            case 'monthly':
                $dayOfMonth = $report['schedule_day'] ?? 1;
                $next = new \DateTime('first day of next month');
                $next->setDate($next->format('Y'), $next->format('m'), min($dayOfMonth, $next->format('t')));
                return $next->format('Y-m-d') . ' ' . $time;

            default:
                return date('Y-m-d H:i:s', strtotime('+1 day'));
        }
    }

    /**
     * Get day name from number
     */
    private function getDayName(int $day): string
    {
        $days = ['', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        return $days[$day] ?? 'Monday';
    }

    /**
     * Log execution
     */
    private function logExecution(int $reportId, string $status, int $count, ?string $filePath, ?string $error = null): void
    {
        $this->db->insert('scheduled_report_logs', [
            'scheduled_report_id' => $reportId,
            'status' => $status,
            'recipients_sent' => $count,
            'file_path' => $filePath,
            'error_message' => $error,
            'executed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Log message
     */
    private function log(string $message, string $type = 'INFO'): void
    {
        $timestamp = date('Y-m-d H:i:s');
        echo "[{$timestamp}] [{$type}] {$message}\n";

        $logFile = dirname(__DIR__) . '/storage/logs/scheduled_reports.log';
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        file_put_contents($logFile, "[{$timestamp}] [{$type}] {$message}\n", FILE_APPEND);
    }
}

// Run
$processor = new ScheduledReportProcessor();
$processor->process();
