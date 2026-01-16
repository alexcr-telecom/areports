<?php
/**
 * Base Model
 * All models extend this class
 */

namespace aReports\Core;

abstract class Model
{
    protected Database $db;
    protected string $table = '';
    protected string $primaryKey = 'id';
    protected array $fillable = [];
    protected array $hidden = ['password_hash'];
    protected bool $timestamps = true;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Find a record by ID
     */
    public function find(int $id): ?array
    {
        $sql = "SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = ?";
        $result = $this->db->fetch($sql, [$id]);
        return $result ? $this->hideFields($result) : null;
    }

    /**
     * Find a record by column value
     */
    public function findBy(string $column, mixed $value): ?array
    {
        $sql = "SELECT * FROM `{$this->table}` WHERE `{$column}` = ?";
        $result = $this->db->fetch($sql, [$value]);
        return $result ? $this->hideFields($result) : null;
    }

    /**
     * Find a record by multiple conditions
     */
    public function findWhere(array $conditions): ?array
    {
        $whereClauses = [];
        $params = [];

        foreach ($conditions as $column => $value) {
            $whereClauses[] = "`{$column}` = ?";
            $params[] = $value;
        }

        $sql = "SELECT * FROM `{$this->table}` WHERE " . implode(' AND ', $whereClauses);
        $result = $this->db->fetch($sql, $params);
        return $result ? $this->hideFields($result) : null;
    }

    /**
     * Get all records
     */
    public function all(array $order = []): array
    {
        $sql = "SELECT * FROM `{$this->table}`";

        if (!empty($order)) {
            $orderClauses = [];
            foreach ($order as $column => $direction) {
                $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
                $orderClauses[] = "`{$column}` {$direction}";
            }
            $sql .= ' ORDER BY ' . implode(', ', $orderClauses);
        }

        $results = $this->db->fetchAll($sql);
        return array_map(fn($row) => $this->hideFields($row), $results);
    }

    /**
     * Get records with conditions
     */
    public function where(array $conditions, array $order = [], ?int $limit = null, int $offset = 0): array
    {
        $whereClauses = [];
        $params = [];

        foreach ($conditions as $column => $value) {
            if (is_array($value)) {
                // Handle operators like ['>', 10]
                $operator = $value[0];
                $val = $value[1];
                $whereClauses[] = "`{$column}` {$operator} ?";
                $params[] = $val;
            } else {
                $whereClauses[] = "`{$column}` = ?";
                $params[] = $value;
            }
        }

        $sql = "SELECT * FROM `{$this->table}` WHERE " . implode(' AND ', $whereClauses);

        if (!empty($order)) {
            $orderClauses = [];
            foreach ($order as $column => $direction) {
                $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
                $orderClauses[] = "`{$column}` {$direction}";
            }
            $sql .= ' ORDER BY ' . implode(', ', $orderClauses);
        }

        if ($limit !== null) {
            $sql .= " LIMIT {$limit} OFFSET {$offset}";
        }

        $results = $this->db->fetchAll($sql, $params);
        return array_map(fn($row) => $this->hideFields($row), $results);
    }

    /**
     * Create a new record
     */
    public function create(array $data): int
    {
        // Filter to fillable fields
        if (!empty($this->fillable)) {
            $data = array_intersect_key($data, array_flip($this->fillable));
        }

        // Add timestamps
        if ($this->timestamps) {
            $now = date('Y-m-d H:i:s');
            $data['created_at'] = $now;
            $data['updated_at'] = $now;
        }

        return $this->db->insert($this->table, $data);
    }

    /**
     * Update a record
     */
    public function update(int $id, array $data): bool
    {
        // Filter to fillable fields
        if (!empty($this->fillable)) {
            $data = array_intersect_key($data, array_flip($this->fillable));
        }

        // Update timestamp
        if ($this->timestamps) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }

        $affected = $this->db->update($this->table, $data, [$this->primaryKey => $id]);
        return $affected > 0;
    }

    /**
     * Delete a record
     */
    public function delete(int $id): bool
    {
        $affected = $this->db->delete($this->table, [$this->primaryKey => $id]);
        return $affected > 0;
    }

    /**
     * Count records
     */
    public function count(array $conditions = []): int
    {
        return $this->db->count($this->table, $conditions);
    }

    /**
     * Paginate results
     */
    public function paginate(int $page = 1, int $perPage = 25, array $conditions = [], array $order = []): array
    {
        $total = $this->count($conditions);
        $totalPages = (int)ceil($total / $perPage);
        $page = max(1, min($page, $totalPages ?: 1));
        $offset = ($page - 1) * $perPage;

        $data = empty($conditions)
            ? $this->allPaginated($order, $perPage, $offset)
            : $this->where($conditions, $order, $perPage, $offset);

        return [
            'data' => $data,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_more' => $page < $totalPages
            ]
        ];
    }

    /**
     * Get paginated results without conditions
     */
    private function allPaginated(array $order, int $limit, int $offset): array
    {
        $sql = "SELECT * FROM `{$this->table}`";

        if (!empty($order)) {
            $orderClauses = [];
            foreach ($order as $column => $direction) {
                $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
                $orderClauses[] = "`{$column}` {$direction}";
            }
            $sql .= ' ORDER BY ' . implode(', ', $orderClauses);
        }

        $sql .= " LIMIT {$limit} OFFSET {$offset}";

        $results = $this->db->fetchAll($sql);
        return array_map(fn($row) => $this->hideFields($row), $results);
    }

    /**
     * Check if a record exists
     */
    public function exists(array $conditions): bool
    {
        return $this->count($conditions) > 0;
    }

    /**
     * Get first record matching conditions
     */
    public function first(array $conditions = [], array $order = []): ?array
    {
        $results = empty($conditions)
            ? $this->all($order)
            : $this->where($conditions, $order, 1);

        return $results[0] ?? null;
    }

    /**
     * Get last record matching conditions
     */
    public function last(array $conditions = []): ?array
    {
        return $this->first($conditions, [$this->primaryKey => 'DESC']);
    }

    /**
     * Execute raw query
     */
    public function raw(string $sql, array $params = []): array
    {
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Execute raw query and get single result
     */
    public function rawFirst(string $sql, array $params = []): ?array
    {
        return $this->db->fetch($sql, $params);
    }

    /**
     * Hide sensitive fields
     */
    protected function hideFields(array $data): array
    {
        foreach ($this->hidden as $field) {
            unset($data[$field]);
        }
        return $data;
    }

    /**
     * Get the table name
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Get the primary key
     */
    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
    }
}
