<?php
/**
 * Campaign Controller
 * Manages call center campaigns (inbound/outbound)
 */

namespace aReports\Controllers;

use aReports\Core\Controller;

class CampaignController extends Controller
{
    /**
     * List campaigns
     */
    public function index(): void
    {
        $this->requirePermission('campaigns.view');

        $status = $this->get('status');
        $type = $this->get('type');

        $sql = "SELECT c.*, qs.display_name as queue_name,
                       u.first_name, u.last_name,
                       (SELECT COUNT(*) FROM campaign_leads cl
                        JOIN campaign_lists clist ON cl.list_id = clist.id
                        WHERE clist.campaign_id = c.id) as total_leads,
                       (SELECT COUNT(*) FROM campaign_call_results ccr
                        WHERE ccr.campaign_id = c.id) as total_calls
                FROM campaigns c
                LEFT JOIN queue_settings qs ON c.queue_id = qs.id
                LEFT JOIN users u ON c.created_by = u.id
                WHERE 1=1";

        $params = [];

        if ($status) {
            $sql .= " AND c.status = ?";
            $params[] = $status;
        }

        if ($type) {
            $sql .= " AND c.type = ?";
            $params[] = $type;
        }

        $sql .= " ORDER BY c.created_at DESC";

        $campaigns = $this->db->fetchAll($sql, $params);

        $this->render('campaigns/index', [
            'title' => 'Campaigns',
            'currentPage' => 'campaigns',
            'campaigns' => $campaigns,
            'filters' => [
                'status' => $status,
                'type' => $type
            ]
        ]);
    }

    /**
     * Create campaign form
     */
    public function create(): void
    {
        $this->requirePermission('campaigns.manage');

        $queues = $this->db->fetchAll("SELECT * FROM queue_settings ORDER BY display_name");

        $this->render('campaigns/create', [
            'title' => 'Create Campaign',
            'currentPage' => 'campaigns',
            'queues' => $queues
        ]);
    }

    /**
     * Store campaign
     */
    public function store(): void
    {
        $this->requirePermission('campaigns.manage');

        $data = $this->validate($_POST, [
            'name' => 'required|max:100',
            'type' => 'required|in:inbound,outbound,blended',
        ]);

        $campaignId = $this->db->insert('campaigns', [
            'name' => $data['name'],
            'description' => $this->post('description'),
            'type' => $data['type'],
            'queue_id' => $this->post('queue_id') ?: null,
            'status' => 'draft',
            'start_date' => $this->post('start_date') ?: null,
            'end_date' => $this->post('end_date') ?: null,
            'target_calls' => $this->post('target_calls') ?: null,
            'target_conversion' => $this->post('target_conversion') ?: null,
            'settings' => json_encode([
                'wrap_up_time' => $this->post('wrap_up_time', 30),
                'max_attempts' => $this->post('max_attempts', 3),
                'callback_enabled' => $this->post('callback_enabled') ? true : false,
            ]),
            'created_by' => $this->user['id'],
        ]);

        // Create default dispositions
        $this->createDefaultDispositions($campaignId);

        $this->audit('create', 'campaign', $campaignId);
        $this->redirectWith('/areports/campaigns/' . $campaignId, 'success', 'Campaign created successfully.');
    }

    /**
     * Show campaign details
     */
    public function show(int $id): void
    {
        $this->requirePermission('campaigns.view');

        $campaign = $this->getCampaign($id);

        // Get campaign stats
        $stats = $this->getCampaignStats($id);

        // Get recent calls
        $recentCalls = $this->db->fetchAll(
            "SELECT ccr.*, cd.name as disposition_name, cd.category as disposition_category
             FROM campaign_call_results ccr
             LEFT JOIN campaign_dispositions cd ON ccr.disposition_id = cd.id
             WHERE ccr.campaign_id = ?
             ORDER BY ccr.created_at DESC
             LIMIT 20",
            [$id]
        );

        // Get lists
        $lists = $this->db->fetchAll(
            "SELECT * FROM campaign_lists WHERE campaign_id = ? ORDER BY name",
            [$id]
        );

        $this->render('campaigns/show', [
            'title' => $campaign['name'],
            'currentPage' => 'campaigns',
            'campaign' => $campaign,
            'stats' => $stats,
            'recentCalls' => $recentCalls,
            'lists' => $lists
        ]);
    }

    /**
     * Edit campaign form
     */
    public function edit(int $id): void
    {
        $this->requirePermission('campaigns.manage');

        $campaign = $this->getCampaign($id);
        $queues = $this->db->fetchAll("SELECT * FROM queue_settings ORDER BY display_name");

        $this->render('campaigns/edit', [
            'title' => 'Edit Campaign',
            'currentPage' => 'campaigns',
            'campaign' => $campaign,
            'queues' => $queues
        ]);
    }

    /**
     * Update campaign
     */
    public function update(int $id): void
    {
        $this->requirePermission('campaigns.manage');

        $data = $this->validate($_POST, [
            'name' => 'required|max:100',
            'type' => 'required|in:inbound,outbound,blended',
        ]);

        $this->db->update('campaigns', [
            'name' => $data['name'],
            'description' => $this->post('description'),
            'type' => $data['type'],
            'queue_id' => $this->post('queue_id') ?: null,
            'start_date' => $this->post('start_date') ?: null,
            'end_date' => $this->post('end_date') ?: null,
            'target_calls' => $this->post('target_calls') ?: null,
            'target_conversion' => $this->post('target_conversion') ?: null,
            'settings' => json_encode([
                'wrap_up_time' => $this->post('wrap_up_time', 30),
                'max_attempts' => $this->post('max_attempts', 3),
                'callback_enabled' => $this->post('callback_enabled') ? true : false,
            ]),
        ], ['id' => $id]);

        $this->audit('update', 'campaign', $id);
        $this->redirectWith('/areports/campaigns/' . $id, 'success', 'Campaign updated successfully.');
    }

    /**
     * Delete campaign
     */
    public function delete(int $id): void
    {
        $this->requirePermission('campaigns.manage');

        $this->db->delete('campaigns', ['id' => $id]);
        $this->audit('delete', 'campaign', $id);
        $this->redirectWith('/areports/campaigns', 'success', 'Campaign deleted successfully.');
    }

    /**
     * Update campaign status
     */
    public function updateStatus(int $id): void
    {
        $this->requirePermission('campaigns.manage');

        $status = $this->post('status');
        $validStatuses = ['draft', 'active', 'paused', 'completed', 'archived'];

        if (!in_array($status, $validStatuses)) {
            $this->json(['success' => false, 'message' => 'Invalid status']);
            return;
        }

        $this->db->update('campaigns', ['status' => $status], ['id' => $id]);
        $this->audit('update_status', 'campaign', $id, null, ['status' => $status]);

        if ($this->isAjax()) {
            $this->json(['success' => true, 'message' => 'Status updated']);
        } else {
            $this->redirectWith('/areports/campaigns/' . $id, 'success', 'Campaign status updated.');
        }
    }

    /**
     * Campaign statistics
     */
    public function stats(int $id): void
    {
        $this->requirePermission('campaigns.view');

        $stats = $this->getCampaignStats($id);
        $this->json(['data' => $stats]);
    }

    /**
     * Campaign leads
     */
    public function leads(int $id): void
    {
        $this->requirePermission('campaigns.view');

        $campaign = $this->getCampaign($id);
        $listId = $this->get('list_id');
        $status = $this->get('status');
        $page = (int) $this->get('page', 1);
        $perPage = 50;

        $sql = "SELECT cl.*, clist.name as list_name
                FROM campaign_leads cl
                JOIN campaign_lists clist ON cl.list_id = clist.id
                WHERE clist.campaign_id = ?";

        $params = [$id];

        if ($listId) {
            $sql .= " AND cl.list_id = ?";
            $params[] = $listId;
        }

        if ($status) {
            $sql .= " AND cl.status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY cl.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = ($page - 1) * $perPage;

        $leads = $this->db->fetchAll($sql, $params);

        // Get lists for filter
        $lists = $this->db->fetchAll(
            "SELECT * FROM campaign_lists WHERE campaign_id = ?",
            [$id]
        );

        $this->render('campaigns/leads', [
            'title' => $campaign['name'] . ' - Leads',
            'currentPage' => 'campaigns',
            'campaign' => $campaign,
            'leads' => $leads,
            'lists' => $lists,
            'filters' => [
                'list_id' => $listId,
                'status' => $status
            ],
            'page' => $page
        ]);
    }

    /**
     * Import leads
     */
    public function importLeads(int $id): void
    {
        $this->requirePermission('campaigns.manage');

        $file = $this->file('file');
        $listName = $this->post('list_name', 'Imported List ' . date('Y-m-d H:i'));

        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $this->redirectWith('/areports/campaigns/' . $id . '/leads', 'error', 'Please upload a valid file.');
            return;
        }

        // Create list
        $listId = $this->db->insert('campaign_lists', [
            'campaign_id' => $id,
            'name' => $listName,
            'status' => 'active',
        ]);

        // Parse CSV
        $handle = fopen($file['tmp_name'], 'r');
        $header = fgetcsv($handle);
        $imported = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($header, $row);

            $this->db->insert('campaign_leads', [
                'list_id' => $listId,
                'phone_number' => $data['phone'] ?? $data['phone_number'] ?? '',
                'first_name' => $data['first_name'] ?? $data['name'] ?? '',
                'last_name' => $data['last_name'] ?? '',
                'email' => $data['email'] ?? '',
                'company' => $data['company'] ?? '',
                'custom_fields' => json_encode(array_diff_key($data, array_flip(['phone', 'phone_number', 'first_name', 'last_name', 'name', 'email', 'company']))),
                'status' => 'new',
            ]);
            $imported++;
        }

        fclose($handle);

        // Update list total
        $this->db->update('campaign_lists', ['total_records' => $imported], ['id' => $listId]);

        $this->audit('import_leads', 'campaign', $id, null, ['list_id' => $listId, 'count' => $imported]);
        $this->redirectWith('/areports/campaigns/' . $id . '/leads', 'success', "Imported {$imported} leads successfully.");
    }

    /**
     * Campaign dispositions
     */
    public function dispositions(int $id): void
    {
        $this->requirePermission('campaigns.view');

        $campaign = $this->getCampaign($id);

        $dispositions = $this->db->fetchAll(
            "SELECT * FROM campaign_dispositions WHERE campaign_id = ? ORDER BY sort_order",
            [$id]
        );

        $this->render('campaigns/dispositions', [
            'title' => $campaign['name'] . ' - Dispositions',
            'currentPage' => 'campaigns',
            'campaign' => $campaign,
            'dispositions' => $dispositions
        ]);
    }

    /**
     * Get campaign or 404
     */
    private function getCampaign(int $id): array
    {
        $campaign = $this->db->fetch(
            "SELECT c.*, qs.display_name as queue_name
             FROM campaigns c
             LEFT JOIN queue_settings qs ON c.queue_id = qs.id
             WHERE c.id = ?",
            [$id]
        );

        if (!$campaign) {
            $this->abort(404, 'Campaign not found');
        }

        $campaign['settings'] = json_decode($campaign['settings'] ?? '{}', true);
        return $campaign;
    }

    /**
     * Get campaign statistics
     */
    private function getCampaignStats(int $id): array
    {
        // Total leads
        $totalLeads = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM campaign_leads cl
             JOIN campaign_lists clist ON cl.list_id = clist.id
             WHERE clist.campaign_id = ?",
            [$id]
        );

        // Leads by status
        $leadsByStatus = $this->db->fetchAll(
            "SELECT cl.status, COUNT(*) as count
             FROM campaign_leads cl
             JOIN campaign_lists clist ON cl.list_id = clist.id
             WHERE clist.campaign_id = ?
             GROUP BY cl.status",
            [$id]
        );

        // Total calls
        $totalCalls = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM campaign_call_results WHERE campaign_id = ?",
            [$id]
        );

        // Conversions
        $conversions = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM campaign_call_results ccr
             JOIN campaign_dispositions cd ON ccr.disposition_id = cd.id
             WHERE ccr.campaign_id = ? AND cd.is_conversion = 1",
            [$id]
        );

        // Average talk time
        $avgTalkTime = $this->db->fetchColumn(
            "SELECT AVG(talk_time) FROM campaign_call_results WHERE campaign_id = ? AND talk_time > 0",
            [$id]
        );

        // Dispositions breakdown
        $dispositionBreakdown = $this->db->fetchAll(
            "SELECT cd.name, cd.category, COUNT(*) as count
             FROM campaign_call_results ccr
             JOIN campaign_dispositions cd ON ccr.disposition_id = cd.id
             WHERE ccr.campaign_id = ?
             GROUP BY cd.id
             ORDER BY count DESC",
            [$id]
        );

        return [
            'total_leads' => (int) $totalLeads,
            'leads_by_status' => array_column($leadsByStatus, 'count', 'status'),
            'total_calls' => (int) $totalCalls,
            'conversions' => (int) $conversions,
            'conversion_rate' => $totalCalls > 0 ? round(($conversions / $totalCalls) * 100, 2) : 0,
            'avg_talk_time' => (int) $avgTalkTime,
            'disposition_breakdown' => $dispositionBreakdown,
        ];
    }

    /**
     * Create default dispositions for campaign
     */
    private function createDefaultDispositions(int $campaignId): void
    {
        $defaults = [
            ['name' => 'Sale', 'category' => 'positive', 'is_conversion' => 1, 'sort_order' => 1],
            ['name' => 'Interested', 'category' => 'positive', 'is_conversion' => 0, 'sort_order' => 2],
            ['name' => 'Callback', 'category' => 'callback', 'requires_callback' => 1, 'sort_order' => 3],
            ['name' => 'Not Interested', 'category' => 'negative', 'sort_order' => 4],
            ['name' => 'No Answer', 'category' => 'neutral', 'sort_order' => 5],
            ['name' => 'Busy', 'category' => 'neutral', 'sort_order' => 6],
            ['name' => 'Wrong Number', 'category' => 'negative', 'sort_order' => 7],
            ['name' => 'Do Not Call', 'category' => 'negative', 'sort_order' => 8],
        ];

        foreach ($defaults as $disposition) {
            $disposition['campaign_id'] = $campaignId;
            $disposition['is_active'] = 1;
            $this->db->insert('campaign_dispositions', $disposition);
        }
    }
}
