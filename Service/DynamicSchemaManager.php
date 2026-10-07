<?php

declare(strict_types=1);

namespace MauticPlugin\CustomObjectsBundle\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Table;
use InvalidArgumentException;

/**
 * Dynamically creates / drops custom object tables linked to Mautic Contacts (leads).
 *
 * Bypasses static Doctrine entity mappings and uses SchemaManager + DBAL directly.
 * Table names follow: {prefix}custom_obj_{sanitized_object_name}
 */
class DynamicSchemaManager
{
    public const TABLE_PREFIX_CUSTOM = 'custom_obj_';

    private Connection $connection;

    /** @var string Table prefix (MAUTIC_TABLE_PREFIX or empty for tests) */
    private string $tablePrefix;

    public function __construct(Connection $connection, ?string $tablePrefix = null)
    {
        $this->connection  = $connection;
        $this->tablePrefix = $tablePrefix ?? (defined('MAUTIC_TABLE_PREFIX') ? MAUTIC_TABLE_PREFIX : '');
    }

    /**
     * Create a new custom object table with FK to leads and optional custom columns.
     *
     * @param string               $objectName   Human-readable name (e.g. "Vehicles")
     * @param array<int, array{name: string, type: string}> $customFields
     *        Each field: ['name' => 'car_model', 'type' => 'string'|'integer'|'text'|'boolean'|'datetime'|'float']
     *
     * @throws InvalidArgumentException
     */
    public function createCustomObjectTable(string $objectName, array $customFields = []): string
    {
        $tableName = $this->buildTableName($objectName);

        $schemaManager = $this->connection->createSchemaManager();

        if ($schemaManager->tablesExist([$tableName])) {
            return $tableName; // idempotent
        }

        $table = new Table($tableName);

        // Primary key
        $table->addColumn('id', 'integer', [
            'autoincrement' => true,
            'unsigned'      => true,
        ]);
        $table->setPrimaryKey(['id']);

        // FK to contacts / leads
        $table->addColumn('contact_id', 'integer', [
            'unsigned' => true,
            'notnull'  => true,
        ]);
        $table->addIndex(['contact_id'], 'idx_contact_id');

        $leadsTable = $this->tablePrefix . 'leads';
        // Only add FK when the leads table actually exists (skip in pure unit tests)
        if ($schemaManager->tablesExist([$leadsTable])) {
            $table->addForeignKeyConstraint(
                $leadsTable,
                ['contact_id'],
                ['id'],
                ['onDelete' => 'CASCADE'],
                'fk_contact_id'
            );
        }

        // Timestamps
        $table->addColumn('date_added', 'datetime', ['notnull' => false]);
        $table->addColumn('date_modified', 'datetime', ['notnull' => false]);

        // Dynamic columns from UI
        foreach ($customFields as $field) {
            $this->assertValidField($field);
            $colName = $this->sanitizeIdentifier($field['name']);
            $type    = $field['type'] ?? 'string';
            $options = $this->columnOptionsForType($type);
            $table->addColumn($colName, $type, $options);
        }

        $schemaManager->createTable($table);

        return $tableName;
    }

    /**
     * Drop a custom object table if it exists.
     */
    public function dropCustomObjectTable(string $objectName): bool
    {
        $tableName     = $this->buildTableName($objectName);
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist([$tableName])) {
            return false;
        }

        $schemaManager->dropTable($tableName);

        return true;
    }

    /**
     * List all custom object tables currently present in the database.
     *
     * @return list<string>
     */
    public function listCustomObjectTables(): array
    {
        $schemaManager = $this->connection->createSchemaManager();
        $allTables     = $schemaManager->listTableNames();
        $prefix        = $this->tablePrefix . self::TABLE_PREFIX_CUSTOM;

        $result = [];
        foreach ($allTables as $name) {
            if (str_starts_with($name, $prefix)) {
                $result[] = $name;
            }
        }

        sort($result);

        return $result;
    }

    /**
     * Check whether a custom object table exists.
     */
    public function tableExists(string $objectName): bool
    {
        $tableName = $this->buildTableName($objectName);

        return $this->connection->createSchemaManager()->tablesExist([$tableName]);
    }

    /**
     * Build the physical table name from a human object name.
     *
     * @throws InvalidArgumentException when the name has no alphanumeric characters
     */
    public function buildTableName(string $objectName): string
    {
        $sanitized = $this->sanitizeIdentifier($objectName);
        if ($sanitized === '') {
            throw new InvalidArgumentException('Object name must contain at least one alphanumeric character.');
        }

        return $this->tablePrefix . self::TABLE_PREFIX_CUSTOM . $sanitized;
    }

    /**
     * Sanitize a string into a safe SQL identifier (lowercase, underscores).
     *
     * Returns an empty string when the input has no usable alphanumeric content
     * (callers such as buildTableName must reject that).
     * Names that start with a digit are prefixed with "obj_" so they remain valid identifiers.
     */
    public function sanitizeIdentifier(string $name): string
    {
        $name = strtolower(trim($name));
        $name = preg_replace('/[^a-z0-9_]+/', '_', $name) ?? '';
        $name = trim($name, '_');

        // Purely invalid input (e.g. "!!!") — do not invent a name
        if ($name === '') {
            return '';
        }

        // SQL identifiers should not start with a digit
        if (is_numeric($name[0])) {
            $name = 'obj_' . $name;
        }

        return $name;
    }

    /**
     * @param array{name?: string, type?: string} $field
     */
    private function assertValidField(array $field): void
    {
        if (empty($field['name']) || !is_string($field['name'])) {
            throw new InvalidArgumentException('Each custom field must have a non-empty "name" string.');
        }

        $allowed = ['string', 'integer', 'text', 'boolean', 'datetime', 'float', 'bigint'];
        $type    = $field['type'] ?? 'string';
        if (!in_array($type, $allowed, true)) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported field type "%s". Allowed: %s',
                $type,
                implode(', ', $allowed)
            ));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function columnOptionsForType(string $type): array
    {
        return match ($type) {
            'string'   => ['length' => 255, 'notnull' => false],
            'text'     => ['notnull' => false],
            'integer', 'bigint', 'float' => ['notnull' => false],
            'boolean'  => ['notnull' => false, 'default' => 0],
            'datetime' => ['notnull' => false],
            default    => ['notnull' => false],
        };
    }

    public function getTablePrefix(): string
    {
        return $this->tablePrefix;
    }

    public function getConnection(): Connection
    {
        return $this->connection;
    }
}
