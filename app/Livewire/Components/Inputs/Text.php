<?php

namespace App\Livewire\Components\Inputs;

use Livewire\Attributes\On;
use Illuminate\Support\Facades\Cache;
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
    public string $cache_key;
    public bool $is_background_calc;

    public function mount($node_id,  $cache_key, $value = null)
    {
        $this->node_id = $node_id;
        $this->cache_key = $cache_key;
        $this->answers = $node_id;
        $this->value = $value;
        $node = Cache::get($cache_key)['full_nodes'][$this->node_id];

        if ($node['category'] === 'background_calculation') {
            $this->is_background_calc = true;
            foreach ($node["answers"] as $answer) {
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
            $this->description = null;
            $this->dispatch('nodeToSave', $this->node_id, $value, $answer_id, $this->answer);
        } else {
            $this->is_background_calc = false;
        }
    }

    public function render()
    {
        return view('livewire.components.inputs.text');
    }
}
