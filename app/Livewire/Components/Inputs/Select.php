<?php

namespace App\Livewire\Components\Inputs;

use Livewire\Component;

class Select extends Component
{
    public $node_id;
    public $label;
    public $description;
    public $answers = [];
    public $answer;
    public string $cache_key;

    public function mount($node_id, $cache_key)
    {
        $this->node_id = $node_id;
        $this->cache_key = $cache_key;
    }

    public function updatingAnswer($value)
    {
        $this->dispatch('nodeUpdated', $value, $this->answer);
    }

    public function render()
    {
        return view('livewire.components.inputs.select');
    }
}
