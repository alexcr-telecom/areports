<?php
/**
 * Admin Role Controller
 * Manages user roles and permissions
 */

namespace aReports\Controllers\Admin;

use aReports\Core\Controller;

class RoleController extends Controller
{
    /**
     * List roles
     */
    public function index(): void
    {
        $this->requirePermission('admin.roles.view');

        $roles = $this->db->fetchAll(
            "SELECT r.*, COUNT(u.id) as user_count
             FROM roles r
             LEFT JOIN users u ON r.id = u.role_id
             GROUP BY r.id
             ORDER BY r.id"
        );

        $this->render('admin/roles/index', [
            'title' => 'Role Management',
            'currentPage' => 'admin.roles',
            'roles' => $roles
        ]);
    }

    /**
     * Create role form
     */
    public function create(): void
    {
        $this->requirePermission('admin.roles.manage');

        $permissions = $this->db->fetchAll("SELECT * FROM permissions ORDER BY category, name");
        $groupedPermissions = $this->groupPermissions($permissions);

        $this->render('admin/roles/create', [
            'title' => 'Create Role',
            'currentPage' => 'admin.roles',
            'groupedPermissions' => $groupedPermissions
        ]);
    }

    /**
     * Store role
     */
    public function store(): void
    {
        $this->requirePermission('admin.roles.manage');

        $data = $this->validate($_POST, [
            'name' => 'required|max:50|unique:roles,name',
            'display_name' => 'required|max:100'
        ]);

        $roleId = $this->db->insert('roles', [
            'name' => $data['name'],
            'display_name' => $data['display_name'],
            'description' => $this->post('description'),
            'is_system' => 0
        ]);

        // Assign permissions
        $permissions = $this->post('permissions', []);
        foreach ($permissions as $permissionId) {
            $this->db->insert('role_permissions', [
                'role_id' => $roleId,
                'permission_id' => $permissionId
            ]);
        }

        $this->audit('create', 'role', $roleId);
        $this->redirectWith('/areports/admin/roles', 'success', 'Role created successfully.');
    }

    /**
     * Edit role form
     */
    public function edit(int $id): void
    {
        $this->requirePermission('admin.roles.manage');

        $role = $this->db->fetch("SELECT * FROM roles WHERE id = ?", [$id]);
        if (!$role) {
            $this->abort(404, 'Role not found');
        }

        $permissions = $this->db->fetchAll("SELECT * FROM permissions ORDER BY category, name");
        $groupedPermissions = $this->groupPermissions($permissions);

        $rolePermissions = $this->db->fetchAll(
            "SELECT permission_id FROM role_permissions WHERE role_id = ?",
            [$id]
        );
        $rolePermissionIds = array_column($rolePermissions, 'permission_id');

        $this->render('admin/roles/edit', [
            'title' => 'Edit Role',
            'currentPage' => 'admin.roles',
            'role' => $role,
            'groupedPermissions' => $groupedPermissions,
            'rolePermissionIds' => $rolePermissionIds
        ]);
    }

    /**
     * Update role
     */
    public function update(int $id): void
    {
        $this->requirePermission('admin.roles.manage');

        $role = $this->db->fetch("SELECT * FROM roles WHERE id = ?", [$id]);
        if (!$role) {
            $this->abort(404, 'Role not found');
        }

        $data = $this->validate($_POST, [
            'display_name' => 'required|max:100'
        ]);

        $this->db->update('roles', [
            'display_name' => $data['display_name'],
            'description' => $this->post('description')
        ], ['id' => $id]);

        // Update permissions (only for non-system roles)
        if (!$role['is_system']) {
            $this->db->delete('role_permissions', ['role_id' => $id]);

            $permissions = $this->post('permissions', []);
            foreach ($permissions as $permissionId) {
                $this->db->insert('role_permissions', [
                    'role_id' => $id,
                    'permission_id' => $permissionId
                ]);
            }
        }

        $this->audit('update', 'role', $id);
        $this->redirectWith('/areports/admin/roles', 'success', 'Role updated successfully.');
    }

    /**
     * Delete role
     */
    public function delete(int $id): void
    {
        $this->requirePermission('admin.roles.manage');

        $role = $this->db->fetch("SELECT * FROM roles WHERE id = ?", [$id]);
        if (!$role) {
            $this->abort(404, 'Role not found');
        }

        if ($role['is_system']) {
            $this->redirectWith('/areports/admin/roles', 'error', 'Cannot delete system roles.');
            return;
        }

        // Check if role has users
        $userCount = $this->db->count('users', ['role_id' => $id]);
        if ($userCount > 0) {
            $this->redirectWith('/areports/admin/roles', 'error', 'Cannot delete role with assigned users.');
            return;
        }

        $this->db->delete('role_permissions', ['role_id' => $id]);
        $this->db->delete('roles', ['id' => $id]);

        $this->audit('delete', 'role', $id);
        $this->redirectWith('/areports/admin/roles', 'success', 'Role deleted successfully.');
    }

    /**
     * Group permissions by category
     */
    private function groupPermissions(array $permissions): array
    {
        $grouped = [];
        foreach ($permissions as $permission) {
            $category = $permission['category'] ?? 'general';
            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            $grouped[$category][] = $permission;
        }
        return $grouped;
    }
}
