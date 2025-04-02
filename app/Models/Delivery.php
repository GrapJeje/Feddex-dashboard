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

    private function loadData(string $folderPath): void
    {
        if (!is_dir($folderPath)) {
            throw new RuntimeException("Data directory not found: $folderPath");
        }

        $this->loadXmlData($folderPath);
        $this->loadJsonData($folderPath);

        error_log("[Delivery] Total items loaded: " . count($this->items));
    }

    private function loadXmlData(string $folderPath): void
    {
        $this->loadStats['xml'] = [
            'files_processed' => 0,
            'items_loaded' => 0,
            'errors' => []
        ];

        $xmlFiles = glob($folderPath . '/*.xml');
        if (empty($xmlFiles)) {
            $this->logError("No XML files found in: $folderPath", 'xml');
            return;
        }

        foreach ($xmlFiles as $xmlFile) {
            $this->loadStats['xml']['files_processed']++;

            try {
                $xml = $this->loadXmlFile($xmlFile);
                $itemsBefore = count($this->items);
                $this->processXmlDeliveries($xml, $xmlFile);
                $itemsAdded = count($this->items) - $itemsBefore;
                $this->loadStats['xml']['items_loaded'] += $itemsAdded;

                error_log("[XML] Processed $xmlFile, added $itemsAdded items");
            } catch (RuntimeException $e) {
                $this->logError("Failed to process XML file $xmlFile: " . $e->getMessage(), 'xml');
            }
        }
    }

    private function loadXmlFile(string $filePath): SimpleXMLElement
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($filePath);

        if ($xml === false) {
            $errors = array_map(fn($e) => $e->message, libxml_get_errors());
            libxml_clear_errors();
            throw new RuntimeException(
                "XML parse error in $filePath: " . json_encode($errors)
            );
        }

        return $xml;
    }

    private function processXmlDeliveries(SimpleXMLElement $xml, string $sourceFile = ''): void
    {
        if (!isset($xml->bezorging)) {
            throw new RuntimeException("No <bezorging> elements found in XML");
        }

        foreach ($xml->bezorging as $index => $delivery) {
            try {
                $itemData = $this->convertXmlDeliveryToObject($delivery);
                $parsedItem = $this->parseItem($itemData);
                $this->items[] = $parsedItem;
            } catch (InvalidArgumentException $e) {
                $this->logError("Skipping invalid XML delivery #$index from $sourceFile: " . $e->getMessage(), 'xml');
            }
        }
    }

    private function convertXmlDeliveryToObject(SimpleXMLElement $delivery): stdClass
    {
        $item = new stdClass();

        // Vereiste velden met fallback waarden
        $item->id = isset($delivery->id) ? (string)$delivery->id : '0';
        $item->weight = isset($delivery->gewicht) ? max(0, (float)$delivery->gewicht) : 0.0;
        $item->format = isset($delivery->formaat) ? (string)$delivery->formaat : 'Onbekend';
        $item->dimensions = isset($delivery->afmetingen) ? (string)$delivery->afmetingen : '0x0x0';

        // Adresgegevens met validatie
        $item->sender = $this->extractAddress($delivery->afzender ?? new SimpleXMLElement('<empty/>'));
        $item->receiver = $this->extractAddress($delivery->ontvanger ?? new SimpleXMLElement('<empty/>'));

        $item->priority = 'low';
        $optionalFields = [
            'binnenland' => 'domestic',
            'bezorgdag' => 'delivery_day',
            'bezorgtijd' => 'delivery_time',
            'dagen_onderweg' => 'days_in_transit',
            'prioriteit' => 'priority',
            'fragile' => 'fragile',
            'track_trace' => 'tracking_code',
            'track_trace_status' => 'tracking_status',
            'chauffeur' => 'driver',
            'bezorgbus_id' => 'delivery_van_id',
            'aanmeld_datum' => 'registration_date',
            'verzend_datum' => 'shipping_date',
            'ontvangst_datum' => 'receipt_date',
            'thuis' => 'home',
            'vermist_datum' => 'missing_date',
            'aantal_dagen_vermist' => 'days_missing'
        ];

        foreach ($optionalFields as $xmlField => $objectField) {
            if (isset($delivery->{$xmlField})) {
                $value = (string)$delivery->{$xmlField};
                if ($xmlField === 'binnenland' || $xmlField === 'fragile') {
                    $value = strtolower($value) === 'true';
                }
                $item->{$objectField} = $value;
            }
        }

        return $item;
    }

    private function extractAddress(SimpleXMLElement $address): stdClass
    {
        $addr = new stdClass();
        $addr->country = (string)$address->land;
        $addr->province = (string)$address->provincie;
        $addr->city = (string)$address->stad;
        $addr->street = (string)$address->straat;
        $addr->house_number = (string)$address->huisnummer;
        $addr->postal_code = (string)$address->postcode;

        return $addr;
    }

    private function loadJsonData(string $folderPath): void
    {
        $this->loadStats['json'] = [
            'files_processed' => 0,
            'items_loaded' => 0,
            'errors' => []
        ];

        $jsonFiles = glob($folderPath . '/*.json');
        if (empty($jsonFiles)) {
            $this->logError("No JSON files found in: $folderPath", 'json');
            return;
        }

        foreach ($jsonFiles as $jsonFile) {
            $this->loadStats['json']['files_processed']++;

            try {
                $data = $this->loadJsonFile($jsonFile);
                $itemsBefore = count($this->items);
                $this->processJsonItems($data, $jsonFile);
                $itemsAdded = count($this->items) - $itemsBefore;
                $this->loadStats['json']['items_loaded'] += $itemsAdded;

                error_log("[JSON] Processed $jsonFile, added $itemsAdded items");
            } catch (RuntimeException $e) {
                $this->logError("Failed to process JSON file $jsonFile: " . $e->getMessage(), 'json');
            }
        }
    }

    private function loadJsonFile(string $filePath)
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new RuntimeException("Failed to read file");
        }

        $data = json_decode($content);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("JSON decode error: " . json_last_error_msg());
        }

        return $data;
    }

    private function processJsonItems($data, string $sourceFile = ''): void
    {
        if (is_array($data)) {
            foreach ($data as $index => $item) {
                try {
                    $parsedItem = $this->parseItem($item);
                    $this->items[] = $parsedItem;
                } catch (InvalidArgumentException $e) {
                    $this->logError("Skipping invalid JSON item #$index from $sourceFile: " . $e->getMessage(), 'json');
                }
            }
        } elseif (is_object($data)) {
            try {
                $parsedItem = $this->parseItem($data);
                $this->items[] = $parsedItem;
            } catch (InvalidArgumentException $e) {
                $this->logError("Skipping invalid JSON object from $sourceFile: " . $e->getMessage(), 'json');
            }
        }
    }

    private function parseItem(object $item): stdClass
    {
        $fieldMappings = [
            'id' => 'id',
            'gewicht' => 'weight',
            'formaat' => 'format',
            'afmetingen' => 'dimensions',
            'afzender' => 'sender',
            'ontvanger' => 'receiver',
            'binnenland' => 'domestic',
            'bezorgdag' => 'delivery_day',
            'bezorgtijd' => 'delivery_time',
            'dagen_onderweg' => 'days_in_transit',
            'prioriteit' => 'priority',
            'fragile' => 'fragile',
            'track_trace' => 'tracking_code',
            'track_trace_status' => 'tracking_status',
            'chauffeur' => 'driver',
            'bezorgbus_id' => 'delivery_van_id',
            'aanmeld_datum' => 'registration_date',
            'verzend_datum' => 'shipping_date',
            'ontvangst_datum' => 'receipt_date',
            'thuis' => 'home',
            'vermist_datum' => 'missing_date',
            'aantal_dagen_vermist' => 'days_missing'
        ];

        $parsed = new stdClass();
        $parsed->uuid = $this->generateUuid();

        $requiredFields = [
            'id' => ['default' => 0, 'type' => 'int'],
            'gewicht' => ['default' => 0.0, 'type' => 'float'],
            'formaat' => ['default' => 'Onbekend', 'type' => 'string'],
            'afmetingen' => ['default' => '0x0x0', 'type' => 'string']
        ];

        foreach ($requiredFields as $field => $config) {
            $englishField = $fieldMappings[$field] ?? $field;
            $value = $item->{$field} ?? $config['default'];
            settype($value, $config['type']);
            $parsed->{$englishField} = $value;
        }

        $parsed->sender = $this->parseAddress($item->afzender ?? null);
        $parsed->receiver = $this->parseAddress($item->ontvanger ?? null);

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
            if (isset($item->{$field})) {
                $englishField = $fieldMappings[$field] ?? $field;
                $value = $item->{$field};
                settype($value, $type);
                $parsed->{$englishField} = $value;
            }
        }

        return $parsed;
    }

    private function parseAddress(?object $address): stdClass
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

    public function getFilteredAndSortedItems(array $filters = [], string $sort = ''): array
    {
        $filteredItems = $this->items;

        if (!empty($filters)) {
            $filteredItems = array_filter($filteredItems, function ($item) use ($filters) {
                foreach ($filters as $key => $value) {
                    if ($key === 'destination') {
                        if ($value === 'domestic' && (!isset($item->domestic) || !$item->domestic)) {
                            return false;
                        }
                        if ($value === 'international' && (!isset($item->domestic) || $item->domestic)) {
                            return false;
                        }
                    } elseif ($key === 'delivery_day') {
                        if (!isset($item->delivery_day) || strtolower($item->delivery_day) !== strtolower($value)) {
                            return false;
                        }
                    } elseif ($key === 'delivery_time') {
                        if (!isset($item->delivery_time) || strtolower($item->delivery_time) !== strtolower($value)) {
                            return false;
                        }
                    }
                }
                return true;
            });
        }

        if (!empty($sort)) {
            usort($filteredItems, function ($a, $b) use ($sort) {
                [$field, $direction] = explode('-', $sort);

                $valA = $this->getSortValue($a, $field);
                $valB = $this->getSortValue($b, $field);

                if ($direction === 'asc') {
                    return $valA <=> $valB;
                } else {
                    return $valB <=> $valA;
                }
            });
        }

        return array_values($filteredItems);
    }

    private function getSortValue($item, string $field)
    {
        switch ($field) {
            case 'size':
                return $item->format ?? '';
            case 'weight':
                return $item->weight ?? 0;
            case 'days':
                return $item->days_in_transit ?? 0;
            default:
                return 0;
        }
    }
}
