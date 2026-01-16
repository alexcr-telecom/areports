<?php
/**
 * Export Controller
 * Handles report exports (CSV, PDF, Excel)
 */

namespace aReports\Controllers;

use aReports\Core\Controller;
use aReports\Services\CDRService;
use aReports\Services\QueueService;
use aReports\Services\AgentService;

class ExportController extends Controller
{
    /**
     * Export report
     */
    public function export(string $type): void
    {
        $format = $this->get('format', 'csv');
        $dateFrom = $this->get('date_from', date('Y-m-d', strtotime('-7 days')));
        $dateTo = $this->get('date_to', date('Y-m-d'));

        switch ($type) {
            case 'cdr':
                $this->requirePermission('reports.cdr.export');
                $this->exportCDR($dateFrom, $dateTo, $format);
                break;

            case 'queue':
                $this->requirePermission('reports.queue.export');
                $this->exportQueue($dateFrom, $dateTo, $format);
                break;

            case 'agent':
                $this->requirePermission('reports.agent.export');
                $this->exportAgent($dateFrom, $dateTo, $format);
                break;

            default:
                $this->abort(404, 'Unknown export type');
        }
    }

    /**
     * Export CDR data
     */
    private function exportCDR(string $dateFrom, string $dateTo, string $format): void
    {
        $cdrService = new CDRService();
        $filters = [
            'date_from' => $dateFrom,
            'date_to' => $dateTo
        ];

        if ($format === 'csv') {
            $csv = $cdrService->exportToCSV($filters);
            $this->outputCSV($csv, "cdr_export_{$dateFrom}_{$dateTo}.csv");
        } else {
            $this->abort(400, 'Unsupported format. Only CSV is currently available.');
        }
    }

    /**
     * Export Queue data
     */
    private function exportQueue(string $dateFrom, string $dateTo, string $format): void
    {
        $queueService = new QueueService();

        if ($format === 'csv') {
            $csv = $queueService->exportToCSV($dateFrom, $dateTo);
            $this->outputCSV($csv, "queue_export_{$dateFrom}_{$dateTo}.csv");
        } else {
            $this->abort(400, 'Unsupported format. Only CSV is currently available.');
        }
    }

    /**
     * Export Agent data
     */
    private function exportAgent(string $dateFrom, string $dateTo, string $format): void
    {
        $agentService = new AgentService();

        if ($format === 'csv') {
            $csv = $agentService->exportToCSV($dateFrom, $dateTo);
            $this->outputCSV($csv, "agent_export_{$dateFrom}_{$dateTo}.csv");
        } else {
            $this->abort(400, 'Unsupported format. Only CSV is currently available.');
        }
    }

    /**
     * Output CSV file
     */
    private function outputCSV(string $content, string $filename): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo $content;
        exit;
    }
}
