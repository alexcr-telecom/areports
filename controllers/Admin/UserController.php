<?php
/**
 * Admin User Controller
 * Manages system users
 */

namespace aReports\Controllers\Admin;

use aReports\Core\Controller;
use aReports\Services\FreePBXService;

class UserController extends Controller
{
    /**
     * List all users
     */
    public function index(): void
    {
        $this->requirePermission('admin.users.view');

        $users = $this->db->fetchAll(
            "SELECT u.*, r.display_name as role_name
             FROM users u
             JOIN roles r ON u.role_id = r.id
             ORDER BY u.first_name, u.last_name"
        );

        $this->render('admin/users/index', [
            'title' => 'User Management',
            'currentPage' => 'admin.users',
            'users' => $users
        ]);
    }

    /**
     * Show create user form
     */
    public function create(): void
    {
        $this->requirePermission('admin.users.manage');

        $roles = $this->db->fetchAll("SELECT * FROM roles ORDER BY id");

        // Get FreePBX extensions
        $freepbxService = new FreePBXService();
        $extensions = $freepbxService->getExtensions();

        $this->render('admin/users/create', [
            'title' => 'Create User',
            'currentPage' => 'admin.users',
            'roles' => $roles,
            'extensions' => $extensions
        ]);
    }

    /**
     * Store new user
     */
    public function store(): void
    {
        $this->requirePermission('admin.users.manage');

        $data = $this->validate($_POST, [
            'username' => 'required|min:3|max:50|unique:users,username',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8|confirmed',
            'first_name' => 'required|max:50',
            'last_name' => 'required|max:50',
            'role_id' => 'required|exists:roles,id'
        ]);

        $userId = $this->db->insert('users', [
            'username' => $data['username'],
            'email' => $data['email'],
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'role_id' => $data['role_id'],
            'extension' => $this->post('extension'),
            'is_active' => 1
        ]);

        // Create user preferences
        $this->db->insert('user_preferences', ['user_id' => $userId]);

        $this->audit('create', 'user', $userId, null, [
            'username' => $data['username'],
            'email' => $data['email']
        ]);

        $this->redirectWith('/areports/admin/users', 'success', 'User created successfully.');
    }

    /**
     * Show user details
     */
    public function show(int $id): void
    {
        $this->requirePermission('admin.users.view');

        $user = $this->getUser($id);

        $this->render('admin/users/show', [
            'title' => 'User Details',
            'currentPage' => 'admin.users',
            'user' => $user
        ]);
    }

    /**
     * Show edit user form
     */
    public function edit(int $id): void
    {
        $this->requirePermission('admin.users.manage');

        $user = $this->getUser($id);
        $roles = $this->db->fetchAll("SELECT * FROM roles ORDER BY id");

        // Get FreePBX extensions
        $freepbxService = new FreePBXService();
        $extensions = $freepbxService->getExtensions();

        $this->render('admin/users/edit', [
            'title' => 'Edit User',
            'currentPage' => 'admin.users',
            'user' => $user,
            'roles' => $roles,
            'extensions' => $extensions
        ]);
    }

    /**
     * Update user
     */
    public function update(int $id): void
    {
        $this->requirePermission('admin.users.manage');

        $user = $this->getUser($id);

        $rules = [
            'email' => 'required|email|unique:users,email,' . $id,
            'first_name' => 'required|max:50',
            'last_name' => 'required|max:50',
            'role_id' => 'required|exists:roles,id'
        ];

        // Only validate password if provided
        if (!empty($_POST['password'])) {
            $rules['password'] = 'min:8|confirmed';
        }

        $data = $this->validate($_POST, $rules);

        $updateData = [
            'email' => $data['email'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'role_id' => $data['role_id'],
            'extension' => $this->post('extension')
        ];

        // Update password if provided
        if (!empty($_POST['password'])) {
            $updateData['password_hash'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }

        $this->db->update('users', $updateData, ['id' => $id]);

        $this->audit('update', 'user', $id, [
            'email' => $user['email'],
            'role_id' => $user['role_id']
        ], [
            'email' => $data['email'],
            'role_id' => $data['role_id']
        ]);

        $this->redirectWith('/areports/admin/users', 'success', 'User updated successfully.');
    }

    /**
     * Delete user
     */
    public function delete(int $id): void
    {
        $this->requirePermission('admin.users.manage');

        $user = $this->getUser($id);

        // Prevent deleting yourself
        if ($id === $this->user['id']) {
            $this->redirectWith('/areports/admin/users', 'error', 'You cannot delete your own account.');
            return;
        }

        // Prevent deleting the last admin
        if ($user['role_id'] === 1) {
            $adminCount = $this->db->count('users', ['role_id' => 1, 'is_active' => 1]);
            if ($adminCount <= 1) {
                $this->redirectWith('/areports/admin/users', 'error', 'Cannot delete the last administrator.');
                return;
            }
        }

        $this->db->delete('users', ['id' => $id]);

        $this->audit('delete', 'user', $id, [
            'username' => $user['username'],
            'email' => $user['email']
        ]);

        $this->redirectWith('/areports/admin/users', 'success', 'User deleted successfully.');
    }

    /**
     * Toggle user active status
     */
    public function toggleActive(int $id): void
    {
        $this->requirePermission('admin.users.manage');

        $user = $this->getUser($id);

        // Prevent deactivating yourself
        if ($id === $this->user['id']) {
            $this->redirectWith('/areports/admin/users', 'error', 'You cannot deactivate your own account.');
            return;
        }

        $newStatus = $user['is_active'] ? 0 : 1;

        $this->db->update('users', ['is_active' => $newStatus], ['id' => $id]);

        $this->audit('toggle_active', 'user', $id, ['is_active' => $user['is_active']], ['is_active' => $newStatus]);

        $status = $newStatus ? 'activated' : 'deactivated';
        $this->redirectWith('/areports/admin/users', 'success', "User {$status} successfully.");
    }

    /**
     * Get user by ID
     */
    private function getUser(int $id): array
    {
        $user = $this->db->fetch(
            "SELECT u.*, r.display_name as role_name
             FROM users u
             JOIN roles r ON u.role_id = r.id
             WHERE u.id = ?",
            [$id]
        );

        if (!$user) {
            $this->abort(404, 'User not found');
        }

        return $user;
    }
}
