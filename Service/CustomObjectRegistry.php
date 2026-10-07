<?php

declare(strict_types=1);

namespace MauticPlugin\CustomObjectsBundle\Service;

use Doctrine\DBAL\Connection;
use InvalidArgumentException;

class CustomObjectRegistry
{
    public const REGISTRY_TABLE = 'custom_object_registry';

    private Connection $connection;
    private DynamicSchemaManager $schemaManager;
    private string $tablePrefix;

    public function __construct(Connection $connection, DynamicSchemaManager $schemaManager, ?string $tablePrefix = null)
    {
        $this->connection    = $connection;
        $this->schemaManager = $schemaManager;
        $this->tablePrefix   = $tablePrefix ?? (defined('MAUTIC_TABLE_PREFIX') ? MAUTIC_TABLE_PREFIX : '');
    }

    public function ensureRegistryTable(): void
    {
        $tableName     = $this->getRegistryTableName();
        $schemaManager = $this->connection->createSchemaManager();
        if ($schemaManager->tablesExist([$tableName])) {
            return;
        }
        $table = new \Doctrine\DBAL\Schema\Table($tableName);
        $table->addColumn('id', 'integer', ['autoincrement' => true, 'unsigned' => true]);
        $table->setPrimaryKey(['id']);
        $table->addColumn('name', 'string', ['length' => 64, 'notnull' => true]);
        $table->addUniqueIndex(['name'], 'uniq_object_name');
        $table->addColumn('singular', 'string', ['length' => 128, 'notnull' => true]);
        $table->addColumn('plural', 'string', ['length' => 128, 'notnull' => true]);
        $table->addColumn('fields_json', 'text', ['notnull' => true]);
        $table->addColumn('date_added', 'datetime', ['notnull' => false]);
        $table->addColumn('date_modified', 'datetime', ['notnull' => false]);
        $schemaManager->createTable($table);
    }

    public function register(string $name, string $singular, string $plural, array $fields): array
    {
        $this->ensureRegistryTable();
        $sanitized = $this->schemaManager->sanitizeIdentifier($name);
        if ($sanitized === '' || $sanitized === 'obj_') {
            throw new InvalidArgumentException('Invalid object name.');
        }
        if ($this->findByName($sanitized) !== null) {
            throw new InvalidArgumentException(sprintf('Object "%s" already exists.', $sanitized));
        }
        $tableName = $this->schemaManager->createCustomObjectTable($sanitized, $fields);
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $this->connection->insert($this->getRegistryTableName(), [
            'name' => $sanitized, 'singular' => $singular, 'plural' => $plural,
            'fields_json' => json_encode(array_values($fields), JSON_THROW_ON_ERROR),
            'date_added' => $now, 'date_modified' => $now,
        ]);
        return ['id' => (int) $this->connection->lastInsertId(), 'name' => $sanitized, 'tableName' => $tableName];
    }

    public function listAll(): array
    {
        $this->ensureRegistryTable();
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM ' . $this->quote($this->getRegistryTableName()) . ' ORDER BY plural ASC'
        );
        return array_map([$this, 'hydrate'], $rows);
    }

    public function findByName(string $name): ?array
    {
        $this->ensureRegistryTable();
        $sanitized = $this->schemaManager->sanitizeIdentifier($name);
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM ' . $this->quote($this->getRegistryTableName()) . ' WHERE name = :name',
            ['name' => $sanitized]
        );
        return $row ? $this->hydrate($row) : null;
    }

    public function findById(int $id): ?array
    {
        $this->ensureRegistryTable();
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM ' . $this->quote($this->getRegistryTableName()) . ' WHERE id = :id',
            ['id' => $id]
        );
        return $row ? $this->hydrate($row) : null;
    }

    public function updateFields(string $name, array $fields): void
    {
        $object = $this->findByName($name);
        if ($object === null) {
            throw new InvalidArgumentException(sprintf('Object "%s" not found.', $name));
        }
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $this->connection->update($this->getRegistryTableName(), [
            'fields_json' => json_encode(array_values($fields), JSON_THROW_ON_ERROR),
            'date_modified' => $now,
        ], ['id' => $object['id']]);
    }

    public function unregister(string $name): bool
    {
        $object = $this->findByName($name);
        if ($object === null) {
            return false;
        }
        $this->schemaManager->dropCustomObjectTable($object['name']);
        $this->connection->delete($this->getRegistryTableName(), ['id' => $object['id']]);
        return true;
    }

    public function getRegistryTableName(): string
    {
        return $this->tablePrefix . self::REGISTRY_TABLE;
    }

    private function hydrate(array $row): array
    {
        $fields = json_decode((string) ($row['fields_json'] ?? '[]'), true);
        if (!is_array($fields)) {
            $fields = [];
        }
        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'singular' => (string) $row['singular'],
            'plural' => (string) $row['plural'],
            'fields' => $fields,
            'date_added' => $row['date_added'] ?? null,
            'date_modified' => $row['date_modified'] ?? null,
        ];
    }

    private function quote(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }
}
