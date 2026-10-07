<?php

declare(strict_types=1);

namespace MauticPlugin\CustomObjectsBundle\Tests\Unit\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Table;
use MauticPlugin\CustomObjectsBundle\Service\DynamicSchemaManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class DynamicSchemaManagerTest extends TestCase
{
    private Connection&MockObject $connection;
    private AbstractSchemaManager&MockObject $schemaManager;
    private DynamicSchemaManager $manager;

    protected function setUp(): void
    {
        $this->connection    = $this->createMock(Connection::class);
        $this->schemaManager = $this->createMock(AbstractSchemaManager::class);

        $this->connection
            ->method('createSchemaManager')
            ->willReturn($this->schemaManager);

        // Empty prefix for tests so table names are predictable
        $this->manager = new DynamicSchemaManager($this->connection, '');
    }

    public function testBuildTableNameSanitizesAndPrefixes(): void
    {
        $this->assertSame('custom_obj_vehicles', $this->manager->buildTableName('Vehicles'));
        $this->assertSame('custom_obj_my_orders', $this->manager->buildTableName('My Orders!'));
        $this->assertSame('custom_obj_product_v2', $this->manager->buildTableName('Product_v2'));
    }

    public function testBuildTableNameRejectsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->manager->buildTableName('!!!');
    }

    public function testSanitizeIdentifier(): void
    {
        $this->assertSame('car_model', $this->manager->sanitizeIdentifier('Car Model'));
        $this->assertSame('year_2024', $this->manager->sanitizeIdentifier('Year 2024'));
        $this->assertSame('obj_123abc', $this->manager->sanitizeIdentifier('123abc'));
    }

    public function testCreateCustomObjectTableIsIdempotentWhenTableExists(): void
    {
        $this->schemaManager
            ->method('tablesExist')
            ->willReturn(true);

        $this->schemaManager
            ->expects($this->never())
            ->method('createTable');

        $name = $this->manager->createCustomObjectTable('Vehicles', [
            ['name' => 'car_model', 'type' => 'string'],
        ]);

        $this->assertSame('custom_obj_vehicles', $name);
    }

    public function testCreateCustomObjectTableBuildsExpectedSchema(): void
    {
        // First call: custom table does not exist; second (leads check) also false
        $this->schemaManager
            ->method('tablesExist')
            ->willReturn(false);

        $captured = null;
        $this->schemaManager
            ->expects($this->once())
            ->method('createTable')
            ->with($this->callback(function (Table $table) use (&$captured) {
                $captured = $table;

                return true;
            }));

        $name = $this->manager->createCustomObjectTable('Vehicles', [
            ['name' => 'car_model', 'type' => 'string'],
            ['name' => 'year', 'type' => 'integer'],
            ['name' => 'notes', 'type' => 'text'],
        ]);

        $this->assertSame('custom_obj_vehicles', $name);
        $this->assertInstanceOf(Table::class, $captured);
        $this->assertSame('custom_obj_vehicles', $captured->getName());
        $this->assertTrue($captured->hasColumn('id'));
        $this->assertTrue($captured->hasColumn('contact_id'));
        $this->assertTrue($captured->hasColumn('date_added'));
        $this->assertTrue($captured->hasColumn('date_modified'));
        $this->assertTrue($captured->hasColumn('car_model'));
        $this->assertTrue($captured->hasColumn('year'));
        $this->assertTrue($captured->hasColumn('notes'));

        // hasPrimaryKey() was removed in Doctrine DBAL 4; use getPrimaryKey() (DBAL 3 + 4)
        $primaryKey = $captured->getPrimaryKey();
        $this->assertNotNull($primaryKey, 'Table must have a primary key');
        $this->assertSame(['id'], $primaryKey->getColumns());

        $this->assertTrue($captured->hasIndex('idx_contact_id'));
    }

    public function testCreateRejectsInvalidFieldType(): void
    {
        $this->schemaManager->method('tablesExist')->willReturn(false);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported field type');

        $this->manager->createCustomObjectTable('Bad', [
            ['name' => 'foo', 'type' => 'blob'],
        ]);
    }

    public function testCreateRejectsMissingFieldName(): void
    {
        $this->schemaManager->method('tablesExist')->willReturn(false);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('non-empty "name"');

        $this->manager->createCustomObjectTable('Bad', [
            ['type' => 'string'],
        ]);
    }

    public function testDropCustomObjectTableReturnsFalseWhenMissing(): void
    {
        $this->schemaManager->method('tablesExist')->willReturn(false);
        $this->schemaManager->expects($this->never())->method('dropTable');

        $this->assertFalse($this->manager->dropCustomObjectTable('Ghost'));
    }

    public function testDropCustomObjectTableDropsWhenPresent(): void
    {
        $this->schemaManager->method('tablesExist')->willReturn(true);
        $this->schemaManager
            ->expects($this->once())
            ->method('dropTable')
            ->with('custom_obj_vehicles');

        $this->assertTrue($this->manager->dropCustomObjectTable('Vehicles'));
    }

    public function testListCustomObjectTablesFiltersByPrefix(): void
    {
        $this->schemaManager
            ->method('listTableNames')
            ->willReturn([
                'leads',
                'custom_obj_vehicles',
                'custom_obj_orders',
                'something_else',
                'custom_obj_products',
            ]);

        $list = $this->manager->listCustomObjectTables();

        $this->assertSame(
            ['custom_obj_orders', 'custom_obj_products', 'custom_obj_vehicles'],
            $list
        );
    }

    public function testTableExists(): void
    {
        $this->schemaManager
            ->method('tablesExist')
            ->with(['custom_obj_vehicles'])
            ->willReturn(true);

        $this->assertTrue($this->manager->tableExists('Vehicles'));
    }

    public function testWithCustomTablePrefix(): void
    {
        $manager = new DynamicSchemaManager($this->connection, 'mtc_');
        $this->assertSame('mtc_custom_obj_orders', $manager->buildTableName('Orders'));
        $this->assertSame('mtc_', $manager->getTablePrefix());
    }
}
