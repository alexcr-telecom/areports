<?php
/**
 * Agent Panel Controller
 * Agent-specific view with queue controls
 */

namespace aReports\Controllers;

use aReports\Core\App;
use aReports\Core\Controller;
use aReports\Services\AMIService;
use aReports\Services\FreePBXService;

class AgentPanelController extends Controller
{
    /**
     * Agent dashboard - shows their CDR and queue status
     */
    public function index(): void
    {
        $this->requirePermission('agent.panel.view');

        $extension = $this->user['extension'] ?? null;

        if (!$extension) {
            $this->render('agent/no-extension', [
                'title' => 'Agent Panel',
                'currentPage' => 'agent.panel'
            ]);
            return;
        }

        // Get agent's recent CDR
        $cdrDb = App::getInstance()->getCdrDb();
        $recentCalls = $cdrDb->fetchAll(
            "SELECT * FROM cdr
             WHERE src = ? OR dst = ? OR cnum = ?
             ORDER BY calldate DESC
             LIMIT 50",
            [$extension, $extension, $extension]
        );

        // Get today's stats
        $today = date('Y-m-d');
        $todayStats = $cdrDb->fetch(
            "SELECT
                COUNT(*) as total_calls,
                SUM(CASE WHEN disposition = 'ANSWERED' THEN 1 ELSE 0 END) as answered,
                SUM(CASE WHEN disposition != 'ANSWERED' THEN 1 ELSE 0 END) as missed,
                SUM(CASE WHEN disposition = 'ANSWERED' THEN duration ELSE 0 END) as talk_time,
                AVG(CASE WHEN disposition = 'ANSWERED' THEN duration ELSE NULL END) as avg_duration
             FROM cdr
             WHERE (src = ? OR dst = ? OR cnum = ?)
             AND DATE(calldate) = ?",
            [$extension, $extension, $extension, $today]
        );

        // Get queues the agent is a member of
        $freepbxService = new FreePBXService();
        $allQueues = $freepbxService->getQueues();

        // Get pause causes
        $pauseCauses = $this->db->fetchAll(
            "SELECT * FROM pause_causes WHERE is_active = 1 ORDER BY sort_order, name"
        );

        $this->render('agent/panel', [
            'title' => 'Agent Panel',
            'currentPage' => 'agent.panel',
            'extension' => $extension,
            'recentCalls' => $recentCalls,
            'todayStats' => $todayStats,
            'queues' => $allQueues,
            'pauseCauses' => $pauseCauses
        ]);
    }

    /**
     * Get user's assigned queues from database
     */
    private function getUserAssignedQueues(): array
    {
        $queues = $this->db->fetchAll(
            "SELECT queue_name FROM user_queues WHERE user_id = ?",
            [$this->user['id']]
        );
        return array_column($queues, 'queue_name');
    }

    /**
     * Get real-time queue status for agent (AJAX)
     */
    public function status(): void
    {
        $this->requirePermission('agent.panel.view');

        $extension = $this->user['extension'] ?? null;

        if (!$extension) {
            $this->json(['error' => 'No extension assigned'], 400);
            return;
        }

        try {
            $ami = new AMIService();

            // Get all queue statuses
            $queueStatus = $ami->getQueueStatus();

            // Get user's assigned queues (if any)
            $assignedQueues = $this->getUserAssignedQueues();

            // Find which queues the agent is in and their status
            $agentQueues = [];
            $interface = "Local/{$extension}@from-queue/n";
            $sipInterface = "PJSIP/{$extension}";

            foreach ($queueStatus as $queue) {
                $queueName = $queue['name'] ?? $queue['queue'] ?? 'Unknown';

                // If user has assigned queues, filter to only those
                if (!empty($assignedQueues) && !in_array($queueName, $assignedQueues)) {
                    continue;
                }
                $inQueue = false;
                $paused = false;
                $pausedReason = '';
                $status = 'logged_out';

                if (isset($queue['members'])) {
                    foreach ($queue['members'] as $member) {
                        $memberInterface = '';
                        if (isset($member['interface'])) {
                            $memberInterface = $member['interface'];
                        } elseif (isset($member['location'])) {
                            $memberInterface = $member['location'];
                        }
                        if (stripos($memberInterface, $extension) !== false) {
                            $inQueue = true;
                            $paused = isset($member['paused']) ? $member['paused'] : false;
                            $pausedReason = isset($member['paused_reason']) ? $member['paused_reason'] : '';

                            if ($paused) {
                                $status = 'paused';
                            } else {
                                $memberStatus = $member['status'] ?? 1;
                                switch ($memberStatus) {
                                    case 1: $status = 'available'; break;
                                    case 2: $status = 'busy'; break;
                                    case 3: $status = 'unavailable'; break;
                                    case 6: $status = 'ringing'; break;
                                    case 7: $status = 'ringinuse'; break;
                                    default: $status = 'available';
                                }
                            }
                            break;
                        }
                    }
                }

                $queueName = 'Unknown';
                if (isset($queue['name'])) {
                    $queueName = $queue['name'];
                } elseif (isset($queue['queue'])) {
                    $queueName = $queue['queue'];
                }
                $agentQueues[] = [
                    'queue' => $queueName,
                    'name' => $queueName,
                    'in_queue' => $inQueue,
                    'status' => $status,
                    'paused' => $paused,
                    'paused_reason' => $pausedReason,
                    'calls_waiting' => isset($queue['calls']) ? $queue['calls'] : 0
                ];
            }

            $this->json([
                'success' => true,
                'extension' => $extension,
                'queues' => $agentQueues
            ]);
        } catch (\Exception $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Login to queue
     */
    public function queueLogin(): void
    {
        $this->requirePermission('agent.panel.queue');

        $extension = $this->user['extension'] ?? null;
        $queue = $this->post('queue');

        if (!$extension) {
            $this->json(['error' => 'No extension assigned'], 400);
            return;
        }

        if (!$queue) {
            $this->json(['error' => 'Queue is required'], 400);
            return;
        }

        try {
            $ami = new AMIService();
            $interface = "Local/{$extension}@from-queue/n";

            $result = $ami->queueAddMember($queue, $interface);

            $this->audit('queue_login', 'agent', $this->user['id'], null, [
                'queue' => $queue,
                'extension' => $extension
            ]);

            $this->json([
                'success' => true,
                'message' => "Logged into queue {$queue}"
            ]);
        } catch (\Exception $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Logout from queue
     */
    public function queueLogout(): void
    {
        $this->requirePermission('agent.panel.queue');

        $extension = $this->user['extension'] ?? null;
        $queue = $this->post('queue');

        if (!$extension) {
            $this->json(['error' => 'No extension assigned'], 400);
            return;
        }

        if (!$queue) {
            $this->json(['error' => 'Queue is required'], 400);
            return;
        }

        try {
            $ami = new AMIService();
            $interface = "Local/{$extension}@from-queue/n";

            $result = $ami->queueRemoveMember($queue, $interface);

            $this->audit('queue_logout', 'agent', $this->user['id'], null, [
                'queue' => $queue,
                'extension' => $extension
            ]);

            $this->json([
                'success' => true,
                'message' => "Logged out of queue {$queue}"
            ]);
        } catch (\Exception $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Pause in queue
     */
    public function queuePause(): void
    {
        $this->requirePermission('agent.panel.queue');

        $extension = $this->user['extension'] ?? null;
        $queue = $this->post('queue');
        $reason = $this->post('reason', '');

        if (!$extension) {
            $this->json(['error' => 'No extension assigned'], 400);
            return;
        }

        try {
            $ami = new AMIService();
            $interface = "Local/{$extension}@from-queue/n";

            // If queue is empty, pause in all queues
            if (empty($queue)) {
                $result = $ami->queuePauseMember('', $interface, true, $reason);
            } else {
                $result = $ami->queuePauseMember($queue, $interface, true, $reason);
            }

            $this->audit('queue_pause', 'agent', $this->user['id'], null, [
                'queue' => $queue ?: 'all',
                'extension' => $extension,
                'reason' => $reason
            ]);

            $this->json([
                'success' => true,
                'message' => "Paused" . ($queue ? " in queue {$queue}" : " in all queues")
            ]);
        } catch (\Exception $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Unpause in queue
     */
    public function queueUnpause(): void
    {
        $this->requirePermission('agent.panel.queue');

        $extension = $this->user['extension'] ?? null;
        $queue = $this->post('queue');

        if (!$extension) {
            $this->json(['error' => 'No extension assigned'], 400);
            return;
        }

        try {
            $ami = new AMIService();
            $interface = "Local/{$extension}@from-queue/n";

            // If queue is empty, unpause in all queues
            if (empty($queue)) {
                $result = $ami->queuePauseMember('', $interface, false);
            } else {
                $result = $ami->queuePauseMember($queue, $interface, false);
            }

            $this->audit('queue_unpause', 'agent', $this->user['id'], null, [
                'queue' => $queue ?: 'all',
                'extension' => $extension
            ]);

            $this->json([
                'success' => true,
                'message' => "Unpaused" . ($queue ? " in queue {$queue}" : " in all queues")
            ]);
        } catch (\Exception $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Login to all queues
     */
    public function loginAll(): void
    {
        $this->requirePermission('agent.panel.queue');

        $extension = $this->user['extension'] ?? null;

        if (!$extension) {
            $this->json(['error' => 'No extension assigned'], 400);
            return;
        }

        try {
            $ami = new AMIService();
            $interface = "Local/{$extension}@from-queue/n";

            // Get user's assigned queues
            $assignedQueues = $this->getUserAssignedQueues();

            // Get all queues from FreePBX
            $freepbxService = new FreePBXService();
            $allQueues = $freepbxService->getQueues();

            // Filter to only assigned queues if any
            $queues = $allQueues;
            if (!empty($assignedQueues)) {
                $queues = array_filter($allQueues, function($q) use ($assignedQueues) {
                    return in_array($q['extension'], $assignedQueues);
                });
            }

            $loggedIn = 0;
            $errors = [];

            foreach ($queues as $queue) {
                try {
                    $ami->queueAddMember($queue['extension'], $interface);
                    $loggedIn++;
                } catch (\Exception $e) {
                    $errors[] = $queue['extension'] . ': ' . $e->getMessage();
                }
            }

            $this->audit('queue_login_all', 'agent', $this->user['id'], null, [
                'extension' => $extension,
                'queues_logged_in' => $loggedIn
            ]);

            $this->json([
                'success' => true,
                'message' => "Logged into {$loggedIn} queues",
                'errors' => $errors
            ]);
        } catch (\Exception $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Logout from all queues
     */
    public function logoutAll(): void
    {
        $this->requirePermission('agent.panel.queue');

        $extension = $this->user['extension'] ?? null;

        if (!$extension) {
            $this->json(['error' => 'No extension assigned'], 400);
            return;
        }

        try {
            $ami = new AMIService();
            $interface = "Local/{$extension}@from-queue/n";

            // Get user's assigned queues
            $assignedQueues = $this->getUserAssignedQueues();

            // Get all queues from FreePBX
            $freepbxService = new FreePBXService();
            $allQueues = $freepbxService->getQueues();

            // Filter to only assigned queues if any
            $queues = $allQueues;
            if (!empty($assignedQueues)) {
                $queues = array_filter($allQueues, function($q) use ($assignedQueues) {
                    return in_array($q['extension'], $assignedQueues);
                });
            }

            $loggedOut = 0;
            $errors = [];

            foreach ($queues as $queue) {
                try {
                    $ami->queueRemoveMember($queue['extension'], $interface);
                    $loggedOut++;
                } catch (\Exception $e) {
                    // Ignore "not in queue" errors
                    if (stripos($e->getMessage(), 'not') === false) {
                        $errors[] = $queue['extension'] . ': ' . $e->getMessage();
                    }
                }
            }

            $this->audit('queue_logout_all', 'agent', $this->user['id'], null, [
                'extension' => $extension,
                'queues_logged_out' => $loggedOut
            ]);

            $this->json([
                'success' => true,
                'message' => "Logged out of {$loggedOut} queues",
                'errors' => $errors
            ]);
        } catch (\Exception $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }
}
