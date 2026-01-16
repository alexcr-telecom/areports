<?php
/**
 * Database Connection Manager
 * Handles PDO connections to both areports and asteriskcdrdb databases
 */

namespace aReports\Core;

class Database
{
    private static ?Database $instance = null;
    private ?\PDO $pdo = null;
    private array $config;
    private string $name;

    public function __construct(array $config, string $name = 'default')
    {
        $this->config = $config;
        $this->name = $name;
        $this->connect();
    }

    private function connect(): void
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $this->config['host'] ?? 'localhost',
            $this->config['port'] ?? 3306,
            $this->config['database'] ?? ''
        );

        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];

        try {
            $this->pdo = new \PDO(
                $dsn,
                $this->config['username'] ?? 'root',
                $this->config['password'] ?? '',
                $options
            );
        } catch (\PDOException $e) {
            throw new \RuntimeException("Database connection failed [{$this->name}]: " . $e->getMessage());
        }
    }

    public function getPdo(): \PDO
    {
        return $this->pdo;
    }

    /**
     * Execute a query and return the statement
     */
    public function query(string $sql, array $params = []): \PDOStatement
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (\PDOException $e) {
            throw new \RuntimeException("Query failed: " . $e->getMessage() . "\nSQL: " . $sql);
        }
    }

    /**
     * Fetch a single row
     */
    public function fetch(string $sql, array $params = []): ?array
    {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Fetch all rows
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Fetch a single column value
     */
    public function fetchColumn(string $sql, array $params = [], int $column = 0): mixed
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchColumn($column);
    }

    /**
     * Insert a record and return the last insert ID
     */
    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ':' . $col, $columns);

        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $table,
            implode('`, `', $columns),
            implode(', ', $placeholders)
        );

        $this->query($sql, $data);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Update records
     */
    public function update(string $table, array $data, array $where): int
    {
        $setClauses = [];
        $params = [];

        foreach ($data as $column => $value) {
            $setClauses[] = "`{$column}` = :set_{$column}";
            $params["set_{$column}"] = $value;
        }

        $whereClauses = [];
        foreach ($where as $column => $value) {
            $whereClauses[] = "`{$column}` = :where_{$column}";
            $params["where_{$column}"] = $value;
        }

        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE %s',
            $table,
            implode(', ', $setClauses),
            implode(' AND ', $whereClauses)
        );

        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Delete records
     */
    public function delete(string $table, array $where): int
    {
        $whereClauses = [];
        $params = [];

        foreach ($where as $column => $value) {
            $whereClauses[] = "`{$column}` = :{$column}";
            $params[$column] = $value;
        }

        $sql = sprintf(
            'DELETE FROM `%s` WHERE %s',
            $table,
            implode(' AND ', $whereClauses)
        );

        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Get row count
     */
    public function count(string $table, array $where = []): int
    {
        $sql = "SELECT COUNT(*) FROM `{$table}`";
        $params = [];

        if (!empty($where)) {
            $whereClauses = [];
            foreach ($where as $column => $value) {
                $whereClauses[] = "`{$column}` = :{$column}";
                $params[$column] = $value;
            }
            $sql .= ' WHERE ' . implode(' AND ', $whereClauses);
        }

        return (int) $this->fetchColumn($sql, $params);
    }

    /**
     * Begin a transaction
     */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commit a transaction
     */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    /**
     * Rollback a transaction
     */
    public function rollback(): bool
    {
        return $this->pdo->rollBack();
    }

    /**
     * Check if in a transaction
     */
    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    /**
     * Get last insert ID
     */
    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * Quote a string for safe SQL inclusion
     */
    public function quote(string $string): string
    {
        return $this->pdo->quote($string);
    }
}
