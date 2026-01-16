<?php
/**
 * Queue Service
 * Handles queue statistics from queuelog table
 */

namespace aReports\Services;

use aReports\Core\App;

class QueueService
{
    private \PDO $cdrDb;
    private \PDO $appDb;

    public function __construct()
    {
        $app = App::getInstance();
        $this->cdrDb = $app->getCdrDb()->getPdo();
        $this->appDb = $app->getDb()->getPdo();
    }

    /**
     * Get queue summary statistics
     */
    public function getQueueSummary(string $dateFrom, string $dateTo, ?string $queueFilter = null): array
    {
        $where = "DATE(time) BETWEEN ? AND ?";
        $params = [$dateFrom, $dateTo];

        if ($queueFilter) {
            $where .= " AND queuename = ?";
            $params[] = $queueFilter;
        }

        $sql = "SELECT
                    queuename,
                    COUNT(CASE WHEN event = 'ENTERQUEUE' THEN 1 END) as total_calls,
                    COUNT(CASE WHEN event = 'CONNECT' THEN 1 END) as answered,
                    COUNT(CASE WHEN event = 'ABANDON' THEN 1 END) as abandoned,
                    COUNT(CASE WHEN event IN ('EXITWITHTIMEOUT', 'EXITEMPTY') THEN 1 END) as timeout,
                    AVG(CASE WHEN event = 'CONNECT' THEN CAST(data1 AS UNSIGNED) END) as avg_wait_time,
                    AVG(CASE WHEN event = 'CONNECT' THEN CAST(data2 AS UNSIGNED) END) as avg_talk_time,
                    MAX(CASE WHEN event = 'CONNECT' THEN CAST(data1 AS UNSIGNED) END) as max_wait_time,
                    MIN(CASE WHEN event = 'CONNECT' THEN CAST(data1 AS UNSIGNED) END) as min_wait_time
                FROM queuelog
                WHERE {$where}
                GROUP BY queuename
                ORDER BY queuename";

        $stmt = $this->cdrDb->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Get queue settings for display names
        $queueSettings = $this->getQueueSettings();

        foreach ($results as &$row) {
            $row['display_name'] = $queueSettings[$row['queuename']]['display_name'] ?? $row['queuename'];
            $row['total_calls'] = (int) ($row['total_calls'] ?? 0);
            $row['answered'] = (int) ($row['answered'] ?? 0);
            $row['abandoned'] = (int) ($row['abandoned'] ?? 0);
            $row['timeout'] = (int) ($row['timeout'] ?? 0);
            $row['avg_wait_time'] = round($row['avg_wait_time'] ?? 0);
            $row['avg_talk_time'] = round($row['avg_talk_time'] ?? 0);
            $row['max_wait_time'] = (int) ($row['max_wait_time'] ?? 0);
            $row['min_wait_time'] = (int) ($row['min_wait_time'] ?? 0);
            $row['answer_rate'] = $row['total_calls'] > 0
                ? round(($row['answered'] / $row['total_calls']) * 100, 1)
                : 0;
            $row['abandon_rate'] = $row['total_calls'] > 0
                ? round(($row['abandoned'] / $row['total_calls']) * 100, 1)
                : 0;
        }

        return $results;
    }

    /**
     * Get queue SLA statistics
     */
    public function getQueueSLA(string $dateFrom, string $dateTo, int $slaThreshold = 20): array
    {
        $sql = "SELECT
                    queuename,
                    COUNT(*) as total_answered,
                    SUM(CASE WHEN CAST(data1 AS UNSIGNED) <= ? THEN 1 ELSE 0 END) as within_sla
                FROM queuelog
                WHERE DATE(time) BETWEEN ? AND ?
                AND event = 'CONNECT'
                GROUP BY queuename
                ORDER BY queuename";

        $stmt = $this->cdrDb->prepare($sql);
        $stmt->execute([$slaThreshold, $dateFrom, $dateTo]);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $queueSettings = $this->getQueueSettings();

        foreach ($results as &$row) {
            $row['display_name'] = $queueSettings[$row['queuename']]['display_name'] ?? $row['queuename'];
            $row['sla_threshold'] = $queueSettings[$row['queuename']]['sla_threshold'] ?? $slaThreshold;
            $row['total_answered'] = (int) $row['total_answered'];
            $row['within_sla'] = (int) $row['within_sla'];
            $row['outside_sla'] = $row['total_answered'] - $row['within_sla'];
            $row['sla_percentage'] = $row['total_answered'] > 0
                ? round(($row['within_sla'] / $row['total_answered']) * 100, 1)
                : 0;
        }

        return $results;
    }

    /**
     * Get queue hourly breakdown
     */
    public function getQueueHourly(string $date, ?string $queueFilter = null): array
    {
        $where = "DATE(time) = ?";
        $params = [$date];

        if ($queueFilter) {
            $where .= " AND queuename = ?";
            $params[] = $queueFilter;
        }

        $sql = "SELECT
                    HOUR(time) as hour,
                    COUNT(CASE WHEN event = 'ENTERQUEUE' THEN 1 END) as total_calls,
                    COUNT(CASE WHEN event = 'CONNECT' THEN 1 END) as answered,
                    COUNT(CASE WHEN event = 'ABANDON' THEN 1 END) as abandoned,
                    AVG(CASE WHEN event = 'CONNECT' THEN CAST(data1 AS UNSIGNED) END) as avg_wait
                FROM queuelog
                WHERE {$where}
                GROUP BY HOUR(time)
                ORDER BY hour";

        $stmt = $this->cdrDb->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Fill in missing hours
        $hourlyData = [];
        for ($h = 0; $h < 24; $h++) {
            $hourlyData[$h] = [
                'hour' => $h,
                'total_calls' => 0,
                'answered' => 0,
                'abandoned' => 0,
                'avg_wait' => 0
            ];
        }

        foreach ($results as $row) {
            $hourlyData[$row['hour']] = [
                'hour' => (int) $row['hour'],
                'total_calls' => (int) ($row['total_calls'] ?? 0),
                'answered' => (int) ($row['answered'] ?? 0),
                'abandoned' => (int) ($row['abandoned'] ?? 0),
                'avg_wait' => round($row['avg_wait'] ?? 0)
            ];
        }

        return array_values($hourlyData);
    }

    /**
     * Get abandoned call details
     */
    public function getAbandonedCalls(string $dateFrom, string $dateTo, ?string $queueFilter = null, int $limit = 100, int $offset = 0): array
    {
        $where = "DATE(time) BETWEEN ? AND ? AND event = 'ABANDON'";
        $params = [$dateFrom, $dateTo];

        if ($queueFilter) {
            $where .= " AND queuename = ?";
            $params[] = $queueFilter;
        }

        // Count total
        $countSql = "SELECT COUNT(*) FROM queuelog WHERE {$where}";
        $stmt = $this->cdrDb->prepare($countSql);
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        // Get records
        $sql = "SELECT
                    time,
                    queuename,
                    callid,
                    data1 as wait_position,
                    data2 as original_position,
                    data3 as wait_time
                FROM queuelog
                WHERE {$where}
                ORDER BY time DESC
                LIMIT ? OFFSET ?";

        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->cdrDb->prepare($sql);
        $stmt->execute($params);
        $records = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'records' => $records,
            'total' => $total
        ];
    }

    /**
     * Get wait time distribution
     */
    public function getWaitTimeDistribution(string $dateFrom, string $dateTo, ?string $queueFilter = null): array
    {
        $where = "DATE(time) BETWEEN ? AND ? AND event = 'CONNECT'";
        $params = [$dateFrom, $dateTo];

        if ($queueFilter) {
            $where .= " AND queuename = ?";
            $params[] = $queueFilter;
        }

        $sql = "SELECT
                    CASE
                        WHEN CAST(data1 AS UNSIGNED) <= 10 THEN '0-10s'
                        WHEN CAST(data1 AS UNSIGNED) <= 20 THEN '11-20s'
                        WHEN CAST(data1 AS UNSIGNED) <= 30 THEN '21-30s'
                        WHEN CAST(data1 AS UNSIGNED) <= 60 THEN '31-60s'
                        WHEN CAST(data1 AS UNSIGNED) <= 120 THEN '1-2min'
                        ELSE '2min+'
                    END as wait_range,
                    COUNT(*) as count
                FROM queuelog
                WHERE {$where}
                GROUP BY wait_range
                ORDER BY MIN(CAST(data1 AS UNSIGNED))";

        $stmt = $this->cdrDb->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get today's queue totals for dashboard
     */
    public function getTodayTotals(): array
    {
        $today = date('Y-m-d');

        $sql = "SELECT
                    COUNT(CASE WHEN event = 'ENTERQUEUE' THEN 1 END) as total_calls,
                    COUNT(CASE WHEN event = 'CONNECT' THEN 1 END) as answered,
                    COUNT(CASE WHEN event = 'ABANDON' THEN 1 END) as abandoned,
                    AVG(CASE WHEN event = 'CONNECT' THEN CAST(data1 AS UNSIGNED) END) as avg_wait,
                    AVG(CASE WHEN event = 'CONNECT' THEN CAST(data2 AS UNSIGNED) END) as avg_talk
                FROM queuelog
                WHERE DATE(time) = ?";

        $stmt = $this->cdrDb->prepare($sql);
        $stmt->execute([$today]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return [
            'total_calls' => (int) ($result['total_calls'] ?? 0),
            'answered' => (int) ($result['answered'] ?? 0),
            'abandoned' => (int) ($result['abandoned'] ?? 0),
            'avg_wait' => round($result['avg_wait'] ?? 0),
            'avg_talk' => round($result['avg_talk'] ?? 0),
            'answer_rate' => $result['total_calls'] > 0
                ? round(($result['answered'] / $result['total_calls']) * 100, 1)
                : 0
        ];
    }

    /**
     * Get queue list for dropdowns
     */
    public function getQueueList(): array
    {
        // Get from queuelog
        $sql = "SELECT DISTINCT queuename FROM queuelog ORDER BY queuename";
        $stmt = $this->cdrDb->query($sql);
        $queues = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        // Get settings
        $queueSettings = $this->getQueueSettings();

        $result = [];
        foreach ($queues as $queue) {
            $result[] = [
                'name' => $queue,
                'display_name' => $queueSettings[$queue]['display_name'] ?? $queue
            ];
        }

        return $result;
    }

    /**
     * Get queue settings from app database
     */
    public function getQueueSettings(): array
    {
        $sql = "SELECT * FROM queue_settings";
        $stmt = $this->appDb->query($sql);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['queue_number']] = $row;
        }

        return $settings;
    }

    /**
     * Update queue settings
     */
    public function updateQueueSettings(string $queueName, array $data): bool
    {
        $existing = $this->appDb->prepare("SELECT id FROM queue_settings WHERE queue_number = ?");
        $existing->execute([$queueName]);

        if ($existing->fetch()) {
            $sql = "UPDATE queue_settings SET
                        display_name = ?,
                        sla_threshold = ?,
                        sla_warning = ?,
                        color = ?,
                        updated_at = NOW()
                    WHERE queue_number = ?";
            $stmt = $this->appDb->prepare($sql);
            return $stmt->execute([
                $data['display_name'],
                $data['sla_threshold'] ?? 20,
                $data['sla_warning'] ?? 80,
                $data['color'] ?? '#007bff',
                $queueName
            ]);
        } else {
            $sql = "INSERT INTO queue_settings (queue_number, display_name, sla_threshold, sla_warning, color)
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $this->appDb->prepare($sql);
            return $stmt->execute([
                $queueName,
                $data['display_name'],
                $data['sla_threshold'] ?? 20,
                $data['sla_warning'] ?? 80,
                $data['color'] ?? '#007bff'
            ]);
        }
    }

    /**
     * Get queue daily trend
     */
    public function getDailyTrend(string $dateFrom, string $dateTo, ?string $queueFilter = null): array
    {
        $where = "DATE(time) BETWEEN ? AND ?";
        $params = [$dateFrom, $dateTo];

        if ($queueFilter) {
            $where .= " AND queuename = ?";
            $params[] = $queueFilter;
        }

        $sql = "SELECT
                    DATE(time) as date,
                    COUNT(CASE WHEN event = 'ENTERQUEUE' THEN 1 END) as total_calls,
                    COUNT(CASE WHEN event = 'CONNECT' THEN 1 END) as answered,
                    COUNT(CASE WHEN event = 'ABANDON' THEN 1 END) as abandoned,
                    AVG(CASE WHEN event = 'CONNECT' THEN CAST(data1 AS UNSIGNED) END) as avg_wait
                FROM queuelog
                WHERE {$where}
                GROUP BY DATE(time)
                ORDER BY date";

        $stmt = $this->cdrDb->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Export queue report to CSV
     */
    public function exportToCSV(string $dateFrom, string $dateTo, ?string $queueFilter = null): string
    {
        $data = $this->getQueueSummary($dateFrom, $dateTo, $queueFilter);

        $output = fopen('php://temp', 'r+');

        fputcsv($output, [
            'Queue', 'Total Calls', 'Answered', 'Abandoned', 'Timeout',
            'Answer Rate', 'Abandon Rate', 'Avg Wait (s)', 'Avg Talk (s)',
            'Max Wait (s)', 'Min Wait (s)'
        ]);

        foreach ($data as $row) {
            fputcsv($output, [
                $row['display_name'],
                $row['total_calls'],
                $row['answered'],
                $row['abandoned'],
                $row['timeout'],
                $row['answer_rate'] . '%',
                $row['abandon_rate'] . '%',
                $row['avg_wait_time'],
                $row['avg_talk_time'],
                $row['max_wait_time'],
                $row['min_wait_time']
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
