<?php

namespace App\Livewire\Components\Step;

use Livewire\Component;

class Registration extends Component
{
    public $nodes;
    public array $answered_nodes;
    public array $old_answered_nodes;
    public string $date_of_birth = '1960-01-01';

    public function mount($nodes)
    {
        $this->nodes = $nodes;
    }

    public function updatingDateOfBirth($value)
    {
        $this->dispatch('dobUpdated', $value, $this->date_of_birth);
    }

    public function updatingAnsweredNodes($value, $node_id)
    {
        $this->dispatch('nodeToSave', $node_id, $value, $this->answered_nodes[$node_id] ?? null, $this->old_answered_nodes[$node_id] ?? null);
        $this->old_answered_nodes[$node_id] = $value;
    }

    public function render()
    {
        return view('livewire.components.step.registration');
    }
}
