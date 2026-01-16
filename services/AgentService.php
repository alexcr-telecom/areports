<?php
/**
 * Agent Service
 * Handles agent performance data from queuelog table
 */

namespace aReports\Services;

use aReports\Core\App;

class AgentService
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
     * Get agent performance summary
     */
    public function getAgentPerformance(string $dateFrom, string $dateTo, ?string $agentFilter = null): array
    {
        $where = "DATE(time) BETWEEN ? AND ? AND agent != 'NONE' AND agent != ''";
        $params = [$dateFrom, $dateTo];

        if ($agentFilter) {
            $where .= " AND agent = ?";
            $params[] = $agentFilter;
        }

        $sql = "SELECT
                    agent,
                    COUNT(CASE WHEN event = 'CONNECT' THEN 1 END) as calls_handled,
                    COUNT(CASE WHEN event = 'RINGNOANSWER' THEN 1 END) as calls_missed,
                    COUNT(CASE WHEN event = 'COMPLETECALLER' THEN 1 END) as completed_caller,
                    COUNT(CASE WHEN event = 'COMPLETEAGENT' THEN 1 END) as completed_agent,
                    SUM(CASE WHEN event IN ('COMPLETECALLER', 'COMPLETEAGENT') THEN CAST(data2 AS UNSIGNED) ELSE 0 END) as total_talk_time,
                    AVG(CASE WHEN event IN ('COMPLETECALLER', 'COMPLETEAGENT') THEN CAST(data2 AS UNSIGNED) END) as avg_talk_time,
                    SUM(CASE WHEN event IN ('COMPLETECALLER', 'COMPLETEAGENT') THEN CAST(data1 AS UNSIGNED) ELSE 0 END) as total_hold_time,
                    AVG(CASE WHEN event IN ('COMPLETECALLER', 'COMPLETEAGENT') THEN CAST(data1 AS UNSIGNED) END) as avg_hold_time
                FROM queuelog
                WHERE {$where}
                GROUP BY agent
                ORDER BY calls_handled DESC";

        $stmt = $this->cdrDb->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Get agent settings for display names
        $agentSettings = $this->getAgentSettings();

        foreach ($results as &$row) {
            $agentId = $this->extractAgentId($row['agent']);
            $row['display_name'] = $agentSettings[$agentId]['display_name'] ?? $row['agent'];
            $row['calls_handled'] = (int) ($row['calls_handled'] ?? 0);
            $row['calls_missed'] = (int) ($row['calls_missed'] ?? 0);
            $row['completed_caller'] = (int) ($row['completed_caller'] ?? 0);
            $row['completed_agent'] = (int) ($row['completed_agent'] ?? 0);
            $row['total_talk_time'] = (int) ($row['total_talk_time'] ?? 0);
            $row['avg_talk_time'] = round($row['avg_talk_time'] ?? 0);
            $row['total_hold_time'] = (int) ($row['total_hold_time'] ?? 0);
            $row['avg_hold_time'] = round($row['avg_hold_time'] ?? 0);
            $row['answer_rate'] = ($row['calls_handled'] + $row['calls_missed']) > 0
                ? round(($row['calls_handled'] / ($row['calls_handled'] + $row['calls_missed'])) * 100, 1)
                : 0;
        }

        return $results;
    }

    /**
     * Get agent login/logout activity
     */
    public function getAgentActivity(string $dateFrom, string $dateTo, ?string $agentFilter = null): array
    {
        $where = "DATE(time) BETWEEN ? AND ? AND event IN ('ADDMEMBER', 'REMOVEMEMBER', 'PAUSE', 'UNPAUSE')";
        $params = [$dateFrom, $dateTo];

        if ($agentFilter) {
            $where .= " AND agent = ?";
            $params[] = $agentFilter;
        }

        $sql = "SELECT
                    time,
                    queuename,
                    agent,
                    event,
                    data1
                FROM queuelog
                WHERE {$where}
                ORDER BY time DESC
                LIMIT 500";

        $stmt = $this->cdrDb->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get agent availability statistics
     */
    public function getAgentAvailability(string $dateFrom, string $dateTo, ?string $agentFilter = null): array
    {
        $where = "DATE(time) BETWEEN ? AND ? AND agent != 'NONE' AND agent != ''";
        $params = [$dateFrom, $dateTo];

        if ($agentFilter) {
            $where .= " AND agent = ?";
            $params[] = $agentFilter;
        }

        // Calculate login time from ADDMEMBER/REMOVEMEMBER pairs
        $sql = "SELECT
                    agent,
                    COUNT(CASE WHEN event = 'ADDMEMBER' THEN 1 END) as login_count,
                    COUNT(CASE WHEN event = 'REMOVEMEMBER' THEN 1 END) as logout_count,
                    COUNT(CASE WHEN event = 'PAUSE' THEN 1 END) as pause_count,
                    COUNT(CASE WHEN event = 'UNPAUSE' THEN 1 END) as unpause_count
                FROM queuelog
                WHERE {$where}
                AND event IN ('ADDMEMBER', 'REMOVEMEMBER', 'PAUSE', 'UNPAUSE')
                GROUP BY agent";

        $stmt = $this->cdrDb->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get agent efficiency report
     */
    public function getAgentEfficiency(string $dateFrom, string $dateTo, ?string $agentFilter = null): array
    {
        $where = "DATE(time) BETWEEN ? AND ? AND agent != 'NONE' AND agent != ''";
        $params = [$dateFrom, $dateTo];

        if ($agentFilter) {
            $where .= " AND agent = ?";
            $params[] = $agentFilter;
        }

        $sql = "SELECT
                    agent,
                    queuename,
                    COUNT(CASE WHEN event = 'CONNECT' THEN 1 END) as calls_handled,
                    SUM(CASE WHEN event IN ('COMPLETECALLER', 'COMPLETEAGENT') THEN CAST(data2 AS UNSIGNED) ELSE 0 END) as talk_time,
                    AVG(CASE WHEN event = 'CONNECT' THEN CAST(data3 AS UNSIGNED) END) as avg_ring_time
                FROM queuelog
                WHERE {$where}
                GROUP BY agent, queuename
                ORDER BY agent, calls_handled DESC";

        $stmt = $this->cdrDb->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get agent hourly activity
     */
    public function getAgentHourly(string $date, string $agent): array
    {
        $sql = "SELECT
                    HOUR(time) as hour,
                    COUNT(CASE WHEN event = 'CONNECT' THEN 1 END) as calls_handled,
                    COUNT(CASE WHEN event = 'RINGNOANSWER' THEN 1 END) as calls_missed,
                    SUM(CASE WHEN event IN ('COMPLETECALLER', 'COMPLETEAGENT') THEN CAST(data2 AS UNSIGNED) ELSE 0 END) as talk_time
                FROM queuelog
                WHERE DATE(time) = ?
                AND agent = ?
                GROUP BY HOUR(time)
                ORDER BY hour";

        $stmt = $this->cdrDb->prepare($sql);
        $stmt->execute([$date, $agent]);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Fill in missing hours
        $hourlyData = [];
        for ($h = 0; $h < 24; $h++) {
            $hourlyData[$h] = [
                'hour' => $h,
                'calls_handled' => 0,
                'calls_missed' => 0,
                'talk_time' => 0
            ];
        }

        foreach ($results as $row) {
            $hourlyData[$row['hour']] = [
                'hour' => (int) $row['hour'],
                'calls_handled' => (int) ($row['calls_handled'] ?? 0),
                'calls_missed' => (int) ($row['calls_missed'] ?? 0),
                'talk_time' => (int) ($row['talk_time'] ?? 0)
            ];
        }

        return array_values($hourlyData);
    }

    /**
     * Get agent daily trend
     */
    public function getAgentDailyTrend(string $dateFrom, string $dateTo, string $agent): array
    {
        $sql = "SELECT
                    DATE(time) as date,
                    COUNT(CASE WHEN event = 'CONNECT' THEN 1 END) as calls_handled,
                    COUNT(CASE WHEN event = 'RINGNOANSWER' THEN 1 END) as calls_missed,
                    SUM(CASE WHEN event IN ('COMPLETECALLER', 'COMPLETEAGENT') THEN CAST(data2 AS UNSIGNED) ELSE 0 END) as talk_time
                FROM queuelog
                WHERE DATE(time) BETWEEN ? AND ?
                AND agent = ?
                GROUP BY DATE(time)
                ORDER BY date";

        $stmt = $this->cdrDb->prepare($sql);
        $stmt->execute([$dateFrom, $dateTo, $agent]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get agent list
     */
    public function getAgentList(): array
    {
        $sql = "SELECT DISTINCT agent FROM queuelog
                WHERE agent != 'NONE' AND agent != '' AND agent IS NOT NULL
                ORDER BY agent";
        $stmt = $this->cdrDb->query($sql);
        $agents = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $agentSettings = $this->getAgentSettings();

        $result = [];
        foreach ($agents as $agent) {
            $agentId = $this->extractAgentId($agent);
            $result[] = [
                'agent' => $agent,
                'extension' => $agentId,
                'display_name' => $agentSettings[$agentId]['display_name'] ?? $agent
            ];
        }

        return $result;
    }

    /**
     * Get today's agent stats for dashboard
     */
    public function getTodayAgentStats(): array
    {
        $today = date('Y-m-d');

        $sql = "SELECT
                    agent,
                    COUNT(CASE WHEN event = 'CONNECT' THEN 1 END) as calls_handled,
                    SUM(CASE WHEN event IN ('COMPLETECALLER', 'COMPLETEAGENT') THEN CAST(data2 AS UNSIGNED) ELSE 0 END) as talk_time
                FROM queuelog
                WHERE DATE(time) = ?
                AND agent != 'NONE' AND agent != ''
                GROUP BY agent
                ORDER BY calls_handled DESC
                LIMIT 10";

        $stmt = $this->cdrDb->prepare($sql);
        $stmt->execute([$today]);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $agentSettings = $this->getAgentSettings();

        foreach ($results as &$row) {
            $agentId = $this->extractAgentId($row['agent']);
            $row['display_name'] = $agentSettings[$agentId]['display_name'] ?? $row['agent'];
        }

        return $results;
    }

    /**
     * Get agent settings from app database
     */
    public function getAgentSettings(): array
    {
        $sql = "SELECT * FROM agent_settings";
        $stmt = $this->appDb->query($sql);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['extension']] = $row;
        }

        return $settings;
    }

    /**
     * Update agent settings
     */
    public function updateAgentSettings(string $agentId, array $data): bool
    {
        $existing = $this->appDb->prepare("SELECT id FROM agent_settings WHERE extension = ?");
        $existing->execute([$agentId]);

        if ($existing->fetch()) {
            $sql = "UPDATE agent_settings SET
                        display_name = ?,
                        team = ?,
                        wrap_up_time = ?,
                        updated_at = NOW()
                    WHERE extension = ?";
            $stmt = $this->appDb->prepare($sql);
            return $stmt->execute([
                $data['display_name'],
                $data['team'] ?? null,
                $data['wrap_up_time'] ?? 0,
                $agentId
            ]);
        } else {
            $sql = "INSERT INTO agent_settings (extension, display_name, team, wrap_up_time)
                    VALUES (?, ?, ?, ?)";
            $stmt = $this->appDb->prepare($sql);
            return $stmt->execute([
                $agentId,
                $data['display_name'],
                $data['team'] ?? null,
                $data['wrap_up_time'] ?? 0
            ]);
        }
    }

    /**
     * Extract agent ID from agent string (e.g., "Local/1001@from-queue" -> "1001")
     */
    private function extractAgentId(string $agent): string
    {
        if (preg_match('/Local\/(\d+)@/', $agent, $matches)) {
            return $matches[1];
        }
        if (preg_match('/SIP\/(\d+)/', $agent, $matches)) {
            return $matches[1];
        }
        if (preg_match('/PJSIP\/(\d+)/', $agent, $matches)) {
            return $matches[1];
        }
        return $agent;
    }

    /**
     * Export agent report to CSV
     */
    public function exportToCSV(string $dateFrom, string $dateTo, ?string $agentFilter = null): string
    {
        $data = $this->getAgentPerformance($dateFrom, $dateTo, $agentFilter);

        $output = fopen('php://temp', 'r+');

        fputcsv($output, [
            'Agent', 'Calls Handled', 'Calls Missed', 'Answer Rate',
            'Total Talk Time (s)', 'Avg Talk Time (s)',
            'Total Hold Time (s)', 'Avg Hold Time (s)'
        ]);

        foreach ($data as $row) {
            fputcsv($output, [
                $row['display_name'],
                $row['calls_handled'],
                $row['calls_missed'],
                $row['answer_rate'] . '%',
                $row['total_talk_time'],
                $row['avg_talk_time'],
                $row['total_hold_time'],
                $row['avg_hold_time']
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
