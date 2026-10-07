<?php
declare(strict_types=1);
namespace MauticPlugin\CustomObjectsBundle\Tests\Unit\Service;
use Doctrine\DBAL\Connection;
use MauticPlugin\CustomObjectsBundle\Service\CustomItemRepository;
use MauticPlugin\CustomObjectsBundle\Service\CustomObjectRegistry;
use MauticPlugin\CustomObjectsBundle\Service\DynamicSchemaManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
final class CustomItemRepositoryTest extends TestCase
{
    private Connection&MockObject $connection;
    private DynamicSchemaManager&MockObject $schemaManager;
    private CustomObjectRegistry&MockObject $registry;
    private CustomItemRepository $repo;
    private array $sampleObject = ['id' => 1, 'name' => 'vehicles', 'singular' => 'Vehicle', 'plural' => 'Vehicles', 'fields' => [['name' => 'car_model', 'type' => 'string'], ['name' => 'year', 'type' => 'integer']], 'date_added' => null, 'date_modified' => null];
    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->schemaManager = $this->createMock(DynamicSchemaManager::class);
        $this->registry = $this->createMock(CustomObjectRegistry::class);
        $this->schemaManager->method('buildTableName')->willReturn('custom_obj_vehicles');
        $this->schemaManager->method('sanitizeIdentifier')->willReturnCallback(static fn (string $n) => strtolower($n));
        $this->repo = new CustomItemRepository($this->connection, $this->schemaManager, $this->registry);
    }
    public function testCreateInsertsRow(): void
    {
        $this->registry->method('findByName')->with('vehicles')->willReturn($this->sampleObject);
        $this->connection->expects($this->once())->method('insert')->with('custom_obj_vehicles', $this->callback(fn (array $row) => $row['contact_id'] === 42 && $row['car_model'] === 'Model 3' && $row['year'] === 2024));
        $this->connection->method('lastInsertId')->willReturn('15');
        $this->assertSame(15, $this->repo->create('vehicles', 42, ['car_model' => 'Model 3', 'year' => 2024, 'unknown' => 'x']));
    }
    public function testCreateThrowsWhenObjectMissing(): void
    {
        $this->registry->method('findByName')->willReturn(null);
        $this->expectException(\InvalidArgumentException::class);
        $this->repo->create('ghost', 1, []);
    }
    public function testUpdate(): void
    {
        $this->registry->method('findByName')->willReturn($this->sampleObject);
        $this->connection->expects($this->once())->method('update')->willReturn(1);
        $this->assertTrue($this->repo->update('vehicles', 15, ['car_model' => 'Model Y']));
    }
    public function testDelete(): void
    {
        $this->registry->method('findByName')->willReturn($this->sampleObject);
        $this->connection->expects($this->once())->method('delete')->with('custom_obj_vehicles', ['id' => 15])->willReturn(1);
        $this->assertTrue($this->repo->delete('vehicles', 15));
    }
    public function testFind(): void
    {
        $this->registry->method('findByName')->willReturn($this->sampleObject);
        $this->connection->method('fetchAssociative')->willReturn(['id' => 15, 'contact_id' => 42, 'car_model' => 'Model 3']);
        $this->assertSame(15, $this->repo->find('vehicles', 15)['id']);
    }
    public function testListAndCount(): void
    {
        $this->registry->method('findByName')->willReturn($this->sampleObject);
        $this->connection->method('fetchAllAssociative')->willReturn([['id' => 1], ['id' => 2]]);
        $this->connection->method('fetchOne')->willReturn(2);
        $this->assertCount(2, $this->repo->list('vehicles', 10));
        $this->assertSame(2, $this->repo->count('vehicles', 10));
    }
    public function testFindContactIdsByProperty(): void
    {
        $this->registry->method('findByName')->willReturn($this->sampleObject);
        $this->connection->method('fetchFirstColumn')->willReturn(['10', '20', '30']);
        $this->assertSame([10, 20, 30], $this->repo->findContactIdsByProperty('vehicles', 'car_model', 'eq', 'Model 3'));
    }
    public function testFindContactIdsRejectsUnknownField(): void
    {
        $this->registry->method('findByName')->willReturn($this->sampleObject);
        $this->expectException(\InvalidArgumentException::class);
        $this->repo->findContactIdsByProperty('vehicles', 'nope', 'eq', 'x');
    }
}
