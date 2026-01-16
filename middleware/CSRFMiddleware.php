<?php
/**
 * CSRF Protection Middleware
 * Validates CSRF tokens on POST/PUT/DELETE requests
 */

namespace aReports\Middleware;

use aReports\Core\App;

class CSRFMiddleware
{
    private App $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    /**
     * Handle the middleware
     */
    public function handle(): bool
    {
        // Only validate on state-changing methods
        $method = $_SERVER['REQUEST_METHOD'];

        if (!in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            return true;
        }

        $session = $this->app->getSession();
        $token = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

        if (!$session->validateCsrfToken($token)) {
            http_response_code(419);

            if ($this->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode([
                    'error' => 'CSRF token mismatch',
                    'code' => 419
                ]);
                exit;
            }

            $session->flash('error', 'Your session has expired. Please try again.');

            // Redirect back
            $referer = $_SERVER['HTTP_REFERER'] ?? '/areports/dashboard';
            header('Location: ' . $referer);
            exit;
        }

        return true;
    }

    /**
     * Check if request is AJAX
     */
    private function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}
