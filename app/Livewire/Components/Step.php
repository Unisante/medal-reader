<?php

namespace App\Livewire\Components;

use Livewire\Component;

class Step extends Component
{
    public $node;

    public function mount($node)
    {
        $this->node = $node;
    }

    public function render()
    {
        return view('livewire.components.step');
    }
}
