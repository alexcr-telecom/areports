<?php
/**
 * Authentication Middleware
 * Ensures user is logged in
 */

namespace aReports\Middleware;

use aReports\Core\App;

class AuthMiddleware
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

        if (!$auth->check()) {
            // Store intended URL for redirect after login
            $this->app->getSession()->set('intended_url', $_SERVER['REQUEST_URI']);

            // Redirect to login
            header('Location: /areports/login');
            exit;
        }

        return true;
    }
}
