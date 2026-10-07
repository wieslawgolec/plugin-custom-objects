<?php
declare(strict_types=1);
namespace MauticPlugin\CustomObjectsBundle\Service;
use Doctrine\DBAL\Connection;
use InvalidArgumentException;
class CustomItemRepository
{
    private Connection $connection;
    private DynamicSchemaManager $schemaManager;
    private CustomObjectRegistry $registry;
    public function __construct(Connection $connection, DynamicSchemaManager $schemaManager, CustomObjectRegistry $registry)
    {
        $this->connection = $connection;
        $this->schemaManager = $schemaManager;
        $this->registry = $registry;
    }
    public function create(string $objectName, int $contactId, array $data): int
    {
        $object = $this->requireObject($objectName);
        $tableName = $this->schemaManager->buildTableName($object['name']);
        $allowed = $this->allowedFieldNames($object['fields']);
        $row = ['contact_id' => $contactId, 'date_added' => $this->now(), 'date_modified' => $this->now()];
        foreach ($data as $key => $value) {
            $col = $this->schemaManager->sanitizeIdentifier((string) $key);
            if (in_array($col, $allowed, true)) { $row[$col] = $value; }
        }
        $this->connection->insert($tableName, $row);
        return (int) $this->connection->lastInsertId();
    }
    public function update(string $objectName, int $itemId, array $data): bool
    {
        $object = $this->requireObject($objectName);
        $tableName = $this->schemaManager->buildTableName($object['name']);
        $allowed = $this->allowedFieldNames($object['fields']);
        $row = ['date_modified' => $this->now()];
        foreach ($data as $key => $value) {
            $col = $this->schemaManager->sanitizeIdentifier((string) $key);
            if ($col === 'id' || $col === 'contact_id') continue;
            if (in_array($col, $allowed, true)) { $row[$col] = $value; }
        }
        return $this->connection->update($tableName, $row, ['id' => $itemId]) > 0;
    }
    public function delete(string $objectName, int $itemId): bool
    {
        $object = $this->requireObject($objectName);
        return $this->connection->delete($this->schemaManager->buildTableName($object['name']), ['id' => $itemId]) > 0;
    }
    public function find(string $objectName, int $itemId): ?array
    {
        $object = $this->requireObject($objectName);
        $row = $this->connection->fetchAssociative('SELECT * FROM ' . $this->quote($this->schemaManager->buildTableName($object['name'])) . ' WHERE id = :id', ['id' => $itemId]);
        return $row ?: null;
    }
    public function list(string $objectName, ?int $contactId = null, int $limit = 100, int $offset = 0): array
    {
        $object = $this->requireObject($objectName);
        $tableName = $this->schemaManager->buildTableName($object['name']);
        $sql = 'SELECT * FROM ' . $this->quote($tableName);
        $params = [];
        if ($contactId !== null) { $sql .= ' WHERE contact_id = :contactId'; $params['contactId'] = $contactId; }
        $sql .= ' ORDER BY id DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
        return $this->connection->fetchAllAssociative($sql, $params);
    }
    public function count(string $objectName, ?int $contactId = null): int
    {
        $object = $this->requireObject($objectName);
        $tableName = $this->schemaManager->buildTableName($object['name']);
        $sql = 'SELECT COUNT(*) FROM ' . $this->quote($tableName);
        $params = [];
        if ($contactId !== null) { $sql .= ' WHERE contact_id = :contactId'; $params['contactId'] = $contactId; }
        return (int) $this->connection->fetchOne($sql, $params);
    }
    public function findContactIdsByProperty(string $objectName, string $fieldName, string $operator, mixed $value, int $limit = 10000): array
    {
        $object = $this->requireObject($objectName);
        $tableName = $this->schemaManager->buildTableName($object['name']);
        $column = $this->schemaManager->sanitizeIdentifier($fieldName);
        $allowed = $this->allowedFieldNames($object['fields']);
        if (!in_array($column, $allowed, true)) {
            throw new InvalidArgumentException(sprintf('Unknown field "%s".', $fieldName));
        }
        [$sqlOp, $params] = $this->buildOperator($operator, $value);
        $sql = sprintf('SELECT DISTINCT contact_id FROM %s WHERE %s %s LIMIT %d', $this->quote($tableName), $this->quote($column), $sqlOp, $limit);
        return array_map('intval', $this->connection->fetchFirstColumn($sql, $params));
    }
    private function requireObject(string $objectName): array
    {
        $object = $this->registry->findByName($objectName);
        if ($object === null) throw new InvalidArgumentException(sprintf('Custom object "%s" not found.', $objectName));
        return $object;
    }
    private function allowedFieldNames(array $fields): array
    {
        $names = [];
        foreach ($fields as $f) {
            if (!empty($f['name'])) $names[] = $this->schemaManager->sanitizeIdentifier((string) $f['name']);
        }
        return $names;
    }
    private function buildOperator(string $operator, mixed $value): array
    {
        return match (strtolower($operator)) {
            'eq', '=' => ['= :val', ['val' => $value]],
            'neq', '!=' => ['<> :val', ['val' => $value]],
            'gt', '>' => ['> :val', ['val' => $value]],
            'gte', '>=' => ['>= :val', ['val' => $value]],
            'lt', '<' => ['< :val', ['val' => $value]],
            'lte', '<=' => ['<= :val', ['val' => $value]],
            'like' => ['LIKE :val', ['val' => $value]],
            'notlike' => ['NOT LIKE :val', ['val' => $value]],
            'empty' => ['IS NULL', []],
            'notempty' => ['IS NOT NULL', []],
            default => throw new InvalidArgumentException(sprintf('Unsupported operator "%s".', $operator)),
        };
    }
    private function now(): string { return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'); }
    private function quote(string $name): string { return '`' . str_replace('`', '``', $name) . '`'; }
}
