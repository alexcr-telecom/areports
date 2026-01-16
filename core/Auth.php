<?php
/**
 * Authentication Handler
 * Manages user authentication and authorization
 */

namespace aReports\Core;

class Auth
{
    private Session $session;
    private Database $db;
    private ?array $user = null;
    private array $permissions = [];
    private bool $loaded = false;

    public function __construct(Session $session, Database $db)
    {
        $this->session = $session;
        $this->db = $db;
    }

    /**
     * Attempt to authenticate a user
     */
    public function attempt(string $username, string $password, bool $remember = false): bool
    {
        // Check for rate limiting
        if ($this->isRateLimited($username)) {
            return false;
        }

        // Find user by username or email
        $sql = "SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1";
        $user = $this->db->fetch($sql, [$username, $username]);

        if (!$user) {
            $this->logAttempt($username, false);
            return false;
        }

        if (!password_verify($password, $user['password_hash'])) {
            $this->logAttempt($username, false);
            return false;
        }

        // Log successful attempt
        $this->logAttempt($username, true);

        $this->login($user);

        return true;
    }

    /**
     * Log the user in
     */
    public function login(array $user): void
    {
        // Regenerate session ID
        $this->session->regenerate();

        // Store user ID in session
        $this->session->set('user_id', $user['id']);
        $this->session->set('login_time', time());

        // Update last login
        $this->db->update('users', [
            'last_login' => date('Y-m-d H:i:s')
        ], ['id' => $user['id']]);

        // Clear cached user
        $this->user = null;
        $this->loaded = false;
    }

    /**
     * Log the user out
     */
    public function logout(): void
    {
        $this->session->destroy();
        $this->user = null;
        $this->loaded = false;
        $this->permissions = [];
    }

    /**
     * Check if user is authenticated
     */
    public function check(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Check if user is a guest
     */
    public function guest(): bool
    {
        return !$this->check();
    }

    /**
     * Get the authenticated user
     */
    public function user(): ?array
    {
        if ($this->loaded) {
            return $this->user;
        }

        $userId = $this->session->get('user_id');

        if (!$userId) {
            $this->loaded = true;
            return null;
        }

        // Check session timeout
        $loginTime = $this->session->get('login_time', 0);
        $timeout = 7200; // 2 hours

        if (time() - $loginTime > $timeout) {
            $this->logout();
            return null;
        }

        // Load user from database
        $sql = "SELECT u.*, r.name as role_name, r.display_name as role_display_name
                FROM users u
                JOIN roles r ON u.role_id = r.id
                WHERE u.id = ? AND u.is_active = 1";

        $this->user = $this->db->fetch($sql, [$userId]);

        if (!$this->user) {
            $this->logout();
            return null;
        }

        // Remove sensitive data
        unset($this->user['password_hash']);

        // Load permissions
        $this->loadPermissions();

        $this->loaded = true;

        return $this->user;
    }

    /**
     * Get user ID
     */
    public function id(): ?int
    {
        $user = $this->user();
        return $user['id'] ?? null;
    }

    /**
     * Load user permissions
     */
    private function loadPermissions(): void
    {
        if (!$this->user) {
            $this->permissions = [];
            return;
        }

        $sql = "SELECT p.name
                FROM permissions p
                JOIN role_permissions rp ON p.id = rp.permission_id
                WHERE rp.role_id = ?";

        $results = $this->db->fetchAll($sql, [$this->user['role_id']]);
        $this->permissions = array_column($results, 'name');
    }

    /**
     * Check if user has a permission
     */
    public function can(string $permission): bool
    {
        $this->user(); // Ensure user and permissions are loaded

        if (empty($this->permissions)) {
            return false;
        }

        return in_array($permission, $this->permissions);
    }

    /**
     * Check if user has any of the permissions
     */
    public function canAny(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->can($permission)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if user has all of the permissions
     */
    public function canAll(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (!$this->can($permission)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check if user has a role
     */
    public function hasRole(string $role): bool
    {
        $user = $this->user();
        return $user && $user['role_name'] === $role;
    }

    /**
     * Check if user has any of the roles
     */
    public function hasAnyRole(array $roles): bool
    {
        $user = $this->user();
        return $user && in_array($user['role_name'], $roles);
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * Check if user is supervisor
     */
    public function isSupervisor(): bool
    {
        return $this->hasRole('supervisor');
    }

    /**
     * Check if user is agent
     */
    public function isAgent(): bool
    {
        return $this->hasRole('agent');
    }

    /**
     * Get all user permissions
     */
    public function permissions(): array
    {
        $this->user();
        return $this->permissions;
    }

    /**
     * Check if login attempts are rate limited
     */
    private function isRateLimited(string $username): bool
    {
        $maxAttempts = 5;
        $lockoutDuration = 900; // 15 minutes

        $sql = "SELECT COUNT(*) FROM login_attempts
                WHERE username = ?
                AND success = 0
                AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)";

        $attempts = (int)$this->db->fetchColumn($sql, [$username, $lockoutDuration]);

        return $attempts >= $maxAttempts;
    }

    /**
     * Log a login attempt
     */
    private function logAttempt(string $username, bool $success): void
    {
        $this->db->insert('login_attempts', [
            'username' => $username,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'success' => $success ? 1 : 0
        ]);
    }

    /**
     * Get remaining lockout time
     */
    public function getLockoutTime(string $username): int
    {
        $lockoutDuration = 900;

        $sql = "SELECT MAX(attempted_at) as last_attempt FROM login_attempts
                WHERE username = ? AND success = 0";

        $result = $this->db->fetch($sql, [$username]);

        if (!$result || !$result['last_attempt']) {
            return 0;
        }

        $lastAttempt = strtotime($result['last_attempt']);
        $unlockTime = $lastAttempt + $lockoutDuration;

        return max(0, $unlockTime - time());
    }

    /**
     * Request password reset
     */
    public function requestPasswordReset(string $email): ?string
    {
        $sql = "SELECT id, email FROM users WHERE email = ? AND is_active = 1";
        $user = $this->db->fetch($sql, [$email]);

        if (!$user) {
            return null;
        }

        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $this->db->update('users', [
            'password_reset_token' => password_hash($token, PASSWORD_DEFAULT),
            'password_reset_expires' => $expires
        ], ['id' => $user['id']]);

        return $token;
    }

    /**
     * Reset password with token
     */
    public function resetPassword(string $email, string $token, string $newPassword): bool
    {
        $sql = "SELECT id, password_reset_token, password_reset_expires
                FROM users
                WHERE email = ? AND is_active = 1 AND password_reset_expires > NOW()";

        $user = $this->db->fetch($sql, [$email]);

        if (!$user || !password_verify($token, $user['password_reset_token'])) {
            return false;
        }

        $this->db->update('users', [
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            'password_reset_token' => null,
            'password_reset_expires' => null
        ], ['id' => $user['id']]);

        return true;
    }

    /**
     * Change password for current user
     */
    public function changePassword(string $currentPassword, string $newPassword): bool
    {
        $userId = $this->id();

        if (!$userId) {
            return false;
        }

        $sql = "SELECT password_hash FROM users WHERE id = ?";
        $user = $this->db->fetch($sql, [$userId]);

        if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
            return false;
        }

        $this->db->update('users', [
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT)
        ], ['id' => $userId]);

        return true;
    }
}
