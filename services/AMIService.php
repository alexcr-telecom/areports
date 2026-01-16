<?php
/**
 * AMI Service
 * Asterisk Manager Interface for real-time data
 */

namespace aReports\Services;

class AMIService
{
    private $socket = null;
    private array $config;
    private bool $authenticated = false;
    private bool $debug = true;
    private string $logFile;

    public function __construct()
    {
        $this->config = require dirname(__DIR__) . '/config/ami.php';
        $this->logFile = dirname(__DIR__) . '/storage/logs/ami_debug.log';
    }

    /**
     * Log debug message
     */
    private function log(string $message, string $type = 'INFO'): void
    {
        if (!$this->debug) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$type}] {$message}\n";

        // Ensure log directory exists
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
    }

    /**
     * Connect to AMI
     */
    public function connect(): bool
    {
        $this->log("Connecting to AMI at {$this->config['host']}:{$this->config['port']}");

        $this->socket = @fsockopen(
            $this->config['host'],
            $this->config['port'],
            $errno,
            $errstr,
            $this->config['connect_timeout']
        );

        if (!$this->socket) {
            $this->log("AMI Connection failed: {$errstr} ({$errno})", 'ERROR');
            error_log("AMI Connection failed: {$errstr} ({$errno})");
            return false;
        }

        $this->log("Socket connected successfully");
        // Use shorter timeout (3 seconds) for faster response
        stream_set_timeout($this->socket, 3);

        // Read welcome message (just one line)
        $welcome = fgets($this->socket, 4096);
        $this->log("Welcome message: " . trim($welcome ?: 'none'));

        // Authenticate
        $result = $this->login();
        $this->log("Login result: " . ($result ? 'SUCCESS' : 'FAILED'));
        return $result;
    }

    /**
     * Login to AMI
     */
    private function login(): bool
    {
        $this->log("Logging in as user: {$this->config['username']}");
        $this->sendCommand("Action: Login\r\nUsername: {$this->config['username']}\r\nSecret: {$this->config['secret']}\r\n\r\n");

        $response = $this->readResponse();
        $this->log("Login response: " . trim(str_replace(["\r", "\n"], ' ', $response)));

        if (strpos($response, 'Success') !== false) {
            $this->authenticated = true;
            return true;
        }

        $this->log("Login failed - response did not contain 'Success'", 'ERROR');
        return false;
    }

    /**
     * Disconnect from AMI
     */
    public function disconnect(): void
    {
        if ($this->socket) {
            if ($this->authenticated) {
                $this->sendCommand("Action: Logoff\r\n\r\n");
            }
            fclose($this->socket);
            $this->socket = null;
            $this->authenticated = false;
        }
    }

    /**
     * Send command to AMI
     */
    private function sendCommand(string $command): bool
    {
        if (!$this->socket) {
            $this->log("Cannot send command - socket is null", 'ERROR');
            return false;
        }

        // Log command (hide secret)
        $logCommand = preg_replace('/Secret: .+/', 'Secret: ****', $command);
        $this->log("SEND >>> " . trim(str_replace(["\r\n", "\r", "\n"], ' | ', $logCommand)));

        $result = fwrite($this->socket, $command);
        if ($result === false) {
            $this->log("Failed to write to socket", 'ERROR');
            return false;
        }

        return true;
    }

    /**
     * Read response from AMI (reads until empty line)
     */
    private function readResponse(): string
    {
        if (!$this->socket) {
            $this->log("Cannot read response - socket is null", 'ERROR');
            return '';
        }

        $response = '';

        while (!feof($this->socket)) {
            $line = fgets($this->socket, 4096);

            if ($line === false) {
                $info = stream_get_meta_data($this->socket);
                if ($info['timed_out']) {
                    $this->log("Socket read timeout", 'WARN');
                }
                break;
            }

            // Skip FullyBooted event that AMI sends after login
            if (strpos($line, 'Event: FullyBooted') !== false) {
                // Read until end of this event (empty line)
                while (!feof($this->socket)) {
                    $skipLine = fgets($this->socket, 4096);
                    if ($skipLine === false || trim($skipLine) === '') {
                        break;
                    }
                }
                continue;
            }

            $response .= $line;

            // Empty line marks end of response
            if (trim($line) === '') {
                break;
            }
        }

        $this->log("RECV <<< " . trim(str_replace(["\r\n", "\r", "\n"], ' | ', $response)));
        return $response;
    }

    /**
     * Parse AMI response into array
     */
    private function parseResponse(string $response): array
    {
        $result = [];
        $lines = explode("\r\n", $response);

        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                [$key, $value] = explode(':', $line, 2);
                $result[trim($key)] = trim($value);
            }
        }

        return $result;
    }

    /**
     * Get queue status
     */
    public function getQueueStatus(?string $queue = null): array
    {
        $this->log("=== getQueueStatus called ===");

        if (!$this->authenticated && !$this->connect()) {
            $this->log("getQueueStatus: Failed to connect/authenticate", 'ERROR');
            return [];
        }

        $actionId = uniqid('queue_');
        $command = "Action: QueueStatus\r\nActionID: {$actionId}\r\n";

        if ($queue) {
            $command .= "Queue: {$queue}\r\n";
        }

        $command .= "\r\n";
        $this->sendCommand($command);

        $queues = [];
        $members = [];
        $callers = [];

        // Read until QueueStatusComplete
        while (!feof($this->socket)) {
            $response = $this->readResponse();
            $data = $this->parseResponse($response);

            if (empty($data)) {
                continue;
            }

            if (isset($data['Event'])) {
                switch ($data['Event']) {
                    case 'QueueParams':
                        $queues[$data['Queue']] = [
                            'name' => $data['Queue'],
                            'max' => $data['Max'] ?? 0,
                            'strategy' => $data['Strategy'] ?? 'unknown',
                            'calls' => (int) ($data['Calls'] ?? 0),
                            'holdtime' => (int) ($data['Holdtime'] ?? 0),
                            'talktime' => (int) ($data['TalkTime'] ?? 0),
                            'completed' => (int) ($data['Completed'] ?? 0),
                            'abandoned' => (int) ($data['Abandoned'] ?? 0),
                            'servicelevel' => (int) ($data['ServiceLevel'] ?? 0),
                            'servicelevelperf' => (float) ($data['ServicelevelPerf'] ?? 0),
                            'weight' => (int) ($data['Weight'] ?? 0),
                            'members' => [],
                            'callers' => []
                        ];
                        break;

                    case 'QueueMember':
                        $queueName = $data['Queue'];
                        $member = [
                            'name' => $data['Name'] ?? $data['Interface'],
                            'interface' => $data['Interface'] ?? '',
                            'stateinterface' => $data['StateInterface'] ?? '',
                            'membership' => $data['Membership'] ?? 'dynamic',
                            'penalty' => (int) ($data['Penalty'] ?? 0),
                            'calls_taken' => (int) ($data['CallsTaken'] ?? 0),
                            'last_call' => (int) ($data['LastCall'] ?? 0),
                            'last_pause' => (int) ($data['LastPause'] ?? 0),
                            'in_call' => ($data['InCall'] ?? '0') === '1',
                            'status' => (int) ($data['Status'] ?? 0),
                            'paused' => ($data['Paused'] ?? '0') === '1',
                            'paused_reason' => $data['PausedReason'] ?? '',
                            'wrapuptime' => (int) ($data['Wrapuptime'] ?? 0),
                            'status_text' => $this->getStatusText((int) ($data['Status'] ?? 0))
                        ];
                        $members[$queueName][] = $member;
                        break;

                    case 'QueueEntry':
                        $queueName = $data['Queue'];
                        $caller = [
                            'channel' => $data['Channel'] ?? '',
                            'uniqueid' => $data['Uniqueid'] ?? '',
                            'position' => (int) ($data['Position'] ?? 0),
                            'caller_id_num' => $data['CallerIDNum'] ?? '',
                            'caller_id_name' => $data['CallerIDName'] ?? '',
                            'wait' => (int) ($data['Wait'] ?? 0),
                            'priority' => (int) ($data['Priority'] ?? 0)
                        ];
                        $callers[$queueName][] = $caller;
                        break;

                    case 'QueueStatusComplete':
                        // Assign members and callers to queues
                        foreach ($queues as $name => &$queueData) {
                            $queueData['members'] = $members[$name] ?? [];
                            $queueData['callers'] = $callers[$name] ?? [];
                        }
                        return array_values($queues);
                }
            }
        }

        return array_values($queues);
    }

    /**
     * Get active channels
     */
    public function getActiveChannels(): array
    {
        if (!$this->authenticated && !$this->connect()) {
            return [];
        }

        $actionId = uniqid('channels_');
        $this->sendCommand("Action: CoreShowChannels\r\nActionID: {$actionId}\r\n\r\n");

        $channels = [];

        while (!feof($this->socket)) {
            $response = $this->readResponse();
            $data = $this->parseResponse($response);

            if (empty($data)) {
                continue;
            }

            if (isset($data['Event'])) {
                if ($data['Event'] === 'CoreShowChannel') {
                    $channels[] = [
                        'channel' => $data['Channel'] ?? '',
                        'uniqueid' => $data['Uniqueid'] ?? '',
                        'context' => $data['Context'] ?? '',
                        'extension' => $data['Extension'] ?? '',
                        'priority' => $data['Priority'] ?? '',
                        'state' => $data['ChannelState'] ?? '',
                        'state_desc' => $data['ChannelStateDesc'] ?? '',
                        'application' => $data['Application'] ?? '',
                        'application_data' => $data['ApplicationData'] ?? '',
                        'caller_id_num' => $data['CallerIDNum'] ?? '',
                        'caller_id_name' => $data['CallerIDName'] ?? '',
                        'connected_line_num' => $data['ConnectedLineNum'] ?? '',
                        'connected_line_name' => $data['ConnectedLineName'] ?? '',
                        'duration' => (int) ($data['Duration'] ?? 0),
                        'bridge_id' => $data['BridgeId'] ?? ''
                    ];
                } elseif ($data['Event'] === 'CoreShowChannelsComplete') {
                    break;
                }
            }
        }

        return $channels;
    }

    /**
     * Get SIP/PJSIP peer status
     */
    public function getPeerStatus(): array
    {
        if (!$this->authenticated && !$this->connect()) {
            return [];
        }

        $peers = [];

        // Try PJSIP first
        $actionId = uniqid('pjsip_');
        $this->sendCommand("Action: PJSIPShowEndpoints\r\nActionID: {$actionId}\r\n\r\n");

        while (!feof($this->socket)) {
            $response = $this->readResponse();
            $data = $this->parseResponse($response);

            if (empty($data)) {
                continue;
            }

            if (isset($data['Event'])) {
                if ($data['Event'] === 'EndpointList') {
                    $peers[] = [
                        'objectname' => $data['ObjectName'] ?? '',
                        'transport' => $data['Transport'] ?? '',
                        'aor' => $data['Aor'] ?? '',
                        'device_state' => $data['DeviceState'] ?? 'UNAVAILABLE',
                        'active_channels' => $data['ActiveChannels'] ?? '0'
                    ];
                } elseif ($data['Event'] === 'EndpointListComplete') {
                    break;
                }
            }
        }

        return $peers;
    }

    /**
     * Get status text from device state code
     */
    private function getStatusText(int $status): string
    {
        return match ($status) {
            0 => 'Unknown',
            1 => 'Not In Use',
            2 => 'In Use',
            3 => 'Busy',
            4 => 'Invalid',
            5 => 'Unavailable',
            6 => 'Ringing',
            7 => 'Ring+In Use',
            8 => 'On Hold',
            default => 'Unknown'
        };
    }

    /**
     * Originate a call (for click-to-dial)
     */
    public function originateCall(string $channel, string $extension, string $context = 'from-internal'): array
    {
        if (!$this->authenticated && !$this->connect()) {
            return ['success' => false, 'message' => 'Failed to connect to AMI'];
        }

        $actionId = uniqid('originate_');
        $command = "Action: Originate\r\n";
        $command .= "ActionID: {$actionId}\r\n";
        $command .= "Channel: {$channel}\r\n";
        $command .= "Context: {$context}\r\n";
        $command .= "Exten: {$extension}\r\n";
        $command .= "Priority: 1\r\n";
        $command .= "Async: true\r\n";
        $command .= "\r\n";

        $this->sendCommand($command);
        $response = $this->readResponse();
        $data = $this->parseResponse($response);

        return [
            'success' => ($data['Response'] ?? '') === 'Success',
            'message' => $data['Message'] ?? 'Unknown error'
        ];
    }

    /**
     * Queue add member
     */
    public function queueAddMember(string $queue, string $interface): array
    {
        if (!$this->authenticated && !$this->connect()) {
            return ['success' => false, 'message' => 'Failed to connect to AMI'];
        }

        $actionId = uniqid('qadd_');
        $command = "Action: QueueAdd\r\n";
        $command .= "ActionID: {$actionId}\r\n";
        $command .= "Queue: {$queue}\r\n";
        $command .= "Interface: {$interface}\r\n";
        $command .= "\r\n";

        $this->sendCommand($command);
        $response = $this->readResponse();
        $data = $this->parseResponse($response);

        return [
            'success' => ($data['Response'] ?? '') === 'Success',
            'message' => $data['Message'] ?? 'Unknown error'
        ];
    }

    /**
     * Queue remove member
     */
    public function queueRemoveMember(string $queue, string $interface): array
    {
        if (!$this->authenticated && !$this->connect()) {
            return ['success' => false, 'message' => 'Failed to connect to AMI'];
        }

        $actionId = uniqid('qremove_');
        $command = "Action: QueueRemove\r\n";
        $command .= "ActionID: {$actionId}\r\n";
        $command .= "Queue: {$queue}\r\n";
        $command .= "Interface: {$interface}\r\n";
        $command .= "\r\n";

        $this->sendCommand($command);
        $response = $this->readResponse();
        $data = $this->parseResponse($response);

        return [
            'success' => ($data['Response'] ?? '') === 'Success',
            'message' => $data['Message'] ?? 'Unknown error'
        ];
    }

    /**
     * Queue pause member
     */
    public function queuePauseMember(string $queue, string $interface, bool $paused = true, string $reason = ''): array
    {
        if (!$this->authenticated && !$this->connect()) {
            return ['success' => false, 'message' => 'Failed to connect to AMI'];
        }

        $actionId = uniqid('qpause_');
        $command = "Action: QueuePause\r\n";
        $command .= "ActionID: {$actionId}\r\n";
        $command .= "Queue: {$queue}\r\n";
        $command .= "Interface: {$interface}\r\n";
        $command .= "Paused: " . ($paused ? 'true' : 'false') . "\r\n";
        if ($reason) {
            $command .= "Reason: {$reason}\r\n";
        }
        $command .= "\r\n";

        $this->sendCommand($command);
        $response = $this->readResponse();
        $data = $this->parseResponse($response);

        return [
            'success' => ($data['Response'] ?? '') === 'Success',
            'message' => $data['Message'] ?? 'Unknown error'
        ];
    }

    /**
     * Hangup a channel
     */
    public function hangup(string $channel): array
    {
        if (!$this->authenticated && !$this->connect()) {
            return ['success' => false, 'message' => 'Failed to connect to AMI'];
        }

        $actionId = uniqid('hangup_');
        $command = "Action: Hangup\r\n";
        $command .= "ActionID: {$actionId}\r\n";
        $command .= "Channel: {$channel}\r\n";
        $command .= "\r\n";

        $this->sendCommand($command);
        $response = $this->readResponse();
        $data = $this->parseResponse($response);

        return [
            'success' => ($data['Response'] ?? '') === 'Success',
            'message' => $data['Message'] ?? 'Unknown error'
        ];
    }

    /**
     * Destructor - ensure connection is closed
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}
