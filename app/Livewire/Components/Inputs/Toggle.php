<?php

namespace App\Livewire\Components\Inputs;

use Livewire\Component;

class Toggle extends Component
{
    public $node;
    public $chosen = false;

    public function mount($node)
    {
        $this->node = $node;
    }

    public function render()
    {
        return view('livewire.components.inputs.toggle');
    }
}
