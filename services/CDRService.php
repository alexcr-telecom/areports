<?php
/**
 * CDR Service
 * Handles CDR data queries and statistics
 */

namespace aReports\Services;

use aReports\Core\App;

class CDRService
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
     * Get CDR records with filters
     */
    public function getCDRList(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['date_from'])) {
            $where[] = 'calldate >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'calldate <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        if (!empty($filters['src'])) {
            $where[] = 'src LIKE ?';
            $params[] = '%' . $filters['src'] . '%';
        }

        if (!empty($filters['dst'])) {
            $where[] = 'dst LIKE ?';
            $params[] = '%' . $filters['dst'] . '%';
        }

        if (!empty($filters['disposition'])) {
            $where[] = 'disposition = ?';
            $params[] = $filters['disposition'];
        }

        if (!empty($filters['dcontext'])) {
            $where[] = 'dcontext = ?';
            $params[] = $filters['dcontext'];
        }

        if (!empty($filters['search'])) {
            $where[] = '(src LIKE ? OR dst LIKE ? OR clid LIKE ? OR uniqueid LIKE ?)';
            $search = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$search, $search, $search, $search]);
        }

        $whereClause = implode(' AND ', $where);

        // Get total count
        $countSql = "SELECT COUNT(*) FROM cdr WHERE {$whereClause}";
        $stmt = $this->cdrDb->prepare($countSql);
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        // Get records
        $sql = "SELECT * FROM cdr WHERE {$whereClause} ORDER BY calldate DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->cdrDb->prepare($sql);
        $stmt->execute($params);
        $records = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'records' => $records,
            'total' => $total,
            'filtered' => $total
        ];
    }

    /**
     * Get CDR by uniqueid
     */
    public function getCDRByUniqueId(string $uniqueid): ?array
    {
        $stmt = $this->cdrDb->prepare("SELECT * FROM cdr WHERE uniqueid = ?");
        $stmt->execute([$uniqueid]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Get today's statistics
     */
    public function getTodayStats(): array
    {
        $today = date('Y-m-d');

        $sql = "SELECT
                    COUNT(*) as total_calls,
                    SUM(CASE WHEN disposition = 'ANSWERED' THEN 1 ELSE 0 END) as answered,
                    SUM(CASE WHEN disposition = 'NO ANSWER' THEN 1 ELSE 0 END) as no_answer,
                    SUM(CASE WHEN disposition = 'BUSY' THEN 1 ELSE 0 END) as busy,
                    SUM(CASE WHEN disposition = 'FAILED' THEN 1 ELSE 0 END) as failed,
                    AVG(CASE WHEN disposition = 'ANSWERED' THEN duration ELSE NULL END) as avg_duration,
                    SUM(CASE WHEN disposition = 'ANSWERED' THEN billsec ELSE 0 END) as total_talk_time
                FROM cdr
                WHERE DATE(calldate) = ?";

        $stmt = $this->cdrDb->prepare($sql);
        $stmt->execute([$today]);
        $stats = $stmt->fetch(\PDO::FETCH_ASSOC);

        return [
            'total_calls' => (int) ($stats['total_calls'] ?? 0),
            'answered' => (int) ($stats['answered'] ?? 0),
            'no_answer' => (int) ($stats['no_answer'] ?? 0),
            'busy' => (int) ($stats['busy'] ?? 0),
            'failed' => (int) ($stats['failed'] ?? 0),
            'avg_duration' => round($stats['avg_duration'] ?? 0),
            'total_talk_time' => (int) ($stats['total_talk_time'] ?? 0),
            'answer_rate' => $stats['total_calls'] > 0
                ? round(($stats['answered'] / $stats['total_calls']) * 100, 1)
                : 0
        ];
    }

    /**
     * Get hourly call volume
     */
    public function getHourlyVolume(string $date = null): array
    {
        $date = $date ?? date('Y-m-d');

        $sql = "SELECT
                    HOUR(calldate) as hour,
                    COUNT(*) as total,
                    SUM(CASE WHEN disposition = 'ANSWERED' THEN 1 ELSE 0 END) as answered
                FROM cdr
                WHERE DATE(calldate) = ?
                GROUP BY HOUR(calldate)
                ORDER BY hour";

        $stmt = $this->cdrDb->prepare($sql);
        $stmt->execute([$date]);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Fill in missing hours
        $hourlyData = array_fill(0, 24, ['total' => 0, 'answered' => 0]);
        foreach ($results as $row) {
            $hourlyData[$row['hour']] = [
                'total' => (int) $row['total'],
                'answered' => (int) $row['answered']
            ];
        }

        return $hourlyData;
    }

    /**
     * Get daily call volume for a date range
     */
    public function getDailyVolume(string $dateFrom, string $dateTo): array
    {
        $sql = "SELECT
                    DATE(calldate) as date,
                    COUNT(*) as total,
                    SUM(CASE WHEN disposition = 'ANSWERED' THEN 1 ELSE 0 END) as answered,
                    SUM(CASE WHEN disposition = 'NO ANSWER' THEN 1 ELSE 0 END) as no_answer,
                    AVG(CASE WHEN disposition = 'ANSWERED' THEN duration ELSE NULL END) as avg_duration
                FROM cdr
                WHERE DATE(calldate) BETWEEN ? AND ?
                GROUP BY DATE(calldate)
                ORDER BY date";

        $stmt = $this->cdrDb->prepare($sql);
        $stmt->execute([$dateFrom, $dateTo]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get call disposition breakdown
     */
    public function getDispositionBreakdown(string $dateFrom, string $dateTo): array
    {
        $sql = "SELECT
                    disposition,
                    COUNT(*) as count
                FROM cdr
                WHERE DATE(calldate) BETWEEN ? AND ?
                GROUP BY disposition
                ORDER BY count DESC";

        $stmt = $this->cdrDb->prepare($sql);
        $stmt->execute([$dateFrom, $dateTo]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get recent calls
     */
    public function getRecentCalls(int $limit = 10): array
    {
        $sql = "SELECT * FROM cdr ORDER BY calldate DESC LIMIT ?";
        $stmt = $this->cdrDb->prepare($sql);
        $stmt->execute([$limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get lost/abandoned calls
     */
    public function getLostCalls(string $dateFrom, string $dateTo, int $limit = 100, int $offset = 0): array
    {
        $where = "DATE(calldate) BETWEEN ? AND ? AND disposition != 'ANSWERED'";
        $params = [$dateFrom, $dateTo];

        // Count total
        $countSql = "SELECT COUNT(*) FROM cdr WHERE {$where}";
        $stmt = $this->cdrDb->prepare($countSql);
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        // Get records
        $sql = "SELECT * FROM cdr WHERE {$where} ORDER BY calldate DESC LIMIT ? OFFSET ?";
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
     * Get calls by extension
     */
    public function getCallsByExtension(string $dateFrom, string $dateTo): array
    {
        $sql = "SELECT
                    CASE
                        WHEN LENGTH(src) <= 5 THEN src
                        ELSE 'External'
                    END as extension,
                    COUNT(*) as outbound,
                    SUM(CASE WHEN disposition = 'ANSWERED' THEN billsec ELSE 0 END) as talk_time
                FROM cdr
                WHERE DATE(calldate) BETWEEN ? AND ?
                GROUP BY extension
                ORDER BY outbound DESC
                LIMIT 20";

        $stmt = $this->cdrDb->prepare($sql);
        $stmt->execute([$dateFrom, $dateTo]);
        $outbound = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $sql = "SELECT
                    CASE
                        WHEN LENGTH(dst) <= 5 THEN dst
                        ELSE 'External'
                    END as extension,
                    COUNT(*) as inbound,
                    SUM(CASE WHEN disposition = 'ANSWERED' THEN billsec ELSE 0 END) as talk_time
                FROM cdr
                WHERE DATE(calldate) BETWEEN ? AND ?
                GROUP BY extension
                ORDER BY inbound DESC
                LIMIT 20";

        $stmt = $this->cdrDb->prepare($sql);
        $stmt->execute([$dateFrom, $dateTo]);
        $inbound = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'outbound' => $outbound,
            'inbound' => $inbound
        ];
    }

    /**
     * Get unique contexts
     */
    public function getContexts(): array
    {
        $sql = "SELECT DISTINCT dcontext FROM cdr ORDER BY dcontext";
        $stmt = $this->cdrDb->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Get call detail with CEL events
     */
    public function getCallDetailWithCEL(string $uniqueid): array
    {
        $cdr = $this->getCDRByUniqueId($uniqueid);
        if (!$cdr) {
            return [];
        }

        // Get CEL events for this call
        $sql = "SELECT * FROM cel WHERE uniqueid = ? OR linkedid = ? ORDER BY eventtime";
        $stmt = $this->cdrDb->prepare($sql);
        $stmt->execute([$uniqueid, $uniqueid]);
        $celEvents = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'cdr' => $cdr,
            'cel' => $celEvents
        ];
    }

    /**
     * Export CDR to CSV
     */
    public function exportToCSV(array $filters = []): string
    {
        $result = $this->getCDRList($filters, 10000, 0);
        $records = $result['records'];

        $output = fopen('php://temp', 'r+');

        // Headers
        fputcsv($output, [
            'Date/Time', 'Source', 'Destination', 'Context',
            'Duration', 'Billable', 'Disposition', 'Unique ID'
        ]);

        foreach ($records as $record) {
            fputcsv($output, [
                $record['calldate'],
                $record['src'],
                $record['dst'],
                $record['dcontext'],
                $record['duration'],
                $record['billsec'],
                $record['disposition'],
                $record['uniqueid']
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
