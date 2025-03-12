<?php

namespace App\Livewire\Pages;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.default')]
class Home extends Component
{
    public function render()
    {
        return view('livewire.pages.home');
    }
}
