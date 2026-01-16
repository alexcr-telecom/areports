<?php
/**
 * API v1 Entry Point
 * RESTful API for external integrations
 */

// Set headers for API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Load autoloader and bootstrap
require_once dirname(dirname(__DIR__)) . '/core/App.php';

use aReports\Core\App;

// Initialize app
$app = App::getInstance();

// API Router
$requestUri = $_SERVER['REQUEST_URI'];
$basePath = '/areports/api/v1';
$path = str_replace($basePath, '', parse_url($requestUri, PHP_URL_PATH));
$method = $_SERVER['REQUEST_METHOD'];

// Authenticate API request
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? null;
$authenticated = authenticateApiKey($app, $apiKey);

// Public endpoints (no auth required)
$publicEndpoints = ['/health', '/version'];

if (!in_array($path, $publicEndpoints) && !$authenticated) {
    jsonResponse(['error' => 'Unauthorized', 'message' => 'Invalid or missing API key'], 401);
}

// Route the request
try {
    $response = routeRequest($app, $method, $path);
    jsonResponse($response);
} catch (Exception $e) {
    jsonResponse(['error' => 'Server Error', 'message' => $e->getMessage()], 500);
}

/**
 * Authenticate API key
 */
function authenticateApiKey(App $app, ?string $apiKey): bool
{
    if (empty($apiKey)) {
        return false;
    }

    $db = $app->getDb();
    $key = $db->fetch(
        "SELECT * FROM api_keys WHERE api_key = ? AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW())",
        [$apiKey]
    );

    if ($key) {
        // Update last used
        $db->update('api_keys', ['last_used_at' => date('Y-m-d H:i:s')], ['id' => $key['id']]);
        return true;
    }

    return false;
}

/**
 * Route API request
 */
function routeRequest(App $app, string $method, string $path): array
{
    // Remove trailing slash
    $path = rtrim($path, '/') ?: '/';

    // Parse path segments
    $segments = array_values(array_filter(explode('/', $path)));

    // Health check
    if ($path === '/health') {
        return ['status' => 'ok', 'timestamp' => date('c')];
    }

    // Version
    if ($path === '/version') {
        return [
            'name' => 'aReports API',
            'version' => '1.0.0',
            'api_version' => 'v1'
        ];
    }

    // Route based on first segment
    $resource = $segments[0] ?? '';
    $id = $segments[1] ?? null;
    $action = $segments[2] ?? null;

    return match ($resource) {
        'queues' => handleQueues($app, $method, $id, $action),
        'agents' => handleAgents($app, $method, $id, $action),
        'calls' => handleCalls($app, $method, $id, $action),
        'cdr' => handleCdr($app, $method, $id, $action),
        'stats' => handleStats($app, $method, $id),
        'alerts' => handleAlerts($app, $method, $id, $action),
        'reports' => handleReports($app, $method, $id),
        'realtime' => handleRealtime($app, $method, $id),
        default => ['error' => 'Not Found', 'message' => "Unknown resource: {$resource}"]
    };
}

/**
 * Handle Queues API
 */
function handleQueues(App $app, string $method, ?string $id, ?string $action): array
{
    $queueService = new \aReports\Services\QueueService();
    $amiService = new \aReports\Services\AMIService();

    if ($method === 'GET') {
        if ($id === null) {
            // List all queues with real-time status
            $queues = $amiService->getQueueStatus();
            return ['data' => $queues, 'count' => count($queues)];
        }

        if ($action === 'stats') {
            // Queue statistics
            $dateFrom = $_GET['from'] ?? date('Y-m-d');
            $dateTo = $_GET['to'] ?? date('Y-m-d');
            $stats = $queueService->getQueueSummary($id, $dateFrom, $dateTo);
            return ['data' => $stats];
        }

        if ($action === 'members') {
            // Queue members
            $status = $amiService->getQueueStatus($id);
            return ['data' => $status[0]['members'] ?? []];
        }

        // Single queue status
        $status = $amiService->getQueueStatus($id);
        return ['data' => $status[0] ?? null];
    }

    return ['error' => 'Method not allowed'];
}

/**
 * Handle Agents API
 */
function handleAgents(App $app, string $method, ?string $id, ?string $action): array
{
    $agentService = new \aReports\Services\AgentService();
    $amiService = new \aReports\Services\AMIService();

    if ($method === 'GET') {
        if ($id === null) {
            // List all agents
            $queues = $amiService->getQueueStatus();
            $agents = [];

            foreach ($queues as $queue) {
                foreach ($queue['members'] as $member) {
                    $key = $member['interface'];
                    if (!isset($agents[$key])) {
                        $agents[$key] = $member;
                        $agents[$key]['queues'] = [];
                    }
                    $agents[$key]['queues'][] = $queue['name'];
                }
            }

            return ['data' => array_values($agents), 'count' => count($agents)];
        }

        if ($action === 'stats') {
            // Agent statistics
            $dateFrom = $_GET['from'] ?? date('Y-m-d');
            $dateTo = $_GET['to'] ?? date('Y-m-d');
            $stats = $agentService->getAgentPerformance($id, $dateFrom, $dateTo);
            return ['data' => $stats];
        }

        if ($action === 'activity') {
            // Agent activity timeline
            $dateFrom = $_GET['from'] ?? date('Y-m-d');
            $dateTo = $_GET['to'] ?? date('Y-m-d');
            $activity = $agentService->getAgentActivity($id, $dateFrom, $dateTo);
            return ['data' => $activity];
        }

        // Single agent info
        $db = $app->getDb();
        $agent = $db->fetch(
            "SELECT * FROM agent_settings WHERE extension = ?",
            [$id]
        );
        return ['data' => $agent];
    }

    if ($method === 'POST' && $action) {
        // Agent actions (login, logout, pause, unpause)
        $queue = $_POST['queue'] ?? '*';

        switch ($action) {
            case 'login':
                $result = $amiService->queueAddMember($queue, "Local/{$id}@from-queue/n");
                return ['success' => $result['success'], 'message' => $result['message']];

            case 'logout':
                $result = $amiService->queueRemoveMember($queue, "Local/{$id}@from-queue/n");
                return ['success' => $result['success'], 'message' => $result['message']];

            case 'pause':
                $reason = $_POST['reason'] ?? '';
                $result = $amiService->queuePauseMember($queue, "Local/{$id}@from-queue/n", true, $reason);
                return ['success' => $result['success'], 'message' => $result['message']];

            case 'unpause':
                $result = $amiService->queuePauseMember($queue, "Local/{$id}@from-queue/n", false);
                return ['success' => $result['success'], 'message' => $result['message']];
        }
    }

    return ['error' => 'Method not allowed'];
}

/**
 * Handle Active Calls API
 */
function handleCalls(App $app, string $method, ?string $id, ?string $action): array
{
    $amiService = new \aReports\Services\AMIService();

    if ($method === 'GET') {
        if ($id === null) {
            // List active calls
            $channels = $amiService->getActiveChannels();
            return ['data' => $channels, 'count' => count($channels)];
        }

        // Single call by uniqueid
        $channels = $amiService->getActiveChannels();
        $call = array_filter($channels, fn($c) => $c['uniqueid'] === $id);
        return ['data' => array_values($call)[0] ?? null];
    }

    if ($method === 'POST' && $action === 'hangup' && $id) {
        // Hangup a call
        $result = $amiService->hangup($id);
        return ['success' => $result['success'], 'message' => $result['message']];
    }

    return ['error' => 'Method not allowed'];
}

/**
 * Handle CDR API
 */
function handleCdr(App $app, string $method, ?string $id, ?string $action): array
{
    $cdrService = new \aReports\Services\CDRService();

    if ($method === 'GET') {
        $dateFrom = $_GET['from'] ?? date('Y-m-d');
        $dateTo = $_GET['to'] ?? date('Y-m-d');
        $page = (int)($_GET['page'] ?? 1);
        $perPage = min((int)($_GET['per_page'] ?? 50), 100);
        $src = $_GET['src'] ?? null;
        $dst = $_GET['dst'] ?? null;
        $disposition = $_GET['disposition'] ?? null;

        if ($id !== null) {
            // Single CDR record
            $cdr = $cdrService->getCallByUniqueid($id);
            return ['data' => $cdr];
        }

        // List CDR records
        $filters = [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'src' => $src,
            'dst' => $dst,
            'disposition' => $disposition,
        ];

        $cdrs = $cdrService->getCalls($filters, $page, $perPage);
        $total = $cdrService->countCalls($filters);

        return [
            'data' => $cdrs,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => ceil($total / $perPage)
            ]
        ];
    }

    return ['error' => 'Method not allowed'];
}

/**
 * Handle Stats API
 */
function handleStats(App $app, string $method, ?string $type): array
{
    if ($method !== 'GET') {
        return ['error' => 'Method not allowed'];
    }

    $dateFrom = $_GET['from'] ?? date('Y-m-d');
    $dateTo = $_GET['to'] ?? date('Y-m-d');

    $queueService = new \aReports\Services\QueueService();
    $agentService = new \aReports\Services\AgentService();
    $cdrService = new \aReports\Services\CDRService();

    switch ($type) {
        case 'summary':
            return [
                'data' => [
                    'date_range' => ['from' => $dateFrom, 'to' => $dateTo],
                    'total_calls' => $cdrService->countCalls(['date_from' => $dateFrom, 'date_to' => $dateTo]),
                    'answered' => $cdrService->countCalls(['date_from' => $dateFrom, 'date_to' => $dateTo, 'disposition' => 'ANSWERED']),
                    'queues' => $queueService->getAllQueuesSummary($dateFrom, $dateTo),
                ]
            ];

        case 'hourly':
            $queue = $_GET['queue'] ?? null;
            return ['data' => $queueService->getHourlyStats($queue, $dateFrom, $dateTo)];

        case 'daily':
            $queue = $_GET['queue'] ?? null;
            return ['data' => $queueService->getDailyStats($queue, $dateFrom, $dateTo)];

        case 'sla':
            return ['data' => $queueService->getSlaCompliance($dateFrom, $dateTo)];

        default:
            return [
                'data' => [
                    'queues' => $queueService->getAllQueuesSummary($dateFrom, $dateTo),
                    'timestamp' => date('c')
                ]
            ];
    }
}

/**
 * Handle Alerts API
 */
function handleAlerts(App $app, string $method, ?string $id, ?string $action): array
{
    $db = $app->getDb();

    if ($method === 'GET') {
        if ($id === null) {
            // List alerts
            $alerts = $db->fetchAll("SELECT * FROM alerts ORDER BY is_active DESC, name");
            return ['data' => $alerts, 'count' => count($alerts)];
        }

        if ($action === 'history') {
            // Alert history
            $history = $db->fetchAll(
                "SELECT * FROM alert_history WHERE alert_id = ? ORDER BY triggered_at DESC LIMIT 100",
                [$id]
            );
            return ['data' => $history];
        }

        // Single alert
        $alert = $db->fetch("SELECT * FROM alerts WHERE id = ?", [$id]);
        return ['data' => $alert];
    }

    if ($method === 'POST' && $id && $action === 'acknowledge') {
        // Acknowledge alert
        $db->update('alert_history', [
            'acknowledged_at' => date('Y-m-d H:i:s'),
        ], ['id' => $id, 'acknowledged_at' => null]);
        return ['success' => true, 'message' => 'Alert acknowledged'];
    }

    return ['error' => 'Method not allowed'];
}

/**
 * Handle Reports API
 */
function handleReports(App $app, string $method, ?string $type): array
{
    if ($method !== 'GET') {
        return ['error' => 'Method not allowed'];
    }

    $dateFrom = $_GET['from'] ?? date('Y-m-d');
    $dateTo = $_GET['to'] ?? date('Y-m-d');
    $format = $_GET['format'] ?? 'json';

    $queueService = new \aReports\Services\QueueService();
    $agentService = new \aReports\Services\AgentService();

    $data = match ($type) {
        'agent' => $agentService->getAllAgentsPerformance($dateFrom, $dateTo),
        'queue' => $queueService->getAllQueuesSummary($dateFrom, $dateTo),
        default => ['error' => 'Unknown report type']
    };

    if ($format === 'csv') {
        // Return CSV download URL
        $excelService = new \aReports\Services\ExcelService();
        $columns = $type === 'agent'
            ? ['agent_name' => 'Agent', 'calls_handled' => 'Calls', 'answer_rate' => 'Answer Rate']
            : ['queue_name' => 'Queue', 'total_calls' => 'Total', 'sla_percentage' => 'SLA %'];

        $result = $excelService->exportToCsv($data, $columns);
        return ['download_url' => '/areports/storage/exports/' . $result['filename']];
    }

    return ['data' => $data];
}

/**
 * Handle Realtime API
 */
function handleRealtime(App $app, string $method, ?string $type): array
{
    if ($method !== 'GET') {
        return ['error' => 'Method not allowed'];
    }

    $amiService = new \aReports\Services\AMIService();

    switch ($type) {
        case 'queues':
            return ['data' => $amiService->getQueueStatus()];

        case 'channels':
            return ['data' => $amiService->getActiveChannels()];

        case 'peers':
            return ['data' => $amiService->getPeerStatus()];

        default:
            return [
                'data' => [
                    'queues' => $amiService->getQueueStatus(),
                    'channels' => $amiService->getActiveChannels(),
                    'timestamp' => date('c')
                ]
            ];
    }
}

/**
 * Send JSON response
 */
function jsonResponse(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}
