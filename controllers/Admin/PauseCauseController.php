<?php
/**
 * Admin Pause Cause Controller
 * Manages pause reasons for queue agents
 */

namespace aReports\Controllers\Admin;

use aReports\Core\Controller;

class PauseCauseController extends Controller
{
    /**
     * List all pause causes
     */
    public function index(): void
    {
        $this->requirePermission('admin.pause_causes.view');

        $causes = $this->db->fetchAll(
            "SELECT * FROM pause_causes ORDER BY sort_order, name"
        );

        $this->render('admin/pause-causes/index', [
            'title' => 'Pause Causes',
            'currentPage' => 'admin.pause_causes',
            'causes' => $causes
        ]);
    }

    /**
     * Show create form
     */
    public function create(): void
    {
        $this->requirePermission('admin.pause_causes.manage');

        $this->render('admin/pause-causes/create', [
            'title' => 'Create Pause Cause',
            'currentPage' => 'admin.pause_causes'
        ]);
    }

    /**
     * Store new pause cause
     */
    public function store(): void
    {
        $this->requirePermission('admin.pause_causes.manage');

        $data = $this->validate($_POST, [
            'code' => 'required|max:20|unique:pause_causes,code',
            'name' => 'required|max:100'
        ]);

        $maxSort = $this->db->fetch(
            "SELECT MAX(sort_order) as max_sort FROM pause_causes"
        );

        $this->db->insert('pause_causes', [
            'code' => strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $data['code'])),
            'name' => $data['name'],
            'description' => $this->post('description'),
            'sort_order' => ($maxSort['max_sort'] ?? 0) + 1,
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ]);

        $this->audit('create', 'pause_cause', $this->db->lastInsertId());
        $this->redirectWith('/areports/admin/pause-causes', 'success', 'Pause cause created successfully.');
    }

    /**
     * Show edit form
     */
    public function edit(int $id): void
    {
        $this->requirePermission('admin.pause_causes.manage');

        $cause = $this->getCause($id);

        $this->render('admin/pause-causes/edit', [
            'title' => 'Edit Pause Cause',
            'currentPage' => 'admin.pause_causes',
            'cause' => $cause
        ]);
    }

    /**
     * Update pause cause
     */
    public function update(int $id): void
    {
        $this->requirePermission('admin.pause_causes.manage');

        $cause = $this->getCause($id);

        $data = $this->validate($_POST, [
            'code' => 'required|max:20|unique:pause_causes,code,' . $id,
            'name' => 'required|max:100'
        ]);

        $this->db->update('pause_causes', [
            'code' => strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $data['code'])),
            'name' => $data['name'],
            'description' => $this->post('description'),
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ], ['id' => $id]);

        $this->audit('update', 'pause_cause', $id);
        $this->redirectWith('/areports/admin/pause-causes', 'success', 'Pause cause updated successfully.');
    }

    /**
     * Delete pause cause
     */
    public function delete(int $id): void
    {
        $this->requirePermission('admin.pause_causes.manage');

        $cause = $this->getCause($id);

        $this->db->delete('pause_causes', ['id' => $id]);

        $this->audit('delete', 'pause_cause', $id, ['code' => $cause['code'], 'name' => $cause['name']]);
        $this->redirectWith('/areports/admin/pause-causes', 'success', 'Pause cause deleted successfully.');
    }

    /**
     * Reorder pause causes (AJAX)
     */
    public function reorder(): void
    {
        $this->requirePermission('admin.pause_causes.manage');

        $order = $this->post('order');
        if (!is_array($order)) {
            $order = json_decode($order, true);
        }

        if (!$order || !is_array($order)) {
            $this->json(['error' => 'Invalid order data'], 400);
            return;
        }

        foreach ($order as $position => $id) {
            $this->db->update('pause_causes', [
                'sort_order' => $position + 1
            ], ['id' => (int)$id]);
        }

        $this->json(['success' => true]);
    }

    /**
     * Toggle active status (AJAX)
     */
    public function toggleActive(int $id): void
    {
        $this->requirePermission('admin.pause_causes.manage');

        $cause = $this->getCause($id);

        $newStatus = $cause['is_active'] ? 0 : 1;
        $this->db->update('pause_causes', ['is_active' => $newStatus], ['id' => $id]);

        $this->audit('toggle_active', 'pause_cause', $id, ['is_active' => $cause['is_active']], ['is_active' => $newStatus]);

        $this->json([
            'success' => true,
            'is_active' => $newStatus
        ]);
    }

    /**
     * Get pause cause by ID
     */
    private function getCause(int $id): array
    {
        $cause = $this->db->fetch(
            "SELECT * FROM pause_causes WHERE id = ?",
            [$id]
        );

        if (!$cause) {
            $this->abort(404, 'Pause cause not found');
        }

        return $cause;
    }
}
