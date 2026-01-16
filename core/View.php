<?php
/**
 * View Renderer
 * Handles template rendering with layouts
 */

namespace aReports\Core;

class View
{
    private App $app;
    private string $viewsPath;
    private ?string $layout = 'main';
    private array $sections = [];
    private ?string $currentSection = null;
    private array $shared = [];

    public function __construct(App $app)
    {
        $this->app = $app;
        $this->viewsPath = dirname(__DIR__) . '/views';

        // Share common data with all views
        $this->shared = [
            'app' => $app,
            'auth' => $app->getAuth(),
            'session' => $app->getSession(),
            'user' => $app->getAuth()->user(),
            'csrfToken' => $app->getSession()->getCsrfToken()
        ];
    }

    /**
     * Render a view
     */
    public function render(string $view, array $data = []): void
    {
        $data = array_merge($this->shared, $data);

        // Extract data to variables
        extract($data);

        // Start output buffering
        ob_start();

        // Include the view file
        $viewFile = $this->viewsPath . '/' . str_replace('.', '/', $view) . '.php';

        if (!file_exists($viewFile)) {
            throw new \RuntimeException("View not found: {$view}");
        }

        include $viewFile;

        $content = ob_get_clean();

        // If layout is set, wrap content in layout
        if ($this->layout) {
            // Only set content section if not already defined by view
            if (!isset($this->sections['content']) || empty($this->sections['content'])) {
                $this->sections['content'] = $content;
            }
            $layoutFile = $this->viewsPath . '/layouts/' . $this->layout . '.php';

            if (!file_exists($layoutFile)) {
                throw new \RuntimeException("Layout not found: {$this->layout}");
            }

            ob_start();
            include $layoutFile;
            $content = ob_get_clean();
        }

        echo $content;
    }

    /**
     * Render a view and return as string
     */
    public function renderToString(string $view, array $data = []): string
    {
        ob_start();
        $this->render($view, $data);
        return ob_get_clean();
    }

    /**
     * Set the layout
     */
    public function setLayout(?string $layout): self
    {
        $this->layout = $layout;
        return $this;
    }

    /**
     * Disable layout
     */
    public function withoutLayout(): self
    {
        $this->layout = null;
        return $this;
    }

    /**
     * Start a section
     */
    public function section(string $name): void
    {
        $this->currentSection = $name;
        ob_start();
    }

    /**
     * End a section
     */
    public function endSection(): void
    {
        if ($this->currentSection) {
            $this->sections[$this->currentSection] = ob_get_clean();
            $this->currentSection = null;
        }
    }

    /**
     * Output a section
     */
    public function yield(string $name, string $default = ''): void
    {
        echo $this->sections[$name] ?? $default;
    }

    /**
     * Check if a section exists
     */
    public function hasSection(string $name): bool
    {
        return isset($this->sections[$name]);
    }

    /**
     * Include a partial view
     */
    public function partial(string $view, array $data = []): void
    {
        $data = array_merge($this->shared, $data);
        extract($data);

        $viewFile = $this->viewsPath . '/' . str_replace('.', '/', $view) . '.php';

        if (!file_exists($viewFile)) {
            throw new \RuntimeException("Partial not found: {$view}");
        }

        include $viewFile;
    }

    /**
     * Escape HTML
     */
    public function e(?string $string): string
    {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }

    /**
     * Generate CSRF field
     */
    public function csrf(): string
    {
        return '<input type="hidden" name="_csrf_token" value="' . $this->e($this->shared['csrfToken']) . '">';
    }

    /**
     * Generate method field for PUT/DELETE
     */
    public function method(string $method): string
    {
        return '<input type="hidden" name="_method" value="' . $this->e(strtoupper($method)) . '">';
    }

    /**
     * Check if user has permission
     */
    public function can(string $permission): bool
    {
        return $this->app->getAuth()->can($permission);
    }

    /**
     * Format date
     */
    public function formatDate(?string $date, string $format = 'd/m/Y'): string
    {
        if (!$date) {
            return '';
        }
        return date($format, strtotime($date));
    }

    /**
     * Format datetime
     */
    public function formatDateTime(?string $date, string $format = 'd/m/Y H:i:s'): string
    {
        if (!$date) {
            return '';
        }
        return date($format, strtotime($date));
    }

    /**
     * Format duration in seconds to HH:MM:SS
     */
    public function formatDuration(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
        }

        return sprintf('%d:%02d', $minutes, $secs);
    }

    /**
     * Generate pagination
     */
    public function pagination(int $currentPage, int $totalPages, string $baseUrl, array $params = []): string
    {
        if ($totalPages <= 1) {
            return '';
        }

        $html = '<nav><ul class="pagination justify-content-center">';

        // Previous button
        if ($currentPage > 1) {
            $params['page'] = $currentPage - 1;
            $url = $baseUrl . '?' . http_build_query($params);
            $html .= '<li class="page-item"><a class="page-link" href="' . $this->e($url) . '">&laquo;</a></li>';
        } else {
            $html .= '<li class="page-item disabled"><span class="page-link">&laquo;</span></li>';
        }

        // Page numbers
        $start = max(1, $currentPage - 2);
        $end = min($totalPages, $currentPage + 2);

        if ($start > 1) {
            $params['page'] = 1;
            $url = $baseUrl . '?' . http_build_query($params);
            $html .= '<li class="page-item"><a class="page-link" href="' . $this->e($url) . '">1</a></li>';
            if ($start > 2) {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }

        for ($i = $start; $i <= $end; $i++) {
            $params['page'] = $i;
            $url = $baseUrl . '?' . http_build_query($params);
            $activeClass = $i === $currentPage ? ' active' : '';
            $html .= '<li class="page-item' . $activeClass . '"><a class="page-link" href="' . $this->e($url) . '">' . $i . '</a></li>';
        }

        if ($end < $totalPages) {
            if ($end < $totalPages - 1) {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            $params['page'] = $totalPages;
            $url = $baseUrl . '?' . http_build_query($params);
            $html .= '<li class="page-item"><a class="page-link" href="' . $this->e($url) . '">' . $totalPages . '</a></li>';
        }

        // Next button
        if ($currentPage < $totalPages) {
            $params['page'] = $currentPage + 1;
            $url = $baseUrl . '?' . http_build_query($params);
            $html .= '<li class="page-item"><a class="page-link" href="' . $this->e($url) . '">&raquo;</a></li>';
        } else {
            $html .= '<li class="page-item disabled"><span class="page-link">&raquo;</span></li>';
        }

        $html .= '</ul></nav>';

        return $html;
    }

    /**
     * Share data with all views
     */
    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    /**
     * Get shared data
     */
    public function getShared(string $key): mixed
    {
        return $this->shared[$key] ?? null;
    }

    /**
     * Get old form input value (for form repopulation after validation errors)
     */
    public function old(string $key, mixed $default = ''): mixed
    {
        return $this->shared['session']->getFlash('old_input')[$key] ?? $default;
    }

    /**
     * Check if there are validation errors
     */
    public function hasErrors(): bool
    {
        return !empty($this->shared['session']->getFlash('errors'));
    }

    /**
     * Get validation errors
     */
    public function errors(?string $key = null): mixed
    {
        $errors = $this->shared['session']->getFlash('errors') ?? [];
        if ($key) {
            return $errors[$key] ?? null;
        }
        return $errors;
    }

    /**
     * Get error message for a field
     */
    public function error(string $key): string
    {
        $errors = $this->shared['session']->getFlash('errors') ?? [];
        return $errors[$key] ?? '';
    }
}
