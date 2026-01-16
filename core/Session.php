<?php
/**
 * Session Management
 * Handles PHP sessions with security features
 */

namespace aReports\Core;

class Session
{
    private bool $started = false;
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'lifetime' => 7200,
            'path' => '/',
            'domain' => '',
            'secure' => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax'
        ], $config);
    }

    /**
     * Start the session
     */
    public function start(): bool
    {
        if ($this->started) {
            return true;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;
            return true;
        }

        // Configure session cookie
        session_set_cookie_params([
            'lifetime' => $this->config['lifetime'],
            'path' => $this->config['path'],
            'domain' => $this->config['domain'],
            'secure' => $this->config['secure'],
            'httponly' => $this->config['httponly'],
            'samesite' => $this->config['samesite']
        ]);

        session_name('AREPORTS_SESSION');

        $this->started = session_start();

        // Regenerate session ID periodically
        if ($this->started && !$this->has('_created')) {
            $this->set('_created', time());
        } elseif ($this->started && $this->get('_created') < time() - 1800) {
            $this->regenerate();
            $this->set('_created', time());
        }

        return $this->started;
    }

    /**
     * Regenerate session ID
     */
    public function regenerate(bool $deleteOldSession = true): bool
    {
        return session_regenerate_id($deleteOldSession);
    }

    /**
     * Get a session value
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Set a session value
     */
    public function set(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    /**
     * Check if a session key exists
     */
    public function has(string $key): bool
    {
        $this->start();
        return isset($_SESSION[$key]);
    }

    /**
     * Remove a session key
     */
    public function remove(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    /**
     * Get all session data
     */
    public function all(): array
    {
        $this->start();
        return $_SESSION;
    }

    /**
     * Clear all session data
     */
    public function clear(): void
    {
        $this->start();
        $_SESSION = [];
    }

    /**
     * Destroy the session
     */
    public function destroy(): bool
    {
        $this->start();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        $this->started = false;
        return session_destroy();
    }

    /**
     * Get session ID
     */
    public function getId(): string
    {
        return session_id();
    }

    /**
     * Set a flash message (available only for next request)
     */
    public function flash(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION['_flash'][$key] = $value;
    }

    /**
     * Get and remove a flash message
     */
    public function getFlash(string $key, mixed $default = null): mixed
    {
        $this->start();
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }

    /**
     * Check if a flash message exists
     */
    public function hasFlash(string $key): bool
    {
        $this->start();
        return isset($_SESSION['_flash'][$key]);
    }

    /**
     * Get all flash messages
     */
    public function getAllFlash(): array
    {
        $this->start();
        $flash = $_SESSION['_flash'] ?? [];
        $_SESSION['_flash'] = [];
        return $flash;
    }

    /**
     * Set CSRF token
     */
    public function setCsrfToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->set('_csrf_token', $token);
        return $token;
    }

    /**
     * Get CSRF token
     */
    public function getCsrfToken(): string
    {
        if (!$this->has('_csrf_token')) {
            return $this->setCsrfToken();
        }
        return $this->get('_csrf_token');
    }

    /**
     * Validate CSRF token
     */
    public function validateCsrfToken(string $token): bool
    {
        return hash_equals($this->getCsrfToken(), $token);
    }
}
