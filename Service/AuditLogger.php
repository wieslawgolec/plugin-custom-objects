<?php

declare(strict_types=1);

namespace MauticPlugin\CustomObjectsBundle\Service;

use Doctrine\DBAL\Connection;

class AuditLogger
{
    public const TABLE = 'custom_object_audit';
    public const ACTION_CREATE_OBJECT = 'create_object';
    public const ACTION_DELETE_OBJECT = 'delete_object';
    public const ACTION_CREATE_ITEM   = 'create_item';
    public const ACTION_UPDATE_ITEM   = 'update_item';
    public const ACTION_DELETE_ITEM   = 'delete_item';
    public const ACTION_IMPORT        = 'import';
    public const ACTION_EXPORT        = 'export';

    private Connection $connection;
    private string $tablePrefix;
    private bool $tableReady = false;

    public function __construct(Connection $connection, ?string $tablePrefix = null)
    {
        $this->connection  = $connection;
        $this->tablePrefix = $tablePrefix ?? (defined('MAUTIC_TABLE_PREFIX') ? MAUTIC_TABLE_PREFIX : '');
    }

    public function ensureTable(): void
    {
        if ($this->tableReady) {
            return;
        }
        $tableName     = $this->getTableName();
        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist([$tableName])) {
            $table = new \Doctrine\DBAL\Schema\Table($tableName);
            $table->addColumn('id', 'integer', ['autoincrement' => true, 'unsigned' => true]);
            $table->setPrimaryKey(['id']);
            $table->addColumn('user_id', 'integer', ['unsigned' => true, 'notnull' => false]);
            $table->addColumn('action', 'string', ['length' => 64, 'notnull' => true]);
            $table->addColumn('object_name', 'string', ['length' => 64, 'notnull' => false]);
            $table->addColumn('item_id', 'integer', ['unsigned' => true, 'notnull' => false]);
            $table->addColumn('details', 'text', ['notnull' => false]);
            $table->addColumn('ip_address', 'string', ['length' => 45, 'notnull' => false]);
            $table->addColumn('date_added', 'datetime', ['notnull' => true]);
            $table->addIndex(['object_name'], 'idx_audit_object');
            $table->addIndex(['action'], 'idx_audit_action');
            $schemaManager->createTable($table);
        }
        $this->tableReady = true;
    }

    public function log(string $action, ?string $objectName = null, ?int $itemId = null, ?int $userId = null, array $details = [], ?string $ipAddress = null): int
    {
        $this->ensureTable();
        $this->connection->insert($this->getTableName(), [
            'user_id'     => $userId,
            'action'      => $action,
            'object_name' => $objectName,
            'item_id'     => $itemId,
            'details'     => $details !== [] ? json_encode($details, JSON_THROW_ON_ERROR) : null,
            'ip_address'  => $ipAddress,
            'date_added'  => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
        ]);
        return (int) $this->connection->lastInsertId();
    }

    public function recent(int $limit = 50, ?string $objectName = null): array
    {
        $this->ensureTable();
        $sql    = 'SELECT * FROM ' . $this->quote($this->getTableName());
        $params = [];
        if ($objectName !== null) {
            $sql .= ' WHERE object_name = :obj';
            $params['obj'] = $objectName;
        }
        $sql .= ' ORDER BY id DESC LIMIT ' . (int) $limit;
        return $this->connection->fetchAllAssociative($sql, $params);
    }

    public function getTableName(): string
    {
        return $this->tablePrefix . self::TABLE;
    }

    private function quote(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }
}
