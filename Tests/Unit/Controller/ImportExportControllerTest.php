<?php
declare(strict_types=1);
namespace MauticPlugin\CustomObjectsBundle\Tests\Unit\Controller;
use MauticPlugin\CustomObjectsBundle\Controller\ImportExportController;
use MauticPlugin\CustomObjectsBundle\Security\CustomObjectsPermissions;
use MauticPlugin\CustomObjectsBundle\Service\AuditLogger;
use MauticPlugin\CustomObjectsBundle\Service\ImportExportService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
final class ImportExportControllerTest extends TestCase
{
    private ImportExportService&MockObject $ie;
    private AuditLogger&MockObject $audit;
    private ImportExportController $controller;
    protected function setUp(): void
    {
        $this->ie = $this->createMock(ImportExportService::class);
        $this->audit = $this->createMock(AuditLogger::class);
        $this->controller = new ImportExportController($this->ie, $this->audit, new CustomObjectsPermissions());
    }
    public function testExportCsv(): void
    {
        $this->ie->method('exportToCsv')->willReturn("contact_id,car_model\n1,Model 3\n");
        $this->audit->expects($this->once())->method('log');
        $response = $this->controller->exportCsvAction('vehicles');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }
    public function testImportCsv(): void
    {
        $this->ie->method('importFromCsv')->willReturn(['imported' => 2, 'skipped' => 0, 'errors' => []]);
        $this->audit->expects($this->once())->method('log');
        $data = json_decode($this->controller->importCsvAction('vehicles', new Request([], [], [], [], [], [], "contact_id,car_model\n1,X\n"))->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame(2, $data['imported']);
    }
    public function testExportDefinition(): void
    {
        $this->ie->method('exportDefinition')->willReturn('{"name":"vehicles"}');
        $this->assertSame(200, $this->controller->exportDefinitionAction('vehicles')->getStatusCode());
    }
    public function testImportDefinition(): void
    {
        $this->ie->method('importDefinition')->willReturn(['id' => 1, 'name' => 'orders', 'tableName' => 'custom_obj_orders']);
        $this->audit->expects($this->once())->method('log');
        $this->assertSame(201, $this->controller->importDefinitionAction(new Request([], [], [], [], [], [], '{"name":"orders","fields":[{"name":"total","type":"float"}]}'))->getStatusCode());
    }
    public function testDeniedExport(): void
    {
        $ctrl = new ImportExportController($this->ie, $this->audit, new CustomObjectsPermissions([CustomObjectsPermissions::EXPORT => false]));
        $this->assertSame(403, $ctrl->exportCsvAction('vehicles')->getStatusCode());
    }
}
