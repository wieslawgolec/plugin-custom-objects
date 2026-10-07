<?php

declare(strict_types=1);

namespace MauticPlugin\CustomObjectsBundle\Tests\Unit\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use MauticPlugin\CustomObjectsBundle\Service\AuditLogger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AuditLoggerTest extends TestCase
{
    private Connection&MockObject $connection;
    private AbstractSchemaManager&MockObject $schemaManager;
    private AuditLogger $logger;

    protected function setUp(): void
    {
        $this->connection    = $this->createMock(Connection::class);
        $this->schemaManager = $this->createMock(AbstractSchemaManager::class);
        $this->connection->method('createSchemaManager')->willReturn($this->schemaManager);
        $this->logger = new AuditLogger($this->connection, '');
    }

    public function testEnsureTableCreatesOnce(): void
    {
        $this->schemaManager->method('tablesExist')->willReturn(false);
        $this->schemaManager->expects($this->once())->method('createTable');
        $this->logger->ensureTable();
        $this->logger->ensureTable();
    }

    public function testLogInsertsRow(): void
    {
        $this->schemaManager->method('tablesExist')->willReturn(true);
        $this->connection->expects($this->once())->method('insert');
        $this->connection->method('lastInsertId')->willReturn('100');
        $id = $this->logger->log(AuditLogger::ACTION_CREATE_ITEM, 'vehicles', 9, 1, ['k' => 'v']);
        $this->assertSame(100, $id);
    }

    public function testRecent(): void
    {
        $this->schemaManager->method('tablesExist')->willReturn(true);
        $this->connection->method('fetchAllAssociative')->willReturn([
            ['id' => 2, 'action' => 'create_item'],
        ]);
        $this->assertCount(1, $this->logger->recent(10, 'vehicles'));
    }

    public function testGetTableName(): void
    {
        $this->assertSame('custom_object_audit', $this->logger->getTableName());
    }
}
