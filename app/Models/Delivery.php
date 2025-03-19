<?php

namespace App\Models;

use Facades\URL;
use Nette\Utils\ArrayList;
use SimpleXMLElement;

class Delivery
{
    public ArrayList $items;

    public function __construct()
    {
        $this->items = new ArrayList();
        $this->loadData();
    }

    private function loadData(): void
    {
        $this->loadXmlData(URL::to('/data/feddex_data.xml'));

        $this->loadJsonData(URL::to('/data/feddex_data.json'));
    }

    private function loadXmlData(string $folderPath): void
    {
        $xmlFiles = glob($folderPath . '/*.xml');

        foreach ($xmlFiles as $xmlFile) {
            $xml = simplexml_load_file($xmlFile);
            if ($xml) {
                $this->items[] = $this->parseXmlItem($xml);
            }
        }
    }

    private function parseXmlItem(SimpleXMLElement $xml): array
    {
        return [
            'id' => (int)$xml->id,
            'weight' => (float)$xml->gewicht,
            'format' => (string)$xml->formaat,
            'dimensions' => (string)$xml->afmetingen,
            'sender' => [
                'country' => (string)$xml->afzender->land,
                'province' => (string)$xml->afzender->provincie,
                'city' => (string)$xml->afzender->stad,
                'street' => (string)$xml->afzender->straat,
                'house_number' => (string)$xml->afzender->huisnummer,
                'postal_code' => (string)$xml->afzender->postcode,
            ],
            'receiver' => [
                'country' => (string)$xml->ontvanger->land,
                'province' => (string)$xml->ontvanger->provincie,
                'city' => (string)$xml->ontvanger->stad,
                'street' => (string)$xml->ontvanger->straat,
                'house_number' => (string)$xml->ontvanger->huisnummer,
                'postal_code' => (string)$xml->ontvanger->postcode,
            ],
            'domestic' => (bool)$xml->binnenland,
            'delivery_day' => (string)$xml->bezorgdag,
            'delivery_time' => (string)$xml->bezorgtijd,
            'days_in_transit' => (int)$xml->dagen_onderweg,
            'priority' => (string)$xml->prioriteit,
            'fragile' => (bool)$xml->fragile,
            'track_trace' => (string)$xml->track_trace,
            'track_trace_status' => (string)$xml->track_trace_status,
            'driver' => (string)$xml->chauffeur,
            'delivery_van_id' => (int)$xml->bezorgbus_id,
            'registration_date' => (string)$xml->aanmeld_datum,
            'shipping_date' => (string)$xml->verzend_datum,
            'receipt_date' => (string)$xml->ontvangst_datum,
            'home' => (bool)$xml->thuis,
            'missing_date' => (string)$xml->vermist_datum,
            'days_missing' => (int)$xml->aantal_dagen_vermist,
        ];
    }

    private function loadJsonData(string $folderPath): void
    {
        $jsonFiles = glob($folderPath . '/*.json');

        foreach ($jsonFiles as $jsonFile) {
            $jsonData = file_get_contents($jsonFile);
            $data = json_decode($jsonData, true);
            if ($data) $this->items[] = $data;
        }
    }
}
