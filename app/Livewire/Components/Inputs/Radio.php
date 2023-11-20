<?php

namespace App\Livewire\Components\Inputs;

use Livewire\Component;

class Radio extends Component
{
    public $node_id;
    public $label;
    public $answers = [];
    public $answer;

    public function mount($node)
    {
        $this->node_id = $node['id'];
        $this->label = $node['label'];
        $this->answers = $node['answers'];
    }

    public function updatingAnswer($value)
    {
        // dd($this->answer);
        $this->dispatch('nodeUpdated', $value, $this->answer);
    }

    public function render()
    {
        return view('livewire.components.inputs.radio');
    }
}
