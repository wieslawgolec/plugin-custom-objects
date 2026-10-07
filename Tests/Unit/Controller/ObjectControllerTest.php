<?php
declare(strict_types=1);
namespace MauticPlugin\CustomObjectsBundle\Tests\Unit\Controller;
use MauticPlugin\CustomObjectsBundle\Controller\ObjectController;
use MauticPlugin\CustomObjectsBundle\Security\CustomObjectsPermissions;
use MauticPlugin\CustomObjectsBundle\Service\AuditLogger;
use MauticPlugin\CustomObjectsBundle\Service\CustomObjectRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
final class ObjectControllerTest extends TestCase
{
    private CustomObjectRegistry&MockObject $registry;
    private AuditLogger&MockObject $audit;
    private ObjectController $controller;
    protected function setUp(): void
    {
        $this->registry = $this->createMock(CustomObjectRegistry::class);
        $this->audit = $this->createMock(AuditLogger::class);
        $this->controller = new ObjectController($this->registry, $this->audit, new CustomObjectsPermissions());
    }
    public function testIndexAction(): void
    {
        $this->registry->method('listAll')->willReturn([['name' => 'vehicles']]);
        $data = json_decode($this->controller->indexAction()->getContent(), true);
        $this->assertSame(1, $data['count']);
    }
    public function testNewActionSuccess(): void
    {
        $this->registry->method('register')->willReturn(['id' => 1, 'name' => 'vehicles', 'tableName' => 'custom_obj_vehicles']);
        $this->audit->expects($this->once())->method('log');
        $request = new Request([], [], [], [], [], [], json_encode(['name' => 'vehicles', 'singular' => 'Vehicle', 'plural' => 'Vehicles', 'fields' => [['name' => 'car_model', 'type' => 'string']]]));
        $this->assertSame(201, $this->controller->newAction($request)->getStatusCode());
    }
    public function testNewActionValidation(): void
    {
        $this->assertSame(400, $this->controller->newAction(new Request([], [], [], [], [], [], json_encode(['name' => ''])))->getStatusCode());
    }
    public function testDeleteAction(): void
    {
        $this->registry->method('unregister')->willReturn(true);
        $this->audit->expects($this->once())->method('log');
        $data = json_decode($this->controller->deleteAction('vehicles')->getContent(), true);
        $this->assertTrue($data['deleted']);
    }
    public function testDeniedWhenNoPermission(): void
    {
        $ctrl = new ObjectController($this->registry, $this->audit, new CustomObjectsPermissions([CustomObjectsPermissions::VIEW => false]));
        $this->assertSame(403, $ctrl->indexAction()->getStatusCode());
    }
    public function testGetFormViewData(): void
    {
        $this->registry->method('listAll')->willReturn([]);
        $data = $this->controller->getFormViewData();
        $this->assertContains('string', $data['fieldTypes']);
    }
}
