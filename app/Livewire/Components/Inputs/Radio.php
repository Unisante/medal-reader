<?php

namespace App\Livewire\Components\Inputs;

use Livewire\Component;

class Radio extends Component
{
    public $node_id;
    public $label;
    public $description;
    public $answers = [];
    public $answer;

    public function mount($node)
    {
        $this->node_id = $node['id'];
        $this->label = $node['label'];
        $this->description = $node['description'];
        $this->answers = $node['answers'];
    }

    public function updatingAnswer($value)
    {
        $this->dispatch('nodeUpdated', $value, $this->answer);
        $this->answer = $value;
    }

    public function render()
    {
        return view('livewire.components.inputs.radio');
    }
}
