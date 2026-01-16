<?php
/**
 * Disposition Controller
 * Call disposition analysis reports
 */

namespace aReports\Controllers;

use aReports\Core\Controller;
use aReports\Services\CDRService;

class DispositionController extends Controller
{
    private CDRService $cdrService;

    public function __construct(\aReports\Core\App $app)
    {
        parent::__construct($app);
        $this->cdrService = new CDRService();
    }

    /**
     * Disposition analysis report
     */
    public function index(): void
    {
        $this->requirePermission('reports.cdr');

        $dateFrom = $this->get('date_from', date('Y-m-d'));
        $dateTo = $this->get('date_to', date('Y-m-d'));
        $queue = $this->get('queue');

        // Get disposition summary
        $dispositions = $this->getDispositionSummary($dateFrom, $dateTo, $queue);

        // Get hourly distribution
        $hourlyData = $this->getHourlyDisposition($dateFrom, $dateTo, $queue);

        // Get agent disposition breakdown
        $agentDispositions = $this->getAgentDispositions($dateFrom, $dateTo, $queue);

        // Get queues for filter
        $queues = $this->db->fetchAll("SELECT * FROM queue_settings WHERE is_monitored = 1 ORDER BY display_name");

        $this->render('reports/disposition/index', [
            'title' => 'Disposition Analysis',
            'currentPage' => 'reports.disposition',
            'dispositions' => $dispositions,
            'hourlyData' => $hourlyData,
            'agentDispositions' => $agentDispositions,
            'queues' => $queues,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'queue' => $queue,
            ]
        ]);
    }

    /**
     * Get disposition data for DataTables
     */
    public function data(): void
    {
        $this->requirePermission('reports.cdr');

        $dateFrom = $this->get('date_from', date('Y-m-d'));
        $dateTo = $this->get('date_to', date('Y-m-d'));
        $queue = $this->get('queue');

        $data = $this->getDispositionDetails($dateFrom, $dateTo, $queue);

        $this->json([
            'data' => $data,
            'recordsTotal' => count($data),
            'recordsFiltered' => count($data),
        ]);
    }

    /**
     * Export disposition report
     */
    public function export(): void
    {
        $this->requirePermission('reports.cdr');

        $dateFrom = $this->get('date_from', date('Y-m-d'));
        $dateTo = $this->get('date_to', date('Y-m-d'));
        $queue = $this->get('queue');
        $format = $this->get('format', 'csv');

        $data = $this->getDispositionDetails($dateFrom, $dateTo, $queue);

        $columns = [
            'disposition' => 'Disposition',
            'total_calls' => 'Total Calls',
            'percentage' => 'Percentage',
            'avg_duration' => 'Avg Duration',
            'total_duration' => 'Total Duration',
        ];

        if ($format === 'csv') {
            $excelService = new \aReports\Services\ExcelService();
            $result = $excelService->exportToCsv($data, $columns, 'disposition_report_' . date('Y-m-d') . '.csv');
        } elseif ($format === 'excel') {
            $excelService = new \aReports\Services\ExcelService();
            $result = $excelService->generateReport('Disposition Analysis', $data, $columns, [
                'date_range' => "{$dateFrom} - {$dateTo}",
            ]);
        } else {
            $pdfService = new \aReports\Services\PDFService();
            $result = $pdfService->generateReport('Disposition Analysis', $data, $columns, [
                'date_range' => "{$dateFrom} - {$dateTo}",
            ]);
        }

        if ($result['success']) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $result['filename'] . '"');
            readfile($result['filepath']);
            exit;
        }

        $this->redirectWith('/areports/reports/disposition', 'error', 'Export failed: ' . ($result['message'] ?? 'Unknown error'));
    }

    /**
     * Get disposition summary
     */
    private function getDispositionSummary(string $dateFrom, string $dateTo, ?string $queue): array
    {
        $sql = "SELECT
                    disposition,
                    COUNT(*) as total_calls,
                    SUM(duration) as total_duration,
                    AVG(duration) as avg_duration,
                    SUM(billsec) as total_billsec,
                    AVG(billsec) as avg_billsec
                FROM cdr
                WHERE DATE(calldate) BETWEEN ? AND ?";

        $params = [$dateFrom, $dateTo];

        if ($queue) {
            $sql .= " AND dcontext LIKE ?";
            $params[] = "%{$queue}%";
        }

        $sql .= " GROUP BY disposition ORDER BY total_calls DESC";

        $results = $this->cdrDb->fetchAll($sql, $params);

        // Calculate percentages
        $total = array_sum(array_column($results, 'total_calls'));
        foreach ($results as &$row) {
            $row['percentage'] = $total > 0 ? round(($row['total_calls'] / $total) * 100, 2) : 0;
        }

        return $results;
    }

    /**
     * Get hourly disposition distribution
     */
    private function getHourlyDisposition(string $dateFrom, string $dateTo, ?string $queue): array
    {
        $sql = "SELECT
                    HOUR(calldate) as hour,
                    disposition,
                    COUNT(*) as count
                FROM cdr
                WHERE DATE(calldate) BETWEEN ? AND ?";

        $params = [$dateFrom, $dateTo];

        if ($queue) {
            $sql .= " AND dcontext LIKE ?";
            $params[] = "%{$queue}%";
        }

        $sql .= " GROUP BY hour, disposition ORDER BY hour";

        $results = $this->cdrDb->fetchAll($sql, $params);

        // Transform into chart-friendly format
        $hourlyData = [];
        for ($h = 0; $h < 24; $h++) {
            $hourlyData[$h] = ['hour' => $h, 'ANSWERED' => 0, 'NO ANSWER' => 0, 'BUSY' => 0, 'FAILED' => 0];
        }

        foreach ($results as $row) {
            $hour = (int) $row['hour'];
            $disp = $row['disposition'];
            if (isset($hourlyData[$hour][$disp])) {
                $hourlyData[$hour][$disp] = (int) $row['count'];
            }
        }

        return array_values($hourlyData);
    }

    /**
     * Get agent disposition breakdown
     */
    private function getAgentDispositions(string $dateFrom, string $dateTo, ?string $queue): array
    {
        $sql = "SELECT
                    CASE
                        WHEN src REGEXP '^[0-9]+$' AND LENGTH(src) <= 5 THEN src
                        ELSE dst
                    END as agent,
                    disposition,
                    COUNT(*) as count
                FROM cdr
                WHERE DATE(calldate) BETWEEN ? AND ?
                AND (
                    (src REGEXP '^[0-9]+$' AND LENGTH(src) <= 5)
                    OR (dst REGEXP '^[0-9]+$' AND LENGTH(dst) <= 5)
                )";

        $params = [$dateFrom, $dateTo];

        if ($queue) {
            $sql .= " AND dcontext LIKE ?";
            $params[] = "%{$queue}%";
        }

        $sql .= " GROUP BY agent, disposition
                  HAVING agent IS NOT NULL AND agent != ''
                  ORDER BY count DESC";

        $results = $this->cdrDb->fetchAll($sql, $params);

        // Reorganize by agent
        $agentData = [];
        foreach ($results as $row) {
            $agent = $row['agent'];
            if (!isset($agentData[$agent])) {
                $agentData[$agent] = [
                    'agent' => $agent,
                    'total' => 0,
                    'dispositions' => []
                ];
            }
            $agentData[$agent]['dispositions'][$row['disposition']] = (int) $row['count'];
            $agentData[$agent]['total'] += (int) $row['count'];
        }

        // Sort by total calls
        uasort($agentData, fn($a, $b) => $b['total'] - $a['total']);

        return array_values(array_slice($agentData, 0, 20));
    }

    /**
     * Get detailed disposition data
     */
    private function getDispositionDetails(string $dateFrom, string $dateTo, ?string $queue): array
    {
        $sql = "SELECT
                    disposition,
                    COUNT(*) as total_calls,
                    SUM(duration) as total_duration,
                    AVG(duration) as avg_duration,
                    MIN(duration) as min_duration,
                    MAX(duration) as max_duration,
                    SUM(billsec) as total_billsec,
                    AVG(billsec) as avg_billsec
                FROM cdr
                WHERE DATE(calldate) BETWEEN ? AND ?";

        $params = [$dateFrom, $dateTo];

        if ($queue) {
            $sql .= " AND dcontext LIKE ?";
            $params[] = "%{$queue}%";
        }

        $sql .= " GROUP BY disposition ORDER BY total_calls DESC";

        $results = $this->cdrDb->fetchAll($sql, $params);

        // Calculate percentages and format
        $total = array_sum(array_column($results, 'total_calls'));
        foreach ($results as &$row) {
            $row['percentage'] = $total > 0 ? round(($row['total_calls'] / $total) * 100, 2) . '%' : '0%';
            $row['avg_duration'] = $this->formatDuration((int) $row['avg_duration']);
            $row['total_duration'] = $this->formatDuration((int) $row['total_duration']);
        }

        return $results;
    }

    /**
     * Format duration
     */
    private function formatDuration(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
        }
        return sprintf('%d:%02d', $minutes, $secs);
    }
}
