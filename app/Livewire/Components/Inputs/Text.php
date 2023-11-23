<?php

namespace App\Livewire\Components\Inputs;

use Livewire\Attributes\On;
use Livewire\Component;

class Text extends Component
{
    public $node_id;
    public $label;
    public $description;
    public $formula;
    public $value;
    public $answers;
    public $answer;

    public function mount($node, $value)
    {
        $this->node_id = $node['id'];
        $this->label = $node['label'];
        $this->description = $node['description'];
        $this->answers = $node['answers'];
        $this->value = $value;

        if ($node['category'] === 'background_calculation') {
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
            $this->dispatch('nodeToSave', $this->node_id, $value, $answer_id, $this->answer);
        }
    }

    public function render()
    {
        return view('livewire.components.inputs.text');
    }
}
