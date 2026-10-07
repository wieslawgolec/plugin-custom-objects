<?php
declare(strict_types=1);
namespace MauticPlugin\CustomObjectsBundle\Tests\Unit\EventSubscriber;
use MauticPlugin\CustomObjectsBundle\EventSubscriber\SegmentFilterSubscriber;
use MauticPlugin\CustomObjectsBundle\Service\CustomItemRepository;
use MauticPlugin\CustomObjectsBundle\Service\CustomObjectRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
final class SegmentFilterSubscriberTest extends TestCase
{
    private CustomObjectRegistry&MockObject $registry;
    private CustomItemRepository&MockObject $items;
    private SegmentFilterSubscriber $subscriber;
    protected function setUp(): void
    {
        $this->registry = $this->createMock(CustomObjectRegistry::class);
        $this->items = $this->createMock(CustomItemRepository::class);
        $this->subscriber = new SegmentFilterSubscriber($this->registry, $this->items);
    }
    public function testGetSubscribedEventsWithoutLeadBundle(): void
    {
        $this->assertIsArray(SegmentFilterSubscriber::getSubscribedEvents());
    }
    public function testBuildFilterChoices(): void
    {
        $this->registry->method('listAll')->willReturn([['id' => 1, 'name' => 'vehicles', 'singular' => 'Vehicle', 'plural' => 'Vehicles', 'fields' => [['name' => 'car_model', 'type' => 'string', 'label' => 'Car Model'], ['name' => 'year', 'type' => 'integer']], 'date_added' => null, 'date_modified' => null]]);
        $choices = $this->subscriber->buildFilterChoices();
        $this->assertArrayHasKey('custom_object_vehicles', $choices);
        $this->assertSame('text', $choices['custom_object_vehicles']['vehicles.car_model']['properties']['type']);
        $this->assertSame('number', $choices['custom_object_vehicles']['vehicles.year']['properties']['type']);
    }
    public function testContactMatchesFilter(): void
    {
        $this->items->method('findContactIdsByProperty')->willReturn([10, 20]);
        $this->assertTrue($this->subscriber->contactMatchesFilter(10, 'vehicles', 'car_model', 'eq', 'Model 3'));
        $this->assertFalse($this->subscriber->contactMatchesFilter(99, 'vehicles', 'car_model', 'eq', 'Model 3'));
    }
    public function testResolveContactIds(): void
    {
        $this->items->method('findContactIdsByProperty')->willReturn([1, 2, 3]);
        $this->assertSame([1, 2, 3], $this->subscriber->resolveContactIds('vehicles', 'year', 'gte', 2020));
    }
    public function testOnGenerateSegmentFiltersAddsChoices(): void
    {
        $this->registry->method('listAll')->willReturn([['id' => 1, 'name' => 'orders', 'singular' => 'Order', 'plural' => 'Orders', 'fields' => [['name' => 'total', 'type' => 'float', 'label' => 'Total']], 'date_added' => null, 'date_modified' => null]]);
        $event = new class { public array $added = []; public function addChoices(string $group, array $filters): void { $this->added[$group] = $filters; } };
        $this->subscriber->onGenerateSegmentFilters($event);
        $this->assertArrayHasKey('custom_object_orders', $event->added);
    }
}
