<?php

namespace App\Livewire;

use Illuminate\View\View;
use Livewire\Component;

class Delivery extends Component
{
    public $items;

    public function __construct()
    {
        $delivery = new \App\Models\Delivery();
        $this->items = $delivery->getItems();
    }

    public function render() : View
    {
        return view('livewire.delivery', [
            'item' => $this->items
        ]);
    }
}
