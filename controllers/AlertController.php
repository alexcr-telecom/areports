<?php
/**
 * Alert Controller
 * Manages alert configuration and history
 */

namespace aReports\Controllers;

use aReports\Core\Controller;

class AlertController extends Controller
{
    /**
     * List alerts
     */
    public function index(): void
    {
        $this->requirePermission('alerts.view');

        $alerts = $this->db->fetchAll(
            "SELECT a.*, u.first_name, u.last_name
             FROM alerts a
             LEFT JOIN users u ON a.created_by = u.id
             ORDER BY a.is_active DESC, a.name"
        );

        $this->render('alerts/index', [
            'title' => 'Alerts',
            'currentPage' => 'alerts',
            'alerts' => $alerts
        ]);
    }

    /**
     * Alert history
     */
    public function history(): void
    {
        $this->requirePermission('alerts.view');

        $page = (int) $this->get('page', 1);
        $perPage = 50;

        $history = $this->db->fetchAll(
            "SELECT ah.*, a.name as alert_name
             FROM alert_history ah
             JOIN alerts a ON ah.alert_id = a.id
             ORDER BY ah.triggered_at DESC
             LIMIT ? OFFSET ?",
            [$perPage, ($page - 1) * $perPage]
        );

        $total = $this->db->count('alert_history');

        $this->render('alerts/history', [
            'title' => 'Alert History',
            'currentPage' => 'alerts.history',
            'history' => $history,
            'page' => $page,
            'totalPages' => ceil($total / $perPage)
        ]);
    }

    /**
     * Create alert form
     */
    public function create(): void
    {
        $this->requirePermission('alerts.manage');

        $this->render('alerts/create', [
            'title' => 'Create Alert',
            'currentPage' => 'alerts'
        ]);
    }

    /**
     * Store alert
     */
    public function store(): void
    {
        $this->requirePermission('alerts.manage');

        $data = $this->validate($_POST, [
            'name' => 'required|max:100',
            'metric' => 'required',
            'condition' => 'required|in:gt,lt,eq,gte,lte',
            'threshold' => 'required|numeric'
        ]);

        $alertId = $this->db->insert('alerts', [
            'name' => $data['name'],
            'metric' => $data['metric'],
            'condition' => $data['condition'],
            'threshold' => $data['threshold'],
            'queue_filter' => $this->post('queue_filter'),
            'notify_email' => $this->post('notify_email'),
            'is_active' => 1,
            'created_by' => $this->user['id']
        ]);

        $this->audit('create', 'alert', $alertId);
        $this->redirectWith('/areports/alerts', 'success', 'Alert created successfully.');
    }

    /**
     * Edit alert form
     */
    public function edit(int $id): void
    {
        $this->requirePermission('alerts.manage');

        $alert = $this->db->fetch("SELECT * FROM alerts WHERE id = ?", [$id]);
        if (!$alert) {
            $this->abort(404, 'Alert not found');
        }

        $this->render('alerts/edit', [
            'title' => 'Edit Alert',
            'currentPage' => 'alerts',
            'alert' => $alert
        ]);
    }

    /**
     * Update alert
     */
    public function update(int $id): void
    {
        $this->requirePermission('alerts.manage');

        $data = $this->validate($_POST, [
            'name' => 'required|max:100',
            'metric' => 'required',
            'condition' => 'required|in:gt,lt,eq,gte,lte',
            'threshold' => 'required|numeric'
        ]);

        $this->db->update('alerts', [
            'name' => $data['name'],
            'metric' => $data['metric'],
            'condition' => $data['condition'],
            'threshold' => $data['threshold'],
            'queue_filter' => $this->post('queue_filter'),
            'notify_email' => $this->post('notify_email')
        ], ['id' => $id]);

        $this->audit('update', 'alert', $id);
        $this->redirectWith('/areports/alerts', 'success', 'Alert updated successfully.');
    }

    /**
     * Delete alert
     */
    public function delete(int $id): void
    {
        $this->requirePermission('alerts.manage');

        $this->db->delete('alerts', ['id' => $id]);
        $this->audit('delete', 'alert', $id);
        $this->redirectWith('/areports/alerts', 'success', 'Alert deleted successfully.');
    }

    /**
     * Acknowledge alert
     */
    public function acknowledge(int $id): void
    {
        $this->requirePermission('alerts.view');

        $this->db->update('alert_history', [
            'acknowledged_at' => date('Y-m-d H:i:s'),
            'acknowledged_by' => $this->user['id']
        ], ['id' => $id, 'acknowledged_at' => null]);

        $this->redirectWith('/areports/alerts/history', 'success', 'Alert acknowledged.');
    }
}
