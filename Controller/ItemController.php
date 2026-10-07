<?php
declare(strict_types=1);
namespace MauticPlugin\CustomObjectsBundle\Controller;
use MauticPlugin\CustomObjectsBundle\Security\CustomObjectsPermissions;
use MauticPlugin\CustomObjectsBundle\Service\AuditLogger;
use MauticPlugin\CustomObjectsBundle\Service\CustomItemRepository;
use MauticPlugin\CustomObjectsBundle\Service\CustomObjectRegistry;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
class ItemController
{
    private CustomItemRepository $items;
    private CustomObjectRegistry $registry;
    private AuditLogger $auditLogger;
    private CustomObjectsPermissions $permissions;
    public function __construct(CustomItemRepository $items, CustomObjectRegistry $registry, AuditLogger $auditLogger, CustomObjectsPermissions $permissions)
    {
        $this->items = $items;
        $this->registry = $registry;
        $this->auditLogger = $auditLogger;
        $this->permissions = $permissions;
    }
    public function indexAction(string $objectName, Request $request): Response
    {
        if (!$this->permissions->isGranted(CustomObjectsPermissions::ITEMS_VIEW)) return new JsonResponse(['error' => 'Access denied'], 403);
        $contactId = $request->query->getInt('contactId') ?: null;
        $limit = max(1, min(500, $request->query->getInt('limit', 50)));
        $offset = max(0, $request->query->getInt('offset', 0));
        try {
            $list = $this->items->list($objectName, $contactId, $limit, $offset);
            $total = $this->items->count($objectName, $contactId);
            return new JsonResponse(['items' => $list, 'total' => $total, 'limit' => $limit, 'offset' => $offset]);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 404);
        }
    }
    public function newAction(string $objectName, Request $request): Response
    {
        if (!$this->permissions->isGranted(CustomObjectsPermissions::ITEMS_CREATE)) return new JsonResponse(['error' => 'Access denied'], 403);
        $payload = json_decode($request->getContent() ?: '{}', true);
        if (!is_array($payload)) return new JsonResponse(['error' => 'Invalid JSON'], 400);
        $contactId = (int) ($payload['contact_id'] ?? 0);
        if ($contactId <= 0) return new JsonResponse(['error' => 'contact_id is required'], 400);
        unset($payload['contact_id'], $payload['id']);
        try {
            $id = $this->items->create($objectName, $contactId, $payload);
            $this->auditLogger->log(AuditLogger::ACTION_CREATE_ITEM, $objectName, $id, null, ['contact_id' => $contactId]);
            return new JsonResponse(['success' => true, 'id' => $id], 201);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }
    public function editAction(string $objectName, int $itemId, Request $request): Response
    {
        if (!$this->permissions->isGranted(CustomObjectsPermissions::ITEMS_EDIT)) return new JsonResponse(['error' => 'Access denied'], 403);
        $payload = json_decode($request->getContent() ?: '{}', true);
        if (!is_array($payload)) return new JsonResponse(['error' => 'Invalid JSON'], 400);
        try {
            $ok = $this->items->update($objectName, $itemId, $payload);
            if ($ok) $this->auditLogger->log(AuditLogger::ACTION_UPDATE_ITEM, $objectName, $itemId);
            return new JsonResponse(['success' => $ok]);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }
    public function deleteAction(string $objectName, int $itemId): Response
    {
        if (!$this->permissions->isGranted(CustomObjectsPermissions::ITEMS_DELETE)) return new JsonResponse(['error' => 'Access denied'], 403);
        try {
            $ok = $this->items->delete($objectName, $itemId);
            if ($ok) $this->auditLogger->log(AuditLogger::ACTION_DELETE_ITEM, $objectName, $itemId);
            return new JsonResponse(['success' => $ok]);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 404);
        }
    }
    public function viewAction(string $objectName, int $itemId): Response
    {
        if (!$this->permissions->isGranted(CustomObjectsPermissions::ITEMS_VIEW)) return new JsonResponse(['error' => 'Access denied'], 403);
        try {
            $item = $this->items->find($objectName, $itemId);
            if ($item === null) return new JsonResponse(['error' => 'Not found'], 404);
            return new JsonResponse(['item' => $item]);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 404);
        }
    }
}
