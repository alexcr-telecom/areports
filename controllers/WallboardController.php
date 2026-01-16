<?php
/**
 * Wallboard Controller
 * Full-screen display for call center monitoring
 */

namespace aReports\Controllers;

use aReports\Core\Controller;

class WallboardController extends Controller
{
    /**
     * Show the wallboard
     */
    public function index(): void
    {
        $this->requirePermission('wallboard.view');

        $this->view->setLayout('wallboard');
        $this->render('wallboard/index', [
            'title' => 'Wallboard'
        ]);
    }

    /**
     * Get wallboard data for AJAX refresh
     */
    public function getData(): void
    {
        $this->requirePermission('wallboard.view');

        $today = date('Y-m-d');
        $todayStart = $today . ' 00:00:00';
        $todayEnd = $today . ' 23:59:59';

        $this->json([
            'stats' => $this->getTodayStats($todayStart, $todayEnd),
            'queues' => $this->getQueueStatus($todayStart, $todayEnd),
            'agents' => $this->getAgentStatus(),
            'timestamp' => date('H:i:s')
        ]);
    }

    /**
     * Get today's statistics
     */
    private function getTodayStats(string $start, string $end): array
    {
        // Total calls
        $totalCalls = (int) $this->cdrDb->fetchColumn(
            "SELECT COUNT(*) FROM cdr WHERE calldate BETWEEN ? AND ?",
            [$start, $end]
        );

        // Answered calls
        $answeredCalls = (int) $this->cdrDb->fetchColumn(
            "SELECT COUNT(*) FROM cdr WHERE calldate BETWEEN ? AND ? AND disposition = 'ANSWERED'",
            [$start, $end]
        );

        // Abandoned calls
        $abandonedCalls = (int) $this->cdrDb->fetchColumn(
            "SELECT COUNT(*) FROM cdr WHERE calldate BETWEEN ? AND ? AND disposition IN ('NO ANSWER', 'BUSY', 'FAILED')",
            [$start, $end]
        );

        // Average wait time from queue log
        $avgWait = (int) $this->cdrDb->fetchColumn(
            "SELECT AVG(CAST(data1 AS UNSIGNED))
             FROM queuelog
             WHERE event = 'CONNECT' AND time BETWEEN ? AND ?",
            [$start, $end]
        );

        // Average talk time
        $avgTalk = (int) $this->cdrDb->fetchColumn(
            "SELECT AVG(billsec) FROM cdr
             WHERE calldate BETWEEN ? AND ? AND disposition = 'ANSWERED'",
            [$start, $end]
        );

        // Overall SLA
        $slaThreshold = $this->setting('sla.default_threshold', 60);
        $answeredInSla = (int) $this->cdrDb->fetchColumn(
            "SELECT COUNT(*) FROM queuelog
             WHERE event = 'CONNECT' AND CAST(data1 AS UNSIGNED) <= ?
             AND time BETWEEN ? AND ?",
            [$slaThreshold, $start, $end]
        );

        $slaPercentage = $answeredCalls > 0
            ? round(($answeredInSla / $answeredCalls) * 100, 1)
            : 100;

        return [
            'total_calls' => $totalCalls,
            'answered_calls' => $answeredCalls,
            'abandoned_calls' => $abandonedCalls,
            'answer_rate' => $totalCalls > 0 ? round(($answeredCalls / $totalCalls) * 100, 1) : 0,
            'avg_wait_time' => $avgWait,
            'avg_talk_time' => $avgTalk,
            'sla_percentage' => $slaPercentage
        ];
    }

    /**
     * Get queue status
     */
    private function getQueueStatus(string $start, string $end): array
    {
        $queues = $this->db->fetchAll(
            "SELECT * FROM queue_settings WHERE is_monitored = 1 ORDER BY sort_order"
        );

        $result = [];

        foreach ($queues as $queue) {
            $queueName = $queue['queue_number'];

            // Get queue stats
            $stats = $this->cdrDb->fetch(
                "SELECT
                    COUNT(CASE WHEN event = 'ENTERQUEUE' THEN 1 END) as total,
                    COUNT(CASE WHEN event = 'CONNECT' THEN 1 END) as answered,
                    COUNT(CASE WHEN event = 'ABANDON' THEN 1 END) as abandoned,
                    AVG(CASE WHEN event = 'CONNECT' THEN CAST(data1 AS UNSIGNED) END) as avg_wait
                FROM queuelog
                WHERE queuename = ? AND time BETWEEN ? AND ?",
                [$queueName, $start, $end]
            );

            // Calculate SLA
            $slaThreshold = $queue['sla_threshold_seconds'];
            $withinSla = (int) $this->cdrDb->fetchColumn(
                "SELECT COUNT(*) FROM queuelog
                 WHERE queuename = ? AND event = 'CONNECT'
                 AND CAST(data1 AS UNSIGNED) <= ? AND time BETWEEN ? AND ?",
                [$queueName, $slaThreshold, $start, $end]
            );

            $answered = (int) ($stats['answered'] ?? 0);
            $slaPercentage = $answered > 0 ? round(($withinSla / $answered) * 100, 1) : 100;

            // Determine status class
            $statusClass = 'good';
            if ($slaPercentage < 80) $statusClass = 'warning';
            if ($slaPercentage < 60) $statusClass = 'critical';

            $result[] = [
                'queue_number' => $queueName,
                'display_name' => $queue['display_name'],
                'color_code' => $queue['color_code'],
                'total' => (int) ($stats['total'] ?? 0),
                'answered' => $answered,
                'abandoned' => (int) ($stats['abandoned'] ?? 0),
                'waiting' => 0, // Would need AMI for real-time
                'avg_wait' => (int) ($stats['avg_wait'] ?? 0),
                'sla_percentage' => $slaPercentage,
                'status_class' => $statusClass
            ];
        }

        return $result;
    }

    /**
     * Get agent status
     */
    private function getAgentStatus(): array
    {
        // Get configured agents
        $agents = $this->db->fetchAll(
            "SELECT * FROM agent_settings WHERE is_monitored = 1 ORDER BY display_name"
        );

        $result = [
            'available' => 0,
            'oncall' => 0,
            'paused' => 0,
            'wrapup' => 0,
            'offline' => 0,
            'list' => []
        ];

        foreach ($agents as $agent) {
            // For now, use static data - would need AMI for real-time status
            // This would be replaced with actual AMI queue member status

            $status = 'available'; // Default status
            $statusText = 'Available';

            // Check last activity in queue log
            $lastActivity = $this->cdrDb->fetch(
                "SELECT event, time FROM queuelog
                 WHERE agent LIKE ? ORDER BY time DESC LIMIT 1",
                ['%' . $agent['extension'] . '%']
            );

            if ($lastActivity) {
                $minutesAgo = (time() - strtotime($lastActivity['time'])) / 60;

                if ($lastActivity['event'] === 'PAUSEALL' || $lastActivity['event'] === 'PAUSE') {
                    $status = 'paused';
                    $statusText = 'Paused';
                } elseif ($lastActivity['event'] === 'REMOVEMEMBER' || $minutesAgo > 60) {
                    $status = 'offline';
                    $statusText = 'Offline';
                }
            } else {
                $status = 'offline';
                $statusText = 'Offline';
            }

            $result[$status]++;
            $result['list'][] = [
                'extension' => $agent['extension'],
                'name' => $agent['display_name'],
                'status' => $status,
                'status_text' => $statusText,
                'team' => $agent['team']
            ];
        }

        return $result;
    }
}
