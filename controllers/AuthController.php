<?php
/**
 * Authentication Controller
 * Handles login, logout, and password reset
 */

namespace aReports\Controllers;

use aReports\Core\Controller;

class AuthController extends Controller
{
    /**
     * Show login form
     */
    public function showLogin(): void
    {
        // Redirect if already logged in
        if ($this->app->getAuth()->check()) {
            $user = $this->app->getAuth()->user();
            // Agents go to Agent Panel
            if (($user['role_id'] ?? 0) == 3) {
                $this->redirect('/areports/agent');
            } else {
                $this->redirect('/areports/dashboard');
            }
        }

        $this->view->setLayout('auth');
        $this->render('auth/login', [
            'title' => 'Login'
        ]);
    }

    /**
     * Process login
     */
    public function login(): void
    {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);

        // Validate CSRF
        if (!$this->session->validateCsrfToken($_POST['_csrf_token'] ?? '')) {
            $this->redirectWith('/areports/login', 'error', 'Invalid request. Please try again.');
            return;
        }

        // Validate input
        if (empty($username) || empty($password)) {
            $this->session->flash('error', 'Please enter username and password.');
            $this->session->flash('old', ['username' => $username]);
            $this->redirect('/areports/login');
            return;
        }

        // Check rate limiting
        $lockoutTime = $this->app->getAuth()->getLockoutTime($username);
        if ($lockoutTime > 0) {
            $minutes = ceil($lockoutTime / 60);
            $this->session->flash('error', "Too many failed attempts. Please try again in {$minutes} minutes.");
            $this->session->flash('old', ['username' => $username]);
            $this->redirect('/areports/login');
            return;
        }

        // Attempt login
        if ($this->app->getAuth()->attempt($username, $password, $remember)) {
            // Log successful login
            $this->audit('login', 'user', $this->app->getAuth()->id());

            // Determine default page based on role
            $user = $this->app->getAuth()->user();
            $defaultPage = '/areports/dashboard';

            // Agents go to Agent Panel by default
            if (($user['role_id'] ?? 0) == 3) {
                $defaultPage = '/areports/agent';
            }

            // Redirect to intended URL or role-based default
            $intended = $this->session->get('intended_url', $defaultPage);
            $this->session->remove('intended_url');

            $this->redirect($intended);
        } else {
            $this->session->flash('error', 'Invalid username or password.');
            $this->session->flash('old', ['username' => $username]);
            $this->redirect('/areports/login');
        }
    }

    /**
     * Logout
     */
    public function logout(): void
    {
        if ($this->app->getAuth()->check()) {
            $this->audit('logout', 'user', $this->app->getAuth()->id());
        }

        $this->app->getAuth()->logout();
        $this->redirectWith('/areports/login', 'success', 'You have been logged out successfully.');
    }

    /**
     * Show forgot password form
     */
    public function showForgotPassword(): void
    {
        $this->view->setLayout('auth');
        $this->render('auth/forgot-password', [
            'title' => 'Forgot Password'
        ]);
    }

    /**
     * Process forgot password
     */
    public function forgotPassword(): void
    {
        $email = trim($_POST['email'] ?? '');

        // Validate CSRF
        if (!$this->session->validateCsrfToken($_POST['_csrf_token'] ?? '')) {
            $this->redirectWith('/areports/forgot-password', 'error', 'Invalid request. Please try again.');
            return;
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->session->flash('error', 'Please enter a valid email address.');
            $this->redirect('/areports/forgot-password');
            return;
        }

        $token = $this->app->getAuth()->requestPasswordReset($email);

        // Always show success message to prevent email enumeration
        $this->session->flash('success', 'If an account with that email exists, a password reset link has been sent.');

        if ($token) {
            // In a real application, send email here
            // For now, log the token for testing
            $this->app->logInfo('Password reset requested', [
                'email' => $email,
                'token' => $token,
                'reset_url' => $this->app->baseUrl() . '/reset-password/' . $token
            ]);
        }

        $this->redirect('/areports/login');
    }

    /**
     * Show reset password form
     */
    public function showResetPassword(string $token): void
    {
        $this->view->setLayout('auth');
        $this->render('auth/reset-password', [
            'title' => 'Reset Password',
            'token' => $token
        ]);
    }

    /**
     * Process password reset
     */
    public function resetPassword(): void
    {
        $email = trim($_POST['email'] ?? '');
        $token = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';
        $passwordConfirmation = $_POST['password_confirmation'] ?? '';

        // Validate CSRF
        if (!$this->session->validateCsrfToken($_POST['_csrf_token'] ?? '')) {
            $this->redirectWith('/areports/reset-password/' . $token, 'error', 'Invalid request. Please try again.');
            return;
        }

        // Validate input
        $errors = [];

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }

        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }

        if ($password !== $passwordConfirmation) {
            $errors[] = 'Password confirmation does not match.';
        }

        if (!empty($errors)) {
            foreach ($errors as $error) {
                $this->session->flash('error', $error);
            }
            $this->redirect('/areports/reset-password/' . $token);
            return;
        }

        // Attempt to reset password
        if ($this->app->getAuth()->resetPassword($email, $token, $password)) {
            $this->redirectWith('/areports/login', 'success', 'Your password has been reset. Please login with your new password.');
        } else {
            $this->redirectWith('/areports/reset-password/' . $token, 'error', 'Invalid or expired reset link. Please try again.');
        }
    }
}
