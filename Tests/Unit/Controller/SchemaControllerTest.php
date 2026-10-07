<?php

declare(strict_types=1);

namespace MauticPlugin\CustomObjectsBundle\Tests\Unit\Controller;

use MauticPlugin\CustomObjectsBundle\Controller\SchemaController;
use MauticPlugin\CustomObjectsBundle\Service\DynamicSchemaManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class SchemaControllerTest extends TestCase
{
    private DynamicSchemaManager&MockObject $schemaManager;
    private SchemaController $controller;

    protected function setUp(): void
    {
        $this->schemaManager = $this->createMock(DynamicSchemaManager::class);
        $this->controller    = new SchemaController($this->schemaManager);
    }

    public function testCreateActionSuccess(): void
    {
        $this->schemaManager
            ->expects($this->once())
            ->method('createCustomObjectTable')
            ->with('Vehicles', [
                ['name' => 'car_model', 'type' => 'string'],
            ])
            ->willReturn('custom_obj_vehicles');

        $request = new Request([], [], [], [], [], [], json_encode([
            'objectName' => 'Vehicles',
            'fields'     => [
                ['name' => 'car_model', 'type' => 'string'],
            ],
        ]));

        $response = $this->controller->createAction($request);

        $this->assertSame(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('custom_obj_vehicles', $data['tableName']);
    }

    public function testCreateActionRejectsMissingObjectName(): void
    {
        $request  = new Request([], [], [], [], [], [], json_encode(['fields' => []]));
        $response = $this->controller->createAction($request);

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('objectName', $data['error']);
    }

    public function testCreateActionRejectsInvalidJson(): void
    {
        $request  = new Request([], [], [], [], [], [], 'not-json');
        $response = $this->controller->createAction($request);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function testCreateActionPropagatesInvalidArgument(): void
    {
        $this->schemaManager
            ->method('createCustomObjectTable')
            ->willThrowException(new \InvalidArgumentException('bad type'));

        $request = new Request([], [], [], [], [], [], json_encode([
            'objectName' => 'X',
            'fields'     => [['name' => 'f', 'type' => 'blob']],
        ]));

        $response = $this->controller->createAction($request);
        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('bad type', $data['error']);
    }

    public function testListAction(): void
    {
        $this->schemaManager
            ->method('listCustomObjectTables')
            ->willReturn(['custom_obj_vehicles', 'custom_obj_orders']);

        $response = $this->controller->listAction();
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame(2, $data['count']);
        $this->assertCount(2, $data['tables']);
    }

    public function testDropAction(): void
    {
        $this->schemaManager
            ->expects($this->once())
            ->method('dropCustomObjectTable')
            ->with('Vehicles')
            ->willReturn(true);

        $response = $this->controller->dropAction('Vehicles');
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertTrue($data['dropped']);
    }
}
