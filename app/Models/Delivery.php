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

        $item->id = isset($delivery->id) ? (string)$delivery->id : '0';
        $item->gewicht = isset($delivery->gewicht) ? max(0, (float)$delivery->gewicht) : 0.0;
        $item->formaat = isset($delivery->formaat) ? (string)$delivery->formaat : 'Onbekend';
        $item->afmetingen = isset($delivery->afmetingen) ? (string)$delivery->afmetingen : '0x0x0';

        $item->afzender = isset($delivery->afzender) ? $delivery->afzender : null;
        $item->ontvanger = isset($delivery->ontvanger) ? $delivery->ontvanger : null;

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
            if (isset($delivery->{$field})) {
                $value = (string)$delivery->{$field};

                if ($type === 'bool') {
                    $item->{$field} = strtolower($value) === 'true';
                }
                else if ($type === 'int') {
                    $item->{$field} = (int)$value;
                }
                else {
                    $item->{$field} = $value;
                }
            } else {
                if ($type === 'bool') {
                    $item->{$field} = false;
                } else if ($type === 'int') {
                    $item->{$field} = 0;
                } else {
                    $item->{$field} = '';
                }
            }
        }

        return $item;
    }

    private function createEmptyAddress(): stdClass
    {
        $empty = new stdClass();
        $empty->country = '';
        $empty->province = '';
        $empty->city = '';
        $empty->street = '';
        $empty->house_number = '';
        $empty->postal_code = '';
        return $empty;
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
        } else if (is_object($data)) {
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
        $parsed = new stdClass();
        $parsed->uuid = $this->generateUuid();

        $requiredFields = [
            'id' => ['property' => 'id', 'default' => 0, 'type' => 'int'],
            'gewicht' => ['property' => 'weight', 'default' => 0.0, 'type' => 'float'],
            'formaat' => ['property' => 'format', 'default' => 'Onbekend', 'type' => 'string'],
            'afmetingen' => ['property' => 'dimensions', 'default' => '0x0x0', 'type' => 'string']
        ];

        foreach ($requiredFields as $field => $config) {
            $value = $item->{$field} ?? $config['default'];
            settype($value, $config['type']);
            $parsed->{$config['property']} = $value;
        }

        if (isset($item->afzender)) {
            $parsed->sender = ($item->afzender instanceof SimpleXMLElement)
                ? $this->parseAddress($item->afzender)
                : (is_object($item->afzender) ? $this->parseJsonAddress($item->afzender) : $this->createEmptyAddress());
        }

        if (isset($item->ontvanger)) {
            $parsed->receiver = ($item->ontvanger instanceof SimpleXMLElement)
                ? $this->parseAddress($item->ontvanger)
                : (is_object($item->ontvanger) ? $this->parseJsonAddress($item->ontvanger) : $this->createEmptyAddress());
        }

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
            $value = $item->{$field} ?? $config['default'];
            settype($value, $config['type']);
            $parsed->{$config['property']} = $value;
        }

        return $parsed;
    }

    private function parseAddress($address): stdClass
    {
        $parsed = new stdClass();

        if (!$address) {
            return $this->createEmptyAddress();
        }

        $parsed->country = isset($address->land) ? (string)$address->land : '';
        $parsed->province = isset($address->provincie) ? (string)$address->provincie : '';
        $parsed->city = isset($address->stad) ? (string)$address->stad : '';
        $parsed->street = isset($address->straat) ? (string)$address->straat : '';
        $parsed->house_number = isset($address->huisnummer) ? (string)$address->huisnummer : '';
        $parsed->postal_code = isset($address->postcode) ? (string)$address->postcode : '';

        return $parsed;
    }

    private function parseJsonAddress($address): stdClass
    {
        if (!$address || !is_object($address)) {
            return $this->createEmptyAddress();
        }

        $parsed = new stdClass();

        $parsed->country = $address->land ?? '';
        $parsed->province = $address->provincie ?? '';
        $parsed->city = $address->stad ?? '';
        $parsed->street = $address->straat ?? '';
        $parsed->house_number = $address->huisnummer ?? '';
        $parsed->postal_code = $address->postcode ?? '';

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
                    } else if ($key === 'delivery_day') {
                        if (!isset($item->delivery_day) || strtolower($item->delivery_day) !== strtolower($value)) {
                            return false;
                        }
                    } else if ($key === 'delivery_time') {
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

    private function getSortValue($item, string $field): int|string
    {
        return match ($field) {
            'size' => $item->format ?? '',
            'weight' => $item->weight ?? 0,
            'days' => $item->days_in_transit ?? 0,
            default => 0,
        };
    }
}
