<?php
/**
 * Role-Based Access Control
 * Manages roles and permissions
 */

namespace aReports\Core;

class RBAC
{
    private Database $db;
    private array $cache = [];

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Get all roles
     */
    public function getRoles(): array
    {
        return $this->db->fetchAll("SELECT * FROM roles ORDER BY id");
    }

    /**
     * Get a role by ID
     */
    public function getRole(int $id): ?array
    {
        return $this->db->fetch("SELECT * FROM roles WHERE id = ?", [$id]);
    }

    /**
     * Get a role by name
     */
    public function getRoleByName(string $name): ?array
    {
        return $this->db->fetch("SELECT * FROM roles WHERE name = ?", [$name]);
    }

    /**
     * Create a role
     */
    public function createRole(string $name, string $displayName, ?string $description = null): int
    {
        return $this->db->insert('roles', [
            'name' => $name,
            'display_name' => $displayName,
            'description' => $description,
            'is_system' => 0
        ]);
    }

    /**
     * Update a role
     */
    public function updateRole(int $id, array $data): bool
    {
        // Don't allow changing system flag
        unset($data['is_system']);

        $affected = $this->db->update('roles', $data, ['id' => $id]);
        return $affected > 0;
    }

    /**
     * Delete a role
     */
    public function deleteRole(int $id): bool
    {
        // Check if it's a system role
        $role = $this->getRole($id);
        if ($role && $role['is_system']) {
            return false;
        }

        // Check if role is in use
        $usersCount = $this->db->count('users', ['role_id' => $id]);
        if ($usersCount > 0) {
            return false;
        }

        $affected = $this->db->delete('roles', ['id' => $id]);
        return $affected > 0;
    }

    /**
     * Get all permissions
     */
    public function getPermissions(): array
    {
        return $this->db->fetchAll("SELECT * FROM permissions ORDER BY category, name");
    }

    /**
     * Get permissions grouped by category
     */
    public function getPermissionsByCategory(): array
    {
        $permissions = $this->getPermissions();
        $grouped = [];

        foreach ($permissions as $permission) {
            $category = $permission['category'];
            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            $grouped[$category][] = $permission;
        }

        return $grouped;
    }

    /**
     * Get permissions for a role
     */
    public function getRolePermissions(int $roleId): array
    {
        $cacheKey = "role_permissions_{$roleId}";

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $sql = "SELECT p.* FROM permissions p
                JOIN role_permissions rp ON p.id = rp.permission_id
                WHERE rp.role_id = ?
                ORDER BY p.category, p.name";

        $permissions = $this->db->fetchAll($sql, [$roleId]);
        $this->cache[$cacheKey] = $permissions;

        return $permissions;
    }

    /**
     * Get permission names for a role
     */
    public function getRolePermissionNames(int $roleId): array
    {
        $permissions = $this->getRolePermissions($roleId);
        return array_column($permissions, 'name');
    }

    /**
     * Check if role has permission
     */
    public function roleHasPermission(int $roleId, string $permissionName): bool
    {
        $permissions = $this->getRolePermissionNames($roleId);
        return in_array($permissionName, $permissions);
    }

    /**
     * Grant permission to role
     */
    public function grantPermission(int $roleId, int $permissionId): bool
    {
        try {
            $this->db->insert('role_permissions', [
                'role_id' => $roleId,
                'permission_id' => $permissionId
            ]);
            $this->clearCache($roleId);
            return true;
        } catch (\Exception $e) {
            // Already exists
            return false;
        }
    }

    /**
     * Revoke permission from role
     */
    public function revokePermission(int $roleId, int $permissionId): bool
    {
        $affected = $this->db->delete('role_permissions', [
            'role_id' => $roleId,
            'permission_id' => $permissionId
        ]);
        $this->clearCache($roleId);
        return $affected > 0;
    }

    /**
     * Sync role permissions (replace all)
     */
    public function syncPermissions(int $roleId, array $permissionIds): void
    {
        $this->db->beginTransaction();

        try {
            // Remove all current permissions
            $this->db->delete('role_permissions', ['role_id' => $roleId]);

            // Add new permissions
            foreach ($permissionIds as $permissionId) {
                $this->db->insert('role_permissions', [
                    'role_id' => $roleId,
                    'permission_id' => (int)$permissionId
                ]);
            }

            $this->db->commit();
            $this->clearCache($roleId);
        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Get user permissions
     */
    public function getUserPermissions(int $userId): array
    {
        $cacheKey = "user_permissions_{$userId}";

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $sql = "SELECT p.name FROM permissions p
                JOIN role_permissions rp ON p.id = rp.permission_id
                JOIN users u ON u.role_id = rp.role_id
                WHERE u.id = ?";

        $results = $this->db->fetchAll($sql, [$userId]);
        $permissions = array_column($results, 'name');

        $this->cache[$cacheKey] = $permissions;

        return $permissions;
    }

    /**
     * Check if user has permission
     */
    public function userHasPermission(int $userId, string $permissionName): bool
    {
        $permissions = $this->getUserPermissions($userId);
        return in_array($permissionName, $permissions);
    }

    /**
     * Assign role to user
     */
    public function assignRole(int $userId, int $roleId): bool
    {
        $affected = $this->db->update('users', ['role_id' => $roleId], ['id' => $userId]);
        $this->clearCache(null, $userId);
        return $affected > 0;
    }

    /**
     * Get user's role
     */
    public function getUserRole(int $userId): ?array
    {
        $sql = "SELECT r.* FROM roles r
                JOIN users u ON u.role_id = r.id
                WHERE u.id = ?";

        return $this->db->fetch($sql, [$userId]);
    }

    /**
     * Clear cache
     */
    private function clearCache(?int $roleId = null, ?int $userId = null): void
    {
        if ($roleId !== null) {
            unset($this->cache["role_permissions_{$roleId}"]);
        }

        if ($userId !== null) {
            unset($this->cache["user_permissions_{$userId}"]);
        }

        if ($roleId === null && $userId === null) {
            $this->cache = [];
        }
    }

    /**
     * Get users with a specific role
     */
    public function getUsersWithRole(int $roleId): array
    {
        $sql = "SELECT id, username, email, first_name, last_name, extension, is_active, last_login
                FROM users WHERE role_id = ? ORDER BY first_name, last_name";

        return $this->db->fetchAll($sql, [$roleId]);
    }

    /**
     * Get users count for each role
     */
    public function getRoleUserCounts(): array
    {
        $sql = "SELECT r.id, r.name, r.display_name, COUNT(u.id) as user_count
                FROM roles r
                LEFT JOIN users u ON u.role_id = r.id
                GROUP BY r.id, r.name, r.display_name
                ORDER BY r.id";

        return $this->db->fetchAll($sql);
    }
}
