<?php

namespace App\Models;

use Exception;
use SimpleXMLElement;
use stdClass;
use RuntimeException;
use InvalidArgumentException;

class Delivery
{
    private array $items = [];
    private array $loadStats = [];

    public function __construct(string $dataPath = null)
    {
        $this->loadData($dataPath ?? public_path('data'));
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function getFilteredAndSortedItems(array $filters = [], string $sort = '', string $search = ''): array
    {
        if (empty($filters)) $filters = $this->getFiltersFromURL();
        if (empty($sort) && isset($_GET['sort'])) $sort = $_GET['sort'];
        if (empty($search) && isset($_GET['search'])) $search = $_GET['search'];

        $normalizedItems = $this->normalizeItems($this->getItems());
        $filteredItems = $this->applyFilters($normalizedItems, $filters, $search);
        return $this->applySorting($filteredItems, $sort);
    }

    private function loadData(string $folderPath): void
    {
        if (!is_dir($folderPath)) throw new RuntimeException("Data directory not found: $folderPath");
        $this->loadXmlData($folderPath);
        $this->loadJsonData($folderPath);

        error_log(sprintf(
            '[Delivery] Total items loaded: %d',
            count($this->items)
        ));
    }

    private function loadXmlData(string $folderPath): void
    {
        $this->initializeLoadStats('xml');

        $xmlFiles = glob($folderPath . '/*.xml');
        if (empty($xmlFiles)) {
            $this->logError("No XML files found in: $folderPath", 'xml');
            return;
        }

        foreach ($xmlFiles as $xmlFile) {
            $this->loadStats['xml']['files_processed']++;

            try {
                $xml = $this->loadXmlFile($xmlFile);
                $itemsAdded = $this->processXmlDeliveries($xml, $xmlFile);
                $this->loadStats['xml']['items_loaded'] += $itemsAdded;

                error_log(sprintf(
                    '[XML] Processed %s, added %d items',
                    $xmlFile,
                    $itemsAdded
                ));
            } catch (RuntimeException $e) {
                $this->logError(
                    sprintf('Failed to process XML file %s: %s', $xmlFile, $e->getMessage()),
                    'xml'
                );
            }
        }
    }

    private function loadJsonData(string $folderPath): void
    {
        $this->initializeLoadStats('json');

        $jsonFiles = glob($folderPath . '/*.json');
        if (empty($jsonFiles)) {
            $this->logError("No JSON files found in: $folderPath", 'json');
            return;
        }

        foreach ($jsonFiles as $jsonFile) {
            $this->loadStats['json']['files_processed']++;

            try {
                $data = $this->loadJsonFile($jsonFile);
                $itemsAdded = $this->processJsonItems($data, $jsonFile);
                $this->loadStats['json']['items_loaded'] += $itemsAdded;

                error_log(sprintf(
                    '[JSON] Processed %s, added %d items',
                    $jsonFile,
                    $itemsAdded
                ));
            } catch (RuntimeException $e) {
                $this->logError(
                    sprintf('Failed to process JSON file %s: %s', $jsonFile, $e->getMessage()),
                    'json'
                );
            }
        }
    }

    private function initializeLoadStats(string $type): void
    {
        $this->loadStats[$type] = [
            'files_processed' => 0,
            'items_loaded' => 0,
            'errors' => []
        ];
    }

    private function loadXmlFile(string $filePath): SimpleXMLElement
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($filePath);

        if ($xml === false) {
            $errors = array_map(fn($e) => $e->message, libxml_get_errors());
            libxml_clear_errors();
            throw new RuntimeException(sprintf(
                'XML parse error in %s: %s',
                $filePath,
                json_encode($errors)
            ));
        }

        return $xml;
    }

    private function processXmlDeliveries(SimpleXMLElement $xml, string $sourceFile = ''): int
    {
        if (!isset($xml->bezorging)) throw new RuntimeException("No <bezorging> elements found in XML");
        $itemsBefore = count($this->items);

        foreach ($xml->bezorging as $index => $delivery) {
            try {
                $itemData = $this->convertXmlDeliveryToObject($delivery);
                $this->items[] = $this->parseItem($itemData);
            } catch (InvalidArgumentException $e) {
                $this->logError(sprintf(
                    'Skipping invalid XML delivery #%d from %s: %s',
                    $index,
                    $sourceFile,
                    $e->getMessage()
                ), 'xml');
            }
        }

        return count($this->items) - $itemsBefore;
    }

    private function convertXmlDeliveryToObject(SimpleXMLElement $delivery): stdClass
    {
        $item = new stdClass();

        $item->id = (string)($delivery->id ?? '0');
        $item->gewicht = max(0, (float)($delivery->gewicht ?? 0.0));
        $item->formaat = (string)($delivery->formaat ?? 'Onbekend');
        $item->afmetingen = (string)($delivery->afmetingen ?? '0x0x0');

        $item->afzender = $delivery->afzender ?? null;
        $item->ontvanger = $delivery->ontvanger ?? null;

        $optionalFields = [
            'binnenland' => 'bool',
            'bezorgdag' => 'string',
            'bezorgtijd' => 'string',
            'dagen_onderweg' => 'int',
            'prioriteit' => 'string',
            'fragile' => 'bool',
            'track_trace' => 'string',
            'track_trace_status' => 'string',
            'chauffeur' => 'string',
            'bezorgbus_id' => 'int',
            'aanmeld_datum' => 'string',
            'verzend_datum' => 'string',
            'ontvangst_datum' => 'string',
            'thuis' => 'string',
            'vermist_datum' => 'string',
            'aantal_dagen_vermist' => 'int'
        ];

        foreach ($optionalFields as $field => $type) {
            $item->{$field} = $this->convertFieldValue($delivery->{$field} ?? null, $type);
        }

        return $item;
    }

    private function convertFieldValue($value, string $type): float|bool|int|string
    {
        if ($value === null) return $this->getDefaultValueForType($type);
        $value = (string)$value;

        return match ($type) {
            'bool' => strtolower($value) === 'true',
            'int' => (int)$value,
            'float' => (float)$value,
            default => $value,
        };
    }

    private function getDefaultValueForType(string $type): false|int|string
    {
        return match ($type) {
            'bool' => false,
            'int', 'float' => 0,
            default => '',
        };
    }

    private function loadJsonFile(string $filePath)
    {
        $content = file_get_contents($filePath);
        if ($content === false) throw new RuntimeException("Failed to read file");

        $data = json_decode($content);
        if (json_last_error() !== JSON_ERROR_NONE) throw new RuntimeException("JSON decode error: " . json_last_error_msg());

        return $data;
    }

    private function processJsonItems($data, string $sourceFile = ''): int
    {
        $itemsBefore = count($this->items);

        if (is_array($data)) {
            foreach ($data as $index => $item) {
                $this->processSingleJsonItem($item, $index, $sourceFile);
            }
        } else if (is_object($data)) $this->processSingleJsonItem($data, 0, $sourceFile);

        return count($this->items) - $itemsBefore;
    }

    private function processSingleJsonItem($item, int $index, string $sourceFile): void
    {
        try {
            $this->items[] = $this->parseItem($item);
        } catch (InvalidArgumentException $e) {
            $this->logError(sprintf(
                'Skipping invalid JSON item #%d from %s: %s',
                $index,
                $sourceFile,
                $e->getMessage()
            ), 'json');
        }
    }

    private function parseItem(object $item): stdClass
    {
        $parsed = new stdClass();
        $parsed->uuid = $this->generateUuid();

        $requiredFields = [
            'id' => ['property' => 'id', 'default' => 0, 'type' => 'int'],
            'gewicht' => ['property' => 'weight', 'default' => 0.0, 'type' => 'float'],
            'formaat' => ['property' => 'format', 'default' => 'Onbekend', 'type' => 'string'],
            'afmetingen' => ['property' => 'dimensions', 'default' => '0x0x0', 'type' => 'string']
        ];

        foreach ($requiredFields as $field => $config) {
            $parsed->{$config['property']} = $this->convertFieldValue(
                $item->{$field} ?? $config['default'],
                $config['type']
            );
        }

        $parsed->sender = $this->parseAddressField($item->afzender ?? null);
        $parsed->receiver = $this->parseAddressField($item->ontvanger ?? null);

        $optionalFields = [
            'binnenland' => ['property' => 'domestic', 'default' => false, 'type' => 'bool'],
            'bezorgdag' => ['property' => 'delivery_day', 'default' => '', 'type' => 'string'],
            'bezorgtijd' => ['property' => 'delivery_time', 'default' => '', 'type' => 'string'],
            'dagen_onderweg' => ['property' => 'days_in_transit', 'default' => 0, 'type' => 'int'],
            'prioriteit' => ['property' => 'priority', 'default' => 'low', 'type' => 'string'],
            'fragile' => ['property' => 'fragile', 'default' => false, 'type' => 'bool'],
            'track_trace' => ['property' => 'tracking_code', 'default' => '', 'type' => 'string'],
            'track_trace_status' => ['property' => 'tracking_status', 'default' => 'unknown', 'type' => 'string'],
            'chauffeur' => ['property' => 'driver', 'default' => '', 'type' => 'string'],
            'bezorgbus_id' => ['property' => 'delivery_van_id', 'default' => 0, 'type' => 'int'],
            'aanmeld_datum' => ['property' => 'registration_date', 'default' => '', 'type' => 'string'],
            'verzend_datum' => ['property' => 'shipping_date', 'default' => '', 'type' => 'string'],
            'ontvangst_datum' => ['property' => 'receipt_date', 'default' => '', 'type' => 'string'],
            'thuis' => ['property' => 'home', 'default' => '', 'type' => 'string'],
            'vermist_datum' => ['property' => 'missing_date', 'default' => '', 'type' => 'string'],
            'aantal_dagen_vermist' => ['property' => 'days_missing', 'default' => 0, 'type' => 'int']
        ];

        foreach ($optionalFields as $field => $config) {
            $parsed->{$config['property']} = $this->convertFieldValue(
                $item->{$field} ?? $config['default'],
                $config['type']
            );
        }

        return $parsed;
    }

    private function parseAddressField($address): stdClass
    {
        if ($address instanceof SimpleXMLElement) return $this->parseXmlAddress($address);
        if (is_object($address)) return $this->parseJsonAddress($address);

        return $this->createEmptyAddress();
    }

    private function parseXmlAddress(SimpleXMLElement $address): stdClass
    {
        $parsed = new stdClass();

        $parsed->country = (string)($address->land ?? '');
        $parsed->province = (string)($address->provincie ?? '');
        $parsed->city = (string)($address->stad ?? '');
        $parsed->street = (string)($address->straat ?? '');
        $parsed->house_number = (string)($address->huisnummer ?? '');
        $parsed->postal_code = (string)($address->postcode ?? '');

        return $parsed;
    }

    private function parseJsonAddress(stdClass $address): stdClass
    {
        $parsed = new stdClass();

        $parsed->country = $address->land ?? '';
        $parsed->province = $address->provincie ?? '';
        $parsed->city = $address->stad ?? '';
        $parsed->street = $address->straat ?? '';
        $parsed->house_number = $address->huisnummer ?? '';
        $parsed->postal_code = $address->postcode ?? '';

        return $parsed;
    }

    private function createEmptyAddress(): stdClass
    {
        return (object)[
            'country' => '',
            'province' => '',
            'city' => '',
            'street' => '',
            'house_number' => '',
            'postal_code' => ''
        ];
    }

    private function generateUuid(): string
    {
        try {
            $data = random_bytes(16);
        } catch (Exception $e) {
            throw new RuntimeException("Failed to generate UUID: " . $e->getMessage());
        }

        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    private function logError(string $message, string $type = 'general'): void
    {
        $this->loadStats[$type]['errors'][] = $message;
        error_log("[Delivery Error] $message");
    }

    private function getFiltersFromURL(): array
    {
        $filters = [];
        $possibleFilters = ['destination', 'delivery_time', 'delivery_day', 'priority', 'fragile'];

        foreach ($possibleFilters as $filter) {
            if (isset($_GET[$filter]) && $_GET[$filter] !== '') {
                $filters[$filter] = $_GET[$filter];
                error_log("Filter found: $filter = " . $_GET[$filter]);
            }
        }

        error_log('Total filters found: ' . count($filters));
        return $filters;
    }

    private function normalizeItems(array $items): array
    {
        return array_map(function ($item) {
            if (is_array($item)) {
                $obj = new stdClass();
                foreach ($item as $key => $value) {
                    $obj->{$key} = $value;
                }
                return $obj;
            }
            return $item;
        }, $items);
    }

    private function applyFilters(array $items, array $filters, string $search = ''): array
    {
        $filteredItems = $items;

        if (!empty($filters)) {
            $filteredItems = array_filter($filteredItems, function ($item) use ($filters) {
                foreach ($filters as $key => $value) {
                    if (!$this->itemMatchesFilter($item, $key, $value)) {
                        return false;
                    }
                }
                return true;
            });
        }

        if (!empty($search)) {
            $searchTerm = strtolower(trim($search));
            $filteredItems = array_filter($filteredItems, function ($item) use ($searchTerm) {
                $searchFields = [
                    strtolower($item->format ?? ''),
                    strtolower($item->dimensions ?? ''),
                    strtolower($item->tracking_code ?? ''),
                    strtolower($item->tracking_status ?? ''),
                    strtolower($item->driver ?? '')
                ];

                foreach ($searchFields as $field) {
                    if (str_contains($field, $searchTerm)) {
                        return true;
                    }
                }
                return false;
            });
        }

        return array_values($filteredItems);
    }

    private function itemMatchesFilter($item, string $key, $value): bool
    {
        if ($value === '' || $value === null) {
            return true;
        }

        switch ($key) {
            case 'destination':
                $isDomestic = $item->domestic ?? false;
                if ($value === 'domestic') {
                    return $isDomestic === true;
                }
                if ($value === 'international') {
                    return $isDomestic === false;
                }
                break;

            case 'delivery_day':
                $itemDay = strtolower($item->delivery_day ?? '');
                $filterDay = strtolower($value);
                return $itemDay === $filterDay;

            case 'delivery_time':
                $itemTime = strtolower($item->delivery_time ?? '');
                $filterTime = strtolower($value);
                if ($filterTime === 'day') {
                    return $itemTime === 'overdag';
                }
                if ($filterTime === 'evening') {
                    return $itemTime === 'avond';
                }
                break;

            case 'priority':
                $itemPriority = strtolower($item->priority ?? '');
                $filterPriority = strtolower($value);
                return $itemPriority === $filterPriority;

            case 'fragile':
                $filterFragile = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                return ($item->fragile ?? false) === $filterFragile;

            default:
                return true;
        }

        return true;
    }

    private function applySorting(array $items, string $sort): array
    {
        if (empty($sort)) {
            return array_values($items);
        }

        $sortParts = explode('-', $sort);
        $field = $sortParts[0];
        $direction = $sortParts[1] ?? 'asc';

        usort($items, function ($a, $b) use ($field, $direction) {
            $valA = $this->getSortValue($a, $field);
            $valB = $this->getSortValue($b, $field);

            $result = $valA <=> $valB;
            return $direction === 'asc' ? $result : -$result;
        });

        return array_values($items);
    }

    private function getSortValue($item, string $field): float|false|int|string
    {
        switch ($field) {
            case 'size':
                if (isset($item->dimensions)) {
                    $dims = explode('x', $item->dimensions);
                    return array_sum(array_map('intval', $dims));
                }
                return 0;

            case 'weight':
                return $item->weight ?? 0;

            case 'days':
                return $item->days_in_transit ?? 0;

            case 'priority':
                $priorityOrder = [
                    'high' => 3,
                    'medium' => 2,
                    'low' => 1,
                    '' => 0
                ];
                $priority = strtolower($item->priority ?? '');
                return $priorityOrder[$priority] ?? 0;

            case 'date':
                if (isset($item->shipping_date)) {
                    return strtotime($item->shipping_date);
                }
                return 0;

            default:
                return $item->{$field} ?? '';
        }
    }

    public function applyAmountFilter(array $items): array
    {
        if (isset($_GET['amount']) && is_numeric($_GET['amount'])) {
            return array_slice($items, 0, (int)$_GET['amount']);
        }

        return $items;
    }
}
