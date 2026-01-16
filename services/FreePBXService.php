<?php
/**
 * FreePBX Service
 * Queries FreePBX asterisk database for extensions, queues, and agents
 */

namespace aReports\Services;

use aReports\Core\App;

class FreePBXService
{
    private \PDO $db;

    public function __construct()
    {
        $app = App::getInstance();
        $this->db = $app->getFreepbxDb()->getPdo();
    }

    /**
     * Get all extensions from FreePBX
     */
    public function getExtensions(): array
    {
        $sql = "SELECT extension, name, voicemail, outboundcid
                FROM users
                ORDER BY CAST(extension AS UNSIGNED)";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get extension by number
     */
    public function getExtension(string $extension): ?array
    {
        $sql = "SELECT extension, name, voicemail, outboundcid
                FROM users
                WHERE extension = ?";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$extension]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Get all queues from FreePBX
     */
    public function getQueues(): array
    {
        $sql = "SELECT extension, descr as name FROM queues_config ORDER BY extension";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get queue members for a specific queue
     */
    public function getQueueMembers(string $queueExtension): array
    {
        $sql = "SELECT data FROM queues_details
                WHERE id = ? AND keyword = 'member'";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$queueExtension]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $members = [];
        foreach ($rows as $row) {
            // Parse Local/EXTENSION@from-queue/n,0 format
            if (preg_match('/Local\/(\d+)@/', $row['data'], $matches)) {
                $ext = $matches[1];
                $extInfo = $this->getExtension($ext);
                $members[] = [
                    'extension' => $ext,
                    'name' => $extInfo['name'] ?? $ext,
                    'raw' => $row['data']
                ];
            }
        }

        return $members;
    }

    /**
     * Get all queue members across all queues
     */
    public function getAllQueueMembers(): array
    {
        $sql = "SELECT qc.extension as queue_ext, qc.descr as queue_name, qd.data as member_data
                FROM queues_config qc
                JOIN queues_details qd ON qc.extension = qd.id AND qd.keyword = 'member'
                ORDER BY qc.extension";

        $stmt = $this->db->query($sql);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $members = [];
        foreach ($rows as $row) {
            if (preg_match('/Local\/(\d+)@/', $row['member_data'], $matches)) {
                $ext = $matches[1];
                if (!isset($members[$ext])) {
                    $extInfo = $this->getExtension($ext);
                    $members[$ext] = [
                        'extension' => $ext,
                        'name' => $extInfo['name'] ?? $ext,
                        'queues' => []
                    ];
                }
                $members[$ext]['queues'][] = [
                    'extension' => $row['queue_ext'],
                    'name' => $row['queue_name']
                ];
            }
        }

        return array_values($members);
    }

    /**
     * Get extensions that are queue agents (members of at least one queue)
     */
    public function getQueueAgents(): array
    {
        $sql = "SELECT DISTINCT
                    SUBSTRING_INDEX(SUBSTRING_INDEX(qd.data, 'Local/', -1), '@', 1) as extension
                FROM queues_details qd
                WHERE qd.keyword = 'member'";

        $stmt = $this->db->query($sql);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $agents = [];
        foreach ($rows as $row) {
            $ext = $row['extension'];
            if (is_numeric($ext)) {
                $extInfo = $this->getExtension($ext);
                $agents[] = [
                    'extension' => $ext,
                    'name' => $extInfo['name'] ?? $ext,
                    'display_name' => ($extInfo['name'] ?? $ext) . ' (' . $ext . ')'
                ];
            }
        }

        // Sort by extension
        usort($agents, function($a, $b) {
            return (int)$a['extension'] - (int)$b['extension'];
        });

        return $agents;
    }

    /**
     * Search extensions by name or number
     */
    public function searchExtensions(string $search): array
    {
        $sql = "SELECT extension, name, voicemail
                FROM users
                WHERE extension LIKE ? OR name LIKE ?
                ORDER BY CAST(extension AS UNSIGNED)
                LIMIT 20";

        $searchTerm = '%' . $search . '%';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$searchTerm, $searchTerm]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get SIP/PJSIP device information for an extension
     */
    public function getDeviceInfo(string $extension): ?array
    {
        // Try PJSIP first
        $sql = "SELECT id, keyword, data FROM pjsip WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$extension]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (!empty($rows)) {
            $device = ['type' => 'pjsip', 'extension' => $extension];
            foreach ($rows as $row) {
                $device[$row['keyword']] = $row['data'];
            }
            return $device;
        }

        // Try SIP
        $sql = "SELECT id, keyword, data FROM sip WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$extension]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (!empty($rows)) {
            $device = ['type' => 'sip', 'extension' => $extension];
            foreach ($rows as $row) {
                $device[$row['keyword']] = $row['data'];
            }
            return $device;
        }

        return null;
    }
}
