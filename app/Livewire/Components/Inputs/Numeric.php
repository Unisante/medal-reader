<?php

namespace App\Livewire\Components\Inputs;

use Livewire\Attributes\Rule;
use Livewire\Component;

class Numeric extends Component
{
    public $node_id;
    public $label;
    public $description;
    public $answers = [];
    public $answer;

    #[Rule('required|numeric')]
    public $value;

    public function mount($node)
    {
        $this->node_id = $node['id'];
        $this->label = $node['label'];
        $this->description = $node['description'];
        $this->answers = $node['answers'];
    }

    public function updatingValue($value)
    {
        foreach ($this->answers as $answer) {
            $result = intval($value);
            $answer_value = $answer['value'];
            $answer_values = explode(',', $answer_value);
            $minValue = intval($answer_values[0]);
            $maxValue = intval($answer_values[1] ?? $minValue);

            $answer_id = match ($answer['operator']) {
                'more_or_equal' => $result >= $minValue ? $answer['id'] : null,
                'less' => $result < $minValue ? $answer['id'] : null,
                'between' => ($result >= $minValue && $result < $maxValue) ? $answer['id'] : null,
                default => null,
            };
            if ($answer_id && $answer_id !== $this->answer) {
                $this->dispatch('nodeToSave', $this->node_id, $value, $answer_id, $this->answer);
                $this->answer = $answer_id;
                return;
            }
        }
        $this->dispatch('nodeToSave', $this->node_id, $value, null, null);
    }
    public function render()
    {
        return view('livewire.components.inputs.numeric');
    }
}
