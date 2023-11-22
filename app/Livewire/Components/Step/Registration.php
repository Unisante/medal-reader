<?php

namespace App\Livewire\Components\Step;

use Illuminate\Support\Facades\Cache;
use Livewire\Component;

class Registration extends Component
{
    public $nodes;
    public $villages;
    public array $answered_nodes;
    public array $old_answered_nodes;
    public string $date_of_birth = '1960-01-01';

    public function mount($nodes, $cache_key)
    {
        $this->nodes = $nodes;
        $this->villages = Cache::get($cache_key)['villages'];
    }

    public function updatingDateOfBirth($value)
    {
        $this->dispatch('dobUpdated', $value, $this->date_of_birth);
    }

    public function render()
    {
        return view('livewire.components.step.registration');
    }
}
