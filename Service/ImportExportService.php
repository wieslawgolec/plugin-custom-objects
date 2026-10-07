<?php
declare(strict_types=1);
namespace MauticPlugin\CustomObjectsBundle\Service;
use InvalidArgumentException;
use RuntimeException;
class ImportExportService
{
    private CustomItemRepository $itemRepository;
    private CustomObjectRegistry $registry;
    private DynamicSchemaManager $schemaManager;
    public function __construct(CustomItemRepository $itemRepository, CustomObjectRegistry $registry, DynamicSchemaManager $schemaManager)
    {
        $this->itemRepository = $itemRepository;
        $this->registry = $registry;
        $this->schemaManager = $schemaManager;
    }
    public function exportToCsv(string $objectName, int $limit = 10000): string
    {
        $object = $this->requireObject($objectName);
        $fields = $this->fieldNames($object['fields']);
        $header = array_merge(['contact_id'], $fields);
        $items = $this->itemRepository->list($objectName, null, $limit, 0);
        $fh = fopen('php://temp', 'r+');
        if ($fh === false) throw new RuntimeException('Unable to open temp stream.');
        fputcsv($fh, $header);
        foreach ($items as $item) {
            $row = [(string) ($item['contact_id'] ?? '')];
            foreach ($fields as $f) { $row[] = (string) ($item[$f] ?? ''); }
            fputcsv($fh, $row);
        }
        rewind($fh);
        $csv = stream_get_contents($fh) ?: '';
        fclose($fh);
        return $csv;
    }
    public function importFromCsv(string $objectName, string $csvContent): array
    {
        $object = $this->requireObject($objectName);
        $fields = $this->fieldNames($object['fields']);
        $lines = preg_split('/\r\n|\r|\n/', trim($csvContent)) ?: [];
        if ($lines === [] || $lines[0] === '') throw new InvalidArgumentException('CSV is empty.');
        $header = str_getcsv(array_shift($lines));
        if ($header === false || $header === [null] || $header === []) throw new InvalidArgumentException('CSV header is missing.');
        $header = array_map(static fn ($h) => strtolower(trim((string) $h)), $header);
        if (!in_array('contact_id', $header, true)) throw new InvalidArgumentException('CSV must contain a contact_id column.');
        $imported = 0; $skipped = 0; $errors = [];
        foreach ($lines as $lineNum => $line) {
            if (trim($line) === '') continue;
            $cols = str_getcsv($line);
            if ($cols === false || count($cols) !== count($header)) { $errors[] = sprintf('Line %d: column count mismatch.', $lineNum + 2); $skipped++; continue; }
            $row = array_combine($header, $cols);
            if ($row === false) { $skipped++; continue; }
            $contactId = (int) ($row['contact_id'] ?? 0);
            if ($contactId <= 0) { $errors[] = sprintf('Line %d: invalid contact_id.', $lineNum + 2); $skipped++; continue; }
            $data = [];
            foreach ($fields as $f) { if (array_key_exists($f, $row)) $data[$f] = $row[$f]; }
            try { $this->itemRepository->create($objectName, $contactId, $data); $imported++; }
            catch (\Throwable $e) { $errors[] = sprintf('Line %d: %s', $lineNum + 2, $e->getMessage()); $skipped++; }
        }
        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }
    public function exportDefinition(string $objectName): string
    {
        $object = $this->requireObject($objectName);
        return json_encode(['name' => $object['name'], 'singular' => $object['singular'], 'plural' => $object['plural'], 'fields' => $object['fields']], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }
    public function importDefinition(string $json): array
    {
        $data = json_decode($json, true);
        if (!is_array($data) || empty($data['name']) || empty($data['fields'])) throw new InvalidArgumentException('Invalid object definition JSON.');
        return $this->registry->register((string) $data['name'], (string) ($data['singular'] ?? $data['name']), (string) ($data['plural'] ?? $data['name'] . 's'), $data['fields']);
    }
    private function requireObject(string $objectName): array
    {
        $object = $this->registry->findByName($objectName);
        if ($object === null) throw new InvalidArgumentException(sprintf('Custom object "%s" not found.', $objectName));
        return $object;
    }
    private function fieldNames(array $fields): array
    {
        $names = [];
        foreach ($fields as $f) { if (!empty($f['name'])) $names[] = $this->schemaManager->sanitizeIdentifier((string) $f['name']); }
        return $names;
    }
}
