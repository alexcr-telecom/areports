<?php
/**
 * Wallboard Layout Controller
 * Manages wallboard display layouts
 */

namespace aReports\Controllers\Admin;

use aReports\Core\Controller;

class WallboardLayoutController extends Controller
{
    /**
     * Available widgets
     */
    private array $availableWidgets = [
        'queue_status' => [
            'name' => 'Queue Status',
            'description' => 'Real-time queue statistics',
            'sizes' => ['small', 'medium', 'large'],
        ],
        'agent_status' => [
            'name' => 'Agent Status',
            'description' => 'Agent availability panel',
            'sizes' => ['small', 'medium', 'large'],
        ],
        'calls_waiting' => [
            'name' => 'Calls Waiting',
            'description' => 'Number of calls in queue',
            'sizes' => ['small', 'medium'],
        ],
        'sla_gauge' => [
            'name' => 'SLA Gauge',
            'description' => 'Service level gauge',
            'sizes' => ['small', 'medium'],
        ],
        'calls_today' => [
            'name' => 'Calls Today',
            'description' => 'Total calls handled today',
            'sizes' => ['small', 'medium'],
        ],
        'avg_wait_time' => [
            'name' => 'Average Wait Time',
            'description' => 'Current average wait time',
            'sizes' => ['small', 'medium'],
        ],
        'avg_talk_time' => [
            'name' => 'Average Talk Time',
            'description' => 'Average talk time today',
            'sizes' => ['small', 'medium'],
        ],
        'hourly_chart' => [
            'name' => 'Hourly Call Volume',
            'description' => 'Chart of calls per hour',
            'sizes' => ['medium', 'large'],
        ],
        'active_calls' => [
            'name' => 'Active Calls',
            'description' => 'List of current active calls',
            'sizes' => ['medium', 'large'],
        ],
        'top_agents' => [
            'name' => 'Top Agents',
            'description' => 'Leaderboard of top performing agents',
            'sizes' => ['medium', 'large'],
        ],
        'alerts' => [
            'name' => 'Active Alerts',
            'description' => 'Current active alerts',
            'sizes' => ['small', 'medium'],
        ],
        'abandoned_rate' => [
            'name' => 'Abandonment Rate',
            'description' => 'Current abandonment percentage',
            'sizes' => ['small', 'medium'],
        ],
        'clock' => [
            'name' => 'Clock',
            'description' => 'Current time display',
            'sizes' => ['small'],
        ],
    ];

    /**
     * List wallboard layouts
     */
    public function index(): void
    {
        $this->requirePermission('admin.wallboard');

        $layouts = $this->db->fetchAll(
            "SELECT wl.*, u.first_name, u.last_name
             FROM wallboard_layouts wl
             LEFT JOIN users u ON wl.created_by = u.id
             ORDER BY wl.is_default DESC, wl.name"
        );

        $this->render('admin/wallboard-layouts/index', [
            'title' => 'Wallboard Layouts',
            'currentPage' => 'admin.wallboard_layouts',
            'layouts' => $layouts
        ]);
    }

    /**
     * Create layout form
     */
    public function create(): void
    {
        $this->requirePermission('admin.wallboard');

        $this->render('admin/wallboard-layouts/create', [
            'title' => 'Create Wallboard Layout',
            'currentPage' => 'admin.wallboard_layouts',
            'availableWidgets' => $this->availableWidgets
        ]);
    }

    /**
     * Store layout
     */
    public function store(): void
    {
        $this->requirePermission('admin.wallboard');

        $data = $this->validate($_POST, [
            'name' => 'required|max:100',
            'layout_type' => 'required|in:grid,sidebar,fullwidth,custom',
        ]);

        // Parse widgets from form
        $widgets = $this->parseWidgetsFromForm();

        // If setting as default, unset other defaults
        if ($this->post('is_default')) {
            $this->db->query("UPDATE wallboard_layouts SET is_default = 0");
        }

        $layoutId = $this->db->insert('wallboard_layouts', [
            'name' => $data['name'],
            'description' => $this->post('description'),
            'layout_type' => $data['layout_type'],
            'columns' => (int) $this->post('columns', 3),
            'widgets' => json_encode($widgets),
            'theme' => $this->post('theme', 'dark'),
            'refresh_interval' => (int) $this->post('refresh_interval', 5000),
            'is_public' => $this->post('is_public') ? 1 : 0,
            'is_default' => $this->post('is_default') ? 1 : 0,
            'created_by' => $this->user['id'],
        ]);

        $this->audit('create', 'wallboard_layout', $layoutId);
        $this->redirectWith('/areports/admin/wallboard-layouts', 'success', 'Wallboard layout created successfully.');
    }

    /**
     * Edit layout form
     */
    public function edit(int $id): void
    {
        $this->requirePermission('admin.wallboard');

        $layout = $this->getLayout($id);

        $this->render('admin/wallboard-layouts/edit', [
            'title' => 'Edit Wallboard Layout',
            'currentPage' => 'admin.wallboard_layouts',
            'layout' => $layout,
            'availableWidgets' => $this->availableWidgets
        ]);
    }

    /**
     * Update layout
     */
    public function update(int $id): void
    {
        $this->requirePermission('admin.wallboard');

        $data = $this->validate($_POST, [
            'name' => 'required|max:100',
            'layout_type' => 'required|in:grid,sidebar,fullwidth,custom',
        ]);

        $widgets = $this->parseWidgetsFromForm();

        // If setting as default, unset other defaults
        if ($this->post('is_default')) {
            $this->db->query("UPDATE wallboard_layouts SET is_default = 0 WHERE id != ?", [$id]);
        }

        $this->db->update('wallboard_layouts', [
            'name' => $data['name'],
            'description' => $this->post('description'),
            'layout_type' => $data['layout_type'],
            'columns' => (int) $this->post('columns', 3),
            'widgets' => json_encode($widgets),
            'theme' => $this->post('theme', 'dark'),
            'refresh_interval' => (int) $this->post('refresh_interval', 5000),
            'is_public' => $this->post('is_public') ? 1 : 0,
            'is_default' => $this->post('is_default') ? 1 : 0,
        ], ['id' => $id]);

        $this->audit('update', 'wallboard_layout', $id);
        $this->redirectWith('/areports/admin/wallboard-layouts', 'success', 'Wallboard layout updated successfully.');
    }

    /**
     * Delete layout
     */
    public function delete(int $id): void
    {
        $this->requirePermission('admin.wallboard');

        $layout = $this->getLayout($id);

        if ($layout['is_default']) {
            $this->redirectWith('/areports/admin/wallboard-layouts', 'error', 'Cannot delete the default layout.');
            return;
        }

        $this->db->delete('wallboard_layouts', ['id' => $id]);
        $this->audit('delete', 'wallboard_layout', $id);
        $this->redirectWith('/areports/admin/wallboard-layouts', 'success', 'Wallboard layout deleted successfully.');
    }

    /**
     * Get layout or 404
     */
    private function getLayout(int $id): array
    {
        $layout = $this->db->fetch("SELECT * FROM wallboard_layouts WHERE id = ?", [$id]);

        if (!$layout) {
            $this->abort(404, 'Wallboard layout not found');
        }

        $layout['widgets'] = json_decode($layout['widgets'], true) ?: [];
        return $layout;
    }

    /**
     * Parse widgets from form submission
     */
    private function parseWidgetsFromForm(): array
    {
        $widgets = [];
        $widgetData = $this->post('widgets', []);

        foreach ($widgetData as $index => $widget) {
            if (empty($widget['type'])) {
                continue;
            }

            $widgets[] = [
                'id' => $widget['id'] ?? uniqid('widget_'),
                'type' => $widget['type'],
                'size' => $widget['size'] ?? 'medium',
                'position' => [
                    'row' => (int) ($widget['row'] ?? $index),
                    'col' => (int) ($widget['col'] ?? 0),
                ],
                'config' => [
                    'queue' => $widget['queue'] ?? null,
                    'show_title' => isset($widget['show_title']),
                    'color' => $widget['color'] ?? null,
                ],
            ];
        }

        return $widgets;
    }
}
