<?php
declare(strict_types=1);
namespace MauticPlugin\CustomObjectsBundle\EventSubscriber;
use MauticPlugin\CustomObjectsBundle\Service\CustomItemRepository;
use MauticPlugin\CustomObjectsBundle\Service\CustomObjectRegistry;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
class SegmentFilterSubscriber implements EventSubscriberInterface
{
    private CustomObjectRegistry $registry;
    private CustomItemRepository $itemRepository;
    public function __construct(CustomObjectRegistry $registry, CustomItemRepository $itemRepository)
    {
        $this->registry = $registry;
        $this->itemRepository = $itemRepository;
    }
    public static function getSubscribedEvents(): array
    {
        if (!class_exists(\Mautic\LeadBundle\LeadEvents::class)) return [];
        $events = [];
        if (defined(\Mautic\LeadBundle\LeadEvents::class . '::LIST_FILTERS_CHOICES_ON_GENERATE')) {
            $events[\Mautic\LeadBundle\LeadEvents::LIST_FILTERS_CHOICES_ON_GENERATE] = ['onGenerateSegmentFilters', 0];
        }
        return $events;
    }
    public function onGenerateSegmentFilters(object $event): void
    {
        if (!method_exists($event, 'addChoices') && !method_exists($event, 'setChoices')) return;
        $choices = $this->buildFilterChoices();
        if (method_exists($event, 'addChoices')) {
            foreach ($choices as $group => $filters) { $event->addChoices($group, $filters); }
        }
    }
    public function buildFilterChoices(): array
    {
        $choices = [];
        foreach ($this->registry->listAll() as $object) {
            $groupKey = 'custom_object_' . $object['name'];
            $filters = [];
            foreach ($object['fields'] as $field) {
                $fieldName = $field['name'] ?? '';
                if ($fieldName === '') continue;
                $label = $field['label'] ?? $fieldName;
                $type = $field['type'] ?? 'string';
                $filters[$object['name'] . '.' . $fieldName] = [
                    'label' => sprintf('%s: %s', $object['plural'], $label),
                    'properties' => ['type' => $this->mapFieldTypeToFilterType($type), 'object' => $object['name'], 'field' => $fieldName],
                ];
            }
            if ($filters !== []) $choices[$groupKey] = $filters;
        }
        return $choices;
    }
    public function contactMatchesFilter(int $contactId, string $objectName, string $fieldName, string $operator, mixed $value): bool
    {
        $ids = $this->itemRepository->findContactIdsByProperty($objectName, $fieldName, $operator, $value);
        return in_array($contactId, $ids, true);
    }
    public function resolveContactIds(string $objectName, string $fieldName, string $operator, mixed $value): array
    {
        return $this->itemRepository->findContactIdsByProperty($objectName, $fieldName, $operator, $value);
    }
    private function mapFieldTypeToFilterType(string $type): string
    {
        return match ($type) {
            'integer', 'bigint', 'float' => 'number',
            'boolean' => 'boolean',
            'datetime' => 'datetime',
            'text' => 'text',
            default => 'text',
        };
    }
}
