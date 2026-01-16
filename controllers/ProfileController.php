<?php
/**
 * Profile Controller
 * Handles user profile management
 */

namespace aReports\Controllers;

use aReports\Core\Controller;

class ProfileController extends Controller
{
    /**
     * Show user profile
     */
    public function index(): void
    {
        $this->render('profile/index', [
            'title' => 'My Profile',
            'currentPage' => 'profile'
        ]);
    }

    /**
     * Update profile
     */
    public function update(): void
    {
        $data = $this->validate($_POST, [
            'first_name' => 'required|max:50',
            'last_name' => 'required|max:50',
            'email' => 'required|email|unique:users,email,' . $this->user['id']
        ]);

        $this->db->update('users', [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email']
        ], ['id' => $this->user['id']]);

        $this->audit('update', 'profile', $this->user['id']);

        $this->redirectWith('/areports/profile', 'success', 'Profile updated successfully.');
    }

    /**
     * Change password
     */
    public function changePassword(): void
    {
        $data = $this->validate($_POST, [
            'current_password' => 'required',
            'password' => 'required|min:8|confirmed'
        ]);

        // Verify current password
        $user = $this->db->fetch("SELECT password_hash FROM users WHERE id = ?", [$this->user['id']]);

        if (!password_verify($data['current_password'], $user['password_hash'])) {
            $this->session->flash('error', 'Current password is incorrect.');
            $this->redirect('/areports/profile');
            return;
        }

        // Update password
        $this->db->update('users', [
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT)
        ], ['id' => $this->user['id']]);

        $this->audit('change_password', 'user', $this->user['id']);

        $this->redirectWith('/areports/profile', 'success', 'Password changed successfully.');
    }

    /**
     * Update preferences
     */
    public function updatePreferences(): void
    {
        $preferences = [
            'timezone' => $this->post('timezone', 'UTC'),
            'date_format' => $this->post('date_format', 'd/m/Y'),
            'time_format' => $this->post('time_format', 'H:i:s'),
            'page_size' => (int) $this->post('page_size', 25),
            'theme' => $this->post('theme', 'light')
        ];

        // Check if user_preferences record exists
        $existing = $this->db->fetch("SELECT id FROM user_preferences WHERE user_id = ?", [$this->user['id']]);

        if ($existing) {
            $this->db->update('user_preferences', $preferences, ['user_id' => $this->user['id']]);
        } else {
            $preferences['user_id'] = $this->user['id'];
            $this->db->insert('user_preferences', $preferences);
        }

        $this->redirectWith('/areports/profile', 'success', 'Preferences updated successfully.');
    }
}
