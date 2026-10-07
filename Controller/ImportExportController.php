<?php
declare(strict_types=1);
namespace MauticPlugin\CustomObjectsBundle\Controller;
use MauticPlugin\CustomObjectsBundle\Security\CustomObjectsPermissions;
use MauticPlugin\CustomObjectsBundle\Service\AuditLogger;
use MauticPlugin\CustomObjectsBundle\Service\ImportExportService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
class ImportExportController
{
    private ImportExportService $importExport;
    private AuditLogger $auditLogger;
    private CustomObjectsPermissions $permissions;
    public function __construct(ImportExportService $importExport, AuditLogger $auditLogger, CustomObjectsPermissions $permissions)
    {
        $this->importExport = $importExport;
        $this->auditLogger = $auditLogger;
        $this->permissions = $permissions;
    }
    public function exportCsvAction(string $objectName): Response
    {
        if (!$this->permissions->isGranted(CustomObjectsPermissions::EXPORT)) return new JsonResponse(['error' => 'Access denied'], 403);
        try {
            $csv = $this->importExport->exportToCsv($objectName);
            $this->auditLogger->log(AuditLogger::ACTION_EXPORT, $objectName, null, null, ['format' => 'csv']);
            return new Response($csv, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => sprintf('attachment; filename="%s_export.csv"', $objectName)]);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 404);
        }
    }
    public function importCsvAction(string $objectName, Request $request): Response
    {
        if (!$this->permissions->isGranted(CustomObjectsPermissions::IMPORT)) return new JsonResponse(['error' => 'Access denied'], 403);
        $csv = $request->getContent();
        if ($csv === '' || $csv === false) {
            $file = $request->files->get('file');
            if ($file !== null) $csv = file_get_contents($file->getPathname()) ?: '';
        }
        if ($csv === '') return new JsonResponse(['error' => 'Empty CSV body'], 400);
        try {
            $result = $this->importExport->importFromCsv($objectName, $csv);
            $this->auditLogger->log(AuditLogger::ACTION_IMPORT, $objectName, null, null, $result);
            return new JsonResponse(['success' => true] + $result);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }
    public function exportDefinitionAction(string $objectName): Response
    {
        if (!$this->permissions->isGranted(CustomObjectsPermissions::EXPORT)) return new JsonResponse(['error' => 'Access denied'], 403);
        try {
            $json = $this->importExport->exportDefinition($objectName);
            return new Response($json, 200, ['Content-Type' => 'application/json', 'Content-Disposition' => sprintf('attachment; filename="%s_definition.json"', $objectName)]);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 404);
        }
    }
    public function importDefinitionAction(Request $request): Response
    {
        if (!$this->permissions->isGranted(CustomObjectsPermissions::IMPORT)) return new JsonResponse(['error' => 'Access denied'], 403);
        $json = $request->getContent() ?: '{}';
        try {
            $result = $this->importExport->importDefinition($json);
            $this->auditLogger->log(AuditLogger::ACTION_CREATE_OBJECT, $result['name'], null, null, ['via' => 'import_definition']);
            return new JsonResponse(['success' => true] + $result, 201);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }
}
