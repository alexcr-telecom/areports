<?php
/**
 * Admin Audit Controller
 * Shows audit log
 */

namespace aReports\Controllers\Admin;

use aReports\Core\Controller;

class AuditController extends Controller
{
    /**
     * List audit log entries
     */
    public function index(): void
    {
        $this->requirePermission('admin.audit.view');

        $page = (int) $this->get('page', 1);
        $perPage = 50;

        $entityType = $this->get('entity_type');
        $action = $this->get('action');
        $userId = $this->get('user_id');

        $where = '1=1';
        $params = [];

        if ($entityType) {
            $where .= ' AND al.entity_type = ?';
            $params[] = $entityType;
        }

        if ($action) {
            $where .= ' AND al.action = ?';
            $params[] = $action;
        }

        if ($userId) {
            $where .= ' AND al.user_id = ?';
            $params[] = $userId;
        }

        $logs = $this->db->fetchAll(
            "SELECT al.*, u.username, u.first_name, u.last_name
             FROM audit_log al
             LEFT JOIN users u ON al.user_id = u.id
             WHERE {$where}
             ORDER BY al.created_at DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, ($page - 1) * $perPage])
        );

        // Get total count
        $totalSql = "SELECT COUNT(*) FROM audit_log al WHERE {$where}";
        $total = (int) $this->db->fetchColumn($totalSql, $params);

        // Get filter options
        $entityTypes = $this->db->fetchAll("SELECT DISTINCT entity_type FROM audit_log ORDER BY entity_type");
        $actions = $this->db->fetchAll("SELECT DISTINCT action FROM audit_log ORDER BY action");
        $users = $this->db->fetchAll("SELECT id, username, first_name, last_name FROM users ORDER BY username");

        $this->render('admin/audit/index', [
            'title' => 'Audit Log',
            'currentPage' => 'admin.audit',
            'logs' => $logs,
            'page' => $page,
            'totalPages' => ceil($total / $perPage),
            'total' => $total,
            'entityTypes' => $entityTypes,
            'actions' => $actions,
            'users' => $users,
            'filters' => [
                'entity_type' => $entityType,
                'action' => $action,
                'user_id' => $userId
            ]
        ]);
    }
}
