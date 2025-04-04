<?php

namespace App\Livewire;

use Illuminate\View\View;
use Livewire\Component;
use App\Models\Delivery as DeliveryModel;
use stdClass;

class Delivery extends Component
{
    public $items;
    public $amount;
    public $destination;
    public $delivery_time;
    public $sort;
    public $search;

    protected $queryString = [
        'amount' => ['except' => ''],
        'destination' => ['except' => ''],
        'delivery_time' => ['except' => ''],
        'sort' => ['except' => ''],
        'search' => ['except' => ''],
    ];

    public function mount(): void
    {
        $this->loadItems();
    }

    public function loadItems(): void
    {
        $delivery = new DeliveryModel();
        $items = $delivery->getItems();

        $this->items = $this->applyFilters($items);
    }

    protected function applyFilters($items)
    {
        // Zorg dat we altijd een collectie van objecten hebben
        $itemsCollection = collect($items)->map(function ($item) {
            if (is_array($item)) {
                $obj = new stdClass();
                foreach ($item as $key => $value) {
                    $obj->{$key} = $value;
                }
                return $obj;
            }
            return $item;
        });

        // Apply filters
        $filteredItems = $itemsCollection;

        // Amount filter
        if ($this->amount) {
            $filteredItems = $filteredItems->take((int)$this->amount);
        }

        // Destination filter
        if ($this->destination === 'domestic') {
            $filteredItems = $filteredItems->filter(function ($item) {
                return isset($item->domestic) && $item->domestic === true;
            });
        } elseif ($this->destination === 'international') {
            $filteredItems = $filteredItems->filter(function ($item) {
                return isset($item->domestic) && $item->domestic === false;
            });
        }

        // Delivery time filter
        if ($this->delivery_time) {
            $filteredItems = $filteredItems->filter(function ($item) {
                return isset($item->delivery_time) &&
                    strtolower($item->delivery_time) === strtolower($this->delivery_time);
            });
        }

        // Priority filter
        $filteredItems = $filteredItems->filter(function ($item) {
            if (!isset($item->priority)) {
                return true;
            }
            // Voeg hier je priority filter logica toe
            return true;
        });

        // Search filter
        if ($this->search) {
            $searchTerm = strtolower($this->search);
            $filteredItems = $filteredItems->filter(function ($item) use ($searchTerm) {
                $format = isset($item->format) ? strtolower($item->format) : '';
                $dimensions = isset($item->dimensions) ? strtolower($item->dimensions) : '';
                $trackingCode = isset($item->tracking_code) ? strtolower($item->tracking_code) : '';

                return strpos($format, $searchTerm) !== false ||
                    strpos($dimensions, $searchTerm) !== false ||
                    strpos($trackingCode, $searchTerm) !== false;
            });
        }

        // Sort filter
        if ($this->sort) {
            $sortParts = explode('-', $this->sort);
            $sortField = $sortParts[0];
            $sortDirection = $sortParts[1] ?? 'asc';

            if ($sortField === 'size') {
                $filteredItems = $filteredItems->sortBy(function ($item) {
                    return isset($item->dimensions) ? array_sum(explode('x', $item->dimensions)) : 0;
                }, SORT_REGULAR, $sortDirection === 'desc');
            }
        }

        return $filteredItems->values()->all();
    }

    public function render(): View
    {
        return view('livewire.delivery', [
            'items' => $this->items
        ]);
    }
}
