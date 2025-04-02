<?php

namespace App\Models;

use SimpleXMLElement;
use stdClass;
use RuntimeException;

class Delivery
{
    private array $items = [];

    public function __construct()
    {
        $this->loadData();
    }

    private function loadData(): void
    {
        $dataPath = public_path('data');
        $this->loadXmlData($dataPath);
        $this->loadJsonData($dataPath);
    }

    private function loadXmlData(string $folderPath): void
    {
        $xmlFiles = glob($folderPath . '/*.xml');

        if ($xmlFiles === false) throw new RuntimeException("Failed to glob XML files in directory: $folderPath");

        foreach ($xmlFiles as $xmlFile) {
            $xml = simplexml_load_file($xmlFile);
            if ($xml === false) throw new RuntimeException("Failed to load XML file: $xmlFile");

            logger($xml);
            $this->parseXmlData($xml);
        }
    }

    private function parseXmlData(SimpleXMLElement $xml): void
    {
        logger($xml);
        foreach ($xml->item as $xmlItem) {
            // Zet het XML-item om naar een array
            $xmlItemArray = json_decode(json_encode($xmlItem), true);

            // Log de volledige XML-item inhoud als een mooi geformatteerde JSON-string
            logger("Parsing XML item: " . json_encode($xmlItemArray, JSON_PRETTY_PRINT));

            // Zet de XML om naar stdClass voor verdere verwerking
            $jsonItem = $this->convertXmlToStdClass($xmlItem);

            // Voeg het item toe aan de items-array
            $this->items[] = $this->parseJsonItem($jsonItem);
        }
    }

    private function convertXmlToStdClass(SimpleXMLElement $xml): stdClass
    {
        $json = json_encode($xml);
        $data = json_decode($json);

        if (json_last_error() !== JSON_ERROR_NONE) throw new RuntimeException("Failed to decode XML to stdClass");

        return $data;
    }

    private function loadJsonData(string $folderPath): void
    {
        $jsonFiles = glob($folderPath . '/*.json');

        if ($jsonFiles === false) throw new RuntimeException("Failed to glob JSON files in directory: $folderPath");

        foreach ($jsonFiles as $jsonFile) {
            $jsonData = file_get_contents($jsonFile);
            if ($jsonData === false) throw new RuntimeException("Failed to read JSON file: $jsonFile");

            $data = json_decode($jsonData);
            if (json_last_error() !== JSON_ERROR_NONE) throw new RuntimeException("Failed to decode JSON from file: $jsonFile");

            if (is_array($data)) {
                foreach ($data as $jsonItem) {
                    $this->items[] = $this->parseJsonItem($jsonItem);
                }
            } elseif (is_object($data)) {
                $this->items[] = $this->parseJsonItem($data);
            }
        }
    }

    private function parseJsonItem(stdClass $json): stdClass
    {
        $item = new stdClass();
        $item->uuid = $this->guidv4();
        $item->id = (int)$json->id;
        $item->weight = (float)$json->gewicht;
        $item->format = (string)$json->formaat;
        $item->dimensions = (string)$json->afmetingen;

        $item->sender = $this->parseJsonAddress($json->afzender);
        $item->receiver = $this->parseJsonAddress($json->ontvanger);

        $item->domestic = (bool)$json->binnenland;
        $item->delivery_day = (string)$json->bezorgdag;
        $item->delivery_time = (string)$json->bezorgtijd;
        $item->days_in_transit = (int)$json->dagen_onderweg;
        $item->priority = (string)$json->prioriteit;
        $item->fragile = (bool)$json->fragile;
        $item->track_trace = (string)$json->track_trace;
        $item->track_trace_status = (string)$json->track_trace_status;
        $item->driver = (string)$json->chauffeur;
        $item->delivery_van_id = (int)$json->bezorgbus_id;
        $item->registration_date = $json->aanmeld_datum ?? null;
        $item->shipping_date = $json->verzend_datum ?? null;
        $item->receipt_date = $json->ontvangst_datum ?? null;
        $item->home = $json->thuis ?? null;
        $item->missing_date = $json->vermist_datum ?? null;
        $item->days_missing = $json->aantal_dagen_vermist ?? null;

        return $item;
    }

    public function guidv4($data = null) {
        $data = $data ?? random_bytes(16);
        assert(strlen($data) == 16);

        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    private function parseJsonAddress(stdClass $address): stdClass
    {
        $parsedAddress = new stdClass();
        $parsedAddress->country = (string)$address->land;
        $parsedAddress->province = (string)$address->provincie;
        $parsedAddress->city = (string)$address->stad;
        $parsedAddress->street = (string)$address->straat;
        $parsedAddress->house_number = (string)$address->huisnummer;
        $parsedAddress->postal_code = (string)$address->postcode;

        return $parsedAddress;
    }

    public function getItems(): array
    {
        return $this->items;
    }
}
