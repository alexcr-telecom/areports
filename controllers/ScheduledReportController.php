<?php
/**
 * Scheduled Report Controller
 * Manages automated report scheduling
 */

namespace aReports\Controllers;

use aReports\Core\Controller;

class ScheduledReportController extends Controller
{
    /**
     * List scheduled reports
     */
    public function index(): void
    {
        $this->requirePermission('scheduled-reports.view');

        $reports = $this->db->fetchAll(
            "SELECT sr.*, u.first_name, u.last_name
             FROM scheduled_reports sr
             LEFT JOIN users u ON sr.user_id = u.id
             ORDER BY sr.is_active DESC, sr.name"
        );

        $this->render('scheduled-reports/index', [
            'title' => 'Scheduled Reports',
            'currentPage' => 'scheduled-reports',
            'reports' => $reports
        ]);
    }

    /**
     * Create form
     */
    public function create(): void
    {
        $this->requirePermission('scheduled-reports.manage');

        $this->render('scheduled-reports/create', [
            'title' => 'Create Scheduled Report',
            'currentPage' => 'scheduled-reports'
        ]);
    }

    /**
     * Store scheduled report
     */
    public function store(): void
    {
        $this->requirePermission('scheduled-reports.manage');

        $data = $this->validate($_POST, [
            'name' => 'required|max:100',
            'report_type' => 'required|in:cdr,queue,agent,sla',
            'schedule_type' => 'required|in:daily,weekly,monthly',
            'recipients' => 'required'
        ]);

        $reportId = $this->db->insert('scheduled_reports', [
            'name' => $data['name'],
            'report_type' => $data['report_type'],
            'schedule_type' => $data['schedule_type'],
            'schedule_time' => $this->post('schedule_time', '08:00:00'),
            'recipients' => json_encode([$data['recipients']]),
            'parameters' => json_encode($_POST['parameters'] ?? []),
            'is_active' => 1,
            'user_id' => $this->user['id']
        ]);

        $this->audit('create', 'scheduled_report', $reportId);
        $this->redirectWith('/areports/scheduled-reports', 'success', 'Scheduled report created successfully.');
    }

    /**
     * Edit form
     */
    public function edit(int $id): void
    {
        $this->requirePermission('scheduled-reports.manage');

        $report = $this->db->fetch("SELECT * FROM scheduled_reports WHERE id = ?", [$id]);
        if (!$report) {
            $this->abort(404, 'Scheduled report not found');
        }

        $this->render('scheduled-reports/edit', [
            'title' => 'Edit Scheduled Report',
            'currentPage' => 'scheduled-reports',
            'report' => $report
        ]);
    }

    /**
     * Update scheduled report
     */
    public function update(int $id): void
    {
        $this->requirePermission('scheduled-reports.manage');

        $data = $this->validate($_POST, [
            'name' => 'required|max:100',
            'report_type' => 'required|in:cdr,queue,agent,sla',
            'schedule_type' => 'required|in:daily,weekly,monthly',
            'recipients' => 'required'
        ]);

        $this->db->update('scheduled_reports', [
            'name' => $data['name'],
            'report_type' => $data['report_type'],
            'schedule_type' => $data['schedule_type'],
            'schedule_time' => $this->post('schedule_time', '08:00:00'),
            'recipients' => json_encode([$data['recipients']]),
            'parameters' => json_encode($_POST['parameters'] ?? []),
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ], ['id' => $id]);

        $this->audit('update', 'scheduled_report', $id);
        $this->redirectWith('/areports/scheduled-reports', 'success', 'Scheduled report updated successfully.');
    }

    /**
     * Delete scheduled report
     */
    public function delete(int $id): void
    {
        $this->requirePermission('scheduled-reports.manage');

        $this->db->delete('scheduled_reports', ['id' => $id]);
        $this->audit('delete', 'scheduled_report', $id);
        $this->redirectWith('/areports/scheduled-reports', 'success', 'Scheduled report deleted successfully.');
    }
}
