<?php
/**
 * API Key Controller
 * Manages API keys for external integrations
 */

namespace aReports\Controllers\Admin;

use aReports\Core\Controller;

class ApiKeyController extends Controller
{
    /**
     * List API keys
     */
    public function index(): void
    {
        $this->requirePermission('admin.api_keys');

        $apiKeys = $this->db->fetchAll(
            "SELECT ak.*, u.first_name, u.last_name, u.username
             FROM api_keys ak
             LEFT JOIN users u ON ak.user_id = u.id
             ORDER BY ak.created_at DESC"
        );

        // Mask the API keys for display
        foreach ($apiKeys as &$key) {
            $key['masked_key'] = substr($key['api_key'], 0, 8) . '...' . substr($key['api_key'], -4);
        }

        $this->render('admin/api-keys/index', [
            'title' => 'API Keys',
            'currentPage' => 'admin.api_keys',
            'apiKeys' => $apiKeys
        ]);
    }

    /**
     * Create API key form
     */
    public function create(): void
    {
        $this->requirePermission('admin.api_keys');

        $users = $this->db->fetchAll(
            "SELECT id, username, first_name, last_name FROM users WHERE is_active = 1 ORDER BY first_name"
        );

        $this->render('admin/api-keys/create', [
            'title' => 'Create API Key',
            'currentPage' => 'admin.api_keys',
            'users' => $users
        ]);
    }

    /**
     * Store API key
     */
    public function store(): void
    {
        $this->requirePermission('admin.api_keys');

        $data = $this->validate($_POST, [
            'name' => 'required|max:100',
        ]);

        // Generate secure API key
        $apiKey = $this->generateApiKey();

        $keyId = $this->db->insert('api_keys', [
            'user_id' => $this->post('user_id') ?: null,
            'name' => $data['name'],
            'api_key' => $apiKey,
            'permissions' => json_encode($this->post('permissions', ['*'])),
            'rate_limit' => (int) $this->post('rate_limit', 1000),
            'is_active' => 1,
            'expires_at' => $this->post('expires_at') ?: null,
        ]);

        $this->audit('create', 'api_key', $keyId);

        // Show the key once
        $this->session->flash('new_api_key', $apiKey);
        $this->redirectWith('/areports/admin/api-keys', 'success', 'API key created successfully. Copy it now - it won\'t be shown again!');
    }

    /**
     * Delete API key
     */
    public function delete(int $id): void
    {
        $this->requirePermission('admin.api_keys');

        $this->db->delete('api_keys', ['id' => $id]);
        $this->audit('delete', 'api_key', $id);
        $this->redirectWith('/areports/admin/api-keys', 'success', 'API key deleted successfully.');
    }

    /**
     * Toggle API key active status
     */
    public function toggle(int $id): void
    {
        $this->requirePermission('admin.api_keys');

        $key = $this->db->fetch("SELECT is_active FROM api_keys WHERE id = ?", [$id]);
        if (!$key) {
            $this->abort(404, 'API key not found');
        }

        $newStatus = $key['is_active'] ? 0 : 1;
        $this->db->update('api_keys', ['is_active' => $newStatus], ['id' => $id]);

        $this->audit('toggle', 'api_key', $id, null, ['is_active' => $newStatus]);

        if ($this->isAjax()) {
            $this->json(['success' => true, 'is_active' => $newStatus]);
        } else {
            $this->redirectWith('/areports/admin/api-keys', 'success', 'API key status updated.');
        }
    }

    /**
     * Generate secure API key
     */
    private function generateApiKey(): string
    {
        return bin2hex(random_bytes(32));
    }
}
