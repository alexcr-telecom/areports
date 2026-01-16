<?php
/**
 * Dashboard Controller
 * Main dashboard with real-time statistics
 */

namespace aReports\Controllers;

use aReports\Core\Controller;

class DashboardController extends Controller
{
    /**
     * Show the main dashboard
     */
    public function index(): void
    {
        $this->requirePermission('dashboard.view');

        // Get today's date range
        $today = date('Y-m-d');
        $todayStart = $today . ' 00:00:00';
        $todayEnd = $today . ' 23:59:59';

        // Get statistics from CDR
        $stats = $this->getTodayStats($todayStart, $todayEnd);

        // Get queue summary
        $queues = $this->getQueueSummary($todayStart, $todayEnd);

        // Get recent calls
        $recentCalls = $this->getRecentCalls(10);

        // Get hourly call volume
        $hourlyVolume = $this->getHourlyVolume($todayStart, $todayEnd);

        $this->render('dashboard/index', [
            'title' => 'Dashboard',
            'currentPage' => 'dashboard',
            'stats' => $stats,
            'queues' => $queues,
            'recentCalls' => $recentCalls,
            'hourlyVolume' => $hourlyVolume
        ]);
    }

    /**
     * Get dashboard data for AJAX refresh
     */
    public function getData(): void
    {
        $this->requirePermission('dashboard.view');

        $today = date('Y-m-d');
        $todayStart = $today . ' 00:00:00';
        $todayEnd = $today . ' 23:59:59';

        $this->json([
            'stats' => $this->getTodayStats($todayStart, $todayEnd),
            'queues' => $this->getQueueSummary($todayStart, $todayEnd),
            'recentCalls' => $this->getRecentCalls(10),
            'hourlyVolume' => $this->getHourlyVolume($todayStart, $todayEnd)
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

        // Abandoned/Unanswered calls
        $abandonedCalls = (int) $this->cdrDb->fetchColumn(
            "SELECT COUNT(*) FROM cdr WHERE calldate BETWEEN ? AND ? AND disposition IN ('NO ANSWER', 'BUSY', 'FAILED')",
            [$start, $end]
        );

        // Average duration
        $avgDuration = (int) $this->cdrDb->fetchColumn(
            "SELECT AVG(duration) FROM cdr WHERE calldate BETWEEN ? AND ? AND disposition = 'ANSWERED'",
            [$start, $end]
        );

        // Average talk time (billsec)
        $avgTalkTime = (int) $this->cdrDb->fetchColumn(
            "SELECT AVG(billsec) FROM cdr WHERE calldate BETWEEN ? AND ? AND disposition = 'ANSWERED'",
            [$start, $end]
        );

        // Calculate answer rate
        $answerRate = $totalCalls > 0 ? round(($answeredCalls / $totalCalls) * 100, 1) : 0;

        // Get yesterday's stats for comparison
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $yesterdayStart = $yesterday . ' 00:00:00';
        $yesterdayEnd = $yesterday . ' 23:59:59';

        $yesterdayTotal = (int) $this->cdrDb->fetchColumn(
            "SELECT COUNT(*) FROM cdr WHERE calldate BETWEEN ? AND ?",
            [$yesterdayStart, $yesterdayEnd]
        );

        // Calculate change
        $callsChange = $yesterdayTotal > 0
            ? round((($totalCalls - $yesterdayTotal) / $yesterdayTotal) * 100, 1)
            : 0;

        return [
            'total_calls' => $totalCalls,
            'answered_calls' => $answeredCalls,
            'abandoned_calls' => $abandonedCalls,
            'answer_rate' => $answerRate,
            'avg_duration' => $avgDuration,
            'avg_talk_time' => $avgTalkTime,
            'calls_change' => $callsChange
        ];
    }

    /**
     * Get queue summary
     */
    private function getQueueSummary(string $start, string $end): array
    {
        // Get configured queues
        $queues = $this->db->fetchAll("SELECT * FROM queue_settings WHERE is_monitored = 1 ORDER BY sort_order");

        $result = [];

        foreach ($queues as $queue) {
            $queueName = $queue['queue_number'];

            // Get queue stats from queuelog
            $stats = $this->cdrDb->fetch(
                "SELECT
                    COUNT(CASE WHEN event = 'ENTERQUEUE' THEN 1 END) as total_calls,
                    COUNT(CASE WHEN event = 'CONNECT' THEN 1 END) as answered,
                    COUNT(CASE WHEN event = 'ABANDON' THEN 1 END) as abandoned,
                    AVG(CASE WHEN event = 'CONNECT' THEN CAST(data1 AS UNSIGNED) END) as avg_wait_time
                FROM queuelog
                WHERE queuename = ? AND time BETWEEN ? AND ?",
                [$queueName, $start, $end]
            );

            // Calculate SLA (answered within threshold)
            $slaThreshold = $queue['sla_threshold_seconds'];
            $withinSla = (int) $this->cdrDb->fetchColumn(
                "SELECT COUNT(*) FROM queuelog
                WHERE queuename = ? AND event = 'CONNECT' AND CAST(data1 AS UNSIGNED) <= ?
                AND time BETWEEN ? AND ?",
                [$queueName, $slaThreshold, $start, $end]
            );

            $answered = (int) ($stats['answered'] ?? 0);
            $slaPercentage = $answered > 0 ? round(($withinSla / $answered) * 100, 1) : 100;

            $result[] = [
                'id' => $queue['id'],
                'queue_number' => $queueName,
                'display_name' => $queue['display_name'],
                'color_code' => $queue['color_code'],
                'total_calls' => (int) ($stats['total_calls'] ?? 0),
                'answered' => $answered,
                'abandoned' => (int) ($stats['abandoned'] ?? 0),
                'avg_wait_time' => (int) ($stats['avg_wait_time'] ?? 0),
                'sla_percentage' => $slaPercentage,
                'sla_threshold' => $slaThreshold
            ];
        }

        return $result;
    }

    /**
     * Get recent calls
     */
    private function getRecentCalls(int $limit): array
    {
        $calls = $this->cdrDb->fetchAll(
            "SELECT calldate, src, dst, duration, billsec, disposition, did, dcontext
            FROM cdr
            ORDER BY calldate DESC
            LIMIT ?",
            [$limit]
        );

        return array_map(function ($call) {
            return [
                'time' => date('H:i:s', strtotime($call['calldate'])),
                'date' => date('d/m/Y', strtotime($call['calldate'])),
                'src' => $call['src'],
                'dst' => $call['dst'],
                'did' => $call['did'],
                'duration' => $this->formatDuration($call['duration']),
                'talk_time' => $this->formatDuration($call['billsec']),
                'disposition' => $call['disposition'],
                'context' => $call['dcontext']
            ];
        }, $calls);
    }

    /**
     * Get hourly call volume
     */
    private function getHourlyVolume(string $start, string $end): array
    {
        $hourly = $this->cdrDb->fetchAll(
            "SELECT HOUR(calldate) as hour,
                    COUNT(*) as total,
                    SUM(CASE WHEN disposition = 'ANSWERED' THEN 1 ELSE 0 END) as answered
            FROM cdr
            WHERE calldate BETWEEN ? AND ?
            GROUP BY HOUR(calldate)
            ORDER BY hour",
            [$start, $end]
        );

        // Fill in missing hours
        $result = [];
        for ($h = 0; $h < 24; $h++) {
            $result[$h] = ['hour' => $h, 'total' => 0, 'answered' => 0];
        }

        foreach ($hourly as $row) {
            $result[(int)$row['hour']] = [
                'hour' => (int) $row['hour'],
                'total' => (int) $row['total'],
                'answered' => (int) $row['answered']
            ];
        }

        return array_values($result);
    }

    /**
     * Format duration in seconds to readable format
     */
    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return '0:' . str_pad($seconds, 2, '0', STR_PAD_LEFT);
        }

        $minutes = floor($seconds / 60);
        $secs = $seconds % 60;

        if ($minutes < 60) {
            return $minutes . ':' . str_pad($secs, 2, '0', STR_PAD_LEFT);
        }

        $hours = floor($minutes / 60);
        $mins = $minutes % 60;

        return $hours . ':' . str_pad($mins, 2, '0', STR_PAD_LEFT) . ':' . str_pad($secs, 2, '0', STR_PAD_LEFT);
    }
}
