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

    public function mount($node, $cache_key)
    {
        $this->node_id = $node['id'];
        $this->cache_key =$cache_key;
        // $this->label = $node['label'];
        // $this->description = $node['description'];
        // $this->answers = collect($node['answers'])
        //     ->filter(function ($answer) {
        //         return $answer['value'] !== 'not_available';
        //     })
        //     ->sortBy('reference');
        // dd($this->node['answers']);
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
