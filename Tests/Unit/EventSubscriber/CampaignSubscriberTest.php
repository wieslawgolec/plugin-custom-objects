<?php

declare(strict_types=1);

namespace MauticPlugin\CustomObjectsBundle\Tests\Unit\EventSubscriber;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use MauticPlugin\CustomObjectsBundle\EventSubscriber\CampaignSubscriber;
use MauticPlugin\CustomObjectsBundle\Service\DynamicSchemaManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class CampaignSubscriberTest extends TestCase
{
    private Connection&MockObject $connection;
    private DynamicSchemaManager&MockObject $schemaManager;
    private CampaignSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->connection    = $this->createMock(Connection::class);
        $this->schemaManager = $this->createMock(DynamicSchemaManager::class);
        $this->subscriber    = new CampaignSubscriber($this->connection, $this->schemaManager);
    }

    public function testGetSubscribedEventsReturnsEmptyWithoutCampaignBundle(): void
    {
        // In standalone CI the CampaignEvents class is not present
        $events = CampaignSubscriber::getSubscribedEvents();
        $this->assertIsArray($events);
    }

    public function testOnCampaignBuildAddsDecisionWhenMethodExists(): void
    {
        $event = new class {
            public array $decisions = [];

            public function addDecision(string $key, array $config): void
            {
                $this->decisions[$key] = $config;
            }
        };

        $this->subscriber->onCampaignBuild($event);

        $this->assertArrayHasKey('customobjects.check_property', $event->decisions);
        $this->assertSame('Check Custom Object Property', $event->decisions['customobjects.check_property']['label']);
    }

    public function testOnCampaignBuildIgnoresEventWithoutAddDecision(): void
    {
        $event = new \stdClass();
        // Should not throw
        $this->subscriber->onCampaignBuild($event);
        $this->assertTrue(true);
    }

    public function testEvaluateCustomObjectPropertyReturnsFalseWhenTableMissing(): void
    {
        $this->schemaManager
            ->method('tableExists')
            ->with('vehicles')
            ->willReturn(false);

        $this->connection->expects($this->never())->method('executeQuery');

        $result = $this->subscriber->evaluateCustomObjectProperty(42, 'vehicles', 'car_model', 'Model 3');
        $this->assertFalse($result);
    }

    public function testEvaluateCustomObjectPropertyReturnsTrueWhenMatchFound(): void
    {
        $this->schemaManager->method('tableExists')->willReturn(true);
        $this->schemaManager->method('buildTableName')->with('vehicles')->willReturn('custom_obj_vehicles');
        $this->schemaManager->method('sanitizeIdentifier')->with('car_model')->willReturn('car_model');

        $resultMock = $this->createMock(Result::class);
        $resultMock->method('fetchOne')->willReturn(1);

        $this->connection
            ->expects($this->once())
            ->method('executeQuery')
            ->with(
                $this->stringContains('custom_obj_vehicles'),
                $this->equalTo(['contactId' => 42, 'value' => 'Model 3'])
            )
            ->willReturn($resultMock);

        $result = $this->subscriber->evaluateCustomObjectProperty(42, 'vehicles', 'car_model', 'Model 3');
        $this->assertTrue($result);
    }

    public function testEvaluateCustomObjectPropertyReturnsFalseWhenNoMatch(): void
    {
        $this->schemaManager->method('tableExists')->willReturn(true);
        $this->schemaManager->method('buildTableName')->willReturn('custom_obj_vehicles');
        $this->schemaManager->method('sanitizeIdentifier')->willReturn('car_model');

        $resultMock = $this->createMock(Result::class);
        $resultMock->method('fetchOne')->willReturn(0);

        $this->connection->method('executeQuery')->willReturn($resultMock);

        $result = $this->subscriber->evaluateCustomObjectProperty(42, 'vehicles', 'car_model', 'Model X');
        $this->assertFalse($result);
    }

    public function testCountForContact(): void
    {
        $this->schemaManager->method('tableExists')->willReturn(true);
        $this->schemaManager->method('buildTableName')->willReturn('custom_obj_orders');

        $resultMock = $this->createMock(Result::class);
        $resultMock->method('fetchOne')->willReturn(3);

        $this->connection->method('executeQuery')->willReturn($resultMock);

        $this->assertSame(3, $this->subscriber->countForContact(10, 'orders'));
    }

    public function testCountForContactWhenTableMissing(): void
    {
        $this->schemaManager->method('tableExists')->willReturn(false);
        $this->connection->expects($this->never())->method('executeQuery');

        $this->assertSame(0, $this->subscriber->countForContact(10, 'orders'));
    }
}
