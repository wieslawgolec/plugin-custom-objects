<?php

declare(strict_types=1);

namespace MauticPlugin\CustomObjectsBundle\Controller;

use MauticPlugin\CustomObjectsBundle\Service\DynamicSchemaManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Minimal UI controller for creating / listing custom object tables.
 *
 * In a full Mautic install this would extend CommonController / AbstractFormController
 * and render Twig templates. Here we keep a thin, testable layer that only talks
 * to DynamicSchemaManager.
 */
class SchemaController
{
    private DynamicSchemaManager $schemaManager;

    public function __construct(DynamicSchemaManager $schemaManager)
    {
        $this->schemaManager = $schemaManager;
    }

    /**
     * Create a custom object table from request payload.
     *
     * Expected JSON body:
     * {
     *   "objectName": "Vehicles",
     *   "fields": [
     *     {"name": "car_model", "type": "string"},
     *     {"name": "year", "type": "integer"}
     *   ]
     * }
     */
    public function createAction(Request $request): Response
    {
        $payload = json_decode($request->getContent() ?: '{}', true);
        if (!is_array($payload)) {
            return new JsonResponse(['error' => 'Invalid JSON body'], 400);
        }

        $objectName = trim((string) ($payload['objectName'] ?? ''));
        $fields     = $payload['fields'] ?? [];

        if ($objectName === '') {
            return new JsonResponse(['error' => 'objectName is required'], 400);
        }

        if (!is_array($fields)) {
            return new JsonResponse(['error' => 'fields must be an array'], 400);
        }

        try {
            $tableName = $this->schemaManager->createCustomObjectTable($objectName, $fields);

            return new JsonResponse([
                'success'   => true,
                'tableName' => $tableName,
                'objectName'=> $objectName,
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'Schema creation failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * List existing custom object tables.
     */
    public function listAction(): Response
    {
        $tables = $this->schemaManager->listCustomObjectTables();

        return new JsonResponse([
            'tables' => $tables,
            'count'  => count($tables),
        ]);
    }

    /**
     * Drop a custom object table.
     */
    public function dropAction(string $objectName): Response
    {
        try {
            $dropped = $this->schemaManager->dropCustomObjectTable($objectName);

            return new JsonResponse([
                'success' => true,
                'dropped' => $dropped,
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }
}
