<?php
/**
 * Admin Queue Controller
 * Manages queue settings
 */

namespace aReports\Controllers\Admin;

use aReports\Core\Controller;
use aReports\Services\QueueService;
use aReports\Services\FreePBXService;

class QueueController extends Controller
{
    /**
     * List queues
     */
    public function index(): void
    {
        $this->requirePermission('admin.queues.view');

        $queues = $this->db->fetchAll("SELECT * FROM queue_settings ORDER BY queue_number");

        // Get FreePBX queues
        $freepbxService = new FreePBXService();
        $freepbxQueues = $freepbxService->getQueues();

        $this->render('admin/queues/index', [
            'title' => 'Queue Settings',
            'currentPage' => 'admin.queues',
            'queues' => $queues,
            'freepbxQueues' => $freepbxQueues
        ]);
    }

    /**
     * Edit queue form
     */
    public function edit(int $id): void
    {
        $this->requirePermission('admin.queues.manage');

        $queue = $this->db->fetch("SELECT * FROM queue_settings WHERE id = ?", [$id]);
        if (!$queue) {
            $this->abort(404, 'Queue not found');
        }

        $this->render('admin/queues/edit', [
            'title' => 'Edit Queue',
            'currentPage' => 'admin.queues',
            'queue' => $queue
        ]);
    }

    /**
     * Update queue
     */
    public function update(int $id): void
    {
        $this->requirePermission('admin.queues.manage');

        $data = $this->validate($_POST, [
            'display_name' => 'required|max:100',
            'sla_threshold' => 'required|numeric|min:1|max:300',
            'sla_warning' => 'required|numeric|min:1|max:100'
        ]);

        $this->db->update('queue_settings', [
            'display_name' => $data['display_name'],
            'sla_threshold_seconds' => $data['sla_threshold'],
            'warning_threshold_seconds' => (int)(($data['sla_threshold'] * $data['sla_warning']) / 100),
            'color_code' => $this->post('color', '#007bff'),
            'is_monitored' => isset($_POST['is_monitored']) ? 1 : 0
        ], ['id' => $id]);

        $this->audit('update', 'queue_settings', $id);
        $this->redirectWith('/areports/admin/queues', 'success', 'Queue updated successfully.');
    }

    /**
     * Sync queues from FreePBX
     */
    public function sync(): void
    {
        $this->requirePermission('admin.queues.manage');

        $freepbxService = new FreePBXService();
        $freepbxQueues = $freepbxService->getQueues();

        $synced = 0;
        foreach ($freepbxQueues as $queue) {
            $existing = $this->db->fetch(
                "SELECT id FROM queue_settings WHERE queue_number = ?",
                [$queue['extension']]
            );

            if (!$existing) {
                $this->db->insert('queue_settings', [
                    'queue_number' => $queue['extension'],
                    'display_name' => $queue['name'],
                    'sla_threshold_seconds' => 20,
                    'warning_threshold_seconds' => 16,
                    'is_monitored' => 1
                ]);
                $synced++;
            }
        }

        $this->audit('sync', 'queue_settings', null, null, ['synced' => $synced]);
        $this->redirectWith('/areports/admin/queues', 'success', "Synced {$synced} new queues from FreePBX.");
    }
}
