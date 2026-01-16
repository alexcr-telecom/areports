<?php
/**
 * aReports - Asterisk Reports & Analytics
 * Application Entry Point
 */

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Define base path
define('BASE_PATH', __DIR__);

// Autoloader
spl_autoload_register(function ($class) {
    // Convert namespace to file path
    $prefix = 'aReports\\';
    $baseDir = __DIR__ . '/';

    // Check if the class uses the namespace prefix
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    // Get the relative class name
    $relativeClass = substr($class, $len);

    // Convert namespace separators to directory separators
    $file = $baseDir . str_replace('\\', '/', lcfirst($relativeClass)) . '.php';

    // Handle different directory mappings
    $mappings = [
        'Core\\' => 'core/',
        'Controllers\\' => 'controllers/',
        'Models\\' => 'models/',
        'Services\\' => 'services/',
        'Middleware\\' => 'middleware/',
    ];

    foreach ($mappings as $nsPrefix => $dir) {
        if (strncmp($nsPrefix, $relativeClass, strlen($nsPrefix)) === 0) {
            $classFile = substr($relativeClass, strlen($nsPrefix));
            $file = $baseDir . $dir . str_replace('\\', '/', $classFile) . '.php';
            break;
        }
    }

    // Require the file if it exists
    if (file_exists($file)) {
        require $file;
    }
});

// Run the application
try {
    $app = \aReports\Core\App::getInstance();
    $app->run();
} catch (\Exception $e) {
    // Log the error
    error_log('aReports Error: ' . $e->getMessage());

    // Show error page in debug mode, generic message otherwise
    if (isset($app) && $app->isDebug()) {
        echo '<h1>Application Error</h1>';
        echo '<p><strong>Message:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<p><strong>File:</strong> ' . htmlspecialchars($e->getFile()) . ':' . $e->getLine() . '</p>';
        echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    } else {
        http_response_code(500);
        echo '<h1>Internal Server Error</h1>';
        echo '<p>An error occurred. Please try again later.</p>';
    }
}
