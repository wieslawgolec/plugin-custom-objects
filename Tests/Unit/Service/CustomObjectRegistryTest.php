<?php
declare(strict_types=1);
namespace MauticPlugin\CustomObjectsBundle\Tests\Unit\Service;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use MauticPlugin\CustomObjectsBundle\Service\CustomObjectRegistry;
use MauticPlugin\CustomObjectsBundle\Service\DynamicSchemaManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
final class CustomObjectRegistryTest extends TestCase
{
    private Connection&MockObject $connection;
    private AbstractSchemaManager&MockObject $schemaManager;
    private DynamicSchemaManager&MockObject $dynamicSchema;
    private CustomObjectRegistry $registry;
    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->schemaManager = $this->createMock(AbstractSchemaManager::class);
        $this->dynamicSchema = $this->createMock(DynamicSchemaManager::class);
        $this->connection->method('createSchemaManager')->willReturn($this->schemaManager);
        $this->dynamicSchema->method('sanitizeIdentifier')->willReturnCallback(static fn (string $n) => strtolower(preg_replace('/[^a-z0-9_]+/i', '_', $n) ?? ''));
        $this->registry = new CustomObjectRegistry($this->connection, $this->dynamicSchema, '');
    }
    public function testEnsureRegistryTableCreatesWhenMissing(): void
    {
        $this->schemaManager->method('tablesExist')->willReturn(false);
        $this->schemaManager->expects($this->once())->method('createTable');
        $this->registry->ensureRegistryTable();
    }
    public function testEnsureRegistryTableSkipsWhenExists(): void
    {
        $this->schemaManager->method('tablesExist')->willReturn(true);
        $this->schemaManager->expects($this->never())->method('createTable');
        $this->registry->ensureRegistryTable();
    }
    public function testRegisterCreatesTableAndInserts(): void
    {
        $this->schemaManager->method('tablesExist')->willReturn(true);
        $this->connection->method('fetchAssociative')->willReturn(false);
        $this->dynamicSchema->expects($this->once())->method('createCustomObjectTable')->with('vehicles', $this->isType('array'))->willReturn('custom_obj_vehicles');
        $this->connection->expects($this->once())->method('insert');
        $this->connection->method('lastInsertId')->willReturn('7');
        $result = $this->registry->register('Vehicles', 'Vehicle', 'Vehicles', [['name' => 'car_model', 'type' => 'string']]);
        $this->assertSame(7, $result['id']);
        $this->assertSame('vehicles', $result['name']);
    }
    public function testRegisterRejectsDuplicate(): void
    {
        $this->schemaManager->method('tablesExist')->willReturn(true);
        $this->connection->method('fetchAssociative')->willReturn(['id' => 1, 'name' => 'vehicles', 'singular' => 'Vehicle', 'plural' => 'Vehicles', 'fields_json' => '[]']);
        $this->expectException(\InvalidArgumentException::class);
        $this->registry->register('vehicles', 'Vehicle', 'Vehicles', [['name' => 'x', 'type' => 'string']]);
    }
    public function testListAllHydratesFields(): void
    {
        $this->schemaManager->method('tablesExist')->willReturn(true);
        $this->connection->method('fetchAllAssociative')->willReturn([['id' => 1, 'name' => 'orders', 'singular' => 'Order', 'plural' => 'Orders', 'fields_json' => '[{"name":"total","type":"float"}]', 'date_added' => null, 'date_modified' => null]]);
        $list = $this->registry->listAll();
        $this->assertCount(1, $list);
        $this->assertSame('total', $list[0]['fields'][0]['name']);
    }
    public function testUnregisterDropsAndDeletes(): void
    {
        $this->schemaManager->method('tablesExist')->willReturn(true);
        $this->connection->method('fetchAssociative')->willReturn(['id' => 3, 'name' => 'vehicles', 'singular' => 'V', 'plural' => 'Vs', 'fields_json' => '[]']);
        $this->dynamicSchema->expects($this->once())->method('dropCustomObjectTable')->with('vehicles');
        $this->connection->expects($this->once())->method('delete')->with('custom_object_registry', ['id' => 3]);
        $this->assertTrue($this->registry->unregister('vehicles'));
    }
    public function testUnregisterReturnsFalseWhenMissing(): void
    {
        $this->schemaManager->method('tablesExist')->willReturn(true);
        $this->connection->method('fetchAssociative')->willReturn(false);
        $this->assertFalse($this->registry->unregister('ghost'));
    }
    public function testGetRegistryTableName(): void
    {
        $this->assertSame('custom_object_registry', $this->registry->getRegistryTableName());
    }
}
