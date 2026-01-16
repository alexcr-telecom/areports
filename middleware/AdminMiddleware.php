<?php
/**
 * Admin Middleware
 * Ensures user has admin role
 */

namespace aReports\Middleware;

use aReports\Core\App;

class AdminMiddleware
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
        $auth = $this->app->getAuth();

        // First check if authenticated
        if (!$auth->check()) {
            header('Location: /areports/login');
            exit;
        }

        // Check for admin or specific admin permissions
        if (!$auth->isAdmin() && !$auth->can('admin.users.view')) {
            http_response_code(403);

            if ($this->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Access Denied', 'code' => 403]);
                exit;
            }

            // Redirect to dashboard with error message
            $this->app->getSession()->flash('error', 'You do not have permission to access the admin area.');
            header('Location: /areports/dashboard');
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
