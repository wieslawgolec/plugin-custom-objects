<?php
declare(strict_types=1);
namespace MauticPlugin\CustomObjectsBundle\Tests\Unit\Service;
use MauticPlugin\CustomObjectsBundle\Service\CustomItemRepository;
use MauticPlugin\CustomObjectsBundle\Service\CustomObjectRegistry;
use MauticPlugin\CustomObjectsBundle\Service\DynamicSchemaManager;
use MauticPlugin\CustomObjectsBundle\Service\ImportExportService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
final class ImportExportServiceTest extends TestCase
{
    private CustomItemRepository&MockObject $items;
    private CustomObjectRegistry&MockObject $registry;
    private DynamicSchemaManager&MockObject $schemaManager;
    private ImportExportService $service;
    private array $object = ['id' => 1, 'name' => 'vehicles', 'singular' => 'Vehicle', 'plural' => 'Vehicles', 'fields' => [['name' => 'car_model', 'type' => 'string'], ['name' => 'year', 'type' => 'integer']], 'date_added' => null, 'date_modified' => null];
    protected function setUp(): void
    {
        $this->items = $this->createMock(CustomItemRepository::class);
        $this->registry = $this->createMock(CustomObjectRegistry::class);
        $this->schemaManager = $this->createMock(DynamicSchemaManager::class);
        $this->schemaManager->method('sanitizeIdentifier')->willReturnCallback(static fn (string $n) => strtolower($n));
        $this->registry->method('findByName')->willReturn($this->object);
        $this->service = new ImportExportService($this->items, $this->registry, $this->schemaManager);
    }
    public function testExportToCsv(): void
    {
        $this->items->method('list')->willReturn([['id' => 1, 'contact_id' => 10, 'car_model' => 'Model 3', 'year' => 2024]]);
        $csv = $this->service->exportToCsv('vehicles');
        $this->assertStringContainsString('contact_id,car_model,year', $csv);
        $this->assertStringContainsString('Model 3', $csv);
    }
    public function testImportFromCsv(): void
    {
        $csv = "contact_id,car_model,year\n10,Model 3,2024\n20,Model Y,2025\n";
        $this->items->expects($this->exactly(2))->method('create')->willReturnOnConsecutiveCalls(1, 2);
        $result = $this->service->importFromCsv('vehicles', $csv);
        $this->assertSame(2, $result['imported']);
        $this->assertSame(0, $result['skipped']);
    }
    public function testImportRejectsMissingContactIdColumn(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->importFromCsv('vehicles', "foo,bar\n1,2\n");
    }
    public function testImportRejectsEmptyCsv(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->importFromCsv('vehicles', '');
    }
    public function testExportDefinition(): void
    {
        $data = json_decode($this->service->exportDefinition('vehicles'), true);
        $this->assertSame('vehicles', $data['name']);
        $this->assertCount(2, $data['fields']);
    }
    public function testImportDefinition(): void
    {
        $json = json_encode(['name' => 'orders', 'singular' => 'Order', 'plural' => 'Orders', 'fields' => [['name' => 'total', 'type' => 'float']]]);
        $this->registry->expects($this->once())->method('register')->willReturn(['id' => 5, 'name' => 'orders', 'tableName' => 'custom_obj_orders']);
        $result = $this->service->importDefinition($json);
        $this->assertSame('orders', $result['name']);
    }
    public function testImportDefinitionRejectsInvalidJson(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->importDefinition('{"foo":1}');
    }
}
