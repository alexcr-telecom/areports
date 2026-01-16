<?php
/**
 * CDR (Call Detail Records) Controller
 * Handles call detail reports and search
 */

namespace aReports\Controllers;

use aReports\Core\Controller;

class CDRController extends Controller
{
    /**
     * Show CDR list
     */
    public function index(): void
    {
        $this->requirePermission('reports.cdr.view');

        // Get filter parameters
        $filters = $this->getFilters();

        // Get queues for filter dropdown
        $queues = $this->db->fetchAll("SELECT * FROM queue_settings ORDER BY sort_order");

        $this->render('reports/cdr/list', [
            'title' => 'Call Detail Records',
            'currentPage' => 'reports.cdr',
            'filters' => $filters,
            'queues' => $queues
        ]);
    }

    /**
     * Get CDR data for DataTables
     */
    public function data(): void
    {
        $this->requirePermission('reports.cdr.view');

        // DataTables parameters
        $draw = (int) $this->get('draw', 1);
        $start = (int) $this->get('start', 0);
        $length = (int) $this->get('length', 25);
        $search = $this->get('search')['value'] ?? '';
        $orderCol = (int) ($this->get('order')[0]['column'] ?? 0);
        $orderDir = $this->get('order')[0]['dir'] ?? 'desc';

        // Get filters
        $filters = $this->getFilters();

        // Build query
        $where = [];
        $params = [];

        // Date range filter
        if ($filters['date_from']) {
            $where[] = "calldate >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }

        if ($filters['date_to']) {
            $where[] = "calldate <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        // Source filter
        if ($filters['src']) {
            $where[] = "src LIKE ?";
            $params[] = '%' . $filters['src'] . '%';
        }

        // Destination filter
        if ($filters['dst']) {
            $where[] = "(dst LIKE ? OR did LIKE ?)";
            $params[] = '%' . $filters['dst'] . '%';
            $params[] = '%' . $filters['dst'] . '%';
        }

        // Disposition filter
        if ($filters['disposition']) {
            $where[] = "disposition = ?";
            $params[] = $filters['disposition'];
        }

        // DID filter
        if ($filters['did']) {
            $where[] = "did LIKE ?";
            $params[] = '%' . $filters['did'] . '%';
        }

        // Context filter (for queue)
        if ($filters['context']) {
            $where[] = "dcontext LIKE ?";
            $params[] = '%' . $filters['context'] . '%';
        }

        // Search
        if ($search) {
            $where[] = "(src LIKE ? OR dst LIKE ? OR did LIKE ? OR clid LIKE ?)";
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Get total count
        $totalSql = "SELECT COUNT(*) FROM cdr";
        $totalRecords = (int) $this->cdrDb->fetchColumn($totalSql);

        // Get filtered count
        $filteredSql = "SELECT COUNT(*) FROM cdr {$whereClause}";
        $filteredRecords = (int) $this->cdrDb->fetchColumn($filteredSql, $params);

        // Column mapping for ordering
        $columns = ['calldate', 'src', 'dst', 'did', 'duration', 'billsec', 'disposition'];
        $orderColumn = $columns[$orderCol] ?? 'calldate';
        $orderDirection = $orderDir === 'asc' ? 'ASC' : 'DESC';

        // Get data
        $sql = "SELECT calldate, clid, src, dst, dcontext, channel, dstchannel,
                       duration, billsec, disposition, did, recordingfile, uniqueid, linkedid
                FROM cdr
                {$whereClause}
                ORDER BY {$orderColumn} {$orderDirection}
                LIMIT {$length} OFFSET {$start}";

        $data = $this->cdrDb->fetchAll($sql, $params);

        // Format data for DataTables
        $result = [];
        foreach ($data as $row) {
            $result[] = [
                'calldate' => date('d/m/Y H:i:s', strtotime($row['calldate'])),
                'src' => $row['src'],
                'clid' => $this->formatCallerId($row['clid']),
                'dst' => $row['dst'],
                'did' => $row['did'],
                'context' => $row['dcontext'],
                'duration' => $this->formatDuration((int) $row['duration']),
                'billsec' => $this->formatDuration((int) $row['billsec']),
                'disposition' => $row['disposition'],
                'has_recording' => !empty($row['recordingfile']),
                'uniqueid' => $row['uniqueid'],
                'linkedid' => $row['linkedid'],
                'actions' => $this->getRowActions($row)
            ];
        }

        $this->json([
            'draw' => $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $result
        ]);
    }

    /**
     * Show single CDR details
     */
    public function show(string $uniqueid): void
    {
        $this->requirePermission('reports.cdr.view');

        $cdr = $this->cdrDb->fetch(
            "SELECT * FROM cdr WHERE uniqueid = ?",
            [$uniqueid]
        );

        if (!$cdr) {
            $this->abort(404, 'Call record not found');
        }

        // Get related calls (same linkedid)
        $relatedCalls = [];
        if ($cdr['linkedid']) {
            $relatedCalls = $this->cdrDb->fetchAll(
                "SELECT * FROM cdr WHERE linkedid = ? ORDER BY calldate",
                [$cdr['linkedid']]
            );
        }

        $this->render('reports/cdr/show', [
            'title' => 'Call Details',
            'currentPage' => 'reports.cdr',
            'cdr' => $cdr,
            'relatedCalls' => $relatedCalls
        ]);
    }

    /**
     * Export CDR data
     */
    public function export(): void
    {
        $this->requirePermission('reports.cdr.export');

        $format = $this->get('format', 'csv');
        $filters = $this->getFilters();

        // Build query (same as data method)
        $where = [];
        $params = [];

        if ($filters['date_from']) {
            $where[] = "calldate >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }

        if ($filters['date_to']) {
            $where[] = "calldate <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        if ($filters['disposition']) {
            $where[] = "disposition = ?";
            $params[] = $filters['disposition'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Limit export to 10000 records
        $sql = "SELECT calldate, clid, src, dst, dcontext, duration, billsec, disposition, did
                FROM cdr {$whereClause} ORDER BY calldate DESC LIMIT 10000";

        $data = $this->cdrDb->fetchAll($sql, $params);

        if ($format === 'csv') {
            $this->exportCsv($data);
        }
    }

    /**
     * Show lost calls report
     */
    public function lostCalls(): void
    {
        $this->requirePermission('reports.cdr.view');

        $filters = $this->getFilters();

        $this->render('reports/cdr/lost-calls', [
            'title' => 'Lost Calls Analysis',
            'currentPage' => 'reports.cdr',
            'filters' => $filters
        ]);
    }

    /**
     * Show call flow for a linked ID
     */
    public function callFlow(string $linkedid): void
    {
        $this->requirePermission('reports.cdr.view');

        $calls = $this->cdrDb->fetchAll(
            "SELECT * FROM cdr WHERE linkedid = ? ORDER BY calldate",
            [$linkedid]
        );

        if (empty($calls)) {
            $this->abort(404, 'Call flow not found');
        }

        // Get CEL events for more details
        $celEvents = $this->cdrDb->fetchAll(
            "SELECT * FROM cel WHERE linkedid = ? ORDER BY eventtime",
            [$linkedid]
        );

        $this->render('reports/cdr/call-flow', [
            'title' => 'Call Flow',
            'currentPage' => 'reports.cdr',
            'calls' => $calls,
            'celEvents' => $celEvents,
            'linkedid' => $linkedid
        ]);
    }

    /**
     * Get filter parameters
     */
    private function getFilters(): array
    {
        $today = date('Y-m-d');

        return [
            'date_from' => $this->get('date_from', $today),
            'date_to' => $this->get('date_to', $today),
            'src' => $this->get('src', ''),
            'dst' => $this->get('dst', ''),
            'did' => $this->get('did', ''),
            'disposition' => $this->get('disposition', ''),
            'context' => $this->get('context', '')
        ];
    }

    /**
     * Format duration
     */
    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return '0:' . str_pad($seconds, 2, '0', STR_PAD_LEFT);
        }

        $minutes = floor($seconds / 60);
        $secs = $seconds % 60;

        if ($minutes < 60) {
            return $minutes . ':' . str_pad($secs, 2, '0', STR_PAD_LEFT);
        }

        $hours = floor($minutes / 60);
        $mins = $minutes % 60;

        return $hours . ':' . str_pad($mins, 2, '0', STR_PAD_LEFT) . ':' . str_pad($secs, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Format caller ID
     */
    private function formatCallerId(string $clid): string
    {
        // Extract name and number from "Name" <number> format
        if (preg_match('/"([^"]*)"/', $clid, $matches)) {
            return $matches[1];
        }
        return $clid;
    }

    /**
     * Get row action buttons
     */
    private function getRowActions(array $row): string
    {
        $actions = '<div class="btn-group btn-group-sm">';
        $actions .= '<a href="/areports/reports/cdr/' . htmlspecialchars($row['uniqueid']) . '" class="btn btn-outline-primary" title="Details"><i class="fas fa-eye"></i></a>';

        if (!empty($row['recordingfile']) && $this->app->getAuth()->can('reports.cdr.listen')) {
            $actions .= '<button type="button" class="btn btn-outline-success play-recording" data-uniqueid="' . htmlspecialchars($row['uniqueid']) . '" title="Play Recording"><i class="fas fa-play"></i></button>';
        }

        if ($row['linkedid']) {
            $actions .= '<a href="/areports/reports/cdr/call-flow/' . htmlspecialchars($row['linkedid']) . '" class="btn btn-outline-info" title="Call Flow"><i class="fas fa-project-diagram"></i></a>';
        }

        $actions .= '</div>';

        return $actions;
    }

    /**
     * Export to CSV
     */
    private function exportCsv(array $data): void
    {
        $filename = 'cdr_export_' . date('Y-m-d_His') . '.csv';

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');

        // Headers
        fputcsv($output, ['Date/Time', 'Caller ID', 'Source', 'Destination', 'Context', 'Duration', 'Talk Time', 'Status', 'DID']);

        // Data
        foreach ($data as $row) {
            fputcsv($output, [
                $row['calldate'],
                $row['clid'],
                $row['src'],
                $row['dst'],
                $row['dcontext'],
                $row['duration'],
                $row['billsec'],
                $row['disposition'],
                $row['did']
            ]);
        }

        fclose($output);
        exit;
    }
}
