#!/usr/bin/env php
<?php
/**
 * Create Admin User CLI Script
 *
 * Usage: php create_admin.php [username] [email] [password]
 *
 * If no arguments provided, will prompt for input.
 */

require_once dirname(__DIR__) . '/core/App.php';

use aReports\Core\App;

class CreateAdmin
{
    private App $app;

    public function __construct()
    {
        $this->app = App::getInstance();
    }

    public function run(array $argv): void
    {
        echo "\n";
        echo "=================================\n";
        echo "  aReports - Create Admin User\n";
        echo "=================================\n\n";

        // Get credentials from arguments or prompt
        if (isset($argv[1], $argv[2], $argv[3])) {
            $username = $argv[1];
            $email = $argv[2];
            $password = $argv[3];
            $firstName = $argv[4] ?? 'Admin';
            $lastName = $argv[5] ?? 'User';
        } else {
            $username = $this->prompt('Username', 'admin');
            $email = $this->prompt('Email');
            $password = $this->promptPassword('Password');
            $firstName = $this->prompt('First Name', 'Admin');
            $lastName = $this->prompt('Last Name', 'User');
        }

        // Validate
        if (empty($username) || empty($email) || empty($password)) {
            $this->error("All fields are required.");
            exit(1);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("Invalid email address.");
            exit(1);
        }

        if (strlen($password) < 6) {
            $this->error("Password must be at least 6 characters.");
            exit(1);
        }

        try {
            $db = $this->app->getDb();

            // Check if user exists
            $existing = $db->fetch(
                "SELECT id FROM users WHERE username = ? OR email = ?",
                [$username, $email]
            );

            if ($existing) {
                $this->error("User with this username or email already exists.");
                exit(1);
            }

            // Get or create admin role
            $role = $db->fetch("SELECT id FROM roles WHERE name = 'admin'");

            if (!$role) {
                $roleId = $db->insert('roles', [
                    'name' => 'admin',
                    'display_name' => 'Administrator',
                    'description' => 'Full system access',
                    'is_system' => 1
                ]);
            } else {
                $roleId = $role['id'];
            }

            // Create user
            $userId = $db->insert('users', [
                'username' => $username,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'role_id' => $roleId,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            $this->success("Admin user created successfully!");
            echo "\n";
            echo "  Username: $username\n";
            echo "  Email:    $email\n";
            echo "  User ID:  $userId\n";
            echo "\n";
            echo "You can now login at: http://your-server/areports\n\n";

        } catch (\Exception $e) {
            $this->error("Failed to create user: " . $e->getMessage());
            exit(1);
        }
    }

    private function prompt(string $label, string $default = ''): string
    {
        $defaultStr = $default ? " [$default]" : '';
        echo "$label$defaultStr: ";
        $input = trim(fgets(STDIN));
        return $input ?: $default;
    }

    private function promptPassword(string $label): string
    {
        // Try to hide password input on Unix systems
        if (strncasecmp(PHP_OS, 'WIN', 3) !== 0) {
            echo "$label: ";
            system('stty -echo');
            $password = trim(fgets(STDIN));
            system('stty echo');
            echo "\n";

            echo "Confirm $label: ";
            system('stty -echo');
            $confirm = trim(fgets(STDIN));
            system('stty echo');
            echo "\n";
        } else {
            echo "$label: ";
            $password = trim(fgets(STDIN));
            echo "Confirm $label: ";
            $confirm = trim(fgets(STDIN));
        }

        if ($password !== $confirm) {
            $this->error("Passwords do not match.");
            exit(1);
        }

        return $password;
    }

    private function success(string $message): void
    {
        echo "\033[32m[SUCCESS]\033[0m $message\n";
    }

    private function error(string $message): void
    {
        echo "\033[31m[ERROR]\033[0m $message\n";
    }
}

// Run
$admin = new CreateAdmin();
$admin->run($argv);
