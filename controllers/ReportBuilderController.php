<?php
/**
 * Report Builder Controller
 * Custom report creation and management
 */

namespace aReports\Controllers;

use aReports\Core\Controller;

class ReportBuilderController extends Controller
{
    /**
     * Available data sources
     */
    private array $dataSources = [
        'cdr' => [
            'name' => 'Call Detail Records',
            'columns' => [
                'calldate' => ['label' => 'Call Date/Time', 'type' => 'datetime'],
                'src' => ['label' => 'Source', 'type' => 'string'],
                'dst' => ['label' => 'Destination', 'type' => 'string'],
                'duration' => ['label' => 'Duration', 'type' => 'duration'],
                'billsec' => ['label' => 'Billable Seconds', 'type' => 'duration'],
                'disposition' => ['label' => 'Disposition', 'type' => 'string'],
                'dcontext' => ['label' => 'Context', 'type' => 'string'],
                'channel' => ['label' => 'Channel', 'type' => 'string'],
                'uniqueid' => ['label' => 'Unique ID', 'type' => 'string'],
            ],
        ],
        'queuelog' => [
            'name' => 'Queue Log',
            'columns' => [
                'time' => ['label' => 'Time', 'type' => 'datetime'],
                'callid' => ['label' => 'Call ID', 'type' => 'string'],
                'queuename' => ['label' => 'Queue', 'type' => 'string'],
                'agent' => ['label' => 'Agent', 'type' => 'string'],
                'event' => ['label' => 'Event', 'type' => 'string'],
                'data1' => ['label' => 'Data 1', 'type' => 'string'],
                'data2' => ['label' => 'Data 2', 'type' => 'string'],
                'data3' => ['label' => 'Data 3', 'type' => 'string'],
            ],
        ],
    ];

    /**
     * List saved report templates
     */
    public function index(): void
    {
        $this->requirePermission('reports.view');

        $templates = $this->db->fetchAll(
            "SELECT rt.*, u.first_name, u.last_name
             FROM report_templates rt
             LEFT JOIN users u ON rt.created_by = u.id
             WHERE rt.is_public = 1 OR rt.created_by = ?
             ORDER BY rt.name",
            [$this->user['id']]
        );

        $this->render('report-builder/index', [
            'title' => 'Report Builder',
            'currentPage' => 'report_builder',
            'templates' => $templates,
            'dataSources' => $this->dataSources,
        ]);
    }

    /**
     * Create new report form
     */
    public function create(): void
    {
        $this->requirePermission('reports.manage');

        $this->render('report-builder/create', [
            'title' => 'Create Report',
            'currentPage' => 'report_builder',
            'dataSources' => $this->dataSources,
        ]);
    }

    /**
     * Preview report with current settings
     */
    public function preview(): void
    {
        $this->requirePermission('reports.view');

        $dataSource = $this->post('data_source');
        $columns = $this->post('columns', []);
        $filters = $this->post('filters', []);
        $groupBy = $this->post('group_by');
        $orderBy = $this->post('order_by');
        $limit = min((int) $this->post('limit', 100), 1000);

        if (!isset($this->dataSources[$dataSource])) {
            $this->json(['error' => 'Invalid data source']);
            return;
        }

        try {
            $data = $this->executeReport($dataSource, $columns, $filters, $groupBy, $orderBy, $limit);
            $this->json([
                'success' => true,
                'data' => $data,
                'count' => count($data),
            ]);
        } catch (\Exception $e) {
            $this->json(['error' => $e->getMessage()]);
        }
    }

    /**
     * Store report template
     */
    public function store(): void
    {
        $this->requirePermission('reports.manage');

        $data = $this->validate($_POST, [
            'name' => 'required|max:100',
            'data_source' => 'required',
        ]);

        $templateId = $this->db->insert('report_templates', [
            'name' => $data['name'],
            'description' => $this->post('description'),
            'data_source' => $data['data_source'],
            'columns' => json_encode($this->post('columns', [])),
            'filters' => json_encode($this->post('filters', [])),
            'grouping' => json_encode($this->post('grouping', [])),
            'sorting' => json_encode($this->post('sorting', [])),
            'chart_config' => json_encode($this->post('chart_config', [])),
            'is_public' => $this->post('is_public') ? 1 : 0,
            'created_by' => $this->user['id'],
        ]);

        $this->audit('create', 'report_template', $templateId);
        $this->redirectWith('/areports/report-builder/' . $templateId, 'success', 'Report template saved successfully.');
    }

    /**
     * Show report template
     */
    public function show(int $id): void
    {
        $this->requirePermission('reports.view');

        $template = $this->getTemplate($id);

        // Execute report with default filters
        $dateFrom = $this->get('date_from', date('Y-m-d'));
        $dateTo = $this->get('date_to', date('Y-m-d'));

        $filters = json_decode($template['filters'], true) ?: [];
        $filters['date_from'] = $dateFrom;
        $filters['date_to'] = $dateTo;

        $data = $this->executeReport(
            $template['data_source'],
            json_decode($template['columns'], true) ?: [],
            $filters,
            json_decode($template['grouping'], true)['field'] ?? null,
            json_decode($template['sorting'], true)['field'] ?? null,
            500
        );

        $this->render('report-builder/show', [
            'title' => $template['name'],
            'currentPage' => 'report_builder',
            'template' => $template,
            'data' => $data,
            'dataSources' => $this->dataSources,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    /**
     * Edit report template form
     */
    public function edit(int $id): void
    {
        $this->requirePermission('reports.manage');

        $template = $this->getTemplate($id);

        // Check ownership
        if ($template['created_by'] != $this->user['id'] && !$this->app->getAuth()->can('admin.full')) {
            $this->abort(403, 'You can only edit your own templates');
        }

        $this->render('report-builder/edit', [
            'title' => 'Edit Report',
            'currentPage' => 'report_builder',
            'template' => $template,
            'dataSources' => $this->dataSources,
        ]);
    }

    /**
     * Update report template
     */
    public function update(int $id): void
    {
        $this->requirePermission('reports.manage');

        $template = $this->getTemplate($id);

        if ($template['created_by'] != $this->user['id'] && !$this->app->getAuth()->can('admin.full')) {
            $this->abort(403, 'You can only edit your own templates');
        }

        $data = $this->validate($_POST, [
            'name' => 'required|max:100',
            'data_source' => 'required',
        ]);

        $this->db->update('report_templates', [
            'name' => $data['name'],
            'description' => $this->post('description'),
            'data_source' => $data['data_source'],
            'columns' => json_encode($this->post('columns', [])),
            'filters' => json_encode($this->post('filters', [])),
            'grouping' => json_encode($this->post('grouping', [])),
            'sorting' => json_encode($this->post('sorting', [])),
            'chart_config' => json_encode($this->post('chart_config', [])),
            'is_public' => $this->post('is_public') ? 1 : 0,
        ], ['id' => $id]);

        $this->audit('update', 'report_template', $id);
        $this->redirectWith('/areports/report-builder/' . $id, 'success', 'Report template updated successfully.');
    }

    /**
     * Delete report template
     */
    public function delete(int $id): void
    {
        $this->requirePermission('reports.manage');

        $template = $this->getTemplate($id);

        if ($template['created_by'] != $this->user['id'] && !$this->app->getAuth()->can('admin.full')) {
            $this->abort(403, 'You can only delete your own templates');
        }

        $this->db->delete('report_templates', ['id' => $id]);
        $this->audit('delete', 'report_template', $id);
        $this->redirectWith('/areports/report-builder', 'success', 'Report template deleted successfully.');
    }

    /**
     * Export report
     */
    public function export(int $id): void
    {
        $this->requirePermission('reports.view');

        $template = $this->getTemplate($id);
        $format = $this->get('format', 'csv');
        $dateFrom = $this->get('date_from', date('Y-m-d'));
        $dateTo = $this->get('date_to', date('Y-m-d'));

        $filters = json_decode($template['filters'], true) ?: [];
        $filters['date_from'] = $dateFrom;
        $filters['date_to'] = $dateTo;

        $columns = json_decode($template['columns'], true) ?: [];
        $data = $this->executeReport(
            $template['data_source'],
            $columns,
            $filters,
            json_decode($template['grouping'], true)['field'] ?? null,
            json_decode($template['sorting'], true)['field'] ?? null,
            10000
        );

        // Build column labels
        $columnLabels = [];
        foreach ($columns as $col) {
            $columnLabels[$col] = $this->dataSources[$template['data_source']]['columns'][$col]['label'] ?? $col;
        }

        if ($format === 'csv') {
            $excelService = new \aReports\Services\ExcelService();
            $result = $excelService->exportToCsv($data, $columnLabels);
        } elseif ($format === 'excel') {
            $excelService = new \aReports\Services\ExcelService();
            $result = $excelService->generateReport($template['name'], $data, $columnLabels, [
                'date_range' => "{$dateFrom} - {$dateTo}",
            ]);
        } else {
            $pdfService = new \aReports\Services\PDFService();
            $result = $pdfService->generateReport($template['name'], $data, $columnLabels, [
                'date_range' => "{$dateFrom} - {$dateTo}",
            ]);
        }

        if ($result['success']) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $result['filename'] . '"');
            readfile($result['filepath']);
            exit;
        }

        $this->redirectWith('/areports/report-builder/' . $id, 'error', 'Export failed.');
    }

    /**
     * Get template or 404
     */
    private function getTemplate(int $id): array
    {
        $template = $this->db->fetch(
            "SELECT * FROM report_templates WHERE id = ? AND (is_public = 1 OR created_by = ?)",
            [$id, $this->user['id']]
        );

        if (!$template) {
            $this->abort(404, 'Report template not found');
        }

        return $template;
    }

    /**
     * Execute report query
     */
    private function executeReport(string $dataSource, array $columns, array $filters, ?string $groupBy, ?string $orderBy, int $limit): array
    {
        $db = $dataSource === 'cdr' || $dataSource === 'queuelog' ? $this->cdrDb : $this->db;
        $table = $dataSource;

        if (empty($columns)) {
            $columns = array_keys($this->dataSources[$dataSource]['columns']);
        }

        // Build SELECT
        $selectCols = [];
        foreach ($columns as $col) {
            if ($groupBy && $col !== $groupBy) {
                // If grouping, aggregate non-group columns
                $type = $this->dataSources[$dataSource]['columns'][$col]['type'] ?? 'string';
                if ($type === 'duration' || $type === 'number') {
                    $selectCols[] = "SUM({$col}) as {$col}";
                } else {
                    $selectCols[] = "COUNT({$col}) as {$col}_count";
                }
            } else {
                $selectCols[] = $col;
            }
        }

        $sql = "SELECT " . implode(', ', $selectCols) . " FROM {$table} WHERE 1=1";
        $params = [];

        // Apply filters
        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            $dateCol = $dataSource === 'cdr' ? 'calldate' : 'time';
            $sql .= " AND DATE({$dateCol}) BETWEEN ? AND ?";
            $params[] = $filters['date_from'];
            $params[] = $filters['date_to'];
        }

        // Custom filters
        foreach ($filters as $key => $value) {
            if (in_array($key, ['date_from', 'date_to']) || empty($value)) {
                continue;
            }
            if (isset($this->dataSources[$dataSource]['columns'][$key])) {
                $sql .= " AND {$key} = ?";
                $params[] = $value;
            }
        }

        // Group by
        if ($groupBy && isset($this->dataSources[$dataSource]['columns'][$groupBy])) {
            $sql .= " GROUP BY {$groupBy}";
        }

        // Order by
        if ($orderBy && isset($this->dataSources[$dataSource]['columns'][$orderBy])) {
            $sql .= " ORDER BY {$orderBy} DESC";
        } else {
            $dateCol = $dataSource === 'cdr' ? 'calldate' : 'time';
            $sql .= " ORDER BY {$dateCol} DESC";
        }

        $sql .= " LIMIT {$limit}";

        return $db->fetchAll($sql, $params);
    }
}
