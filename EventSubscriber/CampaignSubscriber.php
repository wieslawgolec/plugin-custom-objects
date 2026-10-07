<?php

declare(strict_types=1);

namespace MauticPlugin\CustomObjectsBundle\EventSubscriber;

use Doctrine\DBAL\Connection;
use MauticPlugin\CustomObjectsBundle\Service\DynamicSchemaManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Injects a custom Campaign Decision that evaluates properties on dynamically
 * created custom-object tables.
 *
 * In a full Mautic environment this listens to CampaignEvents::CAMPAIGN_ON_BUILD
 * and CampaignExecutionEvent. For standalone unit tests the evaluation logic is
 * exposed as a pure method that only needs a Connection.
 */
class CampaignSubscriber implements EventSubscriberInterface
{
    private Connection $connection;
    private DynamicSchemaManager $schemaManager;

    public function __construct(Connection $connection, DynamicSchemaManager $schemaManager)
    {
        $this->connection    = $connection;
        $this->schemaManager = $schemaManager;
    }

    public static function getSubscribedEvents(): array
    {
        // Soft dependency: only register if the real event class exists (full Mautic).
        if (!class_exists(\Mautic\CampaignBundle\CampaignEvents::class)) {
            return [];
        }

        return [
            \Mautic\CampaignBundle\CampaignEvents::CAMPAIGN_ON_BUILD => ['onCampaignBuild', 0],
        ];
    }

    /**
     * Register the decision node in the Campaign Builder UI.
     * Signature is intentionally loose so the class can be unit-tested without
     * the full CampaignBundle.
     *
     * @param object $event  Expected to be CustomActionTypeEvent / CampaignBuilderEvent
     */
    public function onCampaignBuild(object $event): void
    {
        if (!method_exists($event, 'addDecision')) {
            return;
        }

        $event->addDecision('customobjects.check_property', [
            'label'       => 'Check Custom Object Property',
            'description' => 'Triggers paths depending on values stored in custom object tables.',
            'eventName'   => 'mautic.customobjects.on_campaign_trigger_decision',
            'formType'    => false, // production: point to a FormType that lists tables/fields
        ]);
    }

    /**
     * Pure evaluation helper used both by the real CampaignExecutionEvent listener
     * and by unit tests.
     *
     * @param int    $contactId
     * @param string $objectName   e.g. "vehicles"
     * @param string $fieldName    e.g. "car_model"
     * @param mixed  $expectedValue
     */
    public function evaluateCustomObjectProperty(
        int $contactId,
        string $objectName,
        string $fieldName,
        mixed $expectedValue
    ): bool {
        if (!$this->schemaManager->tableExists($objectName)) {
            return false;
        }

        $tableName = $this->schemaManager->buildTableName($objectName);
        $column    = $this->schemaManager->sanitizeIdentifier($fieldName);

        // Basic whitelist already done by sanitize; still quote identifiers safely.
        $sql = sprintf(
            'SELECT COUNT(*) FROM %s WHERE contact_id = :contactId AND %s = :value',
            $this->quoteIdentifier($tableName),
            $this->quoteIdentifier($column)
        );

        $count = (int) $this->connection->executeQuery($sql, [
            'contactId' => $contactId,
            'value'     => $expectedValue,
        ])->fetchOne();

        return $count > 0;
    }

    /**
     * Count rows for a contact in a custom object table.
     */
    public function countForContact(int $contactId, string $objectName): int
    {
        if (!$this->schemaManager->tableExists($objectName)) {
            return 0;
        }

        $tableName = $this->schemaManager->buildTableName($objectName);
        $sql       = sprintf(
            'SELECT COUNT(*) FROM %s WHERE contact_id = :contactId',
            $this->quoteIdentifier($tableName)
        );

        return (int) $this->connection->executeQuery($sql, [
            'contactId' => $contactId,
        ])->fetchOne();
    }

    private function quoteIdentifier(string $name): string
    {
        // Minimal safe quoting for MySQL / SQLite (used in tests)
        return '`' . str_replace('`', '``', $name) . '`';
    }
}
