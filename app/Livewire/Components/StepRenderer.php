<?php

namespace App\Livewire\Components;

use Livewire\Attributes\On;
use Livewire\Component;

class StepRenderer extends Component
{
    // public $steps;
    public $nodes;
    public $chosen = [];

    public function mount($nodes)
    {
        $this->nodes = $nodes;
        // $this->steps = $steps;
        // dd($nodes);
    }

    #[On('nodeUpdated')]
    public function needToUpdate($node_id, $value)
    {
        $this->dispatch('stepUpdated', $node_id, $value);
    }

    public function render()
    {
        return view('livewire.components.step-renderer');
    }
}
