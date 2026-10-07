<?php
declare(strict_types=1);
namespace MauticPlugin\CustomObjectsBundle\Tests\Unit\Controller;
use MauticPlugin\CustomObjectsBundle\Controller\ItemController;
use MauticPlugin\CustomObjectsBundle\Security\CustomObjectsPermissions;
use MauticPlugin\CustomObjectsBundle\Service\AuditLogger;
use MauticPlugin\CustomObjectsBundle\Service\CustomItemRepository;
use MauticPlugin\CustomObjectsBundle\Service\CustomObjectRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
final class ItemControllerTest extends TestCase
{
    private CustomItemRepository&MockObject $items;
    private CustomObjectRegistry&MockObject $registry;
    private AuditLogger&MockObject $audit;
    private ItemController $controller;
    protected function setUp(): void
    {
        $this->items = $this->createMock(CustomItemRepository::class);
        $this->registry = $this->createMock(CustomObjectRegistry::class);
        $this->audit = $this->createMock(AuditLogger::class);
        $this->controller = new ItemController($this->items, $this->registry, $this->audit, new CustomObjectsPermissions());
    }
    public function testIndexAction(): void
    {
        $this->items->method('list')->willReturn([['id' => 1]]);
        $this->items->method('count')->willReturn(1);
        $data = json_decode($this->controller->indexAction('vehicles', new Request())->getContent(), true);
        $this->assertSame(1, $data['total']);
    }
    public function testNewAction(): void
    {
        $this->items->method('create')->willReturn(9);
        $this->audit->expects($this->once())->method('log');
        $request = new Request([], [], [], [], [], [], json_encode(['contact_id' => 42, 'car_model' => 'Model 3']));
        $this->assertSame(201, $this->controller->newAction('vehicles', $request)->getStatusCode());
    }
    public function testNewRequiresContactId(): void
    {
        $this->assertSame(400, $this->controller->newAction('vehicles', new Request([], [], [], [], [], [], json_encode(['car_model' => 'X'])))->getStatusCode());
    }
    public function testEditAction(): void
    {
        $this->items->method('update')->willReturn(true);
        $this->audit->expects($this->once())->method('log');
        $this->assertSame(200, $this->controller->editAction('vehicles', 9, new Request([], [], [], [], [], [], json_encode(['car_model' => 'Model Y'])))->getStatusCode());
    }
    public function testDeleteAction(): void
    {
        $this->items->method('delete')->willReturn(true);
        $this->audit->expects($this->once())->method('log');
        $data = json_decode($this->controller->deleteAction('vehicles', 9)->getContent(), true);
        $this->assertTrue($data['success']);
    }
    public function testViewAction(): void
    {
        $this->items->method('find')->willReturn(['id' => 9, 'contact_id' => 42]);
        $data = json_decode($this->controller->viewAction('vehicles', 9)->getContent(), true);
        $this->assertSame(9, $data['item']['id']);
    }
}
