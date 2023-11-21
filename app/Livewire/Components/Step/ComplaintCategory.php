<?php

namespace App\Livewire\Components\Step;

use Livewire\Component;

class ComplaintCategory extends Component
{
    public $nodes;
    public string $age_key;
    public array $chosen_complaint_categories;

    public function mount($nodes, $age_key)
    {
        $this->nodes = $nodes;
        $this->age_key = $age_key;
    }

    public function render()
    {
        return view('livewire.components.step.complaint-category');
    }
}
