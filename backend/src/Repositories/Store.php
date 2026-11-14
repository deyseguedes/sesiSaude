<?php
declare(strict_types=1);
namespace App\Repositories;
use PDO;
final class Store
{
    public function __construct(public readonly PDO $db) {}
    public function all(string $sql, array $params = []): array
    {
        $statement = $this->db->prepare($sql); $statement->execute($params);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
    public function one(string $sql, array $params = []): ?array { return $this->all($sql, $params)[0] ?? null; }
    public function oneLocked(string $sql,array $params=[]): ?array
    {
        $locking=$this->db->inTransaction() && $this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
        return $this->one($sql.($locking?' FOR UPDATE':''),$params);
    }
    public function execute(string $sql, array $params = []): void { $s = $this->db->prepare($sql); $s->execute($params); }
    public function insert(string $table, array $data): int
    {
        // Table and column names are internal constants, never request parameters.
        $columns = array_keys($data);
        $this->execute('INSERT INTO ' . $table . ' (' . implode(',', $columns) . ') VALUES (' . implode(',', array_fill(0, count($columns), '?')) . ')', array_values($data));
        return (int) $this->db->lastInsertId();
    }
    public function transaction(callable $operation): mixed
    {
        $this->db->beginTransaction();
        try { $result = $operation(); $this->db->commit(); return $result; }
        catch (\Throwable $error) { $this->db->rollBack(); throw $error; }
    }
}
