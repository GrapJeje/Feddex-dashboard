<?php

namespace App\Livewire;

use Illuminate\View\View;
use Livewire\Component;
use App\Models\Delivery as DeliveryModel;

class Delivery extends Component
{
    public $items;

    public function mount(): void
    {
        $this->loadItems();
    }

    public function loadItems(): void
    {
        $delivery = new DeliveryModel();
        $this->items = $delivery->getItems();
    }

    public function render(): View
    {
        return view('livewire.delivery', [
            'items' => $this->items
        ]);
    }
}
