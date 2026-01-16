<?php
/**
 * Admin Agent Controller
 * Manages agent settings
 */

namespace aReports\Controllers\Admin;

use aReports\Core\Controller;
use aReports\Services\AgentService;
use aReports\Services\FreePBXService;

class AgentController extends Controller
{
    /**
     * List agents
     */
    public function index(): void
    {
        $this->requirePermission('admin.agents.view');

        $agents = $this->db->fetchAll("SELECT * FROM agent_settings ORDER BY extension");

        // Get FreePBX queue agents
        $freepbxService = new FreePBXService();
        $freepbxAgents = $freepbxService->getQueueAgents();

        $this->render('admin/agents/index', [
            'title' => 'Agent Settings',
            'currentPage' => 'admin.agents',
            'agents' => $agents,
            'freepbxAgents' => $freepbxAgents
        ]);
    }

    /**
     * Edit agent form
     */
    public function edit(int $id): void
    {
        $this->requirePermission('admin.agents.manage');

        $agent = $this->db->fetch("SELECT * FROM agent_settings WHERE id = ?", [$id]);
        if (!$agent) {
            $this->abort(404, 'Agent not found');
        }

        $this->render('admin/agents/edit', [
            'title' => 'Edit Agent',
            'currentPage' => 'admin.agents',
            'agent' => $agent
        ]);
    }

    /**
     * Update agent
     */
    public function update(int $id): void
    {
        $this->requirePermission('admin.agents.manage');

        $data = $this->validate($_POST, [
            'display_name' => 'required|max:100'
        ]);

        $this->db->update('agent_settings', [
            'display_name' => $data['display_name'],
            'team' => $this->post('team'),
            'wrap_up_time' => (int) $this->post('wrap_up_time', 0)
        ], ['id' => $id]);

        $this->audit('update', 'agent_settings', $id);
        $this->redirectWith('/areports/admin/agents', 'success', 'Agent updated successfully.');
    }

    /**
     * Sync agents from FreePBX
     */
    public function sync(): void
    {
        $this->requirePermission('admin.agents.manage');

        $freepbxService = new FreePBXService();
        $freepbxAgents = $freepbxService->getQueueAgents();

        $synced = 0;
        $updated = 0;

        foreach ($freepbxAgents as $agent) {
            $existing = $this->db->fetch(
                "SELECT id FROM agent_settings WHERE extension = ?",
                [$agent['extension']]
            );

            if (!$existing) {
                $this->db->insert('agent_settings', [
                    'extension' => $agent['extension'],
                    'display_name' => $agent['name'],
                    'wrap_up_time' => 30,
                    'is_monitored' => 1
                ]);
                $synced++;
            } else {
                // Update display name from FreePBX if it was auto-generated
                $this->db->update('agent_settings', [
                    'display_name' => $agent['name']
                ], ['id' => $existing['id'], 'display_name' => $existing['id']]);
                $updated++;
            }
        }

        $this->audit('sync', 'agent_settings', null, null, ['synced' => $synced, 'updated' => $updated]);
        $this->redirectWith('/areports/admin/agents', 'success', "Synced {$synced} new agents from FreePBX.");
    }
}
