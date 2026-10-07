<?php
declare(strict_types=1);
namespace MauticPlugin\CustomObjectsBundle\Controller;
use MauticPlugin\CustomObjectsBundle\Security\CustomObjectsPermissions;
use MauticPlugin\CustomObjectsBundle\Service\AuditLogger;
use MauticPlugin\CustomObjectsBundle\Service\CustomObjectRegistry;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
class ObjectController
{
    private CustomObjectRegistry $registry;
    private AuditLogger $auditLogger;
    private CustomObjectsPermissions $permissions;
    public function __construct(CustomObjectRegistry $registry, AuditLogger $auditLogger, CustomObjectsPermissions $permissions)
    {
        $this->registry = $registry;
        $this->auditLogger = $auditLogger;
        $this->permissions = $permissions;
    }
    public function indexAction(): Response
    {
        if (!$this->permissions->isGranted(CustomObjectsPermissions::VIEW)) return new JsonResponse(['error' => 'Access denied'], 403);
        $objects = $this->registry->listAll();
        return new JsonResponse(['objects' => $objects, 'count' => count($objects)]);
    }
    public function newAction(Request $request): Response
    {
        if (!$this->permissions->isGranted(CustomObjectsPermissions::CREATE)) return new JsonResponse(['error' => 'Access denied'], 403);
        $payload = json_decode($request->getContent() ?: '{}', true);
        if (!is_array($payload)) return new JsonResponse(['error' => 'Invalid JSON'], 400);
        $name = trim((string) ($payload['name'] ?? ''));
        $singular = trim((string) ($payload['singular'] ?? $name));
        $plural = trim((string) ($payload['plural'] ?? $name . 's'));
        $fields = $payload['fields'] ?? [];
        if ($name === '' || !is_array($fields) || $fields === []) return new JsonResponse(['error' => 'name and fields are required'], 400);
        try {
            $result = $this->registry->register($name, $singular, $plural, $fields);
            $this->auditLogger->log(AuditLogger::ACTION_CREATE_OBJECT, $result['name'], null, null, ['singular' => $singular, 'plural' => $plural, 'fields' => $fields]);
            return new JsonResponse(['success' => true] + $result, 201);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }
    public function viewAction(string $objectName): Response
    {
        if (!$this->permissions->isGranted(CustomObjectsPermissions::VIEW)) return new JsonResponse(['error' => 'Access denied'], 403);
        $object = $this->registry->findByName($objectName);
        if ($object === null) return new JsonResponse(['error' => 'Not found'], 404);
        return new JsonResponse(['object' => $object]);
    }
    public function deleteAction(string $objectName): Response
    {
        if (!$this->permissions->isGranted(CustomObjectsPermissions::DELETE)) return new JsonResponse(['error' => 'Access denied'], 403);
        $deleted = $this->registry->unregister($objectName);
        if ($deleted) $this->auditLogger->log(AuditLogger::ACTION_DELETE_OBJECT, $objectName);
        return new JsonResponse(['success' => true, 'deleted' => $deleted]);
    }
    public function getFormViewData(): array
    {
        return ['objects' => $this->registry->listAll(), 'fieldTypes' => ['string', 'integer', 'text', 'boolean', 'datetime', 'float', 'bigint']];
    }
}
