<?php
/**
 * Base Controller
 * All controllers extend this class
 */

namespace aReports\Core;

abstract class Controller
{
    protected App $app;
    protected View $view;
    protected ?array $user;
    protected Session $session;
    protected Database $db;
    protected Database $cdrDb;

    public function __construct(App $app)
    {
        $this->app = $app;
        $this->view = new View($app);
        $this->session = $app->getSession();
        $this->user = $app->getAuth()->user();
        $this->db = $app->getDb();
        $this->cdrDb = $app->getCdrDb();
    }

    /**
     * Render a view
     */
    protected function render(string $template, array $data = []): void
    {
        $this->view->render($template, $data);
    }

    /**
     * Render without layout
     */
    protected function renderPartial(string $template, array $data = []): void
    {
        $this->view->withoutLayout()->render($template, $data);
    }

    /**
     * Return JSON response
     */
    protected function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Redirect to URL
     */
    protected function redirect(string $url, int $status = 302): void
    {
        http_response_code($status);
        header('Location: ' . $url);
        exit;
    }

    /**
     * Redirect back to previous page
     */
    protected function back(): void
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/areports';
        $this->redirect($referer);
    }

    /**
     * Redirect with flash message
     */
    protected function redirectWith(string $url, string $type, string $message): void
    {
        $this->session->flash($type, $message);
        $this->redirect($url);
    }

    /**
     * Check if user has permission, abort if not
     */
    protected function requirePermission(string $permission): void
    {
        if (!$this->app->getAuth()->can($permission)) {
            $this->abort(403, 'Access Denied');
        }
    }

    /**
     * Check if user has any of the permissions
     */
    protected function requireAnyPermission(array $permissions): void
    {
        foreach ($permissions as $permission) {
            if ($this->app->getAuth()->can($permission)) {
                return;
            }
        }
        $this->abort(403, 'Access Denied');
    }

    /**
     * Abort with error
     */
    protected function abort(int $code, string $message = ''): void
    {
        http_response_code($code);

        if ($this->isAjax()) {
            $this->json(['error' => $message, 'code' => $code], $code);
        }

        $view = match ($code) {
            403 => 'errors/403',
            404 => 'errors/404',
            500 => 'errors/500',
            default => 'errors/generic'
        };

        $this->view->render($view, [
            'title' => 'Error ' . $code,
            'code' => $code,
            'message' => $message
        ]);
        exit;
    }

    /**
     * Validate request data
     */
    protected function validate(array $data, array $rules): array
    {
        $errors = [];
        $validated = [];

        foreach ($rules as $field => $ruleSet) {
            $fieldRules = is_string($ruleSet) ? explode('|', $ruleSet) : $ruleSet;
            $value = $data[$field] ?? null;

            foreach ($fieldRules as $rule) {
                $params = [];

                if (str_contains($rule, ':')) {
                    [$rule, $paramStr] = explode(':', $rule, 2);
                    $params = explode(',', $paramStr);
                }

                $result = $this->validateRule($field, $value, $rule, $params, $data);

                if ($result !== true) {
                    $errors[$field][] = $result;
                }
            }

            if (!isset($errors[$field])) {
                $validated[$field] = $value;
            }
        }

        if (!empty($errors)) {
            if ($this->isAjax()) {
                $this->json(['errors' => $errors], 422);
            }

            $this->session->flash('errors', $errors);
            $this->session->flash('old', $data);
            $this->back();
        }

        return $validated;
    }

    /**
     * Validate a single rule
     */
    private function validateRule(string $field, mixed $value, string $rule, array $params, array $data): true|string
    {
        $label = ucfirst(str_replace('_', ' ', $field));

        return match ($rule) {
            'required' => !empty($value) || $value === '0' ? true : "{$label} is required",

            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) ? true : "{$label} must be a valid email",

            'min' => strlen($value) >= (int)$params[0] ? true : "{$label} must be at least {$params[0]} characters",

            'max' => strlen($value) <= (int)$params[0] ? true : "{$label} must be at most {$params[0]} characters",

            'numeric' => is_numeric($value) ? true : "{$label} must be a number",

            'integer' => filter_var($value, FILTER_VALIDATE_INT) !== false ? true : "{$label} must be an integer",

            'confirmed' => $value === ($data[$field . '_confirmation'] ?? null) ? true : "{$label} confirmation does not match",

            'unique' => $this->validateUnique($field, $value, $params) ? true : "{$label} already exists",

            'exists' => $this->validateExists($field, $value, $params) ? true : "{$label} does not exist",

            'in' => in_array($value, $params) ? true : "{$label} must be one of: " . implode(', ', $params),

            'date' => strtotime($value) !== false ? true : "{$label} must be a valid date",

            'alpha' => ctype_alpha($value) ? true : "{$label} must contain only letters",

            'alphanumeric' => ctype_alnum($value) ? true : "{$label} must contain only letters and numbers",

            'regex' => preg_match($params[0], $value) ? true : "{$label} format is invalid",

            default => true
        };
    }

    /**
     * Validate unique value in database
     */
    private function validateUnique(string $field, mixed $value, array $params): bool
    {
        $table = $params[0] ?? '';
        $column = $params[1] ?? $field;
        $exceptId = $params[2] ?? null;

        $sql = "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = ?";
        $bindings = [$value];

        if ($exceptId) {
            $sql .= " AND id != ?";
            $bindings[] = $exceptId;
        }

        return (int)$this->db->fetchColumn($sql, $bindings) === 0;
    }

    /**
     * Validate value exists in database
     */
    private function validateExists(string $field, mixed $value, array $params): bool
    {
        $table = $params[0] ?? '';
        $column = $params[1] ?? $field;

        $count = $this->db->count($table, [$column => $value]);
        return $count > 0;
    }

    /**
     * Check if request is AJAX
     */
    protected function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Check if request is POST
     */
    protected function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    /**
     * Get request input
     */
    protected function input(string $key = null, mixed $default = null): mixed
    {
        $data = array_merge($_GET, $_POST);

        if ($key === null) {
            return $data;
        }

        return $data[$key] ?? $default;
    }

    /**
     * Get POST data
     */
    protected function post(string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $_POST;
        }

        return $_POST[$key] ?? $default;
    }

    /**
     * Get GET data
     */
    protected function get(string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $_GET;
        }

        return $_GET[$key] ?? $default;
    }

    /**
     * Get old input value (after validation failure)
     */
    protected function old(string $key, mixed $default = null): mixed
    {
        $old = $this->session->getFlash('old', []);
        return $old[$key] ?? $default;
    }

    /**
     * Get validation errors
     */
    protected function errors(): array
    {
        return $this->session->getFlash('errors', []);
    }

    /**
     * Get uploaded file
     */
    protected function file(string $key): ?array
    {
        return $_FILES[$key] ?? null;
    }

    /**
     * Log to audit log
     */
    protected function audit(string $action, string $entityType, ?int $entityId = null, ?array $oldValues = null, ?array $newValues = null): void
    {
        $this->db->insert('audit_log', [
            'user_id' => $this->user['id'] ?? null,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $oldValues ? json_encode($oldValues) : null,
            'new_values' => $newValues ? json_encode($newValues) : null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }

    /**
     * Get setting value
     */
    protected function setting(string $key, mixed $default = null): mixed
    {
        return $this->app->getSetting($key, $default);
    }
}
