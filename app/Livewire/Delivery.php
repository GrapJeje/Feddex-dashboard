<?php

namespace App\Livewire;

use Illuminate\View\View;
use Livewire\Component;
use App\Models\Delivery as DeliveryModel;

class Delivery extends Component
{
    public $items;
    public $totalItems;
    public $currentAmount;
    public $defaultAmount = 5;
    public $showMore;
    public $showLess;

    public function mount(): void
    {
        $this->loadItems();
    }

    public function loadItems(): void
    {
        $delivery = new DeliveryModel();

        // Haal items op met filters uit URL
        $allItems = $delivery->getFilteredAndSortedItems();
        $totalBeforeAmount = count($allItems);

        // Pas amount filter toe
        $this->items = $delivery->applyAmountFilter($allItems);

        // Bepaal paginering status
        $this->totalItems = $totalBeforeAmount;
        $this->currentAmount = isset($_GET['amount']) ? (int)$_GET['amount'] : $this->defaultAmount;
        $this->showMore = $this->currentAmount < $this->totalItems;
        $this->showLess = $this->currentAmount > $this->defaultAmount;
    }

    public function render(): View
    {
        return view('livewire.delivery', [
            'items' => $this->items,
            'totalItems' => $this->totalItems,
            'currentAmount' => $this->currentAmount,
            'defaultAmount' => $this->defaultAmount,
            'showMore' => $this->showMore,
            'showLess' => $this->showLess
        ]);
    }
}
