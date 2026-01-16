<?php
/**
 * WebSocket Server
 * Real-time updates for dashboard and wallboard
 *
 * Run with: php websocket/server.php
 */

require_once dirname(__DIR__) . '/core/App.php';

use aReports\Core\App;
use aReports\Services\AMIService;
use aReports\Services\QueueService;

class WebSocketServer
{
    private $socket;
    private array $clients = [];
    private array $subscriptions = [];
    private int $port;
    private bool $running = true;
    private App $app;
    private int $lastBroadcast = 0;
    private int $broadcastInterval = 5; // seconds

    public function __construct(int $port = 8080)
    {
        $this->port = $port;
        $this->app = App::getInstance();
    }

    /**
     * Start the server
     */
    public function start(): void
    {
        $this->log("Starting WebSocket server on port {$this->port}...");

        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_bind($this->socket, '0.0.0.0', $this->port);
        socket_listen($this->socket);
        socket_set_nonblock($this->socket);

        $this->log("Server started. Listening for connections...");

        // Handle shutdown
        pcntl_signal(SIGTERM, [$this, 'shutdown']);
        pcntl_signal(SIGINT, [$this, 'shutdown']);

        while ($this->running) {
            pcntl_signal_dispatch();

            // Accept new connections
            $newClient = @socket_accept($this->socket);
            if ($newClient) {
                $this->handleNewConnection($newClient);
            }

            // Handle client messages
            foreach ($this->clients as $id => $client) {
                $data = @socket_read($client['socket'], 4096);
                if ($data === false) {
                    $error = socket_last_error($client['socket']);
                    if ($error !== SOCKET_EWOULDBLOCK && $error !== 11) {
                        $this->removeClient($id);
                    }
                } elseif ($data === '') {
                    $this->removeClient($id);
                } elseif ($client['handshake']) {
                    $this->handleMessage($id, $data);
                } else {
                    $this->performHandshake($id, $data);
                }
            }

            // Broadcast data periodically
            if (time() - $this->lastBroadcast >= $this->broadcastInterval) {
                $this->broadcastData();
                $this->lastBroadcast = time();
            }

            usleep(10000); // 10ms
        }

        $this->cleanup();
    }

    /**
     * Handle new connection
     */
    private function handleNewConnection($socket): void
    {
        socket_set_nonblock($socket);
        $id = uniqid('client_');

        $this->clients[$id] = [
            'socket' => $socket,
            'handshake' => false,
            'subscriptions' => [],
        ];

        $this->log("New connection: {$id}");
    }

    /**
     * Perform WebSocket handshake
     */
    private function performHandshake(string $id, string $data): void
    {
        if (preg_match('/Sec-WebSocket-Key: (.+)\r\n/', $data, $matches)) {
            $key = trim($matches[1]);
            $acceptKey = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));

            $response = "HTTP/1.1 101 Switching Protocols\r\n";
            $response .= "Upgrade: websocket\r\n";
            $response .= "Connection: Upgrade\r\n";
            $response .= "Sec-WebSocket-Accept: {$acceptKey}\r\n\r\n";

            socket_write($this->clients[$id]['socket'], $response);
            $this->clients[$id]['handshake'] = true;

            $this->log("Handshake completed: {$id}");

            // Send initial data
            $this->sendToClient($id, [
                'type' => 'connected',
                'message' => 'Connected to aReports WebSocket server',
            ]);
        }
    }

    /**
     * Handle incoming message
     */
    private function handleMessage(string $id, string $data): void
    {
        $decoded = $this->decodeFrame($data);
        if (!$decoded) {
            return;
        }

        $message = json_decode($decoded, true);
        if (!$message) {
            return;
        }

        $this->log("Message from {$id}: " . json_encode($message));

        switch ($message['action'] ?? '') {
            case 'subscribe':
                $channel = $message['channel'] ?? 'all';
                $this->clients[$id]['subscriptions'][$channel] = true;
                $this->sendToClient($id, [
                    'type' => 'subscribed',
                    'channel' => $channel,
                ]);
                break;

            case 'unsubscribe':
                $channel = $message['channel'] ?? 'all';
                unset($this->clients[$id]['subscriptions'][$channel]);
                break;

            case 'ping':
                $this->sendToClient($id, ['type' => 'pong']);
                break;

            case 'get_queues':
                $this->sendQueueData($id);
                break;

            case 'get_agents':
                $this->sendAgentData($id);
                break;
        }
    }

    /**
     * Broadcast data to all subscribed clients
     */
    private function broadcastData(): void
    {
        if (empty($this->clients)) {
            return;
        }

        try {
            $ami = new AMIService();
            $queues = $ami->getQueueStatus();
            $channels = $ami->getActiveChannels();

            $data = [
                'type' => 'update',
                'timestamp' => date('c'),
                'data' => [
                    'queues' => $queues,
                    'active_calls' => count($channels),
                    'channels' => $channels,
                ]
            ];

            foreach ($this->clients as $id => $client) {
                if (!$client['handshake']) {
                    continue;
                }

                $subscriptions = $client['subscriptions'];
                if (isset($subscriptions['all']) || isset($subscriptions['queues'])) {
                    $this->sendToClient($id, $data);
                }
            }
        } catch (\Exception $e) {
            $this->log("Broadcast error: " . $e->getMessage(), 'ERROR');
        }
    }

    /**
     * Send queue data to specific client
     */
    private function sendQueueData(string $id): void
    {
        try {
            $ami = new AMIService();
            $queues = $ami->getQueueStatus();

            $this->sendToClient($id, [
                'type' => 'queues',
                'data' => $queues,
            ]);
        } catch (\Exception $e) {
            $this->sendToClient($id, [
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send agent data to specific client
     */
    private function sendAgentData(string $id): void
    {
        try {
            $ami = new AMIService();
            $queues = $ami->getQueueStatus();

            $agents = [];
            foreach ($queues as $queue) {
                foreach ($queue['members'] ?? [] as $member) {
                    $key = $member['interface'];
                    if (!isset($agents[$key])) {
                        $agents[$key] = $member;
                        $agents[$key]['queues'] = [];
                    }
                    $agents[$key]['queues'][] = $queue['name'];
                }
            }

            $this->sendToClient($id, [
                'type' => 'agents',
                'data' => array_values($agents),
            ]);
        } catch (\Exception $e) {
            $this->sendToClient($id, [
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send message to client
     */
    private function sendToClient(string $id, array $data): void
    {
        if (!isset($this->clients[$id])) {
            return;
        }

        $json = json_encode($data);
        $frame = $this->encodeFrame($json);

        $result = @socket_write($this->clients[$id]['socket'], $frame);
        if ($result === false) {
            $this->removeClient($id);
        }
    }

    /**
     * Remove client
     */
    private function removeClient(string $id): void
    {
        if (isset($this->clients[$id])) {
            @socket_close($this->clients[$id]['socket']);
            unset($this->clients[$id]);
            $this->log("Client disconnected: {$id}");
        }
    }

    /**
     * Encode WebSocket frame
     */
    private function encodeFrame(string $data): string
    {
        $length = strlen($data);
        $frame = chr(0x81); // Text frame

        if ($length <= 125) {
            $frame .= chr($length);
        } elseif ($length <= 65535) {
            $frame .= chr(126) . pack('n', $length);
        } else {
            $frame .= chr(127) . pack('J', $length);
        }

        return $frame . $data;
    }

    /**
     * Decode WebSocket frame
     */
    private function decodeFrame(string $data): ?string
    {
        if (strlen($data) < 2) {
            return null;
        }

        $secondByte = ord($data[1]);
        $masked = ($secondByte & 0x80) !== 0;
        $length = $secondByte & 0x7F;

        $offset = 2;

        if ($length === 126) {
            $length = unpack('n', substr($data, 2, 2))[1];
            $offset = 4;
        } elseif ($length === 127) {
            $length = unpack('J', substr($data, 2, 8))[1];
            $offset = 10;
        }

        if ($masked) {
            $mask = substr($data, $offset, 4);
            $offset += 4;

            $decoded = '';
            for ($i = 0; $i < $length; $i++) {
                $decoded .= $data[$offset + $i] ^ $mask[$i % 4];
            }
            return $decoded;
        }

        return substr($data, $offset, $length);
    }

    /**
     * Shutdown handler
     */
    public function shutdown(): void
    {
        $this->log("Shutting down...");
        $this->running = false;
    }

    /**
     * Cleanup on exit
     */
    private function cleanup(): void
    {
        foreach ($this->clients as $id => $client) {
            @socket_close($client['socket']);
        }

        if ($this->socket) {
            socket_close($this->socket);
        }

        $this->log("Server stopped.");
    }

    /**
     * Log message
     */
    private function log(string $message, string $level = 'INFO'): void
    {
        $timestamp = date('Y-m-d H:i:s');
        echo "[{$timestamp}] [{$level}] {$message}\n";

        $logFile = dirname(__DIR__) . '/storage/logs/websocket.log';
        file_put_contents($logFile, "[{$timestamp}] [{$level}] {$message}\n", FILE_APPEND);
    }
}

// Run server
$port = (int) ($argv[1] ?? 8080);
$server = new WebSocketServer($port);
$server->start();
